<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a server-side paginated, filterable list of tenant contracts.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EmployeeContract::class);

        $tenantId = $request->user()->tenant_id;
        $today = Carbon::today();

        $contracts = EmployeeContract::query()
            ->forTenant($tenantId)
            ->with(['employee:id,full_name,employee_number'])
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('contract_number', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($employee) => $employee->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('avana/kontrak', [
            'contracts' => [
                'data' => collect($contracts->items())
                    ->map(fn (EmployeeContract $contract): array => $this->transformContract($contract, $today))
                    ->all(),
                'meta' => [
                    'current_page' => $contracts->currentPage(),
                    'last_page' => $contracts->lastPage(),
                    'per_page' => $contracts->perPage(),
                    'total' => $contracts->total(),
                    'from' => $contracts->firstItem(),
                    'to' => $contracts->lastItem(),
                ],
            ],
            'employees' => Employee::forTenant($tenantId)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                ])
                ->all(),
            'stats' => [
                'total' => EmployeeContract::forTenant($tenantId)->count(),
                'active' => EmployeeContract::forTenant($tenantId)->where('status', 'active')->count(),
                'expiring_soon' => EmployeeContract::forTenant($tenantId)
                    ->where('status', 'active')
                    ->whereNotNull('end_date')
                    ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
                    ->count(),
            ],
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    /**
     * Persist a new contract under the authenticated user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', EmployeeContract::class);

        $tenantId = $request->user()->tenant_id;

        $validated = $this->validateContract($request, $tenantId);

        EmployeeContract::create([
            ...$validated,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.kontrak')
            ->with('success', 'Kontrak berhasil ditambahkan');
    }

    /**
     * Update an existing contract belonging to the acting user's tenant.
     */
    public function update(Request $request, EmployeeContract $contract): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $contract);
        $this->authorize('update', $contract);

        $validated = $this->validateContract($request, $request->user()->tenant_id, $contract);

        $contract->update($validated);

        return redirect()->route('avana.kontrak')
            ->with('success', 'Kontrak berhasil diperbarui');
    }

    /**
     * Delete a contract belonging to the acting user's tenant.
     */
    public function destroy(Request $request, EmployeeContract $contract): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $contract);
        $this->authorize('delete', $contract);

        $contract->delete();

        return back()->with('success', 'Kontrak dihapus');
    }

    /**
     * Validate the create/update payload, scoping uniqueness to the tenant.
     *
     * @return array<string, mixed>
     */
    private function validateContract(Request $request, ?int $tenantId, ?EmployeeContract $contract = null): array
    {
        $uniqueContractNumber = Rule::unique('employee_contracts', 'contract_number')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if ($contract !== null) {
            $uniqueContractNumber->ignore($contract->id);
        }

        return $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'contract_number' => ['required', 'string', 'max:255', $uniqueContractNumber],
            'contract_type' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,expired,terminated'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * Build the row shape consumed by the Kontrak DataTable.
     *
     * @return array<string, mixed>
     */
    private function transformContract(EmployeeContract $contract, Carbon $today): array
    {
        $daysToExpiry = $contract->end_date
            ? (int) round($today->diffInDays($contract->end_date, false))
            : null;

        $expiringSoon = $contract->status === 'active'
            && $daysToExpiry !== null
            && $daysToExpiry >= 0
            && $daysToExpiry <= 30;

        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'employee' => $contract->employee ? [
                'name' => $contract->employee->full_name,
                'employee_number' => $contract->employee->employee_number,
            ] : null,
            'employee_id' => $contract->employee_id,
            'contract_type' => $contract->contract_type,
            'start_date' => $contract->start_date?->toDateString(),
            'end_date' => $contract->end_date?->toDateString(),
            'basic_salary' => (float) $contract->basic_salary,
            'status' => $contract->status,
            'notes' => $contract->notes,
            'expiring_soon' => $expiringSoon,
            'days_to_expiry' => $daysToExpiry,
        ];
    }

    /**
     * Abort with 404 when the contract does not belong to the acting user's tenant.
     */
    private function ensureTenantOwnership(Request $request, EmployeeContract $contract): void
    {
        abort_if((int) $contract->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}

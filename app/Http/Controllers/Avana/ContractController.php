<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Support\ContractType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    /**
     * Contracts carry salary and personal data, so the file never lands on a
     * publicly reachable disk.
     */
    private const DISK = 'local';

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

        return Inertia::render('avana/kontrak/index', [
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
            'employees' => $this->employeeOptions($tenantId),
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
     * Show the form for creating a new contract.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', EmployeeContract::class);

        return Inertia::render('avana/kontrak/create', [
            'employees' => $this->employeeOptions($request->user()->tenant_id),
        ]);
    }

    /**
     * Show the form for editing an existing contract.
     */
    public function edit(Request $request, EmployeeContract $contract): Response
    {
        $this->ensureTenantOwnership($request, $contract);
        $this->authorize('update', $contract);

        return Inertia::render('avana/kontrak/edit', [
            'contract' => [
                'id' => $contract->id,
                'route_key' => $contract->public_id,
                'contract_number' => $contract->contract_number,
                'employee_id' => $contract->employee_id,
                'contract_type' => $contract->contract_type,
                'start_date' => $contract->start_date?->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
                'basic_salary' => (float) $contract->basic_salary,
                'document_name' => $contract->document_name,
                'document_size' => $contract->document_size !== null ? (int) $contract->document_size : null,
                'has_document' => $contract->document_path !== null,
                'status' => $contract->status,
                'notes' => $contract->notes,
            ],
            'employees' => $this->employeeOptions($request->user()->tenant_id),
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
        unset($validated['document']);

        $contract = EmployeeContract::create([
            ...$validated,
            'tenant_id' => $tenantId,
        ]);

        $this->storeDocument($request, $contract);

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
        unset($validated['document']);

        $contract->update($validated);

        $this->storeDocument($request, $contract);

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

        // The file goes with the record; a private disk full of orphans is
        // still personal data nobody is looking after.
        if ($contract->document_path !== null) {
            Storage::disk(self::DISK)->delete($contract->document_path);
        }

        $contract->delete();

        return back()->with('success', 'Kontrak dihapus');
    }

    /**
     * Serve a contract's document to someone allowed to see the contract.
     */
    public function download(Request $request, EmployeeContract $contract): StreamedResponse
    {
        $this->ensureTenantOwnership($request, $contract);
        $this->authorize('view', $contract);

        abort_if(
            $contract->document_path === null || ! Storage::disk(self::DISK)->exists($contract->document_path),
            404,
        );

        return Storage::disk(self::DISK)->download(
            $contract->document_path,
            $contract->document_name ?? 'kontrak.pdf',
        );
    }

    /**
     * Remove the attached document, keeping the contract itself.
     */
    public function destroyDocument(Request $request, EmployeeContract $contract): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $contract);
        $this->authorize('update', $contract);

        if ($contract->document_path !== null) {
            Storage::disk(self::DISK)->delete($contract->document_path);
        }

        $contract->update(['document_path' => null, 'document_name' => null, 'document_size' => null]);

        return back()->with('success', 'Dokumen kontrak dihapus');
    }

    /**
     * Store an uploaded contract document, replacing whatever it had before.
     */
    private function storeDocument(Request $request, EmployeeContract $contract): void
    {
        $file = $request->file('document');

        if ($file === null) {
            return;
        }

        if ($contract->document_path !== null) {
            Storage::disk(self::DISK)->delete($contract->document_path);
        }

        $contract->update([
            'document_path' => $file->store('kontrak/'.$contract->tenant_id, self::DISK),
            'document_name' => $file->getClientOriginalName(),
            'document_size' => $file->getSize(),
        ]);
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

        $request->merge([
            'contract_type' => ContractType::normalise($request->input('contract_type')),
        ]);

        return $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'contract_number' => ['required', 'string', 'max:255', $uniqueContractNumber],
            'contract_type' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,expired,terminated'],
            'notes' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'document.mimes' => 'Dokumen kontrak harus PDF atau gambar (JPG/PNG).',
            'document.max' => 'Ukuran dokumen kontrak maksimal 10 MB.',
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
            'route_key' => $contract->public_id,
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
            // The list is where someone checks whether a contract was ever
            // signed and filed, so it says so per row rather than making them
            // open each one to find out.
            'document' => $contract->document_path === null ? null : [
                'name' => $contract->document_name ?? 'kontrak.pdf',
                'size' => $contract->document_size !== null ? (int) $contract->document_size : null,
                'href' => route('avana.kontrak.dokumen', $contract),
            ],
        ];
    }

    /**
     * Build the tenant's selectable employee options for the contract form.
     *
     * @return array<int, array<string, mixed>>
     */
    private function employeeOptions(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ])
            ->all();
    }

    /**
     * Abort with 404 when the contract does not belong to the acting user's tenant.
     */
    private function ensureTenantOwnership(Request $request, EmployeeContract $contract): void
    {
        abort_if((int) $contract->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}

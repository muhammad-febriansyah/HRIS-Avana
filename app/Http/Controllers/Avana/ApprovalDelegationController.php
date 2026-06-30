<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\ApprovalDelegation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalDelegationController extends Controller
{
    /**
     * Roles that may always manage approval delegations within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Allowed delegation scope enum values.
     *
     * @var array<int, string>
     */
    private const SCOPES = ['leave', 'overtime', 'all'];

    /**
     * Indonesian labels for the scope enum.
     *
     * @var array<string, string>
     */
    private const SCOPE_LABELS = [
        'leave' => 'Cuti',
        'overtime' => 'Lembur',
        'all' => 'Semua',
    ];

    /**
     * Display the approval delegations for the acting user's tenant.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $delegations = ApprovalDelegation::forTenant($tenantId)
            ->with([
                'delegator:id,full_name,employee_number',
                'delegate:id,full_name,employee_number',
            ])
            ->latest('id')
            ->get()
            ->map(fn (ApprovalDelegation $delegation): array => $this->shapeDelegation($delegation));

        return Inertia::render('avana/delegasi/index', [
            'delegations' => $delegations,
            'employees' => $this->employeeOptions($tenantId),
            'scopes' => $this->scopeOptions(),
            'kpis' => [
                'active_delegations' => $delegations->where('is_active', true)->count(),
                'total_delegations' => $delegations->count(),
            ],
        ]);
    }

    /**
     * Persist a new approval delegation under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'delegator_id' => [
                'required',
                'integer',
                "exists:employees,id,tenant_id,{$tenantId}",
            ],
            'delegate_id' => [
                'required',
                'integer',
                'different:delegator_id',
                "exists:employees,id,tenant_id,{$tenantId}",
            ],
            'scope' => ['required', Rule::in(self::SCOPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        ApprovalDelegation::create([
            'tenant_id' => $tenantId,
            'delegator_id' => $data['delegator_id'],
            'delegate_id' => $data['delegate_id'],
            'scope' => $data['scope'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => true,
        ]);

        return redirect()->route('avana.delegasi')
            ->with('success', 'Delegasi approval dibuat');
    }

    /**
     * Toggle the active flag of a delegation.
     */
    public function toggle(Request $request, ApprovalDelegation $delegation): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $delegation);

        $delegation->update(['is_active' => ! $delegation->is_active]);

        return back()->with('success', $delegation->is_active ? 'Delegasi diaktifkan' : 'Delegasi dinonaktifkan');
    }

    /**
     * Delete a delegation.
     */
    public function destroy(Request $request, ApprovalDelegation $delegation): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $delegation);

        $delegation->delete();

        return back()->with('success', 'Delegasi dihapus');
    }

    /**
     * Build the row shape consumed by the delegations table.
     *
     * @return array<string, mixed>
     */
    private function shapeDelegation(ApprovalDelegation $delegation): array
    {
        return [
            'id' => $delegation->id,
            'delegator' => $this->shapeEmployee($delegation->delegator),
            'delegate' => $this->shapeEmployee($delegation->delegate),
            'scope' => $delegation->scope,
            'scope_label' => self::SCOPE_LABELS[$delegation->scope] ?? $delegation->scope,
            'start_date' => $delegation->start_date?->toDateString(),
            'end_date' => $delegation->end_date?->toDateString(),
            'is_active' => (bool) $delegation->is_active,
        ];
    }

    /**
     * Shape an employee relation into a name/number pair.
     *
     * @return array{name: string|null, employee_number: string|null}|null
     */
    private function shapeEmployee(?Employee $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        return [
            'name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
        ];
    }

    /**
     * Build the selectable employee option list for the acting tenant.
     *
     * @return Collection<int, array{id: int, name: string, employee_number: string|null}>
     */
    private function employeeOptions(int $tenantId): Collection
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ]);
    }

    /**
     * Build the `{ value, label }` list of delegation scopes.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function scopeOptions(): array
    {
        return collect(self::SCOPES)
            ->map(fn (string $scope): array => [
                'value' => $scope,
                'label' => self::SCOPE_LABELS[$scope] ?? $scope,
            ])
            ->all();
    }

    /**
     * Abort with 404 when the delegation does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, ApprovalDelegation $delegation): void
    {
        abort_if((int) $delegation->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasEmployeePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'employee.'));

        abort_unless($isPrivileged || $hasEmployeePermission, 403);
    }
}

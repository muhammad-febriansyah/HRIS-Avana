<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Benefit;
use App\Models\Employee;
use App\Models\EmployeeBenefit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BenefitController extends Controller
{
    /**
     * Roles that may always manage benefits within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Display the benefit master list together with employee assignments.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $benefits = Benefit::forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'value', 'description', 'status'])
            ->map(fn (Benefit $benefit): array => [
                'id' => $benefit->id,
                'code' => $benefit->code,
                'name' => $benefit->name,
                'type' => $benefit->type,
                'value' => (float) $benefit->value,
                'description' => $benefit->description,
                'status' => $benefit->status,
            ]);

        $assignments = EmployeeBenefit::forTenant($tenantId)
            ->with(['employee:id,full_name,employee_number', 'benefit:id,name,type'])
            ->latest('id')
            ->get()
            ->map(fn (EmployeeBenefit $assignment): array => [
                'id' => $assignment->id,
                'employee' => $assignment->employee === null ? null : [
                    'name' => $assignment->employee->full_name,
                    'employee_number' => $assignment->employee->employee_number,
                ],
                'benefit' => $assignment->benefit === null ? null : [
                    'name' => $assignment->benefit->name,
                    'type' => $assignment->benefit->type,
                ],
                'start_date' => $assignment->start_date,
                'end_date' => $assignment->end_date,
                'status' => $assignment->status,
            ]);

        $employees = Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ]);

        return Inertia::render('avana/benefit', [
            'benefits' => $benefits,
            'assignments' => $assignments,
            'employees' => $employees,
        ]);
    }

    /**
     * Persist a new benefit definition under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('benefits', 'code')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:insurance,allowance,facility'],
            'value' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        Benefit::create([
            'tenant_id' => $tenantId,
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'value' => $data['value'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);

        return redirect()->route('avana.benefit')
            ->with('success', 'Benefit berhasil ditambahkan');
    }

    /**
     * Update an existing benefit definition.
     */
    public function update(Request $request, Benefit $benefit): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $benefit);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('benefits', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($benefit->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:insurance,allowance,facility'],
            'value' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $benefit->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'value' => $data['value'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);

        return redirect()->route('avana.benefit')
            ->with('success', 'Benefit berhasil diperbarui');
    }

    /**
     * Delete a benefit definition.
     */
    public function destroy(Request $request, Benefit $benefit): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $benefit);

        $benefit->delete();

        return back()->with('success', 'Benefit dihapus');
    }

    /**
     * Assign a benefit to an employee within the acting user's tenant.
     */
    public function assign(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                "exists:employees,id,tenant_id,{$tenantId}",
            ],
            'benefit_id' => [
                'required',
                'integer',
                "exists:benefits,id,tenant_id,{$tenantId}",
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        EmployeeBenefit::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'benefit_id' => $data['benefit_id'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'active',
        ]);

        return redirect()->route('avana.benefit')
            ->with('success', 'Benefit berhasil ditetapkan ke karyawan');
    }

    /**
     * Remove a benefit assignment from an employee.
     */
    public function unassign(Request $request, EmployeeBenefit $employeeBenefit): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $employeeBenefit);

        $employeeBenefit->delete();

        return back()->with('success', 'Penetapan benefit dibatalkan');
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Benefit|EmployeeBenefit $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
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

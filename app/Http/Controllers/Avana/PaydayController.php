<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payday;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mapping Payday" (BPR payroll submenu): named payday schedules (pay-day of
 * month + cut-off) that employees are mapped to, so groups can be paid on
 * different dates (e.g. staff on the 25th, daily workers on the 1st).
 */
class PaydayController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;

        $paydays = Payday::forTenant($tenantId)
            ->withCount('employees')
            ->orderBy('pay_day')
            ->get()
            ->map(fn (Payday $p): array => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'pay_day' => $p->pay_day,
                'cut_off_day' => $p->cut_off_day,
                'description' => $p->description,
                'is_active' => $p->is_active,
                'employees_count' => $p->employees_count,
            ]);

        $employees = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'payday_id'])
            ->map(fn (Employee $e): array => [
                'id' => $e->id,
                'name' => $e->full_name,
                'payday_id' => $e->payday_id,
            ]);

        return Inertia::render('avana/payroll-payday/index', [
            'paydays' => $paydays,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $this->validated($request, $tenantId);

        Payday::create([
            'tenant_id' => $tenantId,
            ...$data,
        ]);

        return back()->with('success', 'Payday disimpan');
    }

    public function update(Request $request, Payday $payday): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $payday->tenant_id !== (int) $request->user()->tenant_id, 404);

        $data = $this->validated($request, (int) $payday->tenant_id, $payday->id);

        $payday->update($data);

        return back()->with('success', 'Payday diperbarui');
    }

    public function destroy(Request $request, Payday $payday): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $payday->tenant_id !== (int) $request->user()->tenant_id, 404);

        $payday->delete();

        return back()->with('success', 'Payday dihapus');
    }

    /**
     * Map the given employees to this payday schedule.
     */
    public function assign(Request $request, Payday $payday): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $payday->tenant_id !== (int) $request->user()->tenant_id, 404);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'employee_ids' => ['required', 'array'],
            'employee_ids.*' => ['integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
        ]);

        Employee::forTenant($tenantId)
            ->whereIn('id', $data['employee_ids'])
            ->update(['payday_id' => $payday->id]);

        return back()->with('success', 'Pegawai dipetakan ke payday');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        $code = Rule::unique('paydays', 'code')->where('tenant_id', $tenantId);

        if ($ignoreId !== null) {
            $code->ignore($ignoreId);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', $code],
            'name' => ['required', 'string', 'max:255'],
            'pay_day' => ['required', 'integer', 'min:1', 'max:31'],
            'cut_off_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $validated['cut_off_day'] = $validated['cut_off_day'] ?? null;
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasPermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'payroll.'));

        abort_unless($isPrivileged || $hasPermission, 403);
    }
}

<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\SalaryRapel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Rapel" (retroactive salary adjustment): HR records the old/new monthly
 * nominal of a component that changed effective from a past date; once approved
 * the engine back-pays the difference for the elapsed whole months in the period
 * whose window contains the posting date.
 */
class SalaryRapelController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;

        $rapels = SalaryRapel::forTenant($tenantId)
            ->with(['employee:id,full_name,employee_number', 'component:id,name', 'approver:id,name'])
            ->orderByDesc('posting_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (SalaryRapel $r): array {
                $months = $this->monthsBetween($r);
                $diff = (float) $r->new_amount - (float) $r->old_amount;

                return [
                    'id' => $r->id,
                    'employee' => $r->employee?->full_name,
                    'employee_number' => $r->employee?->employee_number,
                    'component' => $r->component?->name ?? $r->label,
                    'old_amount' => (float) $r->old_amount,
                    'new_amount' => (float) $r->new_amount,
                    'effective_from' => $r->effective_from?->toDateString(),
                    'posting_date' => $r->posting_date?->toDateString(),
                    'months' => $months,
                    'total' => round($diff * $months),
                    'reason' => $r->reason,
                    'status' => $r->status,
                    'approver' => $r->approver?->name,
                ];
            });

        return Inertia::render('avana/payroll-rapel/index', [
            'rapels' => $rapels,
            'employeeOptions' => Employee::forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $e): array => [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'nik' => $e->employee_number,
                ])->all(),
            'componentOptions' => PayrollComponent::forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (PayrollComponent $c): array => [
                    'id' => $c->id,
                    'name' => $c->name,
                ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'payroll_component_id' => ['nullable', 'integer', Rule::exists('payroll_components', 'id')->where('tenant_id', $tenantId)],
            'label' => ['nullable', 'string', 'max:255'],
            'old_amount' => ['required', 'numeric', 'min:0'],
            'new_amount' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'posting_date' => ['required', 'date', 'after_or_equal:effective_from'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        SalaryRapel::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'payroll_component_id' => $data['payroll_component_id'] ?? null,
            'label' => $data['label'] ?? null,
            'old_amount' => $data['old_amount'],
            'new_amount' => $data['new_amount'],
            'effective_from' => $data['effective_from'],
            'posting_date' => $data['posting_date'],
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'Rapel gaji disimpan');
    }

    public function approve(Request $request, SalaryRapel $rapel): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $rapel->tenant_id !== (int) $request->user()->tenant_id, 404);

        $rapel->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Rapel gaji disetujui');
    }

    public function destroy(Request $request, SalaryRapel $rapel): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $rapel->tenant_id !== (int) $request->user()->tenant_id, 404);

        $rapel->delete();

        return back()->with('success', 'Rapel gaji dihapus');
    }

    /**
     * Whole months of back-pay owed between the effective date and the posting
     * period's month.
     */
    private function monthsBetween(SalaryRapel $rapel): int
    {
        if ($rapel->effective_from === null || $rapel->posting_date === null) {
            return 0;
        }

        $effective = $rapel->effective_from->copy()->startOfMonth();
        $posting = $rapel->posting_date->copy()->startOfMonth();

        return $effective->lt($posting) ? $effective->diffInMonths($posting) : 0;
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

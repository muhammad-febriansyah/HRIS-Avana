<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\SalaryRapel;
use App\Models\User;
use App\Support\SalaryPeriodLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'payroll';

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

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
        $this->ensureCan($request, 'create');

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
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Rapel gaji disimpan');
    }

    public function approve(Request $request, SalaryRapel $rapel): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        abort_if((int) $rapel->tenant_id !== (int) $request->user()->tenant_id, 404);

        DB::transaction(function () use ($request, $rapel): void {
            // Locked while decided: two approvers clicking at once would both
            // read "pending" and both stamp their name on the payment.
            $locked = $rapel->newQuery()->whereKey($rapel->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Rapel gaji ini sudah diputuskan.']);
            }

            if ($locked->created_by !== null && (int) $locked->created_by === (int) $request->user()->id) {
                throw ValidationException::withMessages([
                    'status' => 'Rapel gaji tidak boleh disetujui oleh pembuatnya sendiri.',
                ]);
            }

            $paidPeriod = SalaryPeriodLock::paidPeriodFor((int) $locked->tenant_id, Carbon::parse($locked->posting_date));

            if ($paidPeriod !== null) {
                throw ValidationException::withMessages([
                    'status' => 'Periode '.($paidPeriod->name ?? $paidPeriod->code).' sudah dikunci — rapel gaji bertanggal itu tidak bisa disetujui lagi.',
                ]);
            }

            $locked->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        });

        return back()->with('success', 'Rapel gaji disetujui');
    }

    public function destroy(Request $request, SalaryRapel $rapel): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        abort_if((int) $rapel->tenant_id !== (int) $request->user()->tenant_id, 404);

        // Deleting an approved row whose period is already locked would remove
        // a line a payslip has stated and a bank file has paid.
        if ($rapel->status === 'approved'
            && SalaryPeriodLock::paidPeriodFor((int) $rapel->tenant_id, Carbon::parse($rapel->posting_date)) !== null
        ) {
            throw ValidationException::withMessages([
                'status' => 'Rapel gaji ini sudah dibayarkan pada periode terkunci — tidak bisa dihapus.',
            ]);
        }

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

    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }
}

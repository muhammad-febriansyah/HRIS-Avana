<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollCorrection;
use App\Models\User;
use App\Support\PrivateFile;
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
 * "Koreksi Gaji" (BPR manual 1.3.3): HR records a manual pay adjustment
 * (earning or deduction) per employee; once approved the engine applies it to
 * the payroll run whose period window contains the correction date.
 */
class PayrollCorrectionController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'payroll';

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $corrections = PayrollCorrection::forTenant($tenantId)
            ->with(['employee:id,full_name,employee_number', 'approver:id,name'])
            ->orderByDesc('correction_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PayrollCorrection $c): array => [
                'id' => $c->id,
                'employee' => $c->employee?->full_name,
                'employee_number' => $c->employee?->employee_number,
                'correction_date' => $c->correction_date?->toDateString(),
                'type' => $c->type,
                'amount' => (float) $c->amount,
                'points' => $c->points,
                'reason' => $c->reason,
                'status' => $c->status,
                'approver' => $c->approver?->name,
            ]);

        return Inertia::render('avana/payroll-koreksi/index', [
            'corrections' => $corrections,
            'employeeOptions' => Employee::forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $e): array => [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'nik' => $e->employee_number,
                ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'correction_date' => ['required', 'date'],
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'points' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:5120'],
        ]);

        $filePath = $request->hasFile('file')
            ? PrivateFile::store($request->file('file'), 'payroll-corrections')
            : null;

        PayrollCorrection::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'correction_date' => $data['correction_date'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'points' => $data['points'] ?? null,
            'reason' => $data['reason'],
            'file_path' => $filePath,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Koreksi gaji disimpan');
    }

    public function approve(Request $request, PayrollCorrection $correction): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        abort_if((int) $correction->tenant_id !== (int) $request->user()->tenant_id, 404);

        DB::transaction(function () use ($request, $correction): void {
            // Locked while decided: two approvers clicking at once would both
            // read "pending" and both stamp their name on the payment.
            $locked = $correction->newQuery()->whereKey($correction->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Koreksi gaji ini sudah diputuskan.']);
            }

            if ($locked->created_by !== null && (int) $locked->created_by === (int) $request->user()->id) {
                throw ValidationException::withMessages([
                    'status' => 'Koreksi gaji tidak boleh disetujui oleh pembuatnya sendiri.',
                ]);
            }

            $paidPeriod = SalaryPeriodLock::paidPeriodFor((int) $locked->tenant_id, Carbon::parse($locked->correction_date));

            if ($paidPeriod !== null) {
                throw ValidationException::withMessages([
                    'status' => 'Periode '.($paidPeriod->name ?? $paidPeriod->code).' sudah dikunci — koreksi gaji bertanggal itu tidak bisa disetujui lagi.',
                ]);
            }

            $locked->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        });

        return back()->with('success', 'Koreksi gaji disetujui');
    }

    public function destroy(Request $request, PayrollCorrection $correction): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        abort_if((int) $correction->tenant_id !== (int) $request->user()->tenant_id, 404);

        // Deleting an approved row whose period is already locked would remove
        // a line a payslip has stated and a bank file has paid.
        if ($correction->status === 'approved'
            && SalaryPeriodLock::paidPeriodFor((int) $correction->tenant_id, Carbon::parse($correction->correction_date)) !== null
        ) {
            throw ValidationException::withMessages([
                'status' => 'Koreksi gaji ini sudah dibayarkan pada periode terkunci — tidak bisa dihapus.',
            ]);
        }

        $correction->delete();

        return back()->with('success', 'Koreksi gaji dihapus');
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

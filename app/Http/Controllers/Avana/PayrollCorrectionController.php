<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollCorrection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

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
        $this->ensureCanManage($request);

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
            ? $request->file('file')->store('payroll-corrections', 'public')
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
        ]);

        return back()->with('success', 'Koreksi gaji disimpan');
    }

    public function approve(Request $request, PayrollCorrection $correction): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $correction->tenant_id !== (int) $request->user()->tenant_id, 404);

        $correction->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Koreksi gaji disetujui');
    }

    public function destroy(Request $request, PayrollCorrection $correction): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $correction->tenant_id !== (int) $request->user()->tenant_id, 404);

        $correction->delete();

        return back()->with('success', 'Koreksi gaji dihapus');
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

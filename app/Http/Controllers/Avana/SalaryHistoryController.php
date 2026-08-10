<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\User;
use App\Services\EmployeeSalaryWriter;
use App\Support\SalaryPeriodLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Riwayat Gaji": every version of every salary component an employee has held,
 * with the date it took effect, the reason it changed and who wrote it.
 *
 * A salary is versioned rather than overwritten, so this screen is where the
 * question "why is this person paid this much, and since when" is answered
 * without reading the database.
 */
class SalaryHistoryController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'payroll';

    /**
     * How many versions are listed when no employee is picked — enough to see
     * recent activity across the company without loading years of history.
     */
    private const RECENT_LIMIT = 200;

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $employeeId = $request->integer('employee_id') ?: null;
        $viewerId = (int) $request->user()->id;

        $versions = EmployeeSalaryComponent::forTenant($tenantId)
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->with([
                'employee:id,full_name,employee_number',
                'component:id,name,type',
                'salaryMaster:id,code,category',
                'createdBy:id,name',
                'contract:id,contract_number',
            ])
            ->orderByDesc('effective_start_date')
            ->orderByDesc('id')
            ->limit($employeeId !== null ? 1000 : self::RECENT_LIMIT)
            ->get()
            ->map(fn (EmployeeSalaryComponent $row): array => [
                'id' => $row->id,
                'employee' => $row->employee?->full_name,
                'employee_number' => $row->employee?->employee_number,
                'component' => $row->component?->name,
                'component_type' => $row->component?->type,
                'amount' => (float) $row->amount,
                'effective_start_date' => $row->effective_start_date?->toDateString(),
                'effective_end_date' => $row->effective_end_date?->toDateString(),
                'status' => $row->status ?? 'active',
                'reason' => $row->reason,
                'master' => $row->salaryMaster?->code,
                'contract' => $row->contract?->contract_number,
                'author' => $row->createdBy?->name,
                'can_approve' => ($row->status ?? 'active') === 'pending_approval'
                    && (int) $row->created_by !== $viewerId,
            ])
            ->values()
            ->all();

        return Inertia::render('avana/payroll-riwayat-gaji/index', [
            'versions' => $versions,
            'employeeId' => $employeeId,
            'employeeOptions' => Employee::forTenant($tenantId)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $e): array => [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'nik' => $e->employee_number,
                ])->all(),
        ]);
    }

    /**
     * Put a pending salary version into force.
     *
     * Whoever wrote the figure cannot be the one who approves it: a pay rise
     * that one person can both grant and sign off is not a control. The figure
     * it replaces is closed only now, so a rejected version never disturbs the
     * salary that was already running.
     */
    public function approve(Request $request, EmployeeSalaryComponent $version): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureOwnership($request, $version->tenant_id);

        if ($version->status !== 'pending_approval') {
            return back()->withErrors(['status' => 'Versi gaji ini tidak sedang menunggu persetujuan.']);
        }

        if ($version->created_by !== null && (int) $version->created_by === (int) $request->user()->id) {
            return back()->withErrors(['status' => 'Perubahan gaji tidak boleh disetujui oleh pembuatnya sendiri.']);
        }

        $refusal = SalaryPeriodLock::refusal(
            (int) $version->tenant_id,
            Carbon::parse($version->effective_start_date ?? now())->startOfDay(),
        );

        if ($refusal !== null) {
            return back()->withErrors(['status' => $refusal]);
        }

        EmployeeSalaryWriter::approve($version, (int) $request->user()->id);

        return back()->with('success', 'Perubahan gaji disetujui');
    }

    /**
     * Turn a pending version down. It stays on the record as cancelled — the
     * documentation keeps rejected drafts rather than deleting them.
     */
    public function reject(Request $request, EmployeeSalaryComponent $version): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureOwnership($request, $version->tenant_id);

        if ($version->status !== 'pending_approval') {
            return back()->withErrors(['status' => 'Versi gaji ini tidak sedang menunggu persetujuan.']);
        }

        $version->update([
            'status' => 'cancelled',
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Perubahan gaji ditolak');
    }

    private function ensureOwnership(Request $request, int|string|null $tenantId): void
    {
        abort_if((int) $tenantId !== (int) $request->user()->tenant_id, 404);
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

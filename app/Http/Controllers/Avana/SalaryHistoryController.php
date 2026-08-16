<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryChangeSet;
use App\Models\User;
use App\Services\EmployeeSalaryWriter;
use App\Services\SalaryMasterAssignment;
use App\Support\SalaryPeriodLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
     * How many salary-history rows are shown per page.
     */
    private const PER_PAGE = 25;

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
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('avana/payroll-riwayat-gaji/index', [
            'versions' => [
                'data' => $versions->getCollection()
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
                    ->all(),
                'meta' => [
                    'current_page' => $versions->currentPage(),
                    'last_page' => $versions->lastPage(),
                    'per_page' => $versions->perPage(),
                    'total' => $versions->total(),
                    'from' => $versions->firstItem(),
                    'to' => $versions->lastItem(),
                ],
            ],
            'batches' => $this->pendingBatches($tenantId, $viewerId),
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

        DB::transaction(function () use ($version, $request): void {
            Employee::forTenant((int) $version->tenant_id)
                ->whereKey($version->employee_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedVersion = EmployeeSalaryComponent::forTenant((int) $version->tenant_id)
                ->whereKey($version->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedVersion->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'status' => 'Versi gaji ini tidak sedang menunggu persetujuan.',
                ]);
            }

            if ($lockedVersion->created_by !== null && (int) $lockedVersion->created_by === (int) $request->user()->id) {
                throw ValidationException::withMessages([
                    'status' => 'Perubahan gaji tidak boleh disetujui oleh pembuatnya sendiri.',
                ]);
            }

            $refusal = SalaryPeriodLock::refusal(
                (int) $lockedVersion->tenant_id,
                Carbon::parse($lockedVersion->effective_start_date ?? now())->startOfDay(),
            );

            if ($refusal !== null) {
                throw ValidationException::withMessages(['status' => $refusal]);
            }

            $changeSet = $lockedVersion->changeSet()
                ->lockForUpdate()
                ->first();

            if ($changeSet !== null) {
                SalaryMasterAssignment::approveChangeSet($changeSet, (int) $request->user()->id);

                return;
            }

            EmployeeSalaryWriter::approve($lockedVersion, (int) $request->user()->id);
        });

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

        DB::transaction(function () use ($version, $request): void {
            Employee::forTenant((int) $version->tenant_id)
                ->whereKey($version->employee_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedVersion = EmployeeSalaryComponent::forTenant((int) $version->tenant_id)
                ->whereKey($version->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedVersion->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'status' => 'Versi gaji ini tidak sedang menunggu persetujuan.',
                ]);
            }

            $changeSet = $lockedVersion->changeSet()->lockForUpdate()->first();
            ($changeSet?->components()->where('status', 'pending_approval')->lockForUpdate()->get() ?? collect([$lockedVersion]))
                ->each(fn (EmployeeSalaryComponent $pending): bool => (bool) $pending->update([
                    'status' => 'cancelled',
                    'updated_by' => $request->user()->id,
                ]));

            $changeSet?->update(['status' => 'rejected']);
        });

        return back()->with('success', 'Perubahan gaji ditolak');
    }

    /**
     * Approve a whole Penetapan Gaji Massal run at once.
     *
     * Hundreds of one-by-one approvals are signed without being read, so the
     * run is reviewed as one thing — how many employees, how much the payroll
     * changes by, and which people carried an exception — and signed once. The
     * per-employee rules still hold: the preparer cannot approve their own run,
     * and a change into a locked period is refused.
     */
    public function approveBatch(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'approve');

        $tenantId = (int) $request->user()->tenant_id;
        $data = $request->validate(['batch_id' => ['required', 'string', 'max:32']]);
        $approverId = (int) $request->user()->id;

        $approved = DB::transaction(function () use ($tenantId, $data, $approverId): int {
            $changeSets = SalaryChangeSet::forTenant($tenantId)
                ->where('batch_id', $data['batch_id'])
                ->where('status', 'pending_approval')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($changeSets->isEmpty()) {
                throw ValidationException::withMessages([
                    'batch_id' => 'Tidak ada perubahan gaji yang menunggu persetujuan pada penetapan ini.',
                ]);
            }

            if ($changeSets->contains(fn (SalaryChangeSet $set): bool => (int) $set->created_by === $approverId)) {
                throw ValidationException::withMessages([
                    'batch_id' => 'Penetapan yang Anda buat sendiri harus disetujui orang lain.',
                ]);
            }

            foreach ($changeSets as $changeSet) {
                $refusal = SalaryPeriodLock::refusal(
                    $tenantId,
                    Carbon::parse($changeSet->effective_start_date ?? now())->startOfDay(),
                );

                if ($refusal !== null) {
                    throw ValidationException::withMessages(['batch_id' => $refusal]);
                }

                SalaryMasterAssignment::approveChangeSet($changeSet, $approverId);
            }

            return $changeSets->count();
        });

        return back()->with('success', $approved.' perubahan gaji disetujui sekaligus');
    }

    /** Turn a whole run down, with the reason recorded on every change set. */
    public function rejectBatch(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'approve');

        $tenantId = (int) $request->user()->tenant_id;
        $data = $request->validate([
            'batch_id' => ['required', 'string', 'max:32'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $rejected = DB::transaction(function () use ($tenantId, $data): int {
            $changeSets = SalaryChangeSet::forTenant($tenantId)
                ->where('batch_id', $data['batch_id'])
                ->where('status', 'pending_approval')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($changeSets as $changeSet) {
                $changeSet->components()
                    ->where('status', 'pending_approval')
                    ->update(['status' => 'rejected', 'reason' => $data['reason']]);

                $changeSet->update(['status' => 'rejected', 'reason' => $data['reason']]);
            }

            return $changeSets->count();
        });

        return back()->with('success', $rejected.' perubahan gaji ditolak');
    }

    /**
     * Mass assignment runs still waiting on an approver, summarised: who it
     * touches, what it costs, and what is unusual about it.
     *
     * @return list<array<string, mixed>>
     */
    private function pendingBatches(int $tenantId, int $viewerId): array
    {
        $changeSets = SalaryChangeSet::forTenant($tenantId)
            ->whereNotNull('batch_id')
            ->where('status', 'pending_approval')
            ->with(['components:id,salary_change_set_id,employee_id,payroll_component_id,amount,previous_amount,source_type', 'salaryMaster:id,code,category', 'createdBy:id,name'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('batch_id');

        return $changeSets
            ->map(function ($sets, string $batchId) use ($viewerId): array {
                $components = $sets->flatMap(fn (SalaryChangeSet $set) => $set->components);

                $newTotal = (float) $components->sum(fn (EmployeeSalaryComponent $row): float => (float) $row->amount);
                $oldTotal = (float) $components->sum(fn (EmployeeSalaryComponent $row): float => (float) ($row->previous_amount ?? 0));

                $first = $sets->first();

                return [
                    'batch_id' => $batchId,
                    'master' => $first->salaryMaster?->code,
                    'effective_start_date' => $first->effective_start_date?->toDateString(),
                    'strategy' => $first->existing_strategy,
                    'reason' => $first->reason,
                    'author' => $first->createdBy?->name,
                    'employee_count' => $sets->pluck('employee_id')->unique()->count(),
                    'component_count' => $components->count(),
                    'total_before' => $oldTotal,
                    'total_after' => $newTotal,
                    'total_delta' => $newTotal - $oldTotal,
                    // The people this run is not treating like everybody else:
                    // an approver should see them before signing.
                    'exception_count' => $components
                        ->where('source_type', 'employee_override')
                        ->pluck('employee_id')
                        ->unique()
                        ->count(),
                    'can_approve' => (int) $first->created_by !== $viewerId,
                ];
            })
            ->values()
            ->all();
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

<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\Avana\AttendanceResource;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Branch;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    use AppliesBranchScope;
    use AuthorizesRequests;

    /**
     * Sortable columns whitelist for the index DataTable.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['clock_in_at', 'created_at'];

    /**
     * Display the daily attendance rekap with status KPIs for a single date.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        $date = $this->resolveDate($request->query('date'));
        $dateString = $date->format('Y-m-d');

        $sort = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'created_at';

        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $query = Attendance::query()
            ->forTenant($tenantId)
            ->whereDate('date', $dateString)
            ->with([
                'employee:id,full_name,employee_number,branch_id',
                'employee.branch:id,name',
                'shift:id,name,start_time,end_time',
            ])
            ->when($request->query('search'), function ($query, $search): void {
                $query->whereHas('employee', function ($q) use ($search): void {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('branch_id'), fn ($q, $branchId) => $q->where('branch_id', $branchId));

        $this->applyBranchScope($query, $request->user());

        $attendances = $query
            ->orderBy($sort, $direction)
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $statusCountQuery = Attendance::forTenant($tenantId)
            ->whereDate('date', $dateString);

        $this->applyBranchScope($statusCountQuery, $request->user());

        $statusCounts = $statusCountQuery
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return Inertia::render('avana/absensi/index', [
            'attendances' => AttendanceResource::collection($attendances),
            'filters' => array_merge(
                $request->only(['search', 'status', 'branch_id', 'sort', 'direction', 'per_page']),
                ['date' => $dateString],
            ),
            'date' => [
                'value' => $dateString,
                'display' => $date->format('d M Y'),
            ],
            'kpis' => [
                'hadir' => (int) ($statusCounts['present'] ?? 0),
                'terlambat' => (int) ($statusCounts['late'] ?? 0),
                'izin' => (int) ($statusCounts['leave'] ?? 0),
                'alpa' => (int) ($statusCounts['absent'] ?? 0),
            ],
            'branches' => Branch::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Approve a pending attendance correction and sync the linked attendance.
     */
    public function approveCorrection(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $correction);
        $this->authorize('approveCorrection', Attendance::class);

        $correction->update([
            'status' => 'approved',
            'approver_id' => $request->user()->id,
        ]);

        $attendance = $correction->attendance;

        if ($attendance !== null) {
            $dateString = $correction->date->format('Y-m-d');
            $updates = ['status' => 'present'];

            if ($correction->requested_clock_in !== null) {
                $updates['clock_in_at'] = $dateString.' '.$correction->requested_clock_in;
            }

            if ($correction->requested_clock_out !== null) {
                $updates['clock_out_at'] = $dateString.' '.$correction->requested_clock_out;
            }

            $attendance->update($updates);
        }

        return back()->with('success', 'Koreksi absensi disetujui');
    }

    /**
     * Reject a pending attendance correction.
     */
    public function rejectCorrection(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $correction);
        $this->authorize('rejectCorrection', Attendance::class);

        $correction->update([
            'status' => 'rejected',
            'approver_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Koreksi absensi ditolak');
    }

    /**
     * Resolve the requested date (Y-m-d), defaulting to today when absent or invalid.
     */
    private function resolveDate(?string $input): Carbon
    {
        if (is_string($input) && $input !== '') {
            try {
                return Carbon::createFromFormat('Y-m-d', $input)->startOfDay();
            } catch (\Throwable) {
                return Carbon::today();
            }
        }

        return Carbon::today();
    }

    /**
     * Abort with 404 when the correction does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, AttendanceCorrection $correction): void
    {
        abort_if((int) $correction->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}

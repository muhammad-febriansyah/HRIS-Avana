<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\ShiftSchedule;
use App\Models\WfhRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Compact home-screen summary for the mobile dashboard: remaining leave,
 * hours worked this month, and how many requests are still pending.
 */
class DashboardController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * How many celebrants the home summary carries. The banner only draws three
     * faces and names, so a large tenant would otherwise ship dozens of rows
     * nobody renders on every pull-to-refresh; `birthdays_total` keeps the
     * headline count honest, and {@see self::birthdays()} serves the full list.
     */
    private const BIRTHDAY_PREVIEW_LIMIT = 12;

    public function summary(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $tenantId = $employee->tenant_id;
        $employeeId = $employee->id;

        $leaveAvailable = (float) LeaveBalance::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->where('year', now()->year)
            ->sum('remaining');

        $workMinutes = (int) Attendance::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->sum('work_minutes');

        $pending = 0;
        foreach ([LeaveRequest::class, OvertimeRequest::class, PermissionRequest::class, WfhRequest::class, Claim::class] as $model) {
            $pending += $model::forTenant($tenantId)
                ->where('employee_id', $employeeId)
                ->where('status', 'pending')
                ->count();
        }

        // This month's attendance breakdown for the home stat cards.
        $statusCounts = Attendance::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return response()->json(['data' => [
            'leave_available' => $leaveAvailable,
            'work_minutes_month' => $workMinutes,
            'work_hours_month' => round($workMinutes / 60, 1),
            'pending_count' => $pending,
            'attendance_month' => [
                'present' => (int) ($statusCounts['present'] ?? 0),
                'absent' => (int) ($statusCounts['absent'] ?? 0),
                'late' => (int) ($statusCounts['late'] ?? 0),
            ],
            'today_shift' => $this->todayShift($tenantId, $employeeId),
            'birthdays' => $this->birthdaysToday($tenantId, $employeeId, self::BIRTHDAY_PREVIEW_LIMIT),
            'birthdays_total' => $this->birthdayQuery($tenantId)->count(),
        ]]);
    }

    /** Everyone in the caller's tenant celebrating a birthday today. */
    public function birthdays(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        return response()->json([
            'data' => $this->birthdaysToday($employee->tenant_id, $employee->id),
        ]);
    }

    /**
     * Active colleagues in the caller's own tenant whose birthday falls today.
     *
     * The tenant filter is the caller's `tenant_id`, taken from their employee
     * record rather than the request, so the list can never span tenants.
     *
     * @param  int|null  $limit  Rows to return; null for the whole list.
     * @return array<int, array<string, mixed>>
     */
    private function birthdaysToday(int $tenantId, int $employeeId, ?int $limit = null): array
    {
        return $this->birthdayQuery($tenantId)
            ->with(['position:id,name', 'department:id,name'])
            ->orderBy('full_name')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'role' => $employee->position?->name ?? $employee->department?->name ?? 'Karyawan',
                'photo_url' => $employee->photo_path !== null
                    ? Storage::disk('public')->url($employee->photo_path)
                    : null,
                'initials' => $this->initials($employee->full_name),
                'avatar_color' => $this->avatarColor($employee->full_name),
                'is_me' => $employee->id === $employeeId,
            ])
            ->values()
            ->all();
    }

    /**
     * The tenant-scoped "birthday is today" query, shared by the preview slice
     * and its total so the two can never disagree.
     *
     * @return Builder<Employee>
     */
    private function birthdayQuery(int $tenantId): Builder
    {
        $today = now();

        return Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $today->month)
            ->whereDay('birth_date', $today->day);
    }

    private function initials(?string $fullName): string
    {
        $initials = collect(preg_split('/\s+/', trim((string) $fullName)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    private function avatarColor(?string $fullName): string
    {
        return self::AVATAR_PALETTE[crc32((string) $fullName) % count(self::AVATAR_PALETTE)];
    }

    /**
     * Today's shift for the home card: a scheduled shift, an explicit day off,
     * or null when the day has not been scheduled.
     *
     * @return array<string, mixed>|null
     */
    private function todayShift(int $tenantId, int $employeeId): ?array
    {
        $schedule = ShiftSchedule::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->whereDate('date', now()->toDateString())
            ->with('shift:id,name,start_time,end_time')
            ->first();

        if ($schedule === null) {
            return null;
        }

        $shift = $schedule->shift;

        return [
            'is_off' => $shift === null,
            'shift_name' => $shift?->name,
            'start' => $shift !== null ? substr((string) $shift->start_time, 0, 5) : null,
            'end' => $shift !== null ? substr((string) $shift->end_time, 0, 5) : null,
        ];
    }
}

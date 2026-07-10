<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Claim;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\ShiftSchedule;
use App\Models\WfhRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Compact home-screen summary for the mobile dashboard: remaining leave,
 * hours worked this month, and how many requests are still pending.
 */
class DashboardController extends Controller
{
    use ResolvesApiEmployee;

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

        return response()->json(['data' => [
            'leave_available' => $leaveAvailable,
            'work_minutes_month' => $workMinutes,
            'work_hours_month' => round($workMinutes / 60, 1),
            'pending_count' => $pending,
            'today_shift' => $this->todayShift($tenantId, $employeeId),
        ]]);
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

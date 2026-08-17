<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\TrackingLocation;
use App\Models\TrackingSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TrackingController extends Controller
{
    use AppliesBranchScope;
    use AuthorizesRequests;

    public function live(Request $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;
        $search = trim((string) $request->query('search', '')) ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $shiftId = $request->integer('shift_id') ?: null;
        $query = TrackingSession::query()
            ->forTenant($tenantId)
            ->where('status', TrackingSession::STATUS_ACTIVE)
            ->with([
                'employee:id,full_name,employee_number,branch_id,department_id,position_id',
                'employee.department:id,name',
                'employee.position:id,name',
                'attendance:id,shift_id,clock_in_at',
                'attendance.shift:id,name',
                'lastLocation',
            ])
            ->when($search !== null, function (Builder $builder) use ($search): void {
                $builder->whereHas('employee', function (Builder $employeeQuery) use ($search): void {
                    $employeeQuery->where(function (Builder $nameQuery) use ($search): void {
                        $nameQuery->where('full_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%");
                    });
                });
            })
            ->when($departmentId !== null, fn (Builder $builder) => $builder->whereHas(
                'employee',
                fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $departmentId),
            ))
            ->when($shiftId !== null, fn (Builder $builder) => $builder->whereHas(
                'attendance',
                fn (Builder $attendanceQuery) => $attendanceQuery->where('shift_id', $shiftId),
            ));

        $this->applyBranchScopeViaEmployee($query, $request->user());
        $employees = $query->latest('last_location_at')->get()->map(
            fn (TrackingSession $session): array => $this->liveShape($session),
        )->values();

        return Inertia::render('avana/tracking/live', [
            'employees' => $employees,
            'filters' => [
                'search' => $search,
                'department_id' => $departmentId,
                'shift_id' => $shiftId,
            ],
            'departments' => Department::forTenant($tenantId)->orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::forTenant($tenantId)->orderBy('name')->get(['id', 'name']),
            'polling_interval' => 10000,
        ]);
    }

    public function history(Request $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;
        $employeeId = $request->integer('employee_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $date = $request->date('date')?->toDateString();
        $query = TrackingSession::query()
            ->forTenant($tenantId)
            ->with([
                'employee:id,full_name,employee_number,branch_id,department_id',
                'employee.department:id,name',
                'attendance:id,clock_in_at,clock_out_at',
            ])
            ->withCount(['locations as accepted_points_count' => fn (Builder $builder) => $builder->where('is_accepted', true)])
            ->when($employeeId !== null, fn (Builder $builder) => $builder->where('employee_id', $employeeId))
            ->when($departmentId !== null, fn (Builder $builder) => $builder->whereHas(
                'employee',
                fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $departmentId),
            ))
            ->when($date !== null, fn (Builder $builder) => $builder->whereDate('started_at', $date));

        $this->applyBranchScopeViaEmployee($query, $request->user());
        $sessions = $query->latest('started_at')->paginate(20)->withQueryString();
        $employeeQuery = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->orderBy('full_name');
        $this->applyBranchScope($employeeQuery, $request->user());

        return Inertia::render('avana/tracking/history', [
            'sessions' => [
                'data' => $sessions->getCollection()->map(fn (TrackingSession $session): array => $this->historyShape($session)),
                'meta' => [
                    'current_page' => $sessions->currentPage(),
                    'last_page' => $sessions->lastPage(),
                    'total' => $sessions->total(),
                ],
            ],
            'filters' => [
                'employee_id' => $employeeId,
                'department_id' => $departmentId,
                'date' => $date,
            ],
            'employees' => $employeeQuery->get(['id', 'full_name', 'employee_number']),
            'departments' => Department::forTenant($tenantId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, TrackingSession $trackingSession): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $query = TrackingSession::query()
            ->forTenant($request->user()->tenant_id)
            ->with([
                'employee:id,full_name,employee_number,branch_id,department_id,position_id',
                'employee.department:id,name',
                'employee.position:id,name',
                'attendance:id,clock_in_at,clock_out_at',
            ]);
        $this->applyBranchScopeViaEmployee($query, $request->user());
        $session = $query->findOrFail($trackingSession->id);
        $pointCount = TrackingLocation::query()
            ->where('tracking_session_id', $session->id)
            ->where('is_accepted', true)
            ->count();
        $step = max(1, (int) ceil($pointCount / 2000));
        $firstPointId = TrackingLocation::query()
            ->where('tracking_session_id', $session->id)
            ->where('is_accepted', true)
            ->orderBy('recorded_at')
            ->value('id');
        $lastPointId = TrackingLocation::query()
            ->where('tracking_session_id', $session->id)
            ->where('is_accepted', true)
            ->latest('recorded_at')
            ->value('id');
        $endpointIds = array_values(array_filter([$firstPointId, $lastPointId]));
        $pointsQuery = TrackingLocation::query()
            ->where('tracking_session_id', $session->id)
            ->where('is_accepted', true);

        if ($step > 1) {
            $pointsQuery->where(function (Builder $builder) use ($step, $endpointIds): void {
                $builder->whereRaw('id % ? = 0', [$step])
                    ->orWhereIn('id', $endpointIds);
            });
        }

        $points = $pointsQuery->orderBy('recorded_at')->get([
            'id', 'latitude', 'longitude', 'accuracy', 'speed', 'is_mocked', 'is_suspicious', 'recorded_at',
        ]);

        return Inertia::render('avana/tracking/show', [
            'session' => [
                ...$this->historyShape($session),
                'position' => $session->employee?->position?->name,
                'points_count' => $pointCount,
                'last_sync' => $session->last_location_at?->toIso8601String(),
            ],
            'points' => $points->map(fn (TrackingLocation $point): array => [
                'latitude' => (float) $point->latitude,
                'longitude' => (float) $point->longitude,
                'accuracy' => (float) $point->accuracy,
                'speed' => $point->speed !== null ? (float) $point->speed : null,
                'is_mocked' => $point->is_mocked,
                'is_suspicious' => $point->is_suspicious,
                'recorded_at' => $point->recorded_at?->toIso8601String(),
            ])->values(),
            'sampled' => $step > 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function liveShape(TrackingSession $session): array
    {
        $last = $session->lastLocation;
        $age = $last?->recorded_at?->diffInSeconds(now()) ?? PHP_INT_MAX;
        $status = match (true) {
            $age <= 120 => ((float) ($last?->speed ?? 0)) >= 0.8 ? 'moving' : 'active',
            $age <= 600 => 'stale',
            default => 'offline',
        };
        // Recent trail (last 30 minutes, capped) used to draw the road-snapped
        // route on the live map. Only fetched per session, so keep it lean.
        $trail = TrackingLocation::query()
            ->where('tracking_session_id', $session->id)
            ->where('is_accepted', true)
            ->where('recorded_at', '>=', now()->subMinutes(30))
            ->orderBy('recorded_at')
            ->limit(30)
            ->get(['latitude', 'longitude', 'recorded_at']);

        return [
            'id' => $session->id,
            'employee_id' => $session->employee_id,
            'name' => $session->employee?->full_name,
            'employee_number' => $session->employee?->employee_number,
            'department' => $session->employee?->department?->name,
            'position' => $session->employee?->position?->name,
            'shift' => $session->attendance?->shift?->name,
            'status' => $status,
            'clock_in_at' => $session->attendance?->clock_in_at?->toIso8601String(),
            'started_at' => $session->started_at?->toIso8601String(),
            'duration_seconds' => max(0, (int) $session->started_at?->diffInSeconds(now())),
            'distance_meters' => (int) $session->total_distance_meters,
            'latitude' => $last !== null ? (float) $last->latitude : null,
            'longitude' => $last !== null ? (float) $last->longitude : null,
            'accuracy' => $last !== null ? (float) $last->accuracy : null,
            'speed' => $last?->speed !== null ? (float) $last->speed : null,
            'battery_level' => $last?->battery_level,
            'is_mocked' => (bool) $last?->is_mocked,
            'is_suspicious' => (bool) $last?->is_suspicious,
            'recorded_at' => $last?->recorded_at?->toIso8601String(),
            'trail' => $trail->map(fn (TrackingLocation $point): array => [
                'latitude' => (float) $point->latitude,
                'longitude' => (float) $point->longitude,
                'recorded_at' => $point->recorded_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function historyShape(TrackingSession $session): array
    {
        return [
            'id' => $session->id,
            'employee' => $session->employee?->full_name,
            'employee_number' => $session->employee?->employee_number,
            'department' => $session->employee?->department?->name,
            'status' => $session->status,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'duration_seconds' => (int) ($session->total_duration_seconds ?: $session->started_at?->diffInSeconds($session->ended_at ?? now())),
            'distance_meters' => (int) $session->total_distance_meters,
            'points_count' => (int) ($session->accepted_points_count ?? 0),
        ];
    }
}

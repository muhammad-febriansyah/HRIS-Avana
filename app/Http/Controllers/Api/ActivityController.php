<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSelfie;
use App\Models\Claim;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\WfhRequest;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Unified activity feed for the mobile "Riwayat" tab: recent attendance and
 * request events (leave, overtime, permission, WFH, reimbursement) merged and
 * sorted newest-first.
 */
class ActivityController extends Controller
{
    use ResolvesApiEmployee;

    private const PER_SOURCE = 15;

    private const LIMIT = 40;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $tenantId = $employee->tenant_id;
        $employeeId = $employee->id;

        /** @var Collection<int, array<string, mixed>> $items */
        $items = collect();

        $attendances = Attendance::forTenant($tenantId)->where('employee_id', $employeeId)
            ->whereNotNull('clock_in_at')
            ->orderByDesc('date')->limit(self::PER_SOURCE)->get();

        // The clock-in selfie (earliest per attendance), keyed by attendance id.
        $selfiePathByAttendance = AttendanceSelfie::whereIn('attendance_id', $attendances->pluck('id'))
            ->orderBy('id')
            ->get(['attendance_id', 'file_path'])
            ->groupBy('attendance_id')
            ->map(fn ($group): ?string => $group->first()->file_path);

        // Current face policy — the app hides "Akurasi Wajah" unless it is 1:1
        // recognition (detection/off have no confidence score to show).
        $faceMode = AttendancePolicy::where('tenant_id', $tenantId)->value('face_mode')
            ?? AttendancePolicy::FACE_MODE_RECOGNITION;

        $attendances->each(function (Attendance $a) use ($items, $selfiePathByAttendance, $faceMode): void {
            $in = $a->clock_in_at?->format('H:i');
            $out = $a->clock_out_at?->format('H:i');
            $selfiePath = $selfiePathByAttendance->get($a->id);

            $items->push($this->shape(
                'attendance',
                'Absensi',
                'Masuk '.($in ?? '--:--').' · Pulang '.($out ?? '--:--'),
                $a->status,
                $a->clock_in_at ?? $a->date,
                detail: [
                    'date' => $a->date instanceof Carbon ? $a->date->toDateString() : (string) $a->date,
                    'clock_in' => $in,
                    'clock_out' => $out,
                    'work_mode' => $a->work_mode,
                    'work_minutes' => (int) ($a->work_minutes ?? 0),
                    'late_minutes' => (int) ($a->late_minutes ?? 0),
                    'status' => $a->status,
                    'location_status' => $a->location_status,
                    'face_mode' => $faceMode,
                    'face_confidence' => $a->face_confidence !== null ? (float) $a->face_confidence : null,
                    'latitude' => $a->clock_in_lat !== null ? (float) $a->clock_in_lat : null,
                    'longitude' => $a->clock_in_lng !== null ? (float) $a->clock_in_lng : null,
                    'selfie_url' => PrivateFile::urlFor($selfiePath),
                ],
            ));
        });

        LeaveRequest::forTenant($tenantId)->where('employee_id', $employeeId)
            ->with('leaveType:id,name')
            ->orderByDesc('created_at')->limit(self::PER_SOURCE)->get()
            ->each(function (LeaveRequest $r) use ($items): void {
                $items->push($this->shape(
                    'leave',
                    'Pengajuan Cuti'.($r->leaveType ? ' — '.$r->leaveType->name : ''),
                    $this->range($r->start_date, $r->end_date),
                    $r->status,
                    $r->created_at,
                ));
            });

        OvertimeRequest::forTenant($tenantId)->where('employee_id', $employeeId)
            ->orderByDesc('created_at')->limit(self::PER_SOURCE)->get()
            ->each(function (OvertimeRequest $o) use ($items): void {
                $items->push($this->shape(
                    'overtime',
                    'Pengajuan Lembur',
                    ((float) $o->hours).' jam · '.$this->date($o->date),
                    $o->status,
                    $o->created_at,
                ));
            });

        PermissionRequest::forTenant($tenantId)->where('employee_id', $employeeId)
            ->orderByDesc('created_at')->limit(self::PER_SOURCE)->get()
            ->each(function (PermissionRequest $p) use ($items): void {
                $items->push($this->shape(
                    'permission',
                    'Izin'.($p->type ? ' — '.$p->type : ''),
                    $this->range($p->start_date, $p->end_date),
                    $p->status,
                    $p->created_at,
                ));
            });

        WfhRequest::forTenant($tenantId)->where('employee_id', $employeeId)
            ->orderByDesc('created_at')->limit(self::PER_SOURCE)->get()
            ->each(function (WfhRequest $w) use ($items): void {
                $items->push($this->shape(
                    'wfh',
                    'Pengajuan WFH',
                    $this->range($w->start_date, $w->end_date),
                    $w->status,
                    $w->created_at,
                ));
            });

        Claim::forTenant($tenantId)->where('employee_id', $employeeId)
            ->orderByDesc('created_at')->limit(self::PER_SOURCE)->get()
            ->each(function (Claim $c) use ($items): void {
                $items->push($this->shape(
                    'reimbursement',
                    $c->title !== null && $c->title !== '' ? $c->title : 'Reimbursement',
                    'Rp '.number_format((float) $c->amount, 0, ',', '.'),
                    $c->status,
                    $c->created_at,
                ));
            });

        $data = $items
            ->sortByDesc(fn (array $i): string => $i['occurred_at'] ?? '')
            ->values()
            ->take(self::LIMIT);

        return response()->json(['data' => $data]);
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function shape(string $type, string $title, string $subtitle, ?string $status, mixed $occurredAt, array $detail = []): array
    {
        $at = $occurredAt instanceof Carbon
            ? $occurredAt
            : ($occurredAt !== null ? Carbon::parse((string) $occurredAt) : null);

        return [
            'type' => $type,
            'title' => $title,
            'subtitle' => $subtitle,
            'status' => $status,
            'occurred_at' => $at?->toIso8601String(),
            'detail' => $detail === [] ? null : $detail,
        ];
    }

    private function range(mixed $start, mixed $end): string
    {
        $s = $this->date($start);
        $e = $this->date($end);

        return $s === $e ? $s : $s.' – '.$e;
    }

    private function date(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return $value instanceof Carbon
            ? $value->toDateString()
            : Carbon::parse((string) $value)->toDateString();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Claim;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\WfhRequest;
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

        Attendance::forTenant($tenantId)->where('employee_id', $employeeId)
            ->whereNotNull('clock_in_at')
            ->orderByDesc('date')->limit(self::PER_SOURCE)->get()
            ->each(function (Attendance $a) use ($items): void {
                $in = $a->clock_in_at?->format('H:i');
                $out = $a->clock_out_at?->format('H:i');
                $items->push($this->shape(
                    'attendance',
                    'Absensi',
                    'Masuk '.($in ?? '--:--').' · Pulang '.($out ?? '--:--'),
                    $a->status,
                    $a->clock_in_at ?? $a->date,
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
                    $this->date($p->date),
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
     * @return array<string, mixed>
     */
    private function shape(string $type, string $title, string $subtitle, ?string $status, mixed $occurredAt): array
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

<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\AttendanceCorrection;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PermissionRequest;
use App\Models\WfhRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds in-app notifications for the mobile feed and stamps each with a
 * deep-link payload so a tap can open the right detail screen.
 *
 * Every notification's `data` carries a `link` of the shape
 * `['type' => <screen>, 'id' => <resource id>]`, which the Flutter app maps to
 * a route. `type` is one of the request tags (leave/lembur/izin/wfh/koreksi/
 * reimburse), `payslip`, or `announcement`.
 */
final class Notifier
{
    /**
     * Request model class → the tag the mobile app routes on and its label.
     *
     * @var array<class-string<Model>, array{type: string, label: string}>
     */
    private const REQUEST_TYPES = [
        LeaveRequest::class => ['type' => 'leave', 'label' => 'Cuti'],
        OvertimeRequest::class => ['type' => 'lembur', 'label' => 'Lembur'],
        PermissionRequest::class => ['type' => 'izin', 'label' => 'Izin'],
        WfhRequest::class => ['type' => 'wfh', 'label' => 'WFH'],
        AttendanceCorrection::class => ['type' => 'koreksi', 'label' => 'Koreksi Absen'],
        Claim::class => ['type' => 'reimburse', 'label' => 'Reimbursement'],
    ];

    /**
     * Notify the employee who filed a request that it was approved or rejected.
     * No-op for models outside {@see self::REQUEST_TYPES} or when the requester
     * has no linked user account.
     */
    public static function requestDecided(Model $request, string $status): void
    {
        $meta = self::REQUEST_TYPES[$request::class] ?? null;

        if ($meta === null) {
            return;
        }

        $userId = self::userIdFor($request->employee_id);

        if ($userId === null) {
            return;
        }

        $approved = $status === 'approved';

        self::insertMany([[
            'tenant_id' => $request->tenant_id,
            'user_id' => $userId,
            'type' => 'approval',
            'title' => $meta['label'].' '.($approved ? 'disetujui' : 'ditolak'),
            'body' => 'Pengajuan '.$meta['label'].' Anda telah '
                .($approved ? 'disetujui' : 'ditolak').' oleh manajer.',
            'data' => [
                'link' => ['type' => $meta['type'], 'id' => $request->id],
                'status' => $status,
            ],
        ]]);
    }

    /** Notify the employee that their reimbursement claim has been paid out. */
    public static function reimbursementPaid(Claim $claim): void
    {
        $userId = self::userIdFor($claim->employee_id);

        if ($userId === null) {
            return;
        }

        self::insertMany([[
            'tenant_id' => $claim->tenant_id,
            'user_id' => $userId,
            'type' => 'reimburse',
            'title' => 'Reimbursement dibayar',
            'body' => 'Reimbursement '.($claim->title ?: 'Anda').' sebesar Rp '
                .number_format((float) $claim->amount, 0, ',', '.').' telah dibayar.',
            'data' => [
                'link' => ['type' => 'reimburse', 'id' => $claim->id],
                'status' => 'paid',
            ],
        ]]);
    }

    /**
     * Notify every active employee in the tenant that an announcement went live.
     */
    public static function announcementPublished(Announcement $announcement): void
    {
        $userIds = Employee::forTenant($announcement->tenant_id)
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $rows = $userIds->map(fn (int $userId): array => [
            'tenant_id' => $announcement->tenant_id,
            'user_id' => $userId,
            'type' => 'announcement',
            'title' => 'Pengumuman baru',
            'body' => $announcement->title,
            'data' => ['link' => ['type' => 'announcement', 'id' => $announcement->id]],
        ])->all();

        self::insertMany($rows);
    }

    /**
     * Notify each employee in a locked payroll run that their payslip is ready,
     * deep-linking to that employee's own run item.
     */
    public static function payrollLocked(PayrollRun $run): void
    {
        $items = PayrollRunItem::where('payroll_run_id', $run->id)
            ->with('employee:id,user_id')
            ->get(['id', 'tenant_id', 'employee_id', 'payroll_period_id']);

        $rows = $items
            ->filter(fn (PayrollRunItem $item): bool => $item->employee?->user_id !== null)
            ->map(fn (PayrollRunItem $item): array => [
                'tenant_id' => $item->tenant_id,
                'user_id' => $item->employee->user_id,
                'type' => 'payslip',
                'title' => 'Slip gaji tersedia',
                'body' => 'Slip gaji Anda sudah dapat dilihat dan diunduh.',
                'data' => ['link' => ['type' => 'payslip', 'id' => $item->id]],
            ])
            ->values()
            ->all();

        self::insertMany($rows);
    }

    private static function userIdFor(?int $employeeId): ?int
    {
        if ($employeeId === null) {
            return null;
        }

        return Employee::whereKey($employeeId)->value('user_id');
    }

    /**
     * Batch-insert notification rows in one query, JSON-encoding the payload and
     * stamping timestamps by hand (Eloquent casts/events are bypassed by insert).
     *
     * @param  array<int, array{tenant_id: int, user_id: int, type: string, title: string, body: string, data: array<string, mixed>}>  $rows
     */
    private static function insertMany(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $now = now();

        Notification::insert(array_map(fn (array $row): array => [
            'tenant_id' => $row['tenant_id'],
            'user_id' => $row['user_id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'body' => $row['body'],
            'data' => json_encode($row['data']),
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows));
    }
}

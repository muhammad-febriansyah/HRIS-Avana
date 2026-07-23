<?php

namespace App\Support;

use App\Jobs\SendBrandedNotificationJob;
use App\Mail\BrandedNotification;
use App\Models\Announcement;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PermissionRequest;
use App\Models\Reimbursement;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
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
        Reimbursement::class => ['type' => 'reimburse', 'label' => 'Reimbursement'],
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

        $approved = $status === 'approved';
        $userId = self::userIdFor($request->employee_id);

        if ($userId !== null) {
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

        self::emailEmployee(
            $request->employee_id,
            $meta['label'].' '.($approved ? 'Disetujui' : 'Ditolak'),
            'Pengajuan '.$meta['label'].' '.($approved ? 'disetujui' : 'ditolak'),
            ['Pengajuan '.$meta['label'].' Anda telah '.($approved ? 'disetujui' : 'ditolak').' oleh manajer.'],
            ['Jenis' => $meta['label'], 'Status' => $approved ? 'Disetujui' : 'Ditolak'],
        );
    }

    /**
     * Notify an employee that their own attendance punch (clock-in / clock-out)
     * was recorded. No-op when the employee has no linked user account.
     */
    public static function attendancePunch(
        int $tenantId,
        ?int $userId,
        string $kind,
        string $title,
        string $body,
        string $date,
    ): void {
        if ($userId === null) {
            return;
        }

        self::insertMany([[
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => 'attendance',
            'title' => $title,
            'body' => $body,
            'data' => [
                'link' => ['type' => 'attendance', 'id' => 0],
                'kind' => $kind,
                'date' => $date,
            ],
        ]]);
    }

    /** Notify the employee that their reimbursement claim has been paid out. */
    public static function reimbursementPaid(Reimbursement $claim): void
    {
        $amount = 'Rp '.number_format((float) $claim->amount, 0, ',', '.');
        $userId = self::userIdFor($claim->employee_id);

        if ($userId !== null) {
            self::insertMany([[
                'tenant_id' => $claim->tenant_id,
                'user_id' => $userId,
                'type' => 'reimburse',
                'title' => 'Reimbursement dibayar',
                'body' => 'Reimbursement '.($claim->title ?: 'Anda').' sebesar '.$amount.' telah dibayar.',
                'data' => [
                    'link' => ['type' => 'reimburse', 'id' => $claim->id],
                    'status' => 'paid',
                ],
            ]]);
        }

        self::emailEmployee(
            $claim->employee_id,
            'Reimbursement Dibayar',
            'Reimbursement Anda telah dibayar',
            ['Reimbursement '.($claim->title ?: 'Anda').' sebesar '.$amount.' telah dibayar.'],
            array_filter(['Keperluan' => $claim->title ?: null, 'Jumlah' => $amount]),
        );
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
            ->with('employee:id,user_id,tenant_id,email,full_name')
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

        foreach ($items as $item) {
            $employee = $item->employee;

            if ($employee === null || blank($employee->email)) {
                continue;
            }

            SendBrandedNotificationJob::dispatch(
                (int) $item->tenant_id,
                $employee->email,
                $employee->full_name,
                BrandedNotification::make(
                    tenantId: (int) $item->tenant_id,
                    subjectLine: 'Slip Gaji Tersedia',
                    heading: 'Slip gaji Anda sudah tersedia',
                    paragraphs: ['Slip gaji Anda untuk periode ini sudah dapat dilihat dan diunduh melalui aplikasi AvanaHR.'],
                    greetingName: $employee->full_name,
                ),
            );
        }
    }

    // ── Platform (super admin) billing notifications ─────────────────────
    //
    // Tier 1: money + revenue-risk events. Recipients are every user holding
    // the `super_admin` role, regardless of tenant — these are platform-level
    // alerts, not tenant-scoped HR ones. The notification row's `tenant_id`
    // carries the *subject* tenant so the bell can deep-link to that client.

    /**
     * Notify super admins (except the acting one) that a tenant invoice was
     * paid. Fired the moment an invoice's status flips to `paid`.
     */
    public static function invoicePaid(Invoice $invoice, ?int $excludeUserId = null): void
    {
        $amount = 'Rp '.number_format((float) $invoice->total, 0, ',', '.');

        self::platformNotify(
            type: 'invoice',
            tenantId: (int) $invoice->tenant_id,
            title: 'Invoice dibayar',
            body: 'Invoice '.$invoice->invoice_number.' ('.($invoice->tenant?->name ?? 'tenant').') '.$amount.' telah lunas.',
            data: [
                'link' => ['type' => 'invoice', 'id' => $invoice->id],
                'invoice_id' => $invoice->id,
                'event' => 'paid',
            ],
            excludeUserId: $excludeUserId,
        );
    }

    /**
     * Notify super admins that a tenant invoice is past its due date. At most
     * one notification per invoice per super admin (safe to run daily).
     */
    public static function invoiceOverdue(Invoice $invoice): void
    {
        $amount = 'Rp '.number_format((float) $invoice->total, 0, ',', '.');

        self::platformNotify(
            type: 'invoice',
            tenantId: (int) $invoice->tenant_id,
            title: 'Invoice jatuh tempo',
            body: 'Invoice '.$invoice->invoice_number.' ('.($invoice->tenant?->name ?? 'tenant').') '.$amount.' telah lewat jatuh tempo.',
            data: [
                'link' => ['type' => 'invoice', 'id' => $invoice->id],
                'invoice_id' => $invoice->id,
                'event' => 'overdue',
            ],
            dedupeColumn: 'invoice_id',
            dedupeValue: $invoice->id,
            dedupeEvent: 'overdue',
        );
    }

    /**
     * Notify super admins that a tenant subscription slipped to `past_due`.
     */
    public static function subscriptionPastDue(Subscription $subscription): void
    {
        self::platformNotify(
            type: 'subscription',
            tenantId: (int) $subscription->tenant_id,
            title: 'Langganan menunggak',
            body: 'Langganan '.($subscription->tenant?->name ?? 'tenant').' berstatus menunggak (past due).',
            data: [
                'link' => ['type' => 'subscription', 'id' => $subscription->id],
                'subscription_id' => $subscription->id,
                'event' => 'past_due',
            ],
        );
    }

    /**
     * Notify super admins that a tenant subscription is nearing its end date.
     * At most one notification per subscription per super admin.
     */
    public static function subscriptionExpiring(Subscription $subscription): void
    {
        $end = $subscription->end_date?->format('d M Y') ?? '-';

        self::platformNotify(
            type: 'subscription',
            tenantId: (int) $subscription->tenant_id,
            title: 'Langganan akan berakhir',
            body: 'Langganan '.($subscription->tenant?->name ?? 'tenant')
                .($subscription->package ? ' ('.$subscription->package->name.')' : '')
                .' berakhir pada '.$end.'.',
            data: [
                'link' => ['type' => 'subscription', 'id' => $subscription->id],
                'subscription_id' => $subscription->id,
                'event' => 'expiring',
            ],
            dedupeColumn: 'subscription_id',
            dedupeValue: $subscription->id,
            dedupeEvent: 'expiring',
        );
    }

    // ── Platform (super admin) tenant-lifecycle notifications ────────────
    //
    // Tier 2: client growth + churn signals. Same recipients/scoping as the
    // billing alerts; the notification row's `tenant_id` is the tenant itself.

    /**
     * Notify super admins (except the acting one) that a new client tenant was
     * added to the platform.
     */
    public static function tenantCreated(Tenant $tenant, ?int $excludeUserId = null): void
    {
        self::platformNotify(
            type: 'tenant',
            tenantId: (int) $tenant->id,
            title: 'Klien baru',
            body: 'Tenant baru '.$tenant->name.' telah ditambahkan ke platform.',
            data: [
                'link' => ['type' => 'tenant', 'id' => $tenant->id],
                'tenant_ref' => $tenant->id,
                'event' => 'created',
            ],
            excludeUserId: $excludeUserId,
        );
    }

    /**
     * Notify super admins (except the acting one) that a tenant converted from
     * trial to a paying (active) subscription.
     */
    public static function tenantActivated(Tenant $tenant, ?int $excludeUserId = null): void
    {
        self::platformNotify(
            type: 'tenant',
            tenantId: (int) $tenant->id,
            title: 'Konversi trial',
            body: 'Tenant '.$tenant->name.' aktif — konversi dari trial.',
            data: [
                'link' => ['type' => 'tenant', 'id' => $tenant->id],
                'tenant_ref' => $tenant->id,
                'event' => 'activated',
            ],
            excludeUserId: $excludeUserId,
        );
    }

    /**
     * Notify super admins (except the acting one) that a tenant was suspended or
     * deactivated — a churn signal. `$status` is `suspended` or `inactive`.
     */
    public static function tenantDeactivated(Tenant $tenant, string $status, ?int $excludeUserId = null): void
    {
        $label = $status === 'suspended' ? 'disuspend' : 'dinonaktifkan';

        self::platformNotify(
            type: 'tenant',
            tenantId: (int) $tenant->id,
            title: $status === 'suspended' ? 'Klien disuspend' : 'Klien nonaktif',
            body: 'Tenant '.$tenant->name.' telah '.$label.'.',
            data: [
                'link' => ['type' => 'tenant', 'id' => $tenant->id],
                'tenant_ref' => $tenant->id,
                'event' => $status,
            ],
            excludeUserId: $excludeUserId,
        );
    }

    /**
     * Insert a platform notification for every super admin, optionally skipping
     * the acting user and any super admin already notified for the same subject
     * event (the dedupe guard keeps recurring scans from re-alerting).
     *
     * @param  array<string, mixed>  $data
     */
    private static function platformNotify(
        string $type,
        int $tenantId,
        string $title,
        string $body,
        array $data,
        ?int $excludeUserId = null,
        ?string $dedupeColumn = null,
        int|string|null $dedupeValue = null,
        ?string $dedupeEvent = null,
    ): void {
        $recipients = User::query()
            ->whereHas('roles', fn ($query) => $query->where('code', 'super_admin'))
            ->when($excludeUserId !== null, fn ($query) => $query->where('id', '!=', $excludeUserId))
            ->pluck('id');

        $rows = [];

        foreach ($recipients as $userId) {
            if ($dedupeColumn !== null
                && self::platformAlreadyNotified((int) $userId, $type, $dedupeColumn, $dedupeValue, $dedupeEvent)) {
                continue;
            }

            $rows[] = [
                'tenant_id' => $tenantId,
                'user_id' => (int) $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ];
        }

        self::insertMany($rows);
    }

    /**
     * Whether the given super admin already holds a notification for this
     * subject event, keyed on a top-level `data` column plus the `event` tag.
     */
    private static function platformAlreadyNotified(int $userId, string $type, string $column, int|string|null $value, ?string $event): bool
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('data->'.$column, $value)
            ->when($event !== null, fn ($query) => $query->where('data->event', $event))
            ->exists();
    }

    /**
     * Queue a branded notification email to an employee (reachable even when
     * they have no linked app account). No-op when the employee or their email
     * is missing.
     *
     * @param  array<int, string>  $paragraphs
     * @param  array<string, string>  $details
     */
    private static function emailEmployee(?int $employeeId, string $subject, string $heading, array $paragraphs, array $details = []): void
    {
        if ($employeeId === null) {
            return;
        }

        $employee = Employee::query()
            ->select('id', 'tenant_id', 'email', 'full_name')
            ->find($employeeId);

        if ($employee === null || blank($employee->email)) {
            return;
        }

        SendBrandedNotificationJob::dispatch(
            (int) $employee->tenant_id,
            $employee->email,
            $employee->full_name,
            BrandedNotification::make(
                tenantId: (int) $employee->tenant_id,
                subjectLine: $subject,
                heading: $heading,
                paragraphs: $paragraphs,
                details: $details,
                greetingName: $employee->full_name,
            ),
        );
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

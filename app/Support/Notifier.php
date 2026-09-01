<?php

namespace App\Support;

use App\Jobs\SendBrandedNotificationJob;
use App\Mail\BrandedNotification;
use App\Models\Announcement;
use App\Models\AttendanceCorrection;
use App\Models\DataChangeRequest;
use App\Models\DutyTravel;
use App\Models\Employee;
use App\Models\EotmPeriod;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PermissionRequest;
use App\Models\Reimbursement;
use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WfhRequest;
use App\Services\ApprovalEngine;
use App\Services\FcmService;
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
        DutyTravel::class => ['type' => 'dinas', 'label' => 'Perjalanan Dinas'],
        DataChangeRequest::class => ['type' => 'data', 'label' => 'Perubahan Data'],
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
            $title = $meta['label'].' '.($approved ? 'disetujui' : 'ditolak');
            $body = 'Pengajuan '.$meta['label'].' Anda telah '
                .($approved ? 'disetujui' : 'ditolak').' oleh manajer.';

            self::insertMany([[
                'tenant_id' => $request->tenant_id,
                'user_id' => $userId,
                'type' => 'approval',
                'title' => $title,
                'body' => $body,
                'data' => [
                    'link' => ['type' => $meta['type'], 'id' => $request->id],
                    'status' => $status,
                ],
            ]]);

            app(FcmService::class)->pushToUsers(
                [$userId],
                $title,
                $body,
                ['type' => $meta['type'], 'id' => $request->id],
            );
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
     * Notify the people a request is now waiting on.
     *
     * Fired by {@see ApprovalEngine} when a submission is routed
     * to its first step and again on every advance. A workflow can name an
     * approver who holds no approval module and watches no queue — without
     * this, the request simply sits on their step unannounced.
     *
     * @param  array<int, int>  $approverEmployeeIds
     */
    public static function requestAwaitingApproval(Model $request, array $approverEmployeeIds): void
    {
        $meta = self::REQUEST_TYPES[$request::class] ?? null;
        $approverEmployeeIds = array_values(array_unique(array_filter($approverEmployeeIds)));

        if ($meta === null || $approverEmployeeIds === []) {
            return;
        }

        $requesterName = Employee::whereKey($request->employee_id)->value('full_name') ?? 'Karyawan';
        $title = 'Persetujuan '.$meta['label'];
        $body = 'Pengajuan '.$meta['label'].' dari '.$requesterName.' menunggu persetujuan Anda.';

        $approvers = Employee::query()
            ->whereKey($approverEmployeeIds)
            // The requester approving their own request is not an approval; a
            // workflow that lands back on them is skipped rather than announced.
            ->whereKeyNot($request->employee_id)
            ->get(['id', 'user_id']);

        $userIds = $approvers
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        self::insertMany(array_map(fn (int $userId): array => [
            'tenant_id' => $request->tenant_id,
            'user_id' => $userId,
            'type' => 'approval',
            'title' => $title,
            'body' => $body,
            'data' => [
                'link' => ['type' => $meta['type'], 'id' => $request->id],
                'status' => 'pending',
            ],
        ], $userIds));

        if ($userIds !== []) {
            app(FcmService::class)->pushToUsers(
                $userIds,
                $title,
                $body,
                ['type' => $meta['type'], 'id' => $request->id],
            );
        }

        foreach ($approvers as $approver) {
            self::emailEmployee(
                (int) $approver->id,
                $title,
                'Menunggu persetujuan Anda',
                [$body],
                ['Jenis' => $meta['label'], 'Pengaju' => $requesterName],
            );
        }
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
            $body = 'Reimbursement '.($claim->title ?: 'Anda').' sebesar '.$amount.' telah dibayar.';

            self::insertMany([[
                'tenant_id' => $claim->tenant_id,
                'user_id' => $userId,
                'type' => 'reimburse',
                'title' => 'Reimbursement dibayar',
                'body' => $body,
                'data' => [
                    'link' => ['type' => 'reimburse', 'id' => $claim->id],
                    'status' => 'paid',
                ],
            ]]);

            app(FcmService::class)->pushToUsers(
                [$userId],
                'Reimbursement dibayar',
                $body,
                ['type' => 'reimburse', 'id' => $claim->id],
            );
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
     * Tell whoever recorded a meeting that its transcript and summary are ready.
     *
     * The processing happens on a queue after the phone stops recording, which
     * can be minutes later — by then the app has usually been closed, so a push
     * is the only way they find out without going back to look.
     */
    public static function meetingSummaryReady(Meeting $meeting): void
    {
        $userId = $meeting->created_by;

        if ($userId === null) {
            return;
        }

        $ready = $meeting->status === Meeting::STATUS_READY;

        $title = $ready ? 'Ringkasan rapat siap' : 'Ringkasan rapat gagal';
        $body = $ready
            ? 'Transkrip & ringkasan "'.$meeting->title.'" sudah bisa dibuka.'
            : 'Rapat "'.$meeting->title.'" gagal diringkas. '.($meeting->failure_reason ?? '');

        self::insertMany([[
            'tenant_id' => $meeting->tenant_id,
            'user_id' => $userId,
            'type' => 'meeting',
            'title' => $title,
            'body' => trim($body),
            'data' => [
                'link' => ['type' => 'meeting', 'id' => $meeting->id],
                'status' => $meeting->status,
            ],
        ]]);

        app(FcmService::class)->pushToUsers(
            [$userId],
            $title,
            trim($body),
            ['type' => 'meeting', 'id' => $meeting->id],
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
     * Tell the author of a social post that someone commented on it.
     *
     * Commenting on your own post is silent — a notification about yourself is
     * noise, not news.
     */
    public static function socialPostCommented(SocialPost $post, Employee $commenter): void
    {
        if ((int) $post->employee_id === (int) $commenter->id) {
            return;
        }

        $userId = self::userIdFor($post->employee_id);

        if ($userId === null) {
            return;
        }

        $body = $commenter->full_name.' mengomentari postingan kamu';

        self::insertMany([[
            'tenant_id' => $post->tenant_id,
            'user_id' => $userId,
            'type' => 'social',
            'title' => 'Komentar baru',
            'body' => $body,
            'data' => ['link' => ['type' => 'social_post', 'id' => $post->id]],
        ]]);

        app(FcmService::class)->pushToUsers(
            [$userId],
            'Komentar baru',
            $body,
            ['type' => 'social_post', 'id' => $post->id],
        );
    }

    /**
     * Tell the author of a comment that someone replied to it.
     *
     * Skipped when they replied to themselves, and when they are also the post
     * author — that case already gets the "commented on your post" notice, and
     * two notifications for one reply is noise.
     */
    public static function socialCommentReplied(
        SocialPostComment $parent,
        Employee $replier,
        SocialPost $post,
    ): void {
        if ((int) $parent->employee_id === (int) $replier->id) {
            return;
        }

        if ((int) $parent->employee_id === (int) $post->employee_id) {
            return;
        }

        $userId = self::userIdFor($parent->employee_id);

        if ($userId === null) {
            return;
        }

        $body = $replier->full_name.' membalas komentar kamu';

        self::insertMany([[
            'tenant_id' => $post->tenant_id,
            'user_id' => $userId,
            'type' => 'social',
            'title' => 'Balasan baru',
            'body' => $body,
            'data' => ['link' => ['type' => 'social_post', 'id' => $post->id]],
        ]]);

        app(FcmService::class)->pushToUsers(
            [$userId],
            'Balasan baru',
            $body,
            ['type' => 'social_post', 'id' => $post->id],
        );
    }

    /**
     * Announce that Employee of the Month voting has opened, so the month does
     * not pass with a ballot nobody knew about.
     */
    public static function eotmPeriodOpened(EotmPeriod $period): void
    {
        $body = 'Voting '.$period->label().' sudah dibuka. Pilih rekan kerja terbaikmu.';

        self::notifyTenantEmployees(
            $period,
            'Voting Employee of the Month dibuka',
            $body,
            'eotm_open',
        );
    }

    /**
     * Announce the winner once voting closes.
     */
    public static function eotmPeriodClosed(EotmPeriod $period): void
    {
        $winner = $period->winner?->full_name;

        $body = $winner !== null
            ? 'Selamat kepada '.$winner.' — Employee of the Month '.$period->label().'!'
            : 'Voting '.$period->label().' telah ditutup.';

        self::notifyTenantEmployees(
            $period,
            'Employee of the Month '.$period->label(),
            $body,
            'eotm_result',
        );
    }

    /**
     * Fan a single message out to every active employee of the period's tenant,
     * in-app and as a push.
     */
    private static function notifyTenantEmployees(
        EotmPeriod $period,
        string $title,
        string $body,
        string $event,
    ): void {
        $userIds = Employee::forTenant($period->tenant_id)
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return;
        }

        self::insertMany($userIds->map(fn (int $userId): array => [
            'tenant_id' => $period->tenant_id,
            'user_id' => $userId,
            'type' => 'eotm',
            'title' => $title,
            'body' => $body,
            'data' => [
                'link' => ['type' => 'eotm', 'id' => $period->id],
                'event' => $event,
            ],
        ])->all());

        app(FcmService::class)->pushToUsers(
            $userIds->all(),
            $title,
            $body,
            ['type' => 'eotm', 'id' => $period->id],
        );
    }

    /**
     * Notify each employee in a locked payroll run that their payslip is ready,
     * deep-linking to that employee's own run item.
     */
    public static function payrollLocked(PayrollRun $run): void
    {
        $items = PayrollRunItem::where('tenant_id', $run->tenant_id)
            ->where('payroll_run_id', $run->id)
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

        if ($rows !== []) {
            app(FcmService::class)->pushToUsers(
                array_column($rows, 'user_id'),
                'Slip gaji tersedia',
                'Slip gaji Anda sudah dapat dilihat dan diunduh.',
                ['type' => 'payslip', 'id' => 0],
            );
        }

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

    /**
     * Warn the client's own admins that their subscription is running out.
     *
     * The platform alert ({@see subscriptionExpiring()}) goes to super admins;
     * this is the tenant-facing half, so recipients are the tenant's Admin
     * Tenant / HR accounts. `$milestone` (end date + days-left tag) both dedupes
     * the reminder and lets a renewal start a fresh series.
     */
    public static function tenantSubscriptionExpiring(
        Tenant $tenant,
        string $endDateLabel,
        int $daysLeft,
        string $milestone,
        ?string $packageName = null,
    ): int {
        $recipients = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($query) => $query->where('code', 'admin_tenant_hr'))
            ->pluck('id')
            ->reject(fn (int $userId): bool => Notification::query()
                ->where('user_id', $userId)
                ->where('type', 'subscription_expiring')
                ->where('data->milestone', $milestone)
                ->exists())
            ->values();

        if ($recipients->isEmpty()) {
            return 0;
        }

        $title = $daysLeft < 0 ? 'Langganan telah berakhir' : 'Langganan akan berakhir';
        $body = 'Langganan AvanaHR'.($packageName !== null ? ' ('.$packageName.')' : '')
            .' '.SubscriptionStatus::countdownLabel($daysLeft).' — '.$endDateLabel
            .'. Hubungi tim AvanaHR untuk perpanjangan.';

        self::insertMany($recipients->map(fn (int $userId): array => [
            'tenant_id' => $tenant->id,
            'user_id' => $userId,
            'type' => 'subscription_expiring',
            'title' => $title,
            'body' => $body,
            'data' => [
                'milestone' => $milestone,
                'days_left' => $daysLeft,
                'end_date' => $endDateLabel,
                'event' => $daysLeft < 0 ? 'expired' : 'expiring',
            ],
        ])->all());

        app(FcmService::class)->pushToUsers(
            $recipients->all(),
            $title,
            $body,
            ['type' => 'subscription', 'id' => $tenant->id],
        );

        return $recipients->count();
    }

    /**
     * Confirm a self-service renewal to the tenant's own admins, naming the new
     * expiry so the receipt answers the only question they have.
     */
    public static function tenantSubscriptionRenewed(Tenant $tenant, string $endDateLabel, string $packageName): void
    {
        $recipients = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($query) => $query->where('code', 'admin_tenant_hr'))
            ->pluck('id');

        if ($recipients->isEmpty()) {
            return;
        }

        $title = 'Langganan diperpanjang';
        $body = 'Pembayaran diterima. Langganan '.$packageName.' aktif sampai '.$endDateLabel.'.';

        self::insertMany($recipients->map(fn (int $userId): array => [
            'tenant_id' => $tenant->id,
            'user_id' => $userId,
            'type' => 'subscription_renewed',
            'title' => $title,
            'body' => $body,
            'data' => [
                'link' => ['type' => 'subscription', 'id' => $tenant->id],
                'end_date' => $endDateLabel,
                'event' => 'renewed',
            ],
        ])->all());

        app(FcmService::class)->pushToUsers(
            $recipients->all(),
            $title,
            $body,
            ['type' => 'subscription', 'id' => $tenant->id],
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
     * Warn super admins that a tenant is missing something provisioning was
     * meant to leave behind — a gap nobody inside the tenant can see, because
     * each one fails somewhere else entirely: no company profile refuses every
     * mobile login, no roles means 403 on every page, no menu means an empty
     * sidebar. Left alone these surface as a client complaint weeks later.
     *
     * At most one notification per tenant per super admin, so the daily scan
     * that raises this does not re-alert.
     *
     * @param  array<string, string>  $missing  prerequisite labels
     */
    public static function tenantProvisioningIncomplete(Tenant $tenant, array $missing): void
    {
        self::platformNotify(
            type: 'tenant',
            tenantId: (int) $tenant->id,
            title: 'Setup klien belum lengkap',
            body: 'Tenant '.$tenant->name.' belum punya '.implode(', ', $missing)
                .'. Jalankan `php artisan avana:periksa-tenant --fix` untuk melengkapinya.',
            data: [
                'link' => ['type' => 'tenant', 'id' => $tenant->id],
                'tenant_ref' => $tenant->id,
                'event' => 'provisioning_incomplete',
            ],
            dedupeColumn: 'tenant_ref',
            dedupeValue: $tenant->id,
            dedupeEvent: 'provisioning_incomplete',
        );
    }

    /**
     * Warn a user about something that happened to their own account: a login
     * from a device they have not used before, or a lockout they did not cause.
     *
     * Both the in-app feed and email are used on purpose — a security alert
     * that only lands inside the app is invisible to the one person who most
     * needs it, the account holder who has just been locked out of it.
     *
     * @param  array<string, string>  $details  label => value rows for the email
     */
    public static function securityAlert(
        User $user,
        string $event,
        string $title,
        string $body,
        array $details = [],
    ): void {
        // The in-app feed is tenant-scoped, so a platform account (super admin,
        // partner) has nowhere to file the row — those users get the email only.
        if ($user->tenant_id !== null) {
            self::insertMany([[
                'tenant_id' => (int) $user->tenant_id,
                'user_id' => (int) $user->id,
                'type' => 'security',
                'title' => $title,
                'body' => $body,
                // No deep link: the phone has no security screen to open, and
                // an unknown link type is worse than none.
                'data' => ['event' => $event],
            ]]);
        }

        if (blank($user->email)) {
            return;
        }

        // Null tenant, not 0: a platform account belongs to no tenant, and the
        // mail branding plus the email log both accept that.
        SendBrandedNotificationJob::dispatch(
            $user->tenant_id === null ? null : (int) $user->tenant_id,
            $user->email,
            $user->name,
            BrandedNotification::make(
                tenantId: $user->tenant_id === null ? null : (int) $user->tenant_id,
                subjectLine: $title,
                heading: $title,
                paragraphs: [
                    $body,
                    'Jika ini memang Anda, abaikan email ini. Jika bukan, segera ganti kata sandi dan aktifkan verifikasi dua langkah.',
                ],
                details: $details,
                greetingName: $user->name,
            ),
        );
    }

    /**
     * Raise a platform-wide security or operations alert to every super admin.
     *
     * Deduped per event key so a nightly scan that keeps finding the same thing
     * does not fill the feed with copies of one warning.
     *
     * @param  array<string, mixed>  $data
     */
    public static function platformAlert(
        string $event,
        string $title,
        string $body,
        array $data = [],
        int|string|null $dedupeValue = null,
    ): void {
        $tenantId = (int) (Tenant::query()->orderBy('id')->value('id') ?? 0);

        self::platformNotify(
            type: 'security',
            tenantId: $tenantId,
            title: $title,
            body: $body,
            data: $data + ['event' => $event, 'alert_ref' => $dedupeValue ?? $event],
            dedupeColumn: $dedupeValue === null ? null : 'alert_ref',
            dedupeValue: $dedupeValue,
            dedupeEvent: $event,
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

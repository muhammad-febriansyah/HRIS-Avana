<?php

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Notification;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->manager = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;
    $tenantId = $this->manager->tenant_id;

    // A direct report; give it a user account so it can receive + read notifs
    // (the demo seeder only links one employee to a login).
    $this->sub = Employee::forTenant($tenantId)
        ->where('id', '!=', $this->manager->id)
        ->where('status', 'active')
        ->firstOrFail();

    $subUser = User::create([
        'name' => $this->sub->full_name,
        'email' => 'sub.notif@nusantara.co.id',
        'tenant_id' => $tenantId,
        'password' => bcrypt('password'),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $this->sub->update(['manager_id' => $this->manager->id, 'user_id' => $subUser->id]);
    $this->sub->refresh();

    $this->leave = LeaveRequest::create([
        'tenant_id' => $tenantId, 'employee_id' => $this->sub->id,
        'leave_type_id' => LeaveType::forTenant($tenantId)->firstOrFail()->id,
        'start_date' => '2026-08-01', 'end_date' => '2026-08-02', 'total_days' => 2,
        'reason' => 'Acara keluarga', 'current_approver_id' => $this->manager->id, 'status' => 'pending',
    ]);

    $this->managerToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->asManager = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->managerToken);
    };

    $this->asSub = function () {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $this->sub->user->email,
            'password' => 'password',
        ])->json('access_token');

        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };
});

it('notifies the requester when a manager approves via the API', function (): void {
    ($this->asManager)()
        ->postJson('/api/v1/mss/approvals/leave-'.$this->leave->id.'/act', ['action' => 'approve'])
        ->assertOk();

    $notification = Notification::where('user_id', $this->sub->user_id)
        ->where('type', 'approval')
        ->firstOrFail();

    expect($notification->data)->toMatchArray([
        'link' => ['type' => 'leave', 'id' => $this->leave->id],
        'status' => 'approved',
    ]);
    expect($notification->title)->toBe('Cuti disetujui');
});

it('notifies the requester with a rejected status when rejected', function (): void {
    $this->leave->update(['status' => 'rejected']);

    $notification = Notification::where('user_id', $this->sub->user_id)
        ->where('type', 'approval')
        ->firstOrFail();

    expect($notification->data['status'])->toBe('rejected');
    expect($notification->title)->toBe('Cuti ditolak');
});

it('does not notify when a non-status field changes', function (): void {
    $this->leave->update(['reason' => 'Alasan diperbarui']);

    expect(Notification::where('user_id', $this->sub->user_id)->where('type', 'approval')->exists())->toBeFalse();
});

it('surfaces the decision in the requester feed and updates the unread badge', function (): void {
    ($this->asManager)()
        ->postJson('/api/v1/mss/approvals/leave-'.$this->leave->id.'/act', ['action' => 'approve'])
        ->assertOk();

    $feed = ($this->asSub)()->getJson('/api/v1/me/notifications')->assertOk();

    $unreadBefore = $feed->json('meta.unread');
    expect($unreadBefore)->toBeGreaterThanOrEqual(1);

    $notificationId = collect($feed->json('data'))
        ->firstWhere('type', 'approval')['id'];

    ($this->asSub)()
        ->postJson('/api/v1/me/notifications/'.$notificationId.'/read')
        ->assertOk()
        ->assertJsonPath('meta.unread', $unreadBefore - 1);

    $after = ($this->asSub)()->getJson('/api/v1/me/notifications')->assertOk();
    expect($after->json('meta.unread'))->toBe($unreadBefore - 1);
});

it('broadcasts an announcement notification to active employees on publish', function (): void {
    $tenantId = $this->manager->tenant_id;

    $announcement = Announcement::create([
        'tenant_id' => $tenantId,
        'title' => 'Libur nasional',
        'body' => 'Kantor tutup Jumat.',
        'status' => 'draft',
    ]);

    Notification::query()->delete();
    $announcement->update(['status' => 'published']);

    $activeEmployees = Employee::forTenant($tenantId)->where('status', 'active')->whereNotNull('user_id')->count();

    $notifications = Notification::where('type', 'announcement')->get();
    expect($notifications)->toHaveCount($activeEmployees);
    expect($notifications->first()->data['link'])->toMatchArray(['type' => 'announcement', 'id' => $announcement->id]);
});

it('does not re-broadcast an already-published announcement', function (): void {
    $announcement = Announcement::create([
        'tenant_id' => $this->manager->tenant_id,
        'title' => 'Sudah terbit', 'body' => 'x', 'status' => 'published',
    ]);

    Notification::query()->delete();
    $announcement->update(['title' => 'Judul diedit']);

    expect(Notification::where('type', 'announcement')->count())->toBe(0);
});

it('notifies each paid employee when a payroll run is locked', function (): void {
    $tenantId = $this->manager->tenant_id;

    $period = PayrollPeriod::create([
        'tenant_id' => $tenantId, 'code' => 'PRD-TEST', 'name' => 'Test Period',
    ]);
    $run = PayrollRun::create([
        'tenant_id' => $tenantId, 'payroll_period_id' => $period->id, 'status' => 'approved',
    ]);
    $item = PayrollRunItem::create([
        'tenant_id' => $tenantId, 'payroll_run_id' => $run->id,
        'payroll_period_id' => $period->id, 'employee_id' => $this->sub->id, 'net_salary' => 5000000,
    ]);

    $run->update(['status' => 'locked']);

    $notification = Notification::where('user_id', $this->sub->user_id)
        ->where('type', 'payslip')
        ->firstOrFail();

    expect($notification->data['link'])->toMatchArray(['type' => 'payslip', 'id' => $item->id]);
});

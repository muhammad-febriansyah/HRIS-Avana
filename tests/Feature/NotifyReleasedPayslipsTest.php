<?php

use App\Models\Notification;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;
    $this->period = PayrollPeriod::create([
        'tenant_id' => $this->employee->tenant_id,
        'code' => 'NOTIF-SLIP',
        'name' => 'Notifikasi Slip',
    ]);
    $this->run = PayrollRun::create([
        'tenant_id' => $this->employee->tenant_id,
        'payroll_period_id' => $this->period->id,
        'status' => PayrollRun::STATUS_APPROVED,
    ]);
});

function releasedNotificationItem(object $context, string $releaseDate): PayrollRunItem
{
    return PayrollRunItem::create([
        'tenant_id' => $context->employee->tenant_id,
        'payroll_run_id' => $context->run->id,
        'payroll_period_id' => $context->period->id,
        'employee_id' => $context->employee->id,
        'net_salary' => 5_000_000,
        'released_at' => $releaseDate,
    ]);
}

it('notifies only when a locked payslip reaches its release date and does not duplicate', function (): void {
    $item = releasedNotificationItem($this, Carbon::today()->addDays(2)->toDateString());

    $this->run->update(['status' => PayrollRun::STATUS_LOCKED]);

    expect(Notification::where('type', 'payslip')->exists())->toBeFalse();
    $this->artisan('avana:notify-released-payslips')->assertSuccessful();
    expect(Notification::where('type', 'payslip')->exists())->toBeFalse();

    $this->travel(2)->days();
    $this->artisan('avana:notify-released-payslips')->assertSuccessful();

    $notification = Notification::where('user_id', $this->employee->user_id)
        ->where('type', 'payslip')
        ->firstOrFail();

    expect($notification->data['link'])->toMatchArray(['type' => 'payslip', 'id' => $item->id]);
    expect($item->fresh()->release_notified_at)->not->toBeNull();

    $this->artisan('avana:notify-released-payslips')->assertSuccessful();
    expect(Notification::where('type', 'payslip')->count())->toBe(1);
});

it('does not notify a superseded payroll revision when its date arrives', function (): void {
    releasedNotificationItem($this, Carbon::today()->addDay()->toDateString());
    $this->run->update(['status' => PayrollRun::STATUS_LOCKED]);
    $this->run->update(['superseded_at' => now()]);

    $this->travelTo(Carbon::tomorrow());
    $this->artisan('avana:notify-released-payslips')->assertSuccessful();

    expect(Notification::where('type', 'payslip')->exists())->toBeFalse();
});

<?php

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->orderBy('id')->firstOrFail();
});

it('notifies HR of a contract expiring within the window', function (): void {
    EmployeeContract::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'contract_number' => 'CT-EXP-1',
        'contract_type' => 'pkwt',
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
        'basic_salary' => 5_000_000,
        'status' => 'active',
    ]);

    artisan('avana:remind-expiring-contracts')->assertSuccessful();

    $notifications = Notification::forTenant($this->tenant->id)->where('type', 'contract_expiring')->get();

    expect($notifications)->not->toBeEmpty();
    expect($notifications->first()->data['employee_id'])->toBe($this->employee->id);
});

it('does not duplicate a notification on a second run', function (): void {
    EmployeeContract::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'contract_number' => 'CT-EXP-2',
        'contract_type' => 'pkwt',
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'basic_salary' => 5_000_000,
        'status' => 'active',
    ]);

    artisan('avana:remind-expiring-contracts')->assertSuccessful();
    $after1 = Notification::forTenant($this->tenant->id)->where('type', 'contract_expiring')->count();

    artisan('avana:remind-expiring-contracts')->assertSuccessful();
    $after2 = Notification::forTenant($this->tenant->id)->where('type', 'contract_expiring')->count();

    expect($after2)->toBe($after1);
});

it('ignores contracts outside the window', function (): void {
    EmployeeContract::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'contract_number' => 'CT-FAR',
        'contract_type' => 'pkwt',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(200)->toDateString(),
        'basic_salary' => 5_000_000,
        'status' => 'active',
    ]);

    artisan('avana:remind-expiring-contracts')->assertSuccessful();

    expect(Notification::forTenant($this->tenant->id)->where('type', 'contract_expiring')->count())->toBe(0);
});

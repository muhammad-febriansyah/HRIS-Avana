<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

it('deactivates a past-resign-date employee and revokes their login', function (): void {
    $tenant = Tenant::create(['name' => 'PT Flag Resign', 'slug' => 'pt-flag-resign']);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'keluar@flagresign.test',
        'status' => 'active',
        'token_version' => 1,
    ]);
    $employee = Employee::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'employee_number' => 'EMP-FLAG-1',
        'full_name' => 'Sudah Keluar',
        'employment_status' => 'permanent',
        'status' => 'active',
        'resign_date' => Carbon::yesterday(),
    ]);

    $this->artisan('avana:flag-resigned-employees')->assertExitCode(0);

    expect($employee->fresh()->status)->toBe('inactive')
        ->and($user->fresh()->status)->toBe('inactive')
        // Saved through the model (not a mass update), so EmployeeObserver
        // fires and bumps the token version the same way a manual edit would.
        ->and($user->fresh()->token_version)->toBe(2)
        // The email is left alone until a new hire actually reuses it
        // (see EmployeeControllerTest) — not renamed just because the
        // resign date passed.
        ->and($user->fresh()->email)->toBe('keluar@flagresign.test');
});

it('leaves employees with a future or absent resign date untouched', function (): void {
    $tenant = Tenant::create(['name' => 'PT Flag Resign 2', 'slug' => 'pt-flag-resign-2']);
    $stillHere = Employee::create([
        'tenant_id' => $tenant->id,
        'employee_number' => 'EMP-FLAG-2',
        'full_name' => 'Masih Kerja',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $notYet = Employee::create([
        'tenant_id' => $tenant->id,
        'employee_number' => 'EMP-FLAG-3',
        'full_name' => 'Belum Waktunya',
        'employment_status' => 'permanent',
        'status' => 'active',
        'resign_date' => Carbon::tomorrow(),
    ]);

    $this->artisan('avana:flag-resigned-employees')->assertExitCode(0);

    expect($stillHere->fresh()->status)->toBe('active')
        ->and($notYet->fresh()->status)->toBe('active');
});

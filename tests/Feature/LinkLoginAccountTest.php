<?php

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenantId = (int) $this->admin->tenant_id;

    $this->orphanUser = User::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Akun Yatim',
        'email' => 'akun.yatim@example.com',
        'password' => 'password',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $this->orphanUser->roles()->sync([
        Role::where('tenant_id', $this->tenantId)->where('code', 'employee')->value('id'),
    ]);

    $this->employee = Employee::create([
        'tenant_id' => $this->tenantId,
        'employee_number' => 'UJI-LINK-1',
        'full_name' => 'Karyawan Tanpa Akun',
        'email' => 'karyawan.tanpa.akun@example.com',
        'status' => 'active',
        'employment_status' => 'permanent',
        'join_date' => now()->toDateString(),
    ]);
});

it('links an existing account to an employee who has none', function (): void {
    $this->actingAs($this->admin)
        ->post("/avana/employees/{$this->employee->public_id}/link-account", [
            'user_id' => $this->orphanUser->id,
        ]);

    expect($this->employee->fresh()->user_id)->toBe($this->orphanUser->id);
});

it('exposes the account email so a screen can show which address actually signs in', function (): void {
    $this->employee->forceFill(['user_id' => $this->orphanUser->id])->save();

    $this->actingAs($this->admin)
        ->get('/avana/employees?search=Karyawan Tanpa Akun')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'employees.data',
            fn ($rows) => collect($rows)
                ->firstWhere('email', 'karyawan.tanpa.akun@example.com')['login_email']
                    === 'akun.yatim@example.com',
        ));
});

it('refuses an account already behind another employee', function (): void {
    $taken = Employee::forTenant($this->tenantId)->whereNotNull('user_id')->firstOrFail();

    $this->actingAs($this->admin)
        ->post("/avana/employees/{$this->employee->public_id}/link-account", [
            'user_id' => $taken->user_id,
        ]);

    expect($this->employee->fresh()->user_id)->toBeNull();
});

it('refuses an account from another tenant', function (): void {
    $outsider = User::where('tenant_id', '!=', $this->tenantId)
        ->orWhereNull('tenant_id')
        ->first();

    if ($outsider === null) {
        $outsider = User::create([
            'tenant_id' => null,
            'name' => 'Orang Luar',
            'email' => 'orang.luar@example.com',
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    $this->actingAs($this->admin)
        ->post("/avana/employees/{$this->employee->public_id}/link-account", [
            'user_id' => $outsider->id,
        ]);

    expect($this->employee->fresh()->user_id)->toBeNull();
});

it('leaves the account email alone — it is what the mobile app authenticates', function (): void {
    $this->actingAs($this->admin)
        ->post("/avana/employees/{$this->employee->public_id}/link-account", [
            'user_id' => $this->orphanUser->id,
        ]);

    expect($this->orphanUser->fresh()->email)->toBe('akun.yatim@example.com');
});

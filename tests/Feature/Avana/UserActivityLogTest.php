<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivityLog;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
});

it('logs a login and a logout row for the acting user', function (): void {
    post(route('login'), [
        'email' => $this->admin->email,
        'password' => 'password',
    ])->assertRedirect();

    $login = UserActivityLog::where('user_id', $this->admin->id)->where('event', 'login')->firstOrFail();
    expect($login->tenant_id)->toBe($this->tenant->id);

    post(route('logout'))->assertRedirect();

    UserActivityLog::where('user_id', $this->admin->id)->where('event', 'logout')->firstOrFail();
});

it('logs a page_view row for an authenticated navigation, not for a partial reload', function (): void {
    actingAs($this->admin)->get(route('avana.audit'))->assertOk();

    UserActivityLog::where('user_id', $this->admin->id)
        ->where('event', 'page_view')
        ->where('path', 'avana/audit')
        ->firstOrFail();
});

it('mirrors a data-change audit row into the activity log', function (): void {
    actingAs($this->admin);

    Employee::create([
        'tenant_id' => $this->tenant->id,
        'employee_number' => 'EMP-ACT-1',
        'full_name' => 'Karyawan Aktivitas',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    $mirrored = UserActivityLog::where('user_id', $this->admin->id)
        ->where('event', 'data_created')
        ->firstOrFail();

    expect($mirrored->description)->toContain('Employee');
});

it('scopes the activity tab to the tenant admin\'s own tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain Aktivitas', 'slug' => 'pt-lain-aktivitas']);

    UserActivityLog::create([
        'tenant_id' => $otherTenant->id,
        'event' => 'login',
        'description' => 'Masuk dari tenant lain',
        'created_at' => now(),
    ]);

    UserActivityLog::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
        'event' => 'login',
        'description' => 'Masuk dari tenant sendiri',
        'created_at' => now(),
    ]);

    actingAs($this->admin)
        ->get(route('avana.audit', ['tab' => 'activity']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/audit/index', false)
            ->where('tab', 'activity')
            ->where('activity.data', fn ($rows) => collect($rows)
                ->pluck('description')
                ->doesntContain('Masuk dari tenant lain')));
});

it('lets a super admin see every tenant on the activity tab', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain Aktivitas 2', 'slug' => 'pt-lain-aktivitas-2']);

    UserActivityLog::create([
        'tenant_id' => $otherTenant->id,
        'event' => 'login',
        'description' => 'Masuk dari tenant lain',
        'created_at' => now(),
    ]);

    actingAs($this->superAdmin)
        ->get(route('avana.audit', ['tab' => 'activity']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.data', fn ($rows) => collect($rows)
                ->pluck('description')
                ->contains('Masuk dari tenant lain'))
            ->has('tenants'));
});

<?php

use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SubscriptionStatus;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->tenantAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->tenantAdmin->tenant_id);

    // Lapsed a week ago, with no live subscription row to override the date.
    Subscription::forTenant($this->tenant->id)->update(['status' => 'cancelled']);
    $this->tenant->update(['end_date' => now()->subWeek()->toDateString()]);
    SubscriptionStatus::forget();
});

it('locks every tenant screen once the term has lapsed', function (): void {
    actingAs($this->tenantAdmin)
        ->get('/dashboard')
        ->assertRedirect(route('avana.locked'));

    actingAs($this->tenantAdmin)
        ->get(route('avana.employees.index'))
        ->assertRedirect(route('avana.locked'));

    // Writes are closed too, not just the screens that render them.
    actingAs($this->tenantAdmin)
        ->post(route('avana.employees.store'), employeeCreatePayload($this->tenant->id, ['full_name' => 'Karyawan Baru']))
        ->assertRedirect(route('avana.locked'));
});

it('locks the employees of the tenant as well, not just the admin', function (): void {
    actingAs($this->employee)
        ->get(route('avana.saya.absensi'))
        ->assertRedirect(route('avana.locked'));
});

it('keeps the renewal page and the lock notice reachable while locked', function (): void {
    actingAs($this->tenantAdmin)
        ->get(route('avana.langganan'))
        ->assertOk();

    actingAs($this->tenantAdmin)
        ->get(route('avana.locked'))
        ->assertOk();
});

it('tells an employee on the lock notice that they cannot renew', function (): void {
    actingAs($this->employee)
        ->get(route('avana.locked'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/langganan/locked', false)
            ->where('canRenew', false)
            ->where('subscription.level', 'expired')
            ->etc());
});

it('locks the mobile API with a 402 the app can read', function (): void {
    $token = auth('api')->login($this->employee);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me/dashboard')
        ->assertStatus(402)
        ->assertJson([
            'locked' => true,
            'code' => 'subscription_expired',
        ]);
});

it('still lets the mobile app authenticate so it can show the reason', function (): void {
    $token = auth('api')->login($this->employee);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});

it('never locks a super admin out of a lapsed client', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.klien'))
        ->assertOk();
});

it('unlocks everything the moment the renewal is paid', function (): void {
    Http::fake(['app.pakasir.com/*' => Http::response(['transaction' => ['status' => 'completed']])]);

    $package = Package::create([
        'name' => 'Pro Lock',
        'code' => 'pro-lock',
        'price' => 500_000,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);

    actingAs($this->tenantAdmin)
        ->post(route('avana.langganan.purchase'), ['package_id' => $package->id, 'cycle' => 'monthly'])
        ->assertRedirect();

    $order = SubscriptionOrder::latest('id')->firstOrFail();

    actingAs($this->tenantAdmin)
        ->get(route('avana.langganan.callback', ['order' => $order->order_number]))
        ->assertRedirect(route('avana.langganan'));

    SubscriptionStatus::forget();

    actingAs($this->tenantAdmin)
        ->get('/dashboard')
        ->assertOk();

    actingAs($this->employee)
        ->get(route('avana.saya.absensi'))
        ->assertOk();
});

it('leaves an open-ended tenant alone', function (): void {
    $this->tenant->update(['end_date' => null]);
    SubscriptionStatus::forget();

    actingAs($this->tenantAdmin)
        ->get('/dashboard')
        ->assertOk();
});

<?php

use App\Models\Package;
use App\Models\ReferralConversion;
use App\Models\ReferralSetting;
use App\Models\SubscriptionOrder;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

/**
 * SubscriptionRenewalService::apply() files the invoice already `paid`, so
 * InvoiceObserver's `updated()` hook — the only other place referral
 * commission is credited from — never fires for it. Covers the fix that
 * makes self-service Pakasir purchases (the tenant's first-ever payment,
 * which now includes every self-serve signup) actually credit the
 * referring partner, same as a super admin marking an invoice paid by hand.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->tenantAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->tenantAdmin->tenant_id);
    $this->partner = createTestPartner();
    $this->tenant->update(['partner_id' => $this->partner->id, 'end_date' => now()->addDays(5)->toDateString()]);

    ReferralSetting::current()->update(['percent_rate' => 5]);

    $this->package = Package::create([
        'name' => 'Pro',
        'code' => 'pro-referral-test',
        'price' => 1_000_000,
        'billing_cycle' => 'monthly',
        'max_users' => 50,
        'max_employees' => 500,
        'max_branches' => 10,
        'is_active' => true,
    ]);
});

it('credits the referring partner once the self-service payment clears', function (): void {
    Http::fake(['app.pakasir.com/*' => Http::response(['transaction' => ['status' => 'completed', 'payment_method' => 'qris']])]);

    actingAs($this->tenantAdmin)->post(route('avana.langganan.purchase'), [
        'package_id' => $this->package->id,
        'cycle' => 'monthly',
    ]);

    $order = SubscriptionOrder::latest('id')->firstOrFail();

    actingAs($this->tenantAdmin)
        ->get(route('avana.langganan.callback', ['order' => $order->order_number]))
        ->assertRedirect(route('avana.langganan'));

    $conversion = ReferralConversion::where('tenant_id', $this->tenant->id)->first();

    expect($conversion)->not->toBeNull();
    expect($conversion->partner_id)->toBe($this->partner->id);
    expect((float) $conversion->commission_amount)->toBe(50000.0);
    expect($conversion->status)->toBe(ReferralConversion::STATUS_PENDING);
});

it('does not credit a tenant with no referring partner', function (): void {
    Http::fake(['app.pakasir.com/*' => Http::response(['transaction' => ['status' => 'completed']])]);

    $this->tenant->update(['partner_id' => null]);

    actingAs($this->tenantAdmin)->post(route('avana.langganan.purchase'), [
        'package_id' => $this->package->id,
        'cycle' => 'monthly',
    ]);

    $order = SubscriptionOrder::latest('id')->firstOrFail();

    actingAs($this->tenantAdmin)->get(route('avana.langganan.callback', ['order' => $order->order_number]));

    expect(ReferralConversion::where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
});

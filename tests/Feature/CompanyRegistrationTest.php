<?php

use App\Http\Middleware\CaptureReferral;
use App\Models\ReferralConversion;
use App\Models\ReferralLead;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    $this->partner = createTestPartner();
});

function validRegistration(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'PT Uji Mandiri',
        'phone' => '081200000000',
        'admin_name' => 'Sari Admin',
        'admin_email' => 'sari.admin@example.com',
        'admin_password' => 'Password123!',
        'admin_password_confirmation' => 'Password123!',
        'terms_accepted' => true,
    ], $overrides);
}

it('shows the self-serve wizard when a valid referral cookie is present', function (): void {
    $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->get('/daftar-perusahaan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/company-registration')
            ->where('partnerCode', $this->partner->code));
});

it('falls back to the plain inquiry form with no referral cookie', function (): void {
    $this->get('/daftar-perusahaan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/company-inquiry'));
});

it('falls back to the inquiry form once the referring partner is suspended', function (): void {
    $this->partner->update(['status' => 'suspended']);

    $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->get('/daftar-perusahaan')
        ->assertInertia(fn (Assert $page) => $page->component('public/company-inquiry'));
});

it('provisions a trial tenant and admin login, then signs the visitor in', function (): void {
    $response = $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan/daftar', validRegistration());

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $tenant = Tenant::where('name', 'PT Uji Mandiri')->first();
    expect($tenant)->not->toBeNull();
    expect($tenant->status)->toBe('trial');
    expect($tenant->package_id)->toBeNull();
    expect($tenant->partner_id)->toBe($this->partner->id);
    expect($tenant->end_date->toDateString())->toBe(now()->addDays(14)->toDateString());

    $admin = User::where('email', 'sari.admin@example.com')->first();
    expect($admin)->not->toBeNull();
    expect($admin->tenant_id)->toBe($tenant->id);
    expect($admin->roles()->where('code', 'admin_tenant_hr')->exists())->toBeTrue();
    expect(auth()->id())->toBe($admin->id);

    $lead = ReferralLead::where('tenant_id', $tenant->id)->first();
    expect($lead)->not->toBeNull();
    expect($lead->status)->toBe(ReferralLead::STATUS_CONVERTED);
    expect($lead->partner_id)->toBe($this->partner->id);
});

it('earns the referring partner no commission while the tenant is only on trial', function (): void {
    $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan/daftar', validRegistration());

    // A trial has no package and no invoice — ReferralConversion (the money)
    // is only ever created from a paid invoice (creditForInvoice()), so
    // signing up never earns commission by itself. The lead created above is
    // what makes the referral visible to the partner in the meantime.
    expect(ReferralConversion::count())->toBe(0);
    expect($this->partner->fresh()->balanceAmount())->toBe(0.0);
});

it('refuses to register without a valid referral cookie', function (): void {
    $response = $this->post('/daftar-perusahaan/daftar', validRegistration());

    $response->assertSessionHas('error');
    expect(Tenant::where('name', 'PT Uji Mandiri')->exists())->toBeFalse();
});

it('rejects a duplicate admin email', function (): void {
    User::create([
        'tenant_id' => null,
        'name' => 'Existing User',
        'email' => 'sari.admin@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $response = $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan/daftar', validRegistration());

    $response->assertSessionHasErrors('admin_email');
    expect(Tenant::where('name', 'PT Uji Mandiri')->exists())->toBeFalse();
});

it('rejects a mismatched password confirmation', function (): void {
    $response = $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan/daftar', validRegistration(['admin_password_confirmation' => 'Different123!']));

    $response->assertSessionHasErrors('admin_password');
    expect(Tenant::where('name', 'PT Uji Mandiri')->exists())->toBeFalse();
});

it('rejects an unaccepted terms checkbox', function (): void {
    $response = $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan/daftar', validRegistration(['terms_accepted' => false]));

    $response->assertSessionHasErrors('terms_accepted');
    expect(Tenant::where('name', 'PT Uji Mandiri')->exists())->toBeFalse();
});

it('rejects a phone number that is not a valid Indonesian mobile format', function (): void {
    $response = $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan/daftar', validRegistration(['phone' => 'Eiusmod voluptatem']));

    $response->assertSessionHasErrors('phone');
    expect(Tenant::where('name', 'PT Uji Mandiri')->exists())->toBeFalse();
});

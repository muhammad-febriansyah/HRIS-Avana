<?php

use App\Http\Middleware\CaptureReferral;
use App\Models\ReferralConversion;
use App\Models\TenantRegistration;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Hash;
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
        'industry' => 'Teknologi',
        'employee_count_range' => '11-50',
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
            ->where('partnerCode', $this->partner->code)
            ->where('partnerName', $this->partner->user->name));
});

it('uses the same company registration wizard for organic visitors', function (): void {
    $this->get('/daftar-perusahaan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/company-registration')
            ->where('partnerCode', null));
});

it('does not attribute a registration to a suspended partner', function (): void {
    $this->partner->update(['status' => 'suspended']);

    $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->get('/daftar-perusahaan')
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/company-registration')
            ->where('partnerCode', null));
});

it('queues a pending registration for super admin review instead of provisioning a tenant', function (): void {
    $response = $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan/daftar', validRegistration());

    $response->assertSessionHas('success');
    $this->assertGuest();

    $registration = TenantRegistration::where('company_name', 'PT Uji Mandiri')->first();
    expect($registration)->not->toBeNull();
    expect($registration->status)->toBe(TenantRegistration::STATUS_PENDING);
    expect($registration->partner_id)->toBe($this->partner->id);
    expect($registration->source)->toBe('referral');
    expect($registration->admin_email)->toBe('sari.admin@example.com');
    // Stored hashed, never the plain value.
    expect(Hash::check('Password123!', $registration->admin_password))->toBeTrue();

    expect(User::where('email', 'sari.admin@example.com')->exists())->toBeFalse();
});

it('earns the referring partner no commission from a pending registration', function (): void {
    $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan/daftar', validRegistration());

    // Nothing is provisioned until a super admin approves it — no tenant, no
    // invoice, so ReferralConversion (the money) never gets created here.
    expect(ReferralConversion::count())->toBe(0);
    expect($this->partner->fresh()->balanceAmount())->toBe(0.0);
});

it('queues an organic registration without referral attribution', function (): void {
    $response = $this->post('/daftar-perusahaan/daftar', validRegistration());

    $response->assertSessionHas('success');
    $registration = TenantRegistration::where('company_name', 'PT Uji Mandiri')->first();
    expect($registration)->not->toBeNull();
    expect($registration->partner_id)->toBeNull();
    expect($registration->source)->toBe('organic');
});

it('rejects a duplicate admin email already in use by a real account', function (): void {
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
    expect(TenantRegistration::where('company_name', 'PT Uji Mandiri')->exists())->toBeFalse();
});

it('rejects a duplicate admin email already pending review', function (): void {
    TenantRegistration::create([
        'company_name' => 'PT Lain',
        'phone' => '081200000099',
        'admin_name' => 'Orang Lain',
        'admin_email' => 'sari.admin@example.com',
        'admin_password' => Hash::make('Whatever123!'),
        'partner_id' => $this->partner->id,
        'status' => TenantRegistration::STATUS_PENDING,
    ]);

    $response = $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan/daftar', validRegistration());

    $response->assertSessionHasErrors('admin_email');
    expect(TenantRegistration::where('company_name', 'PT Uji Mandiri')->exists())->toBeFalse();
});

it('rejects a mismatched password confirmation', function (): void {
    $response = $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan/daftar', validRegistration(['admin_password_confirmation' => 'Different123!']));

    $response->assertSessionHasErrors('admin_password');
    expect(TenantRegistration::where('company_name', 'PT Uji Mandiri')->exists())->toBeFalse();
});

it('rejects an unaccepted terms checkbox', function (): void {
    $response = $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan/daftar', validRegistration(['terms_accepted' => false]));

    $response->assertSessionHasErrors('terms_accepted');
    expect(TenantRegistration::where('company_name', 'PT Uji Mandiri')->exists())->toBeFalse();
});

it('rejects a phone number that is not a valid Indonesian mobile format', function (): void {
    $response = $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan/daftar', validRegistration(['phone' => 'Eiusmod voluptatem']));

    $response->assertSessionHasErrors('phone');
    expect(TenantRegistration::where('company_name', 'PT Uji Mandiri')->exists())->toBeFalse();
});

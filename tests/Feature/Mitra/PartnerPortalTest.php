<?php

use App\Models\ReferralLedger;
use App\Models\ReferralSetting;
use App\Models\ReferralWithdrawal;
use App\Models\TenantRegistration;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();

    ReferralSetting::current()->update(['flat_amount' => 50000, 'min_withdrawal_amount' => 25000]);
});

it('renders the partner dashboard for a partner login', function (): void {
    $partner = createTestPartner();

    actingAs($partner->user)
        ->get(route('mitra.dashboard'))
        ->assertOk();
});

it('shows pending company registrations attributed to the partner', function (): void {
    $partner = createTestPartner();

    TenantRegistration::create([
        'company_name' => 'PT Pending Portal',
        'phone' => '081200000000',
        'admin_name' => 'Admin Pending',
        'admin_email' => 'pending.portal@example.com',
        'admin_password' => bcrypt('Password123!'),
        'partner_id' => $partner->id,
        'source' => 'referral',
        'status' => TenantRegistration::STATUS_PENDING,
    ]);

    actingAs($partner->user)
        ->get(route('mitra.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingRegistrations.0.company_name', 'PT Pending Portal'));
});

it('forbids a tenant user and a super admin from the partner portal', function (): void {
    actingAs($this->admin)->get(route('mitra.dashboard'))->assertForbidden();
    actingAs($this->superAdmin)->get(route('mitra.dashboard'))->assertForbidden();
});

it('sends a partner-role login to /mitra instead of the HR dashboard', function (): void {
    $partner = createTestPartner();

    actingAs($partner->user)
        ->get(route('dashboard'))
        ->assertRedirect(route('mitra.dashboard'));
});

it('lets a partner save their bank details', function (): void {
    $partner = createTestPartner();

    actingAs($partner->user)
        ->post(route('mitra.rekening.update'), [
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'Mitra Uji',
        ])
        ->assertSessionHas('success');

    expect($partner->fresh()->hasBankDetails())->toBeTrue();
});

it('blocks saving bank details while the rekening tab is disabled by the admin', function (): void {
    ReferralSetting::current()->update(['rekening_tab_enabled' => false]);

    $partner = createTestPartner();

    actingAs($partner->user)
        ->post(route('mitra.rekening.update'), [
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'Mitra Uji',
        ])
        ->assertSessionHas('error');

    expect($partner->fresh()->hasBankDetails())->toBeFalse();
});

it('blocks a withdrawal request until bank details are filled in', function (): void {
    $partner = createTestPartner();
    ReferralLedger::create([
        'partner_id' => $partner->id,
        'type' => ReferralLedger::TYPE_EARN,
        'amount' => 100000,
        'balance_after' => 100000,
    ]);

    actingAs($partner->user)
        ->post(route('mitra.penarikan.store'), ['amount' => 50000])
        ->assertSessionHas('error');

    expect(ReferralWithdrawal::where('partner_id', $partner->id)->count())->toBe(0);
});

it('lets a partner request a withdrawal within their available balance, reserving the amount', function (): void {
    $partner = createTestPartner([
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Mitra Uji',
    ]);
    ReferralLedger::create([
        'partner_id' => $partner->id,
        'type' => ReferralLedger::TYPE_EARN,
        'amount' => 100000,
        'balance_after' => 100000,
    ]);

    actingAs($partner->user)
        ->post(route('mitra.penarikan.store'), ['amount' => 50000])
        ->assertSessionHas('success');

    $withdrawal = ReferralWithdrawal::where('partner_id', $partner->id)->first();
    expect($withdrawal)->not->toBeNull();
    expect((float) $withdrawal->amount)->toBe(50000.0);
    expect($withdrawal->bank_account_number)->toBe('1234567890');

    expect($partner->fresh()->availableAmount())->toBe(50000.0);
});

it('refuses a withdrawal larger than the available balance', function (): void {
    $partner = createTestPartner([
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Mitra Uji',
    ]);
    ReferralLedger::create([
        'partner_id' => $partner->id,
        'type' => ReferralLedger::TYPE_EARN,
        'amount' => 100000,
        'balance_after' => 100000,
    ]);

    actingAs($partner->user)
        ->post(route('mitra.penarikan.store'), ['amount' => 999000])
        ->assertSessionHas('error');

    expect(ReferralWithdrawal::where('partner_id', $partner->id)->count())->toBe(0);
});

it('blocks a withdrawal request while withdrawals are disabled by the admin', function (): void {
    ReferralSetting::current()->update(['withdrawal_enabled' => false]);

    $partner = createTestPartner([
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Mitra Uji',
    ]);
    ReferralLedger::create([
        'partner_id' => $partner->id,
        'type' => ReferralLedger::TYPE_EARN,
        'amount' => 100000,
        'balance_after' => 100000,
    ]);

    actingAs($partner->user)
        ->post(route('mitra.penarikan.store'), ['amount' => 50000])
        ->assertSessionHas('error');

    expect(ReferralWithdrawal::where('partner_id', $partner->id)->count())->toBe(0);
});

it('respects the configured minimum withdrawal', function (): void {
    $partner = createTestPartner([
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Mitra Uji',
    ]);
    ReferralLedger::create([
        'partner_id' => $partner->id,
        'type' => ReferralLedger::TYPE_EARN,
        'amount' => 100000,
        'balance_after' => 100000,
    ]);

    actingAs($partner->user)
        ->post(route('mitra.penarikan.store'), ['amount' => 10000])
        ->assertSessionHas('error');

    expect(ReferralWithdrawal::where('partner_id', $partner->id)->count())->toBe(0);
});

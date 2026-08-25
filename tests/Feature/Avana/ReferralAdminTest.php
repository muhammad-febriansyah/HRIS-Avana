<?php

use App\Models\Partner;
use App\Models\PartnerRegistration;
use App\Models\ReferralLedger;
use App\Models\ReferralSetting;
use App\Models\ReferralWithdrawal;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

function makePartnerRegistration(array $overrides = []): PartnerRegistration
{
    return PartnerRegistration::create(array_merge([
        'full_name' => 'Sari Konsultan',
        'email' => 'sari.konsultan@example.com',
        'whatsapp' => '081234567890',
        'password' => 'Password123!',
        'partner_type' => 'HR Consultant',
        'terms_accepted' => true,
        'status' => 'pending',
    ], $overrides));
}

it('lets the super admin turn off partner withdrawals', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.referral.pengaturan.update'), [
            'flat_amount' => 50000,
            'hold_days' => 14,
            'min_withdrawal_amount' => 25000,
            'withdrawal_enabled' => false,
            'leads_tab_enabled' => true,
            'komisi_tab_enabled' => true,
            'rekening_tab_enabled' => true,
        ])
        ->assertSessionHas('success');

    expect(ReferralSetting::current()->withdrawal_enabled)->toBeFalse();
});

it('lets the super admin turn off individual mitra portal tabs', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.referral.pengaturan.update'), [
            'flat_amount' => 50000,
            'hold_days' => 14,
            'min_withdrawal_amount' => 25000,
            'withdrawal_enabled' => true,
            'leads_tab_enabled' => false,
            'komisi_tab_enabled' => false,
            'rekening_tab_enabled' => false,
        ])
        ->assertSessionHas('success');

    $settings = ReferralSetting::current();
    expect($settings->leads_tab_enabled)->toBeFalse();
    expect($settings->komisi_tab_enabled)->toBeFalse();
    expect($settings->rekening_tab_enabled)->toBeFalse();
});

it('forbids a non super admin from the referral centre', function (): void {
    actingAs($this->admin)
        ->get(route('avana.referral'))
        ->assertForbidden();
});

it('forbids a partner login from the referral centre and every /avana route', function (): void {
    $partner = createTestPartner();

    actingAs($partner->user)
        ->get(route('avana.referral'))
        ->assertForbidden();

    actingAs($partner->user)
        ->get(route('avana.klien'))
        ->assertForbidden();
});

it('approves a partner application into a real login and referral profile', function (): void {
    $registration = makePartnerRegistration();

    actingAs($this->superAdmin)
        ->post(route('avana.referral.mitra.approve', $registration))
        ->assertSessionHas('success')
        ->assertSessionMissing('credentials');

    $registration->refresh();
    expect($registration->status)->toBe('approved');

    $user = User::where('email', 'sari.konsultan@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->roles()->where('code', 'partner')->exists())->toBeTrue();
    // The login carries the password the applicant chose at registration —
    // no separate generated password for the super admin to relay.
    expect(Hash::check('Password123!', $user->password))->toBeTrue();

    $partner = Partner::where('user_id', $user->id)->first();
    expect($partner)->not->toBeNull();
    expect($partner->status)->toBe('active');
});

it('lets a newly approved partner log in immediately with the password they chose at registration', function (): void {
    $registration = makePartnerRegistration([
        'email' => 'baru.mitra@example.com',
        'password' => 'RahasiaMitra1',
    ]);

    actingAs($this->superAdmin)
        ->post(route('avana.referral.mitra.approve', $registration));

    // Drop the super admin's faked session before the real login below —
    // otherwise the guard's cached user leaks into this request.
    $this->app['auth']->forgetGuards();
    $this->flushSession();

    $this->post(route('login.store'), [
        'email' => 'baru.mitra@example.com',
        'password' => 'RahasiaMitra1',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    // Fortify lands every login on the generic dashboard route; a partner
    // login bounces from there to its own portal (see PartnerPortalTest).
    $this->get(route('dashboard'))->assertRedirect(route('mitra.dashboard'));
});

it('refuses to approve a partner application whose email already has an account', function (): void {
    $registration = makePartnerRegistration(['email' => $this->admin->email]);

    actingAs($this->superAdmin)
        ->post(route('avana.referral.mitra.approve', $registration))
        ->assertSessionHas('error');

    expect($registration->fresh()->status)->toBe('pending');
});

it('pays an approved withdrawal, uploads the proof privately, and debits the ledger', function (): void {
    Storage::fake('local');

    $partner = createTestPartner();
    ReferralLedger::create([
        'partner_id' => $partner->id,
        'type' => ReferralLedger::TYPE_EARN,
        'amount' => 100000,
        'balance_after' => 100000,
    ]);

    $withdrawal = ReferralWithdrawal::create([
        'partner_id' => $partner->id,
        'amount' => 100000,
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Budi Mitra',
        'status' => ReferralWithdrawal::STATUS_PENDING,
    ]);

    actingAs($this->superAdmin)
        ->post(route('avana.referral.penarikan.approve', $withdrawal))
        ->assertSessionHas('success');

    expect($withdrawal->fresh()->status)->toBe(ReferralWithdrawal::STATUS_APPROVED);

    actingAs($this->superAdmin)
        ->post(route('avana.referral.penarikan.pay', $withdrawal), [
            'proof' => UploadedFile::fake()->create('bukti-tf.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHas('success');

    $withdrawal->refresh();
    expect($withdrawal->status)->toBe(ReferralWithdrawal::STATUS_PAID);
    expect($withdrawal->proof_path)->not->toBeNull();
    Storage::disk('local')->assertExists($withdrawal->proof_path);

    expect($partner->fresh()->balanceAmount())->toBe(0.0);

    $debit = ReferralLedger::where('partner_id', $partner->id)->where('type', ReferralLedger::TYPE_WITHDRAW)->first();
    expect($debit)->not->toBeNull();
    expect((float) $debit->amount)->toBe(-100000.0);
});

it('releases the reservation when a withdrawal is rejected', function (): void {
    $partner = createTestPartner();
    ReferralLedger::create([
        'partner_id' => $partner->id,
        'type' => ReferralLedger::TYPE_EARN,
        'amount' => 100000,
        'balance_after' => 100000,
    ]);

    $withdrawal = ReferralWithdrawal::create([
        'partner_id' => $partner->id,
        'amount' => 100000,
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Budi Mitra',
        'status' => ReferralWithdrawal::STATUS_PENDING,
    ]);

    expect($partner->fresh()->availableAmount())->toBe(0.0);

    actingAs($this->superAdmin)
        ->post(route('avana.referral.penarikan.reject', $withdrawal), ['admin_note' => 'Data rekening tidak sesuai'])
        ->assertSessionHas('success');

    expect($withdrawal->fresh()->status)->toBe(ReferralWithdrawal::STATUS_REJECTED);
    expect($partner->fresh()->availableAmount())->toBe(100000.0);
});

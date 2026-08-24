<?php

use App\Models\Partner;
use App\Models\PartnerRegistration;
use App\Models\ReferralLedger;
use App\Models\ReferralWithdrawal;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
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
        'partner_type' => 'HR Consultant',
        'terms_accepted' => true,
        'status' => 'pending',
    ], $overrides));
}

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
        ->assertSessionHas('credentials');

    $registration->refresh();
    expect($registration->status)->toBe('approved');

    $user = User::where('email', 'sari.konsultan@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->roles()->where('code', 'partner')->exists())->toBeTrue();

    $partner = Partner::where('user_id', $user->id)->first();
    expect($partner)->not->toBeNull();
    expect($partner->status)->toBe('active');
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
        'points' => 20,
        'amount' => 100000,
        'balance_after' => 20,
    ]);

    $withdrawal = ReferralWithdrawal::create([
        'partner_id' => $partner->id,
        'points' => 20,
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

    expect($partner->fresh()->balancePoints())->toBe(0);

    $debit = ReferralLedger::where('partner_id', $partner->id)->where('type', ReferralLedger::TYPE_WITHDRAW)->first();
    expect($debit)->not->toBeNull();
    expect($debit->points)->toBe(-20);
});

it('releases the reservation when a withdrawal is rejected', function (): void {
    $partner = createTestPartner();
    ReferralLedger::create([
        'partner_id' => $partner->id,
        'type' => ReferralLedger::TYPE_EARN,
        'points' => 20,
        'amount' => 100000,
        'balance_after' => 20,
    ]);

    $withdrawal = ReferralWithdrawal::create([
        'partner_id' => $partner->id,
        'points' => 20,
        'amount' => 100000,
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Budi Mitra',
        'status' => ReferralWithdrawal::STATUS_PENDING,
    ]);

    expect($partner->fresh()->availablePoints())->toBe(0);

    actingAs($this->superAdmin)
        ->post(route('avana.referral.penarikan.reject', $withdrawal), ['admin_note' => 'Data rekening tidak sesuai'])
        ->assertSessionHas('success');

    expect($withdrawal->fresh()->status)->toBe(ReferralWithdrawal::STATUS_REJECTED);
    expect($partner->fresh()->availablePoints())->toBe(20);
});

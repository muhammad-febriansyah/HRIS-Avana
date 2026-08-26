<?php

use App\Models\PartnerRegistration;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

function partnerRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Rina Prospek',
        'email' => 'rina.prospek@example.com',
        'whatsapp' => '081234567890',
        'password' => 'RahasiaMitra1',
        'password_confirmation' => 'RahasiaMitra1',
        'partner_type' => 'HR Consultant',
        'terms_accepted' => true,
    ], $overrides);
}

it('renders the public partner registration form', function (): void {
    $this->withoutVite()
        ->get(route('partner-registration.create'))
        ->assertOk();
});

it('stores a pending registration with the applicant\'s chosen password hashed', function (): void {
    $this->withoutVite()
        ->post(route('partner-registration.store'), partnerRegistrationPayload())
        ->assertSessionHas('success');

    $registration = PartnerRegistration::where('email', 'rina.prospek@example.com')->firstOrFail();

    expect($registration->status)->toBe('pending');
    expect($registration->password)->not->toBe('RahasiaMitra1');
    expect(Hash::check('RahasiaMitra1', $registration->password))->toBeTrue();
});

it('rejects registration when the password confirmation does not match', function (): void {
    $this->withoutVite()
        ->post(route('partner-registration.store'), partnerRegistrationPayload([
            'password_confirmation' => 'Beda123456',
        ]))
        ->assertSessionHasErrors('password');

    expect(PartnerRegistration::where('email', 'rina.prospek@example.com')->exists())->toBeFalse();
});

it('rejects a registration password shorter than 8 characters', function (): void {
    $this->withoutVite()
        ->post(route('partner-registration.store'), partnerRegistrationPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))
        ->assertSessionHasErrors('password');
});

it('requires terms to be accepted', function (): void {
    $this->withoutVite()
        ->post(route('partner-registration.store'), partnerRegistrationPayload([
            'terms_accepted' => false,
        ]))
        ->assertSessionHasErrors('terms_accepted');
});

it('rejects a WhatsApp number that is not a valid Indonesian mobile format', function (): void {
    $this->withoutVite()
        ->post(route('partner-registration.store'), partnerRegistrationPayload([
            'whatsapp' => 'Adipisci beatae proi',
        ]))
        ->assertSessionHasErrors('whatsapp');

    expect(PartnerRegistration::where('email', 'rina.prospek@example.com')->exists())->toBeFalse();
});

/**
 * Production was seeded before the referral feature existed, so it never got
 * the `partner` role — which only AvanaDemoSeeder created. Approving an
 * application there left the partner with no role at all, locked out of
 * /mitra and stranded on the tenant dashboard with an empty sidebar.
 */
it('provisions a working partner login even when the partner role does not exist yet', function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();

    // The state production was actually in: no `partner` role anywhere.
    Role::query()->whereNull('tenant_id')->where('code', 'partner')->forceDelete();

    $registration = PartnerRegistration::create([
        'full_name' => 'Nani Mitra',
        'email' => 'nani.mitra@example.com',
        'whatsapp' => '081234567890',
        'password' => Hash::make('RahasiaMitra1'),
        'partner_type' => 'HR Consultant',
        'terms_accepted' => true,
        'status' => 'pending',
    ]);

    actingAs($superAdmin)
        ->post(route('avana.referral.mitra.approve', $registration))
        ->assertSessionHas('success');

    $partnerUser = User::where('email', 'nani.mitra@example.com')->firstOrFail();

    expect($partnerUser->roles->pluck('code'))->toContain('partner');

    // The two gates that silently failed before: the portal itself, and the
    // dashboard redirect that is supposed to send a partner there.
    actingAs($partnerUser)->get(route('mitra.dashboard'))->assertOk();
    actingAs($partnerUser)->get('/dashboard')->assertRedirect(route('mitra.dashboard'));
});

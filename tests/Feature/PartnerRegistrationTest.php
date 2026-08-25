<?php

use App\Models\PartnerRegistration;
use Illuminate\Support\Facades\Hash;

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

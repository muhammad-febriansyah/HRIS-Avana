<?php

use App\Http\Middleware\CaptureReferral;
use App\Models\ReferralClick;
use App\Models\ReferralLead;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    $this->partner = createTestPartner();
});

it('logs a click and hands back an attribution cookie the browser can decrypt on the next request', function (): void {
    $landing = $this->get('/daftar-perusahaan?ref='.$this->partner->code);

    $landing->assertOk();
    expect(ReferralClick::where('partner_id', $this->partner->id)->count())->toBe(1);

    // Proof the server actually encrypted it going out (this is what the old
    // bug broke: the cookie was attached to the response after EncryptCookies
    // had already run, so it went out unencrypted — the browser round trip
    // that follows would then never decrypt it back in on the next request).
    expect($landing->getCookie(CaptureReferral::COOKIE_NAME)?->getValue())->toBe($this->partner->code);

    // withCookie() re-encrypts whatever plain value it is given the same way
    // a real browser session would carry it, so what to pass here is the
    // decrypted code, not the raw header.
    $response = $this->withCookie(CaptureReferral::COOKIE_NAME, $this->partner->code)
        ->post('/daftar-perusahaan', [
            'company_name' => 'PT Uji Atribusi',
            'contact_name' => 'Andi',
            'email' => 'andi@example.com',
            'phone' => '081200000000',
        ]);

    $response->assertSessionHas('success', true);

    $lead = ReferralLead::where('company_name', 'PT Uji Atribusi')->first();
    expect($lead)->not->toBeNull();
    expect($lead->partner_id)->toBe($this->partner->id);
});

it('leaves partner_id null when the inquiry form is submitted with no referral cookie', function (): void {
    $response = $this->post('/daftar-perusahaan', [
        'company_name' => 'PT Tanpa Referral',
        'contact_name' => 'Budi',
        'email' => 'budi@example.com',
        'phone' => '081200000001',
    ]);

    $response->assertSessionHas('success', true);

    $lead = ReferralLead::where('company_name', 'PT Tanpa Referral')->first();
    expect($lead)->not->toBeNull();
    expect($lead->partner_id)->toBeNull();
});

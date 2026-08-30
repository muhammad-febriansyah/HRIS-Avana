<?php

use App\Models\Feature;
use App\Models\MobileMenuItem;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MobileMenu;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    // An ESS account: the phone's Menu Cepat is built for employees.
    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->user->tenant_id);
});

/** Switch one tenant feature off by its code. */
function disableFeature(Tenant $tenant, string $code): void
{
    $tenant->features()->updateOrCreate(
        ['feature_id' => Feature::where('code', $code)->value('id')],
        ['is_enabled' => false],
    );
}

/** Tile keys the account sees in Menu Cepat. */
function tileKeysFor(User $user): array
{
    return array_column(MobileMenu::forUser($user->fresh()), 'key');
}

it('drops the phone tile when its feature is switched off', function (): void {
    expect(tileKeysFor($this->user))->toContain('lembur');

    disableFeature($this->tenant, 'overtime');

    expect(tileKeysFor($this->user))->not->toContain('lembur');
    // The other tiles are untouched — only the disabled module goes.
    expect(tileKeysFor($this->user))->toContain('cuti');
});

it('drops every tile a disabled feature covers', function (): void {
    disableFeature($this->tenant, 'attendance');

    $keys = tileKeysFor($this->user);

    expect($keys)->not->toContain('jadwal');
    expect($keys)->not->toContain('riwayat');
    expect($keys)->not->toContain('koreksi');
    expect($keys)->not->toContain('kunjungan');
});

it('keeps the tiles no feature governs', function (): void {
    foreach (Feature::pluck('code') as $code) {
        disableFeature($this->tenant, $code);
    }

    expect(tileKeysFor($this->user))->toBe(['dasbor']);
});

it('names a tenant feature that actually exists for every tile', function (): void {
    $codes = Feature::pluck('code')->all();

    // A typo here would silently hide the tile from every tenant, since no
    // enabled-feature list can ever contain a code that does not exist.
    $unknown = array_values(array_diff(array_unique(array_values(MobileMenu::TILE_FEATURES)), $codes));

    expect($unknown)->toBe([]);
});

/** Bottom-tab keys the account sees in the app's navigation bar. */
function tabKeysFor(User $user): array
{
    return array_column(MobileMenu::tabsForUser($user->fresh()), 'key');
}

it('drops the bottom tab when its feature is switched off', function (): void {
    expect(tabKeysFor($this->user))->toContain('sosmed');

    disableFeature($this->tenant, 'social');

    expect(tabKeysFor($this->user))->not->toContain('sosmed');
    // The rest of the bar is untouched.
    expect(tabKeysFor($this->user))->toContain('pengumuman');
});

it('keeps Beranda and Profil whatever the tenant switches off', function (): void {
    foreach (Feature::pluck('code') as $code) {
        disableFeature($this->tenant, $code);
    }

    expect(tabKeysFor($this->user))->toBe(['beranda', 'profil']);
});

it('names a tenant feature that actually exists for every bottom tab', function (): void {
    $codes = Feature::pluck('code')->all();

    $unknown = array_values(array_diff(array_unique(array_values(MobileMenu::TAB_FEATURES)), $codes));

    expect($unknown)->toBe([]);
});

it('sends the bottom bar to the phone on login', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'email' => $this->user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('user.tabs.0.key', 'beranda')
        ->assertJsonStructure(['user' => ['tabs' => [['key', 'label', 'icon', 'color', 'route']]]]);
});

it('closes the social wall when Ruang Kita is switched off', function (): void {
    disableFeature($this->tenant, 'social');

    $token = auth('api')->login($this->user->fresh());

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me/social/feed')
        ->assertStatus(403)
        ->assertJsonPath('message', 'Fitur Ruang Kita tidak aktif untuk perusahaan Anda.');
});

it('seeds a pending tile switched off so the phone never opens a screen that does not exist', function (): void {
    $fresh = Tenant::create(['name' => 'PT Baru', 'slug' => 'pt-baru']);

    MobileMenu::seedDefaultsFor($fresh->id);

    foreach (MobileMenu::PENDING_TILES as $key) {
        $tile = MobileMenuItem::query()
            ->where('tenant_id', $fresh->id)
            ->where('key', $key)
            ->first();

        expect($tile)->not->toBeNull()
            ->and($tile->is_active)->toBeFalse();
    }
});

it('hides every pending tile from the account that would tap it', function (): void {
    MobileMenu::seedDefaultsFor($this->tenant->id);

    MobileMenuItem::query()
        ->where('tenant_id', $this->tenant->id)
        ->whereIn('key', MobileMenu::PENDING_TILES)
        ->update(['is_active' => false]);

    expect(tileKeysFor($this->user))->not->toContain(...MobileMenu::PENDING_TILES);
});

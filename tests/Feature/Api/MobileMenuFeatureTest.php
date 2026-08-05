<?php

use App\Models\Feature;
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

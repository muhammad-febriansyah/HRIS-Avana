<?php

use App\Models\Feature;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->tenant = $this->user->tenant;
});

/**
 * Switch one tenant feature on or off by code.
 */
function toggleFeature(object $tenant, string $code, bool $enabled): void
{
    $id = Feature::where('code', $code)->value('id');

    $tenant->features()->updateOrCreate(['feature_id' => $id], ['is_enabled' => $enabled]);
}

/**
 * The self-service menu labels the given user currently sees.
 *
 * @return Collection<int, string>
 */
function selfServiceLabels(User $user): Collection
{
    $section = collect(AvanaNav::forUser($user))->firstWhere('title', 'LAYANAN SAYA');

    return collect($section['items'][0]['children'] ?? [])->pluck('label');
}

it('reads a comma-separated feature gate as a list', function (): void {
    expect(AvanaNav::featureCodes('ess,payroll'))->toBe(['ess', 'payroll'])
        ->and(AvanaNav::featureCodes(' ess , payroll '))->toBe(['ess', 'payroll'])
        ->and(AvanaNav::featureCodes('ess'))->toBe(['ess'])
        ->and(AvanaNav::featureCodes(null))->toBe([])
        ->and(AvanaNav::featureCodes(''))->toBe([]);
});

it('hides a self-service screen whose own module the tenant never enabled', function (): void {
    toggleFeature($this->tenant, 'ess', true);
    toggleFeature($this->tenant, 'payroll', true);

    expect(selfServiceLabels($this->user->fresh()))->toContain('Slip Gaji');

    toggleFeature($this->tenant, 'payroll', false);

    expect(selfServiceLabels($this->user->fresh()))->not->toContain('Slip Gaji');
});

it('blocks the route too, not only the menu', function (): void {
    toggleFeature($this->tenant, 'ess', true);
    toggleFeature($this->tenant, 'payroll', false);

    $this->actingAs($this->user->fresh())
        ->get('/avana/saya/slip-gaji')
        ->assertForbidden();
});

it('keeps a self-service screen whose module is enabled', function (): void {
    toggleFeature($this->tenant, 'ess', true);
    toggleFeature($this->tenant, 'leave', true);

    expect(selfServiceLabels($this->user->fresh()))->toContain('Cuti');

    $this->actingAs($this->user->fresh())
        ->get('/avana/saya/cuti')
        ->assertOk();
});

it('still hides every self-service screen when the ess feature itself is off', function (): void {
    toggleFeature($this->tenant, 'ess', false);
    toggleFeature($this->tenant, 'leave', true);

    expect(selfServiceLabels($this->user->fresh()))->not->toContain('Cuti');

    $this->actingAs($this->user->fresh())
        ->get('/avana/saya/cuti')
        ->assertForbidden();
});

<?php

use App\Models\Feature;
use App\Models\MenuItem;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    // rina is admin_tenant_hr — manages Hak Akses for her own tenant.
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/** Whether a feature code is enabled for the acting tenant. */
function featureEnabled(Tenant $tenant, string $code): bool
{
    $id = Feature::where('code', $code)->value('id');

    return (bool) $tenant->features()->where('feature_id', $id)->value('is_enabled');
}

it('disables a feature via its master switch and blocks the route', function (): void {
    actingAs($this->admin)->get(route('avana.pembelajaran'))->assertOk();

    actingAs($this->admin)
        ->post(route('avana.hak-akses.feature.toggle'), [
            'module_key' => 'learning',
            'enabled' => false,
        ])
        ->assertRedirect();

    expect(featureEnabled($this->tenant, 'learning'))->toBeFalse();
    actingAs($this->admin)->get(route('avana.pembelajaran'))->assertForbidden();
});

it('re-enables a feature through the master switch', function (): void {
    $learning = Feature::where('code', 'learning')->value('id');
    $this->tenant->features()->updateOrCreate(['feature_id' => $learning], ['is_enabled' => false]);

    actingAs($this->admin)
        ->post(route('avana.hak-akses.feature.toggle'), [
            'module_key' => 'learning',
            'enabled' => true,
        ])
        ->assertRedirect();

    expect(featureEnabled($this->tenant, 'learning'))->toBeTrue();
    actingAs($this->admin)->get(route('avana.pembelajaran'))->assertOk();
});

it('toggles a feature-only row (cash advance) whose per-role access lives elsewhere', function (): void {
    actingAs($this->admin)->get(route('avana.kasbon'))->assertOk();

    actingAs($this->admin)
        ->post(route('avana.hak-akses.feature.toggle'), [
            'module_key' => 'cash_advance',
            'enabled' => false,
        ])
        ->assertRedirect();

    expect(featureEnabled($this->tenant, 'cash_advance'))->toBeFalse();
    actingAs($this->admin)->get(route('avana.kasbon'))->assertForbidden();
});

it('rejects toggling a core menu that has no feature', function (): void {
    actingAs($this->admin)
        ->post(route('avana.hak-akses.feature.toggle'), [
            'module_key' => 'audit',
            'enabled' => false,
        ])
        ->assertStatus(422);
});

it('generates a matrix row for every feature plus fixed core rows', function (): void {
    actingAs($this->admin)
        ->get(route('avana.hak-akses'))
        ->assertInertia(fn ($page) => $page
            ->component('avana/hak-akses/index')
            ->where('hasTenant', true)
            // learning: a normal feature row (switch + per-role actions).
            ->where('modules', fn ($modules) => collect($modules)->firstWhere('key', 'learning')['hasFeature'] === true
                && collect($modules)->firstWhere('key', 'learning')['actionable'] === true
                // cash_advance: feature-only row (switch, no per-role actions).
                && collect($modules)->firstWhere('key', 'cash_advance')['hasFeature'] === true
                && collect($modules)->firstWhere('key', 'cash_advance')['actionable'] === false
                // audit: fixed core row (no feature switch).
                && collect($modules)->firstWhere('key', 'audit')['hasFeature'] === false
                // rows carry a section group.
                && collect($modules)->firstWhere('key', 'learning')['group'] === 'TALENTA'));
});

it('auto-adds a newly created feature to the matrix without code changes', function (): void {
    Feature::create([
        'code' => 'brand_new_module',
        'name' => 'Modul Baru',
        'module_group' => 'core',
        'permission_modules' => ['brand_new_module'],
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->get(route('avana.hak-akses'))
        ->assertInertia(fn ($page) => $page
            ->where('modules', fn ($modules) => collect($modules)->firstWhere('key', 'brand_new_module') !== null
                && collect($modules)->firstWhere('key', 'brand_new_module')['hasFeature'] === true));
});

it('keeps every feature-gated menu toggleable from Hak Akses', function (): void {
    $featureCodes = Feature::pluck('code');

    // Every active tenant menu gated by a feature must map to a real feature row,
    // so no menu is feature-gated yet impossible to switch off.
    $uncovered = MenuItem::where('tenant_id', $this->tenant->id)
        ->whereNotNull('feature')
        ->pluck('feature')
        ->unique()
        ->reject(fn (string $code): bool => $featureCodes->contains($code))
        ->values();

    expect($uncovered->all())->toBe([]);
});

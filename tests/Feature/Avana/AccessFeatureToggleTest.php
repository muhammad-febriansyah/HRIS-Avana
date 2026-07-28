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

it('generates a matrix row for every real menu, self-service screens included', function (): void {
    actingAs($this->admin)
        ->get(route('avana.hak-akses'))
        ->assertInertia(fn ($page) => $page
            ->component('avana/hak-akses/index')
            ->where('hasTenant', true)
            ->where('modules', function ($modules) {
                $rows = collect($modules);
                $learning = $rows->firstWhere('key', 'pembelajaran');
                $payslip = $rows->firstWhere('key', 'saya-slip');
                $audit = $rows->firstWhere('key', 'audit');

                return
                    // A feature-gated admin menu: switch + per-role actions.
                    $learning['hasFeature'] === true
                    && $learning['actionable'] === true
                    && $learning['permissionModules'] === ['learning']
                    // An ESS menu now has its own row — the whole point: it is
                    // controllable per role even though it grants no action.
                    && $payslip !== null
                    && $payslip['selfService'] === true
                    && $payslip['actionable'] === false
                    // A menu with no feature behind it still gets a row.
                    && $audit['hasFeature'] === false
                    && $audit['actionable'] === true;
            }));
});

it('auto-adds a newly created menu to the matrix without code changes', function (): void {
    MenuItem::create([
        'tenant_id' => $this->tenant->id,
        'key' => 'menu-baru',
        'section' => 'SISTEM',
        'label' => 'Menu Baru',
        'icon' => 'star',
        'href' => '/avana/menu-baru',
        'modules' => ['audit'],
        'is_active' => true,
        'sort_order' => 999,
    ]);

    actingAs($this->admin)
        ->get(route('avana.hak-akses'))
        ->assertInertia(fn ($page) => $page
            ->where('modules', fn ($modules) => collect($modules)->firstWhere('key', 'menu-baru') !== null
                && collect($modules)->firstWhere('key', 'menu-baru')['label'] === 'Menu Baru'));
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

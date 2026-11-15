<?php

use App\Models\Feature;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    // rina is admin_tenant_hr — may manage /avana/fitur for her own tenant.
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->assetFeature = Feature::where('code', 'asset')->firstOrFail();
});

/** Flatten every leaf id visible to a user's nav. */
function navLeafIdsFor(User $user): array
{
    $ids = [];
    foreach (AvanaNav::forUser($user) as $group) {
        foreach ($group['items'] as $item) {
            if (isset($item['children'])) {
                foreach ($item['children'] as $child) {
                    $ids[] = $child['id'];
                }
            } else {
                $ids[] = $item['id'];
            }
        }
    }

    return $ids;
}

it('hides the menu and blocks the route when a tenant feature is disabled', function (): void {
    // Baseline: the feature is enabled, so the leaf shows and the route opens.
    expect(navLeafIdsFor($this->admin->fresh()))->toContain('aset');
    actingAs($this->admin)->get(route('avana.aset'))->assertOk();

    actingAs($this->admin)
        ->post(route('avana.fitur.toggle'), [
            'feature_id' => $this->assetFeature->id,
            'enabled' => false,
        ])
        ->assertRedirect();

    expect(
        (bool) $this->tenant->features()
            ->where('feature_id', $this->assetFeature->id)
            ->value('is_enabled'),
    )->toBeFalse();

    expect(navLeafIdsFor($this->admin->fresh()))->not->toContain('aset');
    actingAs($this->admin)->get(route('avana.aset'))->assertForbidden();
});

it('restores the menu and route when the feature is re-enabled', function (): void {
    $this->tenant->features()->updateOrCreate(
        ['feature_id' => $this->assetFeature->id],
        ['is_enabled' => false],
    );

    actingAs($this->admin)
        ->post(route('avana.fitur.toggle'), [
            'feature_id' => $this->assetFeature->id,
            'enabled' => true,
        ])
        ->assertRedirect();

    expect(navLeafIdsFor($this->admin->fresh()))->toContain('aset');
    actingAs($this->admin)->get(route('avana.aset'))->assertOk();
});

describe('Alpa Otomatis (super admin only)', function (): void {
    beforeEach(function (): void {
        $this->autoAlpa = Feature::where('code', 'auto_alpa')->firstOrFail();
    });

    it('is not offered to or switchable by a tenant admin', function (): void {
        actingAs($this->admin)->get('/avana/fitur')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'features',
                fn ($features): bool => collect($features)->doesntContain('code', 'auto_alpa'),
            ));

        actingAs($this->admin)
            ->post('/avana/fitur/toggle', ['feature_id' => $this->autoAlpa->id, 'enabled' => true])
            ->assertForbidden();

        actingAs($this->admin)
            ->post('/avana/hak-akses/feature/toggle', ['module_key' => 'auto_alpa', 'enabled' => true])
            ->assertForbidden();

        expect($this->tenant->fresh()->hasFeature('auto_alpa'))->toBeFalse();
    });

    it('is switched per client by the super admin from the Klien page', function (): void {
        $superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();

        actingAs($superAdmin)
            ->post(route('avana.klien.feature.toggle', $this->tenant), ['feature_id' => $this->autoAlpa->id])
            ->assertRedirect();

        expect($this->tenant->fresh()->hasFeature('auto_alpa'))->toBeTrue();
    });
});

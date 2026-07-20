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

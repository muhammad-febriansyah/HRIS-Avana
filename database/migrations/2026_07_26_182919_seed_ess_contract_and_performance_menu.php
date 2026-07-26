<?php

use App\Models\MenuItem;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds "Kontrak Saya" and "Kinerja Saya" to each tenant's LAYANAN SAYA group.
 *
 * The runtime sidebar reads menu_items, and AvanaNav::seedDefaultsFor only
 * seeds a scope that has no rows at all — so tenants already carrying the
 * self-service group would never pick these two up without this.
 */
return new class extends Migration
{
    /**
     * @var array<int, array<string, string>>
     */
    private const LEAVES = [
        ['key' => 'saya-kontrak', 'label' => 'Kontrak Saya', 'icon' => 'file-text', 'href' => '/avana/saya/kontrak'],
        ['key' => 'saya-kinerja', 'label' => 'Kinerja Saya', 'icon' => 'trending-up', 'href' => '/avana/saya/kinerja'],
    ];

    private const SECTION = 'LAYANAN SAYA';

    public function up(): void
    {
        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $this->seedFor((int) $tenantId);
        }
    }

    public function down(): void
    {
        foreach (Tenant::query()->pluck('id') as $tenantId) {
            MenuItem::forTenant((int) $tenantId)
                ->whereIn('key', array_column(self::LEAVES, 'key'))
                ->delete();
        }
    }

    /**
     * Append the two leaves to the tenant's existing self-service group, right
     * after the last one already there.
     */
    private function seedFor(int $tenantId): void
    {
        if (MenuItem::forTenant($tenantId)->doesntExist()) {
            // Never customised: AvanaNav::seedDefaultsFor builds the whole menu,
            // these leaves included, the first time it is needed.
            return;
        }

        $lastOrder = (int) MenuItem::forTenant($tenantId)
            ->whereNull('parent_id')
            ->where('section', self::SECTION)
            ->max('sort_order');

        foreach (self::LEAVES as $index => $leaf) {
            MenuItem::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => $leaf['key'], 'parent_id' => null],
                [
                    'section' => self::SECTION,
                    'label' => $leaf['label'],
                    'icon' => $leaf['icon'],
                    'href' => $leaf['href'],
                    'feature' => 'ess',
                    'modules' => ['own'],
                    'admin_only' => false,
                    'super_admin_only' => false,
                    'is_active' => true,
                    'is_system' => true,
                    'sort_order' => $lastOrder + $index + 1,
                ],
            );
        }
    }
};

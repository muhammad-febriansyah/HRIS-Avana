<?php

use App\Models\MenuItem;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds "Riwayat Gaji" to the Payroll submenu.
 *
 * The runtime sidebar reads menu_items, and AvanaNav::seedDefaultsFor only
 * seeds a scope holding no rows at all — so a tenant already carrying the
 * payroll menu would never pick this screen up without this migration.
 */
return new class extends Migration
{
    private const KEY = 'payroll-riwayat-gaji';

    private const SECTION = 'PAYROLL & KEUANGAN';

    public function up(): void
    {
        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $this->seedFor((int) $tenantId);
        }
    }

    public function down(): void
    {
        MenuItem::query()->where('key', self::KEY)->delete();
    }

    private function seedFor(int $tenantId): void
    {
        if (MenuItem::forTenant($tenantId)->doesntExist()) {
            // Never customised: AvanaNav::seedDefaultsFor builds the whole menu,
            // this leaf included, the first time it is needed.
            return;
        }

        $parent = MenuItem::forTenant($tenantId)
            ->whereNull('parent_id')
            ->where('key', 'payroll')
            ->first();

        $lastOrder = (int) MenuItem::forTenant($tenantId)
            ->where('parent_id', $parent?->id)
            ->max('sort_order');

        MenuItem::firstOrCreate(
            ['tenant_id' => $tenantId, 'key' => self::KEY],
            [
                'parent_id' => $parent?->id,
                'section' => self::SECTION,
                'label' => 'Riwayat Gaji',
                'icon' => 'history',
                'href' => '/avana/payroll/riwayat-gaji',
                'feature' => 'payroll',
                'modules' => ['payroll'],
                'admin_only' => false,
                'super_admin_only' => false,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => $lastOrder + 1,
            ],
        );
    }
};

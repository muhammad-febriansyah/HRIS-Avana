<?php

use App\Models\MenuItem;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds "Laporan PPh 21" to the Payroll submenu.
 *
 * The runtime sidebar reads menu_items, and AvanaNav::seedDefaultsFor only
 * seeds a scope holding no rows at all — so a tenant already carrying the
 * payroll menu would never pick this screen up without this migration.
 */
return new class extends Migration
{
    private const KEY = 'payroll-pph21-report';

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

        // Idempotent: the shift below must not run twice for the same tenant.
        if (MenuItem::forTenant($tenantId)->where('key', self::KEY)->exists()) {
            return;
        }

        $parent = MenuItem::forTenant($tenantId)
            ->whereNull('parent_id')
            ->where('key', 'payroll')
            ->first();

        // Sit right after the TER tariff screen when it is there, so the tax
        // block of the payroll menu reads setup → tariff → report.
        $terOrder = MenuItem::forTenant($tenantId)
            ->where('parent_id', $parent?->id)
            ->where('key', 'payroll-ter')
            ->value('sort_order');

        if ($terOrder !== null) {
            // Make room: sort_order ties are broken by id, so inserting without
            // shifting would drop this leaf after the item already holding the
            // slot instead of before it.
            MenuItem::forTenant($tenantId)
                ->where('parent_id', $parent?->id)
                ->where('sort_order', '>', (int) $terOrder)
                ->increment('sort_order');
        }

        $sortOrder = $terOrder !== null
            ? (int) $terOrder + 1
            : (int) MenuItem::forTenant($tenantId)->where('parent_id', $parent?->id)->max('sort_order') + 1;

        MenuItem::firstOrCreate(
            ['tenant_id' => $tenantId, 'key' => self::KEY],
            [
                'parent_id' => $parent?->id,
                'section' => self::SECTION,
                'label' => 'Laporan PPh 21',
                'icon' => 'file-spreadsheet',
                'href' => '/avana/payroll/pph21-report',
                'feature' => 'payroll,pph21',
                'modules' => ['pph21', 'payroll'],
                'admin_only' => false,
                'super_admin_only' => false,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => $sortOrder,
            ],
        );
    }
};

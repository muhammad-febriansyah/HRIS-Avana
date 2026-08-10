<?php

use App\Models\MenuItem;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds "Penetapan Gaji Massal" to the Payroll submenu — the filter → preview →
 * apply screen the payroll documentation prescribes for setting up many
 * employees at once.
 */
return new class extends Migration
{
    private const KEY = 'payroll-penetapan-massal';

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
                'label' => 'Penetapan Gaji Massal',
                'icon' => 'users',
                'href' => '/avana/payroll/penetapan-massal',
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

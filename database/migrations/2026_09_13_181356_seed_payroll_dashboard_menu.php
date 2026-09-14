<?php

use App\Models\MenuItem;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the dedicated Payroll Dashboard leaf to existing tenant menus.
 */
return new class extends Migration
{
    private const KEY = 'payroll-dashboard';

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
        if (MenuItem::forTenant($tenantId)->doesntExist()
            || MenuItem::forTenant($tenantId)->where('key', self::KEY)->exists()) {
            return;
        }

        $parent = MenuItem::forTenant($tenantId)
            ->whereNull('parent_id')
            ->where('key', 'payroll')
            ->first();

        if ($parent === null) {
            return;
        }

        $firstChildOrder = (int) MenuItem::forTenant($tenantId)
            ->where('parent_id', $parent->id)
            ->min('sort_order');

        MenuItem::forTenant($tenantId)
            ->where('parent_id', $parent->id)
            ->where('sort_order', '>=', $firstChildOrder)
            ->increment('sort_order');

        MenuItem::create([
            'tenant_id' => $tenantId,
            'parent_id' => $parent->id,
            'key' => self::KEY,
            'section' => null,
            'label' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'href' => '/avana/payroll/dashboard',
            'feature' => 'payroll',
            'modules' => ['payroll'],
            'admin_only' => false,
            'super_admin_only' => false,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => $firstChildOrder,
        ]);
    }
};

<?php

use App\Models\MobileMenuItem;
use App\Models\Tenant;
use App\Support\MobileMenu;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the Timesheet tile to Menu Cepat on the phone, switched OFF.
 *
 * MobileMenu::forTenant only seeds a group that holds no rows at all, so every
 * tenant already carrying a Menu Cepat would never pick this tile up on its own.
 *
 * It is seeded inactive because the Flutter screen behind `/timesheet` has not
 * shipped yet — an active tile would send whoever tapped it to a route the app
 * does not know. Switching it on in Kelola Menu is all that is needed once the
 * screen lands.
 */
return new class extends Migration
{
    private const KEY = 'timesheet';

    public function up(): void
    {
        $tile = collect(MobileMenu::defaults())->firstWhere('key', self::KEY);

        if ($tile === null) {
            return;
        }

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            // Never opened the app menu: the defaults are seeded whole the
            // first time anyone asks, this tile included.
            if (MobileMenuItem::query()
                ->where('tenant_id', $tenantId)
                ->where('group', MobileMenu::GROUP_QUICK)
                ->doesntExist()
            ) {
                continue;
            }

            MobileMenuItem::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => self::KEY],
                [
                    ...$tile,
                    'group' => MobileMenu::GROUP_QUICK,
                    // Last in the carousel: an existing tenant arranged its own
                    // order, and a new tile has no claim on a place inside it.
                    'sort_order' => (int) MobileMenuItem::query()
                        ->where('tenant_id', $tenantId)
                        ->where('group', MobileMenu::GROUP_QUICK)
                        ->max('sort_order') + 1,
                    'is_active' => false,
                    'is_system' => true,
                ],
            );
        }
    }

    public function down(): void
    {
        MobileMenuItem::query()->where('key', self::KEY)->delete();
    }
};

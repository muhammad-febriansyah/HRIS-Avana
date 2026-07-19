<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

/**
 * Line managers now review their own team's settlements, and the permission
 * that lets them (`team.claim.approve`) lives under the `team` module. The
 * access middleware gates on a menu row's stored `modules`, and existing rows
 * were seeded with `['claim']` alone — so without this the manager is turned
 * away at the door no matter what the controller allows.
 *
 * Menu rows are per-tenant and editable, so this updates what is already there
 * rather than relying on the seeder, which only fills in missing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuItem::query()
            ->where('href', '/avana/settlement')
            ->get()
            ->each(function (MenuItem $item): void {
                $modules = $item->modules ?? [];

                if (in_array('team', $modules, true)) {
                    return;
                }

                $modules[] = 'team';
                $item->update(['modules' => $modules]);
            });
    }

    public function down(): void
    {
        MenuItem::query()
            ->where('href', '/avana/settlement')
            ->get()
            ->each(function (MenuItem $item): void {
                $modules = array_values(array_filter(
                    $item->modules ?? [],
                    fn (string $module): bool => $module !== 'team',
                ));

                $item->update(['modules' => $modules]);
            });
    }
};

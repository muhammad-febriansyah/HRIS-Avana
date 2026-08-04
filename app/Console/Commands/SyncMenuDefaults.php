<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use App\Models\Tenant;
use App\Support\AvanaNav;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('avana:sync-menu-defaults')]
#[Description('Backfill menus added to the canonical sidebar into tenants that were already seeded.')]
class SyncMenuDefaults extends Command
{
    /**
     * A tenant's sidebar is seeded from {@see AvanaNav::groups()} once, then
     * owned by the tenant's own Menu Builder rows. That means a menu added to
     * the canonical definition later never reaches a tenant that already has
     * rows — the seeders only run for a scope holding none.
     *
     * The seeders match on key and skip what exists, so re-running them adds
     * only what is new and leaves every customisation (renames, reparenting,
     * ordering, hidden flags) alone.
     */
    public function handle(): int
    {
        $before = MenuItem::count();

        AvanaNav::seedPlatformDefaults();

        Tenant::query()
            ->pluck('id')
            ->each(fn (int $tenantId) => AvanaNav::seedDefaultsFor($tenantId));

        $added = MenuItem::count() - $before;

        $this->info("Added {$added} menu row(s).");

        return self::SUCCESS;
    }
}

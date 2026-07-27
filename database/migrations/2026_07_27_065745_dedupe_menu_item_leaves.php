<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

/**
 * Drop duplicate sidebar leaves.
 *
 * `AvanaNav::seedDefaultsFor()` used to match a leaf on (key, parent_id). Once
 * a tenant moved a leaf with the Menu Builder — or the canonical nav re-homed
 * one under a different parent — the next re-seed no longer found it and
 * cloned a second row, so the sidebar showed the same menu twice (e.g. two
 * "Dokumen" entries). The seeder now matches a leaf on `key` alone; this cleans
 * up the rows the old behaviour already created.
 *
 * The oldest row wins: it is the one carrying the tenant's Menu Builder edits.
 * Parent (collapsible) rows are untouched — a parent may legitimately share its
 * key with its first child, e.g. "Payroll" > "Payroll".
 */
return new class extends Migration
{
    public function up(): void
    {
        $parentIds = MenuItem::query()
            ->whereNotNull('parent_id')
            ->distinct()
            ->pluck('parent_id')
            ->flip();

        MenuItem::query()
            ->whereNotNull('href')
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'key'])
            ->groupBy(fn (MenuItem $item): string => $item->tenant_id.'|'.$item->key)
            ->each(function ($rows) use ($parentIds): void {
                if ($rows->count() < 2) {
                    return;
                }

                // Never delete a row that still has children hanging off it.
                $rows->skip(1)
                    ->reject(fn (MenuItem $item): bool => $parentIds->has($item->id))
                    ->each(fn (MenuItem $item) => $item->delete());
            });
    }

    public function down(): void
    {
        // Non-destructive: the removed rows were duplicates of surviving ones.
    }
};

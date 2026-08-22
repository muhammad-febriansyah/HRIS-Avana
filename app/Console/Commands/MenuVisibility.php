<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use App\Support\AvanaNav;
use Illuminate\Console\Command;

/**
 * Show or hide one sidebar entry across tenants.
 *
 * A tenant's sidebar is its own rows in `menu_items`, seeded once from the
 * canonical definition and owned by the tenant afterwards. Turning a menu off
 * in {@see AvanaNav} therefore only affects tenants seeded from
 * then on; everyone already running keeps seeing it. This flips the rows that
 * already exist, so a screen taken out of service disappears everywhere
 * instead of only for new customers.
 *
 * The row is deactivated, never deleted: a path with no menu row behind it
 * resolves to no access requirement at all, which would leave the route open
 * to every role rather than closed.
 */
class MenuVisibility extends Command
{
    protected $signature = 'avana:menu-visibility
        {key : Menu key, e.g. payroll-insentif}
        {--show : Turn the menu back on (default is to hide it)}
        {--tenant= : Limit to one tenant id}';

    protected $description = 'Hide or show one sidebar menu across tenants';

    public function handle(): int
    {
        $key = (string) $this->argument('key');
        $show = (bool) $this->option('show');

        $rows = MenuItem::query()
            ->where('key', $key)
            ->when($this->option('tenant'), fn ($query, $tenant) => $query->where('tenant_id', $tenant))
            ->get();

        if ($rows->isEmpty()) {
            $this->warn("Tidak ada menu dengan key '{$key}'.");

            return self::FAILURE;
        }

        $changed = $rows->where('is_active', ! $show)->count();

        MenuItem::whereIn('id', $rows->pluck('id'))->update(['is_active' => $show]);

        $this->table(
            ['Tenant', 'Key', 'Label', 'Status'],
            $rows->map(fn (MenuItem $row): array => [
                $row->tenant_id ?? 'platform',
                $row->key,
                $row->label,
                $show ? 'ditampilkan' : 'disembunyikan',
            ])->all(),
        );

        $this->info("{$changed} baris menu diubah.");

        return self::SUCCESS;
    }
}

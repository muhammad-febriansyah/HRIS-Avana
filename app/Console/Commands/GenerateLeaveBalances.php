<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\LeaveBalanceProvisioner;
use Illuminate\Console\Command;

/**
 * Open the yearly leave balances so the Cuti screens have something to show and
 * approvals have something to deduct from.
 *
 * Run it once per tenant at the start of a year (or right after the leave types
 * are set up). Re-running is safe: rows that exist are left alone, quotas HR
 * adjusted by hand survive.
 */
class GenerateLeaveBalances extends Command
{
    protected $signature = 'avana:generate-leave-balance
        {year? : The year to open, defaults to the current one}
        {--tenant= : Limit to one tenant id}
        {--carry-from= : Also roll that year\'s leftovers into the opened year}
        {--carry-max= : Cap the carried days per balance}
        {--sync-used : Recompute used days from approved requests}';

    protected $description = 'Create the yearly leave balance rows for each active employee';

    public function handle(): int
    {
        $year = (int) ($this->argument('year') ?? now()->year);

        if ($year < 2000 || $year > 2100) {
            $this->error('Tahun tidak masuk akal: '.$year);

            return self::FAILURE;
        }

        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($query, $tenant) => $query->whereKey($tenant))
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($tenants->isEmpty()) {
            $this->error('Tidak ada tenant yang cocok.');

            return self::FAILURE;
        }

        $carryFrom = $this->option('carry-from') !== null ? (int) $this->option('carry-from') : null;
        $carryMax = $this->option('carry-max') !== null ? (float) $this->option('carry-max') : null;
        $rows = [];

        foreach ($tenants as $tenant) {
            $created = LeaveBalanceProvisioner::forTenant((int) $tenant->id, $year);
            $carried = $carryFrom === null
                ? 0
                : LeaveBalanceProvisioner::carryOver((int) $tenant->id, $carryFrom, $year, $carryMax);
            $synced = $this->option('sync-used')
                ? LeaveBalanceProvisioner::syncUsed((int) $tenant->id, $year)
                : 0;

            $rows[] = [$tenant->id, $tenant->name, $created, $carried, $synced];
        }

        $this->table(['Tenant', 'Nama', 'Saldo dibuat', 'Carry-over', 'Used disinkron'], $rows);

        return self::SUCCESS;
    }
}

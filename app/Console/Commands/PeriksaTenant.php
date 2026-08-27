<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantProvisioner;
use App\Support\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('avana:periksa-tenant {--fix} {--quiet-notify}')]
#[Description('Scan every tenant for prerequisites provisioning should have left behind, alert super admins, and optionally repair them.')]
class PeriksaTenant extends Command
{
    /**
     * A tenant can sit in production half-provisioned without anyone noticing:
     * each missing prerequisite fails somewhere the admin never looks, and the
     * client is the one who finds out. A tenant created from the Klien page
     * went five weeks with no company profile — invisible on the web, and an
     * outright refusal at the mobile login for every employee.
     *
     * Reporting is the point; `--fix` is the convenience. Without the flag
     * this only reads and notifies, so it is safe to schedule and safe to run
     * against production while deciding what to do.
     */
    public function handle(TenantProvisioner $provisioner): int
    {
        $fix = (bool) $this->option('fix');
        $notify = ! $this->option('quiet-notify');

        $checked = 0;
        $incomplete = 0;
        $repaired = 0;

        foreach (Tenant::query()->orderBy('id')->cursor() as $tenant) {
            $checked++;
            $missing = $provisioner->missingPrerequisites($tenant);

            if ($missing === []) {
                continue;
            }

            $incomplete++;
            $this->line($tenant->name.' ('.$tenant->id.') belum punya: '.implode(', ', $missing));

            if ($fix) {
                $provisioner->repairMissing($tenant);
                $this->info('  dilengkapi.');
                $repaired++;

                continue;
            }

            // Only worth an alert while it is still broken — a run that repairs
            // it has nothing left for a super admin to act on.
            if ($notify) {
                Notifier::tenantProvisioningIncomplete($tenant, $missing);
            }
        }

        $this->newLine();

        if ($incomplete === 0) {
            $this->info("Semua beres: {$checked} tenant diperiksa, tidak ada yang kurang.");

            return self::SUCCESS;
        }

        $this->info($fix
            ? "Selesai: {$checked} tenant diperiksa, {$repaired} dilengkapi."
            : "Ditemukan {$incomplete} dari {$checked} tenant belum lengkap. Jalankan ulang dengan --fix untuk melengkapinya.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantProvisioner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('avana:backfill-companies {--dry-run}')]
#[Description('Give tenants provisioned without a company profile the record the mobile app requires.')]
class BackfillTenantCompanies extends Command
{
    /**
     * A tenant created from the Klien page used to be provisioned with roles,
     * features and a menu but no company row, and nothing ever asked for one:
     * only a self-serve signup is held at the "Mulai" checklist until the
     * profile is saved, so an admin-created tenant ran on the web indefinitely
     * with the record missing. The mobile API refuses those accounts — the
     * employee is told "Akun belum terhubung ke perusahaan." and cannot log in
     * at all.
     *
     * {@see TenantProvisioner::provisionCompany()} creates it for new tenants.
     * This backfills the ones provisioned before that, naming each company
     * after the tenant exactly as provisioning would have.
     */
    public function handle(TenantProvisioner $provisioner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $waiting = 0;

        foreach (Tenant::query()->whereDoesntHave('company')->cursor() as $tenant) {
            // Still on the checklist: the profile is theirs to fill, and
            // OnboardingStatus reads this same absence to keep asking.
            if ($tenant->requires_onboarding) {
                $this->line('menunggu onboarding: '.$tenant->name);
                $waiting++;

                continue;
            }

            $this->line(($dryRun ? 'akan dibuat: ' : 'dibuat: ').$tenant->name);

            if (! $dryRun) {
                $provisioner->provisionCompany($tenant);
            }

            $created++;
        }

        $this->info($dryRun
            ? "Uji coba: {$created} profil perusahaan akan dibuat, {$waiting} dilewati."
            : "Selesai: {$created} profil perusahaan dibuat, {$waiting} dilewati.");

        return self::SUCCESS;
    }
}

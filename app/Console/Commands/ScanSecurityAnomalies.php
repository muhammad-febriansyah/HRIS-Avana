<?php

namespace App\Console\Commands;

use App\Services\SecurityAnomalyScanner;
use App\Support\Notifier;
use Illuminate\Console\Command;

/**
 * Runs {@see SecurityAnomalyScanner} and raises what it finds to the platform
 * owners.
 *
 * Each finding carries a stable key, and the notifier dedupes on it, so a scan
 * that keeps seeing the same run of failed logins warns once rather than every
 * night until it stops.
 */
class ScanSecurityAnomalies extends Command
{
    protected $signature = 'avana:scan-security-anomalies {--dry-run : Print findings without notifying anyone}';

    protected $description = 'Periksa jejak aktivitas untuk pola login dan ekspor yang mencurigakan';

    public function handle(SecurityAnomalyScanner $scanner): int
    {
        $findings = $scanner->scan();

        if ($findings === []) {
            $this->info('Tidak ada anomali terdeteksi.');

            return self::SUCCESS;
        }

        foreach ($findings as $finding) {
            $this->warn('['.$finding['kind'].'] '.$finding['title'].' — '.$finding['body']);

            if ($this->option('dry-run')) {
                continue;
            }

            Notifier::platformAlert(
                event: $finding['kind'],
                title: $finding['title'],
                body: $finding['body'],
                data: ['tenant_ref' => $finding['tenant_id']],
                dedupeValue: $finding['key'],
            );
        }

        $this->newLine();
        $this->info(count($findings).' anomali '.($this->option('dry-run') ? 'ditemukan' : 'dilaporkan ke super admin').'.');

        return self::SUCCESS;
    }
}

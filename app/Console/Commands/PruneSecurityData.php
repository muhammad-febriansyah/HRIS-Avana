<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\UserActivityLog;
use App\Models\UserLoginDevice;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Drops the security and activity trails past their retention window.
 *
 * An activity log is personal data — it records where a named person was and
 * when — and UU PDP 27/2022 expects personal data to be kept no longer than it
 * is needed. Windows are configured per trail in `config/security.php`; a
 * window of 0 keeps that trail for good.
 */
class PruneSecurityData extends Command
{
    protected $signature = 'avana:prune-security-data {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Hapus jejak aktivitas, audit, perangkat, dan notifikasi yang melewati masa simpan';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $trails = [
            'Aktivitas' => [UserActivityLog::query(), 'created_at', (int) config('security.retention.activity_log_days')],
            'Audit' => [AuditLog::query(), 'created_at', (int) config('security.retention.audit_log_days')],
            'Perangkat' => [UserLoginDevice::query(), 'last_seen_at', (int) config('security.retention.login_device_days')],
            'Notifikasi' => [Notification::query(), 'created_at', (int) config('security.retention.notification_days')],
        ];

        $total = 0;

        foreach ($trails as $label => [$query, $column, $days]) {
            if ($days <= 0) {
                $this->line("{$label}: masa simpan tidak dibatasi, dilewati.");

                continue;
            }

            $cutoff = now()->subDays($days);
            $count = $this->scope($query, $column, $cutoff)->count();

            if ($count === 0) {
                $this->line("{$label}: tidak ada baris melewati {$days} hari.");

                continue;
            }

            if (! $dryRun) {
                // Chunked: one delete of a year's activity rows can lock the
                // table long enough to time out the requests behind it.
                do {
                    $deleted = $this->scope($query->clone(), $column, $cutoff)->limit(1000)->delete();
                } while ($deleted > 0);
            }

            $total += $count;
            $this->info("{$label}: {$count} baris ".($dryRun ? 'akan dihapus' : 'dihapus').'.');
        }

        $this->newLine();
        $this->info($dryRun ? "Total {$total} baris akan dihapus." : "Total {$total} baris dihapus.");

        return self::SUCCESS;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    private function scope(Builder $query, string $column, CarbonInterface $cutoff): Builder
    {
        return $query->clone()->whereNotNull($column)->where($column, '<', $cutoff);
    }
}

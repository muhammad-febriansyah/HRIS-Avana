<?php

namespace App\Console\Commands;

use App\Services\DatabaseDumper;
use App\Support\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Writes a compressed dump of the database to disk on a schedule.
 *
 * Until this existed the only backup was a super admin remembering to press
 * Unduh on the Backup screen, which is not a backup policy — it is a hope. The
 * dump lands on the configured disk (the private local disk by default) and old
 * files are pruned to the retention window, so the directory cannot grow until
 * it fills the volume it is meant to protect.
 *
 * Point `BACKUP_DISK` at an off-site filesystem (S3, or any configured disk) in
 * production: a backup on the same server as the database survives a mistake,
 * not a fire.
 */
class BackupDatabase extends Command
{
    protected $signature = 'avana:backup-database
                            {--disk= : Filesystem disk to write to (default: config backup.disk)}
                            {--keep= : How many days of dumps to keep}
                            {--no-compress : Write plain SQL instead of gzip}';

    protected $description = 'Simpan dump database terjadwal dan bersihkan dump yang kedaluwarsa';

    public function handle(): int
    {
        $disk = (string) ($this->option('disk') ?: config('security.backup.disk', 'local'));
        $directory = trim((string) config('security.backup.directory', 'backups'), '/');
        $keepDays = (int) ($this->option('keep') ?: config('security.backup.keep_days', 14));
        $compress = ! $this->option('no-compress');

        $name = 'avanahr-'.now()->format('Ymd-His').'.sql'.($compress ? '.gz' : '');
        $path = $directory.'/'.$name;

        try {
            $bytes = $this->write($disk, $path, $compress);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Backup gagal: '.$exception->getMessage());

            $this->alertFailure($exception);

            return self::FAILURE;
        }

        $this->info('Backup tersimpan: '.$disk.':'.$path.' ('.$this->humanBytes($bytes).')');

        $pruned = $this->prune($disk, $directory, $keepDays);

        if ($pruned > 0) {
            $this->line("{$pruned} dump lama dihapus (masa simpan {$keepDays} hari).");
        }

        return self::SUCCESS;
    }

    /**
     * Stream the dump straight onto the disk. Streamed rather than assembled in
     * memory: a production database does not fit in a PHP process.
     *
     * @throws RuntimeException
     */
    private function write(string $disk, string $path, bool $compress): int
    {
        $dumper = new DatabaseDumper;
        $tables = $dumper->tables();

        if ($tables === []) {
            throw new RuntimeException('Tidak ada tabel yang bisa diekspor.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'avana-backup-');

        if ($temp === false) {
            throw new RuntimeException('Tidak bisa membuat berkas sementara untuk dump.');
        }

        $handle = fopen($temp, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Tidak bisa menulis berkas sementara untuk dump.');
        }

        try {
            $sink = $compress ? deflate_init(ZLIB_ENCODING_GZIP) : null;

            foreach ($dumper->dump($tables, true) as $chunk) {
                fwrite($handle, $sink !== null ? deflate_add($sink, $chunk, ZLIB_NO_FLUSH) : $chunk);
            }

            if ($sink !== null) {
                fwrite($handle, deflate_add($sink, '', ZLIB_FINISH));
            }

            fclose($handle);

            $stream = fopen($temp, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Dump tidak bisa dibaca kembali.');
            }

            Storage::disk($disk)->put($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            return (int) filesize($temp);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }

            @unlink($temp);
        }
    }

    /**
     * Delete dumps older than the retention window.
     */
    private function prune(string $disk, string $directory, int $keepDays): int
    {
        if ($keepDays <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($keepDays)->timestamp;
        $storage = Storage::disk($disk);
        $pruned = 0;

        foreach ($storage->files($directory) as $file) {
            if (! str_contains(basename($file), 'avanahr-')) {
                continue;
            }

            if ($storage->lastModified($file) < $cutoff) {
                $storage->delete($file);
                $pruned++;
            }
        }

        return $pruned;
    }

    /**
     * A backup that silently stopped running is worse than no backup, because
     * nobody looks until they need it. Tell the platform owners.
     */
    private function alertFailure(Throwable $exception): void
    {
        if (! config('security.backup.alert_on_failure', true)) {
            return;
        }

        Notifier::platformAlert(
            event: 'backup_failed',
            title: 'Backup database gagal',
            body: 'Backup terjadwal tidak berhasil dijalankan: '.$exception->getMessage(),
        );
    }

    private function humanBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1).' '.$unit;
            }

            $bytes = (int) ($bytes / 1024);
        }

        return $bytes.' TB';
    }
}

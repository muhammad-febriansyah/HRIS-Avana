<?php

namespace App\Console\Commands;

use App\Support\PrivateFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('avana:privatise-uploads {--dry-run}')]
#[Description('Move uploads that belong to a person off the public disk onto the private one.')]
class PrivatiseUploads extends Command
{
    /**
     * Personal uploads used to land on the `public` disk, which the web server
     * answers through a symlink: no session, no tenant, no permission. The
     * application writes them privately now and links to them signed, but the
     * files written before that are still sitting there, still downloadable by
     * anyone who knows the name — and no longer shown to the people who own
     * them, because their link points at the private disk where the file is
     * not.
     *
     * Moving them settles both: the old link stops answering, and the record's
     * own signed link starts working again.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $public = Storage::disk('public');
        $private = Storage::disk(PrivateFile::DISK);

        $moved = 0;
        $skipped = 0;

        foreach (PrivateFile::privatePrefixes() as $prefix) {
            $directory = rtrim($prefix, '/');

            foreach ($public->allFiles($directory) as $path) {
                // A file already on the private disk is the one the signed link
                // resolves to; the public copy is the stray, and overwriting
                // would replace the live file with it.
                if ($private->exists($path)) {
                    $skipped++;

                    if (! $dryRun) {
                        $public->delete($path);
                    }

                    continue;
                }

                $this->line(($dryRun ? 'akan dipindah: ' : 'dipindah: ').$path);

                if (! $dryRun) {
                    $contents = $public->get($path);

                    if ($contents === null) {
                        $skipped++;

                        continue;
                    }

                    $private->put($path, $contents);
                    $public->delete($path);
                }

                $moved++;
            }
        }

        $this->info($dryRun
            ? "{$moved} berkas akan dipindah ke disk privat ({$skipped} dilewati)."
            : "{$moved} berkas dipindah ke disk privat ({$skipped} dilewati).");

        return self::SUCCESS;
    }
}

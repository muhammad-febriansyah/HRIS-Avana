<?php

use App\Support\PrivateFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Upload trees that were public and should never have been.
     *
     * @var array<int, string>
     */
    private const TREES = [
        'documents',
        'claims',
        'recruitment',
        'employee-photos',
    ];

    /**
     * Move already-uploaded personal files onto the private disk.
     *
     * Changing where new uploads land does nothing for the ones already on
     * disk: every document, receipt, CV and photo uploaded before this stays
     * reachable at its old public URL. The stored paths are relative to the
     * disk, so moving the bytes is the whole change — no row is touched.
     *
     * The public copy is deleted, not merely duplicated. Leaving it would keep
     * the old URL working, which is the thing being fixed, and the web server
     * answers the symlink before Laravel ever sees the request.
     */
    public function up(): void
    {
        $this->moveTrees(Storage::disk('public'), Storage::disk(PrivateFile::DISK));
    }

    /**
     * Put the files back where they were, public URL and all.
     */
    public function down(): void
    {
        $this->moveTrees(Storage::disk(PrivateFile::DISK), Storage::disk('public'));
    }

    /**
     * Stream every file in the listed trees from one disk to the other.
     */
    private function moveTrees(Filesystem $from, Filesystem $to): void
    {
        foreach (self::TREES as $tree) {
            if (! $from->exists($tree)) {
                continue;
            }

            foreach ($from->allFiles($tree) as $path) {
                $stream = $from->readStream($path);

                if ($stream === null) {
                    continue;
                }

                $to->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                $from->delete($path);
            }

            $from->deleteDirectory($tree);
        }
    }
};

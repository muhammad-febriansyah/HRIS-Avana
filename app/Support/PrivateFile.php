<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Storage for uploads that are nobody's business but their owner's.
 *
 * Files written to the `public` disk sit under a symlink the web server
 * answers directly, so their URL needs no session, no tenant and no
 * permission — a personnel document, a medical result or a claim receipt was
 * readable by anyone holding the link, and stayed readable after the employee
 * left. The `public` disk is for assets meant to be public: logos, onboarding
 * slides, the marketing site.
 *
 * Everything here lands on the private disk instead and is reached through a
 * signed, expiring URL. The signature is what authorises the read, so the same
 * link works from the web app, from the phone's own HTTP client, and from a
 * browser the phone hands the link to — none of which share a session.
 */
final class PrivateFile
{
    /**
     * The disk with no public URL of its own.
     */
    public const DISK = 'local';

    /**
     * How long a generated link stays valid.
     */
    private const LINK_DAYS = 7;

    /**
     * Store an upload privately and return its path.
     */
    public static function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    /**
     * A signed, expiring URL for a stored file, or null when there is none.
     *
     * The expiry is pinned to midnight rather than "now plus a week" so the
     * URL for a given file is byte-identical all day. Anything caching images
     * by URL — the phone's image cache above all — keeps its entries instead
     * of re-downloading every render.
     */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return URL::temporarySignedRoute(
            'berkas.show',
            Carbon::today()->addDays(self::LINK_DAYS),
            ['path' => $path],
        );
    }

    /**
     * The upload trees this application keeps private.
     *
     * @var array<int, string>
     */
    private const PRIVATE_PREFIXES = ['employee-photos/', 'documents/', 'employee-documents/', 'claims/', 'reimbursements/', 'recruitment/'];

    /**
     * A URL for a path that may belong to either disk.
     *
     * An avatar is whichever of two things exists: the employee's own photo,
     * which is private, or a generated cartoon, which is not. Resolving by
     * prefix keeps both working without a filesystem probe per row.
     */
    public static function urlFor(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        foreach (self::PRIVATE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return self::url($path);
            }
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Delete a stored file, tolerating a null path or a missing file.
     */
    public static function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * Whether the file is actually there.
     */
    public static function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && Storage::disk(self::DISK)->exists($path);
    }
}

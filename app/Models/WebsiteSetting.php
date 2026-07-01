<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Platform-wide (super admin) website settings: branding, SEO meta, social
 * links and contact info. Stored as a single row and accessed via
 * {@see self::current()} (fresh) or {@see self::cached()} (request-cheap).
 */
final class WebsiteSetting extends Model
{
    protected $guarded = [];

    /**
     * Disk the uploaded branding images live on.
     */
    private const DISK = 'public';

    /**
     * Cache key for the singleton row so we don't hit the DB on every request.
     */
    private const CACHE_KEY = 'website_settings.current';

    /**
     * Bust the cache whenever the row is saved or deleted.
     */
    protected static function booted(): void
    {
        $forget = static fn () => Cache::forget(self::CACHE_KEY);

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * The singleton settings row, created on first access.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }

    /**
     * The singleton row, cached for the lifetime of the cache store. Use this
     * on hot paths (root view, shared Inertia props) rather than {@see current()}.
     */
    public static function cached(): self
    {
        return Cache::rememberForever(self::CACHE_KEY, static fn (): self => self::current());
    }

    /**
     * Public URL for the logo, or null when unset.
     */
    public function logoUrl(): ?string
    {
        return $this->fileUrl($this->logo_path);
    }

    /**
     * Public URL for the favicon, or null when unset.
     */
    public function faviconUrl(): ?string
    {
        return $this->fileUrl($this->favicon_path);
    }

    /**
     * Public URL for the Open Graph share image, or null when unset.
     */
    public function ogImageUrl(): ?string
    {
        return $this->fileUrl($this->og_image_path);
    }

    /**
     * Branding fields shared with the frontend (Inertia props).
     *
     * @return array{site_name: ?string, tagline: ?string, logo_url: ?string}
     */
    public function toBrandingArray(): array
    {
        return [
            'site_name' => $this->site_name,
            'tagline' => $this->tagline,
            'logo_url' => $this->logoUrl(),
        ];
    }

    /**
     * Public URL for a stored relative path (null-safe).
     */
    private function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk(self::DISK)->url($path) : null;
    }
}

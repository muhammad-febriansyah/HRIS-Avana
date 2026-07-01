<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
     * The singleton settings row, created on first access.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }

    /**
     * Alias of {@see current()} used on hot paths (root view, shared Inertia
     * props). Kept as a distinct method so a caching layer can be introduced
     * later without touching callers.
     */
    public static function cached(): self
    {
        return self::current();
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
     * Public branding, contact and social fields shared with the frontend
     * (Inertia props) for the navbar, footer and landing page.
     *
     * @return array{
     *     site_name: ?string,
     *     tagline: ?string,
     *     logo_url: ?string,
     *     contact: array{email: ?string, phone: ?string, whatsapp: ?string, address: ?string},
     *     social: array{facebook: ?string, instagram: ?string, twitter: ?string, youtube: ?string, linkedin: ?string, tiktok: ?string}
     * }
     */
    public function toBrandingArray(): array
    {
        return [
            'site_name' => $this->site_name,
            'tagline' => $this->tagline,
            'logo_url' => $this->logoUrl(),
            'contact' => [
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
                'whatsapp' => $this->contact_whatsapp,
                'address' => $this->contact_address,
            ],
            'social' => [
                'facebook' => $this->social_facebook,
                'instagram' => $this->social_instagram,
                'twitter' => $this->social_twitter,
                'youtube' => $this->social_youtube,
                'linkedin' => $this->social_linkedin,
                'tiktok' => $this->social_tiktok,
            ],
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

<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin screen for the public-facing website settings: branding (logo,
 * favicon), SEO meta, social links and contact details. A single settings row
 * is edited in place; replacing an image deletes the previous file so stale
 * uploads don't accumulate.
 */
class WebsiteSettingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Image fields handled as uploads, keyed by form input => storage column.
     *
     * @var array<string, string>
     */
    private const IMAGE_FIELDS = [
        'logo' => 'logo_path',
        'favicon' => 'favicon_path',
        'og_image' => 'og_image_path',
    ];

    /**
     * Disk + folder the uploaded branding images live on.
     */
    private const DISK = 'public';

    private const FOLDER = 'website';

    /**
     * Show the single-page (tabbed) website settings editor.
     */
    public function edit(Request $request): Response
    {
        $this->authorize('view', WebsiteSetting::class);

        $settings = WebsiteSetting::current();

        return Inertia::render('avana/website-settings/index', [
            'settings' => [
                'site_name' => $settings->site_name,
                'tagline' => $settings->tagline,
                'meta_title' => $settings->meta_title,
                'meta_description' => $settings->meta_description,
                'meta_keywords' => $settings->meta_keywords,
                'social_facebook' => $settings->social_facebook,
                'social_instagram' => $settings->social_instagram,
                'social_twitter' => $settings->social_twitter,
                'social_youtube' => $settings->social_youtube,
                'social_linkedin' => $settings->social_linkedin,
                'social_tiktok' => $settings->social_tiktok,
                'contact_email' => $settings->contact_email,
                'contact_phone' => $settings->contact_phone,
                'contact_whatsapp' => $settings->contact_whatsapp,
                'contact_address' => $settings->contact_address,
                'logo_url' => $settings->logoUrl(),
                'favicon_url' => $settings->faviconUrl(),
                'og_image_url' => $settings->ogImageUrl(),
            ],
        ]);
    }

    /**
     * Persist the settings, swapping out any replaced/removed images.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', WebsiteSetting::class);

        $validated = $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'social_facebook' => ['nullable', 'string', 'max:255'],
            'social_instagram' => ['nullable', 'string', 'max:255'],
            'social_twitter' => ['nullable', 'string', 'max:255'],
            'social_youtube' => ['nullable', 'string', 'max:255'],
            'social_linkedin' => ['nullable', 'string', 'max:255'],
            'social_tiktok' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_whatsapp' => ['nullable', 'string', 'max:50'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,svg', 'max:2048'],
            'og_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png,svg', 'max:1024'],
        ]);

        $settings = WebsiteSetting::current();

        // Text fields.
        $settings->fill(collect($validated)->except(array_keys(self::IMAGE_FIELDS))->all());

        // Image fields: replace (delete old) or remove on explicit request.
        foreach (self::IMAGE_FIELDS as $input => $column) {
            if ($request->file($input) instanceof UploadedFile) {
                $this->deleteFile($settings->{$column});
                $settings->{$column} = $request->file($input)->store(self::FOLDER, self::DISK);
            } elseif ($request->boolean("remove_{$input}")) {
                $this->deleteFile($settings->{$column});
                $settings->{$column} = null;
            }
        }

        $settings->save();

        return back()->with('success', 'Pengaturan website berhasil disimpan');
    }

    /**
     * Delete a previously stored file if it exists.
     */
    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}

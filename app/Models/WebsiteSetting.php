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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme' => 'array',
        ];
    }

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
     * Starting content for `privacy_policy`, seeded on first migration and
     * used by the public privacy page if a super admin has somehow cleared
     * the field entirely — the page is never blank.
     */
    public static function defaultPrivacyPolicyHtml(): string
    {
        return <<<'HTML'
            <p>Halaman ini menjelaskan bagaimana AvanaHR mengumpulkan, menyimpan, dan melindungi data yang dikelola melalui platform ini — baik data perusahaan pelanggan (tenant) maupun data karyawan yang diinput ke dalamnya.</p>
            <h2>Data yang dikumpulkan</h2>
            <ul>
            <li>Data akun dan perusahaan yang didaftarkan oleh admin tenant (nama perusahaan, kontak, pengaturan modul).</li>
            <li>Data kepegawaian yang diinput tenant: profil karyawan, absensi, cuti, payroll, dan dokumen terkait.</li>
            <li>Data lokasi (GPS) hanya dikumpulkan saat fitur absensi lokasi atau Live Tracking diaktifkan oleh tenant, dan hanya selama jam kerja yang ditentukan.</li>
            </ul>
            <h2>Bagaimana data dilindungi</h2>
            <ul>
            <li>Dokumen pribadi karyawan (KTP, kontrak, slip gaji, dsb.) disimpan di disk privat dan hanya dapat diakses lewat tautan bertanda tangan (signed URL) yang kedaluwarsa otomatis — bukan URL publik yang dapat ditebak.</li>
            <li>Akses ke setiap menu dan aksi mengikuti peran pengguna (role-based access control) — karyawan tidak otomatis bisa melihat data karyawan lain.</li>
            <li>Aktivitas pengguna pada data sensitif tercatat dalam Audit Trail yang dapat ditinjau admin tenant.</li>
            <li>Autentikasi dua faktor (TOTP) tersedia sebagai opsi tambahan untuk akun web.</li>
            </ul>
            <h2>Berbagi data dengan pihak ketiga</h2>
            <p>Data tenant tidak dijual atau dibagikan ke pihak ketiga untuk kepentingan pemasaran. Integrasi teknis (misalnya penyedia model AI untuk AI Assistant, gateway pembayaran untuk langganan) hanya memproses data seperlunya untuk menjalankan fitur yang diaktifkan tenant.</p>
            <h2>Hak pengguna</h2>
            <p>Admin tenant dapat meminta ekspor atau penghapusan data perusahaannya dengan menghubungi kami melalui kontak resmi.</p>
            HTML;
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
     *     social: array{facebook: ?string, instagram: ?string, twitter: ?string, youtube: ?string, linkedin: ?string, tiktok: ?string},
     *     apps: array{playstore_url: ?string, appstore_url: ?string}
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
            'apps' => [
                'playstore_url' => $this->playstore_url,
                'appstore_url' => $this->appstore_url,
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

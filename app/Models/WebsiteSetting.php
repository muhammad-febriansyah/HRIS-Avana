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
     * Starting content for `terms_of_service`, seeded the same way as
     * {@see defaultPrivacyPolicyHtml()}.
     */
    public static function defaultTermsOfServiceHtml(): string
    {
        return <<<'HTML'
            <p>Dengan menggunakan AvanaHR, perusahaan ("Tenant") dan penggunanya dianggap menyetujui syarat dan ketentuan berikut.</p>
            <h2>Penggunaan layanan</h2>
            <ul>
            <li>Tenant bertanggung jawab atas keakuratan data yang diinput ke dalam sistem, termasuk data karyawan dan komponen payroll.</li>
            <li>Akun pengguna bersifat personal dan tidak boleh dibagikan ke pihak lain di luar organisasi Tenant.</li>
            <li>Fitur yang tersedia mengikuti paket langganan yang aktif; sebagian fitur (mis. Live Tracking, AI Assistant) perlu diaktifkan terlebih dahulu oleh admin tenant.</li>
            </ul>
            <h2>Langganan &amp; pembayaran</h2>
            <p>Rincian harga dan komponen yang termasuk dalam paket dijelaskan pada halaman Harga. Perubahan paket atau jumlah pengguna dapat memengaruhi tagihan periode berikutnya.</p>
            <h2>Data milik Tenant</h2>
            <p>Data yang diinput Tenant (data karyawan, payroll, dokumen) tetap menjadi milik Tenant. Kebijakan penyimpanan dan perlindungan data dijelaskan lebih rinci pada Kebijakan Privasi.</p>
            <h2>Perubahan ketentuan</h2>
            <p>Ketentuan ini dapat diperbarui sewaktu-waktu. Perubahan signifikan akan diinformasikan kepada admin tenant melalui kanal kontak yang terdaftar.</p>
            HTML;
    }

    public static function defaultAccountDeletionHtml(): string
    {
        return <<<'HTML'
            <h2>Penghapusan Akun dan Data AvanaHR</h2>
            <p>Pengguna AvanaHR dapat mengajukan permintaan penghapusan akun dan data pribadi yang terkait dengan penggunaan layanan AvanaHR.</p>
            <h2>Cara Mengajukan Penghapusan Data</h2>
            <p>Untuk meminta penghapusan akun atau data:</p>
            <ol><li>Hubungi administrator atau HR perusahaan tempat Anda terdaftar di AvanaHR; atau</li><li>Kirim permintaan penghapusan data melalui kontak resmi AvanaHR yang tercantum pada website kami.</li></ol>
            <p>Dalam permintaan tersebut, sertakan informasi yang diperlukan untuk memverifikasi akun Anda, seperti nama dan alamat email yang digunakan pada AvanaHR.</p>
            <h2>Data yang Dapat Dihapus</h2>
            <p>Setelah permintaan berhasil diverifikasi, data yang dapat dihapus dapat mencakup:</p>
            <ul><li>informasi akun dan profil;</li><li>foto profil;</li><li>data perangkat terkait akun;</li><li>data atau dokumen pribadi yang tidak lagi diwajibkan untuk disimpan;</li><li>data lain yang dapat dihapus sesuai kebijakan perusahaan dan ketentuan yang berlaku.</li></ul>
            <h2>Data yang Mungkin Tetap Disimpan</h2>
            <p>Sebagian data terkait ketenagakerjaan dapat tetap disimpan apabila diperlukan untuk:</p>
            <ul><li>administrasi perusahaan;</li><li>pencatatan kehadiran;</li><li>payroll dan penggajian;</li><li>perpajakan;</li><li>audit;</li><li>penyelesaian sengketa;</li><li>kewajiban hukum atau peraturan yang berlaku.</li></ul>
            <p>Data yang tetap disimpan hanya akan dipertahankan selama diperlukan untuk tujuan tersebut dan sesuai dengan kebijakan retensi data perusahaan serta ketentuan hukum yang berlaku.</p>
            <h2>Proses Permintaan</h2>
            <p>Permintaan penghapusan akan ditinjau dan diverifikasi terlebih dahulu untuk melindungi akun dan data pengguna dari permintaan yang tidak sah.</p>
            <p>Apabila akun Anda dikelola oleh perusahaan atau organisasi tempat Anda bekerja, permintaan penghapusan tertentu dapat memerlukan persetujuan atau koordinasi dengan administrator perusahaan tersebut.</p>
            <h2>Kontak</h2>
            <p>Untuk pertanyaan atau permintaan terkait penghapusan data AvanaHR, silakan menghubungi kami melalui informasi kontak resmi yang tersedia di:</p>
            <p><strong>https://avanahr.id/</strong></p>
            <p>Informasi lebih lanjut mengenai pengelolaan data dapat dilihat pada Kebijakan Privasi AvanaHR.</p>
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
        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }
}

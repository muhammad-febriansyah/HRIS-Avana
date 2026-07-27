<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Announcements that actually carry a file, so the attachment path — image
 * preview, PDF card, size label, open/download — has something to render.
 *
 * The wall and the app both showed "no attachment" simply because no seeded
 * announcement had one. Idempotent by title.
 */
class AnnouncementDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->orderBy('id')->first();

        if ($tenant === null) {
            $this->command?->warn('Belum ada tenant. Jalankan AvanaDemoSeeder dulu.');

            return;
        }

        $this->create($tenant, [
            'title' => 'Surat Edaran Cuti Bersama 2026',
            'body' => 'Terlampir surat edaran resmi mengenai cuti bersama tahun 2026. '
                .'Mohon dibaca dan disesuaikan dengan rencana cuti masing-masing.',
            'category' => 'Kebijakan',
            'pinned' => true,
            'days_ago' => 0,
        ], $this->pdfAttachment($tenant->id));

        $this->create($tenant, [
            'title' => 'Poster Gathering Karyawan',
            'body' => 'Gathering tahunan digelar 20 Agustus di Pantai Anyer. '
                .'Detail rundown menyusul, poster terlampir.',
            'category' => 'Acara',
            'pinned' => false,
            'days_ago' => 2,
        ], $this->imageAttachment($tenant->id));

        $this->create($tenant, [
            'title' => 'Perubahan Jam Operasional Kantor',
            'body' => 'Mulai Senin depan jam operasional kantor menjadi 08.00-17.00. '
                .'Absensi menyesuaikan otomatis.',
            'category' => 'Operasional',
            'pinned' => false,
            'days_ago' => 5,
        ], null);

        $this->command?->info('Pengumuman demo siap: 2 berlampiran, 1 tanpa lampiran.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $attachment
     */
    private function create(Tenant $tenant, array $data, ?array $attachment): void
    {
        $publishedAt = Carbon::now()->subDays($data['days_ago']);

        Announcement::firstOrCreate(
            ['tenant_id' => $tenant->id, 'title' => $data['title']],
            [
                'body' => $data['body'],
                'category' => $data['category'],
                'status' => 'published',
                'pinned' => $data['pinned'],
                'published_at' => $publishedAt,
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt,
                ...($attachment ?? []),
            ],
        );
    }

    /**
     * A real dompdf circular, so the PDF card and the device viewer both get
     * something openable rather than a stub.
     *
     * @return array<string, mixed>
     */
    private function pdfAttachment(int $tenantId): array
    {
        $html = '<h2>SURAT EDARAN</h2>'
            .'<p>Nomor: SE-HR-014/2026</p>'
            .'<h3>Perihal: Cuti Bersama Tahun 2026</h3>'
            .'<p>Sehubungan dengan Surat Keputusan Bersama Menteri, berikut hari '
            .'cuti bersama yang berlaku di lingkungan perusahaan:</p>'
            .'<ul><li>2 Mei — Hari Raya Idul Fitri</li>'
            .'<li>26 Desember — Hari Raya Natal</li></ul>'
            .'<p>Cuti bersama memotong jatah cuti tahunan sesuai ketentuan.</p>';

        $path = "announcements/{$tenantId}/surat-edaran-cuti-bersama-2026.pdf";
        $bytes = Pdf::loadHTML($html)->output();

        Storage::disk('public')->put($path, $bytes);

        return [
            'attachment_path' => $path,
            'attachment_name' => 'surat-edaran-cuti-bersama-2026.pdf',
            'attachment_mime' => 'application/pdf',
            'attachment_size' => strlen($bytes),
        ];
    }

    /**
     * A generated PNG poster — no binary fixture to keep in the repo.
     *
     * @return array<string, mixed>
     */
    private function imageAttachment(int $tenantId): array
    {
        $width = 1200;
        $height = 800;
        $image = imagecreatetruecolor($width, $height);

        $background = imagecolorallocate($image, 47, 84, 201);
        $accent = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        imagefilledrectangle($image, 80, 560, 1120, 566, $accent);
        imagestring($image, 5, 90, 500, 'GATHERING KARYAWAN 2026', $accent);
        imagestring($image, 5, 90, 600, '20 AGUSTUS - PANTAI ANYER', $accent);

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $path = "announcements/{$tenantId}/poster-gathering-2026.png";
        Storage::disk('public')->put($path, $bytes);

        return [
            'attachment_path' => $path,
            'attachment_name' => 'poster-gathering-2026.png',
            'attachment_mime' => 'image/png',
            'attachment_size' => strlen($bytes),
        ];
    }
}

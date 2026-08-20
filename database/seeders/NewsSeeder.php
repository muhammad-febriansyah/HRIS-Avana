<?php

namespace Database\Seeders;

use App\Models\News;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds 6 published demo articles for the public "Berita" landing section.
 * Each photo is downloaded from Picsum (stable, ID-pinned URLs) and stored on
 * the public disk exactly like an editor-uploaded image, so the section reads
 * like real published content instead of empty placeholders.
 */
class NewsSeeder extends Seeder
{
    use WithoutModelEvents;

    private const DISK = 'public';

    private const FOLDER = 'news';

    public function run(): void
    {
        $articles = [
            [
                'title' => '5 Tren HR Digital yang Wajib Diperhatikan Perusahaan Indonesia di 2026',
                'category' => 'HR Tips',
                'picsum_id' => 180,
                'excerpt' => 'Dari otomasi payroll sampai AI Assistant, berikut tren transformasi digital HR yang mulai jadi standar baru di perusahaan Indonesia.',
                'body' => '<p>Transformasi digital di fungsi HR bukan lagi sekadar wacana — perusahaan yang bergerak cepat sudah mulai menerapkannya sebagai standar operasional.</p><p>Berikut beberapa tren yang paling banyak diadopsi:</p><ul><li><strong>Otomasi payroll dan kepatuhan pajak.</strong> Perhitungan PPh 21 dan BPJS yang dulunya manual kini dijalankan otomatis mengikuti aturan terbaru.</li><li><strong>Absensi berbasis GPS dan verifikasi wajah.</strong> Memastikan kehadiran karyawan lapangan tercatat akurat tanpa proses manual.</li><li><strong>AI Assistant untuk kebijakan internal.</strong> Karyawan bisa bertanya langsung soal cuti, reimbursement, atau SOP tanpa menunggu balasan tim HR.</li><li><strong>Analitik workforce real-time.</strong> Manajemen bisa melihat tren headcount, turnover, dan distribusi payroll kapan saja.</li></ul><p>Perusahaan yang mengadopsi kombinasi ini lebih cepat mengambil keputusan karena data HR tidak lagi tersebar di banyak sistem terpisah.</p>',
                'is_featured' => true,
                'days_ago' => 2,
            ],
            [
                'title' => 'Panduan Lengkap Perhitungan TER PPh 21 untuk Payroll 2024',
                'category' => 'Regulasi',
                'picsum_id' => 20,
                'excerpt' => 'Skema Tarif Efektif Rata-rata (TER) mengubah cara perusahaan menghitung PPh 21 bulanan. Simak ringkasan penerapannya di sistem payroll.',
                'body' => '<p>Sejak diberlakukannya skema Tarif Efektif Rata-rata (TER), perusahaan tidak lagi menghitung PPh 21 bulanan dengan tarif progresif tahunan secara langsung — melainkan mengacu pada tabel tarif efektif berdasarkan status PTKP dan penghasilan bruto bulanan.</p><p>Beberapa hal yang perlu dipastikan tim payroll:</p><ol><li>Kategori TER karyawan (A, B, atau C) sudah sesuai status PTKP terbaru.</li><li>Penghasilan bruto bulanan dihitung mencakup seluruh komponen upah tetap dan tidak tetap.</li><li>Perhitungan masa Desember tetap menggunakan tarif progresif tahunan sebagai penyesuaian akhir.</li></ol><p>Sistem payroll yang sudah mengikuti tabel TER terbaru membantu tim HR menghindari selisih perhitungan pajak di akhir tahun.</p>',
                'is_featured' => false,
                'days_ago' => 6,
            ],
            [
                'title' => 'AvanaHR Perkenalkan Live Tracking untuk Tim yang Bekerja di Lapangan',
                'category' => 'Produk',
                'picsum_id' => 3,
                'excerpt' => 'Fitur baru ini membantu perusahaan memantau posisi dan riwayat perjalanan karyawan lapangan langsung dari dashboard HR.',
                'body' => '<p>Perusahaan dengan tim sales, teknisi, atau kurir yang bekerja di luar kantor sering kesulitan memastikan aktivitas lapangan berjalan sesuai rencana. Fitur Live Tracking hadir untuk menjawab kebutuhan itu.</p><p>Dengan Live Tracking, HR dan manajer tim dapat:</p><ul><li>Melihat posisi karyawan lapangan secara real-time di peta.</li><li>Meninjau riwayat rute perjalanan per sesi kerja.</li><li>Memastikan kunjungan klien tercatat sesuai jadwal.</li></ul><p>Fitur ini terhubung langsung dengan modul Attendance, sehingga data kehadiran dan aktivitas lapangan tetap berada dalam satu platform.</p>',
                'is_featured' => true,
                'days_ago' => 10,
            ],
            [
                'title' => 'Cara Membangun Budaya Kerja yang Sehat di Tengah Hybrid Working',
                'category' => 'HR Tips',
                'picsum_id' => 4,
                'excerpt' => 'Pola kerja hybrid menuntut pendekatan baru dalam menjaga keterlibatan dan kesejahteraan karyawan. Berikut beberapa langkah praktisnya.',
                'body' => '<p>Pola kerja hybrid memberi fleksibilitas, tapi juga tantangan baru bagi tim HR dalam menjaga keterlibatan karyawan. Berikut beberapa langkah yang bisa diterapkan:</p><ul><li><strong>Komunikasi terjadwal dan konsisten.</strong> Sesi check-in rutin membantu tim tetap selaras meski tidak selalu berada di kantor yang sama.</li><li><strong>Transparansi kebijakan kerja.</strong> Karyawan perlu tahu dengan jelas hak dan kewajiban terkait jadwal hybrid.</li><li><strong>Akses informasi yang setara.</strong> Baik karyawan di kantor maupun remote perlu akses yang sama terhadap dokumen dan pengumuman perusahaan.</li></ul><p>Budaya kerja yang sehat pada akhirnya dibangun dari proses HR yang jelas dan mudah diakses oleh seluruh karyawan, di mana pun mereka bekerja.</p>',
                'is_featured' => false,
                'days_ago' => 15,
            ],
            [
                'title' => 'Tips Onboarding Karyawan Baru agar Cepat Produktif',
                'category' => 'HR Tips',
                'picsum_id' => 48,
                'excerpt' => 'Onboarding yang terstruktur membantu karyawan baru memahami peran dan budaya kerja lebih cepat. Ini langkah-langkah yang bisa diterapkan tim HR.',
                'body' => '<p>Onboarding bukan sekadar administrasi hari pertama masuk kerja — proses ini menentukan seberapa cepat karyawan baru bisa berkontribusi.</p><p>Beberapa langkah yang membantu proses onboarding berjalan lebih efektif:</p><ol><li>Siapkan checklist tugas onboarding sejak hari pertama, mulai dari akses sistem sampai perkenalan tim.</li><li>Tetapkan mentor atau buddy untuk membantu adaptasi di minggu-minggu awal.</li><li>Pantau progres onboarding secara berkala, bukan hanya di akhir masa percobaan.</li></ol><p>Proses yang terpantau jelas membuat karyawan baru merasa didampingi, sekaligus memberi HR gambaran progres tanpa harus menanyakan satu per satu secara manual.</p>',
                'is_featured' => false,
                'days_ago' => 21,
            ],
            [
                'title' => 'AvanaHR Hadirkan AI Assistant yang Menjawab Berdasarkan SOP Perusahaan',
                'category' => 'Produk',
                'picsum_id' => 60,
                'excerpt' => 'Berbeda dari chatbot generik, AI Assistant AvanaHR hanya menjawab dari dokumen SOP yang sudah dipublikasikan perusahaan.',
                'body' => '<p>Salah satu tantangan HR sehari-hari adalah menjawab pertanyaan berulang dari karyawan soal kebijakan internal — mulai dari prosedur cuti sampai reimbursement.</p><p>AI Assistant AvanaHR dirancang untuk membantu hal ini dengan pendekatan yang berbeda dari chatbot generik pada umumnya:</p><ul><li>Jawaban disusun berdasarkan dokumen SOP yang telah dipublikasikan perusahaan, bukan hasil karangan dari internet.</li><li>Tim IT/HR dapat memilih provider dan model AI yang digunakan, sesuai kebijakan dan anggaran perusahaan.</li><li>Tersedia untuk HR maupun karyawan, sehingga pertanyaan rutin bisa terjawab tanpa menunggu respons manual.</li></ul><p>Dengan pendekatan ini, jawaban yang diberikan tetap sesuai dengan kebijakan internal masing-masing perusahaan.</p>',
                'is_featured' => false,
                'days_ago' => 28,
            ],
        ];

        foreach ($articles as $article) {
            $imagePath = $this->downloadImage($article['picsum_id']);
            $publishedAt = now()->subDays($article['days_ago']);

            News::updateOrCreate(
                ['slug' => Str::slug($article['title'])],
                [
                    'title' => $article['title'],
                    'excerpt' => $article['excerpt'],
                    'body' => $article['body'],
                    'category' => $article['category'],
                    'image_path' => $imagePath,
                    'status' => 'published',
                    'is_featured' => $article['is_featured'],
                    'published_at' => $publishedAt,
                    'created_at' => $publishedAt,
                    'updated_at' => $publishedAt,
                ],
            );
        }
    }

    private function downloadImage(int $picsumId): ?string
    {
        $target = self::FOLDER.'/'.$picsumId.'.jpg';

        if (Storage::disk(self::DISK)->exists($target)) {
            return $target;
        }

        $response = Http::timeout(20)->get("https://picsum.photos/id/{$picsumId}/1200/800");

        if (! $response->successful()) {
            return null;
        }

        Storage::disk(self::DISK)->put($target, $response->body());

        return $target;
    }
}

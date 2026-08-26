<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            ['question' => 'Apa itu AvanaHR?', 'answer' => 'AvanaHR adalah platform HRIS dan payroll terintegrasi untuk mengelola data karyawan, absensi, cuti, payroll, kinerja, rekrutmen, workforce analytics, dan AI Assistant dalam satu platform.'],
            ['question' => 'Untuk perusahaan seperti apa AvanaHR digunakan?', 'answer' => 'AvanaHR ditujukan untuk perusahaan Indonesia yang ingin menghubungkan proses HR dan data workforce, termasuk organisasi dengan kebutuhan multi-branch, shift, payroll, dan pelaporan yang lebih terpusat.'],
            ['question' => 'Apakah AvanaHR mendukung perhitungan PPh 21 TER dan BPJS?', 'answer' => 'AvanaHR menyediakan otomatisasi payroll dengan perhitungan PPh 21 tarif TER dan BPJS. Detail cakupan, periode regulasi, serta tanggung jawab verifikasi harus dikonfirmasi pada demo dan dokumentasi produk terbaru.'],
            ['question' => 'Apakah absensi dapat menggunakan GPS?', 'answer' => 'AvanaHR menampilkan modul Attendance dengan GPS, geotagging, verifikasi wajah, dan pengelolaan shift roster. Ketersediaan setiap opsi dapat bergantung pada paket dan konfigurasi perusahaan.'],
            ['question' => 'Bagaimana AI Assistant mendapatkan jawabannya?', 'answer' => 'AI Assistant menyusun jawaban dari SOP perusahaan yang telah dipublikasikan di AvanaHR. Provider dan model AI dapat diatur oleh tim IT atau HR.'],
            ['question' => 'Berapa harga AvanaHR?', 'answer' => 'Harga AvanaHR mulai Rp5.000 per bulan. Detail perhitungan, minimum pengguna, dan komponen yang termasuk dapat dikonfirmasi sesuai konfigurasi perusahaan.'],
            ['question' => 'Apakah tersedia demo dan uji coba?', 'answer' => 'AvanaHR menyediakan jadwal demo, kontak WhatsApp, dan uji coba gratis. Syarat, durasi, batas fitur, serta proses aktivasi dapat dikonfirmasi dengan tim AvanaHR.'],
            ['question' => 'Bagaimana keamanan dan kontrol aksesnya?', 'answer' => 'AvanaHR menyediakan role-based access, approval workflow, dan kontrol aktivitas. Rincian teknis penyimpanan, enkripsi, backup, retensi, dan akses data mengikuti dokumentasi resmi yang berlaku.'],
            ['question' => 'Apakah AvanaHR mendukung banyak cabang?', 'answer' => 'Ya, AvanaHR dapat membantu perusahaan mengelola data workforce, struktur organisasi, dan pelaporan dengan kebutuhan multi-branch secara lebih terpusat.'],
            ['question' => 'Apakah data karyawan dapat diekspor?', 'answer' => 'Data dan laporan dapat diekspor sesuai modul serta hak akses pengguna yang diberikan oleh administrator perusahaan.'],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(['question' => $faq['question']], $faq);
        }
    }
}

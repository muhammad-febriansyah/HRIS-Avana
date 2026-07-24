<?php

namespace Database\Seeders;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds a realistic month of AI Assistant usage for the demo tenant so the
 * token meter on the assistant page shows consumed vs. remaining allowance
 * instead of an empty bar. Idempotent: conversations are keyed by title.
 */
class AiUsageDemoSeeder extends Seeder
{
    /**
     * Demo conversations. Each exchange carries plausible prompt/completion
     * token counts for a tool-augmented HR answer.
     *
     * @var array<int, array{title: string, days_ago: int, turns: array<int, array{user: string, assistant: string, prompt: int, completion: int}>}>
     */
    private const CONVERSATIONS = [
        [
            'title' => 'Cara menjalankan payroll bulanan',
            'days_ago' => 14,
            'turns' => [
                ['user' => 'Bagaimana cara menjalankan payroll bulanan?', 'assistant' => 'Untuk menjalankan payroll bulanan: buka menu Payroll, pilih periode berjalan, klik Proses, tinjau komponen gaji & PPh21 tiap karyawan, lalu finalisasi dan terbitkan slip gaji.', 'prompt' => 4200, 'completion' => 1600],
                ['user' => 'Apa yang perlu disiapkan sebelum proses payroll?', 'assistant' => 'Pastikan data kehadiran bulan berjalan sudah lengkap, komponen gaji & tunjangan ter-set, status BPJS/PPh21 terkonfigurasi, dan tidak ada pengajuan lembur/cuti yang belum diproses.', 'prompt' => 6800, 'completion' => 2100],
            ],
        ],
        [
            'title' => 'Draf SOP pengajuan cuti karyawan',
            'days_ago' => 12,
            'turns' => [
                ['user' => 'Buatkan draf SOP pengajuan cuti karyawan', 'assistant' => "**SOP Pengajuan Cuti Karyawan**\n1. Karyawan mengajukan cuti via aplikasi minimal H-3.\n2. Sistem meneruskan ke atasan langsung.\n3. Atasan menyetujui/menolak maksimal 2x24 jam.\n4. HR memverifikasi saldo cuti.\n5. Notifikasi hasil dikirim ke karyawan.", 'prompt' => 5200, 'completion' => 3800],
                ['user' => 'Tambahkan bagian tanggung jawab HR', 'assistant' => '**Tanggung Jawab HR:** memverifikasi saldo cuti, memastikan kepatuhan kebijakan, mengarsipkan dokumen pendukung, dan memperbarui rekap kehadiran setelah cuti disetujui.', 'prompt' => 9800, 'completion' => 2600],
            ],
        ],
        [
            'title' => 'Ringkas alur onboarding karyawan baru',
            'days_ago' => 11,
            'turns' => [
                ['user' => 'Ringkas alur onboarding karyawan baru', 'assistant' => 'Alur onboarding: penawaran kerja & kontrak, pengumpulan dokumen, pembuatan akun & email, orientasi perusahaan, penyerahan aset, dan penetapan atasan serta target 90 hari pertama.', 'prompt' => 4100, 'completion' => 2400],
            ],
        ],
        [
            'title' => 'Komponen slip gaji dan PPh21',
            'days_ago' => 9,
            'turns' => [
                ['user' => 'Jelaskan komponen slip gaji dan PPh21', 'assistant' => 'Slip gaji terdiri dari penerimaan (gaji pokok, tunjangan) dan potongan (BPJS, koperasi, PPh21). PPh21 dihitung dengan skema TER bulanan atas penghasilan bruto sesuai status PTKP.', 'prompt' => 5600, 'completion' => 3100],
                ['user' => 'Bagaimana TER bulanan diterapkan?', 'assistant' => 'TER (Tarif Efektif Rata-rata) bulanan mengalikan penghasilan bruto sebulan dengan tarif sesuai kategori PTKP (A/B/C). Pada Desember dilakukan penghitungan ulang tahunan untuk penyesuaian.', 'prompt' => 11200, 'completion' => 2900],
            ],
        ],
        [
            'title' => 'SOP penggunaan kendaraan dinas',
            'days_ago' => 7,
            'turns' => [
                ['user' => 'Buat SOP penggunaan kendaraan dinas', 'assistant' => "**SOP Penggunaan Kendaraan Dinas**\n1. Ajukan permohonan pemakaian ke bagian umum.\n2. Sertakan tujuan, tanggal, dan estimasi jarak.\n3. Persetujuan atasan & bagian umum.\n4. Catat kilometer awal/akhir dan bahan bakar.\n5. Kembalikan kendaraan dalam kondisi bersih.", 'prompt' => 4800, 'completion' => 4200],
                ['user' => 'Tambahkan sanksi bila melanggar', 'assistant' => '**Sanksi:** teguran tertulis untuk pelanggaran ringan, penggantian biaya kerusakan akibat kelalaian, dan pencabutan hak pakai kendaraan dinas untuk pelanggaran berulang.', 'prompt' => 10600, 'completion' => 3400],
            ],
        ],
        [
            'title' => 'Sisa cuti tahun ini',
            'days_ago' => 5,
            'turns' => [
                ['user' => 'Berapa sisa cuti saya tahun ini?', 'assistant' => 'Berdasarkan data Anda, Cuti Tahunan tersisa 8 dari 12 hari (terpakai 4). Cuti Sakit tersisa 12 hari. Cuti Penting tersisa 2 hari.', 'prompt' => 3200, 'completion' => 900],
            ],
        ],
        [
            'title' => 'Analisa tingkat kehadiran bulan lalu',
            'days_ago' => 3,
            'turns' => [
                ['user' => 'Analisa tingkat kehadiran tim bulan lalu', 'assistant' => 'Tingkat kehadiran bulan lalu 94%: mayoritas hadir tepat waktu, keterlambatan terkonsentrasi pada awal pekan, dan tidak ada alpha. Disarankan meninjau kebijakan jam masuk hari Senin.', 'prompt' => 7400, 'completion' => 2600],
            ],
        ],
        [
            'title' => 'Template SOP lembur dan persetujuan',
            'days_ago' => 1,
            'turns' => [
                ['user' => 'Buatkan template SOP lembur dan persetujuannya', 'assistant' => "**SOP Lembur**\n1. Lembur diajukan sebelum pelaksanaan via aplikasi.\n2. Cantumkan alasan, durasi, dan output.\n3. Atasan menyetujui berdasarkan urgensi.\n4. HR memvalidasi untuk perhitungan upah lembur.\n5. Rekap lembur ditutup setiap akhir periode payroll.", 'prompt' => 6200, 'completion' => 4600],
                ['user' => 'Sesuaikan dengan aturan upah lembur pemerintah', 'assistant' => 'Perhitungan upah lembur mengikuti ketentuan: jam pertama 1,5x upah/jam dan jam berikutnya 2x, dengan dasar 1/173 dari upah sebulan. Lembur hari libur memakai tarif berbeda sesuai regulasi.', 'prompt' => 12800, 'completion' => 3300],
            ],
        ],
    ];

    public function run(): void
    {
        $tenant = Tenant::where('slug', 'nusantara')->first();
        $admin = User::where('email', 'rina.a@nusantara.co.id')->first();

        if ($tenant === null || $admin === null) {
            return;
        }

        // Ensure the demo tenant carries the AI add-on allowance even when this
        // seeder is run standalone against an existing database.
        if ($tenant->ai_token_quota === null) {
            $tenant->forceFill(['ai_token_quota' => 500_000])->save();
        }

        foreach (self::CONVERSATIONS as $spec) {
            $startedAt = now()->subDays($spec['days_ago']);

            $conversation = AiConversation::firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $admin->id, 'title' => $spec['title']],
                ['created_at' => $startedAt, 'updated_at' => $startedAt],
            );

            // Skip message seeding when the conversation already has history.
            if (! $conversation->wasRecentlyCreated) {
                continue;
            }

            $at = $startedAt->copy();

            foreach ($spec['turns'] as $turn) {
                AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'tenant_id' => $tenant->id,
                    'user_id' => $admin->id,
                    'role' => 'user',
                    'content' => $turn['user'],
                    'created_at' => $at->copy(),
                    'updated_at' => $at->copy(),
                ]);

                $at = $at->copy()->addSeconds(6);

                AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'tenant_id' => $tenant->id,
                    'user_id' => $admin->id,
                    'role' => 'assistant',
                    'content' => $turn['assistant'],
                    'model' => 'gpt-4o-mini',
                    'prompt_tokens' => $turn['prompt'],
                    'completion_tokens' => $turn['completion'],
                    'total_tokens' => $turn['prompt'] + $turn['completion'],
                    'created_at' => $at->copy(),
                    'updated_at' => $at->copy(),
                ]);

                $at = $at->copy()->addMinutes(2);
            }

            $conversation->forceFill(['updated_at' => $at])->save();
        }
    }
}

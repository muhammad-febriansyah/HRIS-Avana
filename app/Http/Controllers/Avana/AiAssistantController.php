<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AiAssistantController extends Controller
{
    /**
     * Roles that may always use the AI assistant within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Render the chat-style assistant, surfacing the last question/answer pair.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('avana/ai/index', [
            'question' => $request->session()->get('question'),
            'answer' => $request->session()->get('answer'),
        ]);
    }

    /**
     * Answer a question with a deterministic, keyword-based HR reply (demo mode).
     */
    public function ask(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $answer = $this->buildAnswer($data['message']);

        return back()
            ->with('question', $data['message'])
            ->with('answer', $answer);
    }

    /**
     * Build a canned HR answer from keywords found in the message.
     */
    private function buildAnswer(string $message): string
    {
        $text = Str::lower($message);

        if (Str::contains($text, ['gaji', 'payroll', 'thr', 'slip', 'upah'])) {
            return 'Untuk payroll: buka menu Payroll untuk membuat periode, menjalankan perhitungan (run), dan mengunci gaji. '
                .'Komponen tunjangan/potongan diatur pada Konfigurasi Payroll, sedangkan slip dan file transfer bank dapat diunduh setelah run. (mode demo)';
        }

        if (Str::contains($text, ['cuti', 'leave', 'izin', 'libur'])) {
            return 'Untuk cuti & izin: pengajuan dilakukan di menu Cuti & Lembur, lalu menunggu persetujuan atasan. '
                .'Saldo cuti tahunan tervalidasi otomatis saat pengajuan, dan jenis cuti dapat dikonfigurasi di Jenis Cuti. (mode demo)';
        }

        if (Str::contains($text, ['karyawan', 'pegawai', 'headcount', 'turnover'])) {
            return 'Untuk data karyawan: menu Karyawan menampilkan headcount, status kepegawaian, dan struktur organisasi. '
                .'Laporan turnover serta demografi tersedia di menu Laporan/Analytics. (mode demo)';
        }

        if (Str::contains($text, ['absen', 'kehadiran', 'lembur', 'shift'])) {
            return 'Untuk absensi: rekap kehadiran, keterlambatan, dan koreksi absensi ada di menu Absensi. '
                .'Pengaturan shift dan roster diatur pada menu Roster. (mode demo)';
        }

        return 'Saya asisten HR AvanaHR (mode demo). Saya dapat membantu menjelaskan modul payroll, cuti, '
            .'absensi, dan data karyawan. Coba tanyakan kata kunci seperti "gaji", "cuti", "absensi", atau "karyawan".';
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasEmployeePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'employee.'));

        abort_unless($isPrivileged || $hasEmployeePermission, 403);
    }
}

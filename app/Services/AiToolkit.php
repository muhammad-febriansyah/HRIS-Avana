<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Attendance;
use App\Models\Claim;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PermissionRequest;
use App\Models\Sop;
use App\Models\User;
use App\Models\WfhRequest;
use App\Support\AvanaNav;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Prism\Prism\Tool;

/**
 * Builds the set of data-access tools the AI assistant may call, scoped to the
 * logged-in user and their tenant. Employee-level tools expose only the user's
 * own data; tenant-wide tools are gated behind the relevant view permission.
 */
final class AiToolkit
{
    public function __construct(private readonly User $user) {}

    /**
     * @return array<int, Tool>
     */
    public static function forUser(User $user): array
    {
        return (new self($user))->build();
    }

    /**
     * @return array<int, Tool>
     */
    private function build(): array
    {
        $tools = [];

        // Personal tools are registered even for an account with no employee
        // record (a bare HR/admin login). Withholding them made the model
        // improvise — it used to answer "modul Cuti tidak tersedia" and invent
        // a menu path. Registered, each one reports the real reason instead.
        $tools[] = $this->saldoCutiTool();
        $tools[] = $this->slipGajiTool();
        $tools[] = $this->rekapKehadiranTool();
        $tools[] = $this->pengajuanSayaTool();
        $tools[] = $this->profilSayaTool();
        $tools[] = $this->inboxPersetujuanTool();

        // What this account can actually reach, so "fitur apa saja yang ada?"
        // and "menunya di mana?" are answered from the real sidebar.
        $tools[] = $this->fiturTersediaTool();

        // SOP tools need no employee record — visibility alone decides which
        // documents the caller may be shown.
        $tools[] = $this->daftarSopTool();
        $tools[] = $this->bacaSopTool();

        if ($this->can('employee.view')) {
            $tools[] = $this->cariKaryawanTool();
            $tools[] = $this->statistikKaryawanTool();
        }

        if ($this->can('payroll.view')) {
            $tools[] = $this->ringkasanPayrollTool();
        }

        if ($this->can('recruitment.view')) {
            $tools[] = $this->ringkasanRekrutmenTool();
        }

        return $tools;
    }

    private function employee(): ?Employee
    {
        return $this->user->employee;
    }

    /**
     * The line a personal tool returns when the signed-in account carries no
     * employee record, so there is no "my leave / my payslip" to read. Stating
     * the real cause keeps the model from inventing one.
     */
    private function noEmployeeRecord(string $subject): string
    {
        return "Akun Anda ({$this->user->name}) tidak tertaut ke data karyawan mana pun, "
            ."jadi {$subject} tidak ada. Ini akun administratif, bukan akun karyawan. "
            .'Minta Admin HR menautkan akun ini ke data karyawan bila Anda memang perlu akses layanan mandiri.';
    }

    private function can(string $code): bool
    {
        return $this->user->isSuperAdmin() || $this->user->hasPermissionTo($code);
    }

    private function tenantId(): int
    {
        return (int) $this->user->tenant_id;
    }

    private function rp(float|int|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function date(mixed $value): string
    {
        return $value !== null ? Carbon::parse($value)->translatedFormat('d M Y') : '-';
    }

    private function saldoCutiTool(): Tool
    {
        return (new Tool)
            ->as('saldo_cuti')
            ->for('Saldo/sisa cuti karyawan yang sedang login untuk tahun berjalan, per jenis cuti.')
            ->using(function (): string {
                $employee = $this->employee();

                if ($employee === null) {
                    return $this->noEmployeeRecord('saldo cuti pribadi');
                }

                $names = LeaveType::where('tenant_id', $this->tenantId())->pluck('name', 'id');

                $balances = LeaveBalance::where('tenant_id', $this->tenantId())
                    ->where('employee_id', $employee->id)
                    ->where('year', (int) Carbon::now()->year)
                    ->get();

                if ($balances->isEmpty()) {
                    return 'Belum ada data saldo cuti untuk tahun ini.';
                }

                return $balances->map(fn (LeaveBalance $b): string => sprintf(
                    '%s: sisa %s dari %s hari (terpakai %s)',
                    $names[$b->leave_type_id] ?? 'Cuti',
                    $b->remaining,
                    $b->quota,
                    $b->used,
                ))->implode('; ');
            });
    }

    private function slipGajiTool(): Tool
    {
        return (new Tool)
            ->as('slip_gaji')
            ->for('Slip gaji terbaru karyawan yang sedang login: gaji kotor, potongan, PPh21, dan gaji bersih.')
            ->using(function (): string {
                $employee = $this->employee();

                if ($employee === null) {
                    return $this->noEmployeeRecord('slip gaji pribadi');
                }

                $item = PayrollRunItem::where('tenant_id', $this->tenantId())
                    ->where('employee_id', $employee->id)
                    ->latest('payroll_period_id')
                    ->latest('id')
                    ->first();

                if ($item === null) {
                    return 'Belum ada slip gaji yang tersedia.';
                }

                $period = PayrollPeriod::forTenant($this->tenantId())->find($item->payroll_period_id);

                return sprintf(
                    'Slip %s — Gaji kotor: %s, Potongan: %s, PPh21: %s, Gaji bersih: %s. Status: %s.',
                    $period?->name ?? '-',
                    $this->rp($item->gross_salary),
                    $this->rp($item->total_deduction),
                    $this->rp($item->pph21_total),
                    $this->rp($item->net_salary),
                    $item->status,
                );
            });
    }

    private function rekapKehadiranTool(): Tool
    {
        return (new Tool)
            ->as('rekap_kehadiran')
            ->for('Rekap kehadiran karyawan yang sedang login pada satu bulan (jumlah hadir, terlambat, alpha, cuti/izin).')
            ->withStringParameter('bulan', 'Bulan dalam format YYYY-MM, contoh 2026-06. Kosongkan untuk bulan berjalan.', false)
            ->using(function (?string $bulan = null): string {
                $employee = $this->employee();

                if ($employee === null) {
                    return $this->noEmployeeRecord('rekap kehadiran pribadi');
                }

                $date = $bulan !== null && $bulan !== '' ? Carbon::parse($bulan.'-01') : Carbon::now();

                $counts = Attendance::where('tenant_id', $this->tenantId())
                    ->where('employee_id', $employee->id)
                    ->whereBetween('date', [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()])
                    ->selectRaw('status, count(*) as c')
                    ->groupBy('status')
                    ->pluck('c', 'status');

                if ($counts->isEmpty()) {
                    return 'Tidak ada data kehadiran pada '.$date->translatedFormat('F Y').'.';
                }

                $labels = ['present' => 'Hadir', 'late' => 'Terlambat', 'absent' => 'Alpha', 'leave' => 'Cuti/Izin', 'incomplete' => 'Belum lengkap'];

                return $date->translatedFormat('F Y').': '.collect($counts)
                    ->map(fn ($count, $status): string => ($labels[$status] ?? $status).' '.$count)
                    ->implode(', ');
            });
    }

    private function pengajuanSayaTool(): Tool
    {
        return (new Tool)
            ->as('pengajuan_saya')
            ->for('Status pengajuan milik karyawan yang sedang login: cuti, izin, lembur, WFH, dan klaim/reimbursement.')
            ->using(function (): string {
                $employee = $this->employee();

                if ($employee === null) {
                    return $this->noEmployeeRecord('daftar pengajuan pribadi');
                }

                $tenantId = $this->tenantId();
                $lines = [];

                foreach (LeaveRequest::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->latest('id')->take(3)->get() as $r) {
                    $lines[] = sprintf('Cuti %s s/d %s (%s hari) — %s', $this->date($r->start_date), $this->date($r->end_date), $r->total_days, $r->status);
                }

                foreach (PermissionRequest::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->latest('id')->take(3)->get() as $r) {
                    $lines[] = sprintf('Izin (%s) %s — %s', $r->type, $this->date($r->start_date), $r->status);
                }

                foreach (OvertimeRequest::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->latest('id')->take(3)->get() as $r) {
                    $lines[] = sprintf('Lembur %s (%s jam) — %s', $this->date($r->date), $r->hours, $r->status);
                }

                foreach (WfhRequest::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->latest('id')->take(3)->get() as $r) {
                    $lines[] = sprintf('WFH %s s/d %s — %s', $this->date($r->start_date), $this->date($r->end_date), $r->status);
                }

                foreach (Claim::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->latest('id')->take(3)->get() as $r) {
                    $lines[] = sprintf('Klaim "%s" %s — %s', $r->title, $this->rp($r->amount), $r->status);
                }

                return $lines === [] ? 'Belum ada pengajuan.' : implode('; ', $lines);
            });
    }

    private function profilSayaTool(): Tool
    {
        return (new Tool)
            ->as('profil_saya')
            ->for('Data profil karyawan yang sedang login: jabatan, departemen, cabang, tanggal bergabung, status, dan atasan.')
            ->using(function (): string {
                if ($this->employee() === null) {
                    return $this->noEmployeeRecord('profil karyawan');
                }

                $e = $this->employee()->loadMissing(['position:id,name', 'department:id,name', 'branch:id,name', 'manager:id,full_name']);

                return sprintf(
                    '%s (%s). Jabatan: %s, Departemen: %s, Cabang: %s. Bergabung: %s. Status kepegawaian: %s, status: %s. Atasan: %s.',
                    $e->full_name,
                    $e->employee_number,
                    $e->position?->name ?? '-',
                    $e->department?->name ?? '-',
                    $e->branch?->name ?? '-',
                    $this->date($e->join_date),
                    $e->employment_status,
                    $e->status,
                    $e->manager?->full_name ?? '-',
                );
            });
    }

    private function inboxPersetujuanTool(): Tool
    {
        return (new Tool)
            ->as('inbox_persetujuan')
            ->for('Jumlah pengajuan (cuti, izin, lembur, WFH, klaim) yang menunggu persetujuan dari user yang sedang login (untuk atasan/manajer).')
            ->using(function (): string {
                if ($this->employee() === null) {
                    return $this->noEmployeeRecord('inbox persetujuan pribadi');
                }

                $approverId = $this->employee()->id;
                $tenantId = $this->tenantId();

                $pending = [
                    'Cuti' => LeaveRequest::where('tenant_id', $tenantId)->where('status', 'pending')->where('current_approver_id', $approverId)->count(),
                    'Izin' => PermissionRequest::where('tenant_id', $tenantId)->where('status', 'pending')->where('current_approver_id', $approverId)->count(),
                    'Lembur' => OvertimeRequest::where('tenant_id', $tenantId)->where('status', 'pending')->where('current_approver_id', $approverId)->count(),
                    'WFH' => WfhRequest::where('tenant_id', $tenantId)->where('status', 'pending')->where('current_approver_id', $approverId)->count(),
                    'Klaim' => Claim::where('tenant_id', $tenantId)->where('status', 'pending')->where('current_approver_id', $approverId)->count(),
                ];

                $total = array_sum($pending);

                if ($total === 0) {
                    return 'Tidak ada pengajuan yang menunggu persetujuan Anda.';
                }

                $detail = collect($pending)->filter(fn (int $c): bool => $c > 0)
                    ->map(fn (int $c, string $label): string => "$label $c")->implode(', ');

                return "Total $total pengajuan menunggu persetujuan Anda: $detail.";
            });
    }

    /**
     * Every menu this account can actually open, flattened to
     * `[label, group, href]` and already filtered by tenant feature toggles and
     * role permissions via {@see AvanaNav::forUser()}.
     *
     * @return array<int, array{label: string, group: string, href: string}>
     */
    private function reachableMenus(): array
    {
        $menus = [];

        foreach (AvanaNav::forUser($this->user) as $group) {
            $groupTitle = $group['title'] ?? 'Umum';

            foreach ($group['items'] as $item) {
                foreach ($item['children'] ?? [$item] as $leaf) {
                    if (($leaf['href'] ?? '') === '') {
                        continue;
                    }

                    $parent = isset($item['children']) ? $item['label'].' > ' : '';

                    $menus[] = [
                        'label' => $parent.$leaf['label'],
                        'group' => $groupTitle,
                        'href' => $leaf['href'],
                    ];
                }
            }
        }

        return $menus;
    }

    private function fiturTersediaTool(): Tool
    {
        return (new Tool)
            ->as('fitur_tersedia')
            ->for('Daftar fitur/menu AvanaHR yang benar-benar bisa dibuka oleh pengguna yang sedang login, lengkap dengan grup dan alamat menunya. WAJIB dipakai untuk menjawab "fitur apa saja yang ada", "aplikasi ini bisa apa", atau "menunya di mana" — jangan menebak nama menu.')
            ->withStringParameter('kata_kunci', 'Saring menu yang namanya mengandung kata ini, mis. "cuti" atau "payroll". Kosongkan untuk semua menu.', false)
            ->using(function (?string $kata_kunci = null): string {
                $menus = collect($this->reachableMenus());

                if ($kata_kunci !== null && $kata_kunci !== '') {
                    $needle = mb_strtolower($kata_kunci);
                    $menus = $menus->filter(
                        fn (array $menu): bool => str_contains(mb_strtolower($menu['label']), $needle)
                            || str_contains(mb_strtolower($menu['group']), $needle),
                    );
                }

                if ($menus->isEmpty()) {
                    return $kata_kunci !== null && $kata_kunci !== ''
                        ? "Tidak ada menu yang cocok dengan '{$kata_kunci}' pada akun ini. Jangan mengarang nama menu — sarankan pengguna menghubungi Admin HR bila merasa seharusnya punya akses."
                        : 'Akun ini belum punya menu yang bisa dibuka.';
                }

                return $menus->groupBy('group')
                    ->map(fn ($rows, $group): string => $group.': '.$rows
                        ->map(fn (array $menu): string => $menu['label'].' ('.$menu['href'].')')
                        ->implode(', '))
                    ->implode('. ');
            });
    }

    /**
     * SOP documents the caller may be shown: everything for a user holding
     * `sop.view` (HR/admin), only the `public` ones for a plain employee.
     *
     * @return Builder<Sop>
     */
    private function visibleSops(): Builder
    {
        $query = Sop::query()
            ->where('tenant_id', $this->tenantId())
            ->active();

        if (! $this->can('sop.view')) {
            $query->publiclyVisible();
        }

        return $query;
    }

    private function daftarSopTool(): Tool
    {
        return (new Tool)
            ->as('daftar_sop')
            ->for('Daftar SOP/prosedur resmi perusahaan yang boleh dibaca pengguna ini, lengkap dengan jenis SOP, kode, versi, dan tanggal berlaku. Panggil ini lebih dulu bila pengguna bertanya "SOP apa saja yang ada".')
            ->withStringParameter('jenis', 'Filter berdasarkan nama jenis/kategori SOP. Kosongkan untuk semua.', false)
            ->using(function (?string $jenis = null): string {
                $query = $this->visibleSops()->with('category:id,name');

                if ($jenis !== null && $jenis !== '') {
                    $query->whereHas('category', fn ($q) => $q->where('name', 'like', "%{$jenis}%"));
                }

                $rows = $query->orderBy('title')->take(40)->get();

                if ($rows->isEmpty()) {
                    return 'Belum ada dokumen SOP yang tersedia untuk Anda.';
                }

                return $rows->map(fn (Sop $sop): string => sprintf(
                    '%s%s — jenis: %s, versi: %s, berlaku: %s, tipe: %s',
                    $sop->title,
                    $sop->code !== null ? " ({$sop->code})" : '',
                    $sop->category?->name ?? 'Umum',
                    $sop->version ?? '-',
                    $this->date($sop->effective_date),
                    $sop->visibility,
                ))->implode('; ');
            });
    }

    private function bacaSopTool(): Tool
    {
        return (new Tool)
            ->as('baca_sop')
            ->for('Isi dokumen SOP perusahaan berdasarkan kata kunci (judul, kode, atau isi). WAJIB dipakai untuk menjawab pertanyaan tentang prosedur/aturan internal perusahaan, agar jawaban mengutip SOP resmi dan bukan karangan.')
            ->withStringParameter('kata_kunci', 'Kata kunci topik SOP yang dicari, contoh: "pengajuan cuti", "perjalanan dinas".', true)
            ->using(function (string $kata_kunci): string {
                $rows = $this->visibleSops()
                    ->with('category:id,name')
                    ->where(fn ($q) => $q
                        ->where('title', 'like', "%{$kata_kunci}%")
                        ->orWhere('code', 'like', "%{$kata_kunci}%")
                        ->orWhere('summary', 'like', "%{$kata_kunci}%")
                        ->orWhere('content', 'like', "%{$kata_kunci}%"))
                    ->take(3)
                    ->get();

                if ($rows->isEmpty()) {
                    return "Tidak ada SOP yang cocok dengan '{$kata_kunci}'. Jangan mengarang isi SOP — sarankan pengguna menghubungi HR.";
                }

                return $rows->map(fn (Sop $sop): string => sprintf(
                    "SOP: %s%s [jenis: %s, versi: %s, berlaku: %s]\n%s",
                    $sop->title,
                    $sop->code !== null ? " ({$sop->code})" : '',
                    $sop->category?->name ?? 'Umum',
                    $sop->version ?? '-',
                    $this->date($sop->effective_date),
                    $this->sopExcerpt($sop, $kata_kunci),
                ))->implode("\n\n---\n\n");
            });
    }

    /**
     * The slice of an SOP most likely to answer the question: the text around
     * the first keyword hit, falling back to the opening of the document.
     */
    private function sopExcerpt(Sop $sop, string $keyword): string
    {
        $body = trim((string) ($sop->content ?? ''));

        if ($body === '') {
            return $sop->summary !== null && $sop->summary !== ''
                ? $sop->summary
                : '(Isi dokumen belum terbaca oleh sistem — arahkan pengguna mengunduh berkas SOP dari menu SOP.)';
        }

        $position = mb_stripos($body, $keyword);
        $start = $position === false ? 0 : max(0, $position - 500);
        $excerpt = mb_substr($body, $start, 2500);

        if ($sop->summary !== null && $sop->summary !== '') {
            $excerpt = 'Ringkasan: '.$sop->summary."\n\n".$excerpt;
        }

        return $excerpt;
    }

    private function cariKaryawanTool(): Tool
    {
        return (new Tool)
            ->as('cari_karyawan')
            ->for('Cari karyawan di perusahaan berdasarkan nama atau nomor karyawan. Hanya untuk pengguna dengan akses data karyawan.')
            ->withStringParameter('kata_kunci', 'Nama atau nomor karyawan yang dicari.', true)
            ->using(function (string $kata_kunci): string {
                $rows = Employee::where('tenant_id', $this->tenantId())
                    ->where(fn ($q) => $q->where('full_name', 'like', "%{$kata_kunci}%")->orWhere('employee_number', 'like', "%{$kata_kunci}%"))
                    ->with(['position:id,name', 'department:id,name'])
                    ->take(10)
                    ->get();

                if ($rows->isEmpty()) {
                    return "Tidak ada karyawan yang cocok dengan '{$kata_kunci}'.";
                }

                return $rows->map(fn (Employee $e): string => sprintf(
                    '%s (%s) — %s, %s [%s]',
                    $e->full_name,
                    $e->employee_number,
                    $e->position?->name ?? '-',
                    $e->department?->name ?? '-',
                    $e->status,
                ))->implode('; ');
            });
    }

    private function statistikKaryawanTool(): Tool
    {
        return (new Tool)
            ->as('statistik_karyawan')
            ->for('Statistik karyawan perusahaan: jumlah aktif, sebaran per departemen, resign bulan ini, dan yang berulang tahun bulan ini.')
            ->using(function (): string {
                $tenantId = $this->tenantId();
                $now = Carbon::now();

                $active = Employee::where('tenant_id', $tenantId)->where('status', 'active')->count();

                $deptNames = Department::where('tenant_id', $tenantId)->pluck('name', 'id');
                $byDept = Employee::where('tenant_id', $tenantId)->where('status', 'active')
                    ->selectRaw('department_id, count(*) as c')->groupBy('department_id')->get()
                    ->map(fn ($r): string => ($deptNames[$r->department_id] ?? 'Tanpa dept').' '.$r->c)->implode(', ');

                $resigned = Employee::where('tenant_id', $tenantId)
                    ->whereBetween('resign_date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])
                    ->count();

                $birthdays = Employee::where('tenant_id', $tenantId)->where('status', 'active')
                    ->whereMonth('birth_date', $now->month)->count();

                return "Karyawan aktif: {$active}. Per departemen: {$byDept}. Resign bulan ini: {$resigned}. Ulang tahun bulan ini: {$birthdays}.";
            });
    }

    private function ringkasanPayrollTool(): Tool
    {
        return (new Tool)
            ->as('ringkasan_payroll')
            ->for('Ringkasan proses payroll terbaru perusahaan: jumlah karyawan, total kotor, potongan, pajak, dan total bersih. Hanya untuk pengguna dengan akses payroll.')
            ->using(function (): string {
                $run = PayrollRun::where('tenant_id', $this->tenantId())->latest('id')->first();

                if ($run === null) {
                    return 'Belum ada proses payroll.';
                }

                $period = PayrollPeriod::forTenant($this->tenantId())->find($run->payroll_period_id);

                return sprintf(
                    'Payroll %s (status: %s) — %d karyawan. Total kotor: %s, potongan: %s, pajak: %s, total bersih: %s.',
                    $period?->name ?? '-',
                    $run->status,
                    $run->employee_count,
                    $this->rp($run->total_gross),
                    $this->rp($run->total_deduction),
                    $this->rp($run->total_tax),
                    $this->rp($run->total_net),
                );
            });
    }

    private function ringkasanRekrutmenTool(): Tool
    {
        return (new Tool)
            ->as('ringkasan_rekrutmen')
            ->for('Ringkasan rekrutmen: jumlah lowongan terbuka, pelamar per tahap, dan wawancara mendatang. Hanya untuk pengguna dengan akses rekrutmen.')
            ->using(function (): string {
                $tenantId = $this->tenantId();

                $open = JobPosting::where('tenant_id', $tenantId)->where('status', 'open')->count();

                $byStage = Applicant::where('tenant_id', $tenantId)
                    ->selectRaw('stage, count(*) as c')->groupBy('stage')->pluck('c', 'stage')
                    ->map(fn ($c, $stage): string => "$stage $c")->implode(', ');

                $upcoming = Applicant::where('tenant_id', $tenantId)
                    ->whereNotNull('interview_at')->where('interview_at', '>', Carbon::now())->count();

                return "Lowongan terbuka: {$open}. Pelamar per tahap: {$byStage}. Wawancara mendatang: {$upcoming}.";
            });
    }
}

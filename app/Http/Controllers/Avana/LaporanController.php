<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Web-admin downloadable CSV reports for the AvanaHR tenant: employees,
 * attendance, leave and payroll. Every query is tenant-scoped and streamed
 * straight to the browser so large exports never buffer in memory.
 */
class LaporanController extends Controller
{
    /**
     * Roles that may always view and export reports.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Report types that may be exported as CSV.
     *
     * @var array<int, string>
     */
    private const TYPES = ['karyawan', 'absensi', 'cuti', 'payroll'];

    /**
     * Render the reports landing screen with headline HR stats.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanViewReports($request);

        $tenantId = $request->user()->tenant_id;

        $hadirHariIni = Attendance::forTenant($tenantId)
            ->whereDate('date', Carbon::today())
            ->whereIn('status', ['present', 'late'])
            ->count();

        $latestNet = PayrollRun::forTenant($tenantId)
            ->latest('id')
            ->value('total_net');

        return Inertia::render('avana/laporan', [
            'stats' => [
                'karyawan' => Employee::forTenant($tenantId)->where('status', 'active')->count(),
                'hadir_hari_ini' => $hadirHariIni,
                'cuti_pending' => LeaveRequest::forTenant($tenantId)->where('status', 'pending')->count(),
                'payroll_net' => $this->rupiah((float) ($latestNet ?? 0)),
            ],
        ]);
    }

    /**
     * Stream a tenant-scoped CSV download for the requested report type.
     */
    public function export(Request $request, string $type): StreamedResponse
    {
        $this->ensureCanViewReports($request);

        abort_unless(in_array($type, self::TYPES, true), 404);

        $tenantId = $request->user()->tenant_id;

        [$header, $query, $mapper] = match ($type) {
            'karyawan' => $this->karyawanReport($tenantId),
            'absensi' => $this->absensiReport($tenantId),
            'cuti' => $this->cutiReport($tenantId),
            'payroll' => $this->payrollReport($tenantId),
        };

        $filename = 'laporan-'.$type.'-'.Carbon::today()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($header, $query, $mapper): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);

            $query->chunk(500, function ($rows) use ($out, $mapper): void {
                foreach ($rows as $row) {
                    fputcsv($out, $mapper($row));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Build the employee directory report.
     *
     * @return array{0: array<int, string>, 1: Builder, 2: callable(Employee): array<int, string|int|null>}
     */
    private function karyawanReport(int $tenantId): array
    {
        $header = ['Nomor', 'Nama', 'Email', 'Departemen', 'Jabatan', 'Cabang', 'Status Kerja', 'Status', 'Tgl Masuk'];

        $query = Employee::query()
            ->forTenant($tenantId)
            ->with(['department:id,name', 'position:id,name', 'branch:id,name'])
            ->orderBy('employee_number');

        $mapper = fn (Employee $employee): array => [
            $employee->employee_number,
            $employee->full_name,
            $employee->email,
            $employee->department?->name,
            $employee->position?->name,
            $employee->branch?->name,
            $this->employmentLabel($employee->employment_status),
            $this->activeLabel($employee->status),
            $employee->join_date?->format('Y-m-d'),
        ];

        return [$header, $query, $mapper];
    }

    /**
     * Build the attendance report across every tenant row.
     *
     * @return array{0: array<int, string>, 1: Builder, 2: callable(Attendance): array<int, string|int|null>}
     */
    private function absensiReport(int $tenantId): array
    {
        $header = ['Tanggal', 'Nama', 'Shift', 'Masuk', 'Keluar', 'Telat (menit)', 'Status'];

        $query = Attendance::query()
            ->forTenant($tenantId)
            ->with(['employee:id,full_name', 'shift:id,name'])
            ->orderByDesc('date')
            ->orderBy('id');

        $mapper = fn (Attendance $attendance): array => [
            $attendance->date?->format('Y-m-d'),
            $attendance->employee?->full_name,
            $attendance->shift?->name,
            $attendance->clock_in_at?->format('H:i'),
            $attendance->clock_out_at?->format('H:i'),
            (int) $attendance->late_minutes,
            $this->attendanceLabel($attendance->status),
        ];

        return [$header, $query, $mapper];
    }

    /**
     * Build the leave request report.
     *
     * @return array{0: array<int, string>, 1: Builder, 2: callable(LeaveRequest): array<int, string|int|null>}
     */
    private function cutiReport(int $tenantId): array
    {
        $header = ['Nama', 'Jenis', 'Mulai', 'Selesai', 'Total Hari', 'Status'];

        $query = LeaveRequest::query()
            ->forTenant($tenantId)
            ->with(['employee:id,full_name', 'leaveType:id,name'])
            ->orderByDesc('start_date')
            ->orderBy('id');

        $mapper = fn (LeaveRequest $leave): array => [
            $leave->employee?->full_name,
            $leave->leaveType?->name,
            $leave->start_date?->format('Y-m-d'),
            $leave->end_date?->format('Y-m-d'),
            (int) $leave->total_days,
            $this->leaveLabel($leave->status),
        ];

        return [$header, $query, $mapper];
    }

    /**
     * Build the payroll report from per-employee payroll run items.
     *
     * @return array{0: array<int, string>, 1: Builder, 2: callable(PayrollRunItem): array<int, string|int|null>}
     */
    private function payrollReport(int $tenantId): array
    {
        $header = ['Periode', 'Nama', 'Gross', 'Potongan', 'Net'];

        $query = PayrollRunItem::query()
            ->forTenant($tenantId)
            ->with(['period:id,name', 'employee:id,full_name'])
            ->orderBy('id');

        $mapper = fn (PayrollRunItem $item): array => [
            $item->period?->name,
            $item->employee?->full_name,
            (int) $item->gross_salary,
            (int) $item->total_deduction,
            (int) $item->net_salary,
        ];

        return [$header, $query, $mapper];
    }

    /**
     * Translate an employment status code into its Indonesian label.
     */
    private function employmentLabel(?string $status): string
    {
        return match ($status) {
            'permanent' => 'Tetap',
            'contract' => 'Kontrak',
            'probation' => 'Probation',
            'resigned' => 'Resign',
            default => (string) $status,
        };
    }

    /**
     * Translate an active/inactive status into its Indonesian label.
     */
    private function activeLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'Aktif',
            'inactive' => 'Nonaktif',
            default => (string) $status,
        };
    }

    /**
     * Translate an attendance status code into its Indonesian label.
     */
    private function attendanceLabel(?string $status): string
    {
        return match ($status) {
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'leave' => 'Cuti',
            'absent' => 'Alpa',
            'incomplete' => 'Belum Lengkap',
            'need_correction' => 'Perlu Koreksi',
            default => (string) $status,
        };
    }

    /**
     * Translate a leave request status code into its Indonesian label.
     */
    private function leaveLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => (string) $status,
        };
    }

    /**
     * Format an amount as an Indonesian Rupiah string (e.g. "Rp 4.820.000.000").
     */
    private function rupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * Abort with 403 unless the user is a privileged role or holds report.view.
     */
    private function ensureCanViewReports(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasViewPermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains('report.view');

        abort_unless($isPrivileged || $hasViewPermission, 403);
    }
}

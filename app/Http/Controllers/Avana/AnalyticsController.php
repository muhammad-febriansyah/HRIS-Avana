<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only HR analytics dashboard. Aggregates workforce metrics from existing
 * tenant data (no new tables) into simple chart-ready series and KPI cards.
 * Every query is scoped by tenant_id.
 */
class AnalyticsController extends Controller
{
    /**
     * Roles that may always view analytics within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Statuses that count as "present" for attendance summaries.
     *
     * @var array<int, string>
     */
    private const PRESENT_STATUSES = ['present', 'late'];

    /**
     * Render the analytics dashboard with tenant-scoped workforce metrics.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = (int) ($request->user()->tenant_id ?? 0);
        $today = Carbon::today();

        $totalHeadcount = Employee::forTenant($tenantId)->count();
        $activeHeadcount = Employee::forTenant($tenantId)->where('status', 'active')->count();
        $inactiveHeadcount = $totalHeadcount - $activeHeadcount;

        $newHiresThisPeriod = Employee::forTenant($tenantId)
            ->whereYear('join_date', $today->year)
            ->whereMonth('join_date', $today->month)
            ->count();

        $payroll = $this->payrollCost($tenantId);

        return Inertia::render('avana/analytics/index', [
            'period' => $today->copy()->locale('id')->translatedFormat('F Y'),
            'kpis' => [
                [
                    'label' => 'Total Karyawan',
                    'value' => $this->formatNumber($totalHeadcount),
                    'icon' => 'users',
                    'color' => '#2F54C9',
                ],
                [
                    'label' => 'Karyawan Aktif',
                    'value' => $this->formatNumber($activeHeadcount),
                    'icon' => 'user-check',
                    'color' => '#16A34A',
                ],
                [
                    'label' => 'Karyawan Baru Bulan Ini',
                    'value' => $this->formatNumber($newHiresThisPeriod),
                    'icon' => 'user-plus',
                    'color' => '#6E9BE6',
                ],
                [
                    'label' => 'Biaya Payroll Terakhir',
                    'value' => $payroll !== null ? $payroll['net'] : '—',
                    'icon' => 'wallet',
                    'color' => '#D97706',
                ],
            ],
            'activeStatus' => [
                ['label' => 'Aktif', 'value' => $activeHeadcount],
                ['label' => 'Nonaktif', 'value' => $inactiveHeadcount],
            ],
            'byDepartment' => $this->byDepartment($tenantId),
            'byEmploymentStatus' => $this->byEmploymentStatus($tenantId),
            'byGender' => $this->byGender($tenantId),
            'attendance' => $this->attendanceSummary($tenantId, $today),
            'payroll' => $payroll,
        ]);
    }

    /**
     * Headcount per department, including a bucket for employees with none.
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function byDepartment(int $tenantId): array
    {
        $counts = Employee::forTenant($tenantId)
            ->whereNotNull('department_id')
            ->selectRaw('department_id, COUNT(*) as aggregate')
            ->groupBy('department_id')
            ->pluck('aggregate', 'department_id');

        $series = Department::forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department): array => [
                'label' => (string) $department->name,
                'value' => (int) ($counts[$department->id] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['value'] > 0)
            ->values()
            ->all();

        $noDepartment = Employee::forTenant($tenantId)->whereNull('department_id')->count();

        if ($noDepartment > 0) {
            $series[] = ['label' => 'Tanpa Departemen', 'value' => $noDepartment];
        }

        return $series;
    }

    /**
     * Headcount per employment status (permanent/contract/probation/resigned).
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function byEmploymentStatus(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->selectRaw('employment_status, COUNT(*) as aggregate')
            ->groupBy('employment_status')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn (Employee $row): array => [
                'label' => $this->employmentLabel($row->employment_status),
                'value' => (int) $row->aggregate,
            ])
            ->all();
    }

    /**
     * Headcount per gender.
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function byGender(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->selectRaw('gender, COUNT(*) as aggregate')
            ->groupBy('gender')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn (Employee $row): array => [
                'label' => $this->genderLabel($row->gender),
                'value' => (int) $row->aggregate,
            ])
            ->all();
    }

    /**
     * Attendance status breakdown for the current month, or null when the
     * attendances table is absent.
     *
     * @return array<int, array{label: string, value: int}>|null
     */
    private function attendanceSummary(int $tenantId, Carbon $today): ?array
    {
        if (! Schema::hasTable('attendances')) {
            return null;
        }

        $rows = Attendance::forTenant($tenantId)
            ->whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn (Attendance $row): array => [
                'label' => $this->attendanceLabel($row->status),
                'value' => (int) $row->aggregate,
            ])
            ->all();

        return $rows;
    }

    /**
     * Latest payroll-run cost summary, or null when no run exists.
     *
     * @return array{period: string, gross: string, deduction: string, tax: string, net: string, employee_count: int}|null
     */
    private function payrollCost(int $tenantId): ?array
    {
        $run = PayrollRun::forTenant($tenantId)
            ->with('period:id,name')
            ->latest('id')
            ->first();

        if ($run === null) {
            return null;
        }

        return [
            'period' => (string) ($run->period?->name ?? '—'),
            'gross' => $this->rupiah((float) $run->total_gross),
            'deduction' => $this->rupiah((float) $run->total_deduction),
            'tax' => $this->rupiah((float) $run->total_tax),
            'net' => $this->rupiah((float) $run->total_net),
            'employee_count' => (int) $run->employee_count,
        ];
    }

    /**
     * Format an integer with Indonesian thousands separators.
     */
    private function formatNumber(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    /**
     * Format an amount as an Indonesian Rupiah string.
     */
    private function rupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
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
            default => $status !== null && $status !== '' ? ucfirst($status) : 'Lainnya',
        };
    }

    /**
     * Translate a gender code into its Indonesian label.
     */
    private function genderLabel(?string $gender): string
    {
        return match ($gender) {
            'male' => 'Laki-laki',
            'female' => 'Perempuan',
            default => 'Tidak Ditentukan',
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
            default => $status !== null && $status !== '' ? ucfirst($status) : 'Lainnya',
        };
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

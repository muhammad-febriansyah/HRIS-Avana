<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\OffboardingCase;
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
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'dynamic_report';

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
        $this->ensureCan($request, 'view');

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
        $demographics = $this->demographics($tenantId, $today);
        $attrition = $this->attrition($tenantId, $today, $activeHeadcount);

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
                    'label' => 'Attrition (1 Thn)',
                    'value' => $this->formatNumber($attrition['count']),
                    'icon' => 'user-minus',
                    'color' => '#DC2626',
                ],
                [
                    'label' => 'Attrition Rate',
                    'value' => $attrition['rate'].'%',
                    'icon' => 'trending-down',
                    'color' => '#EA580C',
                ],
                [
                    'label' => 'Rata-rata Usia',
                    'value' => $demographics['avg_age'] > 0 ? $demographics['avg_age'].' th' : '—',
                    'icon' => 'cake',
                    'color' => '#7C3AED',
                ],
                [
                    'label' => 'Rata-rata Masa Kerja',
                    'value' => $demographics['avg_experience'] > 0 ? $demographics['avg_experience'].' th' : '—',
                    'icon' => 'briefcase',
                    'color' => '#0891B2',
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
            'byAgeGroup' => $demographics['byAgeGroup'],
            'bySalarySlab' => $this->bySalarySlab($tenantId, $today),
            'attrition' => $attrition,
            'attendance' => $this->attendanceSummary($tenantId, $today),
            'payroll' => $payroll,
        ]);
    }

    /**
     * Average age, average tenure (years of service), and age-group buckets for
     * active employees, computed in PHP for cross-driver portability.
     *
     * @return array{avg_age: float, avg_experience: float, byAgeGroup: array<int, array{label: string, value: int}>}
     */
    private function demographics(int $tenantId, Carbon $today): array
    {
        $rows = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->get(['birth_date', 'join_date']);

        $ages = [];
        $tenures = [];
        $buckets = ['18-25' => 0, '26-35' => 0, '36-45' => 0, '46-55' => 0, '55+' => 0];

        foreach ($rows as $row) {
            if ($row->birth_date !== null) {
                $age = $row->birth_date->diffInYears($today);
                $ages[] = $age;

                $bucket = match (true) {
                    $age <= 25 => '18-25',
                    $age <= 35 => '26-35',
                    $age <= 45 => '36-45',
                    $age <= 55 => '46-55',
                    default => '55+',
                };
                $buckets[$bucket]++;
            }

            if ($row->join_date !== null) {
                $tenures[] = $row->join_date->diffInYears($today);
            }
        }

        $byAgeGroup = [];
        foreach ($buckets as $label => $value) {
            $byAgeGroup[] = ['label' => $label, 'value' => $value];
        }

        return [
            'avg_age' => $ages === [] ? 0.0 : round(array_sum($ages) / count($ages), 1),
            'avg_experience' => $tenures === [] ? 0.0 : round(array_sum($tenures) / count($tenures), 1),
            'byAgeGroup' => $byAgeGroup,
        ];
    }

    /**
     * Attrition over the trailing 12 months: leaver count, rate, and a
     * month-by-month trend from completed offboarding cases.
     *
     * @return array{count: int, rate: float, trend: array<int, array{label: string, value: int}>}
     */
    private function attrition(int $tenantId, Carbon $today, int $activeHeadcount): array
    {
        $windowStart = $today->copy()->subMonthsNoOverflow(11)->startOfMonth();

        // Exit dates come from formal offboarding cases and, as a fallback, the
        // employee's own resign_date — so a resignation recorded either way counts.
        $exitDates = OffboardingCase::forTenant($tenantId)
            ->whereNotNull('last_day')
            ->where('last_day', '>=', $windowStart)
            ->pluck('last_day');

        $resignDates = Employee::forTenant($tenantId)
            ->whereNotNull('resign_date')
            ->where('resign_date', '>=', $windowStart)
            ->pluck('resign_date');

        $leavers = $exitDates->concat($resignDates);

        $count = $leavers->count();

        // Annualised rate against the average of start and current headcount.
        $avgHeadcount = ($activeHeadcount + $count) > 0 ? ($activeHeadcount + $count) : 1;
        $rate = round(($count / $avgHeadcount) * 100, 1);

        $monthly = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = $today->copy()->subMonthsNoOverflow($i);
            $monthly[$month->format('Y-m')] = [
                'label' => $month->locale('id')->translatedFormat('M y'),
                'value' => 0,
            ];
        }

        foreach ($leavers as $leaver) {
            $key = $leaver->format('Y-m');
            if (isset($monthly[$key])) {
                $monthly[$key]['value']++;
            }
        }

        return [
            'count' => $count,
            'rate' => $rate,
            'trend' => array_values($monthly),
        ];
    }

    /**
     * Active-employee headcount bucketed by monthly salary band (sum of active
     * salary components per employee).
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function bySalarySlab(int $tenantId, Carbon $today): array
    {
        $today = $today->toDateString();

        $perEmployee = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('effective_start_date', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_end_date')->orWhere('effective_end_date', '>=', $today);
            })
            ->selectRaw('employee_id, SUM(amount) as total')
            ->groupBy('employee_id')
            ->pluck('total');

        $buckets = [
            '< 5 Jt' => 0,
            '5–10 Jt' => 0,
            '10–20 Jt' => 0,
            '20–50 Jt' => 0,
            '> 50 Jt' => 0,
        ];

        foreach ($perEmployee as $total) {
            $total = (float) $total;
            $bucket = match (true) {
                $total < 5_000_000 => '< 5 Jt',
                $total < 10_000_000 => '5–10 Jt',
                $total < 20_000_000 => '10–20 Jt',
                $total < 50_000_000 => '20–50 Jt',
                default => '> 50 Jt',
            };
            $buckets[$bucket]++;
        }

        $series = [];
        foreach ($buckets as $label => $value) {
            if ($value > 0) {
                $series[] = ['label' => $label, 'value' => $value];
            }
        }

        return $series;
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
    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }
}

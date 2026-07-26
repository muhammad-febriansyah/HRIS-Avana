<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\Permission;
use App\Models\PermissionRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Statuses that count as "present" for attendance rate calculations.
     *
     * @var array<int, string>
     */
    private const PRESENT_STATUSES = ['present', 'late'];

    /**
     * Indonesian short month labels indexed 1-12.
     *
     * @var array<int, string>
     */
    private const SHORT_MONTHS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /**
     * Indonesian labels for the payroll period status enum.
     *
     * @var array<string, string>
     */
    private const PERIOD_STATUS_LABELS = [
        'draft' => 'Draft',
        'processing' => 'Diproses',
        'locked' => 'Terkunci',
        'final' => 'Final',
        'paid' => 'Dibayar',
    ];

    /**
     * Brand avatar palette mirrored from the AvanaHR prototype.
     *
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = ['#2F54C9', '#6E9BE6', '#0E1A3A', '#D97706', '#16A34A'];

    /**
     * Render the AvanaHR dashboard with real, tenant-scoped figures.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('roles');

        // Super admins operate across every tenant, so they get a platform
        // (SaaS) dashboard instead of the tenant-scoped HR one.
        if ($user->roles->pluck('code')->contains('super_admin')) {
            return $this->superAdminDashboard($request);
        }

        // A plain karyawan carries only the `own` permission module, so the
        // HR-wide figures below are neither visible nor useful to them: give
        // them their own self-service home instead.
        if ($this->isSelfServiceOnly($user)) {
            return $this->employeeDashboard($request);
        }

        $tenantId = $request->user()->tenant_id ?? 0;
        $today = Carbon::today();

        $activeEmployees = Employee::forTenant($tenantId)->where('status', 'active')->count();
        $presentToday = Attendance::forTenant($tenantId)
            ->whereDate('date', $today)
            ->whereIn('status', self::PRESENT_STATUSES)
            ->count();
        $pendingLeave = LeaveRequest::forTenant($tenantId)->where('status', 'pending')->count();
        $attendanceRate = $activeEmployees > 0
            ? round($presentToday / $activeEmployees * 100, 1)
            : 0.0;
        $newHiresThisMonth = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->whereYear('join_date', $today->year)
            ->whereMonth('join_date', $today->month)
            ->count();

        return Inertia::render('dashboard', [
            'kpis' => $this->kpis($tenantId, $activeEmployees, $presentToday, $attendanceRate, $pendingLeave, $newHiresThisMonth),
            'activities' => $this->activities($tenantId),
            'approvals' => $this->approvals($tenantId),
            'birthdays' => $this->birthdaysToday($tenantId, $today),
            'headcount' => $this->headcount($activeEmployees, $today),
            'attendanceWeek' => $this->attendanceWeek($tenantId, $activeEmployees, $attendanceRate, $today),
            'userName' => explode(' ', trim((string) $request->user()->name))[0],
            'today' => $today->copy()->locale('id')->translatedFormat('l, d M Y'),
        ]);
    }

    /**
     * Whether the user only holds self-service (`own`) permissions — a plain
     * karyawan, as opposed to HR, a manager, or finance.
     */
    private function isSelfServiceOnly(User $user): bool
    {
        if ($user->employee === null) {
            return false;
        }

        $modules = Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $user->roles->pluck('id')))
            ->distinct()
            ->pluck('module');

        return $modules->isNotEmpty() && $modules->diff(['own'])->isEmpty();
    }

    /**
     * Render the employee's own home screen: today's attendance, this month's
     * hours, remaining leave, pending requests, and the week around them.
     */
    private function employeeDashboard(Request $request): Response
    {
        $employee = $request->user()->employee;
        $tenantId = (int) $employee->tenant_id;
        $today = Carbon::today();

        $todayRecord = Attendance::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        $monthMinutes = (int) Attendance::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->sum('work_minutes');

        $leaveAvailable = (float) LeaveBalance::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->where('year', $today->year)
            ->sum('remaining');

        $pending = 0;
        foreach ([LeaveRequest::class, OvertimeRequest::class, PermissionRequest::class] as $model) {
            $pending += $model::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->where('status', 'pending')
                ->count();
        }

        return Inertia::render('avana/saya/dashboard', [
            'userName' => explode(' ', trim((string) $request->user()->name))[0],
            'today' => $today->copy()->locale('id')->translatedFormat('l, d M Y'),
            'todayAttendance' => [
                'clock_in' => $todayRecord?->clock_in_at?->format('H:i'),
                'clock_out' => $todayRecord?->clock_out_at?->format('H:i'),
                'status' => $todayRecord?->status,
            ],
            'stats' => [
                'work_hours_month' => round($monthMinutes / 60, 1),
                'leave_available' => $leaveAvailable,
                'pending_requests' => $pending,
            ],
            'attendanceWeek' => $this->ownAttendanceWeek($tenantId, (int) $employee->id, $today),
            'awayToday' => $this->awayToday($tenantId, $employee->department_id, $today),
        ]);
    }

    /**
     * Hours worked on each of the last seven days, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ownAttendanceWeek(int $tenantId, int $employeeId, Carbon $today): array
    {
        $start = $today->copy()->subDays(6);

        $byDate = Attendance::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$start, $today])
            ->get(['date', 'work_minutes', 'status'])
            ->keyBy(fn (Attendance $record): string => $record->date->toDateString());

        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $start->copy()->addDays($offset);
            $record = $byDate->get($date->toDateString());

            $days[] = [
                'date' => $date->toDateString(),
                'label' => $date->copy()->locale('id')->translatedFormat('D'),
                'hours' => round(((int) ($record->work_minutes ?? 0)) / 60, 1),
                'status' => $record->status ?? null,
            ];
        }

        return $days;
    }

    /**
     * Colleagues in the employee's own department on approved leave today.
     *
     * @return array<int, array<string, mixed>>
     */
    private function awayToday(int $tenantId, ?int $departmentId, Carbon $today): array
    {
        return LeaveRequest::forTenant($tenantId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->when($departmentId, fn ($query, $id) => $query->whereHas(
                'employee',
                fn ($employeeQuery) => $employeeQuery->where('department_id', $id),
            ))
            ->with(['employee:id,full_name', 'leaveType:id,name'])
            ->limit(10)
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'id' => $leave->id,
                'name' => $leave->employee?->full_name,
                'leave_type' => $leave->leaveType?->name,
                'end_date' => $leave->end_date?->toDateString(),
            ])->values()->all();
    }

    /**
     * Render the platform (SaaS) dashboard for super admins: figures that span
     * every tenant — clients, recurring revenue, subscriptions, and invoicing.
     */
    private function superAdminDashboard(Request $request): Response
    {
        $today = Carbon::today();

        $totalTenants = Tenant::query()->count();
        $activeTenants = Tenant::query()->where('status', 'active')->count();
        $newTenantsThisMonth = Tenant::query()
            ->whereYear('created_at', $today->year)
            ->whereMonth('created_at', $today->month)
            ->count();
        $activeSubscriptions = Subscription::query()->where('status', 'active')->count();
        $mrr = $this->monthlyRecurringRevenue();

        $outstanding = (float) Invoice::query()
            ->whereIn('status', ['unpaid', 'overdue'])
            ->sum('total');
        $overdueCount = Invoice::query()
            ->where(function ($query): void {
                $query->where('status', 'overdue')
                    ->orWhere(fn ($sub) => $sub->where('status', 'unpaid')->whereDate('due_date', '<', Carbon::today()));
            })
            ->count();

        return Inertia::render('dashboard-admin', [
            'kpis' => [
                [
                    'label' => 'Total Klien',
                    'value' => $this->formatNumber($activeTenants),
                    'icon' => 'building-2',
                    'iconBg' => 'rgba(47,84,201,.1)',
                    'iconColor' => '#2F54C9',
                    'delta' => $newTenantsThisMonth > 0 ? '+'.$newTenantsThisMonth.' bln ini' : '',
                    'deltaIcon' => 'trending-up',
                    'deltaColor' => '#16A34A',
                ],
                [
                    'label' => 'MRR',
                    'value' => $this->formatRupiahCompact($mrr),
                    'icon' => 'wallet',
                    'iconBg' => 'rgba(22,163,74,.1)',
                    'iconColor' => '#16A34A',
                    'delta' => $activeSubscriptions.' langganan',
                    'deltaIcon' => 'circle-dot',
                    'deltaColor' => '#6B7280',
                ],
                [
                    'label' => 'Langganan Aktif',
                    'value' => $this->formatNumber($activeSubscriptions),
                    'icon' => 'refresh-cw',
                    'iconBg' => 'rgba(110,155,230,.16)',
                    'iconColor' => '#2F54C9',
                    'delta' => $totalTenants.' klien',
                    'deltaIcon' => 'users',
                    'deltaColor' => '#6B7280',
                ],
                [
                    'label' => 'Invoice Outstanding',
                    'value' => $this->formatRupiahCompact($outstanding),
                    'icon' => 'file-warning',
                    'iconBg' => 'rgba(217,119,6,.1)',
                    'iconColor' => '#D97706',
                    'delta' => $overdueCount.' jatuh tempo',
                    'deltaIcon' => 'arrow-up-right',
                    'deltaColor' => '#D97706',
                ],
            ],
            'tenantGrowth' => $this->tenantGrowth($today),
            'revenueTrend' => $this->revenueTrend($today),
            'recentTenants' => $this->recentTenants(),
            'overdueInvoices' => $this->overdueInvoices($today),
            'quotaAlerts' => $this->quotaAlerts(),
            'userName' => explode(' ', trim((string) $request->user()->name))[0],
            'today' => $today->copy()->locale('id')->translatedFormat('l, d M Y'),
        ]);
    }

    /**
     * Sum active subscriptions normalised to a monthly figure (yearly / 12).
     */
    private function monthlyRecurringRevenue(): float
    {
        return (float) Subscription::query()
            ->where('status', 'active')
            ->get(['price', 'billing_cycle'])
            ->sum(fn (Subscription $subscription): float => $subscription->billing_cycle === 'yearly'
                ? (float) $subscription->price / 12
                : (float) $subscription->price);
    }

    /**
     * Cumulative tenant count at the end of each of the last 6 months.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function tenantGrowth(Carbon $today): array
    {
        $labels = [];
        $values = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $monthEnd = $today->copy()->subMonths($offset)->endOfMonth();
            $labels[] = self::SHORT_MONTHS[$monthEnd->month];
            $values[] = Tenant::query()->where('created_at', '<=', $monthEnd)->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Paid-invoice revenue (in millions of rupiah) for each of the last 6 months.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function revenueTrend(Carbon $today): array
    {
        $labels = [];
        $values = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $today->copy()->subMonths($offset);
            $labels[] = self::SHORT_MONTHS[$month->month];

            $total = (float) Invoice::query()
                ->where('status', 'paid')
                ->whereNotNull('paid_at')
                ->whereYear('paid_at', $month->year)
                ->whereMonth('paid_at', $month->month)
                ->sum('total');

            $values[] = (int) round($total / 1_000_000);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * The 5 newest tenants for the activity list.
     *
     * @return array<int, array{id: int, ini: string, avBg: string, name: string, type: string}>
     */
    private function recentTenants(): array
    {
        return Tenant::query()
            ->with('package:id,name')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'ini' => $this->initials($tenant->name),
                'avBg' => $this->avatarColor($tenant->name),
                'name' => $tenant->name,
                'type' => ($tenant->package->name ?? 'Tanpa paket').' · '.ucfirst((string) $tenant->status),
            ])
            ->values()
            ->all();
    }

    /**
     * Up to 5 unpaid/overdue invoices, most overdue first.
     *
     * @return array<int, array{id: int, number: string, tenant: string, amount: string, due: string}>
     */
    private function overdueInvoices(Carbon $today): array
    {
        return Invoice::query()
            ->whereIn('status', ['unpaid', 'overdue'])
            ->with('tenant:id,name')
            ->orderBy('due_date')
            ->take(5)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => (string) $invoice->invoice_number,
                'tenant' => $invoice->tenant->name ?? 'Klien',
                'amount' => $this->formatRupiahCompact((float) $invoice->total),
                'due' => $invoice->due_date
                    ? $invoice->due_date->locale('id')->translatedFormat('d M Y')
                    : '—',
            ])
            ->values()
            ->all();
    }

    /**
     * Tenants whose employee count has reached 80%+ of their allotted quota.
     *
     * @return array<int, array{id: int, name: string, usage: string, used: int, max: int, pct: int}>
     */
    private function quotaAlerts(): array
    {
        return Tenant::query()
            ->whereNotNull('max_employees')
            ->where('max_employees', '>', 0)
            ->withCount('employees')
            ->get()
            ->filter(fn (Tenant $tenant): bool => $tenant->employees_count >= (int) $tenant->max_employees * 0.8)
            ->sortByDesc(fn (Tenant $tenant): float => $tenant->employees_count / (int) $tenant->max_employees)
            ->take(5)
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'usage' => $tenant->employees_count.' / '.(int) $tenant->max_employees,
                'used' => (int) $tenant->employees_count,
                'max' => (int) $tenant->max_employees,
                'pct' => (int) round($tenant->employees_count / (int) $tenant->max_employees * 100),
            ])
            ->values()
            ->all();
    }

    /**
     * Build the four KPI cards in the exact shape the prototype consumes.
     *
     * @return array<int, array<string, string>>
     */
    private function kpis(
        int|string $tenantId,
        int $activeEmployees,
        int $presentToday,
        float $attendanceRate,
        int $pendingLeave,
        int $newHiresThisMonth,
    ): array {
        $period = PayrollPeriod::forTenant($tenantId)->orderByDesc('start_date')->first();
        $payrollNet = (float) ($period?->runs()->latest()->first()?->total_net ?? 0);
        $payrollLabel = $period !== null
            ? 'Payroll '.explode(' ', (string) $period->name)[0]
            : 'Payroll';
        $payrollDelta = $period !== null
            ? (self::PERIOD_STATUS_LABELS[$period->status] ?? ucfirst((string) $period->status))
            : '—';

        return [
            [
                'label' => 'Total Karyawan',
                'value' => $this->formatNumber($activeEmployees),
                'icon' => 'users',
                'iconBg' => 'rgba(47,84,201,.1)',
                'iconColor' => '#2F54C9',
                'delta' => $newHiresThisMonth > 0 ? '+'.$newHiresThisMonth : '',
                'deltaIcon' => 'trending-up',
                'deltaColor' => '#16A34A',
            ],
            [
                'label' => 'Hadir Hari Ini',
                'value' => $this->formatNumber($presentToday),
                'icon' => 'user-check',
                'iconBg' => 'rgba(22,163,74,.1)',
                'iconColor' => '#16A34A',
                'delta' => $this->formatPercent($attendanceRate),
                'deltaIcon' => 'trending-up',
                'deltaColor' => '#16A34A',
            ],
            [
                'label' => 'Pending Approval',
                'value' => $this->formatNumber($pendingLeave),
                'icon' => 'clock',
                'iconBg' => 'rgba(217,119,6,.1)',
                'iconColor' => '#D97706',
                'delta' => $pendingLeave.' baru',
                'deltaIcon' => 'arrow-up-right',
                'deltaColor' => '#D97706',
            ],
            [
                'label' => $payrollLabel,
                'value' => $this->formatRupiahCompact($payrollNet),
                'icon' => 'wallet',
                'iconBg' => 'rgba(110,155,230,.16)',
                'iconColor' => '#2F54C9',
                'delta' => $payrollDelta,
                'deltaIcon' => 'circle-dot',
                'deltaColor' => '#6B7280',
            ],
        ];
    }

    /**
     * Compose the latest activity feed (new hires + approved leave), newest first.
     *
     * @return array<int, array<string, string>>
     */
    private function activities(int|string $tenantId): array
    {
        $newEmployees = Employee::forTenant($tenantId)
            ->with('position:id,name')
            ->latest()
            ->take(3)
            ->get()
            ->map(fn (Employee $employee): array => [
                'icon' => 'user-plus',
                'bg' => 'rgba(47,84,201,.1)',
                'color' => '#2F54C9',
                'text' => $employee->full_name.' ditambahkan sebagai '.($employee->position->name ?? 'Karyawan'),
                'time' => $employee->created_at?->diffForHumans() ?? '',
                'sortAt' => $employee->created_at?->timestamp ?? 0,
            ]);

        $approvedLeave = LeaveRequest::forTenant($tenantId)
            ->where('status', 'approved')
            ->with(['employee:id,full_name', 'leaveType:id,name'])
            ->latest('updated_at')
            ->take(2)
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'icon' => 'check-check',
                'bg' => 'rgba(22,163,74,.1)',
                'color' => '#16A34A',
                'text' => ($leave->leaveType->name ?? 'Cuti').' '.($leave->employee->full_name ?? 'Karyawan').' disetujui',
                'time' => $leave->updated_at?->diffForHumans() ?? '',
                'sortAt' => $leave->updated_at?->timestamp ?? 0,
            ]);

        return $newEmployees
            ->merge($approvedLeave)
            ->sortByDesc('sortAt')
            ->take(5)
            ->map(fn (array $item): array => [
                'icon' => $item['icon'],
                'bg' => $item['bg'],
                'color' => $item['color'],
                'text' => $item['text'],
                'time' => $item['time'],
            ])
            ->values()
            ->all();
    }

    /**
     * Build the pending-approval list (up to 4 newest pending leave requests).
     *
     * @return array<int, array<string, int|string>>
     */
    private function approvals(int|string $tenantId): array
    {
        return LeaveRequest::forTenant($tenantId)
            ->where('status', 'pending')
            ->with(['employee:id,full_name', 'leaveType:id,name'])
            ->latest()
            ->take(4)
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'id' => $leave->id,
                'ini' => $this->initials($leave->employee->full_name ?? ''),
                'avBg' => $this->avatarColor($leave->employee->full_name ?? ''),
                'name' => $leave->employee->full_name ?? 'Karyawan',
                'type' => ($leave->leaveType->name ?? 'Cuti').' · '.(int) $leave->total_days.' hari',
            ])
            ->values()
            ->all();
    }

    /**
     * List active employees whose birthday falls on today, tenant-scoped.
     *
     * @return array<int, array{id: int, ini: string, avBg: string, name: string, role: string, age: int|null}>
     */
    private function birthdaysToday(int|string $tenantId, Carbon $today): array
    {
        return Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $today->month)
            ->whereDay('birth_date', $today->day)
            ->with(['position:id,name', 'department:id,name'])
            ->orderBy('full_name')
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'ini' => $this->initials($employee->full_name),
                'avBg' => $this->avatarColor($employee->full_name),
                'name' => $employee->full_name,
                'role' => $employee->position->name ?? ($employee->department->name ?? 'Karyawan'),
                'age' => $employee->birth_date?->age,
            ])
            ->values()
            ->all();
    }

    /**
     * Build the 6-point headcount trend ending at the current active count.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function headcount(int $activeEmployees, Carbon $today): array
    {
        $labels = [];
        $values = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $today->copy()->subMonths($offset);
            $labels[] = self::SHORT_MONTHS[$month->month];
            $values[] = max(0, $activeEmployees - $offset);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Build the present-rate per weekday (Mon-Fri) of the current week.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function attendanceWeek(int|string $tenantId, int $activeEmployees, float $todayRate, Carbon $today): array
    {
        // The 5 most recent dates that actually have attendance records, so the
        // chart reflects real attendance instead of a flat fallback.
        $dates = Attendance::forTenant($tenantId)
            ->whereDate('date', '<=', $today->toDateString())
            ->select('date')
            ->distinct()
            ->orderByDesc('date')
            ->limit(5)
            ->pluck('date')
            ->map(fn ($date): Carbon => $date instanceof Carbon ? $date : Carbon::parse($date))
            ->sort()
            ->values();

        $labels = [];
        $values = [];

        foreach ($dates as $date) {
            $labels[] = $date->format('d/m');

            $presentForDay = Attendance::forTenant($tenantId)
                ->whereDate('date', $date)
                ->whereIn('status', self::PRESENT_STATUSES)
                ->count();

            $values[] = $activeEmployees > 0
                ? (int) round($presentForDay / $activeEmployees * 100)
                : 0;
        }

        // Pad to a stable 5 points when fewer dates have data yet.
        while (count($labels) < 5) {
            array_unshift($labels, '–');
            array_unshift($values, 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Format an integer with Indonesian thousands separators (e.g. 1248 -> "1.248").
     */
    private function formatNumber(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    /**
     * Format a percentage with a single Indonesian decimal (e.g. 92.6 -> "92,6%").
     */
    private function formatPercent(float $value): string
    {
        return number_format($value, 1, ',', '.').'%';
    }

    /**
     * Format a rupiah figure, compacting large values (e.g. 4.82e9 -> "Rp 4,82 M").
     */
    private function formatRupiahCompact(float $amount): string
    {
        if ($amount >= 1_000_000_000) {
            return 'Rp '.number_format($amount / 1_000_000_000, 2, ',', '.').' M';
        }

        if ($amount >= 1_000_000) {
            return 'Rp '.number_format($amount / 1_000_000, 1, ',', '.').' Jt';
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * Build up to two uppercase initials from a full name.
     */
    private function initials(string $fullName): string
    {
        $words = preg_split('/\s+/', trim($fullName)) ?: [];

        $initials = (new Collection($words))
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    /**
     * Pick a deterministic avatar color derived from the employee name.
     */
    private function avatarColor(string $fullName): string
    {
        $index = crc32($fullName) % count(self::AVATAR_PALETTE);

        return self::AVATAR_PALETTE[$index];
    }
}

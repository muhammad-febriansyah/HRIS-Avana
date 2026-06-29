<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
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
            'headcount' => $this->headcount($activeEmployees, $today),
            'attendanceWeek' => $this->attendanceWeek($tenantId, $activeEmployees, $attendanceRate, $today),
            'userName' => explode(' ', trim((string) $request->user()->name))[0],
            'today' => $today->copy()->locale('id')->translatedFormat('l, d M Y'),
        ]);
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

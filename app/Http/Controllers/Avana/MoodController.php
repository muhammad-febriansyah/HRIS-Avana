<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\MoodCheckin;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HR view of the anonymous daily mood check-ins ("Bagaimana perasaanmu hari
 * ini?") submitted from the mobile app. Tenant-scoped; HR/admin only.
 */
class MoodController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const VIEWER_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Mood keys → numeric score (1..5) for the index.
     *
     * @var array<string, int>
     */
    private const SCORES = [
        'sangat_baik' => 5,
        'baik' => 4,
        'biasa' => 3,
        'kurang' => 2,
        'buruk' => 1,
    ];

    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'sangat_baik' => 'Sangat Baik',
        'baik' => 'Baik',
        'biasa' => 'Biasa',
        'kurang' => 'Kurang',
        'buruk' => 'Buruk',
    ];

    public function index(Request $request): Response
    {
        $this->ensureCanView($request);

        $tenantId = $request->user()->tenant_id;

        $date = $this->resolveDate($request->query('date'));

        $rows = MoodCheckin::forTenant($tenantId)
            ->whereDate('date', $date->toDateString())
            ->with('employee:id,full_name,employee_number')
            ->get();

        $breakdown = [];
        foreach (self::SCORES as $key => $score) {
            $breakdown[] = [
                'key' => $key,
                'label' => self::LABELS[$key],
                'score' => $score,
                'count' => $rows->where('mood', $key)->count(),
            ];
        }

        $total = $rows->count();
        $index = $total > 0
            ? (int) round($rows->sum(fn (MoodCheckin $m): int => self::SCORES[$m->mood] ?? 0) / $total * 20)
            : null;

        $employees = $rows
            ->sortByDesc(fn (MoodCheckin $m): int => self::SCORES[$m->mood] ?? 0)
            ->map(fn (MoodCheckin $m): array => [
                'id' => $m->id,
                'name' => $m->employee?->full_name ?? '—',
                'employee_number' => $m->employee?->employee_number,
                'mood' => $m->mood,
                'mood_label' => self::LABELS[$m->mood] ?? $m->mood,
                'score' => self::SCORES[$m->mood] ?? 0,
                'time' => $m->created_at?->format('H:i'),
            ])
            ->values()
            ->all();

        return Inertia::render('avana/mood/index', [
            'date' => $date->toDateString(),
            'summary' => [
                'index' => $index,
                'total' => $total,
                'breakdown' => $breakdown,
            ],
            'employees' => $employees,
            'trend' => $this->trend($tenantId, $date),
        ]);
    }

    /**
     * Average mood index per day over the 7 days ending on [$date].
     *
     * @return array<int, array<string, mixed>>
     */
    private function trend(int|string $tenantId, CarbonInterface $date): array
    {
        $start = $date->copy()->subDays(6)->startOfDay();

        $rows = MoodCheckin::forTenant($tenantId)
            ->whereBetween('date', [$start->toDateString(), $date->toDateString()])
            ->get(['mood', 'date']);

        $out = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $dayRows = $rows->filter(fn (MoodCheckin $m): bool => $m->date instanceof CarbonInterface
                ? $m->date->isSameDay($day)
                : Carbon::parse((string) $m->date)->isSameDay($day));
            $count = $dayRows->count();
            $out[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('d/m'),
                'index' => $count > 0
                    ? (int) round($dayRows->sum(fn (MoodCheckin $m): int => self::SCORES[$m->mood] ?? 0) / $count * 20)
                    : null,
                'count' => $count,
            ];
        }

        return $out;
    }

    private function resolveDate(mixed $value): CarbonInterface
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            try {
                return Carbon::parse($value)->startOfDay();
            } catch (\Throwable) {
                // fall through
            }
        }

        return now()->startOfDay();
    }

    private function ensureCanView(Request $request): void
    {
        $user = $request->user();
        $user->loadMissing('roles');

        abort_unless(
            $user->roles->whereIn('code', self::VIEWER_ROLES)->isNotEmpty(),
            403,
        );
    }
}

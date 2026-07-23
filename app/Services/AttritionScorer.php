<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttritionSetting;
use App\Models\Employee;
use App\Models\EmployeeCareerHistory;
use App\Models\EmployeeSalaryComponent;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PerformanceReview;
use App\Models\SurveyResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resign-risk (attrition) scoring engine — a transparent rule-based model.
 *
 * Each of the nine factors returns whether it could be evaluated (data present)
 * and whether it is triggered. The final 0-100 score is the triggered points as
 * a share of the points that were actually evaluable, so a factor without data
 * neither inflates nor deflates the result (graceful degradation).
 *
 * @phpstan-type FactorResult array{key: string, label: string, weight: int, available: bool, triggered: bool, points: int, detail: string}
 */
final class AttritionScorer
{
    /**
     * The factor keys and their human labels, in display order.
     *
     * @var array<string, string>
     */
    public const FACTOR_LABELS = [
        'tenure' => 'Masa kerja < 1 tahun',
        'no_raise' => 'Tidak naik gaji > 2 tahun',
        'lateness' => 'Sering terlambat',
        'overtime' => 'Lembur berlebih',
        'performance' => 'Kinerja menurun',
        'engagement' => 'Engagement rendah',
        'leave_spike' => 'Pengajuan cuti meningkat',
        'manager_change' => 'Pergantian atasan',
        'no_promotion' => 'Tidak dipromosikan > 3 tahun',
    ];

    /** @var array<string, mixed> */
    private array $rules;

    public function __construct()
    {
        $this->rules = config('attrition.rules');
    }

    /**
     * Score one employee. Weights and risk bands come from the tenant's
     * {@see AttritionSetting}; pass one in to avoid re-resolving it per row.
     *
     * @return array{employee_id: int, score: int, category: string, coverage: int, factors: list<FactorResult>, top_factors: list<string>}
     */
    public function score(Employee $employee, ?AttritionSetting $settings = null): array
    {
        $settings ??= AttritionSetting::resolve($employee->tenant_id);
        $weights = $settings->weights;
        $now = Carbon::today();

        /** @var array<string, callable():array{0: bool, 1: bool, 2: string}> $defs */
        $defs = [
            'tenure' => fn (): array => $this->tenure($employee, $now),
            'no_raise' => fn (): array => $this->noRaise($employee, $now),
            'lateness' => fn (): array => $this->lateness($employee, $now),
            'overtime' => fn (): array => $this->overtime($employee, $now),
            'performance' => fn (): array => $this->performance($employee),
            'engagement' => fn (): array => $this->engagement($employee),
            'leave_spike' => fn (): array => $this->leaveSpike($employee, $now),
            'manager_change' => fn (): array => $this->managerChange($employee, $now),
            'no_promotion' => fn (): array => $this->noPromotion($employee, $now),
        ];

        $factors = [];
        $gotPoints = 0;
        $maxEvaluable = 0;

        foreach ($defs as $key => $evaluate) {
            [$available, $triggered, $detail] = $evaluate();
            $weight = (int) ($weights[$key] ?? 0);
            $points = $triggered ? $weight : 0;

            if ($available) {
                $maxEvaluable += $weight;
                $gotPoints += $points;
            }

            $factors[] = [
                'key' => $key,
                'label' => self::FACTOR_LABELS[$key] ?? $key,
                'weight' => $weight,
                'available' => $available,
                'triggered' => $triggered,
                'points' => $points,
                'detail' => $detail,
            ];
        }

        $score = $maxEvaluable > 0 ? (int) round($gotPoints / $maxEvaluable * 100) : 0;

        $totalWeight = array_sum(array_map('intval', $weights));
        $coverage = $totalWeight > 0 ? (int) round($maxEvaluable / $totalWeight * 100) : 0;

        $topFactors = collect($factors)
            ->filter(fn (array $f): bool => $f['triggered'])
            ->sortByDesc('points')
            ->pluck('label')
            ->take(3)
            ->values()
            ->all();

        return [
            'employee_id' => (int) $employee->id,
            'score' => $score,
            'category' => $this->band($score, $settings),
            'coverage' => $coverage, // % of the model's weight backed by data
            'factors' => $factors,
            'top_factors' => $topFactors,
        ];
    }

    /**
     * Map a 0-100 score onto a risk band using the tenant's cut-offs.
     */
    public function band(int $score, AttritionSetting $settings): string
    {
        if ($score <= $settings->band_low) {
            return 'low';
        }

        if ($score <= $settings->band_medium) {
            return 'medium';
        }

        return 'high';
    }

    /**
     * @return array{0: bool, 1: bool, 2: string}
     */
    private function tenure(Employee $employee, Carbon $now): array
    {
        if ($employee->join_date === null) {
            return [false, false, 'Tanggal masuk tidak diketahui'];
        }

        $months = Carbon::parse($employee->join_date)->diffInMonths($now);
        $triggered = $months < $this->rules['tenure_months'];

        return [true, $triggered, "Masa kerja {$months} bulan"];
    }

    /**
     * @return array{0: bool, 1: bool, 2: string}
     */
    private function noRaise(Employee $employee, Carbon $now): array
    {
        $last = EmployeeSalaryComponent::where('employee_id', $employee->id)
            ->whereNotNull('effective_start_date')
            ->max('effective_start_date');

        if ($last === null) {
            return [false, false, 'Riwayat gaji tidak tersedia'];
        }

        $months = Carbon::parse($last)->diffInMonths($now);
        $triggered = $months >= $this->rules['no_raise_months'];

        return [true, $triggered, $triggered
            ? "Belum naik gaji {$months} bulan"
            : "Gaji terakhir naik {$months} bulan lalu"];
    }

    /**
     * @return array{0: bool, 1: bool, 2: string}
     */
    private function lateness(Employee $employee, Carbon $now): array
    {
        $hasData = Attendance::where('employee_id', $employee->id)
            ->where('date', '>=', $now->copy()->subDays(90)->toDateString())
            ->exists();

        if (! $hasData) {
            return [false, false, 'Belum ada data absensi'];
        }

        $lateCount = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$now->copy()->subDays(30)->toDateString(), $now->toDateString()])
            ->where('late_minutes', '>', 0)
            ->count();

        $triggered = $lateCount > $this->rules['lateness_per_month'];

        return [true, $triggered, "{$lateCount}x terlambat (30 hari)"];
    }

    /**
     * @return array{0: bool, 1: bool, 2: string}
     */
    private function overtime(Employee $employee, Carbon $now): array
    {
        $months = (int) $this->rules['overtime_months'];
        $threshold = (float) $this->rules['overtime_hours_month'];
        $everyMonthOver = true;
        $total = 0.0;

        for ($i = 0; $i < $months; $i++) {
            $hours = (float) OvertimeRequest::where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereBetween('date', [
                    $now->copy()->subMonthsNoOverflow($i + 1)->toDateString(),
                    $now->copy()->subMonthsNoOverflow($i)->toDateString(),
                ])
                ->sum('hours');

            $total += $hours;

            if ($hours <= $threshold) {
                $everyMonthOver = false;
            }
        }

        $avg = round($total / max($months, 1), 1);

        return [true, $everyMonthOver, "Rata-rata {$avg} jam/bulan (3 bln)"];
    }

    /**
     * @return array{0: bool, 1: bool, 2: string}
     */
    private function performance(Employee $employee): array
    {
        $reviews = PerformanceReview::where('employee_id', $employee->id)
            ->whereNotNull('final_score')
            ->with('cycle:id,period_end')
            ->get()
            ->sortBy(fn (PerformanceReview $r) => optional($r->cycle)->period_end)
            ->values();

        if ($reviews->count() < 2) {
            return [false, false, 'Butuh minimal 2 penilaian'];
        }

        $latest = (float) $reviews[$reviews->count() - 1]->final_score;
        $previous = (float) $reviews[$reviews->count() - 2]->final_score;
        $triggered = $latest < $previous;

        return [true, $triggered, 'Skor '.$this->trimNumber($previous).' → '.$this->trimNumber($latest)];
    }

    /**
     * @return array{0: bool, 1: bool, 2: string}
     */
    private function engagement(Employee $employee): array
    {
        $ratings = SurveyResponse::where('employee_id', $employee->id)
            ->whereHas('question', fn ($q) => $q->where('type', 'rating'))
            ->pluck('answer')
            ->map(fn ($a): ?float => is_numeric($a) ? (float) $a : null)
            ->filter(fn (?float $v): bool => $v !== null);

        if ($ratings->isEmpty()) {
            return [false, false, 'Belum ada survey engagement'];
        }

        $avg = round((float) $ratings->avg(), 1);
        $triggered = $avg <= $this->rules['engagement_low'];

        return [true, $triggered, "Skor engagement {$this->trimNumber($avg)}/5"];
    }

    /**
     * @return array{0: bool, 1: bool, 2: string}
     */
    private function leaveSpike(Employee $employee, Carbon $now): array
    {
        if (LeaveRequest::where('employee_id', $employee->id)->doesntExist()) {
            return [false, false, 'Belum ada riwayat cuti'];
        }

        $recent = LeaveRequest::where('employee_id', $employee->id)
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->count();

        // Prior three months (day 31..120), averaged to a monthly rate.
        $priorMonthlyAvg = LeaveRequest::where('employee_id', $employee->id)
            ->whereBetween('created_at', [
                $now->copy()->subDays(120),
                $now->copy()->subDays(30),
            ])
            ->count() / 3.0;

        $triggered = $recent >= 2 && (
            $priorMonthlyAvg <= 0
                ? $recent >= 2
                : $recent >= $priorMonthlyAvg * $this->rules['leave_spike_factor']
        );

        return [true, $triggered, "{$recent} pengajuan (30 hari terakhir)"];
    }

    /**
     * @return array{0: bool, 1: bool, 2: string}
     */
    private function managerChange(Employee $employee, Carbon $now): array
    {
        $changes = DB::table('audit_logs')
            ->where('auditable_type', Employee::class)
            ->where('auditable_id', $employee->id)
            ->where('action', 'updated')
            ->orderByDesc('created_at')
            ->get(['new_values', 'created_at']);

        $managerChange = $changes->first(function (object $row): bool {
            $values = json_decode((string) $row->new_values, true);

            return is_array($values) && array_key_exists('manager_id', $values);
        });

        if ($managerChange === null) {
            return [false, false, 'Tidak ada riwayat pergantian atasan'];
        }

        $triggered = Carbon::parse($managerChange->created_at)
            ->gte($now->copy()->subMonthsNoOverflow($this->rules['manager_change_months']));

        return [true, $triggered, $triggered ? 'Ganti atasan < 6 bulan' : 'Atasan stabil'];
    }

    /**
     * @return array{0: bool, 1: bool, 2: string}
     */
    private function noPromotion(Employee $employee, Carbon $now): array
    {
        if ($employee->join_date === null) {
            return [false, false, 'Tanggal masuk tidak diketahui'];
        }

        $lastPromotion = EmployeeCareerHistory::where('employee_id', $employee->id)
            ->where('movement_type', 'promotion')
            ->max('effective_date');

        $reference = $lastPromotion !== null
            ? Carbon::parse($lastPromotion)
            : Carbon::parse($employee->join_date);

        $years = round($reference->diffInMonths($now) / 12, 1);
        $triggered = $reference->lte($now->copy()->subYears($this->rules['no_promotion_years']));

        return [true, $triggered, $lastPromotion !== null
            ? "Promosi terakhir {$this->trimNumber($years)} tahun lalu"
            : "Belum pernah dipromosikan ({$this->trimNumber($years)} tahun)"];
    }

    /**
     * Format a float without trailing ".0" noise.
     */
    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.');
    }
}

<?php

namespace App\Console\Commands;

use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Operational path out of quarantine for pre-workflow performance reviews.
 *
 * Reviews marked `completed` before calibration became mandatory were flagged
 * `is_legacy` so nothing downstream would pay out or report a rating nobody
 * signed. This command is how those rows leave that state — deliberately
 * without inventing a calibration record: it can only send them *back* into the
 * workflow so a real person scores and calibrates them, or leave them listed as
 * unusable history.
 */
class RemediatePerformanceLegacy extends Command
{
    protected $signature = 'avana:remediate-performance-legacy
        {--tenant= : Limit to one tenant id}
        {--reopen : Send the listed reviews back to manager review instead of only listing them}';

    protected $description = 'List (or reopen) performance reviews quarantined as legacy so they can be re-scored properly';

    public function handle(): int
    {
        $reviews = PerformanceReview::query()
            ->where('is_legacy', true)
            ->when($this->option('tenant') !== null, fn ($query) => $query->where('tenant_id', (int) $this->option('tenant')))
            ->with(['employee:id,full_name', 'cycle:id,name,status'])
            ->orderBy('tenant_id')
            ->orderBy('cycle_id')
            ->get();

        if ($reviews->isEmpty()) {
            $this->info('No quarantined reviews.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Tenant', 'Employee', 'Cycle', 'Cycle status', 'Recorded score'],
            $reviews->map(fn (PerformanceReview $review): array => [
                $review->id,
                $review->tenant_id,
                $review->employee?->full_name ?? '—',
                $review->cycle?->name ?? '—',
                $review->cycle?->status ?? '—',
                $review->final_score ?? '—',
            ])->all(),
        );

        $this->line('');
        $this->warn($reviews->count().' review(s) are excluded from incentives, attrition, Report Studio, and HAV.');

        if (! $this->option('reopen')) {
            $this->line('Re-run with --reopen to send them back to manager review for real scoring.');

            return self::SUCCESS;
        }

        // Reopening is only meaningful where the cycle can be worked in again;
        // a closed cycle would accept the status change and then block every
        // subsequent step.
        $blocked = $reviews->filter(fn (PerformanceReview $review): bool => $review->cycle?->status !== 'active');

        if ($blocked->isNotEmpty()) {
            $this->warn($blocked->count().' review(s) sit in a cycle that is not active and will be skipped:');

            foreach ($blocked->pluck('cycle')->filter()->unique('id') as $cycle) {
                /** @var PerformanceCycle $cycle */
                $this->line("  - cycle #{$cycle->id} \"{$cycle->name}\" is {$cycle->status}");
            }

            $this->line('Reopen those cycles first (Kinerja → Siklus → Aktifkan) if their scores are still needed.');
        }

        $reopenable = $reviews->reject(fn (PerformanceReview $review): bool => $review->cycle?->status !== 'active');

        if ($reopenable->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->confirm("Send {$reopenable->count()} review(s) back to manager review? Their recorded score is kept as a note, not as a rating.")) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($reopenable): void {
            foreach ($reopenable as $review) {
                $recorded = $review->final_score;

                $review->update([
                    'status' => 'manager_review',
                    'is_legacy' => false,
                    'final_score' => null,
                    'calibrated_score' => null,
                    'calibrated_by' => null,
                    'calibrated_at' => null,
                    'manager_score' => null,
                    'manager_scored_by' => null,
                    'manager_scored_at' => null,
                    'notes' => trim(($review->notes ?? '')."\n[Remediasi data lama] Skor tercatat sebelumnya: {$recorded}. Nilai ini belum pernah dikalibrasi dan harus dinilai ulang."),
                ]);
            }
        });

        $this->info($reopenable->count().' review(s) returned to manager review.');

        return self::SUCCESS;
    }
}

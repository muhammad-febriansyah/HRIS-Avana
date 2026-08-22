<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\KeyResult;
use App\Models\KpiIndicator;
use App\Models\PerformanceKpiItem;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\PerformanceKpiScorer;
use App\Services\PerformanceReviewWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * KPI items assigned to a performance review — either a manual indicator with
 * a target/actual, or one sourced live from an employee's Key Result. Every
 * mutation recomputes the parent review's `manager_score`
 * ({@see PerformanceKpiScorer}) and is blocked once the review is completed.
 */
class PerformanceKpiItemController extends Controller
{
    private const MODULE = 'performance';

    /**
     * @var array<int, string>
     */
    private const SOURCES = ['manual', 'key_result'];

    public function store(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $review);
        $this->ensureReviewOpen($review);

        $tenantId = $request->user()->tenant_id;
        $data = $this->validateItem($request, $tenantId, $review);

        // The weight budget is a read-then-write invariant: without the row
        // lock, two concurrent 60% items both see 0% assigned and both pass.
        DB::transaction(function () use ($review, $data, $tenantId): void {
            $locked = $this->lockOpenReview($review);

            $this->ensureWeightBudget($locked, $data['weight']);
            $this->ensureNotDuplicated($locked, $data);

            $item = PerformanceKpiItem::create([
                ...$data,
                'tenant_id' => $tenantId,
                'review_id' => $locked->id,
            ]);

            // The first item commits the review to KPI scoring; from here the
            // scorer owns manager_score and the calibration gate demands a
            // complete scorecard.
            if ($locked->scoring_mode !== 'kpi') {
                $locked->update(['scoring_mode' => 'kpi']);
            }

            $scorer = new PerformanceKpiScorer;
            $scorer->refreshItem($item);
            $scorer->recomputeManagerScore($locked);
        });

        return back()->with('success', 'Item KPI ditambahkan');
    }

    public function update(Request $request, PerformanceKpiItem $item): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $item);
        $review = $item->review;
        $this->ensureReviewOpen($review);

        $tenantId = $request->user()->tenant_id;

        if ($item->source === 'key_result') {
            $data = $request->validate([
                'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);
        } else {
            $data = $this->validateManualFields($request, $tenantId);
        }

        DB::transaction(function () use ($review, $item, $data): void {
            $locked = $this->lockOpenReview($review);

            $this->ensureWeightBudget($locked, $data['weight'], $item->id);
            $this->ensureNotDuplicated($locked, [...$data, 'source' => $item->source], $item->id);

            $item->update($data);

            $scorer = new PerformanceKpiScorer;
            $scorer->refreshItem($item);
            $scorer->recomputeManagerScore($locked);
        });

        return back()->with('success', 'Item KPI diperbarui');
    }

    public function destroy(Request $request, PerformanceKpiItem $item): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $item);
        $review = $item->review;
        $this->ensureReviewOpen($review);

        DB::transaction(function () use ($review, $item): void {
            $locked = $this->lockOpenReview($review);

            $item->delete();

            (new PerformanceKpiScorer)->recomputeManagerScore($locked);
        });

        return back()->with('success', 'Item KPI dihapus');
    }

    /**
     * Stages at which the scorecard may still be edited. Once the manager has
     * submitted, the review is in calibration and its KPI items are the
     * evidence being calibrated — changing them there would move the score out
     * from under the calibrator. Corrections go through `reopen` instead.
     *
     * @var array<int, string>
     */
    private const EDITABLE_STAGES = ['pending', 'self_review', 'manager_review'];

    /**
     * KPI items may only be edited before the manager submits, and only while
     * the cycle is open — a closed cycle freezes the whole scorecard, not just
     * the reviews that happen to have reached `completed`.
     */
    private function ensureReviewOpen(PerformanceReview $review): void
    {
        (new PerformanceReviewWorkflow)->assertCycleOpen($review);

        abort_unless(
            in_array($review->status, self::EDITABLE_STAGES, true),
            423,
            'Item KPI terkunci setelah penilaian atasan dikirim. Buka kembali penilaian untuk mengubahnya.'
        );
    }

    /**
     * Re-read the review under a row lock and re-check the stage gate inside
     * the transaction.
     *
     * Checking the status before opening the transaction is not enough: a
     * calibration request committing in between would finalize the review while
     * this request still believes it is editable, and the scorecard would change
     * after the rating was signed.
     */
    private function lockOpenReview(PerformanceReview $review): PerformanceReview
    {
        /** @var PerformanceReview $locked */
        $locked = PerformanceReview::query()->with('cycle')->lockForUpdate()->findOrFail($review->getKey());

        $this->ensureReviewOpen($locked);

        return $locked;
    }

    /**
     * Reject a second item pointing at the same indicator or Key Result, which
     * would double-count the same measurement under two weights.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureNotDuplicated(PerformanceReview $review, array $data, ?int $ignoreItemId = null): void
    {
        $column = $data['source'] === 'key_result' ? 'key_result_id' : 'kpi_indicator_id';
        $value = $data[$column] ?? null;

        if ($value === null) {
            return;
        }

        $exists = $review->kpiItems()
            ->where($column, $value)
            ->when($ignoreItemId !== null, fn ($query) => $query->where('id', '!=', $ignoreItemId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                $column => 'Indikator atau Key Result ini sudah ada pada penilaian tersebut.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request, int $tenantId, PerformanceReview $review): array
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(self::SOURCES)],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($data['source'] === 'key_result') {
            $keyResultData = $request->validate([
                'key_result_id' => [
                    'required',
                    'integer',
                    Rule::exists('key_results', 'id')->where('tenant_id', $tenantId),
                ],
            ]);

            /** @var KeyResult $keyResult */
            $keyResult = KeyResult::forTenant($tenantId)->with('objective')->findOrFail($keyResultData['key_result_id']);

            abort_if(
                $keyResult->objective?->employee_id !== null && (int) $keyResult->objective->employee_id !== (int) $review->employee_id,
                422,
                'Key Result ini bukan milik karyawan yang dinilai.'
            );

            // An Objective must state which cycle it belongs to, and it must be
            // this one. A cycle-less Objective could otherwise be scored into
            // every review of every period, measuring the same work repeatedly.
            abort_if(
                $keyResult->objective?->cycle_id === null,
                422,
                'Objective dari Key Result ini belum ditautkan ke siklus penilaian.'
            );

            abort_if(
                (int) $keyResult->objective->cycle_id !== (int) $review->cycle_id,
                422,
                'Key Result ini berada pada siklus yang berbeda dengan penilaian.'
            );

            return [
                'source' => 'key_result',
                'weight' => $data['weight'],
                'key_result_id' => $keyResult->id,
                'kpi_indicator_id' => null,
                'label' => $keyResult->title,
                'unit' => $keyResult->unit,
                'direction' => 'higher_better',
                'target_value' => null,
                'actual_value' => null,
            ];
        }

        return [...$this->validateManualFields($request, $tenantId), 'source' => 'manual', 'weight' => $data['weight']];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateManualFields(Request $request, int $tenantId): array
    {
        $data = $request->validate([
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'kpi_indicator_id' => [
                'required',
                'integer',
                Rule::exists('kpi_indicators', 'id')->where('tenant_id', $tenantId),
            ],
            // A non-positive target makes achievement undefined (division by
            // zero, or a "negative goal" the direction rules can't score).
            'target_value' => ['required', 'numeric', 'gt:0'],
            'actual_value' => ['nullable', 'numeric', 'min:0'],
        ], [
            'target_value.gt' => 'Target harus lebih besar dari 0.',
            'actual_value.min' => 'Realisasi tidak boleh negatif.',
        ]);

        $indicator = KpiIndicator::forTenant($tenantId)->findOrFail($data['kpi_indicator_id']);

        return [
            'weight' => $data['weight'],
            'kpi_indicator_id' => $indicator->id,
            'key_result_id' => null,
            'label' => $indicator->name,
            'unit' => $indicator->unit,
            'direction' => $indicator->direction,
            'target_value' => $data['target_value'],
            'actual_value' => $data['actual_value'] ?? null,
        ];
    }

    /**
     * Abort with a validation error if adding/editing this item's weight
     * would push the review's total KPI weight over 100%.
     */
    private function ensureWeightBudget(PerformanceReview $review, float $weight, ?int $ignoreItemId = null): void
    {
        $existing = (float) $review->kpiItems()
            ->when($ignoreItemId !== null, fn ($query) => $query->where('id', '!=', $ignoreItemId))
            ->sum('weight');

        if ($existing + $weight > 100.0001) {
            throw ValidationException::withMessages([
                'weight' => 'Total bobot item KPI tidak boleh melebihi 100%. Sisa bobot: '.round(100 - $existing, 2).'%.',
            ]);
        }
    }

    private function ensureTenantOwnership(Request $request, PerformanceReview|PerformanceKpiItem $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

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

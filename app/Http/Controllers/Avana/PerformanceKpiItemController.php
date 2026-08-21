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
        (new PerformanceReviewWorkflow)->assertMutable($review);

        $tenantId = $request->user()->tenant_id;
        $data = $this->validateItem($request, $tenantId, $review);

        $this->ensureWeightBudget($review, $data['weight']);

        $item = PerformanceKpiItem::create([
            ...$data,
            'tenant_id' => $tenantId,
            'review_id' => $review->id,
        ]);

        $scorer = new PerformanceKpiScorer;
        $scorer->refreshItem($item);
        $scorer->recomputeManagerScore($review);

        return back()->with('success', 'Item KPI ditambahkan');
    }

    public function update(Request $request, PerformanceKpiItem $item): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $item);
        $review = $item->review;
        (new PerformanceReviewWorkflow)->assertMutable($review);

        $tenantId = $request->user()->tenant_id;

        if ($item->source === 'key_result') {
            $data = $request->validate([
                'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);
        } else {
            $data = $this->validateManualFields($request, $tenantId);
        }

        $this->ensureWeightBudget($review, $data['weight'], $item->id);

        $item->update($data);

        $scorer = new PerformanceKpiScorer;
        $scorer->refreshItem($item);
        $scorer->recomputeManagerScore($review);

        return back()->with('success', 'Item KPI diperbarui');
    }

    public function destroy(Request $request, PerformanceKpiItem $item): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $item);
        $review = $item->review;
        (new PerformanceReviewWorkflow)->assertMutable($review);

        $item->delete();

        (new PerformanceKpiScorer)->recomputeManagerScore($review);

        return back()->with('success', 'Item KPI dihapus');
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

            return [
                'source' => 'key_result',
                'weight' => $data['weight'],
                'key_result_id' => $keyResult->id,
                'kpi_indicator_id' => null,
                'label' => $keyResult->title,
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
            'target_value' => ['required', 'numeric'],
            'actual_value' => ['nullable', 'numeric'],
        ]);

        $indicator = KpiIndicator::forTenant($tenantId)->findOrFail($data['kpi_indicator_id']);

        return [
            'weight' => $data['weight'],
            'kpi_indicator_id' => $indicator->id,
            'key_result_id' => null,
            'label' => $indicator->name,
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

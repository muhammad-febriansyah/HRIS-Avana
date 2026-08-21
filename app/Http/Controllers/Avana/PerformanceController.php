<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceFeedback;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\PerformanceReviewWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    /**
     * Permission module key for action-level RBAC.
     */
    private const MODULE = 'performance';

    /**
     * Allowed performance review status values, in display order.
     *
     * @var array<int, string>
     */
    private const REVIEW_STATUSES = ['pending', 'self_review', 'manager_review', 'calibration', 'completed'];

    /**
     * Allowed performance cycle status values, in display order.
     *
     * @var array<int, string>
     */
    private const CYCLE_STATUSES = ['draft', 'active', 'closed'];

    /**
     * Allowed 360 feedback type values, in display order.
     *
     * @var array<int, string>
     */
    private const FEEDBACK_TYPES = ['self', 'peer', 'manager', 'subordinate'];

    /**
     * Display performance reviews together with the cycle list and KPIs.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = $request->user()->tenant_id;

        $reviews = PerformanceReview::forTenant($tenantId)
            ->with(['employee:id,full_name,employee_number', 'cycle:id,name', 'reviewer:id,full_name'])
            ->latest('id')
            ->get()
            ->map(fn (PerformanceReview $review): array => $this->transformReview($review));

        $cycles = PerformanceCycle::forTenant($tenantId)
            ->withCount('reviews')
            ->latest('id')
            ->get()
            ->map(fn (PerformanceCycle $cycle): array => $this->transformCycle($cycle));

        return Inertia::render('avana/kinerja/index', [
            'reviews' => $reviews,
            'cycles' => $cycles,
            'employees' => $this->employeeOptions($tenantId),
            'cycleOptions' => $this->cycleOptions($tenantId),
            'statuses' => $this->reviewStatusOptions(),
            'cycleStatuses' => $this->cycleStatusOptions(),
            'kpis' => [
                'total_reviews' => $reviews->count(),
                'completed' => $reviews->where('status', 'completed')->count(),
                'in_progress' => $reviews->whereNotIn('status', ['completed'])->count(),
                'active_cycles' => collect($cycles)->where('status', 'active')->count(),
            ],
        ]);
    }

    /**
     * Human Asset Value (HAV) report: each rated employee's value index,
     * derived from their latest review score weighted by tenure.
     */
    public function hav(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = $request->user()->tenant_id;

        $latestPerEmployee = PerformanceReview::forTenant($tenantId)
            ->with([
                'employee:id,full_name,join_date,department_id,position_id',
                'employee.department:id,name',
                'employee.position:id,name',
            ])
            ->get()
            ->filter(fn (PerformanceReview $review): bool => $review->employee !== null)
            ->groupBy('employee_id')
            ->map(fn ($group) => $group->sortByDesc('id')->first());

        $rows = $latestPerEmployee
            ->map(function (PerformanceReview $review): array {
                $employee = $review->employee;
                $score = (float) ($review->final_score ?? $review->manager_score ?? $review->self_score ?? 0);
                $years = $employee->join_date !== null ? (float) $employee->join_date->floatDiffInYears(now()) : 0.0;
                $havIndex = round($score * (1 + min($years, 5) * 0.05), 1);

                return [
                    'employee_id' => $employee->id,
                    'employee' => $employee->full_name,
                    'department' => $employee->department?->name,
                    'position' => $employee->position?->name,
                    'score' => round($score, 1),
                    'tenure_years' => round($years, 1),
                    'hav_index' => $havIndex,
                    'category' => $this->havCategory($score, $years),
                ];
            })
            ->sortByDesc('hav_index')
            ->values();

        return Inertia::render('avana/kinerja/hav', [
            'rows' => $rows->all(),
            'kpis' => [
                'rated' => $rows->count(),
                'avg_hav' => $rows->isNotEmpty() ? round($rows->avg('hav_index'), 1) : 0,
                'stars' => $rows->where('category', 'Bintang')->count(),
                'at_risk' => $rows->where('category', 'Perlu Pengembangan')->count(),
            ],
        ]);
    }

    /**
     * Map a score + tenure into a Human Asset Value talent category.
     */
    private function havCategory(float $score, float $years): string
    {
        return match (true) {
            $score >= 85 && $years >= 2 => 'Bintang',
            $score >= 85 => 'Potensial Tinggi',
            $score >= 70 => 'Inti',
            $score >= 55 => 'Berkembang',
            default => 'Perlu Pengembangan',
        };
    }

    /**
     * Show the form for creating a new performance review.
     */
    public function create(Request $request): Response
    {
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('avana/kinerja/create', [
            'employees' => $this->employeeOptions($tenantId),
            'cycleOptions' => $this->cycleOptions($tenantId),
            'statuses' => $this->reviewStatusOptions(),
        ]);
    }

    /**
     * Show the form for editing an existing performance review.
     */
    public function edit(Request $request, PerformanceReview $review): Response
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $review);

        $tenantId = $request->user()->tenant_id;

        $review->load(['feedbacks' => fn ($query) => $query->latest('id'), 'feedbacks.reviewer:id,full_name']);

        return Inertia::render('avana/kinerja/edit', [
            'review' => [
                'id' => $review->id,
                'route_key' => $review->public_id,
                'cycle_id' => $review->cycle_id,
                'employee_id' => $review->employee_id,
                'reviewer_id' => $review->reviewer_id,
                'self_score' => $review->self_score !== null ? (float) $review->self_score : null,
                'manager_score' => $review->manager_score !== null ? (float) $review->manager_score : null,
                'final_score' => $review->final_score !== null ? (float) $review->final_score : null,
                'status' => $review->status,
                'notes' => $review->notes,
                'review_date' => $review->review_date?->toDateString(),
            ],
            'feedbacks' => $review->feedbacks->map(fn (PerformanceFeedback $feedback): array => $this->transformFeedback($feedback))->all(),
            'feedbackTypes' => $this->feedbackTypeOptions(),
            'employees' => $this->employeeOptions($tenantId),
            'cycleOptions' => $this->cycleOptions($tenantId),
            'statuses' => $this->reviewStatusOptions(),
        ]);
    }

    /**
     * Persist a new performance review under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateReview($request, $tenantId);
        $this->ensureCycleActive($tenantId, $data['cycle_id']);

        PerformanceReview::create([
            ...$data,
            'tenant_id' => $tenantId,
            'status' => 'pending',
        ]);

        return redirect()->route('avana.kinerja')
            ->with('success', 'Penilaian kinerja berhasil ditambahkan');
    }

    /**
     * Update an existing performance review's metadata (cycle, employee,
     * reviewer, notes, review date). Scores and status are workflow-controlled
     * and are not accepted here.
     */
    public function update(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $review);
        $this->workflow()->assertMutable($review);

        $data = $this->validateReview($request, $request->user()->tenant_id, $review);
        $this->ensureCycleActive($request->user()->tenant_id, $data['cycle_id']);

        $review->update($data);

        return redirect()->route('avana.kinerja')
            ->with('success', 'Penilaian kinerja berhasil diperbarui');
    }

    /**
     * Delete a performance review.
     */
    public function destroy(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $review);
        $this->workflow()->assertMutable($review);

        $review->delete();

        return back()->with('success', 'Penilaian kinerja dihapus');
    }

    /**
     * Add a performance cycle within the acting user's tenant.
     */
    public function storeCycle(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'status' => ['required', Rule::in(self::CYCLE_STATUSES)],
            'description' => ['nullable', 'string'],
        ]);

        PerformanceCycle::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.kinerja')
            ->with('success', 'Siklus penilaian berhasil ditambahkan');
    }

    /**
     * Update a performance cycle's name, dates, or description.
     */
    public function updateCycle(Request $request, PerformanceCycle $cycle): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureCycleTenantOwnership($request, $cycle);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'description' => ['nullable', 'string'],
        ]);

        $cycle->update($data);

        return back()->with('success', 'Siklus penilaian berhasil diperbarui');
    }

    /**
     * Move a cycle through draft → active → closed. Reopening a closed cycle
     * back to active requires the elevated `approve` permission, the same gate
     * used for calibration and review reopening.
     */
    public function updateCycleStatus(Request $request, PerformanceCycle $cycle): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureCycleTenantOwnership($request, $cycle);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::CYCLE_STATUSES)],
        ]);

        $allowed = [
            'draft' => ['active'],
            'active' => ['closed'],
            'closed' => ['active'],
        ];

        abort_unless(in_array($data['status'], $allowed[$cycle->status] ?? [], true), 422, 'Perubahan status siklus tidak valid.');

        if ($cycle->status === 'closed' && $data['status'] === 'active') {
            $this->ensureCan($request, 'approve');
        }

        $cycle->update(['status' => $data['status']]);

        return back()->with('success', 'Status siklus diperbarui');
    }

    /**
     * Submit the manager's score for a performance review and move it into
     * calibration. Only the review's assigned reviewer, or a holder of the
     * elevated `approve` permission, may score it.
     */
    public function submitScore(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $review);
        $this->ensureIsAssignedReviewer($request, $review);
        $this->workflow()->assertMutable($review);

        $data = $request->validate([
            'manager_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'review_date' => ['nullable', 'date'],
        ]);

        $this->workflow()->submitManagerScore($review, $data['manager_score'] ?? null, $data['review_date'] ?? null);

        return back()->with('success', 'Nilai penilaian diperbarui');
    }

    /**
     * Calibrate a review (BR-19): set the calibrated final score, record the
     * calibrator, and mark the review completed. This is the objectivity gate
     * before a rating becomes final — it only succeeds when the review is
     * currently at the `calibration` stage.
     */
    public function calibrate(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureTenantOwnership($request, $review);

        $data = $request->validate([
            'calibrated_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->workflow()->calibrate($review, (float) $data['calibrated_score'], $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Penilaian dikalibrasi & difinalisasi');
    }

    /**
     * Reopen a completed review for correction. Requires the elevated
     * `approve` permission; the reason is recorded in the review's notes.
     */
    public function reopen(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureTenantOwnership($request, $review);

        $data = $request->validate([
            'to' => ['required', Rule::in(['manager_review', 'calibration'])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->workflow()->reopen($review, $data['to'], $request->user(), $data['reason']);

        return back()->with('success', 'Penilaian dibuka kembali');
    }

    /**
     * Attach a 360 feedback entry to a performance review.
     */
    public function storeFeedback(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $review);
        $this->workflow()->assertMutable($review);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'type' => ['required', Rule::in(self::FEEDBACK_TYPES)],
            'reviewer_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string'],
        ]);

        PerformanceFeedback::create([
            'tenant_id' => $tenantId,
            'review_id' => $review->id,
            'type' => $data['type'],
            'reviewer_id' => $data['reviewer_id'] ?? null,
            'rating' => $data['rating'] ?? null,
            'comment' => $data['comment'] ?? null,
        ]);

        return back()->with('success', 'Umpan balik ditambahkan');
    }

    /**
     * Delete a 360 feedback entry.
     */
    public function destroyFeedback(Request $request, PerformanceFeedback $feedback): RedirectResponse
    {
        $this->ensureCan($request, 'archive');

        abort_if((int) $feedback->tenant_id !== (int) $request->user()->tenant_id, 404);
        $this->workflow()->assertMutable($feedback->review);

        $feedback->delete();

        return back()->with('success', 'Umpan balik dihapus');
    }

    /**
     * Validate the create/update payload for a performance review. Pass the
     * review being edited so the one-review-per-employee-per-cycle check can
     * exclude it from the uniqueness lookup.
     *
     * @return array<string, mixed>
     */
    private function validateReview(Request $request, ?int $tenantId, ?PerformanceReview $review = null): array
    {
        return $request->validate([
            'cycle_id' => [
                'required',
                'integer',
                Rule::exists('performance_cycles', 'id')->where('tenant_id', $tenantId),
            ],
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
                Rule::unique('performance_reviews', 'employee_id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('cycle_id', $request->input('cycle_id')))
                    ->ignore($review?->id),
            ],
            'reviewer_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            'notes' => ['nullable', 'string'],
            'review_date' => ['nullable', 'date'],
        ], [
            'employee_id.unique' => 'Karyawan ini sudah memiliki penilaian pada siklus tersebut.',
        ]);
    }

    /**
     * Abort with 422 unless the given cycle is currently active. Reviews may
     * only be created or edited under an open cycle.
     */
    private function ensureCycleActive(?int $tenantId, int $cycleId): void
    {
        $cycle = PerformanceCycle::forTenant($tenantId)->find($cycleId);

        abort_unless($cycle?->status === 'active', 422, 'Siklus penilaian ini tidak aktif.');
    }

    /**
     * Abort with 404 when the cycle does not belong to the user's tenant.
     */
    private function ensureCycleTenantOwnership(Request $request, PerformanceCycle $cycle): void
    {
        abort_if((int) $cycle->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the acting user is the review's assigned reviewer,
     * or holds the elevated `approve` permission (HR override).
     */
    private function ensureIsAssignedReviewer(Request $request, PerformanceReview $review): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin() || $user->hasPermissionTo(self::MODULE.'.approve')) {
            return;
        }

        abort_unless(
            $review->reviewer_id !== null && $user->employee !== null && (int) $review->reviewer_id === (int) $user->employee->id,
            403,
            'Anda bukan penilai yang ditunjuk untuk review ini.'
        );
    }

    private function workflow(): PerformanceReviewWorkflow
    {
        return new PerformanceReviewWorkflow;
    }

    /**
     * Build the row shape consumed by the reviews table.
     *
     * @return array<string, mixed>
     */
    private function transformReview(PerformanceReview $review): array
    {
        return [
            'id' => $review->id,
            'route_key' => $review->public_id,
            'cycle_id' => $review->cycle_id,
            'cycle' => $review->cycle?->name,
            'employee_id' => $review->employee_id,
            'employee' => $review->employee?->full_name,
            'employee_number' => $review->employee?->employee_number,
            'reviewer_id' => $review->reviewer_id,
            'reviewer' => $review->reviewer?->full_name,
            'self_score' => $review->self_score !== null ? (float) $review->self_score : null,
            'manager_score' => $review->manager_score !== null ? (float) $review->manager_score : null,
            'final_score' => $review->final_score !== null ? (float) $review->final_score : null,
            'status' => $review->status,
            'notes' => $review->notes,
            'review_date' => $review->review_date?->toDateString(),
        ];
    }

    /**
     * Build the row shape consumed by the cycles section.
     *
     * @return array<string, mixed>
     */
    private function transformCycle(PerformanceCycle $cycle): array
    {
        return [
            'id' => $cycle->id,
            'name' => $cycle->name,
            'period_start' => $cycle->period_start?->toDateString(),
            'period_end' => $cycle->period_end?->toDateString(),
            'status' => $cycle->status,
            'description' => $cycle->description,
            'reviews_count' => $cycle->reviews_count,
        ];
    }

    /**
     * Build the row shape consumed by the 360 feedback list.
     *
     * @return array<string, mixed>
     */
    private function transformFeedback(PerformanceFeedback $feedback): array
    {
        return [
            'id' => $feedback->id,
            'type' => $feedback->type,
            'reviewer_id' => $feedback->reviewer_id,
            'reviewer_name' => $feedback->reviewer?->full_name,
            'rating' => $feedback->rating !== null ? (float) $feedback->rating : null,
            'comment' => $feedback->comment,
            'created_at' => $feedback->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Build the `{ value, label }` list of 360 feedback type options.
     *
     * @return array<int, array<string, string>>
     */
    private function feedbackTypeOptions(): array
    {
        $labels = [
            'self' => 'Diri Sendiri',
            'peer' => 'Rekan Kerja',
            'manager' => 'Atasan',
            'subordinate' => 'Bawahan',
        ];

        return collect(self::FEEDBACK_TYPES)
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => $labels[$type],
            ])
            ->all();
    }

    /**
     * Build the tenant's selectable employee options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function employeeOptions(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ])
            ->all();
    }

    /**
     * Build the tenant's selectable cycle options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cycleOptions(int $tenantId): array
    {
        return PerformanceCycle::forTenant($tenantId)
            ->latest('id')
            ->get(['id', 'name'])
            ->map(fn (PerformanceCycle $cycle): array => [
                'id' => $cycle->id,
                'name' => $cycle->name,
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of review status options.
     *
     * @return array<int, array<string, string>>
     */
    private function reviewStatusOptions(): array
    {
        $labels = [
            'pending' => 'Menunggu',
            'self_review' => 'Penilaian Mandiri',
            'manager_review' => 'Penilaian Atasan',
            'calibration' => 'Kalibrasi',
            'completed' => 'Selesai',
        ];

        return collect(self::REVIEW_STATUSES)
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => $labels[$status],
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of cycle status options.
     *
     * @return array<int, array<string, string>>
     */
    private function cycleStatusOptions(): array
    {
        $labels = [
            'draft' => 'Draf',
            'active' => 'Aktif',
            'closed' => 'Selesai',
        ];

        return collect(self::CYCLE_STATUSES)
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => $labels[$status],
            ])
            ->all();
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, PerformanceReview $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Authorize an action-level permission on this module (super admin bypasses).
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

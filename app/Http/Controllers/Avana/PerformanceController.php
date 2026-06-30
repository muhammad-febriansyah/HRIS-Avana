<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceFeedback;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    /**
     * Roles that may always manage performance reviews within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Allowed performance review status values, in display order.
     *
     * @var array<int, string>
     */
    private const REVIEW_STATUSES = ['pending', 'self_review', 'manager_review', 'completed'];

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
        $this->ensureCanManage($request);

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
        $this->ensureCanManage($request);

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
        $this->ensureCanManage($request);

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
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $review);

        $tenantId = $request->user()->tenant_id;

        $review->load(['feedbacks' => fn ($query) => $query->latest('id'), 'feedbacks.reviewer:id,full_name']);

        return Inertia::render('avana/kinerja/edit', [
            'review' => [
                'id' => $review->id,
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
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateReview($request, $tenantId);

        PerformanceReview::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.kinerja')
            ->with('success', 'Penilaian kinerja berhasil ditambahkan');
    }

    /**
     * Update an existing performance review.
     */
    public function update(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $review);

        $data = $this->validateReview($request, $request->user()->tenant_id);

        $review->update($data);

        return redirect()->route('avana.kinerja')
            ->with('success', 'Penilaian kinerja berhasil diperbarui');
    }

    /**
     * Delete a performance review.
     */
    public function destroy(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $review);

        $review->delete();

        return back()->with('success', 'Penilaian kinerja dihapus');
    }

    /**
     * Add a performance cycle within the acting user's tenant.
     */
    public function storeCycle(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

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
     * Submit the scores and status for a performance review.
     */
    public function submitScore(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $review);

        $data = $request->validate([
            'self_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'manager_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'final_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(self::REVIEW_STATUSES)],
            'review_date' => ['nullable', 'date'],
        ]);

        $review->update($data);

        return back()->with('success', 'Nilai penilaian diperbarui');
    }

    /**
     * Attach a 360 feedback entry to a performance review.
     */
    public function storeFeedback(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $review);

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
        $this->ensureCanManage($request);

        abort_if((int) $feedback->tenant_id !== (int) $request->user()->tenant_id, 404);

        $feedback->delete();

        return back()->with('success', 'Umpan balik dihapus');
    }

    /**
     * Validate the create/update payload for a performance review.
     *
     * @return array<string, mixed>
     */
    private function validateReview(Request $request, ?int $tenantId): array
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
            ],
            'reviewer_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            'self_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'manager_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'final_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(self::REVIEW_STATUSES)],
            'notes' => ['nullable', 'string'],
            'review_date' => ['nullable', 'date'],
        ]);
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

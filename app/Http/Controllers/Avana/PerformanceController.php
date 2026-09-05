<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KeyResult;
use App\Models\KpiIndicator;
use App\Models\PerformanceCycle;
use App\Models\PerformanceFeedback;
use App\Models\PerformanceKpiItem;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewRevision;
use App\Models\User;
use App\Services\PerformanceReviewWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            ->with(['employee:id,full_name,employee_number', 'cycle:id,name,status', 'reviewer:id,full_name'])
            ->latest('id')
            ->get()
            ->map(fn (PerformanceReview $review): array => $this->transformReview($review));

        $cycles = PerformanceCycle::forTenant($tenantId)
            ->withCount('reviews')
            ->latest('id')
            ->get()
            ->map(fn (PerformanceCycle $cycle): array => $this->transformCycle($cycle));

        return Inertia::render('avana/kinerja/index', [
            'can' => [
                'create' => $this->userCan($request, 'create'),
                'update' => $this->userCan($request, 'update'),
                'archive' => $this->userCan($request, 'archive'),
                'approve' => $this->userCan($request, 'approve'),
            ],
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

        // Only finalized, calibrated ratings feed HAV. Reading a provisional
        // self or manager score here would publish a number nobody signed off.
        $latestPerEmployee = PerformanceReview::forTenant($tenantId)
            ->publishable()
            // One shared definition of "latest": appraisal period first, then
            // review date, then id. See PerformanceReview::scopeLatestFirst().
            ->latestFirst()
            ->with([
                'employee:id,full_name,join_date,department_id,position_id',
                'employee.department:id,name',
                'employee.position:id,name',
            ])
            ->get()
            ->filter(fn (PerformanceReview $review): bool => $review->employee !== null)
            ->groupBy('employee_id')
            ->map(fn ($group) => $group->first());

        $rows = $latestPerEmployee
            ->map(function (PerformanceReview $review): array {
                $employee = $review->employee;
                $score = (float) $review->final_score;
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
            'cycleOptions' => $this->cycleOptions($tenantId, activeOnly: true),
        ]);
    }

    /**
     * Show the form for editing an existing performance review.
     *
     * `approve` alone is enough to open this screen: the calibration form only
     * exists here, so requiring `update` meant a second signatory could not be
     * given calibration rights without also being handed the power to write the
     * manager score they are supposed to be checking. Everything that actually
     * needs `update` stays gated by the `can` flags below.
     */
    public function edit(Request $request, PerformanceReview $review): Response
    {
        $this->ensureCanAny($request, ['update', 'approve']);
        $this->ensureTenantOwnership($request, $review);

        $tenantId = $request->user()->tenant_id;

        $review->load([
            'feedbacks' => fn ($query) => $query->latest('id'),
            'feedbacks.reviewer:id,full_name',
            'kpiItems' => fn ($query) => $query->latest('id'),
            'revisions' => fn ($query) => $query->latest('id'),
            'revisions.reopenedBy:id,name',
        ]);

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
                'scoring_mode' => $review->scoring_mode,
                'is_legacy' => (bool) $review->is_legacy,
                'is_publishable' => $review->isPublishable(),
                'manager_scored_by' => $review->manager_scored_by,
                'notes' => $review->notes,
                'self_notes' => $review->self_notes,
                'calibration_notes' => $review->calibration_notes,
                'review_date' => $review->review_date?->toDateString(),
                'cycle_status' => $review->cycle?->status,
                'period_start' => $review->cycle?->period_start?->toDateString(),
                'period_end' => $review->cycle?->period_end?->toDateString(),
            ],
            'revisions' => $review->revisions->map(fn (PerformanceReviewRevision $revision): array => [
                'id' => $revision->id,
                'from_status' => $revision->from_status,
                'to_status' => $revision->to_status,
                'self_score' => $revision->self_score !== null ? (float) $revision->self_score : null,
                'manager_score' => $revision->manager_score !== null ? (float) $revision->manager_score : null,
                'final_score' => $revision->final_score !== null ? (float) $revision->final_score : null,
                'calibrated_score' => $revision->calibrated_score !== null ? (float) $revision->calibrated_score : null,
                'reason' => $revision->reason,
                'reopened_by' => $revision->reopenedBy?->name,
                'created_at' => $revision->created_at?->toDateTimeString(),
            ])->all(),
            'feedbacks' => $review->feedbacks->map(fn (PerformanceFeedback $feedback): array => $this->transformFeedback($feedback))->all(),
            'feedbackTypes' => $this->feedbackTypeOptions(),
            'employees' => $this->employeeOptions($tenantId),
            // The review's own cycle stays selectable even if it's no longer
            // active, so the form doesn't render with a blank selection.
            'cycleOptions' => collect($this->cycleOptions($tenantId, activeOnly: true))
                ->when(
                    $review->cycle !== null && $review->cycle->status !== 'active',
                    fn ($options) => $options->push(['id' => $review->cycle->id, 'name' => $review->cycle->name])
                )
                ->unique('id')
                ->values()
                ->all(),
            'statuses' => $this->reviewStatusOptions(),
            'kpiItems' => $review->kpiItems->map(fn (PerformanceKpiItem $item): array => $this->transformKpiItem($item))->all(),
            'kpiIndicatorOptions' => $this->kpiIndicatorOptions($tenantId),
            'keyResultOptions' => $this->keyResultOptions($tenantId, $review->employee_id),
            'can' => [
                'approve' => $this->userCan($request, 'approve'),
                'update' => $this->userCan($request, 'update'),
                'archive' => $this->userCan($request, 'archive'),
                // The scorecard is only editable before the manager submits,
                // and only while the cycle is open. The UI hides the controls;
                // PerformanceKpiItemController is what actually enforces it.
                'edit_kpi' => $this->userCan($request, 'update')
                    && $review->cycle?->status === 'active'
                    && in_array($review->status, ['pending', 'self_review', 'manager_review'], true),
                'submit_score' => $this->userCan($request, 'update')
                    && $review->cycle?->status === 'active'
                    && $review->status === 'manager_review',
                'calibrate' => $this->calibrationBlockReason($request, $review) === null,
                'reopen' => $review->status === 'completed' && $this->userCan($request, 'approve'),
            ],
            // Why the calibrate button is disabled, so the page can say it
            // up front instead of letting the user submit into a refusal.
            'calibrateBlockedReason' => $this->calibrationBlockReason($request, $review),
            // Warns before the review reaches calibration that nobody else in
            // the tenant could sign it off, which is the state that used to
            // strand it there with no way out but an access-rights change.
            'hasSecondCalibrator' => $this->otherCalibratorsExist($request),
        ]);
    }

    /**
     * Why the acting user may not calibrate this review right now, or `null`
     * when they may. Mirrors every rule {@see calibrate()} and
     * {@see PerformanceReviewWorkflow::calibrate()} enforce, so the button and
     * the endpoint can never disagree.
     */
    private function calibrationBlockReason(Request $request, PerformanceReview $review): ?string
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->userCan($request, 'approve')) {
            return 'Peran Anda belum diberi izin kalibrasi penilaian kinerja.';
        }

        if ($review->cycle?->status !== 'active') {
            return 'Siklus penilaian ini tidak aktif.';
        }

        if ($review->status !== 'calibration') {
            return 'Penilaian ini belum berada pada tahap kalibrasi.';
        }

        $employeeId = $user->employee?->id;

        if ($employeeId !== null && (int) $employeeId === (int) $review->employee_id) {
            return 'Anda tidak dapat mengkalibrasi penilaian Anda sendiri.';
        }

        if ($employeeId !== null && $review->reviewer_id !== null && (int) $employeeId === (int) $review->reviewer_id) {
            return 'Kalibrasi harus dilakukan oleh pihak lain, bukan penilai yang sama.';
        }

        if ($review->manager_scored_by !== null && (int) $review->manager_scored_by === (int) $user->id) {
            return 'Kalibrasi harus dilakukan oleh pihak lain, bukan penilai yang mengisi nilai atasan.';
        }

        return null;
    }

    /**
     * Whether anyone *other than* the acting user in this tenant holds the
     * calibration permission.
     *
     * Four-eyes calibration has no escape hatch by design, so a tenant whose
     * only permission holder scores a review parks it in `calibration` forever.
     * Detecting that before the manager score is submitted turns a dead end
     * into an instruction.
     */
    private function otherCalibratorsExist(Request $request): bool
    {
        /** @var User $user */
        $user = $request->user();

        return User::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'active')
            ->whereKeyNot($user->id)
            ->holdingPermission(self::MODULE.'.approve')
            ->exists();
    }

    /**
     * Persist a new performance review under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateReview($request, $tenantId);
        $cycle = $this->ensureCycleActive($tenantId, $data['cycle_id']);
        $this->ensureReviewDateWithinCycle($cycle, $data['review_date'] ?? null);

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
        $cycle = $this->ensureCycleActive($request->user()->tenant_id, $data['cycle_id']);
        $this->ensureReviewDateWithinCycle($cycle, $data['review_date'] ?? null);

        // Once the appraisal has started collecting judgements, its subject and
        // period are frozen: re-pointing a scored review at another employee or
        // cycle would silently transplant their self-assessment and KPI items.
        if ($review->status !== 'pending') {
            abort_if(
                (int) $data['employee_id'] !== (int) $review->employee_id
                || (int) $data['cycle_id'] !== (int) $review->cycle_id,
                422,
                'Karyawan dan siklus tidak dapat diubah setelah penilaian berjalan.'
            );

            // Swapping the assigned reviewer mid-flight defeats the whole
            // separation-of-duties check: whoever is about to calibrate could
            // simply write themselves out of the reviewer slot first.
            abort_if(
                (int) ($data['reviewer_id'] ?? 0) !== (int) ($review->reviewer_id ?? 0),
                422,
                'Penilai tidak dapat diubah setelah penilaian berjalan.'
            );
        }

        // A pending review that already has a scorecard is not a blank slate:
        // its KPI items were chosen for this employee and this cycle.
        if (
            ((int) $data['employee_id'] !== (int) $review->employee_id
            || (int) $data['cycle_id'] !== (int) $review->cycle_id)
            && $review->kpiItems()->exists()
        ) {
            abort(422, 'Hapus item KPI terlebih dahulu sebelum memindahkan penilaian ke karyawan atau siklus lain.');
        }

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
            'description' => ['nullable', 'string'],
        ]);

        $this->ensureNoOverlappingCycle($tenantId, $data['period_start'], $data['period_end']);

        // Every cycle starts as a draft. Creating one directly `active` would
        // skip the setup stage, and creating one `closed` would produce a cycle
        // that never accepted a single review.
        PerformanceCycle::create([
            ...$data,
            'tenant_id' => $tenantId,
            'status' => 'draft',
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

        $this->ensureNoOverlappingCycle($cycle->tenant_id, $data['period_start'], $data['period_end'], $cycle->id);

        // Moving the period of a cycle that already holds reviews re-dates
        // every rating filed under it: review dates validated against the old
        // window fall outside the new one, and incentive periods shift beneath
        // scores that were already paid. Only a draft cycle is still free.
        $periodChanged = $cycle->period_start?->toDateString() !== Carbon::parse($data['period_start'])->toDateString()
            || $cycle->period_end?->toDateString() !== Carbon::parse($data['period_end'])->toDateString();

        if ($periodChanged && $cycle->status !== 'draft') {
            abort_if(
                $cycle->reviews()->exists(),
                422,
                'Periode siklus tidak dapat diubah karena sudah memiliki penilaian.'
            );
        }

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

        if ($cycle->status === 'closed' && $data['status'] === 'active') {
            $this->ensureCan($request, 'approve');
        }

        // Every guard below is read-then-write, so all of it runs inside one
        // transaction against row-locked cycles. Two concurrent activations
        // would otherwise both see "no other active cycle", and a close racing
        // a manager submission would strand the review it just accepted.
        DB::transaction(function () use ($cycle, $data, $allowed): void {
            /** @var PerformanceCycle $locked */
            $locked = PerformanceCycle::query()->lockForUpdate()->findOrFail($cycle->getKey());

            abort_unless(in_array($data['status'], $allowed[$locked->status] ?? [], true), 422, 'Perubahan status siklus tidak valid.');

            if ($data['status'] === 'active') {
                // Two open cycles would let the same employee accrue two
                // concurrent appraisals, and make "the current cycle" ambiguous
                // everywhere.
                $otherActive = PerformanceCycle::forTenant($locked->tenant_id)
                    ->where('status', 'active')
                    ->where('id', '!=', $locked->id)
                    ->lockForUpdate()
                    ->exists();

                abort_if($otherActive, 422, 'Sudah ada siklus penilaian yang aktif. Tutup siklus tersebut terlebih dahulu.');
            }

            if ($data['status'] === 'closed') {
                // Closing mid-flight would strand every unfinished review: the
                // cycle gate then blocks self-assessment, scoring, and
                // calibration.
                $inFlight = $locked->reviews()->where('status', '!=', 'completed')->lockForUpdate()->count();

                abort_if(
                    $inFlight > 0,
                    422,
                    "Masih ada {$inFlight} penilaian yang belum selesai pada siklus ini."
                );
            }

            $locked->update(['status' => $data['status']]);
            $cycle->setRawAttributes($locked->getAttributes(), true);
        });

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
            // Required unless the review already carries one: it decides which
            // incentive period this rating is paid in.
            'review_date' => [$review->review_date === null ? 'required' : 'nullable', 'date'],
        ], [
            'review_date.required' => 'Tanggal penilaian wajib diisi.',
        ]);

        // Checked before the four-eyes availability guard so a review at the
        // wrong stage still reports the wrong stage, not a staffing problem.
        $this->workflow()->assertTransition($review, 'calibration');
        $this->ensureACalibratorRemains($request);

        $this->workflow()->submitManagerScore(
            $review,
            $data['manager_score'] ?? null,
            $data['review_date'] ?? null,
            $request->user(),
        );

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
        $this->ensureIsNotOwnReview($request, $review);

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
        $this->workflow()->assertCycleOpen($review);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'type' => ['required', Rule::in(self::FEEDBACK_TYPES)],
            'reviewer_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            // Feedback carrying neither a rating nor a comment says nothing.
            'rating' => ['nullable', 'required_without:comment', 'numeric', 'min:0', 'max:100'],
            'comment' => ['nullable', 'required_without:rating', 'string', 'max:2000'],
        ], [
            'rating.required_without' => 'Isi nilai atau komentar.',
            'comment.required_without' => 'Isi nilai atau komentar.',
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
        $this->workflow()->assertCycleOpen($feedback->review);

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
     * Build the row shape consumed by the KPI item panel.
     *
     * @return array<string, mixed>
     */
    private function transformKpiItem(PerformanceKpiItem $item): array
    {
        return [
            'id' => $item->id,
            'source' => $item->source,
            'kpi_indicator_id' => $item->kpi_indicator_id,
            'key_result_id' => $item->key_result_id,
            'label' => $item->label,
            'weight' => (float) $item->weight,
            'direction' => $item->direction,
            'target_value' => $item->target_value !== null ? (float) $item->target_value : null,
            'actual_value' => $item->actual_value !== null ? (float) $item->actual_value : null,
            'achievement_pct' => (float) $item->achievement_pct,
        ];
    }

    /**
     * Build the tenant's active KPI indicator options for the item picker.
     *
     * @return array<int, array<string, mixed>>
     */
    private function kpiIndicatorOptions(int $tenantId): array
    {
        return KpiIndicator::forTenant($tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'direction'])
            ->map(fn (KpiIndicator $indicator): array => [
                'id' => $indicator->id,
                'name' => $indicator->name,
                'unit' => $indicator->unit,
                'direction' => $indicator->direction,
            ])
            ->all();
    }

    /**
     * Build the reviewed employee's Key Result options for the item picker.
     *
     * @return array<int, array<string, mixed>>
     */
    private function keyResultOptions(int $tenantId, int $employeeId): array
    {
        return KeyResult::forTenant($tenantId)
            ->whereHas('objective', fn ($query) => $query->where('employee_id', $employeeId))
            ->with('objective:id,title')
            ->get(['id', 'objective_id', 'title', 'progress'])
            ->map(fn (KeyResult $keyResult): array => [
                'id' => $keyResult->id,
                'title' => $keyResult->title,
                'objective_title' => $keyResult->objective?->title,
                'progress' => $keyResult->progress,
            ])
            ->all();
    }

    /**
     * True when the acting user may perform the given action, without
     * aborting — for building `can` capability flags in the response.
     */
    private function userCan(Request $request, string $action): bool
    {
        /** @var User $user */
        $user = $request->user();

        return $user->isSuperAdmin() || $user->hasPermissionTo(self::MODULE.'.'.$action);
    }

    /**
     * Abort with 422 unless the given cycle is currently active. Reviews may
     * only be created or edited under an open cycle.
     */
    private function ensureCycleActive(?int $tenantId, int $cycleId): PerformanceCycle
    {
        $cycle = PerformanceCycle::forTenant($tenantId)->find($cycleId);

        abort_unless($cycle?->status === 'active', 422, 'Siklus penilaian ini tidak aktif.');

        return $cycle;
    }

    /**
     * Abort with 422 when a review date falls outside its cycle's period.
     * Mirrors the same check the workflow applies at manager scoring, so a bad
     * date is refused at entry rather than several stages later.
     */
    private function ensureReviewDateWithinCycle(PerformanceCycle $cycle, ?string $reviewDate): void
    {
        if ($reviewDate === null || $cycle->period_start === null || $cycle->period_end === null) {
            return;
        }

        abort_unless(
            Carbon::parse($reviewDate)->startOfDay()->betweenIncluded(
                $cycle->period_start->startOfDay(),
                $cycle->period_end->endOfDay(),
            ),
            422,
            'Tanggal penilaian harus berada dalam periode siklus ('
            .$cycle->period_start->toDateString().' s/d '.$cycle->period_end->toDateString().').'
        );
    }

    /**
     * Abort with 422 when the given period overlaps another cycle in the same
     * tenant. Overlapping cycles make "which cycle does this date belong to"
     * unanswerable for every downstream report.
     */
    private function ensureNoOverlappingCycle(?int $tenantId, string $periodStart, string $periodEnd, ?int $ignoreCycleId = null): void
    {
        $overlapping = PerformanceCycle::forTenant($tenantId)
            ->when($ignoreCycleId !== null, fn ($query) => $query->where('id', '!=', $ignoreCycleId))
            ->where('period_start', '<=', $periodEnd)
            ->where('period_end', '>=', $periodStart)
            ->first();

        abort_if(
            $overlapping !== null,
            422,
            "Periode siklus tumpang tindih dengan siklus '{$overlapping?->name}'."
        );
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

    /**
     * Refuse calibration by the subject of the review or by its assigned
     * reviewer. Calibration exists to check the manager's rating — it cannot be
     * performed by the person being rated or the person rating.
     *
     * Raised as a validation error rather than a 403 so the calibrator reads
     * the actual reason on the form, with their input intact, instead of a
     * full-page "your role lacks permission" that names the wrong cause.
     */
    private function ensureIsNotOwnReview(Request $request, PerformanceReview $review): void
    {
        /** @var User $user */
        $user = $request->user();
        $employeeId = $user->employee?->id;

        if ($employeeId === null) {
            return;
        }

        if ((int) $employeeId === (int) $review->employee_id) {
            throw ValidationException::withMessages([
                'calibrated_score' => 'Anda tidak dapat mengkalibrasi penilaian Anda sendiri.',
            ]);
        }

        if ($review->reviewer_id !== null && (int) $employeeId === (int) $review->reviewer_id) {
            throw ValidationException::withMessages([
                'calibrated_score' => 'Kalibrasi harus dilakukan oleh pihak lain, bukan penilai yang sama.',
            ]);
        }
    }

    /**
     * Refuse to move a review into calibration when the acting user is the only
     * account in the tenant that could calibrate it.
     *
     * Four-eyes calibration is absolute, so submitting the manager score in a
     * single-approver tenant produces a review nobody is allowed to finish. The
     * refusal is raised here, on the form that causes it, and says what to do
     * about it.
     */
    private function ensureACalibratorRemains(Request $request): void
    {
        if ($this->otherCalibratorsExist($request)) {
            return;
        }

        throw ValidationException::withMessages([
            'manager_score' => 'Tidak ada pengguna lain yang berizin mengkalibrasi di perusahaan ini, '
                .'sehingga penilaian akan tertahan di tahap Kalibrasi. Beri izin "Setujui" pada peran '
                .'penandatangan kedua di Hak Akses terlebih dahulu.',
        ]);
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
            'is_legacy' => (bool) $review->is_legacy,
            'is_publishable' => $review->isPublishable(),
            'cycle_status' => $review->cycle?->status,
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
     * Build the tenant's selectable cycle options. Reviews may only be
     * created/edited under an active cycle, so `$activeOnly` narrows the list
     * accordingly (the index page still wants every cycle, for filtering).
     *
     * @return array<int, array<string, mixed>>
     */
    private function cycleOptions(int $tenantId, bool $activeOnly = false): array
    {
        return PerformanceCycle::forTenant($tenantId)
            ->when($activeOnly, fn ($query) => $query->where('status', 'active'))
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

    /**
     * Authorize a screen that any one of several actions may open.
     *
     * @param  array<int, string>  $actions
     */
    private function ensureCanAny(Request $request, array $actions): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless(
            collect($actions)->contains(fn (string $action): bool => $user->hasPermissionTo(self::MODULE.'.'.$action)),
            403,
        );
    }
}

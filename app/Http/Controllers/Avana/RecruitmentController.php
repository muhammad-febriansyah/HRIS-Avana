<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantBackgroundCheck;
use App\Models\ApplicantMedicalCheck;
use App\Models\Department;
use App\Models\Employee;
use App\Models\HeadcountRequest;
use App\Models\JobPosting;
use App\Models\SalesOrder;
use App\Models\TalentPool;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentController extends Controller
{
    /**
     * Permission module key for action-level RBAC.
     */
    private const MODULE = 'recruitment';

    /**
     * Allowed employment type enum values for a job posting.
     *
     * @var array<int, string>
     */
    private const EMPLOYMENT_TYPES = ['tetap', 'kontrak', 'magang', 'harian'];

    /**
     * Allowed applicant pipeline stages, in display order.
     *
     * @var array<int, string>
     */
    private const STAGES = ['applied', 'screening', 'shortlisted', 'interview', 'offer', 'hired', 'rejected'];

    /**
     * Allowed medical checkup status values.
     *
     * @var array<int, string>
     */
    private const MEDICAL_STATUSES = ['pending', 'passed', 'failed'];

    /**
     * Allowed background-check type values.
     *
     * @var array<int, string>
     */
    private const BACKGROUND_TYPES = ['employment', 'education', 'criminal', 'reference'];

    /**
     * Allowed background-check status values.
     *
     * @var array<int, string>
     */
    private const BACKGROUND_STATUSES = ['requested', 'clear', 'flagged'];

    /**
     * Recruitment dashboard: hiring funnel, headline KPIs, and today's tasks.
     */
    public function dashboard(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;
        $today = Carbon::today();

        $applicants = Applicant::forTenant($tenantId)->get();
        $labels = self::stageLabels();

        $funnel = collect(['applied', 'screening', 'shortlisted', 'interview'])
            ->map(fn (string $stage): array => [
                'label' => $labels[$stage],
                'value' => $applicants->where('stage', $stage)->count(),
            ])
            ->push([
                'label' => 'Keputusan',
                'value' => $applicants->whereIn('stage', ['offer', 'hired'])->count(),
            ])
            ->all();

        $hired = $applicants->where('stage', 'hired');
        $timeToHire = $hired
            ->filter(fn (Applicant $a): bool => $a->applied_date !== null)
            ->map(fn (Applicant $a): int => (int) $a->applied_date->diffInDays($a->updated_at ?? $today))
            ->avg();

        $tasks = Applicant::forTenant($tenantId)
            ->whereNotNull('interview_at')
            ->whereDate('interview_at', '>=', $today)
            ->with('jobPosting:id,title')
            ->orderBy('interview_at')
            ->limit(6)
            ->get()
            ->map(fn (Applicant $a): array => [
                'id' => $a->id,
                'title' => 'Wawancara '.$a->name,
                'subtitle' => $a->jobPosting?->title ?? '—',
                'at' => $a->interview_at?->toDateTimeString(),
            ])
            ->all();

        return Inertia::render('avana/rekrutmen/dashboard', [
            'kpis' => [
                'incoming' => $applicants->where('stage', 'applied')->count(),
                'interviews_today' => $applicants->filter(
                    fn (Applicant $a): bool => $a->interview_at !== null && $a->interview_at->isSameDay($today)
                )->count(),
                'active_offers' => $applicants->whereIn('offer_status', ['sent', 'approved'])->count(),
                'time_to_hire' => $timeToHire !== null ? (int) round($timeToHire) : 0,
            ],
            'funnel' => $funnel,
            'tasks' => $tasks,
        ]);
    }

    /**
     * Job requisitions (vacancy cards) with applicant counts.
     */
    public function jobs(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $postings = JobPosting::forTenant($tenantId)
            ->with('department:id,name')
            ->withCount('applicants')
            ->latest('id')
            ->get()
            ->map(fn (JobPosting $posting): array => $this->transformPosting($posting));

        return Inertia::render('avana/rekrutmen/jobs', [
            'postings' => $postings,
            'departments' => $this->departmentOptions($tenantId),
            'kpis' => [
                'open_postings' => $postings->where('status', 'open')->count(),
                'total_postings' => $postings->count(),
                'total_quota' => $postings->sum('quota'),
            ],
        ]);
    }

    /**
     * Drag-and-drop applicant pipeline grouped by stage.
     */
    public function pipeline(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $applicants = Applicant::forTenant($tenantId)
            ->with('jobPosting:id,title')
            ->latest('id')
            ->get()
            ->map(fn (Applicant $applicant): array => $this->transformApplicant($applicant));

        $columns = ['applied', 'screening', 'shortlisted', 'interview'];

        $pipeline = collect($columns)
            ->mapWithKeys(fn (string $stage): array => [
                $stage => $applicants->where('stage', $stage)->values()->all(),
            ])
            ->all();

        return Inertia::render('avana/rekrutmen/pipeline', [
            'pipeline' => $pipeline,
            'stages' => collect($columns)->map(fn (string $s): array => [
                'value' => $s,
                'label' => self::stageLabels()[$s],
            ])->all(),
            'total' => $applicants->whereIn('stage', $columns)->count(),
        ]);
    }

    /**
     * Centralised candidate database (table + add-candidate modal).
     */
    public function candidates(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $applicants = Applicant::forTenant($tenantId)
            ->with(['jobPosting:id,title', 'talentPool:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (Applicant $a): array => $this->transformCandidateRow($a));

        return Inertia::render('avana/rekrutmen/candidates', [
            'candidates' => $applicants,
            'jobs' => JobPosting::forTenant($tenantId)
                ->orderBy('title')
                ->get(['id', 'title'])
                ->map(fn (JobPosting $j): array => ['id' => $j->id, 'title' => $j->title])
                ->all(),
            'stages' => $this->stageOptions(),
        ]);
    }

    /**
     * AI Intelligence hub. AI matching is not enabled yet, so this renders the
     * empty state while the surrounding recruitment data is already in place.
     */
    public function aiIntelligence(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        return Inertia::render('avana/rekrutmen/ai', [
            'recommendations' => Applicant::forTenant($tenantId)
                ->whereNotNull('ai_recommendation')
                ->with('jobPosting:id,title')
                ->latest('id')
                ->get()
                ->map(fn (Applicant $a): array => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'job_title' => $a->jobPosting?->title,
                    'confidence' => $a->ai_confidence,
                    'recommendation' => $a->ai_recommendation,
                ])
                ->all(),
        ]);
    }

    /**
     * Talent pools with member counts.
     */
    public function pools(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $pools = TalentPool::forTenant($tenantId)
            ->withCount('applicants')
            ->orderBy('name')
            ->get()
            ->map(fn (TalentPool $pool): array => [
                'id' => $pool->id,
                'name' => $pool->name,
                'description' => $pool->description,
                'is_auto' => (bool) $pool->is_auto,
                'members_count' => $pool->applicants_count,
            ]);

        return Inertia::render('avana/rekrutmen/pools', [
            'pools' => $pools,
        ]);
    }

    /**
     * Interview schedule with type, status, and location.
     */
    public function interviews(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $rows = Applicant::forTenant($tenantId)
            ->whereNotNull('interview_at')
            ->with('jobPosting:id,title')
            ->orderByDesc('interview_at')
            ->get()
            ->map(fn (Applicant $a): array => [
                'id' => $a->id,
                'name' => $a->name,
                'job_title' => $a->jobPosting?->title,
                'type' => $a->interview_type,
                'status' => $a->interview_status ?? 'scheduled',
                'result' => $a->interview_result,
                'location' => $a->interview_location,
                'interview_at' => $a->interview_at?->toDateTimeString(),
            ]);

        return Inertia::render('avana/rekrutmen/interviews', [
            'interviews' => $rows,
        ]);
    }

    /**
     * Offer management with governance-style approval status.
     */
    public function offers(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $rows = Applicant::forTenant($tenantId)
            ->where(fn ($q) => $q->whereNotNull('offered_at')->orWhereNotNull('offer_status'))
            ->with('jobPosting:id,title')
            ->latest('offered_at')
            ->get()
            ->map(fn (Applicant $a): array => [
                'id' => $a->id,
                'name' => $a->name,
                'job_title' => $a->jobPosting?->title,
                'salary' => $a->offer_salary !== null ? (float) $a->offer_salary : null,
                'start_date' => $a->offer_start_date?->toDateString(),
                'status' => $a->offer_status ?? 'draft',
                'note' => $a->offer_note,
                'activated' => $a->employee_id !== null,
            ]);

        return Inertia::render('avana/rekrutmen/offers', [
            'offers' => $rows,
        ]);
    }

    /**
     * Recruitment analytics: funnel, source mix, and conversion metrics.
     */
    public function analytics(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $applicants = Applicant::forTenant($tenantId)->get();
        $labels = self::stageLabels();
        $total = $applicants->count();
        $hired = $applicants->where('stage', 'hired')->count();

        $bySource = $applicants
            ->groupBy(fn (Applicant $a): string => $a->source ?: 'Lainnya')
            ->map(fn ($group, $source): array => ['label' => (string) $source, 'value' => $group->count()])
            ->values()
            ->all();

        $byStage = collect(self::STAGES)
            ->map(fn (string $stage): array => [
                'label' => $labels[$stage],
                'value' => $applicants->where('stage', $stage)->count(),
            ])
            ->all();

        return Inertia::render('avana/rekrutmen/analytics', [
            'kpis' => [
                'total_candidates' => $total,
                'hired' => $hired,
                'conversion_rate' => $total > 0 ? round(($hired / $total) * 100, 1) : 0.0,
                'open_postings' => JobPosting::forTenant($tenantId)->where('status', 'open')->count(),
            ],
            'bySource' => $bySource,
            'byStage' => $byStage,
        ]);
    }

    /**
     * Headcount approval queue for HR to review manager requests.
     */
    public function headcount(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $rows = HeadcountRequest::forTenant($tenantId)
            ->with(['requester:id,name', 'department:id,name', 'decider:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (HeadcountRequest $r): array => [
                'id' => $r->id,
                'requester' => $r->requester?->name ?? '—',
                'position' => $r->position_title,
                'unit' => $r->department?->name ?? '—',
                'count' => $r->count,
                'reason' => $r->reason,
                'status' => $r->status,
                'decided_by' => $r->decider?->name,
                'decided_at' => $r->decided_at?->toDateString(),
            ]);

        return Inertia::render('avana/rekrutmen/headcount', [
            'requests' => $rows,
            'departments' => $this->departmentOptions($tenantId),
        ]);
    }

    /**
     * Submit a new headcount request.
     */
    public function storeHeadcount(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'position_title' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'count' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
        ]);

        HeadcountRequest::create([
            ...$data,
            'tenant_id' => $tenantId,
            'requester_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Permintaan headcount diajukan');
    }

    /**
     * Approve or reject a headcount request.
     */
    public function decideHeadcount(Request $request, HeadcountRequest $headcountRequest): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        abort_if((int) $headcountRequest->tenant_id !== (int) $request->user()->tenant_id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        $headcountRequest->update([
            'status' => $data['status'],
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Permintaan headcount diperbarui');
    }

    /**
     * Sales Orders forwarded from Payroll awaiting benefit approval (BPR manual
     * 1.4). Recruitment reviews the mapped Master Gaji / shift / leave benefit.
     */
    public function salesOrders(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $rows = SalesOrder::forTenant($tenantId)
            ->whereIn('status', ['forwarded', 'approved'])
            ->with(['salaryMaster:id,code,category', 'shift:id,name', 'leaveType:id,name', 'forwarder:id,name', 'benefitDecider:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (SalesOrder $o): array => [
                'id' => $o->id,
                'code' => $o->code,
                'client_name' => $o->client_name,
                'position_name' => $o->position_name,
                'headcount' => $o->headcount,
                'salary_master' => $o->salaryMaster?->code.' — '.$o->salaryMaster?->category,
                'shift' => $o->shift?->name,
                'leave_type' => $o->leaveType?->name,
                'status' => $o->status,
                'forwarded_by' => $o->forwarder?->name,
                'benefit_decided_by' => $o->benefitDecider?->name,
                'benefit_decided_at' => $o->benefit_decided_at?->toDateString(),
            ]);

        return Inertia::render('avana/rekrutmen/sales-order', [
            'orders' => $rows,
        ]);
    }

    /**
     * Approve or reject the payroll benefit on a forwarded Sales Order. Approving
     * finalizes it; rejecting returns it to Payroll (status "mapped") with a note.
     */
    public function decideSalesOrder(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        abort_if((int) $salesOrder->tenant_id !== (int) $request->user()->tenant_id, 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('decision') === 'reject')],
        ]);

        if ($salesOrder->status !== 'forwarded') {
            return back()->withErrors(['sales_order' => 'Sales Order ini tidak menunggu approval benefit.']);
        }

        $salesOrder->update([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'mapped',
            'benefit_decided_by' => $request->user()->id,
            'benefit_decided_at' => now(),
            'benefit_note' => $data['note'] ?? null,
        ]);

        return back()->with('success', $data['decision'] === 'approve'
            ? 'Benefit Sales Order disetujui'
            : 'Benefit Sales Order ditolak, dikembalikan ke payroll');
    }

    /**
     * Create a talent pool.
     */
    public function storePool(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('talent_pools', 'name')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        TalentPool::create([
            ...$data,
            'tenant_id' => $tenantId,
            'is_auto' => false,
        ]);

        return back()->with('success', 'Talent pool dibuat');
    }

    /**
     * Approve or reject a candidate offer.
     */
    public function decideOffer(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureTenantOwnership($request, $applicant);

        $data = $request->validate([
            'offer_status' => ['required', Rule::in(['approved', 'accepted', 'rejected'])],
        ]);

        $applicant->update([
            'offer_status' => $data['offer_status'],
            'stage' => $data['offer_status'] === 'accepted' ? 'hired' : $applicant->stage,
        ]);

        return back()->with('success', 'Status penawaran diperbarui');
    }

    /**
     * Show the form for creating a new job posting.
     */
    public function create(Request $request): Response
    {
        $this->ensureCan($request, 'create');

        return Inertia::render('avana/rekrutmen/create', [
            'departments' => $this->departmentOptions($request->user()->tenant_id),
        ]);
    }

    /**
     * Show the form for editing an existing job posting.
     */
    public function edit(Request $request, JobPosting $jobPosting): Response
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $jobPosting);

        return Inertia::render('avana/rekrutmen/edit', [
            'posting' => [
                'id' => $jobPosting->id,
                'title' => $jobPosting->title,
                'department_id' => $jobPosting->department_id,
                'location' => $jobPosting->location,
                'employment_type' => $jobPosting->employment_type,
                'quota' => $jobPosting->quota,
                'status' => $jobPosting->status,
                'description' => $jobPosting->description,
                'posted_date' => $jobPosting->posted_date?->toDateString(),
                'closing_date' => $jobPosting->closing_date?->toDateString(),
            ],
            'departments' => $this->departmentOptions($request->user()->tenant_id),
        ]);
    }

    /**
     * Persist a new job posting under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;

        $data = $this->validatePosting($request, $tenantId);

        JobPosting::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.rekrutmen')
            ->with('success', 'Lowongan berhasil ditambahkan');
    }

    /**
     * Update an existing job posting.
     */
    public function update(Request $request, JobPosting $jobPosting): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $jobPosting);

        $data = $this->validatePosting($request, $request->user()->tenant_id);

        $jobPosting->update($data);

        return redirect()->route('avana.rekrutmen')
            ->with('success', 'Lowongan berhasil diperbarui');
    }

    /**
     * Delete a job posting.
     */
    public function destroy(Request $request, JobPosting $jobPosting): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $jobPosting);

        $jobPosting->delete();

        return back()->with('success', 'Lowongan dihapus');
    }

    /**
     * Add an applicant to a job posting within the acting user's tenant.
     */
    public function storeApplicant(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'job_posting_id' => [
                'required',
                'integer',
                Rule::exists('job_postings', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'stage' => ['required', Rule::in(self::STAGES)],
            'applied_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
        ]);

        // Business rule: a candidate may only apply once to the same vacancy.
        $duplicate = Applicant::forTenant($tenantId)
            ->where('job_posting_id', $data['job_posting_id'])
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['email' => 'Kandidat ini sudah melamar pada lowongan tersebut.']);
        }

        $applicant = Applicant::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        $applicant->update([
            'tracking_number' => 'APP-'.$applicant->created_at->format('Y').'-'.str_pad((string) $applicant->id, 5, '0', STR_PAD_LEFT),
        ]);

        return back()->with('success', 'Pelamar ditambahkan (No. '.$applicant->tracking_number.')');
    }

    /**
     * Stage 6 — record the interviewer's verdict. Passed keeps the candidate in
     * the interview stage ready for an offer; Failed moves them to rejected.
     */
    public function recordInterviewResult(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureTenantOwnership($request, $applicant);

        $data = $request->validate([
            'interview_result' => ['required', Rule::in(['passed', 'failed'])],
        ]);

        $applicant->update([
            'interview_result' => $data['interview_result'],
            'interview_status' => 'completed',
            'stage' => $data['interview_result'] === 'failed' ? 'rejected' : $applicant->stage,
        ]);

        return back()->with('success', 'Hasil wawancara disimpan');
    }

    /**
     * Stage 8 — Onboarding. Activate a hired candidate into an active Employee
     * record and mark the applicant onboarded.
     */
    public function activateEmployee(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureTenantOwnership($request, $applicant);

        if ($applicant->employee_id !== null) {
            return back()->withErrors(['applicant' => 'Kandidat ini sudah diaktivasi menjadi karyawan.']);
        }

        if (! in_array($applicant->stage, ['offer', 'hired'], true) || $applicant->offer_status !== 'accepted') {
            return back()->withErrors(['applicant' => 'Aktivasi hanya untuk kandidat dengan penawaran diterima.']);
        }

        $data = $request->validate([
            'join_date' => ['nullable', 'date'],
        ]);

        $applicant->loadMissing('jobPosting:id,department_id');
        $tenantId = (int) $applicant->tenant_id;

        $employee = Employee::create([
            'tenant_id' => $tenantId,
            'employee_number' => $this->nextEmployeeNumber($tenantId),
            'full_name' => $applicant->name,
            'email' => $applicant->email,
            'phone' => $applicant->phone,
            'department_id' => $applicant->jobPosting?->department_id,
            'join_date' => $data['join_date'] ?? $applicant->offer_start_date?->toDateString() ?? now()->toDateString(),
            'employment_status' => 'probation',
            'status' => 'active',
        ]);

        $applicant->update([
            'employee_id' => $employee->id,
            'stage' => 'hired',
            'onboarded_at' => now(),
        ]);

        return back()->with('success', 'Kandidat diaktivasi menjadi karyawan ('.$employee->employee_number.')');
    }

    /**
     * The next unused tenant-scoped employee number (EMP00001, EMP00002, …).
     */
    private function nextEmployeeNumber(int $tenantId): string
    {
        $seq = (int) Employee::forTenant($tenantId)->max('id') + 1;

        do {
            $number = 'EMP'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            $seq++;
        } while (Employee::forTenant($tenantId)->where('employee_number', $number)->exists());

        return $number;
    }

    /**
     * Move an applicant to a different pipeline stage.
     */
    public function moveStage(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureTenantOwnership($request, $applicant);

        $data = $request->validate([
            'stage' => ['required', Rule::in(self::STAGES)],
        ]);

        $applicant->update(['stage' => $data['stage']]);

        return back()->with('success', 'Tahap pelamar diperbarui');
    }

    /**
     * Toggle the candidate's blacklist flag (with an optional reason).
     */
    public function toggleBlacklist(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $applicant);

        $data = $request->validate([
            'blacklisted' => ['required', 'boolean'],
            'blacklist_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $applicant->update([
            'blacklisted' => $data['blacklisted'],
            'blacklist_reason' => $data['blacklisted'] ? ($data['blacklist_reason'] ?? null) : null,
        ]);

        return back()->with('success', $data['blacklisted'] ? 'Kandidat masuk blacklist' : 'Kandidat dikeluarkan dari blacklist');
    }

    /**
     * Display the full candidate detail screen for a single applicant.
     */
    public function showApplicant(Request $request, Applicant $applicant): Response
    {
        $this->ensureCan($request, 'view');
        $this->ensureTenantOwnership($request, $applicant);

        $applicant->load([
            'jobPosting:id,title,employment_type',
            'medicalChecks' => fn ($query) => $query->latest('id'),
            'backgroundChecks' => fn ($query) => $query->latest('id'),
        ]);

        return Inertia::render('avana/rekrutmen/candidate', [
            'applicant' => $this->transformApplicantDetail($applicant),
            'stages' => $this->stageOptions(),
        ]);
    }

    /**
     * Update an applicant's contact profile and social links.
     */
    public function updateApplicant(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $applicant);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $applicant->update($data);

        return back()->with('success', 'Data kandidat diperbarui');
    }

    /**
     * Store (or replace) the applicant's uploaded CV file.
     */
    public function uploadCv(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $applicant);

        $request->validate([
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        if ($applicant->cv_path) {
            Storage::disk('public')->delete($applicant->cv_path);
        }

        $path = $request->file('cv')->store("recruitment/cv/{$applicant->tenant_id}", 'public');
        $applicant->update(['cv_path' => $path]);

        return back()->with('success', 'CV berhasil diunggah');
    }

    /**
     * Schedule an interview and advance the applicant to the interview stage.
     */
    public function scheduleInterview(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $applicant);

        $data = $request->validate([
            'interview_at' => ['required', 'date'],
            'interview_type' => ['nullable', Rule::in(['hr', 'technical', 'user', 'final'])],
            'interview_location' => ['nullable', 'string', 'max:255'],
        ]);

        $applicant->update([
            'interview_at' => $data['interview_at'],
            'interview_type' => $data['interview_type'] ?? 'hr',
            'interview_location' => $data['interview_location'] ?? null,
            'interview_status' => 'scheduled',
            'stage' => 'interview',
        ]);

        return back()->with('success', 'Jadwal wawancara disimpan');
    }

    /**
     * Record a job offer and advance the applicant to the offer stage.
     */
    public function makeOffer(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $applicant);

        $data = $request->validate([
            'offer_note' => ['nullable', 'string'],
            'offer_salary' => ['nullable', 'numeric', 'min:0'],
            'offer_start_date' => ['nullable', 'date'],
        ]);

        $applicant->update([
            'offer_note' => $data['offer_note'] ?? null,
            'offer_salary' => $data['offer_salary'] ?? null,
            'offer_start_date' => $data['offer_start_date'] ?? null,
            'offer_status' => 'sent',
            'offered_at' => now(),
            'stage' => 'offer',
        ]);

        return back()->with('success', 'Penawaran dikirim ke kandidat');
    }

    /**
     * Attach a medical checkup record (with optional document) to the applicant.
     */
    public function storeMedicalCheck(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'create');
        $this->ensureTenantOwnership($request, $applicant);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(self::MEDICAL_STATUSES)],
            'checked_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $path = $request->hasFile('document')
            ? $request->file('document')->store("recruitment/medical/{$applicant->tenant_id}", 'public')
            : null;

        ApplicantMedicalCheck::create([
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'title' => $data['title'],
            'status' => $data['status'],
            'checked_at' => $data['checked_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'file_path' => $path,
        ]);

        return back()->with('success', 'Pemeriksaan kesehatan ditambahkan');
    }

    /**
     * Record a background-check request (with optional document) for the applicant.
     */
    public function storeBackgroundCheck(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'create');
        $this->ensureTenantOwnership($request, $applicant);

        $data = $request->validate([
            'check_type' => ['required', Rule::in(self::BACKGROUND_TYPES)],
            'status' => ['required', Rule::in(self::BACKGROUND_STATUSES)],
            'requested_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $path = $request->hasFile('document')
            ? $request->file('document')->store("recruitment/background/{$applicant->tenant_id}", 'public')
            : null;

        ApplicantBackgroundCheck::create([
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'check_type' => $data['check_type'],
            'status' => $data['status'],
            'requested_at' => $data['requested_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'file_path' => $path,
        ]);

        return back()->with('success', 'Pemeriksaan latar belakang ditambahkan');
    }

    /**
     * Validate the create/update payload for a job posting.
     *
     * @return array<string, mixed>
     */
    private function validatePosting(Request $request, ?int $tenantId): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'location' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['required', Rule::in(self::EMPLOYMENT_TYPES)],
            'quota' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:open,closed'],
            'description' => ['nullable', 'string'],
            'posted_date' => ['nullable', 'date'],
            'closing_date' => ['nullable', 'date'],
        ]);
    }

    /**
     * Build the row shape consumed by the postings table.
     *
     * @return array<string, mixed>
     */
    private function transformPosting(JobPosting $posting): array
    {
        return [
            'id' => $posting->id,
            'title' => $posting->title,
            'department' => $posting->department?->name,
            'department_id' => $posting->department_id,
            'location' => $posting->location,
            'employment_type' => $posting->employment_type,
            'quota' => $posting->quota,
            'status' => $posting->status,
            'description' => $posting->description,
            'posted_date' => $posting->posted_date?->toDateString(),
            'closing_date' => $posting->closing_date?->toDateString(),
            'applicants_count' => $posting->applicants_count,
        ];
    }

    /**
     * Build the card shape consumed by the applicant pipeline board.
     *
     * @return array<string, mixed>
     */
    private function transformApplicant(Applicant $applicant): array
    {
        return [
            'id' => $applicant->id,
            'name' => $applicant->name,
            'email' => $applicant->email,
            'phone' => $applicant->phone,
            'source' => $applicant->source,
            'stage' => $applicant->stage,
            'applied_date' => $applicant->applied_date?->toDateString(),
            'notes' => $applicant->notes,
            'position' => $applicant->position,
            'job_posting_id' => $applicant->job_posting_id,
            'job_title' => $applicant->jobPosting?->title,
        ];
    }

    /**
     * Build the rich candidate-detail payload consumed by the detail screen.
     *
     * @return array<string, mixed>
     */
    private function transformApplicantDetail(Applicant $applicant): array
    {
        return [
            'id' => $applicant->id,
            'name' => $applicant->name,
            'email' => $applicant->email,
            'phone' => $applicant->phone,
            'source' => $applicant->source,
            'stage' => $applicant->stage,
            'blacklisted' => (bool) $applicant->blacklisted,
            'blacklist_reason' => $applicant->blacklist_reason,
            'position' => $applicant->position ?? $applicant->jobPosting?->title,
            'photo_url' => $applicant->photo_path ? Storage::disk('public')->url($applicant->photo_path) : null,
            'linkedin_url' => $applicant->linkedin_url,
            'portfolio_url' => $applicant->portfolio_url,
            'cv_url' => $applicant->cv_path ? Storage::disk('public')->url($applicant->cv_path) : null,
            'notes' => $applicant->notes,
            'applied_date' => $applicant->applied_date?->toDateString(),
            'interview_at' => $applicant->interview_at?->toDateTimeString(),
            'offered_at' => $applicant->offered_at?->toDateTimeString(),
            'offer_note' => $applicant->offer_note,
            'job_posting_id' => $applicant->job_posting_id,
            'job_title' => $applicant->jobPosting?->title,
            'employment_type' => $applicant->jobPosting?->employment_type,
            'medical_checks' => $applicant->medicalChecks->map(fn (ApplicantMedicalCheck $check): array => [
                'id' => $check->id,
                'title' => $check->title,
                'status' => $check->status,
                'notes' => $check->notes,
                'checked_at' => $check->checked_at?->toDateString(),
                'file_url' => $check->file_path ? Storage::disk('public')->url($check->file_path) : null,
            ])->all(),
            'background_checks' => $applicant->backgroundChecks->map(fn (ApplicantBackgroundCheck $check): array => [
                'id' => $check->id,
                'check_type' => $check->check_type,
                'status' => $check->status,
                'notes' => $check->notes,
                'requested_at' => $check->requested_at?->toDateString(),
                'file_url' => $check->file_path ? Storage::disk('public')->url($check->file_path) : null,
            ])->all(),
        ];
    }

    /**
     * Indonesian labels for the applicant pipeline stages.
     *
     * @return array<string, string>
     */
    private static function stageLabels(): array
    {
        return [
            'applied' => 'Melamar',
            'screening' => 'Seleksi',
            'shortlisted' => 'Shortlist',
            'interview' => 'Wawancara',
            'offer' => 'Penawaran',
            'hired' => 'Diterima',
            'rejected' => 'Ditolak',
        ];
    }

    /**
     * Build the row shape consumed by the candidates table.
     *
     * @return array<string, mixed>
     */
    private function transformCandidateRow(Applicant $applicant): array
    {
        return [
            'id' => $applicant->id,
            'name' => $applicant->name,
            'email' => $applicant->email,
            'phone' => $applicant->phone,
            'stage' => $applicant->stage,
            'applied_date' => $applicant->applied_date?->toDateString(),
            'job_title' => $applicant->jobPosting?->title,
            'pool' => $applicant->talentPool?->name,
            'ai_confidence' => $applicant->ai_confidence,
            'ai_recommendation' => $applicant->ai_recommendation,
        ];
    }

    /**
     * Build the tenant's selectable department options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function departmentOptions(int $tenantId): array
    {
        return Department::forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of applicant pipeline stages.
     *
     * @return array<int, array<string, string>>
     */
    private function stageOptions(): array
    {
        $labels = self::stageLabels();

        return collect(self::STAGES)
            ->map(fn (string $stage): array => [
                'value' => $stage,
                'label' => $labels[$stage],
            ])
            ->all();
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, JobPosting|Applicant $record): void
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

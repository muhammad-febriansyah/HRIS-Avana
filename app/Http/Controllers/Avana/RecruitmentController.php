<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantBackgroundCheck;
use App\Models\ApplicantMedicalCheck;
use App\Models\Department;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentController extends Controller
{
    /**
     * Roles that may always manage recruitment within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

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
    private const STAGES = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];

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
     * Display job postings together with the applicant pipeline.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $postings = JobPosting::forTenant($tenantId)
            ->with('department:id,name')
            ->withCount('applicants')
            ->latest('id')
            ->get()
            ->map(fn (JobPosting $posting): array => $this->transformPosting($posting));

        $applicants = Applicant::forTenant($tenantId)
            ->with('jobPosting:id,title')
            ->latest('id')
            ->get()
            ->map(fn (Applicant $applicant): array => $this->transformApplicant($applicant));

        $pipeline = collect(self::STAGES)
            ->mapWithKeys(fn (string $stage): array => [
                $stage => $applicants->where('stage', $stage)->values()->all(),
            ])
            ->all();

        return Inertia::render('avana/rekrutmen/index', [
            'postings' => $postings,
            'pipeline' => $pipeline,
            'departments' => $this->departmentOptions($tenantId),
            'stages' => $this->stageOptions(),
            'kpis' => [
                'open_postings' => $postings->where('status', 'open')->count(),
                'total_applicants' => $applicants->count(),
                'hired' => $applicants->where('stage', 'hired')->count(),
                'in_process' => $applicants->whereNotIn('stage', ['hired', 'rejected'])->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new job posting.
     */
    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('avana/rekrutmen/create', [
            'departments' => $this->departmentOptions($request->user()->tenant_id),
        ]);
    }

    /**
     * Show the form for editing an existing job posting.
     */
    public function edit(Request $request, JobPosting $jobPosting): Response
    {
        $this->ensureCanManage($request);
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
        $this->ensureCanManage($request);

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
        $this->ensureCanManage($request);
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
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $jobPosting);

        $jobPosting->delete();

        return back()->with('success', 'Lowongan dihapus');
    }

    /**
     * Add an applicant to a job posting within the acting user's tenant.
     */
    public function storeApplicant(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

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
        ]);

        Applicant::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.rekrutmen')
            ->with('success', 'Pelamar berhasil ditambahkan');
    }

    /**
     * Move an applicant to a different pipeline stage.
     */
    public function moveStage(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $applicant);

        $data = $request->validate([
            'stage' => ['required', Rule::in(self::STAGES)],
        ]);

        $applicant->update(['stage' => $data['stage']]);

        return back()->with('success', 'Tahap pelamar diperbarui');
    }

    /**
     * Display the full candidate detail screen for a single applicant.
     */
    public function showApplicant(Request $request, Applicant $applicant): Response
    {
        $this->ensureCanManage($request);
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
        $this->ensureCanManage($request);
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
        $this->ensureCanManage($request);
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
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $applicant);

        $data = $request->validate([
            'interview_at' => ['required', 'date'],
        ]);

        $applicant->update([
            'interview_at' => $data['interview_at'],
            'stage' => 'interview',
        ]);

        return back()->with('success', 'Jadwal wawancara disimpan');
    }

    /**
     * Record a job offer and advance the applicant to the offer stage.
     */
    public function makeOffer(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $applicant);

        $data = $request->validate([
            'offer_note' => ['nullable', 'string'],
        ]);

        $applicant->update([
            'offer_note' => $data['offer_note'] ?? null,
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
        $this->ensureCanManage($request);
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
        $this->ensureCanManage($request);
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
        $labels = [
            'applied' => 'Melamar',
            'screening' => 'Seleksi',
            'interview' => 'Wawancara',
            'offer' => 'Penawaran',
            'hired' => 'Diterima',
            'rejected' => 'Ditolak',
        ];

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

<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Models\Applicant;
use App\Models\ApplicantBackgroundCheck;
use App\Models\ApplicantMedicalCheck;
use App\Models\ApplicantOnboardingItem;
use App\Models\ApplicantStatusLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\HeadcountRequest;
use App\Models\JobPosting;
use App\Models\Notification;
use App\Models\SalesOrder;
use App\Models\TalentPool;
use App\Models\User;
use App\Support\PrivateFile;
use App\Support\TenantQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * The default onboarding checklist seeded when an offer is accepted. Every
     * item must be completed before the candidate can be activated (stage 8).
     *
     * @var array<int, string>
     */
    private const ONBOARDING_DEFAULTS = [
        'Buat Akun Karyawan',
        'Buat Kontrak Kerja',
        'Siapkan Perangkat Kerja',
        'Orientasi Karyawan',
    ];

    /**
     * Record a stage change on an applicant's history trail.
     */
    private function logStatus(Applicant $applicant, ?string $from, string $to, ?string $note = null): void
    {
        ApplicantStatusLog::create([
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'from_stage' => $from,
            'to_stage' => $to,
            'note' => $note,
            'actor_id' => request()->user()?->id,
        ]);
    }

    /**
     * Notify every recruiter (a user holding recruitment.view) in the tenant.
     *
     * @param  array<string, mixed>  $data
     */
    private function notifyRecruiters(int $tenantId, string $type, string $title, string $body, array $data = []): void
    {
        try {
            $recipients = User::where('tenant_id', $tenantId)
                ->whereHas('roles.permissions', fn ($q) => $q->where('code', 'recruitment.view'))
                ->pluck('id');

            foreach ($recipients as $userId) {
                Notification::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'type' => $type,
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                ]);
            }
        } catch (\Throwable) {
            // A notification failure must never break the recruitment flow.
        }
    }

    /**
     * Send a plain-text email to a candidate (mail driver is "log" by default,
     * so this never hits an external SMTP in the demo).
     */
    private function mailCandidate(string $email, string $subject, string $body): void
    {
        try {
            Mail::raw($body, function ($message) use ($email, $subject): void {
                $message->to($email)->subject($subject);
            });
        } catch (\Throwable) {
            // Email delivery is best-effort; it must not break the request.
        }
    }

    /**
     * Seed the default onboarding checklist for an applicant (idempotent).
     */
    private function seedOnboarding(Applicant $applicant): void
    {
        if ($applicant->onboardingItems()->exists()) {
            return;
        }

        foreach (self::ONBOARDING_DEFAULTS as $order => $label) {
            ApplicantOnboardingItem::create([
                'tenant_id' => $applicant->tenant_id,
                'applicant_id' => $applicant->id,
                'label' => $label,
                'is_done' => false,
                'sort_order' => $order,
            ]);
        }
    }

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
                'route_key' => $a->public_id,
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
                ->with('jobPosting:id,title')
                ->orderByRaw('ai_confidence IS NULL')
                ->orderByDesc('ai_confidence')
                ->orderBy('name')
                ->get()
                ->map(fn (Applicant $a): array => [
                    'id' => $a->id,
                    'route_key' => $a->public_id,
                    'name' => $a->name,
                    'job_title' => $a->jobPosting?->title,
                    'position' => $a->position,
                    'stage' => $a->stage,
                    'email' => $a->email,
                    'phone' => $a->phone,
                    'source' => $a->source,
                    'applied_date' => $a->applied_date?->translatedFormat('d M Y'),
                    'interview_at' => $a->interview_at?->translatedFormat('d M Y, H:i'),
                    'confidence' => $a->ai_confidence,
                    'recommendation' => $a->ai_recommendation,
                    'reasoning' => $a->ai_reasoning,
                ])
                ->all(),
        ]);
    }

    /**
     * Stream an AI analysis of every applicant against the job they applied
     * for, emitting one NDJSON line per candidate as it is scored so the UI can
     * update live. Each line: {id, score, recommendation, reasoning}. The score
     * (0-100), recommendation and reasoning are also persisted. Per-candidate
     * failures are skipped, not fatal.
     */
    public function analyzeAiIntelligence(Request $request): StreamedResponse
    {
        $this->ensureCan($request, 'update');

        $tenantId = (int) $request->user()->tenant_id;
        $ai = AiSetting::current()->resolved();

        $hasKey = $ai['api_key'] !== '' || $ai['provider'] === 'ollama';

        if ($ai['api_key'] !== '') {
            config(["prism.providers.{$ai['provider']}.api_key" => $ai['api_key']]);
        }

        $schema = new ObjectSchema(
            'analisa_kandidat',
            'Hasil analisa kecocokan kandidat terhadap lowongan',
            [
                new NumberSchema('score', 'Skor kecocokan 0 sampai 100 (bilangan bulat)'),
                new StringSchema('recommendation', 'Rekomendasi singkat satu kalimat dalam Bahasa Indonesia'),
                new StringSchema('reasoning', 'Alasan penilaian: kekuatan dan kelemahan kandidat terhadap lowongan, 2-3 kalimat dalam Bahasa Indonesia'),
            ],
            ['score', 'recommendation', 'reasoning'],
        );

        $applicants = Applicant::forTenant($tenantId)
            ->with('jobPosting:id,title,description')
            ->get();

        $provider = $ai['provider'];
        $model = $ai['model'];

        return response()->stream(function () use ($applicants, $schema, $provider, $model, $hasKey): void {
            $emit = static function (array $payload): void {
                echo json_encode($payload)."\n";

                if (ob_get_level() > 0) {
                    @ob_flush();
                }

                flush();
            };

            if (! $hasKey) {
                $emit(['error' => 'API key AI belum dikonfigurasi. Atur di Pengaturan AI.', 'done' => true, 'analyzed' => 0]);

                return;
            }

            $emit(['total' => $applicants->count()]);
            $analyzed = 0;

            foreach ($applicants as $applicant) {
                try {
                    $job = $applicant->jobPosting;

                    $prompt = "Nilai kecocokan kandidat berikut untuk lowongan yang dilamar.\n\n"
                        ."KANDIDAT\n"
                        ."Nama: {$applicant->name}\n"
                        .'Posisi dilamar: '.($applicant->position ?? '-')."\n"
                        .'Sumber lamaran: '.($applicant->source ?? '-')."\n"
                        .'Tahap saat ini: '.($applicant->stage ?? '-')."\n\n"
                        ."LOWONGAN\n"
                        .'Judul: '.($job?->title ?? '-')."\n"
                        .'Deskripsi: '.Str::limit((string) ($job?->description ?? '-'), 600)."\n\n"
                        .'Beri skor kecocokan 0-100, rekomendasi singkat, dan alasan penilaian.';

                    $response = Prism::structured()
                        ->using($provider, $model)
                        ->withSchema($schema)
                        ->withSystemPrompt('Kamu asisten rekrutmen yang menilai kecocokan kandidat secara objektif berdasarkan posisi yang dilamar dan deskripsi lowongan. Selalu sertakan alasan yang konkret, bukan tebakan. Jawab ringkas dalam Bahasa Indonesia.')
                        ->withPrompt($prompt)
                        ->asStructured();

                    $data = $response->structured;

                    if (is_array($data) && isset($data['score'])) {
                        $score = max(0, min(100, (int) round((float) $data['score'])));
                        $recommendation = (string) ($data['recommendation'] ?? 'Analisa AI selesai.');
                        $reasoning = (string) ($data['reasoning'] ?? '');

                        $applicant->update([
                            'ai_confidence' => $score,
                            'ai_recommendation' => $recommendation,
                            'ai_reasoning' => $reasoning,
                        ]);
                        $analyzed++;

                        $emit(['id' => $applicant->id, 'score' => $score, 'recommendation' => $recommendation, 'reasoning' => $reasoning]);

                        continue;
                    }

                    $emit(['id' => $applicant->id, 'skipped' => true]);
                } catch (\Throwable $e) {
                    report($e);
                    $emit(['id' => $applicant->id, 'skipped' => true]);
                }
            }

            $emit(['done' => true, 'analyzed' => $analyzed]);
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
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

        $userId = (int) $request->user()->id;

        $rows = Applicant::forTenant($tenantId)
            ->whereNotNull('interview_at')
            ->with(['jobPosting:id,title', 'interviewer:id,name'])
            ->orderByDesc('interview_at')
            ->get()
            ->map(fn (Applicant $a): array => [
                'id' => $a->id,
                'route_key' => $a->public_id,
                'name' => $a->name,
                'job_title' => $a->jobPosting?->title,
                'type' => $a->interview_type,
                'status' => $a->interview_status ?? 'scheduled',
                'result' => $a->interview_result,
                'location' => $a->interview_location,
                'interviewer' => $a->interviewer?->name,
                // An interviewer answers for their own interviews without
                // holding recruitment.approve; everyone else needs it.
                'is_mine' => (int) $a->interviewer_id === $userId,
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
            ->with(['jobPosting:id,title', 'onboardingItems'])
            ->latest('offered_at')
            ->get()
            ->map(function (Applicant $a): array {
                $items = $a->onboardingItems->map(fn (ApplicantOnboardingItem $i): array => [
                    'id' => $i->id,
                    'label' => $i->label,
                    'is_done' => (bool) $i->is_done,
                ])->all();

                return [
                    'id' => $a->id,
                    'route_key' => $a->public_id,
                    'name' => $a->name,
                    'job_title' => $a->jobPosting?->title,
                    'salary' => $a->offer_salary !== null ? (float) $a->offer_salary : null,
                    'start_date' => $a->offer_start_date?->toDateString(),
                    'status' => $a->offer_status ?? 'draft',
                    'note' => $a->offer_note,
                    'activated' => $a->employee_id !== null,
                    'onboarding_items' => $items,
                    'onboarding_ready' => $items !== [] && collect($items)->every(fn ($i): bool => $i['is_done']),
                ];
            });

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

        // A validity date that nothing checks is decoration. Declining a lapsed
        // offer stays open — that is just closing the candidate out — but
        // accepting one has to be re-issued at terms somebody still stands by.
        if ($data['offer_status'] === 'accepted'
            && $applicant->offer_valid_until !== null
            && $applicant->offer_valid_until->isBefore(now()->startOfDay())) {
            return back()->withErrors([
                'offer_status' => 'Masa berlaku penawaran sudah lewat. Terbitkan penawaran baru.',
            ]);
        }

        $from = $applicant->stage;
        $applicant->update(['offer_status' => $data['offer_status']]);

        // Accepted → begin onboarding (stage stays "offer" until activation).
        // Declined/rejected → close the candidate out.
        if ($data['offer_status'] === 'accepted') {
            $this->seedOnboarding($applicant);
            $this->logStatus($applicant, $from, $applicant->stage, 'Penawaran diterima — onboarding dimulai');
        } elseif ($data['offer_status'] === 'rejected') {
            $applicant->update(['stage' => 'rejected']);
            $this->logStatus($applicant, $from, 'rejected', 'Penawaran ditolak kandidat');
        }

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

        // Business rule: candidates may only apply to an active (open) vacancy.
        $posting = JobPosting::forTenant($tenantId)->findOrFail($data['job_posting_id']);
        if ($posting->status !== 'open') {
            return back()->withErrors(['job_posting_id' => 'Lowongan ini sudah tidak aktif.']);
        }

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

        $this->logStatus($applicant, null, $applicant->stage, 'Lamaran masuk');
        $this->mailCandidate(
            $applicant->email,
            'Lamaran Anda diterima',
            "Halo {$applicant->name},\n\nLamaran Anda untuk posisi {$posting->title} telah kami terima.\nNomor lacak: {$applicant->tracking_number}.\n\nTerima kasih.",
        );

        return back()->with('success', 'Pelamar ditambahkan (No. '.$applicant->tracking_number.')');
    }

    /**
     * Stage 5 — Candidate Screening. HR records what the CV, the experience and
     * the skills were worth, then shortlists or rejects on the strength of it.
     *
     * Separate from {@see moveStage()} on purpose: dragging a card records that
     * somebody moved it and nothing about why. "Kenapa kandidat ini gugur?" is
     * the question this screen has to be able to answer months later.
     */
    public function recordScreening(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureTenantOwnership($request, $applicant);

        if (in_array($applicant->stage, ['hired', 'offer'], true)) {
            return back()->withErrors(['decision' => 'Kandidat sudah melewati tahap seleksi administrasi.']);
        }

        $data = $request->validate([
            'decision' => ['required', Rule::in(['shortlisted', 'rejected'])],
            'screening_note' => ['required', 'string', 'max:2000'],
            'screening_score' => ['nullable', 'integer', 'min:1', 'max:5'],
        ], [], [
            'screening_note' => 'catatan evaluasi',
            'screening_score' => 'nilai',
        ]);

        $from = $applicant->stage;

        $applicant->update([
            'screening_note' => $data['screening_note'],
            'screening_score' => $data['screening_score'] ?? null,
            'screened_by' => $request->user()->id,
            'screened_at' => now(),
            'stage' => $data['decision'],
        ]);

        $this->logStatus(
            $applicant,
            $from,
            $data['decision'],
            'Seleksi administrasi: '.$data['screening_note'],
        );

        if ($data['decision'] === 'rejected') {
            $this->mailCandidate(
                $applicant->email,
                'Hasil Seleksi Administrasi',
                "Halo {$applicant->name},\n\nTerima kasih atas lamaran Anda. Setelah kami tinjau, kami belum dapat melanjutkan proses Anda ke tahap berikutnya.\n\nSalam.",
            );
        }

        return back()->with('success', 'Evaluasi seleksi disimpan');
    }

    /**
     * Stage 6 — record the interviewer's verdict. Passed keeps the candidate in
     * the interview stage ready for an offer; Failed moves them to rejected.
     */
    public function recordInterviewResult(Request $request, Applicant $applicant): RedirectResponse
    {
        // The spec makes the Interviewer the one who fills the result in, and
        // an interviewer is rarely a recruiter — asking for recruitment.approve
        // meant the person who ran the interview could be invited to it and
        // then be unable to say how it went. Being the assigned interviewer is
        // its own licence, for that candidate only.
        $isAssignedInterviewer = (int) $applicant->interviewer_id === (int) $request->user()->id;

        if (! $isAssignedInterviewer) {
            $this->ensureCan($request, 'approve');
        }

        $this->ensureTenantOwnership($request, $applicant);

        if ($applicant->stage !== 'interview') {
            return back()->withErrors(['interview_result' => 'Kandidat belum berada pada tahap wawancara.']);
        }

        $data = $request->validate([
            'interview_result' => ['required', Rule::in(['passed', 'failed'])],
            'interview_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $failed = $data['interview_result'] === 'failed';
        $from = $applicant->stage;

        $applicant->update([
            'interview_result' => $data['interview_result'],
            'interview_status' => 'completed',
            'stage' => $failed ? 'rejected' : $applicant->stage,
        ]);

        $verdict = $failed ? 'Wawancara: Failed' : 'Wawancara: Passed';
        $note = trim((string) ($data['interview_note'] ?? ''));

        $this->logStatus(
            $applicant,
            $from,
            $failed ? 'rejected' : $applicant->stage,
            $note === '' ? $verdict : $verdict.' — '.$note,
        );

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

        // Hiring is a headcount addition, so it spends the same package allowance
        // as adding an employee by hand. Checked before the onboarding checklist:
        // there is no point walking someone through it when there is no seat.
        TenantQuota::assertRoom($request->user()->tenant, 'employees', 1, 'applicant');

        // Business rule: Employee is activated only after the full onboarding
        // checklist is complete. Seed it on first attempt if it is missing.
        $this->seedOnboarding($applicant);
        if ($applicant->onboardingItems()->where('is_done', false)->exists()) {
            return back()->withErrors(['applicant' => 'Selesaikan seluruh checklist onboarding sebelum aktivasi.']);
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

        $from = $applicant->stage;
        $applicant->update([
            'employee_id' => $employee->id,
            'stage' => 'hired',
            'onboarded_at' => now(),
        ]);

        $this->logStatus($applicant, $from, 'hired', 'Diaktivasi menjadi karyawan '.$employee->employee_number);
        $this->mailCandidate(
            $applicant->email,
            'Selamat Bergabung',
            "Halo {$applicant->name},\n\nSelamat! Anda resmi menjadi karyawan dengan nomor {$employee->employee_number}.\n\nSampai jumpa di hari pertama Anda.",
        );

        return back()->with('success', 'Kandidat diaktivasi menjadi karyawan ('.$employee->employee_number.')');
    }

    /**
     * Toggle an onboarding checklist item done/undone (stage 8 prerequisite).
     */
    public function toggleOnboardingItem(Request $request, ApplicantOnboardingItem $item): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        abort_if((int) $item->tenant_id !== (int) $request->user()->tenant_id, 404);

        $item->update([
            'is_done' => ! $item->is_done,
            'done_at' => ! $item->is_done ? now() : null,
        ]);

        return back()->with('success', 'Checklist onboarding diperbarui');
    }

    /**
     * Seed the onboarding checklist for an accepted candidate on demand.
     */
    public function startOnboarding(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $applicant);

        if ($applicant->offer_status !== 'accepted') {
            return back()->withErrors(['applicant' => 'Onboarding hanya untuk kandidat dengan penawaran diterima.']);
        }

        $this->seedOnboarding($applicant);

        return back()->with('success', 'Checklist onboarding disiapkan');
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

        $from = $applicant->stage;
        $applicant->update(['stage' => $data['stage']]);

        if ($from !== $data['stage']) {
            $this->logStatus($applicant, $from, $data['stage']);
        }

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
            'interviewer:id,name',
            'screenedBy:id,name',
            'medicalChecks' => fn ($query) => $query->latest('id'),
            'backgroundChecks' => fn ($query) => $query->latest('id'),
            'statusLogs.actor:id,name',
            'onboardingItems',
        ]);

        $tenantId = (int) $applicant->tenant_id;

        return Inertia::render('avana/rekrutmen/candidate', [
            'applicant' => $this->transformApplicantDetail($applicant),
            'stages' => $this->stageOptions(),
            'interviewers' => User::where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name])
                ->all(),
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

        PrivateFile::delete($applicant->cv_path);

        $path = PrivateFile::store($request->file('cv'), "recruitment/cv/{$applicant->tenant_id}");
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

        // Business rule: a candidate must clear screening before an interview.
        if (! in_array($applicant->stage, ['screening', 'shortlisted', 'interview'], true)) {
            return back()->withErrors(['interview_at' => 'Kandidat harus melalui screening (shortlist) sebelum dijadwalkan wawancara.']);
        }

        $tenantId = (int) $applicant->tenant_id;

        $data = $request->validate([
            'interview_at' => ['required', 'date'],
            'interview_type' => ['nullable', Rule::in(['hr', 'technical', 'user', 'final'])],
            'interview_location' => ['nullable', 'string', 'max:255'],
            'interviewer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ]);

        // Business rule: an interviewer's schedule must not clash (within 1 hour).
        if (! empty($data['interviewer_id'])) {
            $at = Carbon::parse($data['interview_at']);
            $clash = Applicant::forTenant($tenantId)
                ->where('interviewer_id', $data['interviewer_id'])
                ->where('id', '!=', $applicant->id)
                ->whereNotNull('interview_at')
                ->whereBetween('interview_at', [$at->copy()->subMinutes(59), $at->copy()->addMinutes(59)])
                ->exists();

            if ($clash) {
                return back()->withErrors(['interview_at' => 'Jadwal interviewer bentrok dengan wawancara lain.']);
            }
        }

        $from = $applicant->stage;
        $applicant->update([
            'interview_at' => $data['interview_at'],
            'interview_type' => $data['interview_type'] ?? 'hr',
            'interview_location' => $data['interview_location'] ?? null,
            'interviewer_id' => $data['interviewer_id'] ?? null,
            'interview_status' => 'scheduled',
            'interview_result' => null,
            'stage' => 'interview',
        ]);

        $this->logStatus($applicant, $from, 'interview', 'Wawancara dijadwalkan');

        // Auto-invitation: notify the interviewer and email the candidate.
        if (! empty($data['interviewer_id'])) {
            try {
                Notification::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $data['interviewer_id'],
                    'type' => 'interview_invitation',
                    'title' => 'Undangan wawancara',
                    'body' => 'Wawancara dengan '.$applicant->name.' pada '.Carbon::parse($data['interview_at'])->format('d M Y H:i'),
                    'data' => ['applicant_id' => $applicant->id],
                ]);
            } catch (\Throwable) {
                // best-effort
            }
        }
        $this->mailCandidate(
            $applicant->email,
            'Undangan Wawancara',
            "Halo {$applicant->name},\n\nAnda dijadwalkan wawancara pada ".Carbon::parse($data['interview_at'])->format('d M Y H:i').".\n\nTerima kasih.",
        );

        return back()->with('success', 'Jadwal wawancara disimpan');
    }

    /**
     * Record a job offer and advance the applicant to the offer stage.
     */
    public function makeOffer(Request $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $applicant);

        // Business rule: only a candidate who passed the interview gets an offer.
        if ($applicant->interview_result !== 'passed') {
            return back()->withErrors(['offer_salary' => 'Penawaran hanya untuk kandidat yang lulus wawancara.']);
        }

        $data = $request->validate([
            'offer_note' => ['nullable', 'string'],
            'offer_salary' => ['nullable', 'numeric', 'min:0'],
            'offer_start_date' => ['nullable', 'date'],
            'offer_benefit' => ['nullable', 'string'],
            // "Offer memiliki masa berlaku" — an offer nobody has to answer by
            // is not an offer, and a candidate sitting on one indefinitely
            // blocks the vacancy behind them.
            'offer_valid_until' => ['required', 'date', 'after_or_equal:today'],
        ], [], ['offer_valid_until' => 'masa berlaku penawaran']);

        $from = $applicant->stage;
        $applicant->update([
            'offer_note' => $data['offer_note'] ?? null,
            'offer_salary' => $data['offer_salary'] ?? null,
            'offer_start_date' => $data['offer_start_date'] ?? null,
            'offer_benefit' => $data['offer_benefit'] ?? null,
            'offer_valid_until' => $data['offer_valid_until'] ?? null,
            'offer_status' => 'sent',
            'offered_at' => now(),
            'stage' => 'offer',
        ]);

        $this->logStatus($applicant, $from, 'offer', 'Penawaran dikirim');
        $this->mailCandidate(
            $applicant->email,
            'Penawaran Kerja',
            "Halo {$applicant->name},\n\nKami dengan senang hati menawarkan posisi kepada Anda. Silakan tinjau penawaran ini.\n\nTerima kasih.",
        );

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
            ? PrivateFile::store($request->file('document'), "recruitment/medical/{$applicant->tenant_id}")
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
            ? PrivateFile::store($request->file('document'), "recruitment/background/{$applicant->tenant_id}")
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
            'route_key' => $applicant->public_id,
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
            'route_key' => $applicant->public_id,
            'name' => $applicant->name,
            'email' => $applicant->email,
            'phone' => $applicant->phone,
            'source' => $applicant->source,
            'stage' => $applicant->stage,
            'blacklisted' => (bool) $applicant->blacklisted,
            'blacklist_reason' => $applicant->blacklist_reason,
            'position' => $applicant->position ?? $applicant->jobPosting?->title,
            'photo_url' => PrivateFile::urlFor($applicant->photo_path),
            'linkedin_url' => $applicant->linkedin_url,
            'portfolio_url' => $applicant->portfolio_url,
            'cv_url' => PrivateFile::urlFor($applicant->cv_path),
            'notes' => $applicant->notes,
            'screening_note' => $applicant->screening_note,
            'screening_score' => $applicant->screening_score,
            'screened_by' => $applicant->screenedBy?->name,
            'screened_at' => $applicant->screened_at?->toDateTimeString(),
            'applied_date' => $applicant->applied_date?->toDateString(),
            'interview_at' => $applicant->interview_at?->toDateTimeString(),
            'interview_result' => $applicant->interview_result,
            'interviewer' => $applicant->interviewer?->name,
            'interviewer_id' => $applicant->interviewer_id,
            'offered_at' => $applicant->offered_at?->toDateTimeString(),
            'offer_note' => $applicant->offer_note,
            'offer_benefit' => $applicant->offer_benefit,
            'offer_salary' => $applicant->offer_salary !== null ? (float) $applicant->offer_salary : null,
            'offer_start_date' => $applicant->offer_start_date?->toDateString(),
            'offer_valid_until' => $applicant->offer_valid_until?->toDateString(),
            'offer_status' => $applicant->offer_status,
            'tracking_number' => $applicant->tracking_number,
            'job_posting_id' => $applicant->job_posting_id,
            'job_title' => $applicant->jobPosting?->title,
            'employment_type' => $applicant->jobPosting?->employment_type,
            'status_logs' => $applicant->statusLogs->map(fn (ApplicantStatusLog $log): array => [
                'id' => $log->id,
                'from_stage' => $log->from_stage,
                'to_stage' => $log->to_stage,
                'note' => $log->note,
                'actor' => $log->actor?->name,
                'at' => $log->created_at?->toDateTimeString(),
            ])->all(),
            'onboarding_items' => $applicant->onboardingItems->map(fn (ApplicantOnboardingItem $i): array => [
                'id' => $i->id,
                'label' => $i->label,
                'is_done' => (bool) $i->is_done,
            ])->all(),
            'medical_checks' => $applicant->medicalChecks->map(fn (ApplicantMedicalCheck $check): array => [
                'id' => $check->id,
                'title' => $check->title,
                'status' => $check->status,
                'notes' => $check->notes,
                'checked_at' => $check->checked_at?->toDateString(),
                'file_url' => PrivateFile::urlFor($check->file_path),
            ])->all(),
            'background_checks' => $applicant->backgroundChecks->map(fn (ApplicantBackgroundCheck $check): array => [
                'id' => $check->id,
                'check_type' => $check->check_type,
                'status' => $check->status,
                'notes' => $check->notes,
                'requested_at' => $check->requested_at?->toDateString(),
                'file_url' => PrivateFile::urlFor($check->file_path),
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
            'route_key' => $applicant->public_id,
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

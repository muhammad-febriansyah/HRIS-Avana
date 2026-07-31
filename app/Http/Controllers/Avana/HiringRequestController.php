<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Department;
use App\Models\HiringRequest;
use App\Models\HiringRequestItem;
use App\Models\Notification;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stage 1 (Hiring Request) and Stage 9 (Candidate Progress Tracking) of the
 * recruitment flow. A Hiring Manager submits a manpower need here; HR turns it
 * into a Recruitment Requisition (see {@see RecruitmentRequisitionController}).
 * The progress screen is view-only monitoring for the requesting manager.
 */
class HiringRequestController extends Controller
{
    private const MODULE = 'recruitment';

    /**
     * @var array<int, string>
     */
    private const EMPLOYMENT_TYPES = ['tetap', 'kontrak', 'magang', 'harian'];

    /**
     * @var array<int, string>
     */
    private const STATUSES = ['open', 'in_process', 'closed'];

    /**
     * List hiring requests. HR sees all; a plain hiring manager sees only the
     * requests they raised.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $query = HiringRequest::forTenant($tenantId)
            ->with([
                'requester:id,name',
                'items.department:id,name',
                'items.position:id,name',
            ])
            ->withCount('requisitions')
            ->latest('id');

        if (! $this->isRecruiter($request)) {
            $query->where('requester_id', $request->user()->id);
        }

        $requests = $query->get()->map(fn (HiringRequest $r): array => $this->shape($r));

        return Inertia::render('avana/rekrutmen/hiring-request', [
            'requests' => $requests,
            'departments' => $this->departmentOptions($tenantId),
            'positions' => $this->positionOptions($tenantId),
            'employmentTypes' => self::EMPLOYMENT_TYPES,
            'canManage' => $this->isRecruiter($request),
            'kpis' => [
                'open' => $requests->where('status', 'open')->count(),
                'in_process' => $requests->where('status', 'in_process')->count(),
                'total' => $requests->count(),
            ],
        ]);
    }

    /**
     * Create a new hiring request under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;
        $data = $this->validatePayload($request, $tenantId);

        $hiringRequest = DB::transaction(function () use ($data, $tenantId, $request): HiringRequest {
            $hiringRequest = HiringRequest::create([
                'tenant_id' => $tenantId,
                'requester_id' => $request->user()->id,
                'status' => 'open',
            ]);

            $hiringRequest->update([
                'request_number' => 'HR-'.$hiringRequest->created_at->format('Y').'-'.str_pad((string) $hiringRequest->id, 4, '0', STR_PAD_LEFT),
            ]);

            $this->syncItems($hiringRequest, $data['items']);

            return $hiringRequest;
        });

        $this->notifyRecruiters($tenantId, $request->user()->id, $hiringRequest->fresh('items'));

        return back()->with('success', 'Hiring Request dibuat ('.$hiringRequest->request_number.')');
    }

    /**
     * Notify HR recruiters (users holding recruitment.view) that a new hiring
     * request needs processing. Best-effort — never breaks the request.
     */
    private function notifyRecruiters(int $tenantId, int $excludeUserId, HiringRequest $hiringRequest): void
    {
        try {
            $recipients = User::where('tenant_id', $tenantId)
                ->where('id', '!=', $excludeUserId)
                ->whereHas('roles.permissions', fn ($q) => $q->where('code', self::MODULE.'.view'))
                ->pluck('id');

            $needs = $hiringRequest->items
                ->map(fn (HiringRequestItem $item): string => $item->position_title.' ('.$item->vacancy.')')
                ->join(', ');

            foreach ($recipients as $userId) {
                Notification::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'type' => 'hiring_request',
                    'title' => 'Hiring Request baru',
                    'body' => $hiringRequest->request_number.' — '.$needs,
                    'data' => ['hiring_request_id' => $hiringRequest->id],
                ]);
            }
        } catch (\Throwable) {
            // best-effort
        }
    }

    /**
     * Update a still-open hiring request.
     */
    public function update(Request $request, HiringRequest $hiringRequest): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureOwnership($request, $hiringRequest);

        $data = $this->validatePayload($request, (int) $request->user()->tenant_id);

        // A need already picked up by HR must not vanish under them, so the
        // rewrite refuses to drop any line a requisition was raised from.
        $answered = $hiringRequest->requisitions()
            ->whereNotNull('hiring_request_item_id')
            ->pluck('hiring_request_item_id')
            ->all();

        $kept = collect($data['items'])->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();
        $dropped = array_diff($answered, $kept);

        if ($dropped !== []) {
            return back()->withErrors([
                'items' => 'Kebutuhan yang sudah dibuatkan requisition tidak dapat dihapus.',
            ]);
        }

        DB::transaction(fn () => $this->syncItems($hiringRequest, $data['items']));

        return back()->with('success', 'Hiring Request diperbarui');
    }

    /**
     * Mark a hiring request as closed (no further requisitions).
     */
    public function close(Request $request, HiringRequest $hiringRequest): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureOwnership($request, $hiringRequest);

        $hiringRequest->update(['status' => 'closed']);

        return back()->with('success', 'Hiring Request ditutup');
    }

    /**
     * Delete a hiring request.
     */
    public function destroy(Request $request, HiringRequest $hiringRequest): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureOwnership($request, $hiringRequest);

        $hiringRequest->delete();

        return back()->with('success', 'Hiring Request dihapus');
    }

    /**
     * Stage 9 — Candidate Progress Tracking. View-only monitoring of the
     * candidates flowing from a manager's own hiring requests.
     */
    public function progress(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $query = HiringRequest::forTenant($tenantId)
            ->with([
                'items.department:id,name',
                'requisitions:id,hiring_request_id,hiring_request_item_id,job_posting_id,requisition_number,status',
                'requisitions.jobPosting:id,title',
            ])
            ->latest('id');

        if (! $this->isRecruiter($request)) {
            $query->where('requester_id', $request->user()->id);
        }

        $requests = $query->get();

        $jobPostingIds = $requests
            ->flatMap(fn (HiringRequest $r) => $r->requisitions->pluck('job_posting_id'))
            ->filter()
            ->unique()
            ->values();

        $applicantsByPosting = Applicant::forTenant($tenantId)
            ->whereIn('job_posting_id', $jobPostingIds)
            ->get(['id', 'name', 'job_posting_id', 'stage', 'updated_at'])
            ->groupBy('job_posting_id');

        $labels = self::stageLabels();

        // Candidates hang off a need, not off the request as a whole: a request
        // for two engineers and a designer would otherwise pool everyone into
        // one undifferentiated list.
        $rows = $requests->map(fn (HiringRequest $r): array => [
            'id' => $r->id,
            'request_number' => $r->request_number,
            'status' => $r->status,
            'vacancy' => $r->totalVacancy(),
            'needs' => $r->items->map(fn (HiringRequestItem $item): array => [
                'id' => $item->id,
                'position_title' => $item->position_title,
                'department' => $item->department?->name,
                'vacancy' => $item->vacancy,
                'candidates' => $r->requisitions
                    ->where('hiring_request_item_id', $item->id)
                    ->flatMap(fn ($req) => ($applicantsByPosting[$req->job_posting_id] ?? collect())
                        ->map(fn (Applicant $a): array => [
                            'id' => $a->id,
                            'name' => $a->name,
                            'stage' => $a->stage,
                            'stage_label' => $labels[$a->stage] ?? $a->stage,
                            'last_update' => $a->updated_at?->toDateString(),
                        ]))
                    ->values()
                    ->all(),
            ])->values()->all(),
        ]);

        return Inertia::render('avana/rekrutmen/progress', [
            'requests' => $rows,
        ]);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function validatePayload(Request $request, int $tenantId): array
    {
        return $request->validate([
            // One line per manpower need — a request may carry several.
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.id' => ['nullable', 'integer', Rule::exists('hiring_request_items', 'id')->where('tenant_id', $tenantId)],
            'items.*.position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('tenant_id', $tenantId)],
            'items.*.position_title' => ['required', 'string', 'max:255'],
            'items.*.department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'items.*.vacancy' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.job_description' => ['nullable', 'string'],
            'items.*.qualification' => ['nullable', 'string'],
            'items.*.employment_type' => ['required', Rule::in(self::EMPLOYMENT_TYPES)],
            'items.*.target_join_date' => ['nullable', 'date'],
        ], [], [
            'items' => 'kebutuhan tenaga kerja',
            'items.*.position_title' => 'posisi',
            'items.*.vacancy' => 'jumlah kebutuhan',
        ]);
    }

    /**
     * Write the request's needs, keeping the rows that already exist so a
     * requisition raised against one keeps pointing at it.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(HiringRequest $hiringRequest, array $items): void
    {
        $keep = [];

        foreach (array_values($items) as $order => $item) {
            $attributes = [
                'tenant_id' => $hiringRequest->tenant_id,
                'position_id' => $item['position_id'] ?? null,
                'position_title' => $item['position_title'],
                'department_id' => $item['department_id'] ?? null,
                'vacancy' => $item['vacancy'],
                'job_description' => $item['job_description'] ?? null,
                'qualification' => $item['qualification'] ?? null,
                'employment_type' => $item['employment_type'],
                'target_join_date' => $item['target_join_date'] ?? null,
                'sort_order' => $order,
            ];

            $existing = ! empty($item['id'])
                ? $hiringRequest->items()->whereKey($item['id'])->first()
                : null;

            if ($existing !== null) {
                $existing->update($attributes);
                $keep[] = $existing->id;

                continue;
            }

            $keep[] = $hiringRequest->items()->create($attributes)->id;
        }

        $hiringRequest->items()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(HiringRequest $r): array
    {
        return [
            'id' => $r->id,
            'request_number' => $r->request_number,
            'requester' => $r->requester?->name,
            'status' => $r->status,
            'vacancy' => $r->totalVacancy(),
            'requisitions_count' => $r->requisitions_count,
            'created_at' => $r->created_at?->format('d M Y'),
            'items' => $r->items->map(fn (HiringRequestItem $i): array => [
                'id' => $i->id,
                'position_id' => $i->position_id,
                'position_title' => $i->position_title,
                'department_id' => $i->department_id,
                'department' => $i->department?->name,
                'vacancy' => $i->vacancy,
                'job_description' => $i->job_description,
                'qualification' => $i->qualification,
                'employment_type' => $i->employment_type,
                'target_join_date' => $i->target_join_date?->toDateString(),
            ])->all(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, department_id: int|null}>
     */
    private function positionOptions(int $tenantId): array
    {
        return Position::forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'department_id'])
            ->map(fn (Position $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'department_id' => $p->department_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function departmentOptions(int $tenantId): array
    {
        return Department::forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $d): array => ['id' => $d->id, 'name' => $d->name])
            ->all();
    }

    /**
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
     * Whether the acting user manages recruitment (sees every tenant request),
     * as opposed to a hiring manager who only sees their own.
     */
    private function isRecruiter(Request $request): bool
    {
        /** @var User $user */
        $user = $request->user();

        return $user->isSuperAdmin() || $user->hasPermissionTo(self::MODULE.'.approve');
    }

    /**
     * A hiring manager may only touch their own request; a recruiter any.
     */
    private function ensureOwnership(Request $request, HiringRequest $hiringRequest): void
    {
        abort_if((int) $hiringRequest->tenant_id !== (int) $request->user()->tenant_id, 404);

        if (! $this->isRecruiter($request)) {
            abort_if((int) $hiringRequest->requester_id !== (int) $request->user()->id, 403);
        }
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

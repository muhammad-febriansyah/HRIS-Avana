<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\HiringRequest;
use App\Models\HiringRequestItem;
use App\Models\JobPosting;
use App\Models\RecruitmentRequisition;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stage 2 (Recruitment Requisition) and Stage 3 (Publish Vacancy). HR turns an
 * approved Hiring Request into a requisition, then publishes it — which spawns
 * the live vacancy ({@see JobPosting}) that candidates apply to.
 */
class RecruitmentRequisitionController extends Controller
{
    private const MODULE = 'recruitment';

    /**
     * @var array<int, string>
     */
    private const EMPLOYMENT_TYPES = ['tetap', 'kontrak', 'magang', 'harian'];

    /**
     * List requisitions plus the open hiring requests they can be created from.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $requisitions = RecruitmentRequisition::forTenant($tenantId)
            ->with([
                'hiringRequest:id,request_number',
                'hiringRequestItem:id,position_title',
                'department:id,name',
                'jobPosting:id,title',
            ])
            ->latest('id')
            ->get()
            ->map(fn (RecruitmentRequisition $r): array => $this->shape($r));

        // One option per need, not per request: a request carrying three needs
        // is three separate things HR can raise a requisition for.
        $hiringRequests = HiringRequest::forTenant($tenantId)
            ->whereIn('status', ['open', 'in_process'])
            ->with(['items.department:id,name'])
            ->latest('id')
            ->get()
            ->flatMap(fn (HiringRequest $h) => $h->items->map(fn (HiringRequestItem $i): array => [
                'id' => $h->id,
                'item_id' => $i->id,
                'request_number' => $h->request_number,
                'position_title' => $i->position_title,
                'department_id' => $i->department_id,
                'department' => $i->department?->name,
                'vacancy' => $i->vacancy,
                'qualification' => $i->qualification,
                'job_description' => $i->job_description,
                'employment_type' => $i->employment_type,
            ]))
            ->values();

        return Inertia::render('avana/rekrutmen/requisition', [
            'requisitions' => $requisitions,
            'hiringRequestItems' => $hiringRequests,
            'departments' => $this->departmentOptions($tenantId),
            'employmentTypes' => self::EMPLOYMENT_TYPES,
            'kpis' => [
                'draft' => $requisitions->where('status', 'draft')->count(),
                'published' => $requisitions->where('status', 'published')->count(),
                'total' => $requisitions->count(),
            ],
        ]);
    }

    /**
     * Create a requisition from a hiring request (stage 2). The source hiring
     * request moves to "in_process".
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'hiring_request_id' => ['required', 'integer', Rule::exists('hiring_requests', 'id')->where('tenant_id', $tenantId)],
            'hiring_request_item_id' => ['required', 'integer', Rule::exists('hiring_request_items', 'id')->where('tenant_id', $tenantId)],
            'position_title' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'vacancy' => ['required', 'integer', 'min:1', 'max:999'],
            'qualification' => ['nullable', 'string'],
            'job_description' => ['nullable', 'string'],
            'employment_type' => ['required', Rule::in(self::EMPLOYMENT_TYPES)],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        // The need has to belong to the request it is filed under, and a closed
        // request is done being worked — neither is worth a requisition.
        $source = HiringRequest::forTenant($tenantId)->findOrFail($data['hiring_request_id']);

        if ($source->status === 'closed') {
            return back()->withErrors(['hiring_request_id' => 'Hiring Request ini sudah ditutup.']);
        }

        $belongs = $source->items()->whereKey($data['hiring_request_item_id'])->exists();

        if (! $belongs) {
            return back()->withErrors(['hiring_request_item_id' => 'Kebutuhan tersebut bukan milik Hiring Request ini.']);
        }

        $requisition = DB::transaction(function () use ($data, $tenantId) {
            $requisition = RecruitmentRequisition::create([
                ...$data,
                'tenant_id' => $tenantId,
                'status' => 'draft',
            ]);

            $requisition->update([
                'requisition_number' => 'REQ-'.$requisition->created_at->format('Y').'-'.str_pad((string) $requisition->id, 4, '0', STR_PAD_LEFT),
            ]);

            HiringRequest::whereKey($data['hiring_request_id'])
                ->where('status', 'open')
                ->update(['status' => 'in_process']);

            return $requisition;
        });

        return back()->with('success', 'Recruitment Requisition dibuat ('.$requisition->requisition_number.')');
    }

    /**
     * Update a draft requisition.
     */
    public function update(Request $request, RecruitmentRequisition $requisition): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenant($request, $requisition);

        abort_if($requisition->status !== 'draft', 422, 'Hanya requisition draft yang dapat diubah.');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'position_title' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'vacancy' => ['required', 'integer', 'min:1', 'max:999'],
            'qualification' => ['nullable', 'string'],
            'job_description' => ['nullable', 'string'],
            'employment_type' => ['required', Rule::in(self::EMPLOYMENT_TYPES)],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $requisition->update($data);

        return back()->with('success', 'Requisition diperbarui');
    }

    /**
     * Publish a requisition (stage 3): mark published, set the vacancy window,
     * and spawn the live JobPosting candidates apply to.
     */
    public function publish(Request $request, RecruitmentRequisition $requisition): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenant($request, $requisition);

        abort_if($requisition->status !== 'draft', 422, 'Requisition ini sudah dipublikasikan.');

        $data = $request->validate([
            'publish_date' => ['required', 'date'],
            'closing_date' => ['required', 'date', 'after_or_equal:publish_date'],
        ]);

        DB::transaction(function () use ($requisition, $data): void {
            $posting = JobPosting::create([
                'tenant_id' => $requisition->tenant_id,
                'department_id' => $requisition->department_id,
                'recruitment_requisition_id' => $requisition->id,
                'title' => $requisition->position_title,
                'location' => $requisition->location,
                'employment_type' => $requisition->employment_type,
                'quota' => $requisition->vacancy,
                'status' => 'open',
                'description' => $requisition->job_description,
                'posted_date' => $data['publish_date'],
                'closing_date' => $data['closing_date'],
            ]);

            $requisition->update([
                'status' => 'published',
                'publish_date' => $data['publish_date'],
                'closing_date' => $data['closing_date'],
                'job_posting_id' => $posting->id,
            ]);
        });

        return back()->with('success', 'Lowongan dipublikasikan');
    }

    /**
     * Delete a draft requisition.
     */
    public function destroy(Request $request, RecruitmentRequisition $requisition): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenant($request, $requisition);

        abort_if($requisition->status === 'published', 422, 'Requisition yang sudah publish tidak dapat dihapus.');

        $requisition->delete();

        return back()->with('success', 'Requisition dihapus');
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(RecruitmentRequisition $r): array
    {
        return [
            'id' => $r->id,
            'requisition_number' => $r->requisition_number,
            'hiring_request_number' => $r->hiringRequest?->request_number,
            'hiring_request_item_id' => $r->hiring_request_item_id,
            // Which of that request's needs this answers — the request number
            // alone no longer identifies one.
            'hiring_request_need' => $r->hiringRequestItem?->position_title,
            'position_title' => $r->position_title,
            'department_id' => $r->department_id,
            'department' => $r->department?->name,
            'vacancy' => $r->vacancy,
            'qualification' => $r->qualification,
            'job_description' => $r->job_description,
            'employment_type' => $r->employment_type,
            'location' => $r->location,
            'status' => $r->status,
            'publish_date' => $r->publish_date?->toDateString(),
            'closing_date' => $r->closing_date?->toDateString(),
            'job_posting_id' => $r->job_posting_id,
            'job_title' => $r->jobPosting?->title,
        ];
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

    private function ensureTenant(Request $request, RecruitmentRequisition $requisition): void
    {
        abort_if((int) $requisition->tenant_id !== (int) $request->user()->tenant_id, 404);
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

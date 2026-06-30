<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CrmController extends Controller
{
    /**
     * Roles that may always manage the CRM pipeline within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Allowed deal pipeline stages, in display order.
     *
     * @var array<int, string>
     */
    private const STAGES = ['lead', 'qualified', 'proposal', 'won', 'lost'];

    /**
     * Display the sales pipeline grouped by stage with its contacts.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $deals = CrmDeal::forTenant($tenantId)
            ->with(['contact:id,name,company', 'owner:id,full_name'])
            ->latest('id')
            ->get()
            ->map(fn (CrmDeal $deal): array => $this->transformDeal($deal));

        $pipeline = collect(self::STAGES)
            ->mapWithKeys(fn (string $stage): array => [
                $stage => $deals->where('stage', $stage)->values()->all(),
            ])
            ->all();

        $contacts = CrmContact::forTenant($tenantId)
            ->withCount('deals')
            ->orderBy('name')
            ->get()
            ->map(fn (CrmContact $contact): array => $this->transformContact($contact));

        return Inertia::render('avana/crm/index', [
            'pipeline' => $pipeline,
            'contacts' => $contacts,
            'contactOptions' => $contacts->map(fn (array $contact): array => [
                'id' => $contact['id'],
                'name' => $contact['name'],
            ])->all(),
            'owners' => $this->ownerOptions($tenantId),
            'stages' => $this->stageOptions(),
            'kpis' => [
                'total_deals' => $deals->count(),
                'pipeline_value' => (float) $deals->whereNotIn('stage', ['won', 'lost'])->sum('value'),
                'won_value' => (float) $deals->where('stage', 'won')->sum('value'),
            ],
        ]);
    }

    /**
     * Persist a new CRM contact under the acting user's tenant.
     */
    public function storeContact(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        CrmContact::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.crm')
            ->with('success', 'Kontak berhasil ditambahkan');
    }

    /**
     * Persist a new deal under the acting user's tenant.
     */
    public function storeDeal(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateDeal($request, $tenantId);

        CrmDeal::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.crm')
            ->with('success', 'Deal berhasil ditambahkan');
    }

    /**
     * Update an existing deal.
     */
    public function updateDeal(Request $request, CrmDeal $deal): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $deal);

        $data = $this->validateDeal($request, $request->user()->tenant_id);

        $deal->update($data);

        return redirect()->route('avana.crm')
            ->with('success', 'Deal berhasil diperbarui');
    }

    /**
     * Move a deal to a different pipeline stage.
     */
    public function moveStage(Request $request, CrmDeal $deal): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $deal);

        $data = $request->validate([
            'stage' => ['required', Rule::in(self::STAGES)],
        ]);

        $deal->update(['stage' => $data['stage']]);

        return back()->with('success', 'Tahap deal diperbarui');
    }

    /**
     * Delete a deal.
     */
    public function destroyDeal(Request $request, CrmDeal $deal): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $deal);

        $deal->delete();

        return back()->with('success', 'Deal dihapus');
    }

    /**
     * Validate the create/update payload for a deal.
     *
     * @return array<string, mixed>
     */
    private function validateDeal(Request $request, ?int $tenantId): array
    {
        return $request->validate([
            'contact_id' => ['nullable', 'integer', Rule::exists('crm_contacts', 'id')->where('tenant_id', $tenantId)],
            'title' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0'],
            'stage' => ['required', Rule::in(self::STAGES)],
            'owner_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'expected_close' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * Build the card shape consumed by the pipeline board.
     *
     * @return array<string, mixed>
     */
    private function transformDeal(CrmDeal $deal): array
    {
        return [
            'id' => $deal->id,
            'title' => $deal->title,
            'value' => (float) $deal->value,
            'stage' => $deal->stage,
            'contact_id' => $deal->contact_id,
            'contact' => $deal->contact?->name,
            'company' => $deal->contact?->company,
            'owner_id' => $deal->owner_id,
            'owner' => $deal->owner?->full_name,
            'expected_close' => $deal->expected_close?->toDateString(),
            'notes' => $deal->notes,
        ];
    }

    /**
     * Build the row shape consumed by the contacts list.
     *
     * @return array<string, mixed>
     */
    private function transformContact(CrmContact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'company' => $contact->company,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'notes' => $contact->notes,
            'deals_count' => $contact->deals_count,
        ];
    }

    /**
     * Build the tenant's selectable deal-owner (employee) options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ownerOptions(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of deal pipeline stages.
     *
     * @return array<int, array<string, string>>
     */
    private function stageOptions(): array
    {
        $labels = [
            'lead' => 'Lead',
            'qualified' => 'Terkualifikasi',
            'proposal' => 'Proposal',
            'won' => 'Menang',
            'lost' => 'Kalah',
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
    private function ensureTenantOwnership(Request $request, CrmDeal $record): void
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

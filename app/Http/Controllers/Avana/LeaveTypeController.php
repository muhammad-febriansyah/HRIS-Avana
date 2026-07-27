<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Policies\LeaveTypePolicy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD controller for the AvanaHR "Jenis Cuti" (leave type) master data.
 *
 * A leave type may branch one level: the root owns the yearly quota, and its
 * sub-types draw from that same pool. Sub-types are edited inline with their
 * parent rather than as standalone records, so the whole tree is saved in one
 * request.
 *
 * Every action is tenant-scoped via {@see LeaveType::scopeForTenant()} and
 * guarded by {@see LeaveTypePolicy}.
 */
class LeaveTypeController extends Controller
{
    use AuthorizesRequests;

    /**
     * List the tenant's root leave types with their sub-types and usage counts.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveType::class);

        $tenantId = $request->user()->tenant_id;

        $leaveTypes = LeaveType::forTenant($tenantId)
            ->roots()
            ->withCount('leaveRequests')
            ->with(['children' => fn ($query) => $query->withCount('leaveRequests')->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (LeaveType $leaveType): array => $this->shape($leaveType));

        return Inertia::render('avana/jenis-cuti/index', [
            'leaveTypes' => $leaveTypes,
        ]);
    }

    /**
     * Show the form for creating a new leave type.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', LeaveType::class);

        return Inertia::render('avana/jenis-cuti/create');
    }

    /**
     * Show the form for editing an existing leave type and its sub-types.
     */
    public function edit(Request $request, LeaveType $leaveType): Response|RedirectResponse
    {
        $this->ensureTenantOwnership($request, $leaveType);
        $this->authorize('update', $leaveType);

        // Editing always happens from the root, so a deep link to a sub-type
        // opens its parent with that branch expanded.
        if ($leaveType->isSub()) {
            return redirect()->route('avana.cuti.jenis.edit', $leaveType->parent_id);
        }

        $leaveType->load(['children' => fn ($query) => $query->withCount('leaveRequests')->orderBy('name')]);

        return Inertia::render('avana/jenis-cuti/edit', [
            'leaveType' => $this->shape($leaveType),
        ]);
    }

    /**
     * Validate and create a new leave type (plus any sub-types) for the tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LeaveType::class);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate($this->rules($tenantId), $this->messages());

        DB::transaction(function () use ($data, $tenantId, $request): void {
            $parent = LeaveType::create([
                'tenant_id' => $tenantId,
                'code' => $data['code'],
                'name' => $data['name'],
                'default_quota' => (int) $data['default_quota'],
                'allow_negative' => $request->boolean('allow_negative'),
                'requires_attachment' => $request->boolean('requires_attachment'),
                'status' => $data['status'],
            ]);

            $this->syncChildren($parent, $data['children'] ?? [], $tenantId);
        });

        return redirect()->route('avana.cuti.jenis')
            ->with('success', 'Jenis cuti dibuat');
    }

    /**
     * Validate and update a leave type and its sub-types.
     */
    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $leaveType);
        $this->authorize('update', $leaveType);

        abort_if($leaveType->isSub(), 404);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate(
            $this->rules($tenantId, (int) $leaveType->getKey()),
            $this->messages(),
        );

        DB::transaction(function () use ($data, $leaveType, $tenantId, $request): void {
            $leaveType->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'default_quota' => (int) $data['default_quota'],
                'allow_negative' => $request->boolean('allow_negative'),
                'requires_attachment' => $request->boolean('requires_attachment'),
                'status' => $data['status'],
            ]);

            $this->syncChildren($leaveType, $data['children'] ?? [], $tenantId);
        });

        return redirect()->route('avana.cuti.jenis')
            ->with('success', 'Jenis cuti diperbarui');
    }

    /**
     * Soft delete a leave type. Deleting a root takes its sub-types with it,
     * since they cannot exist without the quota they draw from.
     */
    public function destroy(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $leaveType);
        $this->authorize('delete', $leaveType);

        DB::transaction(function () use ($leaveType): void {
            $leaveType->children()->each(fn (LeaveType $child) => $child->delete());
            $leaveType->delete();
        });

        return redirect()->route('avana.cuti.jenis')
            ->with('success', 'Jenis cuti dihapus');
    }

    /**
     * Create, update, and retire sub-types so the stored branch matches the
     * submitted one. A sub-type that already carries leave requests is soft
     * deleted rather than dropped, keeping that history readable.
     *
     * @param  array<int, array<string, mixed>>  $children
     */
    private function syncChildren(LeaveType $parent, array $children, int $tenantId): void
    {
        $keptIds = [];

        foreach ($children as $child) {
            $attributes = [
                'tenant_id' => $tenantId,
                'parent_id' => $parent->getKey(),
                'code' => $child['code'],
                'name' => $child['name'],
                // A sub-type has no quota of its own; the parent's is the pool.
                'default_quota' => 0,
                'sub_limit' => ($child['sub_limit'] ?? null) === null || $child['sub_limit'] === ''
                    ? null
                    : (int) $child['sub_limit'],
                'allow_negative' => $this->tristate($child['allow_negative'] ?? 'inherit'),
                'requires_attachment' => $this->tristate($child['requires_attachment'] ?? 'inherit'),
                'status' => $child['status'] ?? 'active',
            ];

            $existing = isset($child['id'])
                ? LeaveType::forTenant($tenantId)
                    ->where('parent_id', $parent->getKey())
                    ->find((int) $child['id'])
                : null;

            if ($existing !== null) {
                $existing->update($attributes);
                $keptIds[] = (int) $existing->getKey();

                continue;
            }

            $keptIds[] = (int) LeaveType::create($attributes)->getKey();
        }

        $parent->children()
            ->whereNotIn('id', $keptIds === [] ? [0] : $keptIds)
            ->each(fn (LeaveType $child) => $child->delete());
    }

    /**
     * Map the form's inherit/yes/no selector onto a nullable boolean, where
     * null means "use the parent's setting".
     */
    private function tristate(mixed $value): ?bool
    {
        return match ($value) {
            'yes', true, 1, '1' => true,
            'no', false, 0, '0' => false,
            default => null,
        };
    }

    /**
     * Build the payload shape shared by the list and edit pages.
     *
     * @return array<string, mixed>
     */
    private function shape(LeaveType $leaveType): array
    {
        return [
            'id' => $leaveType->id,
            'code' => $leaveType->code,
            'name' => $leaveType->name,
            'default_quota' => (int) $leaveType->default_quota,
            'sub_limit' => $leaveType->sub_limit,
            'allow_negative' => $leaveType->allow_negative,
            'requires_attachment' => $leaveType->requires_attachment,
            'status' => $leaveType->status,
            'usage' => (int) ($leaveType->leave_requests_count ?? 0),
            'children' => $leaveType->relationLoaded('children')
                ? $leaveType->children->map(fn (LeaveType $child): array => $this->shape($child))->values()->all()
                : [],
        ];
    }

    /**
     * Build the tenant-scoped validation rules, ignoring the edited record.
     *
     * @return array<string, array<int, mixed>|string>
     */
    private function rules(int $tenantId, ?int $recordId = null): array
    {
        $code = Rule::unique('leave_types', 'code')->where('tenant_id', $tenantId);

        if ($recordId !== null) {
            $code->ignore($recordId);
        }

        return [
            'code' => ['required', 'string', 'max:255', $code],
            'name' => ['required', 'string', 'max:255'],
            'default_quota' => ['required', 'integer', 'min:0', 'max:365'],
            'allow_negative' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],

            'children' => ['array', 'max:20'],
            'children.*.id' => ['nullable', 'integer'],
            'children.*.code' => [
                'required', 'string', 'max:255', 'distinct:ignore_case',
                $this->uniqueChildCode($tenantId),
            ],
            'children.*.name' => ['required', 'string', 'max:255'],
            // A sub-cap larger than the pool it draws from would never bite.
            'children.*.sub_limit' => ['nullable', 'integer', 'min:1', 'lte:default_quota'],
            'children.*.allow_negative' => ['nullable', Rule::in(['inherit', 'yes', 'no'])],
            'children.*.requires_attachment' => ['nullable', Rule::in(['inherit', 'yes', 'no'])],
            'children.*.status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * A sub-type's code must be unique across the tenant, ignoring the row it
     * belongs to. `Rule::unique` cannot express the per-row ignore inside an
     * array, so the check is done by hand against the submitted id.
     */
    private function uniqueChildCode(int $tenantId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($tenantId): void {
            $index = explode('.', $attribute)[1] ?? null;
            $ownId = request()->input("children.{$index}.id");

            $exists = LeaveType::forTenant($tenantId)
                ->where('code', $value)
                ->when($ownId !== null, fn ($query) => $query->whereKeyNot($ownId))
                ->exists();

            if ($exists) {
                $fail('Kode sub-jenis sudah digunakan.');
            }
        };
    }

    /**
     * Indonesian validation messages for the leave type form.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'code.required' => 'Kode wajib diisi.',
            'code.unique' => 'Kode sudah digunakan.',
            'name.required' => 'Nama wajib diisi.',
            'default_quota.required' => 'Jatah cuti wajib diisi.',
            'default_quota.integer' => 'Jatah cuti harus berupa angka.',
            'default_quota.min' => 'Jatah cuti minimal 0 hari.',
            'default_quota.max' => 'Jatah cuti maksimal 365 hari.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
            'children.max' => 'Maksimal 20 sub-jenis per jenis cuti.',
            'children.*.code.required' => 'Kode sub-jenis wajib diisi.',
            'children.*.code.distinct' => 'Kode sub-jenis tidak boleh sama.',
            'children.*.name.required' => 'Nama sub-jenis wajib diisi.',
            'children.*.sub_limit.integer' => 'Batas sub-jenis harus berupa angka.',
            'children.*.sub_limit.min' => 'Batas sub-jenis minimal 1 hari.',
            'children.*.sub_limit.lte' => 'Batas sub-jenis tidak boleh melebihi jatah cuti induk.',
        ];
    }

    /**
     * Abort with 404 when the leave type does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, LeaveType $leaveType): void
    {
        abort_if((int) $leaveType->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}

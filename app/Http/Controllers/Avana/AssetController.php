<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    /**
     * Roles that may always manage assets within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Allowed asset condition enum values.
     *
     * @var array<int, string>
     */
    private const CONDITIONS = ['good', 'fair', 'damaged'];

    /**
     * Allowed asset lifecycle status enum values.
     *
     * @var array<int, string>
     */
    private const STATUSES = ['available', 'assigned', 'maintenance', 'retired'];

    /**
     * Selectable asset category suggestions.
     *
     * @var array<int, string>
     */
    private const CATEGORIES = ['Elektronik', 'Furnitur', 'Kendaraan', 'Perangkat Lunak', 'Peralatan Kantor', 'Lainnya'];

    /**
     * Display the asset register together with active assignments and KPIs.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $assets = Asset::forTenant($tenantId)
            ->with(['currentAssignment.employee:id,full_name,employee_number'])
            ->orderBy('name')
            ->get()
            ->map(fn (Asset $asset): array => $this->transformAsset($asset));

        $assignments = AssetAssignment::forTenant($tenantId)
            ->whereNull('returned_date')
            ->with(['asset:id,code,name', 'employee:id,full_name,employee_number'])
            ->latest('id')
            ->get()
            ->map(fn (AssetAssignment $assignment): array => [
                'id' => $assignment->id,
                'asset' => $assignment->asset === null ? null : [
                    'code' => $assignment->asset->code,
                    'name' => $assignment->asset->name,
                ],
                'employee' => $assignment->employee === null ? null : [
                    'name' => $assignment->employee->full_name,
                    'employee_number' => $assignment->employee->employee_number,
                ],
                'assigned_date' => $assignment->assigned_date?->toDateString(),
                'condition_note' => $assignment->condition_note,
            ]);

        return Inertia::render('avana/aset/index', [
            'assets' => $assets,
            'assignments' => $assignments,
            'employees' => $this->employeeOptions($tenantId),
            'categories' => self::CATEGORIES,
            'conditions' => $this->conditionOptions(),
            'statuses' => $this->statusOptions(),
            'kpis' => [
                'total' => $assets->count(),
                'available' => $assets->where('status', 'available')->count(),
                'assigned' => $assets->where('status', 'assigned')->count(),
                'maintenance' => $assets->where('status', 'maintenance')->count(),
                'total_value' => (float) $assets->sum('purchase_cost'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new asset.
     */
    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('avana/aset/create', [
            'categories' => self::CATEGORIES,
            'conditions' => $this->conditionOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Show the form for editing an existing asset.
     */
    public function edit(Request $request, Asset $asset): Response
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $asset);

        return Inertia::render('avana/aset/edit', [
            'asset' => [
                'id' => $asset->id,
                'code' => $asset->code,
                'name' => $asset->name,
                'category' => $asset->category,
                'purchase_date' => $asset->purchase_date?->toDateString(),
                'purchase_cost' => (float) $asset->purchase_cost,
                'depreciation_years' => $asset->depreciation_years,
                'condition' => $asset->condition,
                'status' => $asset->status,
                'notes' => $asset->notes,
                'book_value' => $this->bookValue($asset),
            ],
            'categories' => self::CATEGORIES,
            'conditions' => $this->conditionOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Persist a new asset under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateAsset($request, $tenantId);

        Asset::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.aset')
            ->with('success', 'Aset berhasil ditambahkan');
    }

    /**
     * Update an existing asset.
     */
    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $asset);

        $data = $this->validateAsset($request, $request->user()->tenant_id, $asset->id);

        $asset->update($data);

        return redirect()->route('avana.aset')
            ->with('success', 'Aset berhasil diperbarui');
    }

    /**
     * Delete an asset.
     */
    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $asset);

        $asset->delete();

        return back()->with('success', 'Aset dihapus');
    }

    /**
     * Assign an asset to an employee and mark it as assigned.
     */
    public function assign(Request $request, Asset $asset): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $asset);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                "exists:employees,id,tenant_id,{$tenantId}",
            ],
            'assigned_date' => ['required', 'date'],
            'condition_note' => ['nullable', 'string', 'max:255'],
        ]);

        AssetAssignment::create([
            'tenant_id' => $tenantId,
            'asset_id' => $asset->id,
            'employee_id' => $data['employee_id'],
            'assigned_date' => $data['assigned_date'],
            'condition_note' => $data['condition_note'] ?? null,
        ]);

        $asset->update(['status' => 'assigned']);

        return redirect()->route('avana.aset')
            ->with('success', 'Aset berhasil ditugaskan ke karyawan');
    }

    /**
     * Mark an asset assignment as returned and free the asset.
     */
    public function returnAsset(Request $request, AssetAssignment $assignment): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $assignment);

        $data = $request->validate([
            'returned_date' => ['nullable', 'date'],
            'condition_note' => ['nullable', 'string', 'max:255'],
        ]);

        $assignment->update([
            'returned_date' => $data['returned_date'] ?? now()->toDateString(),
            'condition_note' => $data['condition_note'] ?? $assignment->condition_note,
        ]);

        $assignment->asset?->update(['status' => 'available']);

        return back()->with('success', 'Aset berhasil dikembalikan');
    }

    /**
     * Validate the create/update payload for an asset.
     *
     * @return array<string, mixed>
     */
    private function validateAsset(Request $request, ?int $tenantId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('assets', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'depreciation_years' => ['required', 'integer', 'min:1'],
            'condition' => ['required', Rule::in(self::CONDITIONS)],
            'status' => ['required', Rule::in(self::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * Build the row shape consumed by the assets table.
     *
     * @return array<string, mixed>
     */
    private function transformAsset(Asset $asset): array
    {
        $current = $asset->currentAssignment;

        return [
            'id' => $asset->id,
            'code' => $asset->code,
            'name' => $asset->name,
            'category' => $asset->category,
            'purchase_date' => $asset->purchase_date?->toDateString(),
            'purchase_cost' => (float) $asset->purchase_cost,
            'depreciation_years' => $asset->depreciation_years,
            'condition' => $asset->condition,
            'status' => $asset->status,
            'notes' => $asset->notes,
            'book_value' => $this->bookValue($asset),
            'current_assignment' => $current === null ? null : [
                'id' => $current->id,
                'assigned_date' => $current->assigned_date?->toDateString(),
                'employee_name' => $current->employee?->full_name,
                'employee_number' => $current->employee?->employee_number,
            ],
        ];
    }

    /**
     * Compute the straight-line book value ("nilai buku") of an asset.
     *
     * Annual depreciation = purchase_cost / depreciation_years, accrued from the
     * purchase date and floored at zero.
     */
    private function bookValue(Asset $asset): float
    {
        $cost = (float) $asset->purchase_cost;
        $years = max(1, (int) $asset->depreciation_years);

        if ($asset->purchase_date === null) {
            return round($cost, 2);
        }

        $elapsed = $asset->purchase_date->diffInYears(now());
        $depreciated = ($cost / $years) * $elapsed;

        return round(max(0, $cost - $depreciated), 2);
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
     * Build the `{ value, label }` list of asset condition options.
     *
     * @return array<int, array<string, string>>
     */
    private function conditionOptions(): array
    {
        $labels = [
            'good' => 'Baik',
            'fair' => 'Cukup',
            'damaged' => 'Rusak',
        ];

        return collect(self::CONDITIONS)
            ->map(fn (string $condition): array => [
                'value' => $condition,
                'label' => $labels[$condition],
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of asset status options.
     *
     * @return array<int, array<string, string>>
     */
    private function statusOptions(): array
    {
        $labels = [
            'available' => 'Tersedia',
            'assigned' => 'Dipakai',
            'maintenance' => 'Perbaikan',
            'retired' => 'Pensiun',
        ];

        return collect(self::STATUSES)
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => $labels[$status],
            ])
            ->all();
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Asset|AssetAssignment $record): void
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

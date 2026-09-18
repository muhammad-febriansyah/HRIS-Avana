<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantGroup;
use App\Models\TenantMembership;
use App\Services\TenantProvisioner;
use App\Support\MultiCompanyEntitlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class MultiCompanyController extends Controller
{
    public function __construct(
        private readonly TenantProvisioner $provisioner,
        private readonly MultiCompanyEntitlement $entitlement,
    ) {}

    public function index(Request $request): Response
    {
        $this->ensureManager($request);

        $tenant = $this->currentTenant($request);
        $group = $tenant->tenantGroup;

        if ($group === null) {
            $group = TenantGroup::create([
                'name' => $tenant->company_name ?: $tenant->name,
                'status' => 'active',
            ]);
            $tenant->forceFill(['tenant_group_id' => $group->id])->saveQuietly();
        }

        $companies = $group->tenants()
            ->withCount(['employees', 'branches'])
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get(['id', 'name', 'company_name', 'status', 'is_primary']);

        return Inertia::render('avana/perusahaan-saya/index', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
            ],
            'entitlement' => $this->entitlementPayload($group),
            'companies' => $companies->map(fn (Tenant $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'company_name' => $company->company_name,
                'status' => $company->status,
                'is_primary' => (bool) $company->is_primary,
                'employees_count' => (int) $company->employees_count,
                'branches_count' => (int) $company->branches_count,
                'is_current' => $company->id === $tenant->id,
            ])->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureManager($request);

        $tenant = $this->currentTenant($request);
        $tenant->loadMissing('tenantGroup', 'package');

        abort_unless($this->entitlement->canCreate($tenant), 403, 'Kuota Multi Company belum tersedia. Hubungi Super Admin.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('tenants', 'slug')],
        ]);

        $company = DB::transaction(function () use ($request, $tenant, $validated): Tenant {
            $slug = filled($validated['slug'] ?? null)
                ? Str::slug($validated['slug'])
                : Str::slug($validated['name']);
            $slug = $this->uniqueSlug($slug);

            $company = Tenant::create([
                'tenant_group_id' => $tenant->tenant_group_id,
                'is_primary' => false,
                'name' => $validated['name'],
                'company_name' => $validated['company_name'] ?? $validated['name'],
                'slug' => $slug,
                'package_id' => $tenant->package_id,
                'status' => 'active',
                'max_users' => $tenant->max_users,
                'max_employees' => $tenant->max_employees,
                'max_branches' => $tenant->max_branches,
                'billing_status' => $tenant->billing_status,
                'start_date' => now()->toDateString(),
                'end_date' => $tenant->end_date,
            ]);

            $this->provisioner->provision($company);

            TenantMembership::updateOrCreate(
                ['user_id' => $request->user()->id, 'tenant_id' => $company->id],
                ['status' => 'active', 'is_default' => false],
            );

            $adminRole = Role::query()
                ->where('tenant_id', $company->id)
                ->where('code', 'admin_tenant_hr')
                ->first();

            if ($adminRole !== null) {
                // The current-user role relation is scoped to the active
                // tenant for permission reads. Attach through the role side
                // so the newly created company's role is not filtered out.
                $adminRole->users()->syncWithoutDetaching([$request->user()->id]);
            }

            return $company;
        });

        return back()->with('success', 'Perusahaan '.$company->name.' berhasil ditambahkan.');
    }

    /**
     * @return array{enabled: bool, company_limit: int, used: int, remaining: int}
     */
    private function entitlementPayload(TenantGroup $group): array
    {
        $addon = $this->entitlement->activeAddon($group);
        $limit = $this->entitlement->companyLimit($group);
        $used = $this->entitlement->usedCompanies($group);

        return [
            'enabled' => $addon !== null && $limit > 1,
            'company_limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
        ];
    }

    private function currentTenant(Request $request): Tenant
    {
        return Tenant::query()->findOrFail($request->user()->tenant_id);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base !== '' ? $base : 'perusahaan';
        $candidate = $slug;
        $suffix = 2;

        while (Tenant::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function ensureManager(Request $request): void
    {
        $user = $request->user();
        $user->loadMissing('roles');

        abort_unless(
            $user->isSuperAdmin() || $user->roles->contains('code', 'admin_tenant_hr'),
            403,
        );
    }
}

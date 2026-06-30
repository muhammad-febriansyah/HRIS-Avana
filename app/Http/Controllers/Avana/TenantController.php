<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform (Super Admin) screen for managing every AvanaHR client tenant:
 * their package, status, usage limits, and which feature modules are enabled.
 */
class TenantController extends Controller
{
    use AuthorizesRequests;

    /**
     * Selectable tenant statuses surfaced in the UI and validation.
     *
     * @var array<int, string>
     */
    private const STATUSES = ['trial', 'active', 'suspended', 'inactive'];

    /**
     * Display a paginated, searchable list of all client tenants.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Tenant::class);

        $tenants = Tenant::query()
            ->withCount(['users', 'employees', 'branches'])
            ->with([
                'package:id,name',
                'features' => fn ($query) => $query->where('is_enabled', true)->with('feature:id,code'),
            ])
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
            })
            ->when(
                in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : null,
                fn ($query, $status) => $query->where('status', $status),
            )
            ->orderBy('name')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $tenants->getCollection()->transform(fn (Tenant $tenant): array => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'company_name' => $tenant->company_name,
            'status' => $tenant->status,
            'billing_status' => $tenant->billing_status,
            'package' => $tenant->package
                ? ['id' => $tenant->package->id, 'name' => $tenant->package->name]
                : null,
            'max_users' => (int) $tenant->max_users,
            'max_employees' => (int) $tenant->max_employees,
            'max_branches' => (int) $tenant->max_branches,
            'users_count' => $tenant->users_count,
            'employees_count' => $tenant->employees_count,
            'branches_count' => $tenant->branches_count,
            'start_date' => $tenant->start_date?->toDateString(),
            'end_date' => $tenant->end_date?->toDateString(),
            'feature_codes' => $tenant->features
                ->pluck('feature.code')
                ->filter()
                ->values()
                ->all(),
        ]);

        return Inertia::render('avana/klien/index', [
            'tenants' => [
                'data' => $tenants->items(),
                'meta' => [
                    'current_page' => $tenants->currentPage(),
                    'last_page' => $tenants->lastPage(),
                    'per_page' => $tenants->perPage(),
                    'total' => $tenants->total(),
                    'from' => $tenants->firstItem(),
                    'to' => $tenants->lastItem(),
                ],
            ],
            'packages' => $this->packageOptions(),
            'features' => Feature::query()
                ->select('id', 'code', 'name')
                ->orderBy('name')
                ->get(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Show the form for creating a new client tenant.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Tenant::class);

        return Inertia::render('avana/klien/create', [
            'packages' => $this->packageOptions(),
        ]);
    }

    /**
     * Show the form for editing an existing client tenant.
     */
    public function edit(Request $request, Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        return Inertia::render('avana/klien/edit', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'company_name' => $tenant->company_name,
                'slug' => $tenant->slug,
                'package_id' => $tenant->package_id,
                'status' => $tenant->status,
                'max_users' => (int) $tenant->max_users,
                'max_employees' => (int) $tenant->max_employees,
                'max_branches' => (int) $tenant->max_branches,
                'billing_status' => $tenant->billing_status,
                'start_date' => $tenant->start_date?->toDateString(),
                'end_date' => $tenant->end_date?->toDateString(),
            ],
            'packages' => $this->packageOptions(),
        ]);
    }

    /**
     * Persist a new tenant and enable every feature module for it by default.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Tenant::class);

        $validated = $request->validate($this->rules());

        $slug = filled($validated['slug'] ?? null)
            ? $validated['slug']
            : $this->uniqueSlug($validated['name']);

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'company_name' => $validated['company_name'] ?? null,
            'slug' => $slug,
            'package_id' => $validated['package_id'] ?? null,
            'status' => $validated['status'] ?? 'trial',
            'max_users' => $validated['max_users'] ?? 0,
            'max_employees' => $validated['max_employees'] ?? 0,
            'max_branches' => $validated['max_branches'] ?? 0,
            'billing_status' => $validated['billing_status'] ?? 'active',
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);

        $this->enableAllFeatures($tenant);

        return back()->with('success', 'Klien berhasil ditambahkan');
    }

    /**
     * Update an existing tenant's profile, package, limits, and status.
     */
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validate($this->rules($tenant));

        $tenant->update([
            'name' => $validated['name'],
            'company_name' => $validated['company_name'] ?? null,
            'slug' => filled($validated['slug'] ?? null) ? $validated['slug'] : $tenant->slug,
            'package_id' => $validated['package_id'] ?? null,
            'status' => $validated['status'] ?? $tenant->status,
            'max_users' => $validated['max_users'] ?? 0,
            'max_employees' => $validated['max_employees'] ?? 0,
            'max_branches' => $validated['max_branches'] ?? 0,
            'billing_status' => $validated['billing_status'] ?? $tenant->billing_status ?? 'active',
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);

        return back()->with('success', 'Klien berhasil diperbarui');
    }

    /**
     * Soft delete (archive) a tenant.
     */
    public function destroy(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('delete', $tenant);

        $tenant->delete();

        return back()->with('success', 'Klien dihapus');
    }

    /**
     * Flip a single feature's enabled state for the tenant.
     */
    public function toggleFeature(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'feature_id' => ['required', 'integer', 'exists:features,id'],
        ]);

        $feature = $tenant->features()->firstOrNew(['feature_id' => $validated['feature_id']]);
        $feature->is_enabled = ! $feature->is_enabled;
        $feature->save();

        return back()->with('success', 'Fitur klien diperbarui');
    }

    /**
     * Shared validation rules for store/update. Pass the tenant on update so
     * its slug uniqueness check ignores the record being edited.
     *
     * @return array<string, mixed>
     */
    private function rules(?Tenant $tenant = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'alpha_dash', 'max:255',
                Rule::unique('tenants', 'slug')->ignore($tenant?->id)->withoutTrashed(),
            ],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'max_users' => ['nullable', 'integer', 'min:0'],
            'max_employees' => ['nullable', 'integer', 'min:0'],
            'max_branches' => ['nullable', 'integer', 'min:0'],
            'billing_status' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Selectable subscription packages for the create/edit forms.
     *
     * @return Collection<int, Package>
     */
    private function packageOptions(): Collection
    {
        return Package::query()
            ->select('id', 'name', 'code', 'max_users', 'max_employees', 'max_branches')
            ->orderBy('name')
            ->get();
    }

    /**
     * Enable every feature module for a freshly created tenant.
     */
    private function enableAllFeatures(Tenant $tenant): void
    {
        foreach (Feature::query()->pluck('id') as $featureId) {
            $tenant->features()->firstOrCreate(
                ['feature_id' => $featureId],
                ['is_enabled' => true],
            );
        }
    }

    /**
     * Derive a unique slug from the tenant name (suffixing on collision).
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'klien';
        $slug = $base;
        $suffix = 1;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}

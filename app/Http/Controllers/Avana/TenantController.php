<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Feature;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\FeatureGroups;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
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
     * Billing cycles a subscription period can run on.
     *
     * @var array<int, string>
     */
    private const BILLING_CYCLES = ['monthly', 'quarterly', 'yearly'];

    /**
     * How long a trial lasts when the wizard does not say otherwise.
     */
    private const DEFAULT_TRIAL_DAYS = 14;

    public function __construct(private readonly TenantProvisioner $provisioner) {}

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
                'company:id,tenant_id,logo_path',
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
            'logo' => $tenant->company?->logo_path
                ? Storage::disk('public')->url($tenant->company->logo_path)
                : null,
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
            'features' => $this->featureCatalog(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * The feature toggle catalog for the Kelola Fitur modal: fixed core rows
     * (always active, no switch) bookending the grouped, per-tenant-togglable
     * features. Mirrors the Hak Akses matrix so both screens list the same menus.
     *
     * @return array<int, array{key: string, id: int|null, code: string|null, name: string, group: string, core: bool}>
     */
    private function featureCatalog(): array
    {
        $core = fn (array $row): array => [
            'key' => 'core:'.$row['label'],
            'id' => null,
            'code' => null,
            'name' => $row['label'],
            'group' => $row['group'],
            'core' => true,
        ];

        $features = Feature::query()
            ->get(['id', 'code', 'name', 'module_group'])
            ->sortBy(fn (Feature $feature): string => sprintf('%02d-%s', FeatureGroups::sortIndex($feature->module_group), $feature->name))
            ->values()
            ->map(fn (Feature $feature): array => [
                'key' => $feature->code,
                'id' => $feature->id,
                'code' => $feature->code,
                'name' => $feature->name,
                'group' => FeatureGroups::label($feature->module_group),
                'core' => false,
            ])
            ->all();

        return [
            ...array_map($core, FeatureGroups::CORE_HEAD),
            ...$features,
            ...array_map($core, FeatureGroups::CORE_TAIL),
        ];
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
     * Show a rich, read-only profile for a single client tenant: usage against
     * quota, subscription & billing health, enabled modules, and org breakdown.
     */
    public function show(Request $request, Tenant $tenant): Response
    {
        $this->authorize('view', $tenant);

        $tenant->loadCount(['users', 'employees', 'branches']);
        $tenant->load('package:id,name,code,price,billing_cycle', 'company:id,tenant_id,logo_path');

        $tenantId = $tenant->id;

        $subscriptions = Subscription::forTenant($tenantId)
            ->with('package:id,name')
            ->orderByDesc('start_date')
            ->get();
        $activeSubscription = $subscriptions->firstWhere('status', 'active');

        $invoices = Invoice::forTenant($tenantId)
            ->orderByDesc('issue_date')
            ->get(['id', 'invoice_number', 'total', 'status', 'issue_date', 'due_date', 'paid_at']);

        $branches = Branch::forTenant($tenantId)
            ->withCount('employees')
            ->orderBy('name')
            ->get(['id', 'name']);

        $departments = Department::forTenant($tenantId)
            ->withCount('employees')
            ->orderBy('name')
            ->get(['id', 'name']);

        $employmentBreakdown = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->selectRaw('employment_status, COUNT(*) as total')
            ->groupBy('employment_status')
            ->pluck('total', 'employment_status');

        $recentHires = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->whereNotNull('join_date')
            ->with('position:id,name')
            ->orderByDesc('join_date')
            ->take(6)
            ->get(['id', 'full_name', 'position_id', 'join_date']);

        return Inertia::render('avana/klien/show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'company_name' => $tenant->company_name,
                'slug' => $tenant->slug,
                'logo' => $tenant->company?->logo_path
                    ? Storage::disk('public')->url($tenant->company->logo_path)
                    : null,
                'status' => $tenant->status,
                'billing_status' => $tenant->billing_status,
                'package' => $tenant->package ? [
                    'name' => $tenant->package->name,
                    'code' => $tenant->package->code,
                    'price' => (float) $tenant->package->price,
                    'billing_cycle' => $tenant->package->billing_cycle,
                ] : null,
                'max_users' => (int) $tenant->max_users,
                'max_employees' => (int) $tenant->max_employees,
                'max_branches' => (int) $tenant->max_branches,
                'users_count' => $tenant->users_count,
                'employees_count' => $tenant->employees_count,
                'branches_count' => $tenant->branches_count,
                'start_date' => $tenant->start_date?->toDateString(),
                'end_date' => $tenant->end_date?->toDateString(),
                'created_at' => $tenant->created_at?->toIso8601String(),
            ],
            'subscription' => [
                'active' => $activeSubscription ? [
                    'package' => $activeSubscription->package?->name,
                    'price' => (float) $activeSubscription->price,
                    'billing_cycle' => $activeSubscription->billing_cycle,
                    'status' => $activeSubscription->status,
                    'start_date' => $activeSubscription->start_date?->toDateString(),
                    'end_date' => $activeSubscription->end_date?->toDateString(),
                ] : null,
                'total' => $subscriptions->count(),
            ],
            'billing' => [
                'paid_total' => (float) $invoices->where('status', 'paid')->sum('total'),
                'outstanding' => (float) $invoices->whereIn('status', ['unpaid', 'overdue'])->sum('total'),
                'overdue_count' => $invoices->where('status', 'overdue')->count(),
                'invoice_count' => $invoices->count(),
                'recent' => $invoices->take(6)->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'total' => (float) $invoice->total,
                    'status' => $invoice->status,
                    'issue_date' => $invoice->issue_date?->toDateString(),
                    'due_date' => $invoice->due_date?->toDateString(),
                ])->values()->all(),
            ],
            'features' => $tenant->features()
                ->where('is_enabled', true)
                ->with('feature:id,code,name')
                ->get()
                ->map(fn ($tenantFeature): ?string => $tenantFeature->feature?->name)
                ->filter()
                ->sort()
                ->values()
                ->all(),
            'branches' => $branches->map(fn (Branch $branch): array => [
                'name' => $branch->name,
                'employees_count' => $branch->employees_count,
            ])->all(),
            'departments' => $departments->map(fn (Department $department): array => [
                'name' => $department->name,
                'employees_count' => $department->employees_count,
            ])->all(),
            'employees' => [
                'active' => (int) $employmentBreakdown->sum(),
                'employment' => [
                    'permanent' => (int) ($employmentBreakdown['permanent'] ?? 0),
                    'contract' => (int) ($employmentBreakdown['contract'] ?? 0),
                    'probation' => (int) ($employmentBreakdown['probation'] ?? 0),
                    'resigned' => (int) ($employmentBreakdown['resigned'] ?? 0),
                ],
                'recent' => $recentHires->map(fn (Employee $employee): array => [
                    'name' => $employee->full_name,
                    'position' => $employee->position?->name,
                    'join_date' => $employee->join_date?->toDateString(),
                ])->all(),
            ],
            'admins' => $this->adminAccounts($tenant),
        ]);
    }

    /**
     * The tenant's Admin Tenant / HR logins — the accounts that actually run
     * the client's AvanaHR. Surfaced on the tenant page so a super admin can
     * see at a glance whether the client can log in at all.
     *
     * @return array<int, array<string, mixed>>
     */
    private function adminAccounts(Tenant $tenant): array
    {
        return User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($query) => $query->where('code', 'admin_tenant_hr'))
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'status', 'created_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'created_at' => $user->created_at?->translatedFormat('d M Y'),
            ])
            ->all();
    }

    /**
     * Persist a new tenant and enable every feature module for it by default.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Tenant::class);

        $validated = $request->validate($this->rules() + $this->adminRules());

        $slug = filled($validated['slug'] ?? null)
            ? $validated['slug']
            : $this->uniqueSlug($validated['name']);

        $package = filled($validated['package_id'] ?? null)
            ? Package::find($validated['package_id'])
            : null;

        $status = $validated['status'] ?? 'trial';
        $cycle = $validated['billing_cycle'] ?? $package?->billing_cycle ?? 'monthly';
        $start = filled($validated['start_date'] ?? null)
            ? Carbon::parse($validated['start_date'])
            : Carbon::today();
        $end = filled($validated['end_date'] ?? null)
            ? Carbon::parse($validated['end_date'])
            : $this->periodEnd($start, $status, $cycle, $validated['trial_days'] ?? null);

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'company_name' => $validated['company_name'] ?? null,
            'slug' => $slug,
            'package_id' => $package?->id,
            'status' => $status,
            // Quotas follow the package unless the super admin typed their own.
            'max_users' => $validated['max_users'] ?? $package?->max_users ?? 0,
            'max_employees' => $validated['max_employees'] ?? $package?->max_employees ?? 0,
            'max_branches' => $validated['max_branches'] ?? $package?->max_branches ?? 0,
            'billing_status' => $validated['billing_status'] ?? 'active',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);

        // The tenant's period is also its subscription, so Billing shows the
        // client from day one instead of waiting for a second manual entry.
        if ($package !== null) {
            Subscription::create([
                'tenant_id' => $tenant->id,
                'package_id' => $package->id,
                'status' => $status === 'trial' ? 'trial' : 'active',
                'billing_cycle' => $cycle,
                // A trial bills nothing until it converts.
                'price' => $status === 'trial' ? 0 : (float) $package->price,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);
        }

        // Features, roles, menu, and the first login — without these the client
        // has no way into their own AvanaHR.
        $this->provisioner->provision($tenant);

        $admin = $this->provisioner->createAdmin(
            $tenant,
            $validated['admin_name'],
            $validated['admin_email'],
            $validated['admin_password'] ?? null,
        );

        return redirect()->route('avana.klien.show', $tenant)
            ->with('success', 'Klien '.$tenant->name.' dibuat beserta akun admin-nya')
            ->with('credentials', [
                'name' => $admin['user']->name,
                'email' => $admin['user']->email,
                'password' => $admin['password'],
            ]);
    }

    /**
     * Add another Admin Tenant / HR login to an existing client — used when the
     * first admin leaves, or the client wants a second HR account.
     */
    public function storeAdmin(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validate($this->adminRules());

        $admin = $this->provisioner->createAdmin(
            $tenant,
            $validated['admin_name'],
            $validated['admin_email'],
            $validated['admin_password'] ?? null,
        );

        return back()
            ->with('success', 'Akun admin ditambahkan')
            ->with('credentials', [
                'name' => $admin['user']->name,
                'email' => $admin['user']->email,
                'password' => $admin['password'],
            ]);
    }

    /**
     * Issue a fresh password for one of the tenant's admin accounts and show it
     * once, so the super admin can hand it over.
     */
    public function resetAdminPassword(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        $this->authorize('update', $tenant);

        abort_if((int) $user->tenant_id !== (int) $tenant->id, 404);

        $validated = $request->validate([
            'admin_password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        $password = $this->provisioner->resetPassword($user, $validated['admin_password'] ?? null);

        return back()
            ->with('success', 'Password admin diperbarui')
            ->with('credentials', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $password,
            ]);
    }

    /**
     * When the subscription period runs out: a trial ends after its own day
     * count, a paid tenant at the end of one billing cycle.
     */
    private function periodEnd(Carbon $start, string $status, string $cycle, ?int $trialDays): Carbon
    {
        if ($status === 'trial') {
            return $start->copy()->addDays($trialDays !== null && $trialDays > 0 ? $trialDays : self::DEFAULT_TRIAL_DAYS);
        }

        return match ($cycle) {
            'yearly' => $start->copy()->addYearNoOverflow(),
            'quarterly' => $start->copy()->addMonthsNoOverflow(3),
            default => $start->copy()->addMonthNoOverflow(),
        };
    }

    /**
     * Validation for the Admin Tenant / HR account fields.
     *
     * @return array<string, array<int, mixed>>
     */
    private function adminRules(): array
    {
        return [
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // Left blank on purpose = generate one and show it once.
            'admin_password' => ['nullable', 'string', 'min:8', 'max:255'],
        ];
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
            // Drive the period from the pricing when no explicit end date is given.
            'billing_cycle' => ['nullable', Rule::in(self::BILLING_CYCLES)],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * Selectable subscription packages for the create/edit forms, carrying the
     * pricing the wizard derives the tenant's quota and period from.
     *
     * @return SupportCollection<int, array<string, mixed>>
     */
    private function packageOptions(): SupportCollection
    {
        return Package::query()
            ->select(
                'id', 'name', 'tagline', 'code', 'price', 'billing_cycle',
                'max_users', 'max_employees', 'max_branches', 'ai_token_quota', 'is_popular',
            )
            ->orderBy('price')
            ->get()
            ->map(fn (Package $package): array => [
                'id' => $package->id,
                'name' => $package->name,
                'tagline' => $package->tagline,
                'code' => $package->code,
                'price' => (float) $package->price,
                'billing_cycle' => $package->billing_cycle ?? 'monthly',
                'max_users' => (int) $package->max_users,
                'max_employees' => (int) $package->max_employees,
                'max_branches' => (int) $package->max_branches,
                'ai_token_quota' => (int) $package->ai_token_quota,
                'is_popular' => (bool) $package->is_popular,
            ])
            ->values();
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

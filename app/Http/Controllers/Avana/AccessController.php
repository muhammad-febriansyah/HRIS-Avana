<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Feature;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AvanaNav;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AccessController extends Controller
{
    /**
     * Roles that implicitly hold every permission (rendered as a full,
     * uneditable row). Only super_admin is immutable.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin'];

    /**
     * Roles allowed to open the access-control screen. A super admin manages any
     * tenant (via the tenant switcher); a tenant admin manages only the roles
     * inside their own tenant.
     *
     * @var array<int, string>
     */
    private const MANAGER_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Avatar colours cycled across the role cards.
     *
     * @var array<int, string>
     */
    private const ROLE_COLORS = ['#2F54C9', '#6E9BE6', '#16A34A', '#6B7280'];

    /**
     * Short Indonesian descriptions keyed by role code.
     *
     * @var array<string, string>
     */
    private const ROLE_DESCRIPTIONS = [
        'super_admin' => 'Akses penuh seluruh modul & tenant',
        'admin_tenant_hr' => 'Kelola karyawan, absensi, cuti, payroll',
        'manager' => 'Approval tim & lihat laporan unit',
        'finance' => 'Kelola klaim, pinjaman, jurnal & payroll',
        'employee' => 'Self-service pribadi (ESS)',
    ];

    /**
     * Section titles for each feature `module_group`, in display order. Feature
     * rows are grouped under these; codes not listed fall back to a generic
     * "LAINNYA" section so a new module_group never disappears from the matrix.
     *
     * @var array<string, string>
     */
    private const GROUP_LABELS = [
        'core' => 'INTI HR',
        'time' => 'WAKTU & KEHADIRAN',
        'payroll' => 'PAYROLL & KEUANGAN',
        'talent' => 'TALENTA',
        'engagement' => 'ENGAGEMENT',
        'analytics' => 'ANALITIK',
        'asset' => 'ASET',
        'crm' => 'CRM',
    ];

    /**
     * Fixed core/system rows that are NOT tenant features (always available, no
     * master switch). Rendered before (UTAMA) and after (SISTEM) the feature rows.
     *
     * @var array<int, array{key: string, label: string, modules: array<int, string>, group: string}>
     */
    private const CORE_HEAD = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'modules' => [], 'group' => 'UTAMA'],
    ];

    private const CORE_TAIL = [
        ['key' => 'pengguna', 'label' => 'Pengguna', 'modules' => ['user'], 'group' => 'SISTEM'],
        ['key' => 'pengaturan', 'label' => 'Pengaturan', 'modules' => ['settings', 'role', 'permission'], 'group' => 'SISTEM'],
        ['key' => 'audit', 'label' => 'Audit Trail', 'modules' => ['audit'], 'group' => 'SISTEM'],
    ];

    /**
     * The matrix rows, generated from the `features` table so a newly-added
     * feature appears automatically. Each feature is one row: its master "Aktif"
     * switch toggles that feature, and its per-role action columns come from the
     * feature's `permission_modules`. Fixed core/system rows (no feature) bookend
     * the list.
     *
     * @return array<int, array{key: string, label: string, modules: array<int, string>, features: array<int, string>, group: string}>
     */
    private function matrixRows(): array
    {
        $groupOrder = array_keys(self::GROUP_LABELS);

        $featureRows = Feature::query()
            ->orderBy('name')
            ->get()
            ->sortBy(fn (Feature $feature): int => array_search($feature->module_group, $groupOrder, true) === false
                ? count($groupOrder)
                : array_search($feature->module_group, $groupOrder, true))
            ->values()
            ->map(fn (Feature $feature): array => [
                'key' => $feature->code,
                'label' => $feature->name,
                'modules' => $feature->permission_modules ?? [],
                'features' => [$feature->code],
                'group' => self::GROUP_LABELS[$feature->module_group] ?? 'LAINNYA',
                'featureId' => $feature->id,
                'moduleGroup' => $feature->module_group,
            ])
            ->all();

        $core = fn (array $row): array => [...$row, 'features' => [], 'featureId' => null, 'moduleGroup' => null];

        return [
            ...array_map($core, self::CORE_HEAD),
            ...$featureRows,
            ...array_map($core, self::CORE_TAIL),
        ];
    }

    /**
     * Render the access-control (RBAC) screen: role cards and the per-action
     * permission matrix (menu × role × action).
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManageAccess($request);

        /** @var User $user */
        $user = $request->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $tenantId = $user->tenant_id;

        $actorRoleIds = $isSuperAdmin ? collect() : $user->roles()->pluck('roles.id');

        $roleModels = $this->tenantRoles($tenantId, $isSuperAdmin)
            ->withCount('users')
            ->with('permissions:id,code')
            ->orderBy('id')
            ->get();

        $roles = $roleModels->values()->map(fn (Role $role, int $index): array => [
            'id' => $role->id,
            'name' => $role->name,
            'code' => $role->code,
            'desc' => self::ROLE_DESCRIPTIONS[$role->code] ?? '',
            'users' => $role->users_count,
            'color' => self::ROLE_COLORS[$index % count(self::ROLE_COLORS)],
            // A locked role cannot be edited by the current actor: super_admin is
            // always immutable, and a tenant admin can never edit their own role.
            'locked' => $role->code === 'super_admin' || $actorRoleIds->contains($role->id),
        ])->all();

        $actions = collect(PermissionCatalog::ACTIONS)
            ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
            ->values()
            ->all();

        // Feature codes currently enabled for the tenant (super admin without an
        // impersonated tenant has none). Drives each row's master "Aktif" switch.
        $tenant = $user->tenant;
        $enabledFeatureCodes = $tenant === null
            ? collect()
            : Feature::whereIn('id', $tenant->features()->where('is_enabled', true)->pluck('feature_id'))->pluck('code');

        $rows = $this->matrixRows();

        $modules = collect($rows)
            ->map(fn (array $module): array => [
                'key' => $module['key'],
                'label' => $module['label'],
                'group' => $module['group'],
                'actionable' => $module['modules'] !== [],
                'hasFeature' => $module['features'] !== [],
                // Enabled only when EVERY feature the row covers is on, so the
                // switch flips the whole menu consistently.
                'featureEnabled' => $module['features'] !== []
                    && collect($module['features'])->every(fn (string $code): bool => $enabledFeatureCodes->contains($code)),
                // Feature-catalog handles: null on fixed core rows.
                'featureId' => $module['featureId'] ?? null,
                'moduleGroup' => $module['moduleGroup'] ?? null,
                'permissionModules' => $module['modules'],
            ])
            ->all();

        // matrix[rowIdx][roleIdx] = { view: bool, create: bool, ... }
        $matrix = collect($rows)
            ->map(fn (array $module): array => $roleModels
                ->map(fn (Role $role): array => $this->roleActionCells($role, $module))
                ->all())
            ->all();

        return Inertia::render('avana/hak-akses/index', [
            'roles' => $roles,
            'actions' => $actions,
            'modules' => $modules,
            'permHeaders' => $roleModels->pluck('name')->all(),
            'matrix' => $matrix,
            'isSuperAdmin' => $isSuperAdmin,
            // Master feature switches only operate against a concrete tenant.
            'hasTenant' => $tenant !== null,
            // Feature-catalog CRUD (super-admin only) folded into this screen.
            'canManageFeatures' => $isSuperAdmin,
            'moduleGroups' => Feature::query()->distinct()->orderBy('module_group')->pluck('module_group')->filter()->values(),
            'moduleOptions' => Permission::query()->distinct()->orderBy('module')->pluck('module')->filter()->values(),
            // Menu-builder (super-admin only) folded in as the "Struktur Menu" tab.
            'canManageMenu' => $isSuperAdmin,
            'menu' => $this->menuBuilderData($request, $user, $isSuperAdmin),
        ]);
    }

    /**
     * Data for the folded-in Menu Builder tab (sidebar structure). Mirrors
     * {@see MenuBuilderController::index} so the shared page component renders
     * unchanged. Seeds the scope's default menu when it has never been edited.
     *
     * @return array<string, mixed>
     */
    private function menuBuilderData(Request $request, User $user, bool $isSuperAdmin): array
    {
        // Super admin may point the builder at a specific tenant via ?tenant=;
        // otherwise their own tenant (or the impersonated one). Mirrors
        // MenuBuilderController::resolveTenantId so the embedded switcher works.
        $tenantId = $user->tenant_id;

        if ($isSuperAdmin) {
            $requested = (int) ($request->query('tenant') ?? 0);
            if ($requested > 0 && Tenant::whereKey($requested)->exists()) {
                $tenantId = $requested;
            }
        }

        if (MenuItem::forTenant($tenantId)->doesntExist()) {
            $tenantId === null
                ? AvanaNav::seedPlatformDefaults()
                : AvanaNav::seedDefaultsFor($tenantId);
        }

        $rows = MenuItem::forTenant($tenantId)
            ->when(! $isSuperAdmin, fn ($query) => $query->where('super_admin_only', false))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byParent = $rows->groupBy(fn (MenuItem $row): int => (int) ($row->parent_id ?? 0));

        $tree = $byParent->get(0, collect())->map(fn (MenuItem $item): array => [
            ...$this->menuRow($item),
            'children' => $byParent->get($item->id, collect())->map(fn (MenuItem $child): array => $this->menuRow($child))->all(),
        ])->all();

        return [
            'tree' => $tree,
            'parents' => $byParent->get(0, collect())->map(fn (MenuItem $i): array => ['id' => $i->id, 'label' => $i->label])->values()->all(),
            'sections' => $byParent->get(0, collect())->pluck('section')->filter()->unique()->values()->all(),
            'features' => Feature::orderBy('name')->get(['code', 'name'])->map(fn (Feature $f): array => ['value' => $f->code, 'label' => $f->name])->all(),
            'modules' => Permission::query()->distinct()->orderBy('module')->pluck('module')->all(),
            'isSuperAdmin' => $isSuperAdmin,
            'selectedTenant' => $tenantId,
            'tenants' => $isSuperAdmin
                ? Tenant::orderBy('name')->get(['id', 'name'])->map(fn (Tenant $t): array => ['id' => $t->id, 'name' => $t->name])->all()
                : [],
        ];
    }

    /**
     * Flatten a menu item to the shape the Menu Builder component expects.
     *
     * @return array<string, mixed>
     */
    private function menuRow(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'key' => $item->key,
            'parent_id' => $item->parent_id,
            'section' => $item->section,
            'label' => $item->label,
            'icon' => $item->icon,
            'href' => $item->href,
            'feature' => $item->feature,
            'modules' => $item->modules ?? [],
            'admin_only' => $item->admin_only,
            'super_admin_only' => $item->super_admin_only,
            'is_active' => $item->is_active,
            'is_system' => $item->is_system,
            'sort_order' => $item->sort_order,
        ];
    }

    /**
     * Toggle a single action of a menu (across the modules it covers) on or off
     * for a role.
     */
    public function togglePermission(Request $request): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'module_key' => ['required', 'string'],
            'action' => ['required', 'string', 'in:'.implode(',', PermissionCatalog::actionKeys())],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $module = collect($this->matrixRows())->firstWhere('key', $validated['module_key']);

        abort_if($module === null || $module['modules'] === [], 422, 'Module cannot be toggled.');

        /** @var User $user */
        $user = $request->user();
        $isSuperAdmin = $user->isSuperAdmin();

        $role = $this->tenantRoles($user->tenant_id, $isSuperAdmin)
            ->whereKey($validated['role_id'])
            ->firstOrFail();

        // System super-admin access is immutable.
        abort_if($role->code === 'super_admin', 403, 'Super admin access cannot be modified.');

        // A tenant admin can never edit the permissions of a role they hold
        // (prevents self-lockout).
        abort_if(
            ! $isSuperAdmin && $user->roles()->whereKey($role->id)->exists(),
            403,
            'Anda tidak dapat mengubah izin peran Anda sendiri.',
        );

        $codes = collect($module['modules'])->map(fn (string $m): string => $m.'.'.$validated['action']);
        $permissionIds = Permission::query()->whereIn('code', $codes)->pluck('id');

        $before = $role->permissions()->pluck('code');

        $hasAll = $role->permissions()->whereIn('permissions.id', $permissionIds)->count() === $permissionIds->count()
            && $permissionIds->isNotEmpty();

        if ($hasAll) {
            $role->permissions()->detach($permissionIds);
        } else {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $this->recordPermissionChange($user, $role, $before, $role->permissions()->pluck('code'));

        return back()->with('success', 'Hak akses diperbarui');
    }

    /**
     * Toggle a menu's master "Aktif" switch: enable/disable every tenant feature
     * the menu depends on. A disabled menu is hidden from the sidebar and its
     * routes 403 (via {@see AvanaNav} + EnsureAvanaAccess). Replaces the separate
     * Menu & Fitur screen so all menu control lives on the Hak Akses page.
     */
    public function toggleFeature(Request $request): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'module_key' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ]);

        $module = collect($this->matrixRows())->firstWhere('key', $validated['module_key']);

        abort_if($module === null || $module['features'] === [], 422, 'Menu ini tidak memiliki fitur untuk diaktifkan.');

        $tenant = $request->user()->tenant;
        abort_if($tenant === null, 404);

        $featureIds = Feature::whereIn('code', $module['features'])->pluck('id');

        foreach ($featureIds as $featureId) {
            $tenant->features()->updateOrCreate(
                ['feature_id' => $featureId],
                ['is_enabled' => $validated['enabled']],
            );
        }

        return back()->with('success', $validated['enabled'] ? 'Menu diaktifkan' : 'Menu dinonaktifkan');
    }

    /**
     * Create a new custom role for the current (or impersonated) tenant.
     */
    public function storeRole(Request $request): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Role::create([
            'tenant_id' => $request->user()->tenant_id,
            'code' => Str::slug($validated['name']),
            'name' => $validated['name'],
            'is_system' => false,
        ]);

        return back()->with('success', 'Role dibuat');
    }

    /**
     * Base query for the roles a user may manage: a super admin sees the tenant's
     * roles plus the global (null-tenant) roles; a tenant admin sees only their
     * own tenant's roles — never another tenant's, never the global super_admin.
     */
    private function tenantRoles(?int $tenantId, bool $isSuperAdmin): Builder
    {
        return Role::query()->where(function (Builder $query) use ($tenantId, $isSuperAdmin): void {
            $query->where('tenant_id', $tenantId);

            if ($isSuperAdmin) {
                $query->orWhereNull('tenant_id');
            }
        });
    }

    /**
     * The per-action checked state for a role/menu pairing.
     *
     * @param  array{key: string, label: string, modules: array<int, string>}  $module
     * @return array<string, bool>
     */
    private function roleActionCells(Role $role, array $module): array
    {
        $privileged = in_array($role->code, self::PRIVILEGED_ROLES, true);
        $held = $role->permissions->pluck('code')->flip();

        $cells = [];

        foreach (PermissionCatalog::actionKeys() as $action) {
            if ($module['modules'] === []) {
                $cells[$action] = false;

                continue;
            }

            if ($privileged) {
                $cells[$action] = true;

                continue;
            }

            // Checked only when the role holds the action for EVERY module the
            // menu covers, so a toggle flips the whole menu consistently.
            $cells[$action] = collect($module['modules'])
                ->every(fn (string $m): bool => $held->has($m.'.'.$action));
        }

        return $cells;
    }

    /**
     * Write an audit row for a role permission change.
     *
     * @param  Collection<int, string>  $before
     * @param  Collection<int, string>  $after
     */
    private function recordPermissionChange(User $actor, Role $role, Collection $before, Collection $after): void
    {
        AuditLog::create([
            'tenant_id' => $role->tenant_id ?? $actor->tenant_id,
            'user_id' => $actor->id,
            'auditable_type' => $role->getMorphClass(),
            'auditable_id' => $role->getKey(),
            'action' => 'permission.updated',
            'old_values' => ['role' => $role->code, 'codes' => $before->sort()->values()->all()],
            'new_values' => ['role' => $role->code, 'codes' => $after->sort()->values()->all()],
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Abort with 403 unless the user may manage access control. A tenant admin is
     * scoped to their own tenant by {@see tenantRoles()}; a super admin manages
     * any tenant via the tenant switcher.
     */
    private function ensureCanManageAccess(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles');

        abort_unless($user->roles->whereIn('code', self::MANAGER_ROLES)->isNotEmpty(), 403);
    }
}

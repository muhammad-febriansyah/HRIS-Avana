<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Feature;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleMenuVisibility;
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
     * The matrix rows: **one per real menu**, in sidebar order, straight from the
     * tenant's own menu (so a renamed, added, or hidden menu shows up here too).
     *
     * Earlier this screen listed one row per *feature*, which is why it never
     * matched what a role actually saw: a single feature can own a dozen menus
     * (`payroll` owns eleven) and the twenty self-service screens shared one
     * feature row with no per-role control at all.
     *
     * `permissionModules` drops the `own` pseudo-module: every employee holds it,
     * so it grants nothing and can only be controlled by visibility.
     *
     * @return array<int, array{key: string, label: string, group: string, parent: string|null, href: string|null, feature: string|null, modules: array<int, string>, permissionModules: array<int, string>, isActive: bool, menuItemId: int|null}>
     */
    private function matrixRows(?int $tenantId): array
    {
        return collect(AvanaNav::menuRows($tenantId))
            ->map(fn (array $row): array => [
                ...$row,
                'permissionModules' => array_values(array_filter(
                    $row['modules'],
                    fn (string $module): bool => $module !== 'own',
                )),
            ])
            ->values()
            ->all();
    }

    /**
     * The permission modules a matrix key covers. A key is a menu key, but the
     * legacy feature code is still accepted so an integration (or an older cached
     * page) that toggles by feature keeps working.
     *
     * @return array<int, string>|null Null when the key matches nothing.
     */
    private function modulesForKey(string $key, ?int $tenantId): ?array
    {
        $menu = collect($this->matrixRows($tenantId))->firstWhere('key', $key);

        if ($menu !== null) {
            return $menu['permissionModules'];
        }

        $feature = Feature::query()->where('code', $key)->first();

        return $feature === null
            ? null
            : array_values(array_filter($feature->permission_modules ?? [], fn (string $m): bool => $m !== 'own'));
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

        $rows = $this->matrixRows($tenantId);

        $featureNames = Feature::query()->pluck('name', 'code');

        $modules = collect($rows)
            ->map(fn (array $row): array => [
                'key' => $row['key'],
                'label' => $row['label'],
                'group' => $row['group'],
                // The collapsible parent this menu sits under, e.g. "Kehadiran".
                'parent' => $row['parent'],
                'href' => $row['href'],
                'actionable' => $row['permissionModules'] !== [],
                'permissionModules' => $row['permissionModules'],
                // Tenant-wide on/off for this one menu (Menu Builder's is_active).
                'menuActive' => $row['isActive'],
                'menuItemId' => $row['menuItemId'],
                // The package feature behind the menu: off = no role can reach it.
                'feature' => $row['feature'],
                'featureLabel' => $row['feature'] !== null ? ($featureNames[$row['feature']] ?? $row['feature']) : null,
                'hasFeature' => $row['feature'] !== null,
                'featureEnabled' => $row['feature'] === null || $enabledFeatureCodes->contains($row['feature']),
                // Self-service rows: every employee holds `own`, so per-role
                // control is visibility only — there is no action to grant.
                'selfService' => $row['modules'] === ['own'],
            ])
            ->all();

        $hiddenByRole = RoleMenuVisibility::query()
            ->whereIn('role_id', $roleModels->pluck('id'))
            ->where('is_visible', false)
            ->get(['role_id', 'menu_key'])
            ->groupBy('role_id')
            ->map(fn ($group) => $group->pluck('menu_key')->flip());

        // matrix[rowIdx][roleIdx] = { visible: bool, view: bool, create: bool, ... }
        $matrix = collect($rows)
            ->map(fn (array $row): array => $roleModels
                ->map(fn (Role $role): array => [
                    'visible' => ! ($hiddenByRole[$role->id] ?? collect())->has($row['key']),
                    ...$this->roleActionCells($role, ['modules' => $row['permissionModules']]),
                ])
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

        /** @var User $user */
        $user = $request->user();
        $isSuperAdmin = $user->isSuperAdmin();

        $modules = $this->modulesForKey($validated['module_key'], $user->tenant_id);

        abort_if($modules === null || $modules === [], 422, 'Module cannot be toggled.');

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

        $codes = collect($modules)->map(fn (string $m): string => $m.'.'.$validated['action']);
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
     * Show or hide one menu for one role — the control the permission columns
     * cannot express, because self-service menus are gated by the `own` module
     * every employee holds.
     *
     * Hiding removes the menu from that role's sidebar AND closes its URL
     * (the `EnsureAvanaAccess` gate). A user holding several
     * roles keeps the menu while any one of their roles still shows it.
     */
    public function toggleMenuVisibility(Request $request): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'menu_key' => ['required', 'string'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'visible' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $isSuperAdmin = $user->isSuperAdmin();

        $menu = collect($this->matrixRows($user->tenant_id))->firstWhere('key', $validated['menu_key']);

        abort_if($menu === null, 422, 'Menu tidak dikenal.');

        $role = $this->tenantRoles($user->tenant_id, $isSuperAdmin)
            ->whereKey($validated['role_id'])
            ->firstOrFail();

        abort_if($role->code === 'super_admin', 403, 'Super admin access cannot be modified.');

        abort_if(
            ! $isSuperAdmin && $user->roles()->whereKey($role->id)->exists(),
            403,
            'Anda tidak dapat mengubah izin peran Anda sendiri.',
        );

        RoleMenuVisibility::updateOrCreate(
            ['role_id' => $role->id, 'menu_key' => $menu['key']],
            ['tenant_id' => $role->tenant_id ?? $user->tenant_id, 'is_visible' => $validated['visible']],
        );

        return back()->with('success', $validated['visible']
            ? 'Menu ditampilkan untuk '.$role->name
            : 'Menu disembunyikan dari '.$role->name);
    }

    /**
     * Turn one menu on or off for the whole tenant (the "Aktif" column). Off
     * hides it from every role and closes its URL, same as the Menu Builder
     * switch this shares its column with.
     */
    public function toggleMenu(Request $request): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'menu_key' => ['required', 'string'],
            'active' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        abort_if($user->tenant_id === null, 404, 'Tidak ada tenant yang dipilih.');

        $item = MenuItem::forTenant($user->tenant_id)
            ->where('key', $validated['menu_key'])
            ->where('super_admin_only', false)
            ->firstOrFail();

        $item->update(['is_active' => $validated['active']]);

        return back()->with('success', $validated['active'] ? 'Menu diaktifkan' : 'Menu dinonaktifkan');
    }

    /**
     * Toggle a tenant feature (the package module behind a menu). Kept as its own
     * action because a feature can back several menus at once, and because the
     * package — not this screen — is what normally decides it.
     */
    public function toggleFeature(Request $request): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'module_key' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ]);

        // Accepts a feature code, or a menu key whose feature is then resolved.
        $code = $validated['module_key'];

        if (Feature::query()->where('code', $code)->doesntExist()) {
            $menu = collect($this->matrixRows($request->user()->tenant_id))->firstWhere('key', $code);
            $code = $menu['feature'] ?? null;
        }

        abort_if($code === null, 422, 'Menu ini tidak memiliki fitur untuk diaktifkan.');

        $feature = Feature::query()->where('code', $code)->first();

        abort_if($feature === null, 422, 'Menu ini tidak memiliki fitur untuk diaktifkan.');

        $tenant = $request->user()->tenant;
        abort_if($tenant === null, 404);

        $tenant->features()->updateOrCreate(
            ['feature_id' => $feature->id],
            ['is_enabled' => $validated['enabled']],
        );

        return back()->with('success', $validated['enabled'] ? 'Fitur diaktifkan' : 'Fitur dinonaktifkan');
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

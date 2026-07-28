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
use Illuminate\Support\Facades\DB;
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
            ->with(['permissions:id,code', 'users' => fn ($query) => $query
                ->select('users.id', 'users.name', 'users.email', 'users.status')
                ->with('employee:id,user_id,full_name,position_id', 'employee.position:id,name')
                ->orderBy('users.name')])
            ->orderBy('id')
            ->get();

        $roles = $roleModels->values()->map(fn (Role $role, int $index): array => [
            'id' => $role->id,
            'name' => $role->name,
            'code' => $role->code,
            'desc' => self::ROLE_DESCRIPTIONS[$role->code] ?? '',
            'users' => $role->users_count,
            'color' => self::ROLE_COLORS[$index % count(self::ROLE_COLORS)],
            'isSystem' => (bool) $role->is_system,
            // A locked role cannot be edited by the current actor: super_admin is
            // always immutable, and a tenant admin can never edit their own role.
            'locked' => $role->code === 'super_admin' || $actorRoleIds->contains($role->id),
            // Who actually holds the role — the question "buat siapa peran ini?"
            // that a permission matrix alone never answers.
            'members' => $role->users->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->employee?->full_name ?? $member->name,
                'email' => $member->email,
                'position' => $member->employee?->position?->name,
                'status' => $member->status,
            ])->values()->all(),
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

        $blockedKeys = collect($modules)
            ->filter(fn (array $module): bool => ! $module['menuActive'] || ! $module['featureEnabled'])
            ->pluck('key')
            ->flip();

        // matrix[rowIdx][roleIdx] = { visible, hidden, granted, view, create, ... }
        //
        // `visible` is what the role ACTUALLY sees, not merely what the hide
        // switch says: a menu also needs a permission on its module (an admin
        // menu with no action granted never reaches the sidebar) and needs to be
        // on for the company. The panel would otherwise claim "Tampil" for rows
        // the role cannot open — which is exactly how this screen used to lie.
        $matrix = collect($rows)
            ->map(function (array $row) use ($roleModels, $hiddenByRole, $blockedKeys): array {
                return $roleModels
                    ->map(function (Role $role) use ($row, $hiddenByRole, $blockedKeys): array {
                        $hidden = ($hiddenByRole[$role->id] ?? collect())->has($row['key']);
                        $actions = $this->roleActionCells($role, ['modules' => $row['permissionModules']]);
                        $granted = $this->roleGrantsMenu($role, $row['permissionModules']);
                        $blocked = $blockedKeys->has($row['key']);

                        return [
                            'visible' => ! $hidden && $granted && ! $blocked,
                            'hidden' => $hidden,
                            'granted' => $granted,
                            ...$actions,
                        ];
                    })
                    ->all();
            })
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
            // Everyone in the tenant who can be put into a role, so assigning a
            // member never means leaving for the Pengguna screen and back.
            'assignableUsers' => $this->assignableUsers($tenantId, $roleModels),
        ]);
    }

    /**
     * Tenant accounts that may be added to a role, with the roles they already
     * hold so the picker can say "sudah di peran ini".
     *
     * @param  Collection<int, Role>  $roles
     * @return array<int, array{id: int, name: string, email: string, role_ids: array<int, int>}>
     */
    private function assignableUsers(?int $tenantId, Collection $roles): array
    {
        if ($tenantId === null) {
            return [];
        }

        $byUser = DB::table('user_roles')
            ->whereIn('role_id', $roles->pluck('id'))
            ->get(['user_id', 'role_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('role_id')->map(fn ($id): int => (int) $id)->values()->all());

        return User::query()
            ->where('tenant_id', $tenantId)
            ->with('employee:id,user_id,full_name')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->employee?->full_name ?? $member->name,
                'email' => $member->email,
                'role_ids' => $byUser[$member->id] ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * Put a tenant account into a role.
     */
    public function attachRoleUser(Request $request, Role $role): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $role = $this->assertManageableRole($actor, $role);

        $member = User::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereKey($validated['user_id'])
            ->firstOrFail();

        $role->users()->syncWithoutDetaching([$member->id]);

        return back()->with('success', $member->name.' ditambahkan ke peran '.$role->name);
    }

    /**
     * Take a tenant account out of a role. Refused when it is their last role —
     * an account with none sees an empty app and cannot be fixed from here.
     */
    public function detachRoleUser(Request $request, Role $role, User $member): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        /** @var User $actor */
        $actor = $request->user();
        $role = $this->assertManageableRole($actor, $role);

        abort_unless((int) $member->tenant_id === (int) $actor->tenant_id, 404);

        if ($member->roles()->count() <= 1) {
            return back()->with('error', $member->name.' harus punya minimal satu peran.');
        }

        $role->users()->detach($member->id);

        return back()->with('success', $member->name.' dikeluarkan dari peran '.$role->name);
    }

    /**
     * The role must belong to the actor's scope and never be the system super
     * admin; a tenant admin may not restaff their own role either.
     */
    private function assertManageableRole(User $actor, Role $role): Role
    {
        $isSuperAdmin = $actor->isSuperAdmin();

        $role = $this->tenantRoles($actor->tenant_id, $isSuperAdmin)
            ->whereKey($role->id)
            ->firstOrFail();

        abort_if($role->code === 'super_admin', 403, 'Super admin access cannot be modified.');

        abort_if(
            ! $isSuperAdmin && $actor->roles()->whereKey($role->id)->exists(),
            403,
            'Anda tidak dapat mengubah peran Anda sendiri.',
        );

        return $role;
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

        if (! $validated['visible']) {
            return back()->with('success', 'Menu disembunyikan dari '.$role->name);
        }

        // Showing a menu means the role can open it. Lifting the hide flag alone
        // would leave an admin menu still absent, because the sidebar also wants
        // a permission on its module — so grant the minimum (Lihat) when the role
        // holds nothing on it yet. Existing actions are never touched.
        $role->load('permissions:id,code');

        if (! $this->roleGrantsMenu($role, $menu['permissionModules'])) {
            $before = $role->permissions()->pluck('code');

            $viewIds = Permission::query()
                ->whereIn('code', collect($menu['permissionModules'])->map(fn (string $m): string => $m.'.view'))
                ->pluck('id');

            $role->permissions()->syncWithoutDetaching($viewIds);
            $this->recordPermissionChange($user, $role, $before, $role->permissions()->pluck('code'));

            return back()->with('success', 'Menu ditampilkan untuk '.$role->name.' beserta izin Lihat');
        }

        return back()->with('success', 'Menu ditampilkan untuk '.$role->name);
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
            // Starting from an existing role beats starting from nothing: a blank
            // role sees no menu at all, which reads as "the role is broken".
            'copy_from_role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $role = Role::create([
            'tenant_id' => $user->tenant_id,
            'code' => Str::slug($validated['name']),
            'name' => $validated['name'],
            'is_system' => false,
        ]);

        if (($validated['copy_from_role_id'] ?? null) !== null) {
            $source = $this->tenantRoles($user->tenant_id, $user->isSuperAdmin())
                ->whereKey($validated['copy_from_role_id'])
                ->where('code', '!=', 'super_admin')
                ->first();

            if ($source !== null) {
                $role->permissions()->syncWithoutDetaching($source->permissions()->pluck('permissions.id'));

                foreach (RoleMenuVisibility::where('role_id', $source->id)->get() as $hidden) {
                    RoleMenuVisibility::updateOrCreate(
                        ['role_id' => $role->id, 'menu_key' => $hidden->menu_key],
                        ['tenant_id' => $role->tenant_id ?? $user->tenant_id, 'is_visible' => $hidden->is_visible],
                    );
                }

                return back()->with('success', 'Peran '.$role->name.' dibuat, menyalin izin dari '.$source->name);
            }
        }

        return back()->with('success', 'Peran '.$role->name.' dibuat. Tambahkan penggunanya, lalu atur menunya.');
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
     * Whether the role holds any permission on the menu's modules — the gate the
     * sidebar and the `EnsureAvanaAccess` gate apply. A menu with
     * no module (Dashboard, self-service) needs nothing.
     *
     * @param  array<int, string>  $modules
     */
    private function roleGrantsMenu(Role $role, array $modules): bool
    {
        if ($modules === []) {
            return true;
        }

        if (in_array($role->code, self::PRIVILEGED_ROLES, true)) {
            return true;
        }

        return $role->permissions
            ->pluck('code')
            ->contains(fn (string $code): bool => in_array(Str::before($code, '.'), $modules, true));
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

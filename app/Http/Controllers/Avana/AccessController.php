<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Feature;
use App\Models\MenuItem;
use App\Models\MobileMenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleMenuVisibility;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Support\AvanaNav;
use App\Support\MobileMenu;
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
        $rows = collect(AvanaNav::menuRows($tenantId))
            ->map(fn (array $row): array => [
                ...$row,
                'permissionModules' => array_values(array_filter(
                    $row['modules'],
                    fn (string $module): bool => $module !== 'own',
                )),
            ])
            ->values();

        return $rows->concat($this->catalogOnlyRows($rows, $tenantId))->values()->all();
    }

    /**
     * Rows for catalogue features no menu covers yet.
     *
     * A feature added from Katalog Fitur creates its permissions immediately,
     * but its menu leaf is written by hand later. Between the two there was
     * nowhere to grant it: the screen listed menus, and this feature had none,
     * so the permissions existed and no role could be given them.
     *
     * Only features this tenant is actually subscribed to. The catalogue is
     * platform-wide, so reading it unfiltered showed every company the name of
     * every feature built for anybody — including one written for a single
     * client — and offered to grant it.
     *
     * Normally empty — every shipped feature owns a menu — so this appends
     * nothing until somebody adds a feature the sidebar has not caught up with.
     *
     * @param  Collection<int, array<string, mixed>>  $menuRows
     * @return array<int, array<string, mixed>>
     */
    private function catalogOnlyRows(Collection $menuRows, ?int $tenantId): array
    {
        $covered = $menuRows->pluck('permissionModules')->flatten()->unique();

        return Feature::query()
            ->where('is_active', true)
            // A super admin works the platform catalogue itself and has no
            // tenant to filter by; everyone else sees only what their company
            // is subscribed to.
            ->when($tenantId !== null, fn ($query) => $query->whereIn('id', TenantFeature::query()
                ->where('tenant_id', $tenantId)
                ->where('is_enabled', true)
                ->select('feature_id')))
            ->orderBy('name')
            ->get()
            ->map(function (Feature $feature): array {
                return [
                    'feature' => $feature,
                    'modules' => array_values(array_filter(
                        $feature->permission_modules ?? [],
                        fn (string $module): bool => $module !== 'own',
                    )),
                ];
            })
            ->filter(fn (array $row): bool => $row['modules'] !== []
                && $covered->intersect($row['modules'])->isEmpty())
            ->map(fn (array $row): array => [
                'key' => $row['feature']->code,
                'label' => $row['feature']->name,
                'group' => 'BELUM ADA MENU',
                'parent' => null,
                'href' => null,
                'feature' => $row['feature']->code,
                'modules' => $row['modules'],
                'permissionModules' => $row['modules'],
                'isActive' => true,
                'menuItemId' => null,
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
     * The menu rows as the screens consume them: sidebar order, with the
     * tenant-wide state (menu on/off, package feature) each row depends on.
     *
     * @param  array<int, array<string, mixed>>  $rows  Output of {@see matrixRows}.
     * @return array<int, array<string, mixed>>
     */
    private function menuPayload(array $rows, ?Tenant $tenant): array
    {
        // Feature codes currently enabled for the tenant (a super admin without an
        // impersonated tenant has none). Drives each row's master "Aktif" switch.
        $enabledFeatureCodes = $tenant === null
            ? collect()
            : Feature::whereIn('id', $tenant->features()->where('is_enabled', true)->pluck('feature_id'))->pluck('code');

        $featureNames = Feature::query()->pluck('name', 'code');

        $accessPath = trim((string) parse_url(route('avana.hak-akses'), PHP_URL_PATH), '/');

        return collect($rows)
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
                // This row IS the access screen: its switch is shown locked,
                // because switching it off would close the way back in.
                'lockedActive' => $this->gatesRequest($row['href'], $accessPath),
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
            // What the admin typed when creating the role wins; the seeded roles
            // have no description of their own, so they fall back to the blurbs.
            'desc' => $role->description ?? self::ROLE_DESCRIPTIONS[$role->code] ?? '',
            'users' => $role->users_count,
            'color' => self::ROLE_COLORS[$index % count(self::ROLE_COLORS)],
            'isSystem' => (bool) $role->is_system,
            // May the holders of this role sign in to the Flutter app? Separate
            // from every web permission — the phone is its own decision.
            'canAccessMobile' => (bool) $role->can_access_mobile,
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

        $tenant = $user->tenant;
        $rows = $this->matrixRows($tenantId);
        $modules = $this->menuPayload($rows, $tenant);

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
            // The Flutter app's Menu Cepat, so the tiles on the phone are set
            // from the same screen as everything else.
            'mobileMenu' => $this->mobileMenuPayload($tenantId, $roleModels),
            // The phone's bottom bar, set from the same screen. Beranda and
            // Profil ride along carrying `locked`, so the switch renders but
            // cannot be thrown — the app needs both to stay usable.
            'mobileTabs' => $this->mobileMenuPayload($tenantId, $roleModels, MobileMenu::GROUP_TAB),
        ]);
    }

    /**
     * One group of phone rows — Menu Cepat tiles or bottom tabs — with, per
     * row, which roles currently show it.
     *
     * @param  Collection<int, Role>  $roles
     * @return array<int, array<string, mixed>>
     */
    private function mobileMenuPayload(?int $tenantId, Collection $roles, string $group = MobileMenu::GROUP_QUICK): array
    {
        if ($tenantId === null) {
            return [];
        }

        $hiddenByRole = RoleMenuVisibility::query()
            ->whereIn('role_id', $roles->pluck('id'))
            ->where('is_visible', false)
            ->where('menu_key', 'like', MobileMenuItem::VISIBILITY_PREFIX.'%')
            ->get(['role_id', 'menu_key'])
            ->groupBy('role_id')
            ->map(fn ($group) => $group->pluck('menu_key')->flip());

        return MobileMenu::forTenant($tenantId, $group)
            ->map(fn (MobileMenuItem $tile): array => [
                'id' => $tile->id,
                'key' => $tile->key,
                'label' => $tile->label,
                'icon' => $tile->icon,
                'color' => $tile->color,
                'route' => $tile->route,
                'isActive' => $tile->is_active,
                'locked' => MobileMenu::isLockedTab($tile),
                // One entry per role column, in the same order as `roles`.
                'visible' => $roles
                    ->map(fn (Role $role): bool => ! ($hiddenByRole[$role->id] ?? collect())->has($tile->visibilityKey()))
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Show or hide one Menu Cepat tile for one role.
     */
    public function toggleMobileMenuVisibility(Request $request): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'menu_id' => ['required', 'integer'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'visible' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $tile = $this->assertTenantTile($user, $validated['menu_id']);
        $role = $this->assertManageableRole($user, Role::findOrFail($validated['role_id']));

        abort_if(
            MobileMenu::isLockedTab($tile) && ! $validated['visible'],
            422,
            $tile->label.' tidak bisa disembunyikan — aplikasi butuh tab ini untuk bisa dipakai.',
        );

        RoleMenuVisibility::updateOrCreate(
            ['role_id' => $role->id, 'menu_key' => $tile->visibilityKey()],
            ['tenant_id' => $role->tenant_id ?? $user->tenant_id, 'is_visible' => $validated['visible']],
        );

        return back()->with('success', $validated['visible']
            ? $tile->label.' ditampilkan untuk '.$role->name
            : $tile->label.' disembunyikan dari '.$role->name);
    }

    /**
     * Turn one Menu Cepat tile on or off for the whole tenant, or rename it.
     */
    public function updateMobileMenu(Request $request): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'menu_id' => ['required', 'integer'],
            'label' => ['nullable', 'string', 'max:30'],
            'active' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $tile = $this->assertTenantTile($user, $validated['menu_id']);

        abort_if(
            MobileMenu::isLockedTab($tile) && ($validated['active'] ?? true) === false,
            422,
            $tile->label.' tidak bisa dimatikan — aplikasi butuh tab ini untuk bisa dipakai.',
        );

        $tile->update(array_filter([
            'label' => $validated['label'] ?? null,
        ], fn ($value): bool => $value !== null) + (
            array_key_exists('active', $validated) && $validated['active'] !== null
                ? ['is_active' => $validated['active']]
                : []
        ));

        return back()->with('success', 'Menu '.$tile->label.' diperbarui.');
    }

    /**
     * Reorder the Menu Cepat: the tiles arrive in the order they should appear.
     */
    public function reorderMobileMenu(Request $request): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        /** @var User $user */
        $user = $request->user();

        // Resolved before anything is written: an id from another tenant part
        // way down the list would otherwise leave the earlier tiles reordered
        // and the rest untouched, which is worse than refusing outright.
        $tiles = collect($validated['order'])
            ->map(fn ($menuId): MobileMenuItem => $this->assertTenantTile($user, (int) $menuId));

        DB::transaction(function () use ($tiles): void {
            $tiles->each(fn (MobileMenuItem $tile, int $position) => $tile->update(['sort_order' => $position]));
        });

        return back()->with('success', 'Urutan Menu Cepat disimpan.');
    }

    /**
     * The tile must belong to the actor's tenant — a phone menu is per company.
     */
    private function assertTenantTile(User $user, int $menuId): MobileMenuItem
    {
        abort_if($user->tenant_id === null, 404, 'Tidak ada tenant yang dipilih.');

        return MobileMenuItem::forTenant($user->tenant_id)->whereKey($menuId)->firstOrFail();
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
     * Allow or bar this role from the mobile (Flutter) app.
     *
     * Turning it off also revokes the outstanding phone sessions of everyone who
     * loses the app by it — otherwise a signed-in device keeps working until its
     * JWT expires, which reads as "the switch does nothing". Holders who keep the
     * app through another role are left alone.
     */
    public function toggleRoleMobile(Request $request, Role $role): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $role = $this->assertManageableRole($actor, $role);

        $role->update(['can_access_mobile' => $validated['enabled']]);

        if ($validated['enabled'] === false) {
            $locked = $role->users()->with('roles:id,can_access_mobile')->get()
                ->reject(fn (User $member): bool => $member->canAccessMobile())
                ->pluck('id');

            if ($locked->isNotEmpty()) {
                User::whereIn('id', $locked)->increment('token_version');
            }
        }

        return back()->with('success', $validated['enabled']
            ? 'Peran '.$role->name.' sekarang bisa memakai aplikasi mobile.'
            : 'Peran '.$role->name.' tidak bisa lagi masuk aplikasi mobile.');
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

        // Unlike the per-role switches, this one is tenant-wide, so the usual
        // self-lockout guard (you cannot edit your own role) does not cover it.
        abort_if(
            ! $validated['active'] && $this->gatesRequest($item->href, $request->path()),
            422,
            'Menu ini adalah layar yang sedang Anda pakai — mematikannya akan mengunci Anda dari sini.',
        );

        $item->update(['is_active' => $validated['active']]);

        return back()->with('success', $validated['active'] ? 'Menu diaktifkan' : 'Menu dinonaktifkan');
    }

    /**
     * Whether this menu is the very screen serving the current request.
     *
     * Turning such a menu off closes every route beneath its href — including
     * the endpoint that would turn it back on — because {@see EnsureAvanaAccess}
     * resolves a path to the longest menu href that prefixes it. A tenant admin
     * who flipped the Hak Akses row's own "Aktif" switch was then locked out of
     * access control for good, with only a super admin able to undo it.
     *
     * Matching by href rather than by menu key keeps the guard correct if the
     * screen ever moves, and leaves every other menu freely toggleable — those
     * can always be switched back on from here.
     */
    private function gatesRequest(?string $href, string $path): bool
    {
        if ($href === null) {
            return false;
        }

        $href = trim($href, '/');
        $path = trim($path, '/');

        return $href !== '' && ($path === $href || str_starts_with($path, $href.'/'));
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
     * The "Buat Peran" screen: the role's own details on top, every menu of the
     * tenant below it. A whole page rather than a dialog — picking menus is the
     * bulk of creating a role, and a modal cannot hold a hundred rows.
     */
    public function createRole(Request $request): Response
    {
        $this->ensureCanManageAccess($request);

        /** @var User $user */
        $user = $request->user();
        $isSuperAdmin = $user->isSuperAdmin();

        $rows = $this->matrixRows($user->tenant_id);

        // Existing roles as optional starting points. Sent with their current
        // selection so "tiru" fills the checkboxes in the browser — the admin sees
        // what they inherit before saving, and can edit it first.
        $sources = $this->tenantRoles($user->tenant_id, $isSuperAdmin)
            ->where('code', '!=', 'super_admin')
            ->with('permissions:id,code')
            ->orderBy('name')
            ->get();

        $hiddenByRole = RoleMenuVisibility::query()
            ->whereIn('role_id', $sources->pluck('id'))
            ->where('is_visible', false)
            ->get(['role_id', 'menu_key'])
            ->groupBy('role_id')
            ->map(fn ($group) => $group->pluck('menu_key')->flip());

        // Only tiles the company still runs: a switched-off one cannot be given
        // to a new role either.
        $tiles = $user->tenant_id === null
            ? collect()
            : MobileMenu::forTenant($user->tenant_id)->where('is_active', true);

        $templates = $sources->map(function (Role $role) use ($rows, $hiddenByRole, $tiles): array {
            $selection = [];

            foreach ($rows as $row) {
                $hidden = ($hiddenByRole[$role->id] ?? collect())->has($row['key']);

                if ($hidden || ! $this->roleGrantsMenu($role, $row['permissionModules'])) {
                    continue;
                }

                $cells = $this->roleActionCells($role, ['modules' => $row['permissionModules']]);

                $selection[$row['key']] = array_keys(array_filter($cells));
            }

            return [
                'id' => $role->id,
                'name' => $role->name,
                'canAccessMobile' => (bool) $role->can_access_mobile,
                'selection' => $selection,
                // The phone shortcuts this role shows, so "tiru" copies the
                // Menu Cepat along with the web menus.
                'mobileSelection' => $tiles
                    ->reject(fn (MobileMenuItem $tile): bool => ($hiddenByRole[$role->id] ?? collect())->has($tile->visibilityKey()))
                    ->pluck('key')
                    ->values()
                    ->all(),
            ];
        })->values()->all();

        return Inertia::render('avana/hak-akses/role-create', [
            'modules' => $this->menuPayload($rows, $user->tenant),
            'actions' => collect(PermissionCatalog::ACTIONS)
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'templates' => $templates,
            // Menu Cepat of the phone app, so the role's shortcuts are picked
            // here too rather than needing a second visit to Hak Akses.
            'mobileMenu' => $tiles
                ->map(fn (MobileMenuItem $tile): array => [
                    'key' => $tile->key,
                    'label' => $tile->label,
                    'icon' => $tile->icon,
                    'color' => $tile->color,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Create a new custom role for the current (or impersonated) tenant, together
     * with the menus and actions picked on the create screen.
     */
    public function storeRole(Request $request): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            // Starting from an existing role beats starting from nothing: a blank
            // role sees no menu at all, which reads as "the role is broken".
            'copy_from_role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'can_access_mobile' => ['nullable', 'boolean'],
            // The menu picks made on the create screen: every menu listed is shown
            // to the role, with the actions it may perform there.
            'menus' => ['nullable', 'array'],
            'menus.*.key' => ['required', 'string'],
            'menus.*.actions' => ['nullable', 'array'],
            'menus.*.actions.*' => ['string', 'in:'.implode(',', PermissionCatalog::actionKeys())],
            // Menu Cepat keys the role shows on the phone. Absent means "leave
            // the defaults" so the older create paths keep working.
            'mobile_menus' => ['nullable', 'array'],
            'mobile_menus.*' => ['string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $role = Role::create([
            'tenant_id' => $user->tenant_id,
            'code' => $this->uniqueRoleCode($validated['name'], $user->tenant_id),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
            'can_access_mobile' => $validated['can_access_mobile'] ?? true,
        ]);

        if (($validated['mobile_menus'] ?? null) !== null) {
            $this->applyMobileSelection($role, $validated['mobile_menus'], $user->tenant_id);
        }

        if (($validated['menus'] ?? null) !== null) {
            $this->applyMenuSelection($role, $validated['menus'], $user->tenant_id);

            return redirect()
                ->route('avana.hak-akses', ['tab' => $role->code])
                ->with('success', 'Peran '.$role->name.' dibuat dengan '.count($validated['menus']).' menu. Tambahkan penggunanya di tab ini.');
        }

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
     * A slug that no other role of the tenant holds. Two roles named alike would
     * otherwise share a code, and the screen keys its tabs by code.
     */
    private function uniqueRoleCode(string $name, ?int $tenantId): string
    {
        $base = Str::slug($name) ?: 'peran';
        $code = $base;
        $suffix = 2;

        while (Role::query()->where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }

    /**
     * Write the create screen's menu picks onto a role: the listed menus become
     * visible with the actions chosen, and every other menu of the tenant is
     * explicitly hidden — an unlisted menu is a menu the admin left out, not one
     * they forgot, and self-service rows would otherwise show up regardless.
     *
     * A picked menu with no action still gets `view`; without it the sidebar drops
     * the row and the pick would silently do nothing.
     *
     * @param  array<int, array{key: string, actions?: array<int, string>|null}>  $menus
     */
    /**
     * Write the create screen's Menu Cepat picks onto a role: every tile the
     * company runs gets a row, visible only for the ones listed.
     *
     * Written for every tile rather than only the hidden ones, so the role's
     * answer is recorded rather than inferred — a tile added to the company
     * later then defaults to visible without silently appearing as "chosen".
     *
     * @param  array<int, string>  $keys
     */
    private function applyMobileSelection(Role $role, array $keys, ?int $tenantId): void
    {
        if ($tenantId === null) {
            return;
        }

        $picked = array_flip($keys);

        foreach (MobileMenu::forTenant($tenantId) as $tile) {
            RoleMenuVisibility::updateOrCreate(
                ['role_id' => $role->id, 'menu_key' => $tile->visibilityKey()],
                ['tenant_id' => $role->tenant_id ?? $tenantId, 'is_visible' => isset($picked[$tile->key])],
            );
        }
    }

    private function applyMenuSelection(Role $role, array $menus, ?int $tenantId): void
    {
        $picked = collect($menus)->keyBy('key');
        $codes = [];

        foreach ($this->matrixRows($tenantId) as $row) {
            $pick = $picked->get($row['key']);

            RoleMenuVisibility::updateOrCreate(
                ['role_id' => $role->id, 'menu_key' => $row['key']],
                ['tenant_id' => $role->tenant_id ?? $tenantId, 'is_visible' => $pick !== null],
            );

            if ($pick === null) {
                continue;
            }

            // Self-service rows carry no action of their own — `own` is filtered
            // out of permissionModules because everybody holds it. But "holds it"
            // was only ever true of the seeded roles: a role built here started
            // with nothing, so picking Cuti Saya showed the menu and then 403'd
            // the page, because EnsureAvanaAccess still wants the module.
            if ($row['modules'] === ['own']) {
                $codes[] = 'own';

                continue;
            }

            if ($row['permissionModules'] === []) {
                continue;
            }

            $actions = $pick['actions'] ?? [];

            if ($actions === []) {
                $actions = ['view'];
            }

            foreach ($row['permissionModules'] as $module) {
                foreach ($actions as $action) {
                    $codes[] = $module.'.'.$action;
                }
            }
        }

        $wanted = Permission::query()->whereIn('code', array_unique($codes))->pluck('id');

        // `own` is a whole module rather than a `{module}.{action}` pair — its
        // four permissions are the self-service baseline, granted together.
        if (in_array('own', $codes, true)) {
            $wanted = $wanted->merge(Permission::query()->where('module', 'own')->pluck('id'));
        }

        $role->permissions()->sync($wanted->unique()->values());
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

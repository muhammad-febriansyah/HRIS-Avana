<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AccessController extends Controller
{
    /**
     * Roles that may view and manage the access-control matrix.
     *
     * @var array<int, string>
     */
    private const MANAGER_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Roles that implicitly hold every permission (rendered as a full row).
     * Only super_admin is immutable; every other role — including
     * admin_tenant_hr — reflects its real permissions so the matrix stays in
     * sync with the sidebar (which is permission-driven via AvanaNav).
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin'];

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
        'employee' => 'Self-service pribadi (ESS)',
    ];

    /**
     * Matrix rows mapping a UI module to the permission module prefixes it covers.
     * An empty `modules` list marks an always-on row (Dashboard).
     *
     * @var array<int, array{key: string, label: string, modules: array<int, string>}>
     */
    private const MODULES = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'modules' => []],
        ['key' => 'karyawan', 'label' => 'Karyawan', 'modules' => ['employee']],
        ['key' => 'absensi', 'label' => 'Absensi', 'modules' => ['attendance']],
        ['key' => 'cuti-lembur', 'label' => 'Cuti & Lembur', 'modules' => ['leave', 'overtime', 'wfh']],
        ['key' => 'payroll', 'label' => 'Payroll', 'modules' => ['payroll']],
        ['key' => 'bpjs-pajak', 'label' => 'BPJS & Pajak', 'modules' => ['bpjs', 'pph21']],
        ['key' => 'laporan', 'label' => 'Laporan', 'modules' => ['report']],
        ['key' => 'pengguna', 'label' => 'Pengguna', 'modules' => ['user']],
        ['key' => 'pengaturan', 'label' => 'Pengaturan', 'modules' => ['settings', 'role', 'permission']],
    ];

    /**
     * Render the access-control (RBAC) screen: role cards and the permission matrix.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManageAccess($request);

        $tenantId = $request->user()->tenant_id;

        $roleModels = $this->tenantRoles($tenantId)
            ->withCount(['permissions', 'users'])
            ->with('permissions:id,module')
            ->orderBy('id')
            ->get();

        $roles = $roleModels->values()->map(fn (Role $role, int $index): array => [
            'id' => $role->id,
            'name' => $role->name,
            'code' => $role->code,
            'desc' => self::ROLE_DESCRIPTIONS[$role->code] ?? '',
            'users' => $role->users_count,
            'color' => self::ROLE_COLORS[$index % count(self::ROLE_COLORS)],
        ])->all();

        $modules = collect(self::MODULES)
            ->map(fn (array $module): array => [
                'key' => $module['key'],
                'label' => $module['label'],
            ])
            ->all();

        $matrix = collect(self::MODULES)
            ->map(fn (array $module): array => $roleModels
                ->map(fn (Role $role): bool => $this->roleCoversModule($role, $module))
                ->all())
            ->all();

        return Inertia::render('avana/hak-akses', [
            'roles' => $roles,
            'modules' => $modules,
            'permHeaders' => $roleModels->pluck('name')->all(),
            'matrix' => $matrix,
        ]);
    }

    /**
     * Toggle every permission belonging to a module on or off for a role.
     */
    public function togglePermission(Request $request): RedirectResponse
    {
        $this->ensureCanManageAccess($request);

        $validated = $request->validate([
            'module_key' => ['required', 'string'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $module = collect(self::MODULES)->firstWhere('key', $validated['module_key']);

        abort_if($module === null || $module['modules'] === [], 422, 'Module cannot be toggled.');

        $tenantId = $request->user()->tenant_id;

        $role = $this->tenantRoles($tenantId)
            ->whereKey($validated['role_id'])
            ->firstOrFail();

        // System super-admin access is immutable.
        abort_if($role->code === 'super_admin', 403, 'Super admin access cannot be modified.');

        $permissionIds = Permission::query()
            ->whereIn('module', $module['modules'])
            ->pluck('id');

        $hasAny = $role->permissions()
            ->whereIn('permissions.id', $permissionIds)
            ->exists();

        if ($hasAny) {
            $role->permissions()->detach($permissionIds);
        } else {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        return back()->with('success', 'Hak akses diperbarui');
    }

    /**
     * Create a new custom role for the current tenant.
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
     * Base query for roles visible to a tenant: tenant-owned plus global roles.
     */
    private function tenantRoles(?int $tenantId): Builder
    {
        return Role::query()->where(function ($query) use ($tenantId): void {
            $query->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
        });
    }

    /**
     * Whether a matrix cell should be checked for the given role/module pairing.
     *
     * @param  array{key: string, label: string, modules: array<int, string>}  $module
     */
    private function roleCoversModule(Role $role, array $module): bool
    {
        if (in_array($role->code, self::PRIVILEGED_ROLES, true)) {
            return true;
        }

        if ($module['modules'] === []) {
            return true;
        }

        return $role->permissions->whereIn('module', $module['modules'])->isNotEmpty();
    }

    /**
     * Abort with 403 unless the user is a privileged role or holds role.manage.
     */
    private function ensureCanManageAccess(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::MANAGER_ROLES)->isNotEmpty();

        $hasManagePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains('role.manage');

        abort_unless($isPrivileged || $hasManagePermission, 403);
    }
}

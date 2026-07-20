<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
     * Matrix rows mapping a UI menu to the permission module prefixes it covers.
     * An empty `modules` list marks an always-on row (Dashboard) with no actions.
     *
     * @var array<int, array{key: string, label: string, modules: array<int, string>}>
     */
    private const MODULES = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'modules' => []],
        ['key' => 'karyawan', 'label' => 'Karyawan', 'modules' => ['employee']],
        ['key' => 'dokumen', 'label' => 'Dokumen & Surat', 'modules' => ['document', 'letter']],
        ['key' => 'offboarding', 'label' => 'Offboarding', 'modules' => ['offboarding']],
        ['key' => 'absensi', 'label' => 'Absensi', 'modules' => ['attendance']],
        ['key' => 'jadwal', 'label' => 'Timesheet & Shift', 'modules' => ['timesheet', 'shift_swap']],
        ['key' => 'cuti-lembur', 'label' => 'Cuti & Lembur', 'modules' => ['leave', 'overtime', 'wfh']],
        ['key' => 'delegasi', 'label' => 'Delegasi Approval', 'modules' => ['delegation']],
        ['key' => 'payroll', 'label' => 'Payroll', 'modules' => ['payroll']],
        ['key' => 'bpjs-pajak', 'label' => 'BPJS & Pajak', 'modules' => ['bpjs', 'pph21']],
        ['key' => 'keuangan', 'label' => 'Klaim, Pinjaman & Jurnal', 'modules' => ['claim', 'loan', 'journal', 'budget', 'salary_structure']],
        ['key' => 'rekrutmen', 'label' => 'Rekrutmen & Onboarding', 'modules' => ['recruitment', 'onboarding']],
        ['key' => 'kinerja', 'label' => 'Kinerja & Talenta', 'modules' => ['performance', 'okr', 'competency', 'talent']],
        ['key' => 'pembelajaran', 'label' => 'Pembelajaran (LMS)', 'modules' => ['learning']],
        ['key' => 'layanan', 'label' => 'Layanan & Engagement', 'modules' => ['helpdesk', 'announcement', 'survey', 'calendar', 'ai', 'asset', 'crm']],
        ['key' => 'laporan', 'label' => 'Laporan', 'modules' => ['report']],
        ['key' => 'analitik', 'label' => 'HR Analytics', 'modules' => ['dynamic_report']],
        ['key' => 'perusahaan', 'label' => 'Perusahaan', 'modules' => ['branch', 'department', 'position', 'organization']],
        ['key' => 'pengguna', 'label' => 'Pengguna', 'modules' => ['user']],
        ['key' => 'pengaturan', 'label' => 'Pengaturan', 'modules' => ['settings', 'role', 'permission']],
        ['key' => 'audit', 'label' => 'Audit Trail', 'modules' => ['audit']],
    ];

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

        $modules = collect(self::MODULES)
            ->map(fn (array $module): array => [
                'key' => $module['key'],
                'label' => $module['label'],
                'actionable' => $module['modules'] !== [],
            ])
            ->all();

        // matrix[rowIdx][roleIdx] = { view: bool, create: bool, ... }
        $matrix = collect(self::MODULES)
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
        ]);
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

        $module = collect(self::MODULES)->firstWhere('key', $validated['module_key']);

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

<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AvanaNav;
use App\Support\PermissionCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Everything a brand-new client tenant needs before anyone can log into it:
 * the four system roles with their permission sets, the editable sidebar menu,
 * and at least one Admin Tenant / HR account.
 *
 * Without this a tenant created from the Klien page has no roles at all, so the
 * user form offers nothing to assign and the account that gets created lands on
 * an empty sidebar and a 403 on every page.
 */
class TenantProvisioner
{
    /**
     * The base permission set each system role holds. Mirrors the demo seeder so
     * a tenant created from the UI behaves exactly like a seeded one.
     */
    private const ROLES = [
        'admin_tenant_hr' => 'Admin Tenant / HR',
        'manager' => 'Manager',
        'finance' => 'Finance',
        'employee' => 'Karyawan',
    ];

    /**
     * Give the tenant its feature modules, roles, and menu. Safe to re-run:
     * every write is a firstOrCreate / syncWithoutDetaching.
     */
    public function provision(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            $this->applyPackageFeatures($tenant);
            $this->provisionRoles($tenant);
            AvanaNav::seedDefaultsFor($tenant->id);
        });
    }

    /**
     * Line the tenant's enabled features up with what their package entitles them
     * to: modules in the package are switched on, everything else off. A package
     * that scopes nothing (or no package at all) grants the whole catalogue, so
     * pricing tiers stay opt-in rather than silently stripping existing clients.
     */
    public function applyPackageFeatures(Tenant $tenant): void
    {
        $tenant->loadMissing('package');
        $entitled = $tenant->package?->entitledFeatureIds() ?? [];

        if ($entitled === []) {
            $this->enableAllFeatures($tenant);

            return;
        }

        foreach (Feature::query()->pluck('id') as $featureId) {
            $tenant->features()->updateOrCreate(
                ['feature_id' => $featureId],
                ['is_enabled' => in_array((int) $featureId, $entitled, true)],
            );
        }
    }

    /**
     * Create an Admin Tenant / HR login for the tenant. Returns the account and
     * the plain password so the caller can show it once — it is never readable
     * again after this.
     *
     * @return array{user: User, password: string}
     */
    public function createAdmin(Tenant $tenant, string $name, string $email, ?string $password = null): array
    {
        $plain = filled($password) ? $password : $this->generatePassword();

        $user = DB::transaction(function () use ($tenant, $name, $email, $plain): User {
            // A tenant created before this service existed may still have no
            // roles, features or menu — provision on demand, otherwise the new
            // admin logs into a near-empty sidebar.
            if (Role::where('tenant_id', $tenant->id)->where('code', 'admin_tenant_hr')->doesntExist()) {
                $this->enableAllFeatures($tenant);
                $this->provisionRoles($tenant);
                AvanaNav::seedDefaultsFor($tenant->id);
            }

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'email' => $email,
                'password' => $plain,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $role = Role::where('tenant_id', $tenant->id)->where('code', 'admin_tenant_hr')->first();

            if ($role !== null) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            return $user;
        });

        return ['user' => $user, 'password' => $plain];
    }

    /**
     * Issue a new password for an existing tenant account, returning the plain
     * value for the one-time hand-off.
     */
    public function resetPassword(User $user, ?string $password = null): string
    {
        $plain = filled($password) ? $password : $this->generatePassword();

        $user->forceFill(['password' => $plain])->save();

        return $plain;
    }

    /**
     * Turn on every feature module for the tenant. The sidebar is filtered by
     * these, so a tenant with none sees almost nothing regardless of its roles.
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
     * Create the four system roles for the tenant and attach their permissions.
     */
    private function provisionRoles(Tenant $tenant): void
    {
        $permissions = Permission::all();

        foreach (self::ROLES as $code => $name) {
            $role = Role::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                ['name' => $name, 'is_system' => true],
            );

            $assigned = $this->permissionsFor($code, $permissions);

            if ($assigned->isEmpty()) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching($assigned->pluck('id'));

            // Fail-open baseline: for every module the role holds, grant its full
            // action set so action-level checks never remove access it should have.
            $role->permissions()->syncWithoutDetaching(
                Permission::whereIn(
                    'code',
                    PermissionCatalog::actionCodesForModules($assigned->pluck('module')->all()),
                )->pluck('id'),
            );
        }
    }

    /**
     * The slice of the permission table a system role starts with.
     *
     * @param  Collection<int, Permission>  $permissions
     * @return Collection<int, Permission>
     */
    private function permissionsFor(string $code, Collection $permissions): Collection
    {
        return match ($code) {
            // Everything except the platform-level tenant administration.
            'admin_tenant_hr' => $permissions->reject(
                fn (Permission $permission): bool => str_starts_with($permission->code, 'tenant.'),
            ),
            'manager' => $permissions->filter(
                fn (Permission $permission): bool => str_starts_with($permission->code, 'team.')
                    || str_starts_with($permission->code, 'own.'),
            ),
            // Finance settles the money, not the people administration.
            'finance' => $permissions->filter(
                fn (Permission $permission): bool => in_array($permission->module, [
                    'claim', 'loan', 'journal', 'budget', 'salary_structure', 'payroll', 'report',
                ], true) || str_starts_with($permission->code, 'own.'),
            ),
            default => $permissions->filter(
                fn (Permission $permission): bool => str_starts_with($permission->code, 'own.'),
            ),
        };
    }

    /**
     * A readable throwaway password: two words plus digits, easy to dictate over
     * the phone but not guessable.
     */
    private function generatePassword(): string
    {
        return 'Avana'.Str::upper(Str::random(3)).random_int(1000, 9999);
    }
}

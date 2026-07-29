<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AvanaNav;
use App\Support\PermissionCatalog;
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
     * The single role a brand-new tenant is given: the Admin Tenant / HR account
     * that will build the rest.
     *
     * Manager, Finance and Karyawan used to be seeded alongside it. They are not
     * every company's org chart — a factory needs Supervisor Shift, an agency
     * needs Account Manager — and a role nobody asked for is a role nobody
     * curates, so tenants inherited three half-right roles they then had to work
     * around. The admin now creates exactly the roles they need on Buat Peran,
     * choosing the menus each one sees.
     */
    private const ADMIN_ROLE_CODE = 'admin_tenant_hr';

    private const ADMIN_ROLE_NAME = 'Admin Tenant / HR';

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
            if (Role::where('tenant_id', $tenant->id)->where('code', self::ADMIN_ROLE_CODE)->doesntExist()) {
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

            $role = Role::where('tenant_id', $tenant->id)->where('code', self::ADMIN_ROLE_CODE)->first();

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
     * Create the tenant's Admin Tenant / HR role and attach its permissions:
     * everything except the platform-level tenant administration, which belongs
     * to the super admin alone.
     */
    private function provisionRoles(Tenant $tenant): void
    {
        $role = Role::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => self::ADMIN_ROLE_CODE],
            ['name' => self::ADMIN_ROLE_NAME, 'is_system' => true],
        );

        $assigned = Permission::query()
            ->where('code', 'not like', 'tenant.%')
            ->get();

        if ($assigned->isEmpty()) {
            return;
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

    /**
     * A readable throwaway password: two words plus digits, easy to dictate over
     * the phone but not guessable.
     */
    private function generatePassword(): string
    {
        return 'Avana'.Str::upper(Str::random(3)).random_int(1000, 9999);
    }
}

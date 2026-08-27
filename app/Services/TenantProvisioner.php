<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DayCalcMethod;
use App\Models\Feature;
use App\Models\MenuItem;
use App\Models\MobileMenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AvanaNav;
use App\Support\DayCalcDefaults;
use App\Support\MobileMenu;
use App\Support\OnboardingStatus;
use App\Support\PermissionCatalog;
use App\Support\ShiftDefaults;
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
     * Give the tenant its feature modules, company, roles, and menu. Safe to
     * re-run: every write is a firstOrCreate / syncWithoutDetaching.
     */
    public function provision(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            $this->applyPackageFeatures($tenant);

            foreach ($this->prerequisites() as $prerequisite) {
                ($prerequisite['repair'])($tenant);
            }
        });
    }

    /**
     * Everything provisioning must leave behind, each paired with the repair
     * that puts it back. One list rather than two so a prerequisite can never
     * be checked without also being fixable — {@see missingPrerequisites()}
     * reads the checks, {@see repairMissing()} and {@see provision()} run the
     * repairs, and a new entry here is picked up by all three at once.
     *
     * Feature modules are deliberately absent: they are the one thing a tenant
     * legitimately edits afterwards (a mitra tailors them per client), and
     * {@see applyPackageFeatures()} resets them to the package, so repairing
     * them on a schedule would undo those choices. Provisioning applies them
     * once, at creation.
     *
     * @return array<string, array{label: string, present: callable(Tenant): bool, repair: callable(Tenant): void}>
     */
    private function prerequisites(): array
    {
        return [
            'perusahaan' => [
                'label' => 'profil perusahaan',
                // A tenant still on the "Mulai" checklist has none on purpose;
                // its absence is what keeps asking them for it.
                'present' => fn (Tenant $tenant): bool => $tenant->requires_onboarding || $tenant->company()->exists(),
                'repair' => fn (Tenant $tenant) => $this->provisionCompany($tenant),
            ],
            'peran' => [
                'label' => 'peran Admin Tenant / HR',
                'present' => fn (Tenant $tenant): bool => Role::forTenant($tenant->id)->where('code', self::ADMIN_ROLE_CODE)->exists(),
                'repair' => fn (Tenant $tenant) => $this->provisionRoles($tenant),
            ],
            'menu' => [
                'label' => 'menu sidebar',
                'present' => fn (Tenant $tenant): bool => MenuItem::forTenant($tenant->id)->exists(),
                'repair' => fn (Tenant $tenant) => AvanaNav::seedDefaultsFor($tenant->id),
            ],
            'menu_mobile' => [
                'label' => 'menu aplikasi mobile',
                'present' => fn (Tenant $tenant): bool => MobileMenuItem::forTenant($tenant->id)->exists(),
                'repair' => fn (Tenant $tenant) => MobileMenu::seedDefaultsFor($tenant->id),
            ],
            // Operational reference data payroll and the roster read before a
            // tenant can run either: without a Perhitungan Hari method every
            // component is silently paid unprorated, and without the M/A/N
            // legend a rotation cannot be expressed at all.
            'perhitungan_hari' => [
                'label' => 'metode Perhitungan Hari',
                'present' => fn (Tenant $tenant): bool => DayCalcMethod::forTenant($tenant->id)->exists(),
                'repair' => fn (Tenant $tenant) => DayCalcDefaults::seedDefaultsFor($tenant->id),
            ],
            'shift' => [
                'label' => 'shift dasar M/A/N',
                'present' => fn (Tenant $tenant): bool => Shift::forTenant($tenant->id)->exists(),
                'repair' => fn (Tenant $tenant) => ShiftDefaults::seedDefaultsFor($tenant->id),
            ],
        ];
    }

    /**
     * Which prerequisites this tenant is missing, as human-readable labels
     * keyed by prerequisite. Empty means the tenant is fully provisioned.
     *
     * @return array<string, string>
     */
    public function missingPrerequisites(Tenant $tenant): array
    {
        $missing = [];

        foreach ($this->prerequisites() as $key => $prerequisite) {
            if (! ($prerequisite['present'])($tenant)) {
                $missing[$key] = $prerequisite['label'];
            }
        }

        return $missing;
    }

    /**
     * Put back only what the tenant is actually missing, returning the labels
     * of what was repaired.
     *
     * Narrower than re-running {@see provision()} on purpose: this runs against
     * live tenants that have been in use for months, and touching a row that is
     * already there — however idempotent the write — risks undoing a setting
     * someone chose by hand.
     *
     * @return array<string, string>
     */
    public function repairMissing(Tenant $tenant): array
    {
        $missing = $this->missingPrerequisites($tenant);

        if ($missing === []) {
            return [];
        }

        DB::transaction(function () use ($tenant, $missing): void {
            $prerequisites = $this->prerequisites();

            foreach (array_keys($missing) as $key) {
                ($prerequisites[$key]['repair'])($tenant);
            }
        });

        return $missing;
    }

    /**
     * Give the tenant the company record the rest of the product assumes exists:
     * branches hang off it, payroll and document branding print its name, and the
     * mobile API refuses to log anyone in whose tenant has none.
     *
     * A tenant heading into the "Mulai" checklist is skipped on purpose. An absent
     * company is exactly what {@see OnboardingStatus} reads to know the profile
     * still needs filling, so creating one here would tick that step off on the
     * tenant's behalf and let them past it having entered nothing.
     */
    public function provisionCompany(Tenant $tenant): void
    {
        if ($tenant->requires_onboarding) {
            return;
        }

        Company::firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['name' => filled($tenant->company_name) ? $tenant->company_name : $tenant->name],
        );
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

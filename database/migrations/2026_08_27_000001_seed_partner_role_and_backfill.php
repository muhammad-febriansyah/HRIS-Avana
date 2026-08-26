<?php

use App\Models\Partner;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ReferralPartnerService;
use Illuminate\Database\Migrations\Migration;

/**
 * The `partner` role only ever existed in AvanaDemoSeeder, so an environment
 * seeded before the referral feature landed — production among them — never
 * got it. Approving a mitra application there produced a login with no role at
 * all: {@see ReferralPartnerService::approve()} skipped the
 * assignment silently, EnsurePartner then refused /mitra, and the dashboard's
 * partner redirect never fired — leaving the account on the tenant HR
 * dashboard with an empty sidebar.
 *
 * Creates the role (with its `referral` permissions) and backfills every
 * existing partner that is missing it. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = collect(['referral.view', 'referral.manage'])
            ->map(fn (string $code): Permission => Permission::firstOrCreate(
                ['code' => $code],
                [
                    'module' => 'referral',
                    'action' => str($code)->after('.')->value(),
                    'name' => $code,
                ],
            ))
            ->pluck('id');

        // Tenant-less like super_admin: a referral partner sits outside every
        // client, so its role carries no tenant either. Mobile is off — the
        // app is employee self-service and a partner has no employee record,
        // so a partner login there only reaches the "account not linked" wall.
        $role = Role::firstOrCreate(
            ['tenant_id' => null, 'code' => 'partner'],
            ['name' => 'Mitra Referral', 'is_system' => true, 'can_access_mobile' => false],
        );

        $role->permissions()->syncWithoutDetaching($permissionIds);

        Partner::query()
            ->with('user.roles:id,code')
            ->get()
            ->each(function (Partner $partner) use ($role): void {
                $user = $partner->user;

                if ($user === null || $user->roles->contains('code', 'partner')) {
                    return;
                }

                $user->roles()->syncWithoutDetaching([$role->id]);
            });
    }

    public function down(): void
    {
        // Non-destructive: leave the role and its assignments in place.
    }
};

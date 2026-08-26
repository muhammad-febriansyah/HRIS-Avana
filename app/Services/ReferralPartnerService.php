<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns an approved partner application into a real login: a `partner`-role
 * User plus its {@see Partner} referral profile. Unlike
 * {@see TenantProvisioner::createAdmin()}, the password isn't generated here
 * — the applicant already chose it on the registration form, so approval
 * just carries that hash over and the partner can log in immediately.
 */
final class ReferralPartnerService
{
    /**
     * @return array{user: User, partner: Partner}
     */
    public function approve(PartnerRegistration $registration, User $approver): array
    {
        return DB::transaction(function () use ($registration, $approver): array {
            // Already hashed (the model casts `password` as `hashed` on both
            // sides), so this is carried straight into the User row without
            // ever touching the plaintext.
            $user = User::create([
                'tenant_id' => null,
                'name' => $registration->full_name,
                'email' => $registration->email,
                'password' => $registration->password,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            // Created rather than looked up: skipping the assignment when the
            // role is missing produced a login with no role at all, which
            // EnsurePartner then refused — the approved partner could not
            // reach /mitra, and DashboardController never redirected them
            // there either. See 2026_08_27_000001_seed_partner_role_and_backfill.
            $role = Role::firstOrCreate(
                ['tenant_id' => null, 'code' => 'partner'],
                ['name' => 'Mitra Referral', 'is_system' => true, 'can_access_mobile' => false],
            );

            $user->roles()->syncWithoutDetaching([$role->id]);

            $partner = Partner::create([
                'user_id' => $user->id,
                'code' => $this->uniqueCode($registration->full_name),
                'status' => 'active',
                'phone' => $registration->whatsapp,
                'created_by' => $approver->id,
            ]);

            $registration->update(['status' => 'approved']);

            return ['user' => $user, 'partner' => $partner];
        });
    }

    /**
     * A short, shareable referral code derived from the applicant's name —
     * `?ref=` carries this in the URL, so it stays readable rather than a
     * raw id.
     */
    private function uniqueCode(string $name): string
    {
        $base = Str::upper(Str::slug(Str::limit(preg_replace('/\s+/', '', $name) ?? $name, 10, ''), ''));
        $base = $base !== '' ? $base : 'MITRA';

        $code = $base;
        $suffix = 1;

        while (Partner::withTrashed()->where('code', $code)->exists()) {
            $code = $base.$suffix;
            $suffix++;
        }

        return $code;
    }
}

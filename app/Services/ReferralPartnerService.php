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
 * User plus its {@see Partner} referral profile. Mirrors
 * {@see TenantProvisioner::createAdmin()} — a generated
 * password handed back once for the super admin to relay.
 */
final class ReferralPartnerService
{
    /**
     * @return array{user: User, partner: Partner, password: string}
     */
    public function approve(PartnerRegistration $registration, User $approver): array
    {
        return DB::transaction(function () use ($registration, $approver): array {
            $plain = $this->generatePassword();

            $user = User::create([
                'tenant_id' => null,
                'name' => $registration->full_name,
                'email' => $registration->email,
                'password' => $plain,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $role = Role::query()->whereNull('tenant_id')->where('code', 'partner')->first();

            if ($role !== null) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            $partner = Partner::create([
                'user_id' => $user->id,
                'code' => $this->uniqueCode($registration->full_name),
                'status' => 'active',
                'phone' => $registration->whatsapp,
                'created_by' => $approver->id,
            ]);

            $registration->update(['status' => 'approved']);

            return ['user' => $user, 'partner' => $partner, 'password' => $plain];
        });
    }

    private function generatePassword(): string
    {
        return 'Mitra'.Str::upper(Str::random(3)).random_int(1000, 9999);
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

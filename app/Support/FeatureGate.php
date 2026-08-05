<?php

namespace App\Support;

use App\Models\Feature;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Feature gating for the screens a sidebar menu does not own on its own.
 *
 * Most tenant features map one-to-one onto a menu leaf, so `AvanaNav` hides the
 * leaf and `EnsureAvanaAccess` closes the route as soon as the Super Admin
 * switches the feature off. A handful of features have no leaf of their own —
 * they live inside a tab of a page another feature owns — which left their
 * toggles in Kelola Fitur writing to the database and changing nothing. This
 * class is the gate for those: the page hides the tab and the endpoints behind
 * it answer 403.
 */
final class FeatureGate
{
    /**
     * Features with no menu leaf of their own, mapped to the menu key whose page
     * hosts them. The guard test walks this list together with the AvanaNav
     * leaves to prove every feature toggle reaches something.
     *
     * @var array<string, string>
     */
    public const TAB_FEATURES = [
        'overtime' => 'cuti',
        'wfh' => 'cuti',
        'bpjs' => 'payroll-config',
        'pph21' => 'payroll-config',
    ];

    /**
     * Enabled feature codes for the user's tenant, or null when no gate applies
     * — a super admin reaches everything, and a platform user has no tenant to
     * read features from.
     *
     * @return Collection<int, string>|null
     */
    public static function codesFor(?User $user): ?Collection
    {
        if ($user === null || $user->tenant_id === null || $user->isSuperAdmin()) {
            return null;
        }

        $tenant = $user->tenant;

        if ($tenant === null) {
            return null;
        }

        // Deliberately not memoised: a stale set would keep a switched-off
        // module reachable, and the query is one indexed read on a short table.
        return Feature::query()
            ->whereIn('id', $tenant->features()->where('is_enabled', true)->select('feature_id'))
            ->pluck('code');
    }

    /**
     * Whether the user's tenant has the feature switched on.
     */
    public static function allows(?User $user, string $code): bool
    {
        $codes = self::codesFor($user);

        return $codes === null || $codes->contains($code);
    }

    /**
     * The on/off state of several features at once, keyed by code — what a page
     * passes to the frontend so it can drop the tabs the tenant does not have.
     *
     * @param  array<int, string>  $codes
     * @return array<string, bool>
     */
    public static function map(?User $user, array $codes): array
    {
        $enabled = self::codesFor($user);

        $out = [];

        foreach ($codes as $code) {
            $out[$code] = $enabled === null || $enabled->contains($code);
        }

        return $out;
    }

    /**
     * Abort with 403 unless the feature is on for the user's tenant.
     *
     * The message matters on the phone: the app shows whatever the body says,
     * and a bare 403 leaves an employee reading "gagal" with no idea that the
     * company simply does not run this module.
     */
    public static function ensure(?User $user, string $code, ?string $message = null): void
    {
        abort_unless(
            self::allows($user, $code),
            403,
            $message ?? 'Fitur ini tidak aktif untuk perusahaan Anda.',
        );
    }

    /**
     * Abort with 403 unless at least one of the features is on — for a page
     * that would be empty with all of them off.
     *
     * @param  array<int, string>  $codes
     */
    public static function ensureAny(?User $user, array $codes, ?string $message = null): void
    {
        foreach ($codes as $code) {
            if (self::allows($user, $code)) {
                return;
            }
        }

        abort(403, $message ?? 'Fitur ini tidak aktif untuk perusahaan Anda.');
    }
}

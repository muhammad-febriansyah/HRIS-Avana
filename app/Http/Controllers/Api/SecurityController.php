<?php

namespace App\Http\Controllers\Api;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;

/**
 * Account security for the mobile app: change password, review the bound
 * device(s), and sign out everywhere. "Sign out" is enforced against stateless
 * JWTs by bumping the per-user token version (see EnsureFreshToken).
 */
class SecurityController extends Controller
{
    use PasswordValidationRules;

    /**
     * Change the account password. Requires the current password, invalidates
     * every previously-issued token, and returns a fresh token so the current
     * session continues uninterrupted.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password:api'],
            'password' => $this->passwordRules(),
        ]);

        /** @var User $user */
        $user = $request->user();

        $user->forceFill(['password' => $request->string('password')->value()])->save();
        $user->increment('token_version');

        $token = auth('api')->login($user);

        return response()->json([
            'message' => 'Kata sandi berhasil diperbarui.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    /**
     * Where the caller stands on two-factor: off, half-enrolled, or on.
     *
     * The secret and its QR only travel while an enrolment is waiting to be
     * confirmed, and the recovery codes only once it has been — there is no
     * request that returns both, and none returns either to an account that
     * never started.
     */
    public function twoFactorStatus(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $enabled = $user->hasEnabledTwoFactorAuthentication();
        $confirming = ! $enabled && $user->two_factor_secret !== null;

        return response()->json([
            'data' => [
                'enabled' => $enabled,
                'confirming' => $confirming,
                'qr_svg' => $confirming ? $user->twoFactorQrCodeSvg() : null,
                'setup_key' => $confirming
                    ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret)
                    : null,
                // Tapping this hands the account straight to the authenticator
                // app, so nobody has to copy 32 characters by hand.
                'setup_url' => $confirming ? $user->twoFactorQrCodeUrl() : null,
                'recovery_codes' => $enabled && $user->two_factor_recovery_codes
                    ? $user->recoveryCodes()
                    : [],
            ],
        ]);
    }

    /**
     * Begin enrolment: mint the secret and hand back what an authenticator app
     * needs to read it. The account is not protected until a code confirms it.
     *
     * The password is asked for again because a phone found unlocked is exactly
     * the situation two-factor exists to survive — the web asks for it too.
     */
    public function enableTwoFactor(Request $request, EnableTwoFactorAuthentication $enable): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'string', 'current_password:api']]);

        /** @var User $user */
        $user = $request->user();

        $enable($user);

        return $this->twoFactorStatus($request);
    }

    /**
     * Finish enrolment with a code from the authenticator app. Only now does
     * the account count as protected, and only now do recovery codes exist.
     */
    public function confirmTwoFactor(Request $request, ConfirmTwoFactorAuthentication $confirm): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        /** @var User $user */
        $user = $request->user();

        abort_if($user->two_factor_secret === null, 422, 'Aktifkan verifikasi dua langkah terlebih dahulu.');

        $confirm($user, $request->string('code')->value());

        return $this->twoFactorStatus($request);
    }

    /**
     * Turn it off, secret and recovery codes with it.
     */
    public function disableTwoFactor(Request $request, DisableTwoFactorAuthentication $disable): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'string', 'current_password:api']]);

        /** @var User $user */
        $user = $request->user();

        $disable($user);

        return $this->twoFactorStatus($request);
    }

    /**
     * Issue a fresh set of recovery codes, retiring the old ones.
     */
    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'string', 'current_password:api']]);

        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasEnabledTwoFactorAuthentication(), 422, 'Verifikasi dua langkah belum aktif.');

        $generate($user);

        return $this->twoFactorStatus($request);
    }

    /**
     * Store the Firebase (FCM) push token for the caller's current device so the
     * backend can send notifications to it. Updates the already-bound device row.
     */
    public function registerFcmToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string'],
            'fcm_token' => ['required', 'string'],
        ]);

        $userId = $request->user()->id;

        $updated = UserDevice::where('user_id', $userId)
            ->where('device_id', $data['device_id'])
            ->update(['fcm_token' => $data['fcm_token']]);

        // The exact device may not be bound (e.g. single-device binding kept the
        // previous device). Fall back to the user's active device so pushes still
        // reach them.
        if ($updated === 0) {
            UserDevice::where('user_id', $userId)
                ->where('status', 'active')
                ->update(['fcm_token' => $data['fcm_token']]);
        }

        return response()->json(['ok' => true]);
    }

    /** The devices bound to this account (active first). */
    public function devices(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentDeviceId = $request->query('device_id');

        $devices = UserDevice::where('user_id', $user->id)
            ->orderByRaw("status = 'active' desc")
            ->orderByDesc('last_login_at')
            ->get()
            ->map(fn (UserDevice $device): array => [
                'id' => $device->id,
                'device_name' => $device->device_name ?? $device->model,
                'model' => $device->model,
                'platform' => $device->platform,
                'os_version' => $device->os_version,
                'app_version' => $device->app_version,
                'status' => $device->status,
                'is_current' => $currentDeviceId !== null && $device->device_id === $currentDeviceId,
                'last_login_at' => $device->last_login_at?->toIso8601String(),
                'bound_at' => $device->bound_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $devices]);
    }

    /**
     * Sign out of all devices: invalidate every outstanding token and release
     * the device binding so a fresh sign-in can re-bind. The caller must log in
     * again.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->increment('token_version');

        UserDevice::where('user_id', $user->id)
            ->active()
            ->update(['status' => 'reset', 'reset_at' => now()]);

        auth('api')->logout();

        return response()->json(['message' => 'Anda telah keluar dari semua perangkat.']);
    }
}

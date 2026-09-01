<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\AttendancePolicy;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\LoginSecurity;
use App\Support\MobileMenu;
use App\Support\PrivateFile;
use App\Support\TenantTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * JWT authentication for the AvanaHR mobile app. Employees sign in with their
 * email + password and receive a JWT bearer token.
 */
class AuthController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * How long a passed password stays exchangeable for a token.
     */
    private const CHALLENGE_TTL_MINUTES = 5;

    /**
     * Wrong codes a single challenge tolerates before it is thrown away.
     */
    private const CHALLENGE_MAX_ATTEMPTS = 5;

    /**
     * Authenticate and issue a JWT.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $token = auth('api')->attempt([
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        if ($token === false) {
            throw ValidationException::withMessages([
                'email' => ['Email atau kata sandi salah.'],
            ]);
        }

        $user = auth('api')->user();

        if ($user->status !== null && $user->status !== 'active') {
            auth('api')->logout();

            throw ValidationException::withMessages([
                'email' => ['Akun tidak aktif.'],
            ]);
        }

        // Web access and phone access are set separately: a role can hold every
        // web menu and still be barred from the app (Hak Akses → "Aplikasi Mobile").
        if (! $user->canAccessMobile()) {
            auth('api')->logout();

            throw ValidationException::withMessages([
                'email' => ['Peran akun ini tidak diizinkan memakai aplikasi mobile.'],
            ]);
        }

        if ($user->tenant === null || $user->tenant->company()->doesntExist()) {
            auth('api')->logout();

            throw ValidationException::withMessages([
                'email' => ['Akun belum terhubung ke perusahaan.'],
            ]);
        }

        // The password was right, but it is not the whole answer for an account
        // that opted into a second factor. Drop the token minted a moment ago
        // and hand back a challenge instead: it opens nothing but the exchange
        // below. Device binding waits until the second factor lands, so holding
        // the password alone cannot claim an account's device slot.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            auth('api')->logout();

            return response()->json([
                'two_factor_required' => true,
                'challenge_token' => $this->issueChallenge($user, $data),
                'expires_in' => self::CHALLENGE_TTL_MINUTES * 60,
            ]);
        }

        if (($deviceRejection = $this->bindDevice($user, $data)) !== null) {
            auth('api')->logout();

            return $deviceRejection;
        }

        $this->recordMobileLogin($user, $data);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Exchange a login challenge plus a valid second factor for the JWT that
     * `login` would otherwise have returned.
     */
    public function twoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $key = self::challengeKey($data['challenge_token']);
        $challenge = Cache::get($key);

        if (! is_array($challenge)) {
            return $this->challengeExpired();
        }

        $user = User::query()->whereKey($challenge['user_id'])->first();

        // The factor could have been turned off from the web between the two
        // calls, and the account's standing is worth re-reading either way.
        if ($user === null || ! $user->hasEnabledTwoFactorAuthentication()) {
            Cache::forget($key);

            return $this->challengeExpired();
        }

        if (! $this->passesSecondFactor($user, $data)) {
            // A challenge is single-use for a success and near enough for a
            // failure: a handful of wrong codes retires it, so the five-minute
            // window cannot be spent guessing at whatever rate an attacker can
            // spread across addresses.
            $challenge['attempts']++;

            $challenge['attempts'] >= self::CHALLENGE_MAX_ATTEMPTS
                ? Cache::forget($key)
                : Cache::put($key, $challenge, now()->addMinutes(self::CHALLENGE_TTL_MINUTES));

            throw ValidationException::withMessages([
                'code' => ['Kode verifikasi tidak valid.'],
            ]);
        }

        Cache::forget($key);

        // Binding runs before the token is minted rather than after, so a
        // rejected device never has one to invalidate.
        if (($deviceRejection = $this->bindDevice($user, $challenge['device'])) !== null) {
            return $deviceRejection;
        }

        $this->recordMobileLogin($user, $challenge['device']);

        return response()->json([
            'access_token' => JWTAuth::fromUser($user),
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Add this sign-in to the account's known-device list.
     *
     * Separate from {@see self::bindDevice()}: binding decides whether the
     * phone may hold the account's single active slot, this only remembers
     * that the account was used from it, so the user sees the same history for
     * web and app and gets warned about an unfamiliar handset.
     *
     * @param  array<string, mixed>  $device
     */
    private function recordMobileLogin(User $user, array $device): void
    {
        $key = is_string($device['device_id'] ?? null) ? $device['device_id'] : null;
        $label = collect([$device['device_name'] ?? null, $device['model'] ?? null])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->first();

        LoginSecurity::recordLogin(
            $user,
            channel: 'mobile',
            deviceKey: $key,
            deviceLabel: is_string($label) ? $label : null,
        );
    }

    /**
     * The authenticated user's profile (enveloped in `data`).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($request->user())]);
    }

    /**
     * Issue a fresh token, blacklisting the old one.
     *
     * Runs outside the `auth:api` guard so an already-expired token can still
     * be exchanged: the refresh flow accepts one until `jwt.refresh_ttl` runs
     * out, which is the whole point of the route. Custom claims are carried
     * over rather than regenerated, so the `tv` on the new token is still the
     * one the old session was issued with — and is checked against the user
     * here, so a revoked session cannot refresh its way back in.
     *
     * The account's standing is re-tested on every refresh, so deactivating an
     * employee or taking away their mobile access ends the session within one
     * token lifetime instead of lasting the full two-week refresh window.
     */
    public function refresh(Request $request): JsonResponse
    {
        $guard = auth('api');

        // The JWT holder is a singleton that remembers the last token set on
        // it and hands that back in place of the current request's header.
        // Under php-fpm nothing survives the response, but a queue worker,
        // Octane, or a test run keeps the process — and this is the one route
        // that retires a token, so a stale one left behind is a blacklisted
        // one, and every later request in that process reads as unauthorised.
        // Clear it going in and going out.
        $guard->unsetToken();

        $bearer = $request->bearerToken();

        if (blank($bearer)) {
            return $this->sessionEnded();
        }

        try {
            $token = $guard->setToken($bearer)->refresh(forceForever: false, resetClaims: false);
        } catch (JWTException) {
            // Past the refresh window, already blacklisted, or not a token we
            // issued. All of them mean the same thing to the caller.
            $guard->unsetToken();

            return $this->sessionEnded();
        }

        $payload = $guard->setToken($token)->payload();
        $user = User::find($payload->get('sub'));

        $guard->unsetToken();

        if ($user === null || (int) ($payload->get('tv') ?? 0) !== (int) $user->token_version) {
            return $this->sessionEnded();
        }

        if (($user->status !== null && $user->status !== 'active')
            || ! $user->canAccessMobile()
            || $user->tenant === null
            || $user->tenant->company()->doesntExist()) {
            return $this->sessionEnded();
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * The one answer every failed refresh gives, worded like the middleware's
     * so the app shows the same thing however the session ended.
     */
    private function sessionEnded(): JsonResponse
    {
        return response()->json([
            'message' => 'Sesi telah berakhir. Silakan masuk kembali.',
        ], 401);
    }

    /**
     * Invalidate the current token.
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    /**
     * Park a passed password check under a single-use token.
     *
     * The token is a plain random string, never a JWT: nothing outside
     * {@see twoFactor} accepts it, so a stolen one buys an attacker the right
     * to answer a challenge they still cannot answer. The device details ride
     * along so binding can run on the far side without the app resending them.
     *
     * @param  array<string, mixed>  $data
     */
    private function issueChallenge(User $user, array $data): string
    {
        $token = Str::random(64);

        Cache::put(
            self::challengeKey($token),
            [
                'user_id' => $user->id,
                'attempts' => 0,
                'device' => Arr::only($data, [
                    'device_id', 'device_name', 'platform', 'model', 'os_version', 'app_version',
                ]),
            ],
            now()->addMinutes(self::CHALLENGE_TTL_MINUTES),
        );

        return $token;
    }

    /**
     * The cache key for a challenge. Hashed so the store never holds the token
     * the app is carrying.
     */
    private static function challengeKey(string $token): string
    {
        return 'mobile-two-factor:'.hash('sha256', $token);
    }

    /**
     * Verify an authenticator code or a recovery code against the same secrets
     * the web challenge reads, so a factor set up on one works on the other. A
     * spent recovery code is replaced, exactly as Fortify does on the web.
     *
     * @param  array<string, mixed>  $data
     */
    private function passesSecondFactor(User $user, array $data): bool
    {
        if (filled($data['code'] ?? null)) {
            return app(TwoFactorAuthenticationProvider::class)->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                $data['code'],
            );
        }

        if (filled($data['recovery_code'] ?? null)) {
            $match = collect($user->recoveryCodes())
                ->first(fn (string $code): bool => hash_equals($code, $data['recovery_code']));

            if ($match !== null) {
                $user->replaceRecoveryCode($match);

                return true;
            }
        }

        return false;
    }

    /**
     * A challenge that has run out, been spent, or never existed. Worded so the
     * app can send the user back to the login screen.
     */
    private function challengeExpired(): JsonResponse
    {
        return response()->json([
            'message' => 'Sesi verifikasi telah berakhir. Silakan masuk kembali.',
        ], 401);
    }

    /**
     * Enforce single-device binding. Binds the first device an account signs in
     * from and records its details; a later sign-in from a different device is
     * rejected until an admin resets the binding. Returns a 403 JsonResponse to
     * block the login, or null when the device is allowed.
     *
     * Requests without a device_id (e.g. non-mobile clients) skip binding.
     *
     * @param  array<string, mixed>  $data
     */
    private function bindDevice(User $user, array $data): ?JsonResponse
    {
        $deviceId = $data['device_id'] ?? null;

        if (blank($deviceId)) {
            return null;
        }

        // Tenants can turn off "1 perangkat 1 akun" in Setup Absensi; then we
        // neither bind nor reject on a device mismatch.
        if (! AttendancePolicy::resolve($user->tenant_id)->device_binding_enabled) {
            return null;
        }

        $details = [
            'device_name' => $data['device_name'] ?? null,
            'platform' => $data['platform'] ?? null,
            'model' => $data['model'] ?? null,
            'os_version' => $data['os_version'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'last_login_at' => now(),
        ];

        $active = UserDevice::where('user_id', $user->id)->active()->first();

        if ($active === null) {
            UserDevice::create(array_merge($details, [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'status' => 'active',
                'bound_at' => now(),
            ]));

            return null;
        }

        if ($active->device_id === $deviceId) {
            $active->update($details);

            return null;
        }

        return response()->json([
            'message' => 'Perangkat tidak dikenali. Akun ini terikat ke HP lain — hubungi admin untuk reset perangkat.',
        ], 403);
    }

    /**
     * The mobile AppUser shape.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing('roles:id,code', 'employee', 'tenant.company:id,tenant_id,logo_path');

        $employee = $user->employee;
        $tenant = $user->tenant;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('code')->all(),
            'avatar_url' => PrivateFile::urlFor($user->avatar_path ?? $employee?->photo_path),
            'employee' => $employee !== null ? $this->employeeProfile($employee) : null,
            // Menu Cepat, resolved for this account: the tenant's active tiles
            // minus the ones hidden from every role they hold. Sent from the
            // server so an admin can change the phone's menu from Hak Akses
            // without a new build.
            'menu' => MobileMenu::forUser($user),
            // The bottom navigation bar, resolved the same way: a company that
            // switched Ruang Kita off in Kelola Fitur, or a role it is hidden
            // from, gets a bar without that tab instead of one that leads to a
            // screen the API refuses.
            'tabs' => MobileMenu::tabsForUser($user),
            // Tenant branding for the mobile splash / white-label after login.
            'tenant' => $tenant !== null ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'company_name' => $tenant->company_name,
                'logo_url' => $tenant->company?->logo_path !== null
                    ? Storage::disk('public')->url($tenant->company->logo_path)
                    : null,
                // Per-tenant appearance colours so the mobile app can match the
                // brand set on the web "Tampilan & Tema" screen.
                'theme' => TenantTheme::resolve($tenant->theme),
            ] : null,
        ];
    }
}

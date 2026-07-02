<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * JWT authentication for the AvanaHR mobile app. Employees sign in with their
 * email + password and receive a JWT bearer token.
 */
class AuthController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Authenticate and issue a JWT.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $token = auth('api')->attempt($credentials);

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

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->userPayload($user),
        ]);
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
     */
    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->userPayload(auth('api')->user()),
        ]);
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
     * The mobile AppUser shape.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing('roles:id,code');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('code')->all(),
            'avatar_url' => null,
            'employee' => $user->employee !== null ? $this->employeeProfile($user->employee) : null,
        ];
    }
}

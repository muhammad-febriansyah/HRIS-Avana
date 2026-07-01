<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * JWT authentication for the AvanaHR mobile app. Employees sign in with their
 * email + password and receive a JWT bearer token used for every self-service
 * call. Tokens are stateless; logout blacklists the current token.
 */
class AuthController extends Controller
{
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

        return $this->tokenResponse($token, $user);
    }

    /**
     * Return the authenticated user's profile and linked employee record.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /**
     * Issue a fresh token and blacklist the old one.
     */
    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();

        return $this->tokenResponse($token, auth('api')->user());
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
     * Standard token + profile response.
     */
    private function tokenResponse(string $token, User $user): JsonResponse
    {
        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Shape the user + employee data returned to the app.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing(['roles:id,code,name', 'employee.position:id,name', 'employee.department:id,name', 'employee.branch:id,name']);
        $employee = $user->employee;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'roles' => $user->roles->pluck('code')->all(),
            'employee' => $employee === null ? null : [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
                'position' => $employee->position?->name,
                'department' => $employee->department?->name,
                'branch' => $employee->branch?->name,
                'join_date' => $employee->join_date?->toDateString(),
                'status' => $employee->status,
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Employee self-service profile updates (limited to safe personal fields). */
class ProfileController extends Controller
{
    use ResolvesApiEmployee;

    public function update(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee->update($data);

        return response()->json(['message' => 'Profil diperbarui']);
    }
}

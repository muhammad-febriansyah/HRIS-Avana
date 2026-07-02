<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Employee self-service profile (view + limited self-update). */
class ProfileController extends Controller
{
    use ResolvesApiEmployee;

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->employeeProfile($this->currentEmployee($request))]);
    }

    public function update(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee->update($data);

        return response()->json(['data' => $this->employeeProfile($employee->fresh())]);
    }
}

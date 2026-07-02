<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\EmployeeFaceEmbedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Employee self-service face enrollment for attendance verification. */
class FaceController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Whether the caller has an enrolled face on record.
     */
    public function status(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $face = EmployeeFaceEmbedding::where('employee_id', $employee->id)->first();

        return response()->json(['data' => [
            'enrolled' => $face !== null,
            'dimensions' => $face?->dimensions,
            'enrolled_at' => $face?->enrolled_at?->toDateTimeString(),
        ]]);
    }

    /**
     * Enroll (or re-enroll) the caller's face embedding.
     */
    public function enroll(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'embedding' => ['required', 'array', 'min:64', 'max:1024'],
            'embedding.*' => ['numeric'],
        ]);

        $embedding = array_map(static fn ($value): float => (float) $value, $data['embedding']);

        EmployeeFaceEmbedding::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'tenant_id' => $employee->tenant_id,
                'embedding' => $embedding,
                'dimensions' => count($embedding),
                'enrolled_at' => now(),
            ],
        );

        return response()->json(['message' => 'Wajah berhasil didaftarkan']);
    }
}

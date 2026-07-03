<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\MoodCheckin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Anonymous daily mood check-in ("Bagaimana perasaanmu hari ini?"). One entry
 * per employee per day; only the employee and HR see it.
 */
class MoodController extends Controller
{
    use ResolvesApiEmployee;

    private const MOODS = ['sangat_baik', 'baik', 'biasa', 'kurang', 'buruk'];

    /**
     * Today's check-in status for the caller.
     */
    public function today(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $mood = MoodCheckin::where('employee_id', $employee->id)
            ->whereDate('date', now()->toDateString())
            ->value('mood');

        return response()->json(['data' => [
            'checked_in' => $mood !== null,
            'mood' => $mood,
        ]]);
    }

    /**
     * Record (or update) today's mood.
     */
    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'mood' => ['required', 'string', 'in:'.implode(',', self::MOODS)],
        ]);

        MoodCheckin::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => now()->toDateString()],
            ['tenant_id' => $employee->tenant_id, 'mood' => $data['mood']],
        );

        return response()->json(['message' => 'Terima kasih, perasaanmu tercatat.', 'data' => ['mood' => $data['mood']]]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\MoodCheckin;
use Illuminate\Database\UniqueConstraintViolationException;
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

        $mood = $this->moodFor($employee->id, now()->toDateString());

        return response()->json(['data' => [
            'checked_in' => $mood !== null,
            'mood' => $mood,
        ]]);
    }

    /**
     * Record today's mood. Once per day and final — a second submission is
     * rejected rather than overwriting the first, so the day's reading stays
     * the employee's honest first answer.
     */
    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'mood' => ['required', 'string', 'in:'.implode(',', self::MOODS)],
        ]);

        $today = now()->toDateString();

        $existing = $this->moodFor($employee->id, $today);

        if ($existing !== null) {
            return response()->json([
                'message' => 'Perasaanmu hari ini sudah tercatat.',
                'data' => ['mood' => $existing],
            ], 409);
        }

        try {
            MoodCheckin::create([
                'employee_id' => $employee->id,
                'date' => $today,
                'tenant_id' => $employee->tenant_id,
                'mood' => $data['mood'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // Raced with a concurrent submission — the first one wins.
            return response()->json([
                'message' => 'Perasaanmu hari ini sudah tercatat.',
                'data' => ['mood' => $this->moodFor($employee->id, $today)],
            ], 409);
        }

        return response()->json(['message' => 'Terima kasih, perasaanmu tercatat.', 'data' => ['mood' => $data['mood']]]);
    }

    /**
     * The mood already recorded for an employee on a date, or null.
     */
    private function moodFor(int $employeeId, string $date): ?string
    {
        return MoodCheckin::where('employee_id', $employeeId)
            ->whereDate('date', $date)
            ->value('mood');
    }
}

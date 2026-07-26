<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\TrainingEnrollment;
use DateTimeInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Pembelajaran Saya" — the training the employee is enrolled in, plus what
 * else is on offer. Enrolment itself is arranged by HR.
 */
class EssLearningController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Indonesian labels for the enrolment status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'registered' => 'Terdaftar',
        'enrolled' => 'Terdaftar',
        'attended' => 'Hadir',
        'in_progress' => 'Berjalan',
        'ongoing' => 'Berjalan',
        'completed' => 'Selesai',
        'passed' => 'Lulus',
        'failed' => 'Tidak Lulus',
        'cancelled' => 'Dibatalkan',
    ];

    /**
     * Indonesian labels for the training delivery type, stored in English.
     *
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'internal' => 'Internal',
        'external' => 'Eksternal',
        'online' => 'Daring',
        'offline' => 'Luring',
    ];

    /**
     * The employee's enrolments, and the catalogue they can ask HR about.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $enrollments = TrainingEnrollment::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('training:id,title,category,type,start_date,end_date,instructor,status')
            ->orderByDesc('id')
            ->get();

        $enrolledIds = $enrollments->pluck('training_id')->all();

        $catalogue = Training::forTenant($employee->tenant_id)
            ->whereNotIn('id', $enrolledIds)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('start_date')
            ->limit(20)
            ->get(['id', 'title', 'category', 'type', 'start_date', 'end_date', 'instructor', 'quota', 'status']);

        return Inertia::render('avana/saya/pembelajaran', [
            'enrollments' => $enrollments->map(fn (TrainingEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'title' => $enrollment->training?->title,
                'category' => $enrollment->training?->category,
                'type' => $this->typeLabel($enrollment->training?->type),
                'instructor' => $enrollment->training?->instructor,
                'start_date' => $this->dateString($enrollment->training?->start_date),
                'end_date' => $this->dateString($enrollment->training?->end_date),
                'status' => $enrollment->status,
                'status_label' => self::STATUS_LABELS[$enrollment->status] ?? $enrollment->status,
                'score' => $enrollment->score !== null ? (float) $enrollment->score : null,
                'certificate_no' => $enrollment->certificate_no,
                'completed_date' => $this->dateString($enrollment->completed_date),
            ])->values(),
            'catalogue' => $catalogue->map(fn (Training $training): array => [
                'id' => $training->id,
                'title' => $training->title,
                'category' => $training->category,
                'type' => $this->typeLabel($training->type),
                'instructor' => $training->instructor,
                'start_date' => $this->dateString($training->start_date),
                'end_date' => $this->dateString($training->end_date),
                'quota' => $training->quota !== null ? (int) $training->quota : null,
            ])->values(),
            'summary' => [
                'total' => $enrollments->count(),
                'completed' => $enrollments->whereIn('status', ['completed', 'passed'])->count(),
                'certificates' => $enrollments->filter(
                    fn (TrainingEnrollment $enrollment): bool => $enrollment->certificate_no !== null
                        && $enrollment->certificate_no !== '',
                )->count(),
            ],
        ]);
    }

    /**
     * The Indonesian label for a delivery type, falling back to the raw value
     * for anything a tenant has added itself.
     */
    private function typeLabel(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return self::TYPE_LABELS[$type] ?? $type;
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     */
    private function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}

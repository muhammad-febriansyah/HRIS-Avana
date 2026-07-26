<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\FieldVisit;
use App\Models\FieldVisitTask;
use DateTimeInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Tugas Saya" — the checklists attached to the employee's own field visits.
 *
 * Ticking an item off stays on mobile: each one can carry before/after photos
 * taken on site, which the browser flow has no way to capture.
 */
class EssTaskController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Indonesian labels for the visit status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'planned' => 'Direncanakan',
        'scheduled' => 'Dijadwalkan',
        'submitted' => 'Dilaporkan',
        'draft' => 'Draft',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'ongoing' => 'Berjalan',
        'in_progress' => 'Berjalan',
        'completed' => 'Selesai',
        'done' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    /**
     * The employee's visits with their task checklists, newest first.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $visits = FieldVisit::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with(['tasks' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->get();

        $rows = $visits->map(function (FieldVisit $visit): array {
            $tasks = $visit->tasks;
            $done = $tasks->where('is_done', true)->count();

            return [
                'id' => $visit->id,
                'visit_date' => $this->dateString($visit->visit_date),
                'location' => $visit->location,
                'client_name' => $visit->client_name,
                'purpose' => $visit->purpose,
                'status' => $visit->status,
                'status_label' => self::STATUS_LABELS[$visit->status] ?? $visit->status,
                'total' => $tasks->count(),
                'done' => $done,
                'percent' => $tasks->count() > 0 ? (int) round($done / $tasks->count() * 100) : 0,
                'tasks' => $tasks->map(fn (FieldVisitTask $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'is_done' => (bool) $task->is_done,
                    'done_at' => $this->dateString($task->done_at),
                ])->values(),
            ];
        });

        return Inertia::render('avana/saya/tugas', [
            'visits' => $rows->values(),
            'summary' => [
                'visits' => $rows->count(),
                'tasks' => (int) $rows->sum('total'),
                'done' => (int) $rows->sum('done'),
                'open' => (int) $rows->sum('total') - (int) $rows->sum('done'),
            ],
        ]);
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     */
    private function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}

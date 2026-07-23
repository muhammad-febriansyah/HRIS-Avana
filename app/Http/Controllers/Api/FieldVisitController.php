<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\FieldVisit;
use App\Models\FieldVisitTask;
use App\Services\FieldVisitPhotoStore;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Employee self-service field visits / visiting pekerjaan (list + report). */
class FieldVisitController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Paginated, searchable list of the employee's own field visits. Paging
     * keeps the payload bounded so the mobile list stays fast with thousands of
     * rows (the app loads a page at a time via infinite scroll).
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = FieldVisit::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with(['photos', 'tasks', 'branch:id,name'])
            ->when($data['date'] ?? null, fn ($query, string $date) => $query->whereDate('visit_date', $date))
            ->when($data['search'] ?? null, fn ($query, string $search) => $query->where(fn ($group) => $group
                ->where('location', 'like', "%{$search}%")
                ->orWhere('client_name', 'like', "%{$search}%")
                ->orWhere('purpose', 'like', "%{$search}%")))
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20);

        return response()->json([
            'data' => collect($page->items())
                ->map(fn (FieldVisit $visit): array => $this->shapeVisit($visit))
                ->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Shape a field visit for the mobile list payload.
     *
     * @return array<string, mixed>
     */
    private function shapeVisit(FieldVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'visit_date' => self::dateString($visit->visit_date),
            'branch' => $visit->branch?->name,
            'location' => $visit->location,
            'client_name' => $visit->client_name,
            'purpose' => $visit->purpose,
            'notes' => $visit->notes,
            'photo_urls' => FieldVisitPhotoStore::urls($visit),
            'status' => $visit->status,
            'tasks' => $visit->tasks
                ->map(fn (FieldVisitTask $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'is_done' => $task->is_done,
                    'photo_note' => $task->photo_note,
                    'before_photo_url' => $task->before_photo_path !== null
                        ? Storage::disk('public')->url($task->before_photo_path)
                        : null,
                    'after_photo_url' => $task->after_photo_path !== null
                        ? Storage::disk('public')->url($task->after_photo_path)
                        : null,
                ])
                ->values()
                ->all(),
            'task_progress' => $visit->taskProgress(),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', "exists:branches,id,tenant_id,{$employee->tenant_id}"],
            'visit_date' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'tasks' => ['nullable', 'array'],
            'tasks.*' => ['required', 'string', 'max:255'],
            // Per-task evidence, indexed alongside `tasks` so entry N here
            // belongs to task N above.
            'task_notes' => ['nullable', 'array'],
            'task_notes.*' => ['nullable', 'string', 'max:255'],
            'task_before' => ['nullable', 'array'],
            'task_before.*' => ['nullable', 'image', 'max:4096'],
            'task_after' => ['nullable', 'array'],
            'task_after.*' => ['nullable', 'image', 'max:4096'],
            ...FieldVisitPhotoStore::rules(),
        ]);

        $visit = DB::transaction(function () use ($request, $employee, $data): FieldVisit {
            $visit = FieldVisit::create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                // Defaults to the employee's own branch — the app reports from
                // the field and rarely has one to pick.
                'branch_id' => $data['branch_id'] ?? $employee->branch_id,
                'visit_date' => $data['visit_date'],
                'location' => $data['location'],
                'client_name' => $data['client_name'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'notes' => $data['notes'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'status' => 'submitted',
            ]);

            $visit->syncAttendees([]);

            $notes = array_values($data['task_notes'] ?? []);
            $before = array_values($request->file('task_before') ?? []);
            $after = array_values($request->file('task_after') ?? []);

            foreach (array_values($data['tasks'] ?? []) as $order => $title) {
                $visit->tasks()->create([
                    'tenant_id' => $employee->tenant_id,
                    'title' => $title,
                    'sort_order' => $order,
                    'photo_note' => $notes[$order] ?? null,
                    'before_photo_path' => ($before[$order] ?? null)?->store('visit-tasks', 'public'),
                    'after_photo_path' => ($after[$order] ?? null)?->store('visit-tasks', 'public'),
                ]);
            }

            return $visit;
        });

        FieldVisitPhotoStore::attach($visit, $request->file('photos') ?? []);

        return response()->json(['message' => 'Kunjungan tercatat', 'data' => ['id' => $visit->id]], 201);
    }

    /**
     * Upload (or replace) the "after" evidence photo for a task on the
     * employee's own visit — the before photo is captured when the report is
     * filed, the after later once the work is actually done.
     */
    public function uploadTaskAfter(Request $request, FieldVisit $visit, FieldVisitTask $task): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        abort_if((int) $visit->tenant_id !== (int) $employee->tenant_id, 404);
        abort_if((int) $visit->employee_id !== (int) $employee->id, 404);
        abort_if((int) $task->field_visit_id !== (int) $visit->id, 404);

        $request->validate([
            'after' => ['required', 'image', 'max:4096'],
        ]);

        if ($task->after_photo_path !== null) {
            Storage::disk('public')->delete($task->after_photo_path);
        }

        $path = $request->file('after')->store('visit-tasks', 'public');
        $task->update(['after_photo_path' => $path]);

        return response()->json(['data' => [
            'id' => $task->id,
            'after_photo_url' => Storage::disk('public')->url($path),
        ]]);
    }

    /** Tick a task off the employee's own visit, or put it back. */
    public function toggleTask(Request $request, FieldVisit $visit, FieldVisitTask $task): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        abort_if((int) $visit->tenant_id !== (int) $employee->tenant_id, 404);
        abort_if((int) $visit->employee_id !== (int) $employee->id, 404);
        abort_if((int) $task->field_visit_id !== (int) $visit->id, 404);

        $isDone = ! $task->is_done;

        $task->update([
            'is_done' => $isDone,
            'done_at' => $isDone ? now() : null,
        ]);

        return response()->json(['data' => ['id' => $task->id, 'is_done' => $isDone]]);
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     *
     * Matched on DateTimeInterface, not Illuminate\Support\Carbon: the app runs
     * Date::use(CarbonImmutable::class), and CarbonImmutable does not extend the
     * Illuminate class — so a narrower check silently passes the raw datetime
     * string through.
     */
    private static function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}

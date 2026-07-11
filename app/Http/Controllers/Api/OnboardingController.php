<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\OffboardingCase;
use App\Models\OnboardingProgram;
use App\Models\OnboardingTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Employee self-service onboarding & offboarding checklists. An employee sees
 * their own joining programme (and can tick off their tasks) and their exit
 * clearance progress (read-only — items are cleared by the responsible teams).
 */
class OnboardingController extends Controller
{
    use ResolvesApiEmployee;

    /** The caller's latest onboarding programme with its tasks and progress. */
    public function onboarding(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $program = OnboardingProgram::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('tasks:id,onboarding_program_id,title,category,due_date,is_done')
            ->orderByDesc('id')
            ->first();

        return response()->json(['data' => $program !== null ? $this->shapeProgram($program) : null]);
    }

    /** Tick or untick one of the caller's own onboarding tasks. */
    public function toggleTask(Request $request, OnboardingTask $task): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate(['is_done' => ['required', 'boolean']]);

        $program = $task->program()->first();

        abort_if(
            $program === null
            || (int) $program->tenant_id !== (int) $employee->tenant_id
            || (int) $program->employee_id !== (int) $employee->id,
            404,
        );

        $task->update(['is_done' => $data['is_done']]);

        // Keep the programme status in step with its tasks.
        $program->loadMissing('tasks:id,onboarding_program_id,is_done');
        $allDone = $program->tasks->isNotEmpty() && $program->tasks->every(fn (OnboardingTask $t): bool => $t->is_done);
        $program->update(['status' => $allDone ? 'completed' : 'in_progress']);

        $program->setRelation('tasks', $program->tasks()->get(['id', 'onboarding_program_id', 'title', 'category', 'due_date', 'is_done']));

        return response()->json([
            'message' => $data['is_done'] ? 'Tugas ditandai selesai.' : 'Tugas ditandai belum selesai.',
            'data' => $this->shapeProgram($program),
        ]);
    }

    /** The caller's offboarding case with clearance items and progress, if any. */
    public function offboarding(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $case = OffboardingCase::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('clearanceItems:id,offboarding_case_id,title,department,is_cleared')
            ->orderByDesc('id')
            ->first();

        return response()->json(['data' => $case !== null ? $this->shapeCase($case) : null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeProgram(OnboardingProgram $program): array
    {
        $tasks = $program->tasks;
        $done = $tasks->where('is_done', true)->count();

        return [
            'id' => $program->id,
            'status' => $program->status,
            'start_date' => $program->start_date?->toDateString(),
            'progress' => $this->progress($done, $tasks->count()),
            'tasks' => $tasks->map(fn (OnboardingTask $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'category' => $task->category,
                'due_date' => $task->due_date?->toDateString(),
                'is_done' => (bool) $task->is_done,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeCase(OffboardingCase $case): array
    {
        $items = $case->clearanceItems;
        $cleared = $items->where('is_cleared', true)->count();

        return [
            'id' => $case->id,
            'status' => $case->status,
            'last_day' => $case->last_day?->toDateString(),
            'reason' => $case->reason,
            'progress' => $this->progress($cleared, $items->count()),
            'clearance_items' => $items->map(fn ($item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'department' => $item->department,
                'is_cleared' => (bool) $item->is_cleared,
            ])->values(),
        ];
    }

    /**
     * @return array{done: int, total: int, percent: int}
     */
    private function progress(int $done, int $total): array
    {
        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
    }
}

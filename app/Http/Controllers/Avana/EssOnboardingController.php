<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\OnboardingProgram;
use App\Models\OnboardingTask;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Onboarding Saya" — the employee's own joining programme. They may tick their
 * tasks off; the programme status follows the tasks automatically.
 */
class EssOnboardingController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * The employee's latest onboarding programme with its checklist.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $program = OnboardingProgram::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('tasks:id,onboarding_program_id,title,category,due_date,is_done')
            ->orderByDesc('id')
            ->first();

        return Inertia::render('avana/saya/onboarding', [
            'program' => $program !== null ? $this->shapeProgram($program) : null,
        ]);
    }

    /**
     * Tick or untick one of the employee's own onboarding tasks.
     */
    public function toggleTask(Request $request, OnboardingTask $task): RedirectResponse
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
        $allDone = $program->tasks->isNotEmpty()
            && $program->tasks->every(fn (OnboardingTask $item): bool => $item->is_done);
        $program->update(['status' => $allDone ? 'completed' : 'in_progress']);

        return back()->with('success', $data['is_done'] ? 'Tugas ditandai selesai' : 'Tugas ditandai belum selesai');
    }

    /**
     * The programme shape the page renders.
     *
     * @return array<string, mixed>
     */
    private function shapeProgram(OnboardingProgram $program): array
    {
        $tasks = $program->tasks;
        $done = $tasks->where('is_done', true)->count();

        return [
            'id' => $program->id,
            'start_date' => $this->dateString($program->start_date),
            'status' => $program->status,
            'total' => $tasks->count(),
            'done' => $done,
            'percent' => $tasks->count() > 0 ? (int) round($done / $tasks->count() * 100) : 0,
            'tasks' => $tasks->map(fn (OnboardingTask $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'category' => $task->category,
                'due_date' => $this->dateString($task->due_date),
                'is_done' => (bool) $task->is_done,
            ])->values(),
        ];
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     */
    private function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}

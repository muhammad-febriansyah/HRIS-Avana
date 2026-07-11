<?php

use App\Models\ClearanceItem;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\OnboardingProgram;
use App\Models\OnboardingTask;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };

    $this->me = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;
    $tenantId = $this->me->tenant_id;

    $this->program = OnboardingProgram::create([
        'tenant_id' => $tenantId, 'employee_id' => $this->me->id,
        'start_date' => '2026-07-01', 'status' => 'in_progress',
    ]);
    $this->doneTask = OnboardingTask::create([
        'tenant_id' => $tenantId, 'onboarding_program_id' => $this->program->id,
        'title' => 'Tanda tangan kontrak', 'category' => 'Dokumen', 'is_done' => true,
    ]);
    $this->openTask = OnboardingTask::create([
        'tenant_id' => $tenantId, 'onboarding_program_id' => $this->program->id,
        'title' => 'Setup email', 'category' => 'IT', 'is_done' => false,
    ]);
});

it('returns the onboarding programme with progress', function (): void {
    ($this->auth)()
        ->getJson('/api/v1/me/onboarding')
        ->assertOk()
        ->assertJsonPath('data.progress', ['done' => 1, 'total' => 2, 'percent' => 50])
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonStructure(['data' => ['id', 'status', 'start_date', 'progress', 'tasks' => [['id', 'title', 'category', 'is_done']]]]);
});

it('ticks a task and completes the programme when all are done', function (): void {
    ($this->auth)()
        ->patchJson('/api/v1/me/onboarding/tasks/'.$this->openTask->id, ['is_done' => true])
        ->assertOk()
        ->assertJsonPath('data.progress.percent', 100)
        ->assertJsonPath('data.status', 'completed');

    expect($this->openTask->fresh()->is_done)->toBeTrue();
    expect($this->program->fresh()->status)->toBe('completed');
});

it('unticks a task and reopens the programme', function (): void {
    ($this->auth)()
        ->patchJson('/api/v1/me/onboarding/tasks/'.$this->doneTask->id, ['is_done' => false])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.progress.done', 0);
});

it('does not let an employee toggle another employees task', function (): void {
    $other = Employee::forTenant($this->me->tenant_id)->where('id', '!=', $this->me->id)->firstOrFail();
    $otherProgram = OnboardingProgram::create([
        'tenant_id' => $this->me->tenant_id, 'employee_id' => $other->id, 'status' => 'in_progress',
    ]);
    $otherTask = OnboardingTask::create([
        'tenant_id' => $this->me->tenant_id, 'onboarding_program_id' => $otherProgram->id,
        'title' => 'Bukan tugas saya', 'is_done' => false,
    ]);

    ($this->auth)()
        ->patchJson('/api/v1/me/onboarding/tasks/'.$otherTask->id, ['is_done' => true])
        ->assertNotFound();

    expect($otherTask->fresh()->is_done)->toBeFalse();
});

it('returns the offboarding case with clearance progress', function (): void {
    $case = OffboardingCase::create([
        'tenant_id' => $this->me->tenant_id, 'employee_id' => $this->me->id,
        'last_day' => '2026-08-31', 'reason' => 'Resign', 'status' => 'in_progress',
    ]);
    ClearanceItem::create([
        'tenant_id' => $this->me->tenant_id, 'offboarding_case_id' => $case->id,
        'title' => 'Kembalikan laptop', 'department' => 'IT', 'is_cleared' => true,
    ]);
    ClearanceItem::create([
        'tenant_id' => $this->me->tenant_id, 'offboarding_case_id' => $case->id,
        'title' => 'Serah terima tugas', 'department' => 'HR', 'is_cleared' => false,
    ]);

    ($this->auth)()
        ->getJson('/api/v1/me/offboarding')
        ->assertOk()
        ->assertJsonPath('data.progress', ['done' => 1, 'total' => 2, 'percent' => 50])
        ->assertJsonStructure(['data' => ['id', 'status', 'last_day', 'clearance_items' => [['id', 'title', 'department', 'is_cleared']]]]);
});

it('returns null onboarding when the employee has none', function (): void {
    $this->program->tasks()->delete();
    $this->program->delete();

    ($this->auth)()->getJson('/api/v1/me/onboarding')->assertOk()->assertJsonPath('data', null);
});

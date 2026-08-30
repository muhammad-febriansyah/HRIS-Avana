<?php

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Timesheet;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->employeeUser->employee;

    $this->tokenFor = function (string $email): string {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])->json('access_token');
    };

    $this->auth = function (string $token) {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };
});

function apiProject(int $tenantId, array $overrides = []): Project
{
    return Project::create(array_merge([
        'tenant_id' => $tenantId,
        'name' => 'Proyek '.fake()->unique()->word(),
        'code' => 'PRJ-'.fake()->unique()->numerify('###'),
        'status' => 'active',
        'is_billable' => true,
        'default_bill_rate' => 200_000,
        'default_cost_rate' => 100_000,
    ], $overrides));
}

it('lists only the caller own entries with a summary', function (): void {
    $project = apiProject($this->employee->tenant_id);

    $mine = Timesheet::create([
        'tenant_id' => $this->employee->tenant_id,
        'employee_id' => $this->employee->id,
        'project_id' => $project->id,
        'date' => now()->toDateString(),
        'hours' => 4,
        'status' => Timesheet::STATUS_PENDING,
    ]);

    $colleague = Employee::forTenant($this->employee->tenant_id)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    $theirs = Timesheet::create([
        'tenant_id' => $colleague->tenant_id,
        'employee_id' => $colleague->id,
        'project_id' => $project->id,
        'date' => now()->toDateString(),
        'hours' => 3,
        'status' => Timesheet::STATUS_PENDING,
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    $response = ($this->auth)($token)
        ->getJson('/api/v1/me/timesheets')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'project', 'date', 'hours', 'status', 'is_billable']],
            'summary' => ['week_hours', 'month_hours', 'pending'],
        ]);

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($theirs->id)
        ->and($response->json('summary.pending'))->toBe(1);
});

it('lists projects with no members plus the ones the caller is assigned to', function (): void {
    $open = apiProject($this->employee->tenant_id);
    $mine = apiProject($this->employee->tenant_id);
    $theirs = apiProject($this->employee->tenant_id);

    $colleague = Employee::forTenant($this->employee->tenant_id)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    ProjectMember::create([
        'tenant_id' => $this->employee->tenant_id,
        'project_id' => $mine->id,
        'employee_id' => $this->employee->id,
    ]);

    ProjectMember::create([
        'tenant_id' => $this->employee->tenant_id,
        'project_id' => $theirs->id,
        'employee_id' => $colleague->id,
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    $ids = collect(
        ($this->auth)($token)
            ->getJson('/api/v1/me/timesheets/projects')
            ->assertOk()
            ->json('data')
    )->pluck('id');

    expect($ids)->toContain($open->id)
        ->and($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($theirs->id);
});

it('files an entry as pending routed to the manager', function (): void {
    $project = apiProject($this->employee->tenant_id);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->postJson('/api/v1/me/timesheets', [
            'project_id' => $project->id,
            'date' => now()->toDateString(),
            'hours' => 6,
            'task' => 'Integrasi API',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', Timesheet::STATUS_PENDING);

    $entry = Timesheet::where('task', 'Integrasi API')->firstOrFail();

    expect($entry->employee_id)->toBe($this->employee->id)
        ->and($entry->source)->toBe('mobile')
        ->and((int) $entry->current_approver_id)->toBe((int) $this->employee->manager_id)
        // Nothing an employee files is priced until it is approved.
        ->and((float) $entry->bill_amount)->toBe(0.0);
});

it('refuses an entry on a project the caller is not assigned to', function (): void {
    $project = apiProject($this->employee->tenant_id);

    $colleague = Employee::forTenant($this->employee->tenant_id)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    ProjectMember::create([
        'tenant_id' => $this->employee->tenant_id,
        'project_id' => $project->id,
        'employee_id' => $colleague->id,
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->postJson('/api/v1/me/timesheets', [
            'project_id' => $project->id,
            'date' => now()->toDateString(),
            'hours' => 2,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('project_id');
});

it('refuses a filing that pushes the day past 24 hours', function (): void {
    $project = apiProject($this->employee->tenant_id);

    Timesheet::create([
        'tenant_id' => $this->employee->tenant_id,
        'employee_id' => $this->employee->id,
        'project_id' => $project->id,
        'date' => '2026-08-10',
        'hours' => 20,
        'status' => Timesheet::STATUS_PENDING,
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->postJson('/api/v1/me/timesheets', [
            'project_id' => $project->id,
            'date' => '2026-08-10',
            'hours' => 6,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('hours');
});

it('lets the caller edit and withdraw a pending entry but not a decided one', function (): void {
    $project = apiProject($this->employee->tenant_id);

    $entry = Timesheet::create([
        'tenant_id' => $this->employee->tenant_id,
        'employee_id' => $this->employee->id,
        'project_id' => $project->id,
        'date' => '2026-08-11',
        'hours' => 3,
        'status' => Timesheet::STATUS_PENDING,
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->putJson("/api/v1/me/timesheets/{$entry->id}", [
            'project_id' => $project->id,
            'date' => '2026-08-11',
            'hours' => 5,
            'task' => 'Revisi',
        ])
        ->assertOk()
        ->assertJsonPath('data.hours', 5);

    $entry->update(['status' => Timesheet::STATUS_APPROVED]);

    ($this->auth)($token)
        ->deleteJson("/api/v1/me/timesheets/{$entry->id}")
        ->assertStatus(422);

    expect(Timesheet::find($entry->id))->not->toBeNull();
});

it('cannot touch another employee entry', function (): void {
    $colleague = Employee::forTenant($this->employee->tenant_id)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    $project = apiProject($colleague->tenant_id);

    $foreign = Timesheet::create([
        'tenant_id' => $colleague->tenant_id,
        'employee_id' => $colleague->id,
        'project_id' => $project->id,
        'date' => '2026-08-12',
        'hours' => 2,
        'status' => Timesheet::STATUS_PENDING,
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->deleteJson("/api/v1/me/timesheets/{$foreign->id}")
        ->assertNotFound();

    expect(Timesheet::find($foreign->id))->not->toBeNull();
});

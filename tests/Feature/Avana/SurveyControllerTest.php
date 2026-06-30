<?php

use App\Models\Employee;
use App\Models\Role;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Create a survey (with an optional rating question + response) for a tenant.
 */
function makeSurvey(int $tenantId, array $overrides = []): Survey
{
    $survey = Survey::create(array_merge([
        'tenant_id' => $tenantId,
        'title' => 'Survei Kepuasan Karyawan',
        'description' => 'Bagaimana perasaan Anda?',
        'status' => 'active',
        'is_anonymous' => true,
    ], $overrides));

    $question = SurveyQuestion::create([
        'tenant_id' => $tenantId,
        'survey_id' => $survey->id,
        'question' => 'Seberapa puas Anda?',
        'type' => 'rating',
        'options' => null,
    ]);

    SurveyResponse::create([
        'tenant_id' => $tenantId,
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
        'employee_id' => null,
        'answer' => '4',
    ]);

    return $survey;
}

it('renders the survei index with the expected props', function (): void {
    makeSurvey($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.survei'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/survei/index', false)
            ->has('surveys.0', fn (Assert $row) => $row
                ->has('id')
                ->has('title')
                ->has('description')
                ->has('status')
                ->has('is_anonymous')
                ->has('responses_count')
                ->has('questions.0', fn (Assert $q) => $q
                    ->has('id')
                    ->has('question')
                    ->has('type')
                    ->has('options')
                    ->has('response_count')
                    ->has('avg_rating')))
            ->has('employees')
            ->has('kpis.active_surveys')
            ->has('kpis.total_responses'));
});

it('only lists surveys that belong to the current tenant', function (): void {
    makeSurvey($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeSurvey($otherTenant->id);

    $tenantTotal = Survey::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.survei'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('surveys', $tenantTotal));
});

it('creates a draft survey by default', function (): void {
    actingAs($this->admin)
        ->post(route('avana.survei.store'), [
            'title' => 'Survei Budaya Kerja',
            'description' => 'Masukan budaya kerja',
        ])
        ->assertRedirect(route('avana.survei'))
        ->assertSessionHas('success');

    $survey = Survey::where('title', 'Survei Budaya Kerja')->firstOrFail();

    expect($survey->tenant_id)->toBe($this->tenant->id);
    expect($survey->status)->toBe('draft');
    expect($survey->is_anonymous)->toBeTrue();
});

it('creates a survey with the chosen status', function (): void {
    actingAs($this->admin)
        ->post(route('avana.survei.store'), [
            'title' => 'Survei Aktif',
            'status' => 'active',
            'is_anonymous' => false,
        ])
        ->assertSessionHas('success');

    $survey = Survey::where('title', 'Survei Aktif')->firstOrFail();

    expect($survey->status)->toBe('active');
    expect($survey->is_anonymous)->toBeFalse();
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.survei.store'), [
            'title' => '',
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors(['title', 'status']);
});

it('adds a question to a survey', function (): void {
    $survey = makeSurvey($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.survei.question.store', $survey), [
            'question' => 'Pilih salah satu',
            'type' => 'choice',
            'options' => ['Setuju', 'Tidak Setuju'],
        ])
        ->assertSessionHas('success');

    $question = SurveyQuestion::where('question', 'Pilih salah satu')->firstOrFail();

    expect($question->survey_id)->toBe($survey->id);
    expect($question->type)->toBe('choice');
    expect($question->options)->toBe(['Setuju', 'Tidak Setuju']);
});

it('validates the question payload', function (): void {
    $survey = makeSurvey($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.survei.question.store', $survey), [
            'question' => '',
            'type' => 'invalid',
        ])
        ->assertSessionHasErrors(['question', 'type']);
});

it('records anonymous responses without an employee', function (): void {
    $survey = makeSurvey($this->tenant->id, ['is_anonymous' => true]);
    $question = $survey->questions()->firstOrFail();
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.survei.respond', $survey), [
            'employee_id' => $employee->id,
            'answers' => [
                ['survey_question_id' => $question->id, 'answer' => '5'],
            ],
        ])
        ->assertSessionHas('success');

    $response = SurveyResponse::where('survey_question_id', $question->id)
        ->where('answer', '5')
        ->firstOrFail();

    expect($response->tenant_id)->toBe($this->tenant->id);
    expect($response->employee_id)->toBeNull();
});

it('records identified responses for a non-anonymous survey', function (): void {
    $survey = makeSurvey($this->tenant->id, ['is_anonymous' => false]);
    $question = $survey->questions()->firstOrFail();
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.survei.respond', $survey), [
            'employee_id' => $employee->id,
            'answers' => [
                ['survey_question_id' => $question->id, 'answer' => '3'],
            ],
        ])
        ->assertSessionHas('success');

    $response = SurveyResponse::where('survey_question_id', $question->id)
        ->where('answer', '3')
        ->firstOrFail();

    expect($response->employee_id)->toBe($employee->id);
});

it('deletes a survey', function (): void {
    $survey = makeSurvey($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.survei.destroy', $survey))
        ->assertSessionHas('success');

    expect(Survey::find($survey->id))->toBeNull();
});

it('returns 404 when adding a question to another tenant survey', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeSurvey($otherTenant->id);

    actingAs($this->admin)
        ->post(route('avana.survei.question.store', $foreign), [
            'question' => 'Tidak boleh',
            'type' => 'text',
        ])
        ->assertNotFound();
});

it('forbids a plain employee from listing or creating surveys', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.survei'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.survei.store'), ['title' => 'Tidak Boleh'])
        ->assertForbidden();
});

<?php

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\HiringRequest;
use App\Models\JobPosting;
use App\Models\RecruitmentRequisition;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

function hiringPayload(array $overrides = []): array
{
    return array_merge([
        'position_title' => 'Backend Engineer',
        'vacancy' => 2,
        'job_description' => 'Membangun API.',
        'qualification' => 'PHP, Laravel.',
        'employment_type' => 'tetap',
        'target_join_date' => '2026-09-01',
    ], $overrides);
}

it('renders the hiring request index', function (): void {
    HiringRequest::create([
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->admin->id,
        'position_title' => 'QA',
        'vacancy' => 1,
        'employment_type' => 'kontrak',
        'status' => 'open',
        'request_number' => 'HR-2026-0001',
    ]);

    actingAs($this->admin)
        ->get(route('avana.rekrutmen.hiring-request'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/rekrutmen/hiring-request', false)
            ->has('requests.0.request_number')
            ->has('departments')
            ->has('employmentTypes')
            ->has('kpis.open'));
});

it('creates a hiring request with an auto request number', function (): void {
    actingAs($this->admin)
        ->post(route('avana.rekrutmen.hiring-request.store'), hiringPayload())
        ->assertRedirect();

    $hr = HiringRequest::forTenant($this->tenant->id)->latest('id')->firstOrFail();

    expect($hr->position_title)->toBe('Backend Engineer')
        ->and($hr->vacancy)->toBe(2)
        ->and($hr->status)->toBe('open')
        ->and($hr->requester_id)->toBe($this->admin->id)
        ->and($hr->request_number)->toMatch('/^HR-\d{4}-\d{4}$/');
});

it('creates a requisition from a hiring request and moves it to in_process', function (): void {
    $hr = HiringRequest::create([
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->admin->id,
        'position_title' => 'Backend Engineer',
        'vacancy' => 2,
        'employment_type' => 'tetap',
        'status' => 'open',
        'request_number' => 'HR-2026-0001',
    ]);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.requisition.store'), [
            'hiring_request_id' => $hr->id,
            'position_title' => 'Backend Engineer',
            'vacancy' => 2,
            'qualification' => 'PHP',
            'job_description' => 'API',
            'employment_type' => 'tetap',
            'location' => 'Jakarta',
        ])
        ->assertRedirect();

    $req = RecruitmentRequisition::forTenant($this->tenant->id)->latest('id')->firstOrFail();

    expect($req->status)->toBe('draft')
        ->and($req->hiring_request_id)->toBe($hr->id)
        ->and($req->requisition_number)->toMatch('/^REQ-\d{4}-\d{4}$/')
        ->and($hr->fresh()->status)->toBe('in_process');
});

it('publishing a requisition spawns a live job posting', function (): void {
    $req = RecruitmentRequisition::create([
        'tenant_id' => $this->tenant->id,
        'position_title' => 'Backend Engineer',
        'vacancy' => 3,
        'employment_type' => 'tetap',
        'status' => 'draft',
        'requisition_number' => 'REQ-2026-0001',
    ]);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.requisition.publish', $req), [
            'publish_date' => '2026-07-21',
            'closing_date' => '2026-08-21',
        ])
        ->assertRedirect();

    $req->refresh();
    expect($req->status)->toBe('published')
        ->and($req->job_posting_id)->not->toBeNull();

    $posting = JobPosting::find($req->job_posting_id);
    expect($posting)->not->toBeNull()
        ->and($posting->title)->toBe('Backend Engineer')
        ->and($posting->quota)->toBe(3)
        ->and($posting->recruitment_requisition_id)->toBe($req->id);
});

it('rejects publishing an already published requisition', function (): void {
    $req = RecruitmentRequisition::create([
        'tenant_id' => $this->tenant->id,
        'position_title' => 'X',
        'vacancy' => 1,
        'employment_type' => 'tetap',
        'status' => 'published',
        'requisition_number' => 'REQ-2026-0002',
    ]);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.requisition.publish', $req), [
            'publish_date' => '2026-07-21',
            'closing_date' => '2026-08-21',
        ])
        ->assertStatus(422);
});

it('blocks a duplicate applicant on the same vacancy', function (): void {
    $posting = JobPosting::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Backend Engineer',
        'employment_type' => 'tetap',
        'quota' => 1,
        'status' => 'open',
    ]);

    $base = [
        'job_posting_id' => $posting->id,
        'name' => 'Alex',
        'email' => 'alex@example.com',
        'stage' => 'applied',
        'applied_date' => '2026-07-20',
    ];

    actingAs($this->admin)->post(route('avana.rekrutmen.pelamar.store'), $base)->assertRedirect();
    actingAs($this->admin)->post(route('avana.rekrutmen.pelamar.store'), $base)->assertSessionHasErrors('email');

    expect(Applicant::forTenant($this->tenant->id)->where('job_posting_id', $posting->id)->count())->toBe(1);
    expect(Applicant::forTenant($this->tenant->id)->latest('id')->first()->tracking_number)->toMatch('/^APP-\d{4}-\d{5}$/');
});

it('records an interview result of failed and rejects the candidate', function (): void {
    $posting = JobPosting::create([
        'tenant_id' => $this->tenant->id, 'title' => 'X', 'employment_type' => 'tetap', 'quota' => 1, 'status' => 'open',
    ]);
    $applicant = Applicant::create([
        'tenant_id' => $this->tenant->id, 'job_posting_id' => $posting->id,
        'name' => 'Budi', 'email' => 'budi@example.com', 'stage' => 'interview', 'applied_date' => '2026-07-20',
    ]);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.pelamar.interview-result', $applicant), ['interview_result' => 'failed'])
        ->assertRedirect();

    $applicant->refresh();
    expect($applicant->interview_result)->toBe('failed')
        ->and($applicant->stage)->toBe('rejected');
});

it('activates an accepted candidate into an active employee', function (): void {
    $posting = JobPosting::create([
        'tenant_id' => $this->tenant->id, 'title' => 'X', 'employment_type' => 'tetap', 'quota' => 1, 'status' => 'open',
    ]);
    $applicant = Applicant::create([
        'tenant_id' => $this->tenant->id, 'job_posting_id' => $posting->id,
        'name' => 'Charlie', 'email' => 'charlie@example.com', 'stage' => 'offer',
        'offer_status' => 'accepted', 'offer_start_date' => '2026-09-01', 'applied_date' => '2026-07-20',
    ]);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.pelamar.activate', $applicant), [])
        ->assertRedirect();

    $applicant->refresh();
    expect($applicant->employee_id)->not->toBeNull()
        ->and($applicant->stage)->toBe('hired')
        ->and($applicant->onboarded_at)->not->toBeNull();

    $employee = Employee::find($applicant->employee_id);
    expect($employee)->not->toBeNull()
        ->and($employee->full_name)->toBe('Charlie')
        ->and($employee->status)->toBe('active')
        ->and($employee->employee_number)->toMatch('/^EMP\d{5}$/');
});

it('does not activate a candidate whose offer is not accepted', function (): void {
    $posting = JobPosting::create([
        'tenant_id' => $this->tenant->id, 'title' => 'X', 'employment_type' => 'tetap', 'quota' => 1, 'status' => 'open',
    ]);
    $applicant = Applicant::create([
        'tenant_id' => $this->tenant->id, 'job_posting_id' => $posting->id,
        'name' => 'Dina', 'email' => 'dina@example.com', 'stage' => 'offer',
        'offer_status' => 'sent', 'applied_date' => '2026-07-20',
    ]);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.pelamar.activate', $applicant), [])
        ->assertSessionHasErrors('applicant');

    expect($applicant->fresh()->employee_id)->toBeNull();
});

it('renders candidate progress tracking', function (): void {
    actingAs($this->admin)
        ->get(route('avana.rekrutmen.progress'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/rekrutmen/progress', false)
            ->has('requests'));
});

it('forbids a non-recruitment user', function (): void {
    $plain = User::factory()->create(['tenant_id' => $this->tenant->id]);

    actingAs($plain)
        ->post(route('avana.rekrutmen.hiring-request.store'), hiringPayload())
        ->assertForbidden();
});

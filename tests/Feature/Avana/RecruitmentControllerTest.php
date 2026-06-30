<?php

use App\Models\Applicant;
use App\Models\ApplicantBackgroundCheck;
use App\Models\ApplicantMedicalCheck;
use App\Models\Department;
use App\Models\JobPosting;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Create a job posting for the given tenant.
 */
function makeJobPosting(int $tenantId, array $overrides = []): JobPosting
{
    return JobPosting::factory()->create(array_merge([
        'tenant_id' => $tenantId,
    ], $overrides));
}

/**
 * Create an applicant attached to a posting under the given tenant.
 */
function makeApplicant(int $tenantId, array $overrides = []): Applicant
{
    $posting = $overrides['job_posting_id'] ?? makeJobPosting($tenantId)->id;

    return Applicant::factory()->create(array_merge([
        'tenant_id' => $tenantId,
        'job_posting_id' => $posting,
    ], $overrides));
}

it('renders the recruitment index with the expected props', function (): void {
    makeJobPosting($this->tenant->id);
    makeApplicant($this->tenant->id, ['stage' => 'interview']);

    actingAs($this->admin)
        ->get(route('avana.rekrutmen'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/rekrutmen/index', false)
            ->has('postings.0', fn (Assert $row) => $row
                ->has('id')
                ->has('title')
                ->has('department')
                ->has('department_id')
                ->has('location')
                ->has('employment_type')
                ->has('quota')
                ->has('status')
                ->has('description')
                ->has('posted_date')
                ->has('closing_date')
                ->has('applicants_count'))
            ->has('pipeline.interview.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name')
                ->has('email')
                ->has('phone')
                ->has('source')
                ->has('stage')
                ->has('applied_date')
                ->has('notes')
                ->has('position')
                ->has('job_posting_id')
                ->has('job_title'))
            ->has('departments')
            ->has('stages')
            ->has('kpis'));
});

it('only lists postings that belong to the current tenant', function (): void {
    makeJobPosting($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeJobPosting($otherTenant->id);

    $tenantTotal = JobPosting::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.rekrutmen'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('postings', $tenantTotal));
});

it('creates a posting scoped to the current tenant', function (): void {
    $department = Department::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.store'), [
            'title' => 'Backend Engineer',
            'department_id' => $department->id,
            'location' => 'Jakarta',
            'employment_type' => 'tetap',
            'quota' => 3,
            'status' => 'open',
            'description' => 'Membangun API',
            'posted_date' => '2026-07-01',
            'closing_date' => '2026-08-01',
        ])
        ->assertRedirect(route('avana.rekrutmen'))
        ->assertSessionHas('success');

    $posting = JobPosting::where('title', 'Backend Engineer')->firstOrFail();

    expect($posting->tenant_id)->toBe($this->tenant->id);
    expect($posting->employment_type)->toBe('tetap');
    expect($posting->quota)->toBe(3);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.rekrutmen.store'), [
            'title' => '',
            'employment_type' => 'invalid',
            'quota' => 0,
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors(['title', 'employment_type', 'quota', 'status']);
});

it('updates an existing posting', function (): void {
    $posting = makeJobPosting($this->tenant->id, ['title' => 'Old Title']);

    actingAs($this->admin)
        ->put(route('avana.rekrutmen.update', $posting), [
            'title' => 'New Title',
            'department_id' => null,
            'location' => 'Bandung',
            'employment_type' => 'kontrak',
            'quota' => 5,
            'status' => 'closed',
            'description' => null,
            'posted_date' => null,
            'closing_date' => null,
        ])
        ->assertRedirect(route('avana.rekrutmen'))
        ->assertSessionHas('success');

    $posting->refresh();

    expect($posting->title)->toBe('New Title');
    expect($posting->employment_type)->toBe('kontrak');
    expect($posting->status)->toBe('closed');
});

it('deletes a posting', function (): void {
    $posting = makeJobPosting($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.rekrutmen.destroy', $posting))
        ->assertSessionHas('success');

    expect(JobPosting::find($posting->id))->toBeNull();
});

it('adds an applicant to a posting', function (): void {
    $posting = makeJobPosting($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.pelamar.store'), [
            'job_posting_id' => $posting->id,
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '08123456789',
            'source' => 'LinkedIn',
            'stage' => 'applied',
            'applied_date' => '2026-07-05',
            'notes' => 'Kandidat menjanjikan',
        ])
        ->assertRedirect(route('avana.rekrutmen'))
        ->assertSessionHas('success');

    $applicant = Applicant::where('email', 'budi@example.com')->firstOrFail();

    expect($applicant->tenant_id)->toBe($this->tenant->id);
    expect($applicant->job_posting_id)->toBe($posting->id);
    expect($applicant->stage)->toBe('applied');
});

it('validates the applicant request against tenant-scoped postings', function (): void {
    actingAs($this->admin)
        ->post(route('avana.rekrutmen.pelamar.store'), [
            'job_posting_id' => 99999,
            'name' => '',
            'email' => 'not-an-email',
            'stage' => 'invalid',
            'applied_date' => '',
        ])
        ->assertSessionHasErrors(['job_posting_id', 'name', 'email', 'stage', 'applied_date']);
});

it('moves an applicant to a different stage', function (): void {
    $applicant = makeApplicant($this->tenant->id, ['stage' => 'applied']);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.pelamar.stage', $applicant), [
            'stage' => 'interview',
        ])
        ->assertSessionHas('success');

    $applicant->refresh();

    expect($applicant->stage)->toBe('interview');
});

it('returns 404 when updating a posting from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeJobPosting($otherTenant->id);

    actingAs($this->admin)
        ->put(route('avana.rekrutmen.update', $foreign), [
            'title' => 'Hack',
            'employment_type' => 'tetap',
            'quota' => 1,
            'status' => 'open',
        ])
        ->assertNotFound();
});

it('returns 404 when deleting a posting from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeJobPosting($otherTenant->id);

    actingAs($this->admin)
        ->delete(route('avana.rekrutmen.destroy', $foreign))
        ->assertNotFound();

    expect(JobPosting::find($foreign->id))->not->toBeNull();
});

it('returns 404 when moving an applicant from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeApplicant($otherTenant->id, ['stage' => 'applied']);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.pelamar.stage', $foreign), [
            'stage' => 'hired',
        ])
        ->assertNotFound();

    $foreign->refresh();

    expect($foreign->stage)->toBe('applied');
});

it('renders the candidate detail screen with rich props', function (): void {
    $applicant = makeApplicant($this->tenant->id, ['stage' => 'interview']);
    ApplicantMedicalCheck::create([
        'tenant_id' => $this->tenant->id,
        'applicant_id' => $applicant->id,
        'title' => 'MCU Umum',
        'status' => 'passed',
    ]);

    actingAs($this->admin)
        ->get(route('avana.rekrutmen.pelamar.show', $applicant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/rekrutmen/candidate', false)
            ->where('applicant.id', $applicant->id)
            ->has('applicant.medical_checks.0')
            ->has('applicant.background_checks')
            ->has('applicant.cv_url')
            ->has('stages'));
});

it('updates a candidate profile and social links', function (): void {
    $applicant = makeApplicant($this->tenant->id);

    actingAs($this->admin)
        ->put(route('avana.rekrutmen.pelamar.update', $applicant), [
            'name' => 'Dio Pratama',
            'email' => 'dio@example.com',
            'phone' => '0811222333',
            'position' => 'HRGA',
            'linkedin_url' => 'https://linkedin.com/in/dio',
            'portfolio_url' => 'https://dio.dev',
        ])
        ->assertSessionHas('success');

    $applicant->refresh();

    expect($applicant->name)->toBe('Dio Pratama');
    expect($applicant->position)->toBe('HRGA');
    expect($applicant->linkedin_url)->toBe('https://linkedin.com/in/dio');
});

it('schedules an interview and advances the stage', function (): void {
    $applicant = makeApplicant($this->tenant->id, ['stage' => 'applied']);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.pelamar.interview', $applicant), [
            'interview_at' => '2026-07-10 09:00',
        ])
        ->assertSessionHas('success');

    $applicant->refresh();

    expect($applicant->stage)->toBe('interview');
    expect($applicant->interview_at)->not->toBeNull();
});

it('records a job offer and advances the stage', function (): void {
    $applicant = makeApplicant($this->tenant->id, ['stage' => 'interview']);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.pelamar.offer', $applicant), [
            'offer_note' => 'Gaji 12jt, mulai 1 Agustus',
        ])
        ->assertSessionHas('success');

    $applicant->refresh();

    expect($applicant->stage)->toBe('offer');
    expect($applicant->offered_at)->not->toBeNull();
    expect($applicant->offer_note)->toBe('Gaji 12jt, mulai 1 Agustus');
});

it('uploads a CV file for the applicant', function (): void {
    Storage::fake('public');
    $applicant = makeApplicant($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.pelamar.cv', $applicant), [
            'cv' => UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf'),
        ])
        ->assertSessionHas('success');

    $applicant->refresh();

    expect($applicant->cv_path)->not->toBeNull();
    Storage::disk('public')->assertExists($applicant->cv_path);
});

it('stores a medical checkup record with a document', function (): void {
    Storage::fake('public');
    $applicant = makeApplicant($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.pelamar.medical', $applicant), [
            'title' => 'MCU Lengkap',
            'status' => 'passed',
            'checked_at' => '2026-07-08',
            'document' => UploadedFile::fake()->create('mcu.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHas('success');

    $check = ApplicantMedicalCheck::where('applicant_id', $applicant->id)->firstOrFail();

    expect($check->status)->toBe('passed');
    expect($check->file_path)->not->toBeNull();
});

it('stores a background check record', function (): void {
    $applicant = makeApplicant($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.rekrutmen.pelamar.background', $applicant), [
            'check_type' => 'employment',
            'status' => 'clear',
            'notes' => 'Verifikasi 2 perusahaan terakhir',
        ])
        ->assertSessionHas('success');

    $check = ApplicantBackgroundCheck::where('applicant_id', $applicant->id)->firstOrFail();

    expect($check->check_type)->toBe('employment');
    expect($check->status)->toBe('clear');
});

it('returns 404 when viewing an applicant from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeApplicant($otherTenant->id);

    actingAs($this->admin)
        ->get(route('avana.rekrutmen.pelamar.show', $foreign))
        ->assertNotFound();
});

it('forbids a plain employee from listing or creating postings', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.rekrutmen'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.rekrutmen.store'), [
            'title' => 'Tidak Boleh',
            'employment_type' => 'tetap',
            'quota' => 1,
            'status' => 'open',
        ])
        ->assertForbidden();
});

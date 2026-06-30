<?php

use App\Models\Applicant;
use App\Models\JobPosting;
use App\Models\Tenant;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->tenant = Tenant::query()->firstOrFail();
});

it('lists only open postings on the public careers portal', function (): void {
    JobPosting::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'open', 'title' => 'Open Role']);
    JobPosting::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'closed', 'title' => 'Closed Role']);

    $this->get(route('careers', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/careers/index', false)
            ->where('tenant.slug', $this->tenant->slug)
            ->has('postings', 1)
            ->where('postings.0.title', 'Open Role'));
});

it('shows a single open posting with its apply form', function (): void {
    $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'open']);

    $this->get(route('careers.show', [$this->tenant, $posting]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/careers/show', false)
            ->where('posting.id', $posting->id));
});

it('returns 404 for a closed posting', function (): void {
    $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'closed']);

    $this->get(route('careers.show', [$this->tenant, $posting]))->assertNotFound();
});

it('returns 404 when the posting belongs to another tenant', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $posting = JobPosting::factory()->create(['tenant_id' => $other->id, 'status' => 'open']);

    $this->get(route('careers.show', [$this->tenant, $posting]))->assertNotFound();
});

it('accepts a public application and creates an applicant', function (): void {
    $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'open']);

    $this->post(route('careers.apply', [$this->tenant, $posting]), [
        'name' => 'Sinta Dewi',
        'email' => 'sinta@example.com',
        'phone' => '0812000111',
        'linkedin_url' => 'https://linkedin.com/in/sinta',
    ])->assertSessionHas('success');

    $applicant = Applicant::where('email', 'sinta@example.com')->firstOrFail();

    expect($applicant->tenant_id)->toBe($this->tenant->id);
    expect($applicant->job_posting_id)->toBe($posting->id);
    expect($applicant->stage)->toBe('applied');
    expect($applicant->source)->toBe('Career Portal');
});

it('rejects an application from a blacklisted email', function (): void {
    $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'open']);
    Applicant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'job_posting_id' => $posting->id,
        'email' => 'blocked@example.com',
        'blacklisted' => true,
    ]);

    $this->post(route('careers.apply', [$this->tenant, $posting]), [
        'name' => 'Blocked Person',
        'email' => 'blocked@example.com',
    ])->assertSessionHasErrors('email');

    expect(Applicant::where('email', 'blocked@example.com')->count())->toBe(1);
});

it('validates required application fields', function (): void {
    $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'open']);

    $this->post(route('careers.apply', [$this->tenant, $posting]), [
        'name' => '',
        'email' => 'not-an-email',
    ])->assertSessionHasErrors(['name', 'email']);
});

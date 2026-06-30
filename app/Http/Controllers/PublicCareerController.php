<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\JobPosting;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public (unauthenticated) careers portal: each tenant exposes its open job
 * postings at /karir/{slug} so candidates can browse and apply directly.
 */
class PublicCareerController extends Controller
{
    /**
     * List a tenant's open job postings.
     */
    public function index(Tenant $tenant): Response
    {
        $postings = JobPosting::forTenant($tenant->id)
            ->where('status', 'open')
            ->with('department:id,name')
            ->latest('id')
            ->get()
            ->map(fn (JobPosting $posting): array => $this->transformPosting($posting));

        return Inertia::render('public/careers/index', [
            'tenant' => $this->transformTenant($tenant),
            'postings' => $postings,
        ]);
    }

    /**
     * Show a single open posting with its application form.
     */
    public function show(Tenant $tenant, JobPosting $jobPosting): Response
    {
        $this->ensureOpenPosting($tenant, $jobPosting);

        $jobPosting->loadMissing('department:id,name');

        return Inertia::render('public/careers/show', [
            'tenant' => $this->transformTenant($tenant),
            'posting' => $this->transformPosting($jobPosting),
        ]);
    }

    /**
     * Accept a public application and create an applicant record.
     */
    public function apply(Request $request, Tenant $tenant, JobPosting $jobPosting): RedirectResponse
    {
        $this->ensureOpenPosting($tenant, $jobPosting);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $isBlacklisted = Applicant::forTenant($tenant->id)
            ->where('email', $data['email'])
            ->where('blacklisted', true)
            ->exists();

        if ($isBlacklisted) {
            throw ValidationException::withMessages([
                'email' => 'Lamaran tidak dapat diproses. Silakan hubungi tim rekrutmen.',
            ]);
        }

        Applicant::create([
            ...$data,
            'tenant_id' => $tenant->id,
            'job_posting_id' => $jobPosting->id,
            'position' => $jobPosting->title,
            'stage' => 'applied',
            'source' => 'Career Portal',
            'applied_date' => now()->toDateString(),
        ]);

        return back()->with('success', 'Lamaran berhasil dikirim. Tim rekrutmen akan menghubungi Anda.');
    }

    /**
     * Abort with 404 unless the posting belongs to the tenant and is open.
     */
    private function ensureOpenPosting(Tenant $tenant, JobPosting $jobPosting): void
    {
        abort_if((int) $jobPosting->tenant_id !== (int) $tenant->id, 404);
        abort_if($jobPosting->status !== 'open', 404);
    }

    /**
     * Build the public posting payload.
     *
     * @return array<string, mixed>
     */
    private function transformPosting(JobPosting $posting): array
    {
        return [
            'id' => $posting->id,
            'title' => $posting->title,
            'department' => $posting->department?->name,
            'location' => $posting->location,
            'employment_type' => $posting->employment_type,
            'description' => $posting->description,
            'posted_date' => $posting->posted_date?->toDateString(),
            'closing_date' => $posting->closing_date?->toDateString(),
        ];
    }

    /**
     * Build the public tenant payload.
     *
     * @return array<string, mixed>
     */
    private function transformTenant(Tenant $tenant): array
    {
        return [
            'slug' => $tenant->slug,
            'name' => $tenant->company_name ?? $tenant->name,
        ];
    }
}

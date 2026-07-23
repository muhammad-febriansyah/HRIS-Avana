<?php

use App\Models\AiSetting;
use App\Models\Applicant;
use App\Models\JobPosting;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenantId = (int) $this->admin->tenant_id;
});

function makeAiApplicant(int $tenantId): Applicant
{
    $job = JobPosting::create([
        'tenant_id' => $tenantId,
        'title' => 'Backend Engineer',
        'status' => 'open',
        'employment_type' => 'tetap',
        'quota' => 1,
        'description' => 'Membutuhkan backend engineer berpengalaman.',
    ]);

    return Applicant::create([
        'tenant_id' => $tenantId,
        'job_posting_id' => $job->id,
        'name' => 'Budi Santoso',
        'position' => 'Backend Engineer',
        'email' => 'budi.ai.test@example.com',
        'stage' => 'applied',
        'applied_date' => now()->toDateString(),
    ]);
}

it('streams an error when no AI key is configured', function (): void {
    AiSetting::query()->updateOrCreate(['id' => 1], ['provider' => 'openai', 'api_key' => null, 'model' => 'gpt-5', 'is_enabled' => true]);

    $response = actingAs($this->admin)->post('/avana/rekrutmen/ai/analyze');

    $response->assertOk();
    expect($response->streamedContent())->toContain('belum dikonfigurasi');
});

it('streams a score for each applicant and persists it', function (): void {
    Applicant::where('tenant_id', $this->tenantId)->delete();
    AiSetting::query()->updateOrCreate(['id' => 1], ['provider' => 'openai', 'api_key' => 'sk-test', 'model' => 'gpt-4o-mini', 'is_enabled' => true]);
    $applicant = makeAiApplicant($this->tenantId);

    Prism::fake([
        StructuredResponseFake::make()->withStructured([
            'score' => 88,
            'recommendation' => 'Kandidat kuat, lanjutkan ke wawancara.',
            'reasoning' => 'Pengalaman backend relevan dengan kebutuhan lowongan.',
        ]),
    ]);

    $response = actingAs($this->admin)->post('/avana/rekrutmen/ai/analyze');

    $response->assertOk();
    expect($response->streamedContent())->toContain('"score":88');

    $applicant->refresh();
    expect($applicant->ai_confidence)->toBe(88);
    expect($applicant->ai_recommendation)->toBe('Kandidat kuat, lanjutkan ke wawancara.');
    expect($applicant->ai_reasoning)->toBe('Pengalaman backend relevan dengan kebutuhan lowongan.');
});

it('clamps an out-of-range AI score into 0-100', function (): void {
    Applicant::where('tenant_id', $this->tenantId)->delete();
    AiSetting::query()->updateOrCreate(['id' => 1], ['provider' => 'openai', 'api_key' => 'sk-test', 'model' => 'gpt-4o-mini', 'is_enabled' => true]);
    $applicant = makeAiApplicant($this->tenantId);

    Prism::fake([
        StructuredResponseFake::make()->withStructured(['score' => 140, 'recommendation' => 'Sangat cocok.', 'reasoning' => 'Semua kriteria terpenuhi.']),
    ]);

    $response = actingAs($this->admin)->post('/avana/rekrutmen/ai/analyze');
    $response->streamedContent();

    expect($applicant->refresh()->ai_confidence)->toBe(100);
});

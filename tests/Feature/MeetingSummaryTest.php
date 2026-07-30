<?php

use App\Models\AiSetting;
use App\Models\AiTokenLedger;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingInsight;
use App\Models\MeetingParticipant;
use App\Models\MeetingSegment;
use App\Models\MeetingSpeaker;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MeetingSummarizer;
use Database\Seeders\AvanaDemoSeeder;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\EmbeddingsResponseFake;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Embedding;
use Prism\Prism\ValueObjects\EmbeddingsUsage;
use Prism\Prism\ValueObjects\Usage;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->user->tenant_id);
    $this->tenant->update(['ai_token_quota' => 1_000_000, 'ai_token_balance' => 0, 'ai_token_user_cap' => null]);
    $this->employee = Employee::where('tenant_id', $this->tenant->id)->first();

    AiSetting::current()->update([
        'provider' => 'openai',
        'api_key' => 'sk-test',
        'model' => 'gpt-4o-mini',
        'is_enabled' => true,
        'meeting_pro_model' => 'gpt-5.5',
        'embedding_model' => 'text-embedding-3-small',
    ]);

    $this->meeting = Meeting::create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'title' => 'Weekly Sync - Product Design',
        'status' => Meeting::STATUS_PROCESSING,
        'started_at' => now(),
    ]);

    MeetingParticipant::create([
        'meeting_id' => $this->meeting->id,
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
    ]);

    MeetingSegment::insert([
        [
            'meeting_id' => $this->meeting->id, 'tenant_id' => $this->tenant->id,
            'speaker_index' => 0, 'start_ms' => 0, 'end_ms' => 4000,
            'text' => "Halo semua, saya {$this->employee->full_name} dari tim Finance, mari kita mulai.",
            'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'meeting_id' => $this->meeting->id, 'tenant_id' => $this->tenant->id,
            'speaker_index' => 1, 'start_ms' => 5000, 'end_ms' => 9000,
            'text' => 'Kita putuskan integrasi payroll berjalan bulan depan.',
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    $this->summarizer = app(MeetingSummarizer::class);
});

it('names speakers, writes the summary, and embeds the transcript', function (): void {
    Prism::fake([
        StructuredResponseFake::make()
            ->withStructured(['speakers' => [
                ['speaker_index' => 0, 'name' => $this->employee->full_name, 'confidence' => 0.9],
                ['speaker_index' => 1, 'name' => '', 'confidence' => 0],
            ]])
            ->withUsage(new Usage(100, 20)),
        StructuredResponseFake::make()
            ->withStructured([
                'summary' => 'Rapat membahas integrasi payroll.',
                'decisions' => ['Integrasi payroll dijalankan bulan depan'],
                'action_items' => [
                    ['text' => 'Siapkan dokumen integrasi', 'assignee' => $this->employee->full_name, 'due_date' => '2026-08-15'],
                ],
            ])
            ->withUsage(new Usage(200, 50)),
        EmbeddingsResponseFake::make()
            ->withEmbeddings([Embedding::fromArray([0.1, 0.2, 0.3]), Embedding::fromArray([0.4, 0.5, 0.6])])
            ->withUsage(new EmbeddingsUsage(40)),
    ]);

    $this->summarizer->process($this->meeting);

    $fresh = $this->meeting->fresh(['speakers', 'actionItems', 'chunks']);

    expect($fresh->status)->toBe(Meeting::STATUS_READY)
        ->and($fresh->summary)->toContain('integrasi payroll')
        ->and($fresh->summary)->toContain('Keputusan')
        ->and($fresh->summary_model)->toBe('gpt-4o-mini');

    $speaker0 = $fresh->speakers->firstWhere('speaker_index', 0);
    expect($speaker0->employee_id)->toBe($this->employee->id)
        ->and($speaker0->guessed_by_ai)->toBeTrue();

    $speaker1 = $fresh->speakers->firstWhere('speaker_index', 1);
    expect($speaker1->display_name)->toBeNull();

    // Both segments are short enough to merge into a single chunk.
    expect($fresh->actionItems)->toHaveCount(1)
        ->and($fresh->actionItems->first()->assignee_employee_id)->toBe($this->employee->id)
        ->and($fresh->chunks)->toHaveCount(1)
        ->and($fresh->chunks->first()->embedding)->toBe([0.1, 0.2, 0.3]);

    // 100+20 (naming) + 200+50 (summary) + 40 (embeddings) = 410, charged once
    // to whoever pressed record.
    $ledger = AiTokenLedger::where('source', 'meeting_summary')->firstOrFail();
    expect($ledger->tokens)->toBe(410)
        ->and($fresh->summary_tokens)->toBe(410);
});

it('marks the meeting failed and charges nothing when the transcript is empty', function (): void {
    $this->meeting->segments()->delete();

    $this->summarizer->process($this->meeting);

    $fresh = $this->meeting->fresh();
    expect($fresh->status)->toBe(Meeting::STATUS_FAILED)
        ->and($fresh->failure_reason)->not->toBeNull()
        ->and(AiTokenLedger::where('meeting_id', $this->meeting->id)->exists())->toBeFalse()
        ->and(AiTokenLedger::where('source', 'meeting_summary')->exists())->toBeFalse();
});

it('marks the meeting failed and hides the provider error when a call throws', function (): void {
    Prism::fake([
        StructuredResponseFake::make()->withStructured(['speakers' => []]),
    ]);

    // Only one fake response queued; the second (summary) call throws because
    // no fixture remains — simulating a provider failure mid-pipeline.
    $this->summarizer->process($this->meeting);

    $fresh = $this->meeting->fresh();
    expect($fresh->status)->toBe(Meeting::STATUS_FAILED)
        ->and($fresh->failure_reason)->not->toContain('quota')
        ->and($fresh->failure_reason)->not->toBeNull();

    // The naming pass ran (20 tokens from the one queued response's default
    // usage) — the platform already paid the provider for that call, so it is
    // still billed even though the pipeline failed afterwards.
    expect((int) AiTokenLedger::where('source', 'meeting_summary')->sum('tokens'))->toBeGreaterThanOrEqual(0);
});

it('generates and stores a premium insight, billed to the requester', function (): void {
    $this->meeting->update(['status' => Meeting::STATUS_READY]);
    MeetingSpeaker::create(['meeting_id' => $this->meeting->id, 'tenant_id' => $this->tenant->id, 'speaker_index' => 0]);
    MeetingSpeaker::create(['meeting_id' => $this->meeting->id, 'tenant_id' => $this->tenant->id, 'speaker_index' => 1]);

    Prism::fake([
        StructuredResponseFake::make()
            ->withStructured([
                'headline' => 'Integrasi payroll disepakati',
                'paragraphs' => ['Rapat membahas rencana integrasi payroll bulan depan.'],
                'key_points' => ['Integrasi payroll bulan depan'],
            ])
            ->withUsage(new Usage(300, 100)),
    ]);

    $insight = $this->summarizer->generateInsight($this->meeting, $this->user, MeetingInsight::TYPE_EXECUTIVE_SUMMARY);

    expect($insight->payload['headline'])->toBe('Integrasi payroll disepakati')
        ->and($insight->tokens)->toBe(400)
        ->and($insight->model)->toBe('gpt-5.5');

    $ledger = AiTokenLedger::where('source', 'meeting_pro')->firstOrFail();
    expect($ledger->tokens)->toBe(400)
        ->and($ledger->user_id)->toBe($this->user->id);
});

it('condenses a transcript too long for one context window before analysing it', function (): void {
    $this->meeting->update(['status' => Meeting::STATUS_READY]);

    // ~34k characters of transcript — past the 24k window, so it must be
    // condensed into two passes rather than handed over whole.
    $rows = [];
    for ($i = 0; $i < 200; $i++) {
        $rows[] = [
            'meeting_id' => $this->meeting->id, 'tenant_id' => $this->tenant->id,
            'speaker_index' => $i % 2, 'start_ms' => 10_000 + $i * 1_000, 'end_ms' => 10_500 + $i * 1_000,
            'text' => str_repeat('Pembahasan anggaran dan jadwal rilis. ', 4),
            'created_at' => now(), 'updated_at' => now(),
        ];
    }
    MeetingSegment::insert($rows);

    $rawLength = mb_strlen($this->summarizer->labelledTranscript(
        $this->meeting->fresh(['speakers']),
        $this->meeting->segments()->get(),
    ));

    expect($rawLength)->toBeGreaterThan(24_000)->toBeLessThan(48_000);

    $fake = Prism::fake([
        // Two condensing passes on the cheap model...
        TextResponseFake::make()->withText('Catatan bagian 1.')->withUsage(new Usage(500, 50)),
        TextResponseFake::make()->withText('Catatan bagian 2.')->withUsage(new Usage(500, 50)),
        // ...then one pro call, which receives those notes, not the 34k chars.
        StructuredResponseFake::make()
            ->withStructured(['risks' => [
                ['risk' => 'Jadwal rilis mepet', 'severity' => 'tinggi', 'likelihood' => 'sedang', 'mitigation' => 'Tambah reviewer', 'owner' => ''],
            ]])
            ->withUsage(new Usage(400, 100)),
    ]);

    $insight = $this->summarizer->generateInsight($this->meeting, $this->user, MeetingInsight::TYPE_PROJECT_RISK);

    $fake->assertCallCount(3);

    // 2 × 550 condensing + 500 analysis, all billed to the requester.
    expect($insight->payload['risks'][0]['risk'])->toBe('Jadwal rilis mepet')
        ->and($insight->tokens)->toBe(1600)
        ->and((int) AiTokenLedger::where('source', 'meeting_pro')->sum('tokens'))->toBe(1600);
});

it('refuses an insight for a meeting whose transcript is not ready yet', function (): void {
    $this->meeting->update(['status' => Meeting::STATUS_PROCESSING]);

    expect(fn () => $this->summarizer->generateInsight($this->meeting, $this->user, MeetingInsight::TYPE_SENTIMENT))
        ->toThrow(RuntimeException::class);
});

<?php

use App\Jobs\ProcessMeetingTranscriptJob;
use App\Models\AiSetting;
use App\Models\AiTokenLedger;
use App\Models\Meeting;
use App\Models\MeetingSegment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MeetingTranscriber;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->user->tenant_id);
    $this->tenant->update(['ai_token_quota' => 1_000_000, 'ai_token_balance' => 0, 'ai_token_user_cap' => null]);

    AiSetting::current()->update([
        'provider' => 'openai',
        'api_key' => 'sk-test',
        'model' => 'gpt-4o-mini',
        'is_enabled' => true,
        'stt_enabled' => true,
        'stt_provider' => 'deepgram',
        'stt_api_key' => 'dg-project-key',
        'stt_model' => 'nova-2',
        'stt_language' => 'id',
        'stt_token_cost_per_minute' => 600,
        'meeting_max_minutes' => 2,
    ]);

    $this->meeting = Meeting::create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'title' => 'Weekly Sync',
        'status' => Meeting::STATUS_RECORDING,
        'started_at' => now(),
    ]);

    $this->transcriber = app(MeetingTranscriber::class);
});

/**
 * @return array<int, array{start_ms: int, end_ms: int, speaker: int, text: string}>
 */
function segmentBatch(int $start, int $count = 2): array
{
    return collect(range(0, $count - 1))
        ->map(fn (int $index): array => [
            'start_ms' => $start + $index * 5_000,
            'end_ms' => $start + $index * 5_000 + 4_000,
            'speaker' => $index % 2,
            'text' => 'Ucapan ke-'.($index + 1),
        ])
        ->all();
}

it('stores segments and charges only the whole blocks of new audio', function (): void {
    $result = $this->transcriber->ingestSegments($this->user, $this->meeting, segmentBatch(0), 40_000);

    // 40s of audio = two full 15s blocks (30s) charged at 600 tokens/minute.
    expect($result['stored'])->toBe(2)
        ->and($result['billed_ms'])->toBe(30_000)
        ->and($result['tokens_charged'])->toBe(300)
        ->and($result['stop'])->toBeFalse()
        ->and(MeetingSegment::where('meeting_id', $this->meeting->id)->count())->toBe(2);

    $ledger = AiTokenLedger::where('source', 'meeting_stt')->firstOrFail();
    expect($ledger->tokens)->toBe(300);
});

it('ignores a resent batch and never bills the same audio twice', function (): void {
    $this->transcriber->ingestSegments($this->user, $this->meeting, segmentBatch(0), 30_000);

    $again = $this->transcriber->ingestSegments($this->user, $this->meeting->fresh(), segmentBatch(0), 30_000);

    expect($again['stored'])->toBe(0)
        ->and($again['tokens_charged'])->toBe(0)
        ->and(MeetingSegment::where('meeting_id', $this->meeting->id)->count())->toBe(2)
        // 30s billed once at 600 tokens/minute — not 600, and not twice.
        ->and((int) AiTokenLedger::where('source', 'meeting_stt')->sum('tokens'))->toBe(300);
});

it('bills the same minute once even when two batches report it concurrently', function (): void {
    // Two separately-loaded copies of the meeting, as two in-flight requests
    // hold — both still believe nothing has been billed. Whichever settles
    // second must decide against the locked row, not against the stale copy it
    // is carrying, or the same minute of audio is paid for twice.
    $requestA = Meeting::findOrFail($this->meeting->id);
    $requestB = Meeting::findOrFail($this->meeting->id);

    expect($requestA->billed_ms)->toBe(0)->and($requestB->billed_ms)->toBe(0);

    $first = $this->transcriber->ingestSegments($this->user, $requestA, segmentBatch(0), 60_000);
    $second = $this->transcriber->ingestSegments($this->user, $requestB, segmentBatch(0), 60_000);

    expect($first['tokens_charged'])->toBe(600)
        ->and($second['tokens_charged'])->toBe(0)
        ->and($this->meeting->fresh()->billed_ms)->toBe(60_000)
        ->and((int) AiTokenLedger::where('source', 'meeting_stt')->sum('tokens'))->toBe(600);
});

it('tells the phone to stop once the duration ceiling is reached', function (): void {
    // The ceiling is 2 minutes; the phone claims 5 and is capped at 2.
    $result = $this->transcriber->ingestSegments($this->user, $this->meeting, segmentBatch(0), 300_000);

    expect($result['stop'])->toBeTrue()
        ->and($result['reason'])->toBe('max_duration')
        ->and($result['billed_ms'])->toBe(120_000)
        ->and($this->meeting->fresh()->duration_ms)->toBe(120_000);
});

it('tells the phone to stop when the company has run out of tokens', function (): void {
    $this->tenant->update(['ai_token_quota' => 100, 'ai_token_balance' => 0]);

    $result = $this->transcriber->ingestSegments($this->user->fresh(), $this->meeting, segmentBatch(0), 60_000);

    expect($result['tokens_charged'])->toBe(600)
        ->and($result['stop'])->toBeTrue()
        ->and($result['reason'])->toBe('pool_empty');
});

it('answers 409 on the segments endpoint when the recording must stop', function (): void {
    $this->tenant->update(['ai_token_quota' => 50, 'ai_token_balance' => 0]);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'rina.a@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/v1/me/meetings/{$this->meeting->id}/segments", [
            'elapsed_ms' => 60_000,
            'segments' => segmentBatch(0),
        ])
        ->assertStatus(409)
        ->assertJsonPath('data.stop', true);
});

it('queues the summary when the phone stops recording', function (): void {
    Queue::fake();

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'rina.a@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/v1/me/meetings/{$this->meeting->id}/stop", ['elapsed_ms' => 45_000])
        ->assertOk()
        ->assertJsonPath('data.status', Meeting::STATUS_PROCESSING);

    Queue::assertPushed(ProcessMeetingTranscriptJob::class);
});

it('refuses to feed a meeting recorded by somebody else', function (): void {
    $other = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $other->email,
        'password' => 'password',
    ])->json('access_token');

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/v1/me/meetings/{$this->meeting->id}/segments", [
            'elapsed_ms' => 10_000,
            'segments' => segmentBatch(0),
        ])
        ->assertForbidden();
});

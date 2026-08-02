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

/**
 * Speech that runs from the start of the recording to `$untilMs`.
 *
 * Charging follows what was said, not how long the phone was open, so a test
 * that claims a minute of audio has to put a minute of speech behind it.
 *
 * @return array<int, array{start_ms: int, end_ms: int, speaker: int, text: string}>
 */
function speechUpTo(int $untilMs): array
{
    $segments = [];

    for ($start = 0; $start < $untilMs; $start += 5_000) {
        $segments[] = [
            'start_ms' => $start,
            'end_ms' => min($start + 5_000, $untilMs),
            'speaker' => intdiv($start, 5_000) % 2,
            'text' => 'Ucapan pada detik '.intdiv($start, 1_000),
        ];
    }

    return $segments;
}

it('stores segments and charges only the whole blocks of new audio', function (): void {
    $result = $this->transcriber->ingestSegments($this->user, $this->meeting, speechUpTo(40_000), 40_000);

    // 40s of speech = two full 15s blocks (30s) charged at 600 tokens/minute.
    expect($result['stored'])->toBe(8)
        ->and($result['billed_ms'])->toBe(30_000)
        ->and($result['tokens_charged'])->toBe(300)
        ->and($result['stop'])->toBeFalse()
        ->and(MeetingSegment::where('meeting_id', $this->meeting->id)->count())->toBe(8);

    $ledger = AiTokenLedger::where('source', 'meeting_stt')->firstOrFail();
    expect($ledger->tokens)->toBe(300);
});

it('ignores a resent batch and never bills the same audio twice', function (): void {
    $this->transcriber->ingestSegments($this->user, $this->meeting, speechUpTo(30_000), 30_000);

    $again = $this->transcriber->ingestSegments($this->user, $this->meeting->fresh(), speechUpTo(30_000), 30_000);

    expect($again['stored'])->toBe(0)
        ->and($again['tokens_charged'])->toBe(0)
        ->and(MeetingSegment::where('meeting_id', $this->meeting->id)->count())->toBe(6)
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

    $first = $this->transcriber->ingestSegments($this->user, $requestA, speechUpTo(60_000), 60_000);
    $second = $this->transcriber->ingestSegments($this->user, $requestB, speechUpTo(60_000), 60_000);

    expect($first['tokens_charged'])->toBe(600)
        ->and($second['tokens_charged'])->toBe(0)
        ->and($this->meeting->fresh()->billed_ms)->toBe(60_000)
        ->and((int) AiTokenLedger::where('source', 'meeting_stt')->sum('tokens'))->toBe(600);
});

it('tells the phone to stop once the duration ceiling is reached', function (): void {
    // The ceiling is 2 minutes; the phone claims 5 and is capped at 2.
    $result = $this->transcriber->ingestSegments($this->user, $this->meeting, speechUpTo(300_000), 300_000);

    expect($result['stop'])->toBeTrue()
        ->and($result['reason'])->toBe('max_duration')
        ->and($result['billed_ms'])->toBe(120_000)
        ->and($this->meeting->fresh()->duration_ms)->toBe(120_000);
});

it('tells the phone to stop when the company has run out of tokens', function (): void {
    $this->tenant->update(['ai_token_quota' => 100, 'ai_token_balance' => 0]);

    $result = $this->transcriber->ingestSegments($this->user->fresh(), $this->meeting, speechUpTo(60_000), 60_000);

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
            'segments' => speechUpTo(60_000),
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

it('does not bill a room that has gone quiet', function (): void {
    // A minute of speech, then four minutes of an open socket saying nothing.
    $this->transcriber->ingestSegments($this->user, $this->meeting, speechUpTo(60_000), 60_000);
    $before = (int) AiTokenLedger::where('source', 'meeting_stt')->sum('tokens');

    $result = $this->transcriber->ingestSegments($this->user, $this->meeting->fresh(), [], 300_000);

    // Silence is real audio the provider meters, but it is not what the tenant
    // came to buy — a phone left face-down must not run up a bill.
    expect($result['tokens_charged'])->toBe(0)
        ->and((int) AiTokenLedger::where('source', 'meeting_stt')->sum('tokens'))->toBe($before);
});

it('stops a recording nobody has spoken into for ten minutes', function (): void {
    // No ceiling configured: the idle cutoff is the only thing left standing
    // between a forgotten handset and an afternoon of provider time.
    // Zero means no ceiling: resolvedStt() reads it back as null.
    AiSetting::current()->update(['meeting_max_minutes' => 0]);

    $this->transcriber->ingestSegments($this->user, $this->meeting, speechUpTo(60_000), 60_000);

    $result = $this->transcriber->ingestSegments(
        $this->user,
        $this->meeting->fresh(),
        [],
        60_000 + MeetingTranscriber::IDLE_STOP_MS,
    );

    expect($result['stop'])->toBeTrue()
        ->and($result['reason'])->toBe('idle')
        ->and($result['message'])->toContain('Tidak ada suara');
});

it('keeps recording while somebody is still talking', function (): void {
    // Zero means no ceiling: resolvedStt() reads it back as null.
    AiSetting::current()->update(['meeting_max_minutes' => 0]);

    // Well past any old ceiling, but the room is still speaking.
    $result = $this->transcriber->ingestSegments($this->user, $this->meeting, speechUpTo(600_000), 600_000);

    expect($result['stop'])->toBeFalse()
        ->and($result['reason'])->toBeNull();
});

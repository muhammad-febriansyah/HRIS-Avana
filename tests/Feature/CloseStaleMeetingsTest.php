<?php

use App\Jobs\ProcessMeetingTranscriptJob;
use App\Models\Meeting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->tenant = Tenant::create([
        'name' => 'PT Nyangkut',
        'company_name' => 'PT Nyangkut',
        'slug' => 'nyangkut',
        'status' => 'active',
    ]);
});

/** @param  array<string, mixed>  $attributes */
function staleMeeting(int $tenantId, array $attributes = []): Meeting
{
    return Meeting::create([
        'tenant_id' => $tenantId,
        'title' => 'Rapat Nyangkut',
        'status' => Meeting::STATUS_RECORDING,
        'started_at' => now()->subHours(9),
        ...$attributes,
    ]);
}

it('closes a recording the phone never came back from', function (): void {
    $meeting = staleMeeting($this->tenant->id);

    $this->artisan('avana:close-stale-meetings')->assertSuccessful();

    expect($meeting->fresh()->status)->toBe(Meeting::STATUS_PROCESSING)
        ->and($meeting->fresh()->ended_at)->not->toBeNull();

    // Whatever it did capture is still worth summarising.
    Queue::assertPushed(ProcessMeetingTranscriptJob::class);
});

it('leaves a recording that is still within the grace window alone', function (): void {
    $meeting = staleMeeting($this->tenant->id, ['started_at' => now()->subMinutes(30)]);

    $this->artisan('avana:close-stale-meetings')->assertSuccessful();

    expect($meeting->fresh()->status)->toBe(Meeting::STATUS_RECORDING);

    Queue::assertNothingPushed();
});

it('never touches a meeting that already finished', function (): void {
    $ready = staleMeeting($this->tenant->id, ['status' => Meeting::STATUS_READY]);
    $failed = staleMeeting($this->tenant->id, ['status' => Meeting::STATUS_FAILED]);

    $this->artisan('avana:close-stale-meetings')->assertSuccessful();

    expect($ready->fresh()->status)->toBe(Meeting::STATUS_READY)
        ->and($failed->fresh()->status)->toBe(Meeting::STATUS_FAILED);

    Queue::assertNothingPushed();
});

it('reports without changing anything on a dry run', function (): void {
    $meeting = staleMeeting($this->tenant->id);

    $this->artisan('avana:close-stale-meetings --dry-run')->assertSuccessful();

    expect($meeting->fresh()->status)->toBe(Meeting::STATUS_RECORDING);

    Queue::assertNothingPushed();
});

it('honours a shorter grace window', function (): void {
    $meeting = staleMeeting($this->tenant->id, ['started_at' => now()->subMinutes(20)]);

    $this->artisan('avana:close-stale-meetings --minutes=10')->assertSuccessful();

    expect($meeting->fresh()->status)->toBe(Meeting::STATUS_PROCESSING);
});

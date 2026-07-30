<?php

use App\Jobs\ProcessMeetingTranscriptJob;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingParticipant;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Queue;

/**
 * What the phone may change after a meeting: ticking follow-ups off, adding one
 * the summary missed, and asking for the summary to be built again.
 */
beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->owner = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->owner->tenant_id);
    $this->tenant->update(['ai_token_quota' => 1_000_000, 'ai_token_balance' => 0, 'ai_token_user_cap' => null]);

    $this->attendee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->attendeeEmployee = Employee::where('user_id', $this->attendee->id)->firstOrFail();

    $this->meeting = Meeting::create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->owner->id,
        'title' => 'Weekly Sync',
        'status' => Meeting::STATUS_READY,
        'started_at' => now(),
        'summary' => 'Ringkasan.',
    ]);

    MeetingParticipant::create([
        'meeting_id' => $this->meeting->id,
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->attendeeEmployee->id,
    ]);

    $this->item = MeetingActionItem::create([
        'meeting_id' => $this->meeting->id,
        'tenant_id' => $this->tenant->id,
        'text' => 'Kirim notulen',
        'status' => MeetingActionItem::STATUS_OPEN,
        'source' => MeetingActionItem::SOURCE_AI,
        'sort_order' => 0,
    ]);

    $this->login = function (string $email) {
        $token = $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])->json('access_token');
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };
});

it('lets somebody who attended tick a follow-up off', function (): void {
    ($this->login)($this->attendee->email)
        ->putJson("/api/v1/me/meetings/{$this->meeting->id}/action-items/{$this->item->id}", [
            'status' => MeetingActionItem::STATUS_DONE,
        ])
        ->assertOk()
        ->assertJsonPath('data.action_items.0.status', MeetingActionItem::STATUS_DONE);

    expect($this->item->fresh()->status)->toBe(MeetingActionItem::STATUS_DONE);
});

it('lets an attendee add a follow-up the summary missed', function (): void {
    ($this->login)($this->attendee->email)
        ->postJson("/api/v1/me/meetings/{$this->meeting->id}/action-items", [
            'text' => 'Booking ruang untuk pekan depan',
        ])
        ->assertCreated();

    $added = MeetingActionItem::where('meeting_id', $this->meeting->id)
        ->where('source', MeetingActionItem::SOURCE_MANUAL)
        ->firstOrFail();

    expect($added->text)->toBe('Booking ruang untuk pekan depan')
        ->and($added->status)->toBe(MeetingActionItem::STATUS_OPEN);
});

it('refuses a follow-up change from somebody who was not in the meeting', function (): void {
    $outsider = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $outsider->forceFill(['password' => bcrypt('password')])->save();

    ($this->login)($outsider->email)
        ->putJson("/api/v1/me/meetings/{$this->meeting->id}/action-items/{$this->item->id}", [
            'status' => MeetingActionItem::STATUS_DONE,
        ])
        ->assertNotFound();

    expect($this->item->fresh()->status)->toBe(MeetingActionItem::STATUS_OPEN);
});

it('refuses a follow-up that belongs to another meeting', function (): void {
    $other = Meeting::create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->owner->id,
        'title' => 'Rapat Lain',
        'status' => Meeting::STATUS_READY,
        'started_at' => now(),
    ]);

    ($this->login)($this->owner->email)
        ->putJson("/api/v1/me/meetings/{$other->id}/action-items/{$this->item->id}", [
            'status' => MeetingActionItem::STATUS_DONE,
        ])
        ->assertNotFound();
});

it('queues the summary again when the recorder asks for it', function (): void {
    Queue::fake();
    $this->meeting->update(['status' => Meeting::STATUS_FAILED, 'failure_reason' => 'gagal']);

    ($this->login)($this->owner->email)
        ->postJson("/api/v1/me/meetings/{$this->meeting->id}/reprocess")
        ->assertOk()
        ->assertJsonPath('data.status', Meeting::STATUS_PROCESSING);

    expect($this->meeting->fresh()->failure_reason)->toBeNull();

    Queue::assertPushed(ProcessMeetingTranscriptJob::class);
});

it('refuses to reprocess for somebody who did not record it', function (): void {
    Queue::fake();

    ($this->login)($this->attendee->email)
        ->postJson("/api/v1/me/meetings/{$this->meeting->id}/reprocess")
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('refuses to reprocess when the tokens have run out', function (): void {
    Queue::fake();
    $this->tenant->update(['ai_token_quota' => 0, 'ai_token_balance' => 0]);

    ($this->login)($this->owner->email)
        ->postJson("/api/v1/me/meetings/{$this->meeting->id}/reprocess")
        ->assertStatus(422)
        ->assertJsonPath('reason', 'pool_empty');

    // The refusal is seen by the person, not swallowed on a worker.
    Queue::assertNothingPushed();
    expect($this->meeting->fresh()->status)->toBe(Meeting::STATUS_READY);
});

it('offers the reprocess button only to whoever recorded it', function (): void {
    ($this->login)($this->owner->email)
        ->getJson("/api/v1/me/meetings/{$this->meeting->id}")
        ->assertOk()
        ->assertJsonPath('data.can_reprocess', true);

    // An attendee reads the same meeting but is not offered a button that
    // would spend somebody else's tokens — and the endpoint refuses them too.
    ($this->login)($this->attendee->email)
        ->getJson("/api/v1/me/meetings/{$this->meeting->id}")
        ->assertOk()
        ->assertJsonPath('data.can_reprocess', false);
});

it('refuses to reprocess a meeting that is still recording', function (): void {
    Queue::fake();
    $this->meeting->update(['status' => Meeting::STATUS_RECORDING]);

    ($this->login)($this->owner->email)
        ->postJson("/api/v1/me/meetings/{$this->meeting->id}/reprocess")
        ->assertStatus(422);

    Queue::assertNothingPushed();
});

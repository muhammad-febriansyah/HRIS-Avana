<?php

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingInsight;
use App\Models\MeetingParticipant;
use App\Models\MeetingSegment;
use App\Models\MeetingSpeaker;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::where('tenant_id', $this->tenant->id)->firstOrFail();

    // A fully populated meeting: every relation the pages read is present, so
    // the render exercises the date casts and payload shapes rather than
    // skipping past empty collections.
    $this->meeting = Meeting::create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->admin->id,
        'title' => 'Weekly Sync - Product Design',
        'location' => 'Jakarta Office Room 302',
        'status' => Meeting::STATUS_READY,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'duration_ms' => 765_000,
        'summary' => 'Rapat membahas integrasi payroll.',
        'decisions' => ['Integrasi jalan bulan depan', 'Vendor dikunci pekan ini'],
        'summary_model' => 'gpt-4o-mini',
        'summary_tokens' => 410,
    ]);

    MeetingParticipant::create([
        'meeting_id' => $this->meeting->id,
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
    ]);

    MeetingSegment::create([
        'meeting_id' => $this->meeting->id, 'tenant_id' => $this->tenant->id,
        'speaker_index' => 0, 'start_ms' => 65_000, 'end_ms' => 69_000,
        'text' => 'Memastikan integrasi payroll bulan depan berjalan sesuai jadwal.',
    ]);

    MeetingSpeaker::create([
        'meeting_id' => $this->meeting->id, 'tenant_id' => $this->tenant->id,
        'speaker_index' => 0, 'employee_id' => $this->employee->id,
        'display_name' => $this->employee->full_name, 'guessed_by_ai' => true, 'confidence' => 0.87,
    ]);

    // Carries a real due_date: the shaping code calls ->toDateString() on it.
    MeetingActionItem::create([
        'meeting_id' => $this->meeting->id, 'tenant_id' => $this->tenant->id,
        'text' => 'Siapkan dokumen integrasi', 'assignee_employee_id' => $this->employee->id,
        'due_date' => '2026-08-15', 'status' => MeetingActionItem::STATUS_OPEN,
        'source' => MeetingActionItem::SOURCE_AI, 'sort_order' => 0,
    ]);

    MeetingInsight::create([
        'meeting_id' => $this->meeting->id, 'tenant_id' => $this->tenant->id,
        'type' => MeetingInsight::TYPE_EXECUTIVE_SUMMARY,
        'payload' => ['headline' => 'Integrasi payroll disepakati', 'paragraphs' => ['Isi.'], 'key_points' => ['Poin.']],
        'model' => 'gpt-5.5', 'tokens' => 400, 'generated_by' => $this->admin->id, 'generated_at' => now(),
    ]);
});

it('renders the meeting list with its counters', function (): void {
    actingAs($this->admin)
        ->get(route('avana.rapat'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/rapat/index')
            ->has('meetings', 1)
            ->where('meetings.0.title', 'Weekly Sync - Product Design')
            ->where('meetings.0.duration_minutes', 13)
            ->where('meetings.0.action_item_count', 1)
            ->where('meetings.0.has_summary', true)
            ->where('kpis.ready', 1)
            ->where('kpis.tokens', 410)
            ->has('recorderReady'));
});

it('renders the meeting detail with transcript, speakers, follow-ups and insights', function (): void {
    actingAs($this->admin)
        ->get(route('avana.rapat.show', $this->meeting->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/rapat/detail')
            ->where('meeting.summary_tokens', 410)
            ->where('meeting.has_audio', false)
            ->has('meeting.decisions', 2)
            ->where('meeting.decisions.0', 'Integrasi jalan bulan depan')
            // The date casts phpstan could not see through, exercised for real.
            ->where('meeting.started_at', $this->meeting->started_at->toIso8601String())
            ->has('transcript', 1)
            ->where('transcript.0.timecode', '01:05')
            ->where('transcript.0.speaker', $this->employee->full_name)
            ->has('speakers', 1)
            ->where('speakers.0.guessed_by_ai', true)
            ->where('speakers.0.lines', 1)
            ->has('actionItems', 1)
            ->where('actionItems.0.due_date', '2026-08-15')
            ->where('actionItems.0.assignee', $this->employee->full_name)
            // Every analysis is listed; only the generated one carries a payload.
            ->has('insights', 5)
            ->where('insights.0.type', MeetingInsight::TYPE_EXECUTIVE_SUMMARY)
            ->where('insights.0.payload.headline', 'Integrasi payroll disepakati')
            ->where('insights.1.payload', null)
            ->has('employees')
            ->where('can.update', true)
            ->has('proModel'));
});

it('returns the same meeting over the mobile API, transcript included', function (): void {
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'rina.a@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/v1/me/meetings/{$this->meeting->id}")
        ->assertOk()
        ->assertJsonPath('data.duration_minutes', 13)
        ->assertJsonPath('data.transcript.0.speaker', $this->employee->full_name)
        ->assertJsonPath('data.action_items.0.due_date', '2026-08-15')
        // The phone gets the decisions as a list, not as prose to re-parse.
        ->assertJsonPath('data.decisions', [
            'Integrasi jalan bulan depan',
            'Vendor dikunci pekan ini',
        ])
        // ...and the analyses somebody already paid for on the web.
        ->assertJsonPath('data.insights.0.type', MeetingInsight::TYPE_EXECUTIVE_SUMMARY)
        ->assertJsonPath('data.insights.0.label', 'Executive Summary')
        ->assertJsonPath(
            'data.insights.0.payload.headline',
            'Integrasi payroll disepakati',
        );
});

it('sends the phone no analyses when none have been generated yet', function (): void {
    $this->meeting->insights()->delete();

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'rina.a@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->app['auth']->forgetGuards();

    // An empty list, not five placeholder headings promising work nobody
    // asked for and nobody has paid for.
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/v1/me/meetings/{$this->meeting->id}")
        ->assertOk()
        ->assertJsonPath('data.insights', []);
});

it('confirms a speaker name and enrols them as a participant', function (): void {
    $other = Employee::where('tenant_id', $this->tenant->id)->where('id', '!=', $this->employee->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.rapat.pembicara', $this->meeting->id), [
            'speakers' => [
                ['speaker_index' => 0, 'employee_id' => $other->id, 'display_name' => null],
            ],
        ])
        ->assertSessionHasNoErrors();

    $speaker = MeetingSpeaker::where('meeting_id', $this->meeting->id)->where('speaker_index', 0)->firstOrFail();

    expect($speaker->employee_id)->toBe($other->id)
        ->and($speaker->guessed_by_ai)->toBeFalse()
        ->and(MeetingParticipant::where('meeting_id', $this->meeting->id)->where('employee_id', $other->id)->exists())->toBeTrue();
});

it('adds, completes and removes a follow-up', function (): void {
    actingAs($this->admin)
        ->post(route('avana.rapat.tindak-lanjut.store', $this->meeting->id), [
            'text' => 'Kirim notulen ke direksi',
            'assignee_employee_id' => $this->employee->id,
            'due_date' => '2026-08-20',
        ])
        ->assertSessionHasNoErrors();

    $item = MeetingActionItem::where('meeting_id', $this->meeting->id)
        ->where('source', MeetingActionItem::SOURCE_MANUAL)
        ->firstOrFail();

    expect($item->due_date->toDateString())->toBe('2026-08-20');

    actingAs($this->admin)
        ->put(route('avana.rapat.tindak-lanjut.update', ['meeting' => $this->meeting->id, 'actionItem' => $item->id]), [
            'status' => MeetingActionItem::STATUS_DONE,
        ])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->status)->toBe(MeetingActionItem::STATUS_DONE);

    actingAs($this->admin)
        ->delete(route('avana.rapat.tindak-lanjut.destroy', ['meeting' => $this->meeting->id, 'actionItem' => $item->id]))
        ->assertSessionHasNoErrors();

    expect(MeetingActionItem::find($item->id))->toBeNull();
});

it('opens a meeting to the whole company when its access is widened', function (): void {
    actingAs($this->admin)
        ->put(route('avana.rapat.akses', $this->meeting->id), ['visibility' => Meeting::VISIBILITY_TENANT])
        ->assertSessionHasNoErrors();

    expect($this->meeting->fresh()->visibility)->toBe(Meeting::VISIBILITY_TENANT);
});

it('forbids the meeting screens for a role without the module', function (): void {
    $outsider = User::factory()->create(['tenant_id' => $this->tenant->id]);

    actingAs($outsider)->get(route('avana.rapat'))->assertForbidden();
    actingAs($outsider)->get(route('avana.rapat.show', $this->meeting->id))->assertForbidden();
});

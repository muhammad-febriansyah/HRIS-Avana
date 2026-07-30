<?php

use App\Models\AiSetting;
use App\Models\Employee;
use App\Models\Feature;
use App\Models\Meeting;
use App\Models\MeetingChunk;
use App\Models\MeetingParticipant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AiToolkit;
use App\Services\MeetingSearch;
use Database\Seeders\AvanaDemoSeeder;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\EmbeddingsResponseFake;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Embedding;

/**
 * @return array<int, string>
 */
function toolNamesForMeetings(User $user): array
{
    return array_map(fn (Tool $tool): string => $tool->name(), AiToolkit::forUser($user));
}

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    AiSetting::current()->update([
        'provider' => 'openai',
        'api_key' => 'sk-test',
        'model' => 'gpt-4o-mini',
        'is_enabled' => true,
        'embedding_model' => 'text-embedding-3-small',
    ]);
});

it('does not register meeting tools when the tenant has no meeting_ai feature', function (): void {
    Feature::where('code', 'meeting_ai')->get()->each(
        fn (Feature $feature) => $this->tenant->features()->where('feature_id', $feature->id)->update(['is_enabled' => false]),
    );

    expect(toolNamesForMeetings($this->admin->fresh()))->not->toContain('daftar_rapat', 'cari_transkrip_rapat');
});

it('registers meeting tools once the feature is enabled', function (): void {
    expect(toolNamesForMeetings($this->admin->fresh()))->toContain('daftar_rapat', 'cari_transkrip_rapat');
});

it('lets a participant read a meeting they attended but not one they did not', function (): void {
    $employee = Employee::where('user_id', $this->employeeUser->id)->firstOrFail();

    $attended = Meeting::create([
        'tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id,
        'title' => 'Rapat Yang Dihadiri', 'status' => Meeting::STATUS_READY, 'started_at' => now(),
    ]);
    MeetingParticipant::create(['meeting_id' => $attended->id, 'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id]);

    $notAttended = Meeting::create([
        'tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id,
        'title' => 'Rapat Tertutup', 'status' => Meeting::STATUS_READY, 'started_at' => now(),
        'visibility' => Meeting::VISIBILITY_PARTICIPANTS,
    ]);

    $readable = Meeting::query()
        ->forTenant($this->tenant->id)
        ->readableBy($employee->id, $this->employeeUser->id, false)
        ->pluck('id');

    expect($readable)->toContain($attended->id)
        ->and($readable)->not->toContain($notAttended->id);
});

it('opens a tenant-wide meeting to every employee regardless of attendance', function (): void {
    $employee = Employee::where('user_id', $this->employeeUser->id)->firstOrFail();

    $open = Meeting::create([
        'tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id,
        'title' => 'Town Hall', 'status' => Meeting::STATUS_READY, 'started_at' => now(),
        'visibility' => Meeting::VISIBILITY_TENANT,
    ]);

    $readable = Meeting::query()
        ->forTenant($this->tenant->id)
        ->readableBy($employee->id, $this->employeeUser->id, false)
        ->pluck('id');

    expect($readable)->toContain($open->id);
});

it('lets meeting.view see every meeting regardless of attendance', function (): void {
    $meeting = Meeting::create([
        'tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id,
        'title' => 'Rapat Tertutup', 'status' => Meeting::STATUS_READY, 'started_at' => now(),
    ]);

    $readable = Meeting::query()
        ->forTenant($this->tenant->id)
        ->readableBy(null, 999_999, true)
        ->pluck('id');

    expect($readable)->toContain($meeting->id);
});

it('only returns chunks from meetings the caller may read', function (): void {
    $employee = Employee::where('user_id', $this->employeeUser->id)->firstOrFail();

    $readableMeeting = Meeting::create([
        'tenant_id' => $this->tenant->id, 'created_by' => $this->employeeUser->id,
        'title' => 'Rapat Saya', 'status' => Meeting::STATUS_READY, 'started_at' => now(),
    ]);
    MeetingChunk::create([
        'meeting_id' => $readableMeeting->id, 'tenant_id' => $this->tenant->id, 'ordinal' => 0,
        'text' => 'Kita putuskan anggaran marketing naik 20%.', 'embedding' => [1, 0, 0], 'embedding_model' => 'text-embedding-3-small',
    ]);

    $hiddenMeeting = Meeting::create([
        'tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id,
        'title' => 'Rapat Direksi Tertutup', 'status' => Meeting::STATUS_READY, 'started_at' => now(),
    ]);
    MeetingChunk::create([
        'meeting_id' => $hiddenMeeting->id, 'tenant_id' => $this->tenant->id, 'ordinal' => 0,
        'text' => 'Rencana akuisisi rahasia perusahaan X.', 'embedding' => [1, 0, 0], 'embedding_model' => 'text-embedding-3-small',
    ]);

    Prism::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([Embedding::fromArray([1, 0, 0])]),
    ]);

    $hits = app(MeetingSearch::class)->search($this->employeeUser->fresh(), 'anggaran marketing');

    expect($hits)->toHaveCount(1)
        ->and($hits->first()['meeting']->id)->toBe($readableMeeting->id);
});

it('returns nothing when no chunk is close enough to the question', function (): void {
    $meeting = Meeting::create([
        'tenant_id' => $this->tenant->id, 'created_by' => $this->employeeUser->id,
        'title' => 'Rapat Saya', 'status' => Meeting::STATUS_READY, 'started_at' => now(),
    ]);
    MeetingChunk::create([
        'meeting_id' => $meeting->id, 'tenant_id' => $this->tenant->id, 'ordinal' => 0,
        'text' => 'Diskusi soal desain logo baru.', 'embedding' => [1, 0, 0], 'embedding_model' => 'text-embedding-3-small',
    ]);

    Prism::fake([
        // Orthogonal vector — cosine similarity 0, below the 0.2 threshold.
        EmbeddingsResponseFake::make()->withEmbeddings([Embedding::fromArray([0, 1, 0])]),
    ]);

    $hits = app(MeetingSearch::class)->search($this->employeeUser->fresh(), 'siapa presiden pertama Indonesia');

    expect($hits)->toBeEmpty();
});

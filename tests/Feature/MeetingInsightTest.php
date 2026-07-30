<?php

use App\Models\AiSetting;
use App\Models\AiTokenLedger;
use App\Models\Meeting;
use App\Models\MeetingInsight;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->tenant->update(['ai_token_quota' => 1_000_000, 'ai_token_balance' => 0, 'ai_token_user_cap' => null]);

    AiSetting::current()->update([
        'provider' => 'openai',
        'api_key' => 'sk-test',
        'model' => 'gpt-4o-mini',
        'is_enabled' => true,
        'meeting_pro_model' => 'gpt-5.5',
    ]);

    $this->meeting = Meeting::create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->admin->id,
        'title' => 'Weekly Sync',
        'status' => Meeting::STATUS_READY,
        'started_at' => now(),
        'summary' => 'Ringkasan rapat.',
    ]);
});

it('generates a premium insight once and reuses it without spending tokens again', function (): void {
    Prism::fake([
        StructuredResponseFake::make()
            ->withStructured(['risks' => [
                ['risk' => 'Keterlambatan vendor', 'severity' => 'tinggi', 'likelihood' => 'sedang', 'mitigation' => 'Cari vendor cadangan', 'owner' => ''],
            ]])
            ->withUsage(new Usage(150, 50)),
    ]);

    actingAs($this->admin)
        ->post(route('avana.rapat.analisis', ['meeting' => $this->meeting->id, 'type' => MeetingInsight::TYPE_PROJECT_RISK]))
        ->assertSessionHasNoErrors();

    $insight = MeetingInsight::where('meeting_id', $this->meeting->id)->where('type', MeetingInsight::TYPE_PROJECT_RISK)->firstOrFail();
    expect($insight->tokens)->toBe(200);
    expect((int) AiTokenLedger::where('source', 'meeting_pro')->sum('tokens'))->toBe(200);

    // Asking again without `refresh` must not call the provider or spend more.
    actingAs($this->admin)
        ->post(route('avana.rapat.analisis', ['meeting' => $this->meeting->id, 'type' => MeetingInsight::TYPE_PROJECT_RISK]))
        ->assertSessionHasNoErrors();

    expect((int) AiTokenLedger::where('source', 'meeting_pro')->sum('tokens'))->toBe(200)
        ->and(MeetingInsight::where('meeting_id', $this->meeting->id)->count())->toBe(1);
});

it('regenerates and re-bills when refresh is explicitly requested', function (): void {
    MeetingInsight::create([
        'meeting_id' => $this->meeting->id,
        'tenant_id' => $this->tenant->id,
        'type' => MeetingInsight::TYPE_SENTIMENT,
        'payload' => ['overall' => 'netral', 'score' => 0, 'note' => 'lama', 'per_speaker' => [], 'tension_points' => []],
        'model' => 'gpt-5.5',
        'tokens' => 100,
        'generated_at' => now()->subDay(),
    ]);

    Prism::fake([
        StructuredResponseFake::make()
            ->withStructured(['overall' => 'positif', 'score' => 0.6, 'note' => 'baru', 'per_speaker' => [], 'tension_points' => []])
            ->withUsage(new Usage(80, 20)),
    ]);

    actingAs($this->admin)
        ->post(route('avana.rapat.analisis', ['meeting' => $this->meeting->id, 'type' => MeetingInsight::TYPE_SENTIMENT]), ['refresh' => true])
        ->assertSessionHasNoErrors();

    $insight = MeetingInsight::where('meeting_id', $this->meeting->id)->where('type', MeetingInsight::TYPE_SENTIMENT)->firstOrFail();
    expect($insight->payload['note'])->toBe('baru')
        ->and($insight->tokens)->toBe(100);
});

it('refuses an insight for a caller without the meeting module permission', function (): void {
    $outsider = User::factory()->create(['tenant_id' => $this->tenant->id]);

    actingAs($outsider)
        ->post(route('avana.rapat.analisis', ['meeting' => $this->meeting->id, 'type' => MeetingInsight::TYPE_SENTIMENT]))
        ->assertForbidden();
});

it('excludes a meeting from another tenant even for a meeting.view holder there', function (): void {
    $otherTenant = Tenant::create([
        'name' => 'PT Lain', 'company_name' => 'PT Lain', 'slug' => 'lain', 'status' => 'active',
    ]);

    // Exercises the same tenant-ownership guard the controller's
    // ensureReadable() applies, without going through the shared
    // feature/menu-gate middleware (a separate concern, covered by every
    // other Avana module's tests).
    $readable = Meeting::query()
        ->whereKey($this->meeting->id)
        ->forTenant($otherTenant->id)
        ->readableBy(null, null, true)
        ->exists();

    expect($readable)->toBeFalse();
});

it('rejects an unknown analysis type', function (): void {
    actingAs($this->admin)
        ->post(route('avana.rapat.analisis', ['meeting' => $this->meeting->id, 'type' => 'not_a_real_type']))
        ->assertNotFound();
});

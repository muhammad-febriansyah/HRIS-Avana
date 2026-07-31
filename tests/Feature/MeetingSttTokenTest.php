<?php

use App\Models\AiSetting;
use App\Models\Meeting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Http;

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
        'stt_api_key' => 'dg-project-key-SECRET',
        'stt_model' => 'nova-2',
        'stt_language' => 'id',
        'stt_token_cost_per_minute' => 500,
    ]);

    $this->meeting = Meeting::create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'title' => 'Weekly Sync',
        'status' => Meeting::STATUS_RECORDING,
        'started_at' => now(),
    ]);

    $this->login = function (string $email) {
        $token = $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])->json('access_token');
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };
});

it('hands the phone a short-lived grant and never the project key', function (): void {
    Http::fake([
        'api.deepgram.com/v1/auth/grant' => Http::response(['access_token' => 'grant-abc', 'expires_in' => 60]),
    ]);

    $response = ($this->login)('rina.a@nusantara.co.id')
        ->getJson("/api/v1/me/meetings/{$this->meeting->id}/stt-token")
        ->assertOk()
        ->assertJsonPath('data.access_token', 'grant-abc')
        ->assertJsonPath('data.params.diarize', 'true')
        ->assertJsonPath('data.params.model', 'nova-2')
        ->assertJsonPath('data.params.language', 'id');

    expect($response->getContent())->not->toContain('dg-project-key-SECRET');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Token dg-project-key-SECRET'));
});

it('refuses a grant for a meeting recorded by somebody else', function (): void {
    Http::fake();

    ($this->login)('bagus.p@nusantara.co.id')
        ->getJson("/api/v1/me/meetings/{$this->meeting->id}/stt-token")
        ->assertForbidden();

    // Nothing is asked of the speech provider once ownership fails. (The
    // branded 403 page itself round-trips through the local Inertia SSR
    // server, which also goes through Http::fake() — irrelevant here, so the
    // assertion is scoped to Deepgram's own host.)
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'deepgram.com'));
});

it('refuses a grant when the company has no tokens left', function (): void {
    Http::fake();
    $this->tenant->update(['ai_token_quota' => 0, 'ai_token_balance' => 0]);

    ($this->login)('rina.a@nusantara.co.id')
        ->getJson("/api/v1/me/meetings/{$this->meeting->id}/stt-token")
        ->assertStatus(422);

    // Nothing is asked of the provider once the wallet is empty.
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'deepgram.com'));
});

it('refuses a grant once the recording has stopped', function (): void {
    Http::fake();
    $this->meeting->update(['status' => Meeting::STATUS_PROCESSING]);

    ($this->login)('rina.a@nusantara.co.id')
        ->getJson("/api/v1/me/meetings/{$this->meeting->id}/stt-token")
        ->assertStatus(422);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'deepgram.com'));
});

it('hides the provider error behind a message the tenant can act on', function (): void {
    Http::fake([
        'api.deepgram.com/v1/auth/grant' => Http::response(['err_msg' => 'Project has exceeded its quota'], 402),
    ]);

    $response = ($this->login)('rina.a@nusantara.co.id')
        ->getJson("/api/v1/me/meetings/{$this->meeting->id}/stt-token")
        ->assertStatus(422);

    expect($response->json('message'))->not->toContain('quota')
        ->and($response->json('message'))->toContain('Coba lagi');
});

it('tells the tenant to fix the key when the provider refuses it', function (): void {
    // Deepgram wants Member scope or higher to mint a grant. A transcription-only
    // key passes every /v1/listen call and is refused here, so "coba lagi nanti"
    // sent people retrying something that could not start without a new key.
    Http::fake([
        'api.deepgram.com/v1/auth/grant' => Http::response(
            ['err_code' => 'FORBIDDEN', 'err_msg' => 'Insufficient permissions.'],
            403,
        ),
    ]);

    $response = ($this->login)('rina.a@nusantara.co.id')
        ->getJson("/api/v1/me/meetings/{$this->meeting->id}/stt-token")
        ->assertStatus(422);

    expect($response->json('message'))->toContain('Super Admin')
        ->and($response->json('message'))->not->toContain('Coba lagi')
        ->and($response->json('message'))->not->toContain('permissions');
});

it('blocks recording before the microphone opens when the wallet is empty', function (): void {
    $this->tenant->update(['ai_token_quota' => 0, 'ai_token_balance' => 0]);

    ($this->login)('rina.a@nusantara.co.id')
        ->postJson('/api/v1/me/meetings', ['title' => 'Rapat Baru'])
        ->assertStatus(422)
        ->assertJsonPath('reason', 'pool_empty');

    expect(Meeting::where('title', 'Rapat Baru')->exists())->toBeFalse();
});

it('falls back to the deployment DEEPGRAM_API_KEY when none is saved in the UI', function (): void {
    config(['services.deepgram.api_key' => 'dg-from-env']);
    AiSetting::current()->update(['stt_api_key' => null]);

    Http::fake([
        'api.deepgram.com/v1/auth/grant' => Http::response(['access_token' => 'grant-env', 'expires_in' => 60]),
    ]);

    ($this->login)('rina.a@nusantara.co.id')
        ->getJson("/api/v1/me/meetings/{$this->meeting->id}/stt-token")
        ->assertOk()
        ->assertJsonPath('data.access_token', 'grant-env');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Token dg-from-env'));
});

it('prefers the key saved in Pengaturan AI over the one in the environment', function (): void {
    config(['services.deepgram.api_key' => 'dg-from-env']);

    Http::fake([
        'api.deepgram.com/v1/auth/grant' => Http::response(['access_token' => 'grant-db', 'expires_in' => 60]),
    ]);

    ($this->login)('rina.a@nusantara.co.id')
        ->getJson("/api/v1/me/meetings/{$this->meeting->id}/stt-token")
        ->assertOk();

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Token dg-project-key-SECRET'));
});

it('stays unavailable when neither the UI nor the environment has a key', function (): void {
    config(['services.deepgram.api_key' => null]);
    AiSetting::current()->update(['stt_api_key' => null]);

    ($this->login)('rina.a@nusantara.co.id')
        ->getJson('/api/v1/me/meetings/status')
        ->assertOk()
        ->assertJsonPath('data.available', false);
});

it('reports the recorder as unavailable until a super admin configures it', function (): void {
    AiSetting::current()->update(['stt_enabled' => false]);

    ($this->login)('rina.a@nusantara.co.id')
        ->getJson('/api/v1/me/meetings/status')
        ->assertOk()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.can_record', false);
});

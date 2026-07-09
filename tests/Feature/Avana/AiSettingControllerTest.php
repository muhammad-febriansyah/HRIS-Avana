<?php

use App\Models\AiSetting;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('renders the AI settings editor for a super admin', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.ai-settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/ai-settings/index', false)
            ->has('settings.provider')
            ->has('settings.has_key')
            ->has('providers.openai')
            ->has('providers.anthropic')
            ->has('suggestedModels'));
});

it('forbids a non super admin from viewing or updating AI settings', function (): void {
    actingAs($this->admin)
        ->get(route('avana.ai-settings'))
        ->assertForbidden();

    actingAs($this->admin)
        ->post(route('avana.ai-settings.update'), ['provider' => 'openai'])
        ->assertForbidden();
});

it('saves the provider, model and encrypted key for a super admin', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.ai-settings.update'), [
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-5',
            'api_key' => 'sk-secret-123',
            'is_enabled' => true,
        ])
        ->assertSessionHas('success');

    $settings = AiSetting::current();

    expect($settings->provider)->toBe('anthropic');
    expect($settings->model)->toBe('claude-sonnet-4-5');
    expect($settings->api_key)->toBe('sk-secret-123'); // decrypted via cast

    // Stored ciphertext must not equal the plaintext key.
    $raw = DB::table('ai_settings')->where('id', $settings->id)->value('api_key');
    expect($raw)->not->toBe('sk-secret-123');
});

it('keeps the existing key when api_key is submitted blank', function (): void {
    AiSetting::current()->update(['api_key' => 'sk-original']);

    actingAs($this->superAdmin)
        ->post(route('avana.ai-settings.update'), [
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'api_key' => '',
        ])
        ->assertSessionHas('success');

    expect(AiSetting::current()->api_key)->toBe('sk-original');
});

it('rejects an unsupported provider', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.ai-settings.update'), [
            'provider' => 'skynet',
        ])
        ->assertSessionHasErrors('provider');
});

it('reports readiness based on the configured key', function (): void {
    $settings = AiSetting::current();
    $settings->update(['provider' => 'openai', 'api_key' => null, 'is_enabled' => true]);
    config(['prism.providers.openai.api_key' => '']);
    expect($settings->fresh()->isReady())->toBeFalse();

    $settings->update(['api_key' => 'sk-live']);
    expect($settings->fresh()->isReady())->toBeTrue();

    // Ollama is ready without a key.
    $settings->update(['provider' => 'ollama', 'api_key' => null]);
    expect($settings->fresh()->isReady())->toBeTrue();
});

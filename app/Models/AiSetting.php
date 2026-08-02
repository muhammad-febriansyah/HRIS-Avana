<?php

namespace App\Models;

use App\Services\MeetingTranscriber;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide (super admin) AI provider configuration: which provider to use,
 * its API key and the model. Stored as a single row and accessed via
 * {@see self::current()}. The API key is encrypted at rest.
 */
final class AiSetting extends Model
{
    protected $guarded = [];

    /**
     * Supported AI providers (Prism driver key => display label).
     *
     * @var array<string, string>
     */
    public const PROVIDERS = [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic (Claude)',
        'gemini' => 'Google Gemini',
        'mistral' => 'Mistral',
        'groq' => 'Groq',
        'xai' => 'xAI (Grok)',
        'ollama' => 'Ollama (lokal)',
    ];

    /**
     * Suggested model IDs per provider (free-text; the user may enter any).
     *
     * @var array<string, list<string>>
     */
    public const SUGGESTED_MODELS = [
        'openai' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini'],
        'anthropic' => ['claude-sonnet-4-5', 'claude-haiku-4-5', 'claude-opus-4-1'],
        'gemini' => ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash'],
        'mistral' => ['mistral-large-latest', 'mistral-small-latest'],
        'groq' => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant'],
        'xai' => ['grok-2', 'grok-2-mini'],
        'ollama' => ['llama3.2', 'qwen2.5'],
    ];

    /**
     * Providers that can generate images. Prism ships an image handler for
     * these two only; picking any other provider leaves the assistant
     * text-only no matter what is configured here.
     *
     * @var array<string, list<string>>
     */
    public const IMAGE_MODELS = [
        'openai' => ['gpt-image-1', 'dall-e-3'],
        'gemini' => ['imagen-3.0-generate-002', 'gemini-2.0-flash-preview-image-generation'],
    ];

    /**
     * Default model used when a provider has no explicit model configured.
     */
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    /**
     * Speech-to-text model per provider, first entry used when none is chosen.
     * Deepgram is the only provider wired: it diarizes (tells speakers apart)
     * on the same request, which is the part a meeting transcript lives or dies
     * on, and it bills per second of audio rather than per token.
     *
     * @var array<string, list<string>>
     */
    public const STT_MODELS = [
        'deepgram' => ['nova-2', 'nova-3', 'nova-2-meeting'],
    ];

    /**
     * Default speech model and language. Indonesian is what these meetings are
     * held in, and nova-2 is the model that supports it.
     */
    private const DEFAULT_STT_MODEL = 'nova-2';

    private const DEFAULT_STT_LANGUAGE = 'id';

    /**
     * Default embedding model for transcript recall. Small on purpose: recall
     * quality barely moves with a larger one, and it is called once per chunk.
     */
    private const DEFAULT_EMBEDDING_MODEL = 'text-embedding-3-small';

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
            'image_enabled' => 'boolean',
            'image_token_cost' => 'integer',
            'stt_enabled' => 'boolean',
            'stt_api_key' => 'encrypted',
            'stt_token_cost_per_minute' => 'integer',
            'meeting_max_minutes' => 'integer',
            'meeting_audio_keep' => 'boolean',
        ];
    }

    /**
     * The singleton settings row, created on first access.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }

    /**
     * Resolve the provider, model and API key to use for AI calls. The stored
     * key/model take precedence; otherwise fall back to the Prism config (env)
     * for the selected provider so existing deployments keep working.
     *
     * @return array{provider: string, model: string, api_key: string, enabled: bool}
     */
    public function resolved(): array
    {
        $provider = $this->provider ?: 'openai';
        $apiKey = (string) ($this->api_key ?: config("prism.providers.{$provider}.api_key", ''));
        $model = (string) ($this->model ?: env('AI_MODEL', self::DEFAULT_MODEL));

        return [
            'provider' => $provider,
            'model' => $model,
            'api_key' => $apiKey,
            'enabled' => (bool) $this->is_enabled,
        ];
    }

    /**
     * Image generation settings, or null when the assistant cannot draw:
     * switched off, chat itself not ready, or a provider Prism has no image
     * handler for.
     *
     * @return array{provider: string, model: string, token_cost: int}|null
     */
    public function resolvedImage(): ?array
    {
        if (! $this->image_enabled || ! $this->isReady()) {
            return null;
        }

        $provider = $this->resolved()['provider'];

        if (! array_key_exists($provider, self::IMAGE_MODELS)) {
            return null;
        }

        return [
            'provider' => $provider,
            'model' => (string) ($this->image_model ?: self::IMAGE_MODELS[$provider][0]),
            'token_cost' => max(0, (int) $this->image_token_cost),
        ];
    }

    /**
     * Whether a usable API key is configured (stored or via env fallback).
     * Ollama runs locally and needs no key.
     */
    public function isReady(): bool
    {
        $resolved = $this->resolved();

        return $resolved['enabled']
            && ($resolved['provider'] === 'ollama' || $resolved['api_key'] !== '');
    }

    /**
     * Speech-to-text settings, or null when meeting recording is not usable:
     * switched off, or no project key anywhere.
     *
     * The key stored in Pengaturan AI wins; otherwise it falls back to the
     * deployment's own `DEEPGRAM_API_KEY`, the same way {@see resolved()} falls
     * back for chat — so a server can be provisioned from `.env` without anyone
     * pasting a credential into a form.
     *
     * Either way it is the project key, which is never handed to a phone — see
     * {@see MeetingTranscriber::grantToken()}.
     *
     * @return array{provider: string, model: string, language: string, api_key: string, token_cost_per_minute: int, max_minutes: int, keep_audio: bool}|null
     */
    public function resolvedStt(): ?array
    {
        $provider = (string) ($this->stt_provider ?: 'deepgram');

        $apiKey = (string) ($this->stt_api_key ?: config("services.{$provider}.api_key", ''));

        if (! $this->stt_enabled || $apiKey === '') {
            return null;
        }

        return [
            'provider' => $provider,
            'model' => (string) ($this->stt_model ?: (self::STT_MODELS[$provider][0] ?? self::DEFAULT_STT_MODEL)),
            'language' => (string) ($this->stt_language ?: self::DEFAULT_STT_LANGUAGE),
            'api_key' => $apiKey,
            'token_cost_per_minute' => max(0, (int) $this->stt_token_cost_per_minute),
            // Zero means no ceiling: the wallet is the only brake, which is
            // safe now that a recording reports in even while nobody speaks
            // and stops itself once a room has gone quiet.
            'max_minutes' => ($minutes = (int) $this->meeting_max_minutes) > 0 ? $minutes : null,
            'keep_audio' => (bool) $this->meeting_audio_keep,
        ];
    }

    /**
     * The model each meeting text job should use.
     *
     * The automatic summary rides the cheap chat model — it runs for every
     * recording, whether anyone reads it or not. `pro` is the costly reasoning
     * model, reached only by an analysis somebody clicked, and falls back to the
     * chat model when no separate one is configured.
     *
     * @return array{provider: string, summary: string, pro: string, embedding: string, api_key: string}
     */
    public function resolvedMeetingModels(): array
    {
        $chat = $this->resolved();

        return [
            'provider' => $chat['provider'],
            'summary' => $chat['model'],
            'pro' => (string) ($this->meeting_pro_model ?: $chat['model']),
            'embedding' => (string) ($this->embedding_model ?: self::DEFAULT_EMBEDDING_MODEL),
            'api_key' => $chat['api_key'],
        ];
    }

    /**
     * The last 4 characters of the stored key for a masked UI preview, or null.
     */
    public function keyPreview(): ?string
    {
        $key = (string) $this->api_key;

        return $key === '' ? null : str_repeat('•', 8).substr($key, -4);
    }

    /**
     * Masked preview of the speech provider's project key, or null.
     */
    public function sttKeyPreview(): ?string
    {
        $key = (string) $this->stt_api_key;

        return $key === '' ? null : str_repeat('•', 8).substr($key, -4);
    }
}

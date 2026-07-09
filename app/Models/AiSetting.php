<?php

namespace App\Models;

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
     * Default model used when a provider has no explicit model configured.
     */
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
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
     * The last 4 characters of the stored key for a masked UI preview, or null.
     */
    public function keyPreview(): ?string
    {
        $key = (string) $this->api_key;

        return $key === '' ? null : str_repeat('•', 8).substr($key, -4);
    }
}

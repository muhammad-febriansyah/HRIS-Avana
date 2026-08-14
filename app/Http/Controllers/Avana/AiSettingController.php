<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin screen to choose the AI provider, its API key and the model used
 * by the AI Assistant. A single settings row is edited in place; the API key is
 * never sent back to the browser in cleartext (only a masked preview).
 */
class AiSettingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the AI provider settings editor.
     */
    public function edit(Request $request): Response
    {
        $this->authorize('view', AiSetting::class);

        $settings = AiSetting::current();

        return Inertia::render('avana/ai-settings/index', [
            'settings' => [
                'provider' => $settings->provider ?: 'openai',
                'model' => $settings->model ?? '',
                'is_enabled' => (bool) $settings->is_enabled,
                'has_key' => $settings->hasStoredKey(),
                'key_preview' => $settings->keyPreview(),
                'is_ready' => $settings->isReady(),
                'image_enabled' => (bool) $settings->image_enabled,
                'image_model' => $settings->image_model ?? '',
                'image_token_cost' => (int) $settings->image_token_cost,
                'can_generate_images' => $settings->resolvedImage() !== null,
                'stt_enabled' => (bool) $settings->stt_enabled,
                'stt_provider' => $settings->stt_provider ?: 'deepgram',
                'stt_model' => $settings->stt_model ?? '',
                'stt_language' => $settings->stt_language ?? '',
                'has_stt_key' => $settings->hasStoredSttKey(),
                'stt_key_preview' => $settings->sttKeyPreview(),
                'stt_token_cost_per_minute' => (int) $settings->stt_token_cost_per_minute,
                'meeting_max_minutes' => (int) $settings->meeting_max_minutes,
                'meeting_audio_keep' => (bool) $settings->meeting_audio_keep,
                'meeting_pro_model' => $settings->meeting_pro_model ?? '',
                'embedding_model' => $settings->embedding_model ?? '',
                'can_record_meetings' => $settings->resolvedStt() !== null,
            ],
            'providers' => AiSetting::PROVIDERS,
            'suggestedModels' => AiSetting::SUGGESTED_MODELS,
            'imageModels' => AiSetting::IMAGE_MODELS,
            'sttModels' => AiSetting::STT_MODELS,
        ]);
    }

    /**
     * Persist the AI settings. A blank api_key keeps the existing stored key so
     * the masked field does not have to be re-entered on every save.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', AiSetting::class);

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_keys(AiSetting::PROVIDERS))],
            'model' => ['nullable', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['boolean'],
            'image_enabled' => ['boolean'],
            'image_model' => ['nullable', 'string', 'max:120'],
            // Images are billed from the same wallet as chat, priced in tokens.
            'image_token_cost' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'stt_enabled' => ['boolean'],
            'stt_provider' => ['nullable', 'string', Rule::in(array_keys(AiSetting::STT_MODELS))],
            'stt_api_key' => ['nullable', 'string', 'max:255'],
            'stt_model' => ['nullable', 'string', 'max:120'],
            'stt_language' => ['nullable', 'string', 'max:16'],
            // Audio is billed per second by the provider, in tokens here.
            'stt_token_cost_per_minute' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            // Zero is "no ceiling" — the token wallet becomes the only brake.
            'meeting_max_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'meeting_audio_keep' => ['boolean'],
            'meeting_pro_model' => ['nullable', 'string', 'max:120'],
            'embedding_model' => ['nullable', 'string', 'max:120'],
        ]);

        $settings = AiSetting::current();

        $settings->provider = $validated['provider'];
        $settings->model = ($validated['model'] ?? null) ?: null;
        $settings->is_enabled = (bool) ($validated['is_enabled'] ?? true);
        $settings->image_enabled = (bool) ($validated['image_enabled'] ?? false);
        $settings->image_model = ($validated['image_model'] ?? null) ?: null;

        if (isset($validated['image_token_cost'])) {
            $settings->image_token_cost = (int) $validated['image_token_cost'];
        }

        $settings->stt_enabled = (bool) ($validated['stt_enabled'] ?? false);
        $settings->stt_provider = ($validated['stt_provider'] ?? null) ?: 'deepgram';
        $settings->stt_model = ($validated['stt_model'] ?? null) ?: null;
        $settings->stt_language = ($validated['stt_language'] ?? null) ?: null;
        $settings->meeting_audio_keep = (bool) ($validated['meeting_audio_keep'] ?? false);
        $settings->meeting_pro_model = ($validated['meeting_pro_model'] ?? null) ?: null;
        $settings->embedding_model = ($validated['embedding_model'] ?? null) ?: null;

        if (isset($validated['stt_token_cost_per_minute'])) {
            $settings->stt_token_cost_per_minute = (int) $validated['stt_token_cost_per_minute'];
        }

        // `array_key_exists`, not `isset`: a null here means the field was
        // cleared, which is how the ceiling is lifted. `isset` would read that
        // as "not submitted" and quietly keep the old limit.
        if (array_key_exists('meeting_max_minutes', $validated)) {
            $settings->meeting_max_minutes = (int) $validated['meeting_max_minutes'];
        }

        // Only overwrite the stored keys when new ones are actually supplied.
        if (! empty($validated['api_key'])) {
            $settings->api_key = $validated['api_key'];
        }

        if (! empty($validated['stt_api_key'])) {
            $settings->stt_api_key = $validated['stt_api_key'];
        }

        $settings->save();

        return back()->with('success', 'Pengaturan AI berhasil disimpan');
    }
}

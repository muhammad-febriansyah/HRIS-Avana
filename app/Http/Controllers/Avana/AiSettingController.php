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
                'has_key' => $settings->api_key !== null && $settings->api_key !== '',
                'key_preview' => $settings->keyPreview(),
                'is_ready' => $settings->isReady(),
                'image_enabled' => (bool) $settings->image_enabled,
                'image_model' => $settings->image_model ?? '',
                'image_token_cost' => (int) $settings->image_token_cost,
                'can_generate_images' => $settings->resolvedImage() !== null,
            ],
            'providers' => AiSetting::PROVIDERS,
            'suggestedModels' => AiSetting::SUGGESTED_MODELS,
            'imageModels' => AiSetting::IMAGE_MODELS,
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

        // Only overwrite the stored key when a new one is actually supplied.
        if (! empty($validated['api_key'])) {
            $settings->api_key = $validated['api_key'];
        }

        $settings->save();

        return back()->with('success', 'Pengaturan AI berhasil disimpan');
    }
}

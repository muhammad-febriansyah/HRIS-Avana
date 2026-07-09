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
            ],
            'providers' => AiSetting::PROVIDERS,
            'suggestedModels' => AiSetting::SUGGESTED_MODELS,
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
        ]);

        $settings = AiSetting::current();

        $settings->provider = $validated['provider'];
        $settings->model = $validated['model'] ?: null;
        $settings->is_enabled = (bool) ($validated['is_enabled'] ?? true);

        // Only overwrite the stored key when a new one is actually supplied.
        if (! empty($validated['api_key'])) {
            $settings->api_key = $validated['api_key'];
        }

        $settings->save();

        return back()->with('success', 'Pengaturan AI berhasil disimpan');
    }
}

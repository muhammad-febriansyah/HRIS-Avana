<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiSetting;
use App\Models\User;
use App\Services\AiToolkit;
use App\Support\AiPersona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiAssistantController extends Controller
{
    /**
     * Roles that may always use the AI assistant within their tenant.
     *
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'ai';

    /**
     * AvanaHR system persona for the assistant, shared with the mobile API.
     */
    private const SYSTEM_PROMPT = AiPersona::SYSTEM_PROMPT;

    /**
     * Render the GPT-style chat with the conversation history sidebar.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $userId = $request->user()->id;

        $conversations = AiConversation::forUser($userId)
            ->latest('updated_at')
            ->latest('id')
            ->get(['id', 'title', 'updated_at'])
            ->map(fn (AiConversation $conversation): array => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'updated_at' => $conversation->updated_at?->diffForHumans(),
            ]);

        $active = $request->integer('c') > 0
            ? AiConversation::forUser($userId)->find($request->integer('c'))
            : null;

        $messages = $active
            ? $active->messages()->orderBy('id')->get(['id', 'role', 'content'])
                ->map(fn (AiMessage $message): array => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                ])
            : collect();

        return Inertia::render('avana/ai/index', [
            'conversations' => $conversations,
            'activeId' => $active?->id,
            'messages' => $messages,
            'ready' => AiSetting::current()->isReady(),
            'tokenUsage' => $this->tokenUsage($request->user()),
        ]);
    }

    /**
     * Monthly AI token allowance and consumption for the user's tenant.
     *
     * `quota` is null when no tenant/package quota is configured (treated as
     * unlimited). `used` sums the tokens logged on assistant replies for the
     * current calendar month.
     *
     * @return array{used: int, quota: int|null, period: string}
     */
    private function tokenUsage(User $user): array
    {
        $tenant = $user->tenant_id !== null
            ? $user->tenant()->with('package:id,ai_token_quota')->first()
            : null;

        $used = (int) AiMessage::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_tokens');

        return [
            'used' => $used,
            'quota' => $tenant?->ai_token_quota ?? $tenant?->package?->ai_token_quota,
            'period' => now()->locale('id')->translatedFormat('F Y'),
        ];
    }

    /**
     * Stream an assistant reply token-by-token into a conversation via Prism.
     */
    public function stream(Request $request): StreamedResponse
    {
        $this->ensureCan($request, 'view');

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        $conversation = ! empty($data['conversation_id'])
            ? AiConversation::forUser($user->id)->find($data['conversation_id'])
            : null;

        if ($conversation === null) {
            $conversation = AiConversation::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'title' => Str::limit($data['message'], 48),
            ]);
        }

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $history = $conversation->messages()
            ->orderBy('id')
            ->get(['role', 'content'])
            ->map(fn (AiMessage $message) => $message->role === 'assistant'
                ? new AssistantMessage($message->content)
                : new UserMessage($message->content))
            ->all();

        // Provider, model & key come from the super-admin AI settings (falling
        // back to the Prism/env config for the selected provider).
        $ai = AiSetting::current()->resolved();
        $provider = $ai['provider'];
        $apiKey = $ai['api_key'];
        $model = $ai['model'];
        $conversationId = $conversation->id;

        // Data-access tools scoped to this user & tenant so the assistant can
        // answer questions about real HR data (leave, payslip, requests, ...).
        $tools = AiToolkit::forUser($user);

        // Feed the stored key into the Prism provider config for this request.
        if ($apiKey !== '') {
            config(["prism.providers.{$provider}.api_key" => $apiKey]);
        }

        return response()->stream(function () use ($history, $user, $provider, $apiKey, $model, $conversationId, $tools): void {
            $emit = static function (string $text): void {
                echo $text;
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $full = '';
            $promptTokens = null;
            $completionTokens = null;

            if ($apiKey === '' && $provider !== 'ollama') {
                $full = 'Kunci API AI belum dikonfigurasi. Super Admin dapat mengaturnya di menu Pengaturan AI (provider, API key, dan model).';
                $emit($full);
            } else {
                try {
                    $request = Prism::text()
                        ->using($provider, $model)
                        ->withSystemPrompt(self::SYSTEM_PROMPT)
                        ->withMessages($history);

                    // Let the model call data tools and loop back with results.
                    if ($tools !== []) {
                        $request = $request->withTools($tools)->withMaxSteps(6);
                    }

                    foreach ($request->asStream() as $event) {
                        if ($event instanceof TextDeltaEvent && $event->delta !== '') {
                            $full .= $event->delta;
                            $emit($event->delta);
                        } elseif ($event instanceof StreamEndEvent && $event->usage !== null) {
                            $promptTokens = $event->usage->promptTokens;
                            $completionTokens = $event->usage->completionTokens;
                        }
                    }
                } catch (\Throwable $e) {
                    $note = "\n\n[Maaf, terjadi gangguan menghubungi AI: ".$e->getMessage().']';
                    $full .= $note;
                    $emit($note);
                }
            }

            $totalTokens = $promptTokens !== null || $completionTokens !== null
                ? (int) $promptTokens + (int) $completionTokens
                : null;

            AiMessage::create([
                'conversation_id' => $conversationId,
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'role' => 'assistant',
                'content' => $full !== '' ? $full : '(tidak ada respons)',
                'model' => ($apiKey === '' && $provider !== 'ollama') ? null : $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
            ]);

            AiConversation::whereKey($conversationId)->update(['updated_at' => now()]);
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'X-Conversation-Id' => (string) $conversation->id,
        ]);
    }

    /**
     * Delete a single conversation and its messages.
     */
    public function destroyConversation(Request $request, AiConversation $conversation): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        abort_if($conversation->user_id !== $request->user()->id, 404);

        $conversation->delete();

        return back();
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }
}

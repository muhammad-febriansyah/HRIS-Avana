<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiSetting;
use App\Models\Sop;
use App\Models\User;
use App\Services\AiTokenService;
use App\Services\AiToolkit;
use App\Support\AiPersona;
use App\Support\GeneratedImageBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            'sopCitations' => $this->sopCitations($request->user()),
        ]);
    }

    /**
     * SOP codes the caller may read, mapped to the URL that serves the PDF.
     *
     * The assistant is told to name the SOP it quotes; the chat turns each of
     * those codes into a link so the source document is one click away.
     * Mirrors {@see AiToolkit::visibleSops()} — a code the user
     * may not read is simply absent, so nothing here leaks a private SOP.
     *
     * @return array<int, array{code: string, title: string, url: string}>
     */
    private function sopCitations(User $user): array
    {
        $canManage = $user->isSuperAdmin() || $user->hasPermissionTo('sop.view');

        $query = Sop::query()
            ->where('tenant_id', $user->tenant_id)
            ->active()
            ->whereNotNull('code')
            ->whereNotNull('file_path');

        if (! $canManage) {
            $query->publiclyVisible();
        }

        // A plain employee downloads through the self-service route; it needs
        // an employee record, so an admin account without one keeps the
        // management route instead.
        $viaSelfService = ! $canManage && $user->employee !== null;

        if (! $canManage && ! $viaSelfService) {
            return [];
        }

        return $query->get(['id', 'code', 'title'])
            ->map(fn (Sop $sop): array => [
                'code' => (string) $sop->code,
                'title' => $sop->title,
                'url' => $viaSelfService
                    ? route('avana.saya.sop.download', $sop->id)
                    : route('avana.sop.download', $sop->id),
            ])
            ->all();
    }

    /**
     * Monthly AI token allowance, wallet balance and per-user cap for the meter.
     * Sourced from the ledger via {@see AiTokenService::remainingForUser()}.
     *
     * @return array<string, mixed>
     */
    private function tokenUsage(User $user): array
    {
        return app(AiTokenService::class)->remainingForUser($user);
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

        $gate = app(AiTokenService::class)->canChat($user);

        if (! $gate->allowed) {
            $blockMessage = (string) $gate->message;

            return response()->stream(function () use ($blockMessage): void {
                echo $blockMessage;
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            }, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'X-Token-Blocked' => $gate->reason ?? '1',
            ]);
        }

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
                    // Never surface the provider's own words. A billing error
                    // ("You exceeded your current quota") would otherwise be
                    // shown verbatim to a tenant's staff, who cannot act on it
                    // and should not see the platform's account state.
                    Log::error('AI assistant stream failed', [
                        'tenant_id' => $user->tenant_id,
                        'user_id' => $user->id,
                        'provider' => $provider,
                        'model' => $model,
                        'message' => $e->getMessage(),
                    ]);

                    $note = "\n\n[Maaf, terjadi kesalahan sistem saat menghubungi AI. Silakan coba lagi beberapa saat lagi.]";
                    $full .= $note;
                    $emit($note);
                }
            }

            // Anything the assistant drew is appended by us, not by the model:
            // a streamed reply is plain text, and asking a model to echo a long
            // URL verbatim is unreliable. See GeneratedImageBag.
            $drawn = app(GeneratedImageBag::class)->toMarkdown();

            if ($drawn !== '') {
                $full .= $drawn;
                $emit($drawn);
            }

            $totalTokens = $promptTokens !== null || $completionTokens !== null
                ? (int) $promptTokens + (int) $completionTokens
                : null;

            if ($totalTokens !== null && $totalTokens > 0) {
                app(AiTokenService::class)->debit($user, $totalTokens);
            }

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

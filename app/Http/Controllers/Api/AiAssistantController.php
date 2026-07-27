<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiSetting;
use App\Models\User;
use App\Services\AiTokenService;
use App\Services\AiToolkit;
use App\Support\AiPersona;
use App\Support\GeneratedImageBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

/**
 * Employee self-service AI assistant for the mobile app. Every request is
 * scoped to the caller: the data tools (AiToolkit) expose only the caller's
 * own leave, payslip, attendance and requests — never another employee's.
 * Replies are returned as a single JSON payload (no streaming).
 */
class AiAssistantController extends Controller
{
    use ResolvesApiEmployee;

    private const MAX_HISTORY = 20;

    /**
     * Assistant landing payload: readiness, token meter, and recent chats.
     */
    public function session(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'ready' => AiSetting::current()->isReady(),
            'usage' => $this->tokenUsage($user),
            'conversations' => $this->conversationList($user),
            // The SOP prompt earns its place here: the mobile app dropped its
            // SOP menu entry, so the assistant is now the way employees reach
            // those documents, and the empty state is where they find that out.
            'suggestions' => [
                'Berapa sisa cuti saya tahun ini?',
                'Apa isi SOP pengajuan cuti?',
                'Tampilkan slip gaji terakhir saya',
                'Rekap kehadiran saya bulan ini',
            ],
        ]);
    }

    /**
     * Full message history for one of the caller's own conversations.
     */
    public function conversation(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_if($conversation->user_id !== $request->user()->id, 404);

        return response()->json([
            'id' => $conversation->id,
            'title' => $conversation->title,
            'messages' => $conversation->messages()
                ->orderBy('id')
                ->get(['id', 'role', 'content'])
                ->map(fn (AiMessage $message): array => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                ]),
        ]);
    }

    /**
     * Send a message and get the assistant's reply plus the updated token meter.
     */
    public function chat(Request $request): JsonResponse
    {
        // Ensures the caller is a real employee before any tool runs.
        $this->currentEmployee($request);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        $gate = app(AiTokenService::class)->canChat($user);

        if (! $gate->allowed) {
            return response()->json([
                'blocked' => true,
                'reason' => $gate->reason,
                'message' => $gate->message,
                'usage' => app(AiTokenService::class)->remainingForUser($user),
            ], 402);
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

        [$reply, $promptTokens, $completionTokens, $model] = $this->generateReply($user, $conversation);

        $totalTokens = $promptTokens !== null || $completionTokens !== null
            ? (int) $promptTokens + (int) $completionTokens
            : null;

        if ($totalTokens !== null && $totalTokens > 0) {
            app(AiTokenService::class)->debit($user, $totalTokens);
        }

        $assistant = AiMessage::create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $reply !== '' ? $reply : '(tidak ada respons)',
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
        ]);

        $conversation->touch();

        return response()->json([
            'conversation_id' => $conversation->id,
            'title' => $conversation->title,
            'reply' => [
                'id' => $assistant->id,
                'role' => 'assistant',
                'content' => $assistant->content,
            ],
            'usage' => $this->tokenUsage($user),
        ]);
    }

    /**
     * Delete one of the caller's own conversations.
     */
    public function destroyConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_if($conversation->user_id !== $request->user()->id, 404);

        $conversation->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Run the model over the conversation history with the caller-scoped tools.
     *
     * @return array{0: string, 1: int|null, 2: int|null, 3: string|null}
     */
    private function generateReply(User $user, AiConversation $conversation): array
    {
        $ai = AiSetting::current()->resolved();
        $provider = $ai['provider'];
        $apiKey = $ai['api_key'];
        $model = $ai['model'];

        if ($apiKey === '' && $provider !== 'ollama') {
            return [
                'Asisten AI belum aktif. Hubungi admin untuk mengatur penyedia dan API key AI.',
                null,
                null,
                null,
            ];
        }

        $history = $conversation->messages()
            ->orderBy('id')
            ->get(['role', 'content'])
            ->take(-self::MAX_HISTORY)
            ->map(fn (AiMessage $message) => $message->role === 'assistant'
                ? new AssistantMessage($message->content)
                : new UserMessage($message->content))
            ->values()
            ->all();

        // Own-data tools only for an ESS employee; nothing about other people.
        $tools = AiToolkit::forUser($user);

        if ($apiKey !== '') {
            config(["prism.providers.{$provider}.api_key" => $apiKey]);
        }

        try {
            $pending = Prism::text()
                ->using($provider, $model)
                ->withSystemPrompt(AiPersona::SYSTEM_PROMPT)
                ->withMessages($history);

            if ($tools !== []) {
                $pending = $pending->withTools($tools)->withMaxSteps(6);
            }

            $response = $pending->asText();

            // Appended by us rather than by the model — see the web controller
            // and GeneratedImageBag. The mobile chat renders the same markdown.
            $text = $response->text.app(GeneratedImageBag::class)->toMarkdown();

            return [
                $text,
                $response->usage->promptTokens,
                $response->usage->completionTokens,
                $model,
            ];
        } catch (\Throwable $e) {
            // The provider's own wording (billing state, rate limits) is for the
            // log, not for a tenant's employee — see the web controller.
            Log::error('AI assistant chat failed', [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'provider' => $provider,
                'model' => $model,
                'message' => $e->getMessage(),
            ]);

            return [
                'Maaf, terjadi kesalahan sistem saat menghubungi AI. Silakan coba lagi beberapa saat lagi.',
                null,
                null,
                $model,
            ];
        }
    }

    /**
     * Recent conversations for the caller, newest first.
     *
     * @return array<int, array{id: int, title: string, updated_at: string|null}>
     */
    private function conversationList(User $user): array
    {
        return AiConversation::forUser($user->id)
            ->latest('updated_at')
            ->latest('id')
            ->take(30)
            ->get(['id', 'title', 'updated_at'])
            ->map(fn (AiConversation $conversation): array => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'updated_at' => $conversation->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Monthly AI token allowance and consumption for the caller's tenant, plus
     * the wallet and per-user cap breakdown. Sourced from the ledger.
     *
     * @return array<string, mixed>
     */
    private function tokenUsage(User $user): array
    {
        return app(AiTokenService::class)->remainingForUser($user);
    }
}

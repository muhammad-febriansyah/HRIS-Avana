<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiSetting;
use App\Models\User;
use App\Services\AiToolkit;
use App\Support\AiPersona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'suggestions' => [
                'Berapa sisa cuti saya tahun ini?',
                'Tampilkan slip gaji terakhir saya',
                'Rekap kehadiran saya bulan ini',
                'Status pengajuan cuti saya',
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

            return [
                $response->text,
                $response->usage->promptTokens,
                $response->usage->completionTokens,
                $model,
            ];
        } catch (\Throwable $e) {
            return [
                'Maaf, terjadi gangguan menghubungi AI: '.$e->getMessage(),
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
     * Monthly AI token allowance and consumption for the caller's tenant.
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
}

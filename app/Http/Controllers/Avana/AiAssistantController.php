<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
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
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * AvanaHR system persona for the assistant.
     */
    private const SYSTEM_PROMPT = 'Kamu adalah asisten HR untuk AvanaHR, sebuah aplikasi HRIS & Payroll. '
        .'Jawab dalam Bahasa Indonesia yang ringkas, jelas, dan profesional. Bantu seputar payroll, absensi, '
        .'cuti & lembur, data karyawan, rekrutmen, kinerja, dan modul HR lainnya. Gunakan format markdown bila perlu '
        .'(list, bold). Jika pertanyaan di luar konteks HR, tetap bantu dengan sopan.';

    /**
     * Render the GPT-style chat with the conversation history sidebar.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

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
            'ready' => (string) config('prism.providers.openai.api_key') !== '',
        ]);
    }

    /**
     * Stream an assistant reply token-by-token into a conversation via Prism.
     */
    public function stream(Request $request): StreamedResponse
    {
        $this->ensureCanManage($request);

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

        $apiKey = (string) config('prism.providers.openai.api_key');
        $model = (string) env('AI_MODEL', 'gpt-4o-mini');
        $conversationId = $conversation->id;

        return response()->stream(function () use ($history, $user, $apiKey, $model, $conversationId): void {
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

            if ($apiKey === '') {
                $full = 'Kunci OpenAI belum dikonfigurasi. Tambahkan `OPENAI` (atau `OPENAI_API_KEY`) di file `.env`, lalu jalankan ulang.';
                $emit($full);
            } else {
                try {
                    $stream = Prism::text()
                        ->using('openai', $model)
                        ->withSystemPrompt(self::SYSTEM_PROMPT)
                        ->withMessages($history)
                        ->asStream();

                    foreach ($stream as $event) {
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
                'model' => $apiKey === '' ? null : $model,
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
        $this->ensureCanManage($request);
        abort_if($conversation->user_id !== $request->user()->id, 404);

        $conversation->delete();

        return back();
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasEmployeePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'employee.'));

        abort_unless($isPrivileged || $hasEmployeePermission, 403);
    }
}

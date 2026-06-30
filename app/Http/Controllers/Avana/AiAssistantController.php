<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Prism\Prism\Facades\Prism;
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
     * Render the GPT-style chat with this user's conversation history.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $messages = AiMessage::forUser($request->user()->id)
            ->orderBy('id')
            ->get(['id', 'role', 'content'])
            ->map(fn (AiMessage $message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
            ]);

        return Inertia::render('avana/ai/index', [
            'messages' => $messages,
            'model' => (string) config('prism.providers.openai.api_key') !== ''
                ? (string) env('AI_MODEL', 'gpt-4o-mini')
                : null,
        ]);
    }

    /**
     * Stream an assistant reply token-by-token via Prism + OpenAI.
     */
    public function stream(Request $request): StreamedResponse
    {
        $this->ensureCanManage($request);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $user = $request->user();

        AiMessage::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $history = AiMessage::forUser($user->id)
            ->orderBy('id')
            ->get(['role', 'content'])
            ->map(fn (AiMessage $message) => $message->role === 'assistant'
                ? new AssistantMessage($message->content)
                : new UserMessage($message->content))
            ->all();

        $apiKey = (string) config('prism.providers.openai.api_key');
        $model = (string) env('AI_MODEL', 'gpt-4o-mini');

        return response()->stream(function () use ($history, $user, $apiKey, $model): void {
            $emit = static function (string $text): void {
                echo $text;
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $full = '';

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
                        }
                    }
                } catch (\Throwable $e) {
                    $note = "\n\n[Maaf, terjadi gangguan menghubungi AI: ".$e->getMessage().']';
                    $full .= $note;
                    $emit($note);
                }
            }

            AiMessage::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'role' => 'assistant',
                'content' => $full !== '' ? $full : '(tidak ada respons)',
            ]);
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Clear the acting user's conversation history.
     */
    public function clear(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        AiMessage::forUser($request->user()->id)->delete();

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

<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Meeting;
use App\Models\MeetingSegment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The server half of a live meeting recording.
 *
 * The phone streams audio straight to the speech provider — that is what makes
 * the transcript arrive as it is spoken — so nothing here ever touches audio.
 * What this class owns instead is the two things that cannot be trusted to a
 * phone: the provider credential, and the meter.
 *
 * The credential is handled by never sending it. The phone asks for a grant, and
 * gets back a token that expires in under a minute and can do nothing but open a
 * listening socket. The project key stays in the database.
 *
 * The meter rides the transcript. Because audio bypasses us, the batches of
 * finished text the phone posts back are both the only copy of the transcript we
 * will ever have AND the only evidence of how much audio was heard. So they are
 * one call: text is stored and the newly-heard audio is charged in the same
 * transaction, capped by what the wallet can still cover. A phone that dies
 * mid-meeting has already paid for the minutes it used, and a phone that resends
 * a batch it was unsure about pays for none of them twice — `meetings.billed_ms`
 * is what makes both true.
 */
final class MeetingTranscriber
{
    /**
     * Audio is charged in whole blocks so a heartbeat every few seconds does
     * not write a ledger row per breath. 15s is short enough that an abandoned
     * recording is billed roughly honestly, long enough to keep the ledger
     * readable.
     */
    public const BILLING_BLOCK_MS = 15_000;

    /**
     * How long a grant may live. Deepgram caps this at an hour; we want the
     * opposite of long — a token that leaks is worthless within the minute.
     * The phone re-asks as it nears expiry, which is a cheap authenticated call.
     */
    private const GRANT_TTL_SECONDS = 60;

    private const DEEPGRAM_GRANT_URL = 'https://api.deepgram.com/v1/auth/grant';

    private const DEEPGRAM_LISTEN_URL = 'wss://api.deepgram.com/v1/listen';

    public function __construct(private readonly AiTokenService $tokens) {}

    /**
     * Whether meeting recording is configured at all.
     */
    public function isAvailable(): bool
    {
        return AiSetting::current()->resolvedStt() !== null;
    }

    /**
     * A short-lived credential the phone can open a listening socket with, plus
     * the socket parameters to open it with.
     *
     * The token gate runs here rather than only at meeting creation: this is the
     * call made every time the socket is (re)opened, so it is the one place a
     * tenant that has run dry mid-meeting can still be stopped.
     *
     * @return array{access_token: string, expires_in: int, ws_url: string, params: array<string, string>, max_minutes: int, block_ms: int}
     *
     * @throws RuntimeException when recording is off, blocked, or the provider refuses
     */
    public function grantToken(User $user, Meeting $meeting): array
    {
        $stt = AiSetting::current()->resolvedStt();

        if ($stt === null) {
            throw new RuntimeException('Perekaman rapat sedang tidak aktif. Hubungi Super Admin.');
        }

        if ($meeting->status !== Meeting::STATUS_RECORDING) {
            throw new RuntimeException('Rapat ini sudah tidak dalam status merekam.');
        }

        $gate = $this->tokens->canChat($user);

        if (! $gate->allowed) {
            throw new RuntimeException((string) $gate->message);
        }

        if ($stt['provider'] !== 'deepgram') {
            throw new RuntimeException('Penyedia transkripsi belum didukung: '.$stt['provider']);
        }

        $response = Http::withToken($stt['api_key'], 'Token')
            ->timeout(15)
            ->post(self::DEEPGRAM_GRANT_URL, ['ttl_seconds' => self::GRANT_TTL_SECONDS]);

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            // The provider's own words are never surfaced to the user: a billing
            // or permission message is about the platform's account, which a
            // tenant's staff can neither act on nor should see. They do belong
            // in the log, where an operator reads them — a 403 here means the
            // configured key cannot mint tokens (a transcription-only key), and
            // without the reason that is a long guess.
            Log::error('Deepgram grant failed', [
                'tenant_id' => $user->tenant_id,
                'meeting_id' => $meeting->id,
                'status' => $response->status(),
                'provider_message' => Str::limit((string) $response->body(), 300),
            ]);

            throw new RuntimeException('Gagal menyiapkan sesi transkripsi. Coba lagi beberapa saat lagi.');
        }

        return [
            'access_token' => (string) $response->json('access_token'),
            'expires_in' => (int) ($response->json('expires_in') ?: self::GRANT_TTL_SECONDS),
            'ws_url' => self::DEEPGRAM_LISTEN_URL,
            // Sent as data rather than baked into the app: the model, the
            // language and even whether to diarize are settings a super admin
            // changes, and a phone build should not have to be reshipped for it.
            'params' => [
                'model' => $stt['model'],
                'language' => $stt['language'],
                'diarize' => 'true',
                'punctuate' => 'true',
                'smart_format' => 'true',
                'interim_results' => 'true',
                'encoding' => 'linear16',
                'sample_rate' => '16000',
                'channels' => '1',
            ],
            'max_minutes' => $stt['max_minutes'],
            'block_ms' => self::BILLING_BLOCK_MS,
        ];
    }

    /**
     * Store a batch of finished segments and charge the audio they cover.
     *
     * @param  array<int, array{start_ms: int, end_ms: int, speaker?: int, text: string}>  $segments
     * @param  int  $elapsedMs  audio heard so far, as the phone counts it
     * @return array{stored: int, duration_ms: int, billed_ms: int, tokens_charged: int, stop: bool, reason: ?string, message: ?string}
     */
    public function ingestSegments(User $user, Meeting $meeting, array $segments, int $elapsedMs): array
    {
        $stt = AiSetting::current()->resolvedStt();
        $costPerMinute = $stt['token_cost_per_minute'] ?? 0;
        $maxMinutes = $stt['max_minutes'] ?? 180;

        $stored = $this->storeSegments($meeting, $segments);

        // The phone's clock is what we have, but it is not allowed to run past
        // the ceiling: audio bypasses the server, so an endless socket is
        // otherwise an endless bill.
        $ceilingMs = $maxMinutes * 60_000;
        $elapsedMs = max(0, min($elapsedMs, $ceilingMs));

        // How much audio is newly chargeable is decided against the LOCKED row,
        // not the copy this request loaded. A phone that resends a batch it was
        // unsure about — the very case the segment unique key exists for — would
        // otherwise have both attempts read the same `billed_ms`, each conclude
        // the same minute was unpaid, and pay for it twice.
        $chargeable = DB::transaction(function () use ($meeting, $elapsedMs): int {
            $fresh = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();

            if ($fresh === null) {
                return 0;
            }

            $newAudio = intdiv(max(0, $elapsedMs - $fresh->billed_ms), self::BILLING_BLOCK_MS) * self::BILLING_BLOCK_MS;

            $fresh->update([
                'duration_ms' => max($fresh->duration_ms, $elapsedMs),
                'billed_ms' => $fresh->billed_ms + $newAudio,
            ]);

            $meeting->setRawAttributes($fresh->getAttributes(), true);

            return $newAudio;
        });

        $charged = $chargeable > 0 && $costPerMinute > 0
            ? (int) ceil($chargeable / 60_000 * $costPerMinute)
            : 0;

        if ($charged > 0) {
            $this->tokens->debit($user, $charged, 'meeting_stt');
        }

        // Only after charging: whether there is anything left to keep going on.
        $gate = $this->tokens->canChat($user);
        $atCeiling = $elapsedMs >= $ceilingMs;

        return [
            'stored' => $stored,
            'duration_ms' => $meeting->duration_ms,
            'billed_ms' => $meeting->billed_ms,
            'tokens_charged' => $charged,
            'stop' => $atCeiling || ! $gate->allowed,
            'reason' => $atCeiling ? 'max_duration' : $gate->reason,
            'message' => $atCeiling
                ? sprintf('Batas durasi rekaman %d menit tercapai. Rekaman dihentikan dan tetap diringkas.', $maxMinutes)
                : $gate->message,
        ];
    }

    /**
     * Insert the segments that are new, ignoring any the phone already sent.
     *
     * @param  array<int, array{start_ms: int, end_ms: int, speaker?: int, text: string}>  $segments
     * @return int how many rows were actually new
     */
    private function storeSegments(Meeting $meeting, array $segments): int
    {
        $rows = [];

        foreach ($segments as $segment) {
            $text = trim((string) ($segment['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $start = max(0, (int) ($segment['start_ms'] ?? 0));

            $rows[$start] = [
                'meeting_id' => $meeting->id,
                'tenant_id' => $meeting->tenant_id,
                'speaker_index' => max(0, (int) ($segment['speaker'] ?? 0)),
                'start_ms' => $start,
                'end_ms' => max($start, (int) ($segment['end_ms'] ?? $start)),
                'text' => $text,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows === []) {
            return 0;
        }

        // insertOrIgnore over the (meeting_id, start_ms) unique key: a resent
        // batch must be a no-op, not a duplicate paragraph in the transcript.
        return MeetingSegment::query()->insertOrIgnore(array_values($rows));
    }
}

<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingChunk;
use App\Models\MeetingInsight;
use App\Models\MeetingSegment;
use App\Models\MeetingSpeaker;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use RuntimeException;
use Throwable;

/**
 * Turns a finished transcript into something a person would actually read, and
 * answers the deep questions when somebody asks for them.
 *
 * Two models, on purpose. The automatic pass — naming the speakers, the summary,
 * the action items, the embeddings — runs for every recording whether anyone
 * opens it or not, so it rides the cheap chat model. The five premium analyses
 * are the only thing that reaches for the expensive reasoning model, and only
 * when a person clicks, which is what keeps a feature that could read hours of
 * audio a day affordable.
 *
 * Long meetings are summarised in two passes rather than one enormous prompt: an
 * hour of speech does not fit a context window worth paying for, and a model
 * handed 200 pages summarises the last ten. Windows of transcript become notes,
 * and the notes become the summary.
 */
final class MeetingSummarizer
{
    /**
     * Characters of transcript per window in the first pass. Roughly 6k tokens —
     * small enough that the model attends to all of it.
     */
    private const WINDOW_CHARS = 24_000;

    /**
     * Characters per embedded chunk. Long enough to hold a full exchange, short
     * enough that a match points at a minute of the meeting, not ten.
     */
    private const CHUNK_CHARS = 1_200;

    /**
     * Embedding inputs per provider call.
     */
    private const EMBED_BATCH = 64;

    /**
     * Seconds to wait for a premium analysis.
     */
    private const INSIGHT_TIMEOUT = 240;

    public function __construct(private readonly AiTokenService $tokens) {}

    /**
     * The full automatic pass over a stopped recording.
     *
     * Tokens are debited for the steps whose output was saved, even if a later
     * step fails: the provider has already charged the platform for those, and
     * the tenant has the speaker names or the summary to show for it. A step
     * that produced nothing costs nothing — same rule as
     * {@see AiImageGenerator::generate()}.
     */
    public function process(Meeting $meeting): void
    {
        $segments = $meeting->segments()->get();

        if ($segments->isEmpty()) {
            $meeting->update([
                'status' => Meeting::STATUS_FAILED,
                'failure_reason' => 'Tidak ada ucapan yang berhasil ditranskrip dari rekaman ini.',
            ]);

            return;
        }

        $models = AiSetting::current()->resolvedMeetingModels();

        if (! AiSetting::current()->isReady()) {
            $meeting->update([
                'status' => Meeting::STATUS_FAILED,
                'failure_reason' => 'Layanan AI belum dikonfigurasi. Hubungi Super Admin.',
            ]);

            return;
        }

        $this->applyKey($models);

        $spent = 0;

        try {
            $spent += $this->nameSpeakers($meeting, $segments, $models);

            // Names first, so the summary quotes people rather than numbers.
            $meeting->load('speakers.employee');

            $spent += $this->writeSummary($meeting, $segments, $models);
            $spent += $this->buildChunks($meeting, $segments, $models);

            $meeting->update([
                'status' => Meeting::STATUS_READY,
                'summary_model' => $models['summary'],
                'failure_reason' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('Meeting summary failed', [
                'tenant_id' => $meeting->tenant_id,
                'meeting_id' => $meeting->id,
                'message' => $e->getMessage(),
            ]);

            $meeting->update([
                'status' => Meeting::STATUS_FAILED,
                // Never the provider's own words: a quota or billing message is
                // about the platform's account, not something a tenant can act on.
                'failure_reason' => 'Terjadi kesalahan saat meringkas rapat. Coba proses ulang beberapa saat lagi.',
            ]);
        } finally {
            $this->charge($meeting, $spent, 'meeting_summary');
        }
    }

    /**
     * Run one of the premium analyses and store it.
     *
     * @throws RuntimeException when AI is unavailable, blocked, or the analysis is unknown
     */
    public function generateInsight(Meeting $meeting, User $user, string $type): MeetingInsight
    {
        if (! in_array($type, MeetingInsight::types(), true)) {
            throw new RuntimeException('Jenis analisis tidak dikenal.');
        }

        if ($meeting->status !== Meeting::STATUS_READY) {
            throw new RuntimeException('Transkrip rapat belum siap dianalisis.');
        }

        $settings = AiSetting::current();

        if (! $settings->isReady()) {
            throw new RuntimeException('Layanan AI belum dikonfigurasi. Hubungi Super Admin.');
        }

        $gate = $this->tokens->canChat($user);

        if (! $gate->allowed) {
            throw new RuntimeException((string) $gate->message);
        }

        $models = $settings->resolvedMeetingModels();
        $this->applyKey($models);

        $meeting->load('speakers.employee');

        // A three-hour meeting does not fit a context window, and the analyses
        // that matter most — decisions, follow-ups — are usually settled at the
        // end, so truncating would drop exactly the part being asked about.
        // Long transcripts are condensed the same way the summary condenses
        // them, and the cheap model's share of that is billed alongside.
        $transcript = $this->labelledTranscript($meeting, $meeting->segments()->get());
        $condensedTokens = 0;

        if (mb_strlen($transcript) > self::WINDOW_CHARS) {
            [$transcript, $condensedTokens] = $this->condense($meeting, $transcript, $models);
        }

        try {
            $response = Prism::structured()
                ->using($models['provider'], $models['pro'])
                ->withSchema($this->insightSchema($type))
                ->withSystemPrompt($this->insightSystemPrompt())
                ->withPrompt($this->insightPrompt($meeting, $type, $transcript))
                // Prism's shared 30s timeout suits a chat completion. A
                // reasoning model reading an hour of transcript routinely needs
                // minutes, and the person is watching a button spin.
                ->withClientOptions(['timeout' => self::INSIGHT_TIMEOUT, 'connect_timeout' => 15])
                ->asStructured();
        } catch (Throwable $e) {
            Log::error('Meeting insight failed', [
                'tenant_id' => $meeting->tenant_id,
                'meeting_id' => $meeting->id,
                'type' => $type,
                'message' => $e->getMessage(),
            ]);

            // Condensing already happened at the provider, so it is billed even
            // though the analysis it was preparing never landed.
            $this->tokens->debit($user, $condensedTokens, 'meeting_pro');

            throw new RuntimeException('Gagal membuat analisis. Coba lagi beberapa saat lagi.');
        }

        $tokens = $this->usageTokens($response->usage->promptTokens, $response->usage->completionTokens) + $condensedTokens;

        $insight = MeetingInsight::updateOrCreate(
            ['meeting_id' => $meeting->id, 'type' => $type],
            [
                'tenant_id' => $meeting->tenant_id,
                'payload' => $response->structured ?? [],
                'model' => $models['pro'],
                'tokens' => $tokens,
                'generated_by' => $user->id,
                'generated_at' => now(),
            ],
        );

        // Charged after the answer is safely stored, so a failed call is free.
        $this->tokens->debit($user, $tokens, 'meeting_pro');

        return $insight;
    }

    /**
     * Propose a name for each speaker number from what they said.
     *
     * Diarization only separates voices; who they belong to is a guess until a
     * person confirms it, which is why every row is written with
     * `guessed_by_ai` set and the meeting page asks for confirmation.
     *
     * @param  Collection<int, MeetingSegment>  $segments
     * @param  array{provider: string, summary: string, pro: string, embedding: string, api_key: string}  $models
     * @return int tokens spent
     */
    private function nameSpeakers(Meeting $meeting, Collection $segments, array $models): int
    {
        $indexes = $segments->pluck('speaker_index')->unique()->sort()->values();

        // Seed a row per voice first: the transcript must be readable even if
        // the guess fails or the model declines to name anyone.
        foreach ($indexes as $index) {
            MeetingSpeaker::firstOrCreate(
                ['meeting_id' => $meeting->id, 'speaker_index' => (int) $index],
                ['tenant_id' => $meeting->tenant_id, 'guessed_by_ai' => false],
            );
        }

        $roster = Employee::query()
            ->where('tenant_id', $meeting->tenant_id)
            ->whereIn('id', $meeting->participants()->pluck('employee_id'))
            ->get(['id', 'full_name']);

        $schema = new ObjectSchema(
            name: 'speakers',
            description: 'Tebakan identitas setiap pembicara.',
            properties: [
                new ArraySchema(
                    name: 'speakers',
                    description: 'Satu entri per nomor pembicara yang muncul di transkrip.',
                    items: new ObjectSchema(
                        name: 'speaker',
                        description: 'Tebakan untuk satu nomor pembicara.',
                        properties: [
                            new NumberSchema('speaker_index', 'Nomor pembicara seperti di transkrip.'),
                            new StringSchema('name', 'Nama orangnya. Kosongkan jika tidak ada petunjuk sama sekali.'),
                            new NumberSchema('confidence', 'Keyakinan 0 sampai 1.'),
                        ],
                        requiredFields: ['speaker_index', 'name', 'confidence'],
                    ),
                ),
            ],
            requiredFields: ['speakers'],
        );

        $prompt = sprintf(
            "Transkrip rapat \"%s\" di bawah memakai nomor pembicara, bukan nama.\n\n"
            .'Tebak nama tiap nomor HANYA dari petunjuk di dalam percakapan: perkenalan diri, '
            .'cara orang lain menyapa mereka, atau jabatan yang mereka sebut. '
            ."Jangan mengarang. Jika tidak ada petunjuk untuk satu nomor, kembalikan name kosong dan confidence 0.\n\n"
            ."%s\n\nTranskrip:\n%s",
            $meeting->title,
            $roster->isEmpty()
                ? 'Tidak ada daftar peserta.'
                : 'Peserta yang terdaftar (pakai nama ini bila cocok): '.$roster->pluck('full_name')->implode(', '),
            $this->windowText($segments, self::WINDOW_CHARS),
        );

        $response = Prism::structured()
            ->using($models['provider'], $models['summary'])
            ->withSchema($schema)
            ->withPrompt($prompt)
            ->asStructured();

        foreach ($response->structured['speakers'] ?? [] as $guess) {
            $name = trim((string) ($guess['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $matched = $roster->first(
                fn (Employee $employee): bool => mb_strtolower($employee->full_name) === mb_strtolower($name)
            );

            MeetingSpeaker::query()
                ->where('meeting_id', $meeting->id)
                ->where('speaker_index', (int) ($guess['speaker_index'] ?? 0))
                ->update([
                    'display_name' => $matched?->full_name ?? $name,
                    'employee_id' => $matched?->id,
                    'guessed_by_ai' => true,
                    'confidence' => min(1, max(0, (float) ($guess['confidence'] ?? 0))),
                ]);
        }

        return $this->usageTokens($response->usage->promptTokens, $response->usage->completionTokens);
    }

    /**
     * The summary, the decisions and the action items.
     *
     * @param  Collection<int, MeetingSegment>  $segments
     * @param  array{provider: string, summary: string, pro: string, embedding: string, api_key: string}  $models
     * @return int tokens spent
     */
    private function writeSummary(Meeting $meeting, Collection $segments, array $models): int
    {
        $transcript = $this->labelledTranscript($meeting, $segments);

        // Two passes only when it is needed: one window is already the whole
        // meeting, and a reduce step over a single note adds cost, not accuracy.
        [$source, $spent] = mb_strlen($transcript) > self::WINDOW_CHARS
            ? $this->condense($meeting, $transcript, $models)
            : [$transcript, 0];

        $schema = new ObjectSchema(
            name: 'ringkasan_rapat',
            description: 'Ringkasan rapat beserta keputusan dan tindak lanjutnya.',
            properties: [
                new StringSchema('summary', 'Ringkasan rapat dalam 2-4 paragraf Bahasa Indonesia.'),
                new ArraySchema(
                    name: 'decisions',
                    description: 'Keputusan yang benar-benar diambil. Kosongkan bila tidak ada.',
                    items: new StringSchema('decision', 'Satu keputusan.'),
                ),
                new ArraySchema(
                    name: 'action_items',
                    description: 'Tugas tindak lanjut yang disebut di rapat.',
                    items: new ObjectSchema(
                        name: 'action_item',
                        description: 'Satu tindak lanjut.',
                        properties: [
                            new StringSchema('text', 'Tugasnya, satu kalimat.'),
                            new StringSchema('assignee', 'Nama penanggung jawab, kosongkan bila tidak disebut.'),
                            new StringSchema('due_date', 'Tenggat sebagai YYYY-MM-DD, kosongkan bila tidak disebut.'),
                        ],
                        requiredFields: ['text', 'assignee', 'due_date'],
                    ),
                ),
            ],
            requiredFields: ['summary', 'decisions', 'action_items'],
        );

        $response = Prism::structured()
            ->using($models['provider'], $models['summary'])
            ->withSchema($schema)
            ->withSystemPrompt('Anda notulen rapat yang teliti. Jawab dalam Bahasa Indonesia. Hanya gunakan isi transkrip — jangan menyimpulkan hal yang tidak dibahas.')
            ->withPrompt(sprintf("Rapat: %s\nTanggal: %s\n\n%s", $meeting->title, $this->meetingDate($meeting), $source))
            ->asStructured();

        $spent += $this->usageTokens($response->usage->promptTokens, $response->usage->completionTokens);
        $structured = $response->structured ?? [];

        $summary = trim((string) ($structured['summary'] ?? ''));
        $decisions = array_values(array_filter(array_map(
            fn ($decision): string => trim((string) $decision),
            $structured['decisions'] ?? [],
        )));

        if ($decisions !== []) {
            // Decisions live inside the summary text rather than in their own
            // table: they are read together, and a heading keeps them findable
            // without a schema nobody queries separately.
            $summary = trim($summary."\n\n## Keputusan\n".collect($decisions)->map(fn (string $d): string => '- '.$d)->implode("\n"));
        }

        $meeting->update([
            'summary' => $summary,
            'summary_model' => $models['summary'],
        ]);

        $this->storeActionItems($meeting, $structured['action_items'] ?? []);

        return $spent;
    }

    /**
     * Save the proposed follow-ups, replacing an earlier AI pass but leaving
     * anything a person typed alone.
     *
     * @param  array<int, array{text?: string, assignee?: string, due_date?: string}>  $items
     */
    private function storeActionItems(Meeting $meeting, array $items): void
    {
        $meeting->actionItems()->where('source', MeetingActionItem::SOURCE_AI)->delete();

        $roster = Employee::query()
            ->where('tenant_id', $meeting->tenant_id)
            ->whereIn('id', $meeting->participants()->pluck('employee_id'))
            ->get(['id', 'full_name']);

        $order = 0;

        foreach ($items as $item) {
            $text = trim((string) ($item['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $assigneeName = trim((string) ($item['assignee'] ?? ''));
            $assignee = $assigneeName === ''
                ? null
                : $roster->first(fn (Employee $e): bool => mb_strtolower($e->full_name) === mb_strtolower($assigneeName));

            MeetingActionItem::create([
                'meeting_id' => $meeting->id,
                'tenant_id' => $meeting->tenant_id,
                'text' => $assignee === null && $assigneeName !== ''
                    ? $text.' ('.$assigneeName.')'
                    : $text,
                'assignee_employee_id' => $assignee?->id,
                'due_date' => $this->parseDate($item['due_date'] ?? null),
                'status' => MeetingActionItem::STATUS_OPEN,
                'source' => MeetingActionItem::SOURCE_AI,
                'sort_order' => $order++,
            ]);
        }
    }

    /**
     * Cut the transcript into chunks and embed them, so the assistant can
     * answer a question about a two-hour meeting by reading a minute of it.
     *
     * @param  Collection<int, MeetingSegment>  $segments
     * @param  array{provider: string, summary: string, pro: string, embedding: string, api_key: string}  $models
     * @return int tokens spent
     */
    private function buildChunks(Meeting $meeting, Collection $segments, array $models): int
    {
        $meeting->chunks()->delete();

        $chunks = [];
        $buffer = '';
        $start = null;
        $end = 0;

        foreach ($segments as $segment) {
            $line = sprintf('%s: %s', $meeting->speakerName($segment->speaker_index), $segment->text);

            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($line) > self::CHUNK_CHARS) {
                $chunks[] = ['text' => $buffer, 'start_ms' => $start ?? 0, 'end_ms' => $end];
                $buffer = '';
                $start = null;
            }

            $start ??= $segment->start_ms;
            $end = $segment->end_ms;
            $buffer = $buffer === '' ? $line : $buffer."\n".$line;
        }

        if ($buffer !== '') {
            $chunks[] = ['text' => $buffer, 'start_ms' => $start ?? 0, 'end_ms' => $end];
        }

        if ($chunks === []) {
            return 0;
        }

        $spent = 0;
        $ordinal = 0;

        foreach (array_chunk($chunks, self::EMBED_BATCH) as $batch) {
            $response = Prism::embeddings()
                ->using($models['provider'], $models['embedding'])
                ->fromArray(array_column($batch, 'text'))
                ->asEmbeddings();

            $spent += (int) ($response->usage->tokens ?? 0);

            foreach ($batch as $position => $chunk) {
                MeetingChunk::create([
                    'meeting_id' => $meeting->id,
                    'tenant_id' => $meeting->tenant_id,
                    'ordinal' => $ordinal++,
                    'text' => $chunk['text'],
                    'start_ms' => $chunk['start_ms'],
                    'end_ms' => $chunk['end_ms'],
                    'embedding' => $response->embeddings[$position]->embedding ?? null,
                    'embedding_model' => $models['embedding'],
                ]);
            }
        }

        return $spent;
    }

    /**
     * Boil a transcript too long to read in one go down to notes.
     *
     * Each window is summarised on its own by the cheap model, and the notes
     * stand in for the transcript afterwards. Windowing rather than truncating
     * because what a meeting decides is usually settled at the end — cutting
     * the tail would drop the part most questions are about.
     *
     * @param  array{provider: string, summary: string, pro: string, embedding: string, api_key: string}  $models
     * @return array{0: string, 1: int} the notes, and the tokens they cost
     */
    private function condense(Meeting $meeting, string $transcript, array $models): array
    {
        $windows = $this->windows($transcript);

        if (count($windows) < 2) {
            return [$transcript, 0];
        }

        $notes = [];
        $spent = 0;

        foreach ($windows as $position => $window) {
            $response = Prism::text()
                ->using($models['provider'], $models['summary'])
                ->withSystemPrompt('Anda mencatat rapat. Tulis catatan padat dalam Bahasa Indonesia: pokok bahasan, keputusan, angka, nama, dan tugas yang disebut. Jangan menambah apa pun yang tidak ada di teks.')
                ->withPrompt(sprintf(
                    "Rapat: %s\nBagian %d dari %d transkrip:\n\n%s",
                    $meeting->title,
                    $position + 1,
                    count($windows),
                    $window,
                ))
                ->asText();

            $notes[] = $response->text;
            $spent += $this->usageTokens($response->usage->promptTokens, $response->usage->completionTokens);
        }

        return [implode("\n\n", $notes), $spent];
    }

    /**
     * The transcript as a person reads it: `mm:ss Nama: kata-kata`.
     *
     * @param  Collection<int, MeetingSegment>  $segments
     */
    public function labelledTranscript(Meeting $meeting, Collection $segments): string
    {
        return $segments
            ->map(fn (MeetingSegment $segment): string => sprintf(
                '[%s] %s: %s',
                $segment->timecode(),
                $meeting->speakerName($segment->speaker_index),
                $segment->text,
            ))
            ->implode("\n");
    }

    /**
     * Speaker-numbered transcript, capped — used before names are known.
     *
     * @param  Collection<int, MeetingSegment>  $segments
     */
    private function windowText(Collection $segments, int $maxChars): string
    {
        $text = $segments
            ->map(fn (MeetingSegment $segment): string => sprintf(
                '[%s] Pembicara %d: %s',
                $segment->timecode(),
                $segment->speaker_index + 1,
                $segment->text,
            ))
            ->implode("\n");

        return mb_strlen($text) > $maxChars ? mb_substr($text, 0, $maxChars) : $text;
    }

    /**
     * Split a transcript on line boundaries into windows the model can hold.
     *
     * @return array<int, string>
     */
    private function windows(string $transcript): array
    {
        if (mb_strlen($transcript) <= self::WINDOW_CHARS) {
            return [$transcript];
        }

        $windows = [];
        $buffer = '';

        foreach (explode("\n", $transcript) as $line) {
            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($line) > self::WINDOW_CHARS) {
                $windows[] = $buffer;
                $buffer = '';
            }

            $buffer = $buffer === '' ? $line : $buffer."\n".$line;
        }

        if ($buffer !== '') {
            $windows[] = $buffer;
        }

        return $windows;
    }

    private function insightSystemPrompt(): string
    {
        return 'Anda analis senior yang membaca notulen rapat perusahaan. Jawab dalam Bahasa Indonesia, '
            .'padat dan spesifik. Setiap poin harus bisa ditelusuri ke isi transkrip — jangan mengarang '
            .'nama, angka, atau keputusan yang tidak ada di sana.';
    }

    private function insightPrompt(Meeting $meeting, string $type, string $transcript): string
    {
        $task = match ($type) {
            MeetingInsight::TYPE_EXECUTIVE_SUMMARY => 'Tulis ringkasan untuk direksi: satu kalimat pembuka yang menangkap inti rapat, '
                .'lalu paragraf penjelas, lalu poin-poin terpenting.',
            MeetingInsight::TYPE_DECISION_ANALYSIS => 'Bedah setiap keputusan yang diambil: alasannya, siapa yang memutuskan, dampaknya, '
                .'dan pertanyaan yang masih menggantung.',
            MeetingInsight::TYPE_PROJECT_RISK => 'Identifikasi risiko proyek yang tersirat maupun tersurat di rapat ini, '
                .'beserta tingkat keparahan, kemungkinan terjadi, mitigasi, dan pemiliknya.',
            MeetingInsight::TYPE_SENTIMENT => 'Nilai sentimen rapat secara keseluruhan dan per pembicara, '
                .'serta tunjukkan momen ketegangan atau ketidaksepakatan.',
            MeetingInsight::TYPE_FOLLOW_UP => 'Rekomendasikan tindak lanjut konkret setelah rapat ini, '
                .'dengan pemilik, tenggat, dan prioritas.',
            default => 'Analisis rapat ini.',
        };

        return sprintf(
            "Rapat: %s\nTanggal: %s\n\nTugas: %s\n\nTranskrip:\n%s",
            $meeting->title,
            $this->meetingDate($meeting),
            $task,
            $transcript,
        );
    }

    private function insightSchema(string $type): ObjectSchema
    {
        return match ($type) {
            MeetingInsight::TYPE_EXECUTIVE_SUMMARY => new ObjectSchema(
                name: 'executive_summary',
                description: 'Ringkasan tingkat direksi.',
                properties: [
                    new StringSchema('headline', 'Satu kalimat inti rapat.'),
                    new ArraySchema('paragraphs', 'Paragraf penjelas.', new StringSchema('paragraph', 'Satu paragraf.')),
                    new ArraySchema('key_points', 'Poin terpenting.', new StringSchema('point', 'Satu poin.')),
                ],
                requiredFields: ['headline', 'paragraphs', 'key_points'],
            ),
            MeetingInsight::TYPE_DECISION_ANALYSIS => new ObjectSchema(
                name: 'decision_analysis',
                description: 'Bedah keputusan rapat.',
                properties: [
                    new ArraySchema('decisions', 'Keputusan beserta analisisnya.', new ObjectSchema(
                        name: 'decision',
                        description: 'Satu keputusan.',
                        properties: [
                            new StringSchema('decision', 'Keputusannya.'),
                            new StringSchema('rationale', 'Alasan di baliknya.'),
                            new StringSchema('owner', 'Siapa yang memutuskan atau menanggungnya.'),
                            new StringSchema('impact', 'Dampak yang diharapkan.'),
                            new ArraySchema('open_questions', 'Yang masih menggantung.', new StringSchema('question', 'Satu pertanyaan.')),
                        ],
                        requiredFields: ['decision', 'rationale', 'owner', 'impact', 'open_questions'],
                    )),
                ],
                requiredFields: ['decisions'],
            ),
            MeetingInsight::TYPE_PROJECT_RISK => new ObjectSchema(
                name: 'project_risk',
                description: 'Risiko proyek dari rapat ini.',
                properties: [
                    new ArraySchema('risks', 'Daftar risiko.', new ObjectSchema(
                        name: 'risk',
                        description: 'Satu risiko.',
                        properties: [
                            new StringSchema('risk', 'Risikonya.'),
                            new StringSchema('severity', 'Keparahan: rendah, sedang, atau tinggi.'),
                            new StringSchema('likelihood', 'Kemungkinan: rendah, sedang, atau tinggi.'),
                            new StringSchema('mitigation', 'Mitigasi yang disarankan.'),
                            new StringSchema('owner', 'Pemilik risiko, kosongkan bila tidak jelas.'),
                        ],
                        requiredFields: ['risk', 'severity', 'likelihood', 'mitigation', 'owner'],
                    )),
                ],
                requiredFields: ['risks'],
            ),
            MeetingInsight::TYPE_SENTIMENT => new ObjectSchema(
                name: 'sentiment',
                description: 'Sentimen rapat.',
                properties: [
                    new StringSchema('overall', 'Sentimen keseluruhan: positif, netral, atau negatif.'),
                    new NumberSchema('score', 'Skor -1 (negatif) sampai 1 (positif).'),
                    new StringSchema('note', 'Penjelasan singkat.'),
                    new ArraySchema('per_speaker', 'Sentimen per pembicara.', new ObjectSchema(
                        name: 'speaker_sentiment',
                        description: 'Sentimen satu pembicara.',
                        properties: [
                            new StringSchema('speaker', 'Nama pembicara seperti di transkrip.'),
                            new StringSchema('sentiment', 'positif, netral, atau negatif.'),
                            new StringSchema('note', 'Alasan singkat.'),
                        ],
                        requiredFields: ['speaker', 'sentiment', 'note'],
                    )),
                    new ArraySchema('tension_points', 'Momen ketegangan atau ketidaksepakatan.', new StringSchema('point', 'Satu momen.')),
                ],
                requiredFields: ['overall', 'score', 'note', 'per_speaker', 'tension_points'],
            ),
            MeetingInsight::TYPE_FOLLOW_UP => new ObjectSchema(
                name: 'follow_up',
                description: 'Rekomendasi tindak lanjut.',
                properties: [
                    new ArraySchema('recommendations', 'Daftar rekomendasi.', new ObjectSchema(
                        name: 'recommendation',
                        description: 'Satu rekomendasi.',
                        properties: [
                            new StringSchema('action', 'Tindakan yang disarankan.'),
                            new StringSchema('owner', 'Pemilik tindakan.'),
                            new StringSchema('deadline', 'Tenggat, boleh relatif seperti "pekan depan".'),
                            new StringSchema('priority', 'tinggi, sedang, atau rendah.'),
                        ],
                        requiredFields: ['action', 'owner', 'deadline', 'priority'],
                    )),
                ],
                requiredFields: ['recommendations'],
            ),
            default => throw new RuntimeException('Jenis analisis tidak dikenal.'),
        };
    }

    /**
     * Feed the stored key into the provider config for this request, the same
     * way the chat assistant does.
     *
     * @param  array{provider: string, summary: string, pro: string, embedding: string, api_key: string}  $models
     */
    private function applyKey(array $models): void
    {
        if ($models['api_key'] !== '') {
            config(["prism.providers.{$models['provider']}.api_key" => $models['api_key']]);
        }
    }

    /**
     * Bill the automatic pass to whoever pressed record. A meeting with no
     * creator left (deleted account) is not charged to anybody.
     */
    private function charge(Meeting $meeting, int $tokens, string $source): void
    {
        if ($tokens <= 0) {
            return;
        }

        $meeting->increment('summary_tokens', $tokens);

        $user = $meeting->creator;

        if ($user !== null) {
            $this->tokens->debit($user, $tokens, $source);
        }
    }

    private function usageTokens(?int $promptTokens, ?int $completionTokens): int
    {
        return max(0, (int) $promptTokens + (int) $completionTokens);
    }

    private function meetingDate(Meeting $meeting): string
    {
        return ($meeting->started_at ?? $meeting->created_at)?->translatedFormat('d F Y H:i') ?? '-';
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}

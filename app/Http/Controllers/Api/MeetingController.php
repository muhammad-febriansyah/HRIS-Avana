<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMeetingTranscriptJob;
use App\Models\AiSetting;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingInsight;
use App\Models\MeetingParticipant;
use App\Models\MeetingSegment;
use App\Services\AiTokenService;
use App\Services\MeetingTranscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The phone's half of meeting recording.
 *
 * The app records, opens a socket to the speech provider with a credential it
 * asks for here, and posts the finished text back as it arrives. Audio never
 * comes through this controller — only the transcript, which is also what the
 * billing is counted from. See {@see MeetingTranscriber}.
 *
 * Read access on this API is attendance-based only: an employee sees the
 * meetings they recorded or sat in, plus anything the company opened to
 * everyone. The HR-wide view lives on the web, behind `meeting.view`.
 */
class MeetingController extends Controller
{
    public function __construct(
        private readonly MeetingTranscriber $transcriber,
        private readonly AiTokenService $tokens,
    ) {}

    /**
     * Whether recording is available, plus the caller's token headroom — the
     * app disables the record button rather than failing at the microphone.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $gate = $this->tokens->canChat($user);
        $stt = AiSetting::current()->resolvedStt();

        return response()->json([
            'data' => [
                'available' => $stt !== null,
                'can_record' => $stt !== null && $gate->allowed,
                'blocked_reason' => $gate->allowed ? null : $gate->reason,
                'blocked_message' => $gate->allowed ? null : $gate->message,
                'max_minutes' => $stt['max_minutes'] ?? null,
                'token_cost_per_minute' => $stt['token_cost_per_minute'] ?? null,
                'keep_audio' => $stt['keep_audio'] ?? false,
                'usage' => $this->tokens->remainingForUser($user),
            ],
        ]);
    }

    /**
     * The caller's readable meetings, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = Meeting::query()
            ->forTenant($user->tenant_id)
            ->readableBy($user->employee?->id, $user->id, false)
            ->with('participants.employee:id,full_name')
            ->when($data['search'] ?? null, fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Meeting $meeting): array => $this->shapeListItem($meeting))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Start a recording. The token gate runs before the microphone does, so a
     * company that has run dry is told now rather than after 40 minutes.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        // Not every account that may record has an employee record (an HR
        // admin logging in from the app, for instance) — read access already
        // follows `created_by`, so attendance here is a bonus, not a
        // requirement.
        $employee = $user->employee;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer'],
        ]);

        $stt = AiSetting::current()->resolvedStt();

        if ($stt === null) {
            return response()->json(['message' => 'Perekaman rapat sedang tidak aktif.'], 422);
        }

        $gate = $this->tokens->canChat($user);

        if (! $gate->allowed) {
            return response()->json([
                'message' => $gate->message,
                'reason' => $gate->reason,
            ], 422);
        }

        $meeting = Meeting::create([
            'tenant_id' => $user->tenant_id,
            'created_by' => $user->id,
            'title' => $data['title'],
            'location' => $data['location'] ?? null,
            'source' => 'mobile_live',
            'status' => Meeting::STATUS_RECORDING,
            'visibility' => Meeting::VISIBILITY_PARTICIPANTS,
            'started_at' => now(),
            'language' => $stt['language'],
            'stt_model' => $stt['model'],
        ]);

        // Whoever pressed record attended it, whether they ticked themselves or
        // not — and attendance is what grants them read access afterwards. Not
        // added when the account has no employee record; `created_by` already
        // covers their own access in that case.
        $participantIds = collect($data['participant_ids'] ?? [])
            ->when($employee !== null, fn ($ids) => $ids->push($employee->id))
            ->unique();

        $valid = Employee::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('id', $participantIds)
            ->pluck('id');

        foreach ($valid as $employeeId) {
            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'tenant_id' => $user->tenant_id,
                'employee_id' => $employeeId,
            ]);
        }

        return response()->json(['data' => $this->shapeDetail($meeting->fresh())], 201);
    }

    /**
     * A short-lived credential for the listening socket, plus its parameters.
     */
    public function sttToken(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeOwner($request, $meeting);

        try {
            return response()->json(['data' => $this->transcriber->grantToken($request->user(), $meeting)]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Store a batch of finished segments and charge the audio they cover.
     *
     * Answers with `stop` when there is nothing left to fund the recording, or
     * the duration ceiling is reached; the app closes the socket on that.
     */
    public function segments(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeOwner($request, $meeting);

        $data = $request->validate([
            'elapsed_ms' => ['required', 'integer', 'min:0'],
            'segments' => ['present', 'array'],
            'segments.*.start_ms' => ['required', 'integer', 'min:0'],
            'segments.*.end_ms' => ['required', 'integer', 'min:0'],
            'segments.*.speaker' => ['nullable', 'integer', 'min:0', 'max:64'],
            'segments.*.text' => ['required', 'string'],
        ]);

        if ($meeting->status !== Meeting::STATUS_RECORDING) {
            return response()->json([
                'data' => ['stop' => true, 'reason' => 'not_recording', 'message' => 'Rapat ini sudah tidak dalam status merekam.'],
            ], 409);
        }

        $result = $this->transcriber->ingestSegments(
            $request->user(),
            $meeting,
            $data['segments'],
            $data['elapsed_ms'],
        );

        // 409 is the app's signal to close the socket. A 200 with a flag would
        // be quietly ignored by a retry loop that only checks the status code.
        return response()->json(['data' => $result], $result['stop'] ? 409 : 200);
    }

    /**
     * Stop recording and queue the summary.
     */
    public function stop(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeOwner($request, $meeting);

        $data = $request->validate([
            'elapsed_ms' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($meeting->status === Meeting::STATUS_RECORDING) {
            $meeting->update([
                'status' => Meeting::STATUS_PROCESSING,
                'ended_at' => now(),
                'duration_ms' => max($meeting->duration_ms, (int) ($data['elapsed_ms'] ?? 0)),
            ]);

            ProcessMeetingTranscriptJob::dispatch($meeting->id);
        }

        return response()->json(['data' => $this->shapeDetail($meeting->fresh())]);
    }

    /**
     * The optional keepsake copy of the recording.
     *
     * Sent after the socket closes, so a slow upload never delays the
     * transcript — which the server already has in full either way.
     */
    public function audio(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeOwner($request, $meeting);

        $request->validate([
            'audio' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp4,audio/aac,audio/m4a,audio/x-m4a,audio/wav,audio/x-wav,audio/webm,video/mp4', 'max:204800'],
        ]);

        $stt = AiSetting::current()->resolvedStt();

        if ($stt === null || ! $stt['keep_audio']) {
            return response()->json(['message' => 'Penyimpanan audio rapat dimatikan.'], 422);
        }

        $file = $request->file('audio');

        $path = $file->storeAs(
            sprintf('meetings/%d', $meeting->tenant_id),
            sprintf('%s.%s', (string) Str::ulid(), $file->getClientOriginalExtension() ?: 'm4a'),
        );

        if ($meeting->audio_path !== null) {
            Storage::delete($meeting->audio_path);
        }

        $meeting->update(['audio_path' => $path, 'audio_size' => $file->getSize()]);

        return response()->json(['data' => ['audio_size' => $meeting->audio_size]]);
    }

    /**
     * Transcript, summary and follow-ups of one readable meeting.
     */
    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();

        $readable = Meeting::query()
            ->whereKey($meeting->id)
            ->forTenant($user->tenant_id)
            ->readableBy($user->employee?->id, $user->id, false)
            ->exists();

        abort_unless($readable, 404);

        return response()->json(['data' => $this->shapeDetail($meeting, withTranscript: true)]);
    }

    /**
     * Only the person recording may feed or stop a meeting: the segments are
     * billed to them, and a second phone posting into the same meeting would
     * scramble the transcript's offsets.
     */
    private function authorizeOwner(Request $request, Meeting $meeting): void
    {
        abort_unless(
            $meeting->tenant_id === $request->user()->tenant_id && $meeting->created_by === $request->user()->id,
            403,
            'Rapat ini bukan rekaman Anda.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeListItem(Meeting $meeting): array
    {
        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'location' => $meeting->location,
            'status' => $meeting->status,
            'started_at' => $meeting->started_at?->toIso8601String(),
            'duration_ms' => $meeting->duration_ms,
            'duration_minutes' => $meeting->durationMinutes(),
            'has_summary' => trim((string) $meeting->summary) !== '',
            'participants' => $meeting->participants
                ->map(fn (MeetingParticipant $participant): ?string => $participant->employee?->full_name)
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeDetail(Meeting $meeting, bool $withTranscript = false): array
    {
        $meeting->loadMissing([
            'participants.employee:id,full_name',
            'speakers.employee:id,full_name',
            'actionItems.assignee:id,full_name',
            'insights',
        ]);

        $payload = $this->shapeListItem($meeting) + [
            'summary' => $meeting->summary,
            'decisions' => $meeting->decisions ?? [],
            'failure_reason' => $meeting->failure_reason,
            // The deep analyses, read-only here: they are written on the web,
            // where somebody chose to spend the tokens on them. Only the ones
            // already paid for are sent, so the phone never shows five empty
            // headings promising things nobody asked for.
            'insights' => $meeting->insights
                ->map(fn (MeetingInsight $insight): array => [
                    'type' => $insight->type,
                    'label' => MeetingInsight::label($insight->type),
                    'payload' => $insight->payload,
                    'generated_at' => $insight->generated_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'action_items' => $meeting->actionItems
                ->map(fn (MeetingActionItem $item): array => [
                    'id' => $item->id,
                    'text' => $item->text,
                    'assignee' => $item->assignee?->full_name,
                    'due_date' => $item->due_date?->toDateString(),
                    'status' => $item->status,
                ])
                ->all(),
        ];

        if (! $withTranscript) {
            return $payload;
        }

        return $payload + [
            'transcript' => $meeting->segments()
                ->get()
                ->map(fn (MeetingSegment $segment): array => [
                    'timecode' => $segment->timecode(),
                    'start_ms' => $segment->start_ms,
                    'speaker' => $meeting->speakerName($segment->speaker_index),
                    'text' => $segment->text,
                ])
                ->all(),
        ];
    }
}

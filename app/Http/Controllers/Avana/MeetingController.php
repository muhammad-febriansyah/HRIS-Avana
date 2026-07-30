<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMeetingTranscriptJob;
use App\Models\AiSetting;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingInsight;
use App\Models\MeetingParticipant;
use App\Models\MeetingSegment;
use App\Models\MeetingSpeaker;
use App\Models\User;
use App\Services\MeetingSummarizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reviewing what the phone recorded: the transcript, who said it, the summary,
 * the follow-ups, and the deep analyses.
 *
 * Recording happens in the app; everything a person does afterwards happens
 * here, because a two-hour transcript is not something to read on a phone.
 */
class MeetingController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'meeting';

    /**
     * Private disk holding the optional audio copies; they are served through
     * {@see self::download()} so a recording never gets a public URL.
     */
    private const DISK = 'local';

    public function __construct(private readonly MeetingSummarizer $summarizer) {}

    /**
     * The tenant's recordings, with the token they have cost so far.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        /** @var User $user */
        $user = $request->user();
        $tenantId = (int) $user->tenant_id;

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:16'],
        ]);

        $meetings = $this->readable($request)
            ->with(['participants.employee:id,full_name', 'creator:id,name'])
            ->withCount('actionItems')
            ->when($data['search'] ?? null, fn ($query, string $search) => $query->where(fn ($group) => $group
                ->where('title', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")))
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Meeting $meeting): array => $this->transformListItem($meeting));

        return Inertia::render('avana/rapat/index', [
            'meetings' => $meetings,
            'filters' => ['search' => $data['search'] ?? '', 'status' => $data['status'] ?? ''],
            'kpis' => [
                'total' => $meetings->count(),
                'ready' => $meetings->where('status', Meeting::STATUS_READY)->count(),
                'processing' => $meetings->whereIn('status', [Meeting::STATUS_RECORDING, Meeting::STATUS_PROCESSING])->count(),
                'minutes' => $meetings->sum('duration_minutes'),
                'tokens' => (int) Meeting::forTenant($tenantId)->sum('summary_tokens'),
            ],
            'recorderReady' => AiSetting::current()->resolvedStt() !== null,
        ]);
    }

    /**
     * One meeting: transcript, speakers to confirm, summary, follow-ups and any
     * premium analyses already paid for.
     */
    public function show(Request $request, Meeting $meeting): Response
    {
        $this->ensureCan($request, 'view');
        $this->ensureReadable($request, $meeting);

        $meeting->load([
            'participants.employee:id,full_name',
            'speakers.employee:id,full_name',
            'actionItems.assignee:id,full_name',
            'insights',
            'creator:id,name',
        ]);

        $segments = $meeting->segments()->get();

        return Inertia::render('avana/rapat/detail', [
            'meeting' => $this->transformListItem($meeting) + [
                'summary' => $meeting->summary,
                'summary_model' => $meeting->summary_model,
                'summary_tokens' => (int) $meeting->summary_tokens,
                'failure_reason' => $meeting->failure_reason,
                'visibility' => $meeting->visibility,
                'has_audio' => $meeting->audio_path !== null,
                'ended_at' => $meeting->ended_at?->toIso8601String(),
            ],
            'transcript' => $segments
                ->map(fn (MeetingSegment $segment): array => [
                    'id' => $segment->id,
                    'timecode' => $segment->timecode(),
                    'start_ms' => $segment->start_ms,
                    'speaker_index' => $segment->speaker_index,
                    'speaker' => $meeting->speakerName($segment->speaker_index),
                    'text' => $segment->text,
                ])
                ->all(),
            'speakers' => $meeting->speakers
                ->map(fn (MeetingSpeaker $speaker): array => [
                    'speaker_index' => $speaker->speaker_index,
                    'employee_id' => $speaker->employee_id,
                    'display_name' => $speaker->display_name,
                    'resolved_name' => $speaker->resolvedName(),
                    'guessed_by_ai' => $speaker->guessed_by_ai,
                    'confidence' => $speaker->confidence,
                    'lines' => $segments->where('speaker_index', $speaker->speaker_index)->count(),
                ])
                ->values()
                ->all(),
            'actionItems' => $meeting->actionItems
                ->map(fn (MeetingActionItem $item): array => [
                    'id' => $item->id,
                    'text' => $item->text,
                    'assignee_employee_id' => $item->assignee_employee_id,
                    'assignee' => $item->assignee?->full_name,
                    'due_date' => $item->due_date?->toDateString(),
                    'status' => $item->status,
                    'source' => $item->source,
                ])
                ->all(),
            'insights' => collect(MeetingInsight::LABELS)
                ->map(function (string $label, string $type) use ($meeting): array {
                    $insight = $meeting->insights->firstWhere('type', $type);

                    return [
                        'type' => $type,
                        'label' => $label,
                        'payload' => $insight?->payload,
                        'model' => $insight?->model,
                        'tokens' => (int) ($insight?->tokens ?? 0),
                        'generated_at' => $insight?->generated_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all(),
            'employees' => Employee::query()
                ->where('tenant_id', $meeting->tenant_id)
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(['id', 'full_name'])
                ->all(),
            'can' => [
                'update' => $this->allows($request, 'update'),
                'archive' => $this->allows($request, 'archive'),
            ],
            'proModel' => AiSetting::current()->resolvedMeetingModels()['pro'],
        ]);
    }

    /**
     * Confirm (or correct) who a speaker number was.
     *
     * Saving a name clears the AI-guess flag: the point of the panel is that a
     * person vouched for it, and the transcript then quotes them by name
     * everywhere without a caveat.
     */
    public function updateSpeakers(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureReadable($request, $meeting);

        $data = $request->validate([
            'speakers' => ['required', 'array'],
            'speakers.*.speaker_index' => ['required', 'integer', 'min:0'],
            'speakers.*.employee_id' => ['nullable', 'integer'],
            'speakers.*.display_name' => ['nullable', 'string', 'max:120'],
        ]);

        $validEmployeeIds = Employee::query()
            ->where('tenant_id', $meeting->tenant_id)
            ->pluck('id')
            ->flip();

        foreach ($data['speakers'] as $row) {
            $employeeId = $row['employee_id'] ?? null;

            if ($employeeId !== null && ! $validEmployeeIds->has($employeeId)) {
                $employeeId = null;
            }

            MeetingSpeaker::query()
                ->where('meeting_id', $meeting->id)
                ->where('speaker_index', (int) $row['speaker_index'])
                ->update([
                    'employee_id' => $employeeId,
                    'display_name' => ($row['display_name'] ?? null) ?: null,
                    'guessed_by_ai' => false,
                    'confidence' => null,
                ]);

            // A named speaker who attended should also be listed as attending —
            // attendance is what grants them access to read this afterwards.
            if ($employeeId !== null) {
                MeetingParticipant::firstOrCreate(
                    ['meeting_id' => $meeting->id, 'employee_id' => $employeeId],
                    ['tenant_id' => $meeting->tenant_id],
                );
            }
        }

        return back()->with('success', 'Nama pembicara disimpan');
    }

    /**
     * Run one of the premium analyses, or return the stored one untouched.
     *
     * These are the expensive half of the feature, so a stored answer is reused
     * until somebody explicitly asks for a fresh one — see the `refresh` flag.
     */
    public function generateInsight(Request $request, Meeting $meeting, string $type): RedirectResponse
    {
        $this->ensureCan($request, 'view');
        $this->ensureReadable($request, $meeting);

        $data = $request->validate([
            'refresh' => ['nullable', 'boolean'],
        ]);

        // The analysis is named by the route segment, so it is checked here
        // rather than by a body rule.
        abort_unless(in_array($type, MeetingInsight::types(), true), 404);

        $existing = $meeting->insights()->where('type', $type)->first();

        if ($existing !== null && ! ($data['refresh'] ?? false)) {
            return back();
        }

        try {
            $this->summarizer->generateInsight($meeting, $request->user(), $type);
        } catch (RuntimeException $e) {
            return back()->withErrors(['insight' => $e->getMessage()]);
        }

        return back()->with('success', MeetingInsight::label($type).' selesai dibuat');
    }

    /**
     * Add a follow-up a person spotted that the summary missed.
     */
    public function storeActionItem(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureReadable($request, $meeting);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
            'assignee_employee_id' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date'],
        ]);

        MeetingActionItem::create([
            'meeting_id' => $meeting->id,
            'tenant_id' => $meeting->tenant_id,
            'text' => $data['text'],
            'assignee_employee_id' => $this->validEmployeeId($meeting, $data['assignee_employee_id'] ?? null),
            'due_date' => $data['due_date'] ?? null,
            'status' => MeetingActionItem::STATUS_OPEN,
            'source' => MeetingActionItem::SOURCE_MANUAL,
            'sort_order' => (int) $meeting->actionItems()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Tindak lanjut ditambahkan');
    }

    /**
     * Edit a follow-up: its wording, owner, deadline or done state.
     */
    public function updateActionItem(Request $request, Meeting $meeting, MeetingActionItem $actionItem): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureReadable($request, $meeting);
        abort_unless($actionItem->meeting_id === $meeting->id, 404);

        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:1000'],
            'assignee_employee_id' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in([MeetingActionItem::STATUS_OPEN, MeetingActionItem::STATUS_DONE])],
        ]);

        $actionItem->update(array_filter([
            'text' => $data['text'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($value): bool => $value !== null) + [
            'assignee_employee_id' => $this->validEmployeeId($meeting, $data['assignee_employee_id'] ?? null),
            'due_date' => $data['due_date'] ?? null,
        ]);

        return back()->with('success', 'Tindak lanjut diperbarui');
    }

    public function destroyActionItem(Request $request, Meeting $meeting, MeetingActionItem $actionItem): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureReadable($request, $meeting);
        abort_unless($actionItem->meeting_id === $meeting->id, 404);

        $actionItem->delete();

        return back()->with('success', 'Tindak lanjut dihapus');
    }

    /**
     * Who may read the transcript: the people in the room, or the whole company.
     */
    public function updateVisibility(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureReadable($request, $meeting);

        $data = $request->validate([
            'visibility' => ['required', Rule::in([Meeting::VISIBILITY_PARTICIPANTS, Meeting::VISIBILITY_TENANT])],
        ]);

        $meeting->update(['visibility' => $data['visibility']]);

        return back()->with('success', 'Akses rapat diperbarui');
    }

    /**
     * Re-run the summary — for a recording that failed, or one whose speaker
     * names were corrected after the fact.
     */
    public function reprocess(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureReadable($request, $meeting);

        abort_if($meeting->status === Meeting::STATUS_RECORDING, 422, 'Rapat masih merekam.');

        $meeting->update(['status' => Meeting::STATUS_PROCESSING, 'failure_reason' => null]);

        ProcessMeetingTranscriptJob::dispatch($meeting->id);

        return back()->with('success', 'Rapat diproses ulang. Ringkasan akan diperbarui.');
    }

    /**
     * The stored audio, streamed rather than linked so a private recording never
     * has a guessable public URL.
     */
    public function download(Request $request, Meeting $meeting): StreamedResponse
    {
        $this->ensureCan($request, 'view');
        $this->ensureReadable($request, $meeting);

        abort_if($meeting->audio_path === null || ! Storage::disk(self::DISK)->exists($meeting->audio_path), 404);

        return Storage::disk(self::DISK)->download(
            $meeting->audio_path,
            $meeting->title.'.'.pathinfo($meeting->audio_path, PATHINFO_EXTENSION),
        );
    }

    public function destroy(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureReadable($request, $meeting);

        if ($meeting->audio_path !== null) {
            Storage::disk(self::DISK)->delete($meeting->audio_path);
        }

        $meeting->delete();

        return redirect()
            ->route('avana.rapat')
            ->with('success', 'Rapat dihapus');
    }

    /**
     * @return array<string, mixed>
     */
    private function transformListItem(Meeting $meeting): array
    {
        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'location' => $meeting->location,
            'status' => $meeting->status,
            'started_at' => $meeting->started_at?->toIso8601String(),
            'duration_minutes' => $meeting->durationMinutes(),
            'recorded_by' => $meeting->creator?->name,
            'action_item_count' => $meeting->action_items_count ?? $meeting->actionItems()->count(),
            'has_summary' => trim((string) $meeting->summary) !== '',
            'participants' => $meeting->participants
                ->map(fn (MeetingParticipant $participant): ?string => $participant->employee?->full_name)
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * Meetings this user may read. `meeting.view` sees the company's whole
     * library; anyone else sees what they recorded or attended.
     *
     * @return Builder<Meeting>
     */
    private function readable(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return Meeting::query()
            ->forTenant((int) $user->tenant_id)
            ->readableBy($user->employee?->id, $user->id, $this->allows($request, 'view'));
    }

    private function ensureReadable(Request $request, Meeting $meeting): void
    {
        abort_unless(
            $meeting->tenant_id === $request->user()->tenant_id
                && $this->readable($request)->whereKey($meeting->id)->exists(),
            404,
        );
    }

    private function validEmployeeId(Meeting $meeting, ?int $employeeId): ?int
    {
        if ($employeeId === null) {
            return null;
        }

        return Employee::query()
            ->where('tenant_id', $meeting->tenant_id)
            ->whereKey($employeeId)
            ->value('id');
    }

    private function ensureCan(Request $request, string $action): void
    {
        abort_unless($this->allows($request, $action), 403);
    }

    private function allows(Request $request, string $action): bool
    {
        /** @var User $user */
        $user = $request->user();

        return $user->isSuperAdmin() || $user->hasPermissionTo(self::MODULE.'.'.$action);
    }
}

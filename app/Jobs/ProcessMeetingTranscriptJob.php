<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Services\MeetingSummarizer;
use App\Support\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Summarises a meeting once the phone has stopped recording.
 *
 * Off the request cycle because it makes several model calls and embeds the
 * whole transcript: the phone's "stop" tap must return immediately, and the
 * person who tapped it is usually walking out of the room by then. They are told
 * it is ready by push notification instead.
 */
class ProcessMeetingTranscriptJob implements ShouldQueue
{
    use Queueable;

    /**
     * A long meeting means several provider calls, so the timeout is generous.
     */
    public int $timeout = 900;

    /**
     * One retry only. A second failure is a real problem (bad key, provider
     * down), and re-running spends tokens again on work that already failed.
     */
    public int $tries = 2;

    public function __construct(private readonly int $meetingId) {}

    public function handle(MeetingSummarizer $summarizer): void
    {
        $meeting = Meeting::query()->find($this->meetingId);

        if ($meeting === null) {
            return;
        }

        $summarizer->process($meeting);

        Notifier::meetingSummaryReady($meeting->refresh());
    }

    /**
     * Both attempts are spent. Say so on the meeting itself.
     *
     * `process()` marks its own failures, but it can only do that for the ones
     * it catches: a worker killed mid-call, a timeout above it, an exception on
     * the way in. Without this the row keeps saying "AI sedang menyusun
     * ringkasan" for good, and a summary that will never arrive is indis-
     * tinguishable on screen from one that is still being written.
     */
    public function failed(?Throwable $e): void
    {
        $meeting = Meeting::query()->find($this->meetingId);

        if ($meeting === null || $meeting->status !== Meeting::STATUS_PROCESSING) {
            return;
        }

        Log::error('Meeting summary job failed outright', [
            'meeting_id' => $this->meetingId,
            'message' => $e?->getMessage(),
        ]);

        $meeting->update([
            'status' => Meeting::STATUS_FAILED,
            'failure_reason' => 'Ringkasan gagal dibuat. Transkrip tetap tersimpan — coba proses ulang dari detail rapat.',
        ]);
    }
}

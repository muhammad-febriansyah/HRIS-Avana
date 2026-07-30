<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMeetingTranscriptJob;
use App\Models\Meeting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('avana:close-stale-meetings {--minutes=} {--dry-run}')]
#[Description('Close meetings left in the recording state by a phone that never came back.')]
class CloseStaleMeetings extends Command
{
    /**
     * How long a recording may sit untouched before it is treated as abandoned.
     * Comfortably past the longest recording the settings allow, so a genuinely
     * long meeting in progress is never cut short.
     */
    private const DEFAULT_GRACE_MINUTES = 240;

    /**
     * A phone that loses its connection, is force-closed, or fails to open the
     * microphone leaves its meeting at `recording` — nothing else ever moves it
     * on, so it sits in the list claiming to be live forever.
     *
     * Anything it did manage to transcribe is still worth summarising, so these
     * are handed to the normal processing path rather than deleted. A recording
     * that captured nothing lands as failed, which is what happened.
     */
    public function handle(): int
    {
        $minutes = (int) ($this->option('minutes') ?: self::DEFAULT_GRACE_MINUTES);
        $cutoff = Carbon::now()->subMinutes(max(1, $minutes));
        $dryRun = (bool) $this->option('dry-run');

        $stale = Meeting::query()
            ->where('status', Meeting::STATUS_RECORDING)
            ->where(function ($query) use ($cutoff): void {
                $query->where('started_at', '<', $cutoff)
                    ->orWhereNull('started_at');
            })
            ->orderBy('id')
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No stale recordings.');

            return self::SUCCESS;
        }

        foreach ($stale as $meeting) {
            $this->line(sprintf(
                '#%d %s (tenant %d, dimulai %s, %d segmen)',
                $meeting->id,
                $meeting->title,
                $meeting->tenant_id,
                $meeting->started_at?->toDateTimeString() ?? '-',
                $meeting->segments()->count(),
            ));

            if ($dryRun) {
                continue;
            }

            $meeting->update([
                'status' => Meeting::STATUS_PROCESSING,
                'ended_at' => $meeting->ended_at ?? now(),
            ]);

            ProcessMeetingTranscriptJob::dispatch($meeting->id);
        }

        $this->info(sprintf(
            '%d rekaman %s.',
            $stale->count(),
            $dryRun ? 'akan ditutup (dry run)' : 'ditutup dan diproses',
        ));

        return self::SUCCESS;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One finished utterance of a meeting, as the speech provider settled it:
 * a speaker number, an offset from the start of the recording, and the words.
 */
final class MeetingSegment extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speaker_index' => 'integer',
            'start_ms' => 'integer',
            'end_ms' => 'integer',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * The offset as `mm:ss`, for a transcript a person reads.
     */
    public function timecode(): string
    {
        $seconds = intdiv($this->start_ms, 1000);

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}

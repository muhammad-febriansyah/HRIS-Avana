<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A speaker number from the recording, and who it turned out to be.
 *
 * Diarization only says "this is a different voice", never whose. The AI
 * proposes a name from what was said ("saya dari Finance...") and the row is
 * kept flagged as a guess until a person confirms it, so a summary never
 * attributes a decision to somebody on the strength of a hunch alone.
 */
final class MeetingSpeaker extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speaker_index' => 'integer',
            'guessed_by_ai' => 'boolean',
            'confidence' => 'float',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The best name available: the linked employee, the typed/guessed label, or
     * the provider's bare numbering.
     */
    public function resolvedName(): string
    {
        if ($this->relationLoaded('employee') && $this->employee !== null) {
            return $this->employee->full_name;
        }

        if ($this->employee_id !== null) {
            return $this->employee?->full_name ?? 'Pembicara '.($this->speaker_index + 1);
        }

        $label = trim((string) $this->display_name);

        return $label !== '' ? $label : 'Pembicara '.($this->speaker_index + 1);
    }
}

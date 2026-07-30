<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee listed as attending a meeting. Attendance is also what grants
 * read access to the transcript — see {@see Meeting::scopeReadableBy()}.
 */
final class MeetingParticipant extends Model
{
    protected $guarded = [];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

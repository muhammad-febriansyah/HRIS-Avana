<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single hit on a partner's `?ref=` link. Analytics and audit trail only —
 * no money moves off this table, only off {@see ReferralConversion}.
 */
final class ReferralClick extends Model
{
    protected $guarded = [];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}

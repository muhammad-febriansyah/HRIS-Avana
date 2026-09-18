<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RosterPlanDay extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(RosterPlan::class, 'roster_plan_id');
    }
}

<?php

namespace App\Models;

use App\Console\Commands\ReleaseReferralHolds;
use App\Services\ReferralConversionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The commission earned when a partner-attributed tenant's FIRST invoice is
 * paid. Created by {@see ReferralConversionService}, pending
 * until its hold period passes — {@see ReleaseReferralHolds}
 * is what turns a pending row into an actual ledger credit.
 */
final class ReferralConversion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_VOID = 'void';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'hold_until' => 'date',
            'approved_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

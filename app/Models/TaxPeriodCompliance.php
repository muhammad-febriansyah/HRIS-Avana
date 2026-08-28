<?php

namespace App\Models;

use App\Http\Controllers\Avana\Pph21ReportController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whether a tax period's PPh 21 was deposited to the state and reported to DJP.
 *
 * @see Pph21ReportController
 */
final class TaxPeriodCompliance extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_DONE];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'deposit_date' => 'date',
            'report_date' => 'date',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function depositDone(): bool
    {
        return $this->deposit_status === self::STATUS_DONE;
    }

    public function reportDone(): bool
    {
        return $this->report_status === self::STATUS_DONE;
    }
}

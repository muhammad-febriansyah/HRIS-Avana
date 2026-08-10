<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Diagnostic trail for face enrollment and face verification.
 *
 * Face scanning runs on the phone, so when it fails there is nothing on the
 * server to look at: the employee only sees a hint that keeps repeating. Each
 * failed (and successful) scan writes one row here with the numbers the device
 * measured, which is what makes a device-specific failure — an iPhone whose
 * capture orientation throws off the pose reading, say — diagnosable at all.
 */
final class FaceScanLog extends Model
{
    use Prunable;

    /** Enrollment flow (Daftar Wajah). */
    public const CONTEXT_ENROLL = 'enroll';

    /** Live verification scan before clocking in. */
    public const CONTEXT_VERIFY = 'verify';

    /** Server-side match performed while recording the punch. */
    public const CONTEXT_CLOCK = 'clock';

    /**
     * Flows the API accepts from the device.
     *
     * @var array<int, string>
     */
    public const CONTEXTS = [self::CONTEXT_ENROLL, self::CONTEXT_VERIFY, self::CONTEXT_CLOCK];

    /**
     * How the scan ended: `ok` captured, `fail` retryable, `blocked` refused.
     *
     * @var array<int, string>
     */
    public const OUTCOMES = ['ok', 'fail', 'blocked'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'step' => 'integer',
        ];
    }

    /**
     * Drop entries older than 60 days. These are troubleshooting breadcrumbs,
     * not an audit trail — a scan loop can write several rows a minute, so
     * keeping them forever would cost far more than it is worth.
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(60));
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

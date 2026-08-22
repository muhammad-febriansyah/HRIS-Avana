<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PerformanceReview extends Model
{
    use Auditable, HasFactory, HasPublicId;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'self_score' => 'decimal:2',
            'manager_score' => 'decimal:2',
            'final_score' => 'decimal:2',
            'calibrated_score' => 'decimal:2',
            'calibrated_at' => 'datetime',
            'manager_scored_at' => 'datetime',
            'is_legacy' => 'boolean',
            'review_date' => 'date',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        // Qualified: scopeLatestFirst() joins performance_cycles, which also has
        // a tenant_id, and an unqualified column is ambiguous once it does.
        return $query->where('performance_reviews.tenant_id', $tenantId);
    }

    /**
     * The single definition of "this rating may be used downstream": completed,
     * not quarantined legacy data, and carrying a full calibration record.
     *
     * Every consumer that pays money, feeds analytics, or reports a rating
     * (incentives, attrition, Report Studio, HAV) must read through this scope
     * — a review sitting in calibration, or one reopened after being finalized,
     * is deliberately invisible to them.
     *
     * `calibrated_by` is not part of the test: its foreign key nulls the column
     * when the calibrator's user account is deleted, and losing the name of who
     * signed a rating must not retroactively unpublish the rating itself.
     * `calibrated_at` is the durable evidence that calibration happened.
     */
    public function scopePublishable(Builder $query): Builder
    {
        // Qualified for the same reason as scopeForTenant(): performance_cycles
        // has its own `status`, and scopeLatestFirst() joins it.
        return $query->where('performance_reviews.status', 'completed')
            ->where('performance_reviews.is_legacy', false)
            ->whereNotNull('performance_reviews.final_score')
            ->whereNotNull('performance_reviews.calibrated_score')
            ->whereNotNull('performance_reviews.calibrated_at');
    }

    /**
     * "The employee's current rating", defined once.
     *
     * Every module used to pick its own ordering — some by `id`, some by
     * `review_date`, attrition by `cycle.period_end` — so the same employee
     * could be "latest rated" at three different scores at the same moment.
     * The appraisal period is what actually orders reviews, with the review
     * date and then the id only breaking ties.
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query
            ->leftJoin('performance_cycles', 'performance_cycles.id', '=', 'performance_reviews.cycle_id')
            ->orderByDesc('performance_cycles.period_end')
            ->orderByDesc('performance_reviews.review_date')
            ->orderByDesc('performance_reviews.id')
            ->select('performance_reviews.*');
    }

    /**
     * Row-level counterpart of {@see scopePublishable()}.
     */
    public function isPublishable(): bool
    {
        return $this->status === 'completed'
            && ! $this->is_legacy
            && $this->final_score !== null
            && $this->calibrated_score !== null
            && $this->calibrated_at !== null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(PerformanceFeedback::class, 'review_id');
    }

    public function kpiItems(): HasMany
    {
        return $this->hasMany(PerformanceKpiItem::class, 'review_id');
    }

    /**
     * Snapshots of this review's scores, taken each time it was reopened.
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PerformanceReviewRevision::class, 'review_id');
    }
}

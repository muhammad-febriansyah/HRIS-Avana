<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Applicant extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'applied_date' => 'date',
            'interview_at' => 'datetime',
            'offered_at' => 'datetime',
            'offer_start_date' => 'date',
            'offer_salary' => 'decimal:2',
            'ai_confidence' => 'integer',
            'blacklisted' => 'boolean',
            'onboarded_at' => 'datetime',
            'offer_valid_until' => 'date',
            'screening_score' => 'integer',
            'screened_at' => 'datetime',
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

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    /**
     * Who signed off the administrative screening.
     */
    public function screenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screened_by');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ApplicantStatusLog::class)->latest('id');
    }

    public function onboardingItems(): HasMany
    {
        return $this->hasMany(ApplicantOnboardingItem::class)->orderBy('sort_order');
    }

    public function talentPool(): BelongsTo
    {
        return $this->belongsTo(TalentPool::class);
    }

    public function medicalChecks(): HasMany
    {
        return $this->hasMany(ApplicantMedicalCheck::class);
    }

    public function backgroundChecks(): HasMany
    {
        return $this->hasMany(ApplicantBackgroundCheck::class);
    }
}

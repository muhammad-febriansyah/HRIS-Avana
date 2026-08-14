<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class TrackingSession extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'attendance_id',
        'started_at',
        'ended_at',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'total_distance_meters',
        'total_duration_seconds',
        'last_location_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_location_at' => 'datetime',
            'start_latitude' => 'decimal:7',
            'start_longitude' => 'decimal:7',
            'end_latitude' => 'decimal:7',
            'end_longitude' => 'decimal:7',
            'total_distance_meters' => 'integer',
            'total_duration_seconds' => 'integer',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(TrackingLocation::class);
    }

    public function lastLocation(): HasOne
    {
        return $this->hasOne(EmployeeLastLocation::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TrackingLocation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tracking_session_id',
        'tenant_id',
        'employee_id',
        'client_uuid',
        'latitude',
        'longitude',
        'accuracy',
        'altitude',
        'speed',
        'heading',
        'is_mocked',
        'battery_level',
        'is_suspicious',
        'is_accepted',
        'suspicion_reason',
        'distance_meters',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
            'altitude' => 'decimal:2',
            'speed' => 'decimal:2',
            'heading' => 'decimal:2',
            'is_mocked' => 'boolean',
            'battery_level' => 'integer',
            'is_suspicious' => 'boolean',
            'is_accepted' => 'boolean',
            'distance_meters' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class, 'tracking_session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

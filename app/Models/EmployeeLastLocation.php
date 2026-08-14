<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeLastLocation extends Model
{
    protected $fillable = [
        'tenant_id',
        'employee_id',
        'tracking_session_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'heading',
        'battery_level',
        'is_mocked',
        'is_suspicious',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
            'speed' => 'decimal:2',
            'heading' => 'decimal:2',
            'battery_level' => 'integer',
            'is_mocked' => 'boolean',
            'is_suspicious' => 'boolean',
            'recorded_at' => 'datetime',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class, 'tracking_session_id');
    }
}

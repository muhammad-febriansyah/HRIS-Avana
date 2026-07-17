<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FieldVisit extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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

    /**
     * The employee the visit is filed under — the one the ESS app reports it
     * as, and the one every photo hangs off. Also present in {@see employees}.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Everyone who attended, the filing employee included.
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'field_visit_employees')
            ->withPivot('tenant_id')
            ->withTimestamps()
            ->orderBy('full_name');
    }

    /**
     * Replace the visit's attendees. The filing employee is always kept in the
     * set, so "who attended" never omits the person the visit belongs to.
     *
     * @param  array<int, int>  $employeeIds
     */
    public function syncAttendees(array $employeeIds): void
    {
        $ids = array_values(array_unique([(int) $this->employee_id, ...array_map('intval', $employeeIds)]));

        $this->employees()->sync(
            collect($ids)
                ->mapWithKeys(fn (int $id): array => [$id => ['tenant_id' => $this->tenant_id]])
                ->all(),
        );
    }

    public function photos(): HasMany
    {
        return $this->hasMany(FieldVisitPhoto::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(FieldVisitTask::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * How far through the tasklist this visit is.
     *
     * @return array{done: int, total: int}
     */
    public function taskProgress(): array
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();

        return [
            'done' => $tasks->where('is_done', true)->count(),
            'total' => $tasks->count(),
        ];
    }
}

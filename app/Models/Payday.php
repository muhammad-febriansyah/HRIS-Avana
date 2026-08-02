<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payday group: when a set of employees is paid, and which attendance window
 * their pay is computed from.
 */
final class Payday extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pay_day' => 'integer',
            'cut_off_start_day' => 'integer',
            'cut_off_end_day' => 'integer',
            'is_active' => 'boolean',
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

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * The date wages land for a payroll month. A group paid at month end
     * follows the month's own length; otherwise the configured day is clamped
     * so "the 31st" still resolves in February.
     */
    public function payDateFor(CarbonInterface $month): CarbonInterface
    {
        $end = $month->copy()->endOfMonth();

        if ($this->pay_mode === 'end_of_month' || $this->pay_day === null) {
            return $end->startOfDay();
        }

        return $month->copy()->startOfMonth()->addDays(min((int) $this->pay_day, $end->day) - 1);
    }

    /**
     * "Tanggal 25" / "Akhir bulan" for display.
     */
    public function payLabel(): string
    {
        return $this->pay_mode === 'end_of_month' || $this->pay_day === null
            ? 'Akhir bulan'
            : 'Tanggal '.$this->pay_day;
    }

    /**
     * "21 – 20 bulan berjalan", or null when the group inherits the Master Gaji
     * window instead of stating one.
     */
    public function cutOffLabel(): ?string
    {
        if ($this->cut_off_start_day === null || $this->cut_off_end_day === null) {
            return null;
        }

        return $this->cut_off_start_day.' – '.$this->cut_off_end_day.' bulan berjalan';
    }
}

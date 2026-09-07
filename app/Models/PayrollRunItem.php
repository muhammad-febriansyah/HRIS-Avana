<?php

namespace App\Models;

use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

final class PayrollRunItem extends Model
{
    use HasPublicId;

    /**
     * How many days before the pay date a locked payslip becomes readable by
     * the employee it belongs to — "slip terbit H-1".
     */
    public const RELEASE_LEAD_DAYS = 1;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:2',
            'total_allowance' => 'decimal:2',
            'total_deduction' => 'decimal:2',
            'bpjs_employee_total' => 'decimal:2',
            'bpjs_company_total' => 'decimal:2',
            'pph21_total' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'calculation_snapshot' => 'array',
            'released_at' => 'date',
            'release_notified_at' => 'datetime',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Only the payslips an employee is allowed to see: those whose run is
     * locked.
     *
     * A draft/calculated/approved run is still being worked on — re-running it
     * replaces every figure — so a payslip read from one is a number the
     * employee may be paid something different from. Finance reviews those in
     * the admin screens; the employee sees a slip once it is final.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereHas('run', fn (Builder $run) => $run->where('status', PayrollRun::STATUS_LOCKED));
    }

    /**
     * Whether this payslip's run is final — the figures will not move again.
     *
     * This is about the run, not about who may read it: an admin previewing a
     * PDF uses it to stamp the sheet "final" or "preview". Employee access goes
     * through {@see isReleased()}, which waits for the release date as well.
     */
    public function isPublished(): bool
    {
        return $this->run?->status === PayrollRun::STATUS_LOCKED;
    }

    /**
     * Only the payslips an employee may read *now*: a locked run whose period
     * has reached its release date, H-1 before the pay date.
     *
     * Locking can happen well before payday — the moment finance finishes. A
     * slip visible from that moment tells the employee their net weeks early,
     * which is not when the company means to hand it over.
     *
     * The date is read off the row: {@see releaseDateFor()} on the run writes
     * it from the employee's own payday group, which the period's single
     * pay_date cannot stand in for once two groups are paid on different days.
     * A null means no pay date was known, so locking alone releases it.
     */
    public function scopeReleased(Builder $query): Builder
    {
        return $query
            ->published()
            ->where(fn (Builder $item) => $item
                ->whereNull('released_at')
                ->orWhereDate('released_at', '<=', Carbon::today()));
    }

    /**
     * The release date a pay date implies: H-1 before it. Null in, null out —
     * a run with no pay date releases on lock.
     *
     * The single place the H-1 arithmetic lives, so the engine, THR and the
     * payroll importer cannot drift apart on when a slip is handed over.
     */
    public static function releaseDateFrom(?string $payDate): ?string
    {
        return $payDate === null
            ? null
            : Carbon::parse($payDate)->subDays(self::RELEASE_LEAD_DAYS)->toDateString();
    }

    /** The date displayed to employees as the payslip's publication date. */
    public function issuedDate(): ?string
    {
        $releasedAt = $this->getAttribute('released_at');

        return $releasedAt !== null
            ? Carbon::parse((string) $releasedAt)->toDateString()
            : $this->created_at?->toDateString();
    }

    /**
     * Whether this payslip is both final and past its H-1 release date.
     */
    public function isReleased(): bool
    {
        return $this->isPublished()
            && ($this->released_at === null
                || $this->released_at->startOfDay()->lessThanOrEqualTo(Carbon::today()));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

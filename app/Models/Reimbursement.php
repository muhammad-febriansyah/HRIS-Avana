<?php

namespace App\Models;

use Database\Factories\ReimbursementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Reimbursement extends Model
{
    /** @use HasFactory<ReimbursementFactory> */
    use HasFactory;

    /**
     * The expense categories finance reimburses, keyed by their stored value.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'medical' => 'Medical Reimbursement',
        'komunikasi' => 'Komunikasi / Internet',
        'transportasi' => 'Transportasi',
        'operasional' => 'Pembelian Operasional',
        'representasi' => 'Biaya Representasi',
    ];

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * The Indonesian label for this reimbursement's category.
     */
    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? (string) $this->category;
    }
}

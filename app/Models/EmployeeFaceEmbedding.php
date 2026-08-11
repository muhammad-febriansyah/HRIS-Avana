<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

final class EmployeeFaceEmbedding extends Model
{
    protected $table = 'face_embeddings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'embedding_ciphertext' => 'encrypted:array',
            'enrolled_at' => 'datetime',
        ];
    }

    /**
     * @return list<float>|null
     */
    public function recognitionEmbedding(): ?array
    {
        try {
            $embedding = $this->getAttribute('embedding_ciphertext');
        } catch (Throwable) {
            return null;
        }

        return is_array($embedding) && $embedding !== []
            ? array_values(array_map(static fn (mixed $value): float => (float) $value, $embedding))
            : null;
    }

    public function isCompatibleWith(string $modelVersion): bool
    {
        $embedding = $this->recognitionEmbedding();

        return $this->model_version === $modelVersion
            && $embedding !== null
            && count($embedding) === (int) $this->dimensions;
    }

    public function enrolledAtString(): ?string
    {
        $enrolledAt = $this->getAttribute('enrolled_at');

        return $enrolledAt instanceof DateTimeInterface
            ? $enrolledAt->format('Y-m-d H:i:s')
            : null;
    }

    /**
     * @param  Builder<EmployeeFaceEmbedding>  $query
     * @return Builder<EmployeeFaceEmbedding>
     */
    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

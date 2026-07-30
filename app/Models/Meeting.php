<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One recorded meeting: the transcript the phone streamed in, and the summary
 * and analyses the AI derived from it.
 */
final class Meeting extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** The phone is streaming; segments are still arriving. */
    public const STATUS_RECORDING = 'recording';

    /** Recording stopped; the summary job is running. */
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    /** Only the recorder, the listed participants and `meeting.view` holders. */
    public const VISIBILITY_PARTICIPANTS = 'participants';

    /** Any employee of the company may read it. */
    public const VISIBILITY_TENANT = 'tenant';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_ms' => 'integer',
            'billed_ms' => 'integer',
            'audio_size' => 'integer',
            'summary_tokens' => 'integer',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Meetings whose transcript the given employee/user pairing may read.
     *
     * Mirrors {@see Sop::scopePubliclyVisible()}: an employee sees what they
     * attended (or what the company opened to everyone), while `meeting.view`
     * is the HR-side override that sees all of it. Passing `$canViewAll` keeps
     * the permission check with the caller, where the user object lives.
     */
    public function scopeReadableBy(Builder $query, ?int $employeeId, ?int $userId, bool $canViewAll): Builder
    {
        if ($canViewAll) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($employeeId, $userId): void {
            $inner->where('visibility', self::VISIBILITY_TENANT);

            if ($userId !== null) {
                $inner->orWhere('created_by', $userId);
            }

            if ($employeeId !== null) {
                $inner->orWhereHas('participants', fn (Builder $q) => $q->where('employee_id', $employeeId));
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(MeetingSegment::class)->orderBy('start_ms');
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(MeetingSpeaker::class)->orderBy('speaker_index');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(MeetingActionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MeetingInsight::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(MeetingChunk::class)->orderBy('ordinal');
    }

    /**
     * Recorded audio in whole minutes, rounded up — what a provider bills.
     */
    public function durationMinutes(): int
    {
        return (int) ceil($this->duration_ms / 60_000);
    }

    /**
     * The name to print for a speaker number: the confirmed one, the AI's guess,
     * or the provider's bare "Pembicara 1".
     */
    public function speakerName(int $speakerIndex): string
    {
        $speaker = $this->speakers->firstWhere('speaker_index', $speakerIndex);

        return $speaker?->resolvedName() ?? 'Pembicara '.($speakerIndex + 1);
    }
}

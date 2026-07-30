<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One deep analysis of a meeting, written by the expensive reasoning model.
 *
 * These are the premium half of the feature: nobody pays for them unless they
 * ask, and once written the answer is stored so re-reading it costs nothing.
 * Regenerating is an explicit act.
 */
final class MeetingInsight extends Model
{
    public const TYPE_EXECUTIVE_SUMMARY = 'executive_summary';

    public const TYPE_DECISION_ANALYSIS = 'decision_analysis';

    public const TYPE_PROJECT_RISK = 'project_risk';

    public const TYPE_SENTIMENT = 'sentiment';

    public const TYPE_FOLLOW_UP = 'follow_up';

    /**
     * The analyses on offer, in the order the meeting page lists them.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        self::TYPE_EXECUTIVE_SUMMARY => 'Executive Summary',
        self::TYPE_DECISION_ANALYSIS => 'Analisis Keputusan',
        self::TYPE_PROJECT_RISK => 'Risiko Proyek',
        self::TYPE_SENTIMENT => 'Analisis Sentimen',
        self::TYPE_FOLLOW_UP => 'Rekomendasi Tindak Lanjut',
    ];

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'tokens' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * The analysis keys, for validating what a request may ask for.
     *
     * @return array<int, string>
     */
    public static function types(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? $type;
    }
}

<?php

namespace App\Support;

/**
 * Compares face feature vectors (MobileFaceNet embeddings) by cosine similarity.
 */
final class FaceMatcher
{
    /**
     * Minimum cosine similarity to accept two embeddings as the same face.
     * MobileFaceNet embeddings are L2-normalized. Genuine same-person scores on
     * phone cameras (varied lighting/angle, and a two-frame enrollment average
     * vs a single verification shot) commonly land around 0.45-0.7, while
     * impostors stay below ~0.35 — so 0.45 accepts real matches without letting
     * impostors through.
     */
    public const THRESHOLD = 0.45;

    /**
     * Cosine similarity in [-1, 1]; returns -1 for mismatched/empty vectors.
     *
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        if ($a === [] || count($a) !== count($b)) {
            return -1.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $valueA) {
            $x = (float) $valueA;
            $y = (float) $b[$i];
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return -1.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    public static function matches(array $a, array $b, float $threshold = self::THRESHOLD): bool
    {
        return self::cosine($a, $b) >= $threshold;
    }
}

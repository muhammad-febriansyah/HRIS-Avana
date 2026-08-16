<?php

namespace App\Support;

/**
 * The marital statuses an employee record may hold, stored as the Indonesian
 * label itself so the column stays readable and matches the four categories
 * Dukcapil prints on a KTP.
 *
 * This is demographic data only — the PTKP category payroll taxes against lives
 * on its own `ptkp_status` column and is not derived from this.
 */
final class MaritalStatus
{
    public const OPTIONS = [
        'Lajang',
        'Menikah',
        'Cerai Hidup',
        'Cerai Mati',
    ];

    /**
     * The list a form renders, as value/label pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $status): array => ['value' => $status, 'label' => $status],
            self::OPTIONS,
        );
    }
}

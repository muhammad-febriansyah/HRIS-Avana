<?php

namespace App\Support;

/**
 * The employment contract kinds, and the one spelling of each that is stored.
 *
 * Two screens write `employee_contracts.contract_type`: the Kontrak form, which
 * always sent a lower-case key, and the employee form, which took free text and
 * sent whatever was typed — in practice "PKWT" and "PKWTT". The Kontrak form's
 * dropdown then found no option matching the stored value and fell back to its
 * first one, so opening a PKWTT contract there showed PKWT, and saving made
 * that true. Everything is normalised on the way in so both screens agree.
 */
final class ContractType
{
    /**
     * The stored key of each kind, and how it reads on screen.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'pkwt' => 'PKWT (Kontrak)',
        'pkwtt' => 'PKWTT (Tetap)',
        'probation' => 'Probation',
    ];

    /**
     * Spellings seen in the wild that mean one of the canonical kinds.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'kontrak' => 'pkwt',
        'tidak tetap' => 'pkwt',
        'tetap' => 'pkwtt',
        'permanent' => 'pkwtt',
        'percobaan' => 'probation',
        'masa percobaan' => 'probation',
        'training' => 'probation',
    ];

    /**
     * The stored form of a typed or posted contract type. Anything unrecognised
     * is kept, lower-cased, rather than forced into a kind it may not be.
     */
    public static function normalise(?string $value): ?string
    {
        $key = mb_strtolower(trim((string) $value));

        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, self::LABELS)) {
            return $key;
        }

        return self::ALIASES[$key] ?? $key;
    }

    /**
     * How a stored type reads on screen, falling back to the value itself for a
     * kind a tenant invented.
     */
    public static function label(?string $value): string
    {
        $key = self::normalise($value);

        if ($key === null) {
            return '—';
        }

        return self::LABELS[$key] ?? mb_strtoupper($key);
    }

    /**
     * Options for a select, in the order the forms show them.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys(self::LABELS),
            array_values(self::LABELS),
        );
    }
}

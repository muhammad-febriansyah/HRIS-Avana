<?php

namespace App\Support;

use RuntimeException;

/**
 * Reads a TER tariff out of the official "Setup TER PPh21" workbook.
 *
 * The published layout is one sheet per category (TER_A / TER_B / TER_C, plus
 * a daily sheet when the file carries one) with a header row and then bracket
 * rows of "batas bawah, batas atas, tarif", and a Kategori_PTKP sheet mapping
 * each PTKP status to its category. Nothing here assumes column positions
 * beyond that shape: a row counts as a bracket when it has a lower bound and a
 * rate, so an extra "No" column or a shifted header does not break the import.
 */
final class Pph21TerImport
{
    /**
     * Sheet-name patterns per category. A workbook that names its sheets
     * differently still imports as long as the category letter is in the name.
     *
     * @var array<string, string>
     */
    private const SHEET_PATTERNS = [
        'A' => '/ter[^a-z]*a\b|kategori[^a-z]*a\b/i',
        'B' => '/ter[^a-z]*b\b|kategori[^a-z]*b\b/i',
        'C' => '/ter[^a-z]*c\b|kategori[^a-z]*c\b/i',
        'HARIAN' => '/harian|daily/i',
    ];

    private const CATEGORY_SHEET_PATTERN = '/kategori.*ptkp|ptkp.*kategori|mapping/i';

    /**
     * Parse a workbook into bracket rows and the PTKP mapping.
     *
     * @return array{
     *     brackets: array<string, list<array{income_min: float, income_max: float|null, rate: float}>>,
     *     categories: array<string, string>,
     *     sheets: list<string>
     * }
     *
     * @throws RuntimeException when the file yields no usable table
     */
    public static function parse(string $path): array
    {
        $reader = XlsxReader::open($path);

        try {
            $names = $reader->sheetNames();
            $brackets = [];

            foreach (self::SHEET_PATTERNS as $category => $pattern) {
                $sheet = self::matchSheet($names, $pattern);

                if ($sheet === null) {
                    continue;
                }

                $rows = self::bracketsFrom($reader->rows($sheet));

                if ($rows !== []) {
                    $brackets[$category] = $rows;
                }
            }

            $mappingSheet = self::matchSheet($names, self::CATEGORY_SHEET_PATTERN);
            $categories = $mappingSheet !== null ? self::categoriesFrom($reader->rows($mappingSheet)) : [];
        } finally {
            $reader->close();
        }

        if ($brackets === []) {
            throw new RuntimeException('Tidak ada tabel TER yang terbaca. Pastikan file punya sheet TER_A / TER_B / TER_C.');
        }

        return ['brackets' => $brackets, 'categories' => $categories, 'sheets' => $names];
    }

    /**
     * @param  list<string>  $names
     */
    private static function matchSheet(array $names, string $pattern): ?string
    {
        foreach ($names as $name) {
            if (preg_match($pattern, $name) === 1) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Turn a sheet's rows into brackets, ordered by their lower bound.
     *
     * The columns are located from the header row rather than by position: a
     * leading "No" counter is indistinguishable from a lower bound otherwise,
     * and getting that wrong silently drops the open-ended top bracket — the
     * one that matters most, since it is the highest rate. A sheet with no
     * recognisable header falls back to reading the last three numeric columns.
     *
     * Rates are accepted either as decimals (0,0025) or as percentages (0,25);
     * if the largest value in the column exceeds 1 it can only be the latter.
     *
     * @param  list<array<string, string>>  $rows
     * @return list<array{income_min: float, income_max: float|null, rate: float}>
     */
    private static function bracketsFrom(array $rows): array
    {
        $columns = self::locateColumns($rows);

        $candidates = $columns !== null
            ? self::bracketsByColumn($rows, $columns)
            : self::bracketsByPosition($rows);

        if ($candidates === []) {
            return [];
        }

        $maxRate = max(array_column($candidates, 'rate'));

        if ($maxRate > 1) {
            $candidates = array_map(
                static function (array $row): array {
                    $row['rate'] = $row['rate'] / 100;

                    return $row;
                },
                $candidates,
            );
        }

        usort($candidates, static fn (array $a, array $b): int => $a['income_min'] <=> $b['income_min']);

        // Exactly one open-ended bracket, and it has to be the last.
        $last = count($candidates) - 1;

        foreach ($candidates as $index => $row) {
            if ($index !== $last && $row['income_max'] === null) {
                unset($candidates[$index]);
            }
        }

        return array_values($candidates);
    }

    /**
     * Find the lower-bound / upper-bound / rate columns from a header row.
     *
     * @param  list<array<string, string>>  $rows
     * @return array{lower: string, upper: ?string, rate: string}|null
     */
    private static function locateColumns(array $rows): ?array
    {
        foreach ($rows as $row) {
            $lower = $upper = $rate = null;

            foreach ($row as $column => $label) {
                if (is_numeric($label)) {
                    continue;
                }

                if (preg_match('/tarif|rate|persen/i', $label) === 1) {
                    $rate = $column;
                } elseif (preg_match('/bawah|minimum|\bmin\b|dari/i', $label) === 1) {
                    $lower = $column;
                } elseif (preg_match('/atas|s\.?\s?d|sampai|maksimum|\bmax\b/i', $label) === 1) {
                    $upper = $column;
                }
            }

            if ($rate !== null && $lower !== null) {
                return ['lower' => $lower, 'upper' => $upper, 'rate' => $rate];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array{lower: string, upper: ?string, rate: string}  $columns
     * @return list<array{income_min: float, income_max: float|null, rate: float}>
     */
    private static function bracketsByColumn(array $rows, array $columns): array
    {
        $brackets = [];

        foreach ($rows as $row) {
            $rate = self::number($row[$columns['rate']] ?? null);
            $lower = self::number($row[$columns['lower']] ?? null);

            if ($rate === null || $lower === null) {
                continue;
            }

            $brackets[] = [
                'income_min' => $lower,
                'income_max' => $columns['upper'] !== null ? self::number($row[$columns['upper']] ?? null) : null,
                'rate' => $rate,
            ];
        }

        return $brackets;
    }

    /**
     * Last-resort reading for a sheet with no header: the rate is the final
     * numeric on the row and the bounds are the two before it.
     *
     * @param  list<array<string, string>>  $rows
     * @return list<array{income_min: float, income_max: float|null, rate: float}>
     */
    private static function bracketsByPosition(array $rows): array
    {
        $brackets = [];

        foreach ($rows as $row) {
            ksort($row);
            $values = [];

            foreach ($row as $value) {
                $number = self::number($value);

                if ($number !== null) {
                    $values[] = $number;
                }
            }

            if (count($values) < 2) {
                continue;
            }

            $rate = array_pop($values);
            $upper = count($values) >= 2 ? array_pop($values) : null;
            $lower = array_pop($values);

            $brackets[] = ['income_min' => (float) $lower, 'income_max' => $upper, 'rate' => $rate];
        }

        return $brackets;
    }

    /**
     * A cell as a number, or null when it holds anything else. Handles the
     * comma decimal separator an Indonesian locale writes.
     */
    private static function number(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $clean = str_replace(',', '.', trim($value));

        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * Read the PTKP→category mapping, keeping only statuses the scheme knows.
     *
     * @param  list<array<string, string>>  $rows
     * @return array<string, string>
     */
    private static function categoriesFrom(array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $status = null;
            $category = null;

            foreach ($row as $value) {
                $clean = strtoupper(str_replace([' ', '-'], ['', '/'], trim($value)));

                if (in_array($clean, Pph21Ter::PTKP_STATUSES, true)) {
                    $status = $clean;
                } elseif (in_array($clean, ['A', 'B', 'C'], true)) {
                    $category = $clean;
                }
            }

            if ($status !== null && $category !== null) {
                $map[$status] = $category;
            }
        }

        return $map;
    }
}

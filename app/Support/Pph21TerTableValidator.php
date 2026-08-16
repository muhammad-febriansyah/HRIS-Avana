<?php

namespace App\Support;

use RuntimeException;

final class Pph21TerTableValidator
{
    private const EPSILON = 0.001;

    /**
     * @param  array<string, list<array{income_min: float, income_max: float|null, rate: float}>>  $tables
     * @param  array<string, string>  $categoryMap
     */
    public static function validate(array $tables, array $categoryMap): void
    {
        $requiredCategories = array_keys(Pph21Ter::CATEGORIES);
        $missingCategories = array_values(array_diff($requiredCategories, array_keys($tables)));
        $extraCategories = array_values(array_diff(array_keys($tables), $requiredCategories));

        if ($missingCategories !== []) {
            throw new RuntimeException('Workbook belum lengkap. Sheet kategori yang tidak ditemukan: '.implode(', ', $missingCategories).'.');
        }

        if ($extraCategories !== []) {
            throw new RuntimeException('Workbook memuat kategori TER yang tidak dikenal: '.implode(', ', $extraCategories).'.');
        }

        foreach ($tables as $category => $brackets) {
            self::validateCategory($category, $brackets);
        }

        $missingStatuses = array_values(array_diff(Pph21Ter::PTKP_STATUSES, array_keys($categoryMap)));
        $extraStatuses = array_values(array_diff(array_keys($categoryMap), Pph21Ter::PTKP_STATUSES));

        if ($missingStatuses !== [] || $extraStatuses !== []) {
            throw new RuntimeException('Mapping PTKP harus memuat tepat delapan status. Status yang belum ada: '.implode(', ', $missingStatuses).'.');
        }

        foreach ($categoryMap as $status => $category) {
            if (! in_array($category, ['A', 'B', 'C'], true)) {
                throw new RuntimeException("Kategori PTKP {$status} harus A, B, atau C.");
            }
        }
    }

    /**
     * @param  list<array{income_min: float, income_max: float|null, rate: float}>  $brackets
     */
    private static function validateCategory(string $category, array $brackets): void
    {
        if ($brackets === []) {
            throw new RuntimeException("Kategori {$category} tidak memiliki bracket.");
        }

        $previousMax = null;
        $seenMinimums = [];

        foreach ($brackets as $index => $bracket) {
            $minimum = $bracket['income_min'];
            $maximum = $bracket['income_max'];
            $rate = $bracket['rate'];

            if (! is_finite($minimum) || $minimum < 0) {
                throw new RuntimeException("Kategori {$category} memiliki batas bawah yang tidak valid.");
            }

            if (! is_finite($rate) || $rate < 0 || $rate > 1) {
                throw new RuntimeException("Kategori {$category} memiliki tarif di luar rentang 0% sampai 100%.");
            }

            $minimumKey = number_format($minimum, 2, '.', '');

            if (isset($seenMinimums[$minimumKey])) {
                throw new RuntimeException("Kategori {$category} memiliki bracket duplikat pada batas {$minimumKey}.");
            }

            $seenMinimums[$minimumKey] = true;

            if ($index === 0 && abs($minimum) > self::EPSILON) {
                throw new RuntimeException("Bracket pertama kategori {$category} harus mulai dari 0.");
            }

            if ($index > 0 && ($previousMax === null || abs($minimum - $previousMax) > self::EPSILON)) {
                throw new RuntimeException("Kategori {$category} memiliki celah atau tumpang tindih sebelum batas {$minimumKey}.");
            }

            if ($maximum !== null && (! is_finite($maximum) || $maximum <= $minimum)) {
                throw new RuntimeException("Kategori {$category} memiliki batas atas yang tidak lebih besar dari batas bawah.");
            }

            $isLast = $index === array_key_last($brackets);

            if ($category === 'HARIAN') {
                if ($maximum === null) {
                    throw new RuntimeException('Kategori HARIAN tidak boleh memiliki bracket tanpa batas; pendapatan di atas Rp2,5 juta memakai Pasal 17.');
                }
            } elseif (($maximum === null) !== $isLast) {
                throw new RuntimeException("Kategori {$category} harus memiliki tepat satu bracket tanpa batas pada baris terakhir.");
            }

            $previousMax = $maximum;
        }
    }
}

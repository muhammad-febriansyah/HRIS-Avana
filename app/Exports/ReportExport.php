<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A generic single-sheet .xlsx export built from a headings row and pre-mapped
 * data rows, reused by every LaporanController report type.
 */
final class ReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, array<int, string|int|null>>  $rows
     * @param  array<int, string>  $headings
     */
    public function __construct(
        private array $rows,
        private array $headings,
        private string $title = 'Laporan',
    ) {}

    /**
     * @return array<int, array<int, string|int|null>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        // Sheet titles are capped at 31 chars and forbid several characters.
        return substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $this->title) ?? 'Laporan', 0, 31);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

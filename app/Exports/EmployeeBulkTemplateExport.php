<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The bulk-employee import template: a fill-in "Karyawan" sheet plus a
 * "Referensi" sheet listing the valid branch / department / position / status
 * values for this tenant, so HR fills names the importer can resolve.
 */
final class EmployeeBulkTemplateExport implements WithMultipleSheets
{
    /**
     * @param  array<int, string>  $branches
     * @param  array<int, string>  $departments
     * @param  array<int, string>  $positions
     */
    public function __construct(
        private array $branches,
        private array $departments,
        private array $positions,
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new EmployeeBulkTemplateSheet,
            new EmployeeBulkReferenceSheet($this->branches, $this->departments, $this->positions),
        ];
    }
}

/** The data-entry sheet: headers + two example rows. */
final class EmployeeBulkTemplateSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            ['Budi Santoso', 'budi@perusahaan.co.id', 'Jakarta Pusat', 'Engineering', 'Software Engineer', 'Tetap', ''],
            ['Sari Wulandari', 'sari@perusahaan.co.id', 'Bandung', 'Sales', 'Sales Executive', 'Kontrak', ''],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['nama_lengkap', 'email', 'cabang', 'departemen', 'jabatan', 'status_kepegawaian', 'password'];
    }

    public function title(): string
    {
        return 'Karyawan';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

/** The reference sheet: valid values HR should copy into the data sheet. */
final class EmployeeBulkReferenceSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, string>  $branches
     * @param  array<int, string>  $departments
     * @param  array<int, string>  $positions
     */
    public function __construct(
        private array $branches,
        private array $departments,
        private array $positions,
    ) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        $statuses = ['Masa Percobaan', 'Kontrak', 'Tetap', 'Resign'];
        $max = max(count($this->branches), count($this->departments), count($this->positions), count($statuses));
        $rows = [];

        for ($i = 0; $i < $max; $i++) {
            $rows[] = [
                $this->branches[$i] ?? '',
                $this->departments[$i] ?? '',
                $this->positions[$i] ?? '',
                $statuses[$i] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Cabang (valid)', 'Departemen (valid)', 'Jabatan (valid)', 'Status (valid)'];
    }

    public function title(): string
    {
        return 'Referensi';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

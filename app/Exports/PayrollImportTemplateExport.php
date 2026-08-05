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
 * The payroll-import template for a tenant that runs its payroll elsewhere:
 * a "Payroll" sheet already listing every payable employee, so HR only fills
 * in the amounts, plus a sheet explaining each column.
 */
final class PayrollImportTemplateExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array{0: string, 1: string}>  $employees  [number, name]
     */
    public function __construct(private array $employees) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new PayrollImportTemplateSheet($this->employees),
            new PayrollImportGuideSheet,
        ];
    }
}

/** The data-entry sheet: one pre-filled row per payable employee. */
final class PayrollImportTemplateSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, array{0: string, 1: string}>  $employees
     */
    public function __construct(private array $employees) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        if ($this->employees === []) {
            return [['EMP-0001', 'Contoh Karyawan', '10000000', '1000000', '0', '400000', '600000', '250000', '']];
        }

        return array_map(
            static fn (array $employee): array => [$employee[0], $employee[1], '', '', '', '', '', '', ''],
            $this->employees,
        );
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return PayrollImportTemplateSheet::COLUMNS;
    }

    /**
     * The column order the importer reads positionally — keep in sync with
     * PayrollImportController::parseRow().
     *
     * @var array<int, string>
     */
    public const COLUMNS = [
        'nomor_karyawan',
        'nama',
        'gaji_bruto',
        'tunjangan',
        'potongan',
        'bpjs_karyawan',
        'bpjs_perusahaan',
        'pph21',
        'take_home_pay',
    ];

    public function title(): string
    {
        return 'Payroll';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

/** What each column means, so the numbers land where HR expects them. */
final class PayrollImportGuideSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            ['nomor_karyawan', 'Wajib. Harus sama persis dengan Nomor Karyawan di menu Karyawan.'],
            ['nama', 'Hanya penanda saat mengisi — tidak dipakai importer.'],
            ['gaji_bruto', 'Wajib. Total penghasilan bruto sebelum potongan.'],
            ['tunjangan', 'Opsional. Bagian tunjangan di dalam bruto (untuk tampilan slip).'],
            ['potongan', 'Opsional. Potongan lain di luar BPJS dan PPh 21.'],
            ['bpjs_karyawan', 'Opsional. Iuran BPJS yang dipotong dari karyawan.'],
            ['bpjs_perusahaan', 'Opsional. Iuran BPJS yang ditanggung perusahaan.'],
            ['pph21', 'Opsional. PPh 21 yang dipotong. Kosongkan bila tidak memotong.'],
            ['take_home_pay', 'Opsional. Kosongkan = bruto − potongan − BPJS karyawan − PPh 21.'],
            ['', ''],
            ['Catatan', 'Angka boleh ditulis 10.000.000 atau 10000000. Baris kosong dilewati.'],
            ['Catatan', 'Impor mengganti seluruh data payroll periode yang dipilih.'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Kolom', 'Keterangan'];
    }

    public function title(): string
    {
        return 'Petunjuk';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

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
 * The "Saldo Cuti" fill-in workbook: one row per employee per quota-owning
 * leave type, pre-filled with the quota already on file so HR only edits the
 * ones that differ.
 */
final class LeaveBalanceTemplateExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $rows  [number, name, type, quota]
     */
    public function __construct(private array $rows, private int $year) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new LeaveBalanceTemplateSheet($this->rows),
            new LeaveBalanceGuideSheet($this->year),
        ];
    }
}

/** The data-entry sheet. */
final class LeaveBalanceTemplateSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * The column order the importer reads positionally — keep in sync with
     * LeaveBalanceController::import().
     *
     * @var array<int, string>
     */
    public const COLUMNS = [
        'nomor_karyawan',
        'nama',
        'jenis_cuti',
        'kuota_hari',
    ];

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $rows
     */
    public function __construct(private array $rows) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        if ($this->rows === []) {
            return [['EMP-0001', 'Contoh Karyawan', 'Cuti Tahunan', '12']];
        }

        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return self::COLUMNS;
    }

    public function title(): string
    {
        return 'Saldo Cuti';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

/** What each column means. */
final class LeaveBalanceGuideSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private int $year) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            ['nomor_karyawan', 'Wajib. Harus sama persis dengan Nomor Karyawan di menu Karyawan.'],
            ['nama', 'Hanya penanda saat mengisi — tidak dipakai importer.'],
            ['jenis_cuti', 'Wajib. Nama jenis cuti induk (yang memegang kuota), persis seperti di menu Jenis Cuti.'],
            ['kuota_hari', 'Wajib. Jatah hari setahun. Boleh desimal, misalnya 12,5.'],
            ['', ''],
            ['Catatan', 'Impor hanya mengubah kuota tahun '.$this->year.'. Baris kosong dilewati.'],
            ['Catatan', 'Hari terpakai tidak bisa diisi dari sini — angkanya dihitung dari cuti yang sudah disetujui.'],
            ['Catatan', 'Satu baris yang salah membatalkan seluruh berkas, jadi tidak ada impor setengah jadi.'],
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

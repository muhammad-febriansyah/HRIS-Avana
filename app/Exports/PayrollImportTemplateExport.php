<?php

namespace App\Exports;

use App\Models\PayrollComponent;
use App\Support\PayrollImportLayout;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The payroll-import template for a tenant that runs its payroll elsewhere.
 *
 * The "Payroll" sheet lists every payable employee with one column per salary
 * component from Master Komponen, so the file mirrors the tenant's own setup
 * rather than a fixed set of columns. Fixed amounts already on the employee's
 * salary are pre-filled as a reference; the rest is left blank. A second sheet
 * explains every column.
 */
final class PayrollImportTemplateExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array{number: string, name: string, amounts: array<int, float>}>  $employees
     * @param  Collection<int, PayrollComponent>  $components
     */
    public function __construct(private array $employees, private Collection $components) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new PayrollImportTemplateSheet($this->employees, $this->components),
            new PayrollImportGuideSheet($this->components),
        ];
    }
}

/** The data-entry sheet: one pre-filled row per payable employee. */
final class PayrollImportTemplateSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, array{number: string, name: string, amounts: array<int, float>}>  $employees
     * @param  Collection<int, PayrollComponent>  $components
     */
    public function __construct(private array $employees, private Collection $components) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        $employees = $this->employees !== []
            ? $this->employees
            : [['number' => 'EMP-0001', 'name' => 'Contoh Karyawan', 'amounts' => []]];

        return array_map(fn (array $employee): array => [
            $employee['number'],
            $employee['name'],
            ...$this->components
                ->map(function (PayrollComponent $component) use ($employee): string {
                    $amount = $employee['amounts'][$component->id] ?? null;

                    return $amount === null ? '' : (string) (int) round($amount);
                })
                ->all(),
            // BPJS, PPh 21 and take-home are the tenant's own figures: the
            // engine does not run on an import, so nothing can fill them in.
            ...array_fill(0, count(PayrollImportLayout::TRAILING), ''),
        ], $employees);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return PayrollImportLayout::headings($this->components);
    }

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
     * @param  Collection<int, PayrollComponent>  $components
     */
    public function __construct(private Collection $components) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        $rows = [
            ['nomor_karyawan', 'Wajib', 'Harus sama persis dengan Nomor Karyawan di menu Karyawan.'],
            ['nama', 'Penanda', 'Hanya penanda saat mengisi — tidak dipakai importer.'],
        ];

        foreach ($this->components as $component) {
            $rows[] = [
                PayrollImportLayout::heading($component, $this->components),
                PayrollImportLayout::isDeduction($component) ? 'Potongan' : 'Penerimaan',
                PayrollImportLayout::fillHint($component),
            ];
        }

        return [
            ...$rows,
            ['bpjs_karyawan', 'Opsional', 'Iuran BPJS (Kesehatan + JHT + JP) yang dipotong dari karyawan. Dikosongkan = 0, tidak dihitung sistem.'],
            ['bpjs_perusahaan', 'Opsional', 'Iuran BPJS yang ditanggung perusahaan — tidak mengurangi THP. Dikosongkan = 0, tidak dihitung sistem.'],
            ['pph21', 'Opsional', 'PPh 21 yang dipotong. Dikosongkan = 0, artinya slip keluar tanpa potongan pajak.'],
            ['take_home_pay', 'Opsional', 'Satu-satunya kolom yang diturunkan bila kosong: penerimaan − potongan − BPJS karyawan − PPh 21.'],
            ['', '', ''],
            ['Catatan', '', 'Kolom komponen mengikuti Master Komponen tenant. Menambah komponen di master menambah kolom di template berikutnya.'],
            ['Catatan', '', 'Impor TIDAK menjalankan mesin perhitungan: kategori TER dan status PTKP karyawan tidak dipakai, dan PPh 21 tidak dihitung. Isi sendiri kolom pph21, atau pakai Jalankan Payroll bila ingin sistem yang menghitung pajak.'],
            ['Catatan', '', 'Angka boleh ditulis 10.000.000 atau 10000000. Baris kosong dilewati.'],
            ['Catatan', '', 'Setiap baris wajib punya minimal satu komponen penerimaan terisi. Satu baris kosong membatalkan seluruh impor.'],
            ['Catatan', '', 'Impor mengganti seluruh data payroll periode yang dipilih.'],
            ['Catatan', '', 'Periode yang sudah pernah dihitung sistem tidak bisa ditimpa impor — pakai periode yang belum dihitung.'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Kolom', 'Sifat', 'Keterangan'];
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

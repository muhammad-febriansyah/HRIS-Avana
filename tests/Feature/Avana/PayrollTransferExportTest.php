<?php

use App\Exports\PayrollTransferExport;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

it('writes a real xlsx with the letterhead, the rows and a total', function (): void {
    $export = new PayrollTransferExport(
        [
            ['name' => 'Budi Santoso', 'bank' => 'BCA', 'account' => '0123456789', 'holder' => 'Budi Santoso', 'net' => 7_500_000],
            ['name' => 'Sari Wulandari', 'bank' => 'Mandiri', 'account' => '9876543210', 'holder' => 'Sari Wulandari', 'net' => 6_250_000],
        ],
        'PT Nusantara Jaya',
        'Agustus 2026',
        'Gaji Agustus 2026',
    );

    // Rendering for real catches what a faked download cannot: a style or
    // drawing PhpSpreadsheet refuses to write.
    $binary = ExcelFacade::raw($export, Excel::XLSX);

    expect(substr($binary, 0, 2))->toBe('PK');

    $sheet = $export->array();

    expect($sheet[0][0])->toBe('PT Nusantara Jaya')
        ->and($sheet[2][0])->toBe('Periode: Agustus 2026')
        ->and($sheet[4])->toBe(['No', 'Nama Karyawan', 'Bank', 'No. Rekening', 'Atas Nama', 'Nominal (Rp)'])
        ->and($sheet[5][3])->toBe('0123456789')
        // The total is a number, so the sheet's rupiah format applies to it too.
        ->and(end($sheet)[5])->toBe(13_750_000);
});

it('leaves the logo out when the tenant has none', function (): void {
    $export = new PayrollTransferExport([], 'PT Tanpa Logo', 'Agustus 2026', 'Gaji', null);

    expect($export->drawings())->toBe([]);
});

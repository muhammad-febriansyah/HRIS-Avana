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

    expect($export->drawings())->toBe([])
        // Without a logo the name carries the letterhead on its own.
        ->and($export->array()[0][0])->toBe('PT Tanpa Logo');
});

it('shows the logo instead of the company name, not both', function (): void {
    $logo = storage_path('app/uji-logo.png');
    file_put_contents($logo, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));

    try {
        $export = new PayrollTransferExport([], 'PT Berlogo', 'Agustus 2026', 'Gaji', $logo);

        expect($export->array()[0][0])->toBeNull()
            ->and($export->drawings())->toHaveCount(1)
            // Anchored where the name would have been, so the block reads the
            // same way either way.
            ->and($export->drawings()[0]->getCoordinates())->toBe('A1');
    } finally {
        @unlink($logo);
    }
});

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1e293b; font-size: 12px; margin: 0; }
        .sheet { padding: 40px 48px; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .brand { font-size: 20px; font-weight: bold; color: #2F54C9; }
        .brand-sub { color: #64748b; font-size: 11px; }
        .doc { font-size: 18px; font-weight: bold; text-align: right; color: #0E1A3A; }
        .doc-no { text-align: right; color: #64748b; font-size: 11px; }
        .section { margin-top: 24px; }
        .label { color: #94a3b8; font-size: 10px; text-transform: uppercase; }
        .val { font-size: 13px; font-weight: bold; color: #0E1A3A; }
        .cols td { vertical-align: top; width: 50%; padding-right: 18px; }
        .items { margin-top: 10px; }
        .items th { text-align: left; padding: 7px 6px; font-size: 10px; color: #94a3b8; text-transform: uppercase; border-bottom: 2px solid #cbd5e1; }
        .items td { padding: 7px 6px; font-size: 12px; border-bottom: 1px solid #eef2f7; }
        .r { text-align: right; }
        .sub td { font-weight: bold; border-top: 2px solid #cbd5e1; }
        .net { margin-top: 22px; background: #F1F5FF; padding: 14px 18px; }
        .net .big { font-size: 20px; font-weight: bold; color: #2F54C9; }
        .foot { margin-top: 40px; color: #94a3b8; font-size: 10px; border-top: 1px solid #eef2f7; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="sheet">
        <table class="head">
            <tr>
                <td>
                    <div class="brand">{{ $company }}</div>
                    <div class="brand-sub">Slip Gaji Karyawan</div>
                </td>
                <td>
                    <div class="doc">SLIP GAJI</div>
                    <div class="doc-no">{{ $period }}</div>
                </td>
            </tr>
        </table>

        <div class="section">
            <table class="cols">
                <tr>
                    <td>
                        <div class="label">Nama Karyawan</div>
                        <div class="val">{{ $employee['name'] }}</div>
                    </td>
                    <td>
                        <div class="label">NIK / No. Karyawan</div>
                        <div class="val">{{ $employee['number'] }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding-top:12px">
                        <div class="label">Jabatan</div>
                        <div class="val">{{ $employee['position'] }}</div>
                    </td>
                    <td style="padding-top:12px">
                        <div class="label">Departemen</div>
                        <div class="val">{{ $employee['department'] }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="cols section">
            <tr>
                <td>
                    <table class="items">
                        <thead><tr><th>Penghasilan</th><th class="r">Jumlah</th></tr></thead>
                        <tbody>
                            @forelse ($earnings as $row)
                                <tr><td>{{ $row['name'] }}</td><td class="r">{{ $row['amount'] }}</td></tr>
                            @empty
                                <tr><td colspan="2">—</td></tr>
                            @endforelse
                            <tr class="sub"><td>Total Bruto</td><td class="r">{{ $gross }}</td></tr>
                        </tbody>
                    </table>
                </td>
                <td>
                    <table class="items">
                        <thead><tr><th>Potongan</th><th class="r">Jumlah</th></tr></thead>
                        <tbody>
                            @forelse ($deductions as $row)
                                <tr><td>{{ $row['name'] }}</td><td class="r">{{ $row['amount'] }}</td></tr>
                            @empty
                                <tr><td colspan="2">—</td></tr>
                            @endforelse
                            <tr class="sub"><td>Total Potongan</td><td class="r">{{ $deduction }}</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <table class="net">
            <tr>
                <td><div class="label">Gaji Bersih (Take-home Pay)</div></td>
                <td class="r"><span class="big">{{ $net }}</span></td>
            </tr>
        </table>

        <div class="foot">
            Dokumen ini dihasilkan otomatis oleh sistem dan dilindungi kata sandi. Rahasia — hanya untuk karyawan bersangkutan.
        </div>
    </div>
</body>
</html>

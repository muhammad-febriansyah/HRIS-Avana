<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1e293b; font-size: 12px; margin: 0; }
        .sheet { padding: 40px 48px; }
        .brand { font-size: 18px; font-weight: bold; color: #2F54C9; }
        .brand-sub { color: #64748b; font-size: 11px; }
        .title { font-size: 16px; font-weight: bold; text-align: center; margin: 22px 0 2px; text-transform: uppercase; }
        .sub { text-align: center; color: #64748b; font-size: 11px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 4px 0; font-size: 12px; vertical-align: top; }
        .info .k { color: #64748b; width: 160px; }
        .rows { margin-top: 20px; }
        .rows th { text-align: left; padding: 8px 8px; font-size: 10px; color: #94a3b8; text-transform: uppercase; border-bottom: 2px solid #cbd5e1; }
        .rows td { padding: 9px 8px; font-size: 12px; border-bottom: 1px solid #eef2f7; }
        .r { text-align: right; }
        .rows tr:last-child td { font-weight: bold; border-top: 2px solid #cbd5e1; }
        .foot { margin-top: 40px; color: #64748b; font-size: 10px; }
        .sign { margin-top: 46px; width: 240px; float: right; text-align: center; font-size: 11px; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="brand">{{ $company }}</div>
        <div class="brand-sub">Bukti Pemotongan PPh Pasal 21</div>

        <div class="title">Formulir 1721-A1</div>
        <div class="sub">Masa Pajak: Januari – Desember {{ $year }}</div>

        <table class="info">
            <tr><td class="k">Nama Karyawan</td><td>: {{ $employee['name'] }}</td>
                <td class="k">No. Karyawan</td><td>: {{ $employee['number'] }}</td></tr>
            <tr><td class="k">NIK / NPWP</td><td>: {{ $employee['nik'] }}</td>
                <td class="k">Status PTKP</td><td>: {{ $employee['ptkp'] }}</td></tr>
            <tr><td class="k">Jabatan</td><td>: {{ $employee['position'] }}</td><td></td><td></td></tr>
        </table>

        <table class="rows">
            <thead><tr><th>Uraian</th><th class="r">Jumlah (Rp)</th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr><td>{{ $row[0] }}</td><td class="r">{{ $row[1] }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <div class="sign">
            {{ $company }}<br><br><br>
            ( ..................................... )<br>
            Pemotong Pajak
        </div>

        <div class="foot" style="clear:both">
            Dokumen ini dihasilkan otomatis dari sistem penggajian. Angka mengacu pada akumulasi seluruh periode gaji tahun berjalan.
        </div>
    </div>
</body>
</html>

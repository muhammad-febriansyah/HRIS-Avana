<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1A2333; }
        .head { border-bottom: 2px solid #2F54C9; padding-bottom: 8px; margin-bottom: 12px; }
        .head h1 { margin: 0; font-size: 16px; color: #0E1A3A; }
        .head .sub { font-size: 10px; color: #6B7280; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        thead th {
            background: #2F54C9; color: #fff; text-align: left;
            padding: 5px 6px; border: 1px solid #2546ad;
        }
        tbody td { padding: 4px 6px; border: 1px solid #E5E9F2; }
        tbody tr:nth-child(even) td { background: #F6F8FC; }
        .foot { margin-top: 10px; font-size: 8px; color: #9AA3B2; text-align: right; }
        .empty { padding: 16px; text-align: center; color: #6B7280; font-size: 10px; }
    </style>
</head>
<body>
    <div class="head">
        <h1>{{ $title }}</h1>
        @if (! empty($subtitle))
            <div class="sub">{{ $subtitle }}</div>
        @endif
    </div>

    @if (count($rows) === 0)
        <div class="empty">Tidak ada data untuk periode ini.</div>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="foot">Dibuat {{ $generatedAt }} · {{ count($rows) }} baris</div>
</body>
</html>

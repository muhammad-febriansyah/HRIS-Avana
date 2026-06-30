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
        .status { text-align: right; font-weight: bold; font-size: 11px; margin-top: 4px; }
        .status.paid { color: #16A34A; }
        .status.unpaid { color: #D97706; }
        .status.overdue { color: #DC2626; }
        .status.cancelled { color: #6B7280; }
        .section { margin-top: 28px; }
        .label { color: #94a3b8; font-size: 10px; text-transform: uppercase; }
        .bill-to { font-size: 13px; font-weight: bold; color: #0E1A3A; }
        .items { margin-top: 24px; }
        .items th { text-align: left; padding: 8px 6px; font-size: 10px; color: #94a3b8; text-transform: uppercase; border-bottom: 2px solid #cbd5e1; }
        .items td { padding: 9px 6px; font-size: 12px; border-bottom: 1px solid #eef2f7; }
        .r { text-align: right; }
        .totals { width: 240px; float: right; margin-top: 14px; }
        .totals td { padding: 5px 0; font-size: 12px; }
        .grand td { border-top: 2px solid #cbd5e1; font-weight: bold; font-size: 14px; padding-top: 10px; }
        .notes { clear: both; margin-top: 40px; color: #64748b; font-size: 11px; border-top: 1px solid #eef2f7; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="sheet">
        <table class="head">
            <tr>
                <td>
                    <div class="brand">AvanaHR</div>
                    <div class="brand-sub">Platform HRIS &amp; Payroll</div>
                </td>
                <td>
                    <div class="doc">INVOICE</div>
                    <div class="doc-no">{{ $invoice['invoice_number'] }}</div>
                    <div class="status {{ $invoice['status'] }}">{{ $statusLabel }}</div>
                </td>
            </tr>
        </table>

        <table class="section">
            <tr>
                <td>
                    <div class="label">Ditagihkan kepada</div>
                    <div class="bill-to">{{ $invoice['tenant_company'] ?? $invoice['tenant'] ?? '-' }}</div>
                </td>
                <td class="r">
                    <div class="label">Tanggal</div>
                    <div>Terbit: {{ $issueDate }}</div>
                    <div>Jatuh tempo: {{ $dueDate }}</div>
                    @if(!empty($periodLabel))<div style="color:#64748b">Periode: {{ $periodLabel }}</div>@endif
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th>Deskripsi</th>
                    <th class="r">Qty</th>
                    <th class="r">Harga</th>
                    <th class="r">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice['items'] as $item)
                    <tr>
                        <td>{{ $item['description'] }}</td>
                        <td class="r">{{ rtrim(rtrim(number_format($item['quantity'], 2, ',', '.'), '0'), ',') }}</td>
                        <td class="r">Rp {{ number_format($item['unit_price'], 0, ',', '.') }}</td>
                        <td class="r">Rp {{ number_format($item['amount'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr><td>Subtotal</td><td class="r">Rp {{ number_format($invoice['subtotal'], 0, ',', '.') }}</td></tr>
            <tr><td>Pajak</td><td class="r">Rp {{ number_format($invoice['tax'], 0, ',', '.') }}</td></tr>
            <tr class="grand"><td>Total</td><td class="r">Rp {{ number_format($invoice['total'], 0, ',', '.') }}</td></tr>
        </table>

        @if(!empty($invoice['notes']))
            <div class="notes">{{ $invoice['notes'] }}</div>
        @endif
    </div>
</body>
</html>

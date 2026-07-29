@php
    // Deliberately says nothing about who bought what: the page is public, so a
    // guessed order number must reveal no more than whether a payment cleared.
    $copy = match ($state) {
        'credited' => [
            'icon' => '&#10003;',
            'tone' => '#16A34A',
            'title' => 'Pembayaran berhasil',
            'body' => 'Token sudah masuk ke saldo pribadi Anda. Kembali ke aplikasi AvanaHR — saldonya diperbarui otomatis.',
        ],
        'pending' => [
            'icon' => '&hellip;',
            'tone' => '#D97706',
            'title' => 'Pembayaran belum selesai',
            'body' => 'Kalau Anda baru saja membayar, tunggu sebentar. Token masuk otomatis setelah pembayaran dikonfirmasi — Anda tidak perlu membayar lagi.',
        ],
        default => [
            'icon' => '?',
            'tone' => '#64748B',
            'title' => 'Pesanan tidak ditemukan',
            'body' => 'Tautan ini tidak cocok dengan pesanan mana pun. Buka kembali menu Token AI di aplikasi.',
        ],
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $copy['title'] }} &middot; AvanaHR</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #F8FAFC;
            color: #0F172A;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .card {
            max-width: 380px;
            width: 100%;
            background: #fff;
            border: 1px solid #E2E8F0;
            border-radius: 18px;
            padding: 32px 26px;
            text-align: center;
        }
        .badge {
            width: 62px;
            height: 62px;
            margin: 0 auto 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
        }
        h1 { font-size: 19px; margin: 0 0 10px; }
        p { font-size: 14px; line-height: 1.65; color: #475569; margin: 0; }
        .hint { margin-top: 22px; font-size: 12.5px; color: #94A3B8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge" style="background: {{ $copy['tone'] }}1a; color: {{ $copy['tone'] }};">
            {!! $copy['icon'] !!}
        </div>
        <h1>{{ $copy['title'] }}</h1>
        <p>{{ $copy['body'] }}</p>
        <div class="hint">Anda bisa menutup halaman ini.</div>
    </div>
</body>
</html>

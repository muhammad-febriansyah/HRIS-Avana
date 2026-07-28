@include('errors.layout', [
    'code' => 429,
    'title' => 'Terlalu banyak permintaan',
    'message' => 'Permintaan Anda terlalu cepat berurutan. Tunggu sebentar, lalu coba lagi.',
    'accent' => '#D97706',
])

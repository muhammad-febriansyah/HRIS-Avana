<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1e293b; font-size: 12px; line-height: 1.6; margin: 0; }
        .sheet { padding: 48px 56px; }
        .company { font-size: 18px; font-weight: bold; color: #2F54C9; }
        .meta { color: #64748b; font-size: 11px; margin-top: 4px; }
        .title { font-size: 16px; font-weight: bold; text-align: center; margin: 28px 0 6px; text-transform: uppercase; }
        .ref { text-align: center; color: #64748b; font-size: 11px; margin-bottom: 22px; }
        .body { margin-top: 18px; }
        hr { border: none; border-top: 1px solid #e2e8f0; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="company">{{ $company['name'] }}</div>
        <div class="meta">Dokumen Resmi Kepegawaian</div>
        <hr>

        <div class="title">{{ $letter['title'] }}</div>
        @if(!empty($letter['letter_number']) || !empty($letter['generated_at_label']))
            <div class="ref">
                @if(!empty($letter['letter_number'])) No: {{ $letter['letter_number'] }} @endif
                @if(!empty($letter['generated_at_label'])) &middot; {{ $letter['generated_at_label'] }} @endif
            </div>
        @endif

        <div class="body">{!! $letter['body'] !!}</div>
    </div>
</body>
</html>

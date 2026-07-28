{{--
    Dependency-free fallback error page: no Vite manifest, no JS bundle, no
    database required. The Inertia `error` page handles the branded in-app case
    (see AppServiceProvider::renderErrorsWithInertia); this is what renders when
    even that cannot run — a broken asset build, or a status not routed there.
--}}
@php
    // The exception may itself be a dead database or cache, so branding is
    // best-effort: a failure here must never replace the error with another one.
    $brand = ['name' => config('app.name', 'AvanaHR'), 'logo' => null, 'whatsapp' => null];

    try {
        $settings = \App\Models\WebsiteSetting::cached();
        $brand = [
            'name' => $settings->site_name ?: $brand['name'],
            'logo' => $settings->logoUrl(),
            'whatsapp' => $settings->contact_whatsapp ?: $settings->contact_phone,
        ];
    } catch (\Throwable) {
        // Keep the config defaults.
    }

    $waDigits = preg_replace('/\D+/', '', (string) ($brand['whatsapp'] ?? ''));
    $accent = $accent ?? '#DC2626';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} · {{ $title }} — {{ $brand['name'] }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background:
                radial-gradient(1200px 520px at 50% -10%, {{ $accent }}1a, transparent 70%),
                #F6F8FC;
            font-family: Poppins, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: #1A2333;
        }
        .wrap { position: relative; width: 100%; max-width: 560px; text-align: center; }
        .ghost {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            font-size: 220px; font-weight: 800; line-height: 1; letter-spacing: -.04em;
            color: {{ $accent }}12; user-select: none; pointer-events: none;
        }
        .card {
            position: relative; background: #fff; border: 1px solid rgba(26,35,51,.06); border-radius: 20px;
            padding: 38px 34px 32px; box-shadow: 0 4px 16px rgba(15,26,58,.045);
        }
        .brand { height: 52px; max-width: 220px; margin: 0 auto 24px; display: block; object-fit: contain; }
        .brand-text {
            font-size: 17px; font-weight: 700; letter-spacing: .08em; color: #2F54C9; margin-bottom: 24px;
        }
        .badge {
            display: inline-block; font-size: 11.5px; font-weight: 700; letter-spacing: .06em;
            color: {{ $accent }}; background: {{ $accent }}17; padding: 4px 11px; border-radius: 999px;
            margin-bottom: 12px;
        }
        h1 { font-size: 23px; font-weight: 700; margin: 0; letter-spacing: -.01em; }
        p { font-size: 13.5px; color: #5B6472; line-height: 1.65; margin: 10px auto 0; max-width: 430px; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 26px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; height: 44px; padding: 0 20px;
            border-radius: 11px; font-size: 14px; font-weight: 600; text-decoration: none; border: none;
            background: #2F54C9; color: #fff; cursor: pointer;
        }
        .btn.ghost-btn { background: #fff; color: #1A2333; border: 1px solid #E6EAF2; }
        .btn.wa { background: #fff; color: #128C7E; border: 1px solid rgba(37,211,102,.4); }
        .foot { font-size: 11.5px; color: #98A2B3; margin-top: 16px; }
        .foot b { color: #5B6472; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="ghost" aria-hidden="true">{{ $code }}</div>
        <div class="card">
            @if ($brand['logo'])
                <img class="brand" src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}">
            @else
                <div class="brand-text">{{ strtoupper($brand['name']) }}</div>
            @endif

            <div class="badge">ERROR {{ $code }}</div>
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>

            <div class="actions">
                <a class="btn" href="{{ url('/') }}">Ke Halaman Utama</a>
                <a class="btn ghost-btn" href="{{ url()->current() }}">Coba Lagi</a>
                @if ($waDigits !== '')
                    <a class="btn wa" href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noreferrer">Bantuan</a>
                @endif
            </div>
        </div>
        <div class="foot">
            {{ $brand['name'] }} · sebutkan kode <b>{{ $code }}</b> saat menghubungi tim dukungan
        </div>
    </div>
</body>
</html>

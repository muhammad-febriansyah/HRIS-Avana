@php
    /** @var array{brand_name: string, logo_url: string, address: ?string, phone: ?string, email: ?string, primary_color: string} $brand */
    $primary = $brand['primary_color'];
@endphp
<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $brand['brand_name'] }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; -webkit-font-smoothing:antialiased; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0;">
                    {{-- Header --}}
                    <tr>
                        <td style="background-color:{{ $primary }}; padding:24px 32px;" align="left">
                            <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['brand_name'] }}" height="36" style="height:36px; max-height:36px; width:auto; display:block; border:0;">
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:24px 32px; background-color:#f8fafc; border-top:1px solid #e2e8f0;">
                            <p style="margin:0 0 4px 0; font-size:14px; font-weight:600; color:#1e293b;">{{ $brand['brand_name'] }}</p>
                            @if ($brand['address'])
                                <p style="margin:0 0 2px 0; font-size:12px; line-height:18px; color:#64748b;">{{ $brand['address'] }}</p>
                            @endif
                            @if ($brand['phone'] || $brand['email'])
                                <p style="margin:0; font-size:12px; line-height:18px; color:#64748b;">
                                    @if ($brand['phone'])<span>{{ $brand['phone'] }}</span>@endif
                                    @if ($brand['phone'] && $brand['email'])<span style="color:#cbd5e1;"> &nbsp;•&nbsp; </span>@endif
                                    @if ($brand['email'])<a href="mailto:{{ $brand['email'] }}" style="color:{{ $primary }}; text-decoration:none;">{{ $brand['email'] }}</a>@endif
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>

                <p style="margin:16px 0 0 0; font-size:11px; color:#94a3b8;">Email ini dikirim otomatis oleh sistem AvanaHR. Mohon tidak membalas email ini.</p>
            </td>
        </tr>
    </table>
</body>
</html>

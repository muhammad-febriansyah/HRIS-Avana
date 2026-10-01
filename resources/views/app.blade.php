<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-PRJN9ZM7');</script>
        <!-- End Google Tag Manager -->

        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-XQPZWDQPGJ"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', 'G-XQPZWDQPGJ');
        </script>

        <!-- Meta Pixel Code -->
        <script>
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '1423275373087087');
            fbq('track', 'PageView');
        </script>
        <!-- End Meta Pixel Code -->

        {{-- AvanaHR is light-only. Force the light surface background before paint. --}}
        <style>
            html {
                background-color: #f4f6fb;
            }
        </style>

        @php
            $siteName = $website->site_name ?: config('app.name', 'AvanaHR');
            $siteTitle = $website->meta_title ?: $siteName;
            $faviconUrl = $website->faviconUrl();
            $ogImage = $website->ogImageUrl() ?? $website->logoUrl();
        @endphp

        {{-- Favicon: database-driven, falls back to the bundled defaults. --}}
        @if ($faviconUrl)
            <link rel="icon" href="{{ $faviconUrl }}">
            <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
        @else
            <link rel="icon" href="/avana/logo-icon.png" type="image/png">
            <link rel="apple-touch-icon" href="/avana/logo-icon.png">
        @endif

        {{-- SEO meta (database-driven; per-page <Head> may override). --}}
        @if ($website->meta_description)
            <meta name="description" content="{{ $website->meta_description }}">
        @endif
        @if ($website->meta_keywords)
            <meta name="keywords" content="{{ $website->meta_keywords }}">
        @endif

        {{-- Open Graph / social share. --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:title" content="{{ $siteTitle }}">
        <meta property="og:url" content="{{ url()->current() }}">
        @if ($website->meta_description)
            <meta property="og:description" content="{{ $website->meta_description }}">
        @endif
        @if ($ogImage)
            <meta property="og:image" content="{{ $ogImage }}">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:image" content="{{ $ogImage }}">
        @endif
        <meta name="twitter:title" content="{{ $siteTitle }}">
        @if ($website->meta_description)
            <meta name="twitter:description" content="{{ $website->meta_description }}">
        @endif

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $siteTitle }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PRJN9ZM7"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        <noscript>
            <img height="1" width="1" style="display:none"
                src="https://www.facebook.com/tr?id=1423275373087087&ev=PageView&noscript=1" />
        </noscript>
        <x-inertia::app />
    </body>
</html>

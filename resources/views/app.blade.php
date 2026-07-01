<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

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
        <x-inertia::app />
    </body>
</html>

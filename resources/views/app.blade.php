<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php($seo = app(\App\Domains\Identity\Services\SeoMeta::class)->for(request()))

        <title inertia>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        <meta name="robots" content="{{ $seo['robots'] }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">

        {{-- How the link looks when someone shares it on WhatsApp, which is
             how most of this audience shares anything --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $seo['siteName'] }}">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        <meta property="og:locale" content="{{ $seo['locale'] }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">

        <meta name="theme-color" content="#4f46e5">

        @if ($seo['structuredData'])
            {{-- Facts an answer engine can quote, rather than prose it has to
                 interpret. Carries the CSP nonce like every other inline tag. --}}
            <script type="application/ld+json" nonce="{{ $cspNonce ?? '' }}">
                {!! json_encode($seo['structuredData'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
            </script>
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        {{-- Inline scripts carry the per-request nonce the CSP expects --}}
        <!-- Scripts -->
        @routes(nonce: $cspNonce ?? null)
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

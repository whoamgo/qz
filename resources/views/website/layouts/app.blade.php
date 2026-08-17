@php
    $seo = $seo ?? [];
    $siteName = gs('site_name') ?: config('app.name');
    $metaTitle = $seo['title'] ?? $siteName;
    $metaDesc  = $seo['description'] ?? '';
    $canonical = $seo['canonical'] ?? url()->current();
    // Force the canonical to the configured scheme + host so it always matches
    // the sitemap exactly (no www vs non-www, no http vs https mismatch).
    $appScheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https';
    if ($appHost = parse_url(config('app.url'), PHP_URL_HOST)) {
        $canonical = preg_replace('#^https?://[^/]+#', $appScheme . '://' . $appHost, $canonical, 1);
    }
    $ogImage   = $seo['image'] ?? getImage(getFilePath('logoIcon') . '/logo.png');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="base-url" content="{{ url('/') }}">

    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-MWWVGDG5');</script>
    <!-- End Google Tag Manager -->

    <!-- Google tag (gtag.js) — GA4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-ZXJMGKR77X"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-ZXJMGKR77X');
    </script>
    <!-- End Google tag (gtag.js) -->

    {{-- Every page supplies its own title and description; none are shared. --}}
    <title>{{ $metaTitle }}{{ $metaTitle === $siteName ? '' : ' | ' . $siteName }}</title>
    <meta name="description" content="{{ $metaDesc }}">
    @if (!empty($seo['keywords']))
        <meta name="keywords" content="{{ $seo['keywords'] }}">
    @endif
    <meta name="robots" content="{{ $seo['robots'] ?? 'index, follow' }}">
    <link rel="canonical" href="{{ $canonical }}">

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDesc }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">

    <meta name="twitter:card" content="summary_large_image">
    @if (config('seo.twitter_site'))
        <meta name="twitter:site" content="{{ config('seo.twitter_site') }}">
    @endif
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDesc }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    <link rel="icon" href="{{ getImage(getFilePath('logoIcon') . '/favicon.ico') }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ getImage(getFilePath('logoIcon') . '/favicon.ico') }}" type="image/x-icon">

    {{-- Preconnect before the stylesheet request so fonts start earlier. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- Category icons are stored as Font Awesome classes (e.g. "fas fa-newspaper"),
         so FA must be present for them to render. Served locally from the copy
         the admin panel already uses — no extra CDN request. --}}
    <link href="{{ asset('assets/global/css/all.min.css') }}" rel="stylesheet">

    {{-- Cache-busted on file mtime so a deploy invalidates the browser cache. --}}
    <link href="{{ wAsset('assets/web/css/style.css') }}" rel="stylesheet">
    <link href="{{ wAsset('assets/web/css/responsive.css') }}" rel="stylesheet">
    {{-- Motion layer, loaded last so it only adds on top of the design. --}}
    <link href="{{ wAsset('assets/web/css/animations.css') }}" rel="stylesheet">
    @stack('styles')

    {{-- Structured data: one script per schema block supplied by the page. --}}
    @foreach (($seo['schema'] ?? []) as $schema)
        <script type="application/ld+json">@json($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endforeach
</head>
<body class="@stack('body-class') has-bottom-nav">

    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-MWWVGDG5"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    <a href="#wMainContent" class="w-skip-link">Skip to main content</a>

    @include('website.layouts.header')

    @hasSection('breadcrumb')
        <nav class="w-breadcrumb" aria-label="Breadcrumb">
            <div class="container">
                @yield('breadcrumb')
            </div>
        </nav>
    @endif

    <main id="wMainContent" tabindex="-1">
        @yield('content')
    </main>

    {{-- Google Website Translator: hidden container. The visible control is
         our own language switcher in the header; Google's banner and toolbar
         are suppressed in CSS so the page never shifts. --}}
    <div id="google_translate_element"
         data-languages="{{ \App\Models\Language::pluck('code')->implode(',') ?: 'en' }}"
         aria-hidden="true"></div>

    @include('website.layouts.footer')
    @include('website.layouts.mobile-nav')

    <button type="button" id="wBackToTop" class="btn w-btn-primary rounded-circle position-fixed d-none"
            style="bottom: 84px; right: 18px; width: 44px; height: 44px; z-index: 1050;"
            aria-label="Back to top">
        <i class="bi bi-arrow-up"></i>
    </button>

    {{-- Flash messages are handed to common.js and shown as toasts. --}}
    @foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $key => $type)
        @if (session($key))
            <span data-flash="{{ $type }}" data-flash-message="{{ session($key) }}" hidden></span>
        @endif
    @endforeach
    @if (isset($errors) && $errors->any())
        <span data-flash="error" data-flash-message="{{ $errors->first() }}" hidden></span>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="{{ wAsset('assets/web/js/common.js') }}"></script>
    <script src="{{ wAsset('assets/web/js/app.js') }}" defer></script>
    <script src="{{ wAsset('assets/web/js/animations.js') }}" defer></script>
    <script src="{{ wAsset('assets/web/js/search.js') }}" defer></script>
    <script src="{{ wAsset('assets/web/js/profile.js') }}" defer></script>
    <script src="{{ wAsset('assets/web/js/translate.js') }}"></script>
    <script src="//translate.google.com/translate_a/element.js?cb=wGoogleTranslateInit" defer></script>
    @stack('scripts')
</body>
</html>

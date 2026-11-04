@props([
    'title' => null,
    'meta_description' => null,
    'metaDescription' => null,
    'og_title' => null,
    'og_description' => null,
    'og_image' => null,
    'og_type' => null,
    'robots' => null,
    'canonical' => null,
])
@php
    $defaultTitle = 'Personalisierte Geschenke & exklusive Lasergravur | Mein Seelenfunke';
    $defaultDescription = 'Verwandle Momente in Ewigkeit ✨ Handveredelte Unikate aus K9-Kristallglas, Naturschiefer & Metall mit Live-3D-Konfigurator. Manufaktur in Gifhorn (Deutschland).';

    $sectionTitle = \Illuminate\Support\Facades\View::hasSection('title') ? trim(\Illuminate\Support\Facades\View::yieldContent('title')) : null;
    $sectionDesc = \Illuminate\Support\Facades\View::hasSection('meta_description') ? trim(\Illuminate\Support\Facades\View::yieldContent('meta_description')) : null;
    $sectionOgTitle = \Illuminate\Support\Facades\View::hasSection('og_title') ? trim(\Illuminate\Support\Facades\View::yieldContent('og_title')) : null;
    $sectionOgDesc = \Illuminate\Support\Facades\View::hasSection('og_description') ? trim(\Illuminate\Support\Facades\View::yieldContent('og_description')) : null;
    $sectionOgImage = \Illuminate\Support\Facades\View::hasSection('og_image') ? trim(\Illuminate\Support\Facades\View::yieldContent('og_image')) : null;
    $sectionOgType = \Illuminate\Support\Facades\View::hasSection('og_type') ? trim(\Illuminate\Support\Facades\View::yieldContent('og_type')) : null;
    $sectionRobots = \Illuminate\Support\Facades\View::hasSection('robots') ? trim(\Illuminate\Support\Facades\View::yieldContent('robots')) : null;
    $sectionCanonical = \Illuminate\Support\Facades\View::hasSection('canonical') ? trim(\Illuminate\Support\Facades\View::yieldContent('canonical')) : null;

    $resolvedTitle = $title ?: ($sectionTitle ?: $defaultTitle);
    if (!str_contains(mb_strtolower($resolvedTitle), 'seelenfunke')) {
        $resolvedTitle .= ' | Mein Seelenfunke';
    }

    $resolvedDescription = $meta_description ?: ($metaDescription ?: ($sectionDesc ?: $defaultDescription));
    $resolvedDescription = \Illuminate\Support\Str::limit( trim(preg_replace('/\s+/', ' ', strip_tags((string) $resolvedDescription))), 165 );

    $resolvedOgTitle = $og_title ?: ($sectionOgTitle ?: $resolvedTitle);
    $resolvedOgDescription = $og_description ?: ($sectionOgDesc ?: $resolvedDescription);
    $resolvedOgImage = $og_image ?: ($sectionOgImage ?: asset('shop/projekt/logo/mein-seelenfunke-logo.png'));
    $resolvedOgType = $og_type ?: ($sectionOgType ?: 'website');
    $resolvedCanonical = $canonical ?: ($sectionCanonical ?: url()->current());

    $currentRouteName = request()->route()?->getName() ?? '';
    $noindexRoutes = [
        'cart',
        'checkout',
        'checkout.success',
        'quote.accept',
        'login',
        'livewire.auth.register',
        'verification.notice',
        'customer.verification.verify',
        'cart.reminder.unsubscribe',
        'newsletter.verify',
        'emergency.index',
        'influencer.invitation',
        'employee.invitation',
        'frontend.tracking.redirect',
    ];
    $noReferrerRoutes = [
        'influencer.invitation',
        'employee.invitation',
        'quote.accept',
        'cart.reminder.unsubscribe',
        'newsletter.verify',
        'customer.verification.verify',
    ];
    $defaultRobots = in_array($currentRouteName, $noindexRoutes, true)
        ? 'noindex, nofollow'
        : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    $resolvedRobots = $robots ?: ($sectionRobots ?: $defaultRobots);
    $resolvedReferrerPolicy = in_array($currentRouteName, $noReferrerRoutes, true)
        ? 'no-referrer'
        : 'strict-origin-when-cross-origin';

    $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
    $founderName = shop_setting('owner_proprietor', 'Alina Steinhauer');
    $streetAddress = shop_setting('company_street')
        ? trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', ''))
        : shop_setting('owner_street', 'Carl-Goerdeler-Ring 26');
    $postalCode = shop_setting('company_zip', '38518');
    $city = shop_setting('company_city', shop_setting('owner_city', 'Gifhorn'));
    $phone = shop_setting('company_phone', shop_setting('owner_phone', '+49 159 019 668 64'));
    $email = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));

    $globalSchemaGraph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => ['Organization', 'OnlineStore', 'LocalBusiness'],
                '@id' => url('/#organization'),
                'name' => $companyName,
                'alternateName' => 'Mein Seelenfunke Manufaktur',
                'legalName' => $companyName . ' – ' . $founderName,
                'url' => url('/'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('shop/projekt/logo/mein-seelenfunke-logo.png'),
                ],
                'image' => asset('shop/projekt/about/gruender-profil-b.webp'),
                'description' => 'Deutsche Manufaktur für personalisierte Geschenke, Trophäen & hochpräzise Lasergravuren auf K9-Kristallglas, Naturschiefer, Holz und Metall mit interaktivem 2D/3D-Echtzeit-Konfigurator.',
                'founder' => [
                    '@type' => 'Person',
                    '@id' => url('/manufaktur#founder'),
                    'name' => $founderName,
                    'jobTitle' => 'Gründerin & Inhaberin',
                    'url' => route('manufacture'),
                ],
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $streetAddress,
                    'postalCode' => $postalCode,
                    'addressLocality' => $city,
                    'addressRegion' => 'Niedersachsen',
                    'addressCountry' => 'DE',
                ],
                'geo' => [
                    '@type' => 'GeoCoordinates',
                    'latitude' => 52.4886,
                    'longitude' => 10.5461,
                ],
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'telephone' => $phone,
                    'email' => $email,
                    'contactType' => 'customer service',
                    'availableLanguage' => ['de', 'German'],
                    'areaServed' => ['DE', 'AT', 'EU'],
                ],
                'areaServed' => [
                    ['@type' => 'Country', 'name' => 'DE'],
                    ['@type' => 'Country', 'name' => 'AT'],
                    ['@type' => 'AdministrativeArea', 'name' => 'EU'],
                ],
                'priceRange' => '€€',
                'sameAs' => [
                    'https://www.instagram.com/Mein_Seelenfunke/',
                    'https://www.tiktok.com/@mein_seelenfunke',
                ],
                'knowsAbout' => [
                    'Lasergravur',
                    'Personalisierte Geschenke',
                    'K9 Kristallglas Gravur',
                    'Glasfoto und Pokale mit Gravur',
                    'Schieferplatten mit Gravur',
                    'Edelstahl und eloxiertes Aluminium Gravur',
                    'Interaktiver 3D Produktkonfigurator',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => url('/#website'),
                'url' => url('/'),
                'name' => $companyName,
                'inLanguage' => 'de-DE',
                'publisher' => [
                    '@id' => url('/#organization'),
                ],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => route('shop') . '?search={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>[x-cloak] { display: none !important; }</style>

    {{-- SEO Meta --}}
    <title>{{ $resolvedTitle }}</title>
    <meta name="description" content="{{ $resolvedDescription }}">
    <meta name="keywords" content="Personalisierte Geschenke, Lasergravur, Glasfoto, K9 Kristallglas, Trophäe gravieren, Schieferplatte graviert, 3D Konfigurator, Gifhorn, Braunschweig, Wolfsburg, Hannover, Fotogeschenk, Unikat, Mein Seelenfunke">
    <meta name="robots" content="{{ $resolvedRobots }}">
    <meta name="referrer" content="{{ $resolvedReferrerPolicy }}">
    <meta name="author" content="{{ $companyName }} – {{ $founderName }}">
    <meta name="geo.region" content="DE-NI">
    <meta name="geo.placename" content="{{ $city }}">

    {{-- Open Graph / Social Media & AI Previews --}}
    <meta property="og:site_name" content="{{ $companyName }}">
    <meta property="og:locale" content="de_DE">
    <meta property="og:title" content="{{ $resolvedOgTitle }}">
    <meta property="og:description" content="{{ $resolvedOgDescription }}">
    <meta property="og:type" content="{{ $resolvedOgType }}">
    <meta property="og:url" content="{{ $resolvedCanonical }}">
    <meta property="og:image" content="{{ $resolvedOgImage }}">

    {{-- Twitter / X Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $resolvedOgTitle }}">
    <meta name="twitter:description" content="{{ $resolvedOgDescription }}">
    <meta name="twitter:image" content="{{ $resolvedOgImage }}">

    {{-- Canonical & Machine-Readable Discovery (Sitemap, Google Shopping Feed & AI Search LLMs.txt) --}}
    <link rel="canonical" href="{{ $resolvedCanonical }}">
    <link rel="sitemap" type="application/xml" title="Sitemap" href="{{ url('/sitemap.xml') }}">
    <link rel="alternate" type="application/rss+xml" title="{{ $companyName }} – Google Shopping Product Feed" href="{{ url('/feed/google-shopping.xml') }}">
    <link rel="alternate" type="application/json" title="{{ $companyName }} – Structured Product Catalog JSON" href="{{ url('/feed/products.json') }}">
    <link rel="alternate" type="text/plain" title="LLMs.txt – AI Search Summary" href="{{ url('/llms.txt') }}">
    <link rel="alternate" type="text/plain" title="LLMs-Full.txt – AI Full Product & Shop Knowledge" href="{{ url('/llms-full.txt') }}">

    {{-- Global Schema.org JSON-LD (Organization + WebSite) --}}
    <script type="application/ld+json">{!! json_encode($globalSchemaGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @stack('structured_data')
    @stack('head')

    {{-- Favicon --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : 2 }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : 2 }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=2">

    {{-- Styles --}}
    @php
        $cssVersion = file_exists(public_path('css/app.css')) ? filemtime(public_path('css/app.css')) : 1;
        $jsVersion = file_exists(public_path('js/app.js')) ? filemtime(public_path('js/app.js')) : 1;
    @endphp
    <link rel="preload" href="{{ asset('css/app.css') }}?v={{ $cssVersion }}" as="style">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $cssVersion }}">

    <!-- cookieconsent -->
    @php
        $ccCssVersion = file_exists(public_path('lib/dp_cookieconsent/css/cookieconsent.css')) ? filemtime(public_path('lib/dp_cookieconsent/css/cookieconsent.css')) : 1;
        $ccJsVersion = file_exists(public_path('lib/dp_cookieconsent/js/cookieconsent-config.js')) ? filemtime(public_path('lib/dp_cookieconsent/js/cookieconsent-config.js')) : 1;
    @endphp
    <link rel="stylesheet" href="{{ asset('lib/dp_cookieconsent/css/cookieconsent.css') }}?v={{ $ccCssVersion }}" media="print" onload="this.media='all'">
    <script type="module" src="{{ asset('lib/dp_cookieconsent/js/cookieconsent-config.js') }}?v={{ $ccJsVersion }}" defer></script>

    {{-- Scripts --}}
    <script src="{{ asset('js/app.js') }}?v={{ $jsVersion }}" defer></script>

    {{-- Livewire --}}
    @livewireStyles
    <script>
        // Öffentlicher Shop: Immer im hellen Manufaktur-Modus (Admin-Darkmode in localStorage.theme bleibt für /admin unberührt)
        (function() {
            document.documentElement.classList.remove('dark');
            document.documentElement.classList.add('light');
        })();

        document.addEventListener('livewire:navigated', function() {
            document.documentElement.classList.remove('dark');
            document.documentElement.classList.add('light');
        });

        // Unterdrückt den harmlosen Alpine/Livewire Fehler bei abgebrochenen CSS-Animationen
        window.addEventListener('unhandledrejection', function(event) {
            if (event.reason && event.reason.isFromCancelledTransition === true) {
                event.preventDefault();
            }
        });
    </script>
    @include("components.layouts.partials.frontend-error-tracker")

    <style>
        [x-cloak] {
            display: none !important;
        }
        html.dark {
            color-scheme: dark;
        }
        html.light, html:not(.dark) {
            color-scheme: light;
        }
        /* Universelle Sichtbarkeit des Mauszeigers & Carets in Input- und Textarea-Feldern */
        input, textarea, [contenteditable="true"] {
            caret-color: currentColor;
        }
        html.dark input,
        html.dark textarea,
        html.dark [contenteditable="true"],
        html.dark select {
            color-scheme: dark;
            caret-color: #E6C687;
        }
        /* Dual-Vector High-Contrast I-Beam Cursor für helle & dunkle Eingabefelder (verhindert unsichtbare Maus auf Windows/Chrome) */
        html:not(.dark) input:not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="range"]):not([type="color"]):not([type="image"]):not(:disabled):not(.cursor-pointer):not(.cursor-not-allowed):not(.cursor-wait),
        html:not(.dark) textarea:not(:disabled):not(.cursor-pointer):not(.cursor-not-allowed),
        html:not(.dark) [contenteditable="true"],
        html:not(.dark) .cursor-text {
            cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='24' viewBox='0 0 16 24'%3E%3Cpath d='M5 3h6M8 3v18M5 21h6' fill='none' stroke='%23ffffff' stroke-width='4.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M5 3h6M8 3v18M5 21h6' fill='none' stroke='%23111827' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") 8 12, text !important;
        }
        html.dark input:not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="range"]):not([type="color"]):not([type="image"]):not(:disabled):not(.cursor-pointer):not(.cursor-not-allowed):not(.cursor-wait),
        html.dark textarea:not(:disabled):not(.cursor-pointer):not(.cursor-not-allowed),
        html.dark [contenteditable="true"],
        html.dark .cursor-text,
        input[class*="bg-gray-9"]:not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="range"]):not(:disabled):not(.cursor-pointer),
        input[class*="bg-slate-9"]:not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="range"]):not(:disabled):not(.cursor-pointer),
        input[class*="bg-black"]:not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="range"]):not(:disabled):not(.cursor-pointer),
        textarea[class*="bg-gray-9"]:not(:disabled):not(.cursor-pointer),
        textarea[class*="bg-slate-9"]:not(:disabled):not(.cursor-pointer),
        textarea[class*="bg-black"]:not(:disabled):not(.cursor-pointer) {
            cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='24' viewBox='0 0 16 24'%3E%3Cpath d='M5 3h6M8 3v18M5 21h6' fill='none' stroke='%23000000' stroke-width='4.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M5 3h6M8 3v18M5 21h6' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") 8 12, text !important;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden antialiased">

@include('frontend.partials.page_transition_portal')
@include('frontend.partials.action_dock')

@php
    $activeReferralCoupon = session('seelenfunke_coupon') ?: request()->cookie('seelenfunke_coupon');
    $showReferralBanner = false;
    $bannerHtml = '';

    if ($activeReferralCoupon) {
        $customer = \Illuminate\Support\Facades\Auth::guard('customer')->user();
        $buyerEmail = $customer ? $customer->email : null;
        $buyerCustomerId = $customer ? $customer->id : null;
        $referralService = app(\App\Services\ReferralService::class);
        $referralCheck = $referralService->validateReferralCode($activeReferralCoupon, $buyerEmail, $buyerCustomerId);

        if ($referralCheck['is_referral']) {
            if ($referralCheck['valid']) {
                $showReferralBanner = true;
                $bannerHtml = 'Dein <strong>10 % Neukundenrabatt (Code: ' . e($activeReferralCoupon) . ')</strong> ist aktiviert und wird im Warenkorb automatisch abgezogen!';
            } else {
                // Bei Eigenwerbung oder Bestandskunde: Banner nicht anzeigen & Cookie/Session bereinigen
                session()->forget('seelenfunke_coupon');
                \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('seelenfunke_coupon'));
            }
        } else {
            // Regulärer Gutschein: Gültigkeit & Eigenbestellungs-Sperre prüfen
            $voucher = \App\Models\Marketing\MarketingVoucher::where('code', $activeReferralCoupon)->where('is_active', true)->first();
            if ($voucher && $voucher->isValid() && !app(\App\Services\CartService::class)->isInfluencerSelfRedemption($voucher, $buyerEmail)) {
                $showReferralBanner = true;
                $bannerHtml = 'Dein Gutscheincode <strong>' . e($activeReferralCoupon) . '</strong> ist aktiviert und wird im Warenkorb automatisch abgezogen!';
            } else {
                session()->forget('seelenfunke_coupon');
                \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('seelenfunke_coupon'));
            }
        }
    }
@endphp
@if($showReferralBanner)
    <div id="referral-coupon-banner"
         class="referral-coupon-banner bg-gradient-to-r from-amber-500 via-primary to-amber-600 text-white text-xs font-semibold py-2 px-4 shadow-sm relative z-40 transition-all duration-300"
         x-data="{ show: true }"
         x-show="show"
         x-cloak>
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 mx-auto sm:mx-0">
                <span class="text-base animate-pulse">🎁</span>
                <span>{!! $bannerHtml !!}</span>
            </div>
            <button @click="show = false" type="button" class="text-white/80 hover:text-white font-bold p-1 leading-none shrink-0 cursor-pointer" title="Schließen">&times;</button>
        </div>
    </div>
@endif

<header>
    @include('frontend.navigation.navigation')
</header>

<main class="relative overflow-x-hidden">
    <div class="relative">
        {{ $slot }}

        @if(auth()->guard('customer')->check() && app(\App\Services\Gamification\GamificationService::class)->isGameModeActive())
            <livewire:customer.gamification.game-spark-hunt-component />
        @endif
    </div>
</main>

{{-- Footer --}}
@include('frontend.footer.footer')

@include('components.gamification.persistent-bgm-dock')

{{-- 2. Der Chat bleibt im Hintergrund geladen, wird aber vom Dock gesteuert --}}
{{-- FunkiChat removed as per request --}}

@livewireScripts
</body>
</html>

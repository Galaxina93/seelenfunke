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
    ];
    $defaultRobots = in_array($currentRouteName, $noindexRoutes, true)
        ? 'noindex, nofollow'
        : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    $resolvedRobots = $robots ?: ($sectionRobots ?: $defaultRobots);

    $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
    $founderName = shop_setting('owner_proprietor', 'Alina Steinhauer');
    $streetAddress = trim(shop_setting('company_street', 'Carl-Goerdeler-Ring') . ' ' . shop_setting('company_street_number', '26'));
    $postalCode = shop_setting('company_zip', '38518');
    $city = shop_setting('company_city', 'Gifhorn');
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
                    'areaServed' => ['DE', 'AT', 'CH'],
                ],
                'areaServed' => [
                    ['@type' => 'Country', 'name' => 'DE'],
                    ['@type' => 'Country', 'name' => 'AT'],
                    ['@type' => 'Country', 'name' => 'CH'],
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
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- SEO Meta --}}
    <title>{{ $resolvedTitle }}</title>
    <meta name="description" content="{{ $resolvedDescription }}">
    <meta name="keywords" content="Personalisierte Geschenke, Lasergravur, Glasfoto, K9 Kristallglas, Trophäe gravieren, Schieferplatte graviert, 3D Konfigurator, Gifhorn, Braunschweig, Wolfsburg, Hannover, Fotogeschenk, Unikat, Mein Seelenfunke">
    <meta name="robots" content="{{ $resolvedRobots }}">
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

    {{-- Canonical & Machine-Readable Discovery (Sitemap & AI Search LLMs.txt) --}}
    <link rel="canonical" href="{{ $resolvedCanonical }}">
    <link rel="sitemap" type="application/xml" title="Sitemap" href="{{ url('/sitemap.xml') }}">
    <link rel="alternate" type="text/plain" title="LLMs.txt – AI Search Summary" href="{{ url('/llms.txt') }}">
    <link rel="alternate" type="text/plain" title="LLMs-Full.txt – AI Full Product & Shop Knowledge" href="{{ url('/llms-full.txt') }}">

    {{-- Global Schema.org JSON-LD (Organization + WebSite) --}}
    <script type="application/ld+json">{!! json_encode($globalSchemaGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @stack('structured_data')
    @stack('head')

    {{-- Favicon --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : 2 }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : 2 }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=2">

    {{-- Styles --}}
    <link rel="preload" href="{{ mix('css/app.css') }}" as="style">
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">

    <!-- cookieconsent -->
    <link rel="stylesheet" href="{{ asset('lib/dp_cookieconsent/css/cookieconsent.css') }}" media="print" onload="this.media='all'">
    <link rel="modulepreload" href="{{ asset('lib/dp_cookieconsent/js/cookieconsent.umd.js') }}">
    <script type="module" src="{{ asset('lib/dp_cookieconsent/js/cookieconsent-config.js') }}"></script>

    {{-- Swiper --}}
    <link href="{{ asset('components/swiper/swiper-bundle.min.css') }}" rel="stylesheet" media="print" onload="this.media='all'">
    <script src="{{ asset('components/swiper/swiper-bundle.min.js') }}" defer></script>

    {{-- Scripts --}}
    <script src="{{ asset('js/app.js') }}" defer></script>

    {{-- Livewire --}}
    @livewireStyles
    <script>
        // Theme Initialisierung: Standardmäßig Light Mode
        (function() {
            try {
                if (localStorage.theme === 'dark') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.classList.add('light');
                }
            } catch (e) {
                document.documentElement.classList.remove('dark');
                document.documentElement.classList.add('light');
            }
        })();

        document.addEventListener('livewire:navigated', function() {
            try {
                if (localStorage.theme === 'dark') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.classList.add('light');
                }
            } catch (e) {}
        });

        // Unterdrückt den harmlosen Alpine/Livewire Fehler bei abgebrochenen CSS-Animationen
        window.addEventListener('unhandledrejection', function(event) {
            if (event.reason && event.reason.isFromCancelledTransition === true) {
                event.preventDefault();
            }
        });
    </script>
    @include("components.layouts.partials.frontend-error-tracker")
</head>

<body class="min-h-screen overflow-x-hidden antialiased">

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
            // Regulärer Gutschein: Gültigkeit prüfen
            $voucher = \App\Models\Marketing\MarketingVoucher::where('code', $activeReferralCoupon)->where('is_active', true)->first();
            if ($voucher && $voucher->isValid()) {
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

<main class="relative">
    <div class="relative">
        {{ $slot }}

        @auth('customer')
            <livewire:customer.gamification.game-spark-hunt-component />
        @endauth
    </div>
</main>

{{-- Footer --}}
@include('frontend.footer.footer')

{{-- 2. Der Chat bleibt im Hintergrund geladen, wird aber vom Dock gesteuert --}}
{{-- FunkiChat removed as per request --}}

@livewireScripts
</body>
</html>

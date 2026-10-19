<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- SEO Meta --}}
    <title>@yield('title', 'Personalisierte Geschenke & exklusive Lasergravur | Mein Seelenfunke')</title>

    {{-- Meta Description: Max. 160 Zeichen, inkl. Keywords und Handlungsaufforderung --}}
    <meta name="description"
          content="@yield('meta_description', 'Verwandle Momente in Ewigkeit ✨ Hochwertige Unikate aus Glas, Schiefer & Metall. Handveredelt in Deutschland. Jetzt dein persönliches Geschenk gestalten!')">

    {{-- Keywords: Fokus auf deine Produkte + Region (für lokales SEO) --}}
    <meta name="keywords"
          content="Personalisierte Geschenke, Lasergravur, Glasfoto, Spotify Code, Schieferplatte graviert, Gifhorn, Braunschweig, Wolfsburg, Hannover, Fotogeschenk, Unikat">

    <meta name="robots" content="index, follow">
    <meta name="author" content="Mein Seelenfunke - [Dein Name]">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Open Graph / Social Media (Wichtig, damit Links in WhatsApp/Insta gut aussehen) --}}
    <meta property="og:title" content="@yield('og_title', 'Mein Seelenfunke – Geschenke, die bleiben.')">
    <meta property="og:description"
          content="@yield('og_description', 'Entdecke handveredelte Unikate aus Glas und Schiefer. Perfekt für Geburtstage, Hochzeiten & besondere Momente.')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image"
          content="@yield('og_image', asset('shop/projekt/logo/mein-seelenfunke-logo.png'))">

    <!-- Canonical -->
    <link rel="canonical" href="{{ url()->current() }}">

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

<main>
    <div>
        {{ $slot }}
    </div>
</main>

{{-- Footer --}}
@include('frontend.footer.footer')

{{-- 2. Der Chat bleibt im Hintergrund geladen, wird aber vom Dock gesteuert --}}
{{-- FunkiChat removed as per request --}}

@livewireScripts
</body>
</html>

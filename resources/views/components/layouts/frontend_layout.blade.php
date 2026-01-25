<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- SEO Meta --}}
        <title>@yield('title', 'Personalisierte Geschenke & exklusive Lasergravur | Mein Seelenfunke')</title>

        {{-- Meta Description: Max. 160 Zeichen, inkl. Keywords und Handlungsaufforderung --}}
        <meta name="description" content="Verwandle Momente in Ewigkeit ✨ Hochwertige Unikate aus Glas, Schiefer & Metall. Handveredelt in Deutschland. Jetzt dein persönliches Geschenk gestalten!">

        {{-- Keywords: Fokus auf deine Produkte + Region (für lokales SEO) --}}
        <meta name="keywords" content="Personalisierte Geschenke, Lasergravur, Glasfoto, Spotify Code, Schieferplatte graviert, Gifhorn, Braunschweig, Wolfsburg, Hannover, Fotogeschenk, Unikat">

        <meta name="robots" content="index, follow">
        <meta name="author" content="Mein Seelenfunke - [Dein Name]">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        {{-- Open Graph / Social Media (Wichtig, damit Links in WhatsApp/Insta gut aussehen) --}}
        <meta property="og:title" content="Mein Seelenfunke – Geschenke, die bleiben.">
        <meta property="og:description" content="Entdecke handveredelte Unikate aus Glas und Schiefer. Perfekt für Geburtstage, Hochzeiten & besondere Momente.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('images/logo/mein-projekt-logo.jpg') }}"> {{-- Pfad zu einem schönen Produktbild anpassen --}}

        <!-- Canonical -->
        <link rel="canonical" href="{{ url()->current() }}">

        {{-- Fav Icon --}}
        <link rel="icon" href="{{ asset('images/projekt/logo/favicon.ico') }}" type="image/x-icon"/>

        {{-- Styles --}}
        <link rel="stylesheet" href="{{ mix('css/app.css') }}">

        <!-- cookieconsent -->
        <link rel="stylesheet" href="{{ asset('lib/dp_cookieconsent/css/cookieconsent.css') }}">
        <script type="module" src="{{ asset('lib/dp_cookieconsent/js/cookieconsent-config.js') }}"></script>

        {{-- Swiper --}}
        <link href="{{ asset('components/swiper/swiper-bundle.min.css') }}" rel="stylesheet">
        <script src="{{ asset('components/swiper/swiper-bundle.min.js') }}"></script>

        {{-- Scripts --}}
        <script src="{{ asset('js/app.js') }}" defer></script>

        {{-- Livewire --}}
        @livewireStyles
    </head>

    <body class="min-h-screen overflow-x-hidden antialiased">

        @include('frontend.partials.site_btn_up_and_down')

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

        @livewireScripts
    </body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title ?? ('Kundenkonto | ' . config('app.name', 'Mein Seelenfunke')) }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : 2 }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : 2 }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=2">

    <link rel="stylesheet" href="{{ mix('css/app.css') }}">

    <script src="{{ asset('js/app.js') }}" defer></script>

    {{--<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>--}}

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

<body class="min-h-screen font-sans antialiased">

<main>
    {{-- "container" entfernt und durch "w-full" ersetzt für volle Breite --}}
    <div class="w-full">
        {{ $slot }}
    </div>
</main>

@livewireScripts

</body>

</html>

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

    @php
        $cssVersion = file_exists(public_path('css/app.css')) ? filemtime(public_path('css/app.css')) : 1;
        $jsVersion = file_exists(public_path('js/app.js')) ? filemtime(public_path('js/app.js')) : 1;
    @endphp
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $cssVersion }}">
    <script src="{{ asset('js/app.js') }}?v={{ $jsVersion }}" defer></script>

    @livewireStyles

    <script>
        // Gast-/Loginbereich: Immer im hellen Manufaktur-Modus (Admin-Darkmode in localStorage.theme bleibt für /admin unberührt)
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

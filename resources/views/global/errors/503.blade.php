<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wartungsarbeiten | Mein Seelenfunke</title>

    {{-- Tailwind CSS via CDN ist für Wartungsseiten okay, ignoriere die Warnung in der Konsole --}}
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* Lokale Fonts einbinden (DSGVO konform) */
        @font-face {
            font-family: 'Playfair Display';
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url('/fonts/playfair-display-v40-latin-regular.woff2') format('woff2');
        }
        @font-face {
            font-family: 'Playfair Display';
            font-style: italic;
            font-weight: 400;
            font-display: swap;
            src: url('/fonts/playfair-display-v40-latin-italic.woff2') format('woff2');
        }
        @font-face {
            font-family: 'Playfair Display';
            font-style: normal;
            font-weight: 700;
            font-display: swap;
            src: url('/fonts/playfair-display-v40-latin-700.woff2') format('woff2');
        }

        @font-face {
            font-family: 'Montserrat';
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url('/fonts/montserrat-v31-latin-regular.woff2') format('woff2');
        }
        @font-face {
            font-family: 'Montserrat';
            font-style: normal;
            font-weight: 700;
            font-display: swap;
            src: url('/fonts/montserrat-v31-latin-700.woff2') format('woff2');
        }

        body { font-family: 'Montserrat', sans-serif; }
        h1 { font-family: 'Playfair Display', serif; }

        .sparkle-bg {
            background: radial-gradient(circle at 50% 30%, #fffcf5 0%, #fdfbf7 100%);
        }
    </style>
</head>
<body class="sparkle-bg text-gray-800 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

{{-- Hintergrund --}}
<div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0 pointer-events-none">
    <div class="absolute top-[10%] left-[20%] w-72 h-72 bg-amber-100/50 rounded-full blur-3xl opacity-60 mix-blend-multiply animate-pulse" style="animation-duration: 4s;"></div>
    <div class="absolute bottom-[15%] right-[20%] w-96 h-96 bg-orange-50/60 rounded-full blur-3xl opacity-60 mix-blend-multiply animate-pulse" style="animation-duration: 6s; animation-delay: 1s;"></div>
</div>

{{-- Main Card --}}
<div class="text-center max-w-2xl w-full relative z-10 bg-white/70 backdrop-blur-md p-8 md:p-14 rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/80">

    {{-- Logo --}}
    <div class="mb-10 flex justify-center">
        {{-- FIX: asset() statt public_path() für Browser-Anzeige --}}
        <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}"
             alt="Mein Seelenfunke Logo"
             class="h-32 w-auto drop-shadow-md object-contain mx-auto">
    </div>

    <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4 italic leading-tight tracking-tight">
        Wir polieren gerade <br>unsere Unikate.
    </h1>

    <div class="flex justify-center items-center gap-3 my-8 opacity-70">
        <div class="h-[1px] w-16 bg-gradient-to-r from-transparent via-amber-400 to-transparent"></div>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
            <path d="M10 2a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 2zM10 15a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 15zM10 7a3 3 0 100 6 3 3 0 000-6zM15.657 5.757a.75.75 0 00-1.06-1.06l-1.061 1.06a.75.75 0 001.06 1.06l1.06-1.06zM6.464 14.95a.75.75 0 00-1.06-1.06l-1.06 1.06a.75.75 0 001.06 1.06l1.06-1.06zM18 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 0118 10zM5 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 015 10zM14.596 15.657a.75.75 0 001.06-1.06l-1.06-1.061a.75.75 0 10-1.06 1.06l1.06 1.06zM5.404 6.464a.75.75 0 001.06-1.06l-1.06-1.06a.75.75 0 10-1.06 1.06l1.06 1.06z" />
        </svg>
        <div class="h-[1px] w-16 bg-gradient-to-r from-transparent via-amber-400 to-transparent"></div>
    </div>

    <p class="text-lg text-gray-600 mb-10 leading-relaxed font-light md:px-10">
        Unser Shop macht eine kurze kreative Pause für technische Verbesserungen, damit dein Erlebnis noch schöner wird.
        <br class="hidden md:block mt-2"> Wir sind gleich wieder da, um deine Momente in Ewigkeit zu verwandeln.
    </p>

    <div class="inline-flex items-center gap-3 px-6 py-3 bg-gradient-to-b from-white to-gray-50 border border-amber-100/80 rounded-full shadow-[0_2px_8px_-2px_rgba(251,191,36,0.15)] text-sm text-gray-600 mb-4">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500 shadow-sm border border-amber-200"></span>
            </span>
        <span class="font-medium tracking-wider uppercase text-[11px]">Wartungsarbeiten aktiv</span>
    </div>

    <div class="mt-14 text-xs text-gray-400/80 font-light tracking-[0.2em] uppercase border-t border-gray-100 pt-6 w-2/3 mx-auto">
        &copy; {{ date('Y') }} Mein Seelenfunke <span class="mx-2 text-amber-300">•</span> Handveredelte Unikate
    </div>
</div>

</body>
</html>

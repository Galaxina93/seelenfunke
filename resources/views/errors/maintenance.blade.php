<!DOCTYPE html>
<html lang="de" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, noarchive">
    <title>{{ $companyName ?? 'Mein Seelenfunke' }} · Manufaktur-Wartung</title>
    <link rel="icon" type="image/png" href="{{ asset('shop/projekt/logo/logo-up.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="min-h-screen bg-[#FAF8F5] text-gray-900 font-sans antialiased flex flex-col justify-between selection:bg-[#C5A059]/20 selection:text-gray-900">

    <main class="flex-1 flex items-center justify-center px-4 py-12 sm:py-16">
        <div class="w-full max-w-xl bg-white rounded-3xl border border-[#E8DFCE] shadow-sm p-8 sm:p-12 text-center">
            <div class="flex justify-center mb-6">
                <img
                    src="{{ asset('shop/projekt/logo/mein-seelenfunke-logo.svg') }}"
                    onerror="this.onerror=null;this.src='{{ asset('shop/projekt/logo/mein-seelenfunke-logo.png') }}';"
                    alt="{{ $companyName ?? 'Mein Seelenfunke' }}"
                    class="h-14 sm:h-16 w-auto object-contain">
            </div>

            <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full border border-[#C5A059]/40 bg-[#FAF6EE] text-[#8F6E2F] text-[11px] font-bold uppercase tracking-widest mb-5">
                <span class="w-1.5 h-1.5 rounded-full bg-[#C5A059]"></span>
                <span>Manufaktur Gifhorn</span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 tracking-tight mb-4">
                Wir bereiten gerade unseren Shop für dich vor
            </h1>

            <p class="text-sm sm:text-base text-gray-600 leading-relaxed max-w-md mx-auto mb-8">
                In unserer Laser-Manufaktur in Gifhorn entsteht aktuell alles für den offiziellen Start deiner persönlichen Seelenstücke. Wir sind in Kürze für dich da.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-left border-t border-gray-100 pt-6">
                <div class="flex items-center gap-2 text-xs font-semibold text-gray-700">
                    <span class="text-[#C5A059] font-bold">✓</span>
                    <span>Handveredelt in Gifhorn</span>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold text-gray-700">
                    <span class="text-[#C5A059] font-bold">✓</span>
                    <span>Präzise UV-Lasergravur</span>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold text-gray-700">
                    <span class="text-[#C5A059] font-bold">✓</span>
                    <span>Persönliche Unikate</span>
                </div>
            </div>
        </div>
    </main>

    <footer class="border-t border-[#EAE3D5] bg-white/80 py-6 px-4 text-center text-xs text-gray-500">
        <div class="max-w-3xl mx-auto space-y-2">
            <p class="font-medium text-gray-700">
                {{ $companyName ?? 'Mein Seelenfunke' }} · Inhaberin: {{ $proprietor ?? 'Alina Steinhauer' }} · {{ $street ?? 'Carl-Goerdeler-Ring 26' }}, {{ $zipCity ?? '38518 Gifhorn' }}
            </p>
            <p>
                Kontakt:
                <a href="mailto:{{ $email ?? 'kontakt@mein-seelenfunke.de' }}" class="text-gray-700 hover:text-[#C5A059] underline transition-colors">{{ $email ?? 'kontakt@mein-seelenfunke.de' }}</a>
                · Tel. {{ $phone ?? '+49 1590 1966864' }}
                @if(!empty($ustId))
                    · USt-IdNr.: {{ $ustId }}
                @endif
            </p>
            <div class="flex items-center justify-center gap-4 pt-1 text-[11px] font-semibold text-gray-600">
                <a href="{{ url('/impressum') }}" class="hover:text-[#C5A059] transition-colors">Impressum</a>
                <span>·</span>
                <a href="{{ url('/datenschutz') }}" class="hover:text-[#C5A059] transition-colors">Datenschutz</a>
                <span>·</span>
                <a href="{{ url('/login') }}" class="text-gray-400 hover:text-gray-700 transition-colors">Verwaltung</a>
            </div>
        </div>
    </footer>

</body>
</html>

@php
    $verifiedUsersCount = \Illuminate\Support\Facades\Cache::remember('universe_verified_users_count', 3600, function () {
        $count = 0;
        try {
            $count += \Illuminate\Support\Facades\DB::table('customer_profiles')->whereNotNull('email_verified_at')->count();
            $count += \Illuminate\Support\Facades\DB::table('admin_profiles')->whereNotNull('email_verified_at')->count();
            $count += \Illuminate\Support\Facades\DB::table('employee_profiles')->whereNotNull('email_verified_at')->count();
        } catch (\Exception $e) {
            $count = 0;
        }
        return $count;
    });

    // Basis-Sterne (150) + echte Nutzer als reiner Integer-Wert für JS
    $totalStars = (int)(150 + $verifiedUsersCount);
@endphp

<section id="home"
         class="relative overflow-hidden text-white bg-gray-950"
         x-data
         x-init="setTimeout(() => { if(window.startUniverseEngine) window.startUniverseEngine($el, {{ $totalStars }}) }, 350)"
         aria-label="Mein Seelenfunke - Personalisierte Geschenke">

    {{-- INTERAKTIVES UNIVERSUM CANVAS --}}
    <canvas class="absolute inset-0 z-0 w-full h-full pointer-events-none" wire:ignore></canvas>

    {{-- Zartes Overlay für bessere Lesbarkeit des Textes --}}
    <div class="absolute inset-0 bg-gradient-to-b from-gray-950/40 to-gray-950/90 z-0 pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-36 relative z-10">
        <div class="text-center">

            {{-- Hauptüberschrift --}}
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-serif font-bold mb-6 leading-tight">
                <span class="text-primary">Ein Funke, der bleibt.</span><br>
                Personalisierte Unikate für die Ewigkeit.
            </h1>

            {{-- Unterüberschrift (Volles Sortiment + Emotion + Live-3D-Konfigurator) --}}
            <p class="text-lg md:text-2xl mb-12 opacity-90 font-light max-w-3xl mx-auto leading-relaxed">
                Handveredelter Gravur-Schmuck aus vergoldetem Edelstahl, massive Schlüssel- und Haustiermarken, 360° Kristallgläser und exklusive K9-Kristalltrophäen.<br class="hidden md:block">
                Gestalte dein Unikat live in 3D und werde einer von unzähligen <span class="text-primary font-bold">leuchtenden Sternen</span> in unserem Universum.
            </p>

            {{-- Buttons --}}
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-12">
                {{-- Button 1: Hauptaktion (Zum Shop) --}}
                <a href="{{ route('shop') }}"
                   class="bg-primary text-gray-900 px-8 py-4 rounded-full font-bold text-lg hover:bg-white hover:text-primary-dark transition-all transform hover:scale-105 shadow-lg shadow-primary/30"
                   aria-label="Jetzt personalisieren">
                    Sortiment öffnen
                </a>

                {{-- Button 2: Sekundär (Kosten Kalkulator B2B) --}}
                <a href="/calculator" target="_blank"
                   class="bg-transparent border-2 border-primary text-primary px-8 py-4 rounded-full font-semibold text-lg hover:bg-primary hover:text-white transition-all transform hover:scale-105"
                   aria-label="Angebotskalkulator">
                    Angebotskalkulator öffnen
                </a>
            </div>

            {{-- Trust-Elemente --}}
            <div class="flex flex-wrap justify-center items-center gap-x-8 gap-y-3 text-white mt-8 opacity-90 text-sm md:text-base">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>Deutsche Manufaktur in Gifhorn</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span>High-End 5W UV- & Faserlaser</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                    <span>Live-3D-Echtzeit-Konfigurator</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    <span>Eingetragene Marke & Deutscher Support</span>
                </div>
            </div>

        </div>
    </div>

    {{-- Dekorative Glow-Effekte --}}
    <div class="absolute top-20 left-10 w-32 h-32 bg-primary opacity-20 blur-3xl rounded-full floating-animation pointer-events-none z-0"></div>
    <div class="absolute bottom-20 right-10 w-40 h-40 bg-primary-light opacity-20 blur-3xl rounded-full floating-animation pointer-events-none z-0" style="animation-delay: 1s;"></div>
</section>



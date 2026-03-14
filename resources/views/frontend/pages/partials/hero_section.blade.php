@php
    $verifiedUsersCount = 0;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('customer_profiles')) {
            $verifiedUsersCount += \Illuminate\Support\Facades\DB::table('customer_profiles')->whereNotNull('email_verified_at')->count();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('admin_profiles')) {
            $verifiedUsersCount += \Illuminate\Support\Facades\DB::table('admin_profiles')->whereNotNull('email_verified_at')->count();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('employee_profiles')) {
            $verifiedUsersCount += \Illuminate\Support\Facades\DB::table('employee_profiles')->whereNotNull('email_verified_at')->count();
        }
    } catch (\Exception $e) {
        $verifiedUsersCount = 0;
    }

    // Basis-Sterne (150) + echte Nutzer als reiner Integer-Wert für JS
    $totalStars = (int)(150 + $verifiedUsersCount);
@endphp

<section id="home"
         class="relative pt-16 overflow-hidden text-white bg-gray-950"
         x-data
         x-init="setTimeout(() => { if(window.startUniverseEngine) window.startUniverseEngine($el, {{ $totalStars }}) }, 100)"
         aria-label="Mein Seelenfunke - Personalisierte Geschenke">

    {{-- INTERAKTIVES UNIVERSUM CANVAS --}}
    <canvas class="absolute inset-0 z-0 w-full h-full pointer-events-none" wire:ignore></canvas>

    {{-- Zartes Overlay für bessere Lesbarkeit des Textes --}}
    <div class="absolute inset-0 bg-gradient-to-b from-gray-950/40 to-gray-950/90 z-0 pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-32 md:py-48 relative z-10">
        <div class="text-center">

            {{-- Hauptüberschrift --}}
            <h1 class="text-3xl md:text-6xl font-serif font-bold mb-6 floating-animation leading-tight">
                <span class="text-primary">Ein Funke, der bleibt.</span><br>
                Personalisierte Unikate für die Ewigkeit.
            </h1>

            {{-- Unterüberschrift (Ohne konkrete Zahlen, mehr Storytelling) --}}
            <p class="text-lg md:text-2xl mb-12 opacity-90 font-light max-w-3xl mx-auto">
                Handveredelte Geschenke aus Glas, Schiefer & Metall. <br class="hidden md:block">
                Werde einer von unzähligen <span class="text-primary font-bold">leuchtenden Sternen</span> in unserem Universum.
            </p>

            {{-- Buttons --}}
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-12">
                {{-- Button 1: Hauptaktion (Zum Shop) --}}
                <a href="{{ route('shop') }}"
                   class="bg-primary text-white px-8 py-4 rounded-full font-semibold text-lg hover:bg-white hover:text-primary-dark transition-all transform hover:scale-105 shadow-lg shadow-primary/30 pulse-button"
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
            <div class="flex flex-col md:flex-row justify-center items-center gap-6 text-white mt-8 opacity-80 text-sm md:text-base">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>Made in Germany</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Schneller Versand</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    <span>Mit Liebe verpackt</span>
                </div>
            </div>

        </div>
    </div>

    {{-- Dekorative Glow-Effekte --}}
    <div class="absolute top-20 left-10 w-32 h-32 bg-primary opacity-20 blur-3xl rounded-full floating-animation pointer-events-none z-0"></div>
    <div class="absolute bottom-20 right-10 w-40 h-40 bg-primary-light opacity-20 blur-3xl rounded-full floating-animation pointer-events-none z-0" style="animation-delay: 1s;"></div>
</section>



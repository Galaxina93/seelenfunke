@props([
    'compact' => false,
    'darkAware' => false,
    'showActionButtons' => true,
])

@php
    $isCustomerLoggedIn = auth()->guard('customer')->check();
    $primaryHref = $isCustomerLoggedIn ? route('customer.reviews') : (route('contact') . '#contact');
    $primaryLabel = $isCustomerLoggedIn ? 'Feedback & Erfahrung teilen' : 'Feedback oder Idee senden';
    $secondaryHref = route('contact') . '#contact';

    $surfaceClass = $darkAware
        ? 'bg-[#FAF8F5] dark:bg-gray-900/80 border border-[#C5A059]/35 dark:border-[#C5A059]/30 text-gray-900 dark:text-white'
        : 'bg-[#FAF8F5] border border-[#E8DFCE] text-gray-900';

    $subTextClass = $darkAware
        ? 'text-gray-600 dark:text-gray-300'
        : 'text-gray-600';

    $badgeClass = $darkAware
        ? 'bg-white dark:bg-gray-950 border border-[#C5A059]/40 text-[#8a6820] dark:text-[#C5A059]'
        : 'bg-white border border-[#C5A059]/40 text-[#8a6820]';

    $secondaryBtnClass = $darkAware
        ? 'bg-white dark:bg-gray-950 hover:border-[#C5A059] border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-200'
        : 'bg-white hover:border-[#C5A059] border border-[#E8DFCE] text-gray-800';
@endphp

<div data-customer-feedback-banner="true"
     {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-2xl transition-colors ' . ($compact ? 'p-4 sm:p-5 ' : 'p-5 sm:p-7 ') . $surfaceClass]) }}>

    <style>
        @keyframes sfFeedbackRingOrbit {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes sfFeedbackMedallionFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-2.5px); }
        }
        .sf-feedback-orbit {
            transform-origin: 50% 50%;
            animation: sfFeedbackRingOrbit 34s linear infinite;
        }
        .sf-feedback-float {
            animation: sfFeedbackMedallionFloat 4.2s ease-in-out infinite;
        }
        @media (prefers-reduced-motion: reduce) {
            .sf-feedback-orbit, .sf-feedback-float { animation: none !important; }
        }
    </style>

    {{-- Flüsterleise architektonische Koordinaten- & Wellen-Geometrie (0 CPU Belastung) --}}
    <svg class="pointer-events-none absolute right-0 top-0 h-full w-64 opacity-[0.14] text-[#C5A059]" viewBox="0 0 260 140" fill="none" aria-hidden="true">
        <path d="M18 118C74 88 128 96 184 62C212 45 236 24 256 8" stroke="currentColor" stroke-width="1" stroke-dasharray="3 5"/>
        <path d="M48 134C102 106 154 110 212 74C232 61 247 46 258 34" stroke="currentColor" stroke-width="0.8"/>
        <circle cx="212" cy="42" r="28" stroke="currentColor" stroke-width="0.75" stroke-dasharray="2 4"/>
    </svg>

    <div class="relative z-10 flex flex-col {{ $compact ? 'sm:flex-row sm:items-center gap-3.5' : 'md:flex-row md:items-center justify-between gap-5' }}">
        <div class="flex items-start sm:items-center gap-4 min-w-0">
            {{-- Skulpturiertes Champagner-Gold Vektor-Medaillon --}}
            <div class="relative {{ $compact ? 'w-11 h-11' : 'w-14 h-14' }} shrink-0 flex items-center justify-center rounded-2xl bg-white dark:bg-gray-950 border border-[#C5A059]/40 shadow-xs">
                <svg class="absolute inset-1 w-[calc(100%-8px)] h-[calc(100%-8px)] text-[#C5A059]/55 sf-feedback-orbit" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                    <circle cx="24" cy="24" r="21" stroke="currentColor" stroke-width="1" stroke-dasharray="3 5"/>
                </svg>
                <svg class="{{ $compact ? 'w-5 h-5' : 'w-6 h-6' }} text-[#C5A059] sf-feedback-float relative z-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 11.625c0-.621.504-1.125 1.125-1.125.47 0 .873.289 1.041.701l.084.205.084-.205a1.125 1.125 0 0 1 2.166.424c0 1.242-2.25 2.625-2.25 2.625s-2.25-1.383-2.25-2.625Z" fill="currentColor" stroke="none"/>
                </svg>
            </div>

            <div class="min-w-0 text-left">
                @if(!$compact)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-[0.18em] mb-1.5 {{ $badgeClass }}">
                        <span class="text-[#C5A059]" aria-hidden="true">✓</span>
                        <span>Gemeinsam besser werden</span>
                    </span>
                @endif
                <h3 class="{{ $compact ? 'text-xs sm:text-sm' : 'text-base sm:text-lg font-serif' }} font-bold leading-snug">
                    Dein ehrliches Feedback macht uns jeden Tag besser
                </h3>
                <p class="{{ $compact ? 'text-[11px] sm:text-xs mt-0.5' : 'text-xs sm:text-sm mt-1 max-w-2xl' }} leading-relaxed {{ $subTextClass }}">
                    @if($compact)
                        Wir sind auf deine Rückmeldungen angewiesen und freuen uns riesig über jedes Lob, jeden Wunsch und jeden Hinweis, damit wir uns stetig verbessern können.
                    @else
                        Als persönliche Manufaktur sind wir auf dein Kundenfeedback angewiesen. Wir freuen uns über jede Hilfe, deine Ideen und ehrliche Hinweise, damit wir unsere Unikate, unseren Konfigurator und unseren Service laufend für dich verbessern können.
                    @endif
                </p>
            </div>
        </div>

        @if($showActionButtons)
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <a href="{{ $primaryHref }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#C5A059] hover:bg-[#b38e47] text-gray-950 text-xs font-bold tracking-wide transition shadow-2xs whitespace-nowrap">
                    <span>{{ $primaryLabel }}</span>
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                    </svg>
                </a>

                @if($isCustomerLoggedIn && !$compact)
                    <a href="{{ $secondaryHref }}"
                       class="inline-flex items-center justify-center px-3.5 py-2.5 rounded-xl text-xs font-semibold transition whitespace-nowrap {{ $secondaryBtnClass }}">
                        Direkt schreiben
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>

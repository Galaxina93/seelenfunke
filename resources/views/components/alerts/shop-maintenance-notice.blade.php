@props([
    'title' => 'Kurze Wartung am Konfigurator & Bestellsystem',
    'message' => 'Wir führen gerade Wartungsarbeiten durch. Du kannst dich weiterhin in Ruhe im gesamten Shop umschauen. Das Gestalten und Bestellen ist in Kürze wieder freigeschaltet.',
    'showShopButton' => false,
])

@php
    $displayShopButton = filter_var($showShopButton, FILTER_VALIDATE_BOOLEAN);
@endphp

<div data-shop-maintenance-notice="true"
     role="status"
     {{ $attributes->merge(['class' => 'w-full rounded-2xl bg-[#FAF8F5] border border-[#C5A059]/35 p-6 sm:p-7 text-gray-900 shadow-xs']) }}>
    <div class="flex flex-col sm:flex-row items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-white border border-[#C5A059]/30 flex items-center justify-center text-[#C5A059] shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085" />
            </svg>
        </div>

        <div class="flex-1 min-w-0">
            <h3 class="font-serif font-bold text-gray-900 text-lg sm:text-xl leading-snug">
                {{ $title }}
            </h3>
            <p class="mt-1.5 text-sm text-gray-600 leading-relaxed">
                {{ $message }}
            </p>

            @if($displayShopButton)
                <div class="mt-4">
                    <a href="{{ route('shop') }}"
                       class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full bg-white border border-[#C5A059]/50 text-xs font-bold text-gray-900 hover:bg-[#C5A059] hover:text-white hover:border-[#C5A059] transition-colors">
                        <span>Weiter im Shop stöbern</span>
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

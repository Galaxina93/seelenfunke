<div class="relative min-h-[calc(100vh-5rem)] flex flex-col justify-between items-center py-12 md:py-16 px-4 sm:px-6 lg:px-8 overflow-hidden bg-[#FAF8F5] font-sans text-gray-800 selection:bg-[#C5A059]/20 selection:text-gray-900"
     style="--linktree-theme: {{ $themeColor ?: '#C5A059' }};">

    {{-- Optionales dezentes Hintergrund-Video (nur falls im Admin explizit ein eigenes hochgeladen wurde, stark abgedämpft & matt) --}}
    @if(!empty($bgVideo))
        <div class="fixed inset-0 w-full h-full z-0 pointer-events-none">
            <video autoplay loop muted playsinline class="object-cover w-full h-full opacity-10">
                <source src="{{ $bgVideo }}" type="video/mp4">
            </video>
            <div class="absolute inset-0 bg-[#FAF8F5]/90"></div>
        </div>
    @endif

    <!-- Main Content Container -->
    <div class="relative z-10 w-full max-w-md mx-auto flex flex-col items-center">

        <!-- Profile / Brand Section (Matt & Authentisch) -->
        <div class="flex flex-col items-center text-center mb-8">
            <div class="relative mb-4">
                <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-white border-2 border-[#E2D5BE] p-1 flex items-center justify-center overflow-hidden shadow-sm">
                    <img src="{{ $profileImage ?: asset('shop/projekt/about/gruender-welcome-sm.webp') }}"
                         onerror="this.onerror=null; this.src='{{ asset('shop/projekt/logo/logo-up.webp') }}';"
                         alt="{{ shop_setting('company_name', 'Mein Seelenfunke') }} – Alina Steinhauer"
                         class="w-full h-full object-cover rounded-full">
                </div>
                <!-- Dezentes Manufaktur-Siegel (Matt) -->
                <div class="absolute bottom-0.5 right-0.5 bg-[#F3ECE0] text-[#8A6820] p-1.5 rounded-full border-2 border-white shadow-xs"
                     title="Manufaktur Gifhorn">
                    <x-heroicon-s-check-badge class="w-4 h-4" />
                </div>
            </div>

            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 tracking-tight">
                {{ shop_setting('company_name', 'Mein Seelenfunke') }}
            </h1>
            <p class="mt-2 text-sm sm:text-base text-gray-600 max-w-xs leading-relaxed">
                Handveredelte Unikate & persönliche Geschenke aus unserer Manufaktur in Gifhorn.
            </p>
        </div>

        <!-- Links Stack (Mattes Karten-Design ohne künstlichen Glow) -->
        <div class="w-full space-y-3.5">
            @forelse($links as $index => $link)
                @php
                    $isHighlight = $link['type'] === 'highlight';
                @endphp

                <a href="{{ $link['resolved_url'] ?? $link['url'] }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   wire:click="trackClick('{{ $link['id'] }}')"
                   class="w-full group relative flex items-center justify-between p-4 rounded-2xl transition-all duration-200 transform hover:-translate-y-0.5 focus:outline-none
                          {{ $isHighlight
                             ? 'bg-[#F3ECE0] hover:bg-[#EAE0CE] border border-[#D4C2A2] text-gray-900 font-semibold shadow-xs'
                             : 'bg-white hover:bg-[#FDFBF7] border border-gray-200/90 hover:border-[#D4C2A2] text-gray-800 shadow-2xs hover:shadow-xs'
                          }}">

                    <!-- Left Icon -->
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl transition-colors duration-200 shrink-0
                                {{ $isHighlight
                                    ? 'bg-[#8A6820] text-white'
                                    : 'bg-[#FAF7F2] border border-[#EDE5D8] text-[#8A6820] group-hover:bg-[#F3ECE0]' }}">
                        @if($link['icon'] === 'instagram')
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        @elseif($link['icon'] === 'tiktok')
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                        @elseif($link['icon'])
                            @svg('heroicon-o-' . $link['icon'], 'w-5 h-5')
                        @else
                            <x-heroicon-o-link class="w-5 h-5" />
                        @endif
                    </div>

                    <!-- Title -->
                    <span class="flex-1 text-center font-medium text-sm sm:text-base px-3 tracking-normal truncate">
                        {{ $link['title'] }}
                    </span>

                    <!-- Right Arrow / Indicator -->
                    <div class="w-10 flex justify-end items-center text-gray-400 group-hover:text-[#8A6820] transition-colors duration-200 shrink-0">
                        <x-heroicon-m-chevron-right class="w-5 h-5 transform group-hover:translate-x-0.5 transition-transform" />
                    </div>
                </a>
            @empty
                <div class="text-center py-8 px-4 rounded-2xl bg-white border border-gray-200 shadow-2xs">
                    <p class="text-gray-500 text-sm">Aktuell werden unsere Links aktualisiert. Schau gleich wieder vorbei!</p>
                </div>
            @endforelse
        </div>

        <!-- Quick Trust / Shop Info (Rechtssicher & Authentisch) -->
        <div class="mt-10 grid grid-cols-3 gap-2 w-full text-center border-t border-gray-200/80 pt-6">
            <div class="flex flex-col items-center">
                <x-heroicon-o-heart class="w-5 h-5 mb-1.5 text-[#8A6820]" />
                <span class="text-[11px] text-gray-600 uppercase tracking-wider font-semibold">Handveredelt</span>
            </div>
            <div class="flex flex-col items-center border-x border-gray-200/80 px-1">
                <x-heroicon-o-truck class="w-5 h-5 mb-1.5 text-[#8A6820]" />
                <span class="text-[11px] text-gray-600 uppercase tracking-wider font-semibold">Sicherer Versand</span>
            </div>
            <div class="flex flex-col items-center">
                <x-heroicon-o-shield-check class="w-5 h-5 mb-1.5 text-[#8A6820]" />
                <span class="text-[11px] text-gray-600 uppercase tracking-wider font-semibold">Manufaktur</span>
            </div>
        </div>

    </div>

    <!-- Footer Legal Links -->
    <div class="relative z-10 mt-12 text-center">
        <div class="flex items-center justify-center space-x-4 text-xs text-gray-500">
            <a href="{{ route('impressum') }}" class="hover:text-gray-900 transition-colors">Impressum</a>
            <span>&bull;</span>
            <a href="{{ route('datenschutz') }}" class="hover:text-gray-900 transition-colors">Datenschutz</a>
            <span>&bull;</span>
            <a href="{{ route('agb') }}" class="hover:text-gray-900 transition-colors">AGB</a>
        </div>
        <p class="mt-2 text-[11px] text-gray-400">
            &copy; {{ date('Y') }} {{ shop_setting('company_name', 'Mein Seelenfunke') }}. Alle Rechte vorbehalten.
        </p>
    </div>
</div>

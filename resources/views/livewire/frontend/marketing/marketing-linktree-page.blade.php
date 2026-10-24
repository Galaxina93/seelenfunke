<div class="relative min-h-screen flex flex-col justify-between items-center py-12 px-4 sm:px-6 lg:px-8 overflow-hidden bg-gray-950 font-sans selection:bg-[var(--linktree-theme)] selection:text-white"
     style="--linktree-theme: {{ $themeColor }};">
    
    <!-- Background Video & Overlay -->
    <div class="fixed inset-0 w-full h-full z-0 pointer-events-none">
        <video autoplay loop muted playsinline class="object-cover w-full h-full opacity-35 scale-105 filter blur-[2px]">
            <source src="{{ $bgVideo ?: asset('videos/sparks_bg.mp4') }}" type="video/mp4">
        </video>
        <!-- Radial Vignette & Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-b from-gray-950/80 via-gray-950/60 to-gray-950/95"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,transparent_0%,rgba(3,7,18,0.8)_100%)]"></div>
    </div>

    <!-- Main Content Container -->
    <div class="relative z-10 w-full max-w-md mx-auto flex flex-col items-center">
        
        <!-- Profile / Brand Section -->
        <div class="flex flex-col items-center text-center mb-8 animate-fade-in-down">
            <div class="relative group mb-4">
                <div class="absolute -inset-0.5 rounded-full blur opacity-60 group-hover:opacity-100 transition duration-700"
                     style="background: linear-gradient(to right, {{ $themeColor }}, #d97706);"></div>
                <div class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-gray-900 border-2 p-1 flex items-center justify-center overflow-hidden shadow-2xl"
                     style="border-color: {{ $themeColor }}80;">
                    <img src="{{ $profileImage ?: asset('images/projekt_funki/funki_herz.webp') }}" alt="Mein Seelenfunke" class="w-full h-full object-cover rounded-full transform group-hover:scale-105 transition duration-500">
                </div>
                <!-- Verified Badge / Sparkle -->
                <div class="absolute bottom-0 right-0 text-gray-950 p-1.5 rounded-full shadow-lg border-2 border-gray-900"
                     style="background-color: {{ $themeColor }};" title="Offizieller Shop">
                    <x-heroicon-s-sparkles class="w-4 h-4" />
                </div>
            </div>

            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-white tracking-wide flex items-center gap-2 drop-shadow-md">
                {{ shop_setting('company_name', 'Mein Seelenfunke') }}
            </h1>
            <p class="mt-2 text-sm sm:text-base text-gray-300 max-w-xs font-light leading-relaxed">
                Handgefertigte Unikate & veredelte Geschenke mit Herz und Laserpräzision. ✨
            </p>
        </div>

        <!-- Links Stack -->
        <div class="w-full space-y-4">
            @forelse($links as $index => $link)
                @php
                    $isHighlight = $link['type'] === 'highlight';
                @endphp

                <a href="{{ $link['resolved_url'] ?? $link['url'] }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   wire:click="trackClick('{{ $link['id'] }}')"
                   style="animation-delay: {{ $index * 100 }}ms; {{ $isHighlight ? 'border-color: ' . $themeColor . '99; background: linear-gradient(to right, ' . $themeColor . '33, ' . $themeColor . '1A, ' . $themeColor . '33); box-shadow: 0 0 25px ' . $themeColor . '40;' : '' }}"
                   class="w-full group relative flex items-center justify-between p-4 rounded-2xl transition-all duration-300 transform hover:-translate-y-1 active:translate-y-0 focus:outline-none overflow-hidden
                          {{ $isHighlight 
                             ? 'border text-white font-semibold' 
                             : 'bg-gray-900/60 hover:bg-gray-800/80 border border-gray-700/60 hover:border-gray-500/80 text-gray-100 backdrop-blur-md shadow-lg hover:shadow-xl' 
                          }}">
                    
                    @if($isHighlight)
                        <!-- Shimmer Effect for Highlighted Links -->
                        <div class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/10 to-transparent group-hover:animate-[shimmer_1.5s_infinite]"></div>
                    @endif

                    <!-- Left Icon -->
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl transition-colors duration-300 shrink-0 {{ $isHighlight ? '' : 'bg-gray-800/80 text-gray-400 group-hover:text-white group-hover:bg-gray-700' }}"
                         style="{{ $isHighlight ? 'background-color: ' . $themeColor . '; color: #030712; box-shadow: 0 4px 6px -1px ' . $themeColor . '4D;' : '' }}">
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
                    <span class="flex-1 text-center font-medium text-sm sm:text-base px-3 tracking-wide truncate">
                        {{ $link['title'] }}
                    </span>

                    <!-- Right Arrow / Indicator -->
                    <div class="w-10 flex justify-end items-center text-gray-500 transition-colors duration-300 shrink-0"
                         style="color: {{ $isHighlight ? $themeColor : '' }};">
                        <x-heroicon-m-chevron-right class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" />
                    </div>
                </a>
            @empty
                <div class="text-center py-8 px-4 rounded-2xl bg-gray-900/50 border border-gray-800 backdrop-blur-md">
                    <x-heroicon-o-sparkles class="w-8 h-8 mx-auto mb-2 opacity-60" style="color: {{ $themeColor }};" />
                    <p class="text-gray-400 text-sm">Aktuell werden unsere Links aktualisiert. Schau gleich wieder vorbei!</p>
                </div>
            @endforelse
        </div>

        <!-- Quick Trust / Shop Info -->
        <div class="mt-10 grid grid-cols-3 gap-2 w-full text-center border-t border-gray-800/80 pt-6">
            <div class="flex flex-col items-center">
                <x-heroicon-o-heart class="w-5 h-5 mb-1" style="color: {{ $themeColor }};" />
                <span class="text-[11px] text-gray-400 uppercase tracking-wider font-medium">Handarbeit</span>
            </div>
            <div class="flex flex-col items-center border-x border-gray-800/80">
                <x-heroicon-o-truck class="w-5 h-5 mb-1" style="color: {{ $themeColor }};" />
                <span class="text-[11px] text-gray-400 uppercase tracking-wider font-medium">Schneller Versand</span>
            </div>
            <div class="flex flex-col items-center">
                <x-heroicon-o-shield-check class="w-5 h-5 mb-1" style="color: {{ $themeColor }};" />
                <span class="text-[11px] text-gray-400 uppercase tracking-wider font-medium">Manufaktur</span>
            </div>
        </div>

    </div>

    <!-- Footer Legal Links -->
    <div class="relative z-10 mt-12 text-center">
        <div class="flex items-center justify-center space-x-4 text-xs text-gray-500">
            <a href="{{ route('impressum') }}" class="hover:text-gray-300 transition-colors">Impressum</a>
            <span>&bull;</span>
            <a href="{{ route('datenschutz') }}" class="hover:text-gray-300 transition-colors">Datenschutz</a>
            <span>&bull;</span>
            <a href="{{ route('agb') }}" class="hover:text-gray-300 transition-colors">AGB</a>
        </div>
        <p class="mt-2 text-[11px] text-gray-600">
            &copy; {{ date('Y') }} {{ shop_setting('company_name', 'Mein Seelenfunke') }}. Alle Rechte vorbehalten.
        </p>
    </div>

    <style>
        @keyframes shimmer {
            100% { transform: translateX(100%); }
        }
    </style>
</div>

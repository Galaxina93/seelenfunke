{{-- resources/views/frontend/partials/action_dock.blade.php --}}
<div x-data="{
        atTop: true,
        atBottom: false,
        vouchersOpen: false,
        {{-- Desktop standardmäßig true (offen), Mobil standardmäßig false (zu) --}}
        dockOpen: window.innerWidth >= 768
    }"
     x-init="
        window.addEventListener('scroll', () => {
            atTop = window.scrollY < 300;
            atBottom = (window.innerHeight + window.scrollY) >= document.body.offsetHeight - 100;
        })
    "
     class="fixed right-0 top-1/2 -translate-y-1/2 z-[9999] flex items-center transition-transform duration-500 ease-[cubic-bezier(0.23,1,0.32,1)]"
     {{-- Verschiebt das Dock basierend auf dem State --}}
     :class="dockOpen ? 'translate-x-0' : 'translate-x-[calc(100%-8px)]'"
>
    {{-- INTERAKTIVE GLOW-ZONE (Toggle für Desktop & Mobil) --}}
    <div @click="dockOpen = !dockOpen; if(!dockOpen) vouchersOpen = false"
         class="absolute left-0 top-0 bottom-0 w-10 cursor-pointer flex items-center justify-center group"
         style="margin-left: -25px;">

        <div class="relative flex items-center justify-center">
            {{-- Pulsierender Glow --}}
            <div class="absolute inset-0 w-3 h-16 bg-primary/40 rounded-full blur-md animate-pulse"></div>

            {{-- Kern-Linie --}}
            <div class="relative w-2 h-14 bg-primary rounded-full shadow-[0_0_20px_rgba(197,160,89,1)] transition-all duration-300 group-hover:h-20"
                 :class="dockOpen ? 'opacity-20' : 'opacity-100'">
            </div>

            {{-- Richtungs-Pfeil --}}
            <div class="absolute text-gray-900 transition-transform duration-500"
                 :class="dockOpen ? 'rotate-0' : 'rotate-180'">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4">
                    <path d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </div>
    </div>

    {{-- DAS HAUPT-DOCK --}}
    <div class="flex flex-col items-center bg-gray-900/95 backdrop-blur-xl py-6 px-2 rounded-l-3xl border-l border-t border-b border-white/10 shadow-[-10px_0_30px_rgba(0,0,0,0.3)] space-y-6 transition-all duration-300 hover:px-3 group/dock relative">

        {{-- 1. GUTSCHEIN TRIGGER --}}
        <button @click="vouchersOpen = !vouchersOpen"
                class="group/icon relative p-2 text-primary hover:bg-primary/20 rounded-xl transition-all"
                title="Gutscheine">

            {{-- Der pulsierende Benachrichtigungspunkt --}}
            <div class="absolute -top-1 -right-1 flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-primary"></span>
            </div>

            {{-- NEU: Heroicon "gift" (outline) --}}
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 transition-transform group-hover/icon:scale-110">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H4.5a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
            </svg>

            {{-- Hover Label --}}
            <span class="absolute right-full mr-4 px-2 py-1 bg-gray-900 text-white text-[10px] font-black rounded opacity-0 group-hover/icon:opacity-100 transition-opacity whitespace-nowrap pointer-events-none tracking-widest uppercase shadow-xl">
                Gutscheine
            </span>
        </button>

        <div class="w-8 h-px bg-white/10"></div>

        {{-- 2. SCROLL NAVIGATION --}}
        <div class="flex flex-col space-y-2">
            <button x-show="!atTop" x-transition @click="window.scrollTo({top: 0, behavior: 'smooth'})"
                    class="p-2 text-white/40 hover:text-white transition-colors" title="Nach oben">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path d="M5 15l7-7 7 7" />
                </svg>
            </button>

            <button x-show="!atBottom" x-transition @click="window.scrollTo({top: document.body.scrollHeight, behavior: 'smooth'})"
                    class="p-2 text-white/40 hover:text-white transition-colors" title="Nach unten">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </div>

        <div class="w-8 h-px bg-white/10"></div>

        {{-- 3. COOKIE KEKS --}}
        <button @click="CookieConsent.showPreferences()"
                class="group/icon relative p-2 text-white/30 hover:text-primary transition-all hover:scale-110"
                title="Cookie-Einstellungen">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M12 21a9 9 0 100-18 9 9 0 000 18z" />
                <path d="M9 8h.01M12 12h.01M15 10h.01M11 16h.01M14 15h.01" stroke-linecap="round" stroke-width="3" />
                <path d="M18.5 12c-1 0-2-1-2-2s1-2 2-2" />
            </svg>
            <span class="absolute right-full mr-4 px-2 py-1 bg-gray-900 text-white text-[10px] font-black rounded opacity-0 group-hover/icon:opacity-100 transition-opacity whitespace-nowrap pointer-events-none tracking-widest uppercase">Cookies</span>
        </button>

        {{-- 4. FUNKI CHAT TRIGGER --}}
        <button @click="$dispatch('toggle-chat')"
                class="group/funki relative p-1 rounded-xl bg-gradient-to-br from-primary to-primary-dark shadow-lg transform active:scale-95 transition-transform">
            <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-9 h-9 rounded-lg object-cover">

            {{-- Grüner Online Punkt --}}
            <div class="absolute -bottom-1 -right-1 flex h-3.5 w-3.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-green-500 border-2 border-gray-900"></span>
            </div>

            {{-- NEU: Hover Text für Funki --}}
            <span class="absolute right-full mr-4 px-2 py-1 bg-gray-900 text-white text-[10px] font-black rounded opacity-0 group-hover/funki:opacity-100 transition-opacity whitespace-nowrap pointer-events-none tracking-widest uppercase">Funki Chat</span>
        </button>
    </div>

    {{-- GUTSCHEIN PANEL --}}
    <div x-show="vouchersOpen"
         x-cloak
         @click.away="vouchersOpen = false"
         x-transition:enter="transition ease-[cubic-bezier(0.23,1,0.32,1)] duration-500"
         x-transition:enter-start="translate-x-10 opacity-0"
         x-transition:enter-end="translate-x-0 opacity-100"
         x-transition:leave="transition ease-in duration-300"
         class="absolute right-full mr-4 bg-white/95 backdrop-blur-2xl w-[85vw] sm:w-[340px] shadow-[-20px_0_50px_rgba(0,0,0,0.15)] rounded-[2rem] sm:rounded-[2.5rem] border border-white/20 overflow-hidden flex flex-col"
    >
        @livewire('frontend.voucher-slider')
    </div>
</div>

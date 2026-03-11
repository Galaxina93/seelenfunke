{{--
@if(!$hasOptedIn)
    <div class="w-full min-h-screen overflow-y-auto bg-gray-950 pb-24 relative scroll-smooth">
        <div class="relative w-full bg-gray-950/80 backdrop-blur-xl flex flex-col xl:flex-row items-center justify-between px-4 sm:px-6 py-4 border-b border-gray-800 z-[250] gap-4 md:gap-6 shadow-lg">
            <div class="flex items-center justify-between w-full xl:w-auto xl:pr-6 xl:border-r border-gray-800 shrink-0">
                <a href="/" target="_blank" class="shrink-0">
                    <img src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}" class="h-16 md:h-20 w-auto hover:scale-105 transition-transform duration-300" alt="Logo">
                </a>
            </div>

            --}}
{{-- GLOBALE PROFIL NAVIGATION (Zentralisiert) --}}{{--

            @livewire('global.profile.profile-dropdown')
        </div>

        <div x-data="optInScreen()" @mousemove="handleMouse($event)" @mouseleave="resetMouse()" class="max-w-[1600px] mx-auto bg-gray-900 rounded-none shadow-[0_20px_50px_rgba(0,0,0,0.5)] border-y border-gray-800 relative p-8 md:p-20 flex flex-col md:flex-row items-center gap-16 overflow-hidden transition-all duration-700 mt-12">
            <div class="absolute inset-0 pointer-events-none transition-opacity duration-300" :style="`background: radial-gradient(circle 600px at ${mouseX}px ${mouseY}px, rgba(197, 160, 89, 0.15), transparent 40%); opacity: ${isHovering ? 1 : 0};`"></div>

            <div x-show="isActivating" style="display: none;" class="fixed inset-0 z-[9000] pointer-events-none flex items-center justify-center overflow-hidden">
                <div x-show="isActivating" x-transition:enter="transition ease-out duration-1000" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="absolute inset-0 bg-gray-950/95 backdrop-blur-xl"></div>
                <div x-show="phase >= 1" x-transition:enter="transition ease-out duration-[1500ms]" x-transition:enter-start="scale-0 opacity-100" x-transition:enter-end="scale-[3] opacity-0" class="absolute w-[30rem] h-[30rem] border-[8px] border-primary rounded-full blur-[4px]"></div>
                <div x-show="phase >= 1" x-transition:enter="transition ease-out duration-[1000ms] delay-100" x-transition:enter-start="scale-0 opacity-100" x-transition:enter-end="scale-[4] opacity-0" class="absolute w-[30rem] h-[30rem] border-[4px] border-white rounded-full"></div>
                <div x-show="phase >= 1" class="relative z-10 w-48 h-48 rounded-full bg-primary/30 blur-2xl animate-pulse"></div>
                <div x-show="flash" x-transition:enter="transition ease-in duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="absolute inset-0 bg-white z-[9010]"></div>
            </div>

            <div class="relative z-10 flex-1">
                @if(count($profileSteps) > 0)
                    <div class="mb-10 flex flex-wrap gap-3 p-5 bg-gray-950 rounded-2xl border border-gray-800 shadow-inner">
                        <span class="w-full block text-[10px] text-gray-500 uppercase tracking-[0.2em] font-black mb-1">Profil vervollständigen:</span>
                        @foreach($profileSteps as $step)
                            <button @click="{!! $step['action'] !!}" class="px-4 py-2 bg-red-500/10 border border-red-500/30 text-red-400 rounded-full text-[9px] font-black uppercase tracking-widest hover:bg-red-500 hover:text-white transition-all shadow-[0_0_15px_rgba(239,68,68,0.2)] animate-pulse">{{ $step['label'] }}</button>
                        @endforeach
                    </div>
                @endif

                <span class="inline-block px-5 py-2 bg-primary/20 text-primary font-black uppercase tracking-widest rounded-full mb-6 border border-primary/40 shadow-[0_0_15px_rgba(197,160,89,0.3)] animate-pulse">Dein neues Shopping-Erlebnis</span>
                <h2 class="text-5xl md:text-6xl lg:text-7xl font-serif font-bold text-white mb-6 leading-tight drop-shadow-md">Einkaufen,<br>weit weg vom <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-amber-300">Standard.</span></h2>
                <p class="text-gray-300 text-lg md:text-xl mb-12 leading-relaxed max-w-2xl">Willkommen in der Manufaktur! Dein Dashboard ist kein einfaches Kundenkonto mehr – es ist spielerisch, interaktiv und lebendig. Sammle Funken, entwickle deinen Begleiter und entdecke Magie.</p>

                <ul class="space-y-6 mb-12">
                    <li class="flex items-center gap-5 text-gray-400 group cursor-default hover:scale-[1.02] hover:translate-x-2 transition-all duration-300 relative" @mouseenter="window.spawnSparks($event)">
                        <div class="absolute -left-2 w-14 h-14 bg-emerald-500/20 rounded-full blur-xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <div class="w-12 h-12 rounded-full bg-gray-800 text-emerald-500 flex items-center justify-center shrink-0 border border-gray-700 group-hover:bg-emerald-500/20 group-hover:border-emerald-500/50 group-hover:text-emerald-400 group-hover:shadow-[0_0_20px_rgba(16,185,129,0.5)] transition-all relative z-10">
                            <svg class="w-6 h-6 group-hover:rotate-12 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <span class="font-medium text-base md:text-lg group-hover:text-white transition-colors relative z-10">Sammle <span class="text-primary font-bold">Funken</span> bei jedem Einkauf</span>
                    </li>
                    <li class="flex items-center gap-5 text-gray-400 group cursor-default hover:scale-[1.02] hover:translate-x-2 transition-all duration-300 relative" @mouseenter="window.spawnSparks($event)">
                        <div class="absolute -left-2 w-14 h-14 bg-emerald-500/20 rounded-full blur-xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <div class="w-12 h-12 rounded-full bg-gray-800 text-emerald-500 flex items-center justify-center shrink-0 border border-gray-700 group-hover:bg-emerald-500/20 group-hover:border-emerald-500/50 group-hover:text-emerald-400 group-hover:shadow-[0_0_20px_rgba(16,185,129,0.5)] transition-all relative z-10">
                            <svg class="w-6 h-6 group-hover:rotate-12 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <span class="font-medium text-base md:text-lg group-hover:text-white transition-colors relative z-10">Schalte <span class="text-white font-bold">Epische 3D-Modelle</span> frei</span>
                    </li>
                    <li class="flex items-center gap-5 text-gray-400 group cursor-default hover:scale-[1.02] hover:translate-x-2 transition-all duration-300 relative" @mouseenter="window.spawnSparks($event)">
                        <div class="absolute -left-2 w-14 h-14 bg-emerald-500/20 rounded-full blur-xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <div class="w-12 h-12 rounded-full bg-gray-800 text-emerald-500 flex items-center justify-center shrink-0 border border-gray-700 group-hover:bg-emerald-500/20 group-hover:border-emerald-500/50 group-hover:text-emerald-400 group-hover:shadow-[0_0_20px_rgba(16,185,129,0.5)] transition-all relative z-10">
                            <svg class="w-6 h-6 group-hover:rotate-12 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <span class="font-medium text-base md:text-lg group-hover:text-white transition-colors relative z-10">Verdiene dir <span class="text-white font-bold">echte Rabatte</span> & 100% Kostenlos</span>
                    </li>
                </ul>

                <button @click="triggerEpicStart($event)" class="group relative px-12 py-6 bg-gradient-to-r from-primary to-primary-dark text-gray-900 rounded-2xl font-black uppercase tracking-widest text-base shadow-[0_0_40px_rgba(197,160,89,0.5)] hover:scale-105 hover:shadow-[0_0_60px_rgba(197,160,89,0.8)] transition-all flex items-center gap-4 overflow-hidden">
                    <div class="absolute inset-0 bg-white/20 transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-700 ease-in-out"></div>
                    Magie jetzt aktivieren
                    <svg class="w-6 h-6 group-hover:translate-x-2 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                </button>
            </div>

            <div class="relative z-10 w-full md:w-5/12 flex justify-center perspective-1000">
                <div class="relative w-72 h-72 md:w-96 md:h-96 transform hover:rotate-y-12 hover:rotate-x-12 transition-transform duration-700 ease-out">
                    <div class="absolute inset-0 bg-primary/30 rounded-full blur-[80px] animate-pulse"></div>
                    <img src="{{ asset('funki/models/images/funki_lvl_5_apprentice.png') }}" draggable="false" class="relative w-full h-full object-contain drop-shadow-[0_30px_30px_rgba(0,0,0,0.8)] animate-[float_6s_ease-in-out_infinite] pointer-events-none select-none">
                </div>
            </div>
        </div>

        <div class="max-w-[1600px] mx-auto px-6 md:px-16 mt-24 relative z-10 animate-fade-in-up delay-300">
            <div class="flex items-center gap-8 mb-12">
                <div class="w-20 h-20 bg-gray-800 rounded-3xl flex items-center justify-center text-primary shadow-[0_0_20px_rgba(197,160,89,0.2)] border border-gray-700">
                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                </div>
                <div>
                    <h2 class="text-4xl md:text-5xl font-serif font-bold text-white mb-2 tracking-tight">Deine Bestellungen</h2>
                    <p class="text-gray-400 font-black uppercase tracking-widest text-[10px]">Historie deiner Schätze</p>
                </div>
            </div>
            <div class="bg-transparent overflow-hidden">
                @include('livewire.customer.partials.orders_section')
            </div>
        </div>
    </div>
@endif
--}}

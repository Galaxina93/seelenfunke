<div class="w-full">
    <div class="relative animate-fade-in-up z-10 min-h-[85vh] flex flex-col items-center justify-center py-10">
        <div class="relative z-10 text-center flex flex-col items-center w-full max-w-6xl px-4 sm:px-6">
            <style>
                @keyframes subtleFloat{0%, 100%{transform: translateY(0px);}50%{transform: translateY(-12px);}}.animate-subtle-float{animation: subtleFloat 6s ease-in-out infinite;}.perspective-1000{perspective: 1000px;}@keyframes slideUpFade{0%{opacity: 0;transform: translateY(40px) scale(0.9);}100%{opacity: 1;transform: translateY(0) scale(1);}}.animate-level-up{animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;}
                </style>

                @if(count($profileSteps) > 0)
                    <div class="mb-8 flex flex-col sm:flex-row flex-wrap items-center justify-center gap-3 p-4 sm:p-5 bg-gray-900 rounded-2xl border border-gray-800 shadow-inner w-full max-w-2xl mx-auto">
                        <span class="w-full block text-[10px] text-gray-500 uppercase tracking-[0.2em] font-black mb-1">Profil vervollständigen:</span>
                        @foreach($profileSteps as $step)
                            <button @click="{!! $step['action'] !!}" class="px-4 py-2 bg-red-500/10 border border-red-500/30 text-red-400 rounded-full text-[9px] font-black uppercase tracking-widest hover:bg-red-500 hover:text-white transition-all shadow-[0_0_15px_rgba(239,68,68,0.2)] animate-pulse">{{ $step['label'] }}</button>
                        @endforeach
                    </div>
                @endif

                <h2 class="text-3xl sm:text-5xl md:text-7xl font-serif font-bold mb-4 tracking-tight text-white drop-shadow-2xl">Willkommen, {{auth()->user()->first_name}}</h2>
                <p class="text-primary text-sm sm:text-xl mb-8 max-w-2xl leading-relaxed drop-shadow-md mx-auto font-medium tracking-wide">Was möchtest du jetzt tun?</p>

                {{-- KACHELN (QUICK ACTIONS) --}}
                <div class="w-full max-w-5xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-16 sm:mb-20 relative z-50">

                    {{-- 0. Profil Kachel --}}
                    <div class="group relative bg-gray-900/80 backdrop-blur-md border border-gray-800 rounded-3xl p-6 overflow-hidden flex flex-col items-center text-center shadow-lg hover:border-gray-700 transition-all duration-500">
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-gray-700/10 via-transparent to-transparent opacity-100"></div>
                        <div class="w-16 h-16 rounded-2xl bg-gray-950 border border-gray-800 text-gray-400 flex items-center justify-center mb-4 shadow-inner group-hover:scale-110 transition-transform duration-500">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-1 relative z-10">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h3>
                        <p class="text-gray-400 text-xs leading-relaxed relative z-10 mb-4">{{ auth()->user()->email }}</p>
                        <div x-data="{ copied: false, code: '{{ strtoupper(explode('-', auth()->guard('customer')->id())[0]) }}' }" class="mt-auto w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-xl text-primary text-xs font-mono font-bold tracking-widest flex items-center justify-between group-hover:border-primary/30 transition-colors relative z-10">
                            <span>Kundennummer:</span>
                            <div class="flex items-center gap-2">
                                <span x-text="code"></span>
                                <button type="button" @click.stop="navigator.clipboard.writeText(code); copied = true; setTimeout(() => copied = false, 2000)" class="text-gray-500 hover:text-primary transition-colors focus:outline-none" title="Nummer kopieren">
                                    <svg x-show="!copied" class="w-4 h-4 cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <svg x-show="copied" style="display: none;" class="w-4 h-4 text-emerald-500 drop-shadow-[0_0_5px_rgba(16,185,129,0.8)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- 1. Bestellungen --}}
                    <a href="{{route('customer.orders')}}" class="group relative bg-gray-900/80 backdrop-blur-md border border-gray-800 rounded-3xl p-6 hover:border-primary/50 transition-all duration-500 overflow-hidden flex flex-col items-center text-center shadow-lg hover:shadow-[0_0_30px_rgba(197,160,89,0.15)] hover:-translate-y-1">
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-primary/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="w-16 h-16 rounded-2xl bg-gray-950 border border-gray-800 text-primary flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-500 shadow-inner">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-2 relative z-10">Deine Bestellungen</h3>
                        <p class="text-gray-400 text-xs leading-relaxed relative z-10">Verfolge den Status deiner Unikate oder sieh dir alte Bestellungen an.</p>
                    </a>

                    {{-- 2. Rechnungen --}}
                    <a href="{{route('customer.invoices')}}" class="group relative bg-gray-900/80 backdrop-blur-md border border-gray-800 rounded-3xl p-6 hover:border-blue-500/50 transition-all duration-500 overflow-hidden flex flex-col items-center text-center shadow-lg hover:shadow-[0_0_30px_rgba(59,130,246,0.15)] hover:-translate-y-1">
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-500/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="w-16 h-16 rounded-2xl bg-gray-950 border border-gray-800 text-blue-400 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-500 shadow-inner">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-2 relative z-10">Deine Rechnungen</h3>
                        <p class="text-gray-400 text-xs leading-relaxed relative z-10">Lade Belege herunter und verwalte deine Dokumente zentral.</p>
                    </a>

                    {{-- 3. Gamification / Spielen --}}
                    <a href="{{route('customer.games')}}" class="group relative bg-gray-900/80 backdrop-blur-md border border-gray-800 rounded-3xl p-6 hover:border-emerald-500/50 transition-all duration-500 overflow-hidden flex flex-col items-center text-center shadow-lg hover:shadow-[0_0_30px_rgba(16,185,129,0.15)] hover:-translate-y-1">
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-emerald-500/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="w-16 h-16 rounded-2xl bg-gray-950 border border-gray-800 text-emerald-400 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-500 shadow-inner">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-.86-1.875-1.915-1.875s-1.915.84-1.915 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a1.5 1.5 0 01-1.5 1.5H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-2 relative z-10">Willst du spielen?</h3>
                        <p class="text-gray-400 text-xs leading-relaxed relative z-10">Minispiele, Funken sammeln und neue Titel freischalten.</p>
                    </a>

                    {{-- 4. Hilfe & Support --}}
                    <a href="{{route('customer.support')}}" class="group relative bg-gray-900/80 backdrop-blur-md border border-gray-800 rounded-3xl p-6 hover:border-amber-500/50 transition-all duration-500 overflow-hidden flex flex-col items-center text-center shadow-lg hover:shadow-[0_0_30px_rgba(251,191,36,0.15)] hover:-translate-y-1">
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-amber-500/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="w-16 h-16 rounded-2xl bg-gray-950 border border-gray-800 text-amber-400 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-500 shadow-inner">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-2 relative z-10">Hilfe & Support</h3>
                        <p class="text-gray-400 text-xs leading-relaxed relative z-10">Fragen zur Bestellung oder Problemen? Wir sind da!</p>
                    </a>

                    {{-- 5. Zurück zum Shop --}}
                    <a href="{{route('shop')}}" class="group relative bg-gray-900/80 backdrop-blur-md border border-gray-800 rounded-3xl p-6 hover:border-purple-500/50 transition-all duration-500 overflow-hidden flex flex-col items-center text-center shadow-lg hover:shadow-[0_0_30px_rgba(168,85,247,0.15)] hover:-translate-y-1">
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-purple-500/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="w-16 h-16 rounded-2xl bg-gray-950 border border-gray-800 text-purple-400 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-500 shadow-inner">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-2 relative z-10">Zurück zum Shop</h3>
                        <p class="text-gray-400 text-xs leading-relaxed relative z-10">Weiter einkaufen und die Manufaktur entdecken.</p>
                    </a>

                </div>

                </div>

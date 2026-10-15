<div>
    <div class="contact-item">

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

            // Basis-Sterne + echte Nutzer (als sicherer Integer)
            $totalStars = (int)(150 + $verifiedUsersCount);
        @endphp

        <section id="contact"
                 class="bg-stone-50 border-t border-gray-200 py-24 relative overflow-hidden"
                 x-data
                 x-init="setTimeout(() => { if(window.startUniverseEngine) window.startUniverseEngine($el, {{ $totalStars }}) }, 100)"
                 aria-label="Kontaktformular und Kontaktdaten">

            {{-- Du brauchst keine id="contact-canvas" mehr! --}}
            <canvas class="absolute inset-0 z-0 w-full h-full pointer-events-none opacity-40" wire:ignore></canvas>

            {{-- Zartes helles Overlay für perfekte Lesbarkeit des Textes und Formulars --}}
            <div class="absolute inset-0 bg-gradient-to-b from-stone-50/80 via-white/85 to-stone-100/90 z-0 pointer-events-none" aria-hidden="true"></div>
            <div class="absolute top-0 right-0 w-96 h-96 bg-primary/10 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid lg:grid-cols-2 gap-12 lg:gap-20 items-center">

                    {{-- LINKE SPALTE: Info & Bild --}}
                    <div class="flex flex-col justify-between h-full space-y-10">

                        <div>
                            <div class="text-primary-dark font-bold tracking-widest uppercase text-sm mb-2">Kontakt & Beratung</div>
                            <h2 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-6 leading-tight">
                                Lass uns <br>
                                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-dark to-primary">sprechen.</span>
                            </h2>
                            <p class="text-gray-600 text-lg leading-relaxed max-w-md">
                                Du hast eine spezielle Idee für eine Lasergravur, Fragen zu einem Unikat oder möchtest einfach Hallo sagen?
                                Nutze das Kontaktformular oder schreibe uns eine E-Mail.
                            </p>
                        </div>

                        {{-- Das Bild "Funki" (Dynamisch mit Alpine.js) --}}
                        <div class="relative w-full flex justify-center lg:justify-start py-6" x-data="{ showRose: @entangle('data_protection') }">
                            {{-- Schein hinter dem Bild ändert sich leicht, wenn die Rose aktiv ist --}}
                            <div class="absolute inset-0 bg-gradient-to-r blur-3xl rounded-full opacity-30 transform scale-75 transition-colors duration-500"
                                 :class="showRose ? 'from-pink-500/20 to-transparent' : 'from-primary/20 to-transparent'"></div>

                            <img :src="showRose ? '{{ asset('shop/projekt/funki/funki_rose.webp') }}' : '{{ asset('shop/projekt/funki/funki_shy.webp') }}'"
                                 alt="Funki Maskottchen hilft bei Kontaktanfragen und Beratung"
                                 width="256" height="256"
                                 loading="lazy"
                                 class="relative z-10 h-48 sm:h-64 object-contain drop-shadow-xl hover:scale-105 transition-all duration-500 ease-in-out">
                        </div>

                        {{-- Kontakt Details --}}
                        <div class="space-y-6">
                            <a href="mailto:kontakt@mein-seelenfunke.de"
                               title="Senden Sie uns eine E-Mail"
                               class="group flex items-center space-x-4 p-4 rounded-2xl border border-gray-200 bg-white hover:border-primary/50 hover:bg-stone-50 hover:shadow-md transition-all duration-300">
                                <div class="w-10 h-10 rounded-full bg-primary/15 flex items-center justify-center text-primary-dark group-hover:bg-primary group-hover:text-gray-900 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">Schreib uns</p>
                                    <p class="text-gray-900 font-semibold group-hover:text-primary-dark transition-colors">kontakt@mein-seelenfunke.de</p>
                                </div>
                            </a>

                            {{-- Social Media --}}
                            <div class="flex gap-4 pt-2">
                                <a href="https://www.instagram.com/Mein_Seelenfunke/"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   title="Mein Seelenfunke auf Instagram besuchen"
                                   class="text-gray-500 hover:text-primary transition-colors transform hover:-translate-y-1">
                                    <span class="sr-only">Instagram</span>
                                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M7.75 2h8.5A5.75 5.75 0 0122 7.75v8.5A5.75 5.75 0 0116.25 22h-8.5A5.75 5.75 0 012 16.25v-8.5A5.75 5.75 0 017.75 2zm0 1.5A4.25 4.25 0 003.5 7.75v8.5A4.25 4.25 0 007.75 20.5h8.5a4.25 4.25 0 004.25-4.25v-8.5A4.25 4.25 0 0016.25 3.5h-8.5zm4.25 3.75a4.5 4.5 0 110 9 4.5 4.5 0 010-9zm0 1.5a3 3 0 100 6 3 3 0 000-6zm4.75-.88a1.12 1.12 0 110 2.25 1.12 1.12 0 010-2.25z"/></svg>
                                </a>
                                <a href="https://www.tiktok.com/@mein_seelenfunke"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   title="Mein Seelenfunke auf TikTok besuchen"
                                   class="text-gray-500 hover:text-primary transition-colors transform hover:-translate-y-1">
                                    <span class="sr-only">TikTok</span>
                                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                                </a>
                            </div>

                        </div>
                    </div>

                    {{-- RECHTE SPALTE: Formular --}}
                    <div class="bg-white border border-gray-200/80 p-8 sm:p-10 rounded-3xl shadow-xl relative">

                        <h4 class="text-2xl font-serif font-bold text-gray-900 mb-8">Nachricht senden</h4>

                        <form wire:submit.prevent="sending" class="space-y-6" aria-label="Kontaktformular">

                            {{-- Name Fields --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label for="first_name" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Vorname</label>
                                    <input wire:model="first_name" type="text" id="first_name" autocomplete="given-name" required aria-required="true"
                                           class="w-full rounded-xl border border-gray-300 bg-gray-50/50 shadow-sm px-4 py-3 text-gray-900 placeholder-gray-400 transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text">
                                    @error('first_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div class="space-y-2">
                                    <label for="last_name" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Nachname</label>
                                    <input wire:model="last_name" type="text" id="last_name" autocomplete="family-name" required aria-required="true"
                                           class="w-full rounded-xl border border-gray-300 bg-gray-50/50 shadow-sm px-4 py-3 text-gray-900 placeholder-gray-400 transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text">
                                    @error('last_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            {{-- Contact Fields --}}
                            <div class="space-y-2">
                                <label for="email" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">E-Mail Adresse</label>
                                <input wire:model="email" type="email" id="email" required aria-required="true"
                                       class="w-full rounded-xl border border-gray-300 bg-gray-50/50 shadow-sm px-4 py-3 text-gray-900 placeholder-gray-400 transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text">
                                @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="phone" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Telefon (Optional)</label>
                                <input wire:model="phone" type="text" id="phone"
                                       class="w-full rounded-xl border border-gray-300 bg-gray-50/50 shadow-sm px-4 py-3 text-gray-900 placeholder-gray-400 transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text">
                            </div>

                            {{-- Message Textarea --}}
                            <div class="space-y-2" x-data="{ count: 0 }" x-init="count = $refs.msg.value.length">
                                <div class="flex items-center justify-between ml-1">
                                    <label for="message" class="text-xs font-bold text-gray-700 uppercase tracking-wider">Deine Nachricht</label>
                                    <span class="text-xs text-gray-500"><span x-text="count"></span>/500</span>
                                </div>
                                <textarea wire:model="message" x-ref="msg" x-on:input="count = $refs.msg.value.length" id="message" rows="5" maxlength="500" required aria-required="true"
                                          class="w-full rounded-xl border border-gray-300 bg-gray-50/50 shadow-sm px-4 py-3 text-gray-900 placeholder-gray-400 transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none resize-none cursor-text"></textarea>
                                @error('message') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            {{-- Checkbox --}}
                            <div class="flex items-start gap-3 pt-2">
                                <div class="flex items-center h-5">
                                    <input wire:model.live="data_protection" type="checkbox" id="checkbox-2" required aria-required="true"
                                           class="w-5 h-5 rounded border-gray-300 bg-white text-primary focus:ring-primary focus:ring-offset-white cursor-pointer">
                                </div>
                                <label for="checkbox-2" class="text-sm text-gray-600 leading-relaxed cursor-pointer">
                                    Ich habe die <a href="/datenschutz" target="_blank" title="Datenschutzerklärung lesen" class="text-primary font-semibold hover:text-primary-dark transition-colors underline decoration-primary/50 underline-offset-4">Datenschutzbestimmungen</a> gelesen und erkenne diese ausdrücklich an.
                                </label>
                            </div>

                            {{-- Button Area --}}
                            <div class="pt-6">
                                <button type="submit"
                                        @if(!$data_protection) disabled @endif
                                        class="w-full flex justify-center items-center gap-2 py-4 px-6 rounded-xl text-sm font-bold uppercase tracking-widest transition-all duration-300 transform shadow-md
                                        {{ $data_protection
                                            ? 'bg-gradient-to-r from-primary-light to-primary text-gray-900 hover:brightness-105 active:scale-95 cursor-pointer hover:-translate-y-0.5'
                                            : 'bg-gray-200 text-gray-400 cursor-not-allowed opacity-60' }}">

                                    <svg wire:loading wire:target="sending" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>

                                    <span wire:loading.remove wire:target="sending">Nachricht absenden</span>
                                    <span wire:loading wire:target="sending">Wird gesendet...</span>
                                </button>
                            </div>

                            @if (session()->has('message'))
                                <div class="mt-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-center animate-fade-in-up">
                                    <p class="text-emerald-700 font-semibold flex items-center justify-center gap-2">
                                        {{ session('message') }}
                                    </p>
                                </div>
                            @endif

                            {{-- Das Easter-Egg / Wusstest du schon (Variante 3) --}}
                            <div class="mt-8 flex items-start gap-3 p-4 rounded-2xl bg-amber-500/5 border border-amber-500/20">
                                <div class="mt-0.5 text-primary animate-pulse">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <p class="text-xs text-gray-600 leading-relaxed">
                                    <strong class="text-gray-900 block mb-0.5">Wusstest du schon?</strong>
                                    Schau dir den Hintergrund an: Jeder einzelne Stern an unserem Himmel steht für einen verifizierten Nutzer. Leuchte mit uns und werde Teil unserer Community.
                                </p>
                            </div>

                        </form>
                    </div>

                </div>
            </div>
        </section>

        @include('components.scripts.universe')

    </div>
</div>

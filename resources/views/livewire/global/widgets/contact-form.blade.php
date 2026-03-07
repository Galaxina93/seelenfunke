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
                 class="bg-black py-24 relative overflow-hidden"
                 x-data
                 x-init="setTimeout(() => { if(window.startUniverseEngine) window.startUniverseEngine($el, {{ $totalStars }}) }, 100)"
                 aria-label="Kontaktformular und Kontaktdaten">

            {{-- Du brauchst keine id="contact-canvas" mehr! --}}
            <canvas class="absolute inset-0 z-0 w-full h-full pointer-events-none" wire:ignore></canvas>

            {{-- Zartes dunkles Overlay für perfekte Lesbarkeit des Textes und Formulars --}}
            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-black/30 z-0 pointer-events-none" aria-hidden="true"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid lg:grid-cols-2 gap-12 lg:gap-20 items-center">

                    {{-- LINKE SPALTE: Info & Bild --}}
                    <div class="flex flex-col justify-between h-full space-y-10">

                        <div>
                            <div class="text-primary font-bold tracking-widest uppercase text-sm mb-2">Kontakt & Beratung</div>
                            <h2 class="text-4xl md:text-5xl font-serif font-bold text-white mb-6 leading-tight">
                                Lass uns <br>
                                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-light to-primary">sprechen.</span>
                            </h2>
                            <p class="text-gray-400 text-lg leading-relaxed max-w-md">
                                Du hast eine spezielle Idee für eine Lasergravur, Fragen zu einem Unikat oder möchtest einfach Hallo sagen?
                                Nutze das Kontaktformular oder schreibe uns eine E-Mail.
                            </p>
                        </div>

                        {{-- Das Bild "Funki" (Dynamisch mit Alpine.js) --}}
                        <div class="relative w-full flex justify-center lg:justify-start py-6" x-data="{ showRose: @entangle('data_protection') }">
                            {{-- Schein hinter dem Bild ändert sich leicht, wenn die Rose aktiv ist --}}
                            <div class="absolute inset-0 bg-gradient-to-r blur-3xl rounded-full opacity-30 transform scale-75 transition-colors duration-500"
                                 :class="showRose ? 'from-pink-500/20 to-transparent' : 'from-primary/20 to-transparent'"></div>

                            <img :src="showRose ? '{{ asset('images/projekt/funki/funki_rose.png') }}' : '{{ asset('images/projekt/funki/funki_shy.png') }}'"
                                 alt="Funki Maskottchen hilft bei Kontaktanfragen und Beratung"
                                 loading="lazy"
                                 class="relative z-10 h-48 sm:h-64 object-contain drop-shadow-2xl hover:scale-105 transition-all duration-500 ease-in-out">
                        </div>

                        {{-- Kontakt Details --}}
                        <div class="space-y-6">
                            <a href="mailto:kontakt@mein-seelenfunke.de"
                               title="Senden Sie uns eine E-Mail"
                               class="group flex items-center space-x-4 p-4 rounded-2xl border border-white/5 bg-white/5 hover:border-primary/50 hover:bg-white/10 transition-all duration-300 backdrop-blur-sm">
                                <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-black transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 uppercase tracking-wide">Schreib uns</p>
                                    <p class="text-white font-medium group-hover:text-primary transition-colors">kontakt@mein-seelenfunke.de</p>
                                </div>
                            </a>

                            {{-- Social Media --}}
                            <div class="flex gap-4 pt-2">
                                <a href="https://www.instagram.com/Mein_Seelenfunke/"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   title="Mein Seelenfunke auf Instagram besuchen"
                                   class="text-gray-400 hover:text-primary transition-colors transform hover:-translate-y-1">
                                    <span class="sr-only">Instagram</span>
                                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M7.75 2h8.5A5.75 5.75 0 0122 7.75v8.5A5.75 5.75 0 0116.25 22h-8.5A5.75 5.75 0 012 16.25v-8.5A5.75 5.75 0 017.75 2zm0 1.5A4.25 4.25 0 003.5 7.75v8.5A4.25 4.25 0 007.75 20.5h8.5a4.25 4.25 0 004.25-4.25v-8.5A4.25 4.25 0 0016.25 3.5h-8.5zm4.25 3.75a4.5 4.5 0 110 9 4.5 4.5 0 010-9zm0 1.5a3 3 0 100 6 3 3 0 000-6zm4.75-.88a1.12 1.12 0 110 2.25 1.12 1.12 0 010-2.25z"/></svg>
                                </a>
                                <a href="https://www.tiktok.com/@mein_seelenfunke"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   title="Mein Seelenfunke auf TikTok besuchen"
                                   class="text-gray-400 hover:text-primary transition-colors transform hover:-translate-y-1">
                                    <span class="sr-only">TikTok</span>
                                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                                </a>
                            </div>

                            {{-- Das Easter-Egg / Wusstest du schon (Mystisch ohne Zahl) --}}
                            <div class="mt-8 flex items-start gap-3 p-4 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-sm">
                                <div class="mt-0.5 text-primary animate-pulse">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <p class="text-xs text-gray-400 leading-relaxed">
                                    <strong class="text-white block mb-0.5">Wusstest du schon?</strong>
                                    Unser Universum lebt. Für jedes gefertigte Unikat und jeden Funken, den wir weitergeben dürfen, lassen wir einen neuen Stern in unserem digitalen Himmel erstrahlen. Werde auch du ein Teil davon.
                                </p>
                            </div>

                        </div>
                    </div>

                    {{-- RECHTE SPALTE: Formular --}}
                    <div class="bg-gray-900/40 backdrop-blur-md border border-white/10 p-8 sm:p-10 rounded-3xl shadow-2xl relative">

                        <h4 class="text-2xl font-serif font-bold text-white mb-8">Nachricht senden</h4>

                        <form wire:submit.prevent="sending" class="space-y-6" aria-label="Kontaktformular">

                            {{-- Name Fields --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label for="first_name" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">Vorname</label>
                                    {{-- FIX: bg-transparent und shadow-inner für den Glas-Look --}}
                                    <input wire:model="first_name" type="text" id="first_name" autocomplete="given-name" required aria-required="true"
                                           class="w-full rounded-xl border border-white/10 bg-transparent shadow-inner px-4 py-3 text-white placeholder-gray-600 transition-all focus:border-primary focus:bg-white/5 focus:ring-1 focus:ring-primary focus:outline-none">
                                    @error('first_name') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="space-y-2">
                                    <label for="last_name" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">Nachname</label>
                                    <input wire:model="last_name" type="text" id="last_name" autocomplete="family-name" required aria-required="true"
                                           class="w-full rounded-xl border border-white/10 bg-transparent shadow-inner px-4 py-3 text-white placeholder-gray-600 transition-all focus:border-primary focus:bg-white/5 focus:ring-1 focus:ring-primary focus:outline-none">
                                    @error('last_name') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            {{-- Contact Fields --}}
                            <div class="space-y-2">
                                <label for="email" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">E-Mail Adresse</label>
                                <input wire:model="email" type="email" id="email" required aria-required="true"
                                       class="w-full rounded-xl border border-white/10 bg-transparent shadow-inner px-4 py-3 text-white placeholder-gray-600 transition-all focus:border-primary focus:bg-white/5 focus:ring-1 focus:ring-primary focus:outline-none">
                                @error('email') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="phone" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">Telefon (Optional)</label>
                                <input wire:model="phone" type="text" id="phone"
                                       class="w-full rounded-xl border border-white/10 bg-transparent shadow-inner px-4 py-3 text-white placeholder-gray-600 transition-all focus:border-primary focus:bg-white/5 focus:ring-1 focus:ring-primary focus:outline-none">
                            </div>

                            {{-- Message Textarea (Komplett transparent!) --}}
                            <div class="space-y-2" x-data="{ count: 0 }" x-init="count = $refs.msg.value.length">
                                <div class="flex items-center justify-between ml-1">
                                    <label for="message" class="text-xs font-bold text-gray-400 uppercase tracking-wider">Deine Nachricht</label>
                                    <span class="text-xs text-gray-500"><span x-text="count"></span>/500</span>
                                </div>
                                <textarea wire:model="message" x-ref="msg" x-on:input="count = $refs.msg.value.length" id="message" rows="5" maxlength="500" required aria-required="true"
                                          class="w-full rounded-xl border border-white/10 bg-transparent shadow-inner px-4 py-3 text-white placeholder-gray-600 transition-all focus:border-primary focus:bg-white/5 focus:ring-1 focus:ring-primary focus:outline-none resize-none"></textarea>
                                @error('message') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- Checkbox --}}
                            <div class="flex items-start gap-3 pt-2">
                                <div class="flex items-center h-5">
                                    <input wire:model.live="data_protection" type="checkbox" id="checkbox-2" required aria-required="true"
                                           class="w-5 h-5 rounded border-gray-600 bg-gray-800 text-primary focus:ring-primary focus:ring-offset-gray-900 cursor-pointer">
                                </div>
                                <label for="checkbox-2" class="text-sm text-gray-400 leading-relaxed cursor-pointer">
                                    Ich habe die <a href="/datenschutz" target="_blank" title="Datenschutzerklärung lesen" class="text-primary hover:text-white transition-colors underline decoration-primary/50 underline-offset-4">Datenschutzbestimmungen</a> gelesen und erkenne diese ausdrücklich an.
                                </label>
                            </div>

                            {{-- Button Area --}}
                            <div class="pt-6">
                                <button type="submit"
                                        @if(!$data_protection) disabled @endif
                                        class="w-full flex justify-center items-center gap-2 py-4 px-6 rounded-xl text-sm font-bold uppercase tracking-widest transition-all duration-300 transform shadow-lg
                                        {{ $data_protection
                                            ? 'bg-primary text-black hover:bg-white hover:shadow-[0_0_20px_rgba(197,160,89,0.4)] cursor-pointer hover:-translate-y-1'
                                            : 'bg-gray-800 text-gray-500 cursor-not-allowed opacity-50' }}">

                                    <svg wire:loading wire:target="sending" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>

                                    <span wire:loading.remove wire:target="sending">Nachricht absenden</span>
                                    <span wire:loading wire:target="sending">Wird gesendet...</span>
                                </button>
                            </div>

                            @if (session()->has('message'))
                                <div class="mt-4 p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-center animate-fade-in-up">
                                    <p class="text-green-400 font-medium flex items-center justify-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        {{ session('message') }}
                                    </p>
                                </div>
                            @endif

                            {{-- Das Easter-Egg / Wusstest du schon (Variante 3) --}}
                            <div class="mt-8 flex items-start gap-3 p-4 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-sm">
                                <div class="mt-0.5 text-primary animate-pulse">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <p class="text-xs text-gray-400 leading-relaxed">
                                    <strong class="text-white block mb-0.5">Wusstest du schon?</strong>
                                    Schau dir den Hintergrund an: Jeder einzelne Stern an unserem Himmel steht für einen verifizierten Nutzer. Aktuell leuchten <span class="text-primary font-bold text-sm">{{ $verifiedUsersCount }} Funken</span> mit uns.
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

<div style="--theme-color: #C5A059; --theme-color-5: #C5A0590D; --theme-color-10: #C5A0591A; --theme-color-15: #C5A05926; --theme-color-20: #C5A05933; --theme-color-30: #C5A0594D; --theme-color-40: #C5A05966; --theme-color-50: #C5A05980; --theme-color-70: #C5A059B3;" class="min-h-screen bg-stone-50/70 flex items-center justify-center px-4 py-12 sm:px-6 lg:px-8 font-sans">
    <div class="max-w-md w-full bg-white p-8 sm:p-10 rounded-[2.5rem] shadow-xl shadow-stone-200/60 border border-[#C5A059]/25 relative overflow-hidden transition-all duration-500">

        {{-- Dekorativer Gold-Hintergrund --}}
        <div class="absolute top-0 right-0 w-64 h-64 bg-[#C5A059]/10 rounded-full blur-3xl -z-10 translate-x-1/3 -translate-y-1/3 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-amber-500/5 rounded-full blur-3xl -z-10 -translate-x-1/3 translate-y-1/3 pointer-events-none"></div>

        {{-- Header --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/10 border border-[#C5A059]/30 text-[#9a6f1a] text-[10px] font-black uppercase tracking-widest mb-3">
                <span>✨ Manufaktur-Post</span>
            </div>
            <h2 class="text-3xl font-serif font-bold text-stone-900 tracking-tight">
                Seelenpost
            </h2>
            <p class="mt-3 text-sm text-stone-600 leading-relaxed max-w-xs mx-auto">
                Bleibe auf dem Laufenden über neue Unikate und Angebote aus unserer Manufaktur.
            </p>
        </div>

        {{-- Tabs --}}
        <div class="flex justify-center gap-1.5 mb-8 bg-stone-100 p-1.5 rounded-2xl w-full border border-stone-200" role="tablist">
            <button type="button"
                    role="tab"
                    aria-selected="{{ $activeTab === 'subscribe' ? 'true' : 'false' }}"
                    wire:click="switchTab('subscribe')"
                    @if($activeTab === 'subscribe')
                        style="background: linear-gradient(135deg, #d4b475 0%, #C5A059 50%, #b38e46 100%); color: #1c1917; box-shadow: 0 4px 12px rgba(197, 160, 89, 0.35);"
                    @endif
                    class="flex-1 py-2.5 px-4 rounded-xl text-sm font-bold transition-all duration-300 cursor-pointer flex items-center justify-center gap-2 {{ $activeTab === 'subscribe' ? 'bg-gradient-to-r from-[#d4b475] via-[#C5A059] to-[#b38e46] text-stone-950 shadow-md ring-1 ring-[#C5A059]' : 'bg-transparent text-stone-500 hover:text-stone-800 hover:bg-white/60' }}">
                <i class="bi {{ $activeTab === 'subscribe' ? 'bi-envelope-check-fill' : 'bi-envelope-plus' }}"></i>
                <span>Anmelden</span>
            </button>
            <button type="button"
                    role="tab"
                    aria-selected="{{ $activeTab === 'unsubscribe' ? 'true' : 'false' }}"
                    wire:click="switchTab('unsubscribe')"
                    @if($activeTab === 'unsubscribe')
                        style="background-color: #ffffff; color: #e11d48; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);"
                    @endif
                    class="flex-1 py-2.5 px-4 rounded-xl text-sm font-bold transition-all duration-300 cursor-pointer flex items-center justify-center gap-2 {{ $activeTab === 'unsubscribe' ? 'bg-white text-rose-600 shadow-md ring-1 ring-rose-200' : 'bg-transparent text-stone-500 hover:text-stone-800 hover:bg-white/60' }}">
                <i class="bi {{ $activeTab === 'unsubscribe' ? 'bi-sliders' : 'bi-envelope-dash' }}"></i>
                <span>Abmelden</span>
            </button>
        </div>

        {{-- Meldungen --}}
        @if (session('verified'))
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl text-sm text-center flex items-center justify-center gap-2 animate-fade-in-up">
                <i class="bi bi-check-circle-fill"></i> {{ session('verified') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-sm text-center flex items-center justify-center gap-2 animate-fade-in-up">
                <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
            </div>
        @endif
        @if ($successMessage)
            <div class="mb-6 bg-amber-50/90 border border-[#C5A059]/40 text-amber-900 px-4 py-3.5 rounded-2xl text-sm text-center flex items-center justify-center gap-2 font-medium animate-fade-in-up">
                <i class="bi bi-check2-circle text-[#C5A059] text-base"></i> {{ $successMessage }}
            </div>
        @endif

        {{-- FORMULAR: ANMELDEN --}}
        @if($activeTab === 'subscribe' && !$successMessage)
            <form class="space-y-5 animate-fade-in" wire:submit.prevent="subscribe" wire:key="form-subscribe" novalidate>
                <div class="group">
                    <div class="relative">
                        <input id="email-address" wire:model="email" type="email"
                               class="block w-full px-5 py-4 bg-stone-50 border @error('email') border-rose-400 ring-2 ring-rose-500/20 @else border-stone-200/80 @enderror rounded-2xl text-stone-900 placeholder-stone-400 focus:ring-2 focus:ring-[#C5A059]/30 focus:border-[#C5A059] focus:bg-white transition-all shadow-inner text-sm font-medium cursor-text"
                               placeholder="Deine E-Mail Adresse">
                        <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-[#C5A059]">
                            <i class="bi bi-envelope"></i>
                        </div>
                    </div>
                    @error('email')
                        <span class="text-rose-600 text-xs mt-2 ml-1 font-semibold flex items-center gap-1.5 animate-fade-in">
                            <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                        </span>
                    @enderror
                </div>

                <div>
                    <div class="flex items-start gap-3 p-3 rounded-2xl transition-all @error('privacy_accepted') bg-rose-50/90 border border-rose-400 ring-2 ring-rose-500/20 @else bg-stone-50/70 border border-stone-200/60 hover:border-[#C5A059]/50 @enderror">
                        <div class="flex items-center h-5 mt-0.5">
                            <input id="privacy" wire:model.live="privacy_accepted" type="checkbox"
                                   class="w-4 h-4 rounded cursor-pointer transition-colors accent-[#C5A059] @error('privacy_accepted') border-rose-500 text-rose-600 focus:ring-rose-500 @else border-stone-300 text-[#C5A059] focus:ring-[#C5A059] @enderror">
                        </div>
                        <div class="text-xs leading-relaxed">
                            <label for="privacy" class="font-bold cursor-pointer select-none transition-colors @error('privacy_accepted') text-rose-700 @else text-stone-800 hover:text-[#9a6f1a] @enderror">
                                Datenschutz akzeptieren
                            </label>
                            <p class="mt-0.5 @error('privacy_accepted') text-rose-600 @else text-stone-500 @enderror">
                                Details in der <a href="{{ route('datenschutz') }}" class="text-[#9a6f1a] font-bold hover:underline underline-offset-2">Datenschutzerklärung</a>.
                            </p>
                        </div>
                    </div>
                    @error('privacy_accepted')
                        <span class="text-rose-600 text-xs mt-2 ml-1 font-semibold flex items-center gap-1.5 animate-fade-in">
                            <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                        </span>
                    @enderror
                </div>

                <button type="submit"
                        style="background: linear-gradient(135deg, #d4b475 0%, #C5A059 50%, #b38e46 100%); color: #1c1917;"
                        class="group relative w-full flex justify-center py-4 px-6 text-sm font-black uppercase tracking-wider rounded-2xl transition-all duration-300 bg-gradient-to-r from-[#d4b475] via-[#C5A059] to-[#b38e46] text-stone-950 shadow-lg shadow-[#C5A059]/25 hover:brightness-105 hover:-translate-y-0.5 active:scale-[0.99] cursor-pointer overflow-hidden">
                    <span wire:loading.remove wire:target="subscribe" class="flex items-center gap-2 relative z-20">
                        <span>Kostenlos abonnieren</span>
                        <i class="bi bi-arrow-right transition-transform group-hover:translate-x-1"></i>
                    </span>
                    <span wire:loading wire:target="subscribe" class="flex items-center gap-2 relative z-20">
                        <i class="bi bi-arrow-repeat animate-spin"></i> Verarbeite...
                    </span>
                </button>
            </form>
        @endif

        {{-- FORMULAR: ABMELDEN & DIFFERENZIERTE E-MAIL-VERWALTUNG --}}
        @if($activeTab === 'unsubscribe' && !$successMessage)
            <div class="mt-2 animate-fade-in" x-data="{ state: 'normal' }" wire:key="form-unsubscribe">

                {{-- FUNKI BEREICH --}}
                <div class="relative flex flex-col items-center justify-center h-36 mb-4">
                    <div class="absolute -top-2 right-6 bg-amber-50/90 border border-[#C5A059]/30 shadow-xs px-3.5 py-1.5 rounded-2xl rounded-bl-none transform -rotate-2 z-20">
                        <p class="text-[11px] font-bold text-stone-700">Du entscheidest, was du erhältst ✨</p>
                    </div>

                    <div class="relative w-28 h-28 transition-transform duration-500"
                         :class="state !== 'normal' ? 'scale-105 rotate-2' : 'scale-100'">
                        <img src="{{ funki_asset('shop/projekt/funki/funki_cry.webp') }}"
                             class="absolute inset-0 w-full h-full object-contain transition-opacity duration-300"
                             :class="state === 'normal' ? 'opacity-100' : 'opacity-0'">
                        <img src="{{ funki_asset('shop/projekt/funki/funki_cry_more.webp') }}"
                             class="absolute inset-0 w-full h-full object-contain transition-opacity duration-300"
                             :class="state === 'focus' ? 'opacity-100' : 'opacity-0'">
                        <img src="{{ funki_asset('shop/projekt/funki/funki_gave_up.webp') }}"
                             class="absolute inset-0 w-full h-full object-contain transition-opacity duration-300"
                             :class="state === 'hover' ? 'opacity-100' : 'opacity-0'">
                    </div>
                </div>

                <form class="space-y-4" wire:submit.prevent="unsubscribe" novalidate>
                    <div class="group">
                        <div class="relative">
                            <input id="email-unsub" wire:model="email" type="email"
                                   @focus="state = 'focus'" @blur="state = 'normal'"
                                   class="block w-full px-5 py-3.5 bg-stone-50 border border-stone-200/80 rounded-2xl text-stone-900 placeholder-stone-400 focus:ring-2 focus:ring-[#C5A059]/30 focus:border-[#C5A059] focus:bg-white transition-all shadow-inner text-sm font-medium cursor-text"
                                   placeholder="Deine E-Mail Adresse">
                            <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-stone-400">
                                <i class="bi bi-envelope"></i>
                            </div>
                        </div>
                        @error('email')
                            <span class="text-rose-600 text-xs mt-1.5 ml-1 font-semibold flex items-center gap-1.5">
                                <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                            </span>
                        @enderror
                    </div>

                    {{-- Differenzierte Auswahl der E-Mail-Kategorien --}}
                    <div class="bg-stone-50/90 border border-stone-200/80 rounded-2xl p-3.5 space-y-2.5">
                        <div class="flex items-center justify-between px-0.5 mb-1">
                            <span class="text-[11px] font-black uppercase tracking-wider text-stone-600">
                                Welche E-Mails abstellen?
                            </span>
                            <button type="button"
                                    wire:click="$set('unsub_newsletter', true); $set('unsub_birthday', true); $set('unsub_anniversary', true)"
                                    class="text-[11px] font-bold text-[#9a6f1a] hover:underline cursor-pointer">
                                Alle auswählen
                            </button>
                        </div>

                        {{-- 1. Eigentliche News (Seelenpost) --}}
                        <label class="flex items-start gap-3 p-2.5 rounded-xl bg-white border {{ $unsub_newsletter ? 'border-[#C5A059] ring-1 ring-[#C5A059]/30' : 'border-stone-200/70' }} cursor-pointer transition-all">
                            <input type="checkbox" wire:model.live="unsub_newsletter"
                                   class="mt-0.5 w-4 h-4 rounded border-stone-300 text-[#C5A059] focus:ring-[#C5A059] cursor-pointer accent-[#C5A059]">
                            <div class="text-xs leading-tight select-none">
                                <span class="font-bold text-stone-800 block">📰 Eigentliche News & Angebote (Seelenpost)</span>
                                <span class="text-stone-500 text-[11px] block mt-0.5">Neuigkeiten, neue Unikate & Saison-Aktionen</span>
                            </div>
                        </label>

                        {{-- 2. Geburtstags-Funke --}}
                        <label class="flex items-start gap-3 p-2.5 rounded-xl bg-white border {{ $unsub_birthday ? 'border-[#C5A059] ring-1 ring-[#C5A059]/30' : 'border-stone-200/70' }} cursor-pointer transition-all">
                            <input type="checkbox" wire:model.live="unsub_birthday"
                                   class="mt-0.5 w-4 h-4 rounded border-stone-300 text-[#C5A059] focus:ring-[#C5A059] cursor-pointer accent-[#C5A059]">
                            <div class="text-xs leading-tight select-none">
                                <span class="font-bold text-stone-800 block">🎂 Geburtstags-Funke</span>
                                <span class="text-stone-500 text-[11px] block mt-0.5">Automatische Geburtstags-Mail (+50 ✨ Bonus-Info)</span>
                            </div>
                        </label>

                        {{-- 3. Vorfreude-Funken zum Kauf-Jubiläum --}}
                        <label class="flex items-start gap-3 p-2.5 rounded-xl bg-white border {{ $unsub_anniversary ? 'border-[#C5A059] ring-1 ring-[#C5A059]/30' : 'border-stone-200/70' }} cursor-pointer transition-all">
                            <input type="checkbox" wire:model.live="unsub_anniversary"
                                   class="mt-0.5 w-4 h-4 rounded border-stone-300 text-[#C5A059] focus:ring-[#C5A059] cursor-pointer accent-[#C5A059]">
                            <div class="text-xs leading-tight select-none">
                                <span class="font-bold text-stone-800 block">🕯️ Vorfreude-Funken zum Kauf-Jubiläum</span>
                                <span class="text-stone-500 text-[11px] block mt-0.5">Jährliche Erinnerung zum Jahrestag deiner Bestellung</span>
                            </div>
                        </label>

                        @error('unsub_selection')
                            <span class="text-rose-600 text-xs mt-1 font-semibold flex items-center gap-1.5">
                                <i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}
                            </span>
                        @enderror
                    </div>

                    <div class="space-y-2.5 pt-1">
                        <button type="submit"
                                @mouseenter="state = 'hover'" @mouseleave="state = 'normal'"
                                style="background: linear-gradient(135deg, #d4b475 0%, #C5A059 50%, #b38e46 100%); color: #1c1917;"
                                class="w-full flex justify-center py-3.5 px-6 text-xs sm:text-sm font-black uppercase tracking-wider rounded-2xl text-stone-900 bg-gradient-to-r from-[#d4b475] via-[#C5A059] to-[#b38e46] hover:brightness-105 shadow-md transition-all duration-300 cursor-pointer active:scale-[0.99]">
                            <span wire:loading.remove wire:target="unsubscribe" class="flex items-center gap-2">
                                <i class="bi bi-check2-square"></i> Ausgewählte E-Mails abstellen
                            </span>
                            <span wire:loading wire:target="unsubscribe" class="flex items-center gap-2">
                                <i class="bi bi-arrow-repeat animate-spin"></i> Verarbeite...
                            </span>
                        </button>

                        <button type="button"
                                wire:click="unsubscribeAll"
                                @mouseenter="state = 'hover'" @mouseleave="state = 'normal'"
                                class="w-full flex justify-center py-3 px-5 text-xs font-bold uppercase tracking-wider rounded-2xl text-stone-600 bg-stone-100 hover:bg-rose-50 hover:text-rose-600 border border-stone-200/80 hover:border-rose-200 transition-all duration-200 cursor-pointer">
                            <span wire:loading.remove wire:target="unsubscribeAll" class="flex items-center gap-1.5">
                                <i class="bi bi-slash-circle"></i> Alles komplett abstellen (Alle 3 blockieren)
                            </span>
                            <span wire:loading wire:target="unsubscribeAll" class="flex items-center gap-1.5">
                                <i class="bi bi-arrow-repeat animate-spin"></i> Stelle alles ab...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>

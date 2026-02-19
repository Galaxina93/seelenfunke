<div x-data="{ termsAccepted: @entangle('terms'), show: false }">
    <section id="register" class="bg-black py-24 relative min-h-screen overflow-hidden" aria-label="Kundenkonto erstellen">

        {{-- Dekorativer Hintergrund-Schein (Gold/Primary) --}}
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none" aria-hidden="true">
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-primary/20 rounded-full blur-[100px] opacity-20"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-primary/10 rounded-full blur-[100px] opacity-20"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-20 items-start">

                {{-- LINKE SPALTE: Info & Funki Media (Sichtbar auf allen Geräten) --}}
                <div class="flex flex-col justify-center h-full space-y-10 lg:sticky lg:top-24">

                    <div>
                        <div class="text-primary font-bold tracking-widest uppercase text-sm mb-2">Mein-Seelenfunken</div>
                        <h2 class="text-4xl md:text-5xl font-serif font-bold text-white mb-6 leading-tight">
                            Werde Teil <br>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-light to-primary">unserer Reise.</span>
                        </h2>
                        <p class="text-gray-400 text-lg leading-relaxed max-w-md">
                            Erstelle dein Kundenkonto und entdecke exklusive Unikate, die wir mit viel Liebe in unserer Manufaktur fertigen.
                        </p>
                        <p class="text-gray-400 text-sm mt-4">
                            Bereits registriert?
                            <a href="{{ route('login') }}" class="text-primary hover:text-white transition-colors underline decoration-primary/50 underline-offset-4">
                                Melde dich hier an.
                            </a>
                        </p>
                    </div>

                    {{-- Das Bild / Video "Funki" - Responsive: Mobil normal, Desktop größer --}}
                    <div class="relative w-full flex justify-center lg:justify-start py-6 h-64 lg:h-80 xl:h-96">
                        {{-- Schein hinter dem Medium --}}
                        <div class="absolute inset-0 bg-gradient-to-r from-primary/20 to-transparent blur-3xl rounded-full opacity-30 transform scale-110"></div>

                        {{-- SHY FUNKI (sichtbar wenn NICHT akzeptiert) --}}
                        <img x-show="!termsAccepted"
                             x-transition:enter="transition ease-out duration-500"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-300"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             src="{{ asset('images/projekt/funki/funki_shy.png') }}"
                             alt="Funki freut sich auf dich"
                             class="absolute z-10 h-full object-contain drop-shadow-2xl transform origin-center lg:origin-left">

                        {{-- DANCE FUNKI (sichtbar WENN akzeptiert) --}}
                        <img x-show="termsAccepted"
                             x-transition:enter="transition ease-out duration-500"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-300"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             src="{{ asset('images/projekt/funki/funki_rose.png') }}"
                             alt="Funki freut sich auf dich"
                             class="absolute z-10 h-full object-contain drop-shadow-2xl transform origin-center lg:origin-left">

                    </div>

                </div>

                {{-- RECHTE SPALTE: Formular --}}
                <div class="bg-gray-900/50 backdrop-blur-md border border-white/10 p-8 sm:p-10 rounded-3xl shadow-2xl relative">

                    <form wire:submit.prevent="register" class="space-y-8" aria-label="Registrierungsformular">

                        {{-- ABSCHNITT 1: PERSÖNLICHE DATEN --}}
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-primary uppercase tracking-wider mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                Persönliche Daten
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label for="firstname" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">Vorname *</label>
                                    <input wire:model.blur="firstname" id="firstname" type="text" required
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 shadow-sm transition-all focus:border-primary focus:bg-white/10 focus:ring-1 focus:ring-primary focus:outline-none @error('firstname') border-red-500 @enderror">
                                    @error('firstname') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div class="space-y-2">
                                    <label for="lastname" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">Nachname *</label>
                                    <input wire:model.blur="lastname" id="lastname" type="text" required
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 shadow-sm transition-all focus:border-primary focus:bg-white/10 focus:ring-1 focus:ring-primary focus:outline-none @error('lastname') border-red-500 @enderror">
                                    @error('lastname') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="email" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">E-Mail-Adresse *</label>
                                <input wire:model.blur="email" id="email" type="email" autocomplete="email" required
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 shadow-sm transition-all focus:border-primary focus:bg-white/10 focus:ring-1 focus:ring-primary focus:outline-none @error('email') border-red-500 @enderror">
                                @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <hr class="border-white/10">

                        {{-- ABSCHNITT 2: ADRESSE --}}
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-primary uppercase tracking-wider mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                                Anschrift
                            </h3>

                            <div class="grid grid-cols-4 gap-4 mb-4">
                                <div class="col-span-3 space-y-2">
                                    <label for="street" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">Straße *</label>
                                    <input wire:model.blur="street" id="street" type="text" required
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 shadow-sm transition-all focus:border-primary focus:bg-white/10 focus:ring-1 focus:ring-primary focus:outline-none @error('street') border-red-500 @enderror">
                                </div>
                                <div class="col-span-1 space-y-2">
                                    <label for="house_number" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">Nr. *</label>
                                    <input wire:model.blur="house_number" id="house_number" type="text" required
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 shadow-sm transition-all focus:border-primary focus:bg-white/10 focus:ring-1 focus:ring-primary focus:outline-none @error('house_number') border-red-500 @enderror">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div class="col-span-1 space-y-2">
                                    <label for="postal" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">PLZ *</label>
                                    <input wire:model.blur="postal" id="postal" type="text" required
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 shadow-sm transition-all focus:border-primary focus:bg-white/10 focus:ring-1 focus:ring-primary focus:outline-none @error('postal') border-red-500 @enderror">
                                </div>
                                <div class="col-span-2 space-y-2">
                                    <label for="city" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">Stadt *</label>
                                    <input wire:model.blur="city" id="city" type="text" required
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 shadow-sm transition-all focus:border-primary focus:bg-white/10 focus:ring-1 focus:ring-primary focus:outline-none @error('city') border-red-500 @enderror">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="country" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">Land *</label>
                                <select wire:model.live="country" id="country"
                                        class="w-full rounded-xl border border-white/10 bg-[#1a1c23] px-4 py-3 text-white shadow-sm transition-all focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                                    @foreach($activeCountries as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('country') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <hr class="border-white/10">

                        {{-- ABSCHNITT 3: SICHERHEIT --}}
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-primary uppercase tracking-wider mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                Sicherheit
                            </h3>

                            <div class="space-y-4">
                                <div class="space-y-2 relative">
                                    <label for="password" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">Passwort *</label>
                                    <input :type="show ? 'text' : 'password'" wire:model.live="password" id="password" autocomplete="new-password" required
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 pr-10 text-white placeholder-gray-500 shadow-sm transition-all focus:border-primary focus:bg-white/10 focus:ring-1 focus:ring-primary focus:outline-none">
                                    <button type="button" @click="show = !show" class="absolute right-0 bottom-0 top-[24px] px-3 flex items-center text-gray-500 hover:text-gray-300 focus:outline-none transition-colors">
                                        <svg x-show="show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        <svg x-show="!show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.05 10.05 0 011.574-2.59M5.75 5.75L18.25 18.25" /></svg>
                                    </button>
                                </div>

                                <div class="space-y-2 relative">
                                    <label for="password_confirmation" class="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">Passwort wiederholen *</label>
                                    <input :type="show ? 'text' : 'password'" wire:model.live="password_confirmation" id="password_confirmation" required
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 pr-10 text-white placeholder-gray-500 shadow-sm transition-all focus:border-primary focus:bg-white/10 focus:ring-1 focus:ring-primary focus:outline-none">
                                </div>

                                {{-- LIVE FEEDBACK BOX (Dark Mode) --}}
                                <div class="bg-white/5 p-4 rounded-xl border border-white/10 space-y-3 text-xs transition-all duration-300">
                                    <p class="font-bold text-gray-400 mb-2 uppercase tracking-wide">Passwort Anforderungen:</p>

                                    <div class="flex items-center gap-2 transition-colors duration-200 {{ $passwordRules['min'] ? 'text-green-400 font-bold' : 'text-gray-500' }}">
                                        <div class="w-4 h-4 rounded-full border flex items-center justify-center {{ $passwordRules['min'] ? 'bg-green-500/20 border-green-500' : 'border-gray-600' }}">
                                            @if($passwordRules['min']) <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg> @endif
                                        </div>
                                        <span>Mindestens 8 Zeichen</span>
                                    </div>

                                    <div class="flex items-center gap-2 transition-colors duration-200 {{ $passwordRules['upper'] ? 'text-green-400 font-bold' : 'text-gray-500' }}">
                                        <div class="w-4 h-4 rounded-full border flex items-center justify-center {{ $passwordRules['upper'] ? 'bg-green-500/20 border-green-500' : 'border-gray-600' }}">
                                            @if($passwordRules['upper']) <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg> @endif
                                        </div>
                                        <span>Mindestens ein Großbuchstabe (A-Z)</span>
                                    </div>

                                    <div class="flex items-center gap-2 transition-colors duration-200 {{ $passwordRules['number'] ? 'text-green-400 font-bold' : 'text-gray-500' }}">
                                        <div class="w-4 h-4 rounded-full border flex items-center justify-center {{ $passwordRules['number'] ? 'bg-green-500/20 border-green-500' : 'border-gray-600' }}">
                                            @if($passwordRules['number']) <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg> @endif
                                        </div>
                                        <span>Mindestens eine Zahl (0-9)</span>
                                    </div>

                                    <div class="flex items-center gap-2 transition-colors duration-200 {{ $passwordRules['match'] ? 'text-green-400 font-bold' : 'text-gray-500' }}">
                                        <div class="w-4 h-4 rounded-full border flex items-center justify-center {{ $passwordRules['match'] ? 'bg-green-500/20 border-green-500' : 'border-gray-600' }}">
                                            @if($passwordRules['match']) <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg> @endif
                                        </div>
                                        <span>Passwörter stimmen überein</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ABSCHNITT 4: SUBMIT --}}
                        <div class="pt-6 space-y-6">

                            {{-- Checkbox --}}
                            <div>
                                <div class="flex items-start gap-3">
                                    <div class="flex items-center h-5">
                                        <input wire:model.live="terms" id="terms" type="checkbox" required aria-required="true"
                                               class="w-5 h-5 rounded border-gray-600 bg-gray-800 text-primary focus:ring-primary focus:ring-offset-gray-900 cursor-pointer">
                                    </div>
                                    <label for="terms" class="text-sm text-gray-400 leading-relaxed cursor-pointer select-none">
                                        Ich akzeptiere die <a href="{{ route('agb') }}" target="_blank" class="text-primary hover:text-white transition-colors underline decoration-primary/50 underline-offset-4">AGB</a> und <a href="{{ route('datenschutz') }}" target="_blank" class="text-primary hover:text-white transition-colors underline decoration-primary/50 underline-offset-4">Datenschutzbestimmungen</a>.
                                    </label>
                                </div>
                                @error('terms') <span class="text-red-500 text-xs block mt-2">{{ $message }}</span> @enderror
                            </div>

                            {{-- Button Area --}}
                            <button type="submit"
                                    @if(!$this->canRegister) disabled @endif
                                    wire:loading.attr="disabled"
                                    class="w-full flex justify-center items-center gap-3 py-4 px-6 rounded-xl text-sm font-bold uppercase tracking-widest transition-all duration-300 transform shadow-lg
                                    {{ $this->canRegister
                                        ? 'bg-primary text-black hover:bg-white hover:-translate-y-1 hover:shadow-primary/50 cursor-pointer'
                                        : 'bg-gray-800 text-gray-500 cursor-not-allowed opacity-50' }}">

                                {{-- Loading Icon (erscheint nur beim Laden und sitzt fest links neben dem Text) --}}
                                <svg wire:loading class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>

                                {{-- Text im Button (Normalzustand) --}}
                                <span wire:loading.remove>
                                    @if($this->canRegister)
                                        Kundenkonto erstellen
                                    @else
                                        Bitte alle Felder korrekt ausfüllen
                                    @endif
                                </span>

                                {{-- Text im Button (Ladezustand) --}}
                                <span wire:loading>
                                    Verarbeite...
                                </span>

                            </button>

                        </div>

                    </form>
                </div>

            </div>
        </div>
    </section>
</div>

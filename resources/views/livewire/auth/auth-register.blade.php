<div x-data="{termsAccepted: @entangle('terms'), show: false}">
    <section id="register" class="bg-gray-50 py-16 sm:py-24 relative min-h-screen overflow-hidden font-sans text-gray-800 antialiased" aria-label="Kundenkonto erstellen">
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none" aria-hidden="true">
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-primary/10 rounded-full blur-[100px] opacity-40"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-primary/10 rounded-full blur-[100px] opacity-40"></div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-20 items-start">
                <div class="flex flex-col justify-center h-full space-y-10 lg:sticky lg:top-24">
                    <div>
                        <div class="text-primary font-bold tracking-widest uppercase text-xs mb-2">Mein-Seelenfunke</div>
                        <h2 class="text-3xl md:text-5xl font-serif font-bold text-gray-900 mb-6 leading-tight tracking-tight">Werde Teil<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-amber-600">unserer Reise.</span></h2>
                        <p class="text-gray-600 text-base md:text-lg leading-relaxed max-w-md">Erstelle dein Kundenkonto und entdecke exklusive Unikate, die wir mit viel Liebe in unserer Manufaktur fertigen.</p>
                        <p class="text-gray-500 text-sm mt-4">Bereits registriert? <a href="{{route('login')}}" class="text-primary hover:text-amber-800 font-bold transition-colors underline decoration-primary/50 underline-offset-4">Melde dich hier an.</a></p>
                    </div>
                    <div class="relative w-full flex justify-center lg:justify-start py-6 h-64 lg:h-80 xl:h-96">
                        <div class="absolute inset-0 bg-gradient-to-r from-primary/15 to-transparent blur-3xl rounded-full opacity-30 transform scale-110"></div>
                        <img x-show="!termsAccepted" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" src="{{asset('shop/projekt/funki/funki_shy.webp')}}" alt="Funki freut sich auf dich" class="absolute z-10 h-full object-contain drop-shadow-xl transform origin-center lg:origin-left">
                        <img x-show="termsAccepted" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" src="{{asset('shop/projekt/funki/funki_rose.webp')}}" alt="Funki freut sich auf dich" class="absolute z-10 h-full object-contain drop-shadow-xl transform origin-center lg:origin-left" style="display: none;">
                    </div>
                </div>
                <div class="bg-white p-8 sm:p-10 rounded-3xl shadow-xl border border-gray-200/80 relative">
                    <form wire:submit.prevent="register" class="space-y-8" aria-label="Registrierungsformular">
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg> Persönliche Daten
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-4">
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Kundentyp *</label>
                                    <select wire:model.blur="is_business" required class="w-full rounded-xl border border-gray-300 bg-gray-50/50 px-4 py-3 text-gray-900 shadow-sm transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-pointer">
                                        <option value="0" class="text-gray-900 bg-white">Privatkunde</option>
                                        <option value="1" class="text-gray-900 bg-white">Gewerblich</option>
                                    </select>
                                    @error('is_business')<span class="text-red-600 text-xs mt-1 block font-medium">{{$message}}</span> @enderror
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Geburtsdatum (optional)</label>
                                    <input wire:model.blur="birthday" type="date" class="w-full rounded-xl border border-gray-300 bg-gray-50/50 px-4 py-3 text-gray-900 shadow-sm transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none [color-scheme:light] cursor-text">
                                    @error('birthday')<span class="text-red-600 text-xs mt-1 block font-medium">{{$message}}</span> @enderror
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label for="firstname" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Vorname *</label>
                                    <input wire:model.blur="firstname" id="firstname" type="text" required class="w-full rounded-xl border border-gray-300 bg-gray-50/50 px-4 py-3 text-gray-900 placeholder-gray-400 shadow-sm transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text @error('firstname') border-red-400 @enderror">
                                    @error('firstname')<span class="text-red-600 text-xs mt-1 block font-medium">{{$message}}</span> @enderror
                                </div>
                                <div class="space-y-2">
                                    <label for="lastname" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Nachname *</label>
                                    <input wire:model.blur="lastname" id="lastname" type="text" required class="w-full rounded-xl border border-gray-300 bg-gray-50/50 px-4 py-3 text-gray-900 placeholder-gray-400 shadow-sm transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text @error('lastname') border-red-400 @enderror">
                                    @error('lastname')<span class="text-red-600 text-xs mt-1 block font-medium">{{$message}}</span> @enderror
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label for="email" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">E-Mail-Adresse *</label>
                                <input wire:model.blur="email" id="email" type="email" autocomplete="email" required class="w-full rounded-xl border border-gray-300 bg-gray-50/50 px-4 py-3 text-gray-900 placeholder-gray-400 shadow-sm transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text @error('email') border-red-400 @enderror">
                                @error('email')<span class="text-red-600 text-xs mt-1 block font-medium">{{$message}}</span> @enderror
                            </div>
                        </div>
                        <hr class="border-gray-200">
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg> Anschrift
                            </h3>
                            <div class="grid grid-cols-4 gap-4 mb-4">
                                <div class="col-span-3 space-y-2">
                                    <label for="street" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Straße *</label>
                                    <input wire:model.blur="street" id="street" type="text" required class="w-full rounded-xl border border-gray-300 bg-gray-50/50 px-4 py-3 text-gray-900 placeholder-gray-400 shadow-sm transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text @error('street') border-red-400 @enderror">
                                </div>
                                <div class="col-span-1 space-y-2">
                                    <label for="house_number" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Nr. *</label>
                                    <input wire:model.blur="house_number" id="house_number" type="text" required class="w-full rounded-xl border border-gray-300 bg-gray-50/50 px-4 py-3 text-gray-900 placeholder-gray-400 shadow-sm transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text @error('house_number') border-red-400 @enderror">
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div class="col-span-1 space-y-2">
                                    <label for="postal" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">PLZ *</label>
                                    <input wire:model.blur="postal" id="postal" type="text" required class="w-full rounded-xl border border-gray-300 bg-gray-50/50 px-4 py-3 text-gray-900 placeholder-gray-400 shadow-sm transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text @error('postal') border-red-400 @enderror">
                                </div>
                                <div class="col-span-2 space-y-2">
                                    <label for="city" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Stadt *</label>
                                    <input wire:model.blur="city" id="city" type="text" required class="w-full rounded-xl border border-gray-300 bg-gray-50/50 px-4 py-3 text-gray-900 placeholder-gray-400 shadow-sm transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text @error('city') border-red-400 @enderror">
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label for="country" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Land *</label>
                                <select wire:model.live="country" id="country" class="w-full rounded-xl border border-gray-300 bg-gray-50/50 px-4 py-3 text-gray-900 shadow-sm transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-pointer">
                                    @foreach($activeCountries as $code => $name)
                                        <option value="{{$code}}" class="text-gray-900 bg-white">{{$name}}</option>
                                    @endforeach
                                </select>
                                @error('country')<span class="text-red-600 text-xs mt-1 block font-medium">{{$message}}</span> @enderror
                            </div>
                        </div>
                        <hr class="border-gray-200">
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2-2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg> Sicherheit
                            </h3>
                            <div class="space-y-4">
                                <div class="space-y-2 relative">
                                    <label for="password" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Passwort *</label>
                                    <input :type="show ? 'text' : 'password'" wire:model.live="password" id="password" autocomplete="new-password" required class="w-full rounded-xl border border-gray-300 bg-gray-50/50 px-4 py-3 pr-10 text-gray-900 placeholder-gray-400 shadow-sm transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text">
                                    <button type="button" @click="show = !show" class="absolute right-0 bottom-0 top-[24px] px-3 flex items-center text-gray-400 hover:text-gray-700 focus:outline-none transition-colors">
                                        <svg x-show="show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        <svg x-show="!show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a10.05 10.05 0 011.574-2.59M5.75 5.75L18.25 18.25" /></svg>
                                    </button>
                                </div>
                                <div class="space-y-2 relative">
                                    <label for="password_confirmation" class="text-xs font-bold text-gray-700 uppercase tracking-wider ml-1">Passwort wiederholen *</label>
                                    <input :type="show ? 'text' : 'password'" wire:model.live="password_confirmation" id="password_confirmation" required class="w-full rounded-xl border border-gray-300 bg-gray-50/50 px-4 py-3 pr-10 text-gray-900 placeholder-gray-400 shadow-sm transition-all focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none cursor-text">
                                </div>
                                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-2.5 text-xs transition-all duration-300">
                                    <p class="font-bold text-gray-700 mb-2 uppercase tracking-wide text-[11px]">Passwort Anforderungen:</p>
                                    <div class="flex items-center gap-2 transition-colors duration-200 {{$passwordRules['min'] ? 'text-emerald-700 font-semibold' : 'text-gray-500'}}">
                                        <div class="w-4 h-4 rounded-full border flex items-center justify-center {{$passwordRules['min'] ? 'bg-emerald-100 border-emerald-400 text-emerald-700' : 'border-gray-300 bg-white text-gray-400'}}">
                                            @if($passwordRules['min'])<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>@endif
                                        </div>
                                        <span>Mindestens 8 Zeichen</span>
                                    </div>
                                    <div class="flex items-center gap-2 transition-colors duration-200 {{$passwordRules['upper'] ? 'text-emerald-700 font-semibold' : 'text-gray-500'}}">
                                        <div class="w-4 h-4 rounded-full border flex items-center justify-center {{$passwordRules['upper'] ? 'bg-emerald-100 border-emerald-400 text-emerald-700' : 'border-gray-300 bg-white text-gray-400'}}">
                                            @if($passwordRules['upper'])<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>@endif
                                        </div>
                                        <span>Mindestens ein Großbuchstabe (A-Z)</span>
                                    </div>
                                    <div class="flex items-center gap-2 transition-colors duration-200 {{$passwordRules['number'] ? 'text-emerald-700 font-semibold' : 'text-gray-500'}}">
                                        <div class="w-4 h-4 rounded-full border flex items-center justify-center {{$passwordRules['number'] ? 'bg-emerald-100 border-emerald-400 text-emerald-700' : 'border-gray-300 bg-white text-gray-400'}}">
                                            @if($passwordRules['number'])<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>@endif
                                        </div>
                                        <span>Mindestens eine Zahl (0-9)</span>
                                    </div>
                                    <div class="flex items-center gap-2 transition-colors duration-200 {{$passwordRules['match'] ? 'text-emerald-700 font-semibold' : 'text-gray-500'}}">
                                        <div class="w-4 h-4 rounded-full border flex items-center justify-center {{$passwordRules['match'] ? 'bg-emerald-100 border-emerald-400 text-emerald-700' : 'border-gray-300 bg-white text-gray-400'}}">
                                            @if($passwordRules['match'])<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>@endif
                                        </div>
                                        <span>Passwörter stimmen überein</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pt-6 space-y-6">
                            <div>
                                <div class="flex items-start gap-3">
                                    <div class="flex items-center h-5">
                                        <input wire:model.live="terms" id="terms" type="checkbox" required aria-required="true" class="w-5 h-5 rounded border-gray-300 bg-white text-primary focus:ring-primary/30 cursor-pointer">
                                    </div>
                                    <label for="terms" class="text-xs sm:text-sm text-gray-600 leading-relaxed cursor-pointer select-none">
                                        Ich akzeptiere die <a href="{{route('agb')}}" target="_blank" class="text-primary hover:text-amber-800 font-bold transition-colors underline decoration-primary/50 underline-offset-4">AGB</a> und habe die <a href="{{route('datenschutz')}}" target="_blank" class="text-primary hover:text-amber-800 font-bold transition-colors underline decoration-primary/50 underline-offset-4">Datenschutzerklärung</a> zur Kenntnis genommen.
                                    </label>
                                </div>
                                @error('terms')<span class="text-red-600 text-xs block mt-2 font-medium">{{$message}}</span> @enderror
                            </div>
                            <button type="submit" @if(!$this->canRegister) disabled @endif wire:loading.attr="disabled" class="w-full flex justify-center items-center gap-3 py-3.5 px-6 rounded-xl text-xs font-bold uppercase tracking-wider transition-all duration-200 shadow-sm {{$this->canRegister ? 'bg-gradient-to-r from-primary-light to-primary hover:from-primary hover:to-primary-light text-gray-900 border border-primary/40 hover:shadow hover:-translate-y-0.5 cursor-pointer' : 'bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed opacity-60'}}">
                                <svg wire:loading class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove>
                                    @if($this->canRegister) Kundenkonto erstellen @else Bitte alle Felder korrekt ausfüllen @endif
                                </span>
                                <span wire:loading>Verarbeite...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

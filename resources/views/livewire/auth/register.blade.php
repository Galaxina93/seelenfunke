<div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">

    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <h2 class="mt-6 text-center text-3xl font-serif font-bold text-gray-900">
            Kundenkonto erstellen
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
            Oder
            <a href="{{ route('login') }}" class="font-medium text-primary hover:text-primary-dark transition">
                melden Sie sich an, wenn Sie bereits registriert sind.
            </a>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10 border border-gray-100">

            <form wire:submit.prevent="register" class="space-y-6">

                {{-- ABSCHNITT 1: PERSÖNLICHE DATEN --}}
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        Persönliche Daten
                    </h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="firstname" class="block text-sm font-medium text-gray-700">Vorname *</label>
                            <div class="mt-1">
                                <input wire:model.blur="firstname" id="firstname" type="text" required
                                       class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm @error('firstname') border-red-500 @enderror">
                            </div>
                            @error('firstname') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="lastname" class="block text-sm font-medium text-gray-700">Nachname *</label>
                            <div class="mt-1">
                                <input wire:model.blur="lastname" id="lastname" type="text" required
                                       class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm @error('lastname') border-red-500 @enderror">
                            </div>
                            @error('lastname') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="email" class="block text-sm font-medium text-gray-700">E-Mail-Adresse *</label>
                        <div class="mt-1">
                            <input wire:model.blur="email" id="email" type="email" autocomplete="email" required
                                   class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm @error('email') border-red-500 @enderror">
                        </div>
                        @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <hr class="border-gray-100">

                {{-- ABSCHNITT 2: ADRESSE --}}
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                        Anschrift
                    </h3>

                    <div class="grid grid-cols-4 gap-4 mb-4">
                        <div class="col-span-3">
                            <label for="street" class="block text-sm font-medium text-gray-700">Straße *</label>
                            <input wire:model.blur="street" id="street" type="text" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm @error('street') border-red-500 @enderror">
                        </div>
                        <div class="col-span-1">
                            <label for="house_number" class="block text-sm font-medium text-gray-700">Nr. *</label>
                            <input wire:model.blur="house_number" id="house_number" type="text" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm @error('house_number') border-red-500 @enderror">
                        </div>
                    </div>
                    @if($errors->has('street') || $errors->has('house_number'))
                        <span class="text-red-500 text-xs block -mt-3 mb-3">Bitte Straße und Hausnummer angeben.</span>
                    @endif

                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-1">
                            <label for="postal" class="block text-sm font-medium text-gray-700">PLZ *</label>
                            <input wire:model.blur="postal" id="postal" type="text" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm @error('postal') border-red-500 @enderror">
                        </div>
                        <div class="col-span-2">
                            <label for="city" class="block text-sm font-medium text-gray-700">Stadt *</label>
                            <input wire:model.blur="city" id="city" type="text" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm @error('city') border-red-500 @enderror">
                        </div>
                    </div>
                    @if($errors->has('postal') || $errors->has('city'))
                        <span class="text-red-500 text-xs block mt-1">PLZ und Stadt sind erforderlich.</span>
                    @endif
                </div>

                <hr class="border-gray-100">

                {{-- ABSCHNITT 3: SICHERHEIT --}}
                <div x-data="{ show: false }">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        Sicherheit
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Passwort *</label>
                            <div class="relative mt-1">
                                <input :type="show ? 'text' : 'password'" wire:model.live="password" id="password" autocomplete="new-password" required
                                       class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm pr-10">
                                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
                                    <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.05 10.05 0 011.574-2.59M5.75 5.75L18.25 18.25" /></svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Passwort wiederholen *</label>
                            <div class="mt-1">
                                <input :type="show ? 'text' : 'password'" wire:model.live="password_confirmation" id="password_confirmation" required
                                       class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm">
                            </div>
                        </div>

                        {{-- LIVE FEEDBACK BOX --}}
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 space-y-2 text-xs transition-all duration-300">
                            <p class="font-bold text-gray-500 mb-2 uppercase tracking-wide">Passwort Anforderungen:</p>

                            <div class="flex items-center gap-2 transition-colors duration-200 {{ $passwordRules['min'] ? 'text-green-600 font-bold' : 'text-gray-400' }}">
                                <div class="w-4 h-4 rounded-full border flex items-center justify-center {{ $passwordRules['min'] ? 'bg-green-100 border-green-500' : 'border-gray-300' }}">
                                    @if($passwordRules['min']) <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg> @endif
                                </div>
                                <span>Mindestens 8 Zeichen</span>
                            </div>

                            <div class="flex items-center gap-2 transition-colors duration-200 {{ $passwordRules['upper'] ? 'text-green-600 font-bold' : 'text-gray-400' }}">
                                <div class="w-4 h-4 rounded-full border flex items-center justify-center {{ $passwordRules['upper'] ? 'bg-green-100 border-green-500' : 'border-gray-300' }}">
                                    @if($passwordRules['upper']) <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg> @endif
                                </div>
                                <span>Mindestens ein Großbuchstabe (A-Z)</span>
                            </div>

                            <div class="flex items-center gap-2 transition-colors duration-200 {{ $passwordRules['number'] ? 'text-green-600 font-bold' : 'text-gray-400' }}">
                                <div class="w-4 h-4 rounded-full border flex items-center justify-center {{ $passwordRules['number'] ? 'bg-green-100 border-green-500' : 'border-gray-300' }}">
                                    @if($passwordRules['number']) <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg> @endif
                                </div>
                                <span>Mindestens eine Zahl (0-9)</span>
                            </div>

                            <div class="flex items-center gap-2 transition-colors duration-200 {{ $passwordRules['match'] ? 'text-green-600 font-bold' : 'text-gray-400' }}">
                                <div class="w-4 h-4 rounded-full border flex items-center justify-center {{ $passwordRules['match'] ? 'bg-green-100 border-green-500' : 'border-gray-300' }}">
                                    @if($passwordRules['match']) <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg> @endif
                                </div>
                                <span>Passwörter stimmen überein</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ABSCHNITT 4: SUBMIT --}}
                <div class="pt-4">
                    <div class="flex items-start mb-6">
                        <div class="flex items-center h-5">
                            <input wire:model.live="terms" id="terms" type="checkbox" class="focus:ring-primary h-4 w-4 text-primary border-gray-300 rounded cursor-pointer">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="terms" class="font-medium text-gray-700 cursor-pointer select-none">
                                Ich akzeptiere die <a href="{{ route('agb') }}" target="_blank" class="text-primary hover:underline">AGB</a> und <a href="{{ route('datenschutz') }}" target="_blank" class="text-primary hover:underline">Datenschutzbestimmungen</a>.
                            </label>
                        </div>
                    </div>
                    @error('terms') <span class="text-red-500 text-xs block mb-4 mt-[-1rem]">{{ $message }}</span> @enderror

                    {{-- Button: Deaktiviert wenn Validation failed --}}
                    <button type="submit"
                            @if(!$this->canRegister) disabled @endif
                            wire:loading.attr="disabled"
                            class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-bold text-white transition-all transform duration-200
                                   {{ $this->canRegister ? 'bg-primary hover:bg-primary-dark hover:-translate-y-0.5 cursor-pointer shadow-primary/30' : 'bg-gray-300 cursor-not-allowed opacity-70' }}">

                        {{-- State: Normal (Loading aus) --}}
                        <span wire:loading.remove>
                            @if($this->canRegister)
                                Kostenpflichtig registrieren
                            @else
                                Bitte alle Felder korrekt ausfüllen
                            @endif
                        </span>

                        {{-- State: Loading (An) --}}
                        <span wire:loading class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>Verarbeite...</span>
                        </span>
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

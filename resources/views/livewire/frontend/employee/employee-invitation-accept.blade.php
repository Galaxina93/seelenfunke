<div class="min-h-[82vh] bg-slate-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 py-12 px-4 sm:px-6 lg:px-8 flex items-center justify-center transition-colors">
    <div class="max-w-2xl w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 sm:p-10 shadow-sm dark:shadow-2xl">
        @if(!$isValidToken)
            <div class="text-center py-8 space-y-4">
                <div class="w-16 h-16 rounded-2xl bg-rose-500/10 border border-rose-500/30 flex items-center justify-center mx-auto text-rose-500 dark:text-rose-400">
                    <x-heroicon-o-exclamation-triangle class="w-8 h-8" />
                </div>
                <h1 class="text-2xl font-serif font-bold text-gray-900 dark:text-white">Einladungslink ungültig oder abgelaufen</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                    Dieser Onboarding-Link wurde bereits eingelöst oder ist abgelaufen. Bitte wende dich kurz an die Geschäftsleitung von {{ shop_setting('company_name', 'Mein Seelenfunke') }}.
                </p>
            </div>
        @else
            {{-- Header --}}
            <div class="border-b border-gray-100 dark:border-gray-800 pb-6 mb-6">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#C5A059]/10 border border-[#C5A059]/30 text-[#C5A059] text-[10px] font-black uppercase tracking-widest mb-2">
                    <span>Digitales Mitarbeiter-Onboarding • Schritt {{ $step }} von 3</span>
                </div>
                <h1 class="text-2xl font-serif font-bold text-gray-900 dark:text-white">Willkommen im Team von {{ shop_setting('company_name', 'Mein Seelenfunke') }}</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Position: <strong class="text-gray-900 dark:text-white">{{ $jobTitle }}</strong> ({{ $employmentTypeLabel }}) • {{ number_format($hourlyRateCents / 100, 2, ',', '.') }} € / Std. • {{ number_format($weeklyHours, 1, ',', '.') }} Std./Woche
                </p>
            </div>

            {{-- 3-Schritt Fortschrittsanzeige --}}
            <div class="grid grid-cols-3 gap-2.5 mb-8">
                <div class="px-3 py-2.5 rounded-xl border text-left transition-colors {{ $step === 1 ? 'border-gray-900 bg-gray-900 text-white dark:border-[#C5A059] dark:bg-[#C5A059]/15 dark:text-white' : ($step > 1 ? 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-200' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-400') }}">
                    <span class="text-[10px] font-bold uppercase tracking-wider block opacity-75">Schritt 1</span>
                    <span class="text-xs font-semibold">Sicherheitscode</span>
                </div>
                <div class="px-3 py-2.5 rounded-xl border text-left transition-colors {{ $step === 2 ? 'border-gray-900 bg-gray-900 text-white dark:border-[#C5A059] dark:bg-[#C5A059]/15 dark:text-white' : ($step > 2 ? 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-200' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-400') }}">
                    <span class="text-[10px] font-bold uppercase tracking-wider block opacity-75">Schritt 2</span>
                    <span class="text-xs font-semibold">Login & Anschrift</span>
                </div>
                <div class="px-3 py-2.5 rounded-xl border text-left transition-colors {{ $step === 3 ? 'border-gray-900 bg-gray-900 text-white dark:border-[#C5A059] dark:bg-[#C5A059]/15 dark:text-white' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-400' }}">
                    <span class="text-[10px] font-bold uppercase tracking-wider block opacity-75">Schritt 3</span>
                    <span class="text-xs font-semibold">Vertrag & Steuer</span>
                </div>
            </div>

            {{-- SCHRITT 1: 6-STELLIGER SICHERHEITSCODE --}}
            @if($step === 1)
                <form wire:submit.prevent="verifyCode" class="space-y-5">
                    <div class="p-6 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-3">
                        <label class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 block">6-stelliger Sicherheitscode *</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Bitte gib den 6-stelligen Code ein, den du zusammen mit diesem Link erhalten hast.</p>
                        <input type="text" wire:model="verificationCode" placeholder="z. B. 482910"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-800 rounded-2xl px-4 py-3.5 text-lg font-mono font-black tracking-widest text-center text-[#C5A059] focus:border-[#C5A059] outline-none" />
                        @error('verificationCode') <p class="text-xs text-rose-600 dark:text-rose-400 font-bold mt-1.5">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="w-full py-3.5 rounded-2xl bg-[#C5A059] hover:bg-[#d4b06a] text-gray-950 font-black text-xs uppercase tracking-widest transition shadow-md cursor-pointer">
                        Sicherheitscode prüfen & weiter
                    </button>
                </form>
            @endif

            {{-- SCHRITT 2: PERSÖNLICHE DATEN, LOGIN & ANSCHRIFT --}}
            @if($step === 2)
                <form wire:submit.prevent="nextToStepThree" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Vorname *</label>
                            <input type="text" wire:model="firstName" class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('firstName') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Nachname *</label>
                            <input type="text" wire:model="lastName" class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('lastName') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">E-Mail-Adresse (für deinen Login) *</label>
                            <input type="email" wire:model="email" class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('email') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Geburtsdatum *</label>
                            <input type="date" wire:model="birthDate" class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('birthDate') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-data="{ showPw: false, showPwConfirm: false }">
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Passwort wählen (mind. 8 Zeichen) *</label>
                            <div class="relative mt-1">
                                <input :type="showPw ? 'text' : 'password'" wire:model="password" placeholder="Mind. 8 Zeichen"
                                       class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl pl-3.5 pr-10 py-2.5 text-sm text-gray-900 dark:text-white outline-none transition-colors" />
                                <button type="button" @click="showPw = !showPw" tabindex="-1"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-[#C5A059] focus:outline-none transition-colors cursor-pointer"
                                        title="Passwort anzeigen/verbergen">
                                    <svg x-show="!showPw" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <svg x-show="showPw" x-cloak class="h-4 w-4 text-[#C5A059]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.05 10.05 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                </button>
                            </div>
                            @error('password') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Passwort bestätigen *</label>
                            <div class="relative mt-1">
                                <input :type="showPwConfirm ? 'text' : 'password'" wire:model="password_confirmation" placeholder="Passwort wiederholen"
                                       class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl pl-3.5 pr-10 py-2.5 text-sm text-gray-900 dark:text-white outline-none transition-colors" />
                                <button type="button" @click="showPwConfirm = !showPwConfirm" tabindex="-1"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-[#C5A059] focus:outline-none transition-colors cursor-pointer"
                                        title="Passwort anzeigen/verbergen">
                                    <svg x-show="!showPwConfirm" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <svg x-show="showPwConfirm" x-cloak class="h-4 w-4 text-[#C5A059]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.05 10.05 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 pt-2">
                        <div class="col-span-2">
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Straße *</label>
                            <input type="text" wire:model="street" class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('street') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Hausnr. *</label>
                            <input type="text" wire:model="houseNumber" class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('houseNumber') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">PLZ *</label>
                            <input type="text" wire:model="postal" class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('postal') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-span-2">
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Wohnort *</label>
                            <input type="text" wire:model="city" class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('city') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full py-3.5 rounded-2xl bg-[#C5A059] hover:bg-[#d4b06a] text-gray-950 font-black text-xs uppercase tracking-widest transition shadow-md cursor-pointer">
                            Weiter zu Schritt 3 (Personalbogen & Vertrag)
                        </button>
                    </div>
                </form>
            @endif

            {{-- SCHRITT 3: STEUER / SV, BANKVERBINDUNG & DIGITALE UNTERSCHRIFT --}}
            @if($step === 3)
                <form wire:submit.prevent="completeOnboarding" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Steuer-ID (11-stellig) *</label>
                            <input type="text" wire:model="taxId" placeholder="12 345 678 901"
                                   class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm font-mono text-gray-900 dark:text-white outline-none" />
                            @error('taxId') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Steuerklasse *</label>
                            <select wire:model="taxClass" class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none">
                                <option value="I">Steuerklasse I</option>
                                <option value="II">Steuerklasse II</option>
                                <option value="III">Steuerklasse III</option>
                                <option value="IV">Steuerklasse IV</option>
                                <option value="V">Steuerklasse V</option>
                                <option value="VI">Steuerklasse VI</option>
                                <option value="Pauschal">Minijob Pauschalsteuerung (2 %)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Sozialversicherungsnummer (RV-Nr.)</label>
                            <input type="text" wire:model="socialSecurityNumber" placeholder="z. B. 12 150595 M 012"
                                   class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm font-mono text-gray-900 dark:text-white outline-none" />
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Krankenkasse *</label>
                            <input type="text" wire:model="healthInsuranceName" placeholder="z. B. TK, AOK, Barmer"
                                   class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('healthInsuranceName') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">Kontoinhaber (Gehaltskonto) *</label>
                            <input type="text" wire:model="accountHolder" class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('accountHolder') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-400">IBAN *</label>
                            <input type="text" wire:model="iban" placeholder="DE00 0000 0000 0000 0000 00"
                                   class="w-full mt-1 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 focus:border-[#C5A059] rounded-xl px-3.5 py-2.5 text-sm font-mono text-gray-900 dark:text-white outline-none" />
                            @error('iban') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Digitale Unterschrift unter Arbeitsvertrag (§ 2 NachwG / BEG IV) --}}
                    <div class="bg-amber-50/60 dark:bg-gray-950 border border-[#C5A059]/40 rounded-2xl p-4 space-y-3 mt-4">
                        <div class="text-xs font-bold text-[#C5A059] uppercase tracking-wider">
                            Digitale Vertragsunterzeichnung (§ 2 NachwG i.d.F. BEG IV)
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            Mit deiner digitalen Unterschrift wird dein Arbeitsvertrag als <strong class="text-gray-900 dark:text-white">{{ $jobTitle }}</strong> ({{ $employmentTypeLabel }}, {{ number_format($hourlyRateCents / 100, 2, ',', '.') }} €/Std., {{ $vacationDays }} Urlaubstage) ausgefertigt und steht dir sofort in deinem Dashboard als PDF zum Download bereit.
                        </p>

                        <div>
                            <label class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300">Digitale Unterschrift (Vor- und Nachname eingeben) *</label>
                            <input type="text" wire:model="signatureName"
                                   class="w-full mt-1 bg-white dark:bg-gray-900 border border-[#C5A059]/50 rounded-xl px-4 py-3 text-base font-serif italic text-gray-900 dark:text-white outline-none" />
                            @error('signatureName') <span class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <label class="flex items-start gap-2.5 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                            <input type="checkbox" wire:model="contractAccepted" class="mt-0.5 rounded bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-[#C5A059]" />
                            <span>Ich nehme den Arbeitsvertrag verbindlich an und bestätige den Erhalt in elektronischer Textform (§ 2 NachwG).</span>
                        </label>
                        @error('contractAccepted') <p class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                        <label class="flex items-start gap-2.5 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                            <input type="checkbox" wire:model="truthConfirmed" class="mt-0.5 rounded bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-[#C5A059]" />
                            <span>Ich bestätige die Richtigkeit meiner Steuer- und Sozialversicherungsangaben für die Lohnabrechnung.</span>
                        </label>
                        @error('truthConfirmed') <p class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <button type="button" wire:click="previousStep" class="px-5 py-3 rounded-2xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold cursor-pointer">
                            Zurück
                        </button>
                        <button type="submit" class="flex-1 py-3.5 rounded-2xl bg-[#C5A059] hover:bg-[#d4b06a] text-gray-950 font-black text-xs uppercase tracking-widest shadow-md cursor-pointer">
                            Arbeitsvertrag digital unterschreiben & ins Dashboard
                        </button>
                    </div>
                </form>
            @endif
        @endif
    </div>
</div>

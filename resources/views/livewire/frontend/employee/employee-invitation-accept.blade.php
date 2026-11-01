<div class="min-h-[82vh] bg-slate-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 py-10 px-4 sm:px-6 lg:px-8 flex items-center justify-center transition-colors">
    <div class="max-w-xl w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8 shadow-sm">
        @if(!$isValidToken)
            <div class="text-center py-8 space-y-3">
                <h1 class="text-lg font-bold text-gray-900 dark:text-white">Link abgelaufen oder bereits eingelöst</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Bitte fordere einen neuen Einladungslink an.
                </p>
            </div>
        @else
            {{-- Kopfbereich --}}
            <div class="border-b border-gray-100 dark:border-gray-800 pb-4 mb-5 flex items-center justify-between gap-2">
                <div>
                    <h1 class="text-lg font-bold text-gray-900 dark:text-white">{{ $jobTitle }}</h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $employmentTypeLabel }} • {{ number_format($hourlyRateCents / 100, 2, ',', '.') }} €/Std. • {{ number_format($weeklyHours, 1, ',', '.') }} Std./Woche
                    </p>
                </div>
                <span class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-300 shrink-0">
                    Schritt {{ $step }} / 3
                </span>
            </div>

            {{-- 1 - 2 - 3 Schritte --}}
            <div class="grid grid-cols-3 gap-2 mb-6">
                <div class="px-3 py-2 rounded-xl border text-center text-xs font-bold {{ $step === 1 ? 'border-gray-900 bg-gray-900 text-white dark:border-[#C5A059] dark:bg-[#C5A059] dark:text-gray-950' : 'border-gray-200 dark:border-gray-800 text-gray-400' }}">
                    1. Code
                </div>
                <div class="px-3 py-2 rounded-xl border text-center text-xs font-bold {{ $step === 2 ? 'border-gray-900 bg-gray-900 text-white dark:border-[#C5A059] dark:bg-[#C5A059] dark:text-gray-950' : 'border-gray-200 dark:border-gray-800 text-gray-400' }}">
                    2. Daten
                </div>
                <div class="px-3 py-2 rounded-xl border text-center text-xs font-bold {{ $step === 3 ? 'border-gray-900 bg-gray-900 text-white dark:border-[#C5A059] dark:bg-[#C5A059] dark:text-gray-950' : 'border-gray-200 dark:border-gray-800 text-gray-400' }}">
                    3. Vertrag
                </div>
            </div>

            {{-- SCHRITT 1: CODE --}}
            @if($step === 1)
                <form wire:submit.prevent="verifyCode" class="space-y-4">
                    <div>
                        <label class="text-xs font-bold text-gray-700 dark:text-gray-300 block mb-1.5">6-stelliger Code</label>
                        <input type="text" wire:model="verificationCode" placeholder="000000"
                               class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-3 text-lg font-mono font-bold tracking-widest text-center text-gray-900 dark:text-white outline-none" />
                        @error('verificationCode') <p class="text-xs text-rose-600 font-semibold mt-1.5">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl bg-gray-900 hover:bg-gray-800 dark:bg-[#C5A059] dark:hover:bg-[#d4b06a] text-white dark:text-gray-950 font-bold text-xs cursor-pointer">
                        Weiter →
                    </button>
                </form>
            @endif

            {{-- SCHRITT 2: DATEN & LOGIN --}}
            @if($step === 2)
                <form wire:submit.prevent="nextToStepThree" class="space-y-3.5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Vorname *</label>
                            <input type="text" wire:model="firstName" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('firstName') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Nachname *</label>
                            <input type="text" wire:model="lastName" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('lastName') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">E-Mail (Login) *</label>
                            <input type="email" wire:model="email" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('email') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Geburtsdatum *</label>
                            <input type="date" wire:model="birthDate" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('birthDate') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Passwort (mind. 8 Zeichen) *</label>
                            <input type="password" wire:model="password" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('password') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Passwort wiederholen *</label>
                            <input type="password" wire:model="password_confirmation" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Straße *</label>
                            <input type="text" wire:model="street" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('street') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Nr. *</label>
                            <input type="text" wire:model="houseNumber" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('houseNumber') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">PLZ *</label>
                            <input type="text" wire:model="postal" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('postal') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-span-2">
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Ort *</label>
                            <input type="text" wire:model="city" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('city') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full py-3 rounded-xl bg-gray-900 hover:bg-gray-800 dark:bg-[#C5A059] dark:hover:bg-[#d4b06a] text-white dark:text-gray-950 font-bold text-xs cursor-pointer">
                            Weiter →
                        </button>
                    </div>
                </form>
            @endif

            {{-- SCHRITT 3: STEUER, IBAN & UNTERSCHRIFT --}}
            @if($step === 3)
                <form wire:submit.prevent="completeOnboarding" class="space-y-3.5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Steuer-ID (11 Ziffern) *</label>
                            <input type="text" wire:model="taxId" placeholder="12345678901"
                                   class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm font-mono text-gray-900 dark:text-white outline-none" />
                            @error('taxId') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Steuerklasse *</label>
                            <select wire:model="taxClass" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none">
                                <option value="I">Steuerklasse I</option>
                                <option value="II">Steuerklasse II</option>
                                <option value="III">Steuerklasse III</option>
                                <option value="IV">Steuerklasse IV</option>
                                <option value="V">Steuerklasse V</option>
                                <option value="VI">Steuerklasse VI</option>
                                <option value="Pauschal">Minijob Pauschal</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Krankenkasse *</label>
                            <input type="text" wire:model="healthInsuranceName" placeholder="z. B. TK, AOK"
                                   class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('healthInsuranceName') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Sozialversicherungsnummer</label>
                            <input type="text" wire:model="socialSecurityNumber"
                                   class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm font-mono text-gray-900 dark:text-white outline-none" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Kontoinhaber *</label>
                            <input type="text" wire:model="accountHolder"
                                   class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white outline-none" />
                            @error('accountHolder') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">IBAN *</label>
                            <input type="text" wire:model="iban" placeholder="DE..."
                                   class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm font-mono text-gray-900 dark:text-white outline-none" />
                            @error('iban') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-3 mt-2">
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 dark:text-gray-300">Unterschrift (Vor- und Nachname eintippen) *</label>
                            <input type="text" wire:model="signatureName"
                                   class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3.5 py-2.5 text-base font-serif italic text-gray-900 dark:text-white outline-none" />
                            @error('signatureName') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                            <input type="checkbox" wire:model="contractAccepted" class="rounded text-gray-900 dark:text-[#C5A059]" />
                            <span>Arbeitsvertrag annehmen</span>
                        </label>
                        @error('contractAccepted') <p class="text-[11px] text-rose-600">{{ $message }}</p> @enderror

                        <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                            <input type="checkbox" wire:model="truthConfirmed" class="rounded text-gray-900 dark:text-[#C5A059]" />
                            <span>Alle Angaben sind korrekt</span>
                        </label>
                        @error('truthConfirmed') <p class="text-[11px] text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <button type="button" wire:click="previousStep" class="px-4 py-3 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-bold cursor-pointer">
                            ← Zurück
                        </button>
                        <button type="submit" class="flex-1 py-3 rounded-xl bg-gray-900 hover:bg-gray-800 dark:bg-[#C5A059] dark:hover:bg-[#d4b06a] text-white dark:text-gray-950 font-bold text-xs cursor-pointer">
                            ✓ Unterschreiben & Starten
                        </button>
                    </div>
                </form>
            @endif
        @endif
    </div>
</div>

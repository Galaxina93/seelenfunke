<div class="min-h-[82vh] bg-slate-50 text-gray-900 py-12 px-4 sm:px-6 lg:px-8 flex items-center justify-center">
    <div class="max-w-2xl w-full bg-white border border-gray-200 rounded-2xl p-6 sm:p-10 shadow-sm">
        @if($isValidToken)
            {{-- Header (Clean, Matt, Ohne Icon) --}}
            <div class="border-b border-gray-100 pb-6 mb-6">
                <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-gray-400 block mb-1">
                    Seelenfunke Creator Partnerprogramm
                </span>
                <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900">
                    @if($step === 1)
                        Einladung verifizieren
                    @elseif($step === 2)
                        Creator-Profil &amp; Wunsch-Codes
                    @else
                        Auszahlung &amp; Steuerangaben
                    @endif
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-1.5">
                    Konditionen: <span class="text-gray-900 font-semibold">{{ $commissionPercent }} % Provision</span> auf Netto-Umsätze &middot; <span class="text-gray-900 font-semibold">{{ $followerDiscountPercent }} % Follower-Rabatt</span>
                </p>
            </div>

            {{-- 3-Schritt Fortschrittsanzeige (Clean & Matt) --}}
            <div class="grid grid-cols-3 gap-2.5 mb-8">
                <div class="px-3 py-2.5 rounded-xl border text-left transition-colors {{ $step === 1 ? 'border-gray-900 bg-gray-900 text-white' : ($step > 1 ? 'border-gray-200 bg-gray-50 text-gray-900' : 'border-gray-200 bg-white text-gray-400') }}">
                    <span class="text-[10px] font-bold uppercase tracking-wider block opacity-75">Schritt 1</span>
                    <span class="text-xs font-semibold">Sicherheitscode</span>
                </div>
                <div class="px-3 py-2.5 rounded-xl border text-left transition-colors {{ $step === 2 ? 'border-gray-900 bg-gray-900 text-white' : ($step > 2 ? 'border-gray-200 bg-gray-50 text-gray-900' : 'border-gray-200 bg-white text-gray-400') }}">
                    <span class="text-[10px] font-bold uppercase tracking-wider block opacity-75">Schritt 2</span>
                    <span class="text-xs font-semibold">Profil &amp; Codes</span>
                </div>
                <div class="px-3 py-2.5 rounded-xl border text-left transition-colors {{ $step === 3 ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 bg-white text-gray-400' }}">
                    <span class="text-[10px] font-bold uppercase tracking-wider block opacity-75">Schritt 3</span>
                    <span class="text-xs font-semibold">Auszahlung</span>
                </div>
            </div>

            {{-- SCHRITT 1: 6-STELLIGER VERIFIZIERUNGSCODE --}}
            @if($step === 1)
                <form wire:submit.prevent="verifyCode" class="space-y-6">
                    <div class="p-6 rounded-xl bg-gray-50 border border-gray-200 space-y-3">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700">
                            6-stelliger Verifizierungscode *
                        </label>
                        <p class="text-xs text-gray-500 leading-relaxed">
                            Bitte gib den Sicherheitscode ein, den du zusammen mit diesem Einladungslink erhalten hast.
                        </p>
                        <input type="text" wire:model="verificationCode" placeholder="482915" maxlength="12" autofocus
                               class="w-full max-w-xs text-center text-2xl font-mono font-bold tracking-[0.3em] bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-4 py-3 text-gray-900 placeholder-gray-300 outline-none transition-colors uppercase">
                        @error('verificationCode')
                            <span class="text-red-600 text-xs font-semibold block pt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" class="w-full py-3.5 px-6 rounded-xl bg-gray-900 hover:bg-gray-800 text-white font-bold text-xs uppercase tracking-widest transition-colors cursor-pointer">
                        Code verifizieren &amp; weiter
                    </button>
                </form>
            @endif

            {{-- SCHRITT 2: CREATOR-PROFIL, LOGIN & WUNSCH-CODES --}}
            @if($step === 2)
                <form wire:submit.prevent="nextToStepThree" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Vorname *</label>
                            <input type="text" wire:model="firstName" placeholder="Lara"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors">
                            @error('firstName') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Nachname *</label>
                            <input type="text" wire:model="lastName" placeholder="Mustermann"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors">
                            @error('lastName') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Haupt-Plattform *</label>
                            <select wire:model="primaryChannel" class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors">
                                <option value="instagram">Instagram</option>
                                <option value="tiktok">TikTok</option>
                                <option value="youtube">YouTube</option>
                                <option value="pinterest">Pinterest</option>
                                <option value="blog">Blog / Website</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Creator-Handle *</label>
                            <input type="text" wire:model.live.debounce.300ms="handle" placeholder="@lara.living"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm font-mono text-gray-900 outline-none transition-colors">
                            @error('handle') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 p-4 rounded-xl bg-gray-50 border border-gray-200">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Wunsch-Kurzlink (/t/...) *</label>
                            <input type="text" wire:model.live.debounce.300ms="slug" placeholder="lara-living"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3 py-2 text-xs font-mono text-gray-900 outline-none transition-colors">
                            <span class="text-[11px] text-gray-500 block mt-1 truncate">{{ url('/t/' . ($slug ?: 'dein-name')) }}</span>
                            @error('slug') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Wunsch-Rabattcode ({{ $followerDiscountPercent }} %)</label>
                            <input type="text" wire:model="customVoucherCode" placeholder="LARA10"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3 py-2 text-xs font-mono uppercase text-gray-900 font-bold outline-none transition-colors">
                            <span class="text-[11px] text-gray-500 block mt-1">Für deine Community</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">E-Mail-Adresse (für deinen Login) *</label>
                        <input type="email" wire:model="email" placeholder="lara@creator.de"
                               class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors">
                        @error('email') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Passwort wählen *</label>
                            <input type="password" wire:model="password" placeholder="Mind. 8 Zeichen"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors">
                            @error('password') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Passwort bestätigen *</label>
                            <input type="password" wire:model="password_confirmation" placeholder="Passwort wiederholen"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors">
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full py-3.5 px-6 rounded-xl bg-gray-900 hover:bg-gray-800 text-white font-bold text-xs uppercase tracking-widest transition-colors cursor-pointer">
                            Weiter zu Auszahlung &amp; Steuerangaben
                        </button>
                    </div>
                </form>
            @endif

            {{-- SCHRITT 3: STEUERANGABEN & AUSZAHLUNGSWEG --}}
            @if($step === 3)
                <form wire:submit.prevent="completeRegistration" class="space-y-4">
                    <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200 text-xs text-gray-600 leading-relaxed">
                        <strong class="text-gray-900">Automatische Abrechnung:</strong> Du musst keine eigenen Rechnungen erstellen. Wir stellen dir bei jeder Auszahlung automatisch eine steuerkonforme Gutschrift (§ 14 Abs. 2 S. 2 UStG) aus.
                    </div>

                    <div class="grid grid-cols-3 gap-3.5">
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Straße *</label>
                            <input type="text" wire:model="street" placeholder="Musterstraße"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors">
                            @error('street') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Hausnr. *</label>
                            <input type="text" wire:model="houseNumber" placeholder="12a"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors">
                            @error('houseNumber') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">PLZ *</label>
                            <input type="text" wire:model="postal" placeholder="10115"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors">
                            @error('postal') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Ort *</label>
                            <input type="text" wire:model="city" placeholder="Berlin"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors">
                            @error('city') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Land *</label>
                            <select wire:model="country" class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors">
                                <option value="DE">Deutschland</option>
                                <option value="AT">Österreich</option>
                                <option value="CH">Schweiz</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Steuer-Status *</label>
                            <select wire:model="taxStatus" class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none transition-colors">
                                <option value="small_business">Kleinunternehmer (§ 19 UStG – 0 % USt.)</option>
                                <option value="standard_tax">Regelbesteuert (+19 % USt. Ausweis)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Steuernummer oder USt-IdNr. *</label>
                            <input type="text" wire:model="taxNumber" placeholder="12/345/67890 oder DE123456789"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3.5 py-2.5 text-sm font-mono text-gray-900 outline-none transition-colors">
                            @error('taxNumber') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Gewünschter Auszahlungsweg *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <button type="button" wire:click="$set('payoutMethod', 'bank_transfer')"
                                    class="p-3 rounded-xl border text-left transition-colors cursor-pointer {{ $payoutMethod === 'bank_transfer' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300' }}">
                                <span class="text-xs font-bold block">SEPA-Bankkonto</span>
                                <span class="text-[11px] {{ $payoutMethod === 'bank_transfer' ? 'text-gray-300' : 'text-gray-500' }}">Klassische Überweisung</span>
                            </button>
                            <button type="button" wire:click="$set('payoutMethod', 'paypal')"
                                    class="p-3 rounded-xl border text-left transition-colors cursor-pointer {{ $payoutMethod === 'paypal' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300' }}">
                                <span class="text-xs font-bold block">PayPal</span>
                                <span class="text-[11px] {{ $payoutMethod === 'paypal' ? 'text-gray-300' : 'text-gray-500' }}">Schnelle Auszahlung</span>
                            </button>
                            <button type="button" wire:click="$set('payoutMethod', 'shop_voucher')"
                                    class="p-3 rounded-xl border text-left transition-colors cursor-pointer {{ $payoutMethod === 'shop_voucher' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300' }}">
                                <span class="text-xs font-bold block">Shop-Gutschein (+20 %)</span>
                                <span class="text-[11px] {{ $payoutMethod === 'shop_voucher' ? 'text-gray-300' : 'text-gray-500' }}">+20 % Bonus-Guthaben</span>
                            </button>
                        </div>
                    </div>

                    @if($payoutMethod === 'bank_transfer')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 p-4 rounded-xl bg-gray-50 border border-gray-200">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Kontoinhaber *</label>
                                <input type="text" wire:model="accountHolder" placeholder="Vorname Nachname"
                                       class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3 py-2 text-xs text-gray-900 outline-none">
                                @error('accountHolder') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">IBAN *</label>
                                <input type="text" wire:model="iban" placeholder="DE00 0000 0000 0000 0000 00"
                                       class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3 py-2 text-xs font-mono uppercase text-gray-900 outline-none">
                                @error('iban') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @elseif($payoutMethod === 'paypal')
                        <div class="p-4 rounded-xl bg-gray-50 border border-gray-200">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">PayPal E-Mail-Adresse *</label>
                            <input type="email" wire:model="paypalEmail" placeholder="dein.paypal@email.de"
                                   class="w-full bg-white border border-gray-300 focus:border-gray-900 rounded-xl px-3 py-2 text-xs text-gray-900 outline-none">
                            @error('paypalEmail') <span class="text-red-600 text-xs font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div class="space-y-2.5 p-4 rounded-xl bg-gray-50 border border-gray-200">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" wire:model="selfEmployedConfirmed" class="mt-0.5 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                            <span class="text-xs text-gray-700 leading-relaxed">
                                Ich bestätige, dass ich als selbstständige/r Creator/in (Gewerbe / Freiberuf) auf eigene Rechnung handle und meine Einnahmen eigenverantwortlich versteuere. *
                            </span>
                        </label>
                        @error('selfEmployedConfirmed') <span class="text-red-600 text-xs font-medium block">{{ $message }}</span> @enderror

                        <label class="flex items-start gap-3 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="termsAccepted" class="mt-0.5 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                            <span class="text-xs text-gray-700 leading-relaxed">
                                Ich bin mit der Abrechnung im Gutschriftsverfahren (§ 14 Abs. 2 S. 2 UStG) einverstanden und möchte mein Creator-Konto jetzt aktivieren. *
                            </span>
                        </label>
                        @error('termsAccepted') <span class="text-red-600 text-xs font-medium block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-2">
                        <button type="button" wire:click="previousStep"
                                class="sm:w-1/3 py-3.5 px-4 rounded-xl border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-xs uppercase tracking-widest transition-colors cursor-pointer">
                            Zurück
                        </button>
                        <button type="submit"
                                class="sm:w-2/3 py-3.5 px-6 rounded-xl bg-gray-900 hover:bg-gray-800 text-white font-bold text-xs uppercase tracking-widest transition-colors cursor-pointer">
                            Registrierung abschließen
                        </button>
                    </div>
                </form>
            @endif
        @else
            <div class="text-center py-8 space-y-4">
                <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-gray-400 block">
                    Seelenfunke Creator Partnerprogramm
                </span>
                <h1 class="text-2xl font-serif font-bold text-gray-900">Einladungslink abgelaufen oder bereits genutzt</h1>
                <p class="text-xs sm:text-sm text-gray-500 max-w-md mx-auto leading-relaxed">
                    Falls du deine Registrierung bereits abgeschlossen hast, kannst du dich jederzeit direkt über den regulären Login mit deiner E-Mail-Adresse anmelden.
                </p>
                <div class="pt-2">
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-gray-900 hover:bg-gray-800 text-white font-bold text-xs uppercase tracking-widest transition-colors">
                        Zum Login
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>

<div x-show="activeProfileTab === '2fa'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0">
    <div class="mb-10 border-b border-gray-800 pb-6">
        <h3 class="text-2xl font-serif font-bold text-white mb-2 tracking-tight">2-Faktor-Authentifizierung</h3>
        <p class="text-gray-400 text-sm">Erhöhen Sie die Sicherheit Ihres Kontos.</p>
    </div>
    <div>
        @if($twoFactorActive)
            <h3 class="text-xl font-bold text-emerald-400 mb-4 flex items-center gap-3"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> Die Zwei-Faktor-Authentifizierung ist aktiviert</h3>
            <div class="mt-4 text-sm text-gray-400 leading-relaxed bg-gray-950 p-6 rounded-2xl border border-gray-800">
                <p>Jetzt wirst du während der Authentifizierung zur Eingabe eines sicheren, zufälligen Tokens aufgefordert. Du kannst diesen Token über die Google Authenticator-Anwendung deines Smartphones abrufen.</p>
            </div>
            @if(isset($qrCodeSvg))
                <div class="mt-8 border-t border-gray-800 pt-8">
                    <p class="font-bold text-white mb-6">Scannen Sie den folgenden QR-Code mit der Authenticator-App.</p>
                    <div class="p-5 bg-white inline-block rounded-[2rem] shadow-[0_0_30px_rgba(197,160,89,0.2)]">{!! $qrCodeSvg !!}</div>
                </div>
            @endif
            <div class="mt-8">
                <p class="font-bold text-white mb-4">Wiederherstellungscodes</p>
                <p class="text-sm text-gray-400 mb-6 leading-relaxed">Speichere diese Wiederherstellungscodes an einem sicheren Ort. Sie können verwendet werden, um den Zugang zu deinem Konto wiederherzustellen, wenn dein Gerät verloren geht.</p>
                <div x-data="{ showCodes: false }">
                    <div class="flex flex-wrap gap-4 mb-6">
                        <button x-ref="toggleButton" @click="showCodes = !showCodes; $refs.toggleButton.innerText = showCodes ? 'Codes ausblenden' : 'Codes anzeigen'" class="bg-gray-800 text-white border border-gray-700 px-6 py-3 rounded-xl font-black uppercase tracking-widest text-xs hover:bg-gray-700 transition-all">Codes anzeigen</button>
                        <button wire:click="deActivate" class="bg-red-500/10 text-red-500 border border-red-500/30 px-6 py-3 rounded-xl font-black uppercase tracking-widest text-xs hover:bg-red-500 hover:text-white transition-all">Deaktivieren</button>
                    </div>
                    <div x-show="showCodes" style="display: none;" class="animate-fade-in-up">
                        @if($user && $user->profile && $user->profile->two_factor_recovery_codes)
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-gray-950 rounded-2xl p-6 border border-gray-800 font-mono text-sm text-primary font-bold shadow-inner">
                                @foreach(json_decode(decrypt($user->profile->two_factor_recovery_codes), true) as $code)
                                    <div>{{ $code }}</div>
                                @endforeach
                            </div>
                        @endif
                        <button wire:click="generateRecoveryCodes" class="mt-6 bg-gray-800 text-white border border-gray-700 px-6 py-3 rounded-xl font-black uppercase tracking-widest text-xs hover:bg-gray-700 transition-all">Codes neu generieren</button>
                    </div>
                </div>
            </div>
        @else
            <h3 class="text-xl font-bold text-gray-300 mb-4 flex items-center gap-3"><svg class="w-6 h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg> Die Zwei-Faktor-Authentifizierung ist nicht aktiviert</h3>
            <p class="text-sm text-gray-400 mb-8 leading-relaxed bg-gray-950 p-6 rounded-2xl border border-gray-800">Wenn die Zwei-Faktor-Authentifizierung aktiviert ist, werden Sie während des Logins zur Eingabe eines sicheren, zufälligen Tokens aufgefordert.</p>
            @if($confirmPasswordOpener)
                <div class="bg-gray-950 border border-gray-800 rounded-2xl p-6 md:p-8 mt-8 shadow-inner animate-fade-in-up">
                    <h2 class="text-xl font-serif font-bold text-white mb-2">Passwort bestätigen</h2>
                    <p class="text-sm text-gray-400 mb-6">Zu Ihrer Sicherheit bestätigen Sie bitte Ihr Passwort, um fortzufahren.</p>
                    <form wire:submit.prevent="activate" class="space-y-6">
                        <div x-data="{ show: false }" class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="password" placeholder="Passwort" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-5 py-3 text-white focus:ring-primary focus:border-primary transition-all pr-12">
                            <button type="button" @click="show = !show" class="absolute right-4 top-1/2 -translate-y-1/2 focus:outline-none flex items-center justify-center group/eye">
                                <x-heroicon-o-eye-slash x-show="!show" class="w-5 h-5 text-gray-600 hover:text-primary transition-colors" />
                                <div x-show="show" style="display: none;" class="relative">
                                    <div class="absolute inset-0 bg-primary/40 blur-md rounded-full scale-150 animate-pulse"></div>
                                    <x-heroicon-s-eye class="relative z-10 w-6 h-6 text-primary drop-shadow-[0_0_15px_rgba(197,160,89,1)] hover:scale-110 transition-all" />
                                </div>
                            </button>
                            @error('password') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <button type="submit" class="bg-primary text-gray-900 px-8 py-3 rounded-xl font-black uppercase tracking-widest text-xs hover:scale-105 transition-all shadow-glow">Bestätigen</button>
                    </form>
                </div>
            @else
                <button wire:click="confirmPassword" class="bg-primary text-gray-900 px-10 py-4 rounded-2xl font-black uppercase tracking-widest text-sm hover:scale-105 transition-all shadow-[0_0_20px_rgba(197,160,89,0.3)] mt-4">Aktivieren</button>
            @endif
        @endif
    </div>
</div>

<div>
    <style>
        .animate-fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <div class="min-h-[100dvh] bg-gray-50 font-sans text-gray-800 antialiased relative flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">

    {{-- LOGIN CONTAINER --}}
    <div class="relative z-10 w-full max-w-md animate-fade-in-up">

        {{-- Card: Elegantes, helles Design --}}
        <div class="bg-white py-10 px-6 sm:px-10 rounded-3xl shadow-xl border border-gray-200/80 relative">

            {{-- Status / Errors --}}
            @if (session('status'))
                <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs font-semibold flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-3 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-xs font-semibold flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-3 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ session('error') }}
                </div>
            @endif

            {{-- Error Messages --}}
            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3.5 rounded-xl text-xs shadow-sm">
                    <div class="flex items-center mb-2 font-bold uppercase tracking-wider text-[11px] text-red-700">
                        <svg class="w-4 h-4 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Bitte überprüfen:
                    </div>
                    <ul class="list-disc pl-6 space-y-1 text-xs text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ========================================== --}}
            {{-- VIEW: LOGIN --}}
            {{-- ========================================== --}}
            @if ($activeView === 'login')
                <div class="text-center mb-8 relative z-10">
                    {{-- LOGO --}}
                    <a href="/" class="inline-block mb-6 hover:scale-105 transition-transform duration-300">
                        <img class="h-24 mx-auto object-contain" src="{{ asset('shop/projekt/logo/mein-seelenfunke-logo.svg') }}" alt="mein-seelenfunke">
                    </a>

                    <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 tracking-tight">
                        Kunden-Login
                    </h1>
                    <p class="mt-2 text-xs text-gray-500 max-w-sm mx-auto">
                        Melde dich an, um auf deine digitalen Güter und dein Profil zuzugreifen.
                    </p>
                </div>

                <form wire:submit.prevent="login" novalidate class="space-y-5 relative z-10">

                    {{-- E-Mail --}}
                    <div>
                        <label for="email" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2 ml-1">E-Mail-Adresse</label>
                        <div class="relative">
                            <input
                                id="email"
                                type="email"
                                wire:model.defer="email"
                                autocomplete="email"
                                required
                                class="block w-full bg-gray-50/50 border border-gray-300 rounded-xl py-3 px-4 text-gray-900 text-sm focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-sm outline-none transition-all placeholder-gray-400 @error('email') border-red-400 focus:border-red-500 focus:ring-red-500/20 @enderror"
                                placeholder="name@beispiel.de"
                                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            >
                        </div>
                    </div>

                    {{-- Passwort --}}
                    <div>
                        <label for="password" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2 ml-1">Passwort</label>
                        <div class="relative" x-data="{ show: false }">
                            <input
                                :type="show ? 'text' : 'password'"
                                id="password"
                                wire:model.defer="password"
                                autocomplete="current-password"
                                required
                                class="block w-full bg-gray-50/50 border border-gray-300 rounded-xl py-3 px-4 pr-12 text-gray-900 text-sm focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-sm outline-none transition-all placeholder-gray-400 @error('password') border-red-400 focus:border-red-500 focus:ring-red-500/20 @enderror"
                                placeholder="••••••••"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            >
                            <button type="button" x-on:click="show = !show"
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer text-gray-400 hover:text-gray-700 transition-colors"
                                    :aria-label="show ? 'Passwort verbergen' : 'Passwort anzeigen'">
                                <span x-show="!show">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </span>
                                <span x-show="show" style="display: none;">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                </span>
                            </button>
                        </div>
                    </div>

                    {{-- Remember + Passwort vergessen --}}
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center cursor-pointer group">
                            <input id="remember" type="checkbox" wire:model.defer="remember" class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary/30">
                            <span class="ml-2.5 text-xs font-medium text-gray-600 group-hover:text-gray-900 transition-colors">Angemeldet bleiben</span>
                        </label>

                        <button type="button" wire:click="setPasswordResetView" class="text-xs font-bold text-amber-700 hover:text-amber-800 transition-colors">
                            Passwort vergessen?
                        </button>
                    </div>

                    {{-- Submit --}}
                    <div class="pt-2">
                        <button type="submit" wire:loading.attr="disabled"
                                class="w-full flex justify-center items-center gap-2 py-3 px-4 rounded-xl text-xs uppercase tracking-wider font-bold text-gray-900 bg-gradient-to-r from-primary-light to-primary hover:from-primary hover:to-primary-light transition-all duration-200 shadow-sm hover:shadow focus:outline-none disabled:opacity-70 disabled:cursor-wait border border-primary/40">
                            <svg wire:loading class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            <span>Anmelden</span>
                        </button>
                    </div>
                </form>

                <div class="mt-6 relative z-10">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200"></div>
                        </div>
                        <div class="relative flex justify-center text-[10px] font-bold uppercase tracking-wider">
                            <span class="px-3 bg-white text-gray-400">Oder weiter mit</span>
                        </div>
                    </div>

                    <div class="mt-5">
                        <a href="{{ route('auth.google', ['guard' => 'customer']) }}"
                           class="w-full flex justify-center items-center py-2.5 px-4 border border-gray-300 rounded-xl shadow-sm bg-white text-xs font-bold text-gray-700 hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 group">
                            <svg class="w-4 h-4 mr-2.5 group-hover:scale-105 transition-transform duration-200" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
                                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
                                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
                                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
                            </svg>
                            <span>Google Login</span>
                        </a>
                    </div>

                    {{-- REGISTRIERUNG --}}
                    <div class="mt-6 text-center bg-amber-50/60 rounded-2xl p-4 border border-amber-200/60">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-800/80 mb-1">Jetzt hier registrieren</span>
                        <p class="text-xs text-gray-600 mb-1">Noch kein Teil unserer Reise?</p>
                        <a href="{{ route('livewire.auth.register') }}" class="text-xs font-bold text-amber-700 hover:text-amber-900 transition-colors inline-flex items-center gap-1 underline decoration-amber-300 underline-offset-4">
                            Konto erstellen ✨
                        </a>
                    </div>
                </div>
            @endif

            {{-- ========================================== --}}
            {{-- VIEW: TWO FACTOR AUTH --}}
            {{-- ========================================== --}}
            @if ($activeView === 'twoFactor')
                <div class="relative z-10">
                    <form wire:submit.prevent="twoFactorVerify" novalidate class="space-y-6">
                        <div class="text-center mb-6">
                            <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-2xl bg-amber-50 border border-amber-200 text-amber-700 mb-4 shadow-sm">
                                <svg class="h-7 w-7 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2-2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            </div>
                            <h2 class="text-xl font-serif font-bold text-gray-900 tracking-wide">Zwei-Faktor-Authentifizierung</h2>
                            <p class="mt-1.5 text-xs text-gray-500">
                                Gib den Code aus deiner Authenticator-App ein.
                            </p>
                        </div>

                        <div>
                            <input
                                id="code"
                                type="text"
                                wire:model.defer="code"
                                required
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                class="block w-full text-center text-3xl tracking-[0.4em] font-mono text-gray-900 bg-gray-50/50 rounded-xl border border-gray-300 py-3 shadow-inner focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all @error('code') border-red-500/50 @enderror"
                                placeholder="••••••"
                            >
                            @error('code')
                            <p class="mt-2 text-xs font-bold text-center text-red-600">{{ $message }}</p>
                            @enderror
                            @if (session()->has('error'))
                                <p class="mt-2 text-xs font-bold text-center text-red-600">{{ session('error') }}</p>
                            @endif
                        </div>

                        <div class="flex flex-col gap-3 pt-2">
                            <button type="submit" wire:loading.attr="disabled"
                                    class="w-full flex justify-center items-center py-3 px-4 rounded-xl text-xs uppercase tracking-wider font-bold text-gray-900 bg-gradient-to-r from-primary-light to-primary hover:from-primary hover:to-primary-light transition-all duration-200 shadow-sm hover:shadow focus:outline-none disabled:opacity-70 disabled:cursor-wait border border-primary/40">
                                <span wire:loading.remove>Bestätigen</span>
                                <span wire:loading>Prüfe Code...</span>
                            </button>

                            <button type="button" wire:click="setLoginView" class="text-xs font-semibold text-gray-500 hover:text-gray-800 transition-colors text-center p-2">
                                Zurück zur Anmeldung
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- ========================================== --}}
            {{-- VIEW: PASSWORT RESET --}}
            {{-- ========================================== --}}
            @if ($activeView === 'passwordReset')
                <div class="relative z-10">
                    <form wire:submit.prevent="sendLink" novalidate class="space-y-6">
                        <div class="text-center mb-8 relative z-10">
                            {{-- LOGO --}}
                            <a href="/" class="inline-block mb-6 hover:scale-105 transition-transform duration-300">
                                <img class="h-24 mx-auto object-contain" src="{{ asset('shop/projekt/logo/mein-seelenfunke-logo.svg') }}" alt="mein-seelenfunke">
                            </a>

                            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 tracking-tight">
                                Passwort vergessen?
                            </h1>
                            <p class="mt-2 text-xs text-gray-500 max-w-sm mx-auto leading-relaxed">
                                Gib deine E-Mail-Adresse ein und wir senden dir einen Link zum Zurücksetzen deines Passworts.
                            </p>
                        </div>

                        <div>
                            <label for="email_reset" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2 ml-1">E-Mail-Adresse</label>
                            <input
                                id="email_reset"
                                type="email"
                                wire:model.defer="email"
                                autocomplete="email"
                                required
                                class="block w-full bg-gray-50/50 border border-gray-300 rounded-xl py-3 px-4 text-gray-900 text-sm focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-sm outline-none transition-all placeholder-gray-400 @error('email') border-red-400 focus:border-red-500 focus:ring-red-500/20 @enderror"
                                placeholder="name@beispiel.de"
                            >
                            @error('email')
                            <p class="mt-2 text-xs font-bold text-red-600 ml-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-3 pt-2">
                            <button type="submit" wire:loading.attr="disabled"
                                    class="w-full flex justify-center items-center gap-2 py-3 px-4 rounded-xl text-xs uppercase tracking-wider font-bold text-gray-900 bg-gradient-to-r from-primary-light to-primary hover:from-primary hover:to-primary-light transition-all duration-200 shadow-sm hover:shadow focus:outline-none disabled:opacity-70 disabled:cursor-wait border border-primary/40">
                                <svg wire:loading class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                <span wire:loading.remove>Link anfordern</span>
                                <span wire:loading>Sende E-Mail...</span>
                            </button>

                            <button type="button" wire:click="setLoginView"
                                    class="w-full flex justify-center py-2.5 px-4 border border-gray-300 rounded-xl text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 transition-all">
                                Zurück zum Login
                            </button>
                        </div>
                    </form>
                </div>
            @endif

        </div>

        {{-- Footer --}}
        <div class="mt-8 text-center text-xs text-gray-400">
            &copy; {{ date('Y') }} mein-seelenfunke. Alle Rechte vorbehalten.
        </div>

    </div>

    </div>
</div>

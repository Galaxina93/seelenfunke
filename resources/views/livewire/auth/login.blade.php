<div>
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        {{-- Optional: Hier könnte noch das Logo stehen, falls gewünscht --}}
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        {{-- Card: Modern, abgerundet, mit sanftem Schein --}}
        <div class="bg-white py-8 px-4 shadow-[0_0_40px_-10px_rgba(255,255,255,0.1)] sm:rounded-2xl sm:px-10 border border-gray-100">

            {{-- Status / Errors --}}
            @if (session('status'))
                <div class="mb-6 bg-green-50 border border-green-100 text-green-800 px-4 py-3 rounded-xl text-sm flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ session('status') }}
                </div>
            @endif

            {{-- Error Messages --}}
            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-100 text-red-800 px-4 py-3 rounded-xl text-sm shadow-sm">
                    <div class="flex items-center mb-2 font-semibold">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Bitte überprüfen:
                    </div>
                    <ul class="list-disc pl-9 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Login View --}}
            @if ($activeView === 'login')
                <div class="sm:mx-auto sm:w-full sm:max-w-md mb-8 text-center">

                    {{-- LOGO HINZUGEFÜGT --}}
                    <a href="/" class="inline-block mb-6 hover:opacity-80 transition-opacity duration-200">
                        <img class="h-32 mx-auto object-contain" src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="mein-seelenfunke">
                    </a>

                    <h1 class="text-3xl font-serif font-bold text-gray-900 tracking-tight">
                        Willkommen zurück
                    </h1>
                    <p class="mt-2 text-sm text-gray-500">
                        Melde dich an, um fortzufahren.
                    </p>
                </div>

                <form wire:submit.prevent="login" novalidate class="space-y-6">
                    {{-- E-Mail --}}
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">E-Mail-Adresse</label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <input
                                id="email"
                                type="email"
                                wire:model.defer="email"
                                autocomplete="email"
                                required
                                class="block w-full rounded-xl border-gray-300 py-3 px-4 placeholder-gray-400 focus:border-primary focus:ring-primary sm:text-sm shadow-sm transition duration-200 ease-in-out @error('email') border-red-300 text-red-900 placeholder-red-300 focus:ring-red-500 focus:border-red-500 @enderror"
                                placeholder="name@beispiel.de"
                                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                aria-describedby="email-error"
                            >
                        </div>
                        @error('email')
                        <p id="email-error" class="mt-1 text-sm text-red-600 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            {{ $message }}
                        </p>
                        @enderror
                    </div>

                    {{-- Passwort --}}
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Passwort</label>
                        <div class="mt-1 relative rounded-md shadow-sm" x-data="{ show: false }">
                            <input
                                :type="show ? 'text' : 'password'"
                                id="password"
                                wire:model.defer="password"
                                autocomplete="current-password"
                                required
                                class="block w-full rounded-xl border-gray-300 py-3 px-4 pr-10 placeholder-gray-400 focus:border-primary focus:ring-primary sm:text-sm shadow-sm transition duration-200 ease-in-out @error('password') border-red-300 text-red-900 placeholder-red-300 focus:ring-red-500 focus:border-red-500 @enderror"
                                placeholder="••••••••"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                aria-describedby="password-error"
                            >
                            <button type="button" x-on:click="show = !show"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer text-gray-400 hover:text-gray-600 transition-colors"
                                    :aria-label="show ? 'Passwort verbergen' : 'Passwort anzeigen'">
                                <span x-show="!show">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </span>
                                <span x-show="show" style="display: none;">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                </span>
                            </button>
                        </div>
                        @error('password')
                        <p id="password-error" class="mt-1 text-sm text-red-600 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            {{ $message }}
                        </p>
                        @enderror
                    </div>

                    {{-- Remember + Passwort vergessen --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input
                                id="remember"
                                type="checkbox"
                                wire:model.defer="remember"
                                class="h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary transition duration-150 ease-in-out cursor-pointer"
                            >
                            <label for="remember" class="ml-2 block text-sm text-gray-700 cursor-pointer">Angemeldet bleiben</label>
                        </div>
                        <div class="text-sm">
                            <button type="button" wire:click="setPasswordResetView" class="font-medium text-primary hover:text-primary-dark hover:underline transition duration-150">
                                Passwort vergessen?
                            </button>
                        </div>
                    </div>

                    {{-- Submit --}}
                    <div>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="w-full flex justify-center items-center gap-2 py-3 px-4 border border-transparent rounded-xl shadow-lg shadow-primary/30 text-sm font-semibold text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transform hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-70 disabled:cursor-wait"
                        >
                            {{-- Loading Icon (nur sichtbar beim Laden) --}}
                            <svg wire:loading class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>

                            {{-- Text (immer sichtbar, Icon erscheint links daneben) --}}
                            <span>Anmelden</span>
                        </button>
                    </div>
                </form>

                <div class="mt-8">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-3 bg-white text-gray-500 font-medium">Oder weiter mit</span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <a href="{{ route('auth.google', ['guard' => 'customer']) }}"
                           class="w-full inline-flex justify-center items-center py-3 px-4 border border-gray-300 rounded-xl shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-200 group">

                            {{-- Das offizielle bunte Google Logo --}}
                            <svg class="w-5 h-5 mr-2 group-hover:scale-110 transition-transform duration-200" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
                                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
                                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
                                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
                            </svg>

                            <span>Weiter mit Google</span>
                        </a>
                    </div>
                </div>

            @endif

            {{-- Two-Factor --}}
            @if ($activeView === 'twoFactor')
                <form wire:submit.prevent="twoFactorVerify" novalidate class="space-y-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-primary/10 mb-4">
                            <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900">Authentifizierung</h2>
                        <p class="mt-2 text-sm text-gray-500">
                            Gib den Code aus deiner Authenticator App ein.
                        </p>
                    </div>

                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700 text-center uppercase tracking-wider">Sicherheitscode</label>
                        <div class="mt-2">
                            <input
                                id="code"
                                type="text"
                                wire:model.defer="code"
                                required
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                class="block w-full text-center text-2xl tracking-[0.5em] font-mono rounded-xl border-gray-300 py-3 shadow-sm focus:ring-primary focus:border-primary @error('code') border-red-500 @enderror"
                                placeholder="123456"
                            >
                        </div>
                        @error('code')
                        <p class="mt-2 text-sm text-center text-red-600">{{ $message }}</p>
                        @enderror
                        @if (session()->has('error'))
                            <p class="mt-2 text-sm text-center text-red-600">{{ session('error') }}</p>
                        @endif
                    </div>

                    <div class="flex flex-col gap-3">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-lg shadow-primary/30 text-sm font-semibold text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-all duration-200"
                        >
                            <span wire:loading.remove>Bestätigen</span>
                            <span wire:loading>
                                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            </span>
                        </button>
                        <button type="button" wire:click="setLoginView" class="text-sm text-gray-500 hover:text-gray-900 transition-colors text-center mt-2">
                            Zurück zur Anmeldung
                        </button>
                    </div>
                </form>
            @endif

            {{-- Passwort zurücksetzen --}}
            @if ($activeView === 'passwordReset')
                <form wire:submit.prevent="sendLink" novalidate class="space-y-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-50 mb-4">
                            <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900">Passwort vergessen?</h2>
                        <p class="mt-2 text-sm text-gray-500">
                            Kein Problem. Gib deine E-Mail-Adresse ein und wir senden dir einen Link zum Zurücksetzen.
                        </p>
                    </div>

                    <div>
                        <label for="email_reset" class="block text-sm font-medium text-gray-700">E-Mail-Adresse</label>
                        <div class="mt-1">
                            <input
                                id="email_reset"
                                type="email"
                                wire:model.defer="email"
                                autocomplete="email"
                                required
                                class="block w-full rounded-xl border-gray-300 py-3 px-4 shadow-sm focus:ring-primary focus:border-primary sm:text-sm @error('email') border-red-500 @enderror"
                                placeholder="name@beispiel.de"
                            >
                        </div>
                        @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-3 mt-8">
                        {{-- Link senden --}}
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="w-full flex justify-center items-center gap-2 py-3 px-4 border border-transparent rounded-xl shadow-lg shadow-primary/30 text-sm font-semibold text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-all duration-200 disabled:opacity-70 disabled:cursor-wait"
                        >
                            {{-- Das Icon erscheint nur beim Laden links --}}
                            <svg wire:loading class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>

                            {{-- Der Text bleibt immer gleich (oder ändert sich optional) --}}
                            <span>
                                <span wire:loading.remove>Link anfordern</span>
                                <span wire:loading>Senden...</span>
                            </span>
                        </button>

                        {{-- Zurück-Button --}}
                        <button
                            type="button"
                            wire:click="setLoginView"
                            class="w-full flex justify-center py-3 px-4 border border-gray-300 rounded-xl shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors"
                        >
                            Zurück zum Login
                        </button>
                    </div>
                </form>
            @endif

        </div>

        {{-- Footer --}}
        <div class="mt-8 text-center text-xs text-gray-500 opacity-60">
            &copy; {{ date('Y') }} mein-seelenfunke. Alle Rechte vorbehalten.
        </div>

    </div>
</div>

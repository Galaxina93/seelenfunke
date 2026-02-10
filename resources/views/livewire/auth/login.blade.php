<div>
    <div>
        <div class="mx-auto w-full max-w-md">

            {{-- Card --}}
            <div class="bg-white shadow rounded-lg px-8 py-6">

                {{-- Status / Errors --}}
                @if (session('status'))
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md text-sm">
                        {{ session('status') }}
                    </div>
                @endif

                {{-- Error Messages --}}
                @if ($errors->any())
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md text-sm">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Login View --}}
                @if ($activeView === 'login')
                    <div class="flex justify-center">
                        <h1 class="font-bold text-2xl">
                            @if($this->guard === "customer") Kunden-Login @endif
                            @if($this->guard === "employee") Mitarbeiter-Login @endif
                            @if($this->guard === "admin") Admin-Login @endif
                        </h1>
                    </div>
                    <form wire:submit.prevent="login" novalidate class="space-y-6">
                        {{-- E-Mail --}}
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">E-Mail-Adresse</label>
                            <div class="mt-1">
                                <input
                                    id="email"
                                    type="email"
                                    wire:model.defer="email"
                                    autocomplete="email"
                                    required
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 sm:text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('email') border-red-500 @enderror"
                                    placeholder="{{ $guard }}@mein-seelenfunke.de"
                                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                    aria-describedby="email-error"
                                >
                            </div>
                            @error('email')
                            <p id="email-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Passwort --}}
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Passwort</label>
                            <div class="mt-1 relative" x-data="{ show: false }">
                                <input
                                    :type="show ? 'text' : 'password'"
                                    id="password"
                                    wire:model.defer="password"
                                    autocomplete="current-password"
                                    required
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 sm:text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('password') border-red-500 @enderror"
                                    placeholder="••••••••"
                                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                    aria-describedby="password-error"
                                >
                                <button type="button" x-on:click="show = !show"
                                        class="absolute inset-y-0 right-1 pr-3 flex items-center text-gray-500 focus:outline-none"
                                        :aria-label="show ? 'Passwort verbergen' : 'Passwort anzeigen'">
                                    <span x-show="!show" class="sr-only">Passwort anzeigen</span>
                                    <span x-show="show" class="sr-only">Passwort verbergen</span>
                                    <x-heroicon-m-eye-slash x-show="!show" class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100 h-5 w-5" aria-hidden="true"/>
                                    <x-heroicon-m-eye x-show="show" class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100 h-5 w-5" aria-hidden="true"/>
                                </button>
                            </div>
                            @error('password')
                            <p id="password-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Remember + Passwort vergessen --}}
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input
                                    id="remember"
                                    type="checkbox"
                                    wire:model.defer="remember"
                                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-2 focus:ring-indigo-500"
                                >
                                <label for="remember" class="ml-2 block text-sm text-gray-700">Angemeldet bleiben</label>
                            </div>
                            <div class="text-sm">
                                <button type="button" wire:click="setPasswordResetView" class="font-medium text-indigo-600 hover:underline">
                                    Passwort vergessen?
                                </button>
                            </div>
                        </div>

                        {{-- Submit --}}
                        <div>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="w-full bg-primary mt-8 inline-block text-white font-semibold py-3 px-8 rounded-lg hover:bg-primary-dark transition focus:outline-none focus:ring-2 focus:ring-offset-2"
                            >
                                <span wire:loading.remove>Anmelden</span>
                                <span wire:loading class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </form>

                    <div class="mt-6">
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-300"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-2 bg-white text-gray-500">Oder weiter mit</span>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-3">
                            <a href="{{ route('auth.google', ['guard' => $guard]) }}"
                               class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Sign in with Google</span>
                                <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.813 1.053 6.44 2.56l2.36-2.36c-2.32-2.16-5.44-4.187-8.8-4.187-7.227 0-13.067 5.84-13.067 13.067s5.84 13.067 13.067 13.067c3.773 0 6.64-1.227 8.547-3.2 1.96-1.96 2.56-4.933 2.56-7.547 0-.547-.053-1.08-.16-1.6H12.48z"></path>
                                </svg>
                                <span class="ml-2">Google</span>
                            </a>
                        </div>
                    </div>

                @endif

                {{-- Two-Factor --}}
                @if ($activeView === 'twoFactor')
                    <form wire:submit.prevent="twoFactorVerify" novalidate class="space-y-6">
                        <div>
                            <h2 class="text-xl font-semibold">2-Faktor-Authentifizierung</h2>
                            <p class="text-sm text-gray-600">
                                Du hast keinen Zugriff auf den Google Authenticator? Nutze einen deiner Sicherheitscodes.
                            </p>
                        </div>

                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700">Code</label>
                            <div class="mt-1">
                                <input
                                    id="code"
                                    type="text"
                                    wire:model.defer="code"
                                    required
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 sm:text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('code') border-red-500 @enderror"
                                    placeholder="123456"
                                >
                            </div>
                            @error('code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @if (session()->has('error'))
                                <p class="mt-1 text-sm text-red-600">{{ session('error') }}</p>
                            @endif
                        </div>

                        <div class="flex items-center justify-between">
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="bg-primary inline-block text-white font-semibold py-3 px-8 rounded-lg hover:bg-primary-dark transition"
                            >
                                <span wire:loading.remove>Bestätigen</span>
                                <span wire:loading>
                            <svg class="animate-spin h-5 w-5 inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                        </span>
                            </button>
                            <button type="button" wire:click="setLoginView" class="text-sm text-gray-600 hover:underline">
                                Zurück zur Anmeldung
                            </button>
                        </div>
                    </form>
                @endif

                {{-- Passwort zurücksetzen --}}
                @if ($activeView === 'passwordReset')
                    <form wire:submit.prevent="sendLink" novalidate class="space-y-6">
                        <div>
                            <h2 class="text-xl font-semibold">Passwort vergessen</h2>
                            <p class="text-sm text-gray-600">
                                Gib deine E-Mail-Adresse ein und erhalte einen Link zum Zurücksetzen.
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
                                    class="block w-full rounded-lg border px-3 py-2 sm:text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('email') border-red-500 @enderror"
                                    placeholder="name@beispiel.de"
                                >
                            </div>
                            @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-4 mt-8">
                            {{-- Link senden --}}
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="w-full bg-primary text-white font-semibold py-3 px-8 rounded-lg hover:bg-primary-dark transition flex items-center justify-center gap-2"
                            >
                                <span wire:loading.remove>Link senden</span>

                                <span wire:loading class="flex items-center gap-2">
                                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                </span>
                            </button>


                            {{-- Zurück-Button im Secondary-Stil --}}
                            <button
                                type="button"
                                wire:click="setLoginView"
                                class="w-full bg-white text-gray-700 border border-gray-300 font-semibold py-3 px-8 rounded-lg hover:bg-gray-100 transition"
                            >
                                Zurück
                            </button>
                        </div>
                    </form>
                @endif

            </div>

            {{-- Optionaler Hinweis oder Footer --}}
            <div class="mt-6 text-center text-xs text-gray-500">
                &copy; {{ date('Y') }} mein-seelenfunke. Alle Rechte vorbehalten.
            </div>

        </div>
    </div>

</div>

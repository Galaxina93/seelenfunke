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

            <div class="relative z-10 text-center mb-8">
                <a href="/" class="inline-block mb-6 hover:scale-105 transition-transform duration-300">
                    <img class="h-24 mx-auto object-contain" src="{{ asset('shop/projekt/logo/mein-seelenfunke-logo.svg') }}" alt="mein-seelenfunke">
                </a>
                <h1 class="text-2xl font-serif font-bold text-gray-900 tracking-tight">Passwort erneuern</h1>
                <p class="mt-2 text-xs text-gray-500 max-w-sm mx-auto">
                    Gib deine E-Mail und dein neues Wunschpasswort ein.
                </p>
            </div>

            <form wire:submit.prevent="submit" novalidate class="space-y-5 relative z-10">
            
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
                        >
                    </div>
                </div>

                {{-- Neues Passwort --}}
                <div>
                    <label for="password" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2 ml-1">Neues Passwort</label>
                    <div class="relative" x-data="{ show: false }">
                        <input
                            :type="show ? 'text' : 'password'"
                            id="password"
                            wire:model.defer="password"
                            required
                            class="block w-full bg-gray-50/50 border border-gray-300 rounded-xl py-3 px-4 pr-12 text-gray-900 text-sm focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-sm outline-none transition-all placeholder-gray-400 @error('password') border-red-400 focus:border-red-500 focus:ring-red-500/20 @enderror"
                            placeholder="••••••••"
                        >
                        <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer text-gray-400 hover:text-gray-700 transition-colors">
                            <span x-show="!show"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></span>
                            <span x-show="show" style="display: none;"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg></span>
                        </button>
                    </div>
                </div>

                {{-- Bestätigung --}}
                <div>
                    <label for="passwordConfirm" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2 ml-1">Passwort bestätigen</label>
                    <div class="relative" x-data="{ show: false }">
                        <input
                            :type="show ? 'text' : 'password'"
                            id="passwordConfirm"
                            wire:model.defer="passwordConfirm"
                            required
                            class="block w-full bg-gray-50/50 border border-gray-300 rounded-xl py-3 px-4 pr-12 text-gray-900 text-sm focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-sm outline-none transition-all placeholder-gray-400 @error('passwordConfirm') border-red-400 focus:border-red-500 focus:ring-red-500/20 @enderror"
                            placeholder="••••••••"
                        >
                        <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer text-gray-400 hover:text-gray-700 transition-colors">
                            <span x-show="!show"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></span>
                            <span x-show="show" style="display: none;"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg></span>
                        </button>
                    </div>
                </div>

                {{-- Button --}}
                <div class="pt-2">
                    <button type="submit" wire:loading.attr="disabled"
                            class="w-full flex justify-center items-center gap-2 py-3 px-4 rounded-xl text-xs uppercase tracking-wider font-bold text-gray-900 bg-gradient-to-r from-primary-light to-primary hover:from-primary hover:to-primary-light transition-all duration-200 shadow-sm hover:shadow focus:outline-none disabled:opacity-70 disabled:cursor-wait border border-primary/40">
                        <svg wire:loading class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        <span>Passwort speichern</span>
                    </button>
                </div>
                
                {{-- Zurück zum Login --}}
                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}" class="text-xs font-semibold text-gray-500 hover:text-gray-900 transition-colors inline-flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        Zurück zur Anmeldung
                    </a>
                </div>

            </form>
        </div>

        {{-- Footer --}}
        <div class="mt-8 text-center text-xs text-gray-400">
            &copy; {{ date('Y') }} mein-seelenfunke. Alle Rechte vorbehalten.
        </div>

    </div>

    </div>
</div>

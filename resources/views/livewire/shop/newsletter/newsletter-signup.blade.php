<div>
    <div class="w-full">
        @if($success)
            <div class="bg-green-500/10 border border-green-500/20 rounded-lg p-4 text-center animate-fade-in-up">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76" />
                </svg>
                <h4 class="text-white font-bold mb-1">Fast geschafft!</h4>
                <p class="text-sm text-gray-300">Bitte prüfe deine E-Mails, um die Anmeldung zu bestätigen.</p>
            </div>
        @else
            <h4 class="text-lg font-serif font-semibold mb-1 text-primary">Newsletter</h4>

            {{-- Der neue Slogan --}}
            <p class="text-sm text-gray-100 font-medium italic mb-3">
                Ein kleiner Lichtblick für dein Postfach.
            </p>

            <p class="text-xs text-gray-400 mb-4 leading-relaxed">
                Erhalte exklusive Angebote und Einblicke in unsere Manufaktur.
            </p>

            <form wire:submit.prevent="subscribe" class="space-y-3">
                <div>
                    <input type="email"
                           wire:model="email"
                           placeholder="Deine E-Mail Adresse"
                           class="w-full bg-gray-800 border border-gray-700 text-white text-sm rounded-lg px-4 py-3 focus:ring-primary focus:border-primary placeholder-gray-500 transition-colors">
                    @error('email') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-start gap-2">
                    <input type="checkbox"
                           id="privacy"
                           wire:model.live="privacy_accepted"
                           class="mt-1 w-4 h-4 rounded bg-gray-800 border-gray-700 text-primary focus:ring-offset-gray-900 focus:ring-primary cursor-pointer">
                    <label for="privacy" class="text-xs text-gray-400 cursor-pointer leading-tight">
                        Ich stimme der <a href="/datenschutz" class="text-gray-300 underline hover:text-white">Datenschutzerklärung</a> zu.
                    </label>
                </div>
                @error('privacy_accepted') <span class="text-red-400 text-xs block">{{ $message }}</span> @enderror

                <button type="submit"
                        @disabled(!$privacy_accepted)
                        class="w-full font-bold py-2.5 rounded-lg transition shadow-lg flex items-center justify-center gap-2
                               disabled:bg-gray-600 disabled:text-gray-400 disabled:cursor-not-allowed disabled:shadow-none
                               enabled:bg-primary enabled:text-white enabled:hover:bg-primary-dark enabled:shadow-primary/20">
                    <span wire:loading.remove>Anmelden</span>
                    <span wire:loading>
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </span>
                </button>
            </form>
        @endif
    </div>
</div>

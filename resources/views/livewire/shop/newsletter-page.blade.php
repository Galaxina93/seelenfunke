<div class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-10 rounded-2xl shadow-xl border border-gray-100">

        {{-- Header --}}
        <div class="text-center">
            <h2 class="mt-2 text-3xl font-serif font-bold text-gray-900">
                Newsletter
            </h2>
            <p class="mt-2 text-sm text-gray-500">
                Bleibe auf dem Laufenden über neue Unikate und Angebote aus unserer Manufaktur.
            </p>
        </div>

        {{-- Tabs --}}
        <div class="flex justify-center border-b border-gray-200">
            <button wire:click="switchTab('subscribe')"
                    class="pb-4 px-6 text-sm font-medium transition-colors border-b-2 {{ $activeTab === 'subscribe' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Anmelden
            </button>
            <button wire:click="switchTab('unsubscribe')"
                    class="pb-4 px-6 text-sm font-medium transition-colors border-b-2 {{ $activeTab === 'unsubscribe' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Abmelden
            </button>
        </div>

        {{-- Meldungen (Flash Messages & Verification Return) --}}
        @if (session('verified'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm text-center">
                {{ session('verified') }}
            </div>
        @endif
        @if (session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm text-center">
                {{ session('error') }}
            </div>
        @endif
        @if ($successMessage)
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg text-sm text-center animate-fade-in-up">
                {{ $successMessage }}
            </div>
        @endif

        {{-- FORMULAR: ANMELDEN --}}
        @if($activeTab === 'subscribe' && !$successMessage)
            <form class="mt-8 space-y-6" wire:submit.prevent="subscribe">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="email-address" class="sr-only">E-Mail Adresse</label>
                        <input id="email-address" wire:model="email" type="email" required
                               class="appearance-none rounded-lg relative block w-full px-4 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                               placeholder="E-Mail Adresse">
                    </div>
                    @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        {{-- WICHTIG: .live sorgt für sofortiges Update des Buttons --}}
                        <input id="privacy" wire:model.live="privacy_accepted" type="checkbox" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded cursor-pointer transition-colors">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="privacy" class="font-medium text-gray-700 cursor-pointer select-none">Datenschutz</label>
                        <p class="text-gray-500 text-xs">Ich stimme zu, dass meine Angaben für den Versand des Newsletters verarbeitet werden. Details in der <a href="{{ route('datenschutz') }}" class="text-primary hover:underline">Datenschutzerklärung</a>.</p>
                    </div>
                </div>
                @error('privacy_accepted') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror

                <div>
                    {{-- BUTTON: Gesperrt solange Haken fehlt --}}
                    <button type="submit"
                            @disabled(!$privacy_accepted)
                            class="group relative w-full flex justify-center py-3 px-4 text-sm font-bold rounded-full transition-all duration-300
                                   disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed disabled:shadow-none
                                   enabled:bg-primary enabled:text-white enabled:shadow-lg enabled:shadow-primary/30
                                   enabled:hover:bg-primary-dark enabled:hover:shadow-primary/50 enabled:hover:-translate-y-0.5 enabled:hover:scale-[1.01]
                                   enabled:active:scale-[0.99] enabled:active:translate-y-0">

                        <span wire:loading.remove class="flex items-center gap-2">
                            <span>Kostenlos abonnieren</span>
                            {{-- Pfeil Icon bei Hover (nur wenn enabled) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-300 group-enabled:group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </span>

                        <span wire:loading>Verarbeite...</span>
                    </button>
                </div>
            </form>
        @endif

        {{-- FORMULAR: ABMELDEN --}}
        @if($activeTab === 'unsubscribe' && !$successMessage)
            <div class="mt-8">
                <p class="text-sm text-gray-500 text-center mb-6">
                    Schade, dass du gehst! Gib deine E-Mail ein, um dich aus dem Verteiler auszutragen.
                </p>
                <form class="space-y-6" wire:submit.prevent="unsubscribe">
                    <div>
                        <label for="email-unsub" class="sr-only">E-Mail Adresse</label>
                        <input id="email-unsub" wire:model="email" type="email" required
                               class="appearance-none rounded-lg relative block w-full px-4 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-red-500 focus:border-red-500 focus:z-10 sm:text-sm"
                               placeholder="E-Mail Adresse">
                        @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-full text-gray-700 bg-gray-100 hover:bg-gray-200 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition">
                            <span wire:loading.remove>Newsletter abstellen</span>
                            <span wire:loading>Verarbeite...</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif

    </div>
</div>

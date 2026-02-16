<div class="min-h-screen bg-slate-50/50 flex items-center justify-center px-4 py-12 sm:px-6 lg:px-8 font-sans">
    <div class="max-w-md w-full bg-white p-8 sm:p-10 rounded-[2.5rem] shadow-xl shadow-slate-200/50 border border-slate-100 relative overflow-hidden transition-all duration-500 hover:shadow-2xl">

        {{-- Dekorativer Hintergrund --}}
        <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full blur-3xl -z-10 translate-x-1/3 -translate-y-1/3"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-secondary/5 rounded-full blur-3xl -z-10 -translate-x-1/3 translate-y-1/3"></div>

        {{-- Header --}}
        <div class="text-center mb-8">
            <h2 class="text-3xl font-serif font-bold text-slate-800 tracking-tight">
                Seelenpost
            </h2>
            <p class="mt-3 text-sm text-slate-500 leading-relaxed max-w-xs mx-auto">
                Bleibe auf dem Laufenden über neue Unikate und Angebote aus unserer Manufaktur.
            </p>
        </div>

        {{-- Tabs --}}
        <div class="flex justify-center mb-8 bg-slate-50 p-1 rounded-2xl inline-flex w-full">
            <button wire:click="$set('activeTab', 'subscribe')"
                    class="flex-1 py-2.5 px-4 rounded-xl text-sm font-bold transition-all duration-300 {{ $activeTab === 'subscribe' ? 'bg-white text-primary shadow-sm ring-1 ring-slate-100' : 'text-slate-400 hover:text-slate-600' }}">
                Anmelden
            </button>
            <button wire:click="$set('activeTab', 'unsubscribe')"
                    class="flex-1 py-2.5 px-4 rounded-xl text-sm font-bold transition-all duration-300 {{ $activeTab === 'unsubscribe' ? 'bg-white text-rose-500 shadow-sm ring-1 ring-slate-100' : 'text-slate-400 hover:text-slate-600' }}">
                Abmelden
            </button>
        </div>

        {{-- Meldungen --}}
        @if (session('verified'))
            <div class="mb-6 bg-emerald-50 border border-emerald-100 text-emerald-600 px-4 py-3 rounded-2xl text-sm text-center flex items-center justify-center gap-2 animate-fade-in-up">
                <i class="bi bi-check-circle-fill"></i> {{ session('verified') }}
            </div>
        @endif
        @if ($successMessage)
            <div class="mb-6 bg-blue-50 border border-blue-100 text-blue-600 px-4 py-3 rounded-2xl text-sm text-center flex items-center justify-center gap-2 animate-fade-in-up">
                <i class="bi bi-info-circle-fill"></i> {{ $successMessage }}
            </div>
        @endif

        {{-- FORMULAR: ANMELDEN --}}
        @if($activeTab === 'subscribe' && !$successMessage)
            <form class="space-y-5 animate-fade-in" wire:submit.prevent="subscribe" wire:key="form-subscribe">
                <div class="group">
                    <div class="relative">
                        <input id="email-address" wire:model="email" type="email" required
                               class="block w-full px-5 py-4 bg-slate-50 border-0 rounded-2xl text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all shadow-inner text-sm font-medium"
                               placeholder="Deine E-Mail Adresse">
                        <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-primary/50">
                            <i class="bi bi-envelope"></i>
                        </div>
                    </div>
                    {{-- Validierung nur anzeigen, wenn im Anmelde-Tab --}}
                    @error('email') <span class="text-rose-500 text-xs mt-2 ml-2 font-medium flex gap-1"><i class="bi bi-exclamation-triangle"></i> {{ $message }}</span> @enderror
                </div>

                <div class="flex items-start gap-3 px-1">
                    <div class="flex items-center h-5 mt-0.5">
                        <input id="privacy" wire:model.live="privacy_accepted" type="checkbox"
                               class="w-4 h-4 text-primary bg-slate-100 border-slate-300 rounded focus:ring-primary focus:ring-offset-0 cursor-pointer transition-colors">
                    </div>
                    <div class="text-xs text-slate-500 leading-relaxed">
                        <label for="privacy" class="font-medium text-slate-700 cursor-pointer select-none hover:text-primary transition-colors">Datenschutz akzeptieren</label>
                        <p class="mt-1">Details in der <a href="{{ route('datenschutz') }}" class="text-primary font-bold hover:underline underline-offset-2">Datenschutzerklärung</a>.</p>
                    </div>
                </div>

                <button type="submit" @disabled(!$privacy_accepted)
                class="group relative w-full flex justify-center py-4 px-6 text-sm font-black uppercase tracking-wider rounded-2xl transition-all duration-300
                               disabled:bg-slate-100 disabled:text-slate-400 enabled:bg-primary enabled:text-white enabled:shadow-lg enabled:hover:-translate-y-1 overflow-hidden">
                    <span wire:loading.remove wire:target="subscribe" class="flex items-center gap-2 relative z-20">
                        <span>Kostenlos abonnieren</span>
                        <i class="bi bi-arrow-right transition-transform group-enabled:group-hover:translate-x-1"></i>
                    </span>
                    <span wire:loading wire:target="subscribe" class="flex items-center gap-2 relative z-20">
                        <i class="bi bi-arrow-repeat animate-spin"></i> Verarbeite...
                    </span>
                </button>
            </form>
        @endif

        {{-- FORMULAR: ABMELDEN --}}
        @if($activeTab === 'unsubscribe' && !$successMessage)
            <div class="mt-4 animate-fade-in" x-data="{ state: 'normal' }" wire:key="form-unsubscribe">

                {{-- FUNKI BEREICH --}}
                <div class="relative flex flex-col items-center justify-center h-48 mb-6">

                    {{-- Sprechblase --}}
                    <div class="absolute -top-4 right-8 bg-white border border-slate-100 shadow-sm px-4 py-2 rounded-2xl rounded-bl-none transform -rotate-2 z-20 animate-bounce-slow">
                        <p class="text-xs font-black text-slate-600">Schade, dass du...</p>
                    </div>

                    {{-- Funki Bilder --}}
                    <div class="relative w-32 h-32 transition-transform duration-500"
                         :class="state !== 'normal' ? 'scale-110 rotate-2' : 'scale-100'">

                        <img src="{{ asset('images/projekt/funki/funki_cry.png') }}"
                             class="absolute inset-0 w-full h-full object-contain transition-opacity duration-300"
                             :class="state === 'normal' ? 'opacity-100' : 'opacity-0'">

                        <img src="{{ asset('images/projekt/funki/funki_cry_more.png') }}"
                             class="absolute inset-0 w-full h-full object-contain transition-opacity duration-300"
                             :class="state === 'focus' ? 'opacity-100' : 'opacity-0'">

                        <img src="{{ asset('images/projekt/funki/funki_gave_up.png') }}"
                             class="absolute inset-0 w-full h-full object-contain transition-opacity duration-300"
                             :class="state === 'hover' ? 'opacity-100' : 'opacity-0'">
                    </div>
                </div>

                <form class="space-y-5" wire:submit.prevent="unsubscribe">
                    <div class="group">
                        <div class="relative">
                            <input id="email-unsub" wire:model="email" type="email" required
                                   @focus="state = 'focus'" @blur="state = 'normal'"
                                   class="block w-full px-5 py-4 bg-slate-50 border-0 rounded-2xl text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-rose-500/20 focus:bg-white transition-all shadow-inner text-sm font-medium"
                                   placeholder="E-Mail Adresse">
                            <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-rose-400">
                                <i class="bi bi-x-circle"></i>
                            </div>
                        </div>
                        {{-- Validierung hier anzeigen --}}
                        @error('email') <span class="text-rose-500 text-xs mt-2 ml-2 font-medium flex gap-1"><i class="bi bi-exclamation-triangle"></i> {{ $message }}</span> @enderror
                    </div>

                    <button type="submit"
                            @mouseenter="state = 'hover'" @mouseleave="state = 'normal'"
                            class="group relative w-full flex justify-center py-4 px-6 text-sm font-black uppercase tracking-wider rounded-2xl text-slate-600 bg-slate-100 hover:bg-rose-50 hover:text-rose-600 transition-all duration-300 hover:shadow-lg hover:-translate-y-1 active:scale-[0.98]">
                        <span wire:loading.remove wire:target="unsubscribe" class="flex items-center gap-2">
                            <i class="bi bi-box-arrow-right"></i> Newsletter abstellen
                        </span>
                        <span wire:loading wire:target="unsubscribe" class="flex items-center gap-2">
                            <i class="bi bi-arrow-repeat animate-spin"></i> Verarbeite...
                        </span>
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>

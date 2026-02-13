{{-- FOOTER --}}
@if($context !== 'preview')
    <div class="p-4 border-t border-gray-200 bg-white z-30 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] shrink-0"
         x-data="{ saved: false }"
         x-on:cart-updated.window="saved = true; setTimeout(() => saved = false, 6000)">

        <div class="max-w-4xl mx-auto space-y-4">

            {{-- 1. RECHTLICHER HINWEIS --}}
            <div @class([
                    'p-3 rounded-xl border transition-all duration-200',
                    'bg-gray-50 border-gray-200' => !$errors->has('config_confirmed'),
                    'bg-red-50 border-red-200' => $errors->has('config_confirmed')
                ])>
                <label class="flex items-start gap-3 cursor-pointer group">
                    <input type="checkbox"
                           wire:model.live="config_confirmed"
                           class="mt-1 w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary">
                    <span class="text-[11px] text-gray-600 leading-relaxed group-hover:text-gray-900 transition-colors">
                        @if($isDigital)
                            Ich stimme ausdrücklich zu, dass mit der Ausführung des Vertrags vor Ablauf der Widerrufsfrist begonnen wird. Mir ist bekannt, dass ich durch diese Zustimmung mein Widerrufsrecht verliere.
                        @else
                            Ich habe meine Texte, Logos und Positionen geprüft. Die Vorschau ist eine <strong>Visualisierung</strong>; handwerkliche Abweichungen sind möglich. Individualisierte Artikel sind vom <strong>Widerrufsrecht ausgeschlossen</strong>.
                        @endif
                        <a href="/agb" target="_blank" class="text-primary underline font-bold ml-1">AGB Details</a>.
                    </span>
                </label>
            </div>

            {{-- 2. ACTION AREA: MENGE & KAUFEN BUTTON --}}
            <div class="flex flex-col sm:flex-row gap-3 h-auto sm:h-14">

                {{-- MENGENAUSWAHL (Links) --}}
                <div class="relative w-full sm:w-32 h-14 bg-gray-100 rounded-xl border border-transparent hover:border-gray-300 focus-within:border-primary focus-within:bg-white transition-all flex items-center">
                    <label class="absolute left-4 text-[10px] font-bold text-gray-500 uppercase tracking-wider pointer-events-none">Menge</label>
                    <select wire:model.live="qty" wire:change="calculatePrice" class="appearance-none bg-transparent w-full h-full text-right font-bold text-xl text-gray-900 focus:outline-none cursor-pointer pl-4 pr-10 pt-3">
                        @for($i = 1; $i <= 100; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                    {{-- Chevron Icon --}}
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>

                {{-- HAUPT-BUTTON (Rechts) --}}
                <button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    @disabled(!$config_confirmed)
                    :class="saved ? 'bg-green-600 hover:bg-green-700' : ({{ $config_confirmed ? 'true' : 'false' }} ? 'bg-gray-900 hover:bg-black' : 'bg-gray-200 cursor-not-allowed text-gray-400')"
                    class="flex-1 h-14 rounded-xl font-bold text-lg transition-all duration-300 flex items-center justify-center shadow-lg hover:shadow-xl disabled:shadow-none text-white relative overflow-hidden group"
                >
                    {{-- PREIS ANZEIGE IM BUTTON --}}
                    <div class="absolute left-0 top-0 bottom-0 bg-black/10 px-5 flex flex-col justify-center items-start border-r border-white/10 min-w-[100px]">
                        {{-- Gesamtpreis --}}
                        <span class="font-serif font-bold leading-none tracking-wide {{ $qty > 1 ? 'text-base' : 'text-lg' }}">
                            {{ number_format($totalPrice / 100, 2, ',', '.') }} €
                        </span>
                        {{-- Einzelpreis (nur wenn Menge > 1) --}}
                        @if($qty > 1)
                            <span class="text-[9px] opacity-80 font-normal leading-none mt-0.5">
                                á {{ number_format($currentPrice / 100, 2, ',', '.') }} €
                            </span>
                        @endif
                    </div>

                    {{-- TEXT & LOADING --}}
                    <div class="pl-24 pr-4 w-full flex items-center justify-center">

                        {{-- Zustand: Erfolg --}}
                        <template x-if="saved">
                            <div class="flex items-center gap-2 animate-fade-in">
                                <svg class="w-6 h-6 text-white animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Hinzugefügt!</span>
                            </div>
                        </template>

                        {{-- Zustand: Normal / Loading --}}
                        <template x-if="!saved">
                            <div class="flex items-center gap-2">

                                {{-- 1. Loading Icon (Links, nur sichtbar wenn wire:loading) --}}
                                <svg wire:loading class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>

                                {{-- 2. Text Normal (Wird ausgeblendet beim Laden) --}}
                                <span wire:loading.remove>
                                    @if($context === 'add') In den Warenkorb
                                    @elseif($context === 'edit') Speichern
                                    @elseif($context === 'calculator') Übernehmen
                                    @endif
                                </span>

                                {{-- 3. Text Loading (Wird eingeblendet beim Laden) --}}
                                <span wire:loading>
                                    Moment...
                                </span>
                            </div>
                        </template>
                    </div>
                </button>
            </div>

            {{-- SEKUNDÄR-BUTTON: Warenkorb (Erscheint nur bei Erfolg) --}}
            <div x-show="saved"
                 x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="w-full"
                 style="display: none;">

                <a href="{{ route('cart') }}"
                   class="w-full border-2 border-primary text-gray-900 py-3 rounded-xl font-bold text-base flex items-center justify-center gap-2 transition-all duration-300 hover:bg-primary hover:text-white group">
                    <span>Zum Warenkorb wechseln</span>
                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                </a>
            </div>

        </div>
    </div>
@endif

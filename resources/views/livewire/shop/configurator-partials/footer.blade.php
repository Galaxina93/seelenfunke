{{-- FOOTER --}}
@if($context !== 'preview')
    <div class="p-4 border-t border-gray-200 bg-white z-30 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] shrink-0"
         x-data="{ saved: false }"
         x-on:cart-updated.window="saved = true; setTimeout(() => saved = false, 6000)">

        <div class="max-w-2xl mx-auto flex flex-col gap-4">

            {{-- RECHTLICHER HINWEIS (VOR DEM BUTTON) --}}
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
                            Ich habe meine Texte, Logos und Positionen geprüft. Die Vorschau ist eine <strong>Visualisierung</strong>; handwerkliche Abweichungen sind möglich. Individualisierte Artikel sind vom <strong>Widerrufsrecht ausgeschlossen</strong>.
                            <a href="/agb#konfigurator" target="_blank" class="text-primary underline font-bold">AGB Details</a>.
                        </span>
                </label>
            </div>

            {{-- HAUPT-BUTTON --}}
            <button
                wire:click="save"
                wire:loading.attr="disabled"
                @disabled(!$config_confirmed)
                :class="saved ? 'bg-green-600 hover:bg-green-700' : ({{ $config_confirmed ? 'true' : 'false' }} ? 'bg-gray-900 hover:bg-black' : 'bg-gray-200 cursor-not-allowed')"
                class="w-full text-white py-4 rounded-full font-bold text-lg hover:scale-[1.01] active:scale-[0.99] transition-all duration-300 flex items-center justify-center gap-3 shadow-lg disabled:grayscale disabled:opacity-50"
            >
                {{-- Zustand: Laden --}}
                <svg wire:loading class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>

                {{-- Zustand: Erfolg --}}
                <template x-if="saved">
                    <div class="flex items-center gap-2 animate-fade-in">
                        <svg class="w-6 h-6 text-white animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Erfolgreich hinzugefügt!</span>
                    </div>
                </template>

                {{-- Zustand: Normal --}}
                <template x-if="!saved">
                        <span wire:loading.remove>
                            @if($context === 'add') In den Warenkorb
                            @elseif($context === 'edit') Änderungen speichern
                            @elseif($context === 'calculator') In Kalkulation übernehmen
                            @endif
                        </span>
                </template>

                <span wire:loading>Verarbeite...</span>
            </button>

            {{-- SEKUNDÄR-BUTTON: Warenkorb (Erscheint nur bei Erfolg) --}}
            <div x-show="saved"
                 x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="w-full"
                 style="display: none;">

                <a href="{{ route('cart') }}"
                   class="w-full border-2 border-gray-900 text-gray-900 py-3.5 rounded-full font-bold text-base flex items-center justify-center gap-2 hover:bg-gray-900 transition-all duration-300 group">
                    <span>Jetzt zum Warenkorb</span>
                    <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
@endif

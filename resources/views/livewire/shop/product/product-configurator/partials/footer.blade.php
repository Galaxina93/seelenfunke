@if($context !== 'preview')
    <div class="p-4 border-t z-30 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] shrink-0 {{ $isDark ? 'bg-gray-950 border-gray-800' : 'bg-white border-gray-200' }}" x-data="{saved: false}" x-on:cart-updated.window="saved = true; setTimeout(() => saved = false, 6000)">
        <div class="max-w-4xl mx-auto space-y-4">

            <div @class(['p-3 rounded-xl border transition-all duration-200',
                ($isDark ? 'bg-gray-900 border-gray-800' : 'bg-gray-50 border-gray-200') => !$errors->has('config_confirmed'),
                ($isDark ? 'bg-red-900/20 border-red-900/50' : 'bg-red-50 border-red-200') => $errors->has('config_confirmed')])>
                <label class="flex items-start gap-3 cursor-pointer group">
                    <input type="checkbox" wire:model.live="config_confirmed" class="mt-1 w-4 h-4 text-primary rounded focus:ring-primary {{ $isDark ? 'border-gray-700 bg-gray-950' : 'border-gray-300' }}">
                    <span class="text-[11px] leading-relaxed transition-colors {{ $isDark ? 'text-gray-400 group-hover:text-gray-200' : 'text-gray-600 group-hover:text-gray-900' }}">
                    @if($context === 'template_admin')
                            Ich bestätige, dass die Vorlage korrekt konfiguriert ist und als Basis für Kunden dient.
                        @elseif($isDigital)
                            Ich stimme ausdrücklich zu, dass mit der Ausführung des Vertrags vor Ablauf der Widerrufsfrist begonnen wird. Mir ist bekannt, dass ich durch diese Zustimmung mein Widerrufsrecht verliere.
                        @else
                            Ich habe meine Texte, Logos und Positionen geprüft. Die Vorschau ist eine <strong>Visualisierung</strong>; handwerkliche Abweichungen sind möglich. Individualisierte Artikel sind vom <strong>Widerrufsrecht ausgeschlossen</strong>.
                        @endif
                        @if($context !== 'template_admin')
                            <a href="/agb" target="_blank" class="text-primary underline font-bold ml-1">AGB Details</a>.
                        @endif
                </span>
                </label>
            </div>

            {{-- KOMPAKTERES LAYOUT: h-12 statt h-14 --}}
            <div class="flex flex-col sm:flex-row gap-3 h-auto sm:h-12">
                @if($context !== 'template_admin')
                    <div class="relative w-full sm:w-28 h-12 rounded-xl border transition-all flex items-center {{ $isDark ? 'bg-gray-900 border-gray-800 hover:border-gray-700 focus-within:border-primary focus-within:bg-gray-950' : 'bg-gray-100 border-transparent hover:border-gray-300 focus-within:border-primary focus-within:bg-white' }}">
                        <label class="absolute left-3 text-[9px] font-bold uppercase tracking-wider pointer-events-none {{ $isDark ? 'text-gray-500' : 'text-gray-500' }}">Menge</label>

                        {{-- COMPACT SELECT: text-lg statt text-xl, padding leicht reduziert --}}
                        <select wire:model.live="qty" class="appearance-none bg-transparent w-full h-full text-right font-bold text-lg focus:outline-none cursor-pointer pl-3 pr-8 pt-2.5 {{ $isDark ? 'text-white' : 'text-gray-900' }}">
                            @for($i = 1; $i <= 100; $i++)
                                {{-- Option mit text-sm für eine kompaktere Liste --}}
                                <option value="{{$i}}" class="text-sm {{ $isDark ? 'bg-gray-900 text-white' : 'bg-white text-gray-900' }}">{{$i}}</option>
                            @endfor
                        </select>

                        <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none {{ $isDark ? 'text-gray-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                @endif

                <button @click.prevent="submitConfig()" wire:loading.attr="disabled" @disabled(!$config_confirmed) :class="saved ? 'bg-green-600 hover:bg-green-700 text-white' : ({{$config_confirmed ? 'true' : 'false'}} ? '{{ $isDark ? 'bg-primary text-gray-900 hover:bg-primary-dark' : 'bg-gray-900 text-white hover:bg-black' }}' : '{{ $isDark ? 'bg-gray-800 text-gray-500 cursor-not-allowed' : 'bg-gray-200 text-gray-400 cursor-not-allowed' }}')" class="flex-1 h-12 rounded-xl font-bold text-base transition-all duration-300 flex items-center justify-center shadow-lg hover:shadow-xl disabled:shadow-none relative overflow-hidden group">
                    @if($context !== 'template_admin')
                        <div class="absolute left-0 top-0 bottom-0 bg-black/10 px-4 flex flex-col justify-center items-start border-r border-black/10 min-w-[90px]">
                            <span class="font-serif font-bold leading-none tracking-wide {{$qty > 1 ? 'text-sm' : 'text-base'}}">{{number_format($totalPrice / 100, 2, ',', '.')}} €</span>
                            @if($qty > 1)
                                <span class="text-[9px] opacity-80 font-normal leading-none mt-0.5">á {{number_format($currentPrice / 100, 2, ',', '.')}} €</span>
                            @endif
                        </div>
                        <div class="pl-20 pr-4 w-full flex items-center justify-center">
                            @else
                                <div class="w-full flex items-center justify-center">
                                    @endif
                                    <template x-if="saved">
                                        <div class="flex items-center gap-2 animate-fade-in">
                                            <svg class="w-5 h-5 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>{{$context === 'template_admin' ? 'Gespeichert!' : 'Hinzugefügt!'}}</span>
                                        </div>
                                    </template>
                                    <template x-if="!saved">
                                        <div class="flex items-center gap-2">
                                            <svg wire:loading class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span wire:loading.remove>
                                    @if($context === 'add')
                                                    In den Warenkorb
                                                @elseif($context === 'edit')
                                                    Speichern
                                                @elseif($context === 'calculator')
                                                    Übernehmen
                                                @elseif($context === 'template_admin')
                                                    Vorlage speichern
                                                @endif
                                                </span>
                                            <span wire:loading>
                                                    Moment...
                                                </span>
                                        </div>
                                    </template>
                                </div>
                        </div>
                </button>
            </div>

            @if($context !== 'template_admin' && $context !== 'calculator')
                <div x-show="saved" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="w-full pt-4" style="display: none;">
                    <a href="{{route('cart')}}" class="w-full border-2 text-sm rounded-xl font-bold flex items-center justify-center gap-2 transition-all duration-300 group py-2.5 {{ $isDark ? 'border-primary text-primary hover:bg-primary hover:text-gray-900' : 'border-primary text-gray-900 hover:bg-primary hover:text-white' }}">
                        <span>Zum Warenkorb wechseln</span>
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                </div>
            @endif
        </div>
    </div>
@endif

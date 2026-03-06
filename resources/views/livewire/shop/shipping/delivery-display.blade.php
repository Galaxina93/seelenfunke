<div class="flex items-center gap-4 mb-8 text-sm w-full flex-wrap align-top">
    @php
        $type = $product->type ?? 'physical';
        $isService = $type === 'service';
    @endphp

    {{-- Flash-Message für Feedback (Alpine) --}}
    <div x-data="{ showFeedbackMsg: false, msg: '' }"
         @feedback-sent.window="showFeedbackMsg = true; msg = $event.detail.msg; setTimeout(() => showFeedbackMsg = false, 5000)"
         x-show="showFeedbackMsg"
         x-transition.opacity
         class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-600 px-5 py-4 rounded-2xl text-sm font-bold flex items-center gap-3 shadow-sm w-full"
         style="display: none;">
        <span class="text-xl">💌</span>
        <span x-text="msg"></span>
    </div>

    {{-- Spezialmodi Banner (Urlaub / Krankheit) mit Feedback-Button --}}
    @if($type === 'physical' && $setting)
        @if($setting->is_vacation_mode && !empty($setting->vacation_description))
            <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-800 p-6 rounded-2xl shadow-sm flex flex-col w-full relative overflow-hidden group">
                <div class="flex items-start gap-4 relative z-10 w-full mb-4">
                    <span class="text-3xl leading-none pt-1 drop-shadow-sm">☀️</span>
                    <div class="flex-1">
                        <p class="leading-relaxed font-medium mb-3">{!! nl2br(e($setting->vacation_description)) !!}</p>
                        @if($setting->vacation_start_date && $setting->vacation_end_date)
                            <p class="text-xs font-bold text-amber-700 bg-amber-500/10 inline-block px-3 py-1.5 rounded-lg border border-amber-500/20 shadow-inner">
                                <span class="uppercase tracking-widest text-[9px] block text-amber-600 mb-0.5">Betriebsferien</span>
                                {{ $setting->vacation_start_date->format('d.m.Y') }} bis {{ $setting->vacation_end_date->format('d.m.Y') }}
                            </p>
                        @endif
                    </div>
                </div>
                @auth('customer')
                    <div class="w-full flex justify-end relative z-10 border-t border-amber-500/20 pt-4 mt-2">
                        @if(!$hasWishedVacation)
                            <button wire:click="sendFeedback('vacation')" class="bg-gradient-to-r from-amber-500 to-orange-500 text-white hover:from-amber-400 hover:to-orange-400 px-6 py-3 rounded-xl font-bold text-sm transition-all shadow-lg transform hover:-translate-y-0.5 active:scale-95 flex items-center gap-2">
                                <span>Schönen Urlaub wünschen! 🌴</span>
                            </button>
                        @else
                            <div class="bg-amber-500/10 border border-amber-500/30 text-amber-600 px-5 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 shadow-inner">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span>Urlaubswunsch gesendet!</span>
                            </div>
                        @endif
                    </div>
                @endauth
                <div class="absolute -right-12 -bottom-12 text-amber-500/10 rotate-12 pointer-events-none group-hover:scale-110 transition-transform duration-700">
                    <svg class="w-48 h-48" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                </div>
            </div>
        @elseif($setting->is_sick_mode && !empty($setting->sick_description))
            <div class="mb-4 bg-red-50 border border-red-200 text-red-800 p-6 rounded-2xl shadow-sm flex flex-col w-full relative overflow-hidden group">
                <div class="flex items-start gap-4 relative z-10 w-full mb-4">
                    <span class="text-3xl leading-none pt-1 drop-shadow-sm">🤒</span>
                    <div class="flex-1">
                        <p class="leading-relaxed font-medium mb-3">{!! nl2br(e($setting->sick_description)) !!}</p>
                    </div>
                </div>
                @auth('customer')
                    <div class="w-full flex justify-end relative z-10 border-t border-red-500/20 pt-4 mt-2">
                        @if(!$hasWishedSick)
                            <button wire:click="sendFeedback('sick')" class="bg-gradient-to-r from-red-500 to-rose-500 text-white hover:from-red-400 hover:to-rose-400 px-6 py-3 rounded-xl font-bold text-sm transition-all shadow-lg transform hover:-translate-y-0.5 active:scale-95 flex items-center gap-2">
                                <span>Gute Besserung wünschen! 💊</span>
                            </button>
                        @else
                            <div class="bg-red-500/10 border border-red-500/30 text-red-600 px-5 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 shadow-inner">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span>Genesungswunsch gesendet!</span>
                            </div>
                        @endif
                    </div>
                @endauth
                <div class="absolute -right-12 -bottom-12 text-red-500/5 rotate-12 pointer-events-none group-hover:scale-110 transition-transform duration-700">
                    <svg class="w-48 h-48" fill="currentColor" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
            </div>
        @endif
    @endif

    {{-- Lagerbestand --}}
    @if($product->track_quantity)
        @if($product->quantity > 0)
            <span class="inline-flex items-center gap-1.5 text-green-700 font-medium">
                <span class="relative flex h-2.5 w-2.5">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
                </span>
                {{ $isService ? 'Plätze verfügbar' : 'Auf Lager, sofort lieferbar' }}
            </span>
        @elseif($product->continue_selling_when_out_of_stock)
            <span class="inline-flex items-center gap-1.5 text-amber-600 font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ $isService ? 'Warteliste verfügbar' : 'Verfügbar auf Nachbestellung' }}
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 text-red-600 font-bold bg-red-50 px-3 py-1 rounded-full border border-red-100 animate-pulse">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                {{ $isService ? 'Derzeit ausgebucht' : 'Derzeit leider vergriffen' }}
            </span>
        @endif
    @endif

    {{-- Intelligente Lieferzeit mit Klick-für-Info Logik --}}
    @if($type === 'physical' && $activeTime)
        @php
            $timeColorClass = 'text-green-700';
            $iconColorClass = 'text-green-600';
            $infoBoxClass   = 'bg-green-50 border-green-100 text-green-800';

            if ($activeTime->color === 'yellow') {
                $timeColorClass = 'text-amber-600';
                $iconColorClass = 'text-amber-500';
                $infoBoxClass   = 'bg-amber-50 border-amber-100 text-amber-800';
            } elseif ($activeTime->color === 'red') {
                $timeColorClass = 'text-red-600';
                $iconColorClass = 'text-red-500';
                $infoBoxClass   = 'bg-red-50 border-red-100 text-red-800';
            }
        @endphp

        <div x-data="{ showInfo: false }" class="flex flex-col w-full sm:w-auto @if($product->track_quantity) sm:border-l border-gray-200 sm:pl-4 @endif">
            <div class="{{ $timeColorClass }} flex flex-wrap items-center gap-1.5 font-bold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $iconColorClass }} shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ $deliveryText }}</span>

                @if(!empty($activeTime->description))
                    <button @click="showInfo = !showInfo" class="text-[10px] text-gray-400 hover:text-gray-600 underline underline-offset-2 ml-1 font-medium transition-colors focus:outline-none">
                        <span x-text="showInfo ? '(Info ausblenden)' : '(weitere Infos)'"></span>
                    </button>
                @endif
            </div>

            @if(!empty($activeTime->description))
                <div x-show="showInfo" x-transition.opacity style="display: none;" class="mt-3 p-3.5 rounded-xl border text-xs leading-relaxed w-full max-w-md {{ $infoBoxClass }} shadow-sm">
                    {{ $activeTime->description }}
                </div>
            @endif
        </div>
    @endif

    @if($product->sku)
        <span class="text-gray-400 sm:border-l border-gray-200 sm:pl-4">Art.-Nr.: {{ $product->sku }}</span>
    @endif
</div>

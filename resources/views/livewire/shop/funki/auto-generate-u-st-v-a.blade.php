<section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-10 relative overflow-hidden transition-all duration-500 mt-6" x-data="{
    scrollAmount: 0,
    container: null,
    init() { this.container = this.$refs.sliderContainer; },
    scroll(direction) {
        const scrollVal = 360; // Breite einer Karte + Gap
        if (direction === 'left') this.container.scrollBy({ left: -scrollVal, behavior: 'smooth' });
        else this.container.scrollBy({ left: scrollVal, behavior: 'smooth' });
    }
}">
    <div class="absolute top-0 left-0 w-2 h-full bg-orange-500 transition-colors duration-500"></div>

    <div class="flex flex-col md:flex-row justify-between items-start mb-10 gap-4 relative z-10 mt-6">
        <div>
            <h3 class="text-2xl font-serif font-bold text-slate-900">Umsatzsteuer Automatisierung</h3>
            <p class="text-xs font-mono text-slate-400 mt-1 uppercase tracking-tighter mb-2">Command: funki:generate-tax-export</p>
            <div class="inline-flex items-start gap-2 bg-orange-50 p-2 rounded-lg border border-orange-100 max-w-xl">
                <x-heroicon-s-information-circle class="w-4 h-4 text-orange-500 shrink-0 mt-0.5" />
                <p class="text-[10px] text-orange-800 leading-snug">
                    <strong>Wann greift die Automation?</strong><br>
                    Ein automatischer Export durch den Cronjob macht am <strong>1. bis 10. des Folgemonats</strong> Sinn. Zu diesem Zeitpunkt sind alle Transaktionen des Vormonats abgeschlossen und die gesetzliche Meldefrist (10. des Monats) wird sicher eingehalten.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <select wire:model.live="selectedYear" class="bg-white border border-slate-200 text-slate-600 hover:text-slate-900 px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm transition-all outline-none focus:ring-2 focus:ring-orange-500/20 cursor-pointer">
                @for($y = date('Y') - 2; $y <= date('Y'); $y++)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>

            <div class="relative" x-data="{ openVault: false }">
                <button @click="openVault = !openVault" class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all bg-slate-50 text-slate-500 hover:bg-slate-100 border border-slate-200 shadow-sm">
                    <x-heroicon-s-archive-box class="w-4 h-4" />
                    Datensicherung
                </button>

                <div x-show="openVault" @click.away="openVault = false" x-cloak class="absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-xl border border-slate-100 p-2 z-50 animate-fade-in-up">
                    <div class="px-4 py-3 border-b border-slate-50 mb-2">
                        <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest">Archivierte Exporte</h4>
                    </div>
                    <div class="max-h-64 overflow-y-auto custom-scrollbar px-2 space-y-2">
                        @forelse($archivedExports as $export)
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl hover:bg-orange-50/50 transition-colors group border border-transparent hover:border-orange-100">
                                <div class="min-w-0 flex-1">
                                    <h5 class="text-xs font-bold text-slate-700 truncate">{{ $export['name'] }}</h5>
                                    <p class="text-[9px] text-slate-400 font-mono mt-0.5">{{ $export['date'] }} • {{ $export['size'] }}</p>
                                </div>
                                <div class="flex gap-1 ml-3">
                                    <button wire:click="downloadExport('{{ $export['name'] }}')" class="p-1.5 text-slate-400 hover:text-orange-500 transition-colors" title="Herunterladen">
                                        <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                    </button>
                                    <button wire:click="deleteExport('{{ $export['name'] }}')" wire:confirm="Endgültig löschen?" class="p-1.5 text-slate-400 hover:text-red-500 transition-colors" title="Löschen">
                                        <x-heroicon-m-trash class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        @empty
                            <p class="text-center text-xs text-slate-400 py-6 italic">Keine Exporte vorhanden.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session()->has('success'))
        <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 text-emerald-700 shadow-sm rounded-r-xl flex items-center gap-3 animate-fade-in text-sm font-bold">
            <x-heroicon-s-check-circle class="w-5 h-5" />
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 text-red-700 shadow-sm rounded-r-xl flex items-center gap-3 animate-fade-in text-sm font-bold">
            <x-heroicon-s-exclamation-circle class="w-5 h-5" />
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="relative group/slider">
        <button @click.stop="scroll('left')" class="absolute left-0 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-white/90 backdrop-blur border border-slate-200 rounded-full shadow-md flex items-center justify-center text-slate-600 hover:text-orange-500 hover:border-orange-500 transition-all opacity-0 group-hover/slider:opacity-100 translate-x-[-10px] group-hover/slider:translate-x-0 duration-300">
            <x-heroicon-m-chevron-left class="w-6 h-6" />
        </button>
        <button @click.stop="scroll('right')" class="absolute right-0 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-white/90 backdrop-blur border border-slate-200 rounded-full shadow-md flex items-center justify-center text-slate-600 hover:text-orange-500 hover:border-orange-500 transition-all opacity-0 group-hover/slider:opacity-100 translate-x-[10px] group-hover/slider:translate-x-0 duration-300">
            <x-heroicon-m-chevron-right class="w-6 h-6" />
        </button>

        <div x-ref="sliderContainer" class="flex gap-6 overflow-x-auto pb-6 pt-2 custom-scrollbar snap-x scroll-smooth relative z-10">
            @foreach($monthsData as $month => $data)
                @php
                    $isReady = $data['status'] === 'ready';
                    $isMissing = $data['status'] === 'missing_receipts';
                    $isInProgress = $data['status'] === 'in_progress';
                    $isFuture = $data['status'] === 'future';

                    // Border/Glow Logik
                    $cardClasses = 'w-[340px] shrink-0 p-6 rounded-3xl border transition-all duration-300 relative flex flex-col min-h-[350px] snap-start bg-white ';

                    if($isMissing) {
                        $cardClasses .= 'border-red-200 bg-red-50/10';
                    } elseif($isReady) {
                        $cardClasses .= 'border-slate-200 hover:border-orange-300 shadow-lg';
                    } else {
                        $cardClasses .= 'border-slate-100 opacity-60';
                    }

                    // Deadline Prüfung
                    $isDeadlinePassed = now()->gt($data['deadline']) && !$isReady;
                @endphp

                <div class="{{ $cardClasses }}">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            @if($isMissing)
                                <div class="bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-black text-xs shadow-sm">!</div>
                            @elseif($isReady)
                                <div class="bg-emerald-500 text-white rounded-full w-5 h-5 flex items-center justify-center font-black text-xs shadow-sm">✓</div>
                            @elseif($isFuture)
                                <div class="bg-slate-200 text-slate-400 rounded-full w-5 h-5 flex items-center justify-center shadow-sm">
                                    <x-heroicon-m-forward class="w-3 h-3"/>
                                </div>
                            @else
                                <div class="bg-slate-300 text-white rounded-full w-5 h-5 flex items-center justify-center shadow-sm">
                                    <x-heroicon-s-clock class="w-3 h-3"/>
                                </div>
                            @endif
                            <span class="text-xs font-black text-slate-400 tracking-wider">{{ $data['month_number'] }}/{{ $data['year'] }}</span>
                        </div>

                        <div class="text-[9px] font-bold uppercase px-2 py-0.5 rounded border {{ $isDeadlinePassed ? 'bg-red-100 text-red-600 border-red-200 animate-pulse' : 'bg-slate-50 text-slate-400 border-slate-200' }}">
                            Frist: {{ $data['deadline']->format('d.m.Y') }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mb-8">
                        <h4 class="text-2xl font-bold text-slate-900">{{ $data['month_name'] }} {{ $data['year'] }}</h4>

                        <div x-data="{ showScore: false }" class="relative flex items-center mt-1">
                            <button @mouseenter="showScore = true" @mouseleave="showScore = false" class="text-slate-300 hover:text-orange-500 transition-colors focus:outline-none">
                                <x-heroicon-s-information-circle class="w-5 h-5" />
                            </button>
                            <div x-show="showScore" x-cloak x-transition class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-60 p-4 bg-slate-900 text-white rounded-xl shadow-2xl z-50 text-xs cursor-default">
                                <h5 class="text-[10px] font-black uppercase tracking-widest text-orange-400 mb-2 border-b border-white/10 pb-1">Quality Score</h5>
                                <div class="space-y-1.5">
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Steuerpfl. Umsatz:</span>
                                        <span class="font-mono font-bold">{{ number_format($data['revenue_net'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Umsatzsteuer:</span>
                                        <span class="font-mono text-emerald-400 font-bold">+{{ number_format($data['vat_collected'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Vorsteuer:</span>
                                        <span class="font-mono text-red-400 font-bold">-{{ number_format($data['vat_paid'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between border-t border-white/10 pt-1 mt-1 font-bold">
                                        <span>Zahllast:</span>
                                        <span>{{ number_format($data['zahllast'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between text-[9px] text-slate-500 mt-2 italic">
                                        <span>Abweichung zur Erklärung:</span>
                                        <span>0,00 €</span>
                                    </div>
                                </div>
                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-900"></div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 mb-8">
                        <div class="flex justify-between text-sm text-slate-600 font-medium">
                            <span>Umsatzsteuer:</span>
                            <span>+{{ number_format($data['vat_collected'], 2, ',', '.') }} €</span>
                        </div>
                        <div class="flex justify-between text-sm text-slate-600 font-medium">
                            <span>Vorsteuer:</span>
                            <span>-{{ number_format($data['vat_paid'], 2, ',', '.') }} €</span>
                        </div>
                        <div class="flex justify-between font-bold text-lg pt-3 border-t border-slate-100 mt-2">
                            <span class="text-slate-900">Zahllast:</span>
                            <span class="{{ $data['zahllast'] > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($data['zahllast'], 2, ',', '.') }} €</span>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div class="flex justify-between text-[10px] font-black uppercase tracking-widest mb-2 {{ $isFuture ? 'text-slate-400' : 'text-orange-500' }}">
                            <span>Readiness</span>
                            <span>{{ $data['progress'] }}%</span>
                        </div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mb-3 overflow-hidden">
                            <div class="bg-orange-500 h-full rounded-full transition-all duration-1000" style="width: {{ $data['progress'] }}%"></div>
                        </div>

                        <div class="h-6">
                            @if($isMissing)
                                <div class="text-[10px] font-black text-red-500 flex items-center gap-1 uppercase tracking-wide">
                                    <x-heroicon-s-exclamation-triangle class="w-3.5 h-3.5" />
                                    {{ $data['missing_receipts'] }} Beleg(e) bei Ausgaben fehlen!
                                </div>
                            @elseif($isFuture)
                                <div class="text-[10px] font-black text-slate-400 flex items-center gap-1 uppercase tracking-wide">
                                    Liegt in der Zukunft
                                </div>
                            @endif
                        </div>

                        <div class="flex gap-3 mt-6">
                            <button wire:click="generateDatevExport({{ $month }})" wire:loading.attr="disabled" @disabled($isFuture) class="flex-1 py-3 border rounded-xl text-xs font-black uppercase tracking-widest transition-colors shadow-sm flex items-center justify-center gap-1.5 {{ $isFuture ? 'bg-slate-50 border-slate-200 text-slate-300 cursor-not-allowed' : 'bg-white border-slate-200 text-slate-600 hover:text-orange-500 hover:border-orange-200' }}">
                                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                <span wire:loading.remove wire:target="generateDatevExport({{ $month }})">DATEV EXPORT</span>
                                <span wire:loading wire:target="generateDatevExport({{ $month }})">LÄDT...</span>
                            </button>

                            <button wire:click="transmitToElster({{ $month }})" wire:loading.attr="disabled" @disabled(!$isReady) class="flex-1 py-3 border border-transparent rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-sm flex items-center justify-center gap-1.5 {{ $isReady ? 'bg-orange-50 text-orange-600 hover:bg-orange-500 hover:text-white' : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed' }}">
                                <x-heroicon-o-paper-airplane class="w-4 h-4" />
                                <span wire:loading.remove wire:target="transmitToElster({{ $month }})">AN ELSTER</span>
                                <span wire:loading wire:target="transmitToElster({{ $month }})">SENDE...</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

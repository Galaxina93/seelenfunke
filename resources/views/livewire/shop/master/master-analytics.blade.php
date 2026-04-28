<div class="p-4 md:p-6 bg-transparent min-h-screen space-y-8 relative z-10">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @include('livewire.shop.master.master-analytics-partials.header')

    @include('livewire.shop.master.master-analytics-partials.master_scores')

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- AKTIV ROUTINE -->
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border border-gray-800 flex flex-col relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <x-heroicon-s-arrow-path class="w-24 h-24 text-white" />
            </div>
            <div class="flex items-center justify-between mb-6 relative z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center border border-primary/50">
                        <x-heroicon-s-clock class="w-5 h-5 text-primary" />
                    </div>
                    <div>
                        <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em]">Live Routine</h3>
                        <p class="text-sm font-bold text-white tracking-widest">Tagesablauf</p>
                    </div>
                </div>
                <a href="{{ route('admin.routine') }}" class="text-xs font-bold text-gray-400 hover:text-white transition-colors flex items-center gap-1">Alle ansehen <x-heroicon-s-chevron-right class="w-3 h-3" /></a>
            </div>

            @if($currentActiveRoutine)
               @php
                   $startT = \Carbon\Carbon::parse($currentActiveRoutine->start_time)->format('H:i');
                   $endT = \Carbon\Carbon::parse($currentActiveRoutine->start_time)->addMinutes($currentActiveRoutine->duration_minutes)->format('H:i');
               @endphp
               <div class="flex-1 flex flex-col justify-center">
                   <div class="flex items-center justify-between mb-2">
                       <h4 class="text-xl font-black text-white truncate pr-2">{{ $currentActiveRoutine->title }}</h4>
                       <span class="bg-emerald-500/20 text-emerald-400 border border-emerald-500/50 px-3 py-1 rounded-full text-xs font-bold shadow-[0_0_8px_rgba(16,185,129,0.5)]">Aktiv</span>
                   </div>
                   <div class="text-sm font-bold text-primary mb-4">{{ $startT }} - {{ $endT }} Uhr ({{ $currentActiveRoutine->duration_minutes }} Min)</div>
                   <p class="text-sm text-gray-400 leading-relaxed max-h-20 overflow-y-auto custom-scrollbar">{{ $currentActiveRoutine->message ?: 'Keine weiteren Details hinterlegt.' }}</p>
               </div>
            @else
               <div class="flex-1 flex flex-col items-center justify-center text-center py-4">
                   <x-heroicon-o-moon class="w-12 h-12 text-gray-700 mb-3" />
                   <h4 class="text-lg font-bold text-gray-500">Keine aktive Routine</h4>
                   <p class="text-xs text-gray-600 mt-1">Aktuell läuft kein geplanter Block.</p>
               </div>
            @endif
        </div>

        <!-- ANSTEHENDE TERMINE -->
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border border-gray-800 flex flex-col relative overflow-hidden group">
            <div class="flex items-center justify-between mb-6 relative z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center border border-blue-500/50">
                        <x-heroicon-s-calendar class="w-5 h-5 text-blue-400" />
                    </div>
                    <div>
                        <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em]">Kalender</h3>
                        <p class="text-sm font-bold text-white tracking-widest">Nächste Termine</p>
                    </div>
                </div>
                <a href="{{ route('admin.calender') }}" class="text-xs font-bold text-gray-400 hover:text-white transition-colors flex items-center gap-1">Alle ansehen <x-heroicon-s-chevron-right class="w-3 h-3" /></a>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-3 max-h-[180px]">
                @forelse($upcomingEvents as $event)
                    @php
                        $isToday = $event->start_date->isToday();
                        $isTomorrow = $event->start_date->isTomorrow();
                        $dateLabel = $isToday ? 'Heute' : ($isTomorrow ? 'Morgen' : $event->start_date->format('d.m.'));
                        $timeStr = $event->is_all_day ? 'Ganztägig' : $event->start_date->format('H:i');
                        $colorClass = $event->priority === 'high' ? 'text-red-400 bg-red-500/10 border-red-500/20' : ($event->priority === 'medium' ? 'text-amber-400 bg-amber-500/10 border-amber-500/20' : 'text-blue-400 bg-blue-500/10 border-blue-500/20');
                    @endphp
                    <div class="bg-gray-950 rounded-xl p-3 border border-gray-800 flex items-start gap-4 hover:border-gray-700 transition-colors">
                        <div class="flex flex-col items-center justify-center shrink-0 w-14 h-14 rounded-lg border {{ $colorClass }}">
                            <span class="text-[10px] font-bold uppercase">{{ $dateLabel }}</span>
                            <span class="{{ $event->is_all_day ? 'text-[8px] sm:text-[9px] tracking-tight uppercase mt-0.5' : 'text-[13px]' }} font-black">{{ $timeStr }}</span>
                        </div>
                        <div class="flex-1 min-w-0 py-0.5">
                            <h4 class="text-sm font-bold text-gray-200 truncate">{{ $event->title }}</h4>
                            <p class="text-xs text-gray-500 truncate mt-1">{{ $event->description ?: 'Keine Beschreibung' }}</p>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center h-full text-center py-6">
                        <x-heroicon-o-calendar-days class="w-10 h-10 text-gray-700 mb-2" />
                        <p class="text-sm font-bold text-gray-500">Keine anstehenden Termine</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ABANDONED CARTS MODAL --}}

    @if($showAbandonedCarts)
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flexitems-center justify-center p-4 xl:p-10 flex items-center" x-data @keydown.escape.window="$wire.set('showAbandonedCarts', false)">
            <div class="bg-gray-900 border border-gray-700 rounded-3xl w-full max-w-5xl max-h-full overflow-hidden relative shadow-[0_0_50px_rgba(245,158,11,0.1)] flex flex-col">
                <div class="p-6 border-b border-gray-800 flex justify-between items-center bg-gray-950/80">
                    <div>
                        <h3 class="text-white font-bold font-serif text-2xl flex items-center gap-3">
                            <x-heroicon-o-shopping-cart class="w-8 h-8 text-amber-500" />
                            Verlassene Körbe Detailansicht
                        </h3>
                        <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mt-1">Umsatzpotenzial und liegengebliebene Warenkörbe</p>
                    </div>
                    <button wire:click="$set('showAbandonedCarts', false)" class="text-gray-500 bg-gray-800/50 p-2 rounded-full hover:bg-gray-700 hover:text-white transition-colors">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>
                <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
                    @if(isset($stats['abandoned_carts']['details']) && count($stats['abandoned_carts']['details']) > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-gray-800">
                                        <th class="p-3 text-[10px] uppercase font-black text-gray-500 tracking-widest">Status / Alter</th>
                                        <th class="p-3 text-[10px] uppercase font-black text-gray-500 tracking-widest">Warenkorb ID</th>
                                        <th class="p-3 text-[10px] uppercase font-black text-gray-500 tracking-widest">Benutzer / Gast</th>
                                        <th class="p-3 text-[10px] uppercase font-black text-gray-500 tracking-widest text-right">Potenzial</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stats['abandoned_carts']['details'] as $cart)
                                        <tr class="border-b border-gray-800/50 hover:bg-gray-800/20 transition-colors">
                                            <td class="p-3 align-middle">
                                                <div class="flex items-center gap-2">
                                                    @if($cart['status'] === 'green')
                                                        <span class="w-3 h-3 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]" title="Kürzlich verlassen"></span>
                                                    @elseif($cart['status'] === 'yellow')
                                                        <span class="w-3 h-3 rounded-full bg-yellow-500 shadow-[0_0_8px_rgba(234,179,8,0.8)]" title="Länger verlassen"></span>
                                                    @else
                                                        <span class="w-3 h-3 rounded-full bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.8)]" title="Sehr alt"></span>
                                                    @endif
                                                    <span class="text-xs font-bold text-gray-400">{{ $cart['age'] }}</span>
                                                </div>
                                            </td>
                                            <td class="p-3 align-middle text-xs font-mono text-gray-500">{{ substr($cart['id'], 0, 8) }}...</td>
                                            <td class="p-3 align-middle">
                                                @if($cart['customer'])
                                                    <div class="font-bold text-sm text-white">{{ $cart['customer'] }}</div>
                                                    @if($cart['email'])
                                                        <div class="text-[10px] text-gray-500">{{ $cart['email'] }}</div>
                                                    @endif
                                                @else
                                                    <span class="text-xs font-black text-gray-500 bg-gray-800 px-2 py-1 rounded">Gast</span>
                                                @endif
                                            </td>
                                            <td class="p-3 align-middle text-right font-black text-amber-500 group relative">
                                                {{ number_format($cart['total'], 2, ',', '.') }} €
                                                <div class="text-[9px] text-gray-500">{{ $cart['items_count'] }} Artikel</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="py-12 flex flex-col items-center justify-center text-gray-500">
                            <x-heroicon-o-shopping-bag class="w-12 h-12 mb-4 opacity-50" />
                            <p class="font-bold text-sm">Keine verlassenen Warenkörbe gefunden.</p>
                            <p class="text-xs mt-1">Im gewählten Zeitraum gab es keine Abbrüche.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @include('livewire.shop.master.master-analytics-partials.scripts')

</div>

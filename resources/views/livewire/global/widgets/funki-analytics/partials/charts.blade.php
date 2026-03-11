<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

    {{-- SITZUNGS-HISTORIE (Ehemals Gewinn Chart Position) --}}
    <div class="lg:col-span-2 bg-gray-900/80 backdrop-blur-md rounded-[2rem] shadow-2xl border border-gray-800 overflow-hidden flex flex-col h-full group w-full">

        {{-- Header inkl. Suchfeld --}}
        <div class="p-6 border-b border-gray-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-gray-900/50">
            <div class="flex items-center justify-between w-full sm:w-auto gap-4">
                <h3 class="text-xs sm:text-sm font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                    <i class="solar-user-check-bold-duotone text-primary text-lg"></i>
                    Sitzungs-Historie
                </h3>
                <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest bg-gray-950 px-2 py-1 rounded border border-gray-800 sm:hidden">Live</span>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="relative w-full sm:w-48">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                        <i class="bi bi-search text-gray-500 text-xs"></i>
                    </div>
                    <input type="text" wire:model.live="searchLogins" placeholder="Suchen..." class="bg-gray-950 border border-gray-800 text-gray-300 text-xs rounded-lg focus:ring-primary focus:border-primary block w-full pl-8 p-1.5 transition-colors shadow-inner">
                </div>
                <span class="hidden sm:inline-block text-[9px] font-bold text-gray-500 uppercase tracking-widest bg-gray-950 px-2 py-1 rounded border border-gray-800">Live</span>
            </div>
        </div>

        {{-- Fester Scrollbereich für die Tabelle --}}
        <div class="overflow-x-auto overflow-y-auto max-h-[350px] custom-scrollbar flex-1 w-full">
            <table class="w-full text-xs text-left border-separate border-spacing-0">
                <thead class="sticky top-0 z-10">
                <tr class="text-[8px] font-black text-gray-500 uppercase tracking-widest bg-gray-950">
                    <th class="px-6 py-3 border-b border-gray-800">Nutzer</th>
                    <th class="px-6 py-3 border-b border-gray-800">Rolle</th>
                    <th class="px-6 py-3 text-right border-b border-gray-800">Aktivität</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                @forelse($activeLogins as $login)
                    <tr class="hover:bg-gray-800/40 transition-colors group/row">
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-gray-950 flex items-center justify-center text-gray-400 font-bold text-[11px] border border-gray-800 shrink-0 group-hover/row:border-primary/50 transition-colors">{{ substr($login['first_name'] ?? 'U', 0, 1) }}</div>
                                <span class="font-bold text-gray-300 tracking-wide truncate max-w-[120px]">{{ $login['first_name'] }} {{ substr($login['last_name'] ?? '', 0, 1) }}.</span>
                            </div>
                        </td>
                        <td class="px-6 py-3">
                                <span @class([
                                    'px-2 py-1 rounded text-[9px] font-black uppercase tracking-widest border whitespace-nowrap',
                                    'bg-purple-500/10 text-purple-400 border-purple-500/30' => $login['type'] === 'Admin',
                                    'bg-primary/10 text-primary border-primary/30' => $login['type'] === 'Customer',
                                    'bg-blue-500/10 text-blue-400 border-blue-500/30' => $login['type'] === 'Employee'
                                ])>{{ $login['type'] }}</span>
                        </td>
                        <td class="px-6 py-3 text-right">
                            <div class="flex flex-col items-end">
                                <span class="text-[11px] font-bold text-gray-400 whitespace-nowrap">{{ \Carbon\Carbon::parse($login['last_seen'])->diffForHumans(null, true, true) }}</span>
                                <span class="text-[9px] text-gray-600 font-black tracking-widest whitespace-nowrap mt-0.5">{{ \Carbon\Carbon::parse($login['last_seen'])->format('H:i') }}</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-10 text-center text-gray-500 italic text-sm">Keine passenden Sitzungen gefunden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- AUSGABEN DOUGHNUT --}}
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border border-gray-800 relative group" wire:ignore>
        <h3 class="text-xs sm:text-sm font-black text-gray-400 uppercase tracking-widest mb-4 text-center">Top Kostentreiber</h3>
        <div class="h-48 relative">
            <canvas id="expensesChart"></canvas>
        </div>
    </div>

    {{-- KUNDEN DOUGHNUT --}}
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border border-gray-800 relative group" wire:ignore>
        <h3 class="text-xs sm:text-sm font-black text-gray-400 uppercase tracking-widest mb-4 text-center">Top Kunden</h3>
        <div class="h-48 relative">
            <canvas id="customersChart"></canvas>
        </div>
    </div>

    {{-- KOMPAKTES PODEST: MEISTVERKAUFTE PRODUKTE --}}
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border border-gray-800 relative overflow-hidden">
        <h3 class="text-xs sm:text-sm font-black text-gray-400 uppercase tracking-widest mb-5 flex items-center gap-2">
            <i class="solar-info-circle-bold-duotone text-primary"></i> Meistverkaufte Unikate
        </h3>
        <div class="space-y-3 relative z-10">
            @if(isset($stats['product_ranking']) && count($stats['product_ranking']) > 0)
                @php $ranks = array_values($stats['product_ranking']); @endphp
                @foreach(array_slice($ranks, 0, 3) as $index => $rank)
                    @php
                        $isFirst = $index === 0;
                        $bg = $isFirst ? 'bg-primary/10 border-primary/30' : 'bg-gray-950 border-gray-800';
                        $textColor = $isFirst ? 'text-primary' : 'text-gray-300';
                    @endphp
                    <div class="flex items-center justify-between p-3 rounded-xl border shadow-inner transition-all hover:bg-gray-800/50 {{ $bg }}">
                        <div class="flex items-center gap-3 min-w-0 pr-4">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-lg bg-gray-900 shadow-inner shrink-0 border border-gray-700">
                                {{ $isFirst ? '🥇' : ($index === 1 ? '🥈' : '🥉') }}
                            </div>
                            <span class="font-bold text-sm truncate {{ $textColor }}">{{ $rank['product_name'] ?? 'Produkt' }}</span>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-lg font-black text-white">{{ $rank['qty'] ?? 0 }}</span>
                            <span class="text-[9px] text-gray-500 uppercase tracking-widest ml-1">Stk.</span>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-gray-500 italic text-sm text-center py-6">Keine Verkäufe.</div>
            @endif
        </div>
    </div>

    {{-- NEU: VERLASSENE WARENKÖRBE --}}
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border border-gray-800 relative group overflow-hidden flex flex-col justify-center text-center transition-all hover:border-amber-500/30">
        <div class="absolute -top-10 -right-10 w-32 h-32 bg-amber-500/10 rounded-full blur-[40px] opacity-40 group-hover:opacity-70 transition-opacity duration-700"></div>

        <h3 class="text-xs sm:text-sm font-black text-gray-400 uppercase tracking-widest mb-4">Verlassene Warenkörbe</h3>

        <div class="text-5xl font-black text-amber-400 drop-shadow-[0_0_15px_rgba(245,158,11,0.4)] mb-3">
            {{ $stats['abandoned_carts']['count'] ?? 0 }}
        </div>

        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">Potenzieller Umsatz</div>
        <div class="text-xl font-bold text-gray-300">{{ number_format($stats['abandoned_carts']['potential_revenue'] ?? 0, 2, ',', '.') }} €</div>
    </div>

    {{-- KÖNIG & TIEF KACHEL (Kompakt nebeneinander) --}}
    <div class="lg:col-span-2 grid grid-cols-2 gap-4">

        {{-- Umsatz-König --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border border-gray-800 relative overflow-hidden flex flex-col justify-center text-center group transition-all hover:border-primary/40">
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-primary/20 rounded-full blur-[40px] opacity-40 group-hover:opacity-70 transition-opacity duration-700"></div>

            <div class="flex items-center justify-center gap-2 mb-4">
                <span class="bg-primary text-gray-900 text-[8px] font-black px-3 py-1 rounded-full shadow-[0_0_15px_rgba(197,160,89,0.5)] animate-pulse tracking-widest">CHAMPION</span>
            </div>

            @if(isset($stats['high_revenue_prod']) && $stats['high_revenue_prod'])
                <div class="text-sm font-bold text-gray-300 truncate mb-2" title="{{ $stats['high_revenue_prod']['product_name'] ?? 'Unbekannt' }}">
                    {{ $stats['high_revenue_prod']['product_name'] ?? 'Unbekannt' }}
                </div>
                <div class="text-2xl lg:text-3xl font-black text-primary tracking-tighter drop-shadow-[0_0_10px_rgba(197,160,89,0.4)]">
                    {{ number_format($stats['high_revenue_prod']['total'] ?? 0, 2, ',', '.') }} €
                </div>
            @else
                <div class="text-gray-500 italic text-xs py-4">Keine Daten</div>
            @endif
        </div>

        {{-- Performance Tief --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border border-gray-800 relative overflow-hidden flex flex-col justify-center text-center group transition-all hover:border-red-500/30">
            <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-red-500/10 rounded-full blur-[40px] opacity-40 group-hover:opacity-70 transition-opacity duration-700"></div>

            <div class="flex items-center justify-center gap-2 mb-4">
                <span class="bg-red-500/10 text-red-400 border border-red-500/30 text-[8px] font-black px-3 py-1 rounded-full tracking-widest uppercase">Schlusslicht</span>
            </div>

            @if(isset($stats['low_revenue_prod']) && $stats['low_revenue_prod'])
                <div class="text-sm font-bold text-gray-400 truncate mb-2" title="{{ $stats['low_revenue_prod']['product_name'] ?? 'Unbekannt' }}">
                    {{ $stats['low_revenue_prod']['product_name'] ?? 'Unbekannt' }}
                </div>
                <div class="text-2xl lg:text-3xl font-black text-red-400 tracking-tight drop-shadow-[0_0_10px_rgba(248,113,113,0.3)]">
                    {{ number_format($stats['low_revenue_prod']['total'] ?? 0, 2, ',', '.') }} €
                </div>
            @else
                <div class="text-gray-500 italic text-xs py-4">Keine Daten</div>
            @endif
        </div>

    </div>

</div>

<div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] p-6 lg:p-8 shadow-2xl border border-gray-800 relative w-full mt-6">
    <div class="flex items-center gap-3 mb-8 border-b border-gray-800 pb-4">
        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary border border-primary/20 shadow-[0_0_15px_rgba(197,160,89,0.2)]">
            <x-heroicon-o-chart-pie class="w-6 h-6" />
        </div>
        <div>
            <h2 class="text-xl font-serif font-bold text-white tracking-tight">E-Commerce Einblicke</h2>
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mt-1">Smarte Übersicht der wichtigsten KPIs</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 divide-y lg:divide-y-0 lg:divide-x divide-gray-800">
        
        {{-- BEREICH 1: PRODUKT-PERFORMANCE --}}
        <div class="flex flex-col gap-6 lg:pr-8">
            <div>
                <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <x-heroicon-o-star class="w-4 h-4 text-primary" /> Meistverkaufte Unikate
                </h3>
                <div class="space-y-2">
                    @if(isset($stats['product_ranking']) && count($stats['product_ranking']) > 0)
                        @php $ranks = array_values($stats['product_ranking']); @endphp
                        @foreach(array_slice($ranks, 0, 3) as $index => $rank)
                            <div class="flex items-center justify-between p-2 rounded-lg bg-gray-950/50 border border-gray-800/50">
                                <div class="flex items-center gap-2 min-w-0 pr-2">
                                    <span class="text-sm shrink-0">{{ $index === 0 ? '🥇' : ($index === 1 ? '🥈' : '🥉') }}</span>
                                    <span class="font-semibold text-xs truncate {{ $index === 0 ? 'text-primary' : 'text-gray-300' }}">{{ $rank['product_name'] ?? 'Produkt' }}</span>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-sm font-black text-white">{{ $rank['qty'] ?? 0 }}</span>
                                    <span class="text-[8px] text-gray-500 uppercase tracking-widest ml-0.5">Stk.</span>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-gray-500 italic text-xs py-2">Keine Verkäufe.</div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 mt-auto">
                <div class="bg-primary/5 rounded-xl p-3 border border-primary/20 flex flex-col justify-center text-center">
                    <span class="text-[8px] font-black px-2 py-0.5 rounded-full uppercase tracking-widest text-primary mb-2 mx-auto">CHAMPION</span>
                    <div class="text-[10px] font-bold text-gray-400 truncate mb-1" title="{{ $stats['high_revenue_prod']['product_name'] ?? 'Unbekannt' }}">
                        {{ $stats['high_revenue_prod']['product_name'] ?? 'Unbekannt' }}
                    </div>
                    <div class="text-sm font-black text-primary drop-shadow-[0_0_8px_rgba(197,160,89,0.5)]">
                        {{ number_format($stats['high_revenue_prod']['total'] ?? 0, 2, ',', '.') }} €
                    </div>
                </div>
                <div class="bg-red-500/5 rounded-xl p-3 border border-red-500/20 flex flex-col justify-center text-center">
                    <span class="text-[8px] font-black px-2 py-0.5 rounded-full uppercase tracking-widest text-red-400 mb-2 mx-auto">Schlusslicht</span>
                    <div class="text-[10px] font-bold text-gray-400 truncate mb-1" title="{{ $stats['low_revenue_prod']['product_name'] ?? 'Unbekannt' }}">
                        {{ $stats['low_revenue_prod']['product_name'] ?? 'Unbekannt' }}
                    </div>
                    <div class="text-sm font-black text-red-400 drop-shadow-[0_0_8px_rgba(248,113,113,0.5)]">
                        {{ number_format($stats['low_revenue_prod']['total'] ?? 0, 2, ',', '.') }} €
                    </div>
                </div>
            </div>

            <div class="bg-amber-500/5 rounded-xl p-4 border border-amber-500/20 flex items-center justify-between mt-2">
                <div>
                    <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2 mb-1">
                        <x-heroicon-o-shopping-cart class="w-4 h-4 text-amber-500" /> Verlassene Körbe
                    </h3>
                    <div class="text-xl font-black text-amber-500 drop-shadow-[0_0_8px_rgba(245,158,11,0.5)]">{{ $stats['abandoned_carts']['count'] ?? 0 }}</div>
                </div>
                <div class="text-right">
                    <div class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-1">Potenzieller Umsatz</div>
                    <div class="text-lg font-bold text-gray-300">{{ number_format($stats['abandoned_carts']['potential_revenue'] ?? 0, 2, ',', '.') }} €</div>
                </div>
            </div>
        </div>

        {{-- BEREICH 2: KUNDEN --}}
        <div class="flex flex-col pt-6 lg:pt-0 lg:px-8">
            <div class="w-full flex-1 flex flex-col" wire:ignore>
                <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <x-heroicon-o-users class="w-4 h-4 text-fuchsia-500" /> Top Kunden Segmentierung
                </h3>
                <div class="h-48 relative w-full flex justify-center mt-auto mb-auto">
                    <canvas id="customersChart"></canvas>
                </div>
            </div>
        </div>

        {{-- BEREICH 3: KOSTEN-TREIBER --}}
        <div class="flex flex-col pt-6 lg:pt-0 lg:pl-8">
            <div class="w-full flex-1 flex flex-col" wire:ignore>
                <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <x-heroicon-o-chart-pie class="w-4 h-4 text-blue-500" /> Top Kostentreiber
                </h3>
                <div class="h-48 relative w-full flex justify-center mt-auto mb-auto">
                    <canvas id="expensesChart"></canvas>
                </div>
            </div>
        </div>

    </div>
</div>

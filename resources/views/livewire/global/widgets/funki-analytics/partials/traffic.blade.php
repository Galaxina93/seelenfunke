<div class="bg-gray-900/80 backdrop-blur-md rounded-[1.5rem] md:rounded-[2.5rem] shadow-2xl border border-gray-800 p-5 md:p-8 xl:col-span-3 flex flex-col w-full overflow-hidden relative group">
    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 via-cyan-400 to-transparent opacity-50"></div>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h2 class="text-xl md:text-2xl font-serif font-bold text-white flex items-center gap-3">
                <i class="solar-chart-2-bold-duotone text-blue-400 drop-shadow-[0_0_10px_rgba(96,165,250,0.5)]"></i>
                Traffic & Analytics
            </h2>
            <p class="text-[10px] md:text-xs font-black uppercase tracking-widest text-gray-500 mt-2">Nutzerverhalten im gewählten Zeitraum</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <div class="bg-gray-950 p-3 md:p-4 rounded-xl md:rounded-2xl border border-gray-800 shadow-inner flex flex-col items-center min-w-[100px]">
                <span class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Seitenaufrufe</span>
                <span class="text-xl md:text-2xl font-black text-white">{{ number_format($stats['frontend_visits_total'] ?? 0, 0, ',', '.') }}</span>
            </div>
            <div class="bg-gray-950 p-3 md:p-4 rounded-xl md:rounded-2xl border border-gray-800 shadow-inner flex flex-col items-center min-w-[100px]">
                <span class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Besucher</span>
                <span class="text-xl md:text-2xl font-black text-blue-400 drop-shadow-[0_0_10px_rgba(96,165,250,0.4)]">{{ number_format($stats['frontend_unique_total'] ?? 0, 0, ',', '.') }}</span>
            </div>

            @php
                $totalDevice = max(1, ($stats['desktop_visits'] ?? 0) + ($stats['mobile_visits'] ?? 0));
                $mobilePct = round((($stats['mobile_visits'] ?? 0) / $totalDevice) * 100);
            @endphp
            <div class="bg-gray-950 p-3 md:p-4 rounded-xl md:rounded-2xl border border-gray-800 shadow-inner flex flex-col items-center min-w-[100px]" title="Desktop vs. Mobile">
                <span class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Mobile Rate</span>
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    <span class="text-xl md:text-2xl font-black text-white">{{ $mobilePct }}%</span>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full mb-8">
        <div class="flex items-center gap-4 mb-4 justify-end">
            <span class="flex items-center gap-2 text-[9px] font-black text-white uppercase tracking-widest bg-gray-950 px-3 py-1.5 rounded-full border border-gray-800">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-500 shadow-[0_0_8px_currentColor]"></span>
                Seitenaufrufe
            </span>
            <span class="flex items-center gap-2 text-[9px] font-black text-white uppercase tracking-widest bg-gray-950 px-3 py-1.5 rounded-full border border-gray-800">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-[0_0_8px_currentColor]"></span>
                Eindeutige Besucher
            </span>
        </div>
        <div class="relative w-full h-56 md:h-80" wire:ignore>
            <canvas id="visitsChart"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 pt-6 border-t border-gray-800">
        {{-- MEISTBESUCHTE SEITEN --}}
        <div class="bg-gray-950/50 rounded-2xl p-5 border border-gray-800 shadow-inner">
            <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Meistbesuchte Seiten
            </h4>
            <div class="space-y-3">
                @if(isset($stats['top_pages']) && count($stats['top_pages']) > 0)
                    @foreach($stats['top_pages'] as $page)
                        @php
                            $totalVisits = $stats['frontend_visits_total'] ?? 1;
                            $pct = $totalVisits > 0 ? round(($page['count'] / $totalVisits) * 100) : 0;
                        @endphp
                        <div class="flex items-center justify-between group/page relative">
                            <div class="flex-1 min-w-0 pr-4 z-10">
                                <p class="text-sm font-bold text-gray-300 truncate" title="{{ $page['path'] }}">{{ $page['path'] === '/' ? '/ (Startseite)' : $page['path'] }}</p>
                            </div>
                            <div class="text-right z-10 shrink-0">
                                <span class="text-xs font-black text-white">{{ $page['count'] }}</span>
                                <span class="text-[10px] text-gray-500 font-bold ml-2">{{ $pct }}%</span>
                            </div>
                            <div class="absolute top-0 left-0 h-full bg-blue-500/10 rounded-lg transition-all duration-500 z-0" style="width: {{ $pct }}%"></div>
                        </div>
                    @endforeach
                @else
                    <p class="text-xs text-gray-600 italic">Keine Daten im Zeitraum.</p>
                @endif
            </div>
        </div>

        {{-- REFERRER --}}
        <div class="bg-gray-950/50 rounded-2xl p-5 border border-gray-800 shadow-inner">
            <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                Top Herkunft (Referrer)
            </h4>
            <div class="space-y-3">
                @if(isset($stats['top_referrers']) && count($stats['top_referrers']) > 0)
                    @foreach($stats['top_referrers'] as $host => $count)
                        <div class="flex items-center justify-between p-2 rounded-xl hover:bg-gray-800/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-6 h-6 rounded bg-gray-800 flex items-center justify-center text-[10px] font-bold text-gray-500 uppercase">{{ substr($host, 0, 2) }}</div>
                                <span class="text-sm font-bold text-gray-300">{{ $host }}</span>
                            </div>
                            <span class="text-xs font-black text-emerald-400 bg-emerald-500/10 px-2 py-1 rounded-md border border-emerald-500/20">{{ $count }}</span>
                        </div>
                    @endforeach
                @else
                    <p class="text-xs text-gray-600 italic">Fast alle Zugriffe waren direkte Eingaben oder Lesezeichen.</p>
                @endif
            </div>
        </div>
    </div>
</div>

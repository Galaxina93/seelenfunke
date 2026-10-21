@php
    $sources = $stats['traffic_sources'] ?? [
        'total_sessions' => 0,
        'channels' => [],
        'top_sources' => [],
        'top_campaigns' => [],
        'summary_text' => 'Keine Daten vorhanden.'
    ];
    $topChannel = collect($sources['channels'] ?? [])->sortByDesc('count')->first();
@endphp

<div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-[1.5rem] md:rounded-[2.5rem] dark:shadow-2xl border border-gray-200/80 dark:border-gray-800 p-5 md:p-8 flex flex-col w-full overflow-hidden relative group transition-colors duration-300">
    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-500 via-teal-400 to-transparent opacity-50"></div>

    {{-- HEADER --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h2 class="text-xl md:text-2xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-3">
                <i class="solar-compass-bold-duotone text-emerald-400 dark:drop-shadow-[0_0_10px_rgba(52,211,153,0.5)]"></i>
                Quellenanalyse & Akquise-Kanäle
            </h2>
            <p class="text-[10px] md:text-xs font-black uppercase tracking-widest text-gray-500 mt-2">
                Herkunft der Besucher – Organische Suche, Social Media, Kampagnen & Direktzugriffe
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <div class="bg-gray-50/80 dark:bg-gray-950 p-3 md:p-4 rounded-xl md:rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner flex flex-col items-center min-w-[110px]">
                <span class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Sitzungen</span>
                <span class="text-xl md:text-2xl font-black text-emerald-500 dark:text-emerald-400 dark:drop-shadow-[0_0_10px_rgba(52,211,153,0.4)]">
                    {{ number_format($sources['total_sessions'] ?? 0, 0, ',', '.') }}
                </span>
            </div>

            <div class="bg-gray-50/80 dark:bg-gray-950 p-3 md:p-4 rounded-xl md:rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner flex flex-col items-center min-w-[120px]">
                <span class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Stärkster Kanal</span>
                <span class="text-sm md:text-base font-black text-gray-900 dark:text-white truncate max-w-[140px]" title="{{ $topChannel['label'] ?? '–' }}">
                    {{ $topChannel['label'] ?? '–' }}
                </span>
                <span class="text-[9px] font-bold text-gray-400 mt-0.5">{{ $topChannel['pct'] ?? 0 }}% Anteil</span>
            </div>

            <div class="bg-gray-50/80 dark:bg-gray-950 p-3 md:p-4 rounded-xl md:rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner flex flex-col items-center min-w-[110px]">
                <span class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Kampagnen</span>
                <span class="text-xl md:text-2xl font-black text-amber-500 dark:text-amber-400">
                    {{ count($sources['top_campaigns'] ?? []) }}
                </span>
            </div>
        </div>
    </div>

    {{-- MAIN GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 md:gap-8">

        {{-- LINKE SPALTE: DOUGHNUT CHART & KANAL-VERTEILUNG (5 COLS) --}}
        <div class="lg:col-span-5 flex flex-col justify-between bg-gray-50/80 dark:bg-gray-950/50 rounded-2xl p-5 border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
            <div>
                <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                    </svg>
                    Kanal-Verteilung
                </h4>

                <div class="relative w-full h-52 md:h-60 flex items-center justify-center mb-6" wire:ignore>
                    <canvas id="sourcesDoughnutChart"></canvas>
                </div>
            </div>

            {{-- CHANNEL PROGRESS LIST --}}
            <div class="space-y-3 pt-4 border-t border-gray-200/80 dark:border-gray-800/80">
                @foreach(($sources['channels'] ?? []) as $channel)
                    <div class="space-y-1">
                        <div class="flex justify-between items-center text-xs">
                            <span class="font-bold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $channel['color'] }}"></span>
                                {{ $channel['label'] }}
                            </span>
                            <span class="font-mono text-gray-900 dark:text-white font-black text-[11px]">
                                {{ $channel['count'] }} <span class="text-gray-400 font-normal">({{ $channel['pct'] }}%)</span>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-800 h-1.5 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500" style="width: {{ $channel['pct'] }}%; background-color: {{ $channel['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- RECHTE SPALTE: TOP HERKUNFTS-QUELLEN & KAMPAGNEN (7 COLS) --}}
        <div class="lg:col-span-7 space-y-6">

            {{-- 1. TOP PLATTFORMEN & DOMAINS --}}
            <div class="bg-gray-50/80 dark:bg-gray-950/50 rounded-2xl p-5 border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
                <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-4 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                        Top Herkunfts-Quellen (Suchmaschinen, Social, Direct)
                    </span>
                    <span class="text-[9px] font-bold text-gray-400">Erstanlaufstelle (First-Touch)</span>
                </h4>

                <div class="space-y-2.5">
                    @forelse(($sources['top_sources'] ?? []) as $src)
                        @php
                            $channelColor = match($src['channel']) {
                                'search' => 'bg-blue-500/10 text-blue-500 dark:text-blue-400 border-blue-500/20',
                                'social' => 'bg-pink-500/10 text-pink-500 dark:text-pink-400 border-pink-500/20',
                                'campaign' => 'bg-amber-500/10 text-amber-500 dark:text-amber-400 border-amber-500/20',
                                'referral' => 'bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 border-emerald-500/20',
                                default => 'bg-gray-500/10 text-gray-500 dark:text-gray-400 border-gray-500/20',
                            };
                        @endphp
                        <div class="flex items-center justify-between p-2.5 rounded-xl hover:bg-gray-100/80 dark:hover:bg-gray-900/60 transition-colors border border-transparent hover:border-gray-200 dark:hover:border-gray-800">
                            <div class="flex items-center gap-3 min-w-0 pr-3">
                                <div class="w-8 h-8 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-center shrink-0 shadow-xs">
                                    <i class="{{ $src['icon'] }} text-base text-gray-600 dark:text-gray-300"></i>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate">{{ $src['name'] }}</span>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-[8px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded border {{ $channelColor }}">
                                            {{ $src['channel_label'] }}
                                        </span>
                                        @if(!empty($src['domain']))
                                            <span class="text-[9px] text-gray-400 truncate">{{ $src['domain'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-xs font-black text-gray-900 dark:text-white font-mono">{{ $src['count'] }}</span>
                                <span class="text-[10px] text-gray-400 font-bold ml-1.5">({{ $src['pct'] }}%)</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 dark:text-gray-600 italic py-3 text-center">Keine Quellen im gewählten Zeitraum erfasst.</p>
                    @endforelse
                </div>
            </div>

            {{-- 2. KAMPAGNEN & REFERRALS (UTM / REF) --}}
            <div class="bg-gray-50/80 dark:bg-gray-950/50 rounded-2xl p-5 border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
                <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-3 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        Aktive Kampagnen & Referrals (UTM-Parameter / Freunde werben)
                    </span>
                    <span class="text-[9px] font-bold text-gray-400">Marketing-Attribution</span>
                </h4>

                @if(!empty($sources['top_campaigns']))
                    <div class="space-y-2">
                        @foreach($sources['top_campaigns'] as $camp)
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/70 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800/80">
                                <div class="min-w-0 pr-3">
                                    <div class="text-xs font-black text-gray-900 dark:text-gray-100 truncate">{{ $camp['name'] }}</div>
                                    <div class="text-[9px] text-gray-500 dark:text-gray-400 flex items-center gap-2 mt-0.5">
                                        <span>Source: <strong class="text-gray-700 dark:text-gray-300">{{ $camp['source'] }}</strong></span>
                                        <span>&bull;</span>
                                        <span>Medium: <strong class="text-gray-700 dark:text-gray-300">{{ $camp['medium'] }}</strong></span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-xs font-black text-amber-600 dark:text-amber-400 font-mono">{{ $camp['count'] }} Klicks</span>
                                    <span class="text-[10px] text-gray-400 font-bold block">{{ $camp['pct'] }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-4 rounded-xl bg-amber-500/5 border border-amber-500/20 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                        <strong class="text-amber-600 dark:text-amber-400 block mb-1 uppercase text-[9px] tracking-widest font-black">Tipp für Werbe- & Social-Media-Links</strong>
                        Verwende in Instagram-Bios, Newslettern oder Werbeanzeigen Tracking-Links wie 
                        <code class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-[11px] font-mono text-gray-800 dark:text-gray-200">?utm_source=instagram&utm_medium=bio&utm_campaign=herbst2026</code>.
                        Das System erfasst diese Klicks hier automatisch und ordnet sie genau zu.
                    </div>
                @endif
            </div>

        </div>

    </div>

    {{-- FOOTER / INSIGHT --}}
    <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-800 flex items-center gap-3 text-xs text-gray-600 dark:text-gray-400">
        <i class="bi bi-lightbulb text-emerald-500 text-base"></i>
        <span><strong>Quellen-Erkenntnis:</strong> {{ $sources['summary_text'] }}</span>
    </div>
</div>

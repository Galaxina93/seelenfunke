<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-60: {{ $this->themeColorHex }}99; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <!-- Header -->
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
        <div class="mb-4 sm:mb-0">
            <h1 class="text-2xl md:text-3xl font-black text-[var(--theme-color)] uppercase tracking-widest drop-shadow-[0_0_10px_var(--theme-color-30)]">Multi-Marketing Dashboard</h1>
            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1 font-mono">UTM First-Touch & Last-Touch Attribution</p>
        </div>
        <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2 items-center">
            <select wire:model.live="dateRange" class="bg-gray-900 border border-gray-800 text-gray-300 text-sm rounded-xl focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)] block w-full p-2.5 font-mono">
                <option value="7">Letzte 7 Tage</option>
                <option value="30">Letzte 30 Tage</option>
                <option value="90">Letzte 90 Tage</option>
                <option value="365">Letztes Jahr</option>
            </select>
        </div>
    </div>

    <!-- AI Marketing Radar -->
    <div class="mb-8 w-full">
        @if($agent)
            <div x-data="{ expanded: true }" class="relative bg-gradient-to-r from-gray-900 via-gray-950 to-gray-900 border border-[var(--theme-color-30)] rounded-2xl p-4 md:p-6 shadow-[0_0_40px_var(--theme-color-15)] overflow-hidden group">
                <!-- Glow background effect -->
                <div class="absolute inset-0 bg-[var(--theme-color)] opacity-5 transition-opacity"></div>
                
                <div class="relative z-10 flex flex-col sm:flex-row gap-4 sm:gap-6 items-start sm:items-center">
                    
                    <!-- Icon Badge / Agent Image -->
                    <div class="shrink-0 relative">
                        <div class="absolute -inset-2 bg-[var(--theme-color)] opacity-20 blur-xl rounded-full animate-pulse-slow"></div>
                        <div class="w-14 h-14 md:w-16 md:h-16 bg-gray-950 border-2 border-[var(--theme-color)] rounded-full flex items-center justify-center shadow-inner relative z-10 overflow-hidden">
                            @if($agent->profile_picture)
                                <img src="{{ \Illuminate\Support\Str::startsWith($agent->profile_picture, 'shop/') ? asset($agent->profile_picture) : Storage::url($agent->profile_picture) }}" alt="{{ $agent->name }}" class="w-full h-full object-cover">
                            @else
                                <x-heroicon-m-megaphone class="w-6 h-6 md:w-8 md:h-8 text-[var(--theme-color)] animate-pulse" />
                            @endif
                        </div>
                    </div>

                    <!-- Header Text -->
                    <div class="flex-1 flex justify-between items-center w-full">
                        <div>
                            <div class="flex items-center gap-3 mb-1.5">
                                <span class="text-[10px] sm:text-xs font-black uppercase text-[var(--theme-color)] tracking-widest bg-[var(--theme-color-10)] px-2 py-0.5 rounded border border-[var(--theme-color-30)]">
                                    {{ $agent->name }} | KI RADAR
                                </span>
                            </div>
                            <p class="text-xs sm:text-sm text-gray-400 font-medium">
                                Berechnet Ad-Spends gegen First-Touch LTVs.
                            </p>
                        </div>
                        
                        <div>
                            <button wire:click="generateAiFeedback" class="px-4 py-2 bg-[var(--theme-color-10)] hover:bg-[var(--theme-color)] hover:text-white border border-[var(--theme-color-50)] rounded-xl text-[var(--theme-color)] text-xs font-black uppercase tracking-widest transition-all shadow-[0_0_15px_var(--theme-color-20)]">
                                <span wire:loading.remove wire:target="generateAiFeedback">Analyse starten</span>
                                <span wire:loading wire:target="generateAiFeedback" class="animate-pulse">Analysiere...</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Expandable Mission Content -->
                @if($aiAnalysis)
                    <div class="mt-5 pt-5 border-t border-[var(--theme-color-20)] relative z-10 ml-0 sm:ml-[88px]">
                        <p class="text-sm md:text-base text-gray-200 font-serif leading-relaxed whitespace-pre-wrap">
                            {!! nl2br(e($aiAnalysis)) !!}
                        </p>
                    </div>
                @endif
            </div>
        @else
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 text-center">
                <p class="text-gray-500 text-sm font-mono">Kein aktiver Marketing-Agent in der Firmenstruktur gefunden.</p>
            </div>
        @endif
    </div>

    <!-- Main KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Revenue -->
        <div class="bg-gray-950 border border-gray-800 rounded-2xl p-5 shadow-inner">
            <h2 class="text-[10px] uppercase tracking-widest font-black text-gray-500 mb-1">Total Tracking Revenue</h2>
            <div class="text-3xl font-black text-white">€{{ number_format($totalRevenue, 2, ',', '.') }}</div>
            <div class="text-xs text-gray-500 font-mono mt-2">Aus allen erfassten Touchpoints</div>
        </div>
        
        <!-- Ad Spend -->
        <div class="bg-gray-950 border border-gray-800 rounded-2xl p-5 shadow-inner">
            <h2 class="text-[10px] uppercase tracking-widest font-black text-gray-500 mb-1">Est. Ad Spend</h2>
            <div class="text-3xl font-black text-[var(--theme-color)]">€{{ number_format($simulatedAdSpend, 2, ',', '.') }}</div>
            <div class="text-xs text-[var(--theme-color-60)] font-mono mt-2">Simulierte CPC/CPA Kosten</div>
        </div>

        <!-- Global ROAS -->
        <div class="bg-gray-950 border border-{{ $globalRoas >= 3 ? 'emerald' : 'amber' }}-500/30 rounded-2xl p-5 shadow-[0_0_20px_rgba({{ $globalRoas >= 3 ? '16,185,129' : '245,158,11' }},0.05)] relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 opacity-5"><x-heroicon-s-currency-dollar class="w-32 h-32 text-current" /></div>
            <h2 class="text-[10px] uppercase tracking-widest font-black text-gray-500 mb-1 relative z-10">Global ROAS</h2>
            <div class="text-3xl font-black text-white relative z-10">{{ number_format($globalRoas, 2, ',', '.') }}</div>
            <div class="text-xs {{ $globalRoas >= 3 ? 'text-emerald-500' : 'text-amber-500' }} font-mono mt-2 relative z-10">Return on Ad Spend</div>
        </div>

        <!-- Leads -->
        <div class="bg-gray-950 border border-gray-800 rounded-2xl p-5 shadow-inner">
            <h2 class="text-[10px] uppercase tracking-widest font-black text-gray-500 mb-1">Generierte Leads</h2>
            <div class="text-3xl font-black text-white">{{ number_format($totalLeads, 0, ',', '.') }} <span class="text-sm text-gray-500">B2B</span></div>
            <div class="text-xs text-gray-500 font-mono mt-2 cursor-help" title="Anfragen über Kontaktformular / Kalkulator">via Funnel Pipeline</div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="bg-gray-950 border border-gray-800 rounded-2xl overflow-hidden shadow-2xl">
        <div class="p-6 border-b border-gray-800 flex justify-between items-center">
            <h2 class="text-sm font-black uppercase tracking-widest text-white">Channel Performance Break-down</h2>
            <span class="text-[10px] uppercase tracking-widest font-bold bg-[var(--theme-color-10)] text-[var(--theme-color)] px-2 py-1 rounded border border-[var(--theme-color-20)]">First-Touch Model</span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-400 font-mono">
                <thead class="text-[10px] uppercase tracking-widest text-gray-500 bg-gray-900/50 border-b border-gray-800">
                    <tr>
                        <th scope="col" class="px-6 py-4">Channel / Source</th>
                        <th scope="col" class="px-6 py-4">Orders</th>
                        <th scope="col" class="px-6 py-4">Leads</th>
                        <th scope="col" class="px-6 py-4 font-bold text-gray-300">Revenue (Day 1)</th>
                        <th scope="col" class="px-6 py-4">LTV (Projiziert)</th>
                        <th scope="col" class="px-6 py-4">CPO</th>
                        <th scope="col" class="px-6 py-4 font-bold text-white">ROAS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    @foreach($tableData as $row)
                        <tr class="hover:bg-gray-900/30 transition-colors">
                            <td class="px-6 py-4 flex items-center gap-3">
                                <div class="w-8 h-8 rounded bg-gray-900 border border-gray-700 flex items-center justify-center text-[var(--theme-color)]">
                                    <x-dynamic-component :component="'heroicon-s-' . $row['icon']" class="w-4 h-4" />
                                </div>
                                <span class="font-bold text-white">{{ $row['source'] }}</span>
                            </td>
                            <td class="px-6 py-4">{{ $row['orders'] }}</td>
                            <td class="px-6 py-4">{{ $row['leads'] }}</td>
                            <td class="px-6 py-4 font-bold text-gray-300">€{{ number_format($row['revenue'], 2, ',', '.') }}</td>
                            <td class="px-6 py-4 text-emerald-500">€{{ number_format($row['ltv'], 2, ',', '.') }}</td>
                            <td class="px-6 py-4">€{{ number_format($row['cpo'], 2, ',', '.') }}</td>
                            <td class="px-6 py-4 font-black">
                                @if($row['roas'] >= 3)
                                    <span class="text-emerald-500 px-2 py-1 bg-emerald-500/10 rounded">{{ number_format($row['roas'], 2, ',', '.') }}</span>
                                @elseif($row['roas'] >= 1.5)
                                    <span class="text-amber-500 px-2 py-1 bg-amber-500/10 rounded">{{ number_format($row['roas'], 2, ',', '.') }}</span>
                                @else
                                    <span class="text-red-500 px-2 py-1 bg-red-500/10 rounded">{{ number_format($row['roas'], 2, ',', '.') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
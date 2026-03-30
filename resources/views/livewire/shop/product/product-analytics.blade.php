<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="space-y-6">


    <!-- TAB 1: COMBINED PERFORMANCE & COSTS -->
    <div class="bg-gradient-to-br from-gray-900 via-gray-950 to-black rounded-[2rem] p-6 lg:p-8 border border-[var(--theme-color-20)] shadow-2xl relative overflow-hidden">
        <div class="absolute -top-24 -right-24 w-64 h-64 bg-[var(--theme-color-10)] rounded-full blur-3xl pointer-events-none"></div>

        <div class="mb-8 flex justify-between items-start">
            <div>
                <h3 class="text-xl flex-col sm:flex-row sm:text-2xl font-serif font-bold text-white mb-2 flex items-start sm:items-center gap-3">
                    <i class="solar-wallet-money-bold-duotone text-[var(--theme-color)] text-2xl"></i> Produkt-Performance & Wahre Zahlen
                </h3>
                <p class="text-[11px] font-medium text-gray-400 max-w-2xl leading-relaxed">
                    Echte Netto-Margen inklusive Einkauf, Laser und Verpackung kombiniert mit der Verkaufsgeschwindigkeit und Logistik.
                </p>
            </div>
            <a href="{{ route('admin.product-analytics.export.full') }}" class="hidden sm:inline-flex items-center gap-2 bg-gray-900 border border-gray-700 hover:bg-white hover:text-black text-gray-300 px-6 py-2.5 rounded-full text-[10px] font-black uppercase tracking-widest transition-all shadow-xl group">
                <i class="bi bi-download text-red-500 group-hover:text-red-600 transition-colors"></i> Export
            </a>
        </div>

        <div class="overflow-x-auto border border-gray-800 rounded-[1.5rem] bg-gray-950/50 shadow-inner">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                <tr class="bg-gray-900/80 border-b border-gray-800">
                    <th class="p-4 pl-6 text-[9px] font-black uppercase tracking-widest text-gray-500 w-[30%]">Produkt & Bestand</th>
                    <th class="p-4 text-[9px] font-black uppercase tracking-widest text-gray-500 w-[25%]">Logistik & Lieferzeit</th>
                    <th class="p-4 text-[9px] font-black uppercase tracking-widest text-gray-500 w-[20%]">Kostenstruktur</th>
                    <th class="p-4 pr-6 text-[9px] font-black uppercase tracking-widest text-gray-500 text-right w-[25%]">Netto VK & Marge</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/60">
                @foreach($combinedData as $cd)
                    <tr class="hover:bg-gray-900/30 transition-colors">
                        <td class="p-4 pl-6">
                            <div class="font-bold text-sm text-gray-200 line-clamp-1 mb-1">{{ $cd['name'] }}</div>
                            <div class="flex items-center gap-3 mt-2">
                                <div class="text-[10px] uppercase font-black tracking-widest {{ $cd['stock'] <= 0 ? 'text-red-400 border-red-400/30' : 'text-emerald-400 border-emerald-400/30' }} border px-2 py-0.5 rounded-md text-nowrap">
                                    {{ $cd['stock'] }} Auf Lager
                                </div>
                                <div class="text-[9px] font-black uppercase tracking-widest text-gray-500 text-nowrap">
                                    {{ $cd['sold_last_30'] }} Sales (30T) &bull; <span class="text-blue-400">ø {{ $cd['velocity'] }}/Tag</span>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 align-middle">
                            <div class="text-xs font-bold text-gray-300 mb-1 flex items-center gap-1.5"><i class="bi bi-truck text-gray-500"></i> {{ $cd['supplier_name'] }}</div>
                            <div class="flex items-center gap-2 mt-1 text-[9px] font-black uppercase tracking-widest text-nowrap">
                                <span class="text-gray-500">{{ $cd['delivery_days'] }} Tage LZ</span>
                                <span class="text-gray-700">&bull;</span>
                                @if($cd['reach_days'] === '∞')
                                    <span class="text-gray-400">Reichweite: ∞ (Kein Verbrauch)</span>
                                @elseif($cd['status'] === 'critical' || $cd['status'] === 'warning')
                                    <span class="text-amber-400">Reichweite: ~{{ $cd['reach_days'] }} T</span>
                                @else
                                    <span class="text-emerald-500">Reichweite: ~{{ $cd['reach_days'] }} T</span>
                                @endif
                            </div>
                        </td>
                        <td class="p-4 align-middle">
                            <div class="flex flex-col gap-1 w-32">
                                <div class="flex justify-between items-center text-[10px] font-black"><span class="text-gray-500">EK:</span> <span class="text-gray-300">{{ number_format($cd['purchase_price'], 2, ',', '.') }} €</span></div>
                                <div class="flex justify-between items-center text-[10px] font-black"><span class="text-gray-500">Laser:</span> <span class="text-amber-500">{{ number_format($cd['laser_cost'], 2, ',', '.') }} €</span></div>
                                <div class="flex justify-between items-center text-[10px] font-black"><span class="text-gray-500">Material:</span> <span class="text-emerald-500">{{ number_format($cd['packaging_cost'], 2, ',', '.') }} €</span></div>
                                <div class="flex justify-between items-center text-[10px] font-black"><span class="text-gray-500">Versand:</span> <span class="text-gray-400">{{ number_format($cd['shipping_cost'], 2, ',', '.') }} €</span></div>
                            </div>
                        </td>
                        <td class="p-4 pr-6 align-middle text-right">
                            <div class="text-[9px] text-gray-500 uppercase tracking-wider font-black mb-1">
                                Brutto: <span class="text-gray-400">{{ number_format($cd['price'], 2, ',', '.') }} €</span> |
                                Netto: <span class="text-gray-300">{{ number_format($cd['net_price'], 2, ',', '.') }} €</span>
                            </div>
                            <div class="text-[16px] font-black text-white block mb-0.5">
                                <span class="text-[10px] tracking-widest uppercase text-gray-500 mr-1">Reingewinn:</span>
                                {{ number_format($cd['net_margin'], 2, ',', '.') }} €
                            </div>
                            @php
                                $mClass = $cd['margin_percent'] >= 50 ? 'text-emerald-400' : ($cd['margin_percent'] >= 20 ? 'text-amber-400' : 'text-red-400');
                            @endphp
                            <div class="text-[10px] font-black uppercase tracking-widest {{ $mClass }}">{{ $cd['margin_percent'] }}% Marge</div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if(count($combinedData) === 0)
                <div class="p-8 text-center text-gray-500 text-sm font-medium">Zeigt aktuell keine Daten an.</div>
            @endif
        </div>

        <div class="mt-6 bg-[var(--theme-color-5)] border border-[var(--theme-color-20)] rounded-xl p-4 flex gap-4 items-start">
            <i class="bi bi-lightbulb text-[var(--theme-color)] text-xl"></i>
            <div>
                <h4 class="text-xs font-black text-white uppercase tracking-widest mb-1">Tipp für maximale Rentabilität</h4>
                <p class="text-[10px] text-gray-400 leading-relaxed">Passe im Produkt-Editor die "Laserlaufzeit in Min." und "Stromfaktor pro Min." an, wenn du neue Materialstärken nutzt. Ein 6mm Holz benötigt ca. 40% mehr Laufzeit als das 3mm Standard-Holz, was den Gewinn senkt.</p>
            </div>
        </div>

        <!-- Mobile Export Button below since it's hidden in the header -->
        <div class="mt-6 flex justify-center sm:hidden">
            <a href="{{ route('admin.product-analytics.export.full') }}" class="inline-flex w-full justify-center items-center gap-2 bg-gray-900 border border-gray-700 hover:bg-white hover:text-black text-gray-300 px-6 py-3 rounded-full text-[10px] font-black uppercase tracking-widest transition-all">
                Exportieren
            </a>
        </div>
    </div>

    <!-- TAB 2: LUCID / PACKAGING DETAILS -->
    <div class="bg-gray-900/40 border border-gray-800 rounded-[2rem] p-6 lg:p-8 shadow-inner">
        <h3 class="text-lg font-serif font-bold text-white mb-2 flex items-center gap-3"><i class="solar-leaf-bold-duotone text-emerald-500 text-xl"></i> Detaillierte Zusammensetzung pro Produkt (LUCID Report {{ $lucidData['year'] }})</h3>
        <p class="text-[11px] font-medium text-gray-400 max-w-2xl leading-relaxed mb-6">
            Zusammensetzung der in diesem Jahr verbrauchten Verpackungsmaterialien nach Produkt (Anzahl Verkäufe x Theoretisches Gewicht).
        </p>

        <div class="overflow-x-auto border border-gray-800/60 rounded-xl">
            <table class="w-full text-left border-collapse bg-gray-950/30 min-w-[700px]">
                <thead>
                <tr class="border-b border-gray-800/60 bg-gray-900/50 text-[9px] font-black uppercase tracking-widest text-gray-600">
                    <th class="p-4 pl-6 w-[30%]">Produkt</th>
                    <th class="p-4 text-center w-[15%]">Verkaufte Einheiten</th>
                    @php
                        $materialIcons = [
                            'paper' => ['color' => 'text-emerald-400', 'label' => 'PPK (Papier/Pappe)'],
                            'plastic' => ['color' => 'text-blue-400', 'label' => 'Kunststoffe'],
                            'glass' => ['color' => 'text-teal-400', 'label' => 'Glas'],
                            'wood' => ['color' => 'text-amber-600', 'label' => 'Holz'],
                            'tin' => ['color' => 'text-gray-400', 'label' => 'Weißblech'],
                            'alu' => ['color' => 'text-gray-300', 'label' => 'Aluminium'],
                            'composite' => ['color' => 'text-purple-400', 'label' => 'Verbund'],
                            'other' => ['color' => 'text-lime-400', 'label' => 'Natur / Sonstige'],
                        ];
                    @endphp
                    @foreach($materialIcons as $key => $iconData)
                        @if($lucidData['totals_kg'][$key] > 0)
                            <th class="p-4 text-right {{ $iconData['color'] }}/70">{{ $iconData['label'] }}</th>
                        @endif
                    @endforeach
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/40">
                @forelse($lucidData['details'] as $item)
                    <tr class="hover:bg-gray-800/30 transition-colors">
                        <td class="p-4 pl-6 text-xs font-bold text-gray-300 line-clamp-1 border-0">{{ $item['name'] }}</td>
                        <td class="p-4 text-center text-[10px] text-gray-400 font-bold border-0"><span class="bg-gray-900 border border-gray-800 px-3 py-1 rounded-lg">{{ $item['sold'] }} x</span></td>
                        @foreach($materialIcons as $key => $iconData)
                            @if($lucidData['totals_kg'][$key] > 0)
                                <td class="p-4 text-right text-[11px] font-black {{ $iconData['color'] }} border-0">
                                    {{ $item[$key . '_kg'] > 0 ? number_format($item[$key . '_kg'], 3, ',', '.') . ' kg' : '-' }}
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="py-8 text-center text-gray-500 text-xs font-medium">Es liegen noch keine abgewickelten Verkäufe mit verknüpften Verpackungsmaterialien vor.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex justify-end">
            <a href="{{ route('admin.product-analytics.export.lucid') }}" class="inline-flex w-full sm:w-auto justify-center sm:justify-start items-center gap-2 bg-gray-800 hover:bg-white text-gray-400 hover:text-black border border-gray-700 px-6 py-2.5 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all">
                <i class="bi bi-download text-red-500 hidden sm:inline-block hover:inline-block"></i> Drucken / Export
            </a>
        </div>
    </div>

    {{-- Header --}}
    <div class="mb-6 md:flex md:items-center md:justify-between py-2">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl sm:text-3xl font-bold leading-7 text-white sm:truncate sm:tracking-tight font-serif drop-shadow-md flex items-center gap-3">
                <x-heroicon-o-cube class="w-8 h-8 font-bold text-[var(--theme-color)]" />
                Produkt & Qualitäts Analyse
            </h2>
            <p class="mt-1 text-sm text-gray-400">
                Performance Echtzeit-Tracking für Lieferschäden, Klumpenrisiken, Reviews und Produkt-Margen.
            </p>
        </div>

        <div class="mt-4 flex md:ml-4 md:mt-0 gap-3">
            <select wire:model.live="dateRange" class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-300 bg-gray-900 shadow-sm ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-inset focus:ring-primary sm:text-sm sm:leading-6 transition-all duration-300">
                <option value="7">Letzte 7 Tage</option>
                <option value="30">Letzte 30 Tage</option>
                <option value="90">Letzte 90 Tage</option>
                <option value="365">Letztes Jahr</option>
                <option value="all">Gesamte Historie</option>
            </select>
        </div>
    </div>

    {{-- Main Charting Wrapper (Alpine.js integration for Chart.js) --}}
    <div x-data="productDashboard()"
         x-init="initCharts()"
         @analytics-updated.window="updateCharts()"
         class="space-y-6 mb-12">

        {{-- Hidden JSON variables to bridge Livewire PHP arrays directly to Alpine/ChartJS --}}
        <div class="hidden"
             id="analytics-data-bridge"
             data-loss='@json($lossData)'
             data-toploss='@json($topLossData)'
             data-review='@json($reviewData)'
             data-supplier='@json($supplierData)'>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Loss Chart --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-rose-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-rose-500" />
                        Schadensmeldungen & Verlust (Menge)
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="lossChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-rose-400 uppercase text-[10px] tracking-widest block mb-1">Qualitäts-Tracker</strong>Zeigt an, wie viele Einheiten pro Zeitraum als Verlust oder Defekt gemeldet wurden.</p>
                </div>
            </div>

            {{-- Top Loss Chart --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-rose-900/40 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-red-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-currency-euro class="w-5 h-5 text-red-500" />
                        Top 5 Schadens-Treiber (€)
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="topLossChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-red-900/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-red-400 uppercase text-[10px] tracking-widest block mb-1">Finanzieller Impact</strong>Identifiziert sofort die 5 fehleranfälligsten Produkte, basierend auf dem monetären Schaden.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Review Categories --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-star class="w-5 h-5 text-amber-500" />
                        Kundenbewertungen (Sterne)
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="reviewChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-amber-400 uppercase text-[10px] tracking-widest block mb-1">Kundenzufriedenheit</strong>Die Verteilung der vergebenen Ratings (1-5 Sterne) auf deine Produkte.</p>
                </div>
            </div>

            {{-- Supplier Types --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-truck class="w-5 h-5 text-indigo-500" />
                        Lieferanten Klumpenrisiko
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="supplierChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-indigo-400 uppercase text-[10px] tracking-widest block mb-1">Lieferketten Logistik</strong>Anzahl der physischen Produkte, die an einen einzelnen Lieferanten gebunden sind.</p>
                </div>
            </div>
        </div>

        {{-- Dashboard Scripts --}}
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('productDashboard', () => {
                    let lossChartObj = null;
                    let topLossChartObj = null;
                    let reviewChartObj = null;
                    let supplierChartObj = null;

                    return {
                        getPayload() {
                            const el = document.getElementById('analytics-data-bridge');
                            return {
                                loss: JSON.parse(el.getAttribute('data-loss')),
                                topLoss: JSON.parse(el.getAttribute('data-toploss')),
                                review: JSON.parse(el.getAttribute('data-review')),
                                supplier: JSON.parse(el.getAttribute('data-supplier'))
                            };
                        },

                        initCharts() {
                            const data = this.getPayload();
                            const gridOptions = { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false };
                            const gridOptionsX = { display: false, drawBorder: false };

                            // 1. Loss Chart
                            const ctxLs = document.getElementById('lossChart').getContext('2d');
                            lossChartObj = new Chart(ctxLs, {
                                type: 'bar',
                                data: {
                                    labels: data.loss.labels,
                                    datasets: [{
                                        label: 'Beschädigte Artikel (Menge)',
                                        data: data.loss.data,
                                        backgroundColor: 'rgba(244, 63, 94, 0.8)',
                                        borderRadius: 4, barPercentage: 0.6
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
                                    plugins: { legend: { display: false } }
                                }
                            });

                            // 2. Top Loss Chart
                            const ctxTop = document.getElementById('topLossChart').getContext('2d');
                            topLossChartObj = new Chart(ctxTop, {
                                type: 'bar',
                                data: {
                                    labels: data.topLoss.labels,
                                    datasets: [{
                                        label: 'Finanzieller Schaden (€)',
                                        data: data.topLoss.data,
                                        backgroundColor: 'rgba(239, 68, 68, 0.9)',
                                        borderRadius: 4, barPercentage: 0.6
                                    }]
                                },
                                options: {
                                    indexAxis: 'y',
                                    responsive: true, maintainAspectRatio: false,
                                    scales: { x: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, y: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
                                    plugins: { legend: { display: false } }
                                }
                            });

                            // 3. Review Chart
                            const ctxRev = document.getElementById('reviewChart').getContext('2d');
                            reviewChartObj = new Chart(ctxRev, {
                                type: 'doughnut',
                                data: {
                                    labels: data.review.labels,
                                    datasets: [{
                                        data: data.review.data,
                                        backgroundColor: ['#10b981', '#34d399', '#fbbf24', '#f87171', '#ef4444'],
                                        borderWidth: 2, borderColor: '#1f2937'
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: { legend: { position: 'right', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                                    cutout: '60%'
                                }
                            });

                            // 4. Supplier Chart
                            const ctxSup = document.getElementById('supplierChart').getContext('2d');
                            supplierChartObj = new Chart(ctxSup, {
                                type: 'doughnut',
                                data: {
                                    labels: data.supplier.labels,
                                    datasets: [{
                                        data: data.supplier.data,
                                        backgroundColor: ['#6366f1', '#06b6d4', '#ec4899', '#f59e0b', '#8b5cf6', '#14b8a6', '#64748b'],
                                        borderWidth: 2, borderColor: '#1f2937'
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: { legend: { position: 'right', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                                    cutout: '60%'
                                }
                            });
                        },

                        updateCharts() {
                            const data = this.getPayload();

                            const updateMap = [
                                { obj: lossChartObj, src: data.loss },
                                { obj: topLossChartObj, src: data.topLoss },
                                { obj: reviewChartObj, src: data.review },
                                { obj: supplierChartObj, src: data.supplier }
                            ];

                            updateMap.forEach(m => {
                                if (m.obj && m.src) {
                                    m.obj.data.labels = m.src.labels;
                                    m.obj.data.datasets[0].data = m.src.data;
                                    m.obj.update();
                                }
                            });
                        }
                    };
                });
            });
        </script>
    </div>

</div>

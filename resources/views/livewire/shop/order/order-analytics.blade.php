<div>
    {{-- Header --}}
    <div class="mb-6 md:flex md:items-center md:justify-between py-2">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl sm:text-3xl font-bold leading-7 text-white sm:truncate sm:tracking-tight font-serif drop-shadow-md flex items-center gap-3">
                <x-heroicon-o-chart-bar class="w-8 h-8 text-primary" />
                Detaillierte Finanz- & Absatzanalyse
            </h2>
            <p class="mt-1 text-sm text-gray-400">
                Hochpräzise KPIs für physische Güter: Kundenbindung, Stornos, Topseller und stündliche Auslastungen.
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
            
            <a href="{{ route('admin.orders') }}" class="inline-flex items-center rounded-md bg-white/10 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-white/20 hover:scale-105 transition-all duration-300 mt-2 border border-gray-700">
                Zurück
            </a>
        </div>
    </div>


    {{-- Main Charting Wrapper (Alpine.js integration for Chart.js) --}}
    <div x-data="orderAnalyticsDashboard()"
         x-init="initCharts()"
         @analytics-updated.window="updateCharts()"
         class="space-y-6">

        {{-- Hidden JSON variables to bridge Livewire PHP arrays directly to Alpine/ChartJS --}}
        <div class="hidden" 
             id="analytics-data-bridge"
             data-b2b='@json($b2bData)'
             data-processing='@json($processingData)'
             data-peak='@json($peakTimesData)'
             data-retention='@json($retentionData)'
             data-cancellation='@json($cancellationData)'
             data-bestseller='@json($bestsellerData)'
             data-weekday='@json($weekdayData)'
             data-quotes='@json($quotesData)'
             data-revocations='@json($revocationsData)'>
        </div>

        <!-- ROW 1: Retention & B2B/B2C -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Retention Rate --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-users class="w-5 h-5 text-emerald-500" />
                        Kundenbindung (Retention Rate)
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="retentionChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-emerald-400 uppercase text-[10px] tracking-widest block mb-1">Vorteil & Optimierung</strong>Zeigt an, ob unser Marketing nachhaltige Stammkunden aufbaut oder nur teure Einmalkäufer anzieht.</p>
                </div>
            </div>

            {{-- B2B vs B2C --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-briefcase class="w-5 h-5 text-blue-500" />
                        B2B vs. B2C Volumenverteilung
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="b2bChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-blue-400 uppercase text-[10px] tracking-widest block mb-1">Vorteil & Optimierung</strong>Hilft uns, Werbebudget gezielter auszurichten. B2B Kunden bestellen meist größere haptische Mengen zu wiederkehrenden Anlässen.</p>
                </div>
            </div>
        </div>

        <!-- ROW 2: Cancellation Drain & Bestsellers -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Cancellation Drain --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-rose-900/40 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-rose-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                            <x-heroicon-o-currency-euro class="w-5 h-5 text-rose-500" />
                            Storno- & Umsatzverlust
                        </h3>
                        <span class="px-2 py-1 rounded bg-rose-500/20 text-rose-400 text-xs font-bold border border-rose-500/20">
                            - {{ number_format($cancellationData['total_lost'] ?? 0, 2, ',', '.') }} €
                        </span>
                    </div>
                    <div class="relative h-64 w-full mt-2" wire:ignore>
                        <canvas id="cancellationChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-rose-900/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-rose-400 uppercase text-[10px] tracking-widest block mb-1">Finanzieller Schmerzpunkt</strong>Der summierte Geldwert abgebrochener oder fehlgeschlagener Käufe pro Tag. Legt Reibungsverluste am Checkout gnadenlos offen.</p>
                </div>
            </div>

            {{-- Top 5 Sellers --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-fire class="w-5 h-5 text-amber-500" />
                        Top 5 Physische Bestseller (Einheiten)
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="bestsellerChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-amber-400 uppercase text-[10px] tracking-widest block mb-1">Performance Treiber</strong>Die absolute Cashcow-Ansicht. Definiert knallhart, welche Rohlinge wir priorisiert im Lager aufstocken müssen!</p>
                </div>
            </div>
        </div>

        <!-- ROW 3: Time Metrics (Durations & Peaks) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Processing Duration Line Chart --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-cyan-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-clock class="w-5 h-5 text-cyan-500" />
                        Ø Fulfillment Durchlaufzeit (Std)
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="processingChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-cyan-400 uppercase text-[10px] tracking-widest block mb-1">Qualitätssicherung</strong>Zeigt die Lücke zwischen 'Bestellung Eingegangen' und 'Paket Versendet'. Zeigt auf, wann unser Team zeitlich absäuft.</p>
                </div>
            </div>

            {{-- Peak Times Bar Chart --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-sun class="w-5 h-5 text-purple-500" />
                        Bestell-Peaks nach Tageszeit
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="peakChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-purple-400 uppercase text-[10px] tracking-widest block mb-1">Verhaltensanalyse</strong>Akkumulierte Bestellungen pro Uhrzeit (00:00 - 23:00). Ideal für das exakte Timing von Werbe-Mails oder neuen Ad-Spend Budgets.</p>
                </div>
            </div>
        </div>

        <!-- ROW 4: Weekdays Full Width -->
        <div class="grid grid-cols-1 gap-6">
            {{-- Weekday Distribution Bar Chart --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-calendar-days class="w-5 h-5 text-primary" />
                        Absatzvolumen nach Wochentag
                    </h3>
                    <div class="relative h-72 w-full mt-4" wire:ignore>
                        <canvas id="weekdayChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50 md:flex items-center justify-between">
                    <p class="text-xs text-gray-400"><strong class="text-primary uppercase text-[10px] tracking-widest block mb-1">Personal-Ressourcen Planung</strong>Aggregiert alle Bestellungen auf den jeweiligen Wochentag. Erlaubt uns, Packer genau an den strategischen Tagen vor Ort zu haben.</p>
                </div>
            </div>
        </div>

        <!-- ROW 5: Quotes & Revocations -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Quotes Volume --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-document-text class="w-5 h-5 text-indigo-500" />
                        Angebot-Anfragen (Interesse)
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="quotesChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-indigo-400 uppercase text-[10px] tracking-widest block mb-1">Lead Generation</strong>Anzahl der angefragten Staffelangebote über die Zeit. Ein starker Indikator für heranwachsendes B2B-Potenzial.</p>
                </div>
            </div>

            {{-- Revocations --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-red-900/40 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-red-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-arrow-uturn-left class="w-5 h-5 text-red-500" />
                        Widerrufe (Eskalationen)
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="revocationsChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-red-900/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-red-400 uppercase text-[10px] tracking-widest block mb-1">Krisen Indikator</strong>Anzahl der eingereichten Widerrufe. Wenn diese Kurve steigt, müssen sofort Qualitätskontrolle oder Produktbeschreibungen nachgebessert werden.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('orderAnalyticsDashboard', () => {
            let b2bChartObj = null;
            let processingChartObj = null;
            let peakChartObj = null;
            let retentionChartObj = null;
            let cancellationChartObj = null;
            let bestsellerChartObj = null;
            let weekdayChartObj = null;
            let quotesChartObj = null;
            let revocationsChartObj = null;

            return {

            getPayload() {
                const el = document.getElementById('analytics-data-bridge');
                return {
                    b2b: JSON.parse(el.getAttribute('data-b2b')),
                    processing: JSON.parse(el.getAttribute('data-processing')),
                    peak: JSON.parse(el.getAttribute('data-peak')),
                    retention: JSON.parse(el.getAttribute('data-retention')),
                    cancellation: JSON.parse(el.getAttribute('data-cancellation')),
                    bestseller: JSON.parse(el.getAttribute('data-bestseller')),
                    weekday: JSON.parse(el.getAttribute('data-weekday')),
                    quotes: JSON.parse(el.getAttribute('data-quotes')),
                    revocations: JSON.parse(el.getAttribute('data-revocations'))
                };
            },

            initCharts() {
                const colors = {
                    primary: 'rgba(197, 160, 89, 1)',      // Gold
                    primaryLight: 'rgba(197, 160, 89, 0.2)', // Gold Fade
                    blue: 'rgba(59, 130, 246, 1)',         // Blue
                    blueLight: 'rgba(59, 130, 246, 0.2)',
                    purple: 'rgba(168, 85, 247, 1)',       // Purple
                    emerald: 'rgba(16, 185, 129, 1)',      // Emerald
                    rose: 'rgba(225, 29, 72, 1)',          // Rose
                    roseLight: 'rgba(225, 29, 72, 0.2)',
                    amber: 'rgba(245, 158, 11, 1)',        // Amber
                    cyan: 'rgba(6, 182, 212, 1)',          // Cyan
                    cyanLight: 'rgba(6, 182, 212, 0.2)',
                    gray: 'rgba(156, 163, 175, 1)'         // Gray
                };
                
                const gridOptions = { color: 'rgba(255, 255, 255, 0.05)', tickColor: 'transparent' };
                const gridOptionsX = { display: false };
                const data = this.getPayload();

                // 1. Retention Rate (Doughnut)
                const ctxRet = document.getElementById('retentionChart').getContext('2d');
                retentionChartObj = new Chart(ctxRet, {
                    type: 'doughnut',
                    data: {
                        labels: data.retention.labels,
                        datasets: [{
                            data: data.retention.data,
                            backgroundColor: [colors.emerald, 'rgba(255, 255, 255, 0.1)'],
                            borderColor: ['rgba(0,0,0,0)', 'rgba(0,0,0,0)'],
                            borderWidth: 0, hoverOffset: 4
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom', labels: { color: colors.gray } } } }
                });

                // 2. B2B vs B2C (Doughnut)
                const ctxB2b = document.getElementById('b2bChart').getContext('2d');
                b2bChartObj = new Chart(ctxB2b, {
                    type: 'doughnut',
                    data: {
                        labels: data.b2b.labels,
                        datasets: [{
                            data: data.b2b.data,
                            backgroundColor: ['rgba(255, 255, 255, 0.1)', colors.blue],
                            borderColor: ['rgba(0,0,0,0)', 'rgba(0,0,0,0)'],
                            borderWidth: 0, hoverOffset: 4
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom', labels: { color: colors.gray } } } }
                });

                // 3. Cancellation Drain (Line)
                const ctxCan = document.getElementById('cancellationChart').getContext('2d');
                cancellationChartObj = new Chart(ctxCan, {
                    type: 'line',
                    data: {
                        labels: data.cancellation.labels,
                        datasets: [{
                            label: 'Verlorener Wert in €',
                            data: data.cancellation.data,
                            borderColor: colors.rose,
                            backgroundColor: colors.roseLight,
                            borderWidth: 2, tension: 0.1, fill: true,
                            pointBackgroundColor: colors.rose, pointBorderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray } }, x: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });

                // 4. Bestsellers (Bar Horizontal)
                const ctxBest = document.getElementById('bestsellerChart').getContext('2d');
                bestsellerChartObj = new Chart(ctxBest, {
                    type: 'bar',
                    data: {
                        labels: data.bestseller.labels,
                        datasets: [{
                            label: 'Verkaufte Einheiten',
                            data: data.bestseller.data,
                            backgroundColor: colors.amber,
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true, maintainAspectRatio: false,
                        scales: { x: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray, precision: 0 } }, y: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });

                // 5. Processing Time (Line)
                const ctxProc = document.getElementById('processingChart').getContext('2d');
                processingChartObj = new Chart(ctxProc, {
                    type: 'line',
                    data: {
                        labels: data.processing.labels,
                        datasets: [{
                            label: 'Dauer (Std)',
                            data: data.processing.data,
                            borderColor: colors.cyan,
                            backgroundColor: colors.cyanLight,
                            borderWidth: 2, tension: 0.4, fill: true,
                            pointBackgroundColor: colors.cyan, pointBorderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray } }, x: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });

                // 6. Peak Times (Bar)
                const ctxPeak = document.getElementById('peakChart').getContext('2d');
                peakChartObj = new Chart(ctxPeak, {
                    type: 'bar',
                    data: {
                        labels: data.peak.labels,
                        datasets: [{
                            label: 'Bestellungen pro Uhrzeit',
                            data: data.peak.data,
                            backgroundColor: colors.purple,
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });

                // 7. Weekday Volume (Bar)
                const ctxWeek = document.getElementById('weekdayChart').getContext('2d');
                weekdayChartObj = new Chart(ctxWeek, {
                    type: 'bar',
                    data: {
                        labels: data.weekday.labels,
                        datasets: [{
                            label: 'Bestellungen pro Wochentag',
                            data: data.weekday.data,
                            backgroundColor: colors.primary,
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });

                // 8. Quotes (Line)
                const ctxQuotes = document.getElementById('quotesChart').getContext('2d');
                quotesChartObj = new Chart(ctxQuotes, {
                    type: 'line',
                    data: {
                        labels: data.quotes.labels,
                        datasets: [{
                            label: 'Angebote',
                            data: data.quotes.data,
                            borderColor: 'rgba(99, 102, 241, 1)',
                            backgroundColor: 'rgba(99, 102, 241, 0.2)',
                            borderWidth: 2, tension: 0.4, fill: true,
                            pointBackgroundColor: 'rgba(99, 102, 241, 1)', pointBorderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });

                // 9. Revocations (Line)
                const ctxRevs = document.getElementById('revocationsChart').getContext('2d');
                revocationsChartObj = new Chart(ctxRevs, {
                    type: 'line',
                    data: {
                        labels: data.revocations.labels,
                        datasets: [{
                            label: 'Widerrufe',
                            data: data.revocations.data,
                            borderColor: 'rgba(239, 68, 68, 1)',
                            backgroundColor: 'rgba(239, 68, 68, 0.2)',
                            borderWidth: 2, tension: 0.4, fill: true,
                            pointBackgroundColor: 'rgba(239, 68, 68, 1)', pointBorderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });
            },

            updateCharts() {
                const data = this.getPayload();

                const mapping = [
                    { obj: retentionChartObj, source: data.retention },
                    { obj: b2bChartObj, source: data.b2b },
                    { obj: cancellationChartObj, source: data.cancellation },
                    { obj: bestsellerChartObj, source: data.bestseller },
                    { obj: processingChartObj, source: data.processing },
                    { obj: peakChartObj, source: data.peak },
                    { obj: weekdayChartObj, source: data.weekday },
                    { obj: quotesChartObj, source: data.quotes },
                    { obj: revocationsChartObj, source: data.revocations }
                ];

                mapping.forEach(item => {
                    if (item.obj) {
                        item.obj.data.labels = item.source.labels;
                        item.obj.data.datasets[0].data = item.source.data;
                        item.obj.update();
                    }
                });
            }
        };
    });
});
</script>
@endpush

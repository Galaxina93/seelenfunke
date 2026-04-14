<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div>
    {{-- Header --}}
    <div class="mb-6 md:flex md:items-center md:justify-between py-2">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl sm:text-3xl font-bold leading-7 text-white sm:truncate sm:tracking-tight font-serif drop-shadow-md flex items-center gap-3">
                <x-heroicon-o-chart-pie class="w-8 h-8 font-bold {{ $this->themeColorClass }}" />
                Marketing & Growth Analyse
            </h2>
            <p class="mt-1 text-sm text-gray-400">
                Performance Tracking für Newsletter, Gutscheine, Blog-Trafic und Kampagnen-Auswertungen.
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
    <div x-data="marketingDashboard()"
         x-init="initCharts()"
         @analytics-updated.window="updateCharts()"
         class="space-y-6">

        {{-- Hidden JSON variables to bridge Livewire PHP arrays directly to Alpine/ChartJS --}}
        <div class="hidden"
             id="analytics-data-bridge"
             data-newsletter='@json($newsletterData)'
             data-voucher='@json($voucherData)'
             data-blog='@json($blogData)'
             data-vouchertype='@json($voucherTypeData)'
             data-landingpage='@json($landingPageData)'>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Newsletter Chart --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-users class="w-5 h-5 text-emerald-500" />
                        Newsletter Wachstum (Verifiziert)
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="newsletterChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-emerald-400 uppercase text-[10px] tracking-widest block mb-1">Lead Generation</strong>Zeigt an, wie viele neue verifizierte Double-Opt-In Subscriber wir gewinnen.</p>
                </div>
            </div>

            {{-- Voucher Generation --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-rose-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-ticket class="w-5 h-5 text-rose-500" />
                        Gutschein Generierungen
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="voucherChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-rose-400 uppercase text-[10px] tracking-widest block mb-1">Kampagnen Taktung</strong>Visualisiert den operativen Output an neu generierten Kampagnen-Gutscheinen.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Blog Categories --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-document-text class="w-5 h-5 text-indigo-500" />
                        Blogartikel nach Kategorie
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="blogChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-indigo-400 uppercase text-[10px] tracking-widest block mb-1">SEO Fokus</strong>Bricht die erzeugten Content-Stücke in ihre Themensilos herunter.</p>
                </div>
            </div>

            {{-- Voucher Types --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-gift class="w-5 h-5 text-amber-500" />
                        Gutschein Verteilung (Typen)
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="voucherTypeChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-amber-400 uppercase text-[10px] tracking-widest block mb-1">Verkaufstaktik</strong>Prozentuale Aufschlüsselung der Gutschein-Archetypen (z.B. Fester Euro-Wert vs Rabatt %).</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Landing Page Chart --}}
            <div class="lg:col-span-2 bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-cursor-arrow-rays class="w-5 h-5 text-purple-500" />
                        Landing Page Aufrufe
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="landingPageChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-purple-400 uppercase text-[10px] tracking-widest block mb-1">Traffic Acquisition</strong>Misst die Seitenaufrufe auf spezifische Landing Pages, generiert durch Instagram und Ads.</p>
                </div>
            </div>

            {{-- Top Performing Landing Pages --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden flex flex-col h-full">
                <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2 mb-6">
                    <x-heroicon-o-fire class="w-5 h-5 text-[var(--theme-color)]" />
                    Top Performer
                </h3>
                <div class="flex-1 overflow-y-auto pr-1 space-y-4">
                    @forelse($topLandingPages as $top)
                        <div class="flex items-center justify-between p-3 bg-gray-900/50 rounded-xl border border-gray-700/50 hover:border-purple-500/30 transition-colors group">
                             <div class="flex-1 min-w-0 pr-4">
                                  <p class="text-sm font-bold text-gray-200 truncate group-hover:text-purple-400 transition-colors">{{ $top['product_name'] }}</p>
                                  <p class="text-[10px] text-gray-500 font-mono truncate mt-0.5">
                                      <a href="{{ $top['path'] }}" target="_blank" class="hover:text-gray-300">{{ $top['path'] }}</a>
                                  </p>
                             </div>
                             <div class="flex flex-col items-center justify-center shrink-0 w-12 h-12 bg-gray-950 rounded-lg border border-gray-800 shadow-inner">
                                  <span class="text-xs text-gray-500 uppercase font-black" style="font-size: 8px;">Views</span>
                                  <span class="text-sm font-black text-white">{{ $top['visits'] }}</span>
                             </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center h-full text-center py-8">
                            <x-heroicon-o-document-magnifying-glass class="w-10 h-10 text-gray-600 mb-2" />
                            <p class="text-sm text-gray-400">Keine Aufrufe verzeichnet</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Dashboard Scripts --}}
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('marketingDashboard', () => {
                    let newsletterChartObj = null;
                    let voucherChartObj = null;
                    let blogChartObj = null;
                    let voucherTypeChartObj = null;
                    let landingPageChartObj = null;

                    return {
                        getPayload() {
                            const el = document.getElementById('analytics-data-bridge');
                            return {
                                newsletter: JSON.parse(el.getAttribute('data-newsletter')),
                                voucher: JSON.parse(el.getAttribute('data-voucher')),
                                blog: JSON.parse(el.getAttribute('data-blog')),
                                voucherType: JSON.parse(el.getAttribute('data-vouchertype')),
                                landingPage: JSON.parse(el.getAttribute('data-landingpage'))
                            };
                        },

                        initCharts() {
                            const data = this.getPayload();
                            const gridOptions = { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false };
                            const gridOptionsX = { display: false, drawBorder: false };

                            const toArr = (val) => Array.isArray(val) ? val : Object.values(val || {});

                            // 1. Newsletter Growth
                            const ctxNl = document.getElementById('newsletterChart').getContext('2d');
                            newsletterChartObj = new Chart(ctxNl, {
                                type: 'line',
                                data: {
                                    labels: toArr(data.newsletter.labels),
                                    datasets: [{
                                        label: 'Neue Abonnenten',
                                        data: toArr(data.newsletter.data),
                                        borderColor: 'rgba(16, 185, 129, 1)',
                                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                        borderWidth: 2, tension: 0.4, fill: true,
                                        pointBackgroundColor: 'rgba(16, 185, 129, 1)', pointBorderColor: '#fff',
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
                                    plugins: { legend: { display: false } }
                                }
                            });

                            // 2. Voucher Gen
                            const ctxVc = document.getElementById('voucherChart').getContext('2d');
                            voucherChartObj = new Chart(ctxVc, {
                                type: 'bar',
                                data: {
                                    labels: toArr(data.voucher.labels),
                                    datasets: [{
                                        label: 'Erstellte Gutscheine',
                                        data: toArr(data.voucher.data),
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

                            // 3. Blog Categories
                            const ctxBl = document.getElementById('blogChart').getContext('2d');
                            blogChartObj = new Chart(ctxBl, {
                                type: 'doughnut',
                                data: {
                                    labels: toArr(data.blog.labels),
                                    datasets: [{
                                        data: toArr(data.blog.data),
                                        backgroundColor: ['#6366f1', '#10b981', '#ef4444', '#f59e0b', '#8b5cf6', '#06b6d4', '#ec4899', '#14b8a6'],
                                        borderWidth: 2, borderColor: '#1f2937'
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: { legend: { position: 'bottom', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                                    cutout: '60%'
                                }
                            });

                            // 4. Voucher Types
                            const ctxVt = document.getElementById('voucherTypeChart').getContext('2d');
                            voucherTypeChartObj = new Chart(ctxVt, {
                                type: 'doughnut',
                                data: {
                                    labels: toArr(data.voucherType.labels),
                                    datasets: [{
                                        data: toArr(data.voucherType.data),
                                        backgroundColor: ['#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#10b981', '#6366f1'],
                                        borderWidth: 2, borderColor: '#1f2937'
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: { legend: { position: 'bottom', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                                    cutout: '60%'
                                }
                            });
                            // 5. Landing Page Visits
                            const ctxLp = document.getElementById('landingPageChart').getContext('2d');
                            landingPageChartObj = new Chart(ctxLp, {
                                type: 'line',
                                data: {
                                    labels: toArr(data.landingPage.labels),
                                    datasets: [{
                                        label: 'Seitenaufrufe',
                                        data: toArr(data.landingPage.data),
                                        borderColor: 'rgba(168, 85, 247, 1)',
                                        backgroundColor: 'rgba(168, 85, 247, 0.1)',
                                        borderWidth: 2, tension: 0.4, fill: true,
                                        pointBackgroundColor: 'rgba(168, 85, 247, 1)', pointBorderColor: '#fff',
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
                                    plugins: { legend: { display: false } }
                                }
                            });
                        },

                        updateCharts() {
                            const data = this.getPayload();
                            const toArr = (val) => Array.isArray(val) ? val : Object.values(val || {});

                            const updateMap = [
                                { obj: newsletterChartObj, src: data.newsletter },
                                { obj: voucherChartObj, src: data.voucher },
                                { obj: blogChartObj, src: data.blog },
                                { obj: voucherTypeChartObj, src: data.voucherType },
                                { obj: landingPageChartObj, src: data.landingPage }
                            ];

                            updateMap.forEach(m => {
                                if (m.obj && m.src) {
                                    m.obj.data.labels = toArr(m.src.labels);
                                    m.obj.data.datasets[0].data = toArr(m.src.data);
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

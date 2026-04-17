<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div>
    {{-- Header --}}
    <div class="mb-6 md:flex md:items-center md:justify-between py-2">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl sm:text-3xl font-bold leading-7 text-white sm:truncate sm:tracking-tight font-serif drop-shadow-md flex items-center gap-3">
                <x-heroicon-o-chart-pie class="w-8 h-8 font-bold {{ $this->themeColorClass }}" />
                Support Analyse
            </h2>
            <p class="mt-1 text-sm text-gray-400">
                Performance Tracking für Chats, Tickets und Kontaktanfragen.
            </p>
        </div>
        
        <div class="mt-4 flex md:ml-4 md:mt-0 gap-3">
            <select wire:model.live="dateRange" class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-300 bg-gray-900 shadow-sm ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-inset focus:ring-[var(--theme-color)] sm:text-sm sm:leading-6 transition-all duration-300">
                <option value="7">Letzte 7 Tage</option>
                <option value="30">Letzte 30 Tage</option>
                <option value="90">Letzte 90 Tage</option>
                <option value="365">Letztes Jahr</option>
                <option value="all">Gesamte Historie</option>
            </select>
        </div>
    </div>

    {{-- KPIs Row --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-4 shadow-xl">
            <h4 class="text-[10px] uppercase font-black tracking-widest text-cyan-500 mb-1">Offene Tickets</h4>
            <p class="text-2xl font-serif text-white">{{ $kpiTicketsOpen }}</p>
        </div>
        <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-4 shadow-xl">
            <h4 class="text-[10px] uppercase font-black tracking-widest text-emerald-500 mb-1">Gelöste Tickets</h4>
            <p class="text-2xl font-serif text-white">{{ $kpiTicketsClosed }}</p>
        </div>
        <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-4 shadow-xl">
            <h4 class="text-[10px] uppercase font-black tracking-widest text-amber-500 mb-1">Ø Ticket Bewertung</h4>
            <p class="text-2xl font-serif text-white">{{ number_format($kpiAvgTicketRating, 1, ',', '.') }}<span class="text-amber-500 text-sm ml-1">★</span></p>
        </div>
        <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-4 shadow-xl">
            <h4 class="text-[10px] uppercase font-black tracking-widest text-purple-500 mb-1">Ø Lösungszeit</h4>
            <p class="text-2xl font-serif text-white">{{ $kpiAvgResolutionHrs }}h</p>
        </div>
    </div>
    <div x-data="supportDashboard()"
         x-init="initCharts()"
         @analytics-updated.window="updateCharts()"
         class="space-y-6">

        {{-- Hidden JSON variables to bridge Livewire PHP arrays directly to Alpine/ChartJS --}}
        <div class="hidden" 
             id="analytics-data-bridge"
             data-volume='@json($volumeData)'
             data-source='@json($sourceData)'
             data-ticketstatus='@json($ticketStatusData)'
             data-chatstatus='@json($chatStatusData)'
             data-chatrating='@json($chatRatingData)'
             data-ticketrating='@json($ticketRatingData)'>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Volume Chart --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-cyan-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-chat-bubble-left-right class="w-5 h-5 text-cyan-500" />
                        Support Aufkommen (Gesamt)
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="volumeChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-cyan-400 uppercase text-[10px] tracking-widest block mb-1">Ticket Volumen</strong>Zeigt an, wie viele neue Tickets, Chats und Kontaktanfragen erstellt wurden.</p>
                </div>
            </div>

            {{-- Source Distribution --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-globe-alt class="w-5 h-5 text-purple-500" />
                        Herkunft & Kanal
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="sourceChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-purple-400 uppercase text-[10px] tracking-widest block mb-1">Kanal Verteilung</strong>Prozentuale Aufschlüsselung der eingehenden Support-Kanäle.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Ticket Status --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-ticket class="w-5 h-5 text-emerald-500" />
                        Ticket Status
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="ticketStatusChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-emerald-400 uppercase text-[10px] tracking-widest block mb-1">Abwicklung</strong>Verteilung der Tickets nach ihrem aktuellen Bearbeitungsstatus.</p>
                </div>
            </div>

            {{-- Chat Status --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-rose-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-chat-bubble-oval-left-ellipsis class="w-5 h-5 text-rose-500" />
                        Chat Status
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="chatStatusChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-rose-400 uppercase text-[10px] tracking-widest block mb-1">Live Interaktion</strong>Prüft, ob Chat-Gespräche gelöst sind oder noch eingreifen erfordern.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Chat Rating --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-star class="w-5 h-5 text-amber-500" />
                        Chat Bewertungen
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="chatRatingChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-amber-400 uppercase text-[10px] tracking-widest block mb-1">KI Zufriedenheit</strong>Zeigt an, wie Kunden den Chat mit dem Support bewertet haben (Sterne).</p>
                </div>
            </div>

            {{-- Ticket Rating --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-star class="w-5 h-5 text-amber-500" />
                        Ticket Bewertungen
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="ticketRatingChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-amber-400 uppercase text-[10px] tracking-widest block mb-1">Support Zufriedenheit</strong>Zeigt an, wie Kunden klassische Support-Tickets bewertet haben.</p>
                </div>
            </div>
        </div>

        {{-- Dashboard Scripts --}}
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('supportDashboard', () => {
                    let volumeChartObj = null;
                    let sourceChartObj = null;
                    let ticketStatusChartObj = null;
                    let chatStatusChartObj = null;
                    let chatRatingChartObj = null;
                    let ticketRatingChartObj = null;

                    return {
                        getPayload() {
                            const el = document.getElementById('analytics-data-bridge');
                            return {
                                volume: JSON.parse(el.getAttribute('data-volume')),
                                source: JSON.parse(el.getAttribute('data-source')),
                                ticketStatus: JSON.parse(el.getAttribute('data-ticketstatus')),
                                chatStatus: JSON.parse(el.getAttribute('data-chatstatus')),
                                chatRating: JSON.parse(el.getAttribute('data-chatrating')),
                                ticketRating: JSON.parse(el.getAttribute('data-ticketrating'))
                            };
                        },

                        initCharts() {
                            const data = this.getPayload();
                            const gridOptions = { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false };
                            const gridOptionsX = { display: false, drawBorder: false };

                            // 1. Volume Growth
                            const ctxVol = document.getElementById('volumeChart').getContext('2d');
                            volumeChartObj = new Chart(ctxVol, {
                                type: 'line',
                                data: {
                                    labels: data.volume.labels,
                                    datasets: [{
                                        label: 'Support Volumen',
                                        data: data.volume.data,
                                        borderColor: 'rgba(6, 182, 212, 1)',
                                        backgroundColor: 'rgba(6, 182, 212, 0.1)',
                                        borderWidth: 2, tension: 0.4, fill: true,
                                        pointBackgroundColor: 'rgba(6, 182, 212, 1)', pointBorderColor: '#fff',
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
                                    plugins: { legend: { display: false } }
                                }
                            });

                            // 2. Source Distribution
                            const ctxSrc = document.getElementById('sourceChart').getContext('2d');
                            sourceChartObj = new Chart(ctxSrc, {
                                type: 'doughnut',
                                data: {
                                    labels: data.source.labels,
                                    datasets: [{
                                        data: data.source.data,
                                        backgroundColor: ['#10b981', '#f59e0b', '#8b5cf6'],
                                        borderWidth: 2, borderColor: '#1f2937'
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: { legend: { position: 'right', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                                    cutout: '60%'
                                }
                            });

                            // 3. Ticket Status
                            const ctxTick = document.getElementById('ticketStatusChart').getContext('2d');
                            ticketStatusChartObj = new Chart(ctxTick, {
                                type: 'doughnut',
                                data: {
                                    labels: data.ticketStatus.labels,
                                    datasets: [{
                                        data: data.ticketStatus.data,
                                        backgroundColor: ['#6366f1', '#10b981', '#ef4444', '#f59e0b', '#8b5cf6', '#06b6d4', '#ec4899', '#14b8a6'],
                                        borderWidth: 2, borderColor: '#1f2937'
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: { legend: { position: 'right', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                                    cutout: '60%'
                                }
                            });

                            // 4. Chat Status
                            const ctxChat = document.getElementById('chatStatusChart').getContext('2d');
                            chatStatusChartObj = new Chart(ctxChat, {
                                type: 'doughnut',
                                data: {
                                    labels: data.chatStatus.labels,
                                    datasets: [{
                                        data: data.chatStatus.data,
                                        backgroundColor: ['#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#10b981', '#6366f1'],
                                        borderWidth: 2, borderColor: '#1f2937'
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: { legend: { position: 'right', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                                    cutout: '60%'
                                }
                            });

                            // 5. Chat Rating
                            const ctxRating = document.getElementById('chatRatingChart').getContext('2d');
                            chatRatingChartObj = new Chart(ctxRating, {
                                type: 'doughnut',
                                data: {
                                    labels: data.chatRating.labels,
                                    datasets: [{
                                        data: data.chatRating.data,
                                        backgroundColor: ['#eab308', '#f59e0b', '#fbbf24', '#fcd34d', '#fef3c7'],
                                        borderWidth: 2, borderColor: '#1f2937'
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: { legend: { position: 'right', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                                    cutout: '60%'
                                }
                            });

                            // 6. Ticket Rating
                            const ctxTicketRating = document.getElementById('ticketRatingChart').getContext('2d');
                            ticketRatingChartObj = new Chart(ctxTicketRating, {
                                type: 'doughnut',
                                data: {
                                    labels: data.ticketRating.labels,
                                    datasets: [{
                                        data: data.ticketRating.data,
                                        backgroundColor: ['#eab308', '#f59e0b', '#fbbf24', '#fcd34d', '#fef3c7'],
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
                                { obj: volumeChartObj, src: data.volume },
                                { obj: sourceChartObj, src: data.source },
                                { obj: ticketStatusChartObj, src: data.ticketStatus },
                                { obj: chatStatusChartObj, src: data.chatStatus },
                                { obj: chatRatingChartObj, src: data.chatRating },
                                { obj: ticketRatingChartObj, src: data.ticketRating }
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

</div>

<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div>
    {{-- Dashboard Scripts registered globally via analytics-dashboards.js --}}

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
            <h4 class="text-[10px] uppercase font-black tracking-widest text-[var(--theme-color)] mb-1">Offene Tickets</h4>
            <p class="text-2xl font-serif text-white">{{ $kpiTicketsOpen }}</p>
        </div>
        <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-4 shadow-xl">
            <h4 class="text-[10px] uppercase font-black tracking-widest text-[var(--theme-color)] mb-1">Gelöste Tickets</h4>
            <p class="text-2xl font-serif text-white">{{ $kpiTicketsClosed }}</p>
        </div>
        <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-4 shadow-xl">
            <h4 class="text-[10px] uppercase font-black tracking-widest text-[var(--theme-color)] mb-1">Ø Ticket Bewertung</h4>
            <p class="text-2xl font-serif text-white">{{ number_format($kpiAvgTicketRating, 1, ',', '.') }}<span class="text-[var(--theme-color)] text-sm ml-1">★</span></p>
        </div>
        <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-4 shadow-xl">
            <h4 class="text-[10px] uppercase font-black tracking-widest text-[var(--theme-color)] mb-1">Ø Lösungszeit</h4>
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
             data-theme-color="{{ $this->themeColorHex }}"
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
                <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color)]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-chat-bubble-left-right class="w-5 h-5 text-[var(--theme-color)]" />
                        Support Aufkommen (Gesamt)
                    </h3>
                    <div class="relative h-64 w-full mt-4" wire:ignore>
                        <canvas id="volumeChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-[var(--theme-color)] uppercase text-[10px] tracking-widest block mb-1">Ticket Volumen</strong>Zeigt an, wie viele neue Tickets, Chats und Kontaktanfragen erstellt wurden.</p>
                </div>
            </div>

            {{-- Source Distribution --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color)]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-globe-alt class="w-5 h-5 text-[var(--theme-color)]" />
                        Herkunft & Kanal
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="sourceChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-[var(--theme-color)] uppercase text-[10px] tracking-widest block mb-1">Kanal Verteilung</strong>Prozentuale Aufschlüsselung der eingehenden Support-Kanäle.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Ticket Status --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color)]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-ticket class="w-5 h-5 text-[var(--theme-color)]" />
                        Ticket Status
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="ticketStatusChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-[var(--theme-color)] uppercase text-[10px] tracking-widest block mb-1">Abwicklung</strong>Verteilung der Tickets nach ihrem aktuellen Bearbeitungsstatus.</p>
                </div>
            </div>

            {{-- Chat Status --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color)]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-chat-bubble-oval-left-ellipsis class="w-5 h-5 text-[var(--theme-color)]" />
                        Chat Status
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="chatStatusChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-[var(--theme-color)] uppercase text-[10px] tracking-widest block mb-1">Live Interaktion</strong>Prüft, ob Chat-Gespräche gelöst sind oder noch eingreifen erfordern.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Chat Rating --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color)]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-star class="w-5 h-5 text-[var(--theme-color)]" />
                        Chat Bewertungen
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="chatRatingChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-[var(--theme-color)] uppercase text-[10px] tracking-widest block mb-1">KI Zufriedenheit</strong>Zeigt an, wie Kunden den Chat mit dem Support bewertet haben (Sterne).</p>
                </div>
            </div>

            {{-- Ticket Rating --}}
            <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700 p-6 shadow-xl relative overflow-hidden group flex flex-col justify-between">
                <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color)]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div>
                    <h3 class="text-white text-lg font-serif font-semibold drop-shadow-sm flex items-center gap-2">
                        <x-heroicon-o-star class="w-5 h-5 text-[var(--theme-color)]" />
                        Ticket Bewertungen
                    </h3>
                    <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="ticketRatingChart"></canvas>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700/50">
                    <p class="text-xs text-gray-400 mb-1"><strong class="text-[var(--theme-color)] uppercase text-[10px] tracking-widest block mb-1">Support Zufriedenheit</strong>Zeigt an, wie Kunden klassische Support-Tickets bewertet haben.</p>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

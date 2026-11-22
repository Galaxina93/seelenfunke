<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-50: {{ $this->themeColorHex }}80;">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-8">

        {{-- Header & Controls --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-gray-900/80 backdrop-blur-md p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-xl">
            <div class="flex items-center gap-3">
                <span class="p-2.5 rounded-xl border border-[var(--theme-color-20)] bg-[var(--theme-color-10)] text-[var(--theme-color)]">
                    <x-heroicon-o-lifebuoy class="w-6 h-6" />
                </span>
                <h1 class="text-2xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    Support Analyse
                    <x-heroicon-o-information-circle
                        class="w-5 h-5 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors"
                        title="Auswertung aller Kundenanfragen über Tickets, KI-Chats und das Kontaktformular inklusive Lösungsdauer und Zufriedenheits-Ratings." />
                </h1>
            </div>

            <div class="flex items-center gap-3">
                <label for="supportDateRangeSelect" class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Zeitraum:</label>
                <select id="supportDateRangeSelect" wire:model.live="dateRange" class="bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white text-sm rounded-xl focus:ring-2 focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)] block px-4 py-2 transition-colors cursor-pointer">
                    <option value="7">Letzte 7 Tage</option>
                    <option value="30">Letzte 30 Tage</option>
                    <option value="90">Letzte 90 Tage</option>
                    <option value="365">Letztes Jahr</option>
                    <option value="all">Gesamte Zeit</option>
                </select>
            </div>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1.5">
                        Offene Tickets
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Noch nicht abgeschlossene Tickets im gewählten Zeitraum (Offen, In Bearbeitung, Kundenantwort, Wartend)." />
                    </p>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $kpiTicketsOpen }}</div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <x-heroicon-o-inbox class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1.5">
                        Gelöste Tickets
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Abgeschlossene oder gelöste Tickets im gewählten Zeitraum." />
                    </p>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $kpiTicketsClosed }}</div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                    <x-heroicon-o-check-circle class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1.5">
                        Ø Lösungsdauer
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Durchschnittliche Bearbeitungszeit von Ticketerstellung bis Abschluss in Stunden." />
                    </p>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($kpiAvgResolutionHours, 1, ',', '.') }} <span class="text-sm font-normal text-gray-500 dark:text-gray-400">Std.</span></div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center text-blue-600 dark:text-blue-400">
                    <x-heroicon-o-clock class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1.5">
                        Ø Ticket-CSAT
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Durchschnittliche Sterne-Bewertung abgeschlossener Support-Tickets (KI-Chat Ø: {{ $kpiAvgChatRating > 0 ? number_format($kpiAvgChatRating, 1, ',', '.') . '/5' : 'k. A.' }})." />
                    </p>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white flex items-baseline gap-1">
                        {{ $kpiAvgTicketRating > 0 ? number_format($kpiAvgTicketRating, 1, ',', '.') : '0,0' }}
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">/ 5</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-[var(--theme-color-10)] flex items-center justify-center text-[var(--theme-color)]">
                    <x-heroicon-s-star class="w-6 h-6" />
                </div>
            </div>
        </div>

        {{-- Charts Grid --}}
        <div x-data="supportDashboard()" x-init="initCharts()" @analytics-updated.window="updateCharts()" @theme-changed.window="initCharts()" class="space-y-6">

            <div class="hidden" id="analytics-data-bridge"
                 data-theme-color="{{ $this->themeColorHex }}"
                 data-volume='@json($volumeData)'
                 data-source='@json($sourceData)'
                 data-ticketstatus='@json($ticketStatusData)'
                 data-chatstatus='@json($chatStatusData)'
                 data-chatrating='@json($chatRatingData)'
                 data-ticketrating='@json($ticketRatingData)'></div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Volume Growth --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-presentation-chart-line class="w-5 h-5 text-[var(--theme-color)]" />
                                Support-Volumen im Verlauf
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Gesamte eingehende Kundeninteraktionen über Tickets, KI-Chats und Kontaktformular im Zeitverlauf." />
                        </h3>
                        <div class="relative h-64 w-full mt-4" wire:ignore>
                            <canvas id="volumeChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Source Distribution --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-arrows-right-left class="w-5 h-5 text-[var(--theme-color)]" />
                                Kontaktkanäle
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Anteil der genutzten Kommunikationskanäle (Tickets, Funki KI-Chats, Kontaktformular und Feedback)." />
                        </h3>
                        <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                            <canvas id="sourceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Ticket Status --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-ticket class="w-5 h-5 text-[var(--theme-color)]" />
                                Ticket-Status
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Verteilung der Support-Tickets nach aktuellem Bearbeitungsstatus." />
                        </h3>
                        <div class="relative h-56 w-full mt-4 flex items-center justify-center" wire:ignore>
                            <canvas id="ticketStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Chat Status --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-chat-bubble-left-right class="w-5 h-5 text-[var(--theme-color)]" />
                                KI-Chat-Auflösung
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Anteil der autonom durch Funki gelösten Chats gegenüber Übergaben an Mitarbeiter." />
                        </h3>
                        <div class="relative h-56 w-full mt-4 flex items-center justify-center" wire:ignore>
                            <canvas id="chatStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Chat Ratings --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-star class="w-5 h-5 text-[var(--theme-color)]" />
                                Chat-Sterne
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Kundenbewertungen (1 bis 5 Sterne) nach Abschluss eines KI-Support-Chats." />
                        </h3>
                        <div class="relative h-56 w-full mt-4 flex items-center justify-center" wire:ignore>
                            <canvas id="chatRatingChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Ticket Ratings --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-s-star class="w-5 h-5 text-[var(--theme-color)]" />
                                Ticket-Sterne
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Kundenbewertungen (1 bis 5 Sterne) nach Abschluss eines Support-Tickets." />
                        </h3>
                        <div class="relative h-56 w-full mt-4 flex items-center justify-center" wire:ignore>
                            <canvas id="ticketRatingChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

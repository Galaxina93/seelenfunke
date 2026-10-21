<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="space-y-6">

    {{-- 1. ZENTRALER KOPFBEREICH MIT ZEITRAUM-FILTER --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl sm:rounded-3xl p-5 sm:p-6 lg:p-7 shadow-sm transition-colors duration-300">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                        <x-heroicon-o-cube class="w-3.5 h-3.5" />
                        Produkt-Telemetrie & Analytics
                    </span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        100% DSGVO-konform (Cookieless)
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">
                    Produkt-, Konfigurator- & Rechner-Analyse
                </h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-2xl">
                    Präzise Auswertungen des Kundenverhaltens im Konfigurator, Funnel-Messung des Mengenrechners und betriebswirtschaftliche Margen-Kalkulation.
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <div class="relative">
                    <label class="sr-only">Zeitraum</label>
                    <select wire:model.live="dateRange" class="block w-full rounded-xl border border-gray-200 dark:border-gray-700 py-2 pl-3 pr-9 text-xs font-bold text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-800 shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] transition-all cursor-pointer">
                        <option value="7">Letzte 7 Tage</option>
                        <option value="30">Letzte 30 Tage</option>
                        <option value="90">Letzte 90 Tage</option>
                        <option value="365">Letztes Jahr</option>
                        <option value="all">Gesamte Historie</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- SUB-TAB NAVIGATION --}}
        <div class="flex items-center gap-2 mt-6 pt-5 border-t border-gray-100 dark:border-gray-800 overflow-x-auto custom-scrollbar">
            <button type="button"
                    wire:click="setTab('configurator')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all cursor-pointer whitespace-nowrap {{ $activeTab === 'configurator' ? 'bg-[var(--theme-color)] text-white shadow-md shadow-[var(--theme-color-30)]' : 'bg-gray-100 dark:bg-gray-800/80 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                <x-heroicon-o-adjustments-horizontal class="w-4 h-4" />
                <span>Produktkonfigurator</span>
                @if(($configuratorStats['views'] ?? 0) > 0)
                    <span class="ml-1 px-1.5 py-0.5 rounded-md text-[10px] {{ $activeTab === 'configurator' ? 'bg-white/20 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                        {{ $configuratorStats['views'] }}
                    </span>
                @endif
            </button>

            <button type="button"
                    wire:click="setTab('calculator')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all cursor-pointer whitespace-nowrap {{ $activeTab === 'calculator' ? 'bg-[var(--theme-color)] text-white shadow-md shadow-[var(--theme-color-30)]' : 'bg-gray-100 dark:bg-gray-800/80 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                <x-heroicon-o-calculator class="w-4 h-4" />
                <span>Mengenrechner (/calculator)</span>
                @if(($calculatorFunnel['views'] ?? 0) > 0)
                    <span class="ml-1 px-1.5 py-0.5 rounded-md text-[10px] {{ $activeTab === 'calculator' ? 'bg-white/20 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                        {{ $calculatorFunnel['views'] }}
                    </span>
                @endif
            </button>

            <button type="button"
                    wire:click="setTab('economics')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all cursor-pointer whitespace-nowrap {{ $activeTab === 'economics' ? 'bg-[var(--theme-color)] text-white shadow-md shadow-[var(--theme-color-30)]' : 'bg-gray-100 dark:bg-gray-800/80 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                <x-heroicon-o-chart-bar class="w-4 h-4" />
                <span>Wirtschaftlichkeit & LUCID</span>
            </button>
        </div>
    </div>


    {{-- ========================================================================= --}}
    {{-- TAB 1: PRODUKTKONFIGURATOR ANALYTICS --}}
    {{-- ========================================================================= --}}
    @if($activeTab === 'configurator')
        <div class="space-y-6 animate-fade-in-up">

            {{-- 4 KPI CARDS --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- KPI 1: Aufrufe & Conversion --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Konfigurator Conversion</span>
                        <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center">
                            <x-heroicon-o-shopping-bag class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-serif font-black text-gray-900 dark:text-white mb-1">
                        {{ $configuratorStats['conversion_rate'] ?? 0 }} %
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center justify-between">
                        <span>{{ $configuratorStats['saved'] ?? 0 }} in den Warenkorb</span>
                        <span class="font-bold text-gray-700 dark:text-gray-300">bei {{ $configuratorStats['views'] ?? 0 }} Aufrufen</span>
                    </div>
                </div>

                {{-- KPI 2: Vorlage vs. Selbst --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Vorlagen-Quote</span>
                        <div class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center">
                            <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-serif font-black text-gray-900 dark:text-white mb-1">
                        {{ $configuratorModeData['template_percent'] ?? 0 }} %
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center justify-between">
                        <span>{{ $configuratorModeData['template_count'] ?? 0 }} x Vorlage</span>
                        <span class="text-gray-400">vs. {{ $configuratorModeData['custom_count'] ?? 0 }} x Selbst</span>
                    </div>
                </div>

                {{-- KPI 3: Express Zuschlag --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Eiliges Geschenk (Express)</span>
                        <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center">
                            <x-heroicon-o-bolt class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-serif font-black text-gray-900 dark:text-white mb-1">
                        {{ $expressStats['attach_rate'] ?? 0 }} %
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center justify-between">
                        <span>{{ $expressStats['completed_with_express'] ?? 0 }} x gebucht</span>
                        <span class="font-bold text-amber-600 dark:text-amber-400">+{{ number_format($expressStats['revenue_euros'] ?? 0, 2, ',', '.') }} €</span>
                    </div>
                </div>

                {{-- KPI 4: Gestaltungselemente --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Gestaltungselemente</span>
                        <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                            <x-heroicon-o-sparkles class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-serif font-black text-gray-900 dark:text-white mb-1">
                        {{ ($configuratorStats['symbols_used'] ?? 0) + ($uploadStats['total'] ?? 0) }}
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center justify-between">
                        <span>{{ $configuratorStats['symbols_used'] ?? 0 }} Motive</span>
                        <span>{{ $uploadStats['total'] ?? 0 }} Uploads</span>
                    </div>
                </div>
            </div>

            {{-- GRID ROW 1: MODUS-VERHÄLTNIS & VORLAGEN HITLISTE --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Modus-Auswahl & Upload-Formate --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <x-heroicon-o-swatch class="w-4 h-4 text-[var(--theme-color)]" />
                                Einstiegs-Entscheidung
                            </h3>
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ $configuratorModeData['total'] ?? 0 }} Klicks</span>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">
                            Wie oft Kunden eine fertige Design-Vorlage wählen vs. komplett bei Null auf dem Holzartikel starten.
                        </p>

                        {{-- Visueller Balken --}}
                        <div class="space-y-4 mb-6">
                            <div>
                                <div class="flex justify-between text-xs font-bold mb-1.5">
                                    <span class="flex items-center gap-1.5 text-purple-600 dark:text-purple-400">
                                        <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> Vorlage nutzen
                                    </span>
                                    <span class="text-gray-800 dark:text-gray-200">{{ $configuratorModeData['template_count'] ?? 0 }} ({{ $configuratorModeData['template_percent'] ?? 0 }}%)</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-3 overflow-hidden">
                                    <div class="bg-purple-500 h-3 rounded-full transition-all duration-500" style="width: {{ $configuratorModeData['template_percent'] ?? 0 }}%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-xs font-bold mb-1.5">
                                    <span class="flex items-center gap-1.5 text-blue-600 dark:text-blue-400">
                                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Selbst konfigurieren
                                    </span>
                                    <span class="text-gray-800 dark:text-gray-200">{{ $configuratorModeData['custom_count'] ?? 0 }} ({{ $configuratorModeData['custom_percent'] ?? 0 }}%)</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-3 overflow-hidden">
                                    <div class="bg-blue-500 h-3 rounded-full transition-all duration-500" style="width: {{ $configuratorModeData['custom_percent'] ?? 0 }}%"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Upload-Formate Breakdown --}}
                        <div class="pt-5 border-t border-gray-100 dark:border-gray-800">
                            <h4 class="text-xs font-black uppercase tracking-widest text-gray-500 mb-3 flex items-center justify-between">
                                <span>Eigene Datei-Uploads</span>
                                <span class="text-emerald-500 font-bold">{{ $uploadStats['total'] ?? 0 }} Gesamt</span>
                            </h4>
                            <div class="grid grid-cols-5 gap-2 text-center">
                                @foreach($uploadStats['by_format'] ?? [] as $fmt => $fCount)
                                    <div class="p-2 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800">
                                        <div class="text-[9px] font-black uppercase text-gray-400">{{ strtoupper($fmt) }}</div>
                                        <div class="text-sm font-bold text-gray-900 dark:text-white mt-0.5">{{ $fCount }}</div>
                                    </div>
                                @endforeach
                            </div>
                            @if(($uploadStats['errors'] ?? 0) > 0)
                                <div class="mt-3 text-[10px] text-red-500 flex items-center gap-1 font-bold">
                                    <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5" />
                                    {{ $uploadStats['errors'] }} fehlgeschlagene Uploads (Datei zu groß oder falsches Format)
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800 text-[11px] text-gray-500 dark:text-gray-400">
                        💡 <strong>Tipp:</strong> Wenn über 60% Vorlagen wählen, lohnt sich der Ausbau weiterer Vorlagen für saisonale Anlässe (Muttertag, Hochzeit).
                    </div>
                </div>

                {{-- Vorlagen Hitliste (2 Spalten breit) --}}
                <div class="lg:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <x-heroicon-o-fire class="w-4 h-4 text-amber-500" />
                                Top Vorlagen-Hitliste
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Welche Vorlagen am häufigsten als Gestaltungsgrundlage ausgewählt wurden.
                            </p>
                        </div>
                        <a href="{{ route('admin.product-templates') }}" class="text-xs font-bold text-[var(--theme-color)] hover:underline flex items-center gap-1">
                            Vorlagen verwalten →
                        </a>
                    </div>

                    @if(count($topTemplates) > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                <tr class="text-[9px] font-black uppercase tracking-widest text-gray-400 border-b border-gray-100 dark:border-gray-800">
                                    <th class="py-2.5 pl-2 w-10">Rang</th>
                                    <th class="py-2.5">Vorlage</th>
                                    <th class="py-2.5">Zugeordnetes Produkt</th>
                                    <th class="py-2.5 text-right pr-2">Gewählt</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60">
                                @foreach($topTemplates as $idx => $tpl)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                                        <td class="py-3 pl-2">
                                            <span class="w-6 h-6 rounded-lg flex items-center justify-center text-[10px] font-black {{ $idx === 0 ? 'bg-amber-500 text-gray-950 shadow-sm' : ($idx === 1 ? 'bg-slate-300 text-gray-900' : ($idx === 2 ? 'bg-amber-700/20 text-amber-700' : 'text-gray-400')) }}">
                                                #{{ $idx + 1 }}
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <div class="flex items-center gap-3">
                                                @if(!empty($tpl['preview_image']))
                                                    <img src="{{ $tpl['preview_image'] }}" alt="{{ $tpl['name'] }}" class="w-9 h-9 rounded-lg object-cover bg-gray-100 dark:bg-gray-800 shrink-0 border border-gray-200 dark:border-gray-700">
                                                @else
                                                    <div class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-400 flex items-center justify-center shrink-0">
                                                        <x-heroicon-o-photo class="w-4 h-4" />
                                                    </div>
                                                @endif
                                                <div class="font-bold text-gray-900 dark:text-gray-100 line-clamp-1">
                                                    {{ $tpl['name'] }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 text-gray-500 dark:text-gray-400">
                                            {{ $tpl['product_name'] }}
                                        </td>
                                        <td class="py-3 text-right pr-2">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-black bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20">
                                                {{ $tpl['count'] }} x
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12 text-gray-400 text-xs">
                            <x-heroicon-o-clipboard-document-list class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700 mb-2" />
                            Noch keine Vorlagenauswahlen im gewählten Zeitraum erfasst. Sobald Kunden eine Vorlage anklicken, erscheint sie hier.
                        </div>
                    @endif
                </div>

            </div>

            {{-- GRID ROW 2: TOP SYMBOLE / MOTIVE & EXPRESS DETAILS --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Beliebteste Symbole (2 Spalten breit) --}}
                <div class="lg:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <x-heroicon-o-sparkles class="w-4 h-4 text-[var(--theme-color)]" />
                                Beliebteste Symbole & Gravurmotive
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Welche Icons und Vektoren aus der Symbol-Bibliothek am häufigsten auf das Holz graviert werden.
                            </p>
                        </div>
                        <span class="text-xs font-black text-gray-400">
                            {{ $configuratorStats['symbols_used'] ?? 0 }} Gravuren
                        </span>
                    </div>

                    @if(count($topSymbols) > 0)
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                            @foreach($topSymbols as $sym)
                                <div class="bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700/60 rounded-2xl p-3 flex flex-col items-center text-center group hover:border-[var(--theme-color)] hover:shadow-md transition-all">
                                    <div class="w-12 h-12 rounded-xl bg-white dark:bg-gray-800 flex items-center justify-center p-2 mb-2 border border-gray-100 dark:border-gray-700 group-hover:scale-110 transition-transform">
                                        <img src="{{ asset('shop/product/configurator/vectors/' . $sym['file']) }}"
                                             alt="{{ $sym['name'] }}"
                                             onerror="this.style.display='none'"
                                             class="w-full h-full object-contain filter dark:invert">
                                    </div>
                                    <div class="text-[11px] font-bold text-gray-800 dark:text-gray-200 line-clamp-1 w-full" title="{{ $sym['name'] }}">
                                        {{ $sym['name'] }}
                                    </div>
                                    <div class="mt-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-[var(--theme-color-10)] text-[var(--theme-color)]">
                                        {{ $sym['count'] }} x
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12 text-gray-400 text-xs">
                            <x-heroicon-o-sparkles class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700 mb-2" />
                            Noch keine Symbol-Verwendungen im gewählten Zeitraum protokolliert.
                        </div>
                    @endif
                </div>

                {{-- Express Zuschlag & Funnel Insights --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <x-heroicon-o-bolt class="w-4 h-4 text-amber-500" />
                                Express-Performance
                            </h3>
                            <span class="text-[9px] font-black uppercase tracking-widest text-amber-600 bg-amber-500/10 px-2.5 py-1 rounded-full border border-amber-500/20">
                                🚀 Eiliges Geschenk
                            </span>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">
                            Analyse der Buchungsquote des Express-Zuschlags im Konfigurator.
                        </p>

                        <div class="space-y-4">
                            <div class="p-4 rounded-2xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40">
                                <div class="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-400 mb-1">
                                    Express-Umsatz
                                </div>
                                <div class="text-2xl font-black text-amber-600 dark:text-amber-300">
                                    + {{ number_format($expressStats['revenue_euros'] ?? 0, 2, ',', '.') }} €
                                </div>
                                <div class="text-[10px] text-amber-700/80 dark:text-amber-400/80 mt-1">
                                    Reiner Zusatzgewinn durch Vorzugsproduktion
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-center">
                                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800">
                                    <div class="text-[9px] font-black uppercase text-gray-400">Aktiviert</div>
                                    <div class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">{{ $expressStats['enabled_clicks'] ?? 0 }} x</div>
                                </div>
                                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800">
                                    <div class="text-[9px] font-black uppercase text-gray-400">Bestellt</div>
                                    <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $expressStats['completed_with_express'] ?? 0 }} x</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800 text-[11px] text-gray-500 dark:text-gray-400">
                        ⚡ <strong>Hinweis:</strong> Die Express-Option wird bei hoher Werkstatt-Auslastung (Shop-Kapazität Stufe 2) automatisch deaktiviert, um Lieferversprechen einzuhalten.
                    </div>
                </div>

            </div>

        </div>
    @endif


    {{-- ========================================================================= --}}
    {{-- TAB 2: MENGENRECHNER (/calculator) FUNNEL --}}
    {{-- ========================================================================= --}}
    @if($activeTab === 'calculator')
        <div class="space-y-6 animate-fade-in-up">

            {{-- 4 KPI CARDS --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Aufrufe --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Rechner-Besucher</span>
                        <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center">
                            <x-heroicon-o-eye class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-serif font-black text-gray-900 dark:text-white mb-1">
                        {{ $calculatorFunnel['views'] ?? 0 }}
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                        Aufrufe der Seite /calculator
                    </div>
                </div>

                {{-- Einstiegsquote --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Einstiegs-Quote (AGB)</span>
                        <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                            <x-heroicon-o-check-badge class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-serif font-black text-gray-900 dark:text-white mb-1">
                        {{ ($calculatorFunnel['views'] ?? 0) > 0 ? round((($calculatorFunnel['started'] ?? 0) / $calculatorFunnel['views']) * 100, 1) : 0 }} %
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                        {{ $calculatorFunnel['started'] ?? 0 }} Kunden starteten Stufe 1
                    </div>
                </div>

                {{-- Abgesendete Anfragen --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Preisanfragen (Leads)</span>
                        <div class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center">
                            <x-heroicon-o-paper-airplane class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-serif font-black text-gray-900 dark:text-white mb-1">
                        {{ $calculatorStats['quotes_count'] ?? 0 }}
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center justify-between">
                        <span>Lead-Rate: {{ $calculatorStats['overall_conversion'] ?? 0 }}%</span>
                        <span class="font-bold text-purple-600 dark:text-purple-400">Erfolgreich</span>
                    </div>
                </div>

                {{-- Anfragevolumen --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Anfrage-Volumen</span>
                        <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center">
                            <x-heroicon-o-banknotes class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-serif font-black text-amber-600 dark:text-amber-400 mb-1">
                        {{ number_format($calculatorStats['total_volume_euros'] ?? 0, 2, ',', '.') }} €
                    </div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                        ø {{ number_format($calculatorStats['avg_quote_euros'] ?? 0, 2, ',', '.') }} € je Anfrage
                    </div>
                </div>
            </div>

            {{-- 5-STUFEN TRICHTER (FUNNEL) --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 lg:p-8 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
                    <div>
                        <h3 class="text-lg font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-heroicon-o-arrow-trending-down class="w-5 h-5 text-[var(--theme-color)]" />
                            5-Stufen-Trichter: Wo springen Interessenten ab?
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Lückenlose Verfolgung jedes Einzelschritts von der Startseite bis zur versendeten Angebotsanfrage.
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                        Trichter-Tracking
                    </span>
                </div>

                {{-- Funnel Steps Visualizer --}}
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    {{-- Stufe 0 --}}
                    <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 relative flex flex-col justify-between">
                        <div>
                            <div class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Stufe 0</div>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white mb-2">1. Aufruf / Intro</h4>
                            <div class="text-2xl font-black text-gray-900 dark:text-white">{{ $calculatorFunnel['views'] ?? 0 }}</div>
                            <div class="text-[10px] text-gray-500 mt-1">Disclaimer & AGB</div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700/60 text-[10px] text-gray-500">
                            Absprung: <strong class="text-rose-500">{{ $calculatorDropOffs['step0_to_1'] ?? 0 }}%</strong>
                        </div>
                    </div>

                    {{-- Stufe 1 --}}
                    <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 relative flex flex-col justify-between">
                        <div>
                            <div class="text-[9px] font-black uppercase tracking-widest text-blue-500 mb-1">Stufe 1</div>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white mb-2">2. Rechner aktiv</h4>
                            <div class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ $calculatorFunnel['started'] ?? 0 }}</div>
                            <div class="text-[10px] text-gray-500 mt-1">Produktauswahl</div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700/60 text-[10px] text-gray-500">
                            Absprung: <strong class="text-rose-500">{{ $calculatorDropOffs['step1_to_2'] ?? 0 }}%</strong>
                        </div>
                    </div>

                    {{-- Stufe 2 --}}
                    <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 relative flex flex-col justify-between">
                        <div>
                            <div class="text-[9px] font-black uppercase tracking-widest text-purple-500 mb-1">Stufe 2</div>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white mb-2">3. Konfiguriert</h4>
                            <div class="text-2xl font-black text-purple-600 dark:text-purple-400">{{ $calculatorFunnel['items_saved'] ?? 0 }}</div>
                            <div class="text-[10px] text-gray-500 mt-1">Artikel im Rechner-Cart</div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700/60 text-[10px] text-gray-500">
                            Absprung: <strong class="text-rose-500">{{ $calculatorDropOffs['step2_to_3'] ?? 0 }}%</strong>
                        </div>
                    </div>

                    {{-- Stufe 3 --}}
                    <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 relative flex flex-col justify-between">
                        <div>
                            <div class="text-[9px] font-black uppercase tracking-widest text-amber-500 mb-1">Stufe 3</div>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white mb-2">4. Kontaktdaten</h4>
                            <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ $calculatorFunnel['form_reached'] ?? 0 }}</div>
                            <div class="text-[10px] text-gray-500 mt-1">Formular geöffnet</div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700/60 text-[10px] text-gray-500">
                            Absprung: <strong class="text-rose-500">{{ $calculatorDropOffs['step3_to_4'] ?? 0 }}%</strong>
                        </div>
                    </div>

                    {{-- Stufe 4 --}}
                    <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-300 dark:border-emerald-800 relative flex flex-col justify-between">
                        <div>
                            <div class="text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400 mb-1">Stufe 4</div>
                            <h4 class="text-xs font-bold text-emerald-900 dark:text-emerald-300 mb-2">5. Lead / Angebot</h4>
                            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $calculatorFunnel['quotes_count'] ?? 0 }}</div>
                            <div class="text-[10px] text-emerald-700 dark:text-emerald-500 mt-1">Anfrage erfolgreich</div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-emerald-200 dark:border-emerald-800 text-[10px] text-emerald-700 dark:text-emerald-400 font-bold">
                            Ziel erreicht 🎉
                        </div>
                    </div>
                </div>

                {{-- ABBRUCH-ANALYSE & FEHLERQUELLEN --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6 pt-6 border-t border-gray-100 dark:border-gray-800">
                    <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700">
                        <h4 class="text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-1.5">
                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-500" />
                            Bekannte Hürden & Fehler
                        </h4>
                        <ul class="text-xs space-y-2 text-gray-600 dark:text-gray-400">
                            <li class="flex items-center justify-between">
                                <span>Formular-Validierungsfehler (Pflichtfelder):</span>
                                <strong class="text-gray-900 dark:text-white">{{ $calculatorDropOffs['validation_errors'] ?? 0 }} x</strong>
                            </li>
                            <li class="flex items-center justify-between">
                                <span>Konfiguration vor Speichern abgebrochen:</span>
                                <strong class="text-gray-900 dark:text-white">{{ $calculatorDropOffs['canceled_items'] ?? 0 }} x</strong>
                            </li>
                        </ul>
                    </div>

                    <div class="p-4 rounded-2xl bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/40">
                        <h4 class="text-xs font-black uppercase tracking-widest text-blue-900 dark:text-blue-300 mb-2 flex items-center gap-1.5">
                            <x-heroicon-o-light-bulb class="w-4 h-4 text-blue-500" />
                            Optimierungs-Empfehlung
                        </h4>
                        <p class="text-xs text-blue-800/80 dark:text-blue-300/80 leading-relaxed">
                            @if(($calculatorDropOffs['step3_to_4'] ?? 0) > 40)
                                Der Absprung beim Kontaktformular (Stufe 3) ist mit {{ $calculatorDropOffs['step3_to_4'] }}% hoch. Prüfe, ob Telefonnummer optional sein kann und hebe hervor, dass das Angebot unverbindlich ist.
                            @elseif(($calculatorDropOffs['step0_to_1'] ?? 0) > 40)
                                {{ $calculatorDropOffs['step0_to_1'] }}% verlassen den Rechner direkt beim AGB-Hinweis. Eine freundlichere, weniger juristische Formulierung kann die Hürde senken.
                            @else
                                Der Mengenrechner weist einen stabilen Funnel auf. Die Weiterleitungsquote in Stufe 4 liegt im gesunden Bereich.
                            @endif
                        </p>
                    </div>
                </div>

            </div>

        </div>
    @endif


    {{-- ========================================================================= --}}
    {{-- TAB 3: WIRTSCHAFTLICHKEIT & LUCID (BESTEHEND) --}}
    {{-- ========================================================================= --}}
    @if($activeTab === 'economics')
        <div class="space-y-6 animate-fade-in-up">

            <!-- TAB 1: COMBINED PERFORMANCE & COSTS -->
            <div class="bg-white dark:bg-gradient-to-br dark:from-gray-900 dark:via-gray-950 dark:to-black rounded-[2rem] p-6 lg:p-8 border border-gray-200 dark:border-[var(--theme-color-20)] dark:shadow-2xl transition-colors duration-300 relative overflow-hidden">
                <div class="hidden dark:block absolute -top-24 -right-24 w-64 h-64 bg-[var(--theme-color-10)] rounded-full blur-3xl pointer-events-none"></div>

                <div class="mb-8 flex justify-between items-start">
                    <div>
                        <h3 class="text-xl flex-col sm:flex-row sm:text-2xl font-serif font-bold text-gray-900 dark:text-white mb-2 flex items-start sm:items-center gap-3">
                            <i class="solar-wallet-money-bold-duotone text-[var(--theme-color)] text-2xl"></i> Produkt-Performance & Wahre Zahlen
                        </h3>
                        <p class="text-[11px] font-medium text-gray-600 dark:text-gray-400 max-w-2xl leading-relaxed">
                            Echte Netto-Margen inklusive Einkauf, Laser und Verpackung kombiniert mit der Verkaufsgeschwindigkeit und Logistik.
                        </p>
                    </div>
                    <button wire:click.prevent="downloadFullReport" class="hidden sm:inline-flex items-center gap-2 bg-[var(--theme-color)] hover:bg-black/90 dark:hover:bg-white text-white dark:text-gray-900 px-6 py-2.5 rounded-full text-[10px] font-black uppercase tracking-widest transition-all dark:shadow-[0_0_20px_var(--theme-color-30)] dark:hover:shadow-[0_0_30px_var(--theme-color-50)] group">
                        <i class="fa-solid fa-file-pdf text-white/80 dark:text-gray-800 transition-colors"></i> Jahresabschlussbericht
                    </button>
                </div>

                <div class="overflow-x-auto border border-gray-200 dark:border-gray-800 rounded-[1.5rem] bg-gray-50/50 dark:bg-gray-950/50 dark:shadow-inner">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead>
                        <tr class="bg-gray-100/80 dark:bg-gray-900/80 border-b border-gray-200 dark:border-gray-800">
                            <th class="p-4 pl-6 text-[9px] font-black uppercase tracking-widest text-gray-500 w-[30%]">Produkt & Bestand</th>
                            <th class="p-4 text-[9px] font-black uppercase tracking-widest text-gray-500 w-[25%]">Logistik & Lieferzeit</th>
                            <th class="p-4 text-[9px] font-black uppercase tracking-widest text-gray-500 w-[20%]">Kostenstruktur</th>
                            <th class="p-4 pr-6 text-[9px] font-black uppercase tracking-widest text-gray-500 text-right w-[25%]">Netto VK & Marge</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800/60">
                        @foreach($combinedData as $cd)
                            <tr class="hover:bg-gray-100/50 dark:hover:bg-gray-900/30 transition-colors">
                                <td class="p-4 pl-6">
                                    <div class="font-bold text-sm text-gray-900 dark:text-gray-200 line-clamp-1 mb-1">{{ $cd['name'] }}</div>
                                    <div class="flex items-center gap-3 mt-2">
                                        <div class="text-[10px] uppercase font-black tracking-widest {{ $cd['stock'] <= 0 ? 'text-red-500 dark:text-red-400 border-red-500/30 dark:border-red-400/30' : 'text-emerald-600 dark:text-emerald-400 border-emerald-600/30 dark:border-emerald-400/30' }} border px-2 py-0.5 rounded-md text-nowrap">
                                            {{ $cd['stock'] }} Auf Lager
                                        </div>
                                        <div class="text-[9px] font-black uppercase tracking-widest text-gray-500 text-nowrap">
                                            {{ $cd['sold_last_30'] }} Sales (30T) &bull; <span class="text-blue-500 dark:text-blue-400">ø {{ $cd['velocity'] }}/Tag</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 align-middle">
                                    <div class="text-xs font-bold text-gray-700 dark:text-gray-300 mb-1 flex items-center gap-1.5"><i class="bi bi-truck text-gray-500"></i> {{ $cd['supplier_name'] }}</div>
                                    <div class="flex items-center gap-2 mt-1 text-[9px] font-black uppercase tracking-widest text-nowrap">
                                        <span class="text-gray-500">{{ $cd['delivery_days'] }} Tage LZ</span>
                                        <span class="text-gray-400 dark:text-gray-700">&bull;</span>
                                        @if($cd['reach_days'] === '∞')
                                            <span class="text-gray-500 dark:text-gray-400">Reichweite: ∞ (Kein Verbrauch)</span>
                                        @elseif($cd['status'] === 'critical' || $cd['status'] === 'warning')
                                            <span class="text-amber-600 dark:text-amber-400">Reichweite: ~{{ $cd['reach_days'] }} T</span>
                                        @else
                                            <span class="text-emerald-600 dark:text-emerald-500">Reichweite: ~{{ $cd['reach_days'] }} T</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="p-4 align-middle">
                                    <div x-data="{ open: false }" class="w-36 select-none">
                                        {{-- Kompakter EK-Trigger-Button --}}
                                        <button type="button"
                                                @click="open = !open"
                                                class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white/70 dark:bg-gray-900/70 hover:bg-gray-100/80 dark:hover:bg-gray-800/80 hover:border-gray-300 dark:hover:border-gray-700 transition-all duration-200 shadow-2xs group cursor-pointer text-left">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 cursor-help" title="Nettopreis im Einkauf (Produktion/Hersteller).">
                                                    EK:
                                                </span>
                                                <span class="text-xs font-black text-gray-900 dark:text-gray-100 font-mono">
                                                    {{ number_format($cd['purchase_price'], 2, ',', '.') }} €
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1 pl-1">
                                                <span class="text-[8px] font-black uppercase tracking-tight px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-400 group-hover:text-[var(--theme-color)] group-hover:bg-[var(--theme-color-10)] transition-colors"
                                                      x-text="open ? '–' : '+5'"></span>
                                                <svg class="w-3 h-3 text-gray-400 group-hover:text-gray-700 dark:group-hover:text-gray-200 transition-transform duration-300 ease-out"
                                                     :class="open ? 'rotate-180 text-[var(--theme-color)]' : ''"
                                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </button>

                                        {{-- Geschmeidig aufklappbare Details --}}
                                        <div x-show="open"
                                             x-cloak
                                             x-collapse.duration.300ms
                                             style="display: none;"
                                             class="mt-1.5 p-2.5 rounded-xl bg-gray-50/90 dark:bg-gray-950/70 border border-gray-200/80 dark:border-gray-800/80 shadow-xs space-y-1.5 backdrop-blur-xs">
                                            <div class="flex justify-between items-center text-[10px] font-black">
                                                <span class="text-gray-500 flex items-center cursor-help" title="Berechnete Stromkosten und Maschinenabnutzung pro Gravur-Minute.">Laser <i class="bi bi-exclamation-circle ml-1 text-[8px] opacity-70"></i>:</span>
                                                <span class="text-amber-600 dark:text-amber-500 font-mono">{{ number_format($cd['laser_cost'], 2, ',', '.') }} €</span>
                                            </div>
                                            <div class="flex justify-between items-center text-[10px] font-black">
                                                <span class="text-gray-500 flex items-center cursor-help" title="Reine Materialkosten für die Verpackung (z.B. der Karton selbst in €).">Material <i class="bi bi-exclamation-circle ml-1 text-[8px] opacity-70"></i>:</span>
                                                <span class="text-emerald-600 dark:text-emerald-500 font-mono">{{ number_format($cd['packaging_cost'], 2, ',', '.') }} €</span>
                                            </div>
                                            <div class="flex justify-between items-center text-[10px] font-black">
                                                <span class="text-gray-500 flex items-center cursor-help" title="Die Portokosten, die für das DHL-Label gezahlt werden.">Versand <i class="bi bi-exclamation-circle ml-1 text-[8px] opacity-70"></i>:</span>
                                                <span class="text-gray-600 dark:text-gray-400 font-mono">{{ number_format($cd['shipping_cost'], 2, ',', '.') }} €</span>
                                            </div>
                                            <div class="flex justify-between items-center text-[10px] font-black">
                                                <span class="text-gray-500 flex items-center cursor-help" title="Geplante Marketing-Kosten ({{ $cd['marketing_percent'] }}% vom Netto).">Marketing <i class="bi bi-exclamation-circle ml-1 text-[8px] opacity-70"></i>:</span>
                                                <span class="text-pink-600 dark:text-pink-500 font-mono">{{ number_format($cd['marketing_cost'], 2, ',', '.') }} €</span>
                                            </div>
                                            <div class="flex justify-between items-center text-[10px] font-black mt-1.5 pt-1.5 border-t border-gray-200 dark:border-gray-800/60">
                                                <span class="text-gray-500 flex items-center cursor-help" title="Das Leergewicht der Versandverpackung (Karton, Luftpolster). Das finale Bruttogewicht für DHL setzt sich aus dem Netto-Produktgewicht und diesem Tara-Wert zusammen.">Tara <i class="bi bi-exclamation-circle ml-1 text-[8px] opacity-70"></i>:</span>
                                                <span class="text-blue-500 dark:text-blue-400 font-mono">{{ $cd['packaging_weight'] !== null ? $cd['packaging_weight'] . ' g' : '-' }}</span>
                                            </div>
                                            <div class="flex justify-between items-center text-[10px] font-black mt-1 pt-1 border-t border-dashed border-gray-200 dark:border-gray-800/60">
                                                <span class="text-[9px] uppercase tracking-wider text-gray-500 font-black">Gesamt:</span>
                                                <span class="text-gray-900 dark:text-gray-200 font-bold font-mono">{{ number_format($cd['total_cost'], 2, ',', '.') }} €</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 pr-6 align-middle text-right">
                                    <div class="text-[9px] text-gray-500 uppercase tracking-wider font-black mb-1">
                                        <span class="cursor-help flex items-center justify-end" title="Brutto: Verkaufspreis im Shop inkl. MwSt. | Netto: Dein reiner Umsatz nach Steuerabzug.">
                                            Brutto: <span class="text-gray-700 dark:text-gray-400 ml-1 mr-1">{{ number_format($cd['price'], 2, ',', '.') }} €</span> |
                                            Netto: <span class="text-gray-900 dark:text-gray-300 ml-1">{{ number_format($cd['net_price'], 2, ',', '.') }} €</span>
                                            <i class="bi bi-exclamation-circle ml-1 text-[8px] opacity-70"></i>
                                        </span>
                                    </div>
                                    <div class="text-[16px] font-black text-gray-900 dark:text-white block mb-0.5 mt-2 flex justify-end items-center">
                                        <span class="text-[10px] tracking-widest uppercase text-gray-500 mr-2 flex items-center cursor-help" title="Netto VK abzüglich ALLER anfallenden Kosten (EK, Laser, Material, Versand). Das ist der Betrag, der am Ende des Tages als Gewinn hängen bleibt.">
                                            Reingewinn <i class="bi bi-exclamation-circle ml-1 text-[8px] opacity-70"></i>:
                                        </span>
                                        {{ number_format($cd['net_margin'], 2, ',', '.') }} €
                                    </div>
                                    @php
                                        $mClass = $cd['margin_percent'] >= 50 ? 'text-emerald-600 dark:text-emerald-400' : ($cd['margin_percent'] >= 20 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400');
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

                <div class="mt-6 bg-gray-50 dark:bg-[var(--theme-color-5)] border border-gray-200 dark:border-[var(--theme-color-20)] rounded-xl p-4 flex gap-4 items-start">
                    <i class="bi bi-lightbulb text-[var(--theme-color)] text-xl"></i>
                    <div>
                        <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest mb-1">Tipp für maximale Rentabilität</h4>
                        <p class="text-[10px] text-gray-600 dark:text-gray-400 leading-relaxed">Passe im Produkt-Editor die "Laserlaufzeit in Min." und "Stromfaktor pro Min." an, wenn du neue Materialstärken nutzt. Ein 6mm Holz benötigt ca. 40% mehr Laufzeit als das 3mm Standard-Holz, was den Gewinn senkt.</p>
                    </div>
                </div>
            </div>

            <!-- TAB 2: LUCID / PACKAGING DETAILS -->
            <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 lg:p-8 dark:shadow-inner transition-colors duration-300">
                <h3 class="text-lg font-serif font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-3"><i class="solar-leaf-bold-duotone text-emerald-500 text-xl"></i> Detaillierte Zusammensetzung pro Produkt (LUCID Report {{ $lucidData['year'] }})</h3>
                <p class="text-[11px] font-medium text-gray-600 dark:text-gray-400 max-w-2xl leading-relaxed mb-6">
                    Zusammensetzung der in diesem Jahr verbrauchten Verpackungsmaterialien nach Produkt (Anzahl Verkäufe x Theoretisches Gewicht).
                </p>

                <div class="overflow-x-auto border border-gray-200 dark:border-gray-800/60 rounded-xl">
                    <table class="w-full text-left border-collapse bg-gray-50/50 dark:bg-gray-950/30 min-w-[700px]">
                        <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800/60 bg-gray-100/70 dark:bg-gray-900/50 text-[9px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-600">
                            <th class="p-4 pl-6 w-[30%]">Produkt</th>
                            <th class="p-4 text-center w-[15%]">Verkaufte Einheiten</th>
                            @php
                                $materialIcons = [
                                    'paper' => ['color' => 'text-emerald-500 dark:text-emerald-400', 'label' => 'PPK (Papier/Pappe)'],
                                    'plastic' => ['color' => 'text-blue-500 dark:text-blue-400', 'label' => 'Kunststoffe'],
                                    'glass' => ['color' => 'text-teal-500 dark:text-teal-400', 'label' => 'Glas'],
                                    'wood' => ['color' => 'text-amber-600', 'label' => 'Holz'],
                                    'tin' => ['color' => 'text-gray-500 dark:text-gray-400', 'label' => 'Weißblech'],
                                    'alu' => ['color' => 'text-gray-600 dark:text-gray-300', 'label' => 'Aluminium'],
                                    'composite' => ['color' => 'text-purple-500 dark:text-purple-400', 'label' => 'Verbund'],
                                    'other' => ['color' => 'text-lime-600 dark:text-lime-400', 'label' => 'Natur / Sonstige'],
                                ];
                            @endphp
                            @foreach($materialIcons as $key => $iconData)
                                @if($lucidData['totals_kg'][$key] > 0)
                                    <th class="p-4 text-right {{ $iconData['color'] }}">{{ $iconData['label'] }}</th>
                                @endif
                            @endforeach
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800/40">
                        @forelse($lucidData['details'] as $item)
                            <tr class="hover:bg-gray-100/50 dark:hover:bg-gray-800/30 transition-colors">
                                <td class="p-4 pl-6 text-xs font-bold text-gray-800 dark:text-gray-300 line-clamp-1 border-0">{{ $item['name'] }}</td>
                                <td class="p-4 text-center text-[10px] text-gray-600 dark:text-gray-400 font-bold border-0"><span class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 px-3 py-1 rounded-lg">{{ $item['sold'] }} x</span></td>
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

                <div class="mt-6 border-t border-gray-200 dark:border-gray-800 pt-6">
                    <button wire:click.prevent="downloadLucidReport" class="inline-flex w-full sm:w-auto justify-center sm:justify-start items-center gap-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-white text-gray-700 dark:text-gray-400 hover:text-black border border-gray-200 dark:border-gray-700 px-6 py-2.5 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all">
                        <i class="fa-solid fa-leaf text-emerald-500"></i> LUCID Bericht herunterladen
                    </button>
                </div>
            </div>

            {{-- Main Charting Wrapper (Alpine.js integration for Chart.js) --}}
            <div x-data="productDashboard()"
                 x-init="initCharts()"
                 @analytics-updated.window="updateCharts()"
                 @theme-changed.window="initCharts()"
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
                    <div class="bg-white dark:bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-200 dark:border-gray-700 p-6 dark:shadow-xl relative overflow-hidden group flex flex-col justify-between transition-colors duration-300">
                        <div class="hidden dark:block absolute inset-0 bg-gradient-to-br from-rose-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                        <div>
                            <h3 class="text-gray-900 dark:text-white text-lg font-serif font-semibold flex items-center gap-2">
                                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-rose-500" />
                                Schadensmeldungen & Verlust (Menge)
                            </h3>
                            <div class="relative h-64 w-full mt-4" wire:ignore>
                                <canvas id="lossChart"></canvas>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700/50">
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-1"><strong class="text-rose-500 dark:text-rose-400 uppercase text-[10px] tracking-widest block mb-1">Qualitäts-Tracker</strong>Zeigt an, wie viele Einheiten pro Zeitraum als Verlust oder Defekt gemeldet wurden.</p>
                        </div>
                    </div>

                    {{-- Top Loss Chart --}}
                    <div class="bg-white dark:bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-200 dark:border-rose-900/40 p-6 dark:shadow-xl relative overflow-hidden group flex flex-col justify-between transition-colors duration-300">
                        <div class="hidden dark:block absolute inset-0 bg-gradient-to-br from-red-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                        <div>
                            <h3 class="text-gray-900 dark:text-white text-lg font-serif font-semibold flex items-center gap-2">
                                <x-heroicon-o-currency-euro class="w-5 h-5 text-red-500" />
                                Top 5 Schadens-Treiber (€)
                            </h3>
                            <div class="relative h-64 w-full mt-4" wire:ignore>
                                <canvas id="topLossChart"></canvas>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-red-900/50">
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-1"><strong class="text-red-500 dark:text-red-400 uppercase text-[10px] tracking-widest block mb-1">Finanzieller Impact</strong>Identifiziert sofort die 5 fehleranfälligsten Produkte, basierend auf dem monetären Schaden.</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Review Categories --}}
                    <div class="bg-white dark:bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-200 dark:border-gray-700 p-6 dark:shadow-xl relative overflow-hidden group flex flex-col justify-between transition-colors duration-300">
                        <div class="hidden dark:block absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                        <div>
                            <h3 class="text-gray-900 dark:text-white text-lg font-serif font-semibold flex items-center gap-2">
                                <x-heroicon-o-star class="w-5 h-5 text-amber-500" />
                                Kundenbewertungen (Sterne)
                            </h3>
                            <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                                <canvas id="reviewChart"></canvas>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700/50">
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-1"><strong class="text-amber-500 dark:text-amber-400 uppercase text-[10px] tracking-widest block mb-1">Kundenzufriedenheit</strong>Die Verteilung der vergebenen Ratings (1-5 Sterne) auf deine Produkte.</p>
                        </div>
                    </div>

                    {{-- Supplier Types --}}
                    <div class="bg-white dark:bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-200 dark:border-gray-700 p-6 dark:shadow-xl relative overflow-hidden group flex flex-col justify-between transition-colors duration-300">
                        <div class="hidden dark:block absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                        <div>
                            <h3 class="text-gray-900 dark:text-white text-lg font-serif font-semibold flex items-center gap-2">
                                <x-heroicon-o-truck class="w-5 h-5 text-indigo-500" />
                                Lieferanten Klumpenrisiko
                            </h3>
                            <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                                <canvas id="supplierChart"></canvas>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700/50">
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-1"><strong class="text-indigo-500 dark:text-indigo-400 uppercase text-[10px] tracking-widest block mb-1">Lieferketten Logistik</strong>Anzahl der physischen Produkte, die an einen einzelnen Lieferanten gebunden sind.</p>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    @endif

</div>

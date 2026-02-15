<div class="p-6 bg-slate-50 min-h-screen space-y-8">
    {{-- Notwendig für Charts --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    {{-- Header mit Filtern --}}
    <div class="flex flex-col xl:flex-row justify-between items-end gap-6 border-b border-slate-200 pb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight flex items-center gap-3">
                <i class="solar-pulse-bold-duotone text-indigo-500"></i>
                System & Business Intelligence
            </h1>
            <p class="text-slate-500 mt-2">Ganzheitliche Analyse von Systemstatus, Finanzen und Shop-Performance.</p>
        </div>

        {{-- FILTER BAR --}}
        <div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-200 flex flex-wrap items-center gap-4">

            {{-- Datumswähler --}}
            <div class="flex items-center gap-2 px-2">
                <div class="relative">
                    <label class="absolute -top-2 left-2 bg-white px-1 text-[10px] font-bold text-slate-400">Von</label>
                    <input type="date" wire:model.blur="dateStart" class="border-slate-200 rounded-lg text-xs font-bold text-slate-600 focus:ring-indigo-500 focus:border-indigo-500 py-1.5">
                </div>
                <span class="text-slate-300">-</span>
                <div class="relative">
                    <label class="absolute -top-2 left-2 bg-white px-1 text-[10px] font-bold text-slate-400">Bis</label>
                    <input type="date" wire:model.blur="dateEnd" class="border-slate-200 rounded-lg text-xs font-bold text-slate-600 focus:ring-indigo-500 focus:border-indigo-500 py-1.5">
                </div>
            </div>

            {{-- Quick Buttons --}}
            <div class="flex gap-2">
                <button wire:click="setCurrentMonth"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-50 text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition">
                    Aktueller Monat
                </button>
                <button wire:click="setWholeYear"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-50 text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition">
                    Ganzes Jahr
                </button>
            </div>

            <div class="h-8 w-px bg-slate-100"></div>

            {{-- Typ Switcher --}}
            <div class="flex bg-slate-100 p-1 rounded-xl">
                <button wire:click="$set('filterType', 'all')"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $filterType === 'all' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    Alles
                </button>
                <button wire:click="$set('filterType', 'business')"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $filterType === 'business' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    Gewerbe
                </button>
                <button wire:click="$set('filterType', 'private')"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $filterType === 'private' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    Privat
                </button>
            </div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- SECTION A: HIGHLIGHTS & RANKING (TOP) --}}
    {{-- ========================================== --}}

    {{-- 6. PRODUKT RANKING (PODEST) --}}
    @if($stats['product_ranking']->isNotEmpty())
        <div class="bg-white rounded-[3rem] p-10 shadow-sm border border-slate-100 text-center relative overflow-hidden group">
            <div class="absolute top-4 right-4 text-slate-300 hover:text-indigo-500 cursor-help" title="Zeigt die meistverkauften Produkte (Menge) im gewählten Zeitraum.">
                <i class="solar-info-circle-bold-duotone text-xl"></i>
            </div>
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
            <h3 class="text-sm font-black text-slate-400 uppercase tracking-[0.3em] mb-12 relative z-10">Meistverkaufte Unikate (Zeitraum)</h3>

            <div class="flex justify-center items-end gap-4 md:gap-12 relative z-10">
                @php $ranks = $stats['product_ranking']; @endphp

                {{-- Platz 2 --}}
                <div class="flex flex-col items-center group/item">
                    <div class="mb-3 text-center">
                        <span class="block text-xs font-bold text-slate-500 truncate max-w-[120px]">{{ $ranks[1]->product_name ?? '-' }}</span>
                        <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ $ranks[1]->qty ?? 0 }} Stk.</span>
                    </div>
                    <div class="w-24 md:w-32 bg-slate-200 rounded-t-2xl flex items-center justify-center h-32 border-x border-t border-slate-300 shadow-inner group-hover/item:bg-slate-300 transition-colors">
                        <span class="text-4xl font-black text-slate-400 opacity-50">2</span>
                    </div>
                </div>

                {{-- Platz 1 --}}
                <div class="flex flex-col items-center group/item">
                    <div class="mb-4 text-center">
                        <div class="text-3xl mb-1 animate-bounce">👑</div>
                        <span class="block text-sm font-black text-indigo-700 truncate max-w-[160px]">{{ $ranks[0]->product_name ?? '-' }}</span>
                        <span class="text-xs font-bold text-white bg-indigo-500 px-3 py-1 rounded-full shadow-lg shadow-indigo-200">{{ $ranks[0]->qty ?? 0 }} Stk.</span>
                    </div>
                    <div class="w-28 md:w-44 bg-gradient-to-b from-indigo-500 to-indigo-600 rounded-t-[2.5rem] flex items-center justify-center h-48 shadow-2xl relative overflow-hidden group-hover/item:scale-105 transition-transform">
                        <div class="absolute inset-0 bg-white/10 opacity-0 group-hover/item:opacity-100 transition-opacity"></div>
                        <span class="text-6xl font-black text-white drop-shadow-md">1</span>
                    </div>
                </div>

                {{-- Platz 3 --}}
                <div class="flex flex-col items-center group/item">
                    <div class="mb-3 text-center">
                        <span class="block text-xs font-bold text-slate-500 truncate max-w-[120px]">{{ $ranks[2]->product_name ?? '-' }}</span>
                        <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ $ranks[2]->qty ?? 0 }} Stk.</span>
                    </div>
                    <div class="w-20 md:w-28 bg-slate-100 rounded-t-xl flex items-center justify-center h-20 border-x border-t border-slate-200 shadow-inner group-hover/item:bg-slate-200 transition-colors">
                        <span class="text-2xl font-black text-slate-300 opacity-50">3</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-[2.5rem] p-8 shadow-sm border border-slate-100 relative overflow-hidden group">
        <div class="absolute top-4 right-4 text-slate-300 hover:text-indigo-500 cursor-help" title="Zeigt kritische Systemzustände wie leere Lagerbestände, fehlende Belege oder ablaufende Verträge.">
            <i class="solar-info-circle-bold-duotone text-xl"></i>
        </div>
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-sm font-black text-slate-400 uppercase tracking-[0.2em]">Operativer Status</h3>
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-300 bg-slate-50 px-3 py-1 rounded-full">Live Action</span>
        </div>
        <livewire:global.widgets.health-check />
    </div>

    {{-- ========================================== --}}
    {{-- SECTION B: KPI KACHELN (ERWEITERT) --}}
    {{-- ========================================== --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        {{-- Trend --}}
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 relative group">
            <div class="absolute top-2 right-2 text-slate-200 hover:text-indigo-500 cursor-help" title="Vergleich des Gesamtumsatzes zum direkten Vorzeitraum (z.B. aktueller Monat vs. Vormonat).">
                <i class="solar-info-circle-bold-duotone"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase">Umsatz-Trend</span>
            <div class="flex items-end gap-2 mt-2">
                <span class="text-xl font-black {{ $stats['revenue_growth'] >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">
                    {{ $stats['revenue_growth'] > 0 ? '+' : ''}}{{ $stats['revenue_growth'] }}%
                </span>
                <span class="text-[9px] text-slate-400 mb-1">vs. Vorperiode</span>
            </div>
        </div>

        {{-- Marge --}}
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 relative group">
            <div class="absolute top-2 right-2 text-slate-200 hover:text-indigo-500 cursor-help" title="Gewinnmarge in Prozent (Gewinn / Umsatz). Zeigt die Effizienz des Geschäfts.">
                <i class="solar-info-circle-bold-duotone"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase">Gewinn-Marge</span>
            <div class="flex items-end gap-2 mt-2">
                <span class="text-xl font-black text-indigo-600">{{ $stats['margin'] }}%</span>
                <span class="text-[9px] text-slate-400 mb-1">Ø Zeitraum</span>
            </div>
        </div>

        {{-- Durchschnitt --}}
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 relative group">
            <div class="absolute top-2 right-2 text-slate-200 hover:text-indigo-500 cursor-help" title="Durchschnittlicher monatlicher Reingewinn im gewählten Zeitraum.">
                <i class="solar-info-circle-bold-duotone"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase">Ø Mtl. Gewinn</span>
            <div class="flex items-end gap-2 mt-2">
                <span class="text-xl font-black text-slate-700">{{ number_format($stats['avg_profit'], 0, ',', '.') }} €</span>
            </div>
        </div>

        {{-- Prognose --}}
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 relative group">
            <div class="absolute top-2 right-2 text-slate-200 hover:text-indigo-500 cursor-help" title="Hochrechnung des Jahresgewinns basierend auf dem aktuellen Durchschnitt.">
                <i class="solar-info-circle-bold-duotone"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase">Jahresprognose</span>
            <div class="flex items-end gap-2 mt-2">
                <span class="text-xl font-black text-purple-600">{{ number_format($stats['projected_year'], 0, ',', '.') }} €</span>
                <span class="text-[9px] text-slate-400 mb-1">Gewinn (Est.)</span>
            </div>
        </div>

        {{-- Break Even --}}
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 relative group">
            <div class="absolute top-2 right-2 text-slate-200 hover:text-indigo-500 cursor-help" title="Monatliche Fixkosten, die mindestens gedeckt werden müssen, um Gewinn zu erzielen.">
                <i class="solar-info-circle-bold-duotone"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase">Break-Even (Fix)</span>
            <div class="flex items-end gap-2 mt-2">
                <span class="text-xl font-black text-slate-700">{{ number_format($stats['break_even_monthly'], 0, ',', '.') }} €</span>
                <span class="text-[9px] text-slate-400 mb-1">mtl. Basis</span>
            </div>
        </div>

        {{-- Einnahmen (Fix) --}}
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 relative group">
            <div class="absolute top-2 right-2 text-slate-200 hover:text-indigo-500 cursor-help" title="Summe aller wiederkehrenden Einnahmen (Mieten, etc.) im Zeitraum.">
                <i class="solar-info-circle-bold-duotone"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase">Einnahmen (Fix)</span>
            <div class="flex items-end gap-2 mt-2">
                <span class="text-xl font-black text-emerald-600">{{ number_format($stats['fixed_income_total'], 0, ',', '.') }} €</span>
            </div>
        </div>

        {{-- Shop Umsatz --}}
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 relative group">
            <div class="absolute top-2 right-2 text-slate-200 hover:text-indigo-500 cursor-help" title="Reiner Umsatz aus Online-Bestellungen (ohne sonstige Einnahmen).">
                <i class="solar-info-circle-bold-duotone"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase">Shop Umsatz</span>
            <div class="flex items-end gap-2 mt-2">
                <span class="text-xl font-black text-teal-600">{{ number_format($stats['shop_revenue'], 0, ',', '.') }} €</span>
            </div>
        </div>

        {{-- Fixkosten Privat --}}
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 relative group">
            <div class="absolute top-2 right-2 text-slate-200 hover:text-indigo-500 cursor-help" title="Fixkosten aus dem privaten Bereich im gewählten Zeitraum.">
                <i class="solar-info-circle-bold-duotone"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase">Fixkosten (Privat)</span>
            <div class="flex items-end gap-2 mt-2">
                <span class="text-xl font-black text-rose-400">{{ number_format($stats['fixed_expenses_priv'], 0, ',', '.') }} €</span>
            </div>
        </div>

        {{-- Fixkosten Gewerbe --}}
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 relative group">
            <div class="absolute top-2 right-2 text-slate-200 hover:text-indigo-500 cursor-help" title="Fixkosten aus dem gewerblichen Bereich im gewählten Zeitraum.">
                <i class="solar-info-circle-bold-duotone"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase">Fixkosten (Gewerbe)</span>
            <div class="flex items-end gap-2 mt-2">
                <span class="text-xl font-black text-rose-600">{{ number_format($stats['fixed_expenses_gew'], 0, ',', '.') }} €</span>
            </div>
        </div>

        {{-- Variable Kosten Total --}}
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 relative group">
            <div class="absolute top-2 right-2 text-slate-200 hover:text-indigo-500 cursor-help" title="Summe aller variablen Sonderausgaben (Privat & Gewerbe) im gewählten Zeitraum.">
                <i class="solar-info-circle-bold-duotone"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase">Variable Kosten</span>
            <div class="flex items-end gap-2 mt-2">
                <span class="text-xl font-black text-orange-500">{{ number_format($stats['variable_expenses'], 0, ',', '.') }} €</span>
            </div>
        </div>
    </div>


    {{-- ========================================== --}}
    {{-- SECTION C: FINANZ & KUNDEN --}}
    {{-- ========================================== --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

        {{-- 1. GEWINN VOR STEUERN (Grosser Chart) --}}
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex flex-col relative group" wire:ignore>
            <div class="absolute top-4 right-4 text-slate-300 hover:text-indigo-500 cursor-help z-10" title="Visualisierung von Umsatz (Blau), Gewinn (Grün) und Ausgaben (Rot/Ausgeblendet) über den Zeitverlauf.">
                <i class="solar-info-circle-bold-duotone text-xl"></i>
            </div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest">Gewinn-Entwicklung</h3>
                    <p class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($dateStart)->format('d.m.y') }} - {{ \Carbon\Carbon::parse($dateEnd)->format('d.m.y') }}</p>
                </div>
                <div class="flex gap-2">
                    <span class="flex items-center gap-1 text-[10px] font-bold text-indigo-600"><span class="w-2 h-2 rounded-full bg-indigo-500"></span> Umsatz</span>
                    <span class="flex items-center gap-1 text-[10px] font-bold text-emerald-600"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Gewinn</span>
                </div>
            </div>
            <div class="h-64 w-full relative">
                <canvas id="profitChart"></canvas>
            </div>
        </div>

        {{-- 2. OFFENE POSTEN --}}
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between relative overflow-hidden group">
            <div class="absolute top-4 right-4 text-slate-300 hover:text-indigo-500 cursor-help z-20" title="Summe aller Rechnungen mit Status 'Offen'. Klicke auf 'Mahnwesen', um diese zu verwalten.">
                <i class="solar-info-circle-bold-duotone text-xl"></i>
            </div>
            <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity"><i class="solar-bill-list-bold-duotone text-6xl"></i></div>
            <div>
                <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest">Offene Posten</h3>
                <div class="mt-2">
                    <span class="text-4xl font-black text-rose-500 tracking-tighter">{{ number_format($stats['pending_invoices']['sum'], 2, ',', '.') }} €</span>
                    <p class="text-xs text-rose-400 font-bold mt-1">aus {{ $stats['pending_invoices']['count'] }} unbezahlten Rechnungen</p>
                </div>
            </div>
            <a href="{{ route('admin.invoices') }}" class="mt-4 w-full py-3 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-xl text-center text-xs font-bold uppercase tracking-widest transition-colors">
                Mahnwesen öffnen
            </a>
        </div>

        {{-- 7. KUNDEN ENTWICKLUNG (Kachel) --}}
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between relative group">
            <div class="absolute top-4 right-4 text-slate-300 hover:text-indigo-500 cursor-help" title="Gesamtzahl aller registrierten Kunden und Zuwachs im gewählten Zeitraum.">
                <i class="solar-info-circle-bold-duotone text-xl"></i>
            </div>
            <div>
                <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest">Kundenstamm</h3>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-4xl font-black text-slate-800">{{ $stats['customer_stats']['total'] }}</span>
                    <span class="text-xs text-slate-500 font-medium">Gesamt</span>
                </div>
                <p class="text-xs text-slate-400 mt-1">+{{ $stats['customer_stats']['new_period'] }} im gewählten Zeitraum</p>
            </div>
        </div>

        {{-- 3. TOP 5 AUSGABEN (Kreis) --}}
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 relative group" wire:ignore>
            <div class="absolute top-4 right-4 text-slate-300 hover:text-indigo-500 cursor-help z-10" title="Verteilung der Ausgaben nach Kategorien (z.B. Material, Büro).">
                <i class="solar-info-circle-bold-duotone text-xl"></i>
            </div>
            <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest mb-4 text-center">Top Kostentreiber</h3>
            <div class="h-48 relative">
                <canvas id="expensesChart"></canvas>
            </div>
        </div>

        {{-- 4. TOP 5 KUNDEN (Kreis) --}}
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 relative group" wire:ignore>
            <div class="absolute top-4 right-4 text-slate-300 hover:text-indigo-500 cursor-help z-10" title="Die 5 Kunden mit dem höchsten Gesamtumsatz (Lifetime).">
                <i class="solar-info-circle-bold-duotone text-xl"></i>
            </div>
            <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest mb-4 text-center">Top Kunden (Umsatz)</h3>
            <div class="h-48 relative">
                <canvas id="customersChart"></canvas>
            </div>
        </div>

        {{-- 5. PRODUKT HIGHLIGHTS (Umsatz) - HELL --}}
        <div class="lg:col-span-2 bg-white rounded-[2.5rem] p-8 shadow-sm border border-slate-200 relative overflow-hidden flex flex-col justify-center group">
            <div class="absolute top-4 right-4 text-slate-300 hover:text-indigo-500 cursor-help z-20" title="Produkte mit dem höchsten (Top) und niedrigsten (Flop) Gesamtumsatz im Zeitraum.">
                <i class="solar-info-circle-bold-duotone text-xl"></i>
            </div>
            <div class="absolute top-0 right-0 p-10 opacity-[0.03]"><i class="solar-graph-up-bold-duotone text-9xl text-slate-900"></i></div>

            <div class="grid grid-cols-2 gap-8 relative z-10 divide-x divide-slate-100">
                {{-- High Performer --}}
                <div class="pl-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="p-2 bg-emerald-100 rounded-xl text-emerald-600 shadow-sm"><i class="solar-star-bold-duotone text-lg"></i></div>
                        <div>
                            <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest">Umsatz-König</h4>
                            <span class="text-[10px] text-emerald-600 font-bold">Top Performer</span>
                        </div>
                    </div>
                    @if($stats['high_revenue_prod'])
                        <div class="text-xl font-bold text-slate-900 truncate" title="{{ $stats['high_revenue_prod']->product_name }}">
                            {{ $stats['high_revenue_prod']->product_name }}
                        </div>
                        <div class="text-3xl font-black mt-1 text-emerald-600 tracking-tighter">
                            {{ number_format($stats['high_revenue_prod']->total / 100, 2, ',', '.') }} €
                        </div>
                    @else
                        <div class="text-slate-400 text-sm">Keine Daten im Zeitraum</div>
                    @endif
                </div>

                {{-- Low Performer --}}
                <div class="pl-8">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="p-2 bg-rose-100 rounded-xl text-rose-600 shadow-sm"><i class="solar-sleeping-circle-bold-duotone text-lg"></i></div>
                        <div>
                            <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest">Schlusslicht</h4>
                            <span class="text-[10px] text-rose-600 font-bold">Optimierungsbedarf</span>
                        </div>
                    </div>
                    @if($stats['low_revenue_prod'])
                        <div class="text-xl font-bold text-slate-900 truncate" title="{{ $stats['low_revenue_prod']->product_name }}">
                            {{ $stats['low_revenue_prod']->product_name }}
                        </div>
                        <div class="text-3xl font-black mt-1 text-rose-500 tracking-tighter">
                            {{ number_format($stats['low_revenue_prod']->total / 100, 2, ',', '.') }} €
                        </div>
                    @else
                        <div class="text-slate-400 text-sm">Keine Daten im Zeitraum</div>
                    @endif
                </div>
            </div>
        </div>

    </div>

    {{-- ========================================== --}}
    {{-- SECTION D: SYSTEM STATUS (Original Checks) --}}
    {{-- ========================================== --}}
    <div class="border-t border-slate-200 pt-8 mt-8">
        <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
            <i class="solar-server-square-bold-duotone text-indigo-500"></i>
            System Status
        </h3>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            @include('livewire.global.widgets.system-check.partials.backend_stats')
            @include('livewire.global.widgets.system-check.partials.frontend_analytics_chart')
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6" x-data="{ showFailed: @entangle('showFailedLogins'), showLogins: @entangle('showFullLogins') }">
            @include('livewire.global.widgets.system-check.partials.login_history')
            @include('livewire.global.widgets.system-check.partials.failed_logins')
        </div>
    </div>

    {{-- Inkludiert das erweiterte Skript --}}
    @include('livewire.global.widgets.system-check.partials.chart_scripts_extended')
</div>

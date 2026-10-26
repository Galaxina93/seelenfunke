@php
    $formattedTaxRate = rtrim(rtrim(number_format((float) ($defaultTaxRate ?? shop_setting('default_tax_rate', 19.0)), 2, ',', '.'), '0'), ',');
    $monthNamesShort = [1 => 'Jan', 2 => 'Feb', 3 => 'Mär', 4 => 'Apr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dez'];
    $monthNamesFull = [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];
    $totalExpensesCount = $fixedItemsDetailed->count() + $variableItemsDetailed->count();
    $totalInvoicesCount = $filteredInvoices->count() + $filteredPayouts->count();
@endphp
<div x-data="{ mainTab: 'expenses', showExplanation: false }"
     class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6 lg:py-7 animate-fade-in-up font-sans antialiased text-gray-700 dark:text-gray-300">

    {{-- Flash-Meldungen --}}
    @if (session()->has('tax_success'))
        <div class="mb-5 p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-xs font-bold flex items-center justify-between shadow-xs">
            <span>{{ session('tax_success') }}</span>
            <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-600 shrink-0" />
        </div>
    @endif

    @if (session()->has('tax_error'))
        <div class="mb-5 p-3.5 rounded-2xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-800 dark:text-red-300 text-xs font-bold flex items-center justify-between shadow-xs">
            <span>{{ session('tax_error') }}</span>
            <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-red-600 shrink-0" />
        </div>
    @endif

    {{-- 1. Schlanker Header: Begrüßung + 4 Kern-KPIs --}}
    <div class="relative overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl sm:rounded-3xl px-5 py-4 sm:px-6 sm:py-5 mb-5 shadow-sm">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-amber-500/5 via-transparent to-transparent pointer-events-none"></div>
        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="relative w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-amber-50 dark:bg-gray-950 border border-primary/30 overflow-hidden flex items-center justify-center shrink-0 shadow-inner">
                    <span class="text-primary font-serif font-bold text-lg sm:text-xl uppercase">
                        {{ substr($employee?->first_name ?? 'S', 0, 1) }}
                    </span>
                </div>

                <div>
                    <span class="text-primary text-[10px] font-black uppercase tracking-[0.2em] block">
                        Seelenfunke Steuerberater Cockpit
                    </span>
                    <h1 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">
                        Hallo, {{ $employee?->first_name }} {{ $employee?->last_name }}
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">
                        Mandant: <strong class="text-gray-900 dark:text-white">{{ $companyMeta['name'] }}</strong>
                        &bull; St.-Nr.: <span class="font-mono">{{ $companyMeta['tax_id'] ?: '—' }}</span>
                        &bull; <span class="text-primary font-semibold">{{ $activeMonthData['is_small_business_year'] ? '§ 19 UStG' : 'Regelbesteuert (' . $formattedTaxRate . ' %)' }}</span>
                    </p>
                </div>
            </div>

            {{-- 4 ruhige KPI-Boxen --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 shrink-0">
                <div class="bg-gray-50/80 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800/80 rounded-xl px-3.5 py-2 min-w-[125px]">
                    <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block">Shop-Umsatz</span>
                    <span class="text-gray-900 dark:text-white text-sm font-serif font-bold block">{{ number_format($activeMonthData['revenue_gross'], 2, ',', '.') }} €</span>
                    <span class="text-gray-400 text-[10px] block">Netto: {{ number_format($activeMonthData['revenue_net'], 2, ',', '.') }} €</span>
                </div>

                <div class="bg-gray-50/80 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800/80 rounded-xl px-3.5 py-2 min-w-[125px]">
                    <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block">Gewerbe-Kosten</span>
                    <span class="text-gray-900 dark:text-white text-sm font-serif font-bold block">{{ number_format($activeMonthData['expenses_gross'], 2, ',', '.') }} €</span>
                    <span class="text-gray-400 text-[10px] block">Netto: {{ number_format($activeMonthData['expenses_net'], 2, ',', '.') }} €</span>
                </div>

                <div class="bg-gray-50/80 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800/80 rounded-xl px-3.5 py-2 min-w-[125px]">
                    <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block">Vorsteuer (Kz 66)</span>
                    <span class="text-gray-900 dark:text-white text-sm font-serif font-bold block">{{ number_format($activeMonthData['vat_paid'], 2, ',', '.') }} €</span>
                    <span class="text-gray-400 text-[10px] block">USt: {{ number_format($activeMonthData['vat_collected'], 2, ',', '.') }} €</span>
                </div>

                <div class="bg-amber-500/10 dark:bg-amber-500/15 border border-primary/30 rounded-xl px-3.5 py-2 min-w-[130px]">
                    <span class="text-amber-900 dark:text-amber-300 text-[9px] font-black uppercase tracking-widest block">Zahllast (Kz 83)</span>
                    <span class="text-primary font-serif text-sm font-bold block">{{ number_format($activeMonthData['zahllast'], 2, ',', '.') }} €</span>
                    <span class="text-gray-500 dark:text-gray-400 text-[10px] block">{{ $monthNamesShort[$selectedMonth] }}/{{ $selectedYear }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Schlanke Gold-Leiste: Zeitraum wählen + Direkt-Downloads in einer Zeile --}}
    <div class="mb-5 p-4 bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border border-primary/30 rounded-2xl sm:rounded-3xl shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3.5">

            {{-- Links: Zeitraum-Auswahl (Monat & Jahr) --}}
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="text-xs font-serif font-bold text-gray-900 dark:text-white mr-1">Abrechnungszeitraum:</span>

                <select wire:change="selectMonth($event.target.value)"
                        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary cursor-pointer shadow-xs">
                    @foreach($monthNamesFull as $mNum => $mLabel)
                        <option value="{{ $mNum }}" @selected((int) $selectedMonth === $mNum)>{{ $mLabel }}</option>
                    @endforeach
                </select>

                <select wire:model.live="selectedYear"
                        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary cursor-pointer shadow-xs">
                    @for($y = (int) date('Y') + 1; $y >= 2023; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>

                <div class="inline-flex items-center bg-white dark:bg-gray-900 p-0.5 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
                    <button type="button" wire:click="setPeriodMode('month')"
                            class="px-2.5 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer {{ $periodMode === 'month' ? 'bg-gray-900 text-white dark:bg-primary dark:text-gray-950' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                        Monat
                    </button>
                    <button type="button" wire:click="setPeriodMode('year')"
                            class="px-2.5 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer {{ $periodMode === 'year' ? 'bg-gray-900 text-white dark:bg-primary dark:text-gray-950' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                        Ganzes Jahr
                    </button>
                </div>
            </div>

            {{-- Rechts: Alle wichtigen Downloads kompakt nebeneinander --}}
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="downloadDatevPackageZip"
                        title="Komplettpaket: DATEV CSV + UStVA PDF + alle Belege als ZIP"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white font-black text-[10px] uppercase tracking-widest transition-colors cursor-pointer shadow-xs">
                    <x-heroicon-s-archive-box-arrow-down class="w-4 h-4" />
                    Komplettpaket (ZIP)
                </button>

                <button type="button" wire:click="downloadDatevCsv"
                        class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-primary text-gray-800 dark:text-gray-200 font-black text-[10px] uppercase tracking-wider transition-colors cursor-pointer shadow-xs">
                    DATEV CSV
                </button>

                <button type="button" wire:click="downloadUstvaPdf"
                        class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-primary text-gray-800 dark:text-gray-200 font-black text-[10px] uppercase tracking-wider transition-colors cursor-pointer shadow-xs">
                    UStVA PDF
                </button>

                <button type="button" wire:click="downloadFinancialReportZip"
                        class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-primary text-gray-800 dark:text-gray-200 font-black text-[10px] uppercase tracking-wider transition-colors cursor-pointer shadow-xs">
                    Finanzbericht ZIP
                </button>

                <button type="button" wire:click="downloadMonthlyInvoicesZip"
                        class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-primary text-gray-800 dark:text-gray-200 font-black text-[10px] uppercase tracking-wider transition-colors cursor-pointer shadow-xs">
                    Rechnungen ZIP
                </button>
            </div>
        </div>
    </div>

    {{-- 3. EINE aufgeräumte Haupt-Kachel mit 3 klaren Tabs --}}
    <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm">

        {{-- Kachel-Header: Titel + Tab-Umschalter + Gewerbe/Privat-Filter --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pb-4 mb-4 border-b border-gray-100 dark:border-gray-800">
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800">
                    <button type="button" @click="mainTab = 'expenses'"
                            :class="mainTab === 'expenses' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-900'"
                            class="px-3.5 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                        Ausgaben &amp; Belege ({{ $totalExpensesCount }})
                    </button>
                    <button type="button" @click="mainTab = 'invoices'"
                            :class="mainTab === 'invoices' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-900'"
                            class="px-3.5 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                        Rechnungen &amp; Gutschriften ({{ $totalInvoicesCount }})
                    </button>
                    <button type="button" @click="mainTab = 'ustva'"
                            :class="mainTab === 'ustva' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-900'"
                            class="px-3.5 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                        UStVA &amp; Jahreswerte
                    </button>
                </div>
            </div>

            {{-- Rechts: Kompakte Filter passend zum Tab --}}
            <div class="flex flex-wrap items-center gap-2">
                <div x-show="mainTab !== 'invoices'" class="inline-flex items-center bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800">
                    <button type="button" wire:click="setScopeFilter('all')"
                            class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer {{ $scopeFilter === 'all' ? 'bg-primary text-white' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                        Alle
                    </button>
                    <button type="button" wire:click="setScopeFilter('business')"
                            class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer {{ $scopeFilter === 'business' ? 'bg-primary text-white' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                        Gewerblich
                    </button>
                    <button type="button" wire:click="setScopeFilter('private')"
                            class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer {{ $scopeFilter === 'private' ? 'bg-primary text-white' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                        Privat
                    </button>
                </div>

                <div x-show="mainTab === 'invoices'" x-cloak>
                    <select wire:model.live="invoiceTypeFilter"
                            class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-1.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary cursor-pointer">
                        <option value="">Alle Belegarten</option>
                        <option value="invoice">Ausgangsrechnungen</option>
                        <option value="cancellation">Stornos &amp; Gutschriften</option>
                        <option value="influencer_payout">Creator-Gutschriften (§ 14 UStG)</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- TAB 1: AUSGABEN & BELEGE (Fixkosten + Sonderausgaben schlank in einer Ansicht) --}}
        <div x-show="mainTab === 'expenses'">
            @if($fixedItemsDetailed->isEmpty() && $variableItemsDetailed->isEmpty())
                <div class="p-6 rounded-2xl bg-gray-50 dark:bg-gray-950/50 border border-dashed border-gray-200 dark:border-gray-800 text-center">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400">
                        Keine Ausgaben für den gewählten Zeitraum oder Filter vorhanden.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-widest text-gray-400">
                                <th class="py-2.5 pr-4">Bezeichnung</th>
                                <th class="py-2.5 px-3">Kategorie / Art</th>
                                <th class="py-2.5 px-3 text-center">Zuordnung</th>
                                <th class="py-2.5 px-3 text-right">Betrag (Brutto)</th>
                                <th class="py-2.5 pl-4 text-right">Beleg / Vertrag</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/70 text-xs">
                            {{-- Fixkosten --}}
                            @foreach($fixedItemsDetailed as $fItem)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-950/40 transition-colors">
                                    <td class="py-3 pr-4">
                                        <span class="font-bold text-gray-900 dark:text-white block">{{ $fItem['name'] }}</span>
                                        <span class="text-[11px] text-gray-400">
                                            Fixvertrag (alle {{ $fItem['interval_months'] }} Mon.)
                                            @if(!$fItem['is_due_in_month']) &bull; In {{ $monthNamesShort[$selectedMonth] }} ruhend @endif
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                        {{ $fItem['group_name'] }}
                                    </td>
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        @if($fItem['is_income'])
                                            <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">Einnahme</span>
                                        @elseif($fItem['is_business'])
                                            <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-blue-500/10 text-blue-700 dark:text-blue-400">Gewerblich</span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-500">Privat</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono whitespace-nowrap">
                                        <span class="font-bold text-gray-900 dark:text-white block">{{ number_format($fItem['gross'], 2, ',', '.') }} €</span>
                                        <span class="text-[10px] text-gray-400">{{ number_format($fItem['tax_rate'], 0) }} % MwSt.</span>
                                    </td>
                                    <td class="py-3 pl-4 text-right whitespace-nowrap">
                                        @if(!empty($fItem['contract_file_path']))
                                            <button type="button" wire:click="downloadReceiptOrContract(@js($fItem['contract_file_path']))"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-primary text-[10px] font-black uppercase tracking-wider text-primary cursor-pointer">
                                                <x-heroicon-o-arrow-down-tray class="w-3.5 h-3.5" /> Vertrag
                                            </button>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-700">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                            {{-- Sonderausgaben & Variable Kosten --}}
                            @foreach($variableItemsDetailed as $vItem)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-950/40 transition-colors">
                                    <td class="py-3 pr-4">
                                        <span class="font-bold text-gray-900 dark:text-white block">{{ $vItem['title'] }}</span>
                                        <span class="text-[11px] text-gray-400">
                                            {{ $vItem['date'] }}
                                            @if($vItem['invoice_number']) &bull; Beleg {{ $vItem['invoice_number'] }} @endif
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                        {{ $vItem['category'] }}
                                    </td>
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        @if($vItem['is_business'])
                                            <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-blue-500/10 text-blue-700 dark:text-blue-400">Gewerblich</span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-500">Privat</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono whitespace-nowrap">
                                        <span class="font-bold {{ $vItem['signed_gross'] >= 0 ? 'text-emerald-600' : 'text-gray-900 dark:text-white' }} block">
                                            {{ number_format($vItem['signed_gross'], 2, ',', '.') }} €
                                        </span>
                                        <span class="text-[10px] text-gray-400">{{ number_format($vItem['tax_rate'], 0) }} % MwSt.</span>
                                    </td>
                                    <td class="py-3 pl-4 text-right whitespace-nowrap">
                                        @if(!empty($vItem['file_paths']))
                                            <div class="inline-flex flex-wrap justify-end gap-1">
                                                @foreach($vItem['file_paths'] as $idx => $rPath)
                                                    <button type="button" wire:click="downloadReceiptOrContract(@js($rPath))"
                                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-primary text-[10px] font-black uppercase tracking-wider text-primary cursor-pointer">
                                                        <x-heroicon-o-arrow-down-tray class="w-3.5 h-3.5" />
                                                        Beleg {{ count($vItem['file_paths']) > 1 ? ($idx + 1) : '' }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-700">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- TAB 2: RECHNUNGEN & GUTSCHRIFTEN --}}
        <div x-show="mainTab === 'invoices'" x-cloak>
            @if($filteredInvoices->isEmpty() && $filteredPayouts->isEmpty())
                <div class="p-6 rounded-2xl bg-gray-50 dark:bg-gray-950/50 border border-dashed border-gray-200 dark:border-gray-800 text-center">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400">
                        Keine abgeschlossenen Rechnungen oder Gutschriften im gewählten Zeitraum.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-widest text-gray-400">
                                <th class="py-2.5 pr-4">Beleg-Nr.</th>
                                <th class="py-2.5 px-3">Datum</th>
                                <th class="py-2.5 px-3">Art</th>
                                <th class="py-2.5 px-3">Empfänger</th>
                                <th class="py-2.5 px-3 text-right">Betrag (Brutto)</th>
                                <th class="py-2.5 pl-4 text-right">Downloads</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/70 text-xs">
                            @foreach($filteredInvoices as $inv)
                                @php
                                    $grossCents = \App\Services\FinancialService::signedInvoiceCents($inv, false);
                                    $recipientName = trim(($inv->billing_address['company'] ?? '') ?: (($inv->billing_address['first_name'] ?? '') . ' ' . ($inv->billing_address['last_name'] ?? '')));
                                @endphp
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-950/40 transition-colors">
                                    <td class="py-3 pr-4 font-mono font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                        {{ $inv->invoice_number }}
                                    </td>
                                    <td class="py-3 px-3 font-mono text-gray-500 whitespace-nowrap">
                                        {{ $inv->invoice_date?->format('d.m.Y') ?? '—' }}
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap">
                                        @if($inv->type === 'cancellation')
                                            <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-red-500/10 text-red-600">Storno</span>
                                        @elseif($inv->type === 'credit_note')
                                            <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-700">Gutschrift</span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">Rechnung</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="font-bold text-gray-900 dark:text-white block truncate max-w-[220px]">{{ $recipientName ?: 'Shop-Kunde' }}</span>
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono font-bold {{ $grossCents < 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }} whitespace-nowrap">
                                        {{ number_format($grossCents / 100, 2, ',', '.') }} €
                                    </td>
                                    <td class="py-3 pl-4 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            <button type="button" wire:click="downloadInvoicePdf('{{ $inv->id }}')"
                                                    class="px-2.5 py-1 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-primary text-[10px] font-black uppercase text-gray-800 dark:text-gray-200 cursor-pointer">
                                                PDF
                                            </button>
                                            <button type="button" wire:click="downloadInvoiceXml('{{ $inv->id }}')"
                                                    class="px-2.5 py-1 rounded-lg bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/25 text-[10px] font-black uppercase cursor-pointer">
                                                XML
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach

                            @foreach($filteredPayouts as $payout)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-950/40 transition-colors">
                                    <td class="py-3 pr-4 font-mono font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                        {{ $payout->credit_note_number }}
                                    </td>
                                    <td class="py-3 px-3 font-mono text-gray-500 whitespace-nowrap">
                                        {{ $payout->created_at?->format('d.m.Y') ?? '—' }}
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-primary/15 text-primary-dark">Creator-Gutschrift</span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="font-bold text-gray-900 dark:text-white block">
                                            {{ $payout->influencer?->employee?->profile?->company_name ?: (($payout->influencer?->employee?->first_name ?? '') . ' ' . ($payout->influencer?->employee?->last_name ?? '')) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                        - {{ number_format($payout->gross_amount_cents / 100, 2, ',', '.') }} €
                                    </td>
                                    <td class="py-3 pl-4 text-right whitespace-nowrap">
                                        <a href="{{ route('employee.tax-advisor.payout.pdf', $payout->id) }}" target="_blank"
                                           class="inline-flex items-center px-2.5 py-1 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-primary text-[10px] font-black uppercase text-gray-800 dark:text-gray-200">
                                            PDF
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- TAB 3: UStVA-KENNZAHLEN & KOMPAKTE JAHRESÜBERSICHT --}}
        <div x-show="mainTab === 'ustva'" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Links: Die 5 UStVA-Zeilen --}}
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800 space-y-2.5">
                    <span class="text-[10px] font-black uppercase tracking-widest text-primary block">
                        ELSTER UStVA ({{ $monthNamesFull[$selectedMonth] }} {{ $selectedYear }})
                    </span>

                    <div class="flex justify-between text-xs py-1.5 border-b border-gray-200/70 dark:border-gray-800">
                        <span class="text-gray-600 dark:text-gray-400">Steuerpfl. Umsätze {{ $formattedTaxRate }} % (Kz 81 Netto)</span>
                        <span class="font-mono font-bold text-gray-900 dark:text-white">{{ number_format($activeMonthData['revenue_net'], 2, ',', '.') }} €</span>
                    </div>
                    <div class="flex justify-between text-xs py-1.5 border-b border-gray-200/70 dark:border-gray-800">
                        <span class="text-gray-600 dark:text-gray-400">Umsatzsteuer (zu Kz 81)</span>
                        <span class="font-mono font-bold text-gray-900 dark:text-white">{{ number_format($activeMonthData['vat_collected'], 2, ',', '.') }} €</span>
                    </div>
                    <div class="flex justify-between text-xs py-1.5 border-b border-gray-200/70 dark:border-gray-800">
                        <span class="text-gray-600 dark:text-gray-400">IG Erwerb / § 13b UStG (Kz 89 / Kz 46)</span>
                        <span class="font-mono font-bold text-gray-900 dark:text-white">{{ number_format($activeMonthData['ig_erwerb_tax'] + $activeMonthData['paragraph_13b_tax'], 2, ',', '.') }} €</span>
                    </div>
                    <div class="flex justify-between text-xs py-1.5 border-b border-gray-200/70 dark:border-gray-800">
                        <span class="text-gray-600 dark:text-gray-400">Abziehbare Vorsteuer (Kz 66)</span>
                        <span class="font-mono font-bold text-emerald-600">- {{ number_format($activeMonthData['vat_paid'], 2, ',', '.') }} €</span>
                    </div>
                    <div class="flex justify-between text-xs pt-1.5 font-bold">
                        <span class="text-gray-900 dark:text-white">Verbleibende Zahllast (Kz 83)</span>
                        <span class="font-mono text-primary">{{ number_format($activeMonthData['zahllast'], 2, ',', '.') }} €</span>
                    </div>
                </div>

                {{-- Rechts: Kompakte Jahresübersicht (Gewerbe & Privat) --}}
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Jahressummen {{ $selectedYear }} ({{ $isNet ? 'Netto' : 'Brutto' }})</span>
                        <button type="button" wire:click="$toggle('isNet')" class="text-[10px] font-bold text-primary hover:underline cursor-pointer">
                            Auf {{ $isNet ? 'Brutto' : 'Netto' }} umschalten
                        </button>
                    </div>

                    @foreach($yearlyMatrix['product_categories'] ?? [] as $catKey => $catData)
                        @php
                            $isBusinessCat = in_array($catKey, ['shop_income', 'fixed_business', 'special_business'], true);
                        @endphp
                        <div class="flex items-center justify-between text-xs py-1 border-b border-gray-200/70 dark:border-gray-800">
                            <span class="text-gray-700 dark:text-gray-300 font-medium">{{ $catData['label'] }}</span>
                            <span class="font-mono font-bold {{ $catData['year_sum'] >= 0 ? 'text-emerald-600' : 'text-gray-900 dark:text-white' }}">
                                {{ number_format($catData['year_sum'], 2, ',', '.') }} €
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Unten mittig: Dezenter Button "Erklärung anzeigen" (wie im Influencer-Dashboard) --}}
        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800/80 flex justify-center">
            <button type="button" @click="showExplanation = !showExplanation"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-gray-500 hover:text-primary dark:text-gray-400 dark:hover:text-primary hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors cursor-pointer">
                <x-heroicon-o-information-circle class="w-4 h-4 text-primary" />
                <span x-text="showExplanation ? 'Erklärung ausblenden' : 'Erklärung anzeigen'">Erklärung anzeigen</span>
                <span class="transition-transform duration-200 inline-flex" :class="showExplanation ? 'rotate-180' : ''">
                    <x-heroicon-s-chevron-down class="w-3.5 h-3.5" />
                </span>
            </button>
        </div>

        {{-- Ausklappbare Zusatzinfos (Mandanten-Anschrift, Tresor-Archive & SKR03-Hinweise) --}}
        <div x-show="showExplanation" x-cloak x-transition class="mt-4 p-4 rounded-2xl bg-gray-50 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800 text-xs text-gray-600 dark:text-gray-400 space-y-3 leading-relaxed">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-2.5 border-b border-gray-200/70 dark:border-gray-800">
                <div>
                    <strong class="text-gray-900 dark:text-white">Mandant:</strong> {{ $companyMeta['name'] }} ({{ $companyMeta['owner'] }}), {{ $companyMeta['street'] }}, {{ $companyMeta['city'] }}
                </div>
                <div class="font-mono">
                    St.-Nr.: <strong>{{ $companyMeta['tax_id'] ?: '—' }}</strong> &bull; USt-IdNr.: <strong>{{ $companyMeta['ust_id'] ?: '—' }}</strong>
                </div>
            </div>

            <ul class="list-disc pl-4 space-y-1">
                <li><strong>Gewerblich vs. Privat:</strong> Nur als <em>Gewerblich</em> gekennzeichnete Positionen fließen in die Vorsteuer (Kz 66) und den DATEV-Buchungsstapel ein.</li>
                <li><strong>SKR03-Vorkontierung:</strong> Shop-Erlöse `1200` an `8400`, gewerbliche Ausgaben `4900` an `1200`.</li>
            </ul>

            @if(count($archivedExports) > 0)
                <div class="pt-2 border-t border-gray-200/70 dark:border-gray-800 flex flex-wrap items-center gap-2">
                    <span class="font-bold text-gray-900 dark:text-white">Gespeicherte Tresor-Archive:</span>
                    @foreach($archivedExports as $arch)
                        <button type="button" wire:click="downloadArchivedTaxExport('{{ $arch['name'] }}')"
                                class="px-2.5 py-1 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-primary text-[11px] font-mono text-primary cursor-pointer">
                            {{ $arch['name'] }} ↓
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

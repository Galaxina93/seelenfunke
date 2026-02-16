<div class="space-y-4">
    {{-- Haupt-KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">

        @php
            $renderKpi = function($key, $title, $value, $subValue = null, $dotColor = 'bg-slate-400', $textColor = 'text-slate-700', $bg = 'bg-white', $border = 'border-slate-100') use ($stats, $infoTexts) {
                return "
                <div class='relative $bg p-4 rounded-xl shadow-sm border $border group transition-all hover:shadow-md'>
                    <div class='flex items-center justify-between mb-1'>
                        <div class='flex items-center gap-1.5'>
                            <span class='w-1.5 h-1.5 rounded-full $dotColor'></span>
                            <span class='text-[10px] font-bold text-slate-400 uppercase tracking-tight'>$title</span>
                        </div>

                        <div x-data='{ show: false }' class='relative flex items-center'>
                            <div @mouseenter='show = true' @mouseleave='show = false' class='cursor-help text-slate-300 hover:text-primary transition-colors'>
                                <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor' class='w-3 h-3'>
                                    <path fill-rule='evenodd' d='M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z' clip-rule='evenodd' />
                                </svg>
                            </div>

                            <div x-show='show' x-cloak x-transition class='absolute z-50 bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-slate-800 text-white text-[10px] font-medium rounded-lg shadow-xl pointer-events-none lowercase tracking-normal'>
                                {$infoTexts[$key]}
                                <div class='absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800'></div>
                            </div>
                        </div>
                    </div>

                    <div class='flex items-baseline gap-1'>
                        <span class='text-lg font-black $textColor leading-none'>$value</span>
                        " . ($subValue ? "<span class='text-[9px] text-slate-400'>$subValue</span>" : "") . "
                    </div>
                </div>";
            };
        @endphp

        {!! $renderKpi('trend', 'Umsatz-Trend', (($stats['revenue_growth'] ?? 0) > 0 ? '+' : '') . ($stats['revenue_growth'] ?? 0) . '%', 'vs. Vorp.', ($stats['revenue_growth'] ?? 0) >= 0 ? 'bg-emerald-500' : 'bg-rose-500', ($stats['revenue_growth'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600') !!}
        {!! $renderKpi('marge', 'Gewinn-Marge', ($stats['margin'] ?? 0) . '%', null, 'bg-indigo-500', 'text-indigo-600') !!}
        {!! $renderKpi('avg_profit', 'Ø Mtl. Gewinn', number_format($stats['avg_profit'] ?? 0, 0, ',', '.') . ' €', null, 'bg-slate-400', 'text-slate-700') !!}
        {!! $renderKpi('prognose', 'Prognose', number_format($stats['projected_year'] ?? 0, 0, ',', '.') . ' €', null, 'bg-purple-500', 'text-purple-600') !!}
        {!! $renderKpi('break_even', 'Break-Even', number_format($stats['break_even_monthly'] ?? 0, 0, ',', '.') . ' €', null, 'bg-amber-500', 'text-slate-700') !!}
        {!! $renderKpi('offene', 'Offene Posten', number_format($stats['pending_invoices']['sum'] ?? 0, 0, ',', '.') . ' €', '(' . ($stats['pending_invoices']['count'] ?? 0) . ')', 'bg-rose-500', 'text-rose-600', 'bg-rose-50/50', 'border-rose-100') !!}
    </div>

    {{-- Kosten-Struktur --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        @php
            $renderSmallKpi = function($key, $title, $value, $color) use ($infoTexts) {
                return "
                <div class='relative bg-slate-50/50 px-4 py-3 rounded-xl border border-slate-100 flex flex-col'>
                    <div class='flex items-center justify-between mb-0.5'>
                        <span class='text-[9px] font-bold text-slate-400 uppercase'>$title</span>

                        <div x-data='{ show: false }' class='relative flex items-center'>
                            <div @mouseenter='show = true' @mouseleave='show = false' class='cursor-help text-slate-300 hover:text-primary transition-colors'>
                                <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor' class='w-2.5 h-2.5'>
                                    <path fill-rule='evenodd' d='M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z' clip-rule='evenodd' />
                                </svg>
                            </div>
                            <div x-show='show' x-cloak x-transition class='absolute z-50 bottom-full left-1/2 -translate-x-1/2 mb-2 w-40 p-2 bg-slate-700 text-white text-[9px] rounded-lg shadow-xl pointer-events-none lowercase tracking-normal font-medium'>
                                {$infoTexts[$key]}
                                <div class='absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-700'></div>
                            </div>
                        </div>
                    </div>
                    <div class='text-md font-bold $color'>$value</div>
                </div>";
            };
        @endphp

        {!! $renderSmallKpi('fix_inc', 'Einnahmen (Fix)', number_format($stats['fixed_income_total'] ?? 0, 0, ',', '.') . ' €', 'text-emerald-600') !!}
        {!! $renderSmallKpi('shop_rev', 'Shop Umsatz', number_format($stats['shop_revenue'] ?? 0, 0, ',', '.') . ' €', 'text-teal-600') !!}
        {!! $renderSmallKpi('fix_priv', 'Fixkosten (P)', number_format($stats['fixed_expenses_priv'] ?? 0, 0, ',', '.') . ' €', 'text-rose-400') !!}
        {!! $renderSmallKpi('fix_bus', 'Fixkosten (G)', number_format($stats['fixed_expenses_gew'] ?? 0, 0, ',', '.') . ' €', 'text-rose-600') !!}
        {!! $renderSmallKpi('variabel', 'Variabel', number_format($stats['variable_expenses'] ?? 0, 0, ',', '.') . ' €', 'text-orange-500') !!}
    </div>
</div>

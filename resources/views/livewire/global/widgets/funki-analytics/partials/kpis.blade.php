<div class="space-y-4 w-full">
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 md:gap-4">
        @php
            $renderKpi = function($key, $title, $value, $subValue = null, $dotColor = 'bg-gray-600', $textColor = 'text-white', $bg = 'bg-gray-900/80 backdrop-blur-md', $border = 'border-gray-800') use ($stats, $infoTexts) {
                return "
                <div class='relative $bg p-3.5 sm:p-5 rounded-xl sm:rounded-2xl shadow-2xl border $border group transition-all hover:border-primary/50 overflow-hidden flex flex-col justify-between min-h-[90px]'>
                    <div class='flex items-start sm:items-center justify-between mb-2 gap-2'>
                        <div class='flex items-center gap-1.5 min-w-0'>
                            <span class='w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full shrink-0 $dotColor shadow-[0_0_8px_currentColor]'></span>
                            <span class='text-[8px] sm:text-[10px] font-black text-gray-400 uppercase tracking-widest truncate' title='$title'>$title</span>
                        </div>
                        <div x-data='{show: false}' class='relative flex items-center shrink-0'>
                            <div @mouseenter='show = true' @mouseleave='show = false' @click='show = !show' class='cursor-help text-gray-600 hover:text-primary transition-colors'>
                                <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor' class='w-3.5 h-3.5 sm:w-4 sm:h-4'><path fill-rule='evenodd' d='M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z' clip-rule='evenodd' /></svg>
                            </div>
                            <div x-show='show' x-cloak x-transition class='absolute z-50 bottom-full right-0 sm:left-1/2 sm:-translate-x-1/2 mb-3 w-48 sm:w-56 p-2.5 sm:p-3 bg-gray-950 border border-gray-700 text-gray-300 text-[10px] sm:text-[11px] font-medium rounded-xl shadow-2xl pointer-events-none tracking-wide whitespace-normal'>
                                {$infoTexts[$key]}
                                <div class='hidden sm:block absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-700'></div>
                            </div>
                        </div>
                    </div>
                    <div class='flex items-baseline gap-1.5 flex-wrap'>
                        <span class='text-lg sm:text-xl md:text-2xl font-black $textColor leading-none tracking-tight'>$value</span>
                        " . ($subValue ? "<span class='text-[8px] sm:text-[10px] font-bold text-gray-500 uppercase tracking-widest'>$subValue</span>" : "") . "
                    </div>
                </div>";
            };
        @endphp

        {!! $renderKpi('trend', 'Umsatz-Trend', (($stats['revenue_growth'] ?? 0) > 0 ? '+' : '') . ($stats['revenue_growth'] ?? 0) . '%', 'vs. Vorp.', ($stats['revenue_growth'] ?? 0) >= 0 ? 'bg-emerald-400' : 'bg-red-400', ($stats['revenue_growth'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400') !!}
        {!! $renderKpi('marge', 'Gewinn-Marge', ($stats['margin'] ?? 0) . '%', null, 'bg-blue-400', 'text-blue-400') !!}
        {!! $renderKpi('avg_profit', 'Ø Mtl. Gewinn', number_format($stats['avg_profit'] ?? 0, 0, ',', '.') . ' €', null, 'bg-gray-400', 'text-white') !!}
        {!! $renderKpi('prognose', 'Prognose', number_format($stats['projected_year'] ?? 0, 0, ',', '.') . ' €', null, 'bg-purple-400', 'text-purple-400') !!}
        {!! $renderKpi('break_even', 'Break-Even', number_format($stats['break_even_monthly'] ?? 0, 0, ',', '.') . ' €', null, 'bg-amber-400', 'text-amber-400') !!}
        {!! $renderKpi('offene', 'Offene Posten', number_format($stats['pending_invoices']['sum'] ?? 0, 0, ',', '.') . ' €', '(' . ($stats['pending_invoices']['count'] ?? 0) . ')', 'bg-red-500', 'text-red-400', 'bg-red-900/10 backdrop-blur-md', 'border-red-500/30') !!}
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4">
        @php
            $renderSmallKpi = function($key, $title, $value, $color) use ($infoTexts) {
                return "
                <div class='relative bg-gray-950 px-3 py-3 md:px-5 md:py-4 rounded-xl sm:rounded-2xl border border-gray-800 shadow-inner flex flex-col overflow-hidden'>
                    <div class='flex items-center justify-between mb-1.5 gap-2'>
                        <span class='text-[8px] sm:text-[10px] font-black text-gray-500 uppercase tracking-widest truncate' title='$title'>$title</span>
                        <div x-data='{show: false}' class='relative flex items-center shrink-0'>
                            <div @mouseenter='show = true' @mouseleave='show = false' @click='show = !show' class='cursor-help text-gray-600 hover:text-primary transition-colors'>
                                <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor' class='w-3 h-3 sm:w-3.5 sm:h-3.5'><path fill-rule='evenodd' d='M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z' clip-rule='evenodd' /></svg>
                            </div>
                            <div x-show='show' x-cloak x-transition class='absolute z-50 bottom-full right-0 sm:left-1/2 sm:-translate-x-1/2 mb-2 w-48 p-2.5 bg-gray-800 border border-gray-700 text-gray-300 text-[9px] sm:text-[10px] rounded-xl shadow-2xl pointer-events-none tracking-wide font-medium whitespace-normal'>
                                {$infoTexts[$key]}
                                <div class='hidden sm:block absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-700'></div>
                            </div>
                        </div>
                    </div>
                    <div class='text-sm sm:text-lg font-black $color truncate'>$value</div>
                </div>";
            };
        @endphp

        {!! $renderSmallKpi('fix_inc', 'Einnahmen (Fix)', number_format($stats['fixed_income_total'] ?? 0, 0, ',', '.') . ' €', 'text-emerald-400') !!}
        {!! $renderSmallKpi('shop_rev', 'Shop Umsatz', number_format($stats['shop_revenue'] ?? 0, 0, ',', '.') . ' €', 'text-primary') !!}
        {!! $renderSmallKpi('fix_priv', 'Fixkosten (P)', number_format($stats['fixed_expenses_priv'] ?? 0, 0, ',', '.') . ' €', 'text-pink-400') !!}
        {!! $renderSmallKpi('fix_bus', 'Fixkosten (G)', number_format($stats['fixed_expenses_gew'] ?? 0, 0, ',', '.') . ' €', 'text-rose-400') !!}
        {!! $renderSmallKpi('variabel', 'Variabel', number_format($stats['variable_expenses'] ?? 0, 0, ',', '.') . ' €', 'text-orange-400') !!}
    </div>
</div>

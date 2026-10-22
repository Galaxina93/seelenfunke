@php
    $popDurationDays = max(1, \Carbon\Carbon::parse($dateStart ?? now()->startOfYear())->diffInDays(\Carbon\Carbon::parse($dateEnd ?? now()->endOfYear())) + 1);
    $popMonths = max(1, $popDurationDays / 30.42);
    $popFixInc = ($stats['fixed_income_total'] ?? 0) / $popMonths;
    $popShopRev = ($stats['avg_revenue_monthly'] ?? 0);
    $popFixPriv = ($stats['fixed_expenses_priv'] ?? 0) / $popMonths;
    $popFixGew = ($stats['fixed_expenses_gew'] ?? 0) / $popMonths;
    $popVarExp = ($stats['variable_expenses'] ?? 0) / $popMonths;
    $popNetProfit = $stats['avg_profit'] ?? 0;
@endphp

<div x-data="{ open: false }" class="relative inline-block text-left" @mouseenter="open = true" @mouseleave="open = false" @click.stop>
    <!-- Trigger Slot / Icon -->
    <button type="button" class="text-gray-400 hover:text-emerald-500 transition-colors focus:outline-none flex items-center gap-1 cursor-pointer">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
    </button>

    <!-- Popover Card -->
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
         class="absolute z-[100] {{ $positionClass ?? 'bottom-full left-1/2 -translate-x-1/2 mb-3' }} w-80 sm:w-96 bg-white/95 dark:bg-gray-950/95 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-800 rounded-2xl shadow-2xl p-4 backdrop-blur-xl pointer-events-auto text-left font-sans transition-colors duration-200">
        
        <div class="flex items-center justify-between pb-2.5 mb-3 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <x-heroicon-s-calculator class="w-3.5 h-3.5" />
                </div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-900 dark:text-gray-200">Mtl. Gewinn-Berechnung</h4>
            </div>
            <span class="text-[10px] font-mono text-gray-500 dark:text-gray-400">Ø pro Monat</span>
        </div>

        <div class="space-y-2 text-xs">
            <!-- Positive: Fixe Einnahmen -->
            <div class="flex justify-between items-center text-emerald-600 dark:text-emerald-400">
                <span class="text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                    <span class="font-bold">+</span> Regelmäßige Einnahmen
                </span>
                <span class="font-mono font-bold">+{{ number_format($popFixInc, 2, ',', '.') }} €</span>
            </div>

            <!-- Positive: Shop Umsatz -->
            <div class="flex justify-between items-center text-emerald-600 dark:text-emerald-400">
                <span class="text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                    <span class="font-bold">+</span> Shop-Umsatz (netto)
                </span>
                <span class="font-mono font-bold">+{{ number_format($popShopRev, 2, ',', '.') }} €</span>
            </div>

            <div class="h-px bg-gray-200 dark:bg-gray-800/80 my-1"></div>

            <!-- Negative: Private Fixkosten -->
            <div class="flex justify-between items-center text-rose-600 dark:text-rose-400">
                <span class="text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                    <span class="font-bold">-</span> Private Fixkosten
                </span>
                <span class="font-mono font-bold">-{{ number_format($popFixPriv, 2, ',', '.') }} €</span>
            </div>

            <!-- Negative: Gewerbliche Fixkosten -->
            <div class="flex justify-between items-center text-rose-600 dark:text-rose-400">
                <span class="text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                    <span class="font-bold">-</span> Gewerbliche Fixkosten
                </span>
                <span class="font-mono font-bold">-{{ number_format($popFixGew, 2, ',', '.') }} €</span>
            </div>

            <!-- Negative: Sonderausgaben / Variabel -->
            <div class="flex justify-between items-center text-rose-600 dark:text-rose-400">
                <span class="text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                    <span class="font-bold">-</span> Sonder- & Einzelausgaben
                </span>
                <span class="font-mono font-bold">-{{ number_format($popVarExp, 2, ',', '.') }} €</span>
            </div>

            <div class="h-px bg-gray-200 dark:bg-gray-800 my-2"></div>

            <!-- Result -->
            <div class="flex justify-between items-center pt-1 text-sm bg-gray-50 dark:bg-gray-900/60 p-2.5 rounded-xl border border-gray-200 dark:border-gray-800">
                <span class="font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                    <span>=</span> Ø Mtl. Reingewinn
                </span>
                <div class="text-right">
                    <span class="font-mono font-black text-base {{ $popNetProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        {{ number_format($popNetProfit, 2, ',', '.') }} €
                    </span>
                    <span class="block text-[9px] text-gray-500 dark:text-gray-400 font-sans font-medium">gerundet ~{{ number_format($popNetProfit, 0, ',', '.') }} €</span>
                </div>
            </div>
        </div>

        <p class="mt-3 text-[10px] text-gray-500 dark:text-gray-400 leading-normal border-t border-gray-200 dark:border-gray-800/80 pt-2">
            Basis: Gesamteinnahmen abzüglich sämtlicher privater und gewerblicher Fix- und Sonderkosten im gewählten Zeitraum.
        </p>

        <!-- Arrow (if bottom-full) -->
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-white dark:border-t-gray-950"></div>
    </div>
</div>

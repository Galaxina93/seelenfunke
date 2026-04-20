@props(['vouchers'])

<div class="w-full relative rounded-2xl overflow-hidden border border-gray-800 bg-gray-950 flex flex-col shadow-[0_0_40px_rgba(0,0,0,0.3)]"
     x-data="{
        page: 1,
        itemsPerPage: 10,
        get vouchers() {
            return {{ \Illuminate\Support\Js::from($vouchers) }};
        },
        get totalPages() {
            return Math.max(1, Math.ceil(this.vouchers.length / this.itemsPerPage));
        },
        get paginatedVouchers() {
            let start = (this.page - 1) * this.itemsPerPage;
            let end = start + this.itemsPerPage;
            return this.vouchers.slice(start, end);
        },
        nextPage() { if (this.page < this.totalPages) this.page++; },
        prevPage() { if (this.page > 1) this.page--; }
     }">
    
    <div class="overflow-x-auto w-full custom-scrollbar">
        <!-- Desktop/Tablet Table -->
        <table class="w-full text-left whitespace-nowrap hidden sm:table">
            <thead class="bg-gray-900/80 border-b border-gray-800 sticky top-0 z-10 backdrop-blur-sm">
                <tr>
                    <th class="px-6 py-4 text-sm font-medium text-gray-400">Bezeichnung</th>
                    <th class="px-6 py-4 text-sm font-medium text-gray-400">Code</th>
                    <th class="px-6 py-4 text-sm font-medium text-gray-400">Wert</th>
                    <th class="px-6 py-4 text-sm font-medium text-gray-400">Nutzung</th>
                    <th class="px-6 py-4 text-sm font-medium text-gray-400">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800/50">
                <template x-for="v in paginatedVouchers" :key="v.id || v.code">
                    <tr class="hover:bg-gray-800/30 transition-colors group">
                        <!-- Typ / Titel -->
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="text-sm text-gray-200" x-text="v.title || 'Standard Gutschein'"></span>
                                <span class="text-[10px] uppercase font-bold tracking-wider text-gray-500" x-text="v.mode || 'Manuell'"></span>
                            </div>
                        </td>
                        <!-- Code -->
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-[color:var(--theme-color-10)] border border-[color:var(--theme-color-30)] flex items-center justify-center transition-colors shrink-0">
                                    <i class="bi bi-tag-fill text-[color:var(--theme-color)] text-xs"></i>
                                </div>
                                <span class="font-mono text-white text-sm tracking-wider" x-text="v.code || 'N/A'"></span>
                            </div>
                        </td>
                        <!-- Wert -->
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-300 font-bold bg-gray-900 shadow-inner px-3 py-1.5 rounded-md border border-gray-800 flex items-center w-fit gap-2">
                                <i class="bi bi-piggy-bank text-[color:var(--theme-color)] text-xs"></i>
                                <span x-text="v.value || '-'"></span>
                            </span>
                        </td>
                        <!-- Nutzung -->
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium transition-colors" 
                                      :class="(v.used_count || 0) > 0 ? 'text-[color:var(--theme-color)]' : 'text-gray-400'"
                                      x-text="(v.used_count || 0) + ' Einlösungen'">
                                </span>
                                <template x-if="v.usage_limit">
                                    <span class="text-xs text-gray-500 mt-0.5" x-text="'Max limit: ' + v.usage_limit"></span>
                                </template>
                            </div>
                        </td>
                        <!-- Status -->
                        <td class="px-6 py-4">
                            <template x-if="v.is_active || v.is_active === undefined">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold uppercase tracking-wider bg-[color:var(--theme-color-10)] text-[color:var(--theme-color)] border border-[color:var(--theme-color-30)] shadow-[0_0_10px_var(--theme-color-10)]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--theme-color)] animate-pulse mr-1.5"></span>
                                    Aktiv
                                </span>
                            </template>
                            <template x-if="v.is_active === false">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold uppercase tracking-wider bg-gray-800/80 text-gray-500 border border-gray-700">
                                    Inaktiv
                                </span>
                            </template>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <!-- Mobile Layout -->
        <div class="sm:hidden flex flex-col divide-y divide-gray-800/50">
            <template x-for="v in paginatedVouchers" :key="v.id || v.code">
                <div class="p-5 flex flex-col gap-4 hover:bg-gray-800/20 active:bg-gray-800/40 transition-colors cursor-default">
                    <div class="flex justify-between items-start gap-4">
                        <div class="flex items-center gap-3 w-full">
                            <div class="w-12 h-12 rounded-xl bg-[color:var(--theme-color-10)] border border-[color:var(--theme-color-20)] flex items-center justify-center shrink-0 shadow-[0_0_10px_var(--theme-color-10)]">
                                <i class="bi bi-tag-fill text-[color:var(--theme-color)] text-xl"></i>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <span class="font-mono text-white text-lg font-bold tracking-wider truncate" x-text="v.code || 'N/A'"></span>
                                <span class="text-xs font-medium text-[color:var(--theme-color)] truncate" x-text="v.title || 'Gutschein'"></span>
                            </div>
                        </div>
                        <div class="shrink-0 flex flex-col items-end gap-1.5 pt-1">
                            <template x-if="v.is_active || v.is_active === undefined">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-[color:var(--theme-color-10)] text-[color:var(--theme-color)] border border-[color:var(--theme-color-20)] shadow-sm">
                                    Aktiv
                                </span>
                            </template>
                            <template x-if="v.is_active === false">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-gray-800 text-gray-500 border border-gray-700">
                                    Inaktiv
                                </span>
                            </template>
                            <span class="text-[9px] uppercase font-bold tracking-widest text-gray-500 bg-gray-900 border border-gray-800 px-1.5 py-0.5 rounded-sm" x-text="v.mode || 'Manuell'"></span>
                        </div>
                    </div>
                    
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-3 bg-gray-900/50 rounded-lg p-3 border border-gray-800/80 shadow-inner">
                        <div class="flex flex-col items-center justify-center relative">
                            <span class="text-[9px] text-gray-500 uppercase tracking-widest mb-1.5 font-semibold">Rabatt</span>
                            <span class="text-sm font-bold text-gray-200" x-text="v.value || '-'"></span>
                            <div class="absolute right-0 top-[10%] bottom-[10%] w-px bg-gray-800"></div>
                        </div>
                        <div class="flex flex-col items-center justify-center relative">
                            <span class="text-[9px] text-gray-500 uppercase tracking-widest mb-1.5 font-semibold">Benutzt</span>
                            <span class="text-sm font-bold" :class="(v.used_count || 0) > 0 ? 'text-[color:var(--theme-color)]' : 'text-gray-400'" x-text="v.used_count || 0"></span>
                            <div class="absolute right-0 top-[10%] bottom-[10%] w-px bg-gray-800"></div>
                        </div>
                        <div class="flex flex-col items-center justify-center">
                            <span class="text-[9px] text-gray-500 uppercase tracking-widest mb-1.5 font-semibold">Maximal</span>
                            <span class="text-sm font-bold text-gray-400" x-text="v.usage_limit || 'Unbegrenzt'"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Pagination Footer -->
    <div class="px-5 py-4 bg-gray-900/80 border-t border-gray-800 flex items-center justify-between" x-show="totalPages > 1" x-cloak>
        <div class="text-xs text-gray-400 hidden sm:block">
            Zeige <span class="font-bold text-white px-1" x-text="Math.min(vouchers.length, (page - 1) * itemsPerPage + 1)"></span> 
            bis <span class="font-bold text-white px-1" x-text="Math.min(vouchers.length, page * itemsPerPage)"></span> 
            von <span class="font-bold text-white px-1" x-text="vouchers.length"></span> Einträgen
        </div>
        <div class="text-sm font-medium text-gray-300 sm:hidden">
            <span class="text-white" x-text="page"></span> / <span x-text="totalPages"></span>
        </div>
        
        <div class="flex gap-2 w-full sm:w-auto justify-end">
            <button @click="prevPage" :disabled="page === 1" class="px-4 py-2 sm:py-1.5 bg-gray-800 text-gray-300 rounded-md hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold tracking-wider uppercase transition-all cursor-pointer border border-gray-700 shadow-sm flex items-center gap-2">
                <i class="bi bi-chevron-left"></i> <span class="hidden sm:inline">Zurück</span>
            </button>
            <button @click="nextPage" :disabled="page === totalPages" class="px-4 py-2 sm:py-1.5 bg-[color:var(--theme-color-15)] text-[color:var(--theme-color)] border border-[color:var(--theme-color-30)] rounded-md hover:bg-[color:var(--theme-color-20)] hover:border-[color:var(--theme-color-40)] disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold tracking-wider uppercase transition-all cursor-pointer shadow-sm flex items-center gap-2">
                <span class="hidden sm:inline">Weiter</span> <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
    
    <div class="w-full flex justify-center py-8" x-show="vouchers.length === 0" x-cloak>
        <span class="text-sm text-gray-500 font-medium">Keine Gutscheine vorhanden.</span>
    </div>
</div>

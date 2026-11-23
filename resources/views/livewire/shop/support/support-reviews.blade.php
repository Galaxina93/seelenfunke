<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80;" class="px-4 sm:px-6 lg:px-8 py-6 w-full max-w-9xl mx-auto space-y-6 text-gray-700 dark:text-gray-300">

    {{-- HEADER (Clean Design ohne Subtexte) --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Bewertungen</h1>

        <button type="button"
                wire:click="$set('filterStatus', '{{ $filterStatus === 'open' ? 'all' : 'open' }}')"
                class="inline-flex items-center gap-2.5 px-4 py-2.5 rounded-2xl border transition-colors text-xs font-black uppercase tracking-widest {{ $openLowRatingCount > 0 ? 'bg-red-500/10 border-red-500/30 text-red-600 dark:text-red-400' : 'bg-white dark:bg-gray-900/80 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300' }}">
            <span class="w-2 h-2 rounded-full {{ $openLowRatingCount > 0 ? 'bg-red-500 animate-pulse' : 'bg-emerald-500' }}"></span>
            <span>Offen (1-3 ★): {{ $openLowRatingCount }}</span>
        </button>
    </div>

    {{-- KOMPAKTE BEREICHS-KPI-KACHELN --}}
    <div class="grid grid-cols-1 {{ count($areaKpis) === 2 ? 'md:grid-cols-2' : 'md:grid-cols-3' }} gap-4">
        @foreach($areaKpis as $kpi)
            <button type="button"
                    wire:key="kpi-area-{{ $kpi['area'] }}"
                    wire:click="$set('filterArea', '{{ $filterArea === $kpi['area'] ? 'all' : $kpi['area'] }}')"
                    class="text-left bg-white dark:bg-gray-900/80 border {{ $filterArea === $kpi['area'] ? 'border-[var(--theme-color)] ring-1 ring-[var(--theme-color-30)]' : 'border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)]' }} rounded-3xl p-5 shadow-sm transition-all flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <span class="block text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 truncate">{{ $kpi['label'] }}</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-gray-900 dark:text-white">{{ $kpi['count'] > 0 ? number_format($kpi['avg'], 1, ',', '.') : '0,0' }}</span>
                        <span class="text-sm font-bold text-[var(--theme-color)]">★</span>
                    </div>
                </div>
                <div class="shrink-0 px-3 py-1.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-right">
                    <span class="block text-sm font-black text-gray-900 dark:text-white">{{ $kpi['count'] }}</span>
                    <span class="block text-[9px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Gesamt</span>
                </div>
            </button>
        @endforeach
    </div>

    {{-- FILTER-LEISTE --}}
    <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 rounded-3xl p-4 shadow-sm flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-3">
        {{-- Status-Pills --}}
        <div class="inline-flex bg-gray-100 dark:bg-gray-950 p-1 rounded-2xl border border-gray-200 dark:border-gray-800 shrink-0">
            <button type="button" wire:click="$set('filterStatus', 'all')"
                    class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $filterStatus === 'all' ? 'bg-[var(--theme-color)] text-gray-900 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                Alle
            </button>
            <button type="button" wire:click="$set('filterStatus', 'open')"
                    class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $filterStatus === 'open' ? 'bg-[var(--theme-color)] text-gray-900 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                Offen
            </button>
            <button type="button" wire:click="$set('filterStatus', 'done')"
                    class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $filterStatus === 'done' ? 'bg-[var(--theme-color)] text-gray-900 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                Erledigt
            </button>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 flex-1 xl:justify-end">
            {{-- Bereich-Filter --}}
            <select wire:model.live="filterArea"
                    aria-label="Bereich filtern"
                    class="rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 px-3.5 py-2.5 text-xs font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none">
                <option value="all">Alle Bereiche</option>
                <option value="configurator">Konfigurator</option>
                <option value="funkenreich">Funkenreich</option>
                @if(\App\Http\Middleware\EnforceShopMaintenanceMode::isQuoteCalculatorEnabled())
                    <option value="calculator">Kalkulator</option>
                @endif
            </select>

            {{-- Sterne-Filter --}}
            <select wire:model.live="filterRating"
                    aria-label="Sterne filtern"
                    class="rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 px-3.5 py-2.5 text-xs font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none">
                <option value="all">Alle Sterne</option>
                <option value="low">1-3 ★</option>
                <option value="5">5 ★</option>
                <option value="4">4 ★</option>
                <option value="3">3 ★</option>
                <option value="2">2 ★</option>
                <option value="1">1 ★</option>
            </select>

            {{-- Suche --}}
            <div class="relative w-full sm:w-64">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Suchen..."
                       class="w-full rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 pl-9 pr-3.5 py-2.5 text-xs font-medium text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none">
                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 absolute left-3 top-2.5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
        </div>
    </div>

    {{-- SCHLANKE HAUPTTABELLE --}}
    <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 rounded-3xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800 text-left">
                <thead class="bg-gray-50 dark:bg-gray-950/60 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-4">Kunde</th>
                        <th class="px-4 py-4">Bereich</th>
                        <th class="px-4 py-4">Sterne</th>
                        <th class="px-4 py-4">Hinweis</th>
                        <th class="px-4 py-4">Gerät</th>
                        <th class="px-4 py-4">Datum</th>
                        <th class="px-5 py-4 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800/70">
                    @forelse($reviews as $rev)
                        @php
                            $custName = $rev->customer
                                ? trim(($rev->customer->first_name ?? '') . ' ' . ($rev->customer->last_name ?? ''))
                                : 'Gelöschter Kunde';
                            $custEmail = $rev->customer->email ?? '';
                            $hasComment = !empty($rev->comment);
                        @endphp
                        <tr wire:key="support-exp-rev-{{ $rev->id }}" class="hover:bg-gray-50/70 dark:hover:bg-gray-800/30 transition-colors">
                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="text-xs font-bold text-gray-900 dark:text-white">{{ $custName !== '' ? $custName : 'Kunde' }}</div>
                                @if($custEmail !== '')
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $custEmail }}</div>
                                @endif
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-lg bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[10px] font-black uppercase tracking-wider text-gray-900 dark:text-white">
                                    {{ $rev->area_label }}
                                </span>
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="inline-flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        <span class="text-sm {{ $i <= (int) $rev->rating ? 'text-[var(--theme-color)]' : 'text-gray-300 dark:text-gray-700' }}">★</span>
                                    @endfor
                                </div>
                            </td>

                            <td class="px-4 py-4 max-w-xs">
                                @if($hasComment)
                                    <span class="text-xs text-gray-700 dark:text-gray-300 truncate block max-w-[240px]">{{ \Illuminate\Support\Str::limit($rev->comment, 60) }}</span>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-600">—</span>
                                @endif
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap text-xs font-semibold text-gray-600 dark:text-gray-400">
                                {{ $rev->device_type === 'mobile' ? 'Mobil' : 'Desktop' }}
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                {{ $rev->updated_at?->format('d.m.Y H:i') }}
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-right">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    @if($hasComment || !empty($rev->product_name))
                                        <button type="button"
                                                wire:click="openDetails('{{ $rev->id }}')"
                                                class="px-2.5 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-gray-200 hover:border-[var(--theme-color)] transition-colors">
                                            Details
                                        </button>
                                    @endif

                                    <button type="button"
                                            wire:click="toggleStatus('{{ $rev->id }}')"
                                            class="px-2.5 py-1.5 rounded-lg border text-[10px] font-black uppercase tracking-wider transition-colors {{ $rev->status === 'open' ? 'bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-400 hover:bg-emerald-500/15 hover:border-emerald-500/40 hover:text-emerald-700 dark:hover:text-emerald-400' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                        {{ $rev->status === 'open' ? 'Offen' : 'Erledigt' }}
                                    </button>

                                    <button type="button"
                                            wire:click="deleteReview('{{ $rev->id }}')"
                                            wire:confirm="Eintrag wirklich löschen?"
                                            aria-label="Löschen"
                                            title="Löschen"
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:border-red-500/40 hover:text-red-600 dark:hover:text-red-400 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-xs font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">
                                Keine Bewertungen gefunden
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($reviews->hasPages())
            <div class="p-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950/40">
                {{ $reviews->links() }}
            </div>
        @endif
    </div>

    {{-- DETAILS-MODAL --}}
    @if($detailReview)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" wire:click.self="closeDetails">
            <div class="w-full max-w-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 shadow-2xl space-y-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)]">{{ $detailReview->area_label }}</span>
                        <h3 class="text-lg font-serif font-bold text-gray-900 dark:text-white mt-0.5">
                            {{ trim(($detailReview->customer->first_name ?? '') . ' ' . ($detailReview->customer->last_name ?? '')) ?: 'Kunde' }}
                        </h3>
                        @if(!empty($detailReview->customer->email))
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $detailReview->customer->email }}</p>
                        @endif
                    </div>

                    <button type="button" wire:click="closeDetails" class="p-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <span class="font-bold text-[var(--theme-color)]">{{ $detailReview->rating }} / 5 ★</span>
                    <span class="px-2.5 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-semibold">
                        {{ $detailReview->device_type === 'mobile' ? 'Mobil' : 'Desktop' }}
                    </span>
                    @if(!empty($detailReview->product_name))
                        <span class="px-2.5 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-semibold">
                            {{ $detailReview->product_name }}
                        </span>
                    @endif
                </div>

                <div class="rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-4 text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line">
                    {{ $detailReview->comment ?: '—' }}
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button"
                            wire:click="toggleStatus('{{ $detailReview->id }}')"
                            class="px-4 py-2 rounded-xl bg-[var(--theme-color)] text-xs font-black uppercase tracking-widest text-gray-900">
                        {{ $detailReview->status === 'open' ? 'Als erledigt markieren' : 'Wieder öffnen' }}
                    </button>
                    <button type="button"
                            wire:click="closeDetails"
                            class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-300">
                        Schließen
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>

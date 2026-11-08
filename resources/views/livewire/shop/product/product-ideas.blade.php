<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">
    <style>
        .ideas-scroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.4) transparent;
        }
        .ideas-scroll:hover {
            scrollbar-color: var(--theme-color) rgba(148, 163, 184, 0.12);
        }
        .ideas-scroll::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        .ideas-scroll::-webkit-scrollbar-button {
            display: none;
            width: 0;
            height: 0;
        }
        .ideas-scroll::-webkit-scrollbar-track {
            background: rgba(148, 163, 184, 0.08);
            border-radius: 9999px;
        }
        .ideas-scroll::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.45);
            border-radius: 9999px;
            transition: background-color 0.2s ease;
        }
        .ideas-scroll:hover::-webkit-scrollbar-thumb {
            background: var(--theme-color);
        }
    </style>

    @if(!$showModal)
        @php
            $totalCount = $ideas->count();
            $topCount = $ideas->where('is_top_priority', true)->count();
            $sampleCount = $ideas->where('status', 'sample_needed')->count();
        @endphp

        <!-- Top Action & Filter Bar -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 mb-4 shadow-sm animate-fade-in-up">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white tracking-tight">Produkt-Ideen</h2>
                    <span class="px-2 py-0.5 rounded-md text-[11px] font-black bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">{{ $totalCount }} Ideen</span>
                    @if($topCount > 0)
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-black bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">🏆 {{ $topCount }} Top-Favorit</span>
                    @endif
                    @if($sampleCount > 0)
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">📸 {{ $sampleCount }} Muster/Fotos offen</span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Schnellfilter Status -->
                    <div class="inline-flex items-center bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 text-xs font-bold">
                        <button type="button" wire:click="setStatusFilter('all')" class="px-2.5 py-1 rounded-lg transition-all cursor-pointer {{ $statusFilter === 'all' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                            Alle
                        </button>
                        <button type="button" wire:click="setStatusFilter('top_idea')" class="px-2.5 py-1 rounded-lg transition-all flex items-center gap-1 cursor-pointer {{ $statusFilter === 'top_idea' ? 'bg-white dark:bg-gray-800 text-amber-600 dark:text-amber-400 shadow-sm' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                            <span>🏆</span> Top
                        </button>
                        <button type="button" wire:click="setStatusFilter('sample_needed')" class="px-2.5 py-1 rounded-lg transition-all flex items-center gap-1 cursor-pointer {{ $statusFilter === 'sample_needed' ? 'bg-white dark:bg-gray-800 text-sky-600 dark:text-sky-400 shadow-sm' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                            <span>📸</span> Muster/Fotos
                        </button>
                        <button type="button" wire:click="setStatusFilter('planned')" class="px-2.5 py-1 rounded-lg transition-all cursor-pointer {{ $statusFilter === 'planned' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                            Geplant
                        </button>
                        <button type="button" wire:click="setStatusFilter('transferred')" class="px-2.5 py-1 rounded-lg transition-all cursor-pointer {{ $statusFilter === 'transferred' ? 'bg-white dark:bg-gray-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                            In Kollektion
                        </button>
                    </div>

                    <!-- Live-Suche -->
                    <div class="relative w-full sm:w-56">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input type="text" wire:model.live.debounce.200ms="search" placeholder="Produkt, Material, Händler..." class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl pl-8 pr-7 py-1.5 text-xs font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] placeholder-gray-400">
                        @if($search !== '')
                            <button type="button" wire:click="$set('search', '')" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                            </button>
                        @endif
                    </div>

                    <button type="button" wire:click="create" class="bg-[var(--theme-color)] hover:opacity-90 text-white dark:text-gray-900 font-bold px-3.5 py-1.5 rounded-xl text-xs shadow-sm transition-all flex items-center gap-1.5 shrink-0 cursor-pointer">
                        <x-heroicon-o-plus class="w-4 h-4" />
                        Produkt-Idee anlegen
                    </button>
                </div>
            </div>
        </div>

        <!-- Schlanke Haupttabelle (Clean Design ohne lange Beschreibungstexte) -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm dark:shadow-xl overflow-hidden animate-fade-in-up">
            <div class="overflow-x-auto ideas-scroll">
                <table class="w-full lg:table-fixed text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/90 dark:bg-gray-950/80 border-b border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            <th class="py-2.5 pl-4 pr-3 lg:w-[27%]">Rang &amp; Produkt-Idee</th>
                            <th class="py-2.5 px-3 lg:w-[32%]">Einzel-Komponenten &amp; Kauflinks</th>
                            <th class="py-2.5 px-3 lg:w-[13%]">Einkauf (Rohlinge)</th>
                            <th class="py-2.5 px-3 lg:w-[14%]">Ziel-VK &amp; Gewinn</th>
                            <th class="py-2.5 px-3 lg:w-[8%]">Status</th>
                            <th class="py-2.5 pl-2 pr-4 text-right lg:w-[6%]">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800/70 text-xs">
                        @forelse($ideas as $idea)
                            @php
                                $isTop = (bool) $idea->is_top_priority;
                                $catLabel = match($idea->category) {
                                    'echtschmuck' => 'Echtschmuck (Gold/Silber)',
                                    'glas_kristall' => 'Glas & Kristall',
                                    'metall_schiefer' => 'Metall & Schiefer',
                                    'geschenkset' => 'Geschenk-Set',
                                    default => 'Manufaktur-Unikat',
                                };
                                $statusBadge = match($idea->status) {
                                    'top_idea' => ['label' => '🏆 Top 1', 'class' => 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30'],
                                    'sample_needed' => ['label' => '📸 Muster/Fotos', 'class' => 'bg-sky-500/15 text-sky-700 dark:text-sky-400 border-sky-500/30'],
                                    'ready' => ['label' => '✅ Bereit', 'class' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30'],
                                    'transferred' => ['label' => '👑 In Kollektion', 'class' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30'],
                                    'discarded' => ['label' => 'Verworfen', 'class' => 'bg-gray-200 dark:bg-gray-800 text-gray-500 border-gray-300 dark:border-gray-700'],
                                    default => ['label' => '💡 Geplant', 'class' => 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700'],
                                };
                                $components = is_array($idea->components_links) ? $idea->components_links : [];
                            @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/30 transition-colors align-top {{ $isTop ? 'bg-amber-500/[0.03] dark:bg-amber-500/[0.04]' : '' }}">
                                <!-- 1. Rang & Produkt-Idee -->
                                <td class="py-3 pl-4 pr-3">
                                    <div class="flex items-start gap-2.5 min-w-0">
                                        <button type="button"
                                                wire:click="toggleTopPriority('{{ $idea->id }}')"
                                                title="{{ $isTop ? 'Top-1-Markierung aktiv (Klicken zum Umschalten)' : 'Als Top-Favorit markieren' }}"
                                                class="w-8 h-8 rounded-lg border flex flex-col items-center justify-center shrink-0 transition-all cursor-pointer {{ $isTop ? 'bg-amber-500/15 border-amber-500/40 text-amber-600 dark:text-amber-400 shadow-xs' : 'bg-gray-100 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:border-amber-500/40' }}">
                                            <span class="text-[10px] font-black leading-none">#{{ $idea->rank }}</span>
                                            @if($isTop)
                                                <span class="text-[8px] font-black uppercase tracking-tighter text-amber-600 dark:text-amber-400">TOP</span>
                                            @endif
                                        </button>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                @if($isTop)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/40 shrink-0">🏆 TOP 1 PRODUKT</span>
                                                @endif
                                                <span class="font-bold text-gray-900 dark:text-white text-xs leading-snug" title="{{ $idea->notes }}">
                                                    {{ $idea->title }}
                                                </span>
                                            </div>
                                            @if($idea->material_summary)
                                                <div class="text-[11px] text-gray-500 dark:text-gray-400 truncate mt-0.5" title="{{ $idea->material_summary }}">
                                                    {{ $idea->material_summary }}
                                                </div>
                                            @endif
                                            <div class="flex items-center gap-2 mt-1 text-[10px] text-gray-400 dark:text-gray-500">
                                                <span>{{ $catLabel }}</span>
                                                @if($idea->supplier_name)
                                                    <span>·</span>
                                                    <span class="font-medium text-gray-600 dark:text-gray-400 truncate">{{ $idea->supplier_name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- 2. Einzel-Komponenten & Geprüfte Kauflinks -->
                                <td class="py-3 px-3">
                                    <div class="flex flex-col gap-1.5">
                                        @forelse($components as $comp)
                                            @php
                                                $cTitle = (string) ($comp['title'] ?? 'Komponente');
                                                $cSku = (string) ($comp['sku'] ?? '');
                                                $cGross = (int) ($comp['price_gross_cents'] ?? 0);
                                                $cNet = (int) ($comp['price_net_cents'] ?? 0);
                                                $cUrl = (string) ($comp['url'] ?? '');
                                            @endphp
                                            <a href="{{ $cUrl }}" target="_blank" rel="noopener noreferrer"
                                               class="group/link flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-950/90 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] transition-all">
                                                <div class="min-w-0 flex-1">
                                                    <div class="font-bold text-gray-800 dark:text-gray-200 group-hover/link:text-[var(--theme-color)] text-[11px] truncate">
                                                        {{ $cTitle }}
                                                    </div>
                                                    @if($cSku || !empty($comp['spec']))
                                                        <div class="text-[10px] text-gray-400 dark:text-gray-500 truncate">
                                                            {{ $cSku }}{{ $cSku && !empty($comp['spec']) ? ' · ' : '' }}{{ $comp['spec'] ?? '' }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    @if($cGross > 0)
                                                        <span class="px-1.5 py-0.5 rounded bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-[10px] font-bold text-gray-700 dark:text-gray-300" title="Netto: {{ number_format($cNet / 100, 2, ',', '.') }} €">
                                                            {{ number_format($cGross / 100, 2, ',', '.') }} €
                                                        </span>
                                                    @endif
                                                    <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5 text-gray-400 group-hover/link:text-[var(--theme-color)] shrink-0" />
                                                </div>
                                            </a>
                                        @empty
                                            <span class="text-[11px] text-gray-400">Keine Einzel-Links hinterlegt</span>
                                        @endforelse
                                    </div>
                                </td>

                                <!-- 3. Einkauf (Netto / Brutto) -->
                                <td class="py-3 px-3">
                                    <div class="font-bold text-gray-900 dark:text-white text-xs">
                                        {{ number_format($idea->purchase_price_net_cents / 100, 2, ',', '.') }} € <span class="text-[10px] font-normal text-gray-400">netto</span>
                                    </div>
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ number_format($idea->purchase_price_gross_cents / 100, 2, ',', '.') }} € brutto
                                    </div>
                                    <div class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">
                                        + {{ number_format(($idea->packaging_cost_cents + $idea->shipping_cost_cents) / 100, 2, ',', '.') }} € Box &amp; DHL
                                    </div>
                                </td>

                                <!-- 4. Ziel-VK & Netto-Gewinn -->
                                <td class="py-3 px-3">
                                    <div class="font-black text-gray-900 dark:text-white text-sm">
                                        {{ number_format($idea->target_price_gross_cents / 100, 2, ',', '.') }} € <span class="text-[10px] font-semibold text-gray-400">VK</span>
                                    </div>
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-black {{ $idea->expected_profit_cents > 0 ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30' : 'bg-gray-100 dark:bg-gray-800 text-gray-500' }}"
                                              title="Reiner Netto-Deckungsbeitrag pro Stück nach Abzug von 19 % MwSt., Rohling-EK, Westpack-Box, versichertem DHL-Versand, Laser & Payment">
                                            {{ $idea->expected_profit_cents >= 0 ? '+' : '' }}{{ number_format($idea->expected_profit_cents / 100, 2, ',', '.') }} € Gewinn
                                        </span>
                                    </div>
                                </td>

                                <!-- 5. Status -->
                                <td class="py-3 px-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $statusBadge['class'] }}">
                                        {{ $statusBadge['label'] }}
                                    </span>
                                </td>

                                <!-- 6. Aktion -->
                                <td class="py-3 pl-2 pr-4 text-right">
                                    <div class="inline-flex items-center justify-end gap-1">
                                        <button type="button" wire:click="edit('{{ $idea->id }}')" title="Bearbeiten &amp; Kalkulation/Notizen öffnen"
                                                class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-[var(--theme-color)] text-gray-600 dark:text-gray-300 hover:text-white dark:hover:text-gray-900 transition-colors cursor-pointer">
                                            <x-heroicon-o-pencil-square class="w-4 h-4" />
                                        </button>
                                        <button type="button" wire:click="delete('{{ $idea->id }}')" wire:confirm="Möchtest du diese Produkt-Idee wirklich löschen?" title="Löschen"
                                                class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-red-500/20 text-gray-500 hover:text-red-500 transition-colors cursor-pointer">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-400 dark:text-gray-500 text-xs">
                                    Noch keine Produkt-Ideen für diesen Filter vorhanden.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <!-- Bearbeiten / Neu anlegen Modal-Ansicht -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm animate-fade-in-up">
            <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-200 dark:border-gray-800">
                <div class="flex items-center gap-2.5">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">
                        {{ $isEditing ? 'Produkt-Idee bearbeiten' : 'Neue Produkt-Idee anlegen' }}
                    </h2>
                    @if($is_top_priority)
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/40">🏆 TOP 1 FAVORIT</span>
                    @endif
                </div>
                <button type="button" wire:click="resetForm" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer">
                    Zurück zur Übersicht
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Linke Spalte: Stammdaten & Einzel-Komponenten mit Kauflinks -->
                <div class="lg:col-span-7 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">Rang</label>
                            <input type="number" wire:model="rank" min="1" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white">
                        </div>
                        <div class="sm:col-span-10">
                            <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">Bezeichnung der Produkt-Idee *</label>
                            <input type="text" wire:model="title" placeholder="z. B. Das Echtgold Seelen-Herz – 585er Gold-Herz & 333er Gold-Panzerkette (Set)" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white">
                            @error('title') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">Kategorie</label>
                            <select wire:model="category" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-semibold text-gray-900 dark:text-white">
                                <option value="echtschmuck">Echtschmuck (Gold/Silber)</option>
                                <option value="glas_kristall">Glas &amp; Kristall</option>
                                <option value="metall_schiefer">Metall &amp; Schiefer</option>
                                <option value="geschenkset">Geschenk-Set</option>
                                <option value="sonstiges">Sonstiges</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">Status</label>
                            <select wire:model="status" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-semibold text-gray-900 dark:text-white">
                                <option value="top_idea">🏆 Top 1 Favorit</option>
                                <option value="sample_needed">📸 Muster / Fotos nötig</option>
                                <option value="planned">💡 Geplant</option>
                                <option value="ready">✅ Bereit für Shop</option>
                                <option value="transferred">👑 In Kollektion übertragen</option>
                                <option value="discarded">Verworfen</option>
                            </select>
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" wire:model="is_top_priority" class="rounded border-gray-300 dark:border-gray-700 text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                                <span class="text-xs font-bold text-gray-800 dark:text-gray-200">🏆 Als Top-1-Produkt markieren</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">Material &amp; Maße (Kurzinfo)</label>
                            <input type="text" wire:model="material_summary" placeholder="z. B. 585er Gold-Herz (13×14 mm) + 333er Panzerkette (0,8 mm)" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">Händler / Bezugsquelle</label>
                            <input type="text" wire:model="supplier_name" placeholder="z. B. Silberketten-Store.de" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white">
                        </div>
                    </div>

                    <!-- Einzel-Komponenten & Kauflinks (z. B. Anhänger + Kette) -->
                    <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950/70 border border-gray-200 dark:border-gray-800">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <span class="text-xs font-bold text-gray-900 dark:text-white">Einzel-Komponenten &amp; Direkt-Kauflinks (z. B. Herz-Anhänger + Halskette)</span>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="sumComponentPrices" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-700 cursor-pointer">
                                    Σ EK aus Komponenten berechnen
                                </button>
                                <button type="button" wire:click="addComponentRow" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-[var(--theme-color)] text-white dark:text-gray-900 cursor-pointer">
                                    + Komponente / Link
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            @foreach($components_links as $idx => $comp)
                                <div class="p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-2">
                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                                        <div class="sm:col-span-6">
                                            <input type="text" wire:model="components_links.{{ $idx }}.title" placeholder="Bezeichnung (z. B. 585er Gold-Herz)" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-gray-900 dark:text-white">
                                        </div>
                                        <div class="sm:col-span-3">
                                            <input type="text" wire:model="components_links.{{ $idx }}.sku" placeholder="Art.-Nr. (z. B. 235559.400)" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg px-2.5 py-1.5 text-xs text-gray-900 dark:text-white">
                                        </div>
                                        <div class="sm:col-span-3 flex items-center gap-1.5">
                                            <input type="text" wire:model.blur="components_links.{{ $idx }}.price_gross_eur" wire:change="sumComponentPrices" placeholder="EK Brutto €" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg px-2.5 py-1.5 text-xs font-bold text-gray-900 dark:text-white">
                                            <button type="button" wire:click="removeComponentRow({{ $idx }})" class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 cursor-pointer" title="Komponente entfernen">
                                                <x-heroicon-o-trash class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                                        <div class="sm:col-span-4">
                                            <input type="text" wire:model="components_links.{{ $idx }}.spec" placeholder="Maße / Details (z. B. 13×14 mm, 0,8 g)" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg px-2.5 py-1.5 text-[11px] text-gray-700 dark:text-gray-300">
                                        </div>
                                        <div class="sm:col-span-8">
                                            <input type="url" wire:model="components_links.{{ $idx }}.url" placeholder="https://..." class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg px-2.5 py-1.5 text-[11px] text-gray-700 dark:text-gray-300">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Rechte Spalte: Preis-Kalkulator & Original-Foto / Influencer Notizen -->
                <div class="lg:col-span-5 space-y-4">
                    <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950/70 border border-gray-200 dark:border-gray-800 space-y-3">
                        <h3 class="text-xs font-bold text-gray-900 dark:text-white">Set- &amp; Margen-Kalkulation</h3>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">Rohlinge EK (Brutto €)</label>
                                <input type="text" wire:model.live.debounce.400ms="purchase_price_gross_eur" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">Rohlinge EK (Netto €)</label>
                                <input type="text" wire:model.live.debounce.400ms="purchase_price_net_eur" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">Verpackung Netto (€)</label>
                                <input type="text" wire:model.live.debounce.300ms="packaging_cost_eur" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" title="Standard: 1,06 € netto (Schwarze Westpack Schmuckbox)">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">DHL Versand Netto (€)</label>
                                <input type="text" wire:model.live.debounce.300ms="shipping_cost_eur" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" title="Standard: 4,50 € netto (Versichertes DHL-Paket bis 500 €)">
                            </div>
                        </div>

                        <div class="pt-2 border-t border-gray-200 dark:border-gray-800 grid grid-cols-2 gap-3 items-center">
                            <div>
                                <label class="block text-[11px] font-black text-gray-900 dark:text-white mb-1">Geplanter Shop-VK (Brutto €)</label>
                                <input type="text" wire:model.live.debounce.300ms="target_price_gross_eur" class="w-full bg-white dark:bg-gray-900 border-2 border-[var(--theme-color)] rounded-xl px-3 py-2 text-sm font-black text-gray-900 dark:text-white">
                            </div>
                            <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-right">
                                <div class="text-[10px] font-bold uppercase text-emerald-700 dark:text-emerald-400">Netto-Reingewinn / Stk.</div>
                                <div class="text-base font-black text-emerald-700 dark:text-emerald-400">
                                    {{ $this->liveCalculatedProfitCents >= 0 ? '+' : '' }}{{ number_format($this->liveCalculatedProfitCents / 100, 2, ',', '.') }} €
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">Notizen (Original-Fotos, Influencer-Muster, Laser-Setup &amp; Strategie)</label>
                        <textarea wire:model="notes" rows="7" placeholder="Notizen zu Musterbestellung, echten Produktfotos (keine KI-Bilder), Influencer-Versand und Laser-Einstellungen..." class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl p-3 text-xs text-gray-900 dark:text-white leading-relaxed"></textarea>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-5 mt-6 border-t border-gray-200 dark:border-gray-800">
                <button type="button" wire:click="resetForm" class="px-4 py-2 rounded-xl text-xs font-bold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 cursor-pointer">
                    Abbrechen
                </button>
                <button type="button" wire:click="save" class="px-5 py-2 rounded-xl text-xs font-bold bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm hover:opacity-90 cursor-pointer">
                    Produkt-Idee speichern
                </button>
            </div>
        </div>
    @endif
</div>

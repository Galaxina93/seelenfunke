<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">

    <!-- SCHLANKER HEADER MIT TAB-AUSWAHL -->
    <div class="bg-white dark:bg-gray-900/85 backdrop-blur-xl px-5 py-3.5 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[var(--theme-color-15)] border border-[var(--theme-color-30)] flex items-center justify-center shrink-0">
                <x-heroicon-s-archive-box class="w-5 h-5 text-[var(--theme-color)]" />
            </div>
            <h1 class="text-lg font-serif font-bold text-gray-900 dark:text-white">
                Verpackungen & LUCID
            </h1>
        </div>

        <div class="inline-flex p-1 rounded-xl bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 self-start sm:self-auto">
            <button
                type="button"
                wire:click="setTab('products')"
                class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'products' ? 'bg-[var(--theme-color)] text-gray-900 shadow-sm font-black' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
            >
                Produkterfassung
            </button>
            <button
                type="button"
                wire:click="setTab('catalog')"
                class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'catalog' ? 'bg-[var(--theme-color)] text-gray-900 shadow-sm font-black' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
            >
                Verpackungsmaterial
            </button>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- TAB 1: PRODUKTERFASSUNG                                               -->
    <!-- ===================================================================== -->
    @if($activeTab === 'products')
        <div class="bg-white dark:bg-gray-900/85 backdrop-blur-xl p-5 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 space-y-5">

            <!-- Zeile 1: Produkt auswählen + Vorlage aus Tab 2 zuweisen -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end pb-5 border-b border-gray-200 dark:border-gray-800">
                <div class="lg:col-span-5">
                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                        Produkt
                    </label>
                    <select wire:model.live="selectedProductId" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none cursor-pointer">
                        <option value="">-- Produkt wählen --</option>
                        @foreach($products as $prod)
                            @php $w = (int) $prod->packagings->sum('weight_grams'); @endphp
                            <option value="{{ $prod->id }}">
                                {{ $prod->name }} ({{ $w > 0 ? $w . ' g' : 'offen' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="lg:col-span-5">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Verpackung aus Katalog
                        </label>
                        <div class="flex items-center gap-3 text-[11px] text-gray-500 dark:text-gray-400">
                            <label class="inline-flex items-center gap-1 cursor-pointer">
                                <input type="radio" wire:model.live="assignMode" value="add" class="text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                                <span>Addieren</span>
                            </label>
                            <label class="inline-flex items-center gap-1 cursor-pointer">
                                <input type="radio" wire:model.live="assignMode" value="replace" class="text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                                <span>Ersetzen</span>
                            </label>
                        </div>
                    </div>
                    <select wire:model="selectedCatalogItemId" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none cursor-pointer">
                        <option value="">-- Verpackung wählen --</option>
                        @foreach($allCatalogItems as $catItem)
                            <option value="{{ $catItem->id }}">
                                {{ $catItem->name }} ({{ $catItem->weight_grams }} g · {{ number_format($catItem->unit_cost_cents / 100, 2, ',', '.') }} €)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="lg:col-span-2">
                    <button
                        type="button"
                        wire:click="assignCatalogItemToProduct"
                        class="w-full px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider bg-[var(--theme-color)] text-gray-900 hover:opacity-90 transition-all cursor-pointer"
                    >
                        Zuweisen
                    </button>
                </div>
            </div>

            @if($this->product)
                <!-- Gespeicherte Materialien am gewählten Produkt -->
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-gray-900 dark:text-white">
                                {{ $this->product->name }}
                            </span>
                            <span class="px-2 py-0.5 rounded-lg text-[11px] font-black bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                                {{ $packagings->sum('weight_grams') }} g
                            </span>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            EK: <strong class="text-[var(--theme-color)]">{{ number_format(((int) $this->product->packaging_cost) / 100, 2, ',', '.') }} €</strong>
                        </span>
                    </div>

                    <div class="space-y-2">
                        @forelse($packagings as $pkg)
                            <div class="flex items-center justify-between gap-3 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-950/60 hover:border-[var(--theme-color-40)] transition-colors">
                                <div class="flex flex-wrap items-center gap-2 min-w-0">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">
                                        {{ $availableTypes[$pkg->material_type] ?? $pkg->material_type }}
                                    </span>
                                    @if($pkg->label)
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-20)]">
                                            {{ $pkg->label }}
                                        </span>
                                    @endif
                                </div>

                                <div class="flex items-center gap-3 shrink-0">
                                    @if($editId === $pkg->id)
                                        <div class="flex items-center gap-1.5">
                                            <input type="number" wire:model="editWeightGrams" min="1" class="w-20 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-lg px-2 py-1 text-xs font-bold text-gray-900 dark:text-white outline-none">
                                            <span class="text-xs text-gray-500">g</span>
                                            <button wire:click="saveEdit" class="p-1 text-[var(--theme-color)] hover:bg-[var(--theme-color-10)] rounded"><x-heroicon-o-check class="w-4 h-4" /></button>
                                            <button wire:click="cancelEdit" class="p-1 text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-800 rounded"><x-heroicon-o-x-mark class="w-4 h-4" /></button>
                                        </div>
                                    @else
                                        <button type="button" wire:click="startEdit({{ $pkg->id }}, {{ $pkg->weight_grams }})" class="text-xs font-black text-[var(--theme-color)] hover:underline cursor-pointer">
                                            {{ $pkg->weight_grams }} g
                                        </button>
                                    @endif

                                    <button type="button" wire:click="deleteMaterial({{ $pkg->id }})" class="p-1 text-gray-400 hover:text-red-500 rounded-lg hover:bg-red-500/10 cursor-pointer" title="Entfernen">
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5 border border-dashed border-gray-200 dark:border-gray-800 rounded-xl text-xs text-gray-400">
                                Noch kein Material hinterlegt.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Zeile 3: Einzelnes Material manuell ergänzen -->
                <form wire:submit.prevent="addMaterial" class="pt-4 border-t border-gray-200 dark:border-gray-800 grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end">
                    <div class="sm:col-span-4">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Material</label>
                        <select wire:model="newType" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                            <option value="">-- Materialart --</option>
                            @foreach($availableTypes as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Bezeichnung (optional)</label>
                        <input type="text" wire:model="newLabel" placeholder="z.B. Klebeband" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Gramm</label>
                        <input type="number" wire:model="newWeightGrams" min="1" placeholder="5" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="w-full px-4 py-2 rounded-xl text-xs font-black uppercase bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)] hover:bg-[var(--theme-color)] hover:text-gray-900 transition-colors cursor-pointer">
                            + Hinzufügen
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- TAB 2: VERPACKUNGSMATERIAL                                            -->
    <!-- ===================================================================== -->
    @if($activeTab === 'catalog')
        <!-- Schlichte 1-Zeilen-Leiste (Light/Dark kompatibel mit Theme-Variable) -->
        <div class="bg-white dark:bg-gray-900/85 backdrop-blur-xl p-4 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 mb-5">
            <div class="flex flex-col md:flex-row gap-2.5">
                <div class="flex-1">
                    <input
                        type="url"
                        wire:model="aiLinkUrl"
                        placeholder="Produkt-Link einfügen (z.B. Westpack oder Karton.eu)..."
                        class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] rounded-xl px-4 py-2.5 text-xs sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 outline-none"
                    >
                </div>

                <div class="flex flex-wrap sm:flex-nowrap gap-2 shrink-0">
                    <button
                        type="button"
                        wire:click="createPackagingFromAiUrl"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider bg-[var(--theme-color)] text-gray-900 hover:opacity-90 transition-all cursor-pointer"
                    >
                        <x-heroicon-s-sparkles class="w-4 h-4" />
                        <span wire:loading.remove wire:target="createPackagingFromAiUrl">KI eintragen lassen</span>
                        <span wire:loading wire:target="createPackagingFromAiUrl">Analysiere...</span>
                    </button>

                    <button
                        type="button"
                        wire:click="openCreateModal"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] hover:bg-[var(--theme-color)] hover:text-gray-900 transition-colors cursor-pointer"
                    >
                        <x-heroicon-s-plus class="w-4 h-4" />
                        Manuell anlegen
                    </button>
                </div>
            </div>

            @if($aiStatusMessage)
                <div class="mt-3 px-3.5 py-2 rounded-xl text-xs font-medium {{ $aiStatusType === 'error' ? 'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20' : 'bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)]' }}">
                    {{ $aiStatusMessage }}
                </div>
            @endif
        </div>

        <!-- Schlanke Verpackungsliste -->
        <div class="space-y-2.5">
            @forelse($catalogItems as $item)
                @php
                    $tariff = $item->postal_tariff_info;
                    $unitEur = number_format($item->unit_cost_cents / 100, 2, ',', '.');
                    $packEur = number_format($item->pack_price_cents / 100, 2, ',', '.');
                    $badgeColors = match($tariff['color']) {
                        'emerald' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/25',
                        'amber' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/25',
                        default => 'bg-gray-500/10 text-gray-600 dark:text-gray-400 border-gray-500/25',
                    };
                @endphp

                <div class="bg-white dark:bg-gray-900/85 backdrop-blur-xl rounded-2xl border border-gray-200 dark:border-gray-800 px-4 py-3.5 flex flex-col lg:flex-row lg:items-center justify-between gap-3 hover:border-[var(--theme-color-40)] transition-all">
                    <!-- Links: Name, Art.-Nr., Maße & LUCID -->
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                                {{ $item->name }}
                            </h3>
                            @if($item->sku)
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                    {{ $item->sku }}
                                </span>
                            @endif
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $badgeColors }}">
                                {{ $tariff['badge'] }}
                            </span>
                            @if($item->is_upsell_option)
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                                    +{{ number_format($item->upsell_price_cents / 100, 2, ',', '.') }} € Option
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            @if($item->supplier_name)
                                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $item->supplier_name }}</span>
                            @endif
                            <span>{{ $item->formatted_dimensions }}</span>
                            <span>
                                {{ $item->weight_grams }} g
                                ({{ $item->primary_weight_grams }}g {{ $availableTypes[$item->primary_material_type] ?? $item->primary_material_type }}{{ $item->secondary_material_type && $item->secondary_weight_grams > 0 ? ' + ' . $item->secondary_weight_grams . 'g ' . ($availableTypes[$item->secondary_material_type] ?? $item->secondary_material_type) : '' }})
                            </span>
                        </div>
                    </div>

                    <!-- Rechts: Preis, Lagerbestand & Aktionen -->
                    <div class="flex flex-wrap items-center justify-between lg:justify-end gap-3 pt-2.5 lg:pt-0 border-t lg:border-t-0 border-gray-100 dark:border-gray-800 shrink-0">
                        <div class="text-left lg:text-right">
                            <div class="text-sm font-black text-[var(--theme-color)]">{{ $unitEur }} € <span class="text-[10px] font-normal text-gray-400">/ Stk.</span></div>
                            <div class="text-[11px] text-gray-400">{{ $item->pack_quantity }} Stk. · {{ $packEur }} €</div>
                        </div>

                        <!-- Bestand +/- -->
                        <div class="flex items-center gap-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-2 py-1">
                            <button type="button" wire:click="adjustStock({{ $item->id }}, -1)" class="w-5 h-5 rounded bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-bold hover:bg-[var(--theme-color)] hover:text-gray-900 transition-colors cursor-pointer">-</button>
                            <span class="text-xs font-black px-1.5 text-gray-900 dark:text-white">
                                {{ $item->stock_quantity }} Stk.
                            </span>
                            <button type="button" wire:click="adjustStock({{ $item->id }}, 1)" class="w-5 h-5 rounded bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-bold hover:bg-[var(--theme-color)] hover:text-gray-900 transition-colors cursor-pointer">+</button>
                        </div>

                        <!-- Buttons -->
                        <div class="flex items-center gap-1.5">
                            @if($item->reorder_url)
                                <a
                                    href="{{ $item->reorder_url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] hover:bg-[var(--theme-color)] hover:text-gray-900 transition-colors"
                                    title="Beim Händler öffnen"
                                >
                                    <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                                    <span class="hidden sm:inline">Link</span>
                                </a>
                            @endif

                            <button
                                type="button"
                                wire:click="editCatalogItem({{ $item->id }})"
                                class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-[var(--theme-color)] hover:text-gray-900 transition-colors cursor-pointer"
                                title="Bearbeiten"
                            >
                                <x-heroicon-o-pencil-square class="w-4 h-4" />
                            </button>

                            <button
                                type="button"
                                wire:click="deleteCatalogItem({{ $item->id }})"
                                wire:confirm="Verpackung „{{ $item->name }}“ löschen?"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-500/10 transition-colors cursor-pointer"
                                title="Löschen"
                            >
                                <x-heroicon-o-trash class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 border border-dashed border-gray-200 dark:border-gray-800 rounded-2xl text-xs text-gray-400">
                    Noch keine Verpackungen hinterlegt.
                </div>
            @endforelse
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MODAL: VERPACKUNG ANLEGEN / BEARBEITEN                                -->
    <!-- ===================================================================== -->
    @if($showItemModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-800 mb-4">
                    <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white">
                        {{ $editingItemId ? 'Verpackung bearbeiten' : 'Verpackung anlegen' }}
                    </h3>
                    <button type="button" wire:click="closeItemModal" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <form wire:submit.prevent="saveCatalogItem" class="space-y-3.5">
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                        <div class="sm:col-span-8">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Bezeichnung *</label>
                            <input type="text" wire:model="itemName" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none" required>
                        </div>
                        <div class="sm:col-span-4">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Art.-Nr. / SKU</label>
                            <input type="text" wire:model="itemSku" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2 text-xs font-mono font-bold text-gray-900 dark:text-white outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Kategorie</label>
                            <select wire:model="itemCategory" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Lieferant</label>
                            <select wire:model="itemSupplierId" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                                <option value="">-- Freitext --</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Händlername</label>
                            <input type="text" wire:model="itemSupplierName" placeholder="Westpack A/S" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Produkt-Link (URL)</label>
                        <input type="url" wire:model="itemReorderUrl" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2 text-xs text-gray-900 dark:text-white outline-none">
                    </div>

                    <!-- Maße & LUCID -->
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2.5">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Länge (mm)</label>
                            <input type="number" step="0.1" wire:model="itemLengthMm" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Breite (mm)</label>
                            <input type="number" step="0.1" wire:model="itemWidthMm" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Höhe (mm)</label>
                            <input type="number" step="0.1" wire:model="itemHeightMm" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Hauptmaterial</label>
                            <select wire:model="itemPrimaryMaterial" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                                @foreach($availableTypes as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Gewicht (g)</label>
                            <input type="number" wire:model.live="itemPrimaryWeight" min="1" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                        </div>
                    </div>

                    <!-- Nebenmaterial (z.B. Schaumstoffeinlage) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Zweites Material (optional)</label>
                            <select wire:model="itemSecondaryMaterial" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                                <option value="">-- Kein zweites Material --</option>
                                @foreach($availableTypes as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Gewicht 2 (g)</label>
                            <input type="number" wire:model.live="itemSecondaryWeight" min="0" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                        </div>
                    </div>

                    <!-- Preise & Bestand -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Menge (Stk.)</label>
                            <input type="number" wire:model.live="itemPackQuantity" min="1" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Gesamtpreis (€)</label>
                            <input type="text" wire:model.live="itemPackPriceEur" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Stückpreis (€)</label>
                            <input type="text" wire:model="itemUnitCostEur" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-black text-[var(--theme-color)] outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Bestand (Stk.)</label>
                            <input type="number" wire:model="itemStockQuantity" min="0" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none">
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-3 border-t border-gray-200 dark:border-gray-800">
                        <label class="inline-flex items-center gap-2 text-xs font-bold text-gray-700 dark:text-gray-300 cursor-pointer">
                            <input type="checkbox" wire:model.live="itemIsUpsellOption" class="rounded text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                            <span>Als Geschenkbox-Option (+Aufpreis)</span>
                        </label>

                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="closeItemModal" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:text-gray-900 dark:hover:text-white">
                                Abbrechen
                            </button>
                            <button type="submit" class="px-5 py-2 rounded-xl text-xs font-black uppercase bg-[var(--theme-color)] text-gray-900 hover:opacity-90 cursor-pointer">
                                Speichern
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@if($currentStep === 3)
    <div class="space-y-6 md:space-y-8 animate-fade-in-up">

        @php
            $assignedPackagings = $product->packagings ?? collect();
            $catCount = $product->relationLoaded('categories') ? $product->categories->count() : $product->categories()->count();
            $nonEmptyAttrs = collect($product->attributes ?? $productAttributes ?? [])->filter(fn($v) => trim((string) $v) !== '')->count();
            $variantsCount = is_array($product->variants ?? null) ? count($product->variants) : 0;

            $sub3_1Done = $catCount > 0;
            $sub3_2Done = $nonEmptyAttrs > 0;
            $sub3_3Done = $variantsCount > 0;
            $sub3_4Done = ($type === 'physical')
                && $assignedPackagings->isNotEmpty()
                && ((int) $packaging_weight > 0 || $assignedPackagings->sum('weight_grams') > 0)
                && ((int) $weight > 0)
                && ((float) $width > 0)
                && ((float) $height > 0);
            $sub3_5Done = true;
            $sub3_6Done = ($type === 'physical') && ($packaging_cost_input !== '' && (float) $packaging_cost_input >= 0);

            $step3SubSteps = [
                1 => ['title' => 'Kategorien', 'done' => $sub3_1Done, 'optional' => false],
                2 => ['title' => 'Eigenschaften', 'done' => $sub3_2Done, 'optional' => true],
                3 => ['title' => 'Varianten (Matrix)', 'done' => $sub3_3Done, 'optional' => true],
            ];

            if ($type === 'physical') {
                $step3SubSteps[4] = ['title' => 'Verpackung (LUCID) & Maße', 'done' => $sub3_4Done, 'optional' => false];
            }
            if ($type !== 'digital') {
                $step3SubSteps[5] = ['title' => $type === 'service' ? 'Verfügbarkeit & Plätze' : 'Lager & Verfügbarkeit', 'done' => $sub3_5Done, 'optional' => false];
            }
            if ($type === 'physical') {
                $step3SubSteps[6] = ['title' => 'Kalkulation & Staffeln', 'done' => $sub3_6Done, 'optional' => false];
            }

            if (!isset($step3SubSteps[$step3SubStep])) {
                $step3SubStep = array_key_first($step3SubSteps) ?? 1;
            }
        @endphp

        {{-- VISUELLE UNTER-STEPS UNTER "3. VERPACKUNG & DETAILS" --}}
        @php
            $step3GridCols = match(count($step3SubSteps)) {
                6 => 'xl:grid-cols-6',
                4 => 'xl:grid-cols-4',
                default => 'xl:grid-cols-3',
            };
        @endphp
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-2xl border border-gray-200 dark:border-gray-800 p-2.5 sm:p-3 shadow-2xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 {{ $step3GridCols }} gap-2">
                @foreach($step3SubSteps as $subIdx => $subMeta)
                    @php
                        $isActiveSub3 = ($step3SubStep === $subIdx);
                    @endphp
                    <button type="button"
                            wire:click="goToStep3SubStep({{ $subIdx }})"
                            class="text-left px-3.5 py-2.5 rounded-xl border transition-all cursor-pointer flex items-center justify-between gap-2 {{ $isActiveSub3
                                ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] text-gray-900 dark:text-white shadow-2xs'
                                : 'border-gray-200/80 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/60 text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700 hover:text-gray-900 dark:hover:text-white' }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-6 h-6 rounded-lg text-[10px] font-black flex items-center justify-center shrink-0 transition-colors {{ $isActiveSub3
                                ? 'bg-[var(--theme-color)] text-white dark:text-gray-900'
                                : ($subMeta['done'] ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-400') }}">
                                {{ $subIdx }}
                            </span>
                            <span class="text-xs font-bold truncate">{{ $subMeta['title'] }}</span>
                        </div>

                        @if($subMeta['done'])
                            <span class="px-1.5 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 text-[9px] font-black shrink-0" title="Vollständig">✓</span>
                        @elseif($subMeta['optional'])
                            <span class="px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400 text-[9px] font-bold shrink-0">Opt.</span>
                        @else
                            <span class="px-1.5 py-0.5 rounded-md bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[9px] font-bold shrink-0">Offen</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- SUB-STEP 1: KATEGORIEN --}}
        @if($step3SubStep === 1)
            <livewire:shop.product.product-categories :product="$product" :key="'product-categories-'.$product->id" />
        @endif

        {{-- SUB-STEP 2: EIGENSCHAFTEN --}}
        @if($step3SubStep === 2)
            <livewire:shop.product.product-attributes :product="$product" :key="'product-attributes-'.$product->id" />
        @endif

        {{-- SUB-STEP 3: PRODUKTVARIANTEN (MATRIX) --}}
        @if($step3SubStep === 3)
            <livewire:shop.product.product-variants :product="$product" :key="'product-variants-'.$product->id" />
        @endif

        {{-- SUB-STEP 4: VERPACKUNG (LUCID), VERSAND & MASSE --}}
        @if($step3SubStep === 4 && $type === 'physical')
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 animate-fade-in-up w-full min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-8 border-b border-gray-200 dark:border-gray-800 pb-5">
                    <div class="flex items-center gap-2 min-w-0">
                        <h3 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">4. Verpackung (LUCID), Versand &amp; Maße</h3>
                        @include('components.alerts.info-tooltip', ['key' => 'lucid_shipping_overview'])
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button"
                                wire:click="setActiveSection('packaging')"
                                class="px-3 py-1.5 rounded-xl text-[10px] font-bold border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer">
                            Verpackungskatalog &rarr;
                        </button>
                        <span class="text-[9px] font-black uppercase tracking-widest text-[var(--theme-color)] bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] px-3 py-1.5 rounded-lg shadow-inner hidden sm:block shrink-0">
                            Unter-Schritt 4 / 6
                        </span>
                    </div>
                </div>

                <div class="space-y-8 w-full">
                    @php
                        $matLabels = \App\Models\Product\ProductPackaging::getMaterialTypes();
                        $assignedItemIds = $assignedPackagings->pluck('packaging_item_id')->filter()->unique()->toArray();
                    @endphp
                    <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-gray-900 dark:text-white">Standard-Versandverpackung aus Katalog wählen (1-Klick LUCID &amp; EK)</span>
                                @include('components.alerts.info-tooltip', ['key' => 'packaging_catalog_pick'])
                            </div>
                            @if($assignedPackagings->isNotEmpty())
                                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[10px] font-black uppercase tracking-wider">
                                    ✓ {{ $assignedPackagings->sum('weight_grams') }}&nbsp;g LUCID erfasst
                                </span>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                            @foreach($this->packagingCatalogItems->whereIn('category', ['shipping_envelope', 'shipping_box']) as $catItem)
                                @php
                                    $isAssignedCat = in_array($catItem->id, $assignedItemIds, true);
                                @endphp
                                <button type="button"
                                        wire:click="assignCatalogPackaging({{ $catItem->id }}, 'replace')"
                                        class="text-left p-3 rounded-xl border transition-all cursor-pointer flex items-center justify-between gap-2 {{ $isAssignedCat ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-[var(--theme-color-40)]' }}">
                                    <div class="min-w-0">
                                        <span class="text-xs font-bold text-gray-900 dark:text-white block truncate">{{ $catItem->name }}</span>
                                        <span class="text-[10px] text-gray-500 block mt-0.5">
                                            {{ $catItem->weight_grams }}&nbsp;g · {{ number_format($catItem->unit_cost_cents / 100, 2, ',', '.') }}&nbsp;€ netto
                                        </span>
                                    </div>
                                    @if($isAssignedCat)
                                        <span class="px-2 py-0.5 rounded-md bg-[var(--theme-color)] text-white dark:text-gray-900 text-[9px] font-black shrink-0">Aktiv</span>
                                    @else
                                        <span class="text-[10px] font-bold text-[var(--theme-color)] shrink-0">Wählen</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>

                        @if($assignedPackagings->isNotEmpty())
                            <div class="pt-3 border-t border-gray-200 dark:border-gray-800 flex flex-wrap items-center gap-2">
                                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 mr-1">Hinterlegte LUCID-Anteile:</span>
                                @foreach($assignedPackagings as $pkgRow)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-[11px] font-medium text-gray-700 dark:text-gray-300">
                                        <strong>{{ $pkgRow->weight_grams }}&nbsp;g</strong> {{ $matLabels[$pkgRow->material_type] ?? $pkgRow->material_type }}
                                        @if($pkgRow->label)
                                            <span class="text-gray-400 text-[10px]">({{ \Illuminate\Support\Str::limit($pkgRow->label, 28) }})</span>
                                        @endif
                                        <button type="button"
                                                wire:click="removeProductPackagingEntry({{ $pkgRow->id }})"
                                                class="text-gray-400 hover:text-red-500 ml-1 cursor-pointer"
                                                title="Anteil entfernen">&times;</button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8 w-full">
                        <div class="w-full min-w-0">
                            <div class="flex items-center gap-2 mb-2 ml-1">
                                <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Produkt-Gewicht (Netto) *</label>
                                @include('components.alerts.info-tooltip', ['key' => 'product_weight_grams'])
                            </div>
                            <div class="relative w-full">
                                <input type="number" wire:model.blur="weight" class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 text-gray-900 dark:text-white font-mono font-bold focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all pr-12 shadow-inner outline-none placeholder-gray-400 dark:placeholder-gray-600 min-w-0" placeholder="0">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] font-black uppercase tracking-widest text-gray-500">g</span>
                            </div>
                        </div>
                        <div class="w-full min-w-0">
                            <div class="flex items-center gap-2 mb-2 ml-1">
                                <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Tara / Verpackung (LUCID) *</label>
                                @include('components.alerts.info-tooltip', ['key' => 'packaging_weight_grams'])
                            </div>
                            <div class="relative w-full">
                                <input type="number" wire:model.blur="packaging_weight" class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 text-gray-900 dark:text-white font-mono font-bold focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all pr-12 shadow-inner outline-none placeholder-gray-400 dark:placeholder-gray-600 min-w-0" placeholder="0">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] font-black uppercase tracking-widest text-gray-500">g</span>
                            </div>
                        </div>
                        <div class="w-full min-w-0">
                            <div class="flex items-center gap-2 mb-2 ml-1">
                                <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Versandart</label>
                                @include('components.alerts.info-tooltip', ['key' => 'shipping_class'])
                            </div>
                            <div class="relative w-full">
                                <select wire:model.blur="shipping_class" class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 text-gray-900 dark:text-white font-bold focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all shadow-inner appearance-none cursor-pointer outline-none text-sm min-w-0">
                                    <option value="" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">DHL Standard (Gewichtsbasiert)</option>
                                    <optgroup label="Spezialversand" class="text-gray-500 italic bg-gray-50 dark:bg-gray-950">
                                        <option value="brief" class="bg-white dark:bg-gray-900 not-italic text-gray-900 dark:text-white">Brief / Großbrief (Günstiger)</option>
                                        <option value="sperrgut" class="bg-white dark:bg-gray-900 not-italic text-gray-900 dark:text-white">DHL Sperrgut (Zuschlag)</option>
                                        <option value="spedition" class="bg-white dark:bg-gray-900 not-italic text-gray-900 dark:text-white">Spedition (Palettenware)</option>
                                    </optgroup>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg></div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-8 border-t border-gray-200 dark:border-gray-800 w-full">
                        <div class="flex items-center gap-2 mb-5 ml-1">
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Abmessungen (L x B x H in mm) *</label>
                            @include('components.alerts.info-tooltip', ['key' => 'dimensions_mm'])
                        </div>
                        <div class="grid grid-cols-3 gap-3 sm:gap-5 w-full">
                            <div class="relative group min-w-0">
                                <input type="number" wire:model.blur="length" class="w-full px-2 sm:px-3 py-4 text-center rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 text-gray-900 dark:text-white font-mono font-bold focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all shadow-inner outline-none placeholder-gray-400 dark:placeholder-gray-600 min-w-0" placeholder="0">
                                <label class="absolute -bottom-6 left-0 w-full text-[9px] text-center text-gray-500 dark:text-gray-600 font-black uppercase tracking-widest transition-colors group-focus-within:text-[var(--theme-color)]">Länge (Tiefe)</label>
                            </div>
                            <div class="relative group min-w-0">
                                <input type="number" wire:model.blur="width" class="w-full px-2 sm:px-3 py-4 text-center rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 text-gray-900 dark:text-white font-mono font-bold focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all shadow-inner outline-none placeholder-gray-400 dark:placeholder-gray-600 min-w-0" placeholder="0">
                                <label class="absolute -bottom-6 left-0 w-full text-[9px] text-center text-gray-500 dark:text-gray-600 font-black uppercase tracking-widest transition-colors group-focus-within:text-[var(--theme-color)]">Breite *</label>
                            </div>
                            <div class="relative group min-w-0">
                                <input type="number" wire:model.blur="height" class="w-full px-2 sm:px-3 py-4 text-center rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 text-gray-900 dark:text-white font-mono font-bold focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all shadow-inner outline-none placeholder-gray-400 dark:placeholder-gray-600 min-w-0" placeholder="0">
                                <label class="absolute -bottom-6 left-0 w-full text-[9px] text-center text-gray-500 dark:text-gray-600 font-black uppercase tracking-widest transition-colors group-focus-within:text-[var(--theme-color)]">Höhe *</label>
                            </div>
                        </div>
                    </div>
                    <div class="h-4"></div>
                </div>
            </div>
        @endif

        {{-- SUB-STEP 5: LAGER & VERFÜGBARKEIT --}}
        @if($step3SubStep === 5 && $type !== 'digital')
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 animate-fade-in-up w-full min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6 border-b border-gray-200 dark:border-gray-800 pb-5">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">5. {{ $type === 'service' ? 'Verfügbarkeit & Plätze' : 'Lager & Verfügbarkeit' }}</h3>
                        @include('components.alerts.info-tooltip', ['key' => 'inventory_overview'])
                    </div>
                    @if($track_quantity)
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest shadow-inner border {{ $quantity > 0 ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20' }}">
                            {{ $quantity > 0 ? ($type === 'service' ? 'Verfügbar (' . $quantity . ')' : 'Auf Lager (' . $quantity . ' Stk.)') : 'Ausverkauft (0 Stk.)' }}
                        </span>
                    @endif
                </div>

                <div class="space-y-6">
                    <div class="flex items-center gap-5 p-5 bg-gray-50 dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-inner transition-all hover:border-gray-300 dark:hover:border-gray-700 group">
                        <button type="button" wire:click="$toggle('track_quantity')" class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 ease-in-out focus:outline-none shadow-inner {{ $track_quantity ? 'bg-emerald-500/20 border-emerald-500/50' : 'bg-gray-200 dark:bg-gray-800 border-gray-300 dark:border-gray-700' }}" role="switch" aria-checked="{{ $track_quantity ? 'true' : 'false' }}">
                            <span class="sr-only">Limitierung aktivieren</span>
                            <span aria-hidden="true" class="pointer-events-none inline-block h-6 w-6 transform rounded-full shadow-sm dark:shadow-[0_0_10px_rgba(0,0,0,0.5)] ring-0 transition duration-300 ease-in-out {{ $track_quantity ? 'translate-x-5 bg-emerald-500 dark:bg-emerald-400 dark:shadow-[0_0_10px_rgba(16,185,129,0.8)]' : 'translate-x-0 bg-gray-400 dark:bg-gray-500' }}"></span>
                        </button>
                        <div class="flex items-center gap-2 flex-1">
                            <span wire:click="$toggle('track_quantity')" class="text-sm font-bold text-gray-900 dark:text-white cursor-pointer group-hover:text-[var(--theme-color)] transition-colors">{{ $type === 'service' ? 'Plätze limitieren' : 'Bestand automatisch verfolgen' }}</span>
                            @include('components.alerts.info-tooltip', ['key' => 'track_quantity'])
                        </div>
                    </div>

                    @if($track_quantity)
                        <div class="p-6 sm:p-8 bg-gray-100/50 dark:bg-gray-950/50 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-inner space-y-6 animate-fade-in-down">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                                <div>
                                    <div class="flex items-center gap-2 mb-2 ml-1">
                                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">{{ $type === 'service' ? 'Freie Plätze' : 'Aktuell Verfügbar (Eltern-Produkt)' }}</label>
                                        @include('components.alerts.info-tooltip', ['key' => 'stock_quantity'])
                                    </div>
                                    <div class="relative">
                                        <input type="number" wire:model.live="quantity" class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-mono font-bold focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all shadow-inner outline-none" placeholder="0">
                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] text-gray-500 font-black uppercase tracking-widest">Stk.</span>
                                    </div>
                                </div>
                                <div class="md:pt-6">
                                    <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-900 transition-colors border border-transparent hover:border-gray-200 dark:hover:border-gray-800">
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <div class="relative flex items-center shrink-0">
                                                <input type="checkbox" id="continue_selling" wire:model.live="continue_selling" class="peer sr-only">
                                                <div class="w-5 h-5 bg-white dark:bg-gray-900 border-2 border-gray-300 dark:border-gray-700 rounded transition-all peer-checked:bg-[var(--theme-color)] peer-checked:border-[var(--theme-color)] shadow-inner"></div>
                                                <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </div>
                                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300">Überverkauf erlauben</span>
                                        </label>
                                        @include('components.alerts.info-tooltip', ['key' => 'continue_selling'])
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- SUB-STEP 6: KALKULATION & STAFFELPREISE --}}
        @if($step3SubStep === 6 && $type === 'physical')
            <div class="space-y-6 md:space-y-8 animate-fade-in-up">
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 w-full min-w-0">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-6 border-b border-gray-200 dark:border-gray-800 pb-5">
                        <div class="flex items-center gap-2">
                            <h3 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">6. Kalkulation &amp; Produktion</h3>
                            @include('components.alerts.info-tooltip', ['key' => 'unit_economics_overview'])
                        </div>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest shadow-inner border bg-gray-100 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700">
                            Unit Economics · Unter-Schritt 6 / 6
                        </span>
                    </div>

                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8 w-full">
                            <div class="w-full min-w-0">
                                <div class="flex items-center gap-2 mb-2 ml-1">
                                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Ø Laser-Laufzeit</label>
                                    @include('components.alerts.info-tooltip', ['key' => 'laser_runtime_minutes'])
                                </div>
                                <div class="relative w-full">
                                    <input type="number" wire:model.blur="laser_runtime_minutes" class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 text-gray-900 dark:text-white font-mono font-bold focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all shadow-inner outline-none placeholder-gray-400 dark:placeholder-gray-600 min-w-0" placeholder="0">
                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] font-black uppercase tracking-widest text-gray-500">Minuten</span>
                                </div>
                            </div>
                            <div class="w-full min-w-0">
                                <div class="flex items-center gap-2 mb-2 ml-1">
                                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Strom &amp; Maschinenverschleiß pro Min.</label>
                                    @include('components.alerts.info-tooltip', ['key' => 'electricity_wear_factor'])
                                </div>
                                <div class="relative w-full">
                                    <input type="number" wire:model.blur="electricity_wear_factor" class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 text-gray-900 dark:text-white font-mono font-bold focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all shadow-inner outline-none placeholder-gray-400 dark:placeholder-gray-600 min-w-0" placeholder="1">
                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] font-black uppercase tracking-widest text-gray-500">Cent</span>
                                </div>
                            </div>

                            <div class="w-full min-w-0">
                                <div class="flex items-center gap-2 mb-2 ml-1">
                                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Verpackungskosten (Netto)</label>
                                    @include('components.alerts.info-tooltip', ['key' => 'packaging_cost'])
                                </div>
                                <div class="relative w-full">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-bold">€</span>
                                    <input type="number" step="0.01" wire:model.blur="packaging_cost_input" class="w-full pl-10 pr-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 text-gray-900 dark:text-white font-mono font-bold focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all shadow-inner outline-none placeholder-gray-400 dark:placeholder-gray-600 min-w-0" placeholder="0.00">
                                </div>
                            </div>

                            <div class="w-full min-w-0">
                                <div class="flex items-center gap-2 mb-2 ml-1">
                                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Marketing-Kosten</label>
                                    @include('components.alerts.info-tooltip', ['key' => 'marketing_cost_percent'])
                                </div>
                                <div class="relative w-full">
                                    <input type="number" step="0.01" wire:model.blur="marketing_cost_percent" class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 text-gray-900 dark:text-white font-mono font-bold focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all pr-12 shadow-inner outline-none placeholder-gray-400 dark:placeholder-gray-600 min-w-0" placeholder="15.00">
                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-bold">%</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/20 border border-indigo-200/70 dark:border-indigo-900/40 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="p-2 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 shrink-0">
                                    <x-heroicon-o-credit-card class="w-4 h-4" />
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-gray-900 dark:text-white">
                                    <span>Stripe &amp; Zahlungsgebühren (Automatisch in Unit Economics)</span>
                                    @include('components.alerts.info-tooltip', ['key' => 'stripe_fees'])
                                </div>
                            </div>
                            @php
                                $rawStripePct = str_replace(',', '.', trim((string) shop_setting('stripe_fee_percent', 2.50)));
                                $stripePctDisplay = max(0.0, (float) $rawStripePct);
                                $rawStripeFixed = str_replace(',', '.', trim((string) shop_setting('stripe_fee_fixed_cents', 30)));
                                $stripeFixedFloat = (float) $rawStripeFixed;
                                $stripeFixedCentsDisplay = str_contains($rawStripeFixed, '.')
                                    ? max(0, (int) round($stripeFixedFloat * 100))
                                    : max(0, (int) round($stripeFixedFloat));
                            @endphp
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-mono font-black bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 shrink-0">
                                {{ number_format($stripePctDisplay, 1, ',', '.') }} % + {{ number_format($stripeFixedCentsDisplay / 100, 2, ',', '.') }} €
                            </span>
                        </div>
                    </div>
                </div>

                <livewire:shop.product.product-tier-pricing :product="$product" :currentPrice="$price_input" :key="'product-tier-pricing-'.$product->id" />
            </div>
        @endif

    </div>
@endif

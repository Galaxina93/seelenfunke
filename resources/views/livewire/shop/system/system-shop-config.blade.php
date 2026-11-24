@php
    $configSearchCatalog = \App\Services\System\GlobalProjectSearchService::getShopConfigCatalog();
@endphp

<div class="p-4 lg:p-8 bg-transparent min-h-screen font-sans antialiased text-gray-300"
     style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;"
     x-data="sfShopConfigShell()"
     x-init="initConfigShell()"
     @config-validation-failed.window="if ($event.detail && $event.detail.tab) { clearConfigSearch(); activeTab = $event.detail.tab; }"
     @sf-focus-shop-setting.window="if ($event.detail) focusSpecificSetting($event.detail.tab, $event.detail.focus)">

    @once
        <style>
            .sf-cfg-hidden-by-search {
                display: none !important;
            }
            .sf-cfg-field-highlight {
                box-shadow: 0 0 0 1.5px rgba(197, 160, 89, 0.85), 0 8px 22px rgba(197, 160, 89, 0.14) !important;
                border-color: #C5A059 !important;
                border-radius: 1rem;
                transition: box-shadow 220ms ease, border-color 220ms ease;
            }
        </style>
    @endonce

    <div x-ref="configMetaSeed"
         data-info="{{ json_encode($infoTexts ?? [], JSON_UNESCAPED_UNICODE) }}"
         data-catalog="{{ json_encode($configSearchCatalog, JSON_UNESCAPED_UNICODE) }}"
         class="hidden"></div>

    <div class="max-w-6xl mx-auto animate-fade-in-up">

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-6">
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Shop-Konfiguration</h1>
            </div>
            <button
                wire:click="save"
                wire:loading.attr="disabled"
                x-data="{ saved: @entangle('saved') }"
                x-effect="if (saved) { setTimeout(() => { saved = false; $wire.resetSaved(); }, 3000) }"
                :class="saved ? 'bg-emerald-500 text-gray-900 shadow-[0_0_20px_rgba(16,185,129,0.4)]' : 'bg-[var(--theme-color)] text-gray-900 hover:brightness-110 hover:scale-[1.02] shadow-sm dark:shadow-[0_0_15px_var(--theme-color-20)]'"
                class="px-8 py-3.5 rounded-xl text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all flex items-center gap-3 min-w-[240px] justify-center border border-transparent cursor-pointer">

                <svg wire:loading class="animate-spin h-5 w-5 text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>

                <template x-if="saved">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-900 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Erfolgreich gespeichert!</span>
                    </div>
                </template>

                <template x-if="!saved">
                    <div class="flex items-center gap-2 hover:text-white" wire:loading.remove>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        <span>Einstellungen sichern</span>
                    </div>
                </template>
            </button>
        </div>

        {{-- DYNAMISCHE 0-MS SEELENFUNKE EINSTELLUNGS-SUCHLEISTE --}}
        <div class="mb-7">
            <div class="relative rounded-2xl bg-white/95 dark:bg-gray-900/90 border transition-all duration-200 shadow-sm"
                 :class="(searchFocused || isSearchActive) ? 'border-[#C5A059] shadow-[0_10px_30px_rgba(197,160,89,0.14)]' : 'border-gray-200 dark:border-gray-800 hover:border-[#C5A059]/50'">

                <div x-show="searchFocused || isSearchActive" style="display: none;" class="sf-magic-search-ring"></div>

                <div class="relative z-10 flex items-center gap-3 px-4 py-3">
                    <div class="relative flex items-center justify-center w-8 h-8 rounded-xl bg-[#C5A059]/15 border border-[#C5A059]/30 shrink-0">
                        <span class="sf-magic-search-star text-xs font-bold">✦</span>
                        <template x-if="isSearchActive">
                            <span>
                                <span class="sf-magic-micro-spark" style="left: 3px; bottom: 3px; --sx: -5px;">✦</span>
                                <span class="sf-magic-micro-spark" style="right: 3px; bottom: 4px; --sx: 5px; animation-delay: 0.4s;">✦</span>
                            </span>
                        </template>
                    </div>

                    <input x-ref="configSearchInput"
                           type="text"
                           x-model="configSearch"
                           @focus="searchFocused = true"
                           @blur="searchFocused = false"
                           @input="applyConfigFilter()"
                           @keydown.escape="clearConfigSearch(); $el.blur()"
                           placeholder="Einstellung live durchsuchen (z. B. IBAN, Versandfrei, Wartungsmodus, Kleinunternehmer, Stripe, APK, Notar)..."
                           class="flex-1 min-w-0 bg-transparent border-0 focus:ring-0 focus:outline-none text-xs sm:text-sm font-semibold text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500" />

                    <template x-if="isSearchActive">
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="px-2.5 py-1 rounded-lg bg-[#C5A059]/15 text-[#C5A059] text-[10px] font-black uppercase tracking-wider"
                                  x-text="matchedCount + (matchedCount === 1 ? ' Bereich' : ' Bereiche')"></span>
                            <button type="button"
                                    @click="clearConfigSearch()"
                                    title="Filter zurücksetzen"
                                    class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-gray-900 dark:hover:text-white transition cursor-pointer">
                                <x-heroicon-m-x-mark class="w-4 h-4" />
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Direkt-Schnelltreffer-Chips zum gezielten Fokus-Sprung --}}
                <template x-if="isSearchActive && quickCatalogMatches.length > 0">
                    <div class="relative z-10 px-4 pb-3 pt-1 border-t border-gray-100 dark:border-gray-800/80 flex flex-wrap items-center gap-1.5">
                        <template x-for="hit in quickCatalogMatches" :key="hit.key">
                            <button type="button"
                                    @click="jumpWithMagicToSetting(hit)"
                                    :class="activatingChipKey === hit.key
                                        ? 'bg-[#C5A059] text-gray-950 border-[#C5A059] sf-magic-shimmer-sweep'
                                        : 'bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 hover:border-[#C5A059] text-gray-700 dark:text-gray-200'"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl border text-[11px] font-bold transition-all cursor-pointer">
                                <span class="text-[#C5A059]" x-show="activatingChipKey !== hit.key">✦</span>
                                <span x-text="activatingChipKey === hit.key ? '✦ Magie wird aktiviert...' : hit.title"></span>
                                <span x-show="activatingChipKey !== hit.key"
                                      class="text-[9px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-[#C5A059]/15 text-[#C5A059]"
                                      x-text="hit.tab_label"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        @if($errors->hasAny(['settings.*']))
            <div class="mb-8 rounded-2xl border border-red-500/40 bg-red-500/10 p-5 text-red-600 dark:text-red-400 shadow-sm">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                    </svg>
                    <div class="text-xs space-y-1">
                        <p class="font-black uppercase tracking-widest">Bitte überprüfe deine Eingaben:</p>
                        <ul class="list-disc list-inside space-y-0.5 font-medium">
                            @foreach($errors->get('settings.*') as $fieldErrors)
                                @foreach($fieldErrors as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- Tab Navigation (Ausgeblendet waehrend aktiver tab-uebergreifender Live-Suche) --}}
        <div x-show="!isSearchActive"
             class="flex flex-wrap items-center justify-between border-b border-gray-200 dark:border-gray-800 mb-8 gap-x-2 gap-y-2">
            <button @click="selectTab('general')" :class="activeTab === 'general' ? 'border-[var(--theme-color)] text-[var(--theme-color)] drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'" class="whitespace-nowrap pb-3.5 px-1.5 border-b-2 text-[10px] xl:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer">Allgemein & Steuern</button>
            <button @click="selectTab('products')" :class="activeTab === 'products' ? 'border-[var(--theme-color)] text-[var(--theme-color)] drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'" class="whitespace-nowrap pb-3.5 px-1.5 border-b-2 text-[10px] xl:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer">Produkt & Marketing</button>
            <button @click="selectTab('shipping')" :class="activeTab === 'shipping' ? 'border-[var(--theme-color)] text-[var(--theme-color)] drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'" class="whitespace-nowrap pb-3.5 px-1.5 border-b-2 text-[10px] xl:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer">Versand & Lieferzeit</button>
            <button @click="selectTab('owner')" :class="activeTab === 'owner' ? 'border-[var(--theme-color)] text-[var(--theme-color)] drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'" class="whitespace-nowrap pb-3.5 px-1.5 border-b-2 text-[10px] xl:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer">Stammdaten</button>
            <button @click="selectTab('seasons')" :class="activeTab === 'seasons' ? 'border-[var(--theme-color)] text-[var(--theme-color)] drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'" class="whitespace-nowrap pb-3.5 px-1.5 border-b-2 text-[10px] xl:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer">Jahreszeiten</button>
            <button @click="selectTab('system')" :class="activeTab === 'system' ? 'border-[var(--theme-color)] text-[var(--theme-color)] drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'" class="whitespace-nowrap pb-3.5 px-1.5 border-b-2 text-[10px] xl:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer">System</button>
            <button @click="selectTab('cronjobs')" :class="activeTab === 'cronjobs' ? 'border-[var(--theme-color)] text-[var(--theme-color)] drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'" class="whitespace-nowrap pb-3.5 px-1.5 border-b-2 text-[10px] xl:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer">Cronjobs</button>
            <button @click="selectTab('emergency')" :class="activeTab === 'emergency' ? 'border-red-500 text-red-500 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'" class="whitespace-nowrap pb-3.5 px-1.5 border-b-2 text-[10px] xl:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer">Notfallplan</button>
        </div>

        {{-- Leerzustand bei 0 Treffern in Shop-Konfiguration --}}
        <div x-cloak x-show="isSearchActive && matchedCount === 0" style="display: none;"
             class="rounded-3xl bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-10 text-center space-y-3 mb-8">
            <div class="text-base font-bold text-gray-900 dark:text-white">Keine passende Shop-Einstellung gefunden</div>
            <div class="flex flex-wrap items-center justify-center gap-2 pt-1">
                <button type="button"
                        @click="clearConfigSearch()"
                        class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-xs font-bold cursor-pointer">
                    Filter zurücksetzen
                </button>
                <button type="button"
                        @click="$dispatch('open-sf-global-search', { query: configSearch })"
                        class="px-4 py-2 rounded-xl bg-[#C5A059] text-gray-950 text-xs font-black uppercase tracking-wider cursor-pointer">
                    ✦ Im gesamten Projekt suchen
                </button>
            </div>
        </div>

        @php
            $inputClass = "w-full bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white rounded-xl text-sm p-3.5 focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] transition-all shadow-sm dark:shadow-inner outline-none placeholder-gray-400 dark:placeholder-gray-600";
            $labelClass = "block text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-500 mb-2 ml-1";
            $checkboxContainerClass = "flex items-center gap-4 cursor-pointer p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 hover:border-gray-300 dark:hover:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-900/80 transition-all shadow-sm dark:shadow-inner group";
        @endphp

        <div x-ref="tabsContainer" class="grid grid-cols-1 gap-8">

            {{-- TAB: ALLGEMEIN --}}
            @include('livewire.shop.system.system-config-partials.general_tab')

            {{-- TAB: PRODUKT & MARKETING --}}
            @include('livewire.shop.system.system-config-partials.products_tab')

            {{-- TAB: VERSAND --}}
            @include('livewire.shop.system.system-config-partials.shipping_tab')

            {{-- TAB: STAMMDATEN --}}
            @include('livewire.shop.system.system-config-partials.ower_tab')

            {{-- TAB: JAHRESZEITEN --}}
            @include('livewire.shop.system.system-config-partials.seasons_tab')

            {{-- TAB: SYSTEM INFO --}}
            @include('livewire.shop.system.system-config-partials.system_tab')

            {{-- TAB: CRONJOBS --}}
            <div data-config-tab="cronjobs" data-config-tab-label="Cronjobs" x-show="isTabVisible('cronjobs')" x-cloak class="min-h-[600px]">
                @livewire('shop.ai.partials-ai-workspace.tab-cronjobs')
            </div>

            {{-- TAB: NOTFALL & VORSORGE --}}
            @include('livewire.shop.system.system-config-partials.emergency_tab')

        </div>
    </div>

    <script>
        if (typeof window.sfShopConfigShell === 'undefined') {
            window.sfShopConfigShell = function () {
                const validTabs = ['general', 'products', 'shipping', 'owner', 'seasons', 'system', 'cronjobs', 'emergency'];

                return {
                    activeTab: localStorage.getItem('shop_config_tab') || 'general',
                    configSearch: '',
                    searchFocused: false,
                    matchedCount: 0,
                    activatingChipKey: null,
                    tabMatches: {
                        general: false,
                        products: false,
                        shipping: false,
                        owner: false,
                        seasons: false,
                        system: false,
                        cronjobs: false,
                        emergency: false,
                    },
                    infoMap: {},
                    catalog: [],
                    quickCatalogMatches: [],

                    get isSearchActive() {
                        return this.configSearch.trim().length > 0;
                    },

                    normalize(s) {
                        if (window.sfNormalizeSearchStr) return window.sfNormalizeSearchStr(s);
                        return String(s || '')
                            .toLowerCase()
                            .replace(/ä/g, 'ae')
                            .replace(/ö/g, 'oe')
                            .replace(/ü/g, 'ue')
                            .replace(/ß/g, 'ss')
                            .replace(/[^a-z0-9\s_-]/g, ' ')
                            .replace(/\s+/g, ' ')
                            .trim();
                    },

                    initConfigShell() {
                        if (!validTabs.includes(this.activeTab)) {
                            this.activeTab = 'general';
                        }

                        const seed = this.$refs.configMetaSeed;
                        if (seed) {
                            if (seed.dataset.info) this.infoMap = JSON.parse(seed.dataset.info);
                            if (seed.dataset.catalog) this.catalog = JSON.parse(seed.dataset.catalog);
                        }

                        this.$watch('activeTab', (val) => {
                            localStorage.setItem('shop_config_tab', val);
                        });

                        // Deep-Link Parameter (?tab=shipping&focus=shipping_free_threshold) auswerten
                        const params = new URLSearchParams(window.location.search);
                        const urlTab = params.get('tab');
                        const urlFocus = params.get('focus');
                        if (urlTab && validTabs.includes(urlTab)) {
                            this.activeTab = urlTab;
                            localStorage.setItem('shop_config_tab', urlTab);
                        }
                        if (urlFocus) {
                            setTimeout(() => {
                                this.focusSpecificSetting(urlTab || this.activeTab, urlFocus);
                            }, 120);
                        }

                        // Nach Livewire-Re-Render Filterzustand sofort wiederherstellen
                        if (window.Livewire && typeof window.Livewire.hook === 'function' && !window.__sfCfgMorphHooked) {
                            window.__sfCfgMorphHooked = true;
                            window.Livewire.hook('morph.updated', () => {
                                const rootEl = document.querySelector('[x-data="sfShopConfigShell()"]');
                                if (rootEl && window.Alpine) {
                                    const data = Alpine.$data(rootEl);
                                    if (data && data.isSearchActive) {
                                        setTimeout(() => data.applyConfigFilter(), 10);
                                    }
                                }
                            });
                        }
                    },

                    selectTab(tab) {
                        this.activeTab = tab;
                    },

                    isTabVisible(tab) {
                        if (this.isSearchActive) {
                            return Boolean(this.tabMatches[tab]);
                        }
                        return this.activeTab === tab;
                    },

                    clearConfigSearch() {
                        this.configSearch = '';
                        this.quickCatalogMatches = [];
                        this.applyConfigFilter();
                    },

                    buildCardIndexText(card, tabKey, tabLabel) {
                        if (card._sfCachedSearchText) {
                            return card._sfCachedSearchText;
                        }
                        let parts = [tabLabel, card.textContent || ''];

                        // Sammle wire:model / wire:click Keys fuer InfoText- & Synonym-Treffer
                        const boundEls = card.querySelectorAll('[wire\\:model], [wire\\:model\\.defer], [wire\\:model\\.blur], [wire\\:model\\.live], [wire\\:click]');
                        boundEls.forEach(el => {
                            for (let a = 0; a < el.attributes.length; a++) {
                                const attr = el.attributes[a];
                                if (attr.name.startsWith('wire:model') || attr.name === 'wire:click') {
                                    const rawVal = attr.value || '';
                                    const cleanKey = rawVal.replace(/^settings\./, '');
                                    parts.push(cleanKey);
                                    if (this.infoMap[cleanKey]) {
                                        parts.push(this.infoMap[cleanKey]);
                                    }
                                    for (let c = 0; c < this.catalog.length; c++) {
                                        const cat = this.catalog[c];
                                        if (cat.key === cleanKey || (cat.tab === tabKey && rawVal.toLowerCase().includes(cat.key.replace(/_/g, '').toLowerCase()))) {
                                            parts.push(cat.title + ' ' + cat.keywords + ' ' + cat.section);
                                        }
                                    }
                                }
                            }
                        });

                        for (let c = 0; c < this.catalog.length; c++) {
                            if (this.catalog[c].tab === tabKey && (tabKey === 'cronjobs' || tabKey === 'seasons')) {
                                parts.push(this.catalog[c].title + ' ' + this.catalog[c].keywords);
                            }
                        }

                        const norm = this.normalize(parts.join(' '));
                        card._sfCachedSearchText = norm;
                        return norm;
                    },

                    applyConfigFilter() {
                        const container = this.$refs.tabsContainer;
                        if (!container) return;

                        const normQuery = this.normalize(this.configSearch);
                        const tokens = normQuery ? normQuery.split(' ').filter(Boolean) : [];

                        // Alle alten Highlights & versteckten Klassen bereinigen
                        container.querySelectorAll('.sf-cfg-hidden-by-search').forEach(el => el.classList.remove('sf-cfg-hidden-by-search'));
                        container.querySelectorAll('.sf-cfg-field-highlight').forEach(el => el.classList.remove('sf-cfg-field-highlight'));

                        if (!tokens.length) {
                            validTabs.forEach(t => { this.tabMatches[t] = false; });
                            this.matchedCount = 0;
                            this.quickCatalogMatches = [];
                            return;
                        }

                        // 1. Schnell-Chips aus dem strukturierten Katalog berechnen
                        const chipHits = [];
                        for (let c = 0; c < this.catalog.length; c++) {
                            const item = this.catalog[c];
                            const hay = this.normalize(item.title + ' ' + item.section + ' ' + item.tab_label + ' ' + item.keywords + ' ' + item.key);
                            if (tokens.every(tok => hay.includes(tok))) {
                                chipHits.push(item);
                            }
                        }
                        this.quickCatalogMatches = chipHits.slice(0, 6);

                        // 2. Alle 8 Tab-Container live filtern
                        let totalCardsMatched = 0;
                        const nextTabMatches = {};
                        validTabs.forEach(t => { nextTabMatches[t] = false; });

                        const tabWrappers = container.querySelectorAll('[data-config-tab]');
                        tabWrappers.forEach(tabEl => {
                            const tabKey = tabEl.getAttribute('data-config-tab') || '';
                            const tabLabel = tabEl.getAttribute('data-config-tab-label') || '';

                            if (tabKey === 'cronjobs') {
                                const cronHay = this.buildCardIndexText(tabEl, 'cronjobs', tabLabel);
                                const cronMatch = tokens.every(tok => cronHay.includes(tok));
                                if (cronMatch) {
                                    nextTabMatches.cronjobs = true;
                                    totalCardsMatched++;
                                }
                                return;
                            }

                            const directChildren = Array.from(tabEl.children).filter(ch => {
                                if (ch.tagName === 'SCRIPT' || ch.tagName === 'STYLE' || ch.tagName === 'TEMPLATE') return false;
                                if (ch.classList.contains('fixed') || ch.getAttribute('role') === 'dialog') return false;
                                const xShow = ch.getAttribute('x-show') || '';
                                if (xShow.includes('Modal')) return false;
                                return true;
                            });

                            directChildren.forEach(card => {
                                const hay = this.buildCardIndexText(card, tabKey, tabLabel);
                                const isMatch = tokens.every(tok => hay.includes(tok));
                                if (isMatch) {
                                    nextTabMatches[tabKey] = true;
                                    totalCardsMatched++;

                                    // Spezifische Feld-Boxen innerhalb der Karte dezent golden hervorheben
                                    const subBlocks = card.querySelectorAll('div.space-y-2, div.space-y-3, div.p-5.rounded-2xl');
                                    subBlocks.forEach(sb => {
                                        const sbText = this.normalize(sb.textContent || '');
                                        if (sbText && tokens.every(tok => sbText.includes(tok))) {
                                            sb.classList.add('sf-cfg-field-highlight');
                                        }
                                    });
                                } else {
                                    card.classList.add('sf-cfg-hidden-by-search');
                                }
                            });
                        });

                        this.tabMatches = nextTabMatches;
                        this.matchedCount = totalCardsMatched;
                    },

                    jumpWithMagicToSetting(hit) {
                        if (!hit) return;
                        this.activatingChipKey = hit.key;
                        if (window.sfPlayMagicSearchChime) window.sfPlayMagicSearchChime();
                        setTimeout(() => {
                            this.activatingChipKey = null;
                            this.focusSpecificSetting(hit.tab, hit.key);
                        }, 210);
                    },

                    focusSpecificSetting(tab, settingKey) {
                        this.clearConfigSearch();
                        if (tab && validTabs.includes(tab)) {
                            this.activeTab = tab;
                        }

                        setTimeout(() => {
                            const container = this.$refs.tabsContainer;
                            if (!container) return;
                            const tabEl = container.querySelector('[data-config-tab="' + this.activeTab + '"]');
                            if (!tabEl) return;

                            let targetEl = null;
                            if (settingKey) {
                                const clean = String(settingKey).replace(/[^a-zA-Z0-9_]/g, '');
                                const compact = clean.replace(/_/g, '').toLowerCase();
                                const candidates = tabEl.querySelectorAll('[wire\\:model], [wire\\:model\\.defer], [wire\\:model\\.blur], [wire\\:model\\.live], [wire\\:click]');
                                for (let i = 0; i < candidates.length; i++) {
                                    const el = candidates[i];
                                    for (let a = 0; a < el.attributes.length; a++) {
                                        const val = el.attributes[a].value || '';
                                        if (val === 'settings.' + clean || val === clean || val.toLowerCase().includes(compact)) {
                                            targetEl = el;
                                            break;
                                        }
                                    }
                                    if (targetEl) break;
                                }
                            }

                            const highlightTarget = targetEl
                                ? (targetEl.closest('.p-5.rounded-2xl, .p-6, .rounded-2xl, div') || targetEl)
                                : tabEl.firstElementChild;

                            if (highlightTarget) {
                                highlightTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                highlightTarget.classList.remove('sf-config-spotlight-active');
                                void highlightTarget.offsetWidth;
                                highlightTarget.classList.add('sf-config-spotlight-active');
                                setTimeout(() => {
                                    highlightTarget.classList.remove('sf-config-spotlight-active');
                                }, 3400);
                            }

                            if (targetEl && (targetEl.tagName === 'INPUT' || targetEl.tagName === 'TEXTAREA') && targetEl.type !== 'checkbox' && targetEl.type !== 'radio' && targetEl.type !== 'file') {
                                setTimeout(() => targetEl.focus({ preventScroll: true }), 260);
                            }
                        }, 80);
                    }
                };
            };
        }
    </script>
</div>

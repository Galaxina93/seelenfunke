@php
    $staticConfigCatalog = \App\Services\System\GlobalProjectSearchService::getShopConfigCatalog();
    $staticNavCatalog = \App\Services\System\GlobalProjectSearchService::getNavigationCatalog();
@endphp

<div id="sf-global-project-search-root"
     x-data="sfGlobalProjectSearch()"
     x-init="initSearch()"
     @open-sf-global-search.window="openModal($event.detail && $event.detail.query ? $event.detail.query : '')"
     @keydown.escape.window="if (isOpen) closeModal()">

    @once
        <style>
            .sf-magic-search-ring {
                position: absolute;
                inset: 0;
                padding: 1.5px;
                border-radius: inherit;
                pointer-events: none;
                z-index: 20;
                overflow: hidden;
                -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
                -webkit-mask-composite: xor;
                mask-composite: exclude;
            }
            .sf-magic-search-ring::before {
                content: '';
                position: absolute;
                left: 50%;
                top: 50%;
                width: 240%;
                aspect-ratio: 1 / 1;
                transform: translate(-50%, -50%) rotate(0deg);
                background: conic-gradient(
                    from 0deg,
                    transparent 0deg,
                    rgba(197, 160, 89, 0.18) 50deg,
                    #C5A059 115deg,
                    #FDE68A 155deg,
                    #FFFBEB 172deg,
                    #C5A059 198deg,
                    rgba(197, 160, 89, 0.18) 255deg,
                    transparent 360deg
                );
                animation: sfMagicSearchRingSpin 3.2s linear infinite;
                will-change: transform;
            }
            .sf-magic-search-star {
                display: inline-block;
                color: #C5A059;
                animation: sfMagicSearchStarPulse 1.6s ease-in-out infinite;
                will-change: transform, opacity;
            }
            .sf-magic-micro-spark {
                position: absolute;
                pointer-events: none;
                color: #C5A059;
                font-size: 9px;
                line-height: 1;
                opacity: 0;
                animation: sfMagicMicroSparkRise 1.35s ease-out infinite;
                will-change: transform, opacity;
            }
            .sf-magic-shimmer-sweep {
                position: relative;
                overflow: hidden;
            }
            .sf-magic-shimmer-sweep::after {
                content: '';
                position: absolute;
                inset: 0;
                pointer-events: none;
                background: linear-gradient(
                    110deg,
                    transparent 18%,
                    rgba(255, 251, 235, 0.5) 50%,
                    transparent 82%
                );
                transform: translateX(-120%);
                animation: sfMagicSweepMove 1.15s ease-in-out infinite;
                will-change: transform;
            }
            @keyframes sfMagicSearchRingSpin {
                from { transform: translate(-50%, -50%) rotate(0deg); }
                to   { transform: translate(-50%, -50%) rotate(360deg); }
            }
            @keyframes sfMagicSearchStarPulse {
                0%, 100% { transform: scale(0.92) rotate(0deg); opacity: 0.85; }
                50%      { transform: scale(1.16) rotate(14deg); opacity: 1; }
            }
            @keyframes sfMagicMicroSparkRise {
                0%   { transform: translate3d(0, 6px, 0) scale(0.55); opacity: 0; }
                30%  { opacity: 0.95; }
                100% { transform: translate3d(var(--sx, 0px), -18px, 0) scale(1.05); opacity: 0; }
            }
            @keyframes sfMagicSweepMove {
                0%   { transform: translateX(-120%); }
                100% { transform: translateX(120%); }
            }
            @keyframes sfConfigSpotlightPulse {
                0%, 100% { box-shadow: 0 0 0 2px rgba(197, 160, 89, 0.85), 0 10px 28px rgba(197, 160, 89, 0.18); }
                50%      { box-shadow: 0 0 0 4px rgba(197, 160, 89, 0.95), 0 14px 36px rgba(197, 160, 89, 0.28); }
            }
            .sf-config-spotlight-active {
                animation: sfConfigSpotlightPulse 1.1s ease-in-out 3;
                border-color: #C5A059 !important;
            }
            @media (prefers-reduced-motion: reduce) {
                .sf-magic-search-ring::before,
                .sf-magic-search-star,
                .sf-magic-micro-spark,
                .sf-magic-shimmer-sweep::after {
                    animation: none !important;
                }
            }
        </style>
    @endonce

    <div x-ref="catalogSeed"
         data-config="{{ json_encode($staticConfigCatalog, JSON_UNESCAPED_UNICODE) }}"
         data-nav="{{ json_encode($staticNavCatalog, JSON_UNESCAPED_UNICODE) }}"
         class="hidden"></div>

    {{-- GLOBALES SEELENFUNKE SUCH-MODAL --}}
    <template x-teleport="body">
        <div x-cloak
             x-show="isOpen"
             style="display: none;"
             class="fixed inset-0 z-[10050] flex items-start justify-center pt-12 sm:pt-20 px-4 pb-8">

            {{-- Samtiger Backdrop --}}
            <div x-show="isOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="closeModal()"
                 class="fixed inset-0 bg-gray-950/55 dark:bg-black/80 backdrop-blur-md"></div>

            {{-- Hauptpanel mit rotierendem Seelenfunke Gold-Ring --}}
            <div x-show="isOpen"
                 x-transition:enter="transition cubic-bezier(0.22, 1, 0.36, 1) duration-250"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 -translate-y-3"
                 class="relative w-full max-w-2xl rounded-3xl bg-white/95 dark:bg-gray-900/95 border border-[#C5A059]/40 dark:border-[#C5A059]/35 shadow-[0_24px_70px_rgba(0,0,0,0.22)] dark:shadow-[0_28px_80px_rgba(0,0,0,0.85)] overflow-hidden flex flex-col max-h-[80vh]">

                <div class="sf-magic-search-ring"></div>

                {{-- Eingabe-Kopfzeile --}}
                <div class="relative z-30 flex items-center gap-3 px-5 py-4 border-b border-gray-200/80 dark:border-gray-800 bg-gradient-to-r from-[#C5A059]/[0.06] via-transparent to-transparent">
                    <div class="relative flex items-center justify-center w-9 h-9 rounded-xl bg-[#C5A059]/15 border border-[#C5A059]/35 shrink-0">
                        <span class="sf-magic-search-star text-sm font-bold">✦</span>
                        <template x-if="isLoading || activatingItemId">
                            <span>
                                <span class="sf-magic-micro-spark" style="left: 4px; bottom: 4px; --sx: -6px; animation-delay: 0s;">✦</span>
                                <span class="sf-magic-micro-spark" style="right: 4px; bottom: 5px; --sx: 6px; animation-delay: 0.35s;">✦</span>
                            </span>
                        </template>
                    </div>

                    <input x-ref="modalInput"
                           type="text"
                           x-model="query"
                           @input="onQueryChanged()"
                           @keydown.down.prevent="moveSelection(1)"
                           @keydown.up.prevent="moveSelection(-1)"
                           @keydown.enter.prevent="activateSelected()"
                           placeholder="Projektweit suchen: Einstellung, Bestellung, Produkt, Aufgabe, Rechnung, Wissen..."
                           class="flex-1 min-w-0 bg-transparent border-0 focus:ring-0 focus:outline-none text-sm sm:text-base font-semibold text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500" />

                    <template x-if="query.length > 0">
                        <button type="button"
                                @click="clearQuery()"
                                title="Eingabe leeren"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer">
                            <x-heroicon-m-x-mark class="w-4 h-4" />
                        </button>
                    </template>

                    <button type="button"
                            @click="closeModal()"
                            class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition cursor-pointer">
                        ESC
                    </button>
                </div>

                {{-- Ergebnis-Bereich --}}
                <div x-ref="resultsScroll" class="relative z-30 flex-1 overflow-y-auto p-3 sm:p-4 space-y-4 custom-scrollbar">

                    {{-- Schnellzugriff bei leerem Suchfeld --}}
                    <template x-if="query.trim() === ''">
                        <div class="p-2 space-y-3">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 px-1">Direktzugriff</div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <template x-for="quick in quickLinks" :key="quick.url">
                                    <button type="button"
                                            @click="triggerMagicNavigation(quick)"
                                            :class="activatingItemId === quick.id ? 'bg-[#C5A059] text-gray-950 border-[#C5A059] sf-magic-shimmer-sweep' : 'bg-gray-50 dark:bg-gray-950/70 hover:bg-[#C5A059]/10 border-gray-200/80 dark:border-gray-800 hover:border-[#C5A059]/50 text-gray-800 dark:text-gray-200'"
                                            class="flex items-center justify-between gap-2 px-3.5 py-2.5 rounded-2xl border text-left text-xs font-bold transition-all cursor-pointer group">
                                        <span class="truncate" x-text="activatingItemId === quick.id ? '✦ Magie...' : quick.title"></span>
                                        <span class="text-[#C5A059] opacity-70 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all">→</span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Gruppierte Live-Treffer --}}
                    <template x-if="query.trim() !== '' && displayGroups.length > 0">
                        <div class="space-y-4">
                            <template x-for="group in displayGroups" :key="group.key">
                                <div class="space-y-1.5">
                                    <div class="px-2 flex items-center justify-between">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-[#C5A059]" x-text="group.label"></span>
                                        <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500" x-text="group.items.length"></span>
                                    </div>

                                    <div class="space-y-1">
                                        <template x-for="item in group.items" :key="item.id">
                                            <button type="button"
                                                    :data-result-flat-idx="item._flatIdx"
                                                    @mouseenter="selectedIndex = item._flatIdx"
                                                    @click="triggerMagicNavigation(item)"
                                                    :class="activatingItemId === item.id
                                                        ? 'bg-[#C5A059] text-gray-950 border-[#C5A059] sf-magic-shimmer-sweep shadow-md scale-[0.995]'
                                                        : (selectedIndex === item._flatIdx
                                                            ? 'bg-[#C5A059]/15 dark:bg-[#C5A059]/20 border-[#C5A059]/60 text-gray-900 dark:text-white shadow-sm'
                                                            : 'bg-gray-50/70 dark:bg-gray-950/50 border-gray-200/60 dark:border-gray-800/80 text-gray-800 dark:text-gray-200 hover:border-[#C5A059]/40')"
                                                    class="w-full flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-2xl border text-left transition-all duration-150 cursor-pointer">

                                                <div class="min-w-0 flex-1">
                                                    <div class="text-xs sm:text-sm font-bold truncate" x-text="item.title"></div>
                                                    <div class="text-[11px] truncate mt-0.5"
                                                         :class="activatingItemId === item.id ? 'text-gray-900/80 font-semibold' : 'text-gray-500 dark:text-gray-400'"
                                                         x-text="item.subtitle"></div>
                                                </div>

                                                <div class="shrink-0 flex items-center gap-2">
                                                    <template x-if="activatingItemId === item.id">
                                                        <span class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-gray-950">
                                                            <span>✦ Magie wird aktiviert...</span>
                                                        </span>
                                                    </template>

                                                    <template x-if="activatingItemId !== item.id">
                                                        <div class="flex items-center gap-2">
                                                            <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300"
                                                                  x-text="item.badge"></span>
                                                            <span class="text-xs font-bold text-[#C5A059]"
                                                                  :class="selectedIndex === item._flatIdx ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-1'">→</span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Keine Treffer --}}
                    <template x-if="query.trim() !== '' && displayGroups.length === 0 && !isLoading">
                        <div class="py-10 text-center space-y-2">
                            <div class="text-sm font-bold text-gray-700 dark:text-gray-300">Keine Treffer gefunden</div>
                            <button type="button"
                                    @click="clearQuery()"
                                    class="px-3.5 py-1.5 rounded-xl bg-[#C5A059]/15 text-[#C5A059] border border-[#C5A059]/30 text-xs font-bold cursor-pointer">
                                Suche zurücksetzen
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Dezente Tastatur-Fusszeile --}}
                <div class="relative z-30 px-5 py-2.5 border-t border-gray-200/70 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-950/70 flex items-center justify-between text-[10px] font-bold text-gray-400 dark:text-gray-500">
                    <div class="flex items-center gap-3">
                        <span>↑↓ Auswahl</span>
                        <span>↵ Öffnen</span>
                        <span>ESC Schließen</span>
                    </div>
                    <span x-show="isLoading" class="text-[#C5A059] animate-pulse">✦ Durchsuche Projekt...</span>
                    <span x-show="!isLoading && flatItems.length > 0" x-text="flatItems.length + ' Treffer'"></span>
                </div>
            </div>
        </div>
    </template>

    <script>
        if (typeof window.sfGlobalProjectSearch === 'undefined') {
            window.sfNormalizeSearchStr = function (str) {
                return String(str || '')
                    .toLowerCase()
                    .replace(/ä/g, 'ae')
                    .replace(/ö/g, 'oe')
                    .replace(/ü/g, 'ue')
                    .replace(/ß/g, 'ss')
                    .replace(/§/g, ' ')
                    .replace(/[^a-z0-9#\-_.\s\/]/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();
            };

            window.sfPlayMagicSearchChime = function () {
                if (window.sfPlaySound) {
                    window.sfPlaySound('select');
                    return;
                }
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                if (!window.__sfSearchAudioCtx) {
                    window.__sfSearchAudioCtx = new AudioCtx();
                }
                const ctx = window.__sfSearchAudioCtx;
                if (ctx.state === 'suspended') {
                    ctx.resume();
                }
                const now = ctx.currentTime;
                [587.33, 880].forEach((freq, idx) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, now + idx * 0.055);
                    gain.gain.setValueAtTime(0.0001, now + idx * 0.055);
                    gain.gain.exponentialRampToValueAtTime(0.045, now + idx * 0.055 + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.055 + 0.24);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(now + idx * 0.055);
                    osc.stop(now + idx * 0.055 + 0.26);
                });
            };

            window.sfExecuteMagicJump = function (url, onDone) {
                window.sfPlayMagicSearchChime();

                const performNav = function () {
                    const targetPath = url.split('?')[0];
                    const curPath = window.location.pathname.replace(/\/+$/, '');

                    // Wenn wir bereits in /admin/configuration sind und eine Einstellung fokussieren: 0ms Direkt-Fokus ohne Reload!
                    if ((curPath === '/admin/configuration' || curPath === '/admin/settings') && targetPath === '/admin/configuration') {
                        const qIndex = url.indexOf('?');
                        if (qIndex !== -1) {
                            const params = new URLSearchParams(url.slice(qIndex + 1));
                            window.dispatchEvent(new CustomEvent('sf-focus-shop-setting', {
                                detail: { tab: params.get('tab') || 'general', focus: params.get('focus') || '' }
                            }));
                            if (window.sfStopPortalLoading) window.sfStopPortalLoading();
                            if (typeof onDone === 'function') onDone();
                            return;
                        }
                    }

                    if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                        const stopOnce = function () {
                            document.removeEventListener('livewire:navigated', stopOnce);
                            if (window.sfStopPortalLoading) window.sfStopPortalLoading();
                        };
                        document.addEventListener('livewire:navigated', stopOnce);
                        setTimeout(stopOnce, 900);
                        window.Livewire.navigate(url);
                    } else {
                        window.location.href = url;
                    }
                    if (typeof onDone === 'function') onDone();
                };

                if (typeof window.sfRunPortalThen === 'function') {
                    window.sfRunPortalThen(performNav, 'search');
                } else {
                    setTimeout(performNav, 180);
                }
            };

            window.sfGlobalProjectSearch = function () {
                return {
                    isOpen: false,
                    query: '',
                    isLoading: false,
                    selectedIndex: 0,
                    activatingItemId: null,
                    configCatalog: [],
                    navCatalog: [],
                    remoteGroups: [],
                    displayGroups: [],
                    flatItems: [],
                    _debounceTimer: null,
                    _abortCtrl: null,
                    quickLinks: [
                        { id: 'ql_cfg', title: 'Shop-Konfiguration', url: '/admin/configuration' },
                        { id: 'ql_ord', title: 'Bestellungen & Laser', url: '/admin/orders' },
                        { id: 'ql_prd', title: 'Produkte & Rohlinge', url: '/admin/products' },
                        { id: 'ql_tsk', title: 'Aufgaben & Fahrplan', url: '/admin/tasks' },
                        { id: 'ql_bnk', title: 'Banken & CSV-Import', url: '/admin/financial-banks' },
                        { id: 'ql_knw', title: 'Wissensgehirn', url: '/admin/tickets?view=knowledge' },
                    ],

                    initSearch() {
                        const seed = this.$refs.catalogSeed;
                        if (seed) {
                            if (seed.dataset.config) {
                                this.configCatalog = JSON.parse(seed.dataset.config);
                            }
                            if (seed.dataset.nav) {
                                this.navCatalog = JSON.parse(seed.dataset.nav);
                            }
                        }
                    },

                    openModal(initialQuery) {
                        this.activatingItemId = null;
                        if (typeof initialQuery === 'string' && initialQuery !== '') {
                            this.query = initialQuery;
                        }
                        this.isOpen = true;
                        this.onQueryChanged();
                        setTimeout(() => {
                            if (this.$refs.modalInput) {
                                this.$refs.modalInput.focus();
                                const len = this.query.length;
                                this.$refs.modalInput.setSelectionRange(len, len);
                            }
                        }, 35);
                    },

                    closeModal() {
                        this.isOpen = false;
                        this.activatingItemId = null;
                    },

                    clearQuery() {
                        this.query = '';
                        this.remoteGroups = [];
                        this.rebuildDisplayGroups();
                        if (this.$refs.modalInput) {
                            this.$refs.modalInput.focus();
                        }
                    },

                    onQueryChanged() {
                        const q = this.query.trim();
                        this.selectedIndex = 0;
                        this.remoteGroups = [];
                        this.rebuildDisplayGroups();

                        if (this._debounceTimer) clearTimeout(this._debounceTimer);
                        if (this._abortCtrl) {
                            this._abortCtrl.abort();
                            this._abortCtrl = null;
                        }

                        if (q.length < 2) {
                            this.isLoading = false;
                            return;
                        }

                        this.isLoading = true;
                        this._debounceTimer = setTimeout(() => {
                            this.fetchRemoteResults(q);
                        }, 140);
                    },

                    matchLocalCatalog(list, tokens, buildText) {
                        if (!tokens.length) return [];
                        const scored = [];
                        for (let i = 0; i < list.length; i++) {
                            const item = list[i];
                            const titleNorm = window.sfNormalizeSearchStr(item.title);
                            const fullNorm = window.sfNormalizeSearchStr(buildText(item));
                            let score = 0;
                            let ok = true;

                            for (let t = 0; t < tokens.length; t++) {
                                const tok = tokens[t];
                                if (titleNorm.startsWith(tok)) score += 35;
                                else if (titleNorm.includes(tok)) score += 24;
                                else if (fullNorm.includes(tok)) score += 12;
                                else {
                                    ok = false;
                                    break;
                                }
                            }
                            if (ok && score > 0) {
                                scored.push({ score, item });
                            }
                        }
                        scored.sort((a, b) => b.score - a.score);
                        return scored.slice(0, 5).map(x => x.item);
                    },

                    rebuildDisplayGroups() {
                        const q = window.sfNormalizeSearchStr(this.query);
                        if (!q) {
                            this.displayGroups = [];
                            this.flatItems = [];
                            return;
                        }

                        const tokens = q.split(' ').filter(Boolean);
                        const merged = [];

                        // Falls Remote-Daten bereits da sind, enthalten sie Fuzzy-Treffer + DB-Gruppen
                        if (this.remoteGroups.length > 0) {
                            for (let g = 0; g < this.remoteGroups.length; g++) {
                                merged.push({
                                    key: this.remoteGroups[g].key,
                                    label: this.remoteGroups[g].label,
                                    items: this.remoteGroups[g].items.map(it => Object.assign({}, it)),
                                });
                            }
                        } else {
                            // 0ms Sofort-Vorschau fuer Einstellungen & Seiten
                            const cfgHits = this.matchLocalCatalog(
                                this.configCatalog,
                                tokens,
                                it => it.title + ' ' + it.section + ' ' + it.tab_label + ' ' + it.keywords + ' ' + it.key
                            );
                            if (cfgHits.length) {
                                merged.push({
                                    key: 'settings',
                                    label: 'Shop-Konfiguration',
                                    items: cfgHits.map(hit => ({
                                        id: 'cfg_' + hit.key,
                                        title: hit.title,
                                        subtitle: hit.tab_label + ' · ' + hit.section,
                                        badge: 'Einstellung',
                                        url: '/admin/configuration?tab=' + encodeURIComponent(hit.tab) + '&focus=' + encodeURIComponent(hit.key),
                                    })),
                                });
                            }

                            const navHits = this.matchLocalCatalog(
                                this.navCatalog,
                                tokens,
                                it => it.title + ' ' + it.subtitle + ' ' + it.keywords + ' ' + it.url
                            );
                            if (navHits.length) {
                                merged.push({
                                    key: 'navigation',
                                    label: 'Seiten & Module',
                                    items: navHits.map((hit, idx) => ({
                                        id: 'nav_' + idx + '_' + hit.url,
                                        title: hit.title,
                                        subtitle: hit.subtitle,
                                        badge: 'Bereich',
                                        url: hit.url,
                                    })),
                                });
                            }
                        }

                        const flat = [];
                        for (let g = 0; g < merged.length; g++) {
                            for (let i = 0; i < merged[g].items.length; i++) {
                                merged[g].items[i]._flatIdx = flat.length;
                                flat.push(merged[g].items[i]);
                            }
                        }

                        this.displayGroups = merged;
                        this.flatItems = flat;
                        if (this.selectedIndex >= flat.length) {
                            this.selectedIndex = 0;
                        }
                    },

                    fetchRemoteResults(q) {
                        const ctrl = new AbortController();
                        this._abortCtrl = ctrl;

                        fetch('/admin/global-search?q=' + encodeURIComponent(q), {
                            method: 'GET',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            signal: ctrl.signal,
                        })
                            .then(res => res.ok ? res.json() : null)
                            .then(data => {
                                if (this._abortCtrl !== ctrl) return;
                                this.isLoading = false;
                                if (data && Array.isArray(data.groups) && this.query.trim() === q) {
                                    this.remoteGroups = data.groups;
                                    this.rebuildDisplayGroups();
                                }
                            })
                            .catch(() => {
                                if (this._abortCtrl === ctrl) {
                                    this.isLoading = false;
                                }
                            });
                    },

                    moveSelection(delta) {
                        const len = this.flatItems.length;
                        if (!len) return;
                        this.selectedIndex = (this.selectedIndex + delta + len) % len;
                        const container = this.$refs.resultsScroll;
                        if (container) {
                            const row = container.querySelector('[data-result-flat-idx="' + this.selectedIndex + '"]');
                            if (row && typeof row.scrollIntoView === 'function') {
                                row.scrollIntoView({ block: 'nearest' });
                            }
                        }
                    },

                    activateSelected() {
                        if (!this.flatItems.length) return;
                        const item = this.flatItems[this.selectedIndex] || this.flatItems[0];
                        if (item) {
                            this.triggerMagicNavigation(item);
                        }
                    },

                    triggerMagicNavigation(item) {
                        if (!item || !item.url || this.activatingItemId) return;
                        this.activatingItemId = item.id;
                        const self = this;
                        setTimeout(() => {
                            window.sfExecuteMagicJump(item.url, function () {
                                self.closeModal();
                            });
                        }, 160);
                    }
                };
            };
        }
    </script>
</div>

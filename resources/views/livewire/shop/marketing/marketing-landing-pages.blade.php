@php
    $currentLayout = in_array($layoutMode ?? 'large', ['large', 'medium', 'list'], true) ? $layoutMode : 'large';
    $totalProductsCount = $products->count();
    $activeLpCount = $products->filter(fn($p) => $p->landingPage && $p->landingPage->status === 'active')->count();
    $seasonalEnabledCount = $products->filter(fn($p) => $p->isAutoSeasonalMainImageEnabled())->count();
    $totalVisitsAll = array_sum($visitCountsTotal ?? []);
    $totalVisits30d = array_sum($visitCounts30d ?? []);
@endphp

<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;"
     x-data="{
        syncLandingPageLayout() {
            try {
                const saved = localStorage.getItem('admin_landing_pages_layout_mode');
                if (saved && ['large', 'medium', 'list'].includes(saved) && saved !== '{{ $currentLayout }}') {
                    $wire.setLayoutMode(saved);
                } else {
                    localStorage.setItem('admin_landing_pages_layout_mode', '{{ $currentLayout }}');
                }
            } catch (e) {}
        }
     }"
     x-init="syncLandingPageLayout()"
     class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6 pb-20 animate-fade-in-up font-sans antialiased text-gray-700 dark:text-gray-300">

    {{-- HEADER BEREICH (Identische Ansichtslogik & Optik wie /admin/product-templates) --}}
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-end mb-6 gap-6 animate-fade-in-up">
        <div>
            <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white mb-2 tracking-tight">
                Produkt-Landing-Pages
            </h1>
            <div class="flex flex-wrap items-center gap-3 text-gray-600 dark:text-gray-400">
                <p class="text-xs sm:text-sm font-medium">
                    Dynamische Adaption an Produkttyp, Jahreszeiten-Vorlagen, Feiertags-Autopilot & EU AI Act.
                </p>
                <span class="px-3 py-1.5 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 dark:shadow-inner rounded-lg text-[10px] font-black uppercase tracking-widest text-gray-500">
                    {{ $activeLpCount }}/{{ $totalProductsCount }} Aktiv
                </span>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 sm:gap-3.5 w-full xl:w-auto">
            {{-- ANSICHTSAUSWAHL (Groß / Mittel / Liste) --}}
            <div class="inline-flex items-center p-1.5 bg-white dark:bg-gray-900/90 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] dark:shadow-inner self-start sm:self-auto" role="group" aria-label="Ansichtsauswahl">
                {{-- Groß (Standard) --}}
                <button type="button"
                        wire:click="setLayoutMode('large')"
                        onclick="try { localStorage.setItem('admin_landing_pages_layout_mode', 'large'); } catch (e) {}"
                        class="px-3.5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 cursor-pointer {{ $currentLayout === 'large' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                        title="Große Kachelansicht">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1.5" />
                        <rect x="14" y="3" width="7" height="7" rx="1.5" />
                        <rect x="3" y="14" width="7" height="7" rx="1.5" />
                        <rect x="14" y="14" width="7" height="7" rx="1.5" />
                    </svg>
                    <span>Groß</span>
                </button>

                {{-- Mittel --}}
                <button type="button"
                        wire:click="setLayoutMode('medium')"
                        onclick="try { localStorage.setItem('admin_landing_pages_layout_mode', 'medium'); } catch (e) {}"
                        class="px-3.5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 cursor-pointer {{ $currentLayout === 'medium' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                        title="Mittlere Kachelansicht">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="4.5" height="4.5" rx="1" />
                        <rect x="9.75" y="3" width="4.5" height="4.5" rx="1" />
                        <rect x="16.5" y="3" width="4.5" height="4.5" rx="1" />
                        <rect x="3" y="9.75" width="4.5" height="4.5" rx="1" />
                        <rect x="9.75" y="9.75" width="4.5" height="4.5" rx="1" />
                        <rect x="16.5" y="9.75" width="4.5" height="4.5" rx="1" />
                        <rect x="3" y="16.5" width="4.5" height="4.5" rx="1" />
                        <rect x="9.75" y="16.5" width="4.5" height="4.5" rx="1" />
                        <rect x="16.5" y="16.5" width="4.5" height="4.5" rx="1" />
                    </svg>
                    <span>Mittel</span>
                </button>

                {{-- Listenansicht --}}
                <button type="button"
                        wire:click="setLayoutMode('list')"
                        onclick="try { localStorage.setItem('admin_landing_pages_layout_mode', 'list'); } catch (e) {}"
                        class="px-3.5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 cursor-pointer {{ $currentLayout === 'list' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                        title="Listenansicht">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span>Liste</span>
                </button>
            </div>

            {{-- STATUS FILTER --}}
            <select wire:model.live="filterStatus"
                    class="px-4 py-3.5 bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] text-xs font-bold text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none cursor-pointer">
                <option value="all">Alle Status</option>
                <option value="active">Nur Aktive</option>
                <option value="draft">Entwurf / Inaktiv</option>
            </select>

            {{-- PRODUKTTYP FILTER --}}
            <select wire:model.live="filterType"
                    class="px-4 py-3.5 bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] text-xs font-bold text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none cursor-pointer">
                <option value="all">Alle Produkttypen</option>
                <option value="physical">Physisch (Manufaktur)</option>
                <option value="digital">Digital / Gutschein</option>
                <option value="service">Service / Beratung</option>
            </select>

            {{-- SUCHE --}}
            <div class="relative w-full sm:w-64 group">
                <input type="text"
                       wire:model.live.debounce.250ms="search"
                       placeholder="Landing Page suchen..."
                       class="w-full pl-11 pr-4 py-3.5 bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:bg-white dark:focus:bg-gray-950 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all dark:shadow-inner outline-none">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400 dark:text-gray-600 group-focus-within:text-[var(--theme-color)] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            {{-- SYNC & TEXTE ERNEUERN BUTTONS --}}
            <button type="button"
                    wire:click="syncAllLandingPages(false)"
                    class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 px-5 py-3.5 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest dark:shadow-inner hover:bg-gray-50 dark:hover:bg-gray-900 hover:text-gray-900 dark:hover:text-white transition-all flex items-center justify-center gap-2 whitespace-nowrap cursor-pointer"
                    title="Alle Produkte mit Landing Pages synchronisieren">
                <svg class="w-3.5 h-3.5 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Sync</span>
            </button>

            <button type="button"
                    wire:click="syncAllLandingPages(true)"
                    onclick="confirm('Sollen die Marketingtexte aller Landing Pages anhand der aktuellen Produktdaten neu generiert werden?') || event.stopImmediatePropagation()"
                    class="bg-[var(--theme-color)] border border-[var(--theme-color-50)] text-white dark:text-gray-900 px-5 py-3.5 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest dark:shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:bg-black/90 dark:hover:bg-white hover:scale-[1.02] transition-all flex items-center justify-center gap-2 whitespace-nowrap cursor-pointer">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                </svg>
                <span>Texte erneuern</span>
            </button>
        </div>
    </div>

    {{-- KPI ÜBERSICHT (Saison-Logik, Aktive Seiten, Saisonbilder, Aufrufe) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-3xl p-4 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0 font-black text-base">
                {{ $activeLpCount }}/{{ $totalProductsCount }}
            </div>
            <div class="min-w-0">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">Aktive Landing Pages</div>
                <div class="text-sm font-bold text-gray-900 dark:text-white truncate mt-0.5">Auto-Sync für neue Produkte</div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-3xl p-4 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 flex items-center justify-center text-2xl shrink-0">
                {{ $activeTheme['theme']['icon'] ?? '✨' }}
            </div>
            <div class="min-w-0">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">Aktive Jahreszeit / Event</div>
                <div class="text-sm font-bold text-gray-900 dark:text-white truncate mt-0.5" title="{{ $activeTheme['event_name'] ?? '' }}">
                    {{ $activeTheme['theme']['label'] ?? 'Manufaktur' }}
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-3xl p-4 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-500/10 border border-purple-200 dark:border-purple-500/20 flex items-center justify-center text-purple-600 dark:text-purple-400 shrink-0 font-black text-base">
                {{ $seasonalEnabledCount }}
            </div>
            <div class="min-w-0">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">Auto-Saisonbild aktiv</div>
                <div class="text-sm font-bold text-gray-900 dark:text-white truncate mt-0.5">
                    {{ $autopilotEnabled ? 'Feiertags-Autopilot aktiv' : 'Kalender-Jahreszeit aktiv' }}
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-3xl p-4 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0 font-black text-base">
                {{ $totalVisits30d }}
            </div>
            <div class="min-w-0">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">Aufrufe (30 Tage)</div>
                <div class="text-sm font-bold text-gray-900 dark:text-white truncate mt-0.5">{{ $totalVisitsAll }} Aufrufe gesamt</div>
            </div>
        </div>
    </div>

    @if($actionError)
        <div class="p-4 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 rounded-2xl flex items-start gap-4">
            <div class="p-2 bg-red-100 dark:bg-red-500/20 rounded-xl shrink-0">
                <svg class="w-5 h-5 text-red-600 dark:text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div>
                <h3 class="text-red-700 dark:text-red-400 font-bold mb-0.5 text-sm">Achtung</h3>
                <p class="text-red-600 dark:text-red-300 text-xs leading-relaxed">{{ $actionError }}</p>
            </div>
        </div>
    @endif

    {{-- EMPTY STATE --}}
    @if($products->isEmpty())
        <div class="bg-white dark:bg-gray-900/50 backdrop-blur-md p-16 rounded-[3rem] border-2 border-dashed border-gray-200 dark:border-gray-800 text-center dark:shadow-inner animate-fade-in-up">
            <div class="w-20 h-20 rounded-[1.5rem] bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center mx-auto mb-5 dark:shadow-inner">
                <svg class="w-10 h-10 text-gray-400 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <h3 class="text-xl font-serif font-bold text-gray-900 dark:text-white mb-2 tracking-wide">Keine Landing Pages gefunden</h3>
            <p class="text-xs text-gray-600 dark:text-gray-500">Für die gewählten Filterkriterien wurden keine Produkte gefunden.</p>
        </div>
    @else
        @if($currentLayout === 'list')
            {{-- ===================================================================== --}}
            {{-- 1. LISTENANSICHT (KOMPAKTE ZEILEN WIE BEI /admin/product-templates)    --}}
            {{-- ===================================================================== --}}
            <div wire:key="lp-layout-list" class="flex flex-col gap-3 animate-fade-in-up" style="animation-delay: 100ms;">
                @foreach($products as $product)
                    @php
                        $lp = $product->landingPage;
                        $isLpActive = $lp && $lp->status === 'active';
                        $hasCustomFile = $lp && \Illuminate\Support\Facades\File::exists(resource_path('views/frontend/pages/landingpages/' . $lp->slug . '.blade.php'));
                        $mainImageMeta = $product->resolveMainImageMeta();
                        $resolvedImageUrl = $mainImageMeta['url'] ?? null;
                        $isAiImage = (bool) ($mainImageMeta['is_ai_generated'] ?? false);
                        $activeSeasonalTemplate = $product->isAutoSeasonalMainImageEnabled() ? $product->resolveSeasonalTemplate() : null;
                        $activeTemplatesCount = $product->templates ? $product->templates->count() : 0;
                        $lpPath = $lp ? '/l/' . $lp->slug : '/l/' . $product->slug;
                        $visitsTotal = (int) ($visitCountsTotal[$lpPath] ?? 0);
                        $visits30d = (int) ($visitCounts30d[$lpPath] ?? 0);
                    @endphp

                    <div wire:key="lp-list-row-{{ $product->id }}"
                         class="bg-white dark:bg-gray-900/80 backdrop-blur-md p-4 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-xl hover:border-gray-300 dark:hover:border-gray-700 transition-all duration-300 flex flex-col lg:flex-row lg:items-center justify-between gap-4 relative overflow-hidden group">

                        {{-- Status-Balken Links --}}
                        <div class="absolute top-0 left-0 w-1.5 h-full bg-gray-100 dark:bg-gray-950 z-20">
                            <div class="w-full h-full transition-all duration-500 {{ $isLpActive ? 'bg-[var(--theme-color)]' : 'bg-gray-400 dark:bg-gray-700' }}"></div>
                        </div>

                        {{-- Links: Vorschaubild + Name + Badges + Pfad --}}
                        <div class="flex items-center gap-4 min-w-0 pl-2 flex-1">
                            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 overflow-hidden shrink-0 relative">
                                @if($resolvedImageUrl)
                                    <img src="{{ $resolvedImageUrl }}"
                                         alt="{{ $product->name }}"
                                         @if($isAiImage) data-is-ai-generated="true" @endif
                                         class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500">
                                @else
                                    <div class="flex items-center justify-center h-full text-gray-400 dark:text-gray-700">
                                        <x-heroicon-o-photo class="w-6 h-6 opacity-40" />
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2.5 flex-wrap mb-1">
                                    <h3 class="font-serif text-base sm:text-lg font-bold text-gray-900 dark:text-white truncate">{{ $product->name }}</h3>

                                    @if($activeSeasonalTemplate)
                                        <span class="px-2.5 py-0.5 rounded-lg bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-20)] text-[9px] font-black uppercase tracking-widest">
                                            {{ $activeSeasonalTemplate->holiday ?: $activeSeasonalTemplate->name }}
                                        </span>
                                    @endif

                                    @if($product->type === 'digital')
                                        <span class="px-2.5 py-0.5 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20 text-[9px] font-black uppercase tracking-widest">Digital</span>
                                    @elseif($product->type === 'service')
                                        <span class="px-2.5 py-0.5 rounded-lg bg-orange-50 dark:bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-200 dark:border-orange-500/20 text-[9px] font-black uppercase tracking-widest">Service</span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-[9px] font-black uppercase tracking-widest">Physisch</span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="text-[var(--theme-color)] font-mono font-bold">{{ $product->slug === 'geschenkgutschein' ? '5,00 € – 1.000,00 €' : $product->formatted_price }}</span>
                                    <span class="text-[11px] font-mono text-gray-400 dark:text-gray-500">{{ $lpPath }}</span>
                                    <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500">{{ $visits30d }} Aufrufe (30T) · {{ $visitsTotal }} gesamt</span>
                                </div>
                            </div>
                        </div>

                        {{-- Rechts: Status + Aktionen --}}
                        <div class="flex flex-wrap items-center justify-between lg:justify-end gap-3 sm:gap-4 pl-2 lg:pl-0 border-t lg:border-t-0 pt-3 lg:pt-0 border-gray-100 dark:border-gray-800/80">
                            @if($lp)
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" @click.away="open = false" class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest rounded-xl border flex items-center gap-1.5 transition-all cursor-pointer {{ $isLpActive ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-500/30' : 'bg-white dark:bg-gray-950 text-gray-700 dark:text-gray-400 border-gray-200 dark:border-gray-700' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $isLpActive ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                        {{ $isLpActive ? 'Aktiv' : 'Entwurf' }}
                                        <svg class="w-3 h-3 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>

                                    <div x-show="open" class="absolute right-0 mt-2 w-32 bg-white dark:bg-gray-950 rounded-xl shadow-xl border border-gray-200 dark:border-gray-800 overflow-hidden z-30" style="display: none;" x-transition>
                                        <button wire:click="updateStatus('{{ $lp->id }}', 'active'); open = false" class="w-full text-left px-4 py-2.5 text-[9px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-900 text-emerald-700 dark:text-emerald-400 border-b border-gray-200 dark:border-gray-800 flex items-center gap-2 cursor-pointer">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktiv
                                        </button>
                                        <button wire:click="updateStatus('{{ $lp->id }}', 'draft'); open = false" class="w-full text-left px-4 py-2.5 text-[9px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-900 text-gray-600 dark:text-gray-500 flex items-center gap-2 cursor-pointer">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Entwurf
                                        </button>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-widest">
                                    <a href="{{ route('landing-page', $lp->slug) }}"
                                       target="_blank"
                                       class="text-[var(--theme-color)] hover:text-gray-900 dark:hover:text-white transition-colors border-b border-[var(--theme-color-30)] hover:border-gray-900 dark:hover:border-white pb-0.5">
                                        Live öffnen &nearr;
                                    </a>
                                    <button type="button"
                                            wire:click="regenerateCopy('{{ $lp->id }}')"
                                            class="text-gray-500 hover:text-[var(--theme-color)] transition-colors border-b border-gray-300 dark:border-gray-700 hover:border-[var(--theme-color)] pb-0.5 cursor-pointer"
                                            title="Texte intelligent aus aktuellen Produktdaten neu generieren">
                                        Texte erneuern
                                    </button>
                                    <button type="button"
                                            wire:click="openEditModal('{{ $lp->id }}')"
                                            class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors border-b border-gray-300 dark:border-gray-600 hover:border-gray-900 dark:hover:border-white pb-0.5 cursor-pointer">
                                        Bearbeiten &rarr;
                                    </button>
                                </div>
                            @else
                                <button type="button"
                                        wire:click="generateLandingPage('{{ $product->id }}')"
                                        class="px-4 py-2 rounded-xl bg-[var(--theme-color)] text-white dark:text-gray-950 text-[10px] font-black uppercase tracking-widest hover:opacity-90 transition-all cursor-pointer">
                                    Landing Page aktivieren
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- ===================================================================== --}}
            {{-- 2. GROSSE & MITTLERE KACHELANSICHT (GRID WIE /admin/product-templates) --}}
            {{-- ===================================================================== --}}
            <div wire:key="lp-layout-{{ $currentLayout }}"
                 class="grid {{ $currentLayout === 'medium' ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5' : 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 sm:gap-8' }} animate-fade-in-up"
                 style="animation-delay: 100ms;">
                @foreach($products as $product)
                    @php
                        $lp = $product->landingPage;
                        $isLpActive = $lp && $lp->status === 'active';
                        $hasCustomFile = $lp && \Illuminate\Support\Facades\File::exists(resource_path('views/frontend/pages/landingpages/' . $lp->slug . '.blade.php'));

                        // Dynamische Saison- & Produktbild-Auflösung nach neuester System-Logik
                        $mainImageMeta = $product->resolveMainImageMeta();
                        $resolvedImageUrl = $mainImageMeta['url'] ?? null;
                        $isAiImage = (bool) ($mainImageMeta['is_ai_generated'] ?? false);
                        $activeSeasonalTemplate = $product->isAutoSeasonalMainImageEnabled() ? $product->resolveSeasonalTemplate() : null;
                        $activeTemplatesCount = $product->templates ? $product->templates->count() : 0;

                        $lpPath = $lp ? '/l/' . $lp->slug : '/l/' . $product->slug;
                        $visitsTotal = (int) ($visitCountsTotal[$lpPath] ?? 0);
                        $visits30d = (int) ($visitCounts30d[$lpPath] ?? 0);

                        $isSale = !empty($product->compare_at_price) && (int) $product->compare_at_price > (int) $product->price;
                        $saleBadgeText = $isSale ? ($product->configurator_settings['autopilot_badge_text'] ?? 'Sale aktiv') : null;
                    @endphp

                    <div wire:key="lp-card-{{ $currentLayout }}-{{ $product->id }}"
                         class="bg-white dark:bg-gray-900/80 backdrop-blur-md {{ $currentLayout === 'medium' ? 'p-4 rounded-3xl' : 'p-5 sm:p-6 rounded-[2.5rem]' }} border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl hover:border-gray-300 dark:hover:border-gray-700 dark:hover:shadow-[0_0_40px_rgba(0,0,0,0.6)] transition-all duration-300 flex flex-col h-full relative overflow-hidden group">

                        {{-- Status-Balken Oben --}}
                        <div class="absolute top-0 left-0 w-full h-1 bg-gray-100 dark:bg-gray-950 z-30">
                            <div class="h-full w-full transition-all duration-500 {{ $isLpActive ? 'bg-[var(--theme-color)] dark:shadow-[0_0_10px_rgba(197,160,89,0.8)]' : 'bg-gray-400 dark:bg-gray-700' }}"></div>
                        </div>

                        {{-- Status Switcher --}}
                        @if($lp)
                            <div class="absolute {{ $currentLayout === 'medium' ? 'top-4 right-4' : 'top-6 right-6' }} z-20"
                                 x-data="{ open: false }">
                                <button @click="open = !open" @click.away="open = false"
                                        class="{{ $currentLayout === 'medium' ? 'px-2.5 py-1.5 text-[8px]' : 'px-3.5 py-2 text-[9px]' }} font-black uppercase tracking-widest rounded-xl border shadow-sm flex items-center gap-2 transition-all backdrop-blur-md cursor-pointer {{ $isLpActive ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-500/30' : 'bg-white dark:bg-gray-950/80 text-gray-700 dark:text-gray-400 border-gray-200 dark:border-gray-700' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $isLpActive ? 'bg-emerald-500 dark:shadow-[0_0_8px_currentColor] animate-pulse' : 'bg-gray-400 dark:bg-gray-500' }}"></span>
                                    {{ $isLpActive ? 'Aktiv' : 'Entwurf' }}
                                    <svg class="w-3.5 h-3.5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>

                                <div x-show="open" class="absolute right-0 mt-2 w-36 bg-white dark:bg-gray-950 rounded-2xl shadow-xl dark:shadow-[0_0_30px_rgba(0,0,0,0.8)] border border-gray-200 dark:border-gray-800 overflow-hidden z-30" style="display: none;" x-transition>
                                    <button wire:click="updateStatus('{{ $lp->id }}', 'active'); open = false" class="w-full text-left px-5 py-3.5 text-[10px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-900 text-emerald-700 dark:text-emerald-400 border-b border-gray-200 dark:border-gray-800 transition-colors flex items-center gap-2 cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktiv
                                    </button>
                                    <button wire:click="updateStatus('{{ $lp->id }}', 'draft'); open = false" class="w-full text-left px-5 py-3.5 text-[10px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-900 text-gray-600 dark:text-gray-500 transition-colors flex items-center gap-2 cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 dark:bg-gray-500"></span> Entwurf
                                    </button>
                                </div>
                            </div>
                        @endif

                        {{-- Vorschaubild (1:1 aspect-square in Groß & Mittel, damit nichts abgeschnitten oder zu nah dargestellt wird) --}}
                        <div class="{{ $currentLayout === 'medium' ? 'aspect-square rounded-2xl mb-4 mt-1.5' : 'aspect-square rounded-[1.5rem] mb-6 mt-2' }} bg-gray-100 dark:bg-gray-950 overflow-hidden relative border border-gray-200 dark:border-gray-800 dark:shadow-inner group-hover:border-gray-300 dark:group-hover:border-gray-700 transition-colors">
                            {{-- Saison-/Anlass-Badge oben links --}}
                            <div class="absolute top-3 left-3 z-10 flex flex-col items-start gap-1.5 pointer-events-none max-w-[65%]">
                                @if($activeSeasonalTemplate)
                                    <span class="px-2.5 py-1 rounded-full bg-gray-900/80 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-widest border border-white/15 shadow-sm truncate max-w-full">
                                        {{ $activeSeasonalTemplate->holiday ?: $activeSeasonalTemplate->name }}
                                    </span>
                                @elseif($product->slug === 'geschenkgutschein' && !empty($mainImageMeta['label']))
                                    <span class="px-2.5 py-1 rounded-full bg-gray-900/80 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-widest border border-white/15 shadow-sm truncate max-w-full">
                                        {{ $mainImageMeta['label'] }}
                                    </span>
                                @endif

                                @if($saleBadgeText)
                                    <span class="px-2.5 py-0.5 rounded-full bg-red-600/95 text-white text-[8px] font-black uppercase tracking-widest shadow-sm">
                                        {{ $saleBadgeText }}
                                    </span>
                                @endif
                            </div>

                            @if($resolvedImageUrl)
                                <img src="{{ $resolvedImageUrl }}"
                                     alt="{{ $product->name }}"
                                     @if($isAiImage) data-is-ai-generated="true" @endif
                                     class="w-full h-full object-cover object-center transition-transform duration-700 group-hover:scale-[1.03] opacity-95 group-hover:opacity-100">

                                @if($isAiImage)
                                    <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                          class="pointer-events-none select-none absolute bottom-2.5 right-2.5 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                        KI-generiert
                                    </span>
                                @endif
                            @else
                                <div class="flex items-center justify-center h-full text-gray-400 dark:text-gray-700 text-[10px] flex-col gap-3 font-black uppercase tracking-widest">
                                    <x-heroicon-o-photo class="w-10 h-10 opacity-30" />
                                    <span>Kein Bild</span>
                                </div>
                            @endif

                            {{-- Aufrufe-Zähler unten links --}}
                            <div class="absolute bottom-2.5 left-2.5 z-20 bg-black/60 backdrop-blur-sm text-white text-[9px] font-mono px-2 py-0.5 rounded-md border border-white/15 pointer-events-none">
                                {{ $visits30d }} Aufrufe (30T)
                            </div>
                        </div>

                        {{-- Titel & Preis --}}
                        <div class="flex items-baseline justify-between gap-2 mb-1">
                            <h3 class="font-serif {{ $currentLayout === 'medium' ? 'text-base' : 'text-lg md:text-xl' }} font-bold text-gray-900 dark:text-white truncate tracking-wide" title="{{ $product->name }}">
                                {{ $product->name }}
                            </h3>
                            <span class="text-xs font-mono font-bold text-[var(--theme-color)] shrink-0">
                                {{ $product->slug === 'geschenkgutschein' ? '5,00 € – 1.000,00 €' : $product->formatted_price }}
                            </span>
                        </div>

                        {{-- Pfad & Produkttyp Badges --}}
                        <div class="flex items-center justify-between gap-2 mb-3 flex-wrap">
                            <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500 truncate">{{ $lpPath }}</span>
                            <div class="flex items-center gap-1.5">
                                @if($activeTemplatesCount > 0)
                                    <span class="px-2 py-0.5 rounded-md bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/20 text-[8px] font-black uppercase tracking-widest">
                                        {{ $activeTemplatesCount }} Vorlagen
                                    </span>
                                @endif
                                @if($hasCustomFile)
                                    <span class="px-2 py-0.5 rounded-md bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/20 text-[8px] font-black uppercase tracking-widest">
                                        Override
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Headline Vorschau --}}
                        <p class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2 leading-relaxed mb-4 border-t border-gray-100 dark:border-gray-800/50 pt-3">
                            {{ $lp?->headline ?: ($product->short_description ?: $product->description) }}
                        </p>

                        {{-- Footer Aktionen (Identisch zu /admin/product-templates) --}}
                        <div class="mt-auto pt-4 border-t border-gray-200 dark:border-gray-800/80 flex justify-between items-center text-[10px] font-black uppercase tracking-widest gap-2">
                            @if($lp)
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('landing-page', $lp->slug) }}"
                                       target="_blank"
                                       class="text-[var(--theme-color)] hover:text-gray-900 dark:hover:text-white transition-colors border-b border-[var(--theme-color-30)] hover:border-gray-900 dark:hover:border-white pb-0.5">
                                        Live &nearr;
                                    </a>

                                    <button type="button"
                                            x-data="{ copied: false }"
                                            @click="
                                                navigator.clipboard.writeText('{{ url('/l/' . $lp->slug) }}');
                                                copied = true;
                                                setTimeout(() => copied = false, 2000);
                                            "
                                            class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors cursor-pointer"
                                            title="Landing-Page-Link kopieren">
                                        <span x-show="!copied">Link</span>
                                        <span x-show="copied" x-cloak class="text-emerald-500">Kopiert!</span>
                                    </button>
                                </div>

                                <div class="flex items-center gap-3">
                                    <button type="button"
                                            wire:click="regenerateCopy('{{ $lp->id }}')"
                                            class="text-gray-500 hover:text-[var(--theme-color)] transition-colors border-b border-gray-300 dark:border-gray-700 hover:border-[var(--theme-color)] pb-0.5 cursor-pointer"
                                            title="Texte aus Produktdaten erneuern">
                                        Sync
                                    </button>
                                    <button type="button"
                                            wire:click="openEditModal('{{ $lp->id }}')"
                                            class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors border-b border-gray-300 dark:border-gray-600 hover:border-gray-900 dark:hover:border-white pb-0.5 cursor-pointer">
                                        Bearbeiten &rarr;
                                    </button>
                                </div>
                            @else
                                <button type="button"
                                        wire:click="generateLandingPage('{{ $product->id }}')"
                                        class="w-full text-center text-[var(--theme-color)] hover:text-gray-900 dark:hover:text-white transition-colors py-1 cursor-pointer">
                                    + Landing Page aktivieren
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- BEARBEITEN-MODAL --}}
    @if($showEditModal)
        @php
            $modalProduct = $editingLandingPage?->product;
            $modalImageMeta = $modalProduct ? $modalProduct->resolveMainImageMeta() : null;
            $modalSeasonalTpl = ($modalProduct && $modalProduct->isAutoSeasonalMainImageEnabled()) ? $modalProduct->resolveSeasonalTemplate() : null;
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-gray-900/60 dark:bg-gray-950/80 backdrop-blur-md">
            <div class="relative w-full max-w-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl shadow-2xl p-6 sm:p-8 space-y-6">
                <div class="flex items-start justify-between border-b border-gray-200 dark:border-gray-800 pb-4 gap-4">
                    <div class="flex items-center gap-3.5">
                        @if(!empty($modalImageMeta['url']))
                            <img src="{{ $modalImageMeta['url'] }}"
                                 alt="{{ $modalProduct?->name }}"
                                 class="w-14 h-14 rounded-2xl object-cover border border-gray-200 dark:border-gray-700 shrink-0">
                        @endif
                        <div>
                            <h2 class="text-xl font-serif font-bold text-gray-900 dark:text-white">
                                Landing Page bearbeiten: {{ $modalProduct?->name }}
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                @if($modalSeasonalTpl)
                                    Aktives Saisonbild: <strong>{{ $modalSeasonalTpl->name }} ({{ $modalSeasonalTpl->holiday }})</strong>
                                @else
                                    Passe Titel, Werbetexte und CTA-Button für maximale Conversion an.
                                @endif
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeEditModal" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1 rounded-lg cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex items-center justify-between bg-amber-50/70 dark:bg-amber-500/10 border border-amber-200/80 dark:border-amber-500/20 rounded-2xl px-4 py-2.5">
                    <span class="text-xs text-amber-900 dark:text-amber-300">
                        Möchtest du die Texte automatisch passend zu Produkttyp & Material vorbelegen?
                    </span>
                    <button type="button"
                            wire:click="applySmartDefaultsInModal"
                            class="text-xs font-bold text-amber-900 dark:text-amber-200 underline hover:no-underline shrink-0 ml-3 cursor-pointer">
                        ✨ Aus Produktdaten füllen
                    </button>
                </div>

                <form wire:submit.prevent="saveEdit" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Produkt-Titel / SEO-Titel auf Landingpage</label>
                        <input type="text" wire:model="editTitle" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3 text-sm text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] focus:outline-none">
                        @error('editTitle') <span class="text-xs text-red-500 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Headline / Subline (Verkaufs-Hook im Hero)</label>
                        <input type="text" wire:model="editHeadline" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3 text-sm text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] focus:outline-none" placeholder="z.B. Magische Lichtbrechung für die Ewigkeit">
                        @error('editHeadline') <span class="text-xs text-red-500 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Werbetext / Story (Details-Sektion)</label>
                        <textarea rows="5" wire:model="editSalesCopy" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3 text-sm text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] focus:outline-none" placeholder="Beschreibe die Besonderheit, Emotion und Veredelung des Produkts..."></textarea>
                        @error('editSalesCopy') <span class="text-xs text-red-500 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">CTA-Button Text</label>
                            <input type="text" wire:model="editCtaText" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3 text-sm text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] focus:outline-none" placeholder="Jetzt dein Unikat gestalten">
                            @error('editCtaText') <span class="text-xs text-red-500 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Status</label>
                            <select wire:model="editStatus" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3 text-sm text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] focus:outline-none">
                                <option value="active">Aktiv (öffentlich aufrufbar)</option>
                                <option value="draft">Entwurf (nur Admin-Vorschau)</option>
                            </select>
                            @error('editStatus') <span class="text-xs text-red-500 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="closeEditModal" class="px-5 py-2.5 text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white rounded-xl hover:bg-gray-100 dark:hover:bg-white/5 transition-colors cursor-pointer">
                            Abbrechen
                        </button>
                        <button type="submit" class="px-6 py-2.5 text-xs font-black uppercase tracking-widest text-gray-950 bg-[var(--theme-color)] hover:opacity-90 rounded-xl shadow-sm transition-all cursor-pointer">
                            Änderungen speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

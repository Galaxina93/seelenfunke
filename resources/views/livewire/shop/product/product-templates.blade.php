@php
    $currentLayout = in_array($layoutMode ?? 'large', ['large', 'medium', 'list'], true) ? $layoutMode : 'large';
@endphp

<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;"
     x-data="{
        syncTemplateLayout() {
            try {
                const saved = localStorage.getItem('admin_product_templates_layout_mode');
                if (saved && ['large', 'medium', 'list'].includes(saved) && saved !== '{{ $currentLayout }}') {
                    $wire.setLayoutMode(saved);
                } else {
                    localStorage.setItem('admin_product_templates_layout_mode', '{{ $currentLayout }}');
                }
            } catch (e) {}
        }
     }"
     x-init="syncTemplateLayout()"
     class="space-y-6 pb-20 animate-fade-in-up font-sans antialiased text-gray-700 dark:text-gray-300">

    @if($viewMode === 'list')
        {{-- HEADER BEREICH (Identische Logik & Optik wie /admin/products) --}}
        <div class="flex flex-col xl:flex-row justify-between items-start xl:items-end mb-8 gap-6 animate-fade-in-up">
            <div>
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white mb-2 tracking-tight">
                    {{ $showArchived ? 'Vorlagen-Archiv' : 'Produktvorlagen' }}
                </h1>
                <div class="flex flex-wrap items-center gap-3 text-gray-600 dark:text-gray-400">
                    <p class="text-xs sm:text-sm font-medium">
                        {{ $showArchived ? 'Archivierte Design-Vorlagen verwalten oder wiederherstellen.' : 'Verwalte deine vorkonfigurierten Design-Vorlagen.' }}
                    </p>
                    <span class="px-3 py-1.5 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 dark:shadow-inner rounded-lg text-[10px] font-black uppercase tracking-widest text-gray-500">
                        {{ count($templates ?? []) }} Vorlagen
                    </span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 sm:gap-3.5 w-full xl:w-auto">
                {{-- ANSICHTSAUSWAHL (Groß / Mittel / Liste) --}}
                <div class="inline-flex items-center p-1.5 bg-white dark:bg-gray-900/90 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] dark:shadow-inner self-start sm:self-auto" role="group" aria-label="Ansichtsauswahl">
                    {{-- Groß (Standard) --}}
                    <button type="button"
                            wire:click="setLayoutMode('large')"
                            onclick="try { localStorage.setItem('admin_product_templates_layout_mode', 'large'); } catch (e) {}"
                            class="px-3.5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 {{ $currentLayout === 'large' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
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
                            onclick="try { localStorage.setItem('admin_product_templates_layout_mode', 'medium'); } catch (e) {}"
                            class="px-3.5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 {{ $currentLayout === 'medium' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
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
                            onclick="try { localStorage.setItem('admin_product_templates_layout_mode', 'list'); } catch (e) {}"
                            class="px-3.5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 {{ $currentLayout === 'list' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                            title="Listenansicht">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <span>Liste</span>
                    </button>
                </div>

                {{-- PRODUKT FILTER --}}
                <select wire:model.live="filterProductId"
                        class="px-4 py-3.5 bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] text-xs font-bold text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none cursor-pointer">
                    <option value="">Alle Produkte</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}">{{ $prod->name }}</option>
                    @endforeach
                </select>

                {{-- ANLASS FILTER --}}
                <select wire:model.live="filterHoliday"
                        class="px-4 py-3.5 bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] text-xs font-bold text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none cursor-pointer">
                    <option value="">Alle Anlässe</option>
                    <option value="Weihnachten">Weihnachten</option>
                    <option value="Ostern">Ostern</option>
                    <option value="Valentinstag">Valentinstag</option>
                    <option value="Halloween">Halloween</option>
                    <option value="Muttertag">Muttertag</option>
                    <option value="Vatertag">Vatertag</option>
                    <option value="Hochzeit">Hochzeit</option>
                    <option value="Geburtstag">Geburtstag</option>
                </select>

                {{-- SUCHE --}}
                <div class="relative w-full sm:w-64 group">
                    <input type="text" wire:model.live="search" placeholder="Vorlage suchen..."
                           class="w-full pl-11 pr-4 py-3.5 bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:bg-white dark:focus:bg-gray-950 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all dark:shadow-inner outline-none">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-400 dark:text-gray-600 group-focus-within:text-[var(--theme-color)] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>

                {{-- NEU / ARCHIV BUTTONS --}}
                @if(!$showArchived)
                    <button wire:click="toggleArchiveMode"
                            class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-400 px-5 py-3.5 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest dark:shadow-inner hover:bg-gray-50 dark:hover:bg-gray-900 hover:text-gray-900 dark:hover:text-white transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                        <span>Archiv öffnen</span>
                        @if(($archivedCount ?? 0) > 0)
                            <span class="px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-[9px]">{{ $archivedCount }}</span>
                        @endif
                    </button>
                    <button wire:click="createNew"
                            class="bg-[var(--theme-color)] border border-[var(--theme-color-50)] text-white dark:text-gray-900 px-6 py-3.5 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest dark:shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:bg-black/90 dark:hover:bg-white hover:scale-[1.02] transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                        <span class="text-sm leading-none">+</span> Neu
                    </button>
                @else
                    <button wire:click="toggleArchiveMode"
                            class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-400 px-6 py-3.5 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest dark:shadow-inner hover:bg-gray-50 dark:hover:bg-gray-900 hover:text-gray-900 dark:hover:text-white transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                        Zurück zu Vorlagen
                    </button>
                @endif
            </div>
        </div>

        {{-- EMPTY STATE --}}
        @if(blank($templates))
            <div class="bg-white dark:bg-gray-900/50 backdrop-blur-md p-16 rounded-[3rem] border-2 border-dashed border-gray-200 dark:border-gray-800 text-center dark:shadow-inner animate-fade-in-up">
                <div class="w-20 h-20 rounded-[1.5rem] bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center mx-auto mb-5 dark:shadow-inner">
                    <x-heroicon-o-document-duplicate class="w-10 h-10 text-gray-400 dark:text-gray-600" />
                </div>
                <h3 class="text-xl font-serif font-bold text-gray-900 dark:text-white mb-2 tracking-wide">
                    {{ $showArchived ? 'Das Archiv ist leer' : 'Keine Vorlagen gefunden' }}
                </h3>
                <p class="text-xs text-gray-600 dark:text-gray-500 mb-6">
                    {{ $showArchived ? 'Es befinden sich aktuell keine gelöschten Vorlagen im Archiv.' : 'Erstelle eine neue Design-Vorlage oder passe deine Filter an.' }}
                </p>
                @if(!$showArchived)
                    <button wire:click="createNew" class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] hover:text-gray-900 dark:hover:text-white transition-colors border-b border-[var(--theme-color-30)] hover:border-gray-900 dark:hover:border-white pb-0.5">
                        Neue Vorlage erstellen
                    </button>
                @endif
            </div>
        @else
            @if($currentLayout === 'list')
                {{-- ===================================================================== --}}
                {{-- 1. LISTENANSICHT (KOMPAKTE ZEILEN)                                    --}}
                {{-- ===================================================================== --}}
                <div wire:key="templates-layout-list" class="flex flex-col gap-3 animate-fade-in-up" style="animation-delay: 100ms;">
                    @foreach($templates as $template)
                        <div wire:key="template-list-row-{{ $template->id }}"
                             class="bg-white dark:bg-gray-900/80 backdrop-blur-md p-4 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-xl hover:border-gray-300 dark:hover:border-gray-700 transition-all duration-300 flex flex-col lg:flex-row lg:items-center justify-between gap-4 relative overflow-hidden group">

                            {{-- Status-Balken Links --}}
                            <div class="absolute top-0 left-0 w-1.5 h-full bg-gray-100 dark:bg-gray-950 z-20">
                                <div class="w-full h-full transition-all duration-500 {{ $template->is_active ? 'bg-[var(--theme-color)]' : 'bg-gray-400 dark:bg-gray-700' }}"></div>
                            </div>

                            {{-- Links: Vorschaubild + Name + Produkt + Anlass --}}
                            <div class="flex items-center gap-4 min-w-0 pl-2 flex-1">
                                <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 overflow-hidden shrink-0 relative">
                                    @if($template->preview_image)
                                        <img src="{{ $template->preview_image_url }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    @else
                                        <div class="flex items-center justify-center h-full text-gray-400 dark:text-gray-700">
                                            <x-heroicon-o-photo class="w-6 h-6 opacity-40" />
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2.5 flex-wrap mb-1">
                                        <h3 class="font-serif text-base sm:text-lg font-bold text-gray-900 dark:text-white truncate">{{ $template->name }}</h3>
                                        @if(!empty($template->holiday))
                                            <span class="px-2.5 py-0.5 rounded-lg bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-20)] text-[9px] font-black uppercase tracking-widest">
                                                {{ $template->holiday }}
                                            </span>
                                        @endif
                                        @if(!empty($template->configuration['variant_name']))
                                            <span class="px-2.5 py-0.5 rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/25 text-[9px] font-black uppercase tracking-widest">
                                                Variante: {{ $template->configuration['variant_name'] }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="font-bold text-gray-700 dark:text-gray-300">{{ $template->product->name ?? 'Unbekanntes Produkt' }}</span>
                                        @if($template->preview_image)
                                            <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500 truncate max-w-xs">{{ $template->preview_image }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Rechts: Status + Aktionen --}}
                            <div class="flex flex-wrap items-center justify-between lg:justify-end gap-3 sm:gap-5 pl-2 lg:pl-0 border-t lg:border-t-0 pt-3 lg:pt-0 border-gray-100 dark:border-gray-800/80">
                                @if(!$showArchived)
                                    <button type="button"
                                            wire:click="toggleAiGenerated('{{ $template->id }}')"
                                            class="px-2.5 py-1.5 rounded-xl border text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5 transition-all {{ $template->is_ai_generated ? 'bg-gray-900 text-white dark:bg-white/10 dark:text-white border-gray-800 dark:border-white/20' : 'bg-gray-50 dark:bg-gray-950 text-gray-400 border-gray-200 dark:border-gray-800 hover:text-gray-700' }}"
                                            title="KI-Generiert Hinweis an/aus">
                                        <span class="w-3.5 h-3.5 rounded flex items-center justify-center border {{ $template->is_ai_generated ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-gray-400' }}">
                                            @if($template->is_ai_generated)
                                                <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            @endif
                                        </span>
                                        <span>KI-generiert</span>
                                    </button>

                                    <div class="relative" x-data="{ open: false }">
                                        <button @click="open = !open" @click.away="open = false" class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest rounded-xl border flex items-center gap-1.5 transition-all {{ $template->is_active ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-500/30' : 'bg-white dark:bg-gray-950 text-gray-700 dark:text-gray-400 border-gray-200 dark:border-gray-700' }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $template->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                            {{ $template->is_active ? 'Aktiv' : 'Inaktiv' }}
                                            <svg class="w-3 h-3 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>

                                        <div x-show="open" class="absolute right-0 mt-2 w-32 bg-white dark:bg-gray-950 rounded-xl shadow-xl border border-gray-200 dark:border-gray-800 overflow-hidden z-30" style="display: none;" x-transition>
                                            <button wire:click="updateStatus('{{ $template->id }}', 'active'); open = false" class="w-full text-left px-4 py-2.5 text-[9px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-900 text-emerald-700 dark:text-emerald-400 border-b border-gray-200 dark:border-gray-800 flex items-center gap-2">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktiv
                                            </button>
                                            <button wire:click="updateStatus('{{ $template->id }}', 'inactive'); open = false" class="w-full text-left px-4 py-2.5 text-[9px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-900 text-gray-600 dark:text-gray-500 flex items-center gap-2">
                                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inaktiv
                                            </button>
                                        </div>
                                    </div>
                                @endif

                                {{-- Aktionen --}}
                                <div class="flex items-center gap-4 text-[10px] font-black uppercase tracking-widest">
                                    @if($showArchived)
                                        <button wire:click="restoreTemplate('{{ $template->id }}')" class="text-emerald-600 dark:text-emerald-500 hover:text-emerald-700 transition-colors border-b border-emerald-600 pb-0.5">
                                            Wiederherstellen
                                        </button>
                                        <button wire:click="forceDeleteTemplate('{{ $template->id }}')" wire:confirm="Vorlage endgültig und unwiderruflich löschen?" class="text-red-500 hover:text-red-700 transition-colors border-b border-red-400 pb-0.5">
                                            Endgültig löschen
                                        </button>
                                    @else
                                        <button wire:click="archiveTemplate('{{ $template->id }}')" onclick="confirm('Vorlage wirklich ins Archiv verschieben?') || event.stopImmediatePropagation()" class="text-gray-500 hover:text-red-600 dark:hover:text-red-400 transition-colors border-b border-gray-300 dark:border-gray-600 hover:border-red-600 pb-0.5">
                                            Löschen
                                        </button>
                                        <button wire:click="edit('{{ $template->id }}')" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors border-b border-gray-300 dark:border-gray-600 hover:border-gray-900 dark:hover:border-white pb-0.5">
                                            Bearbeiten &rarr;
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                {{-- ===================================================================== --}}
                {{-- 2. GROSSE & MITTLERE KACHELANSICHT (GRID)                             --}}
                {{-- ===================================================================== --}}
                <div wire:key="templates-layout-{{ $currentLayout }}"
                     class="grid {{ $currentLayout === 'medium' ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5' : 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 sm:gap-8' }} animate-fade-in-up"
                     style="animation-delay: 100ms;">
                    @foreach($templates as $template)
                        <div wire:key="template-card-{{ $currentLayout }}-{{ $template->id }}"
                             class="bg-white dark:bg-gray-900/80 backdrop-blur-md {{ $currentLayout === 'medium' ? 'p-4 rounded-3xl' : 'p-5 sm:p-6 rounded-[2.5rem]' }} border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl hover:border-gray-300 dark:hover:border-gray-700 dark:hover:shadow-[0_0_40px_rgba(0,0,0,0.6)] transition-all duration-300 flex flex-col h-full relative overflow-hidden group">

                            {{-- Status-Balken Oben --}}
                            <div class="absolute top-0 left-0 w-full h-1 bg-gray-100 dark:bg-gray-950 z-30">
                                <div class="h-full w-full transition-all duration-500 {{ $template->is_active ? 'bg-[var(--theme-color)] dark:shadow-[0_0_10px_rgba(197,160,89,0.8)]' : 'bg-gray-400 dark:bg-gray-700' }}"></div>
                            </div>

                            {{-- Status Switcher --}}
                            @if(!$showArchived)
                                <div class="absolute {{ $currentLayout === 'medium' ? 'top-4 right-4' : 'top-6 right-6' }} z-20"
                                     x-data="{ open: false }">
                                    <button @click="open = !open" @click.away="open = false"
                                            class="{{ $currentLayout === 'medium' ? 'px-2.5 py-1.5 text-[8px]' : 'px-3.5 py-2 text-[9px]' }} font-black uppercase tracking-widest rounded-xl border shadow-sm flex items-center gap-2 transition-all backdrop-blur-md {{ $template->is_active ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-500/30' : 'bg-white dark:bg-gray-950/80 text-gray-700 dark:text-gray-400 border-gray-200 dark:border-gray-700' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $template->is_active ? 'bg-emerald-500 dark:shadow-[0_0_8px_currentColor] animate-pulse' : 'bg-gray-400 dark:bg-gray-500' }}"></span>
                                        {{ $template->is_active ? 'Aktiv' : 'Inaktiv' }}
                                        <svg class="w-3.5 h-3.5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>

                                    <div x-show="open" class="absolute right-0 mt-2 w-36 bg-white dark:bg-gray-950 rounded-2xl shadow-xl dark:shadow-[0_0_30px_rgba(0,0,0,0.8)] border border-gray-200 dark:border-gray-800 overflow-hidden z-30" style="display: none;" x-transition>
                                        <button wire:click="updateStatus('{{ $template->id }}', 'active'); open = false" class="w-full text-left px-5 py-3.5 text-[10px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-900 text-emerald-700 dark:text-emerald-400 border-b border-gray-200 dark:border-gray-800 transition-colors flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktiv
                                        </button>
                                        <button wire:click="updateStatus('{{ $template->id }}', 'inactive'); open = false" class="w-full text-left px-5 py-3.5 text-[10px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-900 text-gray-600 dark:text-gray-500 transition-colors flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400 dark:bg-gray-500"></span> Inaktiv
                                        </button>
                                    </div>
                                </div>
                            @endif

                            {{-- Vorschaubild --}}
                            <div class="{{ $currentLayout === 'medium' ? 'h-48 rounded-2xl mb-4 mt-1.5' : 'aspect-square rounded-[1.5rem] mb-6 mt-2' }} bg-gray-100 dark:bg-gray-950 overflow-hidden relative border border-gray-200 dark:border-gray-800 dark:shadow-inner group-hover:border-gray-300 dark:group-hover:border-gray-700 transition-colors">
                                @if(!empty($template->holiday))
                                    <div class="absolute top-3 left-3 z-10 pointer-events-none">
                                        <span class="px-2.5 py-1 rounded-full bg-gray-900/80 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-widest border border-white/15 shadow-sm">
                                            {{ $template->holiday }}
                                        </span>
                                    </div>
                                @endif

                                @if($template->preview_image)
                                    <img src="{{ $template->preview_image_url }}"
                                         @if($template->is_ai_generated) data-is-ai-generated="true" @endif
                                         class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 opacity-90 group-hover:opacity-100">
                                    <button type="button"
                                            wire:click="toggleAiGenerated('{{ $template->id }}')"
                                            class="absolute bottom-2.5 right-2.5 z-20 px-2 py-1 rounded-md backdrop-blur-sm text-[9px] font-semibold tracking-wider flex items-center gap-1.5 border transition-all {{ $template->is_ai_generated ? 'bg-black/65 text-white/95 border-white/20 shadow-sm' : 'bg-black/35 text-white/55 border-white/10 hover:bg-black/60 hover:text-white' }}"
                                            title="Klicken, um 'KI-generiert' Kennzeichnung für dieses Bild ein-/auszuschalten">
                                        <span class="w-3 h-3 rounded-sm flex items-center justify-center border {{ $template->is_ai_generated ? 'bg-emerald-500 border-emerald-400 text-white' : 'border-white/40' }}">
                                            @if($template->is_ai_generated)
                                                <svg class="w-2 h-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            @endif
                                        </span>
                                        <span>KI-generiert</span>
                                    </button>
                                @else
                                    <div class="flex items-center justify-center h-full text-gray-400 dark:text-gray-700 text-[10px] flex-col gap-3 font-black uppercase tracking-widest">
                                        <x-heroicon-o-photo class="w-10 h-10 opacity-30" />
                                        <span>Kein Bild</span>
                                    </div>
                                @endif
                            </div>

                            <h3 class="font-serif {{ $currentLayout === 'medium' ? 'text-base mb-1' : 'text-lg md:text-xl mb-1.5' }} font-bold text-gray-900 dark:text-white truncate tracking-wide">{{ $template->name }}</h3>

                            <div class="flex items-center justify-between gap-2 mb-4 flex-wrap">
                                <span class="text-xs font-bold text-[var(--theme-color)] truncate">{{ $template->product->name ?? 'Unbekanntes Produkt' }}</span>
                                @if(!empty($template->configuration['variant_name']))
                                    <span class="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/25 text-[9px] font-black uppercase tracking-widest truncate max-w-[160px]" title="Zugewiesene Variante: {{ $template->configuration['variant_name'] }}">
                                        {{ $template->configuration['variant_name'] }}
                                    </span>
                                @endif
                            </div>

                            @if($template->preview_image)
                                <div class="text-[9px] text-gray-500 font-mono truncate mb-4 border-t border-gray-100 dark:border-gray-800/50 pt-3" title="{{ str_starts_with($template->preview_image, 'shop/') || str_starts_with($template->preview_image, 'shopverwaltung/images/') ? 'public/' : 'storage/app/public/' }}{{$template->preview_image}}">
                                    <span class="text-[var(--theme-color)] font-bold">Pfad:</span>
                                    {{ str_starts_with($template->preview_image, 'shop/') || str_starts_with($template->preview_image, 'shopverwaltung/images/') ? 'public/' : 'storage/app/public/' }}{{$template->preview_image}}
                                </div>
                            @endif

                            {{-- Footer Aktionen (Identisch zu /admin/products) --}}
                            <div class="mt-auto pt-4 border-t border-gray-200 dark:border-gray-800/80 flex justify-between items-center text-[10px] font-black uppercase tracking-widest">
                                <span class="text-gray-400 dark:text-gray-500">
                                    {{ $template->holiday ?: 'Standard' }}
                                </span>

                                <div class="flex gap-4">
                                    @if($showArchived)
                                        <button wire:click="restoreTemplate('{{ $template->id }}')" class="text-emerald-600 dark:text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-400 transition-colors border-b border-emerald-600 dark:border-emerald-500 pb-0.5">
                                            Wiederherstellen
                                        </button>
                                        <button wire:click="forceDeleteTemplate('{{ $template->id }}')" wire:confirm="Vorlage endgültig löschen?" class="text-red-500 hover:text-red-700 transition-colors border-b border-red-400 pb-0.5">
                                            Endgültig löschen
                                        </button>
                                    @else
                                        <button wire:click="archiveTemplate('{{ $template->id }}')" onclick="confirm('Vorlage wirklich ins Archiv verschieben?') || event.stopImmediatePropagation()" class="text-gray-500 hover:text-red-600 dark:hover:text-red-400 transition-colors border-b border-gray-300 dark:border-gray-600 hover:border-red-600 pb-0.5">
                                            Löschen
                                        </button>
                                        <button wire:click="edit('{{ $template->id }}')" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors border-b border-gray-300 dark:border-gray-600 hover:border-gray-900 dark:hover:border-white pb-0.5">
                                            Bearbeiten &rarr;
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

    @elseif($viewMode === 'create_select_product')
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[3rem] border border-gray-200 dark:border-gray-800 p-8 sm:p-16 shadow-sm dark:shadow-2xl text-center max-w-5xl mx-auto">
            <div class="flex justify-end mb-4">
                <button wire:click="cancel" class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white px-5 py-2.5 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all border border-gray-200 dark:border-gray-700">
                    &larr; Zurück
                </button>
            </div>
            <h2 class="text-3xl font-serif font-bold text-gray-900 dark:text-white mb-3">Produkt auswählen</h2>
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-12">Wählen Sie das Basis-Produkt, für welches Sie eine neue Vorlage designen möchten.</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-6">
                @foreach($products as $prod)
                    <button wire:click="selectProduct('{{$prod->id}}')" class="p-5 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-3xl hover:border-[var(--theme-color)] dark:hover:border-[var(--theme-color-50)] hover:bg-white dark:hover:bg-gray-900 dark:hover:shadow-[0_0_30px_var(--theme-color-15)] transition-all duration-300 flex flex-col items-center gap-4 text-center group">
                        <div class="w-full aspect-square bg-gray-100 dark:bg-gray-900 rounded-2xl flex items-center justify-center border border-gray-200 dark:border-gray-800 shadow-inner overflow-hidden relative">
                            @if($prod->preview_image_path)
                                <img src="{{asset('storage/'.$prod->preview_image_path)}}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                            @else
                                <x-heroicon-o-cube class="w-10 h-10 text-gray-400 dark:text-gray-600 group-hover:text-[var(--theme-color)] transition-colors" />
                            @endif
                            <div class="absolute inset-0 bg-[var(--theme-color)]/0 group-hover:bg-[var(--theme-color-10)] transition-colors"></div>
                        </div>
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white transition-colors line-clamp-2 leading-snug">{{$prod->name}}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @elseif($viewMode === 'configure')
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[3rem] border border-gray-200 dark:border-gray-800 p-8 shadow-sm dark:shadow-2xl flex flex-col min-h-screen">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-serif font-bold text-gray-900 dark:text-white">Vorlage konfigurieren</h2>
                <button wire:click="cancel" class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white px-5 py-2.5 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all border border-gray-200 dark:border-gray-700">
                    &larr; Zurück zur Übersicht
                </button>
            </div>

            <div class="flex flex-col xl:flex-row items-start xl:items-center gap-6 mb-8 border-b border-gray-200 dark:border-gray-800 pb-6 shrink-0">
                <div class="flex-1 w-full">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Name der Vorlage <span class="text-[var(--theme-color)]">*</span></label>
                    <input wire:model="templateName" type="text" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl px-5 py-4 text-sm font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] outline-none shadow-inner transition-all placeholder:text-gray-400 dark:placeholder:text-gray-600" placeholder="z.B. Premium Hochzeit Layout">
                    @error('templateName') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold mt-2 block ml-1 uppercase tracking-widest">{{$message}}</span> @enderror
                </div>

                <div class="w-full xl:w-56 shrink-0">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Anlass (Feiertag)</label>
                    <select wire:model="templateHoliday" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl px-5 py-4 text-sm font-bold text-gray-800 dark:text-gray-300 focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] outline-none shadow-inner transition-all appearance-none cursor-pointer">
                        <option value="" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Kein spezifischer Anlass</option>
                        <option value="Weihnachten" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Weihnachten</option>
                        <option value="Ostern" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Ostern</option>
                        <option value="Valentinstag" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Valentinstag</option>
                        <option value="Halloween" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Halloween</option>
                        <option value="Muttertag" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Muttertag</option>
                        <option value="Vatertag" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Vatertag</option>
                        <option value="Geburtstag" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Geburtstag</option>
                        <option value="Hochzeit" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Hochzeit</option>
                        <option value="Taufe" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Taufe</option>
                        <option value="Einschulung" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Einschulung</option>
                        <option value="Jubilaeum" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Jubiläum</option>
                        <option value="Trauer" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Trauer</option>
                    </select>
                </div>

                <div class="w-full xl:w-96 shrink-0">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Eigenes Vorschaubild (Optional)</label>

                    @if($existingTemplateImagePath)
                        <div class="bg-gray-50 dark:bg-gray-950 p-3 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-inner flex flex-col gap-3">
                            <div class="flex items-start gap-3">
                                <x-heroicon-m-check-badge class="w-5 h-5 text-[var(--theme-color)] shrink-0 mt-0.5" />
                                <div class="text-[9px] font-mono text-gray-600 dark:text-gray-400 break-all leading-relaxed flex-1 space-y-2">
                                    <div>
                                        <span class="font-bold text-gray-700 dark:text-gray-300 uppercase tracking-widest block mb-0.5 font-sans">Bild hinterlegt:</span>
                                        @if(str_starts_with($existingTemplateImagePath, 'http'))
                                            <a href="{{ $existingTemplateImagePath }}" target="_blank" class="text-blue-500 dark:text-blue-400 hover:underline">{{ $existingTemplateImagePath }}</a>
                                        @else
                                            <div class="text-gray-500">Physischer Server-Pfad:</div>
                                            <div class="text-amber-700 dark:text-amber-500/80 bg-amber-500/10 px-1 py-0.5 rounded">
                                                {{ str_starts_with($existingTemplateImagePath, 'shop/') || str_starts_with($existingTemplateImagePath, 'shopverwaltung/images/') ? 'public/' : 'storage/app/public/' }}{{ $existingTemplateImagePath }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" wire:click="removePreviewImage" class="flex-1 bg-white dark:bg-gray-900 hover:bg-[var(--theme-color)] hover:text-gray-900 text-[10px] text-gray-700 dark:text-gray-400 font-black uppercase tracking-widest py-2.5 px-3 rounded-xl border border-gray-200 dark:border-gray-800 hover:border-transparent transition-all shadow-sm dark:shadow-lg active:scale-95">
                                    Vorschau ersetzen
                                </button>
                                <button type="button" wire:click="removePreviewImage" class="w-10 shrink-0 flex flex-col items-center justify-center bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-red-500/50 hover:bg-red-500/10 text-gray-500 hover:text-red-500 dark:hover:text-red-400 rounded-xl transition-all shadow-sm dark:shadow-lg active:scale-95" title="Bild komplett entfernen">
                                    <x-heroicon-o-x-mark class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    @else
                        <input type="file" wire:model="templateImage" accept="image/*" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3 text-xs text-gray-600 dark:text-gray-400 file:mr-4 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-[9px] file:font-black file:uppercase file:tracking-widest file:bg-gray-200 dark:file:bg-gray-800 file:text-gray-800 dark:file:text-white hover:file:bg-gray-300 dark:hover:file:bg-gray-700 cursor-pointer shadow-inner focus:outline-none focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all">
                        @error('templateImage') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold mt-2 block ml-1 uppercase tracking-widest">{{$message}}</span> @enderror
                        <div wire:loading wire:target="templateImage" class="text-[10px] text-[var(--theme-color)] mt-2 ml-1 font-bold animate-pulse uppercase tracking-widest">Bild wird hochgeladen...</div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3 xl:mt-6 shrink-0">
                    <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-950 p-4 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-inner cursor-pointer group" x-on:click="$wire.set('templateIsActive', !@js($templateIsActive))">
                        <input type="checkbox" wire:model.live="templateIsActive" class="w-5 h-5 rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-[var(--theme-color)] focus:ring-[var(--theme-color)] focus:ring-offset-white dark:focus:ring-offset-gray-950 cursor-pointer">
                        <label class="text-[10px] font-black text-gray-700 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white transition-colors uppercase tracking-widest cursor-pointer select-none">Vorlage ist Aktiv</label>
                    </div>

                    <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-950 p-4 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-inner cursor-pointer group" x-on:click="$wire.set('templateIsAiGenerated', !@js($templateIsAiGenerated))">
                        <input type="checkbox" wire:model.live="templateIsAiGenerated" class="w-5 h-5 rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-[var(--theme-color)] focus:ring-[var(--theme-color)] focus:ring-offset-white dark:focus:ring-offset-gray-950 cursor-pointer">
                        <label class="text-[10px] font-black text-gray-700 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white transition-colors uppercase tracking-widest cursor-pointer select-none">Bild ist KI-generiert</label>
                    </div>
                </div>
            </div>

            <div class="flex-1 bg-white rounded-[2rem] relative shadow-inner border border-gray-200 min-h-[900px] overflow-y-auto custom-scrollbar">
                <livewire:shop.product.product-configurator.product-configurator
                    :product="$selectedProductId"
                    :initialData="$templateConfig"
                    context="template_admin"
                    :key="'template-conf-'.$selectedProductId.'-'.($editingTemplateId ?: 'new')"
                />
            </div>
        </div>
    @endif
</div>

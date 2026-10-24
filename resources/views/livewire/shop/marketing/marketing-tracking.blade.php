<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;" class="animate-fade-in-up font-sans antialiased text-gray-700 dark:text-gray-300 min-h-screen">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-8 pb-20">

        {{-- HEADER --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl relative overflow-hidden transition-colors">
            <div class="hidden dark:block absolute inset-0 bg-gradient-to-br from-[var(--theme-color-10)] via-transparent to-transparent pointer-events-none"></div>
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-[var(--theme-color)] via-[var(--theme-color-50)] to-transparent"></div>

            <div class="flex items-center gap-5 relative z-10">
                <div class="w-14 h-14 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)] shadow-sm dark:shadow-[0_0_20px_var(--theme-color-20)] shrink-0">
                    <x-heroicon-o-chart-bar-square class="w-7 h-7" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">
                        Tracking Verwaltung
                    </h1>
                    <p class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mt-1.5">
                        Werbe-Kurzlinks (/t/...), Social-Media-Kanäle & Klick-Statistiken
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 relative z-10">
                <a href="{{ route('admin.marketing-linktree') }}" class="inline-flex items-center gap-2 px-4 py-3 rounded-2xl bg-gray-50 dark:bg-gray-950 hover:bg-[var(--theme-color-10)] text-gray-700 dark:text-gray-300 hover:text-[var(--theme-color)] text-[10px] font-black uppercase tracking-widest transition-all border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-30)] shadow-sm dark:shadow-inner">
                    <x-heroicon-o-link class="w-4 h-4 text-[var(--theme-color)]" />
                    Linktree Verwaltung
                </a>
                <button wire:click="create" class="inline-flex items-center gap-2.5 px-6 py-3 rounded-2xl bg-[var(--theme-color)] border border-[var(--theme-color-50)] hover:opacity-90 hover:scale-[1.02] text-gray-900 dark:text-gray-950 font-black text-[10px] uppercase tracking-widest transition-all shadow-sm dark:shadow-[0_0_20px_var(--theme-color-30)]">
                    <x-heroicon-m-plus class="w-4 h-4 stroke-2" />
                    Neuer Tracking-Link
                </button>
            </div>
        </div>

        {{-- KPIS SECTION --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- KPI 1: Tracking Aufrufe --}}
            <div class="relative bg-white dark:bg-gradient-to-br dark:from-[var(--theme-color-15)] dark:via-gray-900/90 dark:to-gray-900 border border-gray-200 dark:border-[var(--theme-color-30)] rounded-[2rem] p-6 shadow-sm dark:shadow-[0_0_30px_var(--theme-color-10)] flex items-center justify-between overflow-hidden group transition-colors">
                <div class="hidden dark:block absolute -right-4 -bottom-4 w-24 h-24 bg-[var(--theme-color-10)] rounded-full blur-xl pointer-events-none"></div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mb-1">Tracking Aufrufe (Gesamt)</p>
                    <h3 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white">{{ number_format($totalTrackingClicks, 0, ',', '.') }}</h3>
                    <p class="text-[10px] font-mono text-gray-500 dark:text-gray-400 mt-1.5">Erfasste Klicks über /t/...</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)] shadow-sm dark:shadow-inner shrink-0 group-hover:scale-110 transition-transform">
                    <x-heroicon-o-cursor-arrow-rays class="w-7 h-7" />
                </div>
            </div>

            {{-- KPI 2: Unique Besucher --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl flex items-center justify-between relative overflow-hidden group transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Unique Besucher (Gesamt)</p>
                    <h3 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white">{{ number_format($totalTrackingUnique, 0, ',', '.') }}</h3>
                    <p class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 mt-1.5">Eindeutige Personen (DSGVO-Hash)</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center justify-center shadow-sm dark:shadow-inner shrink-0 group-hover:scale-110 transition-transform">
                    <x-heroicon-o-users class="w-7 h-7" />
                </div>
            </div>

            {{-- KPI 3: Aktive Tracking-Links --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl flex items-center justify-between relative overflow-hidden group transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Aktive Tracking-Links</p>
                    <h3 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white">{{ number_format($activeTrackingCount, 0, ',', '.') }}</h3>
                    <p class="text-[10px] font-mono text-purple-600 dark:text-purple-400 mt-1.5">Verknüpft mit Quellenanalyse</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-purple-50 text-purple-600 border border-purple-200 dark:bg-purple-500/10 dark:border-purple-500/20 dark:text-purple-400 flex items-center justify-center shadow-sm dark:shadow-inner shrink-0 group-hover:scale-110 transition-transform">
                    <x-heroicon-o-tag class="w-7 h-7" />
                </div>
            </div>
        </div>

        {{-- MAIN CONTENT GRID (Identisch zu Linktree Verwaltung: 1/3 Links, 2/3 Rechts) --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- LEFT COLUMN: SCHNELL-ERSTELLUNG & TIPP --}}
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl transition-colors">
                    <div class="flex items-center justify-between pb-5 mb-6 border-b border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center shrink-0">
                                <x-heroicon-o-plus-circle class="w-5 h-5" />
                            </div>
                            <div>
                                <h2 class="font-serif font-bold text-gray-900 dark:text-white text-lg tracking-tight">
                                    {{ $editingTrackingId ? 'Link bearbeiten' : 'Tracking-Link anlegen' }}
                                </h2>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mt-0.5">Kurzlink mit 1 Klick erstellen</p>
                            </div>
                        </div>
                        @if($editingTrackingId)
                            <button type="button" wire:click="resetTrackingForm" class="text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-gray-900 dark:hover:text-white">
                                Abbrechen
                            </button>
                        @endif
                    </div>

                    @if (session()->has('tracking_saved'))
                        <div class="mb-5 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border-l-4 border-emerald-500 text-emerald-700 dark:text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center justify-between shadow-sm dark:shadow-inner">
                            <span>{{ session('tracking_saved') }}</span>
                            <x-heroicon-s-check-circle class="w-5 h-5 shrink-0" />
                        </div>
                    @endif

                    {{-- Schnellwahl-Vorlagen --}}
                    <div class="mb-6">
                        <span class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2.5 ml-1">Schnellwahl:</span>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="applyTrackingPreset('whatsapp')" class="px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-gray-950 hover:bg-emerald-100 dark:hover:bg-gray-800 text-[10px] font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-gray-800 transition-colors shadow-xs">
                                WhatsApp
                            </button>
                            <button type="button" wire:click="applyTrackingPreset('instagram')" class="px-3 py-1.5 rounded-xl bg-pink-50 dark:bg-gray-950 hover:bg-pink-100 dark:hover:bg-gray-800 text-[10px] font-black uppercase tracking-wider text-pink-700 dark:text-pink-400 border border-pink-200 dark:border-gray-800 transition-colors shadow-xs">
                                Instagram Bio
                            </button>
                            <button type="button" wire:click="applyTrackingPreset('tiktok')" class="px-3 py-1.5 rounded-xl bg-cyan-50 dark:bg-gray-950 hover:bg-cyan-100 dark:hover:bg-gray-800 text-[10px] font-black uppercase tracking-wider text-cyan-700 dark:text-cyan-400 border border-cyan-200 dark:border-gray-800 transition-colors shadow-xs">
                                TikTok Bio
                            </button>
                            <button type="button" wire:click="applyTrackingPreset('facebook')" class="px-3 py-1.5 rounded-xl bg-blue-50 dark:bg-gray-950 hover:bg-blue-100 dark:hover:bg-gray-800 text-[10px] font-black uppercase tracking-wider text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-gray-800 transition-colors shadow-xs">
                                Facebook
                            </button>
                            <button type="button" wire:click="applyTrackingPreset('pinterest')" class="px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-gray-950 hover:bg-rose-100 dark:hover:bg-gray-800 text-[10px] font-black uppercase tracking-wider text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-gray-800 transition-colors shadow-xs">
                                Pinterest
                            </button>
                            <button type="button" wire:click="applyTrackingPreset('newsletter')" class="px-3 py-1.5 rounded-xl bg-amber-50 dark:bg-gray-950 hover:bg-amber-100 dark:hover:bg-gray-800 text-[10px] font-black uppercase tracking-wider text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-gray-800 transition-colors shadow-xs">
                                Newsletter
                            </button>
                        </div>
                    </div>

                    <form wire:submit.prevent="saveTrackingLink" class="space-y-5">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">Bezeichnung *</label>
                            <input type="text" wire:model.live.debounce.300ms="trackingName" placeholder="z.B. WhatsApp, Instagram Bio..." class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-2xl px-4 py-3 text-gray-900 dark:text-white text-sm font-bold focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner outline-none transition-all">
                            @error('trackingName') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">Kurzlink-Endung (automatisch)</label>
                            <div class="flex items-center bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-2xl overflow-hidden focus-within:bg-white dark:focus-within:bg-gray-900 focus-within:ring-2 focus-within:ring-[var(--theme-color-30)] focus-within:border-[var(--theme-color)] shadow-sm dark:shadow-inner transition-all">
                                <span class="px-3.5 py-3 text-xs font-mono font-bold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-900 border-r border-gray-300 dark:border-gray-800">/t/</span>
                                <input type="text" wire:model="trackingSlug" placeholder="whatsapp" class="w-full bg-transparent px-3.5 py-3 text-gray-900 dark:text-white text-sm font-mono font-bold focus:outline-none">
                            </div>
                            @error('trackingSlug') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">Zielseite im Shop</label>
                            <input type="text" wire:model="trackingTargetUrl" placeholder="/shop" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-2xl px-4 py-3 text-gray-900 dark:text-white text-sm font-mono focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner outline-none transition-all">
                            @error('trackingTargetUrl') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full py-3.5 rounded-2xl bg-[var(--theme-color)] border border-[var(--theme-color-50)] hover:opacity-90 hover:scale-[1.01] text-gray-900 dark:text-gray-950 text-[10px] font-black uppercase tracking-widest transition-all shadow-sm dark:shadow-[0_0_20px_var(--theme-color-30)] flex justify-center items-center gap-2">
                                <x-heroicon-m-check class="w-4 h-4 stroke-2" />
                                {{ $editingTrackingId ? 'Änderungen speichern' : 'Tracking-Link anlegen' }}
                            </button>
                        </div>
                    </form>

                    {{-- Info Box --}}
                    <div class="mt-6 p-4 rounded-2xl bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-[var(--theme-color-20)] text-xs text-gray-600 dark:text-gray-300 leading-relaxed shadow-sm dark:shadow-inner">
                        <span class="text-[var(--theme-color)] font-black uppercase tracking-widest text-[10px] block mb-1.5">
                            Tipp für Werbe- & Social-Media-Links
                        </span>
                        Gib deinem Link einfach einen Namen wie <strong class="text-gray-900 dark:text-white">WhatsApp</strong>. Du erhältst sofort den Kurzlink
                        <code class="px-1.5 py-0.5 rounded bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-[11px] font-mono font-bold text-[var(--theme-color)]">{{ url('/t/whatsapp') }}</code>,
                        den du überall teilen kannst. Jeder Klick fließt automatisch in die <strong class="text-gray-900 dark:text-white">Quellenanalyse</strong> ein.
                    </div>
                </div>
            </div>

            {{-- RIGHT COLUMN: TRACKING-LINKS LISTE --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl transition-colors">
                    <div class="flex items-center justify-between pb-5 mb-6 border-b border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center shrink-0">
                                <x-heroicon-o-bars-3-bottom-left class="w-5 h-5" />
                            </div>
                            <div>
                                <h2 class="font-serif font-bold text-gray-900 dark:text-white text-lg tracking-tight">Aktive Tracking-Links</h2>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mt-0.5">Alle Kurzlinks & Performance-Metriken</p>
                            </div>
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-950 px-3.5 py-1.5 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
                            {{ count($trackingLinks) }} Einträge
                        </span>
                    </div>

                    @if(empty($trackingLinks))
                        <div class="text-center py-16 px-6 border-2 border-dashed border-gray-200 dark:border-gray-800 rounded-[2rem] bg-gray-50/50 dark:bg-gray-950/30">
                            <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-center mx-auto mb-4 shadow-sm dark:shadow-inner">
                                <x-heroicon-o-chart-bar-square class="w-8 h-8 text-gray-400 dark:text-gray-600" />
                            </div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">Noch keine Tracking-Links vorhanden</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-md mx-auto">Wähle links eine Schnellwahl (z.B. WhatsApp) oder erstelle deinen ersten Kurzlink.</p>
                            <button wire:click="create" class="mt-5 px-5 py-2.5 bg-[var(--theme-color)] hover:opacity-90 text-gray-900 dark:text-gray-950 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-sm">
                                Jetzt anlegen
                            </button>
                        </div>
                    @else
                        <div class="space-y-3.5" x-data="{ copiedSlug: null }">
                            @foreach($trackingLinks as $trk)
                                @php
                                    $badgeClass = match($trk['channel']) {
                                        'social' => 'bg-pink-50 text-pink-700 border-pink-200 dark:bg-pink-500/10 dark:text-pink-400 dark:border-pink-500/20',
                                        'campaign' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
                                        'referral' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
                                        'search' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20',
                                        default => 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-500/10 dark:text-gray-400 dark:border-gray-500/20',
                                    };
                                    $channelLabel = match($trk['channel']) {
                                        'social' => 'Social Media',
                                        'campaign' => 'Kampagne & Ads',
                                        'referral' => 'Empfehlung & Partner',
                                        'search' => 'Organische Suche',
                                        default => 'Direkt',
                                    };
                                @endphp
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 sm:p-5 rounded-2xl bg-gray-50/80 dark:bg-gray-950/70 border border-gray-200 dark:border-gray-800/80 hover:bg-white dark:hover:bg-gray-900 hover:border-[var(--theme-color-40)] transition-all shadow-sm dark:shadow-inner group">

                                    <div class="flex items-start sm:items-center gap-3.5 flex-1 min-w-0">
                                        {{-- Icon --}}
                                        <div class="w-12 h-12 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-center shrink-0 shadow-sm dark:shadow-inner {{ $trk['is_active'] ? 'text-[var(--theme-color)]' : 'text-gray-400 dark:text-gray-600' }}">
                                            <x-heroicon-o-link class="w-5 h-5" />
                                        </div>

                                        {{-- Info --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm sm:text-base font-bold {{ $trk['is_active'] ? 'text-gray-900 dark:text-white group-hover:text-[var(--theme-color)]' : 'text-gray-400 dark:text-gray-500 line-through' }} truncate transition-colors">
                                                    {{ $trk['name'] }}
                                                </h3>
                                                <span class="px-2.5 py-0.5 text-[9px] uppercase tracking-widest font-black rounded-lg border {{ $badgeClass }}">
                                                    {{ $channelLabel }}
                                                </span>
                                                @if(!$trk['is_active'])
                                                    <span class="px-2.5 py-0.5 text-[9px] uppercase tracking-widest font-black rounded-lg bg-red-50 text-red-600 border border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20">
                                                        Inaktiv
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                                <code class="px-2.5 py-1 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-xs font-mono font-bold text-[var(--theme-color)] shadow-xs">
                                                    {{ $trk['short_url'] }}
                                                </code>
                                                <button type="button"
                                                        @click="navigator.clipboard.writeText('{{ $trk['short_url'] }}'); copiedSlug = '{{ $trk['slug'] }}'; setTimeout(() => copiedSlug = null, 2000)"
                                                        class="px-2.5 py-1 rounded-xl bg-white dark:bg-gray-900 hover:bg-[var(--theme-color-10)] text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-gray-300 hover:text-[var(--theme-color)] border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-30)] transition-colors shadow-xs">
                                                    <span x-show="copiedSlug !== '{{ $trk['slug'] }}'">Kopieren</span>
                                                    <span x-show="copiedSlug === '{{ $trk['slug'] }}'" class="text-emerald-600 dark:text-emerald-400" x-cloak>Kopiert ✓</span>
                                                </button>
                                                <span class="text-[11px] text-gray-500 dark:text-gray-400 font-mono truncate">
                                                    Ziel: {{ $trk['target_url'] }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Stats & Actions --}}
                                    <div class="flex items-center justify-between sm:justify-end gap-4 pt-3 sm:pt-0 border-t sm:border-t-0 border-gray-200/80 dark:border-gray-800/80">
                                        <div class="flex flex-col items-start sm:items-end px-4 sm:border-r border-gray-200 dark:border-gray-800">
                                            <span class="text-sm font-black font-mono text-gray-900 dark:text-white">{{ number_format($trk['clicks_count'], 0, ',', '.') }} Aufrufe</span>
                                            <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">
                                                {{ number_format($trk['unique_clicks_count'], 0, ',', '.') }} Unique
                                                @if($trk['last_clicked_at'])
                                                    &bull; {{ $trk['last_clicked_at'] }}
                                                @endif
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button wire:click="toggleTrackingActive('{{ $trk['id'] }}')" class="p-2.5 rounded-xl border {{ $trk['is_active'] ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-500/20' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white' }} shadow-sm dark:shadow-inner transition-all" title="{{ $trk['is_active'] ? 'Deaktivieren' : 'Aktivieren' }}">
                                                <x-heroicon-o-power class="w-4 h-4" />
                                            </button>
                                            <button wire:click="editTrackingLink('{{ $trk['id'] }}')" class="p-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] hover:text-[var(--theme-color)] shadow-sm dark:shadow-inner transition-all" title="Bearbeiten">
                                                <x-heroicon-o-pencil-square class="w-4 h-4" />
                                            </button>
                                            <button wire:click="deleteTrackingLink('{{ $trk['id'] }}')" wire:confirm="Diesen Tracking-Link wirklich löschen?" class="p-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-400 dark:text-gray-500 hover:bg-red-50 dark:hover:bg-red-500/10 hover:border-red-200 dark:hover:border-red-500/30 hover:text-red-600 dark:hover:text-red-400 shadow-sm dark:shadow-inner transition-all" title="Löschen">
                                                <x-heroicon-o-trash class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- EDIT / CREATE MODAL --}}
        @if($showModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/60 dark:bg-black/80 backdrop-blur-md" wire:click="$set('showModal', false)"></div>

                <div class="relative w-full max-w-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2.5rem] shadow-2xl overflow-hidden z-10 animate-fade-in-up transition-colors">
                    <div class="px-6 sm:px-8 py-5 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between bg-gray-50 dark:bg-gray-950/60">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center">
                                <x-heroicon-o-chart-bar-square class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="font-serif font-bold text-gray-900 dark:text-white text-lg">
                                    {{ $editingTrackingId ? 'Tracking-Link bearbeiten' : 'Neuen Tracking-Link erstellen' }}
                                </h3>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-500">Kurzlink für Werbung & Social Media</p>
                            </div>
                        </div>
                        <button wire:click="$set('showModal', false)" class="w-9 h-9 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-gray-900 dark:hover:text-white flex items-center justify-center transition-all shadow-sm">
                            <x-heroicon-o-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <form wire:submit.prevent="saveTrackingLink" class="p-6 sm:p-8 space-y-5">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">Bezeichnung *</label>
                            <input type="text" wire:model.live.debounce.300ms="trackingName" placeholder="z.B. WhatsApp" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-2xl px-4 py-3.5 text-gray-900 dark:text-white text-sm font-bold focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner outline-none transition-all">
                            @error('trackingName') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">Kurzlink-Endung</label>
                            <div class="flex items-center bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-2xl overflow-hidden focus-within:bg-white dark:focus-within:bg-gray-900 focus-within:ring-2 focus-within:ring-[var(--theme-color-30)] focus-within:border-[var(--theme-color)] shadow-sm dark:shadow-inner transition-all">
                                <span class="px-3.5 py-3.5 text-xs font-mono font-bold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-900 border-r border-gray-300 dark:border-gray-800">/t/</span>
                                <input type="text" wire:model="trackingSlug" placeholder="whatsapp" class="w-full bg-transparent px-3.5 py-3.5 text-gray-900 dark:text-white text-sm font-mono font-bold focus:outline-none">
                            </div>
                            @error('trackingSlug') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">Zielseite im Shop</label>
                            <input type="text" wire:model="trackingTargetUrl" placeholder="/shop" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-2xl px-4 py-3.5 text-gray-900 dark:text-white text-sm font-mono focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner outline-none transition-all">
                            @error('trackingTargetUrl') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="pt-5 border-t border-gray-200 dark:border-gray-800 flex items-center justify-end gap-3">
                            <button type="button" wire:click="$set('showModal', false)" class="px-5 py-3 rounded-2xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-[10px] font-black uppercase tracking-widest transition-colors">
                                Abbrechen
                            </button>
                            <button type="submit" class="px-6 py-3 rounded-2xl bg-[var(--theme-color)] border border-[var(--theme-color-50)] hover:opacity-90 text-gray-900 dark:text-gray-950 font-black text-[10px] uppercase tracking-widest transition-all shadow-sm dark:shadow-[0_0_20px_var(--theme-color-30)]">
                                Speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

    </div>
</div>

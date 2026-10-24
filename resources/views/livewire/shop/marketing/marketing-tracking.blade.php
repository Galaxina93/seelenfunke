<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-7xl mx-auto space-y-8">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-900/60 backdrop-blur-xl p-6 rounded-3xl border border-gray-800 shadow-2xl relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color-10)] via-transparent to-transparent pointer-events-none"></div>
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-[var(--theme-color)] via-purple-500 to-indigo-500"></div>
        
        <div class="flex items-center gap-4 relative z-10">
            <div class="w-12 h-12 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] flex items-center justify-center text-[var(--theme-color)] shadow-lg shadow-[var(--theme-color-10)]">
                <x-heroicon-o-chart-bar-square class="w-6 h-6" />
            </div>
            <div>
                <h1 class="text-2xl font-serif font-bold text-white tracking-wide">Tracking Verwaltung</h1>
                <p class="text-sm text-gray-400">Werbe-Kurzlinks (/t/...), Social-Media-Kanäle & Klick-Statistiken</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 relative z-10">
            <a href="{{ route('admin.marketing-linktree') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-medium transition-all border border-gray-700 hover:border-gray-600 shadow-sm">
                <x-heroicon-o-link class="w-4 h-4 text-[var(--theme-color)]" />
                Linktree Verwaltung
            </a>
            <button wire:click="create" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[var(--theme-color)] hover:opacity-90 text-gray-950 font-bold text-sm transition-all shadow-lg shadow-[var(--theme-color-20)]">
                <x-heroicon-o-plus class="w-5 h-5 stroke-2" />
                Neuer Tracking-Link
            </button>
        </div>
    </div>

    <!-- KPIs Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gray-900/60 backdrop-blur-md p-6 rounded-3xl border border-gray-800 flex items-center gap-5 relative overflow-hidden group">
            <div class="w-14 h-14 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] flex items-center justify-center text-[var(--theme-color)] shrink-0 group-hover:scale-110 transition-transform">
                <x-heroicon-o-cursor-arrow-rays class="w-7 h-7" />
            </div>
            <div>
                <p class="text-sm font-medium text-gray-400">Tracking Aufrufe (Gesamt)</p>
                <p class="text-3xl font-bold text-white mt-1">{{ number_format($totalTrackingClicks, 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="bg-gray-900/60 backdrop-blur-md p-6 rounded-3xl border border-gray-800 flex items-center gap-5 relative overflow-hidden group">
            <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0 group-hover:scale-110 transition-transform">
                <x-heroicon-o-users class="w-7 h-7" />
            </div>
            <div>
                <p class="text-sm font-medium text-gray-400">Unique Besucher (Gesamt)</p>
                <p class="text-3xl font-bold text-white mt-1">{{ number_format($totalTrackingUnique, 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="bg-gray-900/60 backdrop-blur-md p-6 rounded-3xl border border-gray-800 flex items-center gap-5 relative overflow-hidden group">
            <div class="w-14 h-14 rounded-2xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 shrink-0 group-hover:scale-110 transition-transform">
                <x-heroicon-o-tag class="w-7 h-7" />
            </div>
            <div>
                <p class="text-sm font-medium text-gray-400">Aktive Tracking-Links</p>
                <p class="text-3xl font-bold text-white mt-1">{{ number_format($activeTrackingCount, 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    <!-- Main Content Grid (Identisch zu Linktree Verwaltung: 1/3 Links, 2/3 Rechts) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Column: Schnell-Erstellung & Tipp -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-gray-900/60 backdrop-blur-md p-6 rounded-3xl border border-gray-800 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <x-heroicon-o-plus-circle class="w-5 h-5 text-[var(--theme-color)]" />
                        {{ $editingTrackingId ? 'Link bearbeiten' : 'Tracking-Link anlegen' }}
                    </h2>
                    @if($editingTrackingId)
                        <button type="button" wire:click="resetTrackingForm" class="text-[11px] font-bold text-gray-400 hover:text-white">
                            Abbrechen
                        </button>
                    @endif
                </div>

                @if (session()->has('tracking_saved'))
                    <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium flex items-center gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 shrink-0" />
                        {{ session('tracking_saved') }}
                    </div>
                @endif

                <!-- Schnellwahl-Vorlagen -->
                <div class="mb-5">
                    <span class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Schnellwahl:</span>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" wire:click="applyTrackingPreset('whatsapp')" class="px-2.5 py-1 rounded-lg bg-gray-950 hover:bg-gray-800 text-[11px] font-bold text-emerald-400 border border-gray-800 transition-colors">
                            WhatsApp
                        </button>
                        <button type="button" wire:click="applyTrackingPreset('instagram')" class="px-2.5 py-1 rounded-lg bg-gray-950 hover:bg-gray-800 text-[11px] font-bold text-pink-400 border border-gray-800 transition-colors">
                            Instagram Bio
                        </button>
                        <button type="button" wire:click="applyTrackingPreset('tiktok')" class="px-2.5 py-1 rounded-lg bg-gray-950 hover:bg-gray-800 text-[11px] font-bold text-cyan-400 border border-gray-800 transition-colors">
                            TikTok Bio
                        </button>
                        <button type="button" wire:click="applyTrackingPreset('facebook')" class="px-2.5 py-1 rounded-lg bg-gray-950 hover:bg-gray-800 text-[11px] font-bold text-blue-400 border border-gray-800 transition-colors">
                            Facebook
                        </button>
                        <button type="button" wire:click="applyTrackingPreset('pinterest')" class="px-2.5 py-1 rounded-lg bg-gray-950 hover:bg-gray-800 text-[11px] font-bold text-rose-400 border border-gray-800 transition-colors">
                            Pinterest
                        </button>
                        <button type="button" wire:click="applyTrackingPreset('newsletter')" class="px-2.5 py-1 rounded-lg bg-gray-950 hover:bg-gray-800 text-[11px] font-bold text-amber-400 border border-gray-800 transition-colors">
                            Newsletter
                        </button>
                    </div>
                </div>

                <form wire:submit.prevent="saveTrackingLink" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Bezeichnung *</label>
                        <input type="text" wire:model.live.debounce.300ms="trackingName" placeholder="z.B. WhatsApp, Instagram Bio..." class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-[var(--theme-color)] transition-colors">
                        @error('trackingName') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Kurzlink-Endung (automatisch)</label>
                        <div class="flex items-center bg-gray-950 border border-gray-800 rounded-xl overflow-hidden focus-within:border-[var(--theme-color)]">
                            <span class="px-3 py-2.5 text-xs font-mono text-gray-500 bg-gray-900/80 border-r border-gray-800">/t/</span>
                            <input type="text" wire:model="trackingSlug" placeholder="whatsapp" class="w-full bg-transparent px-3 py-2.5 text-white text-sm font-mono focus:outline-none">
                        </div>
                        @error('trackingSlug') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Zielseite im Shop</label>
                        <input type="text" wire:model="trackingTargetUrl" placeholder="/shop" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-2.5 text-white text-sm font-mono focus:outline-none focus:border-[var(--theme-color)] transition-colors">
                        @error('trackingTargetUrl') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-[var(--theme-color)] hover:opacity-90 text-gray-950 text-xs font-bold uppercase tracking-wider transition-all shadow-lg shadow-[var(--theme-color-20)] flex justify-center items-center gap-2">
                            <x-heroicon-o-check class="w-4 h-4 stroke-2" />
                            {{ $editingTrackingId ? 'Änderungen speichern' : 'Tracking-Link anlegen' }}
                        </button>
                    </div>
                </form>

                <!-- Info Box -->
                <div class="mt-6 p-4 rounded-2xl bg-[var(--theme-color-5)] border border-[var(--theme-color-20)] text-xs text-gray-300 leading-relaxed">
                    <span class="text-[var(--theme-color)] font-black uppercase tracking-widest text-[10px] block mb-1">
                        Tipp für Werbe- & Social-Media-Links
                    </span>
                    Gib deinem Link einfach einen Namen wie <strong>WhatsApp</strong>. Du erhältst sofort den Kurzlink
                    <code class="px-1.5 py-0.5 rounded bg-gray-950 border border-gray-800 text-[11px] font-mono text-[var(--theme-color)]">{{ url('/t/whatsapp') }}</code>,
                    den du überall teilen kannst. Jeder Klick fließt automatisch in die <strong>Quellenanalyse</strong> ein.
                </div>
            </div>
        </div>

        <!-- Right Column: Tracking-Links Liste -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-gray-900/60 backdrop-blur-md p-6 rounded-3xl border border-gray-800 shadow-xl">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <x-heroicon-o-bars-3-bottom-left class="w-5 h-5 text-[var(--theme-color)]" />
                        Aktive Tracking-Links
                    </h2>
                    <span class="text-xs text-gray-500 bg-gray-950 px-3 py-1 rounded-full border border-gray-800">{{ count($trackingLinks) }} Einträge</span>
                </div>

                @if(empty($trackingLinks))
                    <div class="text-center py-12 border-2 border-dashed border-gray-800 rounded-2xl">
                        <x-heroicon-o-chart-bar-square class="w-12 h-12 text-gray-600 mx-auto mb-3" />
                        <h3 class="text-gray-300 font-medium">Noch keine Tracking-Links vorhanden</h3>
                        <p class="text-gray-500 text-sm mt-1">Wähle links eine Schnellwahl (z.B. WhatsApp) oder erstelle deinen ersten Kurzlink.</p>
                        <button wire:click="create" class="mt-4 px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-xl text-sm transition-colors border border-gray-700">Jetzt anlegen</button>
                    </div>
                @else
                    <div class="space-y-3" x-data="{ copiedSlug: null }">
                        @foreach($trackingLinks as $trk)
                            @php
                                $badgeClass = match($trk['channel']) {
                                    'social' => 'bg-pink-500/10 text-pink-400 border-pink-500/20',
                                    'campaign' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                    'referral' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                    'search' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                    default => 'bg-gray-500/10 text-gray-400 border-gray-500/20',
                                };
                                $channelLabel = match($trk['channel']) {
                                    'social' => 'Social Media',
                                    'campaign' => 'Kampagne & Ads',
                                    'referral' => 'Empfehlung & Partner',
                                    'search' => 'Organische Suche',
                                    default => 'Direkt',
                                };
                            @endphp
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl bg-gray-950/60 border border-gray-800/80 hover:border-gray-700 transition-all group">
                                
                                <div class="flex items-start sm:items-center gap-3.5 flex-1 min-w-0">
                                    <!-- Icon -->
                                    <div class="w-10 h-10 rounded-xl bg-gray-900 border border-gray-800 flex items-center justify-center shrink-0 {{ $trk['is_active'] ? 'text-[var(--theme-color)]' : 'text-gray-600' }}">
                                        <x-heroicon-o-link class="w-5 h-5" />
                                    </div>

                                    <!-- Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-bold {{ $trk['is_active'] ? 'text-white' : 'text-gray-500 line-through' }} truncate">
                                                {{ $trk['name'] }}
                                            </h3>
                                            <span class="px-2 py-0.5 text-[9px] uppercase tracking-wider font-extrabold rounded border {{ $badgeClass }}">
                                                {{ $channelLabel }}
                                            </span>
                                            @if(!$trk['is_active'])
                                                <span class="px-2 py-0.5 text-[9px] uppercase tracking-wider font-bold rounded bg-red-500/10 text-red-400 border border-red-500/20">Inaktiv</span>
                                            @endif
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                            <code class="px-2 py-0.5 rounded-lg bg-gray-900 border border-gray-800 text-xs font-mono text-[var(--theme-color)]">
                                                {{ $trk['short_url'] }}
                                            </code>
                                            <button type="button"
                                                    @click="navigator.clipboard.writeText('{{ $trk['short_url'] }}'); copiedSlug = '{{ $trk['slug'] }}'; setTimeout(() => copiedSlug = null, 2000)"
                                                    class="px-2 py-0.5 rounded-lg bg-gray-900 hover:bg-gray-800 text-[10px] font-bold text-gray-300 border border-gray-800 transition-colors">
                                                <span x-show="copiedSlug !== '{{ $trk['slug'] }}'">Kopieren</span>
                                                <span x-show="copiedSlug === '{{ $trk['slug'] }}'" class="text-emerald-400" x-cloak>Kopiert ✓</span>
                                            </button>
                                            <span class="text-[11px] text-gray-500 font-mono truncate">
                                                Ziel: {{ $trk['target_url'] }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stats & Actions -->
                                <div class="flex items-center justify-between sm:justify-end gap-4 pt-2 sm:pt-0 border-t sm:border-t-0 border-gray-800/80">
                                    <div class="flex flex-col items-start sm:items-end px-3 sm:border-r border-gray-800">
                                        <span class="text-xs font-bold text-white font-mono">{{ number_format($trk['clicks_count'], 0, ',', '.') }} Aufrufe</span>
                                        <span class="text-[10px] text-gray-500 uppercase tracking-wider">
                                            {{ number_format($trk['unique_clicks_count'], 0, ',', '.') }} Unique
                                            @if($trk['last_clicked_at'])
                                                &bull; {{ $trk['last_clicked_at'] }}
                                            @endif
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button wire:click="toggleTrackingActive('{{ $trk['id'] }}')" class="p-2 rounded-xl border {{ $trk['is_active'] ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400 hover:bg-emerald-500/20' : 'bg-gray-900 border-gray-800 text-gray-500 hover:text-gray-300' }} transition-colors" title="{{ $trk['is_active'] ? 'Deaktivieren' : 'Aktivieren' }}">
                                            <x-heroicon-o-power class="w-4 h-4" />
                                        </button>
                                        <button wire:click="editTrackingLink('{{ $trk['id'] }}')" class="p-2 rounded-xl bg-gray-900 hover:bg-gray-800 text-gray-400 hover:text-white border border-gray-800 transition-colors" title="Bearbeiten">
                                            <x-heroicon-o-pencil-square class="w-4 h-4" />
                                        </button>
                                        <button wire:click="deleteTrackingLink('{{ $trk['id'] }}')" wire:confirm="Diesen Tracking-Link wirklich löschen?" class="p-2 rounded-xl bg-gray-900 hover:bg-red-500/20 text-gray-500 hover:text-red-400 border border-gray-800 hover:border-red-500/30 transition-colors" title="Löschen">
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

    <!-- Edit/Create Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
            
            <div class="relative w-full max-w-lg bg-gray-900 border border-gray-800 rounded-3xl shadow-2xl overflow-hidden z-10 animate-fade-in-up">
                <div class="px-6 py-4 border-b border-gray-800 flex items-center justify-between bg-gray-950/50">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <x-heroicon-o-chart-bar-square class="w-5 h-5 text-[var(--theme-color)]" />
                        {{ $editingTrackingId ? 'Tracking-Link bearbeiten' : 'Neuen Tracking-Link erstellen' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-gray-500 hover:text-white transition-colors">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>

                <form wire:submit.prevent="saveTrackingLink" class="p-6 space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Bezeichnung *</label>
                        <input type="text" wire:model.live.debounce.300ms="trackingName" placeholder="z.B. WhatsApp" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-[var(--theme-color)] transition-colors">
                        @error('trackingName') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Kurzlink-Endung</label>
                        <div class="flex items-center bg-gray-950 border border-gray-800 rounded-xl overflow-hidden focus-within:border-[var(--theme-color)]">
                            <span class="px-3 py-3 text-xs font-mono text-gray-500 bg-gray-900/80 border-r border-gray-800">/t/</span>
                            <input type="text" wire:model="trackingSlug" placeholder="whatsapp" class="w-full bg-transparent px-3 py-3 text-white text-sm font-mono focus:outline-none">
                        </div>
                        @error('trackingSlug') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Zielseite im Shop</label>
                        <input type="text" wire:model="trackingTargetUrl" placeholder="/shop" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3 text-white text-sm font-mono focus:outline-none focus:border-[var(--theme-color)] transition-colors">
                        @error('trackingTargetUrl') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-gray-800 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showModal', false)" class="px-5 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-medium transition-colors">
                            Abbrechen
                        </button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-[var(--theme-color)] hover:opacity-90 text-gray-950 font-bold text-sm transition-all shadow-lg shadow-[var(--theme-color-20)]">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
</div>

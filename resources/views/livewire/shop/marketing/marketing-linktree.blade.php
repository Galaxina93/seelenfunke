<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-7xl mx-auto space-y-8">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-900/60 backdrop-blur-xl p-6 rounded-3xl border border-gray-800 shadow-2xl relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color-10)] via-transparent to-transparent pointer-events-none"></div>
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-[var(--theme-color)] via-purple-500 to-indigo-500"></div>
        
        <div class="flex items-center gap-4 relative z-10">
            <div class="w-12 h-12 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] flex items-center justify-center text-[var(--theme-color)] shadow-lg shadow-[var(--theme-color-10)]">
                <x-heroicon-o-link class="w-6 h-6" />
            </div>
            <div>
                <h1 class="text-2xl font-serif font-bold text-white tracking-wide">Linktree Verwaltung</h1>
                <p class="text-sm text-gray-400">Micro-Landingpage, Bio-Links & Klick-Statistiken</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 relative z-10">
            <a href="{{ route('admin.marketing-tracking') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-medium transition-all border border-gray-700 hover:border-gray-600 shadow-sm">
                <x-heroicon-o-chart-bar-square class="w-4 h-4 text-[var(--theme-color)]" />
                Tracking Verwaltung
            </a>
            <a href="{{ route('frontend.linktree') }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-medium transition-all border border-gray-700 hover:border-gray-600 shadow-sm">
                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4 text-gray-400" />
                Live-Ansicht öffnen
            </a>
            <button wire:click="create" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[var(--theme-color)] hover:opacity-90 text-gray-950 font-bold text-sm transition-all shadow-lg shadow-[var(--theme-color-20)]">
                <x-heroicon-o-plus class="w-5 h-5 stroke-2" />
                Neuer Link
            </button>
        </div>
    </div>

    <!-- KPIs Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gray-900/60 backdrop-blur-md p-6 rounded-3xl border border-gray-800 flex items-center gap-5 relative overflow-hidden group">
            <div class="w-14 h-14 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] flex items-center justify-center text-[var(--theme-color)] shrink-0 group-hover:scale-110 transition-transform">
                <x-heroicon-o-eye class="w-7 h-7" />
            </div>
            <div>
                <p class="text-sm font-medium text-gray-400">Profil Aufrufe (Gesamt)</p>
                <p class="text-3xl font-bold text-white mt-1">{{ number_format($totalVisits, 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="bg-gray-900/60 backdrop-blur-md p-6 rounded-3xl border border-gray-800 flex items-center gap-5 relative overflow-hidden group">
            <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0 group-hover:scale-110 transition-transform">
                <x-heroicon-o-cursor-arrow-rays class="w-7 h-7" />
            </div>
            <div>
                <p class="text-sm font-medium text-gray-400">Linktree Klicks (Gesamt)</p>
                <p class="text-3xl font-bold text-white mt-1">{{ number_format($totalClicks, 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="bg-gray-900/60 backdrop-blur-md p-6 rounded-3xl border border-gray-800 flex items-center gap-5 relative overflow-hidden group">
            <div class="w-14 h-14 rounded-2xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 shrink-0 group-hover:scale-110 transition-transform">
                <x-heroicon-o-chart-bar class="w-7 h-7" />
            </div>
            <div>
                <p class="text-sm font-medium text-gray-400">CTR (Klickrate)</p>
                <p class="text-3xl font-bold text-white mt-1">{{ $ctr }}%</p>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Column: Settings & Customization -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-gray-900/60 backdrop-blur-md p-6 rounded-3xl border border-gray-800 shadow-xl">
                <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                    <x-heroicon-o-paint-brush class="w-5 h-5 text-[var(--theme-color)]" />
                    Design Einstellungen
                </h2>
                
                @if (session()->has('settings_saved'))
                    <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium flex items-center gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 shrink-0" />
                        {{ session('settings_saved') }}
                    </div>
                @endif

                <form wire:submit.prevent="saveSettings" class="space-y-5">
                    
                    <!-- Theme Color -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Akzentfarbe (Hex)</label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model.live="themeColor" class="w-10 h-10 rounded-lg cursor-pointer bg-gray-950 border border-gray-800 p-1">
                            <input type="text" wire:model.live="themeColor" class="flex-1 bg-gray-950 border border-gray-800 rounded-xl px-4 py-2 text-white text-sm focus:outline-none focus:border-[var(--theme-color)] font-mono">
                        </div>
                        @error('themeColor') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Profile Image Upload -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Profilbild / Logo</label>
                        @if($existingProfileImage)
                            <div class="mb-3 flex items-center gap-4 bg-gray-950 p-2.5 rounded-xl border border-gray-800">
                                <img src="{{ $existingProfileImage }}" class="w-12 h-12 rounded-full object-cover border border-[var(--theme-color)]/30">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-300 truncate">Aktuelles Bild aktiv</p>
                                    <button type="button" wire:click="deleteProfileImage" class="text-[11px] text-red-400 hover:underline mt-0.5 flex items-center gap-1">
                                        <x-heroicon-o-trash class="w-3 h-3" /> Entfernen (Standard nutzen)
                                    </button>
                                </div>
                            </div>
                        @endif
                        <input type="file" wire:model="profileImage" accept="image/*" class="w-full text-xs text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-gray-800 file:text-gray-300 hover:file:bg-gray-700 cursor-pointer border border-gray-800 rounded-xl bg-gray-950 p-1.5 focus:outline-none">
                        <div wire:loading wire:target="profileImage" class="text-[11px] text-[var(--theme-color)] mt-1 animate-pulse">Lade Bild hoch...</div>
                        @error('profileImage') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Background Video Upload -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Hintergrund-Video (MP4)</label>
                        @if($existingBgVideo)
                            <div class="mb-3 flex items-center gap-4 bg-gray-950 p-2.5 rounded-xl border border-gray-800">
                                <div class="w-12 h-12 rounded-lg bg-gray-900 border border-gray-700 flex items-center justify-center text-[var(--theme-color)] shrink-0">
                                    <x-heroicon-o-film class="w-6 h-6" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-300 truncate">Eigenes Video aktiv</p>
                                    <button type="button" wire:click="deleteBgVideo" class="text-[11px] text-red-400 hover:underline mt-0.5 flex items-center gap-1">
                                        <x-heroicon-o-trash class="w-3 h-3" /> Entfernen (Standard nutzen)
                                    </button>
                                </div>
                            </div>
                        @endif
                        <input type="file" wire:model="bgVideo" accept="video/mp4,video/webm" class="w-full text-xs text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-gray-800 file:text-gray-300 hover:file:bg-gray-700 cursor-pointer border border-gray-800 rounded-xl bg-gray-950 p-1.5 focus:outline-none">
                        <div wire:loading wire:target="bgVideo" class="text-[11px] text-[var(--theme-color)] mt-1 animate-pulse">Lade Video hoch (kann dauern)...</div>
                        @error('bgVideo') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-[var(--theme-color)] hover:opacity-90 text-gray-950 text-xs font-bold uppercase tracking-wider transition-all shadow-lg shadow-[var(--theme-color-20)] flex justify-center items-center gap-2">
                            <x-heroicon-o-check class="w-4 h-4 stroke-2" /> Design Speichern
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <!-- Right Column: Links List -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-gray-900/60 backdrop-blur-md p-6 rounded-3xl border border-gray-800 shadow-xl">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <x-heroicon-o-bars-3-bottom-left class="w-5 h-5 text-[var(--theme-color)]" />
                        Aktive Links & Reihenfolge
                    </h2>
                    <span class="text-xs text-gray-500 bg-gray-950 px-3 py-1 rounded-full border border-gray-800">{{ count($links) }} Einträge</span>
                </div>

                @if(empty($links))
                    <div class="text-center py-12 border-2 border-dashed border-gray-800 rounded-2xl">
                        <x-heroicon-o-link class="w-12 h-12 text-gray-600 mx-auto mb-3" />
                        <h3 class="text-gray-300 font-medium">Noch keine Links vorhanden</h3>
                        <p class="text-gray-500 text-sm mt-1">Erstelle deinen ersten Link für die Linktree-Seite.</p>
                        <button wire:click="create" class="mt-4 px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-xl text-sm transition-colors border border-gray-700">Jetzt anlegen</button>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($links as $index => $link)
                            <div class="flex items-center justify-between gap-4 p-4 rounded-2xl bg-gray-950/60 border {{ $link['type'] === 'highlight' ? 'border-[var(--theme-color-40)] shadow-[0_0_15px_rgba(0,0,0,0.2)]' : 'border-gray-800/80' }} hover:border-gray-700 transition-all group">
                                
                                <!-- Sort Controls -->
                                <div class="flex flex-col gap-1 text-gray-600">
                                    <button wire:click="moveUp('{{ $link['id'] }}')" @if($index === 0) disabled @endif class="hover:text-[var(--theme-color)] disabled:opacity-20 transition-colors p-0.5">
                                        <x-heroicon-s-chevron-up class="w-4 h-4" />
                                    </button>
                                    <button wire:click="moveDown('{{ $link['id'] }}')" @if($index === count($links) - 1) disabled @endif class="hover:text-[var(--theme-color)] disabled:opacity-20 transition-colors p-0.5">
                                        <x-heroicon-s-chevron-down class="w-4 h-4" />
                                    </button>
                                </div>

                                <!-- Icon -->
                                <div class="w-10 h-10 rounded-xl bg-gray-900 border border-gray-800 flex items-center justify-center shrink-0 {{ $link['is_active'] ? 'text-[var(--theme-color)]' : 'text-gray-600' }}">
                                    @if($link['icon'] === 'instagram')
                                        <span class="font-bold text-xs uppercase">IG</span>
                                    @elseif($link['icon'] === 'tiktok')
                                        <span class="font-bold text-xs uppercase">TT</span>
                                    @elseif($link['icon'])
                                        @svg('heroicon-o-' . $link['icon'], 'w-5 h-5')
                                    @else
                                        <x-heroicon-o-link class="w-5 h-5" />
                                    @endif
                                </div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-bold {{ $link['is_active'] ? 'text-white' : 'text-gray-500 line-through' }} truncate">
                                            {{ $link['title'] }}
                                        </h3>
                                        @if($link['type'] === 'highlight')
                                            <span class="px-2 py-0.5 text-[9px] uppercase tracking-wider font-extrabold rounded bg-[var(--theme-color-20)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">Highlight</span>
                                        @endif
                                        @if(!$link['is_active'])
                                            <span class="px-2 py-0.5 text-[9px] uppercase tracking-wider font-bold rounded bg-red-500/10 text-red-400 border border-red-500/20">Inaktiv</span>
                                        @endif
                                    </div>
                                    <a href="{{ $link['url'] }}" target="_blank" class="text-xs text-gray-500 hover:text-gray-300 truncate block mt-0.5 transition-colors">
                                        {{ $link['url'] }}
                                    </a>
                                </div>

                                <!-- Stats -->
                                <div class="hidden sm:flex flex-col items-end px-4 border-r border-gray-800">
                                    <span class="text-xs font-bold text-white">{{ $link['clicks_count'] ?? 0 }}</span>
                                    <span class="text-[10px] text-gray-500 uppercase tracking-wider">Klicks</span>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2">
                                    <button wire:click="toggleActive('{{ $link['id'] }}')" class="p-2 rounded-xl border {{ $link['is_active'] ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400 hover:bg-emerald-500/20' : 'bg-gray-900 border-gray-800 text-gray-500 hover:text-gray-300' }} transition-colors" title="{{ $link['is_active'] ? 'Deaktivieren' : 'Aktivieren' }}">
                                        <x-heroicon-o-power class="w-4 h-4" />
                                    </button>
                                    <button wire:click="edit('{{ $link['id'] }}')" class="p-2 rounded-xl bg-gray-900 hover:bg-gray-800 text-gray-400 hover:text-white border border-gray-800 transition-colors" title="Bearbeiten">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </button>
                                    <button wire:click="delete('{{ $link['id'] }}')" wire:confirm="Diesen Link wirklich unwiderruflich löschen?" class="p-2 rounded-xl bg-gray-900 hover:bg-red-500/20 text-gray-500 hover:text-red-400 border border-gray-800 hover:border-red-500/30 transition-colors" title="Löschen">
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
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
                        <x-heroicon-o-link class="w-5 h-5 text-[var(--theme-color)]" />
                        {{ $editingId ? 'Link bearbeiten' : 'Neuen Link erstellen' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-gray-500 hover:text-white transition-colors">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>

                <form wire:submit.prevent="save" class="p-6 space-y-5">
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Titel *</label>
                        <input type="text" wire:model="title" placeholder="z.B. Zum Onlineshop" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-[var(--theme-color)] transition-colors">
                        @error('title') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Ziel-URL *</label>
                        <input type="url" wire:model="url" placeholder="https://..." class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-[var(--theme-color)] transition-colors">
                        @error('url') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Icon</label>
                            <select wire:model="icon" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-[var(--theme-color)] transition-colors">
                                <option value="globe-alt">Weltkugel (Standard)</option>
                                <option value="shopping-bag">Shopping Bag (Shop)</option>
                                <option value="sparkles">Funken (Neuheiten)</option>
                                <option value="envelope">Briefumschlag (Kontakt)</option>
                                <option value="star">Stern (Bewertungen)</option>
                                <option value="heart">Herz</option>
                                <option value="gift">Geschenk (Aktion)</option>
                                <option value="chat-bubble-left-right">Chat / WhatsApp</option>
                                <option value="instagram">Instagram Logo</option>
                                <option value="tiktok">TikTok Logo</option>
                            </select>
                            @error('icon') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Darstellung</label>
                            <select wire:model="type" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-[var(--theme-color)] transition-colors">
                                <option value="standard">Standard Button</option>
                                <option value="highlight">Highlight (Hervorgehoben)</option>
                            </select>
                            @error('type') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="pt-2">
                        <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl bg-gray-950 border border-gray-800">
                            <input type="checkbox" wire:model="is_active" class="w-5 h-5 rounded bg-gray-900 border-gray-700 text-[var(--theme-color)] focus:ring-0">
                            <div>
                                <span class="text-sm font-bold text-white block">Link ist aktiv</span>
                                <span class="text-xs text-gray-500">Nur aktive Links werden öffentlich angezeigt.</span>
                            </div>
                        </label>
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

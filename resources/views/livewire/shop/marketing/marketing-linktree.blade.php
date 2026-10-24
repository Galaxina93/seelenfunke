<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;" class="animate-fade-in-up font-sans antialiased text-gray-700 dark:text-gray-300 min-h-screen">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-8 pb-20">

        {{-- HEADER --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl relative overflow-hidden transition-colors">
            <div class="hidden dark:block absolute inset-0 bg-gradient-to-br from-[var(--theme-color-10)] via-transparent to-transparent pointer-events-none"></div>
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-[var(--theme-color)] via-[var(--theme-color-50)] to-transparent"></div>

            <div class="flex items-center gap-5 relative z-10">
                <div class="w-14 h-14 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)] shadow-sm dark:shadow-[0_0_20px_var(--theme-color-20)] shrink-0">
                    <x-heroicon-o-link class="w-7 h-7" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">
                        Linktree Verwaltung
                    </h1>
                    <p class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mt-1.5">
                        Micro-Landingpage, Bio-Links & Klick-Statistiken
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 relative z-10">
                <a href="{{ route('admin.marketing-tracking') }}" class="inline-flex items-center gap-2 px-4 py-3 rounded-2xl bg-gray-50 dark:bg-gray-950 hover:bg-[var(--theme-color-10)] text-gray-700 dark:text-gray-300 hover:text-[var(--theme-color)] text-[10px] font-black uppercase tracking-widest transition-all border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-30)] shadow-sm dark:shadow-inner">
                    <x-heroicon-o-chart-bar-square class="w-4 h-4 text-[var(--theme-color)]" />
                    Tracking Verwaltung
                </a>
                <a href="{{ route('frontend.linktree') }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-3 rounded-2xl bg-gray-50 dark:bg-gray-950 hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-[10px] font-black uppercase tracking-widest transition-all border border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 shadow-sm dark:shadow-inner">
                    <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                    Live-Ansicht öffnen
                </a>
                <button wire:click="create" class="inline-flex items-center gap-2.5 px-6 py-3 rounded-2xl bg-[var(--theme-color)] border border-[var(--theme-color-50)] hover:opacity-90 hover:scale-[1.02] text-gray-900 dark:text-gray-950 font-black text-[10px] uppercase tracking-widest transition-all shadow-sm dark:shadow-[0_0_20px_var(--theme-color-30)]">
                    <x-heroicon-m-plus class="w-4 h-4 stroke-2" />
                    Neuer Link
                </button>
            </div>
        </div>

        {{-- KPIS SECTION --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- KPI 1: Profil Aufrufe --}}
            <div class="relative bg-white dark:bg-gradient-to-br dark:from-[var(--theme-color-15)] dark:via-gray-900/90 dark:to-gray-900 border border-gray-200 dark:border-[var(--theme-color-30)] rounded-[2rem] p-6 shadow-sm dark:shadow-[0_0_30px_var(--theme-color-10)] flex items-center justify-between overflow-hidden group transition-colors">
                <div class="hidden dark:block absolute -right-4 -bottom-4 w-24 h-24 bg-[var(--theme-color-10)] rounded-full blur-xl pointer-events-none"></div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mb-1">Profil Aufrufe (Gesamt)</p>
                    <h3 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white">{{ number_format($totalVisits, 0, ',', '.') }}</h3>
                    <p class="text-[10px] font-mono text-gray-500 dark:text-gray-400 mt-1.5">Besucher auf /links</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)] shadow-sm dark:shadow-inner shrink-0 group-hover:scale-110 transition-transform">
                    <x-heroicon-o-eye class="w-7 h-7" />
                </div>
            </div>

            {{-- KPI 2: Linktree Klicks --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl flex items-center justify-between relative overflow-hidden group transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Linktree Klicks (Gesamt)</p>
                    <h3 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white">{{ number_format($totalClicks, 0, ',', '.') }}</h3>
                    <p class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 mt-1.5">Interaktionen auf Buttons</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center justify-center shadow-sm dark:shadow-inner shrink-0 group-hover:scale-110 transition-transform">
                    <x-heroicon-o-cursor-arrow-rays class="w-7 h-7" />
                </div>
            </div>

            {{-- KPI 3: CTR --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl flex items-center justify-between relative overflow-hidden group transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">CTR (Klickrate)</p>
                    <h3 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white">{{ $ctr }}%</h3>
                    <p class="text-[10px] font-mono text-purple-600 dark:text-purple-400 mt-1.5">Verhältnis Aufrufe zu Klicks</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-purple-50 text-purple-600 border border-purple-200 dark:bg-purple-500/10 dark:border-purple-500/20 dark:text-purple-400 flex items-center justify-center shadow-sm dark:shadow-inner shrink-0 group-hover:scale-110 transition-transform">
                    <x-heroicon-o-chart-bar class="w-7 h-7" />
                </div>
            </div>
        </div>

        {{-- MAIN CONTENT GRID --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- LEFT COLUMN: DESIGN SETTINGS --}}
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl transition-colors">
                    <div class="flex items-center gap-3 pb-5 mb-6 border-b border-gray-100 dark:border-gray-800">
                        <div class="w-10 h-10 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center shrink-0">
                            <x-heroicon-o-paint-brush class="w-5 h-5" />
                        </div>
                        <div>
                            <h2 class="font-serif font-bold text-gray-900 dark:text-white text-lg tracking-tight">Design Einstellungen</h2>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mt-0.5">Look & Feel der /links Seite</p>
                        </div>
                    </div>

                    @if (session()->has('settings_saved'))
                        <div class="mb-5 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border-l-4 border-emerald-500 text-emerald-700 dark:text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center justify-between shadow-sm dark:shadow-inner">
                            <span>{{ session('settings_saved') }}</span>
                            <x-heroicon-s-check-circle class="w-5 h-5 shrink-0" />
                        </div>
                    @endif

                    <form wire:submit.prevent="saveSettings" class="space-y-6">

                        {{-- Theme Color --}}
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">
                                Akzentfarbe auf /links (Hex)
                            </label>
                            <div class="flex items-center gap-3">
                                <input type="color" wire:model.live="themeColor" class="w-12 h-12 rounded-2xl cursor-pointer bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 p-1.5 shadow-sm dark:shadow-inner">
                                <input type="text" wire:model.live="themeColor" class="flex-1 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-2xl px-4 py-3 text-gray-900 dark:text-white text-sm font-mono font-bold focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner outline-none transition-all">
                            </div>
                            @error('themeColor') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                        </div>

                        {{-- Profile Image Upload --}}
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">
                                Profilbild / Logo
                            </label>
                            @if($existingProfileImage)
                                <div class="mb-3 flex items-center gap-4 bg-gray-50 dark:bg-gray-950 p-3 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
                                    <img src="{{ $existingProfileImage }}" class="w-12 h-12 rounded-full object-cover border-2 border-[var(--theme-color-40)]">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-gray-800 dark:text-gray-200 truncate">Eigenes Bild aktiv</p>
                                        <button type="button" wire:click="deleteProfileImage" class="text-[10px] font-black uppercase tracking-wider text-red-600 dark:text-red-400 hover:underline mt-1 flex items-center gap-1">
                                            <x-heroicon-o-trash class="w-3.5 h-3.5" /> Entfernen (Standard nutzen)
                                        </button>
                                    </div>
                                </div>
                            @endif
                            <input type="file" wire:model="profileImage" accept="image/*" class="w-full text-xs text-gray-600 dark:text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:bg-[var(--theme-color-10)] file:text-[var(--theme-color)] hover:file:bg-[var(--theme-color-20)] cursor-pointer border border-gray-300 dark:border-gray-800 rounded-2xl bg-gray-50 dark:bg-gray-950 p-2 focus:outline-none shadow-sm dark:shadow-inner transition-colors">
                            <div wire:loading wire:target="profileImage" class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mt-1.5 animate-pulse ml-1">Lade Bild hoch...</div>
                            @error('profileImage') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                        </div>

                        {{-- Background Video Upload --}}
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">
                                Hintergrund-Video (MP4)
                            </label>
                            @if($existingBgVideo)
                                <div class="mb-3 flex items-center gap-4 bg-gray-50 dark:bg-gray-950 p-3 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
                                    <div class="w-12 h-12 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-[var(--theme-color)] shrink-0">
                                        <x-heroicon-o-film class="w-6 h-6" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-gray-800 dark:text-gray-200 truncate">Eigenes Video aktiv</p>
                                        <button type="button" wire:click="deleteBgVideo" class="text-[10px] font-black uppercase tracking-wider text-red-600 dark:text-red-400 hover:underline mt-1 flex items-center gap-1">
                                            <x-heroicon-o-trash class="w-3.5 h-3.5" /> Entfernen (Standard nutzen)
                                        </button>
                                    </div>
                                </div>
                            @endif
                            <input type="file" wire:model="bgVideo" accept="video/mp4,video/webm" class="w-full text-xs text-gray-600 dark:text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:bg-[var(--theme-color-10)] file:text-[var(--theme-color)] hover:file:bg-[var(--theme-color-20)] cursor-pointer border border-gray-300 dark:border-gray-800 rounded-2xl bg-gray-50 dark:bg-gray-950 p-2 focus:outline-none shadow-sm dark:shadow-inner transition-colors">
                            <div wire:loading wire:target="bgVideo" class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mt-1.5 animate-pulse ml-1">Lade Video hoch (kann dauern)...</div>
                            @error('bgVideo') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full py-3.5 rounded-2xl bg-[var(--theme-color)] border border-[var(--theme-color-50)] hover:opacity-90 hover:scale-[1.01] text-gray-900 dark:text-gray-950 text-[10px] font-black uppercase tracking-widest transition-all shadow-sm dark:shadow-[0_0_20px_var(--theme-color-30)] flex justify-center items-center gap-2">
                                <x-heroicon-m-check class="w-4 h-4 stroke-2" />
                                Design Speichern
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            {{-- RIGHT COLUMN: LINKS LIST --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl transition-colors">
                    <div class="flex items-center justify-between pb-5 mb-6 border-b border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center shrink-0">
                                <x-heroicon-o-bars-3-bottom-left class="w-5 h-5" />
                            </div>
                            <div>
                                <h2 class="font-serif font-bold text-gray-900 dark:text-white text-lg tracking-tight">Aktive Links & Reihenfolge</h2>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mt-0.5">Position & Sichtbarkeit steuern</p>
                            </div>
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-950 px-3.5 py-1.5 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
                            {{ count($links) }} Einträge
                        </span>
                    </div>

                    @if(empty($links))
                        <div class="text-center py-16 px-6 border-2 border-dashed border-gray-200 dark:border-gray-800 rounded-[2rem] bg-gray-50/50 dark:bg-gray-950/30">
                            <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-center mx-auto mb-4 shadow-sm dark:shadow-inner">
                                <x-heroicon-o-link class="w-8 h-8 text-gray-400 dark:text-gray-600" />
                            </div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">Noch keine Links vorhanden</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-md mx-auto">Erstelle deinen ersten Link für die öffentliche Linktree-Seite.</p>
                            <button wire:click="create" class="mt-5 px-5 py-2.5 bg-[var(--theme-color)] hover:opacity-90 text-gray-900 dark:text-gray-950 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-sm">
                                Jetzt anlegen
                            </button>
                        </div>
                    @else
                        <div class="space-y-3.5">
                            @foreach($links as $index => $link)
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 sm:p-5 rounded-2xl bg-gray-50/80 dark:bg-gray-950/70 border {{ $link['type'] === 'highlight' ? 'border-[var(--theme-color-50)] shadow-md dark:shadow-[0_0_20px_var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800/80' }} hover:bg-white dark:hover:bg-gray-900 hover:border-[var(--theme-color-40)] transition-all shadow-sm dark:shadow-inner group">

                                    <div class="flex items-center gap-3.5 flex-1 min-w-0">
                                        {{-- Sort Controls --}}
                                        <div class="flex flex-col gap-1 text-gray-400 dark:text-gray-600">
                                            <button wire:click="moveUp('{{ $link['id'] }}')" @if($index === 0) disabled @endif class="hover:text-[var(--theme-color)] disabled:opacity-25 transition-colors p-0.5" title="Nach oben">
                                                <x-heroicon-s-chevron-up class="w-4 h-4" />
                                            </button>
                                            <button wire:click="moveDown('{{ $link['id'] }}')" @if($index === count($links) - 1) disabled @endif class="hover:text-[var(--theme-color)] disabled:opacity-25 transition-colors p-0.5" title="Nach unten">
                                                <x-heroicon-s-chevron-down class="w-4 h-4" />
                                            </button>
                                        </div>

                                        {{-- Icon --}}
                                        <div class="w-12 h-12 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-center shrink-0 shadow-sm dark:shadow-inner {{ $link['is_active'] ? 'text-[var(--theme-color)]' : 'text-gray-400 dark:text-gray-600' }}">
                                            @if($link['icon'] === 'instagram')
                                                <span class="font-black text-xs uppercase tracking-wider">IG</span>
                                            @elseif($link['icon'] === 'tiktok')
                                                <span class="font-black text-xs uppercase tracking-wider">TT</span>
                                            @elseif($link['icon'])
                                                @svg('heroicon-o-' . $link['icon'], 'w-5 h-5')
                                            @else
                                                <x-heroicon-o-link class="w-5 h-5" />
                                            @endif
                                        </div>

                                        {{-- Info --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm sm:text-base font-bold {{ $link['is_active'] ? 'text-gray-900 dark:text-white group-hover:text-[var(--theme-color)]' : 'text-gray-400 dark:text-gray-500 line-through' }} truncate transition-colors">
                                                    {{ $link['title'] }}
                                                </h3>
                                                @if($link['type'] === 'highlight')
                                                    <span class="px-2.5 py-0.5 text-[9px] uppercase tracking-widest font-black rounded-lg bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                                                        Highlight
                                                    </span>
                                                @endif
                                                @if(!$link['is_active'])
                                                    <span class="px-2.5 py-0.5 text-[9px] uppercase tracking-widest font-black rounded-lg bg-red-50 text-red-600 border border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20">
                                                        Inaktiv
                                                    </span>
                                                @endif
                                            </div>
                                            <a href="{{ $link['url'] }}" target="_blank" class="text-xs font-mono text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)] truncate block mt-1 transition-colors">
                                                {{ $link['url'] }}
                                            </a>
                                        </div>
                                    </div>

                                    {{-- Stats & Actions --}}
                                    <div class="flex items-center justify-between sm:justify-end gap-4 pt-3 sm:pt-0 border-t sm:border-t-0 border-gray-200/80 dark:border-gray-800/80">
                                        <div class="flex flex-col items-start sm:items-end px-4 sm:border-r border-gray-200 dark:border-gray-800">
                                            <span class="text-sm font-black font-mono text-gray-900 dark:text-white">{{ number_format($link['clicks_count'] ?? 0, 0, ',', '.') }}</span>
                                            <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Klicks</span>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button wire:click="toggleActive('{{ $link['id'] }}')" class="p-2.5 rounded-xl border {{ $link['is_active'] ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-500/20' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white' }} shadow-sm dark:shadow-inner transition-all" title="{{ $link['is_active'] ? 'Deaktivieren' : 'Aktivieren' }}">
                                                <x-heroicon-o-power class="w-4 h-4" />
                                            </button>
                                            <button wire:click="edit('{{ $link['id'] }}')" class="p-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] hover:text-[var(--theme-color)] shadow-sm dark:shadow-inner transition-all" title="Bearbeiten">
                                                <x-heroicon-o-pencil-square class="w-4 h-4" />
                                            </button>
                                            <button wire:click="delete('{{ $link['id'] }}')" wire:confirm="Diesen Link wirklich unwiderruflich löschen?" class="p-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-400 dark:text-gray-500 hover:bg-red-50 dark:hover:bg-red-500/10 hover:border-red-200 dark:hover:border-red-500/30 hover:text-red-600 dark:hover:text-red-400 shadow-sm dark:shadow-inner transition-all" title="Löschen">
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
                                <x-heroicon-o-link class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="font-serif font-bold text-gray-900 dark:text-white text-lg">
                                    {{ $editingId ? 'Link bearbeiten' : 'Neuen Link erstellen' }}
                                </h3>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-500">Linktree Button konfigurieren</p>
                            </div>
                        </div>
                        <button wire:click="$set('showModal', false)" class="w-9 h-9 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-gray-900 dark:hover:text-white flex items-center justify-center transition-all shadow-sm">
                            <x-heroicon-o-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <form wire:submit.prevent="save" class="p-6 sm:p-8 space-y-5">

                        <div>
                            <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">Titel *</label>
                            <input type="text" wire:model="title" placeholder="z.B. Zum Onlineshop" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-2xl px-4 py-3.5 text-gray-900 dark:text-white text-sm font-bold focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner outline-none transition-all">
                            @error('title') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">Ziel-URL *</label>
                            <input type="url" wire:model="url" placeholder="https://..." class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-2xl px-4 py-3.5 text-gray-900 dark:text-white text-sm font-mono focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner outline-none transition-all">
                            @error('url') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">Icon</label>
                                <select wire:model="icon" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-2xl px-4 py-3.5 text-gray-900 dark:text-white text-sm font-medium focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner outline-none transition-all">
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
                                @error('icon') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 ml-1">Darstellung</label>
                                <select wire:model="type" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-2xl px-4 py-3.5 text-gray-900 dark:text-white text-sm font-medium focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner outline-none transition-all">
                                    <option value="standard">Standard Button</option>
                                    <option value="highlight">Highlight (Hervorgehoben)</option>
                                </select>
                                @error('type') <span class="text-red-500 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest mt-1.5 block ml-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="pt-1">
                            <label class="flex items-center gap-3.5 cursor-pointer p-4 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
                                <input type="checkbox" wire:model="is_active" class="w-5 h-5 rounded bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-[var(--theme-color)] focus:ring-0">
                                <div>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white block">Link ist aktiv</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Nur aktive Links werden öffentlich auf /links angezeigt.</span>
                                </div>
                            </label>
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

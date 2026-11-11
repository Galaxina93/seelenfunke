<div x-data="funkenwerkMusicStudioPage()"
    @music-view-tracks-synced.window="onViewTracksSynced($event.detail.tracks, $event.detail.autoPlayRandom)"
    style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;"
    class="flex flex-col h-[95vh] md:h-[calc(100vh-10rem)] bg-white/95 dark:bg-gray-900/90 backdrop-blur-xl rounded-2xl md:rounded-[2.5rem] dark:shadow-2xl border border-gray-200/80 dark:border-gray-800 overflow-hidden min-h-[560px] md:min-h-[720px] animate-fade-in-up transition-colors duration-300">

    {{-- Livewire-synced JSON dataset of the currently visible playlist tracks --}}
    <div x-ref="viewTracksData" data-tracks="{{ json_encode($tracksPayload ?? []) }}" class="hidden"></div>

    {{-- TOP 3-COLUMN WORKSPACE --}}
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-0 overflow-hidden min-h-0">

        {{-- LEFT SIDEBAR: PLAYLISTS & SMART VIEWS --}}
        <aside class="lg:col-span-3 xl:col-span-3 bg-gray-50/80 dark:bg-gray-950/50 border-b lg:border-b-0 lg:border-r border-gray-200 dark:border-gray-800 flex flex-col justify-between p-4 sm:p-5 select-none overflow-hidden">
            <div class="space-y-4 flex-1 flex flex-col min-h-0">

                {{-- Search Input --}}
                <div class="relative shrink-0">
                    <input type="text"
                           wire:model.live.debounce.250ms="searchQuery"
                           placeholder="Titel oder Künstler suchen..."
                           class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] rounded-xl pl-9 pr-8 py-2.5 text-xs sm:text-sm font-medium text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none transition-all">
                    <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    @if($searchQuery !== '')
                        <button wire:click="$set('searchQuery', '')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 dark:hover:text-white">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>

                {{-- Smart Views --}}
                <nav class="space-y-1 shrink-0">
                    <button wire:click="selectPlaylist('all')"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-all border {{ $activePlaylistId === 'all' && $searchQuery === '' ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-30)] font-bold shadow-xs' : 'border-transparent font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-900/80' }}">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                            </svg>
                            <span>Alle Songs</span>
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-[var(--theme-color-10)] text-[var(--theme-color)] font-bold">{{ $smartCounts['all'] ?? 0 }}</span>
                    </button>

                    <button wire:click="selectPlaylist('favorites')"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-all border {{ $activePlaylistId === 'favorites' && $searchQuery === '' ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30 font-bold shadow-xs' : 'border-transparent font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-900/80' }}">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                            </svg>
                            <span>Favoriten</span>
                        </span>
                        @if(($smartCounts['favorites'] ?? 0) > 0)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400 font-bold">{{ $smartCounts['favorites'] }}</span>
                        @endif
                    </button>

                    <button wire:click="selectPlaylist('most_played')"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-all border {{ $activePlaylistId === 'most_played' && $searchQuery === '' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 font-bold shadow-xs' : 'border-transparent font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-900/80' }}">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg>
                            <span>Meistgehört</span>
                        </span>
                    </button>
                </nav>

                <div class="border-t border-gray-200 dark:border-gray-800 pt-3 flex-1 flex flex-col min-h-0">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 px-3 mb-2">Playlists</div>
                    <div class="space-y-1 overflow-y-auto flex-1 pr-1 custom-scrollbar">
                        @foreach($playlists as $pl)
                            <div class="group flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-all cursor-pointer border {{ $activePlaylistId === $pl->id && $searchQuery === '' ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-30)] font-bold shadow-xs' : 'border-transparent font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-900/80' }}"
                                 wire:click="selectPlaylist('{{ $pl->id }}')">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $pl->color ?: '#C5A059' }}"></span>
                                    <span class="truncate">{{ $pl->name }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-200/70 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-semibold">{{ $pl->tracks_count }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Create New Playlist Footer --}}
            <div class="pt-3 border-t border-gray-200 dark:border-gray-800 mt-3 shrink-0 flex items-center gap-2">
                <input type="text"
                       wire:model="newPlaylistName"
                       wire:keydown.enter="createPlaylist"
                       placeholder="+ Neue Playlist..."
                       class="flex-1 min-w-0 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-[var(--theme-color)]">
                <button type="button"
                        wire:click="createPlaylist"
                        class="px-3.5 py-2 rounded-xl bg-[var(--theme-color)] text-gray-950 font-bold text-xs hover:opacity-90 transition cursor-pointer shrink-0">
                    + Playlist
                </button>
            </div>
        </aside>

        {{-- CENTER PANE: PLAYLIST HEADER, URL IMPORTER & TRACKLIST --}}
        <main class="{{ $selectedTrack ? 'lg:col-span-6 xl:col-span-6' : 'lg:col-span-9 xl:col-span-9' }} flex flex-col h-full overflow-hidden bg-white dark:bg-gray-900/40">

            {{-- Header & YouTube / File Importer --}}
            <div class="p-5 sm:p-6 border-b border-gray-200/80 dark:border-gray-800/80 shrink-0 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-[var(--theme-color-15)] text-[var(--theme-color)]">Funkenwerk Soundplayer</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $smartCounts['cached'] ?? 0 }} / {{ $smartCounts['all'] ?? 0 }} lokal gecacht</span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-extrabold text-gray-900 dark:text-white mt-1">
                            @if($searchQuery !== '')
                                Suche: „{{ $searchQuery }}“
                            @elseif($activePlaylistId === 'all')
                                Alle Songs
                            @elseif($activePlaylistId === 'favorites')
                                Meine Favoriten
                            @elseif($activePlaylistId === 'most_played')
                                Meistgehörte Titel
                            @else
                                {{ $activePlaylist->name ?? 'Musik' }}
                            @endif
                        </h1>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="playAllFromView(false)"
                                class="px-4 py-2.5 rounded-xl bg-[var(--theme-color)] text-gray-950 font-bold text-xs sm:text-sm flex items-center gap-2 shadow-sm hover:opacity-95 transition cursor-pointer">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            <span>Alle abspielen</span>
                        </button>
                        <button type="button"
                                @click="playAllFromView(true)"
                                class="px-3.5 py-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 border border-[var(--theme-color-30)] font-semibold text-xs sm:text-sm flex items-center gap-2 hover:bg-[var(--theme-color-15)] transition cursor-pointer">
                            <svg class="w-4 h-4 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/>
                            </svg>
                            <span>Zufallswiedergabe</span>
                        </button>
                    </div>
                </div>

                {{-- Direct YouTube / Spotify URL / Playlist Importer + Audio File Upload --}}
                <div class="flex flex-wrap items-center gap-2">
                    <form wire:submit.prevent="importYoutubeUrl" class="flex-1 min-w-[260px] flex items-center gap-2">
                        <input type="text"
                               wire:model="importUrl"
                               placeholder="YouTube- oder Spotify-Song/Playlist-URL einfügen zum Direkt-Download..."
                               class="flex-1 bg-gray-50 dark:bg-gray-950/70 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-[var(--theme-color)]">
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 rounded-xl bg-gray-900 dark:bg-gray-800 text-white dark:text-[var(--theme-color)] border border-gray-800 dark:border-[var(--theme-color-30)] font-bold text-xs sm:text-sm hover:opacity-90 transition cursor-pointer shrink-0">
                            <span wire:loading.remove wire:target="importYoutubeUrl">⬇ URL importieren</span>
                            <span wire:loading wire:target="importYoutubeUrl">Importiere...</span>
                        </button>
                    </form>

                    <label class="px-3.5 py-2 rounded-xl bg-gray-100 dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 font-semibold text-xs sm:text-sm hover:bg-gray-200 dark:hover:bg-gray-800 transition cursor-pointer shrink-0">
                        <span>📂 MP3/M4A Upload</span>
                        <input type="file" wire:model="uploadedAudioFile" accept=".mp3,.m4a,.aac,.wav,.ogg,.flac" class="hidden">
                    </label>
                </div>

                @if(!empty($importStatus['active']))
                    <div wire:poll.1500ms class="p-3 rounded-xl bg-gray-900/90 border border-[var(--theme-color-30)] space-y-1.5">
                        <div class="flex items-center justify-between text-xs font-bold text-[var(--theme-color)]">
                            <span class="truncate">{{ $importStatus['message'] ?: 'Importiere...' }}</span>
                            <span>{{ $importStatus['percent'] ?? 0 }}%</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-gray-800 overflow-hidden">
                            <div class="h-full bg-[var(--theme-color)] transition-all duration-300" style="width: {{ max(4, min(100, (int)($importStatus['percent'] ?? 0))) }}%"></div>
                        </div>
                        @if(!empty($importStatus['current_title']))
                            <div class="text-[11px] text-gray-400 truncate">{{ $importStatus['current_title'] }}</div>
                        @endif
                    </div>
                @endif

                @if($importFeedback)
                    <div class="text-xs font-semibold px-3 py-2 rounded-xl bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-20)]">
                        {{ $importFeedback }}
                    </div>
                @endif
            </div>

            {{-- Track List --}}
            <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-1.5 custom-scrollbar">
                @forelse($tracks as $idx => $track)
                    <div wire:key="music-track-{{ $track->id }}"
                         class="group flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-2xl border transition-all cursor-pointer"
                         :class="currentTrack && currentTrack.id === '{{ $track->id }}'
                            ? 'bg-[var(--theme-color-15)] border-[var(--theme-color)] shadow-xs'
                            : 'bg-gray-50/60 dark:bg-gray-900/60 border-gray-200/60 dark:border-gray-800/70 hover:border-[var(--theme-color-40)]'"
                         @click="playTrackById('{{ $track->id }}')">

                        <div class="flex items-center gap-3.5 min-w-0 flex-1">
                            <div class="w-6 flex items-center justify-center shrink-0">
                                <span x-show="!currentTrack || currentTrack.id !== '{{ $track->id }}'"
                                      class="text-xs font-bold text-gray-400 dark:text-gray-500">{{ $idx + 1 }}</span>
                                <svg x-show="currentTrack && currentTrack.id === '{{ $track->id }}' && !isPlaying"
                                     style="display: none;"
                                     class="w-4 h-4 text-[var(--theme-color)]" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                                <svg x-show="currentTrack && currentTrack.id === '{{ $track->id }}' && isPlaying"
                                     style="display: none;"
                                     class="w-4 h-4 text-[var(--theme-color)]" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                                </svg>
                            </div>

                            <img src="{{ $track->cover_url ?: '/shop/projekt/logo/funkenwerk.png' }}"
                                 alt=""
                                 class="w-10 h-10 rounded-xl object-cover bg-gray-800 shrink-0 border border-gray-200/50 dark:border-gray-700/60">

                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-bold truncate"
                                     :class="currentTrack && currentTrack.id === '{{ $track->id }}' ? 'text-[var(--theme-color)]' : 'text-gray-900 dark:text-white'">
                                    {{ $track->title }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $track->artist ?: 'Unbekannter Künstler' }}</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0" @click.stop>
                            @if($track->status === 'cached')
                                <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/10 text-emerald-500 border border-emerald-500/20" title="Auf Server-SSD gecacht (Sofort-Stream)">SSD</span>
                            @endif

                            <span class="text-xs font-mono text-gray-400 dark:text-gray-500 w-11 text-right">
                                {{ $track->duration_seconds > 0 ? sprintf('%d:%02d', floor($track->duration_seconds / 60), $track->duration_seconds % 60) : '—' }}
                            </span>

                            <button wire:click="toggleFavorite('{{ $track->id }}')"
                                    class="p-1.5 rounded-lg hover:bg-gray-200/60 dark:hover:bg-gray-800 transition cursor-pointer"
                                    title="Favorit">
                                @if($track->is_favorite)
                                    <span class="text-amber-400 text-base">★</span>
                                @else
                                    <span class="text-gray-400 hover:text-amber-400 text-base">☆</span>
                                @endif
                            </button>

                            <button wire:click="selectTrackForEdit('{{ $track->id }}')"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-gray-800 transition cursor-pointer"
                                    title="Details bearbeiten">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="py-16 text-center text-gray-400 dark:text-gray-500 text-sm">
                        Keine Songs in dieser Ansicht gefunden.
                    </div>
                @endforelse
            </div>
        </main>

        {{-- RIGHT SIDEBAR: TRACK INSPECTOR (when a track is selected for editing) --}}
        @if($selectedTrack)
            <aside class="lg:col-span-3 xl:col-span-3 bg-gray-50/90 dark:bg-gray-950/70 border-t lg:border-t-0 lg:border-l border-gray-200 dark:border-gray-800 p-5 flex flex-col justify-between overflow-y-auto">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Song-Details</span>
                        <button wire:click="closeTrackInspector" class="text-gray-400 hover:text-white text-sm cursor-pointer">✕</button>
                    </div>

                    <img src="{{ $selectedTrack->cover_url ?: '/shop/projekt/logo/funkenwerk.png' }}"
                         alt=""
                         class="w-full h-40 object-cover rounded-2xl border border-gray-200 dark:border-gray-800">

                    <div class="space-y-3">
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Titel</label>
                            <input type="text" wire:model="editTitle" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Künstler / Interpret</label>
                            <input type="text" wire:model="editArtist" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Playlist</label>
                            <select wire:model="editPlaylistId" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white">
                                @foreach($playlists as $pl)
                                    <option value="{{ $pl->id }}">{{ $pl->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <button wire:click="saveTrackDetails"
                            class="w-full py-2.5 rounded-xl bg-[var(--theme-color)] text-gray-950 font-bold text-xs sm:text-sm hover:opacity-90 transition cursor-pointer">
                        Änderungen speichern
                    </button>
                </div>

                <div class="pt-4 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <span class="text-xs text-gray-400">{{ $selectedTrack->play_count }}× abgespielt</span>
                    <button wire:click="deleteTrack('{{ $selectedTrack->id }}')"
                            class="px-3 py-1.5 rounded-xl bg-red-500/10 text-red-500 hover:bg-red-500/20 text-xs font-bold transition cursor-pointer">
                        🗑 Löschen
                    </button>
                </div>
            </aside>
        @endif
    </div>

    {{-- BOTTOM PERSISTENT SOUNDPLAYER BAR --}}
    <footer class="h-20 sm:h-22 bg-gray-100/95 dark:bg-gray-950/95 border-t border-gray-200 dark:border-gray-800 px-4 sm:px-6 flex items-center justify-between gap-4 shrink-0 select-none">

        {{-- Now Playing Info --}}
        <div class="flex items-center gap-3.5 min-w-[180px] max-w-[280px] flex-1">
            <img :src="currentTrack ? currentTrack.cover : '/shop/projekt/logo/funkenwerk.png'"
                 alt=""
                 class="w-12 h-12 rounded-xl object-cover bg-gray-800 border border-gray-300 dark:border-gray-700 shrink-0">
            <div class="min-w-0">
                <div class="text-sm font-bold text-gray-900 dark:text-white truncate" x-text="currentTrack ? currentTrack.title : 'Kein Titel ausgewählt'"></div>
                <div class="text-xs text-[var(--theme-color)] font-semibold truncate" x-text="currentTrack ? currentTrack.artist : 'Funkenwerk Soundplayer'"></div>
            </div>
        </div>

        {{-- Center Controls + Scrubber --}}
        <div class="flex flex-col items-center flex-1 max-w-xl">
            <div class="flex items-center gap-3.5 mb-1.5">
                {{-- Zufällige Wiedergabe (Sleek Gold SVG) --}}
                <button type="button" @click="toggleShuffle()"
                        :class="isShuffle
                            ? 'bg-[var(--theme-color-20)] border-[var(--theme-color)] text-[var(--theme-color)] shadow-xs'
                            : 'border-transparent text-[var(--theme-color)] opacity-60 hover:opacity-100 hover:bg-[var(--theme-color-10)]'"
                        class="w-8 h-8 rounded-lg border flex items-center justify-center transition-all cursor-pointer"
                        title="Zufällige Wiedergabe">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/>
                    </svg>
                </button>

                {{-- Vorheriger Titel --}}
                <button type="button" @click="prevTrack()"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-600 dark:text-gray-300 hover:text-[var(--theme-color)] transition cursor-pointer"
                        title="Vorheriger Titel">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
                </button>

                {{-- Play / Pause Button --}}
                <button type="button" @click="togglePlay()"
                        class="w-10 h-10 rounded-full bg-[var(--theme-color)] text-gray-950 flex items-center justify-center shadow-md hover:scale-105 transition cursor-pointer"
                        title="Wiedergabe / Pause">
                    <svg x-show="!isPlaying" class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    <svg x-show="isPlaying" style="display: none;" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                    </svg>
                </button>

                {{-- Nächster Titel --}}
                <button type="button" @click="nextTrack()"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-600 dark:text-gray-300 hover:text-[var(--theme-color)] transition cursor-pointer"
                        title="Nächster Titel">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
                </button>

                {{-- Dauerschleife (Sleek Gold SVG) --}}
                <button type="button" @click="toggleRepeat()"
                        :class="isRepeat
                            ? 'bg-[var(--theme-color-20)] border-[var(--theme-color)] text-[var(--theme-color)] shadow-xs'
                            : 'border-transparent text-[var(--theme-color)] opacity-60 hover:opacity-100 hover:bg-[var(--theme-color-10)]'"
                        class="w-8 h-8 rounded-lg border flex items-center justify-center transition-all cursor-pointer"
                        title="Dauerschleife">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 1l4 4-4 4"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 11V9a4 4 0 014-4h14"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 23l-4-4 4-4"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 13v2a4 4 0 01-4 4H3"/>
                    </svg>
                </button>
            </div>

            <div class="w-full flex items-center gap-2.5">
                <span class="text-[11px] font-mono text-gray-400 w-9 text-right" x-text="formatSec(currentTime)">0:00</span>
                <div class="flex-1 h-2 bg-gray-300 dark:bg-gray-800 rounded-full overflow-hidden cursor-pointer relative" @click="seekTo($event)">
                    <div class="h-full bg-[var(--theme-color)] rounded-full transition-all duration-75"
                         :style="'width: ' + (duration > 0 ? Math.min(100, (currentTime / duration) * 100) : 0) + '%'"></div>
                </div>
                <span class="text-[11px] font-mono text-gray-400 w-9" x-text="formatSec(duration)">0:00</span>
            </div>
        </div>

        {{-- Right Volume Slider --}}
        <div class="hidden md:flex items-center justify-end gap-2.5 min-w-[150px]">
            <svg class="w-4 h-4 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
            </svg>
            <input type="range" min="0" max="1" step="0.02" :value="volume" @input="setVolume($event.target.value)"
                   class="w-24 accent-[var(--theme-color)] cursor-pointer">
        </div>
    </footer>
</div>

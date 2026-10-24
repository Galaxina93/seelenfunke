<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <div class="animate-fade-in-up font-sans antialiased text-gray-700 dark:text-gray-300 pb-12 w-full">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-white/90 dark:bg-gray-900/80 backdrop-blur-md p-6 sm:p-10 rounded-[2.5rem] dark:shadow-2xl border border-gray-200/80 dark:border-gray-800 relative overflow-hidden mb-8 transition-colors duration-300">
            <div class="absolute top-0 right-0 p-8 opacity-10 blur-sm pointer-events-none">
                <x-heroicon-o-link class="w-40 h-40 text-[var(--theme-color)]" />
            </div>
            <div class="relative z-10">
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-4">
                    <span class="text-[var(--theme-color)]"><x-heroicon-o-link class="w-10 h-10" /></span>
                    Linktree Verwaltung
                </h1>
                <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm font-medium">Verwalte hier deine digitale Visitenkarte sowie DSGVO-konforme Tracking-Links.</p>
            </div>
            <div class="relative z-10">
                <a href="{{ route('frontend.linktree') }}" target="_blank" rel="noopener noreferrer" class="px-5 py-2.5 rounded-xl font-bold bg-[var(--theme-color)] text-gray-950 hover:brightness-105 transition-all flex items-center gap-2 shadow-sm text-sm">
                    <span>Live-Linktree öffnen</span>
                    <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <dl class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-4">
            <div class="overflow-hidden rounded-2xl bg-white/90 dark:bg-gray-900/60 backdrop-blur-md border border-gray-200/80 dark:border-gray-800 px-4 py-5 dark:shadow sm:p-6">
                <dt class="truncate text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Profil Aufrufe</dt>
                <dd class="mt-2 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $totalVisits }}</dd>
            </div>
            <div class="overflow-hidden rounded-2xl bg-white/90 dark:bg-gray-900/60 backdrop-blur-md border border-gray-200/80 dark:border-gray-800 px-4 py-5 dark:shadow sm:p-6">
                <dt class="truncate text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Linktree Klicks</dt>
                <dd class="mt-2 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $totalClicks }}</dd>
            </div>
            <div class="overflow-hidden rounded-2xl bg-white/90 dark:bg-gray-900/60 backdrop-blur-md border border-gray-200/80 dark:border-gray-800 px-4 py-5 dark:shadow sm:p-6">
                <dt class="truncate text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">CTR (Click-Through-Rate)</dt>
                <dd class="mt-2 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $globalCtr }}%</dd>
            </div>
            <div class="overflow-hidden rounded-2xl bg-white/90 dark:bg-gray-900/60 backdrop-blur-md border border-gray-200/80 dark:border-gray-800 px-4 py-5 dark:shadow sm:p-6">
                <dt class="truncate text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tracking-Link Aufrufe</dt>
                <dd class="mt-2 text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ $totalTrackingClicks }}</dd>
            </div>
        </dl>

        <!-- Settings Form -->
        <div class="mb-8 bg-white/90 dark:bg-gray-900/60 backdrop-blur-md border border-gray-200/80 dark:border-gray-800 rounded-3xl dark:shadow-2xl p-6 sm:p-8 transition-colors duration-300">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-9 h-9 rounded-xl bg-[var(--theme-color-10)] flex items-center justify-center text-[var(--theme-color)]">
                    <x-heroicon-o-swatch class="w-5 h-5" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Design Einstellungen</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Passe Profilbild und Akzentfarbe für deine helle Linktree-Landingpage an.</p>
                </div>
            </div>

            <form wire:submit.prevent="saveSettings" class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-start">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Profilbild</label>
                    <div class="flex items-center gap-4">
                        <div class="relative w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 overflow-hidden border-2 border-[var(--theme-color)] shadow-sm shrink-0">
                            @if ($profileImage)
                                <img src="{{ $profileImage->temporaryUrl() }}" class="object-cover w-full h-full">
                            @elseif ($currentProfileImage)
                                <img src="{{ asset($currentProfileImage) }}" class="object-cover w-full h-full">
                            @else
                                <x-heroicon-o-user class="w-8 h-8 text-gray-400 dark:text-gray-500 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" />
                            @endif
                        </div>
                        <input type="file" wire:model="profileImage" id="profileImage" class="hidden" accept="image/*">
                        <div class="flex gap-2">
                            <label for="profileImage" class="cursor-pointer bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-800 dark:text-white text-xs font-semibold px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 transition-colors shadow-sm flex items-center gap-2">
                                <x-heroicon-o-arrow-up-tray class="w-4 h-4 text-gray-400" />
                                Bild hochladen
                            </label>
                            @if ($currentProfileImage || $profileImage)
                                <button type="button" wire:click="deleteProfileImage" class="bg-red-500/10 hover:bg-red-500/20 text-red-500 px-3 py-2.5 rounded-xl border border-red-500/20 transition-colors flex items-center justify-center shadow-sm" title="Bild löschen">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                    </div>
                    @error('profileImage') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Theme Farbe</label>
                    <div class="flex items-center gap-3">
                        <input type="color" wire:model.live="themeColor" class="h-10 w-14 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-1 cursor-pointer shadow-sm">
                        <input type="text" wire:model.live="themeColor" class="flex-1 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/80 py-2.5 px-3.5 text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none text-xs font-mono font-medium" placeholder="#C5A059">
                    </div>
                    @error('themeColor') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-2 flex justify-end pt-2" x-data="{ saved: false }" @settings-saved.window="saved = true; setTimeout(() => saved = false, 3000)">
                    <button type="submit" class="px-5 py-2.5 rounded-xl font-bold text-xs hover:brightness-105 transition-all flex items-center gap-2 shadow-sm" :class="saved ? 'bg-emerald-500 text-white' : 'bg-[var(--theme-color)] text-gray-950'">
                        <div wire:loading wire:target="saveSettings" class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></div>

                        <span x-show="!saved" class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4" />
                            Einstellungen speichern
                        </span>

                        <span x-show="saved" x-cloak class="flex items-center gap-2">
                            <x-heroicon-o-check-circle class="w-4 h-4" />
                            Erfolgreich gespeichert!
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Links List Card -->
        <div class="mb-8 bg-white/90 dark:bg-gray-900/60 backdrop-blur-md border border-gray-200/80 dark:border-gray-800 rounded-3xl overflow-hidden dark:shadow-2xl transition-colors duration-300">
            <div class="px-6 sm:px-8 py-5 bg-gray-50/50 dark:bg-gray-900/40 border-b border-gray-200/80 dark:border-gray-800 font-bold text-gray-700 dark:text-gray-300 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-[var(--theme-color-10)] flex items-center justify-center text-[var(--theme-color)]">
                        <x-heroicon-o-bars-3-bottom-left class="w-4 h-4" />
                    </div>
                    <span class="font-bold text-sm text-gray-900 dark:text-white">Aktive Links & Reihenfolge</span>
                </div>
                <span class="text-xs font-normal text-gray-400 dark:text-gray-500">Per Drag & Drop sortierbar</span>
            </div>

            <ul role="list"
                id="linktree-sortable-list"
                class="divide-y divide-gray-200/80 dark:divide-gray-800"
                x-data="{
                    initSortable() {
                        if (typeof Sortable === 'undefined') {
                            let script = document.createElement('script');
                            script.src = '{{ asset('vendor/sortablejs/Sortable.min.js') }}';
                            script.onload = () => this.bindSortable();
                            document.head.appendChild(script);
                        } else {
                            this.bindSortable();
                        }
                    },
                    bindSortable() {
                        let el = document.getElementById('linktree-sortable-list');
                        if (!el) return;
                        Sortable.create(el, {
                            animation: 150,
                            handle: '.link-drag-handle',
                            ghostClass: 'opacity-30',
                            draggable: '.linktree-sortable-item',
                            onEnd: () => {
                                let ids = Array.from(el.querySelectorAll('.linktree-sortable-item'))
                                    .map(node => node.getAttribute('data-link-id'))
                                    .filter(Boolean);
                                $wire.updateOrder(ids);
                            }
                        });
                    }
                }"
                x-init="initSortable()">
                @forelse($links as $link)
                    @if($editId === $link->id)
                        <li class="relative flex flex-col sm:flex-row gap-4 py-4 px-6 bg-[var(--theme-color-5)] transition-colors border-y border-[var(--theme-color-20)]">
                            <div class="flex-none flex items-center justify-center">
                                <div class="h-10 w-10 rounded-xl bg-[var(--theme-color-10)] flex items-center justify-center text-[var(--theme-color)]">
                                    <x-heroicon-o-pencil-square class="h-5 w-5" />
                                </div>
                            </div>
                            <div class="flex-auto grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                                <div class="sm:col-span-3">
                                    <input type="text" wire:model="title" placeholder="Titel" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-3 text-xs text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                                    @error('title') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-4">
                                    <input type="text" wire:model="url" placeholder="https:// oder /shop" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-3 text-xs text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                                    @error('url') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <input type="text" wire:model="icon" placeholder="Icon" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-3 text-xs text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                                </div>
                                <div class="sm:col-span-3">
                                    <select wire:model="type" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-3 text-xs text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                                        <option value="standard">Standard</option>
                                        <option value="highlight">Highlight</option>
                                        <option value="secure">Sicher (DSGVO)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center justify-end sm:justify-start gap-2">
                                <button wire:click="save" class="bg-[var(--theme-color)] text-gray-950 rounded-xl px-4 py-2 text-xs font-bold hover:brightness-105 transition-all shadow-sm">
                                    Speichern
                                </button>
                                <button wire:click="resetForm" class="bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-xl px-4 py-2 text-xs font-semibold hover:bg-gray-300 dark:hover:bg-gray-700 transition-colors">
                                    Abbrechen
                                </button>
                            </div>
                        </li>
                    @else
                        <li class="linktree-sortable-item relative flex justify-between gap-x-4 py-4 px-6 hover:bg-gray-50/80 dark:hover:bg-gray-800/40 transition-colors group" data-link-id="{{ $link->id }}">
                            <div class="flex min-w-0 gap-x-3.5 items-center">
                                <button type="button" class="link-drag-handle p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-grab active:cursor-grabbing rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="Ziehen zum Neuanordnen">
                                    <x-heroicon-m-bars-3 class="w-4 h-4" />
                                </button>

                                <div class="h-10 w-10 flex-none rounded-xl bg-[var(--theme-color-10)] flex items-center justify-center text-[var(--theme-color)]">
                                    @if($link->icon)
                                        <x-dynamic-component :component="'heroicon-o-' . $link->icon" class="h-5 w-5" />
                                    @else
                                        <x-heroicon-o-link class="h-5 w-5" />
                                    @endif
                                </div>
                                <div class="min-w-0 flex-auto">
                                    <p class="text-sm font-semibold leading-6 text-gray-900 dark:text-white flex items-center gap-2">
                                        {{ $link->title }}
                                        @if($link->type === 'secure')
                                            <span class="inline-flex items-center rounded-lg bg-yellow-400/10 px-2 py-0.5 text-xs font-medium text-yellow-600 dark:text-yellow-500 ring-1 ring-inset ring-yellow-400/20">Sicher</span>
                                        @elseif($link->type === 'highlight')
                                            <span class="inline-flex items-center rounded-lg bg-[var(--theme-color-10)] px-2 py-0.5 text-xs font-medium text-[var(--theme-color)] ring-1 ring-inset ring-[var(--theme-color-20)]">Highlight</span>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 truncate text-xs leading-5 text-gray-500 dark:text-gray-400 font-mono" title="Aufgelöst: {{ $link->resolved_url }}">{{ $link->resolved_url }}</p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-x-4">
                                <div class="hidden sm:flex sm:flex-col sm:items-end">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $link->clicks_count }}</span>
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider font-semibold">Klicks</span>
                                </div>

                                <!-- Toggle Active Status -->
                                <button wire:click="toggleActive('{{ $link->id }}')" class="{{ $link->is_active ? 'bg-[var(--theme-color)]' : 'bg-gray-300 dark:bg-gray-700' }} relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)] focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-gray-900" role="switch" aria-checked="{{ $link->is_active ? 'true' : 'false' }}" title="Status umschalten">
                                    <span class="{{ $link->is_active ? 'translate-x-5' : 'translate-x-0' }} pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                </button>

                                <!-- Edit & Delete -->
                                <div class="flex items-center gap-1 border-l border-gray-200 dark:border-gray-800 pl-3">
                                    <button wire:click="edit('{{ $link->id }}')" class="p-1.5 text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors" title="Bearbeiten">
                                        <x-heroicon-o-pencil-square class="h-4 w-4" />
                                    </button>
                                    <button wire:click="delete('{{ $link->id }}')" wire:confirm="Diesen Link wirklich löschen?" class="p-1.5 text-gray-400 hover:text-rose-500 hover:bg-rose-500/10 rounded-lg transition-colors" title="Löschen">
                                        <x-heroicon-o-trash class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </li>
                    @endif
                @empty
                    <li class="py-12 text-center text-gray-500 dark:text-gray-400 text-sm">
                        Noch keine Links vorhanden. Füge unten deinen ersten Link hinzu!
                    </li>
                @endforelse

                @if(!$editId)
                    <li class="relative flex flex-col sm:flex-row gap-4 py-4 px-6 bg-gray-50/50 dark:bg-gray-900/30 hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-colors border-t border-dashed border-gray-200 dark:border-gray-800">
                        <div class="flex-none flex items-center justify-center">
                            <div class="h-10 w-10 rounded-xl bg-[var(--theme-color-10)] flex items-center justify-center text-[var(--theme-color)]">
                                <x-heroicon-o-plus class="h-5 w-5" />
                            </div>
                        </div>
                        <div class="flex-auto grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                            <div class="sm:col-span-3">
                                <input type="text" wire:model="title" placeholder="Titel (z.B. Instagram)" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-3 text-xs text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                                @error('title') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="sm:col-span-4">
                                <input type="text" wire:model="url" placeholder="https:// oder /shop" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-3 text-xs text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                                @error('url') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <input type="text" wire:model="icon" placeholder="Icon (z.B. camera)" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-3 text-xs text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                            </div>
                            <div class="sm:col-span-3">
                                <select wire:model="type" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-3 text-xs text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                                    <option value="standard">Standard</option>
                                    <option value="highlight">Highlight</option>
                                    <option value="secure">Sicher (DSGVO)</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center justify-end sm:justify-start">
                            <button wire:click="save" class="bg-[var(--theme-color)] text-gray-950 rounded-xl px-4 py-2 text-xs font-bold hover:brightness-105 transition-all shadow-sm flex items-center gap-1.5">
                                <x-heroicon-m-plus class="w-4 h-4" />
                                Hinzufügen
                            </button>
                        </div>
                    </li>
                @endif
            </ul>
        </div>

        <!-- Tracking Links & Kampagnen-IDs Card -->
        <div class="bg-white/90 dark:bg-gray-900/60 backdrop-blur-md border border-gray-200/80 dark:border-gray-800 rounded-3xl overflow-hidden dark:shadow-2xl transition-colors duration-300">
            <div class="px-6 sm:px-8 py-5 bg-gray-50/50 dark:bg-gray-900/40 border-b border-gray-200/80 dark:border-gray-800 flex flex-col sm:flex-row justify-between sm:items-center gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                        <x-heroicon-o-chart-bar-square class="w-4 h-4" />
                    </div>
                    <div>
                        <span class="font-bold text-sm text-gray-900 dark:text-white">Tracking-Links & Kampagnen-IDs</span>
                        <span class="ml-2 inline-flex items-center rounded-md bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">DSGVO-konform</span>
                    </div>
                </div>

                <!-- 1-Klick Schnellvorlagen -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[10px] uppercase tracking-wider font-bold text-gray-400 mr-1">Schnellwahl:</span>
                    <button type="button" wire:click="applyTrackingPreset('instagram', 'bio', 'Instagram Bio')" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-pink-500/10 text-pink-600 dark:text-pink-400 hover:bg-pink-500/20 transition-colors">Instagram Bio</button>
                    <button type="button" wire:click="applyTrackingPreset('instagram', 'story', 'Instagram Story')" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-pink-500/10 text-pink-600 dark:text-pink-400 hover:bg-pink-500/20 transition-colors">Insta Story</button>
                    <button type="button" wire:click="applyTrackingPreset('tiktok', 'bio', 'TikTok Bio')" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-purple-500/10 text-purple-600 dark:text-purple-400 hover:bg-purple-500/20 transition-colors">TikTok Bio</button>
                    <button type="button" wire:click="applyTrackingPreset('newsletter', 'email', 'Newsletter Kampagne')" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20 transition-colors">Newsletter</button>
                    <button type="button" wire:click="applyTrackingPreset('qr_flyer', 'qr', 'Flyer QR-Code')" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 transition-colors">QR / Flyer</button>
                </div>
            </div>

            <!-- Quick Create / Edit Bar -->
            <div class="p-6 bg-gray-50/40 dark:bg-gray-900/20 border-b border-gray-200/80 dark:border-gray-800">
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-start">
                    <div class="sm:col-span-3">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Bezeichnung</label>
                        <input type="text" wire:model="trackingName" placeholder="z.B. Instagram Bio" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-3 text-xs text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                        @error('trackingName') <span class="text-red-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Quelle (Source)</label>
                        <select wire:model="trackingSource" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-3 text-xs text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                            <option value="instagram">instagram</option>
                            <option value="tiktok">tiktok</option>
                            <option value="facebook">facebook</option>
                            <option value="youtube">youtube</option>
                            <option value="pinterest">pinterest</option>
                            <option value="newsletter">newsletter</option>
                            <option value="google_ads">google_ads</option>
                            <option value="meta_ads">meta_ads</option>
                            <option value="qr_flyer">qr_flyer</option>
                            <option value="partner">partner</option>
                        </select>
                        @error('trackingSource') <span class="text-red-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Medium</label>
                        <select wire:model="trackingMedium" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-3 text-xs text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                            <option value="bio">bio</option>
                            <option value="story">story</option>
                            <option value="reel">reel</option>
                            <option value="post">post</option>
                            <option value="cpc">cpc (Ads)</option>
                            <option value="email">email</option>
                            <option value="qr">qr / print</option>
                            <option value="referral">referral</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Tracking-ID (optional)</label>
                        <div class="flex gap-1">
                            <input type="text" wire:model="trackingSlug" placeholder="Auto-ID" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-2.5 text-xs font-mono text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                            <button type="button" wire:click="generateTrackingId" class="px-2.5 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 text-xs" title="Tracking-ID automatisch generieren">
                                <x-heroicon-o-sparkles class="w-4 h-4" />
                            </button>
                        </div>
                        @error('trackingSlug') <span class="text-red-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Zielseite</label>
                        <div class="flex gap-2">
                            <input type="text" wire:model="trackingTarget" placeholder="/shop" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 px-3 text-xs font-mono text-gray-900 dark:text-white shadow-sm focus:ring-2 focus:ring-[var(--theme-color)] outline-none">
                            <button type="button" wire:click="saveTrackingLink" class="shrink-0 bg-[var(--theme-color)] text-gray-950 rounded-xl px-4 py-2 text-xs font-bold hover:brightness-105 transition-all shadow-sm flex items-center gap-1">
                                <x-heroicon-m-plus class="w-4 h-4" />
                                <span>{{ $trackingEditId ? 'Speichern' : 'Anlegen' }}</span>
                            </button>
                            @if($trackingEditId)
                                <button type="button" wire:click="resetTrackingForm" class="shrink-0 bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-xl px-3 py-2 text-xs font-semibold">
                                    ✕
                                </button>
                            @endif
                        </div>
                        @error('trackingTarget') <span class="text-red-400 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Hinweis-Box analog zur Quellenanalyse -->
                <div class="mt-4 p-3.5 rounded-xl bg-amber-500/5 border border-amber-500/20 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                    <strong class="text-amber-600 dark:text-amber-400 block mb-0.5 uppercase text-[9px] tracking-widest font-black">Tipp für Werbe- & Social-Media-Links</strong>
                    Verwende in Instagram-Bios, Newslettern oder Werbeanzeigen Tracking-Links wie
                    <code class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-[11px] font-mono text-gray-800 dark:text-gray-200">?utm_source=instagram&utm_medium=bio&utm_campaign=herbst2026</code>
                    oder den Kurzlink <code class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-[11px] font-mono text-gray-800 dark:text-gray-200">{{ url('/t/instagram-bio') }}</code>.
                    Das System erfasst diese Aufrufe automatisch in der <strong>Quellenanalyse & Akquise-Kanäle (Kanal-Verteilung)</strong>.
                </div>
            </div>

            <!-- Tracking Links List -->
            <ul role="list" class="divide-y divide-gray-200/80 dark:divide-gray-800">
                @forelse($trackingLinks as $trk)
                    <li class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 py-4 px-6 hover:bg-gray-50/80 dark:hover:bg-gray-800/40 transition-colors"
                        x-data="{ copiedShort: false, copiedUtm: false }">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $trk->name }}</span>
                                <span class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[11px] font-mono font-semibold text-gray-700 dark:text-gray-300">
                                    ID: {{ $trk->slug }}
                                </span>
                                <span class="inline-flex items-center rounded-md bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-600 dark:text-emerald-400">
                                    {{ $trk->utm_source }} / {{ $trk->utm_medium ?: 'link' }}
                                </span>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                <!-- Kurzlink kopieren -->
                                <div class="inline-flex items-center gap-1.5 bg-gray-100/90 dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700 rounded-lg px-2.5 py-1">
                                    <span class="text-[10px] uppercase font-bold text-gray-400">Kurzlink:</span>
                                    <a href="{{ $trk->short_url }}" target="_blank" rel="noopener noreferrer" class="font-mono text-gray-800 dark:text-gray-200 hover:underline truncate max-w-[260px]">{{ $trk->short_url }}</a>
                                    <button type="button"
                                            @click="navigator.clipboard.writeText('{{ $trk->short_url }}'); copiedShort = true; setTimeout(() => copiedShort = false, 2000)"
                                            class="ml-1 text-gray-400 hover:text-gray-900 dark:hover:text-white"
                                            title="Kurzlink kopieren">
                                        <span x-show="!copiedShort"><x-heroicon-o-clipboard-document class="w-3.5 h-3.5" /></span>
                                        <span x-show="copiedShort" x-cloak class="text-emerald-500 text-[10px] font-bold">Kopiert!</span>
                                    </button>
                                </div>

                                <!-- UTM-Link kopieren -->
                                <button type="button"
                                        @click="navigator.clipboard.writeText('{{ $trk->full_utm_url }}'); copiedUtm = true; setTimeout(() => copiedUtm = false, 2000)"
                                        class="inline-flex items-center gap-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-2.5 py-1 text-[11px] font-medium text-gray-600 dark:text-gray-300 hover:border-[var(--theme-color)] transition-colors"
                                        title="{{ $trk->full_utm_url }}">
                                    <x-heroicon-o-link class="w-3.5 h-3.5 text-gray-400" />
                                    <span x-show="!copiedUtm">UTM-URL kopieren</span>
                                    <span x-show="copiedUtm" x-cloak class="text-emerald-500 font-bold">UTM kopiert!</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between lg:justify-end gap-5 shrink-0">
                            <div class="flex items-center gap-4 text-right">
                                <div>
                                    <span class="block text-sm font-black font-mono text-gray-900 dark:text-white">{{ number_format($trk->clicks_count, 0, ',', '.') }}</span>
                                    <span class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Aufrufe</span>
                                </div>
                                <div>
                                    <span class="block text-sm font-black font-mono text-emerald-600 dark:text-emerald-400">{{ number_format($trk->unique_clicks_count, 0, ',', '.') }}</span>
                                    <span class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Unique</span>
                                </div>
                            </div>

                            <button wire:click="toggleTrackingLink('{{ $trk->id }}')" class="{{ $trk->is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-700' }} relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out" role="switch" aria-checked="{{ $trk->is_active ? 'true' : 'false' }}" title="Status umschalten">
                                <span class="{{ $trk->is_active ? 'translate-x-5' : 'translate-x-0' }} pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                            </button>

                            <div class="flex items-center gap-1 border-l border-gray-200 dark:border-gray-800 pl-3">
                                <button wire:click="editTrackingLink('{{ $trk->id }}')" class="p-1.5 text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors" title="Bearbeiten">
                                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                                </button>
                                <button wire:click="deleteTrackingLink('{{ $trk->id }}')" wire:confirm="Tracking-Link wirklich löschen?" class="p-1.5 text-gray-400 hover:text-rose-500 hover:bg-rose-500/10 rounded-lg transition-colors" title="Löschen">
                                    <x-heroicon-o-trash class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="py-8 text-center text-gray-400 text-xs">
                        Noch keine Tracking-Links angelegt. Nutze oben die Schnellwahl oder erstelle eine neue Tracking-ID.
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

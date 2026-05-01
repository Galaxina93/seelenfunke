<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-20: {{ $this->themeColorHex }}33;">
    <div class="px-4 sm:px-6 lg:px-8 py-8">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-2xl font-bold text-white">Linktree Verwaltung</h1>
                <p class="mt-2 text-sm text-gray-400">Verwalte hier die Links für deine digitale Visitenkarte. Füge neue hinzu, bearbeite bestehende oder ändere die Reihenfolge.</p>
            </div>
        </div>

        <!-- KPI Cards -->
        <dl class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="overflow-hidden rounded-xl bg-white/5 border border-white/10 px-4 py-5 shadow sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-400">Profil Aufrufe</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-white">{{ $totalVisits }}</dd>
            </div>
            <div class="overflow-hidden rounded-xl bg-white/5 border border-white/10 px-4 py-5 shadow sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-400">Gesamt Klicks</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-white">{{ $totalClicks }}</dd>
            </div>
            <div class="overflow-hidden rounded-xl bg-white/5 border border-white/10 px-4 py-5 shadow sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-400">CTR (Click-Through-Rate)</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-white">{{ $globalCtr }}%</dd>
            </div>
        </dl>

        <!-- Settings Form -->
        <div class="mt-8 bg-white/5 border border-white/10 rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-white mb-4">Design Einstellungen</h2>
            <form wire:submit.prevent="saveSettings" class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-start">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Profilbild</label>
                    <div class="flex items-center gap-4">
                        <div class="relative w-16 h-16 rounded-full bg-gray-800 overflow-hidden border-2 border-[var(--theme-color)]">
                            @if ($profileImage)
                                <img src="{{ $profileImage->temporaryUrl() }}" class="object-cover w-full h-full">
                            @elseif ($currentProfileImage)
                                <img src="{{ asset($currentProfileImage) }}" class="object-cover w-full h-full">
                            @else
                                <x-heroicon-o-user class="w-8 h-8 text-gray-500 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" />
                            @endif
                        </div>
                        <input type="file" wire:model="profileImage" id="profileImage" class="hidden" accept="image/*">
                        <div class="flex gap-2">
                            <label for="profileImage" class="cursor-pointer bg-gray-800 hover:bg-gray-700 text-white text-sm px-4 py-2 rounded-lg border border-gray-600 transition-colors">
                                Bild hochladen
                            </label>
                            @if ($currentProfileImage || $profileImage)
                                <button type="button" wire:click="deleteProfileImage" class="bg-red-500/10 hover:bg-red-500/20 text-red-500 px-3 py-2 rounded-lg border border-red-500/30 transition-colors flex items-center justify-center" title="Bild löschen">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                    </div>
                    @error('profileImage') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Theme Farbe</label>
                    <div class="flex items-center gap-3">
                        <input type="color" wire:model.live="themeColor" class="h-10 w-14 rounded bg-gray-800 border border-gray-600 p-1 cursor-pointer">
                        <input type="text" wire:model.live="themeColor" class="flex-1 rounded-md border-0 bg-gray-900 py-2 text-white shadow-sm ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-[var(--theme-color)] sm:text-sm" placeholder="#C5A059">
                    </div>
                    @error('themeColor') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-2 flex justify-end" x-data="{ saved: false }" @settings-saved.window="saved = true; setTimeout(() => saved = false, 3000)">
                    <button type="submit" class="text-black px-4 py-2 rounded-lg font-bold text-sm hover:opacity-90 transition-all flex items-center gap-2 relative overflow-hidden" :class="saved ? 'bg-green-500 text-white' : 'bg-[var(--theme-color)]'">
                        <div wire:loading wire:target="saveSettings" class="w-4 h-4 border-2 border-black border-t-transparent rounded-full animate-spin"></div>
                        
                        <span x-show="!saved" class="flex items-center gap-2">
                            Einstellungen speichern
                        </span>
                        
                        <span x-show="saved" x-cloak class="flex items-center gap-2">
                            <x-heroicon-o-check-circle class="w-5 h-5" />
                            Erfolgreich gespeichert!
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-8 bg-white/5 border border-white/10 rounded-xl overflow-hidden shadow-lg">
            <div class="p-4 bg-white/5 border-b border-white/10 font-bold text-gray-300 flex justify-between items-center">
                <span>Aktive Links</span>
                <a href="{{ route('frontend.linktree') }}" target="_blank" class="text-xs font-semibold bg-[var(--theme-color)] text-black px-3 py-1.5 rounded-lg hover:opacity-90 transition-opacity flex items-center gap-2">
                    Zum Linktree <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                </a>
            </div>
            <ul role="list" class="divide-y divide-white/10">
                @forelse($links as $link)
                    @if($editId === $link->id)
                        <li class="relative flex flex-col sm:flex-row gap-4 py-4 px-4 bg-gray-800/50 transition-colors border-y border-[var(--theme-color-20)]">
                            <div class="flex-none flex items-center justify-center">
                                <div class="h-10 w-10 rounded-lg bg-[var(--theme-color-10)] flex items-center justify-center text-[var(--theme-color)]">
                                    <x-heroicon-o-pencil-square class="h-5 w-5" />
                                </div>
                            </div>
                            <div class="flex-auto grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                                <div class="sm:col-span-3">
                                    <input type="text" wire:model="title" placeholder="Titel" class="w-full rounded-md border-0 bg-gray-900 py-1.5 text-white shadow-sm ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-[var(--theme-color)] sm:text-sm">
                                    @error('title') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-4">
                                    <input type="url" wire:model="url" placeholder="https://" class="w-full rounded-md border-0 bg-gray-900 py-1.5 text-white shadow-sm ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-[var(--theme-color)] sm:text-sm">
                                    @error('url') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <input type="text" wire:model="icon" placeholder="Icon" class="w-full rounded-md border-0 bg-gray-900 py-1.5 text-white shadow-sm ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-[var(--theme-color)] sm:text-sm">
                                </div>
                                <div class="sm:col-span-3">
                                    <select wire:model="type" class="w-full rounded-md border-0 bg-gray-900 py-1.5 text-white shadow-sm ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-[var(--theme-color)] sm:text-sm">
                                        <option value="standard">Standard</option>
                                        <option value="highlight">Highlight</option>
                                        <option value="secure">Sicher (DSGVO)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center justify-end sm:justify-start gap-2">
                                <button wire:click="save" class="bg-[var(--theme-color)] text-black rounded-md px-3 py-1.5 text-sm font-semibold hover:opacity-90 transition-opacity">
                                    Speichern
                                </button>
                                <button wire:click="resetForm" class="bg-gray-700 text-white rounded-md px-3 py-1.5 text-sm font-semibold hover:bg-gray-600 transition-colors">
                                    Abbrechen
                                </button>
                            </div>
                        </li>
                    @else
                        <li class="relative flex justify-between gap-x-6 py-4 px-4 hover:bg-white/5 transition-colors">
                            <div class="flex min-w-0 gap-x-4 items-center">
                                <div class="h-10 w-10 flex-none rounded-lg bg-[var(--theme-color-10)] flex items-center justify-center text-[var(--theme-color)]">
                                    @if($link->icon)
                                        <x-dynamic-component :component="'heroicon-o-' . $link->icon" class="h-5 w-5" />
                                    @else
                                        <x-heroicon-o-link class="h-5 w-5" />
                                    @endif
                                </div>
                                <div class="min-w-0 flex-auto">
                                    <p class="text-sm font-semibold leading-6 text-white flex items-center gap-2">
                                        {{ $link->title }}
                                        @if($link->type === 'secure')
                                            <span class="inline-flex items-center rounded-md bg-yellow-400/10 px-2 py-1 text-xs font-medium text-yellow-500 ring-1 ring-inset ring-yellow-400/20">Sicherer Link</span>
                                        @elseif($link->type === 'highlight')
                                            <span class="inline-flex items-center rounded-md bg-cyan-400/10 px-2 py-1 text-xs font-medium text-[var(--theme-color)] ring-1 ring-inset ring-[var(--theme-color-20)]">Highlight</span>
                                        @endif
                                    </p>
                                    <p class="mt-1 truncate text-xs leading-5 text-gray-400">{{ $link->url }}</p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-x-4">
                                <div class="hidden sm:flex sm:flex-col sm:items-end">
                                    <p class="text-sm leading-6 text-white">{{ $link->clicks_count }} Klicks</p>
                                </div>
                                
                                <!-- Toggle Active Status -->
                                <button wire:click="toggleActive('{{ $link->id }}')" class="{{ $link->is_active ? 'bg-[var(--theme-color)]' : 'bg-gray-600' }} relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)] focus:ring-offset-2 focus:ring-offset-gray-900" role="switch" aria-checked="false">
                                    <span class="{{ $link->is_active ? 'translate-x-5' : 'translate-x-0' }} pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                </button>

                                <!-- Edit & Delete -->
                                <div class="flex items-center gap-2 border-l border-white/10 pl-4">
                                    <button wire:click="edit('{{ $link->id }}')" class="text-gray-400 hover:text-white transition-colors">
                                        <x-heroicon-o-pencil-square class="h-5 w-5" />
                                    </button>
                                    <button wire:click="delete('{{ $link->id }}')" class="text-gray-400 hover:text-red-400 transition-colors" onclick="confirm('Wirklich löschen?') || event.stopImmediatePropagation()">
                                        <x-heroicon-o-trash class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>
                        </li>
                    @endif
                @empty
                    <li class="py-12 text-center text-gray-400">
                        Noch keine Links vorhanden.
                    </li>
                @endforelse
                
                @if(!$editId)
                    <li class="relative flex flex-col sm:flex-row gap-4 py-4 px-4 bg-gray-900/50 hover:bg-gray-900 transition-colors border-t border-dashed border-white/10">
                        <div class="flex-none flex items-center justify-center">
                            <div class="h-10 w-10 rounded-lg bg-[var(--theme-color-10)] flex items-center justify-center text-[var(--theme-color)]">
                                <x-heroicon-o-plus class="h-5 w-5" />
                            </div>
                        </div>
                        <div class="flex-auto grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                            <div class="sm:col-span-3">
                                <input type="text" wire:model="title" placeholder="Titel (z.B. Instagram)" class="w-full rounded-md border-0 bg-gray-800 py-1.5 text-white shadow-sm ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-[var(--theme-color)] sm:text-sm">
                                @error('title') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="sm:col-span-4">
                                <input type="url" wire:model="url" placeholder="https://" class="w-full rounded-md border-0 bg-gray-800 py-1.5 text-white shadow-sm ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-[var(--theme-color)] sm:text-sm">
                                @error('url') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <input type="text" wire:model="icon" placeholder="Icon" class="w-full rounded-md border-0 bg-gray-800 py-1.5 text-white shadow-sm ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-[var(--theme-color)] sm:text-sm">
                            </div>
                            <div class="sm:col-span-3">
                                <select wire:model="type" class="w-full rounded-md border-0 bg-gray-800 py-1.5 text-white shadow-sm ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-[var(--theme-color)] sm:text-sm">
                                    <option value="standard">Standard</option>
                                    <option value="highlight">Highlight</option>
                                    <option value="secure">Sicher (DSGVO)</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center justify-end sm:justify-start">
                            <button wire:click="save" class="bg-[var(--theme-color)] text-black rounded-md px-3 py-1.5 text-sm font-semibold hover:opacity-90 transition-opacity">
                                Hinzufügen
                            </button>
                        </div>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</div>

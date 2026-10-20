<div :class="isMaximized ? 'configurator-studio-form p-4 sm:p-6 space-y-6 custom-scrollbar shrink-0' : 'p-6 space-y-8 max-w-2xl mx-auto'"
     :style="isMaximized ? 'flex-shrink: 0;' : ''"
     class="{{ $context === 'preview' ? 'opacity-60 grayscale-[0.5] pointer-events-none' : '' }}">

    @if($context !== 'preview')
        {{-- HAUPT-BUTTONS (Zentriert nebeneinander) --}}
        <div class="flex flex-col items-center gap-3">
            <div class="flex items-center justify-center gap-3 sm:gap-6 w-full">
                {{-- 1. SYMBOLE --}}
                <button type="button"
                        @click="toggleTab('symbols')"
                        class="flex flex-col items-center justify-center w-24 sm:w-28 h-20 sm:h-24 p-2 rounded-2xl border transition-colors duration-200 group select-none cursor-pointer"
                        :class="activeTab === 'symbols'
                            ? 'border-green-500 ring-2 ring-green-500/25 bg-green-500/10 text-green-600 dark:text-green-400 shadow-[0_0_15px_rgba(34,197,94,0.15)]'
                            : '{{ $isDark ? 'border-gray-800 bg-gray-900/90 text-gray-400 hover:border-gray-700 hover:text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900 shadow-sm' }}'">
                    <x-heroicon-o-sparkles class="w-6 h-6 sm:w-7 sm:h-7 mb-1.5 transition-transform group-hover:scale-110" />
                    <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider">Symbole</span>
                </button>

                @if($configSettings['allow_logo'])
                    {{-- 2. EIGENE DATEIEN --}}
                    <button type="button"
                            @click="toggleTab('files')"
                            class="flex flex-col items-center justify-center w-24 sm:w-28 h-20 sm:h-24 p-2 rounded-2xl border transition-colors duration-200 group select-none relative cursor-pointer"
                            :class="activeTab === 'files'
                                ? 'border-green-500 ring-2 ring-green-500/25 bg-green-500/10 text-green-600 dark:text-green-400 shadow-[0_0_15px_rgba(34,197,94,0.15)]'
                                : '{{ $isDark ? 'border-gray-800 bg-gray-900/90 text-gray-400 hover:border-gray-700 hover:text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900 shadow-sm' }}'">
                        @if(count($uploaded_files) > 0)
                            <span class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-primary text-gray-900 text-[10px] font-black flex items-center justify-center shadow-md">
                                {{ count($uploaded_files) }}
                            </span>
                        @endif
                        <x-heroicon-o-photo class="w-6 h-6 sm:w-7 sm:h-7 mb-1.5 transition-transform group-hover:scale-110" />
                        <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider">Eigene Dateien</span>
                    </button>
                @endif

                {{-- 3. DESIGN SICHERN (DISKETTE) --}}
                <button type="button"
                        @click="saveDraft"
                        class="flex flex-col items-center justify-center w-24 sm:w-28 h-20 sm:h-24 p-2 rounded-2xl border transition-colors duration-200 group select-none cursor-pointer"
                        :class="isDraftSaving
                            ? 'border-green-500 ring-2 ring-green-500/25 bg-green-500/10 text-green-600 dark:text-green-400 shadow-[0_0_15px_rgba(34,197,94,0.15)]'
                            : '{{ $isDark ? 'border-gray-800 bg-gray-900/90 text-gray-400 hover:border-[#C5A059] hover:text-[#C5A059]' : 'border-slate-200 bg-white text-slate-600 hover:border-[#C5A059] hover:text-[#C5A059] shadow-sm' }}'">
                    <template x-if="!isDraftSaving">
                        <div class="flex flex-col items-center">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7 mb-1.5 transition-transform group-hover:scale-110" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider">Design Sichern</span>
                        </div>
                    </template>
                    <template x-if="isDraftSaving">
                        <div class="flex flex-col items-center text-green-600 dark:text-green-400 animate-pulse">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7 mb-1.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-tight text-center leading-tight">Gespeichert!</span>
                        </div>
                    </template>
                </button>
            </div>

            {{-- GESPEICHERTES DESIGN LADEN (WENN DRAFT EXISTIERT) --}}
            <div x-show="draftExists" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="pt-1">
                <button type="button"
                        @click="loadDraft"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full border text-xs font-bold transition-all shadow-sm group {{ $isDark ? 'border-[#C5A059]/40 bg-[#C5A059]/10 text-[#C5A059] hover:bg-[#C5A059]/20' : 'border-[#C5A059]/50 bg-[#C5A059]/10 text-[#C5A059] hover:bg-[#C5A059]/20 hover:shadow-md' }}">
                    <template x-if="!isDraftLoading">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-[#C5A059] group-hover:-translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Gespeichertes Design Laden
                        </span>
                    </template>
                    <template x-if="isDraftLoading">
                        <span class="inline-flex items-center gap-1.5 text-green-600 dark:text-green-400 font-bold animate-pulse">
                            <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Design geladen!
                        </span>
                    </template>
                </button>
            </div>
        </div>

        {{-- AUSKLAPPBARER BEREICH: TABS CONTAINER (SYMBOLE / EIGENE DATEIEN) --}}
        <div x-show="activeTab !== null"
             x-cloak
             x-transition:enter="transition-opacity ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="pt-2">

            {{-- 1. SYMBOL-BIBLIOTHEK --}}
            <div x-show="activeTab === 'symbols'"
                 x-transition:enter="transition-opacity ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-2 {{ $isDark ? 'text-gray-200' : 'text-slate-900' }}">
                    <span class="w-8 h-px {{ $isDark ? 'bg-gray-700' : 'bg-slate-200' }}"></span>
                    Symbol-Bibliothek
                </h3>
                <button type="button" @click="activeTab = null" class="text-xs text-gray-400 hover:text-gray-200 px-2 py-1 rounded-lg transition-colors cursor-pointer">
                    Schließen ✕
                </button>
            </div>
            <div class="border rounded-[2rem] p-5 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-slate-50 border-slate-200' }}">
                <div class="flex gap-2 p-1 overflow-x-auto custom-scrollbar border-b pb-4 mb-4 {{ $isDark ? 'border-gray-800' : 'border-slate-200' }}">
                    @foreach($vectors as $category => $items)
                        <button wire:click="$set('selectedCategory', '{{ $category }}')" class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all shadow-sm border {{ $selectedCategory === $category ? ($isDark ? 'bg-[#C5A059] text-gray-900 border-[#C5A059] shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'bg-[#C5A059] text-white border-[#C5A059] shadow-md') : ($isDark ? 'bg-gray-800 text-gray-400 border-gray-700 hover:text-gray-200 hover:border-gray-600' : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200 hover:text-slate-700') }}">
                            {{ $category }} ({{ count($items) }})
                        </button>
                    @endforeach
                </div>

                <div class="grid grid-cols-4 sm:grid-cols-6 gap-3">
                    @php
                        $activeVectors = $vectors[$selectedCategory] ?? [];
                    @endphp

                    @forelse($activeVectors as $v)
                        <button wire:click="addStandardVector('{{ $v['file'] }}')"
                                title="{{ $v['name'] }}"
                                aria-label="{{ $v['name'] }}"
                                class="aspect-square rounded-2xl border shadow-sm hover:border-primary hover:shadow-[0_0_15px_rgba(197,160,89,0.3)] hover:scale-105 transition-all p-3 flex flex-col items-center justify-center group relative cursor-pointer {{ $isDark ? 'bg-amber-500/10 border-amber-500/30' : 'bg-amber-50/50 border-amber-200' }}">
                            <img src="{{ asset('shop/product/configurator/vectors/'.$v['file']) }}"
                                 alt="{{ $v['name'] }}"
                                 class="w-full h-full object-contain opacity-80 group-hover:opacity-100 transition-opacity pointer-events-none">
                            <span class="absolute -bottom-7 left-1/2 -translate-x-1/2 px-2 py-0.5 rounded-md text-[10px] font-bold whitespace-nowrap shadow-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity duration-150 z-30 {{ $isDark ? 'bg-gray-800 text-gray-100 border border-gray-700' : 'bg-gray-900 text-white' }}">
                                {{ $v['name'] }}
                            </span>
                        </button>
                    @empty
                        <div class="col-span-full text-center text-xs py-10 {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
                            <div class="w-12 h-12 rounded-full mb-3 mx-auto flex items-center justify-center {{ $isDark ? 'bg-gray-800' : 'bg-slate-100' }}">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                            </div>
                            Keine Motive in dieser Kategorie vorhanden.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        @if($configSettings['allow_logo'])
            {{-- AUSKLAPPBARER BEREICH: EIGENE DATEIEN --}}
            <div x-show="activeTab === 'files'"
                 x-transition:enter="transition-opacity ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-2 {{ $isDark ? 'text-gray-200' : 'text-slate-900' }}">
                        <span class="w-8 h-px {{ $isDark ? 'bg-gray-700' : 'bg-slate-200' }}"></span>
                        Eigene Logos & Bilder
                    </h3>
                    <div class="flex items-center gap-3">
                        <span class="text-[10px] font-bold px-2 py-1 rounded-md {{ count($uploaded_files) >= 10 ? 'bg-red-100 text-red-600' : ($isDark ? 'bg-gray-800 text-gray-400' : 'bg-slate-100 text-slate-500') }}">
                            {{ count($uploaded_files) }} / 10 Dateien
                        </span>
                        <button type="button" @click="activeTab = null" class="text-xs text-gray-400 hover:text-gray-200 px-2 py-1 rounded-lg transition-colors cursor-pointer">
                            Schließen ✕
                        </button>
                    </div>
                </div>

                <div class="border rounded-xl p-4 flex gap-4 items-start shadow-sm {{ $isDark ? 'bg-blue-900/10 border-blue-900/30' : 'bg-blue-50/80 border-blue-100' }}">
                    <div class="shrink-0 text-blue-500 mt-0.5">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-xs leading-relaxed {{ $isDark ? 'text-blue-400/80' : 'text-blue-800/80' }}">
                        Laden Sie Ihre eigenen Medien hoch. Position, Größe und Rotation können Sie direkt oben im Vorschaubild anpassen. Wir prüfen jede Datei manuell vor der Gravur.
                    </p>
                </div>

                <div wire:key="upload-zone-wrapper-{{ count($uploaded_files) }}" class="relative">

                    <div x-show="uploadError" x-cloak class="mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-xs font-bold text-center animate-pulse">
                        <span x-text="uploadError"></span>
                    </div>

                    @if($errors->has('new_files') || $errors->has('new_files.*'))
                        <div class="mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-xs font-bold text-center">
                            {{ $errors->first('new_files') ?: $errors->first('new_files.*') }}
                        </div>
                    @endif

                    @if(count($uploaded_files) < 10)
                        <div x-on:dragover.prevent="isDropping = true"
                             x-on:dragleave.prevent="isDropping = false"
                             x-on:drop.prevent="handleDrop($event)"
                             :class="isDropping ? 'border-primary bg-primary/5 scale-[1.02]' : '{{ $isDark ? 'border-gray-700 bg-gray-900' : 'border-slate-200 bg-slate-50/50' }}'"
                             class="group border-2 border-dashed rounded-[2rem] p-10 transition-all duration-300 flex flex-col items-center justify-center text-center cursor-pointer hover:border-primary/50 relative overflow-hidden"
                             @click="$refs.fileInput.click()">

                            <input type="file" x-ref="fileInput" wire:model.live="new_files" multiple accept=".jpg,.jpeg,.png,.webp,.svg,.pdf" class="hidden" x-on:change="handleInput($event)">

                            <div class="w-12 h-12 rounded-2xl shadow-sm border flex items-center justify-center transition-all mb-3 relative z-10 {{ $isDark ? 'bg-gray-800 border-gray-700 text-gray-500 group-hover:text-primary' : 'bg-white border-slate-100 text-slate-400 group-hover:text-primary' }}">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                                </svg>
                            </div>
                            <p class="text-[10px] font-black uppercase tracking-widest relative z-10 {{ $isDark ? 'text-gray-300' : 'text-slate-900' }}">Datei hochladen oder hierher ziehen</p>
                            <p class="text-[9px] mt-1 relative z-10 {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">PDF, JPG, PNG (Max. 10 Dateien)</p>
                        </div>

                        <div wire:loading wire:target="new_files" class="absolute inset-0 backdrop-blur-sm rounded-[2rem] border z-20 flex items-center justify-center {{ $isDark ? 'bg-gray-950/80 border-gray-800' : 'bg-white/90 border-slate-100' }}">
                            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 flex flex-col items-center gap-3 text-center">
                                <svg class="animate-spin h-8 w-8 text-primary" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                <span class="text-xs font-black uppercase tracking-wide {{ $isDark ? 'text-gray-300' : 'text-slate-800' }}">
                                    Wird verarbeitet...
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="border-2 rounded-[2rem] p-6 text-center {{ $isDark ? 'border-red-900/30 bg-red-900/10' : 'border-red-100 bg-red-50' }}">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mx-auto mb-2 shadow-sm {{ $isDark ? 'bg-gray-900 text-red-500' : 'bg-white text-red-500' }}">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <p class="text-xs font-bold {{ $isDark ? 'text-red-400' : 'text-red-800' }}">Maximale Anzahl (10) erreicht.</p>
                            <p class="text-[10px] mt-1 {{ $isDark ? 'text-red-500' : 'text-red-600' }}">Bitte löschen Sie eine Datei, um eine neue hochzuladen.</p>
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-3">
                    @foreach($uploaded_files as $index => $path)
                        @php
                            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg']);
                            $isActive = $this->isLogoActive($path);
                        @endphp

                        <div x-data="{ width: 0, height: 0, init() { if('{{ $isImage }}') { let img = new Image(); img.onload = () => { this.width = img.width; this.height = img.height; }; img.src = '{{ asset('storage/'.$path) }}'; } } }"
                             class="flex flex-col p-3 rounded-2xl border transition-all {{ $isDark ? 'bg-gray-900' : 'bg-white' }} {{ $isActive ? 'border-primary shadow-md' : ($isDark ? 'border-gray-800' : 'border-slate-100') }}">

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4 overflow-hidden">
                                    <div class="w-12 h-12 rounded-xl overflow-hidden flex items-center justify-center border shrink-0 relative {{ $isDark ? 'bg-gray-800 border-gray-700' : 'bg-slate-50 border-slate-100' }}">
                                        @if($isImage)
                                            <img src="{{ asset('storage/'.$path) }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="flex flex-col items-center justify-center {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
                                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                                <span class="text-[8px] font-bold uppercase mt-0.5">{{ $ext }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[11px] font-bold truncate {{ $isDark ? 'text-gray-200' : 'text-slate-800' }}" title="{{ basename($path) }}">{{ basename($path) }}</p>

                                        @if($isImage)
                                            <button wire:click="toggleLogo('saved', '{{ $path }}')" class="text-[9px] font-black uppercase tracking-tighter mt-1 {{ $isActive ? 'text-primary' : ($isDark ? 'text-gray-500 hover:text-primary transition-colors' : 'text-slate-400 hover:text-primary transition-colors') }}">
                                                {{ $isActive ? 'In Vorschau aktiv' : 'Als Vorschau einblenden' }}
                                            </button>
                                        @else
                                            <div class="text-[9px] font-medium mt-1 flex items-center gap-1 {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
                                                <svg class="w-3 h-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                Erfolgreich angehängt
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <button wire:click="removeFile({{ $index }})" class="p-2 {{ $isDark ? 'text-gray-600 hover:text-rose-500' : 'text-slate-300 hover:text-rose-500' }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>

                            {{-- SMARTES AMPEL-SYSTEM FÜR BILDQUALITÄT --}}
                            {{-- 1. Gute Auflösung (Kürzeste Seite >= 350px oder Fläche >= 200.000px) --}}
                            <template x-if="{{ $isActive ? 'true' : 'false' }} && '{{ $ext }}' !== 'svg' && width > 0 && (Math.min(width, height) >= 350 || (width * height) >= 200000)">
                                <div class="mt-2 border p-2 rounded-xl text-[9px] font-medium flex items-center justify-between gap-2 {{ $isDark ? 'bg-emerald-950/30 border-emerald-900/40 text-emerald-300' : 'bg-emerald-50/80 border-emerald-200 text-emerald-800' }}">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <span class="truncate font-semibold">✓ Bildqualität für Gravur geeignet</span>
                                    </div>
                                    <span class="text-[8px] opacity-75 shrink-0 hidden sm:inline">Manuelle Prüfung vor Gravur</span>
                                </div>
                            </template>

                            {{-- 2. Geringe Auflösung (Kürzeste Seite < 350px) --}}
                            <template x-if="{{ $isActive ? 'true' : 'false' }} && '{{ $ext }}' !== 'svg' && width > 0 && Math.min(width, height) < 350 && (width * height) < 200000">
                                <div class="mt-2 border p-2.5 rounded-xl text-[9px] font-medium flex items-start gap-2 {{ $isDark ? 'bg-amber-950/30 border-amber-900/40 text-amber-300' : 'bg-amber-50/90 border-amber-200 text-amber-900' }}">
                                    <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold">💡 Tipp für beste Laserschärfe:</p>
                                        <p class="mt-0.5 opacity-90 leading-tight">Dieses Foto hat eine eher geringe Auflösung (<span x-text="width + '×' + height"></span>px). Für ein gestochen scharfes Ergebnis empfehlen wir ein größeres Originalfoto direkt aus deiner Galerie.</p>
                                        <p class="text-[8px] opacity-75 mt-1">Keine Sorge: Unser Team prüft jedes Bild vor dem Gravieren noch einmal persönlich.</p>
                                    </div>
                                </div>
                            </template>

                            {{-- 3. SVG Vektorgrafik --}}
                            <template x-if="{{ $isActive ? 'true' : 'false' }} && '{{ $ext }}' === 'svg'">
                                <div class="mt-2 border p-2 rounded-xl text-[9px] font-medium flex items-center justify-between gap-2 {{ $isDark ? 'bg-purple-950/30 border-purple-900/40 text-purple-300' : 'bg-purple-50/80 border-purple-200 text-purple-800' }}">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <svg class="w-3.5 h-3.5 text-purple-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                        <span class="truncate font-semibold">✓ Vektorgrafik (Verlustfrei skalierbar)</span>
                                    </div>
                                    <span class="text-[8px] opacity-75 shrink-0 hidden sm:inline">Optimale Laserschärfe</span>
                                </div>
                            </template>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        </div>
    @endif

    {{-- BESONDERE WÜNSCHE AN DIE MANUFAKTUR --}}
    <div class="pt-6 border-t {{ $isDark ? 'border-gray-800' : 'border-slate-100' }}" x-data="{ count: @entangle('notes').live?.length || 0 }">
        <label class="text-[10px] font-black uppercase tracking-[0.2em] mb-3 flex justify-between {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
            <span>Besondere Wünsche an die Manufaktur</span>
            <span class="text-[9px]" :class="count >= 400 ? 'text-red-500 font-bold' : '{{ $isDark ? 'text-gray-600' : 'text-slate-300' }}'" x-text="count + ' / 400'">0 / 400</span>
        </label>
        <textarea wire:model="notes"
                  maxlength="400"
                  x-on:input="count = $el.value.length"
                  rows="5"
                  class="w-full border-none rounded-[1.5rem] p-5 text-sm font-medium focus:ring-2 focus:ring-primary/20 transition-all resize-none shadow-inner {{ $isDark ? 'bg-gray-900 text-gray-300 placeholder-gray-600' : 'bg-slate-50 text-slate-700' }}"
                  placeholder="Geben Sie hier Details zu Ihrer gewünschten Gravur an..."
                  {{ $context === 'preview' ? 'readonly' : '' }}></textarea>
    </div>

</div>

<div :class="isMaximized ? 'configurator-studio-form p-4 sm:p-5 space-y-5 custom-scrollbar shrink-0' : 'px-4 sm:px-6 pt-3 sm:pt-4 pb-4 sm:pb-5 space-y-4 sm:space-y-5 max-w-2xl mx-auto'"
     :style="isMaximized ? 'flex-shrink: 0;' : ''"
     class="{{ $context === 'preview' ? 'opacity-60 grayscale-[0.5] pointer-events-none' : '' }}">

    @if($context !== 'preview')
        {{-- ZENTRIERTES OVERLAY-MODAL FÜR SYMBOLE, SPRÜCHE & EIGENE DATEIEN (Wird direkt aus der 2D-Ansicht geöffnet) --}}
        <div x-show="activeTab !== null"
             x-cloak
             @keydown.escape.window="if (activeTab !== null) activeTab = null"
             @click.self="activeTab = null"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[2147483646] flex items-center justify-center p-3 sm:p-6 bg-black/50 backdrop-blur-sm">

            <div @click.stop
                 class="w-full max-w-2xl max-h-[86vh] overflow-y-auto custom-scrollbar rounded-[2rem] border p-5 sm:p-6 shadow-2xl {{ $isDark ? 'bg-gray-950 border-gray-800 text-gray-100' : 'bg-white border-gray-200 text-gray-900' }}">

                {{-- 1. SYMBOL-BIBLIOTHEK --}}
                <div x-show="activeTab === 'symbols'"
                     x-transition:enter="transition-opacity ease-out duration-150"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-2 {{ $isDark ? 'text-gray-200' : 'text-slate-900' }}">
                            <x-heroicon-o-sparkles class="w-4 h-4 text-[#C5A059]" />
                            <span>Symbol-Bibliothek</span>
                        </h3>
                        <button type="button" @click="activeTab = null" class="text-xs font-bold text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 px-2.5 py-1 rounded-xl border border-transparent hover:border-gray-200 dark:hover:border-gray-800 transition-colors cursor-pointer">
                            Schließen ✕
                        </button>
                    </div>
                    <div class="border rounded-[1.5rem] p-4 sm:p-5 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-slate-50 border-slate-200' }}">
                        <div class="flex gap-2 p-1 overflow-x-auto custom-scrollbar border-b pb-3.5 mb-4 {{ $isDark ? 'border-gray-800' : 'border-slate-200' }}">
                            @foreach($vectors as $category => $items)
                                <button wire:click="$set('selectedCategory', '{{ $category }}')" class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all shadow-sm border cursor-pointer {{ $selectedCategory === $category ? ($isDark ? 'bg-[#C5A059] text-gray-900 border-[#C5A059] shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'bg-[#C5A059] text-white border-[#C5A059] shadow-md') : ($isDark ? 'bg-gray-800 text-gray-400 border-gray-700 hover:text-gray-200 hover:border-gray-600' : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200 hover:text-slate-700') }}">
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
                                        @click="activeTab = null"
                                        title="{{ $v['name'] }}"
                                        aria-label="{{ $v['name'] }}"
                                        class="aspect-square rounded-2xl border shadow-sm hover:border-primary hover:shadow-[0_0_15px_rgba(197,160,89,0.3)] hover:scale-105 transition-all p-3 flex flex-col items-center justify-center group relative cursor-pointer {{ $isDark ? 'bg-amber-500/10 border-amber-500/30' : 'bg-amber-50/50 border-amber-200' }}">
                                    <img src="{{ $v['url'] ?? asset('shop/product/configurator/vectors/'.$v['file']) }}"
                                         alt="{{ $v['name'] }}"
                                         class="w-full h-full object-contain opacity-80 group-hover:opacity-100 transition-opacity pointer-events-none">
                                    <span class="absolute -bottom-7 left-1/2 -translate-x-1/2 px-2 py-0.5 rounded-md text-[10px] font-bold whitespace-nowrap shadow-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity duration-150 z-30 {{ $isDark ? 'bg-gray-800 text-gray-100 border border-gray-700' : 'bg-gray-900 text-white' }}">
                                        {{ $v['name'] }}
                                    </span>
                                </button>
                            @empty
                                <div class="col-span-full text-center text-xs py-10 {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
                                    Keine Motive in dieser Kategorie vorhanden.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- 1b. SPRUCH- & WIDMUNGS-BIBLIOTHEK („KREATIV-STARTHILFE“) --}}
                @if(!empty($quotes))
                    <div x-show="activeTab === 'quotes'"
                         x-data="{ activeQuoteCat: @js($selectedQuoteCategory ?: array_key_first($quotes)) }"
                         x-transition:enter="transition-opacity ease-out duration-150"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-2 {{ $isDark ? 'text-gray-200' : 'text-slate-900' }}">
                                <x-heroicon-o-chat-bubble-bottom-center-text class="w-4 h-4 text-[#C5A059]" />
                                <span>Sprüche & Widmungen</span>
                            </h3>
                            <button type="button" @click="activeTab = null" class="text-xs font-bold text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 px-2.5 py-1 rounded-xl border border-transparent hover:border-gray-200 dark:hover:border-gray-800 transition-colors cursor-pointer">
                                Schließen ✕
                            </button>
                        </div>

                        <div class="border rounded-[1.5rem] p-4 sm:p-5 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-slate-50 border-slate-200' }}">
                            <div class="flex gap-2 p-1 overflow-x-auto custom-scrollbar border-b pb-3.5 mb-4 {{ $isDark ? 'border-gray-800' : 'border-slate-200' }}">
                                @foreach($quotes as $qCategory => $qItems)
                                    <button type="button"
                                            @click="activeQuoteCat = @js($qCategory)"
                                            :class="activeQuoteCat === @js($qCategory)
                                                ? '{{ $isDark ? 'bg-[#C5A059] text-gray-900 border-[#C5A059] shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'bg-[#C5A059] text-white border-[#C5A059] shadow-md' }}'
                                                : '{{ $isDark ? 'bg-gray-800 text-gray-400 border-gray-700 hover:text-gray-200 hover:border-gray-600' : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200 hover:text-slate-700' }}'"
                                            class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all shadow-sm border cursor-pointer">
                                        {{ $qCategory }} ({{ count($qItems) }})
                                    </button>
                                @endforeach
                            </div>

                            @foreach($quotes as $qCategory => $qItems)
                                <div x-show="activeQuoteCat === @js($qCategory)"
                                     class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-72 overflow-y-auto custom-scrollbar pr-1">
                                    @forelse($qItems as $quoteItem)
                                        <button type="button"
                                                @click="applyQuoteToStage(@js($quoteItem['text'])); activeTab = null"
                                                class="text-left p-3.5 rounded-2xl border transition-all group cursor-pointer flex flex-col justify-between gap-1.5 hover:border-[#C5A059] hover:shadow-[0_0_15px_rgba(197,160,89,0.2)] {{ $isDark ? 'bg-gray-950/70 border-gray-800 text-gray-200' : 'bg-white border-slate-200 text-slate-800 shadow-xs' }}">
                                            @if(!empty($quoteItem['title']))
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-[10px] font-black uppercase tracking-widest text-[#C5A059]">
                                                        {{ $quoteItem['title'] }}
                                                    </span>
                                                    <span class="text-[10px] font-bold opacity-0 group-hover:opacity-100 transition-opacity text-[#C5A059]">
                                                        + Übernehmen
                                                    </span>
                                                </div>
                                            @endif
                                            <p class="text-xs sm:text-sm font-serif leading-snug">
                                                „{{ $quoteItem['text'] }}“
                                            </p>
                                        </button>
                                    @empty
                                        <div class="col-span-full text-center text-xs py-8 {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
                                            Keine Sprüche in dieser Kategorie vorhanden.
                                        </div>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($configSettings['allow_logo'])
                    {{-- 2. EIGENE DATEIEN & BILDER --}}
                    <div x-show="activeTab === 'files'"
                         x-data="{ showUploadInfo: false }"
                         x-transition:enter="transition-opacity ease-out duration-150"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         class="space-y-4">
                        <div class="flex justify-between items-center gap-2">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-2 {{ $isDark ? 'text-gray-200' : 'text-slate-900' }}">
                                    <x-heroicon-o-photo class="w-4 h-4 text-[#C5A059]" />
                                    <span>Eigene Dateien & Logos</span>
                                </h3>
                                <button type="button"
                                        @click="showUploadInfo = !showUploadInfo"
                                        title="Hinweise zur Gravurprüfung & zum Urheberrecht anzeigen"
                                        class="w-5 h-5 rounded-full border border-[#C5A059]/60 bg-[#C5A059]/10 text-[#C5A059] hover:bg-[#C5A059] hover:text-white text-[11px] font-black flex items-center justify-center transition-colors cursor-pointer shrink-0">
                                    !
                                </button>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="text-[10px] font-bold px-2 py-1 rounded-md {{ count($uploaded_files) >= 10 ? 'bg-red-100 text-red-600' : ($isDark ? 'bg-gray-800 text-gray-400' : 'bg-slate-100 text-slate-500') }}">
                                    {{ count($uploaded_files) }} / 10 Dateien
                                </span>
                                <button type="button" @click="activeTab = null" class="text-xs font-bold text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 px-2.5 py-1 rounded-xl border border-transparent hover:border-gray-200 dark:hover:border-gray-800 transition-colors cursor-pointer">
                                    Schließen ✕
                                </button>
                            </div>
                        </div>

                        {{-- KOMPAKTES INFO-POPOVER HINTER DEM !-ICON (STATT DAUERHAFTEM BLAUEN BANNER) --}}
                        <div x-show="showUploadInfo"
                             x-cloak
                             x-transition
                             class="border rounded-2xl p-3.5 text-xs leading-relaxed space-y-1.5 {{ $isDark ? 'bg-gray-900 border-gray-800 text-gray-300' : 'bg-slate-50 border-slate-200 text-slate-700' }}">
                            <p>
                                <strong>Manufaktur-Qualitätsprüfung:</strong> Position, Größe, Drehung und Zuschnitt kannst du direkt im Vorschaubild anpassen. Wir prüfen jede hochgeladene Datei vor der Gravur manuell auf optimale Laserschärfe.
                            </p>
                            <p class="text-[11px] pt-1 border-t {{ $isDark ? 'border-gray-800 text-gray-400' : 'border-slate-200 text-slate-600' }}">
                                <strong>⚖️ Urheber- &amp; Markenrecht:</strong> Mit dem Upload bestätigst du, dass du alle erforderlichen Rechte an den Motiven besitzt (keine geschützten Markenlogos oder Figuren wie z. B. Pokémon/Disney ohne Lizenz) und uns gemäß <a href="/agb#urheberrecht-freistellung" target="_blank" class="underline font-semibold text-[#C5A059] hover:opacity-80">§ 3 Abs. 7–9 unserer AGB</a> von Ansprüchen Dritter freistellst.
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
                                     :class="isDropping ? 'border-primary bg-primary/5 scale-[1.01]' : '{{ $isDark ? 'border-gray-700 bg-gray-900' : 'border-slate-200 bg-slate-50/50' }}'"
                                     class="group border-2 border-dashed rounded-[1.5rem] p-6 sm:p-8 transition-all duration-300 flex flex-col items-center justify-center text-center cursor-pointer hover:border-primary/50 relative overflow-hidden"
                                     @click="$refs.fileInput.click()">

                                    <input type="file" x-ref="fileInput" wire:model.live="new_files" multiple accept=".jpg,.jpeg,.png,.webp,.svg,.pdf" class="hidden" x-on:change="handleInput($event)">

                                    <div class="w-11 h-11 rounded-2xl shadow-sm border flex items-center justify-center transition-all mb-2.5 relative z-10 {{ $isDark ? 'bg-gray-800 border-gray-700 text-gray-400 group-hover:text-primary' : 'bg-white border-slate-100 text-slate-400 group-hover:text-primary' }}">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                                        </svg>
                                    </div>
                                    <p class="text-[10px] font-black uppercase tracking-widest relative z-10 {{ $isDark ? 'text-gray-200' : 'text-slate-900' }}">Datei auswählen oder hierher ziehen</p>
                                    <p class="text-[9px] mt-1 relative z-10 {{ $isDark ? 'text-gray-400' : 'text-slate-500' }}">JPG, PNG, WEBP, SVG, PDF (Max. 10 Dateien) • Manuelle Qualitätsprüfung inklusive</p>
                                    <p @click.stop class="text-[9px] mt-1.5 relative z-10 {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
                                        Nur eigene/lizenzfreie Motive ohne fremdes Markenrecht (<a href="/agb#urheberrecht-freistellung" target="_blank" class="underline hover:text-[#C5A059]">§ 3 AGB</a>)
                                    </p>
                                </div>

                                <div wire:loading wire:target="new_files" class="absolute inset-0 backdrop-blur-sm rounded-[1.5rem] border z-20 flex items-center justify-center {{ $isDark ? 'bg-gray-950/80 border-gray-800' : 'bg-white/90 border-slate-100' }}">
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
                                <div class="border-2 rounded-[1.5rem] p-6 text-center {{ $isDark ? 'border-red-900/30 bg-red-900/10' : 'border-red-100 bg-red-50' }}">
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
                                                    <button wire:click="toggleLogo('saved', '{{ $path }}')" class="text-[9px] font-black uppercase tracking-tighter mt-1 cursor-pointer {{ $isActive ? 'text-primary' : ($isDark ? 'text-gray-500 hover:text-primary transition-colors' : 'text-slate-400 hover:text-primary transition-colors') }}">
                                                        {{ $isActive ? '✓ In Vorschau aktiv' : '+ In Vorschau einblenden' }}
                                                    </button>
                                                @else
                                                    <div class="text-[9px] font-medium mt-1 flex items-center gap-1 {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
                                                        <svg class="w-3 h-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                        Erfolgreich angehängt
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <button wire:click="removeFile({{ $index }})" class="p-2 cursor-pointer {{ $isDark ? 'text-gray-600 hover:text-rose-500' : 'text-slate-300 hover:text-rose-500' }}">
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

                        @if(count($uploaded_files) > 0)
                            <div class="pt-2 flex justify-end">
                                <button type="button"
                                        @click="activeTab = null"
                                        class="px-5 py-2.5 rounded-xl bg-[#C5A059] hover:bg-[#b08d48] text-white text-xs font-bold uppercase tracking-wider shadow-md transition-all cursor-pointer">
                                    Fertig &amp; zur Ansicht
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- BESONDERE WÜNSCHE AN DIE MANUFAKTUR --}}
    <div class="mt-0!" x-data="{ count: @entangle('notes').live?.length || 0 }">
        <label class="text-[10px] font-black uppercase tracking-[0.2em] mb-2 flex justify-between {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
            <span>Besondere Wünsche an die Manufaktur</span>
            <span class="text-[9px]" :class="count >= 400 ? 'text-red-500 font-bold' : '{{ $isDark ? 'text-gray-600' : 'text-slate-300' }}'" x-text="count + ' / 400'">0 / 400</span>
        </label>
        <textarea wire:model="notes"
                  maxlength="400"
                  x-on:input="count = $el.value.length"
                  :rows="isMaximized ? 3 : 4"
                  rows="4"
                  class="w-full border-none rounded-[1.25rem] p-4 text-sm font-medium focus:ring-2 focus:ring-primary/20 transition-all resize-none shadow-inner {{ $isDark ? 'bg-gray-900 text-gray-300 placeholder-gray-600' : 'bg-slate-50 text-slate-700' }}"
                  placeholder="Geben Sie hier Details zu Ihrer gewünschten Gravur an..."
                  {{ $context === 'preview' ? 'readonly' : '' }}></textarea>
    </div>

    {{-- VERPACKUNGS-UPGRADES & BUNDLE-EXTRAS (IN DER RECHTEN STUDIO-SPALTE BZW. UNTER DEN WÜNSCHEN) --}}
    @if($context !== 'preview' && $context !== 'template_admin' && $context !== 'order_edit' && $this->type === 'physical' && !$isDigital && !$isService && count($this->availableAddons) > 0)
        <div class="pt-5 border-t space-y-2.5 animate-fade-in-up {{ $isDark ? 'border-gray-800' : 'border-slate-100' }}"
             x-data="{ previewAddon: null }"
             @keydown.escape.window="if (previewAddon) previewAddon = null">
            <span class="block text-[10px] font-black uppercase tracking-widest {{ $isDark ? 'text-gray-400' : 'text-gray-500' }}">
                🎁 Verpackung &amp; Extras (Optional hinzubuchen)
            </span>
            <div class="grid grid-cols-1 gap-2.5">
                @foreach($this->availableAddons as $addon)
                    @php
                        $isAddonSelected = in_array($addon['id'], $selectedAddons, true);
                        $isGiftWrapOption = \App\Models\Product\Product::isGiftWrapAddon($addon);
                        $addonFormattedPrice = number_format(((int) $addon['price_cents']) / 100, 2, ',', '.');
                        $addonImageUrl = $addon['image_url'] ?? \App\Models\Product\Product::resolveAddonImageUrl($addon);
                    @endphp
                    <div wire:click="toggleAddon('{{ $addon['id'] }}')"
                         role="button"
                         tabindex="0"
                         @keydown.enter.prevent="$wire.toggleAddon('{{ $addon['id'] }}')"
                         @keydown.space.prevent="$wire.toggleAddon('{{ $addon['id'] }}')"
                         class="w-full text-left border rounded-xl p-3 transition-all cursor-pointer group {{ $isAddonSelected ? ($isDark ? 'bg-gray-900 border-primary ring-1 ring-primary/50 shadow-sm' : 'bg-white border-primary ring-1 ring-primary/40 shadow-xs') : ($isDark ? 'bg-gray-900 border-gray-800 hover:border-primary/40' : 'bg-white border-gray-200 hover:border-primary/40 shadow-2xs') }}">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                {{-- Checkbox links neben dem Bild --}}
                                <span class="w-5 h-5 rounded flex items-center justify-center border shrink-0 transition-colors {{ $isAddonSelected ? 'bg-primary border-primary text-gray-900 shadow-2xs' : ($isDark ? 'border-gray-600 bg-gray-950 group-hover:border-primary/60' : 'border-gray-300 bg-white group-hover:border-primary/60') }}">
                                    @if($isAddonSelected)
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                </span>

                                {{-- Vorschaubild mit Klick-Zoom (Lightbox) --}}
                                @if($addonImageUrl)
                                    <div @click.stop="previewAddon = @js([
                                            'id' => $addon['id'],
                                            'name' => $addon['name'],
                                            'description' => $addon['description'] ?? '',
                                            'price' => $addonFormattedPrice,
                                            'image' => $addonImageUrl,
                                        ])"
                                         title="Bild groß anzeigen"
                                         class="relative w-12 h-12 sm:w-14 sm:h-14 rounded-xl overflow-hidden border shrink-0 cursor-zoom-in group/img {{ $isAddonSelected ? 'border-primary ring-2 ring-primary/30' : ($isDark ? 'border-gray-700 bg-gray-950 hover:border-primary/60' : 'border-gray-200 bg-gray-50 hover:border-primary/60') }}">
                                        <img src="{{ $addonImageUrl }}" alt="{{ $addon['name'] }}" class="w-full h-full object-cover group-hover/img:scale-110 transition-transform duration-300">
                                        <span class="absolute bottom-0.5 right-0.5 w-4 h-4 rounded bg-black/65 text-white flex items-center justify-center backdrop-blur-2xs opacity-85 group-hover/img:opacity-100 transition-all shadow-xs">
                                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/>
                                            </svg>
                                        </span>
                                    </div>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <span class="text-xs sm:text-sm font-bold block leading-tight {{ $isDark ? 'text-gray-100' : 'text-gray-800' }}">
                                        {{ $addon['name'] }}
                                    </span>
                                </div>
                            </div>
                            <span class="font-bold text-primary bg-primary/10 px-2.5 py-1 rounded-lg text-xs whitespace-nowrap shrink-0">
                                + {{ $addonFormattedPrice }}&nbsp;€
                            </span>
                        </div>

                        @if($isAddonSelected && $isGiftWrapOption)
                            <div @click.stop class="mt-2.5 pt-2.5 border-t grid grid-cols-2 gap-2 {{ $isDark ? 'border-gray-800' : 'border-gray-100' }}">
                                <button type="button"
                                        wire:click.stop="setGiftWrapMode('wrapped')"
                                        class="px-2.5 py-1.5 rounded-lg border text-[11px] font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer {{ ($giftWrapMode ?? 'wrapped') !== 'loose' ? ($isDark ? 'bg-gray-800 border-primary text-white shadow-2xs' : 'bg-[#F5F1E8] border-[#C5A059] text-gray-900 shadow-2xs') : ($isDark ? 'bg-gray-950 border-gray-800 text-gray-400 hover:border-gray-700 hover:text-gray-200' : 'bg-gray-50 border-gray-200 text-gray-600 hover:border-gray-300 hover:text-gray-900') }}">
                                    @if(($giftWrapMode ?? 'wrapped') !== 'loose')
                                        <span class="text-[#C5A059] font-black">✓</span>
                                    @endif
                                    <span>Fertig verpackt</span>
                                </button>
                                <button type="button"
                                        wire:click.stop="setGiftWrapMode('loose')"
                                        class="px-2.5 py-1.5 rounded-lg border text-[11px] font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer {{ ($giftWrapMode ?? 'wrapped') === 'loose' ? ($isDark ? 'bg-gray-800 border-primary text-white shadow-2xs' : 'bg-[#F5F1E8] border-[#C5A059] text-gray-900 shadow-2xs') : ($isDark ? 'bg-gray-950 border-gray-800 text-gray-400 hover:border-gray-700 hover:text-gray-200' : 'bg-gray-50 border-gray-200 text-gray-600 hover:border-gray-300 hover:text-gray-900') }}">
                                    @if(($giftWrapMode ?? 'wrapped') === 'loose')
                                        <span class="text-[#C5A059] font-black">✓</span>
                                    @endif
                                    <span>Papier nur mitliefern</span>
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- LIGHTBOX MODAL FÜR GROSSANSICHT DES VERPACKUNGSBILDES (IMMER HELLMODUS + FUNKEN-ANIMATION) --}}
            <style>
                @keyframes sfAddonSparkFloatMain {
                    0%, 100% { transform: translateY(0) scale(1) rotate(0deg); filter: drop-shadow(0 0 12px rgba(197,160,89,0.55)); }
                    50% { transform: translateY(-7px) scale(1.08) rotate(8deg); filter: drop-shadow(0 0 22px rgba(197,160,89,0.85)); }
                }
                @keyframes sfAddonSparkFloatSubA {
                    0%, 100% { transform: translate(0, 0) scale(0.85) rotate(0deg); opacity: 0.65; }
                    50% { transform: translate(-5px, -9px) scale(1.18) rotate(-15deg); opacity: 1; }
                }
                @keyframes sfAddonSparkFloatSubB {
                    0%, 100% { transform: translate(0, 0) scale(0.9) rotate(0deg); opacity: 0.55; }
                    50% { transform: translate(6px, -7px) scale(1.15) rotate(18deg); opacity: 1; }
                }
                @keyframes sfAddonHighlightShimmer {
                    0%, 100% { box-shadow: 0 8px 25px -6px rgba(197, 160, 89, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.9); }
                    50% { box-shadow: 0 12px 32px -4px rgba(197, 160, 89, 0.28), inset 0 1px 0 rgba(255, 255, 255, 1); }
                }
            </style>
            <template x-teleport="body">
                <div x-show="previewAddon !== null"
                     x-cloak
                     style="display: none;"
                     x-transition.opacity.duration.200ms
                     @click="previewAddon = null"
                     class="fixed inset-0 z-[2147483647] flex items-center justify-center p-4 sm:p-6 bg-[#262016]/45 backdrop-blur-md">
                    <div @click.stop
                         x-show="previewAddon !== null"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95 translate-y-3"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         class="relative w-full max-w-md rounded-[2rem] overflow-hidden shadow-[0_25px_70px_-15px_rgba(120,90,40,0.35)] border border-[#e6d7bc] bg-[#fdfbf7] text-gray-900 p-6 sm:p-7">

                        <div class="pointer-events-none absolute -top-20 left-1/2 -translate-x-1/2 w-72 h-40 bg-gradient-to-b from-[#C5A059]/20 via-amber-200/10 to-transparent blur-2xl"></div>

                        <button type="button"
                                @click="previewAddon = null"
                                class="absolute top-4 right-4 z-20 w-9 h-9 rounded-full flex items-center justify-center bg-white hover:bg-[#f7f1e6] text-gray-500 hover:text-gray-900 border border-[#e6d7bc] shadow-sm transition-all hover:rotate-90 cursor-pointer"
                                title="Schließen">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>

                        <div class="relative z-10 flex flex-col items-center text-center">
                            {{-- Animierte Seelenfunke-Sterne (wie unter "Teile deine Erfahrung") --}}
                            <div class="relative w-16 h-12 mb-1 flex items-center justify-center select-none pointer-events-none">
                                <div class="absolute inset-0 bg-[#C5A059]/20 rounded-full blur-xl animate-pulse"></div>
                                <svg style="animation: sfAddonSparkFloatMain 4s ease-in-out infinite;" class="w-9 h-9 text-[#C5A059] relative z-10" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 1L14.8 9.2L23 12L14.8 14.8L12 23L9.2 14.8L1 12L9.2 9.2L12 1Z"/>
                                </svg>
                                <svg style="animation: sfAddonSparkFloatSubA 3.2s ease-in-out infinite 0.4s;" class="w-4 h-4 text-amber-400 absolute -top-0.5 right-0.5 z-20" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0L14.59 9.41L24 12L14.59 14.59L12 24L9.41 14.59L0 12L9.41 9.41L12 0Z"/>
                                </svg>
                                <svg style="animation: sfAddonSparkFloatSubB 3.6s ease-in-out infinite 1.1s;" class="w-3.5 h-3.5 text-amber-500 absolute bottom-0 left-0.5 z-20" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0L14.59 9.41L24 12L14.59 14.59L12 24L9.41 14.59L0 12L9.41 9.41L12 0Z"/>
                                </svg>
                            </div>

                            <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-[#a37e36] mb-1">
                                Manufaktur-Upgrade &bull; Großansicht
                            </span>

                            <div class="w-full aspect-square max-h-64 rounded-2xl overflow-hidden mt-2 mb-5 flex items-center justify-center bg-gradient-to-b from-white to-[#faf6ef] border border-[#eadcc4] p-4 shadow-[inset_0_2px_10px_rgba(197,160,89,0.08)]">
                                <img :src="previewAddon?.image"
                                     :alt="previewAddon?.name"
                                     class="w-full h-full object-contain rounded-xl drop-shadow-[0_10px_20px_rgba(0,0,0,0.12)]">
                            </div>

                            <div class="w-full flex flex-col items-center gap-2 mb-4">
                                <h4 class="font-serif text-xl sm:text-2xl font-bold text-gray-900 leading-tight" x-text="previewAddon?.name"></h4>
                                <span class="inline-flex items-center gap-1.5 text-xs font-extrabold px-3.5 py-1 rounded-full bg-[#fdf6e7] text-[#9a732a] border border-[#e5cf9e] shadow-sm">
                                    + <span x-text="previewAddon?.price"></span>&nbsp;€
                                </span>
                            </div>

                            <div x-show="previewAddon?.description"
                                 style="animation: sfAddonHighlightShimmer 4s ease-in-out infinite;"
                                 class="relative w-full rounded-2xl bg-gradient-to-br from-[#fffdf9] via-[#faf4e6] to-[#f5ead4] border border-[#e2cca0] px-4 py-3.5 mb-5 text-left overflow-hidden">
                                <div class="flex items-start gap-2.5">
                                    <div class="shrink-0 mt-0.5 w-6 h-6 rounded-full bg-[#C5A059]/15 border border-[#C5A059]/30 flex items-center justify-center text-[#a37e36]">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 1L14.8 9.2L23 12L14.8 14.8L12 23L9.2 14.8L1 12L9.2 9.2L12 1Z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-[#9a732a] mb-0.5">
                                            Details zum Extra
                                        </div>
                                        <p class="text-xs sm:text-sm text-gray-800 leading-relaxed font-medium"
                                           x-text="previewAddon?.description"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="w-full flex items-center gap-2.5">
                                <button type="button"
                                        @click="previewAddon = null"
                                        class="flex-1 py-2.5 px-4 rounded-xl text-xs font-bold uppercase tracking-wider border border-[#e2d3b5] bg-white text-gray-700 hover:bg-[#f7f1e6] transition cursor-pointer">
                                    Schließen
                                </button>
                                <button type="button"
                                        @click="if (previewAddon?.id) { $wire.toggleAddon(previewAddon.id); previewAddon = null; }"
                                        class="flex-1 py-2.5 px-4 rounded-xl text-xs font-bold uppercase tracking-wider bg-gradient-to-r from-[#C5A059] to-[#b38b40] hover:from-[#b89249] hover:to-[#9e7830] text-white transition cursor-pointer shadow-md shadow-[#C5A059]/25">
                                    Auswahl umschalten
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    @endif

    {{-- EXPRESS-OPTION (IN DER RECHTEN STUDIO-SPALTE BZW. UNTER VERPACKUNG & EXTRAS) --}}
    @if($context !== 'preview' && $context !== 'template_admin' && $context !== 'order_edit' && $this->type === 'physical' && $this->product->isPersonalizable())
        @php
            $expressPercent = (float)shop_setting('express_surcharge_percent', 20.0);
            $expressMin = (int)shop_setting('express_surcharge_min', 500);

            $currentConfigTotal = $currentPrice * $qty;
            $calculatedExpress = (int) round($currentConfigTotal * ($expressPercent / 100));
            $expressCharge = max($expressMin, $calculatedExpress);

            $formattedPrice = number_format($expressCharge / 100, 2, ',', '.');
            $formattedPercent = number_format($expressPercent, 0, ',', '.');
            $formattedMin = number_format($expressMin / 100, 2, ',', '.');

            $shopCapacityLevel = (int) \Illuminate\Support\Facades\Cache::get('shop_capacity_level', shop_setting('shop_capacity_level', 0));
            $expressDisabled = $shopCapacityLevel >= 2;
        @endphp
        <div class="pt-4 border-t relative w-full transition-all duration-300 animate-fade-in-up {{ $isDark ? 'border-gray-800' : 'border-slate-100' }}">
            <label class="block border rounded-xl p-3.5 transition-all group relative overflow-hidden {{ $expressDisabled ? ($isDark ? 'bg-gray-900 border-gray-800 opacity-70 cursor-not-allowed' : 'bg-gray-50 border-gray-200 opacity-70 cursor-not-allowed') : ($is_express ? ($isDark ? 'bg-amber-900/20 border-amber-500/50 ring-1 ring-amber-500/50 cursor-pointer' : 'bg-amber-50 border-amber-300 ring-1 ring-amber-300 cursor-pointer') : ($isDark ? 'bg-gray-900 border-gray-800 hover:border-primary/50 cursor-pointer' : 'bg-white border-gray-200 hover:border-primary/50 shadow-2xs hover:shadow-sm cursor-pointer')) }}">
                @if($expressDisabled)
                    <div class="absolute inset-0 z-10 cursor-not-allowed" title="Express aktuell deaktiviert." @click.prevent></div>
                @endif

                <div class="flex items-start gap-3">
                    <div class="pt-0.5">
                        <input wire:model.live="is_express" type="checkbox" class="w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary {{ $expressDisabled ? 'opacity-50' : 'cursor-pointer' }}" {{ $expressDisabled ? 'disabled' : '' }}>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-center flex-wrap gap-2">
                            <h3 class="text-xs sm:text-sm font-bold flex items-center gap-1.5 {{ $isDark ? 'text-gray-100' : 'text-gray-800' }}">
                                <x-shop.express-info label="🚀 Eiliges Geschenk (Express)?" />
                                @if($is_express)
                                    <span class="text-[9px] bg-primary text-gray-900 px-2 py-0.5 rounded-full uppercase tracking-wide font-black animate-pulse">Aktiv</span>
                                @endif
                            </h3>

                            <span class="font-bold text-primary bg-primary/10 px-2 py-0.5 rounded-lg text-xs whitespace-nowrap">
                                + {{ $formattedPrice }}&nbsp;€ <span class="text-[9px] text-gray-400 font-normal">Aufpreis</span>
                            </span>
                        </div>

                        <p class="text-[11px] mt-1 leading-snug {{ $isDark ? 'text-gray-400' : 'text-gray-500' }}">
                            Priorisierte Fertigung in der Manufaktur ({{ $formattedPercent }}% Basis-Aufschlag, mind. {{ $formattedMin }}&nbsp;€).
                        </p>

                        @if($expressDisabled)
                            <div class="mt-2 text-[10px] font-bold text-amber-600 bg-amber-50/10 p-2 border border-amber-500/20 rounded-lg flex items-start gap-1.5">
                                <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                Aufgrund hohem Produktionsvolumen kurzzeitig deaktiviert.
                            </div>
                        @endif
                    </div>
                </div>
            </label>
        </div>
    @endif

</div>

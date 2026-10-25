@if($currentStep === 2)
    <div class="space-y-6 md:space-y-8 animate-fade-in-up">

        @php
            $cardClassStep2 = "bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8 transition-colors duration-300";
        @endphp

        {{-- ========================================== --}}
        {{-- BEREICH FÜR DIGITALE DATEI (NUR WENN DIGITAL) --}}
        {{-- ========================================== --}}
        @if($type === 'digital')
            <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-500/20 backdrop-blur-md rounded-[2.5rem] p-6 sm:p-8 relative overflow-hidden group dark:shadow-inner transition-colors">
                {{-- Deko Hintergrund --}}
                <div class="hidden dark:block absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-full opacity-50 blur-[50px] pointer-events-none"></div>

                <div class="relative z-10">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="p-3 bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-500/30 rounded-xl dark:shadow-[0_0_15px_rgba(59,130,246,0.3)] shrink-0">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">Digitale Produktdatei</h2>
                            <p class="text-[10px] font-black uppercase tracking-widest text-blue-600 dark:text-blue-300 mt-1">Die Datei, die der Kunde erhält.</p>
                        </div>
                    </div>

                    @if($product->digital_download_path)
                        {{-- STATUS: DATEI VORHANDEN --}}
                        @php
                            $digAbs = \Illuminate\Support\Facades\Storage::disk('local')->exists($product->digital_download_path)
                                ? \Illuminate\Support\Facades\Storage::disk('local')->path($product->digital_download_path)
                                : null;
                            $digSize = $digAbs && is_file($digAbs) ? (int) @filesize($digAbs) : 0;
                            $digSizeStr = $digSize >= 1048576 ? number_format($digSize / 1048576, 2, ',', '.') . ' MB' : ($digSize > 0 ? number_format($digSize / 1024, 1, ',', '.') . ' KB' : 'Unbekannt');
                            $digMod = $digAbs && is_file($digAbs) ? date('d.m.Y H:i', (int) @filemtime($digAbs)) : '-';
                        @endphp
                        <div x-data="{ copied: false }" class="bg-white dark:bg-gray-950 p-5 rounded-2xl border border-blue-200 dark:border-blue-500/30 dark:shadow-inner flex items-center justify-between">
                            <div class="flex items-center gap-5 min-w-0">
                                <div class="w-14 h-14 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl flex items-center justify-center text-gray-600 dark:text-gray-500 font-black text-[10px] uppercase tracking-widest dark:shadow-inner shrink-0">
                                    {{ pathinfo($product->digital_download_path, PATHINFO_EXTENSION) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="font-bold text-gray-900 dark:text-white text-sm truncate max-w-[200px] sm:max-w-md">
                                            {{ $product->digital_filename ?? basename($product->digital_download_path) }}
                                        </p>
                                        <span class="text-[9px] font-mono font-bold text-blue-600 dark:text-blue-400 bg-blue-500/10 px-1.5 py-0.5 rounded">{{ $digSizeStr }}</span>
                                        <button type="button"
                                                @click="navigator.clipboard.writeText(@js($product->digital_download_path)); copied = true; setTimeout(() => copied = false, 2000)"
                                                class="text-gray-500 hover:text-blue-500 transition-colors p-0.5 cursor-pointer"
                                                title="Speicherpfad kopieren">
                                            <x-heroicon-s-clipboard-document x-show="!copied" class="w-3.5 h-3.5 text-gray-500 hover:text-blue-500 transition-colors" />
                                            <x-heroicon-s-check x-show="copied" style="display: none;" class="w-3.5 h-3.5 text-emerald-500" />
                                        </button>
                                        <div class="relative group/diginfo inline-flex items-center">
                                            <span class="w-4 h-4 rounded-full bg-blue-500/10 border border-blue-500/30 text-blue-600 dark:text-blue-400 hover:bg-blue-500 hover:text-white flex items-center justify-center text-[9px] font-black cursor-help transition-colors">!</span>
                                            <div class="absolute bottom-full left-0 mb-2 hidden group-hover/diginfo:block z-50 w-64 p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-xl text-[10px] font-mono text-gray-700 dark:text-gray-300 space-y-1 pointer-events-none">
                                                <div class="font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1 mb-1">Geschützte Digital-Datei</div>
                                                <div><span class="text-gray-400">Pfad:</span> <span class="break-all">private/{{ $product->digital_download_path }}</span></div>
                                                <div><span class="text-gray-400">Größe:</span> {{ $digSizeStr }}</div>
                                                <div><span class="text-gray-400">Geändert:</span> {{ $digMod }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-400 flex items-center gap-2 mt-2 dark:drop-shadow-[0_0_8px_currentColor]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Sicher gespeichert ({{ $digSizeStr }})
                                    </p>
                                </div>
                            </div>
                            <button wire:click="removeDigitalFile" class="text-gray-500 hover:text-red-600 dark:hover:text-red-400 p-3 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-xl transition-all border border-transparent hover:border-red-200 dark:hover:border-red-500/30 dark:shadow-inner" title="Datei löschen">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    @else
                        {{-- STATUS: KEINE DATEI (UPLOAD DROPZONE) --}}
                        <div
                            x-data="{ isUploading: false, isDropping: false }"
                            x-on:livewire-upload-start="isUploading = true"
                            x-on:livewire-upload-finish="isUploading = false"
                            x-on:livewire-upload-error="isUploading = false"
                            x-on:dragover.prevent="isDropping = true"
                            x-on:dragleave.prevent="isDropping = false"
                            x-on:drop.prevent="isDropping = false;
                                                $refs.digitalInput.files = $event.dataTransfer.files;
                                                $refs.digitalInput.dispatchEvent(new Event('change', { bubbles: true }));"
                            class="border-2 border-dashed rounded-2xl transition-all group/upload relative dark:shadow-inner"
                            :class="isDropping ? 'border-blue-500 bg-blue-50 dark:bg-blue-500/10 dark:shadow-[0_0_30px_rgba(59,130,246,0.2)]' : 'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 hover:bg-gray-50 dark:hover:bg-gray-900 hover:border-blue-400 dark:hover:border-blue-500/50'">

                            <label class="flex flex-col items-center justify-center py-10 cursor-pointer w-full h-full">
                                <input type="file" wire:model.live="new_digital_file" class="hidden" x-ref="digitalInput">

                                <div x-show="!isUploading" class="text-center">
                                    <svg class="w-10 h-10 mx-auto text-gray-400 dark:text-gray-600 group-hover/upload:text-blue-500 dark:group-hover/upload:text-blue-400 mb-4 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 group-hover/upload:text-gray-900 dark:group-hover/upload:text-white transition-colors">
                                        <span x-show="!isDropping">Datei hier ablegen oder klicken</span>
                                        <span x-show="isDropping" class="text-blue-500 dark:text-blue-400">Ja, hier loslassen!</span>
                                    </p>
                                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-600 mt-2">PDF, ZIP, MP3, MP4 (Max. 100MB)</p>
                                </div>

                                {{-- Loading State --}}
                                <div x-show="isUploading" class="flex flex-col items-center">
                                    <svg class="animate-spin h-8 w-8 text-blue-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-blue-500 dark:text-blue-400 dark:drop-shadow-[0_0_8px_currentColor]">Verschlüsselt und lädt...</span>
                                </div>
                            </label>
                        </div>
                        @error('new_digital_file') <p class="text-[10px] text-red-600 dark:text-red-400 mt-3 font-bold uppercase tracking-widest">{{ $message }}</p> @enderror
                    @endif
                </div>
            </div>
        @endif

        {{-- ========================================== --}}
        {{-- PRODUKTBILDER & VIDEOS (MARKETING) --}}
        {{-- ========================================== --}}
        <div class="{{ $cardClassStep2 }}">
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white mb-2 tracking-wide">Galerie & Marketing</h2>
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-8">Diese Bilder werden im Shop angezeigt (Vorschau).</p>

            @error('new_media')
            <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 px-5 py-4 rounded-xl shadow-inner mb-6 text-[10px] font-black uppercase tracking-widest" role="alert">
                <span class="block sm:inline">{{ $message }}</span>
            </div>
            @enderror

            {{-- BILDER UPLOAD & AUTO-SAISON-HAUPTBILD SWITCH --}}
            @php
                $autoSeasonalEnabled = filter_var($configSettings['auto_seasonal_main_image'] ?? $product->isPersonalizable(), FILTER_VALIDATE_BOOLEAN);
                $activeSeasonalMeta = $autoSeasonalEnabled ? $product->resolveMainImageMeta(null, true) : null;
            @endphp

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 border-b border-gray-200 dark:border-gray-800 pb-3">
                <h3 class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400 dark:bg-gray-600"></span> Produktbilder (Mind. 1) *
                </h3>

                @if($is_personalizable)
                    <div class="flex items-center gap-3">
                        <span class="text-[10px] font-black uppercase tracking-widest {{ $autoSeasonalEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400' }}">
                            Automatisch Bilder setzen
                        </span>
                        <button type="button"
                                wire:click="toggleAutoSeasonalMainImage"
                                role="switch"
                                aria-checked="{{ $autoSeasonalEnabled ? 'true' : 'false' }}"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $autoSeasonalEnabled ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-700' }}"
                                title="Hauptbild automatisch nach Jahreszeit / Feiertag setzen">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $autoSeasonalEnabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                    </div>
                @endif
            </div>

            @if($is_personalizable)
                <div class="mb-6 p-4 rounded-2xl border transition-colors {{ $autoSeasonalEnabled ? 'bg-emerald-50/70 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-500/30' : 'bg-gray-50 dark:bg-gray-950/60 border-gray-200 dark:border-gray-800' }}">
                    <div class="flex items-start sm:items-center gap-3.5">
                        @if($autoSeasonalEnabled && !empty($activeSeasonalMeta['url']))
                            <img src="{{ $activeSeasonalMeta['url'] }}"
                                 alt="{{ $activeSeasonalMeta['label'] ?? $product->name }}"
                                 class="w-12 h-12 rounded-xl object-cover border border-emerald-300 dark:border-emerald-500/40 shrink-0 shadow-sm">
                        @else
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ $autoSeasonalEnabled ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-gray-200 dark:bg-gray-800 text-gray-500' }}">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            @if($autoSeasonalEnabled)
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">
                                        Automatisches Saison- & Feiertags-Hauptbild aktiv
                                    </span>
                                    @if(!empty($activeSeasonalMeta['label']))
                                        <span class="px-2 py-0.5 rounded-md bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[9px] font-black uppercase tracking-wider">
                                            Aktuell: {{ $activeSeasonalMeta['label'] }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                                    Das Hauptbild dieses Produkts wird auf der Startseite, im Shop und auf der Produktseite automatisch passend zur aktuellen Jahreszeit bzw. zum anstehenden Feiertag aus den hinterlegten <a href="{{ route('admin.product-templates') }}" class="underline font-bold hover:text-emerald-600">Produktvorlagen</a> bzw. Saisonbildern gesetzt. Deine unten hochgeladenen Produktbilder bleiben als weitere Galeriebilder erhalten.
                                </p>
                            @else
                                <span class="text-xs font-bold text-gray-800 dark:text-gray-200">
                                    Manuelles Hauptbild aktiv
                                </span>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed">
                                    Es wird dauerhaft das unten als „Hauptbild“ markierte Galeriebild als erstes Produktbild im Shop verwendet. Schalte den Switch ein, um das Hauptbild automatisch nach Jahreszeit & Feiertag zu steuern.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6 mb-10">
                @foreach($product->media_gallery ?? [] as $index => $media)
                    @if(is_array($media) && $media['type'] === 'image')
                        @php
                            $isAiImg = filter_var($media['is_ai_generated'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        @endphp
                        <div class="relative aspect-square rounded-[1.5rem] overflow-hidden group border-2 dark:shadow-inner {{ $index === 0 ? 'border-[var(--theme-color)] dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700' }} bg-gray-100 dark:bg-gray-950">
                            <img src="{{ \App\Models\Product\ProductTemplate::resolveImageUrl($media['path']) }}"
                                 @if($isAiImg) data-is-ai-generated="true" @endif
                                 class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition-opacity">

                            @if(!empty($media['holiday']))
                                <span class="absolute top-3 left-3 z-10 bg-gray-900/80 backdrop-blur-sm text-white text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md border border-white/15">
                                    {{ $media['holiday'] }}
                                </span>
                            @endif

                            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-3 z-10">
                                @if($index !== 0)
                                    <button wire:click="setMainImage({{ $index }})" class="bg-white/90 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-white p-2.5 rounded-xl hover:bg-[var(--theme-color)] hover:border-[var(--theme-color)] hover:text-white dark:hover:text-gray-900 shadow-lg transition-all hover:scale-110" title="Als Hauptbild setzen"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg></button>
                                @endif
                                <button wire:click="removeMedia({{ $index }})" class="bg-white/90 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-red-500 dark:text-red-400 p-2.5 rounded-xl hover:bg-red-500 hover:border-red-500 hover:text-white shadow-lg transition-all hover:scale-110" title="Löschen"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                            </div>

                            @if($index === 0)
                                <span class="absolute bottom-3 left-3 z-20 bg-[var(--theme-color)] text-white dark:text-gray-900 text-[8px] font-black uppercase tracking-widest px-2 py-1 rounded-md shadow-sm">Hauptbild</span>
                            @endif

                            {{-- KI-Generiert Haken / Toggle unten rechts --}}
                            <button type="button"
                                    wire:click="toggleMediaAiGenerated({{ $index }})"
                                    class="absolute bottom-3 right-3 z-20 px-2 py-1 rounded-md backdrop-blur-sm text-[8px] font-semibold tracking-wider flex items-center gap-1 border transition-all {{ $isAiImg ? 'bg-black/70 text-white border-white/25 shadow-sm' : 'bg-black/40 text-white/60 border-white/10 hover:bg-black/70 hover:text-white' }}"
                                    title="Klicken, um 'KI-generiert' Hinweis für dieses Bild ein-/auszuschalten">
                                <span class="w-3 h-3 rounded-sm flex items-center justify-center border {{ $isAiImg ? 'bg-emerald-500 border-emerald-400 text-white' : 'border-white/40' }}">
                                    @if($isAiImg)
                                        <svg class="w-2 h-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                </span>
                                <span>KI-generiert</span>
                            </button>
                        </div>
                    @endif
                @endforeach

                {{-- DROPZONE FÜR BILDER --}}
                <div class="aspect-square rounded-[1.5rem] relative overflow-hidden transition-all duration-300 group dark:shadow-inner"
                     x-data="{ isUploading: false, isDropping: false }"
                     x-on:livewire-upload-start="isUploading = true"
                     x-on:livewire-upload-finish="isUploading = false"
                     x-on:livewire-upload-error="isUploading = false"
                     x-on:dragover.prevent="isDropping = true"
                     x-on:dragleave.prevent="isDropping = false"
                     x-on:drop.prevent="isDropping = false;
                                        $refs.imageInput.files = $event.dataTransfer.files;
                                        $refs.imageInput.dispatchEvent(new Event('change', { bubbles: true }));"
                     :class="isDropping ? 'bg-[var(--theme-color-10)] border-2 border-[var(--theme-color)] border-solid dark:shadow-[0_0_20px_rgba(197,160,89,0.2)]' : 'bg-gray-50 dark:bg-gray-950 border-2 border-dashed border-gray-200 dark:border-gray-800 hover:border-gray-400 dark:hover:border-gray-600'">

                    <label class="w-full h-full cursor-pointer flex flex-col items-center justify-center text-gray-500 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">
                        <input type="file" multiple wire:model.live="new_media" class="hidden" accept="image/*" x-ref="imageInput">

                        <div x-show="!isUploading" class="text-center">
                            <svg class="w-8 h-8 mb-3 mx-auto opacity-50 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            <span class="text-[9px] font-black uppercase tracking-widest" x-text="isDropping ? 'Loslassen!' : 'Bilder +'"></span>
                        </div>

                        <div x-show="isUploading" class="absolute inset-0 bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm flex flex-col items-center justify-center z-10">
                            <svg class="animate-spin h-6 w-6 text-[var(--theme-color)] mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="text-[9px] font-black uppercase tracking-widest text-[var(--theme-color)] dark:drop-shadow-[0_0_8px_currentColor]">Lädt...</span>
                        </div>
                    </label>
                </div>
            </div>

            {{-- VIDEOS UPLOAD --}}
            <h3 class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400 mb-5 border-b border-gray-200 dark:border-gray-800 pb-3 flex items-center gap-2 mt-4">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 dark:bg-gray-600"></span> Produktvideos
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6">
                @foreach($product->media_gallery ?? [] as $index => $media)
                    @if(is_array($media) && $media['type'] === 'video')
                        <div class="relative aspect-square rounded-[1.5rem] overflow-hidden border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-950 dark:shadow-inner group">
                            <video src="{{ asset('storage/'.$media['path']) }}" class="w-full h-full object-cover opacity-60 group-hover:opacity-90 transition-opacity"></video>
                            <button wire:click="removeMedia({{ $index }})" class="absolute top-3 right-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-red-500 dark:text-red-400 p-2 rounded-xl hover:bg-red-500 hover:text-white hover:border-red-500 opacity-0 group-hover:opacity-100 transition-all shadow-md hover:scale-110"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                        </div>
                    @endif
                @endforeach

                {{-- DROPZONE FÜR VIDEO --}}
                <div class="aspect-square rounded-[1.5rem] relative overflow-hidden transition-all duration-300 group dark:shadow-inner"
                     x-data="{ isUploading: false, isDropping: false }"
                     x-on:livewire-upload-start="isUploading = true"
                     x-on:livewire-upload-finish="isUploading = false"
                     x-on:livewire-upload-error="isUploading = false"
                     x-on:dragover.prevent="isDropping = true"
                     x-on:dragleave.prevent="isDropping = false"
                     x-on:drop.prevent="isDropping = false;
                                        $refs.videoInput.files = $event.dataTransfer.files;
                                        $refs.videoInput.dispatchEvent(new Event('change', { bubbles: true }));"
                     :class="isDropping ? 'bg-[var(--theme-color-10)] border-2 border-[var(--theme-color)] border-solid dark:shadow-[0_0_20px_rgba(197,160,89,0.2)]' : 'bg-gray-50 dark:bg-gray-950 border-2 border-dashed border-gray-200 dark:border-gray-800 hover:border-gray-400 dark:hover:border-gray-600'">

                    <label class="w-full h-full cursor-pointer flex flex-col items-center justify-center text-gray-500 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">
                        <input type="file" wire:model.live="new_video" class="hidden" accept="video/mp4,video/quicktime" x-ref="videoInput">

                        <div x-show="!isUploading" class="text-center">
                            <svg class="w-8 h-8 mb-3 mx-auto opacity-50 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <span class="text-[9px] font-black uppercase tracking-widest" x-text="isDropping ? 'Loslassen!' : 'Video +'"></span>
                        </div>

                        <div x-show="isUploading" class="absolute inset-0 bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm flex flex-col items-center justify-center z-10">
                            <svg class="animate-spin h-6 w-6 text-[var(--theme-color)] mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="text-[9px] font-black uppercase tracking-widest text-[var(--theme-color)] dark:drop-shadow-[0_0_8px_currentColor]">Lädt...</span>
                        </div>
                    </label>
                </div>
            </div>
        </div>
    </div>
@endif

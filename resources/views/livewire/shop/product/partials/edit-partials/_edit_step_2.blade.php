@if($currentStep === 2)
    <div class="space-y-6 md:space-y-8 animate-fade-in-up">

        @php
            $cardClassStep2 = "bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8";
        @endphp

        {{-- ========================================== --}}
        {{-- BEREICH FÜR DIGITALE DATEI (NUR WENN DIGITAL) --}}
        {{-- ========================================== --}}
        @if($type === 'digital')
            <div class="bg-blue-900/10 border border-blue-500/20 backdrop-blur-md rounded-[2.5rem] p-6 sm:p-8 relative overflow-hidden group shadow-inner">
                {{-- Deko Hintergrund --}}
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-full opacity-50 blur-[50px] pointer-events-none"></div>

                <div class="relative z-10">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="p-3 bg-blue-500/20 text-blue-400 border border-blue-500/30 rounded-xl shadow-[0_0_15px_rgba(59,130,246,0.3)] shrink-0">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-serif font-bold text-white tracking-wide">Digitale Produktdatei</h2>
                            <p class="text-[10px] font-black uppercase tracking-widest text-blue-300 mt-1">Die Datei, die der Kunde erhält.</p>
                        </div>
                    </div>

                    @if($product->digital_download_path)
                        {{-- STATUS: DATEI VORHANDEN --}}
                        <div class="bg-gray-950 p-5 rounded-2xl border border-blue-500/30 shadow-inner flex items-center justify-between">
                            <div class="flex items-center gap-5">
                                <div class="w-14 h-14 bg-gray-900 border border-gray-800 rounded-xl flex items-center justify-center text-gray-500 font-black text-[10px] uppercase tracking-widest shadow-inner shrink-0">
                                    {{ pathinfo($product->digital_download_path, PATHINFO_EXTENSION) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-white text-sm truncate max-w-[200px] sm:max-w-md">
                                        {{ $product->digital_filename ?? basename($product->digital_download_path) }}
                                    </p>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-emerald-400 flex items-center gap-2 mt-2 drop-shadow-[0_0_8px_currentColor]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Sicher gespeichert
                                    </p>
                                </div>
                            </div>
                            <button wire:click="removeDigitalFile" class="text-gray-500 hover:text-red-400 p-3 hover:bg-red-500/10 rounded-xl transition-all border border-transparent hover:border-red-500/30 shadow-inner" title="Datei löschen">
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
                            class="border-2 border-dashed rounded-2xl transition-all group/upload relative shadow-inner"
                            :class="isDropping ? 'border-blue-500 bg-blue-500/10 shadow-[0_0_30px_rgba(59,130,246,0.2)]' : 'border-gray-700 bg-gray-950 hover:bg-gray-900 hover:border-blue-500/50'">

                            <label class="flex flex-col items-center justify-center py-10 cursor-pointer w-full h-full">
                                <input type="file" wire:model.live="new_digital_file" class="hidden" x-ref="digitalInput">

                                <div x-show="!isUploading" class="text-center">
                                    <svg class="w-10 h-10 mx-auto text-gray-600 group-hover/upload:text-blue-400 mb-4 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 group-hover/upload:text-white transition-colors">
                                        <span x-show="!isDropping">Datei hier ablegen oder klicken</span>
                                        <span x-show="isDropping" class="text-blue-400">Ja, hier loslassen!</span>
                                    </p>
                                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-600 mt-2">PDF, ZIP, MP3, MP4 (Max. 100MB)</p>
                                </div>

                                {{-- Loading State --}}
                                <div x-show="isUploading" class="flex flex-col items-center">
                                    <svg class="animate-spin h-8 w-8 text-blue-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-blue-400 drop-shadow-[0_0_8px_currentColor]">Verschlüsselt und lädt...</span>
                                </div>
                            </label>
                        </div>
                        @error('new_digital_file') <p class="text-[10px] text-red-400 mt-3 font-bold uppercase tracking-widest">{{ $message }}</p> @enderror
                    @endif
                </div>
            </div>
        @endif

        {{-- ========================================== --}}
        {{-- PRODUKTBILDER & VIDEOS (MARKETING) --}}
        {{-- ========================================== --}}
        <div class="{{ $cardClassStep2 }}">
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-white mb-2 tracking-wide">Galerie & Marketing</h2>
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-8">Diese Bilder werden im Shop angezeigt (Vorschau).</p>

            @error('new_media')
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-5 py-4 rounded-xl shadow-inner mb-6 text-[10px] font-black uppercase tracking-widest" role="alert">
                <span class="block sm:inline">{{ $message }}</span>
            </div>
            @enderror

            {{-- BILDER UPLOAD --}}
            <h3 class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 mb-5 border-b border-gray-800 pb-3 flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-600"></span> Produktbilder (Mind. 1) *
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6 mb-10">
                @foreach($product->media_gallery ?? [] as $index => $media)
                    @if(is_array($media) && $media['type'] === 'image')
                        <div class="relative aspect-square rounded-[1.5rem] overflow-hidden group border-2 shadow-inner {{ $index === 0 ? 'border-primary shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'border-gray-800 hover:border-gray-700' }} bg-gray-950">
                            <img src="{{ asset('storage/'.$media['path']) }}" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition-opacity">
                            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-3">
                                @if($index !== 0)
                                    <button wire:click="setMainImage({{ $index }})" class="bg-gray-800 border border-gray-700 text-white p-2.5 rounded-xl hover:bg-primary hover:border-primary hover:text-gray-900 shadow-lg transition-all hover:scale-110" title="Als Hauptbild setzen"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg></button>
                                @endif
                                <button wire:click="removeMedia({{ $index }})" class="bg-gray-800 border border-gray-700 text-red-400 p-2.5 rounded-xl hover:bg-red-500 hover:border-red-500 hover:text-white shadow-lg transition-all hover:scale-110" title="Löschen"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                            </div>
                            @if($index === 0) <span class="absolute bottom-3 left-3 bg-primary text-gray-900 text-[8px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md shadow-lg border border-primary-dark">Hauptbild</span> @endif
                        </div>
                    @endif
                @endforeach

                {{-- DROPZONE FÜR BILDER --}}
                <div class="aspect-square rounded-[1.5rem] relative overflow-hidden transition-all duration-300 group shadow-inner"
                     x-data="{ isUploading: false, isDropping: false }"
                     x-on:livewire-upload-start="isUploading = true"
                     x-on:livewire-upload-finish="isUploading = false"
                     x-on:livewire-upload-error="isUploading = false"
                     x-on:dragover.prevent="isDropping = true"
                     x-on:dragleave.prevent="isDropping = false"
                     x-on:drop.prevent="isDropping = false;
                                        $refs.imageInput.files = $event.dataTransfer.files;
                                        $refs.imageInput.dispatchEvent(new Event('change', { bubbles: true }));"
                     :class="isDropping ? 'bg-primary/10 border-2 border-primary border-solid shadow-[0_0_20px_rgba(197,160,89,0.2)]' : 'bg-gray-950 border-2 border-dashed border-gray-800 hover:border-gray-600'">

                    <label class="w-full h-full cursor-pointer flex flex-col items-center justify-center text-gray-500 group-hover:text-white transition-colors">
                        <input type="file" multiple wire:model.live="new_media" class="hidden" accept="image/*" x-ref="imageInput">

                        <div x-show="!isUploading" class="text-center">
                            <svg class="w-8 h-8 mb-3 mx-auto opacity-50 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            <span class="text-[9px] font-black uppercase tracking-widest" x-text="isDropping ? 'Loslassen!' : 'Bilder +'"></span>
                        </div>

                        <div x-show="isUploading" class="absolute inset-0 bg-gray-900/90 backdrop-blur-sm flex flex-col items-center justify-center z-10">
                            <svg class="animate-spin h-6 w-6 text-primary mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="text-[9px] font-black uppercase tracking-widest text-primary drop-shadow-[0_0_8px_currentColor]">Lädt...</span>
                        </div>
                    </label>
                </div>
            </div>

            {{-- VIDEOS UPLOAD --}}
            <h3 class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 mb-5 border-b border-gray-800 pb-3 flex items-center gap-2 mt-4">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-600"></span> Produktvideos
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6">
                @foreach($product->media_gallery ?? [] as $index => $media)
                    @if(is_array($media) && $media['type'] === 'video')
                        <div class="relative aspect-square rounded-[1.5rem] overflow-hidden border border-gray-800 bg-gray-950 shadow-inner group">
                            <video src="{{ asset('storage/'.$media['path']) }}" class="w-full h-full object-cover opacity-60 group-hover:opacity-90 transition-opacity"></video>
                            <button wire:click="removeMedia({{ $index }})" class="absolute top-3 right-3 bg-gray-900 border border-gray-800 text-red-400 p-2 rounded-xl hover:bg-red-500 hover:text-white hover:border-red-500 opacity-0 group-hover:opacity-100 transition-all shadow-lg hover:scale-110"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                        </div>
                    @endif
                @endforeach

                {{-- DROPZONE FÜR VIDEO --}}
                <div class="aspect-square rounded-[1.5rem] relative overflow-hidden transition-all duration-300 group shadow-inner"
                     x-data="{ isUploading: false, isDropping: false }"
                     x-on:livewire-upload-start="isUploading = true"
                     x-on:livewire-upload-finish="isUploading = false"
                     x-on:livewire-upload-error="isUploading = false"
                     x-on:dragover.prevent="isDropping = true"
                     x-on:dragleave.prevent="isDropping = false"
                     x-on:drop.prevent="isDropping = false;
                                        $refs.videoInput.files = $event.dataTransfer.files;
                                        $refs.videoInput.dispatchEvent(new Event('change', { bubbles: true }));"
                     :class="isDropping ? 'bg-primary/10 border-2 border-primary border-solid shadow-[0_0_20px_rgba(197,160,89,0.2)]' : 'bg-gray-950 border-2 border-dashed border-gray-800 hover:border-gray-600'">

                    <label class="w-full h-full cursor-pointer flex flex-col items-center justify-center text-gray-500 group-hover:text-white transition-colors">
                        <input type="file" wire:model.live="new_video" class="hidden" accept="video/mp4,video/quicktime" x-ref="videoInput">

                        <div x-show="!isUploading" class="text-center">
                            <svg class="w-8 h-8 mb-3 mx-auto opacity-50 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <span class="text-[9px] font-black uppercase tracking-widest" x-text="isDropping ? 'Loslassen!' : 'Video +'"></span>
                        </div>

                        <div x-show="isUploading" class="absolute inset-0 bg-gray-900/90 backdrop-blur-sm flex flex-col items-center justify-center z-10">
                            <svg class="animate-spin h-6 w-6 text-primary mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="text-[9px] font-black uppercase tracking-widest text-primary drop-shadow-[0_0_8px_currentColor]">Lädt...</span>
                        </div>
                    </label>
                </div>
            </div>
        </div>
    </div>
@endif

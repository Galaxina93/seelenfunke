@if($currentStep === 2)
    <div class="space-y-8 animate-fade-in-up">

        {{-- ========================================== --}}
        {{-- BEREICH FÜR DIGITALE DATEI (NUR WENN DIGITAL) --}}
        {{-- ========================================== --}}
        @if($type === 'digital')
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-8 relative overflow-hidden group">
                {{-- Deko Hintergrund --}}
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-blue-100 rounded-full opacity-50 blur-xl"></div>

                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-blue-600 text-white rounded-lg shadow-md">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-serif font-bold text-gray-900">Digitale Produktdatei</h2>
                            <p class="text-sm text-blue-700">Die Datei, die der Kunde nach dem Kauf erhält.</p>
                        </div>
                    </div>

                    @if($product->digital_download_path)
                        {{-- STATUS: DATEI VORHANDEN --}}
                        <div class="bg-white p-4 rounded-xl border border-blue-200 shadow-sm flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 font-bold text-xs uppercase">
                                    {{ pathinfo($product->digital_download_path, PATHINFO_EXTENSION) }}
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900 text-sm truncate max-w-[200px] sm:max-w-md">
                                        {{ $product->digital_filename ?? basename($product->digital_download_path) }}
                                    </p>
                                    <p class="text-xs text-green-600 flex items-center gap-1 mt-0.5">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Sicher gespeichert & bereit
                                    </p>
                                </div>
                            </div>
                            <button wire:click="removeDigitalFile" class="text-red-500 hover:text-red-700 p-2 hover:bg-red-50 rounded-full transition" title="Datei löschen">
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
                            class="border-2 border-dashed rounded-xl transition-all group/upload relative"
                            :class="isDropping ? 'border-blue-500 bg-blue-100 ring-4 ring-blue-500/20' : 'border-blue-300 bg-blue-50/50 hover:bg-white hover:border-blue-500'">

                            <label class="flex flex-col items-center justify-center py-8 cursor-pointer w-full h-full">
                                <input type="file" wire:model.live="new_digital_file" class="hidden" x-ref="digitalInput">

                                <div x-show="!isUploading" class="text-center">
                                    <svg class="w-10 h-10 mx-auto text-blue-400 group-hover/upload:text-blue-600 mb-3 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    <p class="text-sm font-bold text-blue-900">
                                        <span x-show="!isDropping">Datei hier ablegen oder klicken</span>
                                        <span x-show="isDropping">Ja, hier loslassen!</span>
                                    </p>
                                    <p class="text-xs text-blue-600 mt-1">PDF, ZIP, MP3, MP4 (Max. 100MB)</p>
                                </div>

                                {{-- Loading State --}}
                                <div x-show="isUploading" class="flex flex-col items-center">
                                    <svg class="animate-spin h-8 w-8 text-blue-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span class="text-xs font-bold text-blue-700">Wird sicher verschlüsselt hochgeladen...</span>
                                </div>
                            </label>
                        </div>
                        @error('new_digital_file') <p class="text-xs text-red-500 mt-2 font-bold">{{ $message }}</p> @enderror
                    @endif
                </div>
            </div>
        @endif

        {{-- ========================================== --}}
        {{-- PRODUKTBILDER & VIDEOS (MARKETING) --}}
        {{-- ========================================== --}}

        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
            <h2 class="text-2xl font-serif text-gray-900 mb-2">Galerie & Marketing</h2>
            <p class="text-sm text-gray-500 mb-6">Diese Bilder werden im Shop angezeigt (Vorschau).</p>

            @error('new_media')
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert"><span class="block sm:inline">{{ $message }}</span></div>
            @enderror

            {{-- BILDER UPLOAD --}}
            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-4 border-b border-gray-100 pb-2">Produktbilder (Mind. 1) *</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                @foreach($product->media_gallery ?? [] as $index => $media)
                    @if(is_array($media) && $media['type'] === 'image')
                        <div class="relative aspect-square rounded-lg overflow-hidden group border {{ $index === 0 ? 'border-2 border-primary' : 'border-gray-200' }} bg-white">
                            <img src="{{ asset('storage/'.$media['path']) }}" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                                @if($index !== 0)
                                    <button wire:click="setMainImage({{ $index }})" class="bg-white text-black p-2 rounded-full hover:bg-gray-100" title="Als Hauptbild setzen"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg></button>
                                @endif
                                <button wire:click="removeMedia({{ $index }})" class="bg-white text-red-500 p-2 rounded-full hover:bg-red-50" title="Löschen"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                            </div>
                            @if($index === 0) <span class="absolute bottom-2 left-2 bg-primary text-white text-[10px] px-2 py-1 rounded shadow-sm">Hauptbild</span> @endif
                        </div>
                    @endif
                @endforeach

                {{-- DROPZONE FÜR BILDER --}}
                <div class="aspect-square rounded-lg relative overflow-hidden transition group"
                     x-data="{ isUploading: false, isDropping: false }"
                     x-on:livewire-upload-start="isUploading = true"
                     x-on:livewire-upload-finish="isUploading = false"
                     x-on:livewire-upload-error="isUploading = false"
                     x-on:dragover.prevent="isDropping = true"
                     x-on:dragleave.prevent="isDropping = false"
                     x-on:drop.prevent="isDropping = false;
                                        $refs.imageInput.files = $event.dataTransfer.files;
                                        $refs.imageInput.dispatchEvent(new Event('change', { bubbles: true }));"
                     :class="isDropping ? 'bg-primary/10 border-2 border-primary border-solid' : 'bg-gray-50 border-2 border-dashed border-gray-300 hover:border-primary'">

                    <label class="w-full h-full cursor-pointer flex flex-col items-center justify-center text-gray-400 group-hover:text-primary">
                        <input type="file" multiple wire:model.live="new_media" class="hidden" accept="image/*" x-ref="imageInput">

                        <div x-show="!isUploading" class="text-center">
                            <svg class="w-8 h-8 mb-2 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span class="text-xs font-bold" x-text="isDropping ? 'Loslassen!' : 'Bilder +'"></span>
                        </div>

                        <div x-show="isUploading" class="absolute inset-0 bg-white/90 flex flex-col items-center justify-center z-10">
                            <svg class="animate-spin h-8 w-8 text-primary mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="text-xs font-bold text-primary">Lade...</span>
                        </div>
                    </label>
                </div>
            </div>

            {{-- VIDEOS UPLOAD --}}
            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-4 border-b border-gray-100 pb-2">Produktvideos</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @foreach($product->media_gallery ?? [] as $index => $media)
                    @if(is_array($media) && $media['type'] === 'video')
                        <div class="relative aspect-square rounded-lg overflow-hidden border border-gray-200 bg-black group">
                            <video src="{{ asset('storage/'.$media['path']) }}" class="w-full h-full object-cover opacity-80"></video>
                            <button wire:click="removeMedia({{ $index }})" class="absolute top-2 right-2 bg-white text-red-500 p-1.5 rounded-full hover:bg-red-50 opacity-0 group-hover:opacity-100 transition"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                        </div>
                    @endif
                @endforeach

                {{-- DROPZONE FÜR VIDEO --}}
                <div class="aspect-square rounded-lg relative overflow-hidden transition group"
                     x-data="{ isUploading: false, isDropping: false }"
                     x-on:livewire-upload-start="isUploading = true"
                     x-on:livewire-upload-finish="isUploading = false"
                     x-on:livewire-upload-error="isUploading = false"
                     x-on:dragover.prevent="isDropping = true"
                     x-on:dragleave.prevent="isDropping = false"
                     x-on:drop.prevent="isDropping = false;
                                        $refs.videoInput.files = $event.dataTransfer.files;
                                        $refs.videoInput.dispatchEvent(new Event('change', { bubbles: true }));"
                     :class="isDropping ? 'bg-primary/10 border-2 border-primary border-solid' : 'bg-gray-50 border-2 border-dashed border-gray-300 hover:border-primary'">

                    <label class="w-full h-full cursor-pointer flex flex-col items-center justify-center text-gray-400 group-hover:text-primary">
                        <input type="file" wire:model.live="new_video" class="hidden" accept="video/mp4,video/quicktime" x-ref="videoInput">

                        <div x-show="!isUploading" class="text-center">
                            <svg class="w-8 h-8 mb-2 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <span class="text-xs font-bold" x-text="isDropping ? 'Loslassen!' : 'Video +'"></span>
                        </div>

                        <div x-show="isUploading" class="absolute inset-0 bg-white/90 flex flex-col items-center justify-center z-10">
                            <svg class="animate-spin h-8 w-8 text-primary mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="text-xs font-bold text-primary">Lädt...</span>
                        </div>
                    </label>
                </div>
            </div>
        </div>
    </div>
@endif

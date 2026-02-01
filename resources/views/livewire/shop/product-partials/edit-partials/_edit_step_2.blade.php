@if($currentStep === 2)
    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
        <h2 class="text-2xl font-serif text-gray-900 mb-2">2. Produktmedien</h2>
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
            <div class="aspect-square rounded-lg relative overflow-hidden bg-gray-50 border-2 border-dashed border-gray-300 hover:border-primary transition group"
                 x-data="{ isUploading: false }"
                 x-on:livewire-upload-start="isUploading = true"
                 x-on:livewire-upload-finish="isUploading = false"
                 x-on:livewire-upload-error="isUploading = false">
                <label class="w-full h-full cursor-pointer flex flex-col items-center justify-center text-gray-400 group-hover:text-primary">
                    <input type="file" multiple wire:model.live="new_media" class="hidden" accept="image/*" onchange="if(this.files[0].size > 3145728){alert('Zu groß'); this.value=''; return;}">
                    <svg class="w-8 h-8 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span class="text-xs font-bold">Bilder +</span>
                </label>
                <div x-show="isUploading" class="absolute inset-0 bg-white/90 flex flex-col items-center justify-center z-10">
                    <svg class="animate-spin h-8 w-8 text-primary mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span class="text-xs font-bold text-primary">Lade...</span>
                </div>
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
            <div class="aspect-square rounded-lg relative overflow-hidden bg-gray-50 border-2 border-dashed border-gray-300 hover:border-primary transition group"
                 x-data="{ isUploading: false }"
                 x-on:livewire-upload-start="isUploading = true"
                 x-on:livewire-upload-finish="isUploading = false"
                 x-on:livewire-upload-error="isUploading = false">
                <label class="w-full h-full cursor-pointer flex flex-col items-center justify-center text-gray-400 group-hover:text-primary">
                    <input type="file" wire:model.live="new_video" class="hidden" accept="video/mp4,video/quicktime">
                    <svg class="w-8 h-8 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="text-xs font-bold">Video +</span>
                </label>
                <div x-show="isUploading" class="absolute inset-0 bg-white/90 flex flex-col items-center justify-center z-10">
                    <svg class="animate-spin h-8 w-8 text-primary mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span class="text-xs font-bold text-primary">Lädt...</span>
                </div>
            </div>
        </div>
    </div>
@endif

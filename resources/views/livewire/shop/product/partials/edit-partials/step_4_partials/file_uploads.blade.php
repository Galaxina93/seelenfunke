<div class="space-y-6">
    {{-- 2D Vorschau Overlay --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-6 sm:p-8 bg-gray-900/50 backdrop-blur-md rounded-[2rem] border border-gray-800 shadow-inner group transition-colors hover:border-gray-700">
        <div class="flex-1">
            <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-300 mb-2 drop-shadow-[0_0_8px_currentColor]">2D Vorschau Overlay (PNG)</label>
            <p class="text-xs text-gray-500 mb-3 font-medium">Dient als Zeichenbrett für den Arbeitsbereich.</p>
            @if($product->preview_image_path)
                <div class="mt-3 flex flex-col gap-1.5">
                    <span class="text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 drop-shadow-[0_0_8px_currentColor]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        Erfolgreich hochgeladen
                    </span>
                    <div class="inline-flex items-center bg-gray-950 border border-gray-800 rounded-lg px-2.5 py-1.5 w-fit max-w-full">
                        <svg class="w-3.5 h-3.5 text-gray-500 mr-2 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span class="text-gray-400 text-[9px] font-mono truncate" title="{{ basename($product->preview_image_path) }}">
                            {{ basename($product->preview_image_path) }}
                        </span>
                    </div>
                </div>
            @endif
        </div>
        <div class="flex items-center gap-4">
            @if($product->preview_image_path)
                <button wire:click="removePreviewImage" class="p-3 text-gray-500 hover:text-red-400 bg-gray-950 border border-gray-800 hover:border-red-500/30 rounded-xl transition-all shadow-inner" title="Löschen">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            @endif
            <div class="flex-shrink-0 relative">
                <label class="cursor-pointer bg-gray-950 border border-gray-800 hover:border-primary text-gray-400 hover:text-primary px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-inner transition-all flex items-center justify-center gap-2" :class="{'opacity-50 pointer-events-none': isUploading}">
                    {{ $product->preview_image_path ? 'Ändern' : 'Wählen' }}
                    <input type="file" wire:model.live="new_preview_image" accept="image/png" class="hidden">
                </label>
            </div>
        </div>
    </div>

    {{-- 3D-Modell (.glb) --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-6 sm:p-8 bg-blue-900/10 backdrop-blur-md rounded-[2rem] border border-blue-500/20 shadow-inner relative overflow-hidden group hover:border-blue-500/40 transition-colors">
        <div class="absolute right-0 top-0 w-32 h-32 bg-blue-500/10 rounded-full blur-[50px] -mr-10 -mt-10 pointer-events-none"></div>
        <div class="flex-1 relative z-10">
            <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-blue-400 mb-2 drop-shadow-[0_0_8px_currentColor]">3D-Modell (.glb)</label>
            <p class="text-xs text-blue-200/50 mb-3 font-medium">Interaktive 360° Ansicht.</p>
            @if($product->three_d_model_path)
                <div class="mt-3 flex flex-col gap-1.5">
                    <span class="text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 drop-shadow-[0_0_8px_currentColor]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        Erfolgreich hochgeladen
                    </span>
                    <div class="inline-flex items-center bg-gray-950 border border-gray-800 rounded-lg px-2.5 py-1.5 w-fit max-w-full">
                        <svg class="w-3.5 h-3.5 text-gray-500 mr-2 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"></path></svg>
                        <span class="text-gray-400 text-[9px] font-mono truncate" title="{{ basename($product->three_d_model_path) }}">
                            {{ basename($product->three_d_model_path) }}
                        </span>
                    </div>
                </div>
            @endif
        </div>
        <div class="flex items-center gap-4 relative z-10">
            @if($product->three_d_model_path)
                <button wire:click="remove3dModel" class="p-3 text-gray-500 hover:text-red-400 bg-gray-950 border border-gray-800 hover:border-red-500/30 rounded-xl transition-all shadow-inner"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
            @endif
            <div class="flex-shrink-0 relative">
                <label class="cursor-pointer bg-blue-600/20 border border-blue-500/30 hover:bg-blue-500/30 text-blue-300 hover:text-white px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-inner transition-all" :class="{'opacity-50 pointer-events-none': isUploading}">
                    {{ $product->three_d_model_path ? 'Ersetzen' : 'Hochladen' }}
                    <input type="file" wire:model.live="new_3d_model" accept=".glb" class="hidden">
                </label>
            </div>
        </div>
    </div>

    {{-- 3D Hintergrund (.jpg/.png) --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-6 sm:p-8 bg-purple-900/10 backdrop-blur-md rounded-[2rem] border border-purple-500/20 shadow-inner relative overflow-hidden group hover:border-purple-500/40 transition-colors">
        <div class="absolute right-0 top-0 w-32 h-32 bg-purple-500/10 rounded-full blur-[50px] -mr-10 -mt-10 pointer-events-none"></div>
        <div class="flex-1 relative z-10">
            <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-purple-400 mb-2 drop-shadow-[0_0_8px_currentColor]">3D Hintergrund (.jpg/.png)</label>
            <p class="text-xs text-purple-200/50 mb-3 font-medium">Wird als echter CSS Hintergrund geladen.</p>
            @if($product->three_d_background_path)
                <div class="mt-3 flex flex-col gap-1.5">
                    <span class="text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 drop-shadow-[0_0_8px_currentColor]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        Erfolgreich hochgeladen
                    </span>
                    <div class="inline-flex items-center bg-gray-950 border border-gray-800 rounded-lg px-2.5 py-1.5 w-fit max-w-full">
                        <svg class="w-3.5 h-3.5 text-gray-500 mr-2 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="text-gray-400 text-[9px] font-mono truncate" title="{{ basename($product->three_d_background_path) }}">
                            {{ basename($product->three_d_background_path) }}
                        </span>
                    </div>
                </div>
            @endif
        </div>
        <div class="flex items-center gap-4 relative z-10">
            @if($product->three_d_background_path)
                <button wire:click="remove3dBackground" class="p-3 text-gray-500 hover:text-red-400 bg-gray-950 border border-gray-800 hover:border-red-500/30 rounded-xl transition-all shadow-inner"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
            @endif
            <div class="flex-shrink-0 relative">
                <label class="cursor-pointer bg-purple-600/20 border border-purple-500/30 hover:bg-purple-500/30 text-purple-300 hover:text-white px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-inner transition-all" :class="{'opacity-50 pointer-events-none': isUploading}">
                    {{ $product->three_d_background_path ? 'Ersetzen' : 'Hochladen' }}
                    <input type="file" wire:model.live="new_3d_background" accept="image/jpeg, image/png" class="hidden">
                </label>
            </div>
        </div>
    </div>

    <div x-show="isUploading" style="display: none;" class="p-6 bg-gray-900/80 backdrop-blur-md rounded-[2rem] border border-gray-800 text-center shadow-inner">
        <svg class="animate-spin h-8 w-8 text-primary mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <span class="text-[10px] font-black uppercase tracking-widest mt-4 text-primary block drop-shadow-[0_0_8px_currentColor]">Upload läuft...</span>
    </div>
</div>

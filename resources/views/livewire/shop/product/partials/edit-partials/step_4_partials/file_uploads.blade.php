<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-6 bg-gray-50 rounded-xl border border-gray-200">
        <div class="flex-1">
            <label class="block text-sm font-bold text-gray-900 mb-1">2D Vorschau Overlay (PNG)</label>
            <p class="text-xs text-gray-500 mb-2">Dient als Zeichenbrett für den Arbeitsbereich.</p>
            @if($product->preview_image_path)
                <div class="mt-2 text-green-600 text-xs font-bold flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Overlay aktiv
                </div>
            @endif
        </div>
        <div class="flex items-center gap-3">
            @if($product->preview_image_path)
                <button wire:click="removePreviewImage" class="p-3 text-red-500 hover:bg-red-50 rounded-full transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            @endif
            <div class="flex-shrink-0 relative">
                <label class="cursor-pointer bg-white border border-gray-300 hover:border-primary hover:text-primary text-gray-700 px-6 py-3 rounded-full text-sm font-bold shadow-sm transition" :class="{'opacity-50 pointer-events-none': isUploading}">
                    {{ $product->preview_image_path ? 'Ändern' : 'Wählen' }}
                    <input type="file" wire:model.live="new_preview_image" accept="image/png" class="hidden">
                </label>
            </div>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-6 bg-blue-50/50 rounded-xl border border-blue-100 relative overflow-hidden">
        <div class="absolute right-0 top-0 w-24 h-24 bg-blue-500/10 rounded-full blur-xl -mr-10 -mt-10"></div>
        <div class="flex-1 relative z-10">
            <label class="block text-sm font-bold text-blue-900 mb-1">3D-Modell (.glb)</label>
            <p class="text-xs text-blue-700/80 mb-2">Interaktive 360° Ansicht.</p>
            @if($product->three_d_model_path)
                <div class="mt-2 text-green-600 text-xs font-bold flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Geladen
                </div>
            @endif
        </div>
        <div class="flex items-center gap-3 relative z-10">
            @if($product->three_d_model_path)
                <button wire:click="remove3dModel" class="p-3 text-red-500 hover:bg-red-50 rounded-full transition"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
            @endif
            <div class="flex-shrink-0 relative">
                <label class="cursor-pointer bg-blue-600 border border-transparent hover:bg-blue-700 text-white px-6 py-3 rounded-full text-sm font-bold shadow-md transition" :class="{'opacity-50 pointer-events-none': isUploading}">
                    {{ $product->three_d_model_path ? 'Ersetzen' : 'Hochladen' }}
                    <input type="file" wire:model.live="new_3d_model" accept=".glb" class="hidden">
                </label>
            </div>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-6 bg-purple-50/50 rounded-xl border border-purple-100 relative overflow-hidden">
        <div class="absolute right-0 top-0 w-24 h-24 bg-purple-500/10 rounded-full blur-xl -mr-10 -mt-10"></div>
        <div class="flex-1 relative z-10">
            <label class="block text-sm font-bold text-purple-900 mb-1">3D Hintergrund (.jpg/.png)</label>
            <p class="text-xs text-purple-700/80 mb-2">Wird als echter CSS Hintergrund geladen.</p>
            @if($product->three_d_background_path)
                <div class="mt-2 text-green-600 text-xs font-bold flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Geladen
                </div>
            @endif
        </div>
        <div class="flex items-center gap-3 relative z-10">
            @if($product->three_d_background_path)
                <button wire:click="remove3dBackground" class="p-3 text-red-500 hover:bg-red-50 rounded-full transition"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
            @endif
            <div class="flex-shrink-0 relative">
                <label class="cursor-pointer bg-purple-600 border border-transparent hover:bg-purple-700 text-white px-6 py-3 rounded-full text-sm font-bold shadow-md transition" :class="{'opacity-50 pointer-events-none': isUploading}">
                    {{ $product->three_d_background_path ? 'Ersetzen' : 'Hochladen' }}
                    <input type="file" wire:model.live="new_3d_background" accept="image/jpeg, image/png" class="hidden">
                </label>
            </div>
        </div>
    </div>

    <div x-show="isUploading" style="display: none;" class="p-4 bg-gray-50 rounded-xl text-center">
        <svg class="animate-spin h-6 w-6 text-primary mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <span class="text-xs font-bold mt-2 block">Upload läuft...</span>
    </div>
</div>

@if($currentStep === 4)
    {{-- Dieser Step wird nur angezeigt, wenn type === 'physical' ist (gesteuert durch das Haupt-Blade) --}}
    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up space-y-8">
        <h2 class="text-2xl font-serif text-gray-900">4. Live-Konfigurator</h2>

        {{-- Overlay Upload --}}
        <div class="flex flex-col md:flex-row items-center gap-6 p-6 bg-gray-50 rounded-xl border border-gray-200"
             x-data="{ isUploading: false }"
             x-on:livewire-upload-start="isUploading = true"
             x-on:livewire-upload-finish="isUploading = false"
             x-on:livewire-upload-error="isUploading = false">
            <div class="flex-1">
                <label class="block text-sm font-bold text-gray-900 mb-1">Vorschau Overlay (PNG)</label>
                <p class="text-xs text-gray-500 mb-2">Das transparente PNG, das als Rahmen über dem Produkt liegt.</p>
                @if($product->preview_image_path)
                    <div class="mt-2 text-green-600 text-xs font-bold flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Overlay aktiv
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-3">
                @if($product->preview_image_path)
                    <button wire:click="removePreviewImage" class="p-3 text-red-500 hover:bg-red-50 rounded-full transition" title="Overlay entfernen">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                @endif
                <div class="flex-shrink-0 relative">
                    <label class="cursor-pointer bg-white border border-gray-300 hover:border-primary hover:text-primary text-gray-700 px-6 py-3 rounded-full text-sm font-bold shadow-sm transition" :class="{'opacity-50 pointer-events-none': isUploading}">
                        {{ $product->preview_image_path ? 'Overlay ändern' : 'Overlay wählen' }}
                        <input type="file" wire:model.live="new_preview_image" accept="image/png" class="hidden">
                    </label>
                    <div x-show="isUploading" class="absolute inset-0 bg-white/90 flex flex-col items-center justify-center z-10 rounded-full">
                        <svg class="animate-spin h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </div>
                </div>
            </div>
            @if($product->preview_image_path)
                <div class="w-20 h-20 border bg-white rounded-lg shadow-sm flex items-center justify-center overflow-hidden p-2">
                    <img src="{{ asset('storage/'.$product->preview_image_path) }}" class="object-contain w-full h-full">
                </div>
            @endif
        </div>

        {{-- Arbeitsbereich Definieren --}}
        <div class="bg-gray-50 p-6 rounded-xl border border-gray-100">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h3 class="font-bold text-lg text-gray-900">Arbeitsbereich definieren</h3>
                    <p class="text-xs text-gray-500">Legen Sie fest, wo Kunden Elemente platzieren dürfen.</p>
                </div>

                {{-- Umschalter für die Form --}}
                <div class="flex bg-white p-1 rounded-lg border border-gray-200 shadow-sm">
                    <button type="button"
                            wire:click="$set('configSettings.area_shape', 'rect')"
                            class="px-4 py-1.5 rounded-md text-xs font-bold transition {{ $configSettings['area_shape'] === 'rect' ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Eckig
                    </button>
                    <button type="button"
                            wire:click="$set('configSettings.area_shape', 'circle')"
                            class="px-4 py-1.5 rounded-md text-xs font-bold transition {{ $configSettings['area_shape'] === 'circle' ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Rund
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">Abstand Oben (%)</label>
                    <input type="number" wire:model.live="configSettings.area_top" class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">Abstand Links (%)</label>
                    <input type="number" wire:model.live="configSettings.area_left" class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">Breite (%)</label>
                    <input type="number" wire:model.live="configSettings.area_width" class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">Höhe (%)</label>
                    <input type="number" wire:model.live="configSettings.area_height" class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary text-sm">
                </div>
            </div>

            {{-- VISUELLE VORSCHAU --}}
            <div class="border border-gray-300 rounded-lg overflow-hidden bg-white relative max-w-sm mx-auto shadow-sm">
                <div class="text-xs text-gray-400 text-center py-2 border-b border-gray-100 uppercase font-bold tracking-widest">Live Vorschau</div>
                <div class="relative w-full aspect-square bg-gray-100 flex items-center justify-center overflow-hidden">
                    @if($product->preview_image_path)
                        <img src="{{ asset('storage/'.$product->preview_image_path) }}" class="absolute inset-0 w-full h-full object-contain z-0">
                    @else
                        <div class="text-gray-300 text-xs font-bold">Kein Overlay</div>
                    @endif

                    <div class="absolute border-2 border-green-500 bg-green-500/20 z-10 transition-all duration-300"
                         style="
                        top: {{ $configSettings['area_top'] }}%;
                        left: {{ $configSettings['area_left'] }}%;
                        width: {{ $configSettings['area_width'] }}%;
                        height: {{ $configSettings['area_height'] }}%;
                        border-radius: {{ ($configSettings['area_shape'] ?? 'rect') === 'circle' ? '50%' : '0' }};
                        box-shadow: 0 0 0 9999px rgba(239, 68, 68, 0.2);
                     ">
                        <span class="absolute top-0 left-0 bg-green-500 text-white text-[10px] px-1 font-bold">Erlaubt</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Settings Checkboxen --}}
        <div class="space-y-6 pt-4">
            <div class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-primary/30 transition bg-white">
                <h3 class="font-bold text-lg text-gray-900">Text-Gravur erlauben</h3>
                <input type="checkbox" wire:model.live="configSettings.allow_text_pos" class="w-6 h-6 rounded text-primary focus:ring-primary border-gray-300">
            </div>
            <div class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-primary/30 transition bg-white">
                <h3 class="font-bold text-lg text-gray-900">Logo-Upload erlauben</h3>
                <input type="checkbox" wire:model.live="configSettings.allow_logo" class="w-6 h-6 rounded text-primary focus:ring-primary border-gray-300">
            </div>
        </div>
    </div>
@endif

<div>
    @if($currentStep === 4)

        <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-10 rounded-[2.5rem] shadow-2xl border border-gray-800 animate-fade-in-up space-y-8"

             {{-- Three Logic --}}
             @include('livewire.shop.product.partials.edit-partials.step_4_partials.three_js_main')

             x-on:livewire-upload-start="isUploading = true"
             x-on:livewire-upload-finish="isUploading = false"
             x-on:livewire-upload-error="isUploading = false">

            <h2 class="text-xl sm:text-2xl font-serif font-bold text-white border-b border-gray-800 pb-5 tracking-wide flex items-center gap-3">
                <span class="w-8 h-8 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shadow-inner text-sm shrink-0">4</span>
                Live-Konfigurator & 3D-Ansicht
            </h2>

            <div class="grid grid-cols-1 gap-8 mb-8">
                {{-- Datei Uploads --}}
                @include('livewire.shop.product.partials.edit-partials.step_4_partials.file_uploads')

                {{-- 1:1 Maße --}}
                @include('livewire.shop.product.partials.edit-partials.step_4_partials.dimensions')
            </div>

            {{-- NEUES LAYOUT: Vorschau Box oben, Regler kompakt darunter --}}
            <div class="pt-8 border-t border-gray-800" x-show="configSettings" style="display: none;" x-transition>

                {{-- Preview Header Einstellungen (z.B. auch Drag and Drop) --}}
                @include('livewire.shop.product.partials.edit-partials.step_4_partials.preview_header_config')

                {{-- ZENTRALE VORSCHAU BOX MIT CSS BACKGROUND BILD --}}
                @include('livewire.shop.product.partials.edit-partials.step_4_partials.3d_preview')

                {{-- SPALTE 1: 2D Arbeitsbereich --}}
                @include('livewire.shop.product.partials.edit-partials.step_4_partials.working_area_config_ranges')

                {{-- SPALTE 2: 3D Modell --}}
                @include('livewire.shop.product.partials.edit-partials.step_4_partials.3d_model_config_ranges')

                {{-- SPALTE 3: 3D Overlay --}}
                @include('livewire.shop.product.partials.edit-partials.step_4_partials.3d_overlay_config_ranges')

            </div>
        </div>

    @endif
</div>

<div>
    @if($currentStep === 4)

        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up space-y-8"

             {{-- Three Logic --}}
             @include('livewire.shop.product.partials.edit-partials.step_4_partials.three_js_main')

             x-on:livewire-upload-start="isUploading = true"
             x-on:livewire-upload-finish="isUploading = false"
             x-on:livewire-upload-error="isUploading = false">

            <h2 class="text-2xl font-serif text-gray-900 border-b border-gray-100 pb-4">4. Live-Konfigurator & 3D-Ansicht</h2>

            <div class="grid grid-cols-1 gap-8 mb-8">
                {{-- Datei Uploads --}}
                @include('livewire.shop.product.partials.edit-partials.step_4_partials.file_uploads')

                {{-- 1:1 Maße --}}
                @include('livewire.shop.product.partials.edit-partials.step_4_partials.dimensions')
            </div>

            {{-- NEUES LAYOUT: Vorschau Box oben, Regler kompakt darunter --}}
            <div class="pt-8 border-t border-gray-200" x-show="configSettings" style="display: none;">

                {{-- Preview Header Einstellungen (z.B. auch Drag and Drop) --}}
                @include('livewire.shop.product.partials.edit-partials.step_4_partials.preview_header_config')

                {{-- ZENTRALE VORSCHAU BOX MIT CSS BACKGROUND BILD --}}
                @include('livewire.shop.product.partials.edit-partials.step_4_partials.3d_preview')

                {{-- KOMPAKTES GRID FÜR ALLE REGLER --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 bg-gray-50 p-5 rounded-xl border border-gray-200">

                    {{-- SPALTE 1: 2D Arbeitsbereich --}}
                    @include('livewire.shop.product.partials.edit-partials.step_4_partials.working_area_config_ranges')

                    {{-- SPALTE 2: 3D Modell --}}
                    @include('livewire.shop.product.partials.edit-partials.step_4_partials.3d_model_config_ranges')

                    {{-- SPALTE 3: 3D Overlay --}}
                    @include('livewire.shop.product.partials.edit-partials.step_4_partials.3d_overlay_config_ranges')

                </div>
            </div>
        </div>

    @endif
</div>

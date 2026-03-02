<div>
    @if($currentStep === 4)

        {{-- Unsichtbarer Wrapper hält den Alpine.js Status für beide Blöcke zusammen --}}
        <div class="space-y-8"
             @include('livewire.shop.product.partials.edit-partials.step_4_partials.three_js_main')
             x-on:livewire-upload-start="isUploading = true"
             x-on:livewire-upload-finish="isUploading = false"
             x-on:livewire-upload-error="isUploading = false">

            {{-- BLOCK 1: DATEIEN & MAßE --}}
            <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-10 rounded-[2.5rem] shadow-2xl border border-gray-800 animate-fade-in-up">
                <h2 class="text-xl sm:text-2xl font-serif font-bold text-white border-b border-gray-800 pb-5 mb-8 tracking-wide flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shadow-inner text-sm shrink-0">4</span>
                    Dateien & Produktdetails
                </h2>

                <div class="grid grid-cols-1 gap-8">
                    @include('livewire.shop.product.partials.edit-partials.step_4_partials.file_uploads')
                    @include('livewire.shop.product.partials.edit-partials.step_4_partials.dimensions')
                </div>
            </div>

            {{-- BLOCK 2: LIVE-KONFIGURATOR (Volle Breite, Regler an der Seite) --}}
            <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-10 rounded-[2.5rem] shadow-2xl border border-gray-800 animate-fade-in-up" x-show="configSettings" style="display: none;" x-transition>

                {{-- Toolbar (Buttons) --}}
                @include('livewire.shop.product.partials.edit-partials.step_4_partials.preview_header_config')

                {{-- DESKTOP GRID: Links 3D, Rechts Regler --}}
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mt-8">

                    {{-- LINKS: 3D Ansicht (nimmt 7 oder 8 Spalten ein) --}}
                    <div class="lg:col-span-7 xl:col-span-8">
                        @include('livewire.shop.product.partials.edit-partials.step_4_partials.3d_preview')
                    </div>

                    {{-- RECHTS: Alle Regler (nimmt den restlichen Platz ein) --}}
                    <div class="lg:col-span-5 xl:col-span-4 flex flex-col gap-6">
                        @include('livewire.shop.product.partials.edit-partials.step_4_partials.working_area_config_ranges')
                        @include('livewire.shop.product.partials.edit-partials.step_4_partials.3d_model_config_ranges')
                        @include('livewire.shop.product.partials.edit-partials.step_4_partials.3d_overlay_config_ranges')
                    </div>

                </div>
            </div>

        </div>

    @endif
</div>

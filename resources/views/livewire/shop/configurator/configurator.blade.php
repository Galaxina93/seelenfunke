@php
    $isDark = ($design ?? 'light') === 'dark';
@endphp

<div class="relative w-full h-full {{ $isDark ? 'bg-gray-950 text-gray-300' : 'bg-white text-gray-900' }}">
    <div class="h-full flex flex-col" x-data="window.frontendConfiguratorData({
        wireModels: {
            texts: $wire.entangle('texts').live,
            logos: $wire.entangle('logos').live,
            texts_back: $wire.entangle('texts_back').live,
            logos_back: $wire.entangle('logos_back').live,
            activeSide: $wire.entangle('activeSide').live
        },
        fonts: @js($fonts),
        context: '{{$context}}',
        config: {
            modelPath: '{{$product->three_d_model_path ? asset("storage/".$product->three_d_model_path) : ""}}',
            bgPath: '{{$product->three_d_background_path ? asset("storage/".$product->three_d_background_path) : ""}}',
            fallbackImg: '{{ $this->previewImage }}',
            has_back_side: {{!empty($configSettings['has_back_side']) && $configSettings['has_back_side'] ? 'true' : 'false'}},
            area_top: {{$configSettings['area_top'] ?? 10}},
            area_left: {{$configSettings['area_left'] ?? 10}},
            area_width: {{$configSettings['area_width'] ?? 80}},
            area_height: {{$configSettings['area_height'] ?? 80}},
            area_shape: '{{$configSettings['area_shape'] ?? "rect"}}',
            overlay_type: '{{$configSettings['overlay_type'] ?? "plane"}}',
            cylinder_radius: {{$configSettings['cylinder_radius'] ?? 50}},
            material_type: '{{$configSettings['material_type'] ?? "glass"}}',
            model_scale: {{$configSettings['model_scale'] ?? 100}},
            model_pos_x: {{$configSettings['model_pos_x'] ?? 0}},
            model_pos_y: {{$configSettings['model_pos_y'] ?? 0}},
            model_pos_z: {{$configSettings['model_pos_z'] ?? 0}},
            model_rot_x: {{$configSettings['model_rot_x'] ?? 0}},
            model_rot_y: {{$configSettings['model_rot_y'] ?? 0}},
            model_rot_z: {{$configSettings['model_rot_z'] ?? 0}},
            engraving_scale: {{$configSettings['engraving_scale'] ?? 100}},
            engraving_pos_x: {{$configSettings['engraving_pos_x'] ?? 0}},
            engraving_pos_y: {{$configSettings['engraving_pos_y'] ?? 0}},
            engraving_pos_z: {{$configSettings['engraving_pos_z'] ?? 0}},
            engraving_rot_x: {{$configSettings['engraving_rot_x'] ?? 0}},
            engraving_rot_y: {{$configSettings['engraving_rot_y'] ?? 0}},
            engraving_rot_z: {{$configSettings['engraving_rot_z'] ?? 0}},
            back_engraving_scale: {{$configSettings['back_engraving_scale'] ?? 100}},
            back_engraving_pos_x: {{$configSettings['back_engraving_pos_x'] ?? 0}},
            back_engraving_pos_y: {{$configSettings['back_engraving_pos_y'] ?? 0}},
            back_engraving_pos_z: {{$configSettings['back_engraving_pos_z'] ?? 0}},
            back_engraving_rot_x: {{$configSettings['back_engraving_rot_x'] ?? 0}},
            back_engraving_rot_y: {{$configSettings['back_engraving_rot_y'] ?? 0}},
            back_engraving_rot_z: {{$configSettings['back_engraving_rot_z'] ?? 0}},
            custom_points: @js($configSettings['custom_points'] ?? [])
        }
    })"
         @mousemove.window="handleMouseMove($event)"
         @mouseup.window="handleMouseUp($event)"
         @touchmove.window="handleMouseMove($event)"
         @touchend.window="handleMouseUp($event)">

        <div class="flex-1 custom-scrollbar pb-20">
            @if(!$isDigital)
                @include('livewire.shop.configurator.partials.preview', ['isDark' => $isDark])
                @include('livewire.shop.configurator.partials.formluar', ['isDark' => $isDark])
            @else
                {{-- Digitales Produkt Layout --}}
                <div class="p-6 sm:p-12 max-w-3xl mx-auto flex flex-col items-center justify-center min-h-[60vh] text-center space-y-8 relative">
                    {{-- Glow Background --}}
                    @if($isDark)
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-primary/10 rounded-full blur-[100px] pointer-events-none"></div>
                    @endif

                    <div class="relative z-10 w-28 h-28 rounded-full flex items-center justify-center shadow-2xl {{ $isDark ? 'bg-gray-900 border border-primary/30 text-primary shadow-[0_0_30px_rgba(197,160,89,0.2)]' : 'bg-primary/10 border border-primary/20 text-primary' }}">
                        <x-heroicon-o-cloud-arrow-down class="w-14 h-14" />
                    </div>

                    <div class="relative z-10 space-y-4">
                        <h2 class="text-3xl sm:text-4xl font-serif font-bold {{ $isDark ? 'text-white drop-shadow-md' : 'text-gray-900' }}">Digitales Produkt</h2>
                        <p class="text-sm sm:text-base max-w-lg mx-auto leading-relaxed {{ $isDark ? 'text-gray-400' : 'text-gray-600' }}">
                            Dieser Artikel ist ein <strong>Sofort-Download</strong>. <br>Nach erfolgreicher Bestellung steht Ihnen die Datei sofort in höchster Qualität zur Verfügung.
                        </p>
                    </div>

                    @if($product->description)
                        <div class="relative z-10 mt-8 p-6 sm:p-8 rounded-[2rem] text-left w-full shadow-inner border {{ $isDark ? 'bg-gray-900/80 backdrop-blur-xl border-gray-800 text-gray-300' : 'bg-gray-50 border-gray-100 text-gray-700' }}">
                            <h4 class="text-[10px] font-black uppercase tracking-[0.2em] mb-4 {{ $isDark ? 'text-primary' : 'text-gray-500' }}">Beschreibung & Details</h4>
                            <div class="prose max-w-none text-sm {{ $isDark ? 'prose-invert prose-p:text-gray-400 prose-headings:text-white' : '' }}">
                                {!! $product->description !!}
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        @include('livewire.shop.configurator.partials.footer', ['isDark' => $isDark])
    </div>

    @include('livewire.shop.configurator.partials.scripts_frontend_2')
    @include('livewire.shop.configurator.partials.scripts_frontend_1')
</div>

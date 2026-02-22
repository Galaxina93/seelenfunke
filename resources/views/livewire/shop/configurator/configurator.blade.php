{{-- resources/views/livewire/shop/configurator/configurator.blade.php --}}
<div class="relative w-full h-full bg-white">
    <div class="h-full flex flex-col"
         x-data="window.frontendConfiguratorData({
            wireModels: {
                texts: $wire.entangle('texts').live,
                logos: $wire.entangle('logos').live
            },
            fonts: @js($fonts),
            context: '{{ $context }}',
            config: {
                modelPath: '{{ $product->three_d_model_path ? asset("storage/".$product->three_d_model_path) : "" }}',
                bgPath: '{{ $product->three_d_background_path ? asset("storage/".$product->three_d_background_path) : "" }}',
                fallbackImg: '{{ $product->preview_image_path ? asset("storage/".$product->preview_image_path) : "" }}',
                area_top: {{ $configSettings['area_top'] ?? 10 }},
                area_left: {{ $configSettings['area_left'] ?? 10 }},
                area_width: {{ $configSettings['area_width'] ?? 80 }},
                area_height: {{ $configSettings['area_height'] ?? 80 }},
                area_shape: '{{ $configSettings['area_shape'] ?? "rect" }}',
                material_type: '{{ $configSettings['material_type'] ?? "glass" }}',
                model_scale: {{ $configSettings['model_scale'] ?? 100 }},
                model_pos_x: {{ $configSettings['model_pos_x'] ?? 0 }},
                model_pos_y: {{ $configSettings['model_pos_y'] ?? 0 }},
                model_pos_z: {{ $configSettings['model_pos_z'] ?? 0 }},
                model_rot_x: {{ $configSettings['model_rot_x'] ?? 0 }},
                model_rot_y: {{ $configSettings['model_rot_y'] ?? 0 }},
                model_rot_z: {{ $configSettings['model_rot_z'] ?? 0 }},
                engraving_scale: {{ $configSettings['engraving_scale'] ?? 100 }},
                engraving_pos_x: {{ $configSettings['engraving_pos_x'] ?? 0 }},
                engraving_pos_y: {{ $configSettings['engraving_pos_y'] ?? 0 }},
                engraving_pos_z: {{ $configSettings['engraving_pos_z'] ?? 0 }},
                engraving_rot_x: {{ $configSettings['engraving_rot_x'] ?? 0 }},
                engraving_rot_y: {{ $configSettings['engraving_rot_y'] ?? 0 }},
                engraving_rot_z: {{ $configSettings['engraving_rot_z'] ?? 0 }},
                custom_points: @js($configSettings['custom_points'] ?? [])
            }
         })"
         @mousemove.window="handleMouseMove($event)"
         @mouseup.window="handleMouseUp($event)"
         @touchmove.window="handleMouseMove($event)"
         @touchend.window="handleMouseUp($event)">

        <div class="flex-1 custom-scrollbar pb-20">
            @if(!$isDigital)
                @include('livewire.shop.configurator.partials.preview')
                @include('livewire.shop.configurator.partials.formluar')
            @else
                {{-- Digitaler Download View... --}}
            @endif
        </div>

        @include('livewire.shop.configurator.partials.footer')
    </div>

    {{-- Hier binden wir die aufgeteilten Scripte ein --}}
    @include('livewire.shop.configurator.partials.scripts_backend')
    @include('livewire.shop.configurator.partials.scripts_frontend')
</div>

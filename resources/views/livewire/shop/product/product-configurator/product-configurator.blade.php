@php
    $isDark = ($design ?? 'light') === 'dark';

    $type            = $this->product->type;
    $isDigital       = $type === 'digital';
    $isService       = $type === 'service';
    $isPhysical      = $type === 'physical';

@endphp

<div :class="isMaximized ? 'configurator-studio-root overflow-hidden flex flex-col {{ $isDark ? 'bg-gray-950 text-gray-200' : 'bg-white text-gray-900' }}' : 'relative w-full h-full {{ $isDark ? 'bg-gray-950 text-gray-300' : 'bg-white text-gray-900' }}'"
     :style="isMaximized ? 'position: fixed !important; top: 0px !important; left: 0px !important; right: 0px !important; bottom: 0px !important; width: 100vw !important; height: 100vh !important; max-width: 100vw !important; max-height: 100vh !important; z-index: 2147483647 !important; margin: 0 !important;' : ''"
     x-data="window.frontendConfiguratorData({
        productId: '{{ $product->id }}',
        wireModels: {
            texts: $wire.entangle('texts').live,
            logos: $wire.entangle('logos').live,
            texts_back: $wire.entangle('texts_back').live,
            logos_back: $wire.entangle('logos_back').live,
            activeSide: $wire.entangle('activeSide').live,
            backgroundTheme: $wire.entangle('backgroundTheme').live
        },
        fonts: @js($fonts),
        context: '{{$context}}',
        config: {
            modelPath: '{{ ($product->three_d_active && $product->three_d_model_path) ? asset("storage/".$product->three_d_model_path) : "" }}',
            bgPath: '{{ $this->resolvedBackgroundPath }}',
            fallbackImg: '{{ $this->previewImage }}',
            templatePackage: '{{ $configSettings["template_package"] ?? "custom" }}',
            enableBgVideo: {{ (!isset($configSettings['enable_background_video']) || $configSettings['enable_background_video']) ? 'true' : 'false' }},
            customBgVideo: '{{ !empty($configSettings["background_video_path"]) ? asset("storage/".$configSettings["background_video_path"]) : "" }}',
            themes: @js(collect($backgroundThemes ?? [])->map(function($t) {
                $vDisk = storage_path('app/public/' . $t['video']);
                $iDisk = storage_path('app/public/' . $t['image']);
                $vVer = is_file($vDisk) ? '?v=' . filemtime($vDisk) : '';
                $iVer = is_file($iDisk) ? '?v=' . filemtime($iDisk) : '';
                return array_merge($t, [
                    'videoUrl' => asset('storage/' . $t['video']) . $vVer,
                    'imageUrl' => asset('storage/' . $t['image']) . $iVer,
                ]);
            })),
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
         @touchend.window="handleMouseUp($event)"
         @keydown.window="handleKeyDown($event)">

    <style>
        /* Studio Fullscreen Mode Scoped Styles */
        html.configurator-fullscreen-active,
        html.configurator-fullscreen-active body {
            overflow: hidden !important;
            height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        html.configurator-fullscreen-active #referral-coupon-banner,
        html.configurator-fullscreen-active .referral-coupon-banner,
        html.configurator-fullscreen-active #navbar,
        html.configurator-fullscreen-active header,
        html.configurator-fullscreen-active nav,
        html.configurator-fullscreen-active footer,
        html.configurator-fullscreen-active #frontend-action-dock,
        html.configurator-fullscreen-active [class*="action_dock"] {
            display: none !important;
        }

        /* Verhindert, dass übergeordnete Container (z.B. in der Bestell-Detailansicht) durch transform, filter oder backdrop-filter das position:fixed einschränken */
        html.configurator-fullscreen-active *:has(.configurator-studio-root) {
            transform: none !important;
            animation: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            filter: none !important;
            perspective: none !important;
            contain: none !important;
            overflow: visible !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        .configurator-studio-root {
            position: fixed !important;
            top: 0px !important;
            left: 0px !important;
            right: 0px !important;
            bottom: 0px !important;
            width: 100vw !important;
            height: 100vh !important;
            max-width: 100vw !important;
            max-height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
            z-index: 2147483647 !important;
            inset: 0 !important;
        }

        .configurator-studio-body {
            display: flex;
            flex-direction: column;
            flex: 1 1 0%;
            min-height: 0;
            overflow-y: auto;
            width: 100%;
            scrollbar-gutter: stable;
        }

        .custom-scrollbar {
            scrollbar-gutter: stable;
        }

        @media (min-width: 1024px) {
            .configurator-studio-body {
                flex-direction: row;
                overflow: hidden;
            }

            .configurator-studio-preview {
                flex: 1 1 0% !important;
                min-width: 0 !important;
                height: 100% !important;
                overflow-y: auto !important;
                scrollbar-gutter: stable;
            }

            .configurator-studio-form {
                width: 480px !important;
                max-width: 42vw !important;
                flex-shrink: 0 !important;
                height: 100% !important;
                overflow-y: auto !important;
                scrollbar-gutter: stable;
            }
        }

        @media (max-width: 1023px) {
            .configurator-studio-form {
                width: 100% !important;
                max-width: 100% !important;
            }
        }
    </style>

    <div class="h-full flex flex-col flex-1 min-h-0 overflow-hidden">

        {{-- MAXIMIZED STUDIO TOP BAR --}}
        <div x-show="isMaximized" x-cloak
             class="w-full px-4 sm:px-6 py-3 border-b flex items-center justify-between shrink-0 z-50 backdrop-blur-md {{ $isDark ? 'bg-gray-950/95 border-gray-800 text-white' : 'bg-white/95 border-gray-200 text-gray-900' }}">
            
            {{-- Linke Seite: Badge & Titel --}}
            <div class="flex items-center gap-3 min-w-0">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest {{ $isDark ? 'bg-primary/20 text-primary border border-primary/30' : 'bg-primary/10 text-primary border border-primary/20' }}">
                    <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                    Studio-Modus
                </span>
                <h3 class="text-sm font-bold truncate hidden sm:block max-w-xs md:max-w-md">{{ $product->name }}</h3>
            </div>

            {{-- Mitte: Varianten Auswahl wenn vorhanden --}}
            @if(!empty($this->activeVariants) && $context !== 'preview')
                <div class="hidden md:flex items-center gap-2 max-w-xs">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 shrink-0">Ausführung:</span>
                    <select wire:model.live="variantId" class="text-xs font-bold rounded-xl border py-1.5 px-3 truncate outline-none cursor-pointer {{ $isDark ? 'bg-gray-900 border-gray-800 text-white' : 'bg-gray-50 border-gray-200 text-gray-900' }}">
                        @foreach($this->activeVariants as $variant)
                            <option value="{{ $variant['id'] }}" class="{{ $isDark ? 'bg-gray-900 text-white' : 'bg-white text-gray-900' }}">
                                {{ $variant['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Rechte Seite: Verkleinern / Esc & Hinweise --}}
            <div class="flex items-center gap-2">
                @if(!$isDigital && !$isService)
                <button type="button"
                        @click="showNotice = !showNotice"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold transition-all shadow-sm cursor-pointer {{ $isDark ? 'bg-gray-900 hover:bg-gray-800 text-gray-300 border-gray-700 hover:text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700 border-gray-200' }}"
                        :title="showNotice ? 'Hinweise ausblenden' : 'Hinweise anzeigen'">
                    <x-heroicon-s-information-circle class="w-4 h-4 text-primary" />
                    <span x-text="showNotice ? 'Hinweise ausblenden' : 'Hinweise einblenden'"></span>
                </button>
                @endif

                <button type="button"
                        @click="toggleMaximize()"
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl border text-xs font-bold transition-all shadow-sm cursor-pointer {{ $isDark ? 'bg-gray-900 hover:bg-gray-800 text-gray-200 border-gray-700 hover:text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-800 border-gray-200' }}">
                    <x-heroicon-m-arrows-pointing-in class="w-4 h-4 text-primary" />
                    <span>Normalansicht</span>
                    <kbd class="hidden sm:inline-block px-1.5 py-0.5 text-[9px] font-mono rounded border {{ $isDark ? 'bg-gray-800 border-gray-700 text-gray-400' : 'bg-white border-gray-300 text-gray-500' }}">Esc</kbd>
                </button>
            </div>
        </div>

        {{-- Varianten Auswahl ganz oben, VOR der Scroll-Area! (Nur in Normalansicht anzeigen) --}}
        @if(!empty($this->activeVariants) && $context !== 'preview')
            <div x-show="!isMaximized" class="px-6 pt-6 pb-2 w-full max-w-[600px] mx-auto shrink-0 z-50">
                <label class="block text-[10px] font-black uppercase tracking-widest text-center mb-2 {{ $isDark ? 'text-gray-400' : 'text-gray-500' }}">Ausführung wählen</label>
                <div class="relative">
                    <select wire:model.live="variantId" class="w-full rounded-2xl border p-3.5 text-sm font-bold shadow-sm transition-all focus:ring-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer outline-none {{ $isDark ? 'bg-gray-900 border-gray-800 text-white' : 'bg-gray-50 border-gray-200 text-gray-900' }}">
                        @foreach($this->activeVariants as $variant)
                            <option value="{{ $variant['id'] }}" class="{{ $isDark ? 'bg-gray-900 text-white' : 'bg-white text-gray-900' }}">
                                {{ $variant['name'] }}
                                @if(!empty($variant['price']))
                                    @php
                                        $diff = (float)$variant['price'] - ($this->product->price / 100);
                                    @endphp
                                    @if($diff > 0)
                                        (+{{ number_format($diff, 2, ',', '.') }} €)
                                    @elseif($diff < 0)
                                        (-{{ number_format(abs($diff), 2, ',', '.') }} €)
                                    @endif
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-primary">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" /></svg>
                    </div>
                </div>
            </div>
        @elseif($context === 'preview' && $variantName)
            <div x-show="!isMaximized" class="px-6 pt-6 pb-2 w-full max-w-[600px] mx-auto shrink-0 z-50">
                <div class="border p-3.5 rounded-2xl text-center {{ $isDark ? 'bg-primary/10 border-primary/20' : 'bg-primary/5 border-primary/20' }}">
                    <span class="text-[9px] font-black uppercase tracking-widest text-primary block mb-0.5">Gewählte Ausführung</span>
                    <span class="font-bold text-sm {{ $isDark ? 'text-white' : 'text-gray-900' }}">{{ $variantName }}</span>
                </div>
            </div>
        @endif

        <div :class="isMaximized ? 'configurator-studio-body' : 'flex-1 custom-scrollbar pb-20 w-full min-w-0'">
            @if(!$this->product->isPersonalizable())
                {{-- Standard-Produkt (Nicht Personalisierbar) Layout --}}
                <div class="p-6 sm:p-12 max-w-3xl mx-auto flex flex-col items-center justify-center text-center space-y-8 relative w-full mt-4">
                    {{-- Glow Background --}}
                    @if($isDark)
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-primary/10 rounded-full blur-[100px] pointer-events-none"></div>
                    @endif

                    <div class="relative z-10 w-28 h-28 rounded-full flex items-center justify-center shadow-2xl {{ $isDark ? 'bg-gray-900 border border-primary/30 text-primary shadow-[0_0_30px_rgba(197,160,89,0.2)]' : 'bg-primary/10 border border-gray-200 text-gray-600' }}">
                         <svg class="w-14 h-14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                    </div>

                    <div class="relative z-10 space-y-4">
                        <h2 class="text-3xl sm:text-4xl font-serif font-bold {{ $isDark ? 'text-white drop-shadow-md' : 'text-gray-900' }}">Bereit zum Kauf</h2>
                        <p class="text-sm sm:text-base max-w-lg mx-auto leading-relaxed {{ $isDark ? 'text-gray-400' : 'text-gray-600' }}">
                            Dieses Produkt ist ein vorgefertigter Artikel und benötigt keine weitere Personalisierung.<br>
                            Wählen Sie einfach die gewünschte Menge und legen Sie es in den Warenkorb.
                        </p>
                    </div>
                </div>
            @elseif(!$isDigital && !$isService)
                {{-- Physisches Produkt Layout --}}
                @include('livewire.shop.product.product-configurator.partials.preview', ['isDark' => $isDark])
                @include('livewire.shop.product.product-configurator.partials.formluar', ['isDark' => $isDark])
            @elseif($isDigital)
                {{-- Digitales Produkt Layout --}}
                <div class="p-6 sm:p-12 max-w-3xl mx-auto flex flex-col items-center justify-center min-h-[60vh] text-center space-y-8 relative w-full">
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
            @elseif($isService)
                {{-- Dienstleistung / Service Layout --}}
                <div class="p-6 sm:p-12 max-w-3xl mx-auto flex flex-col items-center justify-center min-h-[60vh] text-center space-y-8 relative w-full">
                    {{-- Glow Background --}}
                    @if($isDark)
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-primary/10 rounded-full blur-[100px] pointer-events-none"></div>
                    @endif

                    <div class="relative z-10 w-28 h-28 rounded-full flex items-center justify-center shadow-2xl {{ $isDark ? 'bg-gray-900 border border-primary/30 text-primary shadow-[0_0_30px_rgba(197,160,89,0.2)]' : 'bg-primary/10 border border-primary/20 text-primary' }}">
                        <x-heroicon-o-calendar-days class="w-14 h-14" />
                    </div>

                    <div class="relative z-10 space-y-4">
                        <h2 class="text-3xl sm:text-4xl font-serif font-bold {{ $isDark ? 'text-white drop-shadow-md' : 'text-gray-900' }}">Dienstleistung / Service</h2>
                        <p class="text-sm sm:text-base max-w-lg mx-auto leading-relaxed {{ $isDark ? 'text-gray-400' : 'text-gray-600' }}">
                            Dieser Artikel ist eine <strong>Dienstleistung</strong> oder eine persönliche Beratung. <br>Nach der erfolgreichen Buchung setzen wir uns bezüglich des weiteren Ablaufs direkt mit Ihnen in Verbindung.
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

        @include('livewire.shop.product.product-configurator.partials.footer', ['isDark' => $isDark])
    </div>

    @include('livewire.shop.product.product-configurator.partials.scripts_frontend_2')
    @include('livewire.shop.product.product-configurator.partials.scripts_frontend_1')
    @assets
    <script src="{{ asset('vendor/html2canvas/html2canvas.min.js') }}"></script>
    <script src="{{ asset('js/admin-bundle.js') }}"></script>
    @endassets
</div>

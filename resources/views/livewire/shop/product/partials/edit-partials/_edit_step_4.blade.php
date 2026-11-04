<div>
    @if($currentStep === 4)
        @php
            $productTemplates = $product->templates ?? collect();
            $activeTemplatesCount = $productTemplates->where('is_active', true)->count();

            $sub1Done = !empty($product->preview_image_path) || !empty($product->three_d_model_path);
            $overlayType = $configSettings['overlay_type'] ?? 'cylinder';
            $sub2Done = $overlayType === 'cylinder'
                ? (!empty($configSettings['xtool_d_top']) && !empty($configSettings['xtool_height']))
                : (!empty($configSettings['xtool_plane_width']) && !empty($configSettings['xtool_plane_height']));
            $sub3Done = !empty($configSettings['area_width']) && !empty($configSettings['area_height']);
            $sub4Done = $activeTemplatesCount > 0;

            $isStudioPersonalizable = $is_personalizable && (($configSettings['personalization_mode'] ?? 'studio') === 'studio');

            $step4Tabs = [
                1 => ['title' => 'Dateien & 3D-Assets', 'done' => $sub1Done, 'required' => $isStudioPersonalizable],
                2 => ['title' => 'xTool Laser-Parameter', 'done' => $sub2Done, 'required' => false],
                3 => ['title' => 'Live-Konfigurator', 'done' => $sub3Done, 'required' => false],
                4 => ['title' => 'Gravur- & Saison-Vorlagen', 'done' => $sub4Done, 'required' => $isStudioPersonalizable],
            ];
        @endphp

        @if(!$isStudioPersonalizable)
            <div class="mb-6 p-5 rounded-[2rem] bg-emerald-50/80 dark:bg-emerald-950/25 border border-emerald-200 dark:border-emerald-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 font-bold text-lg">✓</div>
                    <div class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                        @if(!$is_personalizable)
                            <span class="font-bold text-gray-900 dark:text-white block text-sm mb-0.5">Standard-Produkt (Sofortkauf ohne Konfigurator)</span>
                            Für dieses vorproduzierte Produkt werden keine 3D-Modelle (.glb) oder 2D-Konfigurator-Overlays benötigt. Du kannst die xTool-Laser-Parameter (4.2) optional für deine interne Produktion hinterlegen oder das Produkt direkt veröffentlichen.
                        @else
                            <span class="font-bold text-gray-900 dark:text-white block text-sm mb-0.5">Schnell-Personalisierung mit dynamischen Eingabefeldern aktiv</span>
                            Deine individuellen Eingabefelder sind bereits in Schritt 1.1 eingerichtet. Es werden keine 3D-Modelle (.glb) oder 2D-Konfigurator-Overlays benötigt. Du kannst optional xTool-Laser-Parameter (4.2) hinterlegen oder das Produkt direkt veröffentlichen.
                        @endif
                    </div>
                </div>
                <button type="button"
                        wire:click="finish"
                        class="shrink-0 px-5 py-3 rounded-xl bg-[var(--theme-color)] text-white dark:text-gray-900 text-[10px] font-black uppercase tracking-widest shadow-sm hover:opacity-90 transition-all cursor-pointer">
                    Jetzt veröffentlichen &rarr;
                </button>
            </div>
        @endif

        {{-- Unsichtbarer Wrapper hält den Alpine.js Status für alle Blöcke zusammen --}}
        <div class="space-y-6"
             @include('livewire.shop.product.partials.edit-partials.step_4_partials.three_js_main')
             x-on:livewire-upload-start="isUploading = true"
             x-on:livewire-upload-finish="isUploading = false"
             x-on:livewire-upload-error="isUploading = false">

            {{-- SUB-STEP FORTSCHRITTS-LEISTE UNTER "4. KONFIGURATOR & VORLAGEN" --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-3 sm:p-4 rounded-[2rem] shadow-sm border border-gray-200 dark:border-gray-800">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2">
                    @foreach($step4Tabs as $subIdx => $subTab)
                        @php
                            $isCurrentSub = ($step4SubStep === $subIdx);
                        @endphp
                        <button type="button"
                                wire:click="goToStep4SubStep({{ $subIdx }})"
                                class="flex items-center justify-between gap-2 px-3.5 py-2.5 rounded-2xl text-left transition-all cursor-pointer border {{ $isCurrentSub ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-30)] text-gray-900 dark:text-white shadow-xs' : 'bg-gray-50/70 dark:bg-gray-950/60 border-gray-200/70 dark:border-gray-800/80 text-gray-500 hover:text-gray-900 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-700' }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-6 h-6 rounded-lg text-[10px] font-black flex items-center justify-center shrink-0 transition-colors {{ $isCurrentSub ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-xs' : ($subTab['done'] ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'bg-gray-200 dark:bg-gray-800 text-gray-500') }}">
                                    @if($subTab['done'] && !$isCurrentSub)
                                        ✓
                                    @else
                                        4.{{ $subIdx }}
                                    @endif
                                </span>
                                <span class="text-[11px] font-bold truncate">{{ $subTab['title'] }}</span>
                            </div>

                            @if($subTab['done'])
                                <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0" title="Vollständig"></span>
                            @elseif($subTab['required'])
                                <span class="px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 shrink-0">Offen</span>
                            @else
                                <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider bg-gray-200/70 dark:bg-gray-800 text-gray-500 shrink-0">Opt.</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- SUB-STEP 4.1: DATEIEN & 3D-ASSETS --}}
            <div x-show="$wire.step4SubStep === 1" x-cloak class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-10 rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 animate-fade-in-up">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-5 mb-8">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-xl bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-20)] flex items-center justify-center shadow-inner font-bold text-xs shrink-0">4.1</span>
                        <div>
                            <h2 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">Dateien & 3D-Assets</h2>
                            <p class="text-[11px] text-gray-500">Vorlage-Paket, 2D Vorschau-Overlay (PNG), 3D-Modell (.glb) und Bühnen-Hintergrund.</p>
                        </div>
                    </div>
                    @include('components.alerts.info-tooltip', ['key' => 'configurator_files_help'])
                </div>

                <div class="grid grid-cols-1 gap-8">
                    @include('livewire.shop.product.partials.edit-partials.step_4_partials.file_uploads')
                </div>
            </div>

            {{-- SUB-STEP 4.2: XTOOL LASER-PARAMETER --}}
            <div x-show="$wire.step4SubStep === 2" x-cloak class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-10 rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 animate-fade-in-up">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-5 mb-8">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-xl bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-20)] flex items-center justify-center shadow-inner font-bold text-xs shrink-0">4.2</span>
                        <div>
                            <h2 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">xTool Laser-Parameter</h2>
                            <p class="text-[11px] text-gray-500">Physische Millimeter-Maße für den maßstabsgetreuen 1:1 SVG-Laser-Export.</p>
                        </div>
                    </div>
                    @include('components.alerts.info-tooltip', ['key' => 'xtool_params_help'])
                </div>

                @include('livewire.shop.product.partials.edit-partials.step_4_partials.dimensions')
            </div>

            {{-- SUB-STEP 4.3: LIVE-KONFIGURATOR & GRAVURFLÄCHE --}}
            <div x-show="$wire.step4SubStep === 3" x-cloak class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-10 rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 animate-fade-in-up">
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
                        @if($three_d_active)
                            @include('livewire.shop.product.partials.edit-partials.step_4_partials.3d_model_config_ranges')
                            @include('livewire.shop.product.partials.edit-partials.step_4_partials.3d_overlay_config_ranges')
                        @endif
                    </div>

                </div>
            </div>

            {{-- SUB-STEP 4.4: GRAVUR- & SAISON-VORLAGEN DIESES PRODUKTS --}}
            <div x-show="$wire.step4SubStep === 4" x-cloak class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-10 rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 animate-fade-in-up space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-5">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-xl bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-20)] flex items-center justify-center shadow-inner font-bold text-xs shrink-0">4.4</span>
                            <h3 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">Gravur- &amp; Saison-Vorlagen</h3>
                            @if($activeTemplatesCount > 0)
                                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[10px] font-black uppercase tracking-wider">
                                    ✓ {{ $activeTemplatesCount }} aktiv
                                </span>
                            @else
                                <span class="px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[10px] font-black uppercase tracking-wider">
                                    ⚠️ Keine Vorlage
                                </span>
                            @endif
                            @include('components.alerts.info-tooltip', ['key' => 'product_templates_help'])
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Schnellstart-Designs im Shop-Konfigurator und saisonale Hauptbilder für „{{ $name ?: $product->name }}“.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button"
                                wire:click="openTemplateEditorForProduct(null, true)"
                                class="px-4 py-2.5 rounded-xl bg-[var(--theme-color)] text-white dark:text-gray-900 text-[10px] font-black uppercase tracking-widest hover:brightness-95 transition cursor-pointer shadow-xs">
                            + Neue Vorlage erstellen
                        </button>
                        <button type="button"
                                wire:click="openTemplateEditorForProduct()"
                                class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:text-[var(--theme-color)] text-[10px] font-bold uppercase tracking-widest transition cursor-pointer">
                            Vorlagen-Übersicht &rarr;
                        </button>
                    </div>
                </div>

                @if($productTemplates->isEmpty())
                    <div class="p-8 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-dashed border-gray-200 dark:border-gray-800 text-center space-y-3">
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-300">Für dieses Produkt sind noch keine Gravur-Vorlagen hinterlegt.</p>
                        <button type="button"
                                wire:click="openTemplateEditorForProduct(null, true)"
                                class="px-4 py-2 rounded-xl bg-[var(--theme-color)] text-white dark:text-gray-900 text-xs font-bold hover:brightness-95 transition cursor-pointer">
                            Erste Vorlage für dieses Produkt anlegen
                        </button>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @foreach($productTemplates as $tpl)
                            @php
                                $tplImg = $tpl->preview_image
                                    ? (str_starts_with($tpl->preview_image, 'shop/') ? asset($tpl->preview_image) : asset('storage/' . ltrim($tpl->preview_image, '/')))
                                    : null;
                            @endphp
                            <div class="p-3.5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/70 flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-14 h-14 rounded-xl overflow-hidden bg-gray-200 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shrink-0">
                                        @if($tplImg)
                                            <img src="{{ $tplImg }}" alt="{{ $tpl->name }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-[10px] text-gray-400">2D/3D</div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="text-xs font-bold text-gray-900 dark:text-white block truncate">{{ $tpl->name }}</span>
                                        @if($tpl->holiday)
                                            <span class="inline-block mt-0.5 px-2 py-0.5 rounded-md bg-[var(--theme-color-10)] text-[var(--theme-color)] text-[9px] font-bold">
                                                {{ $tpl->holiday }}
                                            </span>
                                        @else
                                            <span class="text-[10px] text-gray-400 block mt-0.5">Ganzjährig</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-gray-200/70 dark:border-gray-800 flex items-center justify-between gap-2 text-[10px] font-bold">
                                    <button type="button"
                                            wire:click="toggleProductTemplateActive('{{ $tpl->id }}')"
                                            class="px-2 py-1 rounded-lg border cursor-pointer transition {{ $tpl->is_active ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30' : 'bg-gray-100 dark:bg-gray-900 text-gray-500 border-gray-200 dark:border-gray-800' }}">
                                        {{ $tpl->is_active ? '● Aktiv' : '○ Inaktiv' }}
                                    </button>
                                    <button type="button"
                                            wire:click="openTemplateEditorForProduct('{{ $tpl->id }}')"
                                            class="text-[var(--theme-color)] hover:underline cursor-pointer">
                                        Bearbeiten &rarr;
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

    @endif
</div>

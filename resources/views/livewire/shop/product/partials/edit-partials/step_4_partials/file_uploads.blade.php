<div class="space-y-6">
    {{-- VORLAGE-PAKET & THEMEN-HINTERGRUNDVIDEOS --}}
    @php
        $cfgService = app(\App\Services\ConfiguratorService::class);
        $templatePackages = $cfgService->getTemplatePackages();
        $bgThemes = $cfgService->getBackgroundThemes();
        $activePkgKey = $configSettings['template_package'] ?? 'custom';
        $activeThemeKey = $configSettings['default_background_theme'] ?? 'standard';
        $activeThemeObj = $bgThemes[$activeThemeKey] ?? $bgThemes['standard'];
        $activeVideoRelPath = !empty($configSettings['background_video_path'])
            ? $configSettings['background_video_path']
            : ($activeThemeObj['video'] ?? null);

        $videoMeta = null;
        if ($activeVideoRelPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($activeVideoRelPath)) {
            $vAbs = \Illuminate\Support\Facades\Storage::disk('public')->path($activeVideoRelPath);
            $vSize = (int) @filesize($vAbs);
            $videoMeta = [
                'size' => $vSize >= 1048576 ? number_format($vSize / 1048576, 2, ',', '.') . ' MB' : number_format($vSize / 1024, 1, ',', '.') . ' KB',
                'format' => 'H.264 MP4 (960 × 640 px, 30 FPS Loop)',
                'modified' => date('d.m.Y H:i', (int) @filemtime($vAbs)),
                'path' => 'public/' . $activeVideoRelPath,
                'rel_path' => $activeVideoRelPath,
            ];
        }
    @endphp
    <div class="p-6 sm:p-8 bg-amber-500/5 dark:bg-amber-900/10 backdrop-blur-md rounded-[2rem] border border-amber-200 dark:border-amber-500/20 shadow-inner relative overflow-visible group hover:border-amber-300 dark:hover:border-amber-500/40 transition-colors">
        <div class="hidden dark:block absolute right-0 top-0 w-36 h-36 bg-amber-500/10 rounded-full blur-[50px] -mr-10 -mt-10 pointer-events-none"></div>

        <div class="relative z-10 space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5 mb-1.5">
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-amber-600 dark:text-amber-400 dark:drop-shadow-[0_0_8px_currentColor]">
                            Vorlage-Paket & Themen-Hintergründe
                        </label>
                        @if($activePkgKey !== 'custom' && isset($templatePackages[$activePkgKey]))
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                                Paket aktiv: {{ $activePkgKey }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-amber-950/70 dark:text-amber-200/60 font-medium">
                        Bestimmt automatisch das <strong>2D Vorschau Overlay (PNG)</strong>, den <strong>3D Hintergrund (.jpg/.png)</strong> sowie die komprimierten <strong>Themen-Hintergrundvideos (.mp4)</strong> (z. B. Schneefall im Winter).
                    </p>
                </div>

                <label class="inline-flex items-center gap-2 cursor-pointer text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300 bg-amber-500/10 px-3 py-2 rounded-xl border border-amber-500/25 shrink-0">
                    <input type="checkbox" wire:model.live="configSettings.enable_background_video" class="rounded border-amber-400 text-amber-600 focus:ring-amber-500 w-3.5 h-3.5">
                    <span>Themen-Videos aktiv</span>
                </label>
            </div>

            {{-- Paket-Auswahl Dropdown --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Zugewiesenes Vorlage-Paket</label>
                    <select wire:change="applyTemplatePackage($event.target.value)"
                            class="w-full bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-3 text-xs font-bold text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-amber-500 outline-none cursor-pointer">
                        @foreach($templatePackages as $pkgKey => $pkg)
                            <option value="{{ $pkgKey }}" @selected($activePkgKey === $pkgKey)>
                                {{ $pkg['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Standard-Atmosphäre / Themen-Video (Vorschau)</label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($bgThemes as $tKey => $tData)
                            <button type="button"
                                    wire:click="selectBackgroundTheme('{{ $tKey }}')"
                                    class="px-2.5 py-2 rounded-xl text-[10px] font-bold transition-all border cursor-pointer flex items-center gap-1 {{ $activeThemeKey === $tKey ? 'bg-amber-500 text-gray-950 border-amber-400 shadow-sm' : 'bg-white dark:bg-gray-950 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-800 hover:border-amber-500/40' }}">
                                <span>{{ $tData['icon'] }}</span>
                                <span>{{ $tData['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Aktives Themen-Video Datei-Badge nach 3-Zonen-Logik --}}
            <div class="pt-3 border-t border-amber-500/15 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                        Aktives Hintergrund-Video ({{ !empty($configSettings['background_video_path']) ? 'Eigenes Video' : 'Thema: ' . $activeThemeObj['label'] }})
                    </span>
                    @if($videoMeta)
                        <div class="inline-flex flex-wrap items-center gap-2 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg px-2.5 py-1.5 w-fit max-w-full" x-data="{ copied: false }">
                            <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            <span class="text-gray-600 dark:text-gray-400 text-[9px] font-mono truncate" title="{{ basename($videoMeta['rel_path']) }}">
                                {{ basename($videoMeta['rel_path']) }}
                            </span>
                            <span class="text-[9px] font-mono font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded">{{ $videoMeta['size'] }}</span>
                            {{-- Copy Button --}}
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $videoMeta['rel_path'] }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="text-gray-500 hover:text-amber-500 transition-colors p-0.5 cursor-pointer"
                                    title="Speicherpfad kopieren">
                                <x-heroicon-s-clipboard-document x-show="!copied" class="w-3.5 h-3.5 text-gray-500 hover:text-amber-500 transition-colors" />
                                <x-heroicon-s-check x-show="copied" style="display: none;" class="w-3.5 h-3.5 text-emerald-500" />
                            </button>
                            {{-- Info Hover Badge --}}
                            <div class="relative group/info inline-flex items-center">
                                <span class="w-4 h-4 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-400 hover:bg-amber-500 hover:text-white flex items-center justify-center text-[9px] font-black cursor-help transition-colors">!</span>
                                <div class="absolute bottom-full left-0 mb-2 hidden group-hover/info:block z-50 w-72 p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-xl text-[10px] font-mono text-gray-700 dark:text-gray-300 space-y-1 pointer-events-none">
                                    <div class="font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1 mb-1">Video-Informationen</div>
                                    <div><span class="text-gray-400">Pfad:</span> <span class="break-all">{{ $videoMeta['path'] }}</span></div>
                                    <div><span class="text-gray-400">Größe:</span> {{ $videoMeta['size'] }} (Web-komprimiert)</div>
                                    <div><span class="text-gray-400">Format:</span> {{ $videoMeta['format'] }}</div>
                                    <div><span class="text-gray-400">Geändert:</span> {{ $videoMeta['modified'] }}</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    @if(!empty($configSettings['background_video_path']))
                        <button type="button" wire:click="removeBackgroundVideo" class="p-2.5 text-gray-500 hover:text-red-500 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-red-500/30 rounded-xl transition-all shadow-inner" title="Eigenes Video entfernen (Standard-Themenvideos nutzen)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    @endif
                    <label class="cursor-pointer bg-amber-50 dark:bg-amber-600/20 border border-amber-200 dark:border-amber-500/30 hover:bg-amber-100 dark:hover:bg-amber-500/30 text-amber-700 dark:text-amber-300 hover:text-amber-900 dark:hover:text-white px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-inner transition-all block" :class="{'opacity-50 pointer-events-none': isUploading}">
                        {{ !empty($configSettings['background_video_path']) ? 'Eigenes Video ändern' : 'Eigenes Video (.mp4)' }}
                        <input type="file" wire:model.live="new_background_video" accept="video/mp4,video/webm" class="hidden">
                    </label>
                </div>
            </div>
        </div>
    </div>

    {{-- 2D Vorschau Overlay --}}
    @php
        $previewMeta = null;
        if ($product->preview_image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($product->preview_image_path)) {
            $pAbs = \Illuminate\Support\Facades\Storage::disk('public')->path($product->preview_image_path);
            $pSize = (int) @filesize($pAbs);
            $pImgSize = @getimagesize($pAbs);
            $previewMeta = [
                'size' => $pSize >= 1048576 ? number_format($pSize / 1048576, 2, ',', '.') . ' MB' : number_format($pSize / 1024, 1, ',', '.') . ' KB',
                'dims' => $pImgSize ? ($pImgSize[0] . ' × ' . $pImgSize[1] . ' px') : null,
                'modified' => date('d.m.Y H:i', (int) @filemtime($pAbs)),
                'path' => 'public/' . $product->preview_image_path,
            ];
        }
    @endphp
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-6 sm:p-8 bg-gray-50 dark:bg-gray-900/50 backdrop-blur-md rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-inner group transition-colors hover:border-gray-300 dark:hover:border-gray-700">
        <div class="flex-1">
            <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-700 dark:text-gray-300 mb-2 dark:drop-shadow-[0_0_8px_currentColor]">2D Vorschau Overlay (PNG)</label>
            <p class="text-xs text-gray-500 mb-3 font-medium">Dient als Zeichenbrett für den Arbeitsbereich.</p>
            @if($product->preview_image_path)
                <div class="mt-3 flex flex-col gap-1.5">
                    <span class="text-emerald-600 dark:text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 dark:drop-shadow-[0_0_8px_currentColor]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        Erfolgreich hochgeladen
                    </span>
                    <div class="inline-flex flex-wrap items-center gap-2 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg px-2.5 py-1.5 w-fit max-w-full" x-data="{ copied: false }">
                        <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span class="text-gray-600 dark:text-gray-400 text-[9px] font-mono truncate" title="{{ basename($product->preview_image_path) }}">
                            {{ basename($product->preview_image_path) }}
                        </span>
                        @if($previewMeta)
                            <span class="text-[9px] font-mono font-bold text-gray-500 bg-gray-100 dark:bg-gray-900 px-1.5 py-0.5 rounded">{{ $previewMeta['size'] }}</span>
                        @endif
                        {{-- Copy Button --}}
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $product->preview_image_path }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="text-gray-500 hover:text-[var(--theme-color)] transition-colors p-0.5 cursor-pointer"
                                title="Speicherpfad kopieren">
                            <x-heroicon-s-clipboard-document x-show="!copied" class="w-3.5 h-3.5 text-gray-500 hover:text-[var(--theme-color)] transition-colors" />
                            <x-heroicon-s-check x-show="copied" style="display: none;" class="w-3.5 h-3.5 text-emerald-500" />
                        </button>
                        {{-- Info Hover Badge --}}
                        @if($previewMeta)
                            <div class="relative group/info inline-flex items-center">
                                <span class="w-4 h-4 rounded-full bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:text-amber-500 hover:border-amber-500/50 flex items-center justify-center text-[9px] font-black cursor-help transition-colors">!</span>
                                <div class="absolute bottom-full left-0 mb-2 hidden group-hover/info:block z-50 w-64 p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-xl text-[10px] font-mono text-gray-700 dark:text-gray-300 space-y-1 pointer-events-none">
                                    <div class="font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1 mb-1">Datei-Informationen</div>
                                    <div><span class="text-gray-400">Pfad:</span> <span class="break-all">{{ $previewMeta['path'] }}</span></div>
                                    <div><span class="text-gray-400">Größe:</span> {{ $previewMeta['size'] }}</div>
                                    @if($previewMeta['dims'])
                                        <div><span class="text-gray-400">Auflösung:</span> {{ $previewMeta['dims'] }}</div>
                                    @endif
                                    <div><span class="text-gray-400">Geändert:</span> {{ $previewMeta['modified'] }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div class="flex items-center gap-4">
            @if($product->preview_image_path)
                <button wire:click="removePreviewImage" class="p-3 text-gray-500 hover:text-red-500 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-red-500/30 rounded-xl transition-all shadow-inner" title="Löschen">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            @endif
            <div class="flex-shrink-0 relative">
                <label class="cursor-pointer bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] text-gray-700 dark:text-gray-400 hover:text-[var(--theme-color)] px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-inner transition-all flex items-center justify-center gap-2" :class="{'opacity-50 pointer-events-none': isUploading}">
                    {{ $product->preview_image_path ? 'Ändern' : 'Wählen' }}
                    <input type="file" wire:model.live="new_preview_image" accept="image/png" class="hidden">
                </label>
            </div>
        </div>
    </div>

    @if($three_d_active)
    {{-- 3D-Modell (.glb) --}}
    @php
        $glbStats = null;
        if ($product->three_d_model_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($product->three_d_model_path)) {
            $glbAbs = \Illuminate\Support\Facades\Storage::disk('public')->path($product->three_d_model_path);
            $glbStats = app(\App\Services\Storage\GlbOptimizerService::class)->inspectGlb($glbAbs);
        }
    @endphp
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-6 sm:p-8 bg-blue-500/5 dark:bg-blue-900/10 backdrop-blur-md rounded-[2rem] border border-blue-200 dark:border-blue-500/20 shadow-inner relative overflow-visible group hover:border-blue-300 dark:hover:border-blue-500/40 transition-colors">
        <div class="hidden dark:block absolute right-0 top-0 w-32 h-32 bg-blue-500/10 rounded-full blur-[50px] -mr-10 -mt-10 pointer-events-none"></div>
        <div class="flex-1 relative z-10">
            <div class="flex flex-wrap items-center gap-3 mb-2">
                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-blue-600 dark:text-blue-400 dark:drop-shadow-[0_0_8px_currentColor]">3D-Modell (.glb)</label>
                <label class="inline-flex items-center gap-1.5 cursor-pointer text-[9px] font-mono text-blue-700/80 dark:text-blue-300/80 bg-blue-500/10 px-2 py-0.5 rounded-full border border-blue-500/20" title="Optimiert neue .glb Dateien beim Hochladen automatisch verlustfrei (Mesh-Simplification & Textur-Skalierung)">
                    <input type="checkbox" wire:model.live="autoOptimize3dOnUpload" class="rounded border-blue-400 text-blue-600 focus:ring-blue-500 w-3 h-3">
                    <span>Auto-Optimierung beim Upload</span>
                </label>
            </div>
            <p class="text-xs text-blue-950/60 dark:text-blue-200/50 mb-3 font-medium">Interaktive 360° Ansicht (Meshopt & WebGL optimiert).</p>
            @if($product->three_d_model_path)
                <div class="mt-3 flex flex-col gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-emerald-600 dark:text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 dark:drop-shadow-[0_0_8px_currentColor]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            Erfolgreich hochgeladen
                        </span>
                        @if($glbStats && $glbStats['exists'])
                            @if($glbStats['is_optimized'])
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 flex items-center gap-1">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                    3D-Optimiert
                                    @if($glbStats['saved_percent'])
                                        <span>(-{{ $glbStats['saved_percent'] }}%)</span>
                                    @endif
                                </span>
                            @elseif($glbStats['needs_optimization'])
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30 flex items-center gap-1">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    Großes Rohmodell – Optimierung empfohlen
                                </span>
                            @endif
                        @endif
                    </div>

                    <div class="inline-flex flex-wrap items-center gap-2 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg px-2.5 py-1.5 w-fit max-w-full" x-data="{ copied: false }">
                        <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"></path></svg>
                        <span class="text-gray-600 dark:text-gray-400 text-[9px] font-mono truncate" title="{{ basename($product->three_d_model_path) }}">
                            {{ basename($product->three_d_model_path) }}
                        </span>
                        @if($glbStats && $glbStats['exists'])
                            <span class="text-[9px] font-mono font-bold {{ $glbStats['size_mb'] > 5 ? 'text-amber-600 dark:text-amber-400 bg-amber-500/10' : 'text-blue-600 dark:text-blue-400 bg-blue-500/10' }} px-1.5 py-0.5 rounded">
                                {{ $glbStats['size_formatted'] }}
                            </span>
                            <span class="text-[9px] font-mono text-gray-500 bg-gray-100 dark:bg-gray-900 px-1.5 py-0.5 rounded">
                                {{ number_format($glbStats['triangles'], 0, ',', '.') }} Polygone
                            </span>
                        @endif
                        {{-- Copy Button --}}
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $product->three_d_model_path }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="text-gray-500 hover:text-blue-500 transition-colors p-0.5 cursor-pointer"
                                title="Speicherpfad kopieren">
                            <x-heroicon-s-clipboard-document x-show="!copied" class="w-3.5 h-3.5 text-gray-500 hover:text-blue-500 transition-colors" />
                            <x-heroicon-s-check x-show="copied" style="display: none;" class="w-3.5 h-3.5 text-emerald-500" />
                        </button>
                        {{-- Info Hover Badge --}}
                        @if($glbStats && $glbStats['exists'])
                            <div class="relative group/info inline-flex items-center">
                                <span class="w-4 h-4 rounded-full bg-blue-500/10 border border-blue-500/30 text-blue-600 dark:text-blue-400 hover:bg-blue-500 hover:text-white flex items-center justify-center text-[9px] font-black cursor-help transition-colors">!</span>
                                <div class="absolute bottom-full left-0 mb-2 hidden group-hover/info:block z-50 w-72 p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-xl text-[10px] font-mono text-gray-700 dark:text-gray-300 space-y-1 pointer-events-none">
                                    <div class="font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1 mb-1 flex items-center justify-between">
                                        <span>3D-GLB Röntgen-Analyse</span>
                                        <span class="text-blue-500">{{ $glbStats['size_formatted'] }}</span>
                                    </div>
                                    <div><span class="text-gray-400">Pfad:</span> <span class="break-all">public/{{ $product->three_d_model_path }}</span></div>
                                    <div><span class="text-gray-400">Dreiecke:</span> {{ number_format($glbStats['triangles'], 0, ',', '.') }}</div>
                                    <div><span class="text-gray-400">Vertices:</span> {{ number_format($glbStats['vertices'], 0, ',', '.') }}</div>
                                    <div><span class="text-gray-400">Texturen:</span> {{ $glbStats['textures_count'] }} ({{ $glbStats['textures_size_kb'] }} KB)</div>
                                    @if($glbStats['original_size_mb'])
                                        <div class="text-emerald-600 dark:text-emerald-400"><span class="text-gray-400">Originalgröße:</span> {{ number_format($glbStats['original_size_mb'], 2, ',', '.') }} MB (-{{ $glbStats['saved_percent'] }}%)</div>
                                    @endif
                                    <div><span class="text-gray-400">Geändert:</span> {{ $glbStats['last_modified'] }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-3 relative z-10">
            @if($product->three_d_model_path)
                {{-- Manueller 3D-Optimieren Button --}}
                <button type="button"
                        wire:click="optimizeCurrent3dModel"
                        wire:loading.attr="disabled"
                        wire:target="optimizeCurrent3dModel"
                        class="px-4 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-inner flex items-center gap-2 border {{ ($glbStats['needs_optimization'] ?? false) ? 'bg-amber-500 text-gray-950 border-amber-400 hover:bg-amber-400 animate-pulse' : 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30 hover:bg-emerald-100 dark:hover:bg-emerald-500/25' }}"
                        title="Reduziert Polygone & Texturen verlustfrei ohne sichtbaren optischen Unterschied">
                    <span wire:loading.remove wire:target="optimizeCurrent3dModel" class="flex items-center gap-1.5">
                        <i class="bi bi-lightning-charge-fill text-xs"></i>
                        <span>{{ ($glbStats['is_marked_optimized'] ?? false) ? 'Erneut optimieren' : '3D Optimieren' }}</span>
                    </span>
                    <span wire:loading wire:target="optimizeCurrent3dModel" class="flex items-center gap-1.5">
                        <svg class="animate-spin h-3.5 w-3.5 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span>Optimiere 3D...</span>
                    </span>
                </button>

                <button wire:click="remove3dModel" class="p-3 text-gray-500 hover:text-red-500 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-red-500/30 rounded-xl transition-all shadow-inner" title="3D-Modell löschen"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
            @endif
            <div class="flex-shrink-0 relative">
                <label class="cursor-pointer bg-blue-50 dark:bg-blue-600/20 border border-blue-200 dark:border-blue-500/30 hover:bg-blue-100 dark:hover:bg-blue-500/30 text-blue-700 dark:text-blue-300 hover:text-blue-900 dark:hover:text-white px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-inner transition-all block" :class="{'opacity-50 pointer-events-none': isUploading}">
                     {{ $product->three_d_model_path ? 'Ersetzen' : 'Hochladen' }}
                    <input type="file" wire:model.live="new_3d_model" accept=".glb" class="hidden">
                </label>
            </div>
        </div>
    </div>

    {{-- 3D Hintergrund (.jpg/.png) --}}
    @php
        $bgMeta = null;
        if ($product->three_d_background_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($product->three_d_background_path)) {
            $bAbs = \Illuminate\Support\Facades\Storage::disk('public')->path($product->three_d_background_path);
            $bSize = (int) @filesize($bAbs);
            $bImgSize = @getimagesize($bAbs);
            $bgMeta = [
                'size' => $bSize >= 1048576 ? number_format($bSize / 1048576, 2, ',', '.') . ' MB' : number_format($bSize / 1024, 1, ',', '.') . ' KB',
                'dims' => $bImgSize ? ($bImgSize[0] . ' × ' . $bImgSize[1] . ' px') : null,
                'modified' => date('d.m.Y H:i', (int) @filemtime($bAbs)),
                'path' => 'public/' . $product->three_d_background_path,
            ];
        }
    @endphp
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-6 sm:p-8 bg-purple-500/5 dark:bg-purple-900/10 backdrop-blur-md rounded-[2rem] border border-purple-200 dark:border-purple-500/20 shadow-inner relative overflow-visible group hover:border-purple-300 dark:hover:border-purple-500/40 transition-colors">
        <div class="hidden dark:block absolute right-0 top-0 w-32 h-32 bg-purple-500/10 rounded-full blur-[50px] -mr-10 -mt-10 pointer-events-none"></div>
        <div class="flex-1 relative z-10">
            <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-purple-600 dark:text-purple-400 mb-2 dark:drop-shadow-[0_0_8px_currentColor]">3D Hintergrund (.jpg/.png)</label>
            <p class="text-xs text-purple-950/60 dark:text-purple-200/50 mb-3 font-medium">Wird als echter CSS Hintergrund geladen.</p>
            @if($product->three_d_background_path)
                <div class="mt-3 flex flex-col gap-1.5">
                    <span class="text-emerald-600 dark:text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 dark:drop-shadow-[0_0_8px_currentColor]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        Erfolgreich hochgeladen
                    </span>
                    <div class="inline-flex flex-wrap items-center gap-2 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg px-2.5 py-1.5 w-fit max-w-full" x-data="{ copied: false }">
                        <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="text-gray-600 dark:text-gray-400 text-[9px] font-mono truncate" title="{{ basename($product->three_d_background_path) }}">
                            {{ basename($product->three_d_background_path) }}
                        </span>
                        @if($bgMeta)
                            <span class="text-[9px] font-mono font-bold text-purple-600 dark:text-purple-400 bg-purple-500/10 px-1.5 py-0.5 rounded">{{ $bgMeta['size'] }}</span>
                        @endif
                        {{-- Copy Button --}}
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $product->three_d_background_path }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="text-gray-500 hover:text-purple-500 transition-colors p-0.5 cursor-pointer"
                                title="Speicherpfad kopieren">
                            <x-heroicon-s-clipboard-document x-show="!copied" class="w-3.5 h-3.5 text-gray-500 hover:text-purple-500 transition-colors" />
                            <x-heroicon-s-check x-show="copied" style="display: none;" class="w-3.5 h-3.5 text-emerald-500" />
                        </button>
                        {{-- Info Hover Badge --}}
                        @if($bgMeta)
                            <div class="relative group/info inline-flex items-center">
                                <span class="w-4 h-4 rounded-full bg-purple-500/10 border border-purple-500/30 text-purple-600 dark:text-purple-400 hover:bg-purple-500 hover:text-white flex items-center justify-center text-[9px] font-black cursor-help transition-colors">!</span>
                                <div class="absolute bottom-full left-0 mb-2 hidden group-hover/info:block z-50 w-64 p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-xl text-[10px] font-mono text-gray-700 dark:text-gray-300 space-y-1 pointer-events-none">
                                    <div class="font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1 mb-1">Datei-Informationen</div>
                                    <div><span class="text-gray-400">Pfad:</span> <span class="break-all">{{ $bgMeta['path'] }}</span></div>
                                    <div><span class="text-gray-400">Größe:</span> {{ $bgMeta['size'] }}</div>
                                    @if($bgMeta['dims'])
                                        <div><span class="text-gray-400">Auflösung:</span> {{ $bgMeta['dims'] }}</div>
                                    @endif
                                    <div><span class="text-gray-400">Geändert:</span> {{ $bgMeta['modified'] }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div class="flex items-center gap-4 relative z-10">
            @if($product->three_d_background_path)
                <button wire:click="remove3dBackground" class="p-3 text-gray-500 hover:text-red-500 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-red-500/30 rounded-xl transition-all shadow-inner"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
            @endif
            <div class="flex-shrink-0 relative">
                <label class="cursor-pointer bg-purple-50 dark:bg-purple-600/20 border border-purple-200 dark:border-purple-500/30 hover:bg-purple-100 dark:hover:bg-purple-500/30 text-purple-700 dark:text-purple-300 hover:text-purple-900 dark:hover:text-white px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-inner transition-all block" :class="{'opacity-50 pointer-events-none': isUploading}">
                    {{ $product->three_d_background_path ? 'Ersetzen' : 'Hochladen' }}
                    <input type="file" wire:model.live="new_3d_background" accept="image/jpeg, image/png" class="hidden">
                </label>
            </div>
        </div>
    </div>
    @endif

    <div x-show="isUploading" style="display: none;" class="p-6 bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] border border-gray-200 dark:border-gray-800 text-center shadow-inner">
        <svg class="animate-spin h-8 w-8 text-[var(--theme-color)] mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <span class="text-[10px] font-black uppercase tracking-widest mt-4 text-[var(--theme-color)] block dark:drop-shadow-[0_0_8px_currentColor]">Upload läuft...</span>
    </div>
</div>

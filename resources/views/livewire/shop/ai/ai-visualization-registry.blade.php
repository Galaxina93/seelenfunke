<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
    <div class="animate-fade-in-up font-sans antialiased text-[color:var(--theme-color)] pb-28 lg:pb-12 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="md:col-span-full bg-gray-950 backdrop-blur-xl border border-gray-800 rounded-3xl p-6 shadow-xl">
                <p class="text-gray-400 leading-relaxed mb-4 text-sm max-w-4xl">
                    Diese Registratur zeigt alle aktuell verfügbaren Kategorien, die der Agent über das abstrakte Werkzeug <code class="bg-gray-900 px-2 py-1 rounded text-[color:var(--theme-color)] border border-gray-800 shadow-inner">visualize_data</code> aufrufen kann.
                    Die Intelligenz darüber, welches Design oder welches Blade-Template verwendet wird, liegt komplett im Backend in <code class="bg-gray-900 px-2 py-1 rounded text-[color:var(--theme-color)] border border-gray-800 shadow-inner">AiDataVisualization</code>.
                </p>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium bg-[color:var(--theme-color-10)] text-[color:var(--theme-color)] border border-[color:var(--theme-color-20)]">
                    <x-heroicon-o-shield-check class="w-4 h-4" />
                    Verhindert AI-Halluzinationen im Frontend
                </div>
            </div>

            @foreach($registry as $catKey => $catData)
                <div class="bg-gray-900 backdrop-blur-xl border border-gray-800 rounded-3xl p-6 hover:border-[color:var(--theme-color-50)] text-[color:var(--theme-color)] transition-all group relative overflow-hidden font-sans text-sm shadow-lg">

                    <div class="absolute inset-0 bg-current/5 to-transparent pointer-events-none opacity-0 group-hover:opacity-10 transition-opacity"></div>

                    <div class="flex flex-col sm:flex-row sm:items-start gap-4 mb-4 sm:pr-24 relative">
                        <!-- Status Badge Mobile (Top Left inside the card, absolute on desktop) -->
                        <div class="absolute sm:top-6 sm:right-6 top-4 right-4 hidden sm:block">
                            @if($catData['status'] === 'active')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-[color:var(--theme-color-10)] text-[color:var(--theme-color)] border border-[color:var(--theme-color-30)]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--theme-color)] animate-pulse"></span>
                                    Aktiv
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-800 text-gray-400 border border-gray-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span>
                                    Fallback
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center justify-between w-full sm:w-auto">
                            <div class="w-12 h-12 rounded-xl bg-[color:var(--theme-color-10)] border border-[color:var(--theme-color-20)] flex items-center justify-center shadow-inner group-hover:border-[color:var(--theme-color)] transition-colors shrink-0">
                                <span class="font-mono text-[color:var(--theme-color)] font-medium text-sm transition-colors">{{ substr($catKey, 0, 3) }}</span>
                            </div>
                            <!-- Status Badge Mobile Only -->
                            <div class="sm:hidden">
                                @if($catData['status'] === 'active')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-medium bg-[color:var(--theme-color-10)] text-[color:var(--theme-color)] border border-[color:var(--theme-color-30)]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--theme-color)] animate-pulse"></span>
                                        Aktiv
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-medium bg-gray-800 text-gray-400 border border-gray-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span>
                                        Fallback
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-lg font-medium text-gray-200 group-hover:text-[color:var(--theme-color)] transition-colors truncate">{{ $catData['name'] }}</h3>
                            <p class="text-xs text-gray-500 mt-1 transition-colors">Key: <span class="font-mono">{{ $catKey }}</span></p>
                        </div>
                    </div>

                    <p class="text-sm text-gray-400 mb-6 line-clamp-2 h-10 transition-colors">{{ $catData['description'] }}</p>

                    <div class="space-y-3 pt-4 border-t border-gray-800 group-hover:border-gray-700 transition-colors">
                        <div class="text-xs font-medium text-gray-500 pb-1">Registrierte Blade-Views</div>

                        @foreach($catData['views'] as $viewName => $viewPath)
                            <div class="flex flex-col xl:flex-row xl:items-center justify-between bg-gray-950 p-3 rounded-xl border border-gray-800 transition-colors gap-2">
                                <span class="text-xs font-medium text-gray-400 shrink-0">{{ $viewName }}</span>
                                @if(str_contains($viewPath, 'livewire.'))
                                    <span class="text-[10px] sm:text-xs font-mono text-[color:var(--theme-color)] bg-[color:var(--theme-color-10)] border border-[color:var(--theme-color-20)] px-2 py-1 rounded-lg break-all whitespace-normal w-full xl:w-auto text-left xl:text-right">{{ $viewPath }}</span>
                                @else
                                    <span class="text-[10px] sm:text-xs font-mono text-yellow-500 bg-yellow-500/10 border border-yellow-500/20 px-2 py-1 rounded-lg break-all whitespace-normal w-full xl:w-auto text-left xl:text-right">{{ $viewPath }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

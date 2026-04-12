<div>
    <div class="animate-fade-in-up font-mono antialiased text-emerald-600 pb-12 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
        <div class="mb-12 text-center mt-4 font-mono">
            <h1 class="text-3xl sm:text-4xl font-black text-primary tracking-widest uppercase shadow-primary/20 drop-shadow-md">
                Generative UI
            </h1>
            <p class="text-gray-400 mt-2 text-sm uppercase tracking-widest">
                Intelligentes Livewire Routing für AI-Datenvisualisierung (Headless)
            </p>
        </div>
        <div class="flex justify-end mb-8 relative z-10 font-mono">
            <div class="bg-gray-950 p-2 rounded-xl border border-emerald-900/50 shadow-inner flex items-center gap-3">
                <div class="flex items-center gap-2 px-3 py-1.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-lg text-[10px] font-black uppercase tracking-widest shadow-[0_0_10px_rgba(16,185,129,0.2)]">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_8px_currentColor]"></span>
                    GenUI Aktiv
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="md:col-span-full bg-black/80 backdrop-blur-xl border border-emerald-900/40 rounded-3xl p-6 shadow-[inset_0_0_20px_rgba(16,185,129,0.02)]">
                <p class="text-emerald-500 leading-relaxed mb-4 font-mono text-sm max-w-4xl">
                    Diese Registratur zeigt alle aktuell verfügbaren Kategorien, die Funkira über das abstrakte Werkzeug <code class="bg-gray-950 px-2 py-1 rounded text-emerald-400 border border-emerald-900/50 shadow-inner">visualize_data</code> aufrufen kann.
                    Die Intelligenz darüber, welches Design oder welches Blade-Template verwendet wird, liegt komplett im Backend in <code class="bg-gray-950 px-2 py-1 rounded text-emerald-400 border border-emerald-900/50 shadow-inner">AiDataVisualization</code>.
                </p>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-black bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 uppercase tracking-widest shadow-[0_0_10px_rgba(16,185,129,0.1)]">
                    <x-heroicon-o-shield-check class="w-4 h-4" />
                    Verhindert AI-Halluzinationen im Frontend
                </div>
            </div>

            @foreach($registry as $catKey => $catData)
                <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 rounded-3xl p-6 hover:border-emerald-500/50 text-emerald-500 hover:shadow-[0_0_25px_currentColor] transition-all group relative overflow-hidden font-mono text-sm shadow-[inset_0_0_20px_rgba(0,0,0,0.8)]">
                    
                    <div class="absolute inset-0 bg-current/5 to-transparent pointer-events-none opacity-0 group-hover:opacity-10 transition-opacity"></div>
                    
                    <!-- Status Badge -->
                    <div class="absolute top-6 right-6">
                        @if($catData['status'] === 'active')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[9px] font-black bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 uppercase tracking-widest shadow-[0_0_10px_rgba(16,185,129,0.1)]">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_5px_currentColor]"></span>
                                Aktiv
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[9px] font-black bg-gray-900/80 text-gray-500 border border-gray-700/50 uppercase tracking-widest">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span>
                                Fallback
                            </span>
                        @endif
                    </div>

                    <div class="flex items-start gap-4 mb-4 pr-24">
                        <div class="w-12 h-12 rounded-xl bg-gray-950 border border-gray-800 flex items-center justify-center shadow-inner group-hover:border-current transition-colors shrink-0">
                            <span class="font-mono text-gray-500 group-hover:text-current font-bold text-sm uppercase transition-colors">{{ substr($catKey, 0, 3) }}</span>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-xl font-bold text-gray-200 group-hover:text-current transition-colors truncate">{{ $catData['name'] }}</h3>
                            <p class="text-[10px] font-mono text-emerald-800/80 mt-1 uppercase tracking-widest group-hover:text-emerald-600 transition-colors">Key: <span class="text-gray-500 group-hover:text-current transition-colors">{{ $catKey }}</span></p>
                        </div>
                    </div>
                    
                    <p class="text-sm text-gray-400 group-hover:text-current opacity-80 mb-6 line-clamp-2 h-10 transition-colors">{{ $catData['description'] }}</p>

                    <div class="space-y-3 pt-4 border-t border-gray-800/80 group-hover:border-current/30 transition-colors">
                        <div class="text-[10px] font-black uppercase tracking-widest text-emerald-800/80 group-hover:text-emerald-600 transition-colors pb-1">Registrierte Blade-Views</div>
                        
                        @foreach($catData['views'] as $viewName => $viewPath)
                            <div class="flex justify-between items-center bg-gray-950/50 p-3 rounded-xl border border-gray-800/50 group-hover:border-current/20 shadow-inner transition-colors">
                                <span class="text-xs font-bold text-gray-400">{{ $viewName }}</span>
                                @if(str_contains($viewPath, 'livewire.'))
                                    <span class="text-[9px] font-mono text-emerald-500 bg-emerald-500/10 border border-emerald-500/20 px-2 py-1 rounded-lg shadow-[0_0_10px_rgba(16,185,129,0.1)]">{{ $viewPath }}</span>
                                @else
                                    <span class="text-[9px] font-mono text-yellow-500 bg-yellow-500/10 border border-yellow-500/20 px-2 py-1 rounded-lg">{{ $viewPath }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

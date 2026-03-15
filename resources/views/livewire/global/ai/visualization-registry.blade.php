<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-gray-900 border border-gray-800 overflow-hidden shadow-xl sm:rounded-2xl">
            <div class="p-6 lg:p-8 border-b border-gray-800">
                <div class="flex items-center gap-4 mb-2">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20 flex items-center justify-center ring-1 ring-purple-500/30">
                        <i class="bi bi-cpu-fill text-2xl text-purple-400"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-white tracking-widest uppercase">Funkira <span class="text-purple-400">Generative UI</span></h1>
                        <p class="text-gray-400 font-mono text-sm mt-1">Intelligentes Livewire Routing für AI-Datenvisualisierung (Headless)</p>
                    </div>
                </div>
            </div>

            <div class="bg-gray-950/50 bg-opacity-25 grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8 p-6 lg:p-8">
                <div class="md:col-span-2 bg-gray-900 border border-gray-800 rounded-2xl p-6">
                    <p class="text-gray-300 leading-relaxed mb-4">
                        Diese Registratur zeigt alle aktuell verfügbaren Kategorien, die Funkira über das abstrakte Werkzeug <code class="bg-gray-800 px-2 py-1 rounded text-emerald-400 font-mono text-xs">visualize_data</code> aufrufen kann.
                        Die Intelligenz darüber, welches Design oder welches Blade-Template (Tabelle, Kachel, etc.) verwendet wird, liegt komplett im Backend in der Komponente <code class="bg-gray-800 px-2 py-1 rounded tracking-wide text-indigo-400">AiDataVisualization</code>.
                    </p>
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-black bg-rose-500/10 text-rose-400 border border-rose-500/30 uppercase tracking-widest">
                        <i class="bi bi-shield-lock-fill"></i>
                        Verhindert AI-Halluzinationen im Frontend
                    </div>
                </div>

                @foreach($registry as $catKey => $catData)
                    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 hover:border-purple-500/50 transition-colors group relative overflow-hidden">
                        
                        <!-- Status Badge -->
                        <div class="absolute top-6 right-6">
                            @if($catData['status'] === 'active')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-black bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase tracking-widest">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Aktiv
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-black bg-gray-800 text-gray-500 border border-gray-700 uppercase tracking-widest">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span>
                                    Raw JSON Fallback
                                </span>
                            @endif
                        </div>

                        <div class="flex items-start gap-4 mb-4">
                            <div class="w-10 h-10 rounded-lg bg-gray-950 border border-gray-800 flex items-center justify-center">
                                <span class="font-mono text-gray-500 font-bold text-xs uppercase">{{ substr($catKey, 0, 3) }}</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white group-hover:text-purple-400 transition-colors">{{ $catData['name'] }}</h3>
                                <p class="text-xs font-mono text-gray-500 mt-0.5">Kategorie-Key: <span class="text-purple-300">{{ $catKey }}</span></p>
                            </div>
                        </div>
                        
                        <p class="text-sm text-gray-400 mb-6">{{ $catData['description'] }}</p>

                        <div class="space-y-3">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-600 border-b border-gray-800 pb-2 mb-3">Registrierte Blade-Views</div>
                            
                            @foreach($catData['views'] as $viewName => $viewPath)
                                <div class="flex items-center justify-between bg-gray-950 p-3 rounded-lg border border-gray-800/80">
                                    <span class="text-xs font-bold text-gray-300">{{ $viewName }}</span>
                                    @if(str_contains($viewPath, 'livewire.'))
                                        <span class="text-[10px] font-mono text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded">{{ $viewPath }}</span>
                                    @else
                                        <span class="text-[10px] font-mono text-yellow-500 bg-yellow-500/10 px-2 py-1 rounded">{{ $viewPath }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

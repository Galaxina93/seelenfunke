<div class="space-y-8 w-full">
    {{-- MODEL SELECTOR --}}
    <div class="flex flex-wrap items-center justify-center gap-4 mb-4">
        @foreach($models as $model)
            <button wire:click="selectModel('{{ $model['id'] }}')" 
                    class="px-6 py-2 rounded-full font-bold text-sm transition-all duration-300 {{ $current['id'] === $model['id'] ? 'bg-primary text-black shadow-[0_0_15px_rgba(197,160,89,0.5)] scale-105' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white' }}">
                {{ $model['name'] }}
            </button>
        @endforeach
    </div>

    {{-- HEADER KACHEL (Mega Modell Vorstellung) --}}
    <div class="relative w-full rounded-[2.5rem] p-1 bg-gradient-to-r from-primary/20 via-primary/50 to-primary/20 shadow-[0_0_40px_rgba(197,160,89,0.2)] overflow-hidden group">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-3xl z-0"></div>
        <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/60 to-transparent z-0"></div>
        
        {{-- Animated Glow --}}
        <div class="absolute -top-[50%] -left-[10%] w-[120%] h-[200%] bg-gradient-to-tr from-white/0 via-white/10 to-white/0 transform rotate-12 -translate-x-[100%] group-hover:translate-x-[100%] transition-transform duration-[2s] ease-in-out z-10 pointer-events-none"></div>

        <div class="relative z-20 flex flex-col items-center justify-center py-16 px-6 text-center">
            <h3 class="text-xs uppercase tracking-[0.3em] text-primary mb-2 font-bold">{{ $current['badge'] }}</h3>
            <div class="flex items-center gap-4 mb-4">
                <x-heroicon-o-cpu-chip class="w-12 h-12 text-white/90 drop-shadow-[0_0_15px_rgba(255,255,255,0.5)]" />
                <h1 class="text-5xl sm:text-7xl font-black text-transparent bg-clip-text bg-gradient-to-r from-white via-gray-200 to-gray-500 tracking-tight drop-shadow-2xl">
                    {{ $current['name'] }}
                </h1>
            </div>
            <p class="text-gray-400 text-lg max-w-2xl font-light">
                {{ $current['description'] }}
            </p>
        </div>
    </div>

    {{-- VERGLEICH UND VOR-/NACHTEILE (Grid) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Vorteile / Nachteile Kachel --}}
        <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 rounded-3xl p-8 shadow-2xl">
            <h2 class="text-xl font-bold text-white mb-6 border-b border-gray-800 pb-4">Stärken & Schwächen ({{ $current['name'] }})</h2>
            
            <div class="space-y-6">
                {{-- Vorteile --}}
                <div class="space-y-3">
                    <h3 class="text-sm uppercase tracking-wider text-emerald-500 font-bold mb-4">Vorteile</h3>
                    @foreach($current['pros'] as $pro)
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-full bg-emerald-500/20 p-1"><x-heroicon-o-check class="w-4 h-4 text-emerald-400" /></div>
                        <div>
                            <p class="text-white font-medium">{{ $pro['title'] }}</p>
                            <p class="text-xs text-gray-500">{{ $pro['desc'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Nachteile --}}
                <div class="space-y-3 pt-4 border-t border-gray-800">
                    <h3 class="text-sm uppercase tracking-wider text-red-500 font-bold mb-4">Nachteile / Einschränkungen</h3>
                    @foreach($current['cons'] as $con)
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-full bg-red-500/20 p-1"><x-heroicon-o-x-mark class="w-4 h-4 text-red-400" /></div>
                        <div>
                            <p class="text-white font-medium">{{ $con['title'] }}</p>
                            <p class="text-xs text-gray-500">{{ $con['desc'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Benchmark Kachel --}}
        <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 rounded-3xl p-8 shadow-2xl flex flex-col">
            <h2 class="text-xl font-bold text-white mb-6 border-b border-gray-800 pb-4">Leistungs-Metriken (Provider: {{ $current['provider'] }})</h2>
            
            <div class="space-y-8 flex-1 flex flex-col justify-center">
                @foreach(['speed', 'logic', 'tools'] as $key)
                @php
                    $metric = $current['metrics'][$key];
                    $percentage = min(100, ($metric['value'] / $metric['max']) * 100);
                    $colors = [
                        'speed' => 'from-emerald-600 to-emerald-400',
                        'logic' => 'from-blue-600 to-blue-400',
                        'tools' => 'from-primary-dark to-primary'
                    ];
                @endphp
                <div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-400 font-medium">{{ $metric['label'] }}</span>
                    </div>
                    <div class="relative h-6 bg-gray-800 rounded-full overflow-hidden flex">
                        <div class="absolute inset-y-0 left-0 bg-gradient-to-r {{ $colors[$key] }} w-[{{ $percentage }}%] transition-all duration-1000"></div>
                        <div class="absolute inset-y-0 left-0 bg-gray-600/50 w-[{{ 100 - $percentage }}%] border-l-2 border-black z-10 transition-all duration-1000" style="left: {{ $percentage }}%"></div>
                    </div>
                    <div class="flex justify-between text-xs mt-1 text-gray-500">
                        <span>{{ $metric['text'] }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>


    {{-- DATEI ABLAUFPLAN / STRUKTUR BAUM --}}
    <div class="w-full mt-8">
        <h2 class="text-2xl font-black text-white mb-2 pl-2">Systemarchitektur & Datei-Flow</h2>
        <p class="text-gray-400 text-sm mb-8 pl-2">Diese Dateien formen das "Gehirn", das "Nervensystem" und die "Augen" von Funkira. Daten fließen von links (Benutzer/View) nach rechts (Services/Backend).</p>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">

            {{-- Frontend / View Layer --}}
            <div class="flex flex-col gap-4">
                <div class="text-xs uppercase tracking-widest text-gray-500 font-black mb-2 border-b border-gray-800 pb-2">1. Frontend (Augen & Mund)</div>
                
                <div class="bg-gray-800/40 border border-gray-700 rounded-2xl p-5 hover:bg-gray-800/60 transition-colors">
                    <div class="flex items-center gap-3 mb-3">
                        <x-heroicon-o-eye class="w-5 h-5 text-blue-400" />
                        <h4 class="text-white font-bold font-mono text-sm">FunkiraChat.php</h4>
                    </div>
                    <p class="text-xs text-gray-400 leading-relaxed">
                        (Livewire Komponente) Kontrolliert das 3D UI, empfängt Audio-Text Eingaben des Nutzers (Alpine.js) und kümmert sich um die Sprachausgabe (TTS). Pusht Eingaben in den System-Bus.
                    </p>
                </div>

                <div class="bg-gray-800/40 border border-gray-700 rounded-2xl p-5 hover:bg-gray-800/60 transition-colors">
                    <div class="flex items-center gap-3 mb-3">
                        <x-heroicon-o-presentation-chart-line class="w-5 h-5 text-blue-400" />
                        <h4 class="text-white font-bold font-mono text-sm">CompanyMap.php</h4>
                    </div>
                    <p class="text-xs text-gray-400 leading-relaxed">
                        Das Architektur-Canvas. Visualisiert die Backend-Logik für den User prozedural als Node-Map. Zeigt auch den Echtzeit-Status der API Requests der KI (Pulsende Linien).
                    </p>
                </div>
            </div>

            {{-- Control Layer --}}
            <div class="flex flex-col gap-4">
                <div class="text-xs uppercase tracking-widest text-primary/70 font-black mb-2 border-b border-gray-800 pb-2">2. Processing (Verstand)</div>
                
                <div class="bg-primary/10 border border-primary/30 rounded-2xl p-5 shadow-[0_0_15px_rgba(197,160,89,0.05)] relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-2 opacity-10"><x-heroicon-s-sparkles class="w-16 h-16"/></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <x-heroicon-o-sparkles class="w-5 h-5 text-primary" />
                            <h4 class="text-white font-bold font-mono text-sm">MittwaldAgent.php</h4>
                        </div>
                        <p class="text-xs text-gray-300 leading-relaxed">
                            Das exakte Herz von Funkira. Setzt den System Prompt (Die Persona) zusammen. Sammelt Chatverläufe. Führt die Curls API Requests an die Groq-API (LLM) durch. 
                        </p>
                        <div class="mt-3 text-[10px] bg-black/40 text-primary-light px-2 py-1 rounded w-fit font-mono border border-primary/20">Der "API Proxy"</div>
                    </div>
                </div>

                <div class="bg-gray-800/40 border border-gray-700 rounded-2xl p-5 hover:bg-gray-800/60 transition-colors">
                    <div class="flex items-center gap-3 mb-3">
                        <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-indigo-400" />
                        <h4 class="text-white font-bold font-mono text-sm">FunkiLog.php</h4>
                    </div>
                    <p class="text-xs text-gray-400 leading-relaxed">
                        (Model) Das interne Protokollierungszentrum. Jeder Thought, jeder Tool Call und alle System-Healing-Eingriffe von der KI werden hier audit-sicher weggeschrieben.
                    </p>
                </div>
            </div>

            {{-- Registry Layer --}}
            <div class="flex flex-col gap-4">
                <div class="text-xs uppercase tracking-widest text-purple-500/70 font-black mb-2 border-b border-gray-800 pb-2">3. Werkzeuge (Hände)</div>
                
                <div class="bg-purple-900/10 border border-purple-500/30 rounded-2xl p-5 shadow-[0_0_15px_rgba(168,85,247,0.05)]">
                    <div class="flex items-center gap-3 mb-3">
                        <x-heroicon-o-wrench-screwdriver class="w-5 h-5 text-purple-400" />
                        <h4 class="text-white font-bold font-mono text-sm">AIFunctionsRegistry.php</h4>
                    </div>
                    <p class="text-xs text-gray-300 leading-relaxed">
                        Dies ist das Register aller Fähigkeiten. Wenn die KI (MittwaldAgent) entscheidet eine Datenbankänderung vorzunehmen, checkt sie in dieser Datei, ob es eine berechtigte Funktion dafür gibt und ruft diese auf.
                    </p>
                </div>
            </div>

            {{-- Execution Traits --}}
            <div class="flex flex-col gap-4">
                <div class="text-xs uppercase tracking-widest text-emerald-500/70 font-black mb-2 border-b border-gray-800 pb-2">4. Physische Endpunkte (Muskeln)</div>
                
                <div class="bg-emerald-900/10 border border-emerald-500/30 rounded-2xl p-5 shadow-[0_0_15px_rgba(16,185,129,0.05)]">
                    <div class="flex items-center gap-3 mb-3">
                        <x-heroicon-o-code-bracket class="w-5 h-5 text-emerald-400" />
                        <h4 class="text-white font-bold font-mono text-sm">/Functions/*.php</h4>
                    </div>
                    <p class="text-xs text-gray-300 leading-relaxed mb-3">
                        Dies sind PHP Traits. Jede Datei enthält die harte Logik (Eloquent ORM), um tatsächliche Datenbankveränderungen im ERP zu verrichten.
                    </p>
                    <ul class="text-[10px] font-mono text-emerald-500/70 space-y-1 ml-2">
                        <li>- DashboardFunctions.php</li>
                        <li>- ShopFunctions.php</li>
                        <li>- OrderFunctions.php</li>
                        <li>- AccountingFunctions.php</li>
                        <li>- SettingsFunctions.php</li>
                        <li>- CoreFunctions.php</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>

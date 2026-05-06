@php
    $p = is_array($persona) ? $persona : (array)$persona;
    $name = $p['name'] ?? 'Unbekannt';
    $aliases = $p['aliases'] ?? null;
    $status = $p['status'] ?? 'Unknown';
    $origin = $p['origin'] ?? 'Classified';
    $birthDate = $p['birth_date'] ?? 'REDACTED';
    $imageUrl = $p['image_url'] ?? null;
    $summary = $p['summary'] ?? 'Keine Geheimdienst-Informationen verfügbar.';
    $careerTimeline = $p['career_timeline'] ?? [];
    $associates = $p['known_associates'] ?? [];
@endphp
<div>
    <div class="bg-gray-950/90 backdrop-blur-3xl border border-red-900/50 rounded-3xl p-6 shadow-[0_0_40px_rgba(220,38,38,0.15)] relative overflow-hidden group">
        <!-- Background Radar/Grid effect -->
        <div class="absolute inset-0 bg-[linear-gradient(rgba(220,38,38,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(220,38,38,0.05)_1px,transparent_1px)] bg-[size:20px_20px] opacity-20 pointer-events-none"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 border border-red-500/10 rounded-full animate-[spin_10s_linear_infinite] pointer-events-none"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[32rem] h-[32rem] border border-red-500/5 rounded-full animate-[spin_15s_linear_infinite_reverse] pointer-events-none"></div>
        
        <div class="relative z-10 flex flex-col gap-6">
            
            <!-- Top Secret Header -->
            <div class="flex items-center justify-between border-b border-red-900/50 pb-4">
                <div class="flex items-center gap-3">
                    <i class="bi bi-shield-lock-fill text-red-500 text-2xl"></i>
                    <div>
                        <div class="text-red-500 text-[10px] uppercase tracking-[0.3em] font-bold font-mono">Top Secret // Eyes Only</div>
                        <div class="text-gray-400 text-xs font-mono">Profil-Akte: {{ substr(md5($name), 0, 8) }}-{{ date('Y') }}</div>
                    </div>
                </div>
                <div class="px-3 py-1 bg-red-950/50 border border-red-800 text-red-400 text-[10px] font-mono tracking-widest uppercase rounded">
                    Classified
                </div>
            </div>

            <!-- Profile Overview -->
            <div class="flex flex-col md:flex-row gap-6">
                <!-- Image / Photo -->
                <div class="w-full md:w-1/3 shrink-0 relative">
                    <div class="aspect-[3/4] w-full rounded-xl bg-gray-900 border-2 border-gray-800 relative overflow-hidden flex items-center justify-center">
                        @if($imageUrl)
                            <img src="{{ $imageUrl }}" alt="{{ $name }}" class="w-full h-full object-cover filter contrast-125 saturate-50 sepia-[.2]">
                            <!-- Glitch overlay -->
                            <div class="absolute inset-0 bg-red-500 mix-blend-overlay opacity-20 pointer-events-none"></div>
                            <div class="absolute inset-0 bg-[repeating-linear-gradient(0deg,transparent,transparent_2px,rgba(0,0,0,0.3)_2px,rgba(0,0,0,0.3)_4px)] pointer-events-none"></div>
                        @else
                            <i class="bi bi-person-bounding-box text-6xl text-gray-700"></i>
                        @endif
                        <div class="absolute top-2 right-2 flex gap-1">
                            <div class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></div>
                            <div class="text-[8px] font-mono text-red-500 tracking-widest">LIVE</div>
                        </div>
                    </div>
                    <!-- Status Bar under image -->
                    <div class="mt-3 bg-gray-900 p-2 rounded border border-gray-800 flex items-center justify-between font-mono text-[10px]">
                        <span class="text-gray-500">STATUS:</span>
                        <span class="{{ strtolower($status) == 'verstorben' ? 'text-gray-400' : 'text-emerald-400' }} font-bold uppercase tracking-wider">{{ $status }}</span>
                    </div>
                </div>

                <!-- Bio Data -->
                <div class="w-full md:w-2/3 flex flex-col gap-4">
                    <div>
                        <h2 class="text-3xl font-bold text-white tracking-tight">{{ strtoupper($name) }}</h2>
                        @if($aliases)
                            <div class="text-red-400 font-mono text-xs tracking-widest uppercase mt-1">AKA: {{ $aliases }}</div>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-3 mt-2">
                        <div class="bg-gray-900/50 p-3 rounded-lg border border-gray-800/50">
                            <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500 font-mono">Herkunft</div>
                            <div class="text-sm text-gray-300 font-medium">{{ $origin }}</div>
                        </div>
                        <div class="bg-gray-900/50 p-3 rounded-lg border border-gray-800/50">
                            <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500 font-mono">Geburtsdatum</div>
                            <div class="text-sm text-gray-300 font-medium">{{ $birthDate }}</div>
                        </div>
                    </div>

                    <div class="bg-black/40 p-4 rounded-xl border border-gray-800 mt-2 relative">
                        <div class="absolute -top-2 left-4 px-2 bg-gray-950 text-[9px] uppercase tracking-widest font-bold text-red-500 font-mono">Zusammenfassung</div>
                        <p class="text-sm text-gray-300 leading-relaxed font-sans">
                            {{ $summary }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Bottom Section: Career & Associates -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4 border-t border-gray-800/50 pt-6">
                <!-- Timeline -->
                <div>
                    <div class="text-[10px] uppercase tracking-widest font-bold text-gray-500 font-mono mb-4 flex items-center gap-2">
                        <i class="bi bi-clock-history"></i> Career Timeline
                    </div>
                    @if(count($careerTimeline) > 0)
                        <div class="relative border-l border-gray-800 ml-2 space-y-4">
                            @foreach($careerTimeline as $ct)
                                <div class="relative pl-4">
                                    <div class="absolute -left-[5px] top-1.5 w-2 h-2 rounded-full bg-red-900 border border-red-500 shadow-[0_0_8px_rgba(220,38,38,0.8)]"></div>
                                    <div class="text-[10px] font-mono text-red-400">{{ $ct['year'] ?? '' }}</div>
                                    <div class="text-xs text-gray-300">{{ $ct['event'] ?? '' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-xs text-gray-600 italic font-mono">No timeline data available.</div>
                    @endif
                </div>

                <!-- Associates -->
                <div>
                    <div class="text-[10px] uppercase tracking-widest font-bold text-gray-500 font-mono mb-4 flex items-center gap-2">
                        <i class="bi bi-diagram-3"></i> Known Associates
                    </div>
                    @if(count($associates) > 0)
                        <div class="flex flex-wrap gap-2">
                            @foreach($associates as $assoc)
                                <div class="px-3 py-1.5 bg-gray-900 border border-gray-700 rounded-lg text-xs text-gray-300 font-medium flex items-center gap-2 hover:border-red-500 transition-colors cursor-default">
                                    <i class="bi bi-person text-gray-500"></i> {{ $assoc }}
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-xs text-gray-600 italic font-mono">No known associates logged.</div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>

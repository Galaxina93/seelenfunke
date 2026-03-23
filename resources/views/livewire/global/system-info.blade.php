<div>
    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-black/90 backdrop-blur-md p-4 sm:p-6 rounded-2xl shadow-[0_0_30px_rgba(16,185,129,0.05)] border border-emerald-900/40 relative overflow-hidden my-4">
            <div class="absolute top-0 right-0 p-4 opacity-10 blur-sm pointer-events-none">
                <x-heroicon-o-server class="w-24 h-24 text-emerald-500 drop-shadow-[0_0_20px_rgba(16,185,129,1)]" />
            </div>
            <div class="relative z-10">
                <h1 class="text-2xl sm:text-3xl font-black text-emerald-500 tracking-widest uppercase shadow-emerald-500/20 drop-shadow-md font-mono">System-Info & Hosting</h1>
                <p class="text-emerald-700 mt-1 text-xs font-bold uppercase tracking-widest font-mono">Server-Metriken, Systemumgebung und KI-Hosting-Tarife.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-4 font-mono relative overflow-hidden group flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Laravel Core</div>
                    <div class="text-xl font-black text-gray-200 group-hover:text-emerald-400 transition-colors">v{{ $laravelVersion }}</div>
                </div>
                <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                    <x-heroicon-o-code-bracket class="w-8 h-8 text-emerald-500" />
                </div>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-4 font-mono relative overflow-hidden group flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">PHP Engine</div>
                    <div class="text-xl font-black text-gray-200 group-hover:text-indigo-400 transition-colors">v{{ $phpVersion }}</div>
                </div>
                <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                    <x-heroicon-o-command-line class="w-8 h-8 text-indigo-500" />
                </div>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-4 font-mono relative overflow-hidden group flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Datenbank</div>
                    <div class="text-xl font-black text-gray-200 group-hover:text-pink-400 transition-colors">SQLite (In-Memory)</div>
                </div>
                <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                    <x-heroicon-o-circle-stack class="w-8 h-8 text-pink-500" />
                </div>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-emerald-900/50 shadow-[inset_0_0_20px_rgba(16,185,129,0.1)] rounded-2xl p-4 font-mono relative overflow-hidden group hover:border-emerald-500/80 transition-all cursor-pointer flex justify-between items-center">
                <div>
                    <div class="text-emerald-700 text-[10px] font-black uppercase tracking-widest mb-1">KI Hosting Partner</div>
                    <div class="text-xl font-black text-emerald-400 drop-shadow-[0_0_5px_rgba(16,185,129,0.4)]">Mittwald</div>
                </div>
                <div class="opacity-40 transition-opacity group-hover:opacity-80 group-hover:animate-pulse">
                    <x-heroicon-o-shield-check class="w-8 h-8 text-emerald-500" />
                </div>
            </div>
        </div>

        <div class="text-center mb-4">
            <h2 class="text-xl sm:text-2xl font-black text-gray-200 tracking-widest uppercase font-mono mb-1">KI-Hosting Tarife</h2>
            <p class="text-gray-500 font-mono text-[10px] uppercase tracking-widest">DSGVO-konformes Hosting in Deutschland (Mittwald API)</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-stretch">

            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-5 font-mono flex flex-col h-full transition-all hover:border-gray-500 relative">
                <h3 class="text-lg font-black text-gray-300 uppercase tracking-widest mb-1">Starter</h3>
                <div class="flex items-baseline gap-2 mb-1">
                    <span class="text-2xl font-black text-white">9 €</span>
                </div>
                <p class="text-[9px] text-gray-500 uppercase tracking-widest mb-2">pro Monat zzgl. USt.*</p>
                <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-3 border-b border-gray-800 pb-2">Perfekt zum Testen und Experimentieren</p>

                <ul class="space-y-2 mb-2 flex-1 text-[11px] text-gray-400 tracking-wider">
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> 5 Mio. Tokens/Monat</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> 30 Requests/Minute</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> 5 parallele Requests</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> Alle Modelle verfügbar</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> Unbegrenzte API-Keys</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> OpenAI-kompatible API</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> DSGVO-konformes Hosting</li>
                </ul>
            </div>

            <div class="bg-gray-950 backdrop-blur-xl border-2 border-[peru] shadow-[0_0_30px_rgba(205,133,63,0.15),inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-5 font-mono flex flex-col h-full relative transform xl:scale-105 z-10 w-full">
                <h3 class="text-lg font-black text-[peru] uppercase tracking-widest mb-1 drop-shadow-[0_0_5px_rgba(205,133,63,0.5)]">Pro</h3>
                <div class="flex items-baseline gap-2 mb-1">
                    <span class="text-3xl font-black text-white">39 €</span>
                </div>
                <p class="text-[9px] text-gray-500 uppercase tracking-widest mb-2">pro Monat zzgl. USt.*</p>
                <p class="text-[9px] text-[peru] font-bold uppercase tracking-widest mb-3 border-b border-gray-800 pb-2">Für Agenturen und Produktiveinsatz</p>

                <ul class="space-y-2 mb-2 flex-1 text-[11px] text-gray-300 tracking-wider">
                    <li class="flex items-center gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0" /> <span class="font-bold text-white drop-shadow-[0_0_3px_rgba(255,255,255,0.4)]">75 Mio. Tokens/Monat</span></li>
                    <li class="flex items-start gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0 mt-0.5" /> 60 Requests/Minute</li>
                    <li class="flex items-start gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0 mt-0.5" /> 10 parallele Requests</li>
                    <li class="flex items-start gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0 mt-0.5" /> Alle Modelle verfügbar</li>
                    <li class="flex items-start gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0 mt-0.5" /> Unbegrenzte API-Keys</li>
                    <li class="flex items-start gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0 mt-0.5" /> OpenAI-kompatible API</li>
                    <li class="flex items-start gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0 mt-0.5" /> DSGVO-konformes Hosting</li>
                    <li class="flex items-center gap-2 font-bold text-[peru] mt-3 pt-3 border-t border-[peru]/20"><x-heroicon-s-star class="w-3.5 h-3.5 text-[peru] shrink-0" /> Perfekt für Production</li>
                </ul>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-5 font-mono flex flex-col h-full transition-all hover:border-gray-500 relative">
                <h3 class="text-lg font-black text-gray-300 uppercase tracking-widest mb-1">Business</h3>
                <div class="flex items-baseline gap-2 mb-1">
                    <span class="text-2xl font-black text-white">149 €</span>
                </div>
                <p class="text-[9px] text-gray-500 uppercase tracking-widest mb-2">pro Monat zzgl. USt.*</p>
                <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-3 border-b border-gray-800 pb-2">Für größere Teams und Projekte</p>

                <ul class="space-y-2 mb-2 flex-1 text-[11px] text-gray-400 tracking-wider">
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> 300 Mio. Tokens/Monat</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> 150 Requests/Minute</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> 20 parallele Requests</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> Alle Modelle verfügbar</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> Unbegrenzte API-Keys</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> OpenAI-kompatible API</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> DSGVO-konformes Hosting</li>
                </ul>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-5 font-mono flex flex-col h-full transition-all hover:border-purple-500/50 relative overflow-hidden group">
                <div class="absolute top-0 right-0 bg-gray-900/80 backdrop-blur-sm text-gray-500 group-hover:text-purple-400 transition-colors text-[7px] font-black uppercase tracking-widest px-2 py-1 rounded-bl-xl border-b border-l border-gray-800/60 z-10">ENTERPRISE</div>
                <h3 class="text-lg font-black text-gray-300 group-hover:text-purple-400 transition-colors uppercase tracking-widest mb-1 mt-2 relative z-10">Dedicated</h3>
                <div class="flex items-baseline gap-2 mb-1 relative z-10">
                    <span class="text-2xl font-black text-white">999 €</span>
                </div>
                <p class="text-[9px] text-gray-500 uppercase tracking-widest mb-2 relative z-10">pro Monat zzgl. USt.*</p>
                <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-3 border-b border-gray-800 pb-2 relative z-10">Eigene GPU-Ressourcen</p>

                <ul class="space-y-2 mb-2 flex-1 text-[11px] text-gray-400 tracking-wider relative z-10">
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-purple-500 shrink-0 mt-0.5" /> Milliarden Tokens/Monat</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-purple-500 shrink-0 mt-0.5" /> Eigene RTX PRO 6000</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-purple-500 shrink-0 mt-0.5" /> Custom-Deployments</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-purple-500 shrink-0 mt-0.5" /> Technischer Ansprechpartner</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> OpenAI-kompatible API</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> DSGVO-konformes Hosting</li>
                </ul>
            </div>

        </div>
        <div class="mt-12 bg-black/90 backdrop-blur-md p-6 sm:p-8 rounded-2xl shadow-[0_0_30px_rgba(234,88,12,0.05)] border border-orange-900/40 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10 blur-sm pointer-events-none">
                <x-heroicon-o-document-duplicate class="w-32 h-32 text-orange-500 drop-shadow-[0_0_20px_rgba(234,88,12,1)]" />
            </div>

            <h2 class="text-xl sm:text-2xl font-black text-orange-500 tracking-widest uppercase font-mono mb-2 drop-shadow-md">Laravel Architektur & Update Protokolle</h2>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 relative z-10 mt-6">
                {{-- INFO SIDE --}}
                <div class="space-y-6">
                    <div class="bg-black/60 border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-xl p-5 font-mono">
                        <h3 class="text-orange-400 font-bold uppercase tracking-widest text-sm mb-3">Architektur-Hinweis (Laravel 11+)</h3>
                        <p class="text-gray-400 text-xs leading-relaxed mb-3">
                            Mit Laravel 11.x wurde die Ordnerstruktur massiv entschlackt (<strong>Slim Skeleton</strong>). Die traditionelle Datei <code class="text-pink-400 bg-pink-500/10 px-1 rounded">app/Console/Kernel.php</code> existiert in neuen Installationen nicht mehr.
                        </p>
                        <p class="text-gray-400 text-xs leading-relaxed">
                            <strong>Schedules & Cronjobs</strong> werden ab sofort gebündelt und elegant direkt in der <code class="text-emerald-400 bg-emerald-500/10 px-1 rounded">routes/console.php</code> registriert (über die <code>Schedule::</code> Fassade). Globale Middleware und das Routing-Setup befinden sich nun zentralisiert in der <code class="text-blue-400 bg-blue-500/10 px-1 rounded">bootstrap/app.php</code>.
                        </p>
                    </div>

                    <div class="bg-black/60 border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-xl p-5 font-mono">
                        <h3 class="text-orange-400 font-bold uppercase tracking-widest text-sm mb-3">Framework Upgrade Ablauf</h3>
                        <ol class="list-decimal list-inside text-gray-400 text-xs space-y-2 leading-relaxed">
                            <li>Studieren der offiziellen Release-Notes der neuen Version.</li>
                            <li>Anpassen der <code class="text-gray-300">composer.json</code> auf den neuen Major-Release für <code>laravel/framework</code>, <code>phpunit/phpunit</code> und First-Party-Pakete (z.B. Livewire, Sanctum).</li>
                            <li>Kompilieren des gesamten Vendor-Trees via <code class="text-indigo-400 bg-indigo-500/10 px-1 rounded">composer update -W</code>.</li>
                            <li>Ausführen von <code class="text-emerald-400 bg-emerald-500/10 px-1 rounded">php artisan optimize:clear</code>.</li>
                            <li>Upload des resultierenden Arbeitsberichts hier im Datei-Vault.</li>
                        </ol>
                    </div>
                </div>

                {{-- UPLOAD & LIST SIDE --}}
                <div class="flex flex-col gap-4">
                    {{-- Uploader --}}
                    <div class="bg-gray-900/50 border-2 border-dashed border-gray-700 hover:border-orange-500/50 transition-colors rounded-xl p-6 text-center relative group min-h-[140px] flex items-center justify-center">
                        <input type="file" wire:model.live="uploads" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                        <div class="absolute inset-0 flex items-center justify-center w-full h-full" wire:loading wire:target="uploads">
                            <span class="text-orange-400 font-bold text-sm tracking-widest animate-pulse">Lade hoch...</span>
                        </div>
                        <div class="pointer-events-none relative z-10 flex flex-col items-center" wire:loading.remove wire:target="uploads">
                            <x-heroicon-o-cloud-arrow-up class="w-10 h-10 text-gray-500 group-hover:text-orange-400 transition-colors mb-2" />
                            <p class="text-gray-300 font-bold text-sm">Neue Arbeitsberichte hochladen</p>
                            <p class="text-gray-500 text-[10px] mt-1 uppercase tracking-widest">Drag & Drop oder Klicken (Multi-Auswahl möglich)</p>
                        </div>
                    </div>
                    
                    @if(count($uploads) > 0)
                        <div class="bg-orange-500/10 border border-orange-500/20 rounded-xl p-4 flex justify-between items-center">
                            <span class="text-orange-400 text-xs font-mono">{{ count($uploads) }} Datei(en) bereit zum Speichern</span>
                            <button wire:click="saveUploads" class="bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-lg transition-colors">Jetzt Speichern</button>
                        </div>
                    @endif

                    @if(count($reportFiles) > 0)
                        <div class="bg-black/60 border border-gray-800/60 rounded-xl overflow-hidden flex flex-col flex-1 min-h-[250px]">
                            <div class="bg-gray-900 border-b border-gray-800 px-4 py-3 flex justify-between items-center">
                                <h4 class="text-gray-300 text-xs font-bold uppercase tracking-widest">Gespeicherte Updates</h4>
                                <span class="bg-gray-800 text-gray-400 text-[10px] px-2 py-0.5 rounded">{{ count($reportFiles) }} Dateien</span>
                            </div>
                            <div class="p-2 space-y-1 overflow-y-auto custom-scrollbar flex-1 max-h-[300px]">
                                @foreach($reportFiles as $file)
                                    @php $filename = basename($file); @endphp
                                    <div class="group flex items-center justify-between p-2 rounded-lg hover:bg-gray-800/50 transition-colors border border-transparent hover:border-gray-700/50 {{ in_array($file, $selectedReports) ? 'bg-gray-800/80 border-gray-700/50' : '' }}">
                                        <div class="flex items-center gap-3 cursor-pointer flex-1" wire:click="toggleReport('{{ $file }}')">
                                            <div class="{{ in_array($file, $selectedReports) ? 'text-orange-500' : 'text-gray-500' }} transition-colors">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            </div>
                                            <span class="text-sm font-mono {{ in_array($file, $selectedReports) ? 'text-white font-bold' : 'text-gray-400' }} truncate" title="{{ $filename }}">{{ $filename }}</span>
                                        </div>
                                        <button wire:click="deleteReport('{{ $file }}')" wire:confirm="Bericht wirklich endgültig löschen?" class="opacity-0 group-hover:opacity-100 p-1.5 text-red-500 hover:bg-red-500/20 rounded-md transition-all">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="bg-black/60 border border-gray-800/60 rounded-xl p-8 flex flex-col items-center justify-center h-full text-gray-500 border-dashed">
                            <x-heroicon-o-folder-open class="w-12 h-12 mb-3 opacity-20" />
                            <p class="text-xs uppercase tracking-widest font-mono">Keine Berichte vorhanden</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- MULTI-SELECT VIEWER SEKTION --}}
            @if(count($selectedReports) > 0)
                <div class="mt-8 space-y-4 relative z-10">
                    <h3 class="text-orange-400 font-bold uppercase tracking-widest text-sm mb-4 border-b border-orange-900/30 pb-2">Geöffnete Berichte ({{ count($selectedReports) }})</h3>
                    @foreach($selectedReports as $reportPath)
                        <div class="bg-gray-950 border border-gray-700/50 shadow-2xl rounded-xl overflow-hidden animate-fade-in-up">
                            <div class="bg-gray-900 border-b border-gray-800 px-4 py-2 flex justify-between items-center">
                                <span class="text-orange-300 text-xs font-mono font-bold">{{ basename($reportPath) }}</span>
                                <button wire:click="toggleReport('{{ $reportPath }}')" class="text-gray-400 hover:text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                            <div class="p-6 text-gray-300 font-mono text-xs leading-relaxed overflow-x-auto whitespace-pre-wrap">
                                {!! nl2br(e($this->getReportContent($reportPath))) !!}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

    </div>
</div>

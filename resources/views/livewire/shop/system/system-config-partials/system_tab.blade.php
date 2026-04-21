<div x-show="activeTab === 'system'" class="animate-fade-in space-y-8" style="display: none;">

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-4 font-sans relative overflow-hidden group flex justify-between items-center">
            <div>
                <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Laravel Core</div>
                <div class="text-xl font-black text-gray-200 group-hover:text-[var(--theme-color)] transition-colors">v{{ $laravelVersion }}</div>
            </div>
            <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                <x-heroicon-o-code-bracket class="w-8 h-8 text-[var(--theme-color)]" />
            </div>
        </div>

        <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-4 font-sans relative overflow-hidden group flex justify-between items-center">
            <div>
                <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">PHP Engine</div>
                <div class="text-xl font-black text-gray-200 group-hover:text-indigo-400 transition-colors">v{{ $phpVersion }}</div>
            </div>
            <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                <x-heroicon-o-command-line class="w-8 h-8 text-indigo-500" />
            </div>
        </div>

        <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-4 font-sans relative overflow-hidden group flex justify-between items-center">
            <div>
                <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Datenbank</div>
                <div class="text-xl font-black text-gray-200 group-hover:text-pink-400 transition-colors">SQLite (In-Memory)</div>
            </div>
            <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                <x-heroicon-o-circle-stack class="w-8 h-8 text-pink-500" />
            </div>
        </div>
    </div>

    @if(session()->has('message'))
        <div class="mb-4 bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] p-3 rounded-lg text-xs font-sans text-center">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-2xl shadow-xl shadow-[var(--theme-color-10)] border border-gray-800 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4 opacity-10 blur-sm pointer-events-none">
            <x-heroicon-o-document-duplicate class="w-32 h-32 text-[var(--theme-color)] drop-shadow-xl shadow-[var(--theme-color-10)]" />
        </div>

        <h2 class="text-xl sm:text-2xl font-black text-[var(--theme-color)] font-sans mb-2 drop-shadow-sm">Laravel Architektur & Update Protokolle</h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 relative z-10 mt-6">
            {{-- INFO SIDE --}}
            <div class="space-y-6">
                <div class="bg-gray-900/60 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-xl p-5 font-sans">
                    <h3 class="text-[var(--theme-color)] font-bold uppercase tracking-widest text-sm mb-3">Architektur-Hinweis (Laravel 11+)</h3>
                    <p class="text-gray-400 text-xs leading-relaxed mb-3">
                        Mit Laravel 11.x wurde die Ordnerstruktur massiv entschlackt (<strong>Slim Skeleton</strong>). Die traditionelle Datei <code class="text-pink-400 bg-pink-500/10 px-1 rounded">app/Console/Kernel.php</code> existiert in neuen Installationen nicht mehr.
                    </p>
                    <p class="text-gray-400 text-xs leading-relaxed">
                        <strong>Schedules & Cronjobs</strong> werden ab sofort gebündelt und elegant direkt in der <code class="text-[var(--theme-color)] bg-[var(--theme-color-10)] px-1 rounded">routes/console.php</code> registriert (über die <code>Schedule::</code> Fassade). Globale Middleware und das Routing-Setup befinden sich nun zentralisiert in der <code class="text-blue-400 bg-blue-500/10 px-1 rounded">bootstrap/app.php</code>.
                    </p>
                </div>

                <div class="bg-gray-900/60 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-xl p-5 font-sans">
                    <h3 class="text-[var(--theme-color)] font-bold uppercase tracking-widest text-sm mb-3">Framework Upgrade Ablauf</h3>
                    <ol class="list-decimal list-inside text-gray-400 text-xs space-y-2 leading-relaxed">
                        <li>Studieren der offiziellen Release-Notes der neuen Version.</li>
                        <li>Anpassen der <code class="text-gray-300">composer.json</code> auf den neuen Major-Release für <code>laravel/framework</code>, <code>phpunit/phpunit</code> und First-Party-Pakete (z.B. Livewire, Sanctum).</li>
                        <li>Kompilieren des gesamten Vendor-Trees via <code class="text-indigo-400 bg-indigo-500/10 px-1 rounded">composer update -W</code>.</li>
                        <li>Ausführen von <code class="text-[var(--theme-color)] bg-[var(--theme-color-10)] px-1 rounded">php artisan optimize:clear</code>.</li>
                        <li>Upload des resultierenden Arbeitsberichts hier im Datei-Vault.</li>
                    </ol>
                </div>
            </div>

            {{-- UPLOAD & LIST SIDE --}}
            <div class="flex flex-col gap-4">
                {{-- Uploader --}}
                <div class="bg-gray-900/50 border-2 border-dashed border-gray-700 hover:border-[var(--theme-color-50)] transition-colors rounded-xl p-6 text-center relative group min-h-[140px] flex items-center justify-center">
                    <input type="file" wire:model.live="uploads" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                    <div class="absolute inset-0 flex items-center justify-center w-full h-full" wire:loading wire:target="uploads">
                        <span class="text-[var(--theme-color)] font-bold text-sm tracking-widest animate-pulse">Lade hoch...</span>
                    </div>
                    <div class="pointer-events-none relative z-10 flex flex-col items-center" wire:loading.remove wire:target="uploads">
                        <x-heroicon-o-cloud-arrow-up class="w-10 h-10 text-gray-500 group-hover:text-[var(--theme-color)] transition-colors mb-2" />
                        <p class="text-gray-300 font-bold text-sm">Neue Arbeitsberichte hochladen</p>
                        <p class="text-gray-500 text-[10px] mt-1 uppercase tracking-widest">Drag & Drop oder Klicken (Multi-Auswahl möglich)</p>
                    </div>
                </div>
                
                @if(count($uploads) > 0)
                    <div class="bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] rounded-xl p-4 flex justify-between items-center">
                        <span class="text-[var(--theme-color)] text-xs font-sans">{{ count($uploads) }} Datei(en) bereit zum Speichern</span>
                        <button wire:click.prevent="saveUploads" class="bg-[var(--theme-color)] hover:bg-[var(--theme-color-80)] text-gray-900 text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-lg transition-colors">Jetzt Speichern</button>
                    </div>
                @endif

                @if(count($reportFiles) > 0)
                    <div class="bg-gray-900/60 backdrop-blur-xl border border-gray-800/60 rounded-xl overflow-hidden flex flex-col flex-1 min-h-[250px]">
                        <div class="bg-gray-900 border-b border-gray-800 px-4 py-3 flex justify-between items-center">
                            <h4 class="text-gray-300 text-xs font-bold uppercase tracking-widest">Gespeicherte Updates</h4>
                            <span class="bg-gray-800 text-gray-400 text-[10px] px-2 py-0.5 rounded">{{ count($reportFiles) }} Dateien</span>
                        </div>
                        <div class="p-2 space-y-1 overflow-y-auto custom-scrollbar flex-1 max-h-[300px]">
                            @foreach($reportFiles as $file)
                                @php $filename = basename($file); @endphp
                                <div class="group flex items-center justify-between p-2 rounded-lg hover:bg-gray-800/50 transition-colors border border-transparent hover:border-gray-700/50 {{ in_array($file, $selectedReports) ? 'bg-gray-800/80 border-gray-700/50' : '' }}">
                                    <div class="flex items-center gap-3 cursor-pointer flex-1" wire:click="toggleReport('{{ $file }}')">
                                        <div class="{{ in_array($file, $selectedReports) ? 'text-[var(--theme-color)]' : 'text-gray-500' }} transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </div>
                                        <span class="text-sm font-sans {{ in_array($file, $selectedReports) ? 'text-white font-bold' : 'text-gray-400' }} truncate" title="{{ $filename }}">{{ $filename }}</span>
                                    </div>
                                    <button wire:click.prevent="deleteReport('{{ $file }}')" wire:confirm="Bericht wirklich endgültig löschen?" class="opacity-0 group-hover:opacity-100 p-1.5 text-red-500 hover:bg-red-500/20 rounded-md transition-all">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="bg-gray-900/60 backdrop-blur-xl border border-gray-800/60 rounded-xl p-8 flex flex-col items-center justify-center h-full text-gray-500 border-dashed">
                        <x-heroicon-o-folder-open class="w-12 h-12 mb-3 opacity-20" />
                        <p class="text-xs uppercase tracking-widest font-sans">Keine Berichte vorhanden</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- MULTI-SELECT VIEWER SEKTION --}}
        @if(count($selectedReports) > 0)
            <div class="mt-8 space-y-4 relative z-10 animate-fade-in-up">
                <h3 class="text-[var(--theme-color)] font-bold uppercase tracking-widest text-sm mb-4 border-b border-gray-800 pb-2">Geöffnete Berichte ({{ count($selectedReports) }})</h3>
                @foreach($selectedReports as $reportPath)
                    <div class="bg-gray-950 border border-gray-700/50 shadow-2xl rounded-xl overflow-hidden mb-4">
                        <div class="bg-gray-900 border-b border-gray-800 px-4 py-2 flex justify-between items-center">
                            <span class="text-[var(--theme-color)] text-xs font-sans font-bold">{{ basename($reportPath) }}</span>
                            <button wire:click.prevent="toggleReport('{{ $reportPath }}')" class="text-gray-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="p-6 text-gray-300 font-sans text-xs leading-relaxed overflow-x-auto whitespace-pre-wrap">
                            {!! nl2br(e($this->getReportContent($reportPath))) !!}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

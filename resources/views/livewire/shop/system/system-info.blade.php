<div style="--theme-color: {{ $this->themeColorHex }};">
    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-gray-900/80 backdrop-blur-xl backdrop-blur-md p-4 sm:p-6 rounded-2xl shadow-xl shadow-[var(--theme-color-10)] border border-gray-800 relative overflow-hidden my-4">
            <div class="absolute top-0 right-0 p-4 opacity-10 blur-sm pointer-events-none">
                <x-heroicon-o-server class="w-24 h-24 text-[var(--theme-color)] drop-shadow-xl shadow-[var(--theme-color-10)]" />
            </div>
            <div class="relative z-10">
                <h1 class="text-2xl sm:text-3xl font-black text-[var(--theme-color)] drop-shadow-sm">System-Info & Hosting</h1>
                <p class="text-gray-400 mt-2 text-sm">Server-Metriken, Systemumgebung und KI-Hosting-Tarife.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-900/80 backdrop-blur-xl backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-4 font-sans relative overflow-hidden group flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Laravel Core</div>
                    <div class="text-xl font-black text-gray-200 group-hover:text-[var(--theme-color)] transition-colors">v{{ $laravelVersion }}</div>
                </div>
                <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                    <x-heroicon-o-code-bracket class="w-8 h-8 text-[var(--theme-color)]" />
                </div>
            </div>

            <div class="bg-gray-900/80 backdrop-blur-xl backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-4 font-sans relative overflow-hidden group flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">PHP Engine</div>
                    <div class="text-xl font-black text-gray-200 group-hover:text-indigo-400 transition-colors">v{{ $phpVersion }}</div>
                </div>
                <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                    <x-heroicon-o-command-line class="w-8 h-8 text-indigo-500" />
                </div>
            </div>

            <div class="bg-gray-900/80 backdrop-blur-xl backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-4 font-sans relative overflow-hidden group flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Datenbank</div>
                    <div class="text-xl font-black text-gray-200 group-hover:text-pink-400 transition-colors">SQLite (In-Memory)</div>
                </div>
                <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                    <x-heroicon-o-circle-stack class="w-8 h-8 text-pink-500" />
                </div>
            </div>

            <div class="bg-gray-900/80 backdrop-blur-xl backdrop-blur-xl border border-gray-800 shadow-[inset_0_0_20px_var(--theme-color-10)] rounded-2xl p-4 font-sans relative overflow-hidden group hover:border-[var(--theme-color)]/80 transition-all cursor-pointer flex justify-between items-center">
                <div>
                    <div class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-1">KI Hosting Partner</div>
                    <div class="text-xl font-black text-[var(--theme-color)] drop-shadow-xl shadow-[var(--theme-color-10)]">Google Cloud (Gemini)</div>
                </div>
                <div class="opacity-40 transition-opacity group-hover:opacity-80 group-hover:animate-pulse">
                    <x-heroicon-o-shield-check class="w-8 h-8 text-[var(--theme-color)]" />
                </div>
            </div>
        </div>

        <div class="text-center mb-4">
            <h2 class="text-xl sm:text-2xl font-black text-gray-200 tracking-widest uppercase font-sans mb-1">KI-Hosting Tarife</h2>
            <p class="text-gray-500 font-sans text-[10px] uppercase tracking-widest">Global Scale Hosting & Vertex AI Engine</p>
        </div>

        @if(session()->has('message'))
            <div class="mb-4 bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] p-3 rounded-lg text-xs font-sans text-center">
                {{ session('message') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 items-stretch mb-8">
            @foreach($aiPlans as $plan)
                <div wire:click="setActivePlan({{ $plan->id }})" 
                     class="cursor-pointer font-sans flex flex-col h-full transition-all relative rounded-2xl p-5 border 
                     {{ $plan->is_active ? 'bg-[var(--theme-color-10)] border-[var(--theme-color)] shadow-xl shadow-[var(--theme-color-10)] transform scale-[1.02] z-10' : 'bg-gray-900/80 backdrop-blur-xl backdrop-blur-xl border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] hover:border-gray-500' }}">
                    
                    @if($plan->is_active)
                        <div class="absolute -top-3 -right-3 bg-[var(--theme-color)] text-black text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full shadow-xl shadow-[var(--theme-color-10)] z-20 flex items-center gap-1">
                            <x-heroicon-s-check-circle class="w-3 h-3" /> Aktiv
                        </div>
                    @endif

                    <div class="flex justify-between items-start mb-1">
                        <h3 class="text-lg font-black uppercase tracking-widest {{ str_contains($plan->name, 'Pro') || $plan->name === 'Lokal gehostet' ? 'text-[peru]' : 'text-gray-300' }}">
                            {{ $plan->name }}
                        </h3>
                        @if(!$plan->is_active && !str_contains($plan->name, 'Google') && !str_contains($plan->name, 'Custom'))
                            <button wire:click.stop="deletePlan({{ $plan->id }})" wire:confirm="Individuelles Paket löschen?" class="text-gray-500 hover:text-red-500 transition-colors">
                                <x-heroicon-o-trash class="w-4 h-4" />
                            </button>
                        @endif
                    </div>
                    
                    <div class="flex items-baseline gap-2 mb-1">
                        @if($plan->price_monthly > 0)
                            <span class="text-2xl font-black text-white">{{ number_format($plan->price_monthly, 2, ',', '.') }} €</span>
                        @else
                            <span class="text-2xl font-black text-[var(--theme-color)]">Verbrauch</span>
                        @endif
                    </div>
                    <p class="text-[9px] text-gray-500 uppercase tracking-widest mb-3 border-b border-gray-800 pb-2">
                        {{ $plan->price_monthly > 0 ? 'pro Monat zzgl. USt.*' : 'Pay-As-You-Go (Abrechnung nach Google Tokens)' }}
                    </p>

                    <ul class="space-y-2 mb-2 flex-1 text-[11px] text-gray-400 tracking-wider">
                        <li class="flex items-start gap-2">
                            <x-heroicon-s-check-circle class="w-3.5 h-3.5 {{ $plan->is_active ? 'text-[var(--theme-color)]' : 'text-gray-600' }} shrink-0 mt-0.5" /> 
                            <span class="{{ $plan->is_active ? 'text-white' : '' }}">
                                {{ $plan->token_limit > 0 ? number_format($plan->token_limit, 0, ',', '.') . ' Tokens intern tracken' : 'API Unbegrenzt' }}
                            </span>
                        </li>
                        <li class="flex items-start gap-2 text-gray-500">
                            <x-heroicon-o-check class="w-3.5 h-3.5 shrink-0 mt-0.5" /> Alle Modelle verfügbar
                        </li>
                        <li class="flex items-start gap-2 text-gray-500">
                            <x-heroicon-o-check class="w-3.5 h-3.5 shrink-0 mt-0.5" /> OpenAI-kompatible API
                        </li>
                    </ul>

                    @if(!$plan->is_active)
                        <div class="mt-4 text-center border border-gray-800 hover:bg-gray-800 transition-colors py-2 rounded-lg text-xs font-bold text-gray-400 uppercase tracking-widest">
                            Auswählen
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Create Custom Local Plan Form -->
        <div class="bg-gray-900/60 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-xl p-6 font-sans mb-6">
            <h3 class="text-[var(--theme-color)] font-bold uppercase tracking-widest text-sm border-b border-gray-800 pb-2 mb-4 flex items-center gap-2">
                <x-heroicon-o-server-stack class="w-5 h-5"/> Individuelles Hosting Paket hinterlegen („Lokal gehostet“)
            </h3>
            <p class="text-xs text-gray-400 mb-4 tracking-wider">Definiere hier abweichende Hosting-Pakete oder eigene API Setups für genaue Analyse-Metriken im AI Dashboard.</p>
            
            <form wire:submit.prevent="saveNewPlan" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">Paket-Name</label>
                    <input type="text" wire:model="newPlanName" placeholder="Z.b. RunPod GPU / Lokal" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-2 text-white text-sm focus:border-[var(--theme-color)] focus:ring-0">
                    @error('newPlanName') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">Token Limit / Monat</label>
                    <input type="number" wire:model="newPlanTokens" placeholder="Leer = Unbegrenzt" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-2 text-white text-sm focus:border-[var(--theme-color)] focus:ring-0">
                    @error('newPlanTokens') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">Preis / Monat (€)</label>
                    <input type="number" step="0.01" wire:model="newPlanPrice" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-2 text-white text-sm focus:border-[var(--theme-color)] focus:ring-0">
                    @error('newPlanPrice') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                </div>
                <div>
                    <button type="submit" class="w-full bg-gray-800 hover:bg-gray-700 text-[var(--theme-color)] hover:bg-[var(--theme-color)] text-white font-bold uppercase tracking-widest text-xs py-2.5 rounded-lg transition-colors border border-[var(--theme-color)]">
                        Speichern & Hinzufügen
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-12 bg-gray-900/80 backdrop-blur-xl backdrop-blur-md p-6 sm:p-8 rounded-2xl shadow-xl shadow-[var(--theme-color-10)] border border-gray-800 relative overflow-hidden">
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
                            <button wire:click="saveUploads" class="bg-[var(--theme-color)] text-black hover:bg-[var(--theme-color-80)] text-black text-white text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-lg transition-colors">Jetzt Speichern</button>
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
                                        <button wire:click="deleteReport('{{ $file }}')" wire:confirm="Bericht wirklich endgültig löschen?" class="opacity-0 group-hover:opacity-100 p-1.5 text-red-500 hover:bg-red-500/20 rounded-md transition-all">
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
                <div class="mt-8 space-y-4 relative z-10">
                    <h3 class="text-[var(--theme-color)] font-bold uppercase tracking-widest text-sm mb-4 border-b border-gray-800 pb-2">Geöffnete Berichte ({{ count($selectedReports) }})</h3>
                    @foreach($selectedReports as $reportPath)
                        <div class="bg-gray-950 border border-gray-700/50 shadow-2xl rounded-xl overflow-hidden animate-fade-in-up">
                            <div class="bg-gray-900 border-b border-gray-800 px-4 py-2 flex justify-between items-center">
                                <span class="text-[var(--theme-color)] text-xs font-sans font-bold">{{ basename($reportPath) }}</span>
                                <button wire:click="toggleReport('{{ $reportPath }}')" class="text-gray-400 hover:text-white transition-colors">
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
</div>

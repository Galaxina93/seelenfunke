            
                <div wire:key="settings-view-container" class="flex-1 overflow-y-auto w-full h-full relative rounded-2xl flex flex-col bg-gray-950/80 border border-gray-800 p-6 pb-28 lg:p-10 shadow-2xl backdrop-blur-md custom-scrollbar">
                    <div class="fixed bottom-4 left-4 right-4 lg:absolute lg:bottom-auto lg:left-auto lg:right-4 top-auto lg:top-4 z-50 mb-4 lg:mb-0 shrink-0 shadow-2xl lg:shadow-none">
                        <button wire:click="$set('activeWorkspaceView', 'workspace')" class="w-full lg:w-auto justify-center bg-[var(--theme-color-10)] lg:bg-gray-950 border border-[var(--theme-color-50)] lg:border-gray-800 text-[var(--theme-color)] lg:text-gray-400 px-4 py-3.5 lg:py-2.5 rounded-xl text-xs font-black uppercase tracking-widest hover:text-white hover:border-gray-600 transition-all shadow-[inset_0_0_15px_var(--theme-color-10)] lg:shadow-xl flex items-center gap-2 backdrop-blur-3xl lg:backdrop-blur-xl shrink-0 z-50">
                            <x-heroicon-o-arrow-left class="w-4 h-4"/> Zurück zur Schaltzentrale
                        </button>
                    </div>
                    
                    <div class="max-w-3xl w-full mx-auto relative z-10 pt-8 lg:pt-0">
                        <div class="mb-10 text-center">
                            <h2 class="text-3xl font-black text-white tracking-widest uppercase inline-flex items-center gap-3">
                                <x-heroicon-o-cog-6-tooth class="w-8 h-8 text-[var(--theme-color)]" /> Einstellungen
                            </h2>
                            <p class="text-sm font-mono text-gray-400 tracking-wider mt-2">Zentrale Verwaltung für deinen Workspace</p>
                        </div>

                        <div class="space-y-6">
                            <!-- KI Ausführungspläne Panel -->
                            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 shadow-inner relative overflow-hidden group">
                                <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color-10)] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                                
                                <div class="flex items-start justify-between gap-6 relative z-10">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-200 mb-1 flex items-center gap-2">
                                            <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-[var(--theme-color)]" /> Generierte Ausführungspläne immer durchführen
                                        </h3>
                                        <p class="text-sm font-mono text-gray-400 leading-relaxed max-w-2xl">
                                            Wenn diese Option deaktiviert ist (Sicherheitsmodus), hält die Künstliche Intelligenz nach dem Erstellen des Schlachtplans ("Todo-Liste") an und fragt dich erst um Erlaubnis. Wenn diese Option aktiviert wird, führt die KI die geplanten Schritte sofort automatisiert der Reihe nach aus.
                                        </p>
                                    </div>
                                    <div class="shrink-0 mt-1">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" wire:model.live="autoApprovePlan" class="sr-only peer">
                                            <div class="w-14 h-7 bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:bg-[var(--theme-color)] shadow-inner transition-colors duration-300"></div>
                                            <div class="absolute left-[2px] top-[2px] bg-white border border-gray-300 rounded-full h-6 w-6 transition-transform duration-300 peer-checked:translate-x-7 peer-checked:border-white shadow-sm pointer-events-none"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            </div>

                            <!-- KI HOSTING TARIFE -->
                            <div class="mt-12 mb-6 text-center">
                                <h2 class="text-2xl font-black text-white tracking-widest uppercase inline-flex items-center gap-3">
                                    <x-heroicon-o-server-stack class="w-7 h-7 text-[var(--theme-color)]" /> KI Hosting Tarife
                                </h2>
                                <p class="text-xs font-mono text-gray-400 tracking-wider mt-2">Zentrale LLM-Zugangssteuerung & Paketverwaltung</p>
                            </div>

                            @if(session()->has('message'))
                                <div class="bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] p-3 rounded-lg text-xs font-sans text-center mb-6 shadow-inner">
                                    {{ session('message') }}
                                </div>
                            @endif

                            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                                <!-- Tarife Liste -->
                                <div class="xl:col-span-2 space-y-6">
                                    @foreach($this->aiPlans as $plan)
                                        <div class="bg-gray-900 border {{ $plan->is_active ? 'border-[var(--theme-color-50)] shadow-[0_0_15px_rgba(var(--theme-color-rgb),0.2)]' : 'border-gray-800' }} rounded-2xl p-6 relative overflow-hidden transition-all group">
                                            @if($plan->is_active)
                                                <div class="absolute top-0 right-0 -mt-2 -mr-2 w-16 h-16 bg-[var(--theme-color-20)] rounded-full blur-xl pointer-events-none"></div>
                                                <div class="absolute top-4 right-4 text-[var(--theme-color)] flex items-center gap-1.5 text-xs font-bold uppercase tracking-widest">
                                                    <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] animate-pulse"></span> Aktiv
                                                </div>
                                            @endif

                                            <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-4 relative z-10">
                                                <div>
                                                    <h3 class="text-xl font-black text-white group-hover:text-[var(--theme-color)] transition-colors">{{ $plan->name }}</h3>
                                                    @if($plan->description)
                                                        <p class="text-xs text-[var(--theme-color-80)] mt-1 font-mono">{!! nl2br(e($plan->description)) !!}</p>
                                                    @endif
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <div class="text-2xl font-black text-white">{{ number_format($plan->price_monthly, 2, ',', '.') }} €<span class="text-xs text-gray-500 font-normal">/Monat</span></div>
                                                    <div class="text-xs text-gray-500 font-mono mt-1">
                                                        {{ $plan->token_limit ? number_format($plan->token_limit, 0, ',', '.') . ' Tokens' : 'Unlimitiert / Extern' }}
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Alpine Accordion für Features -->
                                            @if(is_array($plan->features) && count($plan->features) > 0)
                                                <div class="mt-6 border-t border-gray-800/60 pt-4" x-data>
                                                    <h4 class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Vorteile & Features</h4>
                                                    <div class="space-y-2">
                                                        @foreach($plan->features as $idx => $feature)
                                                            <details class="group/details bg-gray-950/50 rounded-xl border border-gray-800/50 overflow-hidden [&_summary::-webkit-details-marker]:hidden">
                                                                <summary class="flex items-center gap-3 p-3 cursor-pointer select-none hover:bg-gray-800/30 transition-colors list-none">
                                                                    <div class="shrink-0 text-[var(--theme-color)] mt-0.5">
                                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" transform="scale(0.8) translate(2,2)" d="M5 13l4 4L19 7" /></svg>
                                                                    </div>
                                                                    <span class="text-sm font-bold text-gray-300 {{ empty($feature['description']) ? 'pointer-events-none' : '' }}">{{ $feature['title'] }}</span>
                                                                    
                                                                    @if(!empty($feature['description']))
                                                                        <span class="ml-auto text-gray-500 group-open/details:rotate-180 transition-transform">
                                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                                                        </span>
                                                                    @endif
                                                                </summary>
                                                                
                                                                @if(!empty($feature['description']))
                                                                    <div class="px-10 pb-4 text-xs font-sans text-gray-400 leading-relaxed bg-gray-950/30">
                                                                        {{ $feature['description'] }}
                                                                    </div>
                                                                @endif
                                                            </details>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="mt-6 flex flex-wrap gap-2 relative z-10 pt-4 border-t border-gray-800/60">
                                                @if(!$plan->is_active)
                                                    <button wire:click="setActivePlan({{ $plan->id }})" class="bg-[var(--theme-color)] text-gray-900 border border-[var(--theme-color-50)] text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-lg hover:bg-[var(--theme-color-80)] transition-colors shadow-lg">
                                                        Tarif aktivieren
                                                    </button>
                                                @endif
                                                <button wire:click="editPlan({{ $plan->id }})" class="bg-gray-800 text-gray-300 border border-gray-700 text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-lg hover:bg-gray-700 hover:text-white transition-colors">
                                                    Bearbeiten
                                                </button>
                                                @if(!$plan->is_active)
                                                    <button wire:click="deletePlan({{ $plan->id }})" wire:confirm="Paket wirklich löschen?" class="bg-gray-950 text-red-500 border border-gray-800 hover:border-red-500/50 hover:bg-red-950/30 text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-lg transition-colors ml-auto">
                                                        Löschen
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <!-- Editor / Creator Form -->
                                <div class="xl:col-span-1">
                                    <div class="bg-gray-900 border border-[var(--theme-color-20)] rounded-2xl p-6 sticky top-6 shadow-2xl shadow-black/50">
                                        <h3 class="text-lg font-black text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                                            @if($editingPlanId)
                                                <x-heroicon-o-pencil-square class="w-5 h-5 text-[var(--theme-color)]" /> Paket Editieren
                                            @else
                                                <x-heroicon-o-plus-circle class="w-5 h-5 text-[var(--theme-color)]" /> Neuer Tarif
                                            @endif
                                        </h3>

                                        <form wire:submit.prevent="saveNewPlan" class="space-y-4">
                                            <div>
                                                <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Paketname</label>
                                                <input type="text" wire:model="newPlanName" required class="w-full bg-gray-950 border border-gray-800 rounded-lg text-sm text-white px-4 py-2.5 focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] transition-colors">
                                            </div>

                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Preis (€/Mt)</label>
                                                    <input type="number" step="0.01" wire:model="newPlanPrice" required class="w-full bg-gray-950 border border-gray-800 rounded-lg text-sm text-white px-4 py-2.5 focus:border-[var(--theme-color)] transition-colors">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Tokens (0=Endlos)</label>
                                                    <input type="number" wire:model="newPlanTokens" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-sm text-white px-4 py-2.5 focus:border-[var(--theme-color)] transition-colors" placeholder="e.g. 5000000">
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Beschreibung / Untertitel</label>
                                                <textarea wire:model="newPlanDescription" rows="2" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-sm text-white px-4 py-2 focus:border-[var(--theme-color)] transition-colors resize-none" placeholder="z.B. Nur in den ersten 3 Monaten..."></textarea>
                                            </div>

                                            <!-- Dynamische Features (Wiederholer) -->
                                            <div class="border-t border-gray-800 pt-4 mt-2">
                                                <div class="flex justify-between items-center mb-2">
                                                    <label class="block text-[10px] uppercase font-bold text-[var(--theme-color)]">Vorteil-Liste (Haken)</label>
                                                    <button type="button" wire:click="addFeatureRow" class="text-xs text-gray-400 hover:text-white transition-colors bg-gray-800 hover:bg-gray-700 px-2 py-0.5 rounded flex items-center gap-1">+ Zeile</button>
                                                </div>
                                                
                                                <div class="space-y-3 max-h-[300px] overflow-y-auto px-1 -mx-1 custom-scrollbar">
                                                    @foreach($newPlanFeatures as $index => $feat)
                                                        <div class="bg-gray-950 p-3 rounded-xl border border-gray-800 relative group/row">
                                                            <input type="text" wire:model="newPlanFeatures.{{ $index }}.title" placeholder="Titel / Bulletpoint" class="w-full bg-transparent border-none text-sm text-white focus:ring-0 px-0 py-1 mb-1 font-bold placeholder-gray-600">
                                                            <textarea wire:model="newPlanFeatures.{{ $index }}.description" rows="2" placeholder="Erklärungstext (optional)" class="w-full bg-gray-900 border border-gray-800 rounded text-xs text-gray-400 focus:border-[var(--theme-color)] transition-colors px-2 py-1.5 resize-none"></textarea>
                                                            
                                                            <button type="button" wire:click="removeFeatureRow({{ $index }})" class="absolute top-2 right-2 opacity-0 group-hover/row:opacity-100 text-red-500 hover:bg-red-500/20 p-1 rounded transition-all">
                                                                <x-heroicon-s-x-mark class="w-3 h-3" />
                                                            </button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div class="pt-4 flex gap-2">
                                                <button type="submit" class="flex-1 bg-[var(--theme-color)] text-gray-900 border border-[var(--theme-color-50)] text-xs font-bold uppercase tracking-widest px-4 py-3 rounded-xl hover:bg-[var(--theme-color-80)] transition-colors text-center shadow-[0_0_15px_rgba(var(--theme-color-rgb),0.3)]">
                                                    {{ $editingPlanId ? 'Speichern' : 'Hinzufügen' }}
                                                </button>
                                                @if($editingPlanId)
                                                    <button type="button" wire:click="cancelEdit" class="bg-gray-800 text-gray-300 border border-gray-700 text-xs font-bold uppercase tracking-widest px-4 py-3 rounded-xl hover:bg-gray-700 transition-colors shrink-0">
                                                        Abbrechen
                                                    </button>
                                                @endif
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

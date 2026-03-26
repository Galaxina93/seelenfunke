{{--
<div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-white mb-2">Funkira Einstellungen</h2>
        <p class="text-gray-400">Verwalte den KI-Agenten "Funkira". Alle Felder sind optional; greift auf Standardwerte zurück, wenn leer.</p>
    </div>

    @if (session()->has('message'))
        <div class="bg-emerald-500/20 border border-emerald-500/50 text-emerald-300 px-4 py-3 rounded-lg mb-6">
            {{ session('message') }}
        </div>
    @endif

    <div class="space-y-10">

        <!-- A. Die Modus-Steuerung -->
        <section>
            <h3 class="text-xl font-semibold text-white mb-2">A. Modus-Steuerung</h3>
            <div class="mb-6 flex flex-col gap-2 border-b border-gray-700 pb-4">
                <p class="text-sm text-gray-400">Wähle die grundsätzliche Persona und das Verhalten von Funkira. Modustasten überschreiben nicht deinen API Schlüssel, aber das "Temperament" (Kreativität) und das System-Prompt.</p>
                <div class="flex items-center gap-2 text-[10px] sm:text-xs text-primary/80 bg-primary/10 border border-primary/20 w-fit px-3 py-1.5 rounded-md">
                    <i class="bi bi-robot"></i> <span>Funkira kann ihren eigenen Modus je nach Situation anpassen.</span>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Effizienz -->
                <button wire:click="setMode('business')" class="relative rounded-2xl p-6 text-left transition-all duration-300 border {{ $activeMode === 'business' ? 'bg-indigo-900/40 border-indigo-500 shadow-[0_0_15px_rgba(99,102,241,0.2)]' : 'bg-gray-900/50 border-gray-800 hover:bg-gray-800 hover:border-gray-700' }}">
                    <div class="text-3xl mb-3">🚀</div>
                    <h4 class="text-lg font-bold text-white mb-1">Absolute Effizienz</h4>
                    <p class="text-xs text-gray-400 mb-2">Unternehmensskalierung, Gewinnoptimierung.</p>
                    <ul class="text-[11px] text-gray-500 space-y-1 list-disc list-inside">
                        <li>Kürzeste, präzise Antworten</li>
                        <li>Kein Smalltalk</li>
                        <li>Temperature = 0.1</li>
                    </ul>
                </button>

                <!-- Gemischtes Verhalten -->
                <button wire:click="setMode('default')" class="relative rounded-2xl p-6 text-left transition-all duration-300 border {{ $activeMode === 'default' ? 'bg-primary/20 border-primary shadow-[0_0_15px_rgba(197,160,89,0.2)]' : 'bg-gray-900/50 border-gray-800 hover:bg-gray-800 hover:border-gray-700' }}">
                    <div class="text-3xl mb-3">⚖️</div>
                    <h4 class="text-lg font-bold text-white mb-1">Gemischtes Verhalten</h4>
                    <p class="text-xs text-gray-400 mb-2">Erfolg von Mein-Seelenfunke, lockere Handhabung.</p>
                    <ul class="text-[11px] text-gray-500 space-y-1 list-disc list-inside">
                        <li>Professionell & charismatisch</li>
                        <li>Ausgewogener Prompt</li>
                        <li>Temperature = 0.4</li>
                    </ul>
                </button>

                <!-- Chill mal -->
                <button wire:click="setMode('chill')" class="relative rounded-2xl p-6 text-left transition-all duration-300 border {{ $activeMode === 'chill' ? 'bg-emerald-900/40 border-emerald-500 shadow-[0_0_15px_rgba(16,185,129,0.2)]' : 'bg-gray-900/50 border-gray-800 hover:bg-gray-800 hover:border-gray-700' }}">
                    <div class="text-3xl mb-3">🛋️</div>
                    <h4 class="text-lg font-bold text-white mb-1">Chill mal</h4>
                    <p class="text-xs text-gray-400 mb-2">Feierabend, Familie, lockere Gespräche.</p>
                    <ul class="text-[11px] text-gray-500 space-y-1 list-disc list-inside">
                        <li>Familiär, empathisch</li>
                        <li>Greift aktiv auf Familien-Profile zu</li>
                        <li>Temperature = 0.7</li>
                    </ul>
                </button>
            </div>
        </section>

        <form wire:submit.prevent="saveAll" class="space-y-10">
            <!-- B. Das Gehirn -->
            <section class="bg-gray-900/40 border border-gray-800 rounded-3xl p-6 sm:p-8">
                <h3 class="text-xl font-semibold text-white mb-2">B. Das Gehirn (API & LLM)</h3>
                <div class="mb-6 flex flex-col gap-2 border-b border-gray-800 pb-4">
                    <p class="text-sm text-gray-400">Kern-Einstellungen der künstlichen Intelligenz. Leere Felder (Key, URL) greifen auf die lokalen <code>.env</code> Standards zurück. Die Token-Anzahl begrenzt, wie viel Text Funkira gleichzeitig lesen/schreiben kann.</p>
                    <div class="flex items-center gap-2 text-[10px] sm:text-xs text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 w-fit px-3 py-1.5 rounded-md">
                        <i class="bi bi-shield-lock"></i> <span>Geschützt: Funkira kann ihr Token-Limit & Modell selbst anpassen, aber NIEMALS den API-Key oder Provider ändern.</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">API Provider</label>
                        <select wire:model.defer="apiProvider" class="w-full bg-gray-950 border border-gray-700 rounded-lg shadow-sm focus:border-primary focus:ring focus:ring-primary/20 text-white sm:text-sm p-2.5">
                            <option value="mittwald">Mittwald (Default)</option>
                            <option value="gemini">Gemini</option>
                            <option value="lokal">Lokal</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">API-Schlüssel</label>
                        <input type="password" wire:model.defer="apiKey" placeholder="Standard-Schlüssel aus .env verwenden" class="w-full bg-gray-950 border border-gray-700 rounded-lg shadow-sm focus:border-primary focus:ring focus:ring-primary/20 text-white sm:text-sm p-2.5">
                        @if($apiKey)
                            <p class="mt-1 text-xs text-emerald-400">Überschriebener Schlüssel ist aktiv.</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">KI-Modell</label>
                        <input type="text" wire:model.defer="aiModel" placeholder="Standard: gpt-oss-120b" class="w-full bg-gray-950 border border-gray-700 rounded-lg shadow-sm focus:border-primary focus:ring focus:ring-primary/20 text-white sm:text-sm p-2.5">
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-300">Token-Limit</label>
                            <label class="inline-flex items-center cursor-pointer relative">
                                <span class="text-xs font-medium text-gray-400 mr-2">Unbegrenzt</span>
                                <input type="checkbox" wire:model.live="unlimitedTokens" class="sr-only peer">
                                <div class="w-7 h-4 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[15px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <input type="number" wire:model.defer="tokenLimit" placeholder="Standard: 8000" class="w-full bg-gray-950 border border-gray-700 rounded-lg shadow-sm focus:border-primary focus:ring focus:ring-primary/20 text-white sm:text-sm p-2.5 disabled:opacity-50 disabled:cursor-not-allowed" @if($unlimitedTokens) disabled @endif>

                        <!-- Token Usage Progress Bar -->
                        <div class="mt-3">
                            <div class="flex justify-between text-[10px] text-gray-500 mb-1">
                                <span>Verbrauchte Tokens (Cycle)</span>
                                <span>{{ $unlimitedTokens ? number_format($tokenUsage, 0, ',', '.') . ' / ∞' : number_format($tokenUsage, 0, ',', '.') . ' / ' . number_format($tokenLimit, 0, ',', '.') }}</span>
                            </div>
                            <div class="w-full bg-gray-800 rounded-full h-1.5 mb-1 overflow-hidden">
                                @php
                                    $barColor = 'bg-emerald-500';
                                    if ($tokenUsagePercent > 70) $barColor = 'bg-amber-500';
                                    if ($tokenUsagePercent > 90) $barColor = 'bg-red-500';
                                @endphp
                                <div class="{{ $barColor }} h-1.5 rounded-full transition-all duration-500 shadow-[0_0_8px_currentColor]" style="width: {{ $unlimitedTokens ? 100 : $tokenUsagePercent }}%; @if($unlimitedTokens) background-color: #10b981; @endif"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- C. Autonomie & Sicherheit -->
                <h3 class="text-xl font-semibold text-white mb-2">C. Autonomie & Sicherheit (Leitplanken)</h3>
                <div class="mb-6 flex flex-col gap-2 border-b border-gray-800 pb-4">
                    <p class="text-sm text-gray-400">Bestimmt, wie viel direkte Zerstörungskraft und Handlungsfreiheit Funkira im System hat. Das Ausführungs-Limit ist der wichtigste Schutz vor finanziellen Schäden durch KI-Endlosschleifen.</p>
                    <div class="flex items-center gap-2 text-[10px] sm:text-xs text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 w-fit px-3 py-1.5 rounded-md">
                        <i class="bi bi-shield-lock"></i> <span>Geschützt: Funkira <b>darf nicht</b> den Human-in-the-Loop umgehen oder ihr eigenes Limit erhöhen.</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-white">Human-in-the-Loop</h4>
                            <p class="text-xs text-gray-500 mt-1">Aktionen nur als Entwurf speichern. Funkira fragt nach Erlaubnis.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.defer="humanInTheLoop" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        </label>
                    </div>

                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-white">Wartungsmodus-Freigabe</h4>
                            <p class="text-xs text-gray-500 mt-1">Erlaubt Funkira bei kritischen Fehlern <code>php artisan down</code> aufzurufen.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.defer="maintenanceModeAllowed" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500"></div>
                        </label>
                    </div>

                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-300 mb-1">Maximales Ausführungs-Limit (Anti-Loop)</label>
                        <p class="text-xs text-gray-500 mb-2">Wie oft darf sie sich im "Plan-and-Execute"-Modus selbst anstupsen, um Endlosschleifen zu verhindern?</p>
                        <input type="number" wire:model.defer="executionLimit" min="1" max="10" placeholder="Standard: 3" class="w-32 bg-gray-950 border border-gray-700 rounded-lg shadow-sm focus:border-primary focus:ring focus:ring-primary/20 text-white sm:text-sm p-2.5">
                    </div>
                </div>
            </section>

            <!-- D. Fähigkeiten-Management -->
            <section class="bg-gray-900/40 border border-gray-800 rounded-3xl p-6 sm:p-8">
                <h3 class="text-xl font-semibold text-white mb-2">D. Fähigkeiten-Management</h3>
                <div class="mb-6 flex flex-col gap-2 border-b border-gray-800 pb-4">
                    <p class="text-sm text-gray-400">Groblagige Zugangsberechtigungen für Funkira. Schaltest du hier einen Bereich ab, werden ihr die entsprechenden Werkzeuge entzogen. Das verhindert Spionage oder ungewollte Aktionen in sensiblen Bereichen.</p>
                    <div class="flex items-center gap-2 text-[10px] sm:text-xs text-primary/80 bg-primary/10 border border-primary/20 w-fit px-3 py-1.5 rounded-md">
                        <i class="bi bi-robot"></i> <span>Funkira kann ihre eigenen Fähigkeiten nach Bedarf anfordern oder abschalten.</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-6">
                    <div class="flex items-center gap-4 bg-gray-950/50 p-4 rounded-xl border border-gray-800">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.defer="capShopSupport" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                        <div>
                            <h4 class="text-sm font-medium text-white">Shop-Steuerung & Support</h4>
                            <p class="text-xs text-gray-400">Erlaubt den Zugriff auf Shop-Daten und Support-Tickets.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 bg-gray-950/50 p-4 rounded-xl border border-gray-800">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.defer="capSystemDiagnostics" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                        <div>
                            <h4 class="text-sm font-medium text-white">System-Diagnose</h4>
                            <p class="text-xs text-gray-400">Zugriff auf Server-Logs und Fehleranalyse-Tools.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 bg-gray-950/50 p-4 rounded-xl border border-gray-800">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.defer="capFamilyCrm" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                        <div>
                            <h4 class="text-sm font-medium text-white">Familien-CRM</h4>
                            <p class="text-xs text-gray-400">Zugriff auf Personen-Profile (erforderlich für den 'Chill'-Modus).</p>
                        </div>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-800 border-dashed">
                    @livewire('global.ai.ai-method')
                </div>
            </section>

            <!-- E. Stimme & Sensorik -->
            <section class="bg-gray-900/40 border border-gray-800 rounded-3xl p-6 sm:p-8">
                <h3 class="text-xl font-semibold text-white mb-2">E. Stimme & Sensorik (Lokale RTX)</h3>
                <div class="mb-6 flex flex-col gap-2 border-b border-gray-800 pb-4">
                    <p class="text-sm text-gray-400">Einstellung für die Sprachausgabe. Die lokale TTS-URL verweist auf deinen lokalen KI-Server (via ngrok/Cloudflare), um Latenzen klein und Kosten auf Null zu halten.</p>
                    <div class="flex items-center gap-2 text-[10px] sm:text-xs text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 w-fit px-3 py-1.5 rounded-md">
                        <i class="bi bi-shield-lock"></i> <span>Geschützt: Funkira darf die Sprachausgabe (Mute) selbst umschalten, aber niemals die API URL verändern.</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-white">Sprachausgabe aktivieren</h4>
                            <p class="text-xs text-gray-500 mt-1">Funkira antwortet mit gesprochenem Audio.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.defer="voiceEnabled" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-500"></div>
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Lokale TTS API-URL</label>
                        <input type="url" wire:model.defer="localTtsUrl" placeholder="z. B. Cloudflare Tunnel URL" class="w-full bg-gray-950 border border-gray-700 rounded-lg shadow-sm focus:border-primary focus:ring focus:ring-primary/20 text-white sm:text-sm p-2.5">
                    </div>
                </div>
            </section>

            <div class="flex justify-end pt-4">
                <button type="submit" class="bg-primary hover:bg-primary/80 text-gray-900 font-bold py-3 px-8 rounded-full shadow-[0_0_15px_rgba(197,160,89,0.4)] transition-all">
                    Konfiguration Speichern
                </button>
            </div>
        </form>
    </div>
</div>
--}}

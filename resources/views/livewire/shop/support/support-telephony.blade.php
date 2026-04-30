<div style="--theme-color: {{ $this->themeColorHex }};" class="p-6 max-w-7xl mx-auto space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-white mb-2">
                <span class="text-[var(--theme-color)] drop-shadow-[0_0_15px_var(--theme-color)0.5)]"><i class="bi bi-telephone"></i></span> 
                Support Telefonie
            </h1>
            <p class="text-gray-400">Verwalte Anrufe, überwache KPIs und greife auf das Telefonbuch der KI zu.</p>
        </div>
        <div>
            <div class="flex space-x-2 bg-gray-800/50 p-1 rounded-xl backdrop-blur-md border border-white/10">
                <button wire:click="$set('currentTab', 'calls')" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 {{ $currentTab === 'calls' ? 'bg-[var(--theme-color)] text-gray-900 shadow' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Dashboard
                </button>
                <button wire:click="$set('currentTab', 'contacts')" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 {{ $currentTab === 'contacts' ? 'bg-[var(--theme-color)] text-gray-900 shadow' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Telefonbuch
                </button>
                <button wire:click="$set('currentTab', 'settings')" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 {{ $currentTab === 'settings' ? 'bg-[var(--theme-color)] text-gray-900 shadow' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Einstellungen
                </button>
            </div>
        </div>
    </div>

    @if($currentTab === 'calls')
        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-gray-900/40 border border-white/5 p-6 rounded-2xl backdrop-blur-md hover:border-[var(--theme-color)]/30 transition-colors">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-400 text-sm font-medium">Anrufe Heute</h3>
                    <div class="h-8 w-8 rounded-full bg-[var(--theme-color)]/20 flex items-center justify-center text-[var(--theme-color)]">
                        <i class="bi bi-telephone"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-white">{{ $kpi['total_calls_today'] }}</div>
            </div>
            
            <div class="bg-gray-900/40 border border-white/5 p-6 rounded-2xl backdrop-blur-md hover:border-[var(--theme-color)]/30 transition-colors">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-400 text-sm font-medium">Gesprächsminuten (Heute)</h3>
                    <div class="h-8 w-8 rounded-full bg-[var(--theme-color)]/20 flex items-center justify-center text-[var(--theme-color)]">
                        <i class="bi bi-clock"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-white">{{ $kpi['total_minutes_today'] }} <span class="text-sm font-normal text-gray-500">Min.</span></div>
            </div>

            <div class="bg-gray-900/40 border border-white/5 p-6 rounded-2xl backdrop-blur-md hover:border-[var(--theme-color)]/30 transition-colors">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-400 text-sm font-medium">Erfolgsquote</h3>
                    <div class="h-8 w-8 rounded-full bg-[var(--theme-color)]/20 flex items-center justify-center text-[var(--theme-color)]">
                        <i class="bi bi-graph-up"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-white">{{ $kpi['success_rate'] }}%</div>
            </div>

            <div class="bg-gray-900/40 border border-white/5 p-6 rounded-2xl backdrop-blur-md hover:border-[var(--theme-color)]/30 transition-colors">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-400 text-sm font-medium">Ø Dauer</h3>
                    <div class="h-8 w-8 rounded-full bg-[var(--theme-color)]/20 flex items-center justify-center text-[var(--theme-color)]">
                        <i class="bi bi-stopwatch"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-white">{{ $kpi['avg_duration'] }}</div>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Live Calls Section -->
            <div class="bg-gray-800/60 border border-[var(--theme-color)]/20 rounded-2xl p-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4">
                    <span class="flex h-3 w-3 relative">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[var(--theme-color)] opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-[var(--theme-color)]"></span>
                    </span>
                </div>
                <h2 class="text-xl font-semibold text-white mb-4">Aktive Anrufe</h2>
                
                @if($activeCalls->isEmpty())
                    <div class="text-gray-400 text-center py-8">
                        Aktuell telefoniert kein Agent.
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($activeCalls as $call)
                            <div class="bg-gray-900/50 border border-gray-700 p-4 rounded-xl flex items-center space-x-4">
                                <div class="h-12 w-12 rounded-full bg-[var(--theme-color)] flex items-center justify-center text-gray-900 font-bold text-lg shadow-[0_0_10px_var(--theme-color)]">
                                    {{ substr($call->agent->name ?? 'A', 0, 1) }}
                                </div>
                                <div>
                                    <div class="text-white font-medium">{{ $call->agent->name ?? 'Unbekannter Agent' }}</div>
                                    <div class="text-sm text-[var(--theme-color)] flex items-center space-x-1">
                                        <i class="bi bi-telephone-outbound"></i>
                                        <span>{{ $call->contact->name ?? $call->phone_number }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">Status: {{ ucfirst($call->status) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Call History -->
            <div class="bg-gray-900/40 border border-white/5 rounded-2xl overflow-hidden backdrop-blur-md">
                <div class="p-6 border-b border-white/5">
                    <h2 class="text-xl font-semibold text-white">Anruf-Historie</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-800/30 text-gray-400 text-xs uppercase tracking-wider">
                                <th class="p-4 font-medium">Datum</th>
                                <th class="p-4 font-medium">Agent</th>
                                <th class="p-4 font-medium">Kontakt</th>
                                <th class="p-4 font-medium">Dauer</th>
                                <th class="p-4 font-medium">Status</th>
                                <th class="p-4 font-medium text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($historyCalls as $call)
                                <tr class="hover:bg-gray-800/20 transition-colors">
                                    <td class="p-4 text-sm text-gray-300">{{ $call->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="p-4 text-sm text-white font-medium">KI Agent</td>
                                    <td class="p-4 text-sm text-gray-300">
                                        {{ $call->contact_name ?? 'Unbekannt' }}<br>
                                        <span class="text-xs text-gray-500">{{ $call->phone }}</span>
                                    </td>
                                    <td class="p-4 text-sm text-gray-300">{{ gmdate("i:s", $call->duration_seconds ?? 0) }}</td>
                                    <td class="p-4 text-sm">
                                        @if($call->status === 'completed')
                                            <span class="px-2 py-1 bg-green-500/10 text-green-400 rounded-md text-xs border border-green-500/20">Beendet</span>
                                        @else
                                            <span class="px-2 py-1 bg-red-500/10 text-red-400 rounded-md text-xs border border-red-500/20">{{ ucfirst($call->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-sm text-right">
                                        <button class="text-[var(--theme-color)] hover:text-white transition-colors text-xs font-medium mr-3" onclick="alert('Fazit: {{ addslashes($call->summary ?? 'Kein Fazit verfügbar.') }}\n\nNächste Schritte: {{ addslashes(implode(', ', json_decode($call->next_steps ?? '[]', true))) }}')">Fazit ansehen</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-gray-500">
                                        Noch keine Anrufe in der Historie.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-white/5">
                    {{ $historyCalls->links() }}
                </div>
            </div>
        </div>
    @elseif($currentTab === 'contacts')
        <div class="bg-gray-900/40 border border-white/5 rounded-2xl p-6 backdrop-blur-md">
            <h2 class="text-xl font-semibold text-white mb-4">KI Telefonbuch</h2>
            <p class="text-gray-400 text-sm mb-6">Diese Kontakte können von den Agenten namentlich angerufen werden.</p>
            
            <div class="text-center py-12 text-gray-500 border border-dashed border-gray-700 rounded-xl">
                Hier erscheint bald die Kontaktverwaltung.
            </div>
        </div>
    @elseif($currentTab === 'settings')
        <div class="bg-gray-900/40 border border-white/5 rounded-2xl p-6 backdrop-blur-md">
            <h2 class="text-xl font-semibold text-white mb-4">Regeln & Limits</h2>
            <p class="text-gray-400 text-sm mb-6">Definiere, wann und wie viel die Agenten telefonieren dürfen.</p>
            
            <div class="space-y-4">
                <div class="p-4 border border-gray-700 rounded-xl flex justify-between items-center bg-gray-800/30">
                    <div>
                        <div class="text-white font-medium">Nachtruhe (Outbound)</div>
                        <div class="text-sm text-gray-400">Verbietet ausgehende Anrufe zwischen 20:00 und 08:00 Uhr.</div>
                    </div>
                    <div class="w-12 h-6 bg-[var(--theme-color)] rounded-full relative cursor-pointer opacity-80 hover:opacity-100 transition-opacity">
                        <div class="absolute right-1 top-1 w-4 h-4 bg-gray-900 rounded-full"></div>
                    </div>
                </div>
                <div class="p-4 border border-gray-700 rounded-xl flex justify-between items-center bg-gray-800/30">
                    <div>
                        <div class="text-white font-medium">Kosten-Limit pro Tag</div>
                        <div class="text-sm text-gray-400">Maximale Gesprächsminuten pro Agent und Tag.</div>
                    </div>
                    <div>
                        <input type="number" value="120" class="bg-gray-900 border border-gray-600 focus:border-[var(--theme-color)] rounded-lg text-white w-24 text-center px-2 py-1 outline-none"> <span class="text-gray-400 ml-1">Min.</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- TIPPS, TRICKS & HINWEISE --}}
    <section class="mt-8 bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] border border-gray-800 p-6 sm:p-10 relative w-full overflow-hidden shadow-2xl mb-12">
        <div class="absolute top-0 right-0 w-64 h-64 bg-[var(--theme-color)]/10 rounded-full blur-[80px] -mr-20 -mt-20 pointer-events-none"></div>

        <div class="mb-8 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-[var(--theme-color)]/10 border border-[var(--theme-color)]/30 text-[var(--theme-color)] flex items-center justify-center shrink-0 shadow-[0_0_20px_var(--theme-color)]">
                <i class="bi bi-diagram-3 text-xl"></i>
            </div>
            <div>
                <h2 class="text-2xl font-serif font-bold text-white tracking-tight">System Dokumentation</h2>
                <p class="text-[11px] font-black text-[var(--theme-color)] uppercase tracking-widest mt-1">Native Twilio Media Streams Integration</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <div class="p-6 bg-gray-950/60 border border-gray-800 rounded-3xl backdrop-blur-sm relative overflow-hidden group hover:border-[var(--theme-color)]/30 transition-colors">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i class="bi bi-hdd-network text-8xl text-[var(--theme-color)]"></i>
                </div>
                
                <h3 class="text-[var(--theme-color)] font-bold mb-4 uppercase tracking-widest text-[11px] flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] shadow-[0_0_8px_var(--theme-color)]"></span>
                    Architektur: Der Hardcore-Weg
                </h3>
                
                <div class="space-y-4 text-sm text-gray-400">
                    <p>
                        Anstatt Drittanbieter-Wrapper zu nutzen, bauen wir die Infrastruktur selbst auf. Seelenfunke nutzt 
                        <strong class="text-white">Twilio Media Streams</strong>, um das rohe Telefonnetz in Echtzeit 
                        mit der <strong class="text-white">Google Gemini Multimodal Live API</strong> zu verbinden.
                    </p>
                    <p>
                        <strong class="text-gray-200 block mb-1">Registrierung & API Keys:</strong>
                        Du benötigst einen Account bei <a href="https://twilio.com/" target="_blank" class="text-[var(--theme-color)] hover:underline">Twilio</a> für die SIP/Festnetz-Telefoninfrastruktur 
                        sowie einen aktiven Google Cloud Account für die Gemini API. Die folgenden Variablen müssen in der <code>.env</code> Datei deines Servers hinterlegt werden:
                    </p>
                    <div class="bg-black/50 p-3 rounded-xl border border-gray-800 font-mono text-xs text-gray-300">
                        TWILIO_ACCOUNT_SID=deine_sid<br>
                        TWILIO_AUTH_TOKEN=dein_token<br>
                        TWILIO_PHONE_NUMBER=+495371...
                    </div>
                </div>
            </div>
            
            <div class="p-6 bg-gray-950/60 border border-gray-800 rounded-3xl backdrop-blur-sm relative overflow-hidden group hover:border-[var(--theme-color)]/30 transition-colors">
                <h3 class="text-[var(--theme-color)] font-bold mb-4 uppercase tracking-widest text-[11px] flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] shadow-[0_0_8px_var(--theme-color)]"></span>
                    Ablauf eines nativen Anrufs
                </h3>
                
                <div class="space-y-4 text-sm text-gray-400">
                    <p>
                        <strong class="text-gray-200 block mb-1">1. Call Initiation & TwiML</strong>
                        Ein Agent triggert das Tool <code>system_call_contact</code>. Laravel sagt der Twilio REST API: "Ruf an!". 
                        Wenn abgehoben wird, sendet Laravel einen TwiML <code>&lt;Connect&gt;&lt;Stream&gt;</code> Befehl an Twilio.
                    </p>
                    <div class="border-t border-gray-800/50 my-2"></div>
                    <p>
                        <strong class="text-gray-200 block mb-1">2. Die Audio-Bridge (WebSocket)</strong>
                        Twilio öffnet einen WebSocket zu unserer eigenen Audio-Bridge (z.B. Node.js oder Swoole). 
                        Diese Bridge übersetzt das rohe Base64 Audio-Format (mulaw 8000Hz) aus dem Telefonnetz und leitet es in Echtzeit an die Google Gemini API weiter.
                    </p>
                    <div class="border-t border-gray-800/50 my-2"></div>
                    <p>
                        <strong class="text-gray-200 block mb-1">3. Interruption Handling & Transkript</strong>
                        Sobald der Mensch der KI ins Wort fällt, erkennt die Bridge dies (VAD) und zwingt Twilio den Audio-Puffer zu leeren (<code>&lt;Clear&gt;</code>).
                        Am Ende des Gesprächs speichert die Bridge das mitgeschriebene Transkript sicher in der Laravel-Datenbank.
                    </p>
                </div>
            </div>

            <div class="p-6 bg-gray-950/60 border border-gray-800 rounded-3xl backdrop-blur-sm relative overflow-hidden group hover:border-[var(--theme-color)]/30 transition-colors">
                <h3 class="text-[var(--theme-color)] font-bold mb-4 uppercase tracking-widest text-[11px] flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] shadow-[0_0_8px_var(--theme-color)]"></span>
                    Sicherheit & Kostenkontrolle
                </h3>
                
                <div class="space-y-4 text-sm text-gray-400">
                    <p>
                        <strong class="text-gray-200 block mb-1">Volle Kontrolle</strong>
                        Da wir keine externen Voice-Plattformen nutzen, zahlen wir nur die reinen Infrastruktur-Kosten 
                        (Twilio Minuten-Preise + Google Gemini Token).
                    </p>
                    <p>
                        <strong class="text-gray-200 block mb-1">Limits beachten</strong>
                        Trotzdem gilt: Lasse das Tages-Kosten-Limit sowie den "Nachtruhe"-Switch in den Einstellungen 
                        stets aktiv, um teure Endlos-Schleifen der KI im Telefonnetz zu verhindern!
                    </p>
                </div>
            </div>

            <div class="p-6 bg-gray-950/60 border border-gray-800 rounded-3xl backdrop-blur-sm relative overflow-hidden group hover:border-[var(--theme-color)]/30 transition-colors">
                <h3 class="text-[var(--theme-color)] font-bold mb-4 uppercase tracking-widest text-[11px] flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] shadow-[0_0_8px_var(--theme-color)]"></span>
                    Preisübersicht: So setzen sich die Kosten zusammen
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-2">
                    <div class="bg-gray-900/50 p-4 rounded-2xl border border-gray-800">
                        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">1. Rufnummer (Twilio)</div>
                        <div class="text-xl font-bold text-white mb-1">~ 1,15 €</div>
                        <div class="text-xs text-gray-400">Pro Monat (Fix)</div>
                        <p class="text-[10px] text-gray-500 mt-2 leading-relaxed">Für eine lokale deutsche Festnetznummer, über die die KI erreichbar ist und nach außen telefoniert.</p>
                    </div>
                    
                    <div class="bg-gray-900/50 p-4 rounded-2xl border border-gray-800">
                        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">2. Telefonie (Twilio)</div>
                        <div class="text-xl font-bold text-white mb-1">~ 0,02 €</div>
                        <div class="text-xs text-gray-400">Pro Minute (Outbound)</div>
                        <p class="text-[10px] text-gray-500 mt-2 leading-relaxed">Für ausgehende Anrufe ins deutsche Festnetz. (Achtung: Anrufe in Mobilfunknetze kosten meist ca. 0,08 € bis 0,09 € pro Minute).</p>
                    </div>

                    <div class="bg-gray-900/50 p-4 rounded-2xl border border-gray-800 relative">
                        <div class="absolute -top-2 -right-2"><span class="flex h-3 w-3 relative"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[var(--theme-color)] opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-[var(--theme-color)]"></span></span></div>
                        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">3. Audio-Stream</div>
                        <div class="text-xl font-bold text-[var(--theme-color)] mb-1">~ 0,004 €</div>
                        <div class="text-xs text-[var(--theme-color)] opacity-80">Pro Minute</div>
                        <p class="text-[10px] text-gray-400 mt-2 leading-relaxed">Der Preis von Twilio "Media Streams", um das Live-Audio über den WebSocket an unseren eigenen Server durchzureichen.</p>
                    </div>

                    <div class="bg-gray-900/50 p-4 rounded-2xl border border-gray-800">
                        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2">4. KI-Gehirn (Google)</div>
                        <div class="text-xl font-bold text-white mb-1">Variabel</div>
                        <div class="text-xs text-gray-400">Gemini Live API</div>
                        <p class="text-[10px] text-gray-500 mt-2 leading-relaxed">Abgerechnet in Token (Input/Output Audio). Abhängig davon, wie viel die KI spricht und zuhört.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

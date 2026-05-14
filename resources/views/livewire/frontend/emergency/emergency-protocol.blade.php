<div class="min-h-screen bg-[#0a0a0a] text-white font-sans pt-20 pb-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Background Decor -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-full pointer-events-none opacity-30">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-red-900/10 rounded-full blur-3xl"></div>
    </div>

    <div class="max-w-7xl mx-auto relative z-10">
        
        <div class="text-center mb-8 sm:mb-12">
            <h1 class="text-3xl md:text-5xl font-serif font-bold text-amber-500 tracking-wide">
                Digitales Todesfall-Protokoll
            </h1>
            <p class="mt-4 text-gray-400 max-w-2xl mx-auto">
                Dieser geschützte Bereich dient ausschließlich den berechtigten Hinterbliebenen zur strukturierten Abwicklung des digitalen und organisatorischen Nachlasses.
            </p>
        </div>

        @if(!$isAuthenticated)
            <!-- Login State -->
            <div class="max-w-md mx-auto bg-black/50 border border-amber-500/20 rounded-2xl p-8 backdrop-blur-sm shadow-[0_0_50px_rgba(245,158,11,0.05)]">
                <form wire:submit.prevent="authenticate" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-amber-500 mb-2 text-center">Bitte Master-Passwort eingeben</label>
                        <input type="password" wire:model.defer="passwordInput" required autofocus
                               class="w-full bg-black/80 border border-amber-500/40 text-center text-xl tracking-widest text-white rounded-xl px-4 py-4 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all placeholder-gray-700"
                               placeholder="••••••••">
                    </div>

                    @if($errorMessage)
                        <div class="text-red-500 text-sm text-center font-medium bg-red-500/10 py-2 rounded-lg border border-red-500/20">
                            {{ $errorMessage }}
                        </div>
                    @endif

                    <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-black font-bold py-4 px-4 rounded-xl transition-colors flex items-center justify-center gap-2" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="authenticate" class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                            Zugang freischalten
                        </span>
                        <span wire:loading wire:target="authenticate" class="flex items-center gap-2">
                            <svg class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Verbindung wird hergestellt...
                        </span>
                    </button>
                </form>
            </div>
        @else
            <!-- Authenticated State (Split View) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <!-- LEFT COLUMN: Action Area & Stepper -->
                <div class="lg:col-span-8 flex flex-col gap-6">
                    
                    <!-- Global Actions -->
                    <div class="bg-black/40 border border-amber-500/20 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4 backdrop-blur-md">
                        <div class="text-sm text-gray-400">
                            Status: <span class="text-amber-500 font-bold">Protokoll aktiv</span>
                        </div>
                        <div class="flex gap-3 w-full sm:w-auto">
                            <button wire:click="downloadEmergencyPdf" class="flex-1 sm:flex-none flex justify-center items-center gap-2 bg-amber-500/10 border border-amber-500/30 hover:bg-amber-500/20 text-amber-500 px-4 py-2 rounded-xl text-sm font-bold transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                Komplette PDF Checkliste
                            </button>
                            <button wire:click="logout" class="flex justify-center items-center bg-red-900/20 border border-red-900/50 hover:bg-red-900/40 text-red-400 px-4 py-2 rounded-xl text-sm font-bold transition-all">
                                Beenden
                            </button>
                        </div>
                    </div>

                    <!-- Stepper -->
                    <div class="bg-black/40 border border-gray-800 rounded-2xl p-6 backdrop-blur-md pt-8">
                        <div class="flex items-center justify-between relative">
                            <!-- Background Line -->
                            <div class="absolute left-0 top-1/2 -translate-y-1/2 w-full h-1 bg-gray-800 z-0 rounded-full"></div>
                            <!-- Active Line -->
                            <div class="absolute left-0 top-1/2 -translate-y-1/2 h-1 bg-amber-500 z-0 transition-all duration-500 rounded-full" style="width: {{ ($currentStep - 1) * 25 }}%;"></div>

                            @foreach([1 => 'Sofort', 2 => 'Shop', 3 => 'Finanzen', 4 => 'Todos', 5 => 'Abschluss'] as $num => $label)
                                <div class="relative z-10 flex flex-col items-center">
                                    <span class="absolute bottom-full mb-3 text-xs font-medium hidden sm:block whitespace-nowrap {{ $currentStep >= $num ? 'text-amber-500' : 'text-gray-500' }}">{{ $label }}</span>
                                    <button wire:click="setStep({{ $num }})" 
                                            class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-300 {{ $currentStep === $num ? 'bg-amber-500 text-black shadow-[0_0_15px_rgba(245,158,11,0.5)] scale-110' : ($currentStep > $num ? 'bg-amber-500/20 text-amber-500 border border-amber-500' : 'bg-gray-800 text-gray-500 border border-gray-700 hover:border-gray-500') }}">
                                        @if($currentStep > $num)
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        @else
                                            {{ $num }}
                                        @endif
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Dynamic Action Area based on Current Step -->
                    <div class="bg-black/40 border border-amber-500/20 rounded-2xl p-6 md:p-8 backdrop-blur-md shadow-[0_0_30px_rgba(245,158,11,0.05)] flex-1">
                        
                        @if($currentStep === 1)
                            <!-- STEP 1 -->
                            <h2 class="text-2xl font-serif text-amber-500 mb-6">Phase 1: Sofortmaßnahmen</h2>
                            <p class="text-gray-400 mb-8">Um Zugriff auf die gesamten Geschäftsdaten, Konten und Abonnements zu erhalten, wird die zentrale Passwort-Datenbank (KeePass) benötigt.</p>
                            
                            <div class="space-y-4">
                                <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-5">
                                    <h3 class="text-xs text-gray-500 uppercase tracking-widest font-bold mb-1">Ort der KeePass-Datenbank</h3>
                                    <p class="text-lg text-white">{{ $settings['emergency_keepass_location'] ?? 'Keine Angabe' }}</p>
                                </div>
                                <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-5">
                                    <h3 class="text-xs text-gray-500 uppercase tracking-widest font-bold mb-1">Ort des Master-Passworts</h3>
                                    <p class="text-lg text-white">{{ $settings['emergency_master_password_location'] ?? 'Keine Angabe' }}</p>
                                </div>
                                <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-5">
                                    <h3 class="text-xs text-gray-500 uppercase tracking-widest font-bold mb-1">Hardware PINs (Handy/Tablet/PC)</h3>
                                    <p class="text-lg text-white">{{ $settings['emergency_hardware_pins'] ?? 'Keine Angabe' }}</p>
                                </div>
                            </div>
                            
                            <div class="mt-8 flex justify-end">
                                <button wire:click="setStep(2)" class="bg-amber-500 hover:bg-amber-600 text-black font-bold py-3 px-6 rounded-xl transition-colors flex items-center gap-2">
                                    Weiter zu Schritt 2
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </div>

                        @elseif($currentStep === 2)
                            <!-- STEP 2 -->
                            <h2 class="text-2xl font-serif text-amber-500 mb-6">Phase 2: Geschäft absichern</h2>
                            <p class="text-gray-400 mb-8">Der laufende Geschäftsbetrieb (Online-Shop) muss umgehend pausiert werden, damit keine neuen Bestellungen mehr angenommen werden und Kunden informiert sind.</p>
                            
                            <div class="bg-amber-500/5 border border-amber-500/20 rounded-xl p-8 text-center flex flex-col items-center justify-center">
                                @if($isMaintenanceMode)
                                    <div class="w-16 h-16 rounded-full bg-emerald-500/20 border-2 border-emerald-500 flex items-center justify-center mb-4">
                                        <svg class="w-8 h-8 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <h3 class="text-xl font-bold text-emerald-500 mb-2">Shop ist erfolgreich pausiert</h3>
                                    <p class="text-gray-400 max-w-md">Der Wartungsmodus ist aktiv. Kunden können aktuell nichts einkaufen. Der Shop ist gesichert.</p>
                                @else
                                    <div class="w-16 h-16 rounded-full bg-red-900/50 border-2 border-red-500 flex items-center justify-center mb-4">
                                        <svg class="w-8 h-8 text-red-500 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    </div>
                                    <h3 class="text-xl font-bold text-red-400 mb-4">Shop ist noch aktiv!</h3>
                                    <p class="text-gray-400 max-w-md mb-6">Bitte klicke auf den Button, um den Shop für Besucher unzugänglich zu machen. Die KI kann dies auf Wunsch ebenfalls für dich übernehmen.</p>
                                    
                                    <button wire:click="setMaintenanceMode" class="bg-red-600 hover:bg-red-500 text-white text-lg font-bold py-4 px-8 rounded-xl shadow-[0_0_20px_rgba(220,38,38,0.4)] transition-all flex items-center gap-2">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                        Jetzt in Wartung setzen
                                    </button>
                                @endif
                            </div>

                            <div class="mt-8 flex justify-between items-center">
                                <button wire:click="setStep(1)" class="text-gray-500 hover:text-white transition-colors text-sm font-bold flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                    Zurück
                                </button>
                                <button wire:click="setStep(3)" class="bg-amber-500 hover:bg-amber-600 text-black font-bold py-3 px-6 rounded-xl transition-colors flex items-center gap-2">
                                    Weiter zu Schritt 3
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </div>

                        @elseif($currentStep === 3)
                            <!-- STEP 3 -->
                            <div class="flex justify-between items-start mb-6">
                                <div>
                                    <h2 class="text-2xl font-serif text-amber-500 mb-2">Phase 3: Finanzielle Verpflichtungen</h2>
                                    <p class="text-gray-400">Dies ist eine Liste aller laufenden Kosten, die aus der Buchhaltung ausgelesen wurde. Du kannst für jeden Vertrag gezielt ein Kündigungsschreiben generieren.</p>
                                </div>
                                <button wire:click="$refresh" class="shrink-0 flex items-center gap-2 bg-gray-800 hover:bg-gray-700 text-gray-300 px-3 py-1.5 rounded-lg text-sm transition-colors border border-gray-700">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                    Aktualisieren
                                </button>
                            </div>
                            
                            <div class="space-y-6 mb-8">
                                @forelse($groups as $group)
                                    @if($group->items->count() > 0)
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-300 border-b border-gray-800 pb-2 mb-4">{{ $group->name }}</h3>
                                            <div class="space-y-3">
                                                @foreach($group->items as $item)
                                                    <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                                        <div class="flex items-center gap-3">
                                                            <div class="relative flex items-center justify-center shrink-0">
                                                                <input type="checkbox" class="sr-only peer" 
                                                                       wire:click="toggleFinanceTodo('{{ $item->id }}')" 
                                                                       {{ ($financeStates[$item->id] ?? false) ? 'checked' : '' }}>
                                                                <div class="w-5 h-5 border-2 rounded transition-colors cursor-pointer peer-checked:bg-emerald-500 peer-checked:border-emerald-500 border-gray-600 flex items-center justify-center">
                                                                    <svg class="w-3.5 h-3.5 text-black opacity-0 peer-checked:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                                    </svg>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <div class="font-bold {{ ($financeStates[$item->id] ?? false) ? 'text-gray-500 line-through' : 'text-white' }}">{{ $item->name }}</div>
                                                                <div class="text-xs {{ ($financeStates[$item->id] ?? false) ? 'text-gray-600' : 'text-gray-500' }} mt-1">
                                                                    {{ $item->provider_company ?? 'Kein Anbieter' }} | 
                                                                    {{ number_format($item->amount, 2, ',', '.') }} € |
                                                                    Intervall: {{ $item->interval_months }} Monat(e)
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <button wire:click="downloadSingleCancellation('{{ $item->id }}')" class="shrink-0 bg-red-900/20 border border-red-900/50 hover:bg-red-500 hover:text-white text-red-400 px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                            Kündigung PDF
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @empty
                                    <div class="text-gray-500 italic text-center py-8">Keine Einträge gefunden.</div>
                                @endforelse
                            </div>

                            <div class="mt-8 flex justify-between items-center">
                                <button wire:click="setStep(2)" class="text-gray-500 hover:text-white transition-colors text-sm font-bold flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                    Zurück
                                </button>
                                <button wire:click="setStep(4)" class="bg-amber-500 hover:bg-amber-600 text-black font-bold py-3 px-6 rounded-xl transition-colors flex items-center gap-2">
                                    Weiter zu Schritt 4
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </div>

                        @elseif($currentStep === 4)
                            <!-- STEP 4 -->
                            <h2 class="text-2xl font-serif text-amber-500 mb-6">Phase 4: Offene To-Dos</h2>
                            <p class="text-gray-400 mb-8">Hier ist eine Liste der organisatorischen Notwendigkeiten. Dein Fortschritt wird hier automatisch gesichert, du kannst die Seite jederzeit verlassen.</p>
                            
                            <div class="space-y-3">
                                @foreach($allTodos as $key => $todo)
                                    <label class="flex items-start gap-4 p-4 rounded-xl cursor-pointer transition-colors border {{ ($todoStates[$key] ?? false) ? 'bg-emerald-900/10 border-emerald-900/30' : 'bg-gray-900/50 border-gray-800 hover:border-gray-600' }}">
                                        <div class="relative flex items-center justify-center mt-1 shrink-0">
                                            <input type="checkbox" class="sr-only peer" 
                                                   wire:click="toggleTodo('{{ $key }}')" 
                                                   {{ ($todoStates[$key] ?? false) ? 'checked' : '' }}>
                                            <div class="w-6 h-6 border-2 rounded transition-colors peer-checked:bg-emerald-500 peer-checked:border-emerald-500 border-gray-500 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-black opacity-0 peer-checked:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-bold text-lg mb-1 {{ ($todoStates[$key] ?? false) ? 'text-gray-400 line-through' : 'text-gray-200' }}">{{ $todo['title'] }}</div>
                                            <div class="text-sm {{ ($todoStates[$key] ?? false) ? 'text-gray-500 line-through' : 'text-gray-400' }}">{{ $todo['desc'] }}</div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>

                            <div class="mt-8 flex justify-between items-center">
                                <button wire:click="setStep(3)" class="text-gray-500 hover:text-white transition-colors text-sm font-bold flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                    Zurück
                                </button>
                                <button wire:click="setStep(5)" class="bg-amber-500 hover:bg-amber-600 text-black font-bold py-3 px-6 rounded-xl transition-colors flex items-center gap-2">
                                    Weiter zu Schritt 5
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </div>
                        
                        @elseif($currentStep === 5)
                            <!-- STEP 5 -->
                            <h2 class="text-2xl font-serif text-amber-500 mb-6">Abschluss & Zusammenfassung</h2>
                            
                            <div class="bg-emerald-900/10 border border-emerald-900/30 rounded-xl p-8 text-center mb-8">
                                <svg class="w-16 h-16 text-emerald-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <h3 class="text-xl font-bold text-emerald-400 mb-2">Vielen Dank</h3>
                                <p class="text-gray-300 max-w-lg mx-auto italic">"Ich hoffe, dieser digitale Prozess hat dir in dieser schweren Zeit geholfen und dir eine kleine organisatorische Last von den Schultern genommen. Danke, dass du das alles für mich geregelt hast."</p>
                                <p class="text-amber-500 font-bold mt-4">– Alina Steinhauer</p>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-5">
                                    <h3 class="text-xs text-gray-500 uppercase tracking-widest font-bold mb-1">Notar / Nachlass</h3>
                                    <p class="text-white">{{ $settings['emergency_contact_notary'] ?? 'Keine Angabe' }}</p>
                                </div>
                                <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-5">
                                    <h3 class="text-xs text-gray-500 uppercase tracking-widest font-bold mb-1">Steuerberater</h3>
                                    <p class="text-white">{{ $settings['emergency_contact_tax_advisor'] ?? 'Keine Angabe' }}</p>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-between items-center">
                                <button wire:click="setStep(4)" class="text-gray-500 hover:text-white transition-colors text-sm font-bold flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                    Zurück
                                </button>
                            </div>

                        @endif
                    </div>
                </div>

                <!-- RIGHT COLUMN: AI Chat -->
                <div class="lg:col-span-4 h-full">
                    <div class="bg-black/40 border border-amber-500/20 rounded-2xl overflow-hidden backdrop-blur-md shadow-[0_0_50px_rgba(245,158,11,0.1)] flex flex-col h-[600px] lg:h-[800px] sticky top-4">
                        
                        <!-- Header -->
                        <div class="px-6 py-4 border-b border-amber-500/20 bg-black/60 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_10px_rgba(16,185,129,1)]"></div>
                                <span class="font-medium text-emerald-500 text-sm uppercase tracking-widest">Dr. Funki Agent</span>
                            </div>
                        </div>

                        <!-- Chat Messages -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-6 scroll-smooth" id="emergency-chat-container">
                            @foreach($this->messages as $msg)
                                @php
                                    $isUser = $msg->role === 'user';
                                    $ctx = $msg->context_data ?? [];
                                    $name = $ctx['name'] ?? ($isUser ? 'Du' : 'Dr. Funki');
                                    $color = $ctx['color'] ?? ($isUser ? 'amber-500' : 'emerald-500');
                                @endphp
                                
                                <div class="flex gap-3 {{ $isUser ? 'flex-row-reverse' : '' }}">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-{{ $color }}/10 border border-{{ $color }}/30 flex items-center justify-center">
                                        @if($isUser)
                                            <svg class="w-4 h-4 text-{{ $color }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                        @else
                                            <svg class="w-4 h-4 text-{{ $color }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                                        @endif
                                    </div>
                                    
                                    <div class="max-w-[85%]">
                                        <div class="text-[10px] text-gray-500 mb-1 {{ $isUser ? 'text-right' : '' }}">{{ $name }}</div>
                                        <div class="p-3 text-sm rounded-2xl {{ $isUser ? 'bg-amber-500/10 border border-amber-500/20 text-white rounded-tr-none' : 'bg-gray-800/50 border border-gray-700 text-gray-200 rounded-tl-none prose prose-invert prose-sm max-w-none' }}">
                                            @if($isUser)
                                                {!! nl2br(e(preg_replace('/\[System-Instruktion:.*?\]/s', '', $msg->content))) !!}
                                            @else
                                                {!! \Illuminate\Support\Str::markdown($msg->content) !!}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            @if($isTyping)
                                <div class="flex gap-3">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-gray-500 mb-1">Dr. Funki tippt...</div>
                                        <div class="p-3 rounded-2xl bg-gray-800/50 border border-gray-700 rounded-tl-none flex gap-1">
                                            <div class="w-1.5 h-1.5 rounded-full bg-gray-500 animate-bounce" style="animation-delay: 0ms"></div>
                                            <div class="w-1.5 h-1.5 rounded-full bg-gray-500 animate-bounce" style="animation-delay: 150ms"></div>
                                            <div class="w-1.5 h-1.5 rounded-full bg-gray-500 animate-bounce" style="animation-delay: 300ms"></div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Input Area -->
                        <div class="p-3 bg-black/60 border-t border-amber-500/20">
                            <form wire:submit.prevent="sendMessage" class="flex gap-2">
                                <input type="text" wire:model.defer="input" placeholder="Frage an den Agenten..." 
                                       class="flex-1 bg-black border border-gray-700 text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all placeholder-gray-600"
                                       {{ $isTyping ? 'disabled' : '' }}>
                                <button type="submit" 
                                        class="bg-amber-500 hover:bg-amber-600 disabled:opacity-50 disabled:hover:bg-amber-500 text-black rounded-xl px-4 font-bold transition-colors"
                                        {{ $isTyping ? 'disabled' : '' }}>
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                </button>
                            </form>
                        </div>

                    </div>
                </div>

            </div>
            
            <script>
                document.addEventListener('livewire:initialized', () => {
                    Livewire.hook('morph.updated', (el, component) => {
                        const container = document.getElementById('emergency-chat-container');
                        if (container) {
                            container.scrollTop = container.scrollHeight;
                        }
                    });
                });
            </script>
        @endif

    </div>
</div>

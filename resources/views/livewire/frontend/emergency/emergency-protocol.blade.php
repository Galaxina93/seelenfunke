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
            <!-- Authenticated State -->
            <div class="max-w-4xl mx-auto w-full">

                <!-- Action Area & Stepper -->
                <div class="flex flex-col gap-6">

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
                            <div class="absolute left-0 top-1/2 -translate-y-1/2 h-1 bg-amber-500 z-0 transition-all duration-500 rounded-full" style="width: {{ ($currentStep - 1) * 33.3333 }}%;"></div>

                            @foreach([1 => 'Sofort', 2 => 'Shop', 3 => 'Finanzen', 4 => 'Abschluss'] as $num => $label)
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

                            <div class="space-y-4 mb-10">
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

                            <h3 class="text-xl font-serif text-amber-500 mb-4 border-t border-gray-800 pt-8">Offene To-Dos & Organisatorisches</h3>
                            <p class="text-gray-400 mb-8">Hier ist eine Liste der organisatorischen Notwendigkeiten. <strong class="text-amber-500 font-normal">Bitte beachte auch die KeePass Datenbank (siehe oben)</strong>, in der sich wichtige Zugänge befinden, um die meisten der folgenden Punkte zu klären. Dein Fortschritt wird hier automatisch gesichert, du kannst die Seite jederzeit verlassen.</p>

                            @if($openOrders && $openOrders->count() > 0)
                                <div class="mb-8">
                                    <h3 class="text-lg font-bold text-gray-300 border-b border-gray-800 pb-2 mb-4">Offene Bestellungen (Nicht versendet)</h3>
                                    <p class="text-xs text-gray-500 mb-4">Die folgenden Bestellungen wurden von Kunden aufgegeben, aber noch nicht versendet. Bitte erstatte diese im jeweiligen Zahlungsanbieter (PayPal, Stripe) zurück oder storniere sie.</p>
                                    <div class="space-y-3">
                                        @foreach($openOrders as $order)
                                            <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-4 flex flex-col sm:flex-row justify-between gap-4">
                                                <div>
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="font-bold text-white">{{ $order->order_number }}</span>
                                                        <span class="text-[10px] px-2 py-0.5 rounded-full uppercase tracking-widest font-bold {{ $order->payment_status === 'paid' ? 'bg-emerald-500/20 text-emerald-500' : 'bg-yellow-500/20 text-yellow-500' }}">
                                                            {{ $order->payment_status === 'paid' ? 'Bezahlt' : 'Offen' }}
                                                        </span>
                                                    </div>
                                                    <div class="text-sm text-gray-400">{{ $order->customer_name }}</div>
                                                </div>
                                                <div class="text-right flex-shrink-0">
                                                    <div class="font-bold text-amber-500">{{ number_format($order->total_price, 2, ',', '.') }} €</div>
                                                    <div class="text-xs text-gray-500">{{ $order->payment_method ?? 'K.A.' }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <h3 class="text-lg font-bold text-gray-300 border-b border-gray-800 pb-2 mb-4">Allgemeine Todos</h3>
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
                                    <p class="text-gray-400 max-w-md mb-6">Bitte klicke auf den Button, um den Shop für Besucher unzugänglich zu machen</p>

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
                                <button x-data="{ status: 'idle' }"
                                        x-on:click="status = 'loading'; $wire.$refresh().then(() => { status = 'success'; setTimeout(() => status = 'idle', 4000); })"
                                        class="shrink-0 flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg text-sm transition-all border w-36"
                                        :class="status === 'success' ? 'bg-emerald-900/20 text-emerald-400 border-emerald-500/50' : 'bg-gray-800 hover:bg-gray-700 text-gray-300 border-gray-700'"
                                        :disabled="status === 'loading'">
                                    <template x-if="status === 'idle'">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                            Aktualisieren
                                        </div>
                                    </template>
                                    <template x-if="status === 'loading'">
                                        <div class="flex items-center gap-2">
                                            <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Lade...
                                        </div>
                                    </template>
                                    <template x-if="status === 'success'">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            Erfolgreich
                                        </div>
                                    </template>
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
                                                        <label class="flex items-center gap-3 cursor-pointer flex-1">
                                                            <div class="relative flex items-center justify-center shrink-0">
                                                                <input type="checkbox" class="sr-only peer"
                                                                       wire:click="toggleFinanceTodo('{{ $item->id }}')"
                                                                       {{ ($financeStates[$item->id] ?? false) ? 'checked' : '' }}>
                                                                <div class="w-5 h-5 border-2 rounded transition-colors peer-checked:bg-emerald-500 peer-checked:border-emerald-500 border-gray-600 flex items-center justify-center">
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
                                                        </label>
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
                                <button wire:click="setStep(3)" class="text-gray-500 hover:text-white transition-colors text-sm font-bold flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                    Zurück
                                </button>
                            </div>

                        @endif
                    </div>
                </div>

            </div>
        @endif

    </div>
</div>

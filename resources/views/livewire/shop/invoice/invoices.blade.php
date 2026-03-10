<div class="p-4 md:p-8 bg-transparent min-h-screen font-sans antialiased text-gray-300" x-data="{ draftBtnText: 'Entwurf speichern' }"
     x-on:reset-draft-success.window="setTimeout(() => { $wire.draftSuccess = false }, 3000)">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold font-serif text-white tracking-tight">Rechnungsverwaltung</h2>
            <p class="text-xs sm:text-sm text-gray-400 mt-1 font-medium">Shop-Bestellungen und manuelle Belege.</p>
        </div>
        <div class="flex flex-wrap gap-3 w-full md:w-auto">
            <button wire:click="toggleManualCreate"
                    class="flex-1 md:flex-none bg-gray-900 border border-gray-700 text-gray-300 px-5 py-2.5 rounded-xl hover:bg-gray-800 hover:text-white transition-all shadow-inner text-[10px] sm:text-xs font-black uppercase tracking-widest flex items-center justify-center gap-2">
                {{ $isCreatingManual ? 'Zurück zur Liste' : '+ Rechnung erstellen' }}
            </button>
            <button wire:click="generateForPaidOrders" wire:loading.attr="disabled"
                    class="flex-1 md:flex-none bg-primary border border-primary/50 text-gray-900 px-5 py-2.5 rounded-xl hover:bg-primary-dark hover:text-white transition-all shadow-[0_0_20px_rgba(197,160,89,0.3)] text-[10px] sm:text-xs font-black uppercase tracking-widest flex items-center justify-center gap-2 hover:scale-[1.02]">
                <span wire:loading.remove wire:target="generateForPaidOrders">Bulk-Action</span>
                <span wire:loading wire:target="generateForPaidOrders" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    Wird ausgeführt...
                </span>
            </button>
        </div>
    </div>

    @if($isCreatingManual)
        @include('livewire.shop.invoice.partials.invoice_create')
    @else
        {{-- CHARTS & STATISTIKEN --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-gray-900/50 backdrop-blur-md p-6 rounded-[2rem] border border-gray-800 shadow-2xl relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition-all duration-500"></div>
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Offene Forderungen</div>
                <div class="text-3xl font-serif font-bold text-amber-400">{{ number_format($invoiceStats['open_amount'] / 100, 2, ',', '.') }} €</div>
                <div class="text-xs font-bold text-gray-400 mt-2 flex items-center gap-1.5">
                    <span class="inline-flex w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.6)]"></span>
                    {{ $invoiceStats['open_count'] }} offene Rechnungen
                </div>
            </div>
            <div class="bg-gray-900/50 backdrop-blur-md p-6 rounded-[2rem] border border-gray-800 shadow-2xl relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all duration-500"></div>
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Umsatz (Bezahlt)</div>
                <div class="text-3xl font-serif font-bold text-emerald-400">{{ number_format($invoiceStats['paid_amount'] / 100, 2, ',', '.') }} €</div>
                <div class="text-xs font-bold text-gray-400 mt-2 flex items-center gap-1.5">
                    <span class="inline-flex w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></span>
                    {{ $invoiceStats['paid_count'] }} bezahlte Rechnungen
                </div>
            </div>
            <div class="bg-gray-900/50 backdrop-blur-md p-6 rounded-[2rem] border border-gray-800 shadow-2xl relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition-all duration-500"></div>
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Entwürfe</div>
                <div class="text-3xl font-serif font-bold text-white">{{ $invoiceStats['draft_count'] }}</div>
                <div class="text-xs font-bold text-gray-400 mt-2 flex items-center gap-1.5">Noch nicht final gebucht</div>
            </div>
            <div class="bg-gray-900/50 backdrop-blur-md p-6 rounded-[2rem] border border-gray-800 shadow-2xl relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-red-500/10 rounded-full blur-2xl group-hover:bg-red-500/20 transition-all duration-500"></div>
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Stornierungen</div>
                <div class="text-3xl font-serif font-bold text-red-400">{{ $invoiceStats['cancelled_count'] }}</div>
                <div class="text-xs font-bold text-gray-400 mt-2 flex items-center gap-1.5">Stornos & Rechnungskorrekturen</div>
            </div>
        </div>

        @include('livewire.shop.invoice.partials.invoice_main_table')

        {{-- WISSENSDATENBANK & BULK ACTION ERKLÄRUNG --}}
        <div class="mt-8 grid grid-cols-1 gap-8 animate-fade-in-up shadow-2xl">
            
            {{-- WICHTIGE HINWEISE --}}
            <div class="bg-gray-900/50 backdrop-blur-md rounded-[2.5rem] p-6 sm:p-8 border border-gray-800">
                <h3 class="text-lg font-serif font-bold text-white flex items-center gap-3 mb-6">
                    <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    Wichtige Hinweise & Best Practices
                </h3>
                
                <div class="space-y-4">
                    <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800/50 shadow-inner">
                        <h4 class="text-sm font-bold text-white mb-1">Unveränderbarkeit (GoBD)</h4>
                        <p class="text-xs text-gray-400 leading-relaxed">Sobald eine Rechnung den Status <strong>"Final"</strong> erreicht hat (oder versendet wurde), darf sie laut Finanzamt-Vorgaben nicht mehr spurlos gelöscht oder editiert werden. Fehlerhafte Rechnungen müssen zwingend über den Button "Storno" storniert werden.</p>
                    </div>
                    <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800/50 shadow-inner">
                        <h4 class="text-sm font-bold text-white mb-1">Fortlaufende Nummern</h4>
                        <p class="text-xs text-gray-400 leading-relaxed">Rechnungsnummern müssen lückenlos und fortlaufend sein. Das System weist bei der Finalisierung automatisch die nächsthöhere, logische Nummer zu.</p>
                    </div>
                    <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800/50 shadow-inner">
                        <h4 class="text-sm font-bold text-white mb-1">Entwürfe speichern</h4>
                        <p class="text-xs text-gray-400 leading-relaxed">Wenn du noch auf Daten warten musst, speichere manuelle Rechnungen als <strong>Entwurf</strong>. Entwürfe erhalten noch keine offizielle Rechnungsnummer und können jederzeit gefahrlos komplett gelöscht werden.</p>
                    </div>
                </div>
            </div>

            {{-- BULK ACTION FLOWCHART --}}
            <div class="bg-gray-900/50 backdrop-blur-md rounded-[2.5rem] p-6 sm:p-8 border border-gray-800">
                <h3 class="text-lg font-serif font-bold text-white flex items-center gap-3 mb-6">
                    <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                    Was macht die "Bulk-Action"?
                </h3>
                
                <p class="text-xs text-gray-400 leading-relaxed mb-6">Mit einem Klick auf <strong>Bulk-Action</strong> nimmt dir das System die gesamte Arbeit ab, wenn Kunden über den Front-Shop bestellt und bezahlt haben, aber die Rechnung noch fehlt.</p>

                <div class="relative w-full overflow-hidden flex justify-center py-2">
                    <svg viewBox="0 0 1000 200" class="w-full h-auto drop-shadow-2xl font-sans" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="bulkPathGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#C5A059" stop-opacity="0.3"/>
                                <stop offset="50%" stop-color="#C5A059" stop-opacity="0.9"/>
                                <stop offset="100%" stop-color="#C5A059" stop-opacity="0.3"/>
                            </linearGradient>
                            <filter id="glowBulk" x="-20%" y="-20%" width="140%" height="140%">
                                <feGaussianBlur stdDeviation="8" result="blur" />
                                <feComposite in="SourceGraphic" in2="blur" operator="over" />
                            </filter>
                        </defs>

                        <!-- Base Line -->
                        <line x1="125" y1="70" x2="875" y2="70" stroke="#374151" stroke-width="4" stroke-linecap="round" stroke-dasharray="10,10" />

                        <!-- Node 1: Scan -->
                        <g transform="translate(125, 70)">
                            <circle cx="0" cy="0" r="35" fill="#111827" stroke="#4b5563" stroke-width="2" />
                            <text x="0" y="5" fill="#9ca3af" font-size="20" font-weight="900" text-anchor="middle">1</text>
                            <text x="0" y="65" fill="#f3f4f6" font-size="14" font-weight="bold" text-anchor="middle">Scannt Shop</text>
                            <text x="0" y="85" fill="#6b7280" font-size="11" text-anchor="middle">Findet bezahlte Orders</text>
                            <text x="0" y="100" fill="#6b7280" font-size="11" text-anchor="middle">ohne Belegdokument.</text>
                        </g>

                        <!-- Node 2: Create -->
                        <g transform="translate(375, 70)">
                            <circle cx="0" cy="0" r="35" fill="#111827" stroke="#4b5563" stroke-width="2" />
                            <text x="0" y="5" fill="#9ca3af" font-size="20" font-weight="900" text-anchor="middle">2</text>
                            <text x="0" y="65" fill="#f3f4f6" font-size="14" font-weight="bold" text-anchor="middle">Beleg-Erstellung</text>
                            <text x="0" y="85" fill="#6b7280" font-size="11" text-anchor="middle">Generiert fortlaufende</text>
                            <text x="0" y="100" fill="#6b7280" font-size="11" text-anchor="middle">Nummern inkl. PDF/XML.</text>
                        </g>

                        <!-- Node 3: Send (Highlight) -->
                        <g transform="translate(625, 70)">
                            <circle cx="0" cy="0" r="42" fill="rgba(197,160,89, 0.15)" stroke="#C5A059" stroke-width="3" filter="url(#glowBulk)" />
                            <text x="0" y="6" fill="#C5A059" font-size="24" font-weight="900" text-anchor="middle">3</text>
                            <text x="0" y="70" fill="#C5A059" font-size="15" font-weight="bold" text-anchor="middle">E-Mail Versand</text>
                            <text x="0" y="90" fill="#9ca3af" font-size="12" font-weight="bold" text-anchor="middle">Kunde erhält automatisch</text>
                            <text x="0" y="105" fill="#6b7280" font-size="11" text-anchor="middle">die finale Email (+ Rechnung).</text>
                        </g>

                        <!-- Node 4: Done -->
                        <g transform="translate(875, 70)">
                            <circle cx="0" cy="0" r="35" fill="rgba(16,185,129,0.1)" stroke="#10b981" stroke-width="2" />
                            <text x="0" y="5" fill="#10b981" font-size="20" font-weight="900" text-anchor="middle">4</text>
                            <text x="0" y="65" fill="#f3f4f6" font-size="14" font-weight="bold" text-anchor="middle">Abgeschlossen</text>
                            <text x="0" y="85" fill="#6b7280" font-size="11" text-anchor="middle">Vollständig im Shop</text>
                            <text x="0" y="100" fill="#6b7280" font-size="11" text-anchor="middle">gebucht & archiviert.</text>
                        </g>
                    </svg>
                </div>
            </div>
        </div>
    @endif

    <livewire:shop.invoice.invoice-preview/>
</div>

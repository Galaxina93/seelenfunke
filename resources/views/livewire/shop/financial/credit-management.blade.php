<div class="p-4 md:p-8 bg-transparent min-h-screen font-sans antialiased text-gray-300 space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold font-serif text-white tracking-tight flex items-center gap-3">
                Gutschriften
                <div x-data="{ showInfo: false }" class="relative flex items-center">
                    <button @mouseenter="showInfo = true" @mouseleave="showInfo = false" class="text-primary hover:text-white transition-colors cursor-help">
                        <x-heroicon-o-information-circle class="w-6 h-6" />
                    </button>
                    <div x-show="showInfo" x-transition class="absolute left-8 top-1/2 -translate-y-1/2 w-80 bg-gray-800 border border-gray-700 text-gray-300 text-xs p-4 rounded-xl shadow-2xl z-50 pointer-events-none">
                        <p class="font-bold text-white mb-2">Was ist eine Gutschrift?</p>
                        <p>Eine Gutschrift (kaufmännisch) stellst du aus, wenn du einem Kunden nachträglich Geld erstatten möchtest, z.B. bei Kulanz, nachträglichen Rabatten oder Rückgaben ohne direkten System-Storno.</p>
                    </div>
                </div>
            </h2>
            <p class="text-xs sm:text-sm text-gray-400 mt-1 font-medium">Verwaltung von manuellen Gutschriften.</p>
        </div>
        <div class="flex flex-wrap gap-3 w-full md:w-auto">
            <button wire:click="openCreateModal" class="flex-1 md:flex-none bg-primary/10 text-primary border border-primary/20 px-5 py-2.5 rounded-xl hover:bg-primary hover:text-white transition-all shadow-[0_0_15px_rgba(197,160,89,0.15)] hover:shadow-[0_0_20px_rgba(197,160,89,0.4)] text-[10px] sm:text-xs font-black uppercase tracking-widest flex items-center justify-center gap-2">
                <x-heroicon-o-plus class="w-5 h-5"/>
                Neue Gutschrift
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl flex items-center gap-3 animate-fade-in-up">
            <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-500 drop-shadow-[0_0_8px_currentColor]" />
            <p class="text-sm font-semibold text-emerald-400">{{ session('success') }}</p>
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative z-10 flex items-center justify-between">
                <div>
                    <h3 class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Alle Gutschriften</h3>
                    <p class="text-3xl font-black text-white font-serif">{{ $stats['total_credits'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-gray-950 flex items-center justify-center border border-gray-800">
                    <x-heroicon-o-document-minus class="w-6 h-6 text-primary"/>
                </div>
            </div>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative z-10 flex items-center justify-between">
                <div>
                    <h3 class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Diesen Monat</h3>
                    <p class="text-3xl font-black text-white font-serif">{{ $stats['this_month'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-gray-950 flex items-center justify-center border border-gray-800">
                    <x-heroicon-o-calendar class="w-6 h-6 text-emerald-400"/>
                </div>
            </div>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-rose-500/5 to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative z-10 flex items-center justify-between">
                <div>
                    <h3 class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Gutschriften Volumen</h3>
                    <p class="text-3xl font-black text-white font-serif">{{ number_format($stats['total_volume'] / 100, 2, ',', '.') }} €</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-gray-950 flex items-center justify-center border border-gray-800">
                    <x-heroicon-o-currency-euro class="w-6 h-6 text-rose-400"/>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Interface --}}
    <div class="bg-gray-900 rounded-3xl border border-gray-800 shadow-2xl overflow-hidden relative backdrop-blur-xl">
        <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-gray-700 to-transparent"></div>

        <div class="p-6 border-b border-gray-800 flex justify-between items-center bg-gray-900/50">
            <h2 class="text-lg font-serif font-semibold text-white flex items-center gap-3">
                <x-heroicon-o-list-bullet class="w-5 h-5 text-gray-500" />
                Ausgestellte Belege
            </h2>
            <div class="relative group">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Beleg oder Kunde suchen..." class="bg-gray-950 border border-gray-800 text-white text-sm rounded-xl pl-11 pr-4 py-2.5 focus:ring-2 focus:ring-primary/30 focus:border-primary w-64 transition-all">
                <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-500 absolute left-4 top-1/2 -translate-y-1/2 group-focus-within:text-primary transition-colors" />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="text-[10px] text-gray-400 uppercase tracking-widest bg-gray-950/50 border-b border-gray-800 font-bold">
                    <tr>
                        <th class="px-6 py-4">Belegnummer & Datum</th>
                        <th class="px-6 py-4">Kunde</th>
                        <th class="px-6 py-4">Betreff</th>
                        <th class="px-6 py-4 text-right">Betrag</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50 relative">
                    @forelse($credits as $credit)
                        <tr class="hover:bg-gray-800/20 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="font-bold text-white mb-1 group-hover:text-primary transition-colors flex items-center gap-2">
                                    <x-heroicon-s-document-text class="w-4 h-4 text-gray-500" />
                                    {{ $credit->invoice_number }}
                                </div>
                                <div class="text-[11px] text-gray-500 flex items-center gap-1.5">
                                    <x-heroicon-o-calendar class="w-3.5 h-3.5" />
                                    {{ $credit->created_at->format('d.m.Y H:i') }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if($credit->customer)
                                    <div class="font-bold text-gray-200">{{ $credit->customer->first_name }} {{ $credit->customer->last_name }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $credit->customer->email }}</div>
                                @else
                                    <span class="text-gray-500 italic">Kein Kunde hinterlegt</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="max-w-xs truncate text-sm" title="{{ $credit->subject }}">
                                    {{ $credit->subject }}
                                </div>
                                @if($credit->type === 'cancellation')
                                     <span class="text-[10px] px-2 py-0.5 mt-1 inline-block bg-orange-500/10 text-orange-400 border border-orange-500/20 rounded-md">Storno</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-rose-400">
                                {{ number_format($credit->total / 100, 2, ',', '.') }} €
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Ausgestellt
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('invoice.download', $credit->id) }}" target="_blank" class="p-2 bg-gray-800 hover:bg-gray-700 text-gray-300 hover:text-white rounded-lg transition-colors inline-flex border border-gray-700 hover:border-gray-600" title="PDF ansehen">
                                    <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center text-gray-500">
                                <div class="bg-gray-950/50 rounded-2xl p-8 max-w-sm mx-auto border border-gray-800/50">
                                    <x-heroicon-o-document-minus class="w-12 h-12 mx-auto mb-4 text-gray-700" />
                                    <h3 class="text-lg font-serif font-bold text-white mb-2">Keine Belege</h3>
                                    <p class="text-sm">Bisher wurden keine Gutschriften oder Rechnungskorrekturen erstellt.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-800 bg-gray-900/50">
            {{ $credits->links() }}
        </div>
    </div>

    {{-- EDUCATIONAL SECTION --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
        <div class="bg-gray-900/50 border border-gray-800 rounded-3xl p-6 lg:p-8 backdrop-blur-sm">
            <h3 class="text-white font-serif font-bold text-lg mb-4 flex items-center gap-2">
                <x-heroicon-o-light-bulb class="w-6 h-6 text-primary" />
                Dafür nutzt du Gutschriften
            </h3>
            <ul class="space-y-4 text-sm text-gray-400">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-800 p-1 rounded-md text-primary"><x-heroicon-s-check class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-200 block mb-0.5">Nachträglicher Rabatt</strong>
                        Ein Kunde hat vergessen einen Gutscheincode einzulösen und hat sich beschwert. Du erstellst ihm hier eine Gutschrift über den Differenzbetrag.
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-800 p-1 rounded-md text-primary"><x-heroicon-s-check class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-200 block mb-0.5">Kulanz / Beschädigung</strong>
                        Ein Produkt kam leicht zerkratzt an, der Kunde behält es aber. Du erstattest als Entschuldigung 15,- €.
                    </div>
                </li>
            </ul>
        </div>
        <div class="bg-gray-900/50 border border-gray-800 rounded-3xl p-6 lg:p-8 backdrop-blur-sm">
            <h3 class="text-white font-serif font-bold text-lg mb-4 flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-emerald-500" />
                Wichtige E-Commerce Hinweise
            </h3>
            <ul class="space-y-4 text-sm text-gray-400">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-800 p-1 rounded-md text-emerald-500"><x-heroicon-o-document-text class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-200 block mb-0.5">MwSt. ist Pflicht!</strong>
                        Wurde der ursprüngliche Artikel mit 19% MwSt. gekauft, MUSS die Gutschrift ebenfalls 19% ausweisen, damit das Finanzamt dir die bereits abgeführte Steuer wieder anrechnet.
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-800 p-1 rounded-md text-emerald-500"><x-heroicon-s-arrows-right-left class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-200 block mb-0.5">Storno vs. Gutschrift</strong>
                        Wenn du eine komplette Bestellung abbrichst, nutze die "Stornieren" Funktion in der Bestellung. Dieses Modul hier ist primär für <strong>teilweise Preisnachlässe</strong> (Wertgutschriften).
                    </div>
                </li>
            </ul>
        </div>
    </div>

    {{-- WORKFLOW GRAPHIC (STEPS) --}}
    <div class="mt-8 bg-gray-900/50 border border-gray-800 rounded-3xl p-6 lg:p-8 backdrop-blur-sm">
        <h3 class="text-white font-serif font-bold text-lg mb-8 flex items-center gap-2">
            <x-heroicon-o-arrow-path-rounded-square class="w-6 h-6 text-primary" />
            Dein Gutschriften Workflow
        </h3>

        <div class="relative w-full overflow-hidden flex justify-center py-4">
            <svg viewBox="0 0 1000 200" class="w-full h-auto drop-shadow-2xl font-sans" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <marker id="arrowPrimary" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                        <path d="M 0 0 L 10 5 L 0 10 z" fill="#C5A059" />
                    </marker>
                    <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                        <feGaussianBlur stdDeviation="5" result="blur" />
                        <feComposite in="SourceGraphic" in2="blur" operator="over" />
                    </filter>
                    <linearGradient id="lineGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#4b5563" />
                        <stop offset="50%" stop-color="#C5A059" />
                        <stop offset="100%" stop-color="#10b981" />
                    </linearGradient>
                </defs>

                <!-- Connecting Path -->
                <path d="M 125 70 L 875 70" fill="none" stroke="url(#lineGrad)" stroke-width="2" stroke-dasharray="6" marker-end="url(#arrowPrimary)"/>

                <!-- Node 1: Anfrage -->
                <g transform="translate(125, 70)">
                    <circle cx="0" cy="0" r="35" fill="#111827" stroke="#4b5563" stroke-width="2" />
                    <text x="0" y="5" fill="#9ca3af" font-size="20" font-weight="900" text-anchor="middle">1</text>
                    <text x="0" y="65" fill="#f3f4f6" font-size="14" font-weight="bold" text-anchor="middle">Kundenanfrage</text>
                    <text x="0" y="85" fill="#6b7280" font-size="11" text-anchor="middle">Defekt, Retoure oder</text>
                    <text x="0" y="100" fill="#6b7280" font-size="11" text-anchor="middle">Bitte um Rabatt.</text>
                </g>

                <!-- Node 2: Prüfung -->
                <g transform="translate(375, 70)">
                    <circle cx="0" cy="0" r="35" fill="#111827" stroke="#4b5563" stroke-width="2" />
                    <text x="0" y="5" fill="#9ca3af" font-size="20" font-weight="900" text-anchor="middle">2</text>
                    <text x="0" y="65" fill="#f3f4f6" font-size="14" font-weight="bold" text-anchor="middle">Fall-Prüfung</text>
                    <text x="0" y="85" fill="#6b7280" font-size="11" text-anchor="middle">Bewertung der Sachlage,</text>
                    <text x="0" y="100" fill="#6b7280" font-size="11" text-anchor="middle">Kulanz wird gewährt.</text>
                </g>

                <!-- Node 3: Gutschrift (Highlight) -->
                <g transform="translate(625, 70)">
                    <circle cx="0" cy="0" r="42" fill="rgba(197,160,89, 0.15)" stroke="#C5A059" stroke-width="3" filter="url(#glow)" />
                    <text x="0" y="6" fill="#C5A059" font-size="24" font-weight="900" text-anchor="middle">3</text>
                    <text x="0" y="70" fill="#C5A059" font-size="15" font-weight="bold" text-anchor="middle">Gutschrift stellen</text>
                    <text x="0" y="90" fill="#9ca3af" font-size="12" font-weight="bold" text-anchor="middle">Genau hier im System</text>
                    <text x="0" y="105" fill="#6b7280" font-size="11" text-anchor="middle">als buchhalterischen Beleg.</text>
                </g>

                <!-- Node 4: Auszahlung -->
                <g transform="translate(875, 70)">
                    <circle cx="0" cy="0" r="35" fill="rgba(16,185,129,0.1)" stroke="#10b981" stroke-width="2" />
                    <text x="0" y="5" fill="#10b981" font-size="20" font-weight="900" text-anchor="middle">4</text>
                    <text x="0" y="65" fill="#f3f4f6" font-size="14" font-weight="bold" text-anchor="middle">Auszahlung</text>
                    <text x="0" y="85" fill="#6b7280" font-size="11" text-anchor="middle">Manuelle Rückerstattung</text>
                    <text x="0" y="100" fill="#6b7280" font-size="11" text-anchor="middle">im Zahlungsanbieter.</text>
                </g>
            </svg>
        </div>

        <div class="mt-6 bg-blue-500/10 border border-blue-500/20 rounded-2xl p-5 flex gap-4 items-start shadow-inner">
            <x-heroicon-s-information-circle class="w-6 h-6 text-blue-400 shrink-0" />
            <div class="text-xs text-blue-100/90 leading-relaxed">
                <p><strong>Hinweis:</strong> Dieses System generiert "nur" den offiziellen Beleg für das Finanzamt und den Kunden. Das Geld selbst erstattest du anschließend manuell über Stripe, PayPal oder Banküberweisung auf das Ursprungskonto zurück.</p>
            </div>
        </div>
    </div>

    {{-- CREATE MODAL (MANUELLE GUTSCHRIFT) --}}
    @if($showCreateModal)
    <div class="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 pt-16 sm:pt-24" x-data="{ open: false }" x-init="setTimeout(() => open = true, 50)">
        <div class="absolute inset-0 bg-gray-950/80 backdrop-blur-sm transition-opacity duration-300 opacity-0" :class="{'opacity-100': open}" wire:click="closeCreateModal"></div>

        <div class="relative w-full max-w-xl bg-gray-900 border border-gray-800 rounded-2xl max-h-[95vh] flex flex-col shadow-2xl transform transition-all duration-300 opacity-0 scale-95" :class="{'opacity-100 scale-100': open}">

            <div class="flex items-center justify-between p-6 border-b border-gray-800 bg-gray-900/50 backdrop-blur-xl shrink-0">
                <h2 class="text-2xl font-serif font-bold text-white flex items-center gap-3">
                    <x-heroicon-o-document-plus class="w-7 h-7 text-primary" />
                    Neue Gutschrift
                </h2>
                <button wire:click="closeCreateModal" class="p-2 text-gray-500 hover:text-white bg-gray-800 hover:bg-gray-700 rounded-xl transition-colors">
                    <x-heroicon-m-x-mark class="w-6 h-6" />
                </button>
            </div>

            <div class="p-6 overflow-y-auto space-y-8 custom-scrollbar">

                {{-- KUNDEN SUCHE --}}
                <div class="space-y-3 relative z-20">
                    <label class="text-[10px] font-black tracking-[0.2em] text-gray-500 uppercase">Kunde zuweisen</label>
                    <div class="relative">
                        <div class="flex items-center bg-gray-950 border border-gray-800 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-primary/30 focus-within:border-primary transition-all">
                            <div class="pl-4 pr-2">
                                <x-heroicon-o-user class="w-5 h-5 text-gray-500" />
                            </div>
                            <input wire:model.live.debounce.300ms="searchCustomer" type="text" placeholder="Vorname, Nachname oder Email eingeben..." class="w-full bg-transparent border-none text-white font-bold text-sm py-3.5 focus:ring-0">
                            @if($newCredit['customer_id'])
                                <div class="pr-2">
                                    <button wire:click="$set('newCredit.customer_id', '')" class="text-primary hover:text-primary-light">
                                        <x-heroicon-s-check-circle class="w-6 h-6" />
                                    </button>
                                </div>
                            @endif
                        </div>

                        @if(!$newCredit['customer_id'] && strlen($searchCustomer) >= 2)
                            <div class="absolute mt-2 w-full bg-gray-800 border border-gray-700 rounded-xl shadow-xl overflow-hidden z-50">
                                @forelse($customers as $c)
                                    <button wire:click="setCustomer('{{ $c->id }}', '{{ $c->first_name }} {{ $c->last_name }}')" class="w-full text-left px-4 py-3 hover:bg-gray-700 border-b border-gray-700/50 last:border-0 flex justify-between items-center group">
                                        <div>
                                            <div class="font-bold text-white group-hover:text-primary transition-colors">{{ $c->first_name }} {{ $c->last_name }}</div>
                                            <div class="text-xs text-gray-400">{{ $c->email }}</div>
                                        </div>
                                        <x-heroicon-m-plus class="w-5 h-5 text-gray-500 group-hover:text-primary" />
                                    </button>
                                @empty
                                    <div class="px-4 py-3 text-sm text-gray-400 italic">Kein Kunde gefunden.</div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>

                {{-- BASISDATEN --}}
                <div class="space-y-6 bg-gray-950/50 p-6 rounded-2xl border border-gray-800/50">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <label class="text-[10px] font-black tracking-[0.2em] text-gray-500 uppercase">Betreff / Dokumententitel</label>
                            <div x-data="{ tooltip: false }" class="relative">
                                <x-heroicon-s-question-mark-circle @mouseenter="tooltip = true" @mouseleave="tooltip = false" class="w-4 h-4 text-gray-600 hover:text-primary cursor-help" />
                                <div x-show="tooltip" class="absolute left-6 top-1/2 -translate-y-1/2 w-64 bg-gray-800 border border-gray-700 text-gray-300 text-xs p-3 rounded-lg shadow-xl z-50">Dieser Titel ist für den Kunden auf dem PDF sichtbar. Du kannst auch die alte Rechnungsnummer im Betreff erwähnen (z.B. "Gutschrift zu R-1234").</div>
                            </div>
                        </div>
                        <input wire:model="newCredit.subject" type="text" class="w-full bg-gray-900 border border-gray-800 text-white font-bold text-sm rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all">
                        @error('newCredit.subject') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-[10px] font-black tracking-[0.2em] text-gray-500 uppercase mb-2 block">Einleitungstext</label>
                        <textarea wire:model="newCredit.header_text" rows="2" class="w-full bg-gray-900 border border-gray-800 text-gray-300 text-sm rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all"></textarea>
                    </div>
                </div>

                {{-- POSITIONEN --}}
                <div class="space-y-4 relative z-10">
                    <div class="flex justify-between items-center">
                        <label class="text-[10px] font-black tracking-[0.2em] text-gray-500 uppercase">Positionen</label>
                        <button wire:click="addCreditItem" class="text-xs font-bold text-primary hover:text-white flex items-center gap-1 transition-colors">
                            <x-heroicon-s-plus-circle class="w-4 h-4" /> Position hinzufügen
                        </button>
                    </div>

                    <div class="space-y-3 max-h-[35vh] overflow-y-auto custom-scrollbar pr-3">
                        @foreach($creditItems as $index => $item)
                            <div class="bg-gray-950 border border-gray-800 rounded-xl p-4 flex gap-4 items-start relative group shadow-inner">
                                <div class="flex-1 space-y-4">
                                    <div>
                                        <input wire:model="creditItems.{{$index}}.name" type="text" placeholder="Bezeichnung (z.B. Kulanzrabatt)" class="w-full bg-gray-900 border border-gray-800 text-white font-bold text-sm rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/30 outline-none">
                                        @error("creditItems.{$index}.name") <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <div class="text-[9px] text-gray-500 uppercase tracking-widest mb-1.5 font-bold">Menge</div>
                                            <input wire:model="creditItems.{{$index}}.quantity" type="number" step="0.01" class="w-full bg-gray-900 border border-gray-800 text-white text-sm rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/30 outline-none text-center">
                                            @error("creditItems.{$index}.quantity") <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5 mb-1.5">
                                                <div class="text-[9px] text-gray-500 uppercase tracking-widest font-bold">Einzelbrutto (€)</div>
                                                <div x-data="{ tooltip: false }" class="relative">
                                                    <x-heroicon-s-information-circle @mouseenter="tooltip = true" @mouseleave="tooltip = false" class="w-3.5 h-3.5 text-gray-600 hover:text-primary cursor-help" />
                                                    <div x-show="tooltip" class="absolute bottom-6 left-1/2 -translate-x-1/2 w-56 text-center bg-gray-800 border border-gray-700 text-gray-300 text-[10px] p-2 rounded-lg shadow-xl z-50">Gib den Betrag <strong>positiv</strong> ein (z.B. 25). Das System weiß automatisch, dass dies eine Gutschrift ist und zieht den Wert in der Statistik für dich ab.</div>
                                                </div>
                                            </div>
                                            <input wire:model="creditItems.{{$index}}.unit_price" type="number" step="0.01" placeholder="z.B. 15.50" class="w-full bg-gray-900 border border-gray-800 text-rose-400 font-mono font-bold text-sm rounded-lg px-3 py-2 focus:ring-2 focus:ring-rose-500/30 outline-none text-right">
                                            @error("creditItems.{$index}.unit_price") <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <div class="text-[9px] text-gray-500 uppercase tracking-widest mb-1.5 font-bold">MwSt %</div>
                                            <select wire:model="creditItems.{{$index}}.tax_rate" class="w-full bg-gray-900 border border-gray-800 text-white text-sm rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/30 outline-none appearance-none cursor-pointer">
                                                <option value="19">19%</option>
                                                <option value="7">7%</option>
                                                <option value="0">0%</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                @if(count($creditItems) > 1)
                                    <button wire:click="removeCreditItem({{ $index }})" class="p-1.5 text-gray-600 hover:text-red-500 hover:bg-red-500/10 rounded-lg transition-colors mt-1" title="Position löschen">
                                        <x-heroicon-m-trash class="w-5 h-5"/>
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>

            <div class="p-6 border-t border-gray-800 bg-gray-900/80 backdrop-blur-md shrink-0 flex justify-between items-center gap-4">
                <button wire:click="closeCreateModal" class="px-6 py-3 text-sm font-bold text-gray-400 hover:text-white transition-colors">Abbrechen</button>
                <button wire:click="generateCreditNote" class="bg-primary hover:bg-primary-dark text-white shadow-[0_0_20px_rgba(197,160,89,0.2)] hover:shadow-[0_0_30px_rgba(197,160,89,0.4)] px-8 py-3 rounded-xl font-bold flex items-center transition-all hover:-translate-y-0.5" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="generateCreditNote" class="flex items-center gap-2">
                        <x-heroicon-o-check class="w-5 h-5 shrink-0" /> Gutschrift erzeugen
                    </span>
                    <span wire:loading wire:target="generateCreditNote" class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Erzeuge PDF...
                    </span>
                </button>
            </div>

        </div>
    </div>
    @endif
</div>

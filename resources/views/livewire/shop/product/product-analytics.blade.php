<div class="space-y-6" x-data="{ tab: @entangle('activeTab') }">

    <!-- Header Navigation Tabs -->
    <div class="bg-gray-900/80 backdrop-blur-xl rounded-2xl p-2 border border-gray-800 shadow-inner flex overflow-x-auto no-scrollbar gap-2 snap-x snap-mandatory">
        <button @click="tab = 'true-costs'" :class="tab === 'true-costs' ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'bg-gray-950 text-gray-400 border border-gray-800 hover:text-white hover:bg-gray-800 hover:border-gray-700'" class="px-6 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all whitespace-nowrap snap-start flex items-center gap-2">
            <i class="bi bi-currency-dollar text-sm"></i> Wahre Zahlen
        </button>
        <button @click="tab = 'forecast'" :class="tab === 'forecast' ? 'bg-blue-500 text-gray-900 shadow-[0_0_15px_rgba(59,130,246,0.3)]' : 'bg-gray-950 text-gray-400 border border-gray-800 hover:text-white hover:bg-gray-800 hover:border-gray-700'" class="px-6 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all whitespace-nowrap snap-start flex items-center gap-2">
            <i class="bi bi-graph-up text-sm"></i> Prognose & Bestand
        </button>
        <button @click="tab = 'losses'" :class="tab === 'losses' ? 'bg-red-500 text-gray-900 shadow-[0_0_15px_rgba(239,68,68,0.3)]' : 'bg-gray-950 text-gray-400 border border-gray-800 hover:text-white hover:bg-gray-800 hover:border-gray-700'" class="px-6 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all whitespace-nowrap snap-start flex items-center gap-2">
            <i class="bi bi-trash3 text-sm"></i> Schwund & Bruch
        </button>
        <button @click="tab = 'lucid'" :class="tab === 'lucid' ? 'bg-emerald-500 text-gray-900 shadow-[0_0_15px_rgba(16,185,129,0.3)]' : 'bg-gray-950 text-gray-400 border border-gray-800 hover:text-white hover:bg-gray-800 hover:border-gray-700'" class="px-6 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all whitespace-nowrap snap-start flex items-center gap-2">
            <i class="bi bi-box-seam text-sm"></i> LUCID Report
        </button>
    </div>

    <!-- TAB 1: TRUE COSTS -->
    <div x-show="tab === 'true-costs'" x-transition.opacity class="space-y-6">
        <div class="bg-gradient-to-br from-gray-900 via-gray-950 to-black rounded-[2rem] p-6 lg:p-8 border border-primary/20 shadow-2xl relative overflow-hidden">
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-primary/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="mb-8">
                <h3 class="text-xl font-serif font-bold text-white mb-2 flex items-center gap-3"><i class="solar-wallet-money-bold-duotone text-primary text-2xl"></i> Stückkosten-Analyse (Reingewinn)</h3>
                <p class="text-[11px] font-medium text-gray-400 max-w-2xl leading-relaxed">
                    Der Verkaufspreis nützt nichts, wenn ihn die Maschine frisst. Hier siehst du die <strong>echte Netto-Marge</strong> pro Artikel unter Berücksichtigung von Einkaufspreisen, Maschinenlaufzeit, Stromverschleiß, Verpackung und Frachtkosten.
                </p>
            </div>

            <div class="overflow-x-auto border border-gray-800 rounded-[1.5rem] bg-gray-950/50 shadow-inner">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-900/80 border-b border-gray-800">
                            <th class="p-4 text-[9px] font-black uppercase tracking-widest text-gray-500 w-1/3">Produkt</th>
                            <th class="p-4 text-[9px] font-black uppercase tracking-widest text-gray-500">EK Preis</th>
                            <th class="p-4 text-[9px] font-black uppercase tracking-widest text-gray-500">Produktion (Strom & Verschleiß)</th>
                            <th class="p-4 text-[9px] font-black uppercase tracking-widest text-gray-500">Verpackung & Versand</th>
                            <th class="p-4 text-[9px] font-black uppercase tracking-widest text-gray-500 text-right">Netto EK & Marge</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60">
                        @foreach($trueCostData as $tc)
                            <tr class="hover:bg-gray-900/30 transition-colors">
                                <td class="p-4">
                                    <div class="font-bold text-sm text-gray-200 line-clamp-1 mb-1">{{ $tc['name'] }}</div>
                                    <div class="text-[9px] text-gray-500 uppercase tracking-wider font-black">Netto-Verkauf: <span class="text-white">{{ number_format($tc['net_price'], 2, ',', '.') }} €</span></div>
                                </td>
                                <td class="p-4 align-middle">
                                    <span class="text-xs font-black text-gray-300">{{ number_format($tc['purchase_price'], 2, ',', '.') }} €</span>
                                </td>
                                <td class="p-4 align-middle">
                                    <span class="text-xs font-black text-amber-500">{{ number_format($tc['laser_cost'], 2, ',', '.') }} €</span>
                                </td>
                                <td class="p-4 align-middle">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-black text-gray-400 bg-gray-800 px-2 py-0.5 rounded-full" title="Verpackung">{{ number_format($tc['packaging_cost'], 2, ',', '.') }} €</span>
                                        <span class="text-gray-600">+</span>
                                        <span class="text-[10px] font-black text-gray-400 bg-gray-800 px-2 py-0.5 rounded-full" title="Versand">{{ number_format($tc['shipping_cost'], 2, ',', '.') }} €</span>
                                    </div>
                                </td>
                                <td class="p-4 align-middle text-right">
                                    <div class="text-sm font-black text-white block mb-0.5">{{ number_format($tc['net_margin'], 2, ',', '.') }} €</div>
                                    @php
                                        $mClass = $tc['margin_percent'] >= 50 ? 'text-emerald-400' : ($tc['margin_percent'] >= 20 ? 'text-amber-400' : 'text-red-400');
                                    @endphp
                                    <div class="text-[10px] font-black uppercase tracking-widest {{ $mClass }}">{{ $tc['margin_percent'] }}% Marge</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if(count($trueCostData) === 0)
                    <div class="p-8 text-center text-gray-500 text-sm font-medium">Noch keine physischen Produkte für die Kostenrechnung vorbereitet. Aktuell in Bearbeitung.</div>
                @endif
            </div>
            
            <div class="mt-6 bg-primary/5 border border-primary/20 rounded-xl p-4 flex gap-4 items-start">
                <i class="bi bi-lightbulb text-primary text-xl"></i>
                <div>
                    <h4 class="text-xs font-black text-white uppercase tracking-widest mb-1">Tipp für maximale Rentabilität</h4>
                    <p class="text-[10px] text-gray-400 leading-relaxed">Passe im Produkt-Editor die "Laserlaufzeit in Min." und "Stromfaktor pro Min." an, wenn du neue Materialstärken nutzt. Ein 6mm Holz benötigt ca. 40% mehr Laufzeit als das 3mm Standard-Holz, was den wahren Gewinn dramatisch senken kann.</p>
                </div>
            </div>

            <div class="mt-8 flex justify-center">
                <a href="{{ route('admin.product-analytics.export.full') }}" class="inline-flex items-center gap-2 bg-gray-900 border border-gray-700 hover:bg-white hover:text-black text-gray-300 px-8 py-3.5 rounded-full text-[11px] font-black uppercase tracking-widest transition-all shadow-xl group">
                    <i class="bi bi-file-earmark-pdf-fill text-red-500 group-hover:text-red-600 text-lg transition-colors"></i> 
                    Produkt Analyse Bericht
                </a>
            </div>
        </div>
    </div>

    <!-- TAB 2: FORECASTING -->
    <div x-show="tab === 'forecast'" x-transition.opacity x-cloak class="space-y-6">
        <div class="bg-gradient-to-br from-gray-900 via-gray-950 to-black rounded-[2rem] p-6 lg:p-8 border border-blue-500/20 shadow-2xl relative overflow-hidden">
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="mb-8">
                <h3 class="text-xl font-serif font-bold text-white mb-2 flex items-center gap-3"><i class="solar-chart-2-bold-duotone text-blue-500 text-2xl"></i> Bestands-Prognose</h3>
                <p class="text-[11px] font-medium text-gray-400 max-w-2xl leading-relaxed">
                    Das System trackt die reale "Ø Verkaufsgeschwindigkeit" der letzten 30 Tage pro Artikel und gleicht sie mit der Lieferzeit beim Hersteller ab. So bestellst du nie wieder zu spät.
                </p>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                @foreach($forecastingData as $fc)
                    @php
                        $bgCard = $fc['status'] === 'out_of_stock' ? 'bg-red-500/5 border-red-500/30' : 
                                ($fc['status'] === 'critical' ? 'bg-amber-500/5 border-amber-500/30' : 
                                'bg-gray-950/50 border-gray-800');
                        
                        $iconColor = $fc['status'] === 'out_of_stock' ? 'text-red-400' : 
                                  ($fc['status'] === 'critical' ? 'text-amber-400' : 'text-emerald-400');
                    @endphp
                    <div class="border rounded-2xl p-5 {{ $bgCard }} flex flex-col justify-between shadow-inner">
                        <div class="flex justify-between items-start mb-6">
                            <div class="flex-1 pr-4">
                                <h4 class="font-bold text-gray-200 text-sm mb-1 truncate">{{ $fc['name'] }}</h4>
                                <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">{{ $fc['sold_last_30'] }} Sales in 30 Tagen <span class="text-blue-400 bg-blue-500/10 px-1.5 py-0.5 rounded-md ml-1">ø {{ $fc['velocity'] }} / Tag</span></div>
                            </div>
                            <div class="shrink-0 text-right">
                                <span class="block text-2xl font-black {{ $iconColor }}">{{ $fc['stock'] }}</span>
                                <span class="text-[8px] font-black uppercase tracking-widest text-gray-500">Auf Lager</span>
                            </div>
                        </div>

                        <div class="w-full bg-gray-900 rounded-full h-1.5 mb-2 overflow-hidden border border-gray-800">
                            @php
                                $fillPercent = $fc['stock'] > 0 ? min(100, ($fc['stock'] / max(1, $fc['sold_last_30'])) * 100) : 0;
                                $fillColor = $fc['status'] === 'ok' ? 'bg-emerald-500' : ($fc['status'] === 'warning' ? 'bg-amber-500' : 'bg-red-500');
                            @endphp
                            <div class="{{ $fillColor }} h-1.5 rounded-full" style="width: {{ $fillPercent }}%"></div>
                        </div>

                        <div class="flex justify-between items-center text-[10px] font-bold">
                            @if($fc['stock'] <= 0)
                                <span class="text-red-400 uppercase tracking-widest animate-pulse"><i class="bi bi-exclamation-triangle-fill mr-1"></i> Ausverkauft</span>
                            @elseif($fc['status'] === 'critical' || $fc['status'] === 'warning')
                                <span class="text-amber-400"><i class="bi bi-clock-history mr-1"></i> Reichweite: ~{{ $fc['reach_days'] }} Tage</span>
                            @else
                                <span class="text-emerald-500"><i class="bi bi-check-circle mr-1"></i> Reichweite: ~{{ $fc['reach_days'] }} Tage</span>
                            @endif
                            
                            <span class="text-gray-500 tracking-widest uppercase">Lieferzeit: {{ $fc['delivery_days'] }} Tage</span>
                        </div>
                    </div>
                @endforeach
                @if(count($forecastingData) === 0)
                    <div class="col-span-full p-8 text-center text-gray-500 text-sm font-medium border border-dashed border-gray-800 rounded-2xl">Lagerverwaltung deaktiviert oder keine physischen Produkte aktiv.</div>
                @endif
            </div>

            <div class="mt-8 pt-6 border-t border-gray-800">
                <a href="/admin/products" class="inline-block bg-blue-500/10 border border-blue-500/30 text-blue-400 hover:bg-blue-500 hover:text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                    Zum Produktkatalog & Nachbestellen
                </a>
            </div>
        </div>
    </div>

    <!-- TAB 3: LOSSES -->
    <div x-show="tab === 'losses'" x-transition.opacity x-cloak class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Side: Stats & Form Trigger -->
            <div class="col-span-1 flex flex-col gap-6">
                <div class="bg-gradient-to-br from-gray-900 via-gray-950 to-black rounded-[2rem] p-6 lg:p-8 border border-red-500/20 shadow-2xl relative overflow-hidden">
                    <div class="absolute -top-20 -right-20 w-48 h-48 bg-red-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="mb-6">
                        <h3 class="text-xl font-serif font-bold text-white mb-2 flex items-center gap-3"><i class="solar-bomb-minimalistic-bold-duotone text-red-500 text-2xl"></i> Bruch & Schwund</h3>
                        <p class="text-[10px] font-medium text-gray-400 max-w-full leading-relaxed">
                            Holz splittert, Laser dekalibrieren. Buche Schwund korrekt aus, um den Bestand präzise zu halten und den finanziellen Verlust als Betriebskosten abzuschreiben.
                        </p>
                    </div>

                    <div class="space-y-3 mb-8">
                        <div class="bg-gray-900 p-4 rounded-xl border border-gray-800 shadow-inner flex justify-between items-center">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Verlust aktueller Monat</span>
                            <span class="text-lg font-black text-red-400">{{ number_format($lossesData['this_month'], 2, ',', '.') }} €</span>
                        </div>
                        <div class="bg-gray-900 p-4 rounded-xl border border-gray-800 shadow-inner flex justify-between items-center">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Verlust All-Time</span>
                            <span class="text-lg font-black text-white">{{ number_format($lossesData['total'], 2, ',', '.') }} €</span>
                        </div>
                    </div>

                    <button @click="$wire.openLossModal(null)" class="w-full bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500 hover:text-white px-5 py-3 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all shadow-glow flex justify-center items-center gap-2">
                        <i class="bi bi-plus-circle"></i> Bruch erfassen
                    </button>
                    
                    <div class="mt-6 bg-red-500/5 border border-red-500/10 rounded-xl p-4 text-[9px] text-gray-400 font-medium">
                        <strong>Hinweis für Steuer:</strong> Die Beträge basieren auf dem hinterlegten Einkaufspreis. Reiner Materialverlust mindert den Rohertrag und wird nicht doppelt besteuert.
                    </div>
                </div>
            </div>

            <!-- Right Side: Recent Losses Log -->
            <div class="col-span-1 lg:col-span-2">
                <div class="bg-gray-900/40 rounded-[2rem] border border-gray-800 shadow-inner h-full flex flex-col p-6">
                    <h4 class="text-xs font-black uppercase tracking-widest text-gray-500 mb-6 border-b border-gray-800/60 pb-4">Letzte Bruch-Logbucheinträge</h4>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-800/60 text-[9px] font-black uppercase tracking-widest text-gray-600">
                                    <th class="pb-3 pr-4">Datum</th>
                                    <th class="pb-3 px-4">Produkt</th>
                                    <th class="pb-3 px-4 text-center">Defekte Menge</th>
                                    <th class="pb-3 px-4">Ursache</th>
                                    <th class="pb-3 px-4 text-right">Verlustsumme</th>
                                    <th class="pb-3 pl-4 text-center">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/40">
                                @forelse($lossesData['recent'] as $loss)
                                    <tr class="hover:bg-gray-800/20 transition-colors">
                                        <td class="py-3 pr-4 text-[10px] text-gray-400 whitespace-nowrap">{{ $loss->created_at->format('d.m.Y H:i') }}</td>
                                        <td class="py-3 px-4 text-xs font-bold text-gray-300">{{ $loss->product->name ?? 'Gelöschtes Produkt' }}</td>
                                        
                                        @if($editLossId === $loss->id)
                                            <td class="py-2 px-4 text-center">
                                                <input type="number" wire:model="editLossQuantity" class="w-16 bg-gray-900 border border-gray-700 rounded-lg px-2 py-1 text-xs font-bold text-white focus:border-red-500 focus:ring-1 focus:ring-red-500" min="1">
                                            </td>
                                            <td class="py-2 px-4 text-[10px]">
                                                <input type="text" wire:model="editLossReason" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 py-1 text-xs text-white focus:border-red-500 focus:ring-1 focus:ring-red-500">
                                            </td>
                                            <td class="py-2 px-4 text-right">
                                                <span class="text-[10px] text-gray-500 uppercase tracking-widest font-black">--</span>
                                            </td>
                                            <td class="py-2 pl-4 text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <button wire:click="updateLoss" class="text-emerald-400 hover:text-emerald-300 bg-emerald-400/10 p-1.5 rounded-lg transition-colors flex items-center justify-center w-7 h-7">
                                                        <x-heroicon-o-check class="w-4 h-4" />
                                                    </button>
                                                    <button wire:click="cancelEditLoss" class="text-gray-500 hover:text-gray-400 bg-gray-800 p-1.5 rounded-lg transition-colors flex items-center justify-center w-7 h-7">
                                                        <x-heroicon-o-x-mark class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        @else
                                            <td class="py-3 px-4 text-center">
                                                <span class="bg-gray-800 text-gray-400 px-2 py-0.5 rounded text-[10px] font-black">{{ $loss->quantity }}x</span>
                                            </td>
                                            <td class="py-3 px-4 text-[10px] text-gray-400 italic max-w-[150px] truncate" title="{{ $loss->reason }}">{{ $loss->reason }}</td>
                                            <td class="py-3 px-4 text-right">
                                                <span class="text-xs font-black text-red-500">- {{ number_format($loss->cost_value / 100, 2, ',', '.') }} €</span>
                                            </td>
                                            <td class="py-3 pl-4 text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <button wire:click="startEditLoss({{ $loss->id }})" class="text-gray-400 hover:text-blue-400 p-1.5 rounded-lg transition-colors bg-gray-800 border border-gray-700 shadow-inner flex items-center justify-center w-7 h-7" title="Bearbeiten">
                                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                                    </button>
                                                    <button wire:click="deleteLoss({{ $loss->id }})" wire:confirm="Sicher? Der Lagerbestand wird wieder automatisch erhöht." class="text-gray-400 hover:text-red-500 p-1.5 rounded-lg transition-colors bg-gray-800 border border-gray-700 shadow-inner flex items-center justify-center w-7 h-7" title="Eintrag stornieren">
                                                        <x-heroicon-o-trash class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-gray-500 text-xs font-medium border-t border-gray-800/40">Noch keine Schwund-Einträge vorhanden. Saubere Arbeit!</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Loss Modal inline via Alpine/Livewire --}}
        <div x-show="$wire.lossModalOpen" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
            <div @click.away="$wire.set('lossModalOpen', false)" class="bg-gray-900 border border-gray-800 rounded-3xl p-6 lg:p-8 shadow-2xl w-full max-w-lg relative">
                <button @click="$wire.set('lossModalOpen', false)" class="absolute top-4 right-4 text-gray-500 hover:text-white"><i class="bi bi-x-lg text-lg"></i></button>
                <h3 class="text-xl font-serif font-bold text-white mb-6 border-b border-gray-800 pb-4">Bruch & Schwund buchen</h3>
                
                <form wire:submit.prevent="recordLoss" class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 pl-1">Welches Produkt ist beschädigt?</label>
                        <select wire:model="lossProductId" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3 text-sm font-bold text-white focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors shadow-inner" required>
                            <option value="">-- Produkt auswählen --</option>
                            @foreach(\App\Models\Product\Product::where('status', 'active')->where('type', 'physical')->orderBy('name')->get() as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} (Lager: {{ $p->quantity }})</option>
                            @endforeach
                        </select>
                        @error('lossProductId') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 pl-1">Defekte Menge</label>
                        <input type="number" wire:model="lossQuantity" min="1" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3 text-sm font-bold text-white focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors shadow-inner" required>
                        @error('lossQuantity') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 pl-1">Ursache / Grund</label>
                        <textarea wire:model="lossReason" rows="3" placeholder="Z.B. Holz beim Laserschnitt gesplittert, Maschine verstellt..." class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3 text-sm font-medium text-white focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors shadow-inner" required></textarea>
                        @error('lossReason') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-gray-800 flex justify-end gap-3">
                        <button type="button" @click="$wire.set('lossModalOpen', false)" class="px-5 py-2.5 rounded-xl border border-gray-700 bg-gray-800 text-gray-300 hover:text-white text-[10px] font-black uppercase tracking-widest transition-colors">Abbrechen</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-500 text-white shadow-glow border border-red-400/30 text-[10px] font-black uppercase tracking-widest transition-colors">Verlust buchen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB 4: LUCID -->
    <div x-show="tab === 'lucid'" x-transition.opacity x-cloak class="space-y-6">
        <div class="bg-gradient-to-br from-gray-900 via-gray-950 to-black rounded-[2rem] p-6 lg:p-8 border border-emerald-500/20 shadow-2xl relative overflow-hidden">
            <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="mb-8">
                <h3 class="text-xl font-serif font-bold text-white mb-2 flex items-center gap-3"><i class="solar-leaf-bold-duotone text-emerald-500 text-2xl"></i> LUCID Verpackungs-Report {{ $lucidData['year'] }}</h3>
                <p class="text-[11px] font-medium text-gray-400 max-w-2xl leading-relaxed">
                    Basierend auf allen nicht stornierten Bestellungen dieses Kalenderjahres, multipliziert mit den im Produkt hinterlegten theoretischen Verpackungsgewichten. Die Daten hieraus kannst du zum Jahresende einfach in das Verpackungsregister übertragen.
                </p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                @php
                    $materialIcons = [
                        'paper' => ['icon' => 'solar-box-bold-duotone', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500', 'label' => 'PPK (Papier/Pappe)'],
                        'plastic' => ['icon' => 'solar-bag-bold-duotone', 'color' => 'text-blue-400', 'bg' => 'bg-blue-500', 'label' => 'Kunststoffe'],
                        'glass' => ['icon' => 'solar-wine-bold-duotone', 'color' => 'text-teal-400', 'bg' => 'bg-teal-500', 'label' => 'Glas'],
                        'wood' => ['icon' => 'solar-tree-bold-duotone', 'color' => 'text-amber-600', 'bg' => 'bg-amber-600', 'label' => 'Holz'],
                        'tin' => ['icon' => 'solar-server-bold-duotone', 'color' => 'text-gray-400', 'bg' => 'bg-gray-500', 'label' => 'Weißblech'],
                        'alu' => ['icon' => 'solar-layers-bold-duotone', 'color' => 'text-gray-300', 'bg' => 'bg-gray-400', 'label' => 'Aluminium'],
                        'composite' => ['icon' => 'solar-box-minimalistic-bold-duotone', 'color' => 'text-purple-400', 'bg' => 'bg-purple-500', 'label' => 'Verbund'],
                        'other' => ['icon' => 'solar-leaf-bold-duotone', 'color' => 'text-lime-400', 'bg' => 'bg-lime-500', 'label' => 'Natur / Sonstige'],
                    ];
                @endphp

                @foreach($lucidData['totals_kg'] as $key => $weight)
                    @if($weight > 0)
                        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 shadow-inner flex flex-col gap-3 relative overflow-hidden group hover:border-{{ explode('-', $materialIcons[$key]['color'])[1] }}-500/40 transition-colors">
                            <div class="absolute inset-0 {{ $materialIcons[$key]['bg'] }}/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full {{ $materialIcons[$key]['bg'] }}/10 border border-{{ explode('-', $materialIcons[$key]['color'])[1] }}-500/30 flex items-center justify-center shrink-0">
                                    <i class="{{ $materialIcons[$key]['icon'] }} {{ $materialIcons[$key]['color'] }} text-xl"></i>
                                </div>
                                <div class="text-[9px] font-black uppercase tracking-widest text-gray-500 leading-tight">{{ $materialIcons[$key]['label'] }}</div>
                            </div>
                            <div>
                                <div class="text-xl font-black text-white">{{ number_format($weight, 3, ',', '.') }} kg</div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="bg-gray-900/40 border border-gray-800 rounded-2xl p-6 shadow-inner">
                <h4 class="text-xs font-black uppercase tracking-widest text-gray-500 mb-6 border-b border-gray-800/60 pb-4">Detaillierte Zusammensetzung pro Produkt</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-800/60 text-[9px] font-black uppercase tracking-widest text-gray-600">
                                <th class="pb-3 pr-4">Produkt</th>
                                <th class="pb-3 px-4 text-center">Verkaufte Einheit</th>
                                @foreach($materialIcons as $key => $iconData)
                                    @if($lucidData['totals_kg'][$key] > 0)
                                        <th class="pb-3 px-2 text-right {{ $iconData['color'] }}/70">{{ $iconData['label'] }}</th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/40">
                            @forelse($lucidData['details'] as $item)
                                <tr class="hover:bg-gray-800/30 transition-colors">
                                    <td class="py-3 pr-4 text-xs font-bold text-gray-300">{{ $item['name'] }}</td>
                                    <td class="py-3 px-4 text-center text-[10px] text-gray-400 font-bold bg-gray-900 rounded-lg">{{ $item['sold'] }} x</td>
                                    @foreach($materialIcons as $key => $iconData)
                                        @if($lucidData['totals_kg'][$key] > 0)
                                            <td class="py-3 px-2 text-right text-xs font-black {{ $iconData['color'] }}">
                                                {{ $item[$key . '_kg'] > 0 ? number_format($item[$key . '_kg'], 3, ',', '.') . ' kg' : '-' }}
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-8 text-center text-gray-500 text-xs font-medium border-t border-gray-800/40">Es liegen noch keine abgewickelten Verkäufe mit verknüpften Verpackungsmaterialien für das aktuelle Jahr vor.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="mt-8 flex justify-end">
                <a href="{{ route('admin.product-analytics.export.lucid') }}" class="inline-flex items-center gap-2 bg-gray-800 hover:bg-white text-gray-400 hover:text-black border border-gray-700 px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                    <i class="bi bi-file-earmark-pdf-fill text-red-500 hidden hover:inline-block"></i> LUCID Report Drucken
                </a>
            </div>
        </div>
    </div>

</div>

<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="space-y-6">
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-serif font-bold text-white flex items-center gap-3"><i class="solar-bomb-minimalistic-bold-duotone text-red-500 text-2xl"></i> Schwund & Bruch</h1>
            <p class="text-[11px] font-medium text-gray-400 mt-1 max-w-2xl leading-relaxed">Erfassen und Verwalten von Defekten, beschädigter Ware oder Fehlproduktionen, inklusive Händler-Reklamation und Rückerstattungs-Tracking.</p>
        </div>
        <button wire:click="openLossModal" class="px-5 py-2.5 bg-red-600/10 hover:bg-red-500 border border-red-500/30 text-red-400 hover:text-white text-[11px] font-black uppercase tracking-widest rounded-xl transition-all shadow-glow flex items-center gap-2">
            <i class="bi bi-plus-circle"></i> Schaden melden
        </button>
    </div>

    <!-- METRICS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-inner flex flex-col justify-between relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-24 h-24 bg-red-500/5 rounded-full blur-2xl pointer-events-none"></div>
            <div class="flex items-start gap-3 mb-2">
                <div class="w-8 h-8 rounded-full bg-red-500/10 flex items-center justify-center shrink-0 border border-red-500/20">
                    <i class="solar-exclamation-circle-bold-duotone text-red-400 text-lg"></i>
                </div>
                <div>
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">Offene Meldungen</div>
                    <div class="text-xl font-black text-white">{{ $metrics['total_open'] }}</div>
                </div>
            </div>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-inner flex flex-col justify-between relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-24 h-24 bg-emerald-500/5 rounded-full blur-2xl pointer-events-none"></div>
            <div class="flex items-start gap-3 mb-2">
                <div class="w-8 h-8 rounded-full bg-emerald-500/10 flex items-center justify-center shrink-0 border border-emerald-500/20">
                    <i class="solar-wallet-money-bold-duotone text-emerald-400 text-lg"></i>
                </div>
                <div>
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">Erstattet (Monat)</div>
                    <div class="text-xl font-black text-emerald-400">{{ number_format($metrics['total_refunded_this_month'], 2, ',', '.') }} €</div>
                </div>
            </div>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-inner flex flex-col justify-between relative overflow-hidden">
            <div class="flex items-start gap-3 mb-2">
                <div class="w-8 h-8 rounded-full bg-red-500/10 flex items-center justify-center shrink-0 border border-red-500/20">
                    <i class="solar-chart-square-bold-duotone text-red-400 text-lg"></i>
                </div>
                <div>
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">Neu-Verlust (Monat)</div>
                    <div class="text-xl font-black text-red-400">{{ number_format($metrics['total_loss_this_month'], 2, ',', '.') }} €</div>
                </div>
            </div>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-inner flex flex-col justify-between relative overflow-hidden">
            <div class="flex items-start gap-3 mb-2">
                <div class="w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center shrink-0 border border-gray-700">
                    <i class="solar-history-bold-duotone text-gray-400 text-lg"></i>
                </div>
                <div>
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">Schaden All-Time</div>
                    <div class="text-xl font-black text-white">{{ number_format($metrics['total_loss_all_time'], 2, ',', '.') }} €</div>
                </div>
            </div>
        </div>
    </div>

    <!-- LIST VIEW: TREATMENT PLAN STYLE -->
    <div class="mt-8">
        <h2 class="text-xl font-bold mb-4 text-white flex items-center gap-2">
            <i class="solar-clipboard-list-bold-duotone text-red-500"></i>
            Schadensprotokolle
        </h2>

        <div class="grid gap-4">
            @forelse($losses as $loss)
                @php
                    $isAssigned = !is_null($loss->supplier_id);
                    $isReported = !is_null($loss->reported_to_supplier_at);
                    $isRefunded = !is_null($loss->refund_received_at);

                    // Calculation of progress
                    $totalItems = 3;
                    $completedItems = 0;
                    if ($isAssigned) $completedItems++;
                    if ($isReported) $completedItems++;
                    if ($isRefunded) $completedItems++;

                    $progressPercent = round(($completedItems / $totalItems) * 100);

                    // Dynamic Status color for the header
                    $statusLabel = 'Schaden erfassst';
                    $statusColor = 'text-gray-400 border-gray-700 bg-gray-800/50';
                    if ($isRefunded) {
                        $statusLabel = 'Vollständig Erstattet';
                        $statusColor = 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30';
                    } elseif ($isReported) {
                        $statusLabel = 'Wartet auf Händler';
                        $statusColor = 'text-amber-400 bg-amber-500/10 border-amber-500/30';
                    } elseif ($isAssigned) {
                        $statusLabel = 'Klärung ausstehend';
                        $statusColor = 'text-blue-400 bg-blue-500/10 border-blue-500/30';
                    } else {
                        $statusLabel = 'Händler fehlt';
                        $statusColor = 'text-red-400 bg-red-500/10 border-red-500/30';
                    }

                    // Dynamic Progress Bar Color
                    $barTextColor = 'text-red-400';
                    $barBgColor = 'bg-gradient-to-r from-red-600 to-red-400 shadow-[0_0_8px_rgba(239,68,68,0.5)]';
                    if ($progressPercent >= 100) {
                        $barTextColor = 'text-emerald-400';
                        $barBgColor = 'bg-gradient-to-r from-emerald-500 to-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.5)]';
                    } elseif ($progressPercent > 33) {
                        $barTextColor = 'text-amber-400';
                        $barBgColor = 'bg-gradient-to-r from-amber-500 to-amber-400 shadow-[0_0_8px_rgba(245,158,11,0.5)]';
                    }
                @endphp

                <div x-data="{ expanded: false }" class="bg-gray-900 border border-gray-800 rounded-2xl p-5 hover:border-red-500/40 transition-all flex flex-col shadow-inner">
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 w-full cursor-pointer" @click="expanded = !expanded">
                        <div class="pl-2 flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="bi bi-chevron-down text-gray-500 transition-transform" :class="expanded ? 'rotate-180' : ''"></i>
                                <h3 class="font-bold text-gray-100 text-lg line-clamp-1">{{ $loss->product->name ?? 'Gelöschtes Produkt' }}</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded-full uppercase tracking-widest font-bold border {{ $statusColor }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 font-mono ml-7">
                                {{ $loss->created_at->format('d.m.Y H:i') }} |
                                {{ $loss->quantity }} Stück defekt |
                                Verlustwert: <span class="text-red-400 font-bold">-{{ number_format($loss->cost_value / 100, 2, ',', '.') }} €</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-2 pl-7 sm:pl-0 shrink-0">
                            @if(!$isRefunded)
                                <button wire:click.stop="deleteLoss('{{ $loss->id }}')" wire:confirm="Dieses SupportTicket unwiderruflich löschen und Bestand wiederherstellen?" class="btn btn-sm bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-red-500 p-2.5 rounded-lg shadow-lg flex items-center justify-center transition-colors" title="Löschen">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Smart Progress Bar -->
                    <div class="mt-4 mb-2 pl-2 pr-2 w-full">
                        <div class="flex justify-between items-end mb-1">
                            <span class="text-xs font-semibold text-gray-400">Klärungsfortschritt</span>
                            <span class="text-xs font-bold {{ $barTextColor }}">{{ $progressPercent }}%</span>
                        </div>
                        <div class="w-full bg-gray-950 rounded-full h-2 border border-gray-800 overflow-hidden relative shadow-inner">
                            <div class="h-full {{ $barBgColor }} transition-all duration-500" style="width: {{ $progressPercent }}%"></div>
                        </div>
                    </div>

                    <!-- Steps Checklist -->
                    <div x-show="expanded" x-collapse>
                        <div class="mt-4 space-y-3 border-t border-gray-800 pt-5 w-full pl-2 pr-2">
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3 block">Grund: "{{ $loss->reason }}"</span>

                            <!-- STEP 1: Supplier -->
                            <div class="flex items-start gap-3 p-3 rounded-xl transition-colors {{ $isAssigned ? 'opacity-50 bg-gray-950 border border-gray-800/50' : 'bg-gray-900 border border-gray-700 shadow-sm' }}">
                                <div class="mt-1 shrink-0">
                                    @if($isAssigned)
                                        <i class="solar-check-circle-bold text-emerald-500 text-2xl drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]"></i>
                                    @else
                                        <i class="solar-danger-circle-bold-duotone text-red-500 text-2xl"></i>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold {{ $isAssigned ? 'line-through text-gray-500' : 'text-gray-200' }}">Wareneinkauf / Händler zuweisen</p>
                                    @if(!$isAssigned)
                                        <div class="mt-3">
                                            <select wire:change="assignSupplier('{{ $loss->id }}', $event.target.value)" class="w-full bg-gray-950 border border-gray-800 rounded-lg px-3 py-2 text-xs font-bold text-white focus:border-red-500 outline-none">
                                                <option value="">-- Händler auswählen zur Klärung --</option>
                                                @foreach($suppliers as $supplier)
                                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @else
                                        <div class="flex items-center justify-between mt-1">
                                            <div class="text-[10px] text-gray-500 font-mono">Verknüpft mit Händler: <span class="text-blue-400">{{ $loss->supplier->name ?? 'Unbekannt' }}</span></div>
                                            <button wire:click.stop="unassignSupplier('{{ $loss->id }}')" class="text-red-500 hover:text-red-400 text-[9px] font-bold uppercase tracking-widest flex items-center gap-1 transition-colors"><i class="bi bi-x-circle"></i> Lösen</button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- STEP 2: Contact -->
                            <div class="flex items-start gap-3 p-3 rounded-xl transition-colors {{ !$isAssigned ? 'opacity-30 pointer-events-none' : ($isReported ? 'opacity-50 bg-gray-950 border border-gray-800/50' : 'bg-gray-900 border border-gray-700 shadow-sm') }}">
                                <div class="mt-1 shrink-0">
                                    @if($isReported)
                                        <i class="solar-check-circle-bold text-emerald-500 text-2xl drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]"></i>
                                    @else
                                        <i class="solar-letter-opened-bold-duotone text-amber-500 text-2xl"></i>
                                    @endif
                                </div>
                                <div class="flex-1 w-full overflow-hidden">
                                    <p class="text-sm font-semibold {{ $isReported ? 'line-through text-gray-500' : 'text-gray-200' }}">Reklamation einreichen</p>
                                    
                                    @if($isAssigned && !$isReported)
                                        <div class="mt-2 text-[11px] text-gray-400 bg-gray-950 p-3 rounded border border-gray-800">
                                            Kontaktiere den Lieferanten mit einem Bildnachweis.<br><br>
                                            <span class="text-white block mt-2 font-bold"><i class="bi bi-person mr-1 text-gray-500"></i> {{ $loss->supplier->contact_person ?? 'Kein AP' }}</span>
                                            <span class="text-blue-400 block"><i class="bi bi-envelope mr-1 text-gray-500"></i> {{ $loss->supplier->email ?? 'Keine E-Mail' }}</span>
                                            @if(is_array($loss->supplier->dynamic_links))
                                                @foreach($loss->supplier->dynamic_links as $link)
                                                    <a href="{{ $link['url'] }}" target="_blank" class="text-blue-400 block hover:underline"><i class="bi bi-link-45deg mr-1 text-gray-500"></i> {{ $link['title'] }}</a>
                                                @endforeach
                                            @endif
                                        </div>
                                        <div class="mt-3 flex justify-end">
                                            <button wire:click="markAsReported('{{ $loss->id }}')" class="bg-amber-500/10 hover:bg-amber-500 text-amber-400 hover:text-white border border-amber-500/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-colors flex items-center gap-1.5">
                                                <i class="bi bi-check2-all"></i> Nachricht versandt!
                                            </button>
                                        </div>
                                    @elseif($isReported)
                                        <div class="flex items-center justify-between mt-1">
                                            <div class="text-[10px] text-gray-500 font-mono">Gemeldet am: {{ $loss->reported_to_supplier_at->format('d.m.Y H:i') }}</div>
                                            <button wire:click.stop="undoReported('{{ $loss->id }}')" class="text-amber-500 hover:text-amber-400 text-[9px] font-bold uppercase tracking-widest flex items-center gap-1 transition-colors"><i class="bi bi-arrow-counterclockwise"></i> Zurücksetzen</button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- STEP 3: Refund -->
                            <div class="flex items-start gap-3 p-3 rounded-xl transition-colors {{ !$isReported ? 'opacity-30 pointer-events-none' : ($isRefunded ? 'opacity-50 bg-gray-950 border border-emerald-500/20' : 'bg-gray-900 border border-emerald-500/50 shadow-[0_0_15px_rgba(16,185,129,0.1)]') }}">
                                <div class="mt-1 shrink-0">
                                    @if($isRefunded)
                                        <i class="solar-check-circle-bold text-emerald-500 text-2xl drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]"></i>
                                    @else
                                        <i class="solar-wallet-bold-duotone text-emerald-500 text-2xl animate-pulse"></i>
                                    @endif
                                </div>
                                <div class="flex-1 w-full overflow-hidden">
                                    <p class="text-sm font-semibold {{ $isRefunded ? 'line-through text-gray-500' : 'text-emerald-400' }}">Rückerstattung / Ersatzlieferung buchen</p>
                                    
                                    @if($isReported && !$isRefunded)
                                        <div class="mt-2 text-[11px] text-gray-400">
                                            Sobald das Geld erstattet wurde oder der Ersatzartikel unterwegs ist, schließe diesen Vorgang ab.
                                        </div>
                                        <div class="mt-3 flex justify-end">
                                            <button wire:click="markAsRefunded('{{ $loss->id }}')" wire:confirm="Wurde das Geld erstattet oder eine Nachlieferung des Händlers versandt?" class="bg-emerald-600 hover:bg-emerald-500 text-white shadow-[0_0_15px_rgba(16,185,129,0.3)] border border-emerald-400/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-all gap-1.5 flex items-center">
                                                <i class="bi bi-check-circle-fill"></i> Erstattung erhalten
                                            </button>
                                        </div>
                                    @elseif($isRefunded)
                                        <div class="flex items-center justify-between mt-1">
                                            <div class="text-[10px] text-emerald-500/70 font-mono">Abgeschlossen am: {{ $loss->refund_received_at->format('d.m.Y H:i') }}</div>
                                            <button wire:click.stop="undoRefunded('{{ $loss->id }}')" class="text-emerald-500 hover:text-emerald-400 text-[9px] font-bold uppercase tracking-widest flex items-center gap-1 transition-colors"><i class="bi bi-arrow-counterclockwise"></i> Zurücksetzen</button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500 bg-gray-900/30 rounded-xl border border-dashed border-gray-800">
                    <i class="solar-box-bold-duotone text-4xl mx-auto mb-3 opacity-30 block"></i>
                    <p>Alle Artikel intakt.<br>Aktuell keine ungelösten Schadensmeldungen.</p>
                </div>
            @endforelse
        </div>

        @if($losses->hasPages())
            <div class="mt-6">
                {{ $losses->links('pagination.backend') }}
            </div>
        @endif
    </div>

    <!-- WORKFLOW MODAL FOR CREATION: ONLY STEP 1 -->
    <div x-show="$wire.lossModalOpen" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-6 bg-black/80 backdrop-blur-md"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div @click.away="$wire.lossModalOpen = false" class="bg-gray-900 border border-gray-800 rounded-[2rem] shadow-2xl w-full max-w-lg relative overflow-hidden flex flex-col"
             x-transition:enter="ease-out duration-300 delay-100" x-transition:enter-start="opacity-0 translate-y-8 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
            
            <button @click="$wire.lossModalOpen = false" class="absolute top-5 right-5 text-gray-500 hover:text-white bg-gray-800 hover:bg-gray-700 w-8 h-8 rounded-full flex items-center justify-center transition-colors z-10"><i class="bi bi-x-lg text-sm"></i></button>
            
            <div class="px-8 pt-8 pb-4 border-b border-gray-800 relative">
                <div class="absolute inset-0 bg-gradient-to-r from-red-500/10 to-transparent pointer-events-none"></div>
                <h3 class="text-xl font-serif font-bold text-white relative flex items-center gap-3">
                    <i class="solar-bomb-minimalistic-bold-duotone text-red-500 text-2xl"></i> Neue Schadensmeldung
                </h3>
            </div>
            
            <div class="p-8 flex-1 overflow-y-auto">
                <form wire:submit.prevent="createLossRecord" class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 pl-1">Welches Produkt ist beschädigt?</label>
                        <select wire:model="lossProductId" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3.5 text-sm font-bold text-white focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors shadow-inner outline-none" required>
                            <option value="">-- Produkt auswählen --</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} (Lager: {{ $p->quantity }})</option>
                            @endforeach
                        </select>
                        @error('lossProductId') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 pl-1">Defekte Menge</label>
                        <input type="number" wire:model="lossQuantity" min="1" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3.5 text-sm font-bold text-white focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors shadow-inner outline-none" required>
                        @error('lossQuantity') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 pl-1">Ursache / Grund</label>
                        <textarea wire:model="lossReason" rows="3" placeholder="Z.B. Holz beim Laserschnitt gesplittert, Maschine verstellt, Mangelware aus China..." class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-3.5 text-sm font-medium text-white focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors shadow-inner outline-none resize-none" required></textarea>
                        @error('lossReason') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-between items-center mt-8 pt-4 border-t border-gray-800">
                        <button type="button" @click="$wire.closeLossModal()" class="px-5 py-3 rounded-xl bg-transparent text-gray-400 hover:text-white text-[10px] font-black uppercase tracking-widest transition-colors">Abbrechen</button>
                        <button type="submit" class="px-5 py-3 rounded-xl bg-red-600 hover:bg-red-500 text-white shadow-[0_0_15px_rgba(239,68,68,0.4)] border border-red-400/30 text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2">
                            Erfassen <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

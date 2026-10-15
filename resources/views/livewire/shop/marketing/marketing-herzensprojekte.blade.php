<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-80: {{ $this->themeColorHex }}CC;">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-8">
        
        {{-- HEADER --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="text-3xl">❤️</span>
                    <h1 class="text-2xl md:text-3xl font-black text-gray-900 dark:text-[var(--theme-color)] uppercase tracking-widest drop-shadow-none dark:drop-shadow-[0_0_10px_var(--theme-color-30)]">
                        Herzensprojekte
                    </h1>
                </div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 font-mono tracking-wide uppercase">
                    Transparente Verwaltung unseres Spenden-Topfs & regionaler Aktionen für Kinder & Kitas.
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                <button 
                    wire:click="openDepositModal" 
                    class="px-4 py-2 rounded-xl text-xs uppercase tracking-widest font-black text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 hover:border-gray-400 dark:hover:border-[var(--theme-color-50)] hover:text-gray-900 dark:hover:text-white shadow-sm transition-all flex items-center gap-2"
                >
                    <span>➕</span> Einzahlung verbuchen
                </button>
                <button 
                    wire:click="openCreateActionModal" 
                    class="px-5 py-2.5 rounded-xl text-xs uppercase tracking-widest font-black text-gray-900 dark:text-white bg-[var(--theme-color)] hover:opacity-90 transition-all shadow-sm dark:shadow-[0_0_20px_var(--theme-color-30)] flex items-center gap-2"
                >
                    <span>🎁</span> Neue Aktion planen
                </button>
            </div>
        </div>

        {{-- METRICS DASHBOARD --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Card 1: Aktueller Topf --}}
            <div class="relative bg-white dark:bg-gradient-to-br dark:from-[var(--theme-color-15)] dark:via-gray-950 dark:to-gray-900 border border-gray-200 dark:border-[var(--theme-color-30)] rounded-2xl p-6 shadow-sm dark:shadow-[0_0_30px_var(--theme-color-10)] overflow-hidden transition-colors">
                <div class="hidden dark:block absolute -right-4 -bottom-4 w-24 h-24 bg-[var(--theme-color-10)] rounded-full blur-xl pointer-events-none"></div>
                <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] block mb-2">Verfügbarer Herzens-Topf</span>
                <div class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white font-mono drop-shadow-none dark:drop-shadow-[0_0_15px_var(--theme-color-30)]">
                    {{ $stats['current_pot_formatted'] }}
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-mono">
                    Bereit für nächste Übergaben
                </p>
            </div>

            {{-- Card 2: Gesamt gesammelt --}}
            <div class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-inner relative overflow-hidden transition-colors">
                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 block mb-2">Allzeit Zufluss</span>
                <div class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-gray-200 font-mono">
                    {{ $stats['total_collected_formatted'] }}
                </div>
                <p class="text-xs text-gray-500 mt-2 font-mono">
                    Gesamte Spendensumme
                </p>
            </div>

            {{-- Card 3: Bisher übergeben --}}
            <div class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-inner relative overflow-hidden transition-colors">
                <span class="text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400 block mb-2">Bereits Realisiert</span>
                <div class="text-3xl sm:text-4xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
                    {{ $stats['total_disbursed_formatted'] }}
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-mono">
                    In {{ $stats['completed_actions_count'] }} Kita-Aktion(en)
                </p>
            </div>

            {{-- Card 4: Bestellungen --}}
            <div class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-inner relative overflow-hidden transition-colors">
                <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] block mb-2">Unterstützte Bestellungen</span>
                <div class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white font-mono">
                    {{ $stats['orders_count'] }}
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-mono">
                    à {{ number_format($stats['cents_per_order'] / 100, 2, ',', '.') }} € pro Bestellung
                </p>
            </div>
        </div>

        {{-- TAB NAVIGATION --}}
        <div class="border-b border-gray-200 dark:border-gray-800 flex gap-6">
            <button 
                wire:click="$set('activeTab', 'actions')" 
                class="pb-3 text-sm font-bold tracking-wide transition-all border-b-2 {{ $activeTab === 'actions' ? 'border-[var(--theme-color)] text-gray-900 dark:text-white' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}"
            >
                Aktionsliste & Kitas ({{ $actions->count() }})
            </button>
            <button 
                wire:click="$set('activeTab', 'ledger')" 
                class="pb-3 text-sm font-bold tracking-wide transition-all border-b-2 {{ $activeTab === 'ledger' ? 'border-[var(--theme-color)] text-gray-900 dark:text-white' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}"
            >
                Transaktionen & Zuflüsse
            </button>
            <button 
                wire:click="$set('activeTab', 'settings')" 
                class="pb-3 text-sm font-bold tracking-wide transition-all border-b-2 {{ $activeTab === 'settings' ? 'border-[var(--theme-color)] text-gray-900 dark:text-white' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}"
            >
                Einstellungen
            </button>
        </div>

        {{-- TAB 1: AKTIONSLISTE --}}
        @if($activeTab === 'actions')
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500 font-mono uppercase">Filter:</span>
                        <select wire:model.live="statusFilter" class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-800 rounded-xl px-3 py-1.5 text-xs text-gray-900 dark:text-white focus:outline-none shadow-sm">
                            <option value="all">Alle Aktionen</option>
                            <option value="planned">Nur Geplante</option>
                            <option value="in_progress">In Umsetzung</option>
                            <option value="completed">Erfolgreich Übergeben</option>
                        </select>
                    </div>
                </div>

                @if($actions->isEmpty())
                    <div class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-12 text-center shadow-sm">
                        <span class="text-4xl block mb-3">🌱</span>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Noch keine Aktionen angelegt</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">
                            Lege deine erste Spendenaktion für einen Kindergarten oder ein Projekt vor Ort an, um eure soziale Wirkung festzuhalten.
                        </p>
                        <button wire:click="openCreateActionModal" class="px-5 py-2.5 rounded-xl text-xs uppercase tracking-widest font-black text-gray-900 dark:text-white bg-[var(--theme-color)] hover:opacity-90 shadow-sm">
                            Erste Aktion planen
                        </button>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($actions as $action)
                            <div wire:key="action-{{ $action->id }}" class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 rounded-2xl p-6 flex flex-col justify-between shadow-sm dark:shadow-inner transition-all group">
                                <div>
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] uppercase tracking-widest font-black border {{ $action->status_color }}">
                                            {{ $action->status_label }}
                                        </span>
                                        <span class="font-mono text-lg font-bold text-gray-900 dark:text-white">
                                            {{ $action->formatted_amount }}
                                        </span>
                                    </div>

                                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1 group-hover:text-[var(--theme-color)] transition-colors">
                                        {{ $action->title }}
                                    </h3>
                                    <p class="text-xs text-amber-600 dark:text-amber-400 font-mono mb-3 flex items-center gap-1.5">
                                        <span>📍</span> {{ $action->target_recipient }}
                                    </p>

                                    @if($action->description)
                                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed mb-4 line-clamp-3">
                                            {{ $action->description }}
                                        </p>
                                    @endif

                                    @if($action->public_story)
                                        <div class="p-3 bg-gray-50 dark:bg-gray-900/60 rounded-xl border border-gray-200 dark:border-gray-800 text-[11px] text-gray-700 dark:text-gray-300 italic mb-4">
                                            „{{ \Illuminate\Support\Str::limit($action->public_story, 120) }}“
                                        </div>
                                    @endif
                                </div>

                                <div class="pt-4 border-t border-gray-100 dark:border-gray-800/80 flex items-center justify-between gap-2 mt-4">
                                    <div class="flex items-center gap-1">
                                        <button 
                                            wire:click="openEditActionModal('{{ $action->id }}')" 
                                            class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                            title="Bearbeiten"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <button 
                                            wire:click="deleteAction('{{ $action->id }}')" 
                                            class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                                            title="Löschen"
                                            onclick="confirm('Aktion wirklich löschen?') || event.stopImmediatePropagation()"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>

                                    @if($action->status !== 'completed')
                                        <button 
                                            wire:click="disburseForAction('{{ $action->id }}')" 
                                            class="px-3 py-1.5 rounded-lg text-[11px] font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition-all flex items-center gap-1"
                                            onclick="confirm('{{ $action->formatted_amount }} jetzt verbindlich aus dem Topf abbuchen und als übergeben markieren?') || event.stopImmediatePropagation()"
                                        >
                                            <span>✓</span> Als übergeben verbuchen
                                        </button>
                                    @else
                                        <span class="text-[11px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1 font-mono font-medium">
                                            <span>✓</span> Übergeben am {{ $action->completed_at ? $action->completed_at->format('d.m.Y') : '-' }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- TAB 2: TRANSAKTIONEN / LEDGER --}}
        @if($activeTab === 'ledger')
            <div class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-inner transition-colors">
                <div class="p-6 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Transaktions-Historie (Ledger)</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Automatische Buchungen aus Bestellungen sowie Auszahlungen für Aktionen.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-gray-700 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 uppercase font-mono text-[10px] text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
                            <tr>
                                <th class="p-4">Datum</th>
                                <th class="p-4">Typ</th>
                                <th class="p-4">Beschreibung</th>
                                <th class="p-4 text-right">Betrag</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60">
                            @forelse($transactions as $tx)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="p-4 font-mono text-gray-500 dark:text-gray-400">
                                        {{ $tx->created_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="p-4">
                                        @if($tx->type === 'order_contribution')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20">
                                                Bestellung
                                            </span>
                                        @elseif($tx->type === 'disbursement')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-red-50 text-red-700 border border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20">
                                                Auszahlung / Übergabe
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                                                Manuelle Einzahlung
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-gray-700 dark:text-gray-300">
                                        {{ $tx->description }}
                                    </td>
                                    <td class="p-4 font-mono font-bold text-right {{ $tx->amount_cents >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $tx->formatted_amount }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-8 text-center text-gray-500">
                                        Noch keine Buchungen im Topf vorhanden.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- TAB 3: EINSTELLUNGEN --}}
        @if($activeTab === 'settings')
            <div class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8 max-w-2xl space-y-6 shadow-sm transition-colors">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Spenden-Einstellungen</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Konfiguriere den automatischen Zufluss pro getätigter Bestellung.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono uppercase text-gray-600 dark:text-gray-400 mb-1">
                            Fester Spendenbeitrag pro Bestellung (in Cents)
                        </label>
                        <div class="flex items-center gap-3">
                            <input 
                                type="number" 
                                wire:model="centsPerOrder" 
                                class="w-48 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white font-mono focus:border-[var(--theme-color)] focus:outline-none"
                            >
                            <span class="text-xs text-gray-600 dark:text-gray-400">
                                = <strong>{{ number_format(((int)$centsPerOrder) / 100, 2, ',', '.') }} €</strong> pro bezahlter Auftrag
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-1">
                            Standard: 200 Cents (2,00 €). Dieser Betrag wird automatisch bei jeder bezahlten Bestellung in den Herzens-Topf eingebucht.
                        </p>
                    </div>

                    <div class="pt-4 border-t border-gray-200 dark:border-gray-800">
                        <button 
                            wire:click="saveSettings" 
                            class="px-5 py-2.5 rounded-xl text-xs uppercase tracking-widest font-black text-gray-900 dark:text-white bg-[var(--theme-color)] hover:opacity-90 shadow-sm transition-all"
                        >
                            Einstellungen speichern
                        </button>
                    </div>
                </div>
            </div>
        @endif

    </div>

    {{-- MODAL: AKTION ANLEGEN / BEARBEITEN --}}
    @if($showActionModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-gray-900/60 dark:bg-gray-950/80 backdrop-blur-md">
            <div class="relative w-full max-w-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-xl dark:shadow-2xl p-6 sm:p-8 space-y-6 animate-fade-in-up transition-colors">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $editingActionId ? 'Aktion bearbeiten' : 'Neue Herzensprojekt-Aktion anlegen' }}
                    </h2>
                    <button wire:click="closeActionModal" class="text-gray-400 hover:text-gray-700 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveAction" class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono uppercase text-gray-600 dark:text-gray-400 mb-1">Titel der Aktion *</label>
                        <input type="text" wire:model="actionTitle" class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-[var(--theme-color)]" placeholder="z.B. Holzspielzeug & Lasergravuren">
                        @error('actionTitle') <span class="text-xs text-red-500 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono uppercase text-gray-600 dark:text-gray-400 mb-1">Empfänger / Kita vor Ort *</label>
                            <input type="text" wire:model="actionRecipient" class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-[var(--theme-color)]" placeholder="z.B. Kita Sonnenschein, Gifhorn">
                            @error('actionRecipient') <span class="text-xs text-red-500 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-mono uppercase text-gray-600 dark:text-gray-400 mb-1">Ziel- / Spendenbetrag in € *</label>
                            <input type="text" wire:model="actionAmountEuro" class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white font-mono focus:outline-none focus:border-[var(--theme-color)]" placeholder="z.B. 350,00">
                            @error('actionAmountEuro') <span class="text-xs text-red-500 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono uppercase text-gray-600 dark:text-gray-400 mb-1">Status</label>
                        <select wire:model="actionStatus" class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none">
                            <option value="planned">Geplant</option>
                            <option value="in_progress">In Umsetzung</option>
                            <option value="completed">Erfolgreich Übergeben</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono uppercase text-gray-600 dark:text-gray-400 mb-1">Interne Beschreibung</label>
                        <textarea rows="3" wire:model="actionDescription" class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none" placeholder="Was genau wird benötigt oder geliefert?"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-mono uppercase text-gray-600 dark:text-gray-400 mb-1">Öffentliche Story (für Kunden & Landingpage)</label>
                        <textarea rows="3" wire:model="actionPublicStory" class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none" placeholder="Kurze Story für Kunden über diese Aktion..."></textarea>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" id="actionIsPublic" wire:model="actionIsPublic" class="h-4 w-4 rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                        <label for="actionIsPublic" class="text-xs text-gray-700 dark:text-gray-300 select-none">
                            Öffentlich anzeigen (für Storytelling & Shop-Bereiche)
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="closeActionModal" class="px-5 py-2.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white rounded-xl hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">
                            Abbrechen
                        </button>
                        <button type="submit" class="px-6 py-2.5 text-sm font-bold text-gray-900 dark:text-white bg-[var(--theme-color)] hover:opacity-90 rounded-xl shadow-sm dark:shadow-lg transition-all">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL: MANUELLE EINZAHLUNG --}}
    @if($showDepositModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-gray-900/60 dark:bg-gray-950/80 backdrop-blur-md">
            <div class="relative w-full max-w-md bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-xl dark:shadow-2xl p-6 sm:p-8 space-y-6 animate-fade-in-up transition-colors">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Guthaben einzahlen</h2>
                    <button wire:click="closeDepositModal" class="text-gray-400 hover:text-gray-700 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono uppercase text-gray-600 dark:text-gray-400 mb-1">Betrag in €</label>
                        <input type="text" wire:model="depositAmountEuro" class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white font-mono focus:outline-none focus:border-[var(--theme-color)]" placeholder="50.00">
                    </div>

                    <div>
                        <label class="block text-xs font-mono uppercase text-gray-600 dark:text-gray-400 mb-1">Notiz / Verwendungszweck</label>
                        <input type="text" wire:model="depositNote" class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white font-mono focus:outline-none focus:border-[var(--theme-color)]">
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="closeDepositModal" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white rounded-xl hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">
                            Abbrechen
                        </button>
                        <button type="button" wire:click="saveDeposit" class="px-5 py-2 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow-sm transition-all">
                            Einzahlen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

<div class="space-y-6 sm:space-y-8 bg-gray-900/80 backdrop-blur-md p-6 sm:p-8 rounded-[2rem] border border-gray-800 shadow-inner relative animate-fade-in-down">

    {{-- Label / Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b border-gray-800 pb-5 gap-4">
        <div class="flex items-center gap-3">
            <div class="h-2 w-2 bg-[var(--theme-color)] rounded-full shadow-[0_0_8px_var(--theme-color-80)] animate-pulse"></div>
            <span class="text-[10px] uppercase font-black text-gray-400 tracking-[0.2em]">Kostenstelle bearbeiten</span>
        </div>
        {{-- Gruppe wechseln Select --}}
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest whitespace-nowrap">Verschieben nach:</label>
            <select wire:model="targetGroupId" class="w-full sm:w-auto text-xs font-bold border border-gray-700 bg-gray-950 text-white rounded-xl py-2 px-3 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] cursor-pointer outline-none shadow-inner">
                @foreach($groups as $g)
                    <option value="{{ $g->id }}" class="bg-gray-900">{{ $g->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @php
        $inputClass = "w-full text-sm p-4 rounded-xl border border-gray-700 bg-gray-950 text-white placeholder-gray-600 focus:bg-black focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-30)] transition-all duration-300 outline-none shadow-inner";
    @endphp

    {{-- Zeile 1: Name & Betrag --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 xl:gap-8">
        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Bezeichnung</label>
            <input type="text" wire:model="itemName" placeholder="z.B. Miete" class="{{ $inputClass }}">
            @error('itemName') <span class="text-[10px] text-red-400 mt-2 block font-bold tracking-widest uppercase ml-1 drop-shadow-[0_0_8px_currentColor]">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Betrag</label>
            <div class="relative">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 text-sm font-bold">€</span>
                <input type="number" step="0.01" wire:model="itemAmount" placeholder="0.00" class="{{ $inputClass }} pl-10 font-mono">
            </div>
            @error('itemAmount') <span class="text-[10px] text-red-400 mt-2 block font-bold tracking-widest uppercase ml-1 drop-shadow-[0_0_8px_currentColor]">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Steuersatz</label>
            <div class="relative">
                <select wire:model="itemTaxRate" class="{{ $inputClass }} appearance-none cursor-pointer">
                    <option value="0" class="bg-gray-950">0 %</option>
                    <option value="7" class="bg-gray-950">7 %</option>
                    <option value="19" class="bg-gray-950">19 %</option>
                </select>
                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Zeile 2: Intervall, Datum, Datei --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 xl:gap-8">
        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Intervall</label>
            <div class="relative">
                <select wire:model="itemInterval" class="{{ $inputClass }} appearance-none cursor-pointer">
                    <option value="1" class="bg-gray-900">Monatlich</option>
                    <option value="3" class="bg-gray-900">Quartalsweise</option>
                    <option value="6" class="bg-gray-900">Halbjährlich</option>
                    <option value="12" class="bg-gray-900">Jährlich</option>
                    <option value="24" class="bg-gray-900">Alle 2 Jahre</option>
                </select>
                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
            </div>
        </div>

        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Erste Zahlung</label>
            <input type="date" wire:model="itemDate" class="{{ $inputClass }} cursor-pointer [&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)]">
            @error('itemDate') <span class="text-[10px] text-red-400 mt-2 block font-bold tracking-widest uppercase ml-1 drop-shadow-[0_0_8px_currentColor]">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Letzte Zahlung (Optional)</label>
            <input type="date" wire:model="itemLastPaymentDate" class="{{ $inputClass }} cursor-pointer [&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)]">
            @error('itemLastPaymentDate') <span class="text-[10px] text-red-400 mt-2 block font-bold tracking-widest uppercase ml-1 drop-shadow-[0_0_8px_currentColor]">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Vertrag / Datei</label>

            <div class="space-y-2">
                {{-- Force DOM recreation to clear file input text via wire:key --}}
                <div wire:key="file-input-{{ $item->id }}-{{ $itemExistingFile ? md5($itemExistingFile) : 'none' }}-{{ now()->timestamp }}">
                    <input type="file" wire:model="itemFile"
                           class="block w-full text-xs text-gray-500 file:mr-4 file:py-3.5 file:px-6 file:rounded-xl file:border-0 file:text-[9px] file:font-black file:uppercase file:tracking-widest file:bg-gray-800 file:text-gray-300 hover:file:bg-gray-700 hover:file:text-white transition-all cursor-pointer bg-gray-950 border border-gray-800 rounded-xl p-1 shadow-inner">
                </div>
                
                @if($itemExistingFile)
                    <div class="flex items-center justify-between p-2 rounded-xl border border-gray-800/50 bg-gray-950/50">
                        <a href="{{ Storage::url($itemExistingFile) }}" target="_blank" class="text-[10px] font-bold text-blue-400 hover:text-white transition-colors truncate max-w-[80%] flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Aktuelle Datei ansehen
                        </a>
                        <button wire:click="removeFileFromItem('{{ $item->id }}')" class="text-gray-500 hover:text-red-400 p-1 rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Zeile 3: Checkbox & Textarea --}}
    <div class="pt-2">
        <label class="inline-flex items-center cursor-pointer select-none group mb-5">
            <input type="checkbox" wire:model="itemIsBusiness" class="sr-only peer">
            <div class="relative w-11 h-6 bg-gray-950 border border-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[1px] after:start-[1px] after:bg-gray-500 after:border-gray-500 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[var(--theme-color)] shadow-inner peer-checked:after:bg-gray-900"></div>
            <span class="ms-3 text-[10px] font-black uppercase tracking-widest text-gray-500 group-hover:text-gray-300 transition-colors">Gewerblicher Eintrag</span>
        </label>

        <textarea wire:model="itemDescription" placeholder="Notizen, Vertragsnummer, Kundennummer..."
                  class="{{ $inputClass }} resize-none leading-relaxed" rows="3"></textarea>
    </div>

    {{-- HISTORY & CHART SECTION --}}
    @if($editingItemId && $item && $item->histories && $item->histories->count() > 0)
        <div class="mt-8 pt-6 border-t border-gray-800 grid grid-cols-1 gap-8" wire:ignore.self>
            {{-- Log Table --}}
            <div class="bg-gray-950/50 rounded-2xl border border-gray-800 p-5 shadow-inner flex flex-col h-[350px]">
                <h4 class="text-xs font-black uppercase text-gray-400 tracking-widest mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Kostenentwicklung Log
                </h4>
                <div class="overflow-y-auto flex-1 relative min-h-0 pr-2 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-gray-800 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-gray-700">
                    <div class="absolute w-0.5 bg-gray-800 h-full left-[11px] top-0 z-0"></div>
                    <ul class="space-y-4 relative z-10">
                        @foreach($item->histories as $history)
                            <li class="flex items-start gap-4" x-data="{ expanded: false }">
                                <div class="w-6 h-6 rounded-full bg-gray-900 border-2 border-[var(--theme-color-50)] flex items-center justify-center shrink-0 mt-0.5 shadow-[0_0_10px_var(--theme-color-20)]">
                                    <div class="w-2 h-2 rounded-full bg-[var(--theme-color)] animate-pulse"></div>
                                </div>
                                <div class="flex-1 bg-gray-900/80 p-3 rounded-xl border border-gray-800 shadow-inner group transition-all hover:border-gray-700">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-[10px] font-bold text-gray-400">{{ $history->created_at->format('d.m.Y H:i') }}</span>
                                        <span class="text-xs font-mono font-bold text-white">{{ number_format($history->amount, 2, ',', '.') }} €</span>
                                    </div>
                                    <p class="text-[10px] text-gray-500 leading-tight mb-2">{{ $history->description }}</p>
                                    <div class="flex items-center gap-3">
                                        <button wire:click="restoreHistory({{ $history->id }})" class="text-[9px] font-black uppercase tracking-widest text-emerald-500 hover:text-emerald-400 transition-colors flex items-center gap-1 opacity-60 group-hover:opacity-100">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                            Wiederherstellen
                                        </button>
                                        <button wire:click="deleteHistory({{ $history->id }})" wire:confirm="Soll dieser Snapshot wirklich gelöscht werden?" class="text-[9px] font-black uppercase tracking-widest text-red-500 hover:text-red-400 transition-colors flex items-center gap-1 opacity-60 group-hover:opacity-100">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            Löschen
                                        </button>
                                        <div class="flex-1"></div>
                                        <button @click="expanded = !expanded" class="text-[9px] font-black uppercase tracking-widest text-blue-500 hover:text-blue-400 transition-colors flex items-center gap-1 opacity-60 group-hover:opacity-100">
                                            <svg class="w-3 h-3" :class="{'rotate-180': expanded}" class="transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            <span x-text="expanded ? 'Schließen' : 'Details'"></span>
                                        </button>
                                    </div>
                                    
                                    <div x-show="expanded" x-collapse x-cloak class="mt-4 pt-4 border-t border-gray-800/50">
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                                            <div><span class="text-gray-500 block text-[9px] uppercase tracking-widest mb-1">Name / Betreff</span><span class="text-white font-bold">{{ $history->name ?? '-' }}</span></div>
                                            <div><span class="text-gray-500 block text-[9px] uppercase tracking-widest mb-1">Betrag</span><span class="text-white font-bold font-mono">{{ number_format($history->amount, 2, ',', '.') }} €</span></div>
                                            <div><span class="text-gray-500 block text-[9px] uppercase tracking-widest mb-1">Intervall</span><span class="text-white font-bold">{{ $history->interval_months }} Monat(e)</span></div>
                                            <div><span class="text-gray-500 block text-[9px] uppercase tracking-widest mb-1">Gewerblich</span><span class="text-white font-bold">{{ $history->is_business ? 'Ja' : 'Nein' }}</span></div>
                                            <div><span class="text-gray-500 block text-[9px] uppercase tracking-widest mb-1">Steuersatz</span><span class="text-white font-bold">{{ $history->tax_rate ?? 0 }} %</span></div>
                                            <div><span class="text-gray-500 block text-[9px] uppercase tracking-widest mb-1">1. Zahlung</span><span class="text-white font-bold">{{ $history->first_payment_date ? $history->first_payment_date->format('d.m.Y') : '-' }}</span></div>
                                            <div><span class="text-gray-500 block text-[9px] uppercase tracking-widest mb-1">Letzte Zahlung</span><span class="text-white font-bold">{{ $history->last_payment_date ? $history->last_payment_date->format('d.m.Y') : '-' }}</span></div>
                                        </div>
                                        @if($history->contract_file_path)
                                        <div class="mt-4 flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                            <span class="text-[10px] text-gray-400 font-medium">Originaldatei in diesem Snapshot verlinkt</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Chart Canvas --}}
            <div wire:key="history-chart-{{ $item->id }}-{{ now()->format('U.u') }}"
                 class="bg-gray-950/50 rounded-2xl border border-gray-800 p-5 shadow-inner flex flex-col h-[350px]"
                 x-data="{
                    chart: null,
                    initChart() {
                        const ctx = $refs.historyChart.getContext('2d');
                        const rawData = @js($item->histories->map(fn($h) => ['date' => $h->created_at->format('d.m.Y'), 'amount' => $h->amount])->reverse()->values());
                        
                        if (rawData.length === 0) return;

                        const labels = rawData.map(d => d.date);
                        const amounts = rawData.map(d => d.amount);

                        // Gradient setup
                        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                        gradient.addColorStop(0, 'var(--theme-color-50)'); // primary
                        gradient.addColorStop(1, 'transparent');

                        this.chart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Kostenentwicklung (€)',
                                    data: amounts,
                                    borderColor: '{{ $this->themeColorHex }}',
                                    borderWidth: 2,
                                    backgroundColor: gradient,
                                    fill: true,
                                    tension: 0.4,
                                    pointBackgroundColor: '#111827',
                                    pointBorderColor: '{{ $this->themeColorHex }}',
                                    pointBorderWidth: 2,
                                    pointRadius: 4,
                                    pointHoverRadius: 6
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: 'rgba(17, 24, 39, 0.95)',
                                        titleColor: '#9ca3af',
                                        bodyColor: '#fff',
                                        bodyFont: { family: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas' },
                                        borderColor: 'var(--theme-color-30)',
                                        borderWidth: 1,
                                        padding: 10,
                                        displayColors: false,
                                        callbacks: {
                                            label: function(context) {
                                                return context.parsed.y.toFixed(2).replace('.', ',') + ' €';
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: false,
                                        grid: { color: 'rgba(31, 41, 55, 0.6)', drawBorder: false },
                                        ticks: { color: '#6b7280', font: { size: 10 } }
                                    },
                                    x: {
                                        grid: { display: false, drawBorder: false },
                                        ticks: { color: '#6b7280', font: { size: 10, family: 'sans-serif' }, maxRotation: 45, minRotation: 45 }
                                    }
                                }
                            }
                        });
                    }
                 }"
                 x-init="initChart()">
                <h4 class="text-xs font-black uppercase text-gray-400 tracking-widest mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                    Verlauf
                </h4>
                <div class="relative flex-1 w-full mt-2" wire:ignore>
                    <canvas x-ref="historyChart"></canvas>
                </div>
            </div>
        </div>
    @endif

    {{-- Footer Actions --}}
    <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 sm:gap-4 pt-6 border-t border-gray-800">
        <button wire:click="cancelItemEdit"
                class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-gray-500 bg-gray-900 border border-gray-800 hover:text-white hover:bg-gray-800 rounded-xl transition-all shadow-inner w-full sm:w-auto text-center">
            Abbrechen
        </button>
        <button wire:click="saveItem" wire:loading.attr="disabled"
                class="bg-emerald-500 border border-emerald-400 text-gray-900 px-8 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:bg-emerald-400 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2 w-full sm:w-auto">
            <span wire:loading.remove wire:target="saveItem">Speichern</span>
            <span wire:loading wire:target="saveItem">Speichert...</span>
            <svg wire:loading wire:target="saveItem" class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        </button>
    </div>
</div>

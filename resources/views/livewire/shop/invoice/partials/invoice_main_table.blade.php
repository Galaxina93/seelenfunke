<div class="animate-fade-in-up">
    {{-- Navigation Tabs --}}
    <div class="flex items-center gap-2 sm:gap-4 mb-8 border-b border-gray-800 overflow-x-auto no-scrollbar">
        <button wire:click="switchTab('list')" @class(['px-4 sm:px-6 py-3 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all border-b-2 whitespace-nowrap', $activeTab === 'list' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300'])>
            Alle Belege
        </button>
        <button wire:click="switchTab('e_invoices')" @class(['px-4 sm:px-6 py-3 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all border-b-2 whitespace-nowrap', $activeTab === 'e_invoices' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300'])>
            E-Rechnungen
        </button>
        <button wire:click="switchTab('archive')" @class(['px-4 sm:px-6 py-3 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all border-b-2 whitespace-nowrap', $activeTab === 'archive' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300'])>
            PDF-Archiv (Storage)
        </button>
    </div>

    @if($activeTab === 'archive')
        {{-- Storage Archive View --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] border border-gray-800 shadow-2xl overflow-hidden animate-fade-in">
            <div class="p-6 sm:p-8 border-b border-gray-800 bg-gray-950 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shadow-inner">
                <div>
                    <h3 class="text-lg sm:text-xl font-serif font-bold text-white tracking-wide">Digitales Rechnungsarchiv</h3>
                    <p class="text-[9px] text-gray-500 uppercase font-black tracking-[0.2em] mt-1.5 flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                        storage/app/invoices
                    </p>
                </div>
            </div>
            <div class="overflow-x-auto w-full no-scrollbar">
                <table class="w-full text-left min-w-[600px] border-collapse">
                    <thead>
                    <tr class="bg-gray-900/50 text-[10px] uppercase font-black text-gray-500 tracking-widest border-b border-gray-800">
                        <th class="px-6 sm:px-8 py-5">Dateiname</th>
                        <th class="px-4 py-5">Größe</th>
                        <th class="px-4 py-5">Datum</th>
                        <th class="px-6 sm:px-8 py-5 text-right">Aktion</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50 text-sm">
                    @forelse($archivedFiles as $file)
                        <tr class="hover:bg-gray-800/30 transition-colors group">
                            <td class="px-6 sm:px-8 py-4 flex items-center gap-4">
                                <div class="p-2.5 bg-red-500/10 text-red-400 border border-red-500/20 rounded-xl group-hover:bg-red-500 group-hover:text-white group-hover:border-red-500 transition-all shadow-inner">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                </div>
                                <span class="font-mono text-sm font-bold text-gray-300 group-hover:text-white transition-colors truncate max-w-[200px] sm:max-w-xs">{{ $file['name'] }}</span>
                            </td>
                            <td class="px-4 py-4 text-xs font-black text-gray-500 uppercase tracking-widest">{{ $file['size'] }}</td>
                            <td class="px-4 py-4 text-xs font-bold text-gray-400">{{ $file['date'] }}</td>
                            <td class="px-6 sm:px-8 py-4 text-right">
                                <button wire:click="downloadPdfByFilename('{{ $file['name'] }}')" class="text-primary font-black text-[10px] uppercase tracking-widest hover:text-white transition-colors border-b border-primary/30 pb-0.5 hover:border-white">Download</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-16 text-center text-gray-500 font-serif italic text-lg">Das Archiv ist aktuell leer.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        {{-- Toolbar & Suche --}}
        <div class="bg-gray-900/80 backdrop-blur-md p-3 sm:p-4 rounded-[2rem] border border-gray-800 flex flex-col md:flex-row gap-4 mb-8 shadow-2xl items-center w-full">
            <div class="relative flex-1 w-full lg:max-w-lg group">
                <input type="text" wire:model.live="search" placeholder="Suche nach Nummer oder Name..." class="w-full pl-12 pr-4 py-3 bg-gray-950 border border-gray-800 rounded-[1.5rem] text-sm text-white focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all placeholder-gray-600 shadow-inner outline-none">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-500 group-focus-within:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2"/></svg>
                </div>
            </div>
            <div class="flex gap-3 w-full md:w-auto overflow-x-auto no-scrollbar pb-1 md:pb-0 px-1">
                <select wire:model.live="filterType" class="flex-1 md:flex-none bg-gray-950 border border-gray-800 text-gray-400 rounded-[1.5rem] text-[10px] font-black uppercase tracking-widest p-3 sm:px-5 focus:bg-black focus:ring-2 focus:ring-primary focus:border-primary cursor-pointer hover:bg-gray-800 hover:text-white transition-all shadow-inner outline-none min-w-[160px]">
                    <option value="">Alle Belege</option>
                    <option value="invoice">Rechnungen</option>
                    <option value="draft">Entwürfe</option>
                    <option value="cancellation">Stornos</option>
                </select>
            </div>
        </div>

        {{-- Master Tabelle --}}
        @php
            $headers = [
                'invoice_number' => ['label' => 'Belegnummer', 'sortable' => true],
                'recipient'      => ['label' => 'Empfänger', 'sortable' => true],
                'total'          => ['label' => 'Bruttobetrag', 'align' => 'right', 'sortable' => true],
                'status'         => ['label' => 'Status', 'align' => 'center', 'sortable' => true],
                'actions'        => ['label' => 'Aktionen', 'align' => 'right']
            ];
        @endphp

        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden w-full">
            <x-table.master
                :headers="$headers"
                :rows="$invoices"
                :sortField="$sortField"
                :sortDirection="$sortDirection"
            >
                {{-- Desktop Content --}}
                @forelse($invoices as $inv)
                    <tr @class(['hover:bg-gray-800/30 transition-colors duration-300 group', 'bg-red-900/10 border-l-4 border-l-red-500' => $inv->status === 'cancelled' || $inv->type === 'cancellation', 'border-l-4 border-l-transparent' => !($inv->status === 'cancelled' || $inv->type === 'cancellation')])>
                        <td class="px-6 sm:px-8 py-5 font-mono font-bold text-gray-300 align-middle">
                            <div class="flex flex-col">
                                <div class="flex items-center gap-3">
                                    <span class="text-base tracking-wide group-hover:text-white transition-colors">{{ $inv->invoice_number }}</span>
                                    @if($inv->is_e_invoice)
                                        <span class="bg-blue-500/10 text-blue-400 border border-blue-500/30 text-[9px] px-1.5 py-0.5 rounded shadow-sm uppercase font-black" title="E-Rechnung (ZUGFeRD/XRechnung)">XML</span>
                                    @endif
                                </div>
                                @if($inv->type === 'cancellation' && $inv->parent_id)
                                    <div class="text-[9px] text-red-400 uppercase font-black tracking-widest mt-1.5 flex items-center gap-1.5">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                                        Zu: {{ \App\Models\Invoice::find($inv->parent_id)?->invoice_number ?? 'Unbekannt' }}
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-5 align-middle">
                            <div class="text-white font-bold truncate max-w-[200px]">
                                {{ $inv->billing_address['company'] ? $inv->billing_address['company'] . ' (' . $inv->billing_address['last_name'] . ')' : $inv->billing_address['first_name'] . ' ' . $inv->billing_address['last_name'] }}
                            </div>
                            <div class="text-[10px] text-gray-500 uppercase tracking-widest font-black mt-1">{{ $inv->invoice_date->format('d.m.Y') }}</div>
                        </td>
                        <td @class(['px-4 py-5 text-right font-serif font-bold text-lg align-middle whitespace-nowrap', 'text-white' => $inv->total >= 0, 'text-red-400' => $inv->total < 0])>
                            {{ number_format($inv->total / 100, 2, ',', '.') }} €
                        </td>
                        <td class="px-4 py-5 text-center align-middle">
                            @if($inv->status == 'paid' && $inv->type !== 'cancellation')
                                <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 shadow-inner">Final</span>
                            @elseif($inv->status == 'cancelled' || $inv->type === 'cancellation')
                                <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest bg-red-500/10 text-red-400 border border-red-500/30 shadow-inner">Storniert</span>
                            @elseif($inv->status == 'draft')
                                <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest bg-amber-500/10 text-amber-400 border border-amber-500/30 shadow-inner">Entwurf</span>
                            @else
                                <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest bg-blue-500/10 text-blue-400 border border-blue-500/30 shadow-inner">Offen</span>
                            @endif
                        </td>
                        <td class="px-6 sm:px-8 py-5 text-right align-middle">
                            <div class="flex justify-end items-center gap-4 opacity-100 lg:opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <button wire:click="downloadPdf('{{ $inv->id }}')" class="p-2 bg-gray-950 border border-gray-800 text-gray-400 rounded-xl hover:text-white hover:border-gray-600 transition-all shadow-inner" title="PDF Kopie laden">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </button>
                                @if($inv->is_e_invoice)
                                    <button wire:click="downloadXml('{{ $inv->id }}')" class="text-blue-400 hover:text-blue-300 text-[10px] font-black uppercase tracking-widest border-b border-blue-400/30 pb-0.5 hover:border-blue-300 transition-colors" title="ZUGFeRD XML laden">XML</button>
                                @endif
                                @if($inv->status === 'draft')
                                    <button wire:click="editDraft('{{ $inv->id }}')" class="text-amber-400 hover:text-amber-300 text-[10px] font-black uppercase tracking-widest border-b border-amber-400/30 pb-0.5 hover:border-amber-300 transition-colors">Edit</button>
                                    <button wire:confirm="Entwurf wirklich löschen? Dies kann nicht rückgängig gemacht werden." wire:click="deleteDraft('{{ $inv->id }}')" class="text-red-500 hover:text-red-400 text-[10px] font-black uppercase tracking-widest border-b border-red-500/30 pb-0.5 hover:border-red-400 transition-colors">Löschen</button>
                                @endif
                                @if($inv->status !== 'cancelled' && $inv->status !== 'draft' && $inv->type !== 'cancellation')
                                    <button wire:confirm="Rechnung wirklich stornieren? Dies erstellt eine Gutschrift." wire:click="cancelInvoice('{{ $inv->id }}')" class="text-red-500 hover:text-red-400 text-[10px] font-black uppercase tracking-widest border-b border-red-500/30 pb-0.5 hover:border-red-400 transition-colors">Storno</button>
                                @endif
                                <button wire:click="$dispatch('openInvoicePreview', { id: '{{ $inv->id }}' })" class="text-primary hover:text-white text-[10px] font-black uppercase tracking-widest border-b border-primary/30 pb-0.5 hover:border-white transition-colors">Vorschau</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-16 text-center text-gray-500 font-serif italic text-lg">Keine Belege gefunden.</td></tr>
                @endforelse

                {{-- Mobile Slot --}}
                <x-slot name="mobileSlot">
                    @foreach($invoices as $inv)
                        <div @class(['p-5 active:bg-gray-800/40 transition-colors border-b border-gray-800 last:border-b-0', 'bg-red-900/10 border-l-4 border-l-red-500' => $inv->status === 'cancelled' || $inv->type === 'cancellation', 'border-l-4 border-l-transparent' => !($inv->status === 'cancelled' || $inv->type === 'cancellation')])>
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex flex-col gap-1.5">
                                    <span class="font-mono font-bold text-gray-300 text-base flex items-center gap-2 tracking-wide">
                                        {{ $inv->invoice_number }}
                                        @if($inv->is_e_invoice) <span class="text-[8px] bg-blue-500/10 border border-blue-500/30 text-blue-400 px-1.5 py-0.5 rounded shadow-sm font-black">XML</span> @endif
                                    </span>
                                    <span class="text-[9px] text-gray-500 uppercase font-black tracking-widest">{{ $inv->invoice_date->format('d.m.Y') }}</span>
                                    @if($inv->type === 'cancellation' && $inv->parent_id)
                                        <span class="text-[8px] text-red-400 font-black uppercase tracking-widest mt-1 flex items-center gap-1.5">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                                            Zu: {{ \App\Models\Invoice::find($inv->parent_id)?->invoice_number }}
                                        </span>
                                    @endif
                                </div>
                                <span @class([
                                            'px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest border shadow-inner',
                                            'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' => $inv->status == 'paid' && $inv->type !== 'cancellation',
                                            'bg-red-500/10 text-red-400 border-red-500/30' => $inv->status == 'cancelled' || $inv->type === 'cancellation',
                                            'bg-amber-500/10 text-amber-400 border-amber-500/30' => $inv->status == 'draft',
                                            'bg-blue-500/10 text-blue-400 border-blue-500/30' => $inv->status != 'paid' && $inv->status != 'cancelled' && $inv->status != 'draft'
                                        ])>
                                            {{ $inv->status === 'cancelled' || $inv->type === 'cancellation' ? 'storniert' : $inv->status }}
                                        </span>
                            </div>

                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4 mt-2">
                                <div class="min-w-0 pr-4">
                                    <div class="text-sm font-bold text-white truncate">{{ $inv->billing_address['last_name'] }}</div>
                                    <div @class(['text-xl font-serif font-bold mt-1', 'text-primary' => $inv->total >= 0, 'text-red-400' => $inv->total < 0])>{{ number_format($inv->total / 100, 2, ',', '.') }} €</div>
                                </div>
                                <div class="flex flex-wrap gap-2 w-full sm:w-auto mt-2 sm:mt-0">
                                    <button wire:click="downloadPdf('{{ $inv->id }}')" class="p-3 bg-gray-950 rounded-xl text-gray-400 border border-gray-800 shadow-inner hover:text-white hover:border-gray-600 transition-colors">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </button>
                                    @if($inv->status === 'draft')
                                        <button wire:click="editDraft('{{ $inv->id }}')" class="px-4 py-3 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded-xl text-[9px] font-black uppercase tracking-widest shadow-inner">Edit</button>
                                        <button wire:confirm="Löschen?" wire:click="deleteDraft('{{ $inv->id }}')" class="px-4 py-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl text-[9px] font-black uppercase tracking-widest shadow-inner">Del</button>
                                    @endif
                                    <button wire:click="$dispatch('openInvoicePreview', { id: '{{ $inv->id }}' })" class="px-4 py-3 bg-primary/10 border border-primary/20 text-primary rounded-xl text-[9px] font-black uppercase tracking-widest shadow-inner flex-1 sm:flex-none text-center">Vorschau</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </x-slot>
            </x-table.master>
        </div>
    @endif
</div>

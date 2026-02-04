<div class="p-2 md:p-6 bg-gray-50 min-h-screen" x-data="{ draftBtnText: 'Entwurf speichern' }" x-on:reset-draft-success.window="setTimeout(() => { $wire.draftSuccess = false }, 3000)">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold font-serif text-gray-800">Rechnungsverwaltung</h2>
            <p class="text-sm text-gray-500">Shop-Bestellungen und manuelle Belege.</p>
        </div>
        <div class="flex flex-wrap gap-2 w-full md:w-auto">
            <button wire:click="toggleManualCreate" class="flex-1 md:flex-none bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-black transition shadow-sm text-sm font-bold uppercase">
                {{ $isCreatingManual ? 'Zurück zur Liste' : '+ Rechnung erstellen' }}
            </button>
            <button wire:click="generateForPaidOrders" wire:loading.attr="disabled" class="flex-1 md:flex-none bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark shadow-sm text-sm font-bold uppercase">
                <span wire:loading.remove>Bulk-Action</span>
                <span wire:loading>...</span>
            </button>
        </div>
    </div>

    @if($isCreatingManual)
        @include('livewire.shop.invoice-partials.creating-manual')
    @else
        {{-- Toolbar & Suche --}}
        <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col md:flex-row gap-4 mb-4 shadow-sm items-center">
            <div class="relative flex-1 w-full md:max-w-md">
                <input type="text" wire:model.live="search" placeholder="Suche nach Nummer oder Name..." class="w-full pl-10 border-gray-300 rounded-lg text-sm text-gray-900 focus:ring-primary focus:border-primary">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2"/></svg>
                </div>
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <select wire:model.live="filterType" class="flex-1 md:flex-none border-gray-300 rounded-lg text-sm text-gray-900 bg-gray-50 p-2 focus:ring-primary">
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

        <x-table.master
            :headers="$headers"
            :rows="$invoices"
            :sortField="$sortField"
            :sortDirection="$sortDirection"
        >
            {{-- Desktop Content --}}
            @forelse($invoices as $inv)
                <tr @class(['hover:bg-gray-50 transition-colors group', 'bg-red-50/30' => $inv->status === 'cancelled' || $inv->type === 'cancellation'])>
                    <td class="px-6 py-4 font-mono font-bold text-gray-900">
                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                {{ $inv->invoice_number }}
                                @if($inv->is_e_invoice)
                                    <span class="bg-blue-100 text-blue-600 text-[8px] px-1 rounded uppercase font-black">E</span>
                                @endif
                            </div>
                            @if($inv->type === 'cancellation' && $inv->parent_id)
                                <div class="text-[10px] text-red-500 uppercase font-bold mt-1">
                                    Zu: {{ \App\Models\Invoice::find($inv->parent_id)?->invoice_number ?? 'Unbekannt' }}
                                </div>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-gray-900 font-medium">
                            {{ $inv->billing_address['company'] ? $inv->billing_address['company'] . ' (' . $inv->billing_address['last_name'] . ')' : $inv->billing_address['first_name'] . ' ' . $inv->billing_address['last_name'] }}
                        </div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-tighter">{{ $inv->invoice_date->format('d.m.Y') }}</div>
                    </td>
                    <td @class(['px-6 py-4 text-right font-bold text-base tracking-tighter', 'text-red-600' => $inv->total < 0])>
                        {{ number_format($inv->total / 100, 2, ',', '.') }} €
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($inv->status == 'paid' && $inv->type !== 'cancellation')
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-green-100 text-green-700 border border-green-200">Final</span>
                        @elseif($inv->status == 'cancelled' || $inv->type === 'cancellation')
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-red-100 text-red-700 border border-red-200">Storniert</span>
                        @elseif($inv->status == 'draft')
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-amber-50 text-amber-700 border border-amber-200">Entwurf</span>
                        @else
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-blue-50 text-blue-700 border border-blue-200">Offen</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button wire:click="downloadPdf('{{ $inv->id }}')" class="p-1 text-gray-400 hover:text-primary transition" title="PDF Kopie">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </button>
                            @if($inv->status === 'draft')
                                <button wire:click="editDraft('{{ $inv->id }}')" class="text-amber-600 font-black text-xs uppercase hover:underline">Bearbeiten</button>
                            @endif
                            @if($inv->status !== 'cancelled' && $inv->status !== 'draft' && $inv->type !== 'cancellation')
                                <button wire:confirm="Rechnung wirklich stornieren? Dies erstellt eine Gutschrift." wire:click="cancelInvoice('{{ $inv->id }}')" class="text-red-600 font-black text-xs uppercase hover:underline">Stornieren</button>
                            @endif
                            <button wire:click="$dispatch('openInvoicePreview', { id: '{{ $inv->id }}' })" class="text-primary font-black text-xs uppercase hover:underline">Vorschau</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-10 text-center text-gray-400 italic">Keine Belege gefunden.</td></tr>
            @endforelse

            {{-- Mobile Slot --}}
            <x-slot name="mobileSlot">
                @foreach($invoices as $inv)
                    <div @class(['p-4 active:bg-gray-50 transition-colors border-b last:border-b-0', 'bg-red-50/30' => $inv->status === 'cancelled' || $inv->type === 'cancellation'])>
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex flex-col">
                                <span class="font-mono font-bold text-gray-900 text-sm flex items-center gap-1">
                                    {{ $inv->invoice_number }}
                                    @if($inv->is_e_invoice) <span class="text-[8px] bg-blue-100 text-blue-600 px-1 rounded">E</span> @endif
                                </span>
                                <span class="text-[10px] text-gray-400 uppercase font-bold">{{ $inv->invoice_date->format('d.m.Y') }}</span>
                                @if($inv->type === 'cancellation' && $inv->parent_id)
                                    <span class="text-[8px] text-red-500 font-bold uppercase">Gutschrift zu {{ \App\Models\Invoice::find($inv->parent_id)?->invoice_number }}</span>
                                @endif
                            </div>
                            <span @class([
                                'px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border',
                                'bg-green-100 text-green-800' => $inv->status == 'paid' && $inv->type !== 'cancellation',
                                'bg-red-100 text-red-800 border-red-200' => $inv->status == 'cancelled' || $inv->type === 'cancellation',
                                'bg-amber-100 text-amber-800 border-amber-200' => $inv->status == 'draft',
                                'bg-blue-100 text-blue-800' => $inv->status != 'paid' && $inv->status != 'cancelled' && $inv->status != 'draft'
                            ])>
                                {{ $inv->status === 'cancelled' || $inv->type === 'cancellation' ? 'storniert' : $inv->status }}
                            </span>
                        </div>

                        <div class="flex justify-between items-end">
                            <div>
                                <div class="text-sm font-bold text-gray-900">{{ $inv->billing_address['last_name'] }}</div>
                                <div @class(['text-base font-black mt-1', 'text-primary' => $inv->total >= 0, 'text-red-600' => $inv->total < 0])>{{ number_format($inv->total / 100, 2, ',', '.') }} €</div>
                            </div>
                            <div class="flex gap-2">
                                <button wire:click="downloadPdf('{{ $inv->id }}')" class="p-2 bg-gray-50 rounded-lg text-gray-500 border border-gray-100 shadow-sm">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </button>
                                @if($inv->status === 'draft')
                                    <button wire:click="editDraft('{{ $inv->id }}')" class="px-3 py-2 bg-amber-50 text-amber-600 rounded-lg text-xs font-black uppercase">Edit</button>
                                @endif
                                <button wire:click="$dispatch('openInvoicePreview', { id: '{{ $inv->id }}' })" class="px-3 py-2 bg-primary/10 text-primary rounded-lg text-xs font-black uppercase">Preview</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </x-slot>
        </x-table.master>
    @endif

    <livewire:shop.invoice-preview />
</div>

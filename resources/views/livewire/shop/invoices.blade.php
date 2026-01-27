<div>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold font-serif text-gray-800">Rechnungsverwaltung</h2>
            <button wire:click="generateForPaidOrders" wire:loading.attr="disabled" class="bg-primary text-white px-4 py-2 rounded hover:bg-primary-dark shadow-sm text-sm">
                <span wire:loading.remove>Offene Rechnungen generieren</span>
                <span wire:loading>Verarbeite...</span>
            </button>
        </div>

        {{-- Toolbar --}}
        <div class="bg-white p-4 rounded-t-xl border border-gray-200 flex gap-4">
            <input type="text" wire:model.live="search" placeholder="Rechnungsnr. oder Name..." class="border-gray-300 rounded-lg text-sm w-full md:w-64">
            <select wire:model.live="filterType" class="border-gray-300 rounded-lg text-sm">
                <option value="">Alle Typen</option>
                <option value="invoice">Rechnungen</option>
                <option value="cancellation">Stornos / Gutschriften</option>
            </select>
        </div>

        {{-- Tabelle --}}
        <div class="bg-white border-x border-b border-gray-200 shadow-sm overflow-x-auto rounded-b-xl">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-500 font-bold uppercase text-xs">
                <tr>
                    <th class="px-6 py-4">Nummer</th>
                    <th class="px-6 py-4">Datum</th>
                    <th class="px-6 py-4">Kunde</th>
                    <th class="px-6 py-4 text-right">Betrag</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4 text-right">Aktion</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($invoices as $inv)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-mono font-bold text-gray-900">
                            {{ $inv->invoice_number }}
                            @if($inv->isCreditNote())
                                <span class="text-xs text-red-500 block">zu {{ $inv->parent->invoice_number ?? '?' }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">{{ $inv->invoice_date->format('d.m.Y') }}</td>
                        <td class="px-6 py-4">
                            {{ $inv->billing_address['first_name'] }} {{ $inv->billing_address['last_name'] }}
                        </td>
                        <td class="px-6 py-4 text-right font-bold {{ $inv->isCreditNote() ? 'text-red-600' : 'text-gray-900' }}">
                            {{ number_format($inv->total / 100, 2, ',', '.') }} €
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($inv->status == 'paid')
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-bold">Bezahlt</span>
                            @elseif($inv->status == 'cancelled')
                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-bold">Storniert</span>
                            @else
                                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs font-bold">{{ ucfirst($inv->status) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button wire:click="$dispatch('openInvoicePreview', { id: '{{ $inv->id }}' })" class="text-primary hover:underline font-bold">
                                Vorschau
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">Keine Rechnungen gefunden.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="p-4">{{ $invoices->links() }}</div>
        </div>

        {{-- Modal für Vorschau wird unten eingebunden oder global --}}
        <livewire:shop.invoice-preview />
    </div>
</div>

<div>
    <div class="p-6">

        {{-- STATS HEADER --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-blue-50 rounded-full text-blue-600">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Bestellungen Gesamt</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-yellow-50 rounded-full text-yellow-600">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Offene Bestellungen</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['open'] }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-green-50 rounded-full text-green-600">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Umsatz Heute</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['revenue_today'] / 100, 2, ',', '.') }} €</p>
                </div>
            </div>
        </div>

        {{-- TOOLBAR --}}
        <div class="bg-white rounded-t-xl border border-gray-200 p-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="relative w-full md:w-96">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Bestellung suchen (Nr, Name, Email)..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <div class="flex gap-2 w-full md:w-auto overflow-x-auto">
                <select wire:model.live="statusFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
                    <option value="">Alle Status</option>
                    <option value="pending">Wartend</option>
                    <option value="processing">In Bearbeitung</option>
                    <option value="shipped">Versendet</option>
                    <option value="completed">Abgeschlossen</option>
                    <option value="cancelled">Storniert</option>
                </select>

                <select wire:model.live="paymentFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
                    <option value="">Alle Zahlungen</option>
                    <option value="paid">Bezahlt</option>
                    <option value="unpaid">Offen</option>
                </select>

                <select wire:model.live="dateFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
                    <option value="">Gesamtzeitraum</option>
                    <option value="today">Heute</option>
                    <option value="week">Diese Woche</option>
                    <option value="month">Dieser Monat</option>
                </select>
            </div>
        </div>

        {{-- TABELLE --}}
        <div class="bg-white border-x border-b border-gray-200 shadow-sm overflow-x-auto rounded-b-xl">
            <table class="w-full text-left border-collapse">
                <thead>
                <tr class="bg-gray-50/50 text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                    <th class="px-6 py-4 cursor-pointer hover:bg-gray-100" wire:click="sortBy('order_number')">Bestell-Nr.</th>
                    <th class="px-6 py-4 cursor-pointer hover:bg-gray-100" wire:click="sortBy('created_at')">Datum</th>
                    <th class="px-6 py-4">Kunde</th>
                    <th class="px-6 py-4 text-right">Summe</th>
                    <th class="px-6 py-4 text-center">Zahlung</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4 text-right">Aktionen</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($orders as $order)
                    <tr class="hover:bg-gray-50/50 transition-colors group">
                        <td class="px-6 py-4 font-mono text-sm font-bold text-gray-900">
                            <a href="{{ route('admin.orders.detail', $order->id) }}" class="text-primary hover:underline">
                                {{ $order->order_number }}
                            </a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $order->created_at->format('d.m.Y H:i') }}
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="font-medium text-gray-900">{{ $order->customer_name }}</div>
                            <div class="text-xs text-gray-400">{{ $order->email }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-right font-bold text-gray-900">
                            {{ number_format($order->total_price / 100, 2, ',', '.') }} €
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $order->payment_status_color }}">
                                {{ ucfirst($order->payment_status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $order->status_color }}">
                                {{ ucfirst($order->status) }}
                            </span>
                        </td>

                        {{-- QUICK ACTIONS --}}
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">

                                {{-- Status ändern Shortcut (Dropdown Simulation) --}}
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="p-1.5 rounded hover:bg-gray-100 text-gray-500" title="Status ändern">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    </button>
                                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-20 border border-gray-100 py-1" style="display: none;">
                                        <button wire:click="updateStatus('{{ $order->id }}', 'processing')" @click="open = false" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">In Bearbeitung</button>
                                        <button wire:click="updateStatus('{{ $order->id }}', 'shipped')" @click="open = false" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Versendet</button>
                                        <button wire:click="updateStatus('{{ $order->id }}', 'completed')" @click="open = false" class="block w-full text-left px-4 py-2 text-sm text-green-600 hover:bg-gray-50">Abgeschlossen</button>
                                    </div>
                                </div>

                                {{-- Mark Paid --}}
                                @if($order->payment_status !== 'paid')
                                    <button wire:click="markAsPaid('{{ $order->id }}')" class="p-1.5 rounded hover:bg-green-50 text-gray-500 hover:text-green-600" title="Als bezahlt markieren">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    </button>
                                @endif

                                {{-- View Detail --}}
                                <a href="{{ route('admin.orders.detail', $order->id) }}" class="p-1.5 rounded hover:bg-blue-50 text-gray-500 hover:text-blue-600" title="Details öffnen">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">Keine Bestellungen gefunden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    </div>
</div>

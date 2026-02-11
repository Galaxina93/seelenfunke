<div class="min-h-screen bg-gray-50 p-4 lg:p-6">

    {{--
        =================================================================
        ABSCHNITT 1: DETAIL ANSICHT
        (Wird angezeigt, wenn eine ID ausgewählt wurde via selectQuote)
        =================================================================
    --}}
    @if($selectedQuoteId)
        @php
            // Wir laden die Details direkt hier (oder man übergibt sie via Controller)
            $detailQuote = \App\Models\Quote\QuoteRequest::with('items')->find($selectedQuoteId);
        @endphp

        @if($detailQuote)
            <div class="max-w-5xl mx-auto">
                {{-- Header & Zurück Button --}}
                <div class="mb-6 flex items-center justify-between">
                    <button wire:click="closeDetail"
                            class="flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-gray-800 transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Zurück zur Übersicht
                    </button>

                    <div class="flex gap-2">
                        @if($detailQuote->status == 'open')
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800 border border-yellow-200 uppercase">Offen</span>
                        @elseif($detailQuote->status == 'converted')
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800 border border-green-200 uppercase">Angenommen</span>
                        @elseif($detailQuote->status == 'rejected')
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200 uppercase">Abgelehnt</span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {{-- LINKE SPALTE: DETAILS --}}
                    <div class="lg:col-span-2 space-y-6">

                        {{-- Karte: Positionen --}}
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 font-bold text-gray-800">
                                Positionen der Anfrage #{{ $detailQuote->quote_number }}
                            </div>
                            <div class="p-0 overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-gray-50 text-gray-500 font-medium border-b">
                                    <tr>
                                        <th class="px-6 py-3">Produkt</th>
                                        <th class="px-6 py-3 text-center">Menge</th>
                                        <th class="px-6 py-3 text-right">Einzel</th>
                                        <th class="px-6 py-3 text-right">Gesamt</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                    @foreach($detailQuote->items as $item)
                                        <tr>
                                            <td class="px-6 py-4">
                                                <div class="font-bold text-gray-900">{{ $item->product_name }}</div>
                                                @if(!empty($item->configuration))
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        @foreach($item->configuration as $key => $val)
                                                            <span class="mr-2">{{ $key }}: {{ Str::limit($val, 20) }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-center">{{ $item->quantity }}</td>
                                            <td class="px-6 py-4 text-right">{{ number_format($item->unit_price / 100, 2, ',', '.') }}
                                                €
                                            </td>
                                            <td class="px-6 py-4 text-right font-bold">{{ number_format($item->total_price / 100, 2, ',', '.') }}
                                                €
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- Summenblock --}}
                            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                                <div class="flex justify-end gap-6 text-sm">
                                    <div class="text-gray-500">Zwischensumme:</div>
                                    <div class="font-medium">{{ number_format($detailQuote->net_total / 100, 2, ',', '.') }}
                                        €
                                    </div>
                                </div>
                                <div class="flex justify-end gap-6 text-sm mt-1">
                                    <div class="text-gray-500">MwSt:</div>
                                    <div class="font-medium">{{ number_format($detailQuote->tax_total / 100, 2, ',', '.') }}
                                        €
                                    </div>
                                </div>
                                @if($detailQuote->shipping_price > 0)
                                    <div class="flex justify-end gap-6 text-sm mt-1">
                                        <div class="text-gray-500">Versand:</div>
                                        <div class="font-medium">{{ number_format($detailQuote->shipping_price / 100, 2, ',', '.') }}
                                            €
                                        </div>
                                    </div>
                                @endif
                                <div class="flex justify-end gap-6 text-lg font-bold text-primary mt-3 pt-3 border-t border-gray-200">
                                    <div>Gesamt:</div>
                                    <div>{{ number_format($detailQuote->gross_total / 100, 2, ',', '.') }} €</div>
                                </div>
                            </div>
                        </div>

                        {{-- AKTIONEN / BUTTONS (Nur wenn Offen) --}}
                        @if($detailQuote->status === 'open')
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <h3 class="font-bold text-gray-800 mb-2">Angebot annehmen & Bestellung erstellen</h3>
                                <p class="text-sm text-gray-500 mb-4">Wie soll der Kunde bezahlen?</p>

                                <div class="flex flex-col sm:flex-row gap-3">
                                    {{-- OPTION 1: Klassische Rechnung --}}
                                    <button wire:click="convertToOrder('{{ $detailQuote->id }}', 'invoice')"
                                            wire:loading.attr="disabled"
                                            class="flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-bold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm group">
                                        <svg class="w-5 h-5 text-gray-400 group-hover:text-gray-600" fill="none"
                                             viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        Als Rechnung erstellen
                                    </button>

                                    {{-- OPTION 2: Online-Zahlung --}}
                                    <button wire:click="convertToOrder('{{ $detailQuote->id }}', 'stripe_link')"
                                            wire:loading.attr="disabled"
                                            class="flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-bold text-white bg-green-600 border border-green-600 rounded-lg hover:bg-green-700 transition shadow-sm shadow-green-200">
                                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24"
                                             stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        Bestellung + Zahlungslink
                                    </button>
                                </div>

                                {{-- Loading Indicator --}}
                                <div wire:loading wire:target="convertToOrder"
                                     class="mt-2 text-center text-xs text-primary font-bold animate-pulse">
                                    Verarbeite Bestellung... Bitte warten...
                                </div>
                            </div>
                        @endif

                    </div>

                    {{-- RECHTE SPALTE: KUNDE --}}
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="font-bold text-gray-800 mb-4 pb-2 border-b">Kundendaten</h3>

                            <div class="space-y-3 text-sm">
                                <div>
                                    <span class="block text-xs text-gray-400 uppercase">Name</span>
                                    <span class="font-medium text-gray-900">{{ $detailQuote->first_name }} {{ $detailQuote->last_name }}</span>
                                </div>
                                <div>
                                    <span class="block text-xs text-gray-400 uppercase">Email</span>
                                    <a href="mailto:{{ $detailQuote->email }}"
                                       class="text-blue-600 hover:underline">{{ $detailQuote->email }}</a>
                                </div>
                                @if($detailQuote->company)
                                    <div>
                                        <span class="block text-xs text-gray-400 uppercase">Firma</span>
                                        <span class="font-medium text-gray-900">{{ $detailQuote->company }}</span>
                                    </div>
                                @endif
                                <div>
                                    <span class="block text-xs text-gray-400 uppercase">Adresse</span>
                                    <span class="block text-gray-700">
                                        {{ $detailQuote->street }} {{ $detailQuote->house_number }}<br>
                                        {{ $detailQuote->postal }} {{ $detailQuote->city }}<br>
                                        {{ $detailQuote->country }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        @if($detailQuote->admin_notes)
                            <div class="bg-yellow-50 rounded-xl border border-yellow-100 p-4">
                                <span class="block text-xs text-yellow-600 uppercase font-bold mb-1">Kunden-Anmerkung:</span>
                                <p class="text-sm text-yellow-800 italic">"{{ $detailQuote->admin_notes }}"</p>
                            </div>
                        @endif

                        @if($detailQuote->is_express)
                            <div class="bg-red-50 rounded-xl border border-red-100 p-4">
                                <span class="block text-xs text-red-600 uppercase font-bold mb-1">🔥 Express Service</span>
                                @if($detailQuote->deadline)
                                    <p class="text-sm text-red-800 font-bold">
                                        Deadline: {{ $detailQuote->deadline->format('d.m.Y') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="text-center p-10 text-gray-500">Anfrage nicht gefunden.</div>
            <button wire:click="closeDetail" class="mx-auto block text-blue-600 underline">Zurück</button>
        @endif

        {{--
            =================================================================
            ABSCHNITT 2: LIST VIEW
            (Dein ursprünglicher Code, jetzt im else-Zweig)
            =================================================================
        --}}
    @else

        {{-- STATS HEADER --}}
        @php
            $stats = [
                'total' => \App\Models\Quote\QuoteRequest::count(),
                'open' => \App\Models\Quote\QuoteRequest::where('status', 'open')->count(),
                'converted' => \App\Models\Quote\QuoteRequest::where('status', 'converted')->count(),
            ];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-blue-50 rounded-full text-blue-600">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Anfragen Gesamt</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-yellow-50 rounded-full text-yellow-600">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Offene Anfragen</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['open'] }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-green-50 rounded-full text-green-600">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Angenommen</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['converted'] }}</p>
                </div>
            </div>
        </div>

        {{-- TOOLBAR --}}
        <div class="bg-white rounded-t-xl border border-gray-200 p-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="relative w-full md:w-96">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche (Nr, Name, Firma)..."
                       class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <div class="flex gap-2 w-full md:w-auto overflow-x-auto">
                <select wire:model.live="filterStatus"
                        class="w-full md:w-auto px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary">
                    <option value="">Alle Status</option>
                    <option value="open">Offen</option>
                    <option value="converted">Angenommen</option>
                    <option value="rejected">Abgelehnt</option>
                </select>
            </div>
        </div>

        {{-- DESKTOP TABELLE --}}
        <div class="hidden md:block bg-white border-x border-b border-gray-200 shadow-sm overflow-x-auto rounded-b-xl">
            <table class="w-full text-left border-collapse">
                <thead>
                <tr class="bg-gray-50/50 text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                    <th class="px-6 py-4">Anfrage-Nr.</th>
                    <th class="px-6 py-4">Datum</th>
                    <th class="px-6 py-4">Kunde</th>
                    <th class="px-6 py-4 text-right">Summe</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4 text-right">Aktionen</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($quotes as $quote)
                    <tr class="hover:bg-gray-50/50 transition-colors group text-sm cursor-pointer"
                        wire:click="selectQuote('{{ $quote->id }}')">
                        <td class="px-6 py-4 font-mono font-bold text-gray-900 text-primary hover:underline">
                            {{ $quote->quote_number }}
                        </td>
                        <td class="px-6 py-4 text-gray-500">
                            {{ $quote->created_at->format('d.m.Y H:i') }}
                            @if($quote->is_express)
                                <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800 uppercase tracking-wide">Express</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $quote->first_name }} {{ $quote->last_name }}</div>
                            <div class="text-xs text-gray-400">{{ $quote->company ?? $quote->email }}</div>
                        </td>
                        <td class="px-6 py-4 text-right font-bold text-gray-900">
                            {{ number_format($quote->gross_total / 100, 2, ',', '.') }} €
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($quote->status == 'open')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">Offen</span>
                            @elseif($quote->status == 'converted')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">Angenommen</span>
                            @elseif($quote->status == 'rejected')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">Abgelehnt</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-blue-600 hover:underline text-xs font-bold">Öffnen</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">Keine Anfragen gefunden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- MOBILE KACHELN (Karten-Layout) --}}
        <div class="md:hidden">
            @forelse($quotes as $quote)
                <div wire:click="selectQuote('{{ $quote->id }}')"
                     class="bg-white p-4 border-x border-b border-gray-200 first:border-t first:rounded-t-none last:rounded-b-xl shadow-sm active:bg-gray-50 transition-colors cursor-pointer">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex flex-col">
                            <span class="font-mono font-bold text-primary text-sm">{{ $quote->quote_number }}</span>
                            <span class="text-[10px] text-gray-400 italic">{{ $quote->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div>
                            @if($quote->status == 'open')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-800 border border-yellow-200 uppercase">Offen</span>
                            @elseif($quote->status == 'converted')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800 border border-green-200 uppercase">Angenommen</span>
                            @elseif($quote->status == 'rejected')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 border border-red-200 uppercase">Abgelehnt</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex justify-between items-end">
                        <div class="truncate pr-4">
                            <div class="font-bold text-gray-900 text-sm truncate">{{ $quote->first_name }} {{ $quote->last_name }}</div>
                            <div class="text-[11px] text-gray-500 truncate">{{ $quote->company ?? $quote->email }}</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            @if($quote->is_express)
                                <div class="mb-1"><span
                                            class="px-1.5 py-0.5 rounded text-[9px] font-black bg-red-100 text-red-800 uppercase tracking-tighter border border-red-200">Express</span>
                                </div>
                            @endif
                            <div class="font-bold text-gray-900 text-base">{{ number_format($quote->gross_total / 100, 2, ',', '.') }}
                                €
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white p-12 text-center text-gray-500 border border-gray-200 rounded-xl">
                    Keine Anfragen gefunden.
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $quotes->links() }}
        </div>

    @endif

</div>

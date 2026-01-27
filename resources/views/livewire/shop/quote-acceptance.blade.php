<div class="min-h-screen bg-gray-50 flex flex-col items-center py-12 px-4 sm:px-6 lg:px-8">

    {{-- LOGO AREA (optional, falls Layout kein Logo hat) --}}
    {{-- <div class="mb-8"><img src="..." class="h-12 w-auto"></div> --}}

    <div class="w-full max-w-3xl">

        {{-- VIEW: ERROR --}}
        @if($viewState === 'error')
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-red-100 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Hoppla!</h2>
                <p class="text-gray-600 mb-6">{{ $errorMessage }}</p>
                <a href="/" class="text-[#C5A059] font-bold hover:underline">Zur Startseite</a>
            </div>

            {{-- VIEW: SUCCESS ACCEPTED --}}
        @elseif($viewState === 'success_accepted')
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-green-100 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
                    <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Vielen Dank!</h2>
                <p class="text-gray-600 mb-6">
                    Das Angebot wurde verbindlich angenommen. Sie erhalten in Kürze eine Bestellbestätigung per E-Mail.
                </p>
                <a href="/" class="inline-block bg-[#C5A059] text-white px-6 py-3 rounded-full font-bold hover:bg-[#b08d4b] transition">
                    Zurück zur Website
                </a>
            </div>

            {{-- VIEW: SUCCESS REJECTED --}}
        @elseif($viewState === 'success_rejected')
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100 mb-6">
                    <svg class="h-8 w-8 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Angebot abgelehnt</h2>
                <p class="text-gray-600 mb-6">
                    Sie haben das Angebot abgelehnt. Falls Sie es sich anders überlegen, können Sie ein neues Angebot erstellen.
                </p>
                <div class="flex justify-center gap-4">
                    <a href="/" class="text-gray-500 hover:text-gray-900 font-bold">Startseite</a>
                    <span class="text-gray-300">|</span>
                    <button wire:click="editQuote" class="text-[#C5A059] font-bold hover:underline">Neues Angebot kalkulieren</button>
                </div>
            </div>

            {{-- VIEW: DASHBOARD (MAIN) --}}
        @else
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">

                {{-- Header --}}
                <div class="bg-[#C5A059]/10 p-6 border-b border-[#C5A059]/20 text-center">
                    <h1 class="text-2xl font-serif font-bold text-gray-900">Ihr persönliches Angebot</h1>
                    <p class="text-[#C5A059] font-medium mt-1">Nr. {{ $quote->quote_number }}</p>
                </div>

                {{-- Status Bar --}}
                <div class="bg-gray-50 px-6 py-3 border-b border-gray-100 flex justify-between items-center text-sm">
                    <span class="text-gray-500">Erstellt am {{ $quote->created_at->format('d.m.Y') }}</span>
                    @if($quote->expires_at->isPast())
                        <span class="text-red-600 font-bold flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Abgelaufen
                        </span>
                    @else
                        <span class="text-green-600 font-bold flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Gültig bis {{ $quote->expires_at->format('d.m.Y') }}
                        </span>
                    @endif
                </div>

                <div class="p-6 sm:p-10 space-y-8">

                    {{-- Introduction --}}
                    <div class="text-center space-y-2">
                        <p class="text-lg text-gray-700">
                            Hallo <strong>{{ $quote->first_name }} {{ $quote->last_name }}</strong>,
                        </p>
                        <p class="text-gray-600">
                            hier können Sie Ihr Angebot prüfen, anpassen oder direkt annehmen.
                        </p>
                    </div>

                    {{-- Items List --}}
                    <div class="border rounded-xl overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3">Produkt</th>
                                <th class="px-4 py-3 text-center">Menge</th>
                                <th class="px-4 py-3 text-right">Summe</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($quote->items as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-900">{{ $item->product_name }}</div>
                                        @if(!empty($item->configuration['text']))
                                            <div class="text-xs text-gray-500 mt-0.5">Gravur: "{{ $item->configuration['text'] }}"</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">{{ $item->quantity }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 border-t border-gray-200">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-right font-bold text-gray-600">Gesamtsumme (Brutto)</td>
                                <td class="px-4 py-3 text-right font-bold text-lg text-[#C5A059]">
                                    {{ number_format($quote->gross_total / 100, 2, ',', '.') }} €
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Actions Area --}}
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                        <h3 class="font-bold text-gray-800 mb-4 text-center">Wie möchten Sie fortfahren?</h3>

                        <div class="flex flex-col sm:flex-row gap-4 justify-center">

                            {{-- 1. Bearbeiten --}}
                            <button wire:click="editQuote" wire:loading.attr="disabled" class="flex-1 flex items-center justify-center gap-2 px-6 py-3 border-2 border-blue-100 bg-white text-blue-600 rounded-lg hover:bg-blue-50 hover:border-blue-200 transition font-bold text-sm shadow-sm group">
                                <svg class="w-5 h-5 text-blue-400 group-hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                <span>Angebot bearbeiten</span>
                            </button>

                            {{-- 2. Annehmen --}}
                            @if($quote->isValid())
                                <button wire:click="acceptQuote" wire:loading.attr="disabled" wire:confirm="Möchten Sie das Angebot jetzt verbindlich annehmen?" class="flex-[1.5] flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-[#C5A059] to-[#b08d4b] text-white rounded-lg hover:shadow-lg hover:scale-[1.02] transition font-bold text-sm shadow-md">
                                    <svg class="w-5 h-5 text-white/90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Jetzt verbindlich annehmen</span>
                                </button>
                            @else
                                <button disabled class="flex-[1.5] flex items-center justify-center gap-2 px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed font-bold text-sm">
                                    Nicht mehr gültig
                                </button>
                            @endif

                        </div>

                        {{-- 3. Ablehnen (Dezent) --}}
                        @if($quote->isValid())
                            <div class="mt-6 text-center">
                                <button wire:click="rejectQuote" wire:confirm="Möchten Sie das Angebot wirklich ablehnen?" class="text-xs text-gray-400 hover:text-red-500 hover:underline transition">
                                    Kein Interesse? Angebot ablehnen.
                                </button>
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        @endif
    </div>
</div>

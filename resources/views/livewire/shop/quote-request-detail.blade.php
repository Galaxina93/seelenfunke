<div class="p-6 max-w-5xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <button wire:click="closeDetail" class="text-sm text-gray-500 hover:text-gray-900 flex items-center gap-1">
            &larr; Zurück zur Übersicht
        </button>
        <div class="flex gap-3">
            @if($quote->status === 'open')
                <button wire:click="markAsRejected('{{ $quote->id }}')" class="px-4 py-2 border border-red-200 text-red-600 rounded bg-white hover:bg-red-50 text-sm font-bold">
                    Ablehnen
                </button>
                <button wire:click="convertToOrder('{{ $quote->id }}')"
                        wire:confirm="Möchtest du diese Anfrage wirklich in eine verbindliche Bestellung umwandeln? Der Kunde erhält eine Bestätigung."
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm font-bold shadow-sm">
                    Angebot annehmen & Bestellung anlegen
                </button>
            @elseif($quote->status === 'converted')
                <div class="flex items-center gap-2 text-green-700 bg-green-50 px-4 py-2 rounded border border-green-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="font-bold text-sm">Wurde in Bestellung umgewandelt</span>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Linke Spalte: Details --}}
        <div class="md:col-span-2 space-y-6">
            {{-- Kopfdaten --}}
            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 mb-1">Anfrage {{ $quote->quote_number }}</h1>
                        <p class="text-sm text-gray-500">Erstellt am {{ $quote->created_at->format('d.m.Y H:i') }}</p>
                    </div>
                    <div class="text-right">
                        @if($quote->is_express)
                            <div class="text-red-600 font-bold text-sm uppercase mb-1">🔥 Express Anfrage</div>
                            <div class="text-xs text-gray-600">Deadline: {{ $quote->deadline ? $quote->deadline->format('d.m.Y') : '-' }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Positionen --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 border-b border-gray-200 text-xs uppercase text-gray-500 font-bold">
                    <tr>
                        <th class="px-4 py-3">Artikel</th>
                        <th class="px-4 py-3 text-center">Menge</th>
                        <th class="px-4 py-3 text-right">Einzel</th>
                        <th class="px-4 py-3 text-right">Gesamt</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach($quote->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-bold text-gray-900">{{ $item->product_name }}</div>

                                {{-- Konfigurations-Details & Dateien --}}
                                @if(!empty($item->configuration))
                                    <div class="mt-2 text-xs text-gray-600 bg-gray-50 p-2 rounded">
                                        @if(!empty($item->configuration['text']))
                                            <div class="mb-1"><strong>Gravur:</strong> "{{ $item->configuration['text'] }}"</div>
                                        @endif

                                        {{-- DATEIEN DOWNLOAD --}}
                                        @if(!empty($item->configuration['files']))
                                            <div class="mt-2 border-t border-gray-200 pt-2">
                                                <span class="font-bold block mb-1">Dateien:</span>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach($item->configuration['files'] as $file)
                                                        <a href="{{ asset('storage/'.$file) }}" target="_blank" class="flex items-center gap-1 bg-white border border-gray-300 px-2 py-1 rounded text-blue-600 hover:text-blue-800 hover:border-blue-400">
                                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                            {{ basename($file) }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                        @if(!empty($item->configuration['logo_storage_path']) && empty($item->configuration['files']))
                                            <div class="mt-2">
                                                <a href="{{ asset('storage/'.$item->configuration['logo_storage_path']) }}" target="_blank" class="text-blue-600 underline">Logo ansehen</a>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($item->unit_price / 100, 2, ',', '.') }} €</td>
                            <td class="px-4 py-3 text-right font-bold">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-gray-200">
                    <tr>
                        <td colspan="3" class="px-4 py-2 text-right font-bold">Gesamtsumme (Brutto):</td>
                        <td class="px-4 py-2 text-right font-bold text-lg">{{ number_format($quote->gross_total / 100, 2, ',', '.') }} €</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Rechte Spalte: Kunde --}}
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                <h3 class="font-bold text-gray-900 mb-4 border-b border-gray-100 pb-2">Kontaktdaten</h3>

                @if($quote->is_guest)
                    <div class="mb-4 bg-yellow-50 text-yellow-800 px-3 py-2 rounded text-xs font-bold flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Gast-Anfrage (Kein Account)
                    </div>
                @else
                    <div class="mb-4 bg-blue-50 text-blue-800 px-3 py-2 rounded text-xs font-bold flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Registrierter Kunde
                    </div>
                @endif

                <div class="space-y-3 text-sm text-gray-700">
                    <div>
                        <span class="block text-xs text-gray-400">Name</span>
                        {{ $quote->first_name }} {{ $quote->last_name }}
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400">Firma</span>
                        {{ $quote->company ?: '-' }}
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400">E-Mail</span>
                        <a href="mailto:{{ $quote->email }}" class="text-primary hover:underline">{{ $quote->email }}</a>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400">Telefon</span>
                        {{ $quote->phone ?: '-' }}
                    </div>
                </div>
            </div>

            {{-- Notizen --}}
            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                <h3 class="font-bold text-gray-900 mb-2">Interne Notizen</h3>
                <textarea class="w-full text-sm border-gray-300 rounded-lg text-gray-600" rows="4" placeholder="Notizen zur Anfrage..." readonly>{{ $quote->admin_notes }}</textarea>
            </div>
        </div>
    </div>
</div>

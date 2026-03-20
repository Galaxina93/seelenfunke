<div>
    {{-- Header Content Placeholder (if needed later) --}}
    
    {{-- Datentabelle --}}
    <div class="bg-gray-900 border border-white/5 shadow-2xl rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-800/50 text-gray-400 text-xs uppercase tracking-widest border-b border-white/10">
                        <th class="px-6 py-4 font-semibold">Datum</th>
                        <th class="px-6 py-4 font-semibold">Kunde</th>
                        <th class="px-6 py-4 font-semibold">Bestellnummer</th>
                        <th class="px-6 py-4 font-semibold">Zusatzinfos</th>
                        <th class="px-6 py-4 font-semibold text-center">Status</th>
                        <th class="px-6 py-4 font-semibold text-right">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-sm">
                    @forelse($revocations as $revocation)
                    <tr class="hover:bg-white/5 transition-colors {{ $revocation->status === 'processed' ? 'opacity-60' : '' }}">
                        <td class="px-6 py-4 text-gray-300 font-mono whitespace-nowrap">
                            {{ $revocation->created_at->format('d.m.Y H:i') }}
                        </td>
                        <td class="px-6 py-4 text-white">
                            <div class="font-bold">{{ $revocation->name }}</div>
                            <div class="text-gray-500 font-mono text-xs">{{ $revocation->email }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-mono font-medium bg-gray-800 text-primary border border-primary/20">
                                {{ $revocation->order_number }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-400">
                            {{ $revocation->items ?: '-' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($revocation->status === 'pending')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-500 border border-red-500/20 shadow-[0_0_10px_rgba(239,68,68,0.2)]">
                                    Offen
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-500/10 text-green-500 border border-green-500/20">
                                    Erledigt
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($revocation->status === 'pending')
                                <button wire:click="markAsProcessed({{ $revocation->id }})" class="text-xs font-semibold text-green-400 hover:text-green-300 transition uppercase tracking-wider bg-green-400/10 hover:bg-green-400/20 px-3 py-1.5 rounded-lg border border-green-400/20">
                                    Als Erledigt markieren
                                </button>
                            @else
                                <button wire:click="markAsPending({{ $revocation->id }})" class="text-xs font-semibold text-gray-400 hover:text-white transition uppercase tracking-wider bg-gray-800 hover:bg-gray-700 px-3 py-1.5 rounded-lg border border-gray-600">
                                    Wieder Öffnen
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <x-heroicon-o-face-smile class="w-12 h-12 mx-auto mb-3 opacity-50" />
                            <p class="font-serif italic text-lg text-gray-400">Bisher keine Widerrufe eingegangen.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- EDUCATIONAL SECTION --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-10">
        <div class="bg-gray-900/50 border border-gray-800 rounded-3xl p-6 lg:p-8 backdrop-blur-sm shadow-xl">
            <h3 class="text-white font-serif font-bold text-lg mb-4 flex items-center gap-2">
                <x-heroicon-o-light-bulb class="w-6 h-6 text-primary" />
                Tipps zum Umgang mit Widerrufen
            </h3>
            <ul class="space-y-4 text-sm text-gray-400">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-800 p-1 rounded-md text-primary"><x-heroicon-s-check class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-200 block mb-0.5">Sonderanfertigungen sind ausgeschlossen</strong>
                        Ist ein Gravur- oder Personalisierungsauftrag bereits in Produktion oder versendet, kannst du den Widerruf mit Verweis auf § 312g Abs. 2 Nr. 1 BGB rechtmäßig ablehnen. Lass dich nicht unter Druck setzen.
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-800 p-1 rounded-md text-primary"><x-heroicon-s-check class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-200 block mb-0.5">Teil-Widerrufe prüfen</strong>
                        Oft widerrufen Kunden nur einen Teil der Bestellung. Achte darauf, dass du beim Erfassen im System (Gutschrifterstellung) auch wirklich nur die retournierten Positionen gutschreibst.
                    </div>
                </li>
            </ul>
        </div>
        <div class="bg-gray-900/50 border border-gray-800 rounded-3xl p-6 lg:p-8 backdrop-blur-sm shadow-xl">
            <h3 class="text-white font-serif font-bold text-lg mb-4 flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-emerald-500" />
                Zusätzliche Hinweise
            </h3>
            <ul class="space-y-4 text-sm text-gray-400">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-800 p-1 rounded-md text-emerald-500"><x-heroicon-o-document-text class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-200 block mb-0.5">Automatischer Beweis</strong>
                        Der Kunde hat durch dieses System sofort ein rechtskräftiges Bestätigungs-PDF über den Eingang erhalten. Du musst diesen Eingang nicht noch einmal manuell bestätigen!
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-800 p-1 rounded-md text-emerald-500"><x-heroicon-s-arrows-right-left class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-200 block mb-0.5">Retourenlabel beilegen?</strong>
                        Bietest du kostenlose Rücksendungen an, vergiss nicht, in deiner Kommunikation mit dem Kunden direkt das DHL-Label als PDF-Anhang in der Mail mitzusenden.
                    </div>
                </li>
            </ul>
        </div>
    </div>

    {{-- WORKFLOW GRAPHIC (STEPS) --}}
    <div class="mt-8 bg-gray-900/50 border border-gray-800 rounded-3xl p-6 lg:p-8 backdrop-blur-sm shadow-xl mb-12">
        <h3 class="text-white font-serif font-bold text-lg mb-8 flex items-center gap-2">
            <x-heroicon-o-arrow-path-rounded-square class="w-6 h-6 text-primary" />
            Vorgeschriebener Prozessablauf bei Widerrufs-Eingang
        </h3>

        <div class="relative w-full overflow-hidden flex justify-center py-4">
            <svg viewBox="0 0 1000 200" class="w-full h-auto drop-shadow-2xl font-sans" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <marker id="arrowPrimary" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                        <path d="M 0 0 L 10 5 L 0 10 z" fill="#C5A059" />
                    </marker>
                    <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                        <feGaussianBlur stdDeviation="5" result="blur" />
                        <feComposite in="SourceGraphic" in2="blur" operator="over" />
                    </filter>
                    <linearGradient id="lineGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#4b5563" />
                        <stop offset="50%" stop-color="#C5A059" />
                        <stop offset="100%" stop-color="#10b981" />
                    </linearGradient>
                </defs>

                <!-- Connecting Path -->
                <path d="M 125 70 L 875 70" fill="none" stroke="url(#lineGrad)" stroke-width="2" stroke-dasharray="6" marker-end="url(#arrowPrimary)"/>

                <!-- Node 1: Eingang -->
                <g transform="translate(125, 70)">
                    <circle cx="0" cy="0" r="35" fill="#111827" stroke="#4b5563" stroke-width="2" />
                    <text x="0" y="5" fill="#9ca3af" font-size="20" font-weight="900" text-anchor="middle">1</text>
                    <text x="0" y="65" fill="#f3f4f6" font-size="14" font-weight="bold" text-anchor="middle">Bestätigung (Auto)</text>
                    <text x="0" y="85" fill="#6b7280" font-size="11" text-anchor="middle">Kunde hat sein PDF</text>
                    <text x="0" y="100" fill="#6b7280" font-size="11" text-anchor="middle">sofort per Mail erhalten.</text>
                </g>

                <!-- Node 2: Prüfung -->
                <g transform="translate(375, 70)">
                    <circle cx="0" cy="0" r="35" fill="#111827" stroke="#4b5563" stroke-width="2" />
                    <text x="0" y="5" fill="#9ca3af" font-size="20" font-weight="900" text-anchor="middle">2</text>
                    <text x="0" y="65" fill="#f3f4f6" font-size="14" font-weight="bold" text-anchor="middle">Rechtliche Prüfung</text>
                    <text x="0" y="85" fill="#6b7280" font-size="11" text-anchor="middle">Personalisierte Waren (§ 312g)?</text>
                    <text x="0" y="100" fill="#6b7280" font-size="11" text-anchor="middle">Oder normales Standard-Produkt?</text>
                </g>

                <!-- Node 3: Kommunikation (Highlight) -->
                <g transform="translate(625, 70)">
                    <circle cx="0" cy="0" r="42" fill="rgba(197,160,89, 0.15)" stroke="#C5A059" stroke-width="3" filter="url(#glow)" />
                    <text x="0" y="6" fill="#C5A059" font-size="24" font-weight="900" text-anchor="middle">3</text>
                    <text x="0" y="70" fill="#C5A059" font-size="15" font-weight="bold" text-anchor="middle">Mail an Kunde</text>
                    <text x="0" y="90" fill="#9ca3af" font-size="12" font-weight="bold" text-anchor="middle">Rücksende-Label senden</text>
                    <text x="0" y="105" fill="#6b7280" font-size="11" text-anchor="middle">oder Widerruf formal ablehnen.</text>
                </g>

                <!-- Node 4: Abschluss -->
                <g transform="translate(875, 70)">
                    <circle cx="0" cy="0" r="35" fill="rgba(16,185,129,0.1)" stroke="#10b981" stroke-width="2" />
                    <text x="0" y="5" fill="#10b981" font-size="20" font-weight="900" text-anchor="middle">4</text>
                    <text x="0" y="65" fill="#f3f4f6" font-size="14" font-weight="bold" text-anchor="middle">Abwicklung</text>
                    <text x="0" y="85" fill="#6b7280" font-size="11" text-anchor="middle">Retoure empfangen,</text>
                    <text x="0" y="100" fill="#6b7280" font-size="11" text-anchor="middle">Gutschrift stellen & Status "Erledigt".</text>
                </g>
            </svg>
        </div>
    </div>
</div>

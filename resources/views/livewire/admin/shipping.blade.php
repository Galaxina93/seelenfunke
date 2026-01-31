<div>

    <div class="space-y-8">

        {{-- DYNAMISCHES CSS FÜR DIE KARTE --}}
        <style>
            /* Standard Hover Effekt (Dunkler werden) */
            .jvm-region:hover {
                opacity: 0.75;
                cursor: pointer;
            }
            /* Dynamische Füllfarben aus PHP */
            {!! $mapVisuals['css'] !!}
        </style>

        {{-- HEADER STATS --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- ... Stats Boxen (Unverändert) ... --}}
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Versandzonen</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['zones'] }}</p>
                </div>
                <div class="p-3 bg-blue-50 rounded-full text-blue-600">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Abgedeckte Länder</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['countries_covered'] }}</p>
                </div>
                <div class="p-3 bg-green-50 rounded-full text-green-600">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Aktive Tarife</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['rates'] }}</p>
                </div>
                <div class="p-3 bg-amber-50 rounded-full text-amber-600">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
        </div>

        {{-- SUCCESS MESSAGE --}}
        @if(session()->has('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-sm">
                <p class="font-bold">Erfolg</p>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        {{-- WELTKARTE --}}
        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm overflow-hidden relative">
            <div class="flex flex-col md:flex-row justify-between items-start mb-6 gap-4">
                <div>
                    <h3 class="font-bold text-gray-900">Aktive Liefergebiete</h3>
                    <p class="text-sm text-gray-500">Visuelle Übersicht deiner Versandzonen.</p>
                </div>

                {{-- Legende --}}
                <div class="flex flex-wrap gap-3 max-w-lg justify-end">
                    @foreach($mapVisuals['legend'] as $name => $color)
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full shadow-sm" style="background-color: {{ $color }}"></span>
                            <span class="text-xs font-medium text-gray-600">{{ $name }}</span>
                        </div>
                    @endforeach
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-gray-200 border border-gray-300"></span>
                        <span class="text-xs font-medium text-gray-400">Kein Versand</span>
                    </div>
                </div>
            </div>

            <div id="shipping-map" class="w-full h-[400px] bg-gray-50 rounded-lg border border-gray-100"></div>

            {{-- ASSETS --}}
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap/dist/css/jsvectormap.min.css" />
            <script src="https://cdn.jsdelivr.net/npm/jsvectormap"></script>
            <script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>

            <script>
                document.addEventListener('livewire:initialized', () => {
                    const activeCodes = @json($mapVisuals['activeCodes']); // Liste der aktiven Codes für Tooltips

                    const map = new jsVectorMap({
                        selector: '#shipping-map',
                        map: 'world',
                        zoomButtons: true,
                        zoomOnScroll: false,
                        backgroundColor: 'transparent',

                        // Basis-Stil (Inaktiv)
                        regionStyle: {
                            initial: {
                                fill: '#E5E7EB', // Gray-200
                                stroke: '#ffffff',
                                strokeWidth: 1,
                            }
                        },

                        // WICHTIG: Keine 'series' Config hier!
                        // Die Farben kommen jetzt über das CSS oben.

                        // Tooltips anpassen
                        onRegionTooltipShow(event, tooltip, code) {
                            if (activeCodes[code]) {
                                // Zone gefunden
                                const zoneName = activeCodes[code];
                                tooltip.text(
                                    `<div class="text-center">
                                    <span class="font-bold block text-sm mb-1">${tooltip.text()}</span>
                                    <span class="text-xs bg-green-500 text-white px-2 py-0.5 rounded-full">${zoneName}</span>
                                </div>`,
                                    true // HTML aktivieren
                                );
                            } else {
                                // Inaktiv
                                tooltip.text(
                                    `<div class="text-center">
                                    <span class="font-bold block text-gray-400">${tooltip.text()}</span>
                                    <span class="text-xs text-gray-500 italic">Kein Versand</span>
                                </div>`,
                                    true
                                );
                            }
                        }
                    });
                });
            </script>
        </div>

        {{-- LIST & EDIT VIEWS (Wie vorher, unverändert) --}}
        @if($view === 'list')
            {{-- ... (Listen Code von vorher) ... --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Versandzonen verwalten</h2>
                        <p class="text-sm text-gray-500">Steuere hier, wohin du lieferst und zu welchem Preis.</p>
                    </div>
                    <button wire:click="createZone" class="bg-gray-900 hover:bg-black text-white px-4 py-2 rounded-lg font-bold text-sm transition shadow-lg flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        Neue Zone erstellen
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider font-bold">
                        <tr>
                            <th class="px-6 py-4">Zone Name</th>
                            <th class="px-6 py-4 text-center">Länder</th>
                            <th class="px-6 py-4 text-center">Tarife</th>
                            <th class="px-6 py-4 text-right">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($zones as $zone)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 font-bold text-gray-900">{{ $zone->name }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $zone->countries_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                        {{ $zone->rates_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button wire:click="editZone({{ $zone->id }})" class="text-indigo-600 hover:text-indigo-900 font-bold text-xs uppercase tracking-wide">Bearbeiten</button>
                                    <button wire:confirm="Zone wirklich löschen? Alle Tarife gehen verloren." wire:click="deleteZone({{ $zone->id }})" class="text-red-600 hover:text-red-900 font-bold text-xs uppercase tracking-wide">Löschen</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500 italic">
                                    Noch keine Versandzonen angelegt. Starte mit "Deutschland" oder "Weltweit".
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($view === 'edit' || $view === 'create')
            {{-- ... (Edit Code von vorher) ... --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- Linke Spalte --}}
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        <h3 class="font-bold text-gray-900 mb-4 border-b pb-2">1. Zone benennen</h3>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Name der Zone</label>
                            <input type="text" wire:model="zoneName" class="w-full rounded-lg border-gray-300 focus:ring-primary focus:border-primary" placeholder="z.B. Europäische Union">
                            @error('zoneName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="mt-4 flex justify-between">
                            <button wire:click="cancel" class="text-gray-500 text-sm hover:underline">Abbrechen</button>
                            <button wire:click="saveZone" class="bg-primary text-white px-4 py-2 rounded font-bold text-sm hover:bg-primary-dark">Speichern</button>
                        </div>
                    </div>

                    @if($view === 'edit')
                        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                            <h3 class="font-bold text-gray-900 mb-4 border-b pb-2">2. Länder zuweisen</h3>
                            <div class="flex gap-2 mb-4">
                                <select wire:model="selectedCountryToAdd" class="flex-1 rounded-lg border-gray-300 text-sm">
                                    <option value="">Land wählen...</option>
                                    @foreach($availableCountries as $code => $name)
                                        <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                                    @endforeach
                                </select>
                                <button wire:click="addCountry" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 rounded-lg font-bold text-xl">+</button>
                            </div>
                            @error('selectedCountryToAdd') <span class="text-red-500 text-xs block mb-2">{{ $message }}</span> @enderror

                            <div class="flex flex-wrap gap-2">
                                @foreach($activeZoneModel->countries as $country)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                                    <img src="https://flagcdn.com/16x12/{{ strtolower($country->country_code) }}.png" class="mr-1.5 h-3 w-4 object-cover rounded-sm">
                                    {{ config('shop.countries.'.$country->country_code, $country->country_code) }}
                                    <button wire:click="removeCountry({{ $country->id }})" class="ml-1.5 text-gray-400 hover:text-red-500 font-bold focus:outline-none">×</button>
                                </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Rechte Spalte (Tarife) --}}
                <div class="lg:col-span-2">
                    @if($view === 'edit')
                        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm h-full">
                            <h3 class="font-bold text-gray-900 mb-6 border-b pb-2 flex justify-between items-center">
                                <span>3. Tarife & Kosten</span>
                                <span class="text-xs font-normal text-gray-500 bg-gray-50 px-2 py-1 rounded">Alle Preise Netto + Steuer</span>
                            </h3>

                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6">
                                <h4 class="text-sm font-bold text-gray-700 mb-3">Neuen Tarif hinzufügen</h4>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                    <div>
                                        <label class="text-xs text-gray-500 font-bold uppercase">Name</label>
                                        <input type="text" wire:model="newRate.name" class="w-full rounded border-gray-300 text-sm" placeholder="z.B. Paket S">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-bold uppercase">Gewicht (g)</label>
                                        <div class="flex items-center gap-1">
                                            <input type="number" wire:model="newRate.min_weight" class="w-1/2 rounded border-gray-300 text-sm" placeholder="Min">
                                            <span class="text-gray-400">-</span>
                                            <input type="number" wire:model="newRate.max_weight" class="w-1/2 rounded border-gray-300 text-sm" placeholder="Max">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 font-bold uppercase">Preis (€)</label>
                                        <input type="number" step="0.01" wire:model="newRate.price" class="w-full rounded border-gray-300 text-sm" placeholder="0.00">
                                    </div>
                                    <button wire:click="addRate" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-bold text-sm h-[42px]">
                                        Hinzufügen
                                    </button>
                                </div>
                                @if ($errors->any())
                                    <div class="mt-2 text-red-500 text-xs"><ul>@foreach ($errors->all() as $error) <li>• {{ $error }}</li> @endforeach</ul></div>
                                @endif
                            </div>

                            <div class="overflow-hidden border border-gray-200 rounded-lg">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                                    <tr>
                                        <th class="px-4 py-3">Tarif Name</th>
                                        <th class="px-4 py-3">Gewichtsbereich</th>
                                        <th class="px-4 py-3 text-right">Preis</th>
                                        <th class="px-4 py-3 text-right"></th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                    @forelse($activeZoneModel->rates as $rate)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-medium text-gray-900">{{ $rate->name }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ number_format($rate->min_weight, 0, ',', '.') }}g - {{ $rate->max_weight ? number_format($rate->max_weight, 0, ',', '.') . 'g' : '∞' }}</td>
                                            <td class="px-4 py-3 text-right font-bold font-mono">{{ number_format($rate->price / 100, 2, ',', '.') }} €</td>
                                            <td class="px-4 py-3 text-right">
                                                <button wire:click="removeRate({{ $rate->id }})" class="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50 transition">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400 italic">Keine Tarife hinterlegt.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="h-full flex items-center justify-center bg-gray-50 rounded-xl border border-dashed border-gray-300 text-gray-400 p-12 text-center">
                            <p>Erstelle zuerst die Zone, um Länder und Tarife hinzuzufügen.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

    </div>


</div>

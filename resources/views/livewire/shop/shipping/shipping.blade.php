<div>

    <div class="space-y-8 animate-fade-in-up">

        {{-- DYNAMISCHES CSS FÜR DIE KARTE --}}
        <style>
            /* Standard Hover Effekt (Dunkler / Leuchtender werden im Darkmode) */
            .jvm-region:hover {
                opacity: 0.8;
                cursor: pointer;
                filter: brightness(1.2);
            }
            /* Dynamische Füllfarben aus PHP */
            {!! $mapVisuals['css'] !!}

            /* Tooltip immer im Vordergrund */
            .jvm-tooltip {
                z-index: 9999 !important;
            }
        </style>

        {{-- HEADER STATS --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 lg:gap-6">
            {{-- Stats Boxen --}}
            <div class="bg-gray-900/80 backdrop-blur-md p-6 rounded-[2rem] border border-gray-800 shadow-2xl flex items-center justify-between group hover:border-blue-500/50 transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Versandzonen</p>
                    <p class="text-3xl font-serif font-bold text-white group-hover:text-blue-400 transition-colors">{{ $stats['zones'] }}</p>
                </div>
                <div class="p-3.5 bg-blue-500/10 rounded-2xl text-blue-400 border border-blue-500/20 shadow-inner">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>

            <div class="bg-gray-900/80 backdrop-blur-md p-6 rounded-[2rem] border border-gray-800 shadow-2xl flex items-center justify-between group hover:border-emerald-500/50 transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Abgedeckte Länder</p>
                    <p class="text-3xl font-serif font-bold text-white group-hover:text-emerald-400 transition-colors">{{ $stats['countries_covered'] }}</p>
                </div>
                <div class="p-3.5 bg-emerald-500/10 rounded-2xl text-emerald-400 border border-emerald-500/20 shadow-inner">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </div>
            </div>

            <div class="bg-gray-900/80 backdrop-blur-md p-6 rounded-[2rem] border border-gray-800 shadow-2xl flex items-center justify-between group hover:border-amber-500/50 transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Aktive Tarife</p>
                    <p class="text-3xl font-serif font-bold text-white group-hover:text-amber-400 transition-colors">{{ $stats['rates'] }}</p>
                </div>
                <div class="p-3.5 bg-amber-500/10 rounded-2xl text-amber-400 border border-amber-500/20 shadow-inner">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
        </div>

        {{-- SUCCESS MESSAGE --}}
        @if(session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-2xl shadow-inner flex items-center gap-3 backdrop-blur-md animate-fade-in-down">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <p class="font-black text-[10px] uppercase tracking-widest">{{ session('success') }}</p>
            </div>
        @endif

        {{-- WELTKARTE --}}
        <div class="bg-gray-900/80 backdrop-blur-md p-6 md:p-8 rounded-[2.5rem] border border-gray-800 shadow-2xl overflow-hidden relative">
            <div class="flex flex-col md:flex-row justify-between items-start mb-8 gap-6">
                <div>
                    <h3 class="font-serif font-bold text-xl text-white tracking-wide">Aktive Liefergebiete</h3>
                    <p class="text-xs text-gray-400 mt-1 font-medium">Visuelle Übersicht deiner Versandzonen.</p>
                </div>

                {{-- Legende --}}
                <div class="flex flex-wrap gap-4 max-w-lg justify-end bg-gray-950 p-3 rounded-2xl border border-gray-800 shadow-inner">
                    @foreach($mapVisuals['legend'] as $name => $color)
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full shadow-[0_0_8px_currentColor]" style="background-color: {{ $color }}; color: {{ $color }};"></span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ $name }}</span>
                        </div>
                    @endforeach
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-gray-800 border border-gray-700"></span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-600">Kein Versand</span>
                    </div>
                </div>
            </div>

            <div id="shipping-map" class="w-full h-[300px] md:h-[450px] bg-gray-950 rounded-[2rem] border border-gray-800 shadow-inner overflow-hidden"></div>

            {{-- ASSETS --}}
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap/dist/css/jsvectormap.min.css" />
            <script src="https://cdn.jsdelivr.net/npm/jsvectormap"></script>
            <script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>

            <script>
                document.addEventListener('livewire:initialized', () => {
                    const activeCodes = @json($mapVisuals['activeCodes']);

                    const map = new jsVectorMap({
                        selector: '#shipping-map',
                        map: 'world',
                        zoomButtons: true,
                        zoomOnScroll: false,
                        backgroundColor: 'transparent',

                        // Standardmäßig etwas näher ran zoomen (Fokus auf Mitteleuropa)
                        focusOn: {
                            x: 0.5,
                            y: 0.35,
                            scale: 1.8 // Vergrößerung (höherer Wert = näher ran)
                        },

                        regionStyle: {
                            initial: {
                                fill: '#1F2937',
                                stroke: '#111827',
                                strokeWidth: 0.5,
                            }
                        },

                        onRegionTooltipShow(event, tooltip, code) {
                            if (activeCodes[code]) {
                                const zoneName = activeCodes[code];
                                tooltip.text(
                                    `<div class="text-center bg-gray-900 border border-gray-700 p-2 rounded-xl shadow-xl">
                                        <span class="font-bold text-white block text-sm mb-1">${tooltip.text()}</span>
                                        <span class="text-[9px] font-black uppercase tracking-widest bg-primary/20 text-primary border border-primary/30 px-2 py-0.5 rounded-md inline-block shadow-[0_0_10px_rgba(197,160,89,0.2)]">${zoneName}</span>
                                    </div>`,
                                    true
                                );
                            } else {
                                tooltip.text(
                                    `<div class="text-center bg-gray-900 border border-gray-700 p-2 rounded-xl shadow-xl">
                                        <span class="font-bold text-gray-300 block text-sm mb-1">${tooltip.text()}</span>
                                        <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-gray-800 px-2 py-0.5 rounded-md inline-block">Kein Versand</span>
                                    </div>`,
                                    true
                                );
                            }
                        }
                    });
                });
            </script>
        </div>

        {{-- LIST & EDIT VIEWS --}}
        @if($view === 'list')
            <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden">
                <div class="p-6 md:p-8 border-b border-gray-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-gray-950 shadow-inner">
                    <div>
                        <h2 class="text-lg md:text-xl font-serif font-bold text-white tracking-wide">Versandzonen verwalten</h2>
                        <p class="text-[10px] text-gray-500 uppercase font-black tracking-widest mt-1">Steuere hier, wohin du lieferst und zu welchem Preis.</p>
                    </div>
                    <button wire:click="createZone" class="bg-primary hover:bg-primary-dark text-gray-900 px-5 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all shadow-[0_0_20px_rgba(197,160,89,0.2)] hover:scale-[1.02] flex items-center gap-2 shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        Neue Zone
                    </button>
                </div>

                <div class="overflow-x-auto w-full no-scrollbar pb-2">
                    <table class="w-full text-left text-sm min-w-[600px] border-collapse">
                        <thead class="bg-gray-900/50 text-[10px] text-gray-500 uppercase tracking-widest font-black border-b border-gray-800">
                        <tr>
                            <th class="px-6 md:px-8 py-5">Zone Name</th>
                            <th class="px-4 py-5 text-center">Länder</th>
                            <th class="px-4 py-5 text-center">Tarife</th>
                            <th class="px-6 md:px-8 py-5 text-right">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                        @forelse($zones as $zone)
                            <tr class="hover:bg-gray-800/30 transition-colors group">
                                <td class="px-6 md:px-8 py-5 font-bold text-white tracking-wide">{{ $zone->name }}</td>
                                <td class="px-4 py-5 text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-lg text-[10px] font-black bg-blue-500/10 text-blue-400 border border-blue-500/20 shadow-inner">
                                        {{ $zone->countries_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-5 text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-lg text-[10px] font-black bg-amber-500/10 text-amber-400 border border-amber-500/20 shadow-inner">
                                        {{ $zone->rates_count }}
                                    </span>
                                </td>
                                <td class="px-6 md:px-8 py-5 text-right">
                                    <div class="flex items-center justify-end gap-3 opacity-100 lg:opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                        <button wire:click="editZone('{{ $zone->id }}')" class="p-2.5 text-gray-500 bg-gray-950 border border-gray-800 rounded-xl hover:text-blue-400 hover:border-blue-500/30 transition-all shadow-inner" title="Bearbeiten">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                        <button wire:confirm="Zone wirklich löschen? Alle Tarife gehen verloren." wire:click="deleteZone('{{ $zone->id }}')" class="p-2.5 text-gray-500 bg-gray-950 border border-gray-800 rounded-xl hover:text-red-400 hover:border-red-500/30 transition-all shadow-inner" title="Löschen">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-16 text-center text-gray-500 italic font-serif text-lg">
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
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
                {{-- Linke Spalte --}}
                <div class="lg:col-span-1 space-y-6 sm:space-y-8">
                    <div class="bg-gray-900/80 backdrop-blur-md p-6 sm:p-8 rounded-[2rem] border border-gray-800 shadow-2xl">
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-5 border-b border-gray-800 pb-3 flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-gray-800 text-white flex items-center justify-center text-[9px] shadow-inner">1</span>
                            Zone benennen
                        </h3>
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Name der Zone</label>
                            <input type="text" wire:model="zoneName" class="w-full rounded-xl bg-gray-950 border border-gray-700 text-white p-3.5 focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all outline-none shadow-inner" placeholder="z.B. Europäische Union">
                            @error('zoneName') <span class="text-[10px] font-bold text-red-400 mt-2 block ml-1 uppercase tracking-widest">{{ $message }}</span> @enderror
                        </div>
                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-800">
                            <button wire:click="cancel" class="text-[9px] font-black uppercase tracking-widest text-gray-500 hover:text-white px-4 py-2 transition-colors">Abbrechen</button>
                            <button wire:click="saveZone" class="bg-primary text-gray-900 px-6 py-2.5 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-primary-dark transition-all shadow-[0_0_15px_rgba(197,160,89,0.2)] hover:scale-[1.02]">Speichern</button>
                        </div>
                    </div>

                    @if($view === 'edit')
                        <div class="bg-gray-900/80 backdrop-blur-md p-6 sm:p-8 rounded-[2rem] border border-gray-800 shadow-2xl">
                            <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-5 border-b border-gray-800 pb-3 flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-gray-800 text-white flex items-center justify-center text-[9px] shadow-inner">2</span>
                                Länder zuweisen
                            </h3>

                            <div class="flex gap-3 mb-5">
                                <select wire:model="selectedCountryToAdd" class="flex-1 rounded-xl bg-gray-950 border border-gray-700 text-gray-300 text-xs font-bold p-3 focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all outline-none shadow-inner cursor-pointer appearance-none">
                                    <option value="" class="bg-gray-900">Land wählen...</option>
                                    @foreach($availableCountries as $code => $name)
                                        <option value="{{ $code }}" class="bg-gray-900">{{ $name }} ({{ $code }})</option>
                                    @endforeach
                                </select>
                                <button wire:click="addCountry" class="bg-primary hover:bg-primary-dark text-gray-900 px-4 rounded-xl font-black text-xl transition-all shadow-[0_0_15px_rgba(197,160,89,0.2)] hover:scale-105">+</button>
                            </div>

                            @error('selectedCountryToAdd') <span class="text-[10px] font-bold text-red-400 mb-4 block uppercase tracking-widest ml-1">{{ $message }}</span> @enderror

                            <div class="flex flex-wrap gap-2.5">
                                @php
                                    $activeCountries = shop_setting('active_countries', []);
                                @endphp

                                @foreach($activeZoneModel->countries as $country)
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest bg-gray-950 text-gray-300 border border-gray-800 shadow-inner group transition-all">
                                        <img src="https://flagcdn.com/16x12/{{ strtolower($country->country_code) }}.png"
                                             class="mr-2 h-3 w-4 object-cover rounded-sm opacity-80"
                                             alt="{{ $country->country_code }}">

                                        {{ $activeCountries[$country->country_code] ?? $country->country_code }}

                                        <button wire:click="removeCountry('{{ $country->id }}')"
                                                class="ml-2 pl-2 border-l border-gray-700 text-gray-500 hover:text-red-400 transition-colors focus:outline-none flex items-center">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </span>
                                @endforeach
                            </div>

                            @if($activeZoneModel->countries->isEmpty())
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-600 text-center py-4 bg-gray-950 rounded-xl border border-gray-800 shadow-inner mt-2">Noch keine Länder zugewiesen.</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Rechte Spalte (Tarife) --}}
                <div class="lg:col-span-2">
                    @if($view === 'edit')
                        <div class="bg-gray-900/80 backdrop-blur-md p-6 sm:p-8 rounded-[2.5rem] border border-gray-800 shadow-2xl h-full flex flex-col">
                            <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-6 border-b border-gray-800 pb-3 flex justify-between items-center">
                                <span class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-gray-800 text-white flex items-center justify-center text-[9px] shadow-inner">3</span>
                                    Tarife & Kosten
                                </span>
                                <span class="bg-gray-950 px-3 py-1 rounded-lg border border-gray-800 text-gray-400 shadow-inner">Alle Preise Netto + Steuer</span>
                            </h3>

                            <div class="bg-gray-950 p-5 rounded-[1.5rem] border border-gray-800 mb-8 shadow-inner">
                                <h4 class="text-[10px] font-black text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    Neuen Tarif hinzufügen
                                </h4>
                                @php
                                    $rateInputClass = "w-full rounded-xl bg-gray-900 border border-gray-700 text-white text-sm p-3 focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all outline-none shadow-inner placeholder-gray-600";
                                @endphp
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                                    <div class="md:col-span-4">
                                        <label class="text-[9px] text-gray-500 font-black uppercase tracking-widest mb-1.5 ml-1 block">Name</label>
                                        <input type="text" wire:model="newRate.name" class="{{ $rateInputClass }}" placeholder="z.B. Paket S">
                                    </div>
                                    <div class="md:col-span-4">
                                        <label class="text-[9px] text-gray-500 font-black uppercase tracking-widest mb-1.5 ml-1 block">Gewicht (g)</label>
                                        <div class="flex items-center gap-2">
                                            <input type="number" wire:model="newRate.min_weight" class="{{ $rateInputClass }} text-center" placeholder="Min">
                                            <span class="text-gray-600 font-bold">-</span>
                                            <input type="number" wire:model="newRate.max_weight" class="{{ $rateInputClass }} text-center" placeholder="Max">
                                        </div>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="text-[9px] text-gray-500 font-black uppercase tracking-widest mb-1.5 ml-1 block">Preis (€)</label>
                                        <input type="number" step="0.01" wire:model="newRate.price" class="{{ $rateInputClass }} font-mono" placeholder="0.00">
                                    </div>
                                    <div class="md:col-span-2">
                                        <button wire:click="addRate" class="w-full bg-emerald-500 hover:bg-emerald-400 text-gray-900 px-4 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all shadow-[0_0_15px_rgba(16,185,129,0.2)] hover:scale-[1.02] h-[46px]">
                                            Save
                                        </button>
                                    </div>
                                </div>
                                @if ($errors->any())
                                    <div class="mt-4 bg-red-500/10 border border-red-500/20 p-3 rounded-xl">
                                        <ul class="text-red-400 text-[9px] font-bold uppercase tracking-widest space-y-1 ml-2">
                                            @foreach ($errors->all() as $error) <li>• {{ $error }}</li> @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>

                            <div class="overflow-x-auto w-full no-scrollbar flex-1 border border-gray-800 rounded-2xl">
                                <table class="w-full text-sm text-left min-w-[500px] border-collapse">
                                    <thead class="bg-gray-950 text-gray-500 uppercase text-[9px] font-black tracking-widest border-b border-gray-800 shadow-inner">
                                    <tr>
                                        <th class="px-5 py-4">Tarif Name</th>
                                        <th class="px-4 py-4 text-center">Gewichtsbereich</th>
                                        <th class="px-4 py-4 text-right">Preis</th>
                                        <th class="px-5 py-4 text-center w-16">Aktion</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-800/50 bg-transparent">
                                    @forelse($activeZoneModel->rates as $rate)
                                        <tr class="hover:bg-gray-800/30 transition-colors group">
                                            <td class="px-5 py-4 font-bold text-white tracking-wide">{{ $rate->name }}</td>
                                            <td class="px-4 py-4 text-gray-400 text-center font-mono text-xs">{{ number_format($rate->min_weight, 0, ',', '.') }}g <span class="mx-1 text-gray-600">-</span> {{ $rate->max_weight ? number_format($rate->max_weight, 0, ',', '.') . 'g' : '∞' }}</td>
                                            <td class="px-4 py-4 text-right font-bold font-mono text-primary">{{ number_format($rate->price / 100, 2, ',', '.') }} €</td>
                                            <td class="px-5 py-4 text-center">
                                                <button wire:click="removeRate('{{ $rate->id }}')" class="text-gray-600 hover:text-red-400 bg-gray-950 hover:bg-red-500/10 hover:border-red-500/30 border border-gray-800 p-2 rounded-xl transition-all shadow-inner">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-5 py-12 text-center text-gray-500 font-serif italic">Keine Tarife hinterlegt.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="h-full flex flex-col items-center justify-center bg-gray-900/50 backdrop-blur-md rounded-[2.5rem] border-2 border-dashed border-gray-800 text-gray-500 p-12 text-center shadow-inner">
                            <div class="w-16 h-16 rounded-full bg-gray-950 border border-gray-800 flex items-center justify-center mb-4 shadow-inner">
                                <svg class="w-8 h-8 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <p class="font-bold text-sm tracking-wide text-white mb-1">Zone noch nicht gespeichert</p>
                            <p class="text-xs">Erstelle zuerst die Zone, um Länder und Tarife hinzuzufügen.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

    </div>

</div>

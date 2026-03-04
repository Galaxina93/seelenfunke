<div>
    <div class="space-y-8 animate-fade-in-up pb-12 w-full">
        <style>
            .jvm-region:hover { opacity: 0.9; cursor: pointer; filter: brightness(1.3); }
            {!! $mapVisuals['css'] !!}
        </style>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-gray-900/80 backdrop-blur-md p-6 rounded-[2rem] border border-gray-800 shadow-2xl flex items-center justify-between group hover:border-blue-500/50 transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Versandzonen</p>
                    <p class="text-3xl font-serif font-bold text-white group-hover:text-blue-400 transition-colors">{{$stats['zones']}}</p>
                </div>
                <div class="p-3.5 bg-blue-500/10 rounded-2xl text-blue-400 border border-blue-500/20 shadow-inner">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>

            <div class="bg-gray-900/80 backdrop-blur-md p-6 rounded-[2rem] border border-gray-800 shadow-2xl flex items-center justify-between group hover:border-emerald-500/50 transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Abgedeckte Länder</p>
                    <p class="text-3xl font-serif font-bold text-white group-hover:text-emerald-400 transition-colors">{{$stats['countries_covered']}}</p>
                </div>
                <div class="p-3.5 bg-emerald-500/10 rounded-2xl text-emerald-400 border border-emerald-500/20 shadow-inner">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </div>
            </div>

            <div class="bg-gray-900/80 backdrop-blur-md p-6 rounded-[2rem] border border-gray-800 shadow-2xl flex items-center justify-between group hover:border-amber-500/50 transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Aktive Tarife</p>
                    <p class="text-3xl font-serif font-bold text-white group-hover:text-amber-400 transition-colors">{{$stats['rates']}}</p>
                </div>
                <div class="p-3.5 bg-amber-500/10 rounded-2xl text-amber-400 border border-amber-500/20 shadow-inner">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
        </div>

        @if(session()->has('success'))
            <div x-data="{show: true}" x-show="show" x-init="setTimeout(()=> show = false, 4000)" class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-2xl shadow-inner flex items-center gap-3 backdrop-blur-md animate-fade-in-down">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <p class="font-black text-[10px] uppercase tracking-widest">{{session('success')}}</p>
            </div>
        @endif

        <div class="flex flex-col xl:flex-row gap-6 w-full min-h-[650px] xl:h-[75vh]">

            <div class="flex-1 relative bg-gray-900/20 backdrop-blur-sm rounded-[2.5rem] border border-gray-800 shadow-2xl overflow-hidden flex flex-col min-h-[400px]">

                <div class="absolute top-6 left-6 right-6 z-10 flex flex-wrap justify-between gap-4 pointer-events-none">
                    <div class="bg-gray-900/80 backdrop-blur-md p-4 rounded-2xl border border-gray-700 shadow-xl pointer-events-auto">
                        <h3 class="font-serif font-bold text-lg text-white tracking-wide">Weltkarte</h3>
                        <p class="text-[10px] text-gray-400 mt-1 font-medium uppercase tracking-widest">Liefergebiete</p>
                    </div>

                    <div class="flex flex-wrap gap-3 bg-gray-900/80 backdrop-blur-md p-3.5 rounded-2xl border border-gray-700 shadow-xl pointer-events-auto max-w-sm justify-end">
                        @foreach($mapVisuals['legend'] as $name => $color)
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full shadow-[0_0_8px_currentColor]" style="background-color:{{$color}};color:{{$color}};"></span>
                                <span class="text-[9px] font-black uppercase tracking-widest text-gray-300">{{$name}}</span>
                            </div>
                        @endforeach
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-gray-800 border border-gray-600"></span>
                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Kein Versand</span>
                        </div>
                    </div>
                </div>

                <div wire:ignore class="absolute inset-0 z-0">
                    <div id="shipping-map" class="w-full h-full"></div>
                </div>

                <link rel="stylesheet" href="{{ asset('vendor/jsvectormap/jsvectormap.min.css') }}" />
                <script src="{{ asset('vendor/jsvectormap/jsvectormap.min.js') }}"></script>
                <script src="{{ asset('vendor/jsvectormap/world.js') }}"></script>

                <script>
                    document.addEventListener('livewire:initialized', () => {
                        let activeCodes = @json($mapVisuals['activeCodes']);

                        const map = new jsVectorMap({
                            selector: '#shipping-map',
                            map: 'world',
                            zoomButtons: true,
                            zoomOnScroll: true,
                            backgroundColor: 'transparent',
                            focusOn: { x: 0.5, y: 0.45, scale: 1.8 },
                            regionStyle: {
                                initial: {
                                    fill: 'rgba(31, 41, 55, 0.4)',
                                    stroke: 'rgba(255, 255, 255, 0.05)',
                                    strokeWidth: 0.5,
                                }
                            },
                            onRegionTooltipShow(event, tooltip, code) {
                                if (activeCodes[code]) {
                                    const zoneName = activeCodes[code];
                                    tooltip.text(`<div class="text-center">
                                        <span class="font-bold text-white block text-sm mb-1">${tooltip.text()}</span>
                                        <span class="text-[9px] font-black uppercase tracking-widest bg-primary/20 text-primary border border-primary/30 px-2 py-0.5 rounded-md inline-block shadow-[0_0_10px_rgba(197,160,89,0.2)]">${zoneName}</span>
                                    </div>`, true);
                                } else {
                                    tooltip.text(`<div class="text-center">
                                        <span class="font-bold text-gray-300 block text-sm mb-1">${tooltip.text()}</span>
                                        <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-gray-800 px-2 py-0.5 rounded-md inline-block">Kein Versand</span>
                                    </div>`, true);
                                }
                            }
                        });

                        // Update Tooltips Dynamically via Livewire Event
                        window.addEventListener('map-updated', (event) => {
                            activeCodes = event.detail.activeCodes;
                        });
                    });
                </script>
            </div>

            <div class="w-full xl:w-[450px] shrink-0 bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] border border-gray-800 shadow-2xl flex flex-col overflow-hidden relative z-10">

                @if($view === 'list')
                    <div class="p-6 border-b border-gray-800 flex justify-between items-center bg-gray-950/50 shadow-inner shrink-0">
                        <div>
                            <h2 class="text-lg font-serif font-bold text-white tracking-wide">Steuerung</h2>
                            <p class="text-[9px] text-gray-500 uppercase font-black tracking-widest mt-1">Alle Zonen</p>
                        </div>
                        <button wire:click="createZone" class="bg-primary hover:bg-primary-dark text-gray-900 w-10 h-10 rounded-xl flex items-center justify-center transition-all shadow-[0_0_15px_rgba(197,160,89,0.2)] hover:scale-105" title="Neue Zone">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-3">
                        @forelse($zones as $zone)
                            <div wire:click="editZone('{{$zone->id}}')" class="bg-gray-950 rounded-2xl p-5 border border-gray-800 shadow-inner hover:border-primary/50 hover:bg-gray-900 transition-all cursor-pointer group">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="font-bold text-white text-base group-hover:text-primary transition-colors">{{$zone->name}}</h3>
                                    <button wire:click.stop="deleteZone('{{$zone->id}}')" wire:confirm="Zone wirklich löschen?" class="text-gray-600 hover:text-red-500 transition-colors p-1" title="Löschen">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                                <div class="flex gap-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[9px] font-black bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                        {{$zone->countries_count}} Länder
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[9px] font-black bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        {{$zone->rates_count}} Tarife
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-12 px-4">
                                <p class="text-gray-500 text-sm font-medium">Noch keine Versandzonen angelegt.</p>
                            </div>
                        @endforelse
                    </div>

                @else
                    <div class="p-6 border-b border-gray-800 flex items-center gap-4 bg-gray-950/50 shadow-inner shrink-0">
                        <button wire:click="cancel" class="w-10 h-10 bg-gray-900 border border-gray-700 text-gray-400 rounded-xl flex items-center justify-center hover:text-white hover:border-gray-500 transition-all shadow-inner">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <div>
                            <h2 class="text-lg font-serif font-bold text-white tracking-wide">{{ $view === 'create' ? 'Neue Zone' : 'Zone bearbeiten' }}</h2>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-8">
                        <div>
                            <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Name der Zone</label>
                            <input type="text" wire:model="zoneName" class="w-full rounded-xl bg-gray-950 border border-gray-700 text-white p-3.5 focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all outline-none shadow-inner" placeholder="z.B. Europäische Union">
                            @error('zoneName')<span class="text-[9px] font-bold text-red-400 mt-2 block ml-1 uppercase tracking-widest">{{$message}}</span>@enderror
                        </div>

                        @if($view === 'edit')
                            <div class="border-t border-gray-800 pt-6">
                                <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-1">Länder zuweisen</label>
                                <div class="flex gap-2 mb-4">
                                    <select wire:model="selectedCountryToAdd" class="flex-1 rounded-xl bg-gray-950 border border-gray-700 text-gray-300 text-xs font-bold p-3 focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all outline-none shadow-inner cursor-pointer appearance-none">
                                        <option value="" class="bg-gray-900">Land wählen...</option>
                                        @foreach($availableCountries as $code => $name)
                                            <option value="{{$code}}" class="bg-gray-900">{{$name}} ({{$code}})</option>
                                        @endforeach
                                    </select>
                                    <button wire:click="addCountry" class="bg-primary hover:bg-primary-dark text-gray-900 px-4 rounded-xl font-black text-xl transition-all shadow-[0_0_15px_rgba(197,160,89,0.2)] hover:scale-105">+</button>
                                </div>
                                @error('selectedCountryToAdd')<span class="text-[9px] font-bold text-red-400 mb-3 block uppercase tracking-widest ml-1">{{$message}}</span>@enderror

                                <div class="flex flex-wrap gap-2">
                                    @foreach($activeZoneModel->countries as $country)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-gray-950 text-gray-300 border border-gray-700 shadow-inner group">
                                            <img src="https://flagcdn.com/16x12/{{strtolower($country->country_code)}}.png" class="mr-2 h-2.5 w-3.5 object-cover rounded-sm opacity-80" alt="{{$country->country_code}}">
                                            {{$allCountries[$country->country_code] ?? $country->country_code}}
                                            <button wire:click="removeCountry('{{$country->id}}')" class="ml-2 pl-2 border-l border-gray-800 text-gray-500 hover:text-red-400 transition-colors">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </span>
                                    @endforeach
                                </div>
                                @if($activeZoneModel->countries->isEmpty())
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-600 text-center py-4 bg-gray-950 rounded-xl border border-gray-800 shadow-inner mt-2">Noch keine Länder zugewiesen.</p>
                                @endif
                            </div>

                            <div class="border-t border-gray-800 pt-6">
                                <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-1">Tarife (Netto)</label>

                                <div class="bg-gray-950 p-4 rounded-xl border border-gray-800 mb-5 shadow-inner">
                                    <div class="space-y-3">
                                        <input type="text" wire:model="newRate.name" class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white text-xs p-2.5 focus:border-primary outline-none" placeholder="Name (z.B. Paket S)">
                                        <div class="flex gap-2">
                                            <input type="number" wire:model="newRate.min_weight" class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white text-xs p-2.5 focus:border-primary outline-none text-center" placeholder="Min g">
                                            <input type="number" wire:model="newRate.max_weight" class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white text-xs p-2.5 focus:border-primary outline-none text-center" placeholder="Max g">
                                        </div>
                                        <div class="flex gap-2">
                                            <input type="number" step="0.01" wire:model="newRate.price" class="flex-1 rounded-lg bg-gray-900 border border-gray-700 text-white text-xs p-2.5 focus:border-primary outline-none font-mono" placeholder="Preis €">
                                            <button wire:click="addRate" class="bg-emerald-500 hover:bg-emerald-400 text-gray-900 px-4 rounded-lg font-black text-[10px] uppercase tracking-widest transition-all">Add</button>
                                        </div>
                                    </div>
                                    @if($errors->any())
                                        <div class="mt-3 text-red-400 text-[9px] font-bold uppercase tracking-widest space-y-1">
                                            @foreach($errors->all() as $error) <div>{{$error}}</div> @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="space-y-2">
                                    @forelse($activeZoneModel->rates as $rate)
                                        <div class="flex items-center justify-between bg-gray-950 p-3 rounded-lg border border-gray-800 shadow-inner">
                                            <div class="min-w-0 flex-1 pr-2">
                                                <p class="text-[10px] font-bold text-white truncate">{{$rate->name}}</p>
                                                <p class="text-[9px] text-gray-500 font-mono mt-0.5">{{number_format($rate->min_weight, 0, ',', '.')}}g - {{$rate->max_weight ? number_format($rate->max_weight, 0, ',', '.') . 'g' : '∞'}}</p>
                                            </div>
                                            <div class="flex items-center gap-3 shrink-0">
                                                <span class="text-xs font-bold font-mono text-primary">{{number_format($rate->price / 100, 2, ',', '.')}}€</span>
                                                <button wire:click="removeRate('{{$rate->id}}')" class="text-gray-600 hover:text-red-400 transition-colors p-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-600 text-center py-2">Keine Tarife hinterlegt.</p>
                                    @endforelse
                                </div>
                            </div>
                        @else
                            <div class="border-t border-gray-800 pt-6 text-center">
                                <p class="text-[10px] text-gray-500 uppercase tracking-widest font-black">Speichere die Zone, um Länder und Tarife hinzuzufügen.</p>
                            </div>
                        @endif
                    </div>

                    <div class="p-5 border-t border-gray-800 bg-gray-950/50 shrink-0">
                        <button wire:click="saveZone" class="w-full bg-primary text-gray-900 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-white hover:scale-[1.02] transition-all shadow-[0_0_15px_rgba(197,160,89,0.3)]">
                            Zone Speichern
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

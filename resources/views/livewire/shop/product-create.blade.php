<div class="min-h-screen bg-gray-50 font-sans text-gray-900" x-data>

    {{-- ========================================== --}}
    {{-- ANSICHT: LISTE (Übersicht aller Produkte)  --}}
    {{-- ========================================== --}}
    @if($viewMode === 'list')
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-end mb-10 gap-4">
                <div>
                    <h1 class="text-4xl font-serif font-bold text-gray-900 mb-2">Deine Kollektion</h1>
                    <div class="flex items-center gap-4 text-gray-500">
                        <p>Verwalte deine Unikate.</p>
                        <span class="px-2 py-0.5 bg-gray-200 rounded text-xs font-bold">{{ count($products) }} Produkte</span>
                    </div>
                </div>

                <div class="flex gap-4 w-full md:w-auto">
                    <div class="relative w-full md:w-64">
                        <input type="text" wire:model.live="search" placeholder="Produkt suchen..." class="w-full pl-10 pr-4 py-3 rounded-full border-0 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary text-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>

                    <button wire:click="createDraft" class="bg-primary text-white px-6 py-3 rounded-full font-semibold shadow-lg shadow-primary/30 hover:bg-white hover:text-primary-dark transition-all transform hover:scale-105 flex items-center gap-2 whitespace-nowrap">
                        <span>+</span> Neu
                    </button>
                </div>
            </div>

            @if($products->isEmpty())
                <div class="bg-white p-16 rounded-2xl border-2 border-dashed border-gray-300 text-center">
                    <h3 class="text-xl font-serif text-gray-900 mb-2">Keine Produkte gefunden</h3>
                    <button wire:click="createDraft" class="text-primary font-bold hover:underline">Erstelle das Erste</button>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    @foreach($products as $prod)
                        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition flex flex-col h-full relative overflow-hidden group">

                            {{-- Fortschrittsbalken Oben --}}
                            <div class="absolute top-0 left-0 w-full h-1 bg-gray-100">
                                <div class="h-full {{ $prod->completion_step >= 4 ? 'bg-primary' : 'bg-red-500' }}" style="width: {{ ($prod->completion_step / 4) * 100 }}%"></div>
                            </div>

                            {{-- Status Switcher --}}
                            <div class="absolute top-4 right-4 z-20" x-data="{ open: false }">
                                <button @click="open = !open" @click.away="open = false" class="px-3 py-1 text-xs font-bold rounded-full border shadow-sm flex items-center gap-1 transition-colors {{ $prod->status == 'active' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-gray-50 text-gray-600 border-gray-200' }}">
                                    <span class="w-2 h-2 rounded-full {{ $prod->status == 'active' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                    {{ $prod->status == 'active' ? 'Aktiv' : 'Entwurf' }}
                                    <svg class="w-3 h-3 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>

                                <div x-show="open" class="absolute right-0 mt-2 w-32 bg-white rounded-lg shadow-xl border border-gray-100 overflow-hidden z-30" style="display: none;">
                                    <button wire:click="updateStatus('{{ $prod->id }}', 'active'); open = false" class="w-full text-left px-4 py-2 text-xs hover:bg-gray-50 text-green-700 font-bold">Aktiv</button>
                                    <button wire:click="updateStatus('{{ $prod->id }}', 'draft'); open = false" class="w-full text-left px-4 py-2 text-xs hover:bg-gray-50 text-gray-600">Entwurf</button>
                                </div>
                            </div>

                            {{-- Bild --}}
                            <div class="aspect-square bg-gray-50 rounded-xl mb-4 overflow-hidden relative mt-2 border border-gray-100">
                                @if(!empty($prod->media_gallery[0]))
                                    @if(isset($prod->media_gallery[0]['type']) && $prod->media_gallery[0]['type'] == 'video')
                                        <video src="{{ asset('storage/'.$prod->media_gallery[0]['path']) }}" class="w-full h-full object-cover"></video>
                                    @else
                                        <img src="{{ asset('storage/'. (is_array($prod->media_gallery[0]) ? $prod->media_gallery[0]['path'] : $prod->media_gallery[0])) }}" class="w-full h-full object-cover">
                                    @endif
                                @else
                                    <div class="flex items-center justify-center h-full text-gray-300 text-sm">Kein Bild</div>
                                @endif
                            </div>

                            <h3 class="font-serif text-lg font-bold text-gray-900 truncate mb-1">{{ $prod->name }}</h3>
                            <p class="text-sm text-gray-500 font-mono mb-6">{{ number_format($prod->price / 100, 2, ',', '.') }} €</p>

                            {{-- NEU: Lagerbestand Anzeige & Schnellerfassung --}}
                            <div class="mb-6 h-8 flex items-center"
                                 x-data="{
                                     editing: false,
                                     qty: {{ $prod->quantity }}
                                 }">

                                @if($prod->track_quantity)
                                    {{-- ANZEIGE MODUS --}}
                                    <div x-show="!editing"
                                         @click="editing = true; $nextTick(() => $refs.qtyInput.focus())"
                                         class="cursor-pointer group relative transition-all hover:scale-105">

                                        @if($prod->quantity > 0)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-100 hover:border-green-300 hover:bg-green-100 transition">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                                <span x-text="qty"></span> auf Lager
                                                <svg class="w-3 h-3 ml-1 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-100 hover:border-red-300 hover:bg-red-100 transition">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                                Ausverkauft
                                                <svg class="w-3 h-3 ml-1 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </span>
                                        @endif
                                    </div>

                                    {{-- EDITIER MODUS --}}
                                    <div x-show="editing" style="display: none;" class="flex items-center gap-2" @click.outside="editing = false; qty = {{ $prod->quantity }}">
                                        <input x-ref="qtyInput"
                                               type="number"
                                               x-model="qty"
                                               class="w-20 px-2 py-1 text-xs font-bold text-center border border-primary rounded-lg focus:ring-2 focus:ring-primary/20 outline-none"
                                               @keydown.enter="$wire.updateStock('{{ $prod->id }}', qty); editing = false"
                                               @keydown.escape="editing = false; qty = {{ $prod->quantity }}">

                                        <button @click="$wire.updateStock('{{ $prod->id }}', qty); editing = false" class="bg-primary text-white p-1 rounded-md hover:bg-primary-dark">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100 cursor-not-allowed opacity-70" title="Unbegrenzter Bestand aktiviert">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        Unbegrenzt
                                    </span>
                                @endif
                            </div>

                            {{-- Footer --}}
                            <div class="mt-auto pt-4 border-t border-gray-100 flex items-center justify-between text-xs">
                                <span class="font-bold {{ $prod->completion_step >= 4 ? 'text-primary' : 'text-red-500' }}">
                                    @if($prod->completion_step >= 4)
                                        <span class="flex items-center gap-1"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Fertig</span>
                                    @else
                                        Schritt {{ $prod->completion_step }}/4
                                    @endif
                                </span>
                                <button wire:click="edit('{{ $prod->id }}')" class="text-gray-900 hover:text-primary font-bold hover:underline transition-colors">
                                    Bearbeiten &rarr;
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ========================================== --}}
        {{-- ANSICHT: EDIT (Bearbeitungsmodus)          --}}
        {{-- ========================================== --}}
    @elseif($viewMode === 'edit')

        {{-- Sticky Header --}}
        <div class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-gray-200 px-6 py-3 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-4">
                <button wire:click="backToList" class="p-2 hover:bg-gray-100 rounded-full text-gray-500 transition" title="Zurück">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </button>
                <div>
                    <h2 class="text-lg font-serif font-bold text-gray-900 leading-tight">{{ $name ?: 'Neues Unikat' }}</h2>
                    <p class="text-xs text-primary font-bold uppercase tracking-wider">Schritt {{ $currentStep }} von {{ $totalSteps }}</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <span wire:loading class="text-xs text-gray-400">Speichere...</span>
                @if (session()->has('success')) <span class="text-xs text-green-600 font-bold animate-pulse">{{ session('success') }}</span> @endif
                <button wire:click="save" class="text-gray-500 hover:text-gray-900 text-sm font-medium px-2">Produkt speichern</button>
            </div>
        </div>

        <div class="max-w-[1800px] mx-auto p-6 lg:p-10">
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-12 items-start">

                {{-- LINKE SPALTE: FORMULARE --}}
                <div class="xl:col-span-7 space-y-8">

                    {{-- Progress Bar mit Beschriftung --}}
                    <div class="flex items-start gap-3 mb-10">
                        @php
                            $stepLabels = [
                                1 => 'Basisdaten',
                                2 => 'Medien',
                                3 => 'Attribute',
                                4 => 'Konfigurator'
                            ];
                        @endphp

                        @foreach($stepLabels as $step => $label)
                            {{-- Container für Text + Balken (Klickbar) --}}
                            <div wire:click="goToStep({{ $step }})"
                                 class="flex-1 flex flex-col gap-2 group transition-all duration-300
                                 {{ ($step <= $product->completion_step + 1) ? 'cursor-pointer' : 'cursor-not-allowed opacity-40' }}"
                                 @if($step > $product->completion_step + 1) title="Schritt noch nicht verfügbar" @endif
                            >
                                {{-- Beschriftung --}}
                                <span
                                    class="text-xs font-bold uppercase tracking-wider
                                           h-8 flex items-center
                                           transition-colors duration-300
                                           {{ $currentStep >= $step
                                               ? 'text-primary'
                                               : 'text-gray-400 group-hover:text-gray-600' }}">
                                    {{ $step }}. {{ $label }}
                                </span>


                                {{-- Balken --}}
                                <div class="h-1.5 w-full rounded-full transition-all duration-500
                                    {{ $currentStep >= $step ? 'bg-primary shadow-sm shadow-primary/30' : 'bg-gray-200 group-hover:bg-gray-300' }}">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- ============================== --}}
                    {{-- SCHRITT 1: BASISINFORMATIONEN  --}}
                    {{-- ============================== --}}
                    @if($currentStep === 1)
                        <div class="space-y-6">
                            {{-- Karte 1: Grunddaten --}}
                            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
                                <h2 class="text-2xl font-serif text-gray-900 mb-6">1. Basisinformationen</h2>
                                <div class="space-y-6">
                                    <div>
                                        <div class="flex items-center gap-2 mb-2">
                                            <label class="block text-sm font-bold text-gray-800">Produktname *</label>
                                            <div x-data="{ show: false }" class="relative inline-block ml-1">
                                                <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                                                <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">{{ $infoTexts['name'] }}</div>
                                            </div>
                                        </div>
                                        <input type="text" wire:model.live="name" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="z.B. Seelen-Kristall">
                                    </div>

                                    <div class="grid grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-bold text-gray-800 mb-2">Preis *</label>
                                            <input type="number" step="0.01" wire:model.live="price_input" class="w-full pl-8 pr-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition font-mono" placeholder="0.00">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold text-gray-800 mb-2">Vergleichspreis</label>
                                            <input type="number" step="0.01" wire:model.live="compare_price_input" class="w-full pl-8 pr-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition font-mono text-gray-500" placeholder="z.B. 59.90">
                                        </div>
                                    </div>

                                    {{-- NEU: Steuer Einstellungen --}}
                                    <div class="p-5 bg-gray-50 rounded-xl border border-gray-100">
                                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
                                            Steuer & Mehrwertsteuer
                                            <span class="text-xs font-normal bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">EU Richtlinie</span>
                                        </h4>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Steuerklasse</label>
                                                <select wire:model.live="tax_class" class="w-full px-3 py-2 text-sm rounded border border-gray-300 focus:border-primary focus:ring-primary">
                                                    <option value="standard">Standard (Regelsteuersatz)</option>
                                                    <option value="reduced">Ermäßigter Satz</option>
                                                    <option value="zero">Steuerfrei / Steuerbefreit</option>
                                                </select>
                                                <p class="text-xs text-gray-400 mt-1">Aktuell: {{ number_format($current_tax_rate, 2) }}%</p>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Berechnung</label>
                                                <div class="flex gap-4 mt-2">
                                                    <label class="flex items-center gap-2 text-sm cursor-pointer hover:text-primary">
                                                        <input type="radio" name="tax_calc" wire:model.live="tax_included" value="1" class="text-primary focus:ring-primary">
                                                        Inklusive (Brutto)
                                                    </label>
                                                    <label class="flex items-center gap-2 text-sm cursor-pointer hover:text-primary">
                                                        <input type="radio" name="tax_calc" wire:model.live="tax_included" value="0" class="text-primary focus:ring-primary">
                                                        Exklusive (Netto)
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-start gap-2 text-xs text-gray-500 bg-white p-3 rounded border border-gray-200">
                                            <svg class="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <p>Hinweis zur EU-MwSt-Richtlinie: Bei grenzüberschreitenden Verkäufen digitaler Güter (OSS) wird im Checkout der Steuersatz des Kundenlandes berechnet. Der hier gewählte Satz gilt als Basis für Deutschland.</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-bold text-gray-800 mb-2">Beschreibung</label>
                                        <textarea wire:model.live="description" rows="6" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="Erzähle die Geschichte des Produkts..."></textarea>
                                    </div>
                                </div>
                            </div>

                            {{-- Karte 2: SEO & Marketing --}}
                            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
                                <div class="flex items-center justify-between mb-4 border-b pb-2">
                                    <h3 class="text-lg font-serif font-bold text-gray-900">SEO & Suchmaschinen</h3>
                                    <span class="text-xs text-gray-400 uppercase tracking-widest">Optional</span>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="block text-sm font-bold text-gray-800">Seitentitel (Meta Title)</label>
                                            <span class="text-xs text-gray-400">{{ strlen($seo_title) }}/60 Zeichen</span>
                                        </div>
                                        <input type="text" wire:model.live="seo_title" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="{{ $name }}">
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="block text-sm font-bold text-gray-800">Beschreibung (Meta Description)</label>
                                            <span class="text-xs text-gray-400">{{ strlen($seo_description) }}/160 Zeichen</span>
                                        </div>
                                        <textarea wire:model.live="seo_description" rows="3" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="Eine kurze Zusammenfassung für Google..."></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-800 mb-1">URL-Handle (Slug)</label>
                                        <div class="flex items-center">
                                            <span class="bg-gray-100 border border-r-0 border-gray-300 text-gray-500 px-3 py-3 rounded-l-lg text-sm">/produkt/</span>
                                            <input type="text" wire:model.live="slug_input" class="w-full px-4 py-3 rounded-r-lg border border-gray-300 focus:border-primary focus:ring-primary transition font-mono text-sm">
                                        </div>
                                        @error('slug_input') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Karte 3: Identifikatoren --}}
                            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
                                <h3 class="text-lg font-serif font-bold text-gray-900 mb-4 border-b pb-2">Produktidentifikatoren</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <div class="flex items-center gap-2 mb-2">
                                            <label class="block text-sm font-bold text-gray-800">Artikelnummer (SKU) *</label>
                                            <div x-data="{ show: false }" class="relative inline-block ml-1">
                                                <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                                                <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">{{ $infoTexts['sku'] }}</div>
                                            </div>
                                        </div>
                                        <input type="text" wire:model.live="sku" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="z.B. GLAS-001">
                                        @if(empty($sku)) <p class="text-xs text-red-500 mt-1">Pflichtfeld</p> @endif
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 mb-2">
                                            <label class="block text-sm font-bold text-gray-800">Barcode (GTIN/EAN)</label>
                                            <div x-data="{ show: false }" class="relative inline-block ml-1">
                                                <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                                                <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">{{ $infoTexts['barcode'] }}</div>
                                            </div>
                                        </div>
                                        <input type="text" wire:model.live="barcode" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="">
                                    </div>
                                    <div class="md:col-span-2">
                                        <div class="flex items-center gap-2 mb-2">
                                            <label class="block text-sm font-bold text-gray-800">Marke / Hersteller</label>
                                            <div x-data="{ show: false }" class="relative inline-block ml-1">
                                                <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                                                <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">{{ $infoTexts['brand'] }}</div>
                                            </div>
                                        </div>
                                        <input type="text" wire:model.live="brand" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="z.B. Eigenmarke">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- ============================== --}}
                    {{-- SCHRITT 2: PRODUKTMEDIEN       --}}
                    {{-- ============================== --}}
                    @if($currentStep === 2)
                        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
                            <h2 class="text-2xl font-serif text-gray-900 mb-2">2. Produktmedien</h2>
                            @error('new_media')
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert"><span class="block sm:inline">{{ $message }}</span></div>
                            @enderror
                            {{-- BILDER UPLOAD --}}
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-4 border-b border-gray-100 pb-2">Produktbilder (Mind. 1) *</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                                @foreach($product->media_gallery ?? [] as $index => $media)
                                    @if(is_array($media) && $media['type'] === 'image')
                                        <div class="relative aspect-square rounded-lg overflow-hidden group border {{ $index === 0 ? 'border-2 border-primary' : 'border-gray-200' }} bg-white">
                                            <img src="{{ asset('storage/'.$media['path']) }}" class="w-full h-full object-cover">
                                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                                                @if($index !== 0)
                                                    <button wire:click="setMainImage({{ $index }})" class="bg-white text-black p-2 rounded-full hover:bg-gray-100" title="Als Hauptbild setzen"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg></button>
                                                @endif
                                                <button wire:click="removeMedia({{ $index }})" class="bg-white text-red-500 p-2 rounded-full hover:bg-red-50" title="Löschen"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                            </div>
                                            @if($index === 0) <span class="absolute bottom-2 left-2 bg-primary text-white text-[10px] px-2 py-1 rounded shadow-sm">Hauptbild</span> @endif
                                        </div>
                                    @endif
                                @endforeach
                                <div class="aspect-square rounded-lg relative overflow-hidden bg-gray-50 border-2 border-dashed border-gray-300 hover:border-primary transition group"
                                     x-data="{ isUploading: false }"
                                     x-on:livewire-upload-start="isUploading = true"
                                     x-on:livewire-upload-finish="isUploading = false"
                                     x-on:livewire-upload-error="isUploading = false">
                                    <label class="w-full h-full cursor-pointer flex flex-col items-center justify-center text-gray-400 group-hover:text-primary">
                                        <input type="file" multiple wire:model.live="new_media" class="hidden" accept="image/*" onchange="if(this.files[0].size > 3145728){alert('Zu groß'); this.value=''; return;}">
                                        <svg class="w-8 h-8 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <span class="text-xs font-bold">Bilder +</span>
                                    </label>
                                    <div x-show="isUploading" class="absolute inset-0 bg-white/90 flex flex-col items-center justify-center z-10">
                                        <svg class="animate-spin h-8 w-8 text-primary mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        <span class="text-xs font-bold text-primary">Lade...</span>
                                    </div>
                                </div>
                            </div>
                            {{-- VIDEOS UPLOAD --}}
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-4 border-b border-gray-100 pb-2">Produktvideos</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                @foreach($product->media_gallery ?? [] as $index => $media)
                                    @if(is_array($media) && $media['type'] === 'video')
                                        <div class="relative aspect-square rounded-lg overflow-hidden border border-gray-200 bg-black group">
                                            <video src="{{ asset('storage/'.$media['path']) }}" class="w-full h-full object-cover opacity-80"></video>
                                            <button wire:click="removeMedia({{ $index }})" class="absolute top-2 right-2 bg-white text-red-500 p-1.5 rounded-full hover:bg-red-50 opacity-0 group-hover:opacity-100 transition"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                        </div>
                                    @endif
                                @endforeach
                                <div class="aspect-square rounded-lg relative overflow-hidden bg-gray-50 border-2 border-dashed border-gray-300 hover:border-primary transition group"
                                     x-data="{ isUploading: false }"
                                     x-on:livewire-upload-start="isUploading = true"
                                     x-on:livewire-upload-finish="isUploading = false"
                                     x-on:livewire-upload-error="isUploading = false">
                                    <label class="w-full h-full cursor-pointer flex flex-col items-center justify-center text-gray-400 group-hover:text-primary">
                                        <input type="file" wire:model.live="new_video" class="hidden" accept="video/mp4,video/quicktime">
                                        <svg class="w-8 h-8 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        <span class="text-xs font-bold">Video +</span>
                                    </label>
                                    <div x-show="isUploading" class="absolute inset-0 bg-white/90 flex flex-col items-center justify-center z-10">
                                        <svg class="animate-spin h-8 w-8 text-primary mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        <span class="text-xs font-bold text-primary">Lädt...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- ============================== --}}
                    {{-- SCHRITT 3: ATTRIBUTE & LAGER   --}}
                    {{-- ============================== --}}
                    @if($currentStep === 3)
                        <div class="space-y-6">
                            {{-- Karte: Attribute --}}
                            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
                                <h2 class="text-2xl font-serif text-gray-900 mb-6">3. Attribute</h2>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    @foreach($productAttributes as $key => $val)
                                        <div>
                                            <div class="flex items-center gap-2 mb-2">
                                                <label class="block text-sm font-bold text-gray-800">
                                                    {{ $key }}
                                                    @if($key === 'Gewicht') <span class="text-xs font-normal text-gray-500">(in Gramm)</span> @endif
                                                    *
                                                </label>
                                                @if(isset($infoTexts[$key]))
                                                    <div x-data="{ show: false }" class="relative inline-block ml-1">
                                                        <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                                                        <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">{{ $infoTexts[$key] }}</div>
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Unterscheidung Eingabefeld für Gewicht --}}
                                            @if($key === 'Gewicht')
                                                <input type="number" wire:model.live="productAttributes.{{ $key }}" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="z.B. 250">
                                            @else
                                                <input type="text" wire:model.live="productAttributes.{{ $key }}" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="Pflichtfeld">
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Karte: Lagerbestand --}}
                            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-serif font-bold text-gray-900">Lager & Verfügbarkeit</h3>
                                    <div x-data="{ show: false }" class="relative inline-block ml-1">
                                        <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                                        <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">{{ $infoTexts['Lager'] }}</div>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" id="track_qty" wire:model.live="track_quantity" class="w-5 h-5 rounded text-primary focus:ring-primary border-gray-300">
                                        <label for="track_qty" class="text-gray-800 font-medium cursor-pointer">Bestand automatisch verfolgen</label>
                                    </div>
                                    @if($track_quantity)
                                        <div class="flex items-end gap-6 p-4 bg-gray-50 rounded-xl border border-gray-100 transition-all">
                                            <div class="w-32">
                                                <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Verfügbar</label>
                                                <input type="number" wire:model.live="quantity" class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary">
                                            </div>
                                            <div class="flex-1 pb-2">
                                                <div class="flex items-center gap-2">
                                                    <input type="checkbox" id="continue_selling" wire:model.live="continue_selling" class="w-4 h-4 rounded text-primary border-gray-300">
                                                    <label for="continue_selling" class="text-sm text-gray-600 cursor-pointer">Weitervorkauf erlauben, wenn ausverkauft</label>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- ============================== --}}
                    {{-- SCHRITT 4: LIVE-KONFIGURATOR   --}}
                    {{-- ============================== --}}
                    @if($currentStep === 4)
                        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up space-y-8">
                            <h2 class="text-2xl font-serif text-gray-900">4. Live-Konfigurator</h2>

                            {{-- Overlay Upload --}}
                            <div class="flex flex-col md:flex-row items-center gap-6 p-6 bg-gray-50 rounded-xl border border-gray-200"
                                 x-data="{ isUploading: false }"
                                 x-on:livewire-upload-start="isUploading = true"
                                 x-on:livewire-upload-finish="isUploading = false"
                                 x-on:livewire-upload-error="isUploading = false">
                                <div class="flex-1">
                                    <label class="block text-sm font-bold text-gray-900 mb-1">Vorschau Overlay (PNG)</label>
                                    <p class="text-xs text-gray-500 mb-2">Das transparente PNG, das als Rahmen über dem Produkt liegt.</p>
                                    @if($product->preview_image_path)
                                        <div class="mt-2 text-green-600 text-xs font-bold flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Overlay aktiv
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3">
                                    @if($product->preview_image_path)
                                        <button wire:click="removePreviewImage" class="p-3 text-red-500 hover:bg-red-50 rounded-full transition" title="Overlay entfernen">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                    <div class="flex-shrink-0 relative">
                                        <label class="cursor-pointer bg-white border border-gray-300 hover:border-primary hover:text-primary text-gray-700 px-6 py-3 rounded-full text-sm font-bold shadow-sm transition" :class="{'opacity-50 pointer-events-none': isUploading}">
                                            {{ $product->preview_image_path ? 'Overlay ändern' : 'Overlay wählen' }}
                                            <input type="file" wire:model.live="new_preview_image" accept="image/png" class="hidden">
                                        </label>
                                        <div x-show="isUploading" class="absolute inset-0 bg-white/90 flex flex-col items-center justify-center z-10 rounded-full">
                                            <svg class="animate-spin h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        </div>
                                    </div>
                                </div>
                                @if($product->preview_image_path)
                                    <div class="w-20 h-20 border bg-white rounded-lg shadow-sm flex items-center justify-center overflow-hidden p-2">
                                        <img src="{{ asset('storage/'.$product->preview_image_path) }}" class="object-contain w-full h-full">
                                    </div>
                                @endif
                            </div>

                            {{-- Arbeitsbereich Definieren --}}
                            <div class="bg-gray-50 p-6 rounded-xl border border-gray-100">
                                <h3 class="font-bold text-lg text-gray-900 mb-2">Arbeitsbereich definieren</h3>
                                <p class="text-xs text-gray-500 mb-4">Legen Sie fest, in welchem Bereich Kunden Elemente platzieren dürfen (Angaben in Prozent des Bildes).</p>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">Abstand Oben (%)</label>
                                        <input type="number" wire:model.live="configSettings.area_top" class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">Abstand Links (%)</label>
                                        <input type="number" wire:model.live="configSettings.area_left" class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">Breite (%)</label>
                                        <input type="number" wire:model.live="configSettings.area_width" class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">Höhe (%)</label>
                                        <input type="number" wire:model.live="configSettings.area_height" class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary text-sm">
                                    </div>
                                </div>

                                {{-- VISUELLE VORSCHAU --}}
                                <div class="border border-gray-300 rounded-lg overflow-hidden bg-white relative max-w-sm mx-auto shadow-sm">
                                    <div class="text-xs text-gray-400 text-center py-2 border-b border-gray-100 uppercase font-bold tracking-widest">Live Vorschau</div>
                                    <div class="relative w-full aspect-square bg-gray-100 flex items-center justify-center overflow-hidden">
                                        @if($product->preview_image_path)
                                            <img src="{{ asset('storage/'.$product->preview_image_path) }}" class="absolute inset-0 w-full h-full object-contain z-0">
                                        @else
                                            <div class="text-gray-300 text-xs font-bold">Kein Overlay</div>
                                        @endif

                                        {{-- Der Arbeitsbereich --}}
                                        <div class="absolute border-2 border-green-500 bg-green-500/20 z-10 transition-all duration-300"
                                             style="
                                                top: {{ $configSettings['area_top'] }}%;
                                                left: {{ $configSettings['area_left'] }}%;
                                                width: {{ $configSettings['area_width'] }}%;
                                                height: {{ $configSettings['area_height'] }}%;
                                                {{-- Trick für 'Außenbereich Rot': Ein riesiger Box-Shadow --}}
                                                box-shadow: 0 0 0 9999px rgba(239, 68, 68, 0.2);
                                             ">
                                            {{-- Label innen --}}
                                            <span class="absolute top-0 left-0 bg-green-500 text-white text-[10px] px-1 font-bold">Erlaubt</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Settings Checkboxen --}}
                            <div class="space-y-6 pt-4">
                                <div class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-primary/30 transition bg-white">
                                    <h3 class="font-bold text-lg text-gray-900">Text-Gravur erlauben</h3>
                                    <input type="checkbox" wire:model.live="configSettings.allow_text_pos" class="w-6 h-6 rounded text-primary focus:ring-primary border-gray-300">
                                </div>
                                <div class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-primary/30 transition bg-white">
                                    <h3 class="font-bold text-lg text-gray-900">Logo-Upload erlauben</h3>
                                    <input type="checkbox" wire:model.live="configSettings.allow_logo" class="w-6 h-6 rounded text-primary focus:ring-primary border-gray-300">
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- FOOTER NAVIGATION --}}
                    <div class="flex justify-between pt-8">
                        <button @if($currentStep === 1) disabled @else wire:click="prevStep" @endif class="px-6 py-3 rounded-full font-bold text-gray-500 hover:bg-gray-100 hover:text-gray-900 disabled:opacity-30 disabled:cursor-not-allowed transition">&larr; Zurück</button>
                        @if($currentStep === $totalSteps)
                            <button wire:click="finish" @if(!$canProceed) disabled @endif class="bg-primary text-white px-10 py-3 rounded-full font-bold shadow-lg shadow-primary/30 hover:bg-white hover:text-primary border border-transparent hover:border-primary transition transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">Fertigstellen & Veröffentlichen</button>
                        @else
                            <button wire:click="nextStep" @if(!$canProceed) disabled @endif class="px-8 py-3 rounded-full font-bold shadow-lg transition transform hover:-translate-y-0.5 {{ $canProceed ? 'bg-gray-900 text-white hover:bg-black' : 'bg-gray-300 text-gray-500 cursor-not-allowed' }}">Weiter &rarr;</button>
                        @endif
                    </div>
                </div>

                {{-- RECHTE SPALTE: PREVIEW --}}
                <div class="xl:col-span-5 relative hidden xl:block">
                    <div class="sticky top-32 space-y-6">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest text-center">Shop Vorschau</h3>
                        <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden transform transition hover:scale-[1.01]">
                            <div class="aspect-square bg-gray-50 relative overflow-hidden">
                                @if(!empty($product->media_gallery[0]) && isset($product->media_gallery[0]['path']))
                                    <img src="{{ asset('storage/'.$product->media_gallery[0]['path']) }}" class="w-full h-full object-cover">
                                @else
                                    <div class="absolute inset-0 flex items-center justify-center text-gray-300 font-serif italic">Vorschau</div>
                                @endif
                            </div>
                            <div class="p-8">
                                <h1 class="text-3xl font-serif text-gray-900 leading-tight mb-2">{{ $name ?: 'Produktname' }}</h1>
                                <div class="flex items-baseline gap-4 mb-6">
                                    <span class="text-2xl font-bold text-primary">{{ $price_input ?: '0,00' }} €</span>
                                    <span class="text-xs text-gray-400">
                                        {{ $tax_included ? 'inkl.' : 'zzgl.' }} MwSt.
                                        @if($tax_class === 'reduced') (7%) @elseif($tax_class === 'zero') (0%) @else (19%) @endif
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-y-4 gap-x-8 text-sm text-gray-500 mb-8 pt-6 border-t border-gray-100">
                                    <div><span class="font-bold text-gray-900 block mb-1">Material</span>{{ $productAttributes['Material'] ?? '-' }}</div>
                                    <div><span class="font-bold text-gray-900 block mb-1">Größe</span>{{ $productAttributes['Größe'] ?? '-' }}</div>
                                    @if(!empty($productAttributes['Farbe']))
                                        <div><span class="font-bold text-gray-900 block mb-1">Farbe</span>{{ $productAttributes['Farbe'] }}</div>
                                    @endif
                                    @if($track_quantity && $quantity > 0)
                                        <div class="col-span-2 text-green-600 font-bold text-xs mt-2 flex items-center gap-1">
                                            <span class="w-2 h-2 bg-green-500 rounded-full inline-block"></span> Auf Lager ({{ $quantity }})
                                        </div>
                                    @endif
                                </div>
                                <button class="w-full bg-primary text-white font-bold py-4 rounded-full shadow-lg shadow-primary/20 flex items-center justify-center gap-2 opacity-50 cursor-default">
                                    <span>Jetzt kaufen</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    @endif
</div>

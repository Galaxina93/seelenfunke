{{-- SCRIPT: Global definieren für Drag & Drop Logik im Calculator --}}
@script
<script>
    window.calculatorDragData = function(data) {
        return {
            ...data.wire,
            fontMap: data.fonts,
            alignMap: { 'left': 'text-left', 'center': 'text-center', 'center_h': 'text-center', 'right': 'text-right' },
            area: {
                top: parseFloat(data.config.area_top || 10),
                left: parseFloat(data.config.area_left || 10),
                width: parseFloat(data.config.area_width || 80),
                height: parseFloat(data.config.area_height || 80)
            },
            isDragging: false,
            currentElement: null,
            dragOffsetX: 0,
            dragOffsetY: 0,

            init() {
                this.onDrag = this.handleDrag.bind(this);
                this.stopDrag = this.handleStop.bind(this);
            },

            startDrag(event, type) {
                this.isDragging = true;
                this.currentElement = type;
                if(event.cancelable) event.preventDefault();
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const container = this.$refs.container.getBoundingClientRect();
                let currentPercentX = (type === 'text') ? this.textX : this.logoX;
                let currentPercentY = (type === 'text') ? this.textY : this.logoY;
                currentPercentX = parseFloat(currentPercentX);
                currentPercentY = parseFloat(currentPercentY);
                let currentPixelX = (currentPercentX / 100) * container.width;
                let currentPixelY = (currentPercentY / 100) * container.height;
                let mousePixelX = clientX - container.left;
                let mousePixelY = clientY - container.top;
                this.dragOffsetX = mousePixelX - currentPixelX;
                this.dragOffsetY = mousePixelY - currentPixelY;
                window.addEventListener('mousemove', this.onDrag);
                window.addEventListener('touchmove', this.onDrag, { passive: false });
                window.addEventListener('mouseup', this.stopDrag);
                window.addEventListener('touchend', this.stopDrag);
            },

            handleDrag(event) {
                if (!this.isDragging) return;
                if(event.cancelable) event.preventDefault();
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const container = this.$refs.container.getBoundingClientRect();
                let mouseX = clientX - container.left;
                let mouseY = clientY - container.top;
                let newCenterX = mouseX - this.dragOffsetX;
                let newCenterY = mouseY - this.dragOffsetY;
                let percentX = (newCenterX / container.width) * 100;
                let percentY = (newCenterY / container.height) * 100;
                let minX = this.area.left;
                let maxX = this.area.left + this.area.width;
                let minY = this.area.top;
                let maxY = this.area.top + this.area.height;
                percentX = Math.max(minX, Math.min(maxX, percentX));
                percentY = Math.max(minY, Math.min(maxY, percentY));
                if (this.currentElement === 'text') {
                    this.textX = percentX;
                    this.textY = percentY;
                } else if (this.currentElement === 'logo') {
                    this.logoX = percentX;
                    this.logoY = percentY;
                }
            },

            handleStop() {
                this.isDragging = false;
                this.currentElement = null;
                window.removeEventListener('mousemove', this.onDrag);
                window.removeEventListener('touchmove', this.onDrag);
                window.removeEventListener('mouseup', this.stopDrag);
                window.removeEventListener('touchend', this.stopDrag);
            }
        }
    }
</script>
@endscript

<div class="w-full" x-data="{ showLightbox: false, lightboxImage: '' }" @keydown.escape.window="showLightbox = false">

    {{-- Start Button (Step 0) --}}
    @if($step === 0)
        <div class="text-center py-12 bg-primary/5 rounded-2xl border border-primary/10">
            <h2 class="text-2xl font-serif font-bold text-gray-900 mb-4">Individuelles Angebot erstellen</h2>
            <p class="text-gray-600 mb-8 max-w-xl mx-auto">
                Sie planen eine Großbestellung für Ihren Verein, Ihre Firma oder ein Event?
                Nutzen Sie unseren Konfigurator für eine unverbindliche Preiskalkulation inklusive Staffelpreisen.
            </p>
            <button wire:click="startCalculator" class="bg-primary text-white px-8 py-3 rounded-md font-semibold hover:bg-primary-dark transition shadow-lg transform hover:-translate-y-1">
                Jetzt Kalkulation starten
            </button>
        </div>
    @endif

    {{-- Calculator Container (Step 1-4) --}}
    @if($step > 0)
        <div class="bg-white rounded-xl shadow-2xl overflow-hidden border border-gray-100 my-10" id="calculator-anchor">

            {{-- Header --}}
            <div class="bg-gray-900 p-6 text-white text-center">
                <h2 class="text-white text-xl md:text-2xl font-serif tracking-wide">
                    @if($step == 1) Produktauswahl & Kalkulation
                    @elseif($step == 2) Design-Vorschau
                    @elseif($step == 3) Angebot anfordern
                    @elseif($step == 4) Anfrage erfolgreich!
                    @endif
                </h2>
                {{-- Steps Anzeige --}}
                @if($step < 4)
                    <div class="flex justify-center gap-2 mt-3 text-xs uppercase tracking-widest text-gray-400">
                        <span class="{{ $step == 1 ? 'text-white font-bold' : '' }}">1. Wahl</span>
                        <span>&rarr;</span>
                        <span class="{{ $step == 2 ? 'text-white font-bold' : '' }}">2. Design</span>
                        <span>&rarr;</span>
                        <span class="{{ $step == 3 ? 'text-white font-bold' : '' }}">3. Kontaktdaten</span>
                    </div>
                @endif
            </div>

            {{-- STEP 1: Auswahl --}}
            @if($step === 1)
                <div class="p-6 md:p-8 animate-fade-in">

                    {{-- Liste der ausgewählten Produkte --}}
                    @if(count($cartItems) > 0)
                        <div class="mb-12 bg-yellow-50/50 p-6 rounded-xl border border-yellow-100">
                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                Ihre aktuelle Kalkulation
                            </h3>

                            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm mb-6">
                                <table class="hidden md:table w-full text-sm text-left">
                                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                                    <tr>
                                        <th class="px-4 py-3">Produkt</th>
                                        <th class="px-4 py-3">Details</th>
                                        <th class="px-4 py-3 text-center">Menge</th>
                                        <th class="px-4 py-3 text-right">Summe</th>
                                        <th class="px-4 py-3 text-right"></th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                    @foreach($cartItems as $index => $item)
                                        <tr class="hover:bg-gray-50/50 transition">
                                            <td class="px-4 py-3 font-bold align-middle">{{ $item['name'] }}</td>
                                            <td class="px-4 py-3 align-middle text-gray-500 text-xs">
                                                {{ Str::limit($item['text'], 25) ?: 'Keine Gravur' }}
                                            </td>
                                            <td class="px-4 py-3 text-center align-middle font-bold">{{ $item['qty'] }}</td>
                                            <td class="px-4 py-3 text-right align-middle font-mono">
                                                {{ number_format($item['calculated_total'], 2, ',', '.') }} €
                                            </td>
                                            <td class="px-4 py-3 text-right align-middle">
                                                <div class="flex justify-end gap-2">
                                                    <button wire:click="editItem({{ $index }})" class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-gray-300 rounded text-xs font-bold text-gray-700 hover:bg-gray-50 hover:text-primary hover:border-primary transition shadow-sm">
                                                        Bearbeiten
                                                    </button>
                                                    <button wire:click="removeItem({{ $index }})" class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-gray-300 rounded text-xs font-bold text-red-600 hover:bg-red-50 hover:border-red-300 transition shadow-sm">
                                                        Löschen
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                                <div class="md:hidden divide-y divide-gray-100">
                                    @foreach($cartItems as $index => $item)
                                        <div class="p-4 flex flex-col gap-3">
                                            <div class="flex justify-between items-start">
                                                <span class="font-bold text-gray-900 text-base">{{ $item['name'] }}</span>
                                                <span class="font-bold text-gray-900 font-mono">
                                                    {{ number_format($item['calculated_total'], 2, ',', '.') }} €
                                                </span>
                                            </div>
                                            <div class="flex justify-between items-center text-sm">
                                                <div class="text-gray-500 text-xs">
                                                    {{ Str::limit($item['text'], 30) ?: 'Keine Gravur' }}
                                                </div>
                                                <div class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs font-bold whitespace-nowrap">
                                                    {{ $item['qty'] }} Stk.
                                                </div>
                                            </div>
                                            <div class="flex justify-end items-center gap-2 mt-1 pt-2 border-t border-gray-50">
                                                <button wire:click="editItem({{ $index }})" class="flex-1 inline-flex justify-center items-center gap-1 px-3 py-2 bg-white border border-gray-300 rounded text-xs font-bold text-gray-700 hover:bg-gray-50 hover:text-primary transition">
                                                    Bearbeiten
                                                </button>
                                                <button wire:click="removeItem({{ $index }})" class="flex-1 inline-flex justify-center items-center gap-1 px-3 py-2 bg-white border border-gray-300 rounded text-xs font-bold text-red-600 hover:bg-red-50 transition">
                                                    Entfernen
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex flex-col md:flex-row justify-between items-end gap-6">
                                <div class="bg-white border border-gray-200 rounded-lg p-4 w-full md:w-auto">
                                    <div class="flex items-center">
                                        <input wire:model.live="isExpress" id="express" type="checkbox" class="w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary">
                                        <label for="express" class="ml-2 font-bold text-gray-700 text-sm">Express-Bearbeitung (+25,00 €)</label>
                                    </div>
                                    @if($isExpress)
                                        <input wire:model="deadline" type="date" class="mt-2 block w-full rounded border-gray-300 text-sm">
                                    @endif
                                </div>
                                <div class="text-right w-full md:w-auto">
                                    <div class="text-sm text-gray-500">Netto: {{ number_format($totalNetto, 2, ',', '.') }} €</div>

                                    {{-- Detaillierte Versandanzeige --}}
                                    @if($shippingCost > 0)
                                        <div class="text-sm text-gray-500">
                                            Versand ({{ $form['country'] }}): {{ number_format($shippingCost, 2, ',', '.') }} €
                                        </div>

                                        {{-- Upselling Hinweis für Deutschland --}}
                                        @if($form['country'] === 'DE')
                                            @php
                                                // Wir rechnen zurück: Gesamtsumme - Versand = Warenwert Brutto
                                                $warenwert = $totalBrutto - $shippingCost;
                                                $missing = 50.00 - $warenwert;
                                            @endphp
                                            @if($missing > 0.01)
                                                <div class="text-xs text-amber-600 font-medium mt-1">
                                                    Noch <strong>{{ number_format($missing, 2, ',', '.') }} €</strong> bis zum kostenlosen Versand!
                                                </div>
                                            @endif
                                        @endif
                                    @else
                                        <div class="text-sm text-green-600 font-bold">
                                            Versand ({{ $form['country'] }}): Kostenlos
                                        </div>
                                    @endif

                                    <div class="text-sm text-gray-500 mt-1">MwSt: {{ number_format($totalMwst, 2, ',', '.') }} €</div>
                                    <div class="text-2xl font-bold text-primary mb-4">Brutto: {{ number_format($totalBrutto, 2, ',', '.') }} €</div>
                                    <button wire:click="goNext" class="w-full md:w-auto bg-gray-900 text-white px-6 py-3 rounded hover:bg-black transition font-bold shadow-lg">
                                        Angebot anfordern
                                    </button>
                                    @error('cart') <div class="text-red-500 text-xs mt-2">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- PRODUKT KATALOG --}}
                    <div class="mb-10">
                        <h3 class="text-lg font-bold text-gray-800 border-b border-gray-200 pb-2 mb-4 uppercase tracking-wider">
                            Unser Sortiment
                        </h3>

                        @if(empty($dbProducts))
                            <div class="flex flex-col items-center justify-center py-12 text-gray-500 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                                <span class="text-sm font-medium">Aktuell keine Produkte verfügbar.</span>
                            </div>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
                                @foreach($dbProducts as $product)
                                    <div class="relative group flex flex-row items-stretch p-3 md:p-4 bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md hover:border-primary/30 transition-all duration-200 cursor-pointer active:scale-[0.99]"
                                         wire:click="openConfig('{{ $product['id'] }}')"
                                         wire:loading.class="opacity-50 pointer-events-none"
                                         wire:target="openConfig('{{ $product['id'] }}')">

                                        {{-- Bild --}}
                                        <div class="flex-shrink-0 mr-4 self-start">
                                            @if(!empty($product['image']))
                                                <img src="{{ asset($product['image']) }}" class="w-24 h-24 md:w-24 md:h-24 object-cover rounded-lg bg-gray-50 border border-gray-100">
                                            @else
                                                <div class="w-24 h-24 md:w-24 md:h-24 bg-gray-50 rounded-lg border border-gray-100 flex items-center justify-center text-gray-300">
                                                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Inhalt --}}
                                        <div class="flex-1 min-w-0 flex flex-col justify-between">
                                            <div>
                                                <h4 class="font-bold text-gray-900 group-hover:text-primary transition truncate">{{ $product['name'] }}</h4>
                                                <p class="text-xs text-gray-500 mt-1 line-clamp-2 leading-relaxed">{{ $product['desc'] }}</p>
                                            </div>
                                            <div class="mt-3">
                                                <div class="flex flex-wrap items-baseline gap-x-1 justify-between items-end">
                                                    <div>
                                                        <div class="flex flex-wrap items-baseline gap-x-1">
                                                            <span class="text-sm font-bold text-primary">ab {{ number_format($product['display_price'], 2, ',', '.') }} €</span>
                                                            <span class="text-[10px] uppercase tracking-wide text-gray-400">{{ $product['tax_included'] ? 'inkl.' : 'zzgl.' }} MwSt.</span>
                                                        </div>
                                                    </div>
                                                    <div class="hidden md:flex w-8 h-8 rounded-full bg-white/90 backdrop-blur border border-gray-200 text-gray-400 items-center justify-center shadow-sm transition-all duration-200 group-hover:border-primary/60 group-hover:text-primary group-hover:shadow-md group-hover:scale-[1.03]">
                                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                    </div>
                                                </div>

                                                {{-- Staffelpreise --}}
                                                @if(!empty($product['tier_pricing']))
                                                    <div class="mt-2 pt-2 border-t border-gray-50">
                                                        <span class="text-[10px] font-bold uppercase text-green-600 block mb-1">Staffelpreise verfügbar:</span>
                                                        <div class="flex flex-wrap gap-2">
                                                            @foreach(collect($product['tier_pricing'])->sortBy('qty')->take(4) as $tier)
                                                                <span class="text-[10px] bg-green-50 text-green-700 px-1.5 py-0.5 rounded border border-green-100">
                                                                    ab {{ $tier['qty'] }} Stk: -{{ 0 + $tier['percent'] }}%
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- STEP 2: KONFIGURATION --}}
            @elseif($step === 2)
                <div class="h-full min-h-[600px] bg-white rounded-xl overflow-hidden animate-fade-in">
                    <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                        <h3 class="font-bold text-gray-800">Artikel anpassen</h3>
                        <button wire:click="cancelConfig" class="text-sm text-gray-500 hover:text-red-500">Abbrechen</button>
                    </div>
                    <livewire:shop.configurator
                        :product="$currentProduct['id']"
                        :initialData="$currentConfig"
                        context="calculator"
                        :key="'calc-conf-'.$currentProduct['id'].'-'.time()"
                    />
                </div>

                {{-- STEP 3: KONTAKTDATEN (Angebot anfordern) --}}
            @elseif($step === 3)
                <div class="p-8 animate-fade-in max-w-3xl mx-auto">
                    <div class="text-center mb-8">
                        <h3 class="text-xl font-bold text-gray-900">Fast geschafft!</h3>
                        <p class="text-gray-500">Geben Sie Ihre Kontaktdaten ein, damit wir Ihnen das Angebot als PDF zusenden können.</p>
                    </div>

                    <form wire:submit.prevent="submit" class="grid grid-cols-1 gap-4 sm:grid-cols-2 bg-gray-50 p-4 sm:p-6 rounded-xl border border-gray-100">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Vorname *</label>
                            <input wire:model.live="form.vorname" type="text" required class="w-full rounded border-gray-300 focus:ring-primary focus:border-primary p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Nachname *</label>
                            <input wire:model.live="form.nachname" type="text" required class="w-full rounded border-gray-300 focus:ring-primary focus:border-primary p-2">
                        </div>
                        <div class="col-span-1 sm:col-span-2">
                            <label class="block text-sm font-bold text-gray-700 mb-1">Firma / Verein (Optional)</label>
                            <input wire:model.live="form.firma" type="text" class="w-full rounded border-gray-300 focus:ring-primary focus:border-primary p-2">
                        </div>
                        <div class="col-span-1 sm:col-span-2">
                            <label class="block text-sm font-bold text-gray-700 mb-1">E-Mail für Angebot *</label>
                            <input wire:model.live="form.email" type="email" required class="w-full rounded border-gray-300 focus:ring-primary focus:border-primary p-2">
                        </div>

                        {{-- LAND AUSWAHL (DYNAMISCH) --}}
                        <div class="col-span-1 sm:col-span-2">
                            <label class="block text-sm font-bold text-gray-700 mb-1">Land für Versand *</label>
                            <select wire:model.live="form.country" class="w-full rounded border-gray-300 focus:ring-primary focus:border-primary p-2">
                                @foreach(config('shop.countries', ['DE' => 'Deutschland']) as $code => $name)
                                    {{-- Sicherheitshalber Konfigurations-Keys ausschließen, falls vorhanden --}}
                                    @if($code !== 'default_tax_rate')
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endif
                                @endforeach
                            </select>

                            {{-- Dynamischer Hinweis unter dem Dropdown --}}
                            <div class="mt-1.5 text-xs text-gray-500 animate-fade-in">
                                @if($form['country'] === 'DE')
                                    <span class="text-green-600 font-bold">Tipp:</span> Versandkostenfrei ab 50,00 € Warenwert (DE). Sonst pauschal 4,90 €.
                                @else
                                    <span class="text-blue-600 font-bold">Hinweis:</span> Internationale Versandkosten werden nach Gewicht & Zone berechnet.
                                @endif
                            </div>
                        </div>

                        <div class="col-span-1 sm:col-span-2 pt-4 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
                            <button type="button" wire:click="goBack" class="text-gray-500 underline text-sm text-center sm:text-left w-full sm:w-auto">Zurück</button>
                            <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded font-bold hover:bg-green-700 shadow-lg flex items-center justify-center gap-2 w-full sm:w-auto transition transform hover:-translate-y-0.5">
                                <svg wire:loading class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span>
                                    <span wire:loading.remove>Angebot jetzt anfordern</span>
                                    <span wire:loading>Sende Anfrage…</span>
                                </span>
                            </button>
                        </div>
                    </form>

                </div>

                {{-- STEP 4: SUCCESS --}}
            @elseif($step === 4)
                <div class="p-12 text-center animate-fade-in-up">
                    <div class="inline-flex items-center justify-center w-24 h-24 bg-green-100 rounded-full mb-6">
                        <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-800 mb-4">Anfrage erfolgreich!</h3>
                    <p class="text-gray-600 mb-8 max-w-lg mx-auto leading-relaxed">
                        Wir haben Ihre Daten erhalten. Ein unverbindliches Angebot wurde soeben als PDF an
                        <strong>{{ $form['email'] }}</strong> gesendet.
                    </p>
                    <button wire:click="restartCalculator" class="text-primary font-bold hover:underline">Neue Berechnung starten</button>
                </div>
            @endif

        </div>
    @endif

    {{-- Lightbox --}}
    <div x-show="showLightbox" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4" x-transition.opacity>
        <div @click.outside="showLightbox = false" class="relative">
            <img :src="lightboxImage" class="max-w-full max-h-[90vh] rounded shadow-none">
            <button @click="showLightbox = false" class="absolute -top-10 right-0 text-white text-3xl hover:text-gray-300">&times;</button>
        </div>
    </div>
</div>

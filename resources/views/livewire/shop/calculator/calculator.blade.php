@script
<script>
    window.calculatorDragData = function(data) {
        return {
            ...data.wire,
            fontMap: data.fonts,
            alignMap: {
                'left': 'text-left',
                'center': 'text-center',
                'center_h': 'text-center',
                'right': 'text-right'
            },
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

                Livewire.on('scroll-top', () => {
                    const anchor = document.getElementById('calculator-anchor');
                    if (anchor) {
                        anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            },

            startDrag(event, type) {
                this.isDragging = true;
                this.currentElement = type;

                if (event.cancelable) event.preventDefault();

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
                if (event.cancelable) event.preventDefault();

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
    @if($step === 0)
        <div class="text-center py-12 bg-primary/5 rounded-2xl border border-primary/10 px-6">
            <h2 class="text-2xl font-serif font-bold text-gray-900 mb-4">Individuelles Angebot erstellen</h2>
            <p class="text-gray-600 mb-6 max-w-xl mx-auto">
                Sie planen eine Großbestellung für Ihren Verein, Ihre Firma oder ein Event? Nutzen Sie unseren Konfigurator für eine unverbindliche Preiskalkulation inklusive Staffelpreisen.
            </p>

            <div class="max-w-md mx-auto mb-8 bg-white p-4 rounded-xl border border-gray-100 shadow-sm text-left">
                <label class="flex items-start gap-3 cursor-pointer group">
                    <input type="checkbox" wire:model.live="agb_accepted" class="mt-1 w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary">
                    <span class="text-sm text-gray-600 leading-relaxed group-hover:text-gray-900 transition-colors">
                        Ich bestätige, dass der Konfigurator als **Visualisierungshilfe** dient. Abweichungen in Farbe und Platzierung (keine mm-Präzision) sind möglich. Es gelten die <a href="/agb#konfigurator" target="_blank" class="text-primary underline font-bold">Besonderen Bestimmungen für Konfigurationen</a>.
                    </span>
                </label>
                @error('agb')
                <p class="mt-2 text-xs text-red-600 font-bold flex items-center gap-1">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    {{ $message }}
                </p>
                @enderror
            </div>

            <button wire:click="startCalculator" @class([
                'px-8 py-3 rounded-md font-semibold transition shadow-lg transform hover:-translate-y-1',
                'bg-primary text-white hover:bg-primary-dark' => $agb_accepted,
                'bg-gray-300 text-gray-500 cursor-not-allowed' => !$agb_accepted
            ])>
                Jetzt Kalkulation starten
            </button>
        </div>
    @endif

    @if($step > 0)
        <div class="relative bg-white rounded-xl shadow-2xl overflow-hidden border border-gray-100 my-10 min-h-[400px]" id="calculator-anchor">

            <div class="bg-gray-900 p-6 text-white text-center">
                <h2 class="text-white text-xl md:text-2xl font-serif tracking-wide">
                    @if($step == 1) Produktauswahl & Kalkulation
                    @elseif($step == 2) Design-Vorschau
                    @elseif($step == 3) Angebot anfordern
                    @elseif($step == 4) Anfrage erfolgreich!
                    @endif
                </h2>

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

            @if($step === 1)
                <div class="p-6 md:p-8 animate-fade-in">
                    @include('livewire.shop.calculator.partials.selected_items')
                    @include('livewire.shop.calculator.partials.sortiment')
                </div>
            @elseif($step === 2)
                <div class="h-full min-h-[600px] bg-white rounded-xl overflow-hidden animate-fade-in flex flex-col relative">
                    <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center shrink-0">
                        <h3 class="font-bold text-gray-800">Artikel anpassen</h3>
                        <button wire:click="cancelConfig" class="text-sm text-gray-500 hover:text-red-500">Abbrechen</button>
                    </div>

                    @if($showTemplateSelection)
                        <div class="flex-1 flex flex-col items-center justify-center p-8 bg-gray-50/50">
                            <h2 class="text-3xl font-serif font-bold text-gray-900 mb-8 text-center">Wie möchten Sie starten?</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl w-full">
                                <button type="button" wire:click="openTemplatesList" class="bg-white border border-gray-200 p-8 rounded-3xl shadow-sm hover:shadow-xl hover:border-primary/50 transition-all group text-left flex flex-col items-center text-center">
                                    <div class="w-20 h-20 bg-primary/10 rounded-full flex items-center justify-center text-primary mb-6 group-hover:scale-110 transition-transform">
                                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                                    </div>
                                    <h3 class="text-xl font-bold text-gray-900 mb-2">Vorlage nutzen</h3>
                                    <p class="text-gray-500 text-sm">Wählen Sie aus unseren liebevoll gestalteten Vorlagen und passen Sie nur noch die Texte an.</p>
                                </button>
                                <button type="button" wire:click="startCustomConfig" class="bg-white border border-gray-200 p-8 rounded-3xl shadow-sm hover:shadow-xl hover:border-gray-400 transition-all group text-left flex flex-col items-center text-center">
                                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 mb-6 group-hover:scale-110 transition-transform">
                                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </div>
                                    <h3 class="text-xl font-bold text-gray-900 mb-2">Selber Konfigurieren</h3>
                                    <p class="text-gray-500 text-sm">Starten Sie mit einem leeren Produkt und lassen Sie Ihrer Kreativität freien Lauf.</p>
                                </button>
                            </div>
                        </div>
                    @elseif($showTemplatesList)
                        <div class="flex-1 p-8 bg-gray-50/50 overflow-y-auto">
                            <div class="flex items-center justify-between mb-8 max-w-5xl mx-auto">
                                <h2 class="text-2xl font-serif font-bold text-gray-900">Vorlagen für {{ $currentProduct['name'] ?? 'Produkt' }}</h2>
                                <button wire:click="$set('showTemplatesList', false); $set('showTemplateSelection', true)" class="text-sm font-bold text-gray-500 hover:text-gray-900 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                    Zurück
                                </button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 max-w-5xl mx-auto">
                                @foreach($productTemplates as $tpl)
                                    <div wire:click="selectTemplate('{{ $tpl['id'] }}')" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg hover:border-primary transition-all cursor-pointer group">
                                        <div class="h-48 bg-gray-100 relative overflow-hidden flex items-center justify-center">
                                            @if(!empty($tpl['preview_image']))
                                                <img src="{{ asset('storage/'.$tpl['preview_image']) }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                            @else
                                                <svg class="w-12 h-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            @endif
                                            <div class="absolute inset-0 bg-primary/0 group-hover:bg-primary/10 transition-colors"></div>
                                        </div>
                                        <div class="p-5 text-center">
                                            <h3 class="font-bold text-gray-900 text-lg group-hover:text-primary transition-colors">{{ $tpl['name'] }}</h3>
                                            <p class="text-xs text-gray-500 mt-1">Vorlage auswählen</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <livewire:shop.configurator.configurator
                            :product="$currentProduct['id']"
                            :initialData="$currentConfig"
                            context="calculator"
                            :key="'calc-conf-'.$currentProduct['id'].'-'.time()"
                        />
                    @endif
                </div>
            @elseif($step === 3)
                @include('livewire.shop.calculator.partials.contact_form')
            @elseif($step === 4)
                <div class="p-12 text-center animate-fade-in-up">
                    <div class="inline-flex items-center justify-center w-24 h-24 bg-green-100 rounded-full mb-6">
                        <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-800 mb-4">Anfrage erfolgreich!</h3>
                    <p class="text-gray-600 mb-8 max-w-lg mx-auto leading-relaxed">
                        Wir haben Ihre Daten erhalten. Ein unverbindliches Angebot wurde soeben als PDF an <strong>{{ $form['email'] }}</strong> gesendet.
                    </p>
                    <button wire:click="restartCalculator" class="text-primary font-bold hover:underline">Neue Berechnung starten</button>
                </div>
            @endif
        </div>
    @endif

    <div x-show="showLightbox" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4" x-transition.opacity>
        <div @click.outside="showLightbox = false" class="relative">
            <img :src="lightboxImage" class="max-w-full max-h-[90vh] rounded shadow-none">
            <button @click="showLightbox = false" class="absolute -top-10 right-0 text-white text-3xl hover:text-gray-300">&times;</button>
        </div>
    </div>
</div>

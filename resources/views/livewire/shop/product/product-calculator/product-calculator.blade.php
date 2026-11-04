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

                window.addEventListener('scroll-top', () => {
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
    };

    window.sfRandomizeCardSparkles = function(cardEl) {
        const zones = [
            { top: [10, 28], left: [10, 35] },
            { top: [10, 28], left: [62, 86] },
            { top: [36, 60], left: [8, 28] },
            { top: [36, 60], left: [68, 88] },
            { top: [66, 86], left: [12, 40] },
            { top: [66, 86], left: [58, 86] },
        ];
        const rand = (min, max) => (min + Math.random() * (max - min)).toFixed(1) + '%';
        cardEl.querySelectorAll('.sf-card-sparkle').forEach((el, i) => {
            const z = zones[i % zones.length];
            el.style.top = rand(z.top[0], z.top[1]);
            el.style.left = rand(z.left[0], z.left[1]);
            el.style.right = 'auto';
            el.style.bottom = 'auto';
        });
    };

    window.sfCardHoverEnter = function(cardEl) {
        clearTimeout(cardEl._sfLeaveTimer);
        clearTimeout(cardEl._sfCleanupTimer);
        if (!cardEl._sfBusy) {
            window.sfRandomizeCardSparkles(cardEl);
            void cardEl.offsetWidth;
        }
        cardEl._sfBusy = true;
        if (!cardEl.classList.contains('sf-is-active')) {
            cardEl._sfEnterTime = performance.now();
            cardEl.classList.add('sf-is-active');
        }
        const hoverVid = cardEl.querySelector('video[data-sf-hover-video]');
        if (hoverVid) {
            hoverVid.play().catch(() => {});
        }
    };

    window.sfCardHoverLeave = function(cardEl) {
        clearTimeout(cardEl._sfLeaveTimer);
        clearTimeout(cardEl._sfCleanupTimer);
        const minForward = 950;
        const elapsed = cardEl._sfEnterTime ? (performance.now() - cardEl._sfEnterTime) : minForward;
        const waitMs = Math.max(0, minForward - elapsed);
        cardEl._sfLeaveTimer = setTimeout(() => {
            cardEl.classList.remove('sf-is-active');
            const hoverVid = cardEl.querySelector('video[data-sf-hover-video]');
            if (hoverVid) {
                hoverVid.pause();
                hoverVid.currentTime = 0;
            }
            cardEl._sfCleanupTimer = setTimeout(() => {
                cardEl._sfBusy = false;
            }, 1350);
        }, waitMs);
    };
</script>
@endscript

<div class="w-full" x-data="{ showLightbox: false, lightboxImage: '' }" @keydown.escape.window="showLightbox = false" @scroll-top.window="setTimeout(() => { document.getElementById('calculator-anchor')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 50)">
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
                        Ich bestätige, dass der Konfigurator als <strong>Visualisierungshilfe</strong> dient (handwerkliche Toleranzen in Farbe und Platzierung möglich) und ich für hochgeladene Motive/Texte <strong>alle erforderlichen Urheber- &amp; Markenrechte</strong> besitze sowie Mein Seelenfunke von Ansprüchen Dritter freistelle. Es gelten die <a href="/agb#konfigurator" target="_blank" class="text-primary underline font-bold">Besonderen Bestimmungen &amp; Freistellungsregeln (§ 3 AGB)</a>.
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
                    @include('livewire.shop.product.product-calculator.partials.selected_items')
                    @include('livewire.shop.product.product-calculator.partials.sortiment')
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
                                        <div class="aspect-square w-full bg-gray-100 relative overflow-hidden flex items-center justify-center">
                                            @if(!empty($tpl['preview_image']))
                                                <img src="{{ \App\Models\Product\ProductTemplate::resolveImageUrl($tpl['preview_image']) }}"
                                                     @if($tpl['is_ai_generated'] ?? true) data-is-ai-generated="true" @endif
                                                     class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500">
                                                @if($tpl['is_ai_generated'] ?? true)
                                                    <span style="font-size: 6.5px; line-height: 1; padding: 1.5px 4px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                                          class="pointer-events-none select-none absolute bottom-1.5 right-1.5 z-10 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                                        KI-generiert
                                                    </span>
                                                @endif
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
                        <livewire:shop.product.product-configurator.product-configurator
                            :product="$currentProduct['id']"
                            :initialData="$currentConfig"
                            context="calculator"
                            :key="'calc-conf-'.$currentProduct['id'].'-'.time()"
                        />
                    @endif
                </div>
            @elseif($step === 3)
                @include('livewire.shop.product.product-calculator.partials.contact_form')
            @elseif($step === 4)
                <div class="p-12 text-center animate-fade-in-up">
                    <div class="inline-flex items-center justify-center w-24 h-24 bg-amber-50 border border-amber-200 rounded-full mb-6">
                        <svg class="w-12 h-12 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-3xl font-serif font-bold text-gray-900 mb-3">Anfrage erfolgreich!</h3>
                    @if(!empty($lastQuoteNumber))
                        <p class="text-sm font-mono font-bold text-primary mb-3">Angebotsnummer: {{ $lastQuoteNumber }}</p>
                    @endif
                    <p class="text-gray-600 mb-8 max-w-lg mx-auto leading-relaxed">
                        Wir haben Ihre Daten erhalten. Ein unverbindliches Angebot wurde soeben als PDF an <strong>{{ $form['email'] }}</strong> gesendet.
                    </p>
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                        @if(!empty($lastQuoteToken))
                            <a href="{{ route('quote.accept', ['token' => $lastQuoteToken]) }}"
                               data-sf-portal="checkout"
                               class="px-7 py-3 rounded-full bg-primary text-gray-900 font-bold text-sm hover:bg-primary-dark hover:text-white transition shadow-md">
                                Angebot direkt online ansehen →
                            </a>
                        @endif
                        <button wire:click="restartCalculator" class="px-7 py-3 rounded-full bg-gray-900 text-white font-bold text-sm hover:bg-gray-800 transition">
                            Neue Berechnung starten
                        </button>
                    </div>
                </div>
            @endif

            @if($step >= 3)
                <div data-step-four="{{ $step === 4 ? '1' : '0' }}"
                     x-data="{
                        isActive: false,
                        phase: 0,
                        showSuccessDetails: false,
                        _magicRaf: null,
                        _revealTimer: null,
                        magicStartedAt: 0,
                        initQuoteStage() {
                            if (this.$el.dataset.stepFour === '1') {
                                this.isActive = true;
                                this.phase = 1;
                                document.body.style.overflow = 'hidden';
                                this.$nextTick(() => {
                                    this.runQuoteSparksCanvas();
                                    requestAnimationFrame(() => { this.phase = 1; });
                                });
                                this._revealTimer = setTimeout(() => {
                                    this.showSuccessDetails = true;
                                }, 350);
                            }
                        },
                        startQuoteMagic() {
                            if (this._revealTimer) {
                                clearTimeout(this._revealTimer);
                                this._revealTimer = null;
                            }
                            this.isActive = true;
                            this.showSuccessDetails = false;
                            this.phase = 0;
                            this.magicStartedAt = Date.now();
                            document.body.style.overflow = 'hidden';
                            this.$nextTick(() => {
                                this.runQuoteSparksCanvas();
                                requestAnimationFrame(() => { this.phase = 1; });
                            });
                        },
                        stopQuoteMagic() {
                            if (this._revealTimer) {
                                clearTimeout(this._revealTimer);
                                this._revealTimer = null;
                            }
                            this.isActive = false;
                            this.showSuccessDetails = false;
                            this.phase = 0;
                            document.body.style.overflow = '';
                            if (this._magicRaf) {
                                cancelAnimationFrame(this._magicRaf);
                                this._magicRaf = null;
                            }
                        },
                        revealQuoteSuccess() {
                            this.isActive = true;
                            document.body.style.overflow = 'hidden';
                            if (!this._magicRaf) {
                                this.$nextTick(() => this.runQuoteSparksCanvas());
                            }
                            this.phase = 1;
                            const elapsed = this.magicStartedAt ? (Date.now() - this.magicStartedAt) : 1000;
                            const waitMs = Math.max(150, 1000 - elapsed);
                            if (this._revealTimer) clearTimeout(this._revealTimer);
                            this._revealTimer = setTimeout(() => {
                                this.showSuccessDetails = true;
                            }, waitMs);
                        },
                        runQuoteSparksCanvas() {
                            const canvas = this.$refs.quoteSparksCanvas;
                            if (!canvas) return;
                            if (this._magicRaf) cancelAnimationFrame(this._magicRaf);
                            const ctx = canvas.getContext('2d', { alpha: true });
                            const dpr = Math.min(window.devicePixelRatio || 1, 1.5);
                            let w = window.innerWidth;
                            let h = window.innerHeight;
                            canvas.width = w * dpr;
                            canvas.height = h * dpr;
                            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                            const centerX = () => w / 2;
                            const centerY = () => this.showSuccessDetails ? Math.min(h * 0.42, 340) : h / 2;

                            const particles = Array.from({ length: 64 }, (_, i) => {
                                const angle = Math.random() * Math.PI * 2;
                                const dist = 60 + Math.random() * Math.max(w, h) * 0.4;
                                return {
                                    x: (w / 2) + Math.cos(angle) * dist,
                                    y: (h / 2) + Math.sin(angle) * dist,
                                    orbitAngle: angle,
                                    orbitRadius: dist,
                                    baseRadius: 95 + (i % 5) * 22,
                                    expandedRadius: 155 + (i % 7) * 42,
                                    orbitSpeed: (0.032 + Math.random() * 0.035) * (i % 2 === 0 ? 1 : -1),
                                    r: 1.8 + Math.random() * 3.2,
                                    twinkle: Math.random() * Math.PI * 2
                                };
                            });

                            const animate = () => {
                                if (!this.isActive) return;
                                if (window.innerWidth !== w || window.innerHeight !== h) {
                                    w = window.innerWidth;
                                    h = window.innerHeight;
                                    canvas.width = w * dpr;
                                    canvas.height = h * dpr;
                                    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                                }
                                ctx.clearRect(0, 0, w, h);
                                const cx = centerX();
                                const cy = centerY();
                                const speedFactor = this.showSuccessDetails ? 0.55 : 1;

                                for (const p of particles) {
                                    p.orbitAngle += p.orbitSpeed * speedFactor;
                                    p.twinkle += 0.065;
                                    const targetRadius = this.showSuccessDetails ? p.expandedRadius : p.baseRadius;
                                    p.orbitRadius = targetRadius + (p.orbitRadius - targetRadius) * 0.95;
                                    p.x = cx + Math.cos(p.orbitAngle) * p.orbitRadius;
                                    p.y = cy + Math.sin(p.orbitAngle) * p.orbitRadius;
                                    const alpha = 0.42 + 0.55 * (0.5 + 0.5 * Math.sin(p.twinkle));
                                    ctx.fillStyle = 'rgba(251, 191, 36, ' + alpha + ')';
                                    ctx.beginPath();
                                    ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                                    ctx.fill();
                                }
                                this._magicRaf = requestAnimationFrame(animate);
                            };
                            this._magicRaf = requestAnimationFrame(animate);
                        }
                     }"
                     x-init="initQuoteStage()"
                     @quote-submitting.window="startQuoteMagic()"
                     @quote-submit-failed.window="stopQuoteMagic()"
                     @quote-submitted.window="revealQuoteSuccess()"
                     @quote-restarted.window="stopQuoteMagic()"
                     x-show="isActive"
                     x-cloak
                     class="fixed inset-0 z-[9999] overflow-y-auto overflow-x-hidden bg-[#fdf8ed]/95 backdrop-blur-md transition-opacity duration-300"
                     wire:key="calculator-quote-magic-stage">

                    {{-- RADIALER GOLD-GLOW HINTERGRUND --}}
                    <div class="fixed inset-0 pointer-events-none z-0"
                         style="background: radial-gradient(circle at 50% 38%, rgba(245, 158, 11, 0.22) 0%, rgba(197, 160, 89, 0.08) 44%, transparent 74%);">
                    </div>

                    {{-- 60 FPS SEELENFUNKEN PARTIKEL-CANVAS --}}
                    <div wire:ignore class="fixed inset-0 pointer-events-none z-10">
                        <canvas x-ref="quoteSparksCanvas" class="w-full h-full"></canvas>
                    </div>

                    {{-- ZENTRIERTE BÜHNE FÜR ÜBERGANG & ENDINFOS --}}
                    <div class="relative z-20 min-h-full w-full flex flex-col items-center justify-center px-4 sm:px-6 py-10 sm:py-14">

                        <div class="flex flex-col items-center justify-center text-center max-w-lg mx-auto transition-all duration-700 transform-gpu"
                             :class="phase >= 1 ? 'scale-100 opacity-100' : 'scale-90 opacity-0'">

                            <div class="relative flex items-center justify-center transition-all duration-[850ms] ease-[cubic-bezier(0.22,1,0.36,1)]"
                                 :class="showSuccessDetails ? 'w-36 h-36 sm:w-44 sm:h-44 mb-3' : 'w-52 h-52 sm:w-64 sm:h-64 mb-4'"
                                 wire:key="quote-success-image-container">
                                <div class="absolute inset-2 rounded-full bg-gradient-to-tr from-amber-400/25 via-primary/20 to-yellow-300/25 blur-2xl animate-pulse"></div>
                                <svg class="absolute inset-0 w-full h-full text-amber-500/45 animate-[spin_14s_linear_infinite]" viewBox="0 0 200 200">
                                    <circle cx="100" cy="100" r="94" fill="none" stroke="currentColor" stroke-width="1" stroke-dasharray="4 8" />
                                    <circle cx="100" cy="100" r="84" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="24 12 4 12" />
                                </svg>
                                <img src="{{ funki_asset('shop/projekt/funki/checkout/funki_happy.webp') }}"
                                     alt="Anfrage erfolgreich"
                                     class="relative z-10 w-4/5 h-4/5 object-contain drop-shadow-[0_14px_28px_rgba(197,160,89,0.35)]">
                            </div>

                            <span class="inline-flex items-center gap-2 px-4 py-1 rounded-full bg-amber-500/15 border border-amber-500/40 text-amber-800 text-[10px] sm:text-xs font-black uppercase tracking-[0.25em] mb-3 transition-all duration-500">
                                <span x-show="!showSuccessDetails" class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                <span x-show="showSuccessDetails" x-cloak class="text-emerald-600 text-xs leading-none">✓</span>
                                <span>Seelenfunken-Manufaktur</span>
                            </span>

                            {{-- Fließender Text-Crossfade: "Einen Moment bitte…" -> "Anfrage erfolgreich!" --}}
                            <div class="grid place-items-center w-full">
                                <div class="col-start-1 row-start-1 transition-all duration-500 ease-out"
                                     :class="showSuccessDetails ? 'opacity-0 -translate-y-3 scale-95 pointer-events-none' : 'opacity-100 translate-y-0 scale-100'">
                                    <h3 class="text-2xl sm:text-4xl font-serif font-black tracking-tight text-gray-900 flex items-center justify-center gap-2.5">
                                        <span>Einen Moment</span>
                                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-700">bitte…</span>
                                    </h3>
                                    <p class="text-xs sm:text-sm font-medium text-gray-600 mt-1.5">
                                        Dein persönliches PDF-Angebot wird gerade erstellt…
                                    </p>
                                </div>

                                <div class="col-start-1 row-start-1 transition-all duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]"
                                     :class="showSuccessDetails ? 'opacity-100 translate-y-0 scale-100 delay-100' : 'opacity-0 translate-y-3 scale-95 pointer-events-none'">
                                    <h2 class="text-3xl sm:text-4xl font-serif font-black tracking-tight text-gray-900 leading-tight flex items-center justify-center gap-2.5">
                                        <span>Anfrage</span>
                                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-700">erfolgreich!</span>
                                    </h2>
                                    <p class="text-sm sm:text-base font-medium text-gray-600 mt-1.5">
                                        Dein unverbindliches Angebot ist unterwegs.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- 2. FLIESSEND AUFKLAPPENDE ENDINFOS AUF DEM ANIMATIONS-HINTERGRUND --}}
                        <div class="w-full max-w-xl mx-auto grid transition-all duration-[850ms] ease-[cubic-bezier(0.22,1,0.36,1)]"
                             :class="showSuccessDetails ? 'grid-rows-[1fr] opacity-100 translate-y-0 mt-6' : 'grid-rows-[0fr] opacity-0 translate-y-8 mt-0 pointer-events-none'">
                            <div class="overflow-hidden px-1 py-2">
                                <div class="bg-white/85 backdrop-blur-xl border border-amber-500/30 rounded-3xl p-5 sm:p-8 shadow-[0_24px_60px_rgba(197,160,89,0.18)] text-center">

                                    <div class="p-4 sm:p-5 bg-[#fdf8ed]/85 border border-amber-500/25 rounded-2xl transition-all duration-700 delay-150"
                                         :class="showSuccessDetails ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
                                        @if(!empty($lastQuoteNumber))
                                            <p class="text-[10px] text-amber-800/70 uppercase tracking-widest font-bold">Angebotsnummer</p>
                                            <p class="text-xl sm:text-2xl font-mono font-bold text-primary tracking-tight mt-0.5">
                                                {{ $lastQuoteNumber }}
                                            </p>
                                        @endif
                                        <p class="text-xs sm:text-sm text-gray-600 {{ !empty($lastQuoteNumber) ? 'mt-2' : '' }} leading-relaxed">
                                            Wir haben Ihre Daten erhalten. Ein unverbindliches Angebot wurde soeben als PDF an <strong class="text-gray-900">{{ $form['email'] }}</strong> gesendet.
                                        </p>
                                    </div>

                                    <div class="mt-5 flex flex-col sm:flex-row gap-3 justify-center transition-all duration-700 delay-300"
                                         :class="showSuccessDetails ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
                                        @if(!empty($lastQuoteToken))
                                            <a href="{{ route('quote.accept', ['token' => $lastQuoteToken]) }}"
                                               @click="document.body.style.overflow = ''"
                                               class="w-full sm:w-auto px-7 py-3.5 rounded-full bg-primary text-gray-900 hover:bg-primary-dark hover:text-white transition-all font-bold text-xs tracking-wide shadow-md flex items-center justify-center gap-2">
                                                <span>Angebot direkt online ansehen</span>
                                                <span>→</span>
                                            </a>
                                        @endif

                                        <button type="button"
                                                @click="stopQuoteMagic(); $wire.restartCalculator()"
                                                class="w-full sm:w-auto px-8 py-3.5 rounded-full bg-gray-900 text-white hover:bg-primary hover:text-gray-900 transition-all font-bold text-xs tracking-wide shadow-lg cursor-pointer">
                                            Neue Berechnung starten
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>
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

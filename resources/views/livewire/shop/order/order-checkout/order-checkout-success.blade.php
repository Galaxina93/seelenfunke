@php
    $isEmbedded = !empty($isEmbeddedInCheckout);
    $alreadyFinished = !empty($isFinished) || !$isEmbedded;
    $activeRefCode = !empty($referralCode) ? $referralCode : ($finalOrderNumber ?? 'FREUNDE10');
    $shareUrl = route('shop', ['ref' => $activeRefCode, 'coupon' => $activeRefCode]);
    $whatsappText = "Ich habe mir gerade ein persönliches Unikat bei Seelenfunke anfertigen lassen! 🥰✨ Mit meinem Code " . $activeRefCode . " sparst du 10% auf deine Bestellung: " . $shareUrl;
    $whatsappUrl = 'https://api.whatsapp.com/send?text=' . urlencode($whatsappText);
@endphp

<div data-already-finished="{{ $alreadyFinished ? '1' : '0' }}"
     x-data="{
        isActive: false,
        phase: 0,
        showSuccessDetails: false,
        _magicRaf: null,
        _revealTimer: null,
        magicStartedAt: 0,
        initStage() {
            if (this.$el.dataset.alreadyFinished === '1') {
                this.isActive = true;
                this.phase = 1;
                window.scrollTo({ top: 0, behavior: 'instant' });
                document.body.style.overflow = 'hidden';
                this.$nextTick(() => {
                    this.runGoldenSparksCanvas();
                    requestAnimationFrame(() => { this.phase = 1; });
                });
                this._revealTimer = setTimeout(() => {
                    this.showSuccessDetails = true;
                }, 450);
            }
        },
        startCheckoutMagic() {
            if (this._revealTimer) {
                clearTimeout(this._revealTimer);
                this._revealTimer = null;
            }
            this.isActive = true;
            this.showSuccessDetails = false;
            this.phase = 0;
            this.magicStartedAt = Date.now();
            document.body.style.overflow = 'hidden';
            window.scrollTo({ top: 0, behavior: 'smooth' });
            this.$nextTick(() => {
                this.runGoldenSparksCanvas();
                requestAnimationFrame(() => { this.phase = 1; });
            });
        },
        stopCheckoutMagic() {
            if (this.showSuccessDetails) return;
            if (this._revealTimer) {
                clearTimeout(this._revealTimer);
                this._revealTimer = null;
            }
            this.isActive = false;
            this.phase = 0;
            document.body.style.overflow = '';
            if (this._magicRaf) {
                cancelAnimationFrame(this._magicRaf);
                this._magicRaf = null;
            }
        },
        revealSuccessDetails() {
            this.isActive = true;
            document.body.style.overflow = 'hidden';
            if (!this._magicRaf) {
                this.$nextTick(() => this.runGoldenSparksCanvas());
            }
            this.phase = 1;
            const elapsed = this.magicStartedAt ? (Date.now() - this.magicStartedAt) : 1100;
            const waitMs = Math.max(150, 1100 - elapsed);
            if (this._revealTimer) clearTimeout(this._revealTimer);
            this._revealTimer = setTimeout(() => {
                this.showSuccessDetails = true;
                if (this.$el && typeof this.$el.scrollTo === 'function') {
                    this.$el.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }, waitMs);
        },
        runGoldenSparksCanvas() {
            const canvas = this.$refs.checkoutSparksCanvas;
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
                    ctx.fillStyle = `rgba(251, 191, 36, ${alpha})`;
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                    ctx.fill();
                }
                this._magicRaf = requestAnimationFrame(animate);
            };
            this._magicRaf = requestAnimationFrame(animate);
        }
     }"
     x-init="initStage()"
     @checkout-processing.window="startCheckoutMagic()"
     @checkout-processing-done.window="stopCheckoutMagic()"
     @payment-processed.window="revealSuccessDetails()"
     x-show="isActive"
     x-cloak
     class="checkout-success-container fixed inset-0 z-[9999] overflow-y-auto overflow-x-hidden bg-[#fdf8ed]/95 backdrop-blur-md transition-opacity duration-300"
     wire:key="checkout-magic-stage">

    <style>
        @keyframes floatGentle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
        .animate-float-gentle {
            animation: floatGentle 3.5s ease-in-out infinite;
        }
    </style>

    {{-- RADIALER GOLD-GLOW HINTERGRUND (FIXIERT) --}}
    <div class="fixed inset-0 pointer-events-none z-0"
         style="background: radial-gradient(circle at 50% 38%, rgba(245, 158, 11, 0.22) 0%, rgba(197, 160, 89, 0.08) 44%, transparent 74%);">
    </div>

    {{-- 60 FPS SEELENFUNKEN PARTIKEL-CANVAS (WIRE:IGNORE DAMIT LIVEWIRE DEN CANVAS NICHT ZURÜCKSETZT) --}}
    <div wire:ignore class="fixed inset-0 pointer-events-none z-10">
        <canvas x-ref="checkoutSparksCanvas" class="w-full h-full"></canvas>
    </div>

    {{-- ZENTRIERTE BÜHNE FÜR ÜBERGANG & ENDINFOS --}}
    <div class="relative z-20 min-h-full w-full flex flex-col items-center justify-center px-4 sm:px-6 pt-24 sm:pt-28 pb-12 sm:pb-16">

        {{-- 1. FUNKI & GOLD-AURA + HEADLINE-CROSSFADE --}}
        <div class="flex flex-col items-center justify-center text-center max-w-lg mx-auto transition-all duration-700 transform-gpu"
             :class="phase >= 1 ? 'scale-100 opacity-100' : 'scale-90 opacity-0'">

            {{-- Party-Funki mit rotierenden Goldringen (gleitet beim Öffnen der Endinfos sanft auf kompakte Größe) --}}
            <div class="relative flex items-center justify-center transition-all duration-[850ms] ease-[cubic-bezier(0.22,1,0.36,1)]"
                 :class="showSuccessDetails ? 'w-36 h-36 sm:w-44 sm:h-44 mb-3' : 'w-52 h-52 sm:w-64 sm:h-64 mb-4'"
                 wire:key="success-image-container">
                <div class="absolute inset-2 rounded-full bg-gradient-to-tr from-amber-400/25 via-primary/20 to-yellow-300/25 blur-2xl animate-pulse"></div>
                <svg class="absolute inset-0 w-full h-full text-amber-500/45 animate-[spin_14s_linear_infinite]" viewBox="0 0 200 200">
                    <circle cx="100" cy="100" r="94" fill="none" stroke="currentColor" stroke-width="1" stroke-dasharray="4 8" />
                    <circle cx="100" cy="100" r="84" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="24 12 4 12" />
                </svg>
                <img src="{{ funki_asset('shop/projekt/funki/checkout/funki_happy.webp') }}"
                     alt="Vielen Dank"
                     class="relative z-10 w-4/5 h-4/5 object-contain drop-shadow-[0_14px_28px_rgba(197,160,89,0.35)] animate-float-gentle">
            </div>

            {{-- Manufaktur-Badge --}}
            <span class="inline-flex items-center gap-2 px-4 py-1 rounded-full bg-amber-500/15 border border-amber-500/40 text-amber-800 text-[10px] sm:text-xs font-black uppercase tracking-[0.25em] mb-3 transition-all duration-500">
                <span x-show="!showSuccessDetails" class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                <span x-show="showSuccessDetails" x-cloak class="text-emerald-600 text-xs leading-none">✓</span>
                <span>Seelenfunken-Manufaktur</span>
            </span>

            {{-- Fließender Text-Crossfade: "Einen Moment bitte…" -> "Vielen Dank!" --}}
            <div class="grid place-items-center w-full">
                {{-- Phase A: Laden --}}
                <div class="col-start-1 row-start-1 transition-all duration-500 ease-out"
                     :class="showSuccessDetails ? 'opacity-0 -translate-y-3 scale-95 pointer-events-none' : 'opacity-100 translate-y-0 scale-100'">
                    <h3 class="text-2xl sm:text-4xl font-serif font-black tracking-tight text-gray-900 flex items-center justify-center gap-2.5">
                        <span>Einen Moment</span>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-700">bitte…</span>
                    </h3>
                </div>

                {{-- Phase B: Vielen Dank! --}}
                <div class="col-start-1 row-start-1 transition-all duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]"
                     :class="showSuccessDetails ? 'opacity-100 translate-y-0 scale-100 delay-100' : 'opacity-0 translate-y-3 scale-95 pointer-events-none'">
                    <h1 class="text-3xl sm:text-4xl font-serif font-black tracking-tight text-gray-900 leading-tight flex items-center justify-center gap-2.5">
                        <span>Vielen</span>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-700">Dank!</span>
                    </h1>
                    <p class="text-sm sm:text-base font-medium text-gray-600 mt-1.5">
                        Deine Bestellung war erfolgreich.
                    </p>
                </div>
            </div>
        </div>

        {{-- 2. FLIESSEND AUFKLAPPENDE ENDINFOS AUF DEM ANIMATIONS-HINTERGRUND --}}
        <div class="w-full max-w-xl mx-auto grid transition-all duration-[850ms] ease-[cubic-bezier(0.22,1,0.36,1)]"
             :class="showSuccessDetails ? 'grid-rows-[1fr] opacity-100 translate-y-0 mt-6' : 'grid-rows-[0fr] opacity-0 translate-y-8 mt-0 pointer-events-none'">
            <div class="overflow-hidden px-1 py-2">

                {{-- HARMONISCHE GLAS-GOLD-KACHEL AUF DEM SEELENFUNKEN-HINTERGRUND --}}
                <div class="bg-white/85 backdrop-blur-xl border border-amber-500/30 rounded-3xl p-5 sm:p-8 shadow-[0_24px_60px_rgba(197,160,89,0.18)] text-center">

                    {{-- Bestellnummer Box --}}
                    <div class="p-4 sm:p-5 bg-[#fdf8ed]/85 border border-amber-500/25 rounded-2xl transition-all duration-700 delay-150"
                         :class="showSuccessDetails ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
                        <p class="text-[10px] text-amber-800/70 uppercase tracking-widest font-bold">Bestellnummer</p>
                        <p class="text-xl sm:text-2xl font-mono font-bold text-primary tracking-tight mt-0.5">
                            {{ $finalOrderNumber ?? 'ORDER-12345' }}
                        </p>
                        <p class="text-xs text-gray-600 mt-1.5">
                            Bestätigung gesendet an <span class="font-semibold text-gray-900">{{ $email ?? 'deine E-Mail' }}</span>
                        </p>
                        @if(($earnedSparks ?? 0) > 0)
                            <div class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-amber-500/15 border border-amber-500/35 text-amber-900 text-xs font-bold">
                                <span>✨ Treue-Bonus:</span>
                                <span class="font-mono font-black">+{{ number_format($earnedSparks, 0, ',', '.') }} Funken</span>
                            </div>
                        @endif
                        <div class="mt-3 pt-2.5 border-t border-amber-500/20 flex items-center justify-center gap-1.5 text-[11px] text-gray-600">
                            <svg class="w-3.5 h-3.5 text-primary/90 fill-current shrink-0" viewBox="0 0 24 24">
                                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                            </svg>
                            @php
                                $successHerzensEuro = \App\Services\HerzensprojektService::resolveFormattedEuro();
                                $successHerzensUnit = \App\Services\HerzensprojektService::resolveUnitLabel(true);
                            @endphp
                            <a href="{{ route('manufacture') }}#herzensprojekte" target="_blank" class="hover:text-gray-900 hover:underline transition-colors">
                                Dein Einkauf unterstützt regionale Kinderprojekte ({{ $successHerzensEuro }}&nbsp;€ {{ $successHerzensUnit }} aus unseren Erlösen im Herzensprojekte-Topf).*
                            </a>
                        </div>
                    </div>

                    {{-- SOFORT-DOWNLOAD BOX (Gutscheine & Digitale Ware) --}}
                    @if(!empty($instantDownloads))
                        <div class="mt-4 p-4 sm:p-5 bg-gradient-to-br from-amber-50/90 via-white/95 to-emerald-50/50 border border-primary/35 rounded-2xl text-left shadow-xs transition-all duration-700 delay-200"
                             :class="showSuccessDetails ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-lg bg-primary/15 text-primary flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </span>
                                    <div>
                                        <h2 class="text-xs sm:text-sm font-bold text-gray-900 leading-tight">Sofort-Download bereit</h2>
                                        <p class="text-[11px] text-gray-500">Deine digitalen Schätze sind sofort freigeschaltet</p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold uppercase tracking-wider shrink-0">
                                    Bezahlt ✓
                                </span>
                            </div>

                            <div class="space-y-2.5">
                                @foreach($instantDownloads as $idx => $dl)
                                    <div class="p-3 bg-white/90 border border-amber-500/20 rounded-xl flex flex-col gap-2.5"
                                         x-data="{ voucherCopied: false }">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-xs sm:text-sm font-bold text-gray-900 truncate">{{ $dl['title'] }}</p>
                                                <p class="text-[11px] text-gray-500 truncate">{{ $dl['subtitle'] ?? '' }}</p>
                                            </div>
                                            <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-900 border border-amber-200/70 text-[10px] font-bold uppercase tracking-wider shrink-0">
                                                {{ $dl['format'] ?? 'PDF' }}
                                            </span>
                                        </div>

                                        @if(!empty($dl['code']))
                                            <div class="flex items-center justify-between gap-2 px-3 py-1.5 bg-amber-50/70 border border-amber-200/70 rounded-lg">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-amber-800/80">Gutscheincode:</span>
                                                    <span class="font-mono text-xs sm:text-sm font-bold text-amber-900 tracking-wider truncate">{{ $dl['code'] }}</span>
                                                </div>
                                                <button type="button"
                                                        @click="navigator.clipboard.writeText('{{ $dl['code'] }}'); voucherCopied = true; setTimeout(() => voucherCopied = false, 2000)"
                                                        class="px-2 py-1 rounded-md bg-white hover:bg-amber-100/60 border border-amber-200 text-amber-900 text-[11px] font-semibold transition cursor-pointer shrink-0">
                                                    <span x-show="!voucherCopied">Code kopieren</span>
                                                    <span x-show="voucherCopied" x-cloak class="text-emerald-700">Kopiert ✓</span>
                                                </button>
                                            </div>
                                        @endif

                                        <a href="{{ $dl['url'] }}"
                                           target="_blank"
                                           rel="noopener"
                                           class="w-full py-2.5 px-4 rounded-xl bg-primary hover:bg-primary-dark text-white font-semibold text-xs sm:text-sm transition flex items-center justify-center gap-2 shadow-xs">
                                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            <span>{{ ($dl['type'] ?? '') === 'gift_voucher' ? 'Gutschein-PDF jetzt herunterladen' : 'Datei jetzt herunterladen' }}</span>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- FREUDE TEILEN DROPDOWN --}}
                    <div data-share-url="{{ $shareUrl }}"
                         data-coupon-code="{{ $activeRefCode }}"
                         x-data="{
                            openShare: false,
                            linkCopied: false,
                            codeCopied: false,
                            sharedFeedback: false,
                            shareNative() {
                                const url = this.$el.dataset.shareUrl;
                                const code = this.$el.dataset.couponCode;
                                if (typeof navigator !== 'undefined' && navigator.share) {
                                    navigator.share({
                                        title: '10% Rabatt bei Seelenfunke',
                                        text: 'Mit meinem Gutscheincode ' + code + ' sparst du 10% bei Seelenfunke:',
                                        url: url
                                    }).catch(() => {});
                                } else {
                                    navigator.clipboard.writeText(url);
                                    this.sharedFeedback = true;
                                    setTimeout(() => this.sharedFeedback = false, 2000);
                                }
                            },
                            copyLink() {
                                navigator.clipboard.writeText(this.$el.dataset.shareUrl);
                                this.linkCopied = true;
                                setTimeout(() => this.linkCopied = false, 2000);
                            },
                            copyCode() {
                                navigator.clipboard.writeText(this.$el.dataset.couponCode);
                                this.codeCopied = true;
                                setTimeout(() => this.codeCopied = false, 2000);
                            }
                         }"
                         class="mt-4 border border-amber-500/25 bg-[#fdf8ed]/65 rounded-2xl overflow-hidden text-left transition-all duration-700 delay-250"
                         :class="showSuccessDetails ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">

                        {{-- Dropdown Header --}}
                        <button type="button"
                                @click="openShare = !openShare"
                                class="w-full px-4 py-3.5 flex items-center justify-between gap-3 hover:bg-amber-500/10 transition-colors cursor-pointer select-none focus:outline-none">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-8 h-8 rounded-xl bg-white border border-amber-500/25 flex items-center justify-center text-sm shrink-0 shadow-2xs">
                                    🎁
                                </span>
                                <div class="min-w-0">
                                    <span class="block text-xs sm:text-sm font-semibold text-gray-900 leading-snug">
                                        Freude teilen &amp; 5&nbsp;€ Guthaben erhalten!
                                    </span>
                                    <span class="block text-[11px] text-gray-600 leading-tight mt-0.5">
                                        10&nbsp;% Rabatt für Freunde • 5&nbsp;€ für dich
                                    </span>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-amber-700/70 shrink-0 transition-transform duration-200"
                                 :class="openShare ? 'rotate-180 text-amber-900' : ''"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        {{-- Dropdown Content --}}
                        <div x-show="openShare" x-collapse x-cloak class="px-4 pb-4 pt-1 border-t border-amber-500/20 space-y-2.5">

                            {{-- Schlanke Code-Kopierzeile --}}
                            <div class="bg-white rounded-xl px-3.5 py-2 border border-amber-500/25 flex items-center justify-between gap-2 mt-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-amber-800/70">Code</span>
                                    <span class="font-mono text-sm font-bold text-gray-900 tracking-wider truncate">{{ $activeRefCode }}</span>
                                </div>
                                <button @click="copyCode()"
                                        type="button"
                                        class="px-2.5 py-1 rounded-lg bg-[#fdf8ed] hover:bg-amber-100/70 active:scale-95 border border-amber-500/25 text-amber-950 text-xs font-medium transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                                    <span x-show="!codeCopied">Kopieren</span>
                                    <span x-show="codeCopied" style="display: none;" class="text-emerald-700 font-semibold">Kopiert ✓</span>
                                    <svg x-show="!codeCopied" class="w-3.5 h-3.5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>

                            {{-- Schnell-Buttons: Via WhatsApp, Link kopieren & Teilen-Icon --}}
                            <div class="flex items-center gap-2">
                                <a href="{{ $whatsappUrl }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="flex-1 py-2 px-3 rounded-xl bg-[#25b45b] hover:bg-[#1fa04f] active:scale-[0.99] text-white font-medium text-xs transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                                    <svg class="w-3.5 h-3.5 fill-current shrink-0" viewBox="0 0 24 24">
                                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                                    </svg>
                                    <span>Via WhatsApp</span>
                                </a>

                                <button @click="copyLink()"
                                        type="button"
                                        class="flex-1 py-2 px-3 rounded-xl bg-white hover:bg-amber-50 active:scale-[0.99] border border-amber-500/25 text-gray-800 font-medium text-xs transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                                    <svg class="w-3.5 h-3.5 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                    <span x-show="!linkCopied">Link kopieren</span>
                                    <span x-show="linkCopied" style="display: none;" class="text-emerald-700 font-semibold">Kopiert ✓</span>
                                </button>

                                <button @click="shareNative()"
                                        type="button"
                                        class="w-9 h-9 rounded-xl bg-white hover:bg-amber-50 active:scale-95 border border-amber-500/25 text-gray-800 transition flex items-center justify-center shrink-0 cursor-pointer"
                                        title="Teilen">
                                    <svg x-show="!sharedFeedback" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                    <svg x-show="sharedFeedback" style="display: none;" class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            </div>

                            <p class="text-[10px] text-gray-500 leading-snug pt-1">
                                <strong>Teilnahmebedingungen (§ 6 DDG):</strong> 10 % Neukunden-Rabatt für geworbene Freunde (keine Eigenwerbung, nicht mit anderen Aktionscodes kombinierbar). Nach erfolgreichem Abschluss der geworbenen Bestellung erhältst du per E-Mail einen 5,00 € Wertgutschein (3 Jahre gültig gemäß §§ 195, 199 BGB, einlösbar auf das gesamte Sortiment, keine Barauszahlung).
                            </p>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="mt-6 flex flex-col sm:flex-row gap-2.5 justify-center transition-all duration-700 delay-300"
                         :class="showSuccessDetails ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
                        <a href="{{ route('customer.orders', array_filter(['order' => !empty($finalOrderId) ? $finalOrderId : (!empty($finalOrderNumber) ? $finalOrderNumber : null)])) }}"
                           @click="document.body.style.overflow = ''"
                           class="w-full sm:w-auto px-7 py-3 bg-primary text-gray-900 hover:bg-primary-dark hover:text-white rounded-full text-xs sm:text-sm font-semibold transition shadow-sm flex items-center justify-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <span>Zur Bestellung</span>
                        </a>
                        <a href="{{ route('shop') }}"
                           @click="document.body.style.overflow = ''"
                           class="w-full sm:w-auto px-7 py-3 bg-gray-900 text-white rounded-full text-xs sm:text-sm font-semibold hover:bg-black transition flex items-center justify-center">
                            Weiter einkaufen
                        </a>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

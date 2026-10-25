<div x-data
     x-init="window.scrollTo({ top: 0, behavior: 'instant' })"
     class="checkout-success-container max-w-xl mx-auto px-4 w-full animate-fade-in"
     style="box-sizing: border-box !important; padding-top: var(--checkout-success-pt, 96px) !important; padding-bottom: 40px !important;"
     wire:key="checkout-success-container">

    <style>
        :root {
            --checkout-success-pt: 96px;
        }
        @keyframes floatGentle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        .animate-float-gentle {
            animation: floatGentle 3.5s ease-in-out infinite;
        }
        .checkout-success-container {
            box-sizing: border-box !important;
            padding-top: var(--checkout-success-pt, 96px) !important;
            padding-bottom: 40px !important;
        }
        @media (max-width: 1023px) {
            :root {
                --checkout-success-pt: 96px;
            }
            .checkout-success-container {
                padding-top: 96px !important;
                padding-bottom: 35px !important;
            }
        }
        @media (min-width: 1024px) {
            :root {
                --checkout-success-pt: max(130px, calc((100vh - 560px) / 2));
            }
            .checkout-success-container {
                padding-top: max(130px, calc((100vh - 560px) / 2)) !important;
                padding-bottom: 48px !important;
            }
        }
    </style>

    @php
        $activeRefCode = !empty($referralCode) ? $referralCode : ($finalOrderNumber ?? 'FREUNDE10');
        $shareUrl = route('shop', ['ref' => $activeRefCode, 'coupon' => $activeRefCode]);
        $whatsappText = "Ich habe mir gerade ein persönliches Unikat bei Seelenfunke anfertigen lassen! 🥰✨ Mit meinem Code " . $activeRefCode . " sparst du 10% auf deine Bestellung: " . $shareUrl;
        $whatsappUrl = 'https://api.whatsapp.com/send?text=' . urlencode($whatsappText);
    @endphp

    {{-- ZENTRIERTE, RUHIGE KARTE --}}
    <div class="bg-white border border-gray-200/90 rounded-3xl p-6 sm:p-9 shadow-xs text-center">

        {{-- Funki Visual (Größer & mittig zentriert) --}}
        <div class="flex justify-center mb-4" wire:key="success-image-container">
            <img src="{{ funki_asset('shop/projekt/funki/checkout/funki_happy.webp') }}"
                 alt="Vielen Dank"
                 class="h-36 sm:h-44 w-auto object-contain animate-float-gentle">
        </div>

        {{-- Headline --}}
        <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 tracking-tight leading-tight">
            Vielen Dank!
        </h1>
        <p class="text-sm sm:text-base text-gray-500 mt-1.5">
            Deine Bestellung war erfolgreich.
        </p>

        {{-- Bestellnummer Box (Matt & reduziert) --}}
        <div class="mt-6 p-4 sm:p-5 bg-gray-50/80 border border-gray-200/70 rounded-2xl">
            <p class="text-[10px] text-gray-400 uppercase tracking-widest font-semibold">Bestellnummer</p>
            <p class="text-xl sm:text-2xl font-mono font-bold text-primary tracking-tight mt-0.5">
                {{ $finalOrderNumber ?? 'ORDER-12345' }}
            </p>
            <p class="text-xs text-gray-500 mt-1.5">
                Bestätigung gesendet an <span class="font-medium text-gray-800">{{ $email ?? 'deine E-Mail' }}</span>
            </p>
            <div class="mt-3 pt-2.5 border-t border-gray-200/60 flex items-center justify-center gap-1.5 text-[11px] text-gray-500">
                <svg class="w-3.5 h-3.5 text-primary/80 fill-current shrink-0" viewBox="0 0 24 24">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                </svg>
                <span>Dein Einkauf unterstützt regionale Kinderprojekte.</span>
            </div>
        </div>

        {{-- SOFORT-DOWNLOAD BOX (Gutscheine & Digitale Ware) --}}
        @if(!empty($instantDownloads))
            <div class="mt-4 p-4 sm:p-5 bg-gradient-to-br from-amber-50/80 via-white to-emerald-50/40 border border-primary/30 rounded-2xl text-left shadow-xs">
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
                        <div class="p-3 bg-white border border-gray-200/80 rounded-xl flex flex-col gap-2.5"
                             x-data="{ voucherCopied: false }">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs sm:text-sm font-bold text-gray-900 truncate">{{ $dl['title'] }}</p>
                                    <p class="text-[11px] text-gray-500 truncate">{{ $dl['subtitle'] ?? '' }}</p>
                                </div>
                                <span class="px-2 py-0.5 rounded-md bg-gray-100 text-gray-700 text-[10px] font-bold uppercase tracking-wider shrink-0">
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

        {{-- SCHLANKES, MATTES DROPDOWN: FREUDE TEILEN (direkt über "Weiter einkaufen") --}}
        <div x-data="{
                openShare: false,
                linkCopied: false,
                codeCopied: false,
                sharedFeedback: false,
                shareUrl: '{{ $shareUrl }}',
                couponCode: '{{ $activeRefCode }}',
                shareNative() {
                    if (typeof navigator !== 'undefined' && navigator.share) {
                        navigator.share({
                            title: '10% Rabatt bei Seelenfunke',
                            text: 'Mit meinem Gutscheincode {{ $activeRefCode }} sparst du 10% bei Seelenfunke:',
                            url: this.shareUrl
                        }).catch(() => {});
                    } else {
                        navigator.clipboard.writeText(this.shareUrl);
                        this.sharedFeedback = true;
                        setTimeout(() => this.sharedFeedback = false, 2000);
                    }
                },
                copyLink() {
                    navigator.clipboard.writeText(this.shareUrl);
                    this.linkCopied = true;
                    setTimeout(() => this.linkCopied = false, 2000);
                },
                copyCode() {
                    navigator.clipboard.writeText(this.couponCode);
                    this.codeCopied = true;
                    setTimeout(() => this.codeCopied = false, 2000);
                }
             }"
             class="mt-4 border border-gray-200/80 bg-gray-50/60 rounded-2xl overflow-hidden text-left transition-colors">

            {{-- Dropdown Header --}}
            <button type="button"
                    @click="openShare = !openShare"
                    class="w-full px-4 py-3.5 flex items-center justify-between gap-3 hover:bg-gray-100/60 transition-colors cursor-pointer select-none focus:outline-none">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-8 h-8 rounded-xl bg-white border border-gray-200/80 flex items-center justify-center text-sm shrink-0">
                        🎁
                    </span>
                    <div class="min-w-0">
                        <span class="block text-xs sm:text-sm font-semibold text-gray-900 leading-snug">
                            Freude teilen &amp; 5&nbsp;€ Guthaben erhalten!
                        </span>
                        <span class="block text-[11px] text-gray-500 leading-tight mt-0.5">
                            10&nbsp;% Rabatt für Freunde • 5&nbsp;€ für dich
                        </span>
                    </div>
                </div>
                <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform duration-200"
                     :class="openShare ? 'rotate-180 text-gray-700' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {{-- Dropdown Content --}}
            <div x-show="openShare" x-collapse x-cloak class="px-4 pb-4 pt-1 border-t border-gray-200/60 space-y-2.5">

                {{-- Schlanke Code-Kopierzeile --}}
                <div class="bg-white rounded-xl px-3.5 py-2 border border-gray-200/80 flex items-center justify-between gap-2 mt-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Code</span>
                        <span class="font-mono text-sm font-bold text-gray-900 tracking-wider truncate">{{ $activeRefCode }}</span>
                    </div>
                    <button @click="copyCode()"
                            type="button"
                            class="px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200/80 active:scale-95 text-gray-700 text-xs font-medium transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                        <span x-show="!codeCopied">Kopieren</span>
                        <span x-show="codeCopied" style="display: none;" class="text-emerald-700 font-semibold">Kopiert ✓</span>
                        <svg x-show="!codeCopied" class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </div>

                {{-- Schnell-Buttons: Via WhatsApp, Link kopieren & Teilen-Icon --}}
                <div class="flex items-center gap-2">
                    {{-- Via WhatsApp (matter, ruhiger Grünton) --}}
                    <a href="{{ $whatsappUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="flex-1 py-2 px-3 rounded-xl bg-[#25b45b] hover:bg-[#1fa04f] active:scale-[0.99] text-white font-medium text-xs transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                        <svg class="w-3.5 h-3.5 fill-current shrink-0" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                        <span>Via WhatsApp</span>
                    </a>

                    {{-- Link kopieren --}}
                    <button @click="copyLink()"
                            type="button"
                            class="flex-1 py-2 px-3 rounded-xl bg-white hover:bg-gray-100 active:scale-[0.99] border border-gray-200/90 text-gray-700 font-medium text-xs transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        <span x-show="!linkCopied">Link kopieren</span>
                        <span x-show="linkCopied" style="display: none;" class="text-emerald-700 font-semibold">Kopiert ✓</span>
                    </button>

                    {{-- Teilen Icon Button --}}
                    <button @click="shareNative()"
                            type="button"
                            class="w-9 h-9 rounded-xl bg-white hover:bg-gray-100 active:scale-95 border border-gray-200/90 text-gray-700 transition flex items-center justify-center shrink-0 cursor-pointer"
                            title="Teilen">
                        <svg x-show="!sharedFeedback" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        <svg x-show="sharedFeedback" style="display: none;" class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="mt-6 flex flex-col sm:flex-row gap-2.5 justify-center">
            <a href="{{ route('shop') }}" class="w-full sm:w-auto px-7 py-3 bg-gray-900 text-white rounded-full text-xs sm:text-sm font-semibold hover:bg-black transition">
                Weiter einkaufen
            </a>
            @if(Auth::guard('customer')->check())
                <a href="{{ route('customer.dashboard') }}" class="w-full sm:w-auto px-6 py-3 bg-gray-100 text-gray-700 hover:bg-gray-200/80 rounded-full text-xs sm:text-sm font-medium transition">
                    Bestellung verfolgen
                </a>
            @endif
        </div>

    </div>
</div>

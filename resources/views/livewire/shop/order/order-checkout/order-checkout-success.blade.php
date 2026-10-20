<div x-data
     x-init="window.scrollTo({ top: 0, behavior: 'instant' })"
     class="checkout-success-container max-w-5xl mx-auto px-4 w-full animate-fade-in"
     style="box-sizing: border-box !important; padding-top: 140px !important; padding-top: max(140px, calc((100vh - 460px) / 2)) !important; padding-bottom: 40px !important;"
     wire:key="checkout-success-container">

    <style>
        @keyframes floatGentle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        .animate-float-gentle {
            animation: floatGentle 3.5s ease-in-out infinite;
        }
        .checkout-success-container {
            box-sizing: border-box !important;
            padding-top: 140px !important;
            padding-top: max(140px, calc((100vh - 460px) / 2)) !important;
            padding-bottom: 40px !important;
        }
        @media (max-width: 1023px) {
            .checkout-success-container {
                padding-top: 130px !important;
                padding-bottom: 50px !important;
            }
        }
    </style>

    @php
        $activeRefCode = !empty($referralCode) ? $referralCode : ($finalOrderNumber ?? 'FREUNDE10');
        $shareUrl = route('shop', ['ref' => $activeRefCode, 'coupon' => $activeRefCode]);
        $whatsappText = "Ich habe mir gerade ein persönliches Unikat bei Seelenfunke anfertigen lassen! 🥰✨ Die Manufaktur hat so wundervolle Geschenkideen. Wenn du dir oder deinen Liebsten auch eine Freude machen willst: Mit meinem Code " . $activeRefCode . " sparst du 10% auf deine Bestellung: " . $shareUrl;
        $whatsappUrl = 'https://api.whatsapp.com/send?text=' . urlencode($whatsappText);
    @endphp

    {{-- 2-SPALTIGES DESKTOP-LAYOUT FÜR NULL SCROLLAUFWAND --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6 items-stretch">
        
        {{-- LINKE SPALTE: FUNKI, BESTELLNUMMER & STATUS --}}
        <div class="lg:col-span-5 flex flex-col justify-between bg-white border border-gray-200/90 rounded-2xl p-5 sm:p-6 shadow-xs text-center">
            <div>
                {{-- Funki Visual --}}
                <div class="flex justify-center mb-2" wire:key="success-image-container">
                    <img src="/shop/projekt/funki/checkout/funki_happy.webp"
                         alt="Vielen Dank"
                         class="h-28 sm:h-32 w-auto object-contain filter drop-shadow-xs animate-float-gentle">
                </div>

                <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 leading-tight">Vielen Dank!</h1>
                <p class="text-sm text-gray-600 mt-1">Deine Bestellung war erfolgreich.</p>

                {{-- Bestellnummer Box --}}
                <div class="mt-4 p-3.5 bg-gray-50/80 border border-gray-150 rounded-xl">
                    <p class="text-[10px] text-gray-500 uppercase tracking-widest font-semibold">Bestellnummer</p>
                    <p class="text-xl sm:text-2xl font-mono font-bold text-primary tracking-tight mt-0.5">{{ $finalOrderNumber ?? 'ORDER-12345' }}</p>
                    <p class="text-xs text-gray-500 mt-1 leading-snug">
                        Bestätigung gesendet an <span class="font-semibold text-gray-800">{{ $email ?? 'deine E-Mail' }}</span>
                    </p>
                </div>

                {{-- Soziales Engagement Mini-Badge --}}
                <div class="mt-3 p-2.5 bg-amber-50/70 border border-amber-200/50 rounded-xl flex items-center justify-center gap-2 text-amber-950 text-xs">
                    <svg class="w-4 h-4 text-amber-500 fill-amber-500 shrink-0" viewBox="0 0 24 24">
                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                    </svg>
                    <span class="text-[11px] leading-tight text-gray-700">
                        <strong class="text-gray-900">Danke!</strong> Dein Einkauf unterstützt regionale Kinderprojekte.
                    </span>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="mt-5 pt-4 border-t border-gray-100 flex flex-col sm:flex-row gap-2.5 justify-center">
                <a href="{{ route('shop') }}" class="w-full sm:w-auto px-5 py-2.5 bg-gray-900 text-white rounded-xl text-xs font-bold hover:bg-black transition shadow-xs">
                    Weiter einkaufen
                </a>
                @if(Auth::guard('customer')->check())
                    <a href="{{ route('customer.dashboard') }}" class="w-full sm:w-auto px-5 py-2.5 bg-white text-gray-800 border border-gray-250 rounded-xl text-xs font-bold hover:bg-gray-50 transition">
                        Bestellung verfolgen
                    </a>
                @endif
            </div>
        </div>

        {{-- RECHTE SPALTE: SMARTE FREUNDE-WERBEN KARTE --}}
        <div x-data="{
                linkCopied: false,
                codeCopied: false,
                shareUrl: '{{ $shareUrl }}',
                couponCode: '{{ $activeRefCode }}',
                canShare: typeof navigator !== 'undefined' && !!navigator.share,
                shareNative() {
                    if (navigator.share) {
                        navigator.share({
                            title: '10% Rabatt bei Seelenfunke',
                            text: 'Ich habe mir gerade ein persönliches Unikat bei Seelenfunke anfertigen lassen! 🥰✨ Mit dem Gutscheincode {{ $activeRefCode }} sparst du 10% auf deine Bestellung:',
                            url: this.shareUrl
                        }).catch(() => {});
                    }
                },
                copyLink() {
                    navigator.clipboard.writeText(this.shareUrl);
                    this.linkCopied = true;
                    setTimeout(() => this.linkCopied = false, 2500);
                },
                copyCode() {
                    navigator.clipboard.writeText(this.couponCode);
                    this.codeCopied = true;
                    setTimeout(() => this.codeCopied = false, 2500);
                }
             }"
             class="lg:col-span-7 flex flex-col justify-between bg-gradient-to-br from-amber-50/70 via-white to-primary/5 border border-primary/25 rounded-2xl p-5 sm:p-6 shadow-xs text-left">
            
            <div>
                {{-- Header Pillen --}}
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-primary/20 text-gray-900 border border-primary/30">
                        🎁 Freude teilen
                    </span>
                    <span class="text-[11px] font-bold text-amber-900 bg-amber-100/90 border border-amber-200/80 px-2.5 py-0.5 rounded-full">
                        10 % für Freunde • 5 € für dich
                    </span>
                </div>

                <h2 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 leading-snug">
                    Freude teilen & 5 € Guthaben erhalten!
                </h2>
                
                <p class="text-xs sm:text-sm text-gray-600 leading-relaxed mt-2 mb-4">
                    Empfehle Seelenfunke deinen Liebsten. Deine Freunde sparen sofort <strong class="text-gray-900">10 % Rabatt</strong> – und für jede Bestellung schreiben wir dir automatisch <strong class="text-gray-900">5 € Einkaufsguthaben</strong> gut.
                </p>

                {{-- Smarte Code-Kachel mit 1-Klick Kopieren --}}
                <div class="bg-white rounded-xl p-3 sm:p-3.5 border border-gray-200 shadow-xs flex items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center text-xl shrink-0">
                            🎟️
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-gray-400 block">Dein Gutscheincode</span>
                            <span class="font-mono text-base sm:text-lg font-black text-gray-900 tracking-wider truncate block">{{ $activeRefCode }}</span>
                        </div>
                    </div>
                    <button @click="copyCode()"
                            type="button"
                            class="px-3.5 py-2 rounded-lg border border-gray-250 bg-gray-50 hover:bg-gray-100 active:scale-95 text-gray-800 text-xs font-bold transition-all flex items-center gap-1.5 shrink-0 cursor-pointer shadow-2xs">
                        <span x-show="!codeCopied">Kopieren</span>
                        <span x-show="codeCopied" style="display: none;" class="text-emerald-600 font-bold">Kopiert! ✓</span>
                        <svg x-show="!codeCopied" class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <svg x-show="codeCopied" style="display: none;" class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </button>
                </div>
            </div>

            {{-- Schnelle Sharing-Aktionen --}}
            <div class="pt-2">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    {{-- WhatsApp Share --}}
                    <a href="{{ $whatsappUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       style="background-color: #25D366 !important; color: #ffffff !important;"
                       onmouseover="this.style.backgroundColor='#20bd5a'"
                       onmouseout="this.style.backgroundColor='#25D366'"
                       class="py-2.5 px-4 rounded-xl active:scale-[0.98] font-bold text-xs shadow-xs hover:shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <svg style="width: 17px; height: 17px; fill: #ffffff !important;" class="shrink-0" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                        <span style="color: #ffffff !important; font-weight: 700;">Via WhatsApp teilen</span>
                    </a>

                    {{-- Link kopieren & Native Share --}}
                    <div class="flex gap-2">
                        <button @click="copyLink()"
                                type="button"
                                class="flex-1 py-2.5 px-3 rounded-xl bg-white hover:bg-gray-50 active:scale-[0.98] border border-gray-250 text-gray-800 font-bold text-xs shadow-2xs transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            <span x-show="!linkCopied">Link kopieren</span>
                            <span x-show="linkCopied" style="display: none;" class="text-emerald-600 font-bold">Kopiert! ✓</span>
                        </button>

                        <button x-show="canShare"
                                @click="shareNative()"
                                type="button"
                                class="px-3.5 py-2.5 rounded-xl bg-gray-900 hover:bg-black active:scale-95 text-white text-xs font-bold shadow-2xs transition-all flex items-center justify-center shrink-0 cursor-pointer"
                                title="Teilen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        </button>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

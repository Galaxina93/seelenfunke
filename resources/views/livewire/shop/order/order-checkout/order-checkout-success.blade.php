<div x-data
     x-init="window.scrollTo({ top: 0, behavior: 'smooth' })"
     class="max-w-3xl mx-auto px-4 py-16 my-8 text-center animate-fade-in"
     wire:key="checkout-success-container">

    <style>
        @keyframes floatGentle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        .animate-float-gentle {
            animation: floatGentle 4s ease-in-out infinite;
        }
    </style>

    {{-- FUNKI STATT GRÜNEM HAKEN --}}
    <div class="mb-6 flex justify-center" wire:key="success-image-container">
        <img src="/shop/projekt/funki/checkout/funki_kiss.webp"
             alt="Vielen Dank"
             class="h-64 w-auto object-contain filter drop-shadow-sm animate-float-gentle">
    </div>

    <h1 class="text-4xl font-serif font-bold text-gray-900 mb-4">Vielen Dank!</h1>
    <p class="text-xl text-gray-600 mb-12">Deine Bestellung war erfolgreich.</p>

    {{-- BESTELLBOX (Clean, da Funki jetzt oben ist) --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm mb-10">
        <p class="text-sm text-gray-500 uppercase tracking-widest mb-2">Bestellnummer</p>
        <p class="text-3xl font-mono font-bold text-primary tracking-tight">{{ $finalOrderNumber ?? 'ORDER-12345' }}</p>

        <div class="mt-8 pt-6 border-t border-gray-100">
            <p class="text-gray-600">
                Wir haben dir eine Bestätigung an <span class="font-bold text-gray-900">{{ $email ?? 'deine E-Mail' }}</span> gesendet.
            </p>

            <div class="mt-6 p-4 bg-amber-50/80 border border-amber-200/60 rounded-xl flex items-center justify-center gap-3 text-amber-950 text-sm">
                <span class="text-xl shrink-0">❤️</span>
                <span class="text-left sm:text-center text-xs sm:text-sm">
                    <strong>Danke, dass du Freude schenkst!</strong> Durch deinen Einkauf fließt automatisch ein Beitrag in unseren Spenden-Topf für regionale Kindergärten & Kinderaktionen.
                </span>
            </div>
        </div>
    </div>

    {{-- FREUNDE WERBEN & 5 € GUTHABEN SICHERN --}}
    @php
        $activeRefCode = !empty($referralCode) ? $referralCode : ($finalOrderNumber ?? 'FREUNDE10');
        $shareUrl = route('shop', ['ref' => $activeRefCode, 'coupon' => $activeRefCode]);
        $whatsappText = "Ich habe mir gerade ein persönliches Unikat bei Seelenfunke anfertigen lassen! 🥰✨ Die Manufaktur hat so wundervolle Geschenkideen. Wenn du dir oder deinen Liebsten auch eine Freude machen willst: Mit meinem Code " . $activeRefCode . " sparst du 10% auf deine Bestellung: " . $shareUrl;
        $whatsappUrl = 'https://api.whatsapp.com/send?text=' . urlencode($whatsappText);
    @endphp

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
         class="relative overflow-hidden bg-gradient-to-br from-amber-50/80 via-white to-primary/5 border border-primary/30 rounded-3xl p-6 sm:p-8 shadow-sm mb-10 text-left">
        
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-primary/20 text-gray-900 border border-primary/40">
                🎁 Freunde werben & Sparen
            </span>
            <span class="text-xs font-bold text-amber-900 bg-amber-100/90 border border-amber-200 px-3 py-1 rounded-full">
                10 % für Freunde • 5 € Einkaufsguthaben für dich
            </span>
        </div>

        <h2 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 mb-2">
            Freude teilen & 5 € Guthaben erhalten!
        </h2>
        <p class="text-sm sm:text-base text-gray-700 leading-relaxed mb-6">
            Erzähle deinen Liebsten oder deiner Familie von Seelenfunke. Deine Freunde erhalten sofort <strong>10 % Rabatt</strong> auf ihre erste Bestellung – und sobald jemand mit deinem persönlichen Code bestellt, schreiben wir dir automatisch <strong>5 € Einkaufsguthaben</strong> für deinen nächsten Einkauf gut!
        </p>

        {{-- 3-Schritte Ablauf --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
            <div class="bg-white/90 backdrop-blur-sm rounded-2xl p-4 border border-amber-200/60 shadow-xs flex items-start gap-3">
                <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-black text-sm shrink-0">1</div>
                <div>
                    <div class="text-xs font-bold text-gray-900">Empfehlung teilen</div>
                    <div class="text-[11px] text-gray-600 mt-0.5 leading-snug">Per WhatsApp, Social Media oder direkt als Link senden.</div>
                </div>
            </div>
            <div class="bg-white/90 backdrop-blur-sm rounded-2xl p-4 border border-amber-200/60 shadow-xs flex items-start gap-3">
                <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-black text-sm shrink-0">2</div>
                <div>
                    <div class="text-xs font-bold text-gray-900">Freund spart 10 %</div>
                    <div class="text-[11px] text-gray-600 mt-0.5 leading-snug">Mit deinem Code <span class="font-mono font-bold text-primary">{{ $activeRefCode }}</span> auf den ersten Einkauf.</div>
                </div>
            </div>
            <div class="bg-white/90 backdrop-blur-sm rounded-2xl p-4 border border-amber-200/60 shadow-xs flex items-start gap-3">
                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-black text-sm shrink-0">3</div>
                <div>
                    <div class="text-xs font-bold text-gray-900">5 € für dich</div>
                    <div class="text-[11px] text-gray-600 mt-0.5 leading-snug">Automatische Gutschrift als Einkaufsguthaben.</div>
                </div>
            </div>
        </div>

        {{-- Gutscheincode Box --}}
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-gray-200/80 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="w-12 h-12 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-2xl shrink-0">
                    🎟️
                </div>
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 block">Dein persönlicher Empfehlungscode</span>
                    <span class="font-mono text-xl sm:text-2xl font-black text-gray-900 tracking-wider">{{ $activeRefCode }}</span>
                </div>
            </div>
            <button @click="copyCode()"
                    type="button"
                    class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-gray-300 hover:border-gray-400 bg-gray-50 hover:bg-gray-100 active:scale-95 text-gray-800 text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer shadow-xs">
                <span x-show="!codeCopied">Code kopieren</span>
                <span x-show="codeCopied" style="display: none;" class="text-emerald-600 font-bold">Kopiert! ✓</span>
                <svg x-show="!codeCopied" class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <svg x-show="codeCopied" style="display: none;" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </button>
        </div>

        {{-- Share Buttons --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {{-- WhatsApp Share --}}
            <a href="{{ $whatsappUrl }}"
               target="_blank"
               rel="noopener noreferrer"
               style="background-color: #25D366 !important; color: #ffffff !important;"
               onmouseover="this.style.backgroundColor='#20bd5a'"
               onmouseout="this.style.backgroundColor='#25D366'"
               class="w-full py-3.5 px-6 rounded-2xl active:scale-[0.98] font-bold text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2.5 cursor-pointer">
                <svg style="width: 20px; height: 20px; fill: #ffffff !important;" class="shrink-0" viewBox="0 0 24 24">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                </svg>
                <span style="color: #ffffff !important; font-weight: 700;">Auf WhatsApp teilen</span>
            </a>

            {{-- Link Copy / Web Share --}}
            <div class="flex gap-2">
                <button @click="copyLink()"
                        type="button"
                        class="flex-1 py-3.5 px-4 rounded-2xl bg-white hover:bg-gray-50 active:scale-[0.98] border border-gray-200 text-gray-800 font-bold text-sm shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    <span x-show="!linkCopied">Link kopieren</span>
                    <span x-show="linkCopied" style="display: none;" class="text-emerald-600 font-bold">Link kopiert! ✓</span>
                </button>

                <button x-show="canShare"
                        @click="shareNative()"
                        type="button"
                        class="px-4 py-3.5 rounded-2xl bg-gray-900 hover:bg-black active:scale-95 text-white text-sm font-bold shadow-sm transition-all flex items-center justify-center shrink-0 cursor-pointer"
                        title="Teilen via Instagram, Facebook oder E-Mail">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Buttons --}}
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="{{ route('shop') }}" class="px-8 py-3 bg-gray-900 text-white rounded-full font-bold hover:bg-black transition shadow-lg transform hover:-translate-y-0.5">
            Weiter einkaufen
        </a>
        @if(Auth::guard('customer')->check())
            <a href="{{ route('customer.dashboard') }}" class="px-8 py-3 bg-white text-gray-900 border border-gray-200 rounded-full font-bold hover:bg-gray-50 transition">
                Bestellung verfolgen
            </a>
        @endif
    </div>
</div>

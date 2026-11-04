@php
    $hasDigitalOrIngame = collect($cart->items ?? [])->contains(function($ci) {
        return ($ci->product->type ?? 'physical') === 'digital'
            && ($ci->product->slug ?? '') !== 'geschenkgutschein'
            && empty($ci->configuration['is_gift_voucher'])
            && !empty($ci->product->digital_download_path);
    });
@endphp
<div class="mt-10 lg:mt-0 lg:sticky lg:top-24 z-20"
     x-data="{
        isProcessing: false,
        showLegalWarning: false,
        terms: @entangle('terms_accepted').live,
        privacy: true,
        digitalWaiver: @entangle('digital_waiver_accepted').live,
        requiresDigitalWaiver: {{ $hasDigitalOrIngame ? 'true' : 'false' }}
     }"
     @checkout-legal-warning.window="showLegalWarning = true"
     @checkout-processing-done.window="isProcessing = false"
     @checkout-processing.window="isProcessing = true">

    <div class="bg-white p-5 sm:p-6 rounded-2xl border border-gray-150 shadow-md transition-all duration-300">

        <div x-show="isProcessing" x-cloak class="flex flex-col items-center justify-center py-12 space-y-6 animate-fade-in">
            <div class="relative w-64 h-64 sm:w-80 sm:h-80 mx-auto">
                <img src="{{ funki_asset('shop/projekt/funki/checkout/funki_happy.webp') }}" class="w-full h-full object-contain animate-bounce-slow" alt="Verarbeite Bestellung">
            </div>
            <div class="text-center space-y-2">
                <h3 class="text-xl font-bold text-gray-900 flex items-center justify-center gap-3">
                    <svg class="animate-spin h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Einen Moment...
                </h3>
                <p class="text-gray-500 text-sm">Deine Bestellung wird sicher übertragen.</p>
            </div>
        </div>

        <div x-show="!isProcessing" class="animate-fade-in">
            <h2 class="text-base sm:text-lg font-bold text-gray-900 mb-4">Bestellübersicht</h2>

            @php
                $threshold = (int) shop_setting('shipping_free_threshold', 5000);
                $currentValue = $totals['subtotal_gross'];

                $hasStandardPhysical = $cart->items->contains(fn($item) => ($item->product->type ?? 'physical') === 'physical');
                $hasPostalVoucher = !empty($totals['has_postal_voucher']);
                $hasPhysical = $hasStandardPhysical || $hasPostalVoucher;

                $percent = $threshold > 0 ? min(100, ($currentValue / $threshold) * 100) : 100;
                $missing = $totals['missing_for_free_shipping'];
                $isFree = $totals['is_free_shipping'];

                if (!$hasStandardPhysical) $percent = 100;
                $targetCountry = ($has_separate_shipping && !empty($shipping_country)) ? $shipping_country : $country;
            @endphp

            @if($targetCountry === 'DE' && $hasStandardPhysical)
                <div class="mb-4 bg-gray-50 p-3.5 rounded-xl border border-gray-200">
                    @if($isFree)
                        <div class="flex items-center gap-2 text-green-600 font-bold text-sm animate-fade-in">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Glückwunsch! Versandkostenfrei.
                        </div>
                    @else
                        <p class="text-sm text-gray-700 font-medium mb-1.5">
                            Noch <span class="text-primary font-bold whitespace-nowrap">{{ number_format($missing / 100, 2, ',', '.') }}&nbsp;€</span> bis zum <span class="text-green-600 font-bold">kostenlosen Versand!</span>
                        </p>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-primary h-2 rounded-full transition-all duration-500" style="width: {{ $percent }}%"></div>
                        </div>
                    @endif
                </div>
            @elseif(!$hasStandardPhysical && $hasPostalVoucher)
                <div class="mb-4 bg-amber-50 p-3 rounded-xl border border-amber-200 text-amber-800 text-xs sm:text-sm flex items-center gap-2.5 shadow-2xs font-medium">
                    <span class="text-base shrink-0">✉️</span>
                    <span>Zustellung per Post im Umschlag (+&nbsp;3,50&nbsp;€) &amp; Sofort-PDF</span>
                </div>
            @elseif(!$hasPhysical)
                <div class="mb-4 bg-blue-50 p-3 rounded-xl border border-blue-100 text-blue-800 text-xs sm:text-sm flex gap-3 shadow-2xs">
                    <svg class="w-5 h-5 flex-shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Zustellung per E-Mail / Download (Versandfrei)</span>
                </div>
            @endif

            <div class="max-h-[28rem] overflow-y-auto pr-1 custom-scrollbar"
                 x-data="{ imageModalOpen: false, imageModalUrl: '' }"
                 @open-image-modal.window="imageModalUrl = $event.detail; imageModalOpen = true">
                <ul role="list" class="divide-y divide-gray-200/80 text-sm font-medium text-gray-900">
                    @foreach($cart->items as $item)
                        @php
                            $displayImageUrl = null;
                            if ($item->product && method_exists($item->product, 'resolveConfigurationImageUrl')) {
                                $displayImageUrl = $item->product->resolveConfigurationImageUrl(is_array($item->configuration) ? $item->configuration : null);
                            }
                            if (!$displayImageUrl) {
                                $displayImage = null;
                                if (!empty($item->product->media_gallery)) {
                                    foreach ($item->product->media_gallery as $media) {
                                        if (($media['type'] ?? '') === 'image' && !empty($media['path'])) {
                                            $displayImage = $media['path'];
                                            break;
                                        }
                                    }
                                }
                                if (!$displayImage && !empty($item->product->preview_image_path)) {
                                    $displayImage = $item->product->preview_image_path;
                                }
                                $displayImageUrl = $displayImage ? asset('storage/' . ltrim(preg_replace('#^/?storage/#', '', $displayImage), '/')) : null;
                            }

                            $snapshotFront = null;
                            $snapshotBack = null;
                            if (!empty($item->configuration['snapshot_path'])) {
                                $sp = $item->configuration['snapshot_path'];
                                if (is_array($sp)) {
                                    $snapshotFront = $sp['front'] ?? null;
                                    $snapshotBack = $sp['back'] ?? null;
                                } else {
                                    $snapshotFront = $sp;
                                }
                            }

                            $frontUrl = resolve_snapshot_url($snapshotFront, $displayImageUrl);
                            $backUrl = resolve_snapshot_url($snapshotBack, null);
                            $hasFrontSnapshot = $frontUrl && $frontUrl !== $displayImageUrl;
                            $hasSnapshots = $hasFrontSnapshot || $backUrl;
                            $hasBothSnapshots = $hasFrontSnapshot && $backUrl;

                            $hasPersonalization = !empty($item->configuration) && is_array($item->configuration) && ($item->product->isPersonalizable() ?? true);
                            $hasTexts = $hasPersonalization && (!empty($item->configuration['texts']) || !empty($item->configuration['text']) || !empty($item->configuration['texts_back']));

                            $logoCount = 0;
                            $fileCount = 0;
                            if ($hasPersonalization) {
                                $logoCount = count(array_filter((array)($item->configuration['logos'] ?? []), fn($i) => is_string($i) && trim($i) !== ''));
                                $fileCount = count(array_filter((array)($item->configuration['files'] ?? []), fn($i) => is_string($i) && trim($i) !== ''));
                                if (is_string($item->configuration['logo_storage_path'] ?? null) && trim($item->configuration['logo_storage_path']) !== '') {
                                    $logoCount++;
                                }
                            }
                            $totalMotivs = max($logoCount, $fileCount);
                        @endphp

                        <li class="py-4 first:pt-2" x-data="{ showText: false, showMotiv: false }">
                            <div class="flex items-start gap-3.5">
                                {{-- Vorschaubilder (Nebeneinander statt untereinander) --}}
                                @if($hasSnapshots)
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if($hasFrontSnapshot)
                                            <div @click="$dispatch('open-image-modal', '{{ $frontUrl }}')"
                                                 title="Vorderseite vergrößern"
                                                 class="relative {{ $hasBothSnapshots ? 'w-16 h-16' : 'w-20 h-20' }} rounded-xl bg-white border border-gray-200 shadow-2xs overflow-hidden p-1 cursor-pointer group hover:border-primary transition-all">
                                                <img src="{{ $frontUrl }}" @if($displayImageUrl) onerror="this.onerror=null; this.src='{{ $displayImageUrl }}';" @endif alt="{{ $item->product->name }} Vorderseite" class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300">
                                                <span class="absolute bottom-0 inset-x-0 bg-gray-900/65 text-white text-[7px] font-bold uppercase tracking-wider text-center py-0.5 leading-none">
                                                    {{ $hasBothSnapshots ? 'Vorn' : 'Vorderseite' }}
                                                </span>
                                            </div>
                                        @endif
                                        @if($backUrl)
                                            <div @click="$dispatch('open-image-modal', '{{ $backUrl }}')"
                                                 title="Rückseite vergrößern"
                                                 class="relative {{ $hasBothSnapshots ? 'w-16 h-16' : 'w-20 h-20' }} rounded-xl bg-white border border-gray-200 shadow-2xs overflow-hidden p-1 cursor-pointer group hover:border-primary transition-all">
                                                <img src="{{ $backUrl }}" alt="{{ $item->product->name }} Rückseite" class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300">
                                                <span class="absolute bottom-0 inset-x-0 bg-gray-900/65 text-white text-[7px] font-bold uppercase tracking-wider text-center py-0.5 leading-none">
                                                    {{ $hasBothSnapshots ? 'Hinten' : 'Rückseite' }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    @if($displayImageUrl)
                                        <div @click="$dispatch('open-image-modal', '{{ $displayImageUrl }}')"
                                             title="Bild vergrößern"
                                             class="flex-none w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden bg-gray-100 border border-gray-200 shadow-2xs cursor-pointer group hover:border-primary transition-all">
                                            <img src="{{ $displayImageUrl }}" alt="{{ $item->product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                        </div>
                                    @else
                                        <div class="flex-none w-16 h-16 sm:w-20 sm:h-20 rounded-xl bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-300">
                                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    @endif
                                @endif

                                {{-- Produktinfos & Preis --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2.5">
                                        <h3 class="font-serif font-bold text-gray-900 text-sm sm:text-base leading-snug break-words">
                                            {{ !empty($item->configuration['ingame_item_name']) ? $item->configuration['ingame_item_name'] : $item->product->name }}
                                        </h3>
                                        <div class="text-right shrink-0">
                                            <p class="font-serif font-bold text-gray-900 text-sm sm:text-base whitespace-nowrap">
                                                {{ number_format(($item->unit_price * $item->quantity) / 100, 2, ',', '.') }}&nbsp;€
                                            </p>
                                            @if($item->quantity > 1)
                                                <p class="text-[10px] text-gray-400 font-normal whitespace-nowrap">
                                                    je {{ number_format($item->unit_price / 100, 2, ',', '.') }}&nbsp;€
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Varianten- & Extras-Anzeige im Checkout --}}
                                    @if(!empty($item->configuration['variant_name']))
                                        <p class="text-[10px] font-black uppercase tracking-widest text-primary mt-0.5 break-words">
                                            Ausführung: {{ $item->configuration['variant_name'] }}
                                        </p>
                                    @endif

                                    @if(!empty($item->configuration['custom_fields_data']) && is_array($item->configuration['custom_fields_data']))
                                        <div class="mt-1.5 flex flex-wrap gap-1">
                                            @foreach($item->configuration['custom_fields_data'] as $cfRow)
                                                @if(is_array($cfRow) && trim((string) ($cfRow['value'] ?? '')) !== '')
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] bg-gray-100 text-gray-800 border border-gray-200/80">
                                                        <span class="text-gray-500 font-semibold">{{ $cfRow['label'] ?? 'Gravur' }}:</span>
                                                        <span class="font-bold">{{ $cfRow['value'] }}</span>
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    @if(!empty($item->configuration['addons_summary']) && is_array($item->configuration['addons_summary']))
                                        <div class="mt-2 flex flex-col gap-1.5">
                                            @foreach($item->configuration['addons_summary'] as $addon)
                                                @if(!empty($addon['name']))
                                                    @php
                                                        $chkAddonImg = \App\Models\Product\Product::resolveAddonImageUrl($addon);
                                                        $chkAddonDesc = \App\Models\Product\Product::resolveAddonDescription($addon, $item->product ?? null);
                                                        $chkAddonPrice = (($addon['price_cents'] ?? 0) > 0)
                                                            ? '+ ' . number_format($addon['price_cents'] / 100, 2, ',', '.') . ' €'
                                                            : '';
                                                    @endphp
                                                    <button type="button"
                                                            @click="$dispatch('open-addon-modal', @js([
                                                                'name' => $addon['name'],
                                                                'price' => $chkAddonPrice,
                                                                'image' => $chkAddonImg,
                                                                'description' => $chkAddonDesc,
                                                            ]))"
                                                            title="Extra-Bild &amp; Details in Großansicht öffnen"
                                                            class="group/addon inline-flex items-center gap-2 w-fit text-left text-xs text-gray-800 hover:text-primary transition-colors cursor-zoom-in">
                                                        @if($chkAddonImg)
                                                            <span class="relative w-6 h-6 rounded-md overflow-hidden border border-gray-200 group-hover/addon:border-primary/60 shadow-2xs shrink-0 block">
                                                                <img src="{{ $chkAddonImg }}" alt="{{ $addon['name'] }}" class="w-full h-full object-cover group-hover/addon:scale-110 transition-transform duration-200">
                                                            </span>
                                                        @endif
                                                        <span class="font-medium leading-snug">{{ $addon['name'] }}</span>
                                                        @if(($addon['price_cents'] ?? 0) > 0)
                                                            <span class="text-primary font-semibold whitespace-nowrap">(+{{ number_format($addon['price_cents'] / 100, 2, ',', '.') }}&nbsp;€)</span>
                                                        @endif
                                                        <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.75">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    </button>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    @if(!empty($item->configuration['is_gift_voucher']))
                                        <div class="mt-1 space-y-0.5 text-[11px] text-gray-600 font-normal">
                                            @if(!empty($item->configuration['recipient_name']))
                                                <p><span class="font-bold text-gray-800">Für:</span> {{ $item->configuration['recipient_name'] }}</p>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Menge & Personalisierungs-Buttons in einer aufgeräumten Zeile --}}
                                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                        <span class="inline-flex items-center text-[11px] font-bold text-gray-600 bg-gray-100 border border-gray-200/80 px-2 py-0.5 rounded-md whitespace-nowrap">
                                            {{ $item->quantity }}x
                                        </span>

                                        @if(!empty($item->configuration['is_gift_voucher']))
                                            @if(($item->configuration['delivery_method'] ?? 'email') === 'post')
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-md">
                                                    ✉️ Per Post (+&nbsp;3,50&nbsp;€)
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-md">
                                                    📧 Per E-Mail (Gratis)
                                                </span>
                                            @endif
                                        @endif

                                        @if($hasTexts)
                                            <button type="button"
                                                    @click="showText = !showText; showMotiv = false"
                                                    :class="showText ? 'bg-primary text-white border-primary shadow-2xs' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100'"
                                                    class="inline-flex items-center gap-1 text-[10px] uppercase font-bold px-2 py-0.5 rounded-md border transition cursor-pointer">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                Text
                                            </button>
                                        @endif

                                        @if($totalMotivs > 0)
                                            <button type="button"
                                                    @click="showMotiv = !showMotiv; showText = false"
                                                    :class="showMotiv ? 'bg-primary text-white border-primary shadow-2xs' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100'"
                                                    class="inline-flex items-center gap-1 text-[10px] uppercase font-bold px-2 py-0.5 rounded-md border transition cursor-pointer">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                Motiv ({{ $totalMotivs }})
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Ausklappbare Personalisierungs-Details über die volle Breite --}}
                            @if($hasPersonalization)
                                <div x-show="showText" x-collapse x-cloak class="mt-3 bg-white rounded-xl p-3 border border-gray-200/80 text-xs text-gray-600 text-left break-words shadow-2xs">
                                    @php $conf = $item->configuration; @endphp
                                    @if(!empty($conf['custom_fields_data']) && is_array($conf['custom_fields_data']))
                                        <div class="space-y-1">
                                            @foreach($conf['custom_fields_data'] as $cfRow)
                                                @if(is_array($cfRow) && trim((string) ($cfRow['value'] ?? '')) !== '')
                                                    <div>
                                                        <strong class="text-gray-800">{{ $cfRow['label'] ?? 'Gravur' }}:</strong>
                                                        <span class="text-gray-900 font-semibold">{{ $cfRow['value'] }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @elseif(!empty($conf['texts']))
                                        @foreach($conf['texts'] as $idx => $t)
                                            @if(!empty($t['text']))
                                                <div class="mb-2 last:mb-0">
                                                    <strong class="text-gray-800">{{ count($conf['texts']) > 1 ? 'Text '.($idx+1).' (Vorderseite)' : 'Gravur (Vorderseite)' }}:</strong><br>
                                                    "{!! nl2br(e($t['text'])) !!}"<br>
                                                    <span class="text-[10px] text-gray-400">Schrift: {{ $t['font'] ?? 'Standard' }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    @elseif(!empty($conf['text']))
                                        <div class="mb-2 last:mb-0">
                                            <strong class="text-gray-800">Gravur:</strong><br>
                                            "{!! nl2br(e($conf['text'])) !!}"<br>
                                            <span class="text-[10px] text-gray-400">Schrift: {{ $conf['font'] ?? 'Standard' }}</span>
                                        </div>
                                    @endif

                                    @if(!empty($conf['texts_back']))
                                        <div class="mt-2 pt-2 border-t border-gray-100"></div>
                                        @foreach($conf['texts_back'] as $idx => $t)
                                            @if(!empty($t['text']))
                                                <div class="mb-2 last:mb-0">
                                                    <strong class="text-gray-800">{{ count($conf['texts_back']) > 1 ? 'Text '.($idx+1).' (Rückseite)' : 'Gravur (Rückseite)' }}:</strong><br>
                                                    "{!! nl2br(e($t['text'])) !!}"<br>
                                                    <span class="text-[10px] text-gray-400">Schrift: {{ $t['font'] ?? 'Standard' }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    @endif
                                </div>

                                <div x-show="showMotiv" x-collapse x-cloak class="mt-3 bg-white rounded-xl p-3 border border-gray-200/80 text-xs text-gray-600 text-left shadow-2xs">
                                    @php $conf = $item->configuration; @endphp
                                    @if(!empty($conf['files']))
                                        @php $validFiles = array_filter((array)$conf['files'], fn($i) => is_string($i) && trim($i) !== ''); @endphp
                                        @if(count($validFiles) > 0)
                                            <strong class="block mb-1.5 text-gray-800">Hinterlegte Bilder: {{ count($validFiles) }} Datei(en)</strong>
                                            <div class="flex gap-2 flex-wrap">
                                                @foreach($validFiles as $file)
                                                    @if(in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp']))
                                                        <a href="{{ asset('storage/'.$file) }}" @click.prevent="$dispatch('open-image-modal', '{{ asset('storage/'.$file) }}')" class="w-12 h-12 rounded-lg border border-gray-200 overflow-hidden block hover:border-primary transition cursor-pointer">
                                                            <img src="{{ asset('storage/'.$file) }}" class="w-full h-full object-cover">
                                                        </a>
                                                    @else
                                                        <a href="{{ asset('storage/'.$file) }}" target="_blank" class="w-12 h-12 rounded-lg border border-gray-200 flex items-center justify-center text-[10px] text-gray-400 bg-white hover:border-primary hover:text-primary transition">PDF</a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>

                {{-- Lightbox Modal für Produktvorschau / Motive im Checkout --}}
                <div x-show="imageModalOpen"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-[#262016]/45 backdrop-blur-md"
                     @click.self="imageModalOpen = false"
                     @keydown.escape.window="imageModalOpen = false">
                    <div class="relative bg-[#fdfbf7] border border-[#e6d7bc] rounded-[2rem] shadow-[0_25px_70px_-15px_rgba(120,90,40,0.35)] max-w-lg w-full overflow-hidden p-5" @click.stop>
                        <button type="button"
                                @click="imageModalOpen = false"
                                class="absolute top-3.5 right-3.5 z-10 w-8 h-8 rounded-full bg-white hover:bg-[#f7f1e6] border border-[#e6d7bc] flex items-center justify-center text-gray-600 transition-colors cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <div class="flex items-center justify-center max-h-[75vh] overflow-auto p-3 rounded-2xl bg-gradient-to-b from-white to-[#faf6ef] border border-[#eadcc4]">
                            <template x-if="imageModalOpen && imageModalUrl">
                                <img :src="imageModalUrl" alt="Vorschau" class="max-w-full max-h-[68vh] object-contain rounded-xl">
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Globale Hellmodus-Großansicht für Produkt-Extras --}}
                <x-shop.addon-preview-modal />
            </div>

            @php
                $hasPhysicalItems = collect($cart->items ?? [])->contains(function($ci) {
                    return ($ci->product->type ?? 'physical') === 'physical';
                });
            @endphp
            @if($hasPhysical)
                <div class="mt-4 p-3 bg-emerald-50/70 border border-emerald-100 rounded-xl flex items-center gap-3 text-xs text-emerald-900">
                    <span class="text-base shrink-0">🚚</span>
                    <div class="min-w-0">
                        <span class="font-bold block text-emerald-950">{{ \App\Models\Delivery\DeliverySetting::getCurrentDeliveryText() }}</span>
                    </div>
                </div>
            @endif

            {{-- OPTIONALES KUNDENKONTO (DSK-Beschluss vom 24.03.2022 / Art. 5 Abs. 1 lit. c DSGVO) --}}
            @if(!auth('customer')->check())
                <div class="mt-3">
                    <label for="create_account" class="flex items-start gap-2.5 bg-amber-50/50 px-3.5 py-2.5 rounded-xl border border-amber-200/70 cursor-pointer hover:bg-amber-50/80 transition-colors select-none">
                        <input id="create_account" type="checkbox" wire:model.live="create_account" class="mt-0.5 h-4 w-4 text-primary rounded border-gray-300 focus:ring-primary cursor-pointer shrink-0">
                        <span class="text-xs text-gray-700 leading-snug">
                            <strong class="font-semibold text-gray-900">Kostenloses Kundenkonto anlegen</strong> <span class="text-gray-400 text-[11px]">(optional)</span>
                            <span class="block text-[11px] text-gray-500 mt-0.5">Aktivierungslink per E-Mail für Bestellstatus, Design-Korrektur &amp; Seelenfunken-Treuepunkte.</span>
                        </span>
                    </label>
                </div>
            @endif

            {{-- Gesamtsumme inkl. MwSt. & Versandkosten (§ 312j Abs. 2 & 3 BGB) --}}
            <div class="mt-4 pt-4 border-t border-gray-100">
                <x-shop.cost-summary :totals="$totals" :country="$targetCountry" :showTitle="false">
                    <button type="button" wire:click="removeCoupon" class="text-red-400 hover:text-red-600 transition cursor-pointer" title="Gutschein entfernen">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </x-shop.cost-summary>
            </div>

            {{-- KOMPAKTE RECHTS- & GEWÄHRLEISTUNGS-BOX DIREKT ÜBER DEM BESTELL-BUTTON --}}
            <div id="checkout-legal-box"
                 x-data="{ zoomModal: false }"
                 class="p-3.5 rounded-xl mt-4 border transition-all duration-200"
                 :class="showLegalWarning && (!terms || !privacy || (requiresDigitalWaiver && !digitalWaiver)) ? 'bg-red-50/40 border-red-400 ring-2 ring-red-500/15' : 'bg-gray-50/90 border-gray-200/80'">
                <div class="flex items-start rounded-lg transition-all duration-200"
                     :class="showLegalWarning && !terms ? 'bg-red-50 border border-red-300 p-2 -mx-1' : ''">
                    <div class="flex h-5 items-center">
                        <input id="terms"
                               x-model="terms"
                               wire:model.live="terms_accepted"
                               type="checkbox"
                               :class="showLegalWarning && !terms ? 'border-red-500 ring-2 ring-red-500/30 bg-red-50 text-red-600 focus:ring-red-500' : 'border-gray-300 text-primary focus:ring-primary'"
                               class="h-4 w-4 rounded cursor-pointer transition-all">
                    </div>
                    <div class="ml-2.5 text-xs">
                        <label for="terms"
                               :class="showLegalWarning && !terms ? 'text-red-700 font-semibold' : 'text-gray-700 font-medium'"
                               class="cursor-pointer select-none leading-snug block transition-colors">
                            Ich akzeptiere die <a href="/agb" target="_blank" class="text-primary underline hover:text-primary-dark">AGB</a> (inkl. <a href="/agb#urheberrecht-freistellung" target="_blank" class="text-primary underline hover:text-primary-dark">Rechtegarantie für eigene Motive</a>), habe die <a href="/widerruf" target="_blank" class="text-primary underline hover:text-primary-dark">Widerrufsbelehrung</a> <span class="text-gray-500 font-normal">(kein Widerruf bei personalisierten Unikaten gem. § 312g Abs. 2 Nr. 1 BGB)</span> sowie die <a href="/datenschutz" target="_blank" class="text-primary underline hover:text-primary-dark">Datenschutzerklärung</a> zur Kenntnis genommen. <span class="text-red-500 font-bold">*</span>
                        </label>
                    </div>
                </div>

                @if($hasDigitalOrIngame)
                    <div class="flex items-start mt-2.5 pt-2.5 border-t -mx-2 px-2 py-2 rounded-lg transition-all duration-200"
                         :class="showLegalWarning && !digitalWaiver ? 'bg-red-50 border border-red-400 ring-2 ring-red-500/20' : 'border-blue-200/70 bg-blue-50/70'">
                        <div class="flex h-5 items-center">
                            <input id="digital_waiver"
                                   x-model="digitalWaiver"
                                   wire:model.live="digital_waiver_accepted"
                                   type="checkbox"
                                   :class="showLegalWarning && !digitalWaiver ? 'border-red-500 ring-2 ring-red-500/30 bg-red-50 text-red-600 focus:ring-red-500' : 'border-blue-300 text-primary focus:ring-primary'"
                                   class="h-4 w-4 rounded cursor-pointer transition-all">
                        </div>
                        <div class="ml-2.5 text-xs">
                            <label for="digital_waiver"
                                   :class="showLegalWarning && !digitalWaiver ? 'text-red-700 font-semibold' : 'text-blue-950 font-medium'"
                                   class="cursor-pointer select-none leading-snug block transition-colors">
                                Ich stimme ausdrücklich zu, dass mit der Ausführung des Vertrags über die <strong>digitalen Inhalte</strong> sofort nach Zahlungsabschluss (vor Ablauf der Widerrufsfrist) begonnen wird, und bestätige meine Kenntnis davon, dass mein <strong>Widerrufsrecht mit Beginn der Vertragsausführung erlischt (§ 356 Abs. 5 BGB)</strong>. <span class="text-red-500 font-bold">*</span>
                            </label>
                        </div>
                    </div>
                @endif

                <div x-show="showLegalWarning && (!terms || !privacy || (requiresDigitalWaiver && !digitalWaiver))"
                     x-cloak
                     style="display: none;"
                     class="mt-2.5 flex items-center gap-2 text-red-700 bg-red-100/90 border border-red-300 rounded-lg px-3 py-2 text-xs font-bold animate-fade-in">
                    <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>Bitte bestätige die rot markierten Pflichthaken, um fortzufahren.</span>
                </div>

                @if($errors->has('terms_accepted') || $errors->has('privacy_accepted') || $errors->has('digital_waiver_accepted'))
                    <div class="text-red-500 text-xs mt-2 font-bold">
                        {{ $errors->first('digital_waiver_accepted') ?: 'Bitte stimme allen rechtlichen Bedingungen zu.' }}
                    </div>
                @endif

                {{-- 1-Klick Pop-up-Link für die harmonisierte EU-Gewährleistungsmitteilung (Durchführungsverordnung (EU) 2025/1960) --}}
                <div class="mt-2.5 pt-2.5 border-t border-gray-200/70 flex items-center justify-between gap-2 text-[11px]">
                    <button type="button"
                            @click="zoomModal = true"
                            class="inline-flex items-center gap-1.5 text-gray-600 hover:text-gray-900 transition-colors cursor-pointer text-left focus:outline-none group">
                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[#003399] text-white font-serif text-[9px] font-bold shrink-0">🇪🇺</span>
                        <span class="underline decoration-gray-300 group-hover:decoration-primary">Informationen zur gesetzlichen Gewährleistung (2 Jahre EU-Schutz)</span>
                    </button>
                    <button type="button"
                            @click="zoomModal = true"
                            class="text-[11px] text-primary hover:text-primary-dark font-bold shrink-0 cursor-pointer focus:outline-none">
                        EU-Mitteilung öffnen
                    </button>
                </div>

                {{-- Pop-up zur Direktansicht der offiziellen farbigen EU-Grafik in Originalgröße --}}
                <div x-show="zoomModal"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs"
                     @click.self="zoomModal = false"
                     @keydown.escape.window="zoomModal = false">
                    <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] flex flex-col overflow-hidden animate-fade-in"
                         @click.stop>
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                            <div class="flex items-center gap-2">
                                <span class="text-base">🇪🇺</span>
                                <h3 class="text-sm font-bold text-gray-900">Gesetzliche Gewährleistung (EU-Mitteilung)</h3>
                            </div>
                            <button type="button" @click="zoomModal = false" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="p-4 overflow-y-auto flex justify-center bg-gray-100/60 max-h-[75vh]">
                            <img src="{{ asset('shop/frontend/legal/eu_gewaehrleistung_notice_de.svg') }}"
                                 alt="EU Harmonisierte Mitteilung über die gesetzliche Gewährleistung"
                                 class="max-w-full h-auto rounded-lg shadow-sm border border-gray-200">
                        </div>
                        <div class="p-3 border-t border-gray-100 bg-white flex justify-between items-center text-xs">
                            <a href="https://europa.eu/youreurope/garantien" target="_blank" rel="noopener noreferrer" class="text-primary hover:text-primary-dark hover:underline flex items-center gap-1 font-medium transition-colors">
                                <span>europa.eu/youreurope/garantien</span>
                            </a>
                            <button type="button" @click="zoomModal = false" class="px-4 py-1.5 bg-gray-900 text-white rounded-lg font-semibold hover:bg-black transition-colors">
                                Schließen
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @if($errors->has('coupon') || $errors->has('cart') || $errors->has('payment'))
                <div class="mt-4 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold space-y-1 animate-fade-in">
                    @error('coupon')
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                    @error('cart')
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                    @error('payment')
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                </div>
            @endif

            @if($errors->any())
                <div class="mt-4 p-3.5 rounded-xl bg-red-50 border border-red-300 text-red-700 text-xs font-semibold space-y-1.5 animate-fade-in">
                    <div class="flex items-center gap-2 font-bold text-red-800">
                        <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>Bitte prüfe noch folgende Angaben:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-0.5 text-[11px] text-red-700">
                        @foreach($errors->all() as $errMsg)
                            <li>{{ $errMsg }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div id="checkout-submit-error" class="hidden mt-4 p-3.5 rounded-xl bg-red-50 border border-red-300 text-red-700 text-xs font-bold animate-fade-in"></div>

            <div class="mt-5">
                <button id="submit-button"
                        type="submit"
                        x-bind:disabled="isProcessing"
                        @click="if (!terms || (requiresDigitalWaiver && !digitalWaiver)) { $event.preventDefault(); $event.stopPropagation(); showLegalWarning = true; document.getElementById('checkout-legal-box')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); }"
                        :class="(!terms || (requiresDigitalWaiver && !digitalWaiver))
                            ? 'bg-gray-300 text-gray-500 shadow-none hover:bg-gray-300'
                            : 'bg-gray-900 text-white shadow-lg shadow-gray-900/20 hover:bg-black enabled:hover:-translate-y-0.5'"
                        class="w-full rounded-full border border-transparent py-3.5 px-4 text-base font-bold focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed disabled:shadow-none transition-all transform cursor-pointer">
                    <span id="button-text">Zahlungspflichtig bestellen</span>
                    <div id="spinner" class="hidden flex items-center justify-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Verarbeite...
                    </div>
                </button>
                <p class="text-[11px] text-gray-400 text-center mt-2.5">Durch Klicken auf den Button schließt du einen zahlungspflichtigen Kaufvertrag ab.</p>

                {{-- Sinnstiftung: Freude schenken, Soziales leisten & Funken sammeln --}}
                <div class="mt-4 pt-3.5 border-t border-gray-100">
                    <div class="relative overflow-hidden rounded-xl bg-gradient-to-r from-amber-500/10 via-amber-400/5 to-amber-500/10 border border-amber-200/60 p-3.5 shadow-2xs">
                        <div class="w-fit sm:w-full mx-auto flex flex-col sm:grid sm:grid-cols-3 items-start sm:items-center justify-between gap-2.5 sm:gap-2 text-left sm:text-center">
                            <div class="flex sm:flex-col items-center gap-2.5 sm:gap-1.5">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-xs shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-gray-900 leading-tight tracking-tight">Freude schenken</p>
                                    <p class="text-[11px] sm:text-[10px] text-amber-900/80 font-medium leading-tight mt-0.5">Mit Herz gefertigt</p>
                                </div>
                            </div>

                            <div class="flex sm:flex-col items-center gap-2.5 sm:gap-1.5 sm:border-x sm:border-amber-200/70 sm:px-1">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gradient-to-br from-rose-400 to-rose-600 text-white shadow-xs shrink-0">
                                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                    </svg>
                                </span>
                                @php
                                    $checkoutHerzensEuro = \App\Services\HerzensprojektService::resolveFormattedEuro();
                                    $checkoutHerzensUnit = \App\Services\HerzensprojektService::resolveUnitLabel(true);
                                @endphp
                                <a href="{{ route('manufacture') }}#herzensprojekte" target="_blank" title="Wir führen {{ $checkoutHerzensEuro }} € {{ $checkoutHerzensUnit }} aus unseren Unternehmenserlösen an unsere regionalen Herzensprojekte für Kinder ab (Details & Transparenzhinweis)" class="group hover:opacity-90 transition-opacity">
                                    <p class="text-xs font-bold text-gray-900 leading-tight tracking-tight group-hover:text-primary transition-colors">Soziales leisten</p>
                                    <p class="text-[11px] sm:text-[10px] text-amber-900/80 font-medium leading-tight mt-0.5">Kinder aktiv unterstützen ({{ $checkoutHerzensEuro }}&nbsp;€ {{ $checkoutHerzensUnit }})</p>
                                </a>
                            </div>

                            <div class="flex sm:flex-col items-center gap-2.5 sm:gap-1.5">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gradient-to-br from-amber-400 to-yellow-600 text-white shadow-xs shrink-0">
                                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                        <path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-gray-900 leading-tight tracking-tight">Funken sammeln</p>
                                    <p class="text-[11px] sm:text-[10px] text-amber-900/80 font-medium leading-tight mt-0.5">Treuepunkte &amp; Rabatte</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

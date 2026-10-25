<div class="mt-10 lg:mt-0 lg:sticky lg:top-24 z-20"
     x-data="{
        isProcessing: false,
        terms: @entangle('terms_accepted'),
        privacy: @entangle('privacy_accepted')
     }"
     @checkout-processing-done.window="isProcessing = false"
     @checkout-processing.window="isProcessing = true">

    <div class="bg-white p-5 sm:p-6 rounded-2xl border border-gray-150 shadow-md transition-all duration-300">

        <div x-show="isProcessing" x-cloak class="flex flex-col items-center justify-center py-12 space-y-6 animate-fade-in">
            <div x-data x-init="window.scrollTo({ top: 0, behavior: 'smooth' })" class="relative w-64 h-64 sm:w-80 sm:h-80 mx-auto">
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
            @endphp

            @if($country === 'DE' && $hasStandardPhysical)
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
                                            {{ $item->product->name }}
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

                                    {{-- Varianten-Anzeige im Checkout --}}
                                    @if(!empty($item->configuration['variant_name']))
                                        <p class="text-[10px] font-black uppercase tracking-widest text-primary mt-0.5 break-words">
                                            {{ $item->configuration['variant_name'] }}
                                        </p>
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
                                    @if(!empty($conf['texts']))
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
                     class="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs"
                     @click.self="imageModalOpen = false"
                     @keydown.escape.window="imageModalOpen = false">
                    <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden p-4" @click.stop>
                        <button type="button"
                                @click="imageModalOpen = false"
                                class="absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-600 transition-colors cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <div class="flex items-center justify-center max-h-[75vh] overflow-auto p-2">
                            <template x-if="imageModalOpen && imageModalUrl">
                                <img :src="imageModalUrl" alt="Vorschau" class="max-w-full max-h-[70vh] object-contain rounded-lg">
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            @php
                $hasPhysicalItems = collect($cart->items ?? [])->contains(function($ci) {
                    return ($ci->product->type ?? 'physical') === 'physical';
                });
            @endphp
            @if($hasPhysicalItems)
                <div class="mt-4 p-3 bg-emerald-50/70 border border-emerald-100 rounded-xl flex items-center gap-3 text-xs text-emerald-900">
                    <span class="text-base shrink-0">🚚</span>
                    <div class="min-w-0">
                        <span class="font-bold block text-emerald-950">{{ \App\Models\Delivery\DeliverySetting::getCurrentDeliveryText() }}</span>
                    </div>
                </div>
            @endif

            <div class="mt-4 pt-4 border-t border-gray-100">
                <x-shop.cost-summary :totals="$totals" :country="$country" :showTitle="false">
                    <button type="button" wire:click="removeCoupon" class="text-red-400 hover:text-red-600 transition cursor-pointer" title="Gutschein entfernen">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </x-shop.cost-summary>
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

            <div class="space-y-3 bg-gray-50/90 p-3.5 rounded-xl mt-4 border border-gray-150">
                <div class="flex items-start">
                    <div class="flex h-5 items-center">
                        <input id="terms" wire:model.live="terms_accepted" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer">
                    </div>
                    <div class="ml-3 text-xs sm:text-sm">
                        <label for="terms" class="font-medium text-gray-700 cursor-pointer select-none">
                            Ich habe die <a href="/agb" target="_blank" class="text-primary underline hover:text-primary-dark">AGB</a> gelesen und akzeptiere diese. <span class="text-red-500">*</span>
                        </label>
                    </div>
                </div>
                <div class="flex items-start">
                    <div class="flex h-5 items-center">
                        <input id="privacy" wire:model.live="privacy_accepted" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer">
                    </div>
                    <div class="ml-3 text-xs sm:text-sm">
                        <label for="privacy" class="font-medium text-gray-700 cursor-pointer select-none">
                            Ich habe die <a href="/datenschutz" target="_blank" class="text-primary underline hover:text-primary-dark">Datenschutzerklärung</a> zur Kenntnis genommen. <span class="text-red-500">*</span>
                        </label>
                    </div>
                </div>
                @if($errors->has('terms_accepted') || $errors->has('privacy_accepted'))
                    <div class="text-red-500 text-xs mt-1 font-bold">Bitte stimme den rechtlichen Bedingungen zu.</div>
                @endif
            </div>

            {{-- EU-Gewährleistung Hinweis (Aufklappbar vor dem Bestell-Button) --}}
            <div x-data="{ openWarranty: false, zoomModal: false }" class="mt-3 border border-gray-200 bg-gray-50/80 rounded-xl overflow-hidden transition-all duration-200">
                <button type="button"
                        @click="openWarranty = !openWarranty"
                        class="w-full px-3.5 py-2.5 flex items-center justify-between text-left text-xs font-medium text-gray-900 hover:bg-gray-100/70 transition-colors focus:outline-none select-none">
                    <span class="flex items-center gap-2">
                        <span class="flex-shrink-0 flex items-center justify-center w-5 h-5 rounded-full bg-[#003399] text-white font-serif text-[11px] font-bold shadow-xs">
                            🇪🇺
                        </span>
                        <span>
                            <strong class="font-semibold text-gray-900">Gesetzliche Gewährleistung:</strong>
                            <span class="text-gray-600">Mind. 2 Jahre EU-Schutz</span>
                        </span>
                    </span>
                    <span class="flex items-center gap-1 text-[11px] text-primary hover:text-primary-dark font-bold ml-2 flex-shrink-0 transition-colors">
                        <span x-text="openWarranty ? 'Weniger anzeigen' : 'Hinweis anzeigen'">Hinweis anzeigen</span>
                        <svg class="w-3.5 h-3.5 text-primary transform transition-transform duration-200" :class="{ 'rotate-180': openWarranty }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </span>
                </button>

                <div x-show="openWarranty" x-collapse x-cloak class="border-t border-gray-100 bg-white p-3.5 text-xs text-gray-700 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="w-16 h-22 flex-shrink-0 bg-white border border-gray-200 rounded p-1 shadow-xs cursor-pointer hover:border-primary hover:shadow-md transition-all group"
                             @click="zoomModal = true"
                             title="Zum Vergrößern klicken">
                            <img src="{{ asset('shop/frontend/legal/eu_gewaehrleistung_notice_de.svg') }}"
                                 alt="EU Harmonisierte Mitteilung über die gesetzliche Gewährleistung"
                                 class="w-full h-full object-contain group-hover:scale-105 transition-transform">
                        </div>
                        <div class="space-y-1 flex-1">
                            <p class="font-bold text-gray-900 text-[11px] leading-tight">
                                Harmonisierte Mitteilung über die gesetzliche Gewährleistung der Vertragsmäßigkeit
                            </p>
                            <p class="text-[11px] text-gray-600 leading-relaxed">
                                Für Waren in der EU gilt eine gesetzliche Gewährleistungsfrist von mindestens <strong>zwei Jahren</strong> ab Lieferung (kostenlose Reparatur, Neulieferung oder Kaufpreiserstattung bei Mängeln).
                            </p>
                            <div class="flex flex-wrap items-center gap-3 pt-1 text-[11px]">
                                <button type="button"
                                        @click="zoomModal = true"
                                        class="inline-flex items-center gap-1 font-semibold text-primary hover:text-primary-dark underline cursor-pointer transition-colors">
                                    <svg class="w-3 h-3 text-primary flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                                    EU-Label vergrößern
                                </button>
                                <a href="https://europa.eu/youreurope/garantien"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1 font-semibold text-primary hover:text-primary-dark underline transition-colors">
                                    <span>Offizielle EU-Infos</span>
                                    <svg class="w-3.5 h-3.5 text-primary flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Modal zur Großansicht des offiziellen EU-Hinweises --}}
                    <div x-show="zoomModal"
                         x-cloak
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
                                    <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                                <button type="button" @click="zoomModal = false" class="px-4 py-1.5 bg-gray-900 text-white rounded-lg font-semibold hover:bg-black transition-colors">
                                    Schließen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <button id="submit-button"
                        type="submit"
                        x-bind:disabled="!terms || !privacy"
                        class="w-full rounded-full border border-transparent bg-gray-900 py-3.5 px-4 text-base font-bold text-white shadow-lg shadow-gray-900/20 hover:bg-black focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed disabled:shadow-none transition-all transform enabled:hover:-translate-y-0.5">
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

                {{-- Sinnstiftung: Freude schenken & Soziales leisten --}}
                <div class="mt-4 pt-3.5 border-t border-gray-100">
                    <div class="relative overflow-hidden rounded-xl bg-gradient-to-r from-amber-500/10 via-amber-400/5 to-amber-500/10 border border-amber-200/60 p-3 shadow-2xs">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-2.5 text-center sm:text-left">
                            <div class="flex items-center gap-2.5">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-xs shrink-0">
                                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-gray-900 leading-tight tracking-tight">Freude schenken</p>
                                    <p class="text-[11px] text-amber-900/80 font-medium leading-none mt-0.5">Mit Herz gefertigt</p>
                                </div>
                            </div>

                            <div class="hidden sm:block w-px h-6 bg-amber-200/80"></div>

                            <div class="flex items-center gap-2.5">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-xs shrink-0">
                                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-gray-900 leading-tight tracking-tight">Soziales leisten</p>
                                    <p class="text-[11px] text-amber-900/80 font-medium leading-none mt-0.5">Kinder aktiv unterstützen</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

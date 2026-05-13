<div class="mt-10 lg:mt-0 lg:col-span-5 sticky top-24 z-20"
     x-data="{
        isProcessing: false,
        terms: @entangle('terms_accepted'),
        privacy: @entangle('privacy_accepted'),
        get funkiState() {
            if (this.terms && this.privacy) return 'party';
            return 'normal';
        }
     }"
     @checkout-processing-done.window="isProcessing = false"
     @checkout-processing.window="isProcessing = true">

    <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-100 shadow-lg transition-all duration-300">

        <div x-show="isProcessing" x-cloak class="flex flex-col items-center justify-center py-12 space-y-6 animate-fade-in">
            <div x-data x-init="window.scrollTo({ top: 0, behavior: 'smooth' })" class="relative w-64 h-64 sm:w-80 sm:h-80 mx-auto">
                <img src="{{ asset('shop/projekt/funki/checkout/funki_happy.webp') }}" class="w-full h-full object-contain animate-bounce-slow" alt="Verarbeite Bestellung">
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
            <h2 class="text-lg font-medium text-gray-900 mb-6">Bestellübersicht</h2>

            @php
                $threshold = (int) shop_setting('shipping_free_threshold', 5000);
                $currentValue = $totals['subtotal_gross'];
                $hasPhysical = $cart->items->contains(fn($i) => ($i->product->type ?? 'physical') === 'physical');
                $percent = $threshold > 0 ? min(100, ($currentValue / $threshold) * 100) : 100;
                $missing = $totals['missing_for_free_shipping'];
                $isFree = $totals['is_free_shipping'];

                if (!$hasPhysical) $percent = 100;
            @endphp

            @if($country === 'DE' && $hasPhysical)
                <div class="mb-6 bg-gray-50 p-4 rounded-xl border border-gray-200">
                    @if($isFree)
                        <div class="flex items-center gap-2 text-green-600 font-bold text-sm animate-fade-in">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Glückwunsch! Versandkostenfrei.
                        </div>
                    @else
                        <p class="text-sm text-gray-700 font-medium mb-2">
                            Noch <span class="text-primary font-bold">{{ number_format($missing / 100, 2, ',', '.') }} €</span> bis zum <span class="text-green-600 font-bold">kostenlosen Versand!</span>
                        </p>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-primary h-2.5 rounded-full transition-all duration-500" style="width: {{ $percent }}%"></div>
                        </div>
                    @endif
                </div>
            @elseif(!$hasPhysical)
                <div class="mb-6 bg-blue-50 p-4 rounded-xl border border-blue-100 text-blue-800 text-sm flex gap-3 shadow-sm">
                    <svg class="w-5 h-5 flex-shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Sofort-Download / Service (Versandfrei)</span>
                </div>
            @endif

            <div class="max-h-96 overflow-y-auto pr-2 overscroll-contain custom-scrollbar">
                <ul role="list" class="divide-y divide-gray-200 text-sm font-medium text-gray-900">
                    @foreach($cart->items as $item)
                        <li class="flex items-start py-6 space-x-4">
                            @php
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
                            @endphp

                            @if($snapshotFront || $snapshotBack)
                                <div class="flex flex-col gap-2">
                                    @if($snapshotFront)
                                        <div class="flex-none w-20 h-20 rounded-md bg-white border border-gray-200 shadow-sm overflow-hidden p-1">
                                            <img src="{{ Storage::url($snapshotFront) }}" class="w-full h-full object-contain">
                                        </div>
                                    @endif
                                    @if($snapshotBack)
                                        <div class="flex-none w-20 h-20 rounded-md bg-white border border-gray-200 shadow-sm overflow-hidden p-1">
                                            <img src="{{ Storage::url($snapshotBack) }}" class="w-full h-full object-contain">
                                        </div>
                                    @endif
                                </div>
                            @else
                                @if($displayImage)
                                    <img src="{{ Storage::url($displayImage) }}" alt="{{ $item->product->name }}" class="flex-none w-20 h-20 rounded-md object-cover bg-gray-100 border border-gray-200 shadow-sm">
                                @else
                                    <div class="flex-none w-20 h-20 rounded-md bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-300">
                                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                            @endif

                            <div class="flex-auto space-y-1">
                                <h3 class="text-gray-900 font-bold">{{ $item->product->name }}</h3>

                                {{-- NEU: Varianten-Anzeige im Checkout --}}
                                @if(!empty($item->configuration['variant_name']))
                                    <p class="text-[10px] font-black uppercase tracking-widest text-primary">
                                        {{ $item->configuration['variant_name'] }}
                                    </p>
                                @endif

                                <p class="text-gray-500">{{ $item->quantity }}x</p>
                                @if(!empty($item->configuration) && is_array($item->configuration) && ($item->product->isPersonalizable() ?? true))
                                    <div x-data="{ showText: false, showMotiv: false }" class="mt-2">
                                        <div class="flex flex-wrap gap-2 mb-2">
                                            @if(!empty($item->configuration['texts']) || !empty($item->configuration['text']))
                                                <button type="button" @click="showText = !showText; showMotiv = false" class="inline-flex items-center gap-1 text-[10px] uppercase font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded transition cursor-pointer">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                    Text
                                                </button>
                                            @endif
                                            @php
                                                $logoCount = count(array_filter((array)($item->configuration['logos'] ?? []), fn($i) => is_string($i) && trim($i) !== ''));
                                                $fileCount = count(array_filter((array)($item->configuration['files'] ?? []), fn($i) => is_string($i) && trim($i) !== ''));
                                                if(is_string($item->configuration['logo_storage_path'] ?? null) && trim($item->configuration['logo_storage_path']) !== '') $logoCount++;
                                                $totalMotivs = max($logoCount, $fileCount);
                                            @endphp
                                            @if($totalMotivs > 0)
                                                <button type="button" @click="showMotiv = !showMotiv; showText = false" class="inline-flex items-center gap-1 text-[10px] uppercase font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded transition cursor-pointer">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                    Motiv ({{ $totalMotivs }})
                                                </button>
                                            @endif
                                        </div>

                                        <div x-show="showText" x-collapse x-cloak class="mb-2 bg-gray-50 rounded-xl p-3 border border-gray-100 text-xs text-gray-600 text-left break-words">
                                            @php $conf = $item->configuration; @endphp
                                            @if(!empty($conf['texts']))
                                                @foreach($conf['texts'] as $idx => $t)
                                                    @if(!empty($t['text']))
                                                        <div class="mb-2"><strong>{{ count($conf['texts']) > 1 ? 'Text '.($idx+1).' (Vorderseite)' : 'Gravur (Vorderseite)' }}:</strong> <br>"{!! nl2br(e($t['text'])) !!}" <br><span class="text-[10px] text-gray-400">Schrift: {{ $t['font'] ?? 'Standard' }}</span></div>
                                                    @endif
                                                @endforeach
                                            @elseif(!empty($conf['text']))
                                                <div class="mb-2"><strong>Gravur:</strong> <br>"{!! nl2br(e($conf['text'])) !!}" <br><span class="text-[10px] text-gray-400">Schrift: {{ $conf['font'] ?? 'Standard' }}</span></div>
                                            @endif

                                            @if(!empty($conf['texts_back']))
                                                <div class="mt-2 pt-2 border-t border-gray-200"></div>
                                                @foreach($conf['texts_back'] as $idx => $t)
                                                    @if(!empty($t['text']))
                                                        <div class="mb-2"><strong>{{ count($conf['texts_back']) > 1 ? 'Text '.($idx+1).' (Rückseite)' : 'Gravur (Rückseite)' }}:</strong> <br>"{!! nl2br(e($t['text'])) !!}" <br><span class="text-[10px] text-gray-400">Schrift: {{ $t['font'] ?? 'Standard' }}</span></div>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </div>

                                        <div x-show="showMotiv" x-collapse x-cloak class="mb-2 bg-gray-50 rounded-xl p-3 border border-gray-100 text-xs text-gray-600 text-left">
                                            @php $conf = $item->configuration; @endphp
                                            @if(!empty($conf['files']))
                                                @php $validFiles = array_filter((array)$conf['files'], fn($i) => is_string($i) && trim($i) !== ''); @endphp
                                                @if(count($validFiles) > 0)
                                                    <strong class="block mb-1">Hinterlegte Bilder: {{ count($validFiles) }} Datei(en)</strong>
                                                    <div class="flex gap-2 flex-wrap">
                                                        @foreach($validFiles as $file)
                                                            @if(in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp']))
                                                                <a href="{{ asset('storage/'.$file) }}" @click.prevent="$dispatch('open-image-modal', '{{ asset('storage/'.$file) }}')" class="w-12 h-12 rounded border border-gray-200 overflow-hidden block hover:border-primary transition cursor-pointer">
                                                                    <img src="{{ asset('storage/'.$file) }}" class="w-full h-full object-cover">
                                                                </a>
                                                            @else
                                                                <a href="{{ asset('storage/'.$file) }}" target="_blank" class="w-12 h-12 rounded border border-gray-200 flex items-center justify-center text-[10px] text-gray-400 bg-white hover:border-primary hover:text-primary transition">PDF</a>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div> </div>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900">{{ number_format($item->unit_price / 100, 2, ',', '.') }} €</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-100">
                <x-shop.cost-summary :totals="$totals" :country="$country" :showTitle="false" />
            </div>

            <div class="space-y-4 bg-gray-50 p-4 rounded-xl">
                <div class="flex items-start">
                    <div class="flex h-5 items-center">
                        <input id="terms" wire:model.live="terms_accepted" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="terms" class="font-medium text-gray-700 cursor-pointer select-none">
                            Ich habe die <a href="/agb" target="_blank" class="text-primary underline hover:text-primary-dark">AGB</a> gelesen und akzeptiere diese. <span class="text-red-500">*</span>
                        </label>
                    </div>
                </div>
                <div class="flex items-start">
                    <div class="flex h-5 items-center">
                        <input id="privacy" wire:model.live="privacy_accepted" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="privacy" class="font-medium text-gray-700 cursor-pointer select-none">
                            Ich habe die <a href="/datenschutz" target="_blank" class="text-primary underline hover:text-primary-dark">Datenschutzerklärung</a> zur Kenntnis genommen. <span class="text-red-500">*</span>
                        </label>
                    </div>
                </div>
                @if($errors->has('terms_accepted') || $errors->has('privacy_accepted'))
                    <div class="text-red-500 text-xs mt-2 font-bold">Bitte stimme den rechtlichen Bedingungen zu.</div>
                @endif
            </div>

            <div class="mt-8">
                <button id="submit-button"
                        type="submit"
                        x-bind:disabled="!terms || !privacy"
                        class="w-full rounded-full border border-transparent bg-gray-900 py-4 px-4 text-base font-bold text-white shadow-lg shadow-gray-900/20 hover:bg-black focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed disabled:shadow-none transition-all transform enabled:hover:-translate-y-1">
                    <span id="button-text">Zahlungspflichtig bestellen</span>
                    <div id="spinner" class="hidden flex items-center justify-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Verarbeite...
                    </div>
                </button>
                <p class="text-xs text-gray-400 text-center mt-4">Durch Klicken auf den Button schließt du einen zahlungspflichtigen Kaufvertrag ab.</p>
            </div>


            <div class="flex justify-center items-center py-6">
                <div class="relative w-48 h-48 sm:w-64 sm:h-64">
                    <img x-show="funkiState === 'normal'"
                         x-transition:enter="transition ease-out duration-500"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         src="{{ asset('shop/projekt/funki/checkout/funki_unhappy.webp') }}"
                         class="absolute inset-0 w-full h-full object-contain"
                         alt="Funki wartet">
                    <img x-show="funkiState === 'party'"
                         x-transition:enter="transition ease-out duration-500"
                         x-transition:enter-start="opacity-0 scale-105"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         src="{{ asset('shop/projekt/funki/checkout/funki_happy.webp') }}"
                         class="absolute inset-0 w-full h-full object-contain drop-shadow-[0_0_15px_rgba(255,215,0,0.5)]"
                         alt="Funki feiert">
                </div>
            </div>
        </div>
    </div>
</div>

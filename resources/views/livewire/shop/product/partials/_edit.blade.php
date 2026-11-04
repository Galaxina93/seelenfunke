<div class="w-full" x-data="{ showPreview: false }" wire:key="product-edit-wrapper-{{ $product->id ?? 'new' }}">
    <div class="hidden xl:col-span-7 xl:col-span-12"></div>

    @php
        $editReadiness = $product->getReadinessReport();
        $stepLabels = [
            1 => 'Basis & Lieferant',
            2 => 'Medien',
            3 => $type === 'physical' ? 'Verpackung & Details' : 'Details',
        ];
        if($type === 'physical') {
            $stepLabels[4] = 'Konfigurator & Vorlagen';
        }
    @endphp

    <div class="sticky top-0 z-40 bg-white/90 dark:bg-gray-900/80 backdrop-blur-xl border-b border-gray-200 dark:border-gray-800 px-4 sm:px-8 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between dark:shadow-2xl gap-4 transition-colors duration-300">

        <div class="flex items-center gap-4 sm:gap-6 w-full sm:w-auto">
            <button wire:click="backToList" class="group p-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 dark:hover:border-gray-600 rounded-xl text-gray-600 hover:text-gray-900 dark:text-gray-500 dark:hover:text-white transition-all dark:shadow-inner shrink-0 cursor-pointer" title="Zurück">
                <svg class="w-5 h-5 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </button>
            <div class="min-w-0">
                <h2 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white leading-tight tracking-wide truncate">{{ $name ?: 'Neues Produkt' }}</h2>
                <p class="text-[9px] sm:text-[10px] text-[var(--theme-color)] font-black uppercase tracking-[0.2em] mt-1 flex items-center gap-2 dark:drop-shadow-[0_0_8px_currentColor]">
                    @if($type === 'physical') Physisches Produkt
                    @elseif($type === 'digital') Digitales Produkt
                    @elseif($type === 'service') Dienstleistung
                    @endif
                    <span class="w-1 h-1 rounded-full bg-[var(--theme-color)]"></span> Schritt {{ $currentStep }} von {{ $totalSteps }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3 sm:gap-5 w-full sm:w-auto justify-end">

            {{-- KOMPAKTER ERSTELL-STATUS (GRÜNER HAKEN / ROTES X MIT HOVER-INFO LINKS NEBEN SHOP VORSCHAU) --}}
            <div x-data="{ showReadinessHover: false }"
                 @mouseenter="showReadinessHover = true"
                 @mouseleave="showReadinessHover = false"
                 class="relative inline-flex items-center shrink-0">
                <button type="button"
                        @click="showReadinessHover = !showReadinessHover"
                        class="w-9 h-9 rounded-xl border flex items-center justify-center transition-all cursor-pointer shadow-2xs {{ $editReadiness['is_ready']
                            ? 'bg-emerald-50 dark:bg-emerald-500/15 border-emerald-200 dark:border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-500/25'
                            : 'bg-red-50 dark:bg-red-500/15 border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/25' }}"
                        aria-label="Produkt-Erstellstatus">
                    @if($editReadiness['is_ready'])
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @else
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    @endif
                </button>

                <div x-show="showReadinessHover"
                     x-cloak
                     x-transition.opacity.duration.150ms
                     class="absolute right-0 sm:left-0 top-full mt-2.5 w-80 sm:w-96 p-4 rounded-2xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 shadow-2xl z-50 text-left">
                    <div class="flex items-center justify-between gap-2 pb-2.5 mb-2.5 border-b border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $editReadiness['is_ready'] ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                            <span class="text-xs font-black uppercase tracking-wider text-gray-900 dark:text-white">
                                {{ $editReadiness['score'] }}&nbsp;% Bereit ({{ $editReadiness['passed_count'] }}/{{ $editReadiness['total_count'] }})
                            </span>
                        </div>
                        <span class="text-[10px] font-bold {{ $editReadiness['is_ready'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $editReadiness['is_ready'] ? 'Vollständig' : count($editReadiness['missing']) . ' offen' }}
                        </span>
                    </div>

                    @if($editReadiness['is_ready'])
                        <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                            Alle Angaben (Basis, Lieferant, Verpackung/LUCID &amp; Vorlagen) vollständig hinterlegt.
                        </p>
                    @else
                        <p class="text-[11px] font-bold text-gray-500 dark:text-gray-400 mb-2">
                            Klicke auf einen offenen Punkt, um direkt hinzuspringen:
                        </p>
                        <div class="space-y-1.5 max-h-60 overflow-y-auto pr-1">
                            @foreach($editReadiness['missing'] as $miss)
                                <button type="button"
                                        wire:click="openProductInStep('{{ $product->id }}', {{ $miss['step'] }}, {{ $miss['sub_step'] ?? 1 }})"
                                        @click="showReadinessHover = false"
                                        class="w-full text-left px-3 py-2 rounded-xl bg-red-50/70 dark:bg-red-950/25 border border-red-200/80 dark:border-red-900/50 hover:border-[var(--theme-color)] transition-all cursor-pointer flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="text-xs font-bold text-red-700 dark:text-red-300 block truncate">✕ {{ $miss['short_missing'] }}</span>
                                        <span class="text-[10px] text-gray-500 dark:text-gray-400 block truncate">{{ $miss['detail'] }}</span>
                                    </div>
                                    <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-800 shrink-0">
                                        Schritt {{ $miss['step'] }}{{ !empty($miss['sub_step']) ? '.' . $miss['sub_step'] : '' }} &rarr;
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if($currentStep !== 4)
                <div class="hidden lg:flex items-center gap-3 bg-gray-100 dark:bg-gray-950 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-800 dark:shadow-inner shrink-0">
                    <span class="text-[10px] font-black uppercase tracking-widest transition-colors" :class="showPreview ? 'text-[var(--theme-color)] dark:drop-shadow-[0_0_5px_currentColor]' : 'text-gray-500'">Shop Vorschau</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" x-model="showPreview" class="sr-only peer">
                        <div class="w-10 h-5 bg-gray-300 dark:bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white dark:after:bg-gray-400 after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[var(--theme-color)] peer-checked:after:bg-white dark:peer-checked:after:bg-gray-900 border border-gray-300 dark:border-gray-700 shadow-inner"></div>
                    </label>
                </div>
            @endif

            <span wire:loading wire:target="save" class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] animate-pulse shrink-0">Speichere...</span>
            @if(session()->has('success'))
                <span class="text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400 animate-pulse dark:drop-shadow-[0_0_8px_currentColor] shrink-0">{{ session('success') }}</span>
            @endif
            <button wire:click="save" class="text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-gray-900 dark:text-gray-500 dark:hover:text-white border-b border-gray-300 dark:border-gray-600 hover:border-gray-900 dark:hover:border-white pb-0.5 transition-colors shrink-0 cursor-pointer">Speichern</button>
        </div>
    </div>

    <div class="max-w-[1800px] mx-auto p-4 sm:p-6 lg:p-10 font-sans antialiased text-gray-700 dark:text-gray-300">
        <div class="flex flex-col lg:flex-row gap-6 lg:gap-10 items-start w-full">

            <div class="w-full transition-all duration-500 space-y-6 lg:space-y-8 {{ $currentStep === 4 ? 'lg:!w-full' : '' }}"
                 :class="showPreview ? 'lg:w-7/12' : 'lg:w-full'">

                <div class="flex items-start gap-2 sm:gap-4 mb-2 overflow-x-auto no-scrollbar pb-2">
                    @foreach($stepLabels as $step => $label)
                        @php
                            $stepChecks = $editReadiness['by_step'][$step] ?? [];
                            $stepMissingCount = count(array_filter($stepChecks, fn($c) => !$c['passed']));
                            $stepAllPassed = count($stepChecks) > 0 && $stepMissingCount === 0;
                        @endphp
                        <div wire:click="goToStep({{ $step }})"
                             class="flex-1 min-w-[110px] flex flex-col gap-2.5 group transition-all duration-300 {{ ($step <= $product->completion_step + 1) ? 'cursor-pointer' : 'cursor-not-allowed opacity-40' }}"
                             @if($step > $product->completion_step + 1) title="Schritt noch nicht verfügbar" @endif >
                            <div class="flex items-center justify-between gap-1.5 h-8">
                                <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest transition-colors duration-300 {{ $currentStep >= $step ? 'text-[var(--theme-color)] dark:drop-shadow-[0_0_8px_currentColor]' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-400' }}">
                                    {{ $step }}. {{ $label }}
                                </span>
                                @if($stepAllPassed)
                                    <span class="px-1.5 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 text-[9px] font-black shrink-0" title="Alle Angaben in diesem Schritt vollständig">✓</span>
                                @elseif($stepMissingCount > 0)
                                    <span class="px-1.5 py-0.5 rounded-md bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[9px] font-bold shrink-0" title="{{ $stepMissingCount }} Angabe(n) in diesem Schritt noch offen">{{ $stepMissingCount }} offen</span>
                                @endif
                            </div>
                            <div class="h-1.5 w-full rounded-full transition-all duration-500 overflow-hidden bg-gray-200 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 dark:shadow-inner">
                                <div class="h-full w-full transition-transform duration-500 origin-left {{ $currentStep >= $step ? 'bg-[var(--theme-color)] dark:shadow-[0_0_10px_rgba(197,160,89,0.8)] scale-x-100' : 'bg-transparent scale-x-0' }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="animate-fade-in-up w-full">
                    @include('livewire.shop.product.partials.edit-partials._edit_step_1')
                    @include('livewire.shop.product.partials.edit-partials._edit_step_2')
                    @include('livewire.shop.product.partials.edit-partials._edit_step_3')

                    @if($type === 'physical')
                        <div x-show="$wire.currentStep === 4">
                            @include('livewire.shop.product.partials.edit-partials._edit_step_4')
                        </div>
                    @endif
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-center pt-8 border-t border-gray-200 dark:border-gray-800 mt-8 gap-4 sm:gap-0">
                    <button @if($currentStep === 1 && $step1SubStep <= 1) disabled @else wire:click="prevStep" @endif class="w-full sm:w-auto px-5 sm:px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-gray-900 dark:text-gray-500 dark:hover:text-white bg-gray-100 hover:bg-gray-200 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 dark:hover:bg-gray-800 disabled:opacity-30 disabled:cursor-not-allowed transition-all dark:shadow-inner cursor-pointer">
                        &larr; Zurück
                    </button>

                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                        <button wire:click="save" class="w-full sm:w-auto flex justify-center items-center gap-2 px-5 sm:px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white bg-white hover:bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-500 dark:hover:bg-gray-800 transition-all dark:shadow-inner cursor-pointer">
                            <svg wire:loading wire:target="save" class="animate-spin h-3.5 w-3.5 text-gray-700 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            <span wire:loading.remove wire:target="save">Zwischenspeichern</span>
                            <span wire:loading wire:target="save">Speichert...</span>
                        </button>

                        @php
                            $allowedStep3 = $this->getStep3AllowedSubSteps();
                            $step3Idx = array_search($step3SubStep, $allowedStep3, true);
                            $hasMoreStep1SubSteps = ($currentStep === 1 && $step1SubStep < 5);
                            $hasMoreStep3SubSteps = ($currentStep === 3 && $step3Idx !== false && $step3Idx < count($allowedStep3) - 1);
                            $hasMoreStep4SubSteps = ($currentStep === 4 && $step4SubStep < 4);
                            $hasMoreSubSteps = $hasMoreStep1SubSteps || $hasMoreStep3SubSteps || $hasMoreStep4SubSteps;
                            $canAdvanceButton = $hasMoreSubSteps ? $this->canProceedSubStep() : $canProceed;
                            $isLastStep = ($type === 'physical' && $currentStep === 4 && !$hasMoreStep4SubSteps) || ($type !== 'physical' && $currentStep === 3 && !$hasMoreStep3SubSteps);
                        @endphp

                        @if($isLastStep)
                            <button wire:click="finish" @if(!$canProceed) disabled @endif class="w-full sm:w-auto bg-[var(--theme-color)] text-white dark:text-gray-900 px-6 sm:px-10 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest dark:shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:bg-black/90 dark:hover:bg-white hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none transition-all cursor-pointer">
                                Fertigstellen & Veröffentlichen
                            </button>
                        @else
                            <button wire:click="{{ $hasMoreSubSteps ? 'nextSubStep' : 'nextStep' }}" @if(!$canAdvanceButton) disabled @endif class="w-full sm:w-auto px-6 sm:px-8 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-sm dark:shadow-lg transition-all transform cursor-pointer {{ $canAdvanceButton ? 'bg-gray-900 dark:bg-gray-800 text-white border border-gray-800 dark:border-gray-600 hover:bg-gray-800 dark:hover:bg-gray-700 hover:-translate-y-0.5' : 'bg-gray-100 dark:bg-gray-950 text-gray-400 dark:text-gray-600 border border-gray-200 dark:border-gray-800 cursor-not-allowed' }}">
                                @if($hasMoreStep1SubSteps)
                                    Nächster Unter-Schritt ({{ $step1SubStep + 1 }}/5) &rarr;
                                @elseif($hasMoreStep3SubSteps)
                                    Nächster Unter-Schritt ({{ ($step3Idx ?: 0) + 2 }}/{{ count($allowedStep3) }}) &rarr;
                                @elseif($hasMoreStep4SubSteps)
                                    Nächster Unter-Schritt ({{ $step4SubStep + 1 }}/4) &rarr;
                                @else
                                    Weiter zu Schritt {{ $currentStep + 1 }} &rarr;
                                @endif
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- REALITÄTSGETREUE SHOP VORSCHAU (ANALOG ZU /produkt/{slug}) --}}
            @if($currentStep !== 4)
                <div class="w-full lg:w-5/12 relative transition-all duration-500"
                     x-show="showPreview"
                     x-transition.opacity.duration.300ms
                     style="display: none;">

                    @php
                        $autoSeasonalPreview = filter_var($configSettings['auto_seasonal_main_image'] ?? ($product->isPersonalizable() && $product->isPhysical()), FILTER_VALIDATE_BOOLEAN);
                        $previewGallery = [];
                        $primaryMeta = $product->resolveMainMediaMeta(null, $autoSeasonalPreview);
                        if (!empty($primaryMeta['url'])) {
                            $previewGallery[] = [
                                'type'            => $primaryMeta['type'] ?? 'image',
                                'url'             => $primaryMeta['url'],
                                'alt'             => $primaryMeta['label'] ?? ($name ?: $product->name),
                                'is_ai_generated' => (bool) ($primaryMeta['is_ai_generated'] ?? true),
                                'seasonal_badge'  => $primaryMeta['label'] ?? null,
                            ];
                        }
                        foreach ($product->media_gallery ?? [] as $m) {
                            if (is_array($m) && !empty($m['path'])) {
                                $url = \App\Models\Product\ProductTemplate::resolveImageUrl($m['path']);
                                $alreadyIn = collect($previewGallery)->contains(fn($g) => $g['url'] === $url);
                                if ($url && !$alreadyIn) {
                                    $previewGallery[] = [
                                        'type'            => $m['type'] ?? 'image',
                                        'url'             => $url,
                                        'alt'             => $m['alt'] ?? ($name ?: $product->name),
                                        'is_ai_generated' => filter_var($m['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN),
                                        'seasonal_badge'  => null,
                                    ];
                                }
                            }
                        }

                        $livePriceCents = max(0, (int) round(((float) str_replace(',', '.', (string) ($price_input ?: 0))) * 100));
                        $liveCompareCents = max(0, (int) round(((float) str_replace(',', '.', (string) ($compare_price_input ?: 0))) * 100));
                        $hasSale = $liveCompareCents > $livePriceCents && $livePriceCents > 0;

                        $isSmallBusiness = filter_var(shop_setting('is_small_business', false), FILTER_VALIDATE_BOOLEAN);
                        $freeThreshold   = (int) shop_setting('shipping_free_threshold', 5000);
                        $shippingCost    = (int) shop_setting('shipping_cost', 490);
                        $isFreeShipping  = ($type !== 'physical') || ($livePriceCents >= $freeThreshold);

                        $previewOriginLabel = $product->getOriginBadgeLabel();
                        $previewOriginMode  = $product->getOriginBadgeMode();
                        $previewRatingAvg   = round((float) ($product->average_rating ?? 5.0), 1);
                        $previewRatingCount = (int) ($product->review_count ?? 0);

                        $previewAddons = ($type === 'physical') ? $product->getEffectiveAddons(true) : [];
                        $previewSparks = \App\Services\Gamification\GameConfig::calculateSparksForCents($livePriceCents);
                        $validPreviewAttrs = collect($productAttributes ?? [])->filter(fn($v) => trim((string) $v) !== '')->all();
                    @endphp

                    <div class="sticky top-24 space-y-4">
                        <div class="flex items-center justify-between px-2">
                            <span class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Live Shop-Vorschau
                            </span>
                            @if(!empty($product->slug) && !str_starts_with($product->slug, 'draft-'))
                                <a href="{{ route('product.show', $product->slug) }}"
                                   target="_blank"
                                   class="text-[10px] font-bold text-[var(--theme-color)] hover:underline flex items-center gap-1">
                                    <span>Im Shop öffnen</span>
                                    <span>&nearr;</span>
                                </a>
                            @endif
                        </div>

                        <div class="bg-white text-gray-900 rounded-[2rem] shadow-xl border border-gray-200 overflow-hidden"
                             style="--theme-color: #C9A66B; --theme-color-10: #C9A66B1A; --theme-color-20: #C9A66B33; --theme-color-30: #C9A66B4D; --theme-color-50: #C9A66B80;"
                             x-data="{
                                activeIdx: 0,
                                descOpen: false,
                                propsOpen: false,
                                gpsrOpen: false,
                                selectedPreviewAddons: [],
                                togglePreviewAddon(id) {
                                    if (this.selectedPreviewAddons.includes(id)) {
                                        this.selectedPreviewAddons = this.selectedPreviewAddons.filter(x => x !== id);
                                    } else {
                                        this.selectedPreviewAddons.push(id);
                                    }
                                }
                             }">

                            <div class="p-5 sm:p-6 space-y-5 max-h-[calc(100vh-8rem)] overflow-y-auto custom-scrollbar">

                                {{-- Breadcrumb --}}
                                <nav class="flex text-xs font-medium text-gray-400">
                                    <span>Startseite</span>
                                    <span class="mx-1.5 text-gray-300">/</span>
                                    <span>Shop</span>
                                    <span class="mx-1.5 text-gray-300">/</span>
                                    <span class="text-gray-700 truncate">{{ $name ?: 'Produktname' }}</span>
                                </nav>

                                {{-- 1. Hauptbild & Galerie-Thumbnails (analog zu left.blade.php) --}}
                                <div class="space-y-3">
                                    <div class="aspect-square bg-gray-50 rounded-2xl overflow-hidden border border-gray-100 shadow-xs relative group">
                                        @if(!empty($previewGallery))
                                            @foreach($previewGallery as $gIdx => $gItem)
                                                <div x-show="activeIdx === {{ $gIdx }}" class="w-full h-full relative">
                                                    @if(($gItem['type'] ?? 'image') === 'video')
                                                        <video src="{{ $gItem['url'] }}" controls playsinline preload="metadata" class="w-full h-full object-cover"></video>
                                                    @else
                                                        <img src="{{ $gItem['url'] }}" alt="{{ $gItem['alt'] }}" class="w-full h-full object-cover">
                                                        @if(!empty($gItem['is_ai_generated']))
                                                            <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                                                  class="pointer-events-none select-none absolute bottom-2.5 right-2.5 z-20 backdrop-blur-xs rounded border border-white/10">
                                                                KI-generiert
                                                            </span>
                                                        @endif
                                                    @endif
                                                </div>
                                            @endforeach
                                        @else
                                            <div class="flex flex-col items-center justify-center h-full text-gray-300">
                                                <svg class="w-14 h-14 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="text-xs font-serif italic text-gray-400">Noch kein Produktbild</span>
                                            </div>
                                        @endif

                                        {{-- Shop Badges oben links --}}
                                        <div class="absolute top-3 left-3 flex flex-col items-start gap-1.5 z-10">
                                            @if($hasSale)
                                                <span class="bg-red-600 text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider shadow-xs">Sale</span>
                                            @endif
                                            @if(!$product->created_at || $product->created_at->diffInDays() < 30)
                                                <span class="bg-[var(--theme-color)] text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider shadow-xs">Neu</span>
                                            @endif
                                            @if($type === 'digital')
                                                <span class="bg-blue-600 text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider shadow-xs">Digital</span>
                                            @endif
                                        </div>
                                    </div>

                                    @if(count($previewGallery) > 1)
                                        <div class="flex gap-2 overflow-x-auto pb-1 no-scrollbar">
                                            @foreach($previewGallery as $gIdx => $gItem)
                                                <button type="button"
                                                        @click="activeIdx = {{ $gIdx }}"
                                                        class="w-14 h-14 rounded-lg overflow-hidden border-2 shrink-0 transition-all cursor-pointer relative"
                                                        :class="activeIdx === {{ $gIdx }} ? 'border-[var(--theme-color)] ring-2 ring-[var(--theme-color-20)]' : 'border-transparent hover:border-gray-300'">
                                                    @if(($gItem['type'] ?? 'image') === 'video')
                                                        <div class="w-full h-full bg-gray-900 flex items-center justify-center text-white text-[10px]">▶</div>
                                                    @else
                                                        <img src="{{ $gItem['url'] }}" class="w-full h-full object-cover">
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                {{-- 2. Titel, Wunschliste & Preis-Block (analog zu right.blade.php) --}}
                                <div>
                                    <div class="flex items-start justify-between gap-3 mb-2">
                                        <h1 class="text-2xl font-serif font-bold text-gray-900 leading-tight break-words">
                                            {{ $name ?: 'Produktname' }}
                                        </h1>
                                        <span class="shrink-0 w-9 h-9 rounded-xl border border-gray-200 text-gray-400 flex items-center justify-center shadow-2xs">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                            </svg>
                                        </span>
                                    </div>

                                    <div class="flex items-baseline gap-2.5 flex-wrap">
                                        <span class="text-2xl font-bold text-[var(--theme-color)]">
                                            {{ number_format($livePriceCents / 100, 2, ',', '.') }}&nbsp;€
                                        </span>
                                        @if($hasSale)
                                            <span class="text-base text-gray-400 line-through decoration-red-400">
                                                {{ number_format($liveCompareCents / 100, 2, ',', '.') }}&nbsp;€
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-red-500 text-white">
                                                {{ $configSettings['autopilot_badge_text'] ?? 'Angebot' }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex flex-col gap-0.5 mt-1 text-xs text-gray-500">
                                        <span>
                                            @if($isSmallBusiness)
                                                <span class="italic">Gemäß § 19 UStG wird keine Umsatzsteuer berechnet</span>
                                            @else
                                                {{ $product->tax_included ? 'inkl. MwSt.' : 'zzgl. MwSt.' }}
                                            @endif
                                        </span>
                                        @if($type === 'digital')
                                            <span class="text-blue-600 font-bold">Sofort-Download (Versandkostenfrei)</span>
                                        @elseif($type === 'service')
                                            <span class="text-orange-600 font-bold">Kein Versand (Dienstleistung)</span>
                                        @elseif($isFreeShipping)
                                            <span class="text-green-700 font-bold">Kostenloser Versand</span>
                                        @else
                                            <span>zzgl. {{ number_format($shippingCost / 100, 2, ',', '.') }}&nbsp;€ Versand <span class="text-gray-400">(frei ab {{ number_format($freeThreshold / 100, 2, ',', '.') }}&nbsp;€)</span></span>
                                        @endif
                                    </div>
                                </div>

                                {{-- 3. Bewertungen & Herkunfts-Siegel + Lieferstatus --}}
                                <div class="space-y-3">
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <div class="inline-flex items-center gap-1.5">
                                            <div class="flex items-center">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <svg class="w-3.5 h-3.5 {{ $i <= round($previewRatingAvg) ? 'text-amber-400' : 'text-gray-200' }}" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                                    </svg>
                                                @endfor
                                            </div>
                                            <span class="text-[11px] font-semibold text-gray-500 underline">
                                                ({{ $previewRatingCount }} {{ $previewRatingCount === 1 ? 'Bewertung' : 'Bewertungen' }})
                                            </span>
                                        </div>

                                        @if($previewOriginLabel)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $previewOriginMode === 'made_in_germany' ? 'bg-amber-50 text-amber-900 border-amber-200' : 'bg-stone-50 text-stone-800 border-stone-200' }}">
                                                <span class="inline-flex flex-col w-3 h-2 rounded-[1px] overflow-hidden shrink-0 border border-black/15">
                                                    <span class="h-1/3 w-full bg-black"></span>
                                                    <span class="h-1/3 w-full bg-[#DD0000]"></span>
                                                    <span class="h-1/3 w-full bg-[#FFCE00]"></span>
                                                </span>
                                                <span>{{ $previewOriginLabel }}</span>
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Lieferanzeige Pill --}}
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border {{ (!$track_quantity || $quantity > 0 || $continue_selling) ? 'bg-emerald-50/80 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                                        <span class="w-2 h-2 rounded-full {{ (!$track_quantity || $quantity > 0 || $continue_selling) ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                        @if(!$track_quantity || $quantity > 0 || $continue_selling)
                                            <span>Rohling auf Lager · Lieferzeit {{ $productAttributes['Lieferzeit'] ?? '3-5 Werktage' }}</span>
                                        @else
                                            <span>Momentan ausverkauft</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- 4. Konfigurator-Startauswahl / Schnell-Personalisierung / Sofortkauf --}}
                                @if($type === 'physical')
                                    @if(!$is_personalizable)
                                        <div class="p-4 rounded-2xl bg-emerald-50/70 border border-emerald-200 flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0 font-bold">✓</div>
                                            <div>
                                                <span class="text-xs font-bold text-gray-900 block">Bereit zum Kauf (Fertiges Produkt)</span>
                                                <span class="text-[11px] text-gray-600 block">Vorgefertigter Artikel ohne weitere Personalisierung.</span>
                                            </div>
                                        </div>
                                    @elseif(($configSettings['personalization_mode'] ?? 'studio') === 'custom_fields')
                                        @php
                                            $previewCustomFields = [];
                                            foreach (($configSettings['custom_fields'] ?? []) as $pCfIdx => $pCfRaw) {
                                                if (is_array($pCfRaw)) {
                                                    $normPcf = \App\Models\Product\Product::normalizeCustomFieldItem($pCfRaw, $pCfIdx);
                                                    if ($normPcf) {
                                                        $previewCustomFields[] = $normPcf;
                                                    }
                                                }
                                            }
                                        @endphp
                                        <div class="p-4 rounded-2xl bg-gray-50/90 border border-gray-200 space-y-3.5">
                                            <div class="flex items-center justify-between gap-2 border-b border-gray-200 pb-2.5">
                                                <span class="text-xs font-serif font-bold text-gray-900">Deine Wunsch-Personalisierung</span>
                                                <span class="text-[10px] font-bold text-[var(--theme-color)] uppercase tracking-wider">Lasergravur inklusive</span>
                                            </div>

                                            @if(count($previewCustomFields) === 0)
                                                <p class="text-xs text-amber-700 italic">Noch keine Eingabefelder in Schritt 1.1 angelegt.</p>
                                            @else
                                                <div class="space-y-3">
                                                    @foreach($previewCustomFields as $pField)
                                                        <div class="space-y-1" x-data="{ showFieldInfo: false }">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <div class="flex items-center gap-1.5 min-w-0">
                                                                    <label class="text-xs font-bold text-gray-800 truncate">
                                                                        {{ $pField['label'] }}
                                                                        @if($pField['required'])
                                                                            <span class="text-rose-500">*</span>
                                                                        @else
                                                                            <span class="text-[10px] font-normal text-gray-400">(optional)</span>
                                                                        @endif
                                                                    </label>
                                                                    @if(!empty($pField['info_text']))
                                                                        <button type="button"
                                                                                @click="showFieldInfo = !showFieldInfo"
                                                                                class="w-4 h-4 rounded-full bg-amber-100 text-amber-800 border border-amber-300 text-[10px] font-black inline-flex items-center justify-center shrink-0 cursor-pointer"
                                                                                title="{{ $pField['info_text'] }}">!</button>
                                                                    @endif
                                                                </div>
                                                                @if($pField['surcharge_cents'] > 0)
                                                                    <span class="text-[10px] font-bold text-[var(--theme-color)] bg-[var(--theme-color-10)] px-2 py-0.5 rounded-md shrink-0">
                                                                        + {{ number_format($pField['surcharge_cents'] / 100, 2, ',', '.') }}&nbsp;€
                                                                    </span>
                                                                @endif
                                                            </div>

                                                            @if(!empty($pField['info_text']))
                                                                <div x-show="showFieldInfo" x-cloak class="p-2 rounded-lg bg-amber-50 border border-amber-200 text-[11px] text-amber-900 leading-snug">
                                                                    {{ $pField['info_text'] }}
                                                                </div>
                                                            @endif

                                                            @if($pField['type'] === 'select')
                                                                <select class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-xs text-gray-700">
                                                                    @foreach($pField['options'] as $optVal)
                                                                        <option>{{ $optVal }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @elseif($pField['type'] === 'textarea')
                                                                <textarea rows="2" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-xs text-gray-700" placeholder="{{ $pField['placeholder'] ?: 'Deine Eingabe...' }}"></textarea>
                                                            @elseif($pField['type'] === 'date')
                                                                <input type="date" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-xs text-gray-700">
                                                            @else
                                                                <input type="text" maxlength="{{ $pField['max_length'] }}" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-xs text-gray-700" placeholder="{{ $pField['placeholder'] ?: 'Deine Eingabe...' }}">
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="p-4 rounded-2xl bg-gray-50/80 border border-gray-200 space-y-3">
                                            <span class="text-xs font-serif font-bold text-gray-900 block text-center">Wie möchtest du starten?</span>
                                            <div class="grid grid-cols-2 gap-2.5">
                                                <div class="bg-white border border-gray-200 p-3 rounded-2xl text-center">
                                                    <div class="w-8 h-8 mx-auto bg-[var(--theme-color-10)] rounded-full flex items-center justify-center text-[var(--theme-color)] mb-1.5 text-xs font-bold">✦</div>
                                                    <span class="text-xs font-bold text-gray-900 block">Vorlage nutzen</span>
                                                    <span class="text-[10px] text-gray-500 block mt-0.5">{{ $product->templates ? $product->templates->where('is_active', true)->count() : 0 }} Vorlagen aktiv</span>
                                                </div>
                                                <div class="bg-white border border-gray-200 p-3 rounded-2xl text-center">
                                                    <div class="w-8 h-8 mx-auto bg-gray-100 rounded-full flex items-center justify-center text-gray-600 mb-1.5 text-xs font-bold">✎</div>
                                                    <span class="text-xs font-bold text-gray-900 block">Selber gestalten</span>
                                                    <span class="text-[10px] text-gray-500 block mt-0.5">{{ $three_d_active ? '3D & 2D Studio' : '2D Studio' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                {{-- 5. Verpackung & Extras + Warenkorb-Footer (exakt wie in footer.blade.php) --}}
                                <div class="pt-3 border-t border-gray-200 space-y-3">
                                    @if($type === 'physical' && count($previewAddons) > 0)
                                        <div class="space-y-2">
                                            <span class="block text-[10px] font-black uppercase tracking-widest text-gray-500">
                                                🎁 Verpackung &amp; Extras (Optional hinzubuchen)
                                            </span>
                                            <div class="grid grid-cols-1 gap-2">
                                                @foreach($previewAddons as $addon)
                                                    @php
                                                        $addonPriceFmt = number_format(((int) $addon['price_cents']) / 100, 2, ',', '.');
                                                        $addonImg = $addon['image_url'] ?? \App\Models\Product\Product::resolveAddonImageUrl($addon);
                                                    @endphp
                                                    <div @click="togglePreviewAddon('{{ $addon['id'] }}')"
                                                         class="w-full text-left border rounded-xl p-2.5 transition-all flex items-center justify-between gap-3 cursor-pointer"
                                                         :class="selectedPreviewAddons.includes('{{ $addon['id'] }}') ? 'bg-amber-50/80 border-[var(--theme-color)] ring-1 ring-[var(--theme-color-30)]' : 'bg-white border-gray-200 hover:border-gray-300'">
                                                        <div class="flex items-center gap-3 min-w-0 flex-1">
                                                            <span class="w-4 h-4 rounded flex items-center justify-center border shrink-0"
                                                                  :class="selectedPreviewAddons.includes('{{ $addon['id'] }}') ? 'bg-[var(--theme-color)] border-[var(--theme-color)] text-gray-900' : 'border-gray-300 bg-white'">
                                                                <svg x-show="selectedPreviewAddons.includes('{{ $addon['id'] }}')" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                            </span>
                                                            @if($addonImg)
                                                                <img src="{{ $addonImg }}" alt="{{ $addon['name'] }}" class="w-11 h-11 rounded-lg object-cover border border-gray-200 shrink-0">
                                                            @endif
                                                            <div class="min-w-0 flex-1">
                                                                <span class="text-xs font-bold text-gray-800 block leading-tight">{{ $addon['name'] }}</span>
                                                                @if(!empty($addon['description']))
                                                                    <span x-show="selectedPreviewAddons.includes('{{ $addon['id'] }}')" x-cloak class="text-[10px] text-gray-500 block mt-0.5 leading-snug">{{ $addon['description'] }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <span class="font-bold text-[var(--theme-color)] bg-[var(--theme-color-10)] px-2 py-0.5 rounded-lg text-[11px] whitespace-nowrap shrink-0">
                                                            + {{ $addonPriceFmt }}&nbsp;€
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if($previewSparks > 0)
                                        <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl border bg-amber-50/90 border-amber-200/90 text-amber-900 text-[11px]">
                                            <span class="font-semibold">✨ Seelenfunken Treue-Bonus (100&nbsp;%):</span>
                                            <span class="font-mono font-black px-2 py-0.5 rounded-full bg-amber-200/70 text-amber-950">+{{ number_format($previewSparks, 0, ',', '.') }} ✨ Seelenfunken</span>
                                        </div>
                                    @endif

                                    <div class="flex items-center gap-2.5">
                                        <div class="w-24 h-11 rounded-xl bg-gray-100 px-3 flex items-center justify-between text-xs font-bold text-gray-700 shrink-0">
                                            <span class="text-[9px] uppercase text-gray-500">Menge</span>
                                            <span>1</span>
                                        </div>
                                        <div class="flex-1 h-11 rounded-xl bg-gray-900 text-white font-bold text-sm flex items-center overflow-hidden shadow-md">
                                            <span class="px-3.5 py-2 bg-black/25 border-r border-white/10 font-serif text-xs whitespace-nowrap">
                                                {{ number_format($livePriceCents / 100, 2, ',', '.') }}&nbsp;€
                                            </span>
                                            <span class="flex-1 text-center text-xs font-bold">In den Warenkorb</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- 6. Aufklappbare Produktbeschreibung, Eigenschaften & GPSR (analog zu left.blade.php) --}}
                                <div class="space-y-2.5 pt-2">
                                    @if(!empty(trim((string) $description)))
                                        <div class="bg-gray-50/70 rounded-2xl border border-gray-100 p-4">
                                            <button type="button" @click="descOpen = !descOpen" class="w-full flex items-center justify-between text-left cursor-pointer">
                                                <span class="text-xs font-bold uppercase tracking-wider text-gray-900">Produktbeschreibung</span>
                                                <span class="text-[11px] font-bold text-gray-500" x-text="descOpen ? 'Weniger' : 'Mehr lesen'">Mehr lesen</span>
                                            </button>
                                            <div class="mt-2 text-xs text-gray-600 leading-relaxed" :class="descOpen ? 'whitespace-pre-line' : 'line-clamp-3'">
                                                {{ $description }}
                                            </div>
                                        </div>
                                    @endif

                                    @if(!empty($validPreviewAttrs))
                                        <div class="bg-gray-50/70 rounded-2xl border border-gray-100 p-4">
                                            <button type="button" @click="propsOpen = !propsOpen" class="w-full flex items-center justify-between text-left cursor-pointer">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-bold uppercase tracking-wider text-gray-900">Eigenschaften</span>
                                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-white text-gray-500 border border-gray-200">{{ count($validPreviewAttrs) }} Details</span>
                                                </div>
                                                <span class="text-[11px] font-bold text-gray-500" x-text="propsOpen ? 'Ausblenden' : 'Anzeigen'">Anzeigen</span>
                                            </button>
                                            <div x-show="propsOpen" x-cloak class="mt-3 pt-3 border-t border-gray-200/60 grid grid-cols-2 gap-3 text-xs">
                                                @foreach($validPreviewAttrs as $k => $v)
                                                    <div>
                                                        <span class="text-gray-500 text-[10px] block">{{ $k }}</span>
                                                        <span class="font-bold text-gray-900">{{ $v }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if($type === 'physical')
                                        <div class="bg-gray-50/70 rounded-2xl border border-gray-100 p-4">
                                            <button type="button" @click="gpsrOpen = !gpsrOpen" class="w-full flex items-center justify-between text-left cursor-pointer">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-bold uppercase tracking-wider text-gray-900">Produktsicherheit &amp; Hersteller</span>
                                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-white text-gray-500 border border-gray-200">GPSR</span>
                                                </div>
                                                <span class="text-[11px] font-bold text-gray-500" x-text="gpsrOpen ? 'Ausblenden' : 'Anzeigen'">Anzeigen</span>
                                            </button>
                                            <div x-show="gpsrOpen" x-cloak class="mt-3 pt-3 border-t border-gray-200/60 text-[11px] text-gray-600 leading-relaxed">
                                                <strong>{{ shop_setting('company_name', 'Mein Seelenfunke') }}</strong> · Modell-/Artikel-Nr.: <span class="font-mono">{{ $sku ?: ('MSF-' . $product->id) }}</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

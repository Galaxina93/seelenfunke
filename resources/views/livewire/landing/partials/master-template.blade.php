@php
    $product = $product ?? $landingPage->product;
    $heroImageMeta = $heroImageMeta ?? $product->resolveMainImageMeta();
    $seasonalTemplate = $seasonalTemplate ?? ($product->isAutoSeasonalMainImageEnabled() ? $product->resolveSeasonalTemplate() : null);
    $excludeForDetail = !empty($heroImageMeta['path']) ? [$heroImageMeta['path']] : [];
    $detailImageMeta = $detailImageMeta ?? ($product->resolveMainImageMeta(null, false, $excludeForDetail) ?: $heroImageMeta);
    $dynamicProfile = $dynamicProfile ?? \App\Models\Marketing\MarketingLandingPage::buildDynamicProfile($product, $heroImageMeta, $seasonalTemplate);
    $activeTheme = $activeTheme ?? app(\App\Services\ConfiguratorService::class)->getCurrentActiveTheme();
    $primaryCtaUrl = $primaryCtaUrl ?? route('product.show', $product->slug);
    $approvedReviews = $approvedReviews ?? collect();
    $crossSell = $crossSell ?? $product->resolveCrossSellProducts(4);
    $relatedProducts = $crossSell['products'] ?? collect();

    if (empty($heroGallery)) {
        $heroGallery = [];
        if (!empty($heroImageMeta['url'])) {
            $heroGallery[] = [
                'type'            => 'image',
                'url'             => $heroImageMeta['url'],
                'path'            => $heroImageMeta['path'] ?? null,
                'alt'             => $heroImageMeta['label'] ?? $product->name,
                'label'           => $seasonalTemplate ? ($seasonalTemplate->holiday ?: $seasonalTemplate->name) : ($heroImageMeta['label'] ?? null),
                'is_ai_generated' => (bool) ($heroImageMeta['is_ai_generated'] ?? false),
                'is_seasonal'     => $seasonalTemplate !== null,
            ];
        }
    }

    $isSmallBusiness    = filter_var(shop_setting('is_small_business', false), FILTER_VALIDATE_BOOLEAN);
    $freeThreshold      = (int) shop_setting('shipping_free_threshold', 5000);
    $shippingCost       = (int) shop_setting('shipping_cost', 490);
    $companyName        = $dynamicProfile['company_name'] ?? (trim((string) shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'))) ?: 'Mein Seelenfunke');
    $companyCity        = $dynamicProfile['company_city'] ?? (trim((string) shop_setting('company_city', 'Gifhorn')) ?: 'Gifhorn');
    $companyCountryName = $dynamicProfile['company_country_name'] ?? 'Deutschland';

    $isVoucher  = $product->slug === 'geschenkgutschein';
    $isDigital  = $product->type === 'digital' && !$isVoucher;
    $isService  = $product->type === 'service';
    $isPhysical = $product->type === 'physical' && !$isVoucher;
    $isFreeShipping = !$isPhysical || ($product->price >= $freeThreshold);

    $ratingAvg = round((float) ($product->approved_reviews_avg ?? $product->average_rating ?? 0), 1);
    $ratingCount = (int) ($product->approved_reviews_count ?? $product->review_count ?? 0);

    // Bereinige den sichtbaren Haupttitel von SEO-Suffixen wie "| Mein Seelenfunke"
    $rawTitle = trim((string) ($landingPage->title ?: $product->name));
    $escapedCompany = preg_quote($companyName, '/');
    $displayTitle = trim((string) preg_replace('/\s*[\|–-]\s*(?:' . $escapedCompany . '|Mein[- ]Seelenfunke|Personalisiertes|Massives|Weizenglas).*$/iu', '', $rawTitle)) ?: $product->name;

    // Kurzer, einzeiliger CTA-Text (verhindert Zeilenumbruch im Button)
    $rawCta = trim((string) ($landingPage->cta_text ?: ''));
    $ctaButtonText = ($rawCta !== '' && mb_strlen($rawCta) <= 28)
        ? $rawCta
        : ($isVoucher ? 'Jetzt Gutschein gestalten' : ($isDigital ? 'Jetzt Download ansehen' : ($isService ? 'Jetzt Service anfragen' : ($product->isPersonalizable() ? 'Jetzt Unikat gestalten' : 'Jetzt im Shop ansehen'))));

    $cleanSalesCopy = \App\Models\Marketing\MarketingLandingPage::sanitizeLegalCopy(
        (string) ($landingPage->sales_copy ?: $product->description)
    );
    $validAttributes = $dynamicProfile['valid_attributes'] ?? \App\Models\Marketing\MarketingLandingPage::extractEssentialAttributes($product);
    $collectionBannerUrl = $dynamicProfile['collection_banner_url'] ?? \App\Models\Marketing\MarketingLandingPage::resolveCollectionBannerUrl();
    $ctaBannerUrl = $dynamicProfile['cta_banner_url'] ?? asset('shop/frontend/banners/cta_wertschaetzung.webp');
@endphp

{{--
    SECTION 1: HERO
    Fokus: Dynamisches Saison-/Produktbild, interaktive Galerie, rechtssichere Preis-/Angebotslogik (§ 11 PAngV),
    dynamische Shop-Lieferzeit (`SystemDeliveryDisplay` / `DeliverySetting`), Staffelpreise & einzeilige CTAs.
--}}
<section class="relative bg-white pt-28 pb-16 lg:pt-36 lg:pb-28 overflow-hidden" aria-label="Produktvorstellung">
    <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-b from-amber-50/30 via-gray-50/40 to-white pointer-events-none" aria-hidden="true"></div>
    <div class="absolute -top-24 -right-24 w-96 h-96 bg-primary/10 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-start">

            {{-- Linke Seite: Text, Preis, Lieferzeit, Staffelpreise & CTA (7 Spalten) --}}
            <div class="order-2 lg:order-1 lg:col-span-7 text-center lg:text-left">

                {{-- Kategorien & Sternebewertung --}}
                <div class="flex flex-wrap items-center justify-center lg:justify-start gap-3 mb-4">
                    @if($product->categories && $product->categories->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-1.5">
                            @foreach($product->categories->take(3) as $cat)
                                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 bg-gray-100 px-2.5 py-1 rounded-md">
                                    {{ $cat->name }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if($ratingCount > 0)
                        <a href="#kundenbewertungen" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-800 hover:text-primary transition-colors bg-amber-50/80 border border-amber-200/60 px-3 py-1 rounded-full">
                            <span class="flex items-center text-amber-400">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-3.5 h-3.5 {{ $i <= round($ratingAvg) ? 'text-amber-400' : 'text-gray-300' }}" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                    </svg>
                                @endfor
                            </span>
                            <span>{{ number_format($ratingAvg, 1, ',', '.') }}</span>
                            <span class="text-gray-500 font-medium">({{ $ratingCount }} {{ $ratingCount === 1 ? 'Bewertung' : 'Bewertungen' }})</span>
                        </a>
                    @endif
                </div>

                {{-- Headline --}}
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-serif font-bold text-gray-900 leading-[1.1] mb-5">
                    {{ $displayTitle }}
                </h1>

                {{-- Subline / Hook --}}
                <p class="text-lg sm:text-xl text-gray-600 mb-8 leading-relaxed font-light max-w-2xl mx-auto lg:mx-0">
                    {{ $landingPage->headline ?: ($product->short_description ?? "Das persönliche Unikat aus unserer Manufaktur in {$companyCity}.") }}
                </p>

                {{-- Rechtssicherer Preis-, Steuer-, Versand-, Lieferzeit- & Staffelpreis-Block --}}
                <div class="bg-gray-50/90 border border-gray-200/80 rounded-2xl p-5 sm:p-6 mb-8 max-w-2xl mx-auto lg:mx-0 text-left shadow-xs">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-baseline gap-3 flex-wrap">
                                @if($isVoucher)
                                    <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Dein Wunschbetrag</span>
                                    <span class="text-3xl font-serif font-bold text-gray-900">ab 5,00 €</span>
                                    <span class="text-xs font-semibold text-gray-500">(frei wählbar von 5,00 € bis 1.000,00 €)</span>
                                @else
                                    <span class="text-3xl font-serif font-bold text-gray-900">
                                        {{ number_format($product->price / 100, 2, ',', '.') }} €
                                    </span>
                                    @if($product->compare_at_price > $product->price)
                                        @php
                                            $heroDiscountPercent = (int) round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100);
                                        @endphp
                                        <span class="text-lg text-gray-400 line-through decoration-red-400">
                                            {{ number_format($product->compare_at_price / 100, 2, ',', '.') }} €
                                        </span>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-red-500 text-white shadow-sm self-center">
                                            <span>{{ $product->configurator_settings['autopilot_badge_text'] ?? 'Angebot' }}</span>
                                            <span>• -{{ max(1, $heroDiscountPercent) }}%</span>
                                        </span>
                                    @endif
                                @endif
                            </div>

                            @if(!$isVoucher && $product->compare_at_price > $product->price)
                                <p class="text-[11px] text-gray-400 mt-1">
                                    Niedrigster Gesamtpreis der letzten 30 Tage vor der Preisermäßigung: {{ number_format($product->compare_at_price / 100, 2, ',', '.') }} €
                                </p>
                            @endif

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-xs text-gray-500">
                                <span>
                                    @if($isSmallBusiness)
                                        Gemäß § 19 UStG wird keine Umsatzsteuer berechnet
                                    @else
                                        {{ $product->tax_included ? 'inkl. MwSt.' : 'zzgl. MwSt.' }}
                                    @endif
                                </span>
                                <span class="text-gray-300">•</span>
                                @if($isVoucher)
                                    <span class="font-semibold text-emerald-700">E-Mail-Gutschein versandkostenfrei (Postkarte optional)</span>
                                @elseif($isDigital)
                                    <span class="font-semibold text-blue-600">Sofort-Download (versandkostenfrei)</span>
                                @elseif($isService)
                                    <span class="font-semibold text-orange-600">Kein Versand erforderlich (Dienstleistung)</span>
                                @elseif($isFreeShipping)
                                    <span class="font-semibold text-emerald-700">Kostenloser Versand ({{ $companyCountryName }})</span>
                                @else
                                    <span>
                                        zzgl. {{ number_format($shippingCost / 100, 2, ',', '.') }} € Versand
                                        <span class="text-gray-400">(frei ab {{ number_format($freeThreshold / 100, 2, ',', '.') }} €)</span>
                                        <a href="{{ route('versand') }}" target="_blank" class="underline hover:text-gray-800 ml-0.5">Details</a>
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if($isDigital || $isVoucher)
                            <div class="shrink-0">
                                <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold whitespace-nowrap">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    Sofort verfügbar
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- Dynamische Shop-Lieferzeit & Verfügbarkeits-Logik (inkl. Urlaubs-/Krankheitsmodus & Ampel) --}}
                    @if($isPhysical || $isService)
                        <div class="mt-4 pt-3.5 border-t border-gray-200/80 [&>div>div]:!mb-0">
                            <livewire:shop.system.system-delivery-display :product="$product" />
                        </div>
                    @endif

                    {{-- Staffelpreise / Mengenrabatt --}}
                    @if($product->tierPrices && $product->tierPrices->isNotEmpty())
                        <div class="mt-4 pt-3.5 border-t border-gray-200/80 flex flex-wrap items-center gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mr-1">Mengenrabatt:</span>
                            @foreach($product->tierPrices->sortBy('qty') as $tier)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white border border-primary/30 text-xs font-bold text-gray-800 shadow-2xs">
                                    <span>Ab {{ $tier->qty }} Stk.</span>
                                    <span class="text-primary-dark font-black">-{{ rtrim(rtrim(number_format((float) $tier->percent, 2, ',', '.'), '0'), ',') }}%</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Einzeilige CTA Buttons (kein Umbruch!) --}}
                <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start mb-10">
                    <a href="{{ $primaryCtaUrl }}"
                       title="{{ $product->name }} ansehen"
                       class="group inline-flex justify-center items-center px-8 py-4 bg-primary text-white text-base sm:text-lg font-bold rounded-xl shadow-lg shadow-primary/20 hover:bg-primary-dark hover:shadow-primary/40 transition-colors duration-300 whitespace-nowrap">
                        <span>{{ $ctaButtonText }}</span>
                        <svg class="ml-2.5 w-5 h-5 shrink-0 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>

                    <a href="#herzensprojekt"
                       title="Mehr über unser Herzensprojekt erfahren"
                       class="inline-flex justify-center items-center px-7 py-4 bg-white border border-gray-200 text-base sm:text-lg font-bold rounded-xl text-gray-700 hover:text-primary-dark hover:border-primary/50 transition-colors shadow-xs gap-2 whitespace-nowrap">
                        <span>❤️</span>
                        <span>Unser Herzensprojekt</span>
                    </a>
                </div>

                {{-- 3 Produkt-spezifische Mini-Trust-Kacheln (mit dynamischem Ort & Shop-Lieferzeit) --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-2xl mx-auto lg:mx-0">
                    @foreach($dynamicProfile['hero_mini_badges'] as $mini)
                        <div class="flex items-center gap-3 bg-white border border-gray-100 rounded-xl p-3.5 shadow-2xs text-left">
                            <div class="w-9 h-9 rounded-lg bg-primary/10 text-primary-dark flex items-center justify-center shrink-0 font-bold">
                                @if(($mini['icon'] ?? '') === 'cube')
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
                                @elseif(($mini['icon'] ?? '') === 'truck')
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                                @elseif(($mini['icon'] ?? '') === 'download')
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                @else
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-gray-900 truncate">{{ $mini['title'] }}</div>
                                <div class="text-[11px] text-gray-500 truncate">{{ $mini['subtitle'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Rechte Seite: Dynamisches Saison-/Produktbild + Interaktive Mini-Galerie (5 Spalten) --}}
            <div class="order-1 lg:order-2 lg:col-span-5 relative"
                 x-data="{
                    activeUrl: @js($heroGallery[0]['url'] ?? ($heroImageMeta['url'] ?? '')),
                    activeType: @js($heroGallery[0]['type'] ?? 'image'),
                    activeAlt: @js($heroGallery[0]['alt'] ?? $product->name),
                    activeIsAi: @js((bool) ($heroGallery[0]['is_ai_generated'] ?? ($heroImageMeta['is_ai_generated'] ?? false)))
                 }">

                <div class="relative w-full aspect-square rounded-3xl overflow-hidden shadow-2xl shadow-gray-200/70 border border-gray-100 bg-white p-2 sm:p-3 group">
                    <div class="relative w-full h-full rounded-2xl overflow-hidden bg-gray-50">
                        {{-- Typ-Badges oben links --}}
                        <div class="absolute top-3.5 left-3.5 z-30 flex flex-col items-start gap-2 pointer-events-none">
                            @if($isDigital)
                                <span class="bg-blue-600/95 backdrop-blur text-white text-[10px] font-black px-3.5 py-1.5 rounded-full uppercase tracking-widest shadow-md">
                                    Digital
                                </span>
                            @elseif($isService)
                                <span class="bg-orange-500/95 backdrop-blur text-white text-[10px] font-black px-3.5 py-1.5 rounded-full uppercase tracking-widest shadow-md">
                                    Service
                                </span>
                            @endif
                        </div>

                        {{-- Hauptbild / Video --}}
                        @if(!empty($heroGallery) || !empty($heroImageMeta['url']))
                            <a href="{{ $primaryCtaUrl }}" class="block w-full h-full">
                                <img x-show="activeType === 'image'"
                                     :src="activeUrl"
                                     :alt="activeAlt"
                                     :data-is-ai-generated="activeIsAi ? 'true' : null"
                                     loading="eager"
                                     class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                            </a>

                            <video x-show="activeType === 'video'"
                                   x-cloak
                                   :src="activeUrl"
                                   controls
                                   playsinline
                                   class="w-full h-full object-cover">
                            </video>

                            {{-- EU AI Act Kennzeichnung (KI-generiert) --}}
                            <span x-show="activeType === 'image' && activeIsAi"
                                  x-cloak
                                  class="pointer-events-none select-none absolute bottom-3 right-3 z-20 bg-black/55 backdrop-blur-sm text-white/90 text-[9px] font-semibold tracking-wider px-2 py-0.5 rounded-md border border-white/15 shadow-sm">
                                KI-generiert
                            </span>
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Interaktive Thumbnail-Leiste (wenn mehrere Produktbilder vorhanden sind) --}}
                @if(count($heroGallery) > 1)
                    <div class="flex items-center gap-3 mt-4 overflow-x-auto pb-1">
                        @foreach($heroGallery as $item)
                            <button type="button"
                                    @click="
                                        activeUrl = @js($item['url']);
                                        activeType = @js($item['type'] ?? 'image');
                                        activeAlt = @js($item['alt'] ?? $product->name);
                                        activeIsAi = @js((bool) ($item['is_ai_generated'] ?? false));
                                    "
                                    class="relative w-16 h-16 sm:w-20 sm:h-20 rounded-2xl overflow-hidden border-2 transition-all shrink-0 cursor-pointer"
                                    :class="activeUrl === @js($item['url']) ? 'border-primary ring-2 ring-primary/25 scale-[1.02]' : 'border-gray-200 opacity-75 hover:opacity-100'">
                                @if(($item['type'] ?? 'image') === 'video')
                                    <div class="w-full h-full bg-gray-900 flex items-center justify-center text-white">
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" /></svg>
                                    </div>
                                @else
                                    <img src="{{ $item['url'] }}"
                                         alt="{{ $item['alt'] ?? $product->name }}"
                                         @if(!empty($item['is_ai_generated'])) data-is-ai-generated="true" @endif
                                         class="w-full h-full object-cover">
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</section>

{{--
    SECTION 2: DYNAMISCHE HIGHLIGHTS (Mit demselben saisonalen Hintergrund wie unter "Unsere Kollektion"!)
--}}
<section id="details" class="py-24 bg-stone-950 border-y border-stone-800 relative overflow-hidden" aria-labelledby="details-heading">
    @if(!empty($collectionBannerUrl))
        <img src="{{ $collectionBannerUrl }}"
             alt=""
             loading="lazy"
             decoding="async"
             class="absolute inset-0 w-full h-full object-cover object-center pointer-events-none select-none">
        <div class="absolute inset-0 bg-gradient-to-b from-stone-950/50 via-stone-950/30 to-stone-950/60 pointer-events-none"></div>
    @endif

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="text-[var(--theme-color)] font-bold uppercase tracking-[0.2em] text-xs mb-3 block drop-shadow">
                Handveredelt in {{ $companyCity }}
            </span>
            <h2 id="details-heading" class="text-3xl sm:text-4xl lg:text-5xl font-serif font-bold text-white mb-4 drop-shadow-lg">
                Warum {{ $product->name }}?
            </h2>
            <p class="text-gray-200 text-base sm:text-lg font-light leading-relaxed drop-shadow">
                {{ $dynamicProfile['highlights_subtitle'] }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach($dynamicProfile['highlights'] as $idx => $highlight)
                <article class="bg-white/95 backdrop-blur-md p-8 sm:p-10 rounded-3xl shadow-xl border border-white/20 hover:border-primary/50 transition-colors duration-300 group">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform
                        {{ $idx === 0 ? 'bg-amber-50 text-amber-600' : ($idx === 1 ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600') }}"
                         aria-hidden="true">
                        @if(($highlight['icon'] ?? '') === 'cube')
                            🎨
                        @elseif(($highlight['icon'] ?? '') === 'download')
                            🚀
                        @elseif(($highlight['icon'] ?? '') === 'mail')
                            ✉️
                        @elseif(($highlight['icon'] ?? '') === 'device')
                            💻
                        @elseif(($highlight['icon'] ?? '') === 'shield')
                            🛡️
                        @else
                            ✨
                        @endif
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">{{ $highlight['title'] }}</h3>
                    <p class="text-gray-600 leading-relaxed text-sm">{{ $highlight['text'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{--
    SECTION 3: DETAILS, STORY & EINGEKLAPPTE KERNEIGENSCHAFTEN
--}}
<section class="py-24 bg-white overflow-hidden" aria-labelledby="specs-heading">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-start">

            {{-- Linke Seite: Sauber gerahmtes quadratisches Detailbild (ohne störende Overlay-Box) --}}
            <div class="lg:col-span-5">
                @if(!empty($detailImageMeta['url']))
                    <div class="relative aspect-square rounded-3xl overflow-hidden shadow-xl border border-gray-100 bg-white p-2 sm:p-3">
                        <div class="relative w-full h-full rounded-2xl overflow-hidden bg-gray-50">
                            <img src="{{ $detailImageMeta['url'] }}"
                                 alt="{{ $product->name }} Detailansicht"
                                 loading="lazy"
                                 @if(!empty($detailImageMeta['is_ai_generated'])) data-is-ai-generated="true" @endif
                                 class="w-full h-full object-cover">

                            @if(!empty($detailImageMeta['is_ai_generated']))
                                <span class="pointer-events-none select-none absolute bottom-3 right-3 z-20 bg-black/55 backdrop-blur-sm text-white/90 text-[9px] font-semibold tracking-wider px-2 py-0.5 rounded-md border border-white/15 shadow-sm">
                                    KI-generiert
                                </span>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="aspect-square rounded-3xl shadow-xl w-full bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-400">
                        {{ $product->name }}
                    </div>
                @endif
            </div>

            {{-- Rechte Seite: Verkaufstext, Checkliste & standardmäßig eingeklappte Kerneigenschaften --}}
            <div class="lg:col-span-7">
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-primary-dark block mb-2">
                    {{ $dynamicProfile['story_kicker'] }}
                </span>
                <h2 id="specs-heading" class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 mb-6 leading-tight">
                    {{ $dynamicProfile['story_headline'] }}
                </h2>

                <div class="text-gray-600 mb-8 text-base sm:text-lg font-light leading-relaxed space-y-4">
                    {!! nl2br(e($cleanSalesCopy)) !!}
                </div>

                {{-- 4 Produkt-spezifische Checklisten-Punkte --}}
                @if(!empty($dynamicProfile['story_points']))
                    <ul class="space-y-3 mb-8">
                        @foreach($dynamicProfile['story_points'] as $point)
                            <li class="flex items-start gap-3 text-sm font-medium text-gray-800">
                                <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold">✓</span>
                                <span>{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Wichtigste Produktdetails & Eigenschaften (Standardmäßig eingeklappt!) --}}
                @if(!empty($validAttributes))
                    <div x-data="{ specsOpen: false }"
                         class="bg-gray-50/80 rounded-2xl border border-gray-200/80 overflow-hidden transition-all">
                        <button type="button"
                                @click="specsOpen = !specsOpen"
                                class="w-full p-5 sm:px-6 flex items-center justify-between gap-4 text-left hover:bg-gray-100/70 transition-colors cursor-pointer group">
                            <div class="flex items-center gap-2.5">
                                <svg class="w-5 h-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <span class="text-sm font-bold uppercase text-gray-900 tracking-wider group-hover:text-primary-dark transition-colors">
                                    Produktdetails & Eigenschaften
                                </span>
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-full bg-white text-gray-500 border border-gray-200">
                                    {{ count($validAttributes) }} {{ count($validAttributes) === 1 ? 'Merkmal' : 'Merkmale' }}
                                </span>
                            </div>
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 group-hover:text-primary-dark shrink-0 transition-colors">
                                <span x-text="specsOpen ? 'Ausblenden' : 'Anzeigen'">Anzeigen</span>
                                <svg class="w-4 h-4 transition-transform duration-300" :class="specsOpen ? 'rotate-180 text-primary-dark' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </button>

                        <div x-show="specsOpen"
                             x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="px-6 pb-6 pt-2 border-t border-gray-200/70">
                            <dl class="divide-y divide-gray-200/60">
                                @foreach($validAttributes as $key => $value)
                                    <div class="grid grid-cols-3 gap-4 py-3 text-sm">
                                        <dt class="text-gray-500 font-medium">{{ $key }}</dt>
                                        <dd class="col-span-2 font-bold text-gray-900 break-words">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                @endif

                <div class="mt-6 flex items-start gap-3">
                    <svg class="w-5 h-5 text-gray-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-xs text-gray-400 italic">
                        @if($isVoucher)
                            Hinweis: Der Gutschein-Code ist nach Zahlungseingang für das gesamte Sortiment im Shop einlösbar.
                        @elseif($isDigital)
                            Hinweis: Nach erfolgreicher Zahlung steht dir der Download direkt in deinem Kundenkonto bereit.
                        @elseif($isService)
                            Hinweis: Nach deiner Anfrage setzen wir uns persönlich mit dir zur Termin- und Detailabstimmung in Verbindung.
                        @elseif($product->isPersonalizable())
                            Hinweis: Da die Gravur individuell nach deinen Angaben gefertigt wird, handelt es sich um eine kundenspezifische Einzelanfertigung (§ 312g Abs. 2 Nr. 1 BGB, Details in unseren <a href="{{ route('agb') }}#widerruf" target="_blank" class="underline hover:text-gray-600">AGB</a>).
                        @else
                            Hinweis: Jedes Exemplar wird vor dem Versand in unserer Manufaktur in {{ $companyCity }} geprüft und sicher verpackt.
                        @endif
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

{{--
    SECTION 4: DAS HERZENSPROJEKT / SOZIALER BEITRAG
    Authentisches Engagement für Kinder vor Ort
--}}
<section id="herzensprojekt" class="py-24 bg-gradient-to-br from-amber-50/60 via-white to-orange-50/40 relative overflow-hidden border-y border-amber-100/60" aria-labelledby="herzensprojekt-heading">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="max-w-3xl mx-auto text-center mb-16">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-800 text-xs font-bold uppercase tracking-widest mb-4">
                <span>❤️</span> Ein Funke, der Gutes tut
            </div>
            <h2 id="herzensprojekt-heading" class="text-3xl sm:text-4xl lg:text-5xl font-serif font-bold text-gray-900 mb-6 leading-tight">
                Freude schenken. <br><span class="text-primary italic font-serif">Gutes bewirken.</span>
            </h2>
            <p class="text-lg text-gray-600 font-light leading-relaxed">
                Wir bei {{ $companyName }} glauben daran, dass wahre Freude wächst, wenn man sie teilt. Mit jedem Kauf fließt ein fester Beitrag in unseren regionalen Herzens-Topf für Kindergärten und Aktionen für Kinder vor Ort in {{ $companyCity }} und Umgebung.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <div class="bg-white/80 backdrop-blur-sm p-8 rounded-2xl border border-amber-200/50 shadow-sm hover:shadow-md transition-all duration-300">
                <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-xl mb-4">
                    🧸
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Spielgeräte & Bastelbedarf</h3>
                <p class="text-sm text-gray-600 leading-relaxed">
                    Wir statten Kindergärten in unserer Region mit langlebigen Spiel- und Lernmaterialien sowie kreativen Bastelsets aus.
                </p>
            </div>

            <div class="bg-white/80 backdrop-blur-sm p-8 rounded-2xl border border-amber-200/50 shadow-sm hover:shadow-md transition-all duration-300">
                <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-xl mb-4">
                    📍
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Greifbar & Regional</h3>
                <p class="text-sm text-gray-600 leading-relaxed">
                    Keine anonymen weltweiten Fonds: Die Unterstützung landet direkt bei Kindern und Kitas vor unserer eigenen Haustür.
                </p>
            </div>

            <div class="bg-white/80 backdrop-blur-sm p-8 rounded-2xl border border-amber-200/50 shadow-sm hover:shadow-md transition-all duration-300">
                <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-xl mb-4">
                    ✨
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Transparente Aktionen</h3>
                <p class="text-sm text-gray-600 leading-relaxed">
                    Jedes Projekt wird mit echten Übergaben, Fotos und Berichten dokumentiert, damit du genau siehst, wo die Unterstützung ankommt.
                </p>
            </div>
        </div>

        <div class="text-center">
            <span class="inline-flex items-center gap-2 text-sm text-amber-900 font-medium bg-amber-100/60 px-5 py-2.5 rounded-full border border-amber-200/60">
                <span>🌟</span> Mit deiner Bestellung wird dieser Funke Wirklichkeit.
            </span>
        </div>
    </div>
</section>

{{--
    SECTION 5: KUNDENBEWERTUNGEN (Social Proof, falls freigegebene Bewertungen vorliegen)
--}}
@if($approvedReviews->isNotEmpty())
<section id="kundenbewertungen" class="py-24 bg-white border-b border-gray-100" aria-labelledby="reviews-heading">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-14">
            <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold mb-3">
                <span>★ {{ number_format($ratingAvg, 1, ',', '.') }} / 5,0</span>
                <span>• {{ $ratingCount }} verifizierte {{ $ratingCount === 1 ? 'Bewertung' : 'Bewertungen' }}</span>
            </div>
            <h2 id="reviews-heading" class="text-3xl sm:text-4xl font-serif font-bold text-gray-900">
                Das sagen unsere Kunden über {{ $product->name }}
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach($approvedReviews as $review)
                <article class="bg-gray-50/70 rounded-3xl p-8 border border-gray-100 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1 text-amber-400 mb-4">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-4 h-4 {{ $i <= (int) $review->rating ? 'text-amber-400' : 'text-gray-200' }}" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                </svg>
                            @endfor
                        </div>
                        @if($review->title)
                            <h3 class="font-bold text-gray-900 text-base mb-2">{{ $review->title }}</h3>
                        @endif
                        <p class="text-sm text-gray-600 leading-relaxed line-clamp-4">
                            „{{ $review->content }}“
                        </p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-gray-200/60 flex items-center justify-between text-xs text-gray-500">
                        <span class="font-bold text-gray-800">
                            {{ $review->customer?->first_name ?? 'Verifizierter Kunde' }}
                            @if(!empty($review->customer?->last_name))
                                {{ mb_substr($review->customer->last_name, 0, 1) }}.
                            @endif
                        </span>
                        <span class="text-emerald-700 font-semibold">✓ Verifizierter Kauf</span>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

@php
    $gallery = is_array($product->media_gallery) ? collect($product->media_gallery) : collect();
    $videoMedia = $gallery->firstWhere('type', 'video');
    $usedPaths = array_filter([
        $heroImageMeta['path'] ?? null,
        $detailImageMeta['path'] ?? null,
    ]);
    $extraImages = $gallery
        ->filter(fn($m) => is_array($m) && (!isset($m['type']) || $m['type'] === 'image') && !empty($m['path']))
        ->reject(fn($m) => in_array($m['path'], $usedPaths, true))
        ->values();
@endphp

@if($videoMedia && !empty($videoMedia['path']))
{{--
    SECTION 6: VIDEO HERO
--}}
<section class="bg-gray-950 py-24 relative overflow-hidden" aria-labelledby="video-heading">
    <div class="absolute inset-0 opacity-20 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-primary/30 via-gray-900 to-gray-950"></div>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center mb-16">
            <h2 id="video-heading" class="text-3xl sm:text-4xl font-serif font-bold text-white mb-4">Live in Action</h2>
            <p class="text-gray-400 text-lg font-light">Erlebe die Faszination von {{ $product->name }} aus jeder Perspektive.</p>
        </div>
        <div class="relative w-full aspect-video rounded-3xl overflow-hidden shadow-[0_0_60px_rgba(0,0,0,0.6)] border border-gray-800">
            <video
                src="{{ asset('storage/' . ltrim((string) $videoMedia['path'], '/')) }}"
                class="w-full h-full object-cover"
                controls
                muted
                autoplay
                loop
                playsinline
            >
                Ihr Browser unterstützt leider kein HTML5 Video.
            </video>
        </div>
    </div>
</section>
@endif

@if($extraImages->count() > 0)
{{--
    SECTION 6B: WEITERE GALERIE-EINBLICKE
--}}
<section class="py-24 bg-gray-50 border-b border-gray-100" aria-labelledby="gallery-heading">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 id="gallery-heading" class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 mb-4">Weitere Einblicke</h2>
            <p class="text-gray-500 font-light">Entdecke die Details von {{ $product->name }}.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($extraImages as $img)
                @php
                    $extraUrl = \App\Models\Product\ProductTemplate::resolveImageUrl($img['path']);
                    $extraIsAi = filter_var($img['is_ai_generated'] ?? false, FILTER_VALIDATE_BOOLEAN);
                @endphp
                @if($extraUrl)
                    <div class="relative group rounded-3xl overflow-hidden bg-white shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-100">
                        <img src="{{ $extraUrl }}"
                             alt="{{ $img['alt'] ?? ('Detailansicht ' . $product->name) }}"
                             loading="lazy"
                             @if($extraIsAi) data-is-ai-generated="true" @endif
                             class="w-full aspect-square object-cover transform group-hover:scale-105 transition-transform duration-700">
                        @if($extraIsAi)
                            <span class="pointer-events-none select-none absolute bottom-2.5 right-2.5 z-10 bg-black/55 backdrop-blur-sm text-white/90 text-[9px] font-semibold tracking-wider px-2 py-0.5 rounded border border-white/15">
                                KI-generiert
                            </span>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</section>
@endif

{{--
    SECTION 7: PASSENDE UNIKATE AUS UNSERER MANUFAKTUR (Cross-Selling)
--}}
@if($relatedProducts->isNotEmpty())
<section class="py-24 bg-white border-b border-gray-100" aria-labelledby="cross-sell-heading">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-12">
            <div>
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-primary-dark block mb-2">
                    Manufaktur-Kollektion
                </span>
                <h2 id="cross-sell-heading" class="text-2xl sm:text-3xl font-serif font-bold text-gray-900">
                    {{ $crossSell['title'] ?? 'Weitere Unikate aus unserer Manufaktur' }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $crossSell['subtitle'] ?? ("Mit derselben Sorgfalt in {$companyCity} für dich veredelt.") }}
                </p>
            </div>
            <a href="{{ route('shop') }}" class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-600 hover:text-primary-dark transition-colors shrink-0 group">
                <span>Alle Produkte im Shop</span>
                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($relatedProducts as $relProduct)
                @php
                    $relMeta = $relProduct->resolveMainImageMeta();
                    $relImgUrl = $relMeta['url'] ?? null;
                    $relIsAi = (bool) ($relMeta['is_ai_generated'] ?? false);
                    $relUrl = route('product.show', $relProduct->slug);
                @endphp
                <a href="{{ $relUrl }}" class="group flex flex-col bg-white rounded-3xl p-3 border border-gray-100 shadow-xs hover:shadow-xl hover:border-primary/30 transition-all duration-300">
                    <div class="aspect-square rounded-2xl overflow-hidden bg-gray-50 relative mb-4 border border-gray-100">
                        @if($relImgUrl)
                            <img src="{{ $relImgUrl }}"
                                 alt="{{ $relProduct->name }}"
                                 loading="lazy"
                                 @if($relIsAi) data-is-ai-generated="true" @endif
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                        @endif
                        @if($relImgUrl && $relIsAi)
                            <span class="pointer-events-none select-none absolute bottom-2 right-2 z-10 bg-black/55 backdrop-blur-sm text-white/90 text-[9px] font-semibold tracking-wider px-2 py-0.5 rounded border border-white/15">
                                KI-generiert
                            </span>
                        @endif
                        @if($relProduct->compare_at_price > $relProduct->price)
                            <span class="absolute top-2.5 left-2.5 bg-red-600 text-white text-[10px] font-black px-2.5 py-0.5 rounded-full">
                                -{{ max(1, (int) round((($relProduct->compare_at_price - $relProduct->price) / $relProduct->compare_at_price) * 100)) }}%
                            </span>
                        @endif
                    </div>
                    <div class="px-2 pb-2 flex-1 flex flex-col justify-between">
                        <h3 class="font-serif font-bold text-base text-gray-900 group-hover:text-primary-dark transition-colors line-clamp-2">
                            {{ $relProduct->name }}
                        </h3>
                        <div class="mt-3 pt-2.5 border-t border-gray-100 flex items-center justify-between">
                            <span class="font-bold text-sm text-gray-900">
                                {{ $relProduct->slug === 'geschenkgutschein' ? 'ab 5,00 €' : $relProduct->formatted_price }}
                            </span>
                            <span class="text-xs font-bold text-primary-dark group-hover:translate-x-0.5 transition-transform">
                                Entdecken →
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{--
    SECTION 8: PREIS & ABSCHLUSS-CTA ("Bereit für echte Wertschätzung?" mit warmem, goldenem Manufaktur-Hintergrund)
--}}
<section class="py-24 bg-stone-950 border-t border-stone-800 relative overflow-hidden" aria-labelledby="cta-heading">
    @if(!empty($ctaBannerUrl))
        <img src="{{ $ctaBannerUrl }}"
             alt=""
             loading="lazy"
             decoding="async"
             class="absolute inset-0 w-full h-full object-cover object-center pointer-events-none select-none">
        <div class="absolute inset-0 bg-gradient-to-b from-stone-950/50 via-stone-950/30 to-stone-950/60 pointer-events-none"></div>
    @endif

    <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
        <h2 id="cta-heading" class="text-3xl sm:text-5xl font-serif font-bold text-white mb-6 drop-shadow-lg">
            Bereit für echte Wertschätzung?
        </h2>
        <p class="text-xl text-stone-100 mb-12 max-w-2xl mx-auto font-light leading-relaxed drop-shadow">
            @if($isVoucher)
                Verschenke freie Auswahl aus unserer gesamten Manufaktur – digital per E-Mail oder als gedruckte Klappkarte.
            @elseif($isDigital)
                Erhalte jetzt {{ $product->name }} mit direktem Download nach deiner Bestellung.
            @elseif($isService)
                Frag jetzt unverbindlich an – wir beraten dich persönlich aus unserer Manufaktur in {{ $companyCity }}.
            @elseif($product->isPersonalizable())
                Egal ob Einzelstück für einen besonderen Anlass oder als persönliches Geschenk: Gestalte jetzt {{ $product->name }} online.
            @else
                Bestelle jetzt {{ $product->name }} direkt aus unserer Manufaktur in {{ $companyCity }}.
            @endif
        </p>

        <div class="flex flex-col sm:flex-row justify-center gap-5">
            <a href="{{ $primaryCtaUrl }}"
               title="{{ $product->name }} ansehen"
               class="inline-flex justify-center items-center px-10 py-5 border border-primary text-lg font-bold rounded-xl text-stone-950 bg-primary hover:bg-[#b8955a] transition-colors duration-200 shadow-sm whitespace-nowrap">
                {{ $ctaButtonText }}
            </a>
            <a href="#contact"
               title="Kontaktieren Sie uns für Fragen"
               class="inline-flex justify-center items-center px-10 py-5 border border-white/25 text-lg font-bold rounded-xl text-white bg-stone-900/50 hover:bg-stone-900/75 transition-colors duration-200 backdrop-blur-xs whitespace-nowrap">
                Frage stellen
            </a>
        </div>

        <p class="mt-10 text-sm text-amber-100/80 flex items-center justify-center gap-2 drop-shadow">
            <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ $dynamicProfile['cta_subtext'] }}</span>
        </p>
    </div>
</section>

{{-- Contact Section --}}
<div id="contact">
    @livewire('frontend.contact-form')
</div>

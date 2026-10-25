@php
    $activeProducts = \App\Models\Product\Product::with([
            'categories',
            'templates' => fn($q) => $q->where('is_active', true),
        ])
        ->where('status', 'active')
        ->get();

    $categoriesByName = \App\Models\Product\ProductCategory::all()->keyBy(fn($c) => mb_strtolower(trim($c->name)));

    $matchProducts = function (array $categoryNames, array $slugs = [], ?string $type = null, array $materialKeywords = []) use ($activeProducts) {
        $normCats = array_map(fn($n) => mb_strtolower(trim($n)), $categoryNames);

        return $activeProducts->filter(function ($product) use ($normCats, $slugs, $type, $materialKeywords) {
            if ($type !== null && $product->type === $type) {
                return true;
            }
            if (!empty($slugs) && in_array($product->slug, $slugs, true)) {
                return true;
            }
            if (!empty($normCats) && $product->categories->contains(fn($c) => in_array(mb_strtolower(trim($c->name)), $normCats, true))) {
                return true;
            }
            if (!empty($materialKeywords)) {
                $matAttr = mb_strtolower((string) ($product->attributes['Material'] ?? ''));
                $matConf = mb_strtolower((string) ($product->configurator_settings['material_type'] ?? ''));
                foreach ($materialKeywords as $kw) {
                    $kwLower = mb_strtolower($kw);
                    if (str_contains($matAttr, $kwLower) || str_contains($matConf, $kwLower)) {
                        return true;
                    }
                }
            }
            return false;
        });
    };

    $rawTiles = [
        [
            'key' => 'glas_kristall',
            'title' => 'Glas & Kristall',
            'subtitle' => 'PREMIUM GRAVUR',
            'image' => asset('shop/product/seelen-kristall_w.webp'),
            'alt' => 'K9-Kristallglas und Trinkgläser mit präziser Lasergravur',
            'description' => 'Unsere Königsdisziplin. Wir veredeln hochwertiges <strong>K9-Kristallglas & Kristallgläser</strong> mit präzisen Lasergravuren, die durch den Weiß-Effekt edel gefrostet wirken.',
            'features' => [
                'Individueller Text & Wunschmotiv',
                'Massives K9-Kristall & 360°-Glasgravur',
                'Inklusive edler Geschenkpräsentation',
            ],
            'category_names' => ['Glas & Kristall'],
            'material_keywords' => ['kristall', 'glass', 'glas'],
            'slugs' => ['seelen-kristall', 'weizenglas-personalisiert'],
            'type' => null,
            'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />',
        ],
        [
            'key' => 'metall_alu',
            'title' => 'Metall & Aluminium',
            'subtitle' => 'MODERNE ELEGANZ',
            'image' => asset('shop/product/seelen-anhaenger_s.webp'),
            'alt' => 'Lasergravur auf massivem Aluminium und Metall-Unikaten',
            'description' => 'Spürbare Wertigkeit aus eloxiertem <strong>Aluminium und edlen Metallen</strong>. Kontrastreiche, abriebfeste Lasergravuren auf Vorder- und Rückseite für den Alltag.',
            'features' => [
                'Beidseitige Präzisions-Lasergravur',
                'Handschmeichelnde, massive Haptik',
                'Unverwüstlich & dauerhaft abriebfest',
            ],
            'category_names' => ['Metall & Alu'],
            'material_keywords' => ['aluminium', 'metall', 'metal', 'edelstahl'],
            'slugs' => ['seelen-anhaenger'],
            'type' => null,
            'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />',
        ],
        [
            'key' => 'klein_fein',
            'title' => 'Schmuck & Accessoires',
            'subtitle' => 'KLEIN & FEIN',
            'image' => asset('shop/product/flaschenoeffner.webp'),
            'alt' => 'Personalisierte Schlüsselanhänger und Accessoires',
            'description' => 'Große Emotionen im handlichen Format. Edle <strong>Schlüsselanhänger, Glücksbringer und Accessoires</strong> als täglicher Begleiter oder persönliches Mitbringsel.',
            'features' => [
                'Perfekt als persönlicher Glücksbringer',
                'Feinste Detail- und Symbolgravur',
                'Ideal für Schlüsselbund & Alltag',
            ],
            'category_names' => ['Schmuck & Anhänger', 'Accessoires'],
            'material_keywords' => [],
            'slugs' => [],
            'type' => null,
            'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />',
        ],
        [
            'key' => 'geschenksets',
            'title' => 'Geschenksets & Gutscheine',
            'subtitle' => 'LIEBEVOLL VERPACKT',
            'image' => asset('shop/product/geschenkpapier.webp'),
            'alt' => 'Exklusive Geschenksets und Wertgutscheine von Mein Seelenfunke',
            'description' => 'Das Auspacken ist Teil des Erlebnisses. Entdecken Sie fertig abgestimmte <strong>Geschenksets und flexible Seelenfunken-Wertgutscheine</strong> für jeden Anlass.',
            'features' => [
                'Sofort verschenkfertig vorbereitet',
                'Gutscheine digital oder als edle Karte',
                'Für Geburtstage, Hochzeiten & Feste',
            ],
            'category_names' => ['Geschenksets'],
            'material_keywords' => [],
            'slugs' => ['geschenkgutschein'],
            'type' => null,
            'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H4.5a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />',
        ],
        [
            'key' => 'schiefer_natur',
            'title' => 'Schiefer & Natur',
            'subtitle' => 'RUSTIKALE UNIKATE',
            'image' => asset('shop/product/schiefer.webp'),
            'alt' => 'Personalisierte Schieferplatten und Naturmaterialien mit Lasergravur',
            'description' => 'Jedes Stück ein gewachsenes Natur-Unikat. Die charaktervolle Struktur von <strong>Schiefer und edlen Hölzern</strong> sorgt für faszinierende Kontraste bei der Gravur.',
            'features' => [
                'Wetterfest & charakterstarke Maserung',
                'Untersetzer, Gedenktafeln & Schilder',
                'Natürliche Bruchkanten-Optik',
            ],
            'category_names' => ['Schiefer & Natur', 'Schiefer', 'Holz & Natur'],
            'material_keywords' => ['schiefer', 'slate', 'holz', 'wood'],
            'slugs' => [],
            'type' => null,
            'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />',
        ],
        [
            'key' => 'deine_idee',
            'title' => 'Deine Idee & B2B',
            'subtitle' => 'SONDERANFERTIGUNG',
            'image' => asset('shop/product/individuell.webp'),
            'alt' => 'Sonderanfertigungen und individuelle Laser-Projekte',
            'description' => 'Sie planen ein besonderes Projekt, Vereins-Awards oder eine individuelle Serie? Wir prüfen die technische Machbarkeit und setzen Ihre Wunschgravur um.',
            'features' => [
                'Individuelle Serien & Vereins-Awards',
                'Persönliche Machbarkeitsprüfung',
                'Direkte Beratung aus der Manufaktur',
            ],
            'category_names' => ['Beratung', 'Express-Service', 'Sonderanfertigung'],
            'material_keywords' => [],
            'slugs' => [],
            'type' => 'service',
            'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />',
        ],
    ];

    $claimedProductIds = [];
    $usedImagePaths = [];

    $resolvedTiles = collect($rawTiles)->map(function (array $tile) use ($matchProducts, $categoriesByName, &$claimedProductIds, &$usedImagePaths) {
        $allMatched = $matchProducts(
            $tile['category_names'],
            $tile['slugs'],
            $tile['type'],
            $tile['material_keywords']
        );

        // Deduplizierung: Jedes Produkt vertritt unter "Veredelungen & Materialien" maximal eine Kachel,
        // damit ein einzelnes Produkt (z. B. in zwei Kategorien) nicht doppelt angezeigt wird.
        $matched = $allMatched->reject(fn($p) => in_array($p->id, $claimedProductIds, true))->values();

        foreach ($matched as $p) {
            $claimedProductIds[] = $p->id;
        }

        // Prüfe, welche davon real lieferbar / vorbestellbar sind
        $inStockProducts = $matched->filter(function ($p) {
            return !$p->track_quantity || $p->quantity > 0 || $p->continue_selling_when_out_of_stock;
        });

        $totalStockUnits = $inStockProducts->sum(fn($p) => $p->track_quantity ? max(0, (int) $p->quantity) : 50);
        $hasActiveProducts = $matched->isNotEmpty();
        $isAvailable = $inStockProducts->isNotEmpty();

        // Trennung zwischen echten Unikaten und Wertgutscheinen
        $unikateCount = $inStockProducts->filter(fn($p) => $p->slug !== 'geschenkgutschein')->count();
        $voucherCount = $inStockProducts->filter(fn($p) => $p->slug === 'geschenkgutschein')->count();

        // Niedrigster Preis (unter Berücksichtigung aktiver Angebote, exkl. 0-€-Gutschein-Basis)
        $pricedProducts = $inStockProducts->filter(fn($p) => (int) $p->price > 0);
        $minPriceCents = $pricedProducts->isNotEmpty() ? (int) $pricedProducts->min('price') : null;

        if ($unikateCount > 0 && $voucherCount > 0) {
            $stockBadgeText = $unikateCount . ' ' . ($unikateCount === 1 ? 'Unikat' : 'Unikate') . ' & Gutschein';
            if ($minPriceCents) {
                $stockBadgeText .= ' · ab ' . number_format($minPriceCents / 100, 2, ',', '.') . ' €';
            }
        } elseif ($voucherCount > 0) {
            $stockBadgeText = ($voucherCount === 1 ? 'Wertgutschein' : $voucherCount . ' Gutscheine') . ' · ab 10,00 €';
        } else {
            $stockBadgeText = $unikateCount . ' ' . ($unikateCount === 1 ? 'Unikat' : 'Unikate');
            if ($minPriceCents) {
                $stockBadgeText .= ' · ab ' . number_format($minPriceCents / 100, 2, ',', '.') . ' €';
            }
        }

        // Prüfe, ob in dieser Kachel gerade ein Feiertags-/Aktionsangebot aktiv ist
        $saleProduct = $inStockProducts->first(fn($p) => !empty($p->compare_at_price) && (int) $p->compare_at_price > (int) $p->price);
        $saleBadge = $saleProduct ? ($saleProduct->configurator_settings['autopilot_badge_text'] ?? 'Angebot aktiv') : null;

        // Ziel-URL im Shop (wenn nur 1 eigenständiges Produkt wie der Gutschein in der Kachel liegt, direkt oder gefiltert)
        $targetCategoryId = null;
        foreach ($tile['category_names'] as $catName) {
            $foundCat = $categoriesByName->get(mb_strtolower(trim($catName)));
            if ($foundCat) {
                $targetCategoryId = $foundCat->id;
                break;
            }
        }

        $shopUrl = ($matched->count() === 1 && $matched->first()->slug === 'geschenkgutschein')
            ? route('product.show', 'geschenkgutschein')
            : ($targetCategoryId
                ? route('shop', ['filterCategory' => $targetCategoryId])
                : ($matched->count() === 1 ? route('product.show', $matched->first()->slug) : route('shop')));

        // Dynamisches Produktbild (passend zu Jahreszeit / Feiertag) + KI-Kennzeichnung
        $tile['is_ai_generated'] = false;
        $productsForTile = $inStockProducts->isNotEmpty() ? $inStockProducts : $matched;
        if ($productsForTile->isNotEmpty()) {
            foreach ($productsForTile as $candidateProduct) {
                $meta = $candidateProduct->resolveMainImageMeta(null, true, $usedImagePaths);
                if (!empty($meta['url'])) {
                    $tile['image'] = $meta['url'];
                    $tile['alt'] = $candidateProduct->name . ' – ' . $tile['title'];
                    $tile['is_ai_generated'] = (bool) ($meta['is_ai_generated'] ?? false);
                    if (!empty($meta['path'])) {
                        $usedImagePaths[] = $meta['path'];
                    }
                    break;
                }
            }
        }

        $tile['matched_count'] = $matched->count();
        $tile['in_stock_count'] = $inStockProducts->count();
        $tile['total_stock_units'] = $totalStockUnits;
        $tile['has_active_products'] = $hasActiveProducts;
        $tile['is_available'] = $isAvailable;
        $tile['min_price_cents'] = $minPriceCents;
        $tile['stock_badge_text'] = $stockBadgeText;
        $tile['sale_badge'] = $saleBadge;
        $tile['shop_url'] = $shopUrl;

        return $tile;
    })->sortByDesc(fn($t) => ($t['is_available'] ? 100 : ($t['has_active_products'] ? 50 : 0)) + $t['in_stock_count'])->values();
@endphp

<section id="services" class="bg-white text-black py-24 px-6 lg:px-12" aria-labelledby="services-heading">
    <header class="text-center mb-16">
        <h2 id="services-heading" class="text-primary-dark font-serif font-bold text-3xl sm:text-4xl lg:text-5xl">
            Veredelungen & Materialien für Ihre Unikate
        </h2>
        <p class="mt-4 text-gray-600 text-base max-w-3xl mx-auto">
            Entdecken Sie unsere aktuell im Bestand verfügbaren <strong>Manufaktur-Materialien und Geschenk-Welten</strong> sowie kommende Erweiterungen, an denen wir in Gifhorn bereits arbeiten.
        </p>
    </header>

    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        @foreach($resolvedTiles as $tile)
            @if($tile['is_available'])
                {{-- =====================================================================
                     AKTIVE & VERFÜGBARE KACHEL (Dynamisch aus realem Bestand)
                ===================================================================== --}}
                <article class="bg-white rounded-2xl shadow-xl overflow-hidden transition-transform hover:scale-[1.03] duration-300 group ring-2 ring-primary ring-offset-4 relative z-10 flex flex-col">
                    <figure class="overflow-hidden relative">
                        {{-- Badges oben rechts & links --}}
                        <div class="absolute top-4 right-4 bg-primary text-gray-900 text-[11px] font-extrabold px-3 py-1 rounded-full z-20 shadow-md flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-700 animate-ping"></span>
                            <span>JETZT VERFÜGBAR</span>
                        </div>

                        @if($tile['sale_badge'])
                            <div class="absolute top-4 left-4 bg-red-600/95 backdrop-blur-sm text-white text-[10px] font-black px-2.5 py-1 rounded-full z-20 shadow-md uppercase tracking-wider">
                                {{ $tile['sale_badge'] }}
                            </div>
                        @endif

                        <img src="{{ $tile['image'] }}"
                             alt="{{ $tile['alt'] }}"
                             loading="lazy"
                             @if(!empty($tile['is_ai_generated'])) data-is-ai-generated="true" @endif
                             class="w-full h-56 object-cover transform group-hover:scale-110 transition-transform duration-500">

                        @if(!empty($tile['is_ai_generated']))
                            <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                  class="pointer-events-none select-none absolute bottom-2 left-2.5 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                KI-generiert
                            </span>
                        @endif
                    </figure>

                    <div class="bg-gradient-to-br from-primary to-primary-dark p-6 relative">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg absolute -top-8 right-6">
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-primary">
                                {!! $tile['icon_svg'] !!}
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-1">{{ $tile['title'] }}</h3>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-white/80 text-xs font-bold tracking-wider uppercase">{{ $tile['subtitle'] }}</span>
                            <span class="text-white/95 text-xs font-extrabold bg-black/20 px-2.5 py-0.5 rounded-full">
                                {{ $tile['stock_badge_text'] }}
                            </span>
                        </div>
                    </div>

                    <div class="p-6 flex-1 flex flex-col">
                        <p class="text-gray-600 mb-4 text-sm leading-relaxed">{!! $tile['description'] !!}</p>
                        <ul class="text-sm text-gray-500 space-y-2 mb-6" aria-label="Produktmerkmale {{ $tile['title'] }}">
                            @foreach($tile['features'] as $feature)
                                <li class="flex items-center gap-2">
                                    <span class="text-primary font-bold" aria-hidden="true">✔</span>
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-auto">
                            <a href="{{ $tile['shop_url'] }}"
                               title="Zur Kategorie {{ $tile['title'] }} im Shop"
                               class="block text-center w-full bg-primary text-gray-900 py-2.5 rounded-lg font-bold hover:bg-primary-dark hover:text-white transition-colors shadow-sm">
                                Jetzt im Shop entdecken →
                            </a>
                        </div>
                    </div>
                </article>
            @elseif($tile['has_active_products'])
                {{-- =====================================================================
                     PRODUKT EXISTIERT, ABER AKTUELL AUSVERKAUFT (Bestand = 0)
                ===================================================================== --}}
                <article class="bg-gray-50 rounded-2xl shadow-sm border border-amber-200 overflow-hidden opacity-80 relative group flex flex-col">
                    <div class="absolute top-4 right-4 z-20 bg-amber-600 text-white text-[11px] font-extrabold px-3 py-1 rounded-full shadow-md uppercase tracking-wider">
                        Aktuell vergriffen
                    </div>
                    <figure class="overflow-hidden">
                        <img src="{{ $tile['image'] }}"
                             alt="{{ $tile['alt'] }}"
                             loading="lazy"
                             class="w-full h-56 object-cover grayscale-[0.5]">
                    </figure>
                    <div class="bg-gray-700 p-6 relative">
                        <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4 shadow-sm absolute -top-8 right-6">
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-600">
                                {!! $tile['icon_svg'] !!}
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-1">{{ $tile['title'] }}</h3>
                        <span class="text-gray-300 text-xs font-bold tracking-wider uppercase">NACHPRODUKTION LÄUFT</span>
                    </div>
                    <div class="p-6 flex-1 flex flex-col">
                        <p class="text-gray-500 mb-4 text-sm">{!! $tile['description'] !!}</p>
                        <ul class="text-sm text-gray-400 space-y-1.5">
                            @foreach(array_slice($tile['features'], 0, 2) as $feature)
                                <li class="flex items-center gap-2">• {{ $feature }}</li>
                            @endforeach
                        </ul>
                    </div>
                </article>
            @else
                {{-- =====================================================================
                     NOCH KEIN AKTIVES PRODUKT IM SYSTEM -> AUTOMATISCH "DEMNÄCHST"
                ===================================================================== --}}
                <article class="bg-gray-50 rounded-2xl shadow-none border border-gray-200 overflow-hidden opacity-60 grayscale-[0.9] cursor-default relative group flex flex-col">
                    <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/20 backdrop-blur-[1px]">
                        <div class="bg-black/80 text-white px-6 py-2 rounded-full font-bold tracking-widest border border-white/20 shadow-xl transform -rotate-12">
                            DEMNÄCHST
                        </div>
                    </div>

                    <figure class="overflow-hidden">
                        <img src="{{ $tile['image'] }}"
                             alt="{{ $tile['alt'] }}"
                             loading="lazy"
                             class="w-full h-56 object-cover">
                    </figure>

                    <div class="bg-gray-600 p-6 relative">
                        <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4 shadow-sm absolute -top-8 right-6">
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-500">
                                {!! $tile['icon_svg'] !!}
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-200 mb-1">{{ $tile['title'] }}</h3>
                        <span class="text-gray-300/80 text-xs font-medium tracking-wider uppercase">{{ $tile['subtitle'] }}</span>
                    </div>

                    <div class="p-6 flex-1 flex flex-col">
                        <p class="text-gray-500 mb-4 text-sm">{!! $tile['description'] !!}</p>
                        <ul class="text-sm text-gray-400 space-y-1.5" aria-label="Produktmerkmale {{ $tile['title'] }}">
                            @foreach(array_slice($tile['features'], 0, 2) as $feature)
                                <li class="flex items-center gap-2">• {{ $feature }}</li>
                            @endforeach
                        </ul>
                    </div>
                </article>
            @endif
        @endforeach
    </div>
</section>

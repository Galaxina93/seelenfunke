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

<section id="services" class="bg-white text-black py-20 px-6 lg:px-12" aria-labelledby="services-heading">
    <style>
        .sf-card {
            transform: translate3d(0, 0, 0);
            transition:
                transform 0.75s cubic-bezier(0.22, 0.61, 0.36, 1),
                box-shadow 0.75s cubic-bezier(0.22, 0.61, 0.36, 1),
                border-color 0.75s cubic-bezier(0.22, 0.61, 0.36, 1);
            will-change: transform;
        }
        .sf-card.sf-is-active {
            transform: translate3d(0, -6px, 0);
            box-shadow: 0 22px 30px -8px rgba(0, 0, 0, 0.14), 0 8px 12px -6px rgba(0, 0, 0, 0.06);
        }
        .sf-card-img {
            transform: scale(1) translateZ(0);
            transition: transform 1.35s cubic-bezier(0.22, 0.61, 0.36, 1);
            will-change: transform;
        }
        .sf-card.sf-is-active .sf-card-img {
            transform: scale(1.045) translateZ(0);
        }
        .sf-card-sheen {
            position: absolute;
            inset: -100%;
            background: linear-gradient(
                135deg,
                rgba(255, 255, 255, 0) 40%,
                rgba(255, 251, 235, 0.05) 44%,
                rgba(255, 248, 225, 0.18) 47.5%,
                rgba(255, 246, 215, 0.38) 50%,
                rgba(255, 248, 225, 0.18) 52.5%,
                rgba(255, 251, 235, 0.05) 56%,
                rgba(255, 255, 255, 0) 60%
            );
            transform: translate3d(-32%, -32%, 0);
            transition: transform 1.45s cubic-bezier(0.22, 0.61, 0.36, 1);
            will-change: transform;
        }
        .sf-card.sf-is-active .sf-card-sheen {
            transform: translate3d(32%, 32%, 0);
        }
        @keyframes sfSparkleFloatA {
            0%, 100% { transform: translate3d(0, 0, 0) rotate(0deg); }
            33% { transform: translate3d(4px, -6px, 0) rotate(18deg); }
            66% { transform: translate3d(-3px, -2px, 0) rotate(-12deg); }
        }
        @keyframes sfSparkleFloatB {
            0%, 100% { transform: translate3d(0, 0, 0) rotate(0deg); }
            33% { transform: translate3d(-5px, -4px, 0) rotate(-20deg); }
            66% { transform: translate3d(3px, -7px, 0) rotate(14deg); }
        }
        .sf-card-sparkle {
            opacity: 0;
            transform: scale(0.35) translateZ(0);
            transition:
                opacity 0.75s cubic-bezier(0.22, 0.61, 0.36, 1) 0s,
                transform 0.85s cubic-bezier(0.22, 0.61, 0.36, 1) 0s;
            will-change: transform, opacity;
        }
        .sf-card.sf-is-active .sf-card-sparkle {
            opacity: 0.92;
            transform: scale(1) translateZ(0);
        }
        .sf-card.sf-is-active .sf-card-sparkle-1 { transition-delay: 0.04s; }
        .sf-card.sf-is-active .sf-card-sparkle-2 { transition-delay: 0.11s; }
        .sf-card.sf-is-active .sf-card-sparkle-3 { transition-delay: 0.18s; }
        .sf-card.sf-is-active .sf-card-sparkle-4 { transition-delay: 0.25s; }
        .sf-card.sf-is-active .sf-card-sparkle-5 { transition-delay: 0.32s; }
        .sf-card.sf-is-active .sf-card-sparkle-6 { transition-delay: 0.39s; }
        .sf-card-sparkle > svg { display: block; width: 100%; height: 100%; will-change: transform; }
        .sf-card-sparkle-1 > svg { animation: sfSparkleFloatA 3.4s ease-in-out infinite; }
        .sf-card-sparkle-2 > svg { animation: sfSparkleFloatB 4.0s ease-in-out infinite 0.4s; }
        .sf-card-sparkle-3 > svg { animation: sfSparkleFloatB 3.2s ease-in-out infinite 0.9s; }
        .sf-card-sparkle-4 > svg { animation: sfSparkleFloatA 3.8s ease-in-out infinite 1.3s; }
        .sf-card-sparkle-5 > svg { animation: sfSparkleFloatA 4.2s ease-in-out infinite 0.7s; }
        .sf-card-sparkle-6 > svg { animation: sfSparkleFloatB 3.6s ease-in-out infinite 1.6s; }
    </style>
    <script>
        window.sfRandomizeCardSparkles = window.sfRandomizeCardSparkles || function(cardEl) {
            const zones = [
                { top: [10, 28], left: [10, 35] },
                { top: [10, 28], left: [62, 86] },
                { top: [36, 60], left: [8, 28] },
                { top: [36, 60], left: [68, 88] },
                { top: [66, 86], left: [12, 40] },
                { top: [66, 86], left: [58, 86] },
            ];
            const rand = (min, max) => (min + Math.random() * (max - min)).toFixed(1) + '%';
            cardEl.querySelectorAll('.sf-card-sparkle').forEach((el, i) => {
                const z = zones[i % zones.length];
                el.style.top = rand(z.top[0], z.top[1]);
                el.style.left = rand(z.left[0], z.left[1]);
                el.style.right = 'auto';
                el.style.bottom = 'auto';
            });
        };
        window.sfCardHoverEnter = window.sfCardHoverEnter || function(cardEl) {
            clearTimeout(cardEl._sfLeaveTimer);
            clearTimeout(cardEl._sfCleanupTimer);
            if (!cardEl._sfBusy) {
                window.sfRandomizeCardSparkles(cardEl);
                void cardEl.offsetWidth;
            }
            cardEl._sfBusy = true;
            if (!cardEl.classList.contains('sf-is-active')) {
                cardEl._sfEnterTime = performance.now();
                cardEl.classList.add('sf-is-active');
            }
        };
        window.sfCardHoverLeave = window.sfCardHoverLeave || function(cardEl) {
            clearTimeout(cardEl._sfLeaveTimer);
            clearTimeout(cardEl._sfCleanupTimer);
            const minForward = 950;
            const elapsed = cardEl._sfEnterTime ? (performance.now() - cardEl._sfEnterTime) : minForward;
            const waitMs = Math.max(0, minForward - elapsed);
            cardEl._sfLeaveTimer = setTimeout(() => {
                cardEl.classList.remove('sf-is-active');
                cardEl._sfCleanupTimer = setTimeout(() => {
                    cardEl._sfBusy = false;
                }, 1350);
            }, waitMs);
        };
    </script>

    <header class="text-center mb-12">
        <h2 id="services-heading" class="text-primary-dark font-serif font-bold text-3xl sm:text-4xl lg:text-5xl">
            Veredelungen & Materialien für Ihre Unikate
        </h2>
        <p class="mt-4 text-gray-600 text-base max-w-3xl mx-auto">
            Entdecken Sie unsere aktuell im Bestand verfügbaren <strong>Manufaktur-Materialien und Geschenk-Welten</strong> sowie kommende Erweiterungen, an denen wir in Gifhorn bereits arbeiten.
        </p>
    </header>

    <div class="max-w-5xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($resolvedTiles as $tile)
            @if($tile['is_available'])
                {{-- =====================================================================
                     AKTIVE & VERFÜGBARE KACHEL (Dynamisch aus realem Bestand)
                ===================================================================== --}}
                <article onmouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter(this)"
                         onmouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave(this)"
                         class="sf-card bg-white rounded-2xl shadow-lg border-2 border-primary overflow-hidden group relative z-10 flex flex-col">
                    <figure class="overflow-hidden relative bg-gray-900" style="aspect-ratio: 10 / 9;">
                        @if($tile['sale_badge'])
                            <div class="absolute top-3 left-3 bg-red-600/95 backdrop-blur-sm text-white text-[9px] font-black px-2.5 py-1 rounded-full z-20 shadow-md uppercase tracking-wider">
                                {{ $tile['sale_badge'] }}
                            </div>
                        @endif

                        <img src="{{ $tile['image'] }}"
                             alt="{{ $tile['alt'] }}"
                             loading="lazy"
                             @if(!empty($tile['is_ai_generated'])) data-is-ai-generated="true" @endif
                             class="sf-card-img w-full h-full object-cover object-center block">

                        <div class="sf-card-sheen pointer-events-none z-10" aria-hidden="true"></div>
                        @for($sp = 1; $sp <= 6; $sp++)
                            <span class="sf-card-sparkle sf-card-sparkle-{{ $sp }} pointer-events-none absolute top-[20%] left-[20%] {{ $sp % 2 === 0 ? 'w-3.5 h-3.5 text-amber-300' : 'w-3 h-3 text-amber-200' }} drop-shadow-[0_0_6px_rgba(251,191,36,0.9)] z-10" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                            </span>
                        @endfor

                        @if(!empty($tile['is_ai_generated']))
                            <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                  class="pointer-events-none select-none absolute bottom-2 left-2.5 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                KI-generiert
                            </span>
                        @endif
                    </figure>

                    <div class="bg-gradient-to-br from-primary to-primary-dark px-5 py-4 relative">
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-md absolute -top-6 right-5">
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-primary">
                                {!! $tile['icon_svg'] !!}
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-1 pr-12">{{ $tile['title'] }}</h3>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-white/80 text-[11px] font-bold tracking-wider uppercase">{{ $tile['subtitle'] }}</span>
                            <span class="text-white/95 text-[11px] font-extrabold bg-black/20 px-2 py-0.5 rounded-full whitespace-nowrap">
                                {{ $tile['stock_badge_text'] }}
                            </span>
                        </div>
                    </div>

                    <div class="p-5 flex-1 flex flex-col">
                        <p class="text-gray-600 mb-3.5 text-xs leading-relaxed">{!! $tile['description'] !!}</p>
                        <ul class="text-xs text-gray-500 space-y-1.5 mb-5" aria-label="Produktmerkmale {{ $tile['title'] }}">
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
                               class="block text-center w-full bg-primary text-gray-900 py-2 rounded-lg text-xs font-bold hover:bg-primary-dark hover:text-white transition-colors shadow-sm">
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
                    <div class="absolute top-3 right-3 z-20 bg-amber-600 text-white text-[10px] font-extrabold px-2.5 py-1 rounded-full shadow-md uppercase tracking-wider">
                        Aktuell vergriffen
                    </div>
                    <figure class="overflow-hidden relative bg-gray-900" style="aspect-ratio: 10 / 9;">
                        <img src="{{ $tile['image'] }}"
                             alt="{{ $tile['alt'] }}"
                             loading="lazy"
                             class="w-full h-full object-cover object-center block grayscale-[0.5]">
                    </figure>
                    <div class="bg-gray-700 px-5 py-4 relative">
                        <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center shadow-sm absolute -top-6 right-5">
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600">
                                {!! $tile['icon_svg'] !!}
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-1 pr-12">{{ $tile['title'] }}</h3>
                        <span class="text-gray-300 text-[11px] font-bold tracking-wider uppercase">NACHPRODUKTION LÄUFT</span>
                    </div>
                    <div class="p-5 flex-1 flex flex-col">
                        <p class="text-gray-500 mb-3.5 text-xs">{!! $tile['description'] !!}</p>
                        <ul class="text-xs text-gray-400 space-y-1.5">
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
                        <div class="bg-black/80 text-white px-5 py-1.5 rounded-full text-xs font-bold tracking-widest border border-white/20 shadow-xl transform -rotate-12">
                            DEMNÄCHST
                        </div>
                    </div>

                    <figure class="overflow-hidden relative bg-gray-900" style="aspect-ratio: 10 / 9;">
                        <img src="{{ $tile['image'] }}"
                             alt="{{ $tile['alt'] }}"
                             loading="lazy"
                             class="w-full h-full object-cover object-center block">
                    </figure>

                    <div class="bg-gray-600 px-5 py-4 relative">
                        <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center shadow-sm absolute -top-6 right-5">
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-500">
                                {!! $tile['icon_svg'] !!}
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-200 mb-1 pr-12">{{ $tile['title'] }}</h3>
                        <span class="text-gray-300/80 text-[11px] font-medium tracking-wider uppercase">{{ $tile['subtitle'] }}</span>
                    </div>

                    <div class="p-5 flex-1 flex flex-col">
                        <p class="text-gray-500 mb-3.5 text-xs">{!! $tile['description'] !!}</p>
                        <ul class="text-xs text-gray-400 space-y-1.5" aria-label="Produktmerkmale {{ $tile['title'] }}">
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

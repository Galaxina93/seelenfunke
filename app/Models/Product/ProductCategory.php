<?php

namespace App\Models\Product;


use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\ExternalChannel;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductCategory extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Produktkategorie & Shop-Kollektion',
            description: 'Katalog-Gliederung (Glas & Kristall, Schmuck & Anhänger, Metall & Alu, Geschenksets).',
            department: SystemDepartment::Product,
            navigationNodes: ['admin.products.categories', 'shop.catalog'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::PublicCatalog,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory;

    protected $guarded = [];

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * Erzeugt vollautomatisch und fehlerunanfällig exakt $targetCount (Standard: 6) Kacheln
     * für die Startseiten-Sektion "Veredelungen & Materialien für Ihre Unikate".
     *
     * Funktionsweise:
     * 1. Prüft dynamisch alle vorhandenen Produktkategorien (inkl. künftig im Backend neu angelegter Kategorien)
     *    sowie alle aktiven Produkte (in nur 2 schlanken DB-Abfragen ohne N+1).
     * 2. Wählt pro aktiver Kategorie über eine gewichtete Zufalls-Rotation (Weighted Random) ein Produktbild aus,
     *    wobei brandneue Produkte in einer Kategorie (<= 14 Tage alt) einen starken "Neuheiten-Boost" erhalten
     *    und kachelübergreifend niemals dasselbe Produktbild doppelt gezogen wird.
     * 3. Nutzt kuratierte Kategorie-Texte + 3 positive Fakten oder generiert diese für künftige neue Kategorien
     *    vollautomatisch anhand der Produkteigenschaften.
     * 4. Füllt das Grid bei weniger als 6 aktiven Kategorien automatisch mit "Aktuell vergriffen"- bzw.
     *    "DEMNÄCHST"-Kacheln auf exakt 6 Kacheln auf.
     */
    public static function resolveShowcaseTiles(int $targetCount = 8): \Illuminate\Support\Collection
    {
        try {
            $activeProducts = Product::with([
                'categories',
                'templates' => fn ($q) => $q->where('is_active', true),
            ])
                ->where('status', 'active')
                ->get();

            $allCategories = static::orderBy('id')->get();
            $categoriesByNormName = $allCategories->keyBy(fn ($c) => mb_strtolower(trim((string) $c->name)));

            // Kuratierte Kern-Welten + automatische Erkennung aller neuen Datenbank-Kategorien
            $coreBlueprints = self::getCoreTileBlueprints();
            $metaCategoryNames = ['bestseller', 'geschenksets'];
            $coveredCategoryIds = [];
            $candidateTileDefs = [];

            foreach ($coreBlueprints as $blueprint) {
                $matchedDbCats = $allCategories->filter(function ($cat) use ($blueprint) {
                    $normName = mb_strtolower(trim((string) $cat->name));
                    $normSlug = mb_strtolower(trim((string) $cat->slug));
                    foreach ($blueprint['category_names'] as $cn) {
                        $normCn = mb_strtolower(trim($cn));
                        if ($normName === $normCn || $normSlug === \Illuminate\Support\Str::slug($normCn)) {
                            return true;
                        }
                    }
                    return false;
                });

                foreach ($matchedDbCats as $mc) {
                    $coveredCategoryIds[] = $mc->id;
                }

                $blueprint['db_category_ids'] = $matchedDbCats->pluck('id')->all();
                $candidateTileDefs[] = $blueprint;
            }

            // Alle weiteren Kategorien, die im Backend neu angelegt wurden, dynamisch als eigene Kachel-Kandidaten aufnehmen
            foreach ($allCategories as $cat) {
                if (in_array($cat->id, $coveredCategoryIds, true)) {
                    continue;
                }
                $normName = mb_strtolower(trim((string) $cat->name));
                if (in_array($normName, $metaCategoryNames, true)) {
                    continue;
                }

                $candidateTileDefs[] = self::buildDynamicCategoryBlueprint($cat);
            }

            $usedHeroProductIds = [];
            $usedImagePaths = [];
            $now = now();
            $oldestGlobalTs = (int) $activeProducts->min(fn (Product $p) => $p->created_at?->timestamp ?? 0);

            $evaluatedTiles = collect($candidateTileDefs)->map(function (array $tile) use (
                $activeProducts,
                $categoriesByNormName,
                &$usedHeroProductIds,
                &$usedImagePaths,
                $now,
                $oldestGlobalTs
            ) {
                $normCats = array_map(fn ($n) => mb_strtolower(trim($n)), $tile['category_names'] ?? []);
                $dbCatIds = $tile['db_category_ids'] ?? [];
                $slugs = $tile['slugs'] ?? [];
                $type = $tile['type'] ?? null;
                $materialKeywords = $tile['material_keywords'] ?? [];
                $tileCollectionLine = $tile['collection_line'] ?? null;

                $allMatched = $activeProducts->filter(function (Product $product) use ($normCats, $dbCatIds, $slugs, $type, $materialKeywords, $tileCollectionLine) {
                    // Strikte 3-Welten-Trennung: Ein Produkt aus "Atelier Echtgold & Silber" oder "Geschenkgutscheine"
                    // darf niemals in einer Manufaktur-Kategorie ("Schmuck & Accessoires", "Metall & Aluminium") gezählt oder als Bild gezogen werden.
                    if ($tileCollectionLine !== null && $product->resolveCollectionWorld() !== $tileCollectionLine) {
                        return false;
                    }
                    if ($type !== null && $product->type === $type) {
                        return true;
                    }
                    if (!empty($slugs) && in_array($product->slug, $slugs, true)) {
                        return true;
                    }
                    if (!empty($dbCatIds) && $product->categories->contains(fn ($c) => in_array($c->id, $dbCatIds, true))) {
                        return true;
                    }
                    if (!empty($normCats) && $product->categories->contains(fn ($c) => in_array(mb_strtolower(trim((string) $c->name)), $normCats, true))) {
                        return true;
                    }
                    if (!empty($materialKeywords) && $product->type === 'physical' && $product->slug !== 'geschenkgutschein') {
                        $matAttr = mb_strtolower((string) ($product->attributes['Material'] ?? ''));
                        $formAttr = mb_strtolower((string) ($product->attributes['Form'] ?? ''));
                        $matConf = mb_strtolower((string) ($product->configurator_settings['material_type'] ?? ''));
                        foreach ($materialKeywords as $kw) {
                            $kwLower = mb_strtolower($kw);
                            if (str_contains($matAttr, $kwLower) || str_contains($formAttr, $kwLower) || str_contains($matConf, $kwLower)) {
                                return true;
                            }
                        }
                    }
                    return false;
                })->values();

                // Jede aktive Kachel benötigt mindestens ein eigenes Produkt, das noch nicht als Hero-Bild
                // einer vorherigen Kachel gezogen wurde (verhindert identische Doppel-Kacheln bei nur 1 Produkt).
                $unclaimedAll = $allMatched->reject(fn (Product $p) => in_array($p->id, $usedHeroProductIds, true))->values();
                $matched = $unclaimedAll->isNotEmpty() ? $allMatched : collect();

                $inStockProducts = $matched->filter(function (Product $p) {
                    return !$p->track_quantity || $p->quantity > 0 || $p->continue_selling_when_out_of_stock;
                })->values();

                $totalStockUnits = $inStockProducts->sum(fn (Product $p) => $p->track_quantity ? max(0, (int) $p->quantity) : 50);
                $hasActiveProducts = $matched->isNotEmpty();
                $isAvailable = $inStockProducts->isNotEmpty();

                $unikateCount = $inStockProducts->filter(fn (Product $p) => $p->slug !== 'geschenkgutschein')->count();
                $voucherCount = $inStockProducts->filter(fn (Product $p) => $p->slug === 'geschenkgutschein')->count();

                $pricedProducts = $inStockProducts->filter(fn (Product $p) => (int) $p->gross_price > 0);
                $minPriceCents = $pricedProducts->isNotEmpty() ? (int) $pricedProducts->min(fn (Product $p) => (int) $p->gross_price) : null;

                if ($unikateCount > 0 && $voucherCount > 0) {
                    $stockBadgeText = $unikateCount . ' ' . ($unikateCount === 1 ? 'Unikat' : 'Unikate') . ' & Gutschein';
                    if ($minPriceCents) {
                        $stockBadgeText .= ' · ab ' . number_format($minPriceCents / 100, 2, ',', '.') . ' €*';
                    }
                } elseif ($voucherCount > 0) {
                    $stockBadgeText = ($voucherCount === 1 ? '1 Gutschein' : $voucherCount . ' Gutscheine') . ' · 5,00 € bis 1.000,00 €*';
                } else {
                    $stockBadgeText = $unikateCount . ' ' . ($unikateCount === 1 ? 'Unikat' : 'Unikate');
                    if ($minPriceCents) {
                        $stockBadgeText .= ' · ab ' . number_format($minPriceCents / 100, 2, ',', '.') . ' €*';
                    }
                }

                // Ziel-URL im Shop ermitteln (inkl. 3-Welten-Routing für Atelier & Gutscheine)
                $targetCategoryId = $dbCatIds[0] ?? null;
                if (!$targetCategoryId) {
                    foreach ($tile['category_names'] as $catName) {
                        $foundCat = $categoriesByNormName->get(mb_strtolower(trim($catName)));
                        if ($foundCat) {
                            $targetCategoryId = $foundCat->id;
                            break;
                        }
                    }
                }

                if ($tileCollectionLine === 'atelier') {
                    $shopUrl = route('shop', ['collectionWorld' => 'atelier']);
                } elseif ($tileCollectionLine === 'voucher') {
                    $shopUrl = route('shop', ['collectionWorld' => 'voucher']);
                } else {
                    $shopUrl = ($matched->count() === 1 && $matched->first()->slug === 'geschenkgutschein')
                        ? route('product.show', 'geschenkgutschein')
                        : ($targetCategoryId
                            ? route('shop', ['collectionWorld' => 'manufaktur', 'filterCategory' => $targetCategoryId])
                            : ($matched->count() === 1 ? route('product.show', $matched->first()->slug) : route('shop')));
                }

                // Gewichtete Zufalls-Auswahl (Weighted Random + Neuheiten-Boost) für das Kachel-Produktbild
                $tile['is_ai_generated'] = false;
                $tile['is_new_arrival'] = false;
                $tile['hero_product_name'] = null;

                $poolSource = $inStockProducts->isNotEmpty() ? $inStockProducts : $matched;
                if ($poolSource->isNotEmpty()) {
                    $orderedCandidates = self::orderCandidatesByWeightedRotation(
                        $poolSource,
                        $usedHeroProductIds,
                        $now
                    );

                    $oldestCategoryTs = (int) $poolSource->min(fn (Product $p) => $p->created_at?->timestamp ?? 0);

                    foreach ($orderedCandidates as $candidateProduct) {
                        $meta = $candidateProduct->resolveMainImageMeta(null, true, $usedImagePaths);
                        if (!empty($meta['url'])) {
                            $tile['image'] = $meta['url'];
                            $tile['alt'] = $candidateProduct->name . ' | ' . $tile['title'];
                            $tile['is_ai_generated'] = (bool) ($meta['is_ai_generated'] ?? false);
                            $tile['hero_product_name'] = $candidateProduct->name;

                            $ageDays = $candidateProduct->created_at ? (float) $candidateProduct->created_at->diffInDays($now) : 999.0;
                            $candidateTs = (int) ($candidateProduct->created_at?->timestamp ?? 0);
                            $baselineTs = $poolSource->count() > 1 ? $oldestCategoryTs : $oldestGlobalTs;
                            if (
                                $candidateProduct->slug !== 'geschenkgutschein'
                                && $ageDays <= 14.0
                                && ($candidateTs - $baselineTs) >= 3600
                            ) {
                                $tile['is_new_arrival'] = true;
                            }

                            $usedHeroProductIds[] = $candidateProduct->id;
                            if (!empty($meta['path'])) {
                                $usedImagePaths[] = $meta['path'];
                            }
                            break;
                        }
                    }
                }

                // Aktions-/Feiertags-Angebot prüfen
                $saleProduct = $inStockProducts->first(fn (Product $p) => !empty($p->compare_at_price) && (int) $p->compare_at_price > (int) $p->gross_price);
                $saleBadge = $saleProduct
                    ? ($saleProduct->configurator_settings['autopilot_badge_text'] ?? 'Angebot aktiv')
                    : ($tile['is_new_arrival'] ? '✨ Neu im Sortiment' : null);

                // Falls dynamische Kategorie ohne feste Fakten: 3 positive Fakten aus realen Produkten verfeinern
                if (!empty($tile['is_dynamic_category']) && $matched->isNotEmpty()) {
                    $tile['features'] = self::buildDynamicCategoryFeatures($matched, $tile['features']);
                }

                $tile['matched_count'] = $matched->count();
                $tile['in_stock_count'] = $inStockProducts->count();
                $tile['unikate_count'] = $unikateCount;
                $tile['total_stock_units'] = $totalStockUnits;
                $tile['has_active_products'] = $hasActiveProducts;
                $tile['is_available'] = $isAvailable;
                $tile['min_price_cents'] = $minPriceCents;
                $tile['stock_badge_text'] = $stockBadgeText;
                $tile['sale_badge'] = $saleBadge;
                $tile['shop_url'] = $shopUrl;

                return $tile;
            });

            // 2-Stufen-Priorisierung (ausschließlich Kategorien mit real vorhandenen Produkten, keine "DEMNÄCHST"-Platzhalter):
            // Stufe 1: Aktive & verfügbare Kategorien (Neuheiten-Kategorien und physische Unikate zuerst)
            $availableTiles = $evaluatedTiles
                ->filter(fn (array $t) => $t['is_available'])
                ->sortByDesc(fn (array $t) => ($t['is_new_arrival'] ? 500 : 0) + ($t['sale_badge'] ? 100 : 0) + (($t['unikate_count'] ?? 0) * 10) + $t['in_stock_count'])
                ->values();

            // Stufe 2: Kategorien mit aktiven Produkten, die aktuell vergriffen sind
            $outOfStockTiles = $evaluatedTiles
                ->filter(fn (array $t) => !$t['is_available'] && $t['has_active_products'])
                ->values();

            return $availableTiles
                ->concat($outOfStockTiles)
                ->take($targetCount)
                ->values();
        } catch (\Throwable $e) {
            report($e);
            return collect();
        }
    }

    /**
     * Sortiert die Produkt-Kandidaten einer Kachel mittels gewichteter Zufalls-Lotterie (Weighted Random Sampling without Replacement).
     * - Noch nicht auf Nachbar-Kacheln gezogene Produkte werden strikt bevorzugt.
     * - Brandneue Produkte (<= 14 Tage alt) erhalten einen starken Neuheiten-Boost, sodass sie anfangs deutlich länger präsent sind.
     * - Alle anderen Produkte rotieren gewichtet zufällig, damit die Kacheln bei jedem Besuch lebendig und abwechslungsreich wirken.
     */
    protected static function orderCandidatesByWeightedRotation(
        \Illuminate\Support\Collection $candidates,
        array $usedHeroProductIds,
        \Carbon\CarbonInterface $now
    ): \Illuminate\Support\Collection {
        $unclaimed = $candidates->reject(fn (Product $p) => in_array($p->id, $usedHeroProductIds, true))->values();
        $claimed = $candidates->filter(fn (Product $p) => in_array($p->id, $usedHeroProductIds, true))->values();

        return self::weightedShuffleProducts($unclaimed, $now)
            ->concat(self::weightedShuffleProducts($claimed, $now))
            ->values();
    }

    /**
     * Führt eine gewichtete Zufalls-Ziehung (Efraimidis-Spirakis / Lottery-Bag) über eine Produkt-Collection aus.
     */
    protected static function weightedShuffleProducts(
        \Illuminate\Support\Collection $products,
        \Carbon\CarbonInterface $now
    ): \Illuminate\Support\Collection {
        if ($products->count() <= 1) {
            return $products->values();
        }

        $oldestTs = (int) $products->min(fn (Product $p) => $p->created_at?->timestamp ?? 0);
        $pool = $products->all();
        $result = [];

        while (!empty($pool)) {
            $weights = [];
            $totalWeight = 0;

            foreach ($pool as $idx => $product) {
                $weight = 10; // Basis-Gewicht für jedes Bestandsprodukt

                $ageDays = $product->created_at ? (float) $product->created_at->diffInDays($now) : 999.0;
                $createdTs = (int) ($product->created_at?->timestamp ?? 0);

                // Absoluter Neuheiten-Boost nach Alter
                if ($ageDays <= 7.0) {
                    $weight += 60;
                } elseif ($ageDays <= 14.0) {
                    $weight += 35;
                } elseif ($ageDays <= 45.0) {
                    $weight += 15;
                }

                // Relativer Neuheiten-Boost: Wenn das Produkt innerhalb der letzten 14 Tage NACH dem bestehenden
                // Katalog-Stamm dieser Kategorie neu hinzugefügt wurde, bleibt es bevorzugt im Schaufenster
                if ($ageDays <= 14.0 && ($createdTs - $oldestTs) >= 300) {
                    $weight += 90;
                }

                // Angebots-Boost bei aktivem Streichpreis
                if (!empty($product->compare_at_price) && (int) $product->compare_at_price > (int) $product->gross_price) {
                    $weight += 25;
                }

                $weights[$idx] = max(1, $weight);
                $totalWeight += $weights[$idx];
            }

            $draw = random_int(1, max(1, $totalWeight));
            $cumulative = 0;
            $pickedIndex = array_key_first($pool);

            foreach ($weights as $idx => $w) {
                $cumulative += $w;
                if ($draw <= $cumulative) {
                    $pickedIndex = $idx;
                    break;
                }
            }

            $result[] = $pool[$pickedIndex];
            unset($pool[$pickedIndex]);
        }

        return collect($result);
    }

    /**
     * Erzeugt automatisch ein schickes Kachel-Profil für jede künftig im Backend neu angelegte Kategorie.
     */
    protected static function buildDynamicCategoryBlueprint(self $category): array
    {
        $name = trim((string) $category->name);
        $safeName = e($name);
        $type = (string) ($category->type ?: 'physical');
        $isAtelierCat = str_contains(mb_strtolower($name), 'atelier') || str_contains(mb_strtolower($name), 'echtgold');

        $subtitle = match (true) {
            $isAtelierCat => '585ER GOLD & 925ER SILBER',
            $type === 'digital' => 'DIGITAL & SOFORT',
            $type === 'service' => 'MANUFAKTUR SERVICE',
            default   => 'MANUFAKTUR UNIKATE',
        };

        $description = match (true) {
            $isAtelierCat => "Echte Edelmetalle für besondere Lebensmomente: Entdecken Sie unsere Unikate aus <strong>{$safeName}</strong>.",
            $type === 'digital' => "Sofort verfügbar und mit Liebe zum Detail ausgearbeitet: Entdecken Sie unsere digitalen Angebote aus der Kollektion <strong>{$safeName}</strong>.",
            $type === 'service' => "Individuelle Beratung und maßgeschneiderte Umsetzung im Bereich <strong>{$safeName}</strong> direkt aus unserer Manufaktur.",
            default   => "Handveredelte Unikate mit Charakter: Entdecken Sie unsere personalisierbaren Geschenke und Gravur-Highlights aus der Kategorie <strong>{$safeName}</strong>.",
        };

        return [
            'key' => 'cat_' . ($category->slug ?: $category->id),
            'title' => $name,
            'subtitle' => $subtitle,
            'collection_line' => $isAtelierCat ? 'atelier' : 'manufaktur',
            'collection_world_label' => $isAtelierCat ? 'Atelier Echtgold & Silber' : 'Manufaktur-Kollektion',
            'image' => asset('shop/product/seelen-kristall_w.webp'),
            'alt' => $name . ' | Personalisierte Unikate von Mein Seelenfunke',
            'description' => $description,
            'features' => [
                'Präzise Laser-Veredelung in Deutschland',
                'Individuell im Online-Konfigurator gestaltbar',
                'Sorgfältig von Hand geprüft & verpackt',
            ],
            'category_names' => [$name],
            'db_category_ids' => [$category->id],
            'material_keywords' => [],
            'slugs' => [],
            'type' => null,
            'is_dynamic_category' => true,
            'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />',
        ];
    }

    /**
     * Berechnet die exakten Stückzahlen der 3 Kollektions-Welten (Manufaktur, Atelier Echtgold & Silber, Geschenkgutscheine).
     */
    public static function resolveCollectionWorldCounts(): array
    {
        try {
            $activeProducts = Product::where('status', 'active')->get();

            return [
                'manufaktur' => $activeProducts->filter(fn (Product $p) => $p->resolveCollectionWorld() === 'manufaktur')->count(),
                'atelier'    => $activeProducts->filter(fn (Product $p) => $p->resolveCollectionWorld() === 'atelier')->count(),
                'voucher'    => $activeProducts->filter(fn (Product $p) => $p->resolveCollectionWorld() === 'voucher')->count(),
            ];
        } catch (\Throwable $e) {
            return [
                'manufaktur' => 6,
                'atelier'    => 1,
                'voucher'    => 1,
            ];
        }
    }

    /**
     * Verfeinert die 3 positiven Fakten für dynamisch angelegte Kategorien anhand der real enthaltenen Produkte.
     */
    protected static function buildDynamicCategoryFeatures(\Illuminate\Support\Collection $products, array $defaultFeatures): array
    {
        $features = [];

        if ($products->contains(fn (Product $p) => (bool) $p->three_d_active)) {
            $features[] = 'Live im interaktiven 3D-Konfigurator gestaltbar';
        } elseif ($products->contains(fn (Product $p) => (bool) $p->is_personalizable)) {
            $features[] = 'Individueller Wunschtext, Motiv & eigene Gravur';
        }

        if ($products->contains(fn (Product $p) => !empty($p->configurator_settings['has_back_side']))) {
            $features[] = 'Beidseitige Präzisions-Lasergravur möglich';
        } else {
            $features[] = 'Hochpräzise & dauerhaft abriebfeste Veredelung';
        }

        $features[] = 'Handgeprüft & liebevoll verschenkfertig verpackt';

        return array_slice(array_values(array_unique(array_merge($features, $defaultFeatures))), 0, 3);
    }

    /**
     * Liefert die kuratierten Kernwelten (strikt getrennt nach den 3 Kollektions-Welten:
     * Manufaktur-Kollektion, Atelier Echtgold & Silber, Geschenkgutscheine).
     */
    public static function getCoreTileBlueprints(): array
    {
        return [
            [
                'key' => 'glas_kristall',
                'title' => 'Glas & Kristall',
                'subtitle' => 'MANUFAKTUR · PREMIUM GRAVUR',
                'collection_line' => 'manufaktur',
                'collection_world_label' => 'Manufaktur-Kollektion',
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
                'subtitle' => 'MANUFAKTUR · MODERNE ELEGANZ',
                'collection_line' => 'manufaktur',
                'collection_world_label' => 'Manufaktur-Kollektion',
                'image' => asset('shop/product/seelen-anhaenger_s.webp'),
                'alt' => 'Lasergravur auf massivem Aluminium und Metall-Unikaten',
                'description' => 'Spürbare Wertigkeit aus eloxiertem <strong>Aluminium und edlen Metallen</strong>. Kontrastreiche, abriebfeste Lasergravuren auf Vorder- und Rückseite für den Alltag.',
                'features' => [
                    'Beidseitige Präzisions-Lasergravur',
                    'Handschmeichelnde, massive Haptik',
                    'Unverwüstlich & dauerhaft abriebfest',
                ],
                'category_names' => ['Metall & Alu', 'Metall & Aluminium'],
                'material_keywords' => ['aluminium', 'metall', 'metal', 'edelstahl'],
                'slugs' => ['seelen-anhaenger'],
                'type' => null,
                'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />',
            ],
            [
                'key' => 'klein_fein',
                'title' => 'Schmuck & Accessoires',
                'subtitle' => 'MANUFAKTUR · KLEIN & FEIN',
                'collection_line' => 'manufaktur',
                'collection_world_label' => 'Manufaktur-Kollektion',
                'image' => asset('shop/product/flaschenoeffner.webp'),
                'alt' => 'Personalisierte Schlüsselanhänger und Accessoires',
                'description' => 'Große Emotionen im handlichen Format. Edle <strong>Halsketten, Schlüsselanhänger, Glücksbringer und Accessoires</strong> als täglicher Begleiter oder persönliches Geschenk.',
                'features' => [
                    'Perfekt als persönlicher Glücksbringer',
                    'Feinste Detail- und Symbolgravur',
                    'Ideal als Schmuckstück & für den Alltag',
                ],
                'category_names' => ['Schmuck & Anhänger', 'Schmuck & Accessoires', 'Accessoires'],
                'material_keywords' => ['halskette', 'ankerkette', 'collier', 'schlüsselanhänger', 'hundemarke'],
                'slugs' => ['goldenes-seelen-herz', 'seelen-talisman', 'seelen-pfote-hundemarke'],
                'type' => null,
                'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />',
            ],
            [
                'key' => 'atelier_echtgold_silber',
                'title' => 'Atelier Echtgold & Silber',
                'subtitle' => '585ER GOLD & 925ER SILBER',
                'collection_line' => 'atelier',
                'collection_world_label' => 'Atelier Echtgold & Silber',
                'image' => asset('storage/produkte/products/echtgold-seelen-herz/echtgold-seelen-herz-main.jpg'),
                'alt' => 'Atelier Echtgold und 925er Silber Schmuck mit Lasergravur',
                'description' => 'Echte Edelmetalle für besondere Lebensmomente. Wir veredeln gestempeltes <strong>585er & 333er Echtgold sowie 925er Sterlingsilber</strong> per berührungslosem UV-Präzisionslaser auf Bestellung.',
                'features' => [
                    'Echtes 585er/333er Gold & 925er Silber',
                    'Beidseitige UV-Präzisions-Lasergravur',
                    'Inklusive Schmucketui & versichertem Versand',
                ],
                'category_names' => ['Atelier Echtgold & Silber'],
                'material_keywords' => [],
                'slugs' => ['echtgold-seelen-herz'],
                'type' => null,
                'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />',
            ],
            [
                'key' => 'atelier_armbaender',
                'title' => 'Echtgold & Silber Armbänder',
                'subtitle' => 'ATELIER · GRAVUR-ARMBÄNDER',
                'collection_line' => 'atelier',
                'collection_world_label' => 'Atelier Echtgold & Silber',
                'image' => asset('storage/produkte/products/echtgold-seelen-herz/echtgold-seelen-herz-lifestyle.jpg'),
                'alt' => 'Personalisierte Gravurarmbänder aus 925er Sterlingsilber und Echtgold',
                'description' => 'Feine Figaro- und massive Panzer-Gravurarmbänder aus <strong>925er Sterlingsilber sowie 333er & 585er Echtgold</strong> mit beidseitig gravierbarer Platte.',
                'features' => [
                    'Gestempeltes 925er Silber & Echtgold',
                    'Gravurschild für Namen, Datum & Koordinaten',
                    'Für Damen, Herren, Partner & zur Taufe',
                ],
                'category_names' => ['Atelier Armbänder'],
                'material_keywords' => ['gravurarmband', 'silberarmband'],
                'slugs' => [],
                'type' => null,
                'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />',
            ],
            [
                'key' => 'geschenksets',
                'title' => 'Geschenkgutscheine',
                'subtitle' => 'SOFORT ODER METALLKARTE',
                'collection_line' => 'voucher',
                'collection_world_label' => 'Geschenkgutscheine',
                'image' => asset('shop/product/geschenkpapier.webp'),
                'alt' => 'Exklusive Geschenksets und Wertgutscheine von Mein Seelenfunke',
                'description' => 'Das Auspacken ist Teil des Erlebnisses. Entdecken Sie flexible <strong>Seelenfunken-Wertgutscheine digital per E-Mail oder als lasergravierte Metallkarte</strong> im Edel-Umschlag.',
                'features' => [
                    'Sofort verschenkfertig vorbereitet',
                    'Digital als PDF oder als edle Metallkarte',
                    'Für Geburtstage, Hochzeiten & Feste',
                ],
                'category_names' => ['Gutscheine', 'Wertgutscheine'],
                'material_keywords' => [],
                'slugs' => ['geschenkgutschein'],
                'type' => null,
                'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H4.5a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />',
            ],
            [
                'key' => 'schiefer_natur',
                'title' => 'Schiefer & Natur',
                'subtitle' => 'RUSTIKALE UNIKATE',
                'collection_line' => 'manufaktur',
                'collection_world_label' => 'Manufaktur-Kollektion',
                'image' => asset('shop/product/schiefer.webp'),
                'alt' => 'Personalisierte Schieferplatten und Naturmaterialien mit Lasergravur',
                'description' => 'Jedes Stück ein gewachsenes Natur-Unikat. Die charaktervolle Struktur von <strong>Schiefer und edlen Hölzern</strong> sorgt für faszinierende Kontraste bei der Gravur.',
                'features' => [
                    'Wetterfest & charakterstarke Maserung',
                    'Untersetzer, Gedenktafeln & Schilder',
                    'Natürliche Bruchkanten-Optik',
                ],
                'category_names' => ['Schiefer & Natur', 'Schiefer', 'Holz & Natur'],
                'material_keywords' => ['schiefer', 'slate', 'holz', 'wood', 'naturstein'],
                'slugs' => [],
                'type' => null,
                'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />',
            ],
            [
                'key' => 'deine_idee',
                'title' => 'Deine Idee & B2B',
                'subtitle' => 'SONDERANFERTIGUNG',
                'collection_line' => 'manufaktur',
                'collection_world_label' => 'Manufaktur-Kollektion',
                'image' => asset('shop/product/individuell.webp'),
                'alt' => 'Sonderanfertigungen und individuelle Laser-Projekte',
                'description' => 'Sie planen ein besonderes Projekt, Vereins-Awards oder eine individuelle Serie? Wir prüfen die technische Machbarkeit und setzen Ihre Wunschgravur um.',
                'features' => [
                    'Individuelle Serien & Vereins-Awards',
                    'Persönliche Machbarkeitsprüfung',
                    'Direkte Beratung aus der Manufaktur',
                ],
                'category_names' => ['Beratung', 'Express-Service', 'Sonderanfertigung', 'B2B'],
                'material_keywords' => [],
                'slugs' => [],
                'type' => 'service',
                'icon_svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />',
            ],
        ];
    }
}

<?php

namespace App\Services\Marketing;

use App\Models\Marketing\MarketingNewsletter;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class HolidayOfferAutopilotService
{
    public const MIN_NET_MARGIN_PERCENT = 45.0;
    public const MIN_STOCK_THRESHOLD = 15;
    public const MAX_CAPACITY_LEVEL = 2; // 0 = Grün, 1 = Normal, 2 = Erhöht (OK), >= 3 = Rot/Stopp

    /**
     * Prüft, ob der 100% Angebots-Autopilot global aktiv ist.
     */
    public function isEnabled(): bool
    {
        $val = shop_setting('marketing_autopilot_offers_enabled', 'true');
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Schaltet den 100% Angebots-Autopiloten an oder aus und synchronisiert sofort die Produktpreise.
     */
    public function setEnabled(bool $enabled): array
    {
        SystemSetting::updateOrCreate(
            ['key' => 'marketing_autopilot_offers_enabled'],
            ['value' => $enabled ? 'true' : 'false']
        );
        Cache::forget('global_shop_settings');

        return $this->syncActiveOffers();
    }

    /**
     * Liefert die Zusammenfassung der 5 eisernen Schutzmauern für die UI & Info-Tooltips.
     */
    public function getGuardrailsStatus(): array
    {
        $capacityLevel = (int) Cache::get('shop_capacity_level', shop_setting('shop_capacity_level', 0));
        $capacityOk = $capacityLevel <= self::MAX_CAPACITY_LEVEL;

        return [
            'enabled' => $this->isEnabled(),
            'min_net_margin_percent' => self::MIN_NET_MARGIN_PERCENT,
            'min_stock_threshold' => self::MIN_STOCK_THRESHOLD,
            'capacity_level' => $capacityLevel,
            'capacity_ok' => $capacityOk,
            'anti_stacking_active' => true,
            'last_minute_protection' => true,
        ];
    }

    public function evaluateCatalogGuardrails(): array
    {
        return $this->getGuardrailsStatus();
    }

    public function getHolidayEventsDefinition(): array
    {
        return $this->getEventDefinitions();
    }

    /**
     * Definiert für jedes Feiertags-/Saison-Event die optimale Manufaktur-Angebotsstrategie.
     */
    public function getEventDefinitions(): array
    {
        return [
            'valentines' => [
                'label' => 'Valentinstags-Aktion',
                'badge_text' => '💘 Valentins-Sale',
                'discount_percent' => 12,
                'start_days_before' => 21,
                'end_days_before' => 4,
                'product_slugs' => ['seelen-kristall', 'seelen-anhaenger'],
                'strategy_hint' => 'Paar- & Liebes-Unikate werden 21 Tage vor dem 14.02. reduziert (7 Tage Ad-Vorlauf vor dem Newsletter). Endet automatisch 4 Tage vor Valentinstag für das Last-Minute-Express-Geschäft zum Vollpreis.',
            ],
            'womens_day' => [
                'label' => 'Weltfrauentags-Special',
                'badge_text' => '💐 Frauentags-Sale',
                'discount_percent' => 10,
                'start_days_before' => 16,
                'end_days_before' => 3,
                'product_slugs' => ['seelen-kristall', 'seelen-anhaenger'],
                'strategy_hint' => 'Wertschätzungs-Aktion auf Kristall & Herz-Anhänger. Schaltet sich 3 Tage vor dem 08.03. automatisch auf Normalpreis zurück.',
            ],
            'easter' => [
                'label' => 'Oster-Manufaktur-Aktion',
                'badge_text' => '🐰 Oster-Angebot',
                'discount_percent' => 10,
                'start_days_before' => 21,
                'end_days_before' => 4,
                'product_slugs' => ['seelen-kristall', 'seelen-anhaenger', 'weizenglas-personalisiert'],
                'strategy_hint' => 'Frühlings- & Familiengeschenke fürs Osternest. Endet 4 Tage vor Ostersonntag pünktlich zur Versandgrenze.',
            ],
            'mothers_day' => [
                'label' => 'Muttertags-Aktion',
                'badge_text' => '💝 Muttertags-Sale',
                'discount_percent' => 12,
                'start_days_before' => 21,
                'end_days_before' => 4,
                'product_slugs' => ['seelen-kristall', 'seelen-anhaenger'],
                'strategy_hint' => 'Bestseller-Aktion für Mütter (3D-Kristall & massives Alu-Herz). Endet 4 Tage vor Muttertag für volle Marge bei Express-Bestellern.',
            ],
            'fathers_day' => [
                'label' => 'Vatertags-Special',
                'badge_text' => '🍺 Vatertags-Sale',
                'discount_percent' => 12,
                'start_days_before' => 21,
                'end_days_before' => 4,
                'product_slugs' => ['weizenglas-personalisiert', 'seelen-anhaenger'],
                'strategy_hint' => 'Fokus auf den Feierabend-Funke (Weizenglas mit 360°-Gravur) & Alu-Anhänger. Endet 4 Tage vor Christi Himmelfahrt.',
            ],
            'sale_summer' => [
                'label' => 'Großer Sommer-Sale',
                'badge_text' => '☀️ Sommer-Sale',
                'discount_percent' => 15,
                'start_days_before' => 0,
                'end_days_before' => -14, // Läuft vom 01.07. bis 15.07.
                'product_slugs' => ['seelen-kristall', 'seelen-anhaenger', 'weizenglas-personalisiert'],
                'strategy_hint' => 'Saisonaler Lager-Umschlag im Sommer (01.07. – 15.07.) für das gesamte physische Sortiment unter Einhaltung der 45-%-Mindestmarge.',
            ],
            'halloween' => [
                'label' => 'Herbst- & Lichter-Aktion',
                'badge_text' => '🎃 Grusel-Angebot',
                'discount_percent' => 10,
                'start_days_before' => 18,
                'end_days_before' => 1,
                'product_slugs' => ['seelen-kristall', 'weizenglas-personalisiert'],
                'strategy_hint' => 'Herbst-Kampagne rund um Lichtbrechung & gemütliche Abende im Oktober.',
            ],
            'advent_1' => [
                'label' => 'Advents-Frühbucher-Aktion',
                'badge_text' => '🕯️ Advents-Deal',
                'discount_percent' => 12,
                'start_days_before' => 14,
                'end_days_before' => 0,
                'product_slugs' => ['seelen-kristall', 'seelen-anhaenger', 'weizenglas-personalisiert'],
                'strategy_hint' => 'Entzerrt das Weihnachtsgeschäft: Belohnt Kunden, die schon im November vor dem großen Dezember-Ansturm bestellen.',
            ],
            'christmas' => [
                'label' => 'Weihnachts-Manufaktur-Aktion',
                'badge_text' => '🎄 Weihnachts-Sale',
                'discount_percent' => 10,
                'start_days_before' => 23, // Ab 01.12.
                'end_days_before' => 6,  // Endet am 18.12.!
                'product_slugs' => ['seelen-kristall', 'seelen-anhaenger', 'weizenglas-personalisiert'],
                'strategy_hint' => 'Haupt-Weihnachtsaktion vom 01.12. bis 18.12. Ab dem 19.12. schaltet der Rabatt automatisch ab (Fokus auf Express & digitale Sofort-Gutscheine zum Vollpreis).',
            ],
            'new_year' => [
                'label' => 'Neujahrs-Aktion',
                'badge_text' => '🎆 Neujahrs-Deal',
                'discount_percent' => 10,
                'start_days_before' => 0,
                'end_days_before' => -7, // 01.01. bis 08.01.
                'product_slugs' => ['seelen-kristall', 'seelen-anhaenger'],
                'strategy_hint' => 'Neujahrs-Impuls in der ersten Januarwoche für persönliche Jahresmottos & Meilensteine.',
            ],
            'sale_winter' => [
                'label' => 'Großer Winter- & Inventur-Sale',
                'badge_text' => '❄️ Winter-Sale',
                'discount_percent' => 15,
                'start_days_before' => 0,
                'end_days_before' => -12, // 04.01. bis 16.01.
                'product_slugs' => ['seelen-kristall', 'seelen-anhaenger', 'weizenglas-personalisiert'],
                'strategy_hint' => 'Inventur-Aktion Anfang Januar (04.01. – 16.01.) zur Auslastung der Werkstatt im ruhigen Januar, bevor die Valentinstags-Phase startet.',
            ],
        ];
    }

    /**
     * Berechnet das exakte Datum eines Events für ein bestimmtes Jahr.
     */
    public function getHolidayDate(string $key, int $year): ?Carbon
    {
        return match ($key) {
            'valentines'  => Carbon::create($year, 2, 14)->startOfDay(),
            'womens_day'  => Carbon::create($year, 3, 8)->startOfDay(),
            'halloween'   => Carbon::create($year, 10, 31)->startOfDay(),
            'christmas'   => Carbon::create($year, 12, 24)->startOfDay(),
            'new_year'    => Carbon::create($year, 1, 1)->startOfDay(),
            'sale_summer' => Carbon::create($year, 7, 1)->startOfDay(),
            'sale_winter' => Carbon::create($year, 1, 4)->startOfDay(),
            'easter'      => $this->getEasterDate($year)->startOfDay(),
            'mothers_day' => Carbon::create($year, 5, 1)->nthOfMonth(2, Carbon::SUNDAY)->startOfDay(),
            'fathers_day' => $this->getEasterDate($year)->addDays(39)->startOfDay(),
            'advent_1'    => Carbon::create($year, 11, 26)->next(Carbon::SUNDAY)->startOfDay(),
            default       => null,
        };
    }

    private function getEasterDate(int $year): Carbon
    {
        $K = (int) ($year / 100);
        $M = 15 + (int) ((3 * $K + 3) / 4) - (int) ((8 * $K + 13) / 25);
        $S = 2 - (int) ((3 * $K + 3) / 4);
        $A = $year % 19;
        $D = (19 * $A + $M) % 30;
        $R = (int) ($D / 29) + ((int) ($D / 28) - (int) ($D / 29)) * (int) ($A / 11);
        $OG = 21 + $D - $R;
        $SZ = 7 - ($year + (int) ($year / 4) + $S) % 7;
        $OE = 7 - ($OG - $SZ) % 7;

        return Carbon::createFromDate($year, 3, 1)->addDays($OG + $OE - 1);
    }

    /**
     * Ermittelt den unantastbaren Basispreis (Normalpreis) eines Produkts in Cent.
     */
    public function resolveTrueBasePriceCents(Product $product): int
    {
        $settings = is_array($product->configurator_settings) ? $product->configurator_settings : [];
        if (!empty($settings['autopilot_base_price']) && (int) $settings['autopilot_base_price'] > 0) {
            return (int) $settings['autopilot_base_price'];
        }

        return (int) $product->price;
    }

    /**
     * Berechnet den margen-gesicherten Aktionspreis & Deckungsbeitrag für ein einzelnes Produkt.
     */
    public function calculateProductOffer(Product $product, int $targetDiscountPercent): array
    {
        $basePriceCents = $this->resolveTrueBasePriceCents($product);

        // 1. Echte Stückkosten aus der Datenbank
        $purchaseCents = (int) ($product->purchase_price ?? 0);
        $packagingCents = (int) ($product->packaging_cost ?? 0);
        $laserMinutes = max(1, (int) ($product->laser_runtime_minutes ?? 1));
        $wearFactorCents = (int) ($product->electricity_wear_factor ?? 10);
        $laserCents = $laserMinutes * $wearFactorCents;
        $directCostsCents = $purchaseCents + $packagingCents + $laserCents;

        // 2. Steuersatz & geplante Marketing-Quote
        $isSmallBusiness = filter_var(shop_setting('is_small_business', false), FILTER_VALIDATE_BOOLEAN);
        $defaultTaxRate = (float) shop_setting('default_tax_rate', 19.0);
        $taxRate = $isSmallBusiness ? 0.0 : (float) ($product->tax_rate ?? $defaultTaxRate);
        $taxIncluded = (bool) ($product->tax_included ?? filter_var(shop_setting('prices_entered_gross', true), FILTER_VALIDATE_BOOLEAN));
        $marketingPercent = (float) ($product->marketing_cost_percent ?? 15.0);

        // 3. Ziel-Aktionspreis berechnen und verkaufspsychologisch auf ,90 € runden
        $rawDiscountedCents = (int) round($basePriceCents * (1 - ($targetDiscountPercent / 100)));
        $salePriceCents = $this->roundToPsychologicalPrice($rawDiscountedCents, $basePriceCents);

        // 4. Harte Margen-Schutzmauer prüfen (Mindestens 45 % Netto-Marge nach ALLEN Kosten)
        $marginProtected = false;
        while ($salePriceCents < $basePriceCents) {
            $metrics = $this->computeMarginMetrics($salePriceCents, $directCostsCents, $taxRate, $marketingPercent, $taxIncluded);
            if ($metrics['net_margin_percent'] >= self::MIN_NET_MARGIN_PERCENT) {
                break;
            }
            $marginProtected = true;
            $salePriceCents += 100; // In 1,00-€-Schritten anheben, bis die 45-%-Marge sicher gehalten wird
        }

        if ($salePriceCents >= $basePriceCents) {
            $salePriceCents = $basePriceCents;
        }

        $finalMetrics = $this->computeMarginMetrics($salePriceCents, $directCostsCents, $taxRate, $marketingPercent, $taxIncluded);
        $savingsCents = max(0, $basePriceCents - $salePriceCents);
        $effectiveDiscountPercent = $basePriceCents > 0
            ? (int) round(($savingsCents / $basePriceCents) * 100)
            : 0;

        $stockOk = !$product->track_quantity || (int) $product->quantity >= self::MIN_STOCK_THRESHOLD;

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'base_price_cents' => $basePriceCents,
            'sale_price_cents' => $salePriceCents,
            'savings_cents' => $savingsCents,
            'target_discount_percent' => $targetDiscountPercent,
            'effective_discount_percent' => $effectiveDiscountPercent,
            'direct_costs_cents' => $directCostsCents,
            'marketing_cost_cents' => $finalMetrics['marketing_cost_cents'],
            'payment_fee_cents' => $finalMetrics['payment_fee_cents'],
            'net_profit_cents' => $finalMetrics['net_profit_cents'],
            'net_margin_percent' => $finalMetrics['net_margin_percent'],
            'stock_quantity' => (int) $product->quantity,
            'stock_ok' => $stockOk,
            'margin_protected' => $marginProtected,
        ];
    }

    private function roundToPsychologicalPrice(int $rawCents, int $basePriceCents): int
    {
        $euros = (int) floor($rawCents / 100);
        $candidate = ($euros * 100) + 90; // z. B. 34,90 €

        if ($candidate >= $basePriceCents) {
            $candidate = max(190, (($euros - 1) * 100) + 90);
        }

        return $candidate;
    }

    private function computeMarginMetrics(int $priceCents, int $directCostsCents, float $taxRate, float $marketingPercent, bool $taxIncluded = true): array
    {
        if ($taxIncluded) {
            $grossCents = $priceCents;
            $netCents = $taxRate > 0 ? (int) round($grossCents / (1 + ($taxRate / 100))) : $grossCents;
        } else {
            $netCents = $priceCents;
            $grossCents = $taxRate > 0 ? (int) round($netCents * (1 + ($taxRate / 100))) : $netCents;
        }

        $marketingCostCents = (int) round($netCents * ($marketingPercent / 100));
        $paymentFeeCents = Product::computeStripeFeeCents($grossCents); // ~2,5 % + 0,30 € Stripe/PayPal Puffer

        $netProfitCents = $netCents - $directCostsCents - $marketingCostCents - $paymentFeeCents;
        $netMarginPercent = $netCents > 0 ? round(($netProfitCents / $netCents) * 100, 1) : 0.0;

        return [
            'net_cents' => $netCents,
            'marketing_cost_cents' => $marketingCostCents,
            'payment_fee_cents' => $paymentFeeCents,
            'net_profit_cents' => $netProfitCents,
            'net_margin_percent' => $netMarginPercent,
        ];
    }

    /**
     * Liefert die komplette Kampagnen-Vorschau für ein Event (inkl. Zeitraum, Produkten & Durchschnitts-Marge).
     */
    public function getCampaignOfferPreview(string $eventKey, ?Carbon $eventDate = null, ?int $newsletterDaysOffset = null): ?array
    {
        $definitions = $this->getEventDefinitions();
        if (!isset($definitions[$eventKey])) {
            return null;
        }

        $def = $definitions[$eventKey];
        $year = (int) date('Y');
        $resolvedEventDate = $eventDate ? $eventDate->copy()->startOfDay() : $this->getHolidayDate($eventKey, $year);

        if (!$resolvedEventDate) {
            return null;
        }

        // Stelle sicher, dass der Sale spätestens zum Newsletter-Versand startet
        $startDaysBefore = (int) $def['start_days_before'];
        if ($newsletterDaysOffset !== null && $newsletterDaysOffset > $startDaysBefore) {
            $startDaysBefore = $newsletterDaysOffset;
        }

        $endDaysBefore = (int) $def['end_days_before'];

        $saleStart = $resolvedEventDate->copy()->subDays($startDaysBefore)->startOfDay();
        $saleEnd = $resolvedEventDate->copy()->subDays($endDaysBefore)->endOfDay();

        // Wenn das gesamte Fenster in der Vergangenheit liegt und kein explizites Datum übergeben wurde, nächstes Jahr berechnen
        if (!$eventDate && $saleEnd->isPast()) {
            $resolvedEventDate = $this->getHolidayDate($eventKey, $year + 1);
            $saleStart = $resolvedEventDate->copy()->subDays($startDaysBefore)->startOfDay();
            $saleEnd = $resolvedEventDate->copy()->subDays($endDaysBefore)->endOfDay();
        }

        $products = Product::where('status', 'active')
            ->where('type', 'physical')
            ->whereIn('slug', $def['product_slugs'])
            ->get();

        $productOffers = [];
        foreach ($products as $product) {
            $productOffers[] = $this->calculateProductOffer($product, (int) $def['discount_percent']);
        }

        $avgMargin = count($productOffers) > 0
            ? round(collect($productOffers)->avg('net_margin_percent'), 1)
            : 0.0;

        $avgDiscount = count($productOffers) > 0
            ? (int) round(collect($productOffers)->avg('effective_discount_percent'))
            : (int) $def['discount_percent'];

        $now = Carbon::now();
        $isActiveWindow = $now->between($saleStart, $saleEnd);

        return [
            'event_key' => $eventKey,
            'label' => $def['label'],
            'badge_text' => $def['badge_text'] ?? 'Sale',
            'strategy_hint' => $def['strategy_hint'],
            'target_discount_percent' => (int) $def['discount_percent'],
            'effective_discount_percent' => $avgDiscount,
            'sale_start' => $saleStart,
            'sale_end' => $saleEnd,
            'event_date' => $resolvedEventDate,
            'has_last_minute_cutoff' => $endDaysBefore > 0,
            'last_minute_days' => max(0, $endDaysBefore),
            'is_active_window' => $isActiveWindow,
            'products' => $productOffers,
            'avg_net_margin_percent' => $avgMargin,
        ];
    }

    /**
     * Ermittelt das aktuell aktive Feiertags-Angebotsfenster (für Shop-Header-Banner & Badges),
     * ohne Datenbank-Schreiboperationen auszuführen.
     */
    public function resolveActiveOfferWindow(?Carbon $referenceDate = null): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $guardrails = $this->evaluateCatalogGuardrails();
        if (!$guardrails['capacity_ok']) {
            return null;
        }

        $now = $referenceDate ? $referenceDate->copy() : Carbon::now();

        foreach ($this->getEventDefinitions() as $key => $def) {
            foreach ([$now->year, $now->year - 1, $now->year + 1] as $candidateYear) {
                $eventDate = $this->getHolidayDate($key, $candidateYear);
                if (!$eventDate) {
                    continue;
                }

                $startDaysBefore = (int) ($def['start_days_before'] ?? 0);
                $endDaysBefore = (int) ($def['end_days_before'] ?? 0);

                $saleStart = $eventDate->copy()->subDays($startDaysBefore)->startOfDay();
                $saleEnd = $eventDate->copy()->subDays($endDaysBefore)->endOfDay();

                if ($now->between($saleStart, $saleEnd)) {
                    return [
                        'event_key'  => $key,
                        'name'       => $def['label'] ?? $key,
                        'label'      => $def['label'] ?? $key,
                        'badge_text' => $def['badge_text'] ?? 'Angebot',
                        'sale_start' => $saleStart,
                        'sale_end'   => $saleEnd,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Prüft, welche Feiertags-Kampagne heute im Angebots-Fenster liegt, und schaltet
     * Streichpreise vollautomatisch ein bzw. nach Ablauf wieder auf den Basispreis zurück.
     */
    public function syncActiveOffers(?Carbon $referenceDate = null): array
    {
        $now = $referenceDate ? $referenceDate->copy() : Carbon::now();
        $guardrails = $this->getGuardrailsStatus();

        $activeTemplates = MarketingNewsletter::where('is_active', true)
            ->where('type', 'automated')
            ->whereNotNull('target_event_key')
            ->get();

        $activeOffersByProductId = [];
        $activeCampaignLabel = null;
        $activeEventKey = null;

        if ($guardrails['enabled'] && $guardrails['capacity_ok']) {
            foreach ($activeTemplates as $template) {
                $key = (string) $template->target_event_key;
                foreach ([$now->year, $now->year - 1, $now->year + 1] as $candidateYear) {
                    $eventDate = $this->getHolidayDate($key, $candidateYear);
                    if (!$eventDate) {
                        continue;
                    }

                    $preview = $this->getCampaignOfferPreview($key, $eventDate, (int) $template->days_offset);
                    if (!$preview) {
                        continue;
                    }

                    if ($now->between($preview['sale_start'], $preview['sale_end'])) {
                        $activeCampaignLabel = $preview['label'];
                        $activeEventKey = $key;

                        foreach ($preview['products'] as $offer) {
                            if ($offer['stock_ok'] && $offer['sale_price_cents'] < $offer['base_price_cents']) {
                                $existing = $activeOffersByProductId[$offer['product_id']] ?? null;
                                // Falls sich zwei Fenster überschneiden, gewinnt der bessere Aktionspreis
                                if (!$existing || $offer['sale_price_cents'] < $existing['sale_price_cents']) {
                                    $activeOffersByProductId[$offer['product_id']] = array_merge($offer, [
                                        'event_key' => $key,
                                        'campaign_label' => $preview['label'],
                                        'badge_text' => $preview['badge_text'] ?? 'Sale',
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        $physicalProducts = Product::where('type', 'physical')->get();
        $updatedCount = 0;
        $restoredCount = 0;

        foreach ($physicalProducts as $product) {
            $settings = is_array($product->configurator_settings) ? $product->configurator_settings : [];
            $targetOffer = $activeOffersByProductId[$product->id] ?? null;

            if ($targetOffer) {
                $basePrice = $targetOffer['base_price_cents'];
                $salePrice = $targetOffer['sale_price_cents'];

                if (!array_key_exists('autopilot_original_compare_at_price', $settings)) {
                    $settings['autopilot_original_compare_at_price'] = $product->compare_at_price !== null ? (int) $product->compare_at_price : null;
                }

                $settings['autopilot_base_price'] = $basePrice;
                $settings['autopilot_event_key'] = $targetOffer['event_key'];
                $settings['autopilot_campaign_label'] = $targetOffer['campaign_label'];
                $settings['autopilot_badge_text'] = $targetOffer['badge_text'] ?? 'Sale';

                if ((int) $product->price !== $salePrice || (int) $product->compare_at_price !== $basePrice || $product->configurator_settings !== $settings) {
                    $product->price = $salePrice;
                    $product->compare_at_price = $basePrice;
                    $product->configurator_settings = $settings;
                    $product->saveQuietly();
                    $updatedCount++;
                }
            } else {
                // Kein aktives Autopilot-Angebot für dieses Produkt -> Basispreis wiederherstellen, falls vom Autopiloten gesetzt
                if (!empty($settings['autopilot_base_price'])) {
                    $basePriceCents = (int) $settings['autopilot_base_price'];
                    $originalCompareAt = $settings['autopilot_original_compare_at_price'] ?? null;
                    unset(
                        $settings['autopilot_base_price'],
                        $settings['autopilot_original_compare_at_price'],
                        $settings['autopilot_event_key'],
                        $settings['autopilot_campaign_label'],
                        $settings['autopilot_badge_text']
                    );

                    $product->price = $basePriceCents;
                    $product->compare_at_price = $originalCompareAt !== null ? (int) $originalCompareAt : null;
                    $product->configurator_settings = $settings;
                    $product->saveQuietly();
                    $restoredCount++;
                }
            }
        }

        return [
            'autopilot_enabled' => $guardrails['enabled'],
            'capacity_ok' => $guardrails['capacity_ok'],
            'active_event_key' => $activeEventKey,
            'active_campaign_label' => $activeCampaignLabel,
            'active_products_count' => count($activeOffersByProductId),
            'updated_count' => $updatedCount,
            'restored_count' => $restoredCount,
        ];
    }

    /**
     * Erzeugt den vollständigen dynamischen Marketing-, Produkt-, Saison- & Feiertags-Kontext
     * für den Funkenwerk Marketing & Content-Planer (inkl. automatischer Adaption neuer Shop-Produkte).
     */
    public function buildLiveMarketingPlanContext(?Carbon $now = null): array
    {
        $now = ($now ? $now->copy() : Carbon::now())->startOfDay();
        $year = (int) $now->year;

        $configurator = app(\App\Services\ConfiguratorService::class);
        $activeTheme = $configurator->getCurrentActiveTheme($now);
        $seasonKey = $configurator->getActiveSeasonKey($now);
        $seasonsDef = $configurator->getSeasonsDefinition();
        $currentSeasonMeta = $seasonsDef[$seasonKey] ?? [
            'key' => $seasonKey,
            'name' => ucfirst($seasonKey),
            'period' => '',
            'icon' => '✨',
            'animation_label' => 'Manufaktur',
        ];

        // 1. Feiertags-Timeline & aktiven / nächsten Anlass berechnen
        $definitions = $this->getEventDefinitions();
        $activeWindow = $this->resolveActiveOfferWindow($now);
        $upcomingHolidays = [];

        foreach ($definitions as $key => $meta) {
            $eventDate = $this->getHolidayDate($key, $year);
            $startDate = $eventDate->copy()->subDays((int) $meta['start_days_before'])->startOfDay();
            $endDate = $eventDate->copy()->subDays((int) $meta['end_days_before'])->endOfDay();

            if ($endDate->lt($now)) {
                $eventDate = $this->getHolidayDate($key, $year + 1);
                $startDate = $eventDate->copy()->subDays((int) $meta['start_days_before'])->startOfDay();
                $endDate = $eventDate->copy()->subDays((int) $meta['end_days_before'])->endOfDay();
            }

            $isActiveNow = $now->betweenIncluded($startDate, $endDate);
            $daysUntilStart = max(0, (int) $now->diffInDays($startDate, false));
            $daysUntilEvent = max(0, (int) $now->diffInDays($eventDate, false));

            $mottoData = $this->resolveHolidayMarketingMotto($key, $seasonKey);

            $upcomingHolidays[] = [
                'event_key' => $key,
                'name' => $meta['name'] ?? $meta['label'] ?? ucfirst($key),
                'badge_text' => $meta['badge_text'],
                'discount_percent' => (int) $meta['discount_percent'],
                'strategy_hint' => $meta['strategy_hint'],
                'product_slugs' => $meta['product_slugs'] ?? [],
                'event_date' => $eventDate->format('Y-m-d'),
                'event_date_formatted' => $eventDate->format('d.m.Y'),
                'start_date_formatted' => $startDate->format('d.m.Y'),
                'cutoff_date_formatted' => $endDate->format('d.m.Y'),
                'window_formatted' => $startDate->format('d.m.') . ' bis ' . $endDate->format('d.m.Y'),
                'is_active_now' => $isActiveNow,
                'days_until_start' => $daysUntilStart,
                'days_until_event' => $daysUntilEvent,
                'motto' => $mottoData['motto'],
                'hook_prefix' => $mottoData['hook_prefix'],
                'caption_addon' => $mottoData['caption_addon'],
                'hashtags' => $mottoData['hashtags'],
                '_sort_ts' => $isActiveNow ? 0 : $startDate->timestamp,
            ];
        }

        usort($upcomingHolidays, fn (array $a, array $b) => $a['_sort_ts'] <=> $b['_sort_ts']);
        foreach ($upcomingHolidays as &$hItem) {
            unset($hItem['_sort_ts']);
        }
        unset($hItem);

        $primaryHoliday = $upcomingHolidays[0] ?? null;
        $seasonMotto = $this->resolveSeasonFallbackMotto($seasonKey);

        // Wenn ein Feiertag aktiv ist oder in <= 28 Tagen startet, dominiert dessen Motto den Content-Fokus
        $useHolidayFocus = $primaryHoliday && ($primaryHoliday['is_active_now'] || $primaryHoliday['days_until_start'] <= 28);
        $effectiveMotto = $useHolidayFocus ? $primaryHoliday['motto'] : $seasonMotto['motto'];
        $effectiveHookPrefix = $useHolidayFocus ? $primaryHoliday['hook_prefix'] : $seasonMotto['hook_prefix'];
        $effectiveCaptionAddon = $useHolidayFocus ? $primaryHoliday['caption_addon'] : $seasonMotto['caption_addon'];
        $effectiveSeasonalHashtags = $useHolidayFocus ? $primaryHoliday['hashtags'] : $seasonMotto['hashtags'];

        // 2. Alle aktiven Produkte aus dem Shop laden und dynamisch für Marketing & Ads aufbereiten
        $rawProducts = Product::where('status', 'active')
            ->whereIn('type', ['physical', 'digital'])
            ->orderByRaw("CASE WHEN type = 'physical' THEN 0 ELSE 1 END")
            ->orderBy('price', 'desc')
            ->get();

        $shopProducts = [];
        foreach ($rawProducts as $product) {
            if ($product->type === 'digital' && $product->slug !== 'geschenkgutschein') {
                continue;
            }

            $landingPage = \App\Models\Marketing\MarketingLandingPage::syncForProduct($product);
            $landingSlug = $landingPage ? '/l/' . ltrim((string) $landingPage->slug, '/') : '/produkt/' . $product->slug;

            $settings = is_array($product->configurator_settings) ? $product->configurator_settings : [];
            $attributes = \App\Models\Marketing\MarketingLandingPage::extractEssentialAttributes($product);
            $materialLabel = (string) ($attributes['Material'] ?? ($attributes['Art'] ?? 'Manufaktur-Unikat'));

            $supportsWestpack = $product->supportsJewelryGiftBox();
            $usesRotaryRa3 = (($settings['overlay_type'] ?? '') === 'cylinder');
            $has3d = (bool) ($product->three_d_active ?? false);
            $hasBackSide = !empty($settings['has_back_side']);

            $offerCalc = $this->calculateProductOffer($product, 0);
            $basePriceCents = (int) $offerCalc['base_price_cents'];
            if ($product->slug === 'geschenkgutschein' && $basePriceCents <= 0) {
                $basePriceCents = 5000; // 50,00 € Referenzwert für Gutschein-Kalkulation
            }

            $directCostsCents = (int) $offerCalc['direct_costs_cents'];
            $paymentFeeCents = (int) $offerCalc['payment_fee_cents'];
            $netRevenueCents = (int) round($basePriceCents / 1.19);
            $netProfitBeforeAdsCents = max(0, $netRevenueCents - $directCostsCents - $paymentFeeCents);

            $targetCpaCents = max(400, min((int) round($basePriceCents * 0.22), (int) round($netProfitBeforeAdsCents * 0.50)));
            $maxCpaCents = max($targetCpaCents + 200, (int) round($netProfitBeforeAdsCents * 0.85));
            $netProfitAtTargetCpaCents = max(0, $netProfitBeforeAdsCents - $targetCpaCents);
            $netMarginPercent = $netRevenueCents > 0 ? round(($netProfitAtTargetCpaCents / $netRevenueCents) * 100, 1) : 0.0;

            $isAdZugpferd = ($product->type === 'physical' && $basePriceCents >= 3500 && $netProfitBeforeAdsCents >= 2000);

            // Dynamisches Setup für den Werkstatt-Dreh anhand der realen Produkteigenschaften
            $setupParts = ['Weiße Handschuhe', $product->name];
            if ($usesRotaryRa3) {
                $setupParts[] = 'xTool RA3 Rotationsmodul';
                $setupParts[] = 'Gegenlicht-Lampe';
            } else {
                $setupParts[] = 'xTool F2 Ultra UV';
            }

            $nameAndMatLower = mb_strtolower($product->name . ' ' . $materialLabel . ' ' . $product->slug);
            if (str_contains($nameAndMatLower, 'kristall')) {
                $setupParts[] = 'LED-Leuchtsockel';
            }
            if ($supportsWestpack) {
                $setupParts[] = 'Schwarze Westpack-Box';
            } elseif ($product->slug === 'geschenkgutschein') {
                $setupParts[] = 'Schwarzer Edel-Umschlag & Gold-Siegel';
            }

            $setupString = implode(' · ', array_unique($setupParts));

            // Dynamische Produkt-Hashtags + Saison-Hashtags
            $slugClean = str_replace(['-', '_'], '', (string) $product->slug);
            $baseHashtags = "#meinseelenfunke #{$slugClean} #handveredelt #unikat #geschenkidee";
            if (str_contains($nameAndMatLower, 'hund') || str_contains($nameAndMatLower, 'pfote')) {
                $baseHashtags = '#meinseelenfunke #seelenpfote #hundemarke #pfotenliebe #hundegeschenk';
            } elseif (str_contains($nameAndMatLower, 'kristall')) {
                $baseHashtags = '#meinseelenfunke #seelenkristall #3dglasgravur #erinnerungsstück #personalisiertegeschenke';
            } elseif (str_contains($nameAndMatLower, 'herz')) {
                $baseHashtags = '#meinseelenfunke #erinnerungsschmuck #goldenesherz #gravurschmuck #handgemacht';
            } elseif (str_contains($nameAndMatLower, 'weizen') || $usesRotaryRa3) {
                $baseHashtags = '#meinseelenfunke #weizenglas #männergeschenk #glasgravur #feierabendfunke';
            }

            // Empfohlenes Dreh-Format anhand der Produkt-DNA
            if ($usesRotaryRa3) {
                $recFormat = '360° Rotationsgravur (RA3) & Gegenlicht-Reveal';
                $defaultHook = "Wenn sich {$product->name} im Laser um 360° dreht ✨";
                $defaultClips = "Clip 1: 4s Einspannen ins RA3 Rotationsmodul · Clip 2: 6s 360°-Gravur mit Laser-Sound · Clip 3: 5s Gegenlicht-Reveal mit weißen Handschuhen";
            } elseif (str_contains($nameAndMatLower, 'kristall')) {
                $recFormat = 'Licht-Reveal im Dunkeln & Emotionale Story';
                $defaultHook = "Wenn aus einem Handyfoto ein leuchtendes 3D-Unikat im Kristall wird 🤍✨";
                $defaultClips = "Clip 1: 4s Dunkler Raum, Einschalten des LED-Sockels unter {$product->name} · Clip 2: 6s UV-Laser im Glas · Clip 3: 6s Nahaufnahme der 3D-Lichtbrechung";
            } elseif ($supportsWestpack) {
                $recFormat = 'White-Glove ASMR & Westpack-Box Reveal';
                $defaultHook = "Wenn eine persönliche Botschaft für die Ewigkeit in {$product->name} gebrannt wird ✨🤍";
                $defaultClips = "Clip 1: 4s Laser-Makro mit Funkenflug · Clip 2: 5s Abpinseln" . ($hasBackSide ? " & Wenden auf die Rückseite" : "") . " mit weißen Handschuhen · Clip 3: 5s Einlegen in die schwarze Westpack-Box";
            } else {
                $recFormat = 'Manufaktur White-Glove ASMR & Detail-Reveal';
                $defaultHook = "Handveredelt in unserer Manufaktur in Gifhorn: {$product->name} ✨";
                $defaultClips = "Clip 1: 4s Start beim ersten Laser-Impuls · Clip 2: 5s Detailaufnahme der feinen Gravur · Clip 3: 5s Qualitätsprüfung mit weißen Handschuhen";
            }

            $isHolidayFocusProduct = $primaryHoliday && in_array($product->slug, $primaryHoliday['product_slugs'] ?? [], true);

            $shopProducts[] = [
                'id' => (string) $product->id,
                'slug' => (string) $product->slug,
                'name' => (string) $product->name,
                'full_display_name' => $product->name . ' (' . number_format($basePriceCents / 100, 2, ',', '.') . ' €)',
                'type' => (string) $product->type,
                'landing_slug' => $landingSlug,
                'material_label' => $materialLabel,
                'supports_westpack_box' => $supportsWestpack,
                'uses_rotary_ra3' => $usesRotaryRa3,
                'has_3d_configurator' => $has3d,
                'has_back_side' => $hasBackSide,
                'base_price_cents' => $basePriceCents,
                'direct_costs_cents' => $directCostsCents,
                'payment_fee_cents' => $paymentFeeCents,
                'net_profit_before_ads_cents' => $netProfitBeforeAdsCents,
                'target_cpa_cents' => $targetCpaCents,
                'max_cpa_cents' => $maxCpaCents,
                'net_profit_at_target_cpa_cents' => $netProfitAtTargetCpaCents,
                'net_margin_percent' => $netMarginPercent,
                'is_ad_zugpferd' => $isAdZugpferd,
                'ad_role' => $isAdZugpferd ? 'ZUGPFERD (Paid Ads & Skalierung)' : 'ORGANISCH & RETARGETING',
                'is_holiday_focus' => $isHolidayFocusProduct,
                'setup_items' => $setupString,
                'hashtags' => trim($baseHashtags . ' ' . $effectiveSeasonalHashtags),
                'recommended_format' => $recFormat,
                'default_hook' => $defaultHook,
                'default_clips' => $defaultClips,
            ];
        }

        return [
            'generated_at' => Carbon::now()->toIso8601String(),
            'season' => [
                'key' => $seasonKey,
                'name' => $currentSeasonMeta['name'] ?? ucfirst($seasonKey),
                'icon' => $currentSeasonMeta['icon'] ?? '✨',
                'period' => $currentSeasonMeta['period'] ?? '',
                'active_theme_label' => $activeTheme['event_name'] ?? ($currentSeasonMeta['name'] ?? 'Manufaktur'),
                'reason_type' => $activeTheme['reason_type'] ?? 'season',
                'effective_motto' => $effectiveMotto,
                'effective_hook_prefix' => $effectiveHookPrefix,
                'effective_caption_addon' => $effectiveCaptionAddon,
                'effective_hashtags' => $effectiveSeasonalHashtags,
            ],
            'active_holiday' => $activeWindow,
            'primary_holiday' => $primaryHoliday,
            'holidays_timeline' => $upcomingHolidays,
            'products_count' => count($shopProducts),
            'products' => $shopProducts,
        ];
    }

    /**
     * Liefert für jeden der 11 Shop-Feiertage das passende emotionale Content-Motto, Hook-Präfix & Hashtags.
     */
    protected function resolveHolidayMarketingMotto(string $eventKey, string $seasonKey): array
    {
        return match ($eventKey) {
            'valentines' => [
                'motto' => '💘 Valentinstag: „Schenke keine Blumen, die verwelken. Schenke einen Moment für die Ewigkeit.“',
                'hook_prefix' => 'Suchst du noch ein Valentinstags-Geschenk, das wirklich Gänsehaut auslöst? 💘✨',
                'caption_addon' => '💘 Valentinstags-Tipp: Jetzt rechtzeitig dein Unikat sichern, damit es pünktlich zum 14.02. in der schwarzen Geschenkbox bereitliegt!',
                'hashtags' => '#valentinstagsgeschenk #geschenkfürsie #geschenkfürhn #liebesbeweis',
            ],
            'womens_day' => [
                'motto' => '💐 Weltfrauentag: „Kleine Gesten der Wertschätzung für die wichtigsten Frauen in deinem Leben.“',
                'hook_prefix' => 'Zum Weltfrauentag am 08.03.: Ein Unikat für eine besondere Frau 💐✨',
                'caption_addon' => '💐 Zum Weltfrauentag: Zeige deine Wertschätzung mit einer persönlichen Wunschgravur aus unserer Manufaktur.',
                'hashtags' => '#weltfrauentag #frauentagsgeschenk #wertschätzung #geschenkfürfrauen',
            ],
            'easter' => [
                'motto' => '🐰 Ostern & Frühling: „Persönliche Erinnerungsstücke im Osternest statt nur Schokolade.“',
                'hook_prefix' => 'Was dieses Jahr ins Osternest kommt, hält ein Leben lang 🐰🌸',
                'caption_addon' => '🐰 Oster-Manufaktur-Aktion: Gestalte jetzt dein persönliches Ostergeschenk für die Familie!',
                'hashtags' => '#ostergeschenk #osternest #frühlingsgefühle #familiengeschenk',
            ],
            'mothers_day' => [
                'motto' => '💝 Muttertag: „Für die Frau, die immer für uns da war: Mamas Herzstück in Gold & Kristall.“',
                'hook_prefix' => 'Wenn Mama beim Auspacken am Muttertag die Tränen kommen 💝🥹',
                'caption_addon' => '💝 Muttertags-Vorlauf: Sichere dir jetzt rechtzeitig vor Annahmeschluss Mamas ganz persönliches Erinnerungsstück!',
                'hashtags' => '#muttertagsgeschenk #besterpapa #bestemama #dankemama',
            ],
            'fathers_day' => [
                'motto' => '🍺 Vatertag & Männertag: „Charakterstarke Unikate mit 360°-Gravur für echte Helden.“',
                'hook_prefix' => 'Das einzige Vatertags-Geschenk, das Papa wirklich jeden Abend stolz nutzt 🍺🔥',
                'caption_addon' => '🍺 Vatertags-Special: Ob 360°-Weizenglas oder massiver Seelen-Talisman – jetzt im 3D-Konfigurator für Papa gestalten!',
                'hashtags' => '#vatertagsgeschenk #männergeschenk #besterpapa #bierglasgravur',
            ],
            'sale_summer' => [
                'motto' => '☀️ Sommer- & Hochzeits-Saison: „Goldene Sommer-Momente, Hochzeiten & Gartenfeste veredelt.“',
                'hook_prefix' => 'Das perfekte Hochzeits- & Sommergeschenk aus unserer Manufaktur ☀️💍',
                'caption_addon' => '☀️ Sommer- & Hochzeits-Saison: Jedes Stück wird frisch in Gifhorn für deinen besonderen Anlass gefertigt.',
                'hashtags' => '#hochzeitsgeschenk #sommerfest #personalisiertegeschenke #manufaktur',
            ],
            'halloween' => [
                'motto' => '🕯️ Herbst & Lichterzeit: „Wenn die Tage dunkler werden, bringen wir Erinnerungen im Kristall zum Leuchten.“',
                'hook_prefix' => 'Wenn es draußen dunkel wird und diese Erinnerung plötzlich aufleuchtet 🕯️✨',
                'caption_addon' => '🍂 Herbst- & Lichter-Aktion: Bringe deine schönsten Momente in der dunklen Jahreszeit zum Strahlen.',
                'hashtags' => '#lichterzeit #herbstdeko #erinnerungslicht #gedenkgeschenk',
            ],
            'advent_1' => [
                'motto' => '🕯️ Advents- & Vorweihnachtszeit: „Frühbucher-Ruhe genießen: Weihnachtsgeschenke ohne Dezember-Stress.“',
                'hook_prefix' => 'Wer jetzt im Advent bestellt, hat an Weihnachten das emotionalste Geschenk unter dem Baum 🕯️🎄',
                'caption_addon' => '🕯️ Advents-Frühbucher-Tipp: Jetzt entspannt vor dem großen Weihnachts-Ansturm dein Unikat in Gifhorn fertigen lassen!',
                'hashtags' => '#adventszeit #weihnachtsgeschenke #frühbucher #geschenkevonherzen',
            ],
            'christmas' => [
                'motto' => '🎄 Weihnachten in der Manufaktur: „Geschenke, die unter dem Weihnachtsbaum für echte Freudentränen sorgen.“',
                'hook_prefix' => 'Das Weihnachtsgeschenk, bei dem unter dem Baum garantiert kein Auge trocken bleibt 🎄🥹',
                'caption_addon' => '🎄 Weihnachts-Manufaktur Gifhorn: Bitte beachte unseren Bestell-Annahmeschluss für pünktliche Lieferung bis Heiligabend!',
                'hashtags' => '#weihnachtsgeschenk #geschenkideeweihnachten #freudentränen #heiligabend',
            ],
            'new_year' => [
                'motto' => '🎆 Neujahr & Jahresrückblick: „Dein schönster Moment des Jahres für immer festgehalten.“',
                'hook_prefix' => 'Welchen Moment aus diesem Jahr möchtest du für immer festhalten? 🎆✨',
                'caption_addon' => '🎆 Neujahrs-Impuls: Halte deinen wichtigsten Meilenstein oder Glücksmoment für das neue Jahr fest.',
                'hashtags' => '#neujahr #jahresrückblick #glücksbringer #meilenstein',
            ],
            'sale_winter' => [
                'motto' => '❄️ Winter-Atelier: „Gutschein-Einlösung & edle Seelenwärmer für kalte Wintertage.“',
                'hook_prefix' => 'Du hast zu Weihnachten einen Gutschein bekommen? So entsteht daraus dein Unikat ❄️🤍',
                'caption_addon' => '❄️ Winter-Atelier: Löse jetzt deinen Weihnachts-Gutschein ein oder gestalte dein persönliches Winter-Unikat.',
                'hashtags' => '#winterzeit #gutscheineinlösen #wunschgravur #seelenwärmer',
            ],
            default => $this->resolveSeasonFallbackMotto($seasonKey),
        };
    }

    /**
     * Fallback-Motto anhand der 4 Shop-Jahreszeiten ('ostern', 'sommer', 'herbst', 'winter').
     */
    protected function resolveSeasonFallbackMotto(string $seasonKey): array
    {
        return match ($seasonKey) {
            'ostern' => [
                'motto' => '🌸 Frühlings-Saison: „Neuanfang, Taufe, Kommunion & Herzensmenschen feiern.“',
                'hook_prefix' => 'Ein persönliches Erinnerungsstück für die schönsten Frühlings-Anlässe 🌸✨',
                'caption_addon' => '🌸 Frühlings-Manufaktur: Perfekt für Geburtstag, Taufe, Hochzeit oder einfach als Liebesbeweis zwischendurch.',
                'hashtags' => '#frühling #taufgeschenk #geburtstagsgeschenk #lieblingsmensch',
            ],
            'sommer' => [
                'motto' => '☀️ Sommer- & Feierabend-Saison: „Hochzeiten, Jubiläen & gesellige Abende mit Charakter.“',
                'hook_prefix' => 'Für unvergessliche Sommerabende und besondere Menschen ☀️✨',
                'caption_addon' => '☀️ Sommer-Atelier Gifhorn: Handveredelt für Hochzeiten, runde Geburtstage und laue Feierabende.',
                'hashtags' => '#sommergeschenk #hochzeit #feierabend #jubiläumsgeschenk',
            ],
            'herbst' => [
                'motto' => '🍂 Goldener Herbst & Lichterzeit: „Wärme, Geborgenheit & leuchtende Erinnerungen für drinnen.“',
                'hook_prefix' => 'Wenn es draußen stürmt, zählen die Erinnerungen, die uns von innen wärmen 🍂🕯️',
                'caption_addon' => '🍂 Herbst- & Lichterzeit: Jetzt schon entspannt an die ersten Geschenke für die Festtage denken!',
                'hashtags' => '#goldenerherbst #lichterzeit #erinnerungsschmuck #gemütlichkeit',
            ],
            default => [
                'motto' => '❄️ Winter- & Festtags-Saison: „Zeit für tiefe Dankbarkeit und bleibende Erinnerungen.“',
                'hook_prefix' => 'Manche Erinnerungen verdienen einen Ehrenplatz für die Ewigkeit ❄️✨',
                'caption_addon' => '❄️ Winter-Manufaktur Gifhorn: Mit weißen Handschuhen gefertigt und bereit zum Verschenken.',
                'hashtags' => '#winter #geschenkvonherzen #erinnerungsstück #manufaktur',
            ],
        };
    }
}

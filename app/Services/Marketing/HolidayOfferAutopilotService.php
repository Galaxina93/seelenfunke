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
        $capacityLevel = (int) Cache::get('shop_capacity_level', 0);
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
                'end_days_before' => -12, // 15.01. bis 27.01.
                'product_slugs' => ['seelen-kristall', 'seelen-anhaenger', 'weizenglas-personalisiert'],
                'strategy_hint' => 'Inventur-Aktion Mitte Januar (15.01. – 27.01.) zur Auslastung der Werkstatt im ruhigen Januar, bevor die Valentinstags-Phase startet.',
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
            'sale_winter' => Carbon::create($year, 1, 15)->startOfDay(), // 15.01., damit keine Kollision mit Neujahr (01.01.) entsteht
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

        if (!empty($product->compare_at_price) && (int) $product->compare_at_price > (int) $product->price) {
            return (int) $product->compare_at_price;
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
        $marketingPercent = (float) ($product->marketing_cost_percent ?? 15.0);

        // 3. Ziel-Aktionspreis berechnen und verkaufspsychologisch auf ,90 € runden
        $rawDiscountedCents = (int) round($basePriceCents * (1 - ($targetDiscountPercent / 100)));
        $salePriceCents = $this->roundToPsychologicalPrice($rawDiscountedCents, $basePriceCents);

        // 4. Harte Margen-Schutzmauer prüfen (Mindestens 45 % Netto-Marge nach ALLEN Kosten)
        $marginProtected = false;
        while ($salePriceCents < $basePriceCents) {
            $metrics = $this->computeMarginMetrics($salePriceCents, $directCostsCents, $taxRate, $marketingPercent);
            if ($metrics['net_margin_percent'] >= self::MIN_NET_MARGIN_PERCENT) {
                break;
            }
            $marginProtected = true;
            $salePriceCents += 100; // In 1,00-€-Schritten anheben, bis die 45-%-Marge sicher gehalten wird
        }

        if ($salePriceCents >= $basePriceCents) {
            $salePriceCents = $basePriceCents;
        }

        $finalMetrics = $this->computeMarginMetrics($salePriceCents, $directCostsCents, $taxRate, $marketingPercent);
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

    private function computeMarginMetrics(int $grossCents, int $directCostsCents, float $taxRate, float $marketingPercent): array
    {
        $netCents = (int) round($grossCents / (1 + ($taxRate / 100)));
        $marketingCostCents = (int) round($netCents * ($marketingPercent / 100));
        $paymentFeeCents = (int) round(($grossCents * 0.025) + 30); // ~2,5 % + 0,30 € Stripe/PayPal Puffer

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
                    $restoredBasePrice = (int) $settings['autopilot_base_price'];
                    unset($settings['autopilot_base_price'], $settings['autopilot_event_key'], $settings['autopilot_campaign_label'], $settings['autopilot_badge_text']);

                    $product->price = $restoredBasePrice;
                    $product->compare_at_price = null;
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
}

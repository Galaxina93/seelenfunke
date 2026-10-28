<?php

namespace App\Models\Marketing;

use App\Models\Delivery\DeliverySetting;
use App\Models\Delivery\DeliveryTime;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Services\ConfiguratorService;
use App\Services\Marketing\HolidayOfferAutopilotService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingLandingPage extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'slug',
        'title',
        'headline',
        'sales_copy',
        'cta_text',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Liest Unternehmens-, Standort- und Live-Lieferzeit-Daten dynamisch aus `shop_setting(...)`
     * sowie `DeliverySetting` / `DeliveryTime` (inkl. Urlaubs-/Krankheitsmodus & Ampelfarbe).
     */
    public static function resolveShopContext(): array
    {
        $companyName = trim((string) shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'))) ?: 'Mein Seelenfunke';
        $companyCity = trim((string) shop_setting('company_city', 'Gifhorn')) ?: 'Gifhorn';
        $companyCountryCode = strtoupper(trim((string) shop_setting('company_country', 'DE'))) ?: 'DE';
        $activeCountries = shop_setting('active_countries', ['DE' => 'Deutschland']);
        $companyCountryName = (is_array($activeCountries) && !empty($activeCountries[$companyCountryCode]))
            ? (string) $activeCountries[$companyCountryCode]
            : 'Deutschland';

        $minDays = 3;
        $maxDays = 5;
        $deliveryDaysShort = '3-5 Werktage';
        $deliveryDateRange = '';
        $deliveryFullText = 'Lieferzeit: 3-5 Werktage';
        $deliveryColor = 'green';
        $deliveryDescription = null;
        $isVacation = false;
        $isSick = false;

        try {
            $details = DeliverySetting::getDeliveryDetails();
            $activeTime = DeliveryTime::where('is_active', true)->first();

            $minDays = (int) ($details['min_days'] ?? 3);
            $maxDays = (int) ($details['max_days'] ?? 5);
            $isVacation = (bool) ($details['is_vacation'] ?? false);
            $isSick = (bool) ($details['is_sick'] ?? false);
            $deliveryDateRange = trim((string) ($details['date_range_formatted'] ?? ''));
            $deliveryFullText = trim((string) ($details['full_text'] ?? "Lieferzeit: {$minDays}-{$maxDays} Werktage"));
            $deliveryDaysShort = ($isVacation && $deliveryDateRange !== '')
                ? $deliveryDateRange
                : "{$minDays}-{$maxDays} Werktage";

            if ($activeTime) {
                $deliveryColor = (string) ($activeTime->color ?: 'green');
                $deliveryDescription = $activeTime->description ?: null;
            }
        } catch (\Throwable $e) {
            // Fallback auf Standardwerte, falls Tabellen in isolierten Tests nicht vorhanden sind
        }

        return [
            'company_name'         => $companyName,
            'company_city'         => $companyCity,
            'company_country_code' => $companyCountryCode,
            'company_country_name' => $companyCountryName,
            'min_days'             => $minDays,
            'max_days'             => $maxDays,
            'delivery_days_short'  => $deliveryDaysShort,
            'delivery_date_range'  => $deliveryDateRange,
            'delivery_full_text'   => $deliveryFullText,
            'delivery_color'       => $deliveryColor,
            'delivery_description' => $deliveryDescription,
            'is_vacation'          => $isVacation,
            'is_sick'              => $isSick,
        ];
    }

    /**
     * Ermittelt die identische dynamische Saison-/Feiertags-Hintergrundgrafik wie unter "Unsere Kollektion" (/shop).
     */
    public static function resolveCollectionBannerUrl(?string $explicitHoliday = null): string
    {
        $seasonKey = app(ConfiguratorService::class)->getActiveSeasonKey();
        $seasonBannerMap = [
            'ostern' => 'fruehling.webp',
            'sommer' => 'sommer.webp',
            'herbst' => 'herbst.webp',
            'winter' => 'winter.webp',
        ];

        $defaultFile = $seasonBannerMap[$seasonKey] ?? 'herbst.webp';

        if ($explicitHoliday !== null && trim($explicitHoliday) !== '') {
            $norm = mb_strtolower(trim($explicitHoliday));
            $filteredBanner = match ($norm) {
                'valentinstag', 'valentines' => 'winter_valentinstag.webp',
                'ostern', 'easter', 'taufe', 'einschulung' => 'fruehling_ostern.webp',
                'muttertag', 'vatertag', 'hochzeit', 'jubilaeum', 'jubiläum', 'weltfrauentag' => 'fruehling_muttertag.webp',
                'weihnachten', 'advent', 'nikolaus' => 'winter_weihnachten.webp',
                'halloween', 'herbst', 'trauer' => 'herbst_halloween.webp',
                'geburtstag', 'neujahr', 'silvester' => 'winter_neujahr.webp',
                default => $defaultFile,
            };

            return asset('shop/frontend/banners/' . $filteredBanner);
        }

        try {
            $autopilot = app(HolidayOfferAutopilotService::class);
            $activeWindow = $autopilot->resolveActiveOfferWindow();

            if ($activeWindow && !empty($activeWindow['event_key'])) {
                $eventBanner = match ($activeWindow['event_key']) {
                    'valentines'  => 'winter_valentinstag.webp',
                    'womens_day', 'mothers_day', 'fathers_day' => 'fruehling_muttertag.webp',
                    'easter'      => 'fruehling_ostern.webp',
                    'sale_summer' => 'sommer.webp',
                    'halloween'   => 'herbst_halloween.webp',
                    'advent_1', 'christmas' => 'winter_weihnachten.webp',
                    'new_year'    => 'winter_neujahr.webp',
                    'sale_winter' => 'winter.webp',
                    default       => $defaultFile,
                };

                return asset('shop/frontend/banners/' . $eventBanner);
            }
        } catch (\Throwable $e) {
            // Fallback auf Jahreszeiten-Banner
        }

        return asset('shop/frontend/banners/' . $defaultFile);
    }

    /**
     * Liest ausschließlich die kundenrelevanten Produkteigenschaften aus der JSON-Spalte `attributes`
     * (verhindert den Zugriff auf Model::$attributes, synchronisiert `Lieferzeit` mit der Shop-Logik
     * und begrenzt auf die wichtigsten Kernmerkmale).
     */
    public static function extractEssentialAttributes(Product $product): array
    {
        $rawAttributes = $product->getAttribute('attributes');
        if (is_string($rawAttributes)) {
            $decoded = json_decode($rawAttributes, true);
            $rawAttributes = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rawAttributes)) {
            return [];
        }

        $priorityOrder = [
            'Material',
            'Volumen',
            'Größe',
            'Form',
            'Farbe',
            'Pflege',
            'Technik',
            'Format',
            'Seiten',
            'Assets',
            'Sprache',
            'Art',
            'Auslieferung',
            'Gültigkeit',
            'Dauer',
            'Ort',
            'Lieferzeit',
        ];

        $cleaned = [];
        foreach ($rawAttributes as $key => $val) {
            if (!is_string($key) || is_array($val) || is_object($val)) {
                continue;
            }
            $strVal = trim((string) $val);
            if ($strVal === '') {
                continue;
            }
            $cleaned[$key] = $strVal;
        }

        // Falls "Lieferzeit" als Attribut existiert, immer dynamisch aus der aktuellen Shop-Lieferlogik überschreiben
        if (isset($cleaned['Lieferzeit']) && ($product->type ?: 'physical') === 'physical' && $product->slug !== 'geschenkgutschein') {
            $ctx = self::resolveShopContext();
            $cleaned['Lieferzeit'] = $ctx['delivery_days_short']
                . ($ctx['delivery_date_range'] !== '' && !$ctx['is_vacation'] ? " ({$ctx['delivery_date_range']})" : '');
        }

        $ordered = [];
        foreach ($priorityOrder as $prioKey) {
            if (isset($cleaned[$prioKey])) {
                $ordered[$prioKey] = $cleaned[$prioKey];
            }
        }

        foreach ($cleaned as $key => $val) {
            if (!isset($ordered[$key])) {
                $ordered[$key] = $val;
            }
        }

        return array_slice($ordered, 0, 5, true);
    }

    /**
     * Bereinigt Produktbeschreibungen von wettbewerbsrechtlich heiklen Absolutheits-/Garantiebegriffen (UWG / § 479 BGB).
     */
    public static function sanitizeLegalCopy(string $text): string
    {
        $replacements = [
            '/\bgarantiert\s+/iu'          => '',
            '/\bzu\s+100%\s+/iu'           => '',
            '/\b100%\s+spülmaschinenfest/iu' => 'spülmaschinengeeignet',
            '/\bspülmaschinenfest/iu'      => 'spülmaschinengeeignet',
            '/\bunverwüstlich\b/iu'        => 'widerstandsfähig',
            '/\babsolut\s+präzise/iu'      => 'präzise',
        ];

        return trim((string) preg_replace(array_keys($replacements), array_values($replacements), $text));
    }

    /**
     * Erzeugt intelligente, produktindividuelle und rechtssichere Standard-Marketingtexte anhand von Produkttyp,
     * Material, Konfigurator-Einstellungen, Beschreibung und `shop_setting(...)`.
     */
    public static function generateDefaultCopy(Product $product): array
    {
        $ctx = self::resolveShopContext();
        $companyName = $ctx['company_name'];
        $companyCity = $ctx['company_city'];

        $name = trim((string) $product->name);
        $slug = trim((string) $product->slug);
        $type = (string) ($product->type ?: 'physical');
        $isPersonalizable = $product->isPersonalizable();
        $settings = is_array($product->getAttribute('configurator_settings')) ? $product->getAttribute('configurator_settings') : [];
        $attributes = self::extractEssentialAttributes($product);
        $material = mb_strtolower((string) ($attributes['Material'] ?? ($settings['material_type'] ?? '')));
        $overlayType = (string) ($settings['overlay_type'] ?? 'plane');
        $hasBackSide = !empty($settings['has_back_side']);

        $rawDesc = self::sanitizeLegalCopy((string) ($product->description ?: $product->short_description ?: ''));

        // 1. Geschenkgutschein
        if ($slug === 'geschenkgutschein') {
            return [
                'title'      => "{$name} | {$companyName} Manufaktur",
                'headline'   => 'Freude schenken mit Stil: Der Seelenfunken Wertgutschein',
                'sales_copy' => $rawDesc ?: 'Verschenke besondere Momente mit unserem Seelenfunken Geschenkgutschein. Wähle flexibel deinen Wunschbetrag, füge eine persönliche Widmung hinzu und erhalte ihn wahlweise digital per E-Mail oder als gedruckte Klappkarte.',
                'cta_text'   => 'Jetzt Gutschein gestalten',
            ];
        }

        // 2. Bekannte Manufaktur-Hauptprodukte mit rechtssicherem Copywriting
        if ($slug === 'seelen-kristall' || str_contains($name, 'Kristall')) {
            return [
                'title'      => "{$name} | Personalisiertes K9-Kristallglas aus der Manufaktur",
                'headline'   => 'Brillante Lichtbrechung für besondere Erinnerungen: ' . $name,
                'sales_copy' => $rawDesc ?: 'Halte besondere Momente stilvoll fest – mit einem geschliffenen K9-Kristallglas. Dank unserer feinen Lasergravur und der interaktiven 3D-Vorschau gestaltest du dein persönliches Unikat online.',
                'cta_text'   => 'Jetzt Unikat gestalten',
            ];
        }

        if ($slug === 'seelen-anhaenger' || str_contains($name, 'Anhänger')) {
            return [
                'title'      => "{$name} | Massives Aluminium-Herz mit beidseitiger Wunschgravur",
                'headline'   => 'Wertschätzung zum Mitnehmen: ' . $name,
                'sales_copy' => $rawDesc ?: 'Der Seelenanhänger verbindet eine angenehme, massive Haptik aus Aluminium mit einer präzisen, beidseitigen Lasergravur für Namen, Daten, Symbole oder Koordinaten.',
                'cta_text'   => 'Jetzt Unikat gestalten',
            ];
        }

        if ($slug === 'weizenglas-personalisiert' || str_contains($name, 'Feierabend')) {
            return [
                'title'      => "{$name} | Weizenglas mit 360° Rundum-Lasergravur",
                'headline'   => 'Dein persönliches Glas mit feiner 360°-Lasergravur: ' . $name,
                'sales_copy' => $rawDesc ?: 'Ein klassisch geformtes Kristall-Weizenbierglas, das durch eine präzise 360° Rundum-Lasergravur mit deinem Wunschtext oder Motiv individuell in unserer Manufaktur veredelt wird.',
                'cta_text'   => 'Jetzt Unikat gestalten',
            ];
        }

        // 3. Dynamische Adaption für alle weiteren & neu angelegten Produkte
        if ($type === 'digital') {
            $headline = 'Digital & direkt nach Bestellung abrufbar: ' . $name;
            $cta = 'Jetzt Download ansehen';
            $fallbackCopy = "Erhalte {$name} als digitales Produkt mit direktem Download nach Abschluss der Bestellung – versandkostenfrei und auf gängigen Endgeräten nutzbar.";
        } elseif ($type === 'service') {
            $headline = 'Persönliche Manufaktur-Dienstleistung: ' . $name;
            $cta = 'Jetzt Service anfragen';
            $fallbackCopy = "Profitiere bei {$name} von persönlicher fachlicher Betreuung aus unserer Manufaktur in {$companyCity} – individuell abgestimmt auf dein Vorhaben.";
        } elseif ($isPersonalizable) {
            if ($overlayType === 'cylinder') {
                $headline = 'Dein persönliches Unikat mit 360°-Lasergravur: ' . $name;
            } elseif ($hasBackSide) {
                $headline = 'Beidseitig individuell für dich graviert: ' . $name;
            } elseif (str_contains($material, 'glas') || str_contains($material, 'kristall') || str_contains($material, 'glass')) {
                $headline = 'Edle Glas- & Kristallveredelung nach Wunsch: ' . $name;
            } elseif (str_contains($material, 'metall') || str_contains($material, 'alu') || str_contains($material, 'edelstahl') || str_contains($material, 'metal')) {
                $headline = 'Spürbare Wertigkeit mit deiner Wunschgravur: ' . $name;
            } elseif (str_contains($material, 'schiefer') || str_contains($material, 'holz') || str_contains($material, 'natur')) {
                $headline = 'Charakterstarkes Natur-Unikat mit Lasergravur: ' . $name;
            } else {
                $headline = 'Handveredeltes Manufaktur-Unikat: ' . $name;
            }

            $cta = 'Jetzt Unikat gestalten';
            $fallbackCopy = "Gestalte {$name} direkt online mit deinem Wunschtext oder Motiv. Jedes Einzelstück wird in unserer Manufaktur in {$companyCity} per Lasergravur veredelt und sorgfältig verpackt.";
        } else {
            $headline = "Aus unserer Manufaktur in {$companyCity}: {$name}";
            $cta = 'Jetzt im Shop ansehen';
            $fallbackCopy = "Entdecke {$name} aus unserer Manufaktur in {$companyCity} – sorgfältig geprüft und sicher verpackt zu dir nach Hause geliefert.";
        }

        $salesCopy = trim($rawDesc !== '' ? $rawDesc : $fallbackCopy);

        return [
            'title'      => ($product->seo_title ?: $name) . " | {$companyName}",
            'headline'   => $headline,
            'sales_copy' => $salesCopy,
            'cta_text'   => $cta,
        ];
    }

    /**
     * Stellt sicher, dass für ein Produkt eine passende MarketingLandingPage existiert
     * und hält Entwurfs-Slugs/Texte bei neu angelegten Produkten automatisch synchron.
     */
    public static function syncForProduct(Product $product, bool $forceRegenerateCopy = false): ?self
    {
        $rawSlug = trim((string) $product->slug);
        if ($rawSlug === '' || str_starts_with($rawSlug, 'draft-')) {
            return null;
        }

        $defaults = self::generateDefaultCopy($product);
        $existing = self::where('product_id', $product->id)->first();

        if (!$existing) {
            $targetSlug = $rawSlug;
            if (self::where('slug', $targetSlug)->exists()) {
                $targetSlug = $rawSlug . '-' . substr((string) $product->id, 0, 6);
            }

            return self::create([
                'product_id' => $product->id,
                'slug'       => $targetSlug,
                'title'      => $defaults['title'],
                'headline'   => $defaults['headline'],
                'sales_copy' => $defaults['sales_copy'],
                'cta_text'   => $defaults['cta_text'],
                'status'     => $product->status === 'active' ? 'active' : 'draft',
            ]);
        }

        $needsCopyUpdate = $forceRegenerateCopy
            || empty(trim((string) $existing->headline))
            || empty(trim((string) $existing->sales_copy))
            || str_contains((string) $existing->title, 'Neues Produkt ')
            || str_contains((string) $existing->headline, 'Neues Produkt ')
            || str_starts_with((string) $existing->headline, 'Entdecke jetzt:');

        $updates = [];

        if ($needsCopyUpdate) {
            $updates['title'] = $defaults['title'];
            $updates['headline'] = $defaults['headline'];
            $updates['sales_copy'] = $defaults['sales_copy'];
            $updates['cta_text'] = $defaults['cta_text'];
        } elseif (mb_strlen((string) $existing->cta_text) > 28) {
            // Verhindert Zeilenumbrüche durch zu lange alte CTA-Texte
            $updates['cta_text'] = $defaults['cta_text'];
        }

        if (str_starts_with((string) $existing->slug, 'draft-') && !self::where('slug', $rawSlug)->where('id', '!=', $existing->id)->exists()) {
            $updates['slug'] = $rawSlug;
        }

        if (!empty($updates)) {
            $existing->update($updates);
        }

        return $existing;
    }

    /**
     * Erzeugt das dynamische, rechtssichere Darstellungs-Profil (Badges, 3 Highlight-Kacheln, Story-Punkte, CTA-Hinweise)
     * passend zu Produkttyp, Material, Eigenschaften sowie Live-Shop-Settings (`shop_setting`, `DeliverySetting`).
     */
    public static function buildDynamicProfile(
        Product $product,
        ?array $heroImageMeta = null,
        ?ProductTemplate $activeSeasonalTemplate = null
    ): array {
        $ctx = self::resolveShopContext();
        $companyName = $ctx['company_name'];
        $companyCity = $ctx['company_city'];
        $companyCountryName = $ctx['company_country_name'];
        $deliveryTime = $ctx['delivery_days_short'];
        $deliveryFullText = $ctx['delivery_full_text'];

        $slug = (string) $product->slug;
        $type = (string) ($product->type ?: 'physical');
        $isVoucher = $slug === 'geschenkgutschein';
        $isDigital = $type === 'digital' && !$isVoucher;
        $isService = $type === 'service';
        $isPersonalizable = $product->isPersonalizable();
        $has3d = (bool) ($product->three_d_active ?? false);

        $settings = is_array($product->getAttribute('configurator_settings')) ? $product->getAttribute('configurator_settings') : [];
        $attributes = self::extractEssentialAttributes($product);
        $materialAttr = (string) ($attributes['Material'] ?? '');
        $materialLower = mb_strtolower($materialAttr . ' ' . ($settings['material_type'] ?? ''));
        $overlayType = (string) ($settings['overlay_type'] ?? 'plane');
        $hasBackSide = !empty($settings['has_back_side']);

        if ($isVoucher) {
            $heroBadge = 'Wertgutschein • Per E-Mail (PDF) oder Postkarte';
            $heroMiniBadges = [
                ['icon' => 'gift', 'title' => '5 € – 1.000 € wählbar', 'subtitle' => 'Dein Wunschbetrag in Euro'],
                ['icon' => 'bolt', 'title' => 'PDF & Postkarte', 'subtitle' => 'Zwei Versandarten'],
                ['icon' => 'heart', 'title' => 'Persönliche Widmung', 'subtitle' => 'Individueller Grußtext'],
            ];
            $highlightsSubtitle = "Ob als kurzfristiges Geschenk per E-Mail oder als gedruckte Klappkarte – der Wertgutschein von {$companyName} lässt dem Beschenkten die freie Auswahl.";
            $highlights = [
                [
                    'icon'  => 'sparkles',
                    'title' => 'Dein Wunschbetrag & Grußtext',
                    'text'  => 'Wähle einen beliebten Festbetrag (10 €, 25 €, 50 €, 100 €) oder gib deinen Wunschbetrag flexibel von 5 € bis 1.000 € (in 5er-Schritten) ein und ergänze deine persönliche Grußbotschaft.',
                ],
                [
                    'icon'  => 'mail',
                    'title' => 'Digital oder per Post',
                    'text'  => 'Nach Zahlungseingang als druckfertiges PDF per E-Mail verfügbar oder optional als gedruckte Klappkarte bestellbar.',
                ],
                [
                    'icon'  => 'shield',
                    'title' => '3 Jahre gültig (§ 195 BGB)',
                    'text'  => 'Einlösbar für das gesamte Sortiment im Online-Shop. Ein eventuelles Restguthaben bleibt für Folgebestellungen erhalten.',
                ],
            ];
            $storyKicker = 'Freie Auswahl schenken';
            $storyHeadline = 'Wenn der Beschenkte sein Unikat selbst gestalten möchte';
            $storyPoints = [
                'Wählbarer Wunschbetrag von 5,00 € bis 1.000,00 € (in 5er-Schritten)',
                'Als PDF zum Selbstausdrucken oder als gedruckte Karte erhältlich',
                'Restguthaben bleibt innerhalb der Gültigkeitsdauer erhalten',
                'Gültig für 3 Jahre ab Ende des Kaufjahres (§ 195 BGB)',
            ];
            $ctaSubtext = 'Digital per E-Mail (versandkostenfrei) oder per Post • 3 Jahre gültig';
        } elseif ($isDigital) {
            $heroBadge = 'Digitales Produkt • Direkt-Download nach Zahlung';
            $heroMiniBadges = [
                ['icon' => 'download', 'title' => 'Sofort-Download', 'subtitle' => 'Nach Zahlungseingang'],
                ['icon' => 'device', 'title' => 'Digitales Format', 'subtitle' => $attributes['Format'] ?? 'Flexibel nutzbar'],
                ['icon' => 'check', 'title' => 'Versandkostenfrei', 'subtitle' => 'Digitale Bereitstellung'],
            ];
            $highlightsSubtitle = 'Direkter digitaler Zugriff nach Bestellabschluss – übersichtlich aufbereitet und ohne Versandkosten.';
            $highlights = [
                [
                    'icon'  => 'download',
                    'title' => 'Direkter Download',
                    'text'  => 'Nach erfolgreichem Zahlungseingang stehen dir die digitalen Dateien direkt im Kundenkonto zum Download bereit.',
                ],
                [
                    'icon'  => 'device',
                    'title' => 'Strukturiert aufbereitet',
                    'text'  => !empty($attributes['Format'])
                        ? "Bereitgestellt im Format {$attributes['Format']} für die direkte Nutzung auf gängigen Endgeräten."
                        : 'Übersichtlich strukturiert und auf gängigen Endgeräten wie PC, Tablet oder Smartphone nutzbar.',
                ],
                [
                    'icon'  => 'shield',
                    'title' => 'Versandkostenfreie Lieferung',
                    'text'  => 'Da die Bereitstellung rein digital erfolgt, fallen keinerlei Versand- oder Verpackungskosten an.',
                ],
            ];
            $storyKicker = 'Digitale Inhalte';
            $storyHeadline = 'Praxisnahe Inhalte zum direkten Abruf';
            $storyPoints = [
                !empty($attributes['Format']) ? "Format: {$attributes['Format']}" : 'Digitale Bereitstellung nach Zahlungseingang',
                !empty($attributes['Seiten']) ? "Umfang: {$attributes['Seiten']} Seiten" : 'Bequemer Download über das Kundenkonto',
                !empty($attributes['Assets']) ? "Enthalten: {$attributes['Assets']}" : 'Für gängige Endgeräte geeignet',
                'Versandkostenfreie digitale Auslieferung',
            ];
            $ctaSubtext = 'Direkt-Download nach Zahlung • Versandkostenfrei';
        } elseif ($isService) {
            $heroBadge = 'Manufaktur-Dienstleistung • Persönliche Abstimmung';
            $heroMiniBadges = [
                ['icon' => 'sparkles', 'title' => 'Fachberatung', 'subtitle' => "Manufaktur {$companyCity}"],
                ['icon' => 'check', 'title' => 'Individuell geplant', 'subtitle' => 'Auf dein Projekt abgestimmt'],
                ['icon' => 'shield', 'title' => 'Klare Abläufe', 'subtitle' => 'Transparente Konditionen'],
            ];
            $highlightsSubtitle = "Persönliche Abstimmung und sorgfältige Umsetzung durch unser Team in {$companyCity}.";
            $highlights = [
                [
                    'icon'  => 'sparkles',
                    'title' => 'Direkter Ansprechpartner',
                    'text'  => "Du sprichst direkt mit unserer Manufaktur in {$companyCity}, um dein Vorhaben im Detail abzustimmen.",
                ],
                [
                    'icon'  => 'check',
                    'title' => 'Machbarkeitsprüfung',
                    'text'  => 'Wir prüfen deine individuellen Anforderungen vorab und besprechen die passende Umsetzung.',
                ],
                [
                    'icon'  => 'shield',
                    'title' => 'Sorgfältige Ausführung',
                    'text'  => 'Ob Einzelprojekt oder Kleinserie: Wir legen Wert auf eine saubere und termingerechte Umsetzung.',
                ],
            ];
            $storyKicker = "Service & Beratung aus {$companyCity}";
            $storyHeadline = 'Persönliche Begleitung für dein Projekt';
            $storyPoints = [
                !empty($attributes['Dauer']) ? "Umfang / Dauer: {$attributes['Dauer']}" : 'Individuelle Abstimmung auf deine Anforderungen',
                !empty($attributes['Ort']) ? "Durchführung: {$attributes['Ort']}" : "Betreuung direkt durch unsere Manufaktur in {$companyCity}",
                'Transparente Konditionen gemäß Angebot',
                'Persönliche Rücksprache bei Rückfragen',
            ];
            $ctaSubtext = "Persönliche Abstimmung • Manufaktur {$companyCity}";
        } elseif ($isPersonalizable) {
            $heroBadge = $has3d
                ? 'Ab 1 Stück personalisierbar • Mit 3D-Konfigurator'
                : "Ab 1 Stück personalisierbar • Veredelt in {$companyCity}";

            if ($overlayType === 'cylinder') {
                $craftTitle = '360° Rundum-Lasergravur';
                $craftText = 'Mittels präziser Laser-Rotationstechnik wird dein Wunschmotiv dauerhaft und abriebfest rund um das Glas eingraviert.';
                $mini1 = ['icon' => 'sparkles', 'title' => '360° Rundum-Gravur', 'subtitle' => $attributes['Pflege'] ?? 'Dauerhafte Lasergravur'];
            } elseif ($hasBackSide) {
                $craftTitle = 'Beidseitige Lasergravur';
                $craftText = 'Vorder- und Rückseite lassen sich individuell mit Texten, Symbolen, Daten oder Koordinaten gravieren.';
                $mini1 = ['icon' => 'sparkles', 'title' => 'Beidseitig gravierbar', 'subtitle' => 'Vorder- & Rückseite'];
            } elseif (str_contains($materialLower, 'kristall') || str_contains($materialLower, 'glass') || str_contains($materialLower, 'glas')) {
                $craftTitle = $materialAttr ?: 'Geschliffenes K9-Kristallglas';
                $craftText = 'Optisch reines Kristallglas bricht das einfallende Licht in feinen Facetten und bringt deine Gravur klar zur Geltung.';
                $mini1 = ['icon' => 'sparkles', 'title' => 'Feine Lasergravur', 'subtitle' => $materialAttr ?: 'Optisches Kristallglas'];
            } elseif (str_contains($materialLower, 'alu') || str_contains($materialLower, 'metall') || str_contains($materialLower, 'metal')) {
                $craftTitle = $materialAttr ?: 'Massives Metall-Unikat';
                $craftText = 'Wertige Haptik kombiniert mit einer kontrastreichen, abriebfesten Lasergravur für den täglichen Gebrauch.';
                $mini1 = ['icon' => 'sparkles', 'title' => 'Abriebfeste Gravur', 'subtitle' => $materialAttr ?: 'Massives Metall'];
            } else {
                $craftTitle = $materialAttr ? "Gefertigt aus {$materialAttr}" : 'Präzise Lasergravur';
                $craftText = 'Dein Wunschmotiv wird in unserer Manufaktur dauerhaft und detailgenau per Laser in das Material eingearbeitet.';
                $mini1 = ['icon' => 'sparkles', 'title' => 'Präzise Lasergravur', 'subtitle' => $materialAttr ?: 'Handveredelt'];
            }

            $heroMiniBadges = [
                $mini1,
                [
                    'icon'     => 'cube',
                    'title'    => $has3d ? 'Live 3D-Konfigurator' : 'Live-Vorschau',
                    'subtitle' => 'Frei nach Wunsch gestaltbar',
                ],
                [
                    'icon'     => 'truck',
                    'title'    => "Manufaktur {$companyCity}",
                    'subtitle' => $ctx['is_vacation'] ? "Lieferung: {$deliveryTime}" : "Lieferzeit: {$deliveryTime}",
                ],
            ];

            $highlightsSubtitle = "Jedes Exemplar von „{$product->name}“ wird nach deiner Gestaltung in unserer Manufaktur in {$companyCity} veredelt.";
            $highlights = [
                [
                    'icon'  => 'sparkles',
                    'title' => $craftTitle,
                    'text'  => $craftText,
                ],
                [
                    'icon'  => 'cube',
                    'title' => $has3d ? 'Interaktive 3D-Vorschau' : 'Intuitiver Online-Konfigurator',
                    'text'  => 'Gestalte Wunschtext, Schriftart und Motive direkt online und prüfe deinen Entwurf vor der Bestellung in der Vorschau.',
                ],
                [
                    'icon'  => 'shield',
                    'title' => 'Geprüft & sicher verpackt',
                    'text'  => "Vor dem Versand wird jedes Stück in unserer Manufaktur in {$companyCity} einer Sichtprüfung unterzogen und bruchsicher verpackt.",
                ],
            ];

            $storyKicker = "Manufaktur & Handwerk aus {$companyCity}";
            $storyHeadline = 'Individuell nach deinen Wünschen gefertigt';
            $storyPoints = array_values(array_filter([
                $materialAttr ? "Material: {$materialAttr}" : 'Ausgewählte Materialien für eine kontrastreiche Gravur',
                !empty($attributes['Größe']) ? "Maße: {$attributes['Größe']}" : ($hasBackSide ? 'Vorder- und Rückseite individuell gravierbar' : 'Mit eigenem Text, Schriftart & Symbolen gestaltbar'),
                !empty($attributes['Volumen']) ? "Fassungsvermögen: {$attributes['Volumen']}" : (!empty($attributes['Pflege']) ? "Pflege: {$attributes['Pflege']}" : 'Dauerhafte, abriebfeste Lasergravur'),
                "Sorgfältige Einzelprüfung & Versand aus {$companyCountryName} ({$deliveryFullText})",
            ]));
            $ctaSubtext = "Individuell veredelt in {$companyCity} • {$deliveryFullText}";
        } else {
            $heroBadge = "Manufaktur-Qualität aus {$companyCity}";
            $heroMiniBadges = [
                ['icon' => 'sparkles', 'title' => 'Geprüfte Qualität', 'subtitle' => $materialAttr ?: 'Sorgfältige Verarbeitung'],
                ['icon' => 'check', 'title' => 'Direkt bestellbar', 'subtitle' => 'Ohne Konfiguration'],
                ['icon' => 'truck', 'title' => "Manufaktur {$companyCity}", 'subtitle' => $ctx['is_vacation'] ? "Lieferung: {$deliveryTime}" : "Lieferzeit: {$deliveryTime}"],
            ];
            $highlightsSubtitle = "Entdecke „{$product->name}“ – sorgfältig ausgewählt und geprüft in unserer Manufaktur in {$companyCity}.";
            $highlights = [
                [
                    'icon'  => 'sparkles',
                    'title' => $materialAttr ? "Gefertigt aus {$materialAttr}" : 'Ausgewählte Materialqualität',
                    'text'  => 'Sorgfältig verarbeitet und vor dem Versand in unserer Manufaktur auf einwandfreie Beschaffenheit geprüft.',
                ],
                [
                    'icon'  => 'check',
                    'title' => 'Direkt ohne Konfiguration bestellbar',
                    'text'  => 'Dieser Artikel wird als fertiges Produkt geliefert und erfordert keine vorherige Personalisierung.',
                ],
                [
                    'icon'  => 'shield',
                    'title' => 'Sorgfältig verpackt',
                    'text'  => "Jede Bestellung wird stoßgeschützt verpackt und per Versanddienstleister aus {$companyCity} zugestellt.",
                ],
            ];
            $storyKicker = "Aus unserer Manufaktur in {$companyCity}";
            $storyHeadline = 'Sorgfalt bis ins Detail';
            $storyPoints = array_values(array_filter([
                $materialAttr ? "Material: {$materialAttr}" : 'Hochwertige und langlebige Verarbeitung',
                !empty($attributes['Größe']) ? "Maße: {$attributes['Größe']}" : 'Sichtprüfung vor dem Versand',
                'Sichere, stoßgeschützte Versandverpackung',
                "Versand aus {$companyCity} ({$deliveryFullText})",
            ]));
            $ctaSubtext = "Versand aus {$companyCity} • {$deliveryFullText}";
        }

        return [
            'hero_badge'            => $heroBadge,
            'hero_mini_badges'      => $heroMiniBadges,
            'highlights_subtitle'   => $highlightsSubtitle,
            'highlights'            => $highlights,
            'story_kicker'          => $storyKicker,
            'story_headline'        => $storyHeadline,
            'story_points'          => array_slice($storyPoints, 0, 4),
            'cta_subtext'           => $ctaSubtext,
            'valid_attributes'      => $attributes,
            'delivery_time'         => $deliveryTime,
            'delivery_full_text'    => $deliveryFullText,
            'delivery_date_range'   => $ctx['delivery_date_range'],
            'delivery_color'        => $ctx['delivery_color'],
            'delivery_description'  => $ctx['delivery_description'],
            'company_name'          => $companyName,
            'company_city'          => $companyCity,
            'company_country_name'  => $companyCountryName,
            'collection_banner_url' => self::resolveCollectionBannerUrl($activeSeasonalTemplate?->holiday),
            'cta_banner_url'        => asset('shop/frontend/banners/cta_wertschaetzung.webp'),
        ];
    }
}

<?php

namespace App\Models\Marketing;

use App\Models\Delivery\DeliverySetting;
use App\Models\Delivery\DeliveryTime;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Services\ConfiguratorService;
use App\Services\Marketing\HolidayOfferAutopilotService;
use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingLandingPage extends Model implements HasSystemBlueprint
{
    use HasUuids;
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Produkt- & Kampagnen-Landingpage',
            description: 'Automatisch mit dem Produktstamm synchronisierte Verkaufs-Landingpages mit 13 logischen Jahreszeiten- & Feiertags-Vorlagenprofilen.',
            department: SystemDepartment::Marketing,
            navigationNodes: ['admin.marketing.landingpages', 'shop.landing'],
            lifecycle: LifecycleBehavior::SeasonalPerpetual,
            sensitivity: DataSensitivity::PublicCatalog,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::PAngV, ComplianceRegulation::EU_AI_Act_Art50],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

    protected $fillable = [
        'product_id',
        'slug',
        'title',
        'headline',
        'sales_copy',
        'cta_text',
        'seasonal_profiles',
        'status',
    ];

    protected $casts = [
        'seasonal_profiles' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Liefert alle 13 Vorlagen-Profile einer Produkt-Landingpage:
     * 1x Standard (Zeitlos / Evergreen) + 4x Jahreszeiten + 8x Feiertage & Anlässe.
     *
     * @return array<string, array{key: string, label: string, icon: string, group: string, hint: string, query_param: ?string}>
     */
    public static function getAllProfileDefinitions(): array
    {
        $profiles = [
            'standard' => [
                'key'         => 'standard',
                'label'       => 'Standard (Zeitlos)',
                'icon'        => '✨',
                'group'       => 'standard',
                'hint'        => 'Ganzjähriger Evergreen-Fallback',
                'query_param' => null,
            ],
        ];

        $querySlugMap = [
            'Winter'       => 'winter',
            'Sommer'       => 'sommer',
            'Herbst'       => 'herbst',
            'Frühling'     => 'fruehling',
            'Weihnachten'  => 'weihnachten',
            'Ostern'       => 'ostern',
            'Valentinstag' => 'valentinstag',
            'Muttertag'    => 'muttertag',
            'Vatertag'     => 'vatertag',
            'Halloween'    => 'halloween',
            'Hochzeit'     => 'hochzeit',
            'Geburtstag'   => 'geburtstag',
        ];

        foreach (Product::getAllSeasonalAndHolidaySlots() as $slotKey => $meta) {
            $profiles[$slotKey] = [
                'key'         => $slotKey,
                'label'       => $meta['label'],
                'icon'        => $meta['icon'] ?? '✨',
                'group'       => $meta['group'] ?? 'holiday',
                'hint'        => $meta['hint'] ?? '',
                'query_param' => $querySlugMap[$slotKey] ?? mb_strtolower($slotKey),
            ];
        }

        return $profiles;
    }

    /**
     * Prüft, ob ein kalendarischer Feiertag gerade erst vorüber ist (innerhalb von 35 Tagen nach Ende des Festes).
     * Dient als Post-Holiday-Guard für veraltete Anzeigen-Links (?holiday=weihnachten&ad=1 bzw. gclid),
     * damit z. B. am 28.12. niemand mehr Weihnachtstexte ausgespielt bekommt.
     */
    public static function isCalendarHolidayRecentlyExpired(string $slotKey, ?CarbonInterface $referenceDate = null): bool
    {
        $now = $referenceDate ? Carbon::instance($referenceDate) : Carbon::now();

        try {
            $autopilot = app(HolidayOfferAutopilotService::class);
            $eventKey = match ($slotKey) {
                'Weihnachten'  => 'christmas',
                'Valentinstag' => 'valentines',
                'Ostern'       => 'easter',
                'Muttertag'    => 'mothers_day',
                'Vatertag'     => 'fathers_day',
                'Halloween'    => 'halloween',
                default        => null,
            };

            if ($eventKey === null) {
                return false;
            }

            foreach ([$now->year, $now->year - 1] as $yr) {
                $eventDate = $autopilot->getHolidayDate($eventKey, $yr);
                if (!$eventDate) {
                    continue;
                }

                $holidayEnd = match ($eventKey) {
                    'christmas' => Carbon::create($yr, 12, 26)->endOfDay(),
                    'easter'    => $eventDate->copy()->addDays(1)->endOfDay(),
                    default     => $eventDate->copy()->endOfDay(),
                };

                $cooldownEnd = $holidayEnd->copy()->addDays(35)->endOfDay();
                if ($now->gt($holidayEnd) && $now->lte($cooldownEnd)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Liest Unternehmens-, Standort- und Live-Lieferzeit-Daten dynamisch aus `shop_setting(...)`
     * sowie `DeliverySetting` / `DeliveryTime` (inkl. Urlaubs-/Krankheitsmodus & Ampelfarbe).
     */
    public static function resolveShopContext(): array
    {
        $companyName = trim((string) shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'))) ?: 'Mein Seelenfunke';
        $companyCity = trim((string) shop_setting('company_city', shop_setting('owner_city', 'Gifhorn'))) ?: 'Gifhorn';
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
    public static function resolveCollectionBannerUrl(?string $explicitHoliday = null, ?CarbonInterface $referenceDate = null): string
    {
        try {
            if ($referenceDate === null) {
                $seasonKey = app(ConfiguratorService::class)->getActiveSeasonKey();
            } else {
                $seasonKey = match ((int) $referenceDate->month) {
                    3, 4, 5   => 'ostern',
                    6, 7, 8   => 'sommer',
                    9, 10, 11 => 'herbst',
                    default   => 'winter',
                };
            }
        } catch (\Throwable $e) {
            $seasonKey = 'herbst';
        }

        $seasonBannerMap = [
            'ostern' => 'fruehling.webp',
            'sommer' => 'sommer.webp',
            'herbst' => 'herbst.webp',
            'winter' => 'winter.webp',
        ];

        $defaultFile = $seasonBannerMap[$seasonKey] ?? 'herbst.webp';

        if ($explicitHoliday !== null && trim($explicitHoliday) !== '' && mb_strtolower(trim($explicitHoliday)) !== 'standard') {
            $norm = mb_strtolower(trim($explicitHoliday));
            $filteredBanner = match ($norm) {
                'valentinstag', 'valentines' => 'winter_valentinstag.webp',
                'ostern', 'easter', 'taufe', 'einschulung' => 'fruehling_ostern.webp',
                'muttertag', 'vatertag', 'hochzeit', 'jubilaeum', 'jubiläum', 'weltfrauentag' => 'fruehling_muttertag.webp',
                'weihnachten', 'christmas', 'advent', 'nikolaus' => 'winter_weihnachten.webp',
                'halloween', 'trauer' => 'herbst_halloween.webp',
                'herbst' => 'herbst.webp',
                'frühling', 'fruehling' => 'fruehling.webp',
                'sommer' => 'sommer.webp',
                'winter' => 'winter.webp',
                'geburtstag', 'neujahr', 'silvester' => 'winter_neujahr.webp',
                default => $defaultFile,
            };

            return asset('shop/frontend/banners/' . $filteredBanner);
        }

        $slotCtx = Product::resolveActivePrimarySeasonalSlot($referenceDate);
        if ($slotCtx['group'] === 'holiday') {
            return self::resolveCollectionBannerUrl($slotCtx['slot'], $referenceDate);
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
     * Bereinigt Produktbeschreibungen von wettbewerbsrechtlich heiklen Absolutheits-/Garantiebegriffen (UWG / § 479 BGB)
     * sowie von unerwünschten KI-Gedankenstrichen.
     */
    public static function sanitizeLegalCopy(string $text): string
    {
        $replacements = [
            '/\bMade\s+in\s+Germany\b/iu'    => 'Handveredelt in Deutschland',
            '/\bgarantiert\s+/iu'            => '',
            '/\bzu\s+100%\s+/iu'             => '',
            '/\b100%\s+spülmaschinenfest/iu' => 'spülmaschinengeeignet',
            '/\bspülmaschinenfest/iu'        => 'spülmaschinengeeignet',
            '/\bunverwüstlich\b/iu'          => 'widerstandsfähig',
            '/\babsolut\s+präzise/iu'        => 'präzise',
            '/\s+\x{2013}\s+/u'              => ': ',
        ];

        return trim((string) preg_replace(array_keys($replacements), array_values($replacements), $text));
    }

    /**
     * Erzeugt intelligente, produktindividuelle und rechtssichere Standard-Marketingtexte (Evergreen-Profil).
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

        if ($slug === 'geschenkgutschein') {
            return [
                'title'      => "{$name} | {$companyName} Manufaktur",
                'headline'   => 'Freude schenken mit Stil: Der Seelenfunken Wertgutschein',
                'sales_copy' => $rawDesc ?: 'Verschenke besondere Momente mit unserem Seelenfunken Geschenkgutschein. Wähle flexibel deinen Wunschbetrag, füge eine persönliche Widmung hinzu und erhalte ihn wahlweise digital per E-Mail oder als lasergravierte Aluminiumkarte im edlen Umschlag.',
                'cta_text'   => 'Jetzt Gutschein gestalten',
            ];
        }

        if ($slug === 'seelen-kristall' || str_contains($name, 'Kristall')) {
            return [
                'title'      => "{$name} | Personalisiertes K9-Kristallglas aus der Manufaktur",
                'headline'   => 'Brillante Lichtbrechung für besondere Erinnerungen: ' . $name,
                'sales_copy' => $rawDesc ?: 'Halte besondere Momente stilvoll in einem geschliffenen K9-Kristallglas fest. Dank unserer feinen Lasergravur und der interaktiven 3D-Vorschau gestaltest du dein persönliches Unikat online.',
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

        if ($type === 'digital') {
            $headline = 'Digital & direkt nach Bestellung abrufbar: ' . $name;
            $cta = 'Jetzt Download ansehen';
            $fallbackCopy = "Erhalte {$name} als digitales Produkt mit direktem Download nach Abschluss der Bestellung: versandkostenfrei und auf gängigen Endgeräten nutzbar.";
        } elseif ($type === 'service') {
            $headline = 'Persönliche Manufaktur-Dienstleistung: ' . $name;
            $cta = 'Jetzt Service anfragen';
            $fallbackCopy = "Profitiere bei {$name} von persönlicher fachlicher Betreuung aus unserer Manufaktur in {$companyCity}, individuell abgestimmt auf dein Vorhaben.";
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
            $fallbackCopy = "Entdecke {$name} aus unserer Manufaktur in {$companyCity}: sorgfältig geprüft und sicher verpackt zu dir nach Hause geliefert.";
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
     * Erzeugt die abmahnsicheren, anlassspezifischen Texte für eines der 12 Saison-/Feiertags-Profile
     * (oder für 'standard') passend zum jeweiligen Produkt.
     */
    public static function generateSeasonalProfileCopy(Product $product, string $slotKey): array
    {
        $normalized = ($slotKey === 'standard') ? 'standard' : (Product::normalizeSeasonalSlotKey($slotKey) ?: 'standard');
        $base = self::generateDefaultCopy($product);

        if ($normalized === 'standard' || !isset(self::getAllProfileDefinitions()[$normalized])) {
            return $base;
        }

        $ctx = self::resolveShopContext();
        $companyName = $ctx['company_name'];
        $companyCity = $ctx['company_city'];
        $name = trim((string) $product->name);
        $slug = trim((string) $product->slug);
        $type = (string) ($product->type ?: 'physical');
        $isVoucher = $slug === 'geschenkgutschein';
        $isPersonalizable = $product->isPersonalizable();

        // Reine B2B-/Digital-Downloads ohne Personalisierung behalten ihr klares Fachprofil
        if (($type === 'digital' && !$isVoucher) || $type === 'service') {
            return $base;
        }

        $baseStory = $base['sales_copy'];

        if ($isVoucher) {
            $voucherMap = [
                'Weihnachten' => [
                    'title'    => "{$name} | Weihnachtsgeschenk aus der Manufaktur {$companyName}",
                    'headline' => 'Freude unter dem Weihnachtsbaum schenken: Der Seelenfunken Wertgutschein',
                    'intro'    => "Das stilvolle Weihnachtsgeschenk, auch für Kurzentschlossene: Wähle deinen Wunschbetrag von 5 € bis 1.000 €, ergänze deine persönliche Weihnachts-Widmung und verschenke ihn direkt per E-Mail (PDF) oder als lasergravierte Gold-Aluminiumkarte im schwarzen Edel-Umschlag.",
                    'cta_text' => 'Weihnachtsgutschein gestalten',
                ],
                'Valentinstag' => [
                    'title'    => "{$name} | Valentinstagsgeschenk von {$companyName}",
                    'headline' => 'Gemeinsame Erinnerungen zum Valentinstag verschenken: Der Seelenfunken Wertgutschein',
                    'intro'    => "Lass deinen Lieblingsmenschen sein Wunsch-Unikat selbst gestalten: mit persönlicher Liebesbotschaft per E-Mail (PDF) oder als edle lasergravierte Aluminiumkarte mit goldenem Siegel.",
                    'cta_text' => 'Gutschein gestalten',
                ],
                'Ostern' => [
                    'title'    => "{$name} | Ostergeschenk von {$companyName}",
                    'headline' => 'Besondere Überraschung im Osternest: Der Seelenfunken Wertgutschein',
                    'intro'    => "Verschenke zu Ostern freie Auswahl aus unserer Manufaktur in {$companyCity}: flexibel wählbar von 5 € bis 1.000 € und mit deiner persönlichen Oster-Grußbotschaft versehen.",
                    'cta_text' => 'Ostergutschein gestalten',
                ],
                'Muttertag' => [
                    'title'    => "{$name} | Muttertagsgeschenk von {$companyName}",
                    'headline' => 'Von Herzen Danke sagen zum Muttertag: Der Seelenfunken Wertgutschein',
                    'intro'    => "Schenke Mama zum Muttertag die Freude, ihr eigenes Erinnerungsstück in Ruhe selbst zu gestalten: sofort per E-Mail abrufbar oder als lasergravierte Aluminiumkarte im edlen Umschlag.",
                    'cta_text' => 'Muttertagsgutschein gestalten',
                ],
                'Vatertag' => [
                    'title'    => "{$name} | Vatertagsgeschenk von {$companyName}",
                    'headline' => 'Stilvolle Wertschätzung zum Vatertag: Der Seelenfunken Wertgutschein',
                    'intro'    => "Ob graviertes Weizenglas oder massives Manufaktur-Unikat: Mit dem Seelenfunken Wertgutschein wählt Papa sein persönliches Lieblingsstück selbst aus.",
                    'cta_text' => 'Gutschein gestalten',
                ],
                'Halloween' => [
                    'title'    => "{$name} | Herbst-Geschenkidee von {$companyName}",
                    'headline' => 'Besondere Freude in der goldenen Herbstzeit: Der Seelenfunken Wertgutschein',
                    'intro'    => "Überrasche liebe Menschen im Herbst mit freier Auswahl aus unserer Manufaktur in {$companyCity}, wahlweise per E-Mail (PDF) oder als lasergravierte Aluminiumkarte.",
                    'cta_text' => 'Gutschein gestalten',
                ],
                'Hochzeit' => [
                    'title'    => "{$name} | Hochzeitsgeschenk von {$companyName}",
                    'headline' => 'Stilvolles Geld- & Wertgeschenk zur Hochzeit: Der Seelenfunken Wertgutschein',
                    'intro'    => "Ideal für das Brautpaar: Als lasergravierte Gold-Aluminiumkarte im schwarzen Umschlag mit goldenem Siegel überreichst du ein bleibendes Hochzeitsgeschenk, mit dem das Paar sein Lieblingsfoto später selbst verewigen kann.",
                    'cta_text' => 'Hochzeitsgutschein gestalten',
                ],
                'Geburtstag' => [
                    'title'    => "{$name} | Geburtstagsgeschenk von {$companyName}",
                    'headline' => 'Das passende Geburtstagsgeschenk in jeder Wunschhöhe: Der Seelenfunken Wertgutschein',
                    'intro'    => "Flexibel von 5 € bis 1.000 € wählbar, mit persönlicher Geburtstags-Widmung und sofort per E-Mail oder als edle Aluminiumkarte verfügbar.",
                    'cta_text' => 'Gutschein gestalten',
                ],
                'Winter' => [
                    'title'    => "{$name} | Winter-Geschenkidee von {$companyName}",
                    'headline' => 'Wärmende Freude in der Winterzeit schenken: Der Seelenfunken Wertgutschein',
                    'intro'    => $baseStory,
                    'cta_text' => 'Jetzt Gutschein gestalten',
                ],
                'Frühling' => [
                    'title'    => "{$name} | Frühlings-Geschenkidee von {$companyName}",
                    'headline' => 'Für Feste, Taufen & besondere Frühlingsmomente: Der Seelenfunken Wertgutschein',
                    'intro'    => $baseStory,
                    'cta_text' => 'Jetzt Gutschein gestalten',
                ],
                'Sommer' => [
                    'title'    => "{$name} | Sommer-Geschenkidee von {$companyName}",
                    'headline' => 'Für Hochzeiten, Jubiläen & Sommerfeste: Der Seelenfunken Wertgutschein',
                    'intro'    => $baseStory,
                    'cta_text' => 'Jetzt Gutschein gestalten',
                ],
                'Herbst' => [
                    'title'    => "{$name} | Herbst-Geschenkidee von {$companyName}",
                    'headline' => 'Freude schenken in der gemütlichen Jahreszeit: Der Seelenfunken Wertgutschein',
                    'intro'    => $baseStory,
                    'cta_text' => 'Jetzt Gutschein gestalten',
                ],
            ];

            $picked = $voucherMap[$normalized] ?? null;
            if ($picked) {
                return [
                    'title'      => $picked['title'],
                    'headline'   => $picked['headline'],
                    'sales_copy' => self::sanitizeLegalCopy($picked['intro']),
                    'cta_text'   => $picked['cta_text'],
                ];
            }
        }

        $ctaDefault = $isPersonalizable ? 'Jetzt Unikat gestalten' : 'Jetzt im Shop ansehen';

        $slotCopyMap = [
            'Weihnachten' => [
                'seo_suffix' => "Persönliches Weihnachtsgeschenk | {$companyName}",
                'headline'   => "Das persönliche Weihnachtsgeschenk aus unserer Manufaktur in {$companyCity}: {$name}",
                'lead'       => "Unter dem Weihnachtsbaum zählen Geschenke mit echter Bedeutung. Gestalte {$name} individuell für deine Liebsten, veredelt in unserer Manufaktur in {$companyCity} und auf Wunsch inklusive schwarzer Geschenkbox oder Schwarz-Gold-Geschenkpapier.",
                'cta'        => $isPersonalizable ? 'Weihnachts-Unikat gestalten' : $ctaDefault,
            ],
            'Valentinstag' => [
                'seo_suffix' => "Persönliches Valentinstagsgeschenk | {$companyName}",
                'headline'   => "Für deinen Lieblingsmenschen zum Valentinstag: {$name} mit persönlicher Gravur",
                'lead'       => "Halte eure gemeinsame Geschichte, ein besonderes Datum oder euer Lieblingsmotiv dauerhaft fest. Jedes Exemplar von {$name} wird in unserer Manufaktur in {$companyCity} einzeln für euch veredelt.",
                'cta'        => $isPersonalizable ? 'Liebes-Unikat gestalten' : $ctaDefault,
            ],
            'Ostern' => [
                'seo_suffix' => "Persönliches Ostergeschenk | {$companyName}",
                'headline'   => "Bleibende Freude zu Ostern & für Frühlingsfeste: {$name}",
                'lead'       => "Überrasche deine Familie zu Ostern mit einem handveredelten Erinnerungsstück statt vergänglicher Kleinigkeiten. {$name} wird individuell in unserer Manufaktur in {$companyCity} gefertigt.",
                'cta'        => $ctaDefault,
            ],
            'Muttertag' => [
                'seo_suffix' => "Persönliches Muttertagsgeschenk | {$companyName}",
                'headline'   => "Von Herzen Danke sagen zum Muttertag: {$name} individuell für Mama veredelt",
                'lead'       => "Schenke Mama zum Muttertag ein Erinnerungsstück, das jeden Tag ein Lächeln schenkt. Gestalte {$name} mit deinen eigenen Worten oder eurem gemeinsamen Motiv, handveredelt in {$companyCity}.",
                'cta'        => $isPersonalizable ? 'Geschenk für Mama gestalten' : $ctaDefault,
            ],
            'Vatertag' => [
                'seo_suffix' => "Persönliches Vatertagsgeschenk | {$companyName}",
                'headline'   => "Charakterstarkes Unikat zum Vatertag: {$name} mit Wunschgravur",
                'lead'       => "Ein persönliches Einzelstück mit echtem Charakter zum Vatertag: {$name} wird nach deinem Entwurf präzise per Laser in unserer Manufaktur in {$companyCity} veredelt.",
                'cta'        => $isPersonalizable ? 'Geschenk für Papa gestalten' : $ctaDefault,
            ],
            'Halloween' => [
                'seo_suffix' => "Stimmungsvolles Herbst-Unikat | {$companyName}",
                'headline'   => "Besondere Licht- & Erinnerungsmomente für den Oktober: {$name}",
                'lead'       => "Wenn die Abende länger werden, sorgt feine Manufaktur-Veredelung für besondere Atmosphäre. Gestalte {$name} individuell in unserer Manufaktur in {$companyCity}.",
                'cta'        => $ctaDefault,
            ],
            'Hochzeit' => [
                'seo_suffix' => "Personalisiertes Hochzeitsgeschenk | {$companyName}",
                'headline'   => "Für die Ewigkeit verbunden: {$name} als persönliches Hochzeitsgeschenk",
                'lead'       => "Verewige Namen, Hochzeitsdatum oder ein gemeinsames Erinnerungsmotiv für das Brautpaar. {$name} wird einzeln in unserer Manufaktur in {$companyCity} veredelt und auf Wunsch direkt im edlen Geschenk-Look geliefert.",
                'cta'        => $isPersonalizable ? 'Hochzeits-Unikat gestalten' : $ctaDefault,
            ],
            'Geburtstag' => [
                'seo_suffix' => "Persönliches Geburtstagsgeschenk | {$companyName}",
                'headline'   => "Einzigartig wie das Geburtstagskind: {$name} individuell gestaltet",
                'lead'       => "Ob runder Ehrentag oder Herzensmensch: Mit {$name} überreichst du ein individuelles Einzelstück aus unserer Manufaktur in {$companyCity}, das lange in Erinnerung bleibt.",
                'cta'        => $isPersonalizable ? 'Geburtstags-Unikat gestalten' : $ctaDefault,
            ],
            'Winter' => [
                'seo_suffix' => "Winter-Kollektion aus der Manufaktur | {$companyName}",
                'headline'   => "Zeitlose Erinnerungen für ruhige Wintermomente: {$name}",
                'lead'       => "Halte wertvolle Augenblicke stilvoll fest: {$name} wird in unserer Manufaktur in {$companyCity} einzeln nach deinen Wünschen veredelt und sorgfältig geprüft.",
                'cta'        => $ctaDefault,
            ],
            'Frühling' => [
                'seo_suffix' => "Frühlings-Kollektion aus der Manufaktur | {$companyName}",
                'headline'   => "Für neue Meilensteine & besondere Frühlingsanlässe: {$name}",
                'lead'       => "Ob Familienfeier, Taufe oder persönlicher Dankmoment im Frühling: {$name} wird individuell für dich in unserer Manufaktur in {$companyCity} veredelt.",
                'cta'        => $ctaDefault,
            ],
            'Sommer' => [
                'seo_suffix' => "Sommer-Kollektion aus der Manufaktur | {$companyName}",
                'headline'   => "Für Sommerfeste, Hochzeiten & sonnige Erinnerungen: {$name}",
                'lead'       => "Bewahre die schönsten Momente des Sommers in spürbarer Manufaktur-Qualität: {$name} wird nach deinem Wunschmotiv in {$companyCity} gefertigt.",
                'cta'        => $ctaDefault,
            ],
            'Herbst' => [
                'seo_suffix' => "Herbst-Kollektion aus der Manufaktur | {$companyName}",
                'headline'   => "Warme Wertschätzung für goldene Herbsttage: {$name}",
                'lead'       => "Für gemütliche Abende und persönliche Geschenkmomente im Herbst: {$name} wird mit höchster Sorgfalt in unserer Manufaktur in {$companyCity} veredelt.",
                'cta'        => $ctaDefault,
            ],
        ];

        $cfg = $slotCopyMap[$normalized] ?? null;
        if (!$cfg) {
            return $base;
        }

        $combinedCopy = trim($cfg['lead'] . "\n\n" . $baseStory);

        return [
            'title'      => "{$name} | {$cfg['seo_suffix']}",
            'headline'   => self::sanitizeLegalCopy($cfg['headline']),
            'sales_copy' => self::sanitizeLegalCopy($combinedCopy),
            'cta_text'   => $cfg['cta'],
        ];
    }

    /**
     * Erzeugt oder ergänzt das strukturierte `seasonal_profiles`-Array für alle 12 Saison- & Feiertags-Slots.
     */
    public static function generateAllSeasonalProfiles(
        Product $product,
        array $existingProfiles = [],
        bool $overwriteExisting = false
    ): array {
        $result = [];

        foreach (array_keys(Product::getAllSeasonalAndHolidaySlots()) as $slotKey) {
            $existing = isset($existingProfiles[$slotKey]) && is_array($existingProfiles[$slotKey])
                ? $existingProfiles[$slotKey]
                : null;

            $hasValidExisting = $existing !== null
                && !empty(trim((string) ($existing['headline'] ?? '')))
                && !empty(trim((string) ($existing['sales_copy'] ?? '')));

            if ($hasValidExisting && !$overwriteExisting) {
                $result[$slotKey] = [
                    'enabled'    => array_key_exists('enabled', $existing) ? (bool) $existing['enabled'] : true,
                    'title'      => (string) ($existing['title'] ?? ''),
                    'headline'   => (string) ($existing['headline'] ?? ''),
                    'sales_copy' => (string) ($existing['sales_copy'] ?? ''),
                    'cta_text'   => (string) ($existing['cta_text'] ?? ''),
                ];
                continue;
            }

            $generated = self::generateSeasonalProfileCopy($product, $slotKey);
            $result[$slotKey] = [
                'enabled'    => ($existing !== null && array_key_exists('enabled', $existing)) ? (bool) $existing['enabled'] : true,
                'title'      => $generated['title'],
                'headline'   => $generated['headline'],
                'sales_copy' => $generated['sales_copy'],
                'cta_text'   => $generated['cta_text'],
            ];
        }

        return $result;
    }

    /**
     * Löst für diese EINE Produkt-Landingpage (/l/{slug}) das aktuell gültige Vorlagen-Profil auf:
     * 1. Expliziter Parameter (?holiday=weihnachten), geschützt durch den Post-Holiday-Guard bei Ad-Klicks (gclid / ad=1)
     * 2. Aktives Feiertags-Fenster laut HolidayOfferAutopilotService (z. B. Weihnachten bis 26.12.)
     * 3. Aktive Jahreszeit direkt nach Feiertags-Ende (z. B. ab 27.12. sofort "Winter")
     * 4. Zeitloses Standard-Profil als Evergreen-Fallback
     *
     * @return array{slot_key: string, label: string, icon: string, group: string, is_explicit: bool, post_holiday_guard_triggered: bool, is_before_cutoff: bool, media_holiday_hint: ?string, title: string, headline: string, sales_copy: string, cta_text: string}
     */
    public function resolveActiveProfile(
        ?string $requestedHoliday = null,
        bool $isAdTraffic = false,
        ?CarbonInterface $referenceDate = null
    ): array {
        $product = $this->relationLoaded('product') ? $this->product : $this->product()->first();
        $definitions = self::getAllProfileDefinitions();
        $liveCtx = Product::resolveActivePrimarySeasonalSlot($referenceDate);

        $requestedSlot = Product::normalizeSeasonalSlotKey($requestedHoliday);
        $isExplicit = false;
        $guardTriggered = false;
        $effectiveSlot = 'standard';

        if ($requestedSlot !== null && $requestedSlot !== '') {
            if (mb_strtolower($requestedSlot) === 'standard') {
                $effectiveSlot = 'standard';
                $isExplicit = true;
            } elseif ($isAdTraffic && self::isCalendarHolidayRecentlyExpired($requestedSlot, $referenceDate)) {
                // Veralteter Feiertags-Ad-Link nach dem Fest -> sofort zurück auf aktuelle Jahreszeit / Standard!
                $guardTriggered = true;
                $effectiveSlot = ($product && ($product->isAutoSeasonalMainImageEnabled() || $product->type === 'physical' || $product->slug === 'geschenkgutschein'))
                    ? $liveCtx['slot']
                    : 'standard';
            } elseif (isset($definitions[$requestedSlot])) {
                $effectiveSlot = $requestedSlot;
                $isExplicit = true;
            }
        } else {
            // Automatische Zeitsteuerung für physische Manufakturprodukte & Geschenkgutschein (sofern Saison-Automatik aktiv)
            $useSeasonalAuto = $product
                && ($product->isAutoSeasonalMainImageEnabled() || $product->slug === 'geschenkgutschein')
                && !(($product->type === 'digital' && $product->slug !== 'geschenkgutschein') || $product->type === 'service');

            if ($useSeasonalAuto) {
                $effectiveSlot = $liveCtx['slot'];
            }
        }

        $storedProfiles = is_array($this->seasonal_profiles) ? $this->seasonal_profiles : [];

        // Falls ein einzelnes Anlass-Profil im Backend bewusst deaktiviert wurde -> Fallback auf Jahreszeit bzw. Standard
        if ($effectiveSlot !== 'standard') {
            $slotEntry = $storedProfiles[$effectiveSlot] ?? null;
            if (is_array($slotEntry) && array_key_exists('enabled', $slotEntry) && !$slotEntry['enabled'] && !$isExplicit) {
                $seasonFallback = $liveCtx['season_slot'];
                $seasonEntry = $storedProfiles[$seasonFallback] ?? null;
                if ($seasonFallback !== $effectiveSlot && (!is_array($seasonEntry) || ($seasonEntry['enabled'] ?? true))) {
                    $effectiveSlot = $seasonFallback;
                } else {
                    $effectiveSlot = 'standard';
                }
            }
        }

        $standardCopy = [
            'title'      => (string) ($this->title ?: ($product?->name ?? '')),
            'headline'   => (string) ($this->headline ?: ''),
            'sales_copy' => (string) ($this->sales_copy ?: ($product?->description ?? '')),
            'cta_text'   => (string) ($this->cta_text ?: ''),
        ];

        if ($effectiveSlot === 'standard') {
            $def = $definitions['standard'];
            return [
                'slot_key'                     => 'standard',
                'label'                        => $def['label'],
                'icon'                         => $def['icon'],
                'group'                        => 'standard',
                'is_explicit'                  => $isExplicit,
                'post_holiday_guard_triggered' => $guardTriggered,
                'is_before_cutoff'             => false,
                'media_holiday_hint'           => null,
                'title'                        => $standardCopy['title'],
                'headline'                     => $standardCopy['headline'],
                'sales_copy'                   => $standardCopy['sales_copy'],
                'cta_text'                     => $standardCopy['cta_text'],
            ];
        }

        $profileData = isset($storedProfiles[$effectiveSlot]) && is_array($storedProfiles[$effectiveSlot])
            ? $storedProfiles[$effectiveSlot]
            : ($product ? self::generateSeasonalProfileCopy($product, $effectiveSlot) : $standardCopy);

        $def = $definitions[$effectiveSlot] ?? $definitions['standard'];
        $isBeforeCutoff = ($effectiveSlot === $liveCtx['slot']) ? $liveCtx['is_before_cutoff'] : false;

        return [
            'slot_key'                     => $effectiveSlot,
            'label'                        => $def['label'],
            'icon'                         => $def['icon'],
            'group'                        => $def['group'],
            'is_explicit'                  => $isExplicit,
            'post_holiday_guard_triggered' => $guardTriggered,
            'is_before_cutoff'             => $isBeforeCutoff,
            'media_holiday_hint'           => $isExplicit ? $effectiveSlot : null,
            'title'                        => trim((string) ($profileData['title'] ?? '')) ?: $standardCopy['title'],
            'headline'                     => trim((string) ($profileData['headline'] ?? '')) ?: $standardCopy['headline'],
            'sales_copy'                   => trim((string) ($profileData['sales_copy'] ?? '')) ?: $standardCopy['sales_copy'],
            'cta_text'                     => trim((string) ($profileData['cta_text'] ?? '')) ?: $standardCopy['cta_text'],
        ];
    }

    /**
     * Stellt sicher, dass für ein Produkt eine passende MarketingLandingPage samt den 12 Jahreszeiten- & Feiertags-Profilen
     * existiert und hält Entwurfs-Slugs/Texte bei neu angelegten Produkten automatisch synchron.
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
                'product_id'        => $product->id,
                'slug'              => $targetSlug,
                'title'             => $defaults['title'],
                'headline'          => $defaults['headline'],
                'sales_copy'        => $defaults['sales_copy'],
                'cta_text'          => $defaults['cta_text'],
                'seasonal_profiles' => self::generateAllSeasonalProfiles($product, [], true),
                'status'            => $product->status === 'active' ? 'active' : 'draft',
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
            $updates['cta_text'] = $defaults['cta_text'];
        }

        $existingProfiles = is_array($existing->seasonal_profiles) ? $existing->seasonal_profiles : [];
        $expectedSlotsCount = count(Product::getAllSeasonalAndHolidaySlots());
        if ($forceRegenerateCopy || count($existingProfiles) < $expectedSlotsCount) {
            $updates['seasonal_profiles'] = self::generateAllSeasonalProfiles($product, $existingProfiles, $forceRegenerateCopy);
        }

        if (str_starts_with((string) $existing->slug, 'draft-') && !self::where('slug', $rawSlug)->where('id', '!=', $existing->id)->exists()) {
            $updates['slug'] = $rawSlug;
        }

        if ($product->status !== 'active' && $existing->status !== 'draft') {
            $updates['status'] = 'draft';
        } elseif ($forceRegenerateCopy && $product->status === 'active' && $existing->status !== 'active') {
            $updates['status'] = 'active';
        }

        if (!empty($updates)) {
            $existing->update($updates);
        }

        return $existing;
    }

    /**
     * Erzeugt das dynamische, rechtssichere Darstellungs-Profil (Hero-Kicker, exakt 3 kurze Vorteile mit goldenem ✓,
     * 3 Highlight-Kacheln, Story-Abschnitt, Abschluss-CTA & Saison-Banner) passend zu Produkttyp, Material,
     * Live-Shop-Settings UND dem aktiven Jahreszeiten-/Feiertags-Profil.
     */
    public static function buildDynamicProfile(
        Product $product,
        ?array $heroImageMeta = null,
        ?ProductTemplate $activeSeasonalTemplate = null,
        ?array $resolvedProfile = null,
        ?CarbonInterface $referenceDate = null
    ): array {
        $ctx = self::resolveShopContext();
        $companyName = $ctx['company_name'];
        $companyCity = $ctx['company_city'];
        $companyCountryName = $ctx['company_country_name'];
        $deliveryTime = $ctx['delivery_days_short'];
        $deliveryFullText = $ctx['delivery_full_text'];

        $activeSlot = (string) ($resolvedProfile['slot_key'] ?? ($activeSeasonalTemplate?->holiday ?: 'standard'));
        $activeGroup = (string) ($resolvedProfile['group'] ?? 'standard');
        $isBeforeCutoff = (bool) ($resolvedProfile['is_before_cutoff'] ?? false);

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
            $heroBadge = ($activeSlot !== 'standard' && $activeGroup === 'holiday')
                ? "Geschenkidee zu {$activeSlot} • PDF oder Aluminiumkarte"
                : 'Wertgutschein • Per E-Mail (PDF) oder Aluminiumkarte';
            $occasionBenefits = [
                'Wunschbetrag von 5 € bis 1.000 € mit persönlicher Widmung',
                'Direkt per E-Mail (PDF) oder als lasergravierte Aluminiumkarte',
                '3 Jahre gültig für das gesamte Sortiment (§ 195 BGB)',
            ];
            $heroMiniBadges = [
                ['icon' => 'gift', 'title' => '5 € bis 1.000 € wählbar', 'subtitle' => 'Dein Wunschbetrag in Euro'],
                ['icon' => 'bolt', 'title' => 'PDF & Aluminiumkarte', 'subtitle' => 'Zwei Versandarten'],
                ['icon' => 'heart', 'title' => 'Persönliche Widmung', 'subtitle' => 'Individueller Grußtext'],
            ];
            $highlightsSubtitle = "Ob als kurzfristiges Geschenk per E-Mail oder als lasergravierte Aluminiumkarte im schwarzen Edel-Umschlag: Der Wertgutschein von {$companyName} lässt dem Beschenkten die freie Auswahl.";
            $highlights = [
                [
                    'icon'  => 'sparkles',
                    'title' => 'Dein Wunschbetrag & Grußtext',
                    'text'  => 'Wähle einen beliebten Festbetrag (10 €, 25 €, 50 €, 100 €) oder gib deinen Wunschbetrag flexibel von 5 € bis 1.000 € (in 5er-Schritten) ein und ergänze deine persönliche Grußbotschaft.',
                ],
                [
                    'icon'  => 'mail',
                    'title' => 'Digital oder per Post',
                    'text'  => 'Nach Zahlungseingang als druckfertiges PDF per E-Mail verfügbar oder optional als lasergravierte Aluminiumkarte im schwarzen Umschlag mit Siegel bestellbar.',
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
                'Als PDF zum Selbstausdrucken oder als lasergravierte Aluminiumkarte erhältlich',
                'Restguthaben bleibt innerhalb der Gültigkeitsdauer erhalten',
                'Gültig für 3 Jahre ab Ende des Kaufjahres (§ 195 BGB)',
            ];
            $ctaHeading = ($activeSlot === 'Weihnachten')
                ? 'Bereit für ein stilvolles Weihnachtsgeschenk?'
                : 'Bereit für echte Wertschätzung?';
            $ctaSubtext = 'Digital per E-Mail (versandkostenfrei) oder per Post • 3 Jahre gültig';
        } elseif ($isDigital) {
            $heroBadge = 'Digitales Produkt • Direkt-Download nach Zahlung';
            $occasionBenefits = [
                'Direkter Zugriff nach erfolgreichem Zahlungseingang',
                !empty($attributes['Format']) ? "Bereitstellung im Format {$attributes['Format']}" : 'Auf PC, Tablet und Smartphone nutzbar',
                'Versandkostenfreie digitale Auslieferung im Kundenkonto',
            ];
            $heroMiniBadges = [
                ['icon' => 'download', 'title' => 'Sofort-Download', 'subtitle' => 'Nach Zahlungseingang'],
                ['icon' => 'device', 'title' => 'Digitales Format', 'subtitle' => $attributes['Format'] ?? 'Flexibel nutzbar'],
                ['icon' => 'check', 'title' => 'Versandkostenfrei', 'subtitle' => 'Digitale Bereitstellung'],
            ];
            $highlightsSubtitle = 'Direkter digitaler Zugriff nach Bestellabschluss: übersichtlich aufbereitet und ohne Versandkosten.';
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
            $ctaHeading = 'Bereit für den direkten Zugriff?';
            $ctaSubtext = 'Direkt-Download nach Zahlung • Versandkostenfrei';
        } elseif ($isService) {
            $heroBadge = 'Manufaktur-Dienstleistung • Persönliche Abstimmung';
            $occasionBenefits = [
                "Persönlicher Ansprechpartner in unserer Manufaktur {$companyCity}",
                'Vorab-Machbarkeitsprüfung für dein individuelles Projekt',
                'Transparente Konditionen & sorgfältige Ausführung',
            ];
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
            $ctaHeading = 'Bereit für dein Projekt?';
            $ctaSubtext = "Persönliche Abstimmung • Manufaktur {$companyCity}";
        } elseif ($isPersonalizable) {
            $heroBadge = self::resolveOccasionHeroBadge($activeSlot, $companyCity, $has3d);
            $occasionBenefits = self::resolveOccasionThreeBenefits($activeSlot, $companyCity, $has3d, true);

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
            } elseif (str_contains($materialLower, 'alu') || str_contains($materialLower, 'metall') || str_contains($materialLower, 'edelstahl') || str_contains($materialLower, 'metal')) {
                $craftTitle = $materialAttr ?: 'Massives Metall-Unikat';
                $craftText = 'Wertige Haptik kombiniert mit einer kontrastreichen, abriebfesten Lasergravur für den täglichen Gebrauch.';
                $mini1 = ['icon' => 'sparkles', 'title' => 'Abriebfeste Gravur', 'subtitle' => $materialAttr ?: 'Edles Metall'];
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

            $thirdHighlight = ($activeGroup === 'holiday')
                ? [
                    'icon'  => 'shield',
                    'title' => 'Bereit zum Überreichen',
                    'text'  => "Einzeln geprüft in unserer Manufaktur in {$companyCity}, sicher verpackt und im Konfigurator auf Wunsch mit schwarzer Geschenkbox oder Schwarz-Gold-Geschenkpapier wählbar.",
                ]
                : [
                    'icon'  => 'shield',
                    'title' => 'Geprüft & sicher verpackt',
                    'text'  => "Vor dem Versand wird jedes Stück in unserer Manufaktur in {$companyCity} einer Sichtprüfung unterzogen und bruchsicher verpackt.",
                ];

            $highlightsSubtitle = self::resolveOccasionHighlightsSubtitle($product->name, $activeSlot, $companyCity);
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
                $thirdHighlight,
            ];

            [$storyKicker, $storyHeadline] = self::resolveOccasionStoryHeader($activeSlot, $companyCity);
            $storyPoints = array_values(array_filter([
                $materialAttr ? "Material: {$materialAttr}" : 'Ausgewählte Materialien für eine kontrastreiche Gravur',
                !empty($attributes['Größe']) ? "Maße: {$attributes['Größe']}" : ($hasBackSide ? 'Vorder- und Rückseite individuell gravierbar' : 'Mit eigenem Text, Schriftart & Symbolen gestaltbar'),
                !empty($attributes['Volumen']) ? "Fassungsvermögen: {$attributes['Volumen']}" : (!empty($attributes['Pflege']) ? "Pflege: {$attributes['Pflege']}" : 'Dauerhafte, abriebfeste Lasergravur'),
                "Sorgfältige Einzelprüfung & Versand aus {$companyCountryName} ({$deliveryFullText})",
            ]));
            $ctaHeading = self::resolveOccasionCtaHeading($activeSlot);
            $ctaSubtext = "Individuell veredelt in {$companyCity} • {$deliveryFullText}";
        } else {
            $heroBadge = self::resolveOccasionHeroBadge($activeSlot, $companyCity, false);
            $occasionBenefits = self::resolveOccasionThreeBenefits($activeSlot, $companyCity, false, false);
            $heroMiniBadges = [
                ['icon' => 'sparkles', 'title' => 'Geprüfte Qualität', 'subtitle' => $materialAttr ?: 'Sorgfältige Verarbeitung'],
                ['icon' => 'check', 'title' => 'Direkt bestellbar', 'subtitle' => 'Ohne Konfiguration'],
                ['icon' => 'truck', 'title' => "Manufaktur {$companyCity}", 'subtitle' => $ctx['is_vacation'] ? "Lieferung: {$deliveryTime}" : "Lieferzeit: {$deliveryTime}"],
            ];
            $highlightsSubtitle = "Entdecke „{$product->name}“, sorgfältig ausgewählt und geprüft in unserer Manufaktur in {$companyCity}.";
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
            [$storyKicker, $storyHeadline] = self::resolveOccasionStoryHeader($activeSlot, $companyCity);
            $storyPoints = array_values(array_filter([
                $materialAttr ? "Material: {$materialAttr}" : 'Hochwertige und langlebige Verarbeitung',
                !empty($attributes['Größe']) ? "Maße: {$attributes['Größe']}" : 'Sichtprüfung vor dem Versand',
                'Sichere, stoßgeschützte Versandverpackung',
                "Versand aus {$companyCity} ({$deliveryFullText})",
            ]));
            $ctaHeading = self::resolveOccasionCtaHeading($activeSlot);
            $ctaSubtext = "Versand aus {$companyCity} • {$deliveryFullText}";
        }

        $cutoffNotice = null;
        if ($isBeforeCutoff && $activeGroup === 'holiday' && !$isDigital && !$isService && !$ctx['is_vacation'] && !$ctx['is_sick']) {
            $cutoffNotice = "Aktuelle Fertigung für {$activeSlot} in {$companyCity} ({$deliveryFullText})";
        }

        $bannerHint = ($activeSlot !== 'standard') ? $activeSlot : ($activeSeasonalTemplate?->holiday ?: null);

        return [
            'active_slot'           => $activeSlot,
            'active_group'          => $activeGroup,
            'hero_badge'            => $heroBadge,
            'occasion_benefits'     => array_slice($occasionBenefits, 0, 3),
            'cutoff_notice'         => $cutoffNotice,
            'hero_mini_badges'      => $heroMiniBadges,
            'highlights_subtitle'   => $highlightsSubtitle,
            'highlights'            => $highlights,
            'story_kicker'          => $storyKicker,
            'story_headline'        => $storyHeadline,
            'story_points'          => array_slice($storyPoints, 0, 4),
            'cta_heading'           => $ctaHeading,
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
            'collection_banner_url' => self::resolveCollectionBannerUrl($bannerHint, $referenceDate),
            'cta_banner_url'        => asset('shop/frontend/banners/cta_wertschaetzung.webp'),
        ];
    }

    private static function resolveOccasionHeroBadge(string $slotKey, string $companyCity, bool $has3d): string
    {
        return match ($slotKey) {
            'Weihnachten'  => "Weihnachts-Geschenkidee • Handveredelt in {$companyCity}",
            'Valentinstag' => "Zum Valentinstag • Handveredelt in {$companyCity}",
            'Ostern'       => "Oster-Geschenkidee • Handveredelt in {$companyCity}",
            'Muttertag'    => "Zum Muttertag • Handveredelt in {$companyCity}",
            'Vatertag'     => "Zum Vatertag • Handveredelt in {$companyCity}",
            'Halloween'    => "Herbst- & Lichter-Edition • Manufaktur {$companyCity}",
            'Hochzeit'     => "Zur Hochzeit & Verlobung • Handveredelt in {$companyCity}",
            'Geburtstag'   => "Persönliches Geburtstagsgeschenk • Manufaktur {$companyCity}",
            'Winter'       => "Winter-Kollektion • Handveredelt in {$companyCity}",
            'Frühling'     => "Frühlings-Kollektion • Handveredelt in {$companyCity}",
            'Sommer'       => "Sommer-Kollektion • Handveredelt in {$companyCity}",
            'Herbst'       => "Herbst-Kollektion • Handveredelt in {$companyCity}",
            default        => $has3d
                ? "Ab 1 Stück personalisierbar • Mit 3D-Konfigurator"
                : "Handveredelt in Deutschland (Manufaktur {$companyCity})",
        };
    }

    /**
     * Exakt 3 kurze, prägnante Vorteile (für die Darstellung mit goldenem ✓ in #C5A059 ohne Box-Overload).
     */
    private static function resolveOccasionThreeBenefits(string $slotKey, string $companyCity, bool $has3d, bool $isPersonalizable): array
    {
        $previewBenefit = $has3d
            ? 'Live im 3D-Konfigurator vor der Bestellung gestalten'
            : ($isPersonalizable ? 'Wunschgravur direkt online in der Live-Vorschau prüfen' : 'Sorgfältige Einzelprüfung vor dem Versand');

        return match ($slotKey) {
            'Weihnachten' => [
                $isPersonalizable ? 'Persönliches Weihnachtsgeschenk mit deiner Wunschgravur' : 'Stilvolles Weihnachtsgeschenk aus unserer Manufaktur',
                "Handveredelt in Deutschland (Manufaktur {$companyCity})",
                'Optional mit schwarzer Geschenkbox oder Schwarz-Gold-Geschenkpapier',
            ],
            'Valentinstag' => [
                $isPersonalizable ? 'Gemeinsame Erinnerung mit Wunschgravur zum Valentinstag' : 'Besonderes Geschenk für deinen Lieblingsmenschen',
                "Handveredelt in Deutschland (Manufaktur {$companyCity})",
                'Optional mit schwarzer Geschenkbox zum direkten Überreichen',
            ],
            'Muttertag' => [
                $isPersonalizable ? 'Bleibendes Erinnerungsstück für Mama mit Wunschgravur' : 'Wertschätzendes Geschenk zum Muttertag',
                $previewBenefit,
                "Handveredelt in {$companyCity} & optional als Geschenk verpackt",
            ],
            'Vatertag' => [
                $isPersonalizable ? 'Individuelles Unikat für Papa ab 1 Stück graviert' : 'Ausgewähltes Geschenk zum Vatertag',
                $previewBenefit,
                "Handveredelt & einzeln geprüft in der Manufaktur {$companyCity}",
            ],
            'Ostern' => [
                $isPersonalizable ? 'Bleibende Osterüberraschung mit persönlicher Lasergravur' : 'Wertiges Geschenk für das Osternest',
                $previewBenefit,
                "Handveredelt in Deutschland (Manufaktur {$companyCity})",
            ],
            'Hochzeit' => [
                $isPersonalizable ? 'Namen, Hochzeitsdatum oder Wunschmotiv dauerhaft verewigt' : 'Stilvolles Erinnerungsgeschenk zur Hochzeit',
                "Handveredelt in Deutschland (Manufaktur {$companyCity})",
                'Optional mit edler schwarzer Geschenkbox & Geschenkpapier',
            ],
            'Geburtstag' => [
                $isPersonalizable ? 'Persönliches Geburtstags-Unikat ab 1 Stück gestaltbar' : 'Besondere Geschenkidee zum Geburtstag',
                $previewBenefit,
                "Handveredelt in {$companyCity} & optional fertig als Geschenk verpackt",
            ],
            default => [
                $isPersonalizable ? 'Individuelle Lasergravur nach deinem Wunschmotiv ab 1 Stück' : 'Sorgfältig ausgewählt & einzeln geprüft',
                $previewBenefit,
                "Handveredelt in Deutschland (Manufaktur {$companyCity})",
            ],
        };
    }

    private static function resolveOccasionHighlightsSubtitle(string $productName, string $slotKey, string $companyCity): string
    {
        return match ($slotKey) {
            'Weihnachten'  => "Ein Weihnachtsgeschenk, das bleibt: Jedes Exemplar von „{$productName}“ wird einzeln in unserer Manufaktur in {$companyCity} veredelt.",
            'Valentinstag' => "Für gemeinsame Momente mit Bedeutung: „{$productName}“ wird nach deinem Entwurf in unserer Manufaktur in {$companyCity} veredelt.",
            'Muttertag'    => "Ein persönliches Dankeschön zum Muttertag: Jedes Stück von „{$productName}“ wird sorgfältig in {$companyCity} gefertigt.",
            'Vatertag'     => "Mit Präzision zum Vatertag gefertigt: „{$productName}“ wird individuell in unserer Manufaktur in {$companyCity} lasergraviert.",
            'Ostern'       => "Handwerk für besondere Familienmomente zu Ostern: „{$productName}“ entsteht einzeln in unserer Manufaktur in {$companyCity}.",
            'Hochzeit'     => "Für einen der schönsten Tage im Leben: „{$productName}“ wird mit höchster Sorgfalt in unserer Manufaktur in {$companyCity} veredelt.",
            'Geburtstag'   => "Ein persönliches Einzelstück zum Ehrentag: „{$productName}“ wird nach deiner Gestaltung in {$companyCity} veredelt.",
            default        => "Jedes Exemplar von „{$productName}“ wird nach deiner Gestaltung in unserer Manufaktur in {$companyCity} veredelt.",
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function resolveOccasionStoryHeader(string $slotKey, string $companyCity): array
    {
        return match ($slotKey) {
            'Weihnachten'  => ["Weihnachts-Manufaktur {$companyCity}", 'Ein persönliches Unikat unter dem Weihnachtsbaum'],
            'Valentinstag' => ["Manufaktur & Herzblut aus {$companyCity}", 'Eure gemeinsame Erinnerung dauerhaft festgehalten'],
            'Muttertag'    => ["Wertschätzung aus {$companyCity}", 'Mit Liebe gestaltet, einzeln für Mama veredelt'],
            'Vatertag'     => ["Präzisionsgravur aus {$companyCity}", 'Ein Einzelstück mit echtem Charakter zum Vatertag'],
            'Ostern'       => ["Frühlings-Manufaktur {$companyCity}", 'Bleibende Freude für das Osternest'],
            'Hochzeit'     => ["Hochzeits-Unikate aus {$companyCity}", 'Für das Brautpaar individuell veredelt'],
            'Geburtstag'   => ["Geschenk-Manufaktur {$companyCity}", 'Persönlich gestaltet für einen besonderen Ehrentag'],
            'Winter'       => ["Winter-Atelier {$companyCity}", 'Wärmende Erinnerungen in feiner Manufaktur-Qualität'],
            'Frühling'     => ["Frühlings-Atelier {$companyCity}", 'Frische Geschenkideen für besondere Anlässe'],
            'Sommer'       => ["Sommer-Atelier {$companyCity}", 'Für sonnige Meilensteine & besondere Feste gefertigt'],
            'Herbst'       => ["Herbst-Atelier {$companyCity}", 'Charakterstarke Unikate für goldene Herbstmomente'],
            default        => ["Manufaktur & Handwerk aus {$companyCity}", 'Individuell nach deinen Wünschen gefertigt'],
        };
    }

    private static function resolveOccasionCtaHeading(string $slotKey): string
    {
        return match ($slotKey) {
            'Weihnachten'  => 'Bereit für dein persönliches Weihnachtsgeschenk?',
            'Valentinstag' => 'Bereit für eine besondere Überraschung zum Valentinstag?',
            'Muttertag'    => 'Bereit für ein persönliches Geschenk zum Muttertag?',
            'Vatertag'     => 'Bereit für ein starkes Unikat zum Vatertag?',
            'Ostern'       => 'Bereit für eine bleibende Osterüberraschung?',
            'Hochzeit'     => 'Bereit für ein persönliches Hochzeitsgeschenk?',
            'Geburtstag'   => 'Bereit für ein unvergessliches Geburtstagsgeschenk?',
            default        => 'Bereit für echte Wertschätzung?',
        };
    }
}

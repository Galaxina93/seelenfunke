<?php

namespace App\Models\Product;

use App\Models\Product\ProductCategory;
use App\Services\PriceCalcService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'media_gallery' => 'array',
        'attributes' => 'array',
        'tier_pricing' => 'array',
        'configurator_settings' => 'array',
        'is_personalizable' => 'boolean',
        'three_d_active' => 'boolean',
        'track_quantity' => 'boolean',
        'continue_selling_when_out_of_stock' => 'boolean',
        'price' => 'integer',
        'compare_at_price' => 'integer',
        'weight' => 'integer',
        'height' => 'integer',
        'width' => 'integer',
        'length' => 'integer',
        'variants_data' => 'array',
        'packaging_weight' => 'integer',
        'marketing_cost_percent' => 'float',
    ];

    protected function priceEuro(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['price'] / 100,
            set: fn ($value) => [
                'price' => (int) round((float)str_replace(',', '.', (string) $value) * 100)
            ],
        );
    }

    public function getNetPriceAttribute(): int
    {
        $calculator = new PriceCalcService();
        if ($this->tax_included) {
            return $calculator->getNetFromGross($this->price, (float)$this->tax_rate);
        }
        return $this->price;
    }

    public function getGrossPriceAttribute(): int
    {
        $calculator = new PriceCalcService();
        if ($this->tax_included) {
            return $this->price;
        }
        return $calculator->getGrossFromNet($this->price, (float)$this->tax_rate);
    }

    public function getFormattedPriceAttribute()
    {
        return number_format($this->price / 100, 2, ',', '.') . ' €';
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'active' => 'bg-green-100 text-green-800',
            'draft' => 'bg-amber-100 text-amber-800',
            'archived' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100',
        };
    }

    public function getProgressColorAttribute()
    {
        return $this->completion_step >= 4 ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50';
    }

    public function getRouteKeyName() { return 'slug'; }

    public function getTaxRateAttribute(): float
    {
        if (shop_setting('is_small_business', false)) {
            return 0.00;
        }

        if ($this->tax_class === 'zero') {
            return 0.00;
        }

        $globalDefault = (float)shop_setting('default_tax_rate', 19.00);

        if ($this->tax_class === 'standard' || empty($this->tax_class)) {
            return $globalDefault;
        }

        if ($this->tax_class === 'reduced') {
            return (float)shop_setting('reduced_tax_rate', 7.00);
        }

        return $globalDefault;
    }

    public function isAvailable(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Bei digitalen Produkten oder Dienstleistungen ist "Lager" oft irrelevant
        // Wir prüfen trotzdem track_quantity, falls limitierte Plätze/Lizenzen verkauft werden
        if (!$this->track_quantity) {
            return true;
        }

        if ($this->continue_selling_when_out_of_stock) {
            return true;
        }

        return $this->quantity > 0;
    }

    /**
     * Liefert den aktuellen Herkunfts-Siegel-Modus des Produkts:
     * - 'made_in_germany'        -> "Made in Germany"
     * - 'handcrafted_in_germany' -> "Handveredelt in Deutschland"
     * - 'none'                   -> Kein Siegel
     */
    public function getOriginBadgeMode(): string
    {
        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        $explicit = $settings['origin_badge'] ?? null;

        if (in_array($explicit, ['made_in_germany', 'handcrafted_in_germany', 'none'], true)) {
            return $explicit;
        }

        if ($this->type !== 'physical') {
            return 'none';
        }

        // Logischer Standardwert für bestehende physische Produkte, falls noch nicht explizit gesetzt
        if (in_array($this->slug, ['weizenglas-personalisiert', 'goldenes-seelen-herz'], true)) {
            return 'made_in_germany';
        }

        return 'handcrafted_in_germany';
    }

    /**
     * Liefert den sichtbaren Text des Herkunfts-Siegels für Shop-Kacheln & Produktdetailseite (oder null bei 'none').
     */
    public function getOriginBadgeLabel(): ?string
    {
        return match ($this->getOriginBadgeMode()) {
            'made_in_germany'        => 'Made in Germany',
            'handcrafted_in_germany' => 'Handveredelt in Deutschland',
            default                  => null,
        };
    }

    public function tierPrices()
    {
        return $this->hasMany(ProductTierPrice::class)->orderBy('qty', 'asc');
    }

    public function getTaxIncludedAttribute(): bool
    {
        return (bool) shop_setting('prices_entered_gross', true);
    }

    /**
     * Prüft, ob eine einzelne Produktvariante kaufbar / auf Lager ist.
     */
    public function isVariantAvailable(array $variant): bool
    {
        if (!filter_var($variant['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (!$this->track_quantity || $this->continue_selling_when_out_of_stock) {
            return true;
        }

        if (isset($variant['stock']) && $variant['stock'] !== '' && $variant['stock'] !== null) {
            return (int) $variant['stock'] > 0;
        }

        return true;
    }

    /**
     * Liefert alle Varianten-bildenden Eigenschaften (nur Eigenschaften mit >= 2 kommagetrennten Werten,
     * die nicht explizit von der Varianten-Matrix ausgeschlossen wurden).
     *
     * @return array<string, array<int, string>>
     */
    public function getVariantAttributeOptions(): array
    {
        $rawAttributes = $this->getAttribute('attributes');
        if (is_string($rawAttributes)) {
            $rawAttributes = json_decode($rawAttributes, true);
        }
        $attributes = is_array($rawAttributes) ? $rawAttributes : [];
        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        $excluded = is_array($settings['excluded_variant_attributes'] ?? null) ? $settings['excluded_variant_attributes'] : [];
        $included = is_array($settings['included_variant_attributes'] ?? null) ? $settings['included_variant_attributes'] : [];

        $defaultInfoOnlyKeys = ['Gewicht', 'Lieferzeit', 'Auslieferung', 'Sprache', 'Seiten', 'Dauer', 'Ort', 'Experte', 'Herkunft'];

        $parsed = [];
        foreach ($attributes as $key => $val) {
            if (!is_string($key) || empty($val) || !is_scalar($val)) {
                continue;
            }
            if (in_array($key, $excluded, true)) {
                continue;
            }
            if (in_array($key, $defaultInfoOnlyKeys, true) && !in_array($key, $included, true)) {
                continue;
            }

            $options = array_values(array_unique(array_filter(
                array_map('trim', explode(',', (string) $val)),
                fn ($v) => $v !== ''
            )));

            // WICHTIG: Nur Eigenschaften mit mindestens 2 Optionen bilden Varianten!
            if (count($options) > 1) {
                $parsed[$key] = $options;
            }
        }

        return $parsed;
    }

    /**
     * Bereinigt bestehende Varianten-Einträge in variants_data von Einzelwert-Attributen
     * (wie z. B. "Größe: 180x200x40 mm" oder "Material: Kristallglas", die nur 1 Wert haben),
     * damit nur echte Varianten-Namen (z. B. "Transparent", "Matt", "Blickdicht") übrig bleiben.
     */
    public function getSanitizedVariantsData(bool $persistIfChanged = false): array
    {
        $rawVariants = $this->variants_data;
        if (!is_array($rawVariants) || empty($rawVariants)) {
            return [];
        }

        $validAttrKeys = array_keys($this->getVariantAttributeOptions());
        if (empty($validAttrKeys)) {
            return $rawVariants;
        }

        $changed = false;
        $cleaned = [];

        foreach ($rawVariants as $v) {
            if (!is_array($v)) {
                continue;
            }
            $vAttrs = is_array($v['attributes'] ?? null) ? $v['attributes'] : [];
            if (!empty($vAttrs)) {
                $filteredAttrs = array_intersect_key($vAttrs, array_flip($validAttrKeys));
                if (!empty($filteredAttrs) && count($filteredAttrs) !== count($vAttrs)) {
                    $ksorted = $filteredAttrs;
                    ksort($ksorted);
                    $v['attributes'] = $filteredAttrs;
                    $v['name'] = implode(' - ', $filteredAttrs);
                    $v['signature'] = implode('|', array_map(fn ($k, $val) => "$k:$val", array_keys($ksorted), $ksorted));
                    $changed = true;
                }
            }
            $cleaned[] = $v;
        }

        if ($changed && $persistIfChanged && $this->exists) {
            $this->variants_data = $cleaned;
            $this->saveQuietly();
        }

        return $cleaned;
    }

    /**
     * Liefert die auswählbaren Einzel-Varianten aus der Matrix für Produktbilder unter "Medien" (Schritt 2).
     *
     * @return array<string, string>
     */
    public function getMediaVariantBindingOptions(): array
    {
        $variants = [];

        foreach ($this->getSanitizedVariantsData(true) as $v) {
            if (!is_array($v)) {
                continue;
            }
            if (!empty($v['id']) && !empty($v['name'])) {
                $variants['var:' . $v['id']] = (string) $v['name'];
            }
        }

        return $variants;
    }

    /**
     * Übersetzt einen gespeicherten variant_binding-String in ein lesbares Label.
     */
    public function resolveMediaVariantBindingLabel(?string $binding): ?string
    {
        if (!$binding || trim($binding) === '') {
            return null;
        }

        $varId = str_starts_with($binding, 'var:') ? substr($binding, 4) : $binding;
        $variant = collect($this->getSanitizedVariantsData(false))->firstWhere('id', $varId);

        return $variant['name'] ?? null;
    }

    public function reduceStock(int $amount, ?string $variantId = null): bool
    {
        if ($amount <= 0) {
            return false;
        }

        // 1. Varianten-Bestand reduzieren, falls eine Variante mit eigenem Bestand gekauft wurde
        if ($variantId && is_array($this->variants_data) && count($this->variants_data) > 0) {
            $variants = $this->variants_data;
            $variantUpdated = false;

            foreach ($variants as $idx => $variant) {
                if (($variant['id'] ?? null) === $variantId) {
                    if (isset($variant['stock']) && $variant['stock'] !== '' && $variant['stock'] !== null) {
                        $currentStock = (int) $variant['stock'];
                        if ($this->track_quantity && !$this->continue_selling_when_out_of_stock && $currentStock < $amount) {
                            return false;
                        }
                        $variants[$idx]['stock'] = max(0, $currentStock - $amount);
                        $variantUpdated = true;
                    }
                    break;
                }
            }

            if ($variantUpdated) {
                $this->variants_data = $variants;
                $this->saveQuietly();
            }
        }

        if (!$this->track_quantity) {
            return true;
        }

        if (!$this->continue_selling_when_out_of_stock) {
            $affected = static::where('id', $this->id)
                ->where('quantity', '>=', $amount)
                ->decrement('quantity', $amount);

            if ($affected <= 0) {
                return false;
            }

            $this->quantity = (int) static::where('id', $this->id)->value('quantity');
            return true;
        }

        return (bool) $this->decrement('quantity', $amount);
    }

    public function restoreStock(int $amount, ?string $variantId = null): void
    {
        if ($amount <= 0) {
            return;
        }

        if ($variantId && is_array($this->variants_data) && count($this->variants_data) > 0) {
            $variants = $this->variants_data;
            $variantUpdated = false;

            foreach ($variants as $idx => $variant) {
                if (($variant['id'] ?? null) === $variantId) {
                    if (isset($variant['stock']) && $variant['stock'] !== '' && $variant['stock'] !== null) {
                        $variants[$idx]['stock'] = max(0, (int) $variant['stock'] + $amount);
                        $variantUpdated = true;
                    }
                    break;
                }
            }

            if ($variantUpdated) {
                $this->variants_data = $variants;
                $this->saveQuietly();
            }
        }

        if ($this->track_quantity) {
            $this->increment('quantity', $amount);
        }
    }

    public function increaseStock(int $amount, ?string $variantId = null): void
    {
        $this->restoreStock($amount, $variantId);
    }

    // --- NEUE TYP-LOGIK ---

    public function isPersonalizable(): bool
    {
        return (bool) $this->is_personalizable;
    }

    public function isPhysical(): bool
    {
        return $this->type === 'physical';
    }

    public function isDigital(): bool
    {
        return $this->type === 'digital';
    }

    public function isService(): bool
    {
        return $this->type === 'service';
    }

    public function hasDigitalFile(): bool
    {
        return !empty($this->digital_download_path);
    }

    /**
     * Zentrale Prüfung, ob es sich um einen Geschenkgutschein handelt (entweder über den Slug oder die Artikelkonfiguration).
     */
    public function isGiftVoucher(?array $configuration = null): bool
    {
        return ($this->slug === 'geschenkgutschein') || (!empty($configuration['is_gift_voucher']));
    }

    /**
     * Zentrale Prüfung, ob das Produkt aktuell im automatischen Feiertags-/Aktions-Autopilot-Sale reduziert ist.
     * Solche Artikel sind laut Shop-Regelwerk nicht mit zusätzlichen Rabattcodes kombinierbar.
     */
    public function isInAutopilotSale(): bool
    {
        return !empty($this->configurator_settings['autopilot_base_price'])
            && (int) $this->compare_at_price > (int) $this->price;
    }

    /**
     * Zentrale Prüfung, ob auf dieses Produkt ein prozentualer oder fester Rabattcode (inkl. Influencer-Follower-Code)
     * im Warenkorb angewendet werden darf.
     */
    public function isDiscountableWithCoupon(?array $configuration = null): bool
    {
        return !$this->isGiftVoucher($configuration) && !$this->isInAutopilotSale();
    }

    /**
     * Zentrale Prüfung, ob ein Produkt aktuell von Influencern/Creatorn beworben werden kann:
     * Aktiv, kaufbar/auf Lager (isAvailable) und kein Geschenkgutschein.
     */
    public function isPromotableByInfluencer(): bool
    {
        return $this->status === 'active'
            && $this->isAvailable()
            && !$this->isGiftVoucher();
    }

    // Relationship: Product belongs to many Categories
    public function categories()
    {
        return $this->belongsToMany(ProductCategory::class);
    }

    public function supplier()
    {
        return $this->belongsTo(ProductSupplier::class, 'product_supplier_id');
    }

    public function packagings()
    {
        return $this->hasMany(ProductPackaging::class);
    }

    public function templates()
    {
        return $this->hasMany(ProductTemplate::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function orderItems()
    {
        return $this->hasMany(\App\Models\Order\OrderOrderItem::class, 'product_id');
    }

    public function getAverageRatingAttribute()
    {
        if (array_key_exists('approved_reviews_avg', $this->attributes)) {
            return round((float) ($this->attributes['approved_reviews_avg'] ?? 0), 1);
        }
        if ($this->relationLoaded('reviews')) {
            return round((float) ($this->reviews->where('status', 'approved')->avg('rating') ?? 0), 1);
        }
        // Nur freigegebene Bewertungen zählen!
        return round($this->reviews()->where('status', 'approved')->avg('rating') ?? 0, 1);
    }

    public function getReviewCountAttribute()
    {
        if (array_key_exists('approved_reviews_count', $this->attributes)) {
            return (int) ($this->attributes['approved_reviews_count'] ?? 0);
        }
        if ($this->relationLoaded('reviews')) {
            return $this->reviews->where('status', 'approved')->count();
        }
        // Nur freigegebene Bewertungen zählen!
        return $this->reviews()->where('status', 'approved')->count();
    }

    public function landingPage()
    {
        return $this->hasOne(\App\Models\Marketing\MarketingLandingPage::class);
    }

    /**
     * Prüft, ob für dieses Produkt das Hauptbild automatisch passend zur Jahreszeit / zum Feiertag
     * aus den Produktvorlagen gesetzt werden soll.
     */
    public function isAutoSeasonalMainImageEnabled(): bool
    {
        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        if (array_key_exists('auto_seasonal_main_image', $settings)) {
            return filter_var($settings['auto_seasonal_main_image'], FILTER_VALIDATE_BOOLEAN);
        }

        return $this->isPersonalizable();
    }

    /**
     * Liefert die priorisierte Liste der Feiertags-/Anlass-Namen (aus ProductTemplate::holiday)
     * passend zum aktuellen Feiertagsfenster bzw. zur aktiven Jahreszeit (oder zu einem historischen Kaufdatum $referenceDate).
     */
    public static function resolvePreferredHolidayCandidates(?string $explicitHoliday = null, ?\Carbon\CarbonInterface $referenceDate = null): array
    {
        $candidates = [];

        if ($explicitHoliday !== null && trim($explicitHoliday) !== '') {
            $candidates[] = trim($explicitHoliday);
        }

        try {
            $autopilot = app(\App\Services\Marketing\HolidayOfferAutopilotService::class);
            $now = $referenceDate ? \Carbon\Carbon::instance($referenceDate) : \Carbon\Carbon::now();

            // 1. Prüfe aktives Angebotsfenster oder Feiertag bis inkl. Feiertags-Tag zum Zeitpunkt $now
            foreach ($autopilot->getEventDefinitions() as $eventKey => $def) {
                foreach ([$now->year, $now->year - 1, $now->year + 1] as $yr) {
                    $eventDate = $autopilot->getHolidayDate($eventKey, $yr);
                    if (!$eventDate) {
                        continue;
                    }
                    $startDaysBefore = max(14, (int) ($def['start_days_before'] ?? 14));
                    $winStart = $eventDate->copy()->subDays($startDaysBefore)->startOfDay();
                    $winEnd = $eventDate->copy()->addDays(1)->endOfDay();

                    if ($now->between($winStart, $winEnd)) {
                        $mapped = match ($eventKey) {
                            'valentines'  => ['Valentinstag', 'Hochzeit'],
                            'womens_day'  => ['Muttertag', 'Valentinstag', 'Ostern'],
                            'easter'      => ['Ostern', 'Muttertag'],
                            'mothers_day' => ['Muttertag', 'Hochzeit'],
                            'fathers_day' => ['Vatertag', 'Geburtstag'],
                            'sale_summer' => ['Hochzeit', 'Geburtstag'],
                            'halloween'   => ['Halloween', 'Geburtstag'],
                            'advent_1', 'christmas', 'sale_winter' => ['Weihnachten'],
                            'new_year'    => ['Geburtstag', 'Weihnachten'],
                            default       => [],
                        };
                        foreach ($mapped as $m) {
                            $candidates[] = $m;
                        }
                    }
                }
            }

            // 2. Fallback über die aktive Jahreszeit (heute aus /admin/configuration, bei historischen Bestellungen aus dem Kaufmonat)
            if ($referenceDate === null || $now->isSameDay(\Carbon\Carbon::now())) {
                $seasonKey = app(\App\Services\ConfiguratorService::class)->getActiveSeasonKey();
            } else {
                $seasonKey = match ((int) $now->month) {
                    3, 4, 5  => 'ostern',
                    6, 7, 8  => 'sommer',
                    9, 10    => 'herbst',
                    default  => 'winter',
                };
            }
            $seasonFallback = match ($seasonKey) {
                'ostern' => ['Ostern', 'Muttertag', 'Vatertag', 'Hochzeit'],
                'sommer' => ['Hochzeit', 'Geburtstag', 'Vatertag', 'Muttertag'],
                'herbst' => ['Halloween', 'Geburtstag', 'Hochzeit'],
                'winter' => ['Weihnachten', 'Valentinstag', 'Geburtstag'],
                default  => ['Halloween', 'Weihnachten', 'Ostern', 'Hochzeit'],
            };
            foreach ($seasonFallback as $s) {
                $candidates[] = $s;
            }
        } catch (\Throwable $e) {
            // Fallback bei DB-/Service-Bootstrapping
            $candidates = array_merge($candidates, ['Halloween', 'Weihnachten', 'Ostern', 'Hochzeit', 'Geburtstag']);
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Findet die passende aktive Produktvorlage für das saisonale / Feiertags-Hauptbild.
     * Optional kann nach einer spezifischen Produktvariante ($variantId) oder einem Referenzdatum ($referenceDate) gefiltert werden.
     */
    public function resolveSeasonalTemplate(?string $explicitHoliday = null, array $excludeImagePaths = [], ?string $variantId = null, ?\Carbon\CarbonInterface $referenceDate = null): ?ProductTemplate
    {
        if ($explicitHoliday === null && !$this->isAutoSeasonalMainImageEnabled()) {
            return null;
        }

        $activeTemplates = $this->relationLoaded('templates')
            ? $this->templates->where('is_active', true)->filter(fn($t) => !empty($t->preview_image))
            : $this->templates()->where('is_active', true)->whereNotNull('preview_image')->where('preview_image', '!=', '')->get();

        if ($activeTemplates->isEmpty()) {
            return null;
        }

        if ($variantId !== null && $variantId !== '') {
            $activeTemplates = $activeTemplates->filter(function (ProductTemplate $tpl) use ($variantId) {
                $cfg = is_array($tpl->configuration) ? $tpl->configuration : [];
                return ($cfg['variant_id'] ?? null) === $variantId;
            });

            if ($activeTemplates->isEmpty()) {
                return null;
            }
        }

        $candidates = self::resolvePreferredHolidayCandidates($explicitHoliday, $referenceDate);

        foreach ($candidates as $candidate) {
            $normCandidate = mb_strtolower(trim($candidate));
            $found = $activeTemplates->first(function (ProductTemplate $tpl) use ($normCandidate, $excludeImagePaths) {
                if (in_array($tpl->preview_image, $excludeImagePaths, true)) {
                    return false;
                }
                return mb_strtolower(trim((string) $tpl->holiday)) === $normCandidate;
            });

            if ($found) {
                return $found;
            }
        }

        // Letzter Fallback: Erste aktive Vorlage, deren Bild noch nicht ausgeschlossen ist
        return $activeTemplates->first(fn(ProductTemplate $tpl) => !in_array($tpl->preview_image, $excludeImagePaths, true));
    }

    /**
     * Ermittelt das passende Produktbild für eine bestimmte Produktvariante ($variantId):
     * 1. Aktive (saisonale) Produktvorlage, die explizit dieser Variante zugewiesen ist
     * 2. Galeriebild unter "Medien" mit direkter Varianten-Zuweisung ("var:{variantId}")
     * 3. Galeriebild unter "Medien" mit Eigenschafts-Gruppen-Zuweisung ("attr:{Attribut}:{Wert}")
     */
    public function resolveVariantImageMeta(?string $variantId, ?string $explicitHoliday = null, ?\Carbon\CarbonInterface $referenceDate = null): ?array
    {
        if (!$variantId || trim($variantId) === '') {
            return null;
        }

        $variant = collect($this->variants_data ?? [])->firstWhere('id', $variantId);
        if (!$variant || !is_array($variant)) {
            return null;
        }

        // 1. Prüfe aktive Produktvorlage speziell für diese Variante
        if ($this->isAutoSeasonalMainImageEnabled()) {
            $variantTemplate = $this->resolveSeasonalTemplate($explicitHoliday, [], $variantId, $referenceDate);
            if ($variantTemplate && !empty($variantTemplate->preview_image)) {
                return [
                    'path'            => $variantTemplate->preview_image,
                    'url'             => ProductTemplate::resolveImageUrl($variantTemplate->preview_image),
                    'is_ai_generated' => (bool) ($variantTemplate->is_ai_generated ?? true),
                    'label'           => $variantTemplate->name . ' (' . ($variant['name'] ?? '') . ')',
                ];
            }
        }

        $gallery = is_array($this->media_gallery) ? $this->media_gallery : [];

        // 2. Prüfe Galeriebilder mit direkter Einzelvarianten-Bindung ("var:{variantId}")
        $targetVarBinding = 'var:' . $variantId;
        foreach ($gallery as $media) {
            if (!is_array($media) || ($media['type'] ?? 'image') !== 'image' || empty($media['path'])) {
                continue;
            }
            if (($media['variant_binding'] ?? '') === $targetVarBinding) {
                return [
                    'path'            => $media['path'],
                    'url'             => ProductTemplate::resolveImageUrl($media['path']),
                    'is_ai_generated' => filter_var($media['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'label'           => $variant['name'] ?? $this->name,
                ];
            }
        }

        // 3. Prüfe Galeriebilder mit Eigenschafts-Gruppen-Bindung ("attr:{Key}:{Value}")
        $variantAttrs = is_array($variant['attributes'] ?? null) ? $variant['attributes'] : [];
        foreach ($variantAttrs as $attrKey => $attrVal) {
            $targetAttrBinding = 'attr:' . trim((string) $attrKey) . ':' . trim((string) $attrVal);
            foreach ($gallery as $media) {
                if (!is_array($media) || ($media['type'] ?? 'image') !== 'image' || empty($media['path'])) {
                    continue;
                }
                if (($media['variant_binding'] ?? '') === $targetAttrBinding) {
                    return [
                        'path'            => $media['path'],
                        'url'             => ProductTemplate::resolveImageUrl($media['path']),
                        'is_ai_generated' => filter_var($media['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'label'           => "{$attrKey}: {$attrVal}",
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Liefert strukturierte Metadaten zum aufgelösten Hauptbild (Pfad, URL, KI-Kennzeichnung, Label).
     */
    public function resolveMainImageMeta(?string $explicitHoliday = null, bool $allowSeasonal = true, array $excludeImagePaths = [], ?\Carbon\CarbonInterface $referenceDate = null): array
    {
        if ($allowSeasonal) {
            // 1. Prüfe aktive Produktvorlagen (ProductTemplate)
            $seasonalTpl = $this->resolveSeasonalTemplate($explicitHoliday, $excludeImagePaths, null, $referenceDate);
            if ($seasonalTpl && !empty($seasonalTpl->preview_image)) {
                return [
                    'path'            => $seasonalTpl->preview_image,
                    'url'             => ProductTemplate::resolveImageUrl($seasonalTpl->preview_image),
                    'is_ai_generated' => (bool) ($seasonalTpl->is_ai_generated ?? true),
                    'label'           => $seasonalTpl->name . ($seasonalTpl->holiday ? ' (' . $seasonalTpl->holiday . ')' : ''),
                ];
            }

            // 2. Falls keine Vorlagen existieren (z. B. beim Geschenkgutschein): Prüfe saisonale Bilder in der media_gallery
            if ($this->isAutoSeasonalMainImageEnabled() && is_array($this->media_gallery) && count($this->media_gallery) > 0) {
                $candidates = self::resolvePreferredHolidayCandidates($explicitHoliday, $referenceDate);
                foreach ($candidates as $candidate) {
                    $normCandidate = mb_strtolower(trim($candidate));
                    foreach ($this->media_gallery as $media) {
                        if (!is_array($media) || ($media['type'] ?? 'image') !== 'image' || empty($media['path'])) {
                            continue;
                        }
                        if (in_array($media['path'], $excludeImagePaths, true)) {
                            continue;
                        }
                        $mediaHoliday = mb_strtolower(trim((string) ($media['holiday'] ?? '')));
                        $filename = mb_strtolower(basename($media['path']));
                        if ($mediaHoliday === $normCandidate || str_contains($filename, '-' . $normCandidate . '.')) {
                            return [
                                'path'            => $media['path'],
                                'url'             => ProductTemplate::resolveImageUrl($media['path']),
                                'is_ai_generated' => filter_var($media['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN),
                                'label'           => $media['holiday'] ?? ucfirst($normCandidate),
                            ];
                        }
                    }
                }
            }
        }

        if (is_array($this->media_gallery) && count($this->media_gallery) > 0) {
            foreach ($this->media_gallery as $media) {
                if (is_array($media) && ($media['type'] ?? 'image') === 'image' && !empty($media['path'])) {
                    if (!in_array($media['path'], $excludeImagePaths, true)) {
                        return [
                            'path'            => $media['path'],
                            'url'             => ProductTemplate::resolveImageUrl($media['path']),
                            'is_ai_generated' => filter_var($media['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN),
                            'label'           => $media['alt'] ?? $this->name,
                        ];
                    }
                } elseif (is_string($media) && $media !== '' && !in_array($media, $excludeImagePaths, true)) {
                    return [
                        'path'            => $media,
                        'url'             => ProductTemplate::resolveImageUrl($media),
                        'is_ai_generated' => true,
                        'label'           => $this->name,
                    ];
                }
            }
        }

        if (!empty($this->preview_image_path) && !in_array($this->preview_image_path, $excludeImagePaths, true)) {
            return [
                'path'            => $this->preview_image_path,
                'url'             => ProductTemplate::resolveImageUrl($this->preview_image_path),
                'is_ai_generated' => false,
                'label'           => $this->name,
            ];
        }

        return [
            'path'            => null,
            'url'             => null,
            'is_ai_generated' => false,
            'label'           => null,
        ];
    }

    /**
     * Liefert den relativen Storage-Pfad des Hauptbildes (entweder saisonale Vorlage oder manuelles Galerie-Hauptbild).
     */
    public function resolveMainImagePath(?string $explicitHoliday = null, bool $allowSeasonal = true, array $excludeImagePaths = [], ?\Carbon\CarbonInterface $referenceDate = null): ?string
    {
        return $this->resolveMainImageMeta($explicitHoliday, $allowSeasonal, $excludeImagePaths, $referenceDate)['path'];
    }

    /**
     * Liefert die fertige, Cache-sichere öffentliche URL des Produkt-Hauptbildes.
     */
    public function resolveMainImageUrl(?string $explicitHoliday = null, bool $allowSeasonal = true, array $excludeImagePaths = [], ?\Carbon\CarbonInterface $referenceDate = null): ?string
    {
        return $this->resolveMainImageMeta($explicitHoliday, $allowSeasonal, $excludeImagePaths, $referenceDate)['url'];
    }

    /**
     * Ermittelt den relativen Bildpfad unter Berücksichtigung einer Artikel-Konfiguration
     * (eingefrorenes Kaufbild 'purchased_image_path', gewählte Variante 'variant_id' oder saisonales Hauptbild zum Referenzdatum).
     */
    public function resolveConfigurationImagePath(?array $configuration = null, ?\Carbon\CarbonInterface $referenceDate = null): ?string
    {
        $conf = is_array($configuration) ? $configuration : [];

        if (!empty($conf['purchased_image_path']) && is_string($conf['purchased_image_path'])) {
            return $conf['purchased_image_path'];
        }

        $explicitHoliday = !empty($conf['purchased_holiday']) && is_string($conf['purchased_holiday'])
            ? $conf['purchased_holiday']
            : null;

        if (!empty($conf['variant_id']) && is_string($conf['variant_id'])) {
            $varMeta = $this->resolveVariantImageMeta($conf['variant_id'], $explicitHoliday, $referenceDate);
            if (!empty($varMeta['path'])) {
                return $varMeta['path'];
            }
        }

        return $this->resolveMainImagePath($explicitHoliday, true, [], $referenceDate);
    }

    /**
     * Ermittelt die fertige Bild-URL unter Berücksichtigung einer Artikel-Konfiguration.
     */
    public function resolveConfigurationImageUrl(?array $configuration = null, ?\Carbon\CarbonInterface $referenceDate = null): ?string
    {
        $path = $this->resolveConfigurationImagePath($configuration, $referenceDate);
        return $path ? ProductTemplate::resolveImageUrl($path) : null;
    }

    /**
     * Ermittelt verkaufspsychologisch sortierte Empfehlungen für die Produktdetailseite:
     * 1. Stufe ("Das könnte dir auch gefallen"): Nur aktive Produkte, die mindestens eine Kategorie
     *    mit dem aktuellen Produkt teilen (sortiert nach Verfügbarkeit, Kategorie-Überschneidungstiefe,
     *    Produkttyp-Affinität und Bewertung).
     * 2. Stufe Fallback ("Weitere Produkte aus unserem Sortiment"): Falls keine anderen aktiven Produkte
     *    in derselben Kategorie existieren, werden andere verfügbare Produkte aus dem Gesamtsortiment geladen.
     *
     * @return array{mode: string, title: string, subtitle: string, kicker: string, products: \Illuminate\Support\Collection}
     */
    public function resolveCrossSellProducts(int $limit = 4): array
    {
        $categoryIds = $this->relationLoaded('categories')
            ? $this->categories->pluck('id')->all()
            : $this->categories()->pluck('product_categories.id')->all();

        $baseQuery = fn () => static::where('status', 'active')
            ->where('id', '!=', $this->id)
            ->with([
                'categories',
                'tierPrices',
                'templates' => fn ($q) => $q->where('is_active', true),
            ])
            ->withCount([
                'reviews as approved_reviews_count' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->withAvg([
                'reviews as approved_reviews_avg' => fn ($q) => $q->where('status', 'approved'),
            ], 'rating');

        if (!empty($categoryIds)) {
            $categoryMatches = $baseQuery()
                ->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('product_categories.id', $categoryIds);
                })
                ->get();

            if ($categoryMatches->isNotEmpty()) {
                $sortedMatches = $categoryMatches->sortByDesc(function (self $candidate) use ($categoryIds) {
                    $availableScore = $candidate->isAvailable() ? 10000 : 0;
                    $overlapCount = $candidate->categories->whereIn('id', $categoryIds)->count();
                    $sameTypeScore = ($candidate->type === $this->type && $candidate->slug !== 'geschenkgutschein') ? 500 : 0;
                    $ratingScore = (int) round(((float) ($candidate->approved_reviews_avg ?? 0)) * 20)
                                 + min(50, (int) ($candidate->approved_reviews_count ?? 0));

                    return $availableScore + ($overlapCount * 1000) + $sameTypeScore + $ratingScore;
                })->take($limit)->values();

                $categoryNames = $this->relationLoaded('categories')
                    ? $this->categories->pluck('name')->take(2)->implode(' & ')
                    : $this->categories()->pluck('name')->take(2)->implode(' & ');

                return [
                    'mode'     => 'category',
                    'kicker'   => $categoryNames !== '' ? 'Passend zu ' . $categoryNames : 'Passend ausgewählt',
                    'title'    => 'Das könnte dir auch gefallen',
                    'subtitle' => 'Entdecke weitere liebevoll veredelte Geschenkideen aus derselben Kategorie.',
                    'products' => $sortedMatches,
                ];
            }
        }

        // Fallback: Keine Produkte in derselben Kategorie vorhanden -> "Weitere Produkte aus unserem Sortiment"
        $fallbackProducts = $baseQuery()
            ->get()
            ->sortByDesc(function (self $candidate) {
                $availableScore = $candidate->isAvailable() ? 10000 : 0;
                $physicalScore = ($candidate->type === 'physical' && $candidate->slug !== 'geschenkgutschein') ? 500 : 0;
                $ratingScore = (int) round(((float) ($candidate->approved_reviews_avg ?? 0)) * 20)
                             + min(50, (int) ($candidate->approved_reviews_count ?? 0));

                return $availableScore + $physicalScore + $ratingScore;
            })
            ->take($limit)
            ->values();

        return [
            'mode'     => 'assortment',
            'kicker'   => 'Blick in die Manufaktur',
            'title'    => 'Weitere Produkte aus unserem Sortiment',
            'subtitle' => 'Entdecke weitere handveredelte Unikate und Geschenkideen aus unserer Kollektion.',
            'products' => $fallbackProducts,
        ];
    }

    /**
     * Berechnet die kalkulatorische Zahlungs-/Stripe-Gebühr in Cent auf Basis des Brutto-Verkaufspreises.
     * Standard: 2,5 % + 0,30 € (30 Cent) als Mischsatz für Stripe (Kreditkarte, Klarna, Apple Pay, Google Pay).
     */
    public static function computeStripeFeeCents(int $grossCents): int
    {
        if ($grossCents <= 0) {
            return 0;
        }

        $feePercent = (float) shop_setting('stripe_fee_percent', 2.50);
        $feeFixedCents = (int) shop_setting('stripe_fee_fixed_cents', 30);

        return (int) round(($grossCents * ($feePercent / 100)) + $feeFixedCents);
    }

    public function calculatePaymentFeeCents(?int $grossPriceCents = null): int
    {
        $gross = $grossPriceCents ?? (int) $this->gross_price;
        return self::computeStripeFeeCents($gross);
    }

    /**
     * Erzeugt den vollständigen Schema.org JSON-LD Graphen (BreadcrumbList + Product inkl. Offer,
     * OfferShippingDetails, MerchantReturnPolicy, AggregateRating & Reviews) für Produkt- und Landingpages.
     */
    public function buildStructuredDataSchema(?string $canonicalUrl = null): array
    {
        $this->loadMissing([
            'categories',
            'tierPrices',
            'reviews' => fn ($q) => $q->where('status', 'approved')->with('customer')->latest(),
        ]);

        $canonicalUrl = $canonicalUrl ?: route('product.show', $this->slug);
        $mainImageUrl = $this->resolveMainImageUrl(null, false)
            ?: ($this->resolveMainImageUrl() ?: asset('shop/projekt/logo/mein-seelenfunke-logo.png'));

        $schemaImages = [$mainImageUrl];
        if (is_array($this->media_gallery)) {
            foreach ($this->media_gallery as $media) {
                if (is_array($media) && ($media['type'] ?? 'image') === 'image' && !empty($media['path'])) {
                    $imgUrl = ProductTemplate::resolveImageUrl($media['path']);
                    if ($imgUrl && !in_array($imgUrl, $schemaImages, true)) {
                        $schemaImages[] = $imgUrl;
                    }
                }
            }
        }

        $additionalProperties = [];
        $essentialAttributes = \App\Models\Marketing\MarketingLandingPage::extractEssentialAttributes($this);
        foreach ($essentialAttributes as $attrKey => $attrVal) {
            if (is_scalar($attrVal) && trim((string) $attrVal) !== '') {
                $additionalProperties[] = [
                    '@type' => 'PropertyValue',
                    'name' => (string) $attrKey,
                    'value' => (string) $attrVal,
                ];
            }
        }

        $shippingCostEur = number_format(((int) shop_setting('shipping_cost', 490)) / 100, 2, '.', '');

        $returnPolicy = $this->isPersonalizable()
            ? [
                '@type' => 'MerchantReturnPolicy',
                'applicableCountry' => 'DE',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
                'description' => 'Individuell nach Kundenwunsch gravierte Unikate sind gemäß § 312g Abs. 2 Nr. 1 BGB vom Widerruf ausgeschlossen; bei Transportschäden oder Mängeln erfolgt kostenfreier Ersatz.',
            ]
            : [
                '@type' => 'MerchantReturnPolicy',
                'applicableCountry' => 'DE',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                'merchantReturnDays' => 14,
                'returnMethod' => 'https://schema.org/ReturnByMail',
            ];

        $shippingDetails = [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => $this->isPhysical() ? $shippingCostEur : '0.00',
                'currency' => 'EUR',
            ],
            'shippingDestination' => [
                '@type' => 'DefinedRegion',
                'addressCountry' => 'DE',
            ],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 1,
                    'maxValue' => 3,
                    'unitCode' => 'DAY',
                ],
                'transitTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 1,
                    'maxValue' => 3,
                    'unitCode' => 'DAY',
                ],
            ],
        ];

        $basePriceEur = number_format($this->price / 100, 2, '.', '');
        $priceValidUntil = now()->addYear()->format('Y-m-d');
        $availabilityUrl = $this->isAvailable()
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        $activeVariants = array_values(array_filter(
            $this->getSanitizedVariantsData(false),
            fn ($v) => is_array($v) && filter_var($v['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN)
        ));

        if (count($activeVariants) > 1) {
            $variantOffers = [];
            $pricesFloat = [];
            foreach ($activeVariants as $v) {
                $varPriceFloat = (isset($v['price']) && $v['price'] !== '')
                    ? (float) $v['price']
                    : ($this->price / 100);
                $pricesFloat[] = $varPriceFloat;
                $variantOffers[] = [
                    '@type' => 'Offer',
                    'name' => (string) ($v['name'] ?? $this->name),
                    'sku' => (string) ($v['sku'] ?? ($this->sku ?: ('MSF-' . strtoupper(substr((string) $this->id, 0, 8))))),
                    'url' => $canonicalUrl,
                    'priceCurrency' => 'EUR',
                    'price' => number_format($varPriceFloat, 2, '.', ''),
                    'priceValidUntil' => $priceValidUntil,
                    'availability' => $this->isVariantAvailable($v)
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'itemCondition' => 'https://schema.org/NewCondition',
                    'seller' => [
                        '@id' => url('/#organization'),
                    ],
                    'hasMerchantReturnPolicy' => $returnPolicy,
                    'shippingDetails' => $shippingDetails,
                ];
            }

            $offersNode = [
                '@type' => 'AggregateOffer',
                'url' => $canonicalUrl,
                'priceCurrency' => 'EUR',
                'lowPrice' => number_format(min($pricesFloat), 2, '.', ''),
                'highPrice' => number_format(max($pricesFloat), 2, '.', ''),
                'price' => $basePriceEur,
                'offerCount' => count($variantOffers),
                'availability' => $availabilityUrl,
                'offers' => $variantOffers,
            ];
        } else {
            $offersNode = [
                '@type' => 'Offer',
                'url' => $canonicalUrl,
                'priceCurrency' => 'EUR',
                'price' => $basePriceEur,
                'priceValidUntil' => $priceValidUntil,
                'availability' => $availabilityUrl,
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => [
                    '@id' => url('/#organization'),
                ],
                'hasMerchantReturnPolicy' => $returnPolicy,
                'shippingDetails' => $shippingDetails,
            ];
        }

        $resolvedSku = $this->sku ?: ('MSF-' . strtoupper(substr((string) $this->id, 0, 8)));

        $productNode = [
            '@type' => 'Product',
            '@id' => $canonicalUrl . '#product',
            'name' => $this->name,
            'description' => trim(preg_replace('/\s+/', ' ', strip_tags((string) ($this->description ?: $this->short_description)))),
            'sku' => $resolvedSku,
            'mpn' => $resolvedSku,
            'isFamilyFriendly' => true,
            'url' => $canonicalUrl,
            'image' => $schemaImages,
            'brand' => [
                '@type' => 'Brand',
                'name' => shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')),
            ],
            'manufacturer' => [
                '@id' => url('/#organization'),
            ],
            'countryOfOrigin' => 'DE',
            'offers' => $offersNode,
        ];

        if ($this->width > 0) {
            $productNode['width'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $this->width,
                'unitCode' => 'MMT',
            ];
        }
        if ($this->height > 0) {
            $productNode['height'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $this->height,
                'unitCode' => 'MMT',
            ];
        }
        if ($this->length > 0) {
            $productNode['depth'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $this->length,
                'unitCode' => 'MMT',
            ];
        }
        if ($this->weight > 0) {
            $productNode['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $this->weight,
                'unitCode' => 'GRM',
            ];
        }

        if (is_array($this->media_gallery)) {
            foreach ($this->media_gallery as $media) {
                if (is_array($media) && ($media['type'] ?? '') === 'video' && !empty($media['path'])) {
                    $videoUrl = asset('storage/' . ltrim((string) $media['path'], '/'));
                    $productNode['subjectOf'] = [
                        '@type' => 'VideoObject',
                        'name' => $media['alt'] ?? ($this->name . ' – Produktvideo & Lasergravur'),
                        'description' => trim(preg_replace('/\s+/', ' ', strip_tags((string) ($this->short_description ?: $this->description)))),
                        'thumbnailUrl' => [$mainImageUrl],
                        'contentUrl' => $videoUrl,
                        'uploadDate' => optional($this->updated_at ?? $this->created_at)->toAtomString() ?: now()->toAtomString(),
                        'duration' => 'PT8S',
                        'isFamilyFriendly' => true,
                    ];
                    break;
                }
            }
        }

        if (!empty($additionalProperties)) {
            $productNode['additionalProperty'] = $additionalProperties;
            if (!empty($essentialAttributes['Material'])) {
                $productNode['material'] = (string) $essentialAttributes['Material'];
            }
        }

        if ($this->categories->isNotEmpty()) {
            $productNode['category'] = $this->categories->pluck('name')->implode(', ');
        }

        $approvedReviews = $this->reviews;
        if ($approvedReviews && $approvedReviews->count() > 0) {
            $productNode['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) round($approvedReviews->avg('rating'), 1),
                'reviewCount' => (int) $approvedReviews->count(),
                'bestRating' => '5',
                'worstRating' => '1',
            ];

            $reviewNodes = [];
            foreach ($approvedReviews->take(5) as $rev) {
                $authorName = $rev->is_anonymous
                    ? 'Anonymer Kunde'
                    : ($rev->customer_name
                        ?: ($rev->customer ? trim($rev->customer->first_name . ' ' . substr((string) $rev->customer->last_name, 0, 1) . '.') : 'Verifizierter Kunde'));
                $reviewNodes[] = [
                    '@type' => 'Review',
                    'author' => [
                        '@type' => 'Person',
                        'name' => $authorName ?: 'Verifizierter Kunde',
                    ],
                    'datePublished' => optional($rev->created_at)->format('Y-m-d') ?: now()->format('Y-m-d'),
                    'reviewBody' => trim(strip_tags((string) ($rev->comment ?? $rev->content ?? ''))),
                    'name' => $rev->title ?: $this->name,
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => (string) ($rev->rating ?? 5),
                        'bestRating' => '5',
                        'worstRating' => '1',
                    ],
                ];
            }
            $productNode['review'] = $reviewNodes;
        }

        $materialText = !empty($essentialAttributes['Material']) ? (string) $essentialAttributes['Material'] : 'hochwertigem Grundmaterial';
        $dimText = ($this->width > 0 && $this->height > 0)
            ? "{$this->width} × {$this->height}" . ($this->length > 0 ? " × {$this->length} mm" : ' mm') . ($this->weight > 0 ? " (Gewicht ca. {$this->weight} g)" : '')
            : 'den in den Produkteigenschaften angegebenen Originalmaßen';

        $faqNode = [
            '@type' => 'FAQPage',
            '@id' => $canonicalUrl . '#faq',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => "Welche genauen Maße und welches Material hat „{$this->name}“?",
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => "„{$this->name}“ besteht aus {$materialText} und besitzt die exakten Abmessungen von {$dimText}. Ein detailliertes technisches Maßblatt befindet sich auf der letzten Position der Produktbild-Galerie.",
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => "Ist die Lasergravur bei „{$this->name}“ im Preis inbegriffen?",
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Ja, die individuelle Personalisierung im interaktiven 2D-Editor und 3D-Echtzeit-Konfigurator (Wunschtext, Schriftart, Motiv oder eigenes Logo) ist im angezeigten Preis von ' . number_format($this->price / 100, 2, ',', '.') . ' € bereits vollständig enthalten.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Wie schnell erfolgt die Anfertigung und Lieferung?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Jedes Unikat wird in unserer Manufaktur in Gifhorn (Deutschland) einzeln per Laser veredelt, handgeprüft und innerhalb von 1–3 Werktagen versichert per DHL versendet.',
                    ],
                ],
            ],
        ];

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $canonicalUrl . '#breadcrumb',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Startseite',
                            'item' => route('home'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Shop',
                            'item' => route('shop'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $this->name,
                            'item' => $canonicalUrl,
                        ],
                    ],
                ],
                $productNode,
                $faqNode,
            ],
        ];
    }
}

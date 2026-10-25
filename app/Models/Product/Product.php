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

    public function tierPrices()
    {
        return $this->hasMany(ProductTierPrice::class)->orderBy('qty', 'asc');
    }

    public function getTaxIncludedAttribute(): bool
    {
        return (bool) shop_setting('prices_entered_gross', true);
    }

    public function reduceStock(int $amount): bool
    {
        if ($amount <= 0) {
            return false;
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

    public function restoreStock(int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        if ($this->track_quantity) {
            $this->increment('quantity', $amount);
        }
    }

    public function increaseStock(int $amount): void
    {
        $this->restoreStock($amount);
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
        // Nur freigegebene Bewertungen zählen!
        return round($this->reviews()->where('status', 'approved')->avg('rating') ?? 0, 1);
    }

    public function getReviewCountAttribute()
    {
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
     * passend zum aktuellen Feiertagsfenster bzw. zur aktiven Jahreszeit.
     */
    public static function resolvePreferredHolidayCandidates(?string $explicitHoliday = null): array
    {
        $candidates = [];

        if ($explicitHoliday !== null && trim($explicitHoliday) !== '') {
            $candidates[] = trim($explicitHoliday);
        }

        try {
            $autopilot = app(\App\Services\Marketing\HolidayOfferAutopilotService::class);
            $now = \Carbon\Carbon::now();

            // 1. Prüfe aktives Angebotsfenster oder Feiertag bis inkl. Feiertags-Tag
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

            // 2. Fallback über die aktive Jahreszeit (aus /admin/configuration -> Jahreszeiten)
            $seasonKey = app(\App\Services\ConfiguratorService::class)->getActiveSeasonKey();
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
     */
    public function resolveSeasonalTemplate(?string $explicitHoliday = null, array $excludeImagePaths = []): ?ProductTemplate
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

        $candidates = self::resolvePreferredHolidayCandidates($explicitHoliday);

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
     * Liefert strukturierte Metadaten zum aufgelösten Hauptbild (Pfad, URL, KI-Kennzeichnung, Label).
     */
    public function resolveMainImageMeta(?string $explicitHoliday = null, bool $allowSeasonal = true, array $excludeImagePaths = []): array
    {
        if ($allowSeasonal) {
            // 1. Prüfe aktive Produktvorlagen (ProductTemplate)
            $seasonalTpl = $this->resolveSeasonalTemplate($explicitHoliday, $excludeImagePaths);
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
                $candidates = self::resolvePreferredHolidayCandidates($explicitHoliday);
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
                                'is_ai_generated' => filter_var($media['is_ai_generated'] ?? false, FILTER_VALIDATE_BOOLEAN),
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
                            'is_ai_generated' => filter_var($media['is_ai_generated'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'label'           => $media['alt'] ?? $this->name,
                        ];
                    }
                } elseif (is_string($media) && $media !== '' && !in_array($media, $excludeImagePaths, true)) {
                    return [
                        'path'            => $media,
                        'url'             => ProductTemplate::resolveImageUrl($media),
                        'is_ai_generated' => false,
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
    public function resolveMainImagePath(?string $explicitHoliday = null, bool $allowSeasonal = true, array $excludeImagePaths = []): ?string
    {
        return $this->resolveMainImageMeta($explicitHoliday, $allowSeasonal, $excludeImagePaths)['path'];
    }

    /**
     * Liefert die fertige, Cache-sichere öffentliche URL des Produkt-Hauptbildes.
     */
    public function resolveMainImageUrl(?string $explicitHoliday = null, bool $allowSeasonal = true, array $excludeImagePaths = []): ?string
    {
        return $this->resolveMainImageMeta($explicitHoliday, $allowSeasonal, $excludeImagePaths)['url'];
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
}

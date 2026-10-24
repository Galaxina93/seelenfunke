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

        return $this->isPersonalizable() && $this->isPhysical();
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
     * Liefert den relativen Storage-Pfad des Hauptbildes (entweder saisonale Vorlage oder manuelles Galerie-Hauptbild).
     */
    public function resolveMainImagePath(?string $explicitHoliday = null, bool $allowSeasonal = true, array $excludeImagePaths = []): ?string
    {
        if ($allowSeasonal) {
            $seasonalTpl = $this->resolveSeasonalTemplate($explicitHoliday, $excludeImagePaths);
            if ($seasonalTpl && !empty($seasonalTpl->preview_image)) {
                return $seasonalTpl->preview_image;
            }
        }

        if (is_array($this->media_gallery) && count($this->media_gallery) > 0) {
            foreach ($this->media_gallery as $media) {
                if (is_array($media) && ($media['type'] ?? 'image') === 'image' && !empty($media['path'])) {
                    if (!in_array($media['path'], $excludeImagePaths, true)) {
                        return $media['path'];
                    }
                } elseif (is_string($media) && $media !== '' && !in_array($media, $excludeImagePaths, true)) {
                    return $media;
                }
            }
        }

        if (!empty($this->preview_image_path) && !in_array($this->preview_image_path, $excludeImagePaths, true)) {
            return $this->preview_image_path;
        }

        return null;
    }

    /**
     * Liefert die fertige, Cache-sichere öffentliche URL des Produkt-Hauptbildes.
     */
    public function resolveMainImageUrl(?string $explicitHoliday = null, bool $allowSeasonal = true, array $excludeImagePaths = []): ?string
    {
        $path = $this->resolveMainImagePath($explicitHoliday, $allowSeasonal, $excludeImagePaths);
        if (!$path) {
            return null;
        }

        return ProductTemplate::resolveImageUrl($path);
    }
}

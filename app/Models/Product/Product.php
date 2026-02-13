<?php

namespace App\Models\Product;

use App\Models\Category;
use App\Services\PriceCalculator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'media_gallery' => 'array',
        'attributes' => 'array',
        'tier_pricing' => 'array',
        'configurator_settings' => 'array',
        'track_quantity' => 'boolean',
        'continue_selling_when_out_of_stock' => 'boolean',
        'price' => 'integer',
        'compare_at_price' => 'integer',
        'cost_per_item' => 'integer',
        'weight' => 'integer',
        'height' => 'integer',
        'width' => 'integer',
        'length' => 'integer',
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
        $calculator = new PriceCalculator();
        if ($this->tax_included) {
            return $calculator->getNetFromGross($this->price, (float)$this->tax_rate);
        }
        return $this->price;
    }

    public function getGrossPriceAttribute(): int
    {
        $calculator = new PriceCalculator();
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

        $globalDefault = (float)shop_setting('default_tax_rate', 19.00);

        if ($this->tax_class === 'standard' || empty($this->tax_class)) {
            return $globalDefault;
        }

        $specialRate = DB::table('tax_rates')
            ->where('tax_class', $this->tax_class)
            ->where('country_code', 'DE')
            ->value('rate');

        return $specialRate !== null ? (float)$specialRate : $globalDefault;
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
        if (!$this->track_quantity) {
            return true;
        }

        if ($this->quantity < $amount && !$this->continue_selling_when_out_of_stock) {
            return false;
        }

        return $this->decrement('quantity', $amount);
    }

    public function restoreStock(int $amount): void
    {
        if ($this->track_quantity) {
            $this->increment('quantity', $amount);
        }
    }

    // --- NEUE TYP-LOGIK ---

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
        return $this->belongsToMany(Category::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Services\PriceCalculator; // NEU

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'price' => 'integer',
        'compare_at_price' => 'integer',
        'cost_per_item' => 'integer',
        'tax_rate' => 'decimal:2',

        // Boolean Casts
        'tax_included' => 'boolean',
        'track_quantity' => 'boolean',
        'continue_selling_when_out_of_stock' => 'boolean',
        'is_physical_product' => 'boolean',

        // JSON Arrays
        'media_gallery' => 'array',
        'tier_pricing' => 'array',
        'attributes' => 'array',
        'configurator_settings' => 'array',

    ];

    protected function priceEuro(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['price'] / 100,
            set: fn ($value) => [
                // FIX: (string) $value verhindert Absturz bei null
                'price' => (int) round((float)str_replace(',', '.', (string) $value) * 100)
            ],
        );
    }

    // NEU: Berechnet Nettopreis dynamisch
    public function getNetPriceAttribute(): int
    {
        $calculator = new PriceCalculator();

        if ($this->tax_included) {
            // Preis in DB ist Brutto -> Netto berechnen
            return $calculator->getNetFromGross($this->price, (float)$this->tax_rate);
        }

        // Preis in DB ist bereits Netto
        return $this->price;
    }

    // NEU: Berechnet Bruttopreis dynamisch
    public function getGrossPriceAttribute(): int
    {
        $calculator = new PriceCalculator();

        if ($this->tax_included) {
            // Preis in DB ist bereits Brutto
            return $this->price;
        }

        // Preis in DB ist Netto -> Brutto berechnen
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

    /**
     * Prüft, ob das Produkt bestellbar ist.
     */
    public function isAvailable(): bool
    {
        // Wenn Lagerbestand nicht getrackt wird -> Immer verfügbar
        if (!$this->track_quantity) {
            return true;
        }

        // Wenn Weiterverkauf erlaubt ist -> Immer verfügbar (Backorder)
        if ($this->continue_selling_when_out_of_stock) {
            return true;
        }

        // Sonst: Nur verfügbar, wenn Menge > 0
        return $this->quantity > 0;
    }
}

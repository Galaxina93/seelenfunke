<?php

namespace App\Models\Order;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderOrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_order_items';

    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'is_completed' => 'boolean',
        'completed_quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(OrderOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Ermittelt den zum Kaufzeitpunkt gültigen Produktbild-Pfad (eingefrorenes 'purchased_image_path',
     * Variantenbild oder saisonales/Feiertags-Hauptbild zum Bestelldatum), damit sich Bestellbilder
     * bei späterem Jahreszeiten-/Feiertagswechsel niemals rückwirkend verändern.
     */
    public function resolveDisplayImagePath(): ?string
    {
        $conf = is_array($this->configuration) ? $this->configuration : [];

        if (!empty($conf['purchased_image_path']) && is_string($conf['purchased_image_path'])) {
            return $conf['purchased_image_path'];
        }

        if ($this->product) {
            $purchaseDate = ($this->relationLoaded('order') && $this->order && $this->order->created_at)
                ? $this->order->created_at
                : $this->created_at;

            return $this->product->resolveConfigurationImagePath($conf, $purchaseDate);
        }

        return null;
    }

    /**
     * Ermittelt die fertige öffentliche Produktbild-URL zum Kaufzeitpunkt.
     */
    public function resolveDisplayImageUrl(): ?string
    {
        $path = $this->resolveDisplayImagePath();
        return $path ? \App\Models\Product\ProductTemplate::resolveImageUrl($path) : null;
    }
}

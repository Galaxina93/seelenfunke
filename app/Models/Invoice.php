<?php

namespace App\Models;

use App\Traits\FormatsECommerceData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, HasUuids, FormatsECommerceData;

    protected $guarded = [];

    protected $casts = [
        'invoice_date' => 'date',
        'delivery_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'shipping_cost' => 'integer',
        'discount_amount' => 'integer',
        'volume_discount' => 'integer',
        'total' => 'integer',
        'custom_items' => 'array',
        'is_e_invoice' => 'boolean',
    ];

    /**
     * Die verknüpfte Bestellung.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Der verknüpfte Kunde.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Bei Stornorechnungen: Verweis auf die Originalrechnung.
     */
    public function parent()
    {
        return $this->belongsTo(Invoice::class, 'parent_id');
    }

    /**
     * Falls diese Rechnung storniert wurde: Verweis auf den Korrekturbeleg.
     */
    public function child()
    {
        return $this->hasOne(Invoice::class, 'parent_id');
    }

    /**
     * Accessor für die Rechnungspositionen.
     * Nutzt entweder die Items der Bestellung oder manuell definierte custom_items.
     */
    public function getItemsAttribute()
    {
        if ($this->order_id && $this->order) {
            return $this->order->items->map(function($item) {
                return (object)[
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate ?? 19,
                    'total_price' => $item->unit_price * $item->quantity,
                    'configuration' => $item->configuration
                ];
            });
        }

        return collect($this->custom_items)->map(fn($i) => (object)$i);
    }

    /**
     * Prüft, ob es sich um eine Gutschrift oder Stornierung handelt.
     */
    public function isCreditNote()
    {
        return in_array($this->type, ['credit_note', 'cancellation']);
    }

    /**
     * Hilfsmethode zur Steuerberechnung (Rückrechnung aus Brutto).
     */
    public static function calculateTax($amount, $countryCode = 'DE')
    {
        if ((bool)shop_setting('is_small_business', false)) {
            return 0;
        }

        $rate = (float)shop_setting('default_tax_rate', 19.0);
        $divisor = 1 + ($rate / 100);

        return (int) round($amount - ($amount / $divisor));
    }

    /**
     * Accessor für die Rechnungsadresse.
     * Priorisiert das Feld in der Invoice-Tabelle, nutzt sonst die Order-Daten.
     */
    public function getBillingAddressAttribute($value)
    {
        if ($value) {
            return is_array($value) ? $value : json_decode($value, true);
        }
        return $this->order->billing_address ?? [];
    }

    /**
     * Accessor für die Lieferadresse.
     * Fallback auf die Rechnungsadresse, falls nicht separat angegeben.
     */
    public function getShippingAddressAttribute($value)
    {
        if ($value) {
            return is_array($value) ? $value : json_decode($value, true);
        }

        if ($this->order_id && $this->order && $this->order->shipping_address) {
            return $this->order->shipping_address;
        }

        return $this->billing_address;
    }

    /**
     * Erforderlich für den Trait FormatsECommerceData,
     * falls is_express nicht direkt in der Invoice-Tabelle steht.
     */
    public function getIsExpressAttribute($value)
    {
        if (!is_null($value)) {
            return (bool)$value;
        }
        return $this->order->is_express ?? false;
    }

    /**
     * Proxy für die E-Mail-Adresse des Kunden.
     */
    public function getEmailAttribute($value)
    {
        return $value ?? ($this->order->email ?? ($this->customer->email ?? ''));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Order extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $guarded = [];

    // FIX: 'cancellation_reason' MUSS hier stehen, sonst wird es nicht gespeichert!
    protected $fillable = [
        'order_number',
        'customer_id',
        'email',
        'status',
        'payment_status',
        'payment_method',
        'stripe_payment_intent_id',
        'billing_address',
        'shipping_address',
        'volume_discount',
        'coupon_code',
        'discount_amount',
        'subtotal_price',
        'tax_amount',
        'shipping_price',
        'total_price',
        'notes',
        'cancellation_reason', // <--- WICHTIG
        'is_express',          // Ergänzt für toFormattedArray
        'deadline',            // Ergänzt für toFormattedArray
        'expires_at',          // Ergänzt für toFormattedArray
        'token'                // Ergänzt für toFormattedArray
    ];

    protected $casts = [
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'subtotal_price' => 'integer',
        'tax_amount' => 'integer',
        'shipping_price' => 'integer',
        'total_price' => 'integer',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_express' => 'boolean',
        'cancellation_reason' => 'string',
    ];

    // Relationen
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id'); // Oder Customer Model
    }

    // Helper für Status-Farben (Tailwind Klassen)
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'processing' => 'bg-blue-100 text-blue-800',
            'shipped' => 'bg-purple-100 text-purple-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'refunded' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getPaymentStatusColorAttribute()
    {
        return match($this->payment_status) {
            'paid' => 'bg-green-100 text-green-800',
            'unpaid' => 'bg-red-100 text-red-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'refunded' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    // Helper für vollen Namen
    public function getCustomerNameAttribute()
    {
        if (isset($this->billing_address['first_name'])) {
            return $this->billing_address['first_name'] . ' ' . $this->billing_address['last_name'];
        }
        return 'Gast';
    }

    /**
     * Berechnet den enthaltenen Steueranteil der Versandkosten.
     * Wichtig für die korrekte Ausweisung auf Rechnungen.
     */
    public function getShippingTaxAmountAttribute()
    {
        if ($this->shipping_price <= 0) {
            return 0;
        }

        // 1. Check: Kleinunternehmer-Status dynamisch aus der DB laden
        if (shop_setting('is_small_business', false)) {
            return 0;
        }

        // 2. Steuersatz holen
        $taxRate = (float) shop_setting('default_tax_rate', 19.00);

        // 3. Dynamische Rückwärtsrechnung
        $divisor = 1 + ($taxRate / 100);

        return (int) round($this->shipping_price - ($this->shipping_price / $divisor));
    }

    /**
     * Gibt den Netto-Versandpreis zurück.
     */
    public function getShippingNetPriceAttribute()
    {
        return $this->shipping_price - $this->shipping_tax_amount;
    }

    /**
     * Prüft, ob der Versand für diese Bestellung kostenlos war.
     */
    public function getIsFreeShippingAttribute()
    {
        return $this->shipping_price === 0;
    }

    /**
     * Führt eine saubere Stornierung durch.
     */
    public function cancel(string $reason): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason
        ]);
    }

    public function invoices() {
        return $this->hasMany(Invoice::class);
    }

    // Formatiert die Daten die an sämtliche Mail Vorlagen gehen.
    public function toFormattedArray()
    {
        $items = [];

        // Werte dynamisch aus der Tabelle 'shop-settings' über den Helper laden
        $isSmallBusiness = (bool)shop_setting('is_small_business', false);
        $defaultTaxRate  = (float)shop_setting('default_tax_rate', 19);
        $validityDays    = (int)shop_setting('order_quote_validity_days', 14);

        foreach ($this->items as $item) {
            $items[] = [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'single_price' => number_format($item->unit_price / 100, 2, ',', '.'),
                'total_price' => number_format($item->total_price / 100, 2, ',', '.'),
                'config' => $item->configuration
            ];
        }

        return [
            'quote_number' => $this->order_number,
            'quote_token'  => $this->token ?? '',
            // Nutzt jetzt die dynamische Einstellung aus dem Backend
            'quote_expiry' => $this->expires_at
                ? $this->expires_at->format('d.m.Y')
                : now()->addDays($validityDays)->format('d.m.Y'),
            'express'      => (bool)$this->is_express,
            'deadline'     => $this->deadline,
            'total_netto'  => number_format(($this->total_price - $this->tax_amount) / 100, 2, ',', '.'),
            'total_vat'    => number_format($this->tax_amount / 100, 2, ',', '.'),
            'total_gross'  => number_format($this->total_price / 100, 2, ',', '.'),
            'shipping_price' => number_format($this->shipping_price / 100, 2, ',', '.'),

            // DYNAMISCH: Kleinunternehmer-Logik & Steuer-Satz aus den DB-Settings
            'is_small_business' => $isSmallBusiness,
            'tax_rate' => $defaultTaxRate,
            'tax_note' => $isSmallBusiness
                ? 'Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG.'
                : "Enthaltene MwSt. ({$defaultTaxRate}%):",

            'contact' => [
                'vorname'  => $this->billing_address['first_name'] ?? '',
                'nachname' => $this->billing_address['last_name'] ?? '',
                'firma'    => $this->billing_address['company'] ?? '',
                'email'    => $this->email,
                'telefon'  => $this->billing_address['phone'] ?? $this->phone ?? '',
                'anmerkung'=> $this->notes ?? '',
                'country'  => $this->billing_address['country'] ?? 'DE'
            ],
            'items' => $items
        ];
    }
}

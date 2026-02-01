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
    ];

    protected $casts = [
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'subtotal_price' => 'integer',
        'tax_amount' => 'integer',
        'shipping_price' => 'integer',
        'total_price' => 'integer',
        'created_at' => 'datetime',
        'cancellation_reason',
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

        // Steuersatz aus Config holen (Standard 19%, falls nicht konfiguriert)
        // Dies entspricht der Logik im CartService
        $taxRate = config('shop.shipping.tax_rate', 19);

        // Rückwärtsrechnung: Brutto - (Brutto / 1.19)
        return (int) round($this->shipping_price - ($this->shipping_price / (1 + ($taxRate / 100))));
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
            // Optional: payment_status auf 'refunded' setzen, falls gewünscht?
            // 'payment_status' => 'refunded',
            'cancellation_reason' => $reason
        ]);

        // Hier könnte man später Events feuern, z.B.:
        // OrderCancelled::dispatch($this);
    }

    public function invoices() {
        return $this->hasMany(Invoice::class);
    }

    // Formatiert die Daten die an sämtliche Mail Vorlagen gehen. Zentralisierung der Umwandlung der Maildaten
    public function toFormattedArray()
    {
        $items = [];
        foreach ($this->items as $item) {
            $items[] = [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'single_price' => number_format($item->unit_price / 100, 2, ',', '.'),
                'total_price' => number_format($item->total_price / 100, 2, ',', '.'),
                'config' => $item->configuration // Hier liegt alles: Gravur, Logo-Pfad etc.
            ];
        }

        return [
            'quote_number' => $this->order_number,
            'quote_token'  => $this->token ?? '', // Falls vorhanden
            'quote_expiry' => $this->expires_at ? $this->expires_at->format('d.m.Y') : now()->addDays(14)->format('d.m.Y'),
            'express'      => (bool)$this->is_express,
            'deadline'     => $this->deadline,
            'total_netto'  => number_format(($this->total_price - $this->tax_amount) / 100, 2, ',', '.'),
            'total_vat'    => number_format($this->tax_amount / 100, 2, ',', '.'),
            'total_gross'  => number_format($this->total_price / 100, 2, ',', '.'),
            'shipping_price' => number_format($this->shipping_price / 100, 2, ',', '.'),
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'shipping_cost' => 'integer',
        'total' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class); // Oder User::class je nach Setup
    }

    // Für Stornos: Referenz auf Original
    public function parent()
    {
        return $this->belongsTo(Invoice::class, 'parent_id');
    }

    // Für Original: Referenz auf Storno
    public function child()
    {
        return $this->hasOne(Invoice::class, 'parent_id');
    }

    // FIX: Accessor statt Relation, damit $invoice->items niemals null ist
    // Wir greifen auf die geladene Order Relation zu.
    public function getItemsAttribute()
    {
        // Wenn Order geladen ist, nimm deren Items. Sonst leere Collection.
        return $this->order ? $this->order->items : collect([]);
    }

    public function isCreditNote()
    {
        return in_array($this->type, ['credit_note', 'cancellation']);
    }
}

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
        'total' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function parent()
    {
        return $this->belongsTo(Invoice::class, 'parent_id');
    }

    public function items()
    {
        // Wir holen die Items über die Order, da wir keine separaten InvoiceItems speichern (vereinfacht)
        // Für Teilrechnungen bräuchte man eine invoice_items Tabelle.
        return $this->order->items();
    }

    // Helper: Ist es eine Stornorechnung?
    public function isCreditNote()
    {
        return in_array($this->type, ['credit_note', 'cancellation']);
    }
}

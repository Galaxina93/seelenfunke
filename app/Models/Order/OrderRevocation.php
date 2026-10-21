<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderRevocation extends Model
{
    use HasFactory;

    protected $table = 'order_revocations';

    protected $fillable = [
        'name',
        'email',
        'order_number',
        'items',
        'attachments',
        'status',
        'legal_check_at',
        'product_type',
        'rejection_reason',
        'customer_notified_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'legal_check_at' => 'datetime',
        'customer_notified_at' => 'datetime',
    ];

    /**
     * Zugehörige Bestellung anhand der Bestellnummer.
     */
    public function order()
    {
        return $this->belongsTo(OrderOrder::class, 'order_number', 'order_number');
    }

    /**
     * Findet die verknüpfte Bestellung tolerant (mit/ohne Raute oder Prefix).
     */
    public function getLinkedOrderAttribute(): ?OrderOrder
    {
        $rawNumber = trim($this->order_number ?? '');
        if ($rawNumber === '') {
            return null;
        }

        $cleanNumber = trim(ltrim($rawNumber, '#'));

        return OrderOrder::where('order_number', $rawNumber)
            ->orWhere('order_number', $cleanNumber)
            ->orWhere('order_number', 'ORD-' . $cleanNumber)
            ->first();
    }
}

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
}

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
    ];

    protected $casts = [
        'attachments' => 'array',
    ];
}

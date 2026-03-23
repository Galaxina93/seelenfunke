<?php

namespace App\Models\Shop\Revocation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Revocation extends Model
{
    use HasFactory;

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

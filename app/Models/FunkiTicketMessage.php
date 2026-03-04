<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FunkiTicketMessage extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'attachments' => 'array',
        'is_read_by_customer' => 'boolean',
        'is_read_by_admin' => 'boolean',
    ];

    public function ticket()
    {
        return $this->belongsTo(FunkiTicket::class);
    }
}

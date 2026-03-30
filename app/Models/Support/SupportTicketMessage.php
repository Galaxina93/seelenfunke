<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupportTicketMessage extends Model
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
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
}

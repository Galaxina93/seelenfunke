<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupportContactRequestMessage extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'attachments' => 'json',
        'is_read_by_customer' => 'boolean',
        'is_read_by_admin' => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(SupportContactRequest::class, 'support_contact_request_id');
    }
}

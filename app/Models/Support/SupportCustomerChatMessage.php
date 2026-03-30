<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SupportCustomerChatMessage extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $keyType = 'string';
    public $incrementing = false;

    public function chat()
    {
        return $this->belongsTo(SupportCustomerChat::class, 'support_customer_chat_id');
    }
}

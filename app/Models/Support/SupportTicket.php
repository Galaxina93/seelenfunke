<?php

namespace App\Models\Support;

use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'reward_claimed' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(OrderOrder::class);
    }

    public function messages()
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at', 'asc');
    }
}

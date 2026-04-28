<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class CustomerGamification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'titles_progress' => 'array',
        'unlocked_coupons' => 'array',
        'show_seelengott_badge' => 'boolean',
        'last_spark_collection_date' => 'date',
        'ranking_opt_in' => 'boolean',
        'ticket_emails_enabled' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}

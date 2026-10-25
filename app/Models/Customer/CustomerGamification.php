<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class CustomerGamification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'titles_progress' => 'array',
        'unlocked_coupons' => 'array',
        'show_seelengott_badge' => 'boolean',
        'last_spark_collection_date' => 'date',
        'last_energy_refill_at' => 'datetime',
        'ranking_opt_in' => 'boolean',
        'ticket_emails_enabled' => 'boolean',
        'left_handed_mode' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}

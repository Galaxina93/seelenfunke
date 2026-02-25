<?php

namespace App\Models\Customer;

use App\Models\Funki\FunkiItem;
use Illuminate\Database\Eloquent\Model;

class CustomerFunkiItem extends Model
{
    protected $guarded = [];

    public function item()
    {
        return $this->belongsTo(FunkiItem::class, 'funki_item_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}

<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementShoppingItem extends Model
{
    use HasUuids;

    protected $table = 'management_shopping_items';
    protected $fillable = [
        'category_id',
        'name',
        'status',
        'last_purchased_at',
        'purchase_count'
    ];

    protected $casts = [
        'last_purchased_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ManagementShoppingCategory::class, 'category_id');
    }
}

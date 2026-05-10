<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementShoppingCategory extends Model
{
    use HasUuids;

    protected $table = 'management_shopping_categories';
    protected $fillable = [
        'name',
        'icon',
        'sort_order',
        'is_archived'
    ];

    protected $casts = [
        'is_archived' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ManagementShoppingItem::class, 'category_id')->orderBy('last_purchased_at', 'asc');
    }
}

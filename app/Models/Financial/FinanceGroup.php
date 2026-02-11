<?php

namespace App\Models\Financial;

use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceGroup extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_id',
        'name',
        'type',
        'position',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(FinanceCostItem::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

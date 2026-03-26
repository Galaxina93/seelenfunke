<?php

namespace App\Models\Accounting;

use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingGroup extends Model
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
        return $this->hasMany(AccountingCostItem::class, 'accounting_group_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

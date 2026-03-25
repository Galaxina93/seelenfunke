<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceCostItemHistory extends Model
{
    protected $fillable = [
        'finance_cost_item_id',
        'name',
        'is_business',
        'tax_rate',
        'first_payment_date',
        'last_payment_date',
        'contract_file_path',
        'tags',
        'finance_group_id',
        'amount',
        'interval_months',
        'description',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'interval_months' => 'integer',
        'first_payment_date' => 'date',
        'last_payment_date' => 'date',
        'is_business' => 'boolean',
        'tags' => 'array',
    ];

    public function costItem(): BelongsTo
    {
        return $this->belongsTo(FinanceCostItem::class, 'finance_cost_item_id');
    }
}

<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceCostItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'finance_group_id',
        'name',
        'description',
        'amount',
        'interval_months',
        'first_payment_date',
        'is_business',
        'contract_file_path',
        'tags',
    ];

    protected $casts = [
        'first_payment_date' => 'date',
        'amount'             => 'decimal:2',
        'is_business'        => 'boolean',
        'tags'               => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(FinanceGroup::class, 'finance_group_id');
    }
}

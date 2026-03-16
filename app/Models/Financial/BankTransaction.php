<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    protected $fillable = [
        'bank_account_id',
        'finapi_transaction_id',
        'amount',
        'currency',
        'purpose',
        'counterpart_name',
        'counterpart_iban',
        'transaction_date',
        'value_date',
        'type',
        'is_pending',
        'raw_data',
        'finance_category_id',
        'finance_cost_item_id'
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'value_date' => 'datetime',
        'is_pending' => 'boolean',
        'raw_data' => 'array',
        'amount' => 'decimal:2'
    ];

    public function account()
    {
        return $this->belongsTo(\App\Models\Financial\BankAccount::class, 'bank_account_id');
    }

    public function costItem()
    {
        return $this->belongsTo(\App\Models\Financial\FinanceCostItem::class, 'finance_cost_item_id');
    }
}

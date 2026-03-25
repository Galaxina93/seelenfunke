<?php

namespace App\Models\Accounting;

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
        'finance_cost_item_id',
        'assigned_by_type',
        'assigned_by_name',
        'is_business',
        'tags',
        'file_paths',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'value_date' => 'datetime',
        'is_pending' => 'boolean',
        'raw_data' => 'array',
        'amount' => 'decimal:2',
        'file_paths' => 'array',
        'is_business' => 'boolean',
        'tags' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(\App\Models\Accounting\BankAccount::class, 'bank_account_id');
    }

    public function costItem()
    {
        return $this->belongsTo(\App\Models\Accounting\FinanceCostItem::class, 'finance_cost_item_id');
    }

    public function financeCategory()
    {
        return $this->belongsTo(\App\Models\Accounting\FinanceCategory::class, 'finance_category_id');
    }
}

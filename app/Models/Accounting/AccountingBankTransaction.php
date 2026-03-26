<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class AccountingBankTransaction extends Model
{
    protected $fillable = [
        'accounting_bank_account_id',
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
        'accounting_category_id',
        'accounting_cost_item_id',
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
        return $this->belongsTo(\App\Models\Accounting\AccountingBankAccount::class, 'accounting_bank_account_id');
    }

    public function costItem()
    {
        return $this->belongsTo(\App\Models\Accounting\AccountingCostItem::class, 'accounting_cost_item_id');
    }

    public function accountingCategory()
    {
        return $this->belongsTo(\App\Models\Accounting\AccountingCategory::class, 'accounting_category_id');
    }
}

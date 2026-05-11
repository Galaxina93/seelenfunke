<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingCostItemHistory extends Model
{
    protected $fillable = [
        'accounting_cost_item_id',
        'name',
        'is_business',
        'requires_contract',
        'tax_rate',
        'first_payment_date',
        'last_payment_date',
        'contract_file_path',
        'tags',
        'accounting_group_id',
        'amount',
        'interval_months',
        'description',
        'provider_street',
        'provider_house_number',
        'provider_zip',
        'provider_city',
        'provider_phone',
        'provider_email',
        'provider_website',
        'contract_number',
        'notice_period',
        'contract_end_date',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'interval_months' => 'integer',
        'first_payment_date' => 'date',
        'last_payment_date' => 'date',
        'contract_end_date' => 'date',
        'is_business' => 'boolean',
        'requires_contract' => 'boolean',
        'tags' => 'array',
    ];

    public function costItem(): BelongsTo
    {
        return $this->belongsTo(AccountingCostItem::class, 'accounting_cost_item_id');
    }
}

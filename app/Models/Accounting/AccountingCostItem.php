<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingCostItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'accounting_group_id',
        'name',
        'description',
        'amount',
        'interval_months',
        'is_business',
        'contract_file_path',
        'tags',
        'tax_rate',
        'first_payment_date',
        'last_payment_date',
        // Provider/Contract Data
        'provider_company',
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
    ];

    protected $casts = [
        'first_payment_date' => 'date',
        'last_payment_date'  => 'date',
        'contract_end_date'  => 'date',
        'amount'             => 'decimal:2',
        'is_business'        => 'boolean',
        'tags'               => 'array',
        'tax_rate'           => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AccountingGroup::class, 'accounting_group_id');
    }

    public function transactions()
    {
        return $this->hasMany(AccountingBankTransaction::class, 'accounting_cost_item_id');
    }

    public function histories()
    {
        return $this->hasMany(AccountingCostItemHistory::class, 'accounting_cost_item_id')->orderBy('created_at', 'desc');
    }
}

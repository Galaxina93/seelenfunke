<?php
namespace App\Models\Accounting;
use Illuminate\Database\Eloquent\Model;

class AccountingBankAccount extends Model
{
    // Diese Zeile erlaubt Laravel, unsere finAPI-Daten in die Datenbank zu schreiben!
    protected $fillable = [
        'admin_id',
        'plaid_item_id',
        'plaid_access_token',
        'plaid_account_id',
        'bank_name',
        'account_name',
        'iban',
        'is_active_for_analysis',
        'is_business',
        'balance',
        'currency',
        'last_synced_at'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_active_for_analysis' => 'boolean',
        'is_business' => 'boolean',
    ];
}

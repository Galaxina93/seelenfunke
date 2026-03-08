<?php
namespace App\Models\Financial;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
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
        'balance',
        'currency',
        'last_synced_at'
    ];
}

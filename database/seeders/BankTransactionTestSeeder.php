<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankTransaction;
use App\Models\Admin\Admin;

class BankTransactionTestSeeder extends Seeder
{
    public function run()
{
        $admin = Admin::first();
        if (!$admin) {
            $this->command->error('Kein Admin gefunden. Test abgebrochen.');
            return;
        }

        // Dummy Bankkonto erstellen
        $account = BankAccount::firstOrCreate(
            ['plaid_account_id' => 'dummy_test_acc_99'],
            [
                'admin_id' => $admin->id,
                'plaid_item_id' => 'dummy_item_99',
                'plaid_access_token' => 'dummy_managed',
                'bank_name' => 'VR Bank Test (Schattenkonto)',
                'account_name' => 'Gewerbekonto Test',
                'iban' => 'DE99999999999999999999',
                'balance' => 15400.50,
                'currency' => 'EUR',
                'last_synced_at' => now(),
                'is_business' => true,
                'is_active_for_analysis' => true,
            ]
        );

        // Alte Test-Transaktionen von diesem Konto löschen, für sauberen Neu-Start
        BankTransaction::where('bank_account_id', $account->id)->delete();

        // Harte Test Cases
        $transactions = [
            // 1) Stadt Gifhorn Gewerbesteuer
            [
                'amount' => -450.00,
                'counterpart_name' => 'Stadt Gifhorn',
                'purpose' => 'Gewerbesteuer Q1 2026 Kassenzeichen 44.123.456',
                'type' => 'expense'
            ],
            [
                'amount' => -450.00,
                'counterpart_name' => 'STADT GIFHORN',
                'purpose' => 'Gewerbesteuer Q2 2026 Kassenzeichen 44.123.456',
                'type' => 'expense'
            ],
            
            // 2) Stadt Gifhorn Müllgebühren
            [
                'amount' => -60.00,
                'counterpart_name' => 'Stadtverwaltung Gifhorn',
                'purpose' => 'Abfallgebühren 2026 Bez. 99-88-77',
                'type' => 'expense'
            ],
            [
                'amount' => -60.00,
                'counterpart_name' => 'Stadt Gifhorn',
                'purpose' => 'Abfallgebühren 2027 Bez. 99-88-77',
                'type' => 'expense'
            ],

            // 3) PayPal Aggregatoren
            [
                'amount' => -12.99,
                'counterpart_name' => 'PayPal (Europe) S.a.r.l. et Cie',
                'purpose' => 'Ihre Zahlung an Spotify AB Ref 8A72B28B',
                'type' => 'expense'
            ],
            [
                'amount' => -12.99,
                'counterpart_name' => 'PayPal',
                'purpose' => 'Ihre Zahlung an Spotify AB Ref 9Z82C39B',
                'type' => 'expense'
            ],
            [
                'amount' => -45.00,
                'counterpart_name' => 'PayPal (Europe) S.a.r.l.',
                'purpose' => 'Zahlung an Bürodiscount24 GmbH Best. 123984',
                'type' => 'expense'
            ],
            
            // 4) Normale Verträge mit dynamischen Daten
            [
                'amount' => -49.99,
                'counterpart_name' => 'Telekom Deutschland GmbH',
                'purpose' => 'Festnetz Rechnung 03/2026 KdNr 400123908',
                'type' => 'expense'
            ],
            [
                'amount' => -49.99,
                'counterpart_name' => 'Telekom Deutschland GmbH',
                'purpose' => 'Festnetz Rechnung 04/2026 KdNr 400123908',
                'type' => 'expense'
            ]
        ];

        $today = now();
        foreach ($transactions as $i => $tx) {
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'finapi_transaction_id' => 'dummy_tx_' . time() . '_' . $i,
                'amount' => $tx['amount'],
                'currency' => 'EUR',
                'purpose' => $tx['purpose'],
                'counterpart_name' => $tx['counterpart_name'],
                'transaction_date' => $today->copy()->subDays($i * 5),
                'value_date' => $today->copy()->subDays($i * 5),
                'type' => $tx['type'],
                'is_pending' => false,
            ]);
        }

        $this->command->info('Erfolgreich 9 extreme Test-Umsätze (Stadt Gifhorn, PayPal, Telekom) erstellt!');
    }
}

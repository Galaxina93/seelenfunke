<?php

namespace Tests\Feature\Accounting;

use App\Livewire\Shop\Accounting\AccountingBank;
use App\Models\Accounting\AccountingBankAccount;
use App\Models\Accounting\AccountingBankTransaction;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingBankSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function receipt_route_blocks_path_traversal(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/accounting/receipt?path=../../.env');

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function receipt_route_blocks_access_to_unwhitelisted_private_paths(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/accounting/receipt?path=buchhaltung/erictresor/secret.xml');

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function receipt_route_allows_whitelisted_receipt_path(): void
    {
        Storage::fake('local');
        $admin = Admin::factory()->create();

        $receiptPath = 'buchhaltung/receipts/test_beleg_123.pdf';
        Storage::disk('local')->put($receiptPath, '%PDF-1.4 Mock receipt content');

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/accounting/receipt?path=' . $receiptPath);

        $response->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_cannot_disconnect_another_admins_bank_account(): void
    {
        $adminA = Admin::factory()->create();
        $adminB = Admin::factory()->create();

        $accountB = AccountingBankAccount::create([
            'admin_id' => $adminB->id,
            'plaid_item_id' => 'item_b',
            'plaid_access_token' => 'token_b',
            'plaid_account_id' => 'acc_b_' . uniqid(),
            'bank_name' => 'Sparkasse',
            'account_name' => 'Admin B Konto',
            'iban' => 'DE12345678901234567890',
            'currency' => 'EUR',
            'is_business' => true,
            'is_active_for_analysis' => true,
        ]);

        Livewire::actingAs($adminA, 'admin')
            ->test(AccountingBank::class)
            ->call('disconnectAccount', $accountB->id);

        $this->assertDatabaseHas('accounting_bank_accounts', [
            'id' => $accountB->id,
            'admin_id' => $adminB->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_cannot_alter_another_admins_transaction(): void
    {
        $adminA = Admin::factory()->create();
        $adminB = Admin::factory()->create();

        $accountB = AccountingBankAccount::create([
            'admin_id' => $adminB->id,
            'plaid_item_id' => 'item_b',
            'plaid_access_token' => 'token_b',
            'plaid_account_id' => 'acc_b_' . uniqid(),
            'bank_name' => 'Sparkasse',
            'account_name' => 'Admin B Konto',
            'iban' => 'DE12345678901234567890',
            'currency' => 'EUR',
            'is_business' => true,
            'is_active_for_analysis' => true,
        ]);

        $txB = AccountingBankTransaction::create([
            'accounting_bank_account_id' => $accountB->id,
            'finapi_transaction_id' => 'tx_' . uniqid(),
            'amount' => 15000,
            'currency' => 'EUR',
            'purpose' => 'Geheime Überweisung B',
            'transaction_date' => now(),
            'is_business' => true,
        ]);

        Livewire::actingAs($adminA, 'admin')
            ->test(AccountingBank::class)
            ->call('toggleBusinessStatus', $txB->id);

        // Transaction status must remain true because Admin A cannot alter Admin B's transaction!
        $this->assertTrue((bool)$txB->fresh()->is_business);
    }
}

<?php

namespace Tests\Feature\Livewire\Shop\Accounting;

use App\Livewire\Shop\Accounting\AccountingBank;
use App\Models\Accounting\AccountingBankAccount;
use App\Models\Accounting\AccountingBankTransaction;
use App\Models\Accounting\AccountingCategory;
use App\Models\Accounting\AccountingCategorizationRule;
use App\Services\BankApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AccountingBankTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Authenticate as Admin (Bypass Eloquent Boot Events and Mass Assignment)
        $adminId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'testadmin-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->admin = \App\Models\Admin\Admin::find($adminId);
        $this->actingAs($this->admin, 'admin');

        // 2. Clear volatile Livewire caches to prevent Hydration Corruptions
        if (is_dir(storage_path('framework/views'))) {
            array_map('unlink', glob(storage_path('framework/views/*.php')));
        }
        
        // 3. Fake Storage for Receipt Uploading
        Storage::fake('public');

        // 4. Seed base domain entities
        $this->bankAccount = AccountingBankAccount::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'admin_id' => $this->admin->id,
            'bank_name' => 'Commerzbank Edge',
            'account_name' => 'Main Business Checking',
            'iban' => 'DE1234567890',
            'balance' => 45000.50,
            'currency' => 'EUR',
            'plaid_account_id' => 'finapi_acc_123',
            'plaid_item_id' => 'finapi_item_123',
            'plaid_access_token' => 'finapi_managed',
            'is_business' => true,
            'is_active_for_analysis' => true
        ]);
        
        // 5. Mock the finAPI Service to physically block external OAuth handshakes
        $this->mock(BankApiService::class, function ($mock) {
            $mock->shouldReceive('updateBankConnection')->andReturnNull();
            $mock->shouldReceive('getUserToken')->andReturn('mocked_finapi_token');
            $mock->shouldReceive('getAccounts')->andReturn([
                [
                    'id' => 'finapi_acc_123',
                    'bankConnectionId' => 'finapi_item_123',
                    'bankName' => 'Commerzbank Edge',
                    'accountName' => 'Main Business Checking',
                    'iban' => 'DE1234567890',
                    'balance' => 45000.50,
                    'accountCurrency' => 'EUR'
                ]
            ]);
            $mock->shouldReceive('getTransactions')->andReturn([
                [
                    'id' => 'tx_9999',
                    'amount' => -15.99,
                    'purpose' => 'NETFLIX PREMIUM',
                    'counterpartName' => 'Netflix Int',
                    'bankBookingDate' => '2026-03-27',
                    'isPending' => false
                ]
            ]);
        });
    }

    #[Test]
    public function it_renders_bank_accounts_and_filters_transactions()
    {
        AccountingBankTransaction::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'accounting_bank_account_id' => $this->bankAccount->id,
            'finapi_transaction_id' => 'tx_1234',
            'amount' => -100,
            'currency' => 'EUR',
            'counterpart_name' => 'Adobe Systems',
            'purpose' => 'Creative Cloud Subscription',
            'transaction_date' => now()
        ]);

        Livewire::test(AccountingBank::class)
            ->assertSet('bankAccounts', function ($accounts) {
                return count($accounts) === 1 && $accounts[0]['bank_name'] === 'Commerzbank Edge';
            })
            ->set('searchTx', 'Adobe')
            ->assertSee('Adobe Systems');
    }

    #[Test]
    public function it_toggles_bank_business_and_active_states()
    {
        Livewire::test(AccountingBank::class)
            ->call('toggleBankBusiness', $this->bankAccount->id)
            ->call('toggleBankActive', $this->bankAccount->id);
            
        $this->bankAccount->refresh();
        $this->assertFalse((bool)$this->bankAccount->is_business);
        $this->assertFalse((bool)$this->bankAccount->is_active_for_analysis);
    }

    #[Test]
    public function it_uploads_and_deletes_receipt_documents()
    {
        $tx = AccountingBankTransaction::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'accounting_bank_account_id' => $this->bankAccount->id,
            'finapi_transaction_id' => 'tx_receipt_1',
            'amount' => -50,
            'currency' => 'EUR'
        ]);

        $file = UploadedFile::fake()->image('receipt.jpg');

        Livewire::test(AccountingBank::class)
            ->set('uploadingBankTxId', $tx->id)
            ->set('quickUploadFile', $file); // Auto triggers updatedQuickUploadFile()

        $tx->refresh();
        $this->assertIsArray($tx->file_paths);
        $this->assertCount(1, $tx->file_paths);
        Storage::disk('local')->assertExists($tx->file_paths[0]);

        // Delete Receipt
        Livewire::test(AccountingBank::class)
            ->call('deleteReceipt', $tx->id, 0);

        $tx->refresh();
        $this->assertEmpty($tx->file_paths);
    }

    #[Test]
    public function it_assigns_categories_and_auto_generates_engine_rules()
    {
        $tx = AccountingBankTransaction::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'accounting_bank_account_id' => $this->bankAccount->id,
            'finapi_transaction_id' => 'tx_cat_1',
            'amount' => -20,
            'currency' => 'EUR',
            'counterpart_name' => 'Aral Tankstelle',
            'purpose' => 'Fuel'
        ]);

        $category = AccountingCategory::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'admin_id' => $this->admin->id,
            'name' => 'Tanken',
            'color' => '#ff0000',
            'icon' => 'car'
        ]);

        Livewire::test(AccountingBank::class)
            ->call('assignCategory', $tx->id, $category->id);

        $tx->refresh();
        $this->assertEquals($category->id, $tx->accounting_category_id);
        $this->assertEquals('admin', $tx->assigned_by_type);

        // Verify that the Rule Engine learned from this manual assignment
        $this->assertDatabaseHas('accounting_categorization_rules', [
            'admin_id' => $this->admin->id,
            'accounting_category_id' => $category->id,
            'search_term' => 'Aral Tankstelle' // Should extract from counterpart
        ]);
    }

    #[Test]
    public function it_syncs_transactions_from_finapi()
    {
        Livewire::test(AccountingBank::class)
            ->call('syncAccount', $this->bankAccount->id);

        $this->assertDatabaseHas('accounting_bank_transactions', [
            'finapi_transaction_id' => 'tx_9999',
            'counterpart_name' => 'Netflix Int'
        ]);
    }

    #[Test]
    public function it_simulates_ai_agent_sorting_via_llm_json()
    {
        $agent = \App\Models\Ai\AiAgent::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Finance Bot',
            'is_active' => true,
            'model' => 'gpt-4'
        ]);

        $tx = AccountingBankTransaction::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'accounting_bank_account_id' => $this->bankAccount->id,
            'finapi_transaction_id' => 'tx_ai_1',
            'amount' => -9.99,
            'currency' => 'EUR',
            'counterpart_name' => 'Spotify AB'
        ]);

        $category = AccountingCategory::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'admin_id' => $this->admin->id,
            'name' => 'Software Abos',
            'color' => '#00ff00',
            'icon' => 'music'
        ]);

        // Fake LLM Response mimicking strict JSON array
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'mappings' => [
                                    [
                                        'tx_id' => $tx->id,
                                        'product_category_id' => $category->id,
                                        'cost_item_id' => null
                                    ]
                                ]
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        Livewire::test(AccountingBank::class)
            ->set('selectedAgentId', $agent->id)
            ->call('startAgentSorting');

        $tx->refresh();
        $this->assertEquals($category->id, $tx->accounting_category_id);
        $this->assertEquals('agent', $tx->assigned_by_type);
    }

    #[Test]
    public function it_auto_creates_fyrst_account_and_imports_18_column_fyrst_csv_accurately()
    {
        // Start with 0 bank accounts (mirrors fresh live setup)
        AccountingBankAccount::query()->delete();

        $csvLines = [
            "\xEF\xBB\xBFUmsätze",
            'Konto;Filial-/Kontonummer;IBAN;Währung',
            'FYRST BASE;370 0000000 00;DE89370400440532013000;EUR',
            '',
            '01.11.2026 - 24.11.2026',
            'Letzter Kontostand;;;;0;EUR',
            'Vorgemerkte und noch nicht gebuchte Umsätze sind nicht Bestandteil dieser Übersicht.',
            'Buchungstag;Wert;Umsatzart;Begünstigter / Auftraggeber;Verwendungszweck;IBAN / Kontonummer;BIC;Kundenreferenz;Mandatsreferenz;Gläubiger ID;Fremde Gebühren;Betrag;Abweichender Empfänger;Anzahl der Aufträge;Anzahl der Schecks;Soll;Haben;Währung',
            '5.11.2026;5.11.2026;Gutschrift;Max Mustermann;Starteinlage Manufaktur;DE12500105170648489890;;REF-1001;;;;2.000;;;;;2.000;EUR',
            '12.11.2026;12.11.2026;SEPA-Überweisung;Westpack A/S;Schmuckboxen Rechnung 29001;DK5000400440116243;;INV-29001;;;;-145,95;;;;-145,95;;EUR',
            '18.11.2026;18.11.2026;Lastschrift;xTool Laser GmbH;;DE44500105175407324931;;KREF-5544;;;;-1.500;;;;-1.500;;EUR',
            'Kontostand;24.11.2026;;;354,05;EUR',
            '',
        ];

        $csvFile = UploadedFile::fake()->createWithContent(
            'Kontoumsaetze_370_0000000_00_20261124_205306.csv',
            implode("\r\n", $csvLines)
        );

        Livewire::test(AccountingBank::class)
            ->call('openCsvModal')
            ->set('csvFile', $csvFile)
            ->call('importCsvTransactions')
            ->assertHasNoErrors();

        $createdAccount = AccountingBankAccount::where('admin_id', $this->admin->id)->first();
        $this->assertNotNull($createdAccount);
        $this->assertEquals('FYRST', $createdAccount->bank_name);
        $this->assertEquals('Geschäftskonto (FYRST BASE)', $createdAccount->account_name);
        $this->assertEquals('DE89370400440532013000', $createdAccount->iban);
        $this->assertTrue((bool) $createdAccount->is_business);
        $this->assertEquals(354.05, (float) $createdAccount->balance);

        // Must import exactly 3 real bookings (skipping preamble & trailing Kontostand row)
        $txs = AccountingBankTransaction::where('accounting_bank_account_id', $createdAccount->id)
            ->orderBy('transaction_date')
            ->get();
        $this->assertCount(3, $txs);

        // 1) Whole-thousand credit "2.000" -> +2000.00 (NOT +2.00!)
        $this->assertEquals(2000.00, (float) $txs[0]->amount);
        $this->assertEquals('Max Mustermann', $txs[0]->counterpart_name);
        $this->assertEquals('DE12500105170648489890', $txs[0]->counterpart_iban);

        // 2) Standard decimal debit "-145,95" -> -145.95
        $this->assertEquals(-145.95, (float) $txs[1]->amount);
        $this->assertEquals('Westpack A/S', $txs[1]->counterpart_name);

        // 3) Whole-thousand debit "-1.500" -> -1500.00 with empty Verwendungszweck fallback
        $this->assertEquals(-1500.00, (float) $txs[2]->amount);
        $this->assertEquals('xTool Laser GmbH', $txs[2]->counterpart_name);
        $this->assertStringContainsString('Lastschrift', (string) $txs[2]->purpose);

        // Verify financeCategory alias works for FinancialService & TaxAdvisorDashboard
        $loadedWithFinanceCat = AccountingBankTransaction::with(['account', 'financeCategory'])->find($txs[0]->id);
        $this->assertNotNull($loadedWithFinanceCat);

        // Re-importing the identical CSV must skip all 3 duplicates
        $csvFileRepeat = UploadedFile::fake()->createWithContent(
            'Kontoumsaetze_370_0000000_00_20261124_205306.csv',
            implode("\r\n", $csvLines)
        );

        Livewire::test(AccountingBank::class)
            ->call('openCsvModal')
            ->set('csvFile', $csvFileRepeat)
            ->call('importCsvTransactions')
            ->assertHasNoErrors();

        $this->assertEquals(3, AccountingBankTransaction::where('accounting_bank_account_id', $createdAccount->id)->count());
    }
}

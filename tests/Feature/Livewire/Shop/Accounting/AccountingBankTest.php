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
}

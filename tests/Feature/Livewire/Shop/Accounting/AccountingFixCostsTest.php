<?php

namespace Tests\Feature\Livewire\Shop\Accounting;

use App\Livewire\Shop\Accounting\AccountingFixCosts;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AccountingFixCostsTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->admin = \App\Models\Admin\Admin::find($adminId);
        $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    public function it_filters_missing_contracts_based_on_requires_contract_flag()
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Test Group',
            'type' => 'expense',
            'position' => 1
        ]);

        // Item 1: Requires contract, no contract file, no provider
        $item1 = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Requires Contract Item',
            'amount' => -100.00,
            'interval_months' => 1,
            'is_business' => false,
            'requires_contract' => true,
            'contract_file_path' => null,
            'provider_company' => null,
            'first_payment_date' => now()
        ]);

        // Item 2: Doesn't require contract, no contract file, no provider
        $item2 = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'No Contract Item',
            'amount' => -50.00,
            'interval_months' => 1,
            'is_business' => false,
            'requires_contract' => false,
            'contract_file_path' => null,
            'provider_company' => null,
            'first_payment_date' => now()
        ]);

        $component = Livewire::test(AccountingFixCosts::class);

        // Check missing contracts property
        $missingContracts = $component->get('missingContracts');
        $this->assertCount(1, $missingContracts);
        $this->assertEquals('Requires Contract Item', $missingContracts->first()->name);

        // Check missing data property
        $missingDataItems = $component->get('missingDataItems');
        $this->assertCount(1, $missingDataItems);
        $this->assertEquals('Requires Contract Item', $missingDataItems->first()->name);
    }
}

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

    #[Test]
    public function it_performs_fuzzy_search_across_groups_and_items()
    {
        $group1 = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Insurance Policies',
            'type' => 'expense',
            'position' => 1
        ]);

        $group2 = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Office Rent',
            'type' => 'expense',
            'position' => 2
        ]);

        // Item 1: In Group 1, name "Health Insurance", provider "Allianz", tags: ["privat"]
        $item1 = AccountingCostItem::create([
            'accounting_group_id' => $group1->id,
            'name' => 'Health Insurance',
            'amount' => -120.00,
            'interval_months' => 1,
            'is_business' => false,
            'requires_contract' => false,
            'provider_company' => 'Allianz',
            'description' => 'Personal health policy',
            'tags' => ['privat'],
            'first_payment_date' => now()
        ]);

        // Item 2: In Group 2, name "Coworking Space", provider "WeWork", description "monthly workspace", tags: ["business"]
        $item2 = AccountingCostItem::create([
            'accounting_group_id' => $group2->id,
            'name' => 'Coworking Space',
            'amount' => -300.00,
            'interval_months' => 1,
            'is_business' => true,
            'requires_contract' => true,
            'provider_company' => 'WeWork',
            'description' => 'monthly workspace',
            'tags' => ['business'],
            'first_payment_date' => now()
        ]);

        // 1. Search for group name matching "Insurance" (substring)
        $component = Livewire::test(AccountingFixCosts::class)
            ->set('searchQuery', 'Insurance');
        $groups = $component->viewData('groups');
        // Group 1 should match (by name) and retain all its items, Group 2 shouldn't match.
        $this->assertCount(1, $groups);
        $this->assertEquals('Insurance Policies', $groups->first()->name);

        // 2. Search for item provider with typo: "Alianz" (fuzzy Levenshtein matching Allianz)
        $component = Livewire::test(AccountingFixCosts::class)
            ->set('searchQuery', 'Alianz');
        $groups = $component->viewData('groups');
        $this->assertCount(1, $groups);
        $this->assertEquals('Insurance Policies', $groups->first()->name);
        $this->assertCount(1, $groups->first()->items);
        $this->assertEquals('Health Insurance', $groups->first()->items->first()->name);

        // 3. Search for tag: "business"
        $component = Livewire::test(AccountingFixCosts::class)
            ->set('searchQuery', 'business');
        $groups = $component->viewData('groups');
        $this->assertCount(1, $groups);
        $this->assertEquals('Office Rent', $groups->first()->name);
        $this->assertEquals('Coworking Space', $groups->first()->items->first()->name);

        // 4. Search for query that doesn't match anything
        $component = Livewire::test(AccountingFixCosts::class)
            ->set('searchQuery', 'NonexistentThing');
        $groups = $component->viewData('groups');
        $this->assertCount(0, $groups);
    }

    #[Test]
    public function it_toggles_panels_correctly()
    {
        $component = Livewire::test(AccountingFixCosts::class);

        // Default states
        $this->assertFalse($component->get('showMissingDocs'));
        $this->assertFalse($component->get('showMissingData'));
        $this->assertFalse($component->get('showTagManagement'));
        $this->assertFalse($component->get('showChart'));

        // Toggle properties
        $component->set('showMissingDocs', !$component->get('showMissingDocs'));
        $this->assertTrue($component->get('showMissingDocs'));

        $component->set('showMissingData', !$component->get('showMissingData'));
        $this->assertTrue($component->get('showMissingData'));

        $component->set('showTagManagement', !$component->get('showTagManagement'));
        $this->assertTrue($component->get('showTagManagement'));

        $component->set('showChart', !$component->get('showChart'));
        $this->assertTrue($component->get('showChart'));
    }
}

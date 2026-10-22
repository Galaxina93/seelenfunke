<?php

namespace Tests\Feature\Livewire\Shop\Accounting;

use App\Livewire\Shop\Accounting\AccountingFixCosts;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $this->assertFalse($component->get('showArchive'));
        $component->set('showArchive', true);
        $this->assertTrue($component->get('showArchive'));
    }

    #[Test]
    public function it_soft_deletes_item_to_archive_and_allows_restore_and_force_delete()
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'IT Infrastructure',
            'type' => 'expense',
            'position' => 1
        ]);

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Server Cloud Pro',
            'amount' => -45.00,
            'interval_months' => 1,
            'first_payment_date' => now(),
            'tags' => ['Server', 'Hosting']
        ]);

        $component = Livewire::test(AccountingFixCosts::class);

        // Initially archive is empty
        $this->assertCount(0, $component->get('archivedItems'));

        // Delete item -> soft-delete
        $component->call('deleteItem', $item->id);

        // Should now be in archivedItems
        $archivedItems = $component->get('archivedItems');
        $this->assertCount(1, $archivedItems);
        $this->assertEquals('Server Cloud Pro', $archivedItems->first()->name);

        // Not in active DB query
        $this->assertNull(AccountingCostItem::find($item->id));

        // Restore item
        $component->call('restoreItem', $item->id);

        // Archive should be empty again and active item back
        $this->assertCount(0, $component->get('archivedItems'));
        $this->assertNotNull(AccountingCostItem::find($item->id));

        // Delete again, then force delete
        $component->call('deleteItem', $item->id);
        $this->assertCount(1, $component->get('archivedItems'));

        $component->call('forceDeleteItem', $item->id);
        $this->assertCount(0, $component->get('archivedItems'));
        $this->assertNull(AccountingCostItem::onlyTrashed()->find($item->id));
    }

    #[Test]
    public function it_handles_group_level_archiving_contract_preservation_and_force_delete()
    {
        Storage::fake('local');

        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Versicherungen',
            'type' => 'expense',
            'position' => 1
        ]);

        $contractPath = 'buchhaltung/contracts/2026/10/2026-10-22_Allianz_Haftpflicht.pdf';
        Storage::disk('local')->put($contractPath, 'dummy pdf content');

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Haftpflichtversicherung',
            'amount' => -120.00,
            'interval_months' => 12,
            'first_payment_date' => now(),
            'contract_file_path' => $contractPath,
            'tags' => ['Versicherung', 'Haftpflicht']
        ]);

        // Group should have 1 active item and 0 archived items
        $this->assertEquals(1, $group->fresh()->items->count());
        $this->assertEquals(0, $group->fresh()->archivedItems->count());

        $component = Livewire::test(AccountingFixCosts::class);

        // 1. Archive item
        $component->call('archiveItem', $item->id);

        // Item must be excluded from group active items and present in group archivedItems
        $this->assertEquals(0, $group->fresh()->items->count());
        $this->assertEquals(1, $group->fresh()->archivedItems->count());

        // Contract file MUST be preserved while archived
        Storage::disk('local')->assertExists($contractPath);

        // 2. Toggle group archive view
        $component->call('toggleGroupArchive', $group->id);
        $this->assertContains($group->id, $component->get('openGroupArchives'));
        $this->assertEquals($group->id, $component->get('activeGroupId'));

        // Toggle it off
        $component->call('toggleGroupArchive', $group->id);
        $this->assertNotContains($group->id, $component->get('openGroupArchives'));

        // 3. Restore item
        $component->call('restoreItem', $item->id);
        $this->assertEquals(1, $group->fresh()->items->count());
        $this->assertEquals(0, $group->fresh()->archivedItems->count());

        // 4. Force delete directly from active or archived status deletes item AND contract file
        $component->call('forceDeleteItem', $item->id);
        $this->assertDatabaseMissing('accounting_cost_items', ['id' => $item->id]);
        Storage::disk('local')->assertMissing($contractPath);
        $this->assertEquals(0, $group->fresh()->items->count());
        $this->assertEquals(0, $group->fresh()->archivedItems->count());
    }

    #[Test]
    public function it_logically_names_and_stores_contract_file_on_item_save()
    {
        Storage::fake('local');

        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'IT Infrastructure',
            'type' => 'expense',
            'position' => 1
        ]);

        $file = UploadedFile::fake()->create('upload_123.pdf', 50, 'application/pdf');

        $component = Livewire::test(AccountingFixCosts::class)
            ->set('addingToGroupId', $group->id)
            ->set('itemName', 'Managed Cloud Server')
            ->set('itemAmount', '49.99')
            ->set('itemInterval', 1)
            ->set('itemDate', '2026-08-11')
            ->set('itemProviderCompany', 'Mittwald CM Service')
            ->set('itemFile', $file)
            ->call('saveItem');

        $item = AccountingCostItem::where('name', 'Managed Cloud Server')->first();
        $this->assertNotNull($item);
        $this->assertEquals('buchhaltung/contracts/2026/08/2026-08-11_Mittwald_CM_Service_Managed_Cloud_Server.pdf', $item->contract_file_path);
        Storage::disk('local')->assertExists($item->contract_file_path);
    }

    #[Test]
    public function it_logically_names_and_stores_contract_file_on_quick_upload()
    {
        Storage::fake('local');

        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Versicherungen',
            'type' => 'expense',
            'position' => 1
        ]);

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Autoversicherung',
            'amount' => -387.23,
            'interval_months' => 12,
            'first_payment_date' => '2026-08-11',
            'provider_company' => 'ADAC',
            'requires_contract' => true,
            'contract_file_path' => null
        ]);

        $file = UploadedFile::fake()->create('scan_document.pdf', 50, 'application/pdf');

        $component = Livewire::test(AccountingFixCosts::class)
            ->set('uploadingMissingItemId', $item->id)
            ->set('quickUploadFile', $file)
            ->call('saveQuickUpload');

        $item->refresh();
        $this->assertEquals('buchhaltung/contracts/2026/08/2026-08-11_ADAC_Autoversicherung.pdf', $item->contract_file_path);
        Storage::disk('local')->assertExists($item->contract_file_path);
    }

    #[Test]
    public function it_can_trigger_ai_contract_analysis_and_autofill_properties()
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Versicherungen',
            'type' => 'expense',
            'position' => 1
        ]);

        $realPdfPath = storage_path('app/buchhaltung/contracts/2026/08/2026-08-11_ADAC_Autoversicherung.pdf');
        if (!file_exists($realPdfPath)) {
            $this->markTestSkipped('ADAC contract file does not exist.');
        }

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Autoversicherung',
            'amount' => 0,
            'interval_months' => 1,
            'first_payment_date' => '2026-08-11',
            'contract_file_path' => 'buchhaltung/contracts/2026/08/2026-08-11_ADAC_Autoversicherung.pdf'
        ]);

        $component = Livewire::test(AccountingFixCosts::class)
            ->call('openItemForm', $group->id, $item->id)
            ->call('analyzeContractWithAi');

        $this->assertNotNull($component->get('aiContractAnalysis'));
        $this->assertTrue($component->get('aiContractAnalysis')['success']);
        $this->assertStringContainsString('ADAC', $component->get('itemProviderCompany'));
        $this->assertNotEmpty($component->get('itemTags'));
        $this->assertContains('Versicherung', $component->get('itemTags'));
    }

    #[Test]
    public function it_can_autofill_all_fields_when_creating_new_item_from_uploaded_document()
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Versicherungen',
            'type' => 'expense',
            'position' => 1
        ]);

        $realPdfPath = storage_path('app/buchhaltung/contracts/2026/08/2026-08-11_ADAC_Autoversicherung.pdf');
        if (!file_exists($realPdfPath)) {
            $this->markTestSkipped('ADAC contract file does not exist.');
        }

        $file = UploadedFile::fake()->createWithContent(
            'ADAC_Autoversicherung.pdf',
            file_get_contents($realPdfPath)
        );

        $component = Livewire::test(AccountingFixCosts::class)
            ->call('toggleAddItemForm', $group->id)
            ->set('itemFile', $file)
            ->call('analyzeContractWithAi');

        $this->assertNotNull($component->get('aiContractAnalysis'));
        $this->assertNotEmpty($component->get('itemName'));
        $this->assertStringContainsString('ADAC', $component->get('itemProviderCompany'));
        $this->assertTrue((float)$component->get('itemAmount') > 0);
        $this->assertTrue((int)$component->get('itemInterval') >= 1);
        $this->assertTrue((bool)$component->get('itemRequiresContract'));
        $this->assertNotEmpty($component->get('itemTags'));
        $this->assertContains('Versicherung', $component->get('itemTags'));

        // Save newly filled item
        $component->call('saveNewItem', $group->id);

        $item = AccountingCostItem::where('accounting_group_id', $group->id)->first();
        $this->assertNotNull($item);
        $this->assertStringContainsString('ADAC', $item->provider_company);
        $this->assertNotNull($item->contract_file_path);
        $this->assertIsArray($item->tags);
        $this->assertContains('Versicherung', $item->tags);
    }

    #[Test]
    public function it_can_manage_tags_interactively_on_cost_item_form()
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'IT & Software',
            'type' => 'expense',
            'position' => 1
        ]);

        $component = Livewire::test(AccountingFixCosts::class)
            ->call('toggleAddItemForm', $group->id)
            ->call('addTagToCurrentForm', 'Hosting')
            ->set('itemNewTag', 'Mittwald')
            ->call('addTagToCurrentForm')
            ->set('itemName', 'Webhosting Server')
            ->set('itemAmount', '49.90')
            ->set('itemDate', '2026-01-01')
            ->call('saveNewItem', $group->id);

        $item = AccountingCostItem::where('accounting_group_id', $group->id)->first();
        $this->assertNotNull($item);
        $this->assertContains('Hosting', $item->tags);
        $this->assertContains('Mittwald', $item->tags);

        // Edit and remove a tag
        $component->call('openItemForm', $group->id, $item->id)
            ->assertSet('itemTags', ['Hosting', 'Mittwald'])
            ->call('removeTagFromCurrentForm', 'Mittwald')
            ->call('saveItem');

        $item->refresh();
        $this->assertEquals(['Hosting'], $item->tags);
    }
}

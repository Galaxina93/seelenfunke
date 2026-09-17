<?php

namespace Tests\Feature\Services\AI;

use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingCostItemHistory;
use App\Models\Accounting\AccountingGroup;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AiFinanceFixedCostsTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminId = (string) Str::uuid();
        DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'Buchi',
            'last_name' => 'Tester',
            'email' => 'finance-' . uniqid() . '@seelenfunke.test',
            'password' => bcrypt('secret123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->admin = Admin::find($adminId);
        $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    public function it_lists_fixed_costs_with_item_uuids_and_metadata(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Hosting & Cloud',
            'type' => 'expense',
            'position' => 1
        ]);

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Netcup vServer',
            'description' => 'Hauptserver für Shop',
            'amount' => -24.99,
            'interval_months' => 1,
            'first_payment_date' => '2026-01-01',
            'is_business' => true,
            'requires_contract' => true,
            'tax_rate' => 19,
            'provider_company' => 'Netcup GmbH',
            'contract_number' => 'NC-998822',
        ]);

        $result = AIFunctionsRegistry::execute('finance_list_fixed_costs', []);

        $this->assertEquals('success', $result['status']);
        $this->assertNotEmpty($result['fixed_costs_by_category']);

        $category = $result['fixed_costs_by_category'][0];
        $this->assertEquals($group->id, $category['group_id']);
        $this->assertEquals('Hosting & Cloud', $category['group_name']);
        $this->assertEquals('expense', $category['type']);

        $this->assertCount(1, $category['items']);
        $listedItem = $category['items'][0];
        $this->assertEquals($item->id, $listedItem['id']);
        $this->assertEquals('Netcup vServer', $listedItem['name']);
        $this->assertEquals(-24.99, $listedItem['amount']);
        $this->assertEquals(1, $listedItem['interval_months']);
        $this->assertEquals('2026-01-01', $listedItem['first_payment_date']);
        $this->assertTrue($listedItem['is_business']);
        $this->assertTrue($listedItem['requires_contract']);
        $this->assertEquals(19, $listedItem['tax_rate']);
        $this->assertEquals('Netcup GmbH', $listedItem['provider_company']);
        $this->assertEquals('NC-998822', $listedItem['contract_number']);
    }

    #[Test]
    public function it_filters_fixed_costs_by_search_query(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Laufende Kosten',
            'type' => 'expense',
            'position' => 1
        ]);

        AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Google Workspace',
            'amount' => -15.00,
            'interval_months' => 1,
            'first_payment_date' => '2026-01-01',
        ]);

        AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Adobe Creative Cloud',
            'amount' => -60.00,
            'interval_months' => 1,
            'first_payment_date' => '2026-01-01',
        ]);

        // Search for "Workspace"
        $result = AIFunctionsRegistry::execute('finance_list_fixed_costs', ['query' => 'Workspace']);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['total_items']);
        $this->assertEquals('Google Workspace', $result['fixed_costs_by_category'][0]['items'][0]['name']);

        // Search for "Adobe"
        $resultAdobe = AIFunctionsRegistry::execute('finance_list_fixed_costs', ['query' => 'adobe']);
        $this->assertEquals('success', $resultAdobe['status']);
        $this->assertEquals(1, $resultAdobe['total_items']);
        $this->assertEquals('Adobe Creative Cloud', $resultAdobe['fixed_costs_by_category'][0]['items'][0]['name']);

        // Search for non-existing term
        $resultEmpty = AIFunctionsRegistry::execute('finance_list_fixed_costs', ['query' => 'UnbekanntXyz']);
        $this->assertEquals('success', $resultEmpty['status']);
        $this->assertEquals(0, $resultEmpty['total_items']);
    }

    #[Test]
    public function it_creates_new_fixed_cost_with_history(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Software & Tools',
            'type' => 'expense',
            'position' => 1
        ]);

        $args = [
            'name' => 'Shopify App Subscription',
            'amount' => -39.99,
            'interval_months' => 1,
            'group_id' => $group->id,
            'first_payment_date' => '2026-05-01',
            'is_business' => true,
            'tax_rate' => 19,
            'requires_contract' => false,
            'description' => 'Live-Chat Widget Lizenz',
            'provider_company' => 'Shopify Inc',
            'contract_number' => 'SHP-123456',
            'notice_period' => '1 Monat',
        ];

        $result = AIFunctionsRegistry::execute('finance_create_fixed_cost', $args);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('id', $result);

        // Check in database
        $createdItem = AccountingCostItem::find($result['id']);
        $this->assertNotNull($createdItem);
        $this->assertEquals('Shopify App Subscription', $createdItem->name);
        $this->assertEquals(-39.99, (float)$createdItem->amount);
        $this->assertEquals(1, $createdItem->interval_months);
        $this->assertEquals($group->id, $createdItem->accounting_group_id);
        $this->assertEquals('Shopify Inc', $createdItem->provider_company);
        $this->assertTrue($createdItem->is_business);

        // Check history
        $history = AccountingCostItemHistory::where('accounting_cost_item_id', $createdItem->id)->first();
        $this->assertNotNull($history);
        $this->assertStringContainsString('Buchi', $history->description);
        $this->assertEquals(-39.99, (float)$history->amount);
    }

    #[Test]
    public function it_creates_group_automatically_when_group_name_provided(): void
    {
        $args = [
            'name' => 'Lagerhalle Miete',
            'amount' => -850.00,
            'interval_months' => 1,
            'group_name' => 'Miete & Logistik',
            'first_payment_date' => '2026-06-01',
        ];

        $result = AIFunctionsRegistry::execute('finance_create_fixed_cost', $args);

        $this->assertEquals('success', $result['status']);

        // Group should have been created
        $group = AccountingGroup::where('admin_id', $this->admin->id)
            ->where('name', 'Miete & Logistik')
            ->first();
        $this->assertNotNull($group);
        $this->assertEquals('expense', $group->type);

        $item = AccountingCostItem::find($result['id']);
        $this->assertEquals($group->id, $item->accounting_group_id);
    }

    #[Test]
    public function it_edits_existing_fixed_cost_and_records_history_diff(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Bürobedarf',
            'type' => 'expense',
            'position' => 1
        ]);

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Kaffee-Abo',
            'amount' => -25.00,
            'interval_months' => 1,
            'first_payment_date' => '2026-01-01',
            'is_business' => true,
        ]);

        $editArgs = [
            'id' => $item->id,
            'amount' => -35.50,
            'interval_months' => 2,
            'description' => 'Preiserhöhung und 2-Monats-Intervall',
        ];

        $result = AIFunctionsRegistry::execute('finance_edit_fixed_cost', $editArgs);

        $this->assertEquals('success', $result['status']);

        $item->refresh();
        $this->assertEquals(-35.50, (float)$item->amount);
        $this->assertEquals(2, $item->interval_months);
        $this->assertEquals('Preiserhöhung und 2-Monats-Intervall', $item->description);

        // Check history
        $latestHistory = AccountingCostItemHistory::where('accounting_cost_item_id', $item->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($latestHistory);
        $this->assertStringContainsString('Buchi', $latestHistory->description);
        $this->assertStringContainsString('35.5', $latestHistory->description);
    }

    #[Test]
    public function it_deletes_fixed_cost(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Alte Verträge',
            'type' => 'expense',
            'position' => 1
        ]);

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Veralteter DSL-Vertrag',
            'amount' => -49.90,
            'interval_months' => 1,
            'first_payment_date' => '2024-01-01',
        ]);

        $itemId = $item->id;

        $result = AIFunctionsRegistry::execute('finance_delete_fixed_cost', ['id' => $itemId]);

        $this->assertEquals('success', $result['status']);
        $this->assertStringContainsString('Veralteter DSL-Vertrag', $result['message']);
        $this->assertNull(AccountingCostItem::find($itemId));
    }

    #[Test]
    public function it_prevents_unauthorized_cross_tenant_editing_and_deleting(): void
    {
        // Second admin
        $otherAdminId = (string) Str::uuid();
        DB::table('admins')->insert([
            'id' => $otherAdminId,
            'first_name' => 'Other',
            'last_name' => 'Admin',
            'email' => 'other-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherGroup = AccountingGroup::create([
            'admin_id' => $otherAdminId,
            'name' => 'Geheime Kosten',
            'type' => 'expense',
            'position' => 1
        ]);

        $otherItem = AccountingCostItem::create([
            'accounting_group_id' => $otherGroup->id,
            'name' => 'Anderer Server',
            'amount' => -100.00,
            'interval_months' => 1,
            'first_payment_date' => '2026-01-01',
        ]);

        // Current session is $this->admin (not otherAdmin)
        $editResult = AIFunctionsRegistry::execute('finance_edit_fixed_cost', [
            'id' => $otherItem->id,
            'amount' => -50.00
        ]);
        $this->assertEquals('error', $editResult['status']);
        $this->assertStringContainsString('Zugriff verweigert', $editResult['message']);

        $deleteResult = AIFunctionsRegistry::execute('finance_delete_fixed_cost', [
            'id' => $otherItem->id
        ]);
        $this->assertEquals('error', $deleteResult['status']);
        $this->assertStringContainsString('Zugriff verweigert', $deleteResult['message']);

        // Item should remain unchanged in DB
        $this->assertNotNull(AccountingCostItem::find($otherItem->id));
    }

    #[Test]
    public function it_runs_fixed_cost_migration_and_attaches_tools_to_finanzmanager(): void
    {
        // Set up role and agent
        $role = AiRole::create([
            'name' => 'Finanzmanager',
            'description' => 'Finance role'
        ]);

        $buchiAgent = AiAgent::create([
            'name' => 'Buchi',
            'ai_role_id' => $role->id,
            'system_prompt' => 'Du bist Buchi. SPRACHMELODIE: Ernst.',
        ]);

        // Run the migration up method
        $migration = require database_path('migrations/2026_03_27_000001_create_ai_agent_tables.php');
        $migration->up();

        // Check that tools exist in ai_tools
        $this->assertDatabaseHas('ai_tools', ['identifier' => 'finance_create_fixed_cost']);
        $this->assertDatabaseHas('ai_tools', ['identifier' => 'finance_edit_fixed_cost']);
        $this->assertDatabaseHas('ai_tools', ['identifier' => 'finance_delete_fixed_cost']);

        // Check that role has tools attached
        $roleTools = $role->fresh()->tools->pluck('identifier')->toArray();
        $this->assertContains('finance_create_fixed_cost', $roleTools);
        $this->assertContains('finance_edit_fixed_cost', $roleTools);
        $this->assertContains('finance_delete_fixed_cost', $roleTools);

        // Check that Buchi agent system prompt was updated
        $buchiAgent->refresh();
        $this->assertStringContainsString('finance_create_fixed_cost', $buchiAgent->system_prompt);
        $this->assertStringContainsString('FIXKOSTEN', $buchiAgent->system_prompt);
    }

    #[Test]
    public function it_auto_generates_sensible_tags_when_creating_fixed_cost(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Hosting & Infrastructure',
            'type' => 'expense',
            'position' => 1
        ]);

        $args = [
            'name' => 'Hetzner Dedicated Root Server',
            'amount' => -89.00,
            'interval_months' => 1,
            'group_id' => $group->id,
            'first_payment_date' => '2026-06-01',
            'is_business' => true,
            'provider_company' => 'Hetzner Online GmbH'
        ];

        $result = AIFunctionsRegistry::execute('finance_create_fixed_cost', $args);
        $this->assertEquals('success', $result['status']);

        $item = AccountingCostItem::find($result['id']);
        $this->assertNotEmpty($item->tags);
        $this->assertContains('Hetzner', $item->tags);
        $this->assertContains('Server', $item->tags);
        $this->assertContains('Monatlich', $item->tags);
        $this->assertContains('Gewerblich', $item->tags);
    }

    #[Test]
    public function it_soft_deletes_item_to_archive_instead_of_hard_deleting(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Software',
            'type' => 'expense',
            'position' => 1
        ]);

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Slack Pro Subscription',
            'amount' => -12.50,
            'interval_months' => 1,
            'first_payment_date' => '2026-01-01',
        ]);

        $result = AIFunctionsRegistry::execute('finance_delete_fixed_cost', ['id' => $item->id]);
        $this->assertEquals('success', $result['status']);
        $this->assertStringContainsString('archiviert', $result['message']);

        // Item is not found in normal queries
        $this->assertNull(AccountingCostItem::find($item->id));

        // But is found in onlyTrashed
        $trashed = AccountingCostItem::onlyTrashed()->find($item->id);
        $this->assertNotNull($trashed);
        $this->assertEquals('Slack Pro Subscription', $trashed->name);
    }

    #[Test]
    public function it_detects_archived_duplicate_on_create_and_suggests_restore(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Online-Dienste',
            'type' => 'expense',
            'position' => 1
        ]);

        // Create and then soft-delete an item
        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'ChatGPT Plus Team',
            'amount' => -25.00,
            'interval_months' => 1,
            'first_payment_date' => '2025-01-01',
        ]);
        $item->delete();

        // Attempt to create item with the same name without ignore_archived
        $createArgs = [
            'name' => 'ChatGPT Plus Team',
            'amount' => -30.00,
            'interval_months' => 1,
            'group_id' => $group->id,
            'first_payment_date' => '2026-06-01',
        ];

        $result = AIFunctionsRegistry::execute('finance_create_fixed_cost', $createArgs);

        $this->assertEquals('archived_match_found', $result['status']);
        $this->assertEquals($item->id, $result['archived_item']['id']);
        $this->assertStringContainsString('finance_restore_fixed_cost', $result['message']);

        // Now create with ignore_archived = true
        $createArgs['ignore_archived'] = true;
        $resultBypass = AIFunctionsRegistry::execute('finance_create_fixed_cost', $createArgs);
        $this->assertEquals('success', $resultBypass['status']);
        $this->assertNotEquals($item->id, $resultBypass['id']);
    }

    #[Test]
    public function it_lists_and_restores_archived_fixed_costs(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Tools',
            'type' => 'expense',
            'position' => 1
        ]);

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Archivierte Lizenz',
            'amount' => -49.00,
            'interval_months' => 12,
            'first_payment_date' => '2025-01-01',
        ]);
        $item->delete();

        // List archived
        $listResult = AIFunctionsRegistry::execute('finance_list_archived_fixed_costs', []);
        $this->assertEquals('success', $listResult['status']);
        $this->assertEquals(1, $listResult['total_archived_items']);
        $this->assertEquals('Archivierte Lizenz', $listResult['archived_items'][0]['name']);

        // Restore
        $restoreResult = AIFunctionsRegistry::execute('finance_restore_fixed_cost', ['id' => $item->id]);
        $this->assertEquals('success', $restoreResult['status']);

        // Item should now be active again
        $restored = AccountingCostItem::find($item->id);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);

        // List archived should now be empty
        $listAfter = AIFunctionsRegistry::execute('finance_list_archived_fixed_costs', []);
        $this->assertEquals(0, $listAfter['total_archived_items']);
    }

    #[Test]
    public function it_permanently_force_deletes_archived_fixed_cost(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Temp',
            'type' => 'expense',
            'position' => 1
        ]);

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Definitiv löschen',
            'amount' => -10.00,
            'interval_months' => 1,
            'first_payment_date' => '2025-01-01',
        ]);
        $item->delete();

        $forceResult = AIFunctionsRegistry::execute('finance_force_delete_fixed_cost', ['id' => $item->id]);
        $this->assertEquals('success', $forceResult['status']);

        // Neither normal find nor onlyTrashed find should locate the record
        $this->assertNull(AccountingCostItem::find($item->id));
        $this->assertNull(AccountingCostItem::onlyTrashed()->find($item->id));
    }

    #[Test]
    public function it_runs_archive_ai_tools_migration_and_updates_buchi(): void
    {
        $role = AiRole::create([
            'name' => 'Finanzmanager',
            'description' => 'Finance role'
        ]);

        $buchiAgent = AiAgent::create([
            'name' => 'Buchi',
            'ai_role_id' => $role->id,
            'system_prompt' => 'Du bist Buchi. 6. FIXKOSTEN-VERWALTUNG: Altes Prompt. SPRACHMELODIE: Ernst.',
        ]);

        $migration = require database_path('migrations/2026_03_27_000001_create_ai_agent_tables.php');
        $migration->up();

        $this->assertDatabaseHas('ai_tools', ['identifier' => 'finance_list_archived_fixed_costs']);
        $this->assertDatabaseHas('ai_tools', ['identifier' => 'finance_restore_fixed_cost']);
        $this->assertDatabaseHas('ai_tools', ['identifier' => 'finance_force_delete_fixed_cost']);

        $roleTools = $role->fresh()->tools->pluck('identifier')->toArray();
        $this->assertContains('finance_list_archived_fixed_costs', $roleTools);
        $this->assertContains('finance_restore_fixed_cost', $roleTools);
        $this->assertContains('finance_force_delete_fixed_cost', $roleTools);

        $buchiAgent->refresh();
        $this->assertStringContainsString('finance_restore_fixed_cost', $buchiAgent->system_prompt);
        $this->assertStringContainsString('FIXKOSTEN', $buchiAgent->system_prompt);
    }

    #[Test]
    public function it_finds_similar_terms_dynamically_like_gruenderzuschuss(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Staatliche Förderungen',
            'type' => 'income',
            'position' => 1
        ]);

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Gründungszuschuss',
            'amount' => 300.00,
            'interval_months' => 1,
            'first_payment_date' => '2026-01-01',
            'is_business' => true,
        ]);

        // Search with inflection/variant "Gründerzuschuss"
        $listResult = AIFunctionsRegistry::execute('finance_list_fixed_costs', [
            'query' => 'Gründerzuschuss'
        ]);

        $this->assertEquals('success', $listResult['status']);
        $this->assertEquals(1, $listResult['total_items']);
        $this->assertEquals('Gründungszuschuss', $listResult['fixed_costs_by_category'][0]['items'][0]['name']);

        // Dynamic edit targeting via "Gründerzuschuss"
        $editResult = AIFunctionsRegistry::execute('finance_edit_fixed_cost', [
            'name' => 'Gründerzuschuss',
            'amount' => 350.00
        ]);

        $this->assertEquals('success', $editResult['status']);
        $this->assertStringContainsString('350', $editResult['message']);
        $item->refresh();
        $this->assertEquals(350.00, (float)$item->amount);
    }

    #[Test]
    public function it_reads_fixed_cost_contract_document(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Verträge & Hosting',
            'type' => 'expense',
            'position' => 1
        ]);

        $fakeFilePath = 'contracts/webhosting_vertrag.txt';
        \Illuminate\Support\Facades\Storage::disk('local')->put($fakeFilePath, "VERTRAGSVEREINBARUNG\nAnbieter: Hetzner Cloud GmbH\nMonatlicher Betrag: 45,00 Euro netto\nKündigungsfrist: 30 Tage zum Monatsende\nVertragsbeginn: 01.01.2026");

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Hetzner Root Server',
            'amount' => -45.00,
            'interval_months' => 1,
            'first_payment_date' => '2026-01-01',
            'requires_contract' => true,
            'contract_file_path' => $fakeFilePath,
            'provider_company' => 'Hetzner',
        ]);

        // 1. Read contract by UUID
        $readResult = AIFunctionsRegistry::execute('finance_read_fixed_cost_contract', [
            'id' => $item->id
        ]);

        $this->assertEquals('success', $readResult['status']);
        $this->assertTrue($readResult['has_contract_file']);
        $this->assertEquals('Hetzner Root Server', $readResult['item_name']);
        $this->assertStringContainsString('Hetzner Cloud GmbH', $readResult['contract_text']);
        $this->assertStringContainsString('Kündigungsfrist: 30 Tage', $readResult['contract_text']);

        // 2. Read contract by flexible / fuzzy name
        $readFuzzyResult = AIFunctionsRegistry::execute('finance_read_fixed_cost_contract', [
            'query' => 'Hetzner Server'
        ]);
        $this->assertEquals('success', $readFuzzyResult['status']);
        $this->assertStringContainsString('VERTRAGSVEREINBARUNG', $readFuzzyResult['contract_text']);

        // 3. Item without contract
        $itemWithoutContract = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Bürokaffee',
            'amount' => -20.00,
            'interval_months' => 1,
            'first_payment_date' => '2026-01-01',
            'requires_contract' => false,
            'contract_file_path' => null,
        ]);

        $readEmptyResult = AIFunctionsRegistry::execute('finance_read_fixed_cost_contract', [
            'id' => $itemWithoutContract->id
        ]);
        $this->assertEquals('success', $readEmptyResult['status']);
        $this->assertFalse($readEmptyResult['has_contract_file']);
        $this->assertStringContainsString('kein Vertrag', $readEmptyResult['message']);

        // 4. Scanned or malformed PDF without catalog (does not crash with unhandled exception)
        $corruptPdfPath = 'contracts/corrupt_contract.pdf';
        \Illuminate\Support\Facades\Storage::disk('local')->put($corruptPdfPath, "%PDF-1.4\n1 0 obj\n<< /Type /NotCatalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");

        $itemWithCorruptPdf = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Gescannter Mietvertrag',
            'amount' => -500.00,
            'interval_months' => 1,
            'first_payment_date' => '2026-01-01',
            'requires_contract' => true,
            'contract_file_path' => $corruptPdfPath,
        ]);

        $readCorruptResult = AIFunctionsRegistry::execute('finance_read_fixed_cost_contract', [
            'id' => $itemWithCorruptPdf->id
        ]);
        $this->assertEquals('success', $readCorruptResult['status']);
        $this->assertTrue($readCorruptResult['has_contract_file']);
        $this->assertNotEmpty($readCorruptResult['contract_text']);
    }

    #[Test]
    public function it_preserves_contract_file_when_archiving_and_restoring(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Daueraufträge',
            'type' => 'expense',
            'position' => 1
        ]);

        $contractPath = 'contracts/dauervertrag_2026.txt';
        \Illuminate\Support\Facades\Storage::disk('local')->put($contractPath, "Dauerschuldverhältnis 2026: monatlich 100 Euro.");

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Dauerhafter Softwarevertrag',
            'amount' => -100.00,
            'interval_months' => 1,
            'first_payment_date' => '2026-01-01',
            'requires_contract' => true,
            'contract_file_path' => $contractPath,
        ]);

        $itemId = $item->id;

        // Archive the item (Soft-Delete)
        $deleteResult = AIFunctionsRegistry::execute('finance_delete_fixed_cost', ['id' => $itemId]);
        $this->assertEquals('success', $deleteResult['status']);
        $this->assertStringContainsString('archiviert', $deleteResult['message']);

        // Verify record is trashed, but DB still retains contract_file_path and file exists on disk
        $trashed = AccountingCostItem::onlyTrashed()->find($itemId);
        $this->assertNotNull($trashed);
        $this->assertEquals($contractPath, $trashed->contract_file_path);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($contractPath));

        // Restore the item
        $restoreResult = AIFunctionsRegistry::execute('finance_restore_fixed_cost', ['id' => $itemId]);
        $this->assertEquals('success', $restoreResult['status']);
        $this->assertTrue($restoreResult['item']['has_contract_file']);

        // Check restored item
        $restored = AccountingCostItem::find($itemId);
        $this->assertNotNull($restored);
        $this->assertEquals($contractPath, $restored->contract_file_path);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($contractPath));

        // Verify contract can still be read seamlessly
        $readResult = AIFunctionsRegistry::execute('finance_read_fixed_cost_contract', ['id' => $itemId]);
        $this->assertEquals('success', $readResult['status']);
        $this->assertStringContainsString('Dauerschuldverhältnis 2026', $readResult['contract_text']);
    }

    #[Test]
    public function it_runs_read_contract_ai_tool_migration_and_updates_buchi(): void
    {
        $role = AiRole::create([
            'name' => 'Finanzmanager',
            'description' => 'Finance role'
        ]);

        $buchiAgent = AiAgent::create([
            'name' => 'Buchi',
            'ai_role_id' => $role->id,
            'system_prompt' => 'Du bist Buchi. 6. FIXKOSTEN- & ARCHIV-VERWALTUNG. SPRACHMELODIE: Ernst.',
        ]);

        $migration = require database_path('migrations/2026_03_27_000001_create_ai_agent_tables.php');
        $migration->up();

        $this->assertDatabaseHas('ai_tools', ['identifier' => 'finance_read_fixed_cost_contract']);

        $roleTools = $role->fresh()->tools->pluck('identifier')->toArray();
        $this->assertContains('finance_read_fixed_cost_contract', $roleTools);

        $buchiAgent->refresh();
        $this->assertStringContainsString('finance_read_fixed_cost_contract', $buchiAgent->system_prompt);
        $this->assertStringContainsString('Verträge lesen', $buchiAgent->system_prompt);
        $this->assertStringContainsString('FIXKOSTEN-, ARCHIV- & VERTRAGS-VERWALTUNG', $buchiAgent->system_prompt);
    }
}

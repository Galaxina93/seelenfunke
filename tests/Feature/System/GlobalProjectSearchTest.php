<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\Management\ManagementTask as ManagementTaskComponent;
use App\Livewire\Shop\Order\OrderOverview;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Management\ManagementContact;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Models\Product\ProductSupplier;
use App\Models\System\SystemSetting;
use App\Services\System\GlobalProjectSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalProjectSearchTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function global_search_endpoint_enforces_strict_admin_guard(): void
    {
        // 1. Unauthentifiziert -> blockiert
        $this->getJson('/admin/global-search?q=iban')->assertStatus(401);

        // 2. Customer -> blockiert
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')
            ->getJson('/admin/global-search?q=iban')
            ->assertStatus(401);

        // 3. Employee (Influencer / Steuerberater) -> strikte Rollen-Isolation
        $influencer = Employee::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'Creator',
            'email' => 'creator-search-test@example.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'influencer',
            'is_active' => true,
        ]);
        $this->actingAs($influencer, 'employee')
            ->getJson('/admin/global-search?q=iban')
            ->assertStatus(401);

        // 4. Authentifizierter Admin -> 200 OK
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')
            ->getJson('/admin/global-search?q=iban')
            ->assertOk()
            ->assertJsonStructure(['query', 'total', 'groups']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function shop_config_and_navigation_catalog_support_synonyms_fuzzy_typos_and_360_feature_gate(): void
    {
        $service = app(GlobalProjectSearchService::class);

        // Synonym-Treffer "fyrst" -> IBAN Einstellung unter Stammdaten
        $fyrstRes = $service->searchAll('fyrst');
        $settingsGroup = collect($fyrstRes['groups'])->firstWhere('key', 'settings');
        $this->assertNotNull($settingsGroup);
        $ibanHit = collect($settingsGroup['items'])->firstWhere('id', 'cfg_owner_iban');
        $this->assertNotNull($ibanHit);
        $this->assertSame('/admin/configuration?tab=owner&focus=owner_iban', $ibanHit['url']);

        // Tippfehler-Toleranz "wartungsmodsu" -> Wartungsmodus
        $typoRes = $service->searchAll('wartungsmodsu');
        $typoSettings = collect($typoRes['groups'])->firstWhere('key', 'settings');
        $this->assertNotNull($typoSettings);
        $this->assertNotNull(collect($typoSettings['items'])->firstWhere('id', 'cfg_maintenance_mode'));

        // 360°-Feature-Gate fuer B2B Angebotsanfragen (quote_calculator_enabled = 0)
        SystemSetting::updateOrCreate(['key' => 'quote_calculator_enabled'], ['value' => '0']);
        $navDisabled = GlobalProjectSearchService::getNavigationCatalog();
        $this->assertNull(collect($navDisabled)->firstWhere('url', '/admin/quote-requests'));

        SystemSetting::updateOrCreate(['key' => 'quote_calculator_enabled'], ['value' => '1']);
        $navEnabled = GlobalProjectSearchService::getNavigationCatalog();
        $this->assertNotNull(collect($navEnabled)->firstWhere('url', '/admin/quote-requests'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function global_search_queries_all_live_domains_and_respects_root_ticket_isolation(): void
    {
        $admin = Admin::factory()->create();

        $order = OrderOrder::create([
            'order_number' => 'ORD-2026-MAGIC77',
            'email' => 'sarah.magic@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'subtotal_price' => 4990,
            'tax_amount' => 797,
            'shipping_price' => 0,
            'total_price' => 4990,
            'billing_address' => ['first_name' => 'Sarah', 'last_name' => 'Sonnenschein'],
            'shipping_address' => ['first_name' => 'Sarah', 'last_name' => 'Sonnenschein'],
        ]);

        $product = Product::create([
            'name' => 'Seelen Kristall Unikat Magic77',
            'slug' => 'seelen-kristall-unikat-magic77',
            'sku' => 'SF-KRISTALL-M77',
            'type' => 'physical',
            'status' => 'active',
            'price' => 5990,
            'track_quantity' => true,
            'quantity' => 12,
            'continue_selling_when_out_of_stock' => false,
        ]);

        ProductSupplier::create([
            'name' => 'Westpack Magic77 Packaging',
            'website' => 'https://www.westpack.com',
            'email' => 'sales@westpack.com',
        ]);

        $taskList = ManagementTaskList::create([
            'name' => 'Fahrplan Magic77',
            'icon' => 'sparkles',
            'color' => '#C5A059',
        ]);

        $rootTask = ManagementTask::create([
            'task_list_id' => $taskList->id,
            'title' => 'Finanzamt Gifhorn USt-IdNr Magic77 pruefen',
            'status' => 'open',
            'priority' => 'high',
        ]);

        // Unteraufgabe / Checklisten-Schritt darf NICHT als eigenes Haupt-Ticket auftauchen
        ManagementTask::create([
            'task_list_id' => $taskList->id,
            'parent_id' => $rootTask->id,
            'title' => 'Versteckter Unterpunkt Magic77',
            'status' => 'open',
        ]);

        AccountingInvoice::create([
            'invoice_number' => 'RE-2026-MAGIC77',
            'status' => 'paid',
            'invoice_date' => now(),
            'subtotal' => 4193,
            'tax_amount' => 797,
            'total' => 4990,
            'billing_address' => ['first_name' => 'Clara', 'last_name' => 'Stern'],
        ]);

        AccountingSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'xTool Reinigungs-Set Magic77',
            'location' => 'xTool Store',
            'category' => 'Werkstatt',
            'amount' => -39.90,
            'execution_date' => now()->toDateString(),
            'is_business' => true,
            'tax_rate' => 19,
        ]);

        $group = AccountingGroup::create([
            'admin_id' => $admin->id,
            'name' => 'Versicherungen',
            'type' => 'expense',
            'position' => 1,
        ]);

        AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Betriebshaftpflicht Magic77',
            'amount' => -19.50,
            'interval_months' => 1,
            'first_payment_date' => now()->toDateString(),
            'is_business' => true,
            'tax_rate' => 19,
        ]);

        ManagementContact::create([
            'first_name' => 'Steuerkanzlei',
            'last_name' => 'Magic77',
            'relation_type' => 'Steuerberater',
            'email' => 'kanzlei@magic77.de',
        ]);

        AiKnowledgeBase::create([
            'title' => 'Laser Fokus Handbuch Magic77',
            'slug' => 'laser-fokus-handbuch-magic77',
            'content' => 'Wichtige UV-Laser Parameter fuer K9 Kristallglas.',
            'is_published' => true,
        ]);

        $res = $this->actingAs($admin, 'admin')
            ->getJson('/admin/global-search?q=Magic77')
            ->assertOk()
            ->json();

        $groupsByKey = collect($res['groups'])->keyBy('key');

        $this->assertTrue($groupsByKey->has('orders'));
        $this->assertSame('/admin/orders?openOrder=' . urlencode((string) $order->id), $groupsByKey['orders']['items'][0]['url']);

        $this->assertTrue($groupsByKey->has('products'));
        $this->assertSame('/admin/products?editProduct=' . urlencode((string) $product->id), $groupsByKey['products']['items'][0]['url']);

        $this->assertTrue($groupsByKey->has('tasks'));
        $this->assertCount(1, $groupsByKey['tasks']['items']);
        $this->assertSame('/admin/tasks?task=' . urlencode((string) $rootTask->id), $groupsByKey['tasks']['items'][0]['url']);

        $this->assertTrue($groupsByKey->has('accounting'));
        $this->assertGreaterThanOrEqual(4, count($groupsByKey['accounting']['items']));

        $this->assertTrue($groupsByKey->has('knowledge'));
        $this->assertSame('/admin/tickets?view=knowledge', $groupsByKey['knowledge']['items'][0]['url']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deep_link_query_parameters_open_order_and_task_details_directly(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $order = OrderOrder::create([
            'order_number' => 'ORD-2026-DEEP01',
            'email' => 'deep@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'shipping_price' => 0,
            'total_price' => 2990,
            'billing_address' => ['first_name' => 'Deep', 'last_name' => 'Link'],
            'shipping_address' => ['first_name' => 'Deep', 'last_name' => 'Link'],
        ]);

        Livewire::withQueryParams(['openOrder' => $order->id])
            ->test(OrderOverview::class)
            ->assertSet('selectedOrderId', $order->id);

        $deepList = ManagementTaskList::create([
            'name' => 'Deep Liste',
            'icon' => 'sparkles',
            'color' => '#C5A059',
        ]);

        $task = ManagementTask::create([
            'task_list_id' => $deepList->id,
            'title' => 'Deep-Link Ticket Test',
            'status' => 'open',
        ]);

        Livewire::withQueryParams(['task' => $task->id])
            ->test(ManagementTaskComponent::class)
            ->assertSet('selectedTaskId', $task->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function dashboard_and_shop_configuration_render_magic_search_without_hijacking_chrome_ctrl_k(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Datenanalysezentrum')
            ->assertSee('sf-global-project-search-root', false)
            ->assertSee('open-sf-global-search', false);

        $this->actingAs($admin, 'admin')
            ->get('/admin/configuration')
            ->assertOk()
            ->assertSee('Shop-Konfiguration')
            ->assertSee('sfShopConfigShell()', false)
            ->assertSee('data-config-tab="general"', false)
            ->assertSee('data-config-tab="emergency"', false);

        // Verbindlicher Schutz: Strg+K / Cmd+K (Chrome Google-Suche) darf NIEMALS abgefangen werden!
        $modalSource = (string) file_get_contents(resource_path('views/components/backend/global-project-search.blade.php'));
        $this->assertStringNotContainsString('ctrlKey', $modalSource);
        $this->assertStringNotContainsString('metaKey', $modalSource);
        $this->assertStringNotContainsString('keydown.ctrl', $modalSource);
        $this->assertStringNotContainsString('keydown.meta', $modalSource);
        $this->assertStringNotContainsString('keydown.k', $modalSource);
    }
}

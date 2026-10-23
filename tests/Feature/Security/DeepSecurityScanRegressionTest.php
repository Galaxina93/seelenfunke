<?php

namespace Tests\Feature\Security;

use App\Livewire\Shop\Product\ProductCreate;
use App\Models\Admin\Admin;
use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiChatSession;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailAttachment;
use App\Models\Management\Mail\MailMessage;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\Functions\AiOrderFuncs;
use App\Services\Export\FileDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeepSecurityScanRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected Employee $employee;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Admin',
            'email' => 'admin@seelenfunke.test',
            'password' => bcrypt('password'),
        ]);

        $this->employee = Employee::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Max',
            'last_name' => 'Employee',
            'email' => 'employee@seelenfunke.test',
            'password' => bcrypt('password'),
        ]);

        $this->customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Klara',
            'last_name' => 'Kunde',
            'email' => 'customer@seelenfunke.test',
            'password' => bcrypt('password'),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_switches_and_persists_admin_products_layout_modes_synchronously(): void
    {
        Product::create([
            'name' => 'Kristall Herz',
            'slug' => 'kristall-herz',
            'price' => 4900,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin, 'admin');

        $component = Livewire::test(ProductCreate::class)
            ->assertSet('layoutMode', 'large')
            ->assertSeeHtml('wire:key="products-layout-large"')
            ->assertDontSeeHtml('wire:key="products-layout-list"');

        // Switch to 'medium' (4-column grid)
        $component->call('setLayoutMode', 'medium')
            ->assertSet('layoutMode', 'medium')
            ->assertSeeHtml('wire:key="products-layout-medium"')
            ->assertSeeHtml('lg:grid-cols-4')
            ->assertDontSeeHtml('wire:key="products-layout-list"');

        $this->assertEquals('medium', SystemSetting::where('key', 'admin_products_layout_mode')->value('value'));

        // Switch to 'list' (table view) - must render table and NEVER render medium/large grid
        $component->call('setLayoutMode', 'list')
            ->assertSet('layoutMode', 'list')
            ->assertSeeHtml('wire:key="products-layout-list"')
            ->assertDontSeeHtml('wire:key="products-layout-medium"')
            ->assertDontSeeHtml('wire:key="products-layout-large"');

        $this->assertEquals('list', SystemSetting::where('key', 'admin_products_layout_mode')->value('value'));

        // Re-mounting component loads persisted 'list' mode from DB
        session()->forget('products_layout_mode');
        Livewire::test(ProductCreate::class)
            ->assertSet('layoutMode', 'list')
            ->assertSeeHtml('wire:key="products-layout-list"')
            ->assertDontSeeHtml('wire:key="products-layout-medium"');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_employee_privilege_escalation_on_admin_only_ai_tools(): void
    {
        $this->actingAs($this->employee, 'employee');

        $adminTools = [
            'finance_get_summary',
            'mail_ReadEmails',
            'email_send_message',
            'system_read_file',
            'system_write_to_file',
            'system_run_command',
            'master_system_repair',
            'workspace_organize_file',
            'health_create_treatment_plan',
            'brain_create_entry',
            'persona_fetch_url',
            'call_get_history',
        ];

        foreach ($adminTools as $toolName) {
            $res = AIFunctionsRegistry::execute($toolName, []);
            $this->assertTrue($res['error'] ?? false, "Tool {$toolName} must be blocked for Employee.");
            $this->assertStringContainsString('ROLE VIOLATION', $res['message'] ?? '');
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_cross_user_chat_history_idor_for_employees(): void
    {
        $adminSession = AiChatSession::create([
            'user_id' => $this->admin->id,
            'title' => 'Geheimer Admin Chat',
        ]);

        AiChatMemory::create([
            'session_id' => $adminSession->id,
            'role' => 'user',
            'content' => 'STRENG_GEHEIME_ADMIN_NACHRICHT',
        ]);

        // Employee tries to read Admin's chat session history
        $resp = $this->actingAs($this->employee, 'employee')
            ->getJson('/api/ai/history?chat_session_id=' . $adminSession->id);

        $resp->assertStatus(403);
        $this->assertStringNotContainsString('STRENG_GEHEIME_ADMIN_NACHRICHT', $resp->getContent());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_allow_session_fixation_via_ai_execute(): void
    {
        $this->actingAs($this->admin, 'admin');
        $originalSessionId = session()->getId();

        $attackerSessionId = 'attackersessionid1234567890';
        $this->postJson('/api/ai/execute', [
            'function' => 'system_get_current_time',
            'args' => [],
            'session_id' => $attackerSessionId,
        ])->assertStatus(200);

        $this->assertNotEquals($attackerSessionId, session()->getId());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_svg_xss_and_unresolved_external_urls_in_laser_svg_export(): void
    {
        $this->actingAs($this->admin, 'admin');

        $product = Product::create([
            'name' => 'Laser Kristall',
            'slug' => 'laser-kristall',
            'price' => 1000,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-SEC-100',
            'email' => 'customer@seelenfunke.test',
            'status' => 'pending',
            'payment_status' => 'paid',
            'billing_address' => [],
            'shipping_address' => [],
            'subtotal_price' => 1000,
            'tax_amount' => 190,
            'total_price' => 1000,
        ]);

        $item = OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Laser Kristall',
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            'configuration' => [
                'width_mm' => 100,
                'height_mm' => 100,
                'logos' => [
                    [
                        'url' => 'https://evil.example.com/payload.png" onload="alert(1)',
                        'x' => '50" onload="alert(2)',
                        'y' => 50,
                        'size' => 100,
                        'rotation' => 0,
                    ],
                ],
                'texts' => [
                    [
                        'text' => '<script>alert("xss")</script>',
                        'x' => 50,
                        'y' => 50,
                        'size' => 100,
                    ],
                ],
            ],
        ]);

        $service = new FileDownloadService();
        ob_start();
        $response = $service->downloadLaserSvg($item->id, 'front');
        $response->sendContent();
        $svgOutput = ob_get_clean();

        $this->assertStringNotContainsString('evil.example.com', $svgOutput);
        $this->assertStringNotContainsString('onload=', $svgOutput);
        $this->assertStringNotContainsString('<script>', $svgOutput);
        $this->assertStringContainsString('&lt;script&gt;', $svgOutput);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_expose_laser_svg_files_on_public_storage_in_ai_order_funcs(): void
    {
        Storage::fake('public');
        Storage::fake('workspace');

        $this->actingAs($this->admin, 'admin');

        $product = Product::create([
            'name' => 'Laser Anhänger',
            'slug' => 'laser-anhaenger',
            'price' => 1000,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-SEC-200',
            'email' => 'customer@seelenfunke.test',
            'status' => 'pending',
            'payment_status' => 'paid',
            'billing_address' => [],
            'shipping_address' => [],
            'subtotal_price' => 1000,
            'tax_amount' => 190,
            'total_price' => 1000,
        ]);

        $item = OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Laser Anhänger',
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            'configuration' => ['texts' => [['text' => 'Gravur', 'x' => 50, 'y' => 50]]],
        ]);

        $resDownload = AiOrderFuncs::executeOrderGenerateXtool([
            'order_item_id' => $item->id,
            'action' => 'download',
        ]);

        $this->assertEquals('success', $resDownload['status']);
        $this->assertStringContainsString("/admin/order-item/{$item->id}/download-laser-svg/front", $resDownload['download_url']);
        $this->assertEmpty(Storage::disk('public')->allFiles());

        $resFm = AiOrderFuncs::executeOrderGenerateXtool([
            'order_item_id' => $item->id,
            'action' => 'filemanager',
        ]);

        $this->assertEquals('success', $resFm['status']);
        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->assertNotEmpty(Storage::disk('workspace')->allFiles());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_enforces_admin_authentication_on_mail_attachment_and_tax_export_downloads(): void
    {
        $service = new FileDownloadService();

        try {
            $service->downloadMailAttachment((string) Str::uuid());
            $this->fail('Expected 403 HttpException for unauthenticated downloadMailAttachment.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        try {
            $service->downloadTaxExport('export_2026.zip');
            $this->fail('Expected 403 HttpException for unauthenticated downloadTaxExport.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_svg_email_attachment_uploads_and_php_receipt_uploads(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->admin, ['*']);

        $svgFile = UploadedFile::fake()->create('malicious.svg', 10, 'image/svg+xml');
        $this->postJson('/api/funki/emails/attachments', [
            'file' => $svgFile,
        ])->assertStatus(422);

        $phpFile = UploadedFile::fake()->create('shell.php', 10, 'application/x-php');
        $this->postJson('/api/funki/financials/scan-receipt', [
            'file' => $phpFile,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_path_traversal_in_digital_product_download(): void
    {
        $product = Product::create([
            'name' => 'Digitales E-Book',
            'slug' => 'digitales-ebook',
            'price' => 990,
            'type' => 'digital',
            'status' => 'active',
            'digital_download_path' => '../../.env',
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-DIGI-1',
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => [],
            'shipping_address' => [],
            'subtotal_price' => 990,
            'tax_amount' => 158,
            'total_price' => 990,
        ]);

        $item = OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 990,
            'total_price' => 990,
        ]);

        $this->actingAs($this->customer, 'customer')
            ->get("/digital-product/{$item->id}/download")
            ->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_non_admins_from_mounting_system_user_management(): void
    {
        $this->actingAs($this->employee, 'employee');
        Livewire::test(\App\Livewire\Shop\System\SystemUserManagement::class)
            ->assertForbidden();

        $this->actingAs($this->customer, 'customer');
        Livewire::test(\App\Livewire\Shop\System\SystemUserManagement::class)
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_employees_from_mutating_ai_roles_or_agent_skills(): void
    {
        $role = \App\Models\Ai\AiRole::create([
            'name' => 'Support Rolle',
            'description' => 'Basis Support',
        ]);

        $tool = \App\Models\Ai\AiTool::firstOrCreate(
            ['identifier' => 'finance_get_summary'],
            ['name' => 'Finanzen', 'description' => 'Admin Finanzen']
        );

        $agent = \App\Models\Ai\AiAgent::create([
            'name' => 'SupportBot',
            'ai_role_id' => $role->id,
            'is_active' => true,
            'color' => 'cyan-500',
            'icon' => 'sparkles',
        ]);

        $this->actingAs($this->employee, 'employee');

        Livewire::test(\App\Livewire\Shop\Ai\AiRoleManager::class)
            ->set('name', 'Manipulierte Admin Rolle')
            ->set('selectedTools', [(string) $tool->id])
            ->call('save')
            ->assertForbidden();

        Livewire::test(\App\Livewire\Shop\Ai\AiAgentEditor::class, ['id' => $agent->id])
            ->call('toggleSkill', $tool->id)
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_employee_guard_from_all_restricted_system_and_agent_tools(): void
    {
        $this->actingAs($this->employee, 'employee');

        $this->assertTrue(\App\Services\AI\AiAuthHelper::isEmployee());
        $this->assertTrue(\App\Services\AI\AiAuthHelper::check());
        $this->assertEquals($this->employee->id, \App\Services\AI\AiAuthHelper::getUserId());

        $newlyBlockedTools = [
            'system_read_code',
            'system_search_code',
            'system_search_files',
            'system_list_directory',
            'system_export_system_report',
            'system_manage_logs',
            'system_clear_security_threats',
            'system_analyze_security_threats',
            'system_dispatch_background_task',
            'system_assign_tool_to_role',
            'agent_update_system_config',
            'agent_update_runtime_config',
            'move_agent_to_department',
            'holiday_create_trip',
        ];

        foreach ($newlyBlockedTools as $toolName) {
            $res = AIFunctionsRegistry::execute($toolName, []);
            $this->assertTrue($res['error'] ?? false, "Tool {$toolName} must be blocked for Employee guard.");
            $this->assertStringContainsString('ROLE VIOLATION', $res['message'] ?? '');
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_ai_system_funcs_against_sensitive_file_access_and_command_injection(): void
    {
        // 1. ReadCode blocks sensitive files & directories
        $readLock = \App\Services\AI\Functions\AiSystemFuncs::executeReadCode(['file_path' => 'composer.lock']);
        $this->assertEquals('error', $readLock['status']);

        $readTraversal = \App\Services\AI\Functions\AiSystemFuncs::executeReadCode(['file_path' => '../.env']);
        $this->assertEquals('error', $readTraversal['status']);

        // 2. WriteToFile blocks writing to public/ and config/ and .sh scripts
        $writePublic = \App\Services\AI\Functions\AiSystemFuncs::executeWriteToFile([
            'file_path' => 'public/backdoor.php',
            'new_content' => '<?php echo 1;',
        ]);
        $this->assertEquals('error', $writePublic['status']);

        $writeConfig = \App\Services\AI\Functions\AiSystemFuncs::executeWriteToFile([
            'file_path' => 'config/evil.php',
            'new_content' => '<?php return [];',
        ]);
        $this->assertEquals('error', $writeConfig['status']);

        // 3. RunCommand blocks command chaining / metacharacters and non-whitelisted binaries
        $chainedCmd = \App\Services\AI\Functions\AiSystemFuncs::executeRunCommand([
            'command' => 'echo hello; id',
        ]);
        $this->assertEquals('error', $chainedCmd['status']);

        $unwhitelistedCmd = \App\Services\AI\Functions\AiSystemFuncs::executeRunCommand([
            'command' => 'curl http://example.com',
        ]);
        $this->assertEquals('error', $unwhitelistedCmd['status']);

        // 4. AssignToolToRole blocks self-assigning finance_ / mail_ / master_ / system_ tools
        $role = \App\Models\Ai\AiRole::create(['name' => 'SelfAssignRole', 'description' => 'Test']);
        $agent = \App\Models\Ai\AiAgent::create([
            'name' => 'SelfAssignAgent',
            'ai_role_id' => $role->id,
            'is_active' => true,
        ]);
        $finSave = \App\Services\AI\Functions\AiSystemFuncs::executeAssignToolToRole([
            'tool_identifier' => 'finance_get_summary',
        ], $agent);
        $this->assertIsString($finSave);
        $this->assertStringContainsString('Sicherheitsrichtlinie', $finSave);

        // 5. executeUpdateFunkiraConfiguration blocks arbitrary non-whitelisted setting keys
        $cfgRes = \App\Services\AI\Functions\AiSystemFuncs::executeUpdateFunkiraConfiguration([
            'arbitrary_evil_setting' => 'pwned',
        ]);
        $this->assertEquals('success', $cfgRes['status']);
        $this->assertStringContainsString('Sicherheits-Sperre', $cfgRes['result']['failed'] ?? '');
        $this->assertNull(\App\Models\Ai\AiAgentSetting::where('key', 'arbitrary_evil_setting')->first());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sanitizes_archive_name_path_traversal_in_workspace_archive(): void
    {
        Storage::fake('workspace');
        Storage::disk('workspace')->put('agenten/workspace/testfolder/notiz.txt', 'Inhalt');

        $res = \App\Services\AI\Functions\AiAgentsFuncs::executeWorkspaceArchiveFolderContent([
            'folder_path' => 'testfolder',
            'archive_name' => '../../../public/exploit.zip',
        ]);

        $this->assertEquals('success', $res['status']);
        $this->assertStringContainsString('exploit.zip', $res['message']);
        $this->assertTrue(Storage::disk('workspace')->exists('agenten/workspace/testfolder/exploit.zip'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_enforces_authorization_on_accounting_order_marketing_and_ai_livewire_components(): void
    {
        $this->actingAs($this->customer, 'customer');

        $protectedComponents = [
            \App\Livewire\Shop\Accounting\AccountingInvoice::class,
            \App\Livewire\Shop\Accounting\AccountingLiquidity::class,
            \App\Livewire\Shop\Accounting\AccountingTax::class,
            \App\Livewire\Shop\Accounting\AccountingBank::class,
            \App\Livewire\Shop\Accounting\AccountingQuickEntry::class,
            \App\Livewire\Shop\Order\OrderOverview::class,
            \App\Livewire\Shop\Marketing\MarketingNewsletter::class,
            \App\Livewire\Shop\Ai\AiWorkspace::class,
            \App\Livewire\Shop\Ai\AiWidget::class,
        ];

        foreach ($protectedComponents as $componentClass) {
            Livewire::test($componentClass)->assertForbidden();
        }

        // Employee is also blocked from Admin-only financial/tax/liquidity/bank/workspace components
        $this->actingAs($this->employee, 'employee');
        foreach ([
            \App\Livewire\Shop\Accounting\AccountingLiquidity::class,
            \App\Livewire\Shop\Accounting\AccountingTax::class,
            \App\Livewire\Shop\Accounting\AccountingBank::class,
            \App\Livewire\Shop\Accounting\AccountingQuickEntry::class,
            \App\Livewire\Shop\Ai\AiWorkspace::class,
        ] as $adminOnlyComponent) {
            Livewire::test($adminOnlyComponent)->assertForbidden();
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_sql_injection_sort_fields_and_cert_path_traversal_and_xxe(): void
    {
        $this->actingAs($this->admin, 'admin');

        // 1. AccountingInvoice sortField whitelist
        Livewire::test(\App\Livewire\Shop\Accounting\AccountingInvoice::class)
            ->call('sortBy', 'id; DROP TABLE admins; --')
            ->assertSet('sortField', 'invoice_date');

        // 2. OrderOverview sortField whitelist
        Livewire::test(\App\Livewire\Shop\Order\OrderOverview::class)
            ->call('sortBy', 'id; DROP TABLE orders; --')
            ->assertSet('sortField', 'default_workflow');

        // 3. AccountingTax certificate path traversal sanitization
        Livewire::test(\App\Livewire\Shop\Accounting\AccountingTax::class)
            ->set('selectedCertName', '../../../.env')
            ->assertSet('selectedCertName', '')
            ->set('selectedCertName', '../../outside/test-soft-pse.pfx')
            ->assertSet('selectedCertName', 'test-soft-pse.pfx');

        // 4. AccountingQuickEntry XXE / DOCTYPE blocking
        $component = new \App\Livewire\Shop\Accounting\AccountingQuickEntry();
        $ref = new \ReflectionMethod($component, 'parseInvoiceXml');
        $ref->setAccessible(true);
        $xxePayload = '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><CrossIndustryInvoice><ExchangedDocument><ID>&xxe;</ID></ExchangedDocument></CrossIndustryInvoice>';
        $this->assertFalse($ref->invoke($component, $xxePayload));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_employee_health_mutations_source_attachments_and_executable_chat_uploads(): void
    {
        Storage::fake('public');

        // 1. Employee cannot attach source code files or upload executable files in AiWidget
        $this->actingAs($this->employee, 'employee');

        $phpUpload = UploadedFile::fake()->create('backdoor.php', 5, 'application/x-php');
        $svgUpload = UploadedFile::fake()->create('xss.svg', 5, 'image/svg+xml');

        Livewire::test(\App\Livewire\Shop\Ai\AiWidget::class)
            ->call('addAttachment', 'app/Models/Admin/Admin.php')
            ->assertSet('attachments', [])
            ->set('input', 'Test Nachricht')
            ->set('uploadedFiles', [$phpUpload, $svgUpload])
            ->call('sendMessage');

        $this->assertEmpty(Storage::disk('public')->allFiles('agenten/workspace/chat-medien'));

        // 2. ManagesHealthData blocks non-admin users from mutating or reading medications
        $healthDummy = new class {
            use \App\Livewire\Shop\Ai\Traits\ManagesHealthData;
        };
        $this->assertTrue($healthDummy->activeMedications()->isEmpty());
        try {
            $healthDummy->saveMedication();
            $this->fail('Expected 403 HttpException for non-admin saveMedication.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        // 3. AiHealthFuncs blocks non-admin users across doctor & document tools
        $this->assertTrue(\App\Services\AI\Functions\AiHealthFuncs::executeGetDoctors([])['error'] ?? false);
        $this->assertTrue(\App\Services\AI\Functions\AiHealthFuncs::executeCreateDoctor([
            'first_name' => 'Dr.',
            'last_name' => 'Unbefugt',
            'relation_type' => 'Hausarzt',
        ])['error'] ?? false);
        $this->assertTrue(\App\Services\AI\Functions\AiHealthFuncs::executeCreateHealthTodo([
            'title' => 'Unbefugte Aufgabe',
        ])['error'] ?? false);
        $this->assertTrue(\App\Services\AI\Functions\AiHealthFuncs::executeReadDocument([
            'filename' => '../../.env',
        ])['error'] ?? false);
    }
}

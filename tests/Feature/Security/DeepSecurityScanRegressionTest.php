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

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_system_backups_neural_analysis_and_shipping_against_traversal_and_injection(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin, 'admin');

        // 1. SystemBackups blocks deleting or downloading .zip files outside the configured backup folder
        config(['backup.backup.name' => 'Laravel', 'backup.backup.destination.disks' => ['local']]);
        Storage::disk('local')->put('other-folder/secret-archive.zip', 'secret');
        Storage::disk('local')->put('Laravel/2026-10-23-backup.zip', 'valid-backup');

        Livewire::test(\App\Livewire\Shop\System\SystemBackups::class)
            ->call('deleteBackup', 'other-folder/secret-archive.zip');

        $this->assertTrue(Storage::disk('local')->exists('other-folder/secret-archive.zip'));

        Livewire::test(\App\Livewire\Shop\System\SystemBackups::class)
            ->call('deleteBackup', 'Laravel/2026-10-23-backup.zip');

        $this->assertFalse(Storage::disk('local')->exists('Laravel/2026-10-23-backup.zip'));

        // 2. SystemNeuralAnalysisIndex blocks .env and path traversal in generateMarkdownForFile
        $this->assertNull(\App\Livewire\Backend\System\SystemNeuralAnalysisIndex::generateMarkdownForFile('.env'));
        $this->assertNull(\App\Livewire\Backend\System\SystemNeuralAnalysisIndex::generateMarkdownForFile('../../.env'));
        $this->assertNull(\App\Livewire\Backend\System\SystemNeuralAnalysisIndex::generateMarkdownForFile('/etc/passwd'));

        // 3. SystemShipping blocks non-whitelisted country codes & CSS injection payloads
        $zone = \App\Models\Logistics\LogisticsShippingZone::create([
            'name' => 'Testzone',
            'is_active' => true,
        ]);

        Livewire::test(\App\Livewire\Shop\System\SystemShipping::class)
            ->call('editZone', $zone->id)
            ->set('selectedCountryToAdd', "']")
            ->call('addCountry')
            ->assertHasErrors(['selectedCountryToAdd']);

        $this->assertDatabaseMissing('logistics_shipping_zone_countries', [
            'country_code' => "']",
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_xss_and_smtp_ssrf_in_management_emails_and_sanitizes_contract_extensions(): void
    {
        $this->actingAs($this->admin, 'admin');

        $account = \App\Models\Management\Mail\MailAccount::forceCreate([
            'name' => 'Internal Test Account',
            'email' => 'office@example.com',
            'password' => encrypt('secret'),
            'imap_host' => 'imap.example.com',
            'smtp_host' => '127.0.0.1',
            'smtp_port' => '465',
            'smtp_encryption' => 'ssl',
            'status' => 'connected',
            'is_default' => true,
        ]);

        $msg = \App\Models\Management\Mail\MailMessage::create([
            'mail_account_id' => $account->id,
            'message_id' => 'xss-msg-1',
            'folder' => 'INBOX',
            'subject' => 'Anfrage',
            'from_name' => '<img src=x onerror=alert(1)>',
            'from_email' => 'attacker@example.com',
            'to_email' => 'office@example.com',
            'body_plain' => 'Hallo Welt',
            'received_at' => now(),
        ]);

        $comp = Livewire::test(\App\Livewire\Shop\Management\ManagementEMails::class)
            ->call('openCompose', 'reply', $msg->id);

        $composeBody = (string) $comp->get('composeBody');
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $composeBody);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $composeBody);

        // Sending via internal/loopback SMTP host (127.0.0.1) is blocked by SSRF protection
        $comp->set('composeTo', 'customer@example.com')
            ->set('composeSubject', 'Test')
            ->call('sendMail')
            ->assertSet('showComposeModal', true);

        // ContractDocumentService blocks executable extensions and defaults them to .pdf
        $contractService = new \App\Services\Accounting\ContractDocumentService();
        $fileName = $contractService->generateLogicalFileName('2026-10-23', 'ACME', 'Wartungsvertrag', 'php');
        $this->assertStringEndsWith('.pdf', $fileName);
        $this->assertStringNotContainsString('.php', $fileName);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_strips_path_traversal_from_cart_and_configurator_configurations(): void
    {
        $product = Product::create([
            'name' => 'Konfigurierbares Glas',
            'slug' => 'konfigurierbares-glas',
            'price' => 2990,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $cartService = app(\App\Services\CartService::class);
        $sanitized = $cartService->sanitizeConfiguration($product, [
            'files' => ['bestellungen/cart-uploads/valid.png', '../../.env', '/etc/passwd'],
            'logos' => [
                ['value' => 'vectors/heart.svg', 'x' => 50, 'y' => 50],
                ['value' => '../../../.env', 'x' => 50, 'y' => 50],
            ],
            'logo_path' => '../../.env',
            'snapshot_path' => '../secret.png',
        ]);

        $this->assertEquals(['bestellungen/cart-uploads/valid.png'], $sanitized['files']);
        $this->assertCount(1, $sanitized['logos']);
        $this->assertEquals('vectors/heart.svg', $sanitized['logos'][0]['value']);
        $this->assertArrayNotHasKey('logo_path', $sanitized);
        $this->assertArrayNotHasKey('snapshot_path', $sanitized);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_unauthenticated_password_reset_token_injection_and_open_mail_relay_via_traits(): void
    {
        $resetRef = new \ReflectionClass(\App\Traits\handlePasswordResetTrait::class);
        $this->assertTrue($resetRef->getMethod('updatePasswordResetToken')->isProtected());
        $this->assertTrue($resetRef->getMethod('generateToken')->isProtected());
        $this->assertTrue($resetRef->getMethod('resetPassword')->isProtected());

        $mailRef = new \ReflectionClass(\App\Traits\handleMailsTrait::class);
        $this->assertTrue($mailRef->getMethod('sendMail')->isProtected());

        $this->expectException(\InvalidArgumentException::class);
        $notification = new \App\Notifications\globalNotification([
            'subject' => "Test\r\nBcc: victim@example.com",
            'viewTemplate' => 'livewire.auth.auth-login',
        ]);
        $notification->toMail(new \stdClass());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_broadcasts_internal_workspace_telephony_and_crm_events_only_on_private_channels(): void
    {
        $speechEvent = new \App\Events\AiWidgetSpeechEvent('agent-1', 'Geheimer Text');
        $speechChannels = $speechEvent->broadcastOn();
        $this->assertInstanceOf(\Illuminate\Broadcasting\PrivateChannel::class, $speechChannels[0]);
        $this->assertEquals('private-workspace', $speechChannels[0]->name);

        $frontendEvent = new \App\Events\AiFrontendEvent('test-event', []);
        $frontendChannels = $frontendEvent->broadcastOn();
        $this->assertInstanceOf(\Illuminate\Broadcasting\PrivateChannel::class, $frontendChannels[0]);
        $this->assertEquals('private-workspace', $frontendChannels[0]->name);

        $telephonyEvent = new \App\Events\SupportTelephonyUpdated();
        $telephonyChannel = $telephonyEvent->broadcastOn();
        $this->assertInstanceOf(\Illuminate\Broadcasting\PrivateChannel::class, $telephonyChannel);
        $this->assertEquals('private-telephony', $telephonyChannel->name);

        $mailEvent = new \App\Events\Management\MailReceivedEvent();
        $mailChannels = $mailEvent->broadcastOn();
        $this->assertInstanceOf(\Illuminate\Broadcasting\PrivateChannel::class, $mailChannels[0]);
        $this->assertEquals('private-crm-inbox', $mailChannels[0]->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_locks_qr_code_svg_and_blocks_javascript_urls_in_profile_dropdown(): void
    {
        $ref = new \ReflectionProperty(\App\Livewire\Auth\AuthProfileDropdown::class, 'qrCodeSvg');
        $lockedAttrs = $ref->getAttributes(\Livewire\Attributes\Locked::class);
        $this->assertNotEmpty($lockedAttrs, 'AuthProfileDropdown::$qrCodeSvg must be #[Locked]');

        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        Livewire::test(\App\Livewire\Auth\AuthProfileDropdown::class)
            ->set('firstName', 'Max')
            ->set('lastName', 'Mustermann')
            ->set('email', $customer->email)
            ->set('url', 'javascript:alert(document.cookie)')
            ->call('saveProfile')
            ->assertHasErrors(['url']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_locks_frontend_support_chat_state_and_enforces_customer_isolation_in_ai_support_funcs(): void
    {
        foreach (['messages', 'agentName', 'agentImage', 'guestLimitReached', 'isResolved', 'ratingSubmitted'] as $prop) {
            $ref = new \ReflectionProperty(\App\Livewire\Frontend\Support\AiFrontendSupportChat::class, $prop);
            $this->assertNotEmpty(
                $ref->getAttributes(\Livewire\Attributes\Locked::class),
                "AiFrontendSupportChat::\${$prop} must be #[Locked]"
            );
        }

        $admin = Admin::factory()->create();
        $victimCustomer = Customer::factory()->create();
        $order = OrderOrder::create([
            'order_number' => 'ORD-ISOLATION-999',
            'customer_id' => $victimCustomer->id,
            'email' => $victimCustomer->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'billing_address' => ['first_name' => 'Victim', 'last_name' => 'User'],
            'shipping_address' => [],
            'subtotal_price' => 1000,
            'tax_amount' => 190,
            'shipping_price' => 0,
            'total_price' => 1000,
        ]);

        // Even if an admin guard is active in the browser, public support chat context (__chat_id present)
        // must never bypass customer_id scoping when no customer is logged in.
        $this->actingAs($admin, 'admin');
        $res = \App\Services\AI\Functions\AiSupportFuncs::executeGetOrderStatus([
            'identifier' => $order->order_number,
            '__chat_id' => 'public-support-chat-123',
        ]);
        $this->assertEquals('error', $res['status']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_cross_site_and_cross_origin_csrf_on_cookie_authenticated_ai_api_routes(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $crossSite = $this->withHeaders([
            'Sec-Fetch-Site' => 'cross-site',
        ])->postJson('/api/ai/execute', [
            'function' => 'system_health',
        ]);
        $crossSite->assertStatus(403);

        $crossOrigin = $this->withHeaders([
            'Origin' => 'https://evil-attacker.example.com',
        ])->postJson('/api/ai/execute', [
            'function' => 'system_health',
        ]);
        $crossOrigin->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_wildcard_chat_ids_and_short_tokens_in_telegram_agent_webhook(): void
    {
        // Short token (< 20 chars) must be rejected immediately
        $shortTokenResp = $this->postJson('/api/telegram/webhook/short-token', [
            'message' => ['chat' => ['id' => 123456], 'text' => 'Hallo'],
        ]);
        $shortTokenResp->assertStatus(404);

        // Agent with wildcard '*' in telegram_allowed_chat_ids must NOT allow arbitrary chat IDs
        $validToken = '1234567890:AAH_test_valid_length_telegram_token_xyz';
        \App\Models\Ai\AiAgent::create([
            'name' => 'TelegramSecTestAgent',
            'model' => 'gemini-2.5-flash',
            'is_active' => true,
            'telegram_bot_token' => $validToken,
            'telegram_allowed_chat_ids' => ['*'],
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $wildcardResp = $this->postJson('/api/telegram/webhook/' . $validToken, [
            'message' => ['chat' => ['id' => 999888777], 'text' => 'Secret Command'],
        ]);
        $wildcardResp->assertStatus(200)
            ->assertJson(['status' => 'unauthorized']);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains((string) ($request['text'] ?? ''), 'nicht berechtigt');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_ssrf_smtp_host_and_folder_traversal_in_funki_emails_api(): void
    {
        $admin = Admin::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($admin, ['*']);

        $unsafeAccount = \App\Models\Management\Mail\MailAccount::create([
            'name' => 'Loopback SMTP',
            'email' => 'loopback@example.com',
            'password' => 'secret',
            'imap_host' => '127.0.0.1',
            'imap_port' => 993,
            'smtp_host' => '127.0.0.1',
            'smtp_port' => 587,
            'status' => 'connected',
            'is_default' => true,
        ]);

        $sendResp = $this->postJson('/api/funki/emails/send', [
            'account_id' => $unsafeAccount->id,
            'to' => 'customer@example.com',
            'subject' => 'Test',
            'body' => 'Hello',
        ]);
        $sendResp->assertStatus(422);

        $folderResp = $this->postJson('/api/funki/emails/folders', [
            'account_id' => $unsafeAccount->id,
            'name' => '../etc/passwd',
        ]);
        $folderResp->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rotates_remember_token_on_forced_customer_password_change(): void
    {
        $customer = Customer::factory()->create([
            'needs_password_change' => true,
            'remember_token' => 'old-static-remember-token-12345',
        ]);

        $this->actingAs($customer, 'customer');

        Livewire::test(\App\Livewire\Customer\CustomerForcePasswordChange::class)
            ->set('password', 'NewSecurePass99!')
            ->set('passwordConfirm', 'NewSecurePass99!')
            ->call('submit')
            ->assertHasNoErrors();

        $customer->refresh();
        $this->assertFalse((bool) $customer->needs_password_change);
        $this->assertNotEquals('old-static-remember-token-12345', $customer->remember_token);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_enforces_boot_auth_guards_on_master_and_ai_admin_livewire_components(): void
    {
        $classes = [
            \App\Livewire\Shop\Master\MasterAnalytics::class,
            \App\Livewire\Shop\Master\MasterShopCapacity::class,
            \App\Livewire\Shop\Master\MasterStorageCapacity::class,
            \App\Livewire\Shop\Ai\PartialsAiWorkspace\TabCronjobs::class,
            \App\Livewire\Shop\Ai\AiAgentEditor::class,
            \App\Livewire\Shop\Ai\AiRoleManager::class,
            \App\Livewire\Shop\Ai\AiKnowledgeBase::class,
            \App\Livewire\Shop\Ai\AiKnowledgeBaseCategories::class,
            \App\Livewire\Shop\Ai\AiKnowledgeBaseTags::class,
            \App\Livewire\Shop\Ai\AiCompanyStructure::class,
            \App\Livewire\Shop\Ai\AiAnalytics::class,
            \App\Livewire\Shop\Ai\AiMethod::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(
                method_exists($class, 'boot'),
                "{$class} must implement boot() to guard all subsequent Livewire requests"
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_locks_db_products_in_product_calculator_and_enforces_product_scoped_templates(): void
    {
        $ref = new \ReflectionProperty(\App\Livewire\Shop\Product\ProductCalculator\ProductCalculator::class, 'dbProducts');
        $this->assertNotEmpty(
            $ref->getAttributes(\Livewire\Attributes\Locked::class),
            'ProductCalculator::$dbProducts must be #[Locked] to prevent client-side price manipulation'
        );

        $productA = \App\Models\Product\Product::create([
            'name' => 'Produkt A',
            'slug' => 'produkt-a-sec-test',
            'price' => 2500,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
        ]);
        $productB = \App\Models\Product\Product::create([
            'name' => 'Produkt B',
            'slug' => 'produkt-b-sec-test',
            'price' => 4500,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
        ]);

        $foreignTemplate = \App\Models\Product\ProductTemplate::create([
            'product_id' => $productB->id,
            'name' => 'Fremde Vorlage',
            'configuration' => ['text' => 'Injected Foreign Config'],
            'is_active' => true,
        ]);

        Livewire::test(\App\Livewire\Shop\Product\ProductShow::class, ['product' => $productA])
            ->call('selectTemplate', $foreignTemplate->id)
            ->assertSet('currentConfig', []);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_enforces_boot_auth_guards_on_product_and_marketing_admin_livewire_components(): void
    {
        $classes = [
            \App\Livewire\Shop\Product\ProductAnalytics::class,
            \App\Livewire\Shop\Product\ProductConfiguratorSettings::class,
            \App\Livewire\Shop\Product\ProductPackagingConfigurator::class,
            \App\Livewire\Shop\Product\ProductTierPricing::class,
            \App\Livewire\Shop\Product\ProductVariants::class,
            \App\Livewire\Shop\Marketing\MarketingHerzensprojekte::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(
                method_exists($class, 'boot'),
                "{$class} must implement boot() to guard all subsequent Livewire requests"
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_path_traversal_in_glb_optimizer_and_validates_calendar_api(): void
    {
        $optimizer = app(\App\Services\Storage\GlbOptimizerService::class);
        $resTraversal = $optimizer->optimizeGlb('../../etc/passwd');
        $this->assertFalse($resTraversal['success']);

        $resWrongExt = $optimizer->optimizeGlb(storage_path('app/test.txt'));
        $this->assertFalse($resWrongExt['success']);

        $admin = Admin::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($admin, ['*']);

        $calResp = $this->postJson('/api/funki/calendar/events', [
            'title' => 'Test Event',
            'start' => now()->toIso8601String(),
            'recurrence' => 'infinite_loop_attack',
            'priority' => 'invalid_priority',
        ]);
        $calResp->assertStatus(422)
            ->assertJsonValidationErrors(['recurrence', 'priority']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_locks_sensitive_properties_in_checkout_success_quote_acceptance_emergency_and_landing_page(): void
    {
        $lockedChecks = [
            [\App\Livewire\Shop\Order\OrderCheckout\OrderCheckoutSuccess::class, 'finalOrderNumber'],
            [\App\Livewire\Shop\Order\OrderCheckout\OrderCheckoutSuccess::class, 'email'],
            [\App\Livewire\Shop\Order\OrderCheckout\OrderCheckoutSuccess::class, 'referralCode'],
            [\App\Livewire\Shop\Order\OrderQuoteAcceptance::class, 'quote'],
            [\App\Livewire\Shop\Order\OrderQuoteAcceptance::class, 'editingItem'],
            [\App\Livewire\Frontend\Emergency\EmergencyProtocol::class, 'todoStates'],
            [\App\Livewire\Frontend\Emergency\EmergencyProtocol::class, 'financeStates'],
            [\App\Livewire\Landing\LandingPageView::class, 'landingPage'],
        ];

        foreach ($lockedChecks as [$class, $property]) {
            $ref = new \ReflectionProperty($class, $property);
            $this->assertNotEmpty(
                $ref->getAttributes(\Livewire\Attributes\Locked::class),
                "{$class}::\${$property} must be #[Locked]"
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_enforces_gobd_immutability_in_accounting_invoice_and_boot_guard_in_system_shop_config(): void
    {
        $this->assertTrue(
            method_exists(\App\Livewire\Shop\System\SystemShopConfig::class, 'boot'),
            'SystemShopConfig must implement boot() to guard all subsequent Livewire requests'
        );

        $admin = Admin::factory()->create();
        $paidInvoice = \App\Models\Accounting\AccountingInvoice::create([
            'invoice_number' => 'RE-GOBD-TEST-001',
            'type' => 'invoice',
            'status' => 'paid',
            'invoice_date' => now(),
            'subtotal' => 1000,
            'tax_amount' => 190,
            'total' => 1190,
            'billing_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'email' => 'max@example.com',
            ],
            'custom_items' => [],
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Accounting\AccountingInvoice::class)
            ->call('editDraft', $paidInvoice->id)
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_infinite_loan_loop_in_accounting_liquidity_and_enforces_cart_item_ownership_in_cart_edit(): void
    {
        $admin = Admin::factory()->create();

        // Attempting configRepaymentMonths = 1 with negative cashflow previously caused an infinite while-loop
        Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Accounting\AccountingLiquidity::class)
            ->set('configLoadDemoData', false)
            ->set('configRepaymentMonths', 1)
            ->set('configInterestRate', 999)
            ->call('updateValue', (int) date('Y'), (int) date('n'), 'out', 'other_out', '5000')
            ->assertSet('configRepaymentMonths', 2)
            ->assertSet('configInterestRate', 50.0);

        // Cart::edit() must not allow opening a foreign CartItem ID
        $product = \App\Models\Product\Product::create([
            'name' => 'Cart Ownership Produkt',
            'slug' => 'cart-ownership-produkt',
            'price' => 1990,
            'status' => 'active',
            'type' => 'physical',
        ]);
        $foreignCart = \App\Models\Cart\Cart::create(['session_id' => 'foreign-session-xyz']);
        $foreignItem = \App\Models\Cart\CartItem::create([
            'cart_id' => $foreignCart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1990,
            'total_price' => 1990,
        ]);

        Livewire::test(\App\Livewire\Shop\Cart\Cart::class)
            ->call('edit', $foreignItem->id)
            ->assertSet('editingItemId', null);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_restricts_ceo_financial_prompt_context_and_private_brain_categories_from_employees(): void
    {
        $employee = Employee::create([
            'first_name' => 'Test',
            'last_name' => 'Mitarbeiter',
            'email' => 'mitarbeiter-r9@example.com',
            'password' => bcrypt('password123'),
        ]);
        $admin = Admin::factory()->create();

        $privateCat = \App\Models\Ai\AiKnowledgeBaseCategory::firstOrCreate(
            ['slug' => 'gesundheit-sozialrecht-bkk-firmus'],
            ['name' => 'Gesundheit & Sozialrecht (BKK firmus)']
        );
        $publicCat = \App\Models\Ai\AiKnowledgeBaseCategory::firstOrCreate(
            ['slug' => 'allgemeines-shop-wissen'],
            ['name' => 'Allgemeines Shop-Wissen']
        );

        $privateArticle = \App\Models\Ai\AiKnowledgeBase::create([
            'title' => 'Geheime BKK Akte',
            'slug' => 'geheime-bkk-akte',
            'ai_knowledge_base_category_id' => $privateCat->id,
            'content' => '<p>Vertrauliche Diagnose</p>',
            'is_published' => true,
        ]);

        // Employee cannot select, edit, or delete private CEO category articles in AiKnowledgeBase
        Livewire::actingAs($employee, 'employee')
            ->test(\App\Livewire\Shop\Ai\AiKnowledgeBase::class)
            ->call('selectArticle', $privateArticle->id)
            ->assertForbidden();

        Livewire::actingAs($employee, 'employee')
            ->test(\App\Livewire\Shop\Ai\AiKnowledgeBase::class)
            ->call('deleteArticle', $privateArticle->id)
            ->assertForbidden();

        // Employee cannot read private CEO category via AiSystemFuncs::executeReadKnowledge
        \Illuminate\Support\Facades\Auth::guard('employee')->login($employee);
        $readRes = \App\Services\AI\Functions\AiSystemFuncs::executeReadKnowledge(['topic' => 'geheime-bkk-akte']);
        $this->assertSame('error', $readRes['status']);

        // Employee cannot search or delete CEO Brain via AiBrainFuncs
        $brainSearchRes = \App\Services\AI\Functions\AiBrainFuncs::executeSearchBrain(['query' => 'Diagnose']);
        $this->assertSame('error', $brainSearchRes['status']);

        $brainDeleteRes = \App\Services\AI\Functions\AiBrainFuncs::executeDeleteBrainEntry(['search_query' => 'Geheime']);
        $this->assertSame('error', $brainDeleteRes['status']);
        \Illuminate\Support\Facades\Auth::guard('employee')->logout();

        // Admin CAN access private CEO category via executeReadKnowledge and AiKnowledgeBase::sanitizeKnowledgeHtml strips XSS
        \Illuminate\Support\Facades\Auth::guard('admin')->login($admin);
        $adminReadRes = \App\Services\AI\Functions\AiSystemFuncs::executeReadKnowledge(['topic' => 'geheime-bkk-akte']);
        $this->assertSame('success', $adminReadRes['status']);
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();

        $sanitizedKb = \App\Livewire\Shop\Ai\AiKnowledgeBase::sanitizeKnowledgeHtml(
            '<p onclick="alert(1)">Text</p><script>alert(2)</script><a href="javascript:alert(3)">Link</a>'
        );
        $this->assertStringNotContainsString('<script', $sanitizedKb);
        $this->assertStringNotContainsString('onclick=', $sanitizedKb);
        $this->assertStringNotContainsString('javascript:', $sanitizedKb);

        // Public category article can be created by employee and XSS is stripped
        Livewire::actingAs($employee, 'employee')
            ->test(\App\Livewire\Shop\Ai\AiKnowledgeBase::class)
            ->set('editForm.title', 'Sicherer Artikel')
            ->set('editForm.ai_knowledge_base_category_id', $publicCat->id)
            ->set('editForm.content', '<p>Hallo</p><script>evil()</script>')
            ->call('saveArticle')
            ->assertHasNoErrors();

        $savedArticle = \App\Models\Ai\AiKnowledgeBase::where('title', 'Sicherer Artikel')->firstOrFail();
        $this->assertStringNotContainsString('<script>', $savedArticle->content);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sanitizes_blog_html_and_hardens_blade_templates_and_ssl_verification(): void
    {
        $admin = Admin::factory()->create();

        $res = \App\Services\AI\Functions\AiMarketingBlogFuncs::marketing_blog_create_post([
            'title' => 'Security Blog Post',
            'content' => '<h2>Willkommen</h2><script>stealCookies()</script><img src="x" onerror="alert(1)"><a href="javascript:evil()">Klick</a>',
            'status' => 'published',
        ]);
        $this->assertSame('success', $res['status']);

        $post = \App\Models\Marketing\MarketingBlogPost::findOrFail($res['id']);
        $this->assertStringNotContainsString('<script>', $post->content);
        $this->assertStringNotContainsString('onerror=', $post->content);
        $this->assertStringNotContainsString('javascript:', $post->content);
        $this->assertStringContainsString('<h2>Willkommen</h2>', $post->content);

        // Verify GeminiAgent::processVisionPrompt configures CURLOPT_SSL_VERIFYPEER
        $geminiSource = file_get_contents(base_path('app/Services/AI/GeminiAgent.php'));
        $this->assertSame(
            3,
            substr_count($geminiSource, 'CURLOPT_SSL_VERIFYPEER'),
            'All 3 cURL endpoints in GeminiAgent must explicitly configure CURLOPT_SSL_VERIFYPEER'
        );

        // Verify FileDownloadService::downloadLaserSvg uses withoutRedirecting()
        $downloadSource = file_get_contents(base_path('app/Services/Export/FileDownloadService.php'));
        $this->assertStringContainsString('->withoutRedirecting()->get($url)', $downloadSource);

        // Verify Blade templates strip unsafe HTML/links
        $invoiceCreateBlade = file_get_contents(base_path('resources/views/livewire/shop/accounting/accounting-invoice-partials/invoice_create.blade.php'));
        $this->assertStringContainsString("e(\$manualInvoice['footer_text'])", $invoiceCreateBlade);

        $configuratorBlade = file_get_contents(base_path('resources/views/livewire/shop/product/product-configurator/product-configurator.blade.php'));
        $this->assertStringNotContainsString('{!! $product->description !!}', $configuratorBlade);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_enforces_rbac_and_path_traversal_guards_in_ai_finance_master_scout_calendar_contact_and_routine_funcs(): void
    {
        $admin = Admin::factory()->create(['first_name' => 'Alina']);
        $employee = Employee::create([
            'first_name' => 'Test',
            'last_name' => 'MitarbeiterR10',
            'email' => 'mitarbeiter-r10@example.com',
            'password' => bcrypt('password123'),
        ]);
        $customer = Customer::factory()->create();

        $runner = new class {
            use \App\Services\AI\Functions\AiFinanceFuncs;
            use \App\Services\AI\Functions\AiMasterFuncs;
            use \App\Services\AI\Functions\AiScoutFuncs;
            use \App\Services\AI\Functions\AiCalendarFuncs;
            use \App\Services\AI\Functions\AiContactFuncs;
            use \App\Services\AI\Functions\AiRoutineFuncs;
        };

        // 1. Authenticated customer must be blocked from Finance, Master, Scout, Calendar, Contact & Routine tools
        $this->actingAs($customer, 'customer');

        $this->assertSame('error', $runner::executeGetFinances([])['status']);
        $this->assertSame('error', $runner::executeListFixedCosts([])['status']);
        $this->assertSame('error', $runner::executeReadFixedCostContract(['keyword' => 'Test'])['status']);
        $this->assertSame('error', $runner::executeDeleteFixedCost(['id' => '123'])['status']);

        $this->assertSame('error', $runner::executeGetCoreKpis([])['status']);
        $this->assertSame('error', $runner::executeGetOperationalHealth([])['status']);
        $this->assertSame('error', $runner::executeToggleMaintenance(['action' => 'down', 'secret_passphrase' => 'CONFIRM_CEO_OVERRIDE'])['status']);

        $this->assertSame('error', $runner::executeGetCurrentMission([])['status']);
        $this->assertSame('error', $runner::executeGetGamificationLeaderboard([])['status']);
        $this->assertSame('error', $runner::executeSearchCustomers(['search_query' => 'Test'])['status']);

        $this->assertSame('error', $runner::executeGetCalendarEvents([])['status']);
        $this->assertSame('error', $runner::executeCreateCalendarEvent(['title' => 'Hack', 'start_date' => '2026-06-01 10:00:00'])['status']);
        $this->assertSame('error', $runner::executeContactGetAll([])['status']);
        $this->assertSame('error', $runner::executeContactSearch(['query' => 'Alina'])['status']);
        $this->assertSame('error', $runner::executeGetDayRoutines([])['status']);

        \Illuminate\Support\Facades\Auth::guard('customer')->logout();

        // 2. Authenticated employee must be blocked from Admin-only Finance, Master & CEO Mission tools
        $this->actingAs($employee, 'employee');

        $this->assertSame('error', $runner::executeGetFinances([])['status']);
        $this->assertSame('error', $runner::executeGetCoreKpis([])['status']);
        $this->assertSame('error', $runner::executeToggleMaintenance(['action' => 'down', 'secret_passphrase' => 'CONFIRM_CEO_OVERRIDE'])['status']);
        $this->assertSame('error', $runner::executeGetCurrentMission([])['status']);

        \Illuminate\Support\Facades\Auth::guard('employee')->logout();

        // 3. Path traversal in AccountingCostItem::contract_file_path must be blocked even for Admin
        $this->actingAs($admin, 'admin');

        $group = \App\Models\Accounting\AccountingGroup::create([
            'admin_id' => $admin->id,
            'name' => 'Fixkosten Test',
            'type' => 'expense',
            'position' => 1,
        ]);

        \App\Models\Accounting\AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Manipulierter Vertrag',
            'amount' => 99.00,
            'interval_months' => 1,
            'first_payment_date' => now()->startOfMonth(),
            'is_business' => true,
            'tax_rate' => 19,
            'contract_file_path' => '../../.env',
        ]);

        $traversalRes = $runner::executeReadFixedCostContract(['query' => 'Manipulierter Vertrag']);
        $this->assertSame('error', $traversalRes['status']);
        $this->assertStringContainsString('Ungültiger oder verdächtiger Dateipfad', $traversalRes['message']);

        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sanitizes_newsletter_html_and_svg_uploads_and_prevents_configurator_path_traversal(): void
    {
        // 1. NewsletterAutomaticMail must escape subscriber first_name and sanitize template HTML
        $template = \App\Models\Marketing\MarketingNewsletter::create([
            'type' => 'manual',
            'title' => 'Sicherheits-Newsletter',
            'subject' => "Wichtige Info\r\nBcc: attacker@evil.com",
            'content' => '<p>Hallo {first_name}</p><script>steal()</script><img src="x" onerror="alert(1)">',
            'days_offset' => 0,
            'is_active' => true,
        ]);

        $subscriber = \App\Models\Marketing\MarketingNewsletterSubscriber::create([
            'email' => 'sub@example.com',
            'is_verified' => true,
        ]);
        $subscriber->first_name = '<script>evilSub()</script>Anna';

        $mail = new \App\Mail\NewsletterAutomaticMail($template, $subscriber);
        $built = $mail->build();
        $this->assertStringNotContainsString("\r", (string) $built->subject);
        $this->assertStringNotContainsString("\n", (string) $built->subject);

        $renderedHtml = $mail->render();
        $this->assertStringNotContainsString('<script>steal()</script>', $renderedHtml);
        $this->assertStringNotContainsString('<script>evilSub()</script>', $renderedHtml);
        $this->assertStringNotContainsString('onerror=', $renderedHtml);
        $this->assertStringContainsString('&lt;script&gt;evilSub()&lt;/script&gt;Anna', $renderedHtml);

        // 2. AiMarketingNewsletterFuncs must sanitize campaign HTML and strip CRLF from subject
        $nlRunner = new class {
            use \App\Services\AI\Functions\AiMarketingNewsletterFuncs;
        };

        $campaignRes = $nlRunner::marketing_newsletter_create_manual_campaign([
            'title' => "Sommer Aktion\r\nInjected",
            'subject' => "Rabatt\r\nBcc: evil@example.com",
            'content' => '<h2>Hallo</h2><script>evil()</script><a href="javascript:alert(1)">Link</a>',
            'send_at' => now()->addDay()->format('Y-m-d H:i'),
        ]);
        $this->assertSame('success', $campaignRes['status']);
        $savedCampaign = \App\Models\Marketing\MarketingNewsletter::findOrFail($campaignRes['id']);
        $this->assertStringNotContainsString('<script>', $savedCampaign->content);
        $this->assertStringNotContainsString('javascript:', $savedCampaign->content);
        $this->assertStringNotContainsString("\r", $savedCampaign->subject);
        $this->assertStringNotContainsString("\n", $savedCampaign->subject);

        // 3. ProductConfiguratorService::sanitizeSvgContent must strip XXE, foreignObject, self-closing script, unquoted on*, and javascript: xlink:href
        $prodConfigService = app(\App\Services\ProductConfiguratorService::class);
        $maliciousSvg = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            . '<svg onload=alert(1) xmlns="http://www.w3.org/2000/svg">'
            . '<script xlink:href="https://evil.com/x.js" />'
            . '<foreignObject><iframe src="javascript:alert(2)"></iframe></foreignObject>'
            . '<a xlink:href="javascript:alert(3)"><path d="M0 0"/></a>'
            . '</svg>';

        $cleanSvg = $prodConfigService->sanitizeSvgContent($maliciousSvg);
        $this->assertStringNotContainsString('<!DOCTYPE', $cleanSvg);
        $this->assertStringNotContainsString('<!ENTITY', $cleanSvg);
        $this->assertStringNotContainsString('<script', $cleanSvg);
        $this->assertStringNotContainsString('<foreignObject', $cleanSvg);
        $this->assertStringNotContainsString('<iframe', $cleanSvg);
        $this->assertStringNotContainsString('onload=', $cleanSvg);
        $this->assertStringNotContainsString('javascript:', $cleanSvg);
        $this->assertStringContainsString('<path d="M0 0"/>', $cleanSvg);

        // 4. Path traversal in resolveVectorDiskPath, resolveVectorUrl, and ConfiguratorService::analyzeOverlayImage
        $this->assertNull($prodConfigService->resolveVectorDiskPath('../../.env'));
        $this->assertSame('', $prodConfigService->resolveVectorUrl('../../.env'));

        $configService = app(\App\Services\ConfiguratorService::class);
        $overlayRes = $configService->analyzeOverlayImage('../../.env');
        $this->assertIsArray($overlayRes);
        $this->assertArrayHasKey('surface_luminance', $overlayRes);

        // 5. AiAgentMessageMail must strip CRLF from subject and reject traversal attachments
        $agentMail = new \App\Services\AI\Mails\AiAgentMessageMail(
            "Test Betreff\r\nBcc: victim@example.com",
            'Inhalt',
            'Funkira',
            ['/var/www/html/storage/app/../../.env']
        );
        $this->assertStringNotContainsString("\r", $agentMail->envelope()->subject);
        $this->assertStringNotContainsString("\n", $agentMail->envelope()->subject);
        $this->assertCount(0, $agentMail->attachments());
    }
}

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

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_enforces_glb_optimizer_path_boundaries_and_ai_customer_sandbox_and_direct_tool_guards(): void
    {
        // 1. GlbOptimizerService::inspectGlb must block path traversal, non-.glb extensions, and paths outside storage/public
        $glbService = app(\App\Services\Storage\GlbOptimizerService::class);
        $this->assertFalse($glbService->inspectGlb(base_path('.env'))['exists']);
        $this->assertArrayHasKey('error', $glbService->inspectGlb(base_path('.env')));
        $this->assertFalse($glbService->inspectGlb(storage_path('app/../../.env'))['exists']);
        $this->assertArrayHasKey('error', $glbService->inspectGlb(storage_path('app/../../.env')));
        $tempOutsideGlb = sys_get_temp_dir() . '/outside_' . uniqid() . '.glb';
        file_put_contents($tempOutsideGlb, 'glTF');
        try {
            $outsideRes = $glbService->inspectGlb($tempOutsideGlb);
            $this->assertFalse($outsideRes['exists']);
            $this->assertArrayHasKey('error', $outsideRes);
        } finally {
            @unlink($tempOutsideGlb);
        }

        // 2. Authenticated Customer must be locked into Zero-Trust Customer Sandbox in AIFunctionsRegistry::execute()
        // even when __chat_id is omitted
        $customerA = \App\Models\Customer\Customer::factory()->create();
        $customerB = \App\Models\Customer\Customer::factory()->create();
        \Illuminate\Support\Facades\Auth::guard('customer')->login($customerA);

        $couponDenied = \App\Services\AI\AIFunctionsRegistry::execute('voucher_create_coupon', [
            'code' => 'HACK100',
            'type' => 'fixed',
            'value' => 100,
        ]);
        $this->assertSame('error', $couponDenied['status']);
        $this->assertStringContainsString('SANDBOX VIOLATION', $couponDenied['message']);

        $adminChatDenied = \App\Services\AI\AIFunctionsRegistry::execute('admin_search_support_chats', []);
        $this->assertSame('error', $adminChatDenied['status']);
        $this->assertStringContainsString('ROLE VIOLATION', $adminChatDenied['message']);

        // 3. Direct trait method calls as authenticated Customer must be rejected across internal AI Function traits
        $agentsDenied = \App\Services\AI\AIFunctionsRegistry::executeWorkspaceCreateFolder(['path' => 'agenten/workspace/Hacked']);
        $this->assertSame('error', $agentsDenied['status']);
        $this->assertStringContainsString('Zugriff verweigert', $agentsDenied['message']);

        $mailDenied = \App\Services\AI\AIFunctionsRegistry::executeMailListPending([]);
        $this->assertSame('error', $mailDenied['status']);
        $this->assertStringContainsString('Zugriff verweigert', $mailDenied['message']);

        $systemDenied = \App\Services\AI\AIFunctionsRegistry::executeReadCode(['file_path' => 'routes/web.php']);
        $this->assertSame('error', $systemDenied['status']);
        $this->assertStringContainsString('Zugriff verweigert', $systemDenied['message']);

        $orderDenied = \App\Services\AI\AIFunctionsRegistry::executeOrderUpdateStatus(['order_id' => '1', 'new_status' => 'cancelled']);
        $this->assertSame('error', $orderDenied['status']);
        $this->assertStringContainsString('Zugriff verweigert', $orderDenied['message']);

        $marketingDenied = \App\Services\AI\AIFunctionsRegistry::executeCreateCoupon(['code' => 'FREE', 'type' => 'percent', 'value' => 90]);
        $this->assertSame('error', $marketingDenied['status']);
        $this->assertStringContainsString('Zugriff verweigert', $marketingDenied['message']);

        // 4. Customer A cannot resolve, penalize, escalate, or leak Customer B's SupportCustomerChat
        $chatB = \App\Models\Support\SupportCustomerChat::create([
            'customer_id' => $customerB->id,
            'status' => 'open',
        ]);
        \App\Models\Support\SupportCustomerChatMessage::create([
            'support_customer_chat_id' => $chatB->id,
            'sender' => 'customer',
            'message' => 'GEHEIMER_CHAT_VON_KUNDE_B',
        ]);

        $resolveDenied = \App\Services\AI\AIFunctionsRegistry::executeResolveChat([
            '__chat_id' => $chatB->id,
            'closing_summary' => 'Manipuliert',
        ]);
        $this->assertSame('error', $resolveDenied['status']);
        $this->assertSame('open', $chatB->fresh()->status);

        $penalizeDenied = \App\Services\AI\AIFunctionsRegistry::executePenalizeOfftopic([
            '__chat_id' => $chatB->id,
            'reason' => 'Fake',
        ]);
        $this->assertSame('error', $penalizeDenied['status']);

        \Illuminate\Support\Facades\Auth::guard('customer')->logout();

        // 5. Employee cannot execute admin_* tools via AIFunctionsRegistry::execute()
        $employee = Employee::create([
            'first_name' => 'Test',
            'last_name' => 'MitarbeiterR11',
            'email' => 'mitarbeiter-r11@example.com',
            'password' => bcrypt('password123'),
        ]);
        \Illuminate\Support\Facades\Auth::guard('employee')->login($employee);

        $empAdminDenied = \App\Services\AI\AIFunctionsRegistry::execute('admin_read_support_ticket_details', [
            'ticket_number' => 'T-100',
        ]);
        $this->assertSame('error', $empAdminDenied['status']);
        $this->assertStringContainsString('ROLE VIOLATION', $empAdminDenied['message']);

        \Illuminate\Support\Facades\Auth::guard('employee')->logout();

        // 6. AiPersonaFuncs::executePersonaDeleteIntel must refuse to delete general non-OSINT knowledge base articles
        $admin = \App\Models\Admin\Admin::factory()->create();
        \Illuminate\Support\Facades\Auth::guard('admin')->login($admin);

        $generalCat = \App\Models\Ai\AiKnowledgeBaseCategory::firstOrCreate(
            ['slug' => 'shop-faq'],
            ['name' => 'Shop FAQ']
        );
        $generalArticle = \App\Models\Ai\AiKnowledgeBase::create([
            'ai_knowledge_base_category_id' => $generalCat->id,
            'title' => 'Wichtige Versandregeln',
            'slug' => 'wichtige-versandregeln-' . uniqid(),
            'content' => 'Versand erfolgt per DHL.',
            'tags' => ['versand'],
            'is_published' => true,
        ]);

        $deleteGeneralRes = \App\Services\AI\AIFunctionsRegistry::executePersonaDeleteIntel([
            'intel_id' => $generalArticle->id,
        ]);
        $this->assertSame('error', $deleteGeneralRes['status']);
        $this->assertDatabaseHas('ai_knowledge_bases', ['id' => $generalArticle->id]);

        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_ai_mailables_prompt_service_background_jobs_and_remaining_ai_traits(): void
    {
        // 1. AI Mailables must block arbitrary local files outside storage/app and block sensitive extensions (.env, .php)
        $validStoragePdf = storage_path('app/test_valid_report_' . uniqid() . '.pdf');
        $blockedStoragePhp = storage_path('app/test_malicious_' . uniqid() . '.php');
        file_put_contents($validStoragePdf, '%PDF-1.4 test');
        file_put_contents($blockedStoragePhp, '<?php echo "secret";');

        try {
            $agentMail = new \App\Services\AI\Mails\AiAgentMessageMail(
                'Subject',
                'Body',
                'Agent',
                [
                    base_path('composer.json'),
                    '/etc/passwd',
                    $blockedStoragePhp,
                    $validStoragePdf,
                ]
            );
            $attachments = $agentMail->attachments();
            $this->assertCount(1, $attachments);

            $holidayMail = new \App\Services\AI\Mails\AiHolidayPlanMail('Sub', 'Body', 'Mapi', [base_path('composer.json'), $blockedStoragePhp]);
            $this->assertCount(0, $holidayMail->attachments());

            $mapMail = new \App\Services\AI\Mails\AiMapSummaryMail('Sub', 'Body', 'Mapi', [base_path('composer.json'), $blockedStoragePhp]);
            $this->assertCount(0, $mapMail->attachments());
        } finally {
            @unlink($validStoragePdf);
            @unlink($blockedStoragePhp);
        }

        // 2. AiPromptService must not inject CEO ("Alina") identity or internal team overview for Customers, and must distinguish Employees
        $funkira = \App\Models\Ai\AiAgent::firstOrCreate(
            ['name' => 'Funkira'],
            ['system_prompt' => 'Basis Prompt', 'is_active' => true]
        );

        $customer = Customer::factory()->create();
        \Illuminate\Support\Facades\Auth::guard('customer')->login($customer);

        $customerPrompt = \App\Services\AI\AiPromptService::getRichPrompt($funkira);
        $this->assertStringNotContainsString('Du sprichst DIREKT mit Alina', $customerPrompt);
        $this->assertStringNotContainsString('[TEAM-ÜBERSICHT & DEINE ROLLE ALS ZENTRALES SPRACHROHR]', $customerPrompt);

        // 3. Remaining AI Function traits must reject direct invocation when a Customer is authenticated
        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::executeHolidayGeneratePdfPlan(['title' => 'Test'])['status']);
        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::executeLaserGeneratePdfAndMail(['title' => 'Test', 'content' => 'X', 'target_action' => 'download'])['status']);
        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::executeMapToggleLivedata(['active' => true])['status']);
        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::executeMapSearchAndMarkNews(['query' => 'Berlin'])['status']);
        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::searchGlobalNews(['query' => 'Berlin'])['status']);
        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::executeGetTasks([])['status']);
        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::marketing_blog_get_posts([])['status']);
        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::marketing_newsletter_get_subscribers([])['status']);

        \Illuminate\Support\Facades\Auth::guard('customer')->logout();

        $employee = Employee::create([
            'first_name' => 'Team',
            'last_name' => 'MemberR12',
            'email' => 'mitarbeiter-r12@example.com',
            'password' => bcrypt('password123'),
        ]);
        \Illuminate\Support\Facades\Auth::guard('employee')->login($employee);

        $employeePrompt = \App\Services\AI\AiPromptService::getRichPrompt($funkira);
        $this->assertStringNotContainsString('Du sprichst DIREKT mit Alina', $employeePrompt);
        $this->assertStringContainsString('Mitarbeiter (Team-Mitglied)', $employeePrompt);

        \Illuminate\Support\Facades\Auth::guard('employee')->logout();

        // 4. AiHealthFuncs::executeUpdateDoctor / executeDeleteDoctor must not modify or delete non-medical contacts
        $admin = \App\Models\Admin\Admin::factory()->create();
        \Illuminate\Support\Facades\Auth::guard('admin')->login($admin);

        $supplierContact = \App\Models\Management\ManagementContact::create([
            'first_name' => 'Max',
            'last_name' => 'Lieferant',
            'relation_type' => 'Lieferant',
        ]);

        $updateDocRes = \App\Services\AI\AIFunctionsRegistry::executeUpdateDoctor([
            'contact_id' => $supplierContact->id,
            'first_name' => 'Manipuliert',
        ]);
        $this->assertTrue($updateDocRes['error'] ?? false);
        $this->assertSame('Max', $supplierContact->fresh()->first_name);

        $deleteDocRes = \App\Services\AI\AIFunctionsRegistry::executeDeleteDoctor([
            'contact_id' => $supplierContact->id,
        ]);
        $this->assertTrue($deleteDocRes['error'] ?? false);
        $this->assertDatabaseHas('management_contacts', ['id' => $supplierContact->id]);

        \Illuminate\Support\Facades\Auth::guard('admin')->logout();

        // 5. ExecuteAiBackgroundToolJob must enforce the forbidden tools blocklist in handle()
        $bgTask = \App\Models\Ai\AiWorkspaceTask::create([
            'prompt' => 'Test Background Blocklist',
            'status' => 'pending',
            'assigned_agent_id' => $funkira->id,
        ]);
        $job = new \App\Jobs\ExecuteAiBackgroundToolJob($bgTask->id, 'system_run_command', ['command' => 'whoami'], $funkira->id);
        $job->handle();
        $bgTask->refresh();
        $this->assertSame('failed', $bgTask->status);
        $this->assertStringContainsString('Sicherheits-Blockade', (string) $bgTask->response_content);

        // 6. ReceiptAiScannerService must reject files outside storage/app, temp, and tests/fixtures (even in unit tests)
        $logDummy = storage_path('logs/test_receipt_outside_' . uniqid() . '.jpg');
        file_put_contents($logDummy, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9");
        try {
            $this->assertNull(\App\Services\AI\ReceiptAiScannerService::analyze($logDummy));
        } finally {
            @unlink($logDummy);
        }

        // 7. StealthCrawlerService::cleanUrl and FetchMailsCommand sanitizers
        $crawler = new \App\Services\StealthCrawlerService();
        $this->assertSame('', $crawler->cleanUrl('javascript:alert(1)'));
        $this->assertSame('', $crawler->cleanUrl('data:text/html,<script>alert(1)</script>'));
        $this->assertSame('https://www.etsy.com/listing/123', $crawler->cleanUrl('/listing/123', 'www.etsy.com'));

        $dirtyEmailHtml = '<p onclick="evil()">Hallo</p><script>alert(1)</script><iframe src="https://evil.test"></iframe><a href="javascript:alert(2)">Link</a>';
        $cleanEmailHtml = \App\Console\Commands\FetchMailsCommand::sanitizeEmailHtml($dirtyEmailHtml);
        $this->assertStringNotContainsString('<script', (string) $cleanEmailHtml);
        $this->assertStringNotContainsString('<iframe', (string) $cleanEmailHtml);
        $this->assertStringNotContainsString('onclick', (string) $cleanEmailHtml);
        $this->assertStringNotContainsString('javascript:', (string) $cleanEmailHtml);

        $this->assertSame('shell.dat', \App\Console\Commands\FetchMailsCommand::sanitizeAttachmentFilename('shell.php', 'php'));
        $this->assertSame('xss.dat', \App\Console\Commands\FetchMailsCommand::sanitizeAttachmentFilename('xss.svg', 'svg'));
        $this->assertSame('rechnung-2026.pdf', \App\Console\Commands\FetchMailsCommand::sanitizeAttachmentFilename('Rechnung 2026.pdf', 'pdf'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_workspace_folders_review_media_persona_dossiers_system_funcs_and_contract_service(): void
    {
        \Illuminate\Support\Facades\Storage::fake('workspace');
        \Illuminate\Support\Facades\Storage::fake('public');

        $admin = \App\Models\Admin\Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'MitarbeiterR13',
            'email' => 'employee-round13@seelenfunke.test',
            'password' => bcrypt('password123'),
        ]);
        $customer = Customer::factory()->create();

        // 1. Workspace root & main folders must be protected against bulk wipe/move
        \Illuminate\Support\Facades\Auth::guard('admin')->login($admin);
        $wipeRoot = \App\Services\AI\AIFunctionsRegistry::executeWorkspaceDeleteFolderContent([
            'folder_path' => '',
        ]);
        $this->assertSame('error', $wipeRoot['status'] ?? null);
        $this->assertStringContainsString('Hauptverzeichnis', (string) ($wipeRoot['message'] ?? ''));

        $moveRootContent = \App\Services\AI\AIFunctionsRegistry::executeWorkspaceMoveFolderContent([
            'source_path' => 'agenten/workspace',
            'target_path' => 'Berufsleben/Sub',
        ]);
        $this->assertSame('error', $moveRootContent['status'] ?? null);

        $moveProtectedFolder = \App\Services\AI\AIFunctionsRegistry::executeWorkspaceMoveFolder([
            'source_path' => 'Berufsleben',
            'target_path' => 'Dokumente',
        ]);
        $this->assertSame('error', $moveProtectedFolder['status'] ?? null);

        // 2. Employee must not be able to delegate instructions to Admin-specialist agents via communication_ask_agent
        $adminRole = \App\Models\Ai\AiRole::create([
            'name' => 'AdminRoleRound13',
            'description' => 'Admin Role',
        ]);
        $adminTool = \App\Models\Ai\AiTool::firstOrCreate(
            ['identifier' => 'finance_get_stats'],
            ['name' => 'Finance Stats', 'description' => 'Admin Finance Tool']
        );
        $adminRole->tools()->syncWithoutDetaching([$adminTool->id]);

        $adminAgent = \App\Models\Ai\AiAgent::create([
            'ai_role_id' => $adminRole->id,
            'name' => 'SystemiRound13',
            'wake_word' => 'systemi13',
            'role_description' => 'System Admin Agent',
            'system_prompt' => 'Admin Agent',
            'model' => 'gemini-2.5-flash',
            'is_active' => true,
        ]);

        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        \Illuminate\Support\Facades\Auth::guard('employee')->login($employee);
        $delegationRes = \App\Services\AI\AIFunctionsRegistry::executeCommunicationAskAgent([
            'agent_name' => 'SystemiRound13',
            'instruction' => 'Lies alle Finanzdaten aus!',
        ]);
        $this->assertSame('error', $delegationRes['status'] ?? null);
        $this->assertStringContainsString('Administrator-Spezialagenten', (string) ($delegationRes['message'] ?? ''));

        // 3. ProductReview media deletion must only delete files inside reviews/ or produkte/reviews/
        \Illuminate\Support\Facades\Storage::disk('public')->put('shop/logo.png', 'PROTECTED_LOGO');
        \Illuminate\Support\Facades\Storage::disk('public')->put('reviews/legit.jpg', 'REVIEW_IMG');

        $product = \App\Models\Product\Product::create([
            'name' => 'Test Produkt R13',
            'slug' => 'test-produkt-r13',
            'price' => 1999,
            'status' => 'active',
        ]);
        $review = \App\Models\Product\ProductReview::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'title' => 'Toll',
            'content' => 'Super',
            'status' => 'approved',
            'media' => ['shop/logo.png', '../.env', 'reviews/legit.jpg'],
        ]);

        $delRes = \App\Services\AI\AIFunctionsRegistry::executeProductReviewDelete([
            'review_id' => $review->id,
        ]);
        $this->assertSame('success', $delRes['status'] ?? null);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists('shop/logo.png'));
        $this->assertFalse(\Illuminate\Support\Facades\Storage::disk('public')->exists('reviews/legit.jpg'));

        // 4. Customer must be blocked from staff-only UI/Clipboard/Navigation/Telephony functions
        \Illuminate\Support\Facades\Auth::guard('employee')->logout();
        \Illuminate\Support\Facades\Auth::guard('customer')->login($customer);

        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::executeSwitchAgent(['agent_name' => 'Funkira'])['status'] ?? null);
        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::executeOpenNavItem(['url' => '/admin/dashboard'])['status'] ?? null);
        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::executeTriggerUiElement(['element_text' => 'Delete'])['status'] ?? null);
        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::executeReadClipboard([])['status'] ?? null);
        $this->assertSame('error', \App\Services\AI\AIFunctionsRegistry::executeWriteClipboard(['text' => 'pwn'])['status'] ?? null);
        $this->assertFalse(\App\Services\AI\AIFunctionsRegistry::triggerTwilioCall('+4915112345678')['success'] ?? true);

        // 5. Admin analyze_neural_error & read_code must block config/ and storage/ files
        \Illuminate\Support\Facades\Auth::guard('customer')->logout();
        \Illuminate\Support\Facades\Auth::guard('admin')->login($admin);

        $neuralConfigRes = \App\Services\AI\AIFunctionsRegistry::executeAnalyzeNeuralError([
            'file_path' => 'config/services.php',
            'error_context' => 'Test',
        ]);
        $this->assertSame('error', $neuralConfigRes['status'] ?? null);

        $readStorageRes = \App\Services\AI\AIFunctionsRegistry::executeReadCode([
            'file_path' => 'storage/logs/laravel.log',
        ]);
        $this->assertSame('error', $readStorageRes['status'] ?? null);

        // 6. ContractDocumentService::getRealPath must reject files in storage/logs/ outside storage/app
        $logSecretFile = storage_path('logs/contract_secret_test_' . uniqid() . '.pdf');
        file_put_contents($logSecretFile, '%PDF-1.4 dummy');
        try {
            $contractService = new \App\Services\Accounting\ContractDocumentService();
            $ref = new \ReflectionMethod($contractService, 'getRealPath');
            $ref->setAccessible(true);
            $this->assertNull($ref->invoke($contractService, $logSecretFile));
        } finally {
            @unlink($logSecretFile);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_ai_controller_history_idor_channels_linktree_emergency_oauth_and_tracking_middlewares(): void
    {
        $admin = \App\Models\Admin\Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'Round14',
            'email' => 'admin-round14@example.com',
            'password' => bcrypt('password123'),
        ]);
        $employee = \App\Models\Employee\Employee::create([
            'first_name' => 'Emp',
            'last_name' => 'Round14',
            'email' => 'emp-round14@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 1. AIController::history must block non-admin access to Telegram sessions or unowned session IDs
        \App\Models\Ai\AiChatMemory::create([
            'session_id' => 'telegram_chat_99887766',
            'role' => 'user',
            'content' => 'Geheime CEO Telegram Nachricht',
        ]);

        $this->actingAs($employee, 'employee')
            ->getJson('/api/ai/history?chat_session_id=telegram_chat_99887766')
            ->assertStatus(403);

        $empSession = \App\Models\Ai\AiChatSession::create([
            'user_id' => $employee->id,
            'title' => 'Mitarbeiter Eigene Session',
        ]);
        \App\Models\Ai\AiChatMemory::create([
            'session_id' => $empSession->id,
            'role' => 'user',
            'content' => 'Eigene Mitarbeiter Nachricht',
        ]);

        $this->actingAs($employee, 'employee')
            ->getJson('/api/ai/history?chat_session_id=' . $empSession->id)
            ->assertStatus(200)
            ->assertJsonFragment(['content' => 'Eigene Mitarbeiter Nachricht']);

        $this->actingAs($admin, 'admin')
            ->getJson('/api/ai/history?chat_session_id=telegram_chat_99887766')
            ->assertStatus(200)
            ->assertJsonFragment(['content' => 'Geheime CEO Telegram Nachricht']);

        // 2. AiAgent must hide telegram_bot_token in JSON/Array serialization
        $agent = \App\Models\Ai\AiAgent::create([
            'name' => 'BotTokenTestAgent',
            'wake_word' => 'bottoken',
            'role_description' => 'Test',
            'system_prompt' => 'Test',
            'model' => 'gemini-2.5-flash',
            'is_active' => true,
            'telegram_bot_token' => '123456789:AAH_SECRET_TELEGRAM_TOKEN_XYZ',
        ]);
        $this->assertArrayNotHasKey('telegram_bot_token', $agent->toArray());

        // 3. MarketingLinktree::resolveEnvironmentUrl must block protocol-relative //evil.com and javascript: URLs
        $this->assertSame(url('/shop'), \App\Models\Marketing\MarketingLinktree::resolveEnvironmentUrl('//evil.com/phish'));
        $this->assertSame(url('/shop'), \App\Models\Marketing\MarketingLinktree::resolveEnvironmentUrl('/\\evil.com/phish'));
        $this->assertSame(url('/shop'), \App\Models\Marketing\MarketingLinktree::resolveEnvironmentUrl('javascript:alert(1)'));
        $this->assertSame(url('/kontakt'), \App\Models\Marketing\MarketingLinktree::resolveEnvironmentUrl('/kontakt'));
        $this->assertSame('https://instagram.com/seelenfunke', \App\Models\Marketing\MarketingLinktree::resolveEnvironmentUrl('https://instagram.com/seelenfunke'));

        // 4. EmergencyProtocol::getSafeEmergencySettings must strip all keys containing secret/token/key/password/pin/hash
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'paypal_client_secret'], ['value' => 'PP_SECRET_123']);
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'lexoffice_api_key'], ['value' => 'LEX_KEY_456']);
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'custom_auth_token'], ['value' => 'TOK_789']);
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'company_name'], ['value' => 'Mein Seelenfunke']);

        $emergencyComp = new \App\Livewire\Frontend\Emergency\EmergencyProtocol();
        $refMethod = new \ReflectionMethod($emergencyComp, 'getSafeEmergencySettings');
        $refMethod->setAccessible(true);
        $safeSettings = $refMethod->invoke($emergencyComp);

        $this->assertArrayNotHasKey('paypal_client_secret', $safeSettings);
        $this->assertArrayNotHasKey('lexoffice_api_key', $safeSettings);
        $this->assertArrayNotHasKey('custom_auth_token', $safeSettings);
        $this->assertSame('Mein Seelenfunke', $safeSettings['company_name'] ?? null);

        // 5. MarketingTrackingMiddleware must gracefully handle array query parameters (?ref[]=1&coupon[]=2&utm_source[]=3) without 500 error
        $middleware = new \App\Http\Middleware\MarketingTrackingMiddleware();
        $req = \Illuminate\Http\Request::create('/shop', 'GET', [
            'utm_source' => ['array_injection'],
            'ref' => ['array_ref'],
            'coupon' => ['array_coupon'],
        ]);
        $res = $middleware->handle($req, fn () => new \Illuminate\Http\Response('OK', 200));
        $this->assertSame(200, $res->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_dateitrichter_file_download_service_glb_optimizer_wiki_uploads_and_customer_tickets(): void
    {
        // 1. DateitrichterService::processSingleFile must reject files outside agenten/workspace/
        \Illuminate\Support\Facades\Storage::disk('workspace')->put('shop/test_logo.txt', 'Shop Asset');
        \Illuminate\Support\Facades\Storage::disk('workspace')->makeDirectory('agenten/workspace');
        $trichter = new \App\Services\AI\Workspace\DateitrichterService();
        $this->assertNull($trichter->processSingleFile('shop/test_logo.txt'));
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('workspace')->exists('shop/test_logo.txt'));
        \Illuminate\Support\Facades\Storage::disk('workspace')->deleteDirectory('shop');

        // 2. FileDownloadService::downloadMailAttachment must block paths outside mail-attachments directories
        $admin = \App\Models\Admin\Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'Round15',
            'email' => 'admin-round15@example.com',
            'password' => bcrypt('password123'),
        ]);
        $this->actingAs($admin, 'admin');

        $account = \App\Models\Management\Mail\MailAccount::create([
            'name' => 'SecTest',
            'email' => 'sec@seelenfunke.test',
            'imap_host' => 'imap.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtp.test',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'password' => 'secret',
        ]);
        $msg = \App\Models\Management\Mail\MailMessage::create([
            'mail_account_id' => $account->id,
            'message_id' => 'msg_sec_15',
            'folder' => 'INBOX',
            'subject' => 'Test',
            'from_email' => 'a@b.test',
            'to_email' => 'sec@seelenfunke.test',
            'received_at' => now(),
        ]);
        $badAttachment = \App\Models\Management\Mail\MailAttachment::create([
            'mail_message_id' => $msg->id,
            'filename' => 'secret.pdf',
            'content_type' => 'application/pdf',
            'size' => 123,
            'path' => 'buchhaltung/invoices/secret.pdf',
        ]);

        $downloadService = new \App\Services\Export\FileDownloadService();
        try {
            $downloadService->downloadMailAttachment($badAttachment->id);
            $this->fail('Expected HttpException 403 for attachment path outside mail-attachments/');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // 3. GlbOptimizerService::inspectGlb must reject files outside storage/app/public and public/
        $privateGlb = storage_path('logs/private_test.glb');
        file_put_contents($privateGlb, 'glTF');
        $glbService = new \App\Services\Storage\GlbOptimizerService();
        $inspectRes = $glbService->inspectGlb($privateGlb);
        $this->assertFalse($inspectRes['exists'] ?? true);
        @unlink($privateGlb);

        // 4. CustomerTicketsComponent::removeAttachment must ignore non-whitelisted property names
        $ticketComp = new \App\Livewire\Customer\CustomerTicketsComponent();
        $ticketComp->customerId = 'cust-123';
        $ticketComp->removeAttachment(0, 'customerId');
        $this->assertSame('cust-123', $ticketComp->customerId);

        // 5. AuthProfileDropdown::generateQrCode and generateSvg must be protected (not callable as public Livewire actions)
        $refQr = new \ReflectionMethod(\App\Livewire\Auth\AuthProfileDropdown::class, 'generateQrCode');
        $refSvg = new \ReflectionMethod(\App\Livewire\Auth\AuthProfileDropdown::class, 'generateSvg');
        $this->assertTrue($refQr->isProtected());
        $this->assertTrue($refSvg->isProtected());

        // 6. GenerateTaxExport::buildDatevCsv must neutralize CSV formula injection (=, +, -, @)
        $taxCmd = new \App\Console\Commands\GenerateTaxExport();
        $refCsv = new \ReflectionMethod($taxCmd, 'buildDatevCsv');
        $refCsv->setAccessible(true);
        $csvOut = $refCsv->invoke($taxCmd, [
            'month_number' => '09',
            'raw_orders' => [],
            'raw_specials' => [
                (object) [
                    'amount' => -19.99,
                    'execution_date' => '2026-09-15',
                    'title' => '=CMD|\' /C calc\'!A0',
                    'invoice_number' => '+SUM(1,1)',
                ],
            ],
            'raw_fixed' => [],
        ]);
        $this->assertStringContainsString('"\'=CMD|', $csvOut);
        $this->assertStringContainsString('"\'+SUM(1,1)"', $csvOut);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_customer_email_verification_gift_vouchers_cart_quote_prices_and_checkout_success(): void
    {
        // 1. Unverified customer email must not gain access to gift vouchers addressed to recipient_email
        $victimVoucher = \App\Models\Marketing\MarketingGiftVoucher::create([
            'code' => 'DANKE-VICTIM-R16',
            'initial_value' => 2500,
            'current_balance' => 2500,
            'recipient_name' => 'Victim',
            'recipient_email' => 'victim-r16@example.com',
            'delivery_method' => 'email',
            'is_active' => true,
        ]);

        $unverifiedCustomer = \App\Models\Customer\Customer::create([
            'first_name' => 'Attacker',
            'last_name' => 'Unverified',
            'email' => 'victim-r16@example.com',
            'password' => bcrypt('password123'),
        ]);
        // Profile email_verified_at is null -> hasVerifiedEmail() is false
        $this->assertFalse($unverifiedCustomer->fresh()->hasVerifiedEmail());

        $this->actingAs($unverifiedCustomer, 'customer')
            ->get(route('customer.gift-voucher.download', $victimVoucher))
            ->assertStatus(403);

        // Also cannot redeem personal DANKE voucher in CartService when unverified
        $cartService = app(\App\Services\CartService::class);
        $applyRes = $cartService->applyCoupon('DANKE-VICTIM-R16');
        $this->assertFalse($applyRes['success']);

        // 2. CartService::resolveItemUnitPrice must not apply bulk quote unit_price when quantity is reduced below quoted quantity
        $product = \App\Models\Product\Product::create([
            'name' => 'Staffel Produkt R16',
            'slug' => 'staffel-produkt-r16',
            'price' => 2500,
            'status' => 'active',
            'type' => 'physical',
        ]);
        $quote = \App\Models\Order\OrderQuoteRequest::create([
            'quote_number' => 'ANG-R16-001',
            'token' => 'tok_r16_bulk_test',
            'status' => 'open',
            'first_name' => 'Bulk',
            'last_name' => 'Buyer',
            'email' => 'bulk@example.com',
            'net_total' => 42017,
            'tax_total' => 7983,
            'gross_total' => 50000,
            'expires_at' => now()->addDays(7),
        ]);
        \App\Models\Order\OrderQuoteRequestItem::create([
            'quote_request_id' => $quote->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 100,
            'unit_price' => 500,
            'total_price' => 50000,
            'configuration' => ['text' => 'B2B'],
        ]);

        session(['checkout_from_quote_id' => $quote->id]);
        $this->assertSame(500, $cartService->resolveItemUnitPrice($product, 100, ['text' => 'B2B']));
        $this->assertSame(2500, $cartService->resolveItemUnitPrice($product, 1, ['text' => 'B2B']));
        $this->assertSame(2500, $cartService->resolveItemUnitPrice($product, 100, ['text' => 'DifferentConfig']));
        session()->forget('checkout_from_quote_id');

        // 3. OrderCheckoutSuccess must not leak order_number or email to unauthenticated visitor without payment_intent_client_secret
        \Illuminate\Support\Facades\Auth::guard('customer')->logout();
        $secretOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R16-SECRET',
            'email' => 'secret-buyer-r16@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'stripe',
            'stripe_payment_intent_id' => 'pi_test_r16_secret',
            'billing_address' => ['first_name' => 'Secret', 'last_name' => 'Buyer'],
            'shipping_address' => [],
            'subtotal_price' => 1999,
            'tax_amount' => 319,
            'shipping_price' => 0,
            'total_price' => 1999,
        ]);

        \Livewire\Livewire::withQueryParams(['payment_intent' => 'pi_test_r16_secret'])
            ->test(\App\Livewire\Shop\Order\OrderCheckout\OrderCheckoutSuccess::class)
            ->assertSet('finalOrderNumber', '')
            ->assertSet('email', '');

        // 4. Legacy snapshot redirect must reject double-dot traversal payloads ('....//')
        $this->get('/storage/snapshots/....//secret.png')
            ->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_system_bridge_ssrf_product_reviews_calculator_referrals_and_digital_downloads(): void
    {
        // 1. SystemSecurityService::isTrustedBridgeRequest must reject wrong secrets even from loopback/private IP
        //    and must reject private subnet fallback when X-Forwarded-For proxy headers are present
        config(['services.twilio.bridge_secret' => 'super-secret-bridge-token-r17']);

        $badSecretReq = \Illuminate\Http\Request::create('/api/system/security/block-ip', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_BRIDGE_SECRET' => 'wrong-secret',
        ]);
        $this->assertFalse(\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($badSecretReq));

        $proxiedPrivateReq = \Illuminate\Http\Request::create('/api/system/security/block-ip', 'POST', [], [], [], [
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
        ]);
        $this->assertFalse(\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($proxiedPrivateReq));

        $validSecretReq = \Illuminate\Http\Request::create('/api/system/security/block-ip', 'POST', [], [], [], [
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_BRIDGE_SECRET' => 'super-secret-bridge-token-r17',
        ]);
        $this->assertTrue(\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($validSecretReq));
        config(['services.twilio.bridge_secret' => null]);

        // 2. SystemSecurityService::isSafeMailHost must block single-label Docker service names & internal suffixes
        $this->assertFalse(\App\Services\System\SystemSecurityService::isSafeMailHost('meine_datenbank'));
        $this->assertFalse(\App\Services\System\SystemSecurityService::isSafeMailHost('mailpit'));
        $this->assertFalse(\App\Services\System\SystemSecurityService::isSafeMailHost('host.docker.internal'));
        $this->assertFalse(\App\Services\System\SystemSecurityService::isSafeMailHost('[::1]'));

        // 3. ProductReviews $product must be #[Locked]
        $product = \App\Models\Product\Product::create([
            'name' => 'Review Produkt R17',
            'slug' => 'review-produkt-r17',
            'price' => 1500,
            'status' => 'active',
            'type' => 'physical',
            'variants_data' => [
                ['id' => 'var_gold', 'name' => 'Deluxe Gold', 'price' => 35.00],
            ],
        ]);
        $otherProduct = \App\Models\Product\Product::create([
            'name' => 'Other Produkt R17',
            'slug' => 'other-produkt-r17',
            'price' => 9900,
            'status' => 'active',
            'type' => 'physical',
        ]);

        $lockedExceptionThrown = false;
        try {
            \Livewire\Livewire::test(\App\Livewire\Shop\Product\ProductReviews::class, ['product' => $product])
                ->set('product', $otherProduct->id);
        } catch (\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException $e) {
            $lockedExceptionThrown = true;
        }
        $this->assertTrue($lockedExceptionThrown, 'ProductReviews::$product must be #[Locked].');

        // 4. ProductCalculator must respect variant pricing in getTierPriceCents() / calculateTotal()
        $calcTest = \Livewire\Livewire::test(\App\Livewire\Shop\Product\ProductCalculator\ProductCalculator::class)
            ->call('saveItemFromConfigurator', [
                'product_id' => $product->id,
                'qty' => 1,
                'variant_id' => 'var_gold',
            ]);
        $calcItems = $calcTest->get('cartItems');
        $this->assertSame(35.0, (float) ($calcItems[0]['calculated_single_price'] ?? 0));

        // 5. ReferralService must block self-referral by customer_id even with different email,
        //    and reverseOrderReferralReward must also reverse rewards granted via utm_campaign_last ('ref:...')
        $referrer = \App\Models\Customer\Customer::create([
            'first_name' => 'Referrer',
            'last_name' => 'Customer',
            'email' => 'referrer-r17@example.com',
            'password' => bcrypt('password123'),
        ]);
        $referralService = app(\App\Services\ReferralService::class);
        $referral = $referralService->getOrCreateReferralForCustomer(
            $referrer->email,
            $referrer->first_name,
            $referrer->last_name,
            $referrer->id
        );

        // Self-referral order with different email but same customer_id
        $selfOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R17-SELF',
            'customer_id' => $referrer->id,
            'email' => 'alias-referrer-r17@example.com',
            'coupon_code' => $referral->code,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'billing_address' => ['first_name' => 'Referrer', 'last_name' => 'Customer'],
            'shipping_address' => [],
            'subtotal_price' => 2500,
            'tax_amount' => 399,
            'shipping_price' => 0,
            'total_price' => 2500,
        ]);
        $this->assertNull($referralService->processOrderReferralReward($selfOrder));

        // Legitimate referral via utm_campaign_last ('ref:...') and subsequent reversal on cancellation
        $friendOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R17-FRIEND',
            'email' => 'new-friend-r17@example.com',
            'coupon_code' => null,
            'utm_campaign_last' => 'ref:' . $referral->code,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'billing_address' => ['first_name' => 'Friend', 'last_name' => 'Buyer'],
            'shipping_address' => [],
            'subtotal_price' => 3000,
            'tax_amount' => 479,
            'shipping_price' => 0,
            'total_price' => 3000,
        ]);
        $rewardVoucher = $referralService->processOrderReferralReward($friendOrder);
        $this->assertNotNull($rewardVoucher);
        $this->assertSame(500, (int) $rewardVoucher->current_balance);

        $reversed = $referralService->reverseOrderReferralReward($friendOrder);
        $this->assertTrue($reversed);
        $this->assertSame(0, (int) $rewardVoucher->fresh()->current_balance);

        // 6. DigitalProductDownloadController must block paths outside produkte/products-secure/
        \Illuminate\Support\Facades\Storage::disk('local')->put('buchhaltung/invoices/secret-r17.pdf', 'SECRET-INVOICE');
        $badDigitalProduct = \App\Models\Product\Product::create([
            'name' => 'Bad Digital R17',
            'slug' => 'bad-digital-r17',
            'price' => 1000,
            'status' => 'active',
            'type' => 'digital',
            'digital_download_path' => 'buchhaltung/invoices/secret-r17.pdf',
            'digital_filename' => 'download.pdf',
        ]);
        $badOrderItem = \App\Models\Order\OrderOrderItem::create([
            'order_id' => $selfOrder->id,
            'product_id' => $badDigitalProduct->id,
            'product_name' => $badDigitalProduct->name,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);

        $this->actingAs($referrer, 'customer')
            ->get("/digital-product/{$badOrderItem->id}/download")
            ->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_enforce_password_change_2fa_support_chats_tickets_svg_vectors_and_crawler_urls(): void
    {
        // 1. EnforcePasswordChange middleware must redirect GET and block POST/JSON when needs_password_change is true
        $forcedCustomer = \App\Models\Customer\Customer::create([
            'first_name' => 'Forced',
            'last_name' => 'Password',
            'email' => 'forced-r18@example.com',
            'password' => bcrypt('TempPass123!'),
            'needs_password_change' => true,
            'temporary_password' => 'TempPass123!',
        ]);

        $this->actingAs($forcedCustomer, 'customer')
            ->get('/customer/dashboard')
            ->assertRedirect(route('customer.password-change-force'));

        $middleware = new \App\Http\Middleware\EnforcePasswordChange();
        $postReq = \Illuminate\Http\Request::create('/customer/dashboard', 'POST');
        $aborted403 = false;
        try {
            $middleware->handle($postReq, fn () => response('ok'));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $aborted403 = ($e->getStatusCode() === 403);
        }
        $this->assertTrue($aborted403, 'EnforcePasswordChange must abort 403 on non-GET requests.');

        // 2. AuthPasswordReset::submit() must clear needs_password_change and temporary_password
        $rawToken = 'r18-reset-token-secret-1234567890123456789012345678901234567890';
        \Illuminate\Support\Facades\DB::table('system_password_reset_tokens')->insert([
            'email' => $forcedCustomer->email,
            'guard' => 'customer',
            'token' => bcrypt($rawToken),
            'created_at' => now(),
        ]);

        \Livewire\Livewire::test(\App\Livewire\Auth\AuthPasswordReset::class, ['token' => $rawToken])
            ->set('email', $forcedCustomer->email)
            ->set('password', 'NewStrongPass99')
            ->set('passwordConfirm', 'NewStrongPass99')
            ->call('submit')
            ->assertRedirect(route('login'));

        $forcedCustomer->refresh();
        $this->assertFalse((bool) $forcedCustomer->needs_password_change);
        $this->assertNull($forcedCustomer->temporary_password);

        // 3. AuthProfileDropdown 2FA deactivation and re-activation in the same component lifecycle must generate a fresh non-null secret
        $forcedCustomer->profile->update([
            'two_factor_is_active' => true,
            'two_factor_secret' => (new \PragmaRX\Google2FA\Google2FA())->generateSecretKey(),
        ]);

        $this->actingAs($forcedCustomer, 'customer');
        $dropdownTest = \Livewire\Livewire::test(\App\Livewire\Auth\AuthProfileDropdown::class)
            ->call('deActivate')
            ->set('password', 'NewStrongPass99')
            ->call('activate');

        $forcedCustomer->profile->refresh();
        $this->assertTrue((bool) $forcedCustomer->profile->two_factor_is_active);
        $this->assertNotEmpty($forcedCustomer->profile->two_factor_secret);

        // 4. AiFrontendSupportChat::startNewChat() as guest must not close a chat already claimed by a customer
        \Illuminate\Support\Facades\Auth::guard('customer')->logout();
        $sharedToken = 'shared-session-token-1234567890';
        $ownedChat = \App\Models\Support\SupportCustomerChat::create([
            'customer_id' => $forcedCustomer->id,
            'session_token' => $sharedToken,
            'status' => 'open',
        ]);
        $guestChat = \App\Models\Support\SupportCustomerChat::create([
            'customer_id' => null,
            'session_token' => $sharedToken,
            'status' => 'open',
        ]);

        \Illuminate\Support\Facades\Cookie::queue('sf_chat_uuid', $sharedToken);
        request()->cookies->set('sf_chat_uuid', $sharedToken);
        session(['current_chat_id' => $guestChat->id]);

        \Livewire\Livewire::test(\App\Livewire\Frontend\Support\AiFrontendSupportChat::class)
            ->call('startNewChat');

        $this->assertSame('open', $ownedChat->fresh()->status, 'Guest startNewChat must not close customer-owned chats.');
        $this->assertSame('closed', $guestChat->fresh()->status, 'Guest startNewChat must close the guest chat.');
        $this->assertNull(session('current_chat_id'));

        // 5. CustomerTicketsComponent::$activeTicketId and SupportTicket::$activeTicketId must be #[Locked]
        $this->actingAs($forcedCustomer, 'customer');
        $ticketLockedThrown = false;
        try {
            \Livewire\Livewire::test(\App\Livewire\Customer\CustomerTicketsComponent::class)
                ->set('activeTicketId', '00000000-0000-0000-0000-000000000999');
        } catch (\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException $e) {
            $ticketLockedThrown = true;
        }
        $this->assertTrue($ticketLockedThrown, 'CustomerTicketsComponent::$activeTicketId must be #[Locked].');

        // 6. ProductConfiguratorService::sanitizeSvgContent() must strip <use>, <image>, external hrefs, and CSS @import/url()
        $configService = app(\App\Services\ProductConfiguratorService::class);
        $dirtySvg = '<svg xmlns="http://www.w3.org/2000/svg"><style>@import url("https://evil.test/x.css"); .a{fill:url("https://evil.test/p")}</style><use href="https://evil.test/s.svg#x"/><image xlink:href="https://evil.test/t.png"/><a href="https://evil.test/phish"><path d="M0 0"/></a></svg>';
        $cleanSvg = $configService->sanitizeSvgContent($dirtySvg);
        $this->assertStringNotContainsString('<use', $cleanSvg);
        $this->assertStringNotContainsString('<image', $cleanSvg);
        $this->assertStringNotContainsString('@import', $cleanSvg);
        $this->assertStringNotContainsString('evil.test', $cleanSvg);

        // 7. StealthCrawlerService::cleanUrl() must block external domains not matching defaultHost
        $crawler = app(\App\Services\StealthCrawlerService::class);
        $this->assertSame('', $crawler->cleanUrl('https://169.254.169.254/latest/meta-data/', 'www.etsy.com'));
        $this->assertSame('', $crawler->cleanUrl('https://evil-attacker.test/listing/123', 'www.etsy.com'));
        $this->assertSame('https://www.etsy.com/listing/123', $crawler->cleanUrl('https://www.etsy.com/listing/123', 'www.etsy.com'));
        $this->assertSame('https://de.etsy.com/listing/123', $crawler->cleanUrl('https://de.etsy.com/listing/123', 'www.etsy.com'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_order_cancellation_voucher_inflation_voucher_laundering_temp_password_and_livewire_locks(): void
    {
        // 1. Cancelling an UNPAID order must NOT inflate MarketingGiftVoucher balance or product stock
        $standardProduct = \App\Models\Product\Product::create([
            'name' => 'Standard Kerze R19',
            'slug' => 'standard-kerze-r19',
            'price' => 2500,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => false,
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => false,
            'quantity' => 10,
        ]);

        $giftVoucher = \App\Models\Marketing\MarketingGiftVoucher::create([
            'code' => 'SF-R19-TEST-5000',
            'recipient_name' => 'Buyer R19',
            'initial_value' => 5000,
            'current_balance' => 5000,
            'is_active' => true,
        ]);

        $unpaidOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R19-UNPAID',
            'email' => 'buyer-r19@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'stripe',
            'coupon_code' => $giftVoucher->code,
            'discount_amount' => 2500,
            'billing_address' => ['first_name' => 'Buyer', 'last_name' => 'R19'],
            'shipping_address' => [],
            'subtotal_price' => 2500,
            'tax_amount' => 399,
            'shipping_price' => 0,
            'total_price' => 0,
        ]);
        \App\Models\Order\OrderOrderItem::create([
            'order_id' => $unpaidOrder->id,
            'product_id' => $standardProduct->id,
            'product_name' => $standardProduct->name,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
        ]);

        $unpaidOrder->handleCancellation('Stornierung unbezahlt', 'pending', 'unpaid');
        $this->assertSame(5000, (int) $giftVoucher->fresh()->current_balance, 'Unpaid order cancellation must not inflate gift voucher balance.');
        $this->assertSame(10, (int) $standardProduct->fresh()->quantity, 'Unpaid order cancellation must not inflate product stock.');

        // 2. Paid order completion and refund must accurately deduct and restore stock & voucher balance once
        $paidOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R19-PAID',
            'email' => 'buyer-r19@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'stripe',
            'coupon_code' => $giftVoucher->code,
            'discount_amount' => 2500,
            'billing_address' => ['first_name' => 'Buyer', 'last_name' => 'R19'],
            'shipping_address' => [],
            'subtotal_price' => 2500,
            'tax_amount' => 399,
            'shipping_price' => 0,
            'total_price' => 0,
        ]);
        \App\Models\Order\OrderOrderItem::create([
            'order_id' => $paidOrder->id,
            'product_id' => $standardProduct->id,
            'product_name' => $standardProduct->name,
            'quantity' => 2,
            'unit_price' => 2500,
            'total_price' => 5000,
        ]);

        $paidOrder->completePayment('pi_r19_test');
        $this->assertSame(2500, (int) $giftVoucher->fresh()->current_balance);
        $this->assertSame(8, (int) $standardProduct->fresh()->quantity);

        $paidOrder->refresh();
        $paidOrder->handleCancellation('Erstattung', $paidOrder->status, $paidOrder->payment_status);
        $paidOrder->update(['status' => 'refunded', 'payment_status' => 'refunded']);
        $this->assertSame(5000, (int) $giftVoucher->fresh()->current_balance);
        $this->assertSame(10, (int) $standardProduct->fresh()->quantity);

        // Calling handleCancellation a second time on an already refunded order must be a no-op
        $paidOrder->refresh();
        $paidOrder->handleCancellation('Doppelter Webhook', $paidOrder->status, $paidOrder->payment_status);
        $this->assertSame(5000, (int) $giftVoucher->fresh()->current_balance);
        $this->assertSame(10, (int) $standardProduct->fresh()->quantity);

        // 3. CartService must block redeeming a MarketingGiftVoucher on a cart containing only gift vouchers (voucher laundering protection)
        $voucherProduct = \App\Models\Product\Product::firstOrCreate(
            ['slug' => 'geschenkgutschein'],
            [
                'name' => 'Geschenkgutschein',
                'price' => 2500,
                'status' => 'active',
                'type' => 'digital',
            ]
        );
        $cartService = app(\App\Services\CartService::class);
        $cartService->emptyCart();
        $cartService->addItem($voucherProduct, 1, ['is_gift_voucher' => true, 'amount_cents' => 2500]);

        $applyRes = $cartService->applyCoupon($giftVoucher->code);
        $this->assertFalse($applyRes['success']);

        // 4. FormatsECommerceData must not leak customer_temporary_password if order email differs from customer email
        $guestCustomer = \App\Models\Customer\Customer::create([
            'first_name' => 'Guest',
            'last_name' => 'Customer',
            'email' => 'guest-r19@example.com',
            'password' => bcrypt('TempSecret99'),
            'needs_password_change' => true,
            'temporary_password' => 'TempSecret99',
        ]);
        $mismatchOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R19-MISMATCH',
            'customer_id' => $guestCustomer->id,
            'email' => 'other-recipient-r19@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'billing_address' => ['first_name' => 'Guest', 'last_name' => 'Customer'],
            'shipping_address' => [],
            'subtotal_price' => 2500,
            'tax_amount' => 399,
            'shipping_price' => 0,
            'total_price' => 2500,
        ]);
        $formattedMismatch = $mismatchOrder->fresh(['customer'])->toFormattedArray();
        $this->assertNull($formattedMismatch['customer_temporary_password']);

        // 5. ContactForm must not expose WithFileUploads trait via handleMailsTrait
        $this->assertFalse(in_array(\Livewire\WithFileUploads::class, class_uses_recursive(\App\Livewire\Frontend\ContactForm::class), true));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_api_auth_twilio_checkout_ownership_configurator_order_edit_and_product_locks(): void
    {
        // 1. POST /api/login must reject customers with needs_password_change = true
        $tempCustomer = \App\Models\Customer\Customer::create([
            'first_name' => 'Temp',
            'last_name' => 'UserR20',
            'email' => 'temp-r20@example.com',
            'password' => bcrypt('TempPassR20!'),
            'needs_password_change' => true,
        ]);
        $tempCustomer->profile()->updateOrCreate(
            ['customer_id' => $tempCustomer->id],
            ['email_verified_at' => now()]
        );

        $loginResp = $this->postJson('/api/login', [
            'email' => 'temp-r20@example.com',
            'password' => 'TempPassR20!',
        ]);
        $loginResp->assertStatus(403)
            ->assertJson(['status' => 'password_change_required']);
        $this->assertSame(0, $tempCustomer->tokens()->count());

        // 2. Twilio outbound webhook must fail-closed when untrusted and must not leak cached context for unregistered CallSid
        config(['services.twilio.bridge_secret' => 'super-secret-bridge-token-r20']);
        config(['services.twilio.sid' => 'AC11111111111111111111111111111111']);
        \Illuminate\Support\Facades\Cache::put('twilio_call_+491709998887', [
            'contact_name' => 'Geheimer Kontakt',
            'calendar_events' => 'Geheimer Termin um 14:00 Uhr',
            'ai_learned_facts' => 'Geheime Fakten',
        ], 600);

        $unauthOutbound = $this->post('/api/twilio/outbound', [
            'To' => '+491709998887',
            'CallSid' => 'CAabcdef1234567890abcdef1234567890',
        ]);
        $unauthOutbound->assertStatus(403);
        $this->assertNotNull(\Illuminate\Support\Facades\Cache::get('twilio_call_+491709998887'));

        // Even with valid AccountSid, unregistered CallSid must NOT pull cached context
        $unregisteredSidResp = $this->post('/api/twilio/outbound', [
            'AccountSid' => 'AC11111111111111111111111111111111',
            'To' => '+491709998887',
            'CallSid' => 'CAabcdef1234567890abcdef1234567890',
        ]);
        $unregisteredSidResp->assertStatus(200);
        $this->assertStringNotContainsString('Geheimer Termin', $unregisteredSidResp->getContent());
        $this->assertNotNull(\Illuminate\Support\Facades\Cache::get('twilio_call_+491709998887'));

        // Once CallSid is registered in SupportTelephonyCall, context is delivered
        \App\Models\SupportTelephonyCall::create([
            'twilio_sid' => 'CAabcdef1234567890abcdef1234567890',
            'contact_name' => 'Geheimer Kontakt',
            'phone' => '+491709998887',
            'objective' => 'Test',
            'status' => 'ongoing',
        ]);
        $registeredSidResp = $this->post('/api/twilio/outbound', [
            'AccountSid' => 'AC11111111111111111111111111111111',
            'To' => '+491709998887',
            'CallSid' => 'CAabcdef1234567890abcdef1234567890',
        ]);
        $registeredSidResp->assertStatus(200);
        $this->assertStringContainsString('Geheimer Termin', $registeredSidResp->getContent());

        // 3. Checkout handlePaymentSuccess must not allow guest ownership bypass via mutable $this->email
        $confProduct = \App\Models\Product\Product::create([
            'name' => 'Konfigurator Produkt R20',
            'slug' => 'konfigurator-produkt-r20',
            'price' => 1990,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'variants_data' => [
                ['id' => 'var_basic', 'name' => 'Basic', 'price' => 19.90, 'is_active' => true],
                ['id' => 'var_deluxe', 'name' => 'Deluxe Gold', 'price' => 99.90, 'is_active' => true],
            ],
        ]);
        app(\App\Services\CartService::class)->addItem($confProduct, 1);

        $victimOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R20-VICTIM',
            'email' => 'victim-r20@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'stripe',
            'billing_address' => ['first_name' => 'Victim', 'last_name' => 'R20'],
            'shipping_address' => [],
            'subtotal_price' => 2500,
            'discount_amount' => 2500,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 0,
        ]);

        \Livewire\Livewire::test(\App\Livewire\Shop\Order\OrderCheckout\OrderCheckout::class)
            ->set('email', 'victim-r20@example.com')
            ->call('handlePaymentSuccess', $victimOrder->id)
            ->assertHasErrors(['payment']);
        $this->assertSame('unpaid', $victimOrder->fresh()->payment_status);

        // 4. ProductConfigurator in order_edit mode must preserve commercial parameters (variant_id, is_express, qty) for customers
        $ownerCustomer = \App\Models\Customer\Customer::create([
            'first_name' => 'Owner',
            'last_name' => 'R20',
            'email' => 'owner-r20@example.com',
            'password' => bcrypt('Secret123!'),
        ]);
        $pendingOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R20-EDIT',
            'customer_id' => $ownerCustomer->id,
            'email' => $ownerCustomer->email,
            'status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'is_express' => false,
            'billing_address' => ['first_name' => 'Owner', 'last_name' => 'R20'],
            'shipping_address' => [],
            'subtotal_price' => 1990,
            'tax_amount' => 318,
            'shipping_price' => 0,
            'total_price' => 1990,
        ]);
        $pendingItem = \App\Models\Order\OrderOrderItem::create([
            'order_id' => $pendingOrder->id,
            'product_id' => $confProduct->id,
            'product_name' => $confProduct->name,
            'quantity' => 1,
            'unit_price' => 1990,
            'total_price' => 1990,
            'configuration' => [
                'variant_id' => 'var_basic',
                'variant_name' => 'Basic',
                'is_express' => false,
                'qty' => 1,
                'notes' => 'Original Notiz',
            ],
        ]);

        \Livewire\Livewire::actingAs($ownerCustomer, 'customer')
            ->test(\App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator::class, [
                'product' => $confProduct,
                'context' => 'order_edit',
                'cartItem' => $pendingItem,
            ])
            ->set('variantId', 'var_deluxe')
            ->set('variantName', 'Deluxe Gold')
            ->set('is_express', true)
            ->set('qty', 50)
            ->set('notes', 'Neue Kunden-Notiz')
            ->set('config_confirmed', true)
            ->call('save');

        $updatedConfig = $pendingItem->fresh()->configuration;
        $this->assertSame('var_basic', $updatedConfig['variant_id']);
        $this->assertSame('Basic', $updatedConfig['variant_name']);
        $this->assertFalse($updatedConfig['is_express']);
        $this->assertSame(1, $updatedConfig['qty']);
        $this->assertSame('Neue Kunden-Notiz', $updatedConfig['notes']);

        // 5. MarketingVoucherBalanceChecker must block unauthenticated probing of personal DANKE- vouchers
        \App\Models\Marketing\MarketingGiftVoucher::create([
            'code' => 'DANKE-R20-PRIVATE',
            'recipient_name' => 'Owner R20',
            'recipient_email' => 'owner-r20@example.com',
            'initial_value' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);

        \Illuminate\Support\Facades\Auth::guard('customer')->logout();
        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherBalanceChecker::class)
            ->set('code', 'DANKE-R20-PRIVATE')
            ->call('checkBalance')
            ->assertHasErrors(['code'])
            ->assertSet('result', null);

        // 6. CustomerTicketsComponent::sendReply() must not reopen closed tickets
        $closedTicket = \App\Models\Support\SupportTicket::create([
            'ticket_number' => 'MSF-R20-CLSD',
            'customer_id' => $ownerCustomer->id,
            'subject' => 'Geschlossenes Ticket',
            'category' => 'support',
            'status' => 'closed',
        ]);

        \Livewire\Livewire::actingAs($ownerCustomer, 'customer')
            ->test(\App\Livewire\Customer\CustomerTicketsComponent::class)
            ->call('setMode', 'chat', $closedTicket->id)
            ->set('chatMessage', 'Versuch der Wiedereröffnung')
            ->call('sendReply')
            ->assertHasErrors(['chatMessage']);
        $this->assertSame('closed', $closedTicket->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_order_overview_payment_completion_gobd_delete_quote_coupons_and_system_shop_config(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $admin = \App\Models\Admin\Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'R21',
            'email' => 'admin-r21@example.com',
            'password' => bcrypt('password123'),
        ]);
        $employee = \App\Models\Employee\Employee::create([
            'first_name' => 'Emp',
            'last_name' => 'R21',
            'email' => 'emp-r21@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 1. OrderOverview::markAsPaid() must execute completePayment() (reducing stock & deducting gift voucher balance)
        $stockProduct = \App\Models\Product\Product::create([
            'name' => 'R21 Stock Item',
            'slug' => 'r21-stock-item',
            'price' => 2500,
            'status' => 'active',
            'type' => 'physical',
            'track_quantity' => true,
            'quantity' => 10,
        ]);
        $giftVoucher = \App\Models\Marketing\MarketingGiftVoucher::create([
            'code' => 'GV-R21-BALANCE',
            'recipient_name' => 'Buyer R21',
            'initial_value' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);

        $unpaidOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R21-MARKPAID',
            'email' => 'buyer-r21@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'invoice',
            'is_express' => false,
            'billing_address' => ['first_name' => 'Buyer', 'last_name' => 'R21'],
            'shipping_address' => [],
            'subtotal_price' => 2500,
            'tax_amount' => 399,
            'shipping_price' => 0,
            'discount_amount' => 1000,
            'coupon_code' => 'GV-R21-BALANCE',
            'total_price' => 1500,
        ]);
        \App\Models\Order\OrderOrderItem::create([
            'order_id' => $unpaidOrder->id,
            'product_id' => $stockProduct->id,
            'product_name' => $stockProduct->name,
            'quantity' => 2,
            'unit_price' => 2500,
            'total_price' => 5000,
        ]);

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Order\OrderOverview::class)
            ->call('markAsPaid', $unpaidOrder->id);

        $this->assertSame('paid', $unpaidOrder->fresh()->payment_status);
        $this->assertSame(8, $stockProduct->fresh()->quantity);
        $this->assertSame(0, (int) $giftVoucher->fresh()->current_balance);

        // 2. OrderOverview::delete() must block employees (403) and block paid orders for admins (GoBD immutability)
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        \Livewire\Livewire::actingAs($employee, 'employee')
            ->test(\App\Livewire\Shop\Order\OrderOverview::class)
            ->call('delete', $unpaidOrder->id)
            ->assertForbidden();

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Order\OrderOverview::class)
            ->call('delete', $unpaidOrder->id);
        $this->assertNotNull($unpaidOrder->fresh(), 'Paid order must not be deleted (GoBD immutability).');

        // 3. SystemShopConfig must redact stripe_secret_key & stripe_webhook_secret in mount() and protect is_small_business in save()
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'stripe_secret_key'], ['value' => 'sk_live_SUPER_SECRET_R21']);
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'stripe_webhook_secret'], ['value' => 'whsec_SUPER_SECRET_R21']);
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'is_small_business'], ['value' => 'false']);
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'owner_email'], ['value' => 'info@mein-seelenfunke.de']);

        $shopConfigTest = \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\System\SystemShopConfig::class);

        $this->assertSame('', $shopConfigTest->get('settings.stripe_secret_key'));
        $this->assertSame('', $shopConfigTest->get('settings.stripe_webhook_secret'));

        $shopConfigTest
            ->set('settings.is_small_business', true)
            ->call('save');

        // Direct save() must not overwrite is_small_business without confirmSmallBusinessTransition()
        $this->assertSame('false', \App\Models\System\SystemSetting::where('key', 'is_small_business')->value('value'));
        // And empty redacted stripe_secret_key in settings must not wipe the stored secret in DB
        $this->assertSame('sk_live_SUPER_SECRET_R21', \App\Models\System\SystemSetting::where('key', 'stripe_secret_key')->value('value'));

        // 4. MarketingVoucher::deleteManualCoupon() must not delete personal reward vouchers (recipient_email !== null)
        $personalRewardVoucher = \App\Models\Marketing\MarketingVoucher::create([
            'code' => 'DANKE-R21-REWARD',
            'title' => 'Personal Reward',
            'type' => 'fixed',
            'value' => 1000,
            'mode' => 'manual',
            'recipient_email' => 'vip@example.com',
            'is_active' => true,
        ]);

        try {
            \Livewire\Livewire::actingAs($admin, 'admin')
                ->test(\App\Livewire\Shop\Marketing\MarketingVoucher::class)
                ->call('deleteManualCoupon', $personalRewardVoucher->id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Expected
        }
        $this->assertNotNull($personalRewardVoucher->fresh());

        // 5. MarketingVoucherPurchasePage::setAmount() must reject arbitrary tampered preset amounts
        \App\Models\Product\Product::updateOrCreate(
            ['slug' => 'geschenkgutschein'],
            ['name' => 'Geschenkgutschein', 'price' => 5000, 'status' => 'active', 'type' => 'digital']
        );

        $voucherPage = \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherPurchasePage::class)
            ->call('setAmount', -999);
        $this->assertSame(50, $voucherPage->get('amount'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_accounting_credits_invoices_fix_costs_and_management_components(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');

        $admin = \App\Models\Admin\Admin::factory()->create();

        // 1. AccountingCredit::generateCreditNote() must enforce 0% tax under § 19 UStG (is_small_business = true)
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'is_small_business'], ['value' => 'true']);
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Accounting\AccountingCredit::class)
            ->set('newCredit.subject', 'Kulanz-Gutschrift R22')
            ->set('newCredit.invoice_date', now()->format('Y-m-d'))
            ->set('creditItems', [
                ['name' => 'Erstattung Position', 'quantity' => 1, 'unit_price' => 100.00, 'tax_rate' => 19],
            ])
            ->call('generateCreditNote')
            ->assertHasNoErrors();

        $creditNote = \App\Models\Accounting\AccountingInvoice::where('type', 'credit_note')
            ->where('subject', 'Kulanz-Gutschrift R22')
            ->firstOrFail();

        $this->assertTrue((bool) $creditNote->is_small_business);
        $this->assertSame(0, (int) $creditNote->tax_amount);
        $this->assertSame(-10000, (int) $creditNote->subtotal);
        $this->assertSame(-10000, (int) $creditNote->total);

        // 2. AccountingInvoice::saveManualInvoice() clamps oversized discounts to 0 and sets paid_at & is_small_business
        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Accounting\AccountingInvoice::class)
            ->set('manualInvoice.customer_email', 'kunde-r22@example.com')
            ->set('manualInvoice.first_name', 'Max')
            ->set('manualInvoice.last_name', 'Mustermann')
            ->set('manualInvoice.address', 'Musterstr. 1')
            ->set('manualInvoice.postal_code', '12345')
            ->set('manualInvoice.city', 'Berlin')
            ->set('manualInvoice.invoice_date', now()->format('Y-m-d'))
            ->set('manualInvoice.delivery_date', now()->format('Y-m-d'))
            ->set('manualInvoice.invoice_number', 'RE-R22-9999')
            ->set('manualInvoice.discount_amount', 500.00)
            ->set('manualInvoice.subject', 'Manuelle Rechnung R22')
            ->set('manualInvoice.items', [
                ['product_name' => 'Beratung', 'quantity' => 1, 'unit_price' => 100.00, 'tax_rate' => 19],
            ])
            ->call('saveManualInvoice', 'paid')
            ->assertHasNoErrors();

        $manualInvoice = \App\Models\Accounting\AccountingInvoice::where('subject', 'Manuelle Rechnung R22')->firstOrFail();
        $this->assertSame(0, (int) $manualInvoice->total);
        $this->assertNotNull($manualInvoice->paid_at);
        $this->assertTrue((bool) $manualInvoice->is_small_business);

        // Attempting to cancel a credit_note via AccountingInvoice::cancelInvoice() must be blocked
        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Accounting\AccountingInvoice::class)
            ->call('cancelInvoice', $creditNote->id);

        $this->assertSame('paid', $creditNote->fresh()->status);
        $this->assertFalse(\App\Models\Accounting\AccountingInvoice::where('parent_id', $creditNote->id)->exists());

        // 3. AccountingFixCosts::restoreHistory() updates the existing cost item when editingItemId is initially null
        $group = \App\Models\Accounting\AccountingGroup::create([
            'admin_id' => $admin->id,
            'name' => 'Fixkosten R22',
            'type' => 'expense',
            'position' => 1,
        ]);

        $costItem = \App\Models\Accounting\AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Aktueller Tarif',
            'amount' => 49.90,
            'interval_months' => 1,
            'first_payment_date' => '2026-01-01',
            'is_business' => true,
            'tax_rate' => 19,
        ]);

        $historyEntry = \App\Models\Accounting\AccountingCostItemHistory::create([
            'accounting_cost_item_id' => $costItem->id,
            'accounting_group_id' => $group->id,
            'name' => 'Alter Tarif R22',
            'amount' => 29.90,
            'interval_months' => 1,
            'first_payment_date' => '2025-01-01',
            'is_business' => true,
            'tax_rate' => 19,
        ]);

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Accounting\AccountingFixCosts::class)
            ->call('restoreHistory', $historyEntry->id)
            ->assertHasNoErrors();

        $this->assertSame('Alter Tarif R22', $costItem->fresh()->name);
        $this->assertEquals(29.90, (float) $costItem->fresh()->amount);
        $this->assertSame(1, \App\Models\Accounting\AccountingCostItem::where('accounting_group_id', $group->id)->count());

        // 4. AccountingVariableCosts::confirmDeleteCategory() blocks self-move deletion
        $category = \App\Models\Accounting\AccountingCategory::create([
            'admin_id' => $admin->id,
            'name' => 'Büromaterial R22',
            'is_business' => true,
        ]);

        $specialIssue = \App\Models\Accounting\AccountingSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'Druckerpapier',
            'category' => 'Büromaterial R22',
            'amount' => -15.00,
            'execution_date' => now()->format('Y-m-d'),
            'is_business' => true,
            'tax_rate' => 19,
        ]);

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Accounting\AccountingVariableCosts::class)
            ->call('deleteCategory', $category->id)
            ->set('targetCategoryId', $category->id)
            ->call('confirmDeleteCategory');

        $this->assertNotNull($category->fresh());
        $this->assertSame('Büromaterial R22', $specialIssue->fresh()->category);

        // 5. ManagementCalender ICS import without UID must not overwrite manual events (ics_uid = null)
        $manualEvent = \App\Models\Management\ManagementCalendarEvent::create([
            'title' => 'Wichtiger Manueller Termin R22',
            'start_date' => now()->startOfDay(),
            'end_date' => now()->endOfDay(),
            'is_all_day' => true,
            'category' => 'meeting',
            'priority' => 'high',
            'ics_uid' => null,
        ]);

        $icsContent = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:Importierter Termin Ohne UID\r\nDTSTART:20261115T100000Z\r\nDTEND:20261115T110000Z\r\nEND:VEVENT\r\nEND:VCALENDAR";
        $icsFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('termine.ics', $icsContent);

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Management\ManagementCalender::class)
            ->set('importFile', $icsFile)
            ->call('importEvents')
            ->assertHasNoErrors();

        $this->assertNotNull($manualEvent->fresh());
        $this->assertSame('Wichtiger Manueller Termin R22', $manualEvent->fresh()->title);
        $importedEvent = \App\Models\Management\ManagementCalendarEvent::where('title', 'Importierter Termin Ohne UID')->first();
        $this->assertNotNull($importedEvent);
        $this->assertStringStartsWith('ics_', (string) $importedEvent->ics_uid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_customer_draft_invoices_gamification_revocations_and_product_management(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Mail::fake();

        $admin = $this->admin;
        $employee = $this->employee;
        $customer = \App\Models\Customer\Customer::factory()->create([
            'email' => 'customer-r23@example.com',
        ]);

        // 1. Draft invoices must not be visible or downloadable by customers
        $draftInvoice = \App\Models\Accounting\AccountingInvoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'RE-R23-DRAFT',
            'type' => 'invoice',
            'status' => 'draft',
            'invoice_date' => now(),
            'subtotal' => 5000,
            'tax_amount' => 950,
            'total' => 5950,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'R23'],
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('invoice.download', $draftInvoice->id))
            ->assertForbidden();

        \Livewire\Livewire::actingAs($customer, 'customer')
            ->test(\App\Livewire\Customer\CustomerInvoicesComponent::class)
            ->assertDontSee('RE-R23-DRAFT');

        // 2. GamificationService & CustomerDashboardComponent must ignore cancelled paid orders and require active opt-in for upgrades
        $cancelledPaidOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R23-CANCELLED',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'cancelled',
            'payment_status' => 'paid',
            'subtotal_price' => 3000,
            'tax_amount' => 570,
            'total_price' => 3000,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'R23'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'R23'],
        ]);

        $gameProfile = \App\Models\Customer\CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => false,
            'level' => 1,
            'funken_balance' => 500,
            'energy_balance' => 5,
        ]);

        $gameService = app(\App\Services\Gamification\GamificationService::class);
        $upgradeResult = $gameService->upgradeLevel($customer);
        $this->assertFalse($upgradeResult['success']);
        $this->assertSame(1, $gameProfile->fresh()->level);

        $gameService->syncDynamicTitles($gameProfile);
        $this->assertSame(0, (int) ($gameProfile->fresh()->titles_progress['schatzhueter'] ?? 0));

        $dashboard = \Livewire\Livewire::actingAs($customer, 'customer')
            ->test(\App\Livewire\Customer\CustomerDashboardComponent::class);
        $this->assertFalse($dashboard->get('hasCompletedOrder'));
        $this->assertSame('', $dashboard->get('referralCode'));

        // 3. OrderRevocations: email verification before order cancellation, GoBD invoice cancellation, and admin-only delete
        $activeOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R23-REV',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal_price' => 4000,
            'tax_amount' => 760,
            'total_price' => 4000,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'R23', 'address' => 'Str 1', 'postal_code' => '12345', 'city' => 'Berlin', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'R23'],
        ]);

        $paidInvoice = \App\Models\Accounting\AccountingInvoice::create([
            'order_id' => $activeOrder->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'RE-R23-ACTIVE',
            'type' => 'invoice',
            'status' => 'paid',
            'paid_at' => now(),
            'invoice_date' => now(),
            'subtotal' => 4000,
            'tax_amount' => 760,
            'total' => 4000,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'R23', 'address' => 'Str 1', 'postal_code' => '12345', 'city' => 'Berlin', 'country' => 'DE'],
        ]);

        $spoofedRevocation = \App\Models\Order\OrderRevocation::create([
            'name' => 'Attacker',
            'email' => 'attacker-r23@evil.com',
            'order_number' => 'ORD-R23-REV',
            'status' => 'pending',
        ]);

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Order\OrderRevocations::class)
            ->call('markAsProcessed', $spoofedRevocation->id);

        // Order must NOT be cancelled when revocation email does not match order/customer email
        $this->assertSame('processing', $activeOrder->fresh()->status);
        $this->assertSame('paid', $paidInvoice->fresh()->status);

        // Employee must NOT be allowed to permanently delete revocation records
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        \Livewire\Livewire::actingAs($employee, 'employee')
            ->test(\App\Livewire\Shop\Order\OrderRevocations::class)
            ->call('deleteRevocation', $spoofedRevocation->id)
            ->assertForbidden();

        // Legitimate revocation with matching email cancels both the order and the paid invoice (GoBD storno)
        $legitRevocation = \App\Models\Order\OrderRevocation::create([
            'name' => 'Max R23',
            'email' => 'customer-r23@example.com',
            'order_number' => 'ORD-R23-REV',
            'status' => 'pending',
        ]);

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Order\OrderRevocations::class)
            ->call('markAsProcessed', $legitRevocation->id);

        $this->assertSame('cancelled', $activeOrder->fresh()->status);
        $this->assertSame('refunded', $activeOrder->fresh()->payment_status);
        $this->assertSame('cancelled', $paidInvoice->fresh()->status);
        $this->assertTrue(\App\Models\Accounting\AccountingInvoice::where('parent_id', $paidInvoice->id)->where('type', 'cancellation')->exists());

        // 4. ProductVariants matrix cap & ProductCreate 3D model glTF magic-byte check
        $product = \App\Models\Product\Product::create([
            'name' => 'Test Produkt R23',
            'slug' => 'test-produkt-r23',
            'sku' => 'SKU-R23',
            'price' => 2500,
            'status' => 'draft',
            'type' => 'physical',
            'attributes' => [
                'A1' => '1,2,3,4,5,6,7,8,9,10',
                'A2' => '1,2,3,4,5,6,7,8,9,10',
                'A3' => '1,2,3,4,5',
            ],
        ]);

        $variantsComp = \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Product\ProductVariants::class, ['product' => $product])
            ->call('generateMatrix');
        $this->assertEmpty($variantsComp->get('variants'));

        $fakeGlb = \Illuminate\Http\UploadedFile::fake()->createWithContent('exploit.glb', '<script>alert(1)</script>');
        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Product\ProductCreate::class)
            ->call('edit', $product->id)
            ->set('new_3d_model', $fakeGlb)
            ->assertHasErrors(['new_3d_model']);
        $this->assertNull($product->fresh()->three_d_model_path);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_newsletter_automation_product_attributes_herzensprojekte_and_system_commands(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $admin = $this->admin;
        $customer = \App\Models\Customer\Customer::factory()->create([
            'email' => 'customer-r24@example.com',
            'email_verified_at' => null,
        ]);

        // 1. SendNewsletter command must not send future manual campaigns today, must archive sent manual campaigns, and must be idempotent
        \App\Models\Marketing\MarketingNewsletterSubscriber::create([
            'email' => 'sub-r24@example.com',
            'is_verified' => true,
            'privacy_accepted' => true,
        ]);

        $futureManual = \App\Models\Marketing\MarketingNewsletter::create([
            'type' => 'manual',
            'title' => 'Future Manual R24',
            'target_event_key' => null,
            'subject' => 'Future Subject R24',
            'content' => '<p>Future</p>',
            'send_at' => now()->addDays(15),
            'days_offset' => 0,
            'is_active' => true,
        ]);

        $todayManual = \App\Models\Marketing\MarketingNewsletter::create([
            'type' => 'manual',
            'title' => 'Today Manual R24',
            'target_event_key' => null,
            'subject' => 'Today Subject R24',
            'content' => '<p>Today</p>',
            'send_at' => now()->subMinute(),
            'days_offset' => 0,
            'is_active' => true,
        ]);

        \Illuminate\Support\Facades\Artisan::call('send-newsletters');

        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\NewsletterAutomaticMail::class, 1);
        $this->assertTrue((bool) $futureManual->fresh()->is_active);
        $this->assertFalse((bool) $todayManual->fresh()->is_active);

        // Running a second time on the same day must not send duplicates
        \Illuminate\Support\Facades\Artisan::call('send-newsletters');
        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\NewsletterAutomaticMail::class, 1);

        // 2. ProductAttributes validation against product_attributes table & whitelist check on toggleAttribute
        $product = \App\Models\Product\Product::create([
            'name' => 'Produkt R24',
            'slug' => 'produkt-r24',
            'sku' => 'SKU-R24',
            'price' => 1999,
            'status' => 'active',
            'type' => 'physical',
        ]);

        $attrComp = \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Product\ProductAttributes::class, ['product' => $product])
            ->set('newAttributeName', 'Material R24')
            ->call('createAttribute')
            ->assertHasNoErrors();

        $this->assertTrue(\App\Models\Product\ProductAttribute::where('name', 'Material R24')->exists());

        $attrComp->call('toggleAttribute', 'UnbekanntesAttributR24');
        $this->assertArrayNotHasKey('UnbekanntesAttributR24', $product->fresh()->attributes ?? []);

        $attrComp->call('toggleAttribute', 'Material R24');
        $this->assertArrayHasKey('Material R24', $product->fresh()->attributes ?? []);

        // 3. MarketingHerzensprojekte pot balance check before disbursement & deletion lock after disbursement
        $action = \App\Models\Marketing\MarketingHerzensprojektAction::create([
            'title' => 'Tierheim Spende R24',
            'target_recipient' => 'Tierheim Gifhorn',
            'allocated_amount_cents' => 5000,
            'status' => 'planned',
            'is_public' => true,
        ]);

        $herzenComp = \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Marketing\MarketingHerzensprojekte::class)
            ->call('disburseForAction', $action->id);

        // Pot is 0, so disbursement of 50.00 EUR must be blocked
        $this->assertSame('planned', $action->fresh()->status);
        $this->assertFalse(\App\Models\Marketing\MarketingHerzensprojektTransaction::where('action_id', $action->id)->exists());

        // Deposit 60.00 EUR and disburse
        $herzenComp->set('depositAmountEuro', '60')
            ->set('depositNote', 'Startguthaben R24')
            ->call('saveDeposit')
            ->call('disburseForAction', $action->id);

        $this->assertSame('completed', $action->fresh()->status);
        $this->assertTrue(\App\Models\Marketing\MarketingHerzensprojektTransaction::where('action_id', $action->id)->where('type', 'disbursement')->exists());

        // Disbursed action must not be deletable
        $herzenComp->call('deleteAction', $action->id);
        $this->assertNotNull($action->fresh());

        // 4. OrderShoppingCarts::sendReminderEmail must require Double-Opt-In consent when cart_abandoned_require_consent is enabled
        $cart = \App\Models\Cart\Cart::create([
            'customer_id' => $customer->id,
            'session_id' => 'sess-r24',
        ]);
        \App\Models\Cart\CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1999,
        ]);

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Order\OrderShoppingCarts::class)
            ->call('sendReminderEmail', $cart->id);

        \Illuminate\Support\Facades\Mail::assertNotSent(\App\Mail\AbandonedCartReminder::class);
        $this->assertNull($cart->fresh()->reminder_email_sent_at);

        // 5. Customer::markEmailAsVerified sync, DeleteUnverifiedUsers protection for archived verified users, and remember_token rotation in SystemUserManagement
        $customer->forceFill(['created_at' => now()->subDays(3), 'remember_token' => 'old_token_r24'])->save();
        $customer->markEmailAsVerified();
        $this->assertNotNull($customer->fresh()->email_verified_at);
        $this->assertNotNull($customer->fresh()->profile->email_verified_at);

        // Archive the verified customer and run system:delete-unverified-users -> must NOT forceDelete archived verified customer
        $customer->delete();
        \Illuminate\Support\Facades\Artisan::call('system:delete-unverified-users');
        $this->assertNotNull(\App\Models\Customer\Customer::withTrashed()->find($customer->id));

        // Restore and change password via SystemUserManagement -> must rotate remember_token
        $customer->restore();
        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\System\SystemUserManagement::class)
            ->call('startEdit', $customer->id, 'customer')
            ->set('formData.password', 'NewSecurePassR24!')
            ->call('saveInline')
            ->assertHasNoErrors();

        $this->assertNotSame('old_token_r24', $customer->fresh()->remember_token);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_accounting_xml_invoices_tax_fixed_costs_capacity_engine_and_background_tool_jobs(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Event::fake();

        $admin = $this->admin;
        $customer = \App\Models\Customer\Customer::factory()->create([
            'email' => 'buyer-r25@example.com',
        ]);

        // 1. AccountingXmlInvoiceService & InvoiceService::generateEInvoiceXml must include order items and express surcharge
        $order = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R25-001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'company' => 'Mustermann GmbH',
                'address' => 'Hauptstr. 1',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'shipping_address' => [],
            'subtotal_price' => 5000,
            'shipping_price' => 490,
            'express_price' => 990,
            'tax_amount' => 1035,
            'total_price' => 6480,
            'is_small_business' => false,
        ]);

        $product = \App\Models\Product\Product::create([
            'name' => 'Schieferplatte Gravur R25',
            'slug' => 'schieferplatte-gravur-r25',
            'sku' => 'SKU-R25-001',
            'price' => 2500,
            'status' => 'active',
            'type' => 'physical',
        ]);

        \App\Models\Order\OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Schieferplatte Gravur R25',
            'quantity' => 2,
            'unit_price' => 2500,
            'total_price' => 5000,
            'tax_rate' => 19.0,
        ]);

        $invoice = \App\Models\Accounting\AccountingInvoice::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'RE-2026-R2501',
            'type' => 'invoice',
            'status' => 'paid',
            'invoice_date' => now(),
            'delivery_date' => now(),
            'due_date' => now()->addDays(7),
            'paid_at' => now(),
            'billing_address' => $order->billing_address,
            'shipping_address' => [],
            'subtotal' => 5000,
            'shipping_cost' => 490,
            'express_price' => 990,
            'tax_amount' => 1035,
            'total' => 6480,
            'is_e_invoice' => true,
            'is_small_business' => false,
        ]);

        $xmlPath = app(\App\Services\InvoiceService::class)->generateEInvoiceXml($invoice);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($xmlPath);
        $xmlContent = \Illuminate\Support\Facades\Storage::disk('local')->get($xmlPath);
        $this->assertStringContainsString('Schieferplatte Gravur R25', $xmlContent);
        $this->assertStringContainsString('Versandkosten', $xmlContent);
        $this->assertStringContainsString('Priorisierte Fertigung (Express-Service)', $xmlContent);

        // 2. AccountingTax::getMonthData must ignore income groups and expired last_payment_date contracts in businessFixed
        $incomeGroup = \App\Models\Accounting\AccountingGroup::create([
            'admin_id' => $admin->id,
            'name' => 'Gewerbe Einnahmen R25',
            'type' => 'income',
            'position' => 1,
        ]);
        \App\Models\Accounting\AccountingCostItem::create([
            'accounting_group_id' => $incomeGroup->id,
            'name' => 'Untervermietung R25',
            'amount' => 500.00,
            'tax_rate' => 19,
            'interval_months' => 1,
            'is_business' => true,
            'first_payment_date' => \Carbon\Carbon::create(2025, 1, 1),
        ]);

        $expenseGroup = \App\Models\Accounting\AccountingGroup::create([
            'admin_id' => $admin->id,
            'name' => 'Gewerbe Ausgaben R25',
            'type' => 'expense',
            'position' => 2,
        ]);
        \App\Models\Accounting\AccountingCostItem::create([
            'accounting_group_id' => $expenseGroup->id,
            'name' => 'Abgelaufener Vertrag R25',
            'amount' => 119.00,
            'tax_rate' => 19,
            'interval_months' => 1,
            'is_business' => true,
            'first_payment_date' => \Carbon\Carbon::create(2025, 1, 1),
            'last_payment_date' => \Carbon\Carbon::create(2025, 12, 31),
        ]);
        \App\Models\Accounting\AccountingCostItem::create([
            'accounting_group_id' => $expenseGroup->id,
            'name' => 'Aktiver Vertrag R25',
            'amount' => 119.00,
            'tax_rate' => 19,
            'interval_months' => 1,
            'is_business' => true,
            'first_payment_date' => \Carbon\Carbon::create(2025, 1, 1),
            'last_payment_date' => null,
        ]);

        $taxComp = \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Accounting\AccountingTax::class)
            ->set('selectedYear', 2026)
            ->call('selectMonth', 5);

        $monthData = $taxComp->instance()->getMonthData(5);
        $this->assertCount(1, $monthData['raw_fixed']);
        $this->assertSame('Aktiver Vertrag R25', $monthData['raw_fixed']->first()->name);
        $this->assertEqualsWithDelta(19.00, $monthData['vat_paid'], 0.01);

        // 3. ShopCapacityEngine must not re-enable DeliveryTime profiles while vacation mode is active
        \App\Models\Delivery\DeliveryTime::query()->update(['is_active' => false]);
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'shop_capacity_autopilot'], ['value' => 'true']);
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'is_vacation_mode'], ['value' => 'true']);
        \Illuminate\Support\Facades\Cache::put('shop_capacity_autopilot', true);

        \Illuminate\Support\Facades\Artisan::call('shop:capacity-engine');
        $this->assertSame(0, \App\Models\Delivery\DeliveryTime::where('is_active', true)->count());

        // 4. ExecuteAiBackgroundToolJob must block newly added high-risk background tools
        $bgTask = \App\Models\Ai\AiWorkspaceTask::create([
            'prompt' => 'Run terminal in background',
            'status' => 'pending',
        ]);

        $job = new \App\Jobs\ExecuteAiBackgroundToolJob($bgTask->id, 'server_execute_terminal', ['command' => 'whoami']);
        $job->handle();

        $this->assertSame('failed', $bgTask->fresh()->status);
        $this->assertStringContainsString('Sicherheits-Blockade', (string) $bgTask->fresh()->response_content);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_analytics_fixed_costs_gamification_categories_bank_api_and_customer_tickets(): void
    {
        $admin = \App\Models\Admin\Admin::create([
            'first_name' => 'Round26',
            'last_name' => 'Admin',
            'email' => 'round26-admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 1. AnalyticsService must classify positive AccountingCostItem in an 'expense' group as expense (fix_gewerbe) and skip expired contracts
        $expenseGroup = \App\Models\Accounting\AccountingGroup::create([
            'admin_id' => $admin->id,
            'name' => 'Gewerbe Fixkosten R26',
            'type' => 'expense',
            'position' => 1,
        ]);

        \App\Models\Accounting\AccountingCostItem::create([
            'accounting_group_id' => $expenseGroup->id,
            'name' => 'Werkstattmiete Aktiv R26',
            'amount' => 300.00,
            'tax_rate' => 19,
            'interval_months' => 1,
            'is_business' => true,
            'first_payment_date' => now()->subMonths(6)->startOfMonth(),
            'last_payment_date' => null,
        ]);

        \App\Models\Accounting\AccountingCostItem::create([
            'accounting_group_id' => $expenseGroup->id,
            'name' => 'Altvertrag Abgelaufen R26',
            'amount' => 900.00,
            'tax_rate' => 19,
            'interval_months' => 1,
            'is_business' => true,
            'first_payment_date' => now()->subYears(2)->startOfMonth(),
            'last_payment_date' => now()->subYear()->endOfMonth(),
        ]);

        $stats = app(\App\Services\AnalyticsService::class)->getStats(
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
            'business'
        );

        $this->assertGreaterThan(0, $stats['fixed_expenses_gew']);
        $this->assertLessThan(400, $stats['fixed_expenses_gew']);
        $this->assertEqualsWithDelta(0.0, $stats['fixed_income_total'], 0.01);

        // 2. GamificationService::syncDynamicTitles must count product categories via Many-to-Many categories() pivot for 'entdecker'
        $customer = \App\Models\Customer\Customer::create([
            'first_name' => 'Clara',
            'last_name' => 'Kunde',
            'email' => 'clara-r26@example.com',
            'password' => bcrypt('password123'),
        ]);

        $cat1 = \App\Models\Product\ProductCategory::create([
            'name' => 'Kristalle R26',
            'slug' => 'kristalle-r26',
            'type' => 'physical',
        ]);
        $cat2 = \App\Models\Product\ProductCategory::create([
            'name' => 'Schiefer R26',
            'slug' => 'schiefer-r26',
            'type' => 'physical',
        ]);

        $prod = \App\Models\Product\Product::create([
            'name' => 'Doppelkategorie Unikat R26',
            'slug' => 'doppelkategorie-unikat-r26',
            'sku' => 'SKU-R26-001',
            'price' => 3900,
            'status' => 'active',
            'type' => 'physical',
        ]);
        $prod->categories()->sync([$cat1->id, $cat2->id]);

        $order = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R26-001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Clara', 'last_name' => 'Kunde'],
            'shipping_address' => [],
            'subtotal_price' => 3900,
            'shipping_price' => 0,
            'tax_amount' => 623,
            'total_price' => 3900,
        ]);

        \App\Models\Order\OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $prod->id,
            'product_name' => $prod->name,
            'quantity' => 1,
            'unit_price' => 3900,
            'total_price' => 3900,
            'tax_rate' => 19.0,
        ]);

        $gameService = app(\App\Services\Gamification\GamificationService::class);
        $profile = $gameService->getProfile($customer);
        $gameService->syncDynamicTitles($profile);

        $this->assertSame(2, $profile->fresh()->titles_progress['entdecker'] ?? 0);

        // 3. BankApiService::createBankImportWebForm must reject external callback redirect URLs
        $bankService = app(\App\Services\BankApiService::class);
        $thrown = false;
        try {
            $bankService->createBankImportWebForm('dummy-token', 'https://evil-attacker.example.com/steal-callback');
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'External redirect URL in BankApiService::createBankImportWebForm must throw InvalidArgumentException');

        // 4. CustomerTicketsComponent::setMode must not retain foreign activeTicketId when mode !== 'chat'
        $otherCustomer = \App\Models\Customer\Customer::create([
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => 'other-r26@example.com',
            'password' => bcrypt('password123'),
        ]);
        $foreignTicket = \App\Models\Support\SupportTicket::create([
            'ticket_number' => 'MSF-26-FORGN',
            'customer_id' => $otherCustomer->id,
            'subject' => 'Fremdes Ticket',
            'category' => 'support',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        \Livewire\Livewire::actingAs($customer, 'customer')
            ->test(\App\Livewire\Customer\CustomerTicketsComponent::class)
            ->call('setMode', 'list', $foreignTicket->id)
            ->assertSet('activeTicketId', null)
            ->call('setMode', 'chat', $foreignTicket->id)
            ->assertSet('activeTicketId', null)
            ->assertSet('viewMode', 'list');

        // 5. AiFrontendSupportChat::sendMessage must not append messages to a closed chat
        $closedChat = \App\Models\Support\SupportCustomerChat::create([
            'customer_id' => $customer->id,
            'status' => 'closed',
        ]);
        session(['current_chat_id' => $closedChat->id, 'ai_chat_privacy_accepted' => true]);

        $chatComp = \Livewire\Livewire::actingAs($customer, 'customer')
            ->test(\App\Livewire\Frontend\Support\AiFrontendSupportChat::class);
        $chatComp->instance()->chatId = $closedChat->id;
        $chatComp->set('hasAcceptedPrivacy', true)
            ->set('message', 'Nachricht in geschlossenen Chat')
            ->call('sendMessage');

        $this->assertSame(0, \App\Models\Support\SupportCustomerChatMessage::where('support_customer_chat_id', $closedChat->id)->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_support_chats_error_logger_analytics_filters_and_review_title_sync(): void
    {
        // 1. SystemErrorLogger must redact API keys, tokens, passwords, and PINs from logged messages
        $log = \App\Services\System\SystemErrorLogger::recordLogEvent(
            'error',
            'HTTP request failed for https://generativelanguage.googleapis.com/v1beta/models/gemini:generateContent?key=AIzaSySuperSecretKey999&token=tok_secret_123',
            ['detail' => 'pin=987654']
        );
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('AIzaSySuperSecretKey999', $log->message);
        $this->assertStringNotContainsString('tok_secret_123', $log->message);
        $this->assertStringContainsString('[REDACTED]', $log->message);
        $this->assertStringNotContainsString('987654', json_encode($log->payload));

        // 2. Analytics components must normalize invalid dateRange and activeTab values
        $admin = \App\Models\Admin\Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'R27',
            'email' => 'admin-r27@example.com',
            'password' => bcrypt('password123'),
        ]);

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->withQueryParams(['dateRange' => 'invalid_injection', 'activeTab' => 'xss_tab'])
            ->test(\App\Livewire\Shop\Product\ProductAnalytics::class)
            ->assertSet('dateRange', '30')
            ->assertSet('activeTab', 'configurator')
            ->call('setTab', 'evil_tab')
            ->assertSet('activeTab', 'configurator')
            ->call('setTab', 'calculator')
            ->assertSet('activeTab', 'calculator');

        // 3. MarketingNewsletterPage::switchTab and ManagementCalender::setView must enforce whitelists
        \Livewire\Livewire::test(\App\Livewire\Shop\Marketing\MarketingNewsletterPage::class)
            ->call('switchTab', 'invalid_tab')
            ->assertSet('activeTab', 'subscribe')
            ->call('switchTab', 'unsubscribe')
            ->assertSet('activeTab', 'unsubscribe');

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Management\ManagementCalender::class)
            ->call('setView', 'invalid_view')
            ->assertSet('view', 'month')
            ->call('setView', 'week')
            ->assertSet('view', 'week');

        // 4. ProductReviews must sync 'botschafter' dynamically and prevent delete+resubmit farming
        $customer = \App\Models\Customer\Customer::create([
            'first_name' => 'Reviewer',
            'last_name' => 'R27',
            'email' => 'reviewer-r27@example.com',
            'password' => bcrypt('password123'),
        ]);

        $product = \App\Models\Product\Product::create([
            'name' => 'Bewertungs-Produkt R27',
            'slug' => 'bewertungs-produkt-r27-' . uniqid(),
            'price' => 2500,
            'status' => 'active',
            'type' => 'physical',
            'quantity' => 10,
        ]);

        $order = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R27-' . uniqid(),
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Reviewer', 'last_name' => 'R27'],
            'shipping_address' => [],
            'subtotal_price' => 2500,
            'shipping_price' => 0,
            'tax_amount' => 399,
            'total_price' => 2500,
        ]);

        \App\Models\Order\OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'tax_rate' => 19.0,
        ]);

        $reviewComp = \Livewire\Livewire::actingAs($customer, 'customer')
            ->test(\App\Livewire\Shop\Product\ProductReviews::class, ['product' => $product]);

        $reviewComp->set('rating', 5)
            ->set('title', 'Tolle Gravur')
            ->set('content', 'Sehr schöne Arbeit und schneller Versand!')
            ->call('submitReview');

        $profile = \App\Models\Customer\CustomerGamification::where('customer_id', $customer->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame(1, $profile->fresh()->titles_progress['botschafter'] ?? 0);

        // Delete review and submit again -> botschafter must remain 1 (not 2)
        $reviewComp->call('deleteReview');
        $this->assertSame(0, $profile->fresh()->titles_progress['botschafter'] ?? 0);

        $reviewComp->set('rating', 5)
            ->set('title', 'Zweiter Versuch')
            ->set('content', 'Immer noch eine sehr schöne Arbeit und schneller Versand!')
            ->call('submitReview');

        $this->assertSame(1, $profile->fresh()->titles_progress['botschafter'] ?? 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_tax_export_newsletter_year_boundary_ai_support_finances_and_configurator_service(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Mail::fake();

        $admin = \App\Models\Admin\Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'R28',
            'email' => 'admin-r28@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 1. AccountingTax & GenerateTaxExport must subtract credit notes and generate PDF without missing keys
        $order = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R28-TAX',
            'email' => 'buyer-r28@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Buyer', 'last_name' => 'R28'],
            'shipping_address' => [],
            'subtotal_price' => 10000,
            'shipping_price' => 0,
            'tax_amount' => 1900,
            'total_price' => 11900,
        ]);
        $order->forceFill(['created_at' => \Carbon\Carbon::create(2025, 9, 15, 12, 0, 0)])->save();

        // Credit note stored with positive total must still reduce revenueGross
        \App\Models\Accounting\AccountingInvoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'CN-R28-001',
            'type' => 'credit_note',
            'status' => 'paid',
            'invoice_date' => \Carbon\Carbon::create(2025, 9, 20),
            'subtotal' => 2000,
            'tax_amount' => 380,
            'total' => 2380,
            'billing_address' => ['first_name' => 'Buyer', 'last_name' => 'R28'],
        ]);

        $taxComp = \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Accounting\AccountingTax::class)
            ->set('selectedYear', 2025);
        $monthData = $taxComp->instance()->getMonthData(9);
        $this->assertEqualsWithDelta(95.20, $monthData['revenue_gross'], 0.01);

        $this->artisan('generate-tax-export', ['--month' => 9, '--year' => 2025, '--force' => true])
            ->assertExitCode(0);
        @unlink(storage_path('app/buchhaltung/tax_exports/TaxExport_2025_09.zip'));

        // 2. SendNewsletter must fire automated holiday templates across the New Year boundary (e.g. Jan 1 - 3 days = Dec 29)
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2026, 12, 29, 10, 0, 0));
        try {
            \App\Models\Marketing\MarketingNewsletterSubscriber::create([
                'email' => 'sub-r28@example.com',
                'is_verified' => true,
                'privacy_accepted' => true,
            ]);

            \App\Models\Marketing\MarketingNewsletter::create([
                'title' => 'Neujahrs-Countdown R28',
                'subject' => 'Bald ist Neujahr!',
                'content' => '<p>Frohes neues Jahr!</p>',
                'type' => 'automated',
                'target_event_key' => 'new_year',
                'days_offset' => 3,
                'is_active' => true,
            ]);

            $this->artisan('send-newsletters')->assertExitCode(0);
            \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\NewsletterAutomaticMail::class, 1);
        } finally {
            \Carbon\Carbon::setTestNow();
        }

        // 3. AiSupportService must exclude private special issues and expired fixed costs from business finances
        \App\Models\Accounting\AccountingSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'Gewerbliches Holz R28',
            'category' => 'Material',
            'amount' => -150.00,
            'is_business' => true,
            'execution_date' => now()->toDateString(),
        ]);
        \App\Models\Accounting\AccountingSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'Privater Einkauf R28',
            'category' => 'Privat',
            'amount' => -900.00,
            'is_business' => false,
            'execution_date' => now()->toDateString(),
        ]);

        $aiSupport = app(\App\Services\AI\AiSupportService::class);
        $cmdData = $aiSupport->getUltimateCommand();
        $this->assertEqualsWithDelta(-150.00, $cmdData['financial_status']['variable_costs_special_issues_total_eur'], 0.01);
        $this->assertEqualsWithDelta(-150.00, $cmdData['financial_status']['variable_costs_special_issues_month_eur'], 0.01);

        // 4. ProductConfiguratorService must reject path traversal in tutorial config and non-SVG uploads
        \App\Models\System\SystemSetting::updateOrCreate(
            ['key' => \App\Services\ProductConfiguratorService::SETTING_VIDEO_PATH],
            ['value' => '../../../.env']
        );
        $configService = app(\App\Services\ProductConfiguratorService::class);
        $tutConfig = $configService->getTutorialConfig();
        $this->assertSame(
            \App\Services\ProductConfiguratorService::TUTORIAL_STORAGE_DIR . '/' . \App\Services\ProductConfiguratorService::DEFAULT_TUTORIAL_FILENAME,
            $tutConfig['path']
        );

        $badSvgFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('exploit.svg', '<html><body>Not an SVG</body></html>');
        try {
            $configService->uploadSymbol($badSvgFile, 'Allgemein', 'Exploit');
            $this->fail('Expected InvalidArgumentException for non-SVG upload.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('SVG', $e->getMessage());
        }

        // 5. GameGamesComponent::selectTitle must ignore array input and GameGlobalRankingComponent must whitelist activeTab
        $customer = \App\Models\Customer\Customer::create([
            'first_name' => 'Gamer',
            'last_name' => 'R28',
            'email' => 'gamer-r28@example.com',
            'password' => bcrypt('password123'),
        ]);
        \App\Models\Customer\CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'energy_balance' => 5,
            'level' => 1,
        ]);

        \Livewire\Livewire::actingAs($customer, 'customer')
            ->test(\App\Livewire\Customer\Gamification\GameGamesComponent::class)
            ->call('selectTitle', ['illegal' => 'offset']);

        \Livewire\Livewire::actingAs($customer, 'customer')
            ->test(\App\Livewire\Customer\Gamification\GameGlobalRankingComponent::class)
            ->set('activeTab', 'invalid_tab')
            ->assertSet('activeTab', 'classic');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_master_analytics_ai_company_structure_workspace_and_product_templates(): void
    {
        $admin = $this->admin;
        $employee = $this->employee;

        // 1. SystemBlockedIp & MasterAnalytics must prevent loopback / self-IP ban
        $this->assertFalse(\App\Models\System\SystemBlockedIp::isBlocked('127.0.0.1'));
        try {
            \App\Models\System\SystemBlockedIp::blockIp('127.0.0.1', 'Loopback Lockout');
            $this->fail('Expected InvalidArgumentException when blocking 127.0.0.1');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('IP-Adresse', $e->getMessage());
        }

        // 2. AnalyticsService::checkSpecialIssues must ignore private expenses without receipts
        \App\Models\Accounting\AccountingSpecialIssue::query()->delete();
        \App\Models\Accounting\AccountingSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'Privater Einkauf Ohne Beleg R29',
            'category' => 'Privat',
            'amount' => -200.00,
            'is_business' => false,
            'file_paths' => null,
            'execution_date' => now()->toDateString(),
        ]);
        \Illuminate\Support\Facades\Auth::guard('admin')->login($admin);
        $analyticsService = app(\App\Services\AnalyticsService::class);
        $refCheck = new \ReflectionMethod($analyticsService, 'checkSpecialIssues');
        $refCheck->setAccessible(true);
        $specialCheck = $refCheck->invoke($analyticsService);
        $this->assertSame(0, $specialCheck['count']);
        $this->assertSame('success', $specialCheck['status']);

        // 3. AiCompanyStructure must forbid employees from creating/editing/deleting departments or moving agents
        $dept = \App\Models\Ai\AiDepartment::create([
            'name' => 'R29 Security Dept',
            'description' => 'Test',
            'icon' => 'shield-check',
            'color' => 'emerald-500',
            'order_index' => 1,
        ]);

        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        \Livewire\Livewire::actingAs($employee, 'employee')
            ->test(\App\Livewire\Shop\Ai\AiCompanyStructure::class)
            ->call('deleteDepartment', $dept->id)
            ->assertForbidden();
        $this->assertNotNull(\App\Models\Ai\AiDepartment::find($dept->id));

        // 4. AiWorkspace::sanitizeExternalTtsUrl must block private/internal SSRF targets
        $workspace = new \App\Livewire\Shop\Ai\AiWorkspace();
        $refTts = new \ReflectionMethod($workspace, 'sanitizeExternalTtsUrl');
        $refTts->setAccessible(true);
        $this->assertSame(
            'https://generativelanguage.googleapis.com',
            $refTts->invoke($workspace, 'http://169.254.169.254/latest/meta-data')
        );
        $this->assertSame(
            'https://generativelanguage.googleapis.com',
            $refTts->invoke($workspace, 'https://127.0.0.1:8080/internal')
        );
        $this->assertSame(
            'https://api.elevenlabs.io',
            $refTts->invoke($workspace, 'https://api.elevenlabs.io/')
        );

        // 5. ProductTemplates must reject external/javascript previewImagePath and non-existent products
        $product = \App\Models\Product\Product::create([
            'name' => 'R29 Template Product',
            'slug' => 'r29-template-product',
            'price' => 2500,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
        ]);

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Product\ProductTemplates::class)
            ->call('selectProduct', $product->id)
            ->set('templateName', 'R29 Safe Template')
            ->call('handleTemplateSaved', ['texts' => []], 'javascript:alert(1)');

        $savedTemplate = \App\Models\Product\ProductTemplate::where('name', 'R29 Safe Template')->first();
        $this->assertNotNull($savedTemplate);
        $this->assertNull($savedTemplate->preview_image);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_ai_agent_editor_kb_categories_widget_crawler_and_capacity_thresholds(): void
    {
        $admin = $this->admin;
        $employee = $this->employee;

        // 1. AiAgentEditor must not leak telegram_bot_token to employees on mount and must block SSRF tts_api_url
        $secretAgent = \App\Models\Ai\AiAgent::create([
            'name' => 'R30 Secret Bot',
            'is_active' => true,
            'model' => 'gemini-3.8-flash',
            'color' => 'cyan-500',
            'icon' => 'sparkles',
            'telegram_bot_token' => '123456:SECRET_TELEGRAM_TOKEN_R30',
            'telegram_allowed_chat_ids' => ['999888777'],
        ]);

        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        $empEditor = \Livewire\Livewire::actingAs($employee, 'employee')
            ->test(\App\Livewire\Shop\Ai\AiAgentEditor::class, ['id' => $secretAgent->id]);
        $this->assertSame('', $empEditor->get('telegram_bot_token'));
        $this->assertSame('', $empEditor->get('telegram_allowed_chat_ids'));

        \Illuminate\Support\Facades\Auth::guard('employee')->logout();
        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Ai\AiAgentEditor::class, ['id' => $secretAgent->id])
            ->set('tts_api_url', 'https://127.0.0.1:9000/tts')
            ->call('save')
            ->assertHasErrors(['tts_api_url']);

        // 2. AiKnowledgeBaseCategories must block employees from seeing, renaming, or deleting admin-only categories
        $restrictedCat = \App\Models\Ai\AiKnowledgeBaseCategory::firstOrCreate(
            ['slug' => 'steuern-buchhaltung'],
            ['name' => 'Steuern & Buchhaltung']
        );

        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        $empCats = \Livewire\Livewire::actingAs($employee, 'employee')
            ->test(\App\Livewire\Shop\Ai\AiKnowledgeBaseCategories::class);
        $visibleSlugs = array_column($empCats->get('categories'), 'slug');
        $this->assertNotContains('steuern-buchhaltung', $visibleSlugs);

        $empCats->call('startEditing', $restrictedCat->id, 'Allgemein')
            ->assertForbidden();
        $this->assertSame('steuern-buchhaltung', $restrictedCat->fresh()->slug);

        // 3. AiWidget must forbid employees from generating neural code structures or system brain maps
        \Livewire\Livewire::actingAs($employee, 'employee')
            ->test(\App\Livewire\Shop\Ai\AiWidget::class)
            ->call('generateNeuralStructure', 'app/Models/User.php')
            ->assertForbidden();

        // 4. ProductCrawler must forbid employees from truncating live crawler data
        \App\Models\Product\ProductNicheItem::create([
            'title' => 'R30 Niche Item',
            'platform' => 'Etsy',
            'url' => 'https://www.etsy.com/listing/123456',
            'price' => 29.99,
            'niche_score' => 85,
        ]);

        \Livewire\Livewire::actingAs($employee, 'employee')
            ->test(\App\Livewire\Shop\Product\ProductCrawler::class)
            ->call('clearData')
            ->assertForbidden();
        $this->assertSame(1, \App\Models\Product\ProductNicheItem::count());

        // 5. MasterShopCapacity::updateThresholds must clamp thresholds within 1..100 without overflowing above 100
        \Illuminate\Support\Facades\Auth::guard('employee')->logout();
        $capComp = \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Master\MasterShopCapacity::class)
            ->call('updateThresholds', 100, 100, 100);
        $this->assertSame(98, $capComp->get('threshold1'));
        $this->assertSame(99, $capComp->get('threshold2'));
        $this->assertSame(100, $capComp->get('threshold3'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_financial_service_elster_eric_service_and_telegram_agent_controller(): void
    {
        $admin = \App\Models\Admin\Admin::factory()->create();
        $finService = app(\App\Services\FinancialService::class);

        // 1. FinancialService: sign-safe handling of credit_note / cancellation invoices with positive DB cents
        \App\Models\Accounting\AccountingInvoice::create([
            'invoice_number' => 'RE-R31-001',
            'type' => 'invoice',
            'status' => 'paid',
            'invoice_date' => '2026-02-10',
            'paid_at' => '2026-02-10 12:00:00',
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total' => 11900,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);
        \App\Models\Accounting\AccountingInvoice::create([
            'invoice_number' => 'GS-R31-001',
            'type' => 'credit_note',
            'status' => 'paid',
            'invoice_date' => '2026-02-15',
            'paid_at' => '2026-02-15 12:00:00',
            'subtotal' => 2000,
            'tax_amount' => 380,
            'total' => 2380, // stored positive in DB, must be subtracted as -23.80 € gross / -20.00 € net
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        $monthlyGross = $finService->getMonthlyStats($admin->id, 2, 2026, false);
        $monthlyNet = $finService->getMonthlyStats($admin->id, 2, 2026, true);
        $this->assertEqualsWithDelta(95.20, $monthlyGross['shop_income'], 0.01);
        $this->assertEqualsWithDelta(80.00, $monthlyNet['shop_income'], 0.01);

        $yearlyNet = $finService->getYearlyMatrix($admin->id, 2026, true);
        $this->assertEqualsWithDelta(80.00, $yearlyNet['product_categories']['shop_income']['months'][2], 0.01);

        // 2. FinancialService::getBarChartData must include day-31 fixed costs on Feb 28 in short months
        $group = \App\Models\Accounting\AccountingGroup::create([
            'admin_id' => $admin->id,
            'name' => 'R31 Serverkosten',
            'type' => 'expense',
        ]);
        \App\Models\Accounting\AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Monatsende Hosting',
            'amount' => 50.00,
            'interval_months' => 1,
            'tax_rate' => 0,
            'is_business' => true,
            'first_payment_date' => '2026-01-31',
        ]);

        $barData = $finService->getBarChartData($admin->id, '2026-02-01', '2026-02-28', false);
        $this->assertCount(28, $barData['labels']);
        // Feb 28 (index 27) must have the 50.00 € fixed cost clamped from day 31 -> 28
        $this->assertEqualsWithDelta(50.00, $barData['expense'][27], 0.01);
        // Feb 15 (index 14) must have the 23.80 € credit note recorded as expense
        $this->assertEqualsWithDelta(23.80, $barData['expense'][14], 0.01);

        // 3. ElsterEricService::buildXmlPayload must floor Kz81 down to whole Euros per ELSTER UStVA specification
        $ericService = new \App\Services\ElsterEricService();
        $refBuildXml = new \ReflectionMethod(\App\Services\ElsterEricService::class, 'buildXmlPayload');
        $refBuildXml->setAccessible(true);
        $xml = $refBuildXml->invoke($ericService, [
            'revenue_net' => 1234.89,
            'vat_paid' => 45.67,
            'zahllast' => 188.95,
            'year' => 2026,
            'month_number' => '02',
        ]);
        $this->assertStringContainsString('<Kz81>1234</Kz81>', $xml);
        $this->assertStringNotContainsString('<Kz81>1235</Kz81>', $xml);

        // 4. AiAgent must hide both telegram_bot_token and telegram_allowed_chat_ids in JSON/Array serialization
        $agent = \App\Models\Ai\AiAgent::create([
            'name' => 'R31 Telegram Bot',
            'model' => 'gemini-2.5-flash',
            'is_active' => true,
            'tts_provider' => 'toni_xttsv2',
            'tts_api_url' => '',
            'telegram_bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ123456789',
            'telegram_allowed_chat_ids' => ['777888999'],
        ]);
        $serialized = $agent->toArray();
        $this->assertArrayNotHasKey('telegram_bot_token', $serialized);
        $this->assertArrayNotHasKey('telegram_allowed_chat_ids', $serialized);

        // 5. TelegramAgentController::generateTtsAudio must reject empty / loopback TTS URLs (no fallback to 127.0.0.1:8020)
        $tgController = new \App\Http\Controllers\Api\TelegramAgentController();
        $refTts = new \ReflectionMethod(\App\Http\Controllers\Api\TelegramAgentController::class, 'generateTtsAudio');
        $refTts->setAccessible(true);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or unsafe TTS API URL.');
        $refTts->invoke($tgController, $agent, 'Test Nachricht');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_ai_agent_service_workspace_files_and_configurator_leap_year(): void
    {
        // 1. AiAgentService::getAgent() must resolve role.tools without throwing RelationNotFoundException,
        // and getAgentPayload() must NOT mutate the DB role or escalate read-only calendar_get_events to write/delete tools.
        $role = \App\Models\Ai\AiRole::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'R32 Calendar Reader Role',
        ]);
        $readCalTool = \App\Models\Ai\AiTool::firstOrCreate(
            ['identifier' => 'calendar_get_events'],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Calendar Get Events']
        );
        $adminFinanceTool = \App\Models\Ai\AiTool::firstOrCreate(
            ['identifier' => 'finance_get_monthly_stats'],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Finance Monthly Stats']
        );
        $role->tools()->attach([$readCalTool->id, $adminFinanceTool->id]);

        $agent = \App\Models\Ai\AiAgent::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'R32 Reader Agent',
            'ai_role_id' => $role->id,
            'is_active' => true,
        ]);

        $loadedAgent = \App\Services\AI\AiAgentService::getAgent($agent->id);
        $this->assertSame($agent->id, $loadedAgent->id);

        $this->actingAs($this->admin, 'admin');
        $adminPayload = \App\Services\AI\AiAgentService::getAgentPayload($loadedAgent);
        $adminToolNames = array_map(fn($t) => $t['function']['name'] ?? '', $adminPayload['tools']);

        $this->assertContains('calendar_get_events', $adminToolNames);
        $this->assertNotContains('calendar_delete_event', $adminToolNames);
        $this->assertNotContains('calendar_update_event', $adminToolNames);
        $this->assertNotContains('calendar_send_ics_email', $adminToolNames);
        $this->assertFalse($role->fresh()->tools()->where('identifier', 'calendar_send_ics_email')->exists());

        // 2. When authenticated as an Employee, AiAgentService::getAgentPayload() must strip admin-only tools
        $employee = $this->employee;
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        $this->actingAs($employee, 'employee');

        $empPayload = \App\Services\AI\AiAgentService::getAgentPayload($loadedAgent);
        $empToolNames = array_map(fn($t) => $t['function']['name'] ?? '', $empPayload['tools']);
        $this->assertNotContains('finance_get_monthly_stats', $empToolNames);
        $this->assertNotContains('system_dispatch_background_task', $empToolNames);
        $this->assertNotContains('system_get_task_status', $empToolNames);

        // 3. Zero-tool role or cloned planning agent ($agentClone->tools = collect([])) must NOT bypass AIFunctionsRegistry::execute()
        $this->actingAs($this->admin, 'admin');
        $agentClone = clone $loadedAgent;
        $agentClone->tools = collect([]);
        $this->assertCount(0, $agentClone->tools);

        $deniedCloneRes = \App\Services\AI\AIFunctionsRegistry::execute('calendar_get_events', [], $agentClone);
        $this->assertTrue($deniedCloneRes['error'] ?? false);
        $this->assertStringContainsString('PERMISSION DENIED', $deniedCloneRes['message'] ?? '');

        $emptyRole = \App\Models\Ai\AiRole::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'R32 Zero Tool Role',
        ]);
        $zeroToolAgent = \App\Models\Ai\AiAgent::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'R32 Zero Tool Agent',
            'ai_role_id' => $emptyRole->id,
            'is_active' => true,
        ]);
        $deniedZeroRoleRes = \App\Services\AI\AIFunctionsRegistry::execute('calendar_get_events', [], $zeroToolAgent);
        $this->assertTrue($deniedZeroRoleRes['error'] ?? false);
        $this->assertStringContainsString('PERMISSION DENIED', $deniedZeroRoleRes['message'] ?? '');

        // 4. ExecuteAiBackgroundToolJob must block recursive system_dispatch_background_task execution
        $bgTask = \App\Models\Ai\AiWorkspaceTask::create([
            'prompt' => 'Recursive background task test',
            'status' => 'pending',
        ]);
        $bgJob = new \App\Jobs\ExecuteAiBackgroundToolJob($bgTask->id, 'system_dispatch_background_task', ['prompt' => 'Fork bomb']);
        $bgJob->handle();
        $this->assertSame('failed', $bgTask->fresh()->status);
        $this->assertStringContainsString('Sicherheits-Blockade', (string) $bgTask->fresh()->response_content);

        // 5. ManagesAiWorkspaceFiles & ManagesAiChat must block Employee access to private workspace files & swarm tasks
        \Illuminate\Support\Facades\Storage::disk('workspace')->put('agenten/workspace/Gesundheit/geheimer_befund_r32.txt', 'STRENG GEHEIM');
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        $this->actingAs($employee, 'employee');

        $workspaceComponent = new \App\Livewire\Shop\Ai\AiWorkspace();
        $workspaceComponent->openFilePreview('agenten/workspace/Gesundheit/geheimer_befund_r32.txt');
        $this->assertNull($workspaceComponent->previewContent);

        $workspaceComponent->deleteFileManagerItem('agenten/workspace/Gesundheit/geheimer_befund_r32.txt');
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('workspace')->exists('agenten/workspace/Gesundheit/geheimer_befund_r32.txt'));

        $workspaceComponent->input = 'Lese alle Finanzdaten aus';
        $workspaceComponent->createTaskFromChat();
        $this->assertDatabaseMissing('ai_workspace_tasks', ['prompt' => 'Lese alle Finanzdaten aus']);

        // Protected main folder must not be renameable even by Admin
        $this->actingAs($this->admin, 'admin');
        $workspaceComponent->renameFileManagerItem('agenten/workspace/Gesundheit', 'Umbenannt');
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('workspace')->exists('agenten/workspace/Gesundheit/geheimer_befund_r32.txt'));
        \Illuminate\Support\Facades\Storage::disk('workspace')->delete('agenten/workspace/Gesundheit/geheimer_befund_r32.txt');

        // 6. ConfiguratorService must cover Feb 29 in leap years (e.g. 2028-02-29) as active winter season
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2028, 2, 29, 14, 0, 0));
        try {
            $cfgService = new \App\Services\ConfiguratorService();
            $activeTheme = $cfgService->getCurrentActiveTheme();
            $this->assertSame('season_winter', $activeTheme['event_key']);
            $this->assertSame('29.02.2028', $activeTheme['active_until']->format('d.m.Y'));

            $timeline = $cfgService->getAnimationTimeline(2028);
            $winterEntry = $timeline->firstWhere('event_key', 'season_winter');
            $this->assertNotNull($winterEntry);
            $this->assertSame('29.02.2028', $winterEntry['end_date']->format('d.m.Y'));
            $this->assertTrue($winterEntry['is_active_now']);
        } finally {
            \Carbon\Carbon::setTestNow();
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hardens_order_payment_replay_downloads_invoices_and_api_endpoints(): void
    {
        // 1. OrderOrder::completePayment() must NOT reactivate cancelled or refunded orders
        $cancelledOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R33-CANCELLED',
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'subtotal_price' => 2500,
            'tax_amount' => 475,
            'total_price' => 2500,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'R33'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'R33'],
        ]);

        $cancelledOrder->completePayment('pi_replayed_r33');
        $this->assertSame('cancelled', $cancelledOrder->fresh()->status);
        $this->assertSame('unpaid', $cancelledOrder->fresh()->payment_status);

        // 2. StripeWebhookController must reject non-EUR currency with 400
        $unpaidOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R33-UNPAID',
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal_price' => 3000,
            'tax_amount' => 570,
            'total_price' => 3000,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'R33'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'R33'],
        ]);

        config(['services.stripe.webhook.secret' => 'whsec_test_r33_secret']);
        $usdPayload = json_encode([
            'id' => 'evt_r33_usd',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_r33_usd',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'currency' => 'usd',
                    'amount_total' => 3000,
                    'payment_intent' => 'pi_r33_usd',
                    'metadata' => ['order_id' => $unpaidOrder->id],
                ],
            ],
        ]);
        $timestamp = time();
        $sig = hash_hmac('sha256', "{$timestamp}.{$usdPayload}", 'whsec_test_r33_secret');
        $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$sig}",
            'CONTENT_TYPE' => 'application/json',
        ], $usdPayload)->assertStatus(400);
        $this->assertSame('unpaid', $unpaidOrder->fresh()->payment_status);

        // 3. DigitalProductDownloadController & GiftVoucherDownloadController must block cancelled/refunded orders
        \Illuminate\Support\Facades\Storage::disk('local')->put('produkte/products-secure/ebook_r33.pdf', '%PDF-1.4 R33');
        $digitalProduct = \App\Models\Product\Product::create([
            'name' => 'R33 E-Book',
            'slug' => 'r33-ebook-' . uniqid(),
            'type' => 'digital',
            'price' => 1500,
            'status' => 'active',
            'digital_download_path' => 'produkte/products-secure/ebook_r33.pdf',
            'digital_filename' => 'ebook_r33.pdf',
        ]);

        $refundedOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-R33-REFUNDED',
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'status' => 'cancelled',
            'payment_status' => 'paid',
            'subtotal_price' => 1500,
            'tax_amount' => 285,
            'total_price' => 1500,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'R33'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'R33'],
        ]);

        $digitalItem = \App\Models\Order\OrderOrderItem::create([
            'order_id' => $refundedOrder->id,
            'product_id' => $digitalProduct->id,
            'product_name' => $digitalProduct->name,
            'quantity' => 1,
            'unit_price' => 1500,
            'total_price' => 1500,
        ]);

        $voucher = \App\Models\Marketing\MarketingGiftVoucher::create([
            'code' => 'GV-R33-CANCEL',
            'order_item_id' => $digitalItem->id,
            'initial_value' => 1500,
            'current_balance' => 1500,
            'recipient_name' => 'Max R33',
            'recipient_email' => $this->customer->email,
            'is_active' => true,
        ]);

        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        \Illuminate\Support\Facades\Auth::guard('employee')->logout();
        $this->actingAs($this->customer, 'customer')
            ->get(route('digital-product.download', ['item' => $digitalItem->id]))
            ->assertForbidden();

        $this->actingAs($this->customer, 'customer')
            ->get(route('customer.gift-voucher.download', ['voucher' => $voucher->id]))
            ->assertForbidden();

        \Illuminate\Support\Facades\Storage::disk('local')->delete('produkte/products-secure/ebook_r33.pdf');

        // 4. InvoiceDownloadController must block Employee from downloading private manual invoices (order_id === null)
        $privateManualInvoice = \App\Models\Accounting\AccountingInvoice::create([
            'order_id' => null,
            'customer_id' => null,
            'invoice_number' => 'RE-R33-PRIVATE-MANUAL',
            'type' => 'invoice',
            'status' => 'paid',
            'invoice_date' => now(),
            'subtotal' => 5000,
            'tax_amount' => 950,
            'total' => 5000,
            'billing_address' => ['first_name' => 'Privat', 'last_name' => 'Mandant'],
        ]);

        \Illuminate\Support\Facades\Auth::guard('customer')->logout();
        $this->actingAs($this->employee, 'employee')
            ->get(route('invoice.download', ['invoice' => $privateManualInvoice->id]))
            ->assertForbidden();

        // 5. FetchMailsCommand::sanitizeEmailHtml() must neutralize data:image/svg+xml & SendNewsletter must skip future manual time today
        $dirtySvgHtml = '<img src="data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9ImFsZXJ0KDEpIj4="><a href="data:application/xhtml+xml,<script>alert(1)</script>">Click</a>';
        $sanitizedHtml = \App\Console\Commands\FetchMailsCommand::sanitizeEmailHtml($dirtySvgHtml);
        $this->assertStringNotContainsString('data:image/svg+xml', (string) $sanitizedHtml);
        $this->assertStringNotContainsString('data:application/xhtml+xml', (string) $sanitizedHtml);

        \Illuminate\Support\Facades\Mail::fake();
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2026, 10, 24, 9, 0, 0));
        try {
            \App\Models\Marketing\MarketingNewsletterSubscriber::create([
                'email' => 'sub-r33@example.com',
                'is_verified' => true,
                'privacy_accepted' => true,
            ]);
            $futureManualNewsletter = \App\Models\Marketing\MarketingNewsletter::create([
                'title' => 'R33 Evening Manual',
                'subject' => 'Evening Drop',
                'content' => '<p>Abendkampagne</p>',
                'type' => 'manual',
                'send_at' => \Carbon\Carbon::create(2026, 10, 24, 18, 0, 0),
                'is_active' => true,
            ]);

            $this->artisan('send-newsletters')->assertExitCode(0);
            \Illuminate\Support\Facades\Mail::assertNothingQueued();
            $this->assertTrue((bool) $futureManualNewsletter->fresh()->is_active);
        } finally {
            \Carbon\Carbon::setTestNow();
        }

        // 6. API Orders DHL label guard on cancelled orders & Finance KPI / file cleanup
        \Illuminate\Support\Facades\Auth::guard('employee')->logout();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/shop/orders/{$cancelledOrder->id}/dhl-label", [
                'package_count' => 1,
                'weight_per_package' => 1.5,
            ])
            ->assertStatus(400);

        \Illuminate\Support\Facades\Storage::disk('local')->put('buchhaltung/receipts/r33_beleg.pdf', 'PDF');
        $specialIssue = \App\Models\Accounting\AccountingSpecialIssue::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'admin_id' => $this->admin->id,
            'title' => 'R33 Testbeleg',
            'amount' => -19.99,
            'category' => 'Sonstiges',
            'execution_date' => now(),
            'is_business' => true,
            'file_paths' => ['buchhaltung/receipts/r33_beleg.pdf'],
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/funki/financials/variable/{$specialIssue->id}")
            ->assertOk();
        $this->assertFalse(\Illuminate\Support\Facades\Storage::disk('local')->exists('buchhaltung/receipts/r33_beleg.pdf'));
    }

    public function test_round34_model_secret_hiding_helpers_brain_map_cron_scheduler_and_api_hardening(): void
    {
        // 1. Sensitive secrets/tokens in Eloquent models must be hidden in toArray() / toJson()
        $mailAccount = \App\Models\Management\Mail\MailAccount::create([
            'name' => 'R34 Postfach',
            'email' => 'r34-mail@mein-seelenfunke.de',
            'password' => 'SuperSecretMailPassword!2026',
            'imap_host' => 'imap.ionos.de',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtp.ionos.de',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
        ]);
        $this->assertArrayNotHasKey('password', $mailAccount->toArray());
        $this->assertStringNotContainsString('SuperSecretMailPassword!2026', $mailAccount->toJson());

        $bankAccount = new \App\Models\Accounting\AccountingBankAccount([
            'bank_name' => 'Testbank',
            'plaid_access_token' => 'access-sandbox-secret-token-r34',
        ]);
        $this->assertArrayNotHasKey('plaid_access_token', $bankAccount->toArray());

        $subscriber = \App\Models\Marketing\MarketingNewsletterSubscriber::create([
            'email' => 'r34-sub@example.com',
            'verification_token' => 'secret-verify-token-r34',
            'privacy_accepted' => true,
        ]);
        $this->assertArrayNotHasKey('verification_token', $subscriber->toArray());

        $device = new \App\Models\System\SystemUserDevice([
            'fcm_token' => 'fcm-secret-device-token-r34',
            'device_name' => 'iPhone',
        ]);
        $this->assertArrayNotHasKey('fcm_token', $device->toArray());

        // 2. MailMessage::toArray() must sanitize body_html so API endpoints never serve raw XSS payloads
        $mailMessage = \App\Models\Management\Mail\MailMessage::create([
            'mail_account_id' => $mailAccount->id,
            'message_id' => 'msg-r34-xss',
            'folder' => 'INBOX',
            'subject' => 'R34 XSS Mail',
            'from_email' => 'attacker@example.com',
            'to_email' => 'r34-mail@mein-seelenfunke.de',
            'body_html' => '<p>Hallo</p><script>alert("r34-xss")</script><img src="x" onerror="alert(1)">',
            'received_at' => now(),
        ]);
        $serializedMsg = $mailMessage->toArray();
        $this->assertStringNotContainsString('<script', (string) ($serializedMsg['body_html'] ?? ''));
        $this->assertStringNotContainsString('onerror=', (string) ($serializedMsg['body_html'] ?? ''));

        // 3. Global helpers resolve_snapshot_url() & funki_asset() must block path traversal and unsafe data: URIs
        $this->assertSame('/fallback.png', resolve_snapshot_url('data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==', '/fallback.png'));
        $this->assertSame('/fallback.png', resolve_snapshot_url('data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9ImFsZXJ0KDEpIj48L3N2Zz4=', '/fallback.png'));
        $this->assertSame('/fallback.png', resolve_snapshot_url('../../.env', '/fallback.png'));
        $validPngData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $this->assertSame($validPngData, resolve_snapshot_url($validPngData, '/fallback.png'));

        $funkiUrl = funki_asset('../../.env', '../../etc');
        $this->assertStringNotContainsString('..', $funkiUrl);

        // 4. TabCronjobs::saveSchedule() must block arbitrary Event method names (like runInBackground / onOneServer)
        $cronjob = \App\Models\System\SystemCronjob::create([
            'name' => 'R34 Test Cron',
            'description' => 'Test',
            'command' => 'system:heartbeat',
            'schedule' => '0 * * * *',
            'is_active' => true,
        ]);

        \Livewire\Livewire::actingAs($this->admin, 'admin')
            ->test(\App\Livewire\Shop\Ai\PartialsAiWorkspace\TabCronjobs::class)
            ->call('editSchedule', $cronjob->id)
            ->set('editingSchedule', 'runInBackground')
            ->call('saveSchedule');
        $this->assertSame('0 * * * *', $cronjob->fresh()->schedule);

        \Livewire\Livewire::actingAs($this->admin, 'admin')
            ->test(\App\Livewire\Shop\Ai\PartialsAiWorkspace\TabCronjobs::class)
            ->call('editSchedule', $cronjob->id)
            ->set('editingSchedule', 'everyFiveMinutes')
            ->call('saveSchedule');
        $this->assertSame('everyFiveMinutes', $cronjob->fresh()->schedule);

        // 5. System Brain Map API endpoint requires admin/employee auth and blocks customers/guests
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        $this->getJson('/api/ai/system-brain-map')->assertStatus(401);

        $this->actingAs($this->customer, 'customer')
            ->getJson('/api/ai/system-brain-map')
            ->assertStatus(401);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/ai/system-brain-map')
            ->assertStatus(403);

        // 6. RegisterTelegramWebhooks must reject internal/loopback HTTPS domains (SSRF / Webhook hijacking protection)
        $this->artisan('telegram:register-webhooks', ['--domain' => 'https://127.0.0.1'])
            ->assertExitCode(\Illuminate\Console\Command::FAILURE);

        // 7. API input validation hardening on finance, routine, and emails endpoints
        \Illuminate\Support\Facades\Auth::guard('customer')->logout();
        $financeGroup = \App\Models\Accounting\AccountingGroup::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'admin_id' => $this->admin->id,
            'name' => 'R34 Fixkosten',
            'type' => 'expense',
            'position' => 1,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/funki/financials/fixed-item', [
                'finance_group_id' => $financeGroup->id,
                'name' => 'Null-Intervall Test',
                'amount' => -50,
                'interval_months' => 0,
                'first_payment_date' => now()->format('Y-m-d'),
            ])
            ->assertStatus(422);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/funki/routine', [
                'title' => 'R34 Routine',
                'duration_minutes' => -10,
                'start_time' => '08:00:00',
            ])
            ->assertStatus(422);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/funki/emails/accounts', [
                'name' => 'Bad Port Account',
                'email' => 'badport@mein-seelenfunke.de',
                'imap_host' => 'imap.ionos.de',
                'imap_port' => 99999,
                'smtp_host' => 'smtp.ionos.de',
                'smtp_port' => 465,
            ])
            ->assertStatus(422);
    }

    public function test_round35_mail_lfi_livewire_svg_xss_dompdf_js_mailable_traversal_token_revocation_and_atomic_stock(): void
    {
        // 1. Livewire temporary upload config must not allow SVG preview (Stored XSS) and DomPDF must disable inline JS
        $previewMimes = config('livewire.temporary_file_upload.preview_mimes', []);
        $this->assertNotContains('svg', $previewMimes);
        $this->assertNotEmpty(config('livewire.temporary_file_upload.middleware'));
        $this->assertFalse((bool) config('dompdf.options.enable_javascript'));

        // 2. mail_item_list.blade.php must block LFI / arbitrary file read (e.g., .env or config/app.php) in PDF mode
        $renderedPdfItemHtml = view('global.mails.partials.mail_item_list', [
            'isPdf' => true,
            'data' => [
                'items' => [
                    [
                        'name' => 'LFI Test Artikel',
                        'quantity' => 1,
                        'total_price' => '19,99',
                        'main_image' => '../.env',
                        'config' => [
                            'snapshot_path' => [
                                'front' => '.env',
                                'back' => 'config/app.php',
                            ],
                            'logo_storage_path' => '../../.env',
                        ],
                    ],
                ],
            ],
        ])->render();

        $this->assertStringNotContainsString('data:', $renderedPdfItemHtml);
        $this->assertStringNotContainsString('Ansehen / Download', $renderedPdfItemHtml);

        // 3. Mailable attachment path traversal & CRLF header injection protection
        $orderMailCustomer = new \App\Mail\NewOrderMailToCustomer(
            ['quote_number' => "ORD-2026\r\nBcc: attacker@evil.test"],
            base_path('.env'),
            base_path('composer.json'),
            [base_path('.env')]
        );
        $this->assertStringNotContainsString("\r", $orderMailCustomer->envelope()->subject);
        $this->assertStringNotContainsString("\n", $orderMailCustomer->envelope()->subject);
        $this->assertCount(0, $orderMailCustomer->attachments());

        $orderMailAdmin = new \App\Mail\NewOrderMailToAdmin(
            [
                'quote_number' => "ORD-2026\r\nInjected: 1",
                'contact' => [
                    'vorname' => "Max\r\n",
                    'nachname' => "Mustermann\r\n",
                    'email' => 'not-an-email',
                ],
            ],
            base_path('.env'),
            null,
            [base_path('config/app.php')]
        );
        $this->assertEmpty($orderMailAdmin->envelope()->replyTo);
        $this->assertStringNotContainsString("\r", $orderMailAdmin->envelope()->subject);
        $this->assertCount(0, $orderMailAdmin->attachments());

        $revocationAdmin = new \App\Mail\RevocationMailToAdmin([
            'order_number' => "ORD-99\r\nBcc: evil@example.com",
            'attachments' => [
                '../.env',
                'other_folder/secret.pdf',
                'revocations/../../.env',
                'revocations/legit_file.pdf',
            ],
        ]);
        $this->assertStringNotContainsString("\r", $revocationAdmin->envelope()->subject);
        $this->assertCount(1, $revocationAdmin->attachments());

        // 4. Sanctum token revocation on password reset
        $resetCustomer = \App\Models\Customer\Customer::factory()->create([
            'password' => \Illuminate\Support\Facades\Hash::make('OldPassword123!'),
        ]);
        \App\Models\Customer\CustomerProfile::firstOrCreate(['customer_id' => $resetCustomer->id]);
        $resetCustomer->createToken('test-device-token');
        $this->assertSame(1, $resetCustomer->tokens()->count());

        $plainToken = 'Round35ResetTokenString1234567890';
        \Illuminate\Support\Facades\DB::table('system_password_reset_tokens')->insert([
            'email' => $resetCustomer->email,
            'token' => \Illuminate\Support\Facades\Hash::make($plainToken),
            'guard' => 'customer',
            'created_at' => now(),
        ]);

        \Livewire\Livewire::test(\App\Livewire\Auth\AuthPasswordReset::class, ['token' => $plainToken])
            ->set('email', $resetCustomer->email)
            ->set('password', 'NewSecurePass99!')
            ->set('passwordConfirm', 'NewSecurePass99!')
            ->call('submit');

        $this->assertSame(0, $resetCustomer->tokens()->count());

        // 5. AuthLogin::mount() must reject invalid session('guard') values
        session(['2fa_user_id' => $resetCustomer->id, 'guard' => 'invalid_guard_injection']);
        \Livewire\Livewire::test(\App\Livewire\Auth\AuthLogin::class)
            ->assertSet('guard', 'customer')
            ->assertSet('activeView', 'login');

        // 6. Atomic Product::reduceStock() and negative amount protection
        $stockProduct = \App\Models\Product\Product::create([
            'name' => 'R35 Atomic Stock Product',
            'slug' => 'r35-atomic-stock-product',
            'price' => 1500,
            'status' => 'active',
            'type' => 'physical',
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => false,
            'quantity' => 2,
        ]);

        $this->assertFalse($stockProduct->reduceStock(0));
        $this->assertFalse($stockProduct->reduceStock(-5));
        $stockProduct->restoreStock(-10);
        $this->assertSame(2, $stockProduct->fresh()->quantity);

        $this->assertTrue($stockProduct->reduceStock(2));
        $this->assertSame(0, $stockProduct->fresh()->quantity);
        $this->assertFalse($stockProduct->reduceStock(1));
        $this->assertSame(0, $stockProduct->fresh()->quantity);

        // 7. MarketingVoucher::scopeCurrent() must exclude personal (recipient_email) and exhausted vouchers
        $personalVoucher = \App\Models\Marketing\MarketingVoucher::create([
            'title' => 'Personal Gift',
            'code' => 'R35-PERSONAL',
            'recipient_email' => 'vip@example.com',
            'type' => 'fixed',
            'value' => 1000,
            'is_active' => true,
            'mode' => 'manual',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
        ]);

        $exhaustedVoucher = \App\Models\Marketing\MarketingVoucher::create([
            'title' => 'Exhausted Promo',
            'code' => 'R35-EXHAUSTED',
            'type' => 'fixed',
            'value' => 500,
            'used_count' => 10,
            'usage_limit' => 10,
            'is_active' => true,
            'mode' => 'manual',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
        ]);

        $publicActiveVoucher = \App\Models\Marketing\MarketingVoucher::create([
            'title' => 'Public Promo',
            'code' => 'R35-PUBLIC',
            'type' => 'fixed',
            'value' => 500,
            'used_count' => 2,
            'usage_limit' => 10,
            'is_active' => true,
            'mode' => 'manual',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
        ]);

        $currentIds = \App\Models\Marketing\MarketingVoucher::current()->pluck('id')->all();
        $this->assertNotContains($personalVoucher->id, $currentIds);
        $this->assertNotContains($exhaustedVoucher->id, $currentIds);
        $this->assertContains($publicActiveVoucher->id, $currentIds);
    }
}

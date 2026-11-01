<?php

namespace Tests\Feature\Architecture;

use App\Models\Accounting\AccountingBankTransaction;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Admin\Admin;
use App\Models\Ai\AiChatMemory;
use App\Models\Customer\Customer;
use App\Models\Employee\EmployeePayslip;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Order\OrderRevocation;
use App\Models\Support\SupportTicket;
use App\Models\System\SystemLog;
use App\Models\System\SystemPasswordResetToken;
use App\Services\AI\Mails\AiAgentMessageMail;
use App\Services\System\SystemBlueprintService;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Exceptions\BlueprintSecurityException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SystemBlueprintArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_eloquent_model_in_project_declares_a_valid_system_blueprint(): void
    {
        $service = app(SystemBlueprintService::class);
        $models = $service->discoverAllModels();
        $audit = $service->auditArchitecture();

        $this->assertGreaterThanOrEqual(127, $models->count());
        $this->assertEmpty(
            $audit['missing_blueprints'],
            'Folgende Models besitzen noch kein eigenes HasSystemBlueprint::blueprint(): ' . implode(', ', $audit['missing_blueprints'])
        );
        $this->assertEmpty(
            $audit['violations'],
            'Architektur-Blueprint-Verstöße gefunden: ' . implode(' | ', $audit['violations'])
        );
        $this->assertTrue($audit['is_fully_compliant']);

        foreach ($models as $entry) {
            $this->assertTrue(
                is_subclass_of($entry['class'], HasSystemBlueprint::class),
                "Model {$entry['class']} muss HasSystemBlueprint implementieren."
            );
            $this->assertTrue(
                $entry['declares_own_blueprint'],
                "Model {$entry['class']} muss eine eigene public static function blueprint(): ModelBlueprint deklarieren."
            );
        }
    }

    public function test_gobd_and_security_critical_models_enforce_strict_retention_and_ai_guardrails(): void
    {
        $gobdCriticalModels = [
            AccountingInvoice::class,
            AccountingBankTransaction::class,
            OrderOrder::class,
            OrderOrderItem::class,
            OrderRevocation::class,
            EmployeePayslip::class,
        ];

        foreach ($gobdCriticalModels as $modelClass) {
            $bp = $modelClass::blueprint();
            $this->assertSame(
                RetentionPolicy::GoBD_10Years,
                $bp->retention,
                "{$modelClass} muss zwingend RetentionPolicy::GoBD_10Years deklarieren."
            );
            $this->assertContains(
                ComplianceRegulation::GoBD,
                $bp->regulations,
                "{$modelClass} muss ComplianceRegulation::GoBD enthalten."
            );
            $this->assertFalse(
                $bp->aiAccess->allowsAiWrite(),
                "{$modelClass} darf niemals autonomen KI-Schreibzugriff erlauben."
            );
        }
    }

    public function test_runtime_ai_guardrail_blocks_writes_and_reads_on_protected_models(): void
    {
        // Autonomous AI models allow read and write
        AiChatMemory::assertAiReadAllowed();
        AiChatMemory::assertAiWriteAllowed();
        SupportTicket::assertAiWriteAllowed();

        // ReadOnly AI models allow read, but throw on write
        AccountingInvoice::assertAiReadAllowed();
        $this->expectException(BlueprintSecurityException::class);
        AccountingInvoice::assertAiWriteAllowed();
    }

    public function test_runtime_ai_guardrail_strictly_blocks_taboo_security_models(): void
    {
        $this->assertSame(AiAccessPolicy::None, Admin::blueprint()->aiAccess);
        $this->assertSame(AiAccessPolicy::None, SystemPasswordResetToken::blueprint()->aiAccess);

        $this->expectException(BlueprintSecurityException::class);
        SystemPasswordResetToken::assertAiReadAllowed();
    }

    public function test_generates_living_gdpr_art30_vvt_markdown_from_model_blueprints(): void
    {
        $service = app(SystemBlueprintService::class);
        $markdown = $service->generateGdprArt30VvtMarkdown(false);

        $this->assertStringContainsString('Verzeichnis von Verarbeitungstätigkeiten (Art. 30 DSGVO)', $markdown);
        $this->assertStringContainsString('Buchhaltung & Finanzen', $markdown);
        $this->assertStringContainsString('Bestellungen & Manufaktur-Fertigung', $markdown);
        $this->assertStringContainsString('AccountingInvoice', $markdown);
        $this->assertStringContainsString('OrderOrder', $markdown);
        $this->assertStringContainsString('10 Jahre GoBD', $markdown);
    }

    public function test_exports_automated_gdpr_art15_customer_data_package_with_gobd_lock_notices(): void
    {
        $customer = Customer::create([
            'first_name' => 'Alina',
            'last_name' => 'Testkundin',
            'email' => 'alina-blueprint@example.com',
            'password' => bcrypt('secret123'),
        ]);

        OrderOrder::create([
            'order_number' => 'SF-BP-2026-001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Alina', 'last_name' => 'Testkundin', 'city' => 'Berlin'],
            'shipping_address' => ['first_name' => 'Alina', 'last_name' => 'Testkundin', 'city' => 'Berlin'],
            'subtotal_price' => 2490,
            'tax_amount' => 398,
            'shipping_price' => 0,
            'total_price' => 2490,
        ]);

        $service = app(SystemBlueprintService::class);
        $export = $service->exportCustomerGdprArt15Data($customer);

        $this->assertSame($customer->id, $export['customer_id']);
        $this->assertArrayHasKey('Customer', $export['datasets']);
        $this->assertArrayHasKey('OrderOrder', $export['datasets']);
        $this->assertSame(1, $export['datasets']['OrderOrder']['record_count']);
        $this->assertArrayNotHasKey('password', $export['datasets']['Customer']['records'][0]);
        $this->assertArrayHasKey('OrderOrder', $export['gobd_retention_notices']);
    }

    public function test_artisan_blueprint_audit_command_succeeds(): void
    {
        $this->artisan('system:blueprint:audit')
            ->assertExitCode(0);
    }

    public function test_guardrail_violation_logs_security_event_and_sends_throttled_admin_alert_mail(): void
    {
        Mail::fake();
        Cache::flush();

        try {
            AccountingInvoice::assertAiWriteAllowed();
            $this->fail('Expected BlueprintSecurityException was not thrown.');
        } catch (BlueprintSecurityException $e) {
            $this->assertStringContainsString('AccountingInvoice', $e->getMessage());
        }

        // Verify SystemLog security entry was created
        $log = SystemLog::where('action_id', 'security:blueprint_ai_guardrail_write')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('security', $log->type);
        $this->assertSame('error', $log->status);
        $this->assertStringContainsString('AccountingInvoice', $log->title);

        // Verify AiAgentMessageMail was sent once (and throttled on immediate repeat)
        Mail::assertSent(AiAgentMessageMail::class, function (AiAgentMessageMail $mail) {
            return str_contains($mail->messageSubject, 'BLUEPRINT KI-GUARDRAIL ALARM')
                && str_contains($mail->messageContent, 'AccountingInvoice');
        });

        try {
            AccountingInvoice::assertAiWriteAllowed();
        } catch (BlueprintSecurityException) {
        }

        Mail::assertSentCount(1);
    }
}

<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\Ai\PartialsAiWorkspace\TabCronjobs;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Customer\Customer;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Support\SupportCustomerChat;
use App\Models\Support\SupportTicket;
use App\Models\System\SystemCronjob;
use App\Services\AI\AiPromptService;
use App\Services\AI\CentralBrainService;
use App\Services\Marketing\HolidayOfferAutopilotService;
use App\Services\System\SystemAlertTicketService;
use App\Services\System\SystemBlueprintService;
use Database\Seeders\SystemCronjobSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CronjobSecurityAndTicketRoutingAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $sysDept = AiDepartment::firstOrCreate(['name' => 'System'], ['description' => 'System & IT']);
        $supDept = AiDepartment::firstOrCreate(['name' => 'Support'], ['description' => 'Kundenservice']);
        $accDept = AiDepartment::firstOrCreate(['name' => 'Buchhaltung'], ['description' => 'Finanzen']);
        $ordDept = AiDepartment::firstOrCreate(['name' => 'Bestellungen'], ['description' => 'Logistik']);
        $agDept = AiDepartment::firstOrCreate(['name' => 'Agenten'], ['description' => 'KI-Agenten']);

        AiAgent::firstOrCreate(['name' => 'Systemi'], ['slug' => 'systemi', 'ai_department_id' => $sysDept->id, 'is_active' => true]);
        AiAgent::firstOrCreate(['name' => 'Funkira'], ['slug' => 'funkira', 'ai_department_id' => $supDept->id, 'is_active' => true]);
        AiAgent::firstOrCreate(['name' => 'Buchi'], ['slug' => 'buchi', 'ai_department_id' => $accDept->id, 'is_active' => true]);
        AiAgent::firstOrCreate(['name' => 'Bestelli'], ['slug' => 'bestelli', 'ai_department_id' => $ordDept->id, 'is_active' => true]);
        AiAgent::firstOrCreate(['name' => 'Agenti'], ['slug' => 'agenti', 'ai_department_id' => $agDept->id, 'is_active' => true]);
        AiAgent::firstOrCreate(['name' => 'Funki'], ['slug' => 'funki', 'ai_department_id' => $supDept->id, 'is_active' => true]);
    }

    #[Test]
    public function blueprint_audit_violation_creates_deduplicated_ticket_and_auto_heals_without_sending_mail(): void
    {
        Mail::fake();

        $bpService = app(SystemBlueprintService::class);
        $mockViolation = [
            'is_fully_compliant' => false,
            'total_models' => 138,
            'compliant_models' => 137,
            'missing_blueprints' => ['App\\Models\\DummyModel'],
            'violations' => ['Model [App\\Models\\DummyModel] besitzt kein blueprint()-Typenschild.'],
        ];

        $bpService->reportArchitectureAuditViolations($mockViolation);
        $bpService->reportArchitectureAuditViolations($mockViolation);

        Mail::assertNothingSent();

        $tickets = AiUniverseTicket::where('error_fingerprint', 'like', 'sys_alert:blueprint_audit:%')->get();
        $this->assertCount(1, $tickets);

        $ticket = $tickets->first();
        $this->assertSame(2, (int) $ticket->occurrence_count);
        $this->assertStringContainsString('Architektur-Blueprint Verstoß: 1 Abweichung(en) erkannt', $ticket->title);
        $this->assertTrue((bool) $ticket->assigned_to_antigravity);

        // Real blueprint audit passes 100 % -> ticket auto-resolves as completed!
        $this->artisan('system:blueprint:audit')->assertExitCode(0);
        Mail::assertNothingSent();

        $this->assertSame(AiUniverseTicket::STATUS_COMPLETED, $ticket->fresh()->status);
    }

    #[Test]
    public function spatie_backup_config_disables_all_mails_and_routes_failures_and_recovery_to_tickets(): void
    {
        Mail::fake();

        $notifications = config('backup.notifications.notifications', []);
        foreach ($notifications as $notifClass => $channels) {
            $this->assertSame([], $channels, "Spatie notification {$notifClass} must not send emails.");
        }

        if (class_exists(\Spatie\Backup\Events\BackupHasFailed::class)) {
            event(new \Spatie\Backup\Events\BackupHasFailed(new \RuntimeException('Disk full during dump')));
            Mail::assertNothingSent();

            $ticket = AiUniverseTicket::where('error_fingerprint', 'sys_alert:spatie_backup:backup_failed')->first();
            $this->assertNotNull($ticket);
            $this->assertNotSame(AiUniverseTicket::STATUS_COMPLETED, $ticket->status);

            if (class_exists(\Spatie\Backup\Events\BackupWasSuccessful::class)) {
                $ref = new \ReflectionClass(\Spatie\Backup\Events\BackupWasSuccessful::class);
                $successEvent = $ref->newInstanceWithoutConstructor();
                event($successEvent);

                $this->assertSame(AiUniverseTicket::STATUS_COMPLETED, $ticket->fresh()->status);
            }
        }
    }

    #[Test]
    public function auto_resolve_support_chats_protects_needs_employee_escalations_and_creates_reminder_ticket(): void
    {
        Mail::fake();

        $aiOnlyChat = SupportCustomerChat::create([
            'session_token' => 'tok_ai_inactive_' . uniqid(),
            'status' => 'open',
        ]);
        SupportCustomerChat::where('id', $aiOnlyChat->id)->update(['updated_at' => now()->subHours(14)]);

        $escalatedChat = SupportCustomerChat::create([
            'session_token' => 'tok_human_waiting_' . uniqid(),
            'status' => 'needs_employee',
        ]);
        SupportCustomerChat::where('id', $escalatedChat->id)->update(['updated_at' => now()->subHours(14)]);

        $this->artisan('support:auto-resolve-chats')->assertExitCode(0);
        Mail::assertNothingSent();

        $this->assertSame('resolved_auto', $aiOnlyChat->fresh()->status);
        $this->assertSame('needs_employee', $escalatedChat->fresh()->status, 'Escalated human chats must NEVER be silently closed!');

        $reminderTicket = AiUniverseTicket::where('error_fingerprint', 'sys_alert:support:stale_escalated_chats')->first();
        $this->assertNotNull($reminderTicket);

        // Once employee resolves the chat, next cron run auto-resolves the internal reminder ticket
        $escalatedChat->update(['status' => 'resolved_admin']);
        $this->artisan('support:auto-resolve-chats')->assertExitCode(0);
        $this->assertSame(AiUniverseTicket::STATUS_COMPLETED, $reminderTicket->fresh()->status);
    }

    #[Test]
    public function delete_unverified_users_protects_customers_with_tickets_chats_or_quote_requests(): void
    {
        $inactiveGhost = Customer::factory()->create([
            'email' => 'ghost@example.test',
            'email_verified_at' => null,
            'created_at' => now()->subHours(30),
        ]);
        $inactiveGhost->profile()->update(['email_verified_at' => null]);

        $ticketCustomer = Customer::factory()->create([
            'email' => 'lead-ticket@example.test',
            'email_verified_at' => null,
            'created_at' => now()->subHours(30),
        ]);
        $ticketCustomer->profile()->update(['email_verified_at' => null]);
        SupportTicket::create([
            'ticket_number' => 'TCK-' . rand(10000, 99999),
            'customer_id' => $ticketCustomer->id,
            'subject' => 'Rückfrage vor Bestellung',
            'category' => 'general',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $quoteCustomer = Customer::factory()->create([
            'email' => 'b2b-quote@example.test',
            'email_verified_at' => null,
            'created_at' => now()->subHours(30),
        ]);
        $quoteCustomer->profile()->update(['email_verified_at' => null]);
        OrderQuoteRequest::create([
            'quote_number' => 'ANF-' . rand(10000, 99999),
            'customer_id' => $quoteCustomer->id,
            'email' => $quoteCustomer->email,
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'status' => 'open',
            'net_total' => 50000,
            'tax_total' => 9500,
            'gross_total' => 59500,
        ]);

        $this->artisan('system:delete-unverified-users')->assertExitCode(0);

        $this->assertNull(Customer::withTrashed()->find($inactiveGhost->id));
        $this->assertNotNull(Customer::find($ticketCustomer->id));
        $this->assertNotNull(Customer::find($quoteCustomer->id));
    }

    #[Test]
    public function tab_cronjobs_blocks_dangerous_commands_and_syncs_all_20_default_cronjobs(): void
    {
        SystemCronjobSeeder::ensureDefaultCronjobsExist();
        $jobs = SystemCronjob::all();
        $this->assertCount(20, $jobs);
        $this->assertNotNull($jobs->firstWhere('command', 'system:blueprint:audit'));

        $admin = Admin::factory()->create();

        foreach (['down', 'key:generate', 'migrate:fresh', 'system:prepare-live', 'queue:work'] as $blockedCmd) {
            $dangerousJob = SystemCronjob::create([
                'name' => 'Danger ' . $blockedCmd,
                'description' => 'Test',
                'command' => $blockedCmd,
                'schedule' => '0 0 * * *',
                'is_active' => false,
                'status' => 'pending',
            ]);

            Livewire::actingAs($admin, 'admin')
                ->test(TabCronjobs::class)
                ->call('runNow', $dangerousJob->id);

            $this->assertSame('pending', $dangerousJob->fresh()->status, "Dangerous command {$blockedCmd} must be blocked.");
        }
    }

    #[Test]
    public function newsletter_holiday_dates_match_offer_autopilot_and_core_cronjobs_execute_cleanly(): void
    {
        Mail::fake();

        $cmd = new \App\Console\Commands\SendNewsletter();
        $ref = new \ReflectionMethod($cmd, 'getHolidayDate');
        $ref->setAccessible(true);

        $autopilot = app(HolidayOfferAutopilotService::class);
        foreach (['valentines', 'easter', 'mothers_day', 'fathers_day', 'sale_summer', 'christmas', 'sale_winter'] as $eventKey) {
            $cmdDate = $ref->invoke($cmd, $eventKey, 2027);
            $autoDate = $autopilot->getHolidayDate($eventKey, 2027);
            $this->assertSame($autoDate?->toDateString(), $cmdDate?->toDateString(), "Mismatch on {$eventKey}");
        }

        $this->artisan('system:heartbeat')->assertExitCode(0);
        $this->artisan('shop:capacity-engine')->assertExitCode(0);
        $this->artisan('shop:generate-monthly-vouchers')->assertExitCode(0);
        $this->artisan('dhl:check-delivery-status')->assertExitCode(0);
        $this->artisan('gamification:award-loyalty-sparks')->assertExitCode(0);
        $this->artisan('shop:send-abandoned-cart-reminders')->assertExitCode(0);
        $this->artisan('shop:cleanup-orphaned-uploads --dry-run')->assertExitCode(0);
        $this->artisan('tickets:cleanup-files')->assertExitCode(0);
        $this->artisan('system:logs-cleanup --prune')->assertExitCode(0);
        $this->artisan('crm:ai-process-mails')->assertExitCode(0);
    }

    #[Test]
    public function completion_reports_and_agent_prompts_enforce_central_knowledge_base_instead_of_folders_or_mails(): void
    {
        $funkira = AiAgent::where('name', 'Funkira')->firstOrFail();
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $prompt = AiPromptService::getRichPrompt($funkira);
        $this->assertStringContainsString('ABSCHLUSSBERICHTE IN DIE WISSENSDATENBANK', $prompt);
        $this->assertStringContainsString('/admin/tickets?view=knowledge', $prompt);

        $kb = CentralBrainService::saveMilestoneReport(
            title: 'Abschlussbericht: Cronjob-Härtung & Ticket-Bridge',
            content: "# Cronjob-Härtung\nAlle internen Warn-Mails wurden auf Universum-Tickets umgestellt.",
            categoryName: 'System',
            tags: ['cronjobs', 'tickets', 'security'],
            logicalReportPath: 'System/Abschlussbericht_Cronjob_Haertung.md',
            syncSnapshot: false
        );

        $this->assertNotNull($kb);
        $this->assertSame(AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE, $kb->visibility_scope);
        $this->assertSame('Abschlussberichte/System/Abschlussbericht_Cronjob_Haertung.md', $kb->source_path);

        // Prüfe zudem, dass AiKnowledgeBaseSeeder bei migrate:fresh --seed automatisch alle Berichte (inkl. neuem Cronjob-Abschlussbericht) einspielt
        AiKnowledgeBase::query()->delete();
        $this->seed(\Database\Seeders\AiKnowledgeBaseSeeder::class);

        $seededCronReport = AiKnowledgeBase::where(
            'source_path',
            'Abschlussberichte/System/Abschlussbericht_Cronjob_Audit_Ticket_Routing_und_Wissensdatenbank.md'
        )->first();
        $this->assertNotNull($seededCronReport, 'Der neue Cronjob-Abschlussbericht muss durch AiKnowledgeBaseSeeder automatisch mitgeseedet werden!');
        $this->assertStringContainsString('Voll-Audit aller 20 System-Cronjobs', $seededCronReport->title);
    }
}

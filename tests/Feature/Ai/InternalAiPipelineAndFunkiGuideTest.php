<?php

namespace Tests\Feature\Ai;

use App\Livewire\Frontend\Support\AiFrontendSupportChat;
use App\Livewire\Shop\Accounting\AccountingQuickEntry;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailMessage;
use App\Models\Order\OrderOrder;
use App\Services\AI\AiOutputQualityGate;
use App\Services\AI\AiPromptService;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\BuchiReceiptPipelineService;
use App\Services\AI\ContextDossierBuilder;
use App\Services\AI\SfInternalOrchestrator;
use App\Services\Management\MailAiSortService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\AiKnowledgeBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InternalAiPipelineAndFunkiGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_funki_answers_customer_dashboard_invoices_blueprint_and_no_telephony_in_zero_tokens(): void
    {
        $customer = Customer::factory()->create(['first_name' => 'Clara']);
        $customer->profile()->updateOrCreate(
            ['customer_id' => $customer->id],
            ['ai_data_consent' => true, 'ai_data_consent_at' => now()]
        );

        AiAgent::create([
            'name' => 'Funki',
            'is_active' => true,
            'system_prompt' => 'Du bist Funki.',
        ]);

        $comp = Livewire::actingAs($customer, 'customer')
            ->test(AiFrontendSupportChat::class);

        // 1. Dashboard-Wegweiser
        $comp->set('message', 'Wie bediene ich mein Kunden-Dashboard und wo finde ich was?')
            ->call('sendMessage')
            ->call('generateAiResponse');

        $messages = $comp->get('messages');
        $lastReply = end($messages)['text'] ?? '';
        $this->assertStringContainsString('/dashboard', $lastReply);
        $this->assertStringContainsString('/profile', $lastReply);
        $this->assertStringContainsString('/orders', $lastReply);
        $this->assertStringContainsString('/invoices', $lastReply);
        $this->assertStringContainsString('/funki', $lastReply);
        $this->assertStringContainsString('/reviews', $lastReply);
        $this->assertStringContainsString('/support', $lastReply);
        $this->assertStringNotContainsString(' – ', $lastReply);

        // 2. Keine Telefonie für Funki
        $comp->set('message', 'Könnt ihr mich bitte telefonisch zurückrufen?')
            ->call('sendMessage')
            ->call('generateAiResponse');

        $messages = $comp->get('messages');
        $noPhoneReply = end($messages)['text'] ?? '';
        $this->assertStringContainsString('telefoniere ich nicht', $noPhoneReply);
        $this->assertStringContainsString('/support', $noPhoneReply);

        // 3. Rechnungs-Archiv & Blueprint
        $comp->set('message', 'Wo kann ich meine Rechnungen herunterladen?')
            ->call('sendMessage')
            ->call('generateAiResponse');

        $messages = $comp->get('messages');
        $invoiceReply = end($messages)['text'] ?? '';
        $this->assertStringContainsString('/invoices', $invoiceReply);

        $comp->set('message', 'Wo sehe ich meine Gravur-Vorschau Blueprint?')
            ->call('sendMessage')
            ->call('generateAiResponse');

        $messages = $comp->get('messages');
        $blueprintReply = end($messages)['text'] ?? '';
        $this->assertStringContainsString('/orders', $blueprintReply);
        $this->assertStringContainsString('Gravur-Vorschau', $blueprintReply);
    }

    public function test_knowledge_base_seeder_and_funki_prompt_include_dashboard_and_3d_atelier_guides(): void
    {
        $this->seed(AiKnowledgeBaseSeeder::class);

        $this->assertTrue(
            AiKnowledgeBase::where('title', 'Kunden-Dashboard & Konto-Wegweiser (Wo finde ich was?)')
                ->where('is_published', true)
                ->exists()
        );
        $this->assertTrue(
            AiKnowledgeBase::where('title', '2D/3D-Produktkonfigurator & 3D-Atelier-Welt (Bedienungshilfe)')
                ->where('is_published', true)
                ->exists()
        );

        $funki = AiAgent::create([
            'name' => 'Funki',
            'is_active' => true,
            'system_prompt' => 'Du bist Funki.',
        ]);

        $prompt = AiPromptService::getRichPrompt($funki);
        $this->assertStringContainsString('/funki', $prompt);
        $this->assertStringContainsString('/invoices', $prompt);
        $this->assertStringContainsString('KEINE TELEFONIE', $prompt);
        $this->assertStringNotContainsString('BKK firmus', $prompt);
    }

    public function test_buchi_receipt_pipeline_applies_supplier_rules_clean_filenames_duplicate_guard_and_seeder_protection(): void
    {
        Storage::fake('local');
        $admin = Admin::factory()->create();

        // 1. Seelenfunke-Lieferantenregel (Westpack -> Verpackungen, Gewerblich, 19 %, negative Cent-Integer, 0 Gedankenstriche)
        $enriched = BuchiReceiptPipelineService::enrichScanResult([
            'merchant' => 'Westpack A/S',
            'title' => 'Westpack – Schwarze Schmuckboxen',
            'amount' => 148.50,
            'date' => '2026-11-20',
            'invoice_number' => 'WP-9921',
        ], 'westpack_invoice.pdf');

        $this->assertSame('Verpackungen', $enriched['category']);
        $this->assertTrue($enriched['is_business']);
        $this->assertSame(19, $enriched['tax_rate']);
        $this->assertSame(-14850, $enriched['amount_cents']);
        $this->assertSame('-148.50', $enriched['amount_formatted']);
        $this->assertStringNotContainsString(' – ', $enriched['title']);
        $this->assertStringStartsWith('buchhaltung/receipts/2026/11/2026-11-20_Westpack', $enriched['suggested_storage_path']);

        // 2. Livewire AccountingQuickEntry speichert logisch benannt & blockiert Dubletten
        $fakePdf = UploadedFile::fake()->createWithContent('rechnung.pdf', "%PDF-1.4\n%Testbeleg Westpack\n%%EOF");

        Livewire::actingAs($admin, 'admin')
            ->test(AccountingQuickEntry::class)
            ->set('specialTitle', 'Westpack - Schmuckboxen')
            ->set('specialCategory', 'Verpackungen')
            ->set('specialAmount', '-148.50')
            ->set('specialDate', '2026-11-20')
            ->set('specialLocation', 'Westpack')
            ->set('specialIsBusiness', true)
            ->set('specialTaxRate', 19)
            ->set('specialInvoiceNumber', 'WP-9921')
            ->set('specialFiles', [$fakePdf])
            ->call('createSpecial')
            ->assertHasNoErrors();

        $savedIssue = AccountingSpecialIssue::where('admin_id', $admin->id)->firstOrFail();
        $this->assertStringContainsString(BuchiReceiptPipelineService::LIVE_MARKER, (string) $savedIssue->note);
        $this->assertNotEmpty($savedIssue->file_paths);
        $this->assertStringStartsWith('buchhaltung/receipts/2026/11/2026-11-20_Westpack', $savedIssue->file_paths[0]);
        Storage::disk('local')->assertExists($savedIssue->file_paths[0]);

        // Zweiter Versuch mit derselben Rechnungsnummer wird durch den Dubletten-Schutz gestoppt
        Livewire::actingAs($admin, 'admin')
            ->test(AccountingQuickEntry::class)
            ->set('specialTitle', 'Westpack - Schmuckboxen Kopie')
            ->set('specialCategory', 'Verpackungen')
            ->set('specialAmount', '-148.50')
            ->set('specialDate', '2026-11-20')
            ->set('specialIsBusiness', true)
            ->set('specialInvoiceNumber', 'WP-9921')
            ->call('createSpecial')
            ->assertHasErrors(['specialTitle']);

        $this->assertSame(1, AccountingSpecialIssue::where('invoice_number', 'WP-9921')->count());

        // 3. Seeder-Falle gelöst: AccountingSeeder löscht [BUCHI-LIVE] Einträge NICHT weg!
        $this->seed(AccountingSeeder::class);
        $this->assertTrue(
            AccountingSpecialIssue::where('id', $savedIssue->id)->exists(),
            'Live-gescannte Belege mit [BUCHI-LIVE] müssen beim Ausführen von AccountingSeeder erhalten bleiben!'
        );
    }

    public function test_finance_scan_receipt_document_tool_previews_and_books_cleanly(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'merchant' => 'PrintEquipment GmbH',
                                'description' => 'Sublimations-Rohlinge & Kristallglas',
                                'title' => 'PrintEquipment - Kristallglas Rohlinge',
                                'amount' => 219.80,
                                'date' => '2026-11-20',
                                'location' => 'Ottersweier',
                                'category' => 'Einkauf',
                                'is_business' => true,
                                'tax_rate' => 19,
                                'invoice_number' => 'PE-2026-7712',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $relDir = 'agenten/workspace/chat-medien';
        $fullDir = storage_path('app/' . $relDir);
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0775, true);
        }
        $testFile = $fullDir . '/test_buchi_scan.pdf';
        file_put_contents($testFile, "%PDF-1.4\n%Mock Receipt\n%%EOF");

        try {
            // 1. Vorschau ohne Buchung (confirmed_by_alina = false) + Lieferantenregel (PrintEquipment -> Rohmaterial)
            $preview = AIFunctionsRegistry::execute('finance_scan_receipt_document', [
                'file_path' => $relDir . '/test_buchi_scan.pdf',
                'confirmed_by_alina' => false,
            ]);

            $this->assertSame('preview', $preview['status']);
            $this->assertSame('Rohmaterial', $preview['analysis']['category']);
            $this->assertSame(-21980, $preview['analysis']['amount_cents']);
            $this->assertSame(0, AccountingSpecialIssue::count());

            // 2. Freigabe durch Alina -> Buchung + logischer Dateipfad + Dubletten-Stopp beim Zweitaufruf
            $booked = AIFunctionsRegistry::execute('finance_scan_receipt_document', [
                'file_path' => $relDir . '/test_buchi_scan.pdf',
                'confirmed_by_alina' => true,
            ]);

            $this->assertSame('success', $booked['status']);
            $this->assertSame(1, AccountingSpecialIssue::count());
            $this->assertStringStartsWith('buchhaltung/receipts/2026/11/2026-11-20_PrintEquipment', (string) $booked['stored_file_path']);

            $dupAttempt = AIFunctionsRegistry::execute('finance_scan_receipt_document', [
                'file_path' => $relDir . '/test_buchi_scan.pdf',
                'confirmed_by_alina' => true,
            ]);
            $this->assertSame('duplicate_warning', $dupAttempt['status']);
        } finally {
            @unlink($testFile);
        }
    }

    public function test_orchestrator_dossier_gdpr_quality_gate_and_mail_autopilot(): void
    {
        // 1. DSGVO-Schutz im Bestell-Dossier (ohne Opt-in anonymisiert, mit Opt-in Klarname)
        $noConsentCustomer = Customer::factory()->create(['first_name' => 'Geheim', 'last_name' => 'Person']);
        $noConsentCustomer->profile()->updateOrCreate(
            ['customer_id' => $noConsentCustomer->id],
            ['ai_data_consent' => false, 'ai_data_consent_at' => null]
        );
        $orderAnon = OrderOrder::create([
            'order_number' => 'ORD-REAL1001',
            'customer_id' => $noConsentCustomer->id,
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal_price' => 4990,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 4990,
            'email' => $noConsentCustomer->email,
            'billing_address' => ['first_name' => 'Geheim', 'last_name' => 'Person', 'city' => 'Gifhorn'],
            'shipping_address' => ['first_name' => 'Geheim', 'last_name' => 'Person', 'city' => 'Gifhorn'],
        ]);

        $anonDossier = ContextDossierBuilder::buildOrderDossier('ORD-REAL1001');
        $this->assertNotNull($anonDossier);
        $this->assertFalse($anonDossier['gdpr_ai_consent']);
        $this->assertSame('Anonyme Person (Keine DSGVO-KI-Freigabe)', $anonDossier['customer_display']);
        $this->assertSame('49,90', $anonDossier['total_eur']);

        $noConsentCustomer->profile()->updateOrCreate(
            ['customer_id' => $noConsentCustomer->id],
            ['ai_data_consent' => true, 'ai_data_consent_at' => now()]
        );
        $consentDossier = ContextDossierBuilder::buildOrderDossier($orderAnon->fresh());
        $this->assertTrue($consentDossier['gdpr_ai_consent']);
        $this->assertStringContainsString('Geheim', $consentDossier['customer_display']);

        // 2. Deterministisches Quality Gate (Gedankenstriche, Material-Abmahnschutz & Halluzinations-Check)
        $rawAi = '**Hinweis** – Unser Echtgold Anhänger Made in Germany kommt im Samtbeutel zu ORD-REAL1001 und ORD-FAKE9999.';
        $gate = AiOutputQualityGate::inspectAndClean($rawAi);
        $this->assertFalse($gate['valid_orders']);
        $this->assertContains('ORD-FAKE9999', $gate['missing_order_numbers']);
        $this->assertStringNotContainsString(' – ', $gate['text']);
        $this->assertStringContainsString('14 Karat vergoldeter Edelstahl (Ion-Plating)', $gate['text']);
        $this->assertStringContainsString('Handveredelt in Deutschland (Manufaktur Gifhorn)', $gate['text']);
        $this->assertStringContainsString('Geschenkverpackung', $gate['text']);
        $this->assertStringContainsString('ORD-REAL1001', $gate['text']);
        $this->assertStringNotContainsString('ORD-FAKE9999', $gate['text']);

        // 3. RBAC & Isolation im SfInternalOrchestrator (Mitarbeiter darf keinen Admin-Buchi-Worker starten)
        $employee = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Mitarbeiter',
            'email' => 'max@seelenfunke.test',
            'role' => 'employee',
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);
        $this->actingAs($employee, 'employee');
        $denied = SfInternalOrchestrator::runSpecialistWorker('buchi', 'Prüfe Monatsgewinn');
        $this->assertSame('error', $denied['status']);

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        $okWorker = SfInternalOrchestrator::runSpecialistWorker('bestelli', 'Prüfe Auftragsstatus', [
            'order_number' => 'ORD-REAL1001',
        ]);
        $this->assertSame('success', $okWorker['status']);
        $this->assertLessThanOrEqual(6, $okWorker['tool_count']);
        $this->assertContains('order', $okWorker['dossiers_injected']);
        $this->assertStringContainsString('ORD-REAL1001', $okWorker['response']);

        // 4. Mail-Autopilot verknüpft eingehende Kundenmail mit Bestellung & erzeugt Antwort-Entwurf
        $account = MailAccount::create([
            'name' => 'Kontakt',
            'email' => 'kontakt@mein-seelenfunke.de',
            'imap_host' => 'localhost',
            'imap_port' => 993,
            'smtp_host' => 'localhost',
            'smtp_port' => 465,
            'password' => 'secret',
        ]);
        $mail = MailMessage::create([
            'mail_account_id' => $account->id,
            'message_id' => '<test-order-mail@seelenfunke.test>',
            'folder' => 'INBOX',
            'subject' => 'Frage zu meiner Bestellung ORD-REAL1001',
            'from_name' => 'Geheim Person',
            'from_email' => $noConsentCustomer->email,
            'to_email' => 'kontakt@mein-seelenfunke.de',
            'body_plain' => 'Hallo, wie ist der aktuelle Stand meiner Bestellung ORD-REAL1001?',
            'received_at' => now(),
        ]);

        app(MailAiSortService::class)->sortAllMailsAndSubfolders();
        $mail->refresh();

        $this->assertSame('Processed', $mail->folder);
        $this->assertNotEmpty($mail->tags['ai_reply_draft']['draft_text'] ?? null);
        $this->assertStringContainsString('ORD-REAL1001', $mail->tags['ai_reply_draft']['draft_text']);
    }

    public function test_android_api_scan_receipt_and_quick_entry_use_buchi_pipeline(): void
    {
        Storage::fake('local');

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'merchant' => 'Glücksfieber',
                                'description' => 'Edelstahl-Herzanhänger Rohlinge',
                                'title' => 'Glücksfieber – Herzanhänger Rohlinge',
                                'amount' => 89.40,
                                'date' => '2026-11-20',
                                'location' => 'Deutschland',
                                'category' => 'Sonstiges',
                                'is_business' => true,
                                'tax_rate' => 19,
                                'invoice_number' => 'GF-2026-881',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $admin = Admin::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($admin, ['*']);

        $file = UploadedFile::fake()->createWithContent('kamera_beleg.pdf', "%PDF-1.4\n%Android Camera Scan\n%%EOF");

        // 1. Android Auto-Save Scan -> Lieferantenregel (Glücksfieber -> Rohmaterial), logischer Pfad, [BUCHI-LIVE]
        $res = $this->postJson('/api/funki/financials/scan-receipt', [
            'file' => $file,
            'auto_save' => 'true',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('auto_saved', true)
            ->assertJsonPath('data.category', 'Rohmaterial');

        $issue = AccountingSpecialIssue::where('admin_id', $admin->id)->firstOrFail();
        $this->assertStringContainsString(BuchiReceiptPipelineService::LIVE_MARKER, (string) $issue->note);
        $this->assertStringStartsWith('buchhaltung/receipts/2026/11/2026-11-20_Gluecksfieber', $issue->file_paths[0]);

        // 2. Zweiter identischer Scan aus der App wird sauber per Dubletten-Schutz abgefangen (success=false + klare Meldung)
        $dupRes = $this->postJson('/api/funki/financials/scan-receipt', [
            'file' => $file,
            'auto_save' => 'true',
        ]);

        $dupRes->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('auto_saved', false);
        $this->assertStringContainsString('Dubletten-Schutz', (string) $dupRes->json('message'));
        $this->assertSame(1, AccountingSpecialIssue::where('admin_id', $admin->id)->count());
    }
}

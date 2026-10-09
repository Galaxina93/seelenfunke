<?php

namespace Tests\Feature\Services\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\Functions\AiSystemFuncs;
use App\Services\AI\Mails\AiAgentMessageMail;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;

class AiDeliveryNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_schema_contains_system_generate_delivery_note(): void
    {
        $schema = AiSystemFuncs::getAiSystemFuncsSchema();
        $this->assertIsArray($schema);

        $toolNames = array_column($schema, 'name');
        $this->assertContains('system_generate_delivery_note', $toolNames);

        $toolDef = collect($schema)->firstWhere('name', 'system_generate_delivery_note');
        $this->assertNotNull($toolDef);
        $this->assertEquals(['recipient_name', 'items'], $toolDef['parameters']['required']);

        $properties = $toolDef['parameters']['properties'];
        $this->assertArrayHasKey('recipient_name', $properties);
        $this->assertArrayHasKey('recipient_address', $properties);
        $this->assertArrayHasKey('items', $properties);
        $this->assertArrayHasKey('delivery_note_number', $properties);
        $this->assertArrayHasKey('delivery_date', $properties);
        $this->assertArrayHasKey('order_reference', $properties);
        $this->assertArrayHasKey('sender_info', $properties);
        $this->assertArrayHasKey('shipping_method', $properties);
        $this->assertArrayHasKey('notes', $properties);
        $this->assertArrayHasKey('design', $properties);
        $this->assertArrayHasKey('target_action', $properties);
        $this->assertArrayHasKey('recipient_email', $properties);
    }

    public function test_generates_delivery_note_seelenfunke_design_for_download(): void
    {
        $response = AIFunctionsRegistry::execute('system_generate_delivery_note', [
            'recipient_name' => 'Max Mustermann',
            'recipient_address' => "Musterstraße 1\n12345 Musterstadt",
            'items' => [
                ['name' => 'Holzbrett Gravur', 'quantity' => 2, 'unit' => 'Stk.', 'notes' => 'Gravur: Familie Muster'],
                ['name' => 'Duftkerze Zimt', 'quantity' => 1, 'unit' => 'Stk.']
            ],
            'delivery_note_number' => 'LS-TEST-1001',
            'delivery_date' => '09.10.2026',
            'order_reference' => 'Auftrag #994',
            'notes' => 'Vorsichtig behandeln, da echtes Naturholz.',
            'design' => 'seelenfunke',
            'target_action' => 'download'
        ]);

        $this->assertEquals('success', $response['status']);
        $this->assertStringContainsString('LS-TEST-1001', $response['message']);
        $this->assertEquals('LS-TEST-1001', $response['delivery_note_number']);
        $this->assertArrayHasKey('_event', $response);
        $this->assertEquals('dispatch', $response['_event']['type']);
        $this->assertEquals('download-file', $response['_event']['name']);
        $this->assertStringContainsString('lieferschein-seelenfunke-ls-test-1001', $response['_event']['detail']['filename']);

        // Verify PDF file was written to disk and has content
        $filePath = 'public/reports/' . $response['_event']['detail']['filename'];
        $this->assertTrue(Storage::exists($filePath));
        $this->assertGreaterThan(500, strlen(Storage::get($filePath)));
    }

    public function test_generates_delivery_note_generic_neutral_design_for_download(): void
    {
        $response = AIFunctionsRegistry::execute('system_generate_delivery_note', [
            'recipient_name' => 'Erika Privat',
            'recipient_address' => "Gartenweg 7\n54321 Kleinort",
            'sender_info' => 'Privatabgabe: Peter Schmidt, Am Wald 3',
            'items' => [
                ['name' => 'Gebrauchtes Fahrrad', 'quantity' => 1, 'unit' => 'Stk.', 'notes' => 'Inkl. Fahrradschloss']
            ],
            'delivery_note_number' => 'LS-PRIVAT-2001',
            'notes' => 'Privatverkauf ohne Rücknahme.',
            'design' => 'generic',
            'target_action' => 'download'
        ]);

        $this->assertEquals('success', $response['status']);
        $this->assertStringContainsString('generic', $response['message']);
        $this->assertEquals('LS-PRIVAT-2001', $response['delivery_note_number']);

        $filename = $response['_event']['detail']['filename'];
        $this->assertStringContainsString('lieferschein-neutral-ls-privat-2001', $filename);

        $filePath = 'public/reports/' . $filename;
        $this->assertTrue(Storage::exists($filePath));
        $this->assertGreaterThan(500, strlen(Storage::get($filePath)));
    }

    public function test_generates_delivery_note_and_sends_email(): void
    {
        Mail::fake();

        $response = AIFunctionsRegistry::execute('system_generate_delivery_note', [
            'recipient_name' => 'Spedition Müller',
            'items' => [
                ['name' => 'Palette Kartonagen', 'quantity' => 4, 'unit' => 'Paket']
            ],
            'design' => 'seelenfunke',
            'target_action' => 'email',
            'recipient_email' => 'dispo@mueller-spedition.de'
        ]);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals('dispo@mueller-spedition.de', $response['recipient']);
        $this->assertStringContainsString('dispo@mueller-spedition.de', $response['message']);

        Mail::assertSent(AiAgentMessageMail::class, function (AiAgentMessageMail $mail) {
            return $mail->hasTo('dispo@mueller-spedition.de')
                && str_contains($mail->messageSubject, 'Lieferschein')
                && count($mail->attachmentPaths) === 1
                && file_exists($mail->attachmentPaths[0]);
        });
    }

    public function test_delivery_note_email_falls_back_to_system_email(): void
    {
        Mail::fake();

        $response = AIFunctionsRegistry::execute('system_generate_delivery_note', [
            'recipient_name' => 'Interne Umlagerung',
            'items' => [
                ['name' => 'Werkzeugkasten', 'quantity' => 1, 'unit' => 'Stk.']
            ],
            'target_action' => 'email',
            'recipient_email' => null
        ]);

        $this->assertEquals('success', $response['status']);
        $this->assertNotEmpty($response['recipient']);

        Mail::assertSent(AiAgentMessageMail::class, function (AiAgentMessageMail $mail) use ($response) {
            return $mail->hasTo($response['recipient'])
                && count($mail->attachmentPaths) === 1;
        });
    }

    public function test_delivery_note_validation_errors(): void
    {
        // Missing recipient_name
        $resp1 = AIFunctionsRegistry::execute('system_generate_delivery_note', [
            'recipient_name' => '',
            'items' => [['name' => 'Test', 'quantity' => 1]]
        ]);
        $this->assertEquals('error', $resp1['status']);
        $this->assertStringContainsString('recipient_name', $resp1['message']);

        // Missing items
        $resp2 = AIFunctionsRegistry::execute('system_generate_delivery_note', [
            'recipient_name' => 'Max',
            'items' => []
        ]);
        $this->assertEquals('error', $resp2['status']);
        $this->assertStringContainsString('Position', $resp2['message']);
    }

    public function test_funkira_has_delivery_note_capability(): void
    {
        // Tool was already created by migration
        $tool = AiTool::where('identifier', 'system_generate_delivery_note')->first();
        $this->assertNotNull($tool, 'system_generate_delivery_note should be registered in ai_tools by default.');

        $role = AiRole::firstOrCreate(
            ['name' => 'Teamleiter'],
            ['description' => 'System-Leitung']
        );

        if (!$role->tools->contains('id', $tool->id)) {
            $role->tools()->attach($tool->id);
        }

        $funkira = AiAgent::firstOrCreate(
            ['name' => 'Funkira'],
            [
                'ai_role_id' => $role->id,
                'system_prompt' => 'Du bist Funkira. LIEFERSCHEINE: Du kannst Lieferscheine erstellen (system_generate_delivery_note).',
                'model' => 'gemini-3.5-flash',
                'temperature' => 0.1,
                'color' => 'sky-500',
                'icon' => 'sparkles'
            ]
        );

        $this->assertTrue($funkira->tools->contains('identifier', 'system_generate_delivery_note'));
        $this->assertStringContainsString('system_generate_delivery_note', $funkira->system_prompt);
    }
}

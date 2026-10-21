<?php

namespace Tests\Feature\Ai;

use App\Services\AI\Functions\AiMapControlFuncs;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiMapControlFuncsSecurityTest extends TestCase
{
    public function test_generate_pdf_summary_validates_recipient_email()
    {
        $dummy = new class {
            use AiMapControlFuncs;
        };

        // Invalid recipient email
        $resInvalid = $dummy::executeMapGeneratePdfSummary([
            'title' => 'Geodaten Übersicht',
            'target_action' => 'email',
            'recipient_email' => "malicious\r\ninjection@example.com",
            'locations' => ['Berlin', 'Hamburg'],
        ]);

        $this->assertEquals('error', $resInvalid['status']);
        $this->assertStringContainsString('Ungültige Empfänger-E-Mail-Adresse', $resInvalid['message']);
    }

    public function test_generate_places_pdf_validates_recipient_email()
    {
        $dummy = new class {
            use AiMapControlFuncs;
        };

        // Invalid recipient email
        $resInvalid = $dummy::executeMapGeneratePlacesPdf([
            'title' => 'Ortsliste',
            'target_action' => 'email',
            'recipient_email' => 'not-an-email',
            'places' => [],
        ]);

        $this->assertEquals('error', $resInvalid['status']);
        $this->assertStringContainsString('Ungültige Empfänger-E-Mail-Adresse', $resInvalid['message']);
    }
}

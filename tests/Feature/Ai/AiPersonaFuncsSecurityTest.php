<?php

namespace Tests\Feature\Ai;

use App\Services\AI\Functions\AiPersonaFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPersonaFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_url_blocks_ssrf_attacks()
    {
        $dummy = new class {
            use AiPersonaFuncs;
        };

        // 1. Localhost / loopback blocked
        $resLocalhost = $dummy::executePersonaFetchUrl([
            'url' => 'http://localhost:8000/admin',
        ]);
        $this->assertEquals('error', $resLocalhost['status']);
        $this->assertStringContainsString('SSRF-Schutz', $resLocalhost['message']);

        // 2. AWS / Cloud metadata IP blocked
        $resCloudMeta = $dummy::executePersonaFetchUrl([
            'url' => 'http://169.254.169.254/latest/meta-data/',
        ]);
        $this->assertEquals('error', $resCloudMeta['status']);
        $this->assertStringContainsString('SSRF-Schutz', $resCloudMeta['message']);

        // 3. Private IP range blocked
        $resPrivateIp = $dummy::executePersonaFetchUrl([
            'url' => 'http://192.168.1.1/router-login',
        ]);
        $this->assertEquals('error', $resPrivateIp['status']);
        $this->assertStringContainsString('SSRF-Schutz', $resPrivateIp['message']);
    }

    public function test_generate_pdf_validates_email_and_delete_intel_requires_title()
    {
        $dummy = new class {
            use AiPersonaFuncs;
        };

        // 1. Invalid email rejected
        $resInvalidEmail = $dummy::executePersonaGeneratePdf([
            'name' => 'Max Mustermann',
            'target_action' => 'email',
            'recipient_email' => 'invalid-email-address',
        ]);
        $this->assertEquals('error', $resInvalidEmail['status']);
        $this->assertStringContainsString('Ungültige Empfänger-E-Mail-Adresse', $resInvalidEmail['message']);

        // 2. Empty title in delete intel rejected
        $resEmptyDelete = $dummy::executePersonaDeleteIntel([]);
        $this->assertEquals('error', $resEmptyDelete['status']);
        $this->assertStringContainsString('fehlt', $resEmptyDelete['message']);
    }
}

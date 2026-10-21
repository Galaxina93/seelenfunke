<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;
use App\Services\AI\Functions\AiSystemFuncs;

class AiSystemFuncsSecurityTest extends TestCase
{
    private $mockClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClass = new class {
            use AiSystemFuncs;

            public static function callIsSafeExternalUrl(string $url): bool
            {
                $method = new \ReflectionMethod(static::class, 'isSafeSystemExternalUrl');
                $method->setAccessible(true);
                return $method->invoke(null, $url);
            }
        };
    }

    public function test_ssrf_rejects_dangerous_internal_urls(): void
    {
        $this->assertFalse($this->mockClass::callIsSafeExternalUrl('http://localhost:8000'));
        $this->assertFalse($this->mockClass::callIsSafeExternalUrl('http://127.0.0.1/admin'));
        $this->assertFalse($this->mockClass::callIsSafeExternalUrl('http://169.254.169.254/latest/meta-data'));
        $this->assertFalse($this->mockClass::callIsSafeExternalUrl('file:///etc/passwd'));
        $this->assertFalse($this->mockClass::callIsSafeExternalUrl('gopher://127.0.0.1:25'));

        $res = $this->mockClass::executeReadWebUrl(['url' => 'http://127.0.0.1:8000/internal']);
        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('SSRF', $res['message']);
    }

    public function test_send_neural_report_mail_rejects_path_traversal(): void
    {
        $res = $this->mockClass::executeSendNeuralReportMail([
            'report_file' => '../../../../.env',
            'email' => 'admin@example.com'
        ]);

        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('Ungültiger', $res['message']);
    }

    public function test_send_neural_report_mail_rejects_crlf_email_injection(): void
    {
        $res = $this->mockClass::executeSendNeuralReportMail([
            'report_file' => 'valid-report.md',
            'email' => "admin@example.com\r\nBcc: evil@example.com"
        ]);

        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('Ungültige', $res['message']);
    }
}

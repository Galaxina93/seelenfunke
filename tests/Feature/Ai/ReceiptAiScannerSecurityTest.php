<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;
use App\Services\AI\ReceiptAiScannerService;

class ReceiptAiScannerSecurityTest extends TestCase
{
    public function test_analyze_rejects_path_traversal(): void
    {
        $res = ReceiptAiScannerService::analyze('../../../../etc/passwd');
        $this->assertNull($res);
    }

    public function test_analyze_rejects_dangerous_file_extensions(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_receipt_') . '.php';
        file_put_contents($tmpFile, '<?php echo "evil";');

        try {
            $res = ReceiptAiScannerService::analyze($tmpFile);
            $this->assertNull($res);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    public function test_normalize_result_clamps_unreasonable_amounts_and_truncates_strings(): void
    {
        $method = new \ReflectionMethod(ReceiptAiScannerService::class, 'normalizeResult');
        $method->setAccessible(true);

        $hugeRaw = [
            'merchant' => str_repeat('Bauhaus ', 50),
            'title' => str_repeat('Langer Titel ', 30),
            'amount' => 99999999999.99, // Unreasonable amount from barcode misread
            'category' => str_repeat('Kategorie ', 20),
            'date' => '2026-05-10',
            'is_business' => true,
        ];

        $normalized = $method->invoke(null, $hugeRaw);

        $this->assertTrue($normalized['success']);
        // Amount should be clamped to reasonable max
        $this->assertLessThanOrEqual(9999999.99, $normalized['amount_abs']);
        // Length of title and merchant must not exceed column limits
        $this->assertLessThanOrEqual(255, mb_strlen($normalized['title']));
        $this->assertLessThanOrEqual(255, mb_strlen($normalized['merchant']));
        $this->assertLessThanOrEqual(100, mb_strlen($normalized['category']));
    }
}

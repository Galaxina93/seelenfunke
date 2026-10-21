<?php

namespace Tests\Feature\Api;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Services\AI\Mails\AiAgentMessageMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiLoggingAndReceiptSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_websocket_error_mail_is_throttled_to_prevent_inbox_bombing(): void
    {
        Mail::fake();

        // 1. First error request -> should trigger mail
        $response1 = $this->postJson('/api/log/websocket', [
            'type' => 'error',
            'message' => 'Connection dropped unexpectedly',
        ]);
        $response1->assertOk();

        // 2. Second immediate error request with identical message -> should be throttled, no second mail
        $response2 = $this->postJson('/api/log/websocket', [
            'type' => 'error',
            'message' => 'Connection dropped unexpectedly',
        ]);
        $response2->assertOk();

        Mail::assertSent(AiAgentMessageMail::class, 1);
    }

    public function test_unauthorized_user_cannot_access_funki_financials_receipt(): void
    {
        // 1. Guest
        $this->getJson('/api/funki/financials/receipt?path=test.pdf')->assertStatus(401);

        // 2. Customer
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer, ['*']);
        $this->getJson('/api/funki/financials/receipt?path=test.pdf')->assertStatus(403);
    }

    public function test_funki_receipt_forces_attachment_and_nosniff_header_for_non_image_or_pdf(): void
    {
        Storage::fake('local');
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        // Create a text/html file
        $receiptPath = 'buchhaltung/receipts/test_beleg.html';
        Storage::disk('local')->put($receiptPath, '<html><script>alert(1)</script></html>');

        $response = $this->get('/api/funki/financials/receipt?path=' . $receiptPath);

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', (string)$response->headers->get('Content-Disposition'));
    }

    public function test_funki_receipt_allows_inline_for_safe_pdf(): void
    {
        Storage::fake('local');
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        // Create a dummy PDF
        $receiptPath = 'buchhaltung/receipts/rechnung_test.pdf';
        Storage::disk('local')->put($receiptPath, '%PDF-1.4 test dummy');

        $response = $this->get('/api/funki/financials/receipt?path=' . $receiptPath);

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline', (string)$response->headers->get('Content-Disposition'));
    }
}

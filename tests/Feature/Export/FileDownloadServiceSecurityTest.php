<?php

namespace Tests\Feature\Export;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiHealthTreatmentPlan;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\Export\FileDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileDownloadServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_download_health_treatment_plan()
    {
        $admin = Admin::factory()->create();
        $agent = AiAgent::create(['id' => Str::uuid(), 'name' => 'Health AI', 'role' => 'health']);

        $plan = AiHealthTreatmentPlan::create([
            'id' => Str::uuid(),
            'user_id' => $admin->id,
            'ai_agent_id' => $agent->id,
            'title' => 'Geheimer Behandlungsplan',
            'status' => 'active',
        ]);

        $service = new FileDownloadService();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Zugriff verweigert.');

        $service->downloadHealthTreatmentPlanPdf($plan->id);
    }

    public function test_guest_cannot_download_product_analytics_reports()
    {
        $service = new FileDownloadService();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Zugriff verweigert.');

        $service->downloadProductAnalyticsFullReportPdf();
    }

    public function test_laser_svg_download_checks_authorization_and_sets_csp()
    {
        $admin = Admin::factory()->create();
        $customer = Customer::factory()->create();

        $product = Product::create([
            'name' => 'Laser Holzschild',
            'slug' => 'laser-holz-' . Str::random(6),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2500,
            'width' => 150,
            'height' => 100,
        ]);

        $order = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-LASER-TEST-99',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'paid',
            'payment_status' => 'paid',
            'subtotal_price' => 2500,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 2500,
            'billing_address' => ['first_name' => 'Test', 'last_name' => 'User'],
        ]);

        $item = OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => [
                'texts' => [
                    ['text' => 'Laser Text', 'font' => 'Arial', 'size' => 1, 'align' => 'center']
                ]
            ]
        ]);

        $service = new FileDownloadService();

        // 1. Guest is blocked
        Auth::logout();
        try {
            $service->downloadLaserSvg($item->id);
            $this->fail('Expected 403 HttpException for unauthenticated caller');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        // 2. Authenticated Admin is allowed and response has security headers
        Auth::guard('admin')->login($admin);
        $response = $service->downloadLaserSvg($item->id);
        $this->assertEquals('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertEquals("default-src 'none'; sandbox", $response->headers->get('Content-Security-Policy'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }
}

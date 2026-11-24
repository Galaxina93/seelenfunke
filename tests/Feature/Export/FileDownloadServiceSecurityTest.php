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
        $this->assertEquals("default-src 'none'; img-src data:; font-src data:; style-src 'unsafe-inline'; sandbox", $response->headers->get('Content-Security-Policy'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_laser_svg_blocks_local_file_inclusion_path_traversal_in_logos()
    {
        $admin = Admin::factory()->create();
        $customer = Customer::factory()->create();

        $product = Product::create([
            'name' => 'Laser Schieferplatte',
            'slug' => 'laser-schiefer-' . Str::random(6),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2500,
            'width' => 150,
            'height' => 100,
        ]);

        $order = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-LFI-TEST-01',
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
                'logos' => [
                    ['url' => '/storage/../../../.env', 'x' => 50, 'y' => 50, 'size' => 100]
                ]
            ]
        ]);

        Auth::guard('admin')->login($admin);
        $service = new FileDownloadService();
        $response = $service->downloadLaserSvg($item->id);

        ob_start();
        $response->sendContent();
        $svgOutput = ob_get_clean();

        $this->assertStringNotContainsString('data:image/env;base64,', $svgOutput);
        $this->assertDatabaseHas('system_logs', [
            'type' => 'security',
            'action_id' => 'security:lfi_attempt_blocked',
        ]);
    }

    public function test_clip_and_normalize_raster_logo_enforces_memory_budget_on_large_blanks_and_hi_res_images(): void
    {
        $service = new FileDownloadService();

        // Hochaufloesendes 3000x2400 Bild (ohne Downscaling ~29 MB GD-Rohpuffer je Instanz)
        $hiRes = imagecreatetruecolor(3000, 2400);
        $this->assertNotFalse($hiRes);
        $dark = imagecolorallocate($hiRes, 30, 30, 30);
        imagefill($hiRes, 0, 0, $dark);
        ob_start();
        imagepng($hiRes, null, 1);
        $hiResBytes = (string) ob_get_clean();
        imagedestroy($hiRes);

        $poly = [
            ['x' => 5.0, 'y' => 5.0],
            ['x' => 95.0, 'y' => 5.0],
            ['x' => 95.0, 'y' => 95.0],
            ['x' => 5.0, 'y' => 95.0],
        ];

        $clipped = $service->clipAndNormalizeRasterLogoForLaser(
            $hiResBytes,
            'png',
            ['x' => 50.0, 'y' => 50.0, 'size' => 250.0, 'rotation' => 15.0],
            600.0,
            600.0,
            $poly
        );

        $this->assertNotNull($clipped);
        $this->assertLessThanOrEqual(1400, $clipped['pixelWidth']);
        $this->assertLessThanOrEqual(1400, $clipped['pixelHeight']);
        $this->assertLessThanOrEqual(1_600_000, $clipped['pixelWidth'] * $clipped['pixelHeight']);
        $this->assertNotEmpty($clipped['pngData']);
    }

    public function test_clip_and_normalize_raster_logo_and_normalize_helper_guard_invalid_inputs_and_oversized_rasters(): void
    {
        $service = new class extends FileDownloadService {
            public function exposedNormalizeRaster(string $bytes, ?string $ext = null): array
            {
                return $this->normalizeRasterImageForLaser($bytes, $ext);
            }

            public function exposedRenderFullStage(
                float $widthMm,
                float $heightMm,
                array $poly,
                array $logos,
                array $texts,
                bool $isDark = false,
                ?array $photoProfile = null
            ): array {
                return $this->renderFullStageLaserPng($widthMm, $heightMm, $poly, $logos, $texts, null, $isDark, $photoProfile);
            }
        };

        $tiny = imagecreatetruecolor(32, 32);
        $black = imagecolorallocate($tiny, 0, 0, 0);
        imagefill($tiny, 0, 0, $black);
        ob_start();
        imagepng($tiny);
        $tinyBytes = (string) ob_get_clean();
        imagedestroy($tiny);

        $validPoly = [
            ['x' => 10, 'y' => 10],
            ['x' => 90, 'y' => 10],
            ['x' => 90, 'y' => 90],
            ['x' => 10, 'y' => 90],
        ];

        // Ungueltige Masse, kaputte Polygone und defekte Bytes duerfen niemals abstuerzen
        $this->assertNull($service->clipAndNormalizeRasterLogoForLaser($tinyBytes, 'png', [], 0.0, 50.0, $validPoly));
        $this->assertNull($service->clipAndNormalizeRasterLogoForLaser($tinyBytes, 'png', [], 50.0, -10.0, $validPoly));
        $this->assertNull($service->clipAndNormalizeRasterLogoForLaser($tinyBytes, 'png', [], 50.0, 50.0, [['foo' => 'bar']]));
        $this->assertNull($service->clipAndNormalizeRasterLogoForLaser('not-an-image', 'png', [], 50.0, 50.0, $validPoly));

        // normalizeRasterImageForLaser skaliert Uebergroessen > 2048 px speicherschonend herunter
        $big = imagecreatetruecolor(2600, 1800);
        $bg = imagecolorallocate($big, 40, 40, 40);
        imagefill($big, 0, 0, $bg);
        ob_start();
        imagepng($big, null, 1);
        $bigBytes = (string) ob_get_clean();
        imagedestroy($big);

        [$normBytes, $normExt] = $service->exposedNormalizeRaster($bigBytes, 'png');
        $this->assertSame('png', $normExt);
        $normInfo = @getimagesizefromstring($normBytes);
        $this->assertIsArray($normInfo);
        $this->assertLessThanOrEqual(2048, $normInfo[0]);
        $this->assertLessThanOrEqual(2048, $normInfo[1]);

        // renderFullStageLaserPng darf bei Grossrohlingen (500x500 mm) das Pixel-Budget nicht sprengen
        $stage = $service->exposedRenderFullStage(
            500.0,
            500.0,
            $validPoly,
            [],
            [['text' => 'TEST', 'x' => 50, 'y' => 50, 'size' => 2.0]],
            true,
            ['polarity' => 'white_on_dark', 'is_dark_surface' => true, 'dpi' => 600]
        );

        $this->assertLessThanOrEqual(2_822_400, $stage['pixelWidth'] * $stage['pixelHeight']);
        $this->assertGreaterThan(0.0, $stage['pxPerMm']);
        $this->assertNotEmpty($stage['pngBytes']);
        $this->assertNotEmpty($stage['previewPngBytes']);
    }
}

<?php

namespace Tests\Feature\Order;

use App\Livewire\Shop\Order\OrderOverview;
use App\Models\Admin\Admin;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderShipment;
use App\Models\Product\Product;
use App\Services\System\FunkenwerkPrintService;
use Database\Seeders\ProductPackagingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class GrossbriefMunbynLabelTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): Admin
    {
        $admin = Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-' . Str::random(5) . '@mein-seelenfunke.de',
            'password' => bcrypt('secret123'),
        ]);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function createPostalVoucherOrder(): OrderOrder
    {
        $voucherProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'type' => 'digital',
            'price' => 0,
            'status' => 'active',
            'weight' => 25,
            'packaging_weight' => 15,
            'shipping_class' => 'brief',
        ]);

        $order = OrderOrder::create([
            'order_number' => 'SF-BRIEF-1001',
            'email' => 'kunde@example.de',
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal_price' => 5000,
            'tax_amount' => 0,
            'shipping_price' => 450,
            'total_price' => 5450,
            'billing_address' => [
                'first_name' => 'Clara',
                'last_name' => 'Sommer',
                'address' => 'Rosenweg 8',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Clara',
                'last_name' => 'Sommer',
                'address' => 'Rosenweg 8',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
        ]);

        $order->items()->create([
            'product_id' => $voucherProduct->id,
            'product_name' => 'Geschenkgutschein',
            'quantity' => 1,
            'unit_price' => 5000,
            'total_price' => 5000,
            'configuration' => [
                'is_gift_voucher' => true,
                'amount_cents' => 5000,
                'delivery_method' => 'post',
                'recipient_name' => 'Max Sommer',
                'shipping_surcharge' => 450,
            ],
        ]);

        return $order->fresh(['items.product', 'shipments']);
    }

    public function test_postal_voucher_order_recommends_vollpapp_envelope_and_defaults_modal_to_brief(): void
    {
        $this->seed(ProductPackagingSeeder::class);
        $this->createAdmin();
        $order = $this->createPostalVoucherOrder();

        $this->assertTrue($order->isPostalVoucherOnly());
        $this->assertTrue($order->isBriefEligibleOrder());

        $box = $order->resolveRecommendedShippingBox();
        $this->assertSame('PKG-ENV-VOUCHER', $box['sku']);
        $this->assertSame('Vollpapp-Versandtasche DIN A5', $box['box_name']);
        $this->assertSame('250 × 175 × 5 mm', $box['box_dimensions']);

        Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $order->id)
            ->assertSet('shippingLabelMode', 'brief')
            ->assertSet('dhlPackagingType', 'envelope')
            ->assertSet('briefRecipientPreview.name', 'Clara Sommer')
            ->assertSet('briefRecipientPreview.postal_code', '38518');
    }

    public function test_admin_can_generate_munbyn_4x6_grossbrief_label_and_queue_for_printing(): void
    {
        Storage::fake('local');
        $this->seed(ProductPackagingSeeder::class);
        $this->createAdmin();
        $order = $this->createPostalVoucherOrder();

        $component = Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $order->id)
            ->set('briefPortoCode', '#PORTO 1mv5-ter4')
            ->call('generateGrossbriefLabel');

        $component->assertSet('dhlError', null)
            ->assertSet('dhlModalOrderId', null);

        $order->refresh();
        $this->assertSame('shipped', $order->status);

        $shipment = OrderShipment::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('deutsche_post_brief', $shipment->carrier);
        $this->assertSame('#PORTO-1MV5TER4', $shipment->tracking_number);
        $this->assertSame('bestellungen/brief_labels/brief_SF-BRIEF-1001.pdf', $shipment->shipping_label_path);

        Storage::disk('local')->assertExists($shipment->shipping_label_path);
        $pdfBytes = Storage::disk('local')->get($shipment->shipping_label_path);
        $this->assertStringStartsWith('%PDF-', $pdfBytes);

        // Funkenwerk MUNBYN queue must receive valid PDF Base64
        $queue = app(FunkenwerkPrintService::class)->getQueueState(true);
        $this->assertGreaterThanOrEqual(1, $queue['pending_count']);
        $job = collect($queue['pending'])->firstWhere('order_number', 'SF-BRIEF-1001');
        $this->assertNotNull($job);
        $this->assertTrue($job['has_pdf']);
        $this->assertStringStartsWith('%PDF-', base64_decode((string) $job['pdf_base64']));

        // Internal #PORTO reference must NOT be rendered as trackable DHL piececode in customer mails
        $formatted = $order->fresh(['items.product', 'shipments'])->toFormattedArray();
        $this->assertTrue($formatted['has_brief_shipment']);
        $this->assertSame([], $formatted['tracking_numbers']);

        // PDF download must allow brief_labels directory
        Livewire::test(OrderOverview::class)
            ->call('downloadDhlLabel', $shipment->id)
            ->assertFileDownloaded('brief_SF-BRIEF-1001.pdf');
    }

    public function test_external_tracking_number_for_brief_is_preserved_for_customer_tracking_while_digital_orders_are_blocked(): void
    {
        Storage::fake('local');
        $this->createAdmin();
        $order = $this->createPostalVoucherOrder();

        Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $order->id)
            ->set('briefTrackingNumber', 'A0034999111222')
            ->call('generateGrossbriefLabel')
            ->assertSet('dhlError', null);

        $formatted = $order->fresh(['items.product', 'shipments'])->toFormattedArray();
        $this->assertTrue($formatted['has_brief_shipment']);
        $this->assertSame(['A0034999111222'], $formatted['tracking_numbers']);

        // Digital-only email voucher order cannot generate a Großbrief label
        $emailOrder = OrderOrder::create([
            'order_number' => 'SF-DIG-9001',
            'email' => 'dig@example.de',
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal_price' => 2500,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 2500,
            'billing_address' => [
                'first_name' => 'Eva',
                'last_name' => 'Klein',
                'address' => 'Bergweg 2',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
        ]);
        $digitalProduct = Product::where('slug', 'geschenkgutschein')->firstOrFail();
        $emailOrder->items()->create([
            'product_id' => $digitalProduct->id,
            'product_name' => 'Geschenkgutschein',
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => [
                'is_gift_voucher' => true,
                'delivery_method' => 'email',
            ],
        ]);

        Livewire::test(OrderOverview::class)
            ->call('openDhlModal', $emailOrder->id)
            ->call('generateGrossbriefLabel')
            ->assertSet('dhlError', 'Für rein digitale Bestellungen können keine Großbrief-Labels erstellt werden.');
    }
}

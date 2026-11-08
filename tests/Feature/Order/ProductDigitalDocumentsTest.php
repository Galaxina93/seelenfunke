<?php

namespace Tests\Feature\Order;

use App\Livewire\Customer\CustomerOrdersComponent;
use App\Livewire\Shop\Product\ProductCreate;
use App\Mail\NewOrderMailToCustomer;
use App\Mail\NewOrderShippedToCustomer;
use App\Mail\PaymentReceivedMail;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductDigitalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_rename_and_remove_digital_documents_on_physical_product(): void
    {
        Storage::fake('local');

        $admin = Admin::factory()->create();
        $product = Product::create([
            'name' => 'Goldenes Seelen-Herz',
            'slug' => 'goldenes-seelen-herz',
            'type' => 'physical',
            'price' => 2990,
            'purchase_price' => 450,
            'sku' => 'MSF-HERZ-001',
            'status' => 'active',
        ]);

        $pdfFile = UploadedFile::fake()->create('echtheitszertifikat.pdf', 120, 'application/pdf');

        $component = Livewire::actingAs($admin, 'admin')
            ->test(ProductCreate::class)
            ->call('edit', $product->id)
            ->call('setDigitalDocumentPresetTitle', 'Echtheitszertifikat')
            ->set('new_digital_documents', [$pdfFile]);

        $product->refresh();
        $this->assertTrue($product->hasDigitalDocuments());

        $docs = $product->getDigitalDocuments();
        $this->assertCount(1, $docs);
        $this->assertSame('Echtheitszertifikat', $docs[0]['title']);
        $this->assertSame('pdf', $docs[0]['extension']);
        $this->assertStringStartsWith('produkte/products-secure/goldenes-seelen-herz/documents/', $docs[0]['path']);
        Storage::disk('local')->assertExists($docs[0]['path']);

        // Ensure saving the product preserves digital_documents in configurator_settings
        $component->call('save', false);
        $product->refresh();
        $this->assertCount(1, $product->getDigitalDocuments());

        // Rename document title
        $docId = $docs[0]['id'];
        $component->call('updateDigitalDocumentTitle', $docId, 'Manufaktur-Echtheitszertifikat');
        $product->refresh();
        $this->assertSame('Manufaktur-Echtheitszertifikat', $product->getDigitalDocuments()[0]['title']);

        // Remove document and verify file cleanup on local disk
        $storedPath = $docs[0]['path'];
        $component->call('removeDigitalDocument', $docId);
        $product->refresh();
        $this->assertFalse($product->hasDigitalDocuments());
        Storage::disk('local')->assertMissing($storedPath);
    }

    public function test_customer_portal_displays_digital_documents_under_dokumente_and_enforces_secure_download(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);
        $customer->profile()->updateOrCreate(
            ['customer_id' => $customer->id],
            ['email_verified_at' => now()]
        );

        $otherCustomer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);
        $otherCustomer->profile()->updateOrCreate(
            ['customer_id' => $otherCustomer->id],
            ['email_verified_at' => now()]
        );

        $docPath = 'produkte/products-secure/seelen-kristall/documents/zertifikat.pdf';
        Storage::disk('local')->put($docPath, '%PDF-1.4 Fake Certificate Content');

        $product = Product::create([
            'name' => '3D Seelen-Kristall',
            'slug' => 'seelen-kristall',
            'type' => 'physical',
            'price' => 4990,
            'status' => 'active',
            'configurator_settings' => [
                'digital_documents' => [
                    [
                        'id' => 'doc_cert_001',
                        'title' => 'Echtheitszertifikat',
                        'path' => $docPath,
                        'filename' => 'zertifikat.pdf',
                        'extension' => 'pdf',
                        'size_bytes' => 32768,
                        'uploaded_at' => '06.11.2026 10:00',
                    ],
                ],
            ],
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-2026-CERT01',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'processing',
            'payment_status' => 'unpaid',
            'billing_address' => [
                'first_name' => 'Alina',
                'last_name' => 'Testerin',
                'address' => 'Testweg 1',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Alina',
                'last_name' => 'Testerin',
                'address' => 'Testweg 1',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'subtotal_price' => 4990,
            'tax_amount' => 797,
            'shipping_price' => 0,
            'total_price' => 4990,
        ]);

        $item = OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 4990,
            'total_price' => 4990,
            'tax_rate' => 19.0,
            'configuration' => [
                'digital_documents' => $product->getDigitalDocuments(),
            ],
        ]);

        $this->assertTrue($order->hasDigitalDocuments());
        $this->assertCount(1, $item->resolveDigitalDocuments());

        // 1. Unpaid order: Document is visible in Customer Portal under "Dokumente" with "Nach Zahlung", download is blocked (403)
        Livewire::actingAs($customer, 'customer')
            ->test(CustomerOrdersComponent::class)
            ->call('showOrder', $order->id)
            ->assertSee('Dokumente')
            ->assertSee('Echtheitszertifikat')
            ->assertSee('Nach Zahlung');

        $downloadUrl = route('digital-product.download', ['item' => $item->id, 'doc' => 'doc_cert_001']);

        $this->actingAs($customer, 'customer')
            ->get($downloadUrl)
            ->assertStatus(403);

        // 2. Mark order as paid: Download link is active and returns 200 for the owner
        $order->update(['payment_status' => 'paid']);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerOrdersComponent::class)
            ->call('showOrder', $order->id)
            ->assertSee('Echtheitszertifikat')
            ->assertSee($downloadUrl);

        $response = $this->actingAs($customer, 'customer')
            ->get($downloadUrl);

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        // 3. Foreign customer cannot download the document (403 IDOR protection)
        $this->actingAs($otherCustomer, 'customer')
            ->get($downloadUrl)
            ->assertStatus(403);
    }

    public function test_order_emails_contain_professional_digital_documents_notice_when_present(): void
    {
        $productWithDocs = Product::create([
            'name' => 'Kristall-Unikat mit Zertifikat',
            'slug' => 'kristall-unikat-mit-zertifikat',
            'type' => 'physical',
            'price' => 3990,
            'status' => 'active',
            'configurator_settings' => [
                'digital_documents' => [
                    [
                        'id' => 'doc_cert_99',
                        'title' => 'Echtheitszertifikat',
                        'path' => 'produkte/products-secure/kristall-unikat-mit-zertifikat/documents/cert.pdf',
                        'filename' => 'cert.pdf',
                        'extension' => 'pdf',
                    ],
                ],
            ],
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-2026-MAIL01',
            'email' => 'kunde@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
            'billing_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'address' => 'Hauptstr. 1',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'address' => 'Hauptstr. 1',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'subtotal_price' => 3990,
            'tax_amount' => 637,
            'shipping_price' => 0,
            'total_price' => 3990,
        ]);

        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $productWithDocs->id,
            'product_name' => $productWithDocs->name,
            'quantity' => 1,
            'unit_price' => 3990,
            'total_price' => 3990,
            'tax_rate' => 19.0,
        ]);

        $formattedData = $order->fresh(['items.product'])->toFormattedArray();
        $this->assertTrue($formattedData['has_digital_documents']);
        $this->assertCount(1, $formattedData['digital_documents_list']);

        $newOrderHtml = (new NewOrderMailToCustomer($formattedData))->render();
        $this->assertStringContainsString('Digitale Dokumente zu deiner Bestellung:', $newOrderHtml);
        $this->assertStringContainsString('Zu deiner Bestellung gehören digitale Begleitdokumente.', $newOrderHtml);
        $this->assertStringContainsString('Dokumente', $newOrderHtml);

        $paymentHtml = (new PaymentReceivedMail($formattedData))->render();
        $this->assertStringContainsString('Digitale Dokumente zu deiner Bestellung:', $paymentHtml);

        $shippedHtml = (new NewOrderShippedToCustomer($formattedData))->render();
        $this->assertStringContainsString('Digitale Dokumente zu deiner Bestellung:', $shippedHtml);
    }
}

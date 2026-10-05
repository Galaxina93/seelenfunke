<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Order\OrderQuoteRequestItem;
use App\Models\Product\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuoteMailPdfImageRenderingTest extends TestCase
{
    use DatabaseTransactions;

    protected Product $product;
    protected string $testSnapshotRel = 'system/snapshots/quote_test_snapshot_front.jpg';
    protected string $testProductImgRel = 'produkte/quote_test_catalog_image.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        // 1x1 Pixel Dummy JPEG
        $dummyJpeg = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAAAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AP//Z');

        Storage::disk('public')->put($this->testSnapshotRel, $dummyJpeg);
        Storage::disk('public')->put($this->testProductImgRel, $dummyJpeg);

        $this->product = Product::create([
            'name' => 'Der Seelen Kristall Test',
            'slug' => 'seelen-kristall-test-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 3990,
            'weight' => 0.4,
            'is_personalizable' => true,
            'three_d_active' => true,
            'three_d_model_path' => 'produkte/products/seelen-kristall/t_seelenk.glb',
            'preview_image_path' => $this->testProductImgRel,
            'media_gallery' => [
                [
                    'alt' => 'Seelen Kristall Frontansicht',
                    'path' => $this->testProductImgRel,
                    'type' => 'image',
                    'is_main' => true,
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Storage::disk('public')->delete($this->testSnapshotRel);
        Storage::disk('public')->delete($this->testProductImgRel);
        parent::tearDown();
    }

    #[Test]
    public function quote_renders_only_3d_snapshot_and_no_catalog_fallback_when_snapshot_exists()
    {
        // Quote-Request mit 3D-Snapshot erstellen
        $quote = OrderQuoteRequest::create([
            'quote_number' => 'AN-TEST-' . uniqid(),
            'email' => 'kunde@test.de',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'status' => 'open',
            'net_total' => 3353,
            'tax_total' => 637,
            'gross_total' => 3990,
        ]);

        OrderQuoteRequestItem::create([
            'quote_request_id' => $quote->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 3990,
            'tax_rate' => 19.00,
            'total_price' => 3990,
            'configuration' => [
                'text' => 'Frohe Weihnachten! - Familie Mustermann -',
                'snapshot_path' => [
                    'front' => $this->testSnapshotRel,
                ],
            ],
        ]);

        $formattedData = $quote->toFormattedArray();

        // 1. Mail-Ansicht prüfen (isPdf = false)
        $mailHtml = view('global.mails.partials.mail_item_list', [
            'data' => $formattedData,
            'isPdf' => false,
        ])->render();

        $this->assertStringContainsString('storage/' . $this->testSnapshotRel, $mailHtml);
        $this->assertStringNotContainsString($this->testProductImgRel, $mailHtml, 'Produktbild darf nicht geladen werden wenn ein 3D Snapshot existiert.');

        // 2. PDF-Ansicht prüfen (isPdf = true -> muss Base64 Data URI sein)
        $pdfHtml = view('global.mails.partials.mail_item_list', [
            'data' => $formattedData,
            'isPdf' => true,
        ])->render();

        $this->assertStringContainsString('data:image/', $pdfHtml);
        $this->assertStringNotContainsString($this->testProductImgRel, $pdfHtml, 'Produktbild darf im PDF nicht als Fallback geladen werden wenn Snapshot existiert.');

        // 3. Volles Angebot-PDF mit DomPDF ohne Fehler rendern
        $pdf = Pdf::loadView('global.mails.calculation_pdf_template', ['data' => $formattedData, 'isPdf' => true]);
        $output = $pdf->output();
        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF-', $output);
    }

    #[Test]
    public function quote_renders_snapshot_even_when_legacy_path_missing_system_prefix()
    {
        $legacySnapshotPath = 'snapshots/quote_test_legacy.jpg';
        $dummyJpeg = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAAAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AP//Z');
        Storage::disk('public')->put('system/' . $legacySnapshotPath, $dummyJpeg);

        try {
            $quote = OrderQuoteRequest::create([
                'quote_number' => 'AN-LEGACY-' . uniqid(),
                'email' => 'kunde@test.de',
                'first_name' => 'Anna',
                'last_name' => 'Muster',
                'status' => 'open',
                'net_total' => 3353,
                'tax_total' => 637,
                'gross_total' => 3990,
            ]);

            OrderQuoteRequestItem::create([
                'quote_request_id' => $quote->id,
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
                'quantity' => 1,
                'unit_price' => 3990,
                'tax_rate' => 19.00,
                'total_price' => 3990,
                'configuration' => [
                    'text' => 'Frohe Weihnachten Vorlage',
                    'snapshot_path' => [
                        'front' => $legacySnapshotPath,
                    ],
                ],
            ]);

            $formattedData = $quote->toFormattedArray();

            // Mail
            $mailHtml = view('global.mails.partials.mail_item_list', [
                'data' => $formattedData,
                'isPdf' => false,
            ])->render();

            $this->assertStringContainsString('storage/system/' . $legacySnapshotPath, $mailHtml);
            $this->assertStringNotContainsString($this->testProductImgRel, $mailHtml);

            // PDF
            $pdfHtml = view('global.mails.partials.mail_item_list', [
                'data' => $formattedData,
                'isPdf' => true,
            ])->render();

            $this->assertStringContainsString('data:image/', $pdfHtml);
            $this->assertStringNotContainsString($this->testProductImgRel, $pdfHtml);
        } finally {
            Storage::disk('public')->delete('system/' . $legacySnapshotPath);
        }
    }

    #[Test]
    public function quote_falls_back_to_product_image_only_when_no_snapshot_exists()
    {
        $quote = OrderQuoteRequest::create([
            'quote_number' => 'AN-NOSNAP-' . uniqid(),
            'email' => 'kunde@test.de',
            'first_name' => 'Bernd',
            'last_name' => 'Beispiel',
            'status' => 'open',
            'net_total' => 3353,
            'tax_total' => 637,
            'gross_total' => 3990,
        ]);

        OrderQuoteRequestItem::create([
            'quote_request_id' => $quote->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 3990,
            'tax_rate' => 19.00,
            'total_price' => 3990,
            'configuration' => [
                'snapshot_path' => null, // Kein Snapshot vorhanden
            ],
        ]);

        $formattedData = $quote->toFormattedArray();

        // Mail: Muss auf Produktbild zurückfallen
        $mailHtml = view('global.mails.partials.mail_item_list', [
            'data' => $formattedData,
            'isPdf' => false,
        ])->render();

        $this->assertStringContainsString('storage/' . $this->testProductImgRel, $mailHtml, 'Ohne 3D Snapshot muss das Produktbild als Fallback in der Mail angezeigt werden.');

        // PDF: Produktbild als Base64
        $pdfHtml = view('global.mails.partials.mail_item_list', [
            'data' => $formattedData,
            'isPdf' => true,
        ])->render();

        $this->assertStringContainsString('data:image/', $pdfHtml, 'Im PDF muss das Fallback-Produktbild als Base64 geladen werden.');
    }

    #[Test]
    public function configurator_preserves_initial_snapshot_when_saved_without_new_capture()
    {
        $initialSnapshot = ['front' => 'system/snapshots/initial_template.jpg'];

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'context' => 'calculator',
            'initialData' => [
                'qty' => 2,
                'snapshot_path' => $initialSnapshot,
                'texts' => [
                    ['text' => 'Bestehender Text', 'font' => 'Arial']
                ],
            ],
        ]);

        $component->assertSet('snapshot_path', $initialSnapshot);

        // Speichern ohne neuen Snapshot
        $component->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('calculator-save', function ($event, $params) use ($initialSnapshot) {
                $data = $params['data'] ?? [];
                return isset($data['snapshot_path'])
                    && $data['snapshot_path'] === $initialSnapshot;
            });
    }
}

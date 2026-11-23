<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductCreate;
use App\Models\Admin\Admin;
use App\Models\Management\ManagementTask;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductPackagingItem;
use App\Models\Product\ProductSupplier;
use App\Models\Product\ProductTemplate;
use App\Services\Product\ProductQuickAiLinkAnalyzerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProductQuickAiCreationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-quick-ai@mein-seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        foreach (['Atelier Echtgold & Silber', 'Schmuck & Anhänger', 'Geschenksets', 'Metall & Alu', 'Bestseller', 'Glas & Kristall'] as $cat) {
            ProductCategory::create([
                'name' => $cat,
                'slug' => \Illuminate\Support\Str::slug($cat),
                'type' => 'physical',
            ]);
        }

        ProductPackagingItem::create([
            'sku' => 'PKG-ENV-A1',
            'name' => 'Luftpolsterversandtasche DIN A6 / C6',
            'category' => 'shipping_envelope',
            'weight_grams' => 15,
            'unit_cost_cents' => 14,
            'is_active' => true,
        ]);
    }

    public function test_ssrf_guard_blocks_loopback_private_lan_metadata_and_non_http_schemes(): void
    {
        $analyzer = app(ProductQuickAiLinkAnalyzerService::class);

        $this->assertFalse($analyzer->isSafeExternalUrl('http://127.0.0.1/admin', false));
        $this->assertFalse($analyzer->isSafeExternalUrl('http://localhost:8080/secret', false));
        $this->assertFalse($analyzer->isSafeExternalUrl('http://192.168.188.151:17700/api/health', false));
        $this->assertFalse($analyzer->isSafeExternalUrl('http://169.254.169.254/latest/meta-data/', false));
        $this->assertFalse($analyzer->isSafeExternalUrl('file:///etc/passwd', false));
        $this->assertFalse($analyzer->isSafeExternalUrl('https://user:pass@www.gluecksfieber.de/item', false));
        $this->assertFalse($analyzer->isSafeExternalUrl('https://seelenfunke.test/produkt', false));
        $this->assertTrue($analyzer->isSafeExternalUrl('https://www.gluecksfieber.de/shop/laserrohlinge/herz.html', false));

        Livewire::actingAs($this->admin, 'admin')
            ->test(ProductCreate::class)
            ->call('openQuickAiModal')
            ->set('quickAiReorderUrl', 'http://192.168.188.151/internal')
            ->call('createQuickAiProduct')
            ->assertHasErrors(['quickAiReorderUrl']);
    }

    public function test_atelier_bracelet_link_extracts_json_ld_never_clones_foreign_images_or_laser_and_creates_dashboard_todo(): void
    {
        config([
            'services.quick_ai.fetch_in_tests' => true,
            'services.quick_ai.ai_in_tests' => true,
            'services.gemini.key' => 'test-gemini-key',
        ]);

        $silberSupplier = ProductSupplier::create([
            'name' => 'Silberketten-Store.de (Udo Hermann)',
            'lead_time_land_days' => 3,
            'website' => 'https://www.silberketten-store.de',
        ]);

        // Fremdes Referenzprodukt MIT Saisonbildern und scharfen Laserwerten anlegen -> darf NIEMALS geklont werden!
        $foreignRef = Product::create([
            'name' => 'Das Echtsilber Seelenarmband Referenz',
            'slug' => 'id-armband-silber-herz',
            'sku' => 'MSF-SILBER-ARMBAND-001',
            'type' => 'physical',
            'collection_line' => 'atelier',
            'status' => 'active',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 6990,
            'purchase_price' => 1390,
            'preview_image_path' => 'produkte/products/id-armband-silber-herz/overlay.png',
            'three_d_model_path' => 'produkte/products/id-armband-silber-herz/model.glb',
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/id-armband-silber-herz/main.jpg', 'is_main' => true],
            ],
            'configurator_settings' => [
                'seasonal_images' => [
                    'Weihnachten' => ['path' => 'produkte/products-secure/seasonal/seelen-talisman/weihnachten.jpg', 'is_ai_generated' => false],
                ],
                'xtool_focus_mm' => 2.5,
                'xtool_power_percent' => 34,
                'xtool_dot_duration_us' => 180,
                'xtool_dpi' => 1000,
                'xtool_passes' => 1,
                'xtool_frequency_khz' => 70,
                'xtool_bitmap_mode' => 'Jarvis',
                'xtool_engrave_mode' => 'Bidirektional',
            ],
            'completion_step' => 4,
        ]);

        ProductTemplate::create([
            'product_id' => $foreignRef->id,
            'name' => 'Weihnachts-Gravur',
            'holiday' => 'Weihnachten',
            'preview_image' => 'produkte/products/fremdbild.jpg',
            'configuration' => ['texts' => [['text' => 'Mia']]],
            'is_active' => true,
        ]);

        $supplierHtml = <<<'HTML'
<!doctype html>
<html lang="de">
<head>
    <title>Gravurarmband 925 Sterlingsilber mit Herz-Platte | Silberketten-Store</title>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Product",
        "name": "ID-Gravurarmband 925 Sterlingsilber Herz",
        "gtin13": "4053917000999",
        "description": "Massives 925er Sterlingsilber Gravurarmband, Gravurplatte 26 x 6 x 1,3 mm, Gewicht ca. 3,2 g.",
        "offers": {
            "@type": "Offer",
            "price": "14.50",
            "priceCurrency": "EUR"
        }
    }
    </script>
</head>
<body>
    <h1>ID-Gravurarmband 925 Sterlingsilber Herz</h1>
    <p>Maße Platte: 26 x 6 x 1,3 mm, Gewicht 3,2 g</p>
</body>
</html>
HTML;

        Http::fake([
            'https://www.silberketten-store.de/*' => Http::response($supplierHtml, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'name' => 'Das Silberne Herz- Gravurarmband',
                                'collection_line' => 'atelier',
                                'unit_ek' => 14.50,
                                'vk' => 74.90,
                                'width' => 26,
                                'height' => 6,
                                'length' => 1.3,
                                'weight' => 4,
                                'material' => 'Massives 925er Sterlingsilber (gestempelt 925)',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $comp = Livewire::actingAs($this->admin, 'admin')
            ->test(ProductCreate::class)
            ->call('openQuickAiModal')
            ->assertDontSee('Temu / Amazon')
            ->set('quickAiReorderUrl', 'https://www.silberketten-store.de/gravurarmband-925-silber-herz.html')
            ->call('analyzeQuickAiUrl')
            ->assertSet('quickAiCollectionLine', 'atelier')
            ->assertSet('quickAiSupplierId', $silberSupplier->id)
            ->assertSet('quickAiPurchasePrice', '14.50')
            ->assertSet('quickAiPrice', '74.90')
            ->assertSet('quickAiWidth', 26)
            ->assertSet('quickAiHeight', 6)
            ->call('createQuickAiProduct')
            ->assertSet('showQuickAiModal', false);

        $created = Product::where('sku', 'MSF-SILBER-ARMBAND-002')->first();
        $this->assertNotNull($created);

        // 1. Sicherheits-Entwurf + Bestandsbremse + 0 Mengenrabatte
        $this->assertSame('draft', $created->status);
        $this->assertSame('atelier', $created->collection_line);
        $this->assertTrue((bool) $created->track_quantity);
        $this->assertSame(0, (int) $created->quantity);
        $this->assertFalse((bool) $created->continue_selling_when_out_of_stock);
        $this->assertSame([], $created->tier_pricing);

        // 2. Korrekte Brief-Verpackungskalkulation für leichten Atelier-Schmuck (14 Cent / 15 g statt pauschal 166 Cent Paket)
        $this->assertSame('brief', $created->shipping_class);
        $this->assertSame(14, (int) $created->packaging_cost);
        $this->assertSame(15, (int) $created->packaging_weight);

        // 3. STRIKTE Asset- & Laser-Isolation: 0 Fremdfotos, 0 fremde Saisonbilder, 0 ungeprüfte Laserwerte!
        $this->assertSame([], $created->media_gallery);
        $this->assertNull($created->preview_image_path);
        $this->assertNull($created->three_d_model_path);
        $this->assertSame([], $created->configurator_settings['seasonal_images'] ?? []);
        $this->assertNull($created->configurator_settings['xtool_focus_mm'] ?? null);
        $this->assertNull($created->configurator_settings['xtool_power_percent'] ?? null);
        $this->assertSame([], $created->configurator_settings['xtool_profiles'] ?? []);

        // 4. Übernommene Vorlagen enthalten KEIN fremdes preview_image
        $this->assertCount(1, $created->templates);
        $this->assertNull($created->templates->first()->preview_image);

        // 5. Ehrliche Reifegrad-Ampel meldet offene Real-Punkte (Medien, 3D/2D, xTool Laser)
        $readiness = $created->getReadinessReport();
        $this->assertFalse($readiness['is_ready']);
        $missingKeys = array_column($readiness['missing'], 'key');
        $this->assertContains('media', $missingKeys);
        $this->assertContains('configurator', $missingKeys);
        $this->assertContains('xtool_laser', $missingKeys);

        // 6. Automatische To-Do-Checkliste auf /admin/dashboard angelegt
        $rootTask = ManagementTask::whereNull('parent_id')
            ->where('title', 'like', '%MSF-SILBER-ARMBAND-002%')
            ->with('subtasks')
            ->first();
        $this->assertNotNull($rootTask);
        $this->assertCount(4, $rootTask->subtasks);

        // 7. 0 KI-Gedankenstriche in allen Texten
        $this->assertStringNotContainsString(' – ', (string) $created->description);
        $this->assertStringNotContainsString(' — ', (string) $created->description);
        $this->assertStringNotContainsString(' – ', (string) $comp->html());
    }

    public function test_unknown_supplier_domain_stays_unassigned_instead_of_falling_back_to_temu(): void
    {
        ProductSupplier::create([
            'name' => 'CTHOLD (Wuxi Qiyue) - Temu & Amazon DE',
            'lead_time_land_days' => 10,
            'website' => 'https://www.temu.com',
        ]);

        Livewire::actingAs($this->admin, 'admin')
            ->test(ProductCreate::class)
            ->call('openQuickAiModal')
            ->set('quickAiReorderUrl', 'https://www.unbekannter-deutscher-edelmetall-grosshandel.de/anhanger-20x20mm')
            ->call('createQuickAiProduct');

        $created = Product::latest('created_at')->first();
        $this->assertNotNull($created);
        $this->assertNull($created->product_supplier_id);

        $missingKeys = array_column($created->getReadinessReport()['missing'], 'key');
        $this->assertContains('supplier', $missingKeys);
    }
}

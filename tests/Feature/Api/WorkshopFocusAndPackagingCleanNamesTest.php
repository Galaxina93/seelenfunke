<?php

namespace Tests\Feature\Api;

use App\Livewire\Shop\Product\ProductFrontendFilterArea;
use App\Livewire\Shop\Product\ProductPackagingConfigurator;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Marketing\MarketingLandingPage;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Models\Product\ProductPackagingItem;
use Database\Seeders\ProductPackagingSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\ProductSupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class WorkshopFocusAndPackagingCleanNamesTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): Admin
    {
        return Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-' . Str::random(5) . '@mein-seelenfunke.de',
            'password' => bcrypt('secret123'),
        ]);
    }

    private function createOrder(array $overrides = []): OrderOrder
    {
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $order = new OrderOrder(array_merge([
            'order_number' => 'SF-' . strtoupper(Str::random(6)),
            'email' => 'kunde@example.de',
            'status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'subtotal_price' => 3990,
            'tax_amount' => 637,
            'shipping_price' => 0,
            'total_price' => 3990,
            'discount_amount' => 0,
            'volume_discount' => 0,
            'billing_address' => [
                'first_name' => 'Clara',
                'last_name' => 'Sommer',
                'address' => 'Hauptstrasse 12',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Clara',
                'last_name' => 'Sommer',
                'address' => 'Hauptstrasse 12',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
        ], $overrides));

        if ($createdAt !== null) {
            $order->created_at = $createdAt;
        }

        $order->save();

        return $order;
    }

    public function test_workshop_focus_auth_matrix_enforces_401_for_guests_403_for_non_admins_and_200_for_admin(): void
    {
        $endpoints = [
            ['GET', '/api/shop/workshop-focus', []],
            ['POST', '/api/shop/workshop-focus/send-laser', []],
            ['POST', '/api/shop/workshop-focus/finish-laser-item', []],
            ['POST', '/api/shop/workshop-focus/toggle-check', []],
            ['POST', '/api/shop/workshop-focus/ship-and-next', []],
        ];

        // 1. Guest -> 401
        foreach ($endpoints as [$method, $uri, $body]) {
            $this->json($method, $uri, $body)->assertStatus(401);
        }

        // 2. Customer -> 403
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer, ['*']);
        foreach ($endpoints as [$method, $uri, $body]) {
            $this->json($method, $uri, $body)->assertStatus(403);
        }

        // 3. Employee, Influencer, TaxAdvisor -> 403
        foreach (['employee', 'influencer', 'tax_advisor'] as $role) {
            $emp = Employee::create([
                'id' => (string) Str::uuid(),
                'first_name' => ucfirst($role),
                'last_name' => 'Tester',
                'email' => $role . '-' . Str::random(4) . '@seelenfunke.de',
                'password' => bcrypt('password123'),
                'role' => $role,
            ]);

            Sanctum::actingAs($emp, ['*']);
            foreach ($endpoints as [$method, $uri, $body]) {
                $this->json($method, $uri, $body)->assertStatus(403);
            }
        }

        // 4. Admin with restricted monitor-only token -> 403
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['monitor:pulse']);
        $this->getJson('/api/shop/workshop-focus')->assertStatus(403);

        // 5. Admin with full access token -> 200
        Sanctum::actingAs($admin, ['*']);
        $this->getJson('/api/shop/workshop-focus')
            ->assertStatus(200)
            ->assertJson([
                'has_order' => false,
                'open_ready_count' => 0,
            ]);
    }

    public function test_weizenglas_is_seeded_as_draft_with_assets_preserved_and_hidden_from_active_collections_and_feeds(): void
    {
        $this->seed(ProductSupplierSeeder::class);
        $this->seed(ProductSeeder::class);

        $weizenglas = Product::where('slug', 'weizenglas-personalisiert')->first();
        $this->assertNotNull($weizenglas);
        $this->assertSame('draft', $weizenglas->status);
        $this->assertSame('produkte/products/weizenglas-personalisiert/beer_glas_3d.glb', $weizenglas->three_d_model_path);
        $this->assertSame('cylinder', $weizenglas->configurator_settings['overlay_type'] ?? null);

        $landingPage = MarketingLandingPage::syncForProduct($weizenglas, true);
        $this->assertNotNull($landingPage);
        $this->assertSame('draft', $landingPage->status);

        // Public product & landing page routes must return 404 while in draft
        $this->get('/produkt/weizenglas-personalisiert')->assertStatus(404);
        $this->get('/l/weizenglas-personalisiert')->assertStatus(404);

        // Sitemap & feeds must only expose active products
        $sitemap = $this->get('/sitemap.xml');
        $sitemap->assertStatus(200);
        $this->assertStringNotContainsString('weizenglas-personalisiert', $sitemap->getContent());
        $this->assertStringContainsString('seelen-kristall', $sitemap->getContent());

        $jsonFeed = $this->get('/feed/products.json');
        $jsonFeed->assertStatus(200);
        $this->assertStringNotContainsString('weizenglas-personalisiert', $jsonFeed->getContent());

        Livewire::test(ProductFrontendFilterArea::class)
            ->assertDontSee('Der Feierabend-Funke')
            ->assertSee('Der Seelen Kristall');
    }

    public function test_clean_packaging_names_lucid_catalog_and_automatic_carton_escalation(): void
    {
        $this->seed(ProductSupplierSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(ProductPackagingSeeder::class);

        $this->assertNull(ProductPackagingItem::where('sku', 'PKG-BOX-GLASS')->first());

        $expectedCatalog = [
            'PKG-ENV-A1' => ['Luftpolstertasche', '260 × 180 × 10 mm', 20, 14],
            'PKG-BOX-CRYSTAL' => ['Buchverpackung (Trophäe)', '284 × 244 × 70 mm', 155, 60],
            'PKG-BOX-NORMAL-2633' => ['Normaler Karton', '300 × 230 × 150 mm', 180, 50],
            'PKG-BOX-MEDIUM-165' => ['Mittlerer Karton', '600 × 400 × 200 mm', 750, 278],
            'PKG-BOX-LARGE-069' => ['Großer Karton', '600 × 400 × 400 mm', 1040, 324],
            '30286060102' => ['Schmuckbox', '86 × 86 × 17 mm', 20, 106],
            'PKG-WRAP-BLACK-GOLD' => ['Geschenkpapier Schwarz-Gold', '700 × 500 × 1 mm', 8, 10],
        ];

        foreach ($expectedCatalog as $sku => [$name, $dims, $grams, $unitCents]) {
            $item = ProductPackagingItem::where('sku', $sku)->first();
            $this->assertNotNull($item, "Missing packaging SKU {$sku}");
            $this->assertSame($name, $item->name);
            $this->assertSame($dims, $item->formatted_dimensions);
            $this->assertSame($grams, $item->weight_grams);
            $this->assertSame($unitCents, $item->unit_cost_cents);
        }

        $admin = $this->createAdmin();
        Livewire::actingAs($admin, 'admin')
            ->test(ProductPackagingConfigurator::class)
            ->assertSee('Verpackung zuordnen')
            ->call('setTab', 'catalog')
            ->assertSee('Luftpolstertasche')
            ->assertSee('Buchverpackung (Trophäe)')
            ->assertSee('Normaler Karton')
            ->assertSee('Mittlerer Karton')
            ->assertSee('Großer Karton')
            ->assertSee('Schmuckbox')
            ->assertSee('Geschenkpapier Schwarz-Gold');

        // Carton escalation across order sizes
        $pendant = Product::where('slug', 'seelen-anhaenger')->firstOrFail();
        $crystal = Product::where('slug', 'seelen-kristall')->firstOrFail();

        // 1) Single pendant -> Luftpolstertasche
        $ord1 = $this->createOrder(['order_number' => 'ORD-PKG-1']);
        $ord1->items()->create([
            'product_id' => $pendant->id,
            'product_name' => $pendant->name,
            'quantity' => 1,
            'unit_price' => 1990,
            'total_price' => 1990,
        ]);
        $this->assertSame('Luftpolstertasche', $ord1->fresh()->resolveRecommendedShippingBox()['box_name']);

        // 2) Single crystal trophy -> Buchverpackung (Trophäe)
        $ord2 = $this->createOrder(['order_number' => 'ORD-PKG-2']);
        $ord2->items()->create([
            'product_id' => $crystal->id,
            'product_name' => $crystal->name,
            'quantity' => 1,
            'unit_price' => 6990,
            'total_price' => 6990,
        ]);
        $this->assertSame('Buchverpackung (Trophäe)', $ord2->fresh()->resolveRecommendedShippingBox()['box_name']);

        // 3) Multiple items / 2 trophies -> Normaler Karton
        $ord3 = $this->createOrder(['order_number' => 'ORD-PKG-3']);
        $ord3->items()->create([
            'product_id' => $crystal->id,
            'product_name' => $crystal->name,
            'quantity' => 2,
            'unit_price' => 6990,
            'total_price' => 13980,
        ]);
        $this->assertSame('Normaler Karton', $ord3->fresh()->resolveRecommendedShippingBox()['box_name']);

        // 4) Medium batch (5 trophies) -> Mittlerer Karton
        $ord4 = $this->createOrder(['order_number' => 'ORD-PKG-4']);
        $ord4->items()->create([
            'product_id' => $crystal->id,
            'product_name' => $crystal->name,
            'quantity' => 5,
            'unit_price' => 6990,
            'total_price' => 34950,
        ]);
        $this->assertSame('Mittlerer Karton', $ord4->fresh()->resolveRecommendedShippingBox()['box_name']);

        // 5) Large batch (12 trophies) -> Großer Karton
        $ord5 = $this->createOrder(['order_number' => 'ORD-PKG-5']);
        $ord5->items()->create([
            'product_id' => $crystal->id,
            'product_name' => $crystal->name,
            'quantity' => 12,
            'unit_price' => 6990,
            'total_price' => 83880,
        ]);
        $this->assertSame('Großer Karton', $ord5->fresh()->resolveRecommendedShippingBox()['box_name']);
    }

    public function test_chronological_packing_checklist_order_and_hiding_of_unbooked_addons(): void
    {
        $pendant = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelenanhaenger',
            'slug' => 'seelen-anhaenger-chk',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 1990,
            'weight' => 20,
        ]);

        // Order A: Metal pendant without gift box, without gift wrap, non-fragile
        $simpleOrder = $this->createOrder(['order_number' => 'ORD-CHK-SIMPLE']);
        $simpleOrder->items()->create([
            'product_id' => $pendant->id,
            'product_name' => $pendant->name,
            'quantity' => 1,
            'unit_price' => 1990,
            'total_price' => 1990,
            'configuration' => ['texts' => [['text' => 'Pur']]],
        ]);

        $simpleChecklist = $simpleOrder->fresh()->resolvePackingChecklistItems();
        $this->assertSame(['cleaned', 'thank_you_card'], array_column($simpleChecklist, 'key'));
        $this->assertSame(['Reinigen · Isopropanol & Tuch', 'Dankeskarte'], array_column($simpleChecklist, 'label'));

        // Order B: Fragile crystal with gift_box + gift_wrap (wrapped)
        $crystal = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 6990,
            'weight' => 920,
            'configurator_settings' => ['xtool_material' => 'K9 Kristallglas'],
        ]);

        $fullOrder = $this->createOrder(['order_number' => 'ORD-CHK-FULL']);
        $fullOrder->items()->create([
            'product_id' => $crystal->id,
            'product_name' => $crystal->name,
            'quantity' => 1,
            'unit_price' => 7770,
            'total_price' => 7770,
            'configuration' => [
                'selected_addons' => ['gift_box_westpack', 'gift_wrap_black_gold'],
                'gift_wrap_mode' => 'wrapped',
            ],
        ]);

        $fullChecklist = $fullOrder->fresh()->resolvePackingChecklistItems();
        $this->assertSame(
            ['cleaned', 'gift_box', 'gift_wrap', 'thank_you_card', 'fragile_sticker'],
            array_column($fullChecklist, 'key')
        );
        $this->assertSame(
            [
                'Reinigen · Isopropanol & Tuch',
                'Schmuckbox',
                'Geschenkpapier · Einpacken',
                'Dankeskarte',
                'Aufkleber Zerbrechlich',
            ],
            array_column($fullChecklist, 'label')
        );
    }

    public function test_full_workshop_focus_api_workflow_from_laser_to_packing_and_ship_and_next(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // Ensure DHL credentials are empty so ship-and-next exercises deterministic fallback
        config([
            'services.dhl.api_key' => '',
            'services.dhl.api_user' => '',
        ]);

        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $crystal = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 6990,
            'width' => 180,
            'height' => 180,
            'length' => 40,
            'weight' => 920,
            'configurator_settings' => [
                'allow_personalization' => true,
                'personalization_mode' => 'studio',
                'overlay_type' => 'plane',
                'area_shape' => 'circle',
                'area_left' => 16.0,
                'area_top' => 11.6,
                'area_width' => 68.0,
                'area_height' => 68.0,
                'xtool_plane_width' => 161.5,
                'xtool_plane_height' => 161.5,
                'xtool_material' => 'K9 Kristallglas',
                'xtool_focus_mm' => 20.0,
            ],
        ]);

        $heart = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Goldenes Seelen-Herz',
            'slug' => 'goldenes-seelen-herz',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 3490,
            'width' => 25,
            'height' => 22,
            'length' => 2,
            'weight' => 18,
            'configurator_settings' => [
                'allow_personalization' => true,
                'personalization_mode' => 'studio',
                'overlay_type' => 'plane',
                'area_shape' => 'rect',
                'area_left' => 15,
                'area_top' => 15,
                'area_width' => 70,
                'area_height' => 70,
                'xtool_plane_width' => 25.0,
                'xtool_plane_height' => 22.0,
                'xtool_material' => 'Edelstahl 14K vergoldet',
            ],
        ]);

        // Blocked orders that MUST NEVER appear in workshop-focus: unpaid, photo_issue, supplier_wait
        $unpaidOrder = $this->createOrder([
            'order_number' => 'ORD-BLOCKED-UNPAID',
            'payment_status' => 'unpaid',
            'created_at' => now()->subHours(6),
        ]);
        $unpaidOrder->items()->create([
            'product_id' => $crystal->id,
            'product_name' => $crystal->name,
            'quantity' => 1,
            'unit_price' => 6990,
            'total_price' => 6990,
        ]);

        $photoIssueOrder = $this->createOrder([
            'order_number' => 'ORD-BLOCKED-PHOTO',
            'created_at' => now()->subHours(5),
        ]);
        $photoIssueOrder->items()->create([
            'product_id' => $crystal->id,
            'product_name' => $crystal->name,
            'quantity' => 1,
            'unit_price' => 6990,
            'total_price' => 6990,
            'configuration' => [
                'photo_issue' => ['reason' => 'Unscharf', 'resolved' => false],
            ],
        ]);

        $supplierWaitOrder = $this->createOrder([
            'order_number' => 'ORD-BLOCKED-SUPPLIER',
            'created_at' => now()->subHours(4),
        ]);
        $supplierWaitOrder->items()->create([
            'product_id' => $heart->id,
            'product_name' => $heart->name,
            'quantity' => 1,
            'unit_price' => 3490,
            'total_price' => 3490,
            'configuration' => [
                'supplier_procurement' => [
                    'required' => true,
                    'missing_quantity' => 1,
                    'ordered_from_supplier' => true,
                ],
            ],
        ]);

        // Ready Order 1: Seelen Kristall (with 3D jig + loose gift wrap)
        $readyOrder1 = $this->createOrder([
            'order_number' => 'ORD-FOCUS-01',
            'status' => 'pending',
            'created_at' => now()->subHours(2),
        ]);
        $item1 = $readyOrder1->items()->create([
            'product_id' => $crystal->id,
            'product_name' => $crystal->name,
            'quantity' => 1,
            'unit_price' => 7280,
            'total_price' => 7280,
            'configuration' => [
                'notes' => 'Bitte mittig zentrieren',
                'selected_addons' => ['gift_wrap_black_gold'],
                'gift_wrap_mode' => 'loose',
                'texts' => [['text' => 'Fuer Mama', 'x' => 50, 'y' => 45.6, 'size' => 1.0, 'font' => 'Montserrat']],
            ],
        ]);

        // Ready Order 2: Goldenes Seelen-Herz (no 3D jig -> camera detection)
        $readyOrder2 = $this->createOrder([
            'order_number' => 'ORD-FOCUS-02',
            'status' => 'pending',
            'created_at' => now()->subHour(),
        ]);
        $readyOrder2->items()->create([
            'product_id' => $heart->id,
            'product_name' => $heart->name,
            'quantity' => 1,
            'unit_price' => 3490,
            'total_price' => 3490,
            'configuration' => [
                'texts' => [['text' => 'A & M', 'x' => 50, 'y' => 50, 'size' => 1.0, 'font' => 'Montserrat']],
                'texts_back' => [['text' => '01.11.2026', 'x' => 50, 'y' => 50, 'size' => 0.9, 'font' => 'Montserrat']],
            ],
        ]);

        // 1. GET /api/shop/workshop-focus -> picks ORD-FOCUS-01 in laser phase with Bambu jig coordinates
        $focusResp = $this->getJson('/api/shop/workshop-focus')
            ->assertStatus(200)
            ->assertJson([
                'has_order' => true,
                'open_ready_count' => 2,
                'phase' => 'laser',
                'order' => [
                    'id' => $readyOrder1->id,
                    'order_number' => 'ORD-FOCUS-01',
                    'active_item' => [
                        'id' => $item1->id,
                        'product_id' => $crystal->id,
                        'customer_note' => 'Bitte mittig zentrieren',
                        'jig' => [
                            'has_jig' => true,
                            'label' => 'Seelen Kristall',
                            'fixed_x_mm' => 100.0,
                            'fixed_y_mm' => 95.0,
                            'fixed_angle_deg' => 0.0,
                        ],
                        'laser' => [
                            'bed_width_mm' => 200.0,
                            'bed_height_mm' => 200.0,
                            'center_x_mm' => 100.0,
                            'center_y_mm' => 95.0,
                        ],
                    ],
                    'packaging' => [
                        'box_name' => 'Buchverpackung (Trophäe)',
                        'box_dimensions' => '284 × 244 × 70 mm',
                    ],
                ],
            ]);

        $this->assertNotEmpty($focusResp->json('order.active_item.laser.contour_d_path'));
        $this->assertStringStartsWith('data:image/png;base64,', (string) $focusResp->json('order.active_item.laser.engrave_preview_data_url'));

        // 2. POST /api/shop/workshop-focus/send-laser -> generates .xf package and sets status to processing
        $sendResp = $this->postJson('/api/shop/workshop-focus/send-laser', [
            'order_id' => $readyOrder1->id,
            'item_id' => $item1->id,
            'side' => 'front',
            'center_x_mm' => 100.0,
            'center_y_mm' => 95.0,
            'angle_deg' => 0.0,
        ])->assertStatus(200)
          ->assertJson([
              'success' => true,
              'center_x_mm' => 100.0,
              'center_y_mm' => 95.0,
              'angle_deg' => 0.0,
          ]);

        $jobId = (string) $sendResp->json('job_id');
        $xfRaw = base64_decode((string) $sendResp->json('xf_base64'), true);
        $this->assertNotEmpty($jobId);
        $this->assertIsString($xfRaw);
        $this->assertStringContainsString('motion.gcode', $xfRaw);
        $this->assertSame('processing', $readyOrder1->fresh()->status);

        // 3. POST /api/shop/workshop-focus/finish-laser-item -> marks item done and transitions phase to packing!
        $finishResp = $this->postJson('/api/shop/workshop-focus/finish-laser-item', [
            'order_id' => $readyOrder1->id,
            'item_id' => $item1->id,
            'job_id' => $jobId,
        ])->assertStatus(200)
          ->assertJson([
              'success' => true,
              'has_order' => true,
              'phase' => 'packing',
              'order' => [
                  'id' => $readyOrder1->id,
                  'status' => 'processing',
              ],
          ]);

        $this->assertTrue($item1->fresh()->is_completed);
        $this->assertSame(1, $item1->fresh()->completed_quantity);
        $this->assertSame(
            ['cleaned', 'gift_wrap', 'thank_you_card', 'fragile_sticker'],
            array_column($finishResp->json('order.packaging.checklist'), 'key')
        );

        // 4. POST /api/shop/workshop-focus/toggle-check -> persists individual check states
        $toggleResp = $this->postJson('/api/shop/workshop-focus/toggle-check', [
            'order_id' => $readyOrder1->id,
            'key' => 'cleaned',
            'checked' => true,
        ])->assertStatus(200)
          ->assertJson(['success' => true]);

        $cleanedEntry = collect($toggleResp->json('checklist'))->firstWhere('key', 'cleaned');
        $this->assertTrue((bool) ($cleanedEntry['checked'] ?? false));
        $this->assertTrue($readyOrder1->fresh()->shipping_checklist['cleaned']);

        // 5. POST /api/shop/workshop-focus/ship-and-next -> checks all items, ships ORD-FOCUS-01, loads ORD-FOCUS-02!
        $shipResp = $this->postJson('/api/shop/workshop-focus/ship-and-next', [
            'order_id' => $readyOrder1->id,
        ])->assertStatus(200)
          ->assertJson([
              'success' => true,
              'order_id' => $readyOrder1->id,
              'status' => 'shipped',
              'dhl_ready' => false,
              'next_focus' => [
                  'has_order' => true,
                  'open_ready_count' => 1,
                  'phase' => 'laser',
                  'order' => [
                      'id' => $readyOrder2->id,
                      'order_number' => 'ORD-FOCUS-02',
                      'active_item' => [
                          'has_back_side_content' => true,
                          'jig' => [
                              'has_jig' => false,
                              'fixed_x_mm' => null,
                              'fixed_y_mm' => null,
                          ],
                      ],
                      'packaging' => [
                          'box_name' => 'Luftpolstertasche',
                          'box_dimensions' => '260 × 180 × 10 mm',
                      ],
                  ],
              ],
          ]);

        foreach ($shipResp->json('checklist') as $entry) {
            $this->assertTrue((bool) $entry['checked'], "Checklist item {$entry['key']} must be checked after ship-and-next");
        }
        $this->assertSame('shipped', $readyOrder1->fresh()->status);

        // Ship ORD-FOCUS-02 -> next_focus has_order becomes false (blocked orders remain excluded!)
        $shipResp2 = $this->postJson('/api/shop/workshop-focus/ship-and-next', [
            'order_id' => $readyOrder2->id,
        ])->assertStatus(200)
          ->assertJson([
              'success' => true,
              'status' => 'shipped',
              'next_focus' => [
                  'has_order' => false,
                  'open_ready_count' => 0,
                  'order' => null,
              ],
          ]);

        $this->assertNotEmpty($shipResp2->json('dhl_message'));
    }
}

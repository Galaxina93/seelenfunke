<?php

namespace Tests\Feature\Shop;

use App\Livewire\Shop\Order\OrderCheckout\OrderCheckout;
use App\Livewire\Shop\System\SystemShopConfig;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminProfile;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\System\SystemSetting;
use App\Services\ShippingCalculatorService;
use App\Services\System\BlueprintZeroLeakScanner;
use App\Services\System\CleanRoomBlueprintBuilderService;
use App\Services\Tax\DigitalEuVatThresholdService;
use App\Support\DigitalDomainContext;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class DigitalDomainAndBlueprintSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): Admin
    {
        $admin = Admin::create([
            'first_name' => 'Lead',
            'last_name' => 'Architect',
            'email' => 'admin-' . Str::lower(Str::random(6)) . '@example.org',
            'password' => Hash::make('Secret123!'),
        ]);

        AdminProfile::firstOrCreate(
            ['admin_id' => $admin->id],
            ['city' => 'Berlin', 'country' => 'DE']
        );

        return $admin;
    }

    public function test_digital_domain_is_off_by_default_blocks_guests_with_503_and_allows_admin_preview_with_200(): void
    {
        Cache::forget('global_shop_settings');

        // Migration legt digital_domain_is_live standardmäßig auf '0' (NICHT LIVE) an
        $this->assertFalse(DigitalDomainContext::isDigitalDomainLive());
        $this->assertSame('0', (string) SystemSetting::where('key', 'digital_domain_is_live')->value('value'));

        // Selbst wenn der physische Hauptshop live ist, bleibt die Digital-Domain für Gäste strikt gesperrt (HTTP 503)
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        Cache::forget('global_shop_settings');

        $this->get('http://digital.mein-seelenfunke.de/shop')
            ->assertStatus(503);

        // Eingeloggte Admins erhalten auf der Digital-Domain vollen HTTP 200 Vorschau-Zugriff
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin')
            ->get('http://digital.mein-seelenfunke.de/shop')
            ->assertStatus(200);

        // Im SystemShopConfig-Backend lässt sich der unabhängige Schalter sauber umschalten
        Livewire::actingAs($admin, 'admin')
            ->test(SystemShopConfig::class)
            ->assertSet('digital_domain_is_live', false)
            ->assertSee('Digital-Domain Live-Freigabe')
            ->call('toggleDigitalDomainIsLive')
            ->assertSet('digital_domain_is_live', true);

        $this->assertTrue(DigitalDomainContext::isDigitalDomainLive());

        // Wieder zurück auf false (Gesperrt)
        Livewire::actingAs($admin, 'admin')
            ->test(SystemShopConfig::class)
            ->call('toggleDigitalDomainIsLive')
            ->assertSet('digital_domain_is_live', false);

        $this->assertFalse(DigitalDomainContext::isDigitalDomainLive());
    }

    public function test_digital_domain_storefront_filters_strictly_to_active_digital_products_and_supports_de_en_switcher(): void
    {
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'digital_domain_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        Cache::forget('global_shop_settings');

        $physicalProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen Kristall Physisch',
            'slug' => 'seelen-kristall-physisch',
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'manufaktur',
            'is_personalizable' => true,
            'three_d_active' => true,
            'three_d_model_path' => 'produkte/products/seelen-kristall/t_seelenk.glb',
            'price' => 6990,
            'track_quantity' => true,
            'quantity' => 5,
        ]);

        $activeBlueprint = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Autonomous 4-Pillar Self-Healing Sentinel Kit',
            'slug' => 'autonomous-self-healing-sentinel-kit',
            'status' => 'active',
            'type' => 'digital',
            'collection_line' => 'digital_blueprint',
            'price' => 34900,
            'track_quantity' => false,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => true,
            'short_description' => 'Deutsche Kurzbeschreibung fuer Sentinel Kit.',
            'attributes' => [
                'name_en' => 'Sentinel Kit English Title',
                'short_description_en' => 'English short summary for Sentinel Kit.',
            ],
        ]);

        $draftBlueprint = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Unveroeffentlichter Blueprint Entwurf',
            'slug' => 'draft-blueprint-secret',
            'status' => 'draft',
            'type' => 'digital',
            'collection_line' => 'digital_blueprint',
            'price' => 99000,
        ]);

        // 1. Aufruf der Digital-Domain auf Deutsch
        $digitalDeResponse = $this->get('http://digital.mein-seelenfunke.de/shop');
        $digitalDeResponse->assertStatus(200);
        $digitalDeHtml = (string) $digitalDeResponse->getContent();

        $this->assertStringContainsString('Software- & KI-Architektur-Blueprints', $digitalDeHtml);
        $this->assertStringContainsString($activeBlueprint->name, $digitalDeHtml);
        $this->assertStringContainsString('ZIP-Paket', $digitalDeHtml);
        $this->assertStringNotContainsString($physicalProduct->name, $digitalDeHtml);
        $this->assertStringNotContainsString($draftBlueprint->name, $digitalDeHtml);
        $this->assertStringNotContainsString('3D shoppen', $digitalDeHtml);
        $this->assertStringNotContainsString('MEIN SEELENFUNKE · LOUVRE SHOWROOM', $digitalDeHtml);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $digitalDeHtml);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $digitalDeHtml);

        // 2. Sprachumschaltung auf Englisch (?lang=en)
        $digitalEnResponse = $this->get('http://digital.mein-seelenfunke.de/shop?lang=en');
        $digitalEnResponse->assertStatus(200);
        $digitalEnHtml = (string) $digitalEnResponse->getContent();

        $this->assertStringContainsString('Autonomous AI Architecture Blueprints', $digitalEnHtml);
        $this->assertStringContainsString('Sentinel Kit English Title', $digitalEnHtml);
        $this->assertStringContainsString('ZIP Blueprint', $digitalEnHtml);

        // 3. Physischer Hauptshop zeigt weiterhin physische Unikate + 3D shoppen, aber KEINE Digital-Domain-Blueprints
        $mainResponse = $this->get('http://mein-seelenfunke.de/shop?domain=main');
        $mainResponse->assertStatus(200);
        $mainHtml = (string) $mainResponse->getContent();

        $this->assertStringContainsString($physicalProduct->name, $mainHtml);
        $this->assertStringContainsString('3D shoppen', $mainHtml);
        $this->assertStringNotContainsString($activeBlueprint->name, $mainHtml);
    }

    public function test_clean_room_builder_creates_five_sterile_zips_and_zero_leak_scanner_passes_with_zero_hits(): void
    {
        $this->seed(ProductCategorySeeder::class);

        $builder = app(CleanRoomBlueprintBuilderService::class);
        $builtPaths = $builder->buildAllPackages();

        $this->assertCount(5, $builtPaths);

        $definitions = CleanRoomBlueprintBuilderService::getBlueprintDefinitions();
        $expectedPrices = [
            'autonomous-self-healing-sentinel-kit' => 34900,
            'multi-agent-executive-orchestra-router' => 39900,
            'zero-secret-ai-vault-airgapped-broker' => 34900,
            'realtime-multimodal-ai-voice-edge-bridge' => 29900,
            'complete-autonomous-ai-company-suite' => 99000,
        ];

        $digitalCatIds = ProductCategory::whereIn('name', ['E-Books & Guides', 'Design-Vorlagen'])->pluck('id');

        foreach ($definitions as $slug => $bp) {
            $this->assertSame($expectedPrices[$slug], $bp['price_cents']);
            $this->assertTrue(Storage::disk('local')->exists($builtPaths[$slug]));

            $seeded = Product::updateOrCreate(['slug' => $slug], [
                'name' => $bp['name'],
                'type' => 'digital',
                'collection_line' => 'digital_blueprint',
                'description' => $bp['description_de'],
                'short_description' => $bp['short_de'],
                'status' => 'draft',
                'price' => $bp['price_cents'],
                'sku' => $bp['sku'],
                'quantity' => 0,
                'digital_download_path' => $builtPaths[$slug],
                'digital_filename' => $bp['filename'],
            ]);
            $seeded->categories()->sync($digitalCatIds);

            $this->assertSame('draft', $seeded->status);
            $this->assertSame('digital', $seeded->type);
            $this->assertSame('digital_blueprint', $seeded->collection_line);
        }

        $exitCode = Artisan::call('blueprint:scan');
        $scanOutput = Artisan::output();
        $this->assertSame(0, $exitCode, $scanOutput);
        $this->assertStringContainsString('ZERO-LEAK OK', $scanOutput);
    }

    public function test_blueprint_zero_leak_scanner_blocks_forbidden_terms_in_content_and_filenames(): void
    {
        $scanner = app(BlueprintZeroLeakScanner::class);
        $tempDir = storage_path('framework/cache/zero-leak-tests');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        // Fall A: Verbotene interne Begriffe im Dateiinhalt
        $poisonedContentZip = $tempDir . '/poisoned-content.zip';
        $zipA = new ZipArchive();
        $this->assertTrue($zipA->open($poisonedContentZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zipA->addFromString('README.md', 'Internal host 192.168.188.151 managed by Funkira on p-g27wim.');
        $zipA->close();

        $reportA = $scanner->scanZipFile($poisonedContentZip);
        $this->assertFalse($reportA['clean']);
        $this->assertGreaterThanOrEqual(3, $reportA['hits_count']);

        $exitCodeA = Artisan::call('blueprint:scan', ['path' => $poisonedContentZip]);
        $this->assertSame(1, $exitCodeA);

        $exceptionThrown = false;
        try {
            $scanner->assertZipIsClean($poisonedContentZip);
        } catch (RuntimeException $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('ZERO-LEAK SECURITY BLOCK', $e->getMessage());
        }
        $this->assertTrue($exceptionThrown);
        @unlink($poisonedContentZip);

        // Fall B: Sauberer Inhalt, aber interner Markenname im Dateipfad innerhalb des ZIPs
        $poisonedFilenameZip = $tempDir . '/clean-outer-name.zip';
        $zipB = new ZipArchive();
        $this->assertTrue($zipB->open($poisonedFilenameZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zipB->addFromString('config/seelenfunke-production.json', '{"mode":"clean"}');
        $zipB->close();

        $reportB = $scanner->scanZipFile($poisonedFilenameZip);
        $this->assertFalse($reportB['clean']);
        $this->assertGreaterThanOrEqual(1, $reportB['hits_count']);
        @unlink($poisonedFilenameZip);
    }

    public function test_digital_product_download_blocks_unpaid_stamps_license_on_the_fly_and_preserves_master_zip(): void
    {
        $builder = app(CleanRoomBlueprintBuilderService::class);
        $relZipPath = $builder->buildPackage('autonomous-self-healing-sentinel-kit');
        $masterAbsPath = Storage::disk('local')->path($relZipPath);
        $masterHashBefore = hash_file('sha256', $masterAbsPath);

        // Master-ZIP selbst besitzt keinen individuellen Käuferstempel
        $masterZip = new ZipArchive();
        $this->assertTrue($masterZip->open($masterAbsPath, ZipArchive::RDONLY) === true);
        $this->assertFalse($masterZip->locateName('LICENSE.txt') !== false);
        $this->assertFalse($masterZip->locateName('blueprint-license.json') !== false);
        $masterZip->close();

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Autonomous 4-Pillar Self-Healing Sentinel Kit',
            'slug' => 'autonomous-self-healing-sentinel-kit',
            'type' => 'digital',
            'collection_line' => 'digital_blueprint',
            'status' => 'active',
            'price' => 34900,
            'digital_download_path' => $relZipPath,
            'digital_filename' => 'NexusCore-Self-Healing-Sentinel-Kit.zip',
        ]);

        $customer = Customer::factory()->create([
            'email' => 'cto@enterprise-buyer.io',
            'email_verified_at' => now(),
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-2026-BP-9001',
            'customer_id' => $customer->id,
            'email' => 'cto@enterprise-buyer.io',
            'status' => 'pending',
            'payment_status' => 'pending',
            'billing_address' => [
                'first_name' => 'Alex',
                'last_name' => 'Mercer',
                'company' => 'Mercer Cloud Systems GmbH',
                'address' => ' Friedrichstrasse 100',
                'postal_code' => '10117',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Alex',
                'last_name' => 'Mercer',
                'address' => 'Friedrichstrasse 100',
                'postal_code' => '10117',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
            'subtotal_price' => 34900,
            'shipping_price' => 0,
            'tax_amount' => 5572,
            'total_price' => 34900,
            'digital_waiver_accepted_at' => now(),
        ]);

        $orderItem = OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 34900,
            'total_price' => 34900,
            'tax_rate' => 19.0,
        ]);

        $signedUrl = URL::signedRoute('digital-product.download', ['item' => $orderItem->id]);

        // 1. Vor Zahlungseingang strikt HTTP 403
        $this->get($signedUrl)->assertStatus(403);

        // 2. Nach Zahlungseingang HTTP 200 mit gestempelter temporärer ZIP-Kopie
        $order->update(['payment_status' => 'paid', 'status' => 'completed']);

        $downloadResponse = $this->get($signedUrl);
        $downloadResponse->assertStatus(200);

        $streamedFile = $downloadResponse->baseResponse->getFile()->getPathname();
        $this->assertFileExists($streamedFile);

        $stampedZip = new ZipArchive();
        $this->assertTrue($stampedZip->open($streamedFile, ZipArchive::RDONLY) === true);

        $licenseTxt = (string) $stampedZip->getFromName('LICENSE.txt');
        $licenseJsonRaw = (string) $stampedZip->getFromName('blueprint-license.json');
        $readmeEn = (string) $stampedZip->getFromName('README_EN.md');
        $stampedZip->close();

        $this->assertStringContainsString('ORD-2026-BP-9001', $licenseTxt);
        $this->assertStringContainsString('AS-IS', $licenseTxt);
        $this->assertStringContainsString('SentinelEngine', $readmeEn);

        $licenseData = json_decode($licenseJsonRaw, true);
        $this->assertIsArray($licenseData);
        $this->assertSame('ORD-2026-BP-9001', $licenseData['order_number']);
        $this->assertSame('Reference Architecture as-is', $licenseData['delivery_basis']);
        $this->assertTrue($licenseData['resale_and_redistribution_prohibited']);
        $this->assertNotEmpty($licenseData['license_id']);
        $this->assertNotEmpty($licenseData['buyer_hash']);

        // 3. Master-ZIP auf der Festplatte ist Byte-für-Byte unverändert geblieben!
        $this->assertSame($masterHashBefore, hash_file('sha256', $masterAbsPath));

        @unlink($streamedFile);
    }

    public function test_pure_digital_checkout_skips_shipping_and_eu_oss_threshold_service_tracks_10k_cap(): void
    {
        $blueprint = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Multi-Agent Executive Orchestra & Parallel Router',
            'slug' => 'multi-agent-executive-orchestra-router',
            'type' => 'digital',
            'collection_line' => 'digital_blueprint',
            'status' => 'active',
            'price' => 39900,
            'tax_class' => 'standard',
            'track_quantity' => false,
            'continue_selling_when_out_of_stock' => true,
            'digital_download_path' => 'private/products-secure/blueprints/OrchestratorRuntime-Multi-Agent-Router.zip',
            'digital_filename' => 'OrchestratorRuntime-Multi-Agent-Router.zip',
        ]);

        $customer = Customer::factory()->create([
            'email' => 'dev@international-buyer.com',
            'email_verified_at' => now(),
        ]);

        $cart = Cart::create([
            'customer_id' => $customer->id,
            'session_id' => 'test-digital-session',
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $blueprint->id,
            'quantity' => 1,
            'unit_price' => 39900,
        ]);

        // 1. Versandkalkulator liefert für rein digitale Warenkörbe weltweit 0 Cent Versand ohne Zonen-Sperre
        $shippingCalc = app(ShippingCalculatorService::class)->calculateShippingCost($cart->items()->with('product')->get(), 39900, 0.0, 'US');
        $this->assertSame(0, $shippingCalc['cost']);
        $this->assertTrue($shippingCalc['is_free']);
        $this->assertSame('non_physical_only', $shippingCalc['reason']);

        // 2. OrderCheckout erkennt isOnlyDigitalCart und erlaubt internationale Rechnungsadresse (z. B. AT / US) ohne Lieferzonen-Sperre
        $checkoutComponent = Livewire::actingAs($customer, 'customer')
            ->test(OrderCheckout::class)
            ->assertViewHas('isOnlyDigital', true)
            ->assertViewHas('totals', fn (array $t) => ($t['shipping'] ?? 0) === 0);

        $this->assertTrue($checkoutComponent->instance()->isOnlyDigitalCart($cart->fresh('items.product')));

        $allowedCountries = $checkoutComponent->instance()->resolveAllowedCheckoutCountries($cart->fresh('items.product'));
        $this->assertArrayHasKey('AT', $allowedCountries);
        $this->assertArrayHasKey('US', $allowedCountries);

        // 3. EU-OSS 10.000-EUR-Netto-Schwellenwächter (§ 3a Abs. 5 UStG, Frühwarnung ab 8.000 EUR)
        $ossService = app(DigitalEuVatThresholdService::class);
        $initial = $ossService->evaluateAnnualThreshold((int) now()->year);
        $this->assertSame(0, $initial['eu_cross_border_net_cents']);
        $this->assertFalse($initial['is_warning_reached']);
        $this->assertFalse($initial['is_oss_threshold_exceeded']);

        // Inlandsbestellung (DE) zählt NICHT in die EU-OSS-Schwelle
        $deOrder = OrderOrder::create([
            'order_number' => 'ORD-DE-OSS-1',
            'email' => 'de@buyer.de',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['country' => 'DE'],
            'shipping_address' => ['country' => 'DE'],
            'subtotal_price' => 952000,
            'shipping_price' => 0,
            'tax_amount' => 152000,
            'total_price' => 952000,
        ]);
        OrderOrderItem::create([
            'order_id' => $deOrder->id,
            'product_id' => $blueprint->id,
            'product_name' => $blueprint->name,
            'quantity' => 1,
            'unit_price' => 952000,
            'total_price' => 952000,
            'tax_rate' => 19.0,
        ]);

        $afterDe = $ossService->evaluateAnnualThreshold((int) now()->year);
        $this->assertSame(0, $afterDe['eu_cross_border_net_cents']);

        // EU-Auslandsbestellung (AT: 9.877,00 EUR Brutto = 8.300,00 EUR Netto bei 19 % USt.) -> Frühwarnung aktiv!
        $atOrder = OrderOrder::create([
            'order_number' => 'ORD-AT-OSS-2',
            'email' => 'at@buyer.at',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['country' => 'AT'],
            'shipping_address' => ['country' => 'AT'],
            'subtotal_price' => 987700,
            'shipping_price' => 0,
            'tax_amount' => 157700,
            'total_price' => 987700,
        ]);
        OrderOrderItem::create([
            'order_id' => $atOrder->id,
            'product_id' => $blueprint->id,
            'product_name' => $blueprint->name,
            'quantity' => 1,
            'unit_price' => 987700,
            'total_price' => 987700,
            'tax_rate' => 19.0,
        ]);

        $afterAt = $ossService->evaluateAnnualThreshold((int) now()->year);
        $this->assertSame(830000, $afterAt['eu_cross_border_net_cents']);
        $this->assertTrue($afterAt['is_warning_reached']);
        $this->assertFalse($afterAt['is_oss_threshold_exceeded']);

        // Weitere EU-Auslandsbestellung (FR: 2.380,00 EUR Brutto = 2.000,00 EUR Netto) -> Summe 10.300 EUR -> OSS-Schwelle überschritten!
        $frOrder = OrderOrder::create([
            'order_number' => 'ORD-FR-OSS-3',
            'email' => 'fr@buyer.fr',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['country' => 'FR'],
            'shipping_address' => ['country' => 'FR'],
            'subtotal_price' => 238000,
            'shipping_price' => 0,
            'tax_amount' => 38000,
            'total_price' => 238000,
        ]);
        OrderOrderItem::create([
            'order_id' => $frOrder->id,
            'product_id' => $blueprint->id,
            'product_name' => $blueprint->name,
            'quantity' => 1,
            'unit_price' => 238000,
            'total_price' => 238000,
            'tax_rate' => 19.0,
        ]);

        $afterFr = $ossService->evaluateAnnualThreshold((int) now()->year);
        $this->assertSame(1030000, $afterFr['eu_cross_border_net_cents']);
        $this->assertTrue($afterFr['is_warning_reached']);
        $this->assertTrue($afterFr['is_oss_threshold_exceeded']);
        $this->assertSame(830000, $afterFr['country_breakdown']['AT']);
        $this->assertSame(200000, $afterFr['country_breakdown']['FR']);
    }

    public function test_htaccess_preserves_digital_subdomain_and_zero_leak_guard_protects_digital_blueprints_mirror(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));
        $this->assertStringContainsString('RewriteCond %{HTTP_HOST} ^www\.mein-seelenfunke\.de$ [NC]', $htaccess);
        $this->assertStringNotContainsString('digital\.mein-seelenfunke\.de', $htaccess);
        $this->assertStringNotContainsString('digital.mein-seelenfunke.de', $htaccess);

        $builder = app(CleanRoomBlueprintBuilderService::class);
        $scanner = app(BlueprintZeroLeakScanner::class);
        $builtPaths = $builder->buildAllPackages();

        $disk = Storage::disk('local');
        $mirrorRelDir = 'private/digital_blueprints';
        $disk->makeDirectory($mirrorRelDir);

        foreach ($builtPaths as $slug => $relPath) {
            $disk->put($mirrorRelDir . '/' . $slug . '.zip', (string) $disk->get($relPath));
        }

        $cleanReport = $scanner->scanDirectory($disk->path($mirrorRelDir));
        $this->assertTrue($cleanReport['clean']);
        $this->assertSame(0, $cleanReport['hits_count']);
        $this->assertSame(5, $cleanReport['scanned_files']);

        // Kontaminiertes Archiv in digital_blueprints wird sofort erkannt und entfernt
        $poisonedPath = $disk->path($mirrorRelDir . '/tampered-check.zip');
        $tamperedZip = new ZipArchive();
        $this->assertTrue($tamperedZip->open($poisonedPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $tamperedZip->addFromString('notes.txt', 'Leak test for mein-seelenfunke internal node.');
        $tamperedZip->close();

        $dirtyReport = $scanner->scanDirectory($disk->path($mirrorRelDir));
        $this->assertFalse($dirtyReport['clean']);
        $this->assertGreaterThanOrEqual(1, $dirtyReport['hits_count']);

        @unlink($poisonedPath);
        $restoredReport = $scanner->scanDirectory($disk->path($mirrorRelDir));
        $this->assertTrue($restoredReport['clean']);
    }
}

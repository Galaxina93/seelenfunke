<?php

namespace Tests\Feature\Shop;

use App\Livewire\Customer\CustomerDashboardComponent;
use App\Livewire\Customer\CustomerFunkiComponent;
use App\Livewire\Shop\Wishlist\WishlistIcon;
use App\Livewire\Shop\Wishlist\WishlistPage;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Support\SupportTicket;
use App\Models\System\SystemSetting;
use App\Services\AccountingEInvoiceParserService;
use App\Services\AccountingXmlInvoiceService;
use App\Services\AI\Functions\AiContactFuncs;
use App\Services\AI\Functions\AiSupportFuncs;
use App\Services\CartService;
use App\Services\InvoiceService;
use App\Services\WishlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ShopEnhancementsAndEInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_point_1_invoice_generates_girocode_portal_qr_and_code128_barcode(): void
    {
        SystemSetting::updateOrCreate(['key' => 'owner_iban'], ['value' => 'DE89 3704 0044 0532 0130 00']);
        SystemSetting::updateOrCreate(['key' => 'owner_bic'], ['value' => 'COBADEFFXXX']);
        SystemSetting::updateOrCreate(['key' => 'company_name'], ['value' => 'Mein Seelenfunke']);

        $invoice = AccountingInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'RE-2026-9001',
            'type' => 'invoice',
            'status' => 'open',
            'invoice_date' => now(),
            'due_date' => now()->addDays(14),
            'subtotal' => 4202,
            'tax_amount' => 798,
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'volume_discount' => 0,
            'total' => 5000,
            'billing_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'address' => 'Musterstr. 1',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
                'email' => 'max@example.com',
            ],
            'custom_items' => [
                [
                    'product_name' => 'Edelstahl Anhänger',
                    'quantity' => 1,
                    'unit_price' => 42.02,
                    'tax_rate' => 19,
                ],
            ],
        ]);

        $service = app(InvoiceService::class);

        $giroPayload = $service->generateGiroCodePayload(
            $invoice,
            'Mein Seelenfunke',
            'DE89 3704 0044 0532 0130 00',
            'COBADEFFXXX'
        );
        $this->assertNotNull($giroPayload);
        $this->assertStringContainsString("BCD\n002\n1\nSCT", $giroPayload);
        $this->assertStringContainsString('DE89370400440532013000', $giroPayload);
        $this->assertStringContainsString('EUR50.00', $giroPayload);
        $this->assertStringContainsString('RE-2026-9001', $giroPayload);

        $giroSvgUri = $service->generateGiroCodeSvgDataUri(
            $invoice,
            'Mein Seelenfunke',
            'DE89 3704 0044 0532 0130 00',
            'COBADEFFXXX'
        );
        $this->assertNotNull($giroSvgUri);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $giroSvgUri);

        $portalQrUri = $service->generatePortalQrSvgDataUri($invoice);
        $this->assertNotNull($portalQrUri);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $portalQrUri);

        $barcodeUri = $service->generateCode128BarcodeSvgDataUri($invoice->invoice_number);
        $this->assertNotNull($barcodeUri);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $barcodeUri);
    }

    public function test_point_2_wishlist_toggle_and_page_works(): void
    {
        $this->withoutExceptionHandling();
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Kristall Herz',
            'slug' => 'kristall-herz',
            'price' => 2990,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
        ]);

        $wishlistService = app(WishlistService::class);
        $this->assertFalse($wishlistService->has($product->id));

        $added = $wishlistService->toggle($product->id);
        $this->assertTrue($added);
        $this->assertTrue($wishlistService->has($product->id));
        $this->assertSame(1, $wishlistService->getCount());

        Livewire::test(WishlistIcon::class)
            ->assertSet('count', 1)
            ->assertSee('Kristall Herz');

        Livewire::test(WishlistPage::class)
            ->assertSee('Kristall Herz')
            ->assertSee('Wunschliste teilen')
            ->call('removeItem', $product->id);

        $this->assertFalse($wishlistService->has($product->id));
        $this->assertSame(0, $wishlistService->getCount());

        // Test shared wishlist link (?wl=kristall-herz) & importSharedWishlist
        Livewire::withQueryParams(['wl' => 'kristall-herz'])
            ->test(WishlistPage::class)
            ->assertSet('isSharedView', true)
            ->assertSee('Geteilte Wunschliste')
            ->assertSee('Kristall Herz')
            ->call('importSharedWishlist');

        $this->assertTrue($wishlistService->has($product->id));
    }

    public function test_point_3_einvoice_xml_generation_and_parser_validation(): void
    {
        SystemSetting::updateOrCreate(['key' => 'owner_iban'], ['value' => 'DE89370400440532013000']);
        SystemSetting::updateOrCreate(['key' => 'owner_bic'], ['value' => 'COBADEFFXXX']);
        SystemSetting::updateOrCreate(['key' => 'company_name'], ['value' => 'Mein Seelenfunke']);
        SystemSetting::updateOrCreate(['key' => 'owner_email'], ['value' => 'kontakt@mein-seelenfunke.de']);
        SystemSetting::updateOrCreate(['key' => 'owner_ust_id'], ['value' => 'DE123456789']);

        $invoice = AccountingInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'RE-2026-9002',
            'type' => 'invoice',
            'status' => 'paid',
            'invoice_date' => now(),
            'due_date' => now()->addDays(14),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'volume_discount' => 0,
            'total' => 11900,
            'billing_address' => [
                'first_name' => 'Erika',
                'last_name' => 'Musterfrau',
                'address' => 'Hauptstr. 12',
                'postal_code' => '10115',
                'city' => 'Berlin',
                'country' => 'DE',
                'email' => 'erika@example.com',
            ],
            'custom_items' => [
                [
                    'product_name' => '3D Kristallglas Würfel',
                    'quantity' => 2,
                    'unit_price' => 50.00,
                    'tax_rate' => 19,
                ],
            ],
        ]);

        $xmlService = app(AccountingXmlInvoiceService::class);
        $xmlPath = $xmlService->generate($invoice);
        $xml = \Illuminate\Support\Facades\Storage::disk('local')->get($xmlPath);

        $this->assertStringContainsString('urn:cen.eu:en16931:2017', $xml);
        $this->assertStringContainsString('BusinessProcessSpecifiedDocumentContextParameter', $xml);
        $this->assertStringContainsString('BuyerReference', $xml);
        $this->assertStringContainsString('DefinedTradeContact', $xml);
        $this->assertStringContainsString('URIUniversalCommunication', $xml);

        $parser = app(AccountingEInvoiceParserService::class);
        $parsed = $parser->parseXmlString($xml);

        $this->assertSame('UN/CEFACT CII (ZUGFeRD / XRechnung)', $parsed['format']);
        $this->assertSame('RE-2026-9002', $parsed['invoice_number']);
        $this->assertEqualsWithDelta(119.00, $parsed['totals']['grand_total_gross'], 0.01);
        $this->assertEqualsWithDelta(100.00, $parsed['totals']['tax_basis_net'], 0.01);
        $this->assertEqualsWithDelta(19.00, $parsed['totals']['tax_total'], 0.01);
        $this->assertTrue($parsed['valid']);
        $this->assertNotEmpty($parsed['validation_checks']);

        // Also test parsing a supplier CII XML like Glücksfieber-Perlen (RE2613298)
        $supplierXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">
  <rsm:ExchangedDocumentContext>
    <ram:GuidelineSpecifiedDocumentContextParameter>
      <ram:ID>urn:cen.eu:en16931:2017</ram:ID>
    </ram:GuidelineSpecifiedDocumentContextParameter>
  </rsm:ExchangedDocumentContext>
  <rsm:ExchangedDocument>
    <ram:ID>RE2613298</ram:ID>
    <ram:TypeCode>380</ram:TypeCode>
    <ram:IssueDateTime>
      <udt:DateTimeString format="102">20260330</udt:DateTimeString>
    </ram:IssueDateTime>
  </rsm:ExchangedDocument>
  <rsm:SupplyChainTradeTransaction>
    <ram:IncludedSupplyChainTradeLineItem>
      <ram:AssociatedDocumentLineDocument><ram:LineID>1</ram:LineID></ram:AssociatedDocumentLineDocument>
      <ram:SpecifiedTradeProduct><ram:Name>Schlüsselanhänger Herz mit Textfeld</ram:Name></ram:SpecifiedTradeProduct>
      <ram:SpecifiedLineTradeAgreement><ram:NetPriceProductTradePrice><ram:ChargeAmount>33.53</ram:ChargeAmount></ram:NetPriceProductTradePrice></ram:SpecifiedLineTradeAgreement>
      <ram:SpecifiedLineTradeDelivery><ram:BilledQuantity unitCode="H87">1.00</ram:BilledQuantity></ram:SpecifiedLineTradeDelivery>
      <ram:SpecifiedLineTradeSettlement>
        <ram:ApplicableTradeTax><ram:TypeCode>VAT</ram:TypeCode><ram:CategoryCode>S</ram:CategoryCode><ram:RateApplicablePercent>19.00</ram:RateApplicablePercent></ram:ApplicableTradeTax>
        <ram:SpecifiedTradeSettlementLineMonetarySummation><ram:LineTotalAmount>33.53</ram:LineTotalAmount></ram:SpecifiedTradeSettlementLineMonetarySummation>
      </ram:SpecifiedLineTradeSettlement>
    </ram:IncludedSupplyChainTradeLineItem>
    <ram:ApplicableHeaderTradeAgreement>
      <ram:SellerTradeParty>
        <ram:Name>Glücksfieber-Perlen</ram:Name>
        <ram:PostalTradeAddress><ram:CityName>Ringe</ram:CityName><ram:CountryID>DE</ram:CountryID></ram:PostalTradeAddress>
        <ram:SpecifiedTaxRegistration><ram:ID schemeID="VA">DE278852408</ram:ID></ram:SpecifiedTaxRegistration>
      </ram:SellerTradeParty>
      <ram:BuyerTradeParty>
        <ram:Name>Mein-Seelenfunke</ram:Name>
      </ram:BuyerTradeParty>
    </ram:ApplicableHeaderTradeAgreement>
    <ram:ApplicableHeaderTradeDelivery/>
    <ram:ApplicableHeaderTradeSettlement>
      <ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>
      <ram:SpecifiedTradeSettlementHeaderMonetarySummation>
        <ram:LineTotalAmount>437.81</ram:LineTotalAmount>
        <ram:TaxBasisTotalAmount>437.81</ram:TaxBasisTotalAmount>
        <ram:TaxTotalAmount currencyID="EUR">83.18</ram:TaxTotalAmount>
        <ram:GrandTotalAmount>520.99</ram:GrandTotalAmount>
        <ram:DuePayableAmount>0.00</ram:DuePayableAmount>
      </ram:SpecifiedTradeSettlementHeaderMonetarySummation>
    </ram:ApplicableHeaderTradeSettlement>
  </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>
XML;

        $supplierParsed = $parser->parseXmlString($supplierXml);
        $this->assertSame('RE2613298', $supplierParsed['invoice_number']);
        $this->assertSame('Glücksfieber-Perlen', $supplierParsed['seller']['name']);
        $this->assertEqualsWithDelta(520.99, $supplierParsed['totals']['grand_total_gross'], 0.01);
        $this->assertEqualsWithDelta(0.00, $supplierParsed['totals']['due_payable_amount'], 0.01);
    }

    public function test_point_4_product_addons_surcharge_calculated_in_cart_service(): void
    {
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen Kristall',
            'slug' => 'der-seelen-kristall',
            'price' => 3990, // 39,90 €
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'variants_data' => [
                [
                    'id' => 'var_luftpolster',
                    'name' => 'Luftpolstertasche',
                    'price' => '',
                    'active' => true,
                ],
                [
                    'id' => 'var_edle_box',
                    'name' => 'Edle Box',
                    'price' => '4,99', // +4,99 € Aufpreis
                    'active' => true,
                ],
            ],
            'configurator_settings' => [
                'addons' => [
                    [
                        'id' => 'addon_giftbox',
                        'name' => 'Schwarze edle Geschenkbox',
                        'description' => 'Edle Geschenkbox mit Samteinlage statt Standard-Luftpolstertasche',
                        'price' => '4.90',
                        'category' => 'Verpackung',
                    ],
                ],
            ],
        ]);

        $cartService = app(CartService::class);
        // Manipulations-Versuch: Client versucht manipulierte addons_summary (1 Cent, fremder Name, SSRF-URL) einzuschleusen
        $cartService->addItem($product, 1, [
            'variant_id' => 'var_edle_box',
            'variant_name' => 'Manipulierter Variantenname',
            'selected_addons' => [
                ['id' => 'addon_giftbox'],
                ['id' => 'non_existent_addon'],
            ],
            'addons_summary' => [
                [
                    'id' => 'addon_giftbox',
                    'name' => 'Manipulierter Gratis-Artikel',
                    'price_cents' => 1,
                    'image_path' => 'https://evil.example.com/ssrf.jpg',
                ],
            ],
        ]);

        $cart = $cartService->getCart(false);
        $item = $cart->items()->first();

        // 3990 cents (39,90 €) + 499 cents Matrix-Aufpreis (4,99 €) + 490 cents Addon (4,90 €) = 4979 cents (49,79 €)
        $this->assertSame(4979, $item->unit_price);
        $this->assertSame('Edle Box', $item->configuration['variant_name']);
        $this->assertSame(['addon_giftbox'], $item->configuration['selected_addons']);
        $this->assertCount(1, $item->configuration['addons_summary']);
        $this->assertSame('Schwarze edle Geschenkbox', $item->configuration['addons_summary'][0]['name']);
        $this->assertSame(490, $item->configuration['addons_summary'][0]['price_cents']);
        $this->assertSame('produkte/addons/edle-schmuckbox-schwarz-gold.jpg', $item->configuration['addons_summary'][0]['image_path']);

        // SSRF- & Path-Traversal-Abwehr prüfen
        $this->assertNull(Product::resolveAddonImagePath(['image_path' => 'https://evil.example.com/ssrf.png']));
        $this->assertNull(Product::resolveAddonImagePath(['image_path' => '//evil.example.com/ssrf.png']));
        $this->assertNull(Product::resolveAddonImagePath(['image_path' => '../../.env']));
        $this->assertNull(Product::resolveAddonImageUrl('https://evil.example.com/ssrf.png'));

        // Physische Existenz & Echtheit der beiden Standard-Assets in storage/app/public/produkte/addons/ prüfen
        $boxAssetPath = storage_path('app/public/produkte/addons/edle-schmuckbox-schwarz-gold.jpg');
        $wrapAssetPath = storage_path('app/public/produkte/addons/geschenkpapier-schwarz-gold.jpg');
        $this->assertFileExists($boxAssetPath);
        $this->assertFileExists($wrapAssetPath);
        $this->assertSame('image/jpeg', mime_content_type($boxAssetPath));
        $this->assertSame('image/jpeg', mime_content_type($wrapAssetPath));

        // Prüfen, dass mail_item_list (Rechnungs-PDF, Angebots-PDF & alle E-Mails) Variante, Extras und eingebettetes Bild sauber darstellt
        $renderedMailList = view('global.mails.partials.mail_item_list', [
            'data' => [
                'is_netto' => false,
                'items' => [
                    [
                        'name' => $product->name,
                        'quantity' => 1,
                        'single_price' => 49.79,
                        'total_price' => 49.79,
                        'tax_rate' => 19,
                        'configuration' => $item->configuration,
                    ],
                ],
                'totals' => [
                    'subtotal' => 41.84,
                    'shipping' => 0,
                    'express' => 0,
                    'total_netto' => 41.84,
                    'total_brutto' => 49.79,
                    'taxes' => ['19' => 7.95],
                ],
            ],
            'isPdf' => true,
        ])->render();

        $this->assertStringContainsString('Ausführung:', $renderedMailList);
        $this->assertStringContainsString('Edle Box', $renderedMailList);
        $this->assertStringContainsString('Verpackung &amp; Extras:', $renderedMailList);
        $this->assertStringContainsString('Schwarze edle Geschenkbox (+4,90 €)', $renderedMailList);
        $this->assertStringContainsString('data:image/jpeg;base64,', $renderedMailList);
    }

    public function test_point_5_gdpr_ai_data_consent_redacts_customer_data(): void
    {
        $customerWithoutConsent = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Geheim',
            'last_name' => 'Person',
            'email' => 'geheim@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $customerWithoutConsent->profile()->create([
            'ai_data_consent' => false,
        ]);

        SupportTicket::create([
            'id' => (string) Str::uuid(),
            'ticket_number' => 'TKT-9999',
            'customer_id' => $customerWithoutConsent->id,
            'category' => 'general',
            'subject' => 'Mein privates Anliegen mit Namen Geheim Person',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $admin = \App\Models\Admin\Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'Chef',
            'email' => 'admin@seelenfunke.test',
            'password' => bcrypt('secret123'),
        ]);
        $this->actingAs($admin, 'admin');

        $supportCaller = new class {
            use AiSupportFuncs;
        };
        $supportResult = $supportCaller::executeAdminSearchSupportTickets(['search_term' => 'TKT-9999']);
        $serialized = json_encode($supportResult);

        $this->assertStringNotContainsString('Geheim', $serialized);
        $this->assertStringNotContainsString('geheim@example.com', $serialized);
        $this->assertStringContainsString('Anonyme Person', $serialized);

        // Searching by customer name when ai_data_consent === false must NOT return the ticket
        $nameSearchResult = $supportCaller::executeAdminSearchSupportTickets(['search_term' => 'Geheim']);
        $this->assertEmpty($nameSearchResult['data'] ?? []);
    }

    public function test_points_7_and_8_funki_page_and_magic_activation_redirect(): void
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Sarah',
            'last_name' => 'Sonnenschein',
            'email' => 'sarah@example.com',
            'password' => bcrypt('secret123'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($customer, 'customer');

        // Before Magic is activated, /funki must redirect to customer.dashboard
        $responseBefore = $this->get(route('customer.funki'));
        $responseBefore->assertRedirect(route('customer.dashboard'));

        // Activate Magic
        Livewire::test(CustomerDashboardComponent::class)
            ->call('activateSeelenfunke')
            ->assertDispatched('gamification-mode-changed');

        $gamification = CustomerGamification::where('customer_id', $customer->id)->first();
        $this->assertNotNull($gamification);
        $this->assertTrue((bool) $gamification->is_active);

        // After Magic is activated, /funki is accessible
        $responseAfter = $this->get(route('customer.funki'));
        $responseAfter->assertStatus(200);
        $responseAfter->assertSee('Dein Funki');
    }

    public function test_universal_main_and_hover_media_image_and_video_logic(): void
    {
        $admin = \App\Models\Admin\Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'Media',
            'email' => 'admin-media@seelenfunke.test',
            'password' => bcrypt('secret123'),
        ]);
        $this->actingAs($admin, 'admin');

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Goldenes Seelen-Herz Test',
            'slug' => 'goldenes-seelen-herz-test',
            'price' => 4290,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'configurator_settings' => [
                'auto_seasonal_main_image' => false,
            ],
            'media_gallery' => [
                [
                    'type' => 'image',
                    'path' => 'produkte/products/goldenes-seelen-herz/goldenes-seelen-herz-main.jpg',
                    'is_main' => true,
                    'is_hover' => false,
                    'is_ai_generated' => true,
                    'alt' => 'Hauptbild',
                ],
                [
                    'type' => 'image',
                    'path' => 'produkte/products/goldenes-seelen-herz/goldenes-seelen-herz-lifestyle.jpg',
                    'is_main' => false,
                    'is_hover' => true,
                    'is_ai_generated' => true,
                    'alt' => 'Hoverbild',
                ],
                [
                    'type' => 'video',
                    'path' => 'produkte/products/goldenes-seelen-herz/demo-showcase.mp4',
                    'is_main' => false,
                    'is_hover' => false,
                    'is_ai_generated' => false,
                    'alt' => 'Produktvideo',
                ],
            ],
        ]);

        // 1. Standard: Hauptbild + Hoverbild
        $mainMeta = $product->resolveMainMediaMeta();
        $hoverMeta = $product->resolveHoverMediaMeta($mainMeta['path']);
        $this->assertSame('image', $mainMeta['type']);
        $this->assertSame('produkte/products/goldenes-seelen-herz/goldenes-seelen-herz-main.jpg', $mainMeta['path']);
        $this->assertNotNull($hoverMeta);
        $this->assertSame('image', $hoverMeta['type']);
        $this->assertSame('produkte/products/goldenes-seelen-herz/goldenes-seelen-herz-lifestyle.jpg', $hoverMeta['path']);

        // 2. Admin ProductCreate: Video (Index 2) als Hover-Video setzen
        $component = Livewire::test(\App\Livewire\Shop\Product\ProductCreate::class)
            ->call('edit', $product->id)
            ->call('setHoverImage', 2);

        $product->refresh();
        $hoverVideoMeta = $product->resolveHoverMediaMeta();
        $this->assertNotNull($hoverVideoMeta);
        $this->assertSame('video', $hoverVideoMeta['type']);
        $this->assertSame('produkte/products/goldenes-seelen-herz/demo-showcase.mp4', $hoverVideoMeta['path']);

        // 3. Admin ProductCreate: Video (Index 2) als Hauptansicht (Hauptvideo) setzen
        $component->call('setMainImage', 2);
        $product->refresh();

        $mainVideoMeta = $product->resolveMainMediaMeta();
        $this->assertSame('video', $mainVideoMeta['type']);
        $this->assertSame('produkte/products/goldenes-seelen-herz/demo-showcase.mp4', $mainVideoMeta['path']);
        // Poster-Fallback liefert automatisch das erste verfügbare statische Bild
        $this->assertNotEmpty($mainVideoMeta['poster_url']);
        // Da das Video jetzt Hauptansicht ist, wurde dessen is_hover automatisch bereinigt
        $this->assertNull($product->resolveHoverMediaMeta($mainVideoMeta['path']));
        // Und resolveMainImageMeta() liefert weiterhin das statische Bild für SEO / OpenGraph / Google Shopping
        $staticImageMeta = $product->resolveMainImageMeta();
        $this->assertSame('image', $staticImageMeta['type']);
        $this->assertSame('produkte/products/goldenes-seelen-herz/goldenes-seelen-herz-main.jpg', $staticImageMeta['path']);
    }
}

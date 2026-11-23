<?php

namespace Tests\Feature\Shop;

use App\Livewire\Shop\Cart\Cart as CartComponent;
use App\Livewire\Shop\Marketing\MarketingVoucherPurchasePage;
use App\Livewire\Shop\Order\OrderCheckout\OrderCheckout;
use App\Livewire\Shop\Order\OrderQuoteAcceptance;
use App\Livewire\Shop\Product\ProductCalculator\ProductCalculator;
use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Livewire\Shop\Product\ProductFrontendFilterArea;
use App\Livewire\Shop\Support\SupportContactFormComponent;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminProfile;
use App\Models\Cart\CartItem;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Order\OrderQuoteRequestItem;
use App\Models\Product\Product;
use App\Models\Support\SupportContactRequest;
use App\Models\System\SystemSetting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ShowcaseMaintenanceAndCalculatorLockTest extends TestCase
{
    use RefreshDatabase;

    private function setShopFlags(bool $shopIsLive, bool $maintenanceMode, bool $quoteCalculatorEnabled = true): void
    {
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => $shopIsLive ? 'true' : 'false']);
        SystemSetting::updateOrCreate(['key' => 'shop_live_unlocked'], ['value' => $shopIsLive ? 'true' : 'false']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => $maintenanceMode ? 'true' : 'false']);
        SystemSetting::updateOrCreate(['key' => 'quote_calculator_enabled'], ['value' => $quoteCalculatorEnabled ? 'true' : 'false']);
        Cache::forget('global_shop_settings');
    }

    private function createAdmin(): Admin
    {
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => Hash::make('Secret123!'),
        ]);

        AdminProfile::firstOrCreate(
            ['admin_id' => $admin->id],
            ['city' => 'Gifhorn', 'country' => 'DE']
        );

        return $admin;
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Seelen Kristall Herz',
            'slug' => 'seelen-kristall-herz-' . Str::lower(Str::random(5)),
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 4990,
            'short_description' => 'Optisch reines K9-Kristallglas mit feiner Lasergravur.',
            'description' => 'Handveredelt in Deutschland in unserer Manufaktur Gifhorn.',
            'track_quantity' => true,
            'quantity' => 15,
            'continue_selling_when_out_of_stock' => true,
            'width' => 80,
            'height' => 80,
            'length' => 40,
        ], $overrides));
    }

    public function test_maintenance_notice_component_enforces_clean_light_mode_without_dark_or_amber_or_en_dash(): void
    {
        $bladePath = resource_path('views/components/alerts/shop-maintenance-notice.blade.php');
        $this->assertFileExists($bladePath);

        $contents = (string) file_get_contents($bladePath);
        $this->assertStringNotContainsString('dark:', $contents);
        $this->assertStringNotContainsString('bg-amber', $contents);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $contents);
        $this->assertStringContainsString('#C5A059', $contents);
    }

    public function test_showcase_maintenance_mode_keeps_catalog_and_product_pages_viewable_while_blocking_all_purchases_and_quotes(): void
    {
        Mail::fake();

        $personalizableProduct = $this->createProduct([
            'name' => 'Seelen Kristall Unikat',
            'slug' => 'seelen-kristall-unikat',
            'is_personalizable' => true,
        ]);

        $standardProduct = $this->createProduct([
            'name' => 'Geschenkset Pur',
            'slug' => 'geschenkset-pur',
            'is_personalizable' => false,
            'price' => 1990,
        ]);

        $voucherProduct = $this->createProduct([
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'type' => 'digital',
            'is_personalizable' => true,
            'price' => 5000,
        ]);

        // Vorab (vor Wartung) einen Artikel in den Kunden-Warenkorb legen, um /cart und /checkout im Wartungsmodus zu prüfen
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->setShopFlags(shopIsLive: true, maintenanceMode: false, quoteCalculatorEnabled: true);
        app(CartService::class)->addItem($personalizableProduct, 1, ['text' => 'Für Mama']);
        $cartBefore = app(CartService::class)->getCart(false);
        $this->assertNotNull($cartBefore);
        $this->assertSame(1, CartItem::count());
        $existingCartItem = $cartBefore->items()->first();

        // Jetzt Schaufenster-Wartungsmodus aktivieren (shop_is_live = true, maintenance_mode = true)
        $this->setShopFlags(shopIsLive: true, maintenanceMode: true, quoteCalculatorEnabled: true);

        // 1. /shop & Showroom-JSON liefern HTTP 200 mit Produktinfos (auch als Gast)
        $this->app['auth']->forgetGuards();
        $this->get('/shop')
            ->assertStatus(200)
            ->assertSee('Seelen Kristall Unikat')
            ->assertSee('Geschenkset Pur');

        $showroomResp = $this->getJson('/shop/showroom-data');
        $showroomResp->assertStatus(200)
            ->assertJsonPath('success', true);
        $showroomProducts = collect($showroomResp->json('products'));
        $stdEntry = $showroomProducts->firstWhere('slug', 'geschenkset-pur');
        $this->assertNotNull($stdEntry);
        $this->assertFalse((bool) ($stdEntry['can_direct_add_to_cart'] ?? true));

        // Katalog-Schnellkauf serverseitig gesperrt
        $filterComp = Livewire::test(ProductFrontendFilterArea::class);
        $filterComp->call('addToCart', (string) $standardProduct->id);
        $this->assertSame(1, CartItem::count());

        // 2. /produkt/{slug} liefert HTTP 200 mit Produktinfos + Light-Mode-Wartungshinweis statt Konfigurator/Warenkorb-Button
        $this->get('/produkt/' . $personalizableProduct->slug)
            ->assertStatus(200)
            ->assertSee('Seelen Kristall Unikat')
            ->assertSee('Optisch reines K9-Kristallglas mit feiner Lasergravur.')
            ->assertSee('Kurze Wartung am Konfigurator & Bestellsystem')
            ->assertSee('data-shop-maintenance-notice="true"', false)
            ->assertDontSee('Wie möchtest du starten?');

        $this->get('/produkt/' . $standardProduct->slug)
            ->assertStatus(200)
            ->assertSee('Geschenkset Pur')
            ->assertSee('Kurze Wartung am Konfigurator & Bestellsystem')
            ->assertDontSee('Standard / Fertig Set');

        // 3. Geschenkgutschein-Seite liefert HTTP 200 mit Wartungshinweis und blockiert addToCart() serverseitig
        $this->get('/produkt/' . $voucherProduct->slug)
            ->assertStatus(200)
            ->assertSee('Geschenkgutscheine')
            ->assertSee('Kurze Wartung am Konfigurator & Bestellsystem');

        Livewire::test(MarketingVoucherPurchasePage::class)
            ->set('amount', 50)
            ->set('recipientName', 'Max Mustermann')
            ->set('deliveryMethod', 'email')
            ->set('recipientEmail', 'max@example.com')
            ->set('currentStep', 3)
            ->assertSee('Kurze Wartung am Konfigurator & Bestellsystem')
            ->assertDontSee('In den Warenkorb legen')
            ->call('addToCart')
            ->assertHasErrors(['maintenance']);

        $this->assertSame(1, CartItem::count());

        // 4. ProductConfigurator blockiert serverseitig in add, edit und calculator (falls alter Tab offen war), erlaubt aber template_admin für Admin
        Livewire::actingAs($customer, 'customer')
            ->test(ProductConfigurator::class, [
                'product' => $personalizableProduct,
                'context' => 'add',
            ])
            ->set('config_confirmed', true)
            ->call('save')
            ->assertHasErrors(['maintenance']);

        Livewire::actingAs($customer, 'customer')
            ->test(ProductConfigurator::class, [
                'product' => $personalizableProduct,
                'context' => 'add',
            ])
            ->set('config_confirmed', true)
            ->call('saveWithSnapshot', null)
            ->assertHasErrors(['maintenance']);

        Livewire::actingAs($customer, 'customer')
            ->test(ProductConfigurator::class, [
                'product' => $personalizableProduct,
                'cartItem' => $existingCartItem,
                'context' => 'edit',
            ])
            ->set('config_confirmed', true)
            ->call('save')
            ->assertHasErrors(['maintenance']);

        Livewire::test(ProductConfigurator::class, [
            'product' => $personalizableProduct,
            'context' => 'calculator',
        ])
            ->call('save')
            ->assertHasErrors(['maintenance'])
            ->assertNotDispatched('calculator-save');

        $admin = $this->createAdmin();
        Livewire::actingAs($admin, 'admin')
            ->test(ProductConfigurator::class, [
                'product' => $personalizableProduct,
                'context' => 'template_admin',
            ])
            ->set('config_confirmed', true)
            ->call('save')
            ->assertHasNoErrors(['maintenance'])
            ->assertDispatched('save-template-data');

        $this->app['auth']->forgetGuards();
        $this->actingAs($customer, 'customer');
        $this->assertSame(1, CartItem::count());

        // 5. Warenkorb bleibt sichtbar (HTTP 200), blendet Bearbeiten & Zur Kasse aus und blockiert Übergang
        $this->actingAs($customer, 'customer')
            ->get(route('cart'))
            ->assertStatus(200)
            ->assertSee('Seelen Kristall Unikat')
            ->assertSee('Kurze Wartung am Konfigurator & Bestellsystem')
            ->assertDontSee('Zur Kasse');

        Livewire::actingAs($customer, 'customer')
            ->test(CartComponent::class)
            ->call('edit', $existingCartItem->id)
            ->assertSet('editingItemId', null)
            ->call('proceedToCheckout')
            ->assertHasErrors(['maintenance']);

        // 6. Checkout zeigt Light-Mode-Wartungshinweis mit Shop-Button statt Bezahlformular und blockiert Order-Erstellung hart
        $this->actingAs($customer, 'customer')
            ->get(route('checkout'))
            ->assertStatus(200)
            ->assertSee('Kurze Wartung am Konfigurator & Bestellsystem')
            ->assertSee('Weiter im Shop stöbern')
            ->assertDontSee('Klassische Zahlungsmethoden');

        Livewire::actingAs($customer, 'customer')
            ->test(OrderCheckout::class)
            ->set('email', 'kunde@example.com')
            ->set('first_name', 'Erika')
            ->set('last_name', 'Mustermann')
            ->set('address', 'Musterweg 12')
            ->set('postal_code', '38518')
            ->set('city', 'Gifhorn')
            ->set('country', 'DE')
            ->set('terms_accepted', true)
            ->call('validateAndCreateOrder')
            ->assertHasErrors(['cart']);

        $this->assertSame(0, OrderOrder::count());

        // 7. Angebotskalkulator (quote_calculator_enabled = true, maintenance_mode = true) zeigt Wartungshinweis statt 503_fragment und blockiert submit()
        $this->get(route('calculator'))
            ->assertStatus(200)
            ->assertSee('Angebotskalkulator kurzzeitig in Wartung')
            ->assertSee('Weiter im Shop stöbern')
            ->assertDontSee('Jetzt Kalkulation starten');

        Livewire::test(ProductCalculator::class)
            ->assertSee('Angebotskalkulator kurzzeitig in Wartung')
            ->set('form.vorname', 'Max')
            ->set('form.nachname', 'Muster')
            ->set('form.email', 'max@firma.de')
            ->set('form.country', 'DE')
            ->call('submit')
            ->assertHasErrors(['maintenance']);

        $this->assertSame(0, OrderQuoteRequest::count());

        // 8. Angebotsannahme (/angebot/{token}/annehmen) zeigt im Wartungsmodus Wartungshinweis und blockiert acceptQuote() & proceedToCheckout()
        $quote = OrderQuoteRequest::create([
            'quote_number' => 'AN-2026-MAINT',
            'first_name' => 'Julia',
            'last_name' => 'Weber',
            'email' => 'julia@weber-events.de',
            'status' => 'open',
            'token' => Str::random(40),
            'expires_at' => now()->addDays(14),
            'net_total' => 4193,
            'tax_total' => 797,
            'gross_total' => 4990,
            'is_express' => false,
        ]);

        OrderQuoteRequestItem::create([
            'quote_request_id' => $quote->id,
            'product_id' => $personalizableProduct->id,
            'product_name' => $personalizableProduct->name,
            'quantity' => 5,
            'unit_price' => 4990,
            'total_price' => 24950,
            'tax_rate' => 19,
            'configuration' => [],
        ]);

        $this->get(route('quote.accept', ['token' => $quote->token]))
            ->assertStatus(200)
            ->assertSee('AN-2026-MAINT')
            ->assertSee('Kurze Wartung am Konfigurator & Bestellsystem')
            ->assertDontSee('Zur Kasse & Bezahlen');

        Livewire::test(OrderQuoteAcceptance::class, ['token' => $quote->token])
            ->call('acceptQuote')
            ->assertHasErrors(['maintenance'])
            ->call('proceedToCheckout')
            ->assertHasErrors(['maintenance']);
    }

    public function test_disabling_quote_calculator_blocks_public_calculator_keeps_token_quote_acceptance_open_and_fixes_support_canned_response(): void
    {
        $product = $this->createProduct([
            'name' => 'B2B Schiefer Trophäe',
            'slug' => 'b2b-schiefer-trophaee',
            'price' => 2990,
        ]);

        // Zuerst bei aktiviertem Kalkulator eine offene Instanz & den Support-Schnellbaustein prüfen
        $this->setShopFlags(shopIsLive: true, maintenanceMode: false, quoteCalculatorEnabled: true);

        $openCalculatorTab = Livewire::test(ProductCalculator::class)
            ->call('saveItemFromConfigurator', [
                'product_id' => $product->id,
                'qty' => 3,
            ])
            ->set('form.vorname', 'Sabine')
            ->set('form.nachname', 'König')
            ->set('form.email', 'sabine@koenig.de')
            ->set('form.country', 'DE');

        $admin = $this->createAdmin();
        $contactReq = SupportContactRequest::create([
            'ticket_number' => 'REQ-2026-1001',
            'first_name' => 'Thomas',
            'last_name' => 'Berger',
            'email' => 'thomas@example.com',
            'subject' => 'Anfrage für 30 Mitarbeitergeschenke',
            'message' => 'Wir möchten gerne 30 Trophäen mit Vereinslogo anfragen.',
            'category' => 'B2B',
            'status' => 'new',
        ]);

        $supportEnabledTest = Livewire::actingAs($admin, 'admin')
            ->test(SupportContactFormComponent::class)
            ->call('openRequest', $contactReq->id)
            ->assertSee("insertCannedResponse('calculator')", false)
            ->call('insertCannedResponse', 'calculator');

        $replyText = (string) $supportEnabledTest->get('replyMessage');
        $this->assertStringContainsString(route('calculator'), $replyText);
        $this->assertStringContainsString('/calculator', $replyText);
        $this->assertStringNotContainsString('/kalkulator', $replyText);

        // Jetzt quote_calculator_enabled = false setzen (ohne Wartungsmodus)
        $this->setShopFlags(shopIsLive: true, maintenanceMode: false, quoteCalculatorEnabled: false);

        // 1. Öffentlicher Kalkulator liefert HTTP 404 (Route + Livewire mount + bereits geöffneter Tab vor submit)
        $this->app['auth']->forgetGuards();
        $this->get('/calculator')->assertStatus(404);

        Livewire::test(ProductCalculator::class)->assertStatus(404);

        $openCalculatorTab->call('submit')->assertStatus(404);
        $this->assertSame(0, OrderQuoteRequest::count());

        // Admin-Kontext bleibt erlaubt
        Livewire::actingAs($admin, 'admin')
            ->test(ProductCalculator::class, ['isAdminContext' => true])
            ->assertStatus(200);

        // 2. /angebot/{token}/annehmen bleibt für Bestandskunden mit Token erreichbar und annehmbar
        $quote = OrderQuoteRequest::create([
            'quote_number' => 'AN-2026-TOKENOK',
            'first_name' => 'Martin',
            'last_name' => 'Schulz',
            'email' => 'martin@schulz-gmbh.de',
            'status' => 'open',
            'token' => Str::random(40),
            'expires_at' => now()->addDays(10),
            'net_total' => 7538,
            'tax_total' => 1432,
            'gross_total' => 8970,
            'is_express' => false,
        ]);

        OrderQuoteRequestItem::create([
            'quote_request_id' => $quote->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 3,
            'unit_price' => 2990,
            'total_price' => 8970,
            'tax_rate' => 19,
            'configuration' => [],
        ]);

        $this->get(route('quote.accept', ['token' => $quote->token]))
            ->assertStatus(200)
            ->assertSee('AN-2026-TOKENOK')
            ->assertSee('Zur Kasse & Bezahlen');

        Livewire::test(OrderQuoteAcceptance::class, ['token' => $quote->token])
            ->call('acceptQuote')
            ->assertRedirect(route('checkout'));

        $cart = app(CartService::class)->getCart(false);
        $this->assertNotNull($cart);
        $this->assertSame(3, (int) $cart->items()->first()->quantity);

        // 3. SupportContactFormComponent blendet den Kalkulator-Schnellbaustein aus und ignoriert Direktaufrufe
        Livewire::actingAs($admin, 'admin')
            ->test(SupportContactFormComponent::class)
            ->call('openRequest', $contactReq->id)
            ->assertDontSee("insertCannedResponse('calculator')", false)
            ->set('replyMessage', '')
            ->call('insertCannedResponse', 'calculator')
            ->assertSet('replyMessage', '');

        // 4. Startseite (/), Navigation, Footer, AGB, Datenschutz, Sitemap & LLMs.txt blenden alle Kalkulator-/B2B-Hinweise aus, behalten aber die helle Mittel-Sektion bei
        $this->get('/')
            ->assertStatus(200)
            ->assertSee('Dein Design in Echtzeit')
            ->assertSee('Jedes Unikat')
            ->assertSee('Unikat auswählen & gestalten', false)
            ->assertDontSee('Für B2B Kunden, Teams & Vereine')
            ->assertDontSee('Wertschätzung mit')
            ->assertDontSee('Attraktive Mengenrabatte')
            ->assertDontSee('Jetzt Serien-Preis berechnen')
            ->assertDontSee('Angebot kalkulieren')
            ->assertDontSee('Angebotskalkulator öffnen')
            ->assertDontSee('B2B Projekt-Kalkulator');

        $this->get('/agb')
            ->assertStatus(200)
            ->assertDontSee('B2B-Projekt-Kalkulator')
            ->assertDontSee('oder Kalkulator hochlädt');

        $this->get('/sitemap.xml')
            ->assertStatus(200)
            ->assertDontSee('/calculator');

        $this->get('/llms.txt')
            ->assertStatus(200)
            ->assertDontSee('/calculator')
            ->assertDontSee('Mengenrabatte/Staffelpreise');
    }
}

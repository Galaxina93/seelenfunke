<?php

namespace Tests\Feature\Shop;

use App\Livewire\Customer\CustomerDashboardComponent;
use App\Livewire\Customer\CustomerFunkiComponent;
use App\Livewire\Shop\Product\ProductCalculator\ProductCalculator;
use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ConfiguratorPermanentCtaAndStarWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'shop_live_unlocked'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'quote_calculator_enabled'], ['value' => 'true']);
        Cache::forget('global_shop_settings');

        $this->ensureExperienceReviewsTableExists();
    }

    private function ensureExperienceReviewsTableExists(): void
    {
        if (Schema::hasTable('support_experience_reviews')) {
            return;
        }

        Schema::create('support_experience_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id')->index();
            $table->string('area', 32);
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('done');
            $table->string('device_type', 16)->default('desktop');
            $table->uuid('product_id')->nullable();
            $table->string('product_name', 190)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'area']);
        });
    }

    private function createVerifiedCustomer(array $overrides = []): Customer
    {
        $customer = Customer::create(array_merge([
            'first_name' => 'Clara',
            'last_name' => 'Stern',
            'email' => 'clara-' . Str::lower(Str::random(6)) . '@example.org',
            'password' => Hash::make('Secret123!'),
            'email_verified_at' => now(),
            'needs_password_change' => false,
        ], $overrides));

        if (array_key_exists('email_verified_at', $overrides) && $overrides['email_verified_at'] === null && $customer->profile) {
            $customer->profile->forceFill(['email_verified_at' => null])->save();
        }

        return $customer->fresh();
    }

    private function createActiveProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Seelen Kristall Quader',
            'slug' => 'seelen-kristall-quader-' . Str::lower(Str::random(5)),
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 3990,
            'short_description' => 'Optisch reines K9-Kristallglas mit feiner Lasergravur.',
            'description' => 'Handveredelt in Deutschland (Manufaktur Gifhorn).',
            'track_quantity' => true,
            'quantity' => 10,
            'continue_selling_when_out_of_stock' => true,
            'width' => 60,
            'height' => 60,
            'length' => 40,
        ], $overrides));
    }

    private function insertExperienceReview(Customer $customer, string $area, int $rating = 5, ?string $comment = null): void
    {
        DB::table('support_experience_reviews')->insert([
            'id' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'area' => $area,
            'rating' => $rating,
            'comment' => $comment,
            'status' => $rating >= 4 ? 'done' : 'open',
            'device_type' => 'desktop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_configurator_keeps_checkout_and_cart_ctas_permanently_visible_without_15s_hide_timer(): void
    {
        $scriptsPath = resource_path('views/livewire/shop/product/product-configurator/partials/scripts_frontend_1.blade.php');
        $footerPath = resource_path('views/livewire/shop/product/product-configurator/partials/footer.blade.php');

        $scriptsContent = (string) file_get_contents($scriptsPath);
        $footerContent = (string) file_get_contents($footerPath);

        $this->assertStringContainsString('hasAddedToCart: false', $scriptsContent);
        $this->assertStringContainsString('this.hasAddedToCart = true;', $scriptsContent);
        $this->assertStringNotContainsString('this.hasAddedToCart = false', $scriptsContent);
        $this->assertStringNotContainsString('15000', $scriptsContent);
        $this->assertStringContainsString('2500', $scriptsContent);

        $this->assertStringContainsString('x-show="hasAddedToCart"', $footerContent);
        $this->assertStringContainsString('Direkt zur Kasse', $footerContent);
        $this->assertStringContainsString('Zum Warenkorb wechseln', $footerContent);
    }

    public function test_configurator_renders_thin_gold_aura_muted_cta_and_spark_flight_while_calculator_omits_confirm_box(): void
    {
        $product = $this->createActiveProduct();

        // 1. Standard Shop-Kontext ('add'): Pflicht-Checkbox mit Goldrand, untergrauter CTA & 60-FPS-Funkenflug aktiv
        $addHtml = html_entity_decode(Livewire::test(ProductConfigurator::class, [
            'product' => $product,
            'context' => 'add',
        ])->html(), ENT_QUOTES);

        $this->assertStringContainsString("configConfirmed: \$wire.entangle('config_confirmed')", $addHtml);
        $this->assertStringContainsString('sf-magic-thin-aura', $addHtml);
        $this->assertStringContainsString('sf-magic-thin-aura-ring', $addHtml);
        $this->assertStringContainsString('sf-magic-thin-aura-cta', $addHtml);
        $this->assertStringContainsString('x-ref="confirmBox"', $addHtml);
        $this->assertStringContainsString('x-ref="addToCartBtn"', $addHtml);
        $this->assertStringContainsString('@mouseenter="onLockedCtaHover()"', $addHtml);
        $this->assertStringContainsString('@change="onConfirmToggle($event)"', $addHtml);
        $this->assertStringContainsString('cursor-not-allowed', $addHtml);
        $this->assertStringContainsString('onLockedCtaHover()', $addHtml);
        $this->assertStringContainsString("mode: 'hint'", $addHtml);
        $this->assertStringContainsString("mode: 'unlock'", $addHtml);
        $this->assertStringContainsString("mode: 'relock'", $addHtml);
        $this->assertStringContainsString("mode: 'to-cart'", $addHtml);
        $this->assertStringContainsString('triggerConfirmToCartSparkFlight', $addHtml);
        $this->assertStringContainsString('drawFourPointStar', $addHtml);
        $this->assertStringContainsString('#E5C17B', $addHtml);
        $this->assertStringNotContainsString('#F4A7B9', $addHtml);
        $this->assertStringNotContainsString('#E88B9C', $addHtml);
        $this->assertStringContainsString('bg-gray-200/95 text-gray-500 border border-gray-300/90 shadow-none', $addHtml);
        $this->assertStringContainsString(':disabled="isSaving"', $addHtml);
        $this->assertStringNotContainsString(':disabled="!configConfirmed"', $addHtml);

        // 2. Kalkulator-Kontext ('calculator'): Keine Pflicht-Checkbox, CTA sofort bereit
        $calcHtml = Livewire::test(ProductConfigurator::class, [
            'product' => $product,
            'context' => 'calculator',
        ])->html();

        $this->assertStringNotContainsString('x-ref="confirmBox"', $calcHtml);
        $this->assertStringContainsString('x-ref="addToCartBtn"', $calcHtml);
        $this->assertStringContainsString('Übernehmen', $calcHtml);
    }

    public function test_guests_unverified_and_password_change_customers_never_see_experience_star_widgets(): void
    {
        $product = $this->createActiveProduct();

        // 1. Gast im Konfigurator & Kalkulator
        Livewire::test(ProductConfigurator::class, ['product' => $product])
            ->assertDontSee('data-experience-star-widget', false)
            ->assertDontSee('Konfigurator bewerten');

        Livewire::test(ProductCalculator::class)
            ->set('step', 3)
            ->assertDontSee('data-experience-star-widget', false)
            ->assertDontSee('Kalkulator bewerten');

        // 2. Unverifizierter Kunde
        $unverified = $this->createVerifiedCustomer(['email_verified_at' => null]);
        $this->actingAs($unverified, 'customer');

        Livewire::test(ProductConfigurator::class, ['product' => $product])
            ->assertDontSee('data-experience-star-widget', false);

        // 3. Kunde mit erzwungenem Passwortwechsel
        $needsPwChange = $this->createVerifiedCustomer(['needs_password_change' => true]);
        $this->actingAs($needsPwChange, 'customer');

        Livewire::test(ProductConfigurator::class, ['product' => $product])
            ->assertDontSee('data-experience-star-widget', false);

        // 4. Soft-Deleted Kunde
        $deletedCustomer = $this->createVerifiedCustomer();
        $deletedCustomer->delete();
        $this->actingAs($deletedCustomer, 'customer');

        Livewire::test(ProductConfigurator::class, ['product' => $product])
            ->assertDontSee('data-experience-star-widget', false);
    }

    public function test_verified_customer_sees_star_widget_in_all_three_core_areas_with_full_interactive_markup(): void
    {
        $customer = $this->createVerifiedCustomer();
        CustomerGamification::updateOrCreate(['customer_id' => $customer->id], ['is_active' => true]);
        $product = $this->createActiveProduct();
        $this->actingAs($customer, 'customer');

        // 1. Produktkonfigurator (Light-Mode, inkl. Produkt-ID, 1-3 Sterne Input + Ändern-Mechanik)
        $configuratorHtml = Livewire::test(ProductConfigurator::class, ['product' => $product])->html();
        $this->assertStringContainsString('data-experience-star-widget="configurator"', $configuratorHtml);
        $this->assertStringContainsString('Konfigurator bewerten', $configuratorHtml);
        $this->assertStringContainsString($product->id, $configuratorHtml);
        $this->assertStringContainsString('/customer/experience-reviews', $configuratorHtml);
        $this->assertStringContainsString('Was können wir verbessern?', $configuratorHtml);
        $this->assertStringContainsString('maxlength="500"', $configuratorHtml);
        $this->assertStringContainsString('Senden', $configuratorHtml);
        $this->assertStringContainsString('Danke!', $configuratorHtml);
        $this->assertStringContainsString('Ändern', $configuratorHtml);
        $this->assertStringContainsString('reopenStars()', $configuratorHtml);
        $this->assertStringContainsString('#C5A059', $configuratorHtml);
        $this->assertStringContainsString('#F5B301', $configuratorHtml);
        $this->assertStringContainsString('#D97706', $configuratorHtml);

        // 2. Funkenreich (nur im CustomerDashboard beim Hauptbutton „Ins Funkenreich“, nicht doppelt bei „Rabatt-Gutscheine freischalten“)
        $dashboardHtml = Livewire::test(CustomerDashboardComponent::class)->html();
        $this->assertStringContainsString('data-experience-star-widget="funkenreich"', $dashboardHtml);
        $this->assertStringContainsString('3D-Atelier bewerten', $dashboardHtml);
        $this->assertStringContainsString('sf_visited_funkenreich_v1', $dashboardHtml);
        $this->assertStringContainsString('sf-funkenreich-visited', $dashboardHtml);

        $funkiHtml = Livewire::test(CustomerFunkiComponent::class)->html();
        $this->assertStringNotContainsString('data-experience-star-widget="funkenreich"', $funkiHtml);

        // 3. Angebots-Kalkulator (Step 3 Formular & Step 1 mit Artikel)
        $calculatorStep3Html = Livewire::test(ProductCalculator::class)
            ->set('step', 3)
            ->html();
        $this->assertStringContainsString('data-experience-star-widget="calculator"', $calculatorStep3Html);
        $this->assertStringContainsString('Kalkulator bewerten', $calculatorStep3Html);
    }

    public function test_already_rated_area_hides_inline_widget_on_subsequent_page_loads_without_affecting_other_areas(): void
    {
        $customer = $this->createVerifiedCustomer();
        CustomerGamification::updateOrCreate(['customer_id' => $customer->id], ['is_active' => true]);
        $product = $this->createActiveProduct();
        $this->actingAs($customer, 'customer');

        $this->insertExperienceReview($customer, 'configurator', 5);

        // Konfigurator wurde bereits bewertet -> Widget wird beim nächsten Laden nicht mehr gerendert
        Livewire::test(ProductConfigurator::class, ['product' => $product])
            ->assertDontSee('data-experience-star-widget="configurator"', false)
            ->assertDontSee('Konfigurator bewerten');

        // Kalkulator & Funkenreich sind noch unbewertet -> bleiben sichtbar
        Livewire::test(ProductCalculator::class)
            ->set('step', 3)
            ->assertSee('data-experience-star-widget="calculator"', false)
            ->assertSee('Kalkulator bewerten');

        Livewire::test(CustomerDashboardComponent::class)
            ->assertSee('data-experience-star-widget="funkenreich"', false);

        // Sobald auch Funkenreich & Kalkulator bewertet wurden, blenden sich auch diese aus
        $this->insertExperienceReview($customer, 'funkenreich', 4);
        $this->insertExperienceReview($customer, 'calculator', 3, 'Mehr Staffelstufen anzeigen');

        Livewire::test(CustomerDashboardComponent::class)
            ->assertDontSee('data-experience-star-widget="funkenreich"', false);

        Livewire::test(CustomerFunkiComponent::class)
            ->assertDontSee('data-experience-star-widget="funkenreich"', false);

        Livewire::test(ProductCalculator::class)
            ->set('step', 3)
            ->assertDontSee('data-experience-star-widget="calculator"', false);
    }

    public function test_all_touched_views_and_widget_comply_with_no_en_dash_and_no_inline_x_init_try_catch_rules(): void
    {
        $widgetPath = resource_path('views/components/shop/experience-star-widget.blade.php');
        $widgetSource = (string) file_get_contents($widgetPath);

        // Kein x-init mit Inline-try/catch (Baustein 7)
        $this->assertDoesNotMatchRegularExpression('/x-init\s*=\s*"[^"]*\btry\b/i', $widgetSource);

        // Shop-Modus (darkAware = false) rendert reinen Light-Mode ohne dark:-Klassen
        $customer = $this->createVerifiedCustomer();
        $this->actingAs($customer, 'customer');
        $lightHtml = (string) view('components.shop.experience-star-widget', [
            'area' => 'configurator',
            'productId' => null,
            'requireFunkenreichVisit' => false,
            'darkAware' => false,
        ])->render();
        $this->assertStringNotContainsString('dark:', $lightHtml);
        $this->assertStringNotContainsString('bg-amber', $lightHtml);

        // 0 KI-Gedankenstriche (Leerzeichen + En-Dash + Leerzeichen) & kein Inline-try/catch in x-init in allen neuen/bearbeiteten Dateien
        $enDashPattern = ' ' . "\u{2013}" . ' ';
        $filesToCheck = [
            resource_path('views/components/shop/experience-star-widget.blade.php'),
            resource_path('views/livewire/shop/product/product-configurator/product-configurator.blade.php'),
            resource_path('views/livewire/shop/product/product-configurator/partials/scripts_frontend_1.blade.php'),
            resource_path('views/livewire/shop/product/product-configurator/partials/footer.blade.php'),
            resource_path('views/livewire/customer/customer-dashboard-component.blade.php'),
            resource_path('views/livewire/customer/customer-funki-component.blade.php'),
            resource_path('views/livewire/shop/product/product-calculator/product-calculator.blade.php'),
            base_path('tests/Feature/Shop/ConfiguratorPermanentCtaAndStarWidgetTest.php'),
        ];

        foreach ($filesToCheck as $filePath) {
            $this->assertFileExists($filePath);
            $content = (string) file_get_contents($filePath);
            $this->assertStringNotContainsString(
                $enDashPattern,
                $content,
                'Verbotener KI-Gedankenstrich gefunden in: ' . $filePath
            );
            $this->assertDoesNotMatchRegularExpression(
                '/x-init\s*=\s*"[^"]*\btry\b/i',
                $content,
                'Verbotenes Inline-try/catch in x-init gefunden in: ' . $filePath
            );
        }
    }
}

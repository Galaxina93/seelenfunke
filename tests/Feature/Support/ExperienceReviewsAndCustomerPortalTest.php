<?php

namespace Tests\Feature\Support;

use App\Http\Controllers\Customer\ExperienceReviewController;
use App\Livewire\Customer\CustomerReviewsComponent;
use App\Livewire\Shop\Master\MasterAnalytics;
use App\Livewire\Shop\Product\ProductReviews;
use App\Livewire\Shop\Support\SupportReviews;
use App\Livewire\Traits\ManagesCustomerProductReviewForm;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Employee\Employee;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Order\OrderRevocation;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use App\Models\Support\SupportExperienceReview;
use App\Services\Gamification\GameConfig;
use App\Services\Navigation\BackendNavigationService;
use App\Services\System\SystemBlueprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ExperienceReviewsAndCustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_ineligible_customers_are_strictly_blocked(): void
    {
        // 1. Gast direkt auf Controller -> 403
        $controllerResponse = app(ExperienceReviewController::class)->upsert(
            Request::create('/customer/experience-reviews', 'POST', [
                'area' => SupportExperienceReview::AREA_CONFIGURATOR,
                'rating' => 5,
            ])
        );
        $this->assertSame(403, $controllerResponse->getStatusCode());

        // Gast über HTTP JSON Route wird durch auth:customer geblockt
        $guestHttp = $this->postJson(route('customer.experience-reviews.upsert'), [
            'area' => SupportExperienceReview::AREA_CONFIGURATOR,
            'rating' => 5,
        ]);
        $this->assertContains($guestHttp->status(), [401, 403]);

        // Gast auf CustomerReviewsComponent -> 403
        Livewire::test(CustomerReviewsComponent::class)->assertStatus(403);

        // 2. Unverifizierter Kunde -> 403 auf JSON-Endpunkt & /reviews & null im Helper
        $unverified = Customer::create([
            'first_name' => 'Unverifiziert',
            'last_name' => 'Test',
            'email' => 'unverified-exp@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => null,
        ]);

        $this->actingAs($unverified, 'customer');

        $this->assertNull(SupportExperienceReview::findForAuthenticatedCustomer(SupportExperienceReview::AREA_CONFIGURATOR));

        $unverifiedResp = $this->postJson(route('customer.experience-reviews.upsert'), [
            'area' => SupportExperienceReview::AREA_CONFIGURATOR,
            'rating' => 5,
        ]);
        $unverifiedResp->assertStatus(403);

        $this->get('/reviews')->assertStatus(403);

        // 3. Kunde mit erzwungenem Passwortwechsel -> 403
        $pwForceCustomer = Customer::create([
            'first_name' => 'Temp',
            'last_name' => 'Passwort',
            'email' => 'temp-pw-exp@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
            'needs_password_change' => true,
        ]);

        $this->actingAs($pwForceCustomer, 'customer');
        $this->postJson(route('customer.experience-reviews.upsert'), [
            'area' => SupportExperienceReview::AREA_FUNKENREICH,
            'rating' => 4,
        ])->assertStatus(403);

        // 4. Soft-gelöschter Kunde -> 403
        $trashedCustomer = Customer::create([
            'first_name' => 'Geloescht',
            'last_name' => 'Kunde',
            'email' => 'trashed-exp@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
        ]);
        $trashedCustomer->delete();

        $this->actingAs($trashedCustomer, 'customer');
        $this->assertNull(SupportExperienceReview::findForAuthenticatedCustomer(SupportExperienceReview::AREA_CALCULATOR));
        $this->postJson(route('customer.experience-reviews.upsert'), [
            'area' => SupportExperienceReview::AREA_CALCULATOR,
            'rating' => 5,
        ])->assertStatus(403);

        $this->assertSame(0, SupportExperienceReview::count());
    }

    public function test_experience_review_json_endpoint_upserts_without_duplicates_and_sanitizes_comments(): void
    {
        $customer = Customer::create([
            'first_name' => 'Clara',
            'last_name' => 'Stern',
            'email' => 'clara-stern@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
        ]);

        $product = Product::create([
            'name' => 'Schieferherz Gravur',
            'slug' => 'schieferherz-gravur-exp-test',
            'type' => 'physical',
            'status' => 'active',
            'price' => 2990,
            'quantity' => 0,
        ]);

        $this->actingAs($customer, 'customer');

        // 1. 5 Sterne -> status: done, comment wird null, Gerät mobile erkannt
        $resFive = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) Mobile Safari/537.36',
        ])->postJson(route('customer.experience-reviews.upsert'), [
            'area' => SupportExperienceReview::AREA_CONFIGURATOR,
            'rating' => 5,
            'comment' => 'Sollte bei 5 Sternen verworfen werden',
            'product_id' => $product->id,
        ]);

        $resFive->assertOk()->assertJson([
            'ok' => true,
            'review' => [
                'area' => 'configurator',
                'rating' => 5,
                'comment' => null,
                'status' => 'done',
            ],
        ]);

        $this->assertSame(1, SupportExperienceReview::count());
        $found = SupportExperienceReview::findForAuthenticatedCustomer(SupportExperienceReview::AREA_CONFIGURATOR);
        $this->assertNotNull($found);
        $this->assertSame(5, $found->rating);
        $this->assertSame('mobile', $found->device_type);
        $this->assertSame('Schieferherz Gravur', $found->product_name);
        $this->assertNotNull($found->resolved_at);

        // 2. Korrektur auf 2 Sterne mit langem Text + HTML-Tags -> Upsert ohne Dublette & status: open
        $longDirtyComment = '  <b>Vorschau ruckelt leicht</b> <script>alert(1)</script> ' . str_repeat('x', 520);

        $resTwo = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        ])->postJson(route('customer.experience-reviews.upsert'), [
            'area' => SupportExperienceReview::AREA_CONFIGURATOR,
            'rating' => 2,
            'comment' => $longDirtyComment,
        ]);

        $resTwo->assertOk()->assertJsonPath('ok', true)
            ->assertJsonPath('review.rating', 2)
            ->assertJsonPath('review.status', 'open');

        $this->assertSame(1, SupportExperienceReview::count());
        $updated = $found->fresh();
        $this->assertNotNull($updated);
        $this->assertSame(2, $updated->rating);
        $this->assertSame('open', $updated->status);
        $this->assertSame('desktop', $updated->device_type);
        $this->assertNull($updated->resolved_at);
        $this->assertStringStartsWith('Vorschau ruckelt leicht alert(1)', (string) $updated->comment);
        $this->assertStringNotContainsString('<b>', (string) $updated->comment);
        $this->assertLessThanOrEqual(500, mb_strlen((string) $updated->comment));

        // 3. Klick auf 1 Stern ohne Kommentar behält bisherigen Kommentar
        $savedComment = $updated->comment;
        $this->postJson(route('customer.experience-reviews.upsert'), [
            'area' => SupportExperienceReview::AREA_CONFIGURATOR,
            'rating' => 1,
        ])->assertOk()->assertJsonPath('review.comment', $savedComment);

        $this->assertSame(1, SupportExperienceReview::count());
    }

    public function test_customer_reviews_portal_page_handles_both_tabs_product_reviews_and_experience_feedback(): void
    {
        Storage::fake('public');

        $customer = Customer::create([
            'first_name' => 'Hannah',
            'last_name' => 'Kundin',
            'email' => 'hannah-portal@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
        ]);

        $purchasedProduct = Product::create([
            'name' => 'Edelstahl Armband Gold',
            'slug' => 'edelstahl-armband-gold-test',
            'type' => 'physical',
            'status' => 'active',
            'price' => 3990,
            'quantity' => 0,
        ]);

        $revokedProduct = Product::create([
            'name' => 'Widerrufener Schlüsselanhänger',
            'slug' => 'widerrufener-anhanger-test',
            'type' => 'physical',
            'status' => 'active',
            'price' => 1990,
            'quantity' => 0,
        ]);

        $paidOrder = OrderOrder::create([
            'order_number' => 'SF-REV-1001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Hannah', 'last_name' => 'Kundin', 'city' => 'Gifhorn'],
            'shipping_address' => ['first_name' => 'Hannah', 'last_name' => 'Kundin', 'city' => 'Gifhorn'],
            'subtotal_price' => 3990,
            'tax_amount' => 637,
            'shipping_price' => 0,
            'total_price' => 3990,
        ]);

        OrderOrderItem::create([
            'order_id' => $paidOrder->id,
            'product_id' => $purchasedProduct->id,
            'product_name' => $purchasedProduct->name,
            'quantity' => 1,
            'unit_price' => 3990,
            'total_price' => 3990,
        ]);

        $revokedOrder = OrderOrder::create([
            'order_number' => 'SF-REV-1002',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Hannah', 'last_name' => 'Kundin', 'city' => 'Gifhorn'],
            'shipping_address' => ['first_name' => 'Hannah', 'last_name' => 'Kundin', 'city' => 'Gifhorn'],
            'subtotal_price' => 1990,
            'tax_amount' => 318,
            'shipping_price' => 0,
            'total_price' => 1990,
        ]);

        OrderOrderItem::create([
            'order_id' => $revokedOrder->id,
            'product_id' => $revokedProduct->id,
            'product_name' => $revokedProduct->name,
            'quantity' => 1,
            'unit_price' => 1990,
            'total_price' => 1990,
        ]);

        OrderRevocation::create([
            'name' => 'Hannah Kundin',
            'email' => $customer->email,
            'order_number' => 'SF-REV-1002',
            'items' => 'Widerrufener Schlüsselanhänger',
            'status' => 'pending',
        ]);

        $this->actingAs($customer, 'customer');

        // Redirect /customer/reviews -> /reviews und HTTP 200 inkl. Navigation
        $this->get('/customer/reviews')->assertRedirect('/reviews');
        $this->get('/reviews')
            ->assertOk()
            ->assertSee('Meine Bewertungen')
            ->assertSee('Produktbewertungen')
            ->assertSee('Erlebnis-Feedback')
            ->assertSee('Edelstahl Armband Gold')
            ->assertDontSee('Widerrufener Schlüsselanhänger');

        // Tab 1: Öffnet die zentrale „Deine Erfahrung teilen“-Komponente im Kundenportal
        $comp = Livewire::actingAs($customer, 'customer')
            ->test(CustomerReviewsComponent::class)
            ->call('startCreateProductReview', $purchasedProduct->id)
            ->assertSee('Deine Erfahrung teilen')
            ->assertSee('Sterne-Bewertung')
            ->assertSee('5_funki_5_stars.webp')
            ->assertSee('Bilder & Video hinzufügen')
            ->assertSee('Anonym bewerten')
            ->assertSee('AGB (§ 8a)');

        // Validierung greift bei fehlender Einwilligung & zu kurzem Text (< 10 Zeichen)
        $comp->set('content', 'Zu kurz')
            ->set('acceptReviewTerms', false)
            ->call('submitReview')
            ->assertHasErrors(['content', 'acceptReviewTerms']);

        // Absenden ohne Medien + Anonym-Flag -> status = approved & sofortige Seelenfunken-Gutschrift
        $comp->set('rating', 5)
            ->set('title', 'Wunderschöne Gravur')
            ->set('content', 'Die Gravur ist gestochen scharf und edel verpackt.')
            ->set('isAnonymous', true)
            ->set('acceptReviewTerms', true)
            ->call('submitReview')
            ->assertHasNoErrors();

        $productReview = ProductReview::where('customer_id', $customer->id)
            ->where('product_id', $purchasedProduct->id)
            ->first();

        $this->assertNotNull($productReview);
        $this->assertSame('approved', $productReview->status);
        $this->assertTrue((bool) $productReview->is_anonymous);

        $gamificationAfterCreate = CustomerGamification::where('customer_id', $customer->id)->first();
        $this->assertNotNull($gamificationAfterCreate);
        $this->assertSame(GameConfig::REVIEW_SPARK_REWARD, (int) $gamificationAfterCreate->funken_balance);

        // Kunde bearbeitet Bewertung unter /reviews und lädt 2 Bilder hoch -> automatische WebP-Konvertierung & Status pending
        $uploadImg1 = UploadedFile::fake()->image('armband-1.png', 900, 700);
        $uploadImg2 = UploadedFile::fake()->image('armband-2.jpg', 800, 800);

        $comp->call('startEditProductReview', $productReview->id)
            ->assertSee('Deine Bewertung bearbeiten')
            ->set('rating', 4)
            ->set('title', 'Sehr schöne Gravur')
            ->set('content', 'Aktualisierter Text mit echten Fotos aus dem Kundenportal.')
            ->set('isAnonymous', false)
            ->set('newMedia', [$uploadImg1, $uploadImg2])
            ->call('submitReview')
            ->assertHasNoErrors();

        $freshReview = $productReview->fresh();
        $this->assertNotNull($freshReview);
        $this->assertSame('pending', $freshReview->status);
        $this->assertSame(4, (int) $freshReview->rating);
        $this->assertFalse((bool) $freshReview->is_anonymous);
        $this->assertIsArray($freshReview->media);
        $this->assertCount(2, $freshReview->media);

        $firstMediaPath = $freshReview->media[0];
        $secondMediaPath = $freshReview->media[1];
        $this->assertStringStartsWith('produkte/reviews/', $firstMediaPath);
        if (function_exists('imagewebp')) {
            $this->assertStringEndsWith('.webp', $firstMediaPath);
            $this->assertStringEndsWith('.webp', $secondMediaPath);
        }
        Storage::disk('public')->assertExists($firstMediaPath);
        Storage::disk('public')->assertExists($secondMediaPath);

        // Lightbox-Slider im selben Fenster wird gerendert (kein target="_blank" auf Medien)
        $comp->assertSee('openMediaLightbox');

        // Kunde entfernt im Bearbeitungsmodus das erste Bild -> Datei wird sauber von Disk gelöscht
        $comp->call('startEditProductReview', $productReview->id)
            ->call('removeExistingMedia', 0)
            ->call('submitReview')
            ->assertHasNoErrors();

        Storage::disk('public')->assertMissing($firstMediaPath);
        Storage::disk('public')->assertExists($secondMediaPath);
        $this->assertCount(1, $productReview->fresh()->media);

        // Kunde löscht Produktbewertung -> Datensatz + verbleibende Mediendatei gelöscht & Seelenfunken zurückgebucht
        $comp->call('deleteProductReview', $productReview->id);
        $this->assertDatabaseMissing('product_reviews', ['id' => $productReview->id]);
        Storage::disk('public')->assertMissing($secondMediaPath);

        $gamificationAfterDelete = CustomerGamification::where('customer_id', $customer->id)->first();
        $this->assertNotNull($gamificationAfterDelete);
        $this->assertSame(0, (int) $gamificationAfterDelete->funken_balance);

        // Tab 2: Erlebnis-Feedback inkl. Korrektur ohne Dublette
        $comp->call('switchTab', 'experience')
            ->assertSee('Produktkonfigurator')
            ->assertSee('3D-Atelier')
            ->assertSee('Angebots-Kalkulator')
            ->call('rateExperience', SupportExperienceReview::AREA_FUNKENREICH, 5)
            ->assertSee('✓ Gespeichert')
            ->assertDontSee('Was können wir verbessern?');

        $this->assertDatabaseHas('support_experience_reviews', [
            'customer_id' => $customer->id,
            'area' => SupportExperienceReview::AREA_FUNKENREICH,
            'rating' => 5,
            'status' => 'done',
        ]);

        // Verklicker-Korrektur auf 2 Sterne + Verbesserungshinweis speichern
        $comp->call('rateExperience', SupportExperienceReview::AREA_FUNKENREICH, 2)
            ->assertSee('Was können wir verbessern?')
            ->set('experienceComments.funkenreich', 'Auf meinem älteren Smartphone wäre ein Performance-Schalter hilfreich.')
            ->call('saveExperienceFeedback', SupportExperienceReview::AREA_FUNKENREICH)
            ->assertSee('✓ Gespeichert');

        $this->assertSame(1, SupportExperienceReview::where('customer_id', $customer->id)->where('area', 'funkenreich')->count());
        $this->assertDatabaseHas('support_experience_reviews', [
            'customer_id' => $customer->id,
            'area' => SupportExperienceReview::AREA_FUNKENREICH,
            'rating' => 2,
            'status' => 'open',
            'comment' => 'Auf meinem älteren Smartphone wäre ein Performance-Schalter hilfreich.',
        ]);
    }

    public function test_shared_single_source_of_truth_review_architecture_between_product_page_and_customer_portal(): void
    {
        $productPageTraits = class_uses_recursive(ProductReviews::class);
        $customerPortalTraits = class_uses_recursive(CustomerReviewsComponent::class);

        $this->assertArrayHasKey(ManagesCustomerProductReviewForm::class, $productPageTraits);
        $this->assertArrayHasKey(ManagesCustomerProductReviewForm::class, $customerPortalTraits);

        $productBlade = (string) file_get_contents(base_path('resources/views/livewire/shop/product/product-reviews.blade.php'));
        $portalBlade = (string) file_get_contents(base_path('resources/views/livewire/customer/customer-reviews-component.blade.php'));

        $this->assertStringContainsString('<x-shop.product-review-experience-form', $productBlade);
        $this->assertStringContainsString('<x-shop.product-review-experience-form', $portalBlade);

        // Light-/Darkmode-Disziplin prüfen: Produktseite bleibt 100 % Light-Mode, Kundenportal ist darkAware
        $customer = Customer::create([
            'first_name' => 'Lena',
            'last_name' => 'Licht',
            'email' => 'lena-dry@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
        ]);

        $product = Product::create([
            'name' => 'Kristallglas Herz',
            'slug' => 'kristallglas-herz-dry-test',
            'type' => 'physical',
            'status' => 'active',
            'price' => 4490,
            'quantity' => 0,
        ]);

        $order = OrderOrder::create([
            'order_number' => 'SF-DRY-2001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Lena', 'last_name' => 'Licht'],
            'shipping_address' => ['first_name' => 'Lena', 'last_name' => 'Licht'],
            'subtotal_price' => 4490,
            'tax_amount' => 717,
            'shipping_price' => 0,
            'total_price' => 4490,
        ]);

        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 4490,
            'total_price' => 4490,
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(ProductReviews::class, ['product' => $product])
            ->assertSee('Deine Erfahrung teilen')
            ->assertSee('data-product-review-experience-form="true"', false)
            ->assertDontSee('dark:bg-gray-900/80 p-6 sm:p-8 rounded-2xl', false);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerReviewsComponent::class)
            ->call('startCreateProductReview', $product->id)
            ->assertSee('Deine Erfahrung teilen')
            ->assertSee('data-product-review-experience-form="true"', false)
            ->assertSee('dark:bg-gray-900/80 p-6 sm:p-8 rounded-2xl', false);
    }

    public function test_admin_support_reviews_kpis_filters_toggle_and_strict_role_isolation(): void
    {
        // Navigationspunkt unter Shopverwaltung -> Support prüfen
        $structure = BackendNavigationService::getStructure();
        $supportGroup = collect($structure)
            ->pluck('items')
            ->flatten(1)
            ->firstWhere('id', 'support');

        $this->assertNotNull($supportGroup);
        $supportReviewNav = collect($supportGroup['children'])->firstWhere('id', 'support-reviews');
        $this->assertNotNull($supportReviewNav);
        $this->assertSame('Bewertungen', $supportReviewNav['title']);
        $this->assertSame('/admin/support/reviews', $supportReviewNav['route']);

        // Rollen-Isolation gegen Gast, Kunde, Influencer & Steuerberater
        Auth::logout();
        Livewire::test(SupportReviews::class)->assertStatus(403);

        $customerUnauth = Customer::create([
            'first_name' => 'Klara',
            'last_name' => 'Kunde',
            'email' => 'klara-unauth@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
        ]);
        Livewire::actingAs($customerUnauth, 'customer')
            ->test(SupportReviews::class)
            ->assertStatus(403);

        $influencer = Employee::create([
            'first_name' => 'Ina',
            'last_name' => 'Creator',
            'email' => 'ina-influencer@example.com',
            'role' => 'influencer',
            'email_verified_at' => now(),
            'password' => bcrypt('Secret123!'),
        ]);

        Livewire::actingAs($influencer, 'employee')
            ->test(SupportReviews::class)
            ->assertStatus(403);

        $taxAdvisor = Employee::create([
            'first_name' => 'Theo',
            'last_name' => 'Steuer',
            'email' => 'theo-tax@example.com',
            'role' => 'tax_advisor',
            'email_verified_at' => now(),
            'password' => bcrypt('Secret123!'),
        ]);

        Livewire::actingAs($taxAdvisor, 'employee')
            ->test(SupportReviews::class)
            ->assertStatus(403);

        // Testdaten für KPIs, Filter & Toggle anlegen
        $c1 = Customer::create([
            'first_name' => 'Markus',
            'last_name' => 'Tester',
            'email' => 'markus-kpi@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
        ]);
        $c2 = Customer::create([
            'first_name' => 'Julia',
            'last_name' => 'Sonne',
            'email' => 'julia-kpi@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
        ]);

        $openRev = SupportExperienceReview::upsertForCustomer(
            customer: $c1,
            area: SupportExperienceReview::AREA_CONFIGURATOR,
            rating: 2,
            comment: 'Schriftarten-Dropdown war auf Mobil etwas schmal.',
            commentProvided: true,
            deviceType: 'mobile'
        );

        SupportExperienceReview::upsertForCustomer(
            customer: $c2,
            area: SupportExperienceReview::AREA_CONFIGURATOR,
            rating: 5,
            comment: null,
            commentProvided: true,
            deviceType: 'desktop'
        );

        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-admin-reviews@example.com',
            'password' => bcrypt('Secret123!'),
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/support/reviews')
            ->assertOk()
            ->assertSee('Bewertungen')
            ->assertSee('Produktkonfigurator')
            ->assertSee('3D-Atelier')
            ->assertSee('Angebots-Kalkulator')
            ->assertSee('Offen (1-3 ★): 1');

        $adminComp = Livewire::actingAs($admin, 'admin')
            ->test(SupportReviews::class)
            ->assertSee('3,5') // Ø aus 2 und 5 im Konfigurator
            ->assertSee('Markus Tester')
            ->assertSee('Julia Sonne')
            ->set('filterStatus', 'open')
            ->assertSee('Markus Tester')
            ->assertDontSee('Julia Sonne')
            ->call('openDetails', $openRev->id)
            ->assertSee('Schriftarten-Dropdown war auf Mobil etwas schmal.')
            ->call('toggleStatus', $openRev->id);

        $this->assertSame('done', $openRev->fresh()->status);
        $this->assertNotNull($openRev->fresh()->resolved_at);

        // Wieder öffnen & Löschen prüfen
        $adminComp->set('filterStatus', 'all')
            ->call('toggleStatus', $openRev->id);
        $this->assertSame('open', $openRev->fresh()->status);

        $adminComp->call('deleteReview', $openRev->id);
        $this->assertDatabaseMissing('support_experience_reviews', ['id' => $openRev->id]);
    }

    public function test_master_analytics_dashboard_counts_open_experience_reviews_with_direct_link(): void
    {
        $c1 = Customer::create([
            'first_name' => 'Lukas',
            'last_name' => 'Feedback',
            'email' => 'lukas-dash@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
        ]);

        SupportExperienceReview::upsertForCustomer(
            customer: $c1,
            area: SupportExperienceReview::AREA_CALCULATOR,
            rating: 2,
            comment: 'Mengenstaffel gerne noch prominenter zeigen.',
            commentProvided: true,
            deviceType: 'desktop'
        );

        SupportExperienceReview::upsertForCustomer(
            customer: $c1,
            area: SupportExperienceReview::AREA_CONFIGURATOR,
            rating: 5,
            comment: null,
            commentProvided: true,
            deviceType: 'desktop'
        );

        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Master',
            'email' => 'alina-master-dash@example.com',
            'password' => bcrypt('Secret123!'),
        ]);

        $dash = Livewire::actingAs($admin, 'admin')
            ->test(MasterAnalytics::class)
            ->assertSee('/admin/support/reviews?filterStatus=open');

        $checks = $dash->get('healthChecks');
        $this->assertArrayHasKey('experience_reviews', $checks);
        $this->assertSame(1, $checks['experience_reviews']['count']);
        $this->assertSame('warning', $checks['experience_reviews']['status']);
    }

    public function test_zero_ai_dashes_and_system_blueprint_compliance(): void
    {
        $audit = app(SystemBlueprintService::class)->auditArchitecture();
        $this->assertTrue(
            $audit['is_fully_compliant'],
            'SystemBlueprint-Audit fehlgeschlagen: ' . implode(' | ', $audit['violations'] ?? [])
        );

        $checkedFiles = [
            base_path('database/migrations/2026_11_18_012500_create_support_experience_reviews_table.php'),
            base_path('app/Models/Support/SupportExperienceReview.php'),
            base_path('app/Http/Controllers/Customer/ExperienceReviewController.php'),
            base_path('app/Livewire/Traits/ManagesCustomerProductReviewForm.php'),
            base_path('resources/views/components/shop/product-review-experience-form.blade.php'),
            base_path('app/Livewire/Shop/Product/ProductReviews.php'),
            base_path('resources/views/livewire/shop/product/product-reviews.blade.php'),
            base_path('app/Livewire/Customer/CustomerReviewsComponent.php'),
            base_path('resources/views/livewire/customer/customer-reviews-component.blade.php'),
            base_path('resources/views/livewire/customer/gamification/game-global-stats-header.blade.php'),
            base_path('resources/views/livewire/auth/auth-profile-dropdown.blade.php'),
            base_path('app/Livewire/Shop/Support/SupportReviews.php'),
            base_path('resources/views/livewire/shop/support/support-reviews.blade.php'),
            base_path('app/Services/Navigation/BackendNavigationService.php'),
            base_path('app/Livewire/Shop/Master/MasterAnalytics.php'),
            base_path('resources/views/livewire/shop/master/master-analytics-partials/master_scores.blade.php'),
            base_path('routes/partials/customer_routes.php'),
            base_path('routes/partials/admin_routes.php'),
        ];

        foreach ($checkedFiles as $path) {
            $this->assertFileExists($path);
            $contents = (string) file_get_contents($path);
            $this->assertStringNotContainsString(
                " \u{2013} ",
                $contents,
                'KI-Gedankenstrich gefunden in: ' . $path
            );
            $this->assertStringNotContainsString(
                " \u{2014} ",
                $contents,
                'Geleerter Geviertstrich gefunden in: ' . $path
            );
        }
    }
}

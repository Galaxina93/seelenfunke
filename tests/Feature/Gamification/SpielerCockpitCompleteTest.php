<?php

namespace Tests\Feature\Gamification;

use App\Livewire\Customer\CustomerDashboardComponent;
use App\Livewire\Shop\System\SystemUserManagement;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SpielerCockpitCompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_funki_levels_1_to_10_have_2d_images_and_automatic_free_backgrounds(): void
    {
        $levels = GameConfig::getLevels();
        $this->assertCount(10, $levels);

        for ($lvl = 1; $lvl <= 10; $lvl++) {
            $this->assertArrayHasKey($lvl, $levels);
            $this->assertStringContainsString("funki_lvl_{$lvl}_", $levels[$lvl]['image']);
            $this->assertStringEndsWith('.png', $levels[$lvl]['image']);
            $this->assertNotEmpty($levels[$lvl]['background_image']);
            $this->assertNotEmpty($levels[$lvl]['background_title']);
            $this->assertEmpty($levels[$lvl]['model']); // No heavy 3D GLB models
        }
    }

    public function test_kaufland_loyalty_order_sparks_and_automatic_level_progression(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\OrderPlaced::class]);
        \Illuminate\Support\Facades\Queue::fake([\App\Jobs\ProcessOrderDocumentsAndMails::class]);

        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Julia',
            'last_name' => 'Käuferin',
            'email' => 'julia@example.com',
            'password' => bcrypt('password123'),
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Kristallglas Herz',
            'slug' => 'kristallglas-herz-' . Str::random(5),
            'price' => 3000,
            'status' => 'active',
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-LOYALTY-100',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal_price' => 6000,
            'tax_amount' => 958,
            'shipping_price' => 0,
            'total_price' => 6000, // 60,00 €
            'billing_address' => ['first_name' => 'Julia', 'last_name' => 'Käuferin'],
            'shipping_address' => ['first_name' => 'Julia', 'last_name' => 'Käuferin'],
        ]);

        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 5,
            'unit_price' => 3000,
            'total_price' => 15000,
        ]);

        $order->update([
            'subtotal_price' => 15000,
            'total_price' => 15000, // 150,00 €
        ]);

        // Variante A (100 % Produktwert): 150,00 € = 150 Seelenfunken!
        // 150 Lebenszeit-Funken => Funki steigt direkt auf Level 2 (ab 150 Funken)!
        $order->completePayment('pi_loyalty_100');

        $profile = CustomerGamification::where('customer_id', $customer->id)->first();
        $this->assertNotNull($profile);
        $this->assertEquals(150, $profile->funken_balance);
        $this->assertEquals(150, $profile->funken_total_earned);
        $this->assertEquals(2, $profile->funki_level);
        $this->assertEquals(2, $profile->level);
    }

    public function test_five_core_titles_and_level_10_unlock_seelengott_title(): void
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Aurelia',
            'last_name' => 'Göttin',
            'email' => 'seelengott@example.com',
            'password' => bcrypt('password123'),
        ]);

        $titles = GameConfig::getTitles();
        $this->assertCount(5, $titles);
        $this->assertCount(10, GameConfig::getLevelTitles());

        $profile = CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'level' => 10,
            'funki_level' => 10,
            'funken_balance' => 5500,
            'funken_total_earned' => 5500,
            'titles_progress' => [
                'sammler'       => 300,  // Max (Diamant >= 250)
                'schatzhueter'  => 10,
                'botschafter'   => 10,
                'freudebringer' => 5,
                'funkenkoenig'  => 5500, // Max (Diamant >= 5000)
            ],
        ]);

        // Level-Titel auswählbar (z.B. Level 10 = "Erhabener des Seelenlichts")
        $service = app(GamificationService::class);
        $resLvl = $service->selectCustomerTitle($customer, 'level_10');
        $this->assertTrue($resLvl['success']);
        $this->assertEquals('Erhabener des Seelenlichts', $resLvl['rank_name']);
    }

    public function test_dashboard_magie_aktivieren_and_voucher_redemption(): void
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Lena',
            'last_name' => 'Stern',
            'email' => 'lena@example.com',
            'password' => bcrypt('password123'),
        ]);

        $profile = CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => false,
            'level' => 3,
            'funki_level' => 3,
            'funken_balance' => 600,
            'funken_total_earned' => 600,
        ]);

        // Magie aktivieren im Dashboard
        Livewire::actingAs($customer, 'customer')
            ->test(CustomerDashboardComponent::class)
            ->set('acceptTerms', true)
            ->call('activateGamification')
            ->assertSet('hasActivatedGamification', true)
            ->call('redeemFunkenVoucher', 'voucher_10'); // 150 Funken -> 10% Rabatt

        $profile->refresh();
        $this->assertTrue((bool) $profile->is_active);
        $this->assertEquals(450, $profile->funken_balance);
        $this->assertEquals(600, $profile->funken_total_earned);
        $this->assertEquals(3, $profile->funki_level);
    }

    public function test_admin_can_adjust_customer_funki_level_and_sparks_in_user_management(): void
    {
        $admin = Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'Master',
            'email' => 'admin-loyalty@example.com',
            'password' => bcrypt('password123'),
        ]);

        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Kunde',
            'last_name' => 'Test',
            'email' => 'kunde-loyalty@example.com',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(SystemUserManagement::class)
            ->call('startEdit', $customer->id, 'customer')
            ->set('formData.funken_balance', 750)
            ->set('formData.funken_total_earned', 1500)
            ->set('formData.funki_level', 6)
            ->set('formData.gamification_active', true)
            ->call('saveInline');

        $profile = CustomerGamification::where('customer_id', $customer->id)->first();
        $this->assertNotNull($profile);
        $this->assertEquals(750, $profile->funken_balance);
        $this->assertEquals(1500, $profile->funken_total_earned);
        $this->assertEquals(6, $profile->funki_level);
        $this->assertTrue((bool) $profile->is_active);
    }
}

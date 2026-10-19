<?php

namespace Tests\Feature;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use App\Services\CartService;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\GameConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class GamificationMilestoneVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected function createCustomer(string $email = 'player@example.com'): Customer
    {
        return Customer::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'email' => $email,
            'password' => bcrypt('secret123'),
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
        ]);
    }

    protected function createProduct(): Product
    {
        return Product::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Graviertes Holzbild',
            'slug' => 'graviertes-holzbild-' . \Illuminate\Support\Str::random(5),
            'price' => 5000,
            'status' => 'active',
        ]);
    }

    public function test_milestone_voucher_is_created_with_high_entropy_and_bound_to_customer_email()
    {
        $customer = $this->createCustomer('gamer@example.com');
        $gameService = app(GamificationService::class);
        $profile = $gameService->getProfile($customer);

        // Funken balance hochsetzen für Level Up zu Level 3
        $profile->update([
            'level' => 2,
            'funken_balance' => 500,
        ]);

        $result = $gameService->upgradeLevel($customer);
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['new_level']);

        $profile->refresh();
        $unlocked = $profile->unlocked_coupons;
        $this->assertIsArray($unlocked);
        $this->assertArrayHasKey('3', $unlocked);

        $code = $unlocked['3'];
        $this->assertStringStartsWith('FUNKI-L3-', $code);
        // Code muss mindestens 17 Zeichen lang sein (FUNKI-L3- + 8 Zeichen Zufall)
        $this->assertGreaterThanOrEqual(17, strlen($code));

        $voucher = MarketingVoucher::where('code', $code)->first();
        $this->assertNotNull($voucher);
        $this->assertEquals('gamer@example.com', $voucher->recipient_email);
        $this->assertEquals('percent', $voucher->type);
        $this->assertEquals(5, $voucher->value);
        $this->assertEquals(1, $voucher->usage_limit);
        $this->assertEquals(0, $voucher->used_count);
        $this->assertTrue($voucher->is_active);
    }

    public function test_sync_unlocked_rewards_is_idempotent_and_does_not_create_duplicate_vouchers()
    {
        $customer = $this->createCustomer('sarah@example.com');
        $gameService = app(GamificationService::class);
        $profile = $gameService->getProfile($customer);

        $profile->update(['level' => 5]);

        // Einmal aufrufen
        $gameService->syncUnlockedRewards($profile);
        $profile->refresh();

        $vouchersCountLvl3 = MarketingVoucher::where('recipient_email', 'sarah@example.com')
            ->where('code', 'like', 'FUNKI-L3-%')
            ->count();
        $vouchersCountLvl5 = MarketingVoucher::where('recipient_email', 'sarah@example.com')
            ->where('code', 'like', 'FUNKI-L5-%')
            ->count();

        $this->assertEquals(1, $vouchersCountLvl3);
        $this->assertEquals(1, $vouchersCountLvl5);

        // Erneut aufrufen
        $gameService->syncUnlockedRewards($profile);
        $profile->refresh();

        $this->assertEquals(1, MarketingVoucher::where('recipient_email', 'sarah@example.com')->where('code', 'like', 'FUNKI-L3-%')->count());
        $this->assertEquals(1, MarketingVoucher::where('recipient_email', 'sarah@example.com')->where('code', 'like', 'FUNKI-L5-%')->count());
    }

    public function test_legitimate_customer_can_apply_milestone_voucher()
    {
        $customer = $this->createCustomer('anna@example.com');
        $gameService = app(GamificationService::class);
        $profile = $gameService->getProfile($customer);
        $profile->update(['level' => 3]);
        $gameService->syncUnlockedRewards($profile);
        $profile->refresh();

        $code = $profile->unlocked_coupons['3'];

        $product = $this->createProduct();

        Auth::guard('customer')->login($customer);
        $cartService = app(CartService::class);
        $cartService->addItem($product, 1);

        $res = $cartService->applyCoupon($code);
        $this->assertTrue($res['success']);
        $this->assertEquals('Gutschein erfolgreich eingelöst!', $res['message']);

        $totals = $cartService->getTotals();
        $this->assertEquals($code, $totals['coupon_code']);
        // 5% auf 50,00 € = 2,50 € Rabatt
        $this->assertEquals(250, $totals['discount_amount']);
    }

    public function test_unrelated_customer_cannot_apply_someone_elses_milestone_voucher()
    {
        $victim = $this->createCustomer('victim@example.com');
        $gameService = app(GamificationService::class);
        $victimProfile = $gameService->getProfile($victim);
        $victimProfile->update(['level' => 10]); // 15% Seelengott-Rabatt
        $gameService->syncUnlockedRewards($victimProfile);
        $victimProfile->refresh();

        $stolenCode = $victimProfile->unlocked_coupons['10'];

        $attacker = $this->createCustomer('attacker@example.com');
        Auth::guard('customer')->login($attacker);

        $product = $this->createProduct();
        $cartService = app(CartService::class);
        $cartService->addItem($product, 1);

        $res = $cartService->applyCoupon($stolenCode);
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('persönlich an das Kundenkonto', $res['message']);
    }

    public function test_personal_milestone_vouchers_are_excluded_from_public_voucher_slider()
    {
        $customer = $this->createCustomer('slider-test@example.com');
        $gameService = app(GamificationService::class);
        $profile = $gameService->getProfile($customer);
        $profile->update(['level' => 3]);
        $gameService->syncUnlockedRewards($profile);
        $profile->refresh();

        // Normaler manueller Shop-Gutschein
        MarketingVoucher::create([
            'title' => 'Öffentlicher Shop Rabatt',
            'code' => 'OFFEN10',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'mode' => 'manual',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $sliderComponent = new \App\Livewire\Shop\Marketing\MarketingVoucherSlider();
        $view = $sliderComponent->render();
        $vouchers = $view->getData()['vouchers'];

        $codes = $vouchers->pluck('code')->toArray();
        $this->assertContains('OFFEN10', $codes);

        $personalCode = $profile->unlocked_coupons['3'];
        $this->assertNotContains($personalCode, $codes);
    }

    public function test_milestone_voucher_is_marked_used_after_order()
    {
        $customer = $this->createCustomer('markus@example.com');
        $gameService = app(GamificationService::class);
        $profile = $gameService->getProfile($customer);
        $profile->update(['level' => 3]);
        $gameService->syncUnlockedRewards($profile);
        $profile->refresh();

        $code = $profile->unlocked_coupons['3'];
        $voucher = MarketingVoucher::where('code', $code)->first();
        $this->assertEquals(0, $voucher->used_count);

        // Bestellung mit dem Gutschein abschließen
        \Illuminate\Support\Facades\Event::fake([\App\Events\OrderPlaced::class]);
        \Illuminate\Support\Facades\Queue::fake([\App\Jobs\ProcessOrderDocumentsAndMails::class]);

        $order = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-TEST-GAME-01',
            'email' => 'markus@example.com',
            'coupon_code' => $code,
            'discount_amount' => 250,
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 4990,
            'billing_address' => ['first_name' => 'Markus', 'last_name' => 'Test'],
            'shipping_address' => ['first_name' => 'Markus', 'last_name' => 'Test'],
        ]);

        $order->completePayment('pi_test_123');

        $this->assertEquals(1, $voucher->fresh()->used_count);
        $this->assertFalse($voucher->fresh()->isValid());

        // In GameProfileComponent prüfen
        Auth::guard('customer')->login($customer);
        $component = new \App\Livewire\Customer\Gamification\GameProfileComponent();
        $component->loadGamificationData($gameService, $profile);

        $this->assertTrue($component->unlockedCoupons['lvl_3']['is_used']);
    }
}

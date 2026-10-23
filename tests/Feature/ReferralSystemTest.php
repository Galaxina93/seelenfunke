<?php

namespace Tests\Feature;

use App\Mail\ReferralRewardEarnedMail;
use App\Models\Marketing\MarketingGiftVoucher;
use App\Models\Marketing\MarketingReferral;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReferralSystemTest extends TestCase
{
    use RefreshDatabase;

    protected ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReferralService::class);
    }

    #[Test]
    public function it_generates_unique_readable_codes_for_same_first_names()
    {
        // 1. Erste Person namens Alina
        $ref1 = $this->service->getOrCreateReferralForCustomer('alina.stone@example.com', 'Alina', 'Stone');
        $this->assertEquals('ALINA10', $ref1->code);

        // 2. Zweite Person ebenfalls namens Alina (anderer Nachname)
        $ref2 = $this->service->getOrCreateReferralForCustomer('alina.mueller@example.com', 'Alina', 'Mueller');
        $this->assertEquals('ALINAM10', $ref2->code);

        // 3. Dritte Person namens Alina Mueller (identischer Vor- und Nachname)
        $ref3 = $this->service->getOrCreateReferralForCustomer('alina.mueller.2@example.com', 'Alina', 'Mueller');
        $this->assertEquals('ALINAMU10', $ref3->code);

        // 4. Vierte Person namens Alina Mueller
        $ref4 = $this->service->getOrCreateReferralForCustomer('alina.mueller.3@example.com', 'Alina', 'Mueller');
        $this->assertStringStartsWith('ALINA-', $ref4->code);

        // Alle 4 Codes müssen absolut eindeutig sein
        $codes = [$ref1->code, $ref2->code, $ref3->code, $ref4->code];
        $this->assertCount(4, array_unique($codes));

        // Alle 4 müssen als MarketingVoucher in der DB existieren
        foreach ($codes as $code) {
            $voucher = MarketingVoucher::where('code', $code)->first();
            $this->assertNotNull($voucher);
            $this->assertEquals(10, $voucher->value);
            $this->assertEquals('percent', $voucher->type);
            $this->assertTrue($voucher->is_active);
        }
    }

    #[Test]
    public function it_returns_same_code_for_returning_customer()
    {
        $ref1 = $this->service->getOrCreateReferralForCustomer('tim.test@example.com', 'Tim', 'Mustermann');
        $ref2 = $this->service->getOrCreateReferralForCustomer('tim.test@example.com', 'Tim', 'Mustermann');

        $this->assertEquals($ref1->id, $ref2->id);
        $this->assertEquals($ref1->code, $ref2->code);
        $this->assertEquals(1, MarketingReferral::where('referrer_email', 'tim.test@example.com')->count());
    }

    #[Test]
    public function it_prevents_self_referral()
    {
        $ref = $this->service->getOrCreateReferralForCustomer('werber@example.com', 'Sarah', 'Sommer');

        // Versuch, den eigenen Code für dieselbe E-Mail einzulösen
        $check = $this->service->validateReferralCode($ref->code, 'werber@example.com');

        $this->assertTrue($check['is_referral']);
        $this->assertFalse($check['valid']);
        $this->assertStringContainsString('eigenen Empfehlungscode', $check['message']);
    }

    #[Test]
    public function it_protects_against_existing_customers_using_referral()
    {
        $ref = $this->service->getOrCreateReferralForCustomer('werber@example.com', 'Sarah', 'Sommer');

        // Bestandskunde hat bereits eine bezahlte Bestellung
        OrderOrder::create([
            'order_number' => 'ORD-OLD-001',
            'email' => 'bestandskunde@example.com',
            'payment_status' => 'paid',
            'status' => 'completed',
            'subtotal_price' => 2000,
            'tax_amount' => 380,
            'shipping_price' => 490,
            'total_price' => 2490,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Kunde'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Kunde'],
        ]);

        $check = $this->service->validateReferralCode($ref->code, 'bestandskunde@example.com');

        $this->assertTrue($check['is_referral']);
        $this->assertFalse($check['valid']);
        $this->assertStringContainsString('Neukunden', $check['message']);
    }

    #[Test]
    public function it_allows_new_customers_to_use_referral()
    {
        $ref = $this->service->getOrCreateReferralForCustomer('werber@example.com', 'Sarah', 'Sommer');

        $check = $this->service->validateReferralCode($ref->code, 'echter.neukunde@example.com');

        $this->assertTrue($check['is_referral']);
        $this->assertTrue($check['valid']);
        $this->assertStringContainsString('10 %', $check['message']);
    }

    #[Test]
    public function it_rewards_referrer_with_5_euro_store_credit_upon_paid_order()
    {
        Mail::fake();

        $ref = $this->service->getOrCreateReferralForCustomer('werber@example.com', 'Sarah', 'Sommer');

        // Bestellung des geworbenen Neukunden
        $friendOrder = OrderOrder::create([
            'order_number' => 'ORD-FRIEND-001',
            'email' => 'neukunde@example.com',
            'coupon_code' => $ref->code,
            'discount_amount' => 500,
            'payment_status' => 'paid',
            'status' => 'pending',
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 4990,
            'billing_address' => ['first_name' => 'Neuer', 'last_name' => 'Freund'],
            'shipping_address' => ['first_name' => 'Neuer', 'last_name' => 'Freund'],
        ]);

        $rewardVoucher = $this->service->processOrderReferralReward($friendOrder);

        $this->assertNotNull($rewardVoucher);
        $this->assertStringStartsWith('DANKE-', $rewardVoucher->code);
        $this->assertEquals(500, $rewardVoucher->initial_value); // 5,00 €
        $this->assertEquals(500, $rewardVoucher->current_balance);
        $this->assertEquals('werber@example.com', $rewardVoucher->recipient_email);
        $this->assertTrue($rewardVoucher->is_active);

        // Referral Counter inkrementiert
        $this->assertEquals(1, $ref->fresh()->successful_referrals_count);

        // Belohnungs-Mail wurde gesendet
        Mail::assertSent(ReferralRewardEarnedMail::class, function ($mail) {
            return $mail->hasTo('werber@example.com');
        });

        // Zweiter Aufruf darf keine zweite Belohnung erzeugen (Idempotenz)
        $secondAttempt = $this->service->processOrderReferralReward($friendOrder);
        $this->assertNull($secondAttempt);
        $this->assertEquals(1, MarketingGiftVoucher::where('recipient_email', 'werber@example.com')->count());

        // Ein zweiter Freund bestellt -> Guthaben auf demselben Gutschein kumuliert auf 10,00 €!
        $secondFriendOrder = OrderOrder::create([
            'order_number' => 'ORD-2026-FREUND2',
            'email' => 'freund2@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'coupon_code' => $ref->code,
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 4990,
            'billing_address' => ['first_name' => 'Freund', 'last_name' => 'Zwei'],
            'shipping_address' => ['first_name' => 'Freund', 'last_name' => 'Zwei'],
        ]);

        $secondRewardVoucher = $this->service->processOrderReferralReward($secondFriendOrder);
        $this->assertNotNull($secondRewardVoucher);
        $this->assertEquals($rewardVoucher->code, $secondRewardVoucher->code); // Selber Gutschein!
        $this->assertEquals(1000, $secondRewardVoucher->current_balance); // 10,00 €
        $this->assertEquals(2, $ref->fresh()->successful_referrals_count);
        $this->assertEquals(1, MarketingGiftVoucher::where('recipient_email', 'werber@example.com')->count());
    }

    #[Test]
    public function it_renders_customer_dashboard_with_active_gift_vouchers_without_morph_exception()
    {
        $customer = \App\Models\Customer\Customer::create([
            'email' => 'alina.stone@t-online.de',
            'first_name' => 'Alina',
            'last_name' => 'Stone',
            'password' => bcrypt('secret123'),
        ]);

        MarketingGiftVoucher::create([
            'code' => 'DANKE-TEST12',
            'initial_value' => 500,
            'current_balance' => 500,
            'recipient_email' => 'alina.stone@t-online.de',
            'recipient_name' => 'Alina Stone',
            'delivery_method' => 'digital',
            'is_active' => true,
        ]);

        \Livewire\Livewire::actingAs($customer, 'customer')
            ->test(\App\Livewire\Customer\CustomerDashboardComponent::class)
            ->assertStatus(200)
            ->assertSee('DANKE-TEST12')
            ->assertSee('5,00 €')
            ->assertSee('Code kopieren');
    }

    #[Test]
    public function it_prevents_unauthorized_customers_from_using_someone_elses_danke_reward_voucher()
    {
        $alina = \App\Models\Customer\Customer::create([
            'email' => 'alina@example.com',
            'first_name' => 'Alina',
            'last_name' => 'Stone',
            'password' => bcrypt('secret123'),
        ]);

        $thief = \App\Models\Customer\Customer::create([
            'email' => 'thief@example.com',
            'first_name' => 'Bad',
            'last_name' => 'Actor',
            'password' => bcrypt('secret123'),
        ]);

        $voucher = MarketingGiftVoucher::create([
            'code' => 'DANKE-7K9M-4X2B',
            'initial_value' => 1500,
            'current_balance' => 1500,
            'recipient_email' => 'alina@example.com',
            'recipient_name' => 'Alina Stone',
            'delivery_method' => 'digital',
            'is_active' => true,
        ]);

        // 1. Fremder (Thief) versucht Alinas Gutschein einzulösen -> Verweigert!
        \Illuminate\Support\Facades\Auth::guard('customer')->login($thief);
        $cartService = app(\App\Services\CartService::class);
        $result = $cartService->applyCoupon('DANKE-7K9M-4X2B');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('gebunden', $result['message']);

        // 2. Alina selbst löst ihren Gutschein ein -> Erfolgreich!
        \Illuminate\Support\Facades\Auth::guard('customer')->login($alina);
        $resultAlina = $cartService->applyCoupon('DANKE-7K9M-4X2B');
        $this->assertTrue($resultAlina['success']);
    }

    #[Test]
    public function it_suppresses_referral_banner_and_cleans_cookie_for_referral_owner_or_existing_customer()
    {
        $alina = \App\Models\Customer\Customer::create([
            'email' => 'alina.stone@example.com',
            'first_name' => 'Alina',
            'last_name' => 'Stone',
            'password' => bcrypt('secret123'),
        ]);

        $ref = $this->service->getOrCreateReferralForCustomer($alina->email, 'Alina', 'Stone', $alina->id);
        $this->assertEquals('ALINA10', $ref->code);

        // Alina ist eingeloggt und hat ihren eigenen Code in Session/Cookie
        $response = $this->actingAs($alina, 'customer')
            ->withSession(['seelenfunke_coupon' => 'ALINA10'])
            ->get(route('shop'));

        $response->assertStatus(200);
        $response->assertDontSee('Dein 10 % Neukundenrabatt');
        $response->assertSessionMissing('seelenfunke_coupon');
    }

    #[Test]
    public function it_shows_referral_banner_with_id_to_new_guests()
    {
        $ref = $this->service->getOrCreateReferralForCustomer('creator@example.com', 'Creator', 'Person');

        // Neuer Besucher (Gast) ruft Shop mit dem Referral-Code auf
        $response = $this->withSession(['seelenfunke_coupon' => $ref->code])
            ->get(route('shop'));

        $response->assertStatus(200);
        $response->assertSee('id="referral-coupon-banner"', false);
        $response->assertSee('Dein <strong>10 % Neukundenrabatt (Code: ' . $ref->code . ')</strong>', false);
    }
}

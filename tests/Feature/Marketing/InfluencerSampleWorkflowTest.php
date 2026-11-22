<?php

namespace Tests\Feature\Marketing;

use App\Livewire\Employee\Influencer\InfluencerDashboard;
use App\Models\Marketing\MarketingInfluencerCommission;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Services\CartService;
use App\Services\Marketing\InfluencerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class InfluencerSampleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_influencer_pr_sample_workflow_with_linking_obligation_and_configurator_feedback(): void
    {
        $service = app(InfluencerService::class);

        // 1. Admin creates invitation slot -> sample_granted is true by default
        $slot = $service->createInvitationSlot([
            'invitation_label' => 'Test Creator @lena.style',
            'commission_value' => 15,
            'follower_discount_percent' => 10,
        ]);

        $this->assertTrue($slot->sample_granted);
        $rawToken = (string) $slot->invitation_token_encrypted;
        $rawCode = (string) $slot->invitation_code_encrypted;

        // 2. Creator completes self-registration with shipping address
        $influencer = $service->completeSelfRegistration($rawToken, $rawCode, [
            'first_name' => 'Lena',
            'last_name' => 'Meyer',
            'email' => 'lena.creator@example.com',
            'password' => 'SecretCreator!2026',
            'handle' => '@lena.style',
            'slug' => 'lena',
            'primary_channel' => 'instagram',
            'street' => 'Musterweg',
            'house_number' => '12',
            'postal' => '38518',
            'city' => 'Gifhorn',
            'country' => 'DE',
            'tax_status' => 'small_business',
            'tax_number' => '19/123/45678',
            'self_employed_confirmed' => true,
            'payout_method' => 'bank_transfer',
            'account_holder' => 'Lena Meyer',
            'iban' => 'DE89370400440532013000',
        ]);

        $this->assertNotNull($influencer);
        $this->assertSame('awaiting_terms', $influencer->sample_status);
        $this->assertFalse($influencer->canOrderSample());

        // 3. Creator logs in and unlocks the 100% PR-Sample by accepting the 14-day linking obligation
        Auth::guard('employee')->login($influencer->employee);

        Livewire::test(InfluencerDashboard::class)
            ->set('sampleTermsConfirmed', true)
            ->call('unlockSampleVoucher')
            ->assertHasNoErrors();

        $influencer->refresh();
        $this->assertNotNull($influencer->sample_terms_accepted_at);
        $this->assertNotNull($influencer->sampleVoucher);
        $this->assertTrue($influencer->sampleVoucher->isInfluencerSample());
        $this->assertStringStartsWith('SAMPLE-', $influencer->sampleVoucher->code);
        $this->assertSame('ready_to_order', $influencer->sample_status);
        $this->assertTrue($influencer->canOrderSample());

        // 4. Physical product is added to cart -> 1 unit + standard shipping = 0,00 €
        $product = Product::create([
            'name' => '3D Seelen-Kristall',
            'slug' => '3d-seelen-kristall',
            'sku' => 'SK-TEST-01',
            'type' => 'physical',
            'status' => 'active',
            'price' => 3990,
            'track_quantity' => true,
            'quantity' => 10,
            'weight' => 450,
        ]);

        $cartService = app(CartService::class);
        $cartService->emptyCart();
        $cartService->addItem($product, 1);

        $applyResult = $cartService->applyCoupon($influencer->sampleVoucher->code);
        $this->assertTrue($applyResult['success']);

        $totalsSingle = $cartService->getTotals('DE');
        $this->assertSame(3990, $totalsSingle['subtotal_gross']);
        $this->assertSame(0, $totalsSingle['total']);
        $this->assertSame($totalsSingle['subtotal_gross'] + $totalsSingle['shipping'], $totalsSingle['discount_amount']);

        // 5. If creator increases quantity to 2, only 1 unit + standard shipping is discounted
        $cart = $cartService->getCart();
        $firstItem = $cart->items()->first();
        $cartService->updateQuantity($firstItem->id, 2);

        $totalsDouble = $cartService->getTotals('DE');
        $this->assertSame(3990, $totalsDouble['total']);

        // Set back to 1 unit and complete the 0,00 € sample order
        $cartService->updateQuantity($firstItem->id, 1);
        $totalsFinal = $cartService->getTotals('DE');
        $this->assertSame(0, $totalsFinal['total']);

        $order = OrderOrder::create([
            'order_number' => 'SF-SAMPLE-1001',
            'email' => $influencer->employee->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'stripe',
            'billing_address' => [
                'first_name' => 'Lena',
                'last_name' => 'Meyer',
                'address' => 'Musterweg 12',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Lena',
                'last_name' => 'Meyer',
                'address' => 'Musterweg 12',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'coupon_code' => $influencer->sampleVoucher->code,
            'discount_amount' => $totalsFinal['discount_amount'],
            'subtotal_price' => $totalsFinal['subtotal_gross'],
            'tax_amount' => 0,
            'shipping_price' => $totalsFinal['shipping'],
            'express_price' => 0,
            'total_price' => 0,
        ]);

        $order->completePayment();

        $influencer->refresh();
        $this->assertSame($order->id, $influencer->sample_order_id);
        $this->assertTrue($order->fresh()->is_influencer_order);
        $this->assertFalse($influencer->sampleVoucher->fresh()->is_active);
        $this->assertSame(1, $influencer->sampleVoucher->fresh()->used_count);
        $this->assertSame('in_production', $influencer->sample_status);
        $this->assertSame(0, MarketingInfluencerCommission::count());

        // 6a. Clicking save with empty fields shows clear inline error
        Livewire::test(InfluencerDashboard::class)
            ->set('sampleConfiguratorRating', 5)
            ->set('sampleConfiguratorFeedback', '')
            ->set('samplePostUrl', '')
            ->call('saveSampleFeedbackAndPost')
            ->assertHasErrors(['samplePostUrl'])
            ->assertNotSet('sampleInlineError', null);

        // 6b. Clicking save with invalid URL shows clear inline error
        Livewire::test(InfluencerDashboard::class)
            ->set('sampleConfiguratorRating', 5)
            ->set('sampleConfiguratorFeedback', 'Super!')
            ->set('samplePostUrl', 'kein-gueltiger-link')
            ->call('saveSampleFeedbackAndPost')
            ->assertHasErrors(['samplePostUrl'])
            ->assertNotSet('sampleInlineError', null);

        // 6c. Creator submits 3D configurator feedback and valid post link
        Livewire::test(InfluencerDashboard::class)
            ->set('sampleConfiguratorRating', 5)
            ->set('sampleConfiguratorFeedback', '3D-Vorschau auf dem iPhone lief super flüssig!')
            ->set('samplePostUrl', 'https://instagram.com/p/seelenfunke-reel')
            ->call('saveSampleFeedbackAndPost')
            ->assertHasNoErrors()
            ->assertNotSet('sampleInlineSuccess', null);

        $influencer->refresh();
        $this->assertSame(5, $influencer->sample_configurator_rating);
        $this->assertSame('3D-Vorschau auf dem iPhone lief super flüssig!', $influencer->sample_configurator_feedback);
        $this->assertSame('https://instagram.com/p/seelenfunke-reel', $influencer->sample_post_url);
        $this->assertSame('post_submitted', $influencer->sample_status);

        // 7. Admin verifies the post and can grant a new sample anytime
        $service->verifySamplePostProof($influencer);
        $this->assertSame('post_verified', $influencer->fresh()->sample_status);

        $service->grantNewSampleToInfluencer($influencer);
        $influencer->refresh();
        $this->assertNull($influencer->sample_order_id);
        $this->assertSame('ready_to_order', $influencer->sample_status);
        $this->assertTrue($influencer->sampleVoucher->is_active);
        $this->assertSame(0, $influencer->sampleVoucher->used_count);

        // 8. Admin can edit and save the Influencer in /admin/marketing-influencers even after a Sample Order was placed with the same email
        $admin = \App\Models\Admin\Admin::first() ?: \App\Models\Admin\Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'admin-test@mein-seelenfunke.de',
            'password' => bcrypt('SecretPass123!'),
        ]);
        $this->actingAs($admin, 'admin');

        Livewire::test(\App\Livewire\Shop\Marketing\MarketingInfluencer::class)
            ->call('editInfluencer', $influencer->id)
            ->set('commissionValue', 18)
            ->call('saveInfluencer')
            ->assertHasNoErrors();

        $this->assertSame(18, (int) $influencer->fresh()->commission_value);
    }

    public function test_invitation_handle_live_typing_and_global_logs_subpage_resolution(): void
    {
        $service = app(InfluencerService::class);
        $inv = $service->createInvitationSlot([
            'invitation_label' => 'Test Creator',
            'primary_channel' => 'instagram',
            'commission_type' => 'percent',
            'commission_value' => 15,
            'follower_discount_percent' => 10,
            'cookie_days' => 30,
        ]);

        // Multi-tick debounce typing on handle updates slug & voucher code until manually edited
        $comp = Livewire::test(\App\Livewire\Frontend\Marketing\InfluencerInvitationAccept::class, ['token' => (string) $inv->invitation_token_encrypted])
            ->set('verificationCode', (string) $inv->invitation_code_encrypted)
            ->call('verifyCode')
            ->assertSet('step', 2)
            ->set('handle', '@la')
            ->assertSet('slug', 'la')
            ->assertSet('customVoucherCode', 'LA10')
            ->set('handle', '@lara.living')
            ->assertSet('slug', 'lara-living')
            ->assertSet('customVoucherCode', 'LARALIVING10');

        // Once slug is manually edited, further handle edits keep manual slug
        $comp->set('slug', 'lara-official')
            ->set('handle', '@lara.living.de')
            ->assertSet('slug', 'lara-official')
            ->assertSet('customVoucherCode', 'LARALIVINGDE10');

        // Verify SystemLog subpage & environment resolution for /admin/global-logs
        $log = \App\Models\System\SystemLog::create([
            'type' => 'marketing',
            'action_id' => 'influencer:save_sample_feedback_failed',
            'title' => '[STAGE] Creator-Cockpit Fehler',
            'message' => 'Test-Fehler im Creator-Cockpit',
            'status' => 'error',
            'payload' => [
                'subpage_path' => '/employee/influencer/dashboard',
                'influencer_handle' => '@lara.living',
            ],
        ]);

        $meta = $log->resolveAreaAndSubpageMeta();
        $this->assertSame('STAGE', $meta['env_label']);
        $this->assertSame('/employee/influencer/dashboard', $meta['subpage_path']);
        $this->assertSame('Creator Cockpit · Influencer Portal', $meta['subpage_label']);
    }
}

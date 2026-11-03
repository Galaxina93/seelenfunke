<?php

namespace Tests\Feature\Gamification;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GamificationServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected GamificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GamificationService::class);
    }

    public function test_add_funken_increments_balance_and_rejects_negative_amounts()
    {
        $customer = Customer::create([
            'id' => Str::uuid(),
            'email' => 'gamer@example.com',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'password' => bcrypt('secret123'),
        ]);

        $profile = $this->service->getProfile($customer);
        $this->assertFalse((bool) $profile->is_active);
        // Inactive profile must not receive sparks before Opt-In
        $this->service->addFunken($customer, 10, 'purchase');
        $profile->refresh();
        $this->assertEquals(0, $profile->funken_balance);

        // Activate Game Mode Opt-In
        $profile->update(['is_active' => true]);

        // 1. Valid positive addition
        $this->service->addFunken($customer, 10, 'purchase');
        $profile->refresh();
        $this->assertEquals(10, $profile->funken_balance);
        $this->assertEquals(10, $profile->funken_total_earned);

        // 2. Negative amount attempt must be rejected
        $this->service->addFunken($customer, -50, 'exploit');
        $profile->refresh();
        $this->assertEquals(10, $profile->funken_balance);
        $this->assertEquals(10, $profile->funken_total_earned);

        // 3. Zero amount attempt must be ignored
        $this->service->addFunken($customer, 0, 'zero');
        $profile->refresh();
        $this->assertEquals(10, $profile->funken_balance);
        $this->assertEquals(10, $profile->funken_total_earned);
    }

    public function test_website_hunt_respects_daily_limit()
    {
        $customer = Customer::create([
            'id' => Str::uuid(),
            'email' => 'hunter@example.com',
            'first_name' => 'Hunter',
            'last_name' => 'Test',
            'password' => bcrypt('secret123'),
        ]);

        $profile = $this->service->getProfile($customer);
        $profile->update(['is_active' => true]);

        // Collect up to DAILY_SPARK_LIMIT
        for ($i = 0; $i < GameConfig::DAILY_SPARK_LIMIT; $i++) {
            $this->service->addFunken($customer, 1, 'website_hunt');
        }

        $profile->refresh();
        $this->assertEquals(GameConfig::DAILY_SPARK_LIMIT, $profile->sparks_collected_today);
        $this->assertEquals(GameConfig::DAILY_SPARK_LIMIT, $profile->funken_balance);

        // Additional hunt attempts beyond daily limit must be rejected
        $this->service->addFunken($customer, 1, 'website_hunt');
        $profile->refresh();
        $this->assertEquals(GameConfig::DAILY_SPARK_LIMIT, $profile->sparks_collected_today);
        $this->assertEquals(GameConfig::DAILY_SPARK_LIMIT, $profile->funken_balance);
        $this->assertEquals(GameConfig::DAILY_SPARK_LIMIT, $profile->titles_progress['sammler'] ?? 0);
    }

    public function test_new_customer_starts_without_rank_until_title_is_unlocked_and_selected()
    {
        $customer = Customer::create([
            'id' => Str::uuid(),
            'email' => 'norank@example.com',
            'first_name' => 'No',
            'last_name' => 'Rank',
            'password' => bcrypt('secret123'),
        ]);

        $profile = $this->service->getProfile($customer);
        $this->assertNull($this->service->resolveActiveRankName($profile));

        // Unlock 'sammler' silber (req >= 50)
        $progress = $profile->titles_progress ?? [];
        $progress['sammler'] = 50;
        $profile->titles_progress = $progress;
        $profile->active_title = 'sammler';
        $profile->is_active = true;
        $profile->save();

        $this->assertEquals('Sucher', $this->service->resolveActiveRankName($profile->fresh()));
    }

    public function test_order_complete_payment_awards_seelenfunken_and_syncs_order_titles()
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\OrderPlaced::class]);
        \Illuminate\Support\Facades\Queue::fake([\App\Jobs\ProcessOrderDocumentsAndMails::class]);

        $customer = Customer::create([
            'id' => Str::uuid(),
            'email' => 'buyer-game@example.com',
            'first_name' => 'Buyer',
            'last_name' => 'Game',
            'password' => bcrypt('secret123'),
        ]);

        $profile = $this->service->getProfile($customer);
        $profile->update(['is_active' => true]);
        $this->assertEquals(0, $profile->funken_balance);

        $order = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-GAME-REWARD-01',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal_price' => 4000,
            'tax_amount' => 760,
            'shipping_price' => 0,
            'total_price' => 4000, // 40,00 € => 20 Funken
            'billing_address' => ['first_name' => 'Buyer', 'last_name' => 'Game', 'address' => 'Hauptstr. 1', 'postal_code' => '38518', 'city' => 'Gifhorn'],
            'shipping_address' => ['first_name' => 'Beschenkt', 'last_name' => 'Freund', 'address' => 'Geschenkweg 5', 'postal_code' => '10115', 'city' => 'Berlin'],
        ]);

        $order->completePayment('pi_game_reward_test');

        // Variante A (100 % vom bezahlten Produktwert): 40,00 € = 40 Seelenfunken
        $profile->refresh();
        $this->assertEquals(40, $profile->funken_balance);
        $this->assertEquals(40, $profile->funken_total_earned);
        $this->assertEquals(1, $profile->titles_progress['freudebringer'] ?? 0);

        // Equip 'freudebringer' (unlocked at Silber req >= 1)
        $profile->update(['active_title' => 'freudebringer']);
        $this->assertEquals('Aufmerksamer Freund', $this->service->resolveActiveRankName($profile->fresh()));

        // Cancel order -> sparks reversed idempotently & active_title reset to null since freudebringer drops to 0
        $prevStatus = $order->status;
        $prevPaymentStatus = $order->payment_status;
        $order->update(['status' => 'cancelled', 'payment_status' => 'refunded']);
        $order->handleCancellation('Test-Storno', $prevStatus, $prevPaymentStatus);
        // Second call must be a no-op (idempotent)
        $order->handleCancellation('Test-Storno Doppelaufruf', 'cancelled', 'refunded');

        $profile->refresh();
        $this->assertEquals(0, $profile->funken_balance);
        $this->assertEquals(0, $profile->funken_total_earned);
        $this->assertEquals(0, $profile->titles_progress['freudebringer'] ?? 0);
        $this->assertNull($profile->active_title);
        $this->assertNull($this->service->resolveActiveRankName($profile));
    }

    public function test_honest_product_review_awards_15_funken_and_prevents_delete_repost_farming()
    {
        $customer = Customer::create([
            'id' => Str::uuid(),
            'email' => 'reviewer@example.com',
            'first_name' => 'Elena',
            'last_name' => 'Stern',
            'password' => bcrypt('secret123'),
        ]);

        $profile = $this->service->getProfile($customer);
        $profile->update(['is_active' => true]);

        $product = \App\Models\Product\Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Seelenkristall Herz',
            'slug' => 'seelenkristall-herz-' . Str::random(5),
            'price' => 4990,
            'status' => 'active',
        ]);

        $review = \App\Models\Product\ProductReview::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'title' => 'Wunderschöne Gravur',
            'content' => 'Sehr liebevoll verarbeitet und schnell geliefert.',
            'status' => 'approved',
        ]);

        // 1. First reward grants +15 Seelenfunken
        $awarded = $this->service->rewardReviewFunken($review);
        $this->assertEquals(GameConfig::REVIEW_SPARK_REWARD, $awarded);

        $profile->refresh();
        $this->assertEquals(15, $profile->funken_balance);
        $this->assertEquals(15, $profile->funken_total_earned);

        // 2. Duplicate call / Admin re-approving an edited review for same product must return 0 (anti-farming)
        $review->update(['status' => 'pending']);
        $review->update(['status' => 'approved']);
        $awardedAgain = $this->service->rewardReviewFunken($review->fresh());
        $this->assertEquals(0, $awardedAgain);
        $this->assertEquals(15, $profile->fresh()->funken_balance);

        // 3. Deleting/rejecting the review when balance is still 15 reverses the 15 Funken
        $this->service->reverseReviewFunken($review);
        $profile->refresh();
        $this->assertEquals(0, $profile->funken_balance);
        $this->assertEquals(0, $profile->funken_total_earned);

        // 4. If customer gets +15 Funken, spends them (balance = 0), and then deletes/re-submits -> must NOT get +15 again!
        $this->assertEquals(15, $this->service->rewardReviewFunken($review->fresh()));
        $profile->refresh();
        $profile->update(['funken_balance' => 0]); // Customer spent the 15 sparks
        $this->service->reverseReviewFunken($review);
        $this->assertEquals(0, $this->service->rewardReviewFunken($review->fresh()));
        $this->assertEquals(0, $profile->fresh()->funken_balance);
    }

    public function test_ultimate_goal_title_is_seelengott_unter_sterblichen_and_hall_of_fame_works()
    {
        $customer = Customer::create([
            'id' => Str::uuid(),
            'email' => 'seelengott@example.com',
            'first_name' => 'Aurelius',
            'last_name' => 'Licht',
            'password' => bcrypt('secret123'),
        ]);

        $profile = $this->service->getProfile($customer);
        $profile->update([
            'is_active' => true,
            'funken_balance' => 5000,
            'funken_total_earned' => 5000,
            'level' => 10,
            'funki_level' => 10,
            'titles_progress' => [
                'funkenkoenig' => 5000,
                'schatzhueter' => 20,
                'sammler' => 500,
                'botschafter' => 15,
                'freudebringer' => 10,
            ],
        ]);

        // Pre-seed reviews & gift orders so syncDynamicTitles keeps all 5 diamond titles
        $productIds = [];
        for ($i = 1; $i <= 12; $i++) {
            $p = \App\Models\Product\Product::create([
                'id' => (string) Str::uuid(),
                'name' => 'Produkt ' . $i,
                'slug' => 'produkt-' . $i . '-' . Str::random(5),
                'price' => 10000,
                'status' => 'active',
            ]);
            $productIds[] = $p->id;

            \App\Models\Product\ProductReview::create([
                'product_id' => $p->id,
                'customer_id' => $customer->id,
                'rating' => 5,
                'title' => 'Top ' . $i,
                'content' => 'Super Qualität ' . $i,
                'status' => 'approved',
            ]);
        }

        for ($o = 1; $o <= 16; $o++) {
            $order = \App\Models\Order\OrderOrder::create([
                'order_number' => 'ORD-SG-' . $o . '-' . Str::random(4),
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'payment_status' => 'paid',
                'status' => 'completed',
                'subtotal_price' => 10000,
                'tax_amount' => 1900,
                'shipping_price' => 0,
                'total_price' => 10000,
                'billing_address' => ['first_name' => 'Aurelius', 'last_name' => 'Licht', 'street' => 'Hauptstr. 1', 'city' => 'Gifhorn'],
                'shipping_address' => ['first_name' => 'Beschenkt', 'last_name' => 'Freund', 'street' => 'Sternenweg 7', 'city' => 'Berlin'],
            ]);
            \App\Models\Order\OrderOrderItem::create([
                'order_id' => $order->id,
                'product_id' => $productIds[($o - 1) % count($productIds)],
                'product_name' => 'Produkt ' . $o,
                'quantity' => 1,
                'unit_price' => 10000,
                'total_price' => 10000,
            ]);
        }

        $res = $this->service->selectCustomerTitle($customer, 'seelengott');
        $this->assertTrue($res['success']);
        $this->assertEquals('Seelengott unter Sterblichen', $this->service->resolveActiveRankName($profile->fresh()));

        // Hall of Fame Opt-In with Spielername
        $hofRes = $this->service->updateHallOfFameSettings($customer, true, false, 'SternenKaiser');
        $this->assertTrue($hofRes['success']);

        $leaders = $this->service->getHallOfFameLeaders(10);
        $this->assertNotEmpty($leaders);
        $this->assertEquals('SternenKaiser', $leaders[0]['display_name']);
        $this->assertEquals('Seelengott unter Sterblichen', $leaders[0]['rank_name']);
        $this->assertNotEmpty($leaders[0]['video_url']);
    }

    public function test_admin_can_uncheck_customer_dsgvo_and_gamification_consents_in_user_management()
    {
        $admin = \App\Models\Admin\Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Admin',
            'email' => 'admin-dsgvo@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Clara',
            'last_name' => 'Kundin',
            'email' => 'clara-dsgvo@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $customer->profile()->firstOrCreate([])->update(['ai_data_consent' => true]);

        $profile = $this->service->getProfile($customer);
        $profile->update([
            'is_active' => true,
            'ranking_opt_in' => true,
            'use_real_name' => true,
            'player_name' => 'ClaraStar',
            'funken_balance' => 300,
            'funken_total_earned' => 300,
            'level' => 3,
            'funki_level' => 3,
        ]);

        $this->actingAs($admin, 'admin');

        \Livewire\Livewire::test(\App\Livewire\Shop\System\SystemUserManagement::class)
            ->call('startEdit', $customer->id, 'customer')
            ->assertSet('formData.gamification_active', true)
            ->assertSet('formData.ranking_opt_in', true)
            ->assertSet('formData.use_real_name', true)
            ->assertSet('formData.ai_data_consent', true)
            ->set('formData.gamification_active', false)
            ->set('formData.ranking_opt_in', false)
            ->set('formData.use_real_name', false)
            ->set('formData.ai_data_consent', false)
            ->call('saveInline')
            ->assertHasNoErrors();

        $profile->refresh();
        $customer->refresh();
        $this->assertFalse((bool) $profile->is_active);
        $this->assertFalse((bool) $profile->ranking_opt_in);
        $this->assertFalse((bool) $profile->use_real_name);
        $this->assertFalse((bool) $customer->profile->ai_data_consent);
    }

    public function test_player_name_validator_blocks_nazi_violence_hate_speech_duplicates_and_enforces_length()
    {
        $c1 = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Anna',
            'last_name' => 'Alpha',
            'email' => 'anna-name@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $c2 = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Ben',
            'last_name' => 'Beta',
            'email' => 'ben-name@example.com',
            'password' => bcrypt('secret123'),
        ]);

        // 1. Too short (< 3 chars) & too long (> 20 chars)
        $resShort = $this->service->updateHallOfFameSettings($c1, true, false, 'Ab');
        $this->assertFalse($resShort['success']);
        $this->assertStringContainsString('zwischen 3 und 20 Zeichen', $resShort['message']);

        $resLong = $this->service->updateHallOfFameSettings($c1, true, false, 'DieserNameIstVielZuLangFuerDasFeld123');
        $this->assertFalse($resLong['success']);
        $this->assertStringContainsString('zwischen 3 und 20 Zeichen', $resLong['message']);

        // 2. Nazi / extremist / violence / hate speech / system impersonation blocked
        foreach (['Hitler88', 'Hakenkreuz1', 'Amoklauf99', 'Hurensohn1', 'AdminSupport'] as $forbidden) {
            $resForbidden = $this->service->updateHallOfFameSettings($c1, true, false, $forbidden);
            $this->assertFalse($resForbidden['success'], "Expected '{$forbidden}' to be rejected");
        }

        // 3. Valid unique name succeeds
        $resValid = $this->service->updateHallOfFameSettings($c1, true, false, 'SternenHeld_99');
        $this->assertTrue($resValid['success']);
        $this->assertEquals('SternenHeld_99', $resValid['player_name']);

        // 4. Duplicate name (case-insensitive) for another customer is rejected
        $resDup = $this->service->updateHallOfFameSettings($c2, true, false, 'sternenheld_99');
        $this->assertFalse($resDup['success']);
        $this->assertStringContainsString('bereits an einen anderen Helden vergeben', $resDup['message']);
    }

    public function test_all_10_funki_levels_have_unique_backgrounds_and_level_10_is_gotteshalle_aus_purem_gold()
    {
        $levels = GameConfig::getFunkiLevels();
        $this->assertCount(10, $levels);

        $bgKeys = [];
        $bgNames = [];
        $videoUrls = [];
        $videoFileHashes = [];
        $circleCssStrings = [];

        foreach ($levels as $lvl => $cfg) {
            $this->assertNotContains($cfg['bg_key'], $bgKeys, "Duplicate bg_key on level {$lvl}");
            $this->assertNotContains($cfg['bg_name'], $bgNames, "Duplicate bg_name on level {$lvl}");
            $this->assertNotContains($cfg['circle_css'], $circleCssStrings, "Duplicate circle_css on level {$lvl}");
            $bgKeys[] = $cfg['bg_key'];
            $bgNames[] = $cfg['bg_name'];
            $circleCssStrings[] = $cfg['circle_css'];

            if (!empty($cfg['video_url'])) {
                $this->assertNotContains($cfg['video_url'], $videoUrls, "Duplicate video_url on level {$lvl}");
                $videoUrls[] = $cfg['video_url'];

                $cleanVideoPath = parse_url($cfg['video_url'], PHP_URL_PATH);
                $fullVideoPath = public_path(ltrim((string) $cleanVideoPath, '/'));
                $this->assertFileExists($fullVideoPath, "Missing video asset on disk for level {$lvl}: {$fullVideoPath}");

                $hash = md5_file($fullVideoPath);
                $this->assertNotContains($hash, $videoFileHashes, "Identical video binary content detected on level {$lvl}");
                $videoFileHashes[] = $hash;
            }
        }

        // Level 3 must be calm (no video) and have its own workshop dusk artwork
        $this->assertNull($levels[3]['video_url']);
        $this->assertEquals('Abendliche Sternen-Werkstatt', $levels[3]['bg_name']);
        $this->assertStringContainsString('bg_workshop_dusk.jpg', (string) $levels[3]['circle_css']);

        // Level 6 (Karmesin-Laserschmiede) and Level 7 (Eklipse der Schwarzen Sonne) must be completely distinct
        $this->assertEquals('Karmesin-Laserschmiede', $levels[6]['bg_name']);
        $this->assertStringContainsString('bg_crimson_forge.jpg', (string) $levels[6]['circle_css']);
        $this->assertEquals('Eklipse der Schwarzen Sonne', $levels[7]['bg_name']);
        $this->assertStringContainsString('bg_obsidian_abyss.jpg', (string) $levels[7]['circle_css']);

        // Level 10 must be the pure gold God Hall
        $this->assertEquals('Gotteshalle aus purem Gold', $levels[10]['bg_name']);
        $this->assertStringContainsString('bg_aurelia_sanctuary.mp4', (string) $levels[10]['video_url']);
    }

    public function test_audio_settings_persistence_and_hall_of_fame_pagination()
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Melody',
            'last_name' => 'Sound',
            'email' => 'melody@example.com',
            'password' => bcrypt('secret123'),
        ]);

        // Default audio settings
        $initialAudio = $this->service->getAudioSettings($this->service->getProfile($customer));
        $this->assertEquals(35, $initialAudio['volume']);
        $this->assertTrue($initialAudio['muted']);
        $this->assertFalse($initialAudio['keep_playing']);

        // Save custom volume, mute state & keep_playing = true
        $saved = $this->service->updateAudioSettings($customer, 70, true, true);
        $this->assertTrue($saved['success']);
        $this->assertEquals(70, $saved['volume']);
        $this->assertTrue($saved['muted']);
        $this->assertTrue($saved['keep_playing']);

        $reloaded = $this->service->getAudioSettings($this->service->getProfile($customer)->fresh());
        $this->assertEquals(70, $reloaded['volume']);
        $this->assertTrue($reloaded['muted']);
        $this->assertTrue($reloaded['keep_playing']);

        // Create 14 Hall of Fame customers and test pagination (10 per page)
        for ($i = 1; $i <= 14; $i++) {
            $c = Customer::create([
                'id' => (string) Str::uuid(),
                'first_name' => 'Hero' . $i,
                'last_name' => 'Tester',
                'email' => "hero{$i}@example.com",
                'password' => bcrypt('secret123'),
            ]);
            $p = $this->service->getProfile($c);
            $p->update([
                'is_active' => true,
                'ranking_opt_in' => true,
                'use_real_name' => false,
                'player_name' => 'HeldNummer' . $i,
                'funken_total_earned' => $i * 100,
                'funken_balance' => $i * 100,
                'level' => GameConfig::getLevelForLifetimeSparks($i * 100),
            ]);
        }

        $page1 = $this->service->getHallOfFamePaginated(1, 10);
        $this->assertEquals(14, $page1['total']);
        $this->assertEquals(2, $page1['last_page']);
        $this->assertCount(10, $page1['items']);
        $this->assertEquals(1, $page1['items'][0]['rank_position']);
        $this->assertEquals('HeldNummer14', $page1['items'][0]['display_name']);

        $page2 = $this->service->getHallOfFamePaginated(2, 10);
        $this->assertCount(4, $page2['items']);
        $this->assertEquals(11, $page2['items'][0]['rank_position']);
    }

    public function test_audio_settings_endpoint_and_locked_dashboard_properties_against_manipulation()
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Secure',
            'last_name' => 'Player',
            'email' => 'secure-player@example.com',
            'password' => bcrypt('secret123'),
        ]);

        // Unauthenticated POST to audio-settings must be rejected/redirected
        $unauthResp = $this->postJson('/customer/gamification/audio-settings', [
            'volume'       => 80,
            'muted'        => false,
            'keep_playing' => true,
        ]);
        $this->assertEquals(401, $unauthResp->status());

        // Authenticated POST saves volume, muted, and keep_playing cleanly
        $this->actingAs($customer, 'customer');
        $authResp = $this->postJson('/customer/gamification/audio-settings', [
            'volume'       => 65,
            'muted'        => false,
            'keep_playing' => true,
        ]);
        $authResp->assertOk()
            ->assertJson([
                'success'      => true,
                'volume'       => 65,
                'muted'        => false,
                'keep_playing' => true,
            ]);

        // Out-of-range volume (e.g. 999 or -20) is rejected by validation
        $invalidResp = $this->postJson('/customer/gamification/audio-settings', [
            'volume'       => 999,
            'muted'        => false,
            'keep_playing' => true,
        ]);
        $invalidResp->assertStatus(422);

        // Livewire CustomerDashboardComponent: Locked properties cannot be tampered with from client
        $component = \Livewire\Livewire::test(\App\Livewire\Customer\CustomerDashboardComponent::class)
            ->assertSet('funkiAudioVolume', 65)
            ->assertSet('funkiAudioMuted', false)
            ->assertSet('funkiAudioKeepPlaying', true)
            ->call('saveFunkiAudioSettings', 45, true, false)
            ->assertSet('funkiAudioVolume', 45)
            ->assertSet('funkiAudioMuted', true)
            ->assertSet('funkiAudioKeepPlaying', false);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        $component->set('funkenBalance', 999999);
    }

    public function test_birthday_log_based_correction_limit_annual_loyalty_sparks_mail_and_admin_profile_log()
    {
        \Illuminate\Support\Facades\Mail::fake();

        $customer = Customer::create([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Sarah',
            'last_name'  => 'Sonnenschein',
            'email'      => 'sarah-bday@example.com',
            'password'   => bcrypt('secret123'),
        ]);
        $customer->profile()->firstOrCreate([]);

        $gamification = $this->service->getProfile($customer);
        $gamification->update(['is_active' => true, 'funken_balance' => 40, 'funken_total_earned' => 40]);

        // 1. First birthday entry (null -> 1995-05-14): 0 corrections so far, can still correct once
        $res1 = $this->service->updateCustomerBirthday($customer, '1995-05-14');
        $this->assertTrue($res1['success']);
        $this->assertFalse($res1['was_correction']);
        $this->assertTrue($res1['can_change_again']);
        $this->assertEquals(0, $this->service->countCustomerBirthdayCorrections($customer));

        // 2. First correction (typo fix: 1995-05-14 -> 1995-10-31): allowed! Now 1 correction in log -> locked for further self-changes
        $todayMonthDay = now()->format('m-d');
        $correctedBirthday = '1995-' . $todayMonthDay;
        if ($correctedBirthday === '1995-05-14') {
            $correctedBirthday = '1994-05-14';
        }
        $res2 = $this->service->updateCustomerBirthday($customer, $correctedBirthday);
        $this->assertTrue($res2['success']);
        $this->assertTrue($res2['was_correction']);
        $this->assertFalse($res2['can_change_again']);
        $this->assertEquals(1, $this->service->countCustomerBirthdayCorrections($customer));

        // 3. Second correction attempt (1995-10-31 -> 1996-01-01): automatically blocked via customer log!
        $res3 = $this->service->updateCustomerBirthday($customer, '1996-01-01');
        $this->assertFalse($res3['success']);
        $this->assertStringContainsString('bereits einmal korrigiert', $res3['message']);

        // 4. Birthday bonus (+50 sparks) was awarded on today's birthday & corporate mail sent once per year
        $gamification->refresh();
        $this->assertEquals(90, (int) $gamification->funken_balance);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\GamificationLoyaltySparksMail::class, 1);

        // Calling processAnnualLoyaltySparksForCustomer again in the same year does NOT award twice
        $repeatCheck = $this->service->processAnnualLoyaltySparksForCustomer($customer, true);
        $this->assertFalse($repeatCheck['birthday_awarded']);
        $this->assertEquals(90, (int) $gamification->fresh()->funken_balance);

        // 5. Hall of Fame settings save shows immediate feedback in CustomerDashboardComponent & logs change
        $this->actingAs($customer, 'customer');
        \Livewire\Livewire::test(\App\Livewire\Customer\CustomerDashboardComponent::class)
            ->set('rankingOptIn', true)
            ->set('useRealName', false)
            ->set('playerName', 'SonnenFunke')
            ->call('saveHallOfFameSettings')
            ->assertSet('hofFeedbackSuccess', true)
            ->assertNotSet('hofFeedbackMessage', null);

        // 6. Admin User Management loads the customer's Profile & DSGVO Change Log
        $logs = $this->service->getUserProfileChangeLogs((string) $customer->id, $customer->email);
        $this->assertGreaterThanOrEqual(3, count($logs));
    }

    public function test_auth_profile_dropdown_birthday_correction_feedback_and_cronjob_monitoring(): void
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max-feedback@example.com',
            'password' => bcrypt('secret123'),
            'email_verified_at' => now(),
        ]);

        \App\Models\Customer\CustomerProfile::create([
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($customer, 'customer');

        // 1. Initial birthday save via AuthProfileDropdown shows feedback and allows 1 correction
        $component = \Livewire\Livewire::test(\App\Livewire\Auth\AuthProfileDropdown::class)
            ->set('birthday', '1990-03-10')
            ->call('saveProfile')
            ->assertHasNoErrors()
            ->assertSet('profileFeedbackSuccess', true)
            ->assertSet('canChangeBirthday', true);

        $this->assertNotEmpty($component->get('profileFeedbackMessage'));

        // 2. First correction via AuthProfileDropdown shows explicit save feedback and locks further changes
        $component->set('birthday', '1990-03-11')
            ->call('saveProfile')
            ->assertHasNoErrors()
            ->assertSet('profileFeedbackSuccess', true)
            ->assertSet('canChangeBirthday', false);

        $this->assertStringContainsString('einmalig korrigiert', (string) $component->get('profileFeedbackMessage'));

        // 3. Second correction attempt via AuthProfileDropdown shows visible error feedback instead of silent twitching
        $component->set('birthday', '1990-03-12')
            ->call('saveProfile')
            ->assertHasErrors(['birthday'])
            ->assertSet('profileFeedbackSuccess', false);

        $this->assertStringContainsString('bereits einmal korrigiert', (string) $component->get('profileFeedbackMessage'));

        // 4. Server Cronjobs table automatically includes gamification:award-loyalty-sparks
        \Database\Seeders\SystemCronjobSeeder::ensureDefaultCronjobsExist();
        $cronjob = \App\Models\System\SystemCronjob::where('command', 'gamification:award-loyalty-sparks')->first();
        $this->assertNotNull($cronjob);
        $this->assertEquals('15 8 * * *', $cronjob->schedule);

        // 5. Cronjob failure is immediately logged into SystemLog (/admin/global-logs)
        $createdLog = \App\Services\System\SystemErrorLogger::recordCommandFailure(
            'gamification:award-loyalty-sparks',
            1,
            'Simulierter Datenbank-Fehler im Treue-Cronjob'
        );

        $this->assertNotNull($createdLog);
        $errorLog = \App\Models\System\SystemLog::where('action_id', 'scheduler:cronjob_failed:gamification:award-loyalty-sparks')
            ->latest('started_at')
            ->first();

        $this->assertNotNull($errorLog);
        $this->assertEquals('error', $errorLog->status);
        $this->assertStringContainsString('Simulierter Datenbank-Fehler', (string) $errorLog->message);
    }

    public function test_public_profile_card_data_does_not_leak_customer_uuid_inactive_stats_or_pseudonym_photo(): void
    {
        $customer = Customer::create([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Elena',
            'last_name'  => 'Geheim',
            'email'      => 'elena-privacy@example.com',
            'password'   => bcrypt('secret123'),
        ]);

        \App\Models\Customer\CustomerProfile::create([
            'customer_id' => $customer->id,
            'photo_path'  => 'customers/photos/elena-real-face.jpg',
        ]);

        $profile = $this->service->getProfile($customer);
        $profile->update([
            'is_active'           => true,
            'use_real_name'       => false,
            'player_name'         => 'SternenWanderin',
            'funken_total_earned' => 450,
            'level'               => 3,
        ]);

        // 1. Active pseudonym mode: must NOT leak customer_id, player_name key, use_real_name key, or real photo URL
        $cardPseudonym = $this->service->getPublicProfileCardData($customer->fresh(['profile', 'gamification']), $profile->fresh());
        $this->assertArrayNotHasKey('customer_id', $cardPseudonym);
        $this->assertArrayNotHasKey('player_name', $cardPseudonym);
        $this->assertArrayNotHasKey('use_real_name', $cardPseudonym);
        $this->assertEquals('SternenWanderin', $cardPseudonym['display_name']);
        $this->assertNull($cardPseudonym['custom_photo_url']);

        // 2. Inactive gamification profile (is_active = false): must NOT expose earned sparks or level > 1
        $profile->update(['is_active' => false]);
        $cardInactive = $this->service->getPublicProfileCardData($customer->fresh(['profile', 'gamification']), $profile->fresh());
        $this->assertEquals('Elena G.', $cardInactive['display_name']);
        $this->assertEquals(1, $cardInactive['level']);
        $this->assertEquals(0, $cardInactive['funken_total_earned']);
    }
}

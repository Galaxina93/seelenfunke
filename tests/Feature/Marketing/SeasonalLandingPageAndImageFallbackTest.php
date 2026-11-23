<?php

namespace Tests\Feature\Marketing;

use App\Livewire\Shop\Marketing\MarketingLandingPages;
use App\Models\Admin\Admin;
use App\Models\Marketing\MarketingLandingPage;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SeasonalLandingPageAndImageFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_product_strictly_falls_back_to_neutral_main_image_immediately_after_christmas_when_winter_image_is_missing(): void
    {
        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Der Herzens-Kristall',
            'slug' => 'herzens-kristall-test',
            'type' => 'physical',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 3990,
            'status' => 'active',
            'media_gallery' => [
                [
                    'type' => 'image',
                    'path' => 'produkte/echtes-hauptbild-neutral.jpg',
                    'is_main' => true,
                    'is_ai_generated' => false,
                ],
            ],
            'configurator_settings' => [
                'auto_seasonal_main_image' => true,
                'seasonal_images' => [
                    'Weihnachten' => [
                        'path' => 'produkte/echtes-weihnachtsbild.jpg',
                        'is_ai_generated' => false,
                    ],
                ],
            ],
        ]);

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Weihnachts-Gravur',
            'holiday' => 'Weihnachten',
            'is_active' => true,
            'is_ai_generated' => false,
            'preview_image' => 'produkte/template-weihnachten.jpg',
            'configuration' => [],
        ]);

        // 1. Während der Weihnachtsphase (15.12. & 26.12.) -> Weihnachtsbild aktiv
        Carbon::setTestNow(Carbon::parse('2026-12-15 14:00:00'));
        $dec15Meta = $product->fresh(['templates'])->resolveMainImageMeta();
        $this->assertStringContainsString('(Weihnachten)', (string) $dec15Meta['label']);
        $this->assertFalse($dec15Meta['is_ai_generated']);
        $this->assertStringContainsString('echtes-weihnachtsbild.jpg', (string) $dec15Meta['path']);

        Carbon::setTestNow(Carbon::parse('2026-12-26 22:30:00'));
        $dec26Meta = $product->fresh(['templates'])->resolveMainImageMeta();
        $this->assertStringContainsString('(Weihnachten)', (string) $dec26Meta['label']);
        $this->assertStringContainsString('echtes-weihnachtsbild.jpg', (string) $dec26Meta['path']);

        // 2. Exakt nach Weihnachten (27.12. 00:05 Uhr, Winter-Bild fehlt noch!) -> SOFORT neutrales Hauptbild!
        foreach (['2026-12-27 00:05:00', '2027-01-12 10:00:00', '2027-07-15 10:00:00'] as $postHolidayTimestamp) {
            Carbon::setTestNow(Carbon::parse($postHolidayTimestamp));
            $fallbackMeta = $product->fresh(['templates'])->resolveMainImageMeta();

            $this->assertSame('Der Herzens-Kristall', $fallbackMeta['label'], "Unexpected holiday label at {$postHolidayTimestamp}");
            $this->assertStringContainsString('echtes-hauptbild-neutral.jpg', (string) $fallbackMeta['path'], "Failed neutral fallback at {$postHolidayTimestamp}");
            $this->assertStringNotContainsString('weihnacht', (string) $fallbackMeta['path']);
        }
    }

    public function test_product_reports_missing_active_season_or_holiday_image_and_switches_cleanly_once_uploaded(): void
    {
        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Ewigkeits-Anhänger',
            'slug' => 'ewigkeits-anhaenger-test',
            'type' => 'physical',
            'is_personalizable' => true,
            'price' => 3490,
            'status' => 'active',
            'media_gallery' => [
                [
                    'type' => 'image',
                    'path' => 'produkte/hauptbild-anhaenger.jpg',
                    'is_main' => true,
                    'is_ai_generated' => false,
                ],
            ],
            'configurator_settings' => [
                'auto_seasonal_main_image' => true,
                'seasonal_images' => [
                    'Weihnachten' => [
                        'path' => 'produkte/anhaenger-weihnachten.jpg',
                        'is_ai_generated' => false,
                    ],
                ],
            ],
        ]);

        // Am 27.12. ist Weihnachten vorbei -> Erwarteter Slot ist "Winter" (fehlt -> Fallback aktiv + Meldung am Produkt)
        Carbon::setTestNow(Carbon::parse('2026-12-27 09:00:00'));
        $statusDec27 = $product->getSeasonalImageSetStatus();
        $this->assertSame('Winter', $statusDec27['primary_expected_slot']);
        $this->assertSame('Winter', $statusDec27['active_missing_label']);
        $this->assertTrue($statusDec27['fallback_active']);
        $this->assertContains('Winter', $statusDec27['missing_labels']);
        $this->assertSame(1, $statusDec27['filled_count']);

        $readiness = $product->getReadinessReport();
        $shortMissingList = array_column($readiness['missing'], 'short_missing');
        $this->assertContains('Bild „Winter“ fehlt (1/12)', $shortMissingList);

        // Sobald Alina das echte Winter-Foto hochlädt, verschwindet der Fallback für Winter
        $settings = $product->configurator_settings;
        $settings['seasonal_images']['Winter'] = [
            'path' => 'produkte/anhaenger-winter.jpg',
            'is_ai_generated' => false,
        ];
        $product->update(['configurator_settings' => $settings]);
        $product->refresh();

        $statusAfterUpload = $product->getSeasonalImageSetStatus();
        $this->assertSame('Winter', $statusAfterUpload['primary_expected_slot']);
        $this->assertNull($statusAfterUpload['active_missing_label']);
        $this->assertFalse($statusAfterUpload['fallback_active']);

        $winterMeta = $product->resolveMainImageMeta();
        $this->assertStringContainsString('(Winter)', (string) $winterMeta['label']);
        $this->assertStringContainsString('anhaenger-winter.jpg', (string) $winterMeta['path']);

        // Vor Ostern 2027 (15.03.2027; Ostersonntag ist 28.03.2027) fehlt das Osterbild -> neutrales Hauptbild + Meldung "Ostern"
        Carbon::setTestNow(Carbon::parse('2027-03-15 10:00:00'));
        $easterMeta = $product->resolveMainImageMeta();
        $this->assertSame('Ewigkeits-Anhänger', $easterMeta['label']);
        $this->assertStringContainsString('hauptbild-anhaenger.jpg', (string) $easterMeta['path']);

        $easterStatus = $product->getSeasonalImageSetStatus();
        $this->assertSame('Ostern', $easterStatus['active_missing_label']);
        $this->assertTrue($easterStatus['fallback_active']);

        // Nach Ostern im April (05.04.2027) ohne Frühlingsbild -> neutrales Hauptbild + Meldung "Frühling"
        Carbon::setTestNow(Carbon::parse('2027-04-05 10:00:00'));
        $springMeta = $product->resolveMainImageMeta();
        $this->assertSame('Ewigkeits-Anhänger', $springMeta['label']);
        $this->assertStringContainsString('hauptbild-anhaenger.jpg', (string) $springMeta['path']);

        $springStatus = $product->getSeasonalImageSetStatus();
        $this->assertSame('Frühling', $springStatus['active_missing_label']);
        $this->assertTrue($springStatus['fallback_active']);
    }

    public function test_single_landing_page_manages_all_13_profiles_and_switches_automatically_by_calendar_and_cutoff(): void
    {
        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Der Seelen-Kristall',
            'slug' => 'seelen-kristall-multi',
            'type' => 'physical',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 4490,
            'status' => 'active',
            'attributes' => [
                'Material' => 'Optisches K9-Kristallglas',
            ],
        ]);

        $lp = MarketingLandingPage::syncForProduct($product, true);
        $this->assertNotNull($lp);
        $this->assertIsArray($lp->seasonal_profiles);
        $this->assertCount(12, $lp->seasonal_profiles);

        // Keine KI-Gedankenstriche (" – ") in irgendeinem der 13 Profile
        $this->assertStringNotContainsString(' – ', (string) $lp->title);
        $this->assertStringNotContainsString(' – ', (string) $lp->headline);
        $this->assertStringNotContainsString(' – ', (string) $lp->sales_copy);

        foreach (array_keys(Product::getAllSeasonalAndHolidaySlots()) as $slotKey) {
            $this->assertArrayHasKey($slotKey, $lp->seasonal_profiles);
            $entry = $lp->seasonal_profiles[$slotKey];
            $this->assertNotEmpty($entry['title']);
            $this->assertNotEmpty($entry['headline']);
            $this->assertNotEmpty($entry['sales_copy']);
            $this->assertStringNotContainsString(' – ', (string) $entry['title']);
            $this->assertStringNotContainsString(' – ', (string) $entry['headline']);
            $this->assertStringNotContainsString(' – ', (string) $entry['sales_copy']);
        }

        // 15.12.: Vor Versand-Cutoff -> Weihnachten-Profil + rechtzeitig vor dem Fest
        $dec15 = $lp->resolveActiveProfile(null, false, Carbon::parse('2026-12-15 12:00:00'));
        $this->assertSame('Weihnachten', $dec15['slot_key']);
        $this->assertTrue($dec15['is_before_cutoff']);
        $this->assertStringContainsString('Weihnachtsgeschenk', $dec15['title']);

        // 22.12.: Nach Versand-Cutoff (19.12.) -> Weiterhin Weihnachten, aber ehrlicher Post-Cutoff-Modus
        $dec22 = $lp->resolveActiveProfile(null, false, Carbon::parse('2026-12-22 12:00:00'));
        $this->assertSame('Weihnachten', $dec22['slot_key']);
        $this->assertFalse($dec22['is_before_cutoff']);

        // 27.12.: Weihnachten vorbei -> Sofortiger Wechsel auf "Winter"-Profil!
        $dec27 = $lp->resolveActiveProfile(null, false, Carbon::parse('2026-12-27 08:00:00'));
        $this->assertSame('Winter', $dec27['slot_key']);
        $this->assertStringNotContainsString('Weihnachtsgeschenk', $dec27['title']);
    }

    public function test_frontend_landing_page_renders_holiday_profile_and_enforces_post_holiday_guard_for_paid_ads(): void
    {
        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Schiefer-Herz',
            'slug' => 'schiefer-herz-lp-test',
            'type' => 'physical',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 2990,
            'status' => 'active',
            'media_gallery' => [
                [
                    'type' => 'image',
                    'path' => 'produkte/schiefer-hauptbild-neutral.jpg',
                    'is_main' => true,
                    'is_ai_generated' => false,
                ],
            ],
            'configurator_settings' => [
                'auto_seasonal_main_image' => true,
                'seasonal_images' => [
                    'Weihnachten' => [
                        'path' => 'produkte/schiefer-weihnachten-echt.jpg',
                        'is_ai_generated' => false,
                    ],
                ],
            ],
        ]);

        $lp = MarketingLandingPage::syncForProduct($product, true);

        // 1. Direkter Kampagnen-Aufruf für Muttertag im aktiven Muttertags-Zeitraum (Mai)
        Carbon::setTestNow(Carbon::parse('2026-05-08 12:00:00'));
        $mothersDayResponse = $this->get('/l/' . $lp->slug . '?holiday=muttertag');
        $mothersDayResponse->assertStatus(200);
        $mothersDayResponse->assertSee('Muttertag');
        $mothersDayResponse->assertSee('fruehling_muttertag.webp');
        $mothersDayResponse->assertSee('#C5A059');

        // 2. Google-Ads-Klick im November (Early-Bird vor Weihnachten) -> Weihnachtsprofil + Weihnachtsbild aktiv
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00'));
        $earlyBirdAdResponse = $this->get('/l/' . $lp->slug . '?holiday=weihnachten&gclid=EAIaIQobChMI_earlybird');
        $earlyBirdAdResponse->assertStatus(200);
        $earlyBirdAdResponse->assertSee('schiefer-weihnachten-echt.jpg');
        $earlyBirdAdResponse->assertSee('winter_weihnachten.webp');

        // 3. Veralteter Google-Ads-Klick am 28.12. nach Weihnachten -> Post-Holiday-Guard greift sofort!
        Carbon::setTestNow(Carbon::parse('2026-12-28 12:00:00'));
        $expiredAdProfile = $lp->resolveActiveProfile('weihnachten', true, Carbon::now());
        $this->assertTrue($expiredAdProfile['post_holiday_guard_triggered']);
        $this->assertSame('Winter', $expiredAdProfile['slot_key']);

        $expiredAdResponse = $this->get('/l/' . $lp->slug . '?holiday=weihnachten&gclid=EAIaIQobChMI_expired');
        $expiredAdResponse->assertStatus(200);
        $expiredAdResponse->assertSee('schiefer-hauptbild-neutral.jpg');
        $expiredAdResponse->assertDontSee('schiefer-weihnachten-echt.jpg');
    }

    public function test_admin_can_customize_individual_holiday_profiles_without_overwriting_standard_profile(): void
    {
        $admin = Admin::factory()->create();

        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Wärme-Becher',
            'slug' => 'waerme-becher-editor-test',
            'type' => 'physical',
            'is_personalizable' => true,
            'price' => 2490,
            'status' => 'active',
        ]);

        $lp = MarketingLandingPage::syncForProduct($product, true);
        $originalStandardHeadline = $lp->headline;

        Livewire::actingAs($admin, 'admin')
            ->test(MarketingLandingPages::class)
            ->call('openEditModal', $lp->id)
            ->assertSet('selectedProfileSlot', 'standard')
            ->call('selectProfileSlot', 'Weihnachten')
            ->assertSet('selectedProfileSlot', 'Weihnachten')
            ->set('editTitle', 'Wärme-Becher zu Weihnachten | Mein-Seelenfunke')
            ->set('editHeadline', 'Dein handveredelter Weihnachts-Becher aus Gifhorn')
            ->set('editSalesCopy', 'Individueller Weihnachtstext für die festliche Kaffeetafel.')
            ->set('editCtaText', 'Jetzt Weihnachts-Becher gestalten')
            ->call('saveCurrentProfileOnly')
            ->call('selectProfileSlot', 'Ostern')
            ->assertSet('selectedProfileSlot', 'Ostern')
            ->set('editHeadline', 'Dein persönlicher Oster-Becher fürs Osternest')
            ->call('saveEdit')
            ->assertSet('showEditModal', false);

        $lp->refresh();
        $this->assertSame($originalStandardHeadline, $lp->headline);
        $this->assertSame('Dein handveredelter Weihnachts-Becher aus Gifhorn', $lp->seasonal_profiles['Weihnachten']['headline']);
        $this->assertSame('Dein persönlicher Oster-Becher fürs Osternest', $lp->seasonal_profiles['Ostern']['headline']);

        $xmasView = $this->get('/l/' . $lp->slug . '?holiday=weihnachten');
        $xmasView->assertStatus(200);
        $xmasView->assertSee('Dein handveredelter Weihnachts-Becher aus Gifhorn');

        $easterView = $this->get('/l/' . $lp->slug . '?holiday=ostern');
        $easterView->assertStatus(200);
        $easterView->assertSee('Dein persönlicher Oster-Becher fürs Osternest');
    }
}

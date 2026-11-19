<?php

namespace Tests\Feature;

use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Livewire\Shop\Product\ProductConfiguratorSettings;
use App\Models\Admin\Admin;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use App\Services\ConfiguratorService;
use App\Services\ProductConfiguratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ConfiguratorSeasonalQuotesTest extends TestCase
{
    use RefreshDatabase;

    private string $manifestDiskPath;
    private ?string $manifestBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'shop_maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'season_auto_mode'], ['value' => 'true']);

        $this->manifestDiskPath = storage_path('app/public/' . ProductConfiguratorService::QUOTES_MANIFEST_REL_PATH);
        app(ProductConfiguratorService::class)->ensureQuotesInitialized();
        $this->manifestBackup = is_file($this->manifestDiskPath)
            ? (string) file_get_contents($this->manifestDiskPath)
            : null;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if ($this->manifestBackup !== null) {
            @file_put_contents($this->manifestDiskPath, $this->manifestBackup);
        }

        parent::tearDown();
    }

    public function test_zero_em_and_en_dashes_in_manifest_seeds_fallbacks_and_sanitizer(): void
    {
        $service = app(ProductConfiguratorService::class);
        $manifest = $service->ensureQuotesInitialized();

        $this->assertNotEmpty($manifest['items']);

        foreach ($manifest['items'] as $id => $item) {
            $text = (string) ($item['text'] ?? '');
            $title = (string) ($item['title'] ?? '');

            $this->assertStringNotContainsString("\u{2013}", $text, "En-Dash gefunden in Spruchtext [{$id}]");
            $this->assertStringNotContainsString("\u{2014}", $text, "Em-Dash gefunden in Spruchtext [{$id}]");
            $this->assertStringNotContainsString(' - ', $text, "Leerzeichen-Bindestrich gefunden in Spruchtext [{$id}]");
            $this->assertStringNotContainsString("\u{2013}", $title, "En-Dash gefunden in Titel [{$id}]");
            $this->assertStringNotContainsString("\u{2014}", $title, "Em-Dash gefunden in Titel [{$id}]");
        }

        foreach (ProductConfiguratorService::DEFAULT_QUOTE_CATEGORIES as $cat) {
            $generated = $service->generateAiQuotes($cat, '', 60, 5);
            foreach ($generated as $gen) {
                $this->assertStringNotContainsString("\u{2013}", $gen['text']);
                $this->assertStringNotContainsString("\u{2014}", $gen['text']);
                $this->assertStringNotContainsString(' - ', $gen['text']);
            }
        }

        // Gezielte Prüfung der intelligenten Gedankenstrich-Bereinigung in sanitizeQuoteText()
        $this->assertSame(
            "Du bist mein Lieblingsmensch,\nheute, morgen und für immer.",
            $service->sanitizeQuoteText("Du bist mein Lieblingsmensch \u{2013}\nheute, morgen und für immer.")
        );
        $this->assertSame(
            "Alles Liebe!\nDeine Familie",
            $service->sanitizeQuoteText("Alles Liebe! \u{2014}\nDeine Familie")
        );
        $this->assertSame(
            "Hand in Hand, für immer.",
            $service->sanitizeQuoteText("Hand in Hand \u{2013} für immer.")
        );
        $this->assertSame(
            "Psalm 23, 1-6",
            $service->sanitizeQuoteText("Psalm 23, 1\u{2013}6")
        );

        // Prüfe automatische Selbstheilung des Manifests inkl. Beibehaltung von archived = true
        $manifest['items']['seed_01']['archived'] = true;
        $manifest['items']['legacy_dirty'] = [
            'id' => 'legacy_dirty',
            'title' => "Alt \u{2013} Test",
            'text' => "Ein alter Spruch \u{2013}\nmit Gedankenstrich \u{2014} im Text.",
            'category' => 'Liebe & Partnerschaft',
            'archived' => false,
            'source' => 'manual',
            'created_at' => '2026-01-01 00:00:00',
        ];
        $service->saveQuotesManifest($manifest);

        $healed = $service->ensureQuotesInitialized();
        $this->assertTrue($healed['items']['seed_01']['archived']);
        $this->assertStringNotContainsString("\u{2013}", $healed['items']['legacy_dirty']['text']);
        $this->assertStringNotContainsString("\u{2014}", $healed['items']['legacy_dirty']['text']);
        $this->assertStringNotContainsString("\u{2013}", $healed['items']['legacy_dirty']['title']);
    }

    public function test_bible_quotes_category_contains_short_public_domain_verses_without_dashes(): void
    {
        $service = app(ProductConfiguratorService::class);
        $grouped = $service->getActiveQuotesGrouped();

        $this->assertContains('Bibelsprüche & Segen', ProductConfiguratorService::DEFAULT_QUOTE_CATEGORIES);
        $this->assertContains('Saison & Feiertage', ProductConfiguratorService::DEFAULT_QUOTE_CATEGORIES);
        $this->assertArrayHasKey('Bibelsprüche & Segen', $grouped);
        $this->assertArrayHasKey('Saison & Feiertage', $grouped);

        $bibleQuotes = $grouped['Bibelsprüche & Segen'];
        $this->assertGreaterThanOrEqual(10, count($bibleQuotes));

        foreach ($bibleQuotes as $quote) {
            $text = $quote['text'];
            $this->assertLessThanOrEqual(60, mb_strlen($text), "Bibelspruch zu lang für kleine Gravurflächen: {$text}");
            $this->assertGreaterThanOrEqual(22, mb_strlen($text));
            $this->assertLessThanOrEqual(1, substr_count($text, "\n"), "Bibelspruch sollte maximal 2 Zeilen haben: {$text}");
            $this->assertStringContainsString('(', $text);
            $this->assertStringContainsString(')', $text);
            $this->assertStringNotContainsString("\u{2013}", $text);
            $this->assertStringNotContainsString("\u{2014}", $text);
        }
    }

    public function test_calendar_engine_delivers_six_matching_seasonal_quotes_and_hides_foreign_holidays(): void
    {
        $cfgService = app(ConfiguratorService::class);

        $kristall = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 6990,
            'configurator_settings' => [
                'personalization_mode' => 'studio',
                'template_package' => 'seelen-kristall',
            ],
        ]);

        $seasonalChecks = [
            '2026-11-18' => 'Weihnachten',
            '2026-12-30' => 'Neujahr',
            '2026-02-05' => 'Valentinstag',
            '2026-03-28' => 'Ostern',
            '2026-05-04' => 'Muttertag',
        ];

        foreach ($seasonalChecks as $dateStr => $expectedHoliday) {
            $bundle = $cfgService->getStorefrontQuotesBundle($kristall, Carbon::parse($dateStr), 6);

            $this->assertCount(6, $bundle['recommended'], "Erwarte exakt 6 Vorschläge am {$dateStr}");
            $this->assertSame($expectedHoliday, $bundle['occasion_label'], "Falsches Anlass-Badge am {$dateStr}");
            $this->assertSame($bundle['total_count'] - 6, $bundle['hidden_count']);

            foreach ($bundle['recommended'] as $rec) {
                $this->assertSame(
                    $expectedHoliday,
                    $rec['holiday'],
                    "Spruch [{$rec['title']}] gehört nicht zu {$expectedHoliday} am {$dateStr}"
                );
            }

            // In der aufgeklappten Rubrik 'Saison & Feiertage' stehen die aktiven Feiertagssprüche zuerst
            $firstInSeasonTab = $bundle['grouped']['Saison & Feiertage'][0] ?? null;
            $this->assertNotNull($firstInSeasonTab);
            $this->assertSame($expectedHoliday, $firstInSeasonTab['holiday']);
        }

        // Reine Sommer-Phase ohne Feiertag (z. B. 20.06.2026): 0 fremde Feiertage in den 6 Direkt-Vorschlägen!
        $summerBundle = $cfgService->getStorefrontQuotesBundle($kristall, Carbon::parse('2026-06-20'), 6);
        $this->assertCount(6, $summerBundle['recommended']);
        $this->assertSame('Sommer', $summerBundle['occasion_label']);
        foreach ($summerBundle['recommended'] as $rec) {
            $this->assertNull($rec['holiday'], "Im Juni darf kein Feiertagsspruch ({$rec['holiday']}) empfohlen werden.");
            if ($rec['season'] !== null) {
                $this->assertSame('Sommer', $rec['season']);
            }
        }
    }

    public function test_product_context_adapts_recommendations_for_jewelry_memorial_pet_and_baptism(): void
    {
        $quoteService = app(ProductConfiguratorService::class);
        $cfgService = app(ConfiguratorService::class);

        // 1. Schmuck vs. Kristall: Füge einen langen (82 Zeichen) Weihnachtsspruch vorne ins Manifest ein
        $longXmas = $quoteService->addQuote(
            "Möge der helle Weihnachtsglanz euer gesamtes Haus mit tiefer Freude, Glück und Harmonie erfüllen.",
            'Saison & Feiertage',
            'Langer Festgruß',
            'manual',
            'Weihnachten',
            null
        );
        $this->assertGreaterThan(60, $longXmas['length']);

        $kristall = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 6990,
        ]);

        $jewelryBracelet = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Echtsilber Seelen-Armband Herz',
            'slug' => 'id-armband-silber-herz',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 5490,
        ]);

        $kristallBundle = $cfgService->getStorefrontQuotesBundle($kristall, Carbon::parse('2026-11-18'), 6);
        $jewelryBundle = $cfgService->getStorefrontQuotesBundle($jewelryBracelet, Carbon::parse('2026-11-18'), 6);

        $this->assertContains($longXmas['id'], array_column($kristallBundle['recommended'], 'id'));
        $this->assertNotContains($longXmas['id'], array_column($jewelryBundle['recommended'], 'id'));
        foreach ($jewelryBundle['recommended'] as $jQuote) {
            $this->assertLessThanOrEqual(48, $jQuote['length'], "Schmuck-Empfehlung sollte <= 48 Zeichen kurz sein: {$jQuote['text']}");
        }

        // 2. Trauer-Produkt mitten in der Weihnachtszeit (18.11.2026): Keine Weihnachtsparty, sondern Trauer & Bibelsprüche!
        $memorialProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Schiefer Gedenkstein & Erinnerungslicht',
            'slug' => 'gedenkstein-erinnerungslicht-trauer',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 3990,
        ]);

        $memorialBundle = $cfgService->getStorefrontQuotesBundle($memorialProduct, Carbon::parse('2026-11-18'), 6);
        $this->assertCount(6, $memorialBundle['recommended']);
        $this->assertSame('Trauer & Trost', $memorialBundle['occasion_label']);
        $memorialCategories = array_unique(array_column($memorialBundle['recommended'], 'category'));
        $this->assertContains('Trauer & Gedenken', $memorialCategories);
        $this->assertContains('Bibelsprüche & Segen', $memorialCategories);
        foreach ($memorialBundle['recommended'] as $mQuote) {
            $this->assertNull($mQuote['holiday']);
            $this->assertNotSame('Saison & Feiertage', $mQuote['category']);
        }

        // 3. Haustier- / Pfoten-Produkt: Integriert vorrangig Pfotensprüche
        $petProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Seelen-Pfote Hundemarke',
            'slug' => 'seelen-pfote-hundemarke',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 2490,
        ]);

        $petBundle = $cfgService->getStorefrontQuotesBundle($petProduct, Carbon::parse('2026-06-20'), 6);
        $this->assertCount(6, $petBundle['recommended']);
        $this->assertSame('Pfotenliebe', $petBundle['occasion_label']);
        $petCatCount = count(array_filter(
            $petBundle['recommended'],
            static fn (array $q): bool => $q['category'] === 'Haustiere & Pfotenliebe'
        ));
        $this->assertGreaterThanOrEqual(4, $petCatCount);

        // 4. Taufe-Produkt: Kombiniert Taufe & Geburt + Bibelsprüche & Segen
        $baptismProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Taufgeschenk Schutzengel zur Taufe',
            'slug' => 'taufgeschenk-schutzengel-taufe',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 4490,
        ]);

        $baptismBundle = $cfgService->getStorefrontQuotesBundle($baptismProduct, Carbon::parse('2026-06-20'), 6);
        $this->assertCount(6, $baptismBundle['recommended']);
        $this->assertSame('Taufe & Segen', $baptismBundle['occasion_label']);
        $baptismCategories = array_unique(array_column($baptismBundle['recommended'], 'category'));
        $this->assertContains('Taufe & Geburt', $baptismCategories);
        $this->assertContains('Bibelsprüche & Segen', $baptismCategories);
    }

    public function test_livewire_product_configurator_and_admin_settings_render_and_manage_seasonal_quotes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 12:00:00'));

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Goldenes Seelen-Herz',
            'slug' => 'goldenes-seelen-herz',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 3490,
            'configurator_settings' => [
                'personalization_mode' => 'studio',
                'allow_logo' => true,
                'template_package' => 'goldenes-seelen-herz',
            ],
        ]);

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $product,
            'context' => 'add',
        ]);

        $recommended = $component->get('recommendedQuotes');
        $totalCount = $component->get('totalQuotesCount');

        $this->assertCount(6, $recommended);
        $this->assertSame('Weihnachten', $component->get('quoteOccasionLabel'));
        $this->assertGreaterThan(100, $totalCount);

        $component
            ->assertSee('Sprüche & Widmungen')
            ->assertSee('Weihnachten')
            ->assertSee($recommended[0]['title'])
            ->assertSee('Alle Sprüche anzeigen (' . $totalCount . ')')
            ->assertSee('Nur passende Auswahl anzeigen (6)')
            ->assertDontSee('shadow-[0_0_15px_rgba(197,160,89,0.2)]');

        // Admin-Spruch-Manager testen (Anlegen + Bearbeiten mit Feiertag & Jahreszeit + Gedankenstrich-Bereinigung)
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'kontakt@mein-seelenfunke.de',
            'password' => bcrypt('secret123'),
        ]);

        $this->actingAs($admin, 'admin');

        $adminComp = Livewire::test(ProductConfiguratorSettings::class)
            ->set('newQuoteCategory', 'Saison & Feiertage')
            ->set('newQuoteTitle', 'Osterfunken')
            ->set('newQuoteHoliday', 'Ostern')
            ->set('newQuoteSeason', 'Frühling')
            ->set('newQuoteText', "Frohe Ostern \u{2013} für dich!")
            ->call('saveNewQuote')
            ->assertHasNoErrors();

        $adminData = app(ProductConfiguratorService::class)->getAdminQuotesData();
        $created = $adminData['quotes'][0];

        $this->assertSame('Osterfunken', $created['title']);
        $this->assertSame('Ostern', $created['holiday']);
        $this->assertSame('Frühling', $created['season']);
        $this->assertSame('Frohe Ostern, für dich!', $created['text']);

        $adminComp
            ->call('startEditQuote', $created['id'])
            ->assertSet('editQuoteHoliday', 'Ostern')
            ->assertSet('editQuoteSeason', 'Frühling')
            ->set('editQuoteHoliday', 'Muttertag')
            ->set('editQuoteSeason', '')
            ->set('editQuoteText', "Danke Mama \u{2014} von Herzen!")
            ->call('saveEditQuote')
            ->assertHasNoErrors();

        $updatedData = app(ProductConfiguratorService::class)->getAdminQuotesData();
        $updated = collect($updatedData['quotes'])->firstWhere('id', $created['id']);

        $this->assertNotNull($updated);
        $this->assertSame('Muttertag', $updated['holiday']);
        $this->assertNull($updated['season']);
        $this->assertSame('Danke Mama, von Herzen!', $updated['text']);
    }
}

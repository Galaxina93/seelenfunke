<?php

namespace Tests\Feature\Product;

use App\Livewire\Shop\Product\ProductCalculator\ProductCalculator;
use App\Livewire\Shop\Product\ProductFrontendFilterArea;
use App\Livewire\Shop\Product\ProductShow;
use App\Livewire\Shop\Product\ProductTemplates;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminProfile;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Models\System\SystemSetting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SeasonalProductTemplatesTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Erstellt ein aktives Studio-Produkt samt den 8 kanonischen Design-Vorlagen
     * (bewusst mit Ostern an erster Erstellposition, um die aktive Platz-1-Sortierung zu beweisen).
     *
     * @return array{product: Product, templates: array<string, ProductTemplate>}
     */
    private function createProductWithEightStandardTemplates(): array
    {
        $product = Product::create([
            'name' => 'Seelen Kristall Edition',
            'slug' => 'seelen-kristall-' . Str::lower(Str::random(6)),
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 4990,
            'short_description' => 'Optisch reines K9-Kristallglas mit feiner Lasergravur.',
            'description' => 'Handveredelt in Deutschland (Manufaktur Gifhorn).',
            'track_quantity' => true,
            'quantity' => 10,
            'continue_selling_when_out_of_stock' => true,
            'width' => 80,
            'height' => 80,
            'length' => 40,
            'configurator_settings' => [
                'personalization_mode' => 'studio',
            ],
        ]);

        $definitions = [
            'Ostern'       => 'Frohes Osterfest',
            'Halloween'    => 'Spooky Halloween',
            'Muttertag'    => 'Beste Mama',
            'Vatertag'     => 'Bester Papa',
            'Valentinstag' => 'Für meinen Lieblingsmenschen',
            'Geburtstag'   => 'Alles Gute zum Geburtstag',
            'Hochzeit'     => 'Für immer vereint',
            'Weihnachten'  => 'Frohe Weihnachten',
        ];

        $created = [];
        foreach ($definitions as $holiday => $title) {
            $created[$holiday] = ProductTemplate::create([
                'product_id' => $product->id,
                'name' => $title,
                'holiday' => $holiday,
                'is_active' => true,
                'is_ai_generated' => true,
                'configuration' => [
                    'text' => $title,
                    'texts' => [
                        [
                            'id' => (string) Str::uuid(),
                            'text' => $title,
                            'font' => 'Dancing Script',
                            'align' => 'center',
                            'x' => 50,
                            'y' => 55,
                            'size' => 1.2,
                            'rotation' => 0,
                        ],
                    ],
                ],
            ]);
        }

        return [
            'product' => $product,
            'templates' => $created,
        ];
    }

    public function test_november_18_shows_christmas_first_plus_evergreens_and_toggles_all_eight_templates(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 12, 0, 0));

        ['product' => $product] = $this->createProductWithEightStandardTemplates();

        $component = Livewire::test(ProductShow::class, ['product' => $product])
            ->call('openTemplatesList')
            ->assertSet('showTemplatesList', true)
            ->assertSet('showAllSeasonalTemplates', false)
            ->assertSet('hiddenSeasonalTemplatesCount', 5)
            ->assertSee('Alle Vorlagen anzeigen (8)')
            ->assertSee('Frohe Weihnachten')
            ->assertSee('Alles Gute zum Geburtstag')
            ->assertSee('Für immer vereint')
            ->assertDontSee('Frohes Osterfest')
            ->assertDontSee('Spooky Halloween')
            ->assertDontSee('Beste Mama');

        $visibleTemplates = $component->get('productTemplates');
        $this->assertCount(3, $visibleTemplates);
        $this->assertSame('Weihnachten', $visibleTemplates[0]['holiday']);

        $visibleHolidays = array_column($visibleTemplates, 'holiday');
        $this->assertContains('Geburtstag', $visibleHolidays);
        $this->assertContains('Hochzeit', $visibleHolidays);
        $this->assertNotContains('Ostern', $visibleHolidays);
        $this->assertNotContains('Halloween', $visibleHolidays);
        $this->assertNotContains('Muttertag', $visibleHolidays);
        $this->assertNotContains('Vatertag', $visibleHolidays);
        $this->assertNotContains('Valentinstag', $visibleHolidays);

        // Klick auf "Alle Vorlagen anzeigen" blendet alle 8 Vorlagen ein (Weihnachten bleibt auf Platz 1)
        $component->call('toggleShowAllTemplates')
            ->assertSet('showAllSeasonalTemplates', true)
            ->assertSet('hiddenSeasonalTemplatesCount', 0)
            ->assertSee('Nur aktuelle Saison anzeigen')
            ->assertSee('Frohes Osterfest')
            ->assertSee('Spooky Halloween')
            ->assertSee('Beste Mama');

        $expandedTemplates = $component->get('productTemplates');
        $this->assertCount(8, $expandedTemplates);
        $this->assertSame('Weihnachten', $expandedTemplates[0]['holiday']);

        // Erneuter Klick blendet außer-saisonale Vorlagen wieder sauber auf 3 zurück
        $component->call('toggleShowAllTemplates')
            ->assertSet('showAllSeasonalTemplates', false)
            ->assertSet('hiddenSeasonalTemplatesCount', 5)
            ->assertSee('Alle Vorlagen anzeigen (8)')
            ->assertDontSee('Frohes Osterfest');

        $this->assertCount(3, $component->get('productTemplates'));
    }

    public function test_july_15_midsummer_hides_christmas_and_easter_and_keeps_evergreens_visible(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 15, 14, 0, 0));

        ['product' => $product] = $this->createProductWithEightStandardTemplates();

        $component = Livewire::test(ProductShow::class, ['product' => $product])
            ->call('openTemplatesList')
            ->assertSet('hiddenSeasonalTemplatesCount', 6)
            ->assertSee('Alles Gute zum Geburtstag')
            ->assertSee('Für immer vereint')
            ->assertSee('Alle Vorlagen anzeigen (8)')
            ->assertDontSee('Frohe Weihnachten')
            ->assertDontSee('Frohes Osterfest');

        $visibleHolidays = array_column($component->get('productTemplates'), 'holiday');
        $this->assertCount(2, $visibleHolidays);
        $this->assertContains('Geburtstag', $visibleHolidays);
        $this->assertContains('Hochzeit', $visibleHolidays);
        $this->assertNotContains('Weihnachten', $visibleHolidays);
        $this->assertNotContains('Ostern', $visibleHolidays);

        // Einschulung ist im Spätsommer (15.07. bis 30.09.) saisonal aktiv, im November jedoch ausgeblendet
        $this->assertTrue(ProductTemplate::isHolidaySlotCurrentlyRelevant('Einschulung', Carbon::create(2026, 7, 15, 10, 0, 0)));
        $this->assertFalse(ProductTemplate::isHolidaySlotCurrentlyRelevant('Einschulung', Carbon::create(2026, 11, 18, 10, 0, 0)));

        // Silvester / Neujahr ist zwischen 27.12. und 08.01. aktiv, Weihnachten ab 27.12. sofort aus
        $this->assertTrue(ProductTemplate::isHolidaySlotCurrentlyRelevant('Silvester', Carbon::create(2026, 12, 28, 10, 0, 0)));
        $this->assertFalse(ProductTemplate::isHolidaySlotCurrentlyRelevant('Weihnachten', Carbon::create(2026, 12, 28, 10, 0, 0)));
    }

    public function test_easter_valentines_and_mothers_day_automatically_rank_first_before_evergreens(): void
    {
        ['product' => $product] = $this->createProductWithEightStandardTemplates();

        // 1) Vor Valentinstag (01. Februar 2026) -> Valentinstag auf Platz 1
        Carbon::setTestNow(Carbon::create(2026, 2, 1, 10, 0, 0));
        $febComp = Livewire::test(ProductShow::class, ['product' => $product]);
        $febList = $febComp->get('productTemplates');
        $this->assertSame('Valentinstag', $febList[0]['holiday']);
        $this->assertContains('Geburtstag', array_column($febList, 'holiday'));
        $this->assertContains('Hochzeit', array_column($febList, 'holiday'));
        $this->assertNotContains('Weihnachten', array_column($febList, 'holiday'));
        $this->assertNotContains('Ostern', array_column($febList, 'holiday'));

        // 2) Vor Ostern (25. März 2026, Ostersonntag ist 05.04.2026) -> Ostern auf Platz 1
        Carbon::setTestNow(Carbon::create(2026, 3, 25, 10, 0, 0));
        $marComp = Livewire::test(ProductShow::class, ['product' => $product]);
        $marList = $marComp->get('productTemplates');
        $this->assertSame('Ostern', $marList[0]['holiday']);
        $this->assertContains('Geburtstag', array_column($marList, 'holiday'));
        $this->assertContains('Hochzeit', array_column($marList, 'holiday'));
        $this->assertNotContains('Weihnachten', array_column($marList, 'holiday'));
        $this->assertNotContains('Valentinstag', array_column($marList, 'holiday'));

        // 3) Vor Muttertag (01. Mai 2026, Muttertag ist 10.05.2026) -> Muttertag auf Platz 1
        Carbon::setTestNow(Carbon::create(2026, 5, 1, 10, 0, 0));
        $mayComp = Livewire::test(ProductShow::class, ['product' => $product]);
        $mayList = $mayComp->get('productTemplates');
        $this->assertSame('Muttertag', $mayList[0]['holiday']);
        $mayHolidays = array_column($mayList, 'holiday');
        $mothersIdx = array_search('Muttertag', $mayHolidays, true);
        $birthdayIdx = array_search('Geburtstag', $mayHolidays, true);
        $weddingIdx = array_search('Hochzeit', $mayHolidays, true);
        $this->assertSame(0, $mothersIdx);
        $this->assertGreaterThan($mothersIdx, $birthdayIdx);
        $this->assertGreaterThan($mothersIdx, $weddingIdx);
    }

    public function test_deep_link_query_param_loads_out_of_season_easter_template_in_november(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 12, 0, 0));

        [
            'product' => $product,
            'templates' => $templates,
        ] = $this->createProductWithEightStandardTemplates();

        $easterTemplate = $templates['Ostern'];

        $component = Livewire::withQueryParams(['t' => $easterTemplate->id])
            ->test(ProductShow::class, ['product' => $product])
            ->assertSet('showTemplateSelection', false)
            ->assertSet('showTemplatesList', false);

        $loadedConfig = $component->get('currentConfig');
        $this->assertSame('Ostern', $loadedConfig['holiday'] ?? null);
        $this->assertSame('Frohes Osterfest', $loadedConfig['template_name'] ?? null);

        // Auch der direkte HTTP-Aufruf ?t=<id> liefert 200 OK ohne 404
        $this->get(route('product.show', $product->slug) . '?t=' . $easterTemplate->id)
            ->assertStatus(200);
    }

    public function test_shop_holiday_filter_calculator_and_admin_badges_stay_synchronized_without_en_dashes(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 12, 0, 0));

        ['product' => $product] = $this->createProductWithEightStandardTemplates();

        // 1) /shop Anlassfilter zeigt im November Weihnachten, Geburtstag & Hochzeit, aber nicht Ostern oder Muttertag
        $filterComp = Livewire::test(ProductFrontendFilterArea::class);
        $availableHolidays = $filterComp->viewData('availableHolidays');

        $this->assertArrayHasKey('Weihnachten', $availableHolidays);
        $this->assertArrayHasKey('Geburtstag', $availableHolidays);
        $this->assertArrayHasKey('Hochzeit', $availableHolidays);
        $this->assertArrayNotHasKey('Ostern', $availableHolidays);
        $this->assertArrayNotHasKey('Muttertag', $availableHolidays);
        $this->assertSame('Weihnachten', array_key_first($availableHolidays));

        // Ein explizit per URL gesetzter Filter (?filterHoliday=Ostern) bleibt erhalten
        $explicitFilterComp = Livewire::withQueryParams(['filterHoliday' => 'Ostern'])
            ->test(ProductFrontendFilterArea::class)
            ->assertSet('filterHoliday', 'Ostern');
        $this->assertArrayHasKey('Ostern', $explicitFilterComp->viewData('availableHolidays'));

        // 2) Angebots-Kalkulator nutzt identische Kuratierung & Toggle-Link
        $calcComp = Livewire::test(ProductCalculator::class)
            ->set('agb_accepted', true)
            ->call('startCalculator')
            ->call('openConfig', $product->id)
            ->call('openTemplatesList')
            ->assertSet('hiddenSeasonalTemplatesCount', 5)
            ->assertSee('Alle Vorlagen anzeigen (8)')
            ->assertSee('Frohe Weihnachten')
            ->assertDontSee('Frohes Osterfest');

        $this->assertCount(3, $calcComp->get('productTemplates'));
        $this->assertSame('Weihnachten', $calcComp->get('productTemplates')[0]['holiday']);

        $calcComp->call('toggleShowAllTemplates')
            ->assertSet('showAllSeasonalTemplates', true)
            ->assertSet('hiddenSeasonalTemplatesCount', 0)
            ->assertSee('Nur aktuelle Saison anzeigen')
            ->assertSee('Frohes Osterfest');

        $this->assertCount(8, $calcComp->get('productTemplates'));

        // 3) Admin-Backend zeigt alle 8 Vorlagen inkl. Status-Badge "Saison aktiv" / "Außer Saison"
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-tpl-' . Str::lower(Str::random(5)) . '@mein-seelenfunke.de',
            'password' => Hash::make('Secret123!'),
        ]);
        AdminProfile::firstOrCreate(['admin_id' => $admin->id], ['city' => 'Gifhorn', 'country' => 'DE']);

        Livewire::actingAs($admin, 'admin')
            ->test(ProductTemplates::class)
            ->assertSee('Frohe Weihnachten')
            ->assertSee('Frohes Osterfest')
            ->assertSee('Saison aktiv')
            ->assertSee('Außer Saison');

        // 4) Strikte Prüfung auf 0 KI-Gedankenstriche in allen bearbeiteten Dateien
        $checkedFiles = [
            app_path('Models/Product/ProductTemplate.php'),
            app_path('Livewire/Shop/Product/ProductShow.php'),
            app_path('Livewire/Shop/Product/ProductCalculator/ProductCalculator.php'),
            app_path('Livewire/Shop/Product/ProductFrontendFilterArea.php'),
            resource_path('views/livewire/shop/product/partials/product-show/right.blade.php'),
            resource_path('views/livewire/shop/product/product-calculator/product-calculator.blade.php'),
            resource_path('views/livewire/shop/product/product-templates.blade.php'),
            base_path('Abschlussberichte/Produkte/Produkte_Vorlagen.md'),
        ];

        foreach ($checkedFiles as $filePath) {
            $this->assertFileExists($filePath);
            $this->assertStringNotContainsString(
                " \u{2013} ",
                (string) file_get_contents($filePath),
                'Unerlaubter KI-Gedankenstrich gefunden in: ' . $filePath
            );
        }
    }
}

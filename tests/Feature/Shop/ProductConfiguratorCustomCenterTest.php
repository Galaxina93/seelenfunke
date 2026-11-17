<?php

namespace Tests\Feature\Shop;

use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProductConfiguratorCustomCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'shop_maintenance_mode'], ['value' => 'false']);
    }

    /**
     * @return array<int, array{x: float, y: float}>
     */
    private function seelenAnhaengerHeartPoints(): array
    {
        return [
            ['x' => 50.0, 'y' => 59.45],
            ['x' => 51.13, 'y' => 58.73],
            ['x' => 52.23, 'y' => 58.01],
            ['x' => 53.34, 'y' => 57.29],
            ['x' => 54.54, 'y' => 56.57],
            ['x' => 55.82, 'y' => 55.84],
            ['x' => 57.26, 'y' => 55.12],
            ['x' => 58.83, 'y' => 54.52],
            ['x' => 60.42, 'y' => 54.19],
            ['x' => 62.68, 'y' => 54.48],
            ['x' => 64.54, 'y' => 55.02],
            ['x' => 66.13, 'y' => 55.74],
            ['x' => 67.49, 'y' => 56.57],
            ['x' => 68.67, 'y' => 57.6],
            ['x' => 69.63, 'y' => 58.73],
            ['x' => 70.38, 'y' => 59.97],
            ['x' => 70.95, 'y' => 61.31],
            ['x' => 71.41, 'y' => 62.75],
            ['x' => 71.7, 'y' => 64.3],
            ['x' => 71.84, 'y' => 65.55],
            ['x' => 71.9, 'y' => 66.62],
            ['x' => 71.82, 'y' => 67.69],
            ['x' => 71.66, 'y' => 68.94],
            ['x' => 71.35, 'y' => 70.59],
            ['x' => 70.87, 'y' => 72.34],
            ['x' => 70.21, 'y' => 74.1],
            ['x' => 69.35, 'y' => 75.95],
            ['x' => 68.31, 'y' => 77.81],
            ['x' => 67.12, 'y' => 79.67],
            ['x' => 65.72, 'y' => 81.52],
            ['x' => 64.07, 'y' => 83.38],
            ['x' => 62.27, 'y' => 85.23],
            ['x' => 60.21, 'y' => 87.09],
            ['x' => 57.94, 'y' => 88.95],
            ['x' => 55.57, 'y' => 90.7],
            ['x' => 53.47, 'y' => 92.25],
            ['x' => 51.55, 'y' => 93.55],
            ['x' => 50.0, 'y' => 94.52],
            ['x' => 48.45, 'y' => 93.55],
            ['x' => 46.53, 'y' => 92.25],
            ['x' => 44.43, 'y' => 90.7],
            ['x' => 42.06, 'y' => 88.95],
            ['x' => 39.79, 'y' => 87.09],
            ['x' => 37.73, 'y' => 85.23],
            ['x' => 35.93, 'y' => 83.38],
            ['x' => 34.28, 'y' => 81.52],
            ['x' => 32.88, 'y' => 79.67],
            ['x' => 31.68, 'y' => 77.81],
            ['x' => 30.65, 'y' => 75.95],
            ['x' => 29.79, 'y' => 74.1],
            ['x' => 29.13, 'y' => 72.34],
            ['x' => 28.65, 'y' => 70.59],
            ['x' => 28.34, 'y' => 68.94],
            ['x' => 28.18, 'y' => 67.69],
            ['x' => 28.1, 'y' => 66.62],
            ['x' => 28.16, 'y' => 65.55],
            ['x' => 28.3, 'y' => 64.3],
            ['x' => 28.59, 'y' => 62.75],
            ['x' => 29.04, 'y' => 61.31],
            ['x' => 29.62, 'y' => 59.97],
            ['x' => 30.37, 'y' => 58.73],
            ['x' => 31.33, 'y' => 57.6],
            ['x' => 32.51, 'y' => 56.57],
            ['x' => 33.87, 'y' => 55.74],
            ['x' => 35.46, 'y' => 55.02],
            ['x' => 37.32, 'y' => 54.48],
            ['x' => 39.58, 'y' => 54.19],
            ['x' => 41.17, 'y' => 54.52],
            ['x' => 42.74, 'y' => 55.12],
            ['x' => 44.18, 'y' => 55.84],
            ['x' => 45.46, 'y' => 56.57],
            ['x' => 46.66, 'y' => 57.29],
            ['x' => 47.77, 'y' => 58.01],
            ['x' => 48.87, 'y' => 58.73],
        ];
    }

    /**
     * @param array<int, array{x: float, y: float}> $pts
     */
    private function isPointInsidePolygon(array $pts, float $px, float $py): bool
    {
        $inside = false;
        $n = count($pts);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = (float) $pts[$i]['x'];
            $yi = (float) $pts[$i]['y'];
            $xj = (float) $pts[$j]['x'];
            $yj = (float) $pts[$j]['y'];
            $intersect = (($yi > $py) !== ($yj > $py))
                && ($px < ($xj - $xi) * ($py - $yi) / (($yj - $yi) ?: 1e-7) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

    public function test_all_seeded_custom_polygon_products_compute_true_interior_visual_center(): void
    {
        $this->seed(ProductSeeder::class);

        $customProducts = Product::query()
            ->get()
            ->filter(fn (Product $p) => (($p->configurator_settings['area_shape'] ?? '') === 'custom')
                && !empty($p->configurator_settings['custom_points']));

        $this->assertGreaterThanOrEqual(6, $customProducts->count());

        $probe = new ProductConfigurator();

        foreach ($customProducts as $product) {
            $pts = $product->configurator_settings['custom_points'];
            $vc = $probe->computeCustomPolygonVisualCenter($pts);

            $this->assertTrue(
                $this->isPointInsidePolygon($pts, $vc['x'], $vc['y']),
                "Optischer Schwerpunkt ({$vc['x']}, {$vc['y']}) für [{$product->slug}] muss strikt innerhalb des Polygons liegen."
            );

            if ($product->slug === 'seelen-anhaenger') {
                // Herz-Bauch: x ≈ 50.0 %, y ∈ [69.5 %, 71.0 %] mit reichlich Abstand unter der oberen Herz-Kerbe (y = 59.45 %)
                $this->assertEqualsWithDelta(50.0, $vc['x'], 0.15);
                $this->assertGreaterThanOrEqual(69.5, $vc['y']);
                $this->assertLessThanOrEqual(71.0, $vc['y']);
                $this->assertGreaterThanOrEqual(37.5, $vc['inscribedWidthPct']);
                $this->assertLessThanOrEqual(42.5, $vc['inscribedWidthPct']);
            } elseif ($product->slug === 'seelen-pfote-hundemarke') {
                // Hundeknochen mit Pfoten-Durchbruch rechts -> weicht sauber nach links in den breiten Gravurbauch (x ≈ 41 %) aus
                $this->assertGreaterThanOrEqual(39.5, $vc['x']);
                $this->assertLessThanOrEqual(42.5, $vc['x']);
                $this->assertGreaterThanOrEqual(47.0, $vc['y']);
                $this->assertLessThanOrEqual(50.5, $vc['y']);
            } elseif (in_array($product->slug, ['id-armband-silber-herz', 'id-armband-gold-herz'], true)) {
                // Rechts versetzte Armband-Gravurplatte (35.10..78.60 %) -> x ≈ 56.85 %, y ≈ 50.0 %
                $this->assertEqualsWithDelta(56.85, $vc['x'], 0.25);
                $this->assertEqualsWithDelta(50.0, $vc['y'], 0.25);
            } elseif ($product->slug === 'seelen-talisman') {
                // Dog-Tag Plakette unterhalb der Ösen-Kerbe (y = 46.84 %) -> x ≈ 49.75 %, y ≈ 67.8 %
                $this->assertEqualsWithDelta(49.75, $vc['x'], 0.25);
                $this->assertGreaterThanOrEqual(66.0, $vc['y']);
                $this->assertLessThanOrEqual(69.5, $vc['y']);
            } elseif (in_array($product->slug, ['goldenes-seelen-herz', 'echtgold-seelen-herz'], true)) {
                $this->assertEqualsWithDelta(50.0, $vc['x'], 0.25);
                $this->assertGreaterThanOrEqual(58.0, $vc['y']);
                $this->assertLessThanOrEqual(64.0, $vc['y']);
            }
        }
    }

    public function test_custom_circle_and_rect_configurators_render_zero_regression_markup_and_studio_spawn_coords(): void
    {
        Storage::disk('public')->put(
            'produkte/configurator/vectors/Liebe/test_herz.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.5-7 10-7 10z"/></svg>'
        );

        $seelenAnhaenger = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelenanhänger',
            'slug' => 'seelen-anhaenger',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 2990,
            'configurator_settings' => [
                'personalization_mode' => 'studio',
                'allow_logo' => true,
                'template_package' => 'seelen-anhaenger',
                'area_shape' => 'custom',
                'area_left' => 28.1,
                'area_top' => 54.19,
                'area_width' => 43.8,
                'area_height' => 40.33,
                'custom_points' => $this->seelenAnhaengerHeartPoints(),
            ],
        ]);

        $seelenKristall = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 6990,
            'configurator_settings' => [
                'personalization_mode' => 'studio',
                'allow_logo' => true,
                'template_package' => 'seelen-kristall',
                'area_shape' => 'circle',
                'area_left' => 16.0,
                'area_top' => 11.6,
                'area_width' => 68.0,
                'area_height' => 68.0,
            ],
        ]);

        $weizenglas = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Feierabend-Funke Weizenglas',
            'slug' => 'weizenglas-personalisiert',
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 2490,
            'configurator_settings' => [
                'personalization_mode' => 'studio',
                'allow_logo' => true,
                'template_package' => 'weizenglas-personalisiert',
                'area_shape' => 'rect',
                'area_left' => 27.0,
                'area_top' => 25.0,
                'area_width' => 46.0,
                'area_height' => 44.0,
            ],
        ]);

        // 1. Seelenanhänger (custom): Leer-Menü-Markup, unveränderte areaCenterX/Y & exakte Spawn-Koordinaten im Herz-Bauch
        $customLw = Livewire::test(ProductConfigurator::class, [
            'product' => $seelenAnhaenger,
            'context' => 'add',
        ]);

        $html = $customLw->html();
        $this->assertStringContainsString('data-testid="configurator-empty-state-menu"', $html);
        $this->assertStringContainsString('+ Text hinzufügen', $html);
        $this->assertStringContainsString('+ Bild / Motiv laden', $html);
        $this->assertStringContainsString('Symbole', $html);
        $this->assertStringContainsString('left: ${emptyStateBoxStyle.left}; top: ${emptyStateBoxStyle.top}; transform: translate(-50%, -50%) scale(${emptyStateBoxStyle.scale});', $html);
        $this->assertStringContainsString('getCustomPolygonVisualCenter(', $html);
        $this->assertStringContainsString('get areaCenterX()', $html);
        $this->assertStringContainsString('get areaCenterY()', $html);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $html);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $html);

        // Neulings-Koordinaten bleiben zu 100 % unverändert (Zero-Regression)
        $beginnerCoords = $customLw->instance()->getBeginnerPositionCoordinates();
        $this->assertSame(50.0, $beginnerCoords['center_x']);
        $this->assertSame(72.0, $beginnerCoords['center_y']);
        $this->assertSame(65.5, $beginnerCoords['symbol_y']);
        $this->assertSame(76.0, $beginnerCoords['text_y']);

        // Studio-Text-Spawn & Symbol-Spawn landen exakt im optischen Herz-Bauch (50.0 %, ~70.68 %)
        $customLw->set('texts', [])
            ->call('addText');
        $spawnedTexts = $customLw->get('texts');
        $this->assertCount(1, $spawnedTexts);
        $this->assertEqualsWithDelta(50.0, $spawnedTexts[0]['x'], 0.15);
        $this->assertGreaterThanOrEqual(69.5, $spawnedTexts[0]['y']);
        $this->assertLessThanOrEqual(71.0, $spawnedTexts[0]['y']);

        $customLw->set('texts', [])
            ->call('addStandardVector', 'Liebe/test_herz.svg');
        $spawnedLogos = $customLw->get('logos');
        $this->assertCount(1, $spawnedLogos);
        $this->assertEqualsWithDelta(50.0, $spawnedLogos[0]['x'], 0.15);
        $this->assertGreaterThanOrEqual(69.5, $spawnedLogos[0]['y']);
        $this->assertLessThanOrEqual(71.0, $spawnedLogos[0]['y']);

        // 2. Seelen-Kristall (circle) & Weizenglas (rect): 100 % unveränderte Koordinaten
        $circleLw = Livewire::test(ProductConfigurator::class, [
            'product' => $seelenKristall,
            'context' => 'add',
        ]);
        $circleSpawn = $circleLw->instance()->resolveStudioSpawnCenter();
        $this->assertSame(50.0, $circleSpawn['x']);
        $this->assertSame(45.6, $circleSpawn['y']);

        $rectLw = Livewire::test(ProductConfigurator::class, [
            'product' => $weizenglas,
            'context' => 'add',
        ]);
        $rectSpawn = $rectLw->instance()->resolveStudioSpawnCenter();
        $this->assertSame(50.0, $rectSpawn['x']);
        $this->assertSame(47.0, $rectSpawn['y']);
    }
}

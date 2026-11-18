<?php

namespace Tests\Feature\Product;

use Database\Seeders\ProductAssetSeeder;
use ReflectionClass;
use Tests\TestCase;

/**
 * Regressionstests für den 3D-Konfigurator:
 * 1. Der Seelenanhänger: kein umgeklapptes Fan-Dreieck am Herzeinschnitt (dunkler, flackernder Mittelstreifen).
 * 2. Echtsilber & Goldenes Seelenarmband: maßtreu geschlossener Ring (18,5 cm) statt offenem Bandstück,
 *    Gravurplatte als vorderster Punkt und Rückseiten-Gravur exakt auf der Plattenrückseite.
 */
class BraceletClosedModelAndHeartShadowTest extends TestCase
{
    private ProductAssetSeeder $seeder;

    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeder = new ProductAssetSeeder();
        $this->reflection = new ReflectionClass($this->seeder);
    }

    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($this->seeder, $args);
    }

    /**
     * Liefert die vorzeichenbehafteten XY-Flächen aller Dreiecke, deren drei Vertices exakt auf Höhe $z liegen.
     */
    private function signedAreasAtZ(array $mesh, float $z): array
    {
        $p = $mesh['positions'];
        $idx = $mesh['indices'];
        $areas = [];
        for ($t = 0; $t < count($idx); $t += 3) {
            $v = [];
            foreach ([$idx[$t], $idx[$t + 1], $idx[$t + 2]] as $i) {
                $v[] = [$p[$i * 3], $p[$i * 3 + 1], $p[$i * 3 + 2]];
            }
            if (abs($v[0][2] - $z) > 1e-9 || abs($v[1][2] - $z) > 1e-9 || abs($v[2][2] - $z) > 1e-9) {
                continue;
            }
            $areas[] = 0.5 * (($v[1][0] - $v[0][0]) * ($v[2][1] - $v[0][1]) - ($v[2][0] - $v[0][0]) * ($v[1][1] - $v[0][1]));
        }

        return $areas;
    }

    private function bbox(array $positions): array
    {
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        for ($i = 0; $i < count($positions); $i += 3) {
            for ($c = 0; $c < 3; $c++) {
                $min[$c] = min($min[$c], $positions[$i + $c]);
                $max[$c] = max($max[$c], $positions[$i + $c]);
            }
        }

        return [$min, $max];
    }

    public function test_heart_pendant_front_and_back_fans_have_no_folded_or_overlapping_triangles(): void
    {
        $this->assertFanFacesAreFoldFree('buildSeelenAnhaenger3DMesh', 0.032);
    }

    public function test_other_flat_metal_products_keep_fold_free_front_and_back_faces(): void
    {
        $this->assertFanFacesAreFoldFree('buildGoldenesHerz3DMesh', 0.088);
        $this->assertFanFacesAreFoldFree('buildPfote3DMesh', 0.032);
        $this->assertFanFacesAreFoldFree('buildIdArmbandHerz3DMesh', ProductAssetSeeder::ARMBAND_PLATE_Z_HALF);
    }

    private function assertFanFacesAreFoldFree(string $builder, float $zHalf): void
    {
        $mesh = $this->invokePrivate($builder);

        foreach ([$zHalf, -$zHalf] as $z) {
            $areas = $this->signedAreasAtZ($mesh, $z);
            $this->assertNotEmpty($areas, "{$builder}: keine Flächen auf z = {$z} gefunden.");

            $positive = array_filter($areas, static fn (float $a): bool => $a > 1e-12);
            $negative = array_filter($areas, static fn (float $a): bool => $a < -1e-12);
            $this->assertTrue(
                count($positive) === 0 || count($negative) === 0,
                sprintf('%s: Fläche z = %s enthält %d umgeklappte Dreiecke (dunkler Z-Fighting-Streifen).', $builder, $z, min(count($positive), count($negative)))
            );

            // Keine Überlappung: Summe der Beträge == Betrag der Summe
            $sumAbs = array_sum(array_map('abs', $areas));
            $absSum = abs(array_sum($areas));
            $this->assertEqualsWithDelta($sumAbs, $absSum, 1e-9, "{$builder}: überlappende Flächen auf z = {$z}.");
        }
    }

    public function test_star_shaped_contour_sanitizer_removes_backtracking_notch_points(): void
    {
        // Synthetische Herzkerbe: Punkt 1 und 2 kreuzen die Mittelachse (wie beim fehlerhaften Normalen-Offset)
        $contour = [
            [400.0, 445.0], [395.0, 460.0], [397.5, 458.5], [401.5, 455.0], [520.0, 430.0], [590.0, 560.0],
            [400.0, 772.0], [210.0, 560.0], [280.0, 430.0], [398.5, 455.0], [402.5, 458.5], [405.0, 460.0],
        ];
        $clean = $this->invokePrivate('sanitizeStarShapedContour', [$contour, [400.0, 595.0]]);

        $this->assertNotContains([395.0, 460.0], $clean);
        $this->assertNotContains([397.5, 458.5], $clean);
        $this->assertNotContains([402.5, 458.5], $clean);
        $this->assertNotContains([405.0, 460.0], $clean);
        $this->assertContains([401.5, 455.0], $clean);
        $this->assertContains([398.5, 455.0], $clean);
        $this->assertSame([400.0, 445.0], $clean[0]);
    }

    public function test_id_bracelet_mesh_is_a_closed_true_to_scale_ring(): void
    {
        $mesh = $this->invokePrivate('buildIdArmbandHerz3DMesh');
        $mm = ProductAssetSeeder::ARMBAND_MM;
        [$min, $max] = $this->bbox($mesh['positions']);

        // 18,5 cm Gesamtlänge (Platte + Endkappen + Figaro-Kette + Federring)
        $this->assertEqualsWithDelta(185.0, $mesh['meta']['total_length_mm'], 0.5);
        $this->assertGreaterThanOrEqual(60, $mesh['meta']['link_count']);
        $this->assertSame(0, $mesh['meta']['link_count'] % 2, 'Kette muss symmetrisch links/rechts verlaufen.');

        // Echter Ring statt flachem Bandstück: Ø ca. 59 mm in X und Tiefe in Z
        $widthMm = ($max[0] - $min[0]) / $mm;
        $depthMm = ($max[2] - $min[2]) / $mm;
        $this->assertGreaterThan(55.0, $widthMm);
        $this->assertLessThan(64.0, $widthMm);
        $this->assertGreaterThan(50.0, $depthMm);

        // Gravurplatte ist der vorderste Punkt (Front-Gravurebene liegt exakt auf der Platte)
        $this->assertEqualsWithDelta(ProductAssetSeeder::ARMBAND_PLATE_Z_HALF, $max[2], 1e-9);
        $this->assertEqualsWithDelta(0.6 * $mm, ProductAssetSeeder::ARMBAND_PLATE_Z_HALF, 1e-12);
        // Symmetrisch um die Overlay-Mitte (400, 400) -> 2D/3D deckungsgleich
        $this->assertEqualsWithDelta(0.0, $min[0] + $max[0], 1e-9);
        $this->assertEqualsWithDelta(0.0, $min[1] + $max[1], 1e-9);

        // Geschlossen: Jeder 10°-Sektor um die Ringmitte enthält Geometrie (keine offene Lücke).
        // Abgetastet werden Vertices, Kantenmitten und Schwerpunkte aller Dreiecke (große Plattendreiecke).
        $ringCenterZ = $min[2] + ($max[0] - $min[0]) * 0.5;
        $bins = array_fill(0, 36, 0);
        $p = $mesh['positions'];
        $idx = $mesh['indices'];
        for ($t = 0; $t < count($idx); $t += 3) {
            $v = [];
            foreach ([$idx[$t], $idx[$t + 1], $idx[$t + 2]] as $i) {
                $v[] = [$p[$i * 3], $p[$i * 3 + 2]];
            }
            $samples = [
                $v[0], $v[1], $v[2],
                [($v[0][0] + $v[1][0]) / 2, ($v[0][1] + $v[1][1]) / 2],
                [($v[1][0] + $v[2][0]) / 2, ($v[1][1] + $v[2][1]) / 2],
                [($v[2][0] + $v[0][0]) / 2, ($v[2][1] + $v[0][1]) / 2],
                [($v[0][0] + $v[1][0] + $v[2][0]) / 3, ($v[0][1] + $v[1][1] + $v[2][1]) / 3],
            ];
            foreach ($samples as [$sx, $sz]) {
                $deg = rad2deg(atan2($sz - $ringCenterZ, $sx));
                $bins[((int) floor(($deg + 360.0) / 10.0)) % 36]++;
            }
        }
        foreach ($bins as $sector => $count) {
            $this->assertGreaterThan(0, $count, 'Armband ist im Sektor ' . ($sector * 10) . '° offen.');
        }

        // Rückseite geschlossen: Kette/Federring direkt hinter der Platte (x ≈ 0, z minimal)
        $backHits = 0;
        for ($i = 0; $i < count($p); $i += 3) {
            if (abs($p[$i]) < 3.0 * $mm && $p[$i + 2] < $min[2] + 4.0 * $mm) {
                $backHits++;
            }
        }
        $this->assertGreaterThan(0, $backHits);
    }

    public function test_bracelet_and_heart_glbs_are_compact_valid_gltf_with_short_indices(): void
    {
        $png = file_get_contents(base_path('public/favicon.ico')) ?: str_repeat("\0", 64);
        foreach (['buildIdArmbandHerz3DMesh' => 300000, 'buildSeelenAnhaenger3DMesh' => 120000] as $builder => $maxBytes) {
            $mesh = $this->invokePrivate($builder);
            $glb = $this->invokePrivate('buildSingleTexturedGlb', [$mesh, $png, 'Test']);

            $this->assertSame('glTF', substr($glb, 0, 4));
            $this->assertSame(strlen($glb), unpack('V', substr($glb, 8, 4))[1]);
            $jsonLen = unpack('V', substr($glb, 12, 4))[1];
            $json = json_decode(substr($glb, 20, $jsonLen), true);
            $this->assertIsArray($json);
            $this->assertSame(5123, $json['accessors'][3]['componentType']);
            $this->assertSame(count($mesh['indices']), $json['accessors'][3]['count']);
            $this->assertLessThan($maxBytes, strlen($glb) - strlen($png), $builder . ' ist zu groß.');
        }
    }

    public function test_seeded_bracelet_engraving_planes_sit_exactly_on_plate_front_and_back(): void
    {
        $mesh = $this->invokePrivate('buildIdArmbandHerz3DMesh');
        [$min, $max] = $this->bbox($mesh['positions']);
        $maxDim = max($max[0] - $min[0], $max[1] - $min[1], $max[2] - $min[2]);
        // Konfigurator: Ebene = maxDim * 1,5 * scale; 800 px Overlay = 800 / 360 Einheiten
        $expectedScale = (800.0 / 360.0) / (1.5 * $maxDim) * 100.0;
        $zHalf = ProductAssetSeeder::ARMBAND_PLATE_Z_HALF;

        $source = file_get_contents(database_path('seeders/ProductSeeder.php'));
        foreach (['id-armband-silber-herz' => 'silver', 'id-armband-gold-herz' => 'gold'] as $slug => $material) {
            $start = strpos($source, "['slug' => '{$slug}']");
            $this->assertNotFalse($start, "Produkt {$slug} fehlt im ProductSeeder.");
            $block = substr($source, $start, 16000);

            $this->assertMatchesRegularExpression("/'material_type' => '{$material}'/", $block);
            $this->assertMatchesRegularExpression("/'three_d_model_path' => 'produkte\\/products\\/{$slug}\\/[a-z_]+_3d\\.glb'/", $block);

            preg_match("/'engraving_scale' => ([0-9.]+)/", $block, $front);
            preg_match("/'back_engraving_scale' => ([0-9.]+)/", $block, $back);
            preg_match("/'back_engraving_pos_z' => (-?[0-9.]+)/", $block, $backZ);
            $this->assertEqualsWithDelta($expectedScale, (float) $front[1], 0.05, "{$slug}: Front-Gravur nicht 1:1 zum Overlay.");
            $this->assertEqualsWithDelta($expectedScale, (float) $back[1], 0.05, "{$slug}: Rückseiten-Gravur nicht 1:1 zum Overlay.");

            // baseZ = Plattenfront + 0,001; Rückseite = baseZ + pos_z * 0,1 muss knapp hinter der Plattenrückseite liegen
            $backPlaneZ = ($zHalf + 0.001) + ((float) $backZ[1]) * 0.1;
            $this->assertLessThan(-$zHalf, $backPlaneZ, "{$slug}: Rückseiten-Gravur steckt in der Platte.");
            $this->assertGreaterThan(-$zHalf - 0.002, $backPlaneZ, "{$slug}: Rückseiten-Gravur schwebt hinter der Platte.");
        }
    }

    public function test_configurator_engine_renders_silver_and_gold_as_polished_metal_with_dark_engraving(): void
    {
        $engine = file_get_contents(resource_path('views/livewire/shop/product/product-configurator/partials/scripts_frontend_2.blade.php'));

        $this->assertStringContainsString("matType === 'metal' || matType === 'silver' || matType === 'gold'", $engine);
        $this->assertStringContainsString("const isMetalSurface = ['metal', 'silver', 'gold'].includes(this.config.material_type);", $engine);
        $this->assertStringNotContainsString("const isLightMetal = (this.config.material_type === 'metal'", $engine);
    }
}

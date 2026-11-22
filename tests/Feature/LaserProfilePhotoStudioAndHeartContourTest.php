<?php

namespace Tests\Feature;

use App\Models\Product\Product;
use App\Services\Export\FileDownloadService;
use App\Services\Laser\XtoolLaserCockpitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LaserProfilePhotoStudioAndHeartContourTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    private function createSeelenAnhaengerProduct(array $extraSettings = []): Product
    {
        $baseSettings = XtoolLaserCockpitService::applySeededMultiLaserProfilesToSettings('seelen-anhaenger', [
            'template_package'               => 'seelen-anhaenger',
            'overlay_type'                   => 'plane',
            'xtool_plane_width'              => 45.0,
            'xtool_plane_height'             => 40.0,
            'xtool_contour_shape'            => 'heart_anhaenger',
            'xtool_equipment_key'            => 'flatbed_standard',
            'xtool_process_mode'             => 'Prozess auf flacher Oberfläche',
            'xtool_material'                 => '0,2 mm Aluminiumlegierung',
            'xtool_photo_substrate_polarity' => 'white_on_dark',
            'area_shape'                     => 'custom',
            'area_top'                       => 54.05,
            'area_left'                      => 28.07,
            'area_width'                     => 43.86,
            'area_height'                    => 40.38,
        ]);

        return Product::create([
            'name'                  => 'Der Seelenanhänger',
            'slug'                  => 'seelen-anhaenger',
            'type'                  => 'physical',
            'status'                => 'active',
            'price'                 => 2990,
            'width'                 => 45,
            'height'                => 40,
            'length'                => 2,
            'weight'                => 18,
            'configurator_settings' => array_replace($baseSettings, $extraSettings),
        ]);
    }

    #[Test]
    public function it_maps_dither_modes_and_seeds_seelen_anhaenger_photo_studio_defaults(): void
    {
        $this->assertSame('Jarvis', XtoolLaserCockpitService::ditherModeToBitmapModeLabel('jarvis'));
        $this->assertSame('Stucki', XtoolLaserCockpitService::ditherModeToBitmapModeLabel('stucki'));
        $this->assertSame('Graustufen', XtoolLaserCockpitService::ditherModeToBitmapModeLabel('grayscale'));
        $this->assertSame('Schwellenwert', XtoolLaserCockpitService::ditherModeToBitmapModeLabel('threshold'));
        $this->assertSame('Jarvis', XtoolLaserCockpitService::ditherModeToBitmapModeLabel('unbekannt'));

        $this->assertSame('jarvis', XtoolLaserCockpitService::bitmapModeLabelToDitherMode('Jarvis'));
        $this->assertSame('stucki', XtoolLaserCockpitService::bitmapModeLabelToDitherMode('Stucki'));
        $this->assertSame('grayscale', XtoolLaserCockpitService::bitmapModeLabelToDitherMode('Graustufen'));
        $this->assertSame('threshold', XtoolLaserCockpitService::bitmapModeLabelToDitherMode('Schwellenwert'));
        $this->assertSame('stucki', XtoolLaserCockpitService::bitmapModeLabelToDitherMode('', 'stucki'));

        $catalog = XtoolLaserCockpitService::getDefaultProductLaserProfilesCatalog();
        $anhaengerProfiles = $catalog['seelen-anhaenger'] ?? [];
        $this->assertCount(1, $anhaengerProfiles);

        $expectedStudio = [
            'grayscale'     => true,
            'invert'        => true,
            'contrast'      => 20,
            'brightness'    => 0,
            'gamma'         => 1.00,
            'highlight_cap' => 58,
            'sharpness'     => 85,
            'dither_mode'   => 'jarvis',
            'threshold'     => 128,
        ];

        $lamellenProfile = collect($anhaengerProfiles)->firstWhere('id', 'prof_silber_kontrast_lamellenrost');

        $this->assertNotNull($lamellenProfile);
        $this->assertSame('100% Getestet · Perfekt', $lamellenProfile['title']);
        $this->assertEquals($expectedStudio, $lamellenProfile['photo_studio'] ?? null);

        $kristallProfiles = $catalog['seelen-kristall'] ?? [];
        $this->assertCount(2, $kristallProfiles);
        $this->assertSame('K9 Tiefen-Frost im Glas · Lamellenrost (31,2 mm)', $kristallProfiles[0]['title']);
        $this->assertEqualsWithDelta(31.2, (float) $kristallProfiles[0]['focus_mm'], 0.01);
        $this->assertTrue($kristallProfiles[0]['photo_studio']['invert'] ?? false);
        $this->assertSame('K9 Außen-Seidenmatt · Lamellenrost (32,5 mm)', $kristallProfiles[1]['title']);
    }

    #[Test]
    public function update_product_laser_profile_photo_studio_persists_all_nine_values_and_synced_bitmap_mode(): void
    {
        $product = $this->createSeelenAnhaengerProduct();
        $service = app(XtoolLaserCockpitService::class);

        $result = $service->updateProductLaserProfilePhotoStudio(
            $product,
            'prof_silber_kontrast_lamellenrost',
            [
                'enabled'       => true, // darf nicht am Profil gespeichert werden
                'grayscale'     => true,
                'invert'        => true,
                'contrast'      => 34,
                'brightness'    => -8,
                'gamma'         => 1.32,
                'highlight_cap' => 54,
                'sharpness'     => 95,
                'dither_mode'   => 'stucki',
                'threshold'     => 140,
            ]
        );

        $updated = $result['updated_profile'];
        $this->assertSame('prof_silber_kontrast_lamellenrost', $updated['id']);
        $this->assertSame('Stucki', $updated['bitmap_mode']);
        $this->assertArrayHasKey('photo_studio', $updated);
        $this->assertArrayNotHasKey('enabled', $updated['photo_studio']);
        $this->assertSame(
            ['grayscale', 'invert', 'contrast', 'brightness', 'gamma', 'highlight_cap', 'sharpness', 'dither_mode', 'threshold'],
            array_keys($updated['photo_studio'])
        );

        $fresh = $product->fresh();
        $dbProfiles = $fresh->configurator_settings['xtool_profiles'] ?? [];
        $dbTarget = collect($dbProfiles)->firstWhere('id', 'prof_silber_kontrast_lamellenrost');

        $this->assertNotNull($dbTarget);
        $this->assertSame('Stucki', $dbTarget['bitmap_mode']);
        $this->assertSame('Stucki', $fresh->configurator_settings['xtool_bitmap_mode']);
        $this->assertArrayNotHasKey('enabled', $dbTarget['photo_studio']);
        $this->assertTrue($dbTarget['photo_studio']['grayscale']);
        $this->assertTrue($dbTarget['photo_studio']['invert']);
        $this->assertSame(34, $dbTarget['photo_studio']['contrast']);
        $this->assertSame(-8, $dbTarget['photo_studio']['brightness']);
        $this->assertEqualsWithDelta(1.32, $dbTarget['photo_studio']['gamma'], 0.001);
        $this->assertSame(54, $dbTarget['photo_studio']['highlight_cap']);
        $this->assertSame(95, $dbTarget['photo_studio']['sharpness']);
        $this->assertSame('stucki', $dbTarget['photo_studio']['dither_mode']);
        $this->assertSame(140, $dbTarget['photo_studio']['threshold']);

        // Auch beim späteren Aktualisieren von Maschinenparametern via saveLaserProfileToProduct bleibt photo_studio erhalten
        $afterParamSave = $service->saveLaserProfileToProduct(
            $fresh,
            '100% Getestet · Perfekt',
            ['power_percent' => 82, 'bitmap_mode' => 'Stucki'],
            true
        );
        $this->assertSame(82, $afterParamSave['profile']['power_percent']);
        $this->assertSame(34, $afterParamSave['profile']['photo_studio']['contrast'] ?? null);
        $this->assertSame(54, $afterParamSave['profile']['photo_studio']['highlight_cap'] ?? null);

        // Fallback-Check für ein komplett neues Produkt ohne bestehendes xtool_profiles-Array
        $bareProduct = Product::create([
            'name'                  => 'Schiefer-Sonderunikat',
            'slug'                  => 'schiefer-sonderunikat',
            'type'                  => 'physical',
            'status'                => 'active',
            'price'                 => 3990,
            'configurator_settings' => [
                'xtool_material'      => 'Naturschiefer',
                'xtool_focus_mm'      => 18.4,
                'xtool_power_percent' => 65,
            ],
        ]);

        $bareRes = $service->updateProductLaserProfilePhotoStudio($bareProduct, 'non_existent', [
            'grayscale'     => true,
            'invert'        => true,
            'contrast'      => 22,
            'brightness'    => 4,
            'gamma'         => 1.15,
            'highlight_cap' => 62,
            'sharpness'     => 105,
            'dither_mode'   => 'grayscale',
            'threshold'     => 128,
        ]);

        $this->assertCount(1, $bareRes['profiles']);
        $this->assertSame('Graustufen', $bareRes['updated_profile']['bitmap_mode']);
        $this->assertSame(22, $bareProduct->fresh()->configurator_settings['xtool_profiles'][0]['photo_studio']['contrast']);
    }

    #[Test]
    public function create_product_laser_profile_from_photo_studio_clones_machine_values_and_preserves_existing_profiles(): void
    {
        $product = $this->createSeelenAnhaengerProduct();
        $service = app(XtoolLaserCockpitService::class);

        $beforeCount = count($product->configurator_settings['xtool_profiles']);
        $this->assertSame(1, $beforeCount);

        $createdResult = $service->createProductLaserProfileFromPhotoStudio(
            $product,
            'Porträt Gegenlicht · Bodenplatte Spezial',
            'prof_silber_kontrast_lamellenrost',
            [
                'enabled'       => true,
                'grayscale'     => true,
                'invert'        => true,
                'contrast'      => 28,
                'brightness'    => -12,
                'gamma'         => 1.45,
                'highlight_cap' => 52,
                'sharpness'     => 92,
                'dither_mode'   => 'threshold',
                'threshold'     => 135,
            ],
            false
        );

        $this->assertCount(2, $createdResult['profiles']);
        $newProf = $createdResult['created_profile'];

        $this->assertStringStartsWith('prof_', $newProf['id']);
        $this->assertSame(15, strlen($newProf['id'])); // 'prof_' + 10 Zeichen
        $this->assertSame('Porträt Gegenlicht · Bodenplatte Spezial', $newProf['title']);
        $this->assertFalse($newProf['is_default']);

        // Geklonte Maschinenwerte von prof_silber_kontrast_lamellenrost prüfen
        $this->assertEqualsWithDelta(19.9, (float) $newProf['focus_mm'], 0.001);
        $this->assertSame(85, $newProf['power_percent']);
        $this->assertSame(280, $newProf['dot_duration_us']);
        $this->assertSame(450, $newProf['speed_mms']);
        $this->assertSame(40, $newProf['frequency_khz']);
        $this->assertSame(600, $newProf['dpi']);
        $this->assertSame(1, $newProf['passes']);
        $this->assertSame('Bild', $newProf['element_type']);
        $this->assertSame('Gravieren', $newProf['engrave_tab']);
        $this->assertSame('Referenz', $newProf['preset_mode']);
        $this->assertSame('Zweidirektional', $newProf['engrave_mode']);
        $this->assertSame('Gravieren · 70 % · 450 mm/s · 300 Linien/cm · 40 kHz', $newProf['vector_note']);

        // Synchronisierter Bitmap-Modus + 9 Foto-Studio-Werte
        $this->assertSame('Schwellenwert', $newProf['bitmap_mode']);
        $this->assertArrayNotHasKey('enabled', $newProf['photo_studio']);
        $this->assertSame(28, $newProf['photo_studio']['contrast']);
        $this->assertSame(-12, $newProf['photo_studio']['brightness']);
        $this->assertEqualsWithDelta(1.45, $newProf['photo_studio']['gamma'], 0.001);
        $this->assertSame(52, $newProf['photo_studio']['highlight_cap']);
        $this->assertSame('threshold', $newProf['photo_studio']['dither_mode']);
        $this->assertSame(135, $newProf['photo_studio']['threshold']);

        // Bestehendes Master-Profil + neu angelegtes Profil sind in der DB vorhanden
        $freshProfiles = $product->fresh()->configurator_settings['xtool_profiles'];
        $this->assertCount(2, $freshProfiles);
        $origDefault = collect($freshProfiles)->firstWhere('id', 'prof_silber_kontrast_lamellenrost');
        $this->assertNotNull($origDefault);
        $this->assertTrue($origDefault['is_default']);
        $this->assertSame(20, $origDefault['photo_studio']['contrast']);

        // Leerer Titel muss InvalidArgumentException werfen
        $this->expectException(\InvalidArgumentException::class);
        $service->createProductLaserProfileFromPhotoStudio($product, '   ', null, ['dither_mode' => 'jarvis']);
    }

    #[Test]
    public function apply_seeded_multi_laser_profiles_to_settings_preserves_custom_admin_profiles_and_saved_photo_studio(): void
    {
        $initialSettings = [
            'template_package' => 'seelen-anhaenger',
            'xtool_profiles'   => [
                [
                    'id'              => 'prof_silber_kontrast_lamellenrost',
                    'title'           => 'Weiß / Silber-Kontrast · Lamellenrost',
                    'is_default'      => true,
                    'focus_mm'        => 19.9,
                    'dot_duration_us' => 280,
                    'power_percent'   => 85,
                    'dpi'             => 600,
                    'frequency_khz'   => 40,
                    'speed_mms'       => 450,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Stucki',
                    'engrave_mode'    => 'Zweidirektional',
                    'photo_studio'    => [
                        'grayscale'     => true,
                        'invert'        => true,
                        'contrast'      => 31,
                        'brightness'    => -5,
                        'gamma'         => 1.25,
                        'highlight_cap' => 55,
                        'sharpness'     => 90,
                        'dither_mode'   => 'stucki',
                        'threshold'     => 128,
                    ],
                ],
                [
                    'id'              => 'prof_admin_haustier_fell',
                    'title'           => 'Admin Spezial · Dunkles Hundefell',
                    'is_default'      => false,
                    'focus_mm'        => 19.9,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 260,
                    'power_percent'   => 80,
                    'dpi'             => 600,
                    'frequency_khz'   => 40,
                    'speed_mms'       => 480,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 70 % · 480 mm/s · 300 Linien/cm · 40 kHz',
                    'photo_studio'    => [
                        'grayscale'     => true,
                        'invert'        => true,
                        'contrast'      => 26,
                        'brightness'    => 10,
                        'gamma'         => 0.88,
                        'highlight_cap' => 60,
                        'sharpness'     => 110,
                        'dither_mode'   => 'jarvis',
                        'threshold'     => 128,
                    ],
                ],
            ],
        ];

        $merged = XtoolLaserCockpitService::applySeededMultiLaserProfilesToSettings('seelen-anhaenger', $initialSettings);
        $profiles = $merged['xtool_profiles'] ?? [];

        // 1 Katalog-Profil + 1 eigenes Admin-Zusatzprofil = 2 Profile
        $this->assertCount(2, $profiles);

        $customAdmin = collect($profiles)->firstWhere('id', 'prof_admin_haustier_fell');
        $this->assertNotNull($customAdmin, 'Eigenes Admin-Zusatzprofil darf beim Seeding nicht gelöscht werden');
        $this->assertSame('Admin Spezial · Dunkles Hundefell', $customAdmin['title']);
        $this->assertSame(480, $customAdmin['speed_mms']);
        $this->assertSame(110, $customAdmin['photo_studio']['sharpness'] ?? null);

        $seededDefault = collect($profiles)->firstWhere('id', 'prof_silber_kontrast_lamellenrost');
        $this->assertNotNull($seededDefault);
        $this->assertSame(31, $seededDefault['photo_studio']['contrast'] ?? null, 'Bereits gespeicherte photo_studio-Anpassung muss erhalten bleiben');
        $this->assertSame('Stucki', $seededDefault['bitmap_mode']);
    }

    #[Test]
    public function heart_anhaenger_contour_renders_full_convex_upper_lobe_curve_and_sharp_notch_and_tip(): void
    {
        $exportService = app(FileDownloadService::class);
        $widthMm = 43.86;
        $heightMm = 40.38;

        $dPath = $exportService->buildSingleOuterBlankContourDPath('heart_anhaenger', $widthMm, $heightMm);
        $this->assertStringStartsWith('M ', $dPath);
        $this->assertStringEndsWith(' Z', $dPath);

        preg_match_all('/[ML]\s+([0-9.]+)\s+([0-9.]+)/', $dPath, $matches, PREG_SET_ORDER);
        $this->assertCount(72, $matches, 'Herz-Kontur muss exakt 72 Punkte ohne Duplikate an V-Kerbe und Spitze besitzen');

        $pts = array_map(static fn (array $m): array => [(float) $m[1], (float) $m[2]], $matches);

        // Index 0 = scharfe V-Kerbe oben exakt auf der Mittelachse (x = widthMm / 2)
        $vNotch = $pts[0];
        $this->assertEqualsWithDelta($widthMm * 0.5, $vNotch[0], 0.02);
        $expectedNotchYMm = ((59.47 - 54.05) / 40.38) * $heightMm;
        $this->assertEqualsWithDelta($expectedNotchYMm, $vNotch[1], 0.05);

        // Index 12 = rechter Lobe-Scheitel, Index 60 = linker Lobe-Scheitel (y = 0.000 mm, kein flaches Clamping-Plateau)
        $rightApex = $pts[12];
        $leftApex = $pts[60];
        $this->assertEqualsWithDelta(0.0, $rightApex[1], 0.01);
        $this->assertEqualsWithDelta(0.0, $leftApex[1], 0.01);
        $this->assertGreaterThan(0.02, $pts[10][1], 'Kein horizontales Clamping-Plateau vor dem Lobe-Scheitel');
        $this->assertGreaterThan(0.02, $pts[14][1], 'Kein horizontales Clamping-Plateau hinter dem Lobe-Scheitel');

        // Konvexe Wölbung prüfen: Punkt in der Mitte von Segment 1 (Index 6) muss echt oberhalb (kleineres y)
        // der Verbindungssehne zwischen V-Kerbe (Index 0) und rechtem Lobe-Scheitel (Index 12) liegen!
        $midSeg1 = $pts[6];
        $tChord1 = ($midSeg1[0] - $vNotch[0]) / ($rightApex[0] - $vNotch[0]);
        $chordY1 = $vNotch[1] + $tChord1 * ($rightApex[1] - $vNotch[1]);
        $this->assertLessThan($chordY1 - 0.5, $midSeg1[1], 'Oberer Herz-Bogen (Segment 1) muss voll konvex nach oben gewölbt sein');

        // Konvexe Wölbung auf Segment 2 (Index 18 zwischen Lobe-Scheitel Index 12 und äußerer Wange Index 24)
        $rightCheek = $pts[24];
        $midSeg2 = $pts[18];
        $tChord2 = ($midSeg2[0] - $rightApex[0]) / ($rightCheek[0] - $rightApex[0]);
        $chordY2 = $rightApex[1] + $tChord2 * ($rightCheek[1] - $rightApex[1]);
        $this->assertLessThan($chordY2 - 2.0, $midSeg2[1], 'Äußerer oberer Herz-Bogen (Segment 2) muss voll konvex nach außen-oben gewölbt sein');

        // Index 36 = scharfe untere Spitze exakt bei (widthMm / 2, heightMm)
        $bottomTip = $pts[36];
        $this->assertEqualsWithDelta($widthMm * 0.5, $bottomTip[0], 0.02);
        $this->assertEqualsWithDelta($heightMm, $bottomTip[1], 0.02);

        // Prüfe auch buildWorkingAreaPolygonPercent: 72-Punkte-Herz behält V-Kerbe, Spitze und Lobe-Scheitel exakt ohne Abflachung
        $customPointsPct = array_map(static fn (array $p): array => [
            'x' => 28.07 + ($p[0] / $widthMm) * 43.86,
            'y' => 54.05 + ($p[1] / $heightMm) * 40.38,
        ], $pts);

        $polyPct = $exportService->buildWorkingAreaPolygonPercent([
            'area_shape'    => 'custom',
            'area_top'      => 54.05,
            'area_left'     => 28.07,
            'area_width'    => 43.86,
            'area_height'   => 40.38,
            'custom_points' => $customPointsPct,
        ]);

        $this->assertCount(72, $polyPct);
        $this->assertEqualsWithDelta(50.00, $polyPct[0]['x'], 0.02);
        $this->assertEqualsWithDelta(59.47, $polyPct[0]['y'], 0.02);
        $this->assertEqualsWithDelta(54.05, $polyPct[12]['y'], 0.02);
        $this->assertEqualsWithDelta(50.00, $polyPct[36]['x'], 0.02);
        $this->assertEqualsWithDelta(94.43, $polyPct[36]['y'], 0.02);
    }
}

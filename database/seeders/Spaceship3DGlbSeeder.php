<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class Spaceship3DGlbSeeder extends Seeder
{
    // UV-Regionen im 512x512 High-Precision PBR Texture Atlas:
    // [uMin, vMin, uMax, vMax]
    private const UV_HULL     = [0.03, 0.03, 0.47, 0.47]; // Tile A: Rumpf-Panzerung & Filigran-Nähte
    private const UV_WING     = [0.53, 0.03, 0.97, 0.47]; // Tile B: Schwingen / Kristallfacetten / Stealth-Flügel
    private const UV_TRIM     = [0.03, 0.53, 0.47, 0.72]; // Tile C: 24k Gold / Platin-Zierleisten & Runenringe
    private const UV_CANOPY   = [0.53, 0.53, 0.73, 0.72]; // Tile D1: Cockpit-Glas & Hologramm-HUD
    private const UV_CASSETTE = [0.77, 0.53, 0.97, 0.72]; // Tile D2: Schwarze Diamant-Panzerkassetten mit Glutkern
    private const UV_CORE     = [0.05, 0.78, 0.45, 0.96]; // Tile E1: Reaktorkern / Singularität / Triebwerksglut
    private const UV_PLASMA   = [0.55, 0.78, 0.95, 0.96]; // Tile E2: Plasma-Lanzen / Energieklingen / Kanonenmündung

    // 5 dedizierte PBR-Material-Slots im glTF 2.0 (.glb)
    private const MAT_HULL     = 0; // Hauptpanzerung (Alabaster / Titan / Platin / Gunmetal)
    private const MAT_TRIM     = 1; // 24k Imperator-Gold / Platin-Zierleisten / Türme / Astrolabium-Ringe
    private const MAT_WING     = 2; // Leuchtende Kristall- / Seraphim- / Phönix-Schwingen
    private const MAT_OBSIDIAN = 3; // Tiefschwarze Obsidian-Diamantkassetten & Panzerglas
    private const MAT_CORE     = 4; // Selbstleuchtender Singularitätskern / Triebwerksplasma / Energieklingen

    public function run(): void
    {
        $outDir = public_path('shop/customer/gamification/images');
        $shipsArtDir = public_path('shop/gamification/ships');
        $alignedTexDir = public_path('shop/gamification/ships/aligned');

        if (!File::exists($outDir)) {
            File::makeDirectory($outDir, 0755, true);
        }
        if (!File::exists($alignedTexDir)) {
            File::makeDirectory($alignedTexDir, 0755, true);
        }

        $families = $this->getShipFamilyConfigs();

        foreach ($families as $familyKey => $fCfg) {
            $baseJpg = $shipsArtDir . '/' . $fCfg['base_jpg'];
            $basePngBytes = $this->createAlignedShipTexturePng($baseJpg, $fCfg, 'base');

            File::put($alignedTexDir . '/' . str_replace('.jpg', '.png', $fCfg['base_jpg']), $basePngBytes);

            foreach ($fCfg['skin_jpgs'] as $skinJpgName => $skinPaletteOverride) {
                if (is_int($skinJpgName)) {
                    $skinJpgName = $skinPaletteOverride;
                    $skinPaletteOverride = [];
                }
                $skinJpgPath = $shipsArtDir . '/' . $skinJpgName;
                $mergedCfg = array_replace_recursive($fCfg, $skinPaletteOverride);
                $skinPngBytes = $this->createAlignedShipTexturePng($skinJpgPath, $mergedCfg, $skinJpgName);
                File::put($alignedTexDir . '/' . str_replace('.jpg', '.png', $skinJpgName), $skinPngBytes);
            }

            foreach ($fCfg['variants'] as $glbFilename => $variantCfg) {
                $subMeshes = $this->buildSculptedShipMesh($familyKey, $fCfg, $variantCfg);
                $glbBinary = $this->buildMultiMaterialGlb(
                    $subMeshes,
                    $basePngBytes,
                    pathinfo($glbFilename, PATHINFO_FILENAME),
                    $fCfg
                );
                File::put($outDir . '/' . $glbFilename, $glbBinary);
            }
        }

        if ($this->command) {
            $this->command->info('Spaceship3DGlbSeeder: Alle 17 Multi-Material PBR 3D-Raumschiff-Modelle (.glb) + 20 1:1 Texturen erfolgreich generiert!');
        }
    }

    private function getShipFamilyConfigs(): array
    {
        return [
            'c_glider' => [
                'base_jpg'   => 'ship_sterngleiter_base.jpg',
                'metallic'   => 0.48,
                'roughness'  => 0.30,
                'hull_rgb'   => [192, 202, 218], // Gebürstetes Titan-Silber
                'hull2_rgb'  => [104, 116, 138], // Dunklerer Stahl-Schatten
                'trim_rgb'   => [238, 182, 68],  // 24k Manufaktur-Gold
                'wing_rgb'   => [168, 180, 202], // Silberne Flügelplatten
                'facet_rgb'  => [242, 188, 78],  // Goldene Flügelkanten
                'canopy_rgb' => [24, 188, 238],  // Cyan HUD-Kanzel
                'core_rgb'   => [56, 216, 255],  // Ionen-Blau
                'cass_rgb'   => [28, 36, 52],
                'wing_style' => 'mechanical',
                'skin_jpgs'  => [
                    'ship_sterngleiter_skin_prism.jpg' => [
                        'hull_rgb'   => [215, 232, 250],
                        'hull2_rgb'  => [115, 155, 208],
                        'trim_rgb'   => [56, 189, 248],
                        'wing_rgb'   => [185, 218, 248],
                        'facet_rgb'  => [168, 85, 247],
                        'canopy_rgb' => [103, 232, 249],
                        'core_rgb'   => [34, 211, 238],
                    ],
                    'ship_sterngleiter_skin_obsidian.jpg' => [
                        'hull_rgb'   => [38, 40, 50],
                        'hull2_rgb'  => [18, 20, 26],
                        'trim_rgb'   => [239, 68, 68],
                        'wing_rgb'   => [32, 34, 44],
                        'facet_rgb'  => [249, 115, 22],
                        'canopy_rgb' => [244, 63, 94],
                        'core_rgb'   => [251, 113, 133],
                    ],
                    'ship_sterngleiter_skin_manufaktur.jpg' => [
                        'hull_rgb'   => [244, 238, 222],
                        'hull2_rgb'  => [188, 164, 118],
                        'trim_rgb'   => [245, 192, 68],
                        'wing_rgb'   => [234, 220, 192],
                        'facet_rgb'  => [217, 155, 38],
                        'canopy_rgb' => [52, 211, 153],
                        'core_rgb'   => [250, 204, 21],
                    ],
                ],
                'variants' => [
                    'funki_rocket.glb'        => ['rank' => 1, 'span' => 1.00, 'thrusters' => 2, 'cannons' => 2],
                    'ship_c1_star_glider.glb' => ['rank' => 1, 'span' => 1.00, 'thrusters' => 2, 'cannons' => 2],
                    'ship_c2_star_glider.glb' => ['rank' => 2, 'span' => 1.06, 'thrusters' => 2, 'cannons' => 4],
                    'ship_c3_star_glider.glb' => ['rank' => 3, 'span' => 1.12, 'thrusters' => 4, 'cannons' => 4],
                ],
            ],

            'b_crystal' => [
                'base_jpg'   => 'ship_kristalljaeger_base.jpg',
                'metallic'   => 0.52,
                'roughness'  => 0.22,
                'hull_rgb'   => [198, 216, 226], // Platin-Silberner Kristallrahmen
                'hull2_rgb'  => [86, 118, 132],
                'trim_rgb'   => [224, 236, 245], // Poliertes Silber-Platin
                'wing_rgb'   => [18, 188, 164],  // Smaragd-Türkis Kristall
                'facet_rgb'  => [110, 248, 224], // Leuchtende Kristall-Kanten
                'canopy_rgb' => [40, 232, 202],  // Smaragd-Prisma-Kanzel
                'core_rgb'   => [52, 248, 200],  // Grünes Kristall-Laserplasma
                'cass_rgb'   => [14, 118, 106],
                'wing_style' => 'crystal',
                'skin_jpgs'  => [
                    'ship_kristalljaeger_skin_frost.jpg' => [
                        'hull_rgb'   => [220, 238, 252],
                        'hull2_rgb'  => [115, 175, 222],
                        'trim_rgb'   => [186, 230, 253],
                        'wing_rgb'   => [56, 189, 248],
                        'facet_rgb'  => [224, 242, 254],
                        'canopy_rgb' => [125, 211, 252],
                        'core_rgb'   => [103, 232, 249],
                        'cass_rgb'   => [14, 116, 144],
                    ],
                    'ship_kristalljaeger_skin_void.jpg' => [
                        'hull_rgb'   => [54, 38, 78],
                        'hull2_rgb'  => [24, 16, 42],
                        'trim_rgb'   => [192, 132, 252],
                        'wing_rgb'   => [126, 34, 206],
                        'facet_rgb'  => [232, 121, 249],
                        'canopy_rgb' => [217, 70, 239],
                        'core_rgb'   => [240, 171, 252],
                        'cass_rgb'   => [88, 28, 135],
                    ],
                    'ship_kristalljaeger_skin_solar.jpg' => [
                        'hull_rgb'   => [242, 214, 148],
                        'hull2_rgb'  => [168, 114, 34],
                        'trim_rgb'   => [253, 224, 71],
                        'wing_rgb'   => [245, 158, 11],
                        'facet_rgb'  => [254, 240, 138],
                        'canopy_rgb' => [251, 191, 36],
                        'core_rgb'   => [254, 240, 138],
                        'cass_rgb'   => [180, 83, 9],
                    ],
                ],
                'variants' => [
                    'ship_b1_crystal.glb' => ['rank' => 1, 'span' => 1.00, 'lances' => 4, 'thrusters' => 2],
                    'ship_b2_crystal.glb' => ['rank' => 2, 'span' => 1.06, 'lances' => 4, 'thrusters' => 2],
                    'ship_b3_crystal.glb' => ['rank' => 3, 'span' => 1.12, 'lances' => 6, 'thrusters' => 3],
                ],
            ],

            'a_phantom' => [
                'base_jpg'   => 'ship_phantom_base.jpg',
                'metallic'   => 0.58,
                'roughness'  => 0.24,
                'hull_rgb'   => [64, 58, 78],    // Dunkles Stealth-Titan
                'hull2_rgb'  => [26, 22, 36],    // Tiefes Obsidian-Violett
                'trim_rgb'   => [192, 132, 252], // Neon-Amethyst Energieleisten
                'wing_rgb'   => [48, 42, 62],    // Gepanzerte Schatten-Schwingen
                'facet_rgb'  => [216, 115, 255], // Violettes Plasma-Blitzgeflecht
                'canopy_rgb' => [147, 51, 234],  // Facettierte Dunkelviolett-Kanzel
                'core_rgb'   => [232, 121, 249], // Brennende Magenta-Energieklingen
                'cass_rgb'   => [38, 16, 62],
                'wing_style' => 'stealth',
                'skin_jpgs'  => [
                    'ship_phantom_skin_cyber.jpg' => [
                        'hull_rgb'   => [34, 56, 82],
                        'hull2_rgb'  => [16, 26, 42],
                        'trim_rgb'   => [34, 211, 238],
                        'wing_rgb'   => [24, 42, 66],
                        'facet_rgb'  => [56, 189, 248],
                        'canopy_rgb' => [6, 182, 212],
                        'core_rgb'   => [103, 232, 249],
                    ],
                    'ship_phantom_skin_hellfire.jpg' => [
                        'hull_rgb'   => [48, 24, 28],
                        'hull2_rgb'  => [20, 10, 12],
                        'trim_rgb'   => [249, 115, 22],
                        'wing_rgb'   => [38, 18, 22],
                        'facet_rgb'  => [239, 68, 68],
                        'canopy_rgb' => [225, 29, 72],
                        'core_rgb'   => [251, 146, 60],
                    ],
                    'ship_phantom_skin_celestial.jpg' => [
                        'hull_rgb'   => [232, 224, 242],
                        'hull2_rgb'  => [152, 136, 178],
                        'trim_rgb'   => [250, 204, 21],
                        'wing_rgb'   => [216, 206, 234],
                        'facet_rgb'  => [192, 132, 252],
                        'canopy_rgb' => [168, 85, 247],
                        'core_rgb'   => [253, 224, 71],
                    ],
                ],
                'variants' => [
                    'ship_a1_phantom.glb' => ['rank' => 1, 'span' => 1.00, 'thrusters' => 2],
                    'ship_a2_phantom.glb' => ['rank' => 2, 'span' => 1.06, 'thrusters' => 3],
                    'ship_a3_phantom.glb' => ['rank' => 3, 'span' => 1.12, 'thrusters' => 4],
                ],
            ],

            's_aurelia' => [
                'base_jpg'   => 'ship_aurelia_base.jpg',
                'metallic'   => 0.62,
                'roughness'  => 0.22,
                'hull_rgb'   => [218, 168, 64],  // 24k Aurelia-Sonnengold
                'hull2_rgb'  => [128, 84, 22],   // Warmer Bronze-Gold-Schatten
                'trim_rgb'   => [252, 218, 86],  // Poliertes Hochglanz-Gold
                'wing_rgb'   => [244, 194, 72],  // Goldene Phönix-Federschwingen
                'facet_rgb'  => [255, 242, 158], // Gleißende Sonnenfeder-Spitzen
                'canopy_rgb' => [48, 222, 252],  // Türkis-Cyan Sternen-Bullaugen
                'core_rgb'   => [255, 132, 20],  // Geschmolzener Sonnen-Singularitätskern
                'cass_rgb'   => [52, 32, 14],
                'wing_style' => 'feathers',
                'skin_jpgs'  => [
                    'ship_aurelia_skin_archangel.jpg' => [
                        'hull_rgb'   => [244, 238, 224],
                        'hull2_rgb'  => [188, 168, 122],
                        'trim_rgb'   => [253, 224, 71],
                        'wing_rgb'   => [252, 244, 188],
                        'facet_rgb'  => [255, 255, 255],
                        'canopy_rgb' => [103, 232, 249],
                        'core_rgb'   => [250, 204, 21],
                    ],
                    'ship_aurelia_skin_bloodmoon.jpg' => [
                        'hull_rgb'   => [88, 22, 34],
                        'hull2_rgb'  => [38, 8, 16],
                        'trim_rgb'   => [245, 158, 11],
                        'wing_rgb'   => [178, 26, 46],
                        'facet_rgb'  => [251, 113, 133],
                        'canopy_rgb' => [244, 63, 94],
                        'core_rgb'   => [239, 68, 68],
                    ],
                    'ship_aurelia_skin_supernova.jpg' => [
                        'hull_rgb'   => [242, 152, 12],
                        'hull2_rgb'  => [138, 58, 12],
                        'trim_rgb'   => [254, 240, 138],
                        'wing_rgb'   => [251, 191, 36],
                        'facet_rgb'  => [125, 211, 252],
                        'canopy_rgb' => [56, 189, 248],
                        'core_rgb'   => [254, 249, 195],
                    ],
                ],
                'variants' => [
                    'ship_s1_gold_phoenix.glb' => ['rank' => 1, 'span' => 1.00, 'feathers' => 7, 'thrusters' => 3],
                    'ship_s2_gold_phoenix.glb' => ['rank' => 2, 'span' => 1.06, 'feathers' => 8, 'thrusters' => 4],
                    'ship_s3_gold_phoenix.glb' => ['rank' => 3, 'span' => 1.12, 'feathers' => 9, 'thrusters' => 5],
                ],
            ],

            's_zion' => [
                'base_jpg'   => 'ship_zion_base.jpg',
                'metallic'   => 0.58,
                'roughness'  => 0.26,
                'hull_rgb'   => [82, 90, 104],   // Schwerer Dreadnought-Geschützstahl
                'hull2_rgb'  => [38, 42, 50],    // Dunkler Panzerrumpf
                'trim_rgb'   => [214, 168, 68],  // Massives Bronze-Gold
                'wing_rgb'   => [68, 76, 88],    // Gepanzerte Seiten-Ausleger
                'facet_rgb'  => [198, 154, 62],  // Goldene Panzerspanten
                'canopy_rgb' => [16, 225, 145],  // Smaragd-Grüne Energierinne
                'core_rgb'   => [34, 245, 158],  // Brennender Smaragd-Fusionsreaktor
                'cass_rgb'   => [16, 85, 64],
                'wing_style' => 'dreadnought',
                'skin_jpgs'  => [
                    'ship_zion_skin_obsidian.jpg' => [
                        'hull_rgb'   => [34, 36, 44],
                        'hull2_rgb'  => [14, 16, 22],
                        'trim_rgb'   => [239, 68, 68],
                        'wing_rgb'   => [26, 28, 36],
                        'facet_rgb'  => [249, 115, 22],
                        'canopy_rgb' => [244, 63, 94],
                        'core_rgb'   => [251, 113, 133],
                        'cass_rgb'   => [155, 22, 22],
                    ],
                    'ship_zion_skin_astral.jpg' => [
                        'hull_rgb'   => [188, 208, 238],
                        'hull2_rgb'  => [88, 118, 162],
                        'trim_rgb'   => [250, 204, 21],
                        'wing_rgb'   => [158, 186, 224],
                        'facet_rgb'  => [56, 189, 248],
                        'canopy_rgb' => [34, 211, 238],
                        'core_rgb'   => [103, 232, 249],
                        'cass_rgb'   => [2, 112, 172],
                    ],
                ],
                'variants' => [
                    'ship_s1_zion_breaker.glb' => ['rank' => 1, 'span' => 1.00, 'thrusters' => 3],
                    'ship_s2_zion_breaker.glb' => ['rank' => 2, 'span' => 1.05, 'thrusters' => 4],
                    'ship_s3_zion_breaker.glb' => ['rank' => 3, 'span' => 1.10, 'thrusters' => 5],
                ],
            ],

            'ss_majesty' => [
                'base_jpg'   => 'ship_majestaet_ss_base.jpg',
                'metallic'   => 0.42,
                'roughness'  => 0.24,
                'hull_rgb'   => [238, 232, 222], // Kaiserlicher Alabaster-Marmor
                'hull2_rgb'  => [164, 154, 140], // Edler Marmor-Schatten
                'trim_rgb'   => [246, 196, 68],  // 24k Imperator-Gold & Runenringe
                'wing_rgb'   => [250, 218, 112], // Himmlische Gold-Kristall-Seraphim-Schwingen
                'facet_rgb'  => [255, 248, 205], // Gleißende Seraphim-Lichtkanten
                'canopy_rgb' => [110, 218, 255], // Himmlisches Saphir-Licht
                'core_rgb'   => [255, 226, 96],  // Göttliche Singularitäts-Sonne
                'cass_rgb'   => [22, 24, 34],    // Tiefschwarze Obsidian-Diamant-Kassetten
                'wing_style' => 'seraphim',
                'skin_jpgs'  => [],
                'variants'   => [
                    'ship_ss_divine_majesty.glb' => ['rank' => 4, 'span' => 1.08, 'thrusters' => 5],
                ],
            ],
        ];
    }

    /**
     * Erzeugt den gestochen scharfen 512x512 PBR-Texturatlas (OHNE Weltraum-Hintergrund,
     * OHNE Spiegel-Naht, 100 % abgestimmt auf die 5 PBR-Materialgruppen & das jeweilige Skin-Artwork).
     */
    private function createAlignedShipTexturePng(string $jpgPath, array $fCfg, string $variantTag = 'base'): string
    {
        $size = 512;
        $dst = imagecreatetruecolor($size, $size);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        $src = File::exists($jpgPath) ? @imagecreatefromjpeg($jpgPath) : null;
        $srcW = $src ? imagesx($src) : 1;
        $srcH = $src ? imagesy($src) : 1;

        [$hullR, $hullG, $hullB]     = $fCfg['hull_rgb'];
        [$hull2R, $hull2G, $hull2B]  = $fCfg['hull2_rgb'];
        [$trimR, $trimG, $trimB]     = $fCfg['trim_rgb'];
        [$wingR, $wingG, $wingB]     = $fCfg['wing_rgb'];
        [$facetR, $facetG, $facetB]  = $fCfg['facet_rgb'];
        [$canR, $canG, $canB]        = $fCfg['canopy_rgb'];
        [$coreR, $coreG, $coreB]     = $fCfg['core_rgb'];
        [$cassR, $cassG, $cassB]     = $fCfg['cass_rgb'] ?? [24, 28, 38];
        $wingStyle = $fCfg['wing_style'] ?? 'mechanical';

        for ($py = 0; $py < $size; $py++) {
            for ($px = 0; $px < $size; $px++) {
                $r = $hullR;
                $g = $hullG;
                $b = $hullB;

                if ($py < 256 && $px < 256) {
                    // =========================================================
                    // TILE A (0..255, 0..255): HAUPT-RUMPFPANZERUNG (GOTISCHE / MANUFAKTUR PLATTEN)
                    // =========================================================
                    $u = ($px - 128.0) / 128.0; // -1..+1
                    $absU = abs($u);
                    $v = $py / 255.0;           // 0..1

                    // Sanfte 3D-Wölbung + Ambient Occlusion zu den Flanken
                    $cyl = cos($absU * 1.15);
                    $mix = max(0.0, min(1.0, 0.22 + 0.78 * $cyl));

                    $r = $hull2R * (1.0 - $mix) + $hullR * $mix;
                    $g = $hull2G * (1.0 - $mix) + $hullG * $mix;
                    $b = $hull2B * (1.0 - $mix) + $hullB * $mix;

                    // Edle 24k Gold-Filigran-Einfassung entlang der Rumpfkanten
                    if (($absU >= 0.68 && $absU <= 0.76) || $absU <= 0.045) {
                        $tMix = 0.88;
                        $r = $r * (1.0 - $tMix) + $trimR * $tMix;
                        $g = $g * (1.0 - $tMix) + $trimG * $tMix;
                        $b = $b * (1.0 - $tMix) + $trimB * $tMix;
                    }

                    // Gotische / Sci-Fi Kassetten-Fugen mit Ambient Occlusion
                    $seamY = min($py % 48, 48 - ($py % 48));
                    if ($seamY <= 1 || abs($absU - 0.38) < 0.012) {
                        $r *= 0.68;
                        $g *= 0.68;
                        $b *= 0.72;
                    } elseif ($seamY === 2) {
                        $r = min(255.0, $r * 1.10);
                        $g = min(255.0, $g * 1.10);
                        $b = min(255.0, $b * 1.10);
                    }

                    // Subtiles Detail-Transfer aus dem zentralen Schiffsrumpf des Artworks
                    if ($src) {
                        $sx = (int) round($srcW * (0.35 + 0.30 * ($px / 255.0)));
                        $sy = (int) round($srcH * (0.35 + 0.30 * $v));
                        $rgb = imagecolorat($src, max(0, min($srcW - 1, $sx)), max(0, min($srcH - 1, $sy)));
                        $sr = ($rgb >> 16) & 0xFF;
                        $sg = ($rgb >> 8) & 0xFF;
                        $sb = $rgb & 0xFF;
                        $sLuma = (0.299 * $sr + 0.587 * $sg + 0.114 * $sb) / 255.0;
                        if ($sLuma > 0.18) {
                            $detailDelta = ($sLuma - 0.50) * 28.0;
                            $r += $detailDelta;
                            $g += $detailDelta;
                            $b += $detailDelta;
                        }
                    }
                } elseif ($py < 256 && $px >= 256) {
                    // =========================================================
                    // TILE B (256..511, 0..255): FLÜGEL / FEDERN / KRISTALLFACETTEN
                    // =========================================================
                    $lx = ($px - 256.0) / 255.0; // 0..1
                    $ly = $py / 255.0;           // 0..1
                    $uSym = abs($lx - 0.5) * 2.0; // 0 in der Mitte, 1 am Rand

                    if ($wingStyle === 'feathers' || $wingStyle === 'seraphim') {
                        $quill = exp(-pow($uSym / 0.14, 2.0));
                        $barb = 0.5 + 0.5 * cos(($ly * 22.0 - $uSym * 12.0) * M_PI);
                        $tipGlow = pow(max(0.0, 1.0 - $ly), 1.5);

                        $r = $wingR * (0.75 + 0.25 * $barb) + ($facetR - $wingR) * max($quill, $tipGlow * 0.80);
                        $g = $wingG * (0.75 + 0.25 * $barb) + ($facetG - $wingG) * max($quill, $tipGlow * 0.80);
                        $b = $wingB * (0.75 + 0.25 * $barb) + ($facetB - $wingB) * max($quill, $tipGlow * 0.80);

                        if ($uSym > 0.84) {
                            $r = $trimR;
                            $g = $trimG;
                            $b = $trimB;
                        }
                    } elseif ($wingStyle === 'crystal') {
                        $diag1 = abs(fmod($lx * 4.0 + $ly * 4.0, 1.0) - 0.5) * 2.0;
                        $diag2 = abs(fmod($lx * 4.0 - $ly * 4.0 + 4.0, 1.0) - 0.5) * 2.0;
                        $facet = max($diag1, $diag2);
                        $caustic = pow($facet, 3.0);

                        $r = $wingR * 0.70 + $facetR * 0.30 + 150.0 * $caustic;
                        $g = $wingG * 0.70 + $facetG * 0.30 + 185.0 * $caustic;
                        $b = $wingB * 0.70 + $facetB * 0.30 + 195.0 * $caustic;

                        if ($uSym > 0.82 || $ly < 0.08 || $ly > 0.92) {
                            $r = $trimR;
                            $g = $trimG;
                            $b = $trimB;
                        }
                    } elseif ($wingStyle === 'stealth') {
                        $vein = exp(-pow(abs($uSym - 0.68) / 0.06, 2.0)) + exp(-pow(abs($ly - 0.28) / 0.04, 2.0)) * 0.65;
                        $vein = min(1.0, $vein);
                        $r = $wingR * (1.0 - $vein) + $facetR * $vein;
                        $g = $wingG * (1.0 - $vein) + $facetG * $vein;
                        $b = $wingB * (1.0 - $vein) + $facetB * $vein;
                    } else {
                        $panel = (($px % 42) < 2 || ($py % 42) < 2) ? 0.68 : 1.0;
                        $grad = 0.80 + 0.28 * (1.0 - $uSym);
                        $r = $wingR * $grad * $panel;
                        $g = $wingG * $grad * $panel;
                        $b = $wingB * $grad * $panel;
                        if ($uSym > 0.78 || $ly < 0.12) {
                            $r = $trimR * $panel;
                            $g = $trimG * $panel;
                            $b = $trimB * $panel;
                        }
                    }
                } elseif ($py >= 256 && $py < 384 && $px < 256) {
                    // =========================================================
                    // TILE C (0..255, 256..383): 24K GOLD / PLATIN-ZIERLEISTEN & RUNENRINGE
                    // =========================================================
                    $ly = ($py - 256.0) / 127.0; // 0..1
                    $sheen = cos(($ly - 0.5) * M_PI);
                    $r = $trimR * (0.68 + 0.38 * $sheen);
                    $g = $trimG * (0.68 + 0.38 * $sheen);
                    $b = $trimB * (0.68 + 0.38 * $sheen);

                    // Eingravierte leuchtende Astrolabium-Runen in der Mitte des Rings
                    if (abs($ly - 0.5) < 0.18 && (($px % 16) >= 4 && ($px % 16) <= 11)) {
                        $runePattern = ((($px >> 2) ^ ($py >> 2)) & 1);
                        if ($runePattern) {
                            $r = min(255.0, $coreR * 0.75 + 255.0 * 0.25);
                            $g = min(255.0, $coreG * 0.75 + 255.0 * 0.25);
                            $b = min(255.0, $coreB * 0.75 + 255.0 * 0.25);
                        }
                    }
                } elseif ($py >= 256 && $py < 384 && $px >= 256 && $px < 384) {
                    // =========================================================
                    // TILE D1 (256..383, 256..383): COCKPIT-GLASKANZEL & HOLOGRAMM-HUD
                    // =========================================================
                    $ux = ($px - 320.0) / 64.0;
                    $uy = ($py - 320.0) / 64.0;
                    $rad = hypot($ux, $uy);

                    $glassSpec = exp(-pow(($ux + 0.28) / 0.32, 2.0) - pow(($uy + 0.28) / 0.32, 2.0));
                    $hudRing = (abs($rad - 0.42) < 0.045 || abs($rad - 0.68) < 0.035 || abs($ux) < 0.03) ? 1.0 : 0.0;

                    $r = $canR * (0.28 + 0.62 * $hudRing) + 255.0 * ($glassSpec * 0.45 + $hudRing * 0.22);
                    $g = $canG * (0.28 + 0.62 * $hudRing) + 255.0 * ($glassSpec * 0.45 + $hudRing * 0.22);
                    $b = $canB * (0.28 + 0.62 * $hudRing) + 255.0 * ($glassSpec * 0.45 + $hudRing * 0.22);

                    if (abs($ux) > 0.84 || abs($uy) > 0.84) {
                        $r = $trimR;
                        $g = $trimG;
                        $b = $trimB;
                    }
                } elseif ($py >= 256 && $py < 384 && $px >= 384) {
                    // =========================================================
                    // TILE D2 (384..511, 256..383): TIEFSCHWARZE OBSIDIAN-DIAMANTKASSETTEN
                    // =========================================================
                    $ux = abs(($px - 448.0) / 64.0);
                    $uy = abs(($py - 320.0) / 64.0);
                    $diamondDist = $ux + $uy;

                    if ($diamondDist < 0.32) {
                        $tCore = 1.0 - ($diamondDist / 0.32);
                        $r = $coreR + (255.0 - $coreR) * $tCore * 0.70;
                        $g = $coreG + (255.0 - $coreG) * $tCore * 0.70;
                        $b = $coreB + (255.0 - $coreB) * $tCore * 0.70;
                    } elseif ($diamondDist > 0.88 || $ux > 0.88 || $uy > 0.88) {
                        $r = $trimR;
                        $g = $trimG;
                        $b = $trimB;
                    } else {
                        $facetShade = ($px > 448 ? 1.25 : 0.75) * ($py < 320 ? 1.20 : 0.80);
                        $r = $cassR * $facetShade;
                        $g = $cassG * $facetShade;
                        $b = $cassB * $facetShade;
                    }
                } else {
                    // =========================================================
                    // TILE E (0..511, 384..511): REAKTORKERN, TRIEBWERKSGLUT & PLASMA-LANZEN
                    // =========================================================
                    if ($px < 256) {
                        $ux = ($px - 128.0) / 112.0;
                        $uy = ($py - 448.0) / 56.0;
                        $rad = min(1.0, hypot($ux, $uy));
                        $hot = pow(1.0 - $rad, 1.5);
                        $r = $coreR * (1.0 - $hot * 0.55) + 255.0 * ($hot * 0.75);
                        $g = $coreG * (1.0 - $hot * 0.55) + 255.0 * ($hot * 0.75);
                        $b = $coreB * (1.0 - $hot * 0.55) + 255.0 * ($hot * 0.75);
                    } else {
                        $uy = abs(($py - 448.0) / 56.0);
                        $beam = pow(max(0.0, 1.0 - $uy), 1.4);
                        $r = $coreR * 0.55 + $facetR * 0.45 + 255.0 * ($beam * 0.65);
                        $g = $coreG * 0.55 + $facetG * 0.45 + 255.0 * ($beam * 0.65);
                        $b = $coreB * 0.55 + $facetB * 0.45 + 255.0 * ($beam * 0.65);
                    }
                }

                $col = imagecolorallocatealpha(
                    $dst,
                    max(0, min(255, (int) round($r))),
                    max(0, min(255, (int) round($g))),
                    max(0, min(255, (int) round($b))),
                    0
                );
                imagesetpixel($dst, $px, $py, $col);
            }
        }

        if ($src) {
            imagedestroy($src);
        }

        ob_start();
        imagepng($dst, null, 6);
        $pngBytes = (string) ob_get_clean();
        imagedestroy($dst);

        return $pngBytes;
    }

    /**
     * Initialisiert 5 getrennte PBR-Submeshes (Hull, GoldTrim, CrystalWings, ObsidianCassettes, CoreGlow).
     */
    private function createEmptySubMeshes(): array
    {
        $subs = [];
        for ($i = 0; $i < 5; $i++) {
            $subs[$i] = [
                'positions' => [],
                'normals'   => [],
                'uvs'       => [],
                'indices'   => [],
            ];
        }
        return $subs;
    }

    private function buildSculptedShipMesh(string $familyKey, array $fCfg, array $variantCfg): array
    {
        $s = $this->createEmptySubMeshes();

        $span = (float) ($variantCfg['span'] ?? 1.0);
        $rank = (int) ($variantCfg['rank'] ?? 1);

        if ($familyKey === 'c_glider') {
            $this->buildStarGlider3D($s, $span, $rank, $variantCfg);
        } elseif ($familyKey === 'b_crystal') {
            $this->buildCrystalFighter3D($s, $span, $rank, $variantCfg);
        } elseif ($familyKey === 'a_phantom') {
            $this->buildPhantomStealth3D($s, $span, $rank, $variantCfg);
        } elseif ($familyKey === 's_aurelia') {
            $this->buildAureliaPhoenix3D($s, $span, $rank, $variantCfg);
        } elseif ($familyKey === 's_zion') {
            $this->buildZionBreaker3D($s, $span, $rank, $variantCfg);
        } elseif ($familyKey === 'ss_majesty') {
            $this->buildDivineMajesty3D($s, $span, $rank, $variantCfg);
        }

        return $s;
    }

    // =========================================================================
    // 1. KLASSE C: STERNGLEITER C-1 BIS C-3 (UND FUNKI_ROCKET)
    // =========================================================================
    private function buildStarGlider3D(array &$s, float $span, int $rank, array $vCfg): void
    {
        // 1. Zentraler Silber-Titan-Rumpf (MAT_HULL)
        $this->addLoftedBody($s[self::MAT_HULL], [
            ['y' => -1.38, 'z' =>  0.00, 'rx' => 0.34, 'rzTop' => 0.20, 'rzBot' => 0.16, 'v' => 0.00],
            ['y' => -0.85, 'z' =>  0.02, 'rx' => 0.44, 'rzTop' => 0.28, 'rzBot' => 0.20, 'v' => 0.22],
            ['y' => -0.15, 'z' =>  0.03, 'rx' => 0.46, 'rzTop' => 0.32, 'rzBot' => 0.22, 'v' => 0.48],
            ['y' =>  0.55, 'z' =>  0.01, 'rx' => 0.36, 'rzTop' => 0.26, 'rzBot' => 0.18, 'v' => 0.74],
            ['y' =>  1.25, 'z' => -0.02, 'rx' => 0.22, 'rzTop' => 0.16, 'rzBot' => 0.12, 'v' => 0.92],
            ['y' =>  1.62, 'z' => -0.03, 'rx' => 0.08, 'rzTop' => 0.07, 'rzBot' => 0.06, 'v' => 1.00],
        ], self::UV_HULL);

        // Erhabener 24k Gold-Rumpfrücken & Bugspitzen (MAT_TRIM)
        $this->addBeamBox($s[self::MAT_TRIM], [0.0, -1.25, 0.22], [0.0, -0.45, 0.28], 0.09, 0.07, 0.07, 0.05, self::UV_TRIM);
        foreach ([-1.0, 1.0] as $side) {
            $this->addBeamBox(
                $s[self::MAT_TRIM],
                [$side * 0.18, 0.95, -0.01],
                [$side * 0.09, 1.88, -0.04],
                0.13, 0.11, 0.04, 0.04,
                self::UV_TRIM
            );
            // Goldene Seiten-Zierleisten entlang der Rumpfflanke
            $this->addBeamBox(
                $s[self::MAT_TRIM],
                [$side * 0.42, -0.95, 0.02],
                [$side * 0.28,  0.95, 0.00],
                0.05, 0.05, 0.035, 0.035,
                self::UV_TRIM
            );
        }

        // Leuchtender Cyan-Sensorkern zwischen den Bugspitzen (MAT_CORE)
        $this->addDiamondCrystal($s[self::MAT_CORE], 0.0, 1.35, 0.00, 0.09, 0.26, 0.08, self::UV_CORE);

        // 3. Erhabene Cyan-HUD-Glaskanzel (MAT_OBSIDIAN + Goldrahmen MAT_TRIM)
        $this->addLoftedBody($s[self::MAT_OBSIDIAN], [
            ['y' => -0.45, 'z' => 0.22, 'rx' => 0.16, 'rzTop' => 0.10, 'rzBot' => 0.04, 'v' => 0.05],
            ['y' => -0.05, 'z' => 0.26, 'rx' => 0.24, 'rzTop' => 0.18, 'rzBot' => 0.05, 'v' => 0.45],
            ['y' =>  0.48, 'z' => 0.22, 'rx' => 0.20, 'rzTop' => 0.15, 'rzBot' => 0.04, 'v' => 0.80],
            ['y' =>  0.88, 'z' => 0.14, 'rx' => 0.08, 'rzTop' => 0.05, 'rzBot' => 0.02, 'v' => 0.98],
        ], self::UV_CANOPY);

        // 4. Gepfeilte Titan-Hauptflügel (MAT_WING) + massive 24k Gold-Vorderkanten (MAT_TRIM)
        $this->addSweptWingPair($s[self::MAT_WING], [
            ['x' => 0.34,          'yL' =>  0.45, 'yT' => -0.95, 'z' =>  0.00, 'th' => 0.13],
            ['x' => 0.95 * $span,  'yL' =>  0.02, 'yT' => -1.02, 'z' => -0.03, 'th' => 0.09],
            ['x' => 1.62 * $span,  'yL' => -0.42, 'yT' => -1.16, 'z' => -0.06, 'th' => 0.05],
        ], self::UV_WING, self::UV_TRIM);

        foreach ([-1.0, 1.0] as $side) {
            // Goldene Flügel-Vorderkanten-Panzerung
            $this->addBeamBox(
                $s[self::MAT_TRIM],
                [$side * 0.36, 0.46, 0.00],
                [$side * 1.62 * $span, -0.42, -0.06],
                0.08, 0.07, 0.045, 0.04,
                self::UV_TRIM
            );
            // Schräge Wingtip-Stabilisator-Finnen
            $wx = $side * 1.60 * $span;
            $this->addBeamBox(
                $s[self::MAT_TRIM],
                [$wx, -0.78, -0.10],
                [$wx + $side * 0.12, -0.98, 0.36],
                0.04, 0.42, 0.025, 0.24,
                self::UV_TRIM
            );
            // Vorwärts gerichtete Wingtip-Lanze (MAT_CORE)
            $this->addBeamBox(
                $s[self::MAT_CORE],
                [$wx, -0.95, -0.06],
                [$wx, -0.18, -0.06],
                0.045, 0.045, 0.018, 0.018,
                self::UV_PLASMA
            );
            // Unterflügel-Plasmablaster
            $this->addBeamBox(
                $s[self::MAT_CORE],
                [$side * 0.88 * $span, -0.25, -0.09],
                [$side * 0.88 * $span,  0.42, -0.09],
                0.055, 0.055, 0.025, 0.025,
                self::UV_PLASMA
            );
            if ($rank >= 2) {
                $this->addBeamBox(
                    $s[self::MAT_CORE],
                    [$side * 1.22 * $span, -0.48, -0.09],
                    [$side * 1.22 * $span,  0.16, -0.09],
                    0.05, 0.05, 0.022, 0.022,
                    self::UV_PLASMA
                );
            }
        }

        // 7. Massive goldgefasste Zwillings-Ionen-Triebwerksgondeln
        foreach ([-1.0, 1.0] as $side) {
            $this->addThrusterNacelle($s, $side * 0.36, -1.48, 0.04, 0.22, 0.92);
            if ($rank >= 3) {
                $this->addThrusterNacelle($s, $side * 0.68, -1.36, 0.00, 0.16, 0.72);
            }
        }
    }

    // =========================================================================
    // 2. KLASSE B: KRISTALLJÄGER B-1 BIS B-3
    // =========================================================================
    private function buildCrystalFighter3D(array &$s, float $span, int $rank, array $vCfg): void
    {
        // 1. Zentraler Platin-Silber-Kristall-Rumpf (MAT_HULL)
        $this->addLoftedBody($s[self::MAT_HULL], [
            ['y' => -1.32, 'z' => 0.00, 'rx' => 0.30, 'rzTop' => 0.18, 'rzBot' => 0.14, 'v' => 0.00],
            ['y' => -0.55, 'z' => 0.02, 'rx' => 0.56, 'rzTop' => 0.34, 'rzBot' => 0.24, 'v' => 0.32],
            ['y' =>  0.25, 'z' => 0.03, 'rx' => 0.52, 'rzTop' => 0.36, 'rzBot' => 0.24, 'v' => 0.62],
            ['y' =>  1.05, 'z' => 0.01, 'rx' => 0.28, 'rzTop' => 0.20, 'rzBot' => 0.14, 'v' => 0.86],
            ['y' =>  1.72, 'z' => 0.00, 'rx' => 0.05, 'rzTop' => 0.05, 'rzBot' => 0.04, 'v' => 1.00],
        ], self::UV_HULL);

        // Polierte Silber-Platin-Rumpfspanten (MAT_TRIM)
        $this->addTorusRing($s[self::MAT_TRIM], 0.0, 0.08, 0.22, 0.42, 0.05, 0.0, 0.0, 0.0, self::UV_TRIM, 16, 8);

        // 2. Zentrales scharf facettiertes Smaragd-Kristall-Prisma auf dem Oberdeck (MAT_CORE + MAT_WING)
        $this->addDiamondCrystal($s[self::MAT_CORE], 0.0, 0.08, 0.28, 0.34, 0.64, 0.24, self::UV_CANOPY);

        // Seitliche scharf facettierte Kristall-Cluster auf dem Rumpf & den Flügeln (MAT_WING)
        foreach ([-1.0, 1.0] as $side) {
            $this->addDiamondCrystal($s[self::MAT_WING], $side * 0.38, -0.15, 0.16, 0.24, 0.52, 0.18, self::UV_WING);
            $this->addDiamondCrystal($s[self::MAT_WING], $side * 0.28,  0.58, 0.14, 0.20, 0.44, 0.15, self::UV_WING);
            // Große facettierte Kristallklingen als 3D-Schwingen
            $this->addBeamBox(
                $s[self::MAT_WING],
                [$side * 0.38,  0.42, 0.04],
                [$side * 1.82 * $span, -0.82, -0.04],
                0.42, 0.14, 0.12, 0.04,
                self::UV_WING
            );
            $this->addBeamBox(
                $s[self::MAT_WING],
                [$side * 0.42, -0.18, 0.02],
                [$side * 1.48 * $span, -1.28, -0.06],
                0.36, 0.12, 0.10, 0.03,
                self::UV_WING
            );
            // Massiver Platin-Silber-Kristallrahmen an der Flügelvorderkante (MAT_TRIM)
            $this->addBeamBox(
                $s[self::MAT_TRIM],
                [$side * 0.34,  0.58, 0.05],
                [$side * 1.84 * $span, -0.76, -0.03],
                0.09, 0.08, 0.04, 0.04,
                self::UV_TRIM
            );
        }

        // 4. Vier (bzw. sechs bei B-3) vorwärts ragende leuchtende Smaragd-Kristall-Lanzenkanonen (MAT_CORE)
        foreach ([-1.0, 1.0] as $side) {
            $this->addBeamBox(
                $s[self::MAT_CORE],
                [$side * 0.34, 0.45, -0.02],
                [$side * 0.42, 1.96, -0.02],
                0.085, 0.085, 0.018, 0.018,
                self::UV_PLASMA
            );
            $this->addBeamBox(
                $s[self::MAT_CORE],
                [$side * 0.62, 0.18, -0.04],
                [$side * 0.72, 1.72, -0.04],
                0.075, 0.075, 0.016, 0.016,
                self::UV_PLASMA
            );
            if ($rank >= 3) {
                $this->addBeamBox(
                    $s[self::MAT_CORE],
                    [$side * 0.92, -0.10, -0.05],
                    [$side * 1.02,  1.38, -0.05],
                    0.065, 0.065, 0.015, 0.015,
                    self::UV_PLASMA
                );
            }
        }

        // 5. Gepanzerte Kryo-Ionen-Triebwerke am Heck
        $this->addThrusterNacelle($s, 0.0, -1.45, 0.00, 0.24, 0.78);
        foreach ([-1.0, 1.0] as $side) {
            $this->addThrusterNacelle($s, $side * 0.34, -1.36, -0.02, 0.16, 0.68);
        }
    }

    // =========================================================================
    // 3. KLASSE A: PHANTOM A-1 BIS A-3 (STEALTH-INTERCEPTOR MIT PLASMA-KLINGEN)
    // =========================================================================
    private function buildPhantomStealth3D(array &$s, float $span, int $rank, array $vCfg): void
    {
        // 1. Kantiger Stealth-Obsidian-Rumpf (MAT_HULL)
        $this->addLoftedBody($s[self::MAT_HULL], [
            ['y' => -1.34, 'z' =>  0.00, 'rx' => 0.42, 'rzTop' => 0.20, 'rzBot' => 0.14, 'v' => 0.00],
            ['y' => -0.65, 'z' =>  0.02, 'rx' => 0.54, 'rzTop' => 0.32, 'rzBot' => 0.20, 'v' => 0.28],
            ['y' =>  0.15, 'z' =>  0.02, 'rx' => 0.50, 'rzTop' => 0.34, 'rzBot' => 0.20, 'v' => 0.58],
            ['y' =>  0.95, 'z' => -0.01, 'rx' => 0.34, 'rzTop' => 0.22, 'rzBot' => 0.14, 'v' => 0.85],
            ['y' =>  1.42, 'z' => -0.03, 'rx' => 0.16, 'rzTop' => 0.10, 'rzBot' => 0.08, 'v' => 1.00],
        ], self::UV_HULL);

        // 2. Aggressiver Doppelklauen-Stealth-Bug + leuchtende Amethyst-Energierippen
        foreach ([-1.0, 1.0] as $side) {
            $this->addBeamBox(
                $s[self::MAT_OBSIDIAN],
                [$side * 0.24, 0.85, -0.02],
                [$side * 0.13, 1.88, -0.05],
                0.15, 0.10, 0.03, 0.03,
                self::UV_HULL
            );
            $this->addBeamBox(
                $s[self::MAT_TRIM],
                [$side * 0.20, 0.92, 0.04],
                [$side * 0.12, 1.82, -0.02],
                0.045, 0.045, 0.02, 0.02,
                self::UV_TRIM
            );
        }

        // 3. Facettierte dunkelviolette Stealth-Cockpit-Kanzel (MAT_OBSIDIAN)
        $this->addDiamondCrystal($s[self::MAT_OBSIDIAN], 0.0, 0.18, 0.22, 0.26, 0.68, 0.18, self::UV_CANOPY);

        // 4. Geschwungene Stealth-Sichelflügel (MAT_WING)
        $this->addSweptWingPair($s[self::MAT_WING], [
            ['x' => 0.38,          'yL' =>  0.48, 'yT' => -0.88, 'z' =>  0.02, 'th' => 0.14],
            ['x' => 1.05 * $span,  'yL' =>  0.12, 'yT' => -0.82, 'z' =>  0.00, 'th' => 0.10],
            ['x' => 1.66 * $span,  'yL' =>  0.28, 'yT' => -0.72, 'z' => -0.04, 'th' => 0.07],
        ], self::UV_WING, self::UV_TRIM);

        // 5. Flügelspitzen-Waffengondeln mit langen brennenden violetten Plasma-Energieklingen nach vorn!
        foreach ([-1.0, 1.0] as $side) {
            $wx = $side * 1.66 * $span;
            $this->addBeamBox(
                $s[self::MAT_OBSIDIAN],
                [$wx, -0.78, -0.04],
                [$wx + $side * 0.08, 0.42, -0.02],
                0.15, 0.12, 0.08, 0.08,
                self::UV_HULL
            );
            // Brennende Plasma-Blitzklinge (MAT_CORE)
            $this->addBeamBox(
                $s[self::MAT_CORE],
                [$wx + $side * 0.08, 0.32, -0.02],
                [$wx + $side * 0.22, 1.95, -0.02],
                0.12, 0.055, 0.015, 0.015,
                self::UV_PLASMA
            );
        }

        // 6. Stealth-Vektordüsen am Heck
        $thrusterCount = (int) ($vCfg['thrusters'] ?? 2);
        foreach ([-1.0, 1.0] as $side) {
            $this->addThrusterNacelle($s, $side * 0.42, -1.42, 0.04, 0.20, 0.86);
        }
        if ($thrusterCount >= 3) {
            $this->addThrusterNacelle($s, 0.0, -1.48, 0.06, 0.18, 0.78);
        }
    }

    // =========================================================================
    // 4. KLASSE S: AURELIA PHÖNIX S-1 BIS S-3 (24K GOLD-PHÖNIX MIT SONNENKERN)
    // =========================================================================
    private function buildAureliaPhoenix3D(array &$s, float $span, int $rank, array $vCfg): void
    {
        // 1. Majestätisch geschwungener 24k Gold-Rumpf (MAT_HULL)
        $this->addLoftedBody($s[self::MAT_HULL], [
            ['y' => -1.62, 'z' => -0.04, 'rx' => 0.36, 'rzTop' => 0.22, 'rzBot' => 0.18, 'v' => 0.00],
            ['y' => -0.95, 'z' =>  0.00, 'rx' => 0.48, 'rzTop' => 0.30, 'rzBot' => 0.24, 'v' => 0.22],
            ['y' => -0.15, 'z' =>  0.04, 'rx' => 0.56, 'rzTop' => 0.36, 'rzBot' => 0.28, 'v' => 0.48],
            ['y' =>  0.68, 'z' =>  0.04, 'rx' => 0.48, 'rzTop' => 0.32, 'rzBot' => 0.24, 'v' => 0.74],
            ['y' =>  1.42, 'z' =>  0.01, 'rx' => 0.28, 'rzTop' => 0.20, 'rzBot' => 0.16, 'v' => 0.92],
            ['y' =>  1.96, 'z' => -0.02, 'rx' => 0.06, 'rzTop' => 0.05, 'rzBot' => 0.05, 'v' => 1.00],
        ], self::UV_HULL);

        // Leuchtende Türkis-Sternen-Bullaugen an beiden Rumpfflanken (MAT_CORE)
        foreach ([-1.0, 1.0] as $side) {
            foreach ([-0.65, -0.15, 0.35, 0.85] as $yPort) {
                $this->addDiamondCrystal($s[self::MAT_CORE], $side * 0.48, $yPort, 0.06, 0.06, 0.12, 0.06, self::UV_CANOPY);
            }
        }

        // 2. Zentraler brennender Sonnen-Singularitätskern (MAT_CORE) + 24k Goldfassung (MAT_TRIM)
        $this->addTorusRing($s[self::MAT_TRIM], 0.0, -0.12, 0.32, 0.42, 0.075, 0.0, 0.0, 0.0, self::UV_TRIM, 28, 10);
        $this->addDiamondCrystal($s[self::MAT_CORE], 0.0, -0.12, 0.34, 0.34, 0.34, 0.24, self::UV_CORE);

        // 3. Erhabene 3D-Phönix-Kopf-Skulptur mit Schnabel & Federkrone über dem Vorderdeck (MAT_TRIM)
        $this->addLoftedBody($s[self::MAT_TRIM], [
            ['y' => 0.32, 'z' => 0.28, 'rx' => 0.18, 'rzTop' => 0.16, 'rzBot' => 0.08, 'v' => 0.10],
            ['y' => 0.58, 'z' => 0.48, 'rx' => 0.14, 'rzTop' => 0.14, 'rzBot' => 0.10, 'v' => 0.45],
            ['y' => 0.88, 'z' => 0.56, 'rx' => 0.12, 'rzTop' => 0.12, 'rzBot' => 0.08, 'v' => 0.78],
            ['y' => 1.24, 'z' => 0.48, 'rx' => 0.03, 'rzTop' => 0.04, 'rzBot' => 0.03, 'v' => 0.98],
        ], self::UV_TRIM);

        // Phönix-Kopfkamm (3 nach hinten geschwungene Goldfedern)
        $this->addBeamBox($s[self::MAT_CORE], [0.0, 0.78, 0.62], [0.0, 0.28, 0.78], 0.055, 0.065, 0.015, 0.02, self::UV_WING);
        foreach ([-1.0, 1.0] as $side) {
            $this->addBeamBox($s[self::MAT_TRIM], [$side * 0.08, 0.76, 0.58], [$side * 0.18, 0.34, 0.72], 0.045, 0.055, 0.015, 0.02, self::UV_TRIM);
        }

        // 4. Mehrreihige 3D-skulptierte Goldene Phönix-Federschwingen (exakt wie ship_aurelia_base.jpg!)
        $featherCount = (int) ($vCfg['feathers'] ?? 7);
        // Geschwungener goldener Schulterbogen (MAT_TRIM)
        foreach ([-1.0, 1.0] as $side) {
            $this->addBeamBox(
                $s[self::MAT_TRIM],
                [$side * 0.40, 0.32, 0.14],
                [$side * 1.92 * $span, 0.88, 0.38],
                0.16, 0.14, 0.06, 0.05,
                self::UV_TRIM
            );
        }

        // Einzelne 3D-skulptierte Gold-Lichtfedern im Fächer (MAT_WING + leuchtende Spitzen MAT_CORE)
        for ($fi = 0; $fi < $featherCount; $fi++) {
            $t = $fi / max(1, $featherCount - 1);
            $xRoot = (0.46 + (1.0 - $t) * 0.74) * $span;
            $yRoot = 0.44 - $t * 0.72;
            $zRoot = 0.10 + (1.0 - $t) * 0.16;

            $xMid = (1.08 + (1.0 - $t) * 0.62) * $span;
            $yMid = 0.66 - $t * 1.06;
            $zMid = 0.22 + (1.0 - $t) * 0.16;

            $xTip = (1.42 + (1.0 - $t) * 0.78) * $span;
            $yTip = 0.84 - $t * 1.42;
            $zTip = 0.30 + (1.0 - $t) * 0.18;

            foreach ([-1.0, 1.0] as $side) {
                $this->addBeamBox(
                    $s[self::MAT_WING],
                    [$side * $xRoot, $yRoot, $zRoot],
                    [$side * $xMid,  $yMid,  $zMid],
                    0.18, 0.09, 0.11, 0.05,
                    self::UV_WING
                );
                $this->addBeamBox(
                    $s[self::MAT_CORE],
                    [$side * $xMid, $yMid, $zMid],
                    [$side * $xTip, $yTip, $zTip],
                    0.11, 0.05, 0.03, 0.015,
                    self::UV_PLASMA
                );
            }
        }

        // 5. Goldene Heck-Triebwerksglocken
        $this->addThrusterNacelle($s, 0.0, -1.72, -0.02, 0.24, 0.75);
        foreach ([-1.0, 1.0] as $side) {
            $this->addThrusterNacelle($s, $side * 0.36, -1.62, -0.02, 0.20, 0.72);
        }
    }

    // =========================================================================
    // 5. KLASSE S: ZION WELTENBRECHER S-1 BIS S-3 (SMARAGD-DREADNOUGHT)
    // =========================================================================
    private function buildZionBreaker3D(array &$s, float $span, int $rank, array $vCfg): void
    {
        // 1. Kolossaler Dreadnought-Rammsporn-Bug & Heck-Kommandosektion (MAT_HULL)
        $this->addLoftedBody($s[self::MAT_HULL], [
            ['y' => -1.62, 'z' => 0.00, 'rx' => 0.56, 'rzTop' => 0.34, 'rzBot' => 0.26, 'v' => 0.00],
            ['y' => -0.68, 'z' => 0.02, 'rx' => 0.62, 'rzTop' => 0.38, 'rzBot' => 0.28, 'v' => 0.32],
            ['y' =>  0.22, 'z' => 0.02, 'rx' => 0.58, 'rzTop' => 0.36, 'rzBot' => 0.28, 'v' => 0.58],
            ['y' =>  1.18, 'z' => 0.00, 'rx' => 0.52, 'rzTop' => 0.32, 'rzBot' => 0.26, 'v' => 0.85],
            ['y' =>  1.86, 'z' => 0.00, 'rx' => 0.32, 'rzTop' => 0.22, 'rzBot' => 0.20, 'v' => 1.00],
        ], self::UV_HULL);

        // Leuchtende Smaragd-Energierinne im Rammsporn-Bug (MAT_CORE)
        $this->addBeamBox($s[self::MAT_CORE], [0.0, 0.35, 0.26], [0.0, 1.90, 0.14], 0.18, 0.12, 0.14, 0.10, self::UV_PLASMA);

        // 2. Freiliegender Smaragd-Gold-Fusionsreaktor-Kern in der Schiffsmitte + Gold-Haltering
        $this->addDiamondCrystal($s[self::MAT_CORE], 0.0, -0.18, 0.14, 0.44, 0.44, 0.38, self::UV_CORE);
        $this->addTorusRing($s[self::MAT_TRIM], 0.0, -0.18, 0.14, 0.54, 0.085, M_PI * 0.5, 0.0, 0.0, self::UV_TRIM, 24, 10);

        // 3. Vier schwere Zwillings-Smaragd-Plasmageschütz-Batterien an den Bugflanken
        foreach ([-1.0, 1.0] as $side) {
            foreach ([0.22, -0.14] as $zOff) {
                $this->addBeamBox(
                    $s[self::MAT_TRIM],
                    [$side * 0.56, 0.15, $zOff],
                    [$side * 0.56, 0.85, $zOff],
                    0.18, 0.14, 0.16, 0.12,
                    self::UV_TRIM
                );
                $this->addBeamBox(
                    $s[self::MAT_CORE],
                    [$side * 0.58, 0.82, $zOff + 0.04],
                    [$side * 0.58, 1.82, $zOff + 0.04],
                    0.055, 0.055, 0.05, 0.05,
                    self::UV_PLASMA
                );
                $this->addBeamBox(
                    $s[self::MAT_CORE],
                    [$side * 0.58, 0.82, $zOff - 0.04],
                    [$side * 0.58, 1.82, $zOff - 0.04],
                    0.055, 0.055, 0.05, 0.05,
                    self::UV_PLASMA
                );
            }
        }

        // 4. Schwere seitliche Ausleger-Pylone + gepanzerte Seiten-Triebwerksgondeln
        $this->addSweptWingPair($s[self::MAT_WING], [
            ['x' => 0.52,          'yL' => -0.35, 'yT' => -1.25, 'z' =>  0.00, 'th' => 0.22],
            ['x' => 1.28 * $span,  'yL' => -0.58, 'yT' => -1.42, 'z' => -0.04, 'th' => 0.18],
        ], self::UV_WING, self::UV_TRIM);

        foreach ([-1.0, 1.0] as $side) {
            $this->addThrusterNacelle($s, $side * 1.32 * $span, -1.52, -0.04, 0.26, 1.05);
            // Hohe dorsale Kommando-Heckfinnen (MAT_TRIM)
            $this->addBeamBox(
                $s[self::MAT_TRIM],
                [$side * 0.26, -1.15, 0.30],
                [$side * 0.32, -1.42, 0.88],
                0.08, 0.36, 0.04, 0.18,
                self::UV_TRIM
            );
        }

        // 5. Haupt-Hecktriebwerke
        $this->addThrusterNacelle($s, 0.0, -1.75, 0.02, 0.28, 0.82);
        foreach ([-1.0, 1.0] as $side) {
            $this->addThrusterNacelle($s, $side * 0.44, -1.68, 0.00, 0.22, 0.78);
        }
    }

    // =========================================================================
    // 6. KLASSE GOTT: GÖTTLICHE MAJESTÄT DES UNIVERSUMS (1:1 NACH ARTWORK!)
    // =========================================================================
    private function buildDivineMajesty3D(array &$s, float $span, int $rank, array $vCfg): void
    {
        // 1. Vorderer Kaiserlicher Alabaster-Marmor Kathedralen-Bug (MAT_HULL, y = -0.08 .. +2.06)
        $this->addLoftedBody($s[self::MAT_HULL], [
            ['y' => -0.08, 'z' =>  0.02, 'rx' => 0.46, 'rzTop' => 0.34, 'rzBot' => 0.26, 'v' => 0.15],
            ['y' =>  0.45, 'z' =>  0.04, 'rx' => 0.50, 'rzTop' => 0.36, 'rzBot' => 0.28, 'v' => 0.42],
            ['y' =>  1.15, 'z' =>  0.04, 'rx' => 0.44, 'rzTop' => 0.34, 'rzBot' => 0.26, 'v' => 0.72],
            ['y' =>  1.78, 'z' =>  0.02, 'rx' => 0.28, 'rzTop' => 0.26, 'rzBot' => 0.22, 'v' => 0.94],
            ['y' =>  2.06, 'z' =>  0.00, 'rx' => 0.10, 'rzTop' => 0.16, 'rzBot' => 0.14, 'v' => 1.00],
        ], self::UV_HULL);

        // Erhabene 24k-Gold Kathedralen-Längsspanten, Bugkiel & Doppel-Kathedralenspitzen vorn am Bug (MAT_TRIM)
        $this->addBeamBox($s[self::MAT_TRIM], [0.0, -0.05, 0.36], [0.0, 1.92, 0.24], 0.09, 0.07, 0.06, 0.05, self::UV_TRIM);
        $this->addBeamBox($s[self::MAT_TRIM], [0.0,  0.10, -0.24], [0.0, 2.04, -0.18], 0.10, 0.08, 0.05, 0.06, self::UV_TRIM);

        foreach ([-1.0, 1.0] as $side) {
            // Gotische Gold-Bugspitzen (Doppel-Kathedralenhörner vorn am Bug!)
            $this->addBeamBox(
                $s[self::MAT_TRIM],
                [$side * 0.18, 1.62, 0.10],
                [$side * 0.14, 2.16, 0.38],
                0.085, 0.14, 0.02, 0.03,
                self::UV_TRIM
            );
            // Obere & untere 24k Gold-Filigran-Zierleisten entlang des Alabaster-Bugs
            $this->addBeamBox(
                $s[self::MAT_TRIM],
                [$side * 0.38, -0.05, 0.24],
                [$side * 0.24,  1.82, 0.18],
                0.065, 0.055, 0.04, 0.04,
                self::UV_TRIM
            );
            $this->addBeamBox(
                $s[self::MAT_TRIM],
                [$side * 0.38, -0.05, -0.18],
                [$side * 0.24,  1.82, -0.14],
                0.065, 0.055, 0.04, 0.04,
                self::UV_TRIM
            );
        }

        // 2. DREISCHICHTIGE SCHWARZE OBSIDIAN-DIAMANT-PANZERKASSETTEN entlang beider Rumpfflanken!
        //    Schicht A: Massiver 24k Gold-Diamantrahmen (MAT_TRIM)
        //    Schicht B: Scharf facettiertes tiefschwarzes Obsidian-Schild (MAT_OBSIDIAN)
        //    Schicht C: Glühender goldener Hochofen-Energiekern im Zentrum (MAT_CORE)
        foreach ([-1.0, 1.0] as $side) {
            foreach ([0.18, 0.58, 0.98, 1.38] as $yCass) {
                $this->addDiamondCrystal($s[self::MAT_TRIM],     $side * 0.45, $yCass, 0.02, 0.15, 0.26, 0.23, self::UV_TRIM);
                $this->addDiamondCrystal($s[self::MAT_OBSIDIAN], $side * 0.49, $yCass, 0.02, 0.13, 0.21, 0.18, self::UV_CASSETTE);
                $this->addDiamondCrystal($s[self::MAT_CORE],     $side * 0.53, $yCass, 0.02, 0.08, 0.10, 0.09, self::UV_CORE);
            }
        }

        // 3. Zwei schwere goldene Zwillings-Geschütztürme auf dem vorderen Oberdeck (MAT_TRIM)!
        foreach ([0.52, 1.08] as $yTurret) {
            $this->addDiamondCrystal($s[self::MAT_TRIM], 0.0, $yTurret, 0.38, 0.21, 0.23, 0.13, self::UV_TRIM);
            foreach ([-0.07, 0.07] as $xBarrel) {
                $this->addBeamBox(
                    $s[self::MAT_TRIM],
                    [$xBarrel, $yTurret + 0.08, 0.42],
                    [$xBarrel, $yTurret + 0.62, 0.44],
                    0.038, 0.038, 0.028, 0.028,
                    self::UV_TRIM
                );
            }
        }

        // 4. Brennende Singularitäts-Sonne im Zentrum (MAT_CORE) + KOLOSSALE DOPPELTE GOLD-RUNEN-ASTROLABIUM-RINGE (MAT_TRIM)!
        $this->addDiamondCrystal($s[self::MAT_CORE], 0.0, -0.24, 0.06, 0.38, 0.38, 0.38, self::UV_CORE);
        $this->addTorusRing($s[self::MAT_TRIM], 0.0, -0.24, 0.06, 1.38, 0.082, 0.42,  0.18, 0.0, self::UV_TRIM, 40, 12);
        $this->addTorusRing($s[self::MAT_TRIM], 0.0, -0.24, 0.06, 1.12, 0.062, 0.42, -0.18, 0.0, self::UV_TRIM, 36, 10);

        // 5. Heck-Sektion & hoch aufragender Kaiserlicher Kathedralen-Kommandoturm mit schwebendem Heiligenschein-Ring!
        $this->addLoftedBody($s[self::MAT_HULL], [
            ['y' => -1.58, 'z' =>  0.00, 'rx' => 0.54, 'rzTop' => 0.32, 'rzBot' => 0.26, 'v' => 0.00],
            ['y' => -0.95, 'z' =>  0.04, 'rx' => 0.62, 'rzTop' => 0.42, 'rzBot' => 0.30, 'v' => 0.25],
            ['y' => -0.38, 'z' =>  0.04, 'rx' => 0.48, 'rzTop' => 0.34, 'rzBot' => 0.24, 'v' => 0.48],
        ], self::UV_HULL);

        // Kathedralen-Hauptturm auf dem Achterdeck (MAT_HULL + goldene Strebepfeiler MAT_TRIM)
        $this->addLoftedBody($s[self::MAT_HULL], [
            ['y' => -1.18, 'z' => 0.32, 'rx' => 0.36, 'rzTop' => 0.18, 'rzBot' => 0.05, 'v' => 0.10],
            ['y' => -0.92, 'z' => 0.56, 'rx' => 0.26, 'rzTop' => 0.22, 'rzBot' => 0.05, 'v' => 0.45],
            ['y' => -0.88, 'z' => 0.84, 'rx' => 0.14, 'rzTop' => 0.20, 'rzBot' => 0.05, 'v' => 0.82],
            ['y' => -0.86, 'z' => 1.15, 'rx' => 0.03, 'rzTop' => 0.06, 'rzBot' => 0.02, 'v' => 1.00],
        ], self::UV_HULL);

        // 4 goldene Flankentürmchen & Spitzen der Kathedrale (MAT_TRIM)
        foreach ([-1.0, 1.0] as $sx) {
            foreach ([-1.12, -0.72] as $sy) {
                $this->addBeamBox(
                    $s[self::MAT_TRIM],
                    [$sx * 0.28, $sy, 0.30],
                    [$sx * 0.24, $sy, 0.86],
                    0.075, 0.075, 0.015, 0.015,
                    self::UV_TRIM
                );
            }
        }
        // Goldene Kathedralen-Hauptspitze
        $this->addBeamBox($s[self::MAT_TRIM], [0.0, -0.88, 0.78], [0.0, -0.86, 1.24], 0.07, 0.07, 0.012, 0.012, self::UV_TRIM);

        // Schwebender horizontaler Gold-Heiligenschein-Ring über der Kathedralenspitze (MAT_TRIM + MAT_CORE)!
        $this->addTorusRing($s[self::MAT_TRIM], 0.0, -0.88, 0.98, 0.58, 0.046, 0.0, 0.0, 0.0, self::UV_TRIM, 32, 10);

        // 6. Sechs Paar leuchtende Gold-Kristall-Seraphim-Schwingen (Gold-Schulterrahmen MAT_TRIM + Kristallklingen MAT_WING + Lichtspitzen MAT_CORE)
        $upperFeathers = [
            ['xR' => 0.48, 'yR' => -0.78, 'zR' => 0.22, 'xT' => 2.14, 'yT' =>  0.18, 'zT' => 0.72, 'w' => 0.22],
            ['xR' => 0.52, 'yR' => -0.92, 'zR' => 0.18, 'xT' => 2.00, 'yT' => -0.28, 'zT' => 0.54, 'w' => 0.20],
            ['xR' => 0.50, 'yR' => -1.06, 'zR' => 0.12, 'xT' => 1.74, 'yT' => -0.68, 'zT' => 0.36, 'w' => 0.18],
        ];
        $lowerFeathers = [
            ['xR' => 0.46, 'yR' => -1.02, 'zR' => -0.08, 'xT' => 1.66, 'yT' => -1.42, 'zT' => -0.28, 'w' => 0.16],
            ['xR' => 0.42, 'yR' => -1.14, 'zR' => -0.12, 'xT' => 1.38, 'yT' => -1.68, 'zT' => -0.36, 'w' => 0.14],
            ['xR' => 0.38, 'yR' => -1.24, 'zR' => -0.15, 'xT' => 1.08, 'yT' => -1.84, 'zT' => -0.42, 'w' => 0.12],
        ];
        foreach (array_merge($upperFeathers, $lowerFeathers) as $f) {
            foreach ([-1.0, 1.0] as $side) {
                $p0 = [$side * $f['xR'], $f['yR'], $f['zR']];
                $p2 = [$side * $f['xT'] * $span, $f['yT'], $f['zT']];
                $p1 = [
                    $p0[0] * 0.45 + $p2[0] * 0.55,
                    $p0[1] * 0.45 + $p2[1] * 0.55,
                    $p0[2] * 0.45 + $p2[2] * 0.55,
                ];
                // Goldener Federkiel an der Wurzel
                $this->addBeamBox($s[self::MAT_TRIM], $p0, $p1, $f['w'] * 0.65, 0.08, $f['w'] * 0.45, 0.05, self::UV_TRIM);
                // Leuchtende Kristall-Seraphim-Federklinge
                $this->addBeamBox($s[self::MAT_WING], $p0, $p2, $f['w'], 0.07, 0.04, 0.02, self::UV_WING);
                // Gleißende Lichtspitze
                $this->addBeamBox($s[self::MAT_CORE], $p1, $p2, $f['w'] * 0.65, 0.05, 0.025, 0.012, self::UV_PLASMA);
            }
        }

        // 7. Vier schwere weiß-goldene Ionen-Triebwerksgondeln + Zentral-Triebwerk am Heck!
        $this->addThrusterNacelle($s, 0.0, -1.72, 0.00, 0.24, 0.78);
        foreach ([-1.0, 1.0] as $side) {
            $this->addThrusterNacelle($s, $side * 0.42, -1.66,  0.04, 0.20, 0.76);
            $this->addThrusterNacelle($s, $side * 0.76, -1.52, -0.04, 0.18, 0.68);
        }
    }

    // =========================================================================
    // 3D GEOMETRIE-PRIMITIVE MIT MESSERSCHARFEN FACETTEN-NORMALEN
    // =========================================================================

    /**
     * Fügt ein einzelnes Dreieck mit eigener, exakt berechneter Flächennormale (Flat Shading) hinzu,
     * sodass Kristallfacetten, Diamantkassetten und Panzerkanten messerscharf das Licht reflektieren!
     */
    private function addFlatTriangle(
        array &$m,
        array $v0,
        array $v1,
        array $v2,
        array $uv0,
        array $uv1,
        array $uv2
    ): void {
        $ux = $v1[0] - $v0[0];
        $uy = $v1[1] - $v0[1];
        $uz = $v1[2] - $v0[2];

        $vx = $v2[0] - $v0[0];
        $vy = $v2[1] - $v0[1];
        $vz = $v2[2] - $v0[2];

        $nx = $uy * $vz - $uz * $vy;
        $ny = $uz * $vx - $ux * $vz;
        $nz = $ux * $vy - $uy * $vx;
        $len = sqrt($nx * $nx + $ny * $ny + $nz * $nz);
        if ($len > 1e-7) {
            $nx /= $len;
            $ny /= $len;
            $nz /= $len;
        } else {
            $nx = 0.0;
            $ny = 0.0;
            $nz = 1.0;
        }

        $baseIdx = intdiv(count($m['positions']), 3);
        array_push(
            $m['positions'],
            $v0[0], $v0[1], $v0[2],
            $v1[0], $v1[1], $v1[2],
            $v2[0], $v2[1], $v2[2]
        );
        array_push(
            $m['normals'],
            $nx, $ny, $nz,
            $nx, $ny, $nz,
            $nx, $ny, $nz
        );
        array_push(
            $m['uvs'],
            $uv0[0], $uv0[1],
            $uv1[0], $uv1[1],
            $uv2[0], $uv2[1]
        );
        array_push($m['indices'], $baseIdx, $baseIdx + 1, $baseIdx + 2);
    }

    /**
     * Erzeugt einen skulptierten 3D-Rumpfkörper entlang einer Kette von Ellipsen-Querschnitten (optional um $centerX verschoben).
     */
    private function addLoftedBody(array &$m, array $rings, array $uvRect, float $centerX = 0.0): void
    {
        $nRings = count($rings);
        if ($nRings < 2) {
            return;
        }
        $segs = 18;
        [$uMin, $vMin, $uMax, $vMax] = $uvRect;

        $ringVertIdx = [];
        for ($r = 0; $r < $nRings; $r++) {
            $ring = $rings[$r];
            $ry = (float) $ring['y'];
            $rz = (float) $ring['z'];
            $rx = max(0.01, (float) $ring['rx']);
            $rzTop = max(0.01, (float) $ring['rzTop']);
            $rzBot = max(0.01, (float) $ring['rzBot']);
            $tv = (float) ($ring['v'] ?? ($r / max(1, $nRings - 1)));
            $vCoord = $vMin + ($vMax - $vMin) * (1.0 - $tv);

            $ringVertIdx[$r] = [];
            for ($s = 0; $s <= $segs; $s++) {
                $t = $s / $segs;
                $ang = $t * M_PI * 2.0;
                $cos = cos($ang);
                $sin = sin($ang);

                $px = $centerX + $rx * $cos;
                $pz = $rz + ($sin >= 0.0 ? $rzTop * $sin : $rzBot * $sin);

                $nx = $cos / $rx;
                $nz = $sin / ($sin >= 0.0 ? $rzTop : $rzBot);
                $nLen = hypot($nx, $nz) ?: 1.0;

                $uSym = 0.5 + 0.5 * $cos;
                $uCoord = $uMin + ($uMax - $uMin) * $uSym;

                $idx = intdiv(count($m['positions']), 3);
                $ringVertIdx[$r][$s] = $idx;
                array_push($m['positions'], $px, $ry, $pz);
                array_push($m['normals'], $nx / $nLen, 0.0, $nz / $nLen);
                array_push($m['uvs'], $uCoord, $vCoord);
            }
        }

        for ($r = 0; $r < $nRings - 1; $r++) {
            for ($s = 0; $s < $segs; $s++) {
                $i00 = $ringVertIdx[$r][$s];
                $i10 = $ringVertIdx[$r][$s + 1];
                $i01 = $ringVertIdx[$r + 1][$s];
                $i11 = $ringVertIdx[$r + 1][$s + 1];
                array_push($m['indices'], $i00, $i10, $i11, $i00, $i11, $i01);
            }
        }

        $first = $rings[0];
        $cTailIdx = intdiv(count($m['positions']), 3);
        array_push($m['positions'], $centerX, (float) $first['y'], (float) $first['z']);
        array_push($m['normals'], 0.0, -1.0, 0.0);
        array_push($m['uvs'], ($uMin + $uMax) * 0.5, $vMax);
        for ($s = 0; $s < $segs; $s++) {
            array_push($m['indices'], $cTailIdx, $ringVertIdx[0][$s + 1], $ringVertIdx[0][$s]);
        }

        $last = $rings[$nRings - 1];
        $cNoseIdx = intdiv(count($m['positions']), 3);
        array_push($m['positions'], $centerX, (float) $last['y'], (float) $last['z']);
        array_push($m['normals'], 0.0, 1.0, 0.0);
        array_push($m['uvs'], ($uMin + $uMax) * 0.5, $vMin);
        for ($s = 0; $s < $segs; $s++) {
            array_push($m['indices'], $cNoseIdx, $ringVertIdx[$nRings - 1][$s], $ringVertIdx[$nRings - 1][$s + 1]);
        }
    }

    /**
     * Erzeugt ein symmetrisches Paar echter 3D-Tragflächen (Links & Rechts).
     */
    private function addSweptWingPair(array &$m, array $stations, array $uvWing, array $uvTrim): void
    {
        [$uMin, $vMin, $uMax, $vMax] = $uvWing;
        $nStat = count($stations);
        if ($nStat < 2) {
            return;
        }

        foreach ([-1.0, 1.0] as $side) {
            $chordSteps = 6;
            $topGrid = [];
            $botGrid = [];

            for ($i = 0; $i < $nStat; $i++) {
                $st = $stations[$i];
                $uSpan = $i / max(1, $nStat - 1);
                $wx = $side * (float) $st['x'];
                $yL = (float) $st['yL'];
                $yT = (float) $st['yT'];
                $wz = (float) $st['z'];
                $th = (float) $st['th'];

                for ($c = 0; $c <= $chordSteps; $c++) {
                    $tc = $c / $chordSteps;
                    $wy = $yL * (1.0 - $tc) + $yT * $tc;
                    $airfoil = sin(pow($tc, 0.65) * M_PI);
                    $dz = $th * (0.18 + 0.82 * $airfoil);

                    $uCoord = $uMin + ($uMax - $uMin) * (0.5 + 0.48 * $uSpan);
                    $vCoord = $vMin + ($vMax - $vMin) * $tc;

                    $tIdx = intdiv(count($m['positions']), 3);
                    array_push($m['positions'], $wx, $wy, $wz + $dz);
                    array_push($m['normals'], 0.0, 0.22 * (0.5 - $tc), 0.97);
                    array_push($m['uvs'], $uCoord, $vCoord);
                    $topGrid[$i][$c] = $tIdx;

                    $bIdx = intdiv(count($m['positions']), 3);
                    array_push($m['positions'], $wx, $wy, $wz - $dz * 0.82);
                    array_push($m['normals'], 0.0, 0.22 * (0.5 - $tc), -0.97);
                    array_push($m['uvs'], $uCoord, $vCoord);
                    $botGrid[$i][$c] = $bIdx;
                }
            }

            for ($i = 0; $i < $nStat - 1; $i++) {
                for ($c = 0; $c < $chordSteps; $c++) {
                    $t00 = $topGrid[$i][$c];
                    $t10 = $topGrid[$i + 1][$c];
                    $t01 = $topGrid[$i][$c + 1];
                    $t11 = $topGrid[$i + 1][$c + 1];

                    $b00 = $botGrid[$i][$c];
                    $b10 = $botGrid[$i + 1][$c];
                    $b01 = $botGrid[$i][$c + 1];
                    $b11 = $botGrid[$i + 1][$c + 1];

                    if ($side > 0) {
                        array_push($m['indices'], $t00, $t01, $t11, $t00, $t11, $t10);
                        array_push($m['indices'], $b00, $b11, $b01, $b00, $b10, $b11);
                    } else {
                        array_push($m['indices'], $t00, $t11, $t01, $t00, $t10, $t11);
                        array_push($m['indices'], $b00, $b01, $b11, $b00, $b11, $b10);
                    }
                }

                $tl0 = $topGrid[$i][0];
                $tl1 = $topGrid[$i + 1][0];
                $bl0 = $botGrid[$i][0];
                $bl1 = $botGrid[$i + 1][0];
                array_push($m['indices'], $tl0, $tl1, $bl1, $tl0, $bl1, $bl0);

                $tt0 = $topGrid[$i][$chordSteps];
                $tt1 = $topGrid[$i + 1][$chordSteps];
                $bt0 = $botGrid[$i][$chordSteps];
                $bt1 = $botGrid[$i + 1][$chordSteps];
                array_push($m['indices'], $tt0, $bt1, $tt1, $tt0, $bt0, $bt1);
            }

            $last = $nStat - 1;
            for ($c = 0; $c < $chordSteps; $c++) {
                array_push(
                    $m['indices'],
                    $topGrid[$last][$c],
                    $botGrid[$last][$c],
                    $botGrid[$last][$c + 1],
                    $topGrid[$last][$c],
                    $botGrid[$last][$c + 1],
                    $topGrid[$last][$c + 1]
                );
            }
        }
    }

    /**
     * Erzeugt einen 3D-facettierten Träger / Schwertklinge / Feder / Kanonenlauf mit messerscharfen Flat-Shading-Normalen!
     */
    private function addBeamBox(
        array &$m,
        array $p0,
        array $p1,
        float $w0,
        float $h0,
        float $w1,
        float $h1,
        array $uvRect
    ): void {
        [$uMin, $vMin, $uMax, $vMax] = $uvRect;
        $dx = $p1[0] - $p0[0];
        $dy = $p1[1] - $p0[1];
        $dz = $p1[2] - $p0[2];
        $len = sqrt($dx * $dx + $dy * $dy + $dz * $dz) ?: 1.0;
        $fx = $dx / $len;
        $fy = $dy / $len;
        $fz = $dz / $len;

        if (abs($fz) < 0.9) {
            $rx = -$fy;
            $ry = $fx;
            $rz = 0.0;
        } else {
            $rx = 1.0;
            $ry = 0.0;
            $rz = 0.0;
        }
        $rLen = sqrt($rx * $rx + $ry * $ry + $rz * $rz) ?: 1.0;
        $rx /= $rLen;
        $ry /= $rLen;
        $rz /= $rLen;

        $ux = $fy * $rz - $fz * $ry;
        $uy = $fz * $rx - $fx * $rz;
        $uz = $fx * $ry - $fy * $rx;

        $profAngles = [0.0, M_PI * 0.35, M_PI * 0.65, M_PI, M_PI * 1.35, M_PI * 1.65];
        $nP = count($profAngles);
        $pts0 = [];
        $pts1 = [];
        $uCoords = [];

        for ($k = 0; $k <= $nP; $k++) {
            $a = $profAngles[$k % $nP];
            $ca = cos($a);
            $sa = sin($a);

            $pts0[$k] = [
                $p0[0] + ($rx * $ca * $w0 + $ux * $sa * $h0),
                $p0[1] + ($ry * $ca * $w0 + $uy * $sa * $h0),
                $p0[2] + ($rz * $ca * $w0 + $uz * $sa * $h0),
            ];
            $pts1[$k] = [
                $p1[0] + ($rx * $ca * $w1 + $ux * $sa * $h1),
                $p1[1] + ($ry * $ca * $w1 + $uy * $sa * $h1),
                $p1[2] + ($rz * $ca * $w1 + $uz * $sa * $h1),
            ];
            $uCoords[$k] = $uMin + ($uMax - $uMin) * (0.5 + 0.45 * $ca);
        }

        $tipPt = [
            $p1[0] + $fx * $w1 * 0.45,
            $p1[1] + $fy * $w1 * 0.45,
            $p1[2] + $fz * $w1 * 0.45,
        ];
        $uMid = ($uMin + $uMax) * 0.5;

        for ($k = 0; $k < $nP; $k++) {
            $uv00 = [$uCoords[$k], $vMax];
            $uv10 = [$uCoords[$k + 1], $vMax];
            $uv01 = [$uCoords[$k], $vMin];
            $uv11 = [$uCoords[$k + 1], $vMin];

            $this->addFlatTriangle($m, $pts0[$k], $pts0[$k + 1], $pts1[$k + 1], $uv00, $uv10, $uv11);
            $this->addFlatTriangle($m, $pts0[$k], $pts1[$k + 1], $pts1[$k],     $uv00, $uv11, $uv01);
            $this->addFlatTriangle($m, $tipPt,    $pts1[$k],     $pts1[$k + 1], [$uMid, $vMin], $uv01, $uv11);
        }
    }

    /**
     * Erzeugt einen scharf facettierten 3D-Diamant-Kristall / Obsidian-Panzerkassette / Reaktorkern (Flat Shaded!).
     */
    private function addDiamondCrystal(
        array &$m,
        float $cx,
        float $cy,
        float $cz,
        float $rx,
        float $ry,
        float $rz,
        array $uvRect
    ): void {
        [$uMin, $vMin, $uMax, $vMax] = $uvRect;
        $uMid = ($uMin + $uMax) * 0.5;
        $vMid = ($vMin + $vMax) * 0.5;
        $uSpan = ($uMax - $uMin) * 0.46;
        $vSpan = ($vMax - $vMin) * 0.46;

        $topPt = [$cx, $cy, $cz + $rz];
        $botPt = [$cx, $cy, $cz - $rz];
        $uvCenter = [$uMid, $vMid];

        $segs = 8;
        $ringPts = [];
        $ringUvs = [];
        for ($i = 0; $i <= $segs; $i++) {
            $a = ($i / $segs) * M_PI * 2.0;
            $ca = cos($a);
            $sa = sin($a);
            $ringPts[$i] = [$cx + $rx * $ca, $cy + $ry * $sa, $cz];
            $ringUvs[$i] = [$uMid + $uSpan * $ca, $vMid + $vSpan * $sa];
        }

        for ($i = 0; $i < $segs; $i++) {
            $this->addFlatTriangle($m, $topPt, $ringPts[$i],     $ringPts[$i + 1], $uvCenter, $ringUvs[$i],     $ringUvs[$i + 1]);
            $this->addFlatTriangle($m, $botPt, $ringPts[$i + 1], $ringPts[$i],     $uvCenter, $ringUvs[$i + 1], $ringUvs[$i]);
        }
    }

    /**
     * Erzeugt einen 3D-Astrolabium-/Heiligenschein-Runenring (Torus) mit freier 3D-Neigung.
     */
    private function addTorusRing(
        array &$m,
        float $cx,
        float $cy,
        float $cz,
        float $rMajor,
        float $rTube,
        float $rotX,
        float $rotY,
        float $rotZ,
        array $uvRect,
        int $segMajor = 36,
        int $segTube = 10
    ): void {
        [$uMin, $vMin, $uMax, $vMax] = $uvRect;
        $cxRot = cos($rotX); $sxRot = sin($rotX);
        $cyRot = cos($rotY); $syRot = sin($rotY);
        $czRot = cos($rotZ); $szRot = sin($rotZ);

        $rotate = static function (float $x, float $y, float $z) use ($cxRot, $sxRot, $cyRot, $syRot, $czRot, $szRot): array {
            $y1 = $y * $cxRot - $z * $sxRot;
            $z1 = $y * $sxRot + $z * $cxRot;
            $x1 = $x;
            $x2 = $x1 * $cyRot + $z1 * $syRot;
            $z2 = -$x1 * $syRot + $z1 * $cyRot;
            $y2 = $y1;
            $x3 = $x2 * $czRot - $y2 * $szRot;
            $y3 = $x2 * $szRot + $y2 * $czRot;
            return [$x3, $y3, $z2];
        };

        $grid = [];
        for ($i = 0; $i <= $segMajor; $i++) {
            $u = $i / $segMajor;
            $theta = $u * M_PI * 2.0;
            $ct = cos($theta);
            $st = sin($theta);

            for ($j = 0; $j <= $segTube; $j++) {
                $v = $j / $segTube;
                $phi = $v * M_PI * 2.0;
                $cp = cos($phi);
                $sp = sin($phi);

                $lx = ($rMajor + $rTube * $cp) * $ct;
                $ly = ($rMajor + $rTube * $cp) * $st;
                $lz = $rTube * $sp;

                [$wx, $wy, $wz] = $rotate($lx, $ly, $lz);
                [$nx, $ny, $nz] = $rotate($cp * $ct, $cp * $st, $sp);

                $idx = intdiv(count($m['positions']), 3);
                array_push($m['positions'], $cx + $wx, $cy + $wy, $cz + $wz);
                array_push($m['normals'], $nx, $ny, $nz);
                array_push(
                    $m['uvs'],
                    $uMin + ($uMax - $uMin) * $u,
                    $vMin + ($vMax - $vMin) * $v
                );
                $grid[$i][$j] = $idx;
            }
        }

        for ($i = 0; $i < $segMajor; $i++) {
            for ($j = 0; $j < $segTube; $j++) {
                $i00 = $grid[$i][$j];
                $i10 = $grid[$i + 1][$j];
                $i01 = $grid[$i][$j + 1];
                $i11 = $grid[$i + 1][$j + 1];
                array_push($m['indices'], $i00, $i10, $i11, $i00, $i11, $i01);
            }
        }
    }

    /**
     * Erzeugt eine 3D-gepanzerte Triebwerksgondel (MAT_HULL) mit 24k Gold-Düsenring (MAT_TRIM) & glühendem Ionen-Plasmakern (MAT_CORE).
     */
    private function addThrusterNacelle(
        array &$s,
        float $x,
        float $yRear,
        float $z,
        float $radius,
        float $length
    ): void {
        $this->addLoftedBody($s[self::MAT_HULL], [
            ['y' => $yRear,                   'z' => $z, 'rx' => $radius * 0.88, 'rzTop' => $radius * 0.88, 'rzBot' => $radius * 0.88, 'v' => 0.05],
            ['y' => $yRear + $length * 0.35,  'z' => $z, 'rx' => $radius * 1.04, 'rzTop' => $radius * 1.04, 'rzBot' => $radius * 1.04, 'v' => 0.45],
            ['y' => $yRear + $length * 0.85,  'z' => $z, 'rx' => $radius * 0.92, 'rzTop' => $radius * 0.92, 'rzBot' => $radius * 0.92, 'v' => 0.88],
            ['y' => $yRear + $length,         'z' => $z, 'rx' => $radius * 0.55, 'rzTop' => $radius * 0.55, 'rzBot' => $radius * 0.55, 'v' => 1.00],
        ], self::UV_HULL, $x);

        $this->addTorusRing($s[self::MAT_TRIM], $x, $yRear + 0.04, $z, $radius * 0.88, $radius * 0.16, M_PI * 0.5, 0.0, 0.0, self::UV_TRIM, 20, 8);
        $this->addDiamondCrystal($s[self::MAT_CORE], $x, $yRear - 0.04, $z, $radius * 0.72, $radius * 0.26, $radius * 0.72, self::UV_CORE);
    }

    /**
     * Baut ein binäres Multi-Material glTF 2.0 (.glb) mit 5 dedizierten PBR-Materialien und eingebetteter PNG-Textur.
     */
    private function buildMultiMaterialGlb(
        array $subMeshes,
        string $pngBytes,
        string $meshName,
        array $fCfg
    ): string {
        $pad4 = static function (string $buf, string $padChar = "\0"): string {
            $rem = strlen($buf) % 4;
            return $rem === 0 ? $buf : $buf . str_repeat($padChar, 4 - $rem);
        };

        $bufferViews = [];
        $accessors = [];
        $primitives = [];
        $binChunks = [];
        $offset = 0;

        foreach ($subMeshes as $matIdx => $m) {
            if (empty($m['indices']) || empty($m['positions'])) {
                continue;
            }

            $positions = $m['positions'];
            $normals   = $m['normals'];
            $uvs       = $m['uvs'];
            $indices   = $m['indices'];

            $minX = INF; $minY = INF; $minZ = INF;
            $maxX = -INF; $maxY = -INF; $maxZ = -INF;
            $posCount = count($positions);
            for ($i = 0; $i < $posCount; $i += 3) {
                $x = (float) $positions[$i];
                $y = (float) $positions[$i + 1];
                $z = (float) $positions[$i + 2];
                if ($x < $minX) $minX = $x; if ($x > $maxX) $maxX = $x;
                if ($y < $minY) $minY = $y; if ($y > $maxY) $maxY = $y;
                if ($z < $minZ) $minZ = $z; if ($z > $maxZ) $maxZ = $z;
            }

            $rawPos  = pack('g*', ...$positions);
            $rawNorm = pack('g*', ...$normals);
            $rawUv   = pack('g*', ...$uvs);
            $rawIdx  = pack('V*', ...$indices);

            $bPos  = $pad4($rawPos);
            $bNorm = $pad4($rawNorm);
            $bUv   = $pad4($rawUv);
            $bIdx  = $pad4($rawIdx);

            $bvPosIdx = count($bufferViews);
            $bufferViews[] = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($rawPos), 'target' => 34962];
            $offset += strlen($bPos);
            $binChunks[] = $bPos;

            $bvNormIdx = count($bufferViews);
            $bufferViews[] = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($rawNorm), 'target' => 34962];
            $offset += strlen($bNorm);
            $binChunks[] = $bNorm;

            $bvUvIdx = count($bufferViews);
            $bufferViews[] = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($rawUv), 'target' => 34962];
            $offset += strlen($bUv);
            $binChunks[] = $bUv;

            $bvIdxIdx = count($bufferViews);
            $bufferViews[] = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($rawIdx), 'target' => 34963];
            $offset += strlen($bIdx);
            $binChunks[] = $bIdx;

            $accPosIdx = count($accessors);
            $accessors[] = [
                'bufferView'    => $bvPosIdx,
                'componentType' => 5126,
                'count'         => intdiv($posCount, 3),
                'type'          => 'VEC3',
                'min'           => [$minX, $minY, $minZ],
                'max'           => [$maxX, $maxY, $maxZ],
            ];

            $accNormIdx = count($accessors);
            $accessors[] = [
                'bufferView'    => $bvNormIdx,
                'componentType' => 5126,
                'count'         => intdiv(count($normals), 3),
                'type'          => 'VEC3',
            ];

            $accUvIdx = count($accessors);
            $accessors[] = [
                'bufferView'    => $bvUvIdx,
                'componentType' => 5126,
                'count'         => intdiv(count($uvs), 2),
                'type'          => 'VEC2',
            ];

            $accIdxIdx = count($accessors);
            $accessors[] = [
                'bufferView'    => $bvIdxIdx,
                'componentType' => 5125,
                'count'         => count($indices),
                'type'          => 'SCALAR',
            ];

            $primitives[] = [
                'attributes' => [
                    'POSITION'   => $accPosIdx,
                    'NORMAL'     => $accNormIdx,
                    'TEXCOORD_0' => $accUvIdx,
                ],
                'indices'  => $accIdxIdx,
                'material' => $matIdx,
            ];
        }

        // Eingebetteter PNG-Texturatlas am Ende des Buffers
        $bImg = $pad4($pngBytes);
        $bvImgIdx = count($bufferViews);
        $bufferViews[] = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($pngBytes)];
        $offset += strlen($bImg);
        $binChunks[] = $bImg;

        $binBuffer = implode('', $binChunks);

        $hullMetal = (float) ($fCfg['metallic'] ?? 0.48);
        $hullRough = (float) ($fCfg['roughness'] ?? 0.26);
        [$coreR, $coreG, $coreB] = $fCfg['core_rgb'] ?? [56, 208, 255];
        [$wingR, $wingG, $wingB] = $fCfg['facet_rgb'] ?? [245, 200, 95];

        $materials = [
            // 0: MAT_HULL
            [
                'name'        => 'sf_mat_hull',
                'doubleSided' => true,
                'pbrMetallicRoughness' => [
                    'baseColorFactor'  => [1.0, 1.0, 1.0, 1.0],
                    'baseColorTexture' => ['index' => 0],
                    'metallicFactor'   => $hullMetal,
                    'roughnessFactor'  => $hullRough,
                ],
            ],
            // 1: MAT_TRIM (24k Imperator-Gold / Platin-Spanten / Astrolabium-Runenringe)
            [
                'name'        => 'sf_mat_trim',
                'doubleSided' => true,
                'emissiveFactor' => [0.14, 0.10, 0.03],
                'pbrMetallicRoughness' => [
                    'baseColorFactor'  => [1.0, 0.96, 0.86, 1.0],
                    'baseColorTexture' => ['index' => 0],
                    'metallicFactor'   => 0.84,
                    'roughnessFactor'  => 0.16,
                ],
            ],
            // 2: MAT_WING (Seraphim-Federn / Smaragd-Kristallklingen / Stealth-Schwingen)
            [
                'name'        => 'sf_mat_wing',
                'doubleSided' => true,
                'emissiveFactor' => [
                    round(($wingR / 255.0) * 0.22, 3),
                    round(($wingG / 255.0) * 0.22, 3),
                    round(($wingB / 255.0) * 0.22, 3),
                ],
                'pbrMetallicRoughness' => [
                    'baseColorFactor'  => [1.0, 1.0, 1.0, 1.0],
                    'baseColorTexture' => ['index' => 0],
                    'metallicFactor'   => 0.38,
                    'roughnessFactor'  => 0.14,
                ],
            ],
            // 3: MAT_OBSIDIAN (Tiefschwarze Obsidian-Diamantkassetten & Panzerglas)
            [
                'name'        => 'sf_mat_obsidian',
                'doubleSided' => true,
                'pbrMetallicRoughness' => [
                    'baseColorFactor'  => [0.88, 0.90, 0.96, 1.0],
                    'baseColorTexture' => ['index' => 0],
                    'metallicFactor'   => 0.76,
                    'roughnessFactor'  => 0.10,
                ],
            ],
            // 4: MAT_CORE (Selbstleuchtende Singularitäts-Sonne / Triebwerksglut / Energielanzen)
            [
                'name'        => 'sf_mat_core',
                'doubleSided' => true,
                'emissiveFactor' => [
                    round(($coreR / 255.0) * 0.85, 3),
                    round(($coreG / 255.0) * 0.85, 3),
                    round(($coreB / 255.0) * 0.85, 3),
                ],
                'pbrMetallicRoughness' => [
                    'baseColorFactor'  => [1.0, 1.0, 1.0, 1.0],
                    'baseColorTexture' => ['index' => 0],
                    'metallicFactor'   => 0.12,
                    'roughnessFactor'  => 0.08,
                ],
            ],
        ];

        $gltf = [
            'asset'       => ['version' => '2.0', 'generator' => 'Seelenfunke-Spaceship-3D-v5-MultiPBR'],
            'scene'       => 0,
            'scenes'      => [['nodes' => [0]]],
            'nodes'       => [['name' => $meshName, 'mesh' => 0]],
            'meshes'      => [[
                'name'       => $meshName,
                'primitives' => $primitives,
            ]],
            'materials'   => $materials,
            'textures'    => [['source' => 0, 'sampler' => 0]],
            'samplers'    => [['magFilter' => 9729, 'minFilter' => 9729, 'wrapS' => 33071, 'wrapT' => 33071]],
            'images'      => [['bufferView' => $bvImgIdx, 'mimeType' => 'image/png']],
            'accessors'   => $accessors,
            'bufferViews' => $bufferViews,
            'buffers'     => [['byteLength' => strlen($binBuffer)]],
        ];

        $jsonBuf = $pad4(json_encode($gltf, JSON_UNESCAPED_SLASHES), ' ');
        $totalLen = 12 + 8 + strlen($jsonBuf) + 8 + strlen($binBuffer);

        $header = 'glTF' . pack('VV', 2, $totalLen);
        $jsonHeader = pack('V', strlen($jsonBuf)) . 'JSON';
        $binHeader = pack('V', strlen($binBuffer)) . "BIN\0";

        return $header . $jsonHeader . $jsonBuf . $binHeader . $binBuffer;
    }
}

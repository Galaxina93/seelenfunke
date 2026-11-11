<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Generiert und synchronisiert alle 3D-Modelle (.glb), 2D-Konfigurator-Overlays (inkl. Saison-Tints)
 * sowie die technischen Maßblätter (*-dimensions.jpg) direkt nativ in PHP innerhalb der Seeder-Pipeline.
 *
 * Ersetzt das frühere externe Node.js-Skript `scripts/generate-product-3d.mjs` vollständig.
 */
class ProductAssetSeeder extends Seeder
{
    /**
     * Offizielles binäres glTF-2.0 Urheberrechts-Wasserzeichen (§§ 2, 10, 69a UrhG) für alle 3D-Modelle (.glb).
     */
    public const GLB_COPYRIGHT_NOTICE = '© Mein-Seelenfunke (Inhaberin: Alina Steinhauer, Gifhorn) - Alle Rechte vorbehalten (§§ 2, 10, 69a UrhG). Unautorisierte Vervielfältigung oder Nutzung untersagt.';

    /**
     * Saisonale Farb-Tints für die 2D-Konfigurator-Bühnen.
     */
    private const THEMES = [
        ['key' => 'standard',   'tint' => null],
        ['key' => 'winter',     'tint' => ['r' => 215, 'g' => 230, 'b' => 255, 'alpha' => 0.08]],
        ['key' => 'ostern',     'tint' => ['r' => 255, 'g' => 245, 'b' => 210, 'alpha' => 0.08]],
        ['key' => 'sommer',     'tint' => ['r' => 255, 'g' => 242, 'b' => 185, 'alpha' => 0.08]],
        ['key' => 'herbst',     'tint' => ['r' => 255, 'g' => 215, 'b' => 175, 'alpha' => 0.09]],
        ['key' => 'hochzeit',   'tint' => ['r' => 255, 'g' => 235, 'b' => 240, 'alpha' => 0.08]],
        ['key' => 'geburtstag', 'tint' => ['r' => 255, 'g' => 240, 'b' => 195, 'alpha' => 0.08]],
        ['key' => 'muttertag',  'tint' => ['r' => 255, 'g' => 225, 'b' => 235, 'alpha' => 0.08]],
        ['key' => 'vatertag',   'tint' => ['r' => 215, 'g' => 235, 'b' => 250, 'alpha' => 0.08]],
    ];

    public function run(): void
    {
        $publicProductsPath = storage_path('app/public/produkte/products');
        File::ensureDirectoryExists($publicProductsPath);

        // 1. Der Seelen-Talisman: 2D-Overlays + 3D-GLB-Mesh
        $talismanDir = $publicProductsPath . '/seelen-talisman';
        File::ensureDirectoryExists($talismanDir);
        $this->ensureHeaderBackground($publicProductsPath, $talismanDir);

        $talismanOverlayPath = $talismanDir . '/overlay.png';
        if (!file_exists($talismanOverlayPath)) {
            $this->renderFallbackTalismanOverlay($talismanOverlayPath);
        }
        $this->ensureSeasonalOverlays($talismanDir);

        $talismanPngBytes = file_get_contents($talismanOverlayPath);
        $talismanMesh = $this->buildTalisman3DMesh();
        $talismanGlb = $this->buildSingleTexturedGlb($talismanMesh, $talismanPngBytes, 'SeelenTalisman');
        file_put_contents($talismanDir . '/seelen_talisman_3d.glb', $talismanGlb);

        // 2. Die Seelen-Pfote (Goldfarbener Edelstahl-Knochenanhänger 40x22x1,8 mm mit 5-teiligem Pfoten-Durchbruch unten rechts)
        $pfoteDir = $publicProductsPath . '/seelen-pfote-hundemarke';
        File::ensureDirectoryExists($pfoteDir);
        $this->ensureHeaderBackground($publicProductsPath, $pfoteDir);

        $pfoteOverlayPath = $pfoteDir . '/overlay.png';
        $this->renderPfoteGoldOverlay($pfoteOverlayPath);
        $this->ensureSeasonalOverlays($pfoteDir, true);

        $pfotePngBytes = file_get_contents($pfoteOverlayPath);
        $pfoteMesh = $this->buildPfote3DMesh();
        $pfoteGlb = $this->buildSingleTexturedGlb($pfoteMesh, $pfotePngBytes, 'SeelenPfote');
        file_put_contents($pfoteDir . '/seelen_pfote_3d.glb', $pfoteGlb);
        $this->ensurePfoteGoldGalleryAndTemplates($publicProductsPath, $pfoteDir);

        // 3. Das Goldene Seelen-Herz (Glücksfieber 14k Edelstahl-Herz mit Collierschlaufe, 25x22x4 mm)
        $goldHerzDir = $publicProductsPath . '/goldenes-seelen-herz';
        File::ensureDirectoryExists($goldHerzDir);
        $this->ensureHeaderBackground($publicProductsPath, $goldHerzDir);

        $goldHerzOverlayPath = $goldHerzDir . '/overlay.png';
        $this->renderGoldenesHerzOverlay($goldHerzOverlayPath);
        $this->ensureSeasonalOverlays($goldHerzDir, true);

        $goldHerzPngBytes = file_get_contents($goldHerzOverlayPath);
        $goldHerzMesh = $this->buildGoldenesHerz3DMesh();
        $goldHerzGlb = $this->buildSingleTexturedGlb($goldHerzMesh, $goldHerzPngBytes, 'GoldenesSeelenHerz');
        file_put_contents($goldHerzDir . '/goldenes_seelen_herz_3d.glb', $goldHerzGlb);
        $this->ensureGoldenesHerzGalleryAndTemplates($publicProductsPath, $goldHerzDir, false);

        // 3b. Das Echtgold Seelen-Herz (Atelier: 585er Echtgold-Herz 13x14 mm + 333er Echtgold-Panzerkette 0,8 mm)
        $echtgoldHerzDir = $publicProductsPath . '/echtgold-seelen-herz';
        File::ensureDirectoryExists($echtgoldHerzDir);
        $this->ensureHeaderBackground($publicProductsPath, $echtgoldHerzDir);

        $echtgoldOverlayPath = $echtgoldHerzDir . '/overlay.png';
        $this->renderGoldenesHerzOverlay($echtgoldOverlayPath);
        $this->ensureSeasonalOverlays($echtgoldHerzDir, true);

        $echtgoldPngBytes = file_get_contents($echtgoldOverlayPath);
        $echtgoldGlb = $this->buildSingleTexturedGlb($goldHerzMesh, $echtgoldPngBytes, 'EchtgoldSeelenHerz');
        file_put_contents($echtgoldHerzDir . '/echtgold_seelen_herz_3d.glb', $echtgoldGlb);
        $this->ensureEchtgoldHerzGalleryAndTemplates($publicProductsPath, $echtgoldHerzDir, false);

        // 3c. Das Echtsilber Seelen-Armband (FBM P001.53062HERZ.5.18.5: 925er Sterlingsilber, Figaro diamantiert 2,1 mm, 18,5 cm)
        $silberArmbandDir = $publicProductsPath . '/id-armband-silber-herz';
        File::ensureDirectoryExists($silberArmbandDir);
        $this->ensureHeaderBackground($publicProductsPath, $silberArmbandDir);

        $silberArmbandOverlayPath = $silberArmbandDir . '/overlay.png';
        $this->renderIdArmbandHerzOverlay($silberArmbandOverlayPath, 'silver');
        $this->ensureSeasonalOverlays($silberArmbandDir, true);

        $armbandMesh = $this->buildIdArmbandHerz3DMesh();
        $silberArmbandPngBytes = file_get_contents($silberArmbandOverlayPath);
        $silberArmbandGlb = $this->buildSingleTexturedGlb($armbandMesh, $silberArmbandPngBytes, 'IdArmbandSilberHerz');
        file_put_contents($silberArmbandDir . '/id_armband_silber_herz_3d.glb', $silberArmbandGlb);
        $this->ensureIdArmbandHerzGalleryAndTemplates($publicProductsPath, $silberArmbandDir, 'id-armband-silber-herz', 'silver', true);

        // 3d. Das Gold-Vermeil Seelen-Armband (FBM P020.53062HERZ.5.18.5: 925er Silber Gelb vergoldet, Figaro diamantiert 2,1 mm, 18,5 cm)
        $goldArmbandDir = $publicProductsPath . '/id-armband-gold-herz';
        File::ensureDirectoryExists($goldArmbandDir);
        $this->ensureHeaderBackground($publicProductsPath, $goldArmbandDir);

        $goldArmbandOverlayPath = $goldArmbandDir . '/overlay.png';
        $this->renderIdArmbandHerzOverlay($goldArmbandOverlayPath, 'gold');
        $this->ensureSeasonalOverlays($goldArmbandDir, true);

        $goldArmbandPngBytes = file_get_contents($goldArmbandOverlayPath);
        $goldArmbandGlb = $this->buildSingleTexturedGlb($armbandMesh, $goldArmbandPngBytes, 'IdArmbandGoldHerz');
        file_put_contents($goldArmbandDir . '/id_armband_gold_herz_3d.glb', $goldArmbandGlb);
        $this->ensureIdArmbandHerzGalleryAndTemplates($publicProductsPath, $goldArmbandDir, 'id-armband-gold-herz', 'gold', true);

        // 4. Saisonale Overlays auch für die bestehenden personalisierbaren Produkte sicherstellen
        foreach (['seelen-kristall', 'seelen-anhaenger', 'weizenglas-personalisiert'] as $slug) {
            $dir = $publicProductsPath . '/' . $slug;
            if (is_dir($dir) && file_exists($dir . '/overlay.png')) {
                $this->ensureSeasonalOverlays($dir, $slug === 'seelen-anhaenger');
            }
        }

        // 4a. Der Seelen Kristall (180 x 200 x 40 mm): Maßgetreues K9-Kristallglas 3D-GLB-Mesh & 100 % deckungsgleiches 2D-Overlay
        // mit 30-segmentigem Sonnenschliff-/Rautenschliff-Facettenrand und trapezförmigem 40 mm Kristallsockel
        $kristallDir = $publicProductsPath . '/seelen-kristall';
        File::ensureDirectoryExists($kristallDir);
        $kristallAtlasPng = $this->renderSeelenKristallAtlasPng();
        if ($kristallAtlasPng !== '') {
            $kristallMesh = $this->buildSeelenKristall3DMesh();
            $kristallGlb = $this->buildOpticalCrystalGlb($kristallMesh, $kristallAtlasPng, 'SeelenKristall');
            file_put_contents($kristallDir . '/t_seelenk.glb', $kristallGlb);
            $this->renderSeelenKristallOverlayPng($kristallDir . '/overlay.png', $kristallMesh, $kristallAtlasPng);
            $this->ensureSeasonalOverlays($kristallDir, true);
        }

        // 4b. Der Seelenanhänger: 1:1 zum 800x800 Echtfoto-Overlay deckungsgleiches 3D-GLB-Mesh erzeugen
        $anhaengerDir = $publicProductsPath . '/seelen-anhaenger';
        $anhaengerOverlayPath = $anhaengerDir . '/overlay.png';
        if (is_file($anhaengerOverlayPath)) {
            $anhaengerPngBytes = file_get_contents($anhaengerOverlayPath);
            $anhaengerMesh = $this->buildSeelenAnhaenger3DMesh();
            $anhaengerGlb = $this->buildSingleTexturedGlb($anhaengerMesh, $anhaengerPngBytes, 'SeelenAnhaenger');
            file_put_contents($anhaengerDir . '/seelenanhaenger_3d_.glb', $anhaengerGlb);
        }

        // 5. Technische Maßblätter (*-dimensions.jpg) für alle physischen Produkte sicherstellen
        $this->generateAllDimensionSheets($publicProductsPath);

        // 6. Alle Lifestyle-/Hoverbilder (*-lifestyle.jpg) web-optimiert & komprimiert (800x800 Progressive JPEG) halten
        $this->optimizeLifestyleHoverImages($publicProductsPath);

        // 7. Wunschsymbole für den Neulings-Modus & Konfigurator-Symbolbibliothek sicherstellen (Kleeblatt, Schutzengel, Lebensbaum, Krone, Anker)
        $this->ensureLaserSymbolVectors();

        // 8. Urheberrechts-Wasserzeichen (glTF 2.0 asset.copyright) in allen .glb-3D-Modellen sicherstellen
        $this->ensureAllGlbCopyrightWatermarks($publicProductsPath);
    }

    /**
     * Bettet das gerichtsfeste glTF-2.0 Urheberrechts-Wasserzeichen (`asset.copyright` & `asset.extras.rights_holder`)
     * verlustfrei in den Binär-Header sämtlicher bestehenden `.glb`-Dateien ein.
     */
    private function ensureAllGlbCopyrightWatermarks(string $publicProductsPath): void
    {
        $optimizer = app(\App\Services\Storage\GlbOptimizerService::class);
        $roots = array_filter([
            $publicProductsPath,
            public_path('shop/customer/gamification/images'),
        ], 'is_dir');

        foreach ($roots as $rootDir) {
            foreach (File::allFiles($rootDir) as $file) {
                if (strtolower($file->getExtension()) === 'glb') {
                    $optimizer->stampCopyrightWatermark($file->getRealPath() ?: $file->getPathname());
                }
            }
        }
    }

    /**
     * Stellt sicher, dass die echten, lasertauglichen (rein gefüllten, XCS-kompatiblen) Vektor-Symbole
     * für Kleeblatt, Schutzengel, Lebensbaum, Krone und Anker in Storage, Public und manifest.json existieren.
     */
    private function ensureLaserSymbolVectors(): void
    {
        $vectors = [
            'Symbole/kleeblatt.svg' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="#000000">
  <path fill="#000000" d="M 28.5 25.5 C 21 23, 8 18, 9 11 C 9.8 6.5, 14.5 6.5, 17 10 C 13.5 7.5, 13.5 2.8, 18 2 C 25 1, 30 14, 28.5 25.5 Z"/>
  <path fill="#000000" d="M 31.5 25.5 C 39 23, 52 18, 51 11 C 50.2 6.5, 45.5 6.5, 43 10 C 46.5 7.5, 46.5 2.8, 42 2 C 35 1, 30 14, 31.5 25.5 Z"/>
  <path fill="#000000" d="M 28.5 28.5 C 21 31, 8 36, 9 43 C 9.8 47.5, 14.5 47.5, 17 44 C 13.5 46.5, 13.5 51.2, 18 52 C 25 53, 30 40, 28.5 28.5 Z"/>
  <path fill="#000000" d="M 31.5 28.5 C 39 31, 52 36, 51 43 C 50.2 47.5, 45.5 47.5, 43 44 C 46.5 46.5, 46.5 51.2, 42 52 C 35 53, 30 40, 31.5 28.5 Z"/>
  <circle fill="#000000" cx="30" cy="27" r="3.2"/>
  <path fill="#000000" d="M 28.8 29 C 29 41, 33 52, 41.5 61.5 C 42.5 62.5, 45 60.5, 43.8 59 C 36.2 50.5, 32.5 40.5, 31.5 29 Z"/>
</svg>
SVG,
            'Symbole/schutzengel.svg' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="#000000">
  <path fill="#000000" fill-rule="evenodd" d="M 32 3 C 24.5 3, 20 5.2, 20 8 C 20 10.8, 24.5 13, 32 13 C 39.5 13, 44 10.8, 44 8 C 44 5.2, 39.5 3, 32 3 Z M 32 5.6 C 37.2 5.6, 40.5 6.7, 40.5 8 C 40.5 9.3, 37.2 10.4, 32 10.4 C 26.8 10.4, 23.5 9.3, 23.5 8 C 23.5 6.7, 26.8 5.6, 32 5.6 Z"/>
  <circle fill="#000000" cx="32" cy="20" r="5.5"/>
  <path fill="#000000" d="M 25.5 26 C 18 19, 6 16, 3 22 C 1.5 25, 5 28.5, 9.5 29.5 C 5 30.5, 3.5 34, 6.5 36.5 C 9 38.5, 13.5 38.5, 16.5 38 C 12.5 40, 12 43.5, 15.5 45 C 19.5 46.5, 24.5 42, 26.5 36 Z"/>
  <path fill="#000000" d="M 38.5 26 C 46 19, 58 16, 61 22 C 62.5 25, 59 28.5, 54.5 29.5 C 59 30.5, 60.5 34, 57.5 36.5 C 55 38.5, 50.5 38.5, 47.5 38 C 51.5 40, 52 43.5, 48.5 45 C 44.5 46.5, 39.5 42, 37.5 36 Z"/>
  <path fill="#000000" d="M 27 27.5 C 29 26.2, 35 26.2, 37 27.5 L 43.5 57 C 44 59.5, 42 61, 39.5 61 L 24.5 61 C 22 61, 20 59.5, 20.5 57 Z"/>
</svg>
SVG,
            'Natur/lebensbaum.svg' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="#000000">
  <path fill="#000000" fill-rule="evenodd" d="M 32 2 C 15.4 2, 2 15.4, 2 32 C 2 48.6, 15.4 62, 32 62 C 48.6 62, 62 48.6, 62 32 C 62 15.4, 48.6 2, 32 2 Z M 32 6 C 46.4 6, 58 17.6, 58 32 C 58 46.4, 46.4 58, 32 58 C 17.6 58, 6 46.4, 6 32 C 6 17.6, 17.6 6, 32 6 Z"/>
  <path fill="#000000" d="M 29.5 48 C 29.8 41, 29.8 35, 29 30 C 25 26, 19 23, 15 19 L 17.5 16.8 C 21.5 20.5, 26.5 23.5, 30 26.5 C 30.5 21, 28 15, 25 11 L 28 9.5 C 30.8 14, 32 19, 32 24 C 32 19, 33.2 14, 36 9.5 L 39 11 C 36 15, 33.5 21, 34 26.5 C 37.5 23.5, 42.5 20.5, 46.5 16.8 L 49 19 C 45 23, 39 26, 35 30 C 34.2 35, 34.2 41, 34.5 48 C 38 51, 43 53.5, 47 55 L 45.5 57.5 C 40.5 55.5, 36 53, 33 50.5 L 33 57 L 31 57 L 31 50.5 C 28 53, 23.5 55.5, 18.5 57.5 L 17 55 C 21 53.5, 26 51, 29.5 48 Z"/>
  <circle fill="#000000" cx="32" cy="12" r="4.2"/>
  <circle fill="#000000" cx="22" cy="14" r="4.0"/>
  <circle fill="#000000" cx="42" cy="14" r="4.0"/>
  <circle fill="#000000" cx="15" cy="21" r="4.2"/>
  <circle fill="#000000" cx="49" cy="21" r="4.2"/>
  <circle fill="#000000" cx="25" cy="22" r="3.6"/>
  <circle fill="#000000" cx="39" cy="22" r="3.6"/>
  <circle fill="#000000" cx="13" cy="29" r="3.5"/>
  <circle fill="#000000" cx="51" cy="29" r="3.5"/>
</svg>
SVG,
            'Symbole/krone.svg' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="#000000">
  <circle fill="#000000" cx="8" cy="20" r="3.2"/>
  <circle fill="#000000" cx="20" cy="14" r="3.2"/>
  <circle fill="#000000" cx="32" cy="10" r="3.6"/>
  <circle fill="#000000" cx="44" cy="14" r="3.2"/>
  <circle fill="#000000" cx="56" cy="20" r="3.2"/>
  <path fill="#000000" d="M 9 24 L 18 35 L 20.5 18 L 27 33 L 32 14.5 L 37 33 L 43.5 18 L 46 35 L 55 24 L 51 46 L 13 46 Z"/>
  <rect fill="#000000" x="12" y="49" width="40" height="6" rx="2.5"/>
</svg>
SVG,
            'Symbole/anker.svg' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="#000000">
  <path fill="#000000" fill-rule="evenodd" d="M 32 4 C 27.6 4, 24 7.6, 24 12 C 24 15.6, 26.4 18.7, 29.7 19.7 L 29.7 24 L 20 24 C 18.6 24, 17.5 25.1, 17.5 26.5 C 17.5 27.9, 18.6 29, 20 29 L 29.7 29 L 29.7 51.5 C 22.5 50.4, 16.8 45.2, 15.2 38.2 L 19 39.2 L 12.5 30.5 L 7 39.8 L 10.6 38.7 C 12.6 48.8, 21.3 56.5, 32 56.5 C 42.7 56.5, 51.4 48.8, 53.4 38.7 L 57 39.8 L 51.5 30.5 L 45 39.2 L 48.8 38.2 C 47.2 45.2, 41.5 50.4, 34.3 51.5 L 34.3 29 L 44 29 C 45.4 29, 46.5 27.9, 46.5 26.5 C 46.5 25.1, 45.4 24, 44 24 L 34.3 24 L 34.3 19.7 C 37.6 18.7, 40 15.6, 40 12 C 40 7.6, 36.4 4, 32 4 Z M 32 8 C 34.2 8, 36 9.8, 36 12 C 36 14.2, 34.2 16, 32 16 C 29.8 16, 28 14.2, 28 12 C 28 9.8, 29.8 8, 32 8 Z"/>
</svg>
SVG,
        ];

        $storageRoot = storage_path('app/public/produkte/configurator/vectors');
        $publicRoot = public_path('shop/product/configurator/vectors');

        foreach ($vectors as $relPath => $svgContent) {
            foreach ([$storageRoot, $publicRoot] as $root) {
                $targetPath = $root . '/' . $relPath;
                File::ensureDirectoryExists(dirname($targetPath));
                file_put_contents($targetPath, trim($svgContent) . "\n");
            }
        }

        try {
            app(\App\Services\ProductConfiguratorService::class)->ensureVectorsInitialized();
        } catch (\Throwable $e) {
            // Ignorieren falls Service während frühen Migrationsläufen noch nicht initialisiert werden kann
        }
    }

    /**
     * Erzeugt das exakt zum 800x800 Echtfoto-Overlay (`seelen-anhaenger/overlay.png`) deckungsgleiche
     * 3D-Mesh für Den Seelenanhänger (Herz-Korpus y=418..772, Drehwirbel-Kette y=225..445, Spaltring y=28..256).
     * Da min(y)+max(y) = 28+772 = 800 und min(x)+max(x) = 207+593 = 800 gilt, liegt die Bounding-Box-Mitte
     * exakt bei (400, 400), sodass 2D-Overlay und 3D-Ansicht zu 100,0 % deckungsgleich sind.
     */
    private function buildSeelenAnhaenger3DMesh(): array
    {
        $positions = [];
        $normals = [];
        $uvs = [];
        $indices = [];

        $innerPct = [
            [50.0, 59.45], [51.13, 58.73], [52.23, 58.01], [53.34, 57.29], [54.54, 56.57], [55.82, 55.84],
            [57.26, 55.12], [58.83, 54.52], [60.42, 54.19], [62.68, 54.48], [64.54, 55.02], [66.13, 55.74],
            [67.49, 56.57], [68.67, 57.6], [69.63, 58.73], [70.38, 59.97], [70.95, 61.31], [71.41, 62.75],
            [71.7, 64.3], [71.86, 65.85], [71.86, 67.39], [71.66, 68.94], [71.35, 70.59], [70.87, 72.34],
            [70.21, 74.1], [69.35, 75.95], [68.31, 77.81], [67.12, 79.67], [65.72, 81.52], [64.07, 83.38],
            [62.27, 85.23], [60.21, 87.09], [57.94, 88.95], [55.57, 90.7], [53.47, 92.25], [51.55, 93.55],
            [50.0, 94.52], [48.45, 93.55], [46.53, 92.25], [44.43, 90.7], [42.06, 88.95], [39.79, 87.09],
            [37.73, 85.23], [35.93, 83.38], [34.28, 81.52], [32.88, 79.67], [31.68, 77.81], [30.65, 75.95],
            [29.79, 74.1], [29.13, 72.34], [28.65, 70.59], [28.34, 68.94], [28.14, 67.39], [28.14, 65.85],
            [28.3, 64.3], [28.59, 62.75], [29.04, 61.31], [29.62, 59.97], [30.37, 58.73], [31.33, 57.6],
            [32.51, 56.57], [33.87, 55.74], [35.46, 55.02], [37.32, 54.48], [39.58, 54.19], [41.17, 54.52],
            [42.74, 55.12], [44.18, 55.84], [45.46, 56.57], [46.66, 57.29], [47.77, 58.01], [48.87, 58.73],
        ];

        $innerPx = array_map(static fn(array $p): array => [$p[0] * 8.0, $p[1] * 8.0], $innerPct);
        $n = count($innerPx);
        $heartOuter = [];
        for ($i = 0; $i < $n; $i++) {
            $prev = $innerPx[($i - 1 + $n) % $n];
            $curr = $innerPx[$i];
            $next = $innerPx[($i + 1) % $n];
            $dx = $next[0] - $prev[0];
            $dy = $next[1] - $prev[1];
            $len = hypot($dx, $dy) ?: 1.0;
            // Im Uhrzeigersinn zeigt (dy, -dx) nach außen
            $nx = $dy / $len;
            $ny = -$dx / $len;
            $ox = $curr[0] + $nx * 16.0;
            $oy = $curr[1] + $ny * 15.8;
            if ($i === 0) {
                $ox = 400.0;
                $oy = 445.0;
            } elseif ($i === 36) {
                $ox = 400.0;
                $oy = 772.0;
            }
            $heartOuter[] = [max(207.0, min(593.0, $ox)), max(418.0, min(772.0, $oy))];
        }

        // 1. Massiver Herz-Korpus (zHalf = 0.032)
        $this->extrudePixelContour($heartOuter, null, [400.0, 595.0], 0.032, $positions, $normals, $uvs, $indices, 1.5);

        // 2. Drehwirbel-Kette & Verbindungsglieder (x = 376..424, y = 225..452)
        $chainPts = [
            [376.0, 225.0], [424.0, 225.0], [425.0, 338.0], [424.0, 452.0], [376.0, 452.0], [375.0, 338.0],
        ];
        $this->extrudePixelContour($chainPts, null, [400.0, 338.0], 0.026, $positions, $normals, $uvs, $indices, 1.0);

        // 3. Oberer Spaltring (cx=407.0, cy=142.0, rxOut=120.0, ryOut=114.0 -> min(y)=28.0, max(y)=256.0)
        $ringSteps = 72;
        $ringOuter = [];
        $ringInner = [];
        for ($i = 0; $i < $ringSteps; $i++) {
            $a = ($i / $ringSteps) * M_PI * 2.0;
            $ringOuter[] = [407.0 + 120.0 * cos($a), 142.0 + 114.0 * sin($a)];
            $ringInner[] = [407.0 + 93.0 * cos($a), 142.0 + 84.0 * sin($a)];
        }
        $this->extrudePixelContour($ringOuter, $ringInner, [407.0, 142.0], 0.028, $positions, $normals, $uvs, $indices, 1.2);

        return compact('positions', 'normals', 'uvs', 'indices');
    }

    /**
     * Komprimiert und optimiert alle Lifestyle-/Anwendungsfall-Hoverbilder (*-lifestyle.jpg)
     * auf 800x800 Pixel Progressive-JPEG (Qualität 80), damit der Hover-Effekt im Shop verzögerungsfrei lädt.
     */
    private function optimizeLifestyleHoverImages(string $publicProductsPath): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        foreach (glob($publicProductsPath . '/*/*-lifestyle.jpg') ?: [] as $filePath) {
            if (!is_file($filePath)) {
                continue;
            }
            $info = @getimagesize($filePath);
            $sizeBytes = @filesize($filePath) ?: 0;
            if ($info && $info[0] === 800 && $info[1] === 800 && $sizeBytes > 0 && $sizeBytes <= 145000) {
                continue;
            }

            $src = @imagecreatefromjpeg($filePath);
            if (!$src) {
                continue;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $dst = imagecreatetruecolor(800, 800);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, 800, 800, $w, $h);
            imageinterlace($dst, true);
            imagejpeg($dst, $filePath, 80);
            imagedestroy($src);
            imagedestroy($dst);
            clearstatcache(true, $filePath);
        }
    }

    /**
     * Kopiert header_bg.png aus der Referenz, falls im Produktordner noch nicht vorhanden.
     */
    private function ensureHeaderBackground(string $publicProductsPath, string $targetDir): void
    {
        $refBg = $publicProductsPath . '/seelen-anhaenger/header_bg.png';
        $targetBg = $targetDir . '/header_bg.png';
        if (file_exists($refBg) && !file_exists($targetBg)) {
            copy($refBg, $targetBg);
        }
    }

    /**
     * Erzeugt fehlende saisonale Overlay-Varianten (overlay_{theme}.png) aus der Basisdatei overlay.png mittels GD.
     */
    private function ensureSeasonalOverlays(string $productDir, bool $force = false): void
    {
        $baseOverlayPath = $productDir . '/overlay.png';
        if (!file_exists($baseOverlayPath)) {
            return;
        }

        foreach (self::THEMES as $theme) {
            $outPath = $productDir . '/overlay_' . $theme['key'] . '.png';
            if (file_exists($outPath) && !$force) {
                continue;
            }

            if ($theme['tint'] === null) {
                copy($baseOverlayPath, $outPath);
                continue;
            }

            if (!extension_loaded('gd')) {
                copy($baseOverlayPath, $outPath);
                continue;
            }

            $src = @imagecreatefrompng($baseOverlayPath);
            if (!$src) {
                copy($baseOverlayPath, $outPath);
                continue;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            imagealphablending($src, false);
            imagesavealpha($src, true);

            $tr = $theme['tint']['r'];
            $tg = $theme['tint']['g'];
            $tb = $theme['tint']['b'];
            $tAlpha = (float) $theme['tint']['alpha'];
            $invAlpha = 1.0 - $tAlpha;

            // Atop-Tinting über alle sichtbaren Pixel (Alpha < 127)
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $rgba = imagecolorat($src, $x, $y);
                    $a = ($rgba >> 24) & 0x7F;
                    if ($a >= 127) {
                        continue;
                    }
                    $r = ($rgba >> 16) & 0xFF;
                    $g = ($rgba >> 8) & 0xFF;
                    $b = $rgba & 0xFF;

                    $nr = (int) min(255, round($r * $invAlpha + $tr * $tAlpha));
                    $ng = (int) min(255, round($g * $invAlpha + $tg * $tAlpha));
                    $nb = (int) min(255, round($b * $invAlpha + $tb * $tAlpha));

                    $col = ($a << 24) | ($nr << 16) | ($ng << 8) | $nb;
                    imagesetpixel($src, $x, $y, $col);
                }
            }

            imagepng($src, $outPath, 9);
            imagedestroy($src);
        }
    }

    /**
     * Erzeugt ein echtes 3D-Extrusions-Mesh direkt aus einer 2D-Konturliste in 800x800 Pixelkoordinaten.
     * Jedes Vertex (px, py) erhält exakt:
     *   X = (px - 400) / 360
     *   Y = (400 - py) / 360
     *   u = px / 800
     *   v = py / 800
     * Dadurch ist das 3D-Modell zu 100,0% deckungsgleich mit dem 800x800 2D-Overlay-Bild.
     */
    private function extrudePixelContour(
        array $outerPts,
        ?array $holePts,
        array $centerPx,
        float $zHalf,
        array &$positions,
        array &$normals,
        array &$uvs,
        array &$indices,
        float $uvInsetPx = 0.0
    ): void {
        $toX = static fn(float $px): float => ($px - 400.0) / 360.0;
        $toY = static fn(float $py): float => (400.0 - $py) / 360.0;
        $toU = static fn(float $px): float => $px / 800.0;
        $toV = static fn(float $py): float => $py / 800.0;

        $insetToward = static function (float $px, float $py, float $tx, float $ty, float $inset): array {
            if ($inset <= 0.0) {
                return [$px, $py];
            }
            $dx = $tx - $px;
            $dy = $ty - $py;
            $len = hypot($dx, $dy);
            if ($len <= 1e-4) {
                return [$px, $py];
            }
            $step = min($inset, $len * 0.5);
            return [$px + ($dx / $len) * $step, $py + ($dy / $len) * $step];
        };

        $n = count($outerPts);

        if ($holePts === null) {
            // 1. Vorderseite (+zHalf) als Fan um centerPx
            $fCenterIdx = intdiv(count($positions), 3);
            array_push($positions, $toX($centerPx[0]), $toY($centerPx[1]), $zHalf);
            array_push($normals, 0.0, 0.0, 1.0);
            array_push($uvs, $toU($centerPx[0]), $toV($centerPx[1]));

            $fRingStart = intdiv(count($positions), 3);
            for ($i = 0; $i < $n; $i++) {
                [$px, $py] = $outerPts[$i];
                [$upx, $upy] = $insetToward($px, $py, $centerPx[0], $centerPx[1], $uvInsetPx);
                array_push($positions, $toX($px), $toY($py), $zHalf);
                array_push($normals, 0.0, 0.0, 1.0);
                array_push($uvs, $toU($upx), $toV($upy));
            }
            for ($i = 0; $i < $n; $i++) {
                $next = ($i + 1) % $n;
                array_push($indices, $fCenterIdx, $fRingStart + $i, $fRingStart + $next);
            }

            // 2. Rückseite (-zHalf)
            $bCenterIdx = intdiv(count($positions), 3);
            array_push($positions, $toX($centerPx[0]), $toY($centerPx[1]), -$zHalf);
            array_push($normals, 0.0, 0.0, -1.0);
            array_push($uvs, $toU($centerPx[0]), $toV($centerPx[1]));

            $bRingStart = intdiv(count($positions), 3);
            for ($i = 0; $i < $n; $i++) {
                [$px, $py] = $outerPts[$i];
                [$upx, $upy] = $insetToward($px, $py, $centerPx[0], $centerPx[1], $uvInsetPx);
                array_push($positions, $toX($px), $toY($py), -$zHalf);
                array_push($normals, 0.0, 0.0, -1.0);
                array_push($uvs, $toU($upx), $toV($upy));
            }
            for ($i = 0; $i < $n; $i++) {
                $next = ($i + 1) % $n;
                array_push($indices, $bCenterIdx, $bRingStart + $next, $bRingStart + $i);
            }
        } else {
            // Ring-Topologie (outerPts + holePts gleicher Länge n)
            // 1. Vorderseite (+zHalf)
            $fStart = intdiv(count($positions), 3);
            for ($i = 0; $i < $n; $i++) {
                [$ox, $oy] = $outerPts[$i];
                [$hx, $hy] = $holePts[$i];
                $mx = ($ox + $hx) * 0.5;
                $my = ($oy + $hy) * 0.5;
                [$uox, $uoy] = $insetToward($ox, $oy, $mx, $my, $uvInsetPx);
                [$uhx, $uhy] = $insetToward($hx, $hy, $mx, $my, $uvInsetPx);
                array_push($positions, $toX($ox), $toY($oy), $zHalf);
                array_push($normals, 0.0, 0.0, 1.0);
                array_push($uvs, $toU($uox), $toV($uoy));

                array_push($positions, $toX($hx), $toY($hy), $zHalf);
                array_push($normals, 0.0, 0.0, 1.0);
                array_push($uvs, $toU($uhx), $toV($uhy));
            }
            for ($i = 0; $i < $n; $i++) {
                $next = ($i + 1) % $n;
                $o0 = $fStart + $i * 2;
                $h0 = $fStart + $i * 2 + 1;
                $o1 = $fStart + $next * 2;
                $h1 = $fStart + $next * 2 + 1;
                array_push($indices, $o0, $h0, $o1, $h0, $h1, $o1);
            }

            // 2. Rückseite (-zHalf)
            $bStart = intdiv(count($positions), 3);
            for ($i = 0; $i < $n; $i++) {
                [$ox, $oy] = $outerPts[$i];
                [$hx, $hy] = $holePts[$i];
                $mx = ($ox + $hx) * 0.5;
                $my = ($oy + $hy) * 0.5;
                [$uox, $uoy] = $insetToward($ox, $oy, $mx, $my, $uvInsetPx);
                [$uhx, $uhy] = $insetToward($hx, $hy, $mx, $my, $uvInsetPx);
                array_push($positions, $toX($ox), $toY($oy), -$zHalf);
                array_push($normals, 0.0, 0.0, -1.0);
                array_push($uvs, $toU($uox), $toV($uoy));

                array_push($positions, $toX($hx), $toY($hy), -$zHalf);
                array_push($normals, 0.0, 0.0, -1.0);
                array_push($uvs, $toU($uhx), $toV($uhy));
            }
            for ($i = 0; $i < $n; $i++) {
                $next = ($i + 1) % $n;
                $o0 = $bStart + $i * 2;
                $h0 = $bStart + $i * 2 + 1;
                $o1 = $bStart + $next * 2;
                $h1 = $bStart + $next * 2 + 1;
                array_push($indices, $o0, $o1, $h0, $h0, $o1, $h1);
            }

            // Innenwand des Lochs
            for ($i = 0; $i < $n; $i++) {
                $next = ($i + 1) % $n;
                [$x0, $y0] = $holePts[$i];
                [$x1, $y1] = $holePts[$next];
                [$ox0, $oy0] = $outerPts[$i];
                [$ox1, $oy1] = $outerPts[$next];
                [$ux0, $uy0] = $insetToward($x0, $y0, ($x0 + $ox0) * 0.5, ($y0 + $oy0) * 0.5, max(1.5, $uvInsetPx));
                [$ux1, $uy1] = $insetToward($x1, $y1, ($x1 + $ox1) * 0.5, ($y1 + $oy1) * 0.5, max(1.5, $uvInsetPx));
                $dx = $toX($x1) - $toX($x0);
                $dy = $toY($y1) - $toY($y0);
                $len = hypot($dx, $dy) ?: 1.0;
                $nx = -$dy / $len;
                $ny = $dx / $len;

                $vBase = intdiv(count($positions), 3);
                array_push(
                    $positions,
                    $toX($x0), $toY($y0), $zHalf,
                    $toX($x1), $toY($y1), $zHalf,
                    $toX($x1), $toY($y1), -$zHalf,
                    $toX($x0), $toY($y0), -$zHalf
                );
                for ($k = 0; $k < 4; $k++) {
                    array_push($normals, $nx, $ny, 0.0);
                }
                array_push($uvs, $toU($ux0), $toV($uy0), $toU($ux1), $toV($uy1), $toU($ux1), $toV($uy1), $toU($ux0), $toV($uy0));
                array_push($indices, $vBase, $vBase + 1, $vBase + 2, $vBase, $vBase + 2, $vBase + 3);
            }
        }

        // 3. Außenwand (Seitenkante in Z)
        for ($i = 0; $i < $n; $i++) {
            $next = ($i + 1) % $n;
            [$x0, $y0] = $outerPts[$i];
            [$x1, $y1] = $outerPts[$next];
            $tx0 = $holePts !== null ? ($x0 + $holePts[$i][0]) * 0.5 : $centerPx[0];
            $ty0 = $holePts !== null ? ($y0 + $holePts[$i][1]) * 0.5 : $centerPx[1];
            $tx1 = $holePts !== null ? ($x1 + $holePts[$next][0]) * 0.5 : $centerPx[0];
            $ty1 = $holePts !== null ? ($y1 + $holePts[$next][1]) * 0.5 : $centerPx[1];
            [$ux0, $uy0] = $insetToward($x0, $y0, $tx0, $ty0, max(1.5, $uvInsetPx));
            [$ux1, $uy1] = $insetToward($x1, $y1, $tx1, $ty1, max(1.5, $uvInsetPx));
            $dx = $toX($x1) - $toX($x0);
            $dy = $toY($y1) - $toY($y0);
            $len = hypot($dx, $dy) ?: 1.0;
            $nx = $dy / $len;
            $ny = -$dx / $len;

            $vBase = intdiv(count($positions), 3);
            array_push(
                $positions,
                $toX($x0), $toY($y0), $zHalf,
                $toX($x0), $toY($y0), -$zHalf,
                $toX($x1), $toY($y1), -$zHalf,
                $toX($x1), $toY($y1), $zHalf
            );
            for ($k = 0; $k < 4; $k++) {
                array_push($normals, $nx, $ny, 0.0);
            }
            array_push($uvs, $toU($ux0), $toV($uy0), $toU($ux0), $toV($uy0), $toU($ux1), $toV($uy1), $toU($ux1), $toV($uy1));
            array_push($indices, $vBase, $vBase + 1, $vBase + 2, $vBase, $vBase + 2, $vBase + 3);
        }
    }

    /**
     * Erzeugt die exakt zum 2D-Overlay (y = 40..760) passende 3D-Kontur der Gold-Platte (mit echtem Stanzloch),
     * des großen flachen Schlüsselrings und des kleinen Biegerings.
     */
    private function buildTalisman3DMesh(): array
    {
        $positions = [];
        $normals = [];
        $uvs = [];
        $indices = [];

        $plateOuter = [];
        $stepsArc = 32;
        $stepsSide = 16;

        // Oben: Kreisbogen von (271, 378) über (400, 322) nach (529, 378)
        $rArc = 176.0;
        $cyTop = 498.0;
        $aTopStart = atan2(378.0 - $cyTop, 271.0 - 400.0);
        $aTopEnd = atan2(378.0 - $cyTop, 529.0 - 400.0);
        for ($i = 0; $i <= $stepsArc; $i++) {
            $a = $aTopStart + ($aTopEnd - $aTopStart) * ($i / $stepsArc);
            $plateOuter[] = [400.0 + $rArc * cos($a), $cyTop + $rArc * sin($a)];
        }

        // Rechte gerade Kante von (529, 378) nach (529, 704)
        for ($i = 1; $i < $stepsSide; $i++) {
            $t = $i / $stepsSide;
            $plateOuter[] = [529.0, 378.0 + (704.0 - 378.0) * $t];
        }

        // Unten: Kreisbogen von (529, 704) über (400, 760) nach (271, 704)
        $cyBot = 584.0;
        $aBotStart = atan2(704.0 - $cyBot, 529.0 - 400.0);
        $aBotEnd = atan2(704.0 - $cyBot, 271.0 - 400.0);
        for ($i = 0; $i <= $stepsArc; $i++) {
            $a = $aBotStart + ($aBotEnd - $aBotStart) * ($i / $stepsArc);
            $plateOuter[] = [400.0 + $rArc * cos($a), $cyBot + $rArc * sin($a)];
        }

        // Linke gerade Kante von (271, 704) nach (271, 378)
        for ($i = 1; $i < $stepsSide; $i++) {
            $t = $i / $stepsSide;
            $plateOuter[] = [271.0, 704.0 - (704.0 - 378.0) * $t];
        }

        // Passendes Innenloch (Stanzloch bei cx=400, cy=352.5, r=16.5)
        $holePts = array_map(static function (array $pt): array {
            $ang = atan2($pt[1] - 352.5, $pt[0] - 400.0);
            return [400.0 + 16.5 * cos($ang), 352.5 + 16.5 * sin($ang)];
        }, $plateOuter);

        $this->extrudePixelContour($plateOuter, $holePts, [400.0, 400.0], 0.032, $positions, $normals, $uvs, $indices);

        // 2. Großer flacher Schlüsselring oben (cx=400, cy=184, R_out=144, R_in=113)
        $ringSteps = 72;
        $ringOuter = [];
        $ringInner = [];
        for ($i = 0; $i < $ringSteps; $i++) {
            $a = ($i / $ringSteps) * M_PI * 2.0;
            $ringOuter[] = [400.0 + 144.0 * cos($a), 184.0 + 144.0 * sin($a)];
            $ringInner[] = [400.0 + 113.0 * cos($a), 184.0 + 113.0 * sin($a)];
        }
        $this->extrudePixelContour($ringOuter, $ringInner, [400.0, 184.0], 0.030, $positions, $normals, $uvs, $indices);

        // 3. Kleiner Verbindungsring / Biegering (cx=400, cy=322, Rx=21, Ry=32)
        $jumpOuter = [];
        $jumpInner = [];
        $jumpSteps = 36;
        for ($i = 0; $i < $jumpSteps; $i++) {
            $a = ($i / $jumpSteps) * M_PI * 2.0;
            $jumpOuter[] = [400.0 + 21.0 * cos($a), 322.0 + 32.0 * sin($a)];
            $jumpInner[] = [400.0 + 11.0 * cos($a), 322.0 + 22.0 * sin($a)];
        }
        $this->extrudePixelContour($jumpOuter, $jumpInner, [400.0, 322.0], 0.031, $positions, $normals, $uvs, $indices);

        return compact('positions', 'normals', 'uvs', 'indices');
    }

    /**
     * Liefert die exakt nach dem realen Gold-Rohling (40 x 22 mm) vermessene, um (400, 400) zentrierte
     * 800x800 Außenkontur der Seelen-Pfote (X = 80.0 .. 720.0, Y = 238.0 .. 562.0 => min+max = 800).
     */
    private function getPfoteOuterPoints(int $stepsPerSegment = 18): array
    {
        $pts = [];
        $sampleCubic = static function (array $p0, array $p1, array $p2, array $p3, int $steps) use (&$pts): void {
            for ($i = 1; $i <= $steps; $i++) {
                $t = $i / $steps;
                $mt = 1.0 - $t;
                $x = $mt * $mt * $mt * $p0[0] + 3.0 * $mt * $mt * $t * $p1[0] + 3.0 * $mt * $t * $t * $p2[0] + $t * $t * $t * $p3[0];
                $y = $mt * $mt * $mt * $p0[1] + 3.0 * $mt * $mt * $t * $p1[1] + 3.0 * $mt * $t * $t * $p2[1] + $t * $t * $t * $p3[1];
                $pts[] = [$x, $y];
            }
        };

        $pts[] = [342.0, 282.0];
        $sampleCubic([342.0, 282.0], [358.0, 282.0], [364.0, 238.0], [400.0, 238.0], 14);
        $sampleCubic([400.0, 238.0], [436.0, 238.0], [442.0, 282.0], [458.0, 282.0], 14);
        $pts[] = [516.0, 282.0];
        $sampleCubic([516.0, 282.0], [552.0, 282.0], [574.0, 238.0], [618.0, 238.0], $stepsPerSegment);
        $sampleCubic([618.0, 238.0], [676.0, 238.0], [720.0, 274.0], [720.0, 326.0], $stepsPerSegment);
        $sampleCubic([720.0, 326.0], [720.0, 366.0], [704.5, 384.0], [704.5, 400.0], 14);
        $sampleCubic([704.5, 400.0], [704.5, 416.0], [720.0, 434.0], [720.0, 474.0], 14);
        $sampleCubic([720.0, 474.0], [720.0, 526.0], [676.0, 562.0], [618.0, 562.0], $stepsPerSegment);
        $sampleCubic([618.0, 562.0], [574.0, 562.0], [552.0, 518.0], [516.0, 518.0], $stepsPerSegment);
        $pts[] = [284.0, 518.0];
        $sampleCubic([284.0, 518.0], [248.0, 518.0], [226.0, 562.0], [182.0, 562.0], $stepsPerSegment);
        $sampleCubic([182.0, 562.0], [124.0, 562.0], [80.0, 526.0], [80.0, 474.0], $stepsPerSegment);
        $sampleCubic([80.0, 474.0], [80.0, 434.0], [95.5, 416.0], [95.5, 400.0], 14);
        $sampleCubic([95.5, 400.0], [95.5, 384.0], [80.0, 366.0], [80.0, 326.0], 14);
        $sampleCubic([80.0, 326.0], [80.0, 274.0], [124.0, 238.0], [182.0, 238.0], $stepsPerSegment);
        $sampleCubic([182.0, 238.0], [226.0, 238.0], [248.0, 282.0], [284.0, 282.0], $stepsPerSegment);

        return $pts;
    }

    /**
     * Liefert die 2D-Konturpunkte des nieren-/herzförmigen Hauptballens unten rechts (exakt nach Foto-Vorlage).
     */
    private function getPfoteMainPadPoints(int $steps = 48): array
    {
        $padCx = 634.8;
        $padCy = 478.9;
        $pts = [];
        for ($i = 0; $i < $steps; $i++) {
            $theta = ($i / $steps) * 2.0 * M_PI;
            $delta = $theta - (M_PI / 4.0);
            $r = 25.5
                - 3.2 * cos($delta)
                - 1.2 * cos(2.0 * $delta)
                - 6.8 * cos(3.0 * $delta)
                + 2.0 * cos(4.0 * $delta);
            $pts[] = [$padCx + $r * cos($theta), $padCy + $r * sin($theta)];
        }

        return $pts;
    }

    /**
     * Liefert die 4 ovalen Zehenballen-Durchbrüche [cx, cy, rx, ry, deg] im unteren rechten Knochen-Bogen.
     */
    private function getPfoteToeDefinitions(): array
    {
        return [
            [581.9, 489.9, 13.6, 9.6, 4.0],
            [575.3, 452.0, 14.2, 11.2, 30.0],
            [608.1, 419.9, 14.2, 11.2, 60.0],
            [645.7, 426.7, 13.6, 9.6, 86.0],
        ];
    }

    /**
     * Erzeugt das exakt zum 2D-Overlay (800x800) deckungsgleiche 3D-Mesh für Die Seelen-Pfote (inkl. 3D-Pfoten-Durchbruch).
     */
    private function buildPfote3DMesh(): array
    {
        $positions = [];
        $normals = [];
        $uvs = [];
        $indices = [];

        $bonePts = $this->getPfoteOuterPoints(16);
        $this->extrudePixelContour($bonePts, null, [400.0, 400.0], 0.032, $positions, $normals, $uvs, $indices);

        // 3D-Innenwände + 3D-Fasenringe für das obere Ösen-Loch und die 5 Pfoten-Durchbrüche unten rechts
        $addHoleWallAndBevel = static function (array $pts, float $cx, float $cy, float $zHalf = 0.032) use (&$positions, &$normals, &$uvs, &$indices): void {
            $toX = static fn(float $px): float => ($px - 400.0) / 360.0;
            $toY = static fn(float $py): float => (400.0 - $py) / 360.0;
            // Opaque gold UV coordinate on overlay.png (300, 400) so inner walls are always solid gold
            $uGold = 300.0 / 800.0;
            $vGold = 400.0 / 800.0;
            $m = count($pts);
            for ($i = 0; $i < $m; $i++) {
                $next = ($i + 1) % $m;
                [$x0, $y0] = $pts[$i];
                [$x1, $y1] = $pts[$next];
                $dx = $toX($x1) - $toX($x0);
                $dy = $toY($y1) - $toY($y0);
                $len = hypot($dx, $dy) ?: 1.0;
                // Inward-pointing normal towards hole center
                $nx = $dy / $len;
                $ny = -$dx / $len;
                $midX = ($x0 + $x1) * 0.5;
                $midY = ($y0 + $y1) * 0.5;
                if (($nx * (($cx - $midX) / 360.0) + $ny * (($midY - $cy) / 360.0)) < 0.0) {
                    $nx = -$nx;
                    $ny = -$ny;
                }

                $vBase = intdiv(count($positions), 3);
                array_push(
                    $positions,
                    $toX($x0), $toY($y0), $zHalf,
                    $toX($x1), $toY($y1), $zHalf,
                    $toX($x1), $toY($y1), -$zHalf,
                    $toX($x0), $toY($y0), -$zHalf
                );
                for ($k = 0; $k < 4; $k++) {
                    array_push($normals, $nx, $ny, 0.0);
                }
                array_push($uvs, $uGold, $vGold, $uGold, $vGold, $uGold, $vGold, $uGold, $vGold);
                array_push($indices, $vBase, $vBase + 1, $vBase + 2, $vBase, $vBase + 2, $vBase + 3);
            }
        };

        $makeOvalPts = static function (float $cx, float $cy, float $rx, float $ry, float $deg, int $steps = 24): array {
            $rad = deg2rad($deg);
            $cos = cos($rad);
            $sin = sin($rad);
            $arr = [];
            for ($i = 0; $i < $steps; $i++) {
                $a = ($i / $steps) * M_PI * 2.0;
                $lx = $rx * cos($a);
                $ly = $ry * sin($a);
                $arr[] = [$cx + $lx * $cos - $ly * $sin, $cy + $lx * $sin + $ly * $cos];
            }
            return $arr;
        };

        // 1. Top eyelet hole
        $addHoleWallAndBevel($makeOvalPts(400.0, 279.0, 23.5, 23.5, 0.0, 32), 400.0, 279.0);
        // 2. Kidney-shaped main paw pad (bottom-right)
        $addHoleWallAndBevel($this->getPfoteMainPadPoints(48), 634.8, 478.9);
        // 3. Four oval toe pads (bottom-right)
        foreach ($this->getPfoteToeDefinitions() as [$tcx, $tcy, $trx, $try, $tdeg]) {
            $addHoleWallAndBevel($makeOvalPts($tcx, $tcy, $trx, $try, $tdeg, 24), $tcx, $tcy);
        }

        return compact('positions', 'normals', 'uvs', 'indices');
    }

    /**
     * Baut ein binäres glTF 2.0 (.glb) mit eingebetteter PNG-Textur in reinem PHP.
     */
    private function buildSingleTexturedGlb(array $meshData, string $pngBytes, string $meshName = 'ProductMesh'): string
    {
        $positions = $meshData['positions'];
        $normals = $meshData['normals'];
        $uvs = $meshData['uvs'];
        $indices = $meshData['indices'];

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

        $pad4 = static function (string $buf, string $padChar = "\0"): string {
            $rem = strlen($buf) % 4;
            return $rem === 0 ? $buf : $buf . str_repeat($padChar, 4 - $rem);
        };

        $rawPos = pack('g*', ...$positions);
        $rawNorm = pack('g*', ...$normals);
        $rawUv = pack('g*', ...$uvs);
        $rawIdx = pack('V*', ...$indices);

        $bPos = $pad4($rawPos);
        $bNorm = $pad4($rawNorm);
        $bUv = $pad4($rawUv);
        $bIdx = $pad4($rawIdx);
        $bImg = $pad4($pngBytes);

        $offset = 0;
        $bvPos = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($rawPos), 'target' => 34962];
        $offset += strlen($bPos);

        $bvNorm = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($rawNorm), 'target' => 34962];
        $offset += strlen($bNorm);

        $bvUv = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($rawUv), 'target' => 34962];
        $offset += strlen($bUv);

        $bvIdx = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($rawIdx), 'target' => 34963];
        $offset += strlen($bIdx);

        $bvImg = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($pngBytes)];
        $offset += strlen($bImg);

        $binBuffer = $bPos . $bNorm . $bUv . $bIdx . $bImg;

        $gltf = [
            'asset' => [
                'version' => '2.0',
                'generator' => 'Seelenfunke-PHP-Seeder-3D-v2',
                'copyright' => self::GLB_COPYRIGHT_NOTICE,
                'extras' => [
                    'seelenfunke_optimized' => true,
                    'rights_holder' => 'Mein-Seelenfunke (Alina Steinhauer)',
                    'protected_under' => 'UrhG §§ 2, 10, 69a & UWG § 4 Nr. 3',
                ],
            ],
            'scene' => 0,
            'scenes' => [['nodes' => [0]]],
            'nodes' => [['name' => $meshName, 'mesh' => 0]],
            'meshes' => [[
                'name' => $meshName,
                'primitives' => [[
                    'attributes' => ['POSITION' => 0, 'NORMAL' => 1, 'TEXCOORD_0' => 2],
                    'indices' => 3,
                    'material' => 0,
                ]],
            ]],
            'materials' => [[
                'name' => 'PolishedGoldMetal',
                'doubleSided' => true,
                'alphaMode' => 'MASK',
                'alphaCutoff' => 0.35,
                'pbrMetallicRoughness' => [
                    'baseColorFactor' => [1.0, 1.0, 1.0, 1.0],
                    'baseColorTexture' => ['index' => 0],
                    'metallicFactor' => 0.85,
                    'roughnessFactor' => 0.2,
                ],
            ]],
            'textures' => [['source' => 0, 'sampler' => 0]],
            'samplers' => [['magFilter' => 9729, 'minFilter' => 9729, 'wrapS' => 33071, 'wrapT' => 33071]],
            'images' => [['bufferView' => 4, 'mimeType' => 'image/png']],
            'accessors' => [
                [
                    'bufferView' => 0,
                    'componentType' => 5126,
                    'count' => intdiv($posCount, 3),
                    'type' => 'VEC3',
                    'min' => [$minX, $minY, $minZ],
                    'max' => [$maxX, $maxY, $maxZ],
                ],
                [
                    'bufferView' => 1,
                    'componentType' => 5126,
                    'count' => intdiv(count($normals), 3),
                    'type' => 'VEC3',
                ],
                [
                    'bufferView' => 2,
                    'componentType' => 5126,
                    'count' => intdiv(count($uvs), 2),
                    'type' => 'VEC2',
                ],
                [
                    'bufferView' => 3,
                    'componentType' => 5125,
                    'count' => count($indices),
                    'type' => 'SCALAR',
                ],
            ],
            'bufferViews' => [$bvPos, $bvNorm, $bvUv, $bvIdx, $bvImg],
            'buffers' => [['byteLength' => strlen($binBuffer)]],
        ];

        $jsonBuf = $pad4(json_encode($gltf, JSON_UNESCAPED_SLASHES), ' ');
        $totalLen = 12 + 8 + strlen($jsonBuf) + 8 + strlen($binBuffer);

        $header = 'glTF' . pack('VV', 2, $totalLen);
        $jsonHeader = pack('V', strlen($jsonBuf)) . 'JSON';
        $binHeader = pack('V', strlen($binBuffer)) . "BIN\0";

        return $header . $jsonHeader . $jsonBuf . $binHeader . $binBuffer;
    }

    /**
     * Erzeugt das maßgetreue, hochrealistische 3D-CAD-Mesh für "Der Seelen Kristall"
     * exakt nach den realen Trophäen-Abmessungen 180 x 200 x 40 mm (1.000 Einheit = 200 mm -> 0.900 x 1.000 x 0.200):
     * - Oberteil: Runde K9-Kristallscheibe (Ø 180 mm => R_outer = 0.450, Zentrum Y = +0.050, Stärke 19 mm => Z = ±0.0475)
     *   mit 28-segmentigem, scharfkantig flach-schattiertem Diamant-/Sonnenschliff-Facettenrand (Vorder- & Rückseite)
     *   und abgeflachter Unterkante bei Y = -0.380.
     * - Unterteil: Trapezförmiger, facettierter K9-Kristallsockel (126 x 24 x 40 mm => X = ±0.315, Y = -0.500..-0.380, Z = ±0.100)
     *   mit 45°-Oberkantenfase, Bodenfase, optischer UV-Klebeflächen-Brechung und internen Totalreflexions-Prismen.
     */
    private function buildSeelenKristall3DMesh(): array
    {
        $positions = [];
        $normals = [];
        $uvs = [];
        $indices = [];

        $addFlatTri = static function (
            array $p0,
            array $p1,
            array $p2,
            array $uv0,
            array $uv1,
            array $uv2,
            array $outDir
        ) use (&$positions, &$normals, &$uvs, &$indices): void {
            $ux = $p1[0] - $p0[0];
            $uy = $p1[1] - $p0[1];
            $uz = $p1[2] - $p0[2];
            $vx = $p2[0] - $p0[0];
            $vy = $p2[1] - $p0[1];
            $vz = $p2[2] - $p0[2];

            $nx = $uy * $vz - $uz * $vy;
            $ny = $uz * $vx - $ux * $vz;
            $nz = $ux * $vy - $uy * $vx;
            $len = sqrt($nx * $nx + $ny * $ny + $nz * $nz);
            if ($len < 1e-8) {
                return;
            }
            $nx /= $len;
            $ny /= $len;
            $nz /= $len;

            $dot = $nx * $outDir[0] + $ny * $outDir[1] + $nz * $outDir[2];
            if ($dot < 0.0) {
                $nx = -$nx;
                $ny = -$ny;
                $nz = -$nz;
                [$p1, $p2] = [$p2, $p1];
                [$uv1, $uv2] = [$uv2, $uv1];
            }

            $base = intdiv(count($positions), 3);
            array_push(
                $positions,
                $p0[0], $p0[1], $p0[2],
                $p1[0], $p1[1], $p1[2],
                $p2[0], $p2[1], $p2[2]
            );
            array_push(
                $normals,
                $nx, $ny, $nz,
                $nx, $ny, $nz,
                $nx, $ny, $nz
            );
            array_push(
                $uvs,
                $uv0[0], $uv0[1],
                $uv1[0], $uv1[1],
                $uv2[0], $uv2[1]
            );
            array_push($indices, $base, $base + 1, $base + 2);
        };

        $addFlatQuad = static function (
            array $p0,
            array $p1,
            array $p2,
            array $p3,
            array $uv0,
            array $uv1,
            array $uv2,
            array $uv3,
            array $outDir
        ) use ($addFlatTri): void {
            $addFlatTri($p0, $p1, $p2, $uv0, $uv1, $uv2, $outDir);
            $addFlatTri($p0, $p2, $p3, $uv0, $uv2, $uv3, $outDir);
        };

        // --- 1. RUNDE K9-KRISTALLSCHEIBE MIT 30-SEGMENT SONNENSCHLIFF (Ø 180 mm, Z = ±0.0475 = 19 mm) ---
        $cy = 0.050;
        $rOuter = 0.450; // 90 mm Radius = 180 mm Durchmesser
        $rInner = 0.418; // 83,6 mm Innenradius der planen Gravurfläche (6,4 mm schlanker Sonnenschliff-Rand wie im Echtfoto)
        $ySeat = -0.376; // Abgeflachte Standfläche auf dem Kristallsockel (bündig eingelassen in Plateau Y = -0.372)
        $zFace = 0.0475; // Halbe Scheibenstärke (9,5 mm -> 19,0 mm gesamt)
        $zRidge = 0.0260; // Äußerer Facettengrat bei Hauptwinkel theta_i
        $zApex = 0.0075;  // Äußere Rautenspitze bei Halbwinkel theta_{i+0.5}
        $nSectors = 30;

        // UV-Mapping auf Region A der 1024x1024 K9-Kristall-Textur (Kreis um px=512, py=416, R_px=392)
        $discUv = static function (float $x, float $y) use ($cy, $rOuter): array {
            $u = (512.0 + ($x / $rOuter) * 392.0) / 1024.0;
            $v = (416.0 - (($y - $cy) / $rOuter) * 392.0) / 1024.0;
            return [max(0.002, min(0.998, $u)), max(0.002, min(0.795, $v))];
        };

        $outerXY = static function (float $theta) use ($cy, $rOuter, $ySeat): array {
            $x = $rOuter * cos($theta);
            $y = $cy + $rOuter * sin($theta);
            if ($y < $ySeat) {
                $y = $ySeat;
            }
            return [$x, $y];
        };

        $innerXY = static function (float $theta) use ($cy, $rInner, $ySeat): array {
            $x = $rInner * cos($theta);
            $y = $cy + $rInner * sin($theta);
            $minInnerY = $ySeat + 0.010;
            if ($y < $minInnerY) {
                $y = $minInnerY;
            }
            return [$x, $y];
        };

        $centerUv = $discUv(0.0, $cy);

        for ($i = 0; $i < $nSectors; $i++) {
            $t0 = (M_PI * 0.5) + ($i / $nSectors) * M_PI * 2.0;
            $tMid = (M_PI * 0.5) + (($i + 0.5) / $nSectors) * M_PI * 2.0;
            $t1 = (M_PI * 0.5) + (($i + 1.0) / $nSectors) * M_PI * 2.0;

            [$ix0, $iy0] = $innerXY($t0);
            [$ix1, $iy1] = $innerXY($t1);
            [$ox0, $oy0] = $outerXY($t0);
            [$oxm, $oym] = $outerXY($tMid);
            [$ox1, $oy1] = $outerXY($t1);

            $uvI0 = $discUv($ix0, $iy0);
            $uvI1 = $discUv($ix1, $iy1);
            $uvO0 = $discUv($ox0, $oy0);
            $uvOm = $discUv($oxm, $oym);
            $uvO1 = $discUv($ox1, $oy1);

            // 1a. Plane optische Vorderseite (+zFace) & Rückseite (-zFace)
            $addFlatTri(
                [0.0, $cy, $zFace],
                [$ix0, $iy0, $zFace],
                [$ix1, $iy1, $zFace],
                $centerUv,
                $uvI0,
                $uvI1,
                [0.0, 0.0, 1.0]
            );
            $addFlatTri(
                [0.0, $cy, -$zFace],
                [$ix1, $iy1, -$zFace],
                [$ix0, $iy0, -$zFace],
                $centerUv,
                $uvI1,
                $uvI0,
                [0.0, 0.0, -1.0]
            );

            $radDirMid = [cos($tMid), sin($tMid), 0.65];
            $radDirL = [cos($t0 + 0.06), sin($t0 + 0.06), 0.55];
            $radDirR = [cos($t1 - 0.06), sin($t1 - 0.06), 0.55];

            // 1b. Vorderer Diamant-/Sonnenschliff-Facettenkranz (3 scharfe Dreiecksfacetten pro Segment)
            $addFlatTri(
                [$ix0, $iy0, $zFace],
                [$oxm, $oym, $zApex],
                [$ix1, $iy1, $zFace],
                $uvI0,
                $uvOm,
                $uvI1,
                $radDirMid
            );
            $addFlatTri(
                [$ix0, $iy0, $zFace],
                [$ox0, $oy0, $zRidge],
                [$oxm, $oym, $zApex],
                $uvI0,
                $uvO0,
                $uvOm,
                $radDirL
            );
            $addFlatTri(
                [$ix1, $iy1, $zFace],
                [$oxm, $oym, $zApex],
                [$ox1, $oy1, $zRidge],
                $uvI1,
                $uvOm,
                $uvO1,
                $radDirR
            );

            // 1c. Hinterer Diamant-/Sonnenschliff-Facettenkranz (-Z)
            $radBackMid = [cos($tMid), sin($tMid), -0.65];
            $radBackL = [cos($t0 + 0.06), sin($t0 + 0.06), -0.55];
            $radBackR = [cos($t1 - 0.06), sin($t1 - 0.06), -0.55];

            $addFlatTri(
                [$ix0, $iy0, -$zFace],
                [$ix1, $iy1, -$zFace],
                [$oxm, $oym, -$zApex],
                $uvI0,
                $uvI1,
                $uvOm,
                $radBackMid
            );
            $addFlatTri(
                [$ix0, $iy0, -$zFace],
                [$oxm, $oym, -$zApex],
                [$ox0, $oy0, -$zRidge],
                $uvI0,
                $uvOm,
                $uvO0,
                $radBackL
            );
            $addFlatTri(
                [$ix1, $iy1, -$zFace],
                [$ox1, $oy1, -$zRidge],
                [$oxm, $oym, -$zApex],
                $uvI1,
                $uvO1,
                $uvOm,
                $radBackR
            );

            // 1d. Äußere 19 mm Umfangswand (Rautenschliff-Seitenkante auf Region B: v = 0.81..0.87)
            $uRim0 = 0.06 + ($i / $nSectors) * 0.88;
            $uRimM = 0.06 + (($i + 0.5) / $nSectors) * 0.88;
            $uRim1 = 0.06 + (($i + 1.0) / $nSectors) * 0.88;
            $vRimF = 0.815;
            $vRimB = 0.865;

            $addFlatQuad(
                [$ox0, $oy0, $zRidge],
                [$ox0, $oy0, -$zRidge],
                [$oxm, $oym, -$zApex],
                [$oxm, $oym, $zApex],
                [$uRim0, $vRimF],
                [$uRim0, $vRimB],
                [$uRimM, $vRimB],
                [$uRimM, $vRimF],
                [cos($t0 + ($tMid - $t0) * 0.5), sin($t0 + ($tMid - $t0) * 0.5), 0.0]
            );
            $addFlatQuad(
                [$oxm, $oym, $zApex],
                [$oxm, $oym, -$zApex],
                [$ox1, $oy1, -$zRidge],
                [$ox1, $oy1, $zRidge],
                [$uRimM, $vRimF],
                [$uRimM, $vRimB],
                [$uRim1, $vRimB],
                [$uRim1, $vRimF],
                [cos($tMid + ($t1 - $tMid) * 0.5), sin($tMid + ($t1 - $tMid) * 0.5), 0.0]
            );
        }

        // --- 2. TRAPEZFÖRMIGER FACETTIERTER K9-KRISTALLSOCKEL (134 x 25.6 x 40 mm, exakt nach Echtfotos 1-3) ---
        $y0 = -0.500; $x0 = 0.331; $z0 = 0.097;
        $y1 = -0.494; $x1 = 0.334; $z1 = 0.100; // Unten 133,6 mm breit, 40,0 mm tief
        $y2 = -0.381; $x2 = 0.300; $z2 = 0.084; // Trapezverjüngung nach oben
        $y3 = -0.372; $x3 = 0.291; $z3 = 0.075; // Oberes Sockel-Plateau (30 mm tief -> 19 mm Scheibe steht mittig darauf)

        $vTopPlat = 0.884;
        $vBevelTop = 0.892;
        $vBevelBot = 0.910;
        $vSlopeBot = 0.980;
        $vFootBot = 0.992;

        // 8 horizontale Unterteilungen pro Sockelflanke, damit der horizontale K9-Glasverlauf & die 45°-Eckprismen
        // auch ohne Fragment-UV-Interpolation absolut fein aufgelöst dargestellt werden
        $nSubX = 8;
        for ($s = 0; $s < $nSubX; $s++) {
            $f0 = $s / $nSubX;
            $f1 = ($s + 1) / $nSubX;

            $sx0_a = -$x0 + ($x0 * 2.0) * $f0; $sx0_b = -$x0 + ($x0 * 2.0) * $f1;
            $sx1_a = -$x1 + ($x1 * 2.0) * $f0; $sx1_b = -$x1 + ($x1 * 2.0) * $f1;
            $sx2_a = -$x2 + ($x2 * 2.0) * $f0; $sx2_b = -$x2 + ($x2 * 2.0) * $f1;
            $sx3_a = -$x3 + ($x3 * 2.0) * $f0; $sx3_b = -$x3 + ($x3 * 2.0) * $f1;

            $u0_a = 0.080 + 0.840 * $f0; $u0_b = 0.080 + 0.840 * $f1;
            $u2_a = 0.120 + 0.760 * $f0; $u2_b = 0.120 + 0.760 * $f1;
            $u3_a = 0.136 + 0.728 * $f0; $u3_b = 0.136 + 0.728 * $f1;

            // Vorderseite (+Z): Oberfase, Trapez-Hauptflanke (oben & unten unterteilt), Bodenfase
            $ym = ($y1 + $y2) * 0.5;
            $zm = ($z1 + $z2) * 0.5;
            $sxm_a = ($sx1_a + $sx2_a) * 0.5;
            $sxm_b = ($sx1_b + $sx2_b) * 0.5;
            $um_a = ($u0_a + $u2_a) * 0.5;
            $um_b = ($u0_b + $u2_b) * 0.5;
            $vMid = ($vBevelBot + $vSlopeBot) * 0.5;

            $addFlatQuad(
                [$sx3_a, $y3, $z3], [$sx2_a, $y2, $z2], [$sx2_b, $y2, $z2], [$sx3_b, $y3, $z3],
                [$u3_a, $vBevelTop], [$u2_a, $vBevelBot], [$u2_b, $vBevelBot], [$u3_b, $vBevelTop],
                [0.0, 0.70, 0.71]
            );
            $addFlatQuad(
                [$sx2_a, $y2, $z2], [$sxm_a, $ym, $zm], [$sxm_b, $ym, $zm], [$sx2_b, $y2, $z2],
                [$u2_a, $vBevelBot], [$um_a, $vMid], [$um_b, $vMid], [$u2_b, $vBevelBot],
                [0.0, 0.14, 0.99]
            );
            $addFlatQuad(
                [$sxm_a, $ym, $zm], [$sx1_a, $y1, $z1], [$sx1_b, $y1, $z1], [$sxm_b, $ym, $zm],
                [$um_a, $vMid], [$u0_a, $vSlopeBot], [$u0_b, $vSlopeBot], [$um_b, $vMid],
                [0.0, 0.14, 0.99]
            );
            $addFlatQuad(
                [$sx1_a, $y1, $z1], [$sx0_a, $y0, $z0], [$sx0_b, $y0, $z0], [$sx1_b, $y1, $z1],
                [$u0_a, $vSlopeBot], [$u0_a, $vFootBot], [$u0_b, $vFootBot], [$u0_b, $vSlopeBot],
                [0.0, -0.45, 0.89]
            );

            // Rückseite (-Z)
            $addFlatQuad(
                [$sx3_b, $y3, -$z3], [$sx2_b, $y2, -$z2], [$sx2_a, $y2, -$z2], [$sx3_a, $y3, -$z3],
                [$u3_b, $vBevelTop], [$u2_b, $vBevelBot], [$u2_a, $vBevelBot], [$u3_a, $vBevelTop],
                [0.0, 0.70, -0.71]
            );
            $addFlatQuad(
                [$sx2_b, $y2, -$z2], [$sxm_b, $ym, -$zm], [$sxm_a, $ym, -$zm], [$sx2_a, $y2, -$z2],
                [$u2_b, $vBevelBot], [$um_b, $vMid], [$um_a, $vMid], [$u2_a, $vBevelBot],
                [0.0, 0.14, -0.99]
            );
            $addFlatQuad(
                [$sxm_b, $ym, -$zm], [$sx1_b, $y1, -$z1], [$sx1_a, $y1, -$z1], [$sxm_a, $ym, -$zm],
                [$um_b, $vMid], [$u0_b, $vSlopeBot], [$u0_a, $vSlopeBot], [$um_a, $vMid],
                [0.0, 0.14, -0.99]
            );
            $addFlatQuad(
                [$sx1_b, $y1, -$z1], [$sx0_b, $y0, -$z0], [$sx0_a, $y0, -$z0], [$sx1_a, $y1, -$z1],
                [$u0_b, $vSlopeBot], [$u0_b, $vFootBot], [$u0_a, $vFootBot], [$u0_a, $vSlopeBot],
                [0.0, -0.45, -0.89]
            );

            // Oberes Sockel-Plateau (Y = -0.372) & Unterseite (Y = -0.500)
            $addFlatQuad(
                [$sx3_a, $y3, -$z3], [$sx3_a, $y3, $z3], [$sx3_b, $y3, $z3], [$sx3_b, $y3, -$z3],
                [$u3_a, $vTopPlat], [$u3_a, $vBevelTop], [$u3_b, $vBevelTop], [$u3_b, $vTopPlat],
                [0.0, 1.0, 0.0]
            );
            $addFlatQuad(
                [$sx0_a, $y0, $z0], [$sx0_a, $y0, -$z0], [$sx0_b, $y0, -$z0], [$sx0_b, $y0, $z0],
                [$u0_a, $vSlopeBot], [$u0_a, $vFootBot], [$u0_b, $vFootBot], [$u0_b, $vSlopeBot],
                [0.0, -1.0, 0.0]
            );
        }

        // Linke Trapez-Seitenwand (-X) & Rechte Trapez-Seitenwand (+X)
        $uSideL = 0.085; $uSideR = 0.200;
        $addFlatQuad(
            [-$x3, $y3, -$z3], [-$x2, $y2, -$z2], [-$x2, $y2, $z2], [-$x3, $y3, $z3],
            [$uSideL, $vBevelTop], [$uSideL, $vBevelBot], [$uSideR, $vBevelBot], [$uSideR, $vBevelTop],
            [-0.73, 0.68, 0.0]
        );
        $addFlatQuad(
            [-$x2, $y2, -$z2], [-$x1, $y1, -$z1], [-$x1, $y1, $z1], [-$x2, $y2, $z2],
            [$uSideL, $vBevelBot], [$uSideL, $vSlopeBot], [$uSideR, $vSlopeBot], [$uSideR, $vBevelBot],
            [-0.95, 0.30, 0.0]
        );
        $addFlatQuad(
            [-$x1, $y1, -$z1], [-$x0, $y0, -$z0], [-$x0, $y0, $z0], [-$x1, $y1, $z1],
            [$uSideL, $vSlopeBot], [$uSideL, $vFootBot], [$uSideR, $vFootBot], [$uSideR, $vSlopeBot],
            [-0.89, -0.45, 0.0]
        );

        $uSideR1 = 0.800; $uSideR2 = 0.915;
        $addFlatQuad(
            [$x3, $y3, $z3], [$x2, $y2, $z2], [$x2, $y2, -$z2], [$x3, $y3, -$z3],
            [$uSideR1, $vBevelTop], [$uSideR1, $vBevelBot], [$uSideR2, $vBevelBot], [$uSideR2, $vBevelTop],
            [0.73, 0.68, 0.0]
        );
        $addFlatQuad(
            [$x2, $y2, $z2], [$x1, $y1, $z1], [$x1, $y1, -$z1], [$x2, $y2, -$z2],
            [$uSideR1, $vBevelBot], [$uSideR1, $vSlopeBot], [$uSideR2, $vSlopeBot], [$uSideR2, $vBevelBot],
            [0.95, 0.30, 0.0]
        );
        $addFlatQuad(
            [$x1, $y1, $z1], [$x0, $y0, $z0], [$x0, $y0, -$z0], [$x1, $y1, -$z1],
            [$uSideR1, $vSlopeBot], [$uSideR1, $vFootBot], [$uSideR2, $vFootBot], [$uSideR2, $vSlopeBot],
            [0.89, -0.45, 0.0]
        );

        // Optischer UV-Klebeflächen-Meniskus an der Kontaktstelle (Y = -0.3715, X = -0.148..+0.148, Z = ±0.052)
        $addFlatQuad(
            [-0.148, $y3 + 0.0005, -$zFace - 0.004],
            [-0.148, $y3 + 0.0005, $zFace + 0.004],
            [0.148, $y3 + 0.0005, $zFace + 0.004],
            [0.148, $y3 + 0.0005, -$zFace - 0.004],
            [0.35, $vBevelTop], [0.35, $vBevelBot], [0.65, $vBevelBot], [0.65, $vBevelTop],
            [0.0, 1.0, 0.0]
        );

        return compact('positions', 'normals', 'uvs', 'indices');
    }

    /**
     * Rendert den hochauflösenden 1024x1024 RGBA Optischen K9-Kristallglas-Texturatlas in reinem PHP-GD,
     * farb- und kontrastkalibriert anhand der 3 realen Studiofotos der 180 x 200 x 40 mm Trophäe.
     */
    private function renderSeelenKristallAtlasPng(): string
    {
        if (!extension_loaded('gd')) {
            return '';
        }

        $w = 1024;
        $h = 1024;
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        $trans = imagecolorallocatealpha($im, 236, 246, 249, 127);
        imagefill($im, 0, 0, $trans);

        $cx = 512.0;
        $cy = 416.0;
        $rOuter = 392.0;
        $rInner = 364.1; // 0.418 / 0.450 * 392.0 (exakt die schlanke 6,3%-Facettenfase des Originals)
        $nSectors = 30;
        $sectorStep = (M_PI * 2.0) / $nSectors;

        // 1. Region A: Runde K9-Kristallscheibe + 30-Segment Sonnenschliff-Facettenkranz (y = 18 .. 812)
        for ($y = 18; $y <= 812; $y++) {
            $dy = $cy - $y;
            for ($x = 114; $x <= 910; $x++) {
                $dx = $x - $cx;
                $r = hypot($dx, $dy);
                if ($r > $rOuter + 4.0) {
                    continue;
                }

                if ($r < $rInner) {
                    // Plane optische K9-Kristallglas-Vorder-/Rückseite (glasklar & hochtransparent, ohne Speichen-Artefakte)
                    $rn = $r / $rInner;
                    $ux = $dx / $rInner;
                    $uy = $dy / $rInner;

                    $sheen = exp(-pow(($ux * 0.60 - $uy * 0.60 + 0.15) / 0.60, 2)) * 0.14;
                    $fresnel = ($rn > 0.82) ? pow(($rn - 0.82) / 0.18, 2.3) : 0.0;

                    $cr = 236.0 + 12.0 * $sheen + 14.0 * $fresnel;
                    $cg = 246.0 + 7.0 * $sheen + 8.0 * $fresnel;
                    $cb = 250.0 + 5.0 * $sheen + 5.0 * $fresnel;

                    $alpha = 0.12 + 0.05 * $sheen + 0.22 * $fresnel;
                    if ($rn > 0.982) {
                        // Feine geschliffene Innenkante zwischen Planfläche und Facettenkranz
                        $edgeRim = ($rn - 0.982) / 0.018;
                        $cr = 248.0;
                        $cg = 253.0;
                        $cb = 255.0;
                        $alpha = 0.34 + 0.38 * $edgeRim;
                    }

                    $gdA = (int) max(0, min(127, round(127.0 * (1.0 - $alpha))));
                    imagesetpixel(
                        $im,
                        $x,
                        $y,
                        ($gdA << 24) | ((int) min(255, round($cr)) << 16) | ((int) min(255, round($cg)) << 8) | (int) min(255, round($cb))
                    );
                } else {
                    // 30-Segment Diamant-/Sonnenschliff-Facettenkranz (natürliche K9-Glas-Lichtbrechung wie in Foto 1-3)
                    $tr = max(0.0, min(1.0, ($r - $rInner) / ($rOuter - $rInner)));
                    $theta = atan2($dy, $dx);
                    $relAngle = fmod($theta - (M_PI * 0.5) + M_PI * 4.0, M_PI * 2.0);
                    $secIdx = (int) floor($relAngle / $sectorStep);
                    $uSec = ($relAngle - ($secIdx * $sectorStep)) / $sectorStep;
                    $dCenter = abs($uSec - 0.5) * 2.0;
                    $diagDist = $dCenter - (1.0 - $tr);

                    // Sanfte winkelabhängige K9-Eisblau/Silber-Modulation (oben/unten zartes K9-Aqua, seitlich kristallklares Silber-Grau)
                    $vertFactor = pow(sin($theta), 2); // 1.0 oben & unten, 0.0 links & rechts
                    $horizFactor = 1.0 - $vertFactor;
                    $secShimmer = sin($secIdx * 1.7 + 0.4) * 0.5 + 0.5;

                    if ($diagDist <= 0.0) {
                        // Haupt-Rautenfacette: helles, edles Kristallglas mit sanftem K9-Aquaschimmer
                        $slopeLight = 0.65 + 0.35 * (1.0 - $dCenter) - 0.15 * $tr;
                        $cr = 212.0 * $horizFactor + 184.0 * $vertFactor + 28.0 * $slopeLight + 8.0 * $secShimmer;
                        $cg = 224.0 * $horizFactor + 226.0 * $vertFactor + 20.0 * $slopeLight + 6.0 * $secShimmer;
                        $cb = 228.0 * $horizFactor + 236.0 * $vertFactor + 16.0 * $slopeLight + 5.0 * $secShimmer;
                        $alpha = 0.52 + 0.16 * $tr + 0.08 * $vertFactor;
                    } else {
                        // Sternfacette: dezenter Totalreflexions-Kontrast (kein harter Blau-Weiß-Pinwheel-Effekt)
                        $isLeftStar = ($uSec < 0.5);
                        $facetAngle = $theta + ($isLeftStar ? -0.35 : 0.35);
                        $refl = sin($facetAngle * 2.0 + ($secIdx % 2) * 0.8);

                        if ($refl > 0.15) {
                            // Helle Spiegel-Facette (#e8f4f7 .. #ffffff)
                            $b = ($refl - 0.15) / 0.85;
                            $cr = 218.0 + 36.0 * $b;
                            $cg = 234.0 + 20.0 * $b;
                            $cb = 239.0 + 16.0 * $b;
                            $alpha = 0.62 + 0.20 * $b;
                        } else {
                            // Edle K9-Tiefenbrechung (oben/unten sanftes Eis-Aqua #94c6d2, seitlich kühles Rauch-Quarz #8ea6ae)
                            $d = min(1.0, (0.15 - $refl) / 1.15);
                            $cr = (168.0 - 28.0 * $d) * $horizFactor + (142.0 - 18.0 * $d) * $vertFactor;
                            $cg = (186.0 - 22.0 * $d) * $horizFactor + (196.0 - 14.0 * $d) * $vertFactor;
                            $cb = (192.0 - 20.0 * $d) * $horizFactor + (208.0 - 12.0 * $d) * $vertFactor;
                            $alpha = 0.58 + 0.18 * $d;
                        }
                    }

                    // Feine, helle Facetten-Schliffkanten entlang der Rautengrate
                    $ridgeLine = min(abs($diagDist), min($uSec, 1.0 - $uSec) * 2.0);
                    if ($ridgeLine < 0.08) {
                        $rMix = 1.0 - ($ridgeLine / 0.08);
                        $cr = $cr * (1.0 - 0.55 * $rMix) + 250.0 * (0.55 * $rMix);
                        $cg = $cg * (1.0 - 0.55 * $rMix) + 254.0 * (0.55 * $rMix);
                        $cb = $cb * (1.0 - 0.55 * $rMix) + 255.0 * (0.55 * $rMix);
                        $alpha = max($alpha, 0.74);
                    }

                    $gdA = (int) max(0, min(127, round(127.0 * (1.0 - min(0.88, $alpha)))));
                    imagesetpixel(
                        $im,
                        $x,
                        $y,
                        ($gdA << 24) | ((int) max(0, min(255, round($cr))) << 16) | ((int) max(0, min(255, round($cg))) << 8) | (int) max(0, min(255, round($cb)))
                    );
                }
            }
        }

        // 2. Region B: 19 mm Umfangswand (v = 0.805 .. 0.875 => y = 824 .. 896)
        for ($y = 824; $y <= 896; $y++) {
            $vt = ($y - 824.0) / (896.0 - 824.0);
            $centerRidge = 1.0 - abs($vt - 0.5) * 2.0;
            for ($x = 40; $x <= 984; $x++) {
                $ut = ($x - 40.0) / (984.0 - 40.0);
                $secWave = abs(sin($ut * $nSectors * M_PI));
                $cr = 172.0 + 36.0 * $centerRidge + 18.0 * $secWave;
                $cg = 198.0 + 26.0 * $centerRidge + 12.0 * $secWave;
                $cb = 205.0 + 22.0 * $centerRidge + 10.0 * $secWave;
                $alpha = 0.68 + 0.12 * $centerRidge;
                $gdA = (int) max(0, min(127, round(127.0 * (1.0 - $alpha))));
                imagesetpixel(
                    $im,
                    $x,
                    $y,
                    ($gdA << 24) | ((int) min(255, round($cr)) << 16) | ((int) min(255, round($cg)) << 8) | (int) min(255, round($cb))
                );
            }
        }

        // 3. Region C: Trapezförmiger 40 mm K9-Kristallsockel (v = 0.880 .. 0.994 => y = 900 .. 1018)
        for ($y = 900; $y <= 1018; $y++) {
            $vy = ($y - 900.0) / (1018.0 - 900.0);
            for ($x = 64; $x <= 960; $x++) {
                $ux = ($x - 64.0) / (960.0 - 64.0);
                $edgeDist = min($ux, 1.0 - $ux);

                if ($vy < 0.25) {
                    // Oberes Plateau & 45°-Glanzfase (helles Eiskristall-Blauweiß wie in Foto 1 bei y=758..786)
                    $bevelT = $vy / 0.25;
                    $centerContact = exp(-pow(($ux - 0.5) / 0.24, 2));
                    $cr = 216.0 + 24.0 * (1.0 - $bevelT) + 12.0 * $centerContact;
                    $cg = 234.0 + 16.0 * (1.0 - $bevelT) + 8.0 * $centerContact;
                    $cb = 242.0 + 11.0 * (1.0 - $bevelT) + 5.0 * $centerContact;
                    $alpha = 0.72 + 0.12 * (1.0 - $bevelT);

                    if ($bevelT > 0.80) {
                        $lineMix = ($bevelT - 0.80) / 0.20;
                        $cr = $cr * (1.0 - 0.35 * $lineMix) + 148.0 * (0.35 * $lineMix);
                        $cg = $cg * (1.0 - 0.35 * $lineMix) + 174.0 * (0.35 * $lineMix);
                        $cb = $cb * (1.0 - 0.35 * $lineMix) + 182.0 * (0.35 * $lineMix);
                        $alpha = 0.80;
                    }
                } elseif ($vy < 0.86) {
                    // Trapez-Hauptflanke des massiven 40 mm Kristallsockels (Foto 1: R=164..198, G=169..219, B=172..224)
                    $bodyT = ($vy - 0.25) / 0.61;
                    $topReflectionBand = exp(-pow(($bodyT - 0.18) / 0.18, 2));
                    $cr = 174.0 + 44.0 * $topReflectionBand;
                    $cg = 190.0 + 42.0 * $topReflectionBand;
                    $cb = 196.0 + 42.0 * $topReflectionBand;
                    $alpha = 0.68 + 0.12 * $topReflectionBand;

                    // Horizontale interne Boden-Brechungslinie bei bodyT ≈ 0.72 (Foto 1 & 3)
                    $stepDist = abs($bodyT - 0.72);
                    if ($stepDist < 0.08) {
                        $sMix = 1.0 - ($stepDist / 0.08);
                        $cr = $cr * (1.0 - 0.45 * $sMix) + 228.0 * (0.45 * $sMix);
                        $cg = $cg * (1.0 - 0.45 * $sMix) + 240.0 * (0.45 * $sMix);
                        $cb = $cb * (1.0 - 0.45 * $sMix) + 244.0 * (0.45 * $sMix);
                        $alpha = max($alpha, 0.78);
                    }

                    // 45°-Eckprismen-Totalreflexion links & rechts (Foto 1-3)
                    $cornerWidth = 0.09 + 0.04 * $bodyT;
                    if ($edgeDist < $cornerWidth) {
                        $cNorm = $edgeDist / $cornerWidth;
                        if ($cNorm > 0.55 && $cNorm < 0.85) {
                            $cr = 132.0;
                            $cg = 154.0;
                            $cb = 162.0;
                            $alpha = 0.82;
                        } elseif ($cNorm >= 0.85) {
                            $cr = 236.0;
                            $cg = 246.0;
                            $cb = 250.0;
                            $alpha = 0.84;
                        } else {
                            $cr = 204.0;
                            $cg = 222.0;
                            $cb = 228.0;
                            $alpha = 0.76;
                        }
                    }
                } else {
                    // Untere geschliffene Standfußkante
                    $footT = ($vy - 0.86) / 0.14;
                    $cr = 156.0 - 14.0 * $footT;
                    $cg = 172.0 - 14.0 * $footT;
                    $cb = 178.0 - 14.0 * $footT;
                    $alpha = 0.84;
                }

                $gdA = (int) max(0, min(127, round(127.0 * (1.0 - min(0.96, $alpha)))));
                imagesetpixel(
                    $im,
                    $x,
                    $y,
                    ($gdA << 24) | ((int) max(0, min(255, round($cr))) << 16) | ((int) max(0, min(255, round($cg))) << 8) | (int) max(0, min(255, round($cb)))
                );
            }
        }

        ob_start();
        imagepng($im, null, 9);
        $pngBytes = (string) ob_get_clean();
        imagedestroy($im);

        return $pngBytes;
    }

    /**
     * Rendert das 918x918 2D-Overlay (overlay.png) für „Der Seelen Kristall“ direkt aus demselben
     * 180 x 200 x 40 mm 3D-Mesh, K9-Kristall-Texturatlas und derselben Three.js-Kameraprojektion
     * (FOV 45°, camZ = 1.81066, model_scale = 1.28, Dual-Pass BackSide + FrontSide),
     * sodass 2D-Editor und 3D-Ansicht pixel- und proportionsidentisch sind.
     */
    private function renderSeelenKristallOverlayPng(string $targetPath, array $meshData, string $atlasPngBytes): void
    {
        if (!extension_loaded('gd') || $atlasPngBytes === '') {
            return;
        }

        $texImg = @imagecreatefromstring($atlasPngBytes);
        if (!$texImg) {
            return;
        }
        $texW = imagesx($texImg);
        $texH = imagesy($texImg);

        $w = 918;
        $h = 918;
        $totalPx = $w * $h;

        // Float-Puffer für exaktes Premultiplied-Alpha-Compositing (wie WebGL SRC_ALPHA / ONE_MINUS_SRC_ALPHA)
        $bufR = array_fill(0, $totalPx, 0.0);
        $bufG = array_fill(0, $totalPx, 0.0);
        $bufB = array_fill(0, $totalPx, 0.0);
        $bufA = array_fill(0, $totalPx, 0.0);

        $positions = $meshData['positions'];
        $normals = $meshData['normals'];
        $uvs = $meshData['uvs'];
        $indices = $meshData['indices'];

        // Exakte Three.js Kamera-Parameter bei 0°-Frontalansicht (maxDim = 1.0, fov = 45°, model_scale = 1.28)
        $modelScale = 1.28;
        $tanHalfFov = tan(deg2rad(22.5));
        $cameraZ = (0.5 / $tanHalfFov) * 1.5; // 1.81066017

        $tris = [];
        $idxCount = count($indices);
        for ($i = 0; $i < $idxCount; $i += 3) {
            $i0 = $indices[$i];
            $i1 = $indices[$i + 1];
            $i2 = $indices[$i + 2];

            $vtx = [];
            $zSum = 0.0;
            $nxSum = 0.0;
            $nySum = 0.0;
            $nzSum = 0.0;

            foreach ([$i0, $i1, $i2] as $idx) {
                $wx = $positions[$idx * 3] * $modelScale;
                $wy = $positions[$idx * 3 + 1] * $modelScale;
                $wz = $positions[$idx * 3 + 2] * $modelScale;

                $nx = $normals[$idx * 3];
                $ny = $normals[$idx * 3 + 1];
                $nz = $normals[$idx * 3 + 2];

                $dz = max(0.1, $cameraZ - $wz);
                $halfFrustum = $dz * $tanHalfFov;
                $sx = (($wx / $halfFrustum) * 0.5 + 0.5) * $w;
                $sy = (0.5 - ($wy / $halfFrustum) * 0.5) * $h;

                $vtx[] = [$sx, $sy, $uvs[$idx * 2], $uvs[$idx * 2 + 1]];
                $zSum += $wz;
                $nxSum += $nx;
                $nySum += $ny;
                $nzSum += $nz;
            }

            $tris[] = [
                'v' => $vtx,
                'z' => $zSum / 3.0,
                'nx' => $nxSum / 3.0,
                'ny' => $nySum / 3.0,
                'nz' => $nzSum / 3.0,
            ];
        }

        // Zweistufige Sortierung analog Three.js Dual-Pass (BackSide zuerst, danach FrontSide von hinten nach vorne)
        usort($tris, static function (array $a, array $b): int {
            $aFront = ($a['nz'] >= 0.0) ? 1 : 0;
            $bFront = ($b['nz'] >= 0.0) ? 1 : 0;
            if ($aFront !== $bFront) {
                return $aFront <=> $bFront;
            }
            return $a['z'] <=> $b['z'];
        });

        foreach ($tris as $t) {
            [$p0, $p1, $p2] = $t['v'];
            $minX = max(0, (int) floor(min($p0[0], $p1[0], $p2[0])));
            $maxX = min($w - 1, (int) ceil(max($p0[0], $p1[0], $p2[0])));
            $minY = max(0, (int) floor(min($p0[1], $p1[1], $p2[1])));
            $maxY = min($h - 1, (int) ceil(max($p0[1], $p1[1], $p2[1])));

            $den = ($p1[1] - $p2[1]) * ($p0[0] - $p2[0]) + ($p2[0] - $p1[0]) * ($p0[1] - $p2[1]);
            if (abs($den) < 0.0005) {
                continue;
            }
            $invDen = 1.0 / $den;

            $nl = sqrt($t['nx'] * $t['nx'] + $t['ny'] * $t['ny'] + $t['nz'] * $t['nz']) ?: 1.0;
            $nx = $t['nx'] / $nl;
            $ny = $t['ny'] / $nl;
            $nz = $t['nz'] / $nl;

            $fresnel = pow(1.0 - min(1.0, abs($nz)), 1.8);
            $spec = pow(max(0.0, $nx * -0.30 + $ny * 0.58 + abs($nz) * 0.75), 22.0);
            $passOpacity = ($nz < 0.0) ? 0.44 : 0.96;

            for ($py = $minY; $py <= $maxY; $py++) {
                $fy = $py + 0.5;
                $rowOffset = $py * $w;
                for ($px = $minX; $px <= $maxX; $px++) {
                    $fx = $px + 0.5;
                    $w0 = (($p1[1] - $p2[1]) * ($fx - $p2[0]) + ($p2[0] - $p1[0]) * ($fy - $p2[1])) * $invDen;
                    if ($w0 < 0.0) {
                        continue;
                    }
                    $w1 = (($p2[1] - $p0[1]) * ($fx - $p2[0]) + ($p0[0] - $p2[0]) * ($fy - $p2[1])) * $invDen;
                    if ($w1 < 0.0) {
                        continue;
                    }
                    $w2 = 1.0 - $w0 - $w1;
                    if ($w2 < 0.0) {
                        continue;
                    }

                    $u = $w0 * $p0[2] + $w1 * $p1[2] + $w2 * $p2[2];
                    $v = $w0 * $p0[3] + $w1 * $p1[3] + $w2 * $p2[3];
                    $tx = max(0, min($texW - 1, (int) ($u * $texW)));
                    $ty = max(0, min($texH - 1, (int) ($v * $texH)));
                    $rgba = imagecolorat($texImg, $tx, $ty);
                    $a7 = ($rgba >> 24) & 0x7F;
                    if ($a7 >= 126) {
                        continue;
                    }

                    $sr = min(255.0, (($rgba >> 16) & 0xFF) + $spec * 85.0 + $fresnel * 14.0);
                    $sg = min(255.0, (($rgba >> 8) & 0xFF) + $spec * 90.0 + $fresnel * 20.0);
                    $sb = min(255.0, ($rgba & 0xFF) + $spec * 95.0 + $fresnel * 24.0);

                    $srcA = (1.0 - ($a7 / 127.0)) * $passOpacity;
                    $srcA = min(0.95, $srcA + $fresnel * 0.12 + $spec * 0.25);

                    $pIdx = $rowOffset + $px;
                    $dstA = $bufA[$pIdx];
                    $outA = $srcA + $dstA * (1.0 - $srcA);
                    if ($outA > 0.0001) {
                        $bufR[$pIdx] = ($sr * $srcA + $bufR[$pIdx] * $dstA * (1.0 - $srcA)) / $outA;
                        $bufG[$pIdx] = ($sg * $srcA + $bufG[$pIdx] * $dstA * (1.0 - $srcA)) / $outA;
                        $bufB[$pIdx] = ($sb * $srcA + $bufB[$pIdx] * $dstA * (1.0 - $srcA)) / $outA;
                        $bufA[$pIdx] = $outA;
                    }
                }
            }
        }

        imagedestroy($texImg);

        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $trans = imagecolorallocatealpha($out, 236, 246, 249, 127);
        imagefill($out, 0, 0, $trans);

        for ($py = 0; $py < $h; $py++) {
            $rowOffset = $py * $w;
            for ($px = 0; $px < $w; $px++) {
                $pIdx = $rowOffset + $px;
                $a = $bufA[$pIdx];
                if ($a <= 0.005) {
                    continue;
                }
                $gdAlpha = (int) max(0, min(126, round(127.0 * (1.0 - $a))));
                $r = (int) max(0, min(255, round($bufR[$pIdx])));
                $g = (int) max(0, min(255, round($bufG[$pIdx])));
                $b = (int) max(0, min(255, round($bufB[$pIdx])));
                imagesetpixel($out, $px, $py, ($gdAlpha << 24) | ($r << 16) | ($g << 8) | $b);
            }
        }

        imagepng($out, $targetPath, 9);
        imagedestroy($out);
    }

    /**
     * Baut ein binäres glTF 2.0 (.glb) für optisches K9-Kristallglas (alphaMode: BLEND, IOR 1.52, Transmission & Volume).
     */
    private function buildOpticalCrystalGlb(array $meshData, string $pngBytes, string $meshName = 'SeelenKristall'): string
    {
        $positions = $meshData['positions'];
        $normals = $meshData['normals'];
        $uvs = $meshData['uvs'];
        $indices = $meshData['indices'];

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

        $pad4 = static function (string $buf, string $padChar = "\0"): string {
            $rem = strlen($buf) % 4;
            return $rem === 0 ? $buf : $buf . str_repeat($padChar, 4 - $rem);
        };

        $rawPos = pack('g*', ...$positions);
        $rawNorm = pack('g*', ...$normals);
        $rawUv = pack('g*', ...$uvs);
        $rawIdx = pack('V*', ...$indices);

        $bPos = $pad4($rawPos);
        $bNorm = $pad4($rawNorm);
        $bUv = $pad4($rawUv);
        $bIdx = $pad4($rawIdx);
        $bImg = $pad4($pngBytes);

        $offset = 0;
        $bvPos = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($rawPos), 'target' => 34962];
        $offset += strlen($bPos);

        $bvNorm = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($rawNorm), 'target' => 34962];
        $offset += strlen($bNorm);

        $bvUv = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($rawUv), 'target' => 34962];
        $offset += strlen($bUv);

        $bvIdx = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($rawIdx), 'target' => 34963];
        $offset += strlen($bIdx);

        $bvImg = ['buffer' => 0, 'byteOffset' => $offset, 'byteLength' => strlen($pngBytes)];
        $offset += strlen($bImg);

        $binBuffer = $bPos . $bNorm . $bUv . $bIdx . $bImg;

        $gltf = [
            'asset' => [
                'version' => '2.0',
                'generator' => 'Seelenfunke-K9-Crystal-CAD-v3',
                'copyright' => self::GLB_COPYRIGHT_NOTICE,
                'extras' => [
                    'seelenfunke_optimized' => true,
                    'dimensions_mm' => '180x200x40',
                    'rights_holder' => 'Mein-Seelenfunke (Alina Steinhauer)',
                    'protected_under' => 'UrhG §§ 2, 10, 69a & UWG § 4 Nr. 3',
                ],
            ],
            'extensionsUsed' => [
                'KHR_materials_ior',
                'KHR_materials_specular',
                'KHR_materials_transmission',
                'KHR_materials_volume',
            ],
            'scene' => 0,
            'scenes' => [['name' => 'SeelenKristall_180x200x40', 'nodes' => [0]]],
            'nodes' => [['name' => $meshName, 'mesh' => 0]],
            'meshes' => [[
                'name' => $meshName,
                'primitives' => [[
                    'attributes' => ['POSITION' => 0, 'NORMAL' => 1, 'TEXCOORD_0' => 2],
                    'indices' => 3,
                    'material' => 0,
                ]],
            ]],
            'materials' => [[
                'name' => 'Optical_K9_Crystal_Glass',
                'doubleSided' => true,
                'alphaMode' => 'BLEND',
                'pbrMetallicRoughness' => [
                    'baseColorFactor' => [1.0, 1.0, 1.0, 1.0],
                    'baseColorTexture' => ['index' => 0],
                    'metallicFactor' => 0.04,
                    'roughnessFactor' => 0.03,
                ],
                'extensions' => [
                    'KHR_materials_ior' => ['ior' => 1.52],
                    'KHR_materials_specular' => ['specularFactor' => 1.0],
                    'KHR_materials_transmission' => ['transmissionFactor' => 0.92],
                    'KHR_materials_volume' => [
                        'thicknessFactor' => 0.19,
                        'attenuationDistance' => 0.85,
                        'attenuationColor' => [0.85, 0.96, 0.98],
                    ],
                ],
            ]],
            'textures' => [['source' => 0, 'sampler' => 0]],
            'samplers' => [['magFilter' => 9729, 'minFilter' => 9729, 'wrapS' => 33071, 'wrapT' => 33071]],
            'images' => [['name' => 'optical_k9_crystal_atlas', 'bufferView' => 4, 'mimeType' => 'image/png']],
            'accessors' => [
                [
                    'bufferView' => 0,
                    'componentType' => 5126,
                    'count' => intdiv($posCount, 3),
                    'type' => 'VEC3',
                    'min' => [$minX, $minY, $minZ],
                    'max' => [$maxX, $maxY, $maxZ],
                ],
                [
                    'bufferView' => 1,
                    'componentType' => 5126,
                    'count' => intdiv(count($normals), 3),
                    'type' => 'VEC3',
                ],
                [
                    'bufferView' => 2,
                    'componentType' => 5126,
                    'count' => intdiv(count($uvs), 2),
                    'type' => 'VEC2',
                ],
                [
                    'bufferView' => 3,
                    'componentType' => 5125,
                    'count' => count($indices),
                    'type' => 'SCALAR',
                ],
            ],
            'bufferViews' => [$bvPos, $bvNorm, $bvUv, $bvIdx, $bvImg],
            'buffers' => [['byteLength' => strlen($binBuffer)]],
        ];

        $jsonBuf = $pad4(json_encode($gltf, JSON_UNESCAPED_SLASHES), ' ');
        $totalLen = 12 + 8 + strlen($jsonBuf) + 8 + strlen($binBuffer);

        $header = 'glTF' . pack('VV', 2, $totalLen);
        $jsonHeader = pack('V', strlen($jsonBuf)) . 'JSON';
        $binHeader = pack('V', strlen($binBuffer)) . "BIN\0";

        return $header . $jsonHeader . $jsonBuf . $binHeader . $binBuffer;
    }

    /**
     * Fallback-Renderer in reinem PHP-GD, falls overlay.png für den Seelen-Talisman auf einem frischen System fehlt.
     */
    private function renderFallbackTalismanOverlay(string $targetPath): void
    {
        if (!extension_loaded('gd')) {
            return;
        }
        $im = imagecreatetruecolor(800, 800);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $trans);

        $gold = imagecolorallocatealpha($im, 236, 205, 98, 0);
        $goldDark = imagecolorallocatealpha($im, 168, 126, 30, 0);

        // Schlüsselring oben (cx=400, cy=184, rOut=144, rIn=113)
        imagefilledellipse($im, 400, 184, 288, 288, $gold);
        imagefilledellipse($im, 400, 184, 226, 226, $trans);

        // Gold-Platte (x=271..529, y=322..760)
        imagefilledrectangle($im, 271, 378, 529, 704, $gold);
        imagefilledellipse($im, 400, 498, 352, 352, $gold);
        imagefilledellipse($im, 400, 584, 352, 352, $gold);
        // Stanzloch
        imagefilledellipse($im, 400, 353, 33, 33, $trans);
        // Kleiner Biegering
        imagesetthickness($im, 8);
        imagearc($im, 400, 322, 42, 64, 0, 360, $goldDark);

        imagepng($im, $targetPath, 9);
        imagedestroy($im);
    }

    /**
     * Rendert das fotorealistische 800x800 Gold-Edelstahl-Overlay für Die Seelen-Pfote (40 x 22 x 1,8 mm)
     * mit 4x4-Subpixel-Scanline-Supersampling, 3D-Gold-Fase und 100 % transparentem Ösen- & Pfoten-Durchbruch.
     */
    private function renderPfoteGoldOverlay(string $targetPath): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $w = 800;
        $h = 800;
        $outerPts = $this->getPfoteOuterPoints(22);
        $nPts = count($outerPts);

        $subSteps = 4;
        $rowIntervals = [];
        for ($sy = 0; $sy < $h * $subSteps; $sy++) {
            $py = ($sy + 0.5) / $subSteps;
            if ($py < 235.0 || $py > 565.0) {
                $rowIntervals[$sy] = [];
                continue;
            }
            $xs = [];
            for ($i = 0; $i < $nPts; $i++) {
                $j = ($i + 1) % $nPts;
                $y0 = $outerPts[$i][1];
                $y1 = $outerPts[$j][1];
                if (($y0 <= $py && $y1 > $py) || ($y1 <= $py && $y0 > $py)) {
                    $t = ($py - $y0) / ($y1 - $y0);
                    $xs[] = $outerPts[$i][0] + $t * ($outerPts[$j][0] - $outerPts[$i][0]);
                }
            }
            sort($xs);
            $pairs = [];
            for ($k = 0; $k + 1 < count($xs); $k += 2) {
                $pairs[] = [$xs[$k], $xs[$k + 1]];
            }
            $rowIntervals[$sy] = $pairs;
        }

        $toes = [];
        foreach ($this->getPfoteToeDefinitions() as [$tcx, $tcy, $trx, $try, $tdeg]) {
            $rad = deg2rad($tdeg);
            $toes[] = [$tcx, $tcy, $trx, $try, cos($rad), sin($rad)];
        }

        $holeSignedDistance = static function (float $px, float $py) use ($toes): float {
            // 1. Top eyelet hole (400, 279, r = 23.5)
            $dEye = hypot($px - 400.0, $py - 279.0) - 23.5;
            $minD = $dEye;

            if ($px > 545.0 && $py > 390.0) {
                // 2. Kidney-shaped main paw pad (634.8, 478.9)
                $dx = $px - 634.8;
                $dy = $py - 478.9;
                $dist = hypot($dx, $dy);
                $theta = atan2($dy, $dx);
                $delta = $theta - (M_PI / 4.0);
                $rPad = 25.5
                    - 3.2 * cos($delta)
                    - 1.2 * cos(2.0 * $delta)
                    - 6.8 * cos(3.0 * $delta)
                    + 2.0 * cos(4.0 * $delta);
                $dPad = $dist - $rPad;
                if ($dPad < $minD) {
                    $minD = $dPad;
                }

                // 3. Four oval toe pads
                foreach ($toes as [$cx, $cy, $rx, $ry, $cosA, $sinA]) {
                    $tx = $px - $cx;
                    $ty = $py - $cy;
                    $lx = $tx * $cosA + $ty * $sinA;
                    $ly = -$tx * $sinA + $ty * $cosA;
                    $norm = hypot($lx / $rx, $ly / $ry);
                    $dToe = ($norm - 1.0) * (($rx + $ry) * 0.5);
                    if ($dToe < $minD) {
                        $minD = $dToe;
                    }
                }
            }

            return $minD;
        };

        $subOffsets = [0.125, 0.375, 0.625, 0.875];
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $trans);

        for ($y = 235; $y <= 565; $y++) {
            for ($x = 76; $x <= 724; $x++) {
                $dHoleCenter = $holeSignedDistance($x + 0.5, $y + 0.5);
                $cov = 0;
                $minOuterHoriz = 999.0;

                for ($sj = 0; $sj < 4; $sj++) {
                    $sy = $y * 4 + $sj;
                    $pairs = $rowIntervals[$sy] ?? [];
                    if (!$pairs) {
                        continue;
                    }
                    $py = $y + $subOffsets[$sj];
                    for ($si = 0; $si < 4; $si++) {
                        $px = $x + $subOffsets[$si];
                        $inOuter = false;
                        foreach ($pairs as [$x0, $x1]) {
                            if ($px >= $x0 && $px <= $x1) {
                                $inOuter = true;
                                $dH = min($px - $x0, $x1 - $px);
                                if ($dH < $minOuterHoriz) {
                                    $minOuterHoriz = $dH;
                                }
                                break;
                            }
                        }
                        if ($inOuter && $holeSignedDistance($px, $py) > 0.0) {
                            $cov++;
                        }
                    }
                }

                if ($cov === 0) {
                    continue;
                }

                $minVertDist = 999.0;
                for ($dy = -9; $dy <= 9; $dy++) {
                    $checkSy = ($y + $dy) * 4 + 2;
                    $pairs = $rowIntervals[$checkSy] ?? [];
                    $insideCheck = false;
                    foreach ($pairs as [$x0, $x1]) {
                        if (($x + 0.5) >= $x0 && ($x + 0.5) <= $x1) {
                            $insideCheck = true;
                            break;
                        }
                    }
                    if (!$insideCheck) {
                        $minVertDist = min($minVertDist, (float) abs($dy));
                    }
                }

                $dOuter = min($minOuterHoriz, $minVertDist);
                $dEdge = min($dOuter, max(0.0, $dHoleCenter));

                $nx = ($x - 400.0) / 320.0;
                $ny = ($y - 400.0) / 162.0;

                // Warmes, hochglanzpoliertes 14k Gold-Edelstahl-Finish mit sanften Studio-Spiegelungen (gemäß Bild 1 & Bild 2)
                $grad = 0.54 - 0.16 * $nx - 0.18 * $ny;
                $sheen1 = exp(-pow(($nx * 0.65 + $ny * 0.75 + 0.12) / 0.36, 2)) * 0.24;
                $sheen2 = exp(-pow(($nx * 0.75 - $ny * 0.45 - 0.38) / 0.28, 2)) * 0.12;
                $darkBand = exp(-pow(($nx * 0.55 + $ny * 0.80 - 0.32) / 0.30, 2)) * 0.10;
                $val = max(0.0, min(1.0, $grad + $sheen1 + $sheen2 - $darkBand));

                $r = 194.0 + $val * 54.0;
                $g = 152.0 + $val * 60.0;
                $b = 70.0 + $val * 52.0;

                if ($dEdge < 8.5) {
                    $t = $dEdge / 8.5;
                    $smooth = $t * $t * (3.0 - 2.0 * $t);
                    $isTopLit = ($dHoleCenter < $dOuter) ? ($y > 279 && $y > 455) : ($ny < -0.15 || $nx < -0.55);
                    if ($t < 0.36) {
                        $darkFactor = 0.36 + 0.64 * ($t / 0.36);
                        $r *= $darkFactor;
                        $g *= $darkFactor;
                        $b *= $darkFactor;
                    } elseif ($t < 0.78) {
                        $bevelBoost = $isTopLit ? 1.13 : 0.84;
                        $mix = sin((($t - 0.36) / 0.42) * M_PI);
                        $r = $r * (1.0 - 0.45 * $mix) + min(255.0, $r * $bevelBoost) * (0.45 * $mix);
                        $g = $g * (1.0 - 0.45 * $mix) + min(242.0, $g * $bevelBoost) * (0.45 * $mix);
                        $b = $b * (1.0 - 0.45 * $mix) + min(155.0, $b * $bevelBoost) * (0.45 * $mix);
                    } else {
                        $r = $r * $smooth + ($r * 0.96) * (1.0 - $smooth);
                    }
                }

                $gdAlpha = (int) round(127.0 * (1.0 - ($cov / 16.0)));
                $gdAlpha = max(0, min(127, $gdAlpha));
                $col = imagecolorallocatealpha(
                    $im,
                    (int) max(0, min(255, round($r))),
                    (int) max(0, min(255, round($g))),
                    (int) max(0, min(255, round($b))),
                    $gdAlpha
                );
                imagesetpixel($im, $x, $y, $col);
            }
        }

        imagepng($im, $targetPath, 9);
        imagedestroy($im);
    }

    /**
     * Stellt sicher, dass alle Produktbilder (main, back, lifestyle) und die 8 Vorlagenbilder
     * von Die Seelen-Pfote im authentischen 14k Gold-Finish vorliegen.
     */
    private function ensurePfoteGoldGalleryAndTemplates(string $publicProductsPath, string $pfoteDir): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $this->convertPfoteSceneToGold($pfoteDir . '/seelen-pfote-main.jpg', 'closeup');
        $this->convertPfoteSceneToGold($pfoteDir . '/seelen-pfote-back.jpg', 'closeup');
        $this->convertPfoteSceneToGold($pfoteDir . '/seelen-pfote-lifestyle.jpg', 'lifestyle');

        $tplDir = dirname($publicProductsPath) . '/product-templates';
        foreach (['weihnachten', 'ostern', 'hochzeit', 'geburtstag', 'valentinstag', 'halloween', 'muttertag', 'vatertag'] as $hSlug) {
            $tplPath = $tplDir . '/seelen-pfote-hundemarke-' . $hSlug . '.jpg';
            if (file_exists($tplPath)) {
                $this->convertPfoteSceneToGold($tplPath, 'closeup');
            }
        }
    }

    /**
     * Färbt den silbernen Knochenanhänger & Spaltring in bestehenden Produkt-/Vorlagen-Szenenfotos
     * selektiv in warmes, hochglanzpoliertes 14k Gold um (ohne Hintergrund, Holz, Schnee, Blüten oder Gravur zu verfälschen).
     */
    private function convertPfoteSceneToGold(string $filePath, string $mode = 'closeup'): void
    {
        if (!file_exists($filePath)) {
            return;
        }
        $im = @imagecreatefromjpeg($filePath);
        if (!$im) {
            return;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $fname = basename($filePath);

        // Prüfen, ob das Foto bereits auf Gold umgestellt wurde
        $sampleX = ($mode === 'lifestyle') ? (int) ($w * 0.47) : (int) ($w * 0.35);
        $sampleY = ($mode === 'lifestyle') ? (int) ($h * 0.76) : (int) ($h * 0.45);
        $rgbSample = imagecolorat($im, $sampleX, $sampleY);
        $rs = ($rgbSample >> 16) & 0xFF;
        $bs = $rgbSample & 0xFF;
        if (($rs - $bs) > 45) {
            imagedestroy($im);
            return;
        }

        $coreMask = new \SplFixedArray($w * $h);
        $visited = new \SplFixedArray($w * $h);

        $isStanding = str_contains($fname, 'hochzeit') || str_contains($fname, 'valentin');
        $isFlatLay = str_contains($fname, 'ostern') || str_contains($fname, 'geburtstag') || str_contains($fname, 'muttertag');
        $isStudioAngle = str_contains($fname, 'main') || str_contains($fname, 'back') || str_contains($fname, 'weihnachten') || str_contains($fname, 'halloween');

        $inBoneReflectionZone = static function (float $nx, float $ny) use ($isStanding, $isStudioAngle, $isFlatLay): bool {
            if ($isStudioAngle) {
                $dLeft = pow(($nx - 0.228) / 0.094, 2) + pow(($ny - 0.628) / 0.102, 2);
                $dRight = pow(($nx - 0.736) / 0.098, 2) + pow(($ny - 0.692) / 0.108, 2);
                $inShaft = ($nx >= 0.28 && $nx <= 0.66 && $ny >= 0.60 && $ny <= 0.688 + ($nx - 0.28) * 0.11);
                return ($dLeft <= 1.0 || $dRight <= 1.0 || $inShaft);
            }
            if ($isStanding) {
                $dTopLeft = pow(($nx - 0.235) / 0.114, 2) + pow(($ny - 0.458) / 0.125, 2);
                $dBotLeft = pow(($nx - 0.246) / 0.116, 2) + pow(($ny - 0.648) / 0.132, 2);
                $dBotRight = pow(($nx - 0.768) / 0.114, 2) + pow(($ny - 0.602) / 0.126, 2);
                $shaftBotY = 0.695 - (($nx - 0.32) / 0.36) * 0.043;
                $inShaft = ($nx >= 0.30 && $nx <= 0.70 && $ny >= 0.50 && $ny <= $shaftBotY);
                return ($dTopLeft <= 1.0 || $dBotLeft <= 1.0 || $dBotRight <= 1.0 || $inShaft);
            }
            if ($isFlatLay) {
                $dTopRight = pow(($nx - 0.715) / 0.095, 2) + pow(($ny - 0.350) / 0.082, 2);
                return ($dTopRight <= 1.0);
            }
            return false;
        };

        $isSilverCore = static function (int $x, int $y, int $r, int $g, int $b) use ($w, $h, $mode, $inBoneReflectionZone, $isFlatLay): bool {
            $nx = $x / $w;
            $ny = $y / $h;
            if ($mode === 'lifestyle') {
                if ($nx < 0.435 || $nx > 0.618 || $ny < 0.705 || $ny > 0.850) {
                    return false;
                }
            } else {
                if ($nx < 0.095 || $nx > 0.915 || $ny < 0.175 || $ny > 0.805) {
                    return false;
                }
                if ($ny < 0.285 && ($nx < 0.41 || $nx > 0.61)) {
                    return false;
                }
            }

            $chroma = max($r, $g, $b) - min($r, $g, $b);
            $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;

            if ($mode !== 'lifestyle' && $ny < 0.415 && $nx >= 0.41 && $nx <= 0.605) {
                return ($lum >= 70.0 && $chroma <= 36 && ($r - $b) <= 30);
            }
            if ($lum >= 152.0 && $chroma <= 30 && ($r - $b) <= 26 && ($g - $r) <= 12) {
                return true;
            }
            if ($mode !== 'lifestyle' && $inBoneReflectionZone($nx, $ny)) {
                if ($isFlatLay) {
                    return ($lum >= 148.0 && ($r - $b) <= 45 && ($g - $r) <= 10);
                }
                if ($lum >= 58.0 && ($g - $r) <= 10) {
                    return true;
                }
            }
            return false;
        };

        if ($isStudioAngle) {
            for ($py = (int) ($h * 0.18); $py <= (int) ($h * 0.41); $py++) {
                $ny = $py / $h;
                for ($px = (int) ($w * 0.44); $px <= (int) ($w * 0.605); $px++) {
                    $nx = $px / $w;
                    $rOuter = hypot(($nx - 0.528) / 0.076, ($ny - 0.296) / 0.116);
                    $rInner = hypot(($nx - 0.532) / 0.033, ($ny - 0.296) / 0.068);
                    if ($rOuter <= 1.0 && $rInner >= 1.0) {
                        $rgb = imagecolorat($im, $px, $py);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        $chroma = max($r, $g, $b) - min($r, $g, $b);
                        $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                        if ($lum >= 65.0 && $chroma <= 32 && ($r - $b) <= 26) {
                            $coreMask[$py * $w + $px] = 1;
                        }
                    }
                }
            }
        }

        $queue = new \SplQueue();
        $seeds = ($mode === 'lifestyle')
            ? [[0.47, 0.76], [0.53, 0.78], [0.58, 0.81], [0.47, 0.81], [0.53, 0.74]]
            : [
                [0.25, 0.42], [0.35, 0.45], [0.50, 0.45], [0.65, 0.48], [0.75, 0.45],
                [0.22, 0.58], [0.45, 0.60], [0.65, 0.60], [0.78, 0.58],
                [0.50, 0.36], [0.50, 0.64], [0.18, 0.45], [0.18, 0.50], [0.22, 0.68], [0.74, 0.74],
                [0.475, 0.28], [0.575, 0.28], [0.50, 0.20], [0.47, 0.24], [0.58, 0.25], [0.48, 0.33], [0.55, 0.33],
            ];

        foreach ($seeds as [$sx, $sy]) {
            $px = (int) round($sx * $w);
            $py = (int) round($sy * $h);
            for ($dy = -4; $dy <= 4; $dy += 2) {
                for ($dx = -4; $dx <= 4; $dx += 2) {
                    $qx = $px + $dx;
                    $qy = $py + $dy;
                    $idx = $qy * $w + $qx;
                    if (!$visited[$idx]) {
                        $rgb = imagecolorat($im, $qx, $qy);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        if ($isSilverCore($qx, $qy, $r, $g, $b)) {
                            $visited[$idx] = 1;
                            $coreMask[$idx] = 1;
                            $queue->enqueue([$qx, $qy]);
                        }
                    }
                }
            }
        }

        $dirs = [[-1, 0], [1, 0], [0, -1], [0, 1], [-1, -1], [1, -1], [-1, 1], [1, 1]];
        while (!$queue->isEmpty()) {
            [$cx, $cy] = $queue->dequeue();
            foreach ($dirs as [$dx, $dy]) {
                $nx = $cx + $dx;
                $ny = $cy + $dy;
                if ($nx < 0 || $nx >= $w || $ny < 0 || $ny >= $h) {
                    continue;
                }
                $nIdx = $ny * $w + $nx;
                if ($visited[$nIdx]) {
                    continue;
                }
                $visited[$nIdx] = 1;

                $rgb = imagecolorat($im, $nx, $ny);
                $nr = ($rgb >> 16) & 0xFF;
                $ng = ($rgb >> 8) & 0xFF;
                $nb = $rgb & 0xFF;
                if ($isSilverCore($nx, $ny, $nr, $ng, $nb)) {
                    $coreMask[$nIdx] = 1;
                    $queue->enqueue([$nx, $ny]);
                }
            }
        }

        $extVisited = new \SplFixedArray($w * $h);
        $extQueue = new \SplQueue();
        $addExtSeed = static function (int $sx, int $sy, bool $forceClearCore = false) use ($w, $h, &$coreMask, &$extVisited, $extQueue): void {
            if ($sx < 0 || $sx >= $w || $sy < 0 || $sy >= $h) {
                return;
            }
            $idx = $sy * $w + $sx;
            if ($forceClearCore) {
                $coreMask[$idx] = 0;
            }
            if (!$coreMask[$idx] && !$extVisited[$idx]) {
                $extVisited[$idx] = 1;
                $extQueue->enqueue([$sx, $sy]);
            }
        };
        for ($x = 0; $x < $w; $x += 2) {
            $addExtSeed($x, 0);
            $addExtSeed($x, $h - 1);
        }
        for ($y = 0; $y < $h; $y += 2) {
            $addExtSeed(0, $y);
            $addExtSeed($w - 1, $y);
        }

        if ($mode === 'lifestyle') {
            $addExtSeed((int) ($w * 0.528), (int) ($h * 0.745), true);
            $addExtSeed((int) ($w * 0.583), (int) ($h * 0.772), true);
        } else {
            $pawBoxes = [];
            if ($fname === 'seelen-pfote-back.jpg') {
                $pawBoxes[] = [0.168, 0.545, 0.292, 0.688];
            } elseif ($isStudioAngle) {
                $pawBoxes[] = [0.678, 0.608, 0.808, 0.758];
            } elseif ($isStanding) {
                $pawBoxes[] = [0.728, 0.532, 0.852, 0.675];
            } elseif ($isFlatLay) {
                $pawBoxes[] = [0.738, 0.440, 0.862, 0.555];
            } else {
                $pawBoxes[] = [0.742, 0.552, 0.858, 0.695];
            }
            foreach ($pawBoxes as [$bx0, $by0, $bx1, $by1]) {
                for ($py = (int) ($by0 * $h); $py <= (int) ($by1 * $h); $py++) {
                    for ($px = (int) ($bx0 * $w); $px <= (int) ($bx1 * $w); $px++) {
                        $rgb = imagecolorat($im, $px, $py);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                        $chroma = max($r, $g, $b) - min($r, $g, $b);
                        if ($lum < 135.0 || $chroma > 28) {
                            $coreMask[$py * $w + $px] = 0;
                            $addExtSeed($px, $py);
                        }
                    }
                }
            }
            $addExtSeed((int) ($w * 0.495), (int) ($h * 0.395));
            $addExtSeed((int) ($w * 0.450), (int) ($h * 0.385));
            $addExtSeed((int) ($w * 0.515), (int) ($h * 0.365));
            $addExtSeed((int) ($w * 0.530), (int) ($h * 0.295));
        }

        while (!$extQueue->isEmpty()) {
            [$cx, $cy] = $extQueue->dequeue();
            foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
                $nx = $cx + $dx;
                $ny = $cy + $dy;
                if ($nx < 0 || $nx >= $w || $ny < 0 || $ny >= $h) {
                    continue;
                }
                $nIdx = $ny * $w + $nx;
                if (!$coreMask[$nIdx] && !$extVisited[$nIdx]) {
                    $extVisited[$nIdx] = 1;
                    $extQueue->enqueue([$nx, $ny]);
                }
            }
        }

        for ($i = 0, $n = $w * $h; $i < $n; $i++) {
            if (!$extVisited[$i]) {
                $coreMask[$i] = 1;
            }
        }

        $dilated = clone $coreMask;
        $radius = ($mode === 'lifestyle') ? 2 : 5;
        for ($step = 0; $step < $radius; $step++) {
            $next = clone $dilated;
            for ($y = 1; $y < $h - 1; $y++) {
                $row = $y * $w;
                for ($x = 1; $x < $w - 1; $x++) {
                    $idx = $row + $x;
                    if ($dilated[$idx]) {
                        continue;
                    }
                    if ($dilated[$idx - 1] || $dilated[$idx + 1] || $dilated[$idx - $w] || $dilated[$idx + $w]) {
                        $rgb = imagecolorat($im, $x, $y);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                        if (($r - $g) < 30 && ($g - $r) < 12 && (($r - $b) < 38 || $lum > 145)) {
                            $next[$idx] = 1;
                        }
                    }
                }
            }
            $dilated = $next;
        }

        $mask = array_fill(0, $w * $h, 0.0);
        for ($i = 0, $n = $w * $h; $i < $n; $i++) {
            if ($dilated[$i]) {
                $mask[$i] = 1.0;
            }
        }
        for ($pass = 0; $pass < 2; $pass++) {
            $nextMask = $mask;
            for ($y = 1; $y < $h - 1; $y++) {
                $row = $y * $w;
                for ($x = 1; $x < $w - 1; $x++) {
                    $idx = $row + $x;
                    $sum = $mask[$idx - $w - 1] + $mask[$idx - $w] + $mask[$idx - $w + 1]
                         + $mask[$idx - 1]      + $mask[$idx]      + $mask[$idx + 1]
                         + $mask[$idx + $w - 1] + $mask[$idx + $w] + $mask[$idx + $w + 1];
                    $nextMask[$idx] = $sum / 9.0;
                }
            }
            $mask = $nextMask;
        }

        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $m = $mask[$row + $x];
                if ($m <= 0.01) {
                    continue;
                }

                $rgb = imagecolorat($im, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255.0;

                if ($lum < 0.25) {
                    $t = $lum / 0.25;
                    $gr = 24.0 + $t * (115.0 - 24.0);
                    $gg = 19.0 + $t * (84.0 - 19.0);
                    $gb = 14.0 + $t * (32.0 - 14.0);
                } elseif ($lum < 0.55) {
                    $t = ($lum - 0.25) / 0.30;
                    $gr = 115.0 + $t * (198.0 - 115.0);
                    $gg = 84.0 + $t * (154.0 - 84.0);
                    $gb = 32.0 + $t * (68.0 - 32.0);
                } elseif ($lum < 0.85) {
                    $t = ($lum - 0.55) / 0.30;
                    $gr = 198.0 + $t * (236.0 - 198.0);
                    $gg = 154.0 + $t * (196.0 - 154.0);
                    $gb = 68.0 + $t * (104.0 - 68.0);
                } else {
                    $t = ($lum - 0.85) / 0.15;
                    $gr = 236.0 + $t * (250.0 - 236.0);
                    $gg = 196.0 + $t * (219.0 - 196.0);
                    $gb = 104.0 + $t * (138.0 - 104.0);
                }

                $origDr = $r - ($lum * 255.0);
                $origDg = $g - ($lum * 255.0);
                $origDb = $b - ($lum * 255.0);
                $gr += $origDr * 0.14;
                $gg += $origDg * 0.14;
                $gb += $origDb * 0.10;

                $fr = (int) round(max(0, min(255, $r * (1.0 - $m) + $gr * $m)));
                $fg = (int) round(max(0, min(255, $g * (1.0 - $m) + $gg * $m)));
                $fb = (int) round(max(0, min(255, $b * (1.0 - $m) + $gb * $m)));

                imagesetpixel($im, $x, $y, ($fr << 16) | ($fg << 8) | $fb);
            }
        }

        imagejpeg($im, $filePath, 92);
        imagedestroy($im);
    }

    /**
     * Liefert die 800x800 Herz-Außenkontur für Das Goldene Seelen-Herz (25 x 22 x 4 mm mit Collierschlaufe).
     * Symmetrisch um x = 400.0 (x = 140.0 .. 660.0, y = 281.0 .. 754.0) mit harmonisch abgerundeter
     * unterer Herzspitze (horizontale Tangente bei (400.0, 754.0)) und vollen unteren Herzflanken.
     */
    private function getGoldenesHerzPoints(): array
    {
        $pts = [];
        $sampleCubic = static function (array $p0, array $p1, array $p2, array $p3, int $steps) use (&$pts): void {
            for ($i = 1; $i <= $steps; $i++) {
                $t = $i / $steps;
                $mt = 1.0 - $t;
                $x = $mt * $mt * $mt * $p0[0] + 3.0 * $mt * $mt * $t * $p1[0] + 3.0 * $mt * $t * $t * $p2[0] + $t * $t * $t * $p3[0];
                $y = $mt * $mt * $mt * $p0[1] + 3.0 * $mt * $mt * $t * $p1[1] + 3.0 * $mt * $t * $t * $p2[1] + $t * $t * $t * $p3[1];
                $pts[] = [$x, $y];
            }
        };

        $pts[] = [400.0, 311.0];
        $sampleCubic([400.0, 311.0], [420.0, 290.0], [456.0, 281.0], [496.0, 281.0], 24);
        $sampleCubic([496.0, 281.0], [594.0, 281.0], [660.0, 338.0], [660.0, 436.0], 36);
        $sampleCubic([660.0, 436.0], [660.0, 512.0], [611.0, 583.0], [553.0, 645.0], 36);
        $sampleCubic([553.0, 645.0], [510.0, 687.0], [485.0, 706.0], [464.0, 720.0], 24);
        // Abgerundete untere Herzspitze mit exakt horizontaler Tangente bei (400.0, 754.0)
        $sampleCubic([464.0, 720.0], [442.0, 734.5], [420.0, 754.0], [400.0, 754.0], 24);
        $sampleCubic([400.0, 754.0], [380.0, 754.0], [358.0, 734.5], [336.0, 720.0], 24);
        $sampleCubic([336.0, 720.0], [315.0, 706.0], [290.0, 687.0], [247.0, 645.0], 24);
        $sampleCubic([247.0, 645.0], [189.0, 583.0], [140.0, 512.0], [140.0, 436.0], 36);
        $sampleCubic([140.0, 436.0], [140.0, 338.0], [206.0, 281.0], [304.0, 281.0], 36);
        $sampleCubic([304.0, 281.0], [344.0, 281.0], [380.0, 290.0], [400.0, 311.0], 24);
        array_pop($pts);

        return $pts;
    }

    /**
     * Liefert die 800x800 Kontur der trapezförmigen Collierschlaufe (Bail, Öse 3 x 6 mm),
     * exakt symmetrisch um x = 400.0 (y = 46.0 .. 228.0), sodass min(y) + max(y) = 46 + 754 = 800.0 gilt.
     */
    private function getGoldenesHerzBailPoints(): array
    {
        $bailPts = [];
        $stepsSide = 24;
        $stepsArch = 12;

        // Obere leicht gewölbte Kante von links (356.5, 48.8) über (400.0, 46.0) nach rechts (443.5, 48.8)
        for ($i = 0; $i <= $stepsArch; $i++) {
            $u = -1.0 + 2.0 * ($i / $stepsArch);
            $x = 400.0 + 43.5 * $u;
            $y = 46.0 + 0.0015 * ($x - 400.0) * ($x - 400.0);
            $bailPts[] = [$x, $y];
        }

        // Rechte verjüngte Flanke von y=48.8 nach y=228.0
        for ($i = 1; $i <= $stepsSide; $i++) {
            $t = $i / $stepsSide;
            $y = 48.8 + (228.0 - 48.8) * $t;
            $halfW = 43.5 * (1.0 - $t) + 17.5 * $t - 2.5 * sin($t * M_PI);
            $bailPts[] = [400.0 + $halfW, $y];
        }

        // Untere Schlaufenkante bei y=228.0
        for ($i = 1; $i < $stepsArch; $i++) {
            $u = 1.0 - 2.0 * ($i / $stepsArch);
            $x = 400.0 + 17.5 * $u;
            $y = 228.0 + 0.004 * ($x - 400.0) * ($x - 400.0);
            $bailPts[] = [$x, $y];
        }

        // Linke verjüngte Flanke von y=228.0 zurück nach oben
        for ($i = 0; $i < $stepsSide; $i++) {
            $t = 1.0 - ($i / $stepsSide);
            $y = 48.8 + (228.0 - 48.8) * $t;
            $halfW = 43.5 * (1.0 - $t) + 17.5 * $t - 2.5 * sin($t * M_PI);
            $bailPts[] = [400.0 - $halfW, $y];
        }

        return $bailPts;
    }

    /**
     * Erzeugt das exakt zum 2D-Overlay (800x800) deckungsgleiche 3D-Mesh für Das Goldene Seelen-Herz
     * inklusive Zwischenring (Jump Ring) und trapezförmiger Collierschlaufe (Bail, Öse 3 x 6 mm).
     */
    private function buildGoldenesHerz3DMesh(): array
    {
        $positions = [];
        $normals = [];
        $uvs = [];
        $indices = [];

        // 1. Massiver 4,0 mm Herz-Korpus (Z = ±0.088 bestimmt die exakte Haupttiefe für Vorder-/Rückseiten-Gravur)
        $heartPts = $this->getGoldenesHerzPoints();
        $this->extrudePixelContour($heartPts, null, [400.0, 485.0], 0.088, $positions, $normals, $uvs, $indices, 2.0);

        // 2. Runder Zwischenring (Jump Ring) in der Herz-Einkerbung bei cx=400.0, cy=258.5
        $ringSteps = 64;
        $ringOuter = [];
        $ringInner = [];
        for ($i = 0; $i < $ringSteps; $i++) {
            $a = ($i / $ringSteps) * M_PI * 2.0;
            $ringOuter[] = [400.0 + 57.2 * cos($a), 258.5 + 52.75 * sin($a)];
            $ringInner[] = [400.0 + 32.8 * cos($a), 258.5 + 30.25 * sin($a)];
        }
        $this->extrudePixelContour($ringOuter, $ringInner, [400.0, 258.5], 0.052, $positions, $normals, $uvs, $indices, 2.2);

        // 3. Trapezförmige Collierschlaufe (Bail, Öse 3 x 6 mm: y = 46.0 .. 228.0)
        $bailPts = $this->getGoldenesHerzBailPoints();
        $this->extrudePixelContour($bailPts, null, [400.0, 136.0], 0.084, $positions, $normals, $uvs, $indices, 2.0);

        return compact('positions', 'normals', 'uvs', 'indices');
    }

    /**
     * Rendert das gestochen scharfe 800x800 2D-Overlay (overlay.png) für Das Goldene Seelen-Herz
     * (14k Ion-Plating Edelstahl-Herz mit Collierschlaufe, 25 x 22 x 4 mm, Öse 3 x 6 mm)
     * mittels 16-fachem Subpixel-Supersampling (4x4 SSAA) und exakt deckungsgleicher 3D-Geometrie.
     */
    private function renderGoldenesHerzOverlay(string $targetPath): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $heartPts = $this->getGoldenesHerzPoints();
        $nPts = count($heartPts);
        $ptNormals = [];
        for ($i = 0; $i < $nPts; $i++) {
            $prev = $heartPts[($i - 1 + $nPts) % $nPts];
            $next = $heartPts[($i + 1) % $nPts];
            $tx = $next[0] - $prev[0];
            $ty = $next[1] - $prev[1];
            $len = hypot($tx, $ty) ?: 1.0;
            $ptNormals[$i] = [$ty / $len, -$tx / $len];
        }

        $ss = 4;
        $maskHeartSS = imagecreatetruecolor(800 * $ss, 800 * $ss);
        $mBlack = imagecolorallocate($maskHeartSS, 0, 0, 0);
        $mWhite = imagecolorallocate($maskHeartSS, 255, 255, 255);
        imagefill($maskHeartSS, 0, 0, $mBlack);

        $flatHeartSS = [];
        foreach ($heartPts as [$px, $py]) {
            $flatHeartSS[] = (int) round($px * $ss);
            $flatHeartSS[] = (int) round($py * $ss);
        }
        imagefilledpolygon($maskHeartSS, $flatHeartSS, $mWhite);

        $out = imagecreatetruecolor(800, 800);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $trans = imagecolorallocatealpha($out, 218, 178, 92, 127);
        imagefill($out, 0, 0, $trans);

        // 1. Zwischenring (Jump Ring) & Collierschlaufe (Bail) mit 16x SSAA (4x4 Subpixel) rendern
        for ($py = 42; $py <= 318; $py++) {
            for ($px = 335; $px <= 465; $px++) {
                $rAcc = 0.0;
                $gAcc = 0.0;
                $bAcc = 0.0;
                $cov = 0;

                for ($sy = 0; $sy < 4; $sy++) {
                    $y = $py + ($sy + 0.5) * 0.25;
                    for ($sx = 0; $sx < 4; $sx++) {
                        $x = $px + ($sx + 0.5) * 0.25;

                        $inBail = false;
                        $bailR = 0.0;
                        $bailG = 0.0;
                        $bailB = 0.0;
                        $yTopBail = 46.0 + 0.0015 * ($x - 400.0) * ($x - 400.0);
                        $yBotBail = 228.0 + 0.004 * ($x - 400.0) * ($x - 400.0);

                        if ($y >= $yTopBail && $y <= $yBotBail) {
                            $tBail = ($y - 46.0) / (228.0 - 46.0);
                            $halfW = 43.5 * (1.0 - $tBail) + 17.5 * $tBail - 2.5 * sin($tBail * M_PI);
                            $dxBail = $x - 400.0;
                            if (abs($dxBail) <= $halfW) {
                                $okCorner = true;
                                if ($y < $yTopBail + 5.0 && abs($dxBail) > $halfW - 5.0) {
                                    $cxC = ($dxBail > 0 ? 1 : -1) * ($halfW - 5.0);
                                    $cyC = $yTopBail + 5.0;
                                    if (hypot($dxBail - $cxC, $y - $cyC) > 5.0) {
                                        $okCorner = false;
                                    }
                                }
                                if ($okCorner) {
                                    $inBail = true;
                                    $u = $dxBail / max(1.0, $halfW);
                                    $distEdge = $halfW - abs($dxBail);
                                    $cyl = cos(($u + 0.18) * 1.35);
                                    $vert = 1.0 - 0.11 * $tBail;
                                    $baseR = (244.0 + 17.0 * $cyl) * $vert;
                                    $baseG = (207.0 + 23.0 * $cyl) * $vert;
                                    $baseB = (130.0 + 25.0 * $cyl) * $vert;

                                    if ($distEdge < 4.2 && $distEdge >= 1.5) {
                                        $bevelMix = 1.0 - abs($distEdge - 2.8) / 1.5;
                                        $bevelMix = max(0.0, min(1.0, $bevelMix)) * ($u > 0 ? 0.70 : 0.44);
                                        $baseR = $baseR * (1.0 - $bevelMix) + 125.0 * $bevelMix;
                                        $baseG = $baseG * (1.0 - $bevelMix) +  88.0 * $bevelMix;
                                        $baseB = $baseB * (1.0 - $bevelMix) +  32.0 * $bevelMix;
                                    } elseif ($distEdge < 1.5) {
                                        $baseR = 228.0;
                                        $baseG = 186.0;
                                        $baseB = 104.0;
                                    }
                                    $dTop = $y - $yTopBail;
                                    if ($dTop < 5.5) {
                                        $lip = sin(($dTop / 5.5) * M_PI);
                                        $baseR -= 36.0 * $lip;
                                        $baseG -= 40.0 * $lip;
                                        $baseB -= 30.0 * $lip;
                                    }
                                    if ($y > 204.0) {
                                        $clasp = ($y - 204.0) / 24.0;
                                        $notchShadow = exp(-pow(($dxBail + 3.0) / 7.0, 2)) * sin($clasp * M_PI * 1.5);
                                        $baseR -= 52.0 * $notchShadow;
                                        $baseG -= 48.0 * $notchShadow;
                                        $baseB -= 32.0 * $notchShadow;
                                    }
                                    $bailR = max(75.0, min(255.0, $baseR));
                                    $bailG = max(50.0, min(246.0, $baseG));
                                    $bailB = max(18.0, min(185.0, $baseB));
                                }
                            }
                        }

                        $inRing = false;
                        $ringR = 0.0;
                        $ringG = 0.0;
                        $ringB = 0.0;
                        $rx = $x - 400.0;
                        $ry = ($y - 258.5) * (45.0 / 41.5);
                        $rDist = hypot($rx, $ry);
                        $tubeDist = abs($rDist - 45.0);
                        if ($tubeDist <= 12.2) {
                            $inRing = true;
                            $tubeU = ($rDist - 45.0) / 12.2;
                            $nz = sqrt(max(0.0, 1.0 - $tubeU * $tubeU));
                            $ang = atan2($ry, $rx);
                            $nx = cos($ang) * $tubeU;
                            $ny = sin($ang) * $tubeU;

                            $light1 = max(0.0, -0.45 * $nx - 0.45 * $ny + 0.77 * $nz);
                            $light2 = max(0.0,  0.50 * $nx + 0.30 * $ny + 0.81 * $nz);
                            $ringR = 196.0 + 58.0 * $light1 + 25.0 * $light2 - 42.0 * (1.0 - $nz);
                            $ringG = 156.0 + 62.0 * $light1 + 22.0 * $light2 - 44.0 * (1.0 - $nz);
                            $ringB =  80.0 + 55.0 * $light1 + 18.0 * $light2 - 32.0 * (1.0 - $nz);

                            $reflBand = exp(-pow(($tubeU - 0.38) / 0.22, 2)) * (0.45 + 0.35 * sin($ang * 2.0 + 0.8));
                            $ringR -= 68.0 * $reflBand;
                            $ringG -= 64.0 * $reflBand;
                            $ringB -= 42.0 * $reflBand;

                            if ($y < 236.0 && abs($x - 400.0) < 24.0) {
                                $ao = max(0.0, 1.0 - hypot(($x - 400.0) / 22.0, ($y - 224.0) / 14.0));
                                $ringR *= (1.0 - 0.42 * $ao);
                                $ringG *= (1.0 - 0.45 * $ao);
                                $ringB *= (1.0 - 0.48 * $ao);
                            }

                            $ringR = max(70.0, min(255.0, $ringR));
                            $ringG = max(48.0, min(248.0, $ringG));
                            $ringB = max(18.0, min(190.0, $ringB));
                        }

                        if ($inBail && (!$inRing || $y <= 225.5 || abs($x - 400.0) <= 16.5)) {
                            $rAcc += $bailR;
                            $gAcc += $bailG;
                            $bAcc += $bailB;
                            $cov++;
                        } elseif ($inRing) {
                            $rAcc += $ringR;
                            $gAcc += $ringG;
                            $bAcc += $ringB;
                            $cov++;
                        }
                    }
                }

                if ($cov > 0) {
                    $r = (int) round($rAcc / $cov);
                    $g = (int) round($gAcc / $cov);
                    $b = (int) round($bAcc / $cov);
                    $gdAlpha = (int) round(127.0 * (1.0 - ($cov / 16.0)));
                    imagesetpixel($out, $px, $py, ($gdAlpha << 24) | ($r << 16) | ($g << 8) | $b);
                }
            }
        }

        // 2. Herz-Korpus mit 16x SSAA und analytischer 4,0 mm 3D-Glanzfase (keinerlei Rand-Unschärfe) rendern
        for ($py = 276; $py <= 758; $py++) {
            for ($px = 136; $px <= 664; $px++) {
                $cov = 0;
                $baseY = $py * $ss;
                $baseX = $px * $ss;
                for ($sy = 0; $sy < $ss; $sy++) {
                    for ($sx = 0; $sx < $ss; $sx++) {
                        if ((imagecolorat($maskHeartSS, $baseX + $sx, $baseY + $sy) & 0xFF) > 127) {
                            $cov++;
                        }
                    }
                }
                if ($cov === 0) {
                    continue;
                }

                $minDist = 999.0;
                $bestIdx = 0;
                for ($i = 0; $i < $nPts; $i += 3) {
                    $d = hypot($px - $heartPts[$i][0], $py - $heartPts[$i][1]);
                    if ($d < $minDist) {
                        $minDist = $d;
                        $bestIdx = $i;
                    }
                }
                $minSegDist = 999.0;
                $nx = 0.0;
                $ny = -1.0;
                for ($off = -4; $off <= 4; $off++) {
                    $i0 = ($bestIdx + $off + $nPts) % $nPts;
                    $i1 = ($i0 + 1) % $nPts;
                    $ax = $heartPts[$i0][0];
                    $ay = $heartPts[$i0][1];
                    $vx = $heartPts[$i1][0] - $ax;
                    $vy = $heartPts[$i1][1] - $ay;
                    $len2 = $vx * $vx + $vy * $vy;
                    $tSeg = $len2 > 1e-6 ? max(0.0, min(1.0, (($px - $ax) * $vx + ($py - $ay) * $vy) / $len2)) : 0.0;
                    $d = hypot($px - ($ax + $tSeg * $vx), $py - ($ay + $tSeg * $vy));
                    if ($d < $minSegDist) {
                        $minSegDist = $d;
                        $nx = $ptNormals[$i0][0] * (1.0 - $tSeg) + $ptNormals[$i1][0] * $tSeg;
                        $ny = $ptNormals[$i0][1] * (1.0 - $tSeg) + $ptNormals[$i1][1] * $tSeg;
                        $nLen = hypot($nx, $ny) ?: 1.0;
                        $nx /= $nLen;
                        $ny /= $nLen;
                    }
                }
                $minDist = $minSegDist;

                $uX = ($px - 400.0) / 260.0;
                $uY = ($py - 500.0) / 240.0;
                $keyGlow = exp(-(pow(($uX + 0.78) / 0.72, 2) + pow(($uY + 0.08) / 0.85, 2)));
                $r = 247.0 + 7.5 * $keyGlow - 3.5 * $uX - 2.0 * $uY;
                $g = 216.0 + 28.0 * $keyGlow - 9.5 * $uX - 4.5 * $uY;
                $b = 148.0 + 52.0 * $keyGlow - 14.0 * $uX - 6.0 * $uY;

                if ($minDist < 26.0) {
                    $leftSpec = max(0.0, -$nx) * exp(-pow(($minDist - 9.5) / 5.5, 2));
                    $r += 8.0 * $leftSpec;
                    $g += 22.0 * $leftSpec;
                    $b += 42.0 * $leftSpec;

                    $rightSpec = max(0.0, 0.65 * $nx + 0.55 * $ny) * exp(-pow(($minDist - 12.5) / 4.2, 2));
                    $r += 10.0 * $rightSpec;
                    $g += 24.0 * $rightSpec;
                    $b += 38.0 * $rightSpec;

                    $outerSlope = exp(-pow(($minDist - 5.2) / 3.2, 2));
                    $slopeDark = 0.28 + 0.24 * max(0.0, $ny) + 0.14 * abs($nx);
                    $r = $r * (1.0 - $slopeDark * $outerSlope) + 215.0 * ($slopeDark * $outerSlope);
                    $g = $g * (1.0 - $slopeDark * $outerSlope) + 162.0 * ($slopeDark * $outerSlope);
                    $b = $b * (1.0 - $slopeDark * $outerSlope) +  82.0 * ($slopeDark * $outerSlope);

                    $rimRefl = exp(-pow(($minDist - 4.0) / 1.8, 2)) * (0.22 + 0.25 * max(0.0, $nx * 0.7 - $ny * 0.3) + 0.25 * max(0.0, -$nx * 0.5 + $ny * 0.7));
                    $r = $r * (1.0 - $rimRefl) + 158.0 * $rimRefl;
                    $g = $g * (1.0 - $rimRefl) + 116.0 * $rimRefl;
                    $b = $b * (1.0 - $rimRefl) +  46.0 * $rimRefl;

                    if ($py > 715) {
                        $tipCrescent = exp(-pow(($px - 400.0) / 22.0, 2)) * exp(-pow(($minDist - 13.5) / 2.6, 2)) * max(0.0, $ny);
                        $r = $r * (1.0 - 0.72 * $tipCrescent) + 68.0 * (0.72 * $tipCrescent);
                        $g = $g * (1.0 - 0.72 * $tipCrescent) + 46.0 * (0.72 * $tipCrescent);
                        $b = $b * (1.0 - 0.72 * $tipCrescent) + 14.0 * (0.72 * $tipCrescent);
                    }

                    $edgeDef = exp(-pow($minDist / 1.6, 2));
                    $r = $r * (1.0 - 0.55 * $edgeDef) + 224.0 * (0.55 * $edgeDef);
                    $g = $g * (1.0 - 0.55 * $edgeDef) + 182.0 * (0.55 * $edgeDef);
                    $b = $b * (1.0 - 0.55 * $edgeDef) +  98.0 * (0.55 * $edgeDef);
                }

                if ($py < 328 && abs($px - 400.0) < 38.0) {
                    $notchDist = hypot(($px - 400.0) / 28.0, ($py - 306.0) / 14.0);
                    if ($notchDist < 1.0) {
                        $ao = (1.0 - $notchDist) * 0.35;
                        $r *= (1.0 - $ao);
                        $g *= (1.0 - $ao * 1.08);
                        $b *= (1.0 - $ao * 1.18);
                    }
                }

                $ir = (int) max(0, min(255, round($r)));
                $ig = (int) max(0, min(255, round($g)));
                $ib = (int) max(0, min(255, round($b)));

                if ($cov === 16) {
                    imagesetpixel($out, $px, $py, (0 << 24) | ($ir << 16) | ($ig << 8) | $ib);
                } else {
                    $existing = imagecolorat($out, $px, $py);
                    $exA = ($existing >> 24) & 0x7F;
                    $alpha = $cov / 16.0;
                    if ($exA >= 127) {
                        $gdAlpha = (int) round(127.0 * (1.0 - $alpha));
                        imagesetpixel($out, $px, $py, ($gdAlpha << 24) | ($ir << 16) | ($ig << 8) | $ib);
                    } else {
                        $exAlpha = 1.0 - ($exA / 127.0);
                        $outAlpha = $alpha + $exAlpha * (1.0 - $alpha);
                        $exR = ($existing >> 16) & 0xFF;
                        $exG = ($existing >> 8) & 0xFF;
                        $exB = $existing & 0xFF;
                        $fR = (int) round(($ir * $alpha + $exR * $exAlpha * (1.0 - $alpha)) / max(0.001, $outAlpha));
                        $fG = (int) round(($ig * $alpha + $exG * $exAlpha * (1.0 - $alpha)) / max(0.001, $outAlpha));
                        $fB = (int) round(($ib * $alpha + $exB * $exAlpha * (1.0 - $alpha)) / max(0.001, $outAlpha));
                        $gdAlpha = (int) round(127.0 * (1.0 - min(1.0, $outAlpha)));
                        imagesetpixel($out, $px, $py, ($gdAlpha << 24) | ($fR << 16) | ($fG << 8) | $fB);
                    }
                }
            }
        }

        imagedestroy($maskHeartSS);
        imagepng($out, $targetPath, 9);
        imagedestroy($out);
    }

    /**
     * Erzeugt die Studio-/Lifestyle-/Rückseiten-Galeriebilder sowie alle 8 saisonalen Vorlagenbilder
     * für Das Goldene Seelen-Herz auf Basis des realen 14k Gold-Herz-Overlays.
     */
    private function ensureGoldenesHerzGalleryAndTemplates(string $publicProductsPath, string $goldHerzDir, bool $force = false): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $overlayPath = $goldHerzDir . '/overlay.png';
        if (!file_exists($overlayPath)) {
            return;
        }

        $fontSansBold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        $fontSans = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        $fontSerifBold = '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf';
        $hasTtf = file_exists($fontSerifBold);

        $renderScene = function (
            string $outFile,
            array $bgRgbTop,
            array $bgRgbBot,
            array $engravingLines,
            string $badgeTopText,
            string $subCaption
        ) use ($overlayPath, $hasTtf, $fontSansBold, $fontSans, $fontSerifBold, $force): void {
            if (file_exists($outFile) && !$force) {
                return;
            }

            $im = imagecreatetruecolor(1024, 1024);
            imagealphablending($im, true);

            // Edler Studio-Verlaufshintergrund
            for ($y = 0; $y < 1024; $y++) {
                $t = $y / 1023.0;
                $r = (int) round($bgRgbTop[0] * (1 - $t) + $bgRgbBot[0] * $t);
                $g = (int) round($bgRgbTop[1] * (1 - $t) + $bgRgbBot[1] * $t);
                $b = (int) round($bgRgbTop[2] * (1 - $t) + $bgRgbBot[2] * $t);
                $lineCol = imagecolorallocate($im, $r, $g, $b);
                imageline($im, 0, $y, 1023, $y, $lineCol);
            }

            // Sanfter Gold-Lichtschein hinter dem Anhänger
            for ($rad = 340; $rad >= 20; $rad -= 10) {
                $alpha = (int) min(126, max(108, round(126 - (340 - $rad) * 0.045)));
                $glowCol = imagecolorallocatealpha($im, 218, 175, 82, $alpha);
                imagefilledellipse($im, 512, 490, $rad * 2, $rad * 2, $glowCol);
            }

            // Dezente Goldkette oben links & rechts durch die Collierschlaufe
            $chainCol = imagecolorallocatealpha($im, 226, 188, 92, 22);
            imagesetthickness($im, 5);
            imageline($im, 140, 40, 512, 272, $chainCol);
            imageline($im, 884, 40, 512, 272, $chainCol);
            imagesetthickness($im, 1);

            // Original 14k Gold-Herz-Overlay (680x680) mittig einbetten
            $ov = @imagecreatefrompng($overlayPath);
            if ($ov) {
                imagecopyresampled($im, $ov, 172, 150, 0, 0, 680, 680, imagesx($ov), imagesy($ov));
                imagedestroy($ov);
            }

            // Kontrastreiche, tief-anthrazitfarbene Faserlaser-Anlassgravur auf dem Gold-Herzen
            $laserCol = imagecolorallocatealpha($im, 24, 20, 15, 10);
            $goldCol = imagecolorallocate($im, 212, 172, 84);
            $whiteCol = imagecolorallocate($im, 248, 244, 236);
            $mutedCol = imagecolorallocate($im, 185, 174, 156);

            $drawCenter = static function ($im, float $pt, int $cx, int $y, int $col, string $font, string $txt) use ($hasTtf): void {
                if ($hasTtf && file_exists($font)) {
                    $bb = imagettfbbox($pt, 0, $font, $txt);
                    $tw = $bb[2] - $bb[0];
                    $tx = (int) round($cx - ($tw / 2) - $bb[0]);
                    imagettftext($im, $pt, 0, $tx, $y, $col, $font, $txt);
                }
            };

            $startY = 545 - (int) ((count($engravingLines) - 1) * 22);
            foreach ($engravingLines as $idx => $line) {
                $pt = $idx === 0 ? 22.0 : 16.5;
                $font = $idx === 0 ? $fontSerifBold : $fontSansBold;
                $drawCenter($im, $pt, 512, $startY + $idx * 44, $laserCol, $font, $line);
            }

            // Dezentes Manufaktur-Header-Badge & Untertitel
            $drawCenter($im, 11.5, 512, 68, $goldCol, $fontSansBold, $badgeTopText);
            $drawCenter($im, 14.0, 512, 945, $whiteCol, $fontSerifBold, 'Das Goldene Seelen-Herz • Inkl. 44–50 cm Gold-Ankerkette');
            $drawCenter($im, 11.0, 512, 972, $mutedCol, $fontSans, $subCaption);

            imagejpeg($im, $outFile, 92);
            imagedestroy($im);
        };

        // 1. Studio-Galeriebilder (Vorderseite, Rückseite, Lifestyle)
        $renderScene(
            $goldHerzDir . '/goldenes-seelen-herz-main.jpg',
            [22, 18, 15],
            [10, 8, 7],
            ['♥', 'Für immer', 'im Herzen'],
            'MEIN-SEELENFUNKE MANUFAKTUR • KOMPLETT-SET INKL. HALSKETTE',
            'Edelstahl-Herz (25 × 22 × 4 mm) + Gold-Ankerkette (44 + 6 cm) • Ion-Plating (IP)'
        );

        $renderScene(
            $goldHerzDir . '/goldenes-seelen-herz-back.jpg',
            [25, 21, 18],
            [12, 10, 8],
            ['24.08.2026', '52°28\'N • 10°32\'E', 'Dein Seelenmensch'],
            'BEIDSEITIGE LASER-VEREDELUNG • RÜCKSEITEN-ANSICHT',
            'Rückseite kostenlos gravierbar mit Wunschdatum, Koordinaten oder Widmung'
        );

        $renderScene(
            $goldHerzDir . '/goldenes-seelen-herz-lifestyle.jpg',
            [34, 26, 19],
            [15, 11, 8],
            ['Mama ♥', 'Du bist unser', 'Goldstück'],
            'INKLUSIVE 44–50 CM GOLD-ANKERKETTE • SOFORT TRAGFERTIG',
            'Wasserfeste 14k Ion-Plating-Veredelung auf rostfreiem Edelstahl'
        );

        // 2. Alle 8 saisonalen Feiertags-Vorlagenbilder in storage/app/public/produkte/product-templates/
        $tplDir = storage_path('app/public/produkte/product-templates');
        File::ensureDirectoryExists($tplDir);

        $holidayScenes = [
            'weihnachten'  => [[18, 26, 38], [9, 13, 20],  ['Frohe', 'Weihnachten', '♥ Familie']],
            'ostern'       => [[28, 34, 24], [14, 18, 12], ['Frohes', 'Osterfest', '✿']],
            'hochzeit'     => [[36, 26, 28], [18, 12, 14], ['Ja, ich will!', 'Für immer wir', '♥']],
            'geburtstag'   => [[34, 28, 18], [16, 13, 8],  ['Alles Liebe', 'zum Geburtstag', '★']],
            'valentinstag' => [[42, 18, 24], [20, 8, 12],  ['Ich liebe Dich', 'Mein Herz', '♥']],
            'halloween'    => [[32, 20, 14], [14, 9, 6],   ['Seelen-Zauber', 'für immer', '✦']],
            'muttertag'    => [[38, 22, 28], [18, 10, 14], ['Beste Mama', 'der Welt', '♥']],
            'vatertag'     => [[20, 26, 34], [10, 13, 18], ['Unser Held', 'Papa ♥', '★']],
        ];

        foreach ($holidayScenes as $hKey => [$topRgb, $botRgb, $lines]) {
            $renderScene(
                $tplDir . "/goldenes-seelen-herz-{$hKey}.jpg",
                $topRgb,
                $botRgb,
                $lines,
                'SAISONALE GESCHENK-EDITION • INKL. 44–50 CM HALSKETTE',
                'Vergoldeter Edelstahl-Herzanhänger (25 × 22 × 4 mm) inkl. Gold-Ankerkette'
            );
        }
    }

    /**
     * Erzeugt die Studio-/Lifestyle-/Rückseiten-Galeriebilder sowie alle 8 saisonalen Vorlagenbilder
     * für Das Echtgold Seelen-Herz (585er Echtgold-Herz 13x14 mm + 333er Echtgold-Panzerkette).
     */
    private function ensureEchtgoldHerzGalleryAndTemplates(string $publicProductsPath, string $echtgoldHerzDir, bool $force = false): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $overlayPath = $echtgoldHerzDir . '/overlay.png';
        if (!file_exists($overlayPath)) {
            return;
        }

        $fontSansBold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        $fontSans = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        $fontSerifBold = '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf';
        $hasTtf = file_exists($fontSerifBold);

        $renderScene = function (
            string $outFile,
            array $bgRgbTop,
            array $bgRgbBot,
            array $engravingLines,
            string $badgeTopText,
            string $subCaption
        ) use ($overlayPath, $hasTtf, $fontSansBold, $fontSans, $fontSerifBold, $force): void {
            if (file_exists($outFile) && !$force) {
                return;
            }

            $im = imagecreatetruecolor(1024, 1024);
            imagealphablending($im, true);

            for ($y = 0; $y < 1024; $y++) {
                $t = $y / 1023.0;
                $r = (int) round($bgRgbTop[0] * (1 - $t) + $bgRgbBot[0] * $t);
                $g = (int) round($bgRgbTop[1] * (1 - $t) + $bgRgbBot[1] * $t);
                $b = (int) round($bgRgbTop[2] * (1 - $t) + $bgRgbBot[2] * $t);
                $lineCol = imagecolorallocate($im, $r, $g, $b);
                imageline($im, 0, $y, 1023, $y, $lineCol);
            }

            for ($rad = 340; $rad >= 20; $rad -= 10) {
                $alpha = (int) min(126, max(108, round(126 - (340 - $rad) * 0.045)));
                $glowCol = imagecolorallocatealpha($im, 218, 175, 82, $alpha);
                imagefilledellipse($im, 512, 490, $rad * 2, $rad * 2, $glowCol);
            }

            // Feine 0,8 mm 333er Echtgold-Panzerkette durch die Schlaufe
            $chainCol = imagecolorallocatealpha($im, 232, 196, 104, 18);
            imagesetthickness($im, 4);
            imageline($im, 150, 40, 512, 272, $chainCol);
            imageline($im, 874, 40, 512, 272, $chainCol);
            imagesetthickness($im, 1);

            $ov = @imagecreatefrompng($overlayPath);
            if ($ov) {
                imagecopyresampled($im, $ov, 172, 150, 0, 0, 680, 680, imagesx($ov), imagesy($ov));
                imagedestroy($ov);
            }

            $laserCol = imagecolorallocatealpha($im, 28, 22, 16, 12);
            $goldCol = imagecolorallocate($im, 218, 178, 90);
            $whiteCol = imagecolorallocate($im, 250, 246, 238);
            $mutedCol = imagecolorallocate($im, 190, 178, 160);

            $drawCenter = static function ($im, float $pt, int $cx, int $y, int $col, string $font, string $txt) use ($hasTtf): void {
                if ($hasTtf && file_exists($font)) {
                    $bb = imagettfbbox($pt, 0, $font, $txt);
                    $tw = $bb[2] - $bb[0];
                    $tx = (int) round($cx - ($tw / 2) - $bbox0 = $bb[0]);
                    imagettftext($im, $pt, 0, $tx, $y, $col, $font, $txt);
                }
            };

            $startY = 545 - (int) ((count($engravingLines) - 1) * 22);
            foreach ($engravingLines as $idx => $line) {
                $pt = $idx === 0 ? 22.0 : 16.5;
                $font = $idx === 0 ? $fontSerifBold : $fontSansBold;
                $drawCenter($im, $pt, 512, $startY + $idx * 44, $laserCol, $font, $line);
            }

            $drawCenter($im, 11.5, 512, 68, $goldCol, $fontSansBold, $badgeTopText);
            $drawCenter($im, 14.0, 512, 945, $whiteCol, $fontSerifBold, 'Das Echtgold Seelen-Herz • 585er Gold & 333er Gold-Panzerkette');
            $drawCenter($im, 11.0, 512, 972, $mutedCol, $fontSans, $subCaption);

            imagejpeg($im, $outFile, 92);
            imagedestroy($im);
        };

        $renderScene(
            $echtgoldHerzDir . '/echtgold-seelen-herz-main.jpg',
            [24, 19, 14],
            [11, 8, 6],
            ['♥', 'Emilia', '14.08.2026'],
            'ATELIER ECHTGOLD • 585ER GOLD-HERZ & 333ER GOLD-PANZERKETTE',
            'Echt 585er Gelbgold (13 × 14 mm, gestempelt 585) + 333er Echtgold-Panzerkette (0,8 mm)'
        );

        $renderScene(
            $echtgoldHerzDir . '/echtgold-seelen-herz-back.jpg',
            [26, 21, 16],
            [12, 9, 7],
            ['Für immer', 'im Herzen', '♥'],
            'ATELIER ECHTGOLD • PRÄZISIONS-UV-LASERGRAVUR',
            'Feinste 5W UV-Laser-Kaltgravur in unserer Manufaktur in Gifhorn'
        );

        $renderScene(
            $echtgoldHerzDir . '/echtgold-seelen-herz-lifestyle.jpg',
            [36, 27, 18],
            [16, 11, 7],
            ['Mama ♥', 'Unser', 'Goldstück'],
            'KOMPLETT-SET INKL. 333ER ECHTGOLD-PANZERKETTE',
            'Gestempeltes 585er & 333er Echtgold • Handveredelt in Deutschland'
        );

        $tplDir = storage_path('app/public/produkte/product-templates');
        File::ensureDirectoryExists($tplDir);

        $holidayScenes = [
            'weihnachten'  => [[18, 26, 38], [9, 13, 20],  ['Frohes Fest', 'Mein Schatz', '♥']],
            'ostern'       => [[28, 34, 24], [14, 18, 12], ['Alles Liebe', 'zu Ostern', '✿']],
            'hochzeit'     => [[36, 26, 28], [18, 12, 14], ['Für immer', 'verbunden', '♥']],
            'geburtstag'   => [[34, 28, 18], [16, 13, 8],  ['Alles Liebe', 'zum Ehrentag', '★']],
            'valentinstag' => [[42, 18, 24], [20, 8, 12],  ['Ich liebe Dich', 'Mein Herz', '♥']],
            'halloween'    => [[32, 20, 14], [14, 9, 6],   ['Ewiger', 'Seelenfunke', '✦']],
            'muttertag'    => [[38, 22, 28], [18, 10, 14], ['Beste Mama', 'der Welt', '♥']],
            'vatertag'     => [[20, 26, 34], [10, 13, 18], ['Für immer', 'Familie', '★']],
        ];

        foreach ($holidayScenes as $hKey => [$topRgb, $botRgb, $lines]) {
            $renderScene(
                $tplDir . "/echtgold-seelen-herz-{$hKey}.jpg",
                $topRgb,
                $botRgb,
                $lines,
                'ATELIER ECHTGOLD • 585ER GOLD & 333ER GOLDKETTE',
                '585er Echtgold-Herzanhänger (13 × 14 mm) inkl. 333er Echtgold-Panzerkette'
            );
        }
    }

    /**
     * Erzeugt das exakt zum 2D-Overlay (800x800) deckungsgleiche 3D-Mesh für die FBM ID-Gravurarmbänder
     * (53062HERZ: Gravurplatte 24 x 5,5 mm mit ausgestanztem Herz-Cutout links, seitlichen Dreiecks-Endkappen
     * und diamantierten 2,1 mm Figaro-Kettengliedern).
     */
    private function buildIdArmbandHerz3DMesh(): array
    {
        $positions = [];
        $normals = [];
        $uvs = [];
        $indices = [];

        // 1. Abgerundete Rechteck-Gravurplatte (x = 136..664, y = 340..460, Eckenradius r = 22)
        $platePts = [];
        $corners = [
            [664.0 - 22.0, 340.0 + 22.0, -M_PI * 0.5, 0.0],
            [664.0 - 22.0, 460.0 - 22.0, 0.0, M_PI * 0.5],
            [136.0 + 22.0, 460.0 - 22.0, M_PI * 0.5, M_PI],
            [136.0 + 22.0, 340.0 + 22.0, M_PI, M_PI * 1.5],
        ];
        foreach ($corners as [$cx, $cy, $a0, $a1]) {
            $steps = 12;
            for ($i = 0; $i <= $steps; $i++) {
                $a = $a0 + ($a1 - $a0) * ($i / $steps);
                $platePts[] = [$cx + 22.0 * cos($a), $cy + 22.0 * sin($a)];
            }
        }
        $this->extrudePixelContour($platePts, null, [400.0, 400.0], 0.032, $positions, $normals, $uvs, $indices, 1.5);

        // 2. 3D-Innenwände für das ausgestanzte Herz-Cutout links (cx = 236, cy = 400) & die seitlichen Bügel-Schlitze
        $addHoleWallRing = static function (array $pts, float $zHalf = 0.032) use (&$positions, &$normals, &$uvs, &$indices): void {
            $toX = static fn(float $px): float => ($px - 400.0) / 360.0;
            $toY = static fn(float $py): float => (400.0 - $py) / 360.0;
            $toU = static fn(float $px): float => $px / 800.0;
            $toV = static fn(float $py): float => $py / 800.0;
            $m = count($pts);
            for ($i = 0; $i < $m; $i++) {
                $next = ($i + 1) % $m;
                [$x0, $y0] = $pts[$i];
                [$x1, $y1] = $pts[$next];
                $dx = $toX($x1) - $toX($x0);
                $dy = $toY($y1) - $toY($y0);
                $len = hypot($dx, $dy) ?: 1.0;
                $nx = -$dy / $len;
                $ny = $dx / $len;
                $vBase = intdiv(count($positions), 3);
                array_push(
                    $positions,
                    $toX($x0), $toY($y0), $zHalf,
                    $toX($x1), $toY($y1), $zHalf,
                    $toX($x1), $toY($y1), -$zHalf,
                    $toX($x0), $toY($y0), -$zHalf
                );
                for ($k = 0; $k < 4; $k++) {
                    array_push($normals, $nx, $ny, 0.0);
                }
                array_push($uvs, $toU(400.0), $toV(400.0), $toU(400.0), $toV(400.0), $toU(400.0), $toV(400.0), $toU(400.0), $toV(400.0));
                array_push($indices, $vBase, $vBase + 1, $vBase + 2, $vBase, $vBase + 2, $vBase + 3);
            }
        };

        $heartHolePts = [];
        $hSteps = 40;
        $rotAngle = deg2rad(-12.0);
        $cosR = cos($rotAngle);
        $sinR = sin($rotAngle);
        for ($i = 0; $i < $hSteps; $i++) {
            $t = ($i / $hSteps) * M_PI * 2.0;
            $hx = 16.0 * pow(sin($t), 3) * 2.15;
            $hy = -(13.0 * cos($t) - 5.0 * cos(2.0 * $t) - 2.0 * cos(3.0 * $t) - cos(4.0 * $t)) * 2.05;
            $rx = $hx * $cosR - $hy * $sinR;
            $ry = $hx * $sinR + $hy * $cosR;
            $heartHolePts[] = [236.0 + $rx, 402.0 + $ry];
        }
        $addHoleWallRing($heartHolePts, 0.032);

        // 3. Dreieckige Endkappen links (x = 82..156) & rechts (x = 644..718)
        $leftCapPts = [
            [156.0, 360.0], [156.0, 440.0], [82.0, 412.0], [82.0, 388.0],
        ];
        $rightCapPts = [
            [644.0, 360.0], [718.0, 388.0], [718.0, 412.0], [644.0, 440.0],
        ];
        $this->extrudePixelContour($leftCapPts, null, [120.0, 400.0], 0.028, $positions, $normals, $uvs, $indices, 1.0);
        $this->extrudePixelContour($rightCapPts, null, [680.0, 400.0], 0.028, $positions, $normals, $uvs, $indices, 1.0);

        // 4. Figaro-Kettenglieder links (x = 12..88) & rechts (x = 712..788)
        $makeLinkRing = function (float $cx, float $cy, float $rxOut, float $ryOut, float $thick) use (&$positions, &$normals, &$uvs, &$indices): void {
            $steps = 32;
            $outer = [];
            $inner = [];
            for ($i = 0; $i < $steps; $i++) {
                $a = ($i / $steps) * M_PI * 2.0;
                $outer[] = [$cx + $rxOut * cos($a), $cy + $ryOut * sin($a)];
                $inner[] = [$cx + max(3.0, $rxOut - $thick) * cos($a), $cy + max(3.0, $ryOut - $thick) * sin($a)];
            }
            $this->extrudePixelContour($outer, $inner, [$cx, $cy], 0.024, $positions, $normals, $uvs, $indices, 1.0);
        };

        $makeLinkRing(64.0, 400.0, 24.0, 17.0, 8.0);
        $makeLinkRing(30.0, 400.0, 18.0, 16.0, 7.5);
        $makeLinkRing(736.0, 400.0, 24.0, 17.0, 8.0);
        $makeLinkRing(770.0, 400.0, 18.0, 16.0, 7.5);

        return compact('positions', 'normals', 'uvs', 'indices');
    }

    /**
     * Prüft, ob ein Punkt (x, y) im ausgestanzten Herz-Durchbruch (cx=236, cy=402, Rotation -12°) liegt.
     */
    private function isInsideArmbandHeartCutout(float $x, float $y): array
    {
        $dx = $x - 236.0;
        $dy = $y - 402.0;
        $rotAngle = deg2rad(12.0);
        $lx = ($dx * cos($rotAngle) - $dy * sin($rotAngle)) / 32.0;
        $ly = -($dx * sin($rotAngle) + $dy * cos($rotAngle)) / 31.0 + 0.12;
        // Klassische implizite Herz-Gleichung: (x^2 + y^2 - 1)^3 - x^2 * y^3 <= 0
        $a = $lx * $lx + $ly * $ly - 1.0;
        $val = $a * $a * $a - $lx * $lx * $ly * $ly * $ly;
        return [$val <= 0.0, $val];
    }

    /**
     * Rendert das gestochen scharfe 800x800 2D-Overlay (overlay.png) für die FBM ID-Gravurarmbänder
     * (53062HERZ: 925er Sterlingsilber bzw. 925er Silber Gelb vergoldet, Figaro diamantiert 2,1 mm).
     */
    private function renderIdArmbandHerzOverlay(string $targetPath, string $metal = 'silver'): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $isGold = ($metal === 'gold');
        $out = imagecreatetruecolor(800, 800);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $trans = imagecolorallocatealpha($out, $isGold ? 218 : 225, $isGold ? 182 : 230, $isGold ? 98 : 238, 127);
        imagefill($out, 0, 0, $trans);

        // 16x Subpixel-Supersampling (4x4 SSAA) über den gesamten Armband-Streifen (y = 330..470, x = 8..792)
        for ($py = 332; $py <= 468; $py++) {
            for ($px = 8; $px <= 792; $px++) {
                $rAcc = 0.0;
                $gAcc = 0.0;
                $bAcc = 0.0;
                $cov = 0;

                for ($sy = 0; $sy < 4; $sy++) {
                    $y = $py + ($sy + 0.5) * 0.25;
                    for ($sx = 0; $sx < 4; $sx++) {
                        $x = $px + ($sx + 0.5) * 0.25;

                        $hit = false;
                        $pr = 0.0;
                        $pg = 0.0;
                        $pb = 0.0;

                        // A. Prüfe Haupt-Gravurplatte (x = 136..664, y = 340..460, Eckenradius r = 22)
                        if ($x >= 136.0 && $x <= 664.0 && $y >= 340.0 && $y <= 460.0) {
                            $cx = max(136.0 + 22.0, min(664.0 - 22.0, $x));
                            $cy = max(340.0 + 22.0, min(460.0 - 22.0, $y));
                            $cornerDist = hypot($x - $cx, $y - $cy);
                            if ($cornerDist <= 22.0) {
                                // Prüfe ausgestanztes Herz-Cutout links (cx=236, cy=402)
                                [$inHeart, $heartVal] = $this->isInsideArmbandHeartCutout($x, $y);
                                // Prüfe seitliche Bügelschlitze (links x=149..162, rechts x=638..651, y=362..438)
                                $inLeftSlot = ($x >= 149.0 && $x <= 162.0 && $y >= 362.0 && $y <= 438.0 && !($y >= 386.0 && $y <= 414.0 && $x <= 156.0));
                                $inRightSlot = ($x >= 638.0 && $x <= 651.0 && $y >= 362.0 && $y <= 438.0 && !($y >= 386.0 && $y <= 414.0 && $x >= 644.0));

                                if (!$inHeart && !$inLeftSlot && !$inRightSlot) {
                                    $hit = true;
                                    $distTop = $y - 340.0;
                                    $distBot = 460.0 - $y;
                                    $distLeft = $x - 136.0;
                                    $distRight = 664.0 - $x;
                                    $edgeDist = min($distTop, $distBot, $distLeft, $distRight, 22.0 - $cornerDist);

                                    // Spiegelpolierter Metallverlauf über die Gravurplatte
                                    $uX = ($x - 400.0) / 264.0;
                                    $uY = ($y - 400.0) / 60.0;
                                    $sheen = exp(-pow(($uX * 0.45 + $uY * 0.35 + 0.12) / 0.55, 2));

                                    if ($isGold) {
                                        $pr = 244.0 + 11.0 * $sheen - 4.0 * $uY;
                                        $pg = 213.0 + 24.0 * $sheen - 7.0 * $uY;
                                        $pb = 132.0 + 38.0 * $sheen - 10.0 * $uY;
                                    } else {
                                        $pr = 238.0 + 15.0 * $sheen - 4.0 * $uY;
                                        $pg = 241.0 + 13.0 * $sheen - 4.0 * $uY;
                                        $pb = 246.0 + 9.0 * $sheen - 3.0 * $uY;
                                    }

                                    // Edle 3D-Außenkantenfase (Oberkante hell, Unterkante dunkler Spiegelkontrast)
                                    if ($edgeDist < 4.5) {
                                        $bevel = 1.0 - ($edgeDist / 4.5);
                                        if ($y < 350.0) {
                                            $pr += ($isGold ? 10.0 : 12.0) * $bevel;
                                            $pg += ($isGold ? 16.0 : 12.0) * $bevel;
                                            $pb += ($isGold ? 24.0 : 10.0) * $bevel;
                                        } else {
                                            $pr -= ($isGold ? 68.0 : 62.0) * $bevel;
                                            $pg -= ($isGold ? 72.0 : 60.0) * $bevel;
                                            $pb -= ($isGold ? 64.0 : 56.0) * $bevel;
                                        }
                                    }

                                    // Polierte 3D-Innenfase rund um das ausgestanzte Herz-Cutout
                                    if ($heartVal > 0.0 && $heartVal < 0.22) {
                                        $hBevel = 1.0 - ($heartVal / 0.22);
                                        $pr -= ($isGold ? 64.0 : 58.0) * $hBevel;
                                        $pg -= ($isGold ? 68.0 : 56.0) * $hBevel;
                                        $pb -= ($isGold ? 58.0 : 52.0) * $hBevel;
                                    }
                                }
                            }
                        }

                        // B. Dreieckige Endkappen / Bügel links (x=82..156) & rechts (x=644..718)
                        if (!$hit) {
                            $inCap = false;
                            $capT = 0.0;
                            $capHalfH = 14.0;
                            if ($x >= 82.0 && $x <= 156.0) {
                                $capT = ($x - 82.0) / (156.0 - 82.0);
                                $capHalfH = 12.0 + 18.0 * $capT;
                                if (abs($y - 400.0) <= $capHalfH && !($x >= 148.0 && abs($y - 400.0) > 14.0)) {
                                    $inCap = true;
                                }
                            } elseif ($x >= 644.0 && $x <= 718.0) {
                                $capT = (718.0 - $x) / (718.0 - 644.0);
                                $capHalfH = 12.0 + 18.0 * $capT;
                                if (abs($y - 400.0) <= $capHalfH && !($x <= 652.0 && abs($y - 400.0) > 14.0)) {
                                    $inCap = true;
                                }
                            }

                            if ($inCap) {
                                $hit = true;
                                $vU = ($y - 400.0) / max(1.0, $capHalfH);
                                $cyl = cos(($vU + 0.22) * 1.45);
                                if ($isGold) {
                                    $pr = 212.0 + 42.0 * $cyl;
                                    $pg = 176.0 + 48.0 * $cyl;
                                    $pb = 96.0 + 52.0 * $cyl;
                                } else {
                                    $pr = 205.0 + 46.0 * $cyl;
                                    $pg = 210.0 + 44.0 * $cyl;
                                    $pb = 218.0 + 37.0 * $cyl;
                                }
                            }
                        }

                        // C. Diamantierte 2,1 mm Figaro-Kettenglieder links (x=10..88) & rechts (x=712..790)
                        if (!$hit) {
                            $links = [
                                [64.0, 400.0, 23.0, 16.0, 7.2],
                                [30.0, 400.0, 17.5, 15.0, 6.8],
                                [736.0, 400.0, 23.0, 16.0, 7.2],
                                [770.0, 400.0, 17.5, 15.0, 6.8],
                            ];
                            foreach ($links as [$lcx, $lcy, $rx, $ry, $th]) {
                                $nx = ($x - $lcx) / $rx;
                                $ny = ($y - $lcy) / $ry;
                                $rNorm = hypot($nx, $ny);
                                $innerRatio = ($rx - $th) / $rx;
                                if ($rNorm <= 1.0 && $rNorm >= $innerRatio) {
                                    $hit = true;
                                    $tubePos = ($rNorm - ($innerRatio + 1.0) * 0.5) / ((1.0 - $innerRatio) * 0.5);
                                    $nz = sqrt(max(0.0, 1.0 - $tubePos * $tubePos));
                                    // Diamantierte Facetten-Glanzlichter
                                    $facet = 0.55 + 0.45 * cos(($x - $lcx) * 0.28 - ($y - $lcy) * 0.22);
                                    if ($isGold) {
                                        $pr = 185.0 + 68.0 * $nz * $facet;
                                        $pg = 148.0 + 74.0 * $nz * $facet;
                                        $pb = 74.0 + 68.0 * $nz * $facet;
                                    } else {
                                        $pr = 182.0 + 70.0 * $nz * $facet;
                                        $pg = 188.0 + 66.0 * $nz * $facet;
                                        $pb = 198.0 + 57.0 * $nz * $facet;
                                    }
                                    break;
                                }
                            }
                        }

                        if ($hit) {
                            $rAcc += max(45.0, min(255.0, $pr));
                            $gAcc += max(35.0, min(255.0, $pg));
                            $bAcc += max(20.0, min(255.0, $pb));
                            $cov++;
                        }
                    }
                }

                if ($cov > 0) {
                    $r = (int) round($rAcc / $cov);
                    $g = (int) round($gAcc / $cov);
                    $b = (int) round($bAcc / $cov);
                    $gdAlpha = (int) round(127.0 * (1.0 - ($cov / 16.0)));
                    imagesetpixel($out, $px, $py, ($gdAlpha << 24) | ($r << 16) | ($g << 8) | $b);
                }
            }
        }

        imagepng($out, $targetPath, 9);
        imagedestroy($out);
    }

    /**
     * Erzeugt die Studio-/Lifestyle-/Rückseiten-Galeriebilder (100% Product-Only, ohne Geschenkboxen!)
     * sowie alle 8 saisonalen Feiertags-Vorlagenbilder für die beiden FBM ID-Gravurarmbänder.
     */
    private function ensureIdArmbandHerzGalleryAndTemplates(
        string $publicProductsPath,
        string $prodDir,
        string $slug,
        string $metal = 'silver',
        bool $force = false
    ): void {
        if (!extension_loaded('gd')) {
            return;
        }

        $overlayPath = $prodDir . '/overlay.png';
        if (!file_exists($overlayPath)) {
            return;
        }

        $isGold = ($metal === 'gold');
        $fontSansBold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        $fontSans = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        $fontSerifBold = '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf';
        $hasTtf = file_exists($fontSerifBold);

        $titleFooter = $isGold
            ? 'Das Gold-Vermeil Seelen-Armband • 925er Silber Gelb vergoldet (18,5 cm)'
            : 'Das Echtsilber Seelen-Armband • Massives 925er Sterlingsilber (18,5 cm)';

        $renderScene = function (
            string $outFile,
            array $bgRgbTop,
            array $bgRgbBot,
            array $engravingLines,
            string $badgeTopText,
            string $subCaption
        ) use ($overlayPath, $isGold, $titleFooter, $hasTtf, $fontSansBold, $fontSans, $fontSerifBold, $force): void {
            if (file_exists($outFile) && !$force) {
                return;
            }

            $im = imagecreatetruecolor(1024, 1024);
            imagealphablending($im, true);

            for ($y = 0; $y < 1024; $y++) {
                $t = $y / 1023.0;
                // Sanfter Studio-Vignetten-Verlauf ohne konzentrisches Banding
                $centerBoost = max(0.0, 1.0 - abs(($y - 512.0) / 420.0));
                $boost = $centerBoost * $centerBoost * 14.0;
                $r = (int) min(255, round($bgRgbTop[0] * (1 - $t) + $bgRgbBot[0] * $t + ($isGold ? $boost * 1.15 : $boost * 0.90)));
                $g = (int) min(255, round($bgRgbTop[1] * (1 - $t) + $bgRgbBot[1] * $t + ($isGold ? $boost * 0.90 : $boost * 1.00)));
                $b = (int) min(255, round($bgRgbTop[2] * (1 - $t) + $bgRgbBot[2] * $t + ($isGold ? $boost * 0.55 : $boost * 1.15)));
                $lineCol = imagecolorallocate($im, $r, $g, $b);
                imageline($im, 0, $y, 1023, $y, $lineCol);
            }

            // Originalgetreues Armband-Overlay (860x860) mittig einbetten
            $ov = @imagecreatefrompng($overlayPath);
            if ($ov) {
                imagecopyresampled($im, $ov, 82, 82, 0, 0, 860, 860, imagesx($ov), imagesy($ov));
                imagedestroy($ov);
            }

            // Kontrastreiche, tief-anthrazitfarbene Lasergravur rechts neben dem Herz-Durchbruch (cx = 576, cy = 512)
            $laserCol = imagecolorallocatealpha($im, 24, 22, 20, 10);
            $accentCol = $isGold
                ? imagecolorallocate($im, 218, 178, 90)
                : imagecolorallocate($im, 212, 220, 232);
            $whiteCol = imagecolorallocate($im, 250, 246, 238);
            $mutedCol = imagecolorallocate($im, 185, 176, 162);

            $drawCenter = static function ($im, float $pt, int $cx, int $y, int $col, string $font, string $txt) use ($hasTtf): void {
                if ($hasTtf && file_exists($font)) {
                    $bb = imagettfbbox($pt, 0, $font, $txt);
                    $tw = $bb[2] - $bb[0];
                    $tx = (int) round($cx - ($tw / 2) - $bb[0]);
                    imagettftext($im, $pt, 0, $tx, $y, $col, $font, $txt);
                }
            };

            $lineCount = count($engravingLines);
            $startY = $lineCount === 1 ? 520 : 500;
            foreach ($engravingLines as $idx => $line) {
                $pt = $idx === 0 ? 19.5 : 13.5;
                $font = $idx === 0 ? $fontSerifBold : $fontSansBold;
                $drawCenter($im, $pt, 578, $startY + $idx * 28, $laserCol, $font, $line);
            }

            $drawCenter($im, 11.5, 512, 68, $accentCol, $fontSansBold, $badgeTopText);
            $drawCenter($im, 14.0, 512, 945, $whiteCol, $fontSerifBold, $titleFooter);
            $drawCenter($im, 11.0, 512, 972, $mutedCol, $fontSans, $subCaption);

            imagejpeg($im, $outFile, 92);
            imagedestroy($im);
        };

        $materialSubtitle = $isGold
            ? '925er Sterlingsilber Gelb vergoldet (2,50 g) • Figaro diamantiert 2,1 mm • Herz-Cutout'
            : 'Massives 925er Sterlingsilber (2,50 g) • Figaro diamantiert 2,1 mm • Herz-Cutout';

        $renderScene(
            $prodDir . "/{$slug}-main.jpg",
            $isGold ? [24, 19, 14] : [18, 22, 28],
            $isGold ? [11, 8, 6]   : [9, 11, 15],
            ['Emilia ♥', '14.08.2026'],
            $isGold
                ? 'ATELIER EDELMETALL • 925ER SILBER GELB VERGOLDET (MADE IN GERMANY)'
                : 'ATELIER EDELMETALL • MASSIVES 925ER STERLINGSILBER (MADE IN GERMANY)',
            $materialSubtitle
        );

        $renderScene(
            $prodDir . "/{$slug}-back.jpg",
            $isGold ? [26, 21, 16] : [20, 24, 30],
            $isGold ? [12, 9, 7]   : [10, 12, 16],
            ['Für immer', 'im Herzen ♥'],
            'BEIDSEITIGE 5W UV-PRÄZISIONS-LASERGRAVUR • RÜCKSEITEN-ANSICHT',
            'Vorder- und Rückseite der Gravurplatte individuell personalisierbar'
        );

        $renderScene(
            $prodDir . "/{$slug}-lifestyle.jpg",
            $isGold ? [34, 26, 18] : [24, 28, 36],
            $isGold ? [15, 11, 7]  : [11, 13, 18],
            ['Lieblingsmensch', 'Für immer wir'],
            'DEUTSCHE TRADITIONSMANUFAKTUR FBM (SEIT 1910) • HANDVEREDELT IN GIFHORN',
            'Echter 925er Feingehaltsstempel • Federringverschluss • 18,5 cm Länge'
        );

        $tplDir = storage_path('app/public/produkte/product-templates');
        File::ensureDirectoryExists($tplDir);

        $holidayScenes = [
            'weihnachten'  => [[18, 26, 38], [9, 13, 20],  ['Frohes Fest ♥', 'Mein Schatz']],
            'ostern'       => [[28, 34, 24], [14, 18, 12], ['Frohes Ostern', 'Alles Liebe ✿']],
            'hochzeit'     => [[36, 26, 28], [18, 12, 14], ['Lieblingsmensch', '24.08.2026']],
            'geburtstag'   => [[34, 28, 18], [16, 13, 8],  ['Happy Birthday', 'Mein Stern ★']],
            'valentinstag' => [[42, 18, 24], [20, 8, 12],  ['Für immer Dein', '14. Februar ♥']],
            'halloween'    => [[32, 20, 14], [14, 9, 6],   ['Magische Nacht', 'Seelenzauber ✦']],
            'muttertag'    => [[38, 22, 28], [18, 10, 14], ['Beste Mama ♥', 'der Welt']],
            'vatertag'     => [[20, 26, 34], [10, 13, 18], ['Unser Held', 'Für immer ★']],
        ];

        foreach ($holidayScenes as $hKey => [$topRgb, $botRgb, $lines]) {
            $renderScene(
                $tplDir . "/{$slug}-{$hKey}.jpg",
                $topRgb,
                $botRgb,
                $lines,
                'ATELIER GESCHENK-EDITION • 925ER EDELMETALL MIT HERZ-CUTOUT',
                $materialSubtitle
            );
        }
    }

    /**
     * Stellt sicher, dass für alle 5 physischen Produkte die technischen Maßblätter (*-dimensions.jpg) existieren.
     * Fehlende Maßblätter werden direkt nativ über PHP-GD + DejaVu-Fonts gerendert.
     */
    private function generateAllDimensionSheets(string $publicProductsPath, bool $force = false): void
    {
        $sheets = [
            [
                'slug' => 'seelen-kristall',
                'outFileName' => 'seelen-kristall-dimensions.jpg',
                'title' => 'Der Seelen Kristall: Maße & Details',
                'subtitle' => 'Optisches K9-Kristallglas mit geschliffener Facettenkante & Tiefen-Lasergravur',
                'overlayTop' => 185,
                'horizArrows' => [
                    ['x1' => 287, 'x2' => 737, 'y' => 745, 'dashedYFrom1' => 440, 'dashedYFrom2' => 440, 'labelPos' => 'below', 'label' => 'Breite: 180 mm (18,0 cm)'],
                ],
                'vertArrows' => [
                    ['y1' => 218, 'y2' => 703, 'x' => 868, 'dashedXFrom1' => 512, 'dashedXFrom2' => 677, 'label' => 'Höhe: 200 mm'],
                ],
                'callouts' => [
                    ['x' => 512, 'y' => 315, 'tx' => 210, 'ty' => 220, 'label' => 'Gravurfläche: Ø 150 mm'],
                ],
                'specs' => [
                    ['label' => 'Abmessungen', 'value' => '180 × 200 × 40 mm', 'sub' => 'Sonnenschliff-Kristall & Sockel'],
                    ['label' => 'Sockeltiefe', 'value' => '40 mm (Massiv)', 'sub' => 'Inkl. massivem Kristallsockel'],
                    ['label' => 'Gewicht', 'value' => 'ca. 920 g', 'sub' => 'Schwere Manufaktur-Qualität'],
                    ['label' => 'Material', 'value' => 'K9 Kristallglas', 'sub' => 'Hochreiner Facettenschliff'],
                ],
            ],
            [
                'slug' => 'seelen-anhaenger',
                'outFileName' => 'seelen-anhaenger-dimensions.jpg',
                'title' => 'Der Seelenanhänger: Maße & Details',
                'subtitle' => 'Herz-Schlüsselanhänger aus eloxiertem Aluminium mit Hochglanz-Metallrahmen',
                'overlayTop' => 185,
                'horizArrows' => [
                    ['x1' => 378, 'x2' => 646, 'y' => 745, 'dashedYFrom1' => 556, 'dashedYFrom2' => 556, 'labelPos' => 'below', 'label' => 'Herz-Breite: 45 mm (4,5 cm)'],
                ],
                'vertArrows' => [
                    ['y1' => 479, 'y2' => 725, 'x' => 215, 'dashedXFrom1' => 445, 'dashedXFrom2' => 512, 'label' => 'Herz-Höhe: 40 mm'],
                    ['y1' => 207, 'y2' => 725, 'x' => 815, 'dashedXFrom1' => 517, 'dashedXFrom2' => 512, 'label' => 'Gesamtlänge: 90 mm'],
                ],
                'callouts' => [
                    ['x' => 512, 'y' => 602, 'tx' => 225, 'ty' => 360, 'label' => 'Gravurfeld: 38 × 35 mm'],
                ],
                'specs' => [
                    ['label' => 'Abmessungen', 'value' => '45 × 40 mm', 'sub' => 'inkl. Drehwirbel & Spaltring'],
                    ['label' => 'Materialstärke', 'value' => '2,3 mm', 'sub' => 'Formstabil & handschmeichelnd'],
                    ['label' => 'Gewicht', 'value' => 'ca. 19 g', 'sub' => 'Angenehm am Schlüsselbund'],
                    ['label' => 'Gravur', 'value' => 'Beidseitig', 'sub' => 'Vorder- & Rückseite inklusive'],
                ],
            ],
            [
                'slug' => 'weizenglas-personalisiert',
                'outFileName' => 'weizenglas-dimensions.jpg',
                'title' => 'Der Feierabend-Funke – Maße & Details',
                'subtitle' => 'Bayerisches 0,5L Kristall-Weizenglas mit umlaufender 360°-Lasergravur',
                'overlayTop' => 215,
                'horizArrows' => [
                    ['x1' => 410, 'x2' => 614, 'y' => 205, 'dashedYFrom1' => 235, 'dashedYFrom2' => 235, 'labelPos' => 'above', 'label' => 'Kelch-Ø oben: 74 mm (7,4 cm)'],
                ],
                'vertArrows' => [
                    ['y1' => 228, 'y2' => 756, 'x' => 815, 'dashedXFrom1' => 614, 'dashedXFrom2' => 580, 'label' => 'Gesamthöhe: 218 mm (21,8 cm)'],
                ],
                'callouts' => [
                    ['x' => 512, 'y' => 485, 'tx' => 220, 'ty' => 485, 'label' => '360° Gravurzone: 148 mm'],
                ],
                'specs' => [
                    ['label' => 'Abmessungen', 'value' => '218 × Ø 74 mm', 'sub' => 'Klassische Weizen-Kontur'],
                    ['label' => 'Füllvolumen', 'value' => '0,5 Liter', 'sub' => 'Randvoll 580 ml'],
                    ['label' => 'Gewicht', 'value' => 'ca. 450 g', 'sub' => 'Starker Manufaktur-Eisboden'],
                    ['label' => 'Veredelung', 'value' => 'Spülmaschinenfest', 'sub' => 'Dauerhafte 360°-Lasergravur'],
                ],
            ],
            [
                'slug' => 'seelen-talisman',
                'outFileName' => 'seelen-talisman-dimensions.jpg',
                'title' => 'Der Seelen-Talisman – Maße & Details',
                'subtitle' => 'Massiver, spiegelpolierter Edelstahl-Schlüsselanhänger in edlem Gold-Finish',
                'overlayTop' => 185,
                'horizArrows' => [
                    ['x1' => 411, 'x2' => 613, 'y' => 202, 'dashedYFrom1' => 314, 'dashedYFrom2' => 314, 'labelPos' => 'above', 'label' => 'Schlüsselring: Ø 30 mm'],
                    ['x1' => 422, 'x2' => 602, 'y' => 748, 'dashedYFrom1' => 678, 'dashedYFrom2' => 678, 'labelPos' => 'below', 'label' => 'Plaketten-Breite: 28 mm (2,8 cm)'],
                ],
                'vertArrows' => [
                    ['y1' => 410, 'y2' => 717, 'x' => 235, 'dashedXFrom1' => 512, 'dashedXFrom2' => 512, 'label' => 'Plaketten-Höhe: 50 mm (5,0 cm)'],
                    ['y1' => 213, 'y2' => 717, 'x' => 790, 'dashedXFrom1' => 512, 'dashedXFrom2' => 512, 'label' => 'Gesamtlänge: 78 mm (7,8 cm)'],
                ],
                'callouts' => [],
                'specs' => [
                    ['label' => 'Plaketten-Maß', 'value' => '50 × 28 mm', 'sub' => '5,0 × 2,8 cm (1,97 × 1,10 in)'],
                    ['label' => 'Materialstärke', 'value' => '2,2 mm (Massiv)', 'sub' => 'Rostfreier Edelstahl'],
                    ['label' => 'Gewicht', 'value' => 'ca. 24 g', 'sub' => 'Spürbar wertige Haptik'],
                    ['label' => 'Veredelung', 'value' => 'Beidseitig', 'sub' => 'Dunkle Faserlaser-Anlassgravur'],
                ],
            ],
            [
                'slug' => 'seelen-pfote-hundemarke',
                'outFileName' => 'seelen-pfote-dimensions.jpg',
                'title' => 'Die Seelen-Pfote: Maße & Details',
                'subtitle' => 'Hochglanzpolierte Edelstahl-Hundemarke in edlem Gold-Finish mit ausgestanztem Pfotenabdruck',
                'overlayTop' => 185,
                'horizArrows' => [
                    ['x1' => 288, 'x2' => 736, 'y' => 265, 'dashedYFrom1' => 385, 'dashedYFrom2' => 385, 'labelPos' => 'above', 'label' => 'Breite: 40 mm (1,57 in)'],
                ],
                'vertArrows' => [
                    ['y1' => 352, 'y2' => 578, 'x' => 165, 'dashedXFrom1' => 359, 'dashedXFrom2' => 359, 'label' => 'Höhe: 22 mm (0,87 in)'],
                ],
                'callouts' => [
                    ['x' => 425, 'y' => 465, 'tx' => 330, 'ty' => 690, 'label' => 'Beidseitige Laser-Gravurzone'],
                    ['x' => 676, 'y' => 520, 'tx' => 720, 'ty' => 690, 'label' => 'Echter Pfoten-Durchbruch'],
                ],
                'specs' => [
                    ['label' => 'Knochen-Maß', 'value' => '40 × 22 mm', 'sub' => '1,57 × 0,87 in (inkl. Öse)'],
                    ['label' => 'Material & Finish', 'value' => '1,8 mm Gold', 'sub' => 'Edelstahl (Gold-Finish)'],
                    ['label' => 'Gewicht', 'value' => 'ca. 12 g (0,43 oz)', 'sub' => 'Rostfrei, wasser- & kratzfest'],
                    ['label' => 'Gravur', 'value' => 'Beidseitig', 'sub' => 'Name vorn + Notfall-Nr. hinten'],
                ],
            ],
            [
                'slug' => 'goldenes-seelen-herz',
                'outFileName' => 'goldenes-seelen-herz-dimensions.jpg',
                'title' => 'Das Goldene Seelen-Herz – Maße & Details',
                'subtitle' => 'Massiver vergoldeter Edelstahl-Herzanhänger (Ion-Plating) inkl. 44–50 cm Gold-Ankerkette',
                'overlayTop' => 185,
                'horizArrows' => [
                    ['x1' => 481, 'x2' => 543, 'y' => 202, 'dashedYFrom1' => 225, 'dashedYFrom2' => 225, 'labelPos' => 'above', 'label' => 'Collierschlaufe: Öse 3 × 6 mm'],
                    ['x1' => 330, 'x2' => 694, 'y' => 742, 'dashedYFrom1' => 490, 'dashedYFrom2' => 490, 'labelPos' => 'below', 'label' => 'Herz-Breite: 22 mm (2,2 cm)'],
                ],
                'vertArrows' => [
                    ['y1' => 382, 'y2' => 713, 'x' => 210, 'dashedXFrom1' => 445, 'dashedXFrom2' => 512, 'label' => 'Herz-Höhe: 25 mm (2,5 cm)'],
                    ['y1' => 217, 'y2' => 713, 'x' => 814, 'dashedXFrom1' => 543, 'dashedXFrom2' => 512, 'label' => 'Materialstärke: 4,0 mm (Massiv)'],
                ],
                'callouts' => [],
                'specs' => [
                    ['label' => 'Herz-Maß', 'value' => '25 × 22 × 4 mm', 'sub' => 'Massives 4,0 mm Vollmaterial'],
                    ['label' => 'Halskette (Inkl.)', 'value' => '44 + 6 cm (1,5 mm)', 'sub' => 'Gold-Ankerkette (Karabiner)'],
                    ['label' => 'Oberfläche', 'value' => '14k Vergoldet (IP)', 'sub' => 'Wasserfest & anlaufgeschützt'],
                    ['label' => 'Herkunft & Gravur', 'value' => 'Handveredelt in DE', 'sub' => 'Beidseitige Faserlaser-Gravur'],
                ],
            ],
            [
                'slug' => 'echtgold-seelen-herz',
                'outFileName' => 'echtgold-seelen-herz-dimensions.jpg',
                'title' => 'Das Echtgold Seelen-Herz – Maße & Details',
                'subtitle' => 'Massiver 585er Echtgold-Herzanhänger (14 Karat) inkl. 333er Echtgold-Panzerkette (0,8 mm)',
                'overlayTop' => 185,
                'horizArrows' => [
                    ['x1' => 481, 'x2' => 543, 'y' => 202, 'dashedYFrom1' => 225, 'dashedYFrom2' => 225, 'labelPos' => 'above', 'label' => 'Schlaufe: 585 Echtgold (gestempelt)'],
                    ['x1' => 330, 'x2' => 694, 'y' => 742, 'dashedYFrom1' => 490, 'dashedYFrom2' => 490, 'labelPos' => 'below', 'label' => 'Herz-Breite: 13 mm (1,3 cm)'],
                ],
                'vertArrows' => [
                    ['y1' => 382, 'y2' => 713, 'x' => 210, 'dashedXFrom1' => 445, 'dashedXFrom2' => 512, 'label' => 'Herz-Höhe: 14 mm (ohne Schlaufe)'],
                    ['y1' => 217, 'y2' => 713, 'x' => 814, 'dashedXFrom1' => 543, 'dashedXFrom2' => 512, 'label' => 'Kette: 0,8 mm (333er Echtgold)'],
                ],
                'callouts' => [],
                'specs' => [
                    ['label' => 'Herz-Anhänger', 'value' => '585er Echtgold', 'sub' => '13 × 14 mm • 0,80 g (14 Karat)'],
                    ['label' => 'Halskette (Im Set)', 'value' => '333er Echtgold', 'sub' => '0,8 mm Panzerkette (Karabiner)'],
                    ['label' => 'Echtheitsstempel', 'value' => '585 & 333 Punze', 'sub' => 'Geprüftes Edelmetall'],
                    ['label' => 'Herkunft & Gravur', 'value' => 'Handveredelt in DE', 'sub' => 'Präzisions-UV-Lasergravur'],
                ],
            ],
            [
                'slug' => 'id-armband-silber-herz',
                'outFileName' => 'id-armband-silber-herz-dimensions.jpg',
                'title' => 'Das Echtsilber Seelen-Armband: Maße & Details',
                'subtitle' => 'Massives 925er Sterlingsilber (FBM Made in Germany) mit Figaro-Kette (2,1 mm) & Herz-Durchbruch',
                'overlayTop' => 185,
                'horizArrows' => [
                    ['x1' => 327, 'x2' => 697, 'y' => 360, 'dashedYFrom1' => 423, 'dashedYFrom2' => 423, 'labelPos' => 'above', 'label' => 'Gravurplatte: 24,0 mm (2,4 cm)'],
                    ['x1' => 240, 'x2' => 784, 'y' => 610, 'dashedYFrom1' => 465, 'dashedYFrom2' => 465, 'labelPos' => 'below', 'label' => 'Gesamtlänge: 18,5 cm (185 mm)'],
                ],
                'vertArrows' => [
                    ['y1' => 423, 'y2' => 507, 'x' => 845, 'dashedXFrom1' => 697, 'dashedXFrom2' => 697, 'labelPos' => 'above', 'label' => 'Platten-Höhe: 5,5 mm'],
                ],
                'callouts' => [
                    ['x' => 397, 'y' => 465, 'tx' => 260, 'ty' => 715, 'label' => 'Echter Herz-Durchbruch'],
                    ['x' => 560, 'y' => 465, 'tx' => 710, 'ty' => 715, 'label' => 'Beidseitige Gravurfläche'],
                ],
                'specs' => [
                    ['label' => 'Edelmetall', 'value' => '925 Sterlingsilber', 'sub' => 'ca. 2,50 g (gestempelt 925)'],
                    ['label' => 'Armband-Länge', 'value' => '18,5 cm (185 mm)', 'sub' => 'Figaro diamantiert 2,1 mm'],
                    ['label' => 'Gravurplatte', 'value' => '24 × 5,5 × 1,2 mm', 'sub' => 'Mit Herz-Durchbruch links'],
                    ['label' => 'Herkunft & Gravur', 'value' => 'Handveredelt in DE', 'sub' => 'Rohling: FBM (Made in DE)'],
                ],
            ],
            [
                'slug' => 'id-armband-gold-herz',
                'outFileName' => 'id-armband-gold-herz-dimensions.jpg',
                'title' => 'Das Gold-Vermeil Seelen-Armband: Maße & Details',
                'subtitle' => '925er Sterlingsilber Gelb vergoldet (FBM Made in Germany) mit Figaro-Kette (2,1 mm) & Herz',
                'overlayTop' => 185,
                'horizArrows' => [
                    ['x1' => 327, 'x2' => 697, 'y' => 360, 'dashedYFrom1' => 423, 'dashedYFrom2' => 423, 'labelPos' => 'above', 'label' => 'Gravurplatte: 24,0 mm (2,4 cm)'],
                    ['x1' => 240, 'x2' => 784, 'y' => 610, 'dashedYFrom1' => 465, 'dashedYFrom2' => 465, 'labelPos' => 'below', 'label' => 'Gesamtlänge: 18,5 cm (185 mm)'],
                ],
                'vertArrows' => [
                    ['y1' => 423, 'y2' => 507, 'x' => 845, 'dashedXFrom1' => 697, 'dashedXFrom2' => 697, 'labelPos' => 'above', 'label' => 'Platten-Höhe: 5,5 mm'],
                ],
                'callouts' => [
                    ['x' => 397, 'y' => 465, 'tx' => 260, 'ty' => 715, 'label' => 'Echter Herz-Durchbruch'],
                    ['x' => 560, 'y' => 465, 'tx' => 710, 'ty' => 715, 'label' => 'Beidseitige Gravurfläche'],
                ],
                'specs' => [
                    ['label' => 'Edelmetall', 'value' => '925 Gelb vergoldet', 'sub' => 'ca. 2,50 g (gestempelt 925)'],
                    ['label' => 'Armband-Länge', 'value' => '18,5 cm (185 mm)', 'sub' => 'Figaro diamantiert 2,1 mm'],
                    ['label' => 'Gravurplatte', 'value' => '24 × 5,5 × 1,2 mm', 'sub' => 'Mit Herz-Durchbruch links'],
                    ['label' => 'Herkunft & Gravur', 'value' => 'Handveredelt in DE', 'sub' => 'Rohling: FBM (Made in DE)'],
                ],
            ],
        ];

        foreach ($sheets as $cfg) {
            $outPath = $publicProductsPath . '/' . $cfg['slug'] . '/' . $cfg['outFileName'];
            if (!file_exists($outPath) || $force || in_array($cfg['slug'], ['seelen-kristall', 'seelen-anhaenger', 'seelen-pfote-hundemarke', 'goldenes-seelen-herz', 'echtgold-seelen-herz', 'id-armband-silber-herz', 'id-armband-gold-herz'], true)) {
                $this->renderDimensionSheetWithGd($publicProductsPath, $cfg);
            }
        }
    }

    /**
     * Rendert ein 1024x1024 Technisches Maßblatt-Produktbild ({slug}-dimensions.jpg) nativ mit PHP-GD.
     */
    private function renderDimensionSheetWithGd(string $publicProductsPath, array $cfg): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $prodDir = $publicProductsPath . '/' . $cfg['slug'];
        $overlayPath = $prodDir . '/overlay.png';
        if (!file_exists($overlayPath)) {
            return;
        }

        $im = imagecreatetruecolor(1024, 1024);
        imagealphablending($im, true);

        $bgDark = imagecolorallocate($im, 18, 15, 12);
        imagefill($im, 0, 0, $bgDark);

        // Technisches Raster (40x40)
        $gridCol = imagecolorallocatealpha($im, 197, 160, 89, 114);
        for ($i = 40; $i < 1024; $i += 40) {
            imageline($im, $i, 0, $i, 1024, $gridCol);
            imageline($im, 0, $i, 1024, $i, $gridCol);
        }

        // Äußerer Goldrahmen
        $borderGold = imagecolorallocatealpha($im, 197, 160, 89, 65);
        imagesetthickness($im, 2);
        imagerectangle($im, 28, 28, 996, 996, $borderGold);

        // Produkt-Overlay (560x560) mittig einfügen
        $overlayImg = @imagecreatefrompng($overlayPath);
        if ($overlayImg) {
            $ow = imagesx($overlayImg);
            $oh = imagesy($overlayImg);
            $overlayTop = (int) ($cfg['overlayTop'] ?? 185);
            imagecopyresampled($im, $overlayImg, 232, $overlayTop, 0, 0, 560, 560, $ow, $oh);
            imagedestroy($overlayImg);
        }

        $fontSansBold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        $fontSans = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        $fontSerifBold = '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf';
        $hasTtf = file_exists($fontSansBold);

        $colGold = imagecolorallocate($im, 197, 160, 89);
        $colBrightGold = imagecolorallocate($im, 229, 193, 88);
        $colTextGold = imagecolorallocate($im, 248, 231, 176);
        $colWhite = imagecolorallocate($im, 255, 255, 255);
        $colMuted = imagecolorallocate($im, 184, 172, 156);
        $colCardBg = imagecolorallocate($im, 30, 26, 22);
        $colBadgeBg = imagecolorallocate($im, 23, 20, 17);

        $drawCenteredText = static function ($im, float $pxSize, int $cx, int $y, int $color, string $font, string $text) use ($hasTtf): void {
            if ($hasTtf && file_exists($font)) {
                $pt = $pxSize * 0.75;
                $bbox = imagettfbbox($pt, 0, $font, $text);
                $tw = $bbox[2] - $bbox[0];
                $tx = (int) round($cx - ($tw / 2) - $bbox[0]);
                imagettftext($im, $pt, 0, $tx, $y, $color, $font, $text);
            } else {
                $tw = strlen($text) * imagefontwidth(4);
                imagestring($im, 4, (int) ($cx - $tw / 2), $y - 12, $text, $color);
            }
        };

        // Header
        $drawCenteredText($im, 14, 512, 78, $colGold, $fontSansBold, 'MEIN-SEELENFUNKE MANUFAKTUR • TECHNISCHES MAßBLATT');
        $drawCenteredText($im, 30, 512, 118, $colWhite, $fontSerifBold, $cfg['title']);
        $drawCenteredText($im, 15, 512, 148, $colMuted, $fontSans, $cfg['subtitle']);

        // Horizontale Maßpfeile
        $colHelperGold = imagecolorallocatealpha($im, 197, 160, 89, 55);
        $colTrans = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagesetstyle($im, [$colHelperGold, $colHelperGold, $colHelperGold, $colHelperGold, $colHelperGold, $colTrans, $colTrans, $colTrans, $colTrans]);

        foreach ($cfg['horizArrows'] ?? [] as $ha) {
            $x1 = (int) $ha['x1'];
            $x2 = (int) $ha['x2'];
            $y = (int) $ha['y'];
            $midX = (int) round(($x1 + $x2) / 2);
            $label = (string) $ha['label'];
            $badgeW = (int) max(150, strlen($label) * 9 + 28);

            if (isset($ha['dashedYFrom1'])) {
                imageline($im, $x1, (int) $ha['dashedYFrom1'], $x1, $y, IMG_COLOR_STYLED);
            }
            if (isset($ha['dashedYFrom2'])) {
                imageline($im, $x2, (int) $ha['dashedYFrom2'], $x2, $y, IMG_COLOR_STYLED);
            }

            imageline($im, $x1, $y - 8, $x1, $y + 8, $colBrightGold);
            imageline($im, $x2, $y - 8, $x2, $y + 8, $colBrightGold);
            imageline($im, $x1 + 4, $y, $x2 - 4, $y, $colBrightGold);
            imagefilledpolygon($im, [$x1, $y, $x1 + 11, $y - 5, $x1 + 11, $y + 5], $colBrightGold);
            imagefilledpolygon($im, [$x2, $y, $x2 - 11, $y - 5, $x2 - 11, $y + 5], $colBrightGold);

            $rectY = ($ha['labelPos'] ?? 'below') === 'above' ? $y - 38 : $y + 8;
            $textY = ($ha['labelPos'] ?? 'below') === 'above' ? $y - 17 : $y + 29;
            imagefilledrectangle($im, (int) ($midX - $badgeW / 2), $rectY, (int) ($midX + $badgeW / 2), $rectY + 30, $colBadgeBg);
            imagerectangle($im, (int) ($midX - $badgeW / 2), $rectY, (int) ($midX + $badgeW / 2), $rectY + 30, $colGold);
            $drawCenteredText($im, 14, $midX, $textY, $colTextGold, $fontSansBold, $label);
        }

        // Vertikale Maßpfeile
        foreach ($cfg['vertArrows'] ?? [] as $va) {
            $y1 = (int) $va['y1'];
            $y2 = (int) $va['y2'];
            $x = (int) $va['x'];
            $midY = (int) round(($y1 + $y2) / 2);
            $label = (string) $va['label'];
            $badgeW = (int) max(155, strlen($label) * 9 + 26);

            if (isset($va['dashedXFrom1'])) {
                imageline($im, (int) $va['dashedXFrom1'], $y1, $x, $y1, IMG_COLOR_STYLED);
            }
            if (isset($va['dashedXFrom2'])) {
                imageline($im, (int) $va['dashedXFrom2'], $y2, $x, $y2, IMG_COLOR_STYLED);
            }

            imageline($im, $x - 8, $y1, $x + 8, $y1, $colBrightGold);
            imageline($im, $x - 8, $y2, $x + 8, $y2, $colBrightGold);
            imageline($im, $x, $y1 + 4, $x, $y2 - 4, $colBrightGold);
            imagefilledpolygon($im, [$x, $y1, $x - 5, $y1 + 11, $x + 5, $y1 + 11], $colBrightGold);
            imagefilledpolygon($im, [$x, $y2, $x - 5, $y2 - 11, $x + 5, $y2 - 11], $colBrightGold);

            $vPos = $va['labelPos'] ?? 'center';
            $badgeY = match ($vPos) {
                'above' => $y1 - 24,
                'below' => $y2 + 24,
                default => $midY,
            };
            imagefilledrectangle($im, (int) ($x - $badgeW / 2), $badgeY - 16, (int) ($x + $badgeW / 2), $badgeY + 16, $colBadgeBg);
            imagerectangle($im, (int) ($x - $badgeW / 2), $badgeY - 16, (int) ($x + $badgeW / 2), $badgeY + 16, $colGold);
            $drawCenteredText($im, 14, $x, $badgeY + 5, $colTextGold, $fontSansBold, $label);
        }

        // Callouts
        foreach ($cfg['callouts'] ?? [] as $co) {
            $x = (int) $co['x'];
            $y = (int) $co['y'];
            $tx = (int) $co['tx'];
            $ty = (int) $co['ty'];
            $label = (string) $co['label'];
            $badgeW = (int) max(170, strlen($label) * 9 + 28);

            imageline($im, $x, $y, $tx, $ty, $colBrightGold);
            imagefilledellipse($im, $x, $y, 10, 10, $colBrightGold);
            imagefilledrectangle($im, (int) ($tx - $badgeW / 2), $ty - 16, (int) ($tx + $badgeW / 2), $ty + 16, $colBadgeBg);
            imagerectangle($im, (int) ($tx - $badgeW / 2), $ty - 16, (int) ($tx + $badgeW / 2), $ty + 16, $colGold);
            $drawCenteredText($im, 14, $tx, $ty + 5, $colTextGold, $fontSansBold, $label);
        }

        // 4 Spezifikations-Kacheln unten
        foreach (array_slice($cfg['specs'] ?? [], 0, 4) as $idx => $s) {
            $bx = 56 + $idx * 232;
            imagefilledrectangle($im, $bx, 845, $bx + 216, 957, $colCardBg);
            imagerectangle($im, $bx, 845, $bx + 216, 957, $borderGold);

            $drawCenteredText($im, 13, $bx + 108, 882, $colMuted, $fontSansBold, mb_strtoupper((string) $s['label']));
            $drawCenteredText($im, 18, $bx + 108, 918, $colWhite, $fontSansBold, (string) $s['value']);
            $drawCenteredText($im, 12.5, $bx + 108, 942, $colGold, $fontSans, (string) ($s['sub'] ?? ''));
        }

        imagejpeg($im, $prodDir . '/' . $cfg['outFileName'], 92);
        imagedestroy($im);
    }
}

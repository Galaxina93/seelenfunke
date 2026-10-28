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

        // 2. Die Seelen-Pfote: 2D-Overlays + 3D-GLB-Mesh
        $pfoteDir = $publicProductsPath . '/seelen-pfote-hundemarke';
        File::ensureDirectoryExists($pfoteDir);
        $this->ensureHeaderBackground($publicProductsPath, $pfoteDir);

        $pfoteOverlayPath = $pfoteDir . '/overlay.png';
        if (!file_exists($pfoteOverlayPath)) {
            $this->renderFallbackPfoteOverlay($pfoteOverlayPath);
        }
        $this->ensureSeasonalOverlays($pfoteDir);

        $pfotePngBytes = file_get_contents($pfoteOverlayPath);
        $pfoteMesh = $this->buildPfote3DMesh();
        $pfoteGlb = $this->buildSingleTexturedGlb($pfoteMesh, $pfotePngBytes, 'SeelenPfote');
        file_put_contents($pfoteDir . '/seelen_pfote_3d.glb', $pfoteGlb);

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

        // 4. Saisonale Overlays auch für die bestehenden personalisierbaren Produkte sicherstellen
        foreach (['seelen-kristall', 'seelen-anhaenger', 'weizenglas-personalisiert'] as $slug) {
            $dir = $publicProductsPath . '/' . $slug;
            if (is_dir($dir) && file_exists($dir . '/overlay.png')) {
                $this->ensureSeasonalOverlays($dir);
            }
        }

        // 5. Technische Maßblätter (*-dimensions.jpg) für alle physischen Produkte sicherstellen
        $this->generateAllDimensionSheets($publicProductsPath);
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
     * Erzeugt das exakt zum 2D-Overlay (800x800) deckungsgleiche 3D-Mesh für Die Seelen-Pfote.
     */
    private function buildPfote3DMesh(): array
    {
        $positions = [];
        $normals = [];
        $uvs = [];
        $indices = [];

        $bonePts = [];
        $sampleCubic = static function (array $p0, array $p1, array $p2, array $p3, int $steps = 14) use (&$bonePts): void {
            for ($i = 1; $i <= $steps; $i++) {
                $t = $i / $steps;
                $mt = 1.0 - $t;
                $x = $mt * $mt * $mt * $p0[0] + 3.0 * $mt * $mt * $t * $p1[0] + 3.0 * $mt * $t * $t * $p2[0] + $t * $t * $t * $p3[0];
                $y = $mt * $mt * $mt * $p0[1] + 3.0 * $mt * $mt * $t * $p1[1] + 3.0 * $mt * $t * $t * $p2[1] + $t * $t * $t * $p3[1];
                $bonePts[] = [$x, $y];
            }
        };

        $bonePts[] = [357.0, 290.0];
        $sampleCubic([357.0, 290.0], [360.0, 250.0], [377.0, 225.0], [400.0, 225.0], 10);
        $sampleCubic([400.0, 225.0], [423.0, 225.0], [440.0, 250.0], [443.0, 290.0], 10);
        $bonePts[] = [530.0, 290.0];
        $sampleCubic([530.0, 290.0], [572.0, 290.0], [592.0, 225.0], [646.0, 225.0]);
        $sampleCubic([646.0, 225.0], [696.0, 225.0], [720.0, 268.0], [720.0, 318.0]);
        $sampleCubic([720.0, 318.0], [720.0, 362.0], [696.0, 385.0], [684.0, 400.0]);
        $sampleCubic([684.0, 400.0], [696.0, 415.0], [720.0, 438.0], [720.0, 482.0]);
        $sampleCubic([720.0, 482.0], [720.0, 532.0], [696.0, 575.0], [646.0, 575.0]);
        $sampleCubic([646.0, 575.0], [592.0, 575.0], [572.0, 510.0], [530.0, 510.0]);
        $bonePts[] = [270.0, 510.0];
        $sampleCubic([270.0, 510.0], [228.0, 510.0], [208.0, 575.0], [154.0, 575.0]);
        $sampleCubic([154.0, 575.0], [104.0, 575.0], [80.0, 532.0], [80.0, 482.0]);
        $sampleCubic([80.0, 482.0], [80.0, 438.0], [104.0, 415.0], [116.0, 400.0]);
        $sampleCubic([116.0, 400.0], [104.0, 385.0], [80.0, 362.0], [80.0, 318.0]);
        $sampleCubic([80.0, 318.0], [80.0, 268.0], [104.0, 225.0], [154.0, 225.0]);
        $sampleCubic([154.0, 225.0], [208.0, 225.0], [228.0, 290.0], [270.0, 290.0]);

        $this->extrudePixelContour($bonePts, null, [400.0, 400.0], 0.032, $positions, $normals, $uvs, $indices);

        // 3D-Innenwände für das obere Ösen-Loch und die 5 Pfoten-Durchbrüche
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

        $makeOvalPts = static function (float $cx, float $cy, float $rx, float $ry, float $deg, int $steps = 20): array {
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

        $addHoleWallRing($makeOvalPts(400.0, 270.0, 21.0, 21.0, 0.0, 28));
        $addHoleWallRing($makeOvalPts(612.0, 488.0, 20.0, 17.0, -35.0, 24));
        $addHoleWallRing($makeOvalPts(573.0, 493.0, 10.5, 7.8, -28.0, 16));
        $addHoleWallRing($makeOvalPts(572.0, 466.0, 11.0, 8.2, -32.0, 16));
        $addHoleWallRing($makeOvalPts(592.0, 446.0, 11.0, 8.2, -36.0, 16));
        $addHoleWallRing($makeOvalPts(619.0, 448.0, 10.5, 7.8, -40.0, 16));

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
            'asset' => ['version' => '2.0', 'generator' => 'Seelenfunke-PHP-Seeder-3D-v2'],
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
     * Fallback-Renderer in reinem PHP-GD, falls overlay.png für die Seelen-Pfote auf einem frischen System fehlt.
     */
    private function renderFallbackPfoteOverlay(string $targetPath): void
    {
        if (!extension_loaded('gd')) {
            return;
        }
        $im = imagecreatetruecolor(800, 800);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $trans);

        $silver = imagecolorallocatealpha($im, 228, 233, 240, 0);
        imagefilledrectangle($im, 240, 290, 560, 510, $silver);
        imagefilledellipse($im, 165, 310, 170, 170, $silver);
        imagefilledellipse($im, 165, 490, 170, 170, $silver);
        imagefilledellipse($im, 635, 310, 170, 170, $silver);
        imagefilledellipse($im, 635, 490, 170, 170, $silver);
        imagefilledellipse($im, 400, 270, 86, 86, $silver);
        imagefilledellipse($im, 400, 270, 42, 42, $trans);

        imagepng($im, $targetPath, 9);
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
            $drawCenter($im, 14.0, 512, 945, $whiteCol, $fontSerifBold, 'Das Goldene Seelen-Herz • 14k Ion-Plating Edelstahl');
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
            'MEIN-SEELENFUNKE MANUFAKTUR • 14 KARAT VERGOLDET',
            'Edelstahl-Herz mit Collierschlaufe (25 × 22 × 4 mm) • Präzisions-Faserlasergravur'
        );

        $renderScene(
            $goldHerzDir . '/goldenes-seelen-herz-back.jpg',
            [25, 21, 18],
            [12, 10, 8],
            ['24.08.2026', '52°28\'N • 10°32\'E', 'Dein Seelenmensch'],
            'BEIDSEITIGE LASER-VEREDELUNG • RÜCKSEITEN-ANSICHT',
            'Rückseite individuell gravierbar mit Wunschdatum, Koordinaten oder Widmung'
        );

        $renderScene(
            $goldHerzDir . '/goldenes-seelen-herz-lifestyle.jpg',
            [34, 26, 19],
            [15, 11, 8],
            ['Mama ♥', 'Du bist unser', 'Goldstück'],
            'INKLUSIVE COLLIERSCHLAUFE (ÖSE 3 × 6 MM) • GESCHENKFERTIG',
            'Langlebige 14k Ion-Plating Vergoldung auf antiallergischem Chirurgen-Edelstahl'
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
                'SAISONALE GESCHENK-EDITION • MEIN-SEELENFUNKE',
                '14 Karat vergoldeter Edelstahl-Herz-Anhänger mit Collierschlaufe (25 × 22 × 4 mm)'
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
                'title' => 'Der Seelen Kristall – Maße & Details',
                'subtitle' => 'Optisches K9-Kristallglas mit geschliffener Facettenkante & Tiefen-Lasergravur',
                'overlayTop' => 185,
                'horizArrows' => [
                    ['x1' => 274, 'x2' => 750, 'y' => 745, 'dashedYFrom1' => 465, 'dashedYFrom2' => 465, 'labelPos' => 'below', 'label' => 'Breite: 180 mm (18,0 cm)'],
                ],
                'vertArrows' => [
                    ['y1' => 228, 'y2' => 712, 'x' => 868, 'dashedXFrom1' => 512, 'dashedXFrom2' => 655, 'label' => 'Höhe: 200 mm'],
                ],
                'callouts' => [
                    ['x' => 512, 'y' => 315, 'tx' => 210, 'ty' => 220, 'label' => 'Gravurfläche: Ø 150 mm'],
                ],
                'specs' => [
                    ['label' => 'Abmessungen', 'value' => '180 × 200 mm', 'sub' => 'Facetten-Achteck / Kreis'],
                    ['label' => 'Materialstärke', 'value' => '40 mm (Massiv)', 'sub' => 'Freistehend ohne Sockel'],
                    ['label' => 'Gewicht', 'value' => 'ca. 920 g', 'sub' => 'Schwere Manufaktur-Qualität'],
                    ['label' => 'Material', 'value' => 'K9 Kristallglas', 'sub' => 'Hochreiner Facettenschliff'],
                ],
            ],
            [
                'slug' => 'seelen-anhaenger',
                'outFileName' => 'seelen-anhaenger-dimensions.jpg',
                'title' => 'Der Seelenanhänger – Maße & Details',
                'subtitle' => 'Herz-Schlüsselanhänger aus eloxiertem Aluminium mit Hochglanz-Metallrahmen',
                'overlayTop' => 185,
                'horizArrows' => [
                    ['x1' => 266, 'x2' => 758, 'y' => 735, 'dashedYFrom1' => 390, 'dashedYFrom2' => 390, 'labelPos' => 'below', 'label' => 'Herz-Breite: 45 mm (4,5 cm)'],
                ],
                'vertArrows' => [
                    ['y1' => 225, 'y2' => 692, 'x' => 868, 'dashedXFrom1' => 615, 'dashedXFrom2' => 512, 'label' => 'Höhe: 40 mm'],
                ],
                'callouts' => [
                    ['x' => 512, 'y' => 510, 'tx' => 190, 'ty' => 635, 'label' => 'Gravurfeld: 36 × 32 mm'],
                ],
                'specs' => [
                    ['label' => 'Abmessungen', 'value' => '45 × 40 mm', 'sub' => 'inkl. massivem Spaltring'],
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
                    ['label' => 'Materialstärke', 'value' => '2,2 mm (Massiv)', 'sub' => 'Rostfreier 304 Edelstahl'],
                    ['label' => 'Gewicht', 'value' => 'ca. 24 g', 'sub' => 'Spürbar wertige Haptik'],
                    ['label' => 'Veredelung', 'value' => 'Beidseitig', 'sub' => 'Dunkle Faserlaser-Anlassgravur'],
                ],
            ],
            [
                'slug' => 'seelen-pfote-hundemarke',
                'outFileName' => 'seelen-pfote-dimensions.jpg',
                'title' => 'Die Seelen-Pfote – Maße & Details',
                'subtitle' => 'Hochglanzpolierte Edelstahl-Hundemarke mit ausgestanztem Pfotenabdruck',
                'overlayTop' => 185,
                'horizArrows' => [
                    ['x1' => 288, 'x2' => 736, 'y' => 265, 'dashedYFrom1' => 385, 'dashedYFrom2' => 385, 'labelPos' => 'above', 'label' => 'Breite: 40 mm (1,57 in)'],
                ],
                'vertArrows' => [
                    ['y1' => 342, 'y2' => 588, 'x' => 165, 'dashedXFrom1' => 340, 'dashedXFrom2' => 340, 'label' => 'Höhe: 22 mm (0,87 in)'],
                ],
                'callouts' => [
                    ['x' => 425, 'y' => 485, 'tx' => 330, 'ty' => 690, 'label' => 'Beidseitige Laser-Gravurzone'],
                    ['x' => 660, 'y' => 525, 'tx' => 720, 'ty' => 690, 'label' => 'Echter Pfoten-Durchbruch'],
                ],
                'specs' => [
                    ['label' => 'Knochen-Maß', 'value' => '40 × 22 mm', 'sub' => '1,57 × 0,87 in (inkl. Öse)'],
                    ['label' => 'Materialstärke', 'value' => '1,8 mm', 'sub' => 'Massiver V2A-Edelstahl'],
                    ['label' => 'Gewicht', 'value' => 'ca. 12 g (0,43 oz)', 'sub' => 'Rostfrei, wasser- & kratzfest'],
                    ['label' => 'Gravur', 'value' => 'Beidseitig', 'sub' => 'Name vorn + Notfall-Nr. hinten'],
                ],
            ],
            [
                'slug' => 'goldenes-seelen-herz',
                'outFileName' => 'goldenes-seelen-herz-dimensions.jpg',
                'title' => 'Das Goldene Seelen-Herz – Maße & Details',
                'subtitle' => 'Massiver 14k vergoldeter Edelstahl-Herzanhänger (Ion-Plating) mit Collierschlaufe',
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
                    ['label' => 'Oberfläche', 'value' => '14 Karat Vergoldet', 'sub' => 'Kratzfestes Ion-Plating (IP)'],
                    ['label' => 'Collierschlaufe', 'value' => 'Öse 3 × 6 mm', 'sub' => 'Für Halsketten & Bänder'],
                    ['label' => 'Herkunft & Gravur', 'value' => 'Made in Germany', 'sub' => 'Beidseitige Faserlaser-Gravur'],
                ],
            ],
        ];

        foreach ($sheets as $cfg) {
            $outPath = $publicProductsPath . '/' . $cfg['slug'] . '/' . $cfg['outFileName'];
            if (!file_exists($outPath) || $force || $cfg['slug'] === 'goldenes-seelen-herz') {
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

            imagefilledrectangle($im, (int) ($x - $badgeW / 2), $midY - 16, (int) ($x + $badgeW / 2), $midY + 16, $colBadgeBg);
            imagerectangle($im, (int) ($x - $badgeW / 2), $midY - 16, (int) ($x + $badgeW / 2), $midY + 16, $colGold);
            $drawCenteredText($im, 14, $x, $midY + 5, $colTextGold, $fontSansBold, $label);
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

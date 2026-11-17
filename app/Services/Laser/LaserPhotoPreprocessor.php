<?php

namespace App\Services\Laser;

/**
 * Physik-kalibrierte Foto-zu-Laser-Pipeline für den xTool F2 Ultra UV (5 W · 355 nm).
 *
 * Hintergrund (Echtfoto "Der Seelenanhänger", 11/2026): Bei 600 DPI liegt der Punktabstand bei 42,3 µm, der
 * UV-Krater misst 42..48 µm. Ab ca. 58..60 % Punktdichte überlappen benachbarte Krater (UV-Dot-Gain) und das
 * schwarze PVD/Eloxal wird flächig zu 100 % abgetragen. Gesichter verschmelzen dann zu einer silber-weißen
 * Blechfläche. Diese Klasse bildet die Szenen-Luminanz eines Kundenfotos substrat- und polaritätsabhängig so
 * auf eine Ziel-Laserdichte ab, dass zwischen den Punkten feine Mikroporen stehen bleiben.
 *
 * Konvention der Ausgabe-Bitmap (xTool Studio): Grauwert 0 = Laser feuert voll, 255 = Laser pausiert.
 * Zieldichte d (0..1) wird als Gray = round(255 * (1 - d)) geschrieben.
 */
class LaserPhotoPreprocessor
{
    public const POLARITY_WHITE_ON_DARK = 'white_on_dark';
    public const POLARITY_DARK_ON_LIGHT = 'dark_on_light';

    /** white_on_dark: UV-Dot-Gain-Gammakurve & Highlight-Deckel */
    public const DOT_GAIN_GAMMA = 1.90;
    public const HIGHLIGHT_CAP = 0.78;
    public const SHADOW_FLOOR = 0.13;
    public const SHADOW_KNEE = 0.24;

    /** dark_on_light: d = k^1.40 * 0.82 mit k = 1 - Luminanz */
    public const DARK_ON_LIGHT_GAMMA = 1.40;
    public const DARK_ON_LIGHT_SCALE = 0.82;
    public const DARK_ON_LIGHT_HIGHLIGHT_FLOOR = 0.10;

    /** Auto-Tonwertspreizung (Perzentile auf sichtbaren Pixeln) mit 12 % Tonwert-Reserve am Laser-Ende */
    public const STRETCH_LOW_PERCENTILE = 0.02;
    public const STRETCH_HIGH_PERCENTILE = 0.98;
    public const STRETCH_HEADROOM = 0.88;

    /** Unsharp Mask für Augen, Wimpern, Nasenflügel, Lippen, Locken */
    public const UNSHARP_AMOUNT = 0.85;
    public const UNSHARP_SIGMA_MM = 0.12;

    /** Sanfte S-Kurve nach der Tonwertspreizung (Steigung Mitte 1 + k, Enden 1 - k) */
    public const S_CURVE_STRENGTH = 0.22;

    /**
     * Kontrast-S-Kurve f(t) = t - k * sin(2πt) / (2π): Mitteltöne werden gespreizt, Tiefen & Lichter weich abgerollt.
     */
    public static function applySCurve(float $t, float $strength = self::S_CURVE_STRENGTH): float
    {
        $t = max(0.0, min(1.0, $t));

        return max(0.0, min(1.0, $t - ($strength * sin(2.0 * M_PI * $t) / (2.0 * M_PI))));
    }

    /** Lesbarkeits-Schutzsaum um Texte & Vektorsymbole über Kundenfotos */
    public const TEXT_KNOCKOUT_MM = 0.45;
    public const TEXT_KNOCKOUT_SOFT_MM = 0.15;

    public static function normalizePolarity(?string $raw, bool $isDarkSurfaceFallback = false): string
    {
        $p = mb_strtolower(trim((string) $raw));
        if (in_array($p, ['white_on_dark', 'light', 'frosted', 'white', 'silver_on_black'], true)) {
            return self::POLARITY_WHITE_ON_DARK;
        }
        if (in_array($p, ['dark_on_light', 'dark', 'black_on_light'], true)) {
            return self::POLARITY_DARK_ON_LIGHT;
        }

        return $isDarkSurfaceFallback ? self::POLARITY_WHITE_ON_DARK : self::POLARITY_DARK_ON_LIGHT;
    }

    /**
     * Ziel-Laserdichte d (0..1) für eine Szenen-Luminanz (0 = schwarz, 1 = weiß) auf dem jeweiligen Substrat.
     */
    public static function densityFromLuminance(float $lum, string $polarity): float
    {
        $lum = max(0.0, min(1.0, $lum));

        if ($polarity === self::POLARITY_WHITE_ON_DARK) {
            if ($lum <= self::SHADOW_FLOOR) {
                return 0.0;
            }
            $knee = 1.0;
            if ($lum < self::SHADOW_KNEE) {
                $t = ($lum - self::SHADOW_FLOOR) / (self::SHADOW_KNEE - self::SHADOW_FLOOR);
                $knee = $t * $t * (3.0 - (2.0 * $t));
            }

            return min(self::HIGHLIGHT_CAP, self::HIGHLIGHT_CAP * pow($lum, self::DOT_GAIN_GAMMA) * $knee);
        }

        $k = 1.0 - $lum;
        if ($k < self::DARK_ON_LIGHT_HIGHLIGHT_FLOOR) {
            return 0.0;
        }

        return min(self::DARK_ON_LIGHT_SCALE, pow($k, self::DARK_ON_LIGHT_GAMMA) * self::DARK_ON_LIGHT_SCALE);
    }

    public static function grayFromDensity(float $density): int
    {
        return max(0, min(255, (int) round(255.0 * (1.0 - max(0.0, min(1.0, $density))))));
    }

    public static function densityFromGray(int $gray): float
    {
        return 1.0 - (max(0, min(255, $gray)) / 255.0);
    }

    /**
     * Unterscheidet echte Halbton-Fotos (Porträt, Haustier) von flachen Silhouetten / Strich-Logos (bimodal).
     *
     * @param array<int, int> $hist 256er Histogramm der sichtbaren Pixel
     */
    public static function classifyHistogram(array $hist, int $total): string
    {
        if ($total < 64) {
            return 'silhouette';
        }

        $distinct = 0;
        foreach ($hist as $count) {
            if ($count > 0) {
                $distinct++;
            }
        }
        if ($distinct <= 12) {
            return 'silhouette';
        }

        // 32 Klassen à 8 Grauwerte: Silhouetten (auch mit JPEG-Rauschen & Kantenglättung) belegen 2..4 Klassen
        // nennenswert, echte Halbton-Fotos verteilen sich über >= 6 Klassen.
        $minBinCount = max(1, (int) round($total * 0.004));
        $occupiedBins = 0;
        for ($bin = 0; $bin < 32; $bin++) {
            $sum = 0;
            for ($g = $bin * 8; $g < ($bin + 1) * 8; $g++) {
                $sum += (int) ($hist[$g] ?? 0);
            }
            if ($sum >= $minBinCount) {
                $occupiedBins++;
            }
        }

        return $occupiedBins >= 6 ? 'halftone' : 'silhouette';
    }

    /**
     * Rekonstruiert die Szenen-Luminanz (0..1) aus dem normalisierten Grauwert des Export-Puffers.
     *
     * - 'rgb':        echtes Farb-/Graustufenfoto (Grauwert = Luminanz)
     * - 'mask_dark':  Konfigurator-Alpha-Maske mit Positiv-Kodierung für dunkle Oberflächen
     *                 (gdAlpha = 123 - round(L^0.85 * 105) -> Deckkraft o = (4 + 105 * L^0.85) / 127)
     * - 'mask_light': Konfigurator-Alpha-Maske mit Standard-Kodierung (o ~ 1 - L)
     */
    public static function decodeLuminance(int $gray, string $encoding): float
    {
        $g = max(0, min(255, $gray));
        if ($encoding === 'mask_dark') {
            $opacity = 1.0 - ($g / 255.0);
            $base = ((127.0 * $opacity) - 4.0) / 105.0;
            if ($base <= 0.0) {
                return 0.0;
            }

            return max(0.0, min(1.0, pow(min(1.0, $base), 1.0 / 0.85)));
        }

        return $g / 255.0;
    }

    /**
     * Ermittelt die Kodierung des Quellbildes anhand der sichtbaren Pixel.
     *
     * @param int $maxRgbChannel  höchster R/G/B-Wert aller sichtbaren Pixel
     * @param int $strongMaskPixels Anzahl sichtbarer Pixel mit Deckkraft >= 0.88 (gdAlpha < 16)
     */
    public static function detectSourceEncoding(int $maxRgbChannel, int $visiblePixels, int $strongMaskPixels, string $polarity): string
    {
        if ($maxRgbChannel > 10 || $visiblePixels <= 0) {
            return 'rgb';
        }
        if ($polarity === self::POLARITY_WHITE_ON_DARK && ($strongMaskPixels / max(1, $visiblePixels)) < 0.02) {
            return 'mask_dark';
        }

        return 'mask_light';
    }

    /**
     * Wandelt den sichtbaren Halbton-Bereich eines normalisierten Graustufen-Puffers (GD, opak = sichtbar)
     * in-place in die physikalisch kalibrierte Laser-Bitmap um:
     * 1. Luminanz-Rekonstruktion, 2. P2..P98 Tonwertspreizung, 3. Unsharp Mask,
     * 4. Substrat-abhängige Dot-Gain-Kurve & Polarität, 5. Gray = round(255 * (1 - d)).
     *
     * @return array{mean_density: float, pixels: int, p_low: float, p_high: float}
     */
    public function processHalftoneInPlace($dst, int $x0, int $y0, int $x1, int $y1, string $polarity, string $encoding, float $pxPerMm): array
    {
        $bw = $x1 - $x0 + 1;
        $bh = $y1 - $y0 + 1;
        if ($bw <= 0 || $bh <= 0) {
            return ['mean_density' => 0.0, 'pixels' => 0, 'p_low' => 0.0, 'p_high' => 1.0];
        }

        $lumImg = imagecreatetruecolor($bw, $bh);
        $lumLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $lumLut[$g] = imagecolorallocate($lumImg, $g, $g, $g);
        }

        $decodeLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $decodeLut[$g] = (int) round(self::decodeLuminance($g, $encoding) * 255.0);
        }

        // 1. Luminanz-Rekonstruktion + Histogramm
        $hist = array_fill(0, 256, 0);
        $visible = 0;
        $lumSum = 0;
        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                $c = imagecolorat($dst, $x, $y);
                if ((($c >> 24) & 0x7F) >= 126) {
                    continue;
                }
                $l8 = $decodeLut[$c & 0xFF];
                imagesetpixel($lumImg, $x - $x0, $y - $y0, $lumLut[$l8]);
                $hist[$l8]++;
                $visible++;
                $lumSum += $l8;
            }
        }

        if ($visible === 0) {
            imagedestroy($lumImg);
            return ['mean_density' => 0.0, 'pixels' => 0, 'p_low' => 0.0, 'p_high' => 1.0];
        }

        // Unsichtbare Pixel mit dem Mittelwert füllen (keine Randartefakte in der Unsharp Mask)
        $meanL8 = (int) round($lumSum / $visible);
        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                if (((imagecolorat($dst, $x, $y) >> 24) & 0x7F) >= 126) {
                    imagesetpixel($lumImg, $x - $x0, $y - $y0, $lumLut[$meanL8]);
                }
            }
        }

        // 2. Perzentil-Tonwertspreizung P2..P98 + sanfte S-Kurve (klare Zeichnung in Gesichtern, Fell & Stoff)
        $pLow = $this->percentileFromHistogram($hist, $visible, self::STRETCH_LOW_PERCENTILE) / 255.0;
        $pHigh = $this->percentileFromHistogram($hist, $visible, self::STRETCH_HIGH_PERCENTILE) / 255.0;
        $span = $pHigh - $pLow;
        $stretchLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $l = $g / 255.0;
            $t = $span >= 0.06 ? max(0.0, min(1.0, ($l - $pLow) / $span)) : $l;
            $t = self::applySCurve($t);
            if ($span >= 0.06) {
                $l = ($polarity === self::POLARITY_WHITE_ON_DARK)
                    ? $t * self::STRETCH_HEADROOM
                    : (1.0 - self::STRETCH_HEADROOM) + ($t * self::STRETCH_HEADROOM);
            } else {
                $l = $t;
            }
            $stretchLut[$g] = $l;
        }

        // 3. Unsharp Mask (Gauß-Unschärfe über C-Puffer)
        $blurImg = $this->buildGaussianBlurCopy($lumImg, $bw, $bh, max(0.6, self::UNSHARP_SIGMA_MM * $pxPerMm));

        // 4. + 5. Dot-Gain-Kurve & Ausgabe
        $densityLut = [];
        for ($q = 0; $q <= 1023; $q++) {
            $densityLut[$q] = self::densityFromLuminance($q / 1023.0, $polarity);
        }
        $outLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $outLut[$g] = imagecolorallocatealpha($dst, $g, $g, $g, 0);
        }

        $densitySum = 0.0;
        for ($y = $y0; $y <= $y1; $y++) {
            $ly = $y - $y0;
            for ($x = $x0; $x <= $x1; $x++) {
                if (((imagecolorat($dst, $x, $y) >> 24) & 0x7F) >= 126) {
                    continue;
                }
                $lx = $x - $x0;
                $base = $stretchLut[imagecolorat($lumImg, $lx, $ly) & 0xFF];
                $blur = $blurImg ? $stretchLut[imagecolorat($blurImg, $lx, $ly) & 0xFF] : $base;
                $sharp = max(0.0, min(1.0, $base + (self::UNSHARP_AMOUNT * ($base - $blur))));
                $d = $densityLut[(int) round($sharp * 1023.0)];
                $densitySum += $d;
                imagesetpixel($dst, $x, $y, $outLut[self::grayFromDensity($d)]);
            }
        }

        imagedestroy($lumImg);
        if ($blurImg) {
            imagedestroy($blurImg);
        }

        return [
            'mean_density' => round($densitySum / $visible, 4),
            'pixels'       => $visible,
            'p_low'        => round($pLow, 4),
            'p_high'       => round($pHigh, 4),
        ];
    }

    /**
     * Stanzt im Foto-Layer einen Lesbarkeits-Schutzsaum (0 % Foto-Laserung) um alle Text-/Vektor-Pixel frei.
     * Kern (<= radiusPx) wird zu Gray 255, der weiche Außenring (radiusPx..radiusPx+softPx) zu 50 % aufgehellt.
     *
     * @return int Anzahl freigestanzter Foto-Pixel
     */
    public function applyTextKnockoutHalo($photoCanvas, $vectorCanvas, int $radiusPx, int $softPx = 0): int
    {
        $w = imagesx($photoCanvas);
        $h = imagesy($photoCanvas);
        if ($w !== imagesx($vectorCanvas) || $h !== imagesy($vectorCanvas)) {
            return 0;
        }

        $outer = max(1, $radiusPx + max(0, $softPx));
        $minX = $w;
        $maxX = -1;
        $minY = $h;
        $maxY = -1;
        $glyph = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($vectorCanvas, $x, $y);
                if ((($c >> 24) & 0x7F) < 100 && ($c & 0xFF) < 200) {
                    $glyph[$y * $w + $x] = 1;
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }
        if ($maxX < $minX) {
            return 0;
        }

        $bx0 = max(0, $minX - $outer);
        $bx1 = min($w - 1, $maxX + $outer);
        $by0 = max(0, $minY - $outer);
        $by1 = min($h - 1, $maxY + $outer);
        $bw = $bx1 - $bx0 + 1;
        $bh = $by1 - $by0 + 1;

        $mask = array_fill(0, $bw * $bh, 0);
        foreach ($glyph as $idx => $_) {
            $gx = $idx % $w;
            $gy = intdiv($idx, $w);
            $mask[(($gy - $by0) * $bw) + ($gx - $bx0)] = 1;
        }
        unset($glyph);

        $core = $this->dilateBinaryMask($mask, $bw, $bh, max(0, $radiusPx));
        $ring = $outer > $radiusPx ? $this->dilateBinaryMask($mask, $bw, $bh, $outer) : $core;

        $grayLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $grayLut[$g] = imagecolorallocatealpha($photoCanvas, $g, $g, $g, 0);
        }

        $knocked = 0;
        for ($ly = 0; $ly < $bh; $ly++) {
            for ($lx = 0; $lx < $bw; $lx++) {
                $i = ($ly * $bw) + $lx;
                if ($ring[$i] === 0) {
                    continue;
                }
                $x = $bx0 + $lx;
                $y = $by0 + $ly;
                $c = imagecolorat($photoCanvas, $x, $y);
                if ((($c >> 24) & 0x7F) >= 126) {
                    continue;
                }
                $gray = $c & 0xFF;
                $newGray = $core[$i] === 1 ? 255 : (int) round(($gray + 255) / 2);
                if ($newGray !== $gray) {
                    imagesetpixel($photoCanvas, $x, $y, $grayLut[$newGray]);
                    $knocked++;
                }
            }
        }

        return $knocked;
    }

    /**
     * Echtes 12-Gewichte Jarvis-Judice-Ninke Error-Diffusion-Dithering (Ausgabe 0 = Laserpunkt, 255 = Pause).
     * Transparente Pixel (gdAlpha >= 120) bleiben transparent und erhalten keinen Fehleranteil.
     */
    public function jarvisDither($grayImg)
    {
        $w = imagesx($grayImg);
        $h = imagesy($grayImg);
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $trans = imagecolorallocatealpha($out, 255, 255, 255, 127);
        $black = imagecolorallocatealpha($out, 0, 0, 0, 0);
        $white = imagecolorallocatealpha($out, 255, 255, 255, 0);
        imagefill($out, 0, 0, $trans);

        $buf = [];
        $vis = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($grayImg, $x, $y);
                $i = ($y * $w) + $x;
                $a = ($c >> 24) & 0x7F;
                $vis[$i] = $a < 120;
                $buf[$i] = (float) ($c & 0xFF);
            }
        }

        // Jarvis, Judice & Ninke (1976): Divisor 48
        $kernel = [
            [1, 0, 7], [2, 0, 5],
            [-2, 1, 3], [-1, 1, 5], [0, 1, 7], [1, 1, 5], [2, 1, 3],
            [-2, 2, 1], [-1, 2, 3], [0, 2, 5], [1, 2, 3], [2, 2, 1],
        ];

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $i = ($y * $w) + $x;
                if (!$vis[$i]) {
                    continue;
                }
                $old = $buf[$i];
                $new = $old < 128.0 ? 0.0 : 255.0;
                imagesetpixel($out, $x, $y, $new === 0.0 ? $black : $white);
                $err = $old - $new;
                if ($err == 0.0) {
                    continue;
                }
                foreach ($kernel as [$dx, $dy, $wgt]) {
                    $nx = $x + $dx;
                    $ny = $y + $dy;
                    if ($nx < 0 || $nx >= $w || $ny >= $h) {
                        continue;
                    }
                    $ni = ($ny * $w) + $nx;
                    if ($vis[$ni]) {
                        $buf[$ni] += $err * $wgt / 48.0;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Mittlere Laserdichte (0..1) aller sichtbaren Pixel eines Laser-PNG (Gray 0 = 100 % Dichte).
     */
    public static function measureMeanDensity(string $pngBytes): float
    {
        $img = @imagecreatefromstring($pngBytes);
        if (!$img) {
            return 0.0;
        }
        $sum = 0.0;
        $n = 0;
        $w = imagesx($img);
        $h = imagesy($img);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a >= 120) {
                    continue;
                }
                $sum += self::densityFromGray($c & 0xFF) * ((127 - $a) / 127.0);
                $n++;
            }
        }
        imagedestroy($img);

        return $n > 0 ? $sum / $n : 0.0;
    }

    private function percentileFromHistogram(array $hist, int $total, float $p): int
    {
        $target = max(1, (int) round($total * $p));
        $cum = 0;
        for ($g = 0; $g <= 255; $g++) {
            $cum += $hist[$g];
            if ($cum >= $target) {
                return $g;
            }
        }

        return 255;
    }

    private function buildGaussianBlurCopy($src, int $w, int $h, float $sigmaPx)
    {
        if (!function_exists('imagefilter')) {
            return null;
        }

        // Gauß-Näherung: Herunterskalieren (1 px ~ 0,85 sigma), 3x3 Gauß, bilinear zurück
        $factor = max(1.0, $sigmaPx / 0.85);
        $sw = max(4, (int) round($w / $factor));
        $sh = max(4, (int) round($h / $factor));
        $small = imagecreatetruecolor($sw, $sh);
        $blur = imagecreatetruecolor($w, $h);
        if (!$small || !$blur) {
            if ($small) imagedestroy($small);
            if ($blur) imagedestroy($blur);
            return null;
        }
        imagecopyresampled($small, $src, 0, 0, 0, 0, $sw, $sh, $w, $h);
        @imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        imagecopyresampled($blur, $small, 0, 0, 0, 0, $w, $h, $sw, $sh);
        @imagefilter($blur, IMG_FILTER_GAUSSIAN_BLUR);
        imagedestroy($small);

        return $blur;
    }

    /**
     * Binäre Dilatation (quadratischer Kern) über separable Präfixsummen in O(N).
     *
     * @param array<int, int> $mask
     * @return array<int, int>
     */
    private function dilateBinaryMask(array $mask, int $w, int $h, int $r): array
    {
        if ($r <= 0) {
            return $mask;
        }

        $horiz = array_fill(0, $w * $h, 0);
        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            $prefix = [0];
            for ($x = 0; $x < $w; $x++) {
                $prefix[$x + 1] = $prefix[$x] + $mask[$row + $x];
            }
            for ($x = 0; $x < $w; $x++) {
                $a = max(0, $x - $r);
                $b = min($w - 1, $x + $r);
                if ($prefix[$b + 1] - $prefix[$a] > 0) {
                    $horiz[$row + $x] = 1;
                }
            }
        }

        $out = array_fill(0, $w * $h, 0);
        for ($x = 0; $x < $w; $x++) {
            $prefix = [0];
            for ($y = 0; $y < $h; $y++) {
                $prefix[$y + 1] = $prefix[$y] + $horiz[($y * $w) + $x];
            }
            for ($y = 0; $y < $h; $y++) {
                $a = max(0, $y - $r);
                $b = min($h - 1, $y + $r);
                if ($prefix[$b + 1] - $prefix[$a] > 0) {
                    $out[($y * $w) + $x] = 1;
                }
            }
        }

        return $out;
    }
}

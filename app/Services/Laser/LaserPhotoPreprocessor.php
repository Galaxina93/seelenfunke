<?php

namespace App\Services\Laser;

use Illuminate\Support\Facades\Storage;

/**
 * Physik-kalibrierte Foto-zu-Laser-Pipeline und mathematisches Foto-Studio
 * für den xTool F2 Ultra UV (5 W · 355 nm).
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
    public const DOT_GAIN_GAMMA_600DPI = 1.55;
    public const HIGHLIGHT_CAP = 0.78;
    public const HIGHLIGHT_CAP_DARK_600DPI = 0.58;
    public const SHADOW_FLOOR = 0.13;
    public const SHADOW_KNEE = 0.24;

    /** dark_on_light: d = k^1.40 * 0.82 mit k = 1 - Luminanz */
    public const DARK_ON_LIGHT_GAMMA = 1.40;
    public const DARK_ON_LIGHT_SCALE = 0.82;
    public const DARK_ON_LIGHT_HIGHLIGHT_FLOOR = 0.10;

    /** Auto-Tonwertspreizung (Perzentile auf sichtbaren Pixeln) */
    public const STRETCH_LOW_PERCENTILE = 0.02;
    public const STRETCH_HIGH_PERCENTILE = 0.98;
    public const STRETCH_HEADROOM = 0.88;
    public const STRETCH_HEADROOM_600DPI = 0.95;

    /** Unsharp Mask für Augen, Wimpern, Nasenflügel, Lippen, Locken */
    public const UNSHARP_AMOUNT = 0.85;
    public const UNSHARP_SIGMA_MM = 0.12;

    /** Sanfte S-Kurve nach der Tonwertspreizung (Steigung Mitte 1 + k, Enden 1 - k) */
    public const S_CURVE_STRENGTH = 0.22;

    /** Lesbarkeits-Schutzsaum um Texte & Vektorsymbole über Kundenfotos */
    public const TEXT_KNOCKOUT_MM = 0.45;
    public const TEXT_KNOCKOUT_SOFT_MM = 0.15;

    /**
     * Normalisiert eine URL oder einen Pfad auf den relativen Pfad innerhalb von Storage::disk('public').
     * Blockiert unsichere Pfad-Traversals.
     */
    public static function normalizePublicRelPath(string $pathOrUrl): string
    {
        $trimmed = trim($pathOrUrl);
        if ($trimmed === '' || str_starts_with($trimmed, 'data:')) {
            return '';
        }

        $parsedPath = parse_url($trimmed, PHP_URL_PATH);
        $clean = rawurldecode(is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : $trimmed);
        $clean = str_replace('\\', '/', $clean);

        if (str_contains($clean, '..') || str_contains($clean, "\0")) {
            return '';
        }

        if (str_contains($clean, '/storage/')) {
            $clean = substr($clean, strpos($clean, '/storage/') + 9);
        } elseif (str_starts_with($clean, 'storage/')) {
            $clean = substr($clean, 8);
        }

        return ltrim($clean, '/');
    }

    /**
     * Löst für ein konfiguriertes Kundenbild den relativen Pfad zum unberührten Originalfoto (_raw)
     * auf Storage::disk('public') auf, falls vorhanden.
     */
    public static function resolveOriginalPhotoRelPath(string $configuredRelPath, array $itemConfiguration = []): string
    {
        $trimmedInput = trim($configuredRelPath);
        if ($trimmedInput === '' || str_starts_with($trimmedInput, 'data:')) {
            return $configuredRelPath;
        }

        $normRel = self::normalizePublicRelPath($trimmedInput);
        if ($normRel === '') {
            return $configuredRelPath;
        }

        $disk = Storage::disk('public');

        // a) $itemConfiguration['raw_files'][$configuredRelPath] (sowie normalisierter Schlüssel)
        $rawFiles = is_array($itemConfiguration['raw_files'] ?? null) ? $itemConfiguration['raw_files'] : [];
        foreach ([$configuredRelPath, $normRel, '/storage/' . $normRel] as $lookupKey) {
            if (!empty($rawFiles[$lookupKey]) && is_string($rawFiles[$lookupKey])) {
                $cand = self::normalizePublicRelPath($rawFiles[$lookupKey]);
                if ($cand !== '' && $disk->exists($cand)) {
                    return $cand;
                }
            }
        }

        // b) Passendes Element in logos / logos_back (inkl. front/back Sub-Arrays) -> 'raw_value'
        $logoLists = [
            $itemConfiguration['logos'] ?? null,
            $itemConfiguration['logos_back'] ?? null,
            $itemConfiguration['front']['logos'] ?? null,
            $itemConfiguration['back']['logos'] ?? null,
        ];
        foreach ($logoLists as $list) {
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $entryVal = (string) ($entry['value'] ?? '');
                $entryUrl = (string) ($entry['url'] ?? '');
                $entryNormVal = self::normalizePublicRelPath($entryVal);
                $entryNormUrl = self::normalizePublicRelPath($entryUrl);

                $matchesEntry = ($entryVal !== '' && $entryVal === $configuredRelPath)
                    || ($entryNormVal !== '' && $entryNormVal === $normRel)
                    || ($entryNormUrl !== '' && $entryNormUrl === $normRel);

                if ($matchesEntry && !empty($entry['raw_value']) && is_string($entry['raw_value'])) {
                    $cand = self::normalizePublicRelPath($entry['raw_value']);
                    if ($cand !== '' && $disk->exists($cand)) {
                        return $cand;
                    }
                }
            }
        }

        // c) Geschwisterdatei im selben Ordner: optionales Suffix _proc entfernen und <stem>_raw.(jpg|jpeg|png|webp) suchen
        $dir = dirname($normRel);
        $dirPrefix = ($dir === '.' || $dir === '' || $dir === '/') ? '' : (rtrim($dir, '/') . '/');
        $filename = (string) pathinfo($normRel, PATHINFO_FILENAME);

        if ($filename !== '' && preg_match('/_raw$/i', $filename) && $disk->exists($normRel)) {
            return $normRel;
        }

        $stem = preg_replace('/_proc$/i', '', $filename) ?? $filename;
        if ($stem !== '') {
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                $sibling = $dirPrefix . $stem . '_raw.' . $ext;
                if ($disk->exists($sibling)) {
                    return $sibling;
                }
            }
        }

        // d) Fallback für Altbestellungen
        return $configuredRelPath;
    }

    /**
     * Gibt true zurück, wenn resolveOriginalPhotoRelPath(...) auf eine existierende Datei zeigt,
     * die sich von $configuredRelPath unterscheidet bzw. auf _raw.<ext> endet.
     */
    public static function hasUntouchedOriginal(string $configuredRelPath, array $itemConfiguration = []): bool
    {
        $resolved = self::resolveOriginalPhotoRelPath($configuredRelPath, $itemConfiguration);
        $resolvedNorm = self::normalizePublicRelPath($resolved);
        if ($resolvedNorm === '' || !Storage::disk('public')->exists($resolvedNorm)) {
            return false;
        }

        $confNorm = self::normalizePublicRelPath($configuredRelPath);

        return $resolvedNorm !== $confNorm
            || (bool) preg_match('/_raw\.(jpg|jpeg|png|webp)$/i', $resolvedNorm);
    }

    /**
     * Sucht in $itemConfiguration['laser_photo_adjustments'] nach einem aktiven Studio-Eintrag
     * (!empty($adj['enabled']) && !empty($adj['edited_path'])) für das übergebene Logo-Element.
     *
     * @return array<string, mixed>|null
     */
    public static function findActivePhotoAdjustment(array $logo, array $itemConfiguration = []): ?array
    {
        $adjustments = $itemConfiguration['laser_photo_adjustments'] ?? null;
        if (!is_array($adjustments) || empty($adjustments)) {
            return null;
        }

        $disk = Storage::disk('public');
        $rawVal = trim((string) ($logo['value'] ?? ''));
        $rawUrl = trim((string) ($logo['url'] ?? $logo['src'] ?? ''));
        $normVal = self::normalizePublicRelPath($rawVal);
        $normUrl = self::normalizePublicRelPath($rawUrl);
        $origVal = $normVal !== '' ? self::normalizePublicRelPath(self::resolveOriginalPhotoRelPath($normVal, $itemConfiguration)) : '';
        $origUrl = $normUrl !== '' ? self::normalizePublicRelPath(self::resolveOriginalPhotoRelPath($normUrl, $itemConfiguration)) : '';
        $logoId = trim((string) ($logo['id'] ?? ''));
        $legacyLogoStorage = self::normalizePublicRelPath((string) ($itemConfiguration['logo_storage_path'] ?? ''));

        $candidateNorms = array_values(array_unique(array_filter([
            $normVal,
            $normUrl,
            $origVal,
            $origUrl,
            $legacyLogoStorage !== '' && $normVal === '' && $normUrl === $legacyLogoStorage ? $legacyLogoStorage : '',
        ])));

        $candidateRaws = array_values(array_unique(array_filter([
            $rawVal,
            $rawUrl,
            $logoId,
            $normVal !== '' ? basename($normVal) : '',
            $normUrl !== '' ? basename($normUrl) : '',
        ])));

        foreach ($adjustments as $fileKey => $adj) {
            if (!is_array($adj) || empty($adj['enabled']) || empty($adj['edited_path']) || !is_string($adj['edited_path'])) {
                continue;
            }

            $editedRel = self::normalizePublicRelPath($adj['edited_path']);
            if ($editedRel === '' || !$disk->exists($editedRel)) {
                continue;
            }

            $keyStr = trim((string) $fileKey);
            $normKey = self::normalizePublicRelPath($keyStr);
            $origKey = $normKey !== '' ? self::normalizePublicRelPath(self::resolveOriginalPhotoRelPath($normKey, $itemConfiguration)) : '';

            $matched = in_array($keyStr, $candidateRaws, true)
                || ($normKey !== '' && in_array($normKey, $candidateNorms, true))
                || ($origKey !== '' && in_array($origKey, $candidateNorms, true))
                || ($normKey !== '' && ($normVal !== '' && basename($normKey) === basename($normVal)))
                || ($normKey !== '' && ($normUrl !== '' && basename($normKey) === basename($normUrl)));

            if ($matched) {
                $out = $adj;
                $out['resolved_edited_rel_path'] = $editedRel;
                $out['matched_key'] = $keyStr;

                return $out;
            }
        }

        return null;
    }

    /**
     * Standard-Parameter des mathematischen Laser-Foto-Studios.
     *
     * @return array{enabled: bool, grayscale: bool, invert: bool, brightness: int, contrast: int, gamma: float, highlight_cap: int, sharpness: int, dither_mode: string, threshold: int}
     */
    public static function defaultStudioParams(bool $isDarkSurface = false, int $dpi = 600): array
    {
        return [
            'enabled'       => false,
            'grayscale'     => true,
            'invert'        => $isDarkSurface,
            'brightness'    => 0,
            'contrast'      => 15,
            'gamma'         => 1.00,
            'highlight_cap' => $isDarkSurface ? 58 : 75,
            'sharpness'     => 85,
            'dither_mode'   => 'jarvis',
            'threshold'     => 128,
        ];
    }

    /**
     * Berechnet aus dem 256-Bin Perceptual Luminanz-Histogramm (BT.709) exakt die optimalen
     * Laser-Foto-Studio-Parameter inkl. Mittelton-Gamma-Rettung und physikalischem Highlight-Deckel.
     *
     * @return array<string, mixed>
     */
    public static function computeMathematicalAutoParams(
        string $sourceAbsPath,
        bool $isDarkSurface = false,
        int $dpi = 600,
        string $materialHint = ''
    ): array {
        $fallback = self::defaultStudioParams($isDarkSurface, $dpi);
        $fallback['enabled'] = true;
        $fallback['metrics'] = [
            'p5'             => 0,
            'median'         => 128,
            'p95'            => 255,
            'mean'           => 128.0,
            'std_dev'        => 48.0,
            'is_binary_logo' => false,
        ];

        $work = self::loadOrientedGdImageFromPath($sourceAbsPath, 512, true);
        if (!$work) {
            return $fallback;
        }

        self::decodeLegacyAlphaMaskGdInPlace($work, $isDarkSurface);

        $w = imagesx($work);
        $h = imagesy($work);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($work);
        unset($work);

            return $fallback;
        }
        $totalPixels = $w * $h;

        $hist = array_fill(0, 256, 0);
        $centerHist = array_fill(0, 256, 0);
        $visibleCount = 0;
        $centerVisibleCount = 0;
        $transparentCount = 0;
        $lumSum = 0;
        $highlightPixels = 0;

        $cx0 = (int) floor($w * 0.22);
        $cx1 = (int) ceil($w * 0.78);
        $cy0 = (int) floor($h * 0.18);
        $cy1 = (int) ceil($h * 0.82);

        for ($y = 0; $y < $h; $y++) {
            $inCenterY = ($y >= $cy0 && $y <= $cy1);
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($work, $x, $y);
                if ((($c >> 24) & 0x7F) >= 115) {
                    $transparentCount++;
                    continue;
                }
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;

                // Schnelle BT.709 Ganzzahl-Luminanz: (54*R + 183*G + 19*B + 128) >> 8
                $bin = (($r * 54) + ($g * 183) + ($b * 19) + 128) >> 8;
                $hist[$bin]++;
                $visibleCount++;
                $lumSum += $bin;
                if ($bin > 215) {
                    $highlightPixels++;
                }
                if ($inCenterY && $x >= $cx0 && $x <= $cx1) {
                    $centerHist[$bin]++;
                    $centerVisibleCount++;
                }
            }
        }

        imagedestroy($work);
        unset($work);

        if ($visibleCount <= 0) {
            return $fallback;
        }

        $p5 = self::staticPercentileFromHistogram($hist, $visibleCount, 0.05);
        $p50 = self::staticPercentileFromHistogram($hist, $visibleCount, 0.50);
        $p95 = self::staticPercentileFromHistogram($hist, $visibleCount, 0.95);
        $mean = $lumSum / $visibleCount;

        $varSum = 0.0;
        for ($g = 0; $g <= 255; $g++) {
            if ($hist[$g] > 0) {
                $diff = $g - $mean;
                $varSum += $hist[$g] * $diff * $diff;
            }
        }
        $stdDev = sqrt($varSum / $visibleCount);
        $highlightShare = $highlightPixels / $visibleCount;

        // Erkennung reiner 2-Farben-Strichlogos / transparenter Freisteller-Silhouetten
        $nearBlackOrWhite = 0;
        for ($g = 0; $g <= 12; $g++) {
            $nearBlackOrWhite += $hist[$g];
        }
        for ($g = 244; $g <= 255; $g++) {
            $nearBlackOrWhite += $hist[$g];
        }
        $extremeRatio = $nearBlackOrWhite / $visibleCount;
        $histClass = self::classifyHistogram($hist, $visibleCount);
        $transparentRatio = $transparentCount / max(1, $totalPixels);
        $isTransparentSilhouette = $transparentRatio >= 0.08
            && (($p95 - $p5) <= 16 || ($extremeRatio > 0.85 && $histClass === 'silhouette'));
        $isBimodalLogo = $extremeRatio > 0.85 && $histClass === 'silhouette';
        $isBinaryLogo = $isBimodalLogo || $isTransparentSilhouette;

        // Vordergrund-/Motiv-Statistik für Porträts vor dunklem Studio-Rand (L > 35)
        $subjectCount = 0;
        $subjectSum = 0.0;
        $subjectHist = array_fill(0, 256, 0);
        for ($g = 36; $g <= 255; $g++) {
            if ($hist[$g] > 0) {
                $subjectHist[$g] = $hist[$g];
                $subjectCount += $hist[$g];
                $subjectSum += $g * $hist[$g];
            }
        }
        $subjectMean = $subjectCount > 0 ? ($subjectSum / $subjectCount) : $mean;
        $subjectMedian = $subjectCount > 0 ? self::staticPercentileFromHistogram($subjectHist, $subjectCount, 0.50) : $p50;
        $centerMedian = $centerVisibleCount > 0 ? self::staticPercentileFromHistogram($centerHist, $centerVisibleCount, 0.50) : $p50;

        $hasBrightSubjectOnDarkBorder = !$isBinaryLogo
            && $p5 <= 40
            && $subjectCount >= (int) round($visibleCount * 0.12)
            && ($highlightShare >= 0.08 || $centerMedian > ($p50 + 35));

        $effectiveMean = $hasBrightSubjectOnDarkBorder
            ? max($mean, ($subjectMean * 0.65) + ($centerMedian * 0.35))
            : $mean;

        $brightness = max(-35, min(35, (int) round((128.0 - $effectiveMean) * 0.30)));
        if ($highlightShare >= 0.12 && $p95 >= 225) {
            // Überbelichtete Stirn/Wangen niemals zusätzlich positiv ins 255-Clipping drücken
            $brightness = min(0, $brightness);
        }

        $dynamicSpan = max(1, $p95 - $p5);
        $contrastBase = (56.0 - $stdDev) * 1.15;
        if ($dynamicSpan < 90) {
            $contrastBase += (90 - $dynamicSpan) * 0.12;
        } elseif ($dynamicSpan > 220 && $stdDev > 62.0) {
            $contrastBase -= 6.0;
        }
        $contrast = $isBinaryLogo
            ? 25
            : max(-15, min(50, (int) round($contrastBase)));

        $effectiveMedian = $hasBrightSubjectOnDarkBorder
            ? max((float) $p50, ($subjectMedian * 0.65) + ($centerMedian * 0.35))
            : (float) $p50;
        if (!$isBinaryLogo && $highlightShare >= 0.12) {
            $effectiveMedian = max($effectiveMedian, 168.0 + min(52.0, $highlightShare * 135.0));
        }

        $medNorm = ($effectiveMedian + ($brightness * 0.75)) / 255.0;
        $clampedMedNorm = max(0.15, min(0.85, $medNorm));
        $targetMid = 0.50;
        $gamma = $isBinaryLogo
            ? 1.00
            : round(max(0.50, min(1.90, log($targetMid) / log($clampedMedNorm))), 2);

        $highlightCap = $isBinaryLogo
            ? 100
            : ($isDarkSurface ? ($dpi >= 600 ? 58 : 65) : ($dpi >= 600 ? 74 : 82));

        $matLower = mb_strtolower($materialHint);
        $sharpness = $isBinaryLogo
            ? 20
            : ((str_contains($matLower, 'holz') || str_contains($matLower, 'schiefer')) ? 110 : 85);

        $ditherMode = $isBinaryLogo ? 'threshold' : 'jarvis';

        return [
            'enabled'       => true,
            'grayscale'     => true,
            'invert'        => $isDarkSurface && !$isBinaryLogo,
            'brightness'    => $brightness,
            'contrast'      => $contrast,
            'gamma'         => $gamma,
            'highlight_cap' => $highlightCap,
            'sharpness'     => $sharpness,
            'dither_mode'   => $ditherMode,
            'threshold'     => 128,
            'metrics'       => [
                'p5'              => $p5,
                'median'          => $p50,
                'p95'             => $p95,
                'mean'            => round($mean, 1),
                'std_dev'         => round($stdDev, 1),
                'highlight_share' => round($highlightShare, 4),
                'is_binary_logo'  => $isBinaryLogo,
            ],
        ];
    }

    /**
     * Rendert eine schnelle Studio-Live-Vorschau im RAM (max. $maxDim px, Standard 640 px) als Base64-PNG-Data-URL
     * unter exakter Beibehaltung der Rand-Transparenz (z. B. Herz- oder Freisteller-PNGs).
     */
    public static function renderStudioPreviewDataUrl(string $sourceAbsPath, array $params, int $maxDim = 640): string
    {
        $maxDim = max(64, min(2400, $maxDim));
        $pngBytes = self::renderStudioPipelinePngBytes($sourceAbsPath, $params, $maxDim, true, true);
        if ($pngBytes === '') {
            $empty = imagecreatetruecolor(16, 16);
            imagealphablending($empty, false);
            imagesavealpha($empty, true);
            imagefill($empty, 0, 0, imagecolorallocatealpha($empty, 255, 255, 255, 127));
            ob_start();
            imagepng($empty, null, 3);
            $pngBytes = (string) ob_get_clean();
            imagedestroy($empty);
        }

        return 'data:image/png;base64,' . base64_encode($pngBytes);
    }

    /**
     * Wendet die mathematische Studio-Pipeline in optimaler Laser-Auflösung (max. 1500 px = 63,5 mm nativ bei 600 DPI) an,
     * speichert das PNG unter storage/app/public/{$targetSubDir}/lp_<sha1>.png und gibt den relativen Pfad zurück.
     */
    public static function saveStudioProcessedLaserPng(
        string $sourceAbsPath,
        array $params,
        string $targetSubDir = 'bestellungen/xtool/photo-edits'
    ): string {
        $cleanDir = trim(str_replace('\\', '/', $targetSubDir), '/');
        if ($cleanDir === '' || str_contains($cleanDir, '..') || str_contains($cleanDir, "\0")) {
            $cleanDir = 'bestellungen/xtool/photo-edits';
        }

        $sanitizedParams = self::sanitizeStudioParams($params);
        $pngBytes = self::renderStudioPipelinePngBytes($sourceAbsPath, $sanitizedParams, 1500, false, false);
        if ($pngBytes === '') {
            throw new \RuntimeException('Kundenfoto konnte für den Laser-Export nicht verarbeitet werden.');
        }

        $hash = sha1(
            $sourceAbsPath . '|'
            . (@filemtime($sourceAbsPath) ?: 0) . '|'
            . (@filesize($sourceAbsPath) ?: 0) . '|'
            . json_encode($sanitizedParams) . '|'
            . md5($pngBytes)
        );

        $relPath = $cleanDir . '/lp_' . $hash . '.png';
        $absTarget = Storage::disk('public')->path($relPath);
        $targetDir = dirname($absTarget);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }
        if (@file_put_contents($absTarget, $pngBytes) === false) {
            Storage::disk('public')->put($relPath, $pngBytes);
        }

        return $relPath;
    }

    /**
     * Validiert und normalisiert ein Studio-Parameter-Array.
     *
     * @return array{enabled: bool, grayscale: bool, invert: bool, brightness: int, contrast: int, gamma: float, highlight_cap: int, sharpness: int, dither_mode: string, threshold: int}
     */
    public static function sanitizeStudioParams(array $params): array
    {
        $rawDither = strtolower(trim((string) ($params['dither_mode'] ?? 'jarvis')));
        if ($rawDither === 'greyscale') {
            $rawDither = 'grayscale';
        }
        if (!in_array($rawDither, ['jarvis', 'stucki', 'grayscale', 'threshold'], true)) {
            $rawDither = 'jarvis';
        }

        return [
            'enabled'       => (bool) ($params['enabled'] ?? true),
            'grayscale'     => array_key_exists('grayscale', $params) ? (bool) $params['grayscale'] : true,
            'invert'        => (bool) ($params['invert'] ?? false),
            'brightness'    => max(-100, min(100, (int) round((float) ($params['brightness'] ?? 0)))),
            'contrast'      => max(-100, min(100, (int) round((float) ($params['contrast'] ?? 15)))),
            'gamma'         => round(max(0.35, min(2.50, (float) ($params['gamma'] ?? 1.00))), 2),
            'highlight_cap' => max(30, min(100, (int) round((float) ($params['highlight_cap'] ?? 75)))),
            'sharpness'     => max(0, min(200, (int) round((float) ($params['sharpness'] ?? 85)))),
            'dither_mode'   => $rawDither,
            'threshold'     => max(10, min(245, (int) round((float) ($params['threshold'] ?? 128)))),
        ];
    }

    /**
     * Lädt ein Bild direkt von Disk (ohne unnötige PHP-String-Kopie großer Megapixel-Fotos), skaliert es bei Bedarf
     * speicherschonend vorab auf $maxDimHint und korrigiert bei JPEGs automatisch die EXIF-Orientation (3, 6, 8).
     *
     * @return \GdImage|resource|null
     */
    public static function loadOrientedGdImageFromPath(string $sourceAbsPath, int $maxDimHint = 0, bool $fastSubsample = false, ?bool &$outIsJpeg = null)
    {
        if ($sourceAbsPath === '' || !@is_file($sourceAbsPath) || !function_exists('imagecreatefromstring')) {
            $outIsJpeg = false;
            return null;
        }

        $fSize = @filesize($sourceAbsPath) ?: 0;
        $img = null;
        $isJpeg = false;
        $hasExifMarker = false;

        if ($fSize > 0 && $fSize <= 2_500_000) {
            // Einzelner Datei-Read für normale Fotos <= 2,5 MB vermeidet mehrfache I/O-Roundtrips
            $rawBytes = @file_get_contents($sourceAbsPath);
            if (!is_string($rawBytes) || $rawBytes === '') {
                $outIsJpeg = false;
                return null;
            }
            $isJpeg = str_starts_with($rawBytes, "\xFF\xD8");
            $hasExifMarker = $isJpeg && str_contains(substr($rawBytes, 0, 128), "\xFF\xE1");
            $img = @imagecreatefromstring($rawBytes);
            unset($rawBytes);
        } else {
            $head = @file_get_contents($sourceAbsPath, false, null, 0, 128);
            if (!is_string($head) || $head === '') {
                $outIsJpeg = false;
                return null;
            }
            $isJpeg = str_starts_with($head, "\xFF\xD8");
            $hasExifMarker = $isJpeg && str_contains($head, "\xFF\xE1");
            if ($isJpeg && function_exists('imagecreatefromjpeg')) {
                $img = @imagecreatefromjpeg($sourceAbsPath);
            } elseif (str_starts_with($head, "\x89PNG") && function_exists('imagecreatefrompng')) {
                $img = @imagecreatefrompng($sourceAbsPath);
            } elseif (str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' && function_exists('imagecreatefromwebp')) {
                $img = @imagecreatefromwebp($sourceAbsPath);
            }
            unset($head);
            if (!$img) {
                $rawBytes = @file_get_contents($sourceAbsPath);
                if (!is_string($rawBytes) || $rawBytes === '') {
                    $outIsJpeg = false;
                    return null;
                }
                $img = @imagecreatefromstring($rawBytes);
                unset($rawBytes);
            }
        }

        $outIsJpeg = $isJpeg;
        if (!$img) {
            return null;
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);

        $angle = 0;
        if ($hasExifMarker && function_exists('exif_read_data') && function_exists('imagerotate')) {
            $exif = @exif_read_data($sourceAbsPath);
            $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
            $angle = match ($orientation) {
                3       => 180,
                6       => -90,
                8       => 90,
                default => 0,
            };
        }

        if ($maxDimHint > 0) {
            $origW = imagesx($img);
            $origH = imagesy($img);
            $maxSide = max($origW, $origH);
            if ($maxSide > $maxDimHint && ($maxDimHint <= 800 || ($origW * $origH) <= 4_500_000 || $angle !== 0)) {
                $scale = $maxDimHint / (float) $maxSide;
                $sw = max(1, (int) round($origW * $scale));
                $sh = max(1, (int) round($origH * $scale));
                $resized = imagecreatetruecolor($sw, $sh);
                if ($resized) {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
                    if ($fastSubsample && ($origW * $origH) > 1_000_000) {
                        imagecopyresized($resized, $img, 0, 0, 0, 0, $sw, $sh, $origW, $origH);
                    } else {
                        imagecopyresampled($resized, $img, 0, 0, 0, 0, $sw, $sh, $origW, $origH);
                    }
                    imagedestroy($img);
                    $img = $resized;
                    unset($resized);
                }
            }
        }

        if ($angle !== 0) {
            $rotated = @imagerotate($img, $angle, 0);
            if ($rotated) {
                imagealphablending($rotated, false);
                imagesavealpha($rotated, true);
                imagedestroy($img);
                $img = $rotated;
                unset($rotated);
            }
        }

        return $img;
    }

    /**
     * Erkennt Legacy-Schwarz-Alpha-Masken aus Altbestellungen (RGB <= 10 mit variablem Alpha-Kanal)
     * und rekonstruiert daraus in-place die echte Graustufen-Luminanz (inkl. 50 %-Alpha-Kompression bei mask_light),
     * während echt transparente Außenpixel (gdAlpha >= 126) transparent bleiben.
     */
    public static function decodeLegacyAlphaMaskGdInPlace($img, bool $isDarkSurface = false): bool
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= 0 || $h <= 0) {
            return false;
        }

        $maxRgb = 0;
        $visible = 0;
        $fullyOpaqueCount = 0;
        $strongAlphaCount = 0;
        $minAlphaSeen = 127;
        $step = ($w > 512 || $h > 512) ? 2 : 1;

        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $c = imagecolorat($img, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a >= 126) {
                    continue;
                }
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                if ($r > $maxRgb) $maxRgb = $r;
                if ($g > $maxRgb) $maxRgb = $g;
                if ($b > $maxRgb) $maxRgb = $b;
                if ($maxRgb > 10) {
                    return false;
                }
                $visible++;
                if ($a <= 6) {
                    $fullyOpaqueCount++;
                }
                if ($a < 16) {
                    $strongAlphaCount++;
                }
                if ($a < $minAlphaSeen) {
                    $minAlphaSeen = $a;
                }
            }
        }

        if ($visible <= 0) {
            return false;
        }

        // Reine 1-Bit-Vektor-Silhouette (innen opak RGB(0,0,0) mit gdAlpha <= 6) nicht als Halbton-Maske umdeuten
        if (($fullyOpaqueCount / $visible) > 0.88) {
            return false;
        }

        $isMaskDark = $isDarkSurface && (($strongAlphaCount / $visible) < 0.05) && $minAlphaSeen >= 14;
        $isHalfCompressedLight = !$isMaskDark && $minAlphaSeen >= 58;

        $alphaToLumLut = [];
        for ($a = 0; $a <= 127; $a++) {
            if ($isMaskDark) {
                $base = (123.0 - $a) / 105.0;
                $lumNorm = $base <= 0.0 ? 0.0 : pow(min(1.0, $base), 1.0 / 0.85);
            } elseif ($isHalfCompressedLight) {
                $engraveNorm = min(1.0, max(0.0, (127.0 - $a) / 63.75));
                $lumNorm = 1.0 - $engraveNorm;
            } else {
                $engrave255 = min(255.0, max(0.0, (127.0 - $a) * 2.0));
                $lumNorm = (255.0 - $engrave255) / 255.0;
            }
            $g8 = max(0, min(255, (int) round($lumNorm * 255.0)));
            $alphaToLumLut[$a] = imagecolorallocatealpha($img, $g8, $g8, $g8, 0);
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a >= 126) {
                    continue;
                }
                imagesetpixel($img, $x, $y, $alphaToLumLut[$a]);
            }
        }

        return true;
    }

    /**
     * Kontrast-S-Kurve f(t) = t - k * sin(2πt) / (2π): Mitteltöne werden gespreizt, Tiefen & Lichter weich abgerollt.
     */
    public static function applySCurve(float $t, float $strength = self::S_CURVE_STRENGTH): float
    {
        $t = max(0.0, min(1.0, $t));

        return max(0.0, min(1.0, $t - ($strength * sin(2.0 * M_PI * $t) / (2.0 * M_PI))));
    }

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
     * Bei expliziter Übergabe von $dpi >= 600 auf dunklen Oberflächen greift der 58 %-Highlight-Deckel gegen UV-Dot-Gain.
     */
    public static function densityFromLuminance(float $lum, string $polarity, ?int $dpi = null): float
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

            $cap = ($dpi !== null && $dpi >= 600) ? self::HIGHLIGHT_CAP_DARK_600DPI : self::HIGHLIGHT_CAP;
            $gamma = ($dpi !== null && $dpi >= 600) ? self::DOT_GAIN_GAMMA_600DPI : self::DOT_GAIN_GAMMA;

            return min($cap, $cap * pow($lum, $gamma) * $knee);
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

        // 32 Klassen à 8 Grauwerte: Silhouetten belegen 2..4 Klassen (überwiegend nahe 0/255), echte Halbton-Fotos >= 6 Klassen
        // bzw. >= 4 Klassen bei kontinuierlichem Tonwertverlauf (> 20 Einzelgrauwerte außerhalb der 0/255-Ränder).
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

        if ($occupiedBins >= 6) {
            return 'halftone';
        }

        if ($occupiedBins >= 4 && $distinct >= 20) {
            $extreme = 0;
            for ($g = 0; $g <= 12; $g++) {
                $extreme += (int) ($hist[$g] ?? 0);
            }
            for ($g = 244; $g <= 255; $g++) {
                $extreme += (int) ($hist[$g] ?? 0);
            }
            if (($extreme / $total) < 0.85) {
                return 'halftone';
            }
        }

        return 'silhouette';
    }

    /**
     * Rekonstruiert die Szenen-Luminanz (0..1) aus dem normalisierten Grauwert des Export-Puffers.
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
     * in-place in die physikalisch kalibrierte Laser-Bitmap um.
     * Nutzt bei >= 600 DPI auf dunklen Oberflächen automatisch den 58 %-Highlight-Deckel gegen weißes Ausbrennen.
     *
     * @return array{mean_density: float, pixels: int, p_low: float, p_high: float}
     */
    public function processHalftoneInPlace(
        $dst,
        int $x0,
        int $y0,
        int $x1,
        int $y1,
        string $polarity,
        string $encoding,
        float $pxPerMm,
        int $dpi = 600
    ): array {
        $bw = $x1 - $x0 + 1;
        $bh = $y1 - $y0 + 1;
        if ($bw <= 0 || $bh <= 0) {
            return ['mean_density' => 0.0, 'pixels' => 0, 'p_low' => 0.0, 'p_high' => 1.0];
        }

        $totalPx = $bw * $bh;
        $lumImg = imagecreatetruecolor($bw, $bh);
        $lumLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $lumLut[$g] = imagecolorallocate($lumImg, $g, $g, $g);
        }

        $decodeLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $decodeLut[$g] = (int) round(self::decodeLuminance($g, $encoding) * 255.0);
        }

        // 1. Luminanz-Rekonstruktion + Histogramm mit gepackter 1-Byte-Sichtbarkeitsmaske (0 Vollbild-PHP-Arrays)
        $visBytes = str_repeat("\x00", $totalPx);
        $hist = array_fill(0, 256, 0);
        $visible = 0;
        $lumSum = 0;
        for ($y = $y0; $y <= $y1; $y++) {
            $ly = $y - $y0;
            $rowOff = $ly * $bw;
            for ($x = $x0; $x <= $x1; $x++) {
                $c = imagecolorat($dst, $x, $y);
                if ((($c >> 24) & 0x7F) >= 126) {
                    continue;
                }
                $lx = $x - $x0;
                $l8 = $decodeLut[$c & 0xFF];
                $visBytes[$rowOff + $lx] = "\x01";
                imagesetpixel($lumImg, $lx, $ly, $lumLut[$l8]);
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
        if ($visible < $totalPx) {
            $meanCol = $lumLut[(int) round($lumSum / $visible)];
            for ($ly = 0; $ly < $bh; $ly++) {
                $rowOff = $ly * $bw;
                for ($lx = 0; $lx < $bw; $lx++) {
                    if ($visBytes[$rowOff + $lx] === "\x00") {
                        imagesetpixel($lumImg, $lx, $ly, $meanCol);
                    }
                }
            }
        }

        // 2. Perzentil-Tonwertspreizung P2..P98 + sanfte S-Kurve
        $pLow = $this->percentileFromHistogram($hist, $visible, self::STRETCH_LOW_PERCENTILE) / 255.0;
        $pHigh = $this->percentileFromHistogram($hist, $visible, self::STRETCH_HIGH_PERCENTILE) / 255.0;
        $span = $pHigh - $pLow;
        $headroom = ($polarity === self::POLARITY_WHITE_ON_DARK && $dpi >= 600)
            ? self::STRETCH_HEADROOM_600DPI
            : self::STRETCH_HEADROOM;

        $stretchLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $l = $g / 255.0;
            $t = $span >= 0.06 ? max(0.0, min(1.0, ($l - $pLow) / $span)) : $l;
            $t = self::applySCurve($t);
            if ($span >= 0.06) {
                $l = ($polarity === self::POLARITY_WHITE_ON_DARK)
                    ? $t * $headroom
                    : (1.0 - self::STRETCH_HEADROOM) + ($t * self::STRETCH_HEADROOM);
            } else {
                $l = $t;
            }
            $stretchLut[$g] = $l;
        }

        // 3. Unsharp Mask (Gauß-Unschärfe über C-Puffer)
        $blurImg = $this->buildGaussianBlurCopy($lumImg, $bw, $bh, max(0.6, self::UNSHARP_SIGMA_MM * $pxPerMm));

        // 4. + 5. Dot-Gain-Kurve (inkl. 58 % Highlight-Deckel bei >= 600 DPI auf dunklem Substrat) & Ausgabe
        $outLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $outLut[$g] = imagecolorallocatealpha($dst, $g, $g, $g, 0);
        }
        $densityLut = [];
        $outColorByQ = [];
        for ($q = 0; $q <= 1023; $q++) {
            $dVal = self::densityFromLuminance($q / 1023.0, $polarity, $dpi);
            $densityLut[$q] = $dVal;
            $outColorByQ[$q] = $outLut[self::grayFromDensity($dVal)];
        }

        $densitySum = 0.0;
        $unsharpAmt = self::UNSHARP_AMOUNT;
        for ($ly = 0; $ly < $bh; $ly++) {
            $y = $y0 + $ly;
            $rowOff = $ly * $bw;
            for ($lx = 0; $lx < $bw; $lx++) {
                if ($visBytes[$rowOff + $lx] === "\x00") {
                    continue;
                }
                $base = $stretchLut[imagecolorat($lumImg, $lx, $ly) & 0xFF];
                $blur = $blurImg ? $stretchLut[imagecolorat($blurImg, $lx, $ly) & 0xFF] : $base;
                $q = (int) ((($base + ($unsharpAmt * ($base - $blur))) * 1023.0) + 0.5);
                if ($q < 0) {
                    $q = 0;
                } elseif ($q > 1023) {
                    $q = 1023;
                }
                $densitySum += $densityLut[$q];
                imagesetpixel($dst, $x0 + $lx, $y, $outColorByQ[$q]);
            }
        }

        unset($visBytes);
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
     * Arbeitet ausschließlich mit gepackten Binär-Strings statt Vollbild-PHP-Arrays.
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
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($vectorCanvas, $x, $y);
                if ((($c >> 24) & 0x7F) < 100 && ($c & 0xFF) < 200) {
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

        $mask = str_repeat("\x00", $bw * $bh);
        for ($y = $minY; $y <= $maxY; $y++) {
            $rowOff = ($y - $by0) * $bw;
            for ($x = $minX; $x <= $maxX; $x++) {
                $c = imagecolorat($vectorCanvas, $x, $y);
                if ((($c >> 24) & 0x7F) < 100 && ($c & 0xFF) < 200) {
                    $mask[$rowOff + ($x - $bx0)] = "\x01";
                }
            }
        }

        $core = $this->dilateBinaryMask($mask, $bw, $bh, max(0, $radiusPx));
        $ring = $outer > $radiusPx ? $this->dilateBinaryMask($mask, $bw, $bh, $outer) : $core;
        unset($mask);

        $grayLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $grayLut[$g] = imagecolorallocatealpha($photoCanvas, $g, $g, $g, 0);
        }

        $knocked = 0;
        for ($ly = 0; $ly < $bh; $ly++) {
            $rowOff = $ly * $bw;
            $y = $by0 + $ly;
            for ($lx = 0; $lx < $bw; $lx++) {
                $i = $rowOff + $lx;
                if ($ring[$i] === "\x00") {
                    continue;
                }
                $x = $bx0 + $lx;
                $c = imagecolorat($photoCanvas, $x, $y);
                if ((($c >> 24) & 0x7F) >= 126) {
                    continue;
                }
                $gray = $c & 0xFF;
                $newGray = $core[$i] === "\x01" ? 255 : (($gray + 256) >> 1);
                if ($newGray !== $gray) {
                    imagesetpixel($photoCanvas, $x, $y, $grayLut[$newGray]);
                    $knocked++;
                }
            }
        }

        return $knocked;
    }

    /**
     * Echtes 12-Gewichte Jarvis-Judice-Ninke Error-Diffusion-Dithering (Divisor 48, Ausgabe 0 = Laserpunkt, 255 = Pause).
     * Transparente Pixel (gdAlpha >= 120) bleiben transparent und erhalten keinen Fehleranteil.
     */
    public function jarvisDither($grayImg, int $threshold = 128)
    {
        return $this->errorDiffusionDither12($grayImg, 'jarvis', $threshold);
    }

    /**
     * Echtes 12-Gewichte Stucki Error-Diffusion-Dithering (Divisor 42, Ausgabe 0 = Laserpunkt, 255 = Pause).
     * Transparente Pixel (gdAlpha >= 120) bleiben transparent und erhalten keinen Fehleranteil.
     */
    public function stuckiDither($grayImg, int $threshold = 128)
    {
        return $this->errorDiffusionDither12($grayImg, 'stucki', $threshold);
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

    /**
     * Gemeinsame mathematische Studio-Pipeline für Live-Vorschau (640 px) und Laser-Export (1500 px)
     * ohne Vollbild-PHP-Arrays (gepackte Binär-Strings + 3-Zeilen Rolling-Error-Dither):
     * grayscale -> brightness & contrast -> gamma -> Unsharp Mask sharpness -> invert -> highlight_cap -> dither_mode.
     */
    private static function renderStudioPipelinePngBytes(
        string $sourceAbsPath,
        array $rawParams,
        int $maxDim,
        bool $allowColorInPreview,
        bool $simulateMaterialAppearance = false
    ): string {
        static $chrLut = null;
        static $ign4096Lut = null;
        if ($chrLut === null) {
            $chrLut = [];
            for ($i = 0; $i <= 255; $i++) {
                $chrLut[$i] = chr($i);
            }
        }
        if ($ign4096Lut === null) {
            $ign4096Lut = self::getInterleavedGradientNoiseLut();
        }

        $p = self::sanitizeStudioParams($rawParams);
        $useGrayscale = $p['grayscale'];
        $ditherMode = $p['dither_mode'];
        $invert = $p['invert'];
        $simulateDarkMaterial = ($simulateMaterialAppearance && $invert);
        $cap = $p['highlight_cap'] / 100.0;
        $sharpAmount = ($p['sharpness'] / 100.0) * 1.25;
        $threshold = $p['threshold'];
        $isColorPreview = (!$useGrayscale && $allowColorInPreview && $ditherMode === 'grayscale');

        $isJpegSource = false;
        $src = self::loadOrientedGdImageFromPath($sourceAbsPath, ($maxDim <= 800 || $isColorPreview) ? $maxDim : 0, false, $isJpegSource);
        if (!$src) {
            return '';
        }

        if (!$isJpegSource) {
            self::decodeLegacyAlphaMaskGdInPlace($src, $invert);
        }

        $origW = imagesx($src);
        $origH = imagesy($src);
        if ($origW <= 0 || $origH <= 0) {
            imagedestroy($src);

            return '';
        }

        // 256er Tonwert-LUT für Brightness + Contrast (mit C1-stetigem Soft-Shoulder gegen Highlight-Clipping) + Gamma
        $bShift = ($p['brightness'] / 200.0);
        $cFactor = tan((45.0 + (40.0 * ($p['contrast'] / 100.0))) * M_PI / 180.0);
        $gamma = $p['gamma'];
        $toneLut = [];
        for ($v = 0; $v <= 255; $v++) {
            $norm = ($v / 255.0) + $bShift;
            $contrasted = 0.5 + (($norm - 0.5) * $cFactor);
            if ($contrasted < 0.08) {
                $rolled = 0.08 * (1.0 - tanh((0.08 - $contrasted) / 0.08));
            } elseif ($contrasted > 0.88) {
                $rolled = 0.88 + (0.12 * tanh(($contrasted - 0.88) / 0.12));
            } else {
                $rolled = $contrasted;
            }
            $clamped = max(0.0, min(1.0, $rolled));
            $toneLut[$v] = pow($clamped, $gamma);
        }

        $instance = new self();
        $maxSide = max($origW, $origH);
        if ($maxSide > $maxDim) {
            $scale = $maxDim / (float) $maxSide;
            $w = max(1, (int) round($origW * $scale));
            $h = max(1, (int) round($origH * $scale));
        } else {
            $w = $origW;
            $h = $origH;
        }

        // Farbiges Vorschaubild, falls grayscale=false und dither_mode=grayscale angefordert wurde
        if ($isColorPreview) {
            $work = $src;
            if ($origW !== $w || $origH !== $h) {
                $resized = imagecreatetruecolor($w, $h);
                if ($resized) {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
                    imagecopyresampled($resized, $src, 0, 0, 0, 0, $w, $h, $origW, $origH);
                    imagedestroy($src);
                    $work = $resized;
                } else {
                    $w = $origW;
                    $h = $origH;
                }
            }

            $outColor = imagecreatetruecolor($w, $h);
            imagealphablending($outColor, false);
            imagesavealpha($outColor, true);
            imagefill($outColor, 0, 0, imagecolorallocatealpha($outColor, 255, 255, 255, 127));

            $blurColor = $sharpAmount > 0.001
                ? $instance->buildGaussianBlurCopy($work, $w, $h, max(1.0, min($w, $h) / 360.0))
                : null;

            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $c = imagecolorat($work, $x, $y);
                    $a = ($c >> 24) & 0x7F;
                    if ($a >= 120) {
                        continue;
                    }
                    $rT = $toneLut[($c >> 16) & 0xFF];
                    $gT = $toneLut[($c >> 8) & 0xFF];
                    $bT = $toneLut[$c & 0xFF];
                    if ($blurColor) {
                        $bc = imagecolorat($blurColor, $x, $y);
                        $rT = max(0.0, min(1.0, $rT + $sharpAmount * ($rT - $toneLut[($bc >> 16) & 0xFF])));
                        $gT = max(0.0, min(1.0, $gT + $sharpAmount * ($gT - $toneLut[($bc >> 8) & 0xFF])));
                        $bT = max(0.0, min(1.0, $bT + $sharpAmount * ($bT - $toneLut[$bc & 0xFF])));
                    }
                    $dR = max(0.0, min(1.0, ($invert ? $rT : (1.0 - $rT)) * $cap));
                    $dG = max(0.0, min(1.0, ($invert ? $gT : (1.0 - $gT)) * $cap));
                    $dB = max(0.0, min(1.0, ($invert ? $bT : (1.0 - $bT)) * $cap));
                    if ($simulateDarkMaterial) {
                        $oR = (int) round(18.0 + ($dR * 224.0));
                        $oG = (int) round(21.0 + ($dG * 224.0));
                        $oB = (int) round(28.0 + ($dB * 222.0));
                    } else {
                        $oR = max(0, min(255, (int) round(255.0 * (1.0 - $dR))));
                        $oG = max(0, min(255, (int) round(255.0 * (1.0 - $dG))));
                        $oB = max(0, min(255, (int) round(255.0 * (1.0 - $dB))));
                    }
                    imagesetpixel($outColor, $x, $y, imagecolorallocatealpha($outColor, $oR, $oG, $oB, $a));
                }
            }
            if ($blurColor) {
                imagedestroy($blurColor);
            }
            imagedestroy($work);
            unset($work);
            ob_start();
            imagepng($outColor, null, 3);
            $bytes = (string) ob_get_clean();
            imagedestroy($outColor);

            return $bytes;
        }

        // 1. Speicherschonende Vorbereitung:
        // - Für > 4,5 MP Smartphone-Fotos: 64-Zeilen-Strip-Resampling in 1-Byte-String ($l8Bytes), sofortiges Freigeben von $src (< 45 MB Peak-RAM)
        // - Für <= 4,5 MP Fotos ohne Transparenz & grayscale=true: schneller C-Pfad direkt auf $work (IMG_FILTER_GRAYSCALE in ~11 ms)
        $totalPx = $w * $h;
        $l8Bytes = null;
        $alphaBytes = null;
        $work = null;
        $rawLumSum = 0;
        $visCnt = 0;

        if (($origW !== $w || $origH !== $h) && ($origW * $origH) > 4_500_000) {
            $l8Bytes = str_repeat("\x00", $totalPx);
            $stripMaxH = min(64, $h);
            $strip = imagecreatetruecolor($w, $stripMaxH);
            imagealphablending($strip, false);
            imagesavealpha($strip, true);
            $stripTrans = imagecolorallocatealpha($strip, 0, 0, 0, 127);

            for ($y0 = 0; $y0 < $h; $y0 += $stripMaxH) {
                $rows = min($stripMaxH, $h - $y0);
                $sy0 = (int) floor(($y0 * $origH) / $h);
                $sy1 = min($origH, max($sy0 + 1, (int) ceil((($y0 + $rows) * $origH) / $h)));
                if (!$isJpegSource) {
                    imagefilledrectangle($strip, 0, 0, $w - 1, $rows - 1, $stripTrans);
                }
                imagecopyresampled($strip, $src, 0, 0, 0, $sy0, $w, $rows, $origW, $sy1 - $sy0);
                if ($useGrayscale && function_exists('imagefilter')) {
                    @imagefilter($strip, IMG_FILTER_GRAYSCALE);
                }

                if ($isJpegSource && $useGrayscale) {
                    for ($ry = 0; $ry < $rows; $ry++) {
                        $rowOff = ($y0 + $ry) * $w;
                        for ($x = 0; $x < $w; $x++) {
                            $l8Bytes[$rowOff + $x] = $chrLut[imagecolorat($strip, $x, $ry) & 0xFF];
                        }
                    }
                    $visCnt += $rows * $w;
                } else {
                    for ($ry = 0; $ry < $rows; $ry++) {
                        $rowOff = ($y0 + $ry) * $w;
                        for ($x = 0; $x < $w; $x++) {
                            $c = imagecolorat($strip, $x, $ry);
                            $a = ($c >> 24) & 0x7F;
                            $idx = $rowOff + $x;
                            if ($a > 0) {
                                if ($alphaBytes === null) {
                                    $alphaBytes = str_repeat("\x00", $totalPx);
                                }
                                $alphaBytes[$idx] = $chrLut[$a];
                                if ($a >= 120) {
                                    continue;
                                }
                            }
                            if ($useGrayscale) {
                                $l8 = $c & 0xFF;
                            } else {
                                $l8 = intdiv((($c >> 16) & 0xFF) + (($c >> 8) & 0xFF) + ($c & 0xFF) + 1, 3);
                            }
                            $l8Bytes[$idx] = $chrLut[$l8];
                            $rawLumSum += $l8;
                            $visCnt++;
                        }
                    }
                }
            }
            if ($isJpegSource && $useGrayscale) {
                $rawLumSum = $visCnt * 128;
            }
            imagedestroy($strip);
            imagedestroy($src);
            unset($strip, $src);
        } else {
            $work = $src;
            unset($src);
            if ($origW !== $w || $origH !== $h) {
                $resized = imagecreatetruecolor($w, $h);
                if ($resized) {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
                    imagecopyresampled($resized, $work, 0, 0, 0, 0, $w, $h, $origW, $origH);
                    imagedestroy($work);
                    unset($work);
                    $work = $resized;
                    unset($resized);
                } else {
                    $w = $origW;
                    $h = $origH;
                    $totalPx = $w * $h;
                }
            }

            // Schneller Transparenz-Detektor: JPEGs sind immer 100 % opak; bei PNG/WebP Ecken + Stichprobengitter prüfen
            $mayHaveAlpha = false;
            if (!$isJpegSource) {
                $sampleStepY = max(1, $h >> 5);
                $sampleStepX = max(1, $w >> 5);
                for ($sy = 0; $sy < $h && !$mayHaveAlpha; $sy += $sampleStepY) {
                    for ($sx = 0; $sx < $w; $sx += $sampleStepX) {
                        if (((imagecolorat($work, $sx, $sy) >> 24) & 0x7F) > 0) {
                            $mayHaveAlpha = true;
                            break;
                        }
                    }
                }
                if (!$mayHaveAlpha) {
                    if (
                        ((imagecolorat($work, $w - 1, 0) >> 24) & 0x7F) > 0
                        || ((imagecolorat($work, 0, $h - 1) >> 24) & 0x7F) > 0
                        || ((imagecolorat($work, $w - 1, $h - 1) >> 24) & 0x7F) > 0
                    ) {
                        $mayHaveAlpha = true;
                    }
                }
            }

            if (!$mayHaveAlpha && $useGrayscale && function_exists('imagefilter') && $w >= 512 && $h >= 512) {
                // C-Fast-Path für opake Fotos >= 512 px: spart kompletten PHP-Pass-1 über Millionen Pixel!
                @imagefilter($work, IMG_FILTER_GRAYSCALE);
                $visCnt = $totalPx;
                $rawLumSum = $totalPx * 128;
            } else {
                $l8Bytes = str_repeat("\x00", $totalPx);
                for ($y = 0; $y < $h; $y++) {
                    $rowOff = $y * $w;
                    for ($x = 0; $x < $w; $x++) {
                        $c = imagecolorat($work, $x, $y);
                        $a = ($c >> 24) & 0x7F;
                        $idx = $rowOff + $x;
                        if ($a > 0) {
                            if ($alphaBytes === null) {
                                $alphaBytes = str_repeat("\x00", $totalPx);
                            }
                            $alphaBytes[$idx] = $chrLut[$a];
                            if ($a >= 120) {
                                continue;
                            }
                        }
                        $r = ($c >> 16) & 0xFF;
                        $g = ($c >> 8) & 0xFF;
                        $b = $c & 0xFF;
                        $l8 = $useGrayscale
                            ? ((($r * 54) + ($g * 183) + ($b * 19) + 128) >> 8)
                            : intdiv($r + $g + $b + 1, 3);
                        $l8Bytes[$idx] = $chrLut[$l8];
                        $rawLumSum += $l8;
                        $visCnt++;
                    }
                }
                imagedestroy($work);
                unset($work);
                $work = null;
            }
        }

        // 2. Unsharp Mask (immer auf volle ($w, $h) echt bilinear via imagescale(IMG_BILINEAR_FIXED) hochskaliertes $blurImg -> 0 Blockkanten!)
        $blurImg = null;
        if ($sharpAmount > 0.001 && $visCnt > 0 && function_exists('imagefilter')) {
            $sigmaPx = max(1.0, min($w, $h) / 360.0);
            if ($work !== null && $alphaBytes === null) {
                $blurImg = $instance->buildGaussianBlurCopy($work, $w, $h, $sigmaPx);
            } elseif ($alphaBytes === null && $w > 360 && $h > 360) {
                $factor = max(1.25, $sigmaPx / 0.85);
                $sw = max(4, (int) round($w / $factor));
                $sh = max(4, (int) round($h / $factor));
                $small = imagecreatetruecolor($sw, $sh);
                if ($small) {
                    $sPal = [];
                    for ($g = 0; $g <= 255; $g++) {
                        $sPal[$g] = imagecolorallocate($small, $g, $g, $g);
                    }
                    $stepX = $w / (float) $sw;
                    $stepY = $h / (float) $sh;
                    for ($sy = 0; $sy < $sh; $sy++) {
                        $y0s = min($h - 1, (int) ($sy * $stepY));
                        $y1s = min($h - 1, (int) (($sy + 0.5) * $stepY));
                        $r0 = $y0s * $w;
                        $r1s = $y1s * $w;
                        for ($sx = 0; $sx < $sw; $sx++) {
                            $x0s = min($w - 1, (int) ($sx * $stepX));
                            $x1s = min($w - 1, (int) (($sx + 0.5) * $stepX));
                            $avg = (ord($l8Bytes[$r0 + $x0s]) + ord($l8Bytes[$r0 + $x1s]) + ord($l8Bytes[$r1s + $x0s]) + ord($l8Bytes[$r1s + $x1s]) + 2) >> 2;
                            imagesetpixel($small, $sx, $sy, $sPal[$avg]);
                        }
                    }
                    @imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
                    @imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
                    if (function_exists('imagescale')) {
                        $scaled = @imagescale($small, $w, $h, IMG_BILINEAR_FIXED);
                        if ($scaled !== false) {
                            $blurImg = $scaled;
                        }
                    }
                    if (!$blurImg) {
                        $blurImg = imagecreatetruecolor($w, $h);
                        if ($blurImg) {
                            imagecopyresampled($blurImg, $small, 0, 0, 0, 0, $w, $h, $sw, $sh);
                            @imagefilter($blurImg, IMG_FILTER_GAUSSIAN_BLUR);
                        }
                    }
                    imagedestroy($small);
                    unset($small);
                }
            } else {
                $meanRawG = ($visCnt < $totalPx) ? max(0, min(255, (int) round($rawLumSum / $visCnt))) : 128;
                $lumImg = imagecreatetruecolor($w, $h);
                if ($lumImg) {
                    $lumPalette = [];
                    for ($g = 0; $g <= 255; $g++) {
                        $lumPalette[$g] = imagecolorallocate($lumImg, $g, $g, $g);
                    }
                    if ($alphaBytes !== null && $visCnt < $totalPx) {
                        imagefill($lumImg, 0, 0, $lumPalette[$meanRawG]);
                    }
                    for ($y = 0; $y < $h; $y++) {
                        $rowOff = $y * $w;
                        for ($x = 0; $x < $w; $x++) {
                            $idx = $rowOff + $x;
                            if ($alphaBytes !== null && ord($alphaBytes[$idx]) >= 120) {
                                continue;
                            }
                            imagesetpixel($lumImg, $x, $y, $lumPalette[ord($l8Bytes[$idx])]);
                        }
                    }
                    $blurImg = $instance->buildGaussianBlurCopy($lumImg, $w, $h, $sigmaPx);
                    imagedestroy($lumImg);
                    unset($lumImg);
                }
            }
        }

        // Algebraisch vorberechnete 8-Bit-Unsharp-LUTs: base + amt*(base - blur) === (1+amt)*base - amt*blur
        $sharpBaseLut = [];
        $sharpBlurLut = [];
        if ($blurImg !== null) {
            $onePlusSharp = 1.0 + $sharpAmount;
            for ($v = 0; $v <= 255; $v++) {
                $tVal = $toneLut[$v];
                $sharpBaseLut[$v] = $onePlusSharp * $tVal;
                $sharpBlurLut[$v] = $sharpAmount * $tVal;
            }
        }

        // 3. + 4. Fusionierte Polarität/Highlight-Cap + Serpentine-Boustrophedon-Ausgabe (+ optionale Rohlings-Optik)
        $isErrorDiff = ($ditherMode === 'jarvis' || $ditherMode === 'stucki');
        $finalImg = imagecreatetruecolor($w, $h);
        imagealphablending($finalImg, false);
        imagesavealpha($finalImg, true);
        $trans = imagecolorallocatealpha($finalImg, 255, 255, 255, 127);

        if ($simulateDarkMaterial) {
            // Rohlingsechte Vorschau für dunkles Eloxal/Schiefer: Laser feuert (0) -> Silber-Weiß, Laser pausiert (255) -> Anthrazit
            $fireCol = imagecolorallocatealpha($finalImg, 242, 245, 250, 0);
            $pauseCol = imagecolorallocatealpha($finalImg, 18, 21, 28, 0);
        } else {
            // xTool-Laser-Konvention (bzw. heller Rohling): 0 = Schwarz (Laser feuert), 255 = Weiß (Laser pausiert)
            $fireCol = imagecolorallocatealpha($finalImg, 0, 0, 0, 0);
            $pauseCol = imagecolorallocatealpha($finalImg, 255, 255, 255, 0);
        }

        $graySlope = $invert ? -($cap * 255.0) : ($cap * 255.0);
        $grayOffset = $invert ? 255.5 : ((255.0 * (1.0 - $cap)) + 0.5);

        $noSharpGrayLut = [];
        for ($v = 0; $v <= 255; $v++) {
            $bVal = $toneLut[$v];
            $rawD = $invert ? $bVal : (1.0 - $bVal);
            if ($ditherMode === 'threshold') {
                $noSharpGrayLut[$v] = ((int) round(255.0 * (1.0 - $rawD))) < $threshold ? 0 : 255;
            } else {
                $capD = max(0.0, min($cap, $rawD * $cap));
                $og = max(0, min(255, (int) round(255.0 * (1.0 - $capD))));
                $noSharpGrayLut[$v] = ($isErrorDiff && $og >= 246) ? 255 : $og;
            }
        }

        if ($isErrorDiff) {
            $thrFloat = (float) max(1, min(254, $threshold));
            $isStucki = ($ditherMode === 'stucki');
            $invDiv = $isStucki ? (1.0 / 42.0) : (1.0 / 48.0);
            $zeroRow = array_fill(0, $w + 4, 0.0);
            $err0 = $zeroRow;
            $err1 = $zeroRow;
            $err2 = $zeroRow;

            if ($alphaBytes === null) {
                imagesavealpha($finalImg, false);
                imagefill($finalImg, 0, 0, $pauseCol);

                $div = $isStucki ? 42 : 48;
                $invDivEps = (1.0 + 1e-11) / (float) $div;
                $thrScaled = max(1, min(254, $threshold)) * $div;
                $pauseScaled = 255 * $div;
                $hasWorkGd = ($work !== null);
                $hasBlurGd = ($blurImg !== null);

                $sharpBaseIntLut = [];
                $sharpBlurIntLut = [];
                $q1024ToScaledLut = [];
                $q1024JitAmpLut = [];
                $guaranteedPauseLut = [];
                $noSharpScaledLut = [];
                $noSharpJitAmpLut = [];

                if ($hasBlurGd) {
                    $onePlusSharp = 1.0 + $sharpAmount;
                    for ($v = 0; $v <= 255; $v++) {
                        $tVal = $toneLut[$v];
                        $sharpBaseIntLut[$v] = (int) round($onePlusSharp * $tVal * 1023.0);
                        $sharpBlurIntLut[$v] = (int) round($sharpAmount * $tVal * 1023.0);
                    }
                    for ($q = 0; $q <= 1023; $q++) {
                        $og = (int) ($grayOffset + (($q / 1023.0) * $graySlope));
                        if ($og < 0) {
                            $og = 0;
                        } elseif ($og >= 246) {
                            $og = 255;
                        }
                        $q1024ToScaledLut[$q] = $og * $div;
                        $distEdge = min($og, 255 - $og) / 127.5;
                        $q1024JitAmpLut[$q] = (int) round(52.0 * $distEdge * $div);
                    }
                    $minBlurInt = min($sharpBlurIntLut[0], $sharpBlurIntLut[255]);
                    $maxBlurInt = max($sharpBlurIntLut[0], $sharpBlurIntLut[255]);
                    for ($v = 0; $v <= 255; $v++) {
                        $qLo = max(0, min(1023, $sharpBaseIntLut[$v] - $maxBlurInt));
                        $qHi = max(0, min(1023, $sharpBaseIntLut[$v] - $minBlurInt));
                        $guaranteedPauseLut[$v] = ($q1024ToScaledLut[$qLo] === $pauseScaled && $q1024ToScaledLut[$qHi] === $pauseScaled);
                    }
                } else {
                    for ($v = 0; $v <= 255; $v++) {
                        $og = $noSharpGrayLut[$v];
                        $sc = $og * $div;
                        $noSharpScaledLut[$v] = $sc;
                        $distEdge = min($og, 255 - $og) / 127.5;
                        $noSharpJitAmpLut[$v] = (int) round(52.0 * $distEdge * $div);
                        $guaranteedPauseLut[$v] = ($sc === $pauseScaled);
                    }
                }
                $q0Scaled = $hasBlurGd ? $q1024ToScaledLut[0] : 0;
                $q1023Scaled = $hasBlurGd ? $q1024ToScaledLut[1023] : $pauseScaled;
                $q0JitAmp = $hasBlurGd ? $q1024JitAmpLut[0] : 0;
                $q1023JitAmp = $hasBlurGd ? $q1024JitAmpLut[1023] : 0;

                $pauseMaxG = -1;
                for ($v = 0; $v <= 255; $v++) {
                    if ($guaranteedPauseLut[$v]) {
                        $pauseMaxG = $v;
                    } else {
                        break;
                    }
                }
                $pauseMaxCol = $pauseMaxG >= 0 ? (($pauseMaxG << 16) | ($pauseMaxG << 8) | $pauseMaxG) : -1;

                $zeroIntRow = array_fill(0, $w + 4, 0);
                $e0 = $zeroIntRow;
                $e1 = $zeroIntRow;
                $e2 = $zeroIntRow;
                $e0Active = false;
                $e1Active = false;
                $e2Active = false;

                for ($y = 0; $y < $h; $y++) {
                    $rowOff = $y * $w;
                    $yIgn = $y * 383;
                    $r1 = 0;
                    $r2 = 0;
                    $b0 = 0; $b1 = 0; $b2 = 0; $b3 = 0;
                    $c0 = 0; $c1 = 0; $c2 = 0; $c3 = 0;
                    $hasCarry = false;

                    if (($y & 1) === 0) {
                        // Gerade Zeile: Links -> Rechts (0 .. w-1)
                        for ($x = 0; $x < $w; $x++) {
                            $ix = $x + 2;
                            $g8 = $hasWorkGd ? (imagecolorat($work, $x, $y) & 0xFF) : ord($l8Bytes[$rowOff + $x]);
                            if (!$hasCarry && (!$e0Active || $e0[$ix] === 0) && $guaranteedPauseLut[$g8]) {
                                if ($hasWorkGd) {
                                    if (!$e0Active && $pauseMaxCol >= 0) {
                                        while (($x + 1) < $w && imagecolorat($work, $x + 1, $y) <= $pauseMaxCol) {
                                            $x++;
                                        }
                                    } else {
                                        while (($x + 1) < $w && (!$e0Active || $e0[$x + 3] === 0) && $guaranteedPauseLut[imagecolorat($work, $x + 1, $y) & 0xFF]) {
                                            $x++;
                                        }
                                    }
                                } else {
                                    if (!$e0Active && $pauseMaxG >= 0) {
                                        while (($x + 1) < $w && ord($l8Bytes[$rowOff + $x + 1]) <= $pauseMaxG) {
                                            $x++;
                                        }
                                    } else {
                                        while (($x + 1) < $w && (!$e0Active || $e0[$x + 3] === 0) && $guaranteedPauseLut[ord($l8Bytes[$rowOff + $x + 1])]) {
                                            $x++;
                                        }
                                    }
                                }
                                continue;
                            }

                            if ($hasBlurGd) {
                                $q = $sharpBaseIntLut[$g8] - $sharpBlurIntLut[imagecolorat($blurImg, $x, $y) & 0xFF];
                                if ($q <= 0) {
                                    $outScaled = $q0Scaled;
                                    $jitAmp = $q0JitAmp;
                                } elseif ($q >= 1023) {
                                    $outScaled = $q1023Scaled;
                                    $jitAmp = $q1023JitAmp;
                                } else {
                                    $outScaled = $q1024ToScaledLut[$q];
                                    $jitAmp = $q1024JitAmpLut[$q];
                                }
                            } else {
                                $outScaled = $noSharpScaledLut[$g8];
                                $jitAmp = $noSharpJitAmpLut[$g8];
                            }

                            $old = $outScaled + ($e0Active ? $e0[$ix] : 0) + $r1;
                            $jit = ($ign4096Lut[((($x * 4399) + $yIgn) >> 4) & 4095] * $jitAmp) >> 9;
                            if (($old + $jit) < $thrScaled) {
                                $new = 0;
                                imagesetpixel($finalImg, $x, $y, $fireCol);
                            } else {
                                $new = $pauseScaled;
                            }

                            $er = (int) (($old - $new) * $invDivEps);
                            if ($er === 0) {
                                if ($hasCarry) {
                                    $r1 = $r2;
                                    $r2 = 0;
                                    $e1[$ix - 2] += $b0;
                                    $b0 = $b1; $b1 = $b2; $b2 = $b3; $b3 = 0;
                                    $e2[$ix - 2] = $c0;
                                    $c0 = $c1; $c1 = $c2; $c2 = $c3; $c3 = 0;
                                    if (($r1 | $b0 | $b1 | $b2 | $c0 | $c1 | $c2) === 0) {
                                        $hasCarry = false;
                                    }
                                }
                                continue;
                            }

                            $hasCarry = true;
                            $e1Active = true;
                            $e2Active = true;
                            if ($isStucki) {
                                $e2w = $er * 2; $e4w = $er * 4; $e8w = $er * 8;
                                $r1 = $r2 + $e8w;
                                $r2 = $e4w;
                                $e1[$ix - 2] += $b0 + $e2w;
                                $b0 = $b1 + $e4w; $b1 = $b2 + $e8w; $b2 = $b3 + $e4w; $b3 = $e2w;
                                $e2[$ix - 2] = $c0 + $er;
                                $c0 = $c1 + $e2w; $c1 = $c2 + $e4w; $c2 = $c3 + $e2w; $c3 = $er;
                            } else {
                                $e3w = $er * 3; $e5w = $er * 5; $e7w = $er * 7;
                                $r1 = $r2 + $e7w;
                                $r2 = $e5w;
                                $e1[$ix - 2] += $b0 + $e3w;
                                $b0 = $b1 + $e5w; $b1 = $b2 + $e7w; $b2 = $b3 + $e5w; $b3 = $e3w;
                                $e2[$ix - 2] = $c0 + $er;
                                $c0 = $c1 + $e3w; $c1 = $c2 + $e5w; $c2 = $c3 + $e3w; $c3 = $er;
                            }
                        }

                        if ($hasCarry) {
                            $e1[$w] += $b0; $e1[$w + 1] += $b1; $e1[$w + 2] += $b2; $e1[$w + 3] += $b3;
                            $e2[$w] = $c0;  $e2[$w + 1] = $c1;  $e2[$w + 2] = $c2;  $e2[$w + 3] = $c3;
                        }
                    } else {
                        // Ungerade Zeile: Serpentine Rechts -> Links (w-1 .. 0), horizontale Gewichte gespiegelt nach links
                        for ($x = $w - 1; $x >= 0; $x--) {
                            $ix = $x + 2;
                            $g8 = $hasWorkGd ? (imagecolorat($work, $x, $y) & 0xFF) : ord($l8Bytes[$rowOff + $x]);
                            if (!$hasCarry && (!$e0Active || $e0[$ix] === 0) && $guaranteedPauseLut[$g8]) {
                                if ($hasWorkGd) {
                                    if (!$e0Active && $pauseMaxCol >= 0) {
                                        while ($x > 0 && imagecolorat($work, $x - 1, $y) <= $pauseMaxCol) {
                                            $x--;
                                        }
                                    } else {
                                        while ($x > 0 && (!$e0Active || $e0[$x + 1] === 0) && $guaranteedPauseLut[imagecolorat($work, $x - 1, $y) & 0xFF]) {
                                            $x--;
                                        }
                                    }
                                } else {
                                    if (!$e0Active && $pauseMaxG >= 0) {
                                        while ($x > 0 && ord($l8Bytes[$rowOff + $x - 1]) <= $pauseMaxG) {
                                            $x--;
                                        }
                                    } else {
                                        while ($x > 0 && (!$e0Active || $e0[$x + 1] === 0) && $guaranteedPauseLut[ord($l8Bytes[$rowOff + $x - 1])]) {
                                            $x--;
                                        }
                                    }
                                }
                                continue;
                            }

                            if ($hasBlurGd) {
                                $q = $sharpBaseIntLut[$g8] - $sharpBlurIntLut[imagecolorat($blurImg, $x, $y) & 0xFF];
                                if ($q <= 0) {
                                    $outScaled = $q0Scaled;
                                    $jitAmp = $q0JitAmp;
                                } elseif ($q >= 1023) {
                                    $outScaled = $q1023Scaled;
                                    $jitAmp = $q1023JitAmp;
                                } else {
                                    $outScaled = $q1024ToScaledLut[$q];
                                    $jitAmp = $q1024JitAmpLut[$q];
                                }
                            } else {
                                $outScaled = $noSharpScaledLut[$g8];
                                $jitAmp = $noSharpJitAmpLut[$g8];
                            }

                            $old = $outScaled + ($e0Active ? $e0[$ix] : 0) + $r1;
                            $jit = ($ign4096Lut[((($x * 4399) + $yIgn) >> 4) & 4095] * $jitAmp) >> 9;
                            if (($old + $jit) < $thrScaled) {
                                $new = 0;
                                imagesetpixel($finalImg, $x, $y, $fireCol);
                            } else {
                                $new = $pauseScaled;
                            }

                            $er = (int) (($old - $new) * $invDivEps);
                            if ($er === 0) {
                                if ($hasCarry) {
                                    $r1 = $r2;
                                    $r2 = 0;
                                    $e1[$ix + 2] += $b0;
                                    $b0 = $b1; $b1 = $b2; $b2 = $b3; $b3 = 0;
                                    $e2[$ix + 2] = $c0;
                                    $c0 = $c1; $c1 = $c2; $c2 = $c3; $c3 = 0;
                                    if (($r1 | $b0 | $b1 | $b2 | $c0 | $c1 | $c2) === 0) {
                                        $hasCarry = false;
                                    }
                                }
                                continue;
                            }

                            $hasCarry = true;
                            $e1Active = true;
                            $e2Active = true;
                            if ($isStucki) {
                                $e2w = $er * 2; $e4w = $er * 4; $e8w = $er * 8;
                                $r1 = $r2 + $e8w;
                                $r2 = $e4w;
                                $e1[$ix + 2] += $b0 + $e2w;
                                $b0 = $b1 + $e4w; $b1 = $b2 + $e8w; $b2 = $b3 + $e4w; $b3 = $e2w;
                                $e2[$ix + 2] = $c0 + $er;
                                $c0 = $c1 + $e2w; $c1 = $c2 + $e4w; $c2 = $c3 + $e2w; $c3 = $er;
                            } else {
                                $e3w = $er * 3; $e5w = $er * 5; $e7w = $er * 7;
                                $r1 = $r2 + $e7w;
                                $r2 = $e5w;
                                $e1[$ix + 2] += $b0 + $e3w;
                                $b0 = $b1 + $e5w; $b1 = $b2 + $e7w; $b2 = $b3 + $e5w; $b3 = $e3w;
                                $e2[$ix + 2] = $c0 + $er;
                                $c0 = $c1 + $e3w; $c1 = $c2 + $e5w; $c2 = $c3 + $e3w; $c3 = $er;
                            }
                        }

                        if ($hasCarry) {
                            $e1[3] += $b0; $e1[2] += $b1; $e1[1] += $b2; $e1[0] += $b3;
                            $e2[3] = $c0;  $e2[2] = $c1;  $e2[1] = $c2;  $e2[0] = $c3;
                        }
                    }

                    $e0 = $e1;
                    $e1 = $e2;
                    $e2 = $e2Active ? $zeroIntRow : $e2;
                    $e0Active = $e1Active;
                    $e1Active = $e2Active;
                    $e2Active = false;
                }
                unset($zeroIntRow, $e0, $e1, $e2);
            } else {
                imagefill($finalImg, 0, 0, $trans);
                $alphaFireLut = [$fireCol];
                $alphaPauseLut = [$pauseCol];
                for ($a = 1; $a <= 127; $a++) {
                    if ($simulateDarkMaterial) {
                        $alphaFireLut[$a] = imagecolorallocatealpha($finalImg, 242, 245, 250, $a);
                        $alphaPauseLut[$a] = imagecolorallocatealpha($finalImg, 18, 21, 28, $a);
                    } else {
                        $alphaFireLut[$a] = imagecolorallocatealpha($finalImg, 0, 0, 0, $a);
                        $alphaPauseLut[$a] = imagecolorallocatealpha($finalImg, 255, 255, 255, $a);
                    }
                }

                $jitScaleFloat = 52.0 / (127.5 * 512.0);
                for ($y = 0; $y < $h; $y++) {
                    $rowOff = $y * $w;
                    $yIgn = $y * 383;
                    $evenRow = (($y & 1) === 0);
                    $xStart = $evenRow ? 0 : ($w - 1);
                    $xEnd = $evenRow ? $w : -1;
                    $xStep = $evenRow ? 1 : -1;
                    $hOff1 = $evenRow ? 1 : -1;
                    $hOff2 = $evenRow ? 2 : -2;

                    for ($x = $xStart; $x !== $xEnd; $x += $xStep) {
                        $idx = $rowOff + $x;
                        $a = ord($alphaBytes[$idx]);
                        if ($a >= 120) {
                            continue;
                        }
                        $g8 = ord($l8Bytes[$idx]);
                        if ($blurImg !== null) {
                            $shVal = $sharpBaseLut[$g8] - $sharpBlurLut[imagecolorat($blurImg, $x, $y) & 0xFF];
                            $shVal = $shVal < 0.0 ? 0.0 : ($shVal > 1.0 ? 1.0 : $shVal);
                            $outGray = (int) ($grayOffset + ($shVal * $graySlope));
                            if ($outGray >= 246) {
                                $outGray = 255;
                            } elseif ($outGray < 0) {
                                $outGray = 0;
                            }
                        } else {
                            $outGray = $noSharpGrayLut[$g8];
                        }

                        $ix = $x + 2;
                        $old = $outGray + $err0[$ix];
                        $distEdge = min($outGray, 255 - $outGray);
                        $jitter = ($ign4096Lut[((($x * 4399) + $yIgn) >> 4) & 4095] * $distEdge) * $jitScaleFloat;
                        if (($old + $jitter) < $thrFloat) {
                            $new = 0.0;
                            imagesetpixel($finalImg, $x, $y, $alphaFireLut[$a]);
                        } else {
                            $new = 255.0;
                            imagesetpixel($finalImg, $x, $y, $alphaPauseLut[$a]);
                        }
                        $err = ($old - $new) * $invDiv;
                        if ($err != 0.0) {
                            if ($isStucki) {
                                $e2 = $err * 2.0; $e4 = $err * 4.0; $e8 = $err * 8.0;
                                $err0[$ix + $hOff1] += $e8; $err0[$ix + $hOff2] += $e4;
                                $err1[$ix - 2] += $e2; $err1[$ix - 1] += $e4; $err1[$ix] += $e8; $err1[$ix + 1] += $e4; $err1[$ix + 2] += $e2;
                                $err2[$ix - 2] += $err; $err2[$ix - 1] += $e2; $err2[$ix] += $e4; $err2[$ix + 1] += $e2; $err2[$ix + 2] += $err;
                            } else {
                                $e3 = $err * 3.0; $e5 = $err * 5.0; $e7 = $err * 7.0;
                                $err0[$ix + $hOff1] += $e7; $err0[$ix + $hOff2] += $e5;
                                $err1[$ix - 2] += $e3; $err1[$ix - 1] += $e5; $err1[$ix] += $e7; $err1[$ix + 1] += $e5; $err1[$ix + 2] += $e3;
                                $err2[$ix - 2] += $err; $err2[$ix - 1] += $e3; $err2[$ix] += $e5; $err2[$ix + 1] += $e3; $err2[$ix + 2] += $err;
                            }
                        }
                    }
                    $err0 = $err1;
                    $err1 = $err2;
                    $err2 = $zeroRow;
                }
            }
            unset($zeroRow, $err0, $err1, $err2);
        } else {
            $opaqueGrayLut = [];
            $simRLut = [];
            $simGLut = [];
            $simBLut = [];
            for ($g = 0; $g <= 255; $g++) {
                if ($simulateDarkMaterial) {
                    $ablation = (255 - $g) / 255.0;
                    $rSim = (int) round(18.0 + ($ablation * 224.0));
                    $gSim = (int) round(21.0 + ($ablation * 224.0));
                    $bSim = (int) round(28.0 + ($ablation * 222.0));
                    $simRLut[$g] = $rSim;
                    $simGLut[$g] = $gSim;
                    $simBLut[$g] = $bSim;
                    $opaqueGrayLut[$g] = imagecolorallocatealpha($finalImg, $rSim, $gSim, $bSim, 0);
                } else {
                    $opaqueGrayLut[$g] = imagecolorallocatealpha($finalImg, $g, $g, $g, 0);
                }
            }
            imagefill($finalImg, 0, 0, $alphaBytes === null ? $pauseCol : $trans);
            $isThreshold = ($ditherMode === 'threshold');
            $thrSlope = $invert ? -255.0 : 255.0;
            $thrOffset = $invert ? 255.5 : 0.5;

            for ($y = 0; $y < $h; $y++) {
                $rowOff = $y * $w;
                for ($x = 0; $x < $w; $x++) {
                    $idx = $rowOff + $x;
                    $a = $alphaBytes !== null ? ord($alphaBytes[$idx]) : 0;
                    if ($a >= 120) {
                        continue;
                    }
                    $g8 = $work !== null ? (imagecolorat($work, $x, $y) & 0xFF) : ord($l8Bytes[$idx]);
                    if ($blurImg !== null) {
                        $shVal = $sharpBaseLut[$g8] - $sharpBlurLut[imagecolorat($blurImg, $x, $y) & 0xFF];
                        $shVal = $shVal < 0.0 ? 0.0 : ($shVal > 1.0 ? 1.0 : $shVal);
                        if ($isThreshold) {
                            $outGray = ((int) ($thrOffset + ($shVal * $thrSlope))) < $threshold ? 0 : 255;
                        } else {
                            $outGray = max(0, min(255, (int) ($grayOffset + ($shVal * $graySlope))));
                        }
                    } else {
                        $outGray = $noSharpGrayLut[$g8];
                    }

                    if ($a > 0) {
                        if ($simulateDarkMaterial) {
                            imagesetpixel($finalImg, $x, $y, imagecolorallocatealpha($finalImg, $simRLut[$outGray], $simGLut[$outGray], $simBLut[$outGray], $a));
                        } else {
                            imagesetpixel($finalImg, $x, $y, imagecolorallocatealpha($finalImg, $outGray, $outGray, $outGray, $a));
                        }
                    } elseif (!$isThreshold || $outGray === 0) {
                        imagesetpixel($finalImg, $x, $y, $opaqueGrayLut[$outGray]);
                    }
                }
            }
        }

        if ($work !== null) {
            imagedestroy($work);
        }
        unset($work);
        if ($blurImg !== null) {
            imagedestroy($blurImg);
            $blurImg = null;
        }
        unset($l8Bytes, $alphaBytes);

        ob_start();
        if ($isErrorDiff || $ditherMode === 'threshold') {
            imagepng($finalImg, null, 1, PNG_FILTER_NONE);
        } else {
            imagepng($finalImg, null, 3);
        }
        $pngBytes = (string) ob_get_clean();
        imagedestroy($finalImg);
        unset($finalImg);

        return $pngBytes;
    }

    /**
     * Hochperformante 12-Nachbarn Error-Diffusion-Engine (Jarvis Divisor 48 / Stucki Divisor 42)
     * mit Serpentine-Boustrophedon-Scan, deterministischem Mikro-Jitter und 3-Zeilen Rolling-Error-Buffer.
     */
    private function errorDiffusionDither12($grayImg, string $mode, int $threshold = 128)
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

        $alphaBlackLut = [$black];
        $alphaWhiteLut = [$white];
        for ($a = 1; $a <= 127; $a++) {
            $alphaBlackLut[$a] = imagecolorallocatealpha($out, 0, 0, 0, $a);
            $alphaWhiteLut[$a] = imagecolorallocatealpha($out, 255, 255, 255, $a);
        }

        $thrFloat = (float) max(1, min(254, $threshold));
        $isStucki = ($mode === 'stucki');
        $invDiv = $isStucki ? (1.0 / 42.0) : (1.0 / 48.0);
        $ign4096Lut = self::getInterleavedGradientNoiseLut();
        $jitScaleFloat = 52.0 / (127.5 * 512.0);

        $zeroRow = array_fill(0, $w + 4, 0.0);
        $err0 = $zeroRow;
        $err1 = $zeroRow;
        $err2 = $zeroRow;

        for ($y = 0; $y < $h; $y++) {
            $yIgn = $y * 383;
            $evenRow = (($y & 1) === 0);
            $xStart = $evenRow ? 0 : ($w - 1);
            $xEnd = $evenRow ? $w : -1;
            $xStep = $evenRow ? 1 : -1;
            $hOff1 = $evenRow ? 1 : -1;
            $hOff2 = $evenRow ? 2 : -2;

            for ($x = $xStart; $x !== $xEnd; $x += $xStep) {
                $c = imagecolorat($grayImg, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a >= 120) {
                    continue;
                }
                $g8 = $c & 0xFF;
                $ix = $x + 2;
                $old = (float) $g8 + $err0[$ix];
                $distEdge = min($g8, 255 - $g8);
                $jitter = ($ign4096Lut[((($x * 4399) + $yIgn) >> 4) & 4095] * $distEdge) * $jitScaleFloat;
                if (($old + $jitter) < $thrFloat) {
                    $new = 0.0;
                    imagesetpixel($out, $x, $y, $alphaBlackLut[$a]);
                } else {
                    $new = 255.0;
                    imagesetpixel($out, $x, $y, $alphaWhiteLut[$a]);
                }
                $err = ($old - $new) * $invDiv;
                if ($err == 0.0) {
                    continue;
                }
                if ($isStucki) {
                    $e2 = $err * 2.0;
                    $e4 = $err * 4.0;
                    $e8 = $err * 8.0;
                    $err0[$ix + $hOff1] += $e8;
                    $err0[$ix + $hOff2] += $e4;
                    $err1[$ix - 2] += $e2;
                    $err1[$ix - 1] += $e4;
                    $err1[$ix]     += $e8;
                    $err1[$ix + 1] += $e4;
                    $err1[$ix + 2] += $e2;
                    $err2[$ix - 2] += $err;
                    $err2[$ix - 1] += $e2;
                    $err2[$ix]     += $e4;
                    $err2[$ix + 1] += $e2;
                    $err2[$ix + 2] += $err;
                } else {
                    $e3 = $err * 3.0;
                    $e5 = $err * 5.0;
                    $e7 = $err * 7.0;
                    $err0[$ix + $hOff1] += $e7;
                    $err0[$ix + $hOff2] += $e5;
                    $err1[$ix - 2] += $e3;
                    $err1[$ix - 1] += $e5;
                    $err1[$ix]     += $e7;
                    $err1[$ix + 1] += $e5;
                    $err1[$ix + 2] += $e3;
                    $err2[$ix - 2] += $err;
                    $err2[$ix - 1] += $e3;
                    $err2[$ix]     += $e5;
                    $err2[$ix + 1] += $e3;
                    $err2[$ix + 2] += $err;
                }
            }
            $err0 = $err1;
            $err1 = $err2;
            $err2 = $zeroRow;
        }

        return $out;
    }

    /**
     * Liefert die einmalig vorberechnete 4096-Stufen Interleaved-Gradient-Noise-Tabelle (Jorge Jimenez 2014)
     * in Festkomma-Skalierung [-256 .. +256] gegen gerichtete Error-Diffusion-Wurmstreifen.
     *
     * @return array<int, int>
     */
    private static function getInterleavedGradientNoiseLut(): array
    {
        static $lut = null;
        if ($lut === null) {
            $lut = [];
            for ($k = 0; $k < 4096; $k++) {
                $f = 52.9829189 * ($k / 4096.0);
                $lut[$k] = (int) round((($f - floor($f)) - 0.5) * 512.0);
            }
        }

        return $lut;
    }

    private static function staticPercentileFromHistogram(array $hist, int $total, float $p): int
    {
        $target = max(1, (int) round($total * $p));
        $cum = 0;
        for ($g = 0; $g <= 255; $g++) {
            $cum += (int) ($hist[$g] ?? 0);
            if ($cum >= $target) {
                return $g;
            }
        }

        return 255;
    }

    private function percentileFromHistogram(array $hist, int $total, float $p): int
    {
        return self::staticPercentileFromHistogram($hist, $total, $p);
    }

    private function buildGaussianBlurCopy($src, int $w, int $h, float $sigmaPx)
    {
        if (!function_exists('imagefilter')) {
            return null;
        }

        $factor = max(1.0, $sigmaPx / 0.85);
        if ($factor <= 1.25 || ($w <= 360 && $h <= 360)) {
            $blur = imagecreatetruecolor($w, $h);
            if (!$blur) {
                return null;
            }
            imagecopy($blur, $src, 0, 0, 0, 0, $w, $h);
            @imagefilter($blur, IMG_FILTER_GAUSSIAN_BLUR);
            @imagefilter($blur, IMG_FILTER_GAUSSIAN_BLUR);
            @imagefilter($blur, IMG_FILTER_GAUSSIAN_BLUR);

            return $blur;
        }

        $sw = max(4, (int) round($w / $factor));
        $sh = max(4, (int) round($h / $factor));
        $small = imagecreatetruecolor($sw, $sh);
        if (!$small) {
            return null;
        }
        imagecopyresized($small, $src, 0, 0, 0, 0, $sw, $sh, $w, $h);
        @imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        @imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);

        $blur = null;
        if (function_exists('imagescale')) {
            $scaled = @imagescale($small, $w, $h, IMG_BILINEAR_FIXED);
            if ($scaled !== false) {
                $blur = $scaled;
            }
        }
        if (!$blur) {
            $blur = imagecreatetruecolor($w, $h);
            if ($blur) {
                imagecopyresampled($blur, $small, 0, 0, 0, 0, $w, $h, $sw, $sh);
                @imagefilter($blur, IMG_FILTER_GAUSSIAN_BLUR);
            }
        }
        imagedestroy($small);

        return $blur ?: null;
    }

    /**
     * Binäre Dilatation (quadratischer Kern) über separable Präfixsummen in O(N) auf gepackten Binär-Strings.
     */
    private function dilateBinaryMask(string $mask, int $w, int $h, int $r): string
    {
        if ($r <= 0) {
            return $mask;
        }

        $totalPx = $w * $h;
        $horiz = str_repeat("\x00", $totalPx);
        $prefix = array_fill(0, $w + 1, 0);
        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $prefix[$x + 1] = $prefix[$x] + ($mask[$row + $x] !== "\x00" ? 1 : 0);
            }
            for ($x = 0; $x < $w; $x++) {
                $a = max(0, $x - $r);
                $b = min($w - 1, $x + $r);
                if ($prefix[$b + 1] - $prefix[$a] > 0) {
                    $horiz[$row + $x] = "\x01";
                }
            }
        }

        $out = str_repeat("\x00", $totalPx);
        $colPrefix = array_fill(0, $h + 1, 0);
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $colPrefix[$y + 1] = $colPrefix[$y] + ($horiz[($y * $w) + $x] !== "\x00" ? 1 : 0);
            }
            for ($y = 0; $y < $h; $y++) {
                $a = max(0, $y - $r);
                $b = min($h - 1, $y + $r);
                if ($colPrefix[$b + 1] - $colPrefix[$a] > 0) {
                    $out[($y * $w) + $x] = "\x01";
                }
            }
        }

        return $out;
    }
}

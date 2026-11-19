<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\Export\FileDownloadService;
use App\Services\Laser\LaserPhotoPreprocessor;
use App\Services\Laser\XtoolLaserCockpitService;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LaserPhotoStudioAndAntiGlareTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $cleanupPublicRelPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $disk = Storage::disk('public');
        foreach ($this->cleanupPublicRelPaths as $rel) {
            if ($rel !== '' && $disk->exists($rel)) {
                $disk->delete($rel);
            }
        }
        $this->cleanupPublicRelPaths = [];

        parent::tearDown();
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name'  => 'Steinhauer',
            'email'      => 'alina-studio-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password'   => bcrypt('secret123'),
        ]);
    }

    private function createOrderItem(Product $product, array $configuration): OrderOrderItem
    {
        $order = OrderOrder::create([
            'order_number'     => 'ORD-2026-ST-' . Str::upper(Str::random(5)),
            'email'            => 'studio@example.com',
            'status'           => 'processing',
            'payment_status'   => 'paid',
            'payment_method'   => 'stripe',
            'billing_address'  => ['first_name' => 'Max', 'last_name' => 'Muster', 'address' => 'Ring 1', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Muster', 'address' => 'Ring 1', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'subtotal_price'   => 4990,
            'tax_amount'       => 797,
            'shipping_price'   => 0,
            'total_price'      => 4990,
        ]);

        return OrderOrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'quantity'      => 1,
            'unit_price'    => 4990,
            'tax_rate'      => 19.0,
            'total_price'   => 4990,
            'configuration' => $configuration,
        ]);
    }

    /**
     * Erzeugt ein überbelichtetes Porträt-Foto:
     * Helle Gesichtsmitte um RGB(242, 238, 235) mit feinen Gesichtszügen (Augen, Nase, Wangenschatten)
     * vor einem dunklen Studiorand (RGB ~18).
     */
    private function createOverexposedPortraitJpeg(string $relPath): string
    {
        $w = 360;
        $h = 360;
        $im = imagecreatetruecolor($w, $h);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $dx = ($x - 180.0) / 105.0;
                $dy = ($y - 180.0) / 132.0;
                $r2 = ($dx * $dx) + ($dy * $dy);

                if ($r2 <= 1.0) {
                    // Überbelichtete Stirn/Gesichtsmitte um RGB(242,238,235) mit sanfter Schattierung zum Wangenrand
                    $falloff = $r2 * 22.0;
                    $eyeLeft = hypot($x - 148.0, $y - 155.0) < 11.0 ? 55.0 : 0.0;
                    $eyeRight = hypot($x - 212.0, $y - 155.0) < 11.0 ? 55.0 : 0.0;
                    $noseShadow = (abs($x - 180.0) < 9.0 && $y > 165 && $y < 208) ? 14.0 : 0.0;
                    $smile = (abs($y - 228.0) < 5.0 && abs($x - 180.0) < 28.0) ? 32.0 : 0.0;
                    $detail = $falloff + $eyeLeft + $eyeRight + $noseShadow + $smile;

                    $r = max(0, min(255, (int) round(242.0 - $detail)));
                    $g = max(0, min(255, (int) round(238.0 - $detail)));
                    $b = max(0, min(255, (int) round(235.0 - $detail)));
                } else {
                    // Dunkler Rand
                    $r = 18;
                    $g = 18;
                    $b = 20;
                }

                imagesetpixel($im, $x, $y, imagecolorallocate($im, $r, $g, $b));
            }
        }

        ob_start();
        imagejpeg($im, null, 95);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        $disk = Storage::disk('public');
        $disk->put($relPath, $bytes);
        $this->cleanupPublicRelPaths[] = $relPath;

        return $disk->path($relPath);
    }

    private function decodeDataUrlToGd(string $dataUrl)
    {
        $this->assertStringStartsWith('data:image/png;base64,', $dataUrl);
        $raw = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        $this->assertIsString($raw);
        $gd = imagecreatefromstring($raw);
        $this->assertNotFalse($gd);

        return $gd;
    }

    #[Test]
    public function it_computes_corrective_auto_params_for_overexposed_portrait_and_preserves_facial_variance(): void
    {
        $relRaw = 'bestellungen/cart-uploads/test_overexposed_' . Str::lower(Str::random(6)) . '_raw.jpg';
        $absRaw = $this->createOverexposedPortraitJpeg($relRaw);

        $auto = LaserPhotoPreprocessor::computeMathematicalAutoParams($absRaw, true, 600, 'Aluminium Schwarz Eloxal');

        $this->assertTrue($auto['enabled']);
        $this->assertTrue($auto['grayscale']);
        $this->assertTrue($auto['invert'], 'Porträt auf dunkler Oberfläche muss positiv (invert=true) lasern');
        $this->assertGreaterThan(1.15, $auto['gamma'], 'Überbelichtetes Porträt benötigt ein abdunkelndes Gamma > 1.15');
        $this->assertLessThanOrEqual(58, $auto['highlight_cap'], 'Bei 600 DPI auf dunkler Oberfläche darf highlight_cap höchstens 58 % betragen');
        $this->assertSame('jarvis', $auto['dither_mode']);
        $this->assertFalse($auto['metrics']['is_binary_logo']);

        $savedRel = LaserPhotoPreprocessor::saveStudioProcessedLaserPng($absRaw, $auto);
        $this->cleanupPublicRelPaths[] = $savedRel;
        $this->assertStringStartsWith('bestellungen/xtool/photo-edits/lp_', $savedRel);
        $this->assertTrue(Storage::disk('public')->exists($savedRel));

        $savedBytes = (string) Storage::disk('public')->get($savedRel);
        $gd = imagecreatefromstring($savedBytes);
        $this->assertNotFalse($gd);

        $w = imagesx($gd);
        $h = imagesy($gd);
        $cx0 = (int) round($w * 0.35);
        $cx1 = (int) round($w * 0.65);
        $cy0 = (int) round($h * 0.35);
        $cy1 = (int) round($h * 0.65);

        $faceValues = [];
        $blackDots = 0;
        $whitePores = 0;
        for ($y = $cy0; $y <= $cy1; $y++) {
            for ($x = $cx0; $x <= $cx1; $x++) {
                $g = imagecolorat($gd, $x, $y) & 0xFF;
                $faceValues[] = $g;
                if ($g === 0) {
                    $blackDots++;
                } elseif ($g === 255) {
                    $whitePores++;
                }
            }
        }
        imagedestroy($gd);

        $n = count($faceValues);
        $this->assertGreaterThan(500, $n);
        $meanGray = array_sum($faceValues) / $n;
        $varSum = 0.0;
        foreach ($faceValues as $val) {
            $d = $val - $meanGray;
            $varSum += $d * $d;
        }
        $variance = $varSum / $n;
        $meanFaceDensity = $blackDots / $n;

        // Gesichtsmitte darf weder komplett schwarz (0) noch komplett weiß (255) sein und muss echte Varianz besitzen
        $this->assertGreaterThan(100, $blackDots, 'Gesichtsregion muss aktive Laserpunkte enthalten');
        $this->assertGreaterThan(100, $whitePores, 'Gesichtsregion muss ungelaserte Mikroporen behalten');
        $this->assertGreaterThan(1500.0, $variance, 'Gesichtsregion muss echte Varianz statt einer Vollfläche aufweisen');
        $this->assertLessThanOrEqual(0.59, $meanFaceDensity, 'Mittlere Laserdichte im Gesicht muss den 58-%-Highlight-Cap einhalten');

        // Holz / Schiefer Material-Hint hebt Schärfe auf 110 %
        $slateAuto = LaserPhotoPreprocessor::computeMathematicalAutoParams($absRaw, true, 600, 'Naturschiefer');
        $this->assertSame(110, $slateAuto['sharpness']);
    }

    #[Test]
    public function it_resolves_untouched_original_raw_photos_across_all_configuration_patterns(): void
    {
        $disk = Storage::disk('public');
        $uuid = Str::lower((string) Str::uuid());
        $procRel = "bestellungen/cart-uploads/{$uuid}_proc.png";
        $rawRel = "bestellungen/cart-uploads/{$uuid}_raw.jpg";

        $im = imagecreatetruecolor(32, 32);
        ob_start();
        imagepng($im);
        $pngBytes = (string) ob_get_clean();
        ob_start();
        imagejpeg($im, null, 90);
        $jpgBytes = (string) ob_get_clean();
        imagedestroy($im);

        $disk->put($procRel, $pngBytes);
        $disk->put($rawRel, $jpgBytes);
        $this->cleanupPublicRelPaths[] = $procRel;
        $this->cleanupPublicRelPaths[] = $rawRel;

        // 1) Geschwisterdatei <uuid>_raw.jpg zu <uuid>_proc.png automatisch finden
        $this->assertSame($rawRel, LaserPhotoPreprocessor::resolveOriginalPhotoRelPath($procRel, []));
        $this->assertSame($rawRel, LaserPhotoPreprocessor::resolveOriginalPhotoRelPath('/storage/' . $procRel, []));
        $this->assertTrue(LaserPhotoPreprocessor::hasUntouchedOriginal($procRel, []));

        // 2) Über raw_files Mapping
        $explicitRaw = "bestellungen/cart-uploads/{$uuid}_custom_orig.jpg";
        $disk->put($explicitRaw, $jpgBytes);
        $this->cleanupPublicRelPaths[] = $explicitRaw;
        $this->assertSame(
            $explicitRaw,
            LaserPhotoPreprocessor::resolveOriginalPhotoRelPath($procRel, [
                'raw_files' => [$procRel => $explicitRaw],
            ])
        );

        // 3) Über logos[].raw_value Mapping
        $logoRaw = "bestellungen/cart-uploads/{$uuid}_logo_orig.jpg";
        $disk->put($logoRaw, $jpgBytes);
        $this->cleanupPublicRelPaths[] = $logoRaw;
        $this->assertSame(
            $logoRaw,
            LaserPhotoPreprocessor::resolveOriginalPhotoRelPath($procRel, [
                'logos' => [['value' => $procRel, 'raw_value' => $logoRaw]],
            ])
        );

        // 4) Fallback für Altbestellungen ohne _raw Datei
        $legacyOnlyRel = "bestellungen/cart-uploads/{$uuid}_legacy_only.png";
        $disk->put($legacyOnlyRel, $pngBytes);
        $this->cleanupPublicRelPaths[] = $legacyOnlyRel;
        $this->assertSame($legacyOnlyRel, LaserPhotoPreprocessor::resolveOriginalPhotoRelPath($legacyOnlyRel, []));
        $this->assertFalse(LaserPhotoPreprocessor::hasUntouchedOriginal($legacyOnlyRel, []));
    }

    #[Test]
    public function it_renders_studio_preview_reacting_accurately_to_all_parameters_and_dither_modes(): void
    {
        $w = 220;
        $h = 220;
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $trans);

        // Freigestellter Farbverlauf mit scharfer vertikaler Kante bei x = 110 und transparenten Rändern (Radius 95 px)
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (hypot($x - 110.0, $y - 110.0) > 95.0) {
                    continue;
                }
                $base = $x < 110
                    ? (int) round(60 + (($y / $h) * 80))
                    : (int) round(150 + (($y / $h) * 80));
                // Kräftig blauer Farbton (R != B), damit grayscale=true (BT.709) vs grayscale=false unterscheidbar ist
                $r = max(0, min(255, $base - 45));
                $g = max(0, min(255, $base));
                $b = max(0, min(255, min(255, $base + 55)));
                imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, $r, $g, $b, 0));
            }
        }

        $relSrc = 'bestellungen/cart-uploads/test_studio_params_' . Str::lower(Str::random(6)) . '.png';
        ob_start();
        imagepng($im);
        Storage::disk('public')->put($relSrc, (string) ob_get_clean());
        imagedestroy($im);
        $this->cleanupPublicRelPaths[] = $relSrc;
        $absSrc = Storage::disk('public')->path($relSrc);

        $baseParams = LaserPhotoPreprocessor::defaultStudioParams(false, 600);
        $baseParams['enabled'] = true;
        $baseParams['dither_mode'] = 'grayscale';
        $baseParams['sharpness'] = 0;
        $baseParams['highlight_cap'] = 100;

        // Performance & Transparenz-Erhalt an den Ecken prüfen
        $t0 = microtime(true);
        $grayUrl = LaserPhotoPreprocessor::renderStudioPreviewDataUrl($absSrc, $baseParams, 800);
        $elapsedMs = (microtime(true) - $t0) * 1000.0;
        $this->assertLessThan(350.0, $elapsedMs);

        $gdGray = $this->decodeDataUrlToGd($grayUrl);
        $cornerAlpha = (imagecolorat($gdGray, 4, 4) >> 24) & 0x7F;
        $centerColor = imagecolorat($gdGray, 80, 110);
        $this->assertSame(127, $cornerAlpha, 'Originale Rand-Transparenz (Freisteller) muss erhalten bleiben');
        $this->assertSame(($centerColor >> 16) & 0xFF, $centerColor & 0xFF, 'Bei grayscale=true müssen R, G, B identisch sein');

        // 1) grayscale = false reagiert messbar
        $colorParams = array_merge($baseParams, ['grayscale' => false]);
        $gdColor = $this->decodeDataUrlToGd(LaserPhotoPreprocessor::renderStudioPreviewDataUrl($absSrc, $colorParams, 800));
        $cPx = imagecolorat($gdColor, 80, 110);
        $this->assertNotSame(($cPx >> 16) & 0xFF, $cPx & 0xFF, 'Bei grayscale=false bleiben Farbkanäle differenziert');
        imagedestroy($gdColor);

        // 2) invert = true kehrt im xTool-Laser-Export die Polarität um, zeigt in der Studio-Vorschau aber rohlingsecht positiv
        $invParams = array_merge($baseParams, ['invert' => true]);
        $invLaserRel = LaserPhotoPreprocessor::saveStudioProcessedLaserPng($absSrc, $invParams);
        $this->cleanupPublicRelPaths[] = $invLaserRel;
        $gdInvLaser = imagecreatefromstring((string) Storage::disk('public')->get($invLaserRel));
        $this->assertNotFalse($gdInvLaser);

        $gdInvPreview = $this->decodeDataUrlToGd(LaserPhotoPreprocessor::renderStudioPreviewDataUrl($absSrc, $invParams, 800));
        $leftNorm = imagecolorat($gdGray, 70, 110) & 0xFF;
        $rightNorm = imagecolorat($gdGray, 150, 110) & 0xFF;
        $leftLaser = imagecolorat($gdInvLaser, 70, 110) & 0xFF;
        $rightLaser = imagecolorat($gdInvLaser, 150, 110) & 0xFF;
        $leftPreviewPx = imagecolorat($gdInvPreview, 70, 110);
        $rightPreviewPx = imagecolorat($gdInvPreview, 150, 110);

        $this->assertLessThan($rightNorm, $leftNorm);
        $this->assertGreaterThan($rightLaser, $leftLaser, 'xTool-Laser-Export muss bei invert=true die helle Seite stärker lasern (kleinerer Grauwert)');
        $this->assertLessThan($rightPreviewPx & 0xFF, $leftPreviewPx & 0xFF, 'Studio-Vorschau muss bei invert=true rohlingsecht positiv bleiben (kein Röntgen-Negativ)');
        $this->assertGreaterThan(($leftPreviewPx >> 16) & 0xFF, $leftPreviewPx & 0xFF, 'Dunkles Eloxal-Substrat hat in der Vorschau den leichten Anthrazit-Blauanteil');
        $this->assertSame(127, (imagecolorat($gdInvPreview, 4, 4) >> 24) & 0x7F, 'Äußere Transparenz muss auch in der Dunkel-Rohling-Vorschau erhalten bleiben');
        imagedestroy($gdInvLaser);
        imagedestroy($gdInvPreview);

        // 3) contrast spreizt dunkle und helle Hälfte stärker auseinander
        $highContrastParams = array_merge($baseParams, ['contrast' => 75]);
        $gdHiCon = $this->decodeDataUrlToGd(LaserPhotoPreprocessor::renderStudioPreviewDataUrl($absSrc, $highContrastParams, 800));
        $spanBase = abs((imagecolorat($gdGray, 150, 110) & 0xFF) - (imagecolorat($gdGray, 70, 110) & 0xFF));
        $spanHiCon = abs((imagecolorat($gdHiCon, 150, 110) & 0xFF) - (imagecolorat($gdHiCon, 70, 110) & 0xFF));
        $this->assertGreaterThan($spanBase, $spanHiCon, 'Höherer contrast muss die Tonwertdifferenz vergrößern');
        imagedestroy($gdHiCon);

        // 4) gamma > 1.0 vs gamma < 1.0 verschiebt die Mitteltöne konsistent (pow(lum, gamma))
        $gdGammaHigh = $this->decodeDataUrlToGd(LaserPhotoPreprocessor::renderStudioPreviewDataUrl($absSrc, array_merge($baseParams, ['gamma' => 1.85]), 800));
        $gdGammaLow = $this->decodeDataUrlToGd(LaserPhotoPreprocessor::renderStudioPreviewDataUrl($absSrc, array_merge($baseParams, ['gamma' => 0.55]), 800));
        // Bei invert=false (Laser-Bitmap: dunkles Foto = niedriges Gray / mehr Laser): gamma=1.85 dunkelt Foto ab -> mehr Laserdichte -> niedrigerer Gray-Wert
        $midGammaHigh = imagecolorat($gdGammaHigh, 140, 110) & 0xFF;
        $midGammaLow = imagecolorat($gdGammaLow, 140, 110) & 0xFF;
        $this->assertLessThan($midGammaLow, $midGammaHigh, 'gamma > 1.0 dunkelt Szenen-Luminanz ab gegenüber gamma < 1.0');
        imagedestroy($gdGammaHigh);
        imagedestroy($gdGammaLow);

        // 5) sharpness erhöht den Kantenkontrast direkt an der Kante x = 109..110
        $gdSharp = $this->decodeDataUrlToGd(LaserPhotoPreprocessor::renderStudioPreviewDataUrl($absSrc, array_merge($baseParams, ['sharpness' => 190]), 800));
        $edgeJumpUnsharp = abs((imagecolorat($gdGray, 111, 110) & 0xFF) - (imagecolorat($gdGray, 108, 110) & 0xFF));
        $edgeJumpSharp = abs((imagecolorat($gdSharp, 111, 110) & 0xFF) - (imagecolorat($gdSharp, 108, 110) & 0xFF));
        $this->assertGreaterThan($edgeJumpUnsharp, $edgeJumpSharp, 'Unsharp Mask (sharpness=190) muss den Kantensprung verstärken');
        imagedestroy($gdSharp);
        imagedestroy($gdGray);

        // 6) Alle 4 dither_mode-Modi (jarvis, stucki, grayscale, threshold) erzeugen gültige, unterscheidbare Ergebnisse
        $modeOutputs = [];
        foreach (['jarvis', 'stucki', 'grayscale', 'threshold'] as $mode) {
            $url = LaserPhotoPreprocessor::renderStudioPreviewDataUrl($absSrc, array_merge($baseParams, [
                'dither_mode'   => $mode,
                'highlight_cap' => 75,
            ]), 800);
            $modeOutputs[$mode] = $url;
            $gdMode = $this->decodeDataUrlToGd($url);

            $nonBinaryCount = 0;
            for ($y = 60; $y <= 160; $y += 4) {
                for ($x = 60; $x <= 160; $x += 4) {
                    $c = imagecolorat($gdMode, $x, $y);
                    if ((($c >> 24) & 0x7F) === 0) {
                        $v = $c & 0xFF;
                        if ($v > 0 && $v < 255) {
                            $nonBinaryCount++;
                        }
                    }
                }
            }
            imagedestroy($gdMode);

            if ($mode === 'grayscale') {
                $this->assertGreaterThan(20, $nonBinaryCount, 'grayscale-Modus muss echte Zwischen-Graustufen enthalten');
            } else {
                $this->assertSame(0, $nonBinaryCount, "{$mode}-Modus muss auf opaken Innenpixeln strikt binär (0/255) sein");
            }
        }

        $this->assertCount(4, array_unique(array_values($modeOutputs)), 'Alle 4 dither_mode-Modi müssen sich mathematisch unterscheiden');
    }

    #[Test]
    public function it_propagates_laser_photo_adjustments_to_svg_png_xcs_and_single_photo_xf_and_fixes_jewelry_presets(): void
    {
        $this->seed(ProductSeeder::class);
        $this->actingAs($this->createAdmin(), 'admin');

        $relRaw = 'bestellungen/cart-uploads/order_photo_' . Str::lower(Str::random(6)) . '_raw.jpg';
        $relProc = str_replace('_raw.jpg', '_proc.png', $relRaw);
        $absRaw = $this->createOverexposedPortraitJpeg($relRaw);

        // Bewusst kaputtes/schwarzes _proc.png ablegen, um zu beweisen, dass edited_path (bzw. _raw) genutzt wird!
        $blackDummy = imagecreatetruecolor(120, 120);
        imagefill($blackDummy, 0, 0, imagecolorallocate($blackDummy, 0, 0, 0));
        ob_start();
        imagepng($blackDummy);
        Storage::disk('public')->put($relProc, (string) ob_get_clean());
        imagedestroy($blackDummy);
        $this->cleanupPublicRelPaths[] = $relProc;

        $anhaenger = Product::where('slug', 'seelen-anhaenger')->firstOrFail();
        $studioParams = array_merge(
            LaserPhotoPreprocessor::computeMathematicalAutoParams($absRaw, true, 600, 'Aluminium'),
            ['dither_mode' => 'stucki']
        );
        $editedRel = LaserPhotoPreprocessor::saveStudioProcessedLaserPng($absRaw, $studioParams);
        $this->cleanupPublicRelPaths[] = $editedRel;

        // Single-Photo-Position OHNE Text und OHNE Vektor-Symbole
        $configWithStudio = [
            'logos' => [[
                'x'        => 50.0,
                'y'        => 72.0,
                'size'     => 220.0,
                'rotation' => 0,
                'type'     => 'saved',
                'value'    => $relProc,
                'url'      => '/storage/' . $relProc,
            ]],
            'texts' => [],
            'laser_photo_adjustments' => [
                $relProc => array_merge($studioParams, [
                    'enabled'     => true,
                    'edited_path' => $editedRel,
                ]),
            ],
        ];

        $item = $this->createOrderItem($anhaenger, $configWithStudio);
        $exportSvc = app(FileDownloadService::class);
        $cockpitSvc = app(XtoolLaserCockpitService::class);

        // 1) Live-Cockpit-Payload + Cache-Fingerprint reagiert auf edited_path
        $payload = $exportSvc->buildLaserDesignPayload($item, 'front', true);
        $this->assertNotEmpty($payload['photoAdjustmentsHash']);
        $this->assertTrue((bool) ($payload['photoLayerInfo']['studio_adjusted'] ?? false));
        $this->assertSame('stucki', $payload['photoLayerInfo']['studio_dither_mode']);

        $calA = $exportSvc->resolveEngravingWorkingAreaCalibration($anhaenger, $anhaenger->configurator_settings, $configWithStudio, false);
        $studioParamsB = array_merge($studioParams, ['gamma' => 1.25, 'dither_mode' => 'jarvis']);
        $editedRelB = LaserPhotoPreprocessor::saveStudioProcessedLaserPng($absRaw, $studioParamsB);
        $this->cleanupPublicRelPaths[] = $editedRelB;
        $configB = $configWithStudio;
        $configB['laser_photo_adjustments'][$relProc]['edited_path'] = $editedRelB;
        $calB = $exportSvc->resolveEngravingWorkingAreaCalibration($anhaenger, $anhaenger->configurator_settings, $configB, false);
        $this->assertNotSame($calA['photoAdjustmentsHash'] ?? '', $calB['photoAdjustmentsHash'] ?? '', 'Preview-Cache-Hash muss sich nach Zahnrad-Speichern ändern');

        // 2) Laser-SVG & Kontur-PNG Export laufen fehlerfrei mit dem aufbereiteten Studio-Bild
        $svgResp = $exportSvc->downloadLaserSvg($item, 'front');
        ob_start();
        $svgResp->sendContent();
        $svgContent = (string) ob_get_clean();
        $this->assertStringContainsString('data:image/png;base64,', $svgContent);

        $pngResp = $exportSvc->downloadLaserPng($item, 'front');
        $this->assertNotNull($pngResp);
        ob_start();
        $pngResp->sendContent();
        $contourPngBytes = (string) ob_get_clean();
        $this->assertNotFalse(imagecreatefromstring($contourPngBytes));

        // 3) .xcs Projekt übernimmt dither_mode ('stucki') aus laser_photo_adjustments
        $xcs = $cockpitSvc->generateXcsProjectBytes($item, 'front', 100.0, 100.0, 0.0);
        $tmpZip = tempnam(sys_get_temp_dir(), 'st_xcs_');
        file_put_contents($tmpZip, $xcs['bytes']);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmpZip) === true);
        $proj = json_decode((string) $zip->getFromName('project.json'), true);
        $dev = json_decode((string) $zip->getFromName('devices/device-GS009-CLASS-4-1.json'), true);
        $xcsProfile = $dev['processing'][$proj['activeCanvasId']]['modes']['LASER_PLANE']['profiles'][0];
        $this->assertSame('stucki', $xcsProfile['values']['bitmapMode']);
        $zip->close();
        @unlink($tmpZip);

        // 4) .xf Single-Photo OHNE Text: geditherter Punkt-Puls-Stream statt durchgehender 100-%-G1-Brennstriche!
        $xf = $cockpitSvc->generateXfPackageBytes($item, 'front', 100.0, 100.0, 0.0);
        $this->assertFalse($xf['is_multi_layer']);
        $this->assertSame('stucki', $xf['description']['processingParams'][0]['bitmapMode']);
        $this->assertStringContainsString('"bitmapMode":"stucki"', $xf['motion_gcode']);

        $maxPulseSpanMm = 0.0;
        $pulseCount = 0;
        $curX = 100.0;
        $curY = 100.0;
        foreach (explode("\n", $xf['motion_gcode']) as $line) {
            if (preg_match('/^G([01])X([0-9.]+)Y([0-9.]+)(?:S(\d+))?/', trim($line), $m)) {
                $nx = (float) $m[2];
                $ny = (float) $m[3];
                $sVal = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0;
                if ($m[1] === '1' && $sVal > 0) {
                    $span = hypot($nx - $curX, $ny - $curY);
                    if ($span > $maxPulseSpanMm) {
                        $maxPulseSpanMm = $span;
                    }
                    $pulseCount++;
                }
                $curX = $nx;
                $curY = $ny;
            }
        }
        $this->assertGreaterThan(50, $pulseCount, 'Single-Photo .xf muss echte Laser-Punktpulse enthalten');
        $this->assertLessThanOrEqual(0.06, $maxPulseSpanMm, 'Benachbarte Halbton-Dither-Punkte dürfen niemals zu langen G1-Linien verschmelzen');

        // 5) Schmuck-Preset-Fix: Halbton-Kundenfoto auf seelen-pfote-hundemarke, goldenes-seelen-herz, seelen-talisman -> 'Jarvis' statt 'Schwellenwert'
        foreach (['seelen-pfote-hundemarke', 'goldenes-seelen-herz', 'seelen-talisman'] as $jewelrySlug) {
            $jewelryProduct = Product::where('slug', $jewelrySlug)->firstOrFail();
            $jewelryItem = $this->createOrderItem($jewelryProduct, [
                'logos' => [[
                    'x'        => 50.0,
                    'y'        => 55.0,
                    'size'     => 180.0,
                    'rotation' => 0,
                    'type'     => 'saved',
                    'value'    => $relRaw,
                    'url'      => '/storage/' . $relRaw,
                ]],
                'texts' => [],
            ]);
            $effParams = $cockpitSvc->resolveEffectiveLaserParameters($jewelryItem, 'front');
            $this->assertSame('Jarvis', $effParams['bitmap_mode'], "{$jewelrySlug}: Halbton-Foto muss Jarvis statt Schwellenwert nutzen");
        }
    }

    #[Test]
    public function it_controls_fill_light_anti_glare_and_restores_brightness_in_finally_and_passes_light_mode_to_relay(): void
    {
        // 1) Prozent-Formel für fillLightBrightFront / fillLightBrightBack prüfen
        $this->assertSame(0, XtoolLaserCockpitService::fillLightPercentToBri(0));
        $this->assertSame(20, XtoolLaserCockpitService::fillLightPercentToBri(1));
        $this->assertSame(255, XtoolLaserCockpitService::fillLightPercentToBri(100));

        // 2) detectBlankPositionOnBed & Relay-Durchreichung von light_mode prüfen
        $tmpRelayDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_relay_antiglare_' . Str::lower(Str::random(6));
        @mkdir($tmpRelayDir, 0775, true);

        $svc = new XtoolLaserCockpitService();
        $svc->setRelayTestingOptions($tmpRelayDir, true, 0.12, 0.12);
        $svc->recordMiniPcRelayHeartbeat(true, '192.168.188.150');

        $enqueued = $svc->enqueueRelayJob('capture_camera', [
            'laser_ip'   => '192.168.188.150',
            'light_mode' => 'dim',
        ]);
        $this->assertSame('dim', $enqueued['light_mode']);
        $claimed = $svc->claimNextPendingRelayJob(true, '192.168.188.150');
        $this->assertNotNull($claimed);
        $this->assertSame('dim', $claimed['light_mode']);

        $bedRes = $svc->detectBlankPositionOnBed(null, ['laser_camera_light_mode' => 'anti_glare'], 'front', false);
        $this->assertSame('anti_glare', $bedRes['light_mode']);

        // 3) V2-WebSocket Kamera-Ablauf inkl. 220 ms Exposure-Settle & garantiertem finally-Restore testen
        $sampleImg = imagecreatetruecolor(640, 480);
        imagefill($sampleImg, 0, 0, imagecolorallocate($sampleImg, 90, 95, 100));
        // Zufälliges Muster damit JPEG > 15 KB groß ist
        mt_srand(42);
        for ($y = 0; $y < 480; $y += 2) {
            for ($x = 0; $x < 640; $x += 2) {
                $v = mt_rand(20, 230);
                imagefilledrectangle($sampleImg, $x, $y, $x + 1, $y + 1, imagecolorallocate($sampleImg, $v, ($v + 40) % 255, ($v + 90) % 255));
            }
        }
        ob_start();
        imagejpeg($sampleImg, null, 98);
        $fakeRawJpeg = (string) ob_get_clean();
        imagedestroy($sampleImg);
        $this->assertGreaterThan(15000, strlen($fakeRawJpeg));

        $mockCockpit = new class($fakeRawJpeg) extends XtoolLaserCockpitService {
            /** @var array<int, array<string, mixed>> */
            public array $sentCommands = [];
            public int $settledMicroseconds = 0;
            public bool $failOnSnap = false;
            private string $jpegBytes;

            public function __construct(string $jpegBytes)
            {
                $this->jpegBytes = $jpegBytes;
            }

            protected function probeDirectLaserSocket(string $ip = self::DEFAULT_LASER_IP, bool $force = false): bool
            {
                return true;
            }

            protected function v2WsConnect(string $ip, int $port, string $path)
            {
                return fopen('php://memory', 'r+');
            }

            protected function resolveOrDownloadGlobalCalibPath($mainWs, $fileWs, int &$txId, string $sessionToken, string $hostAppdataDir): ?string
            {
                return null;
            }

            protected function waitForCameraExposureSettleUs(int $microseconds = 650000): void
            {
                $this->settledMicroseconds += $microseconds;
            }

            protected function v2DownloadFileFromLaser($mainWs, $fileWs, int &$txId, string $sessionToken, string $remoteFilename, int $fileType, int $channel): ?string
            {
                return $this->jpegBytes;
            }

            protected function v2SendJsonCmd($ws, int &$txId, string $sessionToken, string $method, string $url, array $params = [], array $data = []): ?array
            {
                $txId++;
                $this->sentCommands[] = [
                    'method' => $method,
                    'url'    => $url,
                    'params' => $params,
                    'data'   => $data,
                ];
                if ($url === '/v1/user/parity') {
                    return ['code' => 0, 'data' => ['sessionToken' => 'tok_test_123']];
                }
                if ($url === '/v1/device/configs') {
                    return ['code' => 0, 'data' => ['fillLightBrightFront' => 255, 'fillLightBrightBack' => 136]];
                }
                if ($url === '/v1/camera/snap') {
                    if ($this->failOnSnap) {
                        throw new \RuntimeException('Simulated camera timeout');
                    }
                    return ['code' => 0, 'data' => ['filename' => 'snap_main.jpg']];
                }

                return ['code' => 0, 'data' => []];
            }
        };

        $targetBgRel = 'bestellungen/xtool/test_antiglare_bg_' . Str::lower(Str::random(5)) . '.jpg';
        $this->cleanupPublicRelPaths[] = $targetBgRel;
        $targetBgAbs = Storage::disk('public')->path($targetBgRel);

        // Simuliere eine zuvor schreibgeschützte background.jpg (0444) im 0777-Ordner -> atomarer Temp+Unlink+Rename muss trotzdem klappen!
        @mkdir(dirname($targetBgAbs), 0777, true);
        @chmod(dirname($targetBgAbs), 0777);
        file_put_contents($targetBgAbs, 'STALE_READONLY_FILE');
        @chmod($targetBgAbs, 0444);

        // Lauf A: anti_glare Erfolg -> GS009-CLASS-4 /v1/device/configs, fill_light (0, 0), 650 ms V4L2-Flush-Settle, Restore auf Rohwerte (255, 136)
        $ok = $mockCockpit->captureAndCalibrateLiveCameraFromLaserV2('192.168.188.150', $targetBgAbs, true, 'anti_glare');
        $this->assertTrue($ok);
        $this->assertSame(650000, $mockCockpit->settledMicroseconds);
        $this->assertSame(0666, fileperms($targetBgAbs) & 0666);

        $cfgCmds = array_values(array_filter(
            $mockCockpit->sentCommands,
            fn (array $c) => $c['url'] === '/v1/device/configs'
        ));
        $this->assertCount(1, $cfgCmds);
        $this->assertSame([
            'alias' => 'config',
            'type'  => 'user',
            'kv'    => ['fillLightBrightFront', 'fillLightBrightBack'],
        ], $cfgCmds[0]['data']);

        $fillCmds = array_values(array_filter(
            $mockCockpit->sentCommands,
            fn (array $c) => $c['url'] === '/v1/peripheral/param' && ($c['params']['type'] ?? '') === 'fill_light'
        ));
        $this->assertCount(3, $fillCmds, 'fill_light muss vor dem Foto gesetzt, nach POST /v1/camera erneut bestätigt und im finally wiederhergestellt werden');
        $this->assertSame(['action' => 'set_bri', 'front' => 0, 'back' => 0], $fillCmds[0]['data']);
        $this->assertSame(['action' => 'set_bri', 'front' => 0, 'back' => 0], $fillCmds[1]['data']);
        $this->assertSame(['action' => 'set_bri', 'front' => 255, 'back' => 136], $fillCmds[2]['data']);

        // Lauf B: dim + Exception bei /v1/camera/snap -> finally stellt fill_light trotzdem zuverlässig wieder her!
        $mockCockpit->sentCommands = [];
        $mockCockpit->settledMicroseconds = 0;
        $mockCockpit->failOnSnap = true;
        $failedOk = $mockCockpit->captureAndCalibrateLiveCameraFromLaserV2('192.168.188.150', $targetBgAbs, true, 'dim');
        $this->assertFalse($failedOk);

        $dimFillCmds = array_values(array_filter(
            $mockCockpit->sentCommands,
            fn (array $c) => $c['url'] === '/v1/peripheral/param' && ($c['params']['type'] ?? '') === 'fill_light'
        ));
        $this->assertCount(3, $dimFillCmds, 'Auch bei Snap-Abbruch muss fill_light im finally wiederhergestellt werden');
        $this->assertSame(['action' => 'set_bri', 'front' => 0, 'back' => 20], $dimFillCmds[0]['data']);
        $this->assertSame(['action' => 'set_bri', 'front' => 0, 'back' => 20], $dimFillCmds[1]['data']);
        $this->assertSame(['action' => 'set_bri', 'front' => 255, 'back' => 136], $dimFillCmds[2]['data']);

        if (is_dir($tmpRelayDir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($tmpRelayDir);
        }
    }

    #[Test]
    public function it_processes_9_7mp_3600x2700_photos_with_low_memory_footprint_and_high_speed(): void
    {
        $disk = Storage::disk('public');

        // Synthetische Vorlage (180x135) schnell in C auf 3600x2700 (9,72 MP) und 1500x1125 skalieren
        $seed = imagecreatetruecolor(180, 135);
        for ($y = 0; $y < 135; $y++) {
            for ($x = 0; $x < 180; $x++) {
                $dx = ($x - 90.0) / 52.0;
                $dy = ($y - 67.0) / 48.0;
                $r2 = ($dx * $dx) + ($dy * $dy);
                if ($r2 <= 1.0) {
                    $lum = (int) round(226.0 - ($r2 * 64.0) - (((($x * 7) + ($y * 11)) % 19)));
                    $col = imagecolorallocate($seed, max(0, min(255, $lum)), max(0, min(255, $lum - 8)), max(0, min(255, $lum - 14)));
                } else {
                    $bg = 16 + ((($x * 3) + ($y * 5)) % 10);
                    $col = imagecolorallocate($seed, $bg, $bg, $bg + 4);
                }
                imagesetpixel($seed, $x, $y, $col);
            }
        }

        $rel3600 = 'bestellungen/cart-uploads/bench_3600x2700_' . Str::lower(Str::random(6)) . '_raw.jpg';
        $rel1500 = 'bestellungen/cart-uploads/bench_1500x1000_' . Str::lower(Str::random(6)) . '_raw.jpg';
        $this->cleanupPublicRelPaths[] = $rel3600;
        $this->cleanupPublicRelPaths[] = $rel1500;
        $abs3600 = $disk->path($rel3600);
        $abs1500 = $disk->path($rel1500);
        @mkdir(dirname($abs3600), 0775, true);

        $im1500 = imagecreatetruecolor(1500, 1000);
        imagecopyresampled($im1500, $seed, 0, 0, 0, 0, 1500, 1000, 180, 135);
        imagejpeg($im1500, $abs1500, 90);
        imagedestroy($im1500);
        unset($im1500);

        $im3600 = imagecreatetruecolor(3600, 2700);
        imagecopyresampled($im3600, $seed, 0, 0, 0, 0, 3600, 2700, 180, 135);
        imagejpeg($im3600, $abs3600, 90);
        imagedestroy($im3600);
        imagedestroy($seed);
        unset($im3600, $seed);

        // Warmup der statischen LUTs und des Storage-Adapters vor der exakten Messung
        $warmParams = LaserPhotoPreprocessor::defaultStudioParams(true, 600);
        LaserPhotoPreprocessor::renderStudioPreviewDataUrl($abs1500, $warmParams, 64);
        $disk->exists($rel1500);

        // 1) Benchmark für normales 1500px Foto (< 250 ms inkl. Disk-Write & < 30 MB Peak-RAM)
        $elapsed1500Ms = PHP_FLOAT_MAX;
        $peakDelta1500Mb = 0.0;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            gc_collect_cycles();
            if (function_exists('memory_reset_peak_usage')) {
                memory_reset_peak_usage();
            }
            $memBefore1500 = memory_get_usage();
            $t1500 = microtime(true);
            $saved1500Rel = LaserPhotoPreprocessor::saveStudioProcessedLaserPng($abs1500, array_merge($warmParams, [
                'enabled'     => true,
                'dither_mode' => 'jarvis',
            ]));
            $runMs = (microtime(true) - $t1500) * 1000.0;
            $runPeakMb = max(0, memory_get_peak_usage() - $memBefore1500) / (1024.0 * 1024.0);
            $this->cleanupPublicRelPaths[] = $saved1500Rel;
            if ($runMs < $elapsed1500Ms) {
                $elapsed1500Ms = $runMs;
                $peakDelta1500Mb = $runPeakMb;
            }
            if ($elapsed1500Ms < 250.0) {
                break;
            }
        }

        $this->assertTrue($disk->exists($saved1500Rel));
        $this->assertLessThan(250.0, $elapsed1500Ms, sprintf('1500px Jarvis-Export dauerte %.1f ms (Ziel < 250 ms)', $elapsed1500Ms));
        $this->assertLessThan(30.0, $peakDelta1500Mb, sprintf('1500px Peak-RAM-Delta betrug %.2f MB (Ziel < 30 MB)', $peakDelta1500Mb));

        // 2) Härtungs- & OOM-Benchmark für großes 3600 x 2700 (9,7 MP) RGB-Smartphone-Foto
        $elapsed3600Ms = PHP_FLOAT_MAX;
        $peakDelta3600Bytes = 0;
        $peakDelta3600Mb = 0.0;
        $retainedDeltaMb = 0.0;
        $saved3600Rel = '';
        for ($attempt = 0; $attempt < 2; $attempt++) {
            gc_collect_cycles();
            if (function_exists('memory_reset_peak_usage')) {
                memory_reset_peak_usage();
            }
            $memBefore3600 = memory_get_usage();
            $t3600 = microtime(true);

            $autoParams = LaserPhotoPreprocessor::computeMathematicalAutoParams($abs3600, true, 600, 'Aluminium Schwarz Eloxal');
            $saved3600Rel = LaserPhotoPreprocessor::saveStudioProcessedLaserPng($abs3600, array_merge($autoParams, [
                'enabled'     => true,
                'dither_mode' => 'jarvis',
            ]));

            $run3600Ms = (microtime(true) - $t3600) * 1000.0;
            $runPeakBytes = max(0, memory_get_peak_usage() - $memBefore3600);
            $runRetainedMb = max(0, memory_get_usage() - $memBefore3600) / (1024.0 * 1024.0);
            $this->cleanupPublicRelPaths[] = $saved3600Rel;

            if ($run3600Ms < $elapsed3600Ms) {
                $elapsed3600Ms = $run3600Ms;
                $peakDelta3600Bytes = $runPeakBytes;
                $peakDelta3600Mb = $runPeakBytes / (1024.0 * 1024.0);
                $retainedDeltaMb = $runRetainedMb;
            }
            if ($elapsed3600Ms < 800.0) {
                break;
            }
        }

        $this->assertTrue($disk->exists($saved3600Rel));
        $this->assertLessThan(45 * 1024 * 1024, $peakDelta3600Bytes, sprintf('3600x2700 Peak-RAM-Anstieg betrug %.2f MB (Limit < 45 MB)', $peakDelta3600Mb));
        $this->assertLessThan(2.0, $retainedDeltaMb, 'Nach Abschluss dürfen keine Bildpuffer im RAM verbleiben');
        $this->assertLessThan(800.0, $elapsed3600Ms, sprintf('3600x2700 Komplettlauf dauerte %.1f ms (Limit < 800 ms)', $elapsed3600Ms));

        $outGd = imagecreatefromstring((string) $disk->get($saved3600Rel));
        $this->assertNotFalse($outGd);
        $this->assertSame(1500, imagesx($outGd), 'Export-Maximalauflösung muss auf 1500 px begrenzt sein');
        $this->assertSame(1125, imagesy($outGd));

        $blackDots = 0;
        $whitePores = 0;
        for ($y = 400; $y < 700; $y += 3) {
            for ($x = 550; $x < 950; $x += 3) {
                $g = imagecolorat($outGd, $x, $y) & 0xFF;
                if ($g === 0) {
                    $blackDots++;
                } elseif ($g === 255) {
                    $whitePores++;
                }
            }
        }
        imagedestroy($outGd);
        $this->assertGreaterThan(200, $blackDots);
        $this->assertGreaterThan(200, $whitePores);

        // 3) Direkter Aufruf von jarvisDither & stuckiDither (3-Zeilen Rolling-Error-Buffer) inkl. Randtransparenz
        $pre = new LaserPhotoPreprocessor();
        $mini = imagecreatetruecolor(64, 64);
        imagealphablending($mini, false);
        imagesavealpha($mini, true);
        imagefill($mini, 0, 0, imagecolorallocatealpha($mini, 255, 255, 255, 127));
        for ($y = 8; $y < 56; $y++) {
            for ($x = 8; $x < 56; $x++) {
                $g = (int) round((($x + $y) / 112.0) * 220.0);
                imagesetpixel($mini, $x, $y, imagecolorallocatealpha($mini, $g, $g, $g, 0));
            }
        }
        $dj = $pre->jarvisDither($mini, 128);
        $ds = $pre->stuckiDither($mini, 128);
        $this->assertSame(127, (imagecolorat($dj, 2, 2) >> 24) & 0x7F);
        $this->assertSame(127, (imagecolorat($ds, 2, 2) >> 24) & 0x7F);
        imagedestroy($mini);
        imagedestroy($dj);
        imagedestroy($ds);
    }

    #[Test]
    public function it_eliminates_vertical_8px_and_worm_stripes_and_renders_positive_dark_blank_studio_preview(): void
    {
        $w = 640;
        $h = 480;
        $im = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $dx = ($x - 320.0) / 115.0;
                $dy = ($y - 240.0) / 135.0;
                $r2 = ($dx * $dx) + ($dy * $dy);
                if ($r2 <= 1.0) {
                    // Sanfte Kinderstirn/Wangen-Fläche ohne hartes Rauschen (empfindlichster Test für Wurm- & 8px-Streifen!)
                    $lum = (int) round(212.0 - ($r2 * 48.0));
                    $col = imagecolorallocate($im, $lum, $lum - 6, $lum - 12);
                } else {
                    // Gleichmäßiger grüner Stoffhintergrund mit sanftem Verlauf (x-Abhängigkeit deckt 8px-Blur-Stufen sofort auf)
                    $bg = (int) round(68.0 + (($x / (float) $w) * 44.0));
                    $col = imagecolorallocate($im, max(0, $bg - 22), min(255, $bg + 18), max(0, $bg - 16));
                }
                imagesetpixel($im, $x, $y, $col);
            }
        }

        $relRaw = 'bestellungen/cart-uploads/test_nostripes_' . Str::lower(Str::random(6)) . '_raw.jpg';
        $this->cleanupPublicRelPaths[] = $relRaw;
        $absRaw = Storage::disk('public')->path($relRaw);
        @mkdir(dirname($absRaw), 0775, true);
        imagejpeg($im, $absRaw, 96);
        imagedestroy($im);

        $params = [
            'enabled'       => true,
            'grayscale'     => true,
            'invert'        => true,
            'brightness'    => 0,
            'contrast'      => 18,
            'gamma'         => 1.25,
            'highlight_cap' => 58,
            'sharpness'     => 140,
            'dither_mode'   => 'jarvis',
            'threshold'     => 128,
        ];

        // A) Studio-Vorschau: rohlingsecht positiv (Silber-Weiß 242,245,250 auf Eloxal-Anthrazit 18,21,28)
        $previewGd = $this->decodeDataUrlToGd(LaserPhotoPreprocessor::renderStudioPreviewDataUrl($absRaw, $params, 640));
        $silverPixelRgb = (242 << 16) | (245 << 8) | 250;
        $anthracitePixelRgb = (18 << 16) | (21 << 8) | 28;
        $previewSilverCount = 0;
        $previewAnthraciteCount = 0;
        $previewOtherCount = 0;

        $facePreviewSum = 0.0;
        $facePreviewN = 0;
        $bgPreviewSum = 0.0;
        $bgPreviewN = 0;

        for ($y = 40; $y < 440; $y += 2) {
            for ($x = 40; $x < 600; $x += 2) {
                $rgb = imagecolorat($previewGd, $x, $y) & 0xFFFFFF;
                if ($rgb === $silverPixelRgb) {
                    $previewSilverCount++;
                } elseif ($rgb === $anthracitePixelRgb) {
                    $previewAnthraciteCount++;
                } else {
                    $previewOtherCount++;
                }

                $lum = ($rgb >> 16) & 0xFF;
                if ($x >= 270 && $x <= 370 && $y >= 190 && $y <= 290) {
                    $facePreviewSum += $lum;
                    $facePreviewN++;
                } elseif ($x >= 40 && $x <= 140 && $y >= 40 && $y <= 140) {
                    $bgPreviewSum += $lum;
                    $bgPreviewN++;
                }
            }
        }
        imagedestroy($previewGd);

        $this->assertSame(0, $previewOtherCount, 'Dunkle Rohlings-Vorschau im Jarvis-Modus darf nur Silber-Weiß (242,245,250) und Anthrazit (18,21,28) enthalten');
        $this->assertGreaterThan(500, $previewSilverCount);
        $this->assertGreaterThan(500, $previewAnthraciteCount);
        $this->assertGreaterThan(
            ($bgPreviewSum / $bgPreviewN) + 45.0,
            $facePreviewSum / $facePreviewN,
            'Helles Gesicht muss in der Studio-Vorschau deutlich heller erscheinen als der dunkle Hintergrund'
        );

        // B) Laser-Export: 100 % xTool-Konvention (0 = Schwarz = Laser feuert, 255 = Weiß = Laser pausiert) & 0 Streifen!
        foreach (['jarvis', 'stucki'] as $mode) {
            $exportRel = LaserPhotoPreprocessor::saveStudioProcessedLaserPng($absRaw, array_merge($params, ['dither_mode' => $mode]));
            $this->cleanupPublicRelPaths[] = $exportRel;
            $laserGd = imagecreatefromstring((string) Storage::disk('public')->get($exportRel));
            $this->assertNotFalse($laserGd);

            $lw = imagesx($laserGd);
            $lh = imagesy($laserGd);
            $colMeans = array_fill(0, $lw, 0.0);
            $nonBinaryLaser = 0;

            for ($y = 0; $y < $lh; $y++) {
                for ($x = 0; $x < $lw; $x++) {
                    $c = imagecolorat($laserGd, $x, $y) & 0xFFFFFF;
                    if ($c !== 0x000000 && $c !== 0xFFFFFF) {
                        $nonBinaryLaser++;
                    }
                    $colMeans[$x] += ($c & 0xFF);
                }
            }
            for ($x = 0; $x < $lw; $x++) {
                $colMeans[$x] /= $lh;
            }

            // Prüfe 8px-Spaltengrenzen vs. innere Spaltenschritte (bei 1/8 Nearest-Neighbor sprangen x % 8 === 0 signifikant höher)
            $boundaryDiffSum = 0.0;
            $boundaryN = 0;
            $innerDiffSum = 0.0;
            $innerN = 0;
            for ($x = 8; $x < $lw - 8; $x++) {
                $diff = abs($colMeans[$x] - $colMeans[$x - 1]);
                if (($x & 7) === 0) {
                    $boundaryDiffSum += $diff;
                    $boundaryN++;
                } else {
                    $innerDiffSum += $diff;
                    $innerN++;
                }
            }
            $boundaryAvg = $boundaryDiffSum / max(1, $boundaryN);
            $innerAvg = $innerDiffSum / max(1, $innerN);

            imagedestroy($laserGd);

            $this->assertSame(0, $nonBinaryLaser, "{$mode}: Laser-Export muss strikt binär 0 (Laser feuert) / 255 (Laser pausiert) bleiben");
            $this->assertLessThanOrEqual($innerAvg * 1.25, $boundaryAvg, "{$mode}: Es dürfen keine 8-Pixel-Spaltenstreifen an (x % 8 == 0) auftreten");
        }
    }
}

<?php

namespace App\Services\Laser;

use App\Models\System\SystemSetting;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LaserQrCodeService
{
    public const PRESET_SETTING_KEY = 'laser_studio_custom_presets_v1';

    /**
     * Erzeugt einen gestochen scharfen, lasertauglichen 1-Bit Vektor-QR-Code (SVG ohne weißen Kasten-Hintergrund,
     * reine schwarze Pfade in direkten viewBox-Koordinaten ohne verschachtelte <g transform=...>)
     * und legt ihn im öffentlichen Storage ab.
     *
     * @return array{url: string, storage_path: string, svg: string}
     */
    public static function generateLaserReadyQrSvg(string $targetContent, int $marginModules = 1): array
    {
        $clean = trim($targetContent);
        if ($clean === '') {
            $clean = 'https://mein-seelenfunke.de';
        }

        $margin = max(0, min(4, $marginModules));
        $hash = substr(hash('sha256', 'v2flat|' . $clean . '|m' . $margin), 0, 20);
        $relPath = "laser-studio/qr/qr_{$hash}.svg";
        $publicDisk = Storage::disk('public');

        if ($publicDisk->exists($relPath)) {
            $existingSvg = (string) $publicDisk->get($relPath);
            if ($existingSvg !== '' && !str_contains($existingSvg, 'transform=') && !str_contains($existingSvg, '#fefefe')) {
                return [
                    'url' => '/storage/' . $relPath,
                    'storage_path' => $relPath,
                    'svg' => $existingSvg,
                ];
            }
        }

        $qrCode = Encoder::encode($clean, ErrorCorrectionLevel::M(), Encoder::DEFAULT_BYTE_MODE_ECODING);
        $matrix = $qrCode->getMatrix();
        $mWidth = $matrix->getWidth();
        $mHeight = $matrix->getHeight();
        $totalModules = max(1, $mWidth + (2 * $margin));
        $viewSize = 400.0;
        $cell = $viewSize / $totalModules;

        $dParts = [];
        for ($y = 0; $y < $mHeight; $y++) {
            $x = 0;
            while ($x < $mWidth) {
                if ($matrix->get($x, $y) !== 1) {
                    $x++;
                    continue;
                }
                $runStart = $x;
                while ($x < $mWidth && $matrix->get($x, $y) === 1) {
                    $x++;
                }
                $x0 = round(($runStart + $margin) * $cell, 3);
                $x1 = round(($x + $margin) * $cell, 3);
                $y0 = round(($y + $margin) * $cell, 3);
                $y1 = round(($y + 1 + $margin) * $cell, 3);
                $dParts[] = "M{$x0} {$y0}L{$x1} {$y0}L{$x1} {$y1}L{$x0} {$y1}Z";
            }
        }

        $dAttr = implode('', $dParts);
        $laserSvg = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="400" height="400" viewBox="0 0 400 400" shape-rendering="crispEdges">'
            . '<path d="' . $dAttr . '" fill="#000000"/>'
            . '</svg>';

        $publicDisk->put($relPath, $laserSvg);

        return [
            'url' => '/storage/' . $relPath,
            'storage_path' => $relPath,
            'svg' => $laserSvg,
        ];
    }

    /**
     * Schnellwahl-Links für Alinas Werbe-, Geschenk- & Social-Media-Karten.
     *
     * @return array<string, array{label: string, url: string, subtitle: string}>
     */
    public static function getQuickLinkTemplates(): array
    {
        return [
            'linktree' => [
                'label' => 'Linktree (/links)',
                'url' => 'https://mein-seelenfunke.de/links',
                'subtitle' => 'Alle Kanäle auf einen Blick',
            ],
            'shop' => [
                'label' => 'Online-Shop',
                'url' => 'https://mein-seelenfunke.de',
                'subtitle' => 'mein-seelenfunke.de',
            ],
            'instagram' => [
                'label' => 'Instagram',
                'url' => 'https://www.instagram.com/meinseelenfunke',
                'subtitle' => '@meinseelenfunke',
            ],
            'tiktok' => [
                'label' => 'TikTok',
                'url' => 'https://www.tiktok.com/@meinseelenfunke',
                'subtitle' => '@meinseelenfunke',
            ],
        ];
    }

    /**
     * Werkseitig mitgelieferte Material-Presets für den xTool F2 Ultra UV (5W UV-Laser) + Alinas eigene gespeicherte Studio-Presets.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAllStudioMaterialPresets(): array
    {
        $defaults = [
            [
                'id' => 'builtin_card_black_alu',
                'name' => 'Geschenkkarte / Metallkarte Schwarz (Eloxiert)',
                'is_custom' => false,
                'surface_tone' => 'dark_card',
                'params' => [
                    'equipment_key' => 'flatbed_standard',
                    'process_mode' => 'Prozess auf flacher Oberfläche',
                    'material' => 'Eloxiertes Aluminium',
                    'focus_mm' => 0.5,
                    'element_type' => 'Bild',
                    'engrave_tab' => 'Gravieren',
                    'preset_mode' => 'Benutzerdefiniert',
                    'power_percent' => 65,
                    'speed_mms' => 350,
                    'dpi' => 500,
                    'dot_duration_us' => 90,
                    'frequency_khz' => 60,
                    'passes' => 1,
                    'bitmap_mode' => 'Schwellenwert',
                    'engrave_mode' => 'Bidirektional',
                ],
            ],
            [
                'id' => 'builtin_card_gold_metal',
                'name' => 'Geschenkkarte Gold (Metall / Beschichtet)',
                'is_custom' => false,
                'surface_tone' => 'gold_card',
                'params' => [
                    'equipment_key' => 'flatbed_standard',
                    'process_mode' => 'Prozess auf flacher Oberfläche',
                    'material' => 'Messing / Vergoldetes Metall',
                    'focus_mm' => 0.5,
                    'element_type' => 'Bild',
                    'engrave_tab' => 'Gravieren',
                    'preset_mode' => 'Benutzerdefiniert',
                    'power_percent' => 75,
                    'speed_mms' => 260,
                    'dpi' => 500,
                    'dot_duration_us' => 110,
                    'frequency_khz' => 50,
                    'passes' => 1,
                    'bitmap_mode' => 'Schwellenwert',
                    'engrave_mode' => 'Bidirektional',
                ],
            ],
            [
                'id' => 'builtin_stainless_14k',
                'name' => '14k Vergoldeter Edelstahl (Schmuck)',
                'is_custom' => false,
                'surface_tone' => 'gold_card',
                'params' => [
                    'equipment_key' => 'flatbed_standard',
                    'process_mode' => 'Prozess auf flacher Oberfläche',
                    'material' => 'Edelstahl',
                    'focus_mm' => 1.8,
                    'element_type' => 'Bild',
                    'engrave_tab' => 'Gravieren',
                    'preset_mode' => 'Benutzerdefiniert',
                    'power_percent' => 80,
                    'speed_mms' => 200,
                    'dpi' => 600,
                    'dot_duration_us' => 130,
                    'frequency_khz' => 45,
                    'passes' => 1,
                    'bitmap_mode' => 'Jarvis',
                    'engrave_mode' => 'Bidirektional',
                ],
            ],
            [
                'id' => 'builtin_slate_plate',
                'name' => 'Naturschiefer (Hellgraue UV-Gravur)',
                'is_custom' => false,
                'surface_tone' => 'dark_card',
                'params' => [
                    'equipment_key' => 'flatbed_standard',
                    'process_mode' => 'Prozess auf flacher Oberfläche',
                    'material' => 'Schiefer',
                    'focus_mm' => 5.0,
                    'element_type' => 'Bild',
                    'engrave_tab' => 'Gravieren',
                    'preset_mode' => 'Benutzerdefiniert',
                    'power_percent' => 75,
                    'speed_mms' => 240,
                    'dpi' => 450,
                    'dot_duration_us' => 120,
                    'frequency_khz' => 55,
                    'passes' => 1,
                    'bitmap_mode' => 'Jarvis',
                    'engrave_mode' => 'Bidirektional',
                ],
            ],
            [
                'id' => 'builtin_k9_crystal',
                'name' => 'K9 Kristallglas (UV-Oberfläche)',
                'is_custom' => false,
                'surface_tone' => 'light_surface',
                'params' => [
                    'equipment_key' => 'flatbed_standard',
                    'process_mode' => 'Prozess auf flacher Oberfläche',
                    'material' => 'Kristallglas K9',
                    'focus_mm' => 33.0,
                    'element_type' => 'Bild',
                    'engrave_tab' => 'Gravieren',
                    'preset_mode' => 'Benutzerdefiniert',
                    'power_percent' => 85,
                    'speed_mms' => 180,
                    'dpi' => 600,
                    'dot_duration_us' => 140,
                    'frequency_khz' => 45,
                    'passes' => 1,
                    'bitmap_mode' => 'Jarvis',
                    'engrave_mode' => 'Bidirektional',
                ],
            ],
            [
                'id' => 'builtin_cardstock_paper',
                'name' => 'Edel-Karton / Schwarz-Gold-Papierkarte',
                'is_custom' => false,
                'surface_tone' => 'dark_card',
                'params' => [
                    'equipment_key' => 'flatbed_standard',
                    'process_mode' => 'Prozess auf flacher Oberfläche',
                    'material' => 'Kraftpapier / Karton',
                    'focus_mm' => 0.4,
                    'element_type' => 'Bild',
                    'engrave_tab' => 'Gravieren',
                    'preset_mode' => 'Benutzerdefiniert',
                    'power_percent' => 35,
                    'speed_mms' => 420,
                    'dpi' => 400,
                    'dot_duration_us' => 60,
                    'frequency_khz' => 80,
                    'passes' => 1,
                    'bitmap_mode' => 'Schwellenwert',
                    'engrave_mode' => 'Bidirektional',
                ],
            ],
        ];

        return array_merge($defaults, self::getCustomStudioPresets());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getCustomStudioPresets(): array
    {
        if (!Schema::hasTable('system_settings')) {
            return [];
        }

        $raw = SystemSetting::where('key', self::PRESET_SETTING_KEY)->value('value');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn ($p) => is_array($p) && !empty($p['id']) && !empty($p['name'])));
    }

    /**
     * Speichert aktuelle Laserparameter als eigenes wiederverwendbares Studio-Preset.
     *
     * @param array<string, mixed> $laserParams
     * @return array<string, mixed>
     */
    public static function saveCustomStudioPreset(string $name, array $laserParams, string $surfaceTone = 'dark_card'): array
    {
        $cleanName = mb_substr(trim($name), 0, 90);
        if ($cleanName === '') {
            $cleanName = 'Eigenes Studio-Preset';
        }

        $customs = self::getCustomStudioPresets();
        $slugId = 'custom_' . (Str::slug($cleanName) ?: Str::lower(Str::random(6)));

        $newPreset = [
            'id' => $slugId,
            'name' => $cleanName,
            'is_custom' => true,
            'surface_tone' => in_array($surfaceTone, ['dark_card', 'gold_card', 'light_surface'], true) ? $surfaceTone : 'dark_card',
            'params' => [
                'equipment_key' => (string) ($laserParams['equipment_key'] ?? 'flatbed_standard'),
                'process_mode' => (string) ($laserParams['process_mode'] ?? 'Prozess auf flacher Oberfläche'),
                'material' => (string) ($laserParams['material'] ?? $cleanName),
                'focus_mm' => round(max(0.0, min(180.0, (float) ($laserParams['focus_mm'] ?? 1.0))), 2),
                'element_type' => (string) ($laserParams['element_type'] ?? 'Bild'),
                'engrave_tab' => (string) ($laserParams['engrave_tab'] ?? 'Gravieren'),
                'preset_mode' => 'Benutzerdefiniert',
                'power_percent' => max(1, min(100, (int) ($laserParams['power_percent'] ?? 70))),
                'speed_mms' => max(5, min(4000, (int) ($laserParams['speed_mms'] ?? 250))),
                'dpi' => max(50, min(2540, (int) ($laserParams['dpi'] ?? 500))),
                'dot_duration_us' => max(1, min(3000, (int) ($laserParams['dot_duration_us'] ?? 100))),
                'frequency_khz' => max(40, min(200, (int) ($laserParams['frequency_khz'] ?? 60))),
                'passes' => max(1, min(20, (int) ($laserParams['passes'] ?? 1))),
                'bitmap_mode' => (string) ($laserParams['bitmap_mode'] ?? 'Schwellenwert'),
                'engrave_mode' => (string) ($laserParams['engrave_mode'] ?? 'Bidirektional'),
            ],
        ];

        $replaced = false;
        foreach ($customs as $idx => $existing) {
            if (($existing['id'] ?? '') === $slugId || strcasecmp((string) ($existing['name'] ?? ''), $cleanName) === 0) {
                $customs[$idx] = $newPreset;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $customs[] = $newPreset;
        }

        if (Schema::hasTable('system_settings')) {
            SystemSetting::updateOrCreate(
                ['key' => self::PRESET_SETTING_KEY],
                ['value' => json_encode(array_values($customs), JSON_UNESCAPED_UNICODE)]
            );
        }

        return $newPreset;
    }

    public static function deleteCustomStudioPreset(string $presetId): bool
    {
        if (!str_starts_with($presetId, 'custom_') || !Schema::hasTable('system_settings')) {
            return false;
        }

        $customs = self::getCustomStudioPresets();
        $filtered = array_values(array_filter($customs, static fn ($p) => ($p['id'] ?? '') !== $presetId));
        if (count($filtered) === count($customs)) {
            return false;
        }

        SystemSetting::updateOrCreate(
            ['key' => self::PRESET_SETTING_KEY],
            ['value' => json_encode($filtered, JSON_UNESCAPED_UNICODE)]
        );

        return true;
    }
}

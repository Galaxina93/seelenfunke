<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class ConfiguratorService
{
    protected static array $overlayAnalysisCache = [];

    /**
     * Analysiert das 2D-Overlay-Bild (PNG/JPG/WEBP) intelligent auf:
     * 1. Transparenz am Außenrand (Cutout-PNG wie beim Seelenanhänger vs. Vollbild-Szene)
     * 2. Durchschnittliche Helligkeit (Luminanz) der Gravurfläche, um automatisch zwischen
     *    dunkler Gravur (auf hellem Grund) und silber-weißer Lasergravur (auf schwarzem Grund wie beim Seelenanhänger) umzuschalten.
     */
    public function analyzeOverlayImage(?string $previewImagePath, array $configSettings = []): array
    {
        $pkgKey = $configSettings['template_package'] ?? 'custom';
        $packages = $this->getTemplatePackages();
        if (!$previewImagePath && isset($packages[$pkgKey]['preview_image_path'])) {
            $previewImagePath = $packages[$pkgKey]['preview_image_path'];
        }

        $fallbackDark = ($configSettings['material_type'] ?? '') === 'metal' || $pkgKey === 'seelen-anhaenger';
        $fallbackTrans = ($configSettings['area_shape'] ?? '') === 'custom' || $pkgKey === 'seelen-anhaenger';

        if (!$previewImagePath) {
            return [
                'is_dark_surface'         => $fallbackDark,
                'has_transparent_overlay' => $fallbackTrans,
                'surface_luminance'       => $fallbackDark ? 10.0 : 140.0,
            ];
        }

        $cleanRel = ltrim(str_replace('\\', '/', preg_replace('/\?.*$/', '', $previewImagePath)), '/');
        if (str_contains($cleanRel, '..') || str_contains($cleanRel, "\0")) {
            return [
                'is_dark_surface'         => $fallbackDark,
                'has_transparent_overlay' => $fallbackTrans,
                'surface_luminance'       => $fallbackDark ? 10.0 : 140.0,
            ];
        }
        if (str_starts_with($cleanRel, 'storage/')) {
            $cleanRel = substr($cleanRel, strlen('storage/'));
        }

        $diskPath = storage_path('app/public/' . $cleanRel);
        $realDiskPath = realpath($diskPath);
        $allowedRoot = realpath(storage_path('app/public'));
        if ($realDiskPath === false || !is_file($realDiskPath) || ($allowedRoot !== false && !str_starts_with($realDiskPath, $allowedRoot . DIRECTORY_SEPARATOR))) {
            return [
                'is_dark_surface'         => $fallbackDark,
                'has_transparent_overlay' => $fallbackTrans,
                'surface_luminance'       => $fallbackDark ? 10.0 : 140.0,
            ];
        }

        $cacheKey = $diskPath . ':' . @filemtime($diskPath);
        if (isset(self::$overlayAnalysisCache[$cacheKey])) {
            return self::$overlayAnalysisCache[$cacheKey];
        }

        $ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));
        $im = match ($ext) {
            'png'         => @imagecreatefrompng($diskPath),
            'jpg', 'jpeg' => @imagecreatefromjpeg($diskPath),
            'webp'        => @imagecreatefromwebp($diskPath),
            default       => null,
        };

        if (!$im) {
            return self::$overlayAnalysisCache[$cacheKey] = [
                'is_dark_surface'         => $fallbackDark,
                'has_transparent_overlay' => $fallbackTrans,
                'surface_luminance'       => $fallbackDark ? 10.0 : 140.0,
            ];
        }

        $w = imagesx($im);
        $h = imagesy($im);

        // 1. Prüfe Ecken auf Alpha-Transparenz (PHP GD: 0 = deckend, 127 = voll transparent)
        $corners = [
            [2, 2],
            [max(0, $w - 3), 2],
            [2, max(0, $h - 3)],
            [max(0, $w - 3), max(0, $h - 3)],
        ];
        $transparentCorners = 0;
        foreach ($corners as [$cx, $cy]) {
            $rgba = @imagecolorsforindex($im, @imagecolorat($im, $cx, $cy));
            if (is_array($rgba) && ($rgba['alpha'] ?? 0) > 64) {
                $transparentCorners++;
            }
        }
        $hasTransparency = $transparentCorners >= 2;

        // 2. Messe die durchschnittliche Luminanz im zentralen Gravurbereich (30% bis 70%)
        $lumSum = 0.0;
        $sampleCount = 0;
        $stepX = max(1, (int) floor($w / 25));
        $stepY = max(1, (int) floor($h / 25));

        for ($x = (int) floor($w * 0.30); $x <= (int) floor($w * 0.70); $x += $stepX) {
            for ($y = (int) floor($h * 0.30); $y <= (int) floor($h * 0.70); $y += $stepY) {
                $rgba = @imagecolorsforindex($im, @imagecolorat($im, $x, $y));
                if (is_array($rgba) && ($rgba['alpha'] ?? 0) < 100) {
                    $lumSum += (0.299 * $rgba['red']) + (0.587 * $rgba['green']) + (0.114 * $rgba['blue']);
                    $sampleCount++;
                }
            }
        }

        imagedestroy($im);

        $avgLuminance = $sampleCount > 0 ? round($lumSum / $sampleCount, 1) : 140.0;
        $isDarkSurface = $avgLuminance < 65.0;

        return self::$overlayAnalysisCache[$cacheKey] = [
            'is_dark_surface'         => $isDarkSurface,
            'has_transparent_overlay' => $hasTransparency,
            'surface_luminance'       => $avgLuminance,
        ];
    }

    /**
     * Gibt die Standard-Schriftarten für den Konfigurator zurück.
     * So hast du nur eine zentrale Stelle, wenn neue Fonts hinzukommen.
     */
    public function getFonts(): array
    {
        return [
            "Arial"            => "Arial, sans-serif",
            "Times New Roman"  => "Times New Roman, serif",
            "Verdana"          => "Verdana, sans-serif",
            "Courier New"      => "Courier New, monospace",
            "Georgia"          => "Georgia, serif",
            "ABeeZee"          => "'ABeeZee', sans-serif",
            "Alex Brush"       => "'Alex Brush', cursive",
            "Bebas Neue"       => "'Bebas Neue', sans-serif",
            "Caveat"           => "'Caveat', cursive",
            "Cinzel"           => "'Cinzel', serif",
            "Comfortaa"        => "'Comfortaa', cursive",
            "Dancing Script"   => "'Dancing Script', cursive",
            "Great Vibes"      => "'Great Vibes', cursive",
            "Lobster"          => "'Lobster', cursive",
            "Lobster Two"      => "'Lobster Two', cursive",
            "Marck Script"     => "'Marck Script', cursive",
            "Montserrat"       => "'Montserrat', sans-serif",
            "Pacifico"         => "'Pacifico', cursive",
            "Playfair Display" => "'Playfair Display', serif",
            "Righteous"        => "'Righteous', cursive",
            "Sacramento"       => "'Sacramento', cursive",
            "Satisfy"          => "'Satisfy', cursive"
        ];
    }

    /**
     * Liest alle aktiven (nicht archivierten) Symbole über den ProductConfiguratorService
     * aus storage/app/public/produkte/configurator/vectors (inkl. Kategorien-Sortierung & benutzerdefinierten Titeln).
     */
    public function getStandardVectors(): array
    {
        return app(ProductConfiguratorService::class)->getActiveVectorsGrouped();
    }

    /**
     * Zentrale Definition aller Standard-Werte für das 3D/2D Setup.
     */
    public function getDefaultSettings(): array
    {
        return [
            // 2D Area
            'area_shape'   => 'rect',
            'area_left'    => 10,
            'area_top'     => 10,
            'area_width'   => 80,
            'area_height'  => 80,
            'custom_points'=> [
                ['x' => 20, 'y' => 20],
                ['x' => 80, 'y' => 20],
                ['x' => 80, 'y' => 80],
                ['x' => 20, 'y' => 80]
            ],

            // 3D Model
            'overlay_type' => 'plane',
            'material_type'=> 'glass',
            'model_scale'  => 100,
            'model_pos_x'  => 0,
            'model_pos_y'  => 0,
            'model_pos_z'  => 0,
            'model_rot_x'  => 0,
            'model_rot_y'  => 0,
            'model_rot_z'  => 0,

            // 3D Engraving / Overlay
            'engraving_scale' => 100,
            'engraving_pos_x' => 0,
            'engraving_pos_y' => 0,
            'engraving_pos_z' => 0,
            'engraving_rot_x' => 0,
            'engraving_rot_y' => 0,
            'engraving_rot_z' => 0,

            // Funktionalität & Vorlage-Paket / Themen-Hintergrund
            'allow_logo'               => true,
            'allow_text_pos'           => true,
            'template_package'         => 'custom',
            'enable_background_video'  => true,
            'default_background_theme' => 'standard',
            'background_video_path'    => null,
        ];
    }

    /**
     * Gibt alle verfügbaren Vorlage-Pakete (2D Vorschau Overlay, 3D Hintergrund & Themen-Videos) zurück.
     */
    public function getTemplatePackages(): array
    {
        return [
            'seelen-kristall' => [
                'key'                     => 'seelen-kristall',
                'name'                    => 'Seelen Kristall Paket (Glas-Facette & Manufaktur)',
                'description'             => 'Transparenter 2D K9-Kristall-Facettenring + 3D Manufaktur-Holztisch inkl. Themen-Videos',
                'preview_image_path'      => 'produkte/products/seelen-kristall/overlay.png',
                'three_d_background_path' => 'produkte/products/seelen-kristall/header_bg.png',
                'three_d_model_path'      => 'produkte/products/seelen-kristall/t_seelenk.glb',
                'default_background_theme'=> 'standard',
                'material_type'           => 'glass',
                'area_shape'              => 'circle',
            ],
            'seelen-anhaenger' => [
                'key'                     => 'seelen-anhaenger',
                'name'                    => 'Seelenanhänger Paket (Alu-Herz & Manufaktur)',
                'description'             => 'Transparenter 2D Aluminium-Herz Cutout + 3D Manufaktur-Holztisch inkl. Themen-Videos',
                'preview_image_path'      => 'produkte/products/seelen-anhaenger/overlay.png',
                'three_d_background_path' => 'produkte/products/seelen-anhaenger/header_bg.png',
                'three_d_model_path'      => 'produkte/products/seelen-anhaenger/seelenanhaenger_3d_.glb',
                'default_background_theme'=> 'standard',
                'material_type'           => 'metal',
                'area_shape'              => 'custom',
            ],
            'weizenglas-personalisiert' => [
                'key'                     => 'weizenglas-personalisiert',
                'name'                    => 'Feierabend-Funke Paket (Weizenglas & Manufaktur)',
                'description'             => 'Transparenter 2D Weizenglas Cutout + 3D Manufaktur-Holztisch inkl. Themen-Videos',
                'preview_image_path'      => 'produkte/products/weizenglas-personalisiert/overlay.png',
                'three_d_background_path' => 'produkte/products/weizenglas-personalisiert/header_bg.png',
                'three_d_model_path'      => 'produkte/products/weizenglas-personalisiert/beer_glas_3d.glb',
                'default_background_theme'=> 'standard',
                'material_type'           => 'glass',
                'area_shape'              => 'rect',
            ],
            'custom' => [
                'key'                     => 'custom',
                'name'                    => 'Individuell (Manuelle Datei-Zuweisung)',
                'description'             => 'Eigene 2D Vorschau Overlay (PNG), 3D Hintergrund (.jpg/.png) und Hintergrund-Video frei zuweisen',
                'preview_image_path'      => null,
                'three_d_background_path' => null,
                'three_d_model_path'      => null,
                'default_background_theme'=> 'standard',
            ],
        ];
    }

    /**
     * Gibt alle verfügbaren atmosphärischen Themen-Hintergründe (komprimierte Videos + Fallback-Bilder) zurück.
     */
    public function getBackgroundThemes(): array
    {
        return [
            'standard' => [
                'key'          => 'standard',
                'label'        => 'Manufaktur (Funken)',
                'short_label'  => 'Manufaktur',
                'icon'         => '✨',
                'holiday'      => 'Kein Feiertag (Fallback)',
                'season'       => 'Zwischenzeit / Standard',
                'funki_outfit' => 'Standard Manufaktur-Zylinder',
                'video'        => 'produkte/configurator-presets/videos/standard.mp4',
                'image'        => 'produkte/configurator-presets/backgrounds/standard.jpg',
            ],
            'winter' => [
                'key'          => 'winter',
                'label'        => 'Winter (Schneefall)',
                'short_label'  => 'Winter',
                'icon'         => '❄️',
                'holiday'      => 'Weihnachten & Winter',
                'season'       => 'Winter',
                'funki_outfit' => 'Weihnachtsmütze & Schneeflocken',
                'video'        => 'produkte/configurator-presets/videos/winter.mp4',
                'image'        => 'produkte/configurator-presets/backgrounds/winter.jpg',
            ],
            'ostern' => [
                'key'          => 'ostern',
                'label'        => 'Frühling (Blüten & Ostern)',
                'short_label'  => 'Frühling',
                'icon'         => '🌸',
                'holiday'      => 'Ostern & Frühling',
                'season'       => 'Frühling',
                'funki_outfit' => 'Osterohren & Frühlingsblüten',
                'video'        => 'produkte/configurator-presets/videos/ostern.mp4',
                'image'        => 'produkte/configurator-presets/backgrounds/ostern.jpg',
            ],
            'sommer' => [
                'key'          => 'sommer',
                'label'        => 'Sommer (Sonnenstrahlen)',
                'short_label'  => 'Sommer',
                'icon'         => '☀️',
                'holiday'      => 'Sommer & Sommer Sale',
                'season'       => 'Sommer',
                'funki_outfit' => 'Sommerhut, Sonnenbrille & Sonnenschein',
                'video'        => 'produkte/configurator-presets/videos/sommer.mp4',
                'image'        => 'produkte/configurator-presets/backgrounds/sommer.jpg',
            ],
            'herbst' => [
                'key'          => 'herbst',
                'label'        => 'Herbst (Goldenes Laub)',
                'short_label'  => 'Herbst',
                'icon'         => '🍂',
                'holiday'      => 'Herbst & Halloween',
                'season'       => 'Herbst',
                'funki_outfit' => 'Herbstlaub-Hut & Kürbis-Akzent',
                'video'        => 'produkte/configurator-presets/videos/herbst.mp4',
                'image'        => 'produkte/configurator-presets/backgrounds/herbst.jpg',
            ],
            'hochzeit' => [
                'key'          => 'hochzeit',
                'label'        => 'Romantik (Hochzeit & Liebe)',
                'short_label'  => 'Romantik',
                'icon'         => '💍',
                'holiday'      => 'Valentinstag, Muttertag & Hochzeit',
                'season'       => 'Feiertag (Romantik)',
                'funki_outfit' => 'Romantik-Herzen & Gold-Schleife',
                'video'        => 'produkte/configurator-presets/videos/hochzeit.mp4',
                'image'        => 'produkte/configurator-presets/backgrounds/hochzeit.jpg',
            ],
            'geburtstag' => [
                'key'          => 'geburtstag',
                'label'        => 'Party (Geburtstag & Feier)',
                'short_label'  => 'Party',
                'icon'         => '🎉',
                'holiday'      => 'Neujahr, Vatertag & Geburtstag',
                'season'       => 'Feiertag (Party)',
                'funki_outfit' => 'Partyhut & Fest-Konfetti',
                'video'        => 'produkte/configurator-presets/videos/geburtstag.mp4',
                'image'        => 'produkte/configurator-presets/backgrounds/geburtstag.jpg',
            ],
        ];
    }

    /**
     * Löst einen Feiertags- oder Themen-Namen (z. B. "Weihnachten", "winter", "herbst") in einen gültigen Theme-Schlüssel auf.
     */
    public function resolveThemeKey(?string $themeOrHoliday, string $fallback = 'standard'): string
    {
        if (!$themeOrHoliday) {
            return $fallback;
        }

        $normalized = mb_strtolower(trim($themeOrHoliday));

        return match ($normalized) {
            'winter', 'weihnachten', 'frohe weihnachten', 'nikolaus', 'advent', 'advent_1', 'christmas', 'sale_winter', 'winter sale' => 'winter',
            'ostern', 'frühling', 'fruehling', 'spring', 'frohes osterfest', 'easter', 'womens_day', 'weltfrauentag', 'taufe', 'einschulung' => 'ostern',
            'sommer', 'summer', 'sale_summer', 'sommer sale' => 'sommer',
            'herbst', 'autumn', 'fall', 'halloween', 'trauer' => 'herbst',
            'hochzeit', 'romantik', 'valentinstag', 'valentines', 'lieblingsmensch', 'muttertag', 'mothers_day', 'jubilaeum', 'jubiläum' => 'hochzeit',
            'geburtstag', 'feier', 'party', 'happy birthday', 'vatertag', 'fathers_day', 'new_year', 'neujahr', 'silvester' => 'geburtstag',
            'standard', 'manufaktur', 'kein feiertag' => 'standard',
            default => array_key_exists($normalized, $this->getBackgroundThemes()) ? $normalized : $fallback,
        };
    }

    /**
     * Gibt die Feiertags-Events analog zu MarketingNewsletter zurück.
     */
    public function getAvailableHolidayEvents(): array
    {
        return [
            'new_year'    => ['name' => 'Neujahr (01.01.)',               'theme' => 'geburtstag', 'days_before' => 4,  'days_after' => 2],
            'sale_winter' => ['name' => 'Winter Sale (01.01.)',           'theme' => 'winter',     'days_before' => 0,  'days_after' => 14],
            'valentines'  => ['name' => 'Valentinstag (14.02.)',          'theme' => 'hochzeit',   'days_before' => 10, 'days_after' => 1],
            'womens_day'  => ['name' => 'Weltfrauentag (08.03.)',         'theme' => 'ostern',     'days_before' => 5,  'days_after' => 1],
            'easter'      => ['name' => 'Ostern (Variabel)',              'theme' => 'ostern',     'days_before' => 14, 'days_after' => 2],
            'mothers_day' => ['name' => 'Muttertag (2. So im Mai)',       'theme' => 'hochzeit',   'days_before' => 10, 'days_after' => 1],
            'fathers_day' => ['name' => 'Vatertag (Christi Himmelfahrt)', 'theme' => 'geburtstag', 'days_before' => 7,  'days_after' => 1],
            'sale_summer' => ['name' => 'Sommer Sale (01.07.)',           'theme' => 'sommer',     'days_before' => 5,  'days_after' => 14],
            'halloween'   => ['name' => 'Halloween (31.10.)',             'theme' => 'herbst',     'days_before' => 12, 'days_after' => 1],
            'advent_1'    => ['name' => '1. Advent (Variabel)',           'theme' => 'winter',     'days_before' => 7,  'days_after' => 7],
            'christmas'   => ['name' => 'Weihnachten (24.12.)',           'theme' => 'winter',     'days_before' => 17, 'days_after' => 3],
        ];
    }

    /**
     * Berechnet das exakte Feiertags-Datum für ein Jahr (identisch zu MarketingNewsletter).
     */
    public function getHolidayDate(string $key, int $year): Carbon
    {
        return match ($key) {
            'valentines'  => Carbon::create($year, 2, 14)->startOfDay(),
            'womens_day'  => Carbon::create($year, 3, 8)->startOfDay(),
            'halloween'   => Carbon::create($year, 10, 31)->startOfDay(),
            'christmas'   => Carbon::create($year, 12, 24)->startOfDay(),
            'new_year'    => Carbon::create($year, 1, 1)->startOfDay(),
            'sale_summer' => Carbon::create($year, 7, 1)->startOfDay(),
            'sale_winter' => Carbon::create($year, 1, 4)->startOfDay(),
            'easter'      => $this->getEasterDate($year)->startOfDay(),
            'mothers_day' => Carbon::create($year, 5, 1)->nthOfMonth(2, Carbon::SUNDAY)->startOfDay(),
            'fathers_day' => $this->getEasterDate($year)->addDays(39)->startOfDay(),
            'advent_1'    => Carbon::create($year, 11, 26)->next(Carbon::SUNDAY)->startOfDay(),
            // Jahreszeiten-Eckdaten
            'season_spring' => Carbon::create($year, 3, 20)->startOfDay(),
            'season_summer' => Carbon::create($year, 6, 21)->startOfDay(),
            'season_autumn' => Carbon::create($year, 9, 23)->startOfDay(),
            'season_winter' => Carbon::create($year, 12, 21)->startOfDay(),
            default       => Carbon::now()->startOfDay(),
        };
    }

    /**
     * Gaußsche Osterformel (identisch zu MarketingNewsletter).
     */
    public function getEasterDate(int $year): Carbon
    {
        $K = (int)($year / 100);
        $M = 15 + (int)((3 * $K + 3) / 4) - (int)((8 * $K + 13) / 25);
        $S = 2 - (int)((3 * $K + 3) / 4);
        $A = $year % 19;
        $D = (19 * $A + $M) % 30;
        $R = (int)($D / 29) + ((int)($D / 28) - (int)($D / 29)) * (int)($A / 11);
        $OG = 21 + $D - $R;
        $SZ = 7 - ($year + (int)($year / 4) + $S) % 7;
        $OE = 7 - ($OG - $SZ) % 7;

        return Carbon::createFromDate($year, 3, 1)->addDays($OG + $OE - 1)->startOfDay();
    }

    /**
     * Ermittelt vollautomatisch die aktuell aktive Animation & Jahreszeit/Feiertag.
     * Priorität:
     * 1. Aktives Feiertags-Zeitfenster (aus der Newsletter-Kampagnen-Logik: Valentinstag, Ostern, Muttertag, Vatertag, Halloween, Advent, Weihnachten, Neujahr ...)
     * 2. Aktuelle Jahreszeit (Frühling -> ostern, Sommer -> sommer, Herbst -> herbst, Winter -> winter)
     * 3. Fallback: Standard Manufaktur Animation ('standard')
     */
    public function getCurrentActiveTheme(?Carbon $now = null): array
    {
        $now = ($now ? $now->copy() : Carbon::now())->startOfDay();
        $year = (int) $now->year;
        $themes = $this->getBackgroundThemes();

        // 1. Prüfe alle Feiertags-Zeitfenster (für aktuelles Jahr + Jahreswechsel)
        foreach ([$year - 1, $year, $year + 1] as $checkYear) {
            foreach ($this->getAvailableHolidayEvents() as $eventKey => $meta) {
                $eventDate = $this->getHolidayDate($eventKey, $checkYear);
                $from = $eventDate->copy()->subDays($meta['days_before'])->startOfDay();
                $until = $eventDate->copy()->addDays($meta['days_after'])->endOfDay();

                if ($now->betweenIncluded($from, $until)) {
                    $themeKey = $meta['theme'];
                    $themeObj = $themes[$themeKey] ?? $themes['standard'];
                    return [
                        'theme_key'    => $themeKey,
                        'theme'        => $themeObj,
                        'reason_type'  => 'holiday',
                        'event_key'    => $eventKey,
                        'event_name'   => $meta['name'],
                        'active_from'  => $from,
                        'active_until' => $until,
                        'funki_outfit' => $themeObj['funki_outfit'] ?? 'Standard',
                    ];
                }
            }
        }

        // 2. Prüfe aktuelle Jahreszeit (Frühling, Sommer, Herbst, Winter)
        $springStart = Carbon::create($year, 3, 1)->startOfDay();
        $summerStart = Carbon::create($year, 6, 1)->startOfDay();
        $autumnStart = Carbon::create($year, 9, 1)->startOfDay();
        $winterStart = Carbon::create($year, 12, 1)->startOfDay();

        if ($now->betweenIncluded($springStart, $summerStart->copy()->subDay()->endOfDay())) {
            $themeKey = 'ostern';
            $eventKey = 'season_spring';
            $eventName = 'Jahreszeit: Frühling (01.03. – 31.05.)';
            $from = $springStart;
            $until = $summerStart->copy()->subDay()->endOfDay();
        } elseif ($now->betweenIncluded($summerStart, $autumnStart->copy()->subDay()->endOfDay())) {
            $themeKey = 'sommer';
            $eventKey = 'season_summer';
            $eventName = 'Jahreszeit: Sommer (01.06. – 31.08.)';
            $from = $summerStart;
            $until = $autumnStart->copy()->subDay()->endOfDay();
        } elseif ($now->betweenIncluded($autumnStart, $winterStart->copy()->subDay()->endOfDay())) {
            $themeKey = 'herbst';
            $eventKey = 'season_autumn';
            $eventName = 'Jahreszeit: Herbst (01.09. – 30.11.)';
            $from = $autumnStart;
            $until = $winterStart->copy()->subDay()->endOfDay();
        } else {
            $themeKey = 'winter';
            $eventKey = 'season_winter';
            $from = $now->month === 12 ? $winterStart : Carbon::create($year - 1, 12, 1)->startOfDay();
            $until = ($now->month === 12 ? Carbon::create($year + 1, 3, 1) : $springStart)->copy()->subDay()->endOfDay();
            $eventName = 'Jahreszeit: Winter (01.12. – ' . $until->format('d.m.') . ')';
        }

        $themeObj = $themes[$themeKey] ?? $themes['standard'];

        return [
            'theme_key'    => $themeKey,
            'theme'        => $themeObj,
            'reason_type'  => 'season',
            'event_key'    => $eventKey,
            'event_name'   => $eventName,
            'active_from'  => $from,
            'active_until' => $until,
            'funki_outfit' => $themeObj['funki_outfit'] ?? 'Standard',
        ];
    }

    /**
     * Erstellt die chronologische Timeline aller Feiertags- & Jahreszeiten-Animationen
     * (analog zu getNewsletterTimeline in MarketingNewsletter) für /admin/product-templates.
     */
    public function getAnimationTimeline(?int $year = null): Collection
    {
        $now = Carbon::now()->startOfDay();
        $year = $year ?? (int) $now->year;
        $themes = $this->getBackgroundThemes();
        $activeNow = $this->getCurrentActiveTheme($now);
        $events = [];

        // 1. Feiertags-Kampagnen-Fenster
        foreach ($this->getAvailableHolidayEvents() as $key => $meta) {
            $eventDate = $this->getHolidayDate($key, $year);
            $startDate = $eventDate->copy()->subDays($meta['days_before'])->startOfDay();
            $endDate = $eventDate->copy()->addDays($meta['days_after'])->endOfDay();

            if ($endDate->isPast() && !$endDate->isToday()) {
                $eventDate = $this->getHolidayDate($key, $year + 1);
                $startDate = $eventDate->copy()->subDays($meta['days_before'])->startOfDay();
                $endDate = $eventDate->copy()->addDays($meta['days_after'])->endOfDay();
            }

            $themeKey = $meta['theme'];
            $themeObj = $themes[$themeKey] ?? $themes['standard'];
            $vidDisk = storage_path('app/public/' . $themeObj['video']);
            $imgDisk = storage_path('app/public/' . $themeObj['image']);

            $isActiveWindow = $now->betweenIncluded($startDate, $endDate);

            $events[] = [
                'date'          => $startDate,
                'event_date'    => $eventDate,
                'end_date'      => $endDate,
                'title'         => $themeObj['icon'] . ' ' . $themeObj['label'],
                'subtitle'      => 'Zeitraum: ' . $startDate->format('d.m.') . ' – ' . $endDate->format('d.m.Y') . ' • Funki: ' . $themeObj['funki_outfit'],
                'event_key'     => $key,
                'event_name'    => $meta['name'],
                'theme_key'     => $themeKey,
                'theme'         => $themeObj,
                'is_active_now' => $isActiveWindow && ($activeNow['event_key'] === $key),
                'video_path'    => 'storage/app/public/' . $themeObj['video'],
                'video_url'     => asset('storage/' . $themeObj['video']),
                'video_kb'      => is_file($vidDisk) ? (int) max(1, round(filesize($vidDisk) / 1024)) : null,
                'image_path'    => 'storage/app/public/' . $themeObj['image'],
                'image_url'     => asset('storage/' . $themeObj['image']),
                'image_kb'      => is_file($imgDisk) ? (int) max(1, round(filesize($imgDisk) / 1024)) : null,
            ];
        }

        // 2. Die 4 Jahreszeiten als Basis-Animationen
        $seasons = [
            'season_spring' => ['name' => 'Frühling (Basis-Jahreszeit)', 'theme' => 'ostern', 'month' => 3,  'day' => 1, 'end_month' => 5,  'end_day' => 31],
            'season_summer' => ['name' => 'Sommer (Basis-Jahreszeit)',   'theme' => 'sommer', 'month' => 6,  'day' => 1, 'end_month' => 8,  'end_day' => 31],
            'season_autumn' => ['name' => 'Herbst (Basis-Jahreszeit)',   'theme' => 'herbst', 'month' => 9,  'day' => 1, 'end_month' => 11, 'end_day' => 30],
            'season_winter' => ['name' => 'Winter (Basis-Jahreszeit)',   'theme' => 'winter', 'month' => 12, 'day' => 1, 'end_month' => 2,  'end_day' => 28],
        ];

        foreach ($seasons as $sKey => $sMeta) {
            $startDate = Carbon::create($year, $sMeta['month'], $sMeta['day'])->startOfDay();
            $endYear = ($sMeta['end_month'] < $sMeta['month']) ? $year + 1 : $year;
            $endDate = $sMeta['end_month'] === 2
                ? Carbon::create($endYear, 2, 1)->endOfMonth()->endOfDay()
                : Carbon::create($endYear, $sMeta['end_month'], $sMeta['end_day'])->endOfDay();

            // Für Winter im Januar/Februar aktuelles Fenster berücksichtigen
            if ($sKey === 'season_winter' && $now->month <= 2) {
                $startDate = Carbon::create($year - 1, 12, 1)->startOfDay();
                $endDate = Carbon::create($year, 2, 1)->endOfMonth()->endOfDay();
            }

            if ($endDate->isPast() && !$endDate->isToday()) {
                $startDate = Carbon::create($year + 1, $sMeta['month'], $sMeta['day'])->startOfDay();
                $endYearNext = ($sMeta['end_month'] < $sMeta['month']) ? $year + 2 : $year + 1;
                $endDate = $sMeta['end_month'] === 2
                    ? Carbon::create($endYearNext, 2, 1)->endOfMonth()->endOfDay()
                    : Carbon::create($endYearNext, $sMeta['end_month'], $sMeta['end_day'])->endOfDay();
            }

            $themeKey = $sMeta['theme'];
            $themeObj = $themes[$themeKey] ?? $themes['standard'];
            $vidDisk = storage_path('app/public/' . $themeObj['video']);
            $imgDisk = storage_path('app/public/' . $themeObj['image']);

            $isActiveSeason = $now->betweenIncluded($startDate, $endDate);

            $events[] = [
                'date'          => $startDate,
                'event_date'    => $startDate,
                'end_date'      => $endDate,
                'title'         => $themeObj['icon'] . ' ' . $themeObj['label'] . ' (Jahreszeit)',
                'subtitle'      => 'Saison: ' . $startDate->format('d.m.') . ' – ' . $endDate->format('d.m.Y') . ' • Funki: ' . $themeObj['funki_outfit'],
                'event_key'     => $sKey,
                'event_name'    => $sMeta['name'],
                'theme_key'     => $themeKey,
                'theme'         => $themeObj,
                'is_active_now' => $isActiveSeason && ($activeNow['event_key'] === $sKey),
                'video_path'    => 'storage/app/public/' . $themeObj['video'],
                'video_url'     => asset('storage/' . $themeObj['video']),
                'video_kb'      => is_file($vidDisk) ? (int) max(1, round(filesize($vidDisk) / 1024)) : null,
                'image_path'    => 'storage/app/public/' . $themeObj['image'],
                'image_url'     => asset('storage/' . $themeObj['image']),
                'image_kb'      => is_file($imgDisk) ? (int) max(1, round(filesize($imgDisk) / 1024)) : null,
            ];
        }

        // Sortiere: Aktuell aktive Animation zuerst, danach chronologisch nach Startdatum
        return collect($events)->sortBy(function ($item) {
            return ($item['is_active_now'] ? '0_' : '1_') . $item['date']->format('Ymd');
        })->values();
    }

    /**
     * Stellt sicher, dass keine JS-Fehler im Frontend/Backend entstehen,
     * weil ein Wert im Array fehlt oder ungültige Werte/Extreme übergeben werden.
     */
    public function mergeWithDefaults(?array $savedConfig): array
    {
        if (!$savedConfig) {
            $savedConfig = [];
        }

        $merged = array_merge($this->getDefaultSettings(), $savedConfig);

        // Clamp 2D coordinates and dimensions (0..100%, width/height min 1%)
        $merged['area_left'] = max(0, min(100, is_numeric($merged['area_left'] ?? null) ? (float)$merged['area_left'] : 10));
        $merged['area_top'] = max(0, min(100, is_numeric($merged['area_top'] ?? null) ? (float)$merged['area_top'] : 10));
        $merged['area_width'] = max(1, min(100, is_numeric($merged['area_width'] ?? null) ? (float)$merged['area_width'] : 80));
        $merged['area_height'] = max(1, min(100, is_numeric($merged['area_height'] ?? null) ? (float)$merged['area_height'] : 80));

        // Clamp 3D model & engraving scales (10% bis 1000% verhindert WebGL Matrix-Inversions- und Pufferfehler)
        $merged['model_scale'] = max(10, min(1000, is_numeric($merged['model_scale'] ?? null) ? (float)$merged['model_scale'] : 100));
        $merged['engraving_scale'] = max(10, min(1000, is_numeric($merged['engraving_scale'] ?? null) ? (float)$merged['engraving_scale'] : 100));

        // Validate and clamp custom_points
        if (isset($merged['custom_points']) && is_array($merged['custom_points'])) {
            $sanitizedPoints = [];
            foreach ($merged['custom_points'] as $pt) {
                if (is_array($pt) && isset($pt['x'], $pt['y']) && is_numeric($pt['x']) && is_numeric($pt['y'])) {
                    $sanitizedPoints[] = [
                        'x' => max(0, min(100, (float)$pt['x'])),
                        'y' => max(0, min(100, (float)$pt['y'])),
                    ];
                }
            }
            if (!empty($sanitizedPoints)) {
                $merged['custom_points'] = $sanitizedPoints;
            } else {
                $merged['custom_points'] = $this->getDefaultSettings()['custom_points'];
            }
        }

        return $merged;
    }

    /**
     * Erzeugt aus dem Roh-Dateinamen einen kundenfreundlichen deutschen Titel für Tooltips.
     */
    public function formatVectorTitle(string $rawName): string
    {
        $clean = str_replace(['-', '_'], ' ', $rawName);
        $name = ucwords($clean);

        $replacements = [
            'Fuesse' => 'Füße',
            'Fuss' => 'Fuß',
            'Laecheln' => 'Lächeln',
            'Maedchen' => 'Mädchen',
            'Glueck' => 'Glück',
            'Glueckskind' => 'Glückskind',
            'Gluecksklee' => 'Glücksklee',
            'Glueckshufeisen' => 'Glückshufeisen',
            'Bluete' => 'Blüte',
            'Lotusbluete' => 'Lotusblüte',
            'Schluessel' => 'Schlüssel',
            'Puzzlestueck' => 'Puzzlestück',
            'Muetze' => 'Mütze',
            'Fuer Immer' => 'Für immer',
            'Anstoessen' => 'Anstoßen',
            'Und' => '&',
            'Haeschen' => 'Häschen',
            'Amor Pfeil' => 'Amor-Pfeil',
            'Baby Junge' => 'Baby',
            'Baby Fuesse' => 'Baby-Füßchen',
            'Baby Laecheln' => 'Baby-Lächeln',
            'Baby Maedchen' => 'Baby-Mädchen',
            'Weihnachtsmann Muetze' => 'Weihnachtsmann-Mütze',
            'Schutz Und Geborgenheit' => 'Schutz & Geborgenheit',
            'Sekt Anstoessen' => 'Sekt zum Anstoßen',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $name);
    }
}


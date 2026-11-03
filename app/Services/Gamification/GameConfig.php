<?php

namespace App\Services\Gamification;

class GameConfig
{
    public const MAX_LEVEL = 10;
    public const FUNKI_EVOLUTION_MAX_LEVEL = 10;

    // Website-Funkensuche (Selten, Reload- & Bot-geschützt)
    public const DAILY_SPARK_LIMIT = 10;
    public const SPARK_COOLDOWN_SECONDS = 180;
    public const SPARK_MIN_REACTION_SECONDS = 1;
    public const SPARK_HUNT_REWARD = 1;

    // Belohnung für eine ehrliche, verifizierte Produktbewertung (einmalig pro gekauftem Produkt)
    public const REVIEW_SPARK_REWARD = 15;

    // Ultimativer Rang-Titel (Alle 5 Kern-Titel auf Diamant + Funki Level 10)
    public const ULTIMATE_TITLE_NAME = 'Seelengott unter Sterblichen';

    // Prozentuale Seelenfunken-Berechnung (Variante A = 100 % vom bezahlten Produktwert: 1,00 € = 1 Seelenfunke)
    public const ORDER_SPARKS_PER_ITEM = 0;
    public const ORDER_SPARKS_PER_EURO = 1;
    public const ORDER_SPARKS_PERCENT = 100;

    /**
     * Berechnet die Seelenfunken prozentual (100 % = 1 Funke je 1,00 €, kaufmännisch gerundet)
     * anhand des funkenberechtigten Produktwerts in Cent.
     */
    public static function calculateSparksForCents(int $eligibleCents): int
    {
        if ($eligibleCents <= 0) {
            return 0;
        }

        return max(0, (int) round(($eligibleCents / 100) * self::ORDER_SPARKS_PER_EURO));
    }

    public static function getAppearanceMilestones(): array
    {
        return [
            1  => 'funki_lvl_1_rags',
            2  => 'funki_lvl_2_basic',
            3  => 'funki_lvl_3_helper',
            4  => 'funki_lvl_4_novice',
            5  => 'funki_lvl_5_apprentice',
            6  => 'funki_lvl_6_craftsman',
            7  => 'funki_lvl_7_artisan',
            8  => 'funki_lvl_8_master',
            9  => 'funki_lvl_9_lightbringer',
            10 => 'funki_lvl_10_god_of_soul',
        ];
    }

    /**
     * Alle 10 Funki-Evolutionsstufen (2D-Grafiken) inklusive der kostenlosen,
     * automatisch mit jedem Level aufsteigenden epischen Hintergrund-Darstellung.
     */
    public static function getFunkiLevels(): array
    {
        return [
            1 => [
                'level'                    => 1,
                'required_lifetime_sparks' => 0,
                'name'                     => 'Funki • Kleiner Seelenfunke',
                'subtitle'                 => 'Dein treuer Begleiter ab deinem ersten Einkauf in der Manufaktur',
                'badge'                    => 'STUFE 1 • URSPRUNG',
                'image_key'                => 'funki_lvl_1_rags',
                'image_path'               => 'shop/customer/gamification/models/images/original/funki_lvl_1_rags.png',
                'bg_key'                   => 'lvl_1_ivory',
                'bg_name'                  => 'Stilles Manufaktur-Atelier',
                'bg_subtitle'              => 'Sanfter Elfenbein- & Pergament-Schimmer zum Start deiner Reise',
                'accent_hex'               => '#c5a059',
                'video_url'                => null,
                'video_filter_css'         => 'none',
                'video_transform_css'      => 'scale(1.0)',
                'circle_css'               => 'background: radial-gradient(circle at 50% 42%, #ffffff 0%, #faf6ee 62%, #efe4cc 100%); box-shadow: inset 0 0 24px rgba(197,160,89,0.25);',
            ],
            2 => [
                'level'                    => 2,
                'required_lifetime_sparks' => 150,
                'name'                     => 'Funki • Lichtträger-Lehrling',
                'subtitle'                 => 'Erste warme Lichtfunken begleiten deinen Lehrling',
                'badge'                    => 'STUFE 2 • LEHRLING',
                'image_key'                => 'funki_lvl_2_basic',
                'image_path'               => 'shop/customer/gamification/models/images/original/funki_lvl_2_basic.png',
                'bg_key'                   => 'lvl_2_amber_glow',
                'bg_name'                  => 'Warmer Bernsteinschimmer',
                'bg_subtitle'              => 'Dezenter Kupfer- & Honiggold-Lichtschein der Manufaktur',
                'accent_hex'               => '#d97706',
                'video_url'                => null,
                'video_filter_css'         => 'none',
                'video_transform_css'      => 'scale(1.0)',
                'circle_css'               => 'background: radial-gradient(circle at 50% 35%, #fef3c7 0%, #f5d08a 48%, #92400e 100%); box-shadow: inset 0 0 28px rgba(217,119,6,0.42);',
            ],
            3 => [
                'level'                    => 3,
                'required_lifetime_sparks' => 350,
                'name'                     => 'Funki • Manufaktur-Helfer',
                'subtitle'                 => 'Ausgerüstet für die feinsten Arbeiten an der Werkbank',
                'badge'                    => 'STUFE 3 • HELFER',
                'image_key'                => 'funki_lvl_3_helper',
                'image_path'               => 'shop/customer/gamification/models/images/original/funki_lvl_3_helper.png',
                'bg_key'                   => 'lvl_3_workshop_dusk',
                'bg_name'                  => 'Abendliche Sternen-Werkstatt',
                'bg_subtitle'              => 'Ruhiger Bronze- & Nachtblau-Schimmer mit erstem Sternenstaub',
                'accent_hex'               => '#eab308',
                'video_url'                => null,
                'video_filter_css'         => 'none',
                'video_transform_css'      => 'scale(1.0)',
                'circle_css'               => "background: radial-gradient(circle at 50% 32%, rgba(251,191,36,0.28) 0%, rgba(15,23,42,0.72) 100%), url('/shop/gamification/shop/bg_workshop_dusk.jpg?v=2') center/cover no-repeat; box-shadow: inset 0 0 32px rgba(234,179,8,0.45);",
            ],
            4 => [
                'level'                    => 4,
                'required_lifetime_sparks' => 650,
                'name'                     => 'Funki • Kristall-Novize',
                'subtitle'                 => 'Entdeckt das sanfte Leuchten geschliffener Glas- & Kristallkunst',
                'badge'                    => 'STUFE 4 • NOVIZE',
                'image_key'                => 'funki_lvl_4_novice',
                'image_path'               => 'shop/customer/gamification/models/images/original/funki_lvl_4_novice.png',
                'bg_key'                   => 'lvl_4_sapphire',
                'bg_name'                  => 'Sanfter Saphir-Kristallnebel',
                'bg_subtitle'              => 'Ruhig schwebender Saphir-Nebel mit feiner Lichtbrechung',
                'accent_hex'               => '#38bdf8',
                'video_url'                => '/shop/gamification/shop/bg_sapphire_starforge.mp4',
                'video_filter_css'         => 'brightness(0.88) saturate(1.12) contrast(1.06)',
                'video_transform_css'      => 'scale(1.02)',
                'circle_css'               => "background: radial-gradient(circle at 50% 35%, rgba(56,189,248,0.34) 0%, rgba(6,9,24,0.72) 100%), url('/shop/gamification/shop/bg_crystal_nebula.jpg') center/cover no-repeat; box-shadow: inset 0 0 38px rgba(56,189,248,0.58);",
            ],
            5 => [
                'level'                    => 5,
                'required_lifetime_sparks' => 1000,
                'name'                     => 'Funki • Funken-Geselle',
                'subtitle'                 => 'Ein wahrer Hüter persönlicher Erinnerungsstücke',
                'badge'                    => 'STUFE 5 • GESELLE',
                'image_key'                => 'funki_lvl_5_apprentice',
                'image_path'               => 'shop/customer/gamification/models/images/original/funki_lvl_5_apprentice.png',
                'bg_key'                   => 'lvl_5_valhalla',
                'bg_name'                  => 'Smaragd-Polarlicht der Gesellen',
                'bg_subtitle'              => 'Harmonisches Smaragd- & Gold-Polarlicht am Nachthimmel',
                'accent_hex'               => '#10b981',
                'video_url'                => '/shop/gamification/shop/bg_celestial_valhalla.mp4?v=2',
                'video_filter_css'         => 'saturate(1.18) contrast(1.06) brightness(0.98)',
                'video_transform_css'      => 'scale(1.04)',
                'circle_css'               => "background: radial-gradient(circle at 50% 30%, rgba(16,185,129,0.32) 0%, rgba(4,18,16,0.74) 100%), url('/shop/gamification/shop/bg_emerald_aurora.jpg?v=2') center/cover no-repeat; box-shadow: inset 0 0 44px rgba(16,185,129,0.72), 0 0 22px rgba(16,185,129,0.30);",
            ],
            6 => [
                'level'                    => 6,
                'required_lifetime_sparks' => 1500,
                'name'                     => 'Funki • Gravur-Handwerker',
                'subtitle'                 => 'Präzise Handwerkskunst im warmen Schein der Laserschmiede',
                'badge'                    => 'STUFE 6 • HANDWERKER',
                'image_key'                => 'funki_lvl_6_craftsman',
                'image_path'               => 'shop/customer/gamification/models/images/original/funki_lvl_6_craftsman.png',
                'bg_key'                   => 'lvl_6_crimson_forge',
                'bg_name'                  => 'Karmesin-Laserschmiede',
                'bg_subtitle'              => 'Aufsteigende Schmiedeglut & rubinroter Laserstrahl im Atelier',
                'accent_hex'               => '#f43f5e',
                'video_url'                => '/shop/gamification/shop/bg_crimson_bloodmoon.mp4?v=2',
                'video_filter_css'         => 'saturate(1.18) contrast(1.08) brightness(0.98)',
                'video_transform_css'      => 'scale(1.04)',
                'circle_css'               => "background: radial-gradient(circle at 50% 32%, rgba(244,63,94,0.34) 0%, rgba(15,5,10,0.76) 100%), url('/shop/gamification/shop/bg_crimson_forge.jpg?v=2') center/cover no-repeat; box-shadow: inset 0 0 46px rgba(244,63,94,0.68);",
            ],
            7 => [
                'level'                    => 7,
                'required_lifetime_sparks' => 2100,
                'name'                     => 'Funki • Kunsthandwerker',
                'subtitle'                 => 'Veredelt jedes Unikat unter dem Korona-Ring der Eklipse',
                'badge'                    => 'STUFE 7 • KUNSTHANDWERKER',
                'image_key'                => 'funki_lvl_7_artisan',
                'image_path'               => 'shop/customer/gamification/models/images/original/funki_lvl_7_artisan.png',
                'bg_key'                   => 'lvl_7_black_sun',
                'bg_name'                  => 'Eklipse der Schwarzen Sonne',
                'bg_subtitle'              => 'Mystische Sonnenfinsternis mit glühendem Korona-Lichtring',
                'accent_hex'               => '#ef4444',
                'video_url'                => '/shop/gamification/shop/bg_black_sun_throne.mp4',
                'video_filter_css'         => 'saturate(1.25) contrast(1.18) brightness(0.94)',
                'video_transform_css'      => 'scale(1.04)',
                'circle_css'               => "background: radial-gradient(circle at 50% 40%, rgba(239,68,68,0.36) 0%, rgba(9,5,8,0.78) 100%), url('/shop/gamification/shop/bg_obsidian_abyss.jpg') center/cover no-repeat; box-shadow: inset 0 0 50px rgba(239,68,68,0.72);",
            ],
            8 => [
                'level'                    => 8,
                'required_lifetime_sparks' => 2800,
                'name'                     => 'Funki • Meister der Manufaktur',
                'subtitle'                 => 'Strahlende Rüstung eines großen Meisters der Seelenfunken',
                'badge'                    => 'STUFE 8 • MEISTER',
                'image_key'                => 'funki_lvl_8_master',
                'image_path'               => 'shop/customer/gamification/models/images/original/funki_lvl_8_master.png',
                'bg_key'                   => 'lvl_8_singularity',
                'bg_name'                  => 'Astrale Meister-Singularität',
                'bg_subtitle'              => 'Violett-goldener Raumzeit-Wirbel der Meisterstufe',
                'accent_hex'               => '#a855f7',
                'video_url'                => '/shop/gamification/shop/bg_void_singularity.mp4?v=2',
                'video_filter_css'         => 'saturate(1.20) contrast(1.08) brightness(0.98)',
                'video_transform_css'      => 'scale(1.04)',
                'circle_css'               => "background: radial-gradient(circle at 50% 50%, rgba(168,85,247,0.34) 0%, rgba(5,4,12,0.76) 100%), url('/shop/gamification/shop/bg_void_singularity.jpg?v=2') center/cover no-repeat; box-shadow: inset 0 0 58px rgba(168,85,247,0.82), 0 0 28px rgba(168,85,247,0.42);",
            ],
            9 => [
                'level'                    => 9,
                'required_lifetime_sparks' => 3800,
                'name'                     => 'Funki • Hüter des Sternenlichts',
                'subtitle'                 => 'Unmittelbar vor den Toren der goldenen Gotteshalle',
                'badge'                    => 'STUFE 9 • LICHTBRINGER',
                'image_key'                => 'funki_lvl_9_lightbringer',
                'image_path'               => 'shop/customer/gamification/models/images/original/funki_lvl_9_lightbringer.png',
                'bg_key'                   => 'lvl_9_horizon',
                'bg_name'                  => 'Kosmischer Sternen-Horizont',
                'bg_subtitle'              => 'Rotierende goldene Spiralgalaxie an der Schwelle zur Göttlichkeit',
                'accent_hex'               => '#f59e0b',
                'video_url'                => '/shop/gamification/shop/bg_divine_event_horizon.mp4',
                'video_filter_css'         => 'saturate(1.42) contrast(1.18) brightness(1.04)',
                'video_transform_css'      => 'scale(1.08)',
                'circle_css'               => "background: radial-gradient(circle at 50% 38%, rgba(250,204,21,0.36) 0%, rgba(6,10,26,0.78) 100%), url('/shop/gamification/shop/bg_supernova_sanctum.jpg') center/cover no-repeat; box-shadow: inset 0 0 60px rgba(250,204,21,0.78);",
            ],
            10 => [
                'level'                    => 10,
                'required_lifetime_sparks' => 5000,
                'name'                     => 'Funki • Gott des Seelenfunkens',
                'subtitle'                 => 'Höchste Evolutionsstufe in der ewigen Gotteshalle aus purem Gold',
                'badge'                    => 'STUFE 10 • MAXIMALES LEVEL',
                'image_key'                => 'funki_lvl_10_god_of_soul',
                'image_path'               => 'shop/customer/gamification/models/images/original/funki_lvl_10_god_of_soul.png',
                'bg_key'                   => 'lvl_10_golden_god_hall',
                'bg_name'                  => 'Gotteshalle aus purem Gold',
                'bg_subtitle'              => 'Majestätische 24k-Gold-Säulenhalle des Seelengottes mit koronalem Lichtaltar',
                'accent_hex'               => '#fbbf24',
                'video_url'                => '/shop/gamification/shop/bg_aurelia_sanctuary.mp4',
                'video_filter_css'         => 'brightness(1.12) saturate(1.45) contrast(1.16) sepia(0.08)',
                'video_transform_css'      => 'scale(1.02)',
                'circle_css'               => "background: radial-gradient(circle at 50% 38%, rgba(254,240,138,0.55) 0%, rgba(245,158,11,0.45) 48%, rgba(24,14,4,0.82) 100%), url('/shop/gamification/shop/bg_imperial_throne.jpg') center/cover no-repeat; box-shadow: inset 0 0 68px rgba(251,191,36,0.95), 0 0 40px rgba(251,191,36,0.60);",
            ],
        ];
    }

    public static function getLevelForLifetimeSparks(int $lifetimeSparks): int
    {
        $levels = self::getFunkiLevels();
        $resolved = 1;
        foreach ($levels as $lvl => $cfg) {
            if ($lifetimeSparks >= (int) $cfg['required_lifetime_sparks']) {
                $resolved = (int) $lvl;
            }
        }
        return min(self::MAX_LEVEL, max(1, $resolved));
    }

    public static function calculateFunkiLevel(int $lifetimeSparks): int
    {
        return self::getLevelForLifetimeSparks($lifetimeSparks);
    }

    public static function getLevelConfig(int $level): array
    {
        $levels = self::getFunkiLevels();
        $clamped = min(self::MAX_LEVEL, max(1, $level));
        return $levels[$clamped] ?? $levels[1];
    }

    public static function getFunkiStageConfig(int $level): array
    {
        return self::getLevelConfig($level);
    }

    public static function getMissingSparksForNextLevel(int $lifetimeSparks): int
    {
        $progress = self::getLevelProgressData($lifetimeSparks);
        return (int) ($progress['missing_sparks'] ?? 0);
    }

    public static function getVoucherRewards(): array
    {
        $out = [];
        foreach (self::getRedeemableVouchers() as $key => $cfg) {
            $out[$key] = array_merge($cfg, [
                'title'       => $cfg['name'],
                'cost_funken' => $cfg['cost_sparks'],
            ]);
        }
        return $out;
    }

    public static function getLevels(): array
    {
        $levels = self::getFunkiLevels();
        foreach ($levels as $lvl => &$cfg) {
            $cfg['title'] = $cfg['name'];
            $cfg['cost'] = $cfg['required_lifetime_sparks'];
            $cfg['image'] = $cfg['image_path'];
            $cfg['background_image'] = $cfg['circle_css'];
            $cfg['background_title'] = $cfg['bg_name'];
            $cfg['model'] = '';
        }
        return $levels;
    }

    public static function getNextLevelCost(int $currentLevel): int
    {
        $levels = self::getFunkiLevels();
        $nextLevel = min(self::MAX_LEVEL, max(1, $currentLevel + 1));
        return (int) ($levels[$nextLevel]['required_lifetime_sparks'] ?? 5000);
    }

    public static function getLevelProgressData(int $lifetimeSparks, ?int $explicitLevel = null): array
    {
        $levels = self::getFunkiLevels();
        $calculatedLevel = self::getLevelForLifetimeSparks($lifetimeSparks);
        $level = $explicitLevel !== null ? min(self::MAX_LEVEL, max($calculatedLevel, $explicitLevel)) : $calculatedLevel;
        $isMaxLevel = $level >= self::MAX_LEVEL;

        $currentCfg = $levels[$level] ?? $levels[1];
        $currentThreshold = (int) ($currentCfg['required_lifetime_sparks'] ?? 0);

        if ($isMaxLevel) {
            return [
                'level'                => self::MAX_LEVEL,
                'is_max_level'         => true,
                'progress_percentage'  => 100,
                'missing_sparks'       => 0,
                'next_level_threshold' => $currentThreshold,
                'ep_bottom_arc_text'   => '100% • MAXIMALES LEVEL ERREICHT',
                'level_config'         => $currentCfg,
            ];
        }

        $nextCfg = $levels[$level + 1] ?? $currentCfg;
        $nextThreshold = (int) ($nextCfg['required_lifetime_sparks'] ?? ($currentThreshold + 150));
        $span = max(1, $nextThreshold - $currentThreshold);
        $gainedInLevel = max(0, $lifetimeSparks - $currentThreshold);
        $pct = min(99, max(0, (int) round(($gainedInLevel / $span) * 100)));
        $missing = max(1, $nextThreshold - $lifetimeSparks);

        return [
            'level'                => $level,
            'is_max_level'         => false,
            'progress_percentage'  => $pct,
            'missing_sparks'       => $missing,
            'next_level_threshold' => $nextThreshold,
            'ep_bottom_arc_text'   => $pct . '% • NOCH ' . number_format($missing, 0, ',', '.') . ' FUNKEN',
            'level_config'         => $currentCfg,
        ];
    }

    /**
     * Einmalig einlösbare Rabatt-Gutscheine gegen gesammelte Seelenfunken (psychologisch progressiv gestaffelt):
     * 60 Funken = 5 % (12 ✨/%), 150 Funken = 10 % (15 ✨/%), 350 Funken = 15 % (23,3 ✨/%)
     */
    public static function getRedeemableVouchers(): array
    {
        return [
            'voucher_5' => [
                'key'         => 'voucher_5',
                'percent'     => 5,
                'value'       => 5,
                'cost_sparks' => 60,
                'name'        => '5% Rabatt-Gutschein',
                'badge'       => '5% RABATT',
                'code_prefix' => 'FUNKI-5P',
                'description' => 'Einmalig einlösbar für deinen nächsten Einkauf im gesamten Mein-Seelenfunke Shop.',
            ],
            'voucher_10' => [
                'key'         => 'voucher_10',
                'percent'     => 10,
                'value'       => 10,
                'cost_sparks' => 150,
                'name'        => '10% Rabatt-Gutschein',
                'badge'       => '10% RABATT',
                'code_prefix' => 'FUNKI-10P',
                'description' => 'Einmalig einlösbar für deinen nächsten Einkauf im gesamten Mein-Seelenfunke Shop.',
            ],
            'voucher_15' => [
                'key'         => 'voucher_15',
                'percent'     => 15,
                'value'       => 15,
                'cost_sparks' => 350,
                'name'        => '15% Rabatt-Gutschein',
                'badge'       => '15% PREMIUM-RABATT',
                'code_prefix' => 'FUNKI-15P',
                'description' => 'Einmalig einlösbar für deinen nächsten Einkauf im gesamten Mein-Seelenfunke Shop.',
            ],
        ];
    }

    /**
     * Kompatibilitäts-Methode für Meilenstein-Gutschein-Prüfungen (Level 1-10).
     */
    public static function getLevelRewards(): array
    {
        return [
            3  => ['type' => 'percentage', 'value' => 5,  'name' => '5% Rabatt-Gutschein',  'cost_sparks' => 60],
            5  => ['type' => 'percentage', 'value' => 8,  'name' => '8% Rabatt-Gutschein',  'cost_sparks' => 110],
            8  => ['type' => 'percentage', 'value' => 10, 'name' => '10% Rabatt-Gutschein', 'cost_sparks' => 150],
            10 => ['type' => 'percentage', 'value' => 15, 'name' => '15% Seelengott-Rabatt', 'cost_sparks' => 350],
        ];
    }

    public static function getPrestigeMilestones(): array
    {
        return [];
    }

    public static function getIngameShopCatalog(): array
    {
        return [];
    }

    public static function getCockpitBackgrounds(): array
    {
        $out = [];
        foreach (self::getFunkiLevels() as $lvl => $cfg) {
            $out[$cfg['bg_key']] = [
                'key'           => $cfg['bg_key'],
                'level'         => $lvl,
                'name'          => $cfg['bg_name'],
                'subtitle'      => $cfg['bg_subtitle'],
                'accent_hex'    => $cfg['accent_hex'],
                'video_url'     => $cfg['video_url'],
                'circle_css'    => $cfg['circle_css'],
                'price_sparks'  => 0,
                'price_cents'   => 0,
            ];
        }
        return $out;
    }

    /**
     * Sinnvolle Rang-Titel für den Aufstieg von Funki-Level 1 bis 10.
     * Sind zusätzlich alle 5 Kern-Titel auf Max-Stufe (Diamant) und Level 10 erreicht,
     * wird der ultimative Titel "Seelengott" freigeschaltet.
     */
    public static function getLevelTitles(): array
    {
        return [
            1  => ['level' => 1,  'key' => 'level_1',  'name' => 'Kleiner Seelenfunke',        'subtitle' => 'Stufe 1 • Ursprung des Funkens'],
            2  => ['level' => 2,  'key' => 'level_2',  'name' => 'Lichtträger-Lehrling',       'subtitle' => 'Stufe 2 • Entdecker der Manufaktur'],
            3  => ['level' => 3,  'key' => 'level_3',  'name' => 'Manufaktur-Helfer',          'subtitle' => 'Stufe 3 • Hüter der feinen Gravur'],
            4  => ['level' => 4,  'key' => 'level_4',  'name' => 'Kristall-Novize',            'subtitle' => 'Stufe 4 • Meister des Glanzes'],
            5  => ['level' => 5,  'key' => 'level_5',  'name' => 'Funken-Geselle',             'subtitle' => 'Stufe 5 • Bewahrer der Erinnerungen'],
            6  => ['level' => 6,  'key' => 'level_6',  'name' => 'Gravur-Handwerker',          'subtitle' => 'Stufe 6 • Präzision aus Licht & Feuer'],
            7  => ['level' => 7,  'key' => 'level_7',  'name' => 'Kunsthandwerker',            'subtitle' => 'Stufe 7 • Schöpfer edler Unikate'],
            8  => ['level' => 8,  'key' => 'level_8',  'name' => 'Meister der Manufaktur',     'subtitle' => 'Stufe 8 • Großmeister der Seelenkunst'],
            9  => ['level' => 9,  'key' => 'level_9',  'name' => 'Hüter des Sternenlichts',    'subtitle' => 'Stufe 9 • Lichtbringer der Ewigkeit'],
            10 => ['level' => 10, 'key' => 'level_10', 'name' => 'Erhabener des Seelenlichts', 'subtitle' => 'Stufe 10 • Höchste Evolutionsstufe'],
        ];
    }

    public static function getMegaTitles(): array
    {
        return [
            ['req' => 1, 'name' => 'Geweihter des Funkens'],
            ['req' => 2, 'name' => 'Wächter der Manufaktur'],
            ['req' => 3, 'name' => 'Rechte Hand eines Engels'],
            ['req' => 4, 'name' => 'Engel auf Erden'],
            ['req' => 5, 'name' => self::ULTIMATE_TITLE_NAME],
        ];
    }

    /**
     * Die 5 schlichten, sinnvollen Kern-Titel der Manufaktur (jeweils bis zur Max-Stufe Diamant).
     * Sind alle 5 Titel auf Max-Stufe (Diamant) und Level 10 erreicht, wird man zum "Seelengott unter Sterblichen".
     */
    public static function getTitles(): array
    {
        return [
            'sammler' => [
                'name'        => 'Funken-Sammler',
                'description' => 'Versteckte Funken auf der Website gefunden',
                'icon'        => '✨',
                'tiers'       => [
                    'grau'    => ['req' => 0,   'name' => 'Beobachter'],
                    'silber'  => ['req' => 25,  'name' => 'Sucher'],
                    'gold'    => ['req' => 100, 'name' => 'Jäger'],
                    'diamant' => ['req' => 250, 'name' => 'Meister der Funken'],
                ],
            ],
            'schatzhueter' => [
                'name'        => 'Der Schatzhüter',
                'description' => 'Erfolgreich abgeschlossene Bestellungen',
                'icon'        => '🛍️',
                'tiers'       => [
                    'grau'    => ['req' => 0,  'name' => 'Interessent'],
                    'silber'  => ['req' => 1,  'name' => 'Neuling'],
                    'gold'    => ['req' => 3,  'name' => 'Kenner'],
                    'diamant' => ['req' => 10, 'name' => 'Patron der Manufaktur'],
                ],
            ],
            'botschafter' => [
                'name'        => 'Der Botschafter',
                'description' => 'Verifizierte Produktbewertungen geschrieben',
                'icon'        => '✍️',
                'tiers'       => [
                    'grau'    => ['req' => 0,  'name' => 'Stiller Genießer'],
                    'silber'  => ['req' => 1,  'name' => 'Wortschmied'],
                    'gold'    => ['req' => 3,  'name' => 'Meinungsmacher'],
                    'diamant' => ['req' => 10, 'name' => 'Stimme der Seele'],
                ],
            ],
            'freudebringer' => [
                'name'        => 'Der Freudebringer',
                'description' => 'Geschenke an abweichende Lieferadressen versendet',
                'icon'        => '🎁',
                'tiers'       => [
                    'grau'    => ['req' => 0, 'name' => 'Selbstbeschenker'],
                    'silber'  => ['req' => 1, 'name' => 'Aufmerksamer Freund'],
                    'gold'    => ['req' => 3, 'name' => 'Herzensmensch'],
                    'diamant' => ['req' => 5, 'name' => 'Bote der Freude'],
                ],
            ],
            'funkenkoenig' => [
                'name'        => 'Der Alchemist',
                'description' => 'Gesamtzahl aller jemals gesammelten Seelenfunken',
                'icon'        => '👑',
                'tiers'       => [
                    'grau'    => ['req' => 0,    'name' => 'Funkenlos'],
                    'silber'  => ['req' => 150,  'name' => 'Zauberlehrling'],
                    'gold'    => ['req' => 1000, 'name' => 'Magier'],
                    'diamant' => ['req' => 5000, 'name' => 'Herr der Energie'],
                ],
            ],
        ];
    }
}

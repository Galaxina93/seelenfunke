<?php

namespace App\Services\Gamification;

class GameConfig
{
    public const MAX_LEVEL = 100;
    public const FUNKI_EVOLUTION_MAX_LEVEL = 10;
    public const DAILY_SPARK_LIMIT = 20;

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
            10 => 'funki_lvl_10_god_of_soul'
        ];
    }

    // Echte Coupon-Struktur für die Generierung (Margen-geschützt: bleibt strikt auf Level 1-10!)
    public static function getLevelRewards(): array
    {
        return [
            3  => ['type' => 'percentage', 'value' => 5,  'name' => '5% Rabatt-Gutschein'],
            5  => ['type' => 'percentage', 'value' => 8,  'name' => '8% Rabatt-Gutschein'],
            8  => ['type' => 'percentage', 'value' => 10, 'name' => '10% Rabatt-Gutschein'],
            10 => ['type' => 'percentage', 'value' => 15, 'name' => '15% Seelengott-Rabatt'],
        ];
    }

    // Prestige-Meilensteine ab Level 15 bis 100 (0,00 € Margenkosten, maximaler Status in der Halle der Legenden!)
    public static function getPrestigeMilestones(): array
    {
        return [
            15  => ['name' => 'Sternensaphir-Hüter',     'icon' => '💠', 'reward' => 'Ruhmes-Aura: Sternensaphir-Schimmer gratis', 'type' => 'aura',   'key' => 'starlight_rain',  'badge' => '💠 Sternensaphir-Aura'],
            25  => ['name' => 'Sternenschmied',          'icon' => '⚒️', 'reward' => 'Prestige-Wappen in der Halle der Legenden',  'type' => 'rank',   'key' => 'sternenschmied',  'badge' => '⚒️ Sternenschmied (Lvl 25)'],
            35  => ['name' => 'Kometen-Reiter',          'icon' => '☄️', 'reward' => 'Mythischer 3D-Skin: Funki auf dem Kometen',  'type' => 'skin',   'key' => 'funki_comet',     'badge' => '☄️ Funki-Komet Skin'],
            50  => ['name' => 'Supernova-Champion',      'icon' => '🌟', 'reward' => 'Ruhmes-Aura: Goldene Supernova gratis',      'type' => 'aura',   'key' => 'solar_supernova', 'badge' => '🌟 Supernova-Aura (Lvl 50)'],
            75  => ['name' => 'Kosmischer Imperator',    'icon' => '🌌', 'reward' => 'Ruhmes-Aura: Kosmischer Imperator gratis',   'type' => 'aura',   'key' => 'cosmic_emperor',  'badge' => '🌌 Imperator-Aura (Lvl 75)'],
            100 => ['name' => 'Unsterbliche Legende',    'icon' => '👑', 'reward' => 'Höchstes Prestige-Siegel der Manufaktur',    'type' => 'legend', 'key' => 'immortal_100',    'badge' => '👑 Unsterbliche Legende (Lvl 100)'],
        ];
    }

    public static function getFighterSkinKeys(): array
    {
        return [
            'default',
            'crystal_fighter',
            'slate_phantom',
            'gold_phoenix',
            'funki_comet',
            'royal_dreadnought',
        ];
    }

    public static function getBattleshipSkinKeys(): array
    {
        return [
            'aegis_titan',
            'valkyrie_prime',
            'leviathan_frost',
            'hyperion_sun',
            'celestial_ark',
            'dark_perl',
        ];
    }

    public static function getShipSkins(): array
    {
        return [
            // =========================================================================
            // KATEGORIE 1: GLEITER & JÄGER (6 SCHIFFE)
            // =========================================================================
            'default' => [
                'key'               => 'default',
                'category'          => 'fighter',
                'category_label'    => 'Gleiter & Jäger',
                'name'              => 'Sternengleiter MK-I',
                'subtitle'          => 'Klassisches Manufaktur-Raumschiff',
                'rarity'            => 'common',
                'rarity_label'      => 'Standard',
                'badge_icon'        => '🚀',
                'icon'              => '🚀',
                'perk_summary'      => 'Ausgewogene Standard-Konfiguration (+25 m / Funke)',
                'price_sparks'      => 0,
                'min_level'         => 1,
                'vip_buyer_unlock'  => false,
                'glb_file'          => 'funki_rocket.glb',
                'is_vertex_colored' => false,
                'laser_color_hex'   => 0x38bdf8,
                'laser_color_css'   => '#38bdf8',
                'trail_color_hex'   => 0x60a5fa,
                'stats' => [
                    'magnet_mult'          => 1.0,
                    'hitbox_mult'          => 1.0,
                    'skill_duration_mult'  => 1.0,
                    'start_shield'         => 0,
                    'spark_distance_bonus' => 25,
                    'max_hearts'           => 3,
                    'weapon_power_mult'    => 1.0,
                    'salvo_streams'        => 2,
                ],
                'perks' => [
                    'Ausgewogene Standard-Steuerung (3 ❤️ Rumpf-Herzen)',
                    'Cyan-Plasma-Bordkanone',
                    '+25 m Distanz pro eingesammeltem Funken',
                ],
            ],
            'crystal_fighter' => [
                'key'               => 'crystal_fighter',
                'category'          => 'fighter',
                'category_label'    => 'Gleiter & Jäger',
                'name'              => 'Saphir-Kristalljäger',
                'subtitle'          => 'Geschliffenes 3D-Kristallglas-Schiff',
                'rarity'            => 'rare',
                'rarity_label'      => 'Selten',
                'badge_icon'        => '💎',
                'icon'              => '💎',
                'perk_summary'      => '+25 % Funken-Magnet • -5 % Hitbox • +28 m / Funke',
                'price_sparks'      => 450,
                'min_level'         => 1,
                'vip_buyer_unlock'  => false,
                'glb_file'          => 'ship_crystal_fighter.glb',
                'is_vertex_colored' => true,
                'laser_color_hex'   => 0x2dd4bf,
                'laser_color_css'   => '#2dd4bf',
                'trail_color_hex'   => 0x2dd4bf,
                'stats' => [
                    'magnet_mult'          => 1.25,
                    'hitbox_mult'          => 0.95,
                    'skill_duration_mult'  => 1.05,
                    'start_shield'         => 0,
                    'spark_distance_bonus' => 28,
                    'max_hearts'           => 3,
                    'weapon_power_mult'    => 1.08,
                    'salvo_streams'        => 2,
                ],
                'perks' => [
                    '+25 % größerer Funken-Magnet-Radius',
                    '-5 % schmalere Kristall-Hitbox',
                    '+28 m Distanz pro eingesammeltem Funken',
                ],
            ],
            'slate_phantom' => [
                'key'               => 'slate_phantom',
                'category'          => 'fighter',
                'category_label'    => 'Gleiter & Jäger',
                'name'              => 'Schiefer-Phantom',
                'subtitle'          => 'Stealth-Gleiter mit Kupfer-Gold-Gravur',
                'rarity'            => 'epic',
                'rarity_label'      => 'Episch',
                'badge_icon'        => '🦅',
                'icon'              => '🦅',
                'perk_summary'      => '-18 % Stealth-Hitbox • +15 % Skill-Dauer • +30 % Magnet',
                'price_sparks'      => 950,
                'min_level'         => 1,
                'vip_buyer_unlock'  => false,
                'glb_file'          => 'ship_slate_phantom.glb',
                'is_vertex_colored' => true,
                'laser_color_hex'   => 0xf97316,
                'laser_color_css'   => '#f97316',
                'trail_color_hex'   => 0xf59e0b,
                'stats' => [
                    'magnet_mult'          => 1.30,
                    'hitbox_mult'          => 0.82,
                    'skill_duration_mult'  => 1.15,
                    'start_shield'         => 0,
                    'spark_distance_bonus' => 30,
                    'max_hearts'           => 3,
                    'weapon_power_mult'    => 1.15,
                    'salvo_streams'        => 2,
                ],
                'perks' => [
                    '-18 % kleinere Stealth-Hitbox (leichteres Ausweichen)',
                    '+15 % längere Fähigkeiten-Dauer (Multishoot & Schild)',
                    '+30 % größerer Funken-Magnet & +30 m pro Funke',
                ],
            ],
            'gold_phoenix' => [
                'key'               => 'gold_phoenix',
                'category'          => 'fighter',
                'category_label'    => 'Gleiter & Jäger',
                'name'              => 'Aurelia Gold-Phönix',
                'subtitle'          => '24k-Gold- & Amethyst-Schwingenkreuzer',
                'rarity'            => 'legendary',
                'rarity_label'      => 'Legendär',
                'badge_icon'        => '🔥',
                'icon'              => '🔥',
                'perk_summary'      => '35 % Start-Schild • +45 % Magnet • +25 % Skill-Dauer',
                'price_sparks'      => 1800,
                'min_level'         => 1,
                'vip_buyer_unlock'  => false,
                'glb_file'          => 'ship_gold_phoenix.glb',
                'is_vertex_colored' => true,
                'laser_color_hex'   => 0xfde047,
                'laser_color_css'   => '#fde047',
                'trail_color_hex'   => 0xfbbf24,
                'stats' => [
                    'magnet_mult'          => 1.45,
                    'hitbox_mult'          => 0.86,
                    'skill_duration_mult'  => 1.25,
                    'start_shield'         => 35,
                    'spark_distance_bonus' => 32,
                    'max_hearts'           => 3,
                    'weapon_power_mult'    => 1.22,
                    'salvo_streams'        => 2,
                ],
                'perks' => [
                    'Startet jeden Flug direkt mit 35 % Energieschild',
                    '+45 % Funken-Magnet & +25 % längere Skill-Dauer',
                    '-14 % Hitbox & +32 m Distanz pro Funke',
                ],
            ],
            'funki_comet' => [
                'key'               => 'funki_comet',
                'category'          => 'fighter',
                'category_label'    => 'Gleiter & Jäger',
                'name'              => 'Funki auf dem Kometen',
                'subtitle'          => '3D-Charakter-Skin: Funki fliegt selbst!',
                'rarity'            => 'mythic',
                'rarity_label'      => 'Mythisch',
                'badge_icon'        => '☄️',
                'icon'              => '☄️',
                'perk_summary'      => '50 % Start-Schild • -20 % Hitbox • +60 % Magnet',
                'price_sparks'      => 2600,
                'min_level'         => 35,
                'vip_buyer_unlock'  => false,
                'glb_file'          => 'ship_funki_comet.glb',
                'is_vertex_colored' => true,
                'laser_color_hex'   => 0xe879f9,
                'laser_color_css'   => '#e879f9',
                'trail_color_hex'   => 0xc084fc,
                'stats' => [
                    'magnet_mult'          => 1.60,
                    'hitbox_mult'          => 0.80,
                    'skill_duration_mult'  => 1.30,
                    'start_shield'         => 50,
                    'spark_distance_bonus' => 35,
                    'max_hearts'           => 4,
                    'weapon_power_mult'    => 1.30,
                    'salvo_streams'        => 3,
                ],
                'perks' => [
                    'Gratis ab Level 35 (oder sofort für 2.600 ✨)',
                    'Startet mit 50 % Schild, 4 ❤️ Herzen & -20 % Hitbox',
                    '+60 % Magnet-Radius & +35 m Distanz pro Funke',
                ],
            ],
            'royal_dreadnought' => [
                'key'               => 'royal_dreadnought',
                'category'          => 'fighter',
                'category_label'    => 'Gleiter & Jäger',
                'name'              => 'Manufaktur-Majestät',
                'subtitle'          => 'Exklusives VIP-Patron-Flaggschiff',
                'rarity'            => 'vip',
                'rarity_label'      => 'VIP-Exklusiv',
                'badge_icon'        => '👑',
                'icon'              => '👑',
                'perk_summary'      => '100 % Start-Schild • +85 % Riesen-Magnet • -22 % Hitbox',
                'price_sparks'      => 4500,
                'min_level'         => 1,
                'vip_buyer_unlock'  => true,
                'glb_file'          => 'ship_royal_dreadnought.glb',
                'is_vertex_colored' => true,
                'laser_color_hex'   => 0xfef08a,
                'laser_color_css'   => '#fef08a',
                'trail_color_hex'   => 0xfde047,
                'stats' => [
                    'magnet_mult'          => 1.85,
                    'hitbox_mult'          => 0.78,
                    'skill_duration_mult'  => 1.35,
                    'start_shield'         => 100,
                    'spark_distance_bonus' => 40,
                    'max_hearts'           => 4,
                    'weapon_power_mult'    => 1.38,
                    'salvo_streams'        => 3,
                ],
                'perks' => [
                    'SOFORT GRATIS bei jeder echten Shop-Bestellung!',
                    'Startet jede Runde mit vollem 100 % Energieschild & 4 ❤️',
                    '+85 % Riesen-Magnet, -22 % Hitbox & +40 m pro Funke',
                ],
            ],

            // =========================================================================
            // KATEGORIE 2: SCHLACHTSCHIFFE (6 SCHWERE KAMPFSCHIFFE INKL. VIP "DARK PERL")
            // Spielen in der erhöhten Taktik-Ansicht mit mehr Feinden & massiver Feuerkraft!
            // =========================================================================
            'aegis_titan' => [
                'key'               => 'aegis_titan',
                'category'          => 'battleship',
                'category_label'    => 'Schlachtschiff',
                'name'              => 'Aegis-Titan MK-IV',
                'subtitle'          => 'Schwerer gepanzerter Flotten-Schlachtkreuzer',
                'rarity'            => 'epic',
                'rarity_label'      => 'Schlachtkreuzer',
                'badge_icon'        => '🛡️',
                'icon'              => '🛡️',
                'perk_summary'      => '5 ❤️ Rumpf • +50 % Feuerkraft • 60 % Schild • Taktik-Kamera',
                'price_sparks'      => 3200,
                'min_level'         => 1,
                'vip_buyer_unlock'  => false,
                'glb_file'          => 'ship_aegis_titan.glb',
                'is_vertex_colored' => true,
                'laser_color_hex'   => 0x38bdf8,
                'laser_color_css'   => '#38bdf8',
                'trail_color_hex'   => 0x0284c7,
                'stats' => [
                    'magnet_mult'          => 1.95,
                    'hitbox_mult'          => 0.78,
                    'skill_duration_mult'  => 1.40,
                    'start_shield'         => 60,
                    'spark_distance_bonus' => 45,
                    'max_hearts'           => 5,
                    'weapon_power_mult'    => 1.50,
                    'salvo_streams'        => 3,
                ],
                'perks' => [
                    '5 ❤️ Schwerer Titan-Rumpf & 60 % Start-Energieschild',
                    '+50 % Geschütz-Feuerkraft (3-fach Salven) in erhöhter Taktik-Ansicht',
                    '+95 % Magnet-Radius, +40 % Skill-Dauer & +45 m pro Funke',
                ],
            ],
            'valkyrie_prime' => [
                'key'               => 'valkyrie_prime',
                'category'          => 'battleship',
                'category_label'    => 'Schlachtschiff',
                'name'              => 'Walküre-Prime Träger',
                'subtitle'          => 'Orbitaler Flottenträger mit Begleit-Geschwadern',
                'rarity'            => 'epic',
                'rarity_label'      => 'Flottenträger',
                'badge_icon'        => '⚔️',
                'icon'              => '⚔️',
                'perk_summary'      => '5 ❤️ Rumpf • +65 % Feuerkraft • 70 % Schild • +110 % Magnet',
                'price_sparks'      => 4200,
                'min_level'         => 1,
                'vip_buyer_unlock'  => false,
                'glb_file'          => 'ship_valkyrie_prime.glb',
                'is_vertex_colored' => true,
                'laser_color_hex'   => 0x10b981,
                'laser_color_css'   => '#10b981',
                'trail_color_hex'   => 0x34d399,
                'stats' => [
                    'magnet_mult'          => 2.10,
                    'hitbox_mult'          => 0.76,
                    'skill_duration_mult'  => 1.48,
                    'start_shield'         => 70,
                    'spark_distance_bonus' => 50,
                    'max_hearts'           => 5,
                    'weapon_power_mult'    => 1.65,
                    'salvo_streams'        => 3,
                ],
                'perks' => [
                    '5 ❤️ Rumpf, 70 % Start-Schild & autonome Walküre-Begleitjäger',
                    '+65 % Smaragd-Lanzen-Feuerkraft im erweiterten Schlachtfeld',
                    '+110 % Magnet-Radius, +48 % Skill-Dauer & +50 m pro Funke',
                ],
            ],
            'leviathan_frost' => [
                'key'               => 'leviathan_frost',
                'category'          => 'battleship',
                'category_label'    => 'Schlachtschiff',
                'name'              => 'Kryo-Leviathan Dreadnought',
                'subtitle'          => 'Arktischer Tiefenraum-Koloss mit Sub-Zero-Reaktor',
                'rarity'            => 'legendary',
                'rarity_label'      => 'Dreadnought',
                'badge_icon'        => '❄️',
                'icon'              => '❄️',
                'perk_summary'      => '5 ❤️ Rumpf • +80 % Feuerkraft (4-fach) • 80 % Schild',
                'price_sparks'      => 5400,
                'min_level'         => 1,
                'vip_buyer_unlock'  => false,
                'glb_file'          => 'ship_leviathan_frost.glb',
                'is_vertex_colored' => true,
                'laser_color_hex'   => 0x67e8f9,
                'laser_color_css'   => '#67e8f9',
                'trail_color_hex'   => 0x22d3ee,
                'stats' => [
                    'magnet_mult'          => 2.25,
                    'hitbox_mult'          => 0.74,
                    'skill_duration_mult'  => 1.55,
                    'start_shield'         => 80,
                    'spark_distance_bonus' => 55,
                    'max_hearts'           => 5,
                    'weapon_power_mult'    => 1.80,
                    'salvo_streams'        => 4,
                ],
                'perks' => [
                    '4-fach Sub-Zero Breitseiten-Salve mit +80 % Waffenstärke',
                    '5 ❤️ Kryo-Panzerung, 80 % Start-Schild & -26 % Hitbox',
                    '+125 % Magnet-Radius, +55 % Skill-Dauer & +55 m pro Funke',
                ],
            ],
            'hyperion_sun' => [
                'key'               => 'hyperion_sun',
                'category'          => 'battleship',
                'category_label'    => 'Schlachtschiff',
                'name'              => 'Hyperion-Sonnensturm',
                'subtitle'          => 'Solares Imperiums-Schlachtschiff mit Fusions-Korona',
                'rarity'            => 'legendary',
                'rarity_label'      => 'Imperiums-Koloss',
                'badge_icon'        => '☀️',
                'icon'              => '☀️',
                'perk_summary'      => '6 ❤️ Rumpf • +100 % Feuerkraft • 90 % Schild • +140 % Magnet',
                'price_sparks'      => 6800,
                'min_level'         => 1,
                'vip_buyer_unlock'  => false,
                'glb_file'          => 'ship_hyperion_sun.glb',
                'is_vertex_colored' => true,
                'laser_color_hex'   => 0xfacc15,
                'laser_color_css'   => '#facc15',
                'trail_color_hex'   => 0xf59e0b,
                'stats' => [
                    'magnet_mult'          => 2.40,
                    'hitbox_mult'          => 0.72,
                    'skill_duration_mult'  => 1.65,
                    'start_shield'         => 90,
                    'spark_distance_bonus' => 60,
                    'max_hearts'           => 6,
                    'weapon_power_mult'    => 2.00,
                    'salvo_streams'        => 4,
                ],
                'perks' => [
                    '6 ❤️ Sonnengold-Rumpf & 90 % solares Start-Energieschild',
                    'Doppelte Waffen-Feuerkraft (+100 %) & 4-fach Solar-Kanonen',
                    '+140 % Magnet-Radius, +65 % Skill-Dauer & +60 m pro Funke',
                ],
            ],
            'celestial_ark' => [
                'key'               => 'celestial_ark',
                'category'          => 'battleship',
                'category_label'    => 'Schlachtschiff',
                'name'              => 'Astral-Arche der Götter',
                'subtitle'          => 'Mythisches Sternen-Mutterschiff mit Kardan-Astrolabium',
                'rarity'            => 'mythic',
                'rarity_label'      => 'Sternen-Arche',
                'badge_icon'        => '🌌',
                'icon'              => '🌌',
                'perk_summary'      => '6 ❤️ Rumpf • +125 % Feuerkraft • 100 % Schild • +165 % Magnet',
                'price_sparks'      => 8500,
                'min_level'         => 65,
                'vip_buyer_unlock'  => false,
                'glb_file'          => 'ship_celestial_ark.glb',
                'is_vertex_colored' => true,
                'laser_color_hex'   => 0xe879f9,
                'laser_color_css'   => '#e879f9',
                'trail_color_hex'   => 0xa855f7,
                'stats' => [
                    'magnet_mult'          => 2.65,
                    'hitbox_mult'          => 0.70,
                    'skill_duration_mult'  => 1.75,
                    'start_shield'         => 100,
                    'spark_distance_bonus' => 70,
                    'max_hearts'           => 6,
                    'weapon_power_mult'    => 2.25,
                    'salvo_streams'        => 4,
                ],
                'perks' => [
                    '6 ❤️ Astral-Rumpf & sofort 100 % volles Energieschild',
                    '+125 % Quanten-Feuerkraft & -30 % kleinere Hitbox',
                    '+165 % Magnet-Radius, +75 % Skill-Dauer & +70 m pro Funke',
                ],
            ],
            'dark_perl' => [
                'key'               => 'dark_perl',
                'category'          => 'battleship',
                'category_label'    => 'Schlachtschiff',
                'name'              => 'Dark Perl',
                'subtitle'          => 'Schwarzes VIP-Elite Obsidian-Schlachtschiff mit glühenden Lava-Adern',
                'rarity'            => 'vip',
                'rarity_label'      => 'VIP-Elite Supremacy',
                'badge_icon'        => '🌋',
                'icon'              => '🌋',
                'perk_summary'      => '7 ❤️ Rumpf • +160 % Lava-Salven (5-fach) • 100 % Schild • +200 % Magnet',
                'price_sparks'      => 12000,
                'min_level'         => 1,
                'vip_buyer_unlock'  => false,
                'glb_file'          => 'ship_dark_perl.glb',
                'is_vertex_colored' => true,
                'laser_color_hex'   => 0xff2a00,
                'laser_color_css'   => '#ff2a00',
                'trail_color_hex'   => 0xdc2626,
                'max_hearts'        => 7,
                'weapon_power_mult' => 2.60,
                'salvo_streams'     => 5,
                'stats' => [
                    'magnet_mult'          => 3.00,
                    'hitbox_mult'          => 0.65,
                    'skill_duration_mult'  => 2.00,
                    'start_shield'         => 100,
                    'spark_distance_bonus' => 85,
                    'max_hearts'           => 7,
                    'weapon_power_mult'    => 2.60,
                    'salvo_streams'        => 5,
                ],
                'perks' => [
                    'DAS ABSOLUT STÄRKSTE SCHIFF: 7 ❤️ Obsidian-Rumpf & 100 % Lava-Schild',
                    '5-fach Magma-Breitseiten mit +160 % Zerstörungskraft & 4 Dark-Lava-Drohnen',
                    '+200 % Mega-Magnet, doppelte Skill-Dauer (+100 %), -35 % Hitbox & +85 m / Funke',
                ],
            ],
        ];
    }

    public static function getSpaceStationsPlaceholder(): array
    {
        return [
            'aether_forge' => [
                'key'          => 'aether_forge',
                'name'         => 'Äther-Schmiede Orbit-Zitadelle',
                'subtitle'     => 'Passive Funken-Raffinerie & Flotten-Dock im hohen Orbit',
                'rarity'       => 'Geplante Groß-Erweiterung',
                'rarity_label' => 'Orbit-Zitadelle',
                'eta_badge'    => 'Phase III • In Kürze',
                'badge_icon'   => '🛰️',
                'icon'         => '🛰️',
                'description'  => 'Generiert kontinuierlich Sternen-Energie und rüstet deine Schlachtschiffe vor dem Abflug mit orbitalen Überladungs-Modulen aus.',
                'preview'      => 'Generiert kontinuierlich Sternen-Energie und rüstet deine Schlachtschiffe vor dem Abflug mit orbitalen Überladungs-Modulen aus.',
            ],
            'solaris_nexus' => [
                'key'          => 'solaris_nexus',
                'name'         => 'Solaris-Nexus Sternenbasis',
                'subtitle'     => 'Planetarer Verteidigungsring mit 360°-Laser-Phalanx',
                'rarity'       => 'Geplante Groß-Erweiterung',
                'rarity_label' => 'Sternenbasis',
                'eta_badge'    => 'Phase III • In Kürze',
                'badge_icon'   => '🪐',
                'icon'         => '🪐',
                'description'  => 'Dient als kommandierbare Basis im kommenden Sektor-Verteidigungsmodus gegen ganze Flotten von Lord Lieblos.',
                'preview'      => 'Dient als kommandierbare Basis im kommenden Sektor-Verteidigungsmodus gegen ganze Flotten von Lord Lieblos.',
            ],
            'obsidian_bastion' => [
                'key'          => 'obsidian_bastion',
                'name'         => 'Dark-Core Obsidian-Bastion',
                'subtitle'     => 'Heimatstation der legendären „Dark Perl“ mit Magma-Reaktor',
                'rarity'       => 'VIP-Endgame Vorschau',
                'rarity_label' => 'VIP-Festung',
                'eta_badge'    => 'VIP Phase III',
                'badge_icon'   => '🌋',
                'icon'         => '🌋',
                'description'  => 'Schaltet galaktische Spezial-Missionen, Boss-Belagerungen und exklusive Manufaktur-Artefakte frei.',
                'preview'      => 'Schaltet galaktische Spezial-Missionen, Boss-Belagerungen und exklusive Manufaktur-Artefakte frei.',
            ],
        ];
    }

    public static function getHallAuras(): array
    {
        return [
            'default' => [
                'key'              => 'default',
                'name'             => 'Klassischer Glanz',
                'subtitle'         => 'Dezente Darstellung in der Halle der Legenden',
                'description'      => 'Edle, zurückhaltende Manufaktur-Signatur ohne zusätzliche Partikel-Überlagerung.',
                'effect_type'      => 'classic',
                'glow_color'       => '#94a3b8',
                'badge_icon'       => '✨',
                'icon'             => '✨',
                'badge'            => null,
                'price_sparks'     => 0,
                'min_level'        => 1,
                'vip_buyer_unlock' => false,
                'row_class'        => 'sf-aura-row-default',
                'row_classes'      => 'sf-aura-row-default',
                'badge_class'      => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-700',
            ],
            'starlight_rain' => [
                'key'              => 'starlight_rain',
                'name'             => 'Sternensaphir-Schimmer',
                'subtitle'         => 'Pulsierender Kryo-Saphir-Lichtstrom mit schwebenden Eiskristallen',
                'description'      => 'Hüllt deinen Ranglisten-Platz und deine Visitenkarte in ein fließendes Saphir-Polarlicht mit funkelnden Kristall-Spitzen.',
                'effect_type'      => 'sapphire_pulse',
                'glow_color'       => '#22d3ee',
                'badge_icon'       => '💠',
                'icon'             => '💠',
                'badge'            => 'Sternensaphir',
                'price_sparks'     => 500,
                'min_level'        => 15,
                'vip_buyer_unlock' => false,
                'row_class'        => 'sf-aura-row-sapphire bg-gradient-to-r from-cyan-500/15 via-sky-500/10 to-teal-500/15 border-l-4 border-l-cyan-400 shadow-[inset_0_0_28px_rgba(34,211,238,0.18)]',
                'row_classes'      => 'sf-aura-row-sapphire bg-gradient-to-r from-cyan-500/15 via-sky-500/10 to-teal-500/15 border-l-4 border-l-cyan-400 shadow-[inset_0_0_28px_rgba(34,211,238,0.18)]',
                'badge_class'      => 'bg-cyan-500/20 text-cyan-700 dark:text-cyan-300 border-cyan-400/50 shadow-[0_0_12px_rgba(34,211,238,0.35)]',
            ],
            'solar_supernova' => [
                'key'              => 'solar_supernova',
                'name'             => 'Goldene Supernova',
                'subtitle'         => 'Strahlende Sonnengold-Eruption mit wandernder Korona-Welle',
                'description'      => 'Ein warmes 24k-Sonnenfeuer durchzieht deine Zeile mit goldenen Korona-Ringen und aufsteigenden Lichtfunken.',
                'effect_type'      => 'solar_flare',
                'glow_color'       => '#fbbf24',
                'badge_icon'       => '🌟',
                'icon'             => '🌟',
                'badge'            => 'Supernova',
                'price_sparks'     => 1200,
                'min_level'        => 50,
                'vip_buyer_unlock' => false,
                'row_class'        => 'sf-aura-row-supernova bg-gradient-to-r from-amber-500/20 via-yellow-500/12 to-orange-500/20 border-l-4 border-l-amber-400 shadow-[inset_0_0_32px_rgba(251,191,36,0.22)]',
                'row_classes'      => 'sf-aura-row-supernova bg-gradient-to-r from-amber-500/20 via-yellow-500/12 to-orange-500/20 border-l-4 border-l-amber-400 shadow-[inset_0_0_32px_rgba(251,191,36,0.22)]',
                'badge_class'      => 'bg-amber-500/25 text-amber-800 dark:text-amber-200 border-amber-400/60 shadow-[0_0_15px_rgba(251,191,36,0.45)]',
            ],
            'cosmic_emperor' => [
                'key'              => 'cosmic_emperor',
                'name'             => 'Kosmischer Imperator',
                'subtitle'         => 'Mythischer Amethyst-Quasar mit Dimensions-Nebel & Sternenwirbel',
                'description'      => 'Tiefviolettes Galaxie-Leuchten mit irisierendem Magenta-Gold-Farbverlauf und pulsierendem Quasar-Horizont.',
                'effect_type'      => 'cosmic_nebula',
                'glow_color'       => '#e879f9',
                'badge_icon'       => '🌌',
                'icon'             => '🌌',
                'badge'            => 'Imperator',
                'price_sparks'     => 2200,
                'min_level'        => 75,
                'vip_buyer_unlock' => false,
                'row_class'        => 'sf-aura-row-cosmic bg-gradient-to-r from-purple-600/25 via-fuchsia-500/15 to-amber-500/25 border-l-4 border-l-fuchsia-400 shadow-[inset_0_0_38px_rgba(217,70,239,0.28)]',
                'row_classes'      => 'sf-aura-row-cosmic bg-gradient-to-r from-purple-600/25 via-fuchsia-500/15 to-amber-500/25 border-l-4 border-l-fuchsia-400 shadow-[inset_0_0_38px_rgba(217,70,239,0.28)]',
                'badge_class'      => 'bg-fuchsia-500/25 text-fuchsia-800 dark:text-fuchsia-200 border-fuchsia-400/60 shadow-[0_0_18px_rgba(217,70,239,0.5)]',
            ],
            'manufaktur_royal' => [
                'key'              => 'manufaktur_royal',
                'name'             => 'Patron der Manufaktur (VIP)',
                'subtitle'         => 'Ultimative Obsidian-Gold-lava Thron-Aura mit animiertem Kronen-Siegel & Lichtstrahlen',
                'description'      => 'Die königliche Meister-Aura: Tiefschwarzes Obsidian-Samt, durchzogen von flüssigen 24k-Gold-Lichtwellen, rotierendem Manufaktur-Wappen und schwebenden Gold-Diamant-Partikeln!',
                'effect_type'      => 'vip_royal_throne',
                'glow_color'       => '#facc15',
                'badge_icon'       => '👑',
                'icon'             => '👑',
                'badge'            => 'Patron der Manufaktur',
                'price_sparks'     => 3500,
                'min_level'        => 1,
                'vip_buyer_unlock' => true,
                'row_class'        => 'sf-aura-row-vip-royal border-l-4 border-l-yellow-300',
                'row_classes'      => 'sf-aura-row-vip-royal border-l-4 border-l-yellow-300',
                'badge_class'      => 'sf-aura-badge-vip-royal bg-gradient-to-r from-amber-400 via-yellow-200 to-amber-500 text-gray-950 border-yellow-200 shadow-[0_0_22px_rgba(250,204,21,0.85)] font-black',
            ],
        ];
    }

    public static function getHeroMottos(): array
    {
        $out = [];
        foreach (self::getHeroMottosDetails() as $key => $info) {
            $out[$key] = $info['text'];
        }
        return $out;
    }

    public static function getHeroMottosDetails(): array
    {
        return [
            'default' => [
                'key'              => 'default',
                'title'            => 'Sternen-Pionier',
                'icon'             => '🚀',
                'text'             => 'Bereit für den nächsten Funkenflug durch die Galaxis!',
                'buff_label'       => '+5 % Funken-Magnet',
                'buff_description' => 'Vergrößert im Funkenflug-Express deinen passiven Magnet-Radius für Seelenfunken um +5 %.',
                'buff_stat'        => 'magnet_mult',
                'buff_value'       => 0.05,
            ],
            'lord' => [
                'key'              => 'lord',
                'title'            => 'Rebell gegen Lord Lieblos',
                'icon'             => '⚡',
                'text'             => 'Lord Lieblos sieht im Rückspiegel nur meinen Sternenstaub.',
                'buff_label'       => '+8 % Skill-Dauer',
                'buff_description' => 'Verlängert die Wirkungsdauer deiner 4 aktiven Schiffsfähigkeiten um +8 %.',
                'buff_stat'        => 'skill_duration_mult',
                'buff_value'       => 0.08,
            ],
            'craft' => [
                'key'              => 'craft',
                'title'            => 'Meister der Manufaktur',
                'icon'             => '⚒️',
                'text'             => 'Handgefertigte Präzision – in der Manufaktur und im Kosmos.',
                'buff_label'       => '+10 % Start-Schild-Bonus',
                'buff_description' => 'Gewährt dir beim Start jedes Funkenflug-Einsatzes sofort +10 % zusätzliches Energieschild.',
                'buff_stat'        => 'start_shield_bonus',
                'buff_value'       => 10,
            ],
            'collector' => [
                'key'              => 'collector',
                'title'            => 'Artefakt-Sucher',
                'icon'             => '💎',
                'text'             => 'Jäger der seltensten Seelenfunken & Kristall-Artefakte.',
                'buff_label'       => '+10 % Funken-Magnet',
                'buff_description' => 'Zieht Seelenfunken und Äther-Kühlkerne aus +10 % größerer Distanz automatisch an.',
                'buff_stat'        => 'magnet_mult',
                'buff_value'       => 0.10,
            ],
            'boss' => [
                'key'              => 'boss',
                'title'            => 'Bezwingen des Kolosses',
                'icon'             => '🎯',
                'text'             => 'Selbst der Paragraphen-Koloss §-0815 kann mich nicht bremsen!',
                'buff_label'       => '+12 % Boss-Feuerkraft',
                'buff_description' => 'Erhöht deinen Laser- und Salven-Schaden gegen Sektor-Bosse und Zwillings-Bosse um +12 %.',
                'buff_stat'        => 'weapon_power_mult',
                'buff_value'       => 0.12,
            ],
            'patron' => [
                'key'              => 'patron',
                'title'            => 'Hüter der Flamme',
                'icon'             => '❤️',
                'text'             => 'Stolzer Patron der Manufaktur & Hüter des ewigen Lichts.',
                'buff_label'       => '+25 % Herz-Drop-Glück ❤️',
                'buff_description' => 'Erhöht die Chance auf seltene Rote Reparatur-Herzen im Gefecht um +25 %.',
                'buff_stat'        => 'heart_drop_mult',
                'buff_value'       => 0.25,
            ],
            'speed' => [
                'key'              => 'speed',
                'title'            => 'Warp-Navigator',
                'icon'             => '☄️',
                'text'             => 'Lichtgeschwindigkeit ist für mich erst das Aufwärmtraining.',
                'buff_label'       => '+3 m Distanz pro Funke',
                'buff_description' => 'Jeder eingesammelte Seelenfunke katapultiert dich +3 zusätzliche Meter weiter vorwärts.',
                'buff_stat'        => 'spark_distance_bonus',
                'buff_value'       => 3,
            ],
            'legend' => [
                'key'              => 'legend',
                'title'            => 'Ewige Legende',
                'icon'             => '👑',
                'text'             => 'Mein Name ist in den Sternen der Halle der Legenden eingraviert.',
                'buff_label'       => '+8 % Allround-Kampfmoral',
                'buff_description' => 'Kombiniert +8 % Waffen-Feuerkraft mit +8 % größerem Funken-Magnet-Radius.',
                'buff_stat'        => 'allround_boost',
                'buff_value'       => 0.08,
            ],
        ];
    }

    public static function getMegaTitles(): array
    {
        return [
            ['req' => 1,  'name' => 'Eine Art große Sache'],
            ['req' => 3,  'name' => 'Die Leute kennen mich'],
            ['req' => 5,  'name' => 'Rechte Hand eines Engels'],
            ['req' => 10, 'name' => 'Engel auf Erden'],
            ['req' => 12, 'name' => 'Oberster Engelsführer'],
            ['req' => 15, 'name' => 'Unsterblicher Seelengott'],
        ];
    }

    public static function getTitles(): array
    {
        return [
            'spieler' => [
                'name' => 'Meister der Spiele',
                'description' => 'Minispiele in der Manufaktur gespielt',
                'tiers' => [
                    'grau'    => ['req' => 0,    'name' => 'Zuschauer'],
                    'silber'  => ['req' => 5,    'name' => 'Herausforderer'],
                    'gold'    => ['req' => 25,   'name' => 'Taktiker'],
                    'diamant' => ['req' => 100,  'name' => 'Arcade-Legende']
                ]
            ],
            'sammler' => [
                'name' => 'Funken-Sammler',
                'description' => 'Versteckte Funken auf der Website gefunden',
                'tiers' => [
                    'grau'    => ['req' => 0,    'name' => 'Beobachter'],
                    'silber'  => ['req' => 50,   'name' => 'Sucher'],
                    'gold'    => ['req' => 200,  'name' => 'Jäger'],
                    'diamant' => ['req' => 1000, 'name' => 'Meister der Funken']
                ]
            ],
            'botschafter' => [
                'name' => 'Der Botschafter',
                'description' => 'Verifizierte Produktbewertungen geschrieben',
                'tiers' => [
                    'grau'    => ['req' => 0,  'name' => 'Stiller Genießer'],
                    'silber'  => ['req' => 1,  'name' => 'Wortschmied'],
                    'gold'    => ['req' => 5,  'name' => 'Meinungsmacher'],
                    'diamant' => ['req' => 15, 'name' => 'Stimme der Seele']
                ]
            ],
            'bildreporter' => [
                'name' => 'Visueller Poet',
                'description' => 'Bewertungen mit Bildern oder Videos verfasst',
                'tiers' => [
                    'grau'    => ['req' => 0,  'name' => 'Kamerascheu'],
                    'silber'  => ['req' => 1,  'name' => 'Knipser'],
                    'gold'    => ['req' => 3,  'name' => 'Fotograf'],
                    'diamant' => ['req' => 10, 'name' => 'Meister der Linsen']
                ]
            ],
            'wortgewandt' => [
                'name' => 'Der Gelehrte',
                'description' => 'Ausführliche Bewertungen (über 100 Zeichen) verfasst',
                'tiers' => [
                    'grau'    => ['req' => 0,  'name' => 'Kurz angebunden'],
                    'silber'  => ['req' => 1,  'name' => 'Schreiber'],
                    'gold'    => ['req' => 5,  'name' => 'Autor'],
                    'diamant' => ['req' => 15, 'name' => 'Poet der Manufaktur']
                ]
            ],
            'treuer_bewerter' => [
                'name' => 'Bote des Lichts',
                'description' => 'Perfekte 5-Sterne Bewertungen vergeben',
                'tiers' => [
                    'grau'    => ['req' => 0,  'name' => 'Kritiker'],
                    'silber'  => ['req' => 1,  'name' => 'Zufriedener'],
                    'gold'    => ['req' => 5,  'name' => 'Enthusiast'],
                    'diamant' => ['req' => 15, 'name' => 'Wahrer Fan']
                ]
            ],
            'schatzhueter' => [
                'name' => 'Der Schatzhüter',
                'description' => 'Erfolgreich abgeschlossene Bestellungen',
                'tiers' => [
                    'grau'    => ['req' => 0,  'name' => 'Interessent'],
                    'silber'  => ['req' => 1,  'name' => 'Neuling'],
                    'gold'    => ['req' => 5,  'name' => 'Kenner'],
                    'diamant' => ['req' => 15, 'name' => 'Patron der Manufaktur']
                ]
            ],
            'massenkaeufer' => [
                'name' => 'Der Großabnehmer',
                'description' => 'Gesamtzahl der gekauften Einzelartikel',
                'tiers' => [
                    'grau'    => ['req' => 0,   'name' => 'Minimalist'],
                    'silber'  => ['req' => 5,   'name' => 'Sammler'],
                    'gold'    => ['req' => 20,  'name' => 'Horter'],
                    'diamant' => ['req' => 100, 'name' => 'Lager-Plünderer']
                ]
            ],
            'entdecker' => [
                'name' => 'Der Entdecker',
                'description' => 'Verschiedene Produkt-Kategorien ausprobiert',
                'tiers' => [
                    'grau'    => ['req' => 0, 'name' => 'Gewohnheitstier'],
                    'silber'  => ['req' => 1, 'name' => 'Neugieriger'],
                    'gold'    => ['req' => 3, 'name' => 'Weltenbummler'],
                    'diamant' => ['req' => 6, 'name' => 'Meister der Vielfalt']
                ]
            ],
            'freudebringer' => [
                'name' => 'Der Freudebringer',
                'description' => 'Geschenke an abweichende Lieferadressen versendet',
                'tiers' => [
                    'grau'    => ['req' => 0,  'name' => 'Selbstbeschenker'],
                    'silber'  => ['req' => 1,  'name' => 'Aufmerksamer Freund'],
                    'gold'    => ['req' => 3,  'name' => 'Herzensmensch'],
                    'diamant' => ['req' => 10, 'name' => 'Bote der Freude']
                ]
            ],
            'nachtschwaermer' => [
                'name' => 'Kreatur der Nacht',
                'description' => 'Bestellungen zwischen 22:00 und 05:00 Uhr getätigt',
                'tiers' => [
                    'grau'    => ['req' => 0,  'name' => 'Tagträumer'],
                    'silber'  => ['req' => 1,  'name' => 'Nachteule'],
                    'gold'    => ['req' => 3,  'name' => 'Mondwandler'],
                    'diamant' => ['req' => 10, 'name' => 'Fürst der Finsternis']
                ]
            ],
            'wochenend_shopper' => [
                'name' => 'Wochenend-Krieger',
                'description' => 'Bestellungen am Samstag oder Sonntag getätigt',
                'tiers' => [
                    'grau'    => ['req' => 0,  'name' => 'Werktags-Mensch'],
                    'silber'  => ['req' => 1,  'name' => 'Sonntags-Shopper'],
                    'gold'    => ['req' => 5,  'name' => 'Wochenend-Genießer'],
                    'diamant' => ['req' => 15, 'name' => 'Meister der Freizeit']
                ]
            ],
            'treue_seele' => [
                'name' => 'Treue Seele',
                'description' => 'Tage seit deiner Registrierung in der Manufaktur',
                'tiers' => [
                    'grau'    => ['req' => 0,   'name' => 'Frischling'],
                    'silber'  => ['req' => 30,  'name' => 'Bekanntes Gesicht'],
                    'gold'    => ['req' => 180, 'name' => 'Stammgast'],
                    'diamant' => ['req' => 365, 'name' => 'Urgestein']
                ]
            ],
            'wiederholungstaeter' => [
                'name' => 'Der Wiederholungstäter',
                'description' => 'In verschiedenen Monaten bestellt',
                'tiers' => [
                    'grau'    => ['req' => 0,  'name' => 'Einmalkäufer'],
                    'silber'  => ['req' => 2,  'name' => 'Rückkehrer'],
                    'gold'    => ['req' => 6,  'name' => 'Saison-Shopper'],
                    'diamant' => ['req' => 12, 'name' => 'Ewiger Begleiter']
                ]
            ],
            'funkenkoenig' => [
                'name' => 'Der Alchemist',
                'description' => 'Gesamtzahl aller jemals verdienten Funken',
                'tiers' => [
                    'grau'    => ['req' => 0,    'name' => 'Funkenlos'],
                    'silber'  => ['req' => 100,  'name' => 'Zauberlehrling'],
                    'gold'    => ['req' => 1000, 'name' => 'Magier'],
                    'diamant' => ['req' => 5000, 'name' => 'Herr der Energie']
                ]
            ],
        ];
    }
}

<?php

namespace App\Services\Gamification;

class GameConfig
{
    // NEU: Das Maximallevel ist jetzt exakt 10
    public const MAX_LEVEL = 10;
    public const DAILY_SPARK_LIMIT = 20;

    /**
     * Definiert die Aussehens-Meilensteine für Funki (10 Level)
     */
    public static function getAppearanceMilestones(): array
    {
        return [
            1  => 'funki_lvl_1_rags',         // Start-Level: In Lumpen
            2  => 'funki_lvl_2_basic',        // Level 2: Erste saubere Kleidung
            3  => 'funki_lvl_3_helper',       // Level 3: Fleißiger Helfer
            4  => 'funki_lvl_4_novice',       // Level 4: Novize der Werkstatt
            5  => 'funki_lvl_5_apprentice',   // Level 5: Lehrling (Das neue Teaser-Bild)
            6  => 'funki_lvl_6_craftsman',    // Level 6: Handwerker
            7  => 'funki_lvl_7_artisan',      // Level 7: Kunsthandwerker
            8  => 'funki_lvl_8_master',       // Level 8: Meister
            9  => 'funki_lvl_9_lightbringer', // Level 9: Lichtbringer
            10 => 'funki_lvl_10_god_of_soul'  // Max-Level: Epische Rüstung / Seelengott
        ];
    }

    /**
     * Definiert die Belohnungen. Angepasst an 10 Level.
     */
    public static function getLevelRewards(): array
    {
        return [
            3  => ['type' => 'free_shipping', 'name' => 'Kostenloser Versand'],
            5  => ['type' => 'percent', 'value' => 10, 'name' => '10% Rabatt-Gutschein'],
            8  => ['type' => 'fixed', 'value' => 1500, 'name' => '15 € Wert-Gutschein'],
            10 => ['type' => 'percent', 'value' => 20, 'name' => '20% Seelengott-Gutschein'],
        ];
    }

    public static function getTitles(): array
    {
        // ... (Dein bisheriger Code aus getTitles() bleibt exakt gleich) ...
        return [
            'sammler' => [
                'id' => 'sammler',
                'name' => 'Funken-Sammler',
                'description' => 'Versteckte Funken auf der Website gefunden',
                'icon' => 'sparkles',
                'tiers' => [
                    'grau'    => ['req' => 0,    'name' => 'Beobachter'],
                    'silber'  => ['req' => 100,  'name' => 'Sucher'],
                    'gold'    => ['req' => 500,  'name' => 'Jäger'],
                    'diamant' => ['req' => 2000, 'name' => 'Meister der Funken']
                ]
            ],
            'botschafter' => [
                'id' => 'botschafter',
                'name' => 'Der Botschafter',
                'description' => 'Verifizierte Produktbewertungen geschrieben',
                'icon' => 'chat-bubble-bottom-center-text',
                'tiers' => [
                    'grau'    => ['req' => 0,  'name' => 'Stiller Genießer'],
                    'silber'  => ['req' => 1,  'name' => 'Wortschmied'],
                    'gold'    => ['req' => 5,  'name' => 'Meinungsmacher'],
                    'diamant' => ['req' => 15, 'name' => 'Stimme der Seele']
                ]
            ],
            'freudebringer' => [
                'id' => 'freudebringer',
                'name' => 'Der Freudebringer',
                'description' => 'Geschenke an abweichende Lieferadressen versendet',
                'icon' => 'gift',
                'tiers' => [
                    'grau'    => ['req' => 0,  'name' => 'Selbstbeschenker'],
                    'silber'  => ['req' => 1,  'name' => 'Aufmerksamer Freund'],
                    'gold'    => ['req' => 3,  'name' => 'Herzensmensch'],
                    'diamant' => ['req' => 10, 'name' => 'Bote des Lichts']
                ]
            ],
            'schatzhueter' => [
                'id' => 'schatzhueter',
                'name' => 'Der Schatzhüter',
                'description' => 'Erfolgreich abgeschlossene Bestellungen',
                'icon' => 'shopping-bag',
                'tiers' => [
                    'grau'    => ['req' => 0,  'name' => 'Interessent'],
                    'silber'  => ['req' => 1,  'name' => 'Neuling'],
                    'gold'    => ['req' => 5,  'name' => 'Kenner'],
                    'diamant' => ['req' => 15, 'name' => 'Patron der Manufaktur']
                ]
            ],
            'entdecker' => [
                'id' => 'entdecker',
                'name' => 'Der Entdecker',
                'description' => 'Verschiedene Produkt-Kategorien ausprobiert',
                'icon' => 'map',
                'tiers' => [
                    'grau'    => ['req' => 0, 'name' => 'Gewohnheitstier'],
                    'silber'  => ['req' => 1, 'name' => 'Neugieriger'],
                    'gold'    => ['req' => 3, 'name' => 'Weltenbummler'],
                    'diamant' => ['req' => 5, 'name' => 'Meister der Materie']
                ]
            ]
        ];
    }
}

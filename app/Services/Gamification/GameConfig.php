<?php

namespace App\Services\Gamification;

class GameConfig
{
    public const MAX_LEVEL = 10;
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

    // NEU: Echte Coupon-Struktur für die Generierung (Margen-geschützt!)
    public static function getLevelRewards(): array
    {
        return [
            3  => ['type' => 'percentage', 'value' => 5,  'name' => '5% Rabatt-Gutschein'],
            5  => ['type' => 'percentage', 'value' => 8,  'name' => '8% Rabatt-Gutschein'],
            8  => ['type' => 'percentage', 'value' => 10, 'name' => '10% Rabatt-Gutschein'],
            10 => ['type' => 'percentage', 'value' => 15, 'name' => '15% Seelengott-Rabatt'],
        ];
    }

    public static function getMegaTitles(): array
    {
        return [
            ['req' => 0,  'name' => 'Ein Funke im Wind'],
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

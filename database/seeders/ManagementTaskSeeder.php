<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManagementTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $phases = [
            [
                'list' => [
                    'name' => 'Aufgaben',
                    'icon' => 'home',
                    'color' => '#818CF8',
                ],
                'taks' => [
                    [
                        'title' => 'Barmenia Wohngebäude (Nr. 304734394) zum 31.12.2026 kündigen, sobald DEVK-Police da ist',
                        'priority' => 'hoch',
                        'ai_plan' => "Vertragsnummer Barmenia: 304734394 (Kunden-Nr. 704555407).\nKündigung zum 31.12.2026 einreichen, sobald die neue DEVK Komfort Police (552,24 €/Jahr) vorliegt.",
                    ],
                    ['title' => 'Wohngebäude Versicherung prüfen', 'priority' => 'niedrig'],
                    ['title' => 'Burger was noch fehlt', 'priority' => 'niedrig'],
                ],
            ],
            [
                'list' => [
                    'name' => 'Mein-Seelenfunke Shop',
                    'icon' => 'shopping-bag',
                    'color' => '#818CF8',
                ],
                'taks' => [
                    ['title' => 'Echte Produktbilder erstellen (siehe detaillierte Checklisten in PHASE 1 - PRODUKTE)', 'priority' => 'hoch'],
                    ['title' => 'Weitere Produkte raussuchen', 'priority' => 'niedrig'],
                ],
            ],
            [
                'list' => [
                    'name' => 'Immobilienportal',
                    'icon' => 'building-office',
                    'color' => '#38BDF8',
                ],
                'taks' => [],
            ],
            [
                'list' => [
                    'name' => 'PHASE 1 - PRODUKTE',
                    'icon' => 'cube',
                    'color' => '#E53E3E',
                ],
                'taks' => [
                    [
                        'title' => 'Produkt 1: Der Seelen Kristall (K9-Kristallglas 180 x 200 x 40 mm) -> Echte Fotos, Video & 8 Anlass-Vorlagen',
                        'priority' => 'hoch',
                        'ai_plan' => "Slug: seelen-kristall | SKU: MSF-KRISTALL-3D-001 | Maße: 180 x 200 x 40 mm\nAblauf: 1x echtes Meister-Musterstück im 5W UV-Laser gravieren (oder für die 8 Anlass-Vorlagen den echten Rohling im jeweiligen Deko-Setup fotografieren und die Anlass-Gravur digital von Antigravity exakt ins Glas legen lassen, ohne das echte Foto zu verändern).\nZielpfade:\n- Konfigurator-Overlay: storage/app/public/produkte/products/seelen-kristall/overlay.png\n- Galerie: storage/app/public/produkte/products/seelen-kristall/\n- Vorlagen: storage/app/public/produkte/product-templates/seelen-kristall-{anlass}.jpg",
                        'subtasks' => [
                            '2D-Konfigurator-Basis (overlay.png): Ungravierter K9-Kristall exakt frontal vor neutralem Hintergrund',
                            'Standardbild 1 (Hauptbild): Echtes 3D-gelasertes Musterstück frontal scharf im Fokus (seelen-kristall_b.jpg)',
                            'Standardbild 2 (Detail): Makro-Nahaufnahme der 3D-Innengravur & Facetten-Lichtbrechung',
                            'Standardbild 3 (Lifestyle / Hover): Kristall in den Händen / im warmen Wohnraum-Licht (seelen-kristall-lifestyle.jpg)',
                            'Standardbild 4 (Verpackung): Kristall mit Geschenkbox & Schwarz-Gold-Geschenkpapier',
                            'Standardbild 5 (Maßbild): Echtes Produktfoto als Basis für Bemaßung 180 x 200 x 40 mm (seelen-kristall-dimensions.jpg)',
                            'Produktvideo (MP4, 5-8 Sek.): Schwenk um den echten gelaserten Kristall im Licht (für Shop & Reels)',
                            'Vorlage Weihnachten (seelen-kristall-weihnachten.jpg): Motiv Weihnachtsbaum + "Frohe Weihnachten! - Familie Mustermann -"',
                            'Vorlage Ostern (seelen-kristall-ostern.jpg): Motiv Häschen + "Frohes Osterfest!"',
                            'Vorlage Hochzeit (seelen-kristall-hochzeit.jpg): Motiv Eheringe + "Lieblingsmensch 24.08.2026"',
                            'Vorlage Geburtstag (seelen-kristall-geburtstag.jpg): Motiv Geschenk + "Happy Birthday!"',
                            'Vorlage Valentinstag (seelen-kristall-valentinstag.jpg): Motiv Doppelherzen + "Für immer Dein - 14. Februar -"',
                            'Vorlage Halloween / Herbst (seelen-kristall-halloween.jpg): Motiv Mond & Sterne + "Magische Nacht - Seelenzauber -"',
                            'Vorlage Muttertag (seelen-kristall-muttertag.jpg): Motiv Herzkranz + "Beste Mama der Welt"',
                            'Vorlage Vatertag (seelen-kristall-vatertag.jpg): Motiv Siegerpokal + "Bester Papa der Welt!"',
                        ],
                    ],
                    [
                        'title' => 'Produkt 2: Der Seelenanhänger (Alu-Herz schwarz eloxiert 45 x 40 x 2,3 mm) -> Echte Fotos, Video & 8 Anlass-Vorlagen',
                        'priority' => 'hoch',
                        'ai_plan' => "Slug: seelen-anhaenger | SKU: MSF-ALU-HERZ-001 | Maße: 45 x 40 x 2,3 mm (Beidseitig gravierbar)\nAblauf: 1x echtes Musterstück beidseitig lasern (Vorderseite Wunschmotiv, Rückseite 'Für immer verbunden ♥'). Für die 8 Anlass-Vorlagen entweder lasern oder auf Basis deines echten ungravierten Fotos die Gravur von Antigravity exakt in Silber-Weiß auflegen lassen.\nZielpfade:\n- Konfigurator-Overlay: storage/app/public/produkte/products/seelen-anhaenger/overlay.png\n- Galerie: storage/app/public/produkte/products/seelen-anhaenger/\n- Vorlagen: storage/app/public/produkte/product-templates/seelen-anhaenger-{anlass}.jpg",
                        'subtasks' => [
                            '2D-Konfigurator-Basis (overlay.png): Ungraviertes schwarzes Alu-Herz exakt frontal von oben',
                            'Standardbild 1 (Hauptbild): Echtes gelasertes Alu-Herz Vorderseite scharf im Fokus',
                            'Standardbild 2 (Rückseite): Echtes Foto der Rückseiten-Gravur "Für immer verbunden ♥" (seelen-anhaenger-back.jpg)',
                            'Standardbild 3 (Lifestyle / Hover): Graviertes Alu-Herz am Schlüsselbund / Rucksack oder in der Hand (seelen-anhaenger-lifestyle.jpg)',
                            'Standardbild 4 (Verpackung): Alu-Herz drapiert in der schwarzen Westpack-Schmuckbox',
                            'Standardbild 5 (Maßbild): Echtes Produktfoto als Basis für Bemaßung 45 x 40 x 2,3 mm (seelen-anhaenger-dimensions.jpg)',
                            'Produktvideo (MP4, 5-8 Sek.): Schwenk über Vorder- und Rückseiten-Gravur im Licht',
                            'Vorlage Weihnachten (seelen-anhaenger-weihnachten.jpg): Motiv Weihnachtsbaum + "Frohe Weihnachten! Familie Mustermann"',
                            'Vorlage Ostern (seelen-anhaenger-ostern.jpg): Motiv Häschen + "Frohes Osterfest!"',
                            'Vorlage Hochzeit (seelen-anhaenger-hochzeit.jpg): Motiv Eheringe + "Lieblingsmensch 24.08.2026"',
                            'Vorlage Geburtstag (seelen-anhaenger-geburtstag.jpg): Motiv Geschenk + "Happy Birthday!"',
                            'Vorlage Valentinstag (seelen-anhaenger-valentinstag.jpg): Motiv Doppelherzen + "Für immer Dein - 14. Februar -"',
                            'Vorlage Halloween / Herbst (seelen-anhaenger-halloween.jpg): Motiv Mond & Sterne + "Magische Nacht Seelenzauber"',
                            'Vorlage Muttertag (seelen-anhaenger-muttertag.jpg): Motiv Herzkranz + "Beste Mama der Welt"',
                            'Vorlage Vatertag (seelen-anhaenger-vatertag.jpg): Motiv Siegerpokal + "Bester Papa der Welt!"',
                        ],
                    ],
                    [
                        'title' => 'Produkt 3: Der Feierabend-Funke (Weizenbierglas 0,5l mit 360°-Gravur, Ø 74 x 218 mm) -> Echte Fotos, Video & 8 Anlass-Vorlagen',
                        'priority' => 'hoch',
                        'ai_plan' => "Slug: weizenglas-personalisiert | SKU: MSF-GLAS-WEIZEN-001 | Maße: Ø 74 x 218 mm (360° Rotationsgravur mit RA3)\nAblauf: 1x echtes Weizenbierglas auf dem RA3 Rotationsmodul gravieren und sowohl leer (vor dunklem/neutralem Kontrast) als auch frisch eingeschenkt fotografieren.\nZielpfade:\n- Konfigurator-Overlay: storage/app/public/produkte/products/weizenglas-personalisiert/overlay.png\n- Galerie: storage/app/public/produkte/products/weizenglas-personalisiert/\n- Vorlagen: storage/app/public/produkte/product-templates/weizenglas-personalisiert-{anlass}.jpg",
                        'subtasks' => [
                            '2D-Konfigurator-Basis (overlay.png): Ungraviertes Weizenglas exakt gerade frontal vor neutralem Hintergrund',
                            'Standardbild 1 (Hauptbild): Echtes graviertes Weizenglas scharf im Fokus (beer_glas_main.jpg)',
                            'Standardbild 2 (Detail): Makro-Aufnahme der mattierten UV-Lasergravur auf dem Kristallglas',
                            'Standardbild 3 (Lifestyle / Hover): Frisch eingeschenktes Weizenglas mit Schaumkrone im Feierabend-Ambiente (weizenglas-lifestyle.jpg)',
                            'Standardbild 4 (Verpackung): Weizenglas mit Schwarz-Gold-Geschenkpapier / Versandkarton',
                            'Standardbild 5 (Maßbild): Echtes Produktfoto als Basis für Bemaßung 218 x Ø 74 mm (weizenglas-dimensions.jpg)',
                            'Produktvideo (MP4, 5-8 Sek.): 360°-Drehung des echten gravierten Weizenglases im Licht',
                            'Vorlage Weihnachten (weizenglas-personalisiert-weihnachten.jpg): Motiv Weihnachtsbaum + "Frohe Weihnachten! Familie Mustermann"',
                            'Vorlage Ostern (weizenglas-personalisiert-ostern.jpg): Motiv Häschen + "Frohes Osterfest!"',
                            'Vorlage Hochzeit (weizenglas-personalisiert-hochzeit.jpg): Motiv Eheringe + "Lieblingsmensch 24.08.2026"',
                            'Vorlage Geburtstag (weizenglas-personalisiert-geburtstag.jpg): Motiv Geschenk + "Happy Birthday!"',
                            'Vorlage Valentinstag (weizenglas-personalisiert-valentinstag.jpg): Motiv Doppelherzen + "Für immer Dein - 14. Februar -"',
                            'Vorlage Halloween / Herbst (weizenglas-personalisiert-halloween.jpg): Motiv Mond & Sterne + "Magische Nacht - Hexengebräu -"',
                            'Vorlage Muttertag (weizenglas-personalisiert-muttertag.jpg): Motiv Herzkranz + "Beste Mama der Welt"',
                            'Vorlage Vatertag (weizenglas-personalisiert-vatertag.jpg): Motiv Bierkrug + "Bester Papa der Welt!"',
                        ],
                    ],
                    [
                        'title' => 'Produkt 4: Der Seelen-Talisman (Gold-Edelstahl-Schlüsselanhänger 50 x 28 x 2,2 mm) -> Echte Fotos, Video & 8 Anlass-Vorlagen',
                        'priority' => 'hoch',
                        'ai_plan' => "Slug: seelen-talisman | SKU: MSF-EDELSTAHL-TALISMAN-001 | Maße: 50 x 28 x 2,2 mm (Ø 30 mm Flachring, beidseitig gravierbar)\nAblauf: 1x echtes Musterstück beidseitig gravieren (Vorderseite Wunschmotiv, Rückseite Koordinaten 52°31'12\"N 13°24'18\"E).\nZielpfade:\n- Konfigurator-Overlay: storage/app/public/produkte/products/seelen-talisman/overlay.png\n- Galerie: storage/app/public/produkte/products/seelen-talisman/\n- Vorlagen: storage/app/public/produkte/product-templates/seelen-talisman-{anlass}.jpg",
                        'subtasks' => [
                            '2D-Konfigurator-Basis (overlay.png): Ungravierter Gold-Edelstahl-Schlüsselanhänger exakt frontal von oben',
                            'Standardbild 1 (Hauptbild): Echter gelaserter Gold-Talisman Vorderseite (seelen-talisman-main.jpg)',
                            'Standardbild 2 (Rückseite): Rückseite mit Koordinaten-Gravur "52°31\'12\"N 13°24\'18\"E" (seelen-talisman-back.jpg)',
                            'Standardbild 3 (Lifestyle / Hover): Gold-Talisman am echten Haustür-/Autoschlüsselbund (seelen-talisman-lifestyle.jpg)',
                            'Standardbild 4 (Verpackung): Gold-Talisman in der schwarzen Westpack-Schmuckbox',
                            'Standardbild 5 (Maßbild): Echtes Produktfoto als Basis für Bemaßung 50 x 28 x 2,2 mm & Ø 30 mm Ring (seelen-talisman-dimensions.jpg)',
                            'Produktvideo (MP4, 5-8 Sek.): Spiegelglanz des Gold-Finishs & tiefschwarze Lasergravur im Licht',
                            'Vorlage Weihnachten (seelen-talisman-weihnachten.jpg): Motiv Weihnachtsbaum + "Frohe Weihnachten! Familie Mustermann"',
                            'Vorlage Ostern (seelen-talisman-ostern.jpg): Motiv Häschen + "Frohes Osterfest!"',
                            'Vorlage Hochzeit (seelen-talisman-hochzeit.jpg): Motiv Eheringe + "Lieblingsmensch 24.08.2026"',
                            'Vorlage Geburtstag (seelen-talisman-geburtstag.jpg): Motiv Geschenk + "Happy Birthday!"',
                            'Vorlage Valentinstag (seelen-talisman-valentinstag.jpg): Motiv Doppelherzen + "Für immer Dein - 14. Februar -"',
                            'Vorlage Halloween / Herbst (seelen-talisman-halloween.jpg): Motiv Mond & Sterne + "Magische Nacht Seelenzauber"',
                            'Vorlage Muttertag (seelen-talisman-muttertag.jpg): Motiv Herzkranz + "Beste Mama der Welt"',
                            'Vorlage Vatertag (seelen-talisman-vatertag.jpg): Motiv Siegerpokal + "Bester Papa der Welt!"',
                        ],
                    ],
                    [
                        'title' => 'Produkt 5: Die Seelen-Pfote (Edelstahl-Hundemarke Knochenform 40 x 22 x 1,8 mm) -> Echte Fotos, Video & 8 Anlass-Vorlagen',
                        'priority' => 'hoch',
                        'ai_plan' => "Slug: seelen-pfote-hundemarke | SKU: MSF-EDELSTAHL-PFOTE-001 | Maße: 40 x 22 x 1,8 mm inkl. Gold-Spaltring & Schlüsselring\nAblauf: 1x echte Hundemarke beidseitig gravieren (Vorderseite Hundename 'BALU', Rückseite 'Tel: 0176 / 1234567 Bitte Frauchen rufen!').\nZielpfade:\n- Konfigurator-Overlay: storage/app/public/produkte/products/seelen-pfote-hundemarke/overlay.png\n- Galerie: storage/app/public/produkte/products/seelen-pfote-hundemarke/\n- Vorlagen: storage/app/public/produkte/product-templates/seelen-pfote-hundemarke-{anlass}.jpg",
                        'subtasks' => [
                            '2D-Konfigurator-Basis (overlay.png): Ungravierte Hundemarke mit Pfoten-Durchbruch exakt frontal von oben',
                            'Standardbild 1 (Hauptbild): Echte gelaserte Hundemarke Vorderseite mit Name "BALU" (seelen-pfote-main.jpg)',
                            'Standardbild 2 (Rückseite): Rückseite mit Notfall-Telefonnummer "Bitte Frauchen rufen!" (seelen-pfote-back.jpg)',
                            'Standardbild 3 (Lifestyle / Hover): Hundemarke am echten Hundehalsband / Geschirr (seelen-pfote-lifestyle.jpg)',
                            'Standardbild 4 (Verpackung): Hundemarke in der schwarzen Westpack-Schmuckbox',
                            'Standardbild 5 (Maßbild): Echtes Produktfoto als Basis für Bemaßung 40 x 22 x 1,8 mm (seelen-pfote-dimensions.jpg)',
                            'Produktvideo (MP4, 5-8 Sek.): Schwenk über Vorderseite, Pfoten-Durchbruch & Rückseiten-Notfallnummer',
                            'Vorlage Weihnachten (seelen-pfote-hundemarke-weihnachten.jpg): Motiv Weihnachtsbaum + "BALU Frohe Weihnachten"',
                            'Vorlage Ostern (seelen-pfote-hundemarke-ostern.jpg): Motiv Häschen + "BALU Oster-Spürhund"',
                            'Vorlage Hochzeit (seelen-pfote-hundemarke-hochzeit.jpg): Motiv Eheringe + "BALU Seelenhund"',
                            'Vorlage Geburtstag (seelen-pfote-hundemarke-geburtstag.jpg): Motiv Geschenk + "BALU Ich bin gechipt!"',
                            'Vorlage Valentinstag (seelen-pfote-hundemarke-valentinstag.jpg): Motiv Doppelherzen + "BALU Liebling auf 4 Pfoten"',
                            'Vorlage Halloween / Herbst (seelen-pfote-hundemarke-halloween.jpg): Motiv Mond & Sterne + "BALU Nachtwächter"',
                            'Vorlage Muttertag (seelen-pfote-hundemarke-muttertag.jpg): Motiv Herzkranz + "BALU Frauchens Liebling"',
                            'Vorlage Vatertag (seelen-pfote-hundemarke-vatertag.jpg): Motiv Siegerpokal + "BALU Bester Kumpel"',
                        ],
                    ],
                    [
                        'title' => 'Produkt 6: Das Goldene Seelen-Herz (14k vergoldeter Edelstahl-Herzanhänger 25 x 22 x 4 mm + Gold-Ankerkette) -> Echte Fotos, Video & 8 Anlass-Vorlagen',
                        'priority' => 'hoch',
                        'ai_plan' => "Slug: goldenes-seelen-herz | SKU: MSF-GOLD-HERZ-001 | Maße: 25 x 22 x 4,0 mm + 44-50 cm Gold-Ankerkette (Glücksfieber STA1274 + STA268)\nAblauf: 1x echten 4mm Gold-Herz-Rohling beidseitig gravieren (oder ungraviert fotografieren und die 8 Anlass-Motive von Antigravity exakt auf das echte Foto legen lassen).\nZielpfade:\n- Konfigurator-Overlay: storage/app/public/produkte/products/goldenes-seelen-herz/overlay.png\n- Galerie: storage/app/public/produkte/products/goldenes-seelen-herz/\n- Vorlagen: storage/app/public/produkte/product-templates/goldenes-seelen-herz-{anlass}.jpg",
                        'subtasks' => [
                            '2D-Konfigurator-Basis (overlay.png): Ungravierter 4mm Gold-Herzanhänger mit Collierschlaufe exakt frontal',
                            'Standardbild 1 (Hauptbild): Echtes Gold-Herz Komplett-Set mit 44-50 cm Gold-Ankerkette (goldenes-seelen-herz-main.jpg)',
                            'Standardbild 2 (Rückseite): Rückseiten-Gravur "Für immer im Herzen ♥" + 4mm Seitenprofil (goldenes-seelen-herz-back.jpg)',
                            'Standardbild 3 (Lifestyle / Hover): Komplett-Set getragen am Dekolleté / in den Händen (goldenes-seelen-herz-lifestyle.jpg)',
                            'Standardbild 4 (Verpackung): Gold-Herz Collier drapiert in der schwarzen Westpack-Schmuckbox',
                            'Standardbild 5 (Maßbild): Echtes Produktfoto als Basis für Bemaßung 25 x 22 x 4,0 mm & 44-50 cm Kette (goldenes-seelen-herz-dimensions.jpg)',
                            'Produktvideo (MP4, 5-8 Sek.): Funkeln der 4mm Gold-Facetten, Ankerkette & Gravur im Licht',
                            'Vorlage Weihnachten (goldenes-seelen-herz-weihnachten.jpg): Motiv Weihnachtsbaum + "Frohe Weihnachten! Familie Mustermann"',
                            'Vorlage Ostern (goldenes-seelen-herz-ostern.jpg): Motiv Häschen + "Frohes Osterfest!"',
                            'Vorlage Hochzeit (goldenes-seelen-herz-hochzeit.jpg): Motiv Eheringe + "Lieblingsmensch 24.08.2026"',
                            'Vorlage Geburtstag (goldenes-seelen-herz-geburtstag.jpg): Motiv Geschenk + "Happy Birthday!"',
                            'Vorlage Valentinstag (goldenes-seelen-herz-valentinstag.jpg): Motiv Doppelherzen + "Für immer Dein - 14. Februar -"',
                            'Vorlage Halloween / Herbst (goldenes-seelen-herz-halloween.jpg): Motiv Mond & Sterne + "Magische Nacht Seelenzauber"',
                            'Vorlage Muttertag (goldenes-seelen-herz-muttertag.jpg): Motiv Herzkranz + "Beste Mama der Welt"',
                            'Vorlage Vatertag (goldenes-seelen-herz-vatertag.jpg): Motiv Siegerpokal + "Bester Papa der Welt!"',
                        ],
                    ],
/*                    // AKTUELL AUSGEBLENDET (Fokus zum Start auf Echtsilber & Echtgold ID-Gravurarmbänder):
                    [
                        'title' => 'Produkt (Später): Das Echtgold Seelen-Herz (Atelier: 585er Gold-Herz 13 x 14 mm + 333er Goldkette) -> UNGRAVIERT fotografieren (Gravur digital durch Antigravity)',
                        'priority' => 'hoch',
                        'ai_plan' => "Slug: echtgold-seelen-herz | SKU: MSF-ECHTGOLD-HERZ-001 | Maße: 13 x 14 mm (585er Echtgold) + 0,8 mm Panzerkette (333er Echtgold)\nWICHTIG: Den teuren 585er Echtgold-Rohling NICHT für Musterfotos lasern!\nAblauf: Du fotografierst das echte, ungravierte 585er Gold-Herz samt 333er Goldkette (frontal, in der schwarzen Westpack-Box, getragen und in den Deko-Setups). Anschließend setzt Antigravity die realistische UV-Lasergravur und alle 8 Anlass-Motive pixelgenau auf dein Originalfoto, ohne das echte Produktfoto selbst zu verändern!",
                        'subtasks' => [
                            '2D-Konfigurator-Basis (overlay.png): Ungraviertes 585er Gold-Herz an 333er Kette exakt frontal fotografieren',
                            'Standardbild 1 (Hauptbild): Echtes ungraviertes Foto im schwarzen Westpack-Etui -> Gravur wird digital durch Antigravity aufgesetzt (echtgold-seelen-herz-main.jpg)',
                            'Standardbild 2 (Rückseite): Echtes Foto der Rückseite (inkl. 585-Stempel) -> Rückseiten-Gravur "Für immer im Herzen ♥" digital durch Antigravity (echtgold-seelen-herz-back.jpg)',
                            'Standardbild 3 (Lifestyle / Hover): Echtes Tragefoto an der 333er Echtgold-Panzerkette -> Dezente Gravur digital durch Antigravity (echtgold-seelen-herz-lifestyle.jpg)',
                            'Standardbild 4 (Verpackung): Echtgold-Set in der schwarzen Westpack-Schmuckbox mit Schwarz-Gold-Geschenkpapier',
                            'Standardbild 5 (Maßbild): Echtes Produktfoto als Basis für Bemaßung 13 x 14 mm & 0,8 mm Goldkette (echtgold-seelen-herz-dimensions.jpg)',
                            'Produktvideo (MP4, 5-8 Sek.): Edler Schwenk über das echte ungravierte 585er Gold-Set im Schmucketui',
                            'Vorlage Weihnachten (echtgold-seelen-herz-weihnachten.jpg): Echtes Foto -> Weihnachts-Gravur digital durch Antigravity',
                            'Vorlage Ostern (echtgold-seelen-herz-ostern.jpg): Echtes Foto -> Oster-Gravur digital durch Antigravity',
                            'Vorlage Hochzeit (echtgold-seelen-herz-hochzeit.jpg): Echtes Foto -> "Lieblingsmensch 24.08.2026" digital durch Antigravity',
                            'Vorlage Geburtstag (echtgold-seelen-herz-geburtstag.jpg): Echtes Foto -> "Happy Birthday!" digital durch Antigravity',
                            'Vorlage Valentinstag (echtgold-seelen-herz-valentinstag.jpg): Echtes Foto -> "Für immer Dein" digital durch Antigravity',
                            'Vorlage Halloween / Herbst (echtgold-seelen-herz-halloween.jpg): Echtes Foto -> "Magische Nacht" digital durch Antigravity',
                            'Vorlage Muttertag (echtgold-seelen-herz-muttertag.jpg): Echtes Foto -> "Beste Mama der Welt" digital durch Antigravity',
                            'Vorlage Vatertag (echtgold-seelen-herz-vatertag.jpg): Echtes Foto -> "Bester Papa der Welt!" digital durch Antigravity',
                        ],
                    ],
*/
                    [
                        'title' => 'Produkt 7 (Vorbereitet / FBM): Das Echtsilber Seelen-Armband (Atelier: 925er Sterling Silber ID-Gravurarmband) -> UNGRAVIERT fotografieren (Gravur digital durch Antigravity)',
                        'priority' => 'hoch',
                        'ai_plan' => "Geplanter Slug: echtsilber-seelen-armband | Lieferant: FBM (Friedrich Binder GmbH & Co. KG, Made in Germany)\nWICHTIG: Den echten 925er Silber-Rohling NICHT für Musterfotos lasern!\nAblauf:\n1. Sobald die USt-IdNr. da ist und FBM das Händlerkonto freigeschaltet hat: 1x 925er Sterling Silber ID-Panzerarmband als Foto-Muster bestellen.\n2. Das echte Armband ungraviert fotografieren (frontal von oben für den Konfigurator, in der länglichen Westpack-Armbandbox, am Handgelenk getragen).\n3. Antigravity legt die realistische Lasergravur (Namen, Datum, Koordinaten sowie alle 8 Jahreszeiten-/Anlass-Vorlagen) auf das Gravurschild deines echten Fotos, ohne das Produktfoto selbst zu verändern!",
                        'subtasks' => [
                            'Vorbereitung: Nach FBM-Freischaltung 1x 925er Silber ID-Gravurarmband als Foto-Muster bestellen (NICHT lasern!)',
                            '2D-Konfigurator-Basis (overlay.png): Ungraviertes 925er Silber-Gravurschild mit Panzerkette exakt gerade von oben fotografieren',
                            'Standardbild 1 (Hauptbild): Echtes ungraviertes Foto im schwarzen Westpack-Etui -> Gravur wird digital durch Antigravity aufgesetzt',
                            'Standardbild 2 (Rückseite): Echtes Foto der Schild-Rückseite (inkl. 925 & FBM-Punze) -> Rückseiten-Gravur digital durch Antigravity',
                            'Standardbild 3 (Lifestyle / Hover): Echtes Tragefoto am Handgelenk -> Gravur digital auf dem Silberschild durch Antigravity',
                            'Standardbild 4 (Verpackung): Silber-Armband im schwarzen Westpack-Etui mit Schwarz-Gold-Geschenkpapier',
                            'Standardbild 5 (Maßbild): Echtes Produktfoto mit exakten Schild- & Kettenmaßen in mm',
                            'Produktvideo (MP4, 5-8 Sek.): Schwenk über das echte ungravierte 925er Silber-Armband im Licht',
                            'Vorlage Weihnachten (echtsilber-seelen-armband-weihnachten.jpg): Echtes Foto -> Weihnachts-Gravur digital durch Antigravity',
                            'Vorlage Ostern (echtsilber-seelen-armband-ostern.jpg): Echtes Foto -> Oster-Gravur digital durch Antigravity',
                            'Vorlage Hochzeit (echtsilber-seelen-armband-hochzeit.jpg): Echtes Foto -> "Lieblingsmensch" digital durch Antigravity',
                            'Vorlage Geburtstag (echtsilber-seelen-armband-geburtstag.jpg): Echtes Foto -> "Happy Birthday!" digital durch Antigravity',
                            'Vorlage Valentinstag (echtsilber-seelen-armband-valentinstag.jpg): Echtes Foto -> "Für immer Dein" digital durch Antigravity',
                            'Vorlage Halloween / Herbst (echtsilber-seelen-armband-halloween.jpg): Echtes Foto -> "Magische Nacht" digital durch Antigravity',
                            'Vorlage Muttertag (echtsilber-seelen-armband-muttertag.jpg): Echtes Foto -> "Beste Mama" digital durch Antigravity',
                            'Vorlage Vatertag (echtsilber-seelen-armband-vatertag.jpg): Echtes Foto -> "Bester Papa" digital durch Antigravity',
                        ],
                    ],
                    [
                        'title' => 'Produkt 8 (Vorbereitet / FBM): Das Echtgold Seelen-Armband (Atelier: 333er / 585er Echtgold ID-Gravurarmband) -> UNGRAVIERT fotografieren (Gravur digital durch Antigravity)',
                        'priority' => 'hoch',
                        'ai_plan' => "Geplanter Slug: echtgold-seelen-armband | Lieferant: FBM (Friedrich Binder GmbH & Co. KG, Made in Germany)\nWICHTIG: Den teuren Echtgold-Rohling (>120 € EK) AUF KEINEN FALL für Musterfotos lasern!\nAblauf:\n1. Nach FBM-Freischaltung 1x Echtgold ID-Panzerarmband bestellen und unversehrt lassen.\n2. Das echte Goldarmband ungraviert fotografieren (frontal von oben für den Konfigurator, im schwarzen Westpack-Etui, am Handgelenk getragen).\n3. Antigravity setzt den Gravurtext und alle 8 Anlass-Vorlagen realistisch direkt auf das Goldschild deines echten Fotos, ohne das Produktfoto selbst zu verändern. So bleibt der Gold-Rohling zu 100 % unversehrt für den ersten echten Kundenverkauf!",
                        'subtasks' => [
                            'Vorbereitung: Nach FBM-Freischaltung 1x Echtgold ID-Gravurarmband als Foto-Muster bestellen (NICHT lasern!)',
                            '2D-Konfigurator-Basis (overlay.png): Ungraviertes Echtgold-Gravurschild mit Panzerkette exakt gerade von oben fotografieren',
                            'Standardbild 1 (Hauptbild): Echtes ungraviertes Foto im schwarzen Westpack-Etui -> Gravur wird digital durch Antigravity aufgesetzt',
                            'Standardbild 2 (Rückseite): Echtes Foto der Schild-Rückseite (inkl. 333/585 & FBM-Stempel) -> Rückseiten-Gravur digital durch Antigravity',
                            'Standardbild 3 (Lifestyle / Hover): Echtes Tragefoto am Handgelenk -> Gravur digital auf dem Goldschild durch Antigravity',
                            'Standardbild 4 (Verpackung): Echtgold-Armband im schwarzen Westpack-Etui mit Geschenkverpackung',
                            'Standardbild 5 (Maßbild): Echtes Produktfoto mit exakten Schild- & Kettenmaßen in mm',
                            'Produktvideo (MP4, 5-8 Sek.): Edler Schwenk über das echte ungravierte Echtgold-Armband im Schmucketui',
                            'Vorlage Weihnachten (echtgold-seelen-armband-weihnachten.jpg): Echtes Foto -> Weihnachts-Gravur digital durch Antigravity',
                            'Vorlage Ostern (echtgold-seelen-armband-ostern.jpg): Echtes Foto -> Oster-Gravur digital durch Antigravity',
                            'Vorlage Hochzeit (echtgold-seelen-armband-hochzeit.jpg): Echtes Foto -> "Lieblingsmensch" digital durch Antigravity',
                            'Vorlage Geburtstag (echtgold-seelen-armband-geburtstag.jpg): Echtes Foto -> "Happy Birthday!" digital durch Antigravity',
                            'Vorlage Valentinstag (echtgold-seelen-armband-valentinstag.jpg): Echtes Foto -> "Für immer Dein" digital durch Antigravity',
                            'Vorlage Halloween / Herbst (echtgold-seelen-armband-halloween.jpg): Echtes Foto -> "Magische Nacht" digital durch Antigravity',
                            'Vorlage Muttertag (echtgold-seelen-armband-muttertag.jpg): Echtes Foto -> "Beste Mama" digital durch Antigravity',
                            'Vorlage Vatertag (echtgold-seelen-armband-vatertag.jpg): Echtes Foto -> "Bester Papa" digital durch Antigravity',
                        ],
                    ],
                    [
                        'title' => 'Produkt 9: Geschenkgutschein (Lasergravierte Gold-Metallkarte im schwarzen Edel-Umschlag mit Wachssiegel) -> Echte Fotos & 4 Saison-Editions',
                        'priority' => 'mittel',
                        'ai_plan' => "Slug: geschenkgutschein | SKU: MSF-GUTSCHEIN-001\nAblauf: 1x goldene Metall-Gutscheinkarte als Muster lasern und zusammen mit dem schwarzen Umschlag und goldenem Wachssiegel fotografieren.\nZielpfade: storage/app/public/produkte/products/geschenkgutschein/",
                        'subtasks' => [
                            '2D-Vorschau-Basis (envelope.png): Schwarzer Edel-Umschlag mit goldenem Wachssiegel & Gold-Metallkarte frontal',
                            'Standardbild 1 (Hauptbild Standard): Echte gelaserte Gold-Metallkarte mit Umschlag & Wachssiegel (gutschein-main.jpg)',
                            'Standardbild 2 (Lifestyle / Hover): Persönliche Übergabe des Gutschein-Umschlags in den Händen (gutschein-lifestyle.jpg)',
                            'Saison-Edition Weihnachten (gutschein-weihnachten.jpg): Echtes Foto des Gutschein-Sets im Weihnachts-Setup',
                            'Saison-Edition Valentinstag (gutschein-valentinstag.jpg): Echtes Foto des Gutschein-Sets im Romantik-Setup',
                            'Saison-Edition Ostern (gutschein-ostern.jpg): Echtes Foto des Gutschein-Sets im Frühlings-Setup',
                            'Saison-Edition Halloween / Herbst (gutschein-halloween.jpg): Echtes Foto des Gutschein-Sets im Herbst-/Kerzenlicht-Setup',
                        ],
                    ],
                    [
                        'title' => 'Produktübergreifend: Aufpreis-Extras, Verpackungs-Upgrades & Bundle-Optionen (Echte Fotos von Geschenkbox & Geschenkpapier)',
                        'priority' => 'hoch',
                        'ai_plan' => 'In der Produktverwaltung unter "Aufpreis-Extras, Verpackungs-Upgrades & Bundle-Optionen" (sowie im ProductSeeder) das bisherige KI-/Platzhalterbild der Geschenkbox durch ein echtes Produktfoto der schwarzen Westpack-Schmuckbox ersetzen und das wendbare Westpack-Geschenkpapier (Nr. 0751) ebenfalls mit einem echten Foto (keine KI-Generierung) hinterlegen.',
                        'subtasks' => [
                            'Addon-Foto 1: Schwarze Westpack-Schmuckbox (+4,90 €) geöffnet & geschlossen mit goldenem Logo-Finish fotografieren',
                            'Addon-Foto 2: Wendbares Schwarz-Gold-Geschenkpapier (Westpack Nr. 0751) fertig verpackt fotografieren',
                            'Addon-Foto 3: Komplett-Geschenkset (Westpack-Box + Geschenkpapier) als Bundle-Vorschau fotografieren',
                            'Echte Addon-Fotos im ProductSeeder & Konfigurator hinterlegen',
                        ],
                    ],
                    ['title' => 'Landing Pages umfangreich und fett mit den echten Produktfotos aufbauen', 'priority' => 'hoch'],
                ],
            ],
            [
                'list' => [
                    'name' => 'PHASE 2 - MARKETING',
                    'icon' => 'speaker-wave',
                    'color' => '#D69E2E',
                ],
                'taks' => [
                    ['title' => 'Zum Start des neuen Instagram Accounts das Handy in eine Schleife tun und aufmachen. Filmen und Instagram Seite auf den Handy offen haben (zweit Handy nutzen)', 'priority' => 'mittel'],
                    ['title' => 'ETSY nur als Booster nutzen und auf Produkt Konfigurator weiterleiten', 'priority' => 'hoch'],
                    ['title' => 'QR Code, Insta, tiktok mit ins Paket legen', 'priority' => 'mittel'],
                ],
            ],
            [
                'list' => [
                    'name' => 'PHASE 3 - MAIN ABLAUF',
                    'icon' => 'flag',
                    'color' => '#3182CE',
                ],
                'taks' => [
                    [
                        'title' => 'Fragebogen zur steuerlichen Erfassung (ELSTER) ausfüllen -> Betriebliche Steuernummer & USt-IdNr. beantragen (https://www.elster.de)',
                        'priority' => 'hoch',
                        'is_completed' => true,
                        'ai_plan' => "ERLEDIGT & ÜBERMITTELT am Freitag, 06.11.2026 um 15:35:38 Uhr an das Finanzamt Gifhorn (Niedersachsen).\n- ELSTER-Transferticket: ep3104hvg8fm3b1m4zrwrd3djc6ieugc\n- ELSTER-Referenz: 2169193168 -B41\n- Auftrag: Fragebogen zur steuerlichen Erfassung für Einzelunternehmen, Steinhauer Alina (Mein-Seelenfunke, Tätigkeitsbeginn 01.11.2026)\n- Beantragt: Neue betriebliche Steuernummer + Umsatzsteuer-Identifikationsnummer (USt-IdNr. beim BZSt) + Regelbesteuerung (§ 19 Abs. 3 UStG mit Vorsteuerabzug) + Istversteuerung (§ 20 UStG)\n- Hinterlegte Bankverbindung: FYRST / Deutsche Bank AG (IBAN DE76 2507 0370 0079 8629 00, BIC DEUTDE2HP24)",
                        'file_paths' => [
                            'leitung/tasks/attachments/2026-11-06_Finanzamt-Gifhorn_ELSTER-Steuerliche-Erfassung-Versandbestaetigung.pdf',
                        ],
                    ],
                    [
                        'title' => 'Sobald USt-IdNr. (DE...) vorliegt: Westpack Geschenkpapier Nr. 0751 - Wendbar (1x 30 cm × 100 m, Art.-Nr. 03307510300, 67,90 € netto) per Reverse-Charge bestellen -> https://www.westpack.com/eur_eu_ger/wrapping-paper-n-0751-reversible-03307510200.html',
                        'priority' => 'hoch',
                        'ai_plan' => 'WICHTIG: Erst bestellen, sobald die deutsche USt-IdNr. vorliegt! Westpack sitzt in Dänemark und berechnet ohne USt-IdNr. 25 % dänische MwSt. (16,98 €), die das deutsche Finanzamt NICHT über die reguläre Umsatzsteuervoranmeldung erstattet. Mit gültiger USt-IdNr. greift Reverse-Charge (0,00 € MwSt., nur 67,90 € netto). Die 30 cm Rolle deckt im 50 cm Untertischabroller sowohl kleine Schmuckboxen (quer abreißen) als auch große Kristall-Boxen ab.',
                    ],
                    [
                        'title' => 'Sobald USt-IdNr. (DE...) vorliegt: Bei Westpack (sales@westpack.com) für Order #29000069521 (96x schwarze Schmuckboxen, 145,95 € brutto) nachträgliche Reverse-Charge-Rechnungskorrektur & Erstattung der 29,19 € dänischen MwSt. anfragen (Fallback: 145,95 € brutto nach § 9b EStG voll in EÜR absetzen)',
                        'priority' => 'hoch',
                        'ai_plan' => '1. E-Mail an sales@westpack.com senden mit Angabe von Order #29000069521 (Order-ID: 485928 vom 02.11.2026) und der neuen deutschen USt-IdNr. (DE...): Höflich um nachträgliche Umschreibung auf innergemeinschaftliche Lieferung (Reverse-Charge, 0 % MwSt.) und Rückerstattung der gezahlten 29,19 € dänischen MwSt. (25 %) bitten. 2. Falls Westpack rückwirkend nicht korrigiert: Den Beleg in der Buchhaltung auf 0 % Vorsteuerabzug stellen, damit die vollen 145,95 € brutto nach § 9b Abs. 1 EStG zu 100 % als gewinnmindernde Betriebsausgabe in die EÜR einfließen (da ausländische EU-MwSt. unter 50 € Jahresgrenze nicht über die deutsche UStVA abziehbar ist).',
                    ],
                    [
                        'title' => 'Sobald USt-IdNr. (DE...) vorliegt: B2B-Großhandelsanmeldung & KYC-Bogen bei FBM (Friedrich Binder GmbH & Co. KG, info@fbm.de) einreichen',
                        'priority' => 'hoch',
                        'ai_plan' => "B2B-Hersteller für echte 925 Silber & 333/585 Gold ID-Gravurarmbänder („Made in Germany\" seit 1910, Mönsheim).\n1. Angehängtes FBM-Dokument (KYC-Bogen FB-ALL-06 & Verhaltensregeln) mit der neuen USt-IdNr. (DE...) und den FYRST-Bankdaten vollständig ausfüllen und unterschreiben.\n2. Zusammen mit der amtlichen Gewerbeanmeldung der Stadt Gifhorn (GewA 1 vom 28.10.2026, Aktenzeichen gew/28-10-2026/Ce/402) per E-Mail an info@fbm.de senden, um das B2B-Händlerkonto unter https://www.fbm-chains.com/ freischalten zu lassen.\n\nAusfüllhilfe für den KYC-Bogen:\n- Firmenname: Mein-Seelenfunke\n- Inhaberin / wirtschaftlich Berechtigte: Alina Steinhauer\n- Anschrift: Carl-Goerdeler-Ring 26, 38518 Gifhorn, Niedersachsen, Deutschland\n- Kontakt: +49 1590 1966864 | kontakt@mein-seelenfunke.de | https://mein-seelenfunke.de\n- Bankverbindung (FYRST / Deutsche Bank AG): IBAN DE76 2507 0370 0079 8629 00 | BIC DEUTDE2HP24\n- Haupttätigkeitsfeld: Online-Einzelhandel & Laser-Manufaktur (Veredelung und Personalisierung von Schmuck- und Geschenkartikeln mittels Lasergravur)\n- Regierung/Militär/Partei, RJC-Zertifizierung, eigene Lieferkettenrichtlinie, eigenes KYC-Verfahren: jeweils „Nein\" ankreuzen.",
                        'file_paths' => [
                            'leitung/tasks/attachments/2025-05-27_FBM_Friedrich-Binder_KYC-und-Verhaltensregeln.pdf',
                        ],
                    ],
                    ['title' => 'Arbeitslosenversicherung abschließen - https://www.arbeitsagentur.de/freiwillige-arbeitslosenversicherung', 'priority' => 'mittel'],
                    ['title' => 'Verpackungslizenz erwerben -> https://lucid.verpackungsregister.org/Hersteller/Registrierung/Teil-1 https://www.lizenzero.de/verpackungsmengen-kalkulator/', 'priority' => 'mittel'],
                    ['title' => 'Google Bewertungen aktivieren', 'priority' => 'hoch'],
                ],
            ],
            [
                'list' => [
                    'name' => 'PHASE 4 - LIVEGANG',
                    'icon' => 'sparkles',
                    'color' => '#805AD5',
                ],
                'taks' => [
                    ['title' => 'testdata Ordner in Storage ziehen', 'priority' => 'hoch'],
                    ['title' => 'blog Ordner in Storage ziehen', 'priority' => 'hoch'],
                    ['title' => 'env Datei anpassen', 'priority' => 'hoch'],
                    ['title' => 'Web Socket Einrichten (Env Files beachten Pusher Variablen, REVERB und BROADCAST_CONNECTION)', 'priority' => 'hoch'],
                    ['title' => 'DB Seeder die nicht gebraucht werden deaktivieren', 'priority' => 'mittel'],
                    ['title' => 'DHL API Keys auf Live anpassen', 'priority' => 'hoch'],
                    ['title' => 'GOOGLE_PLACES_API_KEY in der Google Cloud Console auf den Live Server anpassen (Domain-Einschränkungen einrichten)', 'priority' => 'hoch'],
                    ['title' => 'Google Bewertungen implementieren - TEMPORÄRER TEST-MODUS', 'priority' => 'mittel'],
                    ['title' => 'Die Archivrechnungen entfernen für einen cleanen start', 'priority' => 'hoch'],
                ],
            ],
            [
                'list' => [
                    'name' => 'Kanzlei & Steuern',
                    'icon' => 'calculator',
                    'color' => '#C5A059',
                ],
                'taks' => [
                    [
                        'title' => 'Fragebogen zur steuerlichen Erfassung (ELSTER) ausfüllen -> Betriebliche Steuernummer & USt-IdNr. beantragen',
                        'priority' => 'hoch',
                        'ai_plan' => "Gewerbeanmeldung bei Stadt Gifhorn liegt vor.\nFinanzamt Gifhorn kontaktiert uns bzw. Fragebogen zur steuerlichen Erfassung via ELSTER online einreichen.\nBeantragung: Betriebliche Steuernummer & USt-IdNr.",
                    ],
                    [
                        'title' => 'Eröffnungsbilanz & Stammdaten mit Steuerberater abstimmen',
                        'priority' => 'mittel',
                        'ai_plan' => "Kanzlei-Direktkanal:\nHier können Rückfragen zu Belegen, Ausgaben und Monatsabschlüssen direkt mit der Kanzlei geklärt werden.",
                    ],
                ],
            ],
        ];

        DB::table('management_tasks')->delete();
        DB::table('management_task_lists')->delete();

        $baseNow = now();
        $phaseCount = 0;

        $priorityMap = [
            'high' => 'hoch',
            'medium' => 'mittel',
            'low' => 'niedrig',
            'hoch' => 'hoch',
            'mittel' => 'mittel',
            'niedrig' => 'niedrig',
        ];

        foreach ($phases as $phase) {
            $phaseTime = $baseNow->copy()->addMinutes($phaseCount++);
            $listId = Str::uuid()->toString();

            DB::table('management_task_lists')->insert([
                'id' => $listId,
                'name' => $phase['list']['name'],
                'icon' => $phase['list']['icon'],
                'color' => $phase['list']['color'],
                'created_at' => $phaseTime,
                'updated_at' => $phaseTime,
            ]);

            $taksToInsert = [];
            $position = 0;

            foreach ($phase['taks'] as $todo) {
                $taskTime = $phaseTime->copy()->subSeconds($position++);
                $parentId = Str::uuid()->toString();
                $rawPrio = $todo['priority'] ?? 'niedrig';
                $mappedPrio = $priorityMap[$rawPrio] ?? 'niedrig';

                $taksToInsert[] = [
                    'id' => $parentId,
                    'task_list_id' => $listId,
                    'parent_id' => null,
                    'title' => $todo['title'],
                    'ai_plan' => $todo['ai_plan'] ?? null,
                    'file_paths' => isset($todo['file_paths']) ? json_encode($todo['file_paths']) : null,
                    'is_completed' => $todo['is_completed'] ?? false,
                    'position' => $position,
                    'priority' => $mappedPrio,
                    'relevant_from' => '2026-08-01 00:00:00',
                    'created_at' => $taskTime,
                    'updated_at' => $taskTime,
                ];

                if (!empty($todo['subtasks']) && is_array($todo['subtasks'])) {
                    $subPosition = 0;
                    foreach ($todo['subtasks'] as $sub) {
                        $subPosition++;
                        $subTitle = is_array($sub) ? $sub['title'] : $sub;
                        $subCompleted = is_array($sub) ? ($sub['is_completed'] ?? false) : false;

                        $taksToInsert[] = [
                            'id' => Str::uuid()->toString(),
                            'task_list_id' => $listId,
                            'parent_id' => $parentId,
                            'title' => $subTitle,
                            'ai_plan' => null,
                            'file_paths' => null,
                            'is_completed' => $subCompleted,
                            'position' => $subPosition,
                            'priority' => 'niedrig',
                            'relevant_from' => null,
                            'created_at' => $taskTime,
                            'updated_at' => $taskTime,
                        ];
                    }
                }
            }

            if (!empty($taksToInsert)) {
                DB::table('management_tasks')->insert($taksToInsert);
            }
        }
    }
}

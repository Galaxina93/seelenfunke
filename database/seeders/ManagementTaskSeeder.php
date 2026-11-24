<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManagementTaskSeeder extends Seeder
{
    /**
     * Zentraler, streng chronologisch & kausal sortierter Master-Fahrplan (01 bis 23) bis zum Shop-Livegang.
     *
     * Aktueller Bereinigungsstand:
     * - Freiwillige Arbeitslosenversicherung (§ 28a SGB III): erledigt/beantragt -> entfernt.
     * - Anlass-Vorlagen (ProductTemplateSeeder): alle erstellt & eingepflegt -> aus allen Produkt-Checklisten entfernt.
     * - Aufpreis-Extras / Verpackungs-Upgrades (Geschenkbox & Geschenkpapier Echtfotos): erledigt -> entfernt.
     * - Testbestellungen & Archivrechnungen (Start ab RE-2026-1000): erledigt & in system:prepare-live automatisiert.
     *
     * Reihenfolge-Logik (keine blockierte Aufgabe steht vor ihrer Voraussetzung):
     * - 01 bis 08 (Prio 'hoch'    = SOFORT MACHBAR): Finanzamt nachhaken + Videos (P1, P2, P5) + Echtfotos vorhandener Rohlinge (Flachbett vor RA3).
     * - 09 bis 18 (Prio 'mittel'  = SOBALD USt-IdNr. DA IST & SHOP-FINALISIERUNG): FBM -> LUCID -> Westpack-Korrektur -> Steuerberater -> FBM-Armbänder -> Landingpages -> Google Ads -> Beileger -> Westpack-Großrolle (Nachschub).
     * - 19 bis 23 (Prio 'niedrig' = FINALER LIVEGANG-CUTOVER & LAUNCH): DHL Live -> Stripe Live + FYRST -> Vertex AI Live -> Instagram Start-Reel -> UGC-Creatorinnen.
     */
    public function run(): void
    {
        $phases = [
            [
                'list' => [
                    'name' => 'Fahrplan bis Livegang',
                    'icon' => 'flag',
                    'color' => '#C5A059',
                    'position' => 1,
                ],
                'taks' => [
                    // =========================================================================
                    // STUFE 1 (01 - 08 | Prio: hoch): AB SOFORT OHNE BLOCKER AUSFÜHRBAR
                    // =========================================================================
                    [
                        'title' => '01. Finanzamt Gifhorn: Neue betriebliche Steuernummer & USt-IdNr. nachverfolgen (Sachstandsanfrage vom 17.11.2026)',
                        'priority' => 'hoch',
                        'ai_plan' => "Kritischer Pfad (schaltet FBM, LUCID, Westpack Reverse-Charge, Steuerberater & Stripe Live frei)!\nAm 17.11.2026 wurde per E-Mail an poststelle@fa-gf.niedersachsen.de eine Sachstandsanfrage mit ELSTER-Versandbestätigung (Transferticket: ep3104hvg8fm3b1m4zrwrd3djc6ieugc) gesendet.\n\nFallback (falls das Finanzamt aus Datenschutzgründen per E-Mail nur den Eingang bestätigt und die Steuernummer nicht unverschlüsselt nennt):\n1. Kurzer Anruf bei der Neugründungsstelle des Finanzamts Gifhorn unter Tel. 05371 800-0 (mit Steuer-ID 66324780911 und Transferticket ep3104hvg8fm3b1m4zrwrd3djc6ieugc).\n2. Oder elektronische Rückfrage direkt über Mein ELSTER ('Sonstige Nachricht an das Finanzamt') unter Angabe von Transferticket ep3104hvg8fm3b1m4zrwrd3djc6ieugc und ELSTER-Referenz 2169193168 -B41.",
                    ],
                    [
                        'title' => '02. Produkt 1 (Video offen): Der Seelen Kristall (K9-Kristallglas 180 x 200 x 40 mm) -> Produktvideo (MP4, 5-8 Sek.)',
                        'priority' => 'hoch',
                        'ai_plan' => "Bilderstellung, Anlass-Vorlagen & Maßbild für Der Seelen Kristall (seelen-kristall) sind komplett im Portal hinterlegt.\nOffen ist nur noch das Produktvideo (MP4, 5-8 Sek.): Schwenk um den echten gelaserten Kristall im Licht (für Shop & Reels).",
                    ],
                    [
                        'title' => '03. Produkt 2 (Video offen): Der Seelenanhänger (Alu-Herz schwarz eloxiert 45 x 40 x 2,3 mm) -> Produktvideo (MP4, 5-8 Sek.)',
                        'priority' => 'hoch',
                        'ai_plan' => "Bilderstellung, Anlass-Vorlagen & Maßbild für Der Seelenanhänger (seelen-anhaenger) sind komplett im Portal hinterlegt.\nOffen ist nur noch das Produktvideo (MP4, 5-8 Sek.): Schwenk über Vorder- und Rückseiten-Gravur im Licht.",
                    ],
                    [
                        'title' => '04. Produkt 5 (Video offen): Die Seelen-Pfote (Edelstahl-Hundemarke Knochenform 40 x 22 x 1,8 mm) -> Produktvideo (MP4, 5-8 Sek.)',
                        'priority' => 'hoch',
                        'ai_plan' => "Bilderstellung, Anlass-Vorlagen & Maßbild für Die Seelen-Pfote (seelen-pfote-hundemarke) sind komplett im Portal hinterlegt.\nOffen ist nur noch das Produktvideo (MP4, 5-8 Sek.): Schwenk über Vorderseite, Pfoten-Durchbruch & Rückseiten-Notfallnummer.",
                    ],
                    [
                        'title' => '05. Produkt 4: Der Seelen-Talisman (Gold-Edelstahl-Schlüsselanhänger 50 x 28 x 2,2 mm) -> Echte Produktfotos & Video',
                        'priority' => 'hoch',
                        'ai_plan' => "Slug: seelen-talisman | SKU: MSF-EDELSTAHL-TALISMAN-001 | Maße: 50 x 28 x 2,2 mm (Ø 30 mm Flachring, beidseitig gravierbar)\nAnlass-Vorlagen sind bereits erstellt und eingepflegt.\nAblauf (Flachbett-Setup): 1x echtes Musterstück beidseitig gravieren (Vorderseite Wunschmotiv, Rückseite Koordinaten 52°31'12\"N 13°24'18\"E), echte Galeriebilder aufnehmen und kurzes Video drehen.\nZielpfade:\n- Konfigurator-Overlay: storage/app/public/produkte/products/seelen-talisman/overlay.png\n- Galerie: storage/app/public/produkte/products/seelen-talisman/",
                        'subtasks' => [
                            '2D-Konfigurator-Basis (overlay.png): Ungravierter Gold-Edelstahl-Schlüsselanhänger exakt frontal von oben',
                            'Standardbild 1 (Hauptbild): Echter gelaserter Gold-Talisman Vorderseite (seelen-talisman-main.jpg)',
                            'Standardbild 2 (Rückseite): Rückseite mit Koordinaten-Gravur "52°31\'12\"N 13°24\'18\"E" (seelen-talisman-back.jpg)',
                            'Standardbild 3 (Lifestyle / Hover): Gold-Talisman am echten Haustür-/Autoschlüsselbund (seelen-talisman-lifestyle.jpg)',
                            'Standardbild 4 (Verpackung): Gold-Talisman in der schwarzen Westpack-Schmuckbox',
                            'Standardbild 5 (Maßbild): Echtes Produktfoto als Basis für Bemaßung 50 x 28 x 2,2 mm & Ø 30 mm Ring (seelen-talisman-dimensions.jpg)',
                            'Produktvideo (MP4, 5-8 Sek.): Spiegelglanz des Gold-Finishs & tiefschwarze Lasergravur im Licht',
                        ],
                    ],
                    [
                        'title' => '06. Produkt 6: Das Goldene Seelen-Herz (14k vergoldeter Edelstahl-Herzanhänger 25 x 22 x 4 mm + Gold-Ankerkette) -> Echte Produktfotos & Video',
                        'priority' => 'hoch',
                        'ai_plan' => "Slug: goldenes-seelen-herz | SKU: MSF-GOLD-HERZ-001 | Maße: 25 x 22 x 4,0 mm + 44-50 cm Gold-Ankerkette (Glücksfieber STA1274 + STA268)\nAnlass-Vorlagen sind bereits erstellt und eingepflegt.\nAblauf (Flachbett-Setup): 1x echten 4mm Gold-Herz-Rohling beidseitig gravieren (oder ungraviert fotografieren und Gravur digital von Antigravity aufsetzen lassen) + kurzes Video drehen.\nZielpfade:\n- Konfigurator-Overlay: storage/app/public/produkte/products/goldenes-seelen-herz/overlay.png\n- Galerie: storage/app/public/produkte/products/goldenes-seelen-herz/",
                        'subtasks' => [
                            '2D-Konfigurator-Basis (overlay.png): Ungravierter 4mm Gold-Herzanhänger mit Collierschlaufe exakt frontal',
                            'Standardbild 1 (Hauptbild): Echtes Gold-Herz Komplett-Set mit 44-50 cm Gold-Ankerkette (goldenes-seelen-herz-main.jpg)',
                            'Standardbild 2 (Rückseite): Rückseiten-Gravur "Für immer im Herzen ♥" + 4mm Seitenprofil (goldenes-seelen-herz-back.jpg)',
                            'Standardbild 3 (Lifestyle / Hover): Komplett-Set getragen am Dekolleté / in den Händen (goldenes-seelen-herz-lifestyle.jpg)',
                            'Standardbild 4 (Verpackung): Gold-Herz Collier drapiert in der schwarzen Westpack-Schmuckbox',
                            'Standardbild 5 (Maßbild): Echtes Produktfoto als Basis für Bemaßung 25 x 22 x 4,0 mm & 44-50 cm Kette (goldenes-seelen-herz-dimensions.jpg)',
                            'Produktvideo (MP4, 5-8 Sek.): Funkeln der 4mm Gold-Facetten, Ankerkette & Gravur im Licht',
                        ],
                    ],
                    [
                        'title' => '07. Produkt 9: Geschenkgutschein (Lasergravierte Aluminiumkarte im schwarzen Edel-Umschlag mit Wachssiegel) -> Echte Produktfotos',
                        'priority' => 'hoch',
                        'ai_plan' => "Slug: geschenkgutschein | SKU: MSF-GUTSCHEIN-001\nSaison-Vorlagen sind bereits eingepflegt.\nAblauf (Flachbett-Setup): 1x Aluminium-Gutscheinkarte als Muster lasern und zusammen mit dem schwarzen Umschlag und goldenem Wachssiegel echt fotografieren.\nZielpfade: storage/app/public/produkte/products/geschenkgutschein/",
                        'subtasks' => [
                            '2D-Vorschau-Basis (envelope.png): Schwarzer Edel-Umschlag mit goldenem Wachssiegel & Aluminiumkarte frontal',
                            'Standardbild 1 (Hauptbild Standard): Echte gelaserte Aluminiumkarte mit Umschlag & Wachssiegel (gutschein-main.jpg)',
                            'Standardbild 2 (Lifestyle / Hover): Persönliche Übergabe des Gutschein-Umschlags in den Händen (gutschein-lifestyle.jpg)',
                        ],
                    ],
                    [
                        'title' => '08. Produkt 3: Der Feierabend-Funke (Weizenbierglas 0,5l mit 360°-Gravur, Ø 74 x 218 mm) -> Echte Produktfotos & Video',
                        'priority' => 'hoch',
                        'ai_plan' => "Slug: weizenglas-personalisiert | SKU: MSF-GLAS-WEIZEN-001 | Maße: Ø 74 x 218 mm (360° Rotationsgravur mit RA3)\nAnlass-Vorlagen sind bereits erstellt und eingepflegt.\nAblauf (nach Abschluss der Flachbett-Muster 05-07 einmalig auf RA3 umrüsten): 1x echtes Weizenbierglas auf dem RA3 Rotationsmodul gravieren und sowohl leer (vor dunklem/neutralem Kontrast) als auch frisch eingeschenkt fotografieren + Video drehen.\nZielpfade:\n- Konfigurator-Overlay: storage/app/public/produkte/products/weizenglas-personalisiert/overlay.png\n- Galerie: storage/app/public/produkte/products/weizenglas-personalisiert/",
                        'subtasks' => [
                            '2D-Konfigurator-Basis (overlay.png): Ungraviertes Weizenglas exakt gerade frontal vor neutralem Hintergrund',
                            'Standardbild 1 (Hauptbild): Echtes graviertes Weizenglas scharf im Fokus (beer_glas_main.jpg)',
                            'Standardbild 2 (Detail): Makro-Aufnahme der mattierten UV-Lasergravur auf dem Kristallglas',
                            'Standardbild 3 (Lifestyle / Hover): Frisch eingeschenktes Weizenglas mit Schaumkrone im Feierabend-Ambiente (weizenglas-lifestyle.jpg)',
                            'Standardbild 4 (Verpackung): Weizenglas mit Schwarz-Gold-Geschenkpapier / Versandkarton',
                            'Standardbild 5 (Maßbild): Echtes Produktfoto als Basis für Bemaßung 218 x Ø 74 mm (weizenglas-dimensions.jpg)',
                            'Produktvideo (MP4, 5-8 Sek.): 360°-Drehung des echten gravierten Weizenglases im Licht',
                        ],
                    ],

                    // =========================================================================
                    // STUFE 2 (09 - 18 | Prio: mittel): SOBALD STEUERNUMMER & USt-IdNr. DA SIND
                    // =========================================================================
                    [
                        'title' => '09. Sobald USt-IdNr. (DE...) vorliegt: B2B-Großhandelsanmeldung & KYC-Bogen bei FBM (Friedrich Binder GmbH & Co. KG, info@fbm.de) einreichen',
                        'priority' => 'mittel',
                        'ai_plan' => "B2B-Hersteller für echte 925 Silber & Echtgold ID-Gravurarmbänder (\"Made in Germany\" seit 1910, Mönsheim).\nDirekt als Erstes nach Erhalt der USt-IdNr. absenden, damit FBM das Händlerkonto für die Musterbestellung (Schritte 13 & 14) freischaltet!\n1. Angehängtes FBM-Dokument (KYC-Bogen FB-ALL-06 & Verhaltensregeln) mit der neuen USt-IdNr. (DE...) und den FYRST-Bankdaten vollständig ausfüllen und unterschreiben.\n2. Zusammen mit der amtlichen Gewerbeanmeldung der Stadt Gifhorn (GewA 1 vom 28.10.2026, Aktenzeichen gew/28-10-2026/Ce/402) per E-Mail an info@fbm.de senden, um das B2B-Händlerkonto unter https://www.fbm-chains.com/ freischalten zu lassen.\n\nAusfüllhilfe für den KYC-Bogen:\n- Firmenname: Mein-Seelenfunke\n- Inhaberin / wirtschaftlich Berechtigte: Alina Steinhauer\n- Anschrift: Carl-Goerdeler-Ring 26, 38518 Gifhorn, Niedersachsen, Deutschland\n- Kontakt: +49 1590 1966864 | kontakt@mein-seelenfunke.de | https://mein-seelenfunke.de\n- Bankverbindung (FYRST / Deutsche Bank AG): IBAN DE76 2507 0370 0079 8629 00 | BIC DEUTDE2HP24\n- Haupttätigkeitsfeld: Online-Einzelhandel & Laser-Manufaktur (Veredelung und Personalisierung von Schmuck- und Geschenkartikeln mittels Lasergravur)\n- Regierung/Militär/Partei, RJC-Zertifizierung, eigene Lieferkettenrichtlinie, eigenes KYC-Verfahren: jeweils \"Nein\" ankreuzen.",
                        'file_paths' => [
                            'leitung/tasks/attachments/2025-05-27_FBM_Friedrich-Binder_KYC-und-Verhaltensregeln.pdf',
                        ],
                    ],
                    [
                        'title' => '10. Verpackungslizenz & LUCID-Registrierung vor erstem Versand abschließen -> https://lucid.verpackungsregister.org/Hersteller/Registrierung/Teil-1 & https://www.lizenzero.de/verpackungsmengen-kalkulator/',
                        'priority' => 'mittel',
                        'ai_plan' => 'Gesetzliche Pflicht nach VerpackG (Abmahnschutz vor dem ersten Paketversand): Sobald Steuernummer / USt-IdNr. vorliegen, kostenfreie Hersteller-Registrierung im Verpackungsregister LUCID durchführen und die Jahres-Startmenge bei Lizenzero lizenzieren (LUCID-Registrierungsnummer in beiden Portalen hinterlegen und Vertragsnummer in AccountingSeeder ergänzen).',
                    ],
                    [
                        'title' => '11. Sobald USt-IdNr. (DE...) vorliegt: Bei Westpack (sales@westpack.com) für Order #29000069521 (96x schwarze Schmuckboxen, 145,95 € brutto) nachträgliche Reverse-Charge-Rechnungskorrektur & Erstattung der 29,19 € dänischen MwSt. anfragen (Fallback: 145,95 € brutto nach § 9b EStG voll in EÜR absetzen)',
                        'priority' => 'mittel',
                        'ai_plan' => 'Vor der Steuerberater-Abstimmung (Schritt 12) klären:\n1. E-Mail an sales@westpack.com senden mit Angabe von Order #29000069521 (Order-ID: 485928 vom 02.11.2026) und der neuen deutschen USt-IdNr. (DE...): Höflich um nachträgliche Umschreibung auf innergemeinschaftliche Lieferung (Reverse-Charge, 0 % MwSt.) und Rückerstattung der gezahlten 29,19 € dänischen MwSt. (25 %) bitten.\n2. Falls Westpack rückwirkend nicht korrigiert: Beleg bleibt auf 0 % Vorsteuerabzug, sodass die vollen 145,95 € brutto nach § 9b Abs. 1 EStG zu 100 % als gewinnmindernde Betriebsausgabe in die EÜR einfließen.',
                    ],
                    [
                        'title' => '12. Eröffnungsbilanz & Stammdaten (Steuernummer, USt-IdNr. & Gründungsbelege) mit Steuerberater abstimmen',
                        'priority' => 'mittel',
                        'ai_plan' => "Sobald Steuernummer, USt-IdNr. und die Westpack-Klärung aus Schritt 11 vorliegen:\nStammdaten, Vorgründungs-/Anfangsbelege aus AccountingSeeder sowie den Kanzlei-Export-Zugang mit dem Steuerberater abstimmen.",
                    ],
/*                    // AKTUELL AUSGEBLENDET (Fokus zum Start auf Echtsilber & Echtgold ID-Gravurarmbänder):
                    [
                        'title' => 'Produkt (Später): Das Echtgold Seelen-Herz (Atelier: 585er Gold-Herz 13 x 14 mm + 333er Goldkette) -> UNGRAVIERT fotografieren (Gravur digital durch Antigravity)',
                        'priority' => 'mittel',
                        'ai_plan' => "Slug: echtgold-seelen-herz | SKU: MSF-ECHTGOLD-HERZ-001 | Maße: 13 x 14 mm (585er Echtgold) + 0,8 mm Panzerkette (333er Echtgold)\nWICHTIG: Den teuren 585er Echtgold-Rohling NICHT für Musterfotos lasern!",
                    ],
*/
                    [
                        'title' => '13. Produkt 7 (FBM): Das Echtsilber Seelenarmband (id-armband-silber-herz, 925er Sterlingsilber) -> Muster bestellen & UNGRAVIERT fotografieren + Video',
                        'priority' => 'mittel',
                        'ai_plan' => "Slug: id-armband-silber-herz | SKU: MSF-SILBER-ARMBAND-001 | Lieferant: FBM (Art.-Nr. P001.53062HERZ.5.18.5)\nAnlass-Vorlagen sind bereits im ProductTemplateSeeder eingepflegt.\nWICHTIG: Den echten 925er Silber-Rohling NICHT für Musterfotos lasern!\nAblauf:\n1. Sobald FBM aus Schritt 09 das Händlerkonto freigeschaltet hat: 1x 925er Sterlingsilber ID-Armband als Foto-Muster bestellen.\n2. Das echte Armband ungraviert fotografieren (frontal von oben für Konfigurator, im schwarzen Westpack-Etui, am Handgelenk getragen).\n3. Antigravity setzt die realistische Lasergravur digital auf das Gravurschild (24 x 5,5 mm) deines echten Fotos, sodass der Rohling unversehrt für den ersten Verkauf bleibt.",
                        'subtasks' => [
                            'Nach FBM-Freischaltung 1x 925er Silber ID-Armband (P001.53062HERZ.5.18.5) als Foto-Muster bestellen (NICHT lasern!)',
                            '2D-Konfigurator-Basis (overlay.png): Ungraviertes 925er Silber-Gravurschild mit Figarokette exakt gerade von oben fotografieren',
                            'Standardbild 1 (Hauptbild): Echtes ungraviertes Foto im schwarzen Westpack-Etui -> Gravur digital durch Antigravity (id-armband-silber-herz-main.jpg)',
                            'Standardbild 2 (Rückseite): Echtes Foto der Schild-Rückseite (inkl. 925 & FBM-Punze) -> Rückseiten-Gravur digital durch Antigravity (id-armband-silber-herz-back.jpg)',
                            'Standardbild 3 (Lifestyle / Hover): Echtes Tragefoto am Handgelenk -> Gravur digital durch Antigravity (id-armband-silber-lifestyle.jpg)',
                            'Standardbild 4 (Maßbild): Echtes Produktfoto mit exakten Schild- & Kettenmaßen (18,5 cm, 24 x 5,5 mm) (id-armband-silber-herz-dimensions.jpg)',
                            'Produktvideo (MP4, 5-8 Sek.): Schwenk über das echte ungravierte 925er Silber-Armband im Licht',
                        ],
                    ],
                    [
                        'title' => '14. Produkt 8 (FBM): Das Goldene / Echtgold Seelenarmband (id-armband-gold-herz) -> Muster bestellen & UNGRAVIERT fotografieren + Video',
                        'priority' => 'mittel',
                        'ai_plan' => "Slug: id-armband-gold-herz | Lieferant: FBM (Friedrich Binder GmbH & Co. KG, Made in Germany)\nAnlass-Vorlagen sind bereits im ProductTemplateSeeder eingepflegt.\nWICHTIG: Den Gold-Rohling AUF KEINEN FALL für Musterfotos lasern!\nAblauf:\n1. Direkt zusammen mit dem Silber-Armband aus Schritt 13 bei FBM mitbestellen und unversehrt lassen.\n2. Im selben Foto-Setup ungraviert fotografieren (frontal von oben, im schwarzen Westpack-Etui, am Handgelenk getragen).\n3. Antigravity setzt den Gravurtext realistisch direkt auf das Goldschild deines echten Fotos, sodass das Schmuckstück zu 100 % verkaufsfähig bleibt!",
                        'subtasks' => [
                            'Nach FBM-Freischaltung 1x Gold ID-Armband zusammen mit Schritt 13 als Foto-Muster bestellen (NICHT lasern!)',
                            '2D-Konfigurator-Basis (overlay.png): Ungraviertes Gold-Gravurschild mit Figarokette exakt gerade von oben fotografieren',
                            'Standardbild 1 (Hauptbild): Echtes ungraviertes Foto im schwarzen Westpack-Etui -> Gravur digital durch Antigravity (id-armband-gold-herz-main.jpg)',
                            'Standardbild 2 (Rückseite): Echtes Foto der Schild-Rückseite (inkl. Punze) -> Rückseiten-Gravur digital durch Antigravity (id-armband-gold-herz-back.jpg)',
                            'Standardbild 3 (Lifestyle / Hover): Echtes Tragefoto am Handgelenk -> Gravur digital durch Antigravity (id-armband-gold-lifestyle.jpg)',
                            'Standardbild 4 (Maßbild): Echtes Produktfoto mit exakten Schild- & Kettenmaßen (id-armband-gold-herz-dimensions.jpg)',
                            'Produktvideo (MP4, 5-8 Sek.): Edler Schwenk über das echte ungravierte Gold-Armband im Schmucketui',
                        ],
                    ],
                    [
                        'title' => '15. Landing Pages (/l/{slug}) mit allen echten Produktfotos aus Schritt 02-14 final befüllen (Multi-Profil-Logik)',
                        'priority' => 'mittel',
                        'ai_plan' => "Konzept-Verknüpfung: Wissensdatenbank (/admin/tickets?view=knowledge -> Marketing / Google-Ads-Autopilot)\n\nArchitektur-Vorgabe:\n1. 1 feste Produkt-Landingpage (/l/{slug}) mit 13 logischen Vorlagen-Profilen (1 Standard/Evergreen + 4 Jahreszeiten + 8 Feiertage).\n2. Sobald alle echten Produktfotos aus Schritt 02 bis 14 hinterlegt sind, die Landing Pages final mit den echten Bildern abgleichen und Ziel-URLs (/l/{slug} & /l/{slug}?holiday=...) prüfen.\n3. Strikter Bild-Fallback in Product.php ist bereits aktiv.",
                        'subtasks' => [
                            [
                                'title' => 'Bild-Fallback-Kaskade in Product.php härten: Nach Feiertags-Ende sofort Fallback auf neutrales Hauptbild + Fehlmeldung am Produkt schärfen',
                                'is_completed' => true,
                            ],
                            [
                                'title' => 'Multi-Profil-Engine auf MarketingLandingPage (/l/{slug}) bauen: 1 Landing Page mit 13 Vorlagen-Profilen (Standard + 4 Jahreszeiten + 8 Feiertage)',
                                'is_completed' => true,
                            ],
                            'Landing Pages mit den echten eigenen Produktfotos aus Schritt 02-14 final befüllen und Ziel-URLs für Google Ads verifizieren',
                        ],
                    ],
                    [
                        'title' => '16. Google-Ads- & Merchant-Center-Autopilot ("Bis zum letzten Klick") mit den echten Produktfotos & Landingpages fertigstellen',
                        'priority' => 'mittel',
                        'ai_plan' => "WICHTIG (Start-Voraussetzung): Startet direkt nach Schritt 15, sobald alle Produktfotos echt fotografiert (ohne Werbetext-Overlays im Bild für Google Shopping!) und die Landingpages befüllt sind!\nKonzept-Dokument: Wissensdatenbank (/admin/tickets?view=knowledge -> Marketing / Google-Ads-Autopilot)\n\nGeplanter Ablauf:\n1. Google Ads Konto & Google Merchant Center auf Mein-Seelenfunke (Alina Steinhauer, Gifhorn, FYRST) eröffnen + Überprüfung der Werbetreibenden durchführen.\n2. Stufe 1 (Shop-Tracking & Feed): Bereits umgesetzt.\n3. Stufe 2 (Saison- & Evergreen-Kampagnen-Studio): Ausbau in Funkenwerk ('Ads & Budget') für Zugpferde (>= 20 € Netto-DB) -> Schicht A Evergreen auf /l/{slug} + Schicht B Feiertags-/Jahreszeiten-Anzeigengruppen auf /l/{slug}?holiday={anlass}.\n4. Stufe 3 (Google Ads API mit PAUSED-Schreibschutz & Versand-Cutoff-Stopp): Offizielle Google Ads API anbinden -> Kampagnen werden fertig pausiert ins Konto gelegt (Alina klickt beim Livegang nur noch auf 'Aktiv').",
                        'subtasks' => [
                            'Google-Ads-Konto (792-675-8923, FYRST, Verifizierung abgeschlossen) & Google-Merchant-Center-Konto eröffnen',
                            [
                                'title' => 'Google Ads API im Google-Cloud-Projekt aktivieren (Explorer Access) und OAuth-Zugang im lokalen Funkenwerk-Tresor hinterlegen',
                                'is_completed' => true,
                            ],
                            [
                                'title' => 'Stufe 1 umsetzen: Cookie-Kategorie marketing + Google Consent Mode v2 + echtes Kaufwert-Conversion-Tracking + Echtfoto-Gatekeeper für /feed/google-shopping.xml',
                                'is_completed' => true,
                            ],
                            'Stufe 2 umsetzen: KI-Kampagnen-Studio inkl. Jahreszeiten-/Feiertags-Anzeigengruppen auf /l/{slug}?holiday={anlass}, Echtfoto-Prüfung & CSV-Export bauen',
                            'Stufe 3 umsetzen: Direkter Google-Ads-API-Upload mit hartem PAUSED-Schreibschutz, Tagesbudget-Deckel & automatischem Feiertags-Versand-Cutoff-Stopp auf /admin/dashboard',
                        ],
                    ],
                    [
                        'title' => '17. Paketbeileger mit QR-Code, Instagram & TikTok gestalten und am Packtisch bereitlegen',
                        'priority' => 'mittel',
                        'ai_plan' => 'Edle Schwarz-Gold-Beilegerkarte mit QR-Code (/links bzw. Instagram & TikTok) fertigstellen, damit ab Paket #1 jede Sendung organische Bewertungen und Follower aufbaut.',
                    ],
                    [
                        'title' => '18. Westpack Geschenkpapier Nr. 0751 (1x 30 cm × 100 m Großrolle, Art.-Nr. 03307510300, 67,90 € netto) mit USt-IdNr. per Reverse-Charge als Nachschub bestellen -> https://www.westpack.com/eur_eu_ger/wrapping-paper-n-0751-reversible-03307510300.html',
                        'priority' => 'mittel',
                        'ai_plan' => 'Kein Foto- oder Start-Blocker mehr (20 Laufmeter Schwarz-Gold-Geschenkpapier von Natürlich verpacken liegen bereits für den Start bereit und die Verpackungs-Echtfotos sind erledigt). Sobald die deutsche USt-IdNr. vorliegt und die 100 m Rolle bei Westpack lieferbar ist, steuerfrei per Reverse-Charge (0,00 € MwSt., 67,90 € netto für den 50 cm Untertischabroller) als günstigen Dauer-Nachschub bestellen.',
                    ],

                    // =========================================================================
                    // STUFE 3 (19 - 23 | Prio: niedrig): FINALER LIVEGANG-SCHALTER & LAUNCH-START
                    // =========================================================================
                    [
                        'title' => '19. Vor Livegang: DHL API Keys auf Live umstellen',
                        'priority' => 'niedrig',
                        'ai_plan' => 'Produktive DHL Geschäftskunden-Zugangsdaten in der Live-Umgebung aktivieren und einen echten Label-Probelauf verifizieren.',
                    ],
                    [
                        'title' => '20. Zum Livegang: Stripe auf Live schalten, FYRST-Geschäftskonto + Steuernummer/USt-IdNr. hinterlegen & Live-API-Keys eintragen -> https://dashboard.stripe.com/acct_1Sttm3GnukHKxpOe/settings/money-management',
                        'priority' => 'niedrig',
                        'ai_plan' => "Link (Test-Ansicht): https://dashboard.stripe.com/acct_1Sttm3GnukHKxpOe/test/settings/money-management\nLink (Live-Ansicht): https://dashboard.stripe.com/acct_1Sttm3GnukHKxpOe/settings/money-management\n\nZu hinterlegende FYRST-Geschäftskontodaten für Auszahlungen (Payouts):\n- Kontoinhaberin: Alina Steinhauer (Mein-Seelenfunke)\n- Bank: FYRST / Deutsche Bank AG\n- IBAN: DE76 2507 0370 0079 8629 00\n- BIC: DEUTDE2HP24\n- Währung: EUR\n\nWichtig beim Livegang: Oben rechts im Stripe-Dashboard den Testmodus deaktivieren, das Bankkonto sowie Steuernummer/USt-IdNr. im Live-Profil hinterlegen und anschließend die Live-API-Keys (pk_live_... / sk_live_... / whsec_...) in der produktiven .env eintragen.",
                        'subtasks' => [
                            'Im Stripe-Dashboard (https://dashboard.stripe.com/acct_1Sttm3GnukHKxpOe/settings/money-management) in den Live-Modus wechseln',
                            'FYRST-Geschäftskonto hinterlegen (IBAN: DE76 2507 0370 0079 8629 00 | BIC: DEUTDE2HP24 | Inhaberin: Alina Steinhauer)',
                            'Neue Steuernummer & USt-IdNr. (DE...) in den Stripe-Unternehmensdaten hinterlegen',
                            'Stripe Live-API-Keys (STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET) in der Live-.env hinterlegen',
                        ],
                    ],
                    [
                        'title' => '21. Vor Livegang: Vertex AI Postpay (vertex-service-account.json & Live-.env) auf seelenfunke-live prüfen',
                        'priority' => 'niedrig',
                        'ai_plan' => "Vor dem Aktivieren von 'Shop Live-Freigabe' sicherstellen, dass Google Cloud Vertex AI Postpay auf seelenfunke-live aktiv ist, damit Funki (Support-Chat) nicht stillschweigend auf den AI-Studio-Fallback (GEMINI_API_KEY) zurückfällt.",
                        'subtasks' => [
                            'storage/app/vertex-service-account.json auf seelenfunke-live ablegen (chmod 600)',
                            'GOOGLE_VERTEX_ENABLED=true & GOOGLE_VERTEX_PROJECT_ID=gen-lang-client-0670786367 in der Live-.env prüfen',
                            'Kurze Testfrage im Funki-Support-Chat auf Live prüfen (Vertex AI Postpay aktiv)',
                        ],
                    ],
                    [
                        'title' => '22. Direkt zum Livegang: Instagram Start-Reel drehen (Handy in Geschenkschleife einpacken, Öffnen filmen & offenen Shop/Instagram-Account auf Zweithandy zeigen)',
                        'priority' => 'niedrig',
                        'ai_plan' => 'Sobald mein-seelenfunke.de live geschaltet ist: Zweithandy mit geöffnetem Instagram-Profil / Live-Shop in eine edle Geschenkschleife binden, das Aufmachen aus Ich-Perspektive filmen und als erstes Launch-Reel veröffentlichen.',
                    ],
                    [
                        'title' => '23. Nach Livegang (sobald alle Produkte echt gelasert & online sind): Erste 2 UGC-Creatorinnen (@content.by.lea & @anna_lena_plz) für PR-Sample + 15 % Partner-Deal anschreiben',
                        'priority' => 'niedrig',
                        'ai_plan' => "WICHTIG: Erst starten, wenn der Shop live ist und alle Produkte einmal echt gelasert, fotografiert und im Live-Shop aktiv sind!\n\nKandidatin 1 (Top-Empfehlung Schmuck & Lifestyle, direkt aus Niedersachsen):\n- Name: Lea Heitmann (@content.by.lea, Winsen)\n- Instagram: https://www.instagram.com/content.by.lea/\n- Portfolio: https://collabstr.com/contentbylea\n\nKandidatin 2 (Ideal für Familien-, Kinder- & Haustier-Gravuren wie Schiefer/Glas/Anhänger):\n- Name: Anna-Lena Palazzo (@anna_lena_plz)\n- Instagram: https://www.instagram.com/anna_lena_plz/\n\nKonditionen (0 € Fixkosten vorab über /admin/marketing-influencers):\n- 100 % Gratis-Muster (SAMPLE-... Code für den 3D-Konfigurator, Pflicht zur Verlinkung/Reel innerhalb von 14 Tagen)\n- 15 % Netto-Provision auf jeden vermittelten Verkauf (30 Tage Cookie + Code)\n- 10 % Rabattcode für ihre Community\n\nDM-Vorlage für Instagram:\n\"Hey [Lea / Anna-Lena], ich bin Alina aus Gifhorn und bin gerade mit meiner Laser-Manufaktur Mein Seelenfunke (personalisierter Edelstahl-Goldschmuck & gravierte Unikate mit eigenem 3D-Konfigurator) gestartet. Mir gefällt dein heller, natürlicher Unboxing-Stil extrem gut! Hättest du Lust, dir in unserem 3D-Konfigurator kostenlos dein eigenes Unikat mit Wunschgravur zu gestalten? Du bekommst es gratis im edlen Geschenk-Etui zugeschickt, zeigst es in einem kurzen Unboxing-Video und erhältst über unser Creator-Portal zusätzlich 15 % Provision auf jeden Verkauf sowie einen 10 % Code für deine Community. Sag gern Bescheid, dann schicke ich dir direkt deinen persönlichen Einladungslink!\"",
                        'subtasks' => [
                            'Im Admin unter /admin/marketing-influencers 1-Klick-Einladungslink + 6-stelligen PIN (15 % Provision, 10 % Follower-Rabatt, Gratis-Muster aktiv) generieren',
                            'Kandidatin 1 anschreiben: Lea Heitmann (@content.by.lea -> https://www.instagram.com/content.by.lea/ | Portfolio: https://collabstr.com/contentbylea)',
                            'Kandidatin 2 anschreiben: Anna-Lena Palazzo (@anna_lena_plz -> https://www.instagram.com/anna_lena_plz/)',
                            'Nach Zusage Einladungslink + PIN senden und Gratis-Muster nach Bestellung im 3D-Konfigurator lasern & versenden',
                        ],
                    ],

                    // =========================================================================
                    // BEREITS ERLEDIGTE MEILENSTEINE (mit hinterlegten Nachweis-Belegen)
                    // =========================================================================
                    [
                        'title' => 'Erledigt (23.11.2026): Vor Livegang Test- & Archivrechnungen entfernt + automatischen Pre-Live-Reset verankert (Start ab RE-2026-1000)',
                        'priority' => 'niedrig',
                        'is_completed' => true,
                        'ai_plan' => "ERLEDIGT am 23.11.2026:\n- Alle Testbestellungen, Bestellpositionen, Test-Herzensbeiträge, Test-Gamification-Funken und Ausgangs-/Archivrechnungen (DB + storage/app/buchhaltung/invoices/) restlos entfernt.\n- Rechnungsnummernkreis startet bei Bestellung #1 sauber bei RE-2026-1000 (inkl. SoftDelete-Schutz in InvoiceService).\n- Zusätzlich automatische Vorab-Bereinigung direkt in php artisan system:prepare-live (solange shop_is_live = false) sowie php artisan accounting:purge-prelive-orders verankert; alle echten Eingangsbelege & Verträge aus AccountingSeeder bleiben zu 100 % geschützt.",
                    ],
                    [
                        'title' => 'Erledigt (06.11.2026): Fragebogen zur steuerlichen Erfassung (ELSTER) übermittelt -> Betriebliche Steuernummer & USt-IdNr. beantragt',
                        'priority' => 'hoch',
                        'is_completed' => true,
                        'ai_plan' => "ERLEDIGT & ÜBERMITTELT am Freitag, 06.11.2026 um 15:35:38 Uhr an das Finanzamt Gifhorn (Niedersachsen).\n- ELSTER-Transferticket: ep3104hvg8fm3b1m4zrwrd3djc6ieugc\n- ELSTER-Referenz: 2169193168 -B41\n- Auftrag: Fragebogen zur steuerlichen Erfassung für Einzelunternehmen, Steinhauer Alina (Mein-Seelenfunke, Tätigkeitsbeginn 01.11.2026)\n- Beantragt: Neue betriebliche Steuernummer + Umsatzsteuer-Identifikationsnummer (USt-IdNr. beim BZSt) + Regelbesteuerung (§ 19 Abs. 3 UStG mit Vorsteuerabzug) + Istversteuerung (§ 20 UStG)\n- Hinterlegte Bankverbindung: FYRST / Deutsche Bank AG (IBAN DE76 2507 0370 0079 8629 00, BIC DEUTDE2HP24)",
                        'file_paths' => [
                            'leitung/tasks/attachments/2026-11-06_Finanzamt-Gifhorn_ELSTER-Steuerliche-Erfassung-Versandbestaetigung.pdf',
                        ],
                    ],
                ],
            ],

            // Rollenisolierter Direktkanal für externe Steuerkanzlei (/steuerberater -> TaxAdvisorDashboard)
            [
                'list' => [
                    'name' => 'Kanzlei & Steuern',
                    'icon' => 'calculator',
                    'color' => '#C5A059',
                    'position' => 2,
                ],
                'taks' => [],
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
            $phaseCount++;
            $phaseTime = $baseNow->copy()->addMinutes($phaseCount);
            $listId = Str::uuid()->toString();

            DB::table('management_task_lists')->insert([
                'id' => $listId,
                'name' => $phase['list']['name'],
                'icon' => $phase['list']['icon'],
                'color' => $phase['list']['color'],
                'position' => $phase['list']['position'] ?? $phaseCount,
                'created_at' => $phaseTime,
                'updated_at' => $phaseTime,
            ]);

            $taksToInsert = [];
            $position = 0;

            foreach ($phase['taks'] as $todo) {
                $position++;
                $taskTime = $phaseTime->copy()->addSeconds($position);
                $parentId = Str::uuid()->toString();
                $rawPrio = $todo['priority'] ?? 'niedrig';
                $mappedPrio = $priorityMap[$rawPrio] ?? 'niedrig';

                $taksToInsert[] = [
                    'id' => $parentId,
                    'task_list_id' => $listId,
                    'parent_id' => null,
                    'title' => $todo['title'],
                    'status' => ($todo['is_completed'] ?? false) ? 'completed' : 'open',
                    'ai_plan' => $todo['ai_plan'] ?? null,
                    'file_paths' => isset($todo['file_paths']) ? json_encode($todo['file_paths']) : null,
                    'is_completed' => $todo['is_completed'] ?? false,
                    'position' => $position,
                    'priority' => $mappedPrio,
                    'relevant_from' => $todo['relevant_from'] ?? null,
                    'due_time' => $todo['due_time'] ?? null,
                    'duration_minutes' => $todo['duration_minutes'] ?? 60,
                    'last_activity_at' => $taskTime,
                    'created_at' => $taskTime,
                    'updated_at' => $taskTime,
                ];

                if (!empty($todo['subtasks']) && is_array($todo['subtasks'])) {
                    $subPosition = 0;
                    foreach ($todo['subtasks'] as $sub) {
                        $subPosition++;
                        $subTime = $taskTime->copy()->addSeconds($subPosition);
                        $subTitle = is_array($sub) ? $sub['title'] : $sub;
                        $subCompleted = is_array($sub) ? ($sub['is_completed'] ?? false) : false;

                        $taksToInsert[] = [
                            'id' => Str::uuid()->toString(),
                            'task_list_id' => $listId,
                            'parent_id' => $parentId,
                            'title' => $subTitle,
                            'status' => $subCompleted ? 'completed' : 'open',
                            'ai_plan' => null,
                            'file_paths' => null,
                            'is_completed' => $subCompleted,
                            'position' => $subPosition,
                            'priority' => 'niedrig',
                            'relevant_from' => null,
                            'due_time' => null,
                            'duration_minutes' => 60,
                            'last_activity_at' => $subTime,
                            'created_at' => $subTime,
                            'updated_at' => $subTime,
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

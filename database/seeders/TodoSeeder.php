<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TodoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $phases = [
            [
                'list' => [
                    'name' => 'Phase 1: Akut & Vor der Gründung',
                    'icon' => 'exclamation-circle',
                    'color' => '#E53E3E', // Rot für hohe Dringlichkeit
                ],
                'todos' => [
                    ['title' => '15.02.2026 -> Gründungszuschuss beantragen', 'priority' => 'high'],
                    ['title' => 'Krankenkasse bescheid geben Selbständigkeit ab Mitte Februar spätestens 01.04.2026', 'priority' => 'high'],
                    ['title' => 'Laserschulung durchführen -> https://luminus-laserschutz.de/kurse/laserschutzbeauftragter-industrie-gewerbe/?_gl=1*tzjq9u*_up*MQ..*_ga*MQ..&gclid=Cj0KCQiAo4TKBhDRARIsAGW29bcTods5PR5OMiWtKsHmbh-TmpQfEYU4HeXs2Sw_lWt40yYZRhrkot4aAqSfEALw_wcB&gbra', 'priority' => 'high'],
                    ['title' => 'Marke schützen -> https://direkt.dpma.de/DpmaDirektWebEditoren/w7005/w7005web.xhtml?jftfdi=&jffi=w7005&jfwid=64cea193-7eb1-4028-ac97-7d08a38df852:0', 'priority' => 'medium'],
                    ['title' => 'Verpackungslizenz erwerben -> https://lucid.verpackungsregister.org/Hersteller/Registrierung/Teil-1', 'priority' => 'high'],
                    ['title' => 'HOSTING EINRICHTEN', 'priority' => 'high'],
                    ['title' => 'Bei Mittwald vServer https://share.google/WWxLZR9MItjByINQS', 'priority' => 'high'],
                    ['title' => 'Tracking und Cookie Warner was sollte man alles integrieren', 'priority' => 'high'],
                    ['title' => 'Den calculator nachziehen. Der geht gerade nicht', 'priority' => 'high'],
                    ['title' => 'Wenn man das Produkt auf 0% Steuersatz stellt (Zero) funktioniert die Berechnung nicht und im Warenkorb wird trotzdem 19% berechnet', 'priority' => 'high'],
                    ['title' => 'Bei der Registration muss eine Bestätigungsmail gesendet werden (verifizieren)', 'priority' => 'high'],
                    ['title' => 'In der Storno Rechnung muss noch [%KONTAKTPERSON%] umgewandelt werden', 'priority' => 'high'],
                    ['title' => 'Werden stornierte Bestellungen im Finanzmanager ignoriert und korrekt raus gerechnet', 'priority' => 'high'],
                    ['title' => 'PRODUCTKONFIGURATOR- Mobil gehen die Buttons nicht die an den Ecken', 'priority' => 'high'],
                    ['title' => 'Notfall Modus Implementierung alle drei kommt eine Mail wenn du die nicht bestätigst dann setzt sich das System auf Wartung', 'priority' => 'high'],
                    ['title' => 'DHL API und Label Drucken einbauen', 'priority' => 'high'],
                ]
            ],
            [
                'list' => [
                    'name' => 'Phase 2: Stichtag Gründung (01.04.2026)',
                    'icon' => 'flag',
                    'color' => '#D69E2E', // Gelb/Gold für den Start
                ],
                'todos' => [
                    ['title' => 'AB GEWERBESTART 01.04.2026', 'priority' => 'high'],
                    ['title' => 'ab 01.04.2026 Gewerbe anmelden https://gewerbe.buergerdienste-online.de/webclient/app/m/3151009/gewerbeanzeige?startPage=0', 'priority' => 'high'],
                    ['title' => 'Konto bei Finom eröffnen -> https://app.finom.co/de/signup/?fnm_product=business&source=finom.de&utm_source=google&utm_medium=cpc&utm_campaign=search-tofu-banking-brand-de-germany-10209387005&utm_content=cid%7C10209387005%7Cgid%7C181322099254%7Caid%7C7644', 'priority' => 'high'],
                    ['title' => 'Versicherung abschließen Versicherungen für Gründer: online absichern | Hiscox Tätigkeit: E-Commerce, Händler von Büro ... https://direkt-versicherung.hiscox.de/antrag/Angebot?ProductId=H-DPD-PSC-1-VersicherungfuerShops', 'priority' => 'high'],
                    ['title' => 'Arbeitslosenversicherung abschließen - https://www.arbeitsagentur.de/freiwillige-arbeitslosenversicherung', 'priority' => 'medium'],
                    ['title' => 'Google Business Account einrichten', 'priority' => 'medium'],
                ]
            ],
            [
                'list' => [
                    'name' => 'Phase 3: Laufender Betrieb & Buchhaltung',
                    'icon' => 'document-text',
                    'color' => '#3182CE', // Blau für Orga & Verwaltung
                ],
                'todos' => [
                    ['title' => 'DATEV Anbindung', 'priority' => 'high'],
                    ['title' => 'STEUER - ABSCHLUSS', 'priority' => 'high'],
                    ['title' => 'Umsatzsteuererklärung erstellen', 'priority' => 'high'],
                    ['title' => 'Gewinn & Verlust Rechnung', 'priority' => 'high'],
                    ['title' => 'Einnahme-Überschuss Rechnung', 'priority' => 'high'],
                    ['title' => 'Das soll alles automatisch beim Drücken von "Export" passieren', 'priority' => 'medium'],
                    ['title' => 'Gutschriften Verwaltung. Ich muss Kunden Gutschriften ausstellen können, um alle möglichen Kostenreduzierungen, Extra Vereinbarungen usw abdecken zu können. Heißt offiziell Rechnungskorrektur', 'priority' => 'high'],
                    ['title' => 'Bestell Status muss am Ende über die DHL API abgeglichen werden', 'priority' => 'medium'],
                    ['title' => 'Frage: wie geht das mit den Rechnungen, wenn ich über etsy und meinen eigenen Shop verkaufen würde. Etsy API, Bestellungen mit in extra Tabelle erfassen und in Bestellung und Rechnung einfließen lassen können.', 'priority' => 'medium'],
                    ['title' => 'Etsy per API anbinden', 'priority' => 'high'],
                ]
            ],
            [
                'list' => [
                    'name' => 'Phase 4: Shop-Features & Workflow',
                    'icon' => 'cog',
                    'color' => '#805AD5', // Lila für Features
                ],
                'todos' => [
                    ['title' => 'System Check fehlende Daten usw alles rot statt in gold anzeigen', 'priority' => 'medium'],
                    ['title' => 'Die Glocke oben im Backend Header mit dem System Check Koppeln', 'priority' => 'medium'],
                    ['title' => 'Angebote sollten Archiviert werden können & muss bei der Hauptübersicht angezeigt werden "Archiv öffnen"', 'priority' => 'medium'],
                    ['title' => 'Kategorien Verwaltung unter variable Kosten genau so bauen wie die Kategorie Verwaltung im Produkt', 'priority' => 'medium'],
                    ['title' => 'Produktkonfigurator mit in die PDF per Mail an den Kunden / Als Sicherheit', 'priority' => 'medium'],
                    ['title' => 'Beim Produkt muss man einstellen können (mit Geschenkpapier möglich)', 'priority' => 'medium'],
                    ['title' => 'Geschenkpapier als extra Auswahl neben Express (die Auswahl soll nicht per Haken sondern per Feld passieren, es sind graue Felder, klickt man die an werden sie grün) ein Produkt hat mehrere Extras. Ein extra hat ein Preis, einen Namen und Beschreibung', 'priority' => 'medium'],
                    ['title' => 'Extras werden im Produkt Konfigurator angezeigt (Express, Geschenkpapier) entsprechendes Putzmaterial direkt mit anbieten', 'priority' => 'medium'],
                    ['title' => 'Vorher nachher Beispiel Konfigurator aufzeigen damit der Kunde weiß was er bekommt', 'priority' => 'medium'],
                    ['title' => 'Bilder Icon Vorlagen die man schon Vorauswahlen kann im Produkt Konfigurator', 'priority' => 'medium'],
                    ['title' => 'PRODUCTKONFIGURATOR - Vorgefertige Icons zur Auswahl hinzufügen', 'priority' => 'medium'],
                    ['title' => 'PRODUCTKONFIGURATOR - Hochgeladene Bilder in laser Kontrast umwandeln', 'priority' => 'medium'],
                    ['title' => 'Express Checkout', 'priority' => 'medium'],
                    ['title' => 'Register Button Loading Icon links neben den Text und E-Mail Verifikation', 'priority' => 'medium'],
                    ['title' => 'Zahlungsstatus manuell ändern können (notfalls) bei Orders', 'priority' => 'medium'],
                    ['title' => 'Rechnung Zahlungsart ändern können (bei manueller Rechnung)', 'priority' => 'medium'],
                    ['title' => 'Status der Rechnung trotzdem händisch anpassen können?', 'priority' => 'medium'],
                    ['title' => 'Einbauen das Kunden privat oder geschäftlich sein können. Wie verhält sich das mit dem Google Account? Einfach Standard privat Kunde ?', 'priority' => 'medium'],
                    ['title' => 'Produkt löschen ? Was passiert mit den Daten im System ansonsten archivierten', 'priority' => 'medium'],
                    ['title' => 'Orders müssen ein SoftDelete bekommen beim löschen', 'priority' => 'medium'],
                    ['title' => 'Text Formatierung im Blogeintrag richtig machen', 'priority' => 'medium'],
                ]
            ],
            [
                'list' => [
                    'name' => 'Phase 5: Nice-to-Have & Marketing',
                    'icon' => 'sparkles',
                    'color' => '#38A169', // Grün für Wachstum/Zukunft
                ],
                'todos' => [
                    ['title' => 'Google Bewertungen aktivieren', 'priority' => 'low'],
                    ['title' => 'Google Bewertungen implementieren - TEMPORÄRER TEST-MODUS', 'priority' => 'low'],
                    ['title' => 'Produktbewertungen sollten möglich sein', 'priority' => 'low'],
                    ['title' => 'PRODUCTKONFIGURATOR- Three js 3D Ansicht bauen', 'priority' => 'low'],
                    ['title' => 'Produktkonfigurator muss in 3d funktionieren - https://share.google/7IoXXDqclKzMX6M4i', 'priority' => 'low'],
                    ['title' => 'Macht es sinn, das der Kunde Punkte sammeln kann?', 'priority' => 'low'],
                    ['title' => 'Was könnte man den Kunden noch alles in seinem Dashboard anbieten', 'priority' => 'low'],
                    ['title' => 'Was passiert wenn der Kunde seinen Account löscht?', 'priority' => 'low'],
                    ['title' => 'Sind Versandkosten eigentlich als Sonderausgabe zu erfassen?', 'priority' => 'low'],
                    ['title' => 'Der nächste sinnvolle Feiertag muss auf der Startseite groß erkenntlich sein Dashboard Admin', 'priority' => 'low'],
                    ['title' => 'Blog Seiten Design anpassen. Das muss viel schöner und professioneller dargestellt werden. Der Text', 'priority' => 'low'],
                    ['title' => 'Blog Tabelle mobil optimieren', 'priority' => 'low'],
                    ['title' => 'Kostenstellen bearbeiten Header mobil optimieren. Überschrift und dann der dropdown über die ganze Breite', 'priority' => 'low'],
                    ['title' => 'Unter Shop Einstellungen - Versand und Länder Button mobile optimieren', 'priority' => 'low'],
                    ['title' => 'Export Button fest einfügen nicht über absolut', 'priority' => 'low'],
                    ['title' => 'Multi Channel Marketing Dashboard selber bauen / interaktive Ansicht der ads, landing Pages usw plus Auswertungen', 'priority' => 'low'],
                ]
            ],
        ];

        foreach ($phases as $phase) {
            // Liste erstellen
            $listId = Str::uuid()->toString();

            DB::table('todo_lists')->insert([
                'id' => $listId,
                'name' => $phase['list']['name'],
                'icon' => $phase['list']['icon'],
                'color' => $phase['list']['color'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Dazugehörige Todos einfügen
            $todosToInsert = [];
            $position = 0;

            foreach ($phase['todos'] as $todo) {
                $todosToInsert[] = [
                    'id' => Str::uuid()->toString(),
                    'todo_list_id' => $listId,
                    'parent_id' => null, // Wir halten es flach, wie gewünscht
                    'title' => $todo['title'],
                    'is_completed' => false,
                    'position' => $position++,
                    'priority' => $todo['priority'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('todos')->insert($todosToInsert);
        }
    }
}

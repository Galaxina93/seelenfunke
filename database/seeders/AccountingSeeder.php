<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use App\Models\Accounting\AccountingCategory;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingSpecialIssue;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run()
    {
        // Wir holen den ersten Admin. Finanzen hängen am Admin.
        $admin = Admin::first();

        if (!$admin) {
            $this->command->error('Kein Admin gefunden! Bitte erstelle zuerst einen Admin.');
            return;
        }

        // --- 0. Kategorien erstellen ---
        $categories = [
            'Nahrungsmittel',
            'Glücksspiel',
            'Freizeit',
            'Gesundheit',
            'Kosmetik',
            'Kleidung',
            'Geschenk',
            'Friseur',
            'Technik',
            'Auto',
            'Sonstiges',
            'Feiertage',
            'Fitness',
            'Schmutzwasser',
            'Steuer',
            'Kind',
            'Haushalt',
            'Sprit',
            // Gewerbliche Kategorien aus den Daten
            'Arbeitsmaterial',
            'Wareneinkauf',
            'Rohmaterial',
            'Verpackungen',
            'Software & Lizenzen',
            'Werbung & Marketing'
        ];

        $businessNames = [
            'Arbeitsmaterial',
            'Wareneinkauf',
            'Rohmaterial',
            'Verpackungen',
            'Software & Lizenzen',
            'Werbung & Marketing'
        ];

        foreach ($categories as $catName) {
            AccountingCategory::updateOrCreate(
                [
                    'admin_id' => $admin->id,
                    'name'     => $catName
                ],
                [
                    'is_business' => in_array($catName, $businessNames)
                ]
            );
        }

        // --- 1. Gruppen erstellen ---

        $groupsData = [
            'Einnahmen'        => ['type' => 'income',  'pos' => 10],
            'Haus'             => ['type' => 'expense', 'pos' => 20],
            'Versicherungen'   => ['type' => 'expense', 'pos' => 30],
            'Vertrag & Lizenz' => ['type' => 'expense', 'pos' => 40],
            'Auto'             => ['type' => 'expense', 'pos' => 50],
            'Unterhalt'        => ['type' => 'expense', 'pos' => 60],
        ];

        $createdGroups = [];

        foreach ($groupsData as $name => $data) {
            $group = AccountingGroup::create([
                'admin_id' => $admin->id,
                'name'     => $name,
                'type'     => $data['type'],
                'position' => $data['pos'],
            ]);
            $createdGroups[$name] = $group->id;
        }

        // --- 2. Kostenstellen erstellen ---

        $items = [
            // Einnahmen (Positiv)
            [
                'group'       => 'Einnahmen',
                'name'        => 'ALG 1',
                'amount'      => 1806.30,
                'interval'    => 1, // monthly
                'start_date'  => '2026-01-30',
                'last_date'   => '2027-01-30',
                'provider_company' => 'Agentur für Arbeit Gifhorn',
                'provider_street'  => 'Winkeler Str. 1',
                'provider_zip'     => '38518',
                'provider_city'    => 'Gifhorn',
                'provider_phone'   => '0800 4 5555-00',
                'contract_number'  => '241D129534',
                'description' => "Kontaktdaten Name: Frau Grandke\nTelefon Alternativ: +49 5351 522 888\nÖffnungszeiten:\nMo – Do: 08:00 – 18:00\nFr: 08:00 – 14:00",
                'tags'        => ['Einnahme', 'Staat', 'Förderung', 'Einkommen', 'Agentur für Arbeit'],
                'contract_file_path' => 'buchhaltung/contracts/2026/01/2026-01-30_Staat_ALG-1.pdf'
            ],
            [
                'group'       => 'Einnahmen',
                'name'        => 'Gründerzuschuss',
                'amount'      => 0,
                'interval'    => 1, // monthly
                'start_date'  => '2026-01-30',
                'last_date'   => '2027-01-30',
                'provider_company' => 'K/A',
                'provider_street'  => 'Winkeler Straße',
                'provider_house_number' => '1',
                'provider_zip'     => '38518',
                'provider_city'    => 'Gifhorn',
                'provider_email'   => 'K/A',
                'provider_phone'   => 'K/A',
                'contract_number'  => 'K/A',
                'notice_period'    => 'K/A',
                'description' => null,
                'tags'        => ['Einnahme', 'Staat', 'Förderung', 'Einkommen'],
            ],
            [
                'group'       => 'Einnahmen',
                'name'        => 'Miete',
                'amount'      => 550.00,
                'interval'    => 1,
                'start_date'  => '2025-07-22',
                'description' => null,
                'provider_company' => 'Alina Steinhauer',
                'provider_street'  => 'Carl-Goerdeler-Ring',
                'provider_house_number' => '26',
                'provider_zip'     => '38518',
                'provider_city'    => 'Gifhorn',
                'provider_email'   => 'alina.stone@t-online.de',
                'provider_phone'   => '015901966864',
                'contract_number'  => 'Vertrag-002',
                'notice_period'    => '3 Monate',
                'tags'        => ['Einnahme', 'Immobilie', 'Passiv', 'Mieteinnahmen'],
                'contract_file_path' => 'buchhaltung/contracts/2025/07/2025-07-22_Vermieter_Miete.pdf'
            ],

            // Haus (Ausgaben = Negativ)
            [
                'group'       => 'Haus',
                'name'        => 'Wasser',
                'amount'      => -18.00,
                'interval'    => 1,
                'start_date'  => '2021-09-14',
                'provider_company' => 'LSW Energie GmbH & Co. KG',
                'provider_street'  => 'Heßlinger Straße 1 – 5',
                'provider_zip'     => '38440',
                'provider_city'    => 'Wolfsburg',
                'provider_phone'   => '0800 46 36 579',
                'provider_email'   => 'service@lsw.de',
                'contract_number'  => '0014808802',
                'description' => "0800 INFOLSW\nMo – Fr von 7 – 19 Uhr",
                'tags'        => ['Haus', 'Nebenkosten', 'Versorger', 'Wohnen', 'LSW Energie'],
                'contract_file_path' => 'buchhaltung/contracts/2021/09/2021-09-14_LSW-Energie_Wasser.pdf'
            ],
            [
                'group'       => 'Haus',
                'name'        => 'Strom',
                'amount'      => -18.00,
                'interval'    => 1,
                'start_date'  => '2021-09-14',
                'provider_company' => 'LSW Netz',
                'provider_street'  => 'Heßlinger Str. 1-5',
                'provider_zip'     => '38440',
                'provider_city'    => 'Wolfsburg',
                'provider_phone'   => '05361 189-3600',
                'provider_email'   => 'info@lsw-netz.de',
                'provider_website' => 'www.lsw-netz.de',
                'contract_number'  => '0016028714/0014761613',
                'description' => "Ansprechpartnerin: Fr. Söhlig\n(inkl. Telefon und direkter Mailadresse)\nSteuernummer vorhanden.",
                'tags'        => ['Haus', 'Nebenkosten', 'Energie', 'Versorger', 'LSW Netz'],
                'contract_file_path' => 'buchhaltung/contracts/2021/09/2021-09-14_LSW-Netz_Strom.pdf'
            ],
            [
                'group'       => 'Haus',
                'name'        => 'Kredit',
                'amount'      => -546.00,
                'interval'    => 1,
                'start_date'  => '2021-09-14',
                'provider_company' => 'Volksbank eG Braunschweig Wolfsburg',
                'provider_street'  => 'Am Mühlengraben 1',
                'provider_zip'     => '38440',
                'provider_city'    => 'Wolfsburg',
                'contract_number'  => '8583196200',
                'description' => "Anbieter: Volksbank eG Braunschweig Wolfsburg",
                'tags'        => ['Haus', 'Darlehen', 'Bank', 'Finanzierung', 'Volksbank'],
                'contract_file_path' => 'buchhaltung/contracts/2021/09/2021-09-14_Volksbank_Kredit.pdf'
            ],
            [
                'group'       => 'Haus',
                'name'        => 'Abfall',
                'amount'      => -60.72,
                'interval'    => 3, // quarter_yearly
                'start_date'  => '2021-09-14',
                'provider_company' => 'Landkreis Gifhorn',
                'provider_street'  => 'Schlossplatz 1',
                'provider_zip'     => '38518',
                'provider_city'    => 'Gifhorn',
                'provider_phone'   => '05371 82-781',
                'provider_email'   => 'Kundenservice.Abfall@gifhorn.de',
                'contract_number'  => '9054903',
                'description' => "Objektangaben: Carl-Goerdeler-Ring 26, Objektnummer: 011531\nBehörde: Der Landrat, 9 - Umwelt\nKundenservice-Postfach: KH III",
                'tags'        => ['Haus', 'Nebenkosten', 'Gemeinde', 'Wohnen', 'Landkreis Gifhorn'],
                'contract_file_path' => 'buchhaltung/contracts/2021/09/2021-09-14_Landkreis-Gifhorn_Abfall.pdf'
            ],
            [
                'group'       => 'Haus',
                'name'        => 'Rundfunkbeitrag',
                'amount'      => -18.36,
                'interval'    => 1,
                'start_date'  => '2021-09-14',
                'description' => "Bitte nutzen Sie für Beitragsanliegen die Online-Formulare.\nGeschäftsführer: Michael Krüßel\nIBAN: DE96200700000111111100\nBIC: DEUTDEHHXXX",
                'provider_company' => 'ARD ZDF Deutschlandradio Beitragsservice',
                'provider_street' => 'Freimersdorfer Weg',
                'provider_house_number' => '6',
                'provider_zip' => '50829',
                'provider_city' => 'Köln',
                'provider_phone' => '0221 5061-0',
                'provider_email' => 'impressum@rundfunkbeitrag.de',
                'contract_number' => '705238795',
                'notice_period' => 'K/A',
                'tags'        => ['Haus', 'Behörde', 'GEZ', 'Pflicht'],
                'contract_file_path' => 'buchhaltung/contracts/2021/09/2021-09-14_GEZ_Rundfunkbeitrag.pdf'
            ],
            [
                'group'       => 'Haus',
                'name'        => 'Grundsteuer',
                'amount'      => -103.36,
                'interval'    => 3,
                'start_date'  => '2021-09-14',
                'provider_company' => 'Stadt Gifhorn',
                'provider_city'    => 'Gifhorn',
                'provider_phone'   => '05371/88-184',
                'provider_email'   => 'a.hein@stadt-gifhorn.de',
                'contract_number'  => '182816-001-60',
                'description' => "Anbieter: Stadt Gifhorn, Der Bürgermeister\nZuständig: Alexander Hein (Zimmer 237)\nGläubiger-ID: DE16ZZZ00000000942",
                'tags'        => ['Haus', 'Steuer', 'Immobilie', 'Gemeinde', 'Stadt Gifhorn'],
                'contract_file_path' => 'buchhaltung/contracts/2021/09/2021-09-14_Stadt-Gifhorn_Grundsteuer.pdf'
            ],
            [
                'group'       => 'Haus',
                'name'        => 'Schmutzwasser',
                'amount'      => -9.00,
                'interval'    => 1,
                'start_date'  => '2025-02-13',
                'provider_company' => 'ASG Stadt Gifhorn',
                'provider_street'  => 'Winkeler Str. 4',
                'provider_zip'     => '38518',
                'provider_city'    => 'Gifhorn',
                'provider_phone'   => '05371/9842-12',
                'provider_email'   => 'b.baumann@asg-gifhorn.de',
                'contract_number'  => '82816 - 2',
                'description' => "Sachbearbeiterin: Britta Baumann",
                'tags'        => ['Haus', 'Nebenkosten', 'Gemeinde', 'Wasser', 'ASG Stadt Gifhorn'],
                'contract_file_path' => 'buchhaltung/contracts/2025/02/2025-02-13_ASG-Stadt-Gifhorn_Schmutzwasser.pdf'
            ],
            [
                'group'       => 'Haus',
                'name'        => 'Straßenreinigung',
                'amount'      => -15.18,
                'interval'    => 3,
                'start_date'  => '2022-03-23',
                'provider_company' => 'ASG Stadt Gifhorn',
                'provider_street'  => 'Winkeler Str. 4',
                'provider_zip'     => '38518',
                'provider_city'    => 'Gifhorn',
                'provider_phone'   => '05371/9842-13',
                'provider_email'   => 's.sauerbrei@asg-gifhorn.de',
                'contract_number'  => '82816 - 1',
                'description' => "Benutzungsgebühren (Straßenreinigung/Winterdienst)\nZuständig: Simone Sauerbrei",
                'tags'        => ['Haus', 'Nebenkosten', 'Gemeinde', 'ASG Stadt Gifhorn'],
                'contract_file_path' => 'buchhaltung/contracts/2022/03/2022-03-23_ASG-Stadt-Gifhorn_Strassenreinigung.pdf'
            ],

            // Versicherungen (Ausgaben = Negativ)
            [
                'group'       => 'Versicherungen',
                'name'        => 'Unfall',
                'amount'      => -43.26,
                'interval'    => 12, // yearly
                'start_date'  => '2021-12-14',
                'provider_company' => 'Tecis',
                'provider_street'  => 'Swiss-Life-Platz 1',
                'provider_zip'     => '30659',
                'provider_city'    => 'Hannover',
                'provider_phone'   => '0511/90200',
                'provider_email'   => 'kundenservice@tecis.de',
                'provider_website' => 'www.tecis.de',
                'contract_number'  => '104618765',
                'description' => "Allianz vorher: montl. -9,78€\nJetzt: montl. 3,60€",
                'tags'        => ['Versicherung', 'Vorsorge', 'Gesundheit', 'Tecis'],
                'contract_file_path' => 'buchhaltung/contracts/2021/12/2021-12-14_Swiss-Life_Unfall.pdf'
            ],
            [
                'group'       => 'Versicherungen',
                'name'        => 'Hausrat',
                'amount'      => -86.69,
                'interval'    => 12,
                'start_date'  => '2021-12-15',
                'provider_company' => 'Tecis',
                'provider_street'  => 'Alter Teichweg 17',
                'provider_zip'     => '22081',
                'provider_city'    => 'Hamburg',
                'provider_phone'   => '040-69 69 69 69',
                'provider_email'   => 'kundenservice@tecis.de',
                'contract_number'  => '02591521',
                'description' => "Vorher Allianz Jährlich: -117,36\nBezahlt am: 15.12...",
                'tags'        => ['Versicherung', 'Wohnen', 'Absicherung', 'Tecis'],
                'contract_file_path' => 'buchhaltung/contracts/2021/12/2021-12-15_Barmenia_Hausrat.pdf'
            ],
            [
                'group'       => 'Versicherungen',
                'name'        => 'Haftpflicht',
                'amount'      => -62.18,
                'interval'    => 12,
                'start_date'  => '2021-12-15',
                'provider_company' => 'Tecis',
                'provider_street'  => 'Alter Teichweg 17',
                'provider_zip'     => '22081',
                'provider_city'    => 'Hamburg',
                'provider_phone'   => '040 69 69 69 69',
                'provider_email'   => 'kundenservice@tecis.de',
                'contract_number'  => '254998914',
                'description' => "Vorher Allianz: 70.40€\nVermietete Objekte sind mi...",
                'tags'        => ['Versicherung', 'Vorsorge', 'Absicherung', 'Tecis'],
                'contract_file_path' => 'buchhaltung/contracts/2021/12/2021-12-15_Barmenia_Haftpflicht.pdf'
            ],
            [
                'group'       => 'Versicherungen',
                'name'        => 'Wohngebäude',
                'amount'      => -754.60,
                'interval'    => 12,
                'start_date'  => '2025-12-14',
                'provider_company' => 'Barmenia',
                'provider_street'  => 'Barmenia-Allee 1',
                'provider_zip'     => '42119',
                'provider_city'    => 'Wuppertal',
                'provider_phone'   => '(02 02) 438 - 3850',
                'provider_website' => 'www.barmenia.de',
                'contract_number'  => '304734394',
                'description' => "Kundennummer: 704555407\nGläubiger-Identifikationsnummer: DE63ZZZ0000001057...",
                'tags'        => ['Versicherung', 'Immobilie', 'Absicherung', 'Wichtig', 'Barmenia'],
                'contract_file_path' => 'buchhaltung/contracts/2025/12/2025-12-14_Barmenia_Wohngebaeude.pdf'
            ],
            [
                'group'       => 'Versicherungen',
                'name'        => 'Betriebshaftpflicht',
                'amount'      => 0,
                'interval'    => 1, // monthly
                'start_date'  => '2026-04-15',
                'is_business' => 1,
                'provider_company' => 'K/A',
                'provider_street'  => 'Bernhard-Wicki-Str.',
                'provider_house_number' => '3',
                'provider_zip'     => '80636',
                'provider_city'    => 'München',
                'provider_phone'   => '+49 89 54 58 01 281',
                'provider_email'   => 'hiscox.info@hiscox.de',
                'provider_website' => 'https://www.hiscox.de',
                'contract_number'  => 'K/A',
                'notice_period'    => 'K/A',
                'description' => "Betriebs­haftpflicht zur Absicherung bei gravierenden Fehlern.\nHandelsregister: München HRB 238125\nUSt-IDNr. DE320201626\nVersicherungssteuernr.: 802/V20000024429",
                'tags'        => ['Versicherung', 'Gewerbe', 'Absicherung', 'Pflicht'],
            ],
            [
                'group'       => 'Versicherungen',
                'name'        => 'Rechtss. Gew. & Privat',
                'amount'      => -317.97,
                'interval'    => 12,
                'start_date'  => '2021-09-14',
                'provider_company' => 'ARAG SE',
                'provider_street'  => 'ARAG Platz 1',
                'provider_zip'     => '40472',
                'provider_city'    => 'Düsseldorf',
                'provider_phone'   => '(0211) 9890-2478',
                'provider_email'   => 'service@ARAG.de',
                'provider_website' => 'www.ARAG.de',
                'contract_number'  => '11 0085 1757 8482',
                'description' => null,
                'tags'        => ['Versicherung', 'Recht', 'Absicherung', 'Gewerbe', 'Privat', 'ARAG'],
                'contract_file_path' => 'buchhaltung/contracts/2021/09/2021-09-14_ARAG_Rechtsschutz-Gewerbe-Privat.pdf'
            ],
            [
                'group'       => 'Versicherungen',
                'name'        => 'Zahnzusatzversicherung',
                'amount'      => -23.58,
                'interval'    => 1,
                'start_date'  => '2023-10-15',
                'provider_company' => 'Tecis',
                'provider_street'  => 'Laher-Feld-Str. 24',
                'provider_zip'     => '30659',
                'provider_city'    => 'Hannover',
                'provider_phone'   => '0 40 69 69 69-61',
                'provider_email'   => 'vertriebspartner-service@tecis.de',
                'contract_number'  => 'AK - 6099415323',
                'description' => "Weitere E-Mail:\nKundenservice@tecis.de",
                'tags'        => ['Versicherung', 'Gesundheit', 'Vorsorge', 'Tecis'],
                'contract_file_path' => 'buchhaltung/contracts/2023/10/2023-10-15_tecis_Zahnzusatzversicherung.pdf'
            ],
            [
                'group'       => 'Versicherungen',
                'name'        => 'Risikolebensversicherung',
                'amount'      => -106.18,
                'interval'    => 12,
                'start_date'  => '2025-12-14',
                'provider_company' => 'Baloise Lebensversicherung AG Deutschland',
                'provider_street'  => 'Ludwig-Erhard-Straße 22',
                'provider_zip'     => '20459',
                'provider_city'    => 'Hamburg',
                'provider_phone'   => '06172 1254600',
                'contract_number'  => 'L 2.658.240',
                'description' => "Anbieter: Baloise Lebensversicherung AG Deutschland\nService-Team Baloise/Basler:\nTelefon: 040 / 3599-7911\nE-Mail: Premium@Basler.de\nLaher-Feld-Str. 24, 30659 Hannover",
                'tags'        => ['Versicherung', 'Vorsorge', 'Familie', 'Absicherung', 'Baloise'],
                'contract_file_path' => 'buchhaltung/contracts/2025/12/2025-12-14_Baloise_Risikolebensversicherung.pdf'
            ],
            [
                'group'       => 'Versicherungen',
                'name'        => 'Arbeitslosenversicherung',
                'amount'      => 0,
                'interval'    => 1, // monthly
                'start_date'  => '2026-04-15',
                'is_business' => 1,
                'provider_company' => 'K/A',
                'provider_street'  => 'Winkeler Straße',
                'provider_house_number' => '1',
                'provider_zip'     => '38518',
                'provider_city'    => 'Gifhorn',
                'provider_email'   => 'K/A',
                'provider_phone'   => 'K/A',
                'contract_number'  => 'K/A',
                'notice_period'    => 'K/A',
                'description' => "Arbeitslosenversicherung zur Absicherung falls die Firma nicht klappt.",
                'tags'        => ['Versicherung', 'Gewerbe', 'Vorsorge', 'Absicherung'],
            ],

            // Vertrag & Lizenz (Ausgaben = Negativ)

            [
                'group'       => 'Vertrag & Lizenz',
                'name'        => 'Glasfaser',
                'amount'      => -49.99,
                'interval'    => 1,
                'start_date'  => '2021-09-14',
                'provider_company' => 'Telekom Deutschland GmbH',
                'provider_street'  => 'Landgrabenweg 151',
                'provider_zip'     => '53227',
                'provider_city'    => 'Bonn',
                'provider_phone'   => '0800 22 66 100',
                'provider_website' => 'www.telekom.de',
                'contract_number'  => '2752557615',
                'description' => "1-12 Monat (-25€)\nPaket: DG Classic 400\nPostanschrift: 53171 Bonn\nUSt.-IdNr. DE122265872\nInternet: www.telekom.de/glasfaser-ratgeber",
                'tags'        => ['Vertrag', 'Internet', 'Kommunikation', 'Wohnen', 'Telekom'],
                'contract_file_path' => 'buchhaltung/contracts/2021/09/2021-09-14_Deutsche-Glasfaser_Glasfaser.pdf'
            ],
            [
                'group'       => 'Vertrag & Lizenz',
                'name'        => 'Handy',
                'amount'      => -7.99,
                'interval'    => 1,
                'start_date'  => '2025-02-10',
                'provider_company' => 'Drillisch Online GmbH',
                'provider_street'  => 'Lindleystraße 11',
                'provider_zip'     => '60314',
                'provider_city'    => 'Frankfurt am Main',
                'provider_phone'   => '06181 7074 243',
                'provider_email'   => 'kontakt@handyvertrag.de',
                'provider_website' => 'https://service.handyvertrag.de/start',
                'description' => "Geschäftsführung: Christian Bockelt\nFaxnummer: 06181 7074 063\nErreichbarkeitszeiten: Täglich 6–22 Uhr",
                'tags'        => ['Vertrag', 'Mobilfunk', 'Kommunikation', 'Smartphone', 'Drillisch'],
                'contract_file_path' => 'buchhaltung/contracts/2025/02/2025-02-10_Drillisch_Handy.pdf'
            ],
            [
                'group'       => 'Vertrag & Lizenz',
                'name'        => 'PHP Storm',
                'amount'      => -70.21,
                'interval'    => 12,
                'start_date'  => '2025-04-29',
                'description' => "The Netherlands\nFax: +31 (0)20 205 01 19",
                'provider_company' => 'JetBrains N.V.',
                'provider_street'  => 'Gelrestraat',
                'provider_house_number' => '16',
                'provider_zip'     => '1079 MZ',
                'provider_city'    => 'Amsterdam',
                'provider_phone'   => '+31 (0)20 205 01 18',
                'provider_email'   => 'legal-nl@jetbrains.com',
                'contract_number'  => 'DZTR3UPAP9',
                'notice_period'    => 'K/A',
                'tags'        => ['Lizenz', 'Vertrag', 'Software', 'Entwicklung', 'Abo'],
                'contract_file_path' => 'buchhaltung/contracts/2025/04/2025-04-29_JetBrains_PHP-Storm.pdf'
            ],
            [
                'group'       => 'Vertrag & Lizenz',
                'name'        => 'infomaniak (Datenverwaltung)',
                'amount'      => -19.00,
                'interval'    => 12,
                'start_date'  => '2026-01-20',
                'provider_company' => 'Infomaniak Network AG',
                'provider_street'  => 'Rue Eugène Marziano 25',
                'provider_zip'     => '1227',
                'provider_city'    => 'Les Acacias (GE)',
                'description' => "Firmensitz: Schweiz\nHandelsregister-Nummer: CH-660.0.059.996-1\nKonto-Informationen: Alina Steinhauer, alina.stone@t-online.de",
                'tags'        => ['Vertrag', 'IT', 'Cloud', 'Backup', 'infomaniak'],
                'contract_file_path' => 'buchhaltung/contracts/2026/01/2026-01-20_Infomaniak_Datenverwaltung.pdf'
            ],
            [
                'group'       => 'Vertrag & Lizenz',
                'name'        => 'Verpackungslizenz',
                'amount'      => -39.00,
                'interval'    => 12, // yearly
                'start_date'  => '2026-04-15',
                'is_business' => 1,
                'provider_company' => 'Interzero Recycling Alliance GmbH',
                'provider_street'  => 'Stollwerckstr.',
                'provider_house_number' => '9a',
                'provider_zip'     => '51149',
                'provider_city'    => 'Köln',
                'provider_phone'   => '+49 2203 9147-1964',
                'provider_email'   => 'kontakt@lizenzero.de',
                'contract_number'  => 'K/A',
                'notice_period'    => 'K/A',
                'description' => "Verpackungslizenz Gebühren zum Versand (Verpackungsmüll).\nGeschäftsführung: Michael Bürstner, Frank Kurrat\nAmtsgericht Köln HRB 104034\nUST-IDNr. DE345747730\nVerantwortlicher im Sinne von § 18 Abs. 2 MStV: Frank Kurrat",
                'tags'        => ['Lizenz', 'Gewerbe', 'Pflicht', 'Logistik'],
            ],
            [
                'group'       => 'Vertrag & Lizenz',
                'name'        => 'Hosting',
                'amount'      => -35.00,
                'interval'    => 1, // monthly
                'start_date'  => '2026-04-15',
                'is_business' => 1,
                'provider_company' => 'Mittwald CM Service GmbH & Co. KG',
                'provider_street'  => 'Königsberger Straße 4-6',
                'provider_zip'     => '32339',
                'provider_city'    => 'Espelkamp',
                'provider_phone'   => '+49-5772-293-100',
                'description' => "Mittwald Hosting zum verwalten und betreiben der Domains\nHRA: 6640, AG Bad Oeynhausen\nUSt. ID-Nr: DE814773217\nKunden-E-Mail: kontakt@mein-seelenfunke.de\nFax: +49-5772-293-333",
                'tags'        => ['Vertrag', 'IT', 'Gewerbe', 'Hosting', 'Mittwald'],
                'contract_file_path' => 'buchhaltung/contracts/2026/04/2026-04-15_Mittwald_Hosting.pdf'
            ],
            [
                'group'       => 'Vertrag & Lizenz',
                'name'        => 'Gemini Ultra',
                'amount'      => -219.99,
                'interval'    => 1,
                'start_date'  => '2026-04-01',
                'is_business' => 1,
                'provider_company' => 'Google Commerce Limited',
                'provider_street'  => 'Gordon House, Barrow Street',
                'provider_zip'     => 'Dublin 4',
                'provider_city'    => 'Dublin, Ireland',
                'contract_number'  => '2473286515522798-1',
                'description' => "Google AI Cloud - Agenten Workspace & API\nUmsatzsteuer-Identifikationsnummer: IE9825613N\nHandelsregisternummer der GCL: 512080\nGCL-Aktienkapital: 1.000.000 €",
                'tags'        => ['Vertrag', 'IT', 'Gewerbe', 'KI', 'Google'],
                'contract_file_path' => 'buchhaltung/contracts/2026/04/2026-04-01_Google_Gemini-Ultra.pdf'
            ],

            // Auto (Ausgaben = Negativ)
            [
                'group'       => 'Auto',
                'name'        => 'Benzin',
                'amount'      => -100.00,
                'interval'    => 1,
                'start_date'  => '2025-02-13',
                'description' => null,
                'requires_contract' => false,
                'tags'        => ['Auto', 'Mobilität', 'Verbrauch', 'Tank'],
            ],
            [
                'group'       => 'Auto',
                'name'        => 'Steuer',
                'amount'      => -76.00,
                'interval'    => 12,
                'start_date'  => '2023-09-25',
                'description' => null,
                'requires_contract' => false,
                'tags'        => ['Auto', 'Behörde', 'Mobilität', 'Zoll'],
            ],
            [
                'group'       => 'Auto',
                'name'        => 'Autoversicherung',
                'amount'      => -727.58,
                'interval'    => 12,
                'start_date'  => '2023-09-25',
                'provider_company' => 'Allianz Generalvertretung Andreas Wiegand',
                'provider_street'  => 'Hauptstr. 19',
                'provider_zip'     => '38542',
                'provider_city'    => 'Leiferde',
                'provider_phone'   => '0 53 73.61 19',
                'provider_email'   => 'andreas2.wiegand@allianz.de',
                'provider_website' => 'www.allianz-wiegand.de',
                'contract_number'  => 'AS-9185063127',
                'description' => "Allianz Generalvertretung Andreas Wiegand. Faxnummer und weitere Kontaktdetails vorhanden.",
                'tags'        => ['Auto', 'Versicherung', 'Mobilität', 'Absicherung', 'Kfz', 'Allianz'],
                'contract_file_path' => 'buchhaltung/contracts/2023/09/2023-09-25_HUK-Coburg_Autoversicherung.pdf'
            ],

            // Unterhalt (Ausgaben = Negativ)
            [
                'group'       => 'Unterhalt',
                'name'        => 'Unterhalt Noah',
                'amount'      => -355.00,
                'interval'    => 1,
                'start_date'  => '2024-01-12',
                'description' => "Es wurde alles durch Lenas Rechtsanwältin tippe...",
                'requires_contract' => false,
                'tags'        => ['Unterhalt', 'Familie', 'Kind', 'Verpflichtung'],
            ],
        ];

        foreach ($items as $item) {
            AccountingCostItem::updateOrCreate(
                [
                    'accounting_group_id' => $createdGroups[$item['group']],
                    'name'                => $item['name'],
                ],
                [
                    'amount'             => $item['amount'],
                    'interval_months'    => $item['interval'],
                    'first_payment_date' => $item['start_date'] ?? null,
                    'last_payment_date'  => $item['last_date'] ?? null,
                    'description'        => $item['description'],
                    'is_business'        => $item['is_business'] ?? false,
                    'requires_contract'  => $item['requires_contract'] ?? true,
                    'tags'               => $item['tags'] ?? [],
                    'contract_file_path' => $item['contract_file_path'] ?? null,
                    'provider_company'      => $item['provider_company'] ?? null,
                    'provider_street'       => $item['provider_street'] ?? null,
                    'provider_house_number' => $item['provider_house_number'] ?? null,
                    'provider_zip'          => $item['provider_zip'] ?? null,
                    'provider_city'         => $item['provider_city'] ?? null,
                    'provider_phone'        => $item['provider_phone'] ?? null,
                    'provider_email'        => $item['provider_email'] ?? null,
                    'provider_website'      => $item['provider_website'] ?? null,
                    'contract_number'       => $item['contract_number'] ?? null,
                    'notice_period'         => $item['notice_period'] ?? null,
                    'contract_end_date'     => $item['contract_end_date'] ?? null,
                ]
            );
        }

        // --- 3. Sonderausgaben erstellen (Gewerblich) ---

        $specialIssues = [
            [
                'title'    => 'Plastikboxen',
                'location' => 'Zimmermann',
                'amount'   => -40,31,
                'date'     => '2026-06-15',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/06/2026-06-15_Zimmermann_Plastikboxen.pdf']
            ],
            [
                'title'    => 'Laserschutzschulung',
                'location' => 'Luminus Institut für Laserschutz und Arbeitssicherheit',
                'amount'   => -712,81,
                'date'     => '2026-04-23',
                'category' => 'Software & Lizenzen',
                'file_paths' => ['buchhaltung/receipts/2026/04/2026-04-23_Luminus-Institut_Laserschutzschulung.pdf']
            ],
            [
                'title'    => 'Lenovo Docking Station',
                'location' => 'AFB Social & Green IT',
                'amount'   => -84,90,
                'date'     => '2026-02-27',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/02/2026-02-27_AFB-Social-Green-IT_Lenovo-Docking-Station.pdf']
            ],
            [
                'title'    => 'Laserschutzbrille',
                'location' => 'Amazon',
                'amount'   => -48.88,
                'date'     => '2026-01-19',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-19_Amazon_Laserschutzbrille.pdf']
            ],
            [
                'title'    => 'Stiftehalter für den Büro Tisch',
                'location' => 'Amazon',
                'amount'   => -3.30,
                'date'     => '2026-01-09',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-09_Amazon_Stiftehalter.pdf']
            ],
            [
                'title'    => 'Mülleimer 50l',
                'location' => 'Amazon',
                'amount'   => -25.99,
                'date'     => '2026-01-08',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-08_Amazon_Muelleimer-50l.pdf']
            ],
            [
                'title'    => 'REV Verlängerungskabel - Verlängerung 3m',
                'location' => 'Amazon',
                'amount'   => -8.99,
                'date'     => '2026-01-06',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-06_Amazon_Verlaengerungskabel-3m.pdf']
            ],
            [
                'title'    => '2x Mehrfachsteckdose überspannungsschutz',
                'location' => 'Amazon',
                'amount'   => -27.58,
                'date'     => '2026-01-05',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-05_Amazon_2x-Mehrfachsteckdose.pdf']
            ],
            [
                'title'    => '2x 50l Mülleimer Pappe und Folie',
                'location' => 'Amazon',
                'amount'   => -52.18,
                'date'     => '2026-01-05',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-05_Amazon_2x-50l-Muelleimer.pdf']
            ],
            [
                'title'    => '2100LM 224LED Schreibtischlampe Led klemmbar',
                'location' => 'Amazon',
                'amount'   => -47.88,
                'date'     => '2026-01-03',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-03_Amazon_Schreibtischlampe-2100LM.pdf']
            ],
            [
                'title'    => '150stk Schlüsselanhänger Flaschenöffner Silber/Rot...',
                'location' => 'www.heavytool-shop.de/',
                'amount'   => -43.50,
                'date'     => '2026-01-03',
                'category' => 'Rohmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-03_Heavytool_150stk-Schluesselanhaenger.pdf']
            ],
            [
                'title'    => '240stk Kartons für Trophäe',
                'location' => 'Karton.eu',
                'amount'   => -171.36,
                'date'     => '2026-01-03',
                'category' => 'Verpackungen',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-03_Karton-eu_240stk-Kartons-fuer-Trophae.pdf']
            ],
            [
                'title'    => '400stk Luftpolster Versandtüten',
                'location' => 'Karton.eu',
                'amount'   => -68.02,
                'date'     => '2026-01-03',
                'category' => 'Verpackungen',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-03_Karton-eu_400stk-Luftpolster-Versandtueten.pdf']
            ],
            [
                'title'    => 'Luftdruck Reiniger',
                'location' => 'Amazon',
                'amount'   => -39.99,
                'date'     => '2026-01-02',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-02_Amazon_Luftdruck-Reiniger.pdf']
            ],
            [
                'title'    => 'Weiße Baumwollehandschuhe',
                'location' => 'Amazon',
                'amount'   => -9.90,
                'date'     => '2026-01-02',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-02_Amazon_Weisse-Baumwollhandschuhe.pdf']
            ],
            [
                'title'    => 'Waage',
                'location' => 'Amazon',
                'amount'   => -29.99,
                'date'     => '2026-01-02',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-02_Amazon_Waage.pdf']
            ],
            [
                'title'    => 'Isopropanol 6x 1 Liter',
                'location' => 'Amazon',
                'amount'   => -29.95,
                'date'     => '2026-01-02',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-02_Amazon_Isopropanol-6x1L.pdf']
            ],
            [
                'title'    => 'Feuerlöscher co2',
                'location' => 'Amazon',
                'amount'   => -56.80,
                'date'     => '2026-01-02',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-02_Amazon_Feuerloescher-CO2.pdf']
            ],
            [
                'title'    => 'Tisch',
                'location' => 'eBay Kleinanzeigen',
                'amount'   => -75.00,
                'date'     => '2026-01-01',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/01/2026-01-01_eBay-Kleinanzeigen_Tisch.pdf']
            ],
            [
                'title'    => 'Stuhl Räder',
                'location' => 'Amazon',
                'amount'   => -72.00,
                'date'     => '2025-12-31',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2025/12/2025-12-31_Amazon_Stuhl-Raeder.pdf']
            ],
            [
                'title'    => 'Warnaufkleber "WARNUNG Sichtbare und unsichtbare L...',
                'location' => 'Amazon',
                'amount'   => -4.99,
                'date'     => '2025-12-28',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2025/12/2025-12-28_Amazon_Warnaufkleber.pdf']
            ],
            [
                'title'    => 'Schwerlastregale',
                'location' => 'Amazon',
                'amount'   => -303.20,
                'date'     => '2025-12-27',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2025/12/2025-12-27_Amazon_Schwerlastregale.pdf']
            ],
            [
                'title'    => 'Fragile Glas Aufkleber, Testartikel, Klebeband etc...',
                'location' => 'Temu',
                'amount'   => -50.07,
                'date'     => '2025-12-28',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2025/12/2025-12-28_Temu_Fragile-Glas-Aufkleber.pdf']
            ],
            [
                'title'    => 'Etikettendrucker, Papierschneider, Abroller und Dr...',
                'location' => 'Amazon',
                'amount'   => -192.31,
                'date'     => '2025-12-27',
                'category' => 'Arbeitsmaterial',
                'file_paths' => [
                    'buchhaltung/receipts/2025/12/2025-12-27_Amazon_Etikettendrucker-Papierschneider-Abroller.pdf',
                    'buchhaltung/receipts/2025/12/2025-12-27_Amazon_Papierschneider.pdf',
                    'buchhaltung/receipts/2025/12/2025-12-27_Amazon_Handroller-Abroller.pdf'
                ]
            ],
        ];

        foreach ($specialIssues as $issue) {
            AccountingSpecialIssue::create([
                'admin_id'       => $admin->id,
                'title'          => $issue['title'],
                'location'       => $issue['location'],
                'category'       => $issue['category'],
                'amount'         => $issue['amount'],
                'execution_date' => $issue['date'],
                'is_business'    => true,
                'file_paths'     => $issue['file_paths'] ?? null,
            ]);
        }
    }
}

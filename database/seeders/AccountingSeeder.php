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
            'Werbung & Marketing',
            'Beratung & Gründung'
        ];

        $businessNames = [
            'Arbeitsmaterial',
            'Wareneinkauf',
            'Rohmaterial',
            'Verpackungen',
            'Software & Lizenzen',
            'Werbung & Marketing',
            'Beratung & Gründung'
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

        AccountingCostItem::whereHas('group', fn($q) => $q->where('admin_id', $admin->id))->delete();
        AccountingGroup::where('admin_id', $admin->id)->delete();
        AccountingSpecialIssue::where('admin_id', $admin->id)->delete();

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
                'group'                 => 'Einnahmen',
                'name'                  => 'ALG 1 / Gründerzuschuss',
                'amount'                => 1806.30,
                'interval'              => 1, // monthly
                'start_date'            => '2026-01-30',
                'provider_company'      => 'Agentur für Arbeit Gifhorn',
                'provider_street'       => 'Winkeler Straße',
                'provider_house_number' => '1',
                'provider_zip'          => '38518',
                'provider_city'         => 'Gifhorn',
                'provider_phone'        => '0800 4 5555-00',
                'contract_number'       => '241D129534',
                'description'           => "Ansprechpartnerin: Frau Grandke | Telefon Alternativ: +49 5351 522 888\nÖffnungszeiten: Mo – Do: 08:00 – 18:00, Fr: 08:00 – 14:00\nAb 01.11.2026 Übergang in Gründungszuschuss Phase 1 (§ 93 SGB III): ALG-1-Anteil (1.806,30 €) + monatliche Pauschale zur sozialen Absicherung (300,00 € = 2.106,30 €) für 6 Monate vom 01.11.2026 bis 30.04.2027 (Bewilligungsbescheid folgt).",
                'tags'                  => ['Einnahme', 'Staat', 'Förderung', 'Einkommen', 'Gründungszuschuss', 'Agentur für Arbeit'],
                'contract_file_path'    => 'buchhaltung/contracts/2026/01/2026-01-30_Staat_ALG-1.pdf'
            ],
            [
                'group'       => 'Einnahmen',
                'name'        => 'Miete',
                'amount'      => 595.00,
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
            [
                'group'                 => 'Einnahmen',
                'name'                  => 'Internet Mieterin',
                'amount'                => 25.00,
                'interval'              => 1,
                'start_date'            => '2026-09-01',
                'provider_company'      => 'Heike Janny Keller',
                'provider_street'       => 'Carl-Goerdeler-Ring',
                'provider_house_number' => '26',
                'provider_zip'          => '38518',
                'provider_city'         => 'Gifhorn',
                'contract_number'       => 'Zusatzvereinbarung-002',
                'notice_period'         => 'Endet automatisch mit Beendigung des Hauptmietverhältnisses (3 Monate)',
                'description'           => "Zusatzvereinbarung zum Mietvertrag über die Nutzung des Internetzugangs (WLAN / LAN)\nAnschlussinhaberin / Vermieterin: Alina Steinhauer, Carl-Goerdeler-Ring 26, 38518 Gifhorn\nMitbenutzerin / Mieterin: Frau Heike Janny Keller\nKostenbeteiligung (§ 2): 25,00 € monatliche Pauschale im Voraus, spätestens zum 1. eines jeden Monats fällig (als separate Überweisung auf das bekannte Konto der Anschlussinhaberin)\nLaufzeit (§ 4): Beginn am 01.09.2026 (unbestimmte Zeit, endet automatisch mit Beendigung des Hauptmietverhältnisses)\nUnterzeichnet am: 31.07.2026 in Gifhorn",
                'tags'                  => ['Einnahme', 'Internet', 'Mieterin', 'Nebenkosten'],
                'contract_file_path'    => 'buchhaltung/contracts/2026/09/2026-09-01_Mieterin-Keller_Internet-Zusatzvereinbarung.pdf'
            ],

            // Haus (Ausgaben = Negativ)
            [
                'group'       => 'Haus',
                'name'        => 'Wasser',
                'amount'      => -40.00,
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
            /*[
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
            ],*/
            [
                'group'       => 'Versicherungen',
                'name'        => 'Haftpflicht',
                'amount'      => -44.48,
                'interval'    => 12,
                'start_date'  => '2026-12-15',
                'provider_company' => 'AXA Versicherung AG',
                'provider_street'  => 'Colonia-Allee',
                'provider_house_number' => '10-20',
                'provider_zip'     => '51067',
                'provider_city'    => 'Köln',
                'provider_phone'   => '0800 3203205',
                'provider_email'   => 'service@axa.de',
                'provider_website' => 'https://www.axa.de',
                'contract_number'  => '56004546918',
                'notice_period'    => 'Jederzeit zum nächsten Tag kündbar',
                'description' => "Tarif: L mit Bestleistungs-Garantie\nDeckungssumme: 60 Mio. Euro\nSelbstbeteiligung: 0 Euro\nVersicherungsnehmerin: Alina Steinhauer\nVersicherungsnummer (Haftpflicht Online Nr.): 56004546918\nAntragsnummer bei CHECK24: PHV-15034785\nBetreuung: CHECK24 Vergleichsportal Sachvers. GmbH, Speicherstr. 55, 60327 Frankfurt (Tel.: 089/24241246, E-Mail: phv@check24.de)\nVersichert ab: 15.12.2026\nVorversicherung: Grundeigentümer-Versicherung (GEV) gekündigt zum 15.12.2026",
                'tags'        => ['Versicherung', 'Vorsorge', 'Absicherung', 'AXA', 'CHECK24', 'Haftpflicht'],
                'contract_file_path' => 'buchhaltung/contracts/2026/10/2026-10-19_AXA_Haftpflicht.pdf'
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
                'group'             => 'Versicherungen',
                'name'              => 'Betriebshaftpflicht',
                'amount'            => -100.14,
                'interval'          => 12, // yearly
                'start_date'        => '2026-11-01',
                'contract_end_date' => '2027-10-31',
                'is_business'       => 1,
                'provider_company'  => 'Hiscox SA Niederlassung für Deutschland',
                'provider_street'   => 'Bernhard-Wicki-Str.',
                'provider_house_number' => '3',
                'provider_zip'      => '80636',
                'provider_city'     => 'München',
                'provider_phone'    => '+49 (0)89 54 58 01 700',
                'provider_email'    => 'myhiscox@hiscox.de',
                'provider_website'  => 'https://www.hiscox.de',
                'contract_number'   => 'PL-PSC700408140/00',
                'notice_period'     => '1 Monat zum Ablauf des Versicherungsjahres (automatische Verlängerung um 12 Monate)',
                'description'       => "Tarif: Betriebshaftpflichtversicherung Onlinehandel (inkl. Produkthaftung / Quasi-Hersteller, Passiver Rechtsschutz, Schutz bei Verletzung geistigen Eigentums / Markenrechte bis 25.000 €, Hiscox Business Academy)\nVersicherungssumme: 5.000.000 € | Selbstbeteiligung Sachschäden: 100 € | Start-up-Rabatt: 15 % inklusive\nVersicherungsnehmerin: Frau Alina Steinhauer, Carl-Goerdeler-Ring 26, 38518 Gifhorn\nVersicherungsschein-Nr.: PL-PSC700408140/00 | Rechnungsnr. (Erstprämie): PL-PSC700408140/00/01112026/0990738 (01.11.2026)\nLaufzeit: 01.11.2026 (0:00 Uhr) – 31.10.2027 (24:00 Uhr)\nBeitrag (jährlich): 84,15 € netto zzgl. 15,99 € Versicherungssteuer (19 % VersSt, umsatzsteuerbefreit nach § 4 Nr. 10 u. 11 UStG) = 100,14 € brutto\nZahlungsart: SEPA-Bankeinzug jährlich (Abbuchung Erstprämie am 22.11.2026)\nHandelsregister: Amtsgericht München HRB 238125 (Hauptsitz: Hiscox SA, 35 Avenue Monterey, L-2163 Luxemburg, R.C.S. B217018) | Hauptbevollmächtigter: Tim Bethge | Fax: +49 (0)89 54 58 01 799\nUSt-IdNr.: DE320201626 | VersSt-Nr.: 802/V20000024429",
                'tags'              => ['Versicherung', 'Gewerbe', 'Absicherung', 'Pflicht', 'Hiscox', 'Betriebshaftpflicht', 'Produkthaftung'],
                'contract_file_path' => 'buchhaltung/contracts/2026/11/2026-11-01_Hiscox_Betriebshaftpflicht.pdf'
            ],
            [
                'group'       => 'Versicherungen',
                'name'        => 'Zahnzusatzversicherung',
                'amount'      => -12.14,
                'interval'    => 1,
                'start_date'  => '2026-12-01',
                'provider_company' => 'AXA Krankenversicherung AG',
                'provider_street'  => 'Colonia-Allee',
                'provider_house_number' => '10-20',
                'provider_zip'     => '51067',
                'provider_city'    => 'Köln',
                'provider_phone'   => '0221 148-41002',
                'provider_email'   => 'service@axa.de',
                'provider_website' => 'https://www.axa.de',
                'contract_number'  => '4942640485',
                'notice_period'    => 'Zum Ablauf des Versicherungsjahres',
                'description' => "Tarif: Zahn Klassik (Vertragsgrundlage 13, 556)\nLeistungen: 80% Zahnersatz, 100% Zahnbehandlung, 100% prof. Zahnreinigung 1x/Jahr, 80% Kieferorthopädie bis 5.000 Euro\nVersicherungsnehmerin / Versicherte Person: Alina Steinhauer (geb. 01.09.1993)\nKranken-/Pflege-Versicherung Nr.: 4942640485 (Unsere Zeichen: /T935750)\nVersicherungsbeginn / Fälligkeit: ab 01.12.2026 (monatlich zum 01.)\nBetreuung / Vermittler: CHECK24 Vergleichsportal Krankenvers. GmbH, An der Alster 64, 20099 Hamburg (Tel.: 089/24241277, E-Mail: zahn@check24.de)\nSEPA-Lastschrift: Volksbank BRAWO (IBAN DE85xxxxxxxxxxxxxx6000, BIC GENODEF1WOB)\nMandatsnummer: MDT 21060234274 | Gläubigernummer: DE23G0100000066097\nPostanschrift: 50592 Köln | HRB 1012 Köln | USt-IdNr.: DE 122786679 | VersSt-Nr.: 810/V90810030208\nVorversicherung: Tecis (Vertragsnr. AK - 6099415323, zuvor mtl. -23,58 €)",
                'tags'        => ['Versicherung', 'Gesundheit', 'Vorsorge', 'AXA', 'CHECK24', 'Zahnzusatz'],
                'contract_file_path' => 'buchhaltung/contracts/2026/10/2026-10-26_AXA_Zahnzusatzversicherung.pdf'
            ],
            [
                'group'       => 'Versicherungen',
                'name'        => 'Arbeitslosenversicherung',
                'amount'      => 0,
                'interval'    => 1, // monthly
                'start_date'  => '2026-11-01',
                'is_business' => false,
                'provider_company' => 'Agentur für Arbeit Gifhorn',
                'provider_street'  => 'Winkeler Straße',
                'provider_house_number' => '1',
                'provider_zip'     => '38518',
                'provider_city'    => 'Gifhorn',
                'provider_email'   => 'K/A',
                'provider_phone'   => '0800 4 5555-00',
                'contract_number'  => '241D129534',
                'notice_period'    => 'Nach 5 Jahren jederzeit mit 3 Monaten Frist (oder bei Aufgabe der Selbstständigkeit)',
                'description' => "Freiwillige Weiterversicherung gegen Arbeitslosigkeit (§ 28a SGB III). Steuerlich private Vorsorgeaufwendung / Sonderausgabe (keine Betriebsausgabe).\nStatus: Wartet auf Bewilligungsbescheid des Gründungszuschusses (3-Monats-Antragsfrist ab Gründung läuft bis 31.01.2027; Startbeitrag 50 % in den ersten 24 Monaten ca. 49,35 €/Monat).",
                'tags'        => ['Versicherung', 'Privat', 'Vorsorge', 'Absicherung', 'Agentur für Arbeit'],
            ],

            // Vertrag & Lizenz (Ausgaben = Negativ)

            [
                'group'                 => 'Vertrag & Lizenz',
                'name'                  => 'Glasfaser',
                'amount'                => -39.94,
                'interval'              => 1,
                'start_date'            => '2026-10-29',
                'provider_company'      => 'Telekom Deutschland GmbH',
                'provider_street'       => 'Landgrabenweg',
                'provider_house_number' => '151',
                'provider_zip'          => '53227',
                'provider_city'         => 'Bonn',
                'provider_phone'        => '0800 22 66 100',
                'provider_website'      => 'https://www.telekom.de',
                'contract_number'       => '2752557615 / VK 5635166558',
                'description'           => "Status: Aktuell Telekom DSL-Zwischenvertrag (Übergangstarif -39,94 € mtl. bis zur endgültigen Glasfaser-Bereitstellung)\nKundennummer: 2752557615 | Festnetz Vertragskonto: 5635166558 | Auftragsnummer: 260317-PSM-KJ5N4C (Schreiben vom 17.03.2026)\nAnschlussinhaberin / Gebäudedaten: Alina Steinhauer, Carl-Goerdeler-Ring 26, 38518 Gifhorn (Tel.: 015901966864, E-Mail: alina.stone@t-online.de)\nSEPA-Lastschrift (Abbuchung 29.10.): RG 7366743769/20.10.2026 | EREF: Zahlbeleg 318203144786\nMandatsreferenz (MREF): DE000201000500000000000000011548255 | Gläubiger-ID (CRED): DE93ZZZ00000078611\nBankverbindung Telekom: IBAN DE68700202700667302269 | BIC: HYVEDEMM\nPostanschrift: 53171 Bonn | Amtsgericht Bonn HRB 5919 | USt-IdNr.: DE122265872 | WEEE-Reg.-Nr.: DE60800328\nVorvertrag: Deutsche Glasfaser (DG Classic 400, -49,99 € mtl., per Anbieterwechselvollmacht gekündigt)",
                'tags'                  => ['Vertrag', 'Internet', 'Kommunikation', 'Wohnen', 'Telekom', 'DSL', 'Glasfaser'],
                'contract_file_path'    => 'buchhaltung/contracts/2026/03/2026-03-17_Telekom_Glasfaser-DSL-Zwischenvertrag.pdf'
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
                'amount'      => -48,79,
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
                'name'        => 'Google AI Pro',
                'amount'      => -21,99,
                'interval'    => 1,
                'start_date'  => '2026-10-25',
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
/*            [
                'group'       => 'Auto',
                'name'        => 'Benzin',
                'amount'      => -100.00,
                'interval'    => 1,
                'start_date'  => '2025-02-13',
                'description' => null,
                'requires_contract' => false,
                'tags'        => ['Auto', 'Mobilität', 'Verbrauch', 'Tank'],
            ],*/
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
                'amount'      => -387.23,
                'interval'    => 12,
                'start_date'  => '2026-08-11',
                'contract_end_date' => '2027-08-11',
                'notice_period' => 'Automatische Verlängerung um 12 Monate',
                'provider_company' => 'ADAC Autoversicherung AG',
                'provider_street'  => 'Hansastraße',
                'provider_house_number' => '19',
                'provider_zip'     => '80686',
                'provider_city'    => 'München',
                'provider_phone'   => null,
                'provider_email'   => 'kfz-beratung@check24.de',
                'provider_website' => 'www.adac.de',
                'contract_number'  => '1437-2545-2110-61',
                'description' => "Ihr Fahrzeug:\nName / Halter: Frau Alina Steinhauer\nAnschrift: Carl-Goerdeler-Ring 26, 38518 Gifhorn\nKennzeichen: GF-HS 232\nFIN: WVWZZZ1KZBP176602\nKilometerstand: 101.000 km\n\nVersicherungsdetails (ADAC Basis mit Werkstattbonus):\nVersicherungsbeginn: 11.08.2026\nAblauf: 11.08.2027 (automatische Verlängerung um 12 Monate)\nVorversicherung: Allianz (Vertragsnr. as-9185063127)\nAntrag-Referenz: 1437-2545-2110-61 (CHECK24)\nZahlungsart: SEPA-Lastschrift (ADAC Autoversicherung AG, Hansastraße 19, 80686 München)\nBeitrag Haftpflicht: 301,59 € jährlich\nBeitrag Kasko: 85,64 € jährlich\nGesamtbeitrag (inkl. Versicherungssteuer): 387,23 € jährlich\nCashback von CHECK24: -50,00 € einmalig\nSF-Klasse Haftpflicht: SF 31 (angegeben & tatsächlich)\nSelbstbeteiligung Teilkasko: 150 €\nBesonderheiten der Selbstbeteiligung: Senkung um 100% bei Reparatur statt Austausch der Frontscheibe in einer Partnerwerkstatt; Erhöhung um 20% der Reparaturkosten bei Reparatur in einer anderen Werkstatt als einer Partnerwerkstatt\nEinschränkungen: keine\nVertragsunterlagen: online\nNotiz: läuft über meine Mama (Kerstin)",
                'tags'        => ['Auto', 'Versicherung', 'Mobilität', 'Absicherung', 'Kfz', 'ADAC', 'Haftpflicht', 'Kasko'],
                'contract_file_path' => 'buchhaltung/contracts/2026/08/2026-08-11_ADAC_Autoversicherung.pdf'
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
                'title'          => 'Schaumstoff Meterware Schwarz (Dicke 2cm, 250cm)',
                'location'       => 'www.schaumstoffonline.de (Schaumstoffonline DE)',
                'amount'         => -44.38,
                'date'           => '2026-11-05',
                'category'       => 'Verpackungen',
                'tax_rate'       => 19,
                'invoice_number' => 'SO23591',
                'note'           => "Quittung / Steuerrechnung: #SO23591 vom 05.11.2026 | Bestellt am: 05.11.2026 (13:55 Uhr) | Status: Ganz bezahlt (44,38 €)\nPosition:\n- 1x Schaumstoff Meterware Schwarz Dicke 2cm, 250cm (Artikelnummer: schaumstoff-meterware-schwarz-2cm-dick-250cm, 19,0% Steuern): 34,43 € brutto (28,93 € netto)\nZwischensumme: 34,43 € brutto | Versand (Standardversand DPD / Hermes): 9,95 € brutto (8,36 € netto) | Gesamt (Netto): 37,29 € | DE MwSt. (19,0%): 7,09 € | Gesamt (Brutto): 44,38 €\nHändler: Schaumstoffonline DE, Steenplaetsstraat 7, 2288 AA Rijswijk, Netherlands, USt-IdNr.: NL858268632B01 (858268632B01), HRB: 70332754, IBAN: NL37KNAB0256916543, E-Mail: mail@schaumstoffonline.de, Web: www.schaumstoffonline.de",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/11/2026-11-05_Schaumstoffonline_Schaumstoff-Meterware-Schwarz-2cm-250cm-Rechnung-SO23591.pdf'
                ]
            ],
            [
                'title'          => 'MUNBYN AirPrint Etikettendrucker Wi-Fi WLAN Labeldrucker 4x6 ITPP941AP (Weiß, 203 DPI Thermodrucker)',
                'location'       => 'Amazon (MUNBYNUK)',
                'amount'         => -229.99,
                'date'           => '2026-11-05',
                'category'       => 'Arbeitsmaterial',
                'tax_rate'       => 19,
                'invoice_number' => '303-8737484-6302724',
                'note'           => "Bestellnummer: 303-8737484-6302724 | Bestellt am: 05.11.2026 (11:04 Uhr) | Zahlungsart: Volksbank eG Braunschweig Wolfsburg ••• 000\nPosition:\n- 1x MUNBYN AirPrint Etikettendrucker Wi-Fi WLAN Labeldrucker 4x6 ITPP941AP Weiß | 203DPI Thermodrucker WLAN, Etiketten Thermal Printer für Versandetiketten, kompatibel mit Shopify, DHL, DPD, FedEx (Verkauf durch: MUNBYNUK): 229,99 € brutto\nZwischensumme / Gesamt vor USt. (Netto): 193,27 € | Verpackung & Versand: 0,00 € | Geschätzte USt. (19%): 36,72 € | Gesamtsumme (Brutto): 229,99 €\nSupport & Herstellerkontakt (MUNBYN):\n- Tel: +1 628 229 3612 (8:00-17:00 Uhr PT)\n- WhatsApp: +86 17817881067 / +86 181 4489 8200 (Werktags - 24 Stunden)\n- E-Mail: support@munbyn.com\nBestellübersicht-Link: https://www.amazon.de/gp/css/summary/print.html?orderID=303-8737484-6302724&ref=ppx_yo2ov_dt_b_invoice",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/11/2026-11-05_Amazon-MUNBYNUK_MUNBYN-AirPrint-Etikettendrucker-WiFi-WLAN-ITPP941AP-Order-303-8737484-6302724.pdf'
                ]
            ],
            [
                'title'          => '20x Schwerlastkarton 600x400x400 mm (069), 20x Schwerlastkarton 600x400x200 mm (165), 140x Bundle-Karton 300x230x150 mm (2633) & 36x Leise abrollendes BOPP-Klebeband braun (128)',
                'location'       => 'www.karton.eu (IPS Karton.eu GmbH & Co. KG)',
                'amount'         => -273.27,
                'date'           => '2026-11-03',
                'category'       => 'Verpackungen',
                'tax_rate'       => 19,
                'invoice_number' => '26169398 (Bestellnr. B2075689)',
                'note'           => "Rechnungsnr.: 26169398 vom 03.11.2026 | Bestellnr.: B2075689 | Auftragsnr.: A2416277 | Kundennr.: K523045 | Zahlungsart: PayPal Checkout (Transaktionscode: 7SN65368SR6734133, Volksbank eG Braunschweig Wolfsburg x-6000)\nPositionen (Netto-Einzelpreise zzgl. 19% MwSt., Gesamtgewicht: 67,40 kg):\n- Pos. 1: 20 Stück 600x400x400 mm zweiwellige Kartons (Außenmaß, BC-Welle bis 30 kg, Art.-Nr.: 069, E-Gew.: 1,04 kg / G-Gew.: 20,80 kg): 3,24 € netto/Stk. (3,86 € brutto) = 64,80 € netto\n- Pos. 2: 36 Rollen Leise abrollendes BOPP-Klebeband braun (Art.-Nr.: 128, E-Gew.: 0,17 kg / G-Gew.: 6,12 kg): 1,09 € netto/Rolle (1,30 € brutto) = 39,24 € netto\n- Pos. 3: 20 Stück 600x400x200 mm zweiwellige Kartons (Außenmaß, BC-Welle bis 30 kg, Art.-Nr.: 165, E-Gew.: 0,75 kg / G-Gew.: 15,00 kg): 2,78 € netto/Stk. (3,31 € brutto) = 55,60 € netto\n- Pos. 4: 140 Stück 300x230x150 mm einwellige Kartons (Universal-/Bundle- & Weizenglas-Karton, Art.-Nr.: 2633, E-Gew.: 0,18 kg / G-Gew.: 25,48 kg): 0,50 € netto/Stk. (0,60 € brutto) = 70,00 € netto\n- Pos. 5–7: Paketversand (0,00 €), Jahres-Wandkalender \"karton.eu\" (0,00 €), Katalog karton.eu (0,00 €)\nGesamt (Netto): 229,64 € | zzgl. 19% MwSt.: 43,63 € | Rechnungsbetrag (Brutto): 273,27 €\nHändler: IPS Karton.eu GmbH & Co. KG, Tuchmacherallee 6, 03130 Spremberg, AG Cottbus HRA 2809 CB, St.-Nr.: 056/156/00190, USt-IdNr.: DE288964141, E-Mail: info@karton.eu\nProdukt-Links:\n- https://www.karton.eu/?qs=600x400x400\n- https://www.karton.eu/600x400x200-mm-zweiwellige-Kartons-Aussenmass\n- https://www.karton.eu/300x230x150-mm-einwellige-Kartons\n- https://www.karton.eu/?qs=128",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/11/2026-11-03_Karton-eu_Rechnung-26169398-Bestellung-B2075689.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-03_Karton-eu_20x-069-20x-165-140x-2633-36x-Klebeband-128-PayPal-Order-B2075689.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-03_Karton-eu_Bestellbestaetigung-Order-B2075689.png',
                    'buchhaltung/receipts/2026/11/2026-11-03_Karton-eu_Warenkorb-Order-B2075689.png'
                ]
            ],
            [
                'title'          => '30x Flache Schlüsselringe Gold (3 Größen), 100x Edelstahl-Spaltringe Klein Gold (6–12mm) & LEONTOOL Mini-Spaltringzange (10,2 cm)',
                'location'       => 'Amazon (Hong Kong Tsun Yew Limited / railing / LEONTOOL EU)',
                'amount'         => -16.67,
                'date'           => '2026-11-03',
                'category'       => 'Rohmaterial',
                'tax_rate'       => 19,
                'invoice_number' => '028-1563466-9621923 (Teilrechnung TVtCBC8ZD-1793755138)',
                'note'           => "Bestellnummer: 028-1563466-9621923 | Bestellt am: 03.11.2026 (03:47 / 03:50 Uhr) | Zahlungsart: Volksbank eG Braunschweig Wolfsburg •••• 000\nPositionen (inkl. 19% USt.):\n- Pos. 1 (Teilrechnung 1/3: Nr. TVtCBC8ZD-1793755138 vom 04.11.2026): 1x JeoPoom Schlüsselring [30 Stück], Flache Schlüsselanhänger Ring, 3 verschiedene Größen (je 10x 20mm, 25mm, 30mm, Golden, ASIN: B093R9HS4Y, Verkäufer: Hong Kong Tsun Yew Limited, H22, ROOM 1017, 10/F, Wing Hing Industrial Building, No. 83-93 Chai Wan Kok St, Tsuen Wan, Hong Kong, CN, USt-IdNr.: –): 4,99 € brutto (4,19 € netto zzgl. 0,80 € USt. 19% / 0,14 € netto pro Stück) – Bezahlt\n- Pos. 2: 1x Nogeqi 100 Stück Schlüsselringe Klein, 6/8/10/12mm Edelstahl Biegeringe / Spaltringe Gold (ASIN: B0H6FG92JY, Verkauf durch: railing): 4,99 € brutto (4,19 € netto / 0,04 € netto pro Stück)\n- Pos. 3: 1x LEONTOOL Mini-Spaltringzange für Schmuckherstellung, 10,2 cm, Ringöffnungszange / Spaltringöffner (ASIN: B0C2TCMBSG, Verkauf durch: LEONTOOL EU): 6,69 € brutto (5,62 € netto)\nVerpackung & Versand: 0,00 € | Gesamtsumme: 16,67 € brutto (14,01 € netto zzgl. 2,66 € USt. 19%)\nProdukt-Links:\n- https://www.amazon.de/dp/B093R9HS4Y\n- https://www.amazon.de/dp/B0H6FG92JY\n- https://www.amazon.de/dp/B0C2TCMBSG",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/11/2026-11-04_Amazon-TsunYew_30x-Flache-Schluesselringe-Gold-Rechnung-TVtCBC8ZD-1793755138-Order-028-1563466-9621923.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-03_Amazon_30x-Flache-Schluesselringe-100x-Edelstahl-Spaltringe-Gold-Mini-Spaltringzange-Order-028-1563466-9621923.pdf'
                ]
            ],
            [
                'title'          => '3x 10er Set Edelstahl-Hundeanhänger Knochenform mit Pfoten-Durchbruch (30 Stk., Gold)',
                'location'       => 'Temu (Wuxi Qiyue Industry and Trade Co., Ltd)',
                'amount'         => -28.59,
                'date'           => '2026-11-03',
                'category'       => 'Rohmaterial',
                'tax_rate'       => 19,
                'invoice_number' => 'PO-076-04964841144950774',
                'note'           => "Bestellnummer: PO-076-04964841144950774 | Bestellzeit & Bezahlt am: 03.11.2026 via PayPal (a***e@t-online.de) | Zustellung bis: 18.11.2026\nPosition:\n- 3x 10 leere Edelstahl-Hundeanhänger mit hohlen Pfotenmustern für Haustiere (Variante: Golden / Einheitsgröße, 30 Stück gesamt) à 8,34 € = 25,02 €\nVersand: KOSTENLOS (0,00 €) | Einfuhrabgaben: 3,57 €\nGesamtsumme: 28,59 € brutto (24,03 € netto zzgl. 4,56 € USt. 19% | Stückpreis: 0,80 € netto / 0,95 € brutto)\nVerkäufer: CTHOLD | Wuxi Qiyue Industry and Trade Co., Ltd (via Temu)\nProdukt-Link: https://www.temu.com/goods.html?_bg_fs=1&goods_id=601099544143627&parent_order_sn=PO-076-04964841144950774&_oak_order_sn=076-04964799201910774&_oak_goods_num=3&_x_sessn_id=qexm6jf56e&refer_page_name=bgt_order_receipt&refer_page_id=10471_1793672371535_hlekpk8i9o&refer_page_sn=10471&is_back=1",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/11/2026-11-03_Temu_30x-Edelstahl-Hundeanhaenger-Knochen-Pfote-Gold-Order-PO-076-04964841144950774.pdf'
                ]
            ],
            [
                'title'          => '10x Edelstahl-Ankerkette (44+6 cm, 1,5 mm, Ion-Plating) & 5x Edelstahl-Herzanhänger mit Collierschlaufe (14k vergoldet, 25x22x4 mm)',
                'location'       => 'www.gluecksfieber.de',
                'amount'         => -45.39,
                'date'           => '2026-11-03',
                'category'       => 'Rohmaterial',
                'tax_rate'       => 19,
                'invoice_number' => 'RE2613436 (Bestellung 325015)',
                'note'           => "Rechnung Nr. RE2613436 vom 03.11.2026 | Bestellnummer: 325015 | Kundennr.: 252590 | PayPal-Transaktionscode: 29A72875SB338471W (Volksbank eG Braunschweig Wolfsburg x-6000)\nPositionen (inkl. 19% MwSt.):\n- Pos. 1: 5x Edelstahl Laserrohling Anhänger, Herz, mit Collierschlaufe, 14 Karat vergoldet, 25 x 22 x 4 mm, Öse: 3 x 6 mm, Ion Plating (IP) (Art. Nr. STA1274): Einzelpreis 2,40 € brutto (2,02 € netto) = 12,00 € brutto\n- Pos. 2: 10x Edelstahl Gliederkette mit Karabinerverschluss, Ankerkette, goldfarben, Länge 44 cm + 6 cm Verlängerungskettchen, Kettenglieder 1,5 mm, Ion Plating (IP) (Art. Nr. STA268): Einzelpreis 2,84 € brutto (2,39 € netto) = 28,40 € brutto\n- Pos. 3: 1x Porto & Verpackung (Art. Nr. P / DHL): 4,99 € brutto (4,19 € netto)\nWarenwert Gesamt (netto): 38,14 € | MwSt. 19%: 7,25 € | Rechnungsbetrag (brutto): 45,39 €\nHändler: Glücksfieber-Perlen, Stefan Spreier & Sandra Schmidt GbR, Stockholmring 8, 23560 Lübeck, Steuer-Nr. 22 285 50300, USt-IdNr.: DE246566105, Tel.: +49 451 4799850, E-Mail: kundenservice@gluecksfieber.de\nProdukt-Links:\n- https://www.gluecksfieber.de/shop/edelstahl/edelstahl-gliederketten/edelstahl-gliederkette-mit-karabinerverschluss-ankerkette-goldfarben-laenge-44-cm-6-cm-verlaengerungskettchen-kettenglieder-1-5-mm-ion-plating-ip.html\n- https://www.gluecksfieber.de/shop/laserrohlinge/edelstahl-laserrohling-anhaenger-herz-mit-collierschlaufe-14-karat-vergoldet-25-x-22-x-4-mm-oese-3-x-6-mm-ion-plating-ip.html",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/11/2026-11-03_Gluecksfieber_Rechnung-RE2613436-Bestellung-325015.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-03_Gluecksfieber_Rechnung-RE2613436-Bestellung-325015.xml',
                    'buchhaltung/receipts/2026/11/2026-11-03_Gluecksfieber_10x-Edelstahl-Ankerkette-STA268-5x-Herz-Anhaenger-STA1274-Order-325015.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-03_Gluecksfieber_10x-Edelstahl-Ankerkette-STA268-5x-Herz-Anhaenger-STA1274-Order-325015.png'
                ]
            ],
            [
                'title'          => '10x KADAX Werkstatt-Stapelkiste / Sichtlagerbox aus PP Kunststoff (Schwarz, 290x200mm)',
                'location'       => 'Amazon (ALEKSY LISOWSKI NETFOX)',
                'amount'         => -34.99,
                'date'           => '2026-11-02',
                'category'       => 'Arbeitsmaterial',
                'tax_rate'       => 19,
                'invoice_number' => 'PL60984198MOVU',
                'note'           => "Bestellnummer: 303-2735132-0737928 | Rechnungsnummer: PL60984198MOVU | Zahlungsreferenz: 6U2BRNRIQFC89AK7 | Rechnungs-/Lieferdatum: 02.11.2026\nPosition:\n- 1x (Set à 10 Stück) KADAX Werkstatt-Stapelkiste, Stapelbox aus PP Kunststoff, Sichtlagerbox in verschiedenen Größen, Lagerboxen für Werkzeugwand, Sichtlagerkasten, Sortierbox (Schwarz 10 Stück, 290x200mm, ASIN: B0BSQZ447T)\nBetrag: 29,40 € netto + 5,59 € USt. (19% DE via EU-OSS) = 34,99 € brutto (Versand: 0,00 €)\nVerkäufer: ALEKSY LISOWSKI NETFOX, Długosza 2, 32-410 Dobczyce, Małopolska, Polen, USt-IdNr.: PL6811896571\nProdukt-Link: https://www.amazon.de/dp/B0BSQZ447T",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/11/2026-11-02_Amazon_10x-KADAX-Werkstatt-Stapelkiste-290x200mm-Rechnung-PL60984198MOVU.pdf'
                ]
            ],
            [
                'title'          => 'Untertischabroller 50cm mit glattem Messer, Abroller für Papier',
                'location'       => 'eBay (xtdriver)',
                'amount'         => -34.20,
                'date'           => '2026-11-02',
                'category'       => 'Arbeitsmaterial',
                'tax_rate'       => 0,
                'invoice_number' => '17-15363-69154',
                'note'           => "Bestellnummer: 17-15363-69154 | eBay-Artikelnummer: 206541588475 | Bestellt & bezahlt am: 02.11.2026\nPosition:\n- 1x Untertischabroller 50cm mit glattem Messer, Abroller für Papier: 29,00 €\n- Versand (DHL Päckchen): 5,20 €\nGesamtbetrag: 34,20 € (ohne gesonderten USt.-Ausweis, 0% Vorsteuer)\nZahlungsmethode: PayPal (Käufer-Account: dexter199315, Lieferadresse: Alina Steinhauer, Carl-Goerdeler-Ring 26, 38518 Gifhorn)\nVerkäufer: xtdriver (eBay: https://www.ebay.de/usr/xtdriver)",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/11/2026-11-02_eBay-xtdriver_Untertischabroller-50cm-Order-17-15363-69154.pdf'
                ]
            ],
            [
                'title'          => '96x Frankfurt ECO Schmuckschachtel Anhänger/Armreif (86 x 86 x 17 mm, Schwarz matt inkl. Logo-Druck & Klischee)',
                'location'       => 'www.westpack.com (Westpack A/S)',
                'amount'         => -145.95,
                'date'           => '2026-11-02',
                'category'       => 'Verpackungen',
                'tax_rate'       => 25,
                'invoice_number' => '29000069521 (Klischee L247401 / Kd.-Nr. 7164015)',
                'note'           => "Bestellung Order #29000069521 vom 02.11.2026 (Order-ID: 485928) | Kundennummer: 7164015 | Westpack Klischee-Nr.: L247401\nLogokorrektur / Proof vom 03.11.2026 (Azizah Fitria Nada Zahra, afz@westpack.com): Logo \"mein-seelenfunke – EIN FUNKE, DER BLEIBT\" (60 x h11,4 mm mittig auf Schachteldeckel 86 x 86 x 17 mm)\nPositionen:\n- 96x Frankfurt ECO Schmuckschachtel Anhänger/Armreif (Art.-Nr.: 30286060102, Schwarz matt, 86 x 86 x 17 mm inkl. Schaumstoffeinlage & eigenem Mein-Seelenfunke-Logodruck): Einzelpreis 1,06 € netto = 101,76 € netto\n- 1x Klischeekosten / Druckstempel (Art.-Nr.: 60000002000, Klischee-Nr. L247401): 0,00 €\nZwischensumme: 101,76 € netto | Versand & Bearbeitung (Standard nach DE): 15,00 € netto | Steuer (25% DK MwSt.): 29,19 € | Gesamtsumme: 145,95 € brutto (1,52 € brutto / Stk.)\nZahlungsart: PayPal (Alina Steinhauer - Mein Seelenfunke)\nHändler: Westpack A/S, Sletten 21, 7500 Holstebro, Dänemark, USt-IdNr.: DK15505908, Kontakt Klischee: afz@westpack.com\nProdukt-Link: https://www.westpack.com/eur_eu_ger/frankfurt-eco-jewellery-box-for-pendant-bangle-30286060102.html",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/11/2026-11-02_Westpack_96x-Frankfurt-ECO-Schmuckschachtel-Order-29000069521.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-03_Westpack_Logokorrektur-Klischee-L247401-Kundennr-7164015.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-03_Westpack_Logo-Vektor-L247401-Kundennr-7164015.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-03_Westpack_Logo-Vorschau-L247401-Kundennr-7164015.jpg'
                ]
            ],
            [
                'title'          => 'DPMA Markenanmeldung Wortmarke "Mein Seelenfunke" (Nizza-Klassen 14, 21, 40 - Leitklasse 14)',
                'location'       => 'Deutsches Patent- und Markenamt (DPMA)',
                'amount'         => -290.00,
                'date'           => '2026-11-01',
                'category'       => 'Beratung & Gründung',
                'tax_rate'       => 0,
                'invoice_number' => '302026261747.6',
                'note'           => "Amtliches Aktenzeichen: 30 2026 261 747.6 (302026261747.6) | Dokumenten Referenznummer (DRN): 2026110121484155WA | Postalische Empfangsbestätigung & Gebührenbescheid vom: 02.11.2026 (Formular b1v2_k467_id791 / M 8643/7.26, Barcode 5350119)\nMarkenform: Wortmarke \"Mein Seelenfunke\" (Individualmarke) | Anmelder: Steinhauer, Alina, 38518 Gifhorn | Anmeldetag: 01.11.2026\nEingetragene Nizza-Klassen (3 Klassen, 13 Begriffe, Leitklasse 14, Weitere Klassen: 21, 40):\n- Klasse 14 (4 Begriffe): Juwelierwaren, Schmuckwaren; Schlüsselringe und Schlüsselketten sowie Anhänger hierfür; Schmuck- und Uhrenbehältnisse; Edelsteine, Perlen und Edelmetalle sowie Imitationen hiervon\n- Klasse 21 (3 Begriffe): Geschirr, Kochgeschirr und Behälter; Überwiegend aus Keramik sowie Glas oder deren Ersatzstoffen hergestellte Kunstwerke und Verzierungen, einschließlich Skulpturen; Unbearbeitetes und teilweise bearbeitetes Glas, nicht für Bauzwecke\n- Klasse 40 (6 Begriffe): Gravieren; Gravieren mittels Laser u.a.\nGebührentatbestand: Anmeldeverfahren einschließlich der Klassengebühr bis zu drei Klassen (§ 32 MarkenG, Geb.-Nr.: 331 000) = 290,00 EUR (umsatzsteuerfrei, hoheitliche Gebühr)\nBezahlt am: 01.11.2026 um 22:01:27 Uhr per Überweisung von Volksbank BRAWO (Kontoinhaberin: Alina Steinhauer, IBAN: DE85 2699 1066 8583 1960 00, BIC: GENODEF1WOB, Freigabe: SecureGo plus) an Bundeskasse, Bundesbankfiliale München, Leopoldstraße 234, 80807 München (IBAN: DE84 7000 0000 0070 0010 54, BIC: MARKDEF1700, Verwendungszweck: 302026261747.6 331000)\nBehörde: Deutsches Patent- und Markenamt, Hausanschrift: Zweibrückenstraße 12, 80331 München (Postanschrift: 80297 München), Tel.: +49 (0)89 2195-4661, Fax: +49 (0)89 2195-2221, Web: https://www.dpma.de",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/11/2026-11-02_DPMA_Empfangsbestaetigung-Gebuehrenbescheid-302026261747-6.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-01_DPMA_Volksbank-Ueberweisungsbestaetigung-290EUR.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-01_DPMA_Markenanmeldung-Mein-Seelenfunke-Eingangsbestaetigung-302026261747-6.pdf'
                ]
            ],
            [
                'title'          => 'xTool F2 Ultra UV 5W UV-Lasergravierer (+ Conveyor), RA3 MagSwap Rotationsmodul, SafetyPro IF2 2.0 Rohrventilator & Pedal Switch',
                'location'       => 'de.xtool.com (XTL Tech EU GmbH)',
                'amount'         => -4907.51,
                'date'           => '2026-11-01',
                'category'       => 'Arbeitsmaterial',
                'tax_rate'       => 19,
                'invoice_number' => 'DE-99216',
                'note'           => "Bestellbestätigungsnr.: CN4LVQI0Q | Rechnungsnr.: DE-99216 (01.11.2026 15:46:43)\nPositionen (inkl. 19% DE MwSt.):\n1x xTool F2 Ultra UV 5 W UV-Lasergravierer + Conveyor (SKU: P0101000396-P3010010 / X-P3010010): 4.739,00 € brutto abzgl. 460,00 € Rabatt (Code: XTBFCM460) = 4.279,00 € brutto\n1x xTool RA3 & RA3 Lite: Smartes MagSwap-Rotationsmodul für X1/P3/F-Serie (SKU: P5010454): 389,13 € brutto (327,00 € netto + 62,13 € MwSt.)\n1x xTool SafetyPro IF2 2.0 Rohrventilator (SKU: P0102000113, Vorbestellung): 198,73 € brutto (167,00 € netto + 31,73 € MwSt.)\n1x xTool F2/F1/F2 Ultra/F2 Ultra UV Pedal Switch (SKU: P5010340, Vorbestellung): 40,65 € brutto (34,16 € netto + 6,49 € MwSt.)\nGratisgeschenke (0,00 €): 3x K9-Rechteck-Kristall (SKU: P0104001272), 1 Monat Atomm Pro kostenlos (SKU: W0603000312)\nGesamtsumme: 4.907,51 € brutto (4.123,96 € netto zzgl. 783,55 € MwSt. | Versand: 0,00 €)\nZahlungsart: PayPal / Shopify-Zahlung (kontakt@mein-seelenfunke.de)\nHändler: xTool Deutschland / XTL Tech EU GmbH, Hermann-Klammt-Straße 1-3, 41460 Neuss, USt-IdNr.: DE459313945, E-Mail: service.de@xtool.com",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/11/2026-11-01_xTool_F2-Ultra-UV-Komplettpaket-Rechnung-DE-99216.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-01_xTool_F2-Ultra-UV-Komplettpaket-Bestellbestaetigung-CN4LVQI0Q.png',
                    'buchhaltung/receipts/2026/11/2026-11-01_xTool_AGB.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-01_xTool_Widerrufsrecht.pdf'
                ]
            ],
            [
                'title'          => '5x Edelstahl Laserrohling Anhänger, Herz, mit Collierschlaufe, 14 Karat vergoldet, 25 x 22 x 4 mm, Öse: 3 x 6 mm, Ion Plating(IP)',
                'location'       => 'www.gluecksfieber.de',
                'amount'         => -16.99,
                'date'           => '2026-10-30',
                'category'       => 'Rohmaterial',
                'tax_rate'       => 19,
                'invoice_number' => '324858',
                'note'           => "Bestellung #324858 | Art. Nr.: STA1274 (5 Stück à 2,40 € brutto = 12,00 € brutto / 10,08 € netto zzgl. 1,92 € MwSt.)\nVersand (DHL): 4,99 € brutto (4,19 € netto zzgl. 0,80 € MwSt.) | Gesamtbetrag: 16,99 €\nZahlungsart: PayPal (kontakt@mein-seelenfunke.de)\nHändler: Stefan Spreier & Sandra Schmidt GbR (Glücksfieber), Stockholmring 8, 23560 Lübeck, Tel.: 0451/ 47 99 85-0, E-Mail: kundenservice@gluecksfieber.de\nProdukt-Link: https://www.gluecksfieber.de/shop/laserrohlinge/edelstahl-laserrohling-anhaenger-herz-mit-collierschlaufe-14-karat-vergoldet-25-x-22-x-4-mm-oese-3-x-6-mm-ion-plating-ip.html",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/10/2026-10-30_Gluecksfieber_5x-Edelstahl-Laserrohling-Anhaenger-Herz-Rechnung.pdf'
                ]
            ],
            [
                'title'          => 'Gewerbeanmeldung Stadt Gifhorn (GewA1) - Neugründung Mein-Seelenfunke',
                'location'       => 'Stadt Gifhorn',
                'amount'         => -32.50,
                'date'           => '2026-10-22',
                'category'       => 'Beratung & Gründung',
                'tax_rate'       => 0,
                'invoice_number' => 'OGAN-2026-00000406',
                'note'           => "Amtliche Bescheinigung GewA 1 (§ 14 / § 55c GewO) der Stadt Gifhorn (Gemeindekennzahl: 03151009)\nAktenzeichen: gew/28-10-2026/Ce/402 | Vorgangsnummer: gew / 28-10-2026 / Ce / 893 | Bescheinigt am: 28.10.2026 (i.A. Ceylan, Dienstsiegel 68)\nGeschäftsbezeichnung (Feld 3): Mein-Seelenfunke | Inhaberin: Alina Steinhauer (geb. 01.09.1993 in Gifhorn), Betriebsstätte: Carl-Goerdeler-Ring 26, 38518 Gifhorn, Tel.: +49 1590 1966864, E-Mail: kontakt@mein-seelenfunke.de\nAngemeldete Tätigkeit (Feld 18): Online-Einzelhandel mit und Vertrieb von physischen und digitalen Produkten (insbesondere personalisierte Geschenkartikel, Deko- und Wohnaccessoires sowie Design- und digitale Vorlagen); Veredelung und Personalisierung von Waren (mittels Lasergravur und UV-Druck) sowie Erbringung von Dienstleistungen im Bereich Mediengestaltung, Grafikdesign und Veredelung.; Online-Einzelhandel mit physischen und digitalen Produkten (insbesondere Geschenkartikel, Deko- und Wohnaccessoires).; Veredelung und Personalisierung von Waren, Geschenkartikeln und Werkstoffen wie Glas, Schiefer, Holz und Metallen (mittels Lasergravur und UV-Druck).; Dienstleistungen in den Bereichen Mediengestaltung, Grafikdesign sowie Erstellung und Vertrieb digitaler Design- und Druckvorlagen.; Laser-Lohnarbeiten und Gravurservice für Gewerbekunden, Vereine und Agenturen.\nTätigkeitsbeginn (Feld 20): 01.11.2026 (Haupterwerb, Hauptniederlassung, Neugründung, Handel & Sonstiges)\nELSTER Fragebogen zur steuerlichen Erfassung (Finanzamt Gifhorn): Übermittelt am 06.11.2026 um 15:35:38 Uhr | Transferticket: ep3104hvg8fm3b1m4zrwrd3djc6ieugc | ELSTER-Nr.: 2169193168 -B41 (Regelbesteuerung, Istversteuerung, USt-IdNr. beantragt, Geschäftskonto FYRST DE76 2507 0370 0079 8629 00)\nBezahlt via PayPal (Transaktionscode: 7N857731L42389422) | Bankkonto: Volksbank eG Braunschweig Wolfsburg x-6000\nOnline-Transaktionsnummer: OLAV-GEW001317632 | Auftragsnummer: GEWANZ-20261022-000454-KCHAXY | Referenznummer Vorgang: A0007510",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/10/2026-10-28_Stadt-Gifhorn_Gewerbeanmeldung-GewA1-Bescheinigung.pdf',
                    'buchhaltung/receipts/2026/11/2026-11-06_Finanzamt-Gifhorn_ELSTER-Steuerliche-Erfassung-Versandbestaetigung.pdf',
                    'buchhaltung/receipts/2026/10/2026-10-28_Stadt-Gifhorn_Gewerbeanzeige-Vorgang-Abgeschlossen-A0007510.pdf',
                    'buchhaltung/receipts/2026/10/2026-10-22_Stadt-Gifhorn_Gewerbeanzeige-Zahlungsbeleg.pdf',
                    'buchhaltung/receipts/2026/10/2026-10-22_Stadt-Gifhorn_PayPal-Zahlungsbeleg.pdf'
                ]
            ],
            [
                'title'          => '50x 3D Goldfolien-Kristall-Transferaufkleber & 50x 3D UV Metallic Logo Aufkleber',
                'location'       => 'Temu',
                'amount'         => -36.34,
                'date'           => '2026-10-22',
                'category'       => 'Rohmaterial',
                'tax_rate'       => 19,
                'invoice_number' => 'PO-076-01528762450550774',
                'file_paths'     => [
                    'buchhaltung/receipts/2026/10/2026-10-22_Temu_3D-Goldfolien-Kristall-Transferaufkleber-Metallic-Logo.pdf',
                    'buchhaltung/receipts/2026/10/2026-10-22_Temu_3D-Goldfolien-Kristall-Transferaufkleber-Metallic-Logo.png'
                ]
            ],
            [
                'title'          => '1x Bambus-Kugelschreiber (24er Set) & 2x Edelstahl-Schlüsselanhänger (16 Stk. Gold)',
                'location'       => 'Temu',
                'amount'         => -32.85,
                'date'           => '2026-10-15',
                'category'       => 'Rohmaterial',
                'tax_rate'       => 19,
                'invoice_number' => 'PO-076-11149563187830774',
                'file_paths'     => [
                    'buchhaltung/receipts/2026/10/2026-10-15_Temu_Bambus-Kugelschreiber-Edelstahl-Schluesselanhaenger.pdf',
                    'buchhaltung/receipts/2026/10/2026-10-15_Temu_Bambus-Kugelschreiber-Edelstahl-Schluesselanhaenger.png'
                ]
            ],
            [
                'title'          => 'Tragfähigkeitsprüfung für ALG 1 Gründungszuschuss & Erstellung der fachkundigen Stellungnahme',
                'location'       => 'CopeCart GmbH',
                'amount'         => -94.01,
                'date'           => '2026-10-12',
                'category'       => 'Beratung & Gründung',
                'tax_rate'       => 19,
                'invoice_number' => '7078405',
                'file_paths'     => ['buchhaltung/receipts/2026/10/2026-10-12_CopeCart_Tragfaehigkeitspruefung-Gruendungszuschuss.pdf']
            ],
            [
                'title'    => 'Plastikboxen',
                'location' => 'Zimmermann',
                'amount'   => -40.31,
                'date'     => '2026-06-15',
                'category' => 'Arbeitsmaterial',
                'file_paths' => ['buchhaltung/receipts/2026/06/2026-06-15_Zimmermann_Plastikboxen.pdf']
            ],
            [
                'title'          => '2x Set Schwarze Grußkartenumschläge mit Goldrand (100 Umschläge + 100 goldene Siegel-Aufkleber) & 1x Massivholz-Bandhalter',
                'location'       => 'Temu',
                'amount'         => -25.05,
                'date'           => '2026-06-11',
                'category'       => 'Verpackungen',
                'tax_rate'       => 19,
                'invoice_number' => 'PO-076-16518259507830774',
                'note'           => "Bestellnummer: PO-076-16518259507830774 (Bestellzeit / Bezahlt am 11.06.2026 via PayPal, zugestellt am 19.06.2026)\nEnthält 19% USt. in Höhe von 4,00 € (Artikel gesamt: 25,41 €, Temu-Rabatt: -0,36 €)\nPositionen:\n- 2x 100 Stück Set (je 50 schwarze Umschläge mit goldenem Folienrand + 50 goldene Wachssiegel-Aufkleber = 100 Umschläge + 100 Siegel-Aufkleber) à 7,96 € (Händler: Lumina BK / Kaibo(dongguan)Zhi Zhi Pin Co., Ltd)\n- 1x Massivholz-Bandhalter / Garnwickelregal zur Bandaufbewahrung à 9,49 € (Händler: Gnyn)",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/06/2026-06-11_Temu_100x-Schwarze-Goldrand-Umschlaege-Siegel-Massivholz-Bandhalter-Order-PO-076-16518259507830774.pdf',
                    'buchhaltung/receipts/2026/06/2026-06-11_Temu_100x-Schwarze-Goldrand-Umschlaege-Siegel-Massivholz-Bandhalter-Order-PO-076-16518259507830774.png'
                ]
            ],
            [
                'title'          => '5x 50er Set Goldene Metallkarten (250 Stk., 86x54x0,2mm für Gutscheine) & 10x Goldfarbenes Geschenkband (91,44m Spulen)',
                'location'       => 'Temu',
                'amount'         => -35.25,
                'date'           => '2026-05-25',
                'category'       => 'Rohmaterial',
                'tax_rate'       => 19,
                'invoice_number' => 'PO-076-06811572188790774',
                'note'           => "Bestellnummer: PO-076-06811572188790774 (Bestellzeit / Bezahlt am 25.05.2026 via PayPal, zugestellt am 04.06.2026)\nEnthält 19% USt. in Höhe von 5,63 €\nPositionen:\n- 5x 50 Stück goldene, luxuriöse Metallkarten (250 Stück gesamt, 8,6 x 5,4 cm / 86 x 54 mm, Stärke 0,2 mm) à 4,07 € = 20,35 € (Händler: GGOOOM)\n- 10x 91,44 Meter Lockige Bandspulen / Geschenkband Goldfarben à 1,49 € = 14,90 € (Händler: Victory Party / Yiwu Xiku Crafts Co.,Ltd.)",
                'file_paths'     => [
                    'buchhaltung/receipts/2026/05/2026-05-25_Temu_250x-Goldene-Metallkarten-10x-Geschenkband-Order-PO-076-06811572188790774.pdf',
                    'buchhaltung/receipts/2026/05/2026-05-25_Temu_250x-Goldene-Metallkarten-10x-Geschenkband-Order-PO-076-06811572188790774.png'
                ]
            ],
            [
                'title'    => 'Laserschutzschulung',
                'location' => 'Luminus Institut für Laserschutz und Arbeitssicherheit',
                'amount'   => -712.81,
                'date'     => '2026-04-23',
                'category' => 'Software & Lizenzen',
                'file_paths' => ['buchhaltung/receipts/2026/04/2026-04-23_Luminus-Institut_Laserschutzschulung.pdf']
            ],
            [
                'title'    => 'Lenovo Docking Station',
                'location' => 'AFB Social & Green IT',
                'amount'   => -84.90,
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
            AccountingSpecialIssue::updateOrCreate(
                [
                    'admin_id'       => $admin->id,
                    'title'          => $issue['title'],
                    'execution_date' => $issue['date'],
                ],
                [
                    'location'       => $issue['location'],
                    'category'       => $issue['category'],
                    'amount'         => $issue['amount'],
                    'is_business'    => true,
                    'tax_rate'       => $issue['tax_rate'] ?? 19,
                    'invoice_number' => $issue['invoice_number'] ?? null,
                    'file_paths'     => $issue['file_paths'] ?? null,
                    'note'           => $issue['note'] ?? null,
                ]
            );
        }
    }
}

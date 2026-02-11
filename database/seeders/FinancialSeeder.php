<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use App\Models\Financial\FinanceCategory;
use App\Models\Financial\FinanceCostItem;
use App\Models\Financial\FinanceGroup;
use App\Models\Financial\FinanceSpecialIssue;
use Illuminate\Database\Seeder;

class FinancialSeeder extends Seeder
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
            'Urlaub',
            'Fitness',
            'Schmutzwasser',
            'Steuer',
            'Kind',
            'Haushalt',
            'Sprit',
            // Gewerbliche Kategorien aus den Daten
            'Arbeitsmaterial',
            'Rohmaterial',
            'Verpackungen'
        ];

        foreach ($categories as $catName) {
            FinanceCategory::firstOrCreate([
                'admin_id' => $admin->id,
                'name' => $catName
            ]);
        }

        // --- 1. Gruppen erstellen ---

        $groupsData = [
            'Einnahmen' => ['type' => 'income', 'pos' => 10],
            'Haus' => ['type' => 'expense', 'pos' => 20],
            'Versicherungen' => ['type' => 'expense', 'pos' => 30],
            'Internet & Mobil' => ['type' => 'expense', 'pos' => 40],
            'Auto' => ['type' => 'expense', 'pos' => 50],
            'Unterhalt' => ['type' => 'expense', 'pos' => 60],
        ];

        $createdGroups = [];

        foreach ($groupsData as $name => $data) {
            $group = FinanceGroup::create([
                'admin_id' => $admin->id,
                'name' => $name,
                'type' => $data['type'],
                'position' => $data['pos'],
            ]);
            $createdGroups[$name] = $group->id;
        }

        // --- 2. Kostenstellen erstellen ---

        $items = [
            // Einnahmen (Positiv)
            [
                'group' => 'Einnahmen',
                'name' => 'ALG 1 + GZ',
                'amount' => 1800.00,
                'interval' => 1, // monthly
                'start_date' => '2021-09-08',
                'description' => null,
            ],
/*            [
                'group' => 'Einnahmen',
                'name' => 'Garage',
                'amount' => 200.00,
                'interval' => 1, // monthly
                'start_date' => '2021-09-14',
                'description' => null,
            ],*/
            [
                'group' => 'Einnahmen',
                'name' => 'Miete',
                'amount' => 550.00,
                'interval' => 1,
                'start_date' => '2025-07-22',
                'description' => null,
            ],

            // Haus (Ausgaben = Negativ)
            [
                'group' => 'Haus',
                'name' => 'Wasser',
                'amount' => -18.00,
                'interval' => 1,
                'start_date' => '2021-09-14',
                'description' => "KundenNr: 0014808802\nZählerNr: 8MAD0100022997\n02....",
            ],
            [
                'group' => 'Haus',
                'name' => 'Strom',
                'amount' => -18.00,
                'interval' => 1,
                'start_date' => '2021-09-14',
                'description' => "KundenNr: 0014808802\nAktuelles Paket: Individualst...",
            ],
            [
                'group' => 'Haus',
                'name' => 'Kredit',
                'amount' => -546.00,
                'interval' => 1,
                'start_date' => '2021-09-14',
                'description' => "KontoNr.: 8583196200",
            ],
            [
                'group' => 'Haus',
                'name' => 'Abfall',
                'amount' => -60.72,
                'interval' => 3, // quarter_yearly
                'start_date' => '2021-09-14',
                'description' => "Für das Jahr 2021: -47,46€\nVierteljährliche Zahlw...",
            ],
            [
                'group' => 'Haus',
                'name' => 'Rundfunkbeitrag',
                'amount' => -18.36,
                'interval' => 1,
                'start_date' => '2021-09-14',
                'description' => "IBAN: DE96200700000111111100\nBIC: DEUTDEHHXXX\nZah...",
            ],
            [
                'group' => 'Haus',
                'name' => 'Grundsteuer',
                'amount' => -103.36,
                'interval' => 3,
                'start_date' => '2021-09-14',
                'description' => "Der aktuelle Grundsteuersatz liegt bei 58,73€ (Sie...",
            ],
            [
                'group' => 'Haus',
                'name' => 'Schmutzwasser',
                'amount' => -9.00,
                'interval' => 1,
                'start_date' => '2025-02-13',
                'description' => null,
            ],
            [
                'group' => 'Haus',
                'name' => 'Straßenreinigung',
                'amount' => -15.18,
                'interval' => 3,
                'start_date' => '2022-03-23',
                'description' => "Grundabgaben sind (Winterdienst & Straßenreinigung...",
            ],

            // Versicherungen (Ausgaben = Negativ)
            [
                'group' => 'Versicherungen',
                'name' => 'Unfall',
                'amount' => -43.26,
                'interval' => 12, // yearly
                'start_date' => '2021-12-14',
                'description' => "Allianz vorher: montl. -9,78€\nJetzt: montl. 3,60€",
            ],
            [
                'group' => 'Versicherungen',
                'name' => 'Hausrat',
                'amount' => -86.69,
                'interval' => 12,
                'start_date' => '2021-12-15',
                'description' => "Vorher Allianz Jährlich: -117,36\nBezahlt am: 15.12...",
            ],
            [
                'group' => 'Versicherungen',
                'name' => 'Haftpflicht',
                'amount' => -62.18,
                'interval' => 12,
                'start_date' => '2021-12-15',
                'description' => "Vorher Allianz: 70.40€\nVermietete Objekte sind mi...",
            ],
            [
                'group' => 'Versicherungen',
                'name' => 'Wohngebäude',
                'amount' => -754.60,
                'interval' => 12,
                'start_date' => '2025-12-14',
                'description' => "Gläubiger-Identifikationsnummer: DE63ZZZ0000001057...",
            ],
            [
                'group' => 'Versicherungen',
                'name' => 'Berufsunfähigkeit',
                'amount' => -425.24,
                'interval' => 12,
                'start_date' => '2021-09-14',
                'description' => "Diese Versicherung kann erstmal so bleiben. Die is...",
            ],
            [
                'group' => 'Versicherungen',
                'name' => 'Rechtss. Gew. & Privat',
                'amount' => -317.97,
                'interval' => 12,
                'start_date' => '2021-09-14',
                'description' => "Preiserhöhung auf 341,07€ ab den 01.05.2025\nUmstel...",
            ],
            [
                'group' => 'Versicherungen',
                'name' => 'Zahnzusatzversicherung',
                'amount' => -23.58,
                'interval' => 1,
                'start_date' => '2023-10-15',
                'description' => "Weitere E-Mail:\nKundenservice@tecis.de",
            ],
            [
                'group' => 'Versicherungen',
                'name' => 'Risikolebensversicherung',
                'amount' => -106.18,
                'interval' => 12,
                'start_date' => '2025-12-14',
                'description' => "Aktuell Top! Besser geht es nicht. Christopher Koo...",
            ],

            // Internet & Mobil (Ausgaben = Negativ)
            [
                'group' => 'Internet & Mobil',
                'name' => 'All Ink. Server',
                'amount' => -9.95,
                'interval' => 1,
                'start_date' => '2021-09-14',
                'description' => null,
            ],
            [
                'group' => 'Internet & Mobil',
                'name' => 'Glasfaser',
                'amount' => -49.99,
                'interval' => 1,
                'start_date' => '2021-09-14',
                'description' => "1-12 Monat (-25€)\nPaket: DG Classic 400",
            ],
            [
                'group' => 'Internet & Mobil',
                'name' => 'Handy',
                'amount' => -7.99,
                'interval' => 1,
                'start_date' => '2025-02-10',
                'description' => null,
            ],
            [
                'group' => 'Internet & Mobil',
                'name' => 'PHP Storm',
                'amount' => -70.21,
                'interval' => 12,
                'start_date' => '2025-04-29',
                'description' => null,
            ],
            [
                'group' => 'Internet & Mobil',
                'name' => 'infomaniak (Datenverwaltung)',
                'amount' => -19.00,
                'interval' => 12,
                'start_date' => '2026-01-20',
                'description' => null,
            ],

            // Auto (Ausgaben = Negativ)
            [
                'group' => 'Auto',
                'name' => 'Benzin',
                'amount' => -100.00,
                'interval' => 1,
                'start_date' => '2025-02-13',
                'description' => null,
            ],
            [
                'group' => 'Auto',
                'name' => 'Steuer',
                'amount' => -76.00,
                'interval' => 12,
                'start_date' => '2023-09-25',
                'description' => null,
            ],
            [
                'group' => 'Auto',
                'name' => 'Versicherung',
                'amount' => -727.58,
                'interval' => 12,
                'start_date' => '2023-09-25',
                'description' => null,
            ],

            // Unterhalt (Ausgaben = Negativ)
            [
                'group' => 'Unterhalt',
                'name' => 'Unterhalt Noah',
                'amount' => -355.00,
                'interval' => 1,
                'start_date' => '2024-01-12',
                'description' => "Es wurde alles durch Lenas Rechtsanwältin berechne...",
            ],
        ];

        foreach ($items as $item) {
            FinanceCostItem::create([
                'finance_group_id' => $createdGroups[$item['group']],
                'name' => $item['name'],
                'amount' => $item['amount'],
                'interval_months' => $item['interval'],
                'first_payment_date' => $item['start_date'],
                'description' => $item['description'],
                'is_business' => false, // Standardmäßig privat für Fixkosten (kann im UI geändert werden)
            ]);
        }

        // --- 3. Sonderausgaben erstellen (Gewerblich) ---

        $specialIssues = [
            [
                'title' => 'Laserschutzbrille',
                'location' => 'Amazon',
                'amount' => -48.88,
                'date' => '2026-01-19',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Ladekabel, Adapter und Locher',
                'location' => 'Amazon',
                'amount' => -21.59,
                'date' => '2026-01-18',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Briefmarken',
                'location' => 'DHL Kaufland',
                'amount' => -29.60,
                'date' => '2026-01-16',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Mini Staubsauger',
                'location' => 'Temu',
                'amount' => -22.56,
                'date' => '2026-01-13',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Rohlinge zum lasern (Testen)',
                'location' => 'Temu',
                'amount' => -39.76,
                'date' => '2026-01-13',
                'category' => 'Rohmaterial',
            ],
            [
                'title' => 'Stiftehalter für den Büro Tisch',
                'location' => 'Amazon',
                'amount' => -3.30,
                'date' => '2026-01-09',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Mülleimer 50l',
                'location' => 'Amazon',
                'amount' => -25.99,
                'date' => '2026-01-08',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'REV Verlängerungskabel - Verlängerung 3m',
                'location' => 'Amazon',
                'amount' => -8.99,
                'date' => '2026-01-06',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => '2x Mehrfachsteckdose überspannungsschutz',
                'location' => 'Amazon',
                'amount' => -27.58,
                'date' => '2026-01-05',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => '2x 50l Mülleimer Pappe und Folie',
                'location' => 'Amazon',
                'amount' => -52.18,
                'date' => '2026-01-05',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => '2100LM 224LED Schreibtischlampe Led klemmbar',
                'location' => 'Amazon',
                'amount' => -47.88,
                'date' => '2026-01-03',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => '150stk Schlüsselanhänger Flaschenöffner Silber/Rot...',
                'location' => 'www.heavytool-shop.de/',
                'amount' => -43.50,
                'date' => '2026-01-03',
                'category' => 'Rohmaterial',
            ],
            [
                'title' => '240stk Kartons für Trophäe',
                'location' => 'Karton.eu',
                'amount' => -171.36,
                'date' => '2026-01-03',
                'category' => 'Verpackungen',
            ],
            [
                'title' => '400stk Luftpolster Versandtüten',
                'location' => 'Karton.eu',
                'amount' => -68.02,
                'date' => '2026-01-03',
                'category' => 'Verpackungen',
            ],
            [
                'title' => 'Luftdruck Reiniger',
                'location' => 'Amazon',
                'amount' => -39.99,
                'date' => '2026-01-02',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Weiße Baumwollehandschuhe',
                'location' => 'Amazon',
                'amount' => -9.90,
                'date' => '2026-01-02',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Waage',
                'location' => 'Amazon',
                'amount' => -29.99,
                'date' => '2026-01-02',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Isopropanol 6x 1 Liter',
                'location' => 'Amazon',
                'amount' => -29.95,
                'date' => '2026-01-02',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Feuerlöscher co2',
                'location' => 'Amazon',
                'amount' => -56.80,
                'date' => '2026-01-02',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Tisch',
                'location' => 'eBay Kleinanzeigen',
                'amount' => -75.00,
                'date' => '2026-01-01',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Stuhl Räder',
                'location' => 'Amazon',
                'amount' => -72.00,
                'date' => '2025-12-31',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Warnaufkleber "WARNUNG Sichtbare und unsichtbare L...',
                'location' => 'Amazon',
                'amount' => -4.99,
                'date' => '2025-12-28',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Schwerlastregale',
                'location' => 'Amazon',
                'amount' => -303.20,
                'date' => '2025-12-27',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Fragile Glas Aufkleber, Testartikel, Klebeband etc...',
                'location' => 'Temu',
                'amount' => -50.07,
                'date' => '2025-12-28',
                'category' => 'Arbeitsmaterial',
            ],
            [
                'title' => 'Etikettendrucker, Papierschneider, Abroller und Dr...',
                'location' => 'Amazon',
                'amount' => -192.31,
                'date' => '2025-12-27',
                'category' => 'Arbeitsmaterial',
            ],
        ];

        foreach ($specialIssues as $issue) {
            FinanceSpecialIssue::create([
                'admin_id' => $admin->id,
                'title' => $issue['title'],
                'location' => $issue['location'],
                'category' => $issue['category'],
                'amount' => $issue['amount'],
                'execution_date' => $issue['date'],
                'is_business' => true,
            ]);
        }
    }
}

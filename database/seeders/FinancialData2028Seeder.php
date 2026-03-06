<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use App\Models\Financial\FinanceCategory;
use App\Models\Financial\FinanceCostItem;
use App\Models\Financial\FinanceGroup;
use App\Models\Financial\FinanceSpecialIssue;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FinancialData2028Seeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::first();
        if (!$admin) {
            $this->command->error('Kein Admin gefunden. Seeder abgebrochen.');
            return;
        }

        // 1. KATEGORIEN ANLEGEN
        $categories = ['Wareneinkauf', 'Werbung & Marketing', 'Büromaterial', 'Porto & Logistik'];
        foreach ($categories as $cat) {
            FinanceCategory::firstOrCreate(['admin_id' => $admin->id, 'name' => $cat]);
        }

        // 4. VARIABLE SONDERAUSGABEN (MATERIAL & WERBUNG) FÜR 2028
        $variableExpenses = [
            // Q1 (Valentinstag & Ostern Vorbereitung)
            ['title' => 'Einkauf K9 Glasblöcke', 'cat' => 'Wareneinkauf', 'amount' => -650.00, 'date' => '2028-01-10'],
            ['title' => 'TikTok Kampagne (Valentinstag)', 'cat' => 'Werbung & Marketing', 'amount' => -250.00, 'date' => '2028-01-20'],
            ['title' => 'Etsy Ads Aufladung', 'cat' => 'Werbung & Marketing', 'amount' => -100.00, 'date' => '2028-02-05'],
            ['title' => 'Einkauf Acrylplatten', 'cat' => 'Wareneinkauf', 'amount' => -320.00, 'date' => '2028-02-15'],
            ['title' => 'Verpackungsmaterial Großbestellung', 'cat' => 'Wareneinkauf', 'amount' => -480.00, 'date' => '2028-03-05'],

            // Q2 (Muttertag & Hochzeiten)
            ['title' => 'Instagram Ads (Muttertag)', 'cat' => 'Werbung & Marketing', 'amount' => -300.00, 'date' => '2028-04-10'],
            ['title' => 'Schieferplatten Palette', 'cat' => 'Wareneinkauf', 'amount' => -750.00, 'date' => '2028-04-15'],
            ['title' => 'Etsy Ads Aufladung', 'cat' => 'Werbung & Marketing', 'amount' => -150.00, 'date' => '2028-05-02'],
            ['title' => 'Einkauf Schmuck Rohlinge', 'cat' => 'Wareneinkauf', 'amount' => -400.00, 'date' => '2028-06-10'],

            // Q3 (Sommer / Vorbereitung Herbst)
            ['title' => 'Etsy Ads', 'cat' => 'Werbung & Marketing', 'amount' => -100.00, 'date' => '2028-07-05'],
            ['title' => 'Material Nachbestellung (Glas)', 'cat' => 'Wareneinkauf', 'amount' => -350.00, 'date' => '2028-08-15'],
            ['title' => 'Werbung B2B Firmengeschenke', 'cat' => 'Werbung & Marketing', 'amount' => -200.00, 'date' => '2028-09-01'],
            ['title' => 'Herbst-Sortiment Rohlinge', 'cat' => 'Wareneinkauf', 'amount' => -550.00, 'date' => '2028-09-20'],

            // Q4 (Weihnachtsgeschäft - Sehr hoher Wareneinkauf)
            ['title' => 'Großbestellung K9 Glasblöcke', 'cat' => 'Wareneinkauf', 'amount' => -1200.00, 'date' => '2028-10-05'],
            ['title' => 'Großbestellung Schieferplatten', 'cat' => 'Wareneinkauf', 'amount' => -800.00, 'date' => '2028-10-10'],
            ['title' => 'Verpackungen & Inlays (Premium)', 'cat' => 'Wareneinkauf', 'amount' => -600.00, 'date' => '2028-10-15'],
            ['title' => 'TikTok & Instagram Ads (Black Week)', 'cat' => 'Werbung & Marketing', 'amount' => -500.00, 'date' => '2028-11-05'],
            ['title' => 'Last Minute Material (Notkauf)', 'cat' => 'Wareneinkauf', 'amount' => -450.00, 'date' => '2028-12-05'],
            ['title' => 'Google Ads Push (Dezember)', 'cat' => 'Werbung & Marketing', 'amount' => -350.00, 'date' => '2028-12-10'],
        ];

        foreach ($variableExpenses as $exp) {
            FinanceSpecialIssue::create([
                'id' => Str::uuid(),
                'admin_id' => $admin->id,
                'title' => $exp['title'],
                'category' => $exp['cat'],
                'amount' => $exp['amount'],
                'execution_date' => $exp['date'],
                'is_business' => true,
                'tax_rate' => 19,
            ]);
        }

        $this->command->info('✅ Realistische Test-Daten für das Finanzjahr 2028 erfolgreich angelegt!');
    }
}

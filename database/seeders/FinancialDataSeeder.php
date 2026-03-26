<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Accounting\AccountingCategory;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Order\Order;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FinancialDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::first();
        if (!$admin) {
            $this->command->error('Kein Admin gefunden. Seeder abgebrochen.');
            return;
        }

        // 1. Kategorien anlegen
        $categories = ['Wareneinkauf', 'Werbung & Marketing', 'Büromaterial', 'Porto & Logistik', 'Consulting & Setup'];
        foreach ($categories as $cat) {
            AccountingCategory::firstOrCreate(['admin_id' => $admin->id, 'name' => $cat]);
        }

        // Dummy Customer für die Sales
        $customer = Customer::firstOrCreate([
            'email' => 'demokunde@seelenfunke.local'
        ], [
            'first_name' => 'Demo',
            'last_name' => 'Kunde',
            'password' => bcrypt('password')
        ]);

        // ALTE TESTDATEN LÖSCHEN UM DOPPELUNGEN ZU VERMEIDEN
        AccountingSpecialIssue::where('admin_id', $admin->id)->whereYear('execution_date', '>=', 2027)->delete();
        Order::where('email', 'demokunde@seelenfunke.local')->delete();

        // JAHRESVERLAUF 2027 & 2028
        // Monatliche Umsatz-Ziele (Netto/Brutto gerundet, in EUR)
        $monthlyRevenues2027 = [
            1 => 1200, 2 => 1800, 3 => 2200, 4 => 2500, 5 => 3000, 6 => 2800,
            7 => 2500, 8 => 2900, 9 => 3500, 10 => 4500, 11 => 7500, 12 => 9000
        ];

        $monthlyRevenues2028 = [
            1 => 3500, 2 => 4200, 3 => 4800, 4 => 5500, 5 => 6000, 6 => 5500,
            7 => 5000, 8 => 5800, 9 => 6500, 10 => 8500, 11 => 12500, 12 => 15000
        ];

        // 2. Dummy Orders (Umsätze) generieren
        $this->generateOrdersForYear(2027, $monthlyRevenues2027, $customer);
        $this->generateOrdersForYear(2028, $monthlyRevenues2028, $customer);

        // 3. Variable Sonderausgaben (Material & Werbung) für 2027
        $expenses2027 = [
            // Q1
            ['title' => 'Einkauf Acrylplatten Basics', 'cat' => 'Wareneinkauf', 'amount' => -450.00, 'date' => '2027-01-15'],
            ['title' => 'TikTok & Insta Ads', 'cat' => 'Werbung & Marketing', 'amount' => -150.00, 'date' => '2027-02-10'],
            ['title' => 'Verpackungsmaterial (Kartons)', 'cat' => 'Wareneinkauf', 'amount' => -200.00, 'date' => '2027-03-05'],
            // Q2
            ['title' => 'Meta Ads Muttertag', 'cat' => 'Werbung & Marketing', 'amount' => -250.00, 'date' => '2027-04-10'],
            ['title' => 'Holz & Schiefer Rohlinge', 'cat' => 'Wareneinkauf', 'amount' => -550.00, 'date' => '2027-05-05'],
            // Q3
            ['title' => 'Messe / Markt Standgebühr', 'cat' => 'Werbung & Marketing', 'amount' => -180.00, 'date' => '2027-07-20'],
            ['title' => 'Etsy Ads Aufladung', 'cat' => 'Werbung & Marketing', 'amount' => -150.00, 'date' => '2027-08-15'],
            ['title' => 'K9 Glasblöcke Vorbestellung', 'cat' => 'Wareneinkauf', 'amount' => -800.00, 'date' => '2027-09-10'],
            // Q4 (Weihnachtsgeschäft)
            ['title' => 'Verpackung Premium Inlays', 'cat' => 'Wareneinkauf', 'amount' => -400.00, 'date' => '2027-10-05'],
            ['title' => 'Instagram Ads Black Week', 'cat' => 'Werbung & Marketing', 'amount' => -400.00, 'date' => '2027-11-05'],
            ['title' => 'Notkauf Rohlinge (Nachbestellung)', 'cat' => 'Wareneinkauf', 'amount' => -350.00, 'date' => '2027-12-02'],
            ['title' => 'Google Ads Push (Dezember)', 'cat' => 'Werbung & Marketing', 'amount' => -300.00, 'date' => '2027-12-10'],
        ];

        // 4. Variable Sonderausgaben für 2028 (Skalierung)
        $expenses2028 = [
            // Q1
            ['title' => 'Einkauf K9 Glasblöcke Palette', 'cat' => 'Wareneinkauf', 'amount' => -1200.00, 'date' => '2028-01-10'],
            ['title' => 'TikTok Kampagne (Valentinstag)', 'cat' => 'Werbung & Marketing', 'amount' => -350.00, 'date' => '2028-01-20'],
            ['title' => 'Etsy Ads', 'cat' => 'Werbung & Marketing', 'amount' => -200.00, 'date' => '2028-02-05'],
            ['title' => 'Einkauf Acrylplatten & Holz', 'cat' => 'Wareneinkauf', 'amount' => -650.00, 'date' => '2028-03-05'],
            // Q2
            ['title' => 'Instagram Ads (Muttertag)', 'cat' => 'Werbung & Marketing', 'amount' => -450.00, 'date' => '2028-04-10'],
            ['title' => 'Schieferplatten Palette', 'cat' => 'Wareneinkauf', 'amount' => -950.00, 'date' => '2028-05-15'],
            ['title' => 'Einkauf Schmuck Rohlinge', 'cat' => 'Wareneinkauf', 'amount' => -400.00, 'date' => '2028-06-10'],
            // Q3
            ['title' => 'Werbung B2B Firmengeschenke', 'cat' => 'Werbung & Marketing', 'amount' => -500.00, 'date' => '2028-08-01'],
            ['title' => 'Herbst-Sortiment Rohlinge', 'cat' => 'Wareneinkauf', 'amount' => -850.00, 'date' => '2028-09-20'],
            // Q4
            ['title' => 'Großbestellung K9 Glasblöcke', 'cat' => 'Wareneinkauf', 'amount' => -2200.00, 'date' => '2028-10-05'],
            ['title' => 'Verpackungen & Inlays (Premium)', 'cat' => 'Wareneinkauf', 'amount' => -900.00, 'date' => '2028-10-15'],
            ['title' => 'TikTok & Instagram Ads (Black Week)', 'cat' => 'Werbung & Marketing', 'amount' => -800.00, 'date' => '2028-11-05'],
            ['title' => 'Last Minute Material (Notkauf)', 'cat' => 'Wareneinkauf', 'amount' => -650.00, 'date' => '2028-12-05'],
            ['title' => 'Google Ads Push (Dezember)', 'cat' => 'Werbung & Marketing', 'amount' => -600.00, 'date' => '2028-12-10'],
        ];

        $allExpenses = array_merge($expenses2027, $expenses2028);

        foreach ($allExpenses as $exp) {
            AccountingSpecialIssue::create([
                'id' => (string) Str::uuid(),
                'admin_id' => $admin->id,
                'title' => $exp['title'],
                'category' => $exp['cat'],
                'amount' => $exp['amount'],
                'execution_date' => $exp['date'],
                'is_business' => true,
                'tax_rate' => 19,
            ]);
        }

        $this->command->info('✅ Realistische Test-Daten (Umsätze & Kosten) für 2027/2028 erfolgreich angelegt!');
    }

    private function generateOrdersForYear($year, $monthlyTargets, $customer)
    {
        foreach ($monthlyTargets as $month => $targetGross) {
            // Wir splitten das Monatsziel in ca 4-8 kleinere "Tages"-Orders auf
            $orderCount = rand(4, 8);
            $avgOrderValue = $targetGross / $orderCount;

            for ($i = 0; $i < $orderCount; $i++) {
                // Variation von +/- 30%
                $variation = $avgOrderValue * (rand(-30, 30) / 100);
                $finalValue = $avgOrderValue + $variation;

                // Cents
                $totalCents = (int) round($finalValue * 100);

                // Verteile Orders zufällig über den Monat
                $day = rand(1, 28);
                $date = Carbon::createFromDate($year, $month, $day)->setTime(rand(9, 18), rand(0, 59));

                Order::create([
                    'id' => (string) Str::uuid(),
                    'order_number' => 'ORD-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . Str::random(5),
                    'customer_id' => $customer->id,
                    'email' => $customer->email,
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'payment_method' => 'paypal',
                    'total_price' => $totalCents,
                    'subtotal_price' => (int) round($totalCents / 1.19),
                    'tax_amount' => $totalCents - (int) round($totalCents / 1.19),
                    'shipping_price' => 0,
                    'billing_address' => json_encode(['first_name' => 'Demo', 'last_name' => 'Kunde', 'street' => 'Musterweg 1', 'zip' => '12345', 'city' => 'Musterstadt', 'country' => 'DE']),
                    'shipping_address' => json_encode(['first_name' => 'Demo', 'last_name' => 'Kunde', 'street' => 'Musterweg 1', 'zip' => '12345', 'city' => 'Musterstadt', 'country' => 'DE']),
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
            }
        }
    }
}

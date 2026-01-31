<?php

namespace Database\Seeders;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCountry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        // Tabellen leeren für sauberen Neustart
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        ShippingRate::truncate();
        ShippingZoneCountry::truncate();
        ShippingZone::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // =================================================================
        // ZONE 1: DEUTSCHLAND (Backup für DB-Logik)
        // =================================================================
        // Auch wenn der CartService dies aktuell hardcoded hat ("Hybrid-Lösung"),
        // ist es sauberer, die Werte auch in der Datenbank zu haben.
        $de = ShippingZone::create(['name' => 'Deutschland']);
        $de->countries()->create(['country_code' => 'DE']);

        // < 50 € = 4,90 €
        $de->rates()->create([
            'name' => 'Standardversand',
            'min_price' => 0,
            'max_weight' => null,
            'price' => 490
        ]);

        // >= 50 € = 0,00 € (Logik im CartService prüft min_price)
        $de->rates()->create([
            'name' => 'Kostenloser Versand',
            'min_price' => 5000,
            'max_weight' => null,
            'price' => 0
        ]);


        // =================================================================
        // ZONE 2: EUROPÄISCHE UNION (EU)
        // =================================================================
        // Pauschalpreise sind in der EU oft üblich und einfacher.
        $eu = ShippingZone::create(['name' => 'EU']);

        $euCountries = [
            'AT', 'NL', 'BE', 'FR', 'IT', 'ES', 'PL', 'CZ', 'DK', 'SE', 'FI',
            'IE', 'PT', 'GR', 'HU', 'RO', 'BG', 'SK', 'SI', 'HR', 'EE', 'LV',
            'LT', 'LU'
        ];

        foreach ($euCountries as $code) {
            $eu->countries()->create(['country_code' => $code]);
        }

        // EU Tarif: Pauschal 13,90 €
        $eu->rates()->create([
            'name' => 'EU Standard',
            'min_weight' => 0,
            'max_weight' => null,
            'price' => 1390
        ]);


        // =================================================================
        // ZONE 3: EUROPA (NICHT-EU)
        // =================================================================
        // Hier ist Zoll im Spiel (Schweiz, UK, Norwegen). Etwas teurer.
        $europeNonEu = ShippingZone::create(['name' => 'Europa (Nicht-EU)']);

        $nonEuCountries = ['CH', 'GB', 'NO', 'IS']; // Schweiz, UK, Norwegen, Island

        foreach ($nonEuCountries as $code) {
            $europeNonEu->countries()->create(['country_code' => $code]);
        }

        // Gewichtsbasiert
        // Bis 2kg (Päckchen)
        $europeNonEu->rates()->create([
            'name' => 'Päckchen (bis 2kg)',
            'min_weight' => 0,
            'max_weight' => 2000,
            'price' => 1990 // 19,90 €
        ]);
        // Bis 5kg (Paket)
        $europeNonEu->rates()->create([
            'name' => 'Paket (bis 5kg)',
            'min_weight' => 2001,
            'max_weight' => 5000,
            'price' => 2990 // 29,90 €
        ]);
        // Ab 5kg
        $europeNonEu->rates()->create([
            'name' => 'Paket (ab 5kg)',
            'min_weight' => 5001,
            'max_weight' => null,
            'price' => 3990 // 39,90 €
        ]);


        // =================================================================
        // ZONE 4: NORDAMERIKA
        // =================================================================
        // Langstrecke, Luftfracht teuer.
        $na = ShippingZone::create(['name' => 'Nordamerika']);

        $na->countries()->createMany([
            ['country_code' => 'US'],
            ['country_code' => 'CA'],
            ['country_code' => 'MX'],
        ]);

        $na->rates()->create([
            'name' => 'Economy (bis 2kg)',
            'min_weight' => 0,
            'max_weight' => 2000,
            'price' => 3590
        ]);
        $na->rates()->create([
            'name' => 'Premium (bis 5kg)',
            'min_weight' => 2001,
            'max_weight' => 5000,
            'price' => 5990
        ]);
        $na->rates()->create([
            'name' => 'Heavy (ab 5kg)',
            'min_weight' => 5001,
            'max_weight' => null,
            'price' => 8990
        ]);


        // =================================================================
        // ZONE 5: WELTWEIT (REST)
        // =================================================================
        // Asien, Südamerika, Australien etc.
        $world = ShippingZone::create(['name' => 'Weltweit']);

        $worldCountries = [
            // Südamerika
            'BR', 'AR', 'CL', 'CO',
            // Asien
            'CN', 'JP', 'KR', 'IN', 'SG', 'AE',
            // Ozeanien
            'AU', 'NZ',
            // Afrika
            'ZA'
        ];

        foreach ($worldCountries as $code) {
            $world->countries()->create(['country_code' => $code]);
        }

        // Teuerste Zone
        $world->rates()->create([
            'name' => 'Global S (bis 2kg)',
            'min_weight' => 0,
            'max_weight' => 2000,
            'price' => 3990
        ]);
        $world->rates()->create([
            'name' => 'Global M (bis 5kg)',
            'min_weight' => 2001,
            'max_weight' => 5000,
            'price' => 6990
        ]);
        $world->rates()->create([
            'name' => 'Global L (ab 5kg)',
            'min_weight' => 5001,
            'max_weight' => null,
            'price' => 9990
        ]);
    }
}

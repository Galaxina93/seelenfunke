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
        // ZONE 1: DEUTSCHLAND
        // =================================================================
        $de = ShippingZone::create(['name' => 'Deutschland']);
        $de->countries()->create(['country_code' => 'DE']);

        // < 50 € = 4,90 €
        $de->rates()->create([
            'name' => 'Standardversand DE',
            'min_price' => 0,
            'min_weight' => 0,
            'max_weight' => 31500,
            'price' => 490
        ]);

        // =================================================================
        // ZONE 2: EUROPÄISCHE UNION (DHL ZONE 1 Preise)
        // =================================================================
        $eu = ShippingZone::create(['name' => 'EU - Zone 1 (DHL)']);

        // Alle DHL Zone 1 Länder (EU)
        $euCountries = [
            'BE', 'BG', 'DK', 'EE', 'FI', 'FR', 'GR', 'IE', 'IT', 'HR',
            'LV', 'LT', 'LU', 'MT', 'MC', 'NL', 'AT', 'PL', 'PT', 'RO',
            'SE', 'SK', 'SI', 'ES', 'CZ', 'HU', 'CY'
        ];

        foreach ($euCountries as $code) {
            $eu->countries()->create(['country_code' => $code]);
        }

        // DHL Preise EU (Online-Tarife Stand 2024/2025)
        // Paket bis 2 kg
        $eu->rates()->create([
            'name' => 'DHL Paket bis 2kg',
            'min_weight' => 0,
            'max_weight' => 2000,
            'price' => 1449 // 14,49 €
        ]);

        // Paket bis 5 kg
        $eu->rates()->create([
            'name' => 'DHL Paket bis 5kg',
            'min_weight' => 2001,
            'max_weight' => 5000,
            'price' => 1749 // 17,49 €
        ]);

        // Paket bis 10 kg
        $eu->rates()->create([
            'name' => 'DHL Paket bis 10kg',
            'min_weight' => 5001,
            'max_weight' => 10000,
            'price' => 2249 // 22,49 €
        ]);

        // Paket bis 20 kg
        $eu->rates()->create([
            'name' => 'DHL Paket bis 20kg',
            'min_weight' => 10001,
            'max_weight' => 20000,
            'price' => 2849 // 28,49 €
        ]);

        // Paket bis 31.5 kg
        $eu->rates()->create([
            'name' => 'DHL Paket bis 31.5kg',
            'min_weight' => 20001,
            'max_weight' => 31500,
            'price' => 4549 // 45,49 €
        ]);

        // HINWEIS: Zonen für USA, Weltweit etc. wurden entfernt,
        // da nur noch in die EU versendet werden soll.
    }
}

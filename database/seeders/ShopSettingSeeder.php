<?php

namespace Database\Seeders;

use App\Models\ShopSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class ShopSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Die Master-Länderliste definieren (Code => Name)
        $countries = [
            'DE' => 'Deutschland', 'AT' => 'Österreich', 'NL' => 'Niederlande',
            'BE' => 'Belgien', 'FR' => 'Frankreich', 'IT' => 'Italien',
            'ES' => 'Spanien', 'PL' => 'Polen', 'CZ' => 'Tschechien',
            'DK' => 'Dänemark', 'SE' => 'Schweden', 'FI' => 'Finnland',
            'IE' => 'Irland', 'PT' => 'Portugal', 'GR' => 'Griechenland',
            'HU' => 'Ungarn', 'RO' => 'Rumänien', 'BG' => 'Bulgarien',
            'SK' => 'Slowakei', 'SI' => 'Slowenien', 'HR' => 'Kroatien',
            'EE' => 'Estland', 'LV' => 'Lettland', 'LT' => 'Litauen', 'LU' => 'Luxemburg'
        ];

        // 2. Alle Einstellungen definieren
        $settings = [
            // Shop Status & Steuern
            'is_small_business'         => 'false', // Als String speichern, da DB-Feld meist Text/String ist
            'default_tax_rate'          => '19',
            'prices_entered_gross'      => 'true',

            // Inhaber Daten (Echte Daten von Alina)
            'owner_name'                => 'Mein Seelenfunke',
            'owner_proprietor'          => 'Alina Steinhauer',
            'owner_street'              => 'Carl-Goerdeler-Ring 26',
            'owner_city'                => '38518 Gifhorn',
            'owner_email'               => 'kontakt@mein-seelenfunke.de',
            'owner_phone'               => '+49 (0) 159 019 668 64',
            'owner_tax_id'              => '19/143/11624',
            'owner_ust_id'              => 'DE123456789',

            // Versand & Logistik (Preise in Cents)
            'shipping_cost'             => '490',   // 4,90 €
            'shipping_free_threshold'   => '5000',  // 50,00 €
            'express_surcharge'         => '2500',  // 25,00 €

            // Sonstiges
            'order_quote_validity_days' => '14',

            // Länderliste als JSON
            'active_countries'          => json_encode($countries),
        ];

        // 3. Daten in die Datenbank schreiben
        foreach ($settings as $key => $value) {
            ShopSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        // 4. Cache leeren, damit die neuen Daten sofort überall aktiv sind
        Cache::forget('global_shop_settings');
    }
}

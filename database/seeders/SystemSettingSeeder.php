<?php

namespace Database\Seeders;

use App\Models\System\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SystemSettingSeeder extends Seeder
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
            'is_small_business'         => 'false',
            'default_tax_rate'          => '19',
            'prices_entered_gross'      => 'true',
            'maintenance_mode'          => 'false',

            // Inhaber Daten (Echte Daten von Alina - für juristische Erwähnungen)
            'owner_name'                => 'Mein Seelenfunke',
            'owner_proprietor'          => 'Alina Steinhauer',
            'owner_street'              => 'Carl-Goerdeler-Ring 26',
            'owner_city'                => '38518 Gifhorn',

            // Firmen Stammdaten (Für das öffentliche System)
            'company_name'              => 'Mein Seelenfunke',
            'company_street'            => 'Carl-Goerdeler-Ring',
            'company_street_number'     => '26',
            'company_zip'               => '38518',
            'company_city'              => 'Gifhorn',
            'company_country'           => 'DE',
            'company_phone'             => '+49 (0) 159 019 668 64',
            'company_email'             => 'kontakt@mein-seelenfunke.de',

            // E-Mail Routing & Postfächer
            'owner_email'               => 'kontakt@mein-seelenfunke.de',
            'owner_email_impressum'     => 'impressum@mein-seelenfunke.de',
            'owner_email_invoices'      => 'rechnungen@mein-seelenfunke.de',
            'owner_email_backup'        => 'backup@mein-seelenfunke.de',

            'owner_phone'               => '+49 (0) 159 019 668 64',
            'owner_tax_id'              => '19/143/11624',
            'owner_ust_id'              => 'DE123456789',
            'owner_court'               => 'Amtsgericht Gifhorn',
            'owner_website'             => 'www.mein-seelenfunke.de',

            // Bankdaten
            'owner_bank_name'           => 'OWNER_BANK_NAME',
            'owner_bank_address'        => 'OWNER_BANK_ADDRESS',
            'owner_bic'                 => '123456789',
            'owner_iban'                => 'DE123456789123456',

            // Erweiterte behördliche Daten (Platzhalter)
            'owner_finanzamt_nr'        => '2319',
            'owner_social_security_nr'  => '29 010993 S 512', // Sozialversicherungsnr
            'owner_tax_ident_nr'        => '66324780911', // Persönliche Steuer-ID
            'owner_health_insurance_nr' => 'O603571189',
            'owner_agency_labor_nr'     => '241D129534',
            'owner_economic_ident_nr'   => 'DE410594485', // W-IdNr.

            // Versand & Logistik (Preise in Cents)
            'shipping_cost'             => '490',   // 4,90 €
            'shipping_free_threshold'   => '5000',  // 50,00 €
            'express_surcharge_percent' => '20',    // 20% des Netto-Warenwerts
            'express_surcharge_min'     => '500',   // Minimum 5,00 €
            'packaging_weight_grams'    => '350',   // 350g Leergewicht für Verpackung

            // Produkt & Lager Einstellungen
            'inventory_low_stock_threshold' => '100',     // Warnung ab 50 Stück
            'skip_shipping_for_digital'     => 'true', // false = Digitale Produkte haben auch Versandkosten / true = Versand fällt weg bei D. Produkten

            // Stripe Konfiguration
            'stripe_publishable_key'    => '',
            'stripe_secret_key'         => '',
            'stripe_webhook_secret'     => '',

            // Sonstiges
            'order_quote_validity_days' => '14',

            // Produktions-Kapazitäten
            'shop_daily_working_hours'  => '7',
            'shop_minutes_per_order'    => '10',
            'shop_capacity_buffer'      => '12',

            // Länderliste als JSON (Fallback)
            'active_countries'          => json_encode($countries),
        ];

        // 3. Daten in die Datenbank schreiben
        foreach ($settings as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        // 4. Cache leeren, damit die neuen Daten sofort überall aktiv sind
        Cache::forget('global_shop_settings');
        Cache::forget('shop_setting_inventory_threshold');
    }
}

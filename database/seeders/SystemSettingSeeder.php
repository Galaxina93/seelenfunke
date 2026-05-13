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
            'active_tax_rates'          => json_encode([19, 7, 0]),
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

        // 5. Lieferzeiten (Ampelsystem) & Spezialmodi initialisieren
        \App\Models\Delivery\DeliverySetting::firstOrCreate(['id' => 1], [
            'is_vacation_mode' => false,
            'is_sick_mode' => false,
            'vacation_description' => 'Mein Seelenfunke macht eine kurze Kreativpause! ☀️ Bestellungen sind weiterhin möglich, werden aber erst nach unserer Rückkehr gefertigt und versendet. Wir wünschen dir trotzdem ganz viel Spaß beim Stöbern im Shop!',
            'sick_description' => 'Einige aus unseren Team sind leider aktuell gesundheitlich etwas angeschlagen und liegen flach. 🤒 Bestellungen werden natürlich weiterhin angenommen, die Fertigung und der Versand verzögern sich jedoch, bis wir wieder fit sind. Wir bitten um dein Verständnis und wünschen dir trotzdem ganz viel Spaß beim Stöbern!'
        ]);

        \App\Models\Delivery\DeliveryTime::updateOrCreate(
            ['name' => 'Standard'],
            [
                'min_days' => 3,
                'max_days' => 5,
                'color' => 'green',
                'description' => 'Da jedes Seelenstück ein Unikat ist, setzt sich diese Zeit aus der individuellen Fertigung in der Manufaktur und dem anschließenden Postweg zusammen.',
            ]
        );

        \App\Models\Delivery\DeliveryTime::updateOrCreate(
            ['name' => 'Erhöhtes Aufkommen'],
            [
                'min_days' => 5,
                'max_days' => 8,
                'color' => 'yellow',
                'description' => 'Aufgrund vieler Bestellungen benötigen wir aktuell etwas länger für die liebevolle Handfertigung deines Unikats. Danke für deine Geduld!',
            ]
        );

        \App\Models\Delivery\DeliveryTime::updateOrCreate(
            ['name' => 'Hohe Auslastung'],
            [
                'min_days' => 10,
                'max_days' => 14,
                'color' => 'red',
                'description' => 'Wir fertigen auf Hochtouren! Bitte beachte die deutlich verlängerte Bearbeitungszeit durch die aktuell extrem hohe Nachfrage.',
            ]
        );

        \App\Models\Delivery\DeliveryTime::updateOrCreate(
            ['name' => 'Extreme Auslastung'],
            [
                'min_days' => 16,
                'max_days' => 21,
                'color' => 'red',
                'description' => 'Aufgrund extrem hoher Auslastung kommt es derzeit zu Verzögerungen. Dein Unikat wird mit größter Sorgfalt, aber etwas später gefertigt. Danke für dein Verständnis!',
            ]
        );

        // Sicherstellen, dass mindestens eine Lieferzeit aktiv ist
        if (\App\Models\Delivery\DeliveryTime::where('is_active', true)->count() === 0) {
            \App\Models\Delivery\DeliveryTime::where('name', 'Standard')->update(['is_active' => true]);
        }
    }
}

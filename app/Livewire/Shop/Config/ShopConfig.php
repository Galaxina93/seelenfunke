<?php

namespace App\Livewire\Shop\Config;

use App\Models\Shipping\ShippingZoneCountry;
use App\Models\ShopSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class ShopConfig extends Component
{
    public $settings = [];

    protected $configKeys = [
        'is_small_business',
        'default_tax_rate',
        'owner_name',
        'owner_proprietor',
        'owner_street',
        'owner_city',
        'owner_email',
        'owner_phone',
        'owner_tax_id',
        'owner_ust_id',
        'owner_website',
        'owner_court',
        'owner_iban',
        'shipping_cost',
        'shipping_free_threshold',
        'express_surcharge',
        'prices_entered_gross',
        'order_quote_validity_days',
        'maintenance_mode',
        'inventory_low_stock_threshold',
        'skip_shipping_for_digital',
        'stripe_publishable_key',
        'stripe_secret_key',
        'stripe_webhook_secret',
        // NEU: Erweiterte Stammdaten
        'owner_finanzamt_nr',
        'owner_social_security_nr',
        'owner_tax_ident_nr',
        'owner_health_insurance_nr',
        'owner_agency_labor_nr',
        'owner_economic_ident_nr'
    ];

    public $infoTexts = [
        'is_small_business' => 'Aktiviert den Hinweis auf § 19 UStG auf Rechnungen und im Checkout. Es wird keine MwSt. berechnet.',
        'default_tax_rate' => 'Der Standard-Steuersatz für deine Produkte (meist 19% in DE).',
        'owner_name' => 'Dein offizieller Shop-Name, wie er im Briefkopf der Rechnung erscheint.',
        'owner_proprietor' => 'Der Vor- und Nachname der Inhaberin (gesetzlich vorgeschrieben).',
        'owner_email' => 'Kontaktadresse für Kundenanfragen und Systembenachrichtigungen.',
        'owner_tax_id' => 'Deine persönliche Steuernummer beim Finanzamt.',
        'owner_ust_id' => 'Umsatzsteuer-Identifikationsnummer für den EU-weiten Handel.',
        'owner_court' => 'Der Gerichtsstand ist bei Streitigkeiten mit gewerblichen Kunden relevant.',
        'owner_iban' => 'Wird auf Rechnungen für die Zahlungsart Vorkasse/Überweisung ausgegeben.',
        'shipping_cost' => 'Standardversandkosten pro Bestellung (Eingabe in Euro).',
        'shipping_free_threshold' => 'Ab diesem Brutto-Warenwert entfallen die Versandkosten automatisch (Eingabe in Euro).',
        'express_surcharge' => 'Zusätzliche Gebühr, wenn der Kunde die Express-Option im Checkout wählt (Eingabe in Euro).',
        'order_quote_validity_days' => 'Legt fest, wie viele Tage ein generiertes PDF-Angebot rechtlich bindend ist.',
        'maintenance_mode' => 'Sperrt den Zugang zum Frontend für Kunden. Nur Admins können den Shop sehen.',
        'inventory_low_stock_threshold' => 'Ab dieser Stückzahl wird ein Produkt im Dashboard als "niedriger Bestand" markiert.',
        'skip_shipping_for_digital' => 'Wenn aktiviert, werden keine Versandkosten berechnet, sobald der Warenkorb NUR digitale Produkte enthält.',
        'stripe_publishable_key' => 'Der öffentliche Schlüssel von Stripe (pk_test_... oder pk_live_...).',
        'stripe_secret_key' => 'Der geheime Schlüssel von Stripe (sk_test_... oder sk_live_...).',
        'stripe_webhook_secret' => 'Das Secret für Webhooks (whsec_...), um Zahlungsbestätigungen zu empfangen.',
        'owner_finanzamt_nr' => 'Steuernummer beim zuständigen Finanzamt.',
        'owner_social_security_nr' => 'Sozialversicherungsnummer (Rentenversicherungsnummer).',
        'owner_tax_ident_nr' => 'Persönliche Steuer-Identifikationsnummer (Steuer-ID).',
        'owner_health_insurance_nr' => 'Mitgliedsnummer bei der Krankenkasse.',
        'owner_agency_labor_nr' => 'Kundennummer bei der Agentur für Arbeit.',
        'owner_economic_ident_nr' => 'Wirtschafts-Identifikationsnummer (W-IdNr.), falls vorhanden.'
    ];

    public $saved = false;

    public function mount()
    {
        $dbSettings = ShopSetting::whereIn('key', $this->configKeys)
            ->pluck('value', 'key')
            ->toArray();

        foreach ($this->configKeys as $key) {
            $value = $dbSettings[$key] ?? $this->getFallback($key);

            if (in_array($key, ['shipping_cost', 'shipping_free_threshold', 'express_surcharge'])) {
                $value = number_format((int)$value / 100, 2, '.', '');
            }

            $this->settings[$key] = $value;
        }

        $boolKeys = ['is_small_business', 'prices_entered_gross', 'maintenance_mode', 'skip_shipping_for_digital'];
        foreach($boolKeys as $key) {
            $this->settings[$key] = filter_var($this->settings[$key], FILTER_VALIDATE_BOOLEAN);
        }
    }

    public function getActiveShippingCountriesProperty()
    {
        $codes = ShippingZoneCountry::pluck('country_code')->toArray();
        $masterList = $this->getMasterCountryList();
        return array_intersect_key($masterList, array_flip($codes));
    }

    private function getFallback($key)
    {
        $fallbacks = [
            'is_small_business' => false,
            'default_tax_rate'  => 19,
            'owner_name'        => 'Mein Seelenfunke',
            'owner_proprietor'  => 'Alina Steinhauer',
            'owner_website'     => 'www.mein-seelenfunke.de',
            'owner_court'       => 'Gifhorn',
            'owner_iban'        => 'DE...',
            'shipping_cost'     => 490,
            'shipping_free_threshold' => 5000,
            'express_surcharge' => 2500,
            'prices_entered_gross' => true,
            'order_quote_validity_days' => 14,
            'maintenance_mode' => false,
            'inventory_low_stock_threshold' => 5,
            'skip_shipping_for_digital' => false,
        ];
        return $fallbacks[$key] ?? '';
    }

    public function save()
    {
        $this->validate([
            'settings.default_tax_rate' => 'required|numeric',
            'settings.owner_email' => 'required|email',
            'settings.shipping_cost' => 'required|numeric',
            'settings.shipping_free_threshold' => 'required|numeric',
            'settings.express_surcharge' => 'required|numeric',
            'settings.inventory_low_stock_threshold' => 'required|integer|min:0',
        ]);

        foreach ($this->settings as $key => $value) {
            $finalValue = $value;

            if (in_array($key, ['shipping_cost', 'shipping_free_threshold', 'express_surcharge'])) {
                $finalValue = (int)round((float)$value * 100);
            }

            ShopSetting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($finalValue) ? ($finalValue ? 'true' : 'false') : $finalValue]
            );
        }

        Cache::forget('global_shop_settings');
        Cache::forget('shop_setting_inventory_threshold');

        $this->saved = true;
    }

    public function resetSaved() { $this->saved = false; }

    public function getMasterCountryList()
    {
        return [
            'DE' => 'Deutschland', 'AT' => 'Österreich', 'NL' => 'Niederlande',
            'BE' => 'Belgien', 'FR' => 'Frankreich', 'IT' => 'Italien',
            'ES' => 'Spanien', 'PL' => 'Polen', 'CZ' => 'Tschechien',
            'DK' => 'Dänemark', 'SE' => 'Schweden', 'FI' => 'Finnland',
            'IE' => 'Irland', 'PT' => 'Portugal', 'GR' => 'Griechenland',
            'HU' => 'Ungarn', 'RO' => 'Rumänien', 'BG' => 'Bulgarien',
            'SK' => 'Slowakei', 'SI' => 'Slowenien', 'HR' => 'Kroatien',
            'EE' => 'Estland', 'LV' => 'Lettland', 'LT' => 'Litauen', 'LU' => 'Luxemburg'
        ];
    }

    public function render() { return view('livewire.shop.config.shop-config'); }
}

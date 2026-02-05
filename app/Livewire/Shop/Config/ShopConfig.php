<?php

namespace App\Livewire\Shop\Config;

use App\Models\ShippingZoneCountry;
use App\Models\ShopSetting;
use Illuminate\Support\Facades\Cache;
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
        'order_quote_validity_days'
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
        'order_quote_validity_days' => 'Legt fest, wie viele Tage ein generiertes PDF-Angebot rechtlich bindend ist.'
    ];

    public $saved = false;

    public function mount()
    {
        $dbSettings = ShopSetting::whereIn('key', $this->configKeys)
            ->pluck('value', 'key')
            ->toArray();

        foreach ($this->configKeys as $key) {
            $value = $dbSettings[$key] ?? $this->getFallback($key);

            // Konvertierung von Cent zu Euro für die Anzeige im Admin
            if (in_array($key, ['shipping_cost', 'shipping_free_threshold', 'express_surcharge'])) {
                $value = number_format((int)$value / 100, 2, '.', '');
            }

            $this->settings[$key] = $value;
        }

        $this->settings['is_small_business'] = filter_var($this->settings['is_small_business'], FILTER_VALIDATE_BOOLEAN);
        $this->settings['prices_entered_gross'] = filter_var($this->settings['prices_entered_gross'], FILTER_VALIDATE_BOOLEAN);
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
        ]);

        foreach ($this->settings as $key => $value) {
            $finalValue = $value;

            // Konvertierung von Euro zurück in Cent für die Datenbank
            if (in_array($key, ['shipping_cost', 'shipping_free_threshold', 'express_surcharge'])) {
                $finalValue = (int)round((float)$value * 100);
            }

            ShopSetting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($finalValue) ? ($finalValue ? 'true' : 'false') : $finalValue]
            );
        }

        Cache::forget('global_shop_settings');
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

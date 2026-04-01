<?php

namespace App\Livewire\Shop\System;

use Livewire\Attributes\Layout;

use App\Models\System\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class SystemShopConfig extends Component
{
    use \App\Livewire\Traits\WithDepartmentTheming;

    protected string $themingDepartment = 'System';
    public $settings = [];

    protected $configKeys = [
        'is_small_business',
        'default_tax_rate',
        // Company Data
        'company_name',
        'company_street',
        'company_street_number',
        'company_zip',
        'company_city',
        'company_country',
        'company_email',
        'company_phone',
        // Owner Data
        'owner_name',
        'owner_proprietor',
        'owner_street',
        'owner_city',
        'owner_email',
        'owner_email_impressum',
        'owner_email_invoices',
        'owner_email_backup',
        'owner_phone',
        'owner_tax_id',
        'owner_ust_id',
        'owner_website',
        'owner_court',
        'owner_bank_name',
        'owner_bank_address',
        'owner_bic',
        'owner_iban',
        'shipping_cost',
        'shipping_free_threshold',
        'express_surcharge',
        'packaging_weight_grams',
        'prices_entered_gross',
        'order_quote_validity_days',
        'maintenance_mode',
        'inventory_low_stock_threshold',
        'skip_shipping_for_digital',
        'stripe_publishable_key',
        'stripe_secret_key',
        'stripe_webhook_secret',
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
        // Company Info
        'company_name' => 'Name des Unternehmens (wird auf allen offiziellen Dokumenten, im Footer und Impressum genutzt).',
        'company_street' => 'Straße der Firmenadresse (für Rechnungen und DHL Retouren).',
        'company_street_number' => 'Hausnummer der Firmenadresse.',
        'company_zip' => 'Postleitzahl der Firmenadresse.',
        'company_city' => 'Stadt der Firmenadresse.',
        'company_country' => 'Länder-Kürzel nach ISO-3166-1 alpha-2 (z.B. DE, AT, CH) für e-Rechnungen und DHL.',
        'company_email' => 'Öffentliche Firmen-Email für den alltäglichen Kundenkontakt.',
        'company_phone' => 'Öffentliche Firmen-Telefonnummer für den Support.',
        // Owner Info
        'owner_name' => 'Ehemaliger Platzhalter, nur noch intern verwendet.',
        'owner_proprietor' => 'Der Vor- und Nachname der Inhaberin (gesetzlich im Impressum als Vertreter vorgeschrieben).',
        'owner_email' => 'Kontaktadresse für Kundenanfragen und Systembenachrichtigungen.',
        'owner_email_impressum' => 'Spezielle E-Mail-Adresse für das Impressum und rechtliche Anfragen.',
        'owner_email_invoices' => 'E-Mail-Adresse als Absender für automatische Rechnungen und buchhalterische Themen.',
        'owner_email_backup' => 'An diese E-Mail-Adresse werden System-Benachrichtigungen und Datenbank-Backups gesendet.',
        'owner_tax_id' => 'Deine persönliche Steuernummer beim Finanzamt.',
        'owner_ust_id' => 'Umsatzsteuer-Identifikationsnummer für den EU-weiten Handel.',
        'owner_court' => 'Der Gerichtsstand ist bei Streitigkeiten mit gewerblichen Kunden relevant.',
        'owner_bank_name' => 'Der Name deines Kreditinstituts (z.B. Volksbank BraWo).',
        'owner_bank_address' => 'Die Adresse deiner Bankfiliale (oft für internationale Überweisungen wichtig).',
        'owner_bic' => 'Der BIC (Business Identifier Code) bzw. SWIFT-Code deiner Bank.',
        'owner_iban' => 'Die IBAN, auf die Kunden ihre Vorkasse-Zahlungen überweisen sollen.',
        'shipping_cost' => 'Standardversandkosten pro Bestellung (Eingabe in Euro).',
        'shipping_free_threshold' => 'Ab diesem Brutto-Warenwert entfallen die Versandkosten automatisch (Eingabe in Euro).',
        'express_surcharge' => 'Zusätzliche Gebühr, wenn der Kunde die Express-Option im Checkout wählt (Eingabe in Euro).',
        'packaging_weight_grams' => 'Das standardmäßige Leergewicht (in Gramm) eines Kartons (inkl. Füllmaterial). Dient zur automatischen Berechnung des DHL-Etikettengewichts.',
        'order_quote_validity_days' => 'Legt fest, wie viele Tage ein generiertes PDF-Angebot rechtlich bindend ist.',
        'maintenance_mode' => 'Sperrt den Zugang zum Shop & Konfigurator. Nur Admins können Wartungen durchführen.',
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
        $dbSettings = SystemSetting::whereIn('key', $this->configKeys)
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

    private function getFallback($key)
    {
        $fallbacks = [
            'is_small_business' => false,
            'default_tax_rate'  => 19,
            'company_name'      => 'Mein Seelenfunke',
            'company_street'    => 'Carl-Goerdeler-Ring',
            'company_street_number' => '26',
            'company_zip'       => '38518',
            'company_city'      => 'Gifhorn',
            'company_country'   => 'DE',
            'company_email'     => 'kontakt@mein-seelenfunke.de',
            'company_phone'     => '+49 159 019 668 64',
            'owner_name'        => 'Mein Seelenfunke',
            'owner_proprietor'  => 'Alina Steinhauer',
            'owner_website'     => 'www.mein-seelenfunke.de',
            'owner_court'       => 'Gifhorn',
            'owner_bank_name'   => 'Volksbank',
            'owner_bank_address'=> '',
            'owner_bic'         => '',
            'owner_iban'        => 'DE...',
            'shipping_cost'     => 490,
            'shipping_free_threshold' => 5000,
            'express_surcharge' => 2500,
            'packaging_weight_grams' => 350,
            'prices_entered_gross' => true,
            'order_quote_validity_days' => 14,
            'maintenance_mode' => false,
            'inventory_low_stock_threshold' => 5,
            'skip_shipping_for_digital' => false,
            'owner_email_impressum' => 'impressum@mein-seelenfunke.de',
            'owner_email_invoices' => 'rechnungen@mein-seelenfunke.de',
            'owner_email_backup' => 'backup@mein-seelenfunke.de',
        ];
        return $fallbacks[$key] ?? '';
    }

    public function save()
    {
        $this->validate([
            'settings.default_tax_rate' => 'required|numeric',
            'settings.owner_email' => 'required|email',
            'settings.owner_email_impressum' => 'nullable|email',
            'settings.owner_email_invoices' => 'nullable|email',
            'settings.owner_email_backup' => 'nullable|email',
            'settings.shipping_cost' => 'required|numeric',
            'settings.shipping_free_threshold' => 'required|numeric',
            'settings.express_surcharge' => 'required|numeric',
            'settings.packaging_weight_grams' => 'required|integer|min:0',
            'settings.inventory_low_stock_threshold' => 'required|integer|min:0',
        ]);

        foreach ($this->settings as $key => $value) {
            $finalValue = $value;

            if (in_array($key, ['shipping_cost', 'shipping_free_threshold', 'express_surcharge'])) {
                $finalValue = (int)round((float)$value * 100);
            }

            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($finalValue) ? ($finalValue ? 'true' : 'false') : $finalValue]
            );
        }

        Cache::forget('global_shop_settings');
        Cache::forget('shop_setting_inventory_threshold');

        $this->saved = true;
    }

    public function resetSaved() { $this->saved = false; }

    public function render() { return view('livewire.shop.system.system-shop-config'); }
}

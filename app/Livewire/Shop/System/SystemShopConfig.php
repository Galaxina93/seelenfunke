<?php

namespace App\Livewire\Shop\System;

use Livewire\Attributes\Layout;

use App\Models\System\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

#[Layout('components.layouts.backend_layout')]
class SystemShopConfig extends Component
{
    use \App\Livewire\Traits\WithDepartmentTheming;
    use WithFileUploads;

    public string $themingDepartment = 'System';
    public $settings = [];
    
    // Updates/Reports System-Info
    public $uploads = [];
    public $selectedReports = [];
    public $reportFiles = [];

    // Kleinunternehmer § 19 UStG Transition Modal
    public bool $showSmallBusinessModal = false;
    public string $smallBusinessTransitionMode = 'immediate_retroactive';
    public ?bool $pendingSmallBusinessState = null;

    protected $configKeys = [
        'is_small_business',
        'small_business_end_year',
        'small_business_scheduled_end_year',
        'small_business_switched_year',
        'small_business_switched_at',
        'default_tax_rate',
        'reduced_tax_rate',
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
        'shipping_cost_voucher',
        'express_surcharge_percent',
        'express_surcharge_min',
        'packaging_weight_grams',
        'packaging_weight_envelope_grams',
        'shipping_buffer_grams',
        'shipping_buffer_envelope_grams',
        'prices_entered_gross',
        'order_quote_validity_days',
        'herzensprojekt_cents_per_order',
        'stripe_fee_percent',
        'stripe_fee_fixed_cents',
        'maintenance_mode',
        'inventory_low_stock_threshold',
        'skip_shipping_for_digital',
        'send_order_email_to_admin',
        'stripe_publishable_key',
        'stripe_secret_key',
        'stripe_webhook_secret',
        'owner_finanzamt_nr',
        'owner_social_security_nr',
        'owner_tax_ident_nr',
        'owner_health_insurance_nr',
        'owner_agency_labor_nr',
        'owner_economic_ident_nr',
        'emergency_keepass_location',
        'emergency_master_password_location',
        'emergency_contact_notary',
        'emergency_contact_tax_advisor',
        'emergency_contact_family',
        'emergency_hardware_pins',
        'emergency_master_password',
        'season_auto_mode',
        'manual_active_season',
        'manual_active_animation'
    ];

    public $infoTexts = [
        'is_small_business' => 'Aktiviert den Hinweis auf § 19 UStG auf Rechnungen und im Checkout. Es wird keine MwSt. berechnet.',
        'default_tax_rate' => 'Der Standard-Steuersatz für deine Produkte (meist 19% in DE).',
        'reduced_tax_rate' => 'Der ermäßigte Steuersatz für entsprechend gekennzeichnete Produkte (meist 7% in DE).',
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
        'owner_email' => 'Zentrale Kontaktadresse für Kundenanfragen, Impressum, Rechnungen und Systembenachrichtigungen.',
        'owner_tax_id' => 'Deine persönliche Steuernummer beim Finanzamt.',
        'owner_ust_id' => 'Umsatzsteuer-Identifikationsnummer für den EU-weiten Handel.',
        'owner_court' => 'Der Gerichtsstand ist bei Streitigkeiten mit gewerblichen Kunden relevant.',
        'owner_bank_name' => 'Der Name deines Kreditinstituts (z.B. Volksbank BraWo).',
        'owner_bank_address' => 'Die Adresse deiner Bankfiliale (oft für internationale Überweisungen wichtig).',
        'owner_bic' => 'Der BIC (Business Identifier Code) bzw. SWIFT-Code deiner Bank.',
        'owner_iban' => 'Die IBAN deines Geschäftskontos (wird im Rechnungs-Footer und bei B2B-Rechnungskauf ausgewiesen).',
        'shipping_cost' => 'Standardversandkosten pro Bestellung (Eingabe in Euro).',
        'shipping_free_threshold' => 'Ab diesem Brutto-Warenwert entfallen die Versandkosten automatisch (Eingabe in Euro).',
        'shipping_cost_voucher' => 'Versandkostenaufpreis für den postalischen Versand von gedruckten Gutscheinen (Eingabe in Euro).',
        'express_surcharge_percent' => 'Prozentualer Aufschlag für Express-Priorisierung (Eingabe in %, z.B. 20 für 20%).',
        'express_surcharge_min' => 'Zwingender Mindestbetrag für den Express-Aufschlag (Eingabe in Euro, z.B. 5.00).',
        'packaging_weight_grams' => 'Das standardmäßige Leergewicht (in Gramm) eines Kartons (inkl. Füllmaterial). Dient zur automatischen Berechnung des DHL-Etikettengewichts.',
        'packaging_weight_envelope_grams' => 'Leergewicht (in Gramm) eines Umschlags / Luftpolsterbriefs für den leichten Kleinteile-Versand.',
        'shipping_buffer_grams' => 'Sicherheits-Gewichtspuffer (in Gramm) für Pakete zur Vermeidung von DHL-Nachportokosten.',
        'shipping_buffer_envelope_grams' => 'Sicherheits-Gewichtspuffer (in Gramm) für Umschläge / Kleinsendungen.',
        'prices_entered_gross' => 'Legt fest, ob die im Admin gepflegten Produktpreise bereits die gesetzliche MwSt. enthalten (Brutto = Standard im B2C).',
        'order_quote_validity_days' => 'Legt fest, wie viele Tage ein generiertes PDF-Angebot rechtlich bindend ist.',
        'herzensprojekt_cents_per_order' => 'Spendenbetrag (in Euro), der pro verkauftem Artikel (bzw. Bestellung) für das Herzensprojekt zurückgelegt wird.',
        'stripe_fee_percent' => 'Kalkulatorischer Prozentsatz für Zahlungsanbieter-Gebühren in der Produkt-Margenberechnung (z.B. 2.50%).',
        'stripe_fee_fixed_cents' => 'Kalkulatorische Fixgebühr pro Transaktion in der Produkt-Margenberechnung (Eingabe in Euro, z.B. 0.30).',
        'maintenance_mode' => 'Sperrt den Zugang zum Shop & Konfigurator. Nur Admins können Wartungen durchführen.',
        'inventory_low_stock_threshold' => 'Ab dieser Stückzahl wird ein Produkt im Dashboard als "niedriger Bestand" markiert.',
        'skip_shipping_for_digital' => 'Wenn aktiviert, werden keine Versandkosten berechnet, sobald der Warenkorb NUR digitale Produkte enthält.',
        'send_order_email_to_admin' => 'Bestimmt, ob der Admin bei Bestelleingang eine E-Mail erhalten soll.',
        'stripe_publishable_key' => 'Der öffentliche Schlüssel von Stripe (pk_test_... oder pk_live_...).',
        'stripe_secret_key' => 'Der geheime Schlüssel von Stripe (sk_test_... oder sk_live_...).',
        'stripe_webhook_secret' => 'Das Secret für Webhooks (whsec_...), um Zahlungsbestätigungen zu empfangen.',
        'owner_finanzamt_nr' => 'Steuernummer beim zuständigen Finanzamt.',
        'owner_social_security_nr' => 'Sozialversicherungsnummer (Rentenversicherungsnummer).',
        'owner_tax_ident_nr' => 'Persönliche Steuer-Identifikationsnummer (Steuer-ID).',
        'owner_health_insurance_nr' => 'Mitgliedsnummer bei der Krankenkasse.',
        'owner_agency_labor_nr' => 'Kundennummer bei der Agentur für Arbeit.',
        'owner_economic_ident_nr' => 'Wirtschafts-Identifikationsnummer (W-IdNr.), falls vorhanden.',
        'emergency_keepass_location' => 'Wo ist die KeePass-Datenbank zu finden? (z.B. USB-Stick Tresor, Dropbox)',
        'emergency_master_password_location' => 'Wo liegt das Master-Passwort für KeePass? (z.B. im roten Ordner, Schließfach 12)',
        'emergency_contact_notary' => 'Name und Telefonnummer des Notars (für Testament und Vollmachten).',
        'emergency_contact_tax_advisor' => 'Name und Telefonnummer des Steuerberaters.',
        'emergency_contact_family' => 'Wichtigster familiärer Notfallkontakt.',
        'emergency_hardware_pins' => 'PINs für Smartphones/Laptops (oder Hinweis auf deren Verwahrort).',
        'emergency_master_password' => 'Master-Passwort für den digitalen Zugang zum Todesfall-Protokoll (/notfall).'
    ];

    public $saved = false;

    public function boot(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $dbSettings = SystemSetting::whereIn('key', $this->configKeys)
            ->pluck('value', 'key')
            ->toArray();

        $requiredFallbackKeys = [
            'default_tax_rate',
            'reduced_tax_rate',
            'shipping_cost',
            'shipping_free_threshold',
            'shipping_cost_voucher',
            'express_surcharge_percent',
            'express_surcharge_min',
            'packaging_weight_grams',
            'packaging_weight_envelope_grams',
            'shipping_buffer_grams',
            'shipping_buffer_envelope_grams',
            'inventory_low_stock_threshold',
            'order_quote_validity_days',
            'herzensprojekt_cents_per_order',
            'stripe_fee_percent',
            'stripe_fee_fixed_cents',
            'company_country',
            'owner_email',
        ];

        foreach ($this->configKeys as $key) {
            $hasDbKey = array_key_exists($key, $dbSettings);
            $value = $dbSettings[$key] ?? null;
            if (!$hasDbKey || $value === null || $value === 'Keine Angabe' || ($value === '' && in_array($key, $requiredFallbackKeys, true))) {
                $value = $this->getFallback($key);
            }

            if (in_array($key, ['shipping_cost', 'shipping_free_threshold', 'express_surcharge_min', 'shipping_cost_voucher', 'herzensprojekt_cents_per_order', 'stripe_fee_fixed_cents'], true)) {
                $rawStr = trim((string) $value);
                $euroFloat = str_contains($rawStr, '.') ? (float) $rawStr : (((int) $rawStr) / 100);
                $value = number_format($euroFloat, 2, '.', '');
            }

            if (in_array($key, ['emergency_master_password', 'stripe_secret_key', 'stripe_webhook_secret'], true)) {
                $value = ''; // Never send sensitive secrets/hashes to the frontend
            }

            $this->settings[$key] = $value;
        }

        $boolKeys = ['is_small_business', 'prices_entered_gross', 'maintenance_mode', 'skip_shipping_for_digital', 'send_order_email_to_admin', 'season_auto_mode'];
        foreach($boolKeys as $key) {
            $this->settings[$key] = filter_var($this->settings[$key], FILTER_VALIDATE_BOOLEAN);
        }

        if (empty($this->settings['manual_active_season'])) {
            $this->settings['manual_active_season'] = app(\App\Services\ConfiguratorService::class)->getCalendarSeasonKey();
        }

        $this->loadReports();
    }

    private function getFallback($key)
    {
        $fallbacks = [
            'is_small_business' => false,
            'small_business_end_year' => '',
            'small_business_scheduled_end_year' => '',
            'small_business_switched_year' => '',
            'small_business_switched_at' => '',
            'default_tax_rate'  => 19,
            'reduced_tax_rate'  => 7,
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
            'owner_street'      => 'Carl-Goerdeler-Ring 26',
            'owner_city'        => '38518 Gifhorn',
            'owner_email'       => 'kontakt@mein-seelenfunke.de',
            'owner_phone'       => '+49 159 019 668 64',
            'owner_tax_id'      => '19/143/11624',
            'owner_ust_id'      => '',
            'owner_website'     => 'www.mein-seelenfunke.de',
            'owner_court'       => 'Gifhorn',
            'owner_bank_name'   => 'Volksbank',
            'owner_bank_address'=> '',
            'owner_bic'         => '',
            'owner_iban'        => '',
            'shipping_cost'     => 490,
            'shipping_free_threshold' => 5000,
            'shipping_cost_voucher' => 350,
            'express_surcharge_percent' => 20,
            'express_surcharge_min' => 500,
            'packaging_weight_grams' => 350,
            'packaging_weight_envelope_grams' => 30,
            'shipping_buffer_grams' => 50,
            'shipping_buffer_envelope_grams' => 10,
            'prices_entered_gross' => true,
            'order_quote_validity_days' => 14,
            'herzensprojekt_cents_per_order' => 50,
            'stripe_fee_percent' => 2.50,
            'stripe_fee_fixed_cents' => 30,
            'maintenance_mode' => false,
            'inventory_low_stock_threshold' => 5,
            'skip_shipping_for_digital' => false,
            'send_order_email_to_admin' => true,
            'emergency_keepass_location' => '\\\\nas\\Unterlagen\\Dokumente\\Keepass',
            'emergency_master_password_location' => '',
            'emergency_contact_notary' => '',
            'emergency_contact_tax_advisor' => '',
            'emergency_contact_family' => 'Jan Steinhauer, Kerstin Steinhauer, Tim Steinhauer',
            'emergency_hardware_pins' => '',
            'season_auto_mode' => true,
            'manual_active_season' => '',
            'manual_active_animation' => '',
        ];
        return $fallbacks[$key] ?? '';
    }

    public function toggleSeasonAutoMode(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $newState = !filter_var($this->settings['season_auto_mode'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->settings['season_auto_mode'] = $newState;

        if ($newState) {
            // Zurück auf Automatik: Manuelle Animations-Übersteuerung zurücksetzen
            $this->settings['manual_active_animation'] = '';
        } else {
            // Beim Wechsel auf Manuell: Aktuelle Kalender-Jahreszeit vorbelegen, falls noch leer
            if (empty($this->settings['manual_active_season']) || !in_array($this->settings['manual_active_season'], ['ostern', 'sommer', 'herbst', 'winter'], true)) {
                $this->settings['manual_active_season'] = app(\App\Services\ConfiguratorService::class)->getCalendarSeasonKey();
            }
            $this->settings['manual_active_animation'] = $this->settings['manual_active_season'];
        }

        $this->persistSeasonSettings();
    }

    public function selectManualSeason(string $seasonKey): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        if (!in_array($seasonKey, ['ostern', 'sommer', 'herbst', 'winter'], true)) {
            return;
        }

        if (filter_var($this->settings['season_auto_mode'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return; // Nur im manuellen Modus ("Automatisch" = Aus) wählbar
        }

        $this->settings['manual_active_season'] = $seasonKey;
        $this->settings['manual_active_animation'] = $seasonKey;

        $this->persistSeasonSettings();
    }

    public function toggleManualAnimation(string $themeKey): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $validThemes = array_keys(app(\App\Services\ConfiguratorService::class)->getBackgroundThemes());
        if (!in_array($themeKey, $validThemes, true)) {
            return;
        }

        $currentManual = (string) ($this->settings['manual_active_animation'] ?? '');
        if ($currentManual === $themeKey) {
            // Haken wieder entfernen
            $this->settings['manual_active_animation'] = '';
        } else {
            // Haken setzen (z. B. "Schnee" aktivieren)
            $this->settings['manual_active_animation'] = $themeKey;
            if (in_array($themeKey, ['ostern', 'sommer', 'herbst', 'winter'], true) && !filter_var($this->settings['season_auto_mode'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $this->settings['manual_active_season'] = $themeKey;
            }
        }

        $this->persistSeasonSettings();
    }

    protected function persistSeasonSettings(): void
    {
        foreach (['season_auto_mode', 'manual_active_season', 'manual_active_animation'] as $key) {
            $val = $this->settings[$key] ?? '';
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($val) ? ($val ? 'true' : 'false') : (string) $val]
            );
        }

        Cache::forget('global_shop_settings');
        try {
            $freshSettings = SystemSetting::pluck('value', 'key');
            Cache::put('global_shop_settings', $freshSettings, 60);
        } catch (\Throwable $e) {}
    }

    public function save()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        if (!empty($this->settings['company_country'])) {
            $this->settings['company_country'] = strtoupper(trim((string) $this->settings['company_country']));
        }

        try {
            $this->validate([
                'settings.default_tax_rate' => 'required|numeric|min:0|max:100',
                'settings.reduced_tax_rate' => 'required|numeric|min:0|max:100',
                'settings.company_email' => 'nullable|email',
                'settings.company_country' => 'nullable|string|size:2',
                'settings.owner_email' => 'required|email',
                'settings.shipping_cost' => 'required|numeric|min:0',
                'settings.shipping_free_threshold' => 'required|numeric|min:0',
                'settings.shipping_cost_voucher' => 'required|numeric|min:0',
                'settings.express_surcharge_percent' => 'required|numeric|min:0|max:100',
                'settings.express_surcharge_min' => 'required|numeric|min:0',
                'settings.packaging_weight_grams' => 'required|integer|min:0',
                'settings.packaging_weight_envelope_grams' => 'required|integer|min:0',
                'settings.shipping_buffer_grams' => 'required|integer|min:0',
                'settings.shipping_buffer_envelope_grams' => 'required|integer|min:0',
                'settings.inventory_low_stock_threshold' => 'required|integer|min:0',
                'settings.order_quote_validity_days' => 'nullable|integer|min:1|max:365',
                'settings.herzensprojekt_cents_per_order' => 'required|numeric|min:0',
                'settings.stripe_fee_percent' => 'required|numeric|min:0|max:100',
                'settings.stripe_fee_fixed_cents' => 'required|numeric|min:0',
                'settings.emergency_keepass_location' => 'nullable|string',
                'settings.emergency_master_password_location' => 'nullable|string',
                'settings.emergency_contact_notary' => 'nullable|string',
                'settings.emergency_contact_tax_advisor' => 'nullable|string',
                'settings.emergency_contact_family' => 'nullable|string',
                'settings.emergency_hardware_pins' => 'nullable|string',
                'settings.emergency_master_password' => 'nullable|string',
            ], [], [
                'settings.default_tax_rate' => 'Standard-Steuersatz',
                'settings.reduced_tax_rate' => 'Ermäßigter Steuersatz',
                'settings.company_email' => 'Öffentliche Firmen-E-Mail',
                'settings.company_country' => 'Länder-Kürzel',
                'settings.owner_email' => 'Zentrale Kontakt-E-Mail',
                'settings.shipping_cost' => 'Standard Versandkosten',
                'settings.shipping_free_threshold' => 'Versandkostenfrei-Grenze',
                'settings.shipping_cost_voucher' => 'Versandkosten Post-Gutscheine',
                'settings.express_surcharge_percent' => 'Express-Zuschlag (%)',
                'settings.express_surcharge_min' => 'Express Mindestwert',
                'settings.packaging_weight_grams' => 'Tara-Gewicht pro Paket',
                'settings.packaging_weight_envelope_grams' => 'Tara-Gewicht Umschlag',
                'settings.shipping_buffer_grams' => 'Gewichtspuffer Paket',
                'settings.shipping_buffer_envelope_grams' => 'Gewichtspuffer Umschlag',
                'settings.inventory_low_stock_threshold' => 'Warn-Schwellenwert Lagerbestand',
                'settings.order_quote_validity_days' => 'Gültigkeit von Angeboten (Tage)',
                'settings.herzensprojekt_cents_per_order' => 'Herzensprojekt Spendenbetrag',
                'settings.stripe_fee_percent' => 'Stripe Gebühr (%)',
                'settings.stripe_fee_fixed_cents' => 'Stripe Fixgebühr (€)',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstKey = (string) collect($e->validator->errors()->keys())->first();
            $targetTab = match (true) {
                str_starts_with($firstKey, 'settings.company_'),
                str_starts_with($firstKey, 'settings.owner_') => 'owner',
                str_starts_with($firstKey, 'settings.shipping_'),
                str_starts_with($firstKey, 'settings.express_'),
                str_starts_with($firstKey, 'settings.packaging_') => 'shipping',
                $firstKey === 'settings.inventory_low_stock_threshold' => 'products',
                str_starts_with($firstKey, 'settings.emergency_') => 'emergency',
                default => 'general',
            };
            $this->dispatch('config-validation-failed', tab: $targetTab);
            throw $e;
        }

        $protectedTaxTransitionKeys = [
            'is_small_business',
            'small_business_end_year',
            'small_business_scheduled_end_year',
            'small_business_switched_year',
            'small_business_switched_at',
        ];

        foreach ($this->settings as $key => $value) {
            if (!in_array($key, $this->configKeys, true) || in_array($key, $protectedTaxTransitionKeys, true)) {
                continue;
            }
            if ($key === 'emergency_master_password') {
                if (!empty($value)) {
                    SystemSetting::updateOrCreate(
                        ['key' => $key],
                        ['value' => \Illuminate\Support\Facades\Hash::make($value)]
                    );
                    $this->settings[$key] = ''; // clear from view
                }
                continue;
            }
            if (in_array($key, ['stripe_secret_key', 'stripe_webhook_secret'], true)) {
                if (!empty($value)) {
                    SystemSetting::updateOrCreate(
                        ['key' => $key],
                        ['value' => $value]
                    );
                    if ($key === 'stripe_secret_key') {
                        \Illuminate\Support\Facades\Config::set('services.stripe.secret', $value);
                    } elseif ($key === 'stripe_webhook_secret') {
                        \Illuminate\Support\Facades\Config::set('services.stripe.webhook.secret', $value);
                        \Illuminate\Support\Facades\Config::set('services.stripe.webhook_secret', $value);
                    }
                    $this->settings[$key] = '';
                }
                continue;
            }

            if ($key === 'stripe_publishable_key' && !empty($value)) {
                \Illuminate\Support\Facades\Config::set('services.stripe.key', $value);
            }

            if ($key === 'owner_email' && !empty($value)) {
                \Illuminate\Support\Facades\Config::set('backup.notifications.mail.to', $value);
            }

            $finalValue = $value;

            if (in_array($key, ['shipping_cost', 'shipping_free_threshold', 'express_surcharge_min', 'shipping_cost_voucher', 'herzensprojekt_cents_per_order', 'stripe_fee_fixed_cents'], true)) {
                $normalizedFloat = (float) str_replace(',', '.', (string) $value);
                $finalValue = (int) round($normalizedFloat * 100);
                $this->settings[$key] = number_format($normalizedFloat, 2, '.', '');
            }

            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($finalValue) ? ($finalValue ? 'true' : 'false') : (string) ($finalValue ?? '')]
            );
        }

        // Synchronisiere active_tax_rates mit default_tax_rate und reduced_tax_rate
        $syncRates = array_values(array_unique([
            (float) ($this->settings['default_tax_rate'] ?? 19),
            (float) ($this->settings['reduced_tax_rate'] ?? 7),
            0.0,
        ]));
        rsort($syncRates);
        SystemSetting::updateOrCreate(
            ['key' => 'active_tax_rates'],
            ['value' => json_encode($syncRates)]
        );

        Cache::forget('global_shop_settings');
        Cache::forget('shop_setting_inventory_threshold');

        // Cache sofort mit den frischen Werten neu befüllen, damit Änderungen augenblicklich aktiv sind
        try {
            $freshSettings = SystemSetting::pluck('value', 'key');
            Cache::put('global_shop_settings', $freshSettings, 60);
        } catch (\Throwable $e) {}

        $this->saved = true;
    }

    public function initiateSmallBusinessToggle()
    {
        $currentState = (bool)($this->settings['is_small_business'] ?? false);
        $this->pendingSmallBusinessState = !$currentState;
        $this->smallBusinessTransitionMode = 'immediate_retroactive';
        $this->showSmallBusinessModal = true;
    }

    public function cancelSmallBusinessTransition()
    {
        $this->showSmallBusinessModal = false;
        $this->pendingSmallBusinessState = null;
    }

    public function confirmSmallBusinessTransition()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $targetState = (bool)$this->pendingSmallBusinessState;
        $mode = in_array($this->smallBusinessTransitionMode, ['immediate_retroactive', 'next_year', 'immediate'], true)
            ? $this->smallBusinessTransitionMode
            : 'immediate_retroactive';

        app(\App\Services\Tax\TaxSettingService::class)->applyTransition($targetState, $mode);

        // Einstellungen direkt in Livewire-State aktualisieren
        $this->settings['is_small_business'] = (bool)shop_setting('is_small_business', false);
        $this->settings['small_business_end_year'] = shop_setting('small_business_end_year', '');
        $this->settings['small_business_scheduled_end_year'] = shop_setting('small_business_scheduled_end_year', '');
        $this->settings['small_business_switched_year'] = shop_setting('small_business_switched_year', '');
        $this->settings['small_business_switched_at'] = shop_setting('small_business_switched_at', '');

        $this->showSmallBusinessModal = false;
        $this->pendingSmallBusinessState = null;
        $this->saved = true;
    }

    public function getTaxStatusSummaryProperty(): array
    {
        $isSmallBusiness = (bool)($this->settings['is_small_business'] ?? false);
        $scheduledEndYear = $this->settings['small_business_scheduled_end_year'] ?? '';
        $switchedYear = $this->settings['small_business_switched_year'] ?? '';
        $switchedAt = $this->settings['small_business_switched_at'] ?? '';
        $currentYear = (int)now()->year;

        if ($scheduledEndYear && (int)$scheduledEndYear === $currentYear) {
            return [
                'type' => 'scheduled',
                'badge' => "Geplanter Wechsel zum 01.01." . ($currentYear + 1),
                'title' => "Geplanter Wechsel zum 01.01." . ($currentYear + 1),
                'desc' => "Bis zum 31.12.{$currentYear} bleibt dein Shop Kleinunternehmer (§ 19 UStG). Ab dem 01.01." . ($currentYear + 1) . " wird automatisch zur Regelbesteuerung gewechselt.",
                'badge_color' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30'
            ];
        }

        if ($switchedYear && (int)$switchedYear === $currentYear) {
            $switchDateFormatted = $switchedAt ? \Carbon\Carbon::parse($switchedAt)->format('d.m.Y') : 'im laufenden Jahr';
            return [
                'type' => 'midyear_switch',
                'badge' => "Regelbesteuerung (Unterjähriger Wechsel)",
                'title' => "Unterjährig gewechselt am {$switchDateFormatted}",
                'desc' => "Gemäß § 19 UStG ist für das gesamte Steuerjahr {$currentYear} MwSt fällig. Für Verkäufe vor dem {$switchDateFormatted} errechnet die UStVA-Zentrale die fällige Steuer automatisch aus dem Brutto heraus.",
                'badge_color' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30'
            ];
        }

        if ($isSmallBusiness) {
            return [
                'type' => 'active',
                'badge' => "Kleinunternehmer (§ 19 UStG)",
                'title' => "Kleinunternehmerregelung aktiv",
                'desc' => "Gilt für das gesamte Steuerjahr {$currentYear}. Es wird keine MwSt berechnet und kein Vorsteuerabzug geltend gemacht.",
                'badge_color' => 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/30'
            ];
        }

        $taxRate = $this->settings['default_tax_rate'] ?? 19;
        return [
            'type' => 'standard',
            'badge' => "Regelbesteuerung ({$taxRate}%)",
            'title' => "Regelbesteuerung aktiv",
            'desc' => "Im Shop und auf Rechnungen wird die gesetzliche MwSt ({$taxRate}%) ausgewiesen und Vorsteuerabzug gewährt.",
            'badge_color' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30'
        ];
    }

    public function resetSaved() { $this->saved = false; }

    public function generateEmergencyPdf()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $this->save(); // Save current state before generating PDF
        
        $groups = \App\Models\Accounting\AccountingGroup::with('items')
            ->where('admin_id', Auth::guard('admin')->id())
            ->get();
        $date = now()->format('d.m.Y H:i');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.emergency-plan', [
            'settings' => $this->settings,
            'groups' => $groups,
            'date' => $date
        ])->setOptions(['isRemoteEnabled' => false]);

        $fileName = 'notfall_handbuch_' . now()->format('Y_m_d_H_i') . '.pdf';
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }

    // --- SYSTEM INFO METHODS (MIGRATED) ---
    public function loadReports()
    {
        try {
            Storage::makeDirectory('shopverwaltung/reports/laravel-updates');
            $files = Storage::files('shopverwaltung/reports/laravel-updates');
            rsort($files);
            $this->reportFiles = $files;
        } catch (\Exception $e) {
            $this->reportFiles = [];
        }
    }

    public function toggleReport($filename)
    {
        if (in_array($filename, $this->selectedReports)) {
            $this->selectedReports = array_diff($this->selectedReports, [$filename]);
        } else {
            $this->selectedReports[] = $filename;
        }
    }

    public function saveUploads()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $this->validate([
            'uploads' => 'required|array|min:1|max:20',
            'uploads.*' => 'file|max:10240|mimes:txt,log,md,pdf,json',
        ], [], [
            'uploads' => 'Berichtsdateien',
            'uploads.*' => 'Berichtsdatei',
        ]);

        $allowedReportExts = ['txt', 'log', 'md', 'pdf', 'json'];
        $allowedReportMimes = [
            'text/plain',
            'text/markdown',
            'text/x-markdown',
            'text/x-log',
            'application/pdf',
            'application/json',
            'application/octet-stream',
            'application/x-empty',
        ];
        $savedCount = 0;
        foreach ($this->uploads as $file) {
            $rawName = basename((string) $file->getClientOriginalName());
            $ext = strtolower(pathinfo($rawName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedReportExts, true)) {
                $guessed = strtolower((string) $file->guessExtension());
                $ext = in_array($guessed, $allowedReportExts, true) ? $guessed : 'txt';
            }

            $realPath = $file->getRealPath();
            if ($realPath && is_file($realPath)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo ? (string) finfo_file($finfo, $realPath) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }
                $isAllowedMime = $detectedMime === ''
                    || in_array($detectedMime, $allowedReportMimes, true)
                    || (in_array($ext, ['txt', 'log', 'md', 'json'], true) && str_starts_with($detectedMime, 'text/'));

                if (!$isAllowedMime) {
                    continue;
                }
            }

            $base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) pathinfo($rawName, PATHINFO_FILENAME));
            $base = trim((string) $base, '._-') ?: ('report_' . time());
            $filename = $base . '.' . $ext;
            // Store directly in our updates folder
            $file->storeAs('shopverwaltung/reports/laravel-updates', $filename);
            $savedCount++;
        }

        $this->uploads = [];
        $this->loadReports();
        
        if ($savedCount > 0) {
            session()->flash('message', 'Dateien erfolgreich hochgeladen.');
        } else {
            $this->addError('uploads', 'Keine gültigen Berichtsdateien (.txt, .log, .md, .pdf, .json) erkannt.');
        }
    }

    public function deleteReport($filename)
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $base = basename($filename);
        $safePath = 'shopverwaltung/reports/laravel-updates/' . $base;

        if (Storage::exists($safePath)) {
            Storage::delete($safePath);
            $this->selectedReports = array_diff($this->selectedReports, [$filename, $safePath]);
            $this->loadReports();
            session()->flash('message', 'Bericht gelöscht.');
        }
    }

    public function getReportContent($filename)
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $base = basename($filename);
        $safePath = 'shopverwaltung/reports/laravel-updates/' . $base;

        if (Storage::exists($safePath)) {
            return Storage::get($safePath);
        }
        return 'Fehler: Bericht nicht gefunden.';
    }

    public function render() 
    { 
        $laravelVersion = app()->version();
        $phpVersion = PHP_VERSION;
        
        $pythonVersion = 'Nicht installiert';
        try {
            $pythonBinary = config('services.ai.python_binary', 'python3');
            $pythonOutput = @shell_exec(escapeshellarg($pythonBinary) . ' --version 2>&1');
            if ($pythonOutput && preg_match('/Python\s+([0-9a-zA-Z.-]+)/i', $pythonOutput, $matches)) {
                $pythonVersion = $matches[1];
            }
        } catch (\Exception $e) {
            // Fallback remains
        }

        $livewireVersion = 'Unbekannt';
        try {
            if (class_exists(\Composer\InstalledVersions::class)) {
                $livewireVersion = \Composer\InstalledVersions::getVersion('livewire/livewire') ?? 'Unbekannt';
                if (strpos($livewireVersion, '@') !== false) {
                    $livewireVersion = explode('@', $livewireVersion)[0];
                }
                if (str_starts_with($livewireVersion, 'v')) {
                    $livewireVersion = substr($livewireVersion, 1);
                }
            }
        } catch (\Exception $e) {
            // Fallback remains
        }
        
        $configService = app(\App\Services\ConfiguratorService::class);

        $databaseDriverLabel = 'MySQL';
        try {
            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
            $dbName = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
            if ($driver === 'sqlite') {
                $databaseDriverLabel = $dbName === ':memory:' ? 'SQLite (In-Memory)' : 'SQLite';
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $verRow = \Illuminate\Support\Facades\DB::selectOne('SELECT VERSION() as v');
                $rawVer = (string) ($verRow->v ?? '');
                $engineName = stripos($rawVer, 'mariadb') !== false ? 'MariaDB' : 'MySQL';
                $shortVer = preg_match('/^(\d+\.\d+)/', $rawVer, $m) ? $m[1] : '';
                $databaseDriverLabel = trim($engineName . ' ' . $shortVer);
            } else {
                $databaseDriverLabel = strtoupper($driver);
            }
        } catch (\Throwable $e) {
            // Fallback bleibt 'MySQL'
        }
        
        return view('livewire.shop.system.system-shop-config', [
            'laravelVersion'         => $laravelVersion,
            'phpVersion'             => $phpVersion,
            'pythonVersion'          => $pythonVersion,
            'livewireVersion'        => $livewireVersion,
            'databaseDriverLabel'    => $databaseDriverLabel,
            'seasonsDefinition'      => $configService->getSeasonsDefinition(),
            'activeSeasonKey'        => $configService->getActiveSeasonKey(),
            'animationTimeline'      => $configService->getAnimationTimeline(),
            'currentActiveAnimation' => $configService->getCurrentActiveTheme(),
            'backgroundThemes'       => $configService->getBackgroundThemes(),
        ]);
    }
}

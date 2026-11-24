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
    public bool $shop_is_live = false;
    
    // Updates/Reports System-Info
    public $uploads = [];
    public $selectedReports = [];
    public $reportFiles = [];

    // Funkenwerk Android OTA Release Vault
    public $androidApkUpload = null;
    public int $androidVersionCode = 13;
    public string $androidVersionName = '1.6.0';

    // Funkenwerk Windows Suite OTA Release Vault
    public $windowsSuiteUpload = null;
    public int $windowsVersionCode = 1;
    public string $windowsVersionName = '1.0.0';

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
        'shop_is_live',
        'shop_live_unlocked',
        'maintenance_mode',
        'showroom_multiplayer_enabled',
        'quote_calculator_enabled',
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
        'manual_active_animation',
        'google_tag_enabled',
        'google_tag_id',
        'google_ads_conversion_label',
        'google_shopping_require_real_photos',
    ];

    public $infoTexts = [
        'is_small_business' => 'Aktiviert den Hinweis auf § 19 UStG auf Rechnungen und im Checkout (keine MwSt.-Berechnung).',
        'default_tax_rate' => 'Regulärer Steuersatz für Produkte (in DE 19 %).',
        'reduced_tax_rate' => 'Ermäßigter Steuersatz für entsprechend markierte Produkte (in DE 7 %).',
        // Company Info
        'company_name' => 'Offizieller Shop- und Markenname für Impressum, Rechnungen und Footer.',
        'company_street' => 'Straße der Firmenadresse.',
        'company_street_number' => 'Hausnummer der Firmenadresse.',
        'company_zip' => 'Postleitzahl der Firmenadresse.',
        'company_city' => 'Stadt der Firmenadresse.',
        'company_country' => 'Zweistelliges ISO-Länderkürzel (z. B. DE, AT, CH) für E-Rechnungen und DHL.',
        'company_email' => 'Öffentliche E-Mail-Adresse für Kundenanfragen.',
        'company_phone' => 'Öffentliche Telefonnummer für den Support.',
        // Owner Info
        'owner_name' => 'Interne Bezeichnung der Inhaberin.',
        'owner_proprietor' => 'Vor- und Nachname der Inhaberin für die gesetzliche Impressumspflicht.',
        'owner_email' => 'Zentrale Adresse für Impressum, Rechnungen und Systemmeldungen.',
        'owner_tax_id' => 'Steuernummer beim zuständigen Finanzamt.',
        'owner_ust_id' => 'Umsatzsteuer-Identifikationsnummer für den EU-Handel.',
        'owner_court' => 'Zuständiges Amtsgericht bei gewerblichen Streitigkeiten.',
        'owner_bank_name' => 'Name deiner Geschäftsbank.',
        'owner_bank_address' => 'Optionale Anschrift der Bank für Auslandsüberweisungen.',
        'owner_bic' => 'BIC / SWIFT-Code deiner Geschäftsbank.',
        'owner_iban' => 'IBAN deines Geschäftskontos für Rechnungs-Footer und B2B-Vorkasse.',
        'shipping_cost' => 'Standardversandkosten pro Bestellung in Euro.',
        'shipping_free_threshold' => 'Brutto-Warenwert in Euro, ab dem der Versand kostenlos ist.',
        'shipping_cost_voucher' => 'Versandaufpreis in Euro für physische Gutscheinkarten per Post.',
        'express_surcharge_percent' => 'Prozentualer Aufschlag auf den Warenwert bei Express-Fertigung.',
        'express_surcharge_min' => 'Mindestbetrag in Euro für den Express-Zuschlag.',
        'packaging_weight_grams' => 'Leergewicht eines Versandkartons inkl. Polsterung in Gramm für DHL-Labels.',
        'packaging_weight_envelope_grams' => 'Leergewicht eines Versand-Umschlags in Gramm für Kleinsendungen.',
        'shipping_buffer_grams' => 'Sicherheits-Gewichtspuffer in Gramm für Pakete.',
        'shipping_buffer_envelope_grams' => 'Sicherheits-Gewichtspuffer in Gramm für Umschläge.',
        'prices_entered_gross' => 'Aktiviert die Bruttopreis-Pflege inkl. gesetzlicher MwSt. im Admin.',
        'order_quote_validity_days' => 'Bindungsfrist in Tagen für erstellte B2B-Angebote.',
        'herzensprojekt_cents_per_order' => 'Betrag in Euro, der pro Artikel für regionale Kinderprojekte zurückgelegt wird.',
        'stripe_fee_percent' => 'Kalkulatorischer Prozentsatz für Zahlungsgebühren in der Margenberechnung.',
        'stripe_fee_fixed_cents' => 'Kalkulatorische Fixgebühr in Euro pro Transaktion in der Margenberechnung.',
        'shop_is_live' => 'Zweiter Hauptschalter vor Verkaufsstart: Schaltet mein-seelenfunke.de öffentlich live (deaktiviert = Vorbereitungsmodus).',
        'shop_live_unlocked' => 'Hauptschalter für den personalisierten Manufaktur-Shop auf mein-seelenfunke.de.',
        'maintenance_mode' => 'Pausiert Warenkorb, Checkout und Konfigurator, während das Schaufenster (Startseite, Sortiment, Magazin) sichtbar bleibt.',
        'showroom_multiplayer_enabled' => 'Aktiviert Mehrspieler-Instanzen und Raum-Chat in der 3D-Welt (deaktiviert = Solo-Modus).',
        'quote_calculator_enabled' => 'Schaltet den B2B-Angebotskalkulator unter /calculator im Frontend sichtbar.',
        'inventory_low_stock_threshold' => 'Stückzahl-Grenze, ab der ein Produkt im Dashboard als kritischer Bestand gemeldet wird.',
        'skip_shipping_for_digital' => 'Erlässt die Versandkosten automatisch bei rein digitalen Warenkörben.',
        'send_order_email_to_admin' => 'Sendet bei jeder neuen Bestellung eine Kopie per E-Mail an den Admin.',
        'stripe_publishable_key' => 'Öffentlicher Stripe-Key (pk_test_... oder pk_live_...).',
        'stripe_secret_key' => 'Geheimer Stripe-Key (sk_test_... oder sk_live_...).',
        'stripe_webhook_secret' => 'Stripe Webhook-Secret (whsec_...) für Zahlungsbestätigungen.',
        'owner_finanzamt_nr' => 'Nummer des zuständigen Finanzamts.',
        'owner_social_security_nr' => 'Persönliche Sozialversicherungsnummer.',
        'owner_tax_ident_nr' => 'Persönliche 11-stellige Steuer-Identifikationsnummer.',
        'owner_health_insurance_nr' => 'Mitgliedsnummer der Krankenkasse.',
        'owner_agency_labor_nr' => 'Betriebsnummer bei der Agentur für Arbeit.',
        'owner_economic_ident_nr' => 'Wirtschafts-Identifikationsnummer (W-IdNr.).',
        'emergency_keepass_location' => 'Verwahrort der KeePass-Datenbank (z. B. Tresor-USB-Stick, NAS).',
        'emergency_master_password_location' => 'Physischer Verwahrort des Master-Passworts (z. B. Notfall-Ordner, Tresor).',
        'emergency_contact_notary' => 'Name und Kontaktdaten des Notars für Vollmachten und Nachlass.',
        'emergency_contact_tax_advisor' => 'Name und Kontaktdaten der Steuerkanzlei.',
        'emergency_contact_family' => 'Wichtigster familiärer Ansprechpartner im Ernstfall.',
        'emergency_hardware_pins' => 'Verwahrort oder Hinweis zu den Geräte-PINs (Smartphone/PC).',
        'emergency_master_password' => 'Passwort für den geschützten Zugriff auf den digitalen Notfall-Assistenten (/notfall).',
        'google_tag_enabled' => 'Lädt den Google Tag (gtag.js) im Frontend nach erteiltem Marketing-Consent.',
        'google_tag_id' => 'Google Tag oder Ads-ID (z. B. AW-..., G-... oder GTM-...).',
        'google_ads_conversion_label' => 'Conversion-Label für abgeschlossene Bestellungen in Google Ads.',
        'google_shopping_require_real_photos' => 'Schließt Produkte ohne echte Fotos (nur KI-Bilder) aus dem Google Shopping-Feed aus.',
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

        $boolKeys = ['is_small_business', 'prices_entered_gross', 'shop_is_live', 'shop_live_unlocked', 'maintenance_mode', 'showroom_multiplayer_enabled', 'quote_calculator_enabled', 'skip_shipping_for_digital', 'send_order_email_to_admin', 'season_auto_mode', 'google_tag_enabled', 'google_shopping_require_real_photos'];
        foreach($boolKeys as $key) {
            $this->settings[$key] = filter_var($this->settings[$key], FILTER_VALIDATE_BOOLEAN);
        }

        $hasExplicitShopIsLive = array_key_exists('shop_is_live', $dbSettings)
            && $dbSettings['shop_is_live'] !== null
            && $dbSettings['shop_is_live'] !== '';
        $hasExplicitLegacyUnlocked = array_key_exists('shop_live_unlocked', $dbSettings)
            && $dbSettings['shop_live_unlocked'] !== null
            && $dbSettings['shop_live_unlocked'] !== '';

        if ($hasExplicitShopIsLive) {
            $resolvedShopIsLive = filter_var($dbSettings['shop_is_live'], FILTER_VALIDATE_BOOLEAN);
        } elseif (\App\Http\Middleware\EnforceShopMaintenanceMode::isLiveHost(request())) {
            $resolvedShopIsLive = false;
        } elseif ($hasExplicitLegacyUnlocked) {
            $resolvedShopIsLive = filter_var($dbSettings['shop_live_unlocked'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $resolvedShopIsLive = true;
        }

        $this->shop_is_live = $resolvedShopIsLive;
        $this->settings['shop_is_live'] = $resolvedShopIsLive;
        $this->settings['shop_live_unlocked'] = $resolvedShopIsLive;

        if (empty($this->settings['manual_active_season'])) {
            $this->settings['manual_active_season'] = app(\App\Services\ConfiguratorService::class)->getCalendarSeasonKey();
        }

        $this->loadReports();

        $existingAndroid = app(\App\Services\System\AndroidReleaseService::class)->getLatestManifest();
        if ($existingAndroid !== null) {
            $this->androidVersionCode = max(1, (int) ($existingAndroid['version_code'] ?? 13));
            $this->androidVersionName = (string) ($existingAndroid['version_name'] ?? '1.6.0');
        }

        $existingWindows = app(\App\Services\System\WindowsReleaseService::class)->getLatestManifest();
        if ($existingWindows !== null) {
            $this->windowsVersionCode = max(1, (int) ($existingWindows['version_code'] ?? 1));
            $this->windowsVersionName = (string) ($existingWindows['version_name'] ?? '1.0.0');
        }
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
            'shipping_cost_voucher' => 450,
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
            'shop_is_live' => !\App\Http\Middleware\EnforceShopMaintenanceMode::isLiveHost(request()),
            'shop_live_unlocked' => !\App\Http\Middleware\EnforceShopMaintenanceMode::isLiveHost(request()),
            'maintenance_mode' => false,
            'showroom_multiplayer_enabled' => true,
            'quote_calculator_enabled' => true,
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
            'google_tag_enabled' => true,
            'google_tag_id' => 'AW-18579999677',
            'google_ads_conversion_label' => 'cZPCCMjkirodEL2X0ZtF',
            'google_shopping_require_real_photos' => true,
        ];
        return $fallbacks[$key] ?? '';
    }

    public function toggleShopIsLive(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $newState = !$this->shop_is_live;
        $this->persistShopIsLive($newState);
    }

    public function updatedShopIsLive($value): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $this->persistShopIsLive(filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    public function updatedSettingsShopIsLive($value): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $this->persistShopIsLive(filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    public function toggleShopLiveUnlocked(): void
    {
        $this->toggleShopIsLive();
    }

    public function updatedSettingsShopLiveUnlocked($value): void
    {
        $this->updatedSettingsShopIsLive($value);
    }

    protected function persistShopIsLive(bool $isLive): void
    {
        $this->shop_is_live = $isLive;
        $this->settings['shop_is_live'] = $isLive;
        $this->settings['shop_live_unlocked'] = $isLive;

        $strValue = $isLive ? 'true' : 'false';

        SystemSetting::updateOrCreate(
            ['key' => 'shop_is_live'],
            ['value' => $strValue]
        );
        SystemSetting::updateOrCreate(
            ['key' => 'shop_live_unlocked'],
            ['value' => $strValue]
        );

        Cache::forget('global_shop_settings');
        try {
            $freshSettings = SystemSetting::pluck('value', 'key');
            Cache::put('global_shop_settings', $freshSettings, 60);
        } catch (\Throwable $e) {
        }

        $this->saved = true;
    }

    public function toggleMaintenanceMode(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $newState = !filter_var($this->settings['maintenance_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->settings['maintenance_mode'] = $newState;
        $this->persistMaintenanceMode($newState);
    }

    public function updatedSettingsMaintenanceMode($value): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $boolState = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->settings['maintenance_mode'] = $boolState;
        $this->persistMaintenanceMode($boolState);
    }

    protected function persistMaintenanceMode(bool $enabled): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'maintenance_mode'],
            ['value' => $enabled ? 'true' : 'false']
        );

        Cache::forget('global_shop_settings');
        try {
            $freshSettings = SystemSetting::pluck('value', 'key');
            Cache::put('global_shop_settings', $freshSettings, 60);
        } catch (\Throwable $e) {
        }

        $this->saved = true;
    }

    public function toggleShowroomMultiplayer(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $newState = !filter_var($this->settings['showroom_multiplayer_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->settings['showroom_multiplayer_enabled'] = $newState;
        $this->persistShowroomMultiplayer($newState);
    }

    public function updatedSettingsShowroomMultiplayerEnabled($value): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $boolState = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->settings['showroom_multiplayer_enabled'] = $boolState;
        $this->persistShowroomMultiplayer($boolState);
    }

    protected function persistShowroomMultiplayer(bool $enabled): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'showroom_multiplayer_enabled'],
            ['value' => $enabled ? '1' : '0']
        );

        Cache::forget('global_shop_settings');
        try {
            $freshSettings = SystemSetting::pluck('value', 'key');
            Cache::put('global_shop_settings', $freshSettings, 60);
        } catch (\Throwable $e) {
        }

        if ($enabled) {
            \App\Services\Gamification\LobbyInstanceService::forgetMultiplayerEnabledCache();
        } else {
            app(\App\Services\Gamification\LobbyInstanceService::class)->flushAllMultiplayerState();
        }

        $this->saved = true;
    }

    public function toggleQuoteCalculator(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $newState = !filter_var($this->settings['quote_calculator_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->settings['quote_calculator_enabled'] = $newState;
        $this->persistQuoteCalculator($newState);
    }

    public function updatedSettingsQuoteCalculatorEnabled($value): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $boolState = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->settings['quote_calculator_enabled'] = $boolState;
        $this->persistQuoteCalculator($boolState);
    }

    protected function persistQuoteCalculator(bool $enabled): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'quote_calculator_enabled'],
            ['value' => $enabled ? 'true' : 'false']
        );

        Cache::forget('global_shop_settings');
        try {
            $freshSettings = SystemSetting::pluck('value', 'key');
            Cache::put('global_shop_settings', $freshSettings, 60);
        } catch (\Throwable $e) {
        }

        $this->saved = true;
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

        if (array_key_exists('google_tag_id', $this->settings)) {
            $this->settings['google_tag_id'] = strtoupper(trim((string) $this->settings['google_tag_id']));
        }

        if (array_key_exists('google_ads_conversion_label', $this->settings)) {
            $this->settings['google_ads_conversion_label'] = trim((string) $this->settings['google_ads_conversion_label']);
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
                'settings.google_tag_id' => ['nullable', 'string', 'max:32', 'regex:/^(?:(?:AW|G|GT|GTM)-[A-Z0-9]+)?$/i'],
                'settings.google_ads_conversion_label' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]*$/'],
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
                'settings.google_tag_id' => 'Google Tag / Ads ID',
                'settings.google_ads_conversion_label' => 'Conversion-Label (Kauf)',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstKey = (string) collect($e->validator->errors()->keys())->first();
            $targetTab = match (true) {
                str_starts_with($firstKey, 'settings.company_'),
                str_starts_with($firstKey, 'settings.owner_') => 'owner',
                str_starts_with($firstKey, 'settings.shipping_'),
                str_starts_with($firstKey, 'settings.express_'),
                str_starts_with($firstKey, 'settings.packaging_') => 'shipping',
                $firstKey === 'settings.inventory_low_stock_threshold',
                str_starts_with($firstKey, 'settings.google_') => 'products',
                str_starts_with($firstKey, 'settings.emergency_') => 'emergency',
                default => 'general',
            };
            $this->dispatch('config-validation-failed', tab: $targetTab);
            throw $e;
        }

        $this->settings['shop_is_live'] = $this->shop_is_live;
        $this->settings['shop_live_unlocked'] = $this->shop_is_live;

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

        if (filter_var($this->settings['showroom_multiplayer_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            \App\Services\Gamification\LobbyInstanceService::forgetMultiplayerEnabledCache();
        } else {
            app(\App\Services\Gamification\LobbyInstanceService::class)->flushAllMultiplayerState();
        }

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

    public function publishAndroidApk()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $this->validate([
            'androidApkUpload' => 'required|file|max:97280',
            'androidVersionCode' => 'required|integer|min:1|max:100000000',
            'androidVersionName' => ['required', 'string', 'max:32', 'regex:/^[0-9]+(\.[0-9]+){1,3}(-[A-Za-z0-9._]+)?$/'],
        ], [
            'androidApkUpload.required' => 'Bitte eine signierte .apk-Datei auswählen.',
            'androidVersionName.regex' => 'Ungültiges Versionsformat (z. B. 1.4.0).',
        ]);

        try {
            $releaseService = app(\App\Services\System\AndroidReleaseService::class);
            $manifest = $releaseService->storeVerifiedRelease(
                sourceTempFile: $this->androidApkUpload->getRealPath(),
                versionCode: (int) $this->androidVersionCode,
                versionName: trim($this->androidVersionName),
                expectedSha256: null,
                releaseNotes: null,
                allowSameVersionRebuild: true
            );

            try {
                $this->androidApkUpload->delete();
            } catch (\Throwable $e) {
            }
            $this->androidApkUpload = null;
            $this->androidVersionCode = ((int) $manifest['version_code']) + 1;
            session()->flash('message', 'Funkenwerk Android v' . $manifest['version_name'] . ' (Build ' . $manifest['version_code'] . ') im Tresor bereitgestellt.');
        } catch (\Throwable $e) {
            $this->addError('androidApkUpload', $e->getMessage());
        }
    }

    public function publishWindowsSuite()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $this->validate([
            'windowsSuiteUpload' => 'required|file|max:524288',
            'windowsVersionCode' => 'required|integer|min:1|max:100000000',
            'windowsVersionName' => ['required', 'string', 'max:32', 'regex:/^[0-9]+(\.[0-9]+){1,3}(-[A-Za-z0-9._]+)?$/'],
        ], [
            'windowsSuiteUpload.required' => 'Bitte ein gültiges Funkenwerk-ZIP-Paket auswählen.',
            'windowsVersionName.regex'    => 'Ungültiges Versionsformat (z. B. 1.0.0).',
        ]);

        try {
            $releaseService = app(\App\Services\System\WindowsReleaseService::class);
            $manifest = $releaseService->storeVerifiedRelease(
                sourceTempFile: $this->windowsSuiteUpload->getRealPath(),
                versionCode: (int) $this->windowsVersionCode,
                versionName: trim($this->windowsVersionName),
                expectedSha256: null,
                releaseNotes: null,
                allowSameVersionRebuild: true
            );

            try {
                $this->windowsSuiteUpload->delete();
            } catch (\Throwable $e) {
            }
            $this->windowsSuiteUpload = null;
            $this->windowsVersionCode = ((int) $manifest['version_code']) + 1;
            session()->flash('message', 'Funkenwerk Windows Suite v' . $manifest['version_name'] . ' (Build ' . $manifest['version_code'] . ') im Tresor bereitgestellt.');
        } catch (\Throwable $e) {
            $this->addError('windowsSuiteUpload', $e->getMessage());
        }
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
        
        $androidRelease = app(\App\Services\System\AndroidReleaseService::class)->getLatestManifest();
        $androidDownloadUrl = $androidRelease
            ? \Illuminate\Support\Facades\URL::temporarySignedRoute('admin.system.funkenwerk-apk.download', now()->addMinutes(15))
            : null;

        $windowsRelease = app(\App\Services\System\WindowsReleaseService::class)->getLatestManifest();
        $windowsDownloadUrl = $windowsRelease
            ? \Illuminate\Support\Facades\URL::temporarySignedRoute('admin.system.funkenwerk-windows.download', now()->addMinutes(15))
            : null;

        return view('livewire.shop.system.system-shop-config', [
            'laravelVersion'         => $laravelVersion,
            'phpVersion'             => $phpVersion,
            'pythonVersion'          => $pythonVersion,
            'livewireVersion'        => $livewireVersion,
            'databaseDriverLabel'    => $databaseDriverLabel,
            'androidRelease'         => $androidRelease,
            'androidDownloadUrl'     => $androidDownloadUrl,
            'windowsRelease'         => $windowsRelease,
            'windowsDownloadUrl'     => $windowsDownloadUrl,
            'seasonsDefinition'      => $configService->getSeasonsDefinition(),
            'activeSeasonKey'        => $configService->getActiveSeasonKey(),
            'animationTimeline'      => $configService->getAnimationTimeline(),
            'currentActiveAnimation' => $configService->getCurrentActiveTheme(),
            'backgroundThemes'       => $configService->getBackgroundThemes(),
        ]);
    }
}

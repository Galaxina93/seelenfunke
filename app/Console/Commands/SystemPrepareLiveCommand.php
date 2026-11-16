<?php

namespace App\Console\Commands;

use App\Models\Accounting\AccountingGroup;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminProfile;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Logistics\LogisticsShippingZone;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailFolder;
use App\Models\Management\ManagementCalendarEvent;
use App\Models\Management\ManagementDayRoutine;
use App\Models\Management\ManagementPlaylist;
use App\Models\Management\ManagementTaskList;
use App\Models\Marketing\MarketingInfluencer;
use App\Models\Marketing\MarketingTrackingLink;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductTemplate;
use App\Models\System\SystemCronjob;
use App\Models\System\SystemSetting;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\AiAgentSeeder;
use Database\Seeders\AiCompanyStructureSeeder;
use Database\Seeders\AiHealthMedicationSeeder;
use Database\Seeders\AiKnowledgeBaseLaserSeeder;
use Database\Seeders\AiKnowledgeBaseSeeder;
use Database\Seeders\AiTarifeSeeder;
use Database\Seeders\BankTransactionTestSeeder;
use Database\Seeders\BlogCategorySeeder;
use Database\Seeders\FinancialDataSeeder;
use Database\Seeders\GamificationTestSeeder;
use Database\Seeders\GamificationUltimatePlayerSeeder;
use Database\Seeders\LogisticsShippingSeeder;
use Database\Seeders\MailAccountSeeder;
use Database\Seeders\ManagementCalenderSeeder;
use Database\Seeders\ManagementContactSeeder;
use Database\Seeders\ManagementDayRoutineSeeder;
use Database\Seeders\ManagementMusicSeeder;
use Database\Seeders\ManagementShoppingListSeeder;
use Database\Seeders\ManagementTaskSeeder;
use Database\Seeders\MarketingBlogSeeder;
use Database\Seeders\MarketingLinktreeSeeder;
use Database\Seeders\MonthlyVoucherSeeder;
use Database\Seeders\NewsletterKampagnenSeeder;
use Database\Seeders\OrderShoppingCartSeeder;
use Database\Seeders\OrdersTableSeeder;
use Database\Seeders\ProductAttributeSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\ProductIdeaSeeder;
use Database\Seeders\ProductLandingPageSeeder;
use Database\Seeders\ProductPackagingSeeder;
use Database\Seeders\ProductReviewSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\ProductSupplierSeeder;
use Database\Seeders\ProductTemplateSeeder;
use Database\Seeders\Spaceship3DGlbSeeder;
use Database\Seeders\SystemCronjobSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\TaxRateSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SystemPrepareLiveCommand extends Command
{
    public const ALINA_ADMIN_ID = '2122795e-da08-484f-9696-a58d11070163';
    public const ALINA_ADMIN_EMAIL = 'kontakt@mein-seelenfunke.de';

    /**
     * Bekannte Entwicklungs- und Test-Mitarbeiter aus UserSeeder, die auf Live strikt verboten sind.
     */
    public const FORBIDDEN_EMPLOYEE_EMAILS = [
        'mitarbeiter@mein-seelenfunke.de',
        'influencer@mein-seelenfunke.de',
        'steuerberater@mein-seelenfunke.de',
    ];

    public const FORBIDDEN_VOUCHER_CODES = [
        'LARA10',
    ];

    public const FORBIDDEN_INFLUENCER_SLUGS = [
        'lara-living',
    ];

    /**
     * Geordnete Liste aller verifizierten Produktions- und Stammdaten-Seeder.
     * Hinweis: ProductSeeder ruft am Ende intern automatisch ProductAssetSeeder auf.
     */
    public const PRODUCTION_SEEDERS = [
        TaxRateSeeder::class,
        LogisticsShippingSeeder::class,
        SystemSettingSeeder::class,
        SystemCronjobSeeder::class,
        AiTarifeSeeder::class,
        ProductSupplierSeeder::class,
        ProductCategorySeeder::class,
        ProductAttributeSeeder::class,
        ProductSeeder::class,
        ProductPackagingSeeder::class,
        ProductTemplateSeeder::class,
        ProductLandingPageSeeder::class,
        ProductIdeaSeeder::class,
        BlogCategorySeeder::class,
        MarketingBlogSeeder::class,
        NewsletterKampagnenSeeder::class,
        MonthlyVoucherSeeder::class,
        MarketingLinktreeSeeder::class,
        AccountingSeeder::class,
        MailAccountSeeder::class,
        ManagementDayRoutineSeeder::class,
        ManagementContactSeeder::class,
        ManagementCalenderSeeder::class,
        ManagementTaskSeeder::class,
        ManagementShoppingListSeeder::class,
        ManagementMusicSeeder::class,
        AiAgentSeeder::class,
        AiKnowledgeBaseSeeder::class,
        AiKnowledgeBaseLaserSeeder::class,
        AiCompanyStructureSeeder::class,
    ];

    /**
     * Explizit von Live ausgeschlossene Dummy-, Test- und reine Datei-Generator-Seeder.
     */
    public const EXCLUDED_SEEDERS = [
        UserSeeder::class,
        OrdersTableSeeder::class,
        OrderShoppingCartSeeder::class,
        ProductReviewSeeder::class,
        FinancialDataSeeder::class,
        BankTransactionTestSeeder::class,
        GamificationTestSeeder::class,
        GamificationUltimatePlayerSeeder::class,
        Spaceship3DGlbSeeder::class,
        AiHealthMedicationSeeder::class,
    ];

    protected $signature = 'system:prepare-live
                            {--verify-only : Prüft ausschließlich den Live-Zustand ohne Seeding oder Schreibzugriffe}
                            {--keep-maintenance-state : Behält die aktuellen shop_is_live- und maintenance_mode-Werte bei statt die Live-Sperre zu erzwingen}';

    protected $description = 'Bereitet die Live-Datenbank idempotent mit aktivem Wartungsmodus und deaktiviertem Live-gehen-Schalter (HTTP 503) vor.';

    public function handle(): int
    {
        $verifyOnly = (bool) $this->option('verify-only');
        $keepMaintenanceState = (bool) $this->option('keep-maintenance-state');

        $settingsTable = (new SystemSetting())->getTable();
        $adminsTable = (new Admin())->getTable();

        if (!Schema::hasTable($settingsTable) || !Schema::hasTable($adminsTable)) {
            $this->error("Erforderliche Systemtabellen ({$settingsTable} / {$adminsTable}) fehlen. Bitte zuerst php artisan migrate --force ausführen.");

            return self::FAILURE;
        }

        $currentShopIsLiveState = filter_var(
            SystemSetting::where('key', 'shop_is_live')->value('value') ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $currentMaintenanceState = filter_var(
            SystemSetting::where('key', 'maintenance_mode')->value('value') ?? true,
            FILTER_VALIDATE_BOOLEAN
        );
        $expectedShopIsLiveState = $keepMaintenanceState ? $currentShopIsLiveState : false;
        $expectedMaintenanceState = $keepMaintenanceState ? $currentMaintenanceState : true;

        if ($verifyOnly) {
            $this->info('Starte reine Live-Bereitschaftsprüfung (--verify-only)...');

            return $this->verifyLiveState($expectedShopIsLiveState, $expectedMaintenanceState);
        }

        $this->info('Starte idempotente Live-Initialisierung für mein-seelenfunke.de...');

        // a) Fail-Closed Step 1: Live gehen auf false und Wartungsmodus auf true scharf stellen (sofern nicht --keep-maintenance-state)
        if (!$keepMaintenanceState) {
            SystemSetting::updateOrCreate(
                ['key' => 'shop_is_live'],
                ['value' => 'false']
            );
            SystemSetting::updateOrCreate(
                ['key' => 'shop_live_unlocked'],
                ['value' => 'false']
            );
            SystemSetting::updateOrCreate(
                ['key' => 'maintenance_mode'],
                ['value' => 'true']
            );
        } else {
            SystemSetting::firstOrCreate(
                ['key' => 'shop_is_live'],
                ['value' => 'false']
            );
            SystemSetting::firstOrCreate(
                ['key' => 'shop_live_unlocked'],
                ['value' => 'false']
            );
            SystemSetting::firstOrCreate(
                ['key' => 'maintenance_mode'],
                ['value' => 'true']
            );
        }
        Cache::forget('global_shop_settings');

        // b) Reiner Admin-Account (Alina) + Bereinigung jeglicher Test-Accounts
        $admin = $this->ensureAlinaAdminAccount();
        $this->purgeForbiddenTestAccounts();

        // c) Geordnetes Produktions-Seeding (--force kompatibel in APP_ENV=production)
        $seedingFailed = $this->runProductionSeeders($admin);

        // d) Fail-Closed Step Final: Live gehen (false) & Wartungsmodus (true) erneut hart absichern & global_shop_settings-Cache erneuern
        if (!$keepMaintenanceState) {
            SystemSetting::updateOrCreate(
                ['key' => 'shop_is_live'],
                ['value' => 'false']
            );
            SystemSetting::updateOrCreate(
                ['key' => 'shop_live_unlocked'],
                ['value' => 'false']
            );
            SystemSetting::updateOrCreate(
                ['key' => 'maintenance_mode'],
                ['value' => 'true']
            );
        }
        $this->refreshGlobalShopSettingsCache();

        if ($seedingFailed) {
            $this->error('Mindestens ein Produktions-Seeder meldete einen Fehler. Live-Sperre und Wartungsmodus bleiben geschlossen.');

            return self::FAILURE;
        }

        return $this->verifyLiveState($expectedShopIsLiveState, $expectedMaintenanceState);
    }

    /**
     * Legt ausschließlich Alinas Admin-Konto + AdminProfile idempotent per firstOrCreate-Semantik an,
     * ohne jemals ein bestehendes Passwort oder 2FA-Geheimnis zu überschreiben.
     */
    private function ensureAlinaAdminAccount(): Admin
    {
        $admin = Admin::withTrashed()->where('email', self::ALINA_ADMIN_EMAIL)->first();

        if ($admin !== null) {
            if ($admin->trashed()) {
                $admin->restore();
            }
        } else {
            $targetId = Admin::withTrashed()->whereKey(self::ALINA_ADMIN_ID)->exists()
                ? (string) Str::uuid()
                : self::ALINA_ADMIN_ID;

            $initialPassword = (string) env('LIVE_ADMIN_INITIAL_PASSWORD', 'SeeleSeele1993+++');

            $admin = Admin::withoutEvents(function () use ($targetId, $initialPassword): Admin {
                $created = new Admin();
                $created->forceFill([
                    'id' => $targetId,
                    'first_name' => 'Alina',
                    'last_name' => 'Steinhauer',
                    'email' => self::ALINA_ADMIN_EMAIL,
                    'password' => Hash::make($initialPassword),
                ]);
                $created->save();

                return $created;
            });
        }

        $profileDefaults = [
            'street' => 'Carl-Goerdeler-Ring',
            'house_number' => '26',
            'postal' => '38518',
            'city' => 'Gifhorn',
            'country' => 'DE',
            'phone_number' => '+49 1590 1966864',
            'email_verified_at' => now(),
            'photo_path' => 'shop/projekt/about/gruender-profil.webp',
            'ai_data_consent' => true,
            'ai_data_consent_at' => now(),
        ];

        $existingProfile = AdminProfile::withTrashed()->where('admin_id', $admin->id)->first();
        if ($existingProfile !== null) {
            if ($existingProfile->trashed()) {
                $existingProfile->restore();
            }
            if (empty($existingProfile->street) && empty($existingProfile->city)) {
                $existingProfile->fill([
                    'street' => $profileDefaults['street'],
                    'house_number' => $profileDefaults['house_number'],
                    'postal' => $profileDefaults['postal'],
                    'city' => $profileDefaults['city'],
                    'country' => $profileDefaults['country'],
                    'phone_number' => $existingProfile->phone_number ?: $profileDefaults['phone_number'],
                    'email_verified_at' => $existingProfile->email_verified_at ?? $profileDefaults['email_verified_at'],
                    'photo_path' => $existingProfile->photo_path ?: $profileDefaults['photo_path'],
                    'ai_data_consent' => true,
                    'ai_data_consent_at' => $existingProfile->ai_data_consent_at ?? $profileDefaults['ai_data_consent_at'],
                ]);
                $existingProfile->save();
            }
        } else {
            AdminProfile::firstOrCreate(
                ['admin_id' => $admin->id],
                $profileDefaults
            );
        }

        return $admin;
    }

    /**
     * Entfernt sämtliche bekannten Test-Accounts, Test-Influencer und Test-Gutscheine (z. B. LARA10), falls vorhanden.
     */
    private function purgeForbiddenTestAccounts(): void
    {
        if (Schema::hasTable((new MarketingVoucher())->getTable())) {
            $this->purgeRecords(
                MarketingVoucher::class,
                fn ($q) => $q->whereIn('code', self::FORBIDDEN_VOUCHER_CODES)
            );
        }

        $forbiddenEmployeeIds = [];
        if (Schema::hasTable((new Employee())->getTable())) {
            $forbiddenEmployeeIds = Employee::withTrashed()
                ->whereIn('email', self::FORBIDDEN_EMPLOYEE_EMAILS)
                ->pluck('id')
                ->all();
        }

        if (Schema::hasTable((new MarketingInfluencer())->getTable())) {
            $this->purgeRecords(
                MarketingInfluencer::class,
                function ($q) use ($forbiddenEmployeeIds) {
                    $q->whereIn('slug', self::FORBIDDEN_INFLUENCER_SLUGS)
                        ->orWhere('handle', '@lara.living');
                    if (!empty($forbiddenEmployeeIds)) {
                        $q->orWhereIn('employee_id', $forbiddenEmployeeIds);
                    }
                }
            );
        }

        if (Schema::hasTable((new MarketingTrackingLink())->getTable())) {
            $this->purgeRecords(
                MarketingTrackingLink::class,
                fn ($q) => $q->whereIn('slug', self::FORBIDDEN_INFLUENCER_SLUGS)
            );
        }

        if (Schema::hasTable((new Employee())->getTable())) {
            $employees = Employee::withTrashed()
                ->whereIn('email', self::FORBIDDEN_EMPLOYEE_EMAILS)
                ->get();

            foreach ($employees as $employee) {
                $employee->revokeActiveSessionsAndTokens();
                $employee->forceDelete();
            }
        }

        if (Schema::hasTable((new Customer())->getTable())) {
            $this->purgeRecords(
                Customer::class,
                fn ($q) => $q->where(function ($sub) {
                    $sub->where('first_name', 'Sarah')->where('last_name', 'Sonnenschein');
                })
                    ->orWhereIn('email', ['demokunde@seelenfunke.local', 'imperator@mein-seelenfunke.de'])
                    ->orWhere('email', 'like', '%@test-gamer.de')
            );
        }
    }

    /**
     * Führt alle Produktions-Seeder in definierter Abhängigkeits-Reihenfolge aus
     * und schützt bereits vorhandene operative Live-Einträge vor destruktivem Neuaufbau.
     */
    private function runProductionSeeders(Admin $admin): bool
    {
        $failed = false;

        foreach (self::PRODUCTION_SEEDERS as $seederClass) {
            $shortName = class_basename($seederClass);

            // 1. SystemCronjobSeeder: Bestehende Admin-Cronjob-Anpassungen auf Live bewahren
            if ($seederClass === SystemCronjobSeeder::class) {
                if (Schema::hasTable((new SystemCronjob())->getTable()) && SystemCronjob::count() > 0) {
                    SystemCronjobSeeder::ensureDefaultCronjobsExist();
                    $this->line("  [OK] {$shortName} (bestehende Cronjob-Konfiguration beibehalten)");
                    continue;
                }
            }

            // 2. AccountingSeeder: Nur bei leerer Buchhaltungsstruktur initialisieren, um Live-Belege zu schützen
            if ($seederClass === AccountingSeeder::class) {
                if (Schema::hasTable((new AccountingGroup())->getTable()) && AccountingGroup::where('admin_id', $admin->id)->exists()) {
                    $this->line("  [OK] {$shortName} (bestehende Buchhaltungsdaten beibehalten)");
                    continue;
                }
            }

            // 3. MailAccountSeeder: Im Testbetrieb nur das saubere Hauptpostfach ohne lokales Backup-ZIP anlegen
            if ($seederClass === MailAccountSeeder::class) {
                if (app()->runningUnitTests()) {
                    $this->ensureCleanCommercialMailAccount();
                    $this->line("  [OK] {$shortName} (Hauptpostfach ohne lokales Backup-ZIP initialisiert)");
                    continue;
                }
                if (Schema::hasTable((new MailAccount())->getTable()) && MailAccount::count() > 0) {
                    $this->line("  [OK] {$shortName} (bestehende Postfächer beibehalten)");
                    continue;
                }
            }

            // 4. Destruktive Management-Seeder nur bei Erstinitialisierung ausführen
            if ($seederClass === ManagementDayRoutineSeeder::class && Schema::hasTable((new ManagementDayRoutine())->getTable()) && ManagementDayRoutine::count() > 0) {
                $this->line("  [OK] {$shortName} (bestehende Tagesroutine beibehalten)");
                continue;
            }
            if ($seederClass === ManagementCalenderSeeder::class && Schema::hasTable((new ManagementCalendarEvent())->getTable()) && ManagementCalendarEvent::count() > 0) {
                $this->line("  [OK] {$shortName} (bestehende Kalendertermine beibehalten)");
                continue;
            }
            if ($seederClass === ManagementTaskSeeder::class && Schema::hasTable((new ManagementTaskList())->getTable()) && ManagementTaskList::count() > 0) {
                $this->line("  [OK] {$shortName} (bestehende Aufgabenlisten beibehalten)");
                continue;
            }
            if ($seederClass === ManagementMusicSeeder::class) {
                if (app()->runningUnitTests() || (Schema::hasTable((new ManagementPlaylist())->getTable()) && ManagementPlaylist::count() > 0)) {
                    $this->line("  [OK] {$shortName} (Lokal-Musik auf Live/Test übersprungen)");
                    continue;
                }
            }

            $exitCode = $this->callSilent('db:seed', [
                '--class' => $seederClass,
                '--force' => true,
                '--no-interaction' => true,
            ]);

            if ($exitCode !== 0) {
                $this->error("  [FEHLER] {$shortName} fehlgeschlagen (Exit-Code {$exitCode}).");
                $failed = true;
            } else {
                $this->line("  [OK] {$shortName}");
            }
        }

        return $failed;
    }

    private function ensureCleanCommercialMailAccount(): void
    {
        if (!Schema::hasTable((new MailAccount())->getTable())) {
            return;
        }

        $account = MailAccount::firstOrCreate(
            ['email' => self::ALINA_ADMIN_EMAIL],
            [
                'name' => self::ALINA_ADMIN_EMAIL,
                'imap_host' => 'mail.agenturserver.de',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'imap_username' => self::ALINA_ADMIN_EMAIL,
                'smtp_host' => 'mail.agenturserver.de',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
                'smtp_username' => self::ALINA_ADMIN_EMAIL,
                'password' => '',
                'is_default' => true,
                'is_commercial' => true,
                'status' => 'connected',
                'last_sync_at' => now(),
            ]
        );

        if (Schema::hasTable((new MailFolder())->getTable())) {
            foreach (['Bankkonto', 'Glücksfieber', 'Hosting', 'KartonEu', 'Rechnungen', 'Rechtliches', 'Server-Backups', 'Westpack', 'XTool'] as $folderName) {
                MailFolder::firstOrCreate([
                    'mail_account_id' => $account->id,
                    'name' => $folderName,
                ]);
            }
        }
    }

    private function refreshGlobalShopSettingsCache(): void
    {
        Cache::forget('global_shop_settings');
        Cache::forget('shop_setting_inventory_threshold');

        try {
            $freshSettings = SystemSetting::pluck('value', 'key');
            Cache::put('global_shop_settings', $freshSettings, 60);
        } catch (\Throwable) {
            // Ignorieren falls Cache-Treiber temporär nicht beschreibbar
        }
    }

    /**
     * Führt die harte Abschluss-Selbstprüfung aller Live-Sicherheits- und Stammdaten-Kriterien durch.
     */
    private function verifyLiveState(bool $expectedShopIsLiveState, bool $expectedMaintenanceState): int
    {
        Cache::forget('global_shop_settings');
        $actualShopIsLiveState = filter_var(shop_setting('shop_is_live', false), FILTER_VALIDATE_BOOLEAN);
        $actualMaintenanceState = filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN);
        $this->refreshGlobalShopSettingsCache();

        $alinaExists = Admin::where('email', self::ALINA_ADMIN_EMAIL)->exists();
        $adminCount = Admin::count();
        $productCount = Schema::hasTable((new Product())->getTable()) ? Product::count() : 0;
        $categoryCount = Schema::hasTable((new ProductCategory())->getTable()) ? ProductCategory::count() : 0;
        $templateCount = Schema::hasTable((new ProductTemplate())->getTable()) ? ProductTemplate::count() : 0;
        $activeZoneCount = Schema::hasTable((new LogisticsShippingZone())->getTable())
            ? LogisticsShippingZone::where('is_active', true)->count()
            : 0;
        $taxRateCount = Schema::hasTable('tax_rates') ? DB::table('tax_rates')->count() : 0;

        $forbiddenEmployeeCount = Schema::hasTable((new Employee())->getTable())
            ? Employee::withTrashed()->whereIn('email', self::FORBIDDEN_EMPLOYEE_EMAILS)->count()
            : 0;

        $forbiddenInfluencerCount = Schema::hasTable((new MarketingInfluencer())->getTable())
            ? $this->countWithOptionalTrashed(
                MarketingInfluencer::class,
                fn ($q) => $q->whereIn('slug', self::FORBIDDEN_INFLUENCER_SLUGS)->orWhere('handle', '@lara.living')
            )
            : 0;

        $forbiddenVoucherCount = Schema::hasTable((new MarketingVoucher())->getTable())
            ? $this->countWithOptionalTrashed(
                MarketingVoucher::class,
                fn ($q) => $q->whereIn('code', self::FORBIDDEN_VOUCHER_CODES)
            )
            : 0;

        $forbiddenCustomerCount = Schema::hasTable((new Customer())->getTable())
            ? $this->countWithOptionalTrashed(
                Customer::class,
                fn ($q) => $q->where(function ($sub) {
                    $sub->where('first_name', 'Sarah')->where('last_name', 'Sonnenschein');
                })
                    ->orWhereIn('email', ['demokunde@seelenfunke.local', 'imperator@mein-seelenfunke.de'])
                    ->orWhere('email', 'like', '%@test-gamer.de')
            )
            : 0;

        $checks = [
            [
                'Prüfpunkt' => 'Live gehen (shop_is_live)',
                'Ist-Wert' => $actualShopIsLiveState ? 'true (Live)' : 'false (Gesperrt / 503)',
                'Status' => $actualShopIsLiveState === $expectedShopIsLiveState ? 'OK' : 'FEHLER',
                'passed' => $actualShopIsLiveState === $expectedShopIsLiveState,
            ],
            [
                'Prüfpunkt' => 'Wartungsmodus (maintenance_mode)',
                'Ist-Wert' => $actualMaintenanceState ? 'true (aktiv)' : 'false (inaktiv)',
                'Status' => $actualMaintenanceState === $expectedMaintenanceState ? 'OK' : 'FEHLER',
                'passed' => $actualMaintenanceState === $expectedMaintenanceState,
            ],
            [
                'Prüfpunkt' => 'Admin Alina (' . self::ALINA_ADMIN_EMAIL . ')',
                'Ist-Wert' => $alinaExists ? "Vorhanden (Admins gesamt: {$adminCount})" : 'Fehlt',
                'Status' => ($alinaExists && $adminCount >= 1) ? 'OK' : 'FEHLER',
                'passed' => $alinaExists && $adminCount >= 1,
            ],
            [
                'Prüfpunkt' => 'Produkte / Kategorien / Vorlagen',
                'Ist-Wert' => "{$productCount} Produkte / {$categoryCount} Kategorien / {$templateCount} Vorlagen",
                'Status' => ($productCount > 0 && $categoryCount > 0 && $templateCount > 0) ? 'OK' : 'FEHLER',
                'passed' => $productCount > 0 && $categoryCount > 0 && $templateCount > 0,
            ],
            [
                'Prüfpunkt' => 'Aktive Lieferzonen & Steuersätze',
                'Ist-Wert' => "{$activeZoneCount} aktive Zone(n) / {$taxRateCount} Steuersätze",
                'Status' => ($activeZoneCount > 0 && $taxRateCount > 0) ? 'OK' : 'FEHLER',
                'passed' => $activeZoneCount > 0 && $taxRateCount > 0,
            ],
            [
                'Prüfpunkt' => '0 Test-Mitarbeiter / Test-Influencer',
                'Ist-Wert' => "{$forbiddenEmployeeCount} Test-Employees / {$forbiddenInfluencerCount} Test-Influencer",
                'Status' => ($forbiddenEmployeeCount === 0 && $forbiddenInfluencerCount === 0) ? 'OK' : 'FEHLER',
                'passed' => $forbiddenEmployeeCount === 0 && $forbiddenInfluencerCount === 0,
            ],
            [
                'Prüfpunkt' => '0 Test-Gutschein LARA10 & 0 Test-Kunden',
                'Ist-Wert' => "{$forbiddenVoucherCount} LARA10 / {$forbiddenCustomerCount} Test-Kunden",
                'Status' => ($forbiddenVoucherCount === 0 && $forbiddenCustomerCount === 0) ? 'OK' : 'FEHLER',
                'passed' => $forbiddenVoucherCount === 0 && $forbiddenCustomerCount === 0,
            ],
        ];

        $this->table(
            ['Prüfpunkt', 'Ist-Wert', 'Status'],
            array_map(fn (array $row) => [
                $row['Prüfpunkt'],
                $row['Ist-Wert'],
                $row['Status'],
            ], $checks)
        );

        $allPassed = collect($checks)->every(fn (array $row) => $row['passed'] === true);

        if (!$allPassed) {
            $this->error('Live-Bereitschaftsprüfung NICHT bestanden. Bitte fehlerhafte Prüfpunkte kontrollieren.');

            return self::FAILURE;
        }

        $this->info('Live-Bereitschaftsprüfung erfolgreich bestanden (Exit-Code 0).');

        return self::SUCCESS;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function purgeRecords(string $modelClass, \Closure $scope): void
    {
        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
        $query = $usesSoftDeletes ? $modelClass::withTrashed() : $modelClass::query();
        $scope($query);

        foreach ($query->get() as $record) {
            if ($usesSoftDeletes && method_exists($record, 'forceDelete')) {
                $record->forceDelete();
            } else {
                $record->delete();
            }
        }
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function countWithOptionalTrashed(string $modelClass, \Closure $scope): int
    {
        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
        $query = $usesSoftDeletes ? $modelClass::withTrashed() : $modelClass::query();
        $scope($query);

        return $query->count();
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateAiMap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:generate-map';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates an ai_map.md file containing an overview of Models, Controllers, Livewire Components, and Routes for the AI.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating AI Map...');

        $markdown = "# Seelenfunke System-Architektur & AI Map (`ai_map.md`)\n";
        $markdown .= "> Generiert via `php artisan ai:generate-map` (" . now()->format('Y-m-d H:i') . ")\n\n";

        // 1. Ökosystem & Pfade
        $markdown .= "## 1. Ökosystem-Pfade & Umgebungen\n";
        $markdown .= "- **Hauptportal (`Mein-Seelenfunke`)**: Win-UNC `\\\\wsl.localhost\\Ubuntu\\home\\ubuntuxina\\meine-projekte\\seelenfunke` | WSL `/home/ubuntuxina/meine-projekte/seelenfunke` (Docker PHP: `mein_php_server`) | Lokal `http://127.0.0.1` (`http://seelenfunke.test`) | Stage `https://stage.mein-seelenfunke.de` | Live `https://mein-seelenfunke.de`\n";
        $markdown .= "- **Funkenwerk Windows Desktop Suite (C#/WPF/.NET 8)**: `\\\\wsl.localhost\\Ubuntu\\home\\ubuntuxina\\meine-projekte\\seelenfunke\\funkenwerk` (`MainWindow.xaml.cs`, `CameraStudioEngine.cs`, `SmartHomeEngine.cs`, `MarketingEngine.cs`, `funkenwerk_dlc_bridge.py`) + KI-Face-Swap `C:\\Users\\konta\\Documents\\Deep-Live-Cam`\n";
        $markdown .= "- **Funkenwerk Android App & Widgets (Kotlin/Compose)**: `\\\\wsl.localhost\\Ubuntu\\home\\ubuntuxina\\meine-projekte\\seelenfunke\\android-app` (Mirror: `C:\\Users\\konta\\AndroidStudioProjects\\seelenfunke-android`)\n";
        $markdown .= "- **Dokumentation & Belege**: `Abschlussberichte/` (`README.md`, `<Abteilung>/`, `Workflows/`, `Konzepte/`) | Belege: `storage/app/buchhaltung/receipts/` & `contracts/`\n\n";

        // 2. Wichtige Routen & Begriffs-Mapping
        $markdown .= "## 2. Zentrales Routen- & Begriffs-Mapping (`routes/partials/*.php`)\n";
        $markdown .= "- **Kommandozentrale / Operativer Status / To-Do Liste**: `/admin/dashboard` -> `Shop\\Master\\MasterAnalytics` & `Shop\\Management\\ManagementTask`\n";
        $markdown .= "- **Buchhaltung (`Shop\\Accounting\\*`, Seeder: `AccountingSeeder.php`)**:\n";
        $markdown .= "  - Sonderausgaben / Einkäufe: `/admin/financial-variable-costs` -> `AccountingVariableCosts` (`AccountingSpecialIssue`)\n";
        $markdown .= "  - Fixkosten / Verträge / Versicherungen: `/admin/financial-fix-costs` -> `AccountingFixCosts` (`AccountingCostItem`, `AccountingGroup`)\n";
        $markdown .= "  - Rechnungen / Gutschriften / Bank / Steuer-Export / Analyse: `/admin/financial-invoices` (`AccountingInvoice`, `AccountingCredit`), `/admin/financial-banks` (`AccountingBank`), `/admin/financial-tax-exports` (`AccountingTax`, `TaxAdvisorDashboard`), `/admin/financial-analytics` (`AccountingAnalytics`, `AccountingLiquidity`)\n";
        $markdown .= "- **Produkte, Konfigurator, Verpackung & Lieferanten (`Shop\\Product\\*`, Seeder: `Product*Seeder.php`)**:\n";
        $markdown .= "  - Produkte / Varianten / Kalkulator: `/admin/products` -> `ProductCreate`, `ProductShow`, `ProductVariants`, `ProductCalculator`\n";
        $markdown .= "  - 2D/3D-Konfigurator: `Shop\\Product\\ProductConfigurator\\ProductConfigurator` (`resources/views/livewire/shop/product/product-configurator/`)\n";
        $markdown .= "  - Verpackungs-Kalkulator: `/admin/packaging` (`ProductPackagingConfigurator`) | Lieferanten: `/admin/suppliers` (`ProductSuppliers`)\n";
        $markdown .= "  - Crawler / Vorlagen / Kategorien / Attribute / Bewertungen / Bruch: `/admin/product-*` -> `ProductCrawler`, `ProductTemplates`, `ProductCategories`, `ProductAttributes`, `ProductReviews`, `ProductFracture`\n";
        $markdown .= "- **Bestellungen & Logistik (`Shop\\Order\\*`, `Shop\\Cart\\*`, `Shop\\System\\*`)**:\n";
        $markdown .= "  - Bestellungen & Laser-Produktion: `/admin/orders` (`OrderOverview`) | Checkout & Warenkörbe: `OrderCheckout`, `/admin/carts` (`OrderShoppingCarts`)\n";
        $markdown .= "  - Angebote / Widerrufe / Versand (DHL): `/admin/quote-requests` (`OrderQuoteRequests`), `/admin/revocations` (`OrderRevocations`), `/admin/shipping` (`SystemShipping`), `/admin/delivery-display` (`SystemDeliveryDisplay`, `SystemDeliveryTimes`)\n";
        $markdown .= "- **Leitung / Management (`Shop\\Management\\*`)**:\n";
        $markdown .= "  - Mail (`/admin/mail` -> `ManagementEMails`), Kalender (`/admin/calendar` -> `ManagementCalender`), Kontakte (`/admin/contacts` -> `ManagementContacts`), Tagesroutine (`/admin/routine` -> `ManagementRoutine`), Einkaufsliste (`ManagementShoppingList`), Aufgaben (`ManagementTask`), Mitarbeiter (`ManagementEmployees`), Musik (`ManagementMusic`)\n";
        $markdown .= "- **Marketing & Influencer Portal (`Shop\\Marketing\\*`)**:\n";
        $markdown .= "  - Influencer & Partner-Portal: `/admin/marketing-influencers` -> `MarketingInfluencer` & `Employee\\Influencer\\InfluencerDashboard` (Rolle `influencer` isoliert)\n";
        $markdown .= "  - Gutscheine (`MarketingVoucher`), Blog (`MarketingBlog`), Newsletter (`MarketingNewsletter`), Landingpages (`MarketingLandingPages`), Herzensprojekte (`MarketingHerzensprojekte`), Linktree (`/links` -> `MarketingLinktree`)\n";
        $markdown .= "- **KI-Agenten, Support & System (`Shop\\Ai\\*`, `Shop\\Support\\*`, `Shop\\System\\*`)**:\n";
        $markdown .= "  - Organigramm (`/admin/organigramm` -> `AiCompanyStructure`), Workspace (`/admin/ai-workspace` -> `AiWorkspace`), Agenten (`AiAgentEditor`), Rollen (`AiRoleManager`), Wissen (`AiKnowledgeBase`)\n";
        $markdown .= "  - Support: Tickets (`SupportTicket`), Chats (`SupportChats`), Telefonie (`SupportTelephony`) | System: `/admin/settings` (`SystemShopConfig`), `/admin/users` (`SystemUserManagement`), `SystemLogs`, `SystemBackups`\n\n";

        // 3. Seeders
        $markdown .= "## 3. Datenbank-Seeder (`database/seeders/*.php`)\n";
        $markdown .= $this->scanDirectoryGrouped(database_path('seeders'), 'Database\\Seeders');

        // 4. Models
        $markdown .= "## 4. Datenbank-Modelle (`app/Models/<Domain>/*.php`)\n";
        $markdown .= $this->scanDirectoryGrouped(app_path('Models'), 'App\\Models');

        // 5. Livewire Components
        $markdown .= "## 5. Livewire-Komponenten (`app/Livewire/<Domain>/*.php`)\n";
        $markdown .= $this->scanDirectoryGrouped(app_path('Livewire'), 'App\\Livewire');

        // 6. Controllers
        $markdown .= "## 6. HTTP-Controller (`app/Http/Controllers/*.php`)\n";
        $markdown .= $this->scanDirectoryGrouped(app_path('Http/Controllers'), 'App\\Http\\Controllers');

        // 7. Services
        $markdown .= "## 7. Services & Geschäftslogik (`app/Services/<Domain>/*.php`)\n";
        $markdown .= $this->scanDirectoryGrouped(app_path('Services'), 'App\\Services');

        // Save ai_map.md
        $outputPath = base_path('ai_map.md');
        File::put($outputPath, $markdown);
        $this->info("AI Map successfully written to: {$outputPath} (" . strlen($markdown) . " bytes)");

        // Sync embedded AI_MAP block in project GEMINI.md and global Windows GEMINI.md if present
        $this->syncEmbeddedAiMap(base_path('GEMINI.md'), $markdown);
        $globalGeminiPath = '/mnt/c/Users/konta/.gemini/config/GEMINI.md';
        if (File::exists($globalGeminiPath)) {
            $this->syncEmbeddedAiMap($globalGeminiPath, $markdown);
        }
    }

    /**
     * Scans a directory and returns a compact domain-grouped markdown list of classes.
     */
    protected function scanDirectoryGrouped($path, $namespacePrefix)
    {
        if (!File::exists($path)) {
            return "- *Verzeichnis existiert nicht*\n\n";
        }

        $files = File::allFiles($path);
        $groups = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            $dir = dirname($relativePath);
            $groupKey = $dir === '.' ? 'Root' : str_replace('/', '\\', $dir);
            $baseClass = basename($relativePath, '.php');

            $groups[$groupKey][] = $baseClass;
        }

        ksort($groups);
        $output = "";

        foreach ($groups as $group => $classes) {
            sort($classes);
            $classList = implode(', ', $classes);
            $output .= "- **{$group}**: `{$classList}`\n";
        }

        return rtrim($output) . "\n\n";
    }

    /**
     * Replaces content between <!-- AI_MAP_START --> and <!-- AI_MAP_END --> in target file.
     */
    protected function syncEmbeddedAiMap(string $targetPath, string $aiMapMarkdown): void
    {
        if (!File::exists($targetPath)) {
            return;
        }

        $content = File::get($targetPath);
        $startMarker = '<!-- AI_MAP_START -->';
        $endMarker = '<!-- AI_MAP_END -->';

        if (str_contains($content, $startMarker) && str_contains($content, $endMarker)) {
            $pattern = '/' . preg_quote($startMarker, '/') . '.*?' . preg_quote($endMarker, '/') . '/s';
            $replacement = $startMarker . "\n" . trim($aiMapMarkdown) . "\n" . $endMarker;
            $updated = preg_replace($pattern, $replacement, $content, 1);
            if ($updated !== null) {
                File::put($targetPath, $updated);
                $this->info("Synced AI Map into: {$targetPath} (" . strlen($updated) . " bytes)");
            }
        }
    }
}

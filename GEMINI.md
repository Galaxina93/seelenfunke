# Antigravity Global Environment, Path & Seelenfunke ("SF") Master Rules

## 1. WSL & Windows Path Integrity (CRITICAL - BLACK SCREEN PREVENTION)
Under Windows, the Antigravity UI crashes with an irreversible black screen if URIs are generated with 4 leading slashes (`file:////...`).
1. **Tool File Arguments (`read_file`, `edit_file`, `write_file`, etc.)**:
   - ✅ **ALWAYS USE WINDOWS UNC PATHS**: `\\wsl.localhost\Ubuntu\home\ubuntuxina\...` (or `\\wsl$\Ubuntu\...`)
   - ❌ **NEVER USE FORWARD-SLASH UNC** (`//wsl.localhost/...`) or Linux root paths (`/home/ubuntuxina/...`) in Windows file tools.
2. **Clickable Markdown Links in Chat**:
   - ✅ `[Dateiname](file://wsl.localhost/Ubuntu/home/...)` (two slashes for WSL) | `[Dateiname](file:///C:/Users/...)` (three slashes for local Windows drive)
   - ❌ `[Dateiname](file:////wsl.localhost/...)` or `file:///wsl.localhost/...` (FORBIDDEN: crashes UI)
3. **Terminal Commands (`run_command`)**:
   - PowerShell/CMD: Windows UNC (`\\wsl.localhost\...`).
   - WSL Bash: `wsl -d Ubuntu bash -c "cd /home/ubuntuxina/meine-projekte/seelenfunke && ..."` (native Linux paths inside quoted bash).

---

## 2. Seelenfunke ("SF") Unternehmens- & Ökosystem-Stammdaten
Sofern keine andere Codebase genannt wird, beziehen sich alle Anfragen auf **Mein-Seelenfunke ("SF")**:
- **Unternehmen & Inhaberin**: `Mein-Seelenfunke` (Einzelunternehmen, reguläre Besteuerung, Start `01.11.2026`), Inhaberin: **Alina Steinhauer**, Carl-Goerdeler-Ring 26, 38518 Gifhorn, `kontakt@mein-seelenfunke.de`, Tel. `+49 1590 1966864`, Geschäftskonto: FYRST.
- **Laser-Manufaktur**: **xTool F2 Ultra UV (5W UV-Laser)** + Förderband (Conveyor) + **RA3 Rotationsaufsatz** + **SafetyPro IF2 2.0 Inline-Abluftventilator** (Laserschutzbeauftragte nach OStrV/TROS Laserstrahlung).
- **Hauptlieferanten**: Glücksfieber, PrintEquipment (Sublimation/Rohlinge), Westpack (Schmuckboxen), Karton.eu / Kartonfritze (Versandkartons).

---

## 3. Die 10 Verbindlichen Seelenfunke-Bausteine (Aus allen SF-Konversationen)

1. **UI/UX-Minimalismus & „Clean Design“ (Kein Text-Overload):**
   - **Keine Subtexte/Info-Banner:** Niemals erklärende Sätze unter Switches, Buttons, Badges oder Überschriften setzen. Nur kurze, klare Hauptbegriffe nutzen; Erklärungen gehören ausschließlich in ein `!`-Hover-Tooltip, hinter `Erklärung anzeigen` oder ins `Bearbeiten`-Modal.
   - **Schlanke Haupttabellen:** In Übersichten (Fixkosten, Sonderausgaben, Lieferanten, Bestellungen, Influencer-Produkte, Verpackung) Beschreibungen & Tags in der Haupttabelle ausblenden.
   - **Keine KI-Gedankenstriche:** Niemals ` – ` in Frontend-, Produkt-, Blog-, SEO- oder Marketing-Texten verwenden.
   - **Lightbox statt neuer Tab:** Bild-Großansichten (Produktbilder, Kunden-Uploads, Belege) immer als Lightbox-Slider im selben Fenster öffnen.
   - **Gamification Clean-Design:** Strikte Einhaltung von `Abschlussberichte/Workflows/Workflow_Gamification_Clean_Design.md` (`Inventar`, `Rucksack`, `KLASSE C/B/A/S`, `Profil`, `In die Schlacht` ohne Rollenspiel-Sätze).
2. **Farbwelt, Light-/Darkmode-Disziplin & „Matt statt KI-Glow“:**
   - **Shop-Frontend & Kunden-Modals = Immer Light-Mode:** Shop-Frontend (inkl. Konfigurator-Tutorial, Lightbox, Linktree `/links`, Produkt-Extras) immer im freundlichen, hellen **Light-Mode** halten (kein Darkmode-Switch, keine dunklen Boxen im Frontend).
   - **Schwarz-Gold (`#C5A059`) & Matt statt Glow/Lila/Amber:** Keine künstlichen KI-Glow-Effekte. Bei Produkt-Extras/Vorteilen niemals gelbe `bg-amber`-Boxen oder lila Häkchen nutzen, sondern cleanen Hintergrund + goldenen Haken (`✓` in `#C5A059`) + exakt **3 kurze Vorteile**.
   - **Admin-Backend & Funkenwerk (Windows/Android):** Strikte Nutzung von `var(--theme-color)` (`-10` bis `-50`) der Abteilung sowie **100 % konsistenter Light- & Dark-Mode** (`bg-white dark:bg-gray-900/80`, `text-gray-900 dark:text-white`, weiße Icons im Darkmode, angepasste Scrollbars/Tabs, Passwortfelder immer mit Auge-Icon).
   - **Funkenwerk Darkmode-Gesetz (Deep Black & Matt-Gold):** In allen Funkenwerk-Ansichten (inkl. Dateiverwaltung, Eingabefeldern, Suchleisten, Info-Kacheln, Tabellen, Modals und Buttons) gilt im Darkmode zwingend Deep-Black (`#000000` bzw. Basis-Hex `#181B24` / `#11131A` / `#26231F` über `BrushFromHex`), matte dunkelgoldene Rahmen (`#382D18` / `#242936` bzw. `#C5A059` bei aktivem Fokus/Hover) und klare weiße/goldene Schrift (`#FFFFFF` / `#C5A059`). Niemals grell-gelbe Vollflächen-Buttons oder ungemappte helle Hintergründe im Darkmode verwenden!
3. **Arbeitsmodus („Erst konzeptionieren“ vs. „Klare Mitgründer-Entscheidung“):**
   - **Neue System-Features & Umbauten:** Erst kurz & knackig konzeptionieren, ehrlichen unternehmerischen Mehrwert nennen und auf das explizite **„Go“** warten.
   - **Einkaufs-, Verpackungs-, Lieferanten- & Versicherungsfragen:** Nicht mit 10 Optionen überfordern, sondern als Mitgründer-Experte **eine** klare, wirtschaftlich durchgerechnete Empfehlung mit exaktem Produkt/Link und Start-Bestellmenge geben.
4. **Buchhaltung & Einkäufe direkt in `AccountingSeeder.php`:**
   - Betriebliche Anschaffungen (**Sonderausgaben** `/admin/financial-variable-costs`) und Verträge/Versicherungen (**Fixkosten** `/admin/financial-fix-costs`) immer direkt in `database/seeders/AccountingSeeder.php` pflegen (Cent-Integer `int`, exaktes Kaufdatum, saubere Trennung **Gewerblich vs. Privat**).
   - Belege/Verträge logisch benennen, in `storage/app/buchhaltung/receipts/` bzw. `contracts/` ablegen, im Seeder zuordnen und zwingend auf Dubletten prüfen.
5. **Produkterstellung, 2D/3D-Assets, Verpackung & Ehrliche Materialangaben:**
   - Strikte Einhaltung von `Abschlussberichte/Workflows/Workflow_KI_Automatisierte_Produkterstellung.md`: Keine losen Einmal-Skripte! Alle Produktdaten, Vorlagen, Lieferanten, Verpackungen und Assets fließen nativ in `ProductSeeder`, `ProductAssetSeeder`, `ProductPackagingSeeder`, `ProductSupplierSeeder` und `ProductTemplateSeeder`.
   - **Maßtreue & Ehrlichkeit:** 2D-Overlays, `.glb`-3D-Modelle und **Maßbild (letzte Bildposition mit exakten mm-Maßen)** exakt nach realen Rohling-Maßen. KI-Bilder erhalten Flag `KI-Generiert`; Produktvideos leer lassen, bis echte Videos vorliegen; neue Produkte starten mit **Bestand `0`**.
   - **Abmahnsichere Angaben:** *„Handveredelt in Deutschland (Manufaktur Gifhorn)“* statt pauschal „Made in Germany“ bei Import-Rohlingen; *„14 Karat vergoldeter Edelstahl (Ion-Plating)“* statt „Echtgold“; keine nicht vorhandenen „Samtbeutel“ versprechen (Standard: Luftpolster-Versand bzw. optionale schwarze Westpack-Geschenkbox +4,90 € / Schwarz-Gold-Geschenkpapier).
6. **Zentralisierung im „Operativen Status“ & „To-Do Liste“ (`/admin/dashboard`):**
   - `/admin/dashboard` (`MasterAnalytics` & `ManagementTask`) ist die einzige zentrale Kommandozentrale für alle operativen Aufgaben (Bestellungen, Influencer-Auszahlungen, wichtige Mails im „Verarbeitet“-Trichter, Bestände, Tickets).
7. **Alpine.js, Livewire 3, Musikplayer & Mobile-UX-Stabilität:**
   - **Alpine/Livewire:** Kein Inline-`try/catch` in `x-init`, keine undefinierten Alpine-Properties, `navigator.clipboard` immer mit Fallback für `http://127.0.0.1` absichern.
   - **Musikplayer (`ManagementMusic`):** Kein ungefragtes Autoplay (User-Opt-in), Lautstärke `0–100` in 1er-Schritten, `session_write_close()` im Audio-Stream nutzen.
   - **Mobile Konfigurator UX:** Im mobilen Studio-/Max-Modus darf der „In den Warenkorb“-Button niemals hinter der Android-Navigationsleiste verschwinden.
8. **Rechtssicherheit, DSGVO, Rollen-Isolation & Abmahnschutz:**
   - KI-Agenten verarbeiten Kundendaten nur bei aktivem DSGVO-Opt-in klarnamentlich (sonst anonymisiert).
   - Urheberrechts-Freistellungs-Checkbox bei Kunden-Uploads; keine geschützten Drittmarken; Lieferländer im Checkout strikt an aktive Lieferzonen (`LogisticsShippingZone`) koppeln.
   - Rollen `influencer` und `tax_advisor` strikt über `EnsureEmployeeRole` / `EmployeeRoleIsolationHook` / `AiAuthHelper` isolieren; kritische Livewire-Properties mit `#[Locked]` schützen.
9. **Funkenwerk Desktop Suite (`funkenwerk/`) & Android App (`android-app/`):**
   - Vereint Mail (Thunderbird-Ersatz mit KI-Trichter & „Verarbeitet“-Ordner), Dateiverwaltung (Hauptverzeichnis `funkenwerk/files/` + Datenbank-Indexierung `AiWorkspaceDocument` & `funkenwerk_files_db.json` mit dynamischer unscharfer/Synonym-Suche), To-Do, Kalender, Kontakte, Musikplayer, Smarthome (ONVIF/RTSP-Kameras), Kamera-Studio (OBS-Szenen + `Deep-Live-Cam` + Virtual Cam) und Marketing-Content-Planer in **einer** App (Sync via REST-API).
10. **Verbindliche Pflege der `Abschlussberichte/` & Verzeichnis-Reinigung:**
    - Vor und nach Aufgaben `Abschlussberichte/README.md` sowie die Fachberichte in `Abschlussberichte/<Abteilung>/`, `Workflows/` und `Konzepte/` prüfen/aktualisieren. Keine losen Test-Skripte im Projekt-Root (`Workflow_Verzeichnis_Reinigung.md`).
    - Nach neuen Models/Livewire/Controllern/Services/Seedern `php artisan ai:generate-map` ausführen (synchronisiert automatisch `ai_map.md` und diese `GEMINI.md`).
11. **Live-Screen „Augen & Hände“-Brücke (`C:\Users\konta\Desktop\AgyLiveBridge\`):**
    - Für Live-Sprachführung und direkte Steuerung von Desktop-Apps (insb. **xTool Studio** am F2 Ultra UV) liegt auf dem Desktop die Direkt-Schnittstelle `C:\Users\konta\Desktop\AgyLiveBridge\invoke_hands.ps1` (gekoppelt an den Hintergrund-Task `AgyLiveScreen`).
    - **Sofort-Blick (Augen, `< 1,5 Sek.`):** `powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\Users\konta\Desktop\AgyLiveBridge\invoke_hands.ps1" -Action capture` -> liefert sofort das unverdeckte `xTool Studio`-Fenster unter `C:\Users\konta\Desktop\AgyLiveBridge\xtool_live.png` sowie den Gesamtdesktop unter `desktop_live.png`.
    - **Direkt-Steuerung (Hände):** Unterstützt `-Action click -X <int> -Y <int>`, `-Action dblclick -X <int> -Y <int>`, `-Action drag -X <int> -Y <int> -X2 <int> -Y2 <int>` und `-Action type -X <int> -Y <int> -Text "<wert>"` inkl. automatischem Kontroll-Screenshot nach jeder Aktion.

---

<!-- AI_MAP_START -->
# Seelenfunke System-Architektur & AI Map (`ai_map.md`)
> Generiert via `php artisan ai:generate-map` (2026-11-14 17:20)

## 1. Ökosystem-Pfade & Umgebungen
- **Hauptportal (`Mein-Seelenfunke`)**: Win-UNC `\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke` | WSL `/home/ubuntuxina/meine-projekte/seelenfunke` (Docker PHP: `mein_php_server`) | Lokal `http://127.0.0.1` (`http://seelenfunke.test`) | Stage `https://stage.mein-seelenfunke.de` | Live `https://mein-seelenfunke.de`
- **Funkenwerk Windows Desktop Suite (C#/WPF/.NET 8)**: `\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\funkenwerk` (`MainWindow.xaml.cs`, `CameraStudioEngine.cs`, `SmartHomeEngine.cs`, `MarketingEngine.cs`, `funkenwerk_dlc_bridge.py`) + KI-Face-Swap `C:\Users\konta\Documents\Deep-Live-Cam`
- **Funkenwerk Android App & Widgets (Kotlin/Compose)**: `\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\android-app` (Mirror: `C:\Users\konta\AndroidStudioProjects\seelenfunke-android`)
- **Dokumentation & Belege**: `Abschlussberichte/` (`README.md`, `<Abteilung>/`, `Workflows/`, `Konzepte/`) | Belege: `storage/app/buchhaltung/receipts/` & `contracts/`

## 2. Zentrales Routen- & Begriffs-Mapping (`routes/partials/*.php`)
- **Kommandozentrale / Operativer Status / To-Do Liste**: `/admin/dashboard` -> `Shop\Master\MasterAnalytics` & `Shop\Management\ManagementTask`
- **Buchhaltung (`Shop\Accounting\*`, Seeder: `AccountingSeeder.php`)**:
  - Sonderausgaben / Einkäufe: `/admin/financial-variable-costs` -> `AccountingVariableCosts` (`AccountingSpecialIssue`)
  - Fixkosten / Verträge / Versicherungen: `/admin/financial-fix-costs` -> `AccountingFixCosts` (`AccountingCostItem`, `AccountingGroup`)
  - Rechnungen / Gutschriften / Bank / Steuer-Export / Analyse: `/admin/financial-invoices` (`AccountingInvoice`, `AccountingCredit`), `/admin/financial-banks` (`AccountingBank`), `/admin/financial-tax-exports` (`AccountingTax`, `TaxAdvisorDashboard`), `/admin/financial-analytics` (`AccountingAnalytics`, `AccountingLiquidity`)
- **Produkte, Konfigurator, Verpackung & Lieferanten (`Shop\Product\*`, Seeder: `Product*Seeder.php`)**:
  - Produkte / Varianten / Kalkulator: `/admin/products` -> `ProductCreate`, `ProductShow`, `ProductVariants`, `ProductCalculator`
  - 2D/3D-Konfigurator: `Shop\Product\ProductConfigurator\ProductConfigurator` (`resources/views/livewire/shop/product/product-configurator/`)
  - Verpackungs-Kalkulator: `/admin/packaging` (`ProductPackagingConfigurator`) | Lieferanten: `/admin/suppliers` (`ProductSuppliers`)
  - Crawler / Vorlagen / Kategorien / Attribute / Bewertungen / Bruch: `/admin/product-*` -> `ProductCrawler`, `ProductTemplates`, `ProductCategories`, `ProductAttributes`, `ProductReviews`, `ProductFracture`
- **Bestellungen & Logistik (`Shop\Order\*`, `Shop\Cart\*`, `Shop\System\*`)**:
  - Bestellungen & Laser-Produktion: `/admin/orders` (`OrderOverview`) | Checkout & Warenkörbe: `OrderCheckout`, `/admin/carts` (`OrderShoppingCarts`)
  - Angebote / Widerrufe / Versand (DHL): `/admin/quote-requests` (`OrderQuoteRequests`), `/admin/revocations` (`OrderRevocations`), `/admin/shipping` (`SystemShipping`), `/admin/delivery-display` (`SystemDeliveryDisplay`, `SystemDeliveryTimes`)
- **Leitung / Management (`Shop\Management\*`)**:
  - Mail (`/admin/mail` -> `ManagementEMails`), Kalender (`/admin/calendar` -> `ManagementCalender`), Kontakte (`/admin/contacts` -> `ManagementContacts`), Tagesroutine (`/admin/routine` -> `ManagementRoutine`), Einkaufsliste (`ManagementShoppingList`), Aufgaben (`ManagementTask`), Mitarbeiter (`ManagementEmployees`), Musik (`ManagementMusic`)
- **Marketing & Influencer Portal (`Shop\Marketing\*`)**:
  - Influencer & Partner-Portal: `/admin/marketing-influencers` -> `MarketingInfluencer` & `Employee\Influencer\InfluencerDashboard` (Rolle `influencer` isoliert)
  - Gutscheine (`MarketingVoucher`), Blog (`MarketingBlog`), Newsletter (`MarketingNewsletter`), Landingpages (`MarketingLandingPages`), Herzensprojekte (`MarketingHerzensprojekte`), Linktree (`/links` -> `MarketingLinktree`)
- **KI-Agenten, Support & System (`Shop\Ai\*`, `Shop\Support\*`, `Shop\System\*`)**:
  - Organigramm (`/admin/organigramm` -> `AiCompanyStructure`), Workspace (`/admin/ai-workspace` -> `AiWorkspace`), Agenten (`AiAgentEditor`), Rollen (`AiRoleManager`), Wissen (`AiKnowledgeBase`)
  - Support: Tickets (`SupportTicket`), Chats (`SupportChats`), Telefonie (`SupportTelephony`) | System: `/admin/settings` (`SystemShopConfig`), `/admin/users` (`SystemUserManagement`), `SystemLogs`, `SystemBackups`

## 3. Datenbank-Seeder (`database/seeders/*.php`)
- **Root**: `AccountingSeeder, AiAgentSeeder, AiCompanyStructureSeeder, AiHealthMedicationSeeder, AiKnowledgeBaseLaserSeeder, AiKnowledgeBaseSeeder, AiTarifeSeeder, BankTransactionTestSeeder, BlogCategorySeeder, DatabaseSeeder, FinancialDataSeeder, GamificationTestSeeder, GamificationUltimatePlayerSeeder, LogisticsShippingSeeder, MailAccountSeeder, ManagementCalenderSeeder, ManagementContactSeeder, ManagementDayRoutineSeeder, ManagementMusicSeeder, ManagementShoppingListSeeder, ManagementTaskSeeder, MarketingBlogSeeder, MarketingLinktreeSeeder, MonthlyVoucherSeeder, NewsletterKampagnenSeeder, OrderShoppingCartSeeder, OrdersTableSeeder, ProductAssetSeeder, ProductAttributeSeeder, ProductCategorySeeder, ProductIdeaSeeder, ProductLandingPageSeeder, ProductPackagingSeeder, ProductReviewSeeder, ProductSeeder, ProductSupplierSeeder, ProductTemplateSeeder, Spaceship3DGlbSeeder, SystemCronjobSeeder, SystemSettingSeeder, TaxRateSeeder, UserSeeder`

## 4. Datenbank-Modelle (`app/Models/<Domain>/*.php`)
- **Accounting**: `AccountingBankAccount, AccountingBankTransaction, AccountingCategorizationRule, AccountingCategory, AccountingCostItem, AccountingCostItemHistory, AccountingGroup, AccountingInvoice, AccountingSpecialIssue`
- **Admin**: `Admin, AdminProfile`
- **Ai**: `AiAgent, AiAgentSetting, AiArtifact, AiCall, AiChatMemory, AiChatSession, AiContact, AiDepartment, AiHealthMedication, AiHealthProtocol, AiHealthTreatmentItem, AiHealthTreatmentPlan, AiKnowledgeBase, AiKnowledgeBaseCategory, AiKnowledgeBaseTag, AiMetric, AiRole, AiTool, AiToolUsage, AiUserWorkspaceSetting, AiWidgetConfig, AiWorkspaceDocument, AiWorkspaceTask`
- **Cart**: `Cart, CartItem, WishlistItem`
- **Customer**: `Customer, CustomerGamification, CustomerGamificationBugReport, CustomerGamificationLog, CustomerProfile, GamificationGalacticPlanet`
- **Delivery**: `DeliveryFeedback, DeliverySetting, DeliveryTime`
- **Employee**: `Employee, EmployeeContract, EmployeePayslip, EmployeeProfile, EmployeeTimeEntry`
- **Logistics**: `LogisticsShippingRate, LogisticsShippingZone, LogisticsShippingZoneCountry`
- **Management**: `ManagementCalendarEvent, ManagementContact, ManagementDayRoutine, ManagementDayRoutineLog, ManagementDayRoutineStep, ManagementMission, ManagementPlaylist, ManagementShoppingCategory, ManagementShoppingItem, ManagementTask, ManagementTaskList, ManagementTrack`
- **Management\Mail**: `MailAccount, MailAttachment, MailFolder, MailMessage, MailRule`
- **Marketing**: `MarketingBlogCategory, MarketingBlogPost, MarketingCoupon, MarketingGiftVoucher, MarketingGiftVoucherLog, MarketingHerzensprojektAction, MarketingHerzensprojektTransaction, MarketingInfluencer, MarketingInfluencerCommission, MarketingInfluencerPayout, MarketingLandingPage, MarketingLinktree, MarketingLinktreeClick, MarketingLinktreeVisit, MarketingNewsletter, MarketingNewsletterSubscriber, MarketingReferral, MarketingTrackingLink, MarketingTrackingLinkHit, MarketingVoucher`
- **Order**: `OrderOrder, OrderOrderItem, OrderQuoteRequest, OrderQuoteRequestItem, OrderRevocation, OrderShipment`
- **Product**: `Product, ProductAnalyticsEvent, ProductAttribute, ProductCategory, ProductIdea, ProductLoss, ProductNicheCrawlerRun, ProductNicheItem, ProductPackaging, ProductPackagingItem, ProductReview, ProductSupplier, ProductTemplate, ProductTierPrice`
- **Root**: `SupportTelephonyCall`
- **Support**: `SupportContactRequest, SupportContactRequestMessage, SupportCustomerChat, SupportCustomerChatMessage, SupportTicket, SupportTicketMessage`
- **System**: `SystemAiHostingPlan, SystemBlockedIp, SystemCheckConfig, SystemCronjob, SystemDirectory, SystemLog, SystemLoginAttempt, SystemNeuralNode, SystemPasswordResetToken, SystemSchedulerLog, SystemSetting, SystemUser, SystemUserDevice`
- **Tracking**: `PageVisit`

## 5. Livewire-Komponenten (`app/Livewire/<Domain>/*.php`)
- **Auth**: `AuthLogin, AuthLoginDropdown, AuthPasswordReset, AuthProfileDropdown, AuthRegister`
- **Backend\System**: `SystemNeuralAnalysisIndex`
- **Customer**: `CustomerDashboardComponent, CustomerForcePasswordChange, CustomerFunkiComponent, CustomerInvoicesComponent, CustomerOrdersComponent, CustomerTicketsComponent`
- **Customer\Gamification**: `GameGlobalStatsHeader, GameSparkHuntComponent`
- **Employee**: `EmployeeDashboard`
- **Employee\Influencer**: `InfluencerDashboard`
- **Employee\TaxAdvisor**: `TaxAdvisorDashboard`
- **Frontend**: `ContactForm, GoogleReviews`
- **Frontend\Emergency**: `EmergencyProtocol`
- **Frontend\Employee**: `EmployeeInvitationAccept`
- **Frontend\Marketing**: `InfluencerInvitationAccept, MarketingLinktreePage`
- **Frontend\Support**: `AiFrontendSupportChat`
- **Hooks**: `EmployeeRoleIsolationHook`
- **Landing**: `LandingPageView`
- **Shop\Accounting**: `AccountingAnalytics, AccountingBank, AccountingCredit, AccountingFixCosts, AccountingInvoice, AccountingInvoicePreview, AccountingLiquidity, AccountingQuickEntry, AccountingTax, AccountingVariableCosts`
- **Shop\Ai**: `AiAgentEditor, AiAnalytics, AiCompanyStructure, AiKnowledgeBase, AiKnowledgeBaseCategories, AiKnowledgeBaseTags, AiMethod, AiRoleManager, AiWidget, AiWorkspace`
- **Shop\Ai\PartialsAiWorkspace**: `TabCronjobs`
- **Shop\Ai\Traits**: `ManagesAiChat, ManagesAiWorkspaceFiles, ManagesHealthData`
- **Shop\Cart**: `Cart, CartIcon`
- **Shop\Management**: `ManagementCalender, ManagementContacts, ManagementEMails, ManagementEmployees, ManagementMusic, ManagementRoutine, ManagementShoppingList, ManagementTask`
- **Shop\Marketing**: `MarketingAnalytics, MarketingBlog, MarketingBlogIndex, MarketingBlogShow, MarketingHerzensprojekte, MarketingInfluencer, MarketingLandingPages, MarketingLinktree, MarketingNewsletter, MarketingNewsletterPage, MarketingNewsletterSignup, MarketingTracking, MarketingVoucher, MarketingVoucherBalanceChecker, MarketingVoucherPurchasePage, MarketingVoucherSlider`
- **Shop\Master**: `MasterAnalytics, MasterShopCapacity, MasterStorageCapacity`
- **Shop\Order**: `OrderAnalytics, OrderOverview, OrderQuoteAcceptance, OrderQuoteRequests, OrderRevocationForm, OrderRevocations, OrderShoppingCarts`
- **Shop\Order\OrderCheckout**: `OrderCheckout, OrderCheckoutSuccess`
- **Shop\Order\OrderCheckout\Traits**: `HandlesOrderCreation, HandlesStripePayment`
- **Shop\Product**: `ProductAnalytics, ProductAttributes, ProductCategories, ProductConfiguratorSettings, ProductControlReviews, ProductCrawler, ProductCreate, ProductFracture, ProductFrontendFilterArea, ProductIdeas, ProductPackagingConfigurator, ProductReviews, ProductShow, ProductSuppliers, ProductTax, ProductTemplates, ProductTierPricing, ProductVariants`
- **Shop\Product\ProductCalculator**: `ProductCalculator`
- **Shop\Product\ProductConfigurator**: `ProductConfigurator`
- **Shop\Product\ProductConfigurator\Traits**: `HandlesConfiguratorLogic`
- **Shop\Support**: `SupportAnalytics, SupportChats, SupportContactFormComponent, SupportTelephony, SupportTicket`
- **Shop\System**: `SystemBackups, SystemDeliveryDisplay, SystemDeliveryTimes, SystemLogs, SystemShipping, SystemShopConfig, SystemUserManagement`
- **Shop\Wishlist**: `WishlistIcon, WishlistPage`
- **Traits**: `WithDepartmentTheming`

## 6. HTTP-Controller (`app/Http/Controllers/*.php`)
- **Accounting**: `InvoiceDownloadController`
- **Api**: `TelegramAgentController, TwilioCallController`
- **Customer**: `CustomerVerificationController, DigitalProductDownloadController, GiftVoucherDownloadController`
- **Root**: `AIController, CartReminderUnsubscribeController, Controller, GoogleAuthController, NewsletterController, RevocationFileController, RevocationPdfController, SeoController, StripeWebhookController`

## 7. Services & Geschäftslogik (`app/Services/<Domain>/*.php`)
- **AI**: `AIFunctionsRegistry, AiAgentFactory, AiAgentRouter, AiAgentService, AiAuthHelper, AiPromptService, AiSupportService, FunkiraSkillPoolService, GeminiAgent, GeminiTtsService, ReceiptAiScannerService`
- **AI\Contracts**: `AiProviderInterface`
- **AI\Functions**: `AiAgentsFuncs, AiBrainFuncs, AiCalendarFuncs, AiContactFuncs, AiFinanceFuncs, AiHealthFuncs, AiHolidayPlannerFuncs, AiLaserFuncs, AiMailFuncs, AiMapControlFuncs, AiMapNewsFuncs, AiMarketingBlogFuncs, AiMarketingFuncs, AiMarketingNewsletterFuncs, AiMarketingVoucherFuncs, AiMasterFuncs, AiNewsFuncs, AiOrderFuncs, AiPersonaFuncs, AiProductFuncs, AiRoutineFuncs, AiScoutFuncs, AiShoppingListFuncs, AiSuppliersFuncs, AiSupportFuncs, AiSystemFuncs, AiTaskFuncs, AiTelefonyFuncs`
- **AI\Mails**: `AiAgentMessageMail, AiHolidayPlanMail, AiMapSummaryMail`
- **AI\Workspace**: `DateitrichterService`
- **Accounting**: `ContractDocumentService`
- **Employee**: `EmployeeHrService`
- **Export**: `FileDownloadService`
- **Gamification**: `GameConfig, GamificationService, PlayerNameValidator`
- **Management**: `MailAiSortService`
- **Marketing**: `HolidayOfferAutopilotService, InfluencerService`
- **Navigation**: `BackendNavigationService`
- **Order**: `OrderProductionBatchService`
- **Root**: `AccountingEInvoiceParserService, AccountingXmlInvoiceService, AnalyticsService, BankApiService, CartFileCleanupService, CartService, ConfiguratorService, DhlService, ElsterEricService, FinancialService, FirebaseService, GoogleReviewsService, HerzensprojektService, InvoiceService, PriceCalcService, ProductConfiguratorService, ProductTrackingService, ReferralService, ShippingCalculatorService, StealthCrawlerService, WishlistService`
- **Storage**: `GlbOptimizerService`
- **System**: `AndroidReleaseService, FunkenwerkPrintService, FunkiraPhoneConciergeService, MiniPcTrichterRelayService, SystemBlueprintService, SystemBrainDiagnosticsService, SystemErrorLogger, SystemSecurityService`
- **Tax**: `TaxSettingService`
<!-- AI_MAP_END -->

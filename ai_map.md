# Seelenfunke System-Architektur & AI Map (`ai_map.md`)
> Generiert via `php artisan ai:generate-map` (2026-11-24 18:33)

## 1. Ökosystem-Pfade & Umgebungen
- **Hauptportal (`Mein-Seelenfunke`)**: Win-UNC `\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke` | WSL `/home/ubuntuxina/meine-projekte/seelenfunke` (Docker PHP: `mein_php_server`) | Lokal `http://127.0.0.1` (`http://seelenfunke.test`) | Stage `https://stage.mein-seelenfunke.de` | Live `https://mein-seelenfunke.de`
- **Funkenwerk Windows Desktop Suite (C#/WPF/.NET 8)**: `\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\funkenwerk` (`MainWindow.xaml.cs`, `CameraStudioEngine.cs`, `SmartHomeEngine.cs`, `MarketingEngine.cs`, `funkenwerk_dlc_bridge.py`) + KI-Face-Swap `C:\Users\konta\Documents\Deep-Live-Cam`
- **Funkenwerk Android App & Widgets (Kotlin/Compose)**: `\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\android-app` (Mirror: `C:\Users\konta\AndroidStudioProjects\seelenfunke-android`)
- **Zentrales Wissensgehirn & Belege**: `/admin/tickets?view=knowledge` (`AiKnowledgeBase`, dublettenfrei inkl. automatischer Ticket-Abschlussberichte) | Belege: `storage/app/buchhaltung/receipts/` & `contracts/`

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
- **Accounting**: `AccountingBankAccount, AccountingBankTransaction, AccountingCategorizationRule, AccountingCategory, AccountingCostItem, AccountingCostItemHistory, AccountingGroup, AccountingInvestmentPlan, AccountingInvoice, AccountingSpecialIssue`
- **Admin**: `Admin, AdminProfile`
- **Ai**: `AiAgent, AiAgentSetting, AiArtifact, AiCall, AiChatMemory, AiChatSession, AiContact, AiDepartment, AiHealthMedication, AiHealthProtocol, AiHealthTreatmentItem, AiHealthTreatmentPlan, AiKnowledgeBase, AiKnowledgeBaseCategory, AiKnowledgeBaseTag, AiLearnedPreference, AiMetric, AiRole, AiTool, AiToolUsage, AiUniverseTicket, AiUniverseTicketMessage, AiUserWorkspaceSetting, AiWidgetConfig, AiWorkspaceDocument, AiWorkspaceTask`
- **Cart**: `Cart, CartItem, WishlistItem`
- **Customer**: `Customer, CustomerGamification, CustomerGamificationBugReport, CustomerGamificationLog, CustomerProfile, GamificationGalacticPlanet`
- **Delivery**: `DeliveryFeedback, DeliverySetting, DeliveryTime`
- **Employee**: `Employee, EmployeeContract, EmployeePayslip, EmployeeProfile, EmployeeTimeEntry`
- **Logistics**: `LogisticsShippingRate, LogisticsShippingZone, LogisticsShippingZoneCountry`
- **Management**: `ManagementCalendarEvent, ManagementContact, ManagementDayRoutine, ManagementDayRoutineLog, ManagementDayRoutineStep, ManagementMission, ManagementPlaylist, ManagementShoppingCategory, ManagementShoppingItem, ManagementTask, ManagementTaskList, ManagementTaskMessage, ManagementTaskOperatorProfile, ManagementTrack`
- **Management\Mail**: `MailAccount, MailAttachment, MailFolder, MailMessage, MailRule`
- **Marketing**: `MarketingBlogCategory, MarketingBlogPost, MarketingCoupon, MarketingGiftVoucher, MarketingGiftVoucherLog, MarketingHerzensprojektAction, MarketingHerzensprojektTransaction, MarketingInfluencer, MarketingInfluencerCommission, MarketingInfluencerPayout, MarketingLandingPage, MarketingLinktree, MarketingLinktreeClick, MarketingLinktreeVisit, MarketingNewsletter, MarketingNewsletterSubscriber, MarketingReferral, MarketingTrackingLink, MarketingTrackingLinkHit, MarketingVoucher`
- **Order**: `OrderOrder, OrderOrderItem, OrderQuoteRequest, OrderQuoteRequestItem, OrderRevocation, OrderShipment`
- **Product**: `LaserStudioProject, Product, ProductAnalyticsEvent, ProductAttribute, ProductCategory, ProductIdea, ProductLoss, ProductNicheCrawlerRun, ProductNicheItem, ProductPackaging, ProductPackagingItem, ProductReview, ProductSupplier, ProductTemplate, ProductTierPrice`
- **Root**: `SupportTelephonyCall`
- **Support**: `SupportContactRequest, SupportContactRequestMessage, SupportCustomerChat, SupportCustomerChatMessage, SupportExperienceReview, SupportTicket, SupportTicketMessage`
- **System**: `PersonalAccessToken, SystemAiHostingPlan, SystemBlockedIp, SystemCheckConfig, SystemCronjob, SystemDirectory, SystemLog, SystemLoginAttempt, SystemNeuralNode, SystemPasswordResetToken, SystemSchedulerLog, SystemSetting, SystemUser, SystemUserDevice`
- **Tracking**: `PageVisit`

## 5. Livewire-Komponenten (`app/Livewire/<Domain>/*.php`)
- **Auth**: `AuthLogin, AuthLoginDropdown, AuthPasswordReset, AuthProfileDropdown, AuthRegister`
- **Backend\System**: `SystemNeuralAnalysisIndex`
- **Customer**: `CustomerDashboardComponent, CustomerForcePasswordChange, CustomerFunkiComponent, CustomerInvoicesComponent, CustomerOrdersComponent, CustomerReviewsComponent, CustomerTicketsComponent`
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
- **Shop\Ai**: `AiAgentEditor, AiAnalytics, AiCompanyStructure, AiKnowledgeBase, AiKnowledgeBaseCategories, AiKnowledgeBaseTags, AiRoleManager, AiUniverseTickets, AiWidget, AiWorkspace`
- **Shop\Ai\PartialsAiWorkspace**: `TabCronjobs`
- **Shop\Ai\Traits**: `ManagesAiChat, ManagesAiWorkspaceFiles, ManagesHealthData`
- **Shop\Cart**: `Cart, CartIcon`
- **Shop\Management**: `ManagementCalender, ManagementContacts, ManagementEMails, ManagementEmployees, ManagementMusic, ManagementRoutine, ManagementShoppingList, ManagementTask`
- **Shop\Marketing**: `MarketingAnalytics, MarketingBlog, MarketingBlogIndex, MarketingBlogShow, MarketingHerzensprojekte, MarketingInfluencer, MarketingLandingPages, MarketingLinktree, MarketingNewsletter, MarketingNewsletterPage, MarketingNewsletterSignup, MarketingTracking, MarketingVoucher, MarketingVoucherBalanceChecker, MarketingVoucherPurchasePage, MarketingVoucherSlider`
- **Shop\Master**: `MasterAnalytics, MasterShopCapacity, MasterStorageCapacity`
- **Shop\Order**: `OrderAnalytics, OrderOverview, OrderQuoteAcceptance, OrderQuoteRequests, OrderRevocationForm, OrderRevocations, OrderShoppingCarts`
- **Shop\Order\OrderCheckout**: `OrderCheckout, OrderCheckoutSuccess`
- **Shop\Order\OrderCheckout\Traits**: `HandlesOrderCreation, HandlesStripePayment`
- **Shop\Product**: `LaserStudioWorkspace, ProductAnalytics, ProductAttributes, ProductCategories, ProductConfiguratorSettings, ProductControlReviews, ProductCrawler, ProductCreate, ProductFracture, ProductFrontendFilterArea, ProductIdeas, ProductPackagingConfigurator, ProductReviews, ProductShow, ProductSuppliers, ProductTax, ProductTemplates, ProductTierPricing, ProductVariants`
- **Shop\Product\ProductCalculator**: `ProductCalculator`
- **Shop\Product\ProductConfigurator**: `ProductConfigurator`
- **Shop\Product\ProductConfigurator\Traits**: `HandlesConfiguratorLogic`
- **Shop\Product\Traits**: `ManagesConfiguratorStartSelection`
- **Shop\Support**: `SupportAnalytics, SupportChats, SupportContactFormComponent, SupportReviews, SupportTelephony, SupportTicket`
- **Shop\System**: `SystemBackups, SystemDeliveryDisplay, SystemDeliveryTimes, SystemLogs, SystemShipping, SystemShopConfig, SystemUserManagement`
- **Shop\Wishlist**: `WishlistIcon, WishlistPage`
- **Traits**: `ManagesCustomerProductReviewForm, WithDepartmentTheming`

## 6. HTTP-Controller (`app/Http/Controllers/*.php`)
- **Accounting**: `InvoiceDownloadController`
- **Api**: `AgenticCommerceController, AntigravityDirectInboxController, CustomerLobbyController, FunkenwerkHeroesController, LavaParkourController, TelegramAgentController, TwilioCallController, WorkshopFocusApiController`
- **Customer**: `CustomerVerificationController, DigitalProductDownloadController, ExperienceReviewController, GiftVoucherDownloadController`
- **Frontend**: `FunkenreichGatewayController, ProductMediaController, ShopShowroomController, WishlistController`
- **Root**: `AIController, CartReminderUnsubscribeController, Controller, GoogleAuthController, NewsletterController, RevocationFileController, RevocationPdfController, SeoController, StripeWebhookController`

## 7. Services & Geschäftslogik (`app/Services/<Domain>/*.php`)
- **AI**: `AIFunctionsRegistry, AgentSkillBudgetGuard, AiAgentFactory, AiAgentRouter, AiAgentService, AiAuthHelper, AiOutputQualityGate, AiPromptService, AiSupportService, AntigravityTicketQueueService, AntigravityUniverseTicketBridge, BuchiReceiptPipelineService, CentralBrainService, ConceptLifecycleService, ContextDossierBuilder, FunkiraPresenceContext, FunkiraSkillPoolService, FunkiraTicketRouter, GeminiAgent, GeminiTtsService, KnowledgeReportSyncService, MasterNextBestActionService, ProcurementSafetyGuard, ReceiptAiScannerService, SfInternalOrchestrator, TicketAutoCompactService, TicketDeliverableService, VertexAiService, WebSearchCascadeService`
- **AI\Contracts**: `AiProviderInterface`
- **AI\Functions**: `AiAgentsFuncs, AiBrainFuncs, AiCalendarFuncs, AiContactFuncs, AiFinanceFuncs, AiHealthFuncs, AiHolidayPlannerFuncs, AiLaserFuncs, AiMailFuncs, AiMapControlFuncs, AiMapNewsFuncs, AiMarketingBlogFuncs, AiMarketingFuncs, AiMarketingNewsletterFuncs, AiMarketingVoucherFuncs, AiMasterFuncs, AiNewsFuncs, AiOrderFuncs, AiPersonaFuncs, AiProductFuncs, AiRoutineFuncs, AiScoutFuncs, AiShoppingListFuncs, AiSmartHomeFuncs, AiStripeFuncs, AiSuppliersFuncs, AiSupportFuncs, AiSystemFuncs, AiTaskFuncs, AiTelefonyFuncs, AiUniverseTicketFuncs`
- **AI\Mails**: `AiAgentMessageMail, AiHolidayPlanMail, AiMapSummaryMail`
- **AI\Workspace**: `AbschlussberichteIndexerService, DateitrichterService`
- **Accounting**: `ContractDocumentService, PreLiveOrderAndInvoiceCleanupService`
- **Employee**: `EmployeeHrService`
- **Export**: `FileDownloadService`
- **Gamification**: `FunkenreichSsoBridgeService, GameConfig, GamificationService, LavaParkourService, LobbyInstanceService, PlayerNameValidator`
- **Laser**: `LaserPhotoPreprocessor, LaserQrCodeService, XtoolLaserCockpitService`
- **Management**: `MailAiSortService, MailAttachmentFunnelService, ManagementTaskTicketService`
- **Marketing**: `HolidayOfferAutopilotService, InfluencerService`
- **Navigation**: `BackendNavigationService`
- **Order**: `OrderProductionBatchService, ShopCapacityService`
- **Product**: `ProductQuickAiLinkAnalyzerService`
- **Root**: `AccountingEInvoiceParserService, AccountingXmlInvoiceService, AnalyticsService, BankApiService, CartFileCleanupService, CartService, ConfiguratorService, DhlService, ElsterEricService, FinancialService, FirebaseService, GoogleReviewsService, HerzensprojektService, InvoiceService, PriceCalcService, ProductConfiguratorService, ProductTrackingService, ReferralService, ShippingCalculatorService, StealthCrawlerService, StripeAgentExecutionService, WishlistService`
- **Shipping**: `GrossbriefLabelService`
- **Shop**: `ShowroomChatGuardService, ShowroomMultiplayerService`
- **Storage**: `GlbOptimizerService`
- **System**: `AndroidReleaseService, AntigravityDirectInboxService, AudioMulawCodec, FunkenwerkPrintService, FunkiraPhoneConciergeService, KdriveTrichterRelayService, LogArchiveService, MiniPcTrichterRelayService, SentinelTriageService, SystemAlertTicketService, SystemBlueprintService, SystemBrainDiagnosticsService, SystemErrorLogger, SystemHealthPulseService, SystemSecurityService, TwilioGeminiLiveBridgeService, TwilioReverbServerFactory, ZeroTokenSelfHealer`
- **Tax**: `TaxSettingService`


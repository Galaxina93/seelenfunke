using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Controls.Primitives;
using System.Windows.Input;
using System.Windows.Media;

namespace Funkenwerk
{
    public class DailyShootRecipeDto
    {
        public int DayNumber { get; set; }
        public string WeekdayLabel { get; set; } = "";
        public string CategoryBadge { get; set; } = "";
        public string Title { get; set; } = "";
        public string BaseFormatTitle { get; set; } = "";
        public int ProductVariantIndex { get; set; } = 0;
        public string ProductName { get; set; } = "";
        public string DurationLabel { get; set; } = "";
        public string CapCutTemplate { get; set; } = "";
        public string Step1Setup { get; set; } = "";
        public string Step2Hook { get; set; } = "";
        public string Step3Clips { get; set; } = "";
        public string Step4Edit { get; set; } = "";
        public string OnScreenHookText { get; set; } = "";
        public string AudioDirection { get; set; } = "";
        public string ReadyCaption { get; set; } = "";
        public string LandingPageSlug { get; set; } = "";
        public bool IsCompletedToday { get; set; } = false;
        public string RecommendedMusicTrackId { get; set; } = "";
    }

    public class ViralVideoAnalysisDto
    {
        public int Rank { get; set; }
        public string Title { get; set; } = "";
        public string Duration { get; set; } = "";
        public string Views { get; set; } = "";
        public string HookFirst3Sec { get; set; } = "";
        public string SoundUsed { get; set; } = "";
        public string SpecialFeature { get; set; } = "";
        public string SeelenfunkeAdaptation { get; set; } = "";
        public int MappedRecipeDay { get; set; } = 1;
    }

    public class TopCompetitorAccountDto
    {
        public int Rank { get; set; }
        public string Name { get; set; } = "";
        public string Handle { get; set; } = "";
        public string Platform { get; set; } = ""; // "TikTok & IG", "TikTok", "Instagram"
        public string Cluster { get; set; } = "";  // "schmuck", "kristall", "laser", "luxury"
        public string ClusterLabel { get; set; } = "";
        public string Followers { get; set; } = "";
        public string AvgVideoLength { get; set; } = "";
        public string BioStructure { get; set; } = "";
        public string WebsiteFunnel { get; set; } = "";
        public string GoogleBusinessProfile { get; set; } = "";
        public List<string> WinningSecrets { get; set; } = new();
        public List<ViralVideoAnalysisDto> TopVideos { get; set; } = new();
    }

    public class MarketQuestionItemDto
    {
        public int Number { get; set; }
        public string CategoryKey { get; set; } = ""; // "base", "hook", "camera", "story", "algo", "funnel", "ads"
        public string CategoryName { get; set; } = "";
        public string Question { get; set; } = "";
        public string MarketFinding { get; set; } = "";
        public string SeelenfunkeRule { get; set; } = "";
    }

    public class CustomAiScanRecordDto
    {
        public string Id { get; set; } = Guid.NewGuid().ToString("N");
        public string ContentHash { get; set; } = "";
        public string CreatedAt { get; set; } = DateTime.Now.ToString("dd.MM.yyyy HH:mm");
        public string TargetName { get; set; } = "";
        public string DurationAndHook { get; set; } = "";
        public string SoundAndVisuals { get; set; } = "";
        public string BioAndFunnel { get; set; } = "";
        public string ActionableTakeaway { get; set; } = "";
    }

    public class ShopMarketingProductDto
    {
        public string Id { get; set; } = "";
        public string Slug { get; set; } = "";
        public string Name { get; set; } = "";
        public string FullDisplayName { get; set; } = "";
        public string Type { get; set; } = "physical";
        public string LandingSlug { get; set; } = "";
        public string MaterialLabel { get; set; } = "";
        public bool SupportsWestpackBox { get; set; } = false;
        public bool UsesRotaryRa3 { get; set; } = false;
        public bool Has3dConfigurator { get; set; } = true;
        public bool HasBackSide { get; set; } = false;
        public int BasePriceCents { get; set; } = 0;
        public int DirectCostsCents { get; set; } = 0;
        public int PaymentFeeCents { get; set; } = 0;
        public int NetProfitBeforeAdsCents { get; set; } = 0;
        public int TargetCpaCents { get; set; } = 0;
        public int MaxCpaCents { get; set; } = 0;
        public int NetProfitAtTargetCpaCents { get; set; } = 0;
        public double NetMarginPercent { get; set; } = 0;
        public bool IsAdZugpferd { get; set; } = false;
        public string AdRole { get; set; } = "";
        public bool IsHolidayFocus { get; set; } = false;
        public string SetupItems { get; set; } = "";
        public string Hashtags { get; set; } = "";
        public string RecommendedFormat { get; set; } = "";
        public string DefaultHook { get; set; } = "";
        public string DefaultClips { get; set; } = "";
    }

    public class ShopHolidayContextDto
    {
        public string SeasonKey { get; set; } = "herbst";
        public string SeasonName { get; set; } = "Herbst";
        public string SeasonIcon { get; set; } = "🍂";
        public string ActiveThemeLabel { get; set; } = "Herbst (Goldenes Laub)";
        public string EffectiveMotto { get; set; } = "";
        public string EffectiveHookPrefix { get; set; } = "";
        public string EffectiveCaptionAddon { get; set; } = "";
        public string EffectiveHashtags { get; set; } = "";
        public string PrimaryHolidayKey { get; set; } = "";
        public string PrimaryHolidayName { get; set; } = "";
        public string PrimaryHolidayBadge { get; set; } = "";
        public string PrimaryHolidayWindow { get; set; } = "";
        public string PrimaryHolidayCutoff { get; set; } = "";
        public string PrimaryHolidayStrategyHint { get; set; } = "";
        public int PrimaryHolidayDiscountPercent { get; set; } = 10;
        public int DaysUntilHolidayStart { get; set; } = 0;
        public int DaysUntilHolidayEvent { get; set; } = 0;
        public bool IsHolidayActiveNow { get; set; } = false;
        public List<string> FocusProductSlugs { get; set; } = new();
        public string LastShopSyncAt { get; set; } = "";
        public bool IsLiveShopSynced { get; set; } = false;
    }

    public class MarketingVaultStorageDto
    {
        public string LastUpdated { get; set; } = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
        public bool IsFocusMode { get; set; } = false;
        public int CurrentRound { get; set; } = 1;
        public int TotalVideosCompleted { get; set; } = 0;
        public int ActiveDailyRecipeIndex { get; set; } = -1;
        public List<int> CompletedRecipeDays { get; set; } = new();
        public string LastDailyRoutineReset { get; set; } = "";
        public string LastWeeklyRoutineReset { get; set; } = "";
        public string LastMonthlyRoutineReset { get; set; } = "";
        public List<string> CompletedRoutineIds { get; set; } = new();
        public List<ShopMarketingProductDto> ShopProducts { get; set; } = new();
        public ShopHolidayContextDto SeasonContext { get; set; } = new();
        public List<DailyShootRecipeDto> DailyRecipes { get; set; } = new();
        public List<TopCompetitorAccountDto> TopAccounts { get; set; } = new();
        public List<MarketQuestionItemDto> QuestionsCatalog { get; set; } = new();
        public List<CustomAiScanRecordDto> CustomAiScans { get; set; } = new();
        public List<ViralConceptIdeaDto> ViralIdeas { get; set; } = new();
        public int ActiveViralIdeaIndex { get; set; } = 0;
        public List<MarketingMusicTrackDto> MusicCatalog { get; set; } = new();
    }

    public class MarketingMusicTrackDto
    {
        public int TrackNumber { get; set; }
        public string Id { get; set; } = "";
        public string FileName { get; set; } = "";
        public string ExportFileName { get; set; } = "";
        public string CategoryKey { get; set; } = "viral"; // "viral", "camera", "werkstatt", "atelier"
        public string CategoryLabel { get; set; } = "";
        public string Title { get; set; } = "";
        public string Subtitle { get; set; } = "";
        public int Bpm { get; set; } = 84;
        public string MusicalKey { get; set; } = "D-Moll";
        public int DurationSeconds { get; set; } = 75;
        public string DurationFormatted => $"{DurationSeconds / 60}:{DurationSeconds % 60:00}";
        public string DropTimingLabel { get; set; } = "";
        public string ScenarioGuide { get; set; } = "";
        public string MixingGuide { get; set; } = "";
        public string InstrumentationGuide { get; set; } = "";
        public string SunoUdioPrompt { get; set; } = "";
    }

    public class ViralConceptIdeaDto
    {
        public int Rank { get; set; }
        public string CategoryKey { get; set; } = "launch"; // "launch", "asmr", "story", "quick"
        public string CategoryLabel { get; set; } = "";
        public string Title { get; set; } = "";
        public string BaseTitle { get; set; } = "";
        public string DurationLabel { get; set; } = "";
        public string EffortLabel { get; set; } = "";
        public string ViewsBenchmark { get; set; } = "";
        public string OnScreenHook { get; set; } = "";
        public string TrendingSongs { get; set; } = "";
        public string ShortSongBadge { get; set; } = "";
        public string ReferenceTrendUrl { get; set; } = "";
        public string StructureBreakdown { get; set; } = "";
        public string WhyItGoesViral { get; set; } = "";
        public string Step1Setup { get; set; } = "";
        public string Step2Shoot { get; set; } = "";
        public string Step3EditAndPost { get; set; } = "";
        public string ReadyCaptionAndTags { get; set; } = "";
        public int ProductVariantIndex { get; set; } = 0;
        public string ActiveProductName { get; set; } = "";
        public string RecommendedMusicTrackId { get; set; } = "";
    }

    public partial class MainWindow : Window
    {
        private bool _isMarketingInitialized = false;
        private string _marketingVaultPath = "";
        private MarketingVaultStorageDto _mktVault = new();
        private int _currentDailyRecipeIndex = 0;
        private int _currentViralIdeaIndex = 0;
        private bool _isFocusViralDropdownOpen = false;
        private string _activeMarketingSubTab = "plan"; // "plan", "routine", "accounts", "questions", "templates", "ads", "viral", "music"
        private string _activeAccountClusterFilter = "all";
        private string _activeQuestionCategoryFilter = "all";
        private string _activeViralCategoryFilter = "all";
        private string _activeMusicCategoryFilter = "all";
        private string _marketingSearchQuery = "";
        private TopCompetitorAccountDto? _selectedCompetitorAccount = null;
        private DailyShootRecipeDto? _selectedPlanRecipe = null;
        private ViralConceptIdeaDto? _selectedViralIdea = null;
        private MarketingMusicTrackDto? _selectedMusicTrack = null;
        private readonly MediaPlayer _mktMusicPreviewPlayer = new();
        private string _currentlyPlayingMktTrackId = "";
        private bool _isMktMusicPreviewPlaying = false;

        private static List<ShopMarketingProductDto> BuildDefaultShopProducts()
        {
            return new List<ShopMarketingProductDto>
            {
                new ShopMarketingProductDto
                {
                    Slug = "seelen-kristall",
                    Name = "Der Seelen Kristall",
                    FullDisplayName = "Der Seelen Kristall (69,90 € · K9-Kristallglas 3D)",
                    Type = "physical",
                    LandingSlug = "/l/seelen-kristall",
                    MaterialLabel = "Optisch reines K9 Kristallglas",
                    SupportsWestpackBox = false,
                    UsesRotaryRa3 = false,
                    Has3dConfigurator = true,
                    HasBackSide = false,
                    BasePriceCents = 6990,
                    DirectCostsCents = 1326,
                    PaymentFeeCents = 130,
                    NetProfitBeforeAdsCents = 4418,
                    TargetCpaCents = 1050,
                    MaxCpaCents = 1400,
                    NetProfitAtTargetCpaCents = 3368,
                    NetMarginPercent = 57.3,
                    IsAdZugpferd = true,
                    AdRole = "ZUGPFERD #1 (Paid Ads & Skalierung)",
                    SetupItems = "Weiße Handschuhe · Der Seelen Kristall (K9) · LED-Leuchtsockel · xTool F2 Ultra UV",
                    Hashtags = "#meinseelenfunke #seelenkristall #3dglasgravur #erinnerungsstück #personalisiertegeschenke",
                    RecommendedFormat = "Licht-Reveal im Dunkeln & Emotionale Story",
                    DefaultHook = "Wenn aus einem einfachen Handyfoto ein leuchtendes 3D-Unikat im Kristall wird 🤍✨",
                    DefaultClips = "Clip 1: 4s Dunkler Raum, Einschalten des LED-Sockels · Clip 2: 6s UV-Laser im Glas · Clip 3: 6s Nahaufnahme der 3D-Lichtbrechung"
                },
                new ShopMarketingProductDto
                {
                    Slug = "goldenes-seelen-herz",
                    Name = "Das Goldene Seelen-Herz",
                    FullDisplayName = "Das Goldene Seelen-Herz (49,90 € · 14k vergoldet inkl. Kette)",
                    Type = "physical",
                    LandingSlug = "/l/goldenes-seelen-herz",
                    MaterialLabel = "Massiver Edelstahl (14 Karat vergoldet)",
                    SupportsWestpackBox = true,
                    UsesRotaryRa3 = false,
                    Has3dConfigurator = true,
                    HasBackSide = true,
                    BasePriceCents = 4990,
                    DirectCostsCents = 835,
                    PaymentFeeCents = 100,
                    NetProfitBeforeAdsCents = 3258,
                    TargetCpaCents = 750,
                    MaxCpaCents = 1000,
                    NetProfitAtTargetCpaCents = 2508,
                    NetMarginPercent = 59.8,
                    IsAdZugpferd = true,
                    AdRole = "ZUGPFERD #2 (Paid Ads & Skalierung)",
                    SetupItems = "Weiße Handschuhe · Das Goldene Seelen-Herz · xTool F2 Ultra UV · Schwarze Westpack-Box",
                    Hashtags = "#meinseelenfunke #erinnerungsschmuck #goldenesherz #gravurschmuck #handgemacht",
                    RecommendedFormat = "White-Glove ASMR & Westpack-Box Reveal",
                    DefaultHook = "Wenn eine Handschrift für die Ewigkeit in 14k Gold gebrannt wird ✨🤍",
                    DefaultClips = "Clip 1: 4s Laser-Makro · Clip 2: 5s Abpinseln mit weißen Handschuhen · Clip 3: 5s Einlegen in schwarze Westpack-Box"
                },
                new ShopMarketingProductDto
                {
                    Slug = "seelen-talisman",
                    Name = "Der Seelen-Talisman",
                    FullDisplayName = "Der Seelen-Talisman (36,90 € · Gold-Edelstahl Anhänger)",
                    Type = "physical",
                    LandingSlug = "/l/seelen-talisman",
                    MaterialLabel = "Hochglanzpolierter Edelstahl (Gold-Finish)",
                    SupportsWestpackBox = true,
                    UsesRotaryRa3 = false,
                    Has3dConfigurator = true,
                    HasBackSide = true,
                    BasePriceCents = 3690,
                    DirectCostsCents = 510,
                    PaymentFeeCents = 80,
                    NetProfitBeforeAdsCents = 2511,
                    TargetCpaCents = 650,
                    MaxCpaCents = 900,
                    NetProfitAtTargetCpaCents = 1861,
                    NetMarginPercent = 60.0,
                    IsAdZugpferd = true,
                    AdRole = "ZUGPFERD #3 (Geschenk-Ads & Retargeting)",
                    SetupItems = "Weiße Handschuhe · Der Seelen-Talisman · xTool F2 Ultra UV · Schwarze Westpack-Box",
                    Hashtags = "#meinseelenfunke #seelentalisman #glücksbringer #koordinatenschmuck #liebesbeweis",
                    RecommendedFormat = "White-Glove ASMR & Beidseitiger Koordinaten-Reveal",
                    DefaultHook = "Die Koordinaten des Ortes, an dem alles begann – auf ewig in Gold-Edelstahl ✨",
                    DefaultClips = "Clip 1: 4s Lasergravur der Koordinaten · Clip 2: 5s Wenden auf die Rückseite mit weißen Handschuhen · Clip 3: 5s Westpack-Box"
                },
                new ShopMarketingProductDto
                {
                    Slug = "seelen-pfote-hundemarke",
                    Name = "Die Seelen-Pfote",
                    FullDisplayName = "Die Seelen-Pfote (29,90 € · Edelstahl-Hundemarke)",
                    Type = "physical",
                    LandingSlug = "/l/seelen-pfote-hundemarke",
                    MaterialLabel = "Hochglanzpolierter Edelstahl (Rostfrei)",
                    SupportsWestpackBox = true,
                    UsesRotaryRa3 = false,
                    Has3dConfigurator = true,
                    HasBackSide = true,
                    BasePriceCents = 2990,
                    DirectCostsCents = 492,
                    PaymentFeeCents = 70,
                    NetProfitBeforeAdsCents = 1951,
                    TargetCpaCents = 0,
                    MaxCpaCents = 650,
                    NetProfitAtTargetCpaCents = 1951,
                    NetMarginPercent = 77.6,
                    IsAdZugpferd = false,
                    AdRole = "ORGANISCH (Reichweiten-Magnet · 0 € Einzel-Ads)",
                    SetupItems = "Weiße Handschuhe · Die Seelen-Pfote · xTool F2 Ultra UV · Schwarze Westpack-Box",
                    Hashtags = "#meinseelenfunke #seelenpfote #hundemarke #pfotenliebe #hundegeschenk",
                    RecommendedFormat = "Laser-ASMR & Haustier-Sicherheits-Story",
                    DefaultHook = "Hör dir dieses Geräusch an, wenn aus Edelstahl ein Unikat für deine Fellnase wird 🐾🔊",
                    DefaultClips = "Clip 1: 4s Funkenflug auf der Knochenform · Clip 2: 5s Rückseite mit Notfallnummer · Clip 3: 5s Westpack-Box"
                },
                new ShopMarketingProductDto
                {
                    Slug = "seelen-anhaenger",
                    Name = "Der Seelenanhänger",
                    FullDisplayName = "Der Seelenanhänger (29,90 € · Massives Alu-Herz)",
                    Type = "physical",
                    LandingSlug = "/l/seelen-anhaenger",
                    MaterialLabel = "Schwarz eloxiertes Aluminium (Massiv)",
                    SupportsWestpackBox = true,
                    UsesRotaryRa3 = false,
                    Has3dConfigurator = true,
                    HasBackSide = true,
                    BasePriceCents = 2990,
                    DirectCostsCents = 530,
                    PaymentFeeCents = 70,
                    NetProfitBeforeAdsCents = 1913,
                    TargetCpaCents = 0,
                    MaxCpaCents = 650,
                    NetProfitAtTargetCpaCents = 1913,
                    NetMarginPercent = 76.1,
                    IsAdZugpferd = false,
                    AdRole = "ORGANISCH (Reichweiten-Magnet · 0 € Einzel-Ads)",
                    SetupItems = "Weiße Handschuhe · Der Seelenanhänger · xTool F2 Ultra UV · Schwarze Westpack-Box",
                    Hashtags = "#meinseelenfunke #seelenanhänger #handschriftgravur #unikat #geschenkmitherz",
                    RecommendedFormat = "Kontrast-Lasergravur (Silber-Weiß auf Tiefschwarz)",
                    DefaultHook = "Wenn auf mattschwarzem Metall plötzlich Omas echte Handschrift in Silber-Weiß erscheint 🤍",
                    DefaultClips = "Clip 1: 4s Kontrast-Laserblitz · Clip 2: 5s Vorder- & Rückseite im Streiflicht · Clip 3: 5s Westpack-Box"
                },
                new ShopMarketingProductDto
                {
                    Slug = "weizenglas-personalisiert",
                    Name = "Der Feierabend-Funke",
                    FullDisplayName = "Der Feierabend-Funke (29,90 € · 360° Weizenglas)",
                    Type = "physical",
                    LandingSlug = "/l/weizenglas-personalisiert",
                    MaterialLabel = "Brillantes Kristallglas (0,5l)",
                    SupportsWestpackBox = false,
                    UsesRotaryRa3 = true,
                    Has3dConfigurator = true,
                    HasBackSide = false,
                    BasePriceCents = 2990,
                    DirectCostsCents = 993,
                    PaymentFeeCents = 70,
                    NetProfitBeforeAdsCents = 1450,
                    TargetCpaCents = 0,
                    MaxCpaCents = 550,
                    NetProfitAtTargetCpaCents = 1450,
                    NetMarginPercent = 57.7,
                    IsAdZugpferd = false,
                    AdRole = "ORGANISCH (Männer- & Vereins-Magnet · 0 € Einzel-Ads)",
                    SetupItems = "Weiße Handschuhe · Der Feierabend-Funke (Weizenglas) · xTool RA3 Rotationsmodul · Gegenlicht-Lampe",
                    Hashtags = "#meinseelenfunke #weizenglas #männergeschenk #glasgravur #feierabendfunke",
                    RecommendedFormat = "360° Rotationsgravur (RA3) & Gegenlicht-Reveal",
                    DefaultHook = "Wenn sich das Weizenglas im Laser um 360° dreht 🍺✨",
                    DefaultClips = "Clip 1: 4s Einspannen ins RA3 Rotationsmodul · Clip 2: 6s 360°-Gravur · Clip 3: 5s Gegenlicht-Reveal"
                },
                new ShopMarketingProductDto
                {
                    Slug = "geschenkgutschein",
                    Name = "Seelenfunken Geschenkgutschein",
                    FullDisplayName = "Geschenkgutschein (Gold-Metallkarte & Siegel · ab 25 €)",
                    Type = "digital",
                    LandingSlug = "/l/geschenkgutschein",
                    MaterialLabel = "PDF-Sofortdownload oder Goldene Metallkarte mit Siegel",
                    SupportsWestpackBox = false,
                    UsesRotaryRa3 = false,
                    Has3dConfigurator = false,
                    HasBackSide = false,
                    BasePriceCents = 5000,
                    DirectCostsCents = 250,
                    PaymentFeeCents = 100,
                    NetProfitBeforeAdsCents = 3850,
                    TargetCpaCents = 600,
                    MaxCpaCents = 1000,
                    NetProfitAtTargetCpaCents = 3250,
                    NetMarginPercent = 77.3,
                    IsAdZugpferd = true,
                    AdRole = "LAST-MINUTE RETTER (Ab Annahmeschluss bis Heiligabend/Muttertag)",
                    SetupItems = "Weiße Handschuhe · Goldene Gutschein-Metallkarte · Schwarzer Umschlag · Gold-Wachssiegel",
                    Hashtags = "#meinseelenfunke #geschenkgutschein #lastminutegeschenk #wachssiegel #geschenkidee",
                    RecommendedFormat = "White-Glove Kuvertierung & Wachssiegel-ASMR",
                    DefaultHook = "Zu spät für den Paketversand? Unser Gutschein als Gold-Metallkarte oder Sofort-PDF ✨✉️",
                    DefaultClips = "Clip 1: 4s Lasergravur der goldenen Gutscheinkarte · Clip 2: 5s Einlegen in schwarzen Umschlag · Clip 3: 5s Gold-Siegel"
                }
            };
        }

        private static readonly (string Id, string Cycle, string PillarBadge, string Title, string Description, string TimeLabel, string QuickJumpTab)[] _marketingRoutineMatrix = new[]
        {
            // 1x AM TAG (4 Säulen: Content, Community, Zahlen, Markt-Radar)
            ("d1", "daily", "1. CONTENT · 1x TAG", "Tägliches Reel / TikTok posten", "Heutiges Video aus der Tagesempfehlung (18:30–20:00 Uhr) veröffentlichen + kurz mit Konfigurator-Link in die Story teilen.", "5 Min.", "plan"),
            ("d2", "daily", "2. COMMUNITY · 1x TAG", "Kommentare & DMs beantworten", "Direkt nach dem Posten alle neuen Kommentare & Nachrichten herzlich beantworten (gerne mit Gegenfrage für den Algorithmus).", "5 Min.", ""),
            ("d3", "daily", "3. ZAHLEN · 1x TAG", "Skip-Rate & Views prüfen", "Kurzer Blick ins gestrige Video: Haben > 65 % der Zuschauer die ersten 3 Sekunden (Hook) weitergeschaut?", "2 Min.", ""),
            ("d4", "daily", "4. RADAR · 1x TAG", "10 Min. Fokus-Scrolling", "Bewusst im Nischen-Feed (Gravur, Schmuck, Geschenke) nach aktuellen Piano-/ASMR-Sounds und starken Hooks schauen.", "10 Min.", "accounts"),

            // 1x DIE WOCHE (4 Säulen: Drehtag, Live Q&A, Top 3 Performer, 5 Videos speichern)
            ("w1", "weekly", "1. CONTENT · 1x WOCHE", "Fester Werkstatt-Drehtag (Batch-Dreh)", "Alle 5–7 Videos der Woche in einem Rutsch (60–90 Min.) mit weißen Handschuhen am xTool F2 Ultra UV vordrehen.", "60 Min.", "camera"),
            ("w2", "weekly", "2. COMMUNITY · 1x WOCHE", "Live Q&A / Live-Gravur aus Gifhorn", "15–20 Min. Instagram/TikTok Live: Zuschauer schauen live beim Gravieren & Verpacken in die Westpack-Box zu.", "20 Min.", ""),
            ("w3", "weekly", "3. ZAHLEN · 1x WOCHE", "Top 3 Performer identifizieren", "Die 3 stärksten Videos der Woche (Watchtime & Speicher-Rate) küren -> Gewinner-Hook wiederholen & für Spark Ads vormerken.", "10 Min.", "ads"),
            ("w4", "weekly", "4. RADAR · 1x WOCHE", "5 virale Benchmark-Videos speichern", "5 herausragende Videos aus der Nische speichern oder direkt im Funkenwerk 37-Punkte-Röntgen auf ihre Erfolgsformel scannen.", "10 Min.", "questions"),

            // 1x IM MONAT (4 Säulen: Content-Säulen Update, Community-Challenge, Volle Analyse, 3 neue Formate)
            ("m1", "monthly", "1. CONTENT · 1x MONAT", "Content-Säulen Update", "Fokus-Produkte & saisonale Anlässe des Monats festlegen (Muttertag, Vatertag, Hochzeit, Weihnachten, Haustier-Gedenken).", "15 Min.", "plan"),
            ("m2", "monthly", "2. COMMUNITY · 1x MONAT", "Community-Challenge („Seelen-Geschichte“)", "Follower teilen ihre bewegendste Geschichte in den Kommentaren – 1 Gewinner-Unikat wird kostenlos im Story-Video gefertigt.", "20 Min.", ""),
            ("m3", "monthly", "3. ZAHLEN · 1x MONAT", "Volle Content- & Funnel-Analyse", "Prüfen, welche Videos die meisten Klicks auf die Landingpages (/l/...) und 3D-Konfigurator-Bestellungen brachten; schwächste 20 % streichen.", "20 Min.", ""),
            ("m4", "monthly", "4. RADAR · 1x MONAT", "3 neue Video-Formate testen", "3 frische Hook- oder Kamerawinkel-Experimente (z. B. neuer Licht-Reveal, Makro-Perspektive, Split-Screen) für diesen Monat testen.", "15 Min.", "templates")
        };

        private static DateTime GetEasterDate(int year)
        {
            int k = year / 100;
            int m = 15 + (3 * k + 3) / 4 - (8 * k + 13) / 25;
            int s = 2 - (3 * k + 3) / 4;
            int a = year % 19;
            int d = (19 * a + m) % 30;
            int r = d / 29 + (d / 28 - d / 29) * (a / 11);
            int og = 21 + d - r;
            int sz = 7 - (year + year / 4 + s) % 7;
            int oe = 7 - (og - sz) % 7;
            return new DateTime(year, 3, 1).AddDays(og + oe - 1).Date;
        }

        private static DateTime GetShopHolidayDate(string key, int year)
        {
            return key switch
            {
                "valentines" => new DateTime(year, 2, 14),
                "womens_day" => new DateTime(year, 3, 8),
                "easter" => GetEasterDate(year),
                "mothers_day" => GetSecondSundayOfMay(year),
                "fathers_day" => GetEasterDate(year).AddDays(39),
                "sale_summer" => new DateTime(year, 7, 1),
                "halloween" => new DateTime(year, 10, 31),
                "advent_1" => GetFirstAdventDate(year),
                "christmas" => new DateTime(year, 12, 24),
                "new_year" => new DateTime(year, 1, 1),
                "sale_winter" => new DateTime(year, 1, 4),
                _ => DateTime.Today
            };
        }

        private static DateTime GetSecondSundayOfMay(int year)
        {
            var d = new DateTime(year, 5, 1);
            int daysToSunday = ((int)DayOfWeek.Sunday - (int)d.DayOfWeek + 7) % 7;
            return d.AddDays(daysToSunday + 7);
        }

        private static DateTime GetFirstAdventDate(int year)
        {
            var d = new DateTime(year, 11, 26).AddDays(1);
            while (d.DayOfWeek != DayOfWeek.Sunday) d = d.AddDays(1);
            return d;
        }

        private static ShopHolidayContextDto ComputeLocalSeasonAndHolidayContext(DateTime today)
        {
            today = today.Date;
            int month = today.Month;
            int year = today.Year;

            string seasonKey = (month >= 3 && month <= 5) ? "ostern"
                : (month >= 6 && month <= 8) ? "sommer"
                : (month >= 9 && month <= 11) ? "herbst"
                : "winter";

            var (seasonName, seasonIcon, seasonMotto, seasonHook, seasonAddon, seasonTags) = seasonKey switch
            {
                "ostern" => ("Frühling", "🌸",
                    "🌸 Frühlings-Saison: „Neuanfang, Taufe, Kommunion & Herzensmenschen feiern.“",
                    "Ein persönliches Erinnerungsstück für die schönsten Frühlings-Anlässe 🌸✨",
                    "🌸 Frühlings-Manufaktur: Perfekt für Geburtstag, Taufe, Hochzeit oder als Liebesbeweis.",
                    "#frühling #taufgeschenk #geburtstagsgeschenk #lieblingsmensch"),
                "sommer" => ("Sommer", "☀️",
                    "☀️ Sommer- & Feierabend-Saison: „Hochzeiten, Jubiläen & gesellige Abende mit Charakter.“",
                    "Für unvergessliche Sommerabende und besondere Menschen ☀️✨",
                    "☀️ Sommer-Atelier Gifhorn: Handveredelt für Hochzeiten, runde Geburtstage und laue Feierabende.",
                    "#sommergeschenk #hochzeit #feierabend #jubiläumsgeschenk"),
                "herbst" => ("Herbst", "🍂",
                    "🍂 Goldener Herbst & Lichterzeit: „Wärme, Geborgenheit & leuchtende Erinnerungen für drinnen.“",
                    "Wenn es draußen stürmt, zählen die Erinnerungen, die uns von innen wärmen 🍂🕯️",
                    "🍂 Herbst- & Lichterzeit: Jetzt schon entspannt an die ersten Geschenke für die Festtage denken!",
                    "#goldenerherbst #lichterzeit #erinnerungsschmuck #gemütlichkeit"),
                _ => ("Winter", "❄️",
                    "❄️ Winter- & Festtags-Saison: „Zeit für tiefe Dankbarkeit und bleibende Erinnerungen.“",
                    "Manche Erinnerungen verdienen einen Ehrenplatz für die Ewigkeit ❄️✨",
                    "❄️ Winter-Manufaktur Gifhorn: Mit weißen Handschuhen gefertigt und bereit zum Verschenken.",
                    "#winter #geschenkvonherzen #erinnerungsstück #manufaktur")
            };

            var holidayDefs = new (string Key, string Name, string Badge, int StartBefore, int EndBefore, int Discount, string Hint, string Motto, string Hook, string Addon, string Tags, string[] Slugs)[]
            {
                ("valentines", "Valentinstag-Kampagne", "💘 Valentins-Sale", 21, 4, 12,
                    "Romantik-Offensive (24.01.–10.02.): Fokus auf Herz-Schmuck & Kristall. Annahmeschluss 4 Tage vor Valentinstag.",
                    "💘 Valentinstag: „Schenke keine Blumen, die verwelken. Schenke einen Moment für die Ewigkeit.“",
                    "Suchst du noch ein Valentinstags-Geschenk, das wirklich Gänsehaut auslöst? 💘✨",
                    "💘 Valentinstags-Tipp: Jetzt rechtzeitig vor Annahmeschluss sichern, damit es pünktlich zum 14.02. in der schwarzen Geschenkbox bereitliegt!",
                    "#valentinstagsgeschenk #geschenkfürsie #geschenkfürhn #liebesbeweis",
                    new[] { "goldenes-seelen-herz", "seelen-anhaenger", "seelen-kristall", "seelen-talisman" }),

                ("womens_day", "Weltfrauentag-Aktion", "💐 Frauentags-Sale", 16, 3, 10,
                    "Wertschätzungs-Kampagne (20.02.–05.03.) für Partnerinnen, Mütter und beste Freundinnen.",
                    "💐 Weltfrauentag: „Kleine Gesten der Wertschätzung für die wichtigsten Frauen in deinem Leben.“",
                    "Zum Weltfrauentag am 08.03.: Ein Unikat für eine besondere Frau 💐✨",
                    "💐 Zum Weltfrauentag: Zeige deine Wertschätzung mit einer persönlichen Wunschgravur aus unserer Manufaktur.",
                    "#weltfrauentag #frauentagsgeschenk #wertschätzung #geschenkfürfrauen",
                    new[] { "goldenes-seelen-herz", "seelen-kristall", "seelen-anhaenger" }),

                ("easter", "Oster-Manufaktur-Aktion", "🐰 Oster-Angebot", 21, 4, 10,
                    "Frühlings- & Familiengeschenke ins Osternest (endet Karfreitag für pünktlichen Versand).",
                    "🐰 Ostern & Frühling: „Persönliche Erinnerungsstücke im Osternest statt nur Schokolade.“",
                    "Was dieses Jahr ins Osternest kommt, hält ein Leben lang 🐰🌸",
                    "🐰 Oster-Manufaktur-Aktion: Gestalte jetzt dein persönliches Ostergeschenk für die Familie!",
                    "#ostergeschenk #osternest #frühlingsgefühle #familiengeschenk",
                    new[] { "seelen-kristall", "seelen-anhaenger", "seelen-talisman", "seelen-pfote-hundemarke" }),

                ("mothers_day", "Muttertags-Herzensaktion", "💝 Muttertags-Sale", 21, 4, 12,
                    "Emotionaler Jahres-Peak für Mamas: 3 Wochen Vorlauf, Annahmeschluss Mittwoch vor Muttertag.",
                    "💝 Muttertag: „Für die Frau, die immer für uns da war: Mamas Herzstück in Gold & Kristall.“",
                    "Wenn Mama beim Auspacken am Muttertag die Tränen kommen 💝🥹",
                    "💝 Muttertags-Vorlauf: Sichere dir jetzt rechtzeitig vor Annahmeschluss Mamas ganz persönliches Erinnerungsstück!",
                    "#muttertagsgeschenk #bestemama #dankemama #erinnerungsschmuck",
                    new[] { "goldenes-seelen-herz", "seelen-kristall", "seelen-anhaenger" }),

                ("fathers_day", "Vatertags- & Männertags-Aktion", "🍺 Vatertags-Sale", 21, 4, 10,
                    "3 Wochen Vorlauf auf das personalisierte 360°-Weizenglas, den Seelen-Talisman und die Hundemarke.",
                    "🍺 Vatertag & Männertag: „Charakterstarke Unikate mit 360°-Gravur für echte Helden.“",
                    "Das einzige Vatertags-Geschenk, das Papa wirklich jeden Abend stolz nutzt 🍺🔥",
                    "🍺 Vatertags-Special: Ob 360°-Weizenglas oder massiver Seelen-Talisman – jetzt im 3D-Konfigurator für Papa gestalten!",
                    "#vatertagsgeschenk #männergeschenk #besterpapa #bierglasgravur",
                    new[] { "weizenglas-personalisiert", "seelen-talisman", "seelen-pfote-hundemarke" }),

                ("sale_summer", "Großer Manufaktur Sommer-Sale", "☀️ Sommer-Sale", 0, -14, 15,
                    "Sommerloch-Überbrückung (01.07.–15.07.): Fokus auf Hochzeitsgeschenke, Grill- & Biergarten-Saison.",
                    "☀️ Sommer- & Hochzeits-Saison: „Goldene Sommer-Momente, Hochzeiten & Gartenfeste veredelt.“",
                    "Das perfekte Hochzeits- & Sommergeschenk aus unserer Manufaktur ☀️💍",
                    "☀️ Sommer- & Hochzeits-Saison: Jedes Stück wird frisch in Gifhorn für deinen besonderen Anlass gefertigt.",
                    "#hochzeitsgeschenk #sommerfest #personalisiertegeschenke #manufaktur",
                    new[] { "weizenglas-personalisiert", "seelen-kristall", "seelen-talisman", "seelen-pfote-hundemarke" }),

                ("halloween", "Goldener Herbst & Lichter-Aktion", "🎃 Herbst- & Lichter-Aktion", 18, 1, 10,
                    "Herbstlicher Q4-Auftakt (13.10.–30.10.): Fokus auf leuchtendes Kristallglas & Haustier-Marken in der dunklen Jahreszeit.",
                    "🕯️ Herbst & Lichterzeit: „Wenn die Tage dunkler werden, bringen wir Erinnerungen im Kristall zum Leuchten.“",
                    "Wenn es draußen dunkel wird und diese Erinnerung plötzlich aufleuchtet 🕯️✨",
                    "🍂 Herbst- & Lichter-Aktion: Bringe deine schönsten Momente in der dunklen Jahreszeit zum Strahlen.",
                    "#lichterzeit #herbstdeko #erinnerungslicht #gedenkgeschenk",
                    new[] { "seelen-kristall", "seelen-pfote-hundemarke", "seelen-talisman" }),

                ("advent_1", "Advents-Frühbucher-Aktion", "🕯️ Advents-Deal", 14, 0, 10,
                    "Entzerrt die Laser-Produktion im November: Frühbucher-Vorteil für die ersten Weihnachtsgeschenke.",
                    "🕯️ Advents- & Vorweihnachtszeit: „Frühbucher-Ruhe genießen: Weihnachtsgeschenke ohne Dezember-Stress.“",
                    "Wer jetzt im Advent bestellt, hat an Weihnachten das emotionalste Geschenk unter dem Baum 🕯️🎄",
                    "🕯️ Advents-Frühbucher-Tipp: Jetzt entspannt vor dem großen Weihnachts-Ansturm dein Unikat in Gifhorn fertigen lassen!",
                    "#adventszeit #weihnachtsgeschenke #frühbucher #geschenkevonherzen",
                    new[] { "seelen-kristall", "goldenes-seelen-herz", "seelen-anhaenger", "seelen-talisman" }),

                ("christmas", "Weihnachts-Manufaktur-Aktion", "🎄 Weihnachts-Sale", 23, 6, 12,
                    "Haupt-Weihnachtsgeschäft (01.12.–18.12.): Endet am 18.12. für garantierte Lieferung vor Heiligabend.",
                    "🎄 Weihnachten in der Manufaktur: „Geschenke, die unter dem Weihnachtsbaum für echte Freudentränen sorgen.“",
                    "Das Weihnachtsgeschenk, bei dem unter dem Baum garantiert kein Auge trocken bleibt 🎄🥹",
                    "🎄 Weihnachts-Manufaktur Gifhorn: Bitte beachte unseren Bestell-Annahmeschluss (18.12.) für pünktliche Lieferung bis Heiligabend!",
                    "#weihnachtsgeschenk #geschenkideeweihnachten #freudentränen #heiligabend",
                    new[] { "seelen-kristall", "goldenes-seelen-herz", "seelen-anhaenger", "seelen-talisman", "weizenglas-personalisiert", "seelen-pfote-hundemarke" }),

                ("new_year", "Neujahrs-Glücksbringer-Aktion", "🎆 Neujahrs-Deal", 0, -7, 10,
                    "Neujahrs-Woche (01.01.–08.01.): Fokus auf Talismane & Gutschein-Einlösungen nach Weihnachten.",
                    "🎆 Neujahr & Jahresrückblick: „Dein schönster Moment des Jahres für immer festgehalten.“",
                    "Welchen Moment aus diesem Jahr möchtest du für immer festhalten? 🎆✨",
                    "🎆 Neujahrs-Impuls: Halte deinen wichtigsten Meilenstein oder Glücksmoment für das neue Jahr fest.",
                    "#neujahr #jahresrückblick #glücksbringer #meilenstein",
                    new[] { "seelen-talisman", "goldenes-seelen-herz", "seelen-anhaenger" }),

                ("sale_winter", "Winter-Atelier-Sale", "❄️ Winter-Sale", 0, -12, 12,
                    "Winter-Aktion (04.01.–16.01.): Belebt das ruhige Januar-Geschäft vor dem Valentinstags-Start.",
                    "❄️ Winter-Atelier: „Gutschein-Einlösung & edle Seelenwärmer für kalte Wintertage.“",
                    "Du hast zu Weihnachten einen Gutschein bekommen? So entsteht daraus dein Unikat ❄️🤍",
                    "❄️ Winter-Atelier: Löse jetzt deinen Weihnachts-Gutschein ein oder gestalte dein persönliches Winter-Unikat.",
                    "#winterzeit #gutscheineinlösen #wunschgravur #seelenwärmer",
                    new[] { "seelen-kristall", "weizenglas-personalisiert", "seelen-pfote-hundemarke" })
            };

            ShopHolidayContextDto? bestCandidate = null;
            long bestSortScore = long.MaxValue;

            foreach (var h in holidayDefs)
            {
                DateTime evDate = GetShopHolidayDate(h.Key, year);
                DateTime startDate = evDate.AddDays(-h.StartBefore).Date;
                DateTime endDate = evDate.AddDays(-h.EndBefore).Date;

                if (endDate < today)
                {
                    evDate = GetShopHolidayDate(h.Key, year + 1);
                    startDate = evDate.AddDays(-h.StartBefore).Date;
                    endDate = evDate.AddDays(-h.EndBefore).Date;
                }

                bool isActive = today >= startDate && today <= endDate;
                int daysUntilStart = Math.Max(0, (int)(startDate - today).TotalDays);
                int daysUntilEvent = Math.Max(0, (int)(evDate - today).TotalDays);
                long sortScore = isActive ? 0 : startDate.Ticks;

                if (sortScore < bestSortScore)
                {
                    bestSortScore = sortScore;
                    bool useHolidayFocus = isActive || daysUntilStart <= 28;
                    bestCandidate = new ShopHolidayContextDto
                    {
                        SeasonKey = seasonKey,
                        SeasonName = seasonName,
                        SeasonIcon = seasonIcon,
                        ActiveThemeLabel = isActive ? h.Name : $"Jahreszeit: {seasonName}",
                        EffectiveMotto = useHolidayFocus ? h.Motto : seasonMotto,
                        EffectiveHookPrefix = useHolidayFocus ? h.Hook : seasonHook,
                        EffectiveCaptionAddon = useHolidayFocus ? h.Addon : seasonAddon,
                        EffectiveHashtags = useHolidayFocus ? h.Tags : seasonTags,
                        PrimaryHolidayKey = h.Key,
                        PrimaryHolidayName = h.Name,
                        PrimaryHolidayBadge = h.Badge,
                        PrimaryHolidayWindow = $"{startDate:dd.MM.} – {endDate:dd.MM.yyyy}",
                        PrimaryHolidayCutoff = $"{endDate:dd.MM.yyyy}",
                        PrimaryHolidayStrategyHint = h.Hint,
                        PrimaryHolidayDiscountPercent = h.Discount,
                        DaysUntilHolidayStart = daysUntilStart,
                        DaysUntilHolidayEvent = daysUntilEvent,
                        IsHolidayActiveNow = isActive,
                        FocusProductSlugs = h.Slugs.ToList()
                    };
                }
            }

            return bestCandidate ?? new ShopHolidayContextDto
            {
                SeasonKey = seasonKey,
                SeasonName = seasonName,
                SeasonIcon = seasonIcon,
                EffectiveMotto = seasonMotto,
                EffectiveHookPrefix = seasonHook,
                EffectiveCaptionAddon = seasonAddon,
                EffectiveHashtags = seasonTags
            };
        }

        private void InitMarketingEngine()
        {
            EnsureMarketingInitialized();
        }

        private void EnsureMarketingInitialized()
        {
            if (_isMarketingInitialized) return;
            _isMarketingInitialized = true;

            try
            {
                string vaultDir = Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "Funkenwerk",
                    "MarketingVault");
                Directory.CreateDirectory(vaultDir);
                _marketingVaultPath = Path.Combine(vaultDir, "market_intelligence.json");

                LoadOrSeedMarketingVault();
            }
            catch
            {
                _mktVault = BuildDefaultMarketingVault();
            }

            if (_mktVault.ShopProducts == null || _mktVault.ShopProducts.Count == 0)
            {
                _mktVault.ShopProducts = BuildDefaultShopProducts();
            }

            // Aktualisiere den lokalen Saison- & Feiertags-Kontext sofort deterministisch nach heutigem Datum
            var localSeason = ComputeLocalSeasonAndHolidayContext(DateTime.Today);
            if (!string.IsNullOrEmpty(_mktVault.SeasonContext?.LastShopSyncAt))
            {
                localSeason.LastShopSyncAt = _mktVault.SeasonContext.LastShopSyncAt;
                localSeason.IsLiveShopSynced = _mktVault.SeasonContext.IsLiveShopSynced;
            }
            _mktVault.SeasonContext = localSeason;

            EnsureMarketingMusicInitialized();
            IntegrateDynamicShopProductsIntoPlan();
            EnsureViralIdeasInitialized();
            CheckAndResetRoutineCycles();

            _mktMusicPreviewPlayer.MediaEnded -= MktMusicPreviewPlayer_MediaEnded;
            _mktMusicPreviewPlayer.MediaEnded += MktMusicPreviewPlayer_MediaEnded;

            // Pick daily recommendation automatically by day of year if not manually overridden today
            if (_mktVault.DailyRecipes.Count > 0)
            {
                if (_mktVault.ActiveDailyRecipeIndex >= 0 && _mktVault.ActiveDailyRecipeIndex < _mktVault.DailyRecipes.Count)
                {
                    _currentDailyRecipeIndex = _mktVault.ActiveDailyRecipeIndex;
                }
                else
                {
                    _currentDailyRecipeIndex = (DateTime.Today.DayOfYear - 1) % _mktVault.DailyRecipes.Count;
                }
                _selectedPlanRecipe = _mktVault.DailyRecipes[_currentDailyRecipeIndex];
            }

            if (_mktVault.ViralIdeas.Count > 0)
            {
                if (_mktVault.ActiveViralIdeaIndex >= 0 && _mktVault.ActiveViralIdeaIndex < _mktVault.ViralIdeas.Count)
                {
                    _currentViralIdeaIndex = _mktVault.ActiveViralIdeaIndex;
                }
                else
                {
                    _currentViralIdeaIndex = 0; // Startet standardmäßig mit #1: Handy in Schleife durchschneiden!
                }
                _selectedViralIdea = _mktVault.ViralIdeas[_currentViralIdeaIndex];
            }

            _selectedCompetitorAccount = _mktVault.TopAccounts.FirstOrDefault();
            _selectedMusicTrack = _mktVault.MusicCatalog.FirstOrDefault();

            RenderDailyHeroBanner();
            RenderDynamicSeasonAndAdsUI();
            StyleClusterFilterButtons();
            StyleQuestionCategoryButtons();
            StyleViralFilterButtons();
            StyleMusicCategoryButtons();
            SwitchMarketingSubTabUI("plan");
            ApplyMarketingModeUI(_mktVault.IsFocusMode, save: false);

            // Starte im Hintergrund den Live-Sync mit dem Mein-Seelenfunke Shop (lädt neue Produkte, Preise & Saison-Einstellungen)
            _ = SyncMarketingWithShopAsync(manualTrigger: false);
        }

        private void RenderMarketingThemeRefresh()
        {
            if (!_isMarketingInitialized) return;
            EnsureMarketingMusicInitialized();
            RenderDailyHeroBanner();
            RenderDynamicSeasonAndAdsUI();
            StyleClusterFilterButtons();
            StyleQuestionCategoryButtons();
            StyleViralFilterButtons();
            StyleMusicCategoryButtons();
            SwitchMarketingSubTabUI(_activeMarketingSubTab);
            RenderMarketingPlanPane();
            RenderMarketingRoutinePane();
            RenderMarketingAccountsPane();
            RenderMarketingQuestionsPane();
            RenderMarketingViralPane();
            RenderMarketingMusicPane();
            RenderFocusViralDropdown();
            ApplyMarketingModeUI(_mktVault.IsFocusMode, save: false);
        }

        private async void MktSyncWithShop_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            if (sender is Button btn)
            {
                string orig = btn.Content?.ToString() ?? "🔄 Shop-Sync";
                btn.Content = "⏳ Synchronisiere...";
                await SyncMarketingWithShopAsync(manualTrigger: true);
                btn.Content = "✓ Shop synchron!";
                _ = Task.Delay(1600).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
            }
            else
            {
                await SyncMarketingWithShopAsync(manualTrigger: true);
            }
        }

        private async Task SyncMarketingWithShopAsync(bool manualTrigger = false)
        {
            try
            {
                var resp = await _http.GetAsync($"{_apiBaseUrl}/funki/marketing/live-context");
                if (!resp.IsSuccessStatusCode)
                {
                    if (manualTrigger)
                    {
                        _mktVault.SeasonContext = ComputeLocalSeasonAndHolidayContext(DateTime.Today);
                        RenderDynamicSeasonAndAdsUI();
                    }
                    return;
                }

                string json = await resp.Content.ReadAsStringAsync();
                using var doc = JsonDocument.Parse(json);
                if (!doc.RootElement.TryGetProperty("data", out var dataEl)) return;

                // 1. Parse Season & Primary Holiday from Shop
                var seasonCtx = ComputeLocalSeasonAndHolidayContext(DateTime.Today);
                if (dataEl.TryGetProperty("season", out var sEl))
                {
                    seasonCtx.SeasonKey = sEl.TryGetProperty("key", out var k) ? (k.GetString() ?? seasonCtx.SeasonKey) : seasonCtx.SeasonKey;
                    seasonCtx.SeasonName = sEl.TryGetProperty("name", out var n) ? (n.GetString() ?? seasonCtx.SeasonName) : seasonCtx.SeasonName;
                    seasonCtx.SeasonIcon = sEl.TryGetProperty("icon", out var ic) ? (ic.GetString() ?? seasonCtx.SeasonIcon) : seasonCtx.SeasonIcon;
                    seasonCtx.ActiveThemeLabel = sEl.TryGetProperty("active_theme_label", out var atl) ? (atl.GetString() ?? seasonCtx.ActiveThemeLabel) : seasonCtx.ActiveThemeLabel;
                    seasonCtx.EffectiveMotto = sEl.TryGetProperty("effective_motto", out var em) ? (em.GetString() ?? seasonCtx.EffectiveMotto) : seasonCtx.EffectiveMotto;
                    seasonCtx.EffectiveHookPrefix = sEl.TryGetProperty("effective_hook_prefix", out var ehp) ? (ehp.GetString() ?? seasonCtx.EffectiveHookPrefix) : seasonCtx.EffectiveHookPrefix;
                    seasonCtx.EffectiveCaptionAddon = sEl.TryGetProperty("effective_caption_addon", out var eca) ? (eca.GetString() ?? seasonCtx.EffectiveCaptionAddon) : seasonCtx.EffectiveCaptionAddon;
                    seasonCtx.EffectiveHashtags = sEl.TryGetProperty("effective_hashtags", out var eh) ? (eh.GetString() ?? seasonCtx.EffectiveHashtags) : seasonCtx.EffectiveHashtags;
                }

                if (dataEl.TryGetProperty("primary_holiday", out var phEl) && phEl.ValueKind == JsonValueKind.Object)
                {
                    seasonCtx.PrimaryHolidayKey = phEl.TryGetProperty("event_key", out var pk) ? (pk.GetString() ?? "") : "";
                    seasonCtx.PrimaryHolidayName = phEl.TryGetProperty("name", out var pn) ? (pn.GetString() ?? "") : "";
                    seasonCtx.PrimaryHolidayBadge = phEl.TryGetProperty("badge_text", out var pb) ? (pb.GetString() ?? "") : "";
                    seasonCtx.PrimaryHolidayWindow = phEl.TryGetProperty("window_formatted", out var pw) ? (pw.GetString() ?? "") : "";
                    seasonCtx.PrimaryHolidayCutoff = phEl.TryGetProperty("cutoff_date_formatted", out var pc) ? (pc.GetString() ?? "") : "";
                    seasonCtx.PrimaryHolidayStrategyHint = phEl.TryGetProperty("strategy_hint", out var psh) ? (psh.GetString() ?? "") : "";
                    seasonCtx.PrimaryHolidayDiscountPercent = phEl.TryGetProperty("discount_percent", out var pdp) && pdp.TryGetInt32(out int discVal) ? discVal : 10;
                    seasonCtx.DaysUntilHolidayStart = phEl.TryGetProperty("days_until_start", out var dus) && dus.TryGetInt32(out int dusVal) ? dusVal : 0;
                    seasonCtx.DaysUntilHolidayEvent = phEl.TryGetProperty("days_until_event", out var due) && due.TryGetInt32(out int dueVal) ? dueVal : 0;
                    seasonCtx.IsHolidayActiveNow = phEl.TryGetProperty("is_active_now", out var ian) && ian.ValueKind == JsonValueKind.True;
                }

                seasonCtx.LastShopSyncAt = DateTime.Now.ToString("dd.MM. HH:mm");
                seasonCtx.IsLiveShopSynced = true;
                _mktVault.SeasonContext = seasonCtx;

                // 2. Parse Dynamic Shop Products
                if (dataEl.TryGetProperty("products", out var prodArr) && prodArr.ValueKind == JsonValueKind.Array)
                {
                    var liveProducts = new List<ShopMarketingProductDto>();
                    foreach (var pEl in prodArr.EnumerateArray())
                    {
                        var dto = new ShopMarketingProductDto
                        {
                            Id = pEl.TryGetProperty("id", out var idPr) ? (idPr.GetString() ?? "") : "",
                            Slug = pEl.TryGetProperty("slug", out var slPr) ? (slPr.GetString() ?? "") : "",
                            Name = pEl.TryGetProperty("name", out var nmPr) ? (nmPr.GetString() ?? "") : "",
                            FullDisplayName = pEl.TryGetProperty("full_display_name", out var fdPr) ? (fdPr.GetString() ?? "") : "",
                            Type = pEl.TryGetProperty("type", out var tpPr) ? (tpPr.GetString() ?? "physical") : "physical",
                            LandingSlug = pEl.TryGetProperty("landing_slug", out var lsPr) ? (lsPr.GetString() ?? "") : "",
                            MaterialLabel = pEl.TryGetProperty("material_label", out var mlPr) ? (mlPr.GetString() ?? "") : "",
                            SupportsWestpackBox = pEl.TryGetProperty("supports_westpack_box", out var swPr) && swPr.ValueKind == JsonValueKind.True,
                            UsesRotaryRa3 = pEl.TryGetProperty("uses_rotary_ra3", out var raPr) && raPr.ValueKind == JsonValueKind.True,
                            Has3dConfigurator = pEl.TryGetProperty("has_3d_configurator", out var tdPr) && tdPr.ValueKind == JsonValueKind.True,
                            HasBackSide = pEl.TryGetProperty("has_back_side", out var bsPr) && bsPr.ValueKind == JsonValueKind.True,
                            BasePriceCents = pEl.TryGetProperty("base_price_cents", out var bpPr) && bpPr.TryGetInt32(out int bpV) ? bpV : 0,
                            DirectCostsCents = pEl.TryGetProperty("direct_costs_cents", out var dcPr) && dcPr.TryGetInt32(out int dcV) ? dcV : 0,
                            PaymentFeeCents = pEl.TryGetProperty("payment_fee_cents", out var pfPr) && pfPr.TryGetInt32(out int pfV) ? pfV : 0,
                            NetProfitBeforeAdsCents = pEl.TryGetProperty("net_profit_before_ads_cents", out var npbPr) && npbPr.TryGetInt32(out int npbV) ? npbV : 0,
                            TargetCpaCents = pEl.TryGetProperty("target_cpa_cents", out var tcPr) && tcPr.TryGetInt32(out int tcV) ? tcV : 0,
                            MaxCpaCents = pEl.TryGetProperty("max_cpa_cents", out var mcPr) && mcPr.TryGetInt32(out int mcV) ? mcV : 0,
                            NetProfitAtTargetCpaCents = pEl.TryGetProperty("net_profit_at_target_cpa_cents", out var npaPr) && npaPr.TryGetInt32(out int npaV) ? npaV : 0,
                            NetMarginPercent = pEl.TryGetProperty("net_margin_percent", out var nmPctPr) && nmPctPr.TryGetDouble(out double nmPctV) ? nmPctV : 0.0,
                            IsAdZugpferd = pEl.TryGetProperty("is_ad_zugpferd", out var azPr) && azPr.ValueKind == JsonValueKind.True,
                            AdRole = pEl.TryGetProperty("ad_role", out var arPr) ? (arPr.GetString() ?? "") : "",
                            IsHolidayFocus = pEl.TryGetProperty("is_holiday_focus", out var hfPr) && hfPr.ValueKind == JsonValueKind.True,
                            SetupItems = pEl.TryGetProperty("setup_items", out var siPr) ? (siPr.GetString() ?? "") : "",
                            Hashtags = pEl.TryGetProperty("hashtags", out var htPr) ? (htPr.GetString() ?? "") : "",
                            RecommendedFormat = pEl.TryGetProperty("recommended_format", out var rfPr) ? (rfPr.GetString() ?? "") : "",
                            DefaultHook = pEl.TryGetProperty("default_hook", out var dhPr) ? (dhPr.GetString() ?? "") : "",
                            DefaultClips = pEl.TryGetProperty("default_clips", out var dclPr) ? (dclPr.GetString() ?? "") : ""
                        };
                        if (!string.IsNullOrWhiteSpace(dto.Name))
                        {
                            liveProducts.Add(dto);
                        }
                    }

                    if (liveProducts.Count > 0)
                    {
                        _mktVault.ShopProducts = liveProducts;
                    }
                }

                IntegrateDynamicShopProductsIntoPlan();
                SaveMarketingVaultOffline();

                Dispatcher.Invoke(() =>
                {
                    RenderDailyHeroBanner();
                    RenderDynamicSeasonAndAdsUI();
                    if (_activeMarketingSubTab == "plan") RenderMarketingPlanPane();
                });
            }
            catch
            {
                // Bei Offline-Betrieb bleibt der lokale Saison- & Produkt-Tresor aktiv
            }
        }

        /// <summary>
        /// Prüft alle aktiven Produkte aus dem Mein-Seelenfunke Shop (_mktVault.ShopProducts).
        /// Wenn ein neues Produkt im Shop angelegt wurde, das noch keinen eigenen Drehtag im Plan hat,
        /// wird es vollautomatisch mit passendem 4-Schritte-Drehrezept, Setup, Hook & Landingpage in den Drehplan aufgenommen!
        /// </summary>
        private void IntegrateDynamicShopProductsIntoPlan()
        {
            if (_mktVault.ShopProducts == null || _mktVault.ShopProducts.Count == 0)
            {
                _mktVault.ShopProducts = BuildDefaultShopProducts();
            }

            if (_mktVault.DailyRecipes == null || _mktVault.DailyRecipes.Count == 0) return;

            string[] weekdays = { "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag", "Sonntag" };
            bool addedNew = false;

            foreach (var prod in _mktVault.ShopProducts)
            {
                if (string.IsNullOrWhiteSpace(prod.Name)) continue;

                // Prüfe, ob dieses Shop-Produkt bereits im Drehplan vorkommt (anhand Name oder LandingSlug)
                bool existsInPlan = _mktVault.DailyRecipes.Any(r =>
                    (!string.IsNullOrEmpty(prod.Slug) && r.LandingPageSlug.Contains(prod.Slug, StringComparison.OrdinalIgnoreCase)) ||
                    r.ProductName.Contains(prod.Name, StringComparison.OrdinalIgnoreCase) ||
                    r.Title.Contains(prod.Name, StringComparison.OrdinalIgnoreCase));

                if (!existsInPlan)
                {
                    int nextDayNum = _mktVault.DailyRecipes.Count + 1;
                    string weekday = weekdays[(nextDayNum - 1) % 7];
                    string formatBadge = !string.IsNullOrWhiteSpace(prod.RecommendedFormat) ? prod.RecommendedFormat : "White-Glove ASMR";
                    string hookText = !string.IsNullOrWhiteSpace(prod.DefaultHook)
                        ? prod.DefaultHook
                        : $"Wenn aus {prod.Name} in unserer Manufaktur ein persönliches Unikat wird ✨🤍";
                    string clipsText = !string.IsNullOrWhiteSpace(prod.DefaultClips)
                        ? prod.DefaultClips
                        : $"Clip 1: 4s Laser-Start auf {prod.Name} · Clip 2: 5s Detailprüfung mit weißen Handschuhen · Clip 3: 5s Finaler Reveal";
                    string seasonAddon = _mktVault.SeasonContext?.EffectiveCaptionAddon ?? "";

                    var newRecipe = new DailyShootRecipeDto
                    {
                        DayNumber = nextDayNum,
                        WeekdayLabel = weekday,
                        CategoryBadge = formatBadge,
                        BaseFormatTitle = formatBadge,
                        ProductVariantIndex = Math.Max(0, _mktVault.ShopProducts.IndexOf(prod)),
                        Title = $"{prod.Name} – {formatBadge}",
                        ProductName = !string.IsNullOrWhiteSpace(prod.FullDisplayName) ? prod.FullDisplayName : prod.Name,
                        DurationLabel = prod.UsesRotaryRa3 ? "17 Sek." : "15 Sek.",
                        CapCutTemplate = prod.UsesRotaryRa3 ? "Vorlage A: Pure Atelier ASMR (17s)" : "Vorlage A: Pure Atelier ASMR (15s)",
                        Step1Setup = !string.IsNullOrWhiteSpace(prod.SetupItems) ? prod.SetupItems : $"Weiße Handschuhe · {prod.Name} · xTool F2 Ultra UV",
                        Step2Hook = $"0–3s: Start exakt beim ersten Laser-Impuls auf {prod.Name}",
                        Step3Clips = clipsText,
                        Step4Edit = "CapCut Vorlage A · Schnitte auf 1,2s · Original Laser-Sound + sanftes Piano (-8 dB)",
                        OnScreenHookText = hookText,
                        AudioDirection = "0–4s Original Laser-Zischen (0 dB) -> ab Sek. 4 sanftes Piano (-8 dB)",
                        ReadyCaption = $"Neu in unserer Manufaktur in Gifhorn: {prod.Name} ✨🤍 Jedes Stück wird mit weißen Handschuhen für dich veredelt.\n\n{(string.IsNullOrWhiteSpace(seasonAddon) ? "" : seasonAddon + "\n\n")}👉 Jetzt live im Shop gestalten (Link in Bio: {prod.LandingSlug})!\n\n{prod.Hashtags}",
                        LandingPageSlug = !string.IsNullOrWhiteSpace(prod.LandingSlug) ? prod.LandingSlug : $"/produkt/{prod.Slug}",
                        IsCompletedToday = false
                    };

                    _mktVault.DailyRecipes.Add(newRecipe);
                    addedNew = true;
                }
            }

            if (addedNew)
            {
                SaveMarketingVaultOffline();
            }
        }

        private void RenderDynamicSeasonAndAdsUI()
        {
            var s = _mktVault.SeasonContext ?? ComputeLocalSeasonAndHolidayContext(DateTime.Today);
            var prods = (_mktVault.ShopProducts != null && _mktVault.ShopProducts.Count > 0)
                ? _mktVault.ShopProducts
                : BuildDefaultShopProducts();

            if (MktHeroSeasonBadgeTxt != null)
            {
                if (s.IsHolidayActiveNow && !string.IsNullOrEmpty(s.PrimaryHolidayBadge))
                {
                    MktHeroSeasonBadgeTxt.Text = $"{s.PrimaryHolidayBadge} AKTIV (bis {s.PrimaryHolidayCutoff})";
                }
                else if (!string.IsNullOrEmpty(s.PrimaryHolidayName))
                {
                    MktHeroSeasonBadgeTxt.Text = $"{s.SeasonIcon} {s.SeasonName} · Nächster Peak: {s.PrimaryHolidayBadge} (Start in {s.DaysUntilHolidayStart}d)";
                }
                else
                {
                    MktHeroSeasonBadgeTxt.Text = $"{s.SeasonIcon} Jahreszeit: {s.SeasonName}";
                }
            }

            if (MktHeroSeasonMottoTxt != null)
            {
                MktHeroSeasonMottoTxt.Text = !string.IsNullOrEmpty(s.EffectiveMotto)
                    ? $"{s.EffectiveMotto}"
                    : $"{s.SeasonIcon} Aktuelle Jahreszeit: {s.SeasonName}";
            }

            if (MktHeroShopSyncMetaTxt != null)
            {
                string syncLabel = s.IsLiveShopSynced && !string.IsNullOrEmpty(s.LastShopSyncAt)
                    ? $"Live-Sync ({s.LastShopSyncAt})"
                    : "Shop-Autopilot aktiv";
                MktHeroShopSyncMetaTxt.Text = $"🛍️ {prods.Count} Shop-Produkte im Plan · {syncLabel}";
            }

            // Focus Mode: Jahreszeit & Nächster Feiertag klar & knackig oben und in der Karte
            int totalDays = _mktVault.DailyRecipes?.Count > 0 ? _mktVault.DailyRecipes.Count : 14;
            var currentRecipe = (_mktVault.DailyRecipes != null && _mktVault.DailyRecipes.Count > 0)
                ? _mktVault.DailyRecipes[Math.Clamp(_currentDailyRecipeIndex, 0, _mktVault.DailyRecipes.Count - 1)]
                : null;
            string dayInfo = currentRecipe != null
                ? $"TAG {currentRecipe.DayNumber:00}/{totalDays:00} · {currentRecipe.WeekdayLabel.ToUpperInvariant()}"
                : $"RUNDE {_mktVault.CurrentRound}";

            if (MktFocusTopBadgeTxt != null)
            {
                string holidayShort = !string.IsNullOrEmpty(s.PrimaryHolidayBadge)
                    ? $"   ·   {s.PrimaryHolidayBadge.ToUpperInvariant()}"
                    : "";
                MktFocusTopBadgeTxt.Text = $"{dayInfo}   ·   {s.SeasonIcon} {s.SeasonName.ToUpperInvariant()}{holidayShort}";
                MktFocusTopBadgeTxt.Foreground = BrushFromHex("#9CA3AF");
            }

            if (MktFocusHolidayBadgeTxt != null)
            {
                if (!string.IsNullOrEmpty(s.PrimaryHolidayCutoff))
                {
                    MktFocusHolidayBadgeTxt.Text = s.IsHolidayActiveNow
                        ? $"🔥 Aktiv · Versand bis {s.PrimaryHolidayCutoff}"
                        : $"🕯️ Versand bis {s.PrimaryHolidayCutoff}";
                }
                else
                {
                    MktFocusHolidayBadgeTxt.Text = $"{s.SeasonIcon} {s.SeasonName}";
                }
                MktFocusHolidayBadgeTxt.Foreground = BrushFromHex("#34D399");
            }

            if (MktFocusHolidayBadgeBorder != null)
            {
                MktFocusHolidayBadgeBorder.Background = BrushFromHex("#132226");
                MktFocusHolidayBadgeBorder.BorderBrush = BrushFromHex("#104E3B");
            }

            if (MktFocusSeasonMottoBtn != null)
            {
                MktFocusSeasonMottoBtn.Background = BrushFromHex("#132226");
                MktFocusSeasonMottoBtn.Foreground = BrushFromHex("#34D399");
                MktFocusSeasonMottoBtn.BorderBrush = BrushFromHex("#104E3B");
            }

            if (MktHeroSeasonBadgeBorder != null)
            {
                MktHeroSeasonBadgeBorder.Background = BrushFromHex("#132226");
                MktHeroSeasonBadgeBorder.BorderBrush = BrushFromHex("#104E3B");
            }

            if (MktHeroSeasonBadgeTxt != null)
            {
                MktHeroSeasonBadgeTxt.Foreground = BrushFromHex("#34D399");
            }

            if (MktApplySeasonMottoBtn != null)
            {
                MktApplySeasonMottoBtn.Background = BrushFromHex("#132226");
                MktApplySeasonMottoBtn.Foreground = BrushFromHex("#34D399");
                MktApplySeasonMottoBtn.BorderBrush = BrushFromHex("#104E3B");
            }

            if (MktAdsSeasonBannerTxt != null)
            {
                string holidayInfo = s.IsHolidayActiveNow
                    ? $"🔥 AKTIVER PEAK: {s.PrimaryHolidayName} ({s.PrimaryHolidayWindow} · Annahmeschluss/Ad-Stopp: {s.PrimaryHolidayCutoff}) -> 15–20 €/Tag"
                    : $"Nächster Peak: {s.PrimaryHolidayName} ({s.PrimaryHolidayWindow} · Annahmeschluss: {s.PrimaryHolidayCutoff})";
                MktAdsSeasonBannerTxt.Text = $"Regulär: 8,50 €/Tag (5 € Social + 3,50 € Google = ~180–240 €/Monat)   ·   {holidayInfo}";
            }

            if (MktAdsDynamicProductsTxt != null)
            {
                var sb = new StringBuilder();
                var zugpferde = prods.Where(p => p.IsAdZugpferd && p.Type == "physical").OrderByDescending(p => p.BasePriceCents).ToList();
                var organisch = prods.Where(p => !p.IsAdZugpferd && p.Type == "physical").OrderByDescending(p => p.BasePriceCents).ToList();
                var gutschein = prods.FirstOrDefault(p => p.Slug == "geschenkgutschein" || p.Type == "digital");

                int idx = 1;
                foreach (var z in zugpferde)
                {
                    double vk = z.BasePriceCents / 100.0;
                    double ek = z.DirectCostsCents / 100.0;
                    double targetCpa = z.TargetCpaCents / 100.0;
                    double maxCpa = z.MaxCpaCents / 100.0;
                    double profitAtTarget = z.NetProfitAtTargetCpaCents / 100.0;
                    string focusTag = z.IsHolidayFocus ? " ⭐ [Saison-Fokus]" : "";

                    sb.AppendLine($"✓ Zugpferd #{idx} – {z.Name} ({vk:0.00} € VK | ~{ek:0.00} € Direktkosten){focusTag}:");
                    sb.AppendLine($"   · Ziel-CPA: {targetCpa:0.00} €   ·   Stopp-Grenze: max. {maxCpa:0.00} € CPA (bleiben ~{profitAtTarget:0.00} € Reingewinn!)");
                    idx++;
                }

                if (organisch.Count > 0)
                {
                    string names = string.Join(", ", organisch.Select(o => $"{o.Name} ({o.BasePriceCents / 100.0:0.00} €)"));
                    sb.AppendLine($"✓ Organische Reichweiten-Magneten ({names}):");
                    sb.AppendLine("   · 0,00 € Einzel-Ads! Werden organisch gepostet & ziehen Kunden in den Shop (Max. Retargeting-CPA: 6,00 €).");
                }

                if (gutschein != null)
                {
                    sb.AppendLine($"✓ Last-Minute-Retter – {gutschein.Name} ({gutschein.LandingSlug}):");
                    sb.AppendLine($"   · Ab Manufaktur-Annahmeschluss ({s.PrimaryHolidayCutoff}) bis zum Festtag mit 5 €/Tag bewerben (Sofort-PDF & Goldkarte).");
                }

                MktAdsDynamicProductsTxt.Text = sb.ToString().TrimEnd();
            }

            if (MktViralTopSeasonBadgeTxt != null)
            {
                string holidayInfo = !string.IsNullOrEmpty(s.PrimaryHolidayName)
                    ? $"{s.PrimaryHolidayBadge} (Annahmeschluss: {s.PrimaryHolidayCutoff})"
                    : s.SeasonName;
                MktViralTopSeasonBadgeTxt.Text = $"{s.SeasonIcon} {s.SeasonName} · {holidayInfo} · {prods.Count} Shop-Produkte aktiv";
            }

            RenderFocusViralDropdown();
            if (_activeMarketingSubTab == "viral") RenderMarketingViralPane();
        }

        private void MktApplySeasonMotto_Click(object sender, RoutedEventArgs e)
        {
            if (_mktVault.DailyRecipes.Count == 0) return;
            PlaySoundActionPop();

            var s = _mktVault.SeasonContext ?? ComputeLocalSeasonAndHolidayContext(DateTime.Today);
            var r = _mktVault.DailyRecipes[_currentDailyRecipeIndex];

            if (!string.IsNullOrWhiteSpace(s.EffectiveHookPrefix))
            {
                r.OnScreenHookText = s.EffectiveHookPrefix;
            }

            if (!string.IsNullOrWhiteSpace(s.EffectiveCaptionAddon) && !r.ReadyCaption.Contains(s.EffectiveCaptionAddon))
            {
                r.ReadyCaption = $"{s.EffectiveCaptionAddon}\n\n{r.ReadyCaption}";
            }

            if (!string.IsNullOrWhiteSpace(s.EffectiveHashtags) && !r.ReadyCaption.Contains(s.EffectiveHashtags))
            {
                r.ReadyCaption = $"{r.ReadyCaption.TrimEnd()} {s.EffectiveHashtags}";
            }

            _selectedPlanRecipe = r;
            SaveMarketingVaultOffline();
            RenderDailyHeroBanner();
            RenderFocusViralDropdown();
            if (_activeMarketingSubTab == "plan") RenderMarketingPlanPane();
            else if (_activeMarketingSubTab == "viral") RenderMarketingViralPane();

            if (sender is Button btn)
            {
                string orig = btn.Content?.ToString() ?? "🎄 Saison-Motto";
                btn.Content = "✓ Saison-Motto aktiv!";
                _ = Task.Delay(1500).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
            }
        }

        private void MktSetNormalMode_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            ApplyMarketingModeUI(false, save: true);
        }

        private void MktSetFocusMode_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            ApplyMarketingModeUI(true, save: true);
        }

        private void ApplyMarketingModeUI(bool focusMode, bool save = true)
        {
            _mktVault.IsFocusMode = focusMode;
            if (MktNormalModeGrid != null)
                MktNormalModeGrid.Visibility = focusMode ? Visibility.Collapsed : Visibility.Visible;
            if (MktFocusModeGrid != null)
                MktFocusModeGrid.Visibility = focusMode ? Visibility.Visible : Visibility.Collapsed;

            if (MktModeNormalBtn != null)
            {
                MktModeNormalBtn.Background = !focusMode ? (_isDarkMode ? BrushFromHex("#000000") : BrushFromHex("#C5A059")) : Brushes.Transparent;
                MktModeNormalBtn.Foreground = !focusMode ? (_isDarkMode ? BrushFromHex("#C5A059") : BrushFromHex("#111827")) : BrushFromHex("#D1D5DB");
                MktModeNormalBtn.BorderBrush = !focusMode ? BrushFromHex("#C5A059") : Brushes.Transparent;
                MktModeNormalBtn.BorderThickness = new Thickness(!focusMode ? 1 : 0);
            }
            if (MktModeFocusBtn != null)
            {
                MktModeFocusBtn.Background = focusMode ? (_isDarkMode ? BrushFromHex("#000000") : BrushFromHex("#C5A059")) : Brushes.Transparent;
                MktModeFocusBtn.Foreground = focusMode ? (_isDarkMode ? BrushFromHex("#C5A059") : BrushFromHex("#111827")) : BrushFromHex("#D1D5DB");
                MktModeFocusBtn.BorderBrush = focusMode ? BrushFromHex("#C5A059") : Brushes.Transparent;
                MktModeFocusBtn.BorderThickness = new Thickness(focusMode ? 1 : 0);
            }

            UpdateMiniPlayerVisibility();

            if (save)
            {
                SaveMarketingVaultOffline();
            }
        }

        private void LoadOrSeedMarketingVault()
        {
            if (File.Exists(_marketingVaultPath))
            {
                try
                {
                    string json = File.ReadAllText(_marketingVaultPath, Encoding.UTF8);
                    var loaded = JsonSerializer.Deserialize<MarketingVaultStorageDto>(json);
                    if (loaded != null && loaded.TopAccounts.Count >= 20 && loaded.QuestionsCatalog.Count >= 37 && loaded.DailyRecipes.Count >= 14)
                    {
                        _mktVault = loaded;
                        if (_mktVault.CurrentRound < 1) _mktVault.CurrentRound = 1;
                        if (_mktVault.ShopProducts == null || _mktVault.ShopProducts.Count == 0)
                        {
                            _mktVault.ShopProducts = BuildDefaultShopProducts();
                        }
                        foreach (var r in _mktVault.DailyRecipes)
                        {
                            r.IsCompletedToday = _mktVault.CompletedRecipeDays.Contains(r.DayNumber);
                            if (string.IsNullOrEmpty(r.BaseFormatTitle))
                            {
                                int dashIdx = r.Title.IndexOf("–");
                                r.BaseFormatTitle = dashIdx >= 0 ? r.Title.Substring(dashIdx + 1).Trim() : r.Title;
                            }
                        }
                        var q5 = _mktVault.QuestionsCatalog.FirstOrDefault(q => q.Number == 5);
                        if (q5 != null)
                        {
                            q5.MarketFinding = "TikTok hat ein striktes 80-Zeichen-Limit (3 ultrakurze Zeilen); Instagram erlaubt 150 Zeichen (inkl. 4. CTA-Zeile).";
                            q5.SeelenfunkeRule = "TikTok (66/80 Zeichen): '✨ Unikate / 🤍 Glas, Schiefer & Metall / 🧤 Handveredelt aus Deutschland' (+ 4. CTA-Zeile auf Instagram).";
                        }
                        return;
                    }
                }
                catch { }
            }

            _mktVault = BuildDefaultMarketingVault();
            SaveMarketingVaultOffline();
        }

        private void SaveMarketingVaultOffline()
        {
            try
            {
                if (string.IsNullOrEmpty(_marketingVaultPath)) return;
                _mktVault.LastUpdated = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
                _mktVault.ActiveDailyRecipeIndex = _currentDailyRecipeIndex;
                _mktVault.ActiveViralIdeaIndex = _currentViralIdeaIndex;
                string json = JsonSerializer.Serialize(_mktVault, new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(_marketingVaultPath, json, Encoding.UTF8);
            }
            catch { }
        }

        private void ApplyProductVariantToRecipe(DailyShootRecipeDto r, int nextIndex)
        {
            var prods = (_mktVault.ShopProducts != null && _mktVault.ShopProducts.Count > 0)
                ? _mktVault.ShopProducts
                : BuildDefaultShopProducts();
            if (prods.Count == 0) return;

            int idx = ((nextIndex % prods.Count) + prods.Count) % prods.Count;
            r.ProductVariantIndex = idx;
            var prod = prods[idx];

            if (string.IsNullOrEmpty(r.BaseFormatTitle))
            {
                int dashIdx = r.Title.IndexOf("–");
                r.BaseFormatTitle = dashIdx >= 0 ? r.Title.Substring(dashIdx + 1).Trim() : r.CategoryBadge;
            }

            string seasonAddon = _mktVault.SeasonContext?.EffectiveCaptionAddon ?? "";
            string seasonTags = _mktVault.SeasonContext?.EffectiveHashtags ?? "";

            r.ProductName = !string.IsNullOrWhiteSpace(prod.FullDisplayName) ? prod.FullDisplayName : prod.Name;
            r.LandingPageSlug = !string.IsNullOrWhiteSpace(prod.LandingSlug) ? prod.LandingSlug : $"/produkt/{prod.Slug}";
            r.Title = $"{prod.Name} – {r.BaseFormatTitle}";
            r.Step1Setup = !string.IsNullOrWhiteSpace(prod.SetupItems) ? prod.SetupItems : $"Weiße Handschuhe · {prod.Name} · xTool F2 Ultra UV";
            r.ReadyCaption = $"Jedes Unikat ({prod.Name}) wird in unserer Manufaktur in Gifhorn mit weißen Handschuhen und höchster Präzision für dich gefertigt. ✨🤍\n\n{(string.IsNullOrWhiteSpace(seasonAddon) ? "" : seasonAddon + "\n\n")}👉 Jetzt live im 3D-Konfigurator testen (Link in Bio: {r.LandingPageSlug})!\n\n{prod.Hashtags} {seasonTags}".Trim();
        }

        private void MktRotateProductVariant_Click(object sender, RoutedEventArgs e)
        {
            if (_mktVault.DailyRecipes.Count == 0) return;
            PlaySoundActionPop();
            var r = _mktVault.DailyRecipes[_currentDailyRecipeIndex];
            ApplyProductVariantToRecipe(r, r.ProductVariantIndex + 1);
            _selectedPlanRecipe = r;
            SaveMarketingVaultOffline();
            RenderDailyHeroBanner();
            if (_activeMarketingSubTab == "plan") RenderMarketingPlanPane();
        }

        private void MktRotateSelectedPlanProduct_Click(object sender, RoutedEventArgs e)
        {
            var r = _selectedPlanRecipe ?? _mktVault.DailyRecipes.FirstOrDefault();
            if (r == null) return;
            PlaySoundActionPop();
            ApplyProductVariantToRecipe(r, r.ProductVariantIndex + 1);
            SaveMarketingVaultOffline();
            RenderDailyHeroBanner();
            RenderMarketingPlanPane();
        }

        // =========================================================================
        // TOP HERO BAR: "HEUTE DREHEN (TAGESEMPFEHLUNG)"
        // =========================================================================
        private void RenderDailyHeroBanner()
        {
            if (_mktVault.DailyRecipes.Count == 0 || MktHeroTitleTxt == null) return;
            if (_currentDailyRecipeIndex < 0 || _currentDailyRecipeIndex >= _mktVault.DailyRecipes.Count)
                _currentDailyRecipeIndex = 0;

            var r = _mktVault.DailyRecipes[_currentDailyRecipeIndex];
            r.IsCompletedToday = _mktVault.CompletedRecipeDays.Contains(r.DayNumber);
            int totalDays = _mktVault.DailyRecipes.Count;
            var recTrack = GetRecommendedTrackForDailyRecipe(r);

            MktHeroDayBadgeTxt.Text = $"RUNDE {_mktVault.CurrentRound} · TAG {r.DayNumber:00}/{totalDays:00} · {r.WeekdayLabel.ToUpperInvariant()}";
            MktHeroCategoryBadgeTxt.Text = $"{r.CategoryBadge} · {r.DurationLabel}";
            MktHeroTitleTxt.Text = r.Title;
            MktHeroHookPreviewTxt.Text = $"Hook im Bild: „{r.OnScreenHookText}“  ·  🎵 Soundempfehlung: {recTrack.Title} ({recTrack.Bpm} BPM · {recTrack.MusicalKey})";

            MktHeroStep1Txt.Text = r.Step1Setup;
            MktHeroStep2Txt.Text = r.Step2Hook;
            MktHeroStep3Txt.Text = r.Step3Clips;
            MktHeroStep4Txt.Text = r.Step4Edit;

            if (MktHeroDoneBtn != null)
            {
                MktHeroDoneBtn.Content = r.IsCompletedToday ? "✓ Heute gedreht" : "○ Als gedreht markieren";
                MktHeroDoneBtn.Background = BrushFromHex(r.IsCompletedToday ? "#064E3B" : "#1F2430");
                MktHeroDoneBtn.Foreground = BrushFromHex(r.IsCompletedToday ? "#34D399" : "#D1D5DB");
            }

            // Update Focus Mode Card ("DAS MUSST DU HEUTE MACHEN:")
            if (MktFocusCategoryBadgeTxt != null)
            {
                MktFocusCategoryBadgeTxt.Text = $"🎬 {r.DurationLabel} · {r.CategoryBadge}";
                MktFocusCategoryBadgeTxt.Foreground = BrushFromHex("#D4AF37");
            }
            if (MktFocusTitleTxt != null)
            {
                string cleanTitle = !string.IsNullOrWhiteSpace(r.ProductName) ? r.ProductName : r.Title;
                int dashIdx = cleanTitle.IndexOf('–');
                if (dashIdx > 0) cleanTitle = cleanTitle.Substring(0, dashIdx).Trim();
                MktFocusTitleTxt.Text = cleanTitle;
                MktFocusTitleTxt.Foreground = BrushFromHex("#F9FAFB");
            }
            if (MktFocusHookTxt != null)
            {
                MktFocusHookTxt.Text = $"„{r.OnScreenHookText}“";
                MktFocusHookTxt.Foreground = BrushFromHex("#9CA3AF");
            }
            if (MktFocusStep1Txt != null)
                MktFocusStep1Txt.Text = r.Step1Setup;
            if (MktFocusStep2Txt != null)
                MktFocusStep2Txt.Text = r.Step3Clips;
            if (MktFocusStep3Txt != null)
                MktFocusStep3Txt.Text = $"{r.CapCutTemplate} · 18:30–20:00 Uhr posten ({r.LandingPageSlug})";
            if (MktFocusSoundRecTitleTxt != null)
            {
                MktFocusSoundRecTitleTxt.Text = $"🎵 Soundempfehlung für heute: {recTrack.Title}";
                MktFocusSoundRecTitleTxt.Foreground = BrushFromHex("#D4AF37");
            }
            if (MktFocusSoundRecMetaTxt != null)
            {
                MktFocusSoundRecMetaTxt.Text = $"{recTrack.Bpm} BPM · {recTrack.MusicalKey} · {recTrack.DurationFormatted} · {recTrack.DropTimingLabel}";
                MktFocusSoundRecMetaTxt.Foreground = BrushFromHex("#9CA3AF");
            }
            if (MktFocusSoundRecBorder != null)
            {
                MktFocusSoundRecBorder.Background = BrushFromHex("#0B0D12");
                MktFocusSoundRecBorder.BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059"));
            }
            if (MktFocusDoneBtn != null)
            {
                MktFocusDoneBtn.Content = r.IsCompletedToday ? "✓ Heute erledigt" : "✓ Heute als erledigt markieren";
                MktFocusDoneBtn.Background = r.IsCompletedToday
                    ? BrushFromHex("#064E3B")
                    : (_isDarkMode ? BrushFromHex("#000000") : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059")));
                MktFocusDoneBtn.Foreground = r.IsCompletedToday
                    ? BrushFromHex("#34D399")
                    : (_isDarkMode ? BrushFromHex("#C5A059") : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#111827")));
                MktFocusDoneBtn.BorderBrush = BrushFromHex("#C5A059");
                MktFocusDoneBtn.BorderThickness = new Thickness(1);
            }
            if (MktFocusCardBorder != null)
            {
                MktFocusCardBorder.Background = BrushFromHex("#141720");
                MktFocusCardBorder.BorderBrush = BrushFromHex(r.IsCompletedToday ? "#10B981" : "#232734");
            }
            UpdateMarketingMusicPreviewButtonsUI();

            if (MktOfflineStatusTxt != null)
            {
                int doneCount = _mktVault.CompletedRecipeDays.Count;
                int dailyDone = _marketingRoutineMatrix.Count(x => x.Cycle == "daily" && _mktVault.CompletedRoutineIds.Contains(x.Id));
                int prodCount = _mktVault.ShopProducts?.Count ?? 7;
                MktOfflineStatusTxt.Text = $"🛍️ {prodCount} Produkte · 🎵 12 Songs · ☀️ Routine {dailyDone}/4 · 🔄 Runde {_mktVault.CurrentRound} ({doneCount}/{totalDays} Drehs)";
                if (MktTabRoutineBtn != null)
                    MktTabRoutineBtn.Content = $"⚡ Routine ({dailyDone}/4)";
            }

            RenderDynamicSeasonAndAdsUI();
        }

        private void MktPrevDailyRecipe_Click(object sender, RoutedEventArgs e)
        {
            if (_mktVault.DailyRecipes.Count == 0) return;
            PlaySoundNav();
            _currentDailyRecipeIndex = (_currentDailyRecipeIndex - 1 + _mktVault.DailyRecipes.Count) % _mktVault.DailyRecipes.Count;
            _selectedPlanRecipe = _mktVault.DailyRecipes[_currentDailyRecipeIndex];
            SaveMarketingVaultOffline();
            RenderDailyHeroBanner();
            if (_activeMarketingSubTab == "plan") RenderMarketingPlanPane();
        }

        private void MktNextDailyRecipe_Click(object sender, RoutedEventArgs e)
        {
            if (_mktVault.DailyRecipes.Count == 0) return;
            PlaySoundNav();
            _currentDailyRecipeIndex = (_currentDailyRecipeIndex + 1) % _mktVault.DailyRecipes.Count;
            _selectedPlanRecipe = _mktVault.DailyRecipes[_currentDailyRecipeIndex];
            SaveMarketingVaultOffline();
            RenderDailyHeroBanner();
            if (_activeMarketingSubTab == "plan") RenderMarketingPlanPane();
        }

        private void MktCopyDailyCaption_Click(object sender, RoutedEventArgs e)
        {
            if (_mktVault.DailyRecipes.Count == 0) return;
            var r = _mktVault.DailyRecipes[_currentDailyRecipeIndex];
            Clipboard.SetText(r.ReadyCaption);
            PlaySoundActionPop();
            if (sender is Button btn)
            {
                string orig = btn.Content?.ToString() ?? "📋 Caption kopieren";
                btn.Content = "✓ Kopiert!";
                Task.Delay(1400).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
            }
        }

        private void MktOpenCameraStudio_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            SwitchSpaceUI("camera");
            SaveSettingsToFile();
        }

        private async void MktAddDailyToTodo_Click(object sender, RoutedEventArgs e)
        {
            if (_mktVault.DailyRecipes.Count == 0) return;
            var r = _mktVault.DailyRecipes[_currentDailyRecipeIndex];
            var recTrack = GetRecommendedTrackForDailyRecipe(r);
            string taskTitle = $"🎬 Video drehen ({r.DurationLabel}): {r.Title} [{r.CapCutTemplate} · Song: {recTrack.Title}]";

            PlaySoundTaskDone();
            if (sender is Button btn)
            {
                btn.Content = "✓ Im To-Do!";
                _ = Task.Delay(1500).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = "➕ In To-Do"));
            }

            try
            {
                var payload = JsonSerializer.Serialize(new
                {
                    title = taskTitle,
                    priority = "hoch",
                    relevant_from = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")
                });
                await _http.PostAsync($"{_apiBaseUrl}/funki/tasks/lists/my_day/tasks", new StringContent(payload, Encoding.UTF8, "application/json"));
                await LoadTodoListsAsync();
            }
            catch
            {
                // Fallback local task entry if API is offline
                _allTasks.Insert(0, new TaskItemDto
                {
                    Id = Guid.NewGuid().ToString("N"),
                    Title = taskTitle,
                    Priority = "hoch",
                    IsCompleted = false
                });
                UpdateSpacesBadges();
            }
        }

        private void MktToggleDailyDone_Click(object sender, RoutedEventArgs e)
        {
            if (_mktVault.DailyRecipes.Count == 0) return;
            var r = _mktVault.DailyRecipes[_currentDailyRecipeIndex];
            if (_mktVault.CompletedRecipeDays.Contains(r.DayNumber))
            {
                _mktVault.CompletedRecipeDays.Remove(r.DayNumber);
                r.IsCompletedToday = false;
                if (_mktVault.TotalVideosCompleted > 0) _mktVault.TotalVideosCompleted--;
                PlaySoundActionPop();
            }
            else
            {
                _mktVault.CompletedRecipeDays.Add(r.DayNumber);
                r.IsCompletedToday = true;
                _mktVault.TotalVideosCompleted++;
                if (!_mktVault.CompletedRoutineIds.Contains("d1"))
                    _mktVault.CompletedRoutineIds.Add("d1");
                PlaySoundTaskDone();

                // Endlos-Rotation: Sobald alle 14 Tage einer Runde erledigt sind, startet automatisch die nächste Runde mit neuen Produkt-Kombinationen!
                if (_mktVault.CompletedRecipeDays.Count >= _mktVault.DailyRecipes.Count)
                {
                    _mktVault.CurrentRound++;
                    _mktVault.CompletedRecipeDays.Clear();
                    foreach (var recipe in _mktVault.DailyRecipes)
                    {
                        recipe.IsCompletedToday = false;
                        ApplyProductVariantToRecipe(recipe, recipe.ProductVariantIndex + 1);
                    }
                    _currentDailyRecipeIndex = 0;
                    _selectedPlanRecipe = _mktVault.DailyRecipes[0];
                }
            }
            SaveMarketingVaultOffline();
            RenderDailyHeroBanner();
            if (_activeMarketingSubTab == "plan") RenderMarketingPlanPane();
        }

        // =========================================================================
        // SUB-NAVIGATION TABS: PLAN | ACCOUNTS | RÖNTGEN | CAPCUT & BIO | ADS | VIRAL | MUSIK
        // =========================================================================
        private void MktSubTab_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string tab)
            {
                PlaySoundNav();
                SwitchMarketingSubTabUI(tab);
            }
        }

        private void SwitchMarketingSubTabUI(string tab)
        {
            _activeMarketingSubTab = tab;

            MktPanePlan.Visibility = tab == "plan" ? Visibility.Visible : Visibility.Collapsed;
            if (MktPaneRoutine != null)
                MktPaneRoutine.Visibility = tab == "routine" ? Visibility.Visible : Visibility.Collapsed;
            MktPaneAccounts.Visibility = tab == "accounts" ? Visibility.Visible : Visibility.Collapsed;
            MktPaneQuestions.Visibility = tab == "questions" ? Visibility.Visible : Visibility.Collapsed;
            MktPaneTemplates.Visibility = tab == "templates" ? Visibility.Visible : Visibility.Collapsed;
            MktPaneAds.Visibility = tab == "ads" ? Visibility.Visible : Visibility.Collapsed;
            if (MktPaneViral != null)
                MktPaneViral.Visibility = tab == "viral" ? Visibility.Visible : Visibility.Collapsed;
            if (MktPaneMusic != null)
                MktPaneMusic.Visibility = tab == "music" ? Visibility.Visible : Visibility.Collapsed;

            StyleSubTabButton(MktTabPlanBtn, tab == "plan");
            StyleSubTabButton(MktTabRoutineBtn, tab == "routine");
            StyleSubTabButton(MktTabAccountsBtn, tab == "accounts");
            StyleSubTabButton(MktTabQuestionsBtn, tab == "questions");
            StyleSubTabButton(MktTabTemplatesBtn, tab == "templates");
            StyleSubTabButton(MktTabAdsBtn, tab == "ads");
            StyleSubTabButton(MktTabViralBtn, tab == "viral");
            StyleSubTabButton(MktTabMusicBtn, tab == "music");

            if (tab == "plan") RenderMarketingPlanPane();
            else if (tab == "routine") RenderMarketingRoutinePane();
            else if (tab == "accounts") RenderMarketingAccountsPane();
            else if (tab == "questions") RenderMarketingQuestionsPane();
            else if (tab == "viral") RenderMarketingViralPane();
            else if (tab == "music") RenderMarketingMusicPane();
        }

        // =========================================================================
        // PANE 6: 4x3 MARKETING-ROUTINE (1x TAG · 1x WOCHE · 1x MONAT)
        // =========================================================================
        private void CheckAndResetRoutineCycles()
        {
            string todayKey = DateTime.Today.ToString("yyyy-MM-dd");
            string weekKey = $"{System.Globalization.ISOWeek.GetYear(DateTime.Today)}-W{System.Globalization.ISOWeek.GetWeekOfYear(DateTime.Today):00}";
            string monthKey = DateTime.Today.ToString("yyyy-MM");

            bool changed = false;
            if (_mktVault.LastDailyRoutineReset != todayKey)
            {
                _mktVault.LastDailyRoutineReset = todayKey;
                _mktVault.CompletedRoutineIds.RemoveAll(id => id.StartsWith("d"));
                changed = true;
            }
            if (_mktVault.LastWeeklyRoutineReset != weekKey)
            {
                _mktVault.LastWeeklyRoutineReset = weekKey;
                _mktVault.CompletedRoutineIds.RemoveAll(id => id.StartsWith("w"));
                changed = true;
            }
            if (_mktVault.LastMonthlyRoutineReset != monthKey)
            {
                _mktVault.LastMonthlyRoutineReset = monthKey;
                _mktVault.CompletedRoutineIds.RemoveAll(id => id.StartsWith("m"));
                changed = true;
            }
            if (changed) SaveMarketingVaultOffline();
        }

        private void RenderMarketingRoutinePane()
        {
            if (MktRoutineDailyListPanel == null || MktRoutineWeeklyListPanel == null || MktRoutineMonthlyListPanel == null)
                return;

            CheckAndResetRoutineCycles();

            MktRoutineDailyListPanel.Children.Clear();
            MktRoutineWeeklyListPanel.Children.Clear();
            MktRoutineMonthlyListPanel.Children.Clear();

            int dailyDone = _marketingRoutineMatrix.Count(x => x.Cycle == "daily" && _mktVault.CompletedRoutineIds.Contains(x.Id));
            int weeklyDone = _marketingRoutineMatrix.Count(x => x.Cycle == "weekly" && _mktVault.CompletedRoutineIds.Contains(x.Id));
            int monthlyDone = _marketingRoutineMatrix.Count(x => x.Cycle == "monthly" && _mktVault.CompletedRoutineIds.Contains(x.Id));

            if (MktRoutineDailyBadgeTxt != null) MktRoutineDailyBadgeTxt.Text = $"{dailyDone}/4";
            if (MktRoutineWeeklyBadgeTxt != null) MktRoutineWeeklyBadgeTxt.Text = $"{weeklyDone}/4";
            if (MktRoutineMonthlyBadgeTxt != null) MktRoutineMonthlyBadgeTxt.Text = $"{monthlyDone}/4";

            if (MktRoutineHeaderSummaryTxt != null)
            {
                int kw = System.Globalization.ISOWeek.GetWeekOfYear(DateTime.Today);
                MktRoutineHeaderSummaryTxt.Text = $"☀️ Heute: {dailyDone}/4   ·   📅 KW {kw:00}: {weeklyDone}/4   ·   🏆 {DateTime.Today:MMMM}: {monthlyDone}/4";
            }

            foreach (var item in _marketingRoutineMatrix)
            {
                bool isDone = _mktVault.CompletedRoutineIds.Contains(item.Id);

                var card = new Border
                {
                    Background = BrushFromHex(isDone ? "#06281E" : "#141720"),
                    BorderBrush = isDone
                        ? BrushFromHex("#34D399")
                        : BrushFromHex("#232734"),
                    BorderThickness = new Thickness(isDone ? 1.5 : 1),
                    CornerRadius = new CornerRadius(9),
                    Padding = new Thickness(12, 10, 12, 10),
                    Margin = new Thickness(0, 0, 0, 8),
                    Cursor = Cursors.Hand,
                    ToolTip = item.Description
                };

                var rowGrid = new Grid();
                rowGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                rowGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var leftSp = new StackPanel { VerticalAlignment = VerticalAlignment.Center, Margin = new Thickness(0, 0, 8, 0) };
                leftSp.Children.Add(new TextBlock
                {
                    Text = isDone ? $"✓ {item.Title}" : $"○ {item.Title}",
                    Foreground = BrushFromHex("#F9FAFB"),
                    FontSize = 12.5,
                    FontWeight = FontWeights.Bold,
                    TextWrapping = TextWrapping.Wrap
                });
                rowGrid.Children.Add(leftSp);

                var statusPill = new Border
                {
                    Background = BrushFromHex(isDone ? "#064E3B" : "#1F2430"),
                    CornerRadius = new CornerRadius(6),
                    Padding = new Thickness(8, 3, 8, 3),
                    VerticalAlignment = VerticalAlignment.Center,
                    Child = new TextBlock
                    {
                        Text = isDone ? "✓" : item.TimeLabel,
                        Foreground = BrushFromHex(isDone ? "#34D399" : "#D4AF37"),
                        FontSize = 10.5,
                        FontWeight = FontWeights.Bold
                    }
                };
                Grid.SetColumn(statusPill, 1);
                rowGrid.Children.Add(statusPill);

                card.Child = rowGrid;
                string capturedId = item.Id;
                card.MouseLeftButtonUp += (s, ev) =>
                {
                    if (_mktVault.CompletedRoutineIds.Contains(capturedId))
                    {
                        _mktVault.CompletedRoutineIds.Remove(capturedId);
                        PlaySoundActionPop();
                    }
                    else
                    {
                        _mktVault.CompletedRoutineIds.Add(capturedId);
                        PlaySoundTaskDone();
                    }
                    SaveMarketingVaultOffline();
                    RenderDailyHeroBanner();
                    RenderMarketingRoutinePane();
                };

                if (item.Cycle == "daily") MktRoutineDailyListPanel.Children.Add(card);
                else if (item.Cycle == "weekly") MktRoutineWeeklyListPanel.Children.Add(card);
                else MktRoutineMonthlyListPanel.Children.Add(card);
            }
        }

        private async void MktAddDailyRoutineToTodo_Click(object sender, RoutedEventArgs e)
        {
            var openDaily = _marketingRoutineMatrix.Where(x => x.Cycle == "daily" && !_mktVault.CompletedRoutineIds.Contains(x.Id)).ToList();
            if (openDaily.Count == 0)
            {
                PlaySoundActionPop();
                return;
            }

            PlaySoundTaskDone();
            if (sender is Button btn)
            {
                string orig = btn.Content?.ToString() ?? "➕ Offene Tages-Routine in To-Do";
                btn.Content = $"✓ {openDaily.Count} Aufgaben im To-Do!";
                _ = Task.Delay(1600).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
            }

            foreach (var item in openDaily)
            {
                string taskTitle = $"⚡ Marketing-Routine ({item.TimeLabel}): {item.Title}";
                try
                {
                    var payload = JsonSerializer.Serialize(new
                    {
                        title = taskTitle,
                        priority = "normal",
                        relevant_from = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")
                    });
                    await _http.PostAsync($"{_apiBaseUrl}/funki/tasks/lists/my_day/tasks", new StringContent(payload, Encoding.UTF8, "application/json"));
                }
                catch
                {
                    _allTasks.Insert(0, new TaskItemDto
                    {
                        Id = Guid.NewGuid().ToString("N"),
                        Title = taskTitle,
                        Priority = "normal",
                        IsCompleted = false
                    });
                }
            }

            try { await LoadTodoListsAsync(); } catch { UpdateSpacesBadges(); }
        }

        private void MktResetCurrentRoutine_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _mktVault.CompletedRoutineIds.Clear();
            SaveMarketingVaultOffline();
            RenderDailyHeroBanner();
            RenderMarketingRoutinePane();
        }

        private void StyleSubTabButton(Button? btn, bool active)
        {
            if (btn == null) return;
            btn.Background = active
                ? (_isDarkMode ? BrushFromHex("#000000") : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059")))
                : BrushFromHex("#181B24");
            btn.Foreground = active
                ? (_isDarkMode ? BrushFromHex("#C5A059") : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#111827")))
                : BrushFromHex("#D1D5DB");
            btn.BorderBrush = active ? BrushFromHex("#C5A059") : BrushFromHex("#232734");
            btn.BorderThickness = new Thickness(1);
            btn.FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold;
        }

        private void MktSearchBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            _marketingSearchQuery = (MktSearchBox.Text ?? "").Trim();
            if (MktSearchPlaceholder != null)
                MktSearchPlaceholder.Visibility = string.IsNullOrEmpty(_marketingSearchQuery) ? Visibility.Visible : Visibility.Collapsed;

            if (_activeMarketingSubTab == "plan") RenderMarketingPlanPane();
            else if (_activeMarketingSubTab == "accounts") RenderMarketingAccountsPane();
            else if (_activeMarketingSubTab == "questions") RenderMarketingQuestionsPane();
            else if (_activeMarketingSubTab == "viral") RenderMarketingViralPane();
            else if (_activeMarketingSubTab == "music") RenderMarketingMusicPane();
        }

        // =========================================================================
        // PANE 7: VIRAL GEHEN (260-VIDEO-AUSWERTUNG & 15 KURZE VIRAL-REZEPTE)
        // + FOKUS-MODUS DROPDOWN ("Deine Virale Idee für heute")
        // =========================================================================
        private void EnsureViralIdeasInitialized()
        {
            if (_mktVault.ViralIdeas == null || _mktVault.ViralIdeas.Count < 15)
            {
                _mktVault.ViralIdeas = BuildDefaultViralIdeas();
            }

            foreach (var idea in _mktVault.ViralIdeas)
            {
                if (string.IsNullOrEmpty(idea.BaseTitle))
                {
                    idea.BaseTitle = idea.Title;
                }
            }
        }

        private void MktViralFilter_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string filter)
            {
                PlaySoundNav();
                _activeViralCategoryFilter = filter;
                StyleViralFilterButtons();
                RenderMarketingViralPane();
            }
        }

        private void StyleViralFilterButtons()
        {
            StyleSubTabButton(MktViralFilterAllBtn, _activeViralCategoryFilter == "all");
            StyleSubTabButton(MktViralFilterLaunchBtn, _activeViralCategoryFilter == "launch");
            StyleSubTabButton(MktViralFilterAsmrBtn, _activeViralCategoryFilter == "asmr");
            StyleSubTabButton(MktViralFilterStoryBtn, _activeViralCategoryFilter == "story");
            StyleSubTabButton(MktViralFilterQuickBtn, _activeViralCategoryFilter == "quick");
        }

        private ShopMarketingProductDto GetProductForViralIdea(ViralConceptIdeaDto idea)
        {
            var prods = (_mktVault.ShopProducts != null && _mktVault.ShopProducts.Count > 0)
                ? _mktVault.ShopProducts
                : BuildDefaultShopProducts();
            if (prods.Count == 0) return new ShopMarketingProductDto { Name = "Das Goldene Seelen-Herz", LandingSlug = "/l/goldenes-seelen-herz" };

            int idx = ((idea.ProductVariantIndex % prods.Count) + prods.Count) % prods.Count;
            return prods[idx];
        }

        private void RenderMarketingViralPane()
        {
            EnsureViralIdeasInitialized();
            if (MktViralListPanel == null) return;
            MktViralListPanel.Children.Clear();

            var s = _mktVault.SeasonContext ?? ComputeLocalSeasonAndHolidayContext(DateTime.Today);
            if (MktViralTopSeasonBadgeTxt != null)
            {
                string holidayInfo = !string.IsNullOrEmpty(s.PrimaryHolidayName)
                    ? $"{s.PrimaryHolidayBadge} (Versand bis {s.PrimaryHolidayCutoff})"
                    : s.SeasonName;
                MktViralTopSeasonBadgeTxt.Text = $"{s.SeasonIcon} {s.SeasonName} · {holidayInfo}";
            }

            var ideas = _mktVault.ViralIdeas.AsEnumerable();
            if (_activeViralCategoryFilter != "all")
            {
                ideas = ideas.Where(x => x.CategoryKey == _activeViralCategoryFilter);
            }
            if (!string.IsNullOrEmpty(_marketingSearchQuery))
            {
                ideas = ideas.Where(x =>
                    x.Title.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase) ||
                    x.TrendingSongs.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase) ||
                    x.CategoryLabel.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase));
            }

            var filteredList = ideas.ToList();
            if (_selectedViralIdea == null || !filteredList.Any(x => x.Rank == _selectedViralIdea.Rank))
            {
                _selectedViralIdea = filteredList.FirstOrDefault() ?? _mktVault.ViralIdeas.FirstOrDefault();
            }

            int activeFocusRank = (_mktVault.ViralIdeas.Count > 0 && _currentViralIdeaIndex >= 0 && _currentViralIdeaIndex < _mktVault.ViralIdeas.Count)
                ? _mktVault.ViralIdeas[_currentViralIdeaIndex].Rank
                : 1;

            foreach (var idea in filteredList)
            {
                bool isSelected = _selectedViralIdea != null && _selectedViralIdea.Rank == idea.Rank;
                bool isTodayFocus = idea.Rank == activeFocusRank;
                var prod = GetProductForViralIdea(idea);

                var card = new Border
                {
                    Background = BrushFromHex(isSelected ? "#26231F" : "#141720"),
                    BorderBrush = isSelected
                        ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059"))
                        : BrushFromHex(isTodayFocus ? "#4D3D1E" : "#222634"),
                    BorderThickness = new Thickness(isSelected ? 1.5 : 1),
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(12, 9, 12, 9),
                    Margin = new Thickness(0, 0, 0, 7),
                    Cursor = Cursors.Hand
                };

                var sp = new StackPanel();
                var topGrid = new Grid();
                topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var leftBadges = new StackPanel { Orientation = Orientation.Horizontal };
                leftBadges.Children.Add(new TextBlock
                {
                    Text = $"#{idea.Rank:00} · {idea.CategoryLabel}",
                    Foreground = BrushFromHex("#D4AF37"),
                    FontSize = 10,
                    FontWeight = FontWeights.Bold
                });
                if (isTodayFocus)
                {
                    leftBadges.Children.Add(new TextBlock
                    {
                        Text = "  🔥 HEUTE IM FOKUS",
                        Foreground = BrushFromHex("#34D399"),
                        FontSize = 9.5,
                        FontWeight = FontWeights.Bold
                    });
                }
                topGrid.Children.Add(leftBadges);

                var durTxt = new TextBlock
                {
                    Text = $"{idea.DurationLabel} · {idea.ViewsBenchmark}",
                    Foreground = BrushFromHex("#9CA3AF"),
                    FontSize = 10,
                    FontWeight = FontWeights.Bold
                };
                Grid.SetColumn(durTxt, 1);
                topGrid.Children.Add(durTxt);
                sp.Children.Add(topGrid);

                sp.Children.Add(new TextBlock
                {
                    Text = idea.Title,
                    Foreground = BrushFromHex("#F9FAFB"),
                    FontSize = 12.5,
                    FontWeight = FontWeights.Bold,
                    Margin = new Thickness(0, 3, 0, 0),
                    TextWrapping = TextWrapping.Wrap
                });

                card.ToolTip = $"🎵 {idea.ShortSongBadge} · 🛍️ {prod.Name}";
                card.Child = sp;
                int capturedRank = idea.Rank;
                card.MouseLeftButtonUp += (sObj, ev) =>
                {
                    PlaySoundNav();
                    _selectedViralIdea = _mktVault.ViralIdeas.FirstOrDefault(x => x.Rank == capturedRank);
                    RenderMarketingViralPane();
                };

                MktViralListPanel.Children.Add(card);
            }

            RenderSelectedViralIdeaDetails();
        }

        private (string hook, string step1, string step2, string step3, string caption) BuildDynamicViralTexts(ViralConceptIdeaDto idea)
        {
            var s = _mktVault.SeasonContext ?? ComputeLocalSeasonAndHolidayContext(DateTime.Today);
            var prod = GetProductForViralIdea(idea);

            string seasonLabel = !string.IsNullOrEmpty(s.PrimaryHolidayName)
                ? $"{s.PrimaryHolidayBadge} ({s.SeasonName})"
                : $"{s.SeasonIcon} {s.SeasonName}";
            string cutoffNote = !string.IsNullOrEmpty(s.PrimaryHolidayCutoff)
                ? $"Bestellschluss für pünktlichen Versand: {s.PrimaryHolidayCutoff}."
                : "Versandfertig in 3–5 Werktagen aus Gifhorn.";
            string mottoSentence = !string.IsNullOrWhiteSpace(s.EffectiveCaptionAddon)
                ? s.EffectiveCaptionAddon
                : $"Passend für {seasonLabel} handveredelt in unserer Manufaktur in Gifhorn.";
            string extraTags = !string.IsNullOrWhiteSpace(s.EffectiveHashtags) ? $" {s.EffectiveHashtags}" : "";

            string hook = idea.OnScreenHook.Replace("{PRODUKT}", prod.Name).Replace("{SAISON}", s.SeasonName);
            string step1 = idea.Step1Setup
                .Replace("{PRODUKT}", prod.Name)
                .Replace("{SETUP_ITEMS}", !string.IsNullOrWhiteSpace(prod.SetupItems) ? prod.SetupItems : prod.Name)
                .Replace("{SONG}", idea.ShortSongBadge);
            string step2 = idea.Step2Shoot
                .Replace("{PRODUKT}", prod.Name)
                .Replace("{SLUG}", prod.LandingSlug)
                .Replace("{SAISON}", seasonLabel);
            string step3 = $"{idea.Step3EditAndPost.Replace("{PRODUKT}", prod.Name).Replace("{SLUG}", prod.LandingSlug)}\nSaison-Hinweis ({seasonLabel}): {cutoffNote}";
            string caption = $"{idea.ReadyCaptionAndTags.Replace("{PRODUKT}", prod.Name).Replace("{SLUG}", prod.LandingSlug)}\n\n{mottoSentence}\n\n{prod.Hashtags}{extraTags}".Trim();

            return (hook, step1, step2, step3, caption);
        }

        private void RenderSelectedViralIdeaDetails()
        {
            var idea = _selectedViralIdea ?? _mktVault.ViralIdeas.FirstOrDefault();
            if (idea == null || MktViralDetailTitleTxt == null) return;

            var prod = GetProductForViralIdea(idea);
            var dyn = BuildDynamicViralTexts(idea);
            var recTrack = GetRecommendedTrackForViralIdea(idea);

            MktViralDetailBadgeTxt.Text = $"🔥 VIRAL #{idea.Rank:00} · {idea.CategoryLabel.ToUpperInvariant()} · {idea.DurationLabel.ToUpperInvariant()}";
            MktViralDetailStatsTxt.Text = $"Ø {idea.ViewsBenchmark} Views · Aufwand: {idea.EffortLabel} · Produkt: {prod.Name}";
            MktViralDetailTitleTxt.Text = idea.Title;
            MktViralDetailHookTxt.Text = $"On-Screen-Hook (0–2,5s mittig im Bild): „{dyn.hook}“";
            MktViralDetailSongTxt.Text = idea.TrendingSongs;
            if (MktViralDetailOwnSoundRecTxt != null)
            {
                MktViralDetailOwnSoundRecTxt.Text = $"✨ Rechtsfreier Seelenfunke-Sound: {recTrack.Title}";
                MktViralDetailOwnSoundRecTxt.Foreground = BrushFromHex("#D4AF37");
            }
            if (MktViralDetailOwnSoundMetaTxt != null)
            {
                MktViralDetailOwnSoundMetaTxt.Text = $"{recTrack.Bpm} BPM · {recTrack.MusicalKey} · {recTrack.DurationFormatted} · {recTrack.DropTimingLabel}";
                MktViralDetailOwnSoundMetaTxt.Foreground = BrushFromHex("#9CA3AF");
            }
            MktViralDetailStructureTxt.Text = idea.StructureBreakdown;
            MktViralDetailWhyViralTxt.Text = idea.WhyItGoesViral;
            MktViralDetailStep1Txt.Text = dyn.step1;
            MktViralDetailStep2Txt.Text = dyn.step2;
            MktViralDetailStep3Txt.Text = dyn.step3;
            MktViralDetailCaptionTxt.Text = dyn.caption;
            UpdateMarketingMusicPreviewButtonsUI();
        }

        private void RenderFocusViralDropdown()
        {
            EnsureViralIdeasInitialized();
            if (_mktVault.ViralIdeas.Count == 0 || MktFocusViralTitleTxt == null) return;

            if (_currentViralIdeaIndex < 0 || _currentViralIdeaIndex >= _mktVault.ViralIdeas.Count)
                _currentViralIdeaIndex = 0;

            var idea = _mktVault.ViralIdeas[_currentViralIdeaIndex];
            var prod = GetProductForViralIdea(idea);
            var s = _mktVault.SeasonContext ?? ComputeLocalSeasonAndHolidayContext(DateTime.Today);
            var dyn = BuildDynamicViralTexts(idea);
            var recTrack = GetRecommendedTrackForViralIdea(idea);

            if (MktFocusViralHeaderPreviewTxt != null)
            {
                MktFocusViralHeaderPreviewTxt.Text = $"#{idea.Rank} {idea.Title} ({idea.DurationLabel} · 🎵 {recTrack.Title})";
            }

            if (MktFocusViralChevronTxt != null)
            {
                MktFocusViralChevronTxt.Text = _isFocusViralDropdownOpen ? "▲ Zuklappen" : "▼ Aufklappen";
            }

            if (MktFocusViralBodyBorder != null)
            {
                MktFocusViralBodyBorder.Visibility = _isFocusViralDropdownOpen ? Visibility.Visible : Visibility.Collapsed;
            }

            if (MktFocusViralDurationBadgeTxt != null)
            {
                MktFocusViralDurationBadgeTxt.Text = $"🔥 #{idea.Rank} · {idea.DurationLabel} · {idea.EffortLabel}";
            }

            if (MktFocusViralSeasonBadgeTxt != null)
            {
                string holidayBadge = !string.IsNullOrEmpty(s.PrimaryHolidayCutoff)
                    ? $"{s.SeasonIcon} {s.SeasonName} · {s.PrimaryHolidayBadge} (bis {s.PrimaryHolidayCutoff})"
                    : $"{s.SeasonIcon} {s.SeasonName}";
                MktFocusViralSeasonBadgeTxt.Text = $"{holidayBadge} · {prod.Name}";
            }

            MktFocusViralTitleTxt.Text = $"{idea.Title} ({prod.Name})";
            MktFocusViralHookTxt.Text = $"Hook im Bild: „{dyn.hook}“";
            MktFocusViralSongTxt.Text = $"🎵 TikTok & Instagram Trend-Song: {idea.TrendingSongs}";
            MktFocusViralStep1Txt.Text = dyn.step1;
            MktFocusViralStep2Txt.Text = dyn.step2;
            MktFocusViralStep3Txt.Text = dyn.step3;

            if (MktFocusViralSoundRecTxt != null)
            {
                MktFocusViralSoundRecTxt.Text = $"🎵 Musik-Empfehlung für diese Virale Idee: {recTrack.Title}";
                MktFocusViralSoundRecTxt.Foreground = BrushFromHex("#D4AF37");
            }
            if (MktFocusViralSoundMetaTxt != null)
            {
                MktFocusViralSoundMetaTxt.Text = $"{recTrack.Bpm} BPM · {recTrack.MusicalKey} · {recTrack.DurationFormatted} · {recTrack.DropTimingLabel}";
                MktFocusViralSoundMetaTxt.Foreground = BrushFromHex("#9CA3AF");
            }
            if (MktFocusViralSoundRecBorder != null)
            {
                MktFocusViralSoundRecBorder.Background = BrushFromHex("#0B0D12");
                MktFocusViralSoundRecBorder.BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059"));
            }
            UpdateMarketingMusicPreviewButtonsUI();
        }

        private void MktToggleFocusViralDropdown_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            _isFocusViralDropdownOpen = !_isFocusViralDropdownOpen;
            RenderFocusViralDropdown();
        }

        private void MktPrevViralIdea_Click(object sender, RoutedEventArgs e)
        {
            EnsureViralIdeasInitialized();
            if (_mktVault.ViralIdeas.Count == 0) return;
            PlaySoundNav();
            _currentViralIdeaIndex = (_currentViralIdeaIndex - 1 + _mktVault.ViralIdeas.Count) % _mktVault.ViralIdeas.Count;
            _selectedViralIdea = _mktVault.ViralIdeas[_currentViralIdeaIndex];
            SaveMarketingVaultOffline();
            RenderFocusViralDropdown();
            if (_activeMarketingSubTab == "viral") RenderMarketingViralPane();
        }

        private void MktNextViralIdea_Click(object sender, RoutedEventArgs e)
        {
            EnsureViralIdeasInitialized();
            if (_mktVault.ViralIdeas.Count == 0) return;
            PlaySoundNav();
            _currentViralIdeaIndex = (_currentViralIdeaIndex + 1) % _mktVault.ViralIdeas.Count;
            _selectedViralIdea = _mktVault.ViralIdeas[_currentViralIdeaIndex];
            SaveMarketingVaultOffline();
            RenderFocusViralDropdown();
            if (_activeMarketingSubTab == "viral") RenderMarketingViralPane();
        }

        private void MktSetViralAsFocusIdea_Click(object sender, RoutedEventArgs e)
        {
            EnsureViralIdeasInitialized();
            if (_selectedViralIdea == null) return;
            PlaySoundTaskDone();

            int idx = _mktVault.ViralIdeas.FindIndex(x => x.Rank == _selectedViralIdea.Rank);
            if (idx >= 0)
            {
                _currentViralIdeaIndex = idx;
                SaveMarketingVaultOffline();
                RenderFocusViralDropdown();
                RenderMarketingViralPane();
            }

            if (sender is Button btn)
            {
                string orig = btn.Content?.ToString() ?? "⭐ Als Fokus-Idee heute setzen";
                btn.Content = "✓ Im Fokus-Dropdown gesetzt!";
                _ = Task.Delay(1500).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
            }
        }

        private void MktRotateSelectedViralProduct_Click(object sender, RoutedEventArgs e)
        {
            EnsureViralIdeasInitialized();
            var target = (_mktVault.IsFocusMode && _mktVault.ViralIdeas.Count > 0)
                ? _mktVault.ViralIdeas[Math.Clamp(_currentViralIdeaIndex, 0, _mktVault.ViralIdeas.Count - 1)]
                : (_selectedViralIdea ?? _mktVault.ViralIdeas.FirstOrDefault());
            if (target == null) return;

            PlaySoundActionPop();
            target.ProductVariantIndex++;
            SaveMarketingVaultOffline();
            RenderFocusViralDropdown();
            if (_activeMarketingSubTab == "viral") RenderMarketingViralPane();
        }

        private void MktCopySelectedViralSong_Click(object sender, RoutedEventArgs e)
        {
            EnsureViralIdeasInitialized();
            var target = (_mktVault.IsFocusMode && _mktVault.ViralIdeas.Count > 0)
                ? _mktVault.ViralIdeas[Math.Clamp(_currentViralIdeaIndex, 0, _mktVault.ViralIdeas.Count - 1)]
                : (_selectedViralIdea ?? _mktVault.ViralIdeas.FirstOrDefault());
            if (target == null) return;

            Clipboard.SetText(target.TrendingSongs);
            PlaySoundActionPop();
            if (sender is Button btn)
            {
                string orig = btn.Content?.ToString() ?? "📋 Song kopieren";
                btn.Content = "✓ Song kopiert!";
                _ = Task.Delay(1400).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
            }
        }

        private void MktCopySelectedViralCaption_Click(object sender, RoutedEventArgs e)
        {
            EnsureViralIdeasInitialized();
            var target = (_mktVault.IsFocusMode && _mktVault.ViralIdeas.Count > 0)
                ? _mktVault.ViralIdeas[Math.Clamp(_currentViralIdeaIndex, 0, _mktVault.ViralIdeas.Count - 1)]
                : (_selectedViralIdea ?? _mktVault.ViralIdeas.FirstOrDefault());
            if (target == null) return;

            var dyn = BuildDynamicViralTexts(target);
            Clipboard.SetText(dyn.caption);
            PlaySoundActionPop();
            if (sender is Button btn)
            {
                string orig = btn.Content?.ToString() ?? "📋 Caption";
                btn.Content = "✓ Kopiert!";
                _ = Task.Delay(1400).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
            }
        }

        private void MktOpenViralReferenceUrl_Click(object sender, RoutedEventArgs e)
        {
            var idea = _selectedViralIdea ?? _mktVault.ViralIdeas.FirstOrDefault();
            if (idea == null || string.IsNullOrWhiteSpace(idea.ReferenceTrendUrl)) return;
            PlaySoundNav();
            try
            {
                Process.Start(new ProcessStartInfo
                {
                    FileName = idea.ReferenceTrendUrl,
                    UseShellExecute = true
                });
            }
            catch { }
        }

        private async void MktAddViralToTodo_Click(object sender, RoutedEventArgs e)
        {
            var idea = _selectedViralIdea ?? _mktVault.ViralIdeas.FirstOrDefault();
            if (idea == null) return;
            var prod = GetProductForViralIdea(idea);
            string taskTitle = $"🔥 Viral-Video drehen ({idea.DurationLabel}): {idea.Title} mit {prod.Name} [Song: {idea.ShortSongBadge}]";

            PlaySoundTaskDone();
            if (sender is Button btn)
            {
                string orig = btn.Content?.ToString() ?? "➕ In To-Do";
                btn.Content = "✓ Im To-Do!";
                _ = Task.Delay(1500).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
            }

            try
            {
                var payload = JsonSerializer.Serialize(new
                {
                    title = taskTitle,
                    priority = "hoch",
                    relevant_from = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")
                });
                await _http.PostAsync($"{_apiBaseUrl}/funki/tasks/lists/my_day/tasks", new StringContent(payload, Encoding.UTF8, "application/json"));
                await LoadTodoListsAsync();
            }
            catch
            {
                _allTasks.Insert(0, new TaskItemDto
                {
                    Id = Guid.NewGuid().ToString("N"),
                    Title = taskTitle,
                    Priority = "hoch",
                    IsCompleted = false
                });
                UpdateSpacesBadges();
            }
        }

        private List<ViralConceptIdeaDto> BuildDefaultViralIdeas()
        {
            return new List<ViralConceptIdeaDto>
            {
                new ViralConceptIdeaDto
                {
                    Rank = 1,
                    CategoryKey = "launch",
                    CategoryLabel = "✂️ Schleife & Launch",
                    Title = "Handy in Schleife durchschneiden (Online Grand Opening)",
                    DurationLabel = "9 Sek.",
                    EffortLabel = "5 Min. Dreh",
                    ViewsBenchmark = "4,5M–18M",
                    ShortSongBadge = "Taylor Swift – The Fate of Ophelia (Piano) / Bridgerton Strings",
                    TrendingSongs = "TikTok & Reels #1: Taylor Swift – The Fate of Ophelia (Piano / Instrumental)   |   #2: Vitamin String Quartet – Give Me Everything (Bridgerton Strings)   |   #3: Petit Biscuit – Sunset Lover (-8 dB) + 100 % echter Scheren-Schnipp (0 dB)",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=online%20business%20grand%20opening%20ribbon%20cutting%20phone",
                    OnScreenHook = "Wenn man eine Online-Manufaktur eröffnet und trotzdem feierlich das Band durchschneidet ✨✂️",
                    StructureBreakdown = "• Sek. 0,0–2,2 (Visueller Stopp): Smartphone liegt auf dunklem Schiefer, Display auf 100 % Helligkeit mit @meinseelenfunke / 3D-Konfigurator für {PRODUKT}. Ein goldenes oder schwarzes Satinband ist mit einer Schleife fest um das Handy gebunden. Die goldene Schere schwebt (mit weißen Handschuhen!) bereits an der Schleife.\n• Sek. 2,2–4,8 (ASMR-Schnitt): Ein einzelner, knackiger Scherenschnitt (0 dB Original-Ton!). Das Band fällt nach links und rechts auseinander.\n• Sek. 4,8–9,0 (Reveal & Loop): Der Finger (im weißen Handschuh) tippt live auf das freigelegte Display und dreht {PRODUKT} in 3D, während daneben das echte Unikat in der schwarzen Westpack-Box funkelt.",
                    WhyItGoesViral = "1. Muster-Unterbrechung (Pattern Interrupt): Zuschauer kennen Band-Durchschneiden nur von echten Ladengeschäften – ein Handy in einer Geschenk-Schleife stoppt in 0,4 Sek. den Daumen.\n2. Sympathie-Trigger ('Support Small Business'): Tausende fremde Zuschauer kommentieren automatisch 'Herzlichen Glückwunsch zur Eröffnung!', was den Algorithmus explodieren lässt.",
                    Step1Setup = "1. Zweites Smartphone (Display-Timeout aus, 100 % Helligkeit, geöffnet auf @meinseelenfunke oder {PRODUKT}).\n2. 40 cm Satinband (Gold #C5A059 oder Schwarz) + scharfe Gold-Schere + weiße Baumwollhandschuhe.\n3. Echtes {PRODUKT} in geöffneter Westpack-Box rechts neben das Handy legen.",
                    Step2Shoot = "Binde das Band locker um das Handy (Schleife mittig auf dem Display). Starte die Aufnahme exakt, wenn die Schere das Band berührt (kein Leerlauf!). Schneide bei Sekunde 2,5 kräftig durch, ziehe die Bandenden zur Seite und drehe 3 Sekunden lang {PRODUKT} live auf dem Handybildschirm.",
                    Step3EditAndPost = "Schneide das Video auf knackige 8,5–9,5 Sekunden. Lass das echte 'Schnipp'-Geräusch der Schere bei 100 % (0 dB) und lege 'The Fate of Ophelia (Piano)' oder 'Bridgerton Strings' bei -8 dB darunter. Link in Bio auf {SLUG}.",
                    ReadyCaptionAndTags = "Hiermit ist unsere Online-Manufaktur Mein-Seelenfunke offiziell eröffnet! ✂️✨ Da wir kein Straßengeschäft haben, mussten wir das feierliche Band eben auf unsere eigene Art durchschneiden. 🤍 Welches Stück würdest du als Erstes im 3D-Konfigurator gestalten – {PRODUKT} oder den Seelen Kristall? 👇\n\n👉 Schau dich jetzt in unserem Atelier um: mein-seelenfunke.de ({SLUG})\n\n#neueröffnung #grandopening #kleinesunternehmen #handgemacht #geschenkidee",
                    ProductVariantIndex = 1
                },
                new ViralConceptIdeaDto
                {
                    Rank = 2,
                    CategoryKey = "asmr",
                    CategoryLabel = "🔊 ASMR & Handschuhe",
                    Title = "Das 8-Sekunden Schachtel-Klacken (Westpack White-Glove Ritual)",
                    DurationLabel = "8 Sek.",
                    EffortLabel = "4 Min. Dreh",
                    ViewsBenchmark = "2,8M–9,4M",
                    ShortSongBadge = "100 % Original Box-Snap + Petit Biscuit – Sunset Lover",
                    TrendingSongs = "Original ASMR Ton (Samt-Streichen & Schachtel-Klacken auf 0 dB)   |   Hintergrund (-10 dB): Petit Biscuit – Sunset Lover (Slowed Instrumental) oder Ludovico Einaudi – Una Mattina",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=luxury%20jewelry%20packaging%20white%20gloves%20asmr",
                    OnScreenHook = "POV: Dein Geschenk verlässt unsere Manufaktur erst nach dem White-Glove-Ritual 🧤✨",
                    StructureBreakdown = "• Sek. 0,0–2,5: Blütenweiße Handschuhe streichen langsam über das funkelnde {PRODUKT} im warmen Streiflicht.\n• Sek. 2,5–5,5: Sanftes Einbetten in das schwarze Samt-Inlay der mattschwarzen Westpack-Schachtel.\n• Sek. 5,5–8,0: Der Deckel schließt mit einem satten, tiefen 'KLACK' – Ende exakt auf dem Schallimpuls.",
                    WhyItGoesViral = "Hebt den wahrgenommenen Wert sofort auf 250-€-Juwelier-Niveau und löst bei Geschenk-Käufern das Gefühl aus: 'Ich muss nichts mehr selbst einpacken!'",
                    Step1Setup = "Mattschwarze Westpack-Schmuckbox · Blütenweiße Baumwollhandschuhe · Feiner Staubpinsel · {PRODUKT}.",
                    Step2Shoot = "Halte das Handy-Mikrofon 15 cm nah an die Schachtel. Streiche einmal mit dem Pinsel über {PRODUKT}, lege es in die Box und schließe den Deckel hörbar.",
                    Step3EditAndPost = "Kürze auf exakt 8 Sekunden. Der letzte Frame ist die geschlossene Box mit Gold-Logo (perfekter Loop!). Song bei -10 dB darunterlegen.",
                    ReadyCaptionAndTags = "Kein Fingerabdruck, kein Staubkorn – jedes {PRODUKT} wird in unserer Manufaktur mit weißen Handschuhen geprüft und verschenkfertig gebettet. 🧤🖤 Für wen suchst du gerade ein besonderes Unikat? 👇\n\n👉 Direkt konfigurieren: {SLUG}\n\n#whiteglove #unboxingasmr #schmuckliebe #geschenkverpackung",
                    ProductVariantIndex = 1
                },
                new ViralConceptIdeaDto
                {
                    Rank = 3,
                    CategoryKey = "asmr",
                    CategoryLabel = "🔊 ASMR & Handschuhe",
                    Title = "Licht-Aus im Dunkeln: Der 3D-Kristall erwacht (Lights-Off Reveal)",
                    DurationLabel = "10 Sek.",
                    EffortLabel = "5 Min. Dreh",
                    ViewsBenchmark = "3,9M–14M",
                    ShortSongBadge = "Hans Zimmer – Cornfield Chase (Piano) / Einaudi – Experience",
                    TrendingSongs = "TikTok & Reels #1: Hans Zimmer – Cornfield Chase (Dorian Marko Piano)   |   #2: Ludovico Einaudi – Experience (Violin Drop bei Sek. 3,5)",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=3d%20crystal%20photo%20light%20reveal%20in%20dark",
                    OnScreenHook = "Warte bis das Licht ausgeht... 🕯️✨ (Aus einem normalen 2D-Handyfoto!)",
                    StructureBreakdown = "• Sek. 0,0–3,0: Raum ist hell, Handy mit 2D-Foto wird neben den unbeleuchteten Glasblock gehalten.\n• Sek. 3,0–3,5: Raumlicht geht schlagartig aus (Klick-Geräusch des Schalters!).\n• Sek. 3,5–10,0: Der LED-Sockel schaltet sich ein und das 3D-Portrait im {PRODUKT} leuchtet magisch im Dunkeln, während weiße Handschuhe es um 45° drehen.",
                    WhyItGoesViral = "'Warte bis...'-Hooks in Kombination mit einem echten Hell-zu-Dunkel-Kontrast bei Sekunde 3 sorgen für über 85 % Watchtime bis zum Ende.",
                    Step1Setup = "Der Seelen Kristall (oder {PRODUKT}) · LED-Leuchtsockel · 2. Handy mit Vorlagen-Foto · Raum komplett abdunkelbar.",
                    Step2Shoot = "Starte mit Raumlicht an (Foto neben Kristall). Schalte bei Sekunde 3 das Raumlicht aus und im selben Moment den LED-Sockel an. Drehe das Stück langsam.",
                    Step3EditAndPost = "Lege den Violin-/Piano-Drop von 'Cornfield Chase' oder 'Experience' exakt auf Sekunde 3,2 (den Einschalt-Moment des Lichts!).",
                    ReadyCaptionAndTags = "Aus einem ganz normalen Handyfoto wird ein leuchtendes 3D-Erinnerungsstück für die Ewigkeit. 💎✨ Welches Foto auf deinem Handy hätte diesen Platz verdient? 👇\n\n👉 Teste dein Foto live in 3D: {SLUG}\n\n#3dkristall #lichtmagie #erinnerungsstück #fotogeschenk",
                    ProductVariantIndex = 0
                },
                new ViralConceptIdeaDto
                {
                    Rank = 4,
                    CategoryKey = "story",
                    CategoryLabel = "🤍 Emotion & Story",
                    Title = "Letzte Handschrift auf altem Papier -> Für immer in Gold",
                    DurationLabel = "11 Sek.",
                    EffortLabel = "6 Min. Dreh",
                    ViewsBenchmark = "3,2M–11M",
                    ShortSongBadge = "SYML – Where's My Love (Acoustic Piano) / Billie Eilish Instrumental",
                    TrendingSongs = "TikTok & Reels #1: SYML – Where's My Love (Acoustic Piano Solo)   |   #2: Billie Eilish – What Was I Made For? (Piano Instrumental) + 3 Sek. echtes Laser-Singen",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=handwriting%20engraved%20necklace%20memorial",
                    OnScreenHook = "„Kannst du die letzte Geburtstagskarte meiner Oma auf Gold verewigen?“ 🥺🤍",
                    StructureBreakdown = "• Sek. 0,0–2,5: Eine alte handgeschriebene Karte ('Hab dich lieb, deine Oma') liegt neben dem ungravierten {PRODUKT}.\n• Sek. 2,5–6,0: Makro-Aufnahme: Der xTool F2 Ultra brennt exakt diese Schwünge mit einem hellen Blitz ins Material.\n• Sek. 6,0–11,0: Weiße Handschuhe halten die Papierkarte und das fertige {PRODUKT} direkt nebeneinander ins warme Streiflicht.",
                    WhyItGoesViral = "Handschriften von Großeltern/Eltern lösen sofort Gänsehaut aus und werden extrem häufig per WhatsApp an Geschwister und Familie weitergeleitet (hohe Share-Rate!).",
                    Step1Setup = "Handgeschriebener Zettel/Karte · {PRODUKT} · Weiße Baumwollhandschuhe · xTool F2 Ultra UV.",
                    Step2Shoot = "Clip 1 (2,5s): Zettel neben Rohling. Clip 2 (3,5s): Laser-Gravur in Nahaufnahme. Clip 3 (5s): Fertiges Stück neben dem Zettel leicht im Licht kippen.",
                    Step3EditAndPost = "0–6s echter Laser-Ton hörbar lassen, ab Sekunde 6 sanftes Piano ('Where's My Love') aufblenden. Direktlink auf {SLUG}.",
                    ReadyCaptionAndTags = "Manche Worte auf Papier verblassen mit den Jahren – aber auf {PRODUKT} bleiben sie für immer nah am Herzen tragbar. 🤍✨ Wessen Handschrift würdest du verewigen lassen? 👇\n\n👉 Handschrift einfach abfotografieren & hochladen: {SLUG}\n\n#handschriftgravur #erinnerungsschmuck #omageschenk #seelenfunke",
                    ProductVariantIndex = 1
                },
                new ViralConceptIdeaDto
                {
                    Rank = 5,
                    CategoryKey = "quick",
                    CategoryLabel = "⚡ 7-Sek. Trends",
                    Title = "7-Sekunden Endlos-Loop: Violetter UV-Laser schwebt im Glas",
                    DurationLabel = "7 Sek.",
                    EffortLabel = "3 Min. Dreh",
                    ViewsBenchmark = "1,9M–8,5M",
                    ShortSongBadge = "100 % Original UV-Laser-Knistern (0 dB) + Øfdream – Thelema",
                    TrendingSongs = "100 % Original xTool 5W UV-Laser-Knistern (0 dB)   |   Optional leise (-14 dB): Øfdream – Thelema (Slowed) oder Pure ASMR (ohne Musik)",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=subsurface%20laser%20engraving%20crystal%20asmr",
                    OnScreenHook = "Wie kommt das Bild mitten ins Glas, ohne die Oberfläche zu berühren? 🤯🔮",
                    StructureBreakdown = "• Sek. 0,0–4,5: Extreme 3x-Makro-Aufnahme durch die Laserschutz-Scheibe: Tausende violette Mikropunkte blitzen im K9-Kristall auf.\n• Sek. 4,5–7,0: Weiße Handschuhe streichen über die spiegelglatte Außenfläche des Glases – null Kratzer außen, 100 % 3D innen!",
                    WhyItGoesViral = "Technik-Rätsel ('Wie ist das physikalisch möglich?') triggern hunderte Diskussionen in den Kommentaren, während das kurze 7s-Video 3x durchläuft.",
                    Step1Setup = "xTool F2 Ultra UV im laufenden Kristall-Job · Smartphone mit 2x/3x Zoom fest ans Schutzfenster halten · Weiße Handschuhe.",
                    Step2Shoot = "Filme 5 Sekunden lang das violette Aufblitzen im Kristall und 2 Sekunden lang, wie du mit dem behandschuhten Finger über die glatte Glas-Außenseite streichst.",
                    Step3EditAndPost = "Auf exakt 7,0 Sekunden schneiden. Keinen Abspann einbauen, damit das Video unbemerkt wieder von vorn startet.",
                    ReadyCaptionAndTags = "Unser 5W UV-Laser bündelt das Licht exakt im Inneren des K9-Kristalls – die Oberfläche bleibt spiegelglatt und unversehrt! 🔮⚡ Faszinierend, oder? 👇\n\n👉 Erstelle dein eigenes 3D-Kristall-Unikat: {SLUG}\n\n#uvlaser #laserasmr #3dglasgravur #manufaktur #technikmagie",
                    ProductVariantIndex = 0
                },
                new ViralConceptIdeaDto
                {
                    Rank = 6,
                    CategoryKey = "story",
                    CategoryLabel = "🤍 Emotion & Story",
                    Title = "Der Perfektionismus-Check: „Warum dieses Stück NICHT rausgeht“",
                    DurationLabel = "13 Sek.",
                    EffortLabel = "6 Min. Dreh",
                    ViewsBenchmark = "2,1M–6,8M",
                    ShortSongBadge = "Ruhige Gründer-Stimme + Warmes Lo-Fi Piano (-15 dB)",
                    TrendingSongs = "Originalton / Ruhige Stimme (0 dB)   |   Hintergrund (-15 dB): Idealism – Both of Us (Lo-Fi Piano) oder Toshifumi Hinata – Reflections",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=why%20this%20order%20failed%20quality%20control%20jewelry",
                    OnScreenHook = "Warum dieses {PRODUKT} meine Qualitätskontrolle heute NICHT bestanden hat 🔍🧤",
                    StructureBreakdown = "• Sek. 0,0–4,0: Du hältst {PRODUKT} mit weißen Handschuhen unter die Lupe ins Streiflicht ('Für 99 % sieht es perfekt aus, aber die Gravur sitzt 0,4 mm zu weit links').\n• Sek. 4,0–9,0: Einlegen eines brandneuen Rohlings in den xTool Laser und perfekter Zweit-Durchlauf.\n• Sek. 9,0–13,0: Beide Stücke kurz vergleichen und das makellose Stück in die Westpack-Box betten.",
                    WhyItGoesViral = "Zuschauer versuchen im Video den winzigen 'Fehler' zu finden (halten das Video an -> 200 % Watchtime!) und fassen blindes Vertrauen in deine Manufaktur-Qualität.",
                    Step1Setup = "Ein Probe-/Ausschuss-Stück + ein perfektes {PRODUKT} · Juwelierlupe · Weiße Handschuhe · Westpack-Box.",
                    Step2Shoot = "Zeige 4s das Probestück unter der Lupe, 4s den neuen Laserblitz und 5s das makellose Endergebnis in der schwarzen Schachtel.",
                    Step3EditAndPost = "Entweder mit 2 ruhigen Sätzen einsprechen oder rein über Text-Einblendungen + sanftes Piano lösen.",
                    ReadyCaptionAndTags = "Aus unserer Manufaktur in Gifhorn geht kein Stück heraus, das ich nicht selbst mit Stolz verschenken würde. 🔍🤍 Siehst du den winzigen Unterschied von 0,4 mm? 👇\n\n👉 Handveredelte Perfektion entdecken: {SLUG}\n\n#qualitätskontrolle #manufaktur #handarbeit #perfektionismus #schmuckmanufaktur",
                    ProductVariantIndex = 1
                },
                new ViralConceptIdeaDto
                {
                    Rank = 7,
                    CategoryKey = "launch",
                    CategoryLabel = "✂️ Schleife & Launch",
                    Title = "3D-Konfigurator am Handy -> Fingerschnips -> Echtes Unikat",
                    DurationLabel = "8 Sek.",
                    EffortLabel = "4 Min. Dreh",
                    ViewsBenchmark = "1,8M–5,5M",
                    ShortSongBadge = "Sabrina Carpenter – Espresso (Instrumental) / Beat-Transition",
                    TrendingSongs = "TikTok & Reels #1: Sabrina Carpenter – Espresso (Instrumental Beat-Drop)   |   #2: Peggy Gou – (It Goes Like) Nanana (Instrumental)",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=custom%203d%20preview%20to%20real%20product%20transition",
                    OnScreenHook = "Wenn du erst im 3D-Konfigurator spielst... und es 3 Tage später ECHT vor dir liegt ✨📱",
                    StructureBreakdown = "• Sek. 0,0–3,5: Eine Hand dreht {PRODUKT} live im 3D-Konfigurator auf dem Smartphone-Display.\n• Sek. 3,5–4,0: Die andere Hand (im weißen Handschuh) führt das echte {PRODUKT} exakt im gleichen Winkel vor die Kameralinse.\n• Sek. 4,0–8,0: Das echte Unikat funkelt im Streiflicht vor der schwarzen Westpack-Box.",
                    WhyItGoesViral = "Nimmt jedem Erstbesucher sofort die größte Angst im Online-Handel ('Sieht das am Ende wirklich so aus wie in der Vorschau?') und löst direkte Spieltrieb-Klicks auf den Bio-Link aus.",
                    Step1Setup = "Smartphone mit geöffnetem 3D-Konfigurator ({SLUG}) · Echtes {PRODUKT} mit identischem Motiv · Weiße Handschuhe.",
                    Step2Shoot = "Drehe 3,5 Sekunden lang das 3D-Modell am Handy und schiebe auf dem Beat-Drop das echte {PRODUKT} exakt darüber ins Bild.",
                    Step3EditAndPost = "Schnitt exakt auf den Beat-Drop bei Sekunde 3,5 legen. Direktlink in Bio auf {SLUG} setzen.",
                    ReadyCaptionAndTags = "Kein blindes Bestellen mehr: Bei uns siehst du dein {PRODUKT} schon vor dem Kauf live in 3D von allen Seiten! 📱✨ Hast du unseren 3D-Konfigurator schon kostenlos ausprobiert? 👇\n\n👉 Jetzt eigenes Foto/Wunschtext in 3D testen: {SLUG}\n\n#3dkonfigurator #vorhernachher #personalisiertegeschenke #unikat",
                    ProductVariantIndex = 2
                },
                new ViralConceptIdeaDto
                {
                    Rank = 8,
                    CategoryKey = "story",
                    CategoryLabel = "🤍 Emotion & Story",
                    Title = "Schwarz-Eloxal Kontrast-Magie: Silber-Weiß blitzt aus Tiefschwarz",
                    DurationLabel = "9 Sek.",
                    EffortLabel = "4 Min. Dreh",
                    ViewsBenchmark = "2,4M–7,9M",
                    ShortSongBadge = "Yiruma – River Flows in You + xTool Laser-Zischen",
                    TrendingSongs = "Sek. 0–4: 100 % Original Laser-Zischen (0 dB)   |   Ab Sek. 4: Yiruma – River Flows in You (Warm Piano -8 dB)",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=black%20metal%20laser%20engraving%20satisfying",
                    OnScreenHook = "Dieser Kontrast, wenn der Laser auf mattschwarzes Metall trifft... ⚡🖤",
                    StructureBreakdown = "• Sek. 0,0–4,0: Der Laserstrahl rast in Höchstgeschwindigkeit über das tiefschwarze Aluminium (Der Seelenanhänger) und legt in Echtzeit strahlendes Silber-Weiß frei.\n• Sek. 4,0–9,0: Weiße Handschuhe heben das Herz hoch und wenden es auf die Rückseite (geheime Botschaft).",
                    WhyItGoesViral = "Der extreme Hell-Dunkel-Kontrast beim Abtragen der schwarzen Eloxalschicht gehört zu den meistgespeicherten 'Satisfying Engraving'-Clips auf TikTok und Reels.",
                    Step1Setup = "Der Seelenanhänger (Schwarz-Eloxal) oder {PRODUKT} · xTool F2 Ultra · Weiße Handschuhe · Schwarze Westpack-Box.",
                    Step2Shoot = "Starte die Aufnahme 0,2 Sek. vor dem ersten Laserblitz (2x Zoom). Nimm das Stück danach mit weißen Handschuhen heraus und wende es im Licht.",
                    Step3EditAndPost = "Auf 9 Sekunden kürzen, Original-Laserzischen ungekürzt auf 0 dB lassen.",
                    ReadyCaptionAndTags = "Außen edles Mattschwarz, innen eine Botschaft in strahlendem Silber-Weiß, die niemals verblasst. 🖤✨ Welches Datum oder welchen Namen würdest du auf die Rückseite gravieren? 👇\n\n👉 Jetzt gestalten: {SLUG}\n\n#satisfyingvideo #lasergravur #schwarzesschmuckstück #gravuranhänger",
                    ProductVariantIndex = 4
                },
                new ViralConceptIdeaDto
                {
                    Rank = 9,
                    CategoryKey = "quick",
                    CategoryLabel = "⚡ 7-Sek. Trends",
                    Title = "Das Bestseller-Duell: „Team 1 (Gold) oder Team 2 (Kristall)?“",
                    DurationLabel = "7 Sek.",
                    EffortLabel = "3 Min. Dreh",
                    ViewsBenchmark = "1,5M–4,9M",
                    ShortSongBadge = "Tyla – Water (Instrumental) / Luxury Lounge Beat",
                    TrendingSongs = "TikTok & Reels #1: Tyla – Water (Instrumental Slowed)   |   #2: Kaytranada – Intimidated (Instrumental Lounge Beat)",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=which%20one%20would%20you%20choose%20jewelry%20edition",
                    OnScreenHook = "Ehrliche Frage: Über welches Geschenk würdest du dich mehr freuen – 1 oder 2? 👇✨",
                    StructureBreakdown = "• Sek. 0,0–3,5: Links im Bild funkelt Das Goldene Seelen-Herz in der geöffneten Westpack-Box ('1').\n• Sek. 3,5–7,0: Kameraschwenk 15 cm nach rechts auf Den Seelen Kristall auf dem leuchtenden LED-Sockel ('2').",
                    WhyItGoesViral = "Menschen lieben es, in 1 Sekunde ihre Meinung abzugeben ('1' oder '2' tippen). Hunderte Kurz-Kommentare pushen das Video sofort in neue Feeds.",
                    Step1Setup = "Das Goldene Seelen-Herz (in Westpack-Box) + Der Seelen Kristall (auf LED-Sockel) nebeneinander auf dunklem Schiefer.",
                    Step2Shoot = "Ein einziger ruhiger Kameraschwenk von links (Stück 1) nach rechts (Stück 2) mit weißen Handschuhen, die kurz beide Stücke ins Licht drehen.",
                    Step3EditAndPost = "Über das linke Stück '1 💛' und über das rechte Stück '2 💎' als kleinen Text setzen. Auf 7 Sekunden schneiden.",
                    ReadyCaptionAndTags = "Stell dir vor, dein Lieblingsmensch überrascht dich heute: Team 1 (Goldenes Seelen-Herz) oder Team 2 (Leuchtender 3D Seelen Kristall)? Schreib 1 oder 2 in die Kommentare! 👇✨\n\n👉 Beide Unikate findest du hier: {SLUG}\n\n#geschenkidee #team1oder2 #schmuckoderkristall #persönlichesgeschenk",
                    ProductVariantIndex = 0
                },
                new ViralConceptIdeaDto
                {
                    Rank = 10,
                    CategoryKey = "story",
                    CategoryLabel = "🤍 Emotion & Story",
                    Title = "Haustier-Unikat: „Damit deine Fellnase immer bei dir ist 🐾“",
                    DurationLabel = "12 Sek.",
                    EffortLabel = "5 Min. Dreh",
                    ViewsBenchmark = "2,9M–10M",
                    ShortSongBadge = "JVKE – golden hour (Piano Slowed)",
                    TrendingSongs = "TikTok & Reels #1: JVKE – golden hour (Piano Slowed)   |   #2: Coldplay – Sparks (Instrumental Piano) + 3s Laser-Singen",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=custom%20pet%20memorial%20engraving%20dog",
                    OnScreenHook = "Für alle, deren Vierbeiner kein bloßes Haustier, sondern Familie ist 🐾🤍",
                    StructureBreakdown = "• Sek. 0,0–3,0: Smartphone mit einem süßen Hunde-/Katzenfoto liegt neben dem Rohling ({PRODUKT} / Seelen-Pfote).\n• Sek. 3,0–7,0: Präzisions-Gravur im xTool F2 Ultra.\n• Sek. 7,0–12,0: Das fertige Stück wird im goldenen Licht direkt neben das Haustier-Foto gehalten.",
                    WhyItGoesViral = "Die Haustier-Community (Hunde & Katzen) hat auf TikTok und Instagram die höchste emotionale Kauf- und Markier-Rate ('@freundin das brauchen wir für Bello!').",
                    Step1Setup = "Die Seelen-Pfote oder Der Seelen Kristall ({PRODUKT}) · Handy mit Haustierfoto · Weiße Handschuhe.",
                    Step2Shoot = "Zeige 3s das Haustierfoto am Handy, 4s den Laserblitz und 5s das fertige Unikat in der weißen behandschuhten Hand.",
                    Step3EditAndPost = "Beim Übergang vom Laser zum fertigen Stück den Piano-Refrain von 'golden hour' einsetzen lassen.",
                    ReadyCaptionAndTags = "Ob als täglicher Begleiter am Halsband oder als leuchtende Erinnerung für die Ewigkeit – wir verewigen deine Fellnase mit höchster Präzision. 🐾🤍 Wie heißt dein treuster Begleiter? 👇\n\n👉 Jetzt gestalten: {SLUG}\n\n#hundeliebe #katzenliebe #pfotenabdruck #haustiergeschenk #hundemarke",
                    ProductVariantIndex = 3
                },
                new ViralConceptIdeaDto
                {
                    Rank = 11,
                    CategoryKey = "quick",
                    CategoryLabel = "⚡ 7-Sek. Trends",
                    Title = "Der 180°-Wende-Moment: Das Geheimnis auf der Rückseite",
                    DurationLabel = "8 Sek.",
                    EffortLabel = "4 Min. Dreh",
                    ViewsBenchmark = "1,7M–6,2M",
                    ShortSongBadge = "Stephen Sanchez – Until I Found You (Instrumental Strings)",
                    TrendingSongs = "TikTok & Reels #1: Stephen Sanchez – Until I Found You (Strings Instrumental)   |   #2: Taylor Swift – Lover (Piano Instrumental)",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=secret%20message%20backside%20engraved%20necklace",
                    OnScreenHook = "Von vorne ein edles Schmuckstück... aber dreh es mal um 🤫✨",
                    StructureBreakdown = "• Sek. 0,0–3,5: Weiße Handschuhe halten die glänzende Vorderseite von {PRODUKT} (mit den Koordinaten eines besonderen Ortes).\n• Sek. 3,5–8,0: Langsame 180°-Drehung zwischen den Fingern – auf der Rückseite erscheint eine persönliche Liebesbotschaft oder Handschrift.",
                    WhyItGoesViral = "Neugier-Lücke ('Was steht auf der Rückseite?') zwingt den Zuschauer dranzubleiben und oft auf Pause zu drücken, um die geheime Gravur zu lesen.",
                    Step1Setup = "Beidseitig graviertes {PRODUKT} (z. B. Goldenes Seelen-Herz oder Seelen-Talisman) · Weiße Handschuhe · Schwarzer Hintergrund.",
                    Step2Shoot = "Halte {PRODUKT} ruhig ins Streiflicht, wende es bei Sekunde 3,5 sanft um 180° auf die Rückseite und lege es in die Westpack-Box.",
                    Step3EditAndPost = "Auf 8 Sekunden schneiden. Keinen Text über die Mitte legen, damit die Gravur frei lesbar bleibt.",
                    ReadyCaptionAndTags = "Vorne die Koordinaten des Ortes, an dem alles begann – und auf der Rückseite nur für dich allein lesbar: Deine persönlichen Worte. 🤫💛 Welchen Ort würdest du verewigen? 👇\n\n👉 Vorder- & Rückseite im 3D-Konfigurator gestalten: {SLUG}\n\n#koordinatenschmuck #geheimebotschaft #partnergeschenk #jahrestagsgeschenk",
                    ProductVariantIndex = 2
                },
                new ViralConceptIdeaDto
                {
                    Rank = 12,
                    CategoryKey = "asmr",
                    CategoryLabel = "🔊 ASMR & Handschuhe",
                    Title = "360° Rotations-Hypnose: Weizenglas dreht sich im Laserstrahl",
                    DurationLabel = "10 Sek.",
                    EffortLabel = "5 Min. Dreh",
                    ViewsBenchmark = "1,6M–5,8M",
                    ShortSongBadge = "RA3 Servo-Sound (0 dB) + Daft Punk – Veridis Quo (Slowed)",
                    TrendingSongs = "Sek. 0–5: 100 % Original RA3 Rotations- & Laser-Sound (0 dB)   |   Ab Sek. 5: Daft Punk – Veridis Quo (Slowed -8 dB)",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=rotary%20laser%20engraving%20beer%20glass%20satisfying",
                    OnScreenHook = "Wenn das Feierabend-Glas im 360°-Laser zum Einzelstück wird 🍺⚡",
                    StructureBreakdown = "• Sek. 0,0–5,0: Das Weizenglas dreht sich im xTool RA3 Rotationsaufsatz wie ein Uhrwerk, während der UV-Laser das Wappen/Motiv schneeweiß ins Kristallglas brennt.\n• Sek. 5,0–10,0: Weiße Handschuhe heben das fertige Glas ins warme Gegenlicht – die milchweiße Frost-Gravur leuchtet gestochen scharf.",
                    WhyItGoesViral = "Mechanische Präzision + Rotation wirkt extrem hypnotisch und erschließt sofort die riesige Zielgruppe für Männer-, Papa-, Vereins- und Trauzeugen-Geschenke.",
                    Step1Setup = "Der Feierabend-Funke (Weizenglas) · xTool RA3 Rotationsaufsatz · Weiße Handschuhe · Gegenlicht-Lampe.",
                    Step2Shoot = "Filme 5s die Drehung im RA3 während des Laserns und 5s das fertige Glas im warmen Gegenlicht vor dunklem Hintergrund.",
                    Step3EditAndPost = "Auf 10 Sekunden schneiden. Das mechanische Summen des RA3 in den ersten 4 Sekunden hörbar lassen.",
                    ReadyCaptionAndTags = "Kein Aufkleber, kein Verblassen in der Spülmaschine: Unsere UV-Lasergravur ist für die Ewigkeit direkt ins Kristallglas geschmolzen. 🍺✨ Wer aus deinem Freundeskreis hat sein eigenes Stammglas verdient? 👇\n\n👉 Jetzt Wunschname & Motiv eingeben: {SLUG}\n\n#weizenglas #männergeschenk #papageschenk #glasgravur #feierabendbier",
                    ProductVariantIndex = 5
                },
                new ViralConceptIdeaDto
                {
                    Rank = 13,
                    CategoryKey = "asmr",
                    CategoryLabel = "🔊 ASMR & Handschuhe",
                    Title = "Pack with Me: „Verpacke mit mir eine Kundenbestellung aus Gifhorn“",
                    DurationLabel = "12 Sek.",
                    EffortLabel = "5 Min. Dreh",
                    ViewsBenchmark = "1,4M–4,6M",
                    ShortSongBadge = "Packaging ASMR (0 dB) + Laufey – From The Start (Instrumental)",
                    TrendingSongs = "Original ASMR Verpackungs-Geräusche (0 dB)   |   Hintergrund (-10 dB): Laufey – From The Start (Instrumental) oder Cozy Cafe Bossanova",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=pack%20an%20order%20with%20me%20small%20jewelry%20business%20asmr",
                    OnScreenHook = "Verpacke mit mir eine ganz besondere Bestellung aus unserer Manufaktur 📦🧤",
                    StructureBreakdown = "• Sek. 0,0–3,0: Weiße Handschuhe nehmen das frisch gravierte {PRODUKT} aus dem xTool F2 Ultra.\n• Sek. 3,0–7,5: Einlegen in die schwarze Westpack-Box, Schließen des Deckels (KLACK!).\n• Sek. 7,5–12,0: Optionales Schwarz-Gold-Geschenkpapier / Versandkarton + Beilegen der Dankeskarte.",
                    WhyItGoesViral = "'Pack an Order with Me'-Videos zeigen echte Aktivität im Shop ('Da bestellen andere, also ist der Shop vertrauenswürdig!') und machen Lust, dass das nächste Paket das eigene ist.",
                    Step1Setup = "{PRODUKT} · Schwarze Westpack-Box · Weiße Handschuhe · Schwarzer Versandkarton & Dankeskarte.",
                    Step2Shoot = "4 kurze Schnitte à 2,5–3 Sekunden: 1. Entnahme aus Laser, 2. Einlegen in Box, 3. Box schließen, 4. In Versandkarton legen.",
                    Step3EditAndPost = "Schnelle, knackige Schnitte auf den Takt von 'From The Start (Instrumental)' legen, Original-Rascheln bei 0 dB lassen.",
                    ReadyCaptionAndTags = "Jedes einzelne Paket, das unsere Manufaktur in Gifhorn verlässt, hält eine persönliche Geschichte in sich. 📦🤍 Soll wir deine nächste Bestellung auch mit der Kamera begleiten? Schreib uns deine Bestellnummer in die Kommentare! 👇\n\n👉 Zum Shop: {SLUG}\n\n#packwithme #bestellungverpacken #manufaktur #kleinesunternehmen #geschenkbox",
                    ProductVariantIndex = 1
                },
                new ViralConceptIdeaDto
                {
                    Rank = 14,
                    CategoryKey = "quick",
                    CategoryLabel = "⚡ 7-Sek. Trends",
                    Title = "„Besser als Geld im Umschlag“ (Der Hochzeits- & Jahrestags-Retter)",
                    DurationLabel = "8 Sek.",
                    EffortLabel = "4 Min. Dreh",
                    ViewsBenchmark = "1,9M–6,4M",
                    ShortSongBadge = "Duomo – Wildest Dreams (Bridgerton Strings Cover)",
                    TrendingSongs = "TikTok & Reels #1: Duomo – Wildest Dreams (Strings Cover)   |   #2: Edith Whiskers – Home (Instrumental)",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=wedding%20gift%20idea%20better%20than%20money%20crystal",
                    OnScreenHook = "POV: Alle anderen schenken nur einen langweiligen Briefumschlag... und du kommst DAMIT an ✨🤍",
                    StructureBreakdown = "• Sek. 0,0–2,5: Die schwarze Westpack-Box (oder der beleuchtete Seelen Kristall) steht edel auf dem Tisch.\n• Sek. 2,5–8,0: Weiße Handschuhe öffnen langsam den Deckel und enthüllen {PRODUKT} mit den Initialen / dem Hochzeitsfoto des Paares.",
                    WhyItGoesViral = "Jede Woche suchen tausende Gäste verzweifelt nach einem Geschenk für Hochzeit, Jahrestag oder runden Geburtstag – dieses Video wird extrem oft als Lesezeichen gespeichert!",
                    Step1Setup = "{PRODUKT} · Schwarze Westpack-Box oder LED-Sockel · Weiße Handschuhe.",
                    Step2Shoot = "Halte die Kamera nah an die geschlossene Box, öffne den Deckel langsam mit weißen Handschuhen im warmen Licht.",
                    Step3EditAndPost = "8 Sekunden Länge. Hinweis 'Speichere dir dieses Video für die nächste Einladung 📌' in die Caption setzen.",
                    ReadyCaptionAndTags = "Geld ist nach zwei Wochen ausgegeben – ein persönliches Unikat aus unserer Manufaktur bleibt ein Leben lang. 🤍✨ Speichere dir dieses Video für die nächste Hochzeit, Taufe oder den nächsten Jahrestag! 📌\n\n👉 In 2 Minuten im 3D-Konfigurator gestalten: {SLUG}\n\n#hochzeitsgeschenk #jahrestagsgeschenk #besseralsgeld #geschenkidee #erinnerungsstück",
                    ProductVariantIndex = 0
                },
                new ViralConceptIdeaDto
                {
                    Rank = 15,
                    CategoryKey = "launch",
                    CategoryLabel = "✂️ Schleife & Launch",
                    Title = "Der 60-Sekunden Last-Minute-Retter (Gutschein & Vorab-Freude)",
                    DurationLabel = "8 Sek.",
                    EffortLabel = "4 Min. Dreh",
                    ViewsBenchmark = "1,1M–3,8M",
                    ShortSongBadge = "Sabrina Carpenter – Please Please Please (Instrumental)",
                    TrendingSongs = "TikTok & Reels #1: Sabrina Carpenter – Please Please Please (Instrumental)   |   #2: French Cafe Accordion / Upbeat Piano",
                    ReferenceTrendUrl = "https://www.tiktok.com/search?q=last%20minute%20personalized%20gift%20idea%20saved%20me",
                    OnScreenHook = "Wenn du morgen ein Geschenk brauchst, aber trotzdem etwas 100 % Persönliches schenken willst 🎁⚡",
                    StructureBreakdown = "• Sek. 0,0–3,0: Blick auf den Kalender / die Uhr ('Morgen ist der Ehrentag!').\n• Sek. 3,0–8,0: Weiße Handschuhe präsentieren den edlen Mein-Seelenfunke Geschenkgutschein neben einem funkelnden {PRODUKT} und dem 3D-Konfigurator am Handy.",
                    WhyItGoesViral = "Rettet alle Spätkäufer nach dem Versand-Annahmeschluss vor Feiertagen und bringt 100 % Sofort-Umsatz ohne Versandrisiko.",
                    Step1Setup = "Edler Seelenfunke-Gutschein (PDF/Karte) · Smartphone mit /gutschein Seite · {PRODUKT} als Deko daneben.",
                    Step2Shoot = "Zeige 3 Sekunden die Gutschein-Auswahl am Handy und 5 Sekunden die edle Übergabe mit {PRODUKT} im Hintergrund.",
                    Step3EditAndPost = "Besonders an den letzten 4 Tagen vor einem Feiertag (nach Versand-Cutoff!) posten. Link auf /gutschein.",
                    ReadyCaptionAndTags = "Zu spät für den Paketversand dran? Kein Stress! Mit unserem Seelenfunke-Geschenkgutschein verschenkst du heute sofort die Vorfreude – und der Beschenkte gestaltet sich sein Unikat selbst in 3D! 🎁✨\n\n👉 Sofort als PDF-Download oder edle Karte sichern: /gutschein\n\n#lastminutegeschenk #geschenkgutschein #geschenkretter #meinseelenfunke",
                    ProductVariantIndex = 6
                }
            };
        }

        // =========================================================================
        // PANE 1: 14-TAGE DREHPLAN (OHNE NACHDENKEN UMSETZEN)
        // =========================================================================
        private void RenderMarketingPlanPane()
        {
            if (MktPlanListPanel == null) return;
            MktPlanListPanel.Children.Clear();

            var recipes = _mktVault.DailyRecipes.AsEnumerable();
            if (!string.IsNullOrEmpty(_marketingSearchQuery))
            {
                recipes = recipes.Where(r =>
                    r.Title.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase) ||
                    r.ProductName.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase) ||
                    r.CategoryBadge.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase));
            }

            foreach (var r in recipes)
            {
                bool isSelected = _selectedPlanRecipe != null && _selectedPlanRecipe.DayNumber == r.DayNumber;
                bool isCurrentHero = _mktVault.DailyRecipes[_currentDailyRecipeIndex].DayNumber == r.DayNumber;
                bool isDone = _mktVault.CompletedRecipeDays.Contains(r.DayNumber);

                var card = new Border
                {
                    Background = BrushFromHex(isSelected ? "#26231F" : "#141720"),
                    BorderBrush = isSelected
                        ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059"))
                        : BrushFromHex(isCurrentHero ? "#4D3D1E" : "#222634"),
                    BorderThickness = new Thickness(isSelected ? 1.5 : 1),
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(12, 10, 12, 10),
                    Margin = new Thickness(0, 0, 0, 7),
                    Cursor = Cursors.Hand
                };

                var sp = new StackPanel();
                var topGrid = new Grid();
                topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var badgeRow = new StackPanel { Orientation = Orientation.Horizontal };
                badgeRow.Children.Add(new TextBlock
                {
                    Text = $"TAG {r.DayNumber:00} · {r.WeekdayLabel.ToUpperInvariant()}",
                    Foreground = BrushFromHex("#D4AF37"),
                    FontSize = 10.5,
                    FontWeight = FontWeights.Bold
                });
                if (isCurrentHero)
                {
                    badgeRow.Children.Add(new TextBlock
                    {
                        Text = "  ★ HEUTE AKTIV",
                        Foreground = BrushFromHex("#D4AF37"),
                        FontSize = 10,
                        FontWeight = FontWeights.Bold
                    });
                }
                topGrid.Children.Add(badgeRow);

                var rightTxt = new TextBlock
                {
                    Text = isDone ? $"✓ {r.DurationLabel}" : r.DurationLabel,
                    Foreground = BrushFromHex(isDone ? "#34D399" : "#9CA3AF"),
                    FontSize = 10.5,
                    FontWeight = FontWeights.Bold
                };
                Grid.SetColumn(rightTxt, 1);
                topGrid.Children.Add(rightTxt);
                sp.Children.Add(topGrid);

                sp.Children.Add(new TextBlock
                {
                    Text = r.Title,
                    Foreground = BrushFromHex("#F9FAFB"),
                    FontSize = 12.5,
                    FontWeight = FontWeights.Bold,
                    Margin = new Thickness(0, 3, 0, 0),
                    TextWrapping = TextWrapping.Wrap
                });

                card.ToolTip = $"{r.ProductName} · {r.CategoryBadge}";
                card.Child = sp;
                int capturedDay = r.DayNumber;
                card.MouseLeftButtonUp += (s, e) =>
                {
                    PlaySoundNav();
                    _selectedPlanRecipe = _mktVault.DailyRecipes.FirstOrDefault(x => x.DayNumber == capturedDay);
                    RenderMarketingPlanPane();
                };

                MktPlanListPanel.Children.Add(card);
            }

            RenderSelectedPlanRecipeDetail();
        }

        private void RenderSelectedPlanRecipeDetail()
        {
            var r = _selectedPlanRecipe ?? _mktVault.DailyRecipes.FirstOrDefault();
            if (r == null || MktPlanDetailTitleTxt == null) return;

            var recTrack = GetRecommendedTrackForDailyRecipe(r);

            MktPlanDetailBadgeTxt.Text = $"RUNDE {_mktVault.CurrentRound} · TAG {r.DayNumber:00} · {r.WeekdayLabel.ToUpperInvariant()} · {r.CategoryBadge.ToUpperInvariant()} ({r.DurationLabel})";
            MktPlanDetailTitleTxt.Text = r.Title;
            MktPlanDetailProductTxt.Text = r.ProductName;
            MktPlanDetailTemplateTxt.Text = r.CapCutTemplate;
            MktPlanDetailLandingTxt.Text = r.LandingPageSlug;
            MktPlanDetailHookTxt.Text = $"„{r.OnScreenHookText}“";
            if (MktPlanDetailSoundRecTxt != null)
            {
                MktPlanDetailSoundRecTxt.Text = $"🎵 {recTrack.Title} ({recTrack.Bpm} BPM · {recTrack.MusicalKey} · {recTrack.DurationFormatted})";
                MktPlanDetailSoundRecTxt.Foreground = BrushFromHex("#D4AF37");
            }
            MktPlanDetailAudioTxt.Text = $"{r.AudioDirection}  ·  {recTrack.DropTimingLabel}";
            MktPlanDetailStep1Txt.Text = r.Step1Setup;
            MktPlanDetailStep2Txt.Text = r.Step2Hook;
            MktPlanDetailStep3Txt.Text = r.Step3Clips;
            MktPlanDetailStep4Txt.Text = r.Step4Edit;
            MktPlanDetailCaptionBox.Text = r.ReadyCaption;
            UpdateMarketingMusicPreviewButtonsUI();
        }

        private void MktSetSelectedAsToday_Click(object sender, RoutedEventArgs e)
        {
            if (_selectedPlanRecipe == null) return;
            int idx = _mktVault.DailyRecipes.FindIndex(x => x.DayNumber == _selectedPlanRecipe.DayNumber);
            if (idx >= 0)
            {
                PlaySoundTaskDone();
                _currentDailyRecipeIndex = idx;
                SaveMarketingVaultOffline();
                RenderDailyHeroBanner();
                RenderMarketingPlanPane();
            }
        }

        private void MktCopySelectedPlanCaption_Click(object sender, RoutedEventArgs e)
        {
            if (MktPlanDetailCaptionBox == null) return;
            Clipboard.SetText(MktPlanDetailCaptionBox.Text ?? "");
            PlaySoundActionPop();
            if (sender is Button btn)
            {
                string orig = btn.Content?.ToString() ?? "📋 Caption kopieren";
                btn.Content = "✓ Kopiert!";
                Task.Delay(1400).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
            }
        }

        // =========================================================================
        // PANE 8: EIGENER SEELENFUNKE MUSIKKATALOG & ALBUM (12 RECHTSFREIE TRACKS)
        // + DYNAMISCHE SOUND-EMPFEHLUNG IM FOKUS-MODUS, DREHPLAN & VIRAL-DROPDOWN
        // =========================================================================
        private void EnsureMarketingMusicInitialized()
        {
            if (_mktVault.MusicCatalog == null || _mktVault.MusicCatalog.Count < 12)
            {
                _mktVault.MusicCatalog = BuildDefaultMarketingMusicCatalog();
            }
        }

        private MarketingMusicTrackDto GetRecommendedTrackForDailyRecipe(DailyShootRecipeDto? r)
        {
            EnsureMarketingMusicInitialized();
            var cat = _mktVault.MusicCatalog;
            if (cat.Count == 0) return new MarketingMusicTrackDto { Title = "Golden Shimmer Drop (Viral Reveal)", Bpm = 92, MusicalKey = "D-Moll", DurationSeconds = 72 };
            if (r == null) return cat[0];

            if (!string.IsNullOrWhiteSpace(r.RecommendedMusicTrackId))
            {
                var explicitMatch = cat.FirstOrDefault(x => string.Equals(x.Id, r.RecommendedMusicTrackId, StringComparison.OrdinalIgnoreCase));
                if (explicitMatch != null) return explicitMatch;
            }

            string mappedId = r.DayNumber switch
            {
                1 => "sf_mkt_01",  // Goldenes Seelen-Herz White-Glove ASMR -> Golden Shimmer Drop (92 BPM, D-Moll)
                2 => "sf_mkt_02",  // Seelen Kristall Licht-Reveal im Dunkeln -> Lights-Off Crystal (84 BPM, A-Moll)
                3 => "sf_mkt_04",  // Gründerin Perfektionismus-Check -> Founder Trust Piano (74 BPM, D-Moll)
                4 => "sf_mkt_08",  // Seelen-Pfote Laser-ASMR -> Schiefer, Glas & Gold Manufaktur (82 BPM, A-Moll)
                5 => "sf_mkt_07",  // Weizenglas RA3 Rotation -> Action-Cam White-Glove POV (86 BPM, D-Moll)
                6 => "sf_mkt_05",  // Omas Handschrift Story -> Omas Handschrift Elegie (68 BPM, A-Moll)
                7 => "sf_mkt_03",  // 3D-Konfigurator Live-Beweis -> Velvet Impulse Beat (90 BPM, D-Dorisch)
                8 => "sf_mkt_07",  // Westpack White-Glove Ritual -> Action-Cam White-Glove POV (86 BPM, D-Moll)
                9 => "sf_mkt_11",  // Seelen-Talisman Koordinaten -> Kerzenlicht & Kristall (62 BPM, A-Moll)
                10 => "sf_mkt_06", // UV-Laser Magie im Glas erklärt -> Atelier Herzschlag (72 BPM, D-Moll)
                11 => "sf_mkt_09", // Kunden-Bestellung verpacken -> Pack With Me Gifhorn (88 BPM, D-Dorisch)
                12 => "sf_mkt_03", // Team Gold vs. Team Kristall -> Velvet Impulse Beat (90 BPM, D-Dorisch)
                13 => "sf_mkt_10", // Hochzeits- & Jahrestags-Edition -> Mystisches Atelier Login-Theme (60 BPM, D-Dorisch)
                14 => "sf_mkt_12", // Sonntagsschicht in Gifhorn -> Sonntagsschicht Ruhe (64 BPM, D-Moll)
                _ => ""
            };

            if (string.IsNullOrEmpty(mappedId))
            {
                string badge = (r.CategoryBadge + " " + r.Title).ToLowerInvariant();
                if (badge.Contains("story") || badge.Contains("erinnerung") || badge.Contains("oma")) mappedId = "sf_mkt_05";
                else if (badge.Contains("gründer") || badge.Contains("check") || badge.Contains("talk")) mappedId = "sf_mkt_04";
                else if (badge.Contains("verpack") || badge.Contains("box") || badge.Contains("handschuh")) mappedId = "sf_mkt_07";
                else if (badge.Contains("kristall") || badge.Contains("dunkel") || badge.Contains("licht")) mappedId = "sf_mkt_02";
                else mappedId = cat[(Math.Max(1, r.DayNumber) - 1) % cat.Count].Id;
            }

            r.RecommendedMusicTrackId = mappedId;
            return cat.FirstOrDefault(x => x.Id == mappedId) ?? cat[0];
        }

        private MarketingMusicTrackDto GetRecommendedTrackForViralIdea(ViralConceptIdeaDto? idea)
        {
            EnsureMarketingMusicInitialized();
            var cat = _mktVault.MusicCatalog;
            if (cat.Count == 0) return new MarketingMusicTrackDto { Title = "Golden Shimmer Drop (Viral Reveal)", Bpm = 92, MusicalKey = "D-Moll", DurationSeconds = 72 };
            if (idea == null) return cat[0];

            if (!string.IsNullOrWhiteSpace(idea.RecommendedMusicTrackId))
            {
                var explicitMatch = cat.FirstOrDefault(x => string.Equals(x.Id, idea.RecommendedMusicTrackId, StringComparison.OrdinalIgnoreCase));
                if (explicitMatch != null) return explicitMatch;
            }

            string mappedId = idea.Rank switch
            {
                1 => "sf_mkt_01",  // Handy in Schleife durchschneiden -> Golden Shimmer Drop (92 BPM, D-Moll)
                2 => "sf_mkt_07",  // 8-Sek. Westpack Schachtel-Klacken -> Action-Cam White-Glove POV (86 BPM, D-Moll)
                3 => "sf_mkt_02",  // Licht-Aus im Dunkeln 3D-Kristall -> Lights-Off Crystal (84 BPM, A-Moll)
                4 => "sf_mkt_05",  // Letzte Handschrift von Oma -> Omas Handschrift Elegie (68 BPM, A-Moll)
                5 => "sf_mkt_02",  // Hunde-Erinnerung im K9-Kristall -> Lights-Off Crystal (84 BPM, A-Moll)
                6 => "sf_mkt_08",  // Vorher/Nachher Schiefer & Glas -> Schiefer, Glas & Gold Manufaktur (82 BPM, A-Moll)
                7 => "sf_mkt_06",  // Qualitätskontrolle mit Lupe -> Atelier Herzschlag (72 BPM, D-Moll)
                8 => "sf_mkt_01",  // UV-Laser mitten ins Glas -> Golden Shimmer Drop (92 BPM, D-Moll)
                9 => "sf_mkt_04",  // Gründerin vor der Kamera -> Founder Trust Piano (74 BPM, D-Moll)
                10 => "sf_mkt_03", // Team 1 Gold vs. Team 2 Kristall -> Velvet Impulse Beat (90 BPM, D-Dorisch)
                11 => "sf_mkt_11", // Geheime Botschaft auf der Rückseite -> Kerzenlicht & Kristall (62 BPM, A-Moll)
                12 => "sf_mkt_08", // Weizenglas im RA3 Rotationsaufsatz -> Schiefer, Glas & Gold Manufaktur (82 BPM, A-Moll)
                13 => "sf_mkt_09", // Pack an Order with Me -> Pack With Me Gifhorn (88 BPM, D-Dorisch)
                14 => "sf_mkt_10", // Besser als Geld im Umschlag -> Mystisches Atelier Login-Theme (60 BPM, D-Dorisch)
                15 => "sf_mkt_03", // 60-Sek. Last-Minute-Retter Gutschein -> Velvet Impulse Beat (90 BPM, D-Dorisch)
                _ => cat[(Math.Max(1, idea.Rank) - 1) % cat.Count].Id
            };

            idea.RecommendedMusicTrackId = mappedId;
            return cat.FirstOrDefault(x => x.Id == mappedId) ?? cat[0];
        }

        private string? ResolveMarketingMusicFilePath(MarketingMusicTrackDto track)
        {
            if (track == null || string.IsNullOrWhiteSpace(track.FileName)) return null;

            string baseDir = AppDomain.CurrentDomain.BaseDirectory;
            string localAppData = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Funkenwerk", "MarketingMusic");

            string[] candidates =
            {
                Path.Combine(baseDir, "Assets", "MarketingMusic", track.FileName),
                Path.Combine(localAppData, track.FileName),
                Path.Combine(baseDir, track.FileName),
                @"\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke\funkenwerk\Assets\MarketingMusic\" + track.FileName
            };

            foreach (var p in candidates)
            {
                try
                {
                    if (File.Exists(p)) return p;
                }
                catch { }
            }
            return null;
        }

        private void MktMusicPreviewPlayer_MediaEnded(object? sender, EventArgs e)
        {
            Dispatcher.Invoke(() =>
            {
                _isMktMusicPreviewPlaying = false;
                _currentlyPlayingMktTrackId = "";
                UpdateMarketingMusicPreviewButtonsUI();
                if (_activeMarketingSubTab == "music") RenderMarketingMusicPane();
            });
        }

        private void ToggleMarketingMusicTrackPreview(MarketingMusicTrackDto? track)
        {
            if (track == null) return;

            if (_isMktMusicPreviewPlaying && string.Equals(_currentlyPlayingMktTrackId, track.Id, StringComparison.OrdinalIgnoreCase))
            {
                StopMarketingMusicPreview();
                PlaySoundNav();
                return;
            }

            string? path = ResolveMarketingMusicFilePath(track);
            if (string.IsNullOrEmpty(path) || !File.Exists(path))
            {
                MessageBox.Show($"Die Audiodatei '{track.FileName}' wurde nicht gefunden.", "Musik-Vorschau", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            try
            {
                // Pause main music player if it is playing so the user hears the marketing track cleanly
                if (_isMusicPlaying)
                {
                    _mediaPlayer.Pause();
                    _isMusicPlaying = false;
                    _musicTimer.Stop();
                    if (MusicPlayPauseBtn != null) MusicPlayPauseBtn.Content = "▶";
                }

                _mktMusicPreviewPlayer.Stop();
                _mktMusicPreviewPlayer.Open(new Uri(path, UriKind.Absolute));
                _mktMusicPreviewPlayer.Volume = Math.Clamp(_layoutSettings.SoundVolume / 100.0, 0.25, 1.0);
                _mktMusicPreviewPlayer.Play();

                _currentlyPlayingMktTrackId = track.Id;
                _isMktMusicPreviewPlaying = true;
                UpdateMarketingMusicPreviewButtonsUI();
                if (_activeMarketingSubTab == "music") RenderMarketingMusicPane();
            }
            catch (Exception ex)
            {
                MessageBox.Show($"Fehler beim Abspielen: {ex.Message}", "Musik-Vorschau", MessageBoxButton.OK, MessageBoxImage.Warning);
            }
        }

        private void StopMarketingMusicPreview()
        {
            try
            {
                _mktMusicPreviewPlayer.Stop();
            }
            catch { }
            _isMktMusicPreviewPlaying = false;
            _currentlyPlayingMktTrackId = "";
            UpdateMarketingMusicPreviewButtonsUI();
        }

        private void UpdateMarketingMusicPreviewButtonsUI()
        {
            var activeDailyRecipe = (_mktVault.DailyRecipes != null && _mktVault.DailyRecipes.Count > 0)
                ? _mktVault.DailyRecipes[Math.Clamp(_currentDailyRecipeIndex, 0, _mktVault.DailyRecipes.Count - 1)]
                : null;
            var planRecipe = _selectedPlanRecipe ?? activeDailyRecipe;
            var focusPlanTrack = GetRecommendedTrackForDailyRecipe(activeDailyRecipe);
            var detailPlanTrack = GetRecommendedTrackForDailyRecipe(planRecipe);

            var activeViralIdea = (_mktVault.ViralIdeas != null && _mktVault.ViralIdeas.Count > 0)
                ? _mktVault.ViralIdeas[Math.Clamp(_currentViralIdeaIndex, 0, _mktVault.ViralIdeas.Count - 1)]
                : null;
            var detailViralIdea = _selectedViralIdea ?? activeViralIdea;
            var focusViralTrack = GetRecommendedTrackForViralIdea(activeViralIdea);
            var detailViralTrack = GetRecommendedTrackForViralIdea(detailViralIdea);

            void StylePlayBtn(Button? btn, string trackId, string defaultLabel)
            {
                if (btn == null) return;
                bool playingThis = _isMktMusicPreviewPlaying && string.Equals(_currentlyPlayingMktTrackId, trackId, StringComparison.OrdinalIgnoreCase);
                btn.Content = playingThis ? "⏹ Stopp" : defaultLabel;
                btn.Background = playingThis
                    ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059"))
                    : BrushFromHex("#1E222D");
                btn.Foreground = playingThis
                    ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#111827"))
                    : BrushFromHex("#F9FAFB");
                btn.BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059"));
                btn.BorderThickness = new Thickness(1);
            }

            StylePlayBtn(MktFocusSoundPlayBtn, focusPlanTrack.Id, "▶ Anhören");
            StylePlayBtn(MktPlanSoundPlayBtn, detailPlanTrack.Id, "▶ Anhören");
            StylePlayBtn(MktFocusViralSoundPlayBtn, focusViralTrack.Id, "▶ Anhören");
            StylePlayBtn(MktViralSoundPlayBtn, detailViralTrack.Id, "▶ Anhören");

            if (_selectedMusicTrack != null)
            {
                StylePlayBtn(MktMusicDetailPlayBtn, _selectedMusicTrack.Id, "▶ Song anhören");
            }
        }

        private void PromptDownloadMarketingTrack(MarketingMusicTrackDto? track, Button? feedbackBtn = null)
        {
            if (track == null) return;
            string? srcPath = ResolveMarketingMusicFilePath(track);
            if (string.IsNullOrEmpty(srcPath) || !File.Exists(srcPath))
            {
                MessageBox.Show($"Die Audiodatei '{track.FileName}' wurde nicht gefunden.", "Song-Download", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            PlaySoundActionPop();
            var dlg = new Microsoft.Win32.SaveFileDialog
            {
                Title = $"Rechtsfreien Seelenfunke-Song speichern: {track.Title}",
                FileName = !string.IsNullOrWhiteSpace(track.ExportFileName) ? track.ExportFileName : track.FileName,
                DefaultExt = ".m4a",
                Filter = "M4A Studio-Audio (*.m4a)|*.m4a|Alle Dateien (*.*)|*.*"
            };

            if (dlg.ShowDialog() == true && !string.IsNullOrWhiteSpace(dlg.FileName))
            {
                try
                {
                    File.Copy(srcPath, dlg.FileName, overwrite: true);
                    PlaySoundTaskDone();
                    if (feedbackBtn != null)
                    {
                        string orig = feedbackBtn.Content?.ToString() ?? "⬇ Download";
                        feedbackBtn.Content = "✓ Gespeichert!";
                        _ = Task.Delay(1600).ContinueWith(_ => Dispatcher.Invoke(() => feedbackBtn.Content = orig));
                    }
                }
                catch (Exception ex)
                {
                    MessageBox.Show($"Fehler beim Speichern: {ex.Message}", "Song-Download", MessageBoxButton.OK, MessageBoxImage.Error);
                }
            }
        }

        private void MktPlayRecommendedPlanTrack_Click(object sender, RoutedEventArgs e)
        {
            var r = _mktVault.IsFocusMode
                ? (_mktVault.DailyRecipes.Count > 0 ? _mktVault.DailyRecipes[Math.Clamp(_currentDailyRecipeIndex, 0, _mktVault.DailyRecipes.Count - 1)] : null)
                : (_selectedPlanRecipe ?? _mktVault.DailyRecipes.FirstOrDefault());
            var track = GetRecommendedTrackForDailyRecipe(r);
            ToggleMarketingMusicTrackPreview(track);
        }

        private void MktDownloadRecommendedPlanTrack_Click(object sender, RoutedEventArgs e)
        {
            var r = _mktVault.IsFocusMode
                ? (_mktVault.DailyRecipes.Count > 0 ? _mktVault.DailyRecipes[Math.Clamp(_currentDailyRecipeIndex, 0, _mktVault.DailyRecipes.Count - 1)] : null)
                : (_selectedPlanRecipe ?? _mktVault.DailyRecipes.FirstOrDefault());
            var track = GetRecommendedTrackForDailyRecipe(r);
            PromptDownloadMarketingTrack(track, sender as Button);
        }

        private void MktRotateRecommendedPlanTrack_Click(object sender, RoutedEventArgs e)
        {
            EnsureMarketingMusicInitialized();
            var cat = _mktVault.MusicCatalog;
            if (cat.Count == 0) return;

            var r = _mktVault.IsFocusMode
                ? (_mktVault.DailyRecipes.Count > 0 ? _mktVault.DailyRecipes[Math.Clamp(_currentDailyRecipeIndex, 0, _mktVault.DailyRecipes.Count - 1)] : null)
                : (_selectedPlanRecipe ?? _mktVault.DailyRecipes.FirstOrDefault());
            if (r == null) return;

            PlaySoundActionPop();
            var current = GetRecommendedTrackForDailyRecipe(r);
            int idx = cat.FindIndex(x => x.Id == current.Id);
            var next = cat[(idx + 1) % cat.Count];
            r.RecommendedMusicTrackId = next.Id;

            SaveMarketingVaultOffline();
            RenderDailyHeroBanner();
            if (_activeMarketingSubTab == "plan") RenderMarketingPlanPane();
        }

        private void MktPlayRecommendedViralTrack_Click(object sender, RoutedEventArgs e)
        {
            EnsureViralIdeasInitialized();
            var idea = _mktVault.IsFocusMode
                ? (_mktVault.ViralIdeas.Count > 0 ? _mktVault.ViralIdeas[Math.Clamp(_currentViralIdeaIndex, 0, _mktVault.ViralIdeas.Count - 1)] : null)
                : (_selectedViralIdea ?? _mktVault.ViralIdeas.FirstOrDefault());
            var track = GetRecommendedTrackForViralIdea(idea);
            ToggleMarketingMusicTrackPreview(track);
        }

        private void MktDownloadRecommendedViralTrack_Click(object sender, RoutedEventArgs e)
        {
            EnsureViralIdeasInitialized();
            var idea = _mktVault.IsFocusMode
                ? (_mktVault.ViralIdeas.Count > 0 ? _mktVault.ViralIdeas[Math.Clamp(_currentViralIdeaIndex, 0, _mktVault.ViralIdeas.Count - 1)] : null)
                : (_selectedViralIdea ?? _mktVault.ViralIdeas.FirstOrDefault());
            var track = GetRecommendedTrackForViralIdea(idea);
            PromptDownloadMarketingTrack(track, sender as Button);
        }

        private void MktRotateRecommendedViralTrack_Click(object sender, RoutedEventArgs e)
        {
            EnsureMarketingMusicInitialized();
            EnsureViralIdeasInitialized();
            var cat = _mktVault.MusicCatalog;
            if (cat.Count == 0) return;

            var idea = _mktVault.IsFocusMode
                ? (_mktVault.ViralIdeas.Count > 0 ? _mktVault.ViralIdeas[Math.Clamp(_currentViralIdeaIndex, 0, _mktVault.ViralIdeas.Count - 1)] : null)
                : (_selectedViralIdea ?? _mktVault.ViralIdeas.FirstOrDefault());
            if (idea == null) return;

            PlaySoundActionPop();
            var current = GetRecommendedTrackForViralIdea(idea);
            int idx = cat.FindIndex(x => x.Id == current.Id);
            var next = cat[(idx + 1) % cat.Count];
            idea.RecommendedMusicTrackId = next.Id;

            SaveMarketingVaultOffline();
            RenderFocusViralDropdown();
            if (_activeMarketingSubTab == "viral") RenderMarketingViralPane();
        }

        private void MktMusicCategoryFilter_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string cat)
            {
                PlaySoundNav();
                _activeMusicCategoryFilter = cat;
                StyleMusicCategoryButtons();
                RenderMarketingMusicPane();
            }
        }

        private void StyleMusicCategoryButtons()
        {
            StyleSubTabButton(MktMusicCatAllBtn, _activeMusicCategoryFilter == "all");
            StyleSubTabButton(MktMusicCatViralBtn, _activeMusicCategoryFilter == "viral");
            StyleSubTabButton(MktMusicCatCameraBtn, _activeMusicCategoryFilter == "camera");
            StyleSubTabButton(MktMusicCatWerkstattBtn, _activeMusicCategoryFilter == "werkstatt");
            StyleSubTabButton(MktMusicCatAtelierBtn, _activeMusicCategoryFilter == "atelier");
        }

        private void RenderMarketingMusicPane()
        {
            EnsureMarketingMusicInitialized();
            if (MktMusicListPanel == null) return;
            MktMusicListPanel.Children.Clear();

            var tracks = _mktVault.MusicCatalog.AsEnumerable();
            if (_activeMusicCategoryFilter != "all")
            {
                tracks = tracks.Where(t => t.CategoryKey == _activeMusicCategoryFilter);
            }
            if (!string.IsNullOrEmpty(_marketingSearchQuery))
            {
                tracks = tracks.Where(t =>
                    t.Title.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase) ||
                    t.Subtitle.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase) ||
                    t.CategoryLabel.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase) ||
                    t.ScenarioGuide.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase));
            }

            var filtered = tracks.ToList();
            if (_selectedMusicTrack == null || !filtered.Any(x => x.Id == _selectedMusicTrack.Id))
            {
                _selectedMusicTrack = filtered.FirstOrDefault() ?? _mktVault.MusicCatalog.FirstOrDefault();
            }

            var todayRecipe = (_mktVault.DailyRecipes != null && _mktVault.DailyRecipes.Count > 0)
                ? _mktVault.DailyRecipes[Math.Clamp(_currentDailyRecipeIndex, 0, _mktVault.DailyRecipes.Count - 1)]
                : null;
            string todayTrackId = GetRecommendedTrackForDailyRecipe(todayRecipe).Id;

            foreach (var track in filtered)
            {
                bool isSelected = _selectedMusicTrack != null && _selectedMusicTrack.Id == track.Id;
                bool isPlaying = _isMktMusicPreviewPlaying && string.Equals(_currentlyPlayingMktTrackId, track.Id, StringComparison.OrdinalIgnoreCase);
                bool isTodayRec = string.Equals(todayTrackId, track.Id, StringComparison.OrdinalIgnoreCase);

                var card = new Border
                {
                    Background = BrushFromHex(isSelected ? "#26231F" : "#141720"),
                    BorderBrush = (isSelected || isPlaying)
                        ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059"))
                        : BrushFromHex(isTodayRec ? "#4D3D1E" : "#222634"),
                    BorderThickness = new Thickness((isSelected || isPlaying) ? 1.5 : 1),
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(12, 10, 12, 10),
                    Margin = new Thickness(0, 0, 0, 7),
                    Cursor = Cursors.Hand
                };

                var outerGrid = new Grid();
                outerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                outerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                outerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var playQuickBtn = new Button
                {
                    Content = isPlaying ? "⏹" : "▶",
                    Width = 34,
                    Height = 34,
                    Background = isPlaying
                        ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059"))
                        : BrushFromHex("#1E222D"),
                    Foreground = isPlaying
                        ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#111827"))
                        : BrushFromHex("#D4AF37"),
                    BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059")),
                    BorderThickness = new Thickness(1),
                    FontWeight = FontWeights.Bold,
                    FontSize = 13,
                    Margin = new Thickness(0, 0, 10, 0),
                    VerticalAlignment = VerticalAlignment.Center,
                    Cursor = Cursors.Hand,
                    ToolTip = isPlaying ? "Vorschau stoppen" : "Song direkt anhören"
                };
                var capturedTrack = track;
                playQuickBtn.Click += (sObj, ev) =>
                {
                    ev.Handled = true;
                    _selectedMusicTrack = capturedTrack;
                    ToggleMarketingMusicTrackPreview(capturedTrack);
                };
                Grid.SetColumn(playQuickBtn, 0);
                outerGrid.Children.Add(playQuickBtn);

                var midStack = new StackPanel { VerticalAlignment = VerticalAlignment.Center };
                var badgeRow = new StackPanel { Orientation = Orientation.Horizontal };
                badgeRow.Children.Add(new TextBlock
                {
                    Text = $"#{track.TrackNumber:00} · {track.CategoryLabel}",
                    Foreground = BrushFromHex("#D4AF37"),
                    FontSize = 10,
                    FontWeight = FontWeights.Bold
                });
                if (isTodayRec)
                {
                    badgeRow.Children.Add(new TextBlock
                    {
                        Text = "  ★ HEUTE EMPFOHLEN",
                        Foreground = BrushFromHex("#34D399"),
                        FontSize = 9.5,
                        FontWeight = FontWeights.Bold
                    });
                }
                midStack.Children.Add(badgeRow);

                midStack.Children.Add(new TextBlock
                {
                    Text = track.Title,
                    Foreground = BrushFromHex("#F9FAFB"),
                    FontSize = 13,
                    FontWeight = FontWeights.Bold,
                    Margin = new Thickness(0, 2, 0, 2),
                    TextTrimming = TextTrimming.CharacterEllipsis
                });

                midStack.Children.Add(new TextBlock
                {
                    Text = $"{track.Bpm} BPM · {track.MusicalKey} · {track.DurationFormatted}",
                    Foreground = BrushFromHex("#9CA3AF"),
                    FontSize = 10.5
                });
                Grid.SetColumn(midStack, 1);
                outerGrid.Children.Add(midStack);

                var dlQuickBtn = new Button
                {
                    Content = "⬇",
                    Width = 30,
                    Height = 30,
                    Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059")),
                    Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#111827")),
                    BorderThickness = new Thickness(0),
                    FontWeight = FontWeights.Bold,
                    FontSize = 12,
                    Margin = new Thickness(8, 0, 0, 0),
                    VerticalAlignment = VerticalAlignment.Center,
                    Cursor = Cursors.Hand,
                    ToolTip = "Song als .m4a herunterladen"
                };
                dlQuickBtn.Click += (sObj, ev) =>
                {
                    ev.Handled = true;
                    PromptDownloadMarketingTrack(capturedTrack, dlQuickBtn);
                };
                Grid.SetColumn(dlQuickBtn, 2);
                outerGrid.Children.Add(dlQuickBtn);

                card.Child = outerGrid;
                card.MouseLeftButtonUp += (sObj, ev) =>
                {
                    PlaySoundNav();
                    _selectedMusicTrack = capturedTrack;
                    RenderMarketingMusicPane();
                };

                MktMusicListPanel.Children.Add(card);
            }

            RenderSelectedMusicTrackDetail();
        }

        private void RenderSelectedMusicTrackDetail()
        {
            var t = _selectedMusicTrack ?? _mktVault.MusicCatalog.FirstOrDefault();
            if (t == null || MktMusicDetailTitleTxt == null) return;

            MktMusicDetailCatBadgeTxt.Text = t.CategoryLabel;
            MktMusicDetailTechBadgeTxt.Text = $"{t.Bpm} BPM · {t.MusicalKey} · {t.DurationFormatted} · 48 kHz Stereo AAC (.m4a)";
            MktMusicDetailTitleTxt.Text = $"#{t.TrackNumber:00} {t.Title}";
            MktMusicDetailSubtitleTxt.Text = t.Subtitle;
            MktMusicDetailDropTimingTxt.Text = t.DropTimingLabel;
            MktMusicDetailScenarioTxt.Text = t.ScenarioGuide;
            MktMusicDetailMixGuideTxt.Text = t.MixingGuide;
            MktMusicDetailInstrumentsTxt.Text = t.InstrumentationGuide;
            MktMusicDetailPromptTxt.Text = t.SunoUdioPrompt;
            UpdateMarketingMusicPreviewButtonsUI();
        }

        private void MktPlaySelectedMusicTrack_Click(object sender, RoutedEventArgs e)
        {
            var t = _selectedMusicTrack ?? _mktVault.MusicCatalog.FirstOrDefault();
            ToggleMarketingMusicTrackPreview(t);
        }

        private void MktDownloadSelectedMusicTrack_Click(object sender, RoutedEventArgs e)
        {
            var t = _selectedMusicTrack ?? _mktVault.MusicCatalog.FirstOrDefault();
            PromptDownloadMarketingTrack(t, sender as Button);
        }

        private void MktAssignMusicTrackToToday_Click(object sender, RoutedEventArgs e)
        {
            var t = _selectedMusicTrack ?? _mktVault.MusicCatalog.FirstOrDefault();
            if (t == null) return;

            PlaySoundTaskDone();
            if (_mktVault.DailyRecipes.Count > 0)
            {
                var r = _mktVault.DailyRecipes[Math.Clamp(_currentDailyRecipeIndex, 0, _mktVault.DailyRecipes.Count - 1)];
                r.RecommendedMusicTrackId = t.Id;
            }
            if (_mktVault.ViralIdeas.Count > 0)
            {
                var idea = _mktVault.ViralIdeas[Math.Clamp(_currentViralIdeaIndex, 0, _mktVault.ViralIdeas.Count - 1)];
                idea.RecommendedMusicTrackId = t.Id;
            }

            SaveMarketingVaultOffline();
            RenderDailyHeroBanner();
            RenderFocusViralDropdown();
            RenderMarketingMusicPane();

            if (sender is Button btn)
            {
                string orig = btn.Content?.ToString() ?? "🎯 Für heute nutzen";
                btn.Content = "✓ Für heute gesetzt!";
                _ = Task.Delay(1500).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
            }
        }

        private void MktCopySelectedMusicPrompt_Click(object sender, RoutedEventArgs e)
        {
            var t = _selectedMusicTrack ?? _mktVault.MusicCatalog.FirstOrDefault();
            if (t == null) return;
            Clipboard.SetText(t.SunoUdioPrompt);
            PlaySoundActionPop();
            if (sender is Button btn)
            {
                string orig = btn.Content?.ToString() ?? "📋 Prompt kopieren";
                btn.Content = "✓ Prompt kopiert!";
                _ = Task.Delay(1400).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
            }
        }

        private void MktOpenMusicFolder_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            EnsureMarketingMusicInitialized();
            var first = _mktVault.MusicCatalog.FirstOrDefault();
            string? path = first != null ? ResolveMarketingMusicFilePath(first) : null;
            string folder = !string.IsNullOrEmpty(path)
                ? (Path.GetDirectoryName(path) ?? AppDomain.CurrentDomain.BaseDirectory)
                : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Funkenwerk", "MarketingMusic");

            try
            {
                Directory.CreateDirectory(folder);
                Process.Start(new ProcessStartInfo("explorer.exe", $"\"{folder}\"") { UseShellExecute = true });
            }
            catch { }
        }

        private void MktExportFullAlbum_Click(object sender, RoutedEventArgs e)
        {
            EnsureMarketingMusicInitialized();
            PlaySoundActionPop();

            var dlg = new Microsoft.Win32.OpenFolderDialog
            {
                Title = "Zielordner für das komplette 12-Song Seelenfunke Marketing-Album wählen"
            };

            if (dlg.ShowDialog() == true && !string.IsNullOrWhiteSpace(dlg.FolderName))
            {
                int copied = 0;
                foreach (var t in _mktVault.MusicCatalog)
                {
                    string? src = ResolveMarketingMusicFilePath(t);
                    if (!string.IsNullOrEmpty(src) && File.Exists(src))
                    {
                        string targetName = !string.IsNullOrWhiteSpace(t.ExportFileName) ? t.ExportFileName : t.FileName;
                        string dest = Path.Combine(dlg.FolderName, targetName);
                        try
                        {
                            File.Copy(src, dest, overwrite: true);
                            copied++;
                        }
                        catch { }
                    }
                }

                PlaySoundTaskDone();
                if (sender is Button btn)
                {
                    string orig = btn.Content?.ToString() ?? "📦 Ganzes Album speichern (12 Songs)";
                    btn.Content = $"✓ {copied}/12 Songs exportiert!";
                    _ = Task.Delay(1800).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
                }
            }
        }

        private static List<MarketingMusicTrackDto> BuildDefaultMarketingMusicCatalog()
        {
            return new List<MarketingMusicTrackDto>
            {
                // =========================================================================
                // KATEGORIE 1: VIRALE REELS & SHIMMER-DROPS (3 SONGS)
                // =========================================================================
                new MarketingMusicTrackDto
                {
                    TrackNumber = 1,
                    Id = "sf_mkt_01",
                    FileName = "sf_mkt_01_golden_shimmer_drop.m4a",
                    ExportFileName = "Mein-Seelenfunke_01_Golden-Shimmer-Drop_92BPM_D-Moll.m4a",
                    CategoryKey = "viral",
                    CategoryLabel = "🔥 Virale Reels & Shimmer-Drops",
                    Title = "Golden Shimmer Drop (Viral Reveal)",
                    Subtitle = "Tiefer Sub-Bass-Puls (0–2s) + Kristallglas-Shimmer-Drop exakt bei Sekunde 4,0",
                    Bpm = 92,
                    MusicalKey = "D-Moll (D minor)",
                    DurationSeconds = 72,
                    DropTimingLabel = "⏱️ Sek. 0,0–2,0: Tiefer Herzschlag-Puls · Sek. 4,0: Edler Kristall-Shimmer-Drop (für Laser-Start oder Umdrehen!)",
                    ScenarioGuide = "• Perfekt für deine viralen „White-Glove Laser-Gravur & Reveal“-Videos sowie das „Handy in Schleife durchschneiden“-Video.\n• Sek. 0–4: Zeige die Vorlage / die Schere am Band / den UV-Laser.\n• Sek. 4,0 (exakt auf dem Shimmer-Drop!): Dreh das fertige Gold-Herz oder den Seelen Kristall mit weißen Handschuhen ins Licht.",
                    MixingGuide = "• Sekunde 0–4: Originalton (Laser-Singen oder Scheren-Schnitt) auf 100 % (0 dB), Musik auf -10 dB.\n• Ab Sekunde 4,0 (beim Shimmer-Drop): Musik auf -3 dB hochziehen für sofortige Gänsehaut.",
                    InstrumentationGuide = "• Warmes Filzklavier (Felt Piano in D-Moll) für menschliche Wärme („Seele“) + echte Kristallglas-Harfen-Obertöne („Funke“) + 55 Hz Sub-Bass-Herzschlag.",
                    SunoUdioPrompt = "Viral luxury aesthetic background music, emotional cinematic minimal electronic, warm deep sub bass pulse in first 2 seconds, intimate felt piano in D minor, sparkling crystal glass plucks, subtle slow heartbeat percussion, dark gold aesthetic, mesmerizing build-up with goosebump shimmer drop at 4 seconds, 92 BPM, instrumental, ultra clean mastering"
                },
                new MarketingMusicTrackDto
                {
                    TrackNumber = 2,
                    Id = "sf_mkt_02",
                    FileName = "sf_mkt_02_lights_off_crystal.m4a",
                    ExportFileName = "Mein-Seelenfunke_02_Lights-Off-Crystal_84BPM_A-Moll.m4a",
                    CategoryKey = "viral",
                    CategoryLabel = "🔥 Virale Reels & Shimmer-Drops",
                    Title = "Lights-Off Crystal (3D Licht-Reveal)",
                    Subtitle = "Spannungs-Aufbau im Hellen -> Magischer Glas-Harfen-Drop bei Sekunde 3,5 wenn das Licht ausgeht",
                    Bpm = 84,
                    MusicalKey = "A-Moll (A minor)",
                    DurationSeconds = 75,
                    DropTimingLabel = "⏱️ Sek. 0,0–3,2: Sanfte Spannung · Sek. 3,5: Licht-Aus & Leuchtsockel-Drop mit Kathedralen-Hall",
                    ScenarioGuide = "• Maßgeschneidert für „Der Seelen Kristall“ im abgedunkelten Raum.\n• Sek. 0,0–3,2: Zeige das Handyfoto neben dem unbeleuchteten Kristallblock.\n• Sek. 3,5: Schalte das Raumlicht aus und den LED-Sockel ein – exakt hier öffnet sich die volle Kristall-Harfe.",
                    MixingGuide = "• Lass das echte Klick-Geräusch des Lichtschalters bei Sekunde 3,3 hörbar (0 dB) und lege den Drop von „Lights-Off Crystal“ exakt auf das Aufleuchten.",
                    InstrumentationGuide = "• A-Moll Filzklavier + analoger Cello-Bogen + hochfrequente 528 Hz / 880 Hz Kristallglas-Chimes, die im Kopf sofort mit leuchtendem K9-Kristallglas verknüpft werden.",
                    SunoUdioPrompt = "Cinematic dark luxury crystal reveal soundtrack, A minor, intimate felt piano, warm analog cello swell, dramatic silence at 3.2 seconds followed by magical glowing crystal glass harp chord drop at 3.5 seconds, deep sub bass warmth, cathedral shimmer reverb, 84 BPM, instrumental"
                },
                new MarketingMusicTrackDto
                {
                    TrackNumber = 3,
                    Id = "sf_mkt_03",
                    FileName = "sf_mkt_03_velvet_impulse_beat.m4a",
                    ExportFileName = "Mein-Seelenfunke_03_Velvet-Impulse-Beat_90BPM_D-Dorisch.m4a",
                    CategoryKey = "viral",
                    CategoryLabel = "🔥 Virale Reels & Shimmer-Drops",
                    Title = "Velvet Impulse Beat (Bestseller & 7-Sek. Loops)",
                    Subtitle = "Moderner Boutique-Lounge-Puls für schnelle Schnitte, 3D-Konfigurator-Demos & Bestseller-Duelle",
                    Bpm = 90,
                    MusicalKey = "D-Dorisch (D Dorian)",
                    DurationSeconds = 68,
                    DropTimingLabel = "⏱️ Sofortiger Samt-Puls ab Sek. 0,0 · Akzent-Impulse alle 2,6 Sek. für knackige Schnitt-Wechsel",
                    ScenarioGuide = "• Ideal für kurze 7–12 Sek. Videos: „Team 1 (Gold-Herz) oder Team 2 (Seelen Kristall)?“, 3D-Konfigurator-Beweis am Smartphone oder Last-Minute-Gutschein-Reels.",
                    MixingGuide = "• Musik durchgehend auf -5 dB legen; jeder Beat-Impuls markiert den perfekten Moment für den nächsten Kamera-Winkel.",
                    InstrumentationGuide = "• Samtiger Sub-Bass-Groove (90 BPM) + warmes Rhodes/Felt-Piano in D-Dorisch + dezente goldene Glas-Plucks ohne aufdringliche Drums.",
                    SunoUdioPrompt = "Sophisticated dark gold boutique lounge beat, D Dorian, warm velvet sub bass groove, muted felt piano chords, delicate crystal glass plucks, modern luxury jewelry reel soundtrack, clean rhythmic pulse for fast cuts, 90 BPM, instrumental"
                },

                // =========================================================================
                // KATEGORIE 2: VOR DER KAMERA & STORYTELLING (3 SONGS)
                // =========================================================================
                new MarketingMusicTrackDto
                {
                    TrackNumber = 4,
                    Id = "sf_mkt_04",
                    FileName = "sf_mkt_04_founder_trust_piano.m4a",
                    ExportFileName = "Mein-Seelenfunke_04_Founder-Trust-Piano_74BPM_D-Moll.m4a",
                    CategoryKey = "camera",
                    CategoryLabel = "🎙️ Vor der Kamera & Storytelling",
                    Title = "Founder Trust Piano (Vor der Kamera & Voiceover)",
                    Subtitle = "Warmer Neoklassik-Teppich mit Frequenz-Lücke in den Mitten – lässt deiner Stimme 100 % Raum",
                    Bpm = 74,
                    MusicalKey = "D-Moll (D minor)",
                    DurationSeconds = 84,
                    DropTimingLabel = "⏱️ Durchgehend ruhiger, vertrauenswürdiger Fluss (74 BPM) – übertönt niemals deine Sprechstimme",
                    ScenarioGuide = "• Dein Standard-Background-Song, wenn du selbst vor der Kamera sprichst („Hallo aus unserer Manufaktur in Gifhorn“, „Warum ich mich für den xTool F2 Ultra UV entschieden habe“, Fragerunden & Storytime).",
                    MixingGuide = "• Deine Stimme auf 0 dB (100 %), diesen Song auf -16 dB bis -18 dB darunterlegen. Durch das gedämpfte Filzklavier bleibt jede Silbe deiner Stimme glasklar verständlich.",
                    InstrumentationGuide = "• Speziell zwischen 800 Hz und 3.000 Hz (menschlicher Stimmfrequenzbereich) sanft abgesenkt: Weiches Felt Piano + tiefes warmes Cello-Pad.",
                    SunoUdioPrompt = "Warm emotional ambient lo-fi neoclassical background music for voiceover, D minor, soft muted felt piano, warm cello pad, gentle analog warmth, calm and trustworthy handcrafted luxury brand mood, leaves mid-range frequency space for female voiceover, 74 BPM, instrumental, no drums"
                },
                new MarketingMusicTrackDto
                {
                    TrackNumber = 5,
                    Id = "sf_mkt_05",
                    FileName = "sf_mkt_05_oma_handschrift_elegie.m4a",
                    ExportFileName = "Mein-Seelenfunke_05_Omas-Handschrift-Elegie_68BPM_A-Moll.m4a",
                    CategoryKey = "camera",
                    CategoryLabel = "🎙️ Vor der Kamera & Storytelling",
                    Title = "Omas Handschrift Elegie (Emotionales Storytelling)",
                    Subtitle = "Tief berührendes Filzklavier & Solo-Cello für Gedenkstücke, Handschriften & Haustier-Erinnerungen",
                    Bpm = 68,
                    MusicalKey = "A-Moll (A minor)",
                    DurationSeconds = 88,
                    DropTimingLabel = "⏱️ Sek. 0–4: Intimes Solo-Piano · Ab Sek. 6,5: Warmes Solo-Cello setzt ein (Tränen- & Gänsehaut-Moment)",
                    ScenarioGuide = "• Für deine emotionalsten Aufträge: „Letzte Geburtstagskarte von Oma auf 14k Gold verewigt“ oder „Er war 14 Jahre mein treuester Begleiter (Seelen-Pfote / Seelen Kristall)“.",
                    MixingGuide = "• Ohne Sprechen: Musik auf -5 dB + 3 Sekunden leises Original-Laser-Zischen am Anfang. Mit Voiceover: Musik auf -15 dB.",
                    InstrumentationGuide = "• Intimes Nils-Frahm-/Olafur-Arnalds-inspiriertes Filzklavier (inkl. sanfter Hammer-Mechanik) + singendes analoges Cello in A-Moll.",
                    SunoUdioPrompt = "Deeply emotional intimate neoclassical piano and solo cello soundtrack, A minor, soft felt piano with gentle key mechanics, warm expressive cello melody entering at 6 seconds, nostalgic heirloom memory mood, Olafur Arnalds style, 68 BPM, instrumental"
                },
                new MarketingMusicTrackDto
                {
                    TrackNumber = 6,
                    Id = "sf_mkt_06",
                    FileName = "sf_mkt_06_atelier_herzschlag.m4a",
                    ExportFileName = "Mein-Seelenfunke_06_Atelier-Herzschlag_72BPM_D-Moll.m4a",
                    CategoryKey = "camera",
                    CategoryLabel = "🎙️ Vor der Kamera & Storytelling",
                    Title = "Atelier Herzschlag (Qualitäts-Check & Lupe)",
                    Subtitle = "Souveräner, ruhiger Manufaktur-Puls für „Warum dieses Stück heute NICHT verschickt wird“-Videos",
                    Bpm = 72,
                    MusicalKey = "D-Moll (D minor)",
                    DurationSeconds = 80,
                    DropTimingLabel = "⏱️ Sek. 0–3: Fokussierter Atelier-Puls · Ab Sek. 5: Warme Auflösung beim Zeigen des perfekten Unikats",
                    ScenarioGuide = "• Perfekt für Videos, die dein kompromissloses Qualitätsversprechen zeigen: Prüfung unter der Lupe mit weißen Handschuhen, Vergleich Oberflächengravur vs. UV-Tiefengravur.",
                    MixingGuide = "• Deine erklärende Stimme auf 0 dB, Musik auf -15 dB.",
                    InstrumentationGuide = "• Dezenter 72-BPM-Ruhepuls + warmes D-Moll Filzklavier + feine Kristall-Obertöne.",
                    SunoUdioPrompt = "Calm authoritative luxury craftsmanship background music, D minor, subtle 72 BPM heartbeat pulse, warm felt piano, resonant cello drone, precision jeweler quality inspection mood, leaves space for speaking, 72 BPM, instrumental"
                },

                // =========================================================================
                // KATEGORIE 3: WERKSTATT, ACTION-CAM & WHITE-GLOVE PACKAGING (3 SONGS)
                // =========================================================================
                new MarketingMusicTrackDto
                {
                    TrackNumber = 7,
                    Id = "sf_mkt_07",
                    FileName = "sf_mkt_07_actioncam_white_glove.m4a",
                    ExportFileName = "Mein-Seelenfunke_07_ActionCam-White-Glove-POV_86BPM_D-Moll.m4a",
                    CategoryKey = "werkstatt",
                    CategoryLabel = "🧤 Werkstatt, Action-Cam & Packaging",
                    Title = "Action-Cam White-Glove POV (Werkstatt & Handschuhe)",
                    Subtitle = "Speziell für deine Kopf-/Action-Cam-Videos mit weißen Handschuhen beim Prüfen & Verpacken",
                    Bpm = 86,
                    MusicalKey = "D-Moll (D minor)",
                    DurationSeconds = 78,
                    DropTimingLabel = "⏱️ Rhythmisches 86-BPM-Handwerks-Pacing – trägt ASMR-Geräusche (Samt, Pinsel, Box-Klack), ohne sie zu überdecken",
                    ScenarioGuide = "• Dein Signature-Sound, wenn du die Action-Cam auf dem Kopf trägst, weiße Baumwollhandschuhe anhast und der Zuschauer aus deiner Ich-Perspektive (POV) sieht, wie du das Unikat aus dem xTool F2 Ultra nimmst, abpinselst und in die schwarze Westpack-Box bettest.",
                    MixingGuide = "• Original-Werkstatt-ASMR (Pinsel-Streichen, Samt-Inlay, sattes Schachtel-Klacken) auf 100 % (0 dB) lassen! Diesen Song bei -9 dB als edlen Teppich darunterlegen.",
                    InstrumentationGuide = "• Rhythmische gedämpfte Filzklavier-Arpeggien in D-Moll + seidiger Sub-Bass + kristallklare Höhen, die den hohen Mittenbereich für echte ASMR-Geräusche komplett freihalten.",
                    SunoUdioPrompt = "First-person POV luxury workshop packaging soundtrack, D minor, rhythmic muted felt piano arpeggios, warm deep sub bass, leaves crisp high-mid space for white glove and velvet box ASMR sounds, sophisticated handcrafted aesthetic, 86 BPM, instrumental"
                },
                new MarketingMusicTrackDto
                {
                    TrackNumber = 8,
                    Id = "sf_mkt_08",
                    FileName = "sf_mkt_08_schiefer_gold_manufaktur.m4a",
                    ExportFileName = "Mein-Seelenfunke_08_Schiefer-Glas-Gold-Manufaktur_82BPM_A-Moll.m4a",
                    CategoryKey = "werkstatt",
                    CategoryLabel = "🧤 Werkstatt, Action-Cam & Packaging",
                    Title = "Schiefer, Glas & Gold (Laser-Manufaktur)",
                    Subtitle = "Edler Manufaktur-Soundtrack für xTool F2 Ultra UV, Förderband & RA3 Rotationsaufsatz",
                    Bpm = 82,
                    MusicalKey = "A-Moll (A minor)",
                    DurationSeconds = 76,
                    DropTimingLabel = "⏱️ Sek. 0–3,5: Dunkler Schiefer-Bass · Ab Sek. 3,8: Goldener Harmonie-Aufbau zum Laser-Fortschritt",
                    ScenarioGuide = "• Perfekt für Nahaufnahmen an der Maschine: Naturschiefer-Gravur, Weizenglas im RA3 Rotationsaufsatz oder Serienfertigung auf dem Conveyor-Förderband.",
                    MixingGuide = "• Laser-Summen in den ersten 3,5 Sekunden auf 0 dB, danach diesen Track auf -5 dB tragen lassen.",
                    InstrumentationGuide = "• Erdiger, tiefer Analog-Bass (Schiefer & Metall) trifft auf glitzernde Kristall-Obertöne (Glas & UV-Laser) in A-Moll.",
                    SunoUdioPrompt = "Modern high-end laser atelier soundtrack, A minor, deep slate and metal sub bass texture, warm analog synth pad, sparkling UV crystal harmonics, rhythmic precision pulse, dark luxury manufactory mood, 82 BPM, instrumental"
                },
                new MarketingMusicTrackDto
                {
                    TrackNumber = 9,
                    Id = "sf_mkt_09",
                    FileName = "sf_mkt_09_pack_with_me_gifhorn.m4a",
                    ExportFileName = "Mein-Seelenfunke_09_Pack-With-Me-Gifhorn_88BPM_D-Dorisch.m4a",
                    CategoryKey = "werkstatt",
                    CategoryLabel = "🧤 Werkstatt, Action-Cam & Packaging",
                    Title = "Pack With Me Gifhorn (Edles Unboxing & Versand)",
                    Subtitle = "Warmer, flüssiger Boutique-Flow für „Packe Bestellung #... mit mir“-Videos mit weißen Handschuhen",
                    Bpm = 88,
                    MusicalKey = "D-Dorisch (D Dorian)",
                    DurationSeconds = 74,
                    DropTimingLabel = "⏱️ Alle 2,7 Sek. sanfter Harmonie-Wechsel – ideal für 4–5 flüssige Verpackungs-Schnitte",
                    ScenarioGuide = "• Für deine „Pack an Order with Me“-Videos mit Action-Cam: 1. Unikat prüfen -> 2. In schwarze Westpack-Box legen -> 3. Schwarz-Gold-Geschenkpapier / Dankeskarte dazu -> 4. Versandfertig machen.",
                    MixingGuide = "• Papier-Rascheln und Schachtel-Schließen auf 0 dB, Musik auf -8 dB.",
                    InstrumentationGuide = "• Positiv-edles D-Dorisch mit weichem Filzklavier, warmem Cello-Hauch und feinen Glöckchen-Obertönen.",
                    SunoUdioPrompt = "Warm boutique order packaging background music, D Dorian, flowing felt piano chords, gentle cello warmth, subtle luxury chime accents, cozy and prestigious small business packing aesthetic, 88 BPM, instrumental"
                },

                // =========================================================================
                // KATEGORIE 4: MYSTISCHES ATELIER & RUHIGE SZENEN (3 SONGS INKL. LOGIN-SONG)
                // =========================================================================
                new MarketingMusicTrackDto
                {
                    TrackNumber = 10,
                    Id = "sf_mkt_10",
                    FileName = "sf_mkt_10_mystic_atelier_login.m4a",
                    ExportFileName = "Mein-Seelenfunke_10_Mystisches-Atelier-Login-Extended_60BPM_D-Dorisch.m4a",
                    CategoryKey = "atelier",
                    CategoryLabel = "🕯️ Mystisches Atelier & Ruhige Szenen",
                    Title = "Mein Seelenfunke – Mystisches Atelier (Login-Song Extended)",
                    Subtitle = "Dein geliebter, mystisch-warmer Funkenwerk-Login-Soundtrack in voller 1:30-Minuten-Albumlänge",
                    Bpm = 60,
                    MusicalKey = "D-Dorisch (D Dorian)",
                    DurationSeconds = 90,
                    DropTimingLabel = "⏱️ Sanftes Einfaden (0–3s) · Mystische Kristall-Harfe & Cello-Drone im 60-BPM-Ruhepuls",
                    ScenarioGuide = "• Genau der mystische, ruhige Song aus deinem Funkenwerk-Loginbereich – hier in voller Länge (1:30 Min.) für ruhige Abend-Aufnahmen, edle Schiefer-/Glas-/Kerzenlicht-Kamerafahrten, Hochzeits-Unikate und Marken-Trailer.",
                    MixingGuide = "• Wirkt sowohl pur auf 0 dB als epischer Marken-Soundtrack als auch auf -12 dB unter ruhigen Erzählungen.",
                    InstrumentationGuide = "• Warmer analoger Cello-Drone (D2 + A2) + mystische Kristallglas-Harfen-Chimes + gedämpftes Filzklavier in D-Dorisch + tiefer 60-BPM-Kathedralen-Raumklang.",
                    SunoUdioPrompt = "Dark luxury ambient cinematic soundtrack, warm analog cello drone, mystical resonant crystal glass harp chimes, soft felt piano in D Dorian, deep warm sub bass, slow atmospheric cathedral reverb, boutique jewelry atelier vibe, Hans Zimmer meets Nils Frahm and Olafur Arnalds, 60 BPM, instrumental, no drums, ultra clean mastering"
                },
                new MarketingMusicTrackDto
                {
                    TrackNumber = 11,
                    Id = "sf_mkt_11",
                    FileName = "sf_mkt_11_kerzenlicht_kristall.m4a",
                    ExportFileName = "Mein-Seelenfunke_11_Kerzenlicht-und-Kristall_62BPM_A-Moll.m4a",
                    CategoryKey = "atelier",
                    CategoryLabel = "🕯️ Mystisches Atelier & Ruhige Szenen",
                    Title = "Kerzenlicht & Kristall (Edle Makro-Ruhe)",
                    Subtitle = "Schwerelose Glas-Harfe & Kerzenlicht-Piano für langsame 360°-Produktfahrten am Abend",
                    Bpm = 62,
                    MusicalKey = "A-Moll (A minor)",
                    DurationSeconds = 86,
                    DropTimingLabel = "⏱️ Zeitloser 62-BPM-Fluss für Zeitlupen- und Makro-Aufnahmen im warmen Kerzenschein",
                    ScenarioGuide = "• Perfekt für ruhige Aesthetic-Reels am Abend: Wenn auf deinem Regal Kerzen brennen und du mit der Kamera ganz langsam an Schiefer, Kristallglas und 14k Gold entlangfährst.",
                    MixingGuide = "• Pur auf -2 dB nutzen oder mit leisem Knistern/Raumton mischen.",
                    InstrumentationGuide = "• A-Moll Kristallglas-Harfe + weitläufiger Shimmer-Reverb + samtiges Filzklavier.",
                    SunoUdioPrompt = "Candlelight luxury macro product showcase soundtrack, A minor, ethereal crystal glass harp harmonics, slow intimate felt piano, warm analog pad, peaceful evening jewelry atelier atmosphere, 62 BPM, instrumental, no percussion"
                },
                new MarketingMusicTrackDto
                {
                    TrackNumber = 12,
                    Id = "sf_mkt_12",
                    FileName = "sf_mkt_12_sonntagsschicht_ruhe.m4a",
                    ExportFileName = "Mein-Seelenfunke_12_Sonntagsschicht-in-Gifhorn_64BPM_D-Moll.m4a",
                    CategoryKey = "atelier",
                    CategoryLabel = "🕯️ Mystisches Atelier & Ruhige Szenen",
                    Title = "Sonntagsschicht in Gifhorn (Warme Manufaktur-Seele)",
                    Subtitle = "Geborgene, ehrliche Feierabend- & Sonntags-Atmosphäre für ruhige Einblicke hinter die Kulissen",
                    Bpm = 64,
                    MusicalKey = "D-Moll (D minor)",
                    DurationSeconds = 82,
                    DropTimingLabel = "⏱️ Sanfter, herzlicher Aufbau – ideal für Wochen-Rückblicke, Dankes-Videos & ruhige Werkbank-Szenen",
                    ScenarioGuide = "• Für deine Sonntags- oder Feierabend-Videos: „Während draußen alles ruhig ist, verpacke ich noch eure Herzens-Bestellungen für Montag“.",
                    MixingGuide = "• Mit Stimme auf -15 dB, ohne Stimme auf -4 dB + echte leise Handgriffe an der Werkbank.",
                    InstrumentationGuide = "• Warmes D-Moll Filzklavier + sanfter Kontrabass/Cello-Bogen + dezente goldene Oberton-Glöckchen.",
                    SunoUdioPrompt = "Cozy Sunday evening luxury workshop ambient music, D minor, warm felt piano melody, gentle acoustic cello and upright bass warmth, peaceful handcrafted brand storytelling soundtrack, 64 BPM, instrumental"
                }
            };
        }

        // =========================================================================
        // PANE 2: TOP 20 ACCOUNTS (TIKTOK & INSTAGRAM) + 10 VIRAL VIDEOS JE ACCOUNT
        // =========================================================================
        private void MktAccountClusterFilter_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string cluster)
            {
                PlaySoundNav();
                _activeAccountClusterFilter = cluster;
                StyleClusterFilterButtons();
                RenderMarketingAccountsPane();
            }
        }

        private void StyleClusterFilterButtons()
        {
            var map = new (Button? Btn, string Key)[]
            {
                (MktClusterAllBtn, "all"),
                (MktClusterSchmuckBtn, "schmuck"),
                (MktClusterKristallBtn, "kristall"),
                (MktClusterLaserBtn, "laser"),
                (MktClusterLuxuryBtn, "luxury")
            };
            foreach (var (btn, key) in map)
            {
                if (btn == null) continue;
                bool active = _activeAccountClusterFilter == key;
                btn.Background = active
                    ? (_isDarkMode ? BrushFromHex("#000000") : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059")))
                    : BrushFromHex("#181B24");
                btn.Foreground = active
                    ? (_isDarkMode ? BrushFromHex("#C5A059") : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#111827")))
                    : BrushFromHex("#D1D5DB");
                btn.BorderBrush = active ? BrushFromHex("#C5A059") : BrushFromHex("#232734");
                btn.BorderThickness = new Thickness(1);
                btn.FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold;
            }
        }

        private void RenderMarketingAccountsPane()
        {
            if (MktAccountsListPanel == null) return;
            MktAccountsListPanel.Children.Clear();

            var list = _mktVault.TopAccounts.AsEnumerable();
            if (_activeAccountClusterFilter != "all")
                list = list.Where(a => a.Cluster == _activeAccountClusterFilter);

            if (!string.IsNullOrEmpty(_marketingSearchQuery))
            {
                list = list.Where(a =>
                    a.Name.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase) ||
                    a.Handle.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase) ||
                    a.ClusterLabel.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase));
            }

            var filtered = list.ToList();
            if (_selectedCompetitorAccount == null || !filtered.Any(a => a.Rank == _selectedCompetitorAccount.Rank))
                _selectedCompetitorAccount = filtered.FirstOrDefault() ?? _mktVault.TopAccounts.FirstOrDefault();

            foreach (var acc in filtered)
            {
                bool isSel = _selectedCompetitorAccount != null && _selectedCompetitorAccount.Rank == acc.Rank;
                var card = new Border
                {
                    Background = BrushFromHex(isSel ? "#26231F" : "#141720"),
                    BorderBrush = isSel
                        ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059"))
                        : BrushFromHex("#222634"),
                    BorderThickness = new Thickness(isSel ? 1.5 : 1),
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(12, 9, 12, 9),
                    Margin = new Thickness(0, 0, 0, 6),
                    Cursor = Cursors.Hand
                };

                var sp = new StackPanel();
                var topRow = new Grid();
                topRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                topRow.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                topRow.Children.Add(new TextBlock
                {
                    Text = $"#{acc.Rank:00}  {acc.Name}",
                    Foreground = BrushFromHex("#F9FAFB"),
                    FontSize = 13,
                    FontWeight = FontWeights.Bold,
                    TextTrimming = TextTrimming.CharacterEllipsis
                });

                var platBadge = new TextBlock
                {
                    Text = $"{acc.Platform} · Ø {acc.AvgVideoLength}",
                    Foreground = BrushFromHex("#D4AF37"),
                    FontSize = 10.5,
                    FontWeight = FontWeights.Bold
                };
                Grid.SetColumn(platBadge, 1);
                topRow.Children.Add(platBadge);
                sp.Children.Add(topRow);

                sp.Children.Add(new TextBlock
                {
                    Text = $"{acc.Handle}  ·  {acc.Followers}  ·  {acc.ClusterLabel}",
                    Foreground = BrushFromHex("#9CA3AF"),
                    FontSize = 11,
                    Margin = new Thickness(0, 3, 0, 0),
                    TextTrimming = TextTrimming.CharacterEllipsis
                });

                card.Child = sp;
                int capturedRank = acc.Rank;
                card.MouseLeftButtonUp += (s, e) =>
                {
                    PlaySoundNav();
                    _selectedCompetitorAccount = _mktVault.TopAccounts.FirstOrDefault(x => x.Rank == capturedRank);
                    RenderMarketingAccountsPane();
                };

                MktAccountsListPanel.Children.Add(card);
            }

            RenderSelectedAccountDetail();
        }

        private void RenderSelectedAccountDetail()
        {
            var acc = _selectedCompetitorAccount;
            if (acc == null || MktAccDetailTitleTxt == null) return;

            MktAccDetailTitleTxt.Text = $"#{acc.Rank:00} {acc.Name} ({acc.Handle})";
            MktAccDetailMetaTxt.Text = $"{acc.Platform}  ·  {acc.Followers} Follower  ·  Ø Videolänge: {acc.AvgVideoLength}  ·  Cluster: {acc.ClusterLabel}";
            MktAccBioTxt.Text = acc.BioStructure;
            MktAccWebTxt.Text = acc.WebsiteFunnel;
            MktAccGoogleTxt.Text = acc.GoogleBusinessProfile;
            MktAccSecretsTxt.Text = string.Join("\n", acc.WinningSecrets.Select(s => $"✓ {s}"));

            MktAccVideosListPanel.Children.Clear();
            foreach (var v in acc.TopVideos)
            {
                var vCard = new Border
                {
                    Background = BrushFromHex("#141720"),
                    BorderBrush = BrushFromHex("#232734"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(14, 10, 14, 10),
                    Margin = new Thickness(0, 0, 0, 8)
                };

                var vSp = new StackPanel();
                var headerGrid = new Grid();
                headerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                headerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                headerGrid.Children.Add(new TextBlock
                {
                    Text = $"Viral #{v.Rank:00}: {v.Title}",
                    Foreground = BrushFromHex("#F9FAFB"),
                    FontSize = 13,
                    FontWeight = FontWeights.Bold,
                    VerticalAlignment = VerticalAlignment.Center
                });

                var rightStack = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
                rightStack.Children.Add(new Border
                {
                    Background = BrushFromHex("#1F2430"),
                    CornerRadius = new CornerRadius(6),
                    Padding = new Thickness(8, 3, 8, 3),
                    Margin = new Thickness(0, 0, 6, 0),
                    Child = new TextBlock
                    {
                        Text = $"⏱ {v.Duration}  ·  👁 {v.Views}",
                        Foreground = BrushFromHex("#D4AF37"),
                        FontSize = 11,
                        FontWeight = FontWeights.Bold
                    }
                });

                var adaptBtn = new Button
                {
                    Content = "⚡ Heute so drehen",
                    Background = _isDarkMode ? BrushFromHex("#000000") : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059")),
                    Foreground = _isDarkMode ? BrushFromHex("#C5A059") : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#111827")),
                    BorderBrush = BrushFromHex("#C5A059"),
                    BorderThickness = new Thickness(1),
                    FontWeight = FontWeights.Bold,
                    FontSize = 11,
                    Padding = new Thickness(10, 4, 10, 4),
                    Cursor = Cursors.Hand
                };
                int mappedDay = v.MappedRecipeDay;
                adaptBtn.Click += (s, e) =>
                {
                    int idx = _mktVault.DailyRecipes.FindIndex(r => r.DayNumber == mappedDay);
                    if (idx >= 0)
                    {
                        PlaySoundTaskDone();
                        _currentDailyRecipeIndex = idx;
                        _selectedPlanRecipe = _mktVault.DailyRecipes[idx];
                        SaveMarketingVaultOffline();
                        RenderDailyHeroBanner();
                    }
                };
                rightStack.Children.Add(adaptBtn);
                Grid.SetColumn(rightStack, 1);
                headerGrid.Children.Add(rightStack);
                vSp.Children.Add(headerGrid);

                vSp.Children.Add(new TextBlock
                {
                    Text = $"Hook: {v.HookFirst3Sec}   ·   Vorlage: {v.SeelenfunkeAdaptation}",
                    Foreground = BrushFromHex("#9CA3AF"),
                    FontSize = 11.5,
                    Margin = new Thickness(0, 4, 0, 0),
                    TextWrapping = TextWrapping.Wrap
                });

                vCard.ToolTip = $"Sound & Besonderheit: {v.SoundUsed} · {v.SpecialFeature}";
                vCard.Child = vSp;
                MktAccVideosListPanel.Children.Add(vCard);
            }
        }

        private Border BuildMiniFactBox(string label, string val)
        {
            var bd = new Border
            {
                Background = BrushFromHex("#0F1117"),
                BorderBrush = BrushFromHex("#232734"),
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(7),
                Padding = new Thickness(9, 6, 9, 6),
                Margin = new Thickness(0, 0, 6, 0)
            };
            var sp = new StackPanel();
            sp.Children.Add(new TextBlock
            {
                Text = label,
                Foreground = BrushFromHex("#D4AF37"),
                FontSize = 9.5,
                FontWeight = FontWeights.Bold
            });
            sp.Children.Add(new TextBlock
            {
                Text = val,
                Foreground = BrushFromHex("#E5E7EB"),
                FontSize = 11.5,
                Margin = new Thickness(0, 2, 0, 0),
                TextWrapping = TextWrapping.Wrap
            });
            bd.Child = sp;
            return bd;
        }

        // =========================================================================
        // PANE 3: 37-PUNKTE RÖNTGEN + TOKENARME KI-ANALYSE (OFFLINE CACHED)
        // =========================================================================
        private void MktQuestionCategory_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string cat)
            {
                PlaySoundNav();
                _activeQuestionCategoryFilter = cat;
                StyleQuestionCategoryButtons();
                RenderMarketingQuestionsPane();
            }
        }

        private void StyleQuestionCategoryButtons()
        {
            var map = new (Button? Btn, string Key)[]
            {
                (MktQCatAllBtn, "all"),
                (MktQCatBaseBtn, "base"),
                (MktQCatHookBtn, "hook"),
                (MktQCatCameraBtn, "camera"),
                (MktQCatStoryBtn, "story"),
                (MktQCatAlgoBtn, "algo"),
                (MktQCatFunnelBtn, "funnel"),
                (MktQCatAdsBtn, "ads")
            };
            foreach (var (btn, key) in map)
            {
                if (btn == null) continue;
                bool active = _activeQuestionCategoryFilter == key;
                btn.Background = active
                    ? (_isDarkMode ? BrushFromHex("#000000") : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059")))
                    : BrushFromHex("#181B24");
                btn.Foreground = active
                    ? (_isDarkMode ? BrushFromHex("#C5A059") : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#111827")))
                    : BrushFromHex("#D1D5DB");
                btn.BorderBrush = active ? BrushFromHex("#C5A059") : BrushFromHex("#232734");
                btn.BorderThickness = new Thickness(1);
                btn.FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold;
            }
        }

        private void RenderMarketingQuestionsPane()
        {
            if (MktQuestionsListPanel == null) return;
            StyleQuestionCategoryButtons();
            MktQuestionsListPanel.Children.Clear();

            var items = _mktVault.QuestionsCatalog.AsEnumerable();
            if (_activeQuestionCategoryFilter != "all")
                items = items.Where(q => q.CategoryKey == _activeQuestionCategoryFilter);

            if (!string.IsNullOrEmpty(_marketingSearchQuery))
            {
                items = items.Where(q =>
                    q.Question.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase) ||
                    q.MarketFinding.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase) ||
                    q.SeelenfunkeRule.Contains(_marketingSearchQuery, StringComparison.OrdinalIgnoreCase));
            }

            foreach (var q in items)
            {
                var card = new Border
                {
                    Background = BrushFromHex("#141720"),
                    BorderBrush = BrushFromHex("#232734"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(9),
                    Padding = new Thickness(14, 10, 14, 10),
                    Margin = new Thickness(0, 0, 0, 6),
                    ToolTip = $"Markt-Ergebnis: {q.MarketFinding}"
                };

                var grid = new Grid();
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.2, GridUnitType.Star) });

                var col0 = new StackPanel { Margin = new Thickness(0, 0, 14, 0), VerticalAlignment = VerticalAlignment.Center };
                col0.Children.Add(new TextBlock
                {
                    Text = $"#{q.Number:00}  {q.Question}",
                    Foreground = BrushFromHex("#F9FAFB"),
                    FontSize = 12.5,
                    FontWeight = FontWeights.Bold,
                    TextWrapping = TextWrapping.Wrap
                });
                grid.Children.Add(col0);

                var col1 = new StackPanel { VerticalAlignment = VerticalAlignment.Center };
                col1.Children.Add(new TextBlock
                {
                    Text = $"✓ {q.SeelenfunkeRule}",
                    Foreground = BrushFromHex("#34D399"),
                    FontSize = 12,
                    FontWeight = FontWeights.SemiBold,
                    TextWrapping = TextWrapping.Wrap
                });
                Grid.SetColumn(col1, 1);
                grid.Children.Add(col1);

                card.Child = grid;
                MktQuestionsListPanel.Children.Add(card);
            }

            RenderSavedCustomAiScans();
        }

        private async void MktRunTokenLeanAiScan_Click(object sender, RoutedEventArgs e)
        {
            string rawInput = (MktAiScanInputBox?.Text ?? "").Trim();
            if (string.IsNullOrEmpty(rawInput)) return;

            PlaySoundNav();
            string hash = ComputeSha256Hex(rawInput.ToLowerInvariant());

            // 1. Check Offline SHA-256 Cache first (0 Tokens!)
            var cached = _mktVault.CustomAiScans.FirstOrDefault(x => x.ContentHash == hash);
            if (cached != null)
            {
                if (MktAiScanStatusTxt != null)
                    MktAiScanStatusTxt.Text = "⚡ Aus lokalem Offline-Speicher geladen (0 Tokens verbraucht!)";
                RenderSavedCustomAiScans();
                return;
            }

            if (MktAiScanBtn != null) MktAiScanBtn.IsEnabled = false;
            if (MktAiScanStatusTxt != null) MktAiScanStatusTxt.Text = "Analysiere kompakt & speichere offline...";

            CustomAiScanRecordDto? record = null;
            try
            {
                var payload = JsonSerializer.Serialize(new
                {
                    prompt = $"Analysiere diesen Social-Media-Account/Video für 'Mein Seelenfunke' (Gravur, Schmuck, K9-Kristall, Weiße Handschuhe) ultrakompakt in 4 Punkten: {rawInput}"
                });
                var res = await _http.PostAsync($"{_apiBaseUrl}/funki/marketing/quick-scan", new StringContent(payload, Encoding.UTF8, "application/json"));
                if (res.IsSuccessStatusCode)
                {
                    string body = await res.Content.ReadAsStringAsync();
                    record = JsonSerializer.Deserialize<CustomAiScanRecordDto>(body);
                }
            }
            catch { }

            // Local deterministic synthesis fallback (0 external tokens needed when offline!)
            if (record == null)
            {
                bool isShort = rawInput.Contains("asmr", StringComparison.OrdinalIgnoreCase) || rawInput.Contains("laser", StringComparison.OrdinalIgnoreCase);
                record = new CustomAiScanRecordDto
                {
                    ContentHash = hash,
                    TargetName = rawInput.Length > 48 ? rawInput.Substring(0, 48) + "..." : rawInput,
                    DurationAndHook = isShort
                        ? "Ziel-Dauer: 12–15 Sek. | Hook: Start direkt im Laser-Geräusch (0 dB) + 1-Satz On-Screen-Hook"
                        : "Ziel-Dauer: 24–30 Sek. | Hook: Kundenwunsch/Foto in Sekunde 0–2 zeigen + weiße Handschuhe",
                    SoundAndVisuals = "3 Sek. knackiger Werkstatt-Originalton -> sanfter Übergang auf Piano beim Licht-Reveal im Dunkeln",
                    BioAndFunnel = "Direkt-Link ohne Zwischenklicks auf thematische Landingpage (/l/...) -> 3D-Live-Konfigurator",
                    ActionableTakeaway = "Mit weißen Handschuhen am xTool F2 Ultra UV nachdrehen und in Westpack-Schachtel abschließen."
                };
            }
            record.ContentHash = hash;

            _mktVault.CustomAiScans.Insert(0, record);
            SaveMarketingVaultOffline();

            if (MktAiScanInputBox != null) MktAiScanInputBox.Text = "";
            if (MktAiScanBtn != null) MktAiScanBtn.IsEnabled = true;
            if (MktAiScanStatusTxt != null) MktAiScanStatusTxt.Text = "✓ Dauerhaft im Offline-Tresor gespeichert!";

            RenderSavedCustomAiScans();
        }

        private void RenderSavedCustomAiScans()
        {
            if (MktCustomScansListPanel == null) return;
            MktCustomScansListPanel.Children.Clear();

            foreach (var scan in _mktVault.CustomAiScans.Take(6))
            {
                var bd = new Border
                {
                    Background = BrushFromHex("#141720"),
                    BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059")),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(9),
                    Padding = new Thickness(12, 8, 12, 8),
                    Margin = new Thickness(0, 0, 0, 6)
                };
                var sp = new StackPanel();
                sp.Children.Add(new TextBlock
                {
                    Text = $"⚡ {scan.TargetName} ({scan.CreatedAt})",
                    Foreground = BrushFromHex("#D4AF37"),
                    FontSize = 11.5,
                    FontWeight = FontWeights.Bold
                });
                sp.Children.Add(new TextBlock
                {
                    Text = $"{scan.DurationAndHook}  ·  {scan.ActionableTakeaway}",
                    Foreground = BrushFromHex("#E5E7EB"),
                    FontSize = 11,
                    Margin = new Thickness(0, 2, 0, 0),
                    TextWrapping = TextWrapping.Wrap
                });
                bd.Child = sp;
                MktCustomScansListPanel.Children.Add(bd);
            }
        }

        private static string ComputeSha256Hex(string input)
        {
            byte[] bytes = SHA256.HashData(Encoding.UTF8.GetBytes(input));
            return Convert.ToHexString(bytes);
        }

        // =========================================================================
        // PANE 4 & 5: COPY HELPERS FOR BIO, CAPCUT & INFLUENCER TEMPLATES
        // =========================================================================
        private void MktCopyTextTag_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string textToCopy)
            {
                Clipboard.SetText(textToCopy.Replace("\\n", "\n"));
                PlaySoundActionPop();
                string orig = btn.Content?.ToString() ?? "📋 Kopieren";
                btn.Content = "✓ Kopiert!";
                Task.Delay(1300).ContinueWith(_ => Dispatcher.Invoke(() => btn.Content = orig));
            }
        }

        // =========================================================================
        // SEED GENERATOR: 14 DAILY RECIPES + 20 TOP ACCOUNTS + 37 QUESTIONS
        // =========================================================================
        private MarketingVaultStorageDto BuildDefaultMarketingVault()
        {
            var vault = new MarketingVaultStorageDto
            {
                ShopProducts = BuildDefaultShopProducts(),
                SeasonContext = ComputeLocalSeasonAndHolidayContext(DateTime.Today)
            };

            // 1. 14 FERTIGE TAGES-DREHBÜCHER FÜR MEIN SEELENFUNKE (OHNE NACHDENKEN UMSETZEN)
            vault.DailyRecipes = new List<DailyShootRecipeDto>
            {
                new() {
                    DayNumber = 1, WeekdayLabel = "Montag", CategoryBadge = "White-Glove ASMR", DurationLabel = "14 Sek.",
                    Title = "Das Goldene Seelen-Herz – 14k Gold-Gravur & Westpack-Box",
                    ProductName = "Das Goldene Seelen-Herz (14k vergoldet)",
                    CapCutTemplate = "Vorlage A: Pure Atelier ASMR (14s)", LandingPageSlug = "/l/goldenes-seelen-herz",
                    OnScreenHookText = "Wenn eine Handschrift für die Ewigkeit in 14k Gold gebrannt wird ✨",
                    AudioDirection = "0–4s Original UV-Laser-Zischen (0 dB) → ab Sek. 5 sanftes Piano (-8 dB)",
                    Step1Setup = "Weiße Handschuhe · Goldenes Seelen-Herz · xTool F2 Ultra UV · Schwarze Westpack-Box",
                    Step2Hook = "0–3s: Start exakt beim Aufblitzen des UV-Lasers auf dem goldenen Herz",
                    Step3Clips = "Clip 1: 4s Laser-Makro · Clip 2: 5s Abpinseln mit weißen Handschuhen · Clip 3: 5s Einlegen in Westpack-Box",
                    Step4Edit = "CapCut Vorlage A · Schnitte auf 1,2s · Am Ende Deckel-Klack der Schachtel hörbar lassen",
                    ReadyCaption = "Manche Worte sind zu wertvoll, um nur auf Papier zu stehen. ✨🤍 Jedes Goldene Seelen-Herz wird in unserer Manufaktur in Gifhorn mit weißen Handschuhen für dich gefertigt.\n\n👉 Teste deine Wunschgravur jetzt live in 3D über den Link in der Bio!\n\n#meinseelenfunke #erinnerungsschmuck #geschenkidee #gravurschmuck #handgemacht"
                },
                new() {
                    DayNumber = 2, WeekdayLabel = "Dienstag", CategoryBadge = "Emotionale Story", DurationLabel = "28 Sek.",
                    Title = "Der Seelen Kristall – Haustier-Erinnerung im Dunkeln erleuchtet",
                    ProductName = "Der Seelen Kristall (K9-Kristallglas 3D)",
                    CapCutTemplate = "Vorlage B: Kunden-Story & Licht-Reveal (28s)", LandingPageSlug = "/l/seelen-kristall",
                    OnScreenHookText = "„Er war 14 Jahre lang mein bester Freund... könnt ihr ihn verewigen?“ 🐾🤍",
                    AudioDirection = "0–5s leises Laser-Summen → ab Sek. 12 beim Licht-Einschalten volle Gänsehaut-Musik",
                    Step1Setup = "Weiße Handschuhe · Der Seelen Kristall · LED-Leuchtsockel · Raum abdunkelbar",
                    Step2Hook = "0–3s: Handyfoto des Hundes kurz neben den rohen K9-Kristallblock halten",
                    Step3Clips = "Clip 1: 6s UV-Laser im Glasinneren · Clip 2: 8s Polieren mit Handschuhen · Clip 3: 11s Raum dunkel & Licht an",
                    Step4Edit = "CapCut Vorlage B · Musik-Drop exakt bei Sekunde 14 wenn der Kristall im Dunkeln aufleuchtet",
                    ReadyCaption = "Für immer im Herzen – und jetzt für die Ewigkeit in reinem K9-Kristallglas festgehalten. 🐾✨ Danke für euer Vertrauen bei so emotionalen Erinnerungsstücken.\n\n👇 Lade dein Lieblingsfoto jetzt unverbindlich in unseren 3D-Konfigurator (Link in Bio)!\n\n#meinseelenfunke #seelenkristall #3dglasgravur #hundeerinnerung #trauergeschenk #pfotenliebe"
                },
                new() {
                    DayNumber = 3, WeekdayLabel = "Mittwoch", CategoryBadge = "Gründerin & Vertrauen", DurationLabel = "34 Sek.",
                    Title = "Perfektionismus-Check: Warum dieses Stück heute NICHT rausgeht",
                    ProductName = "Manufaktur-Qualitätsversprechen (Kristall & Schmuck)",
                    CapCutTemplate = "Vorlage C: Gründer-Talk & Qualitäts-Lupe (34s)", LandingPageSlug = "/linktree",
                    OnScreenHookText = "Warum ich diese Bestellung heute NICHT verschicke (sondern neu mache) 🔍🧤",
                    AudioDirection = "Ruhige, ehrliche Stimme (0 dB) + dezente Hintergrundmusik (-14 dB)",
                    Step1Setup = "Weiße Handschuhe · Lupe / Gegenlicht · 1 Musterstück · Westpack-Schachtel",
                    Step2Hook = "0–4s: Du hältst mit weißen Handschuhen ein Stück ins Streiflicht und schüttelst leicht den Kopf",
                    Step3Clips = "Clip 1: 8s Erklärung (winziger 0,2mm Punkt) · Clip 2: 12s frischer Laser-Durchgang · Clip 3: 10s perfektes Stück verpacken",
                    Step4Edit = "CapCut Vorlage C · Automatische Untertitel mit goldenem Highlight (#C5A059) aktivieren",
                    ReadyCaption = "99 % würden diesen 0,2mm Einschluss niemals sehen. Aber wenn es um deine Erinnerung geht, reichen uns 99 % nicht. 🧤✨ Jedes Unikat verlässt unsere Manufaktur in Gifhorn erst, wenn es perfekt ist.\n\n#meinseelenfunke #manufaktur #handarbeit #qualitätsversprechen #personalisiertegeschenke"
                },
                new() {
                    DayNumber = 4, WeekdayLabel = "Donnerstag", CategoryBadge = "Laser-ASMR Pur", DurationLabel = "15 Sek.",
                    Title = "Die Seelen-Pfote – Knackige Tiefengravur mit Funkenflug",
                    ProductName = "Die Seelen-Pfote (Personalisierte Hundemarke)",
                    CapCutTemplate = "Vorlage A: Pure Atelier ASMR (15s)", LandingPageSlug = "/l/seelen-pfote",
                    OnScreenHookText = "Hör dir dieses Geräusch an, wenn aus Metall ein Unikat wird 🔥🐾",
                    AudioDirection = "100 % echter Laser-Sound in den ersten 8 Sek. → warmer Bass/Piano ab Sek. 9",
                    Step1Setup = "Die Seelen-Pfote Rohling · xTool F2 Ultra · Weiße Handschuhe · Mikrofon nah an Laserhaube",
                    Step2Hook = "0–2s: Extremer Makro-Shot, wie der Laserstrahl die Pfoten-Kontur zieht",
                    Step3Clips = "Clip 1: 6s Laser-Gravur mit Originalton · Clip 2: 4s Abwischen mit Samttuch · Clip 3: 5s Schärfe-Reveal im Goldlicht",
                    Step4Edit = "CapCut Vorlage A · Höhen beim Laser-Audio leicht anheben (+2 dB) für Gänsehaut-ASMR",
                    ReadyCaption = "Präzision auf den Hundertstelmillimeter – für deinen treuesten Begleiter. 🐾🔥\n\n👉 Gestalte deine eigene Seelen-Pfote in 60 Sekunden über den Link in der Bio!\n\n#meinseelenfunke #hundemarke #laserasmr #hundeliebe #personalisiert"
                },
                new() {
                    DayNumber = 5, WeekdayLabel = "Freitag", CategoryBadge = "Wochenend-Impuls", DurationLabel = "17 Sek.",
                    Title = "Der Feierabend-Funke – 360° Rotationsgravur auf Weizenglas",
                    ProductName = "Der Feierabend-Funke (Personalisiertes Weizenglas)",
                    CapCutTemplate = "Vorlage A: Pure Atelier ASMR (17s)", LandingPageSlug = "/l/weizenglas",
                    OnScreenHookText = "Das Männergeschenk, das garantiert nicht im Schrank verstaubt 🍺✨",
                    AudioDirection = "0–6s Surren des xTool RA3 Rotationsmoduls + Laser → ab Sek. 7 kraftvoller Beat",
                    Step1Setup = "Weizenglas · xTool RA3 MagSwap Rotationsmodul · Weiße Handschuhe · Gegenlicht-Lampe",
                    Step2Hook = "0–3s: Das Weizenglas dreht sich im RA3-Modul während der UV-Laser das Wappen graviert",
                    Step3Clips = "Clip 1: 6s Rotationsgravur · Clip 2: 5s Glas mit weißen Handschuhen ins Gegenlicht heben · Clip 3: 6s Detail der Mattierung",
                    Step4Edit = "CapCut Vorlage A · Schneller Schnitt vom Laser direkt ins funkelnde Gegenlicht",
                    ReadyCaption = "Für Papa, den besten Freund oder den wohlverdienten eigenen Feierabend. 🍺✨ Mit dem UV-Laser dauerhaft und spülmaschinenfest ins Glas veredelt.\n\n👇 Jetzt Wunschnamen im Live-Konfigurator eingeben (Link in Bio)!\n\n#meinseelenfunke #weizenglas #männergeschenk #vatertagsgeschenk #glasgravur"
                },
                new() {
                    DayNumber = 6, WeekdayLabel = "Samstag", CategoryBadge = "Emotionale Story", DurationLabel = "26 Sek.",
                    Title = "Der Seelenanhänger – Omas letzte Zeilen für immer bei dir",
                    ProductName = "Der Seelenanhänger / Der Seelen-Talisman",
                    CapCutTemplate = "Vorlage B: Kunden-Story & Licht-Reveal (26s)", LandingPageSlug = "/l/seelen-anhaenger",
                    OnScreenHookText = "„Ich habe nur noch diese eine Geburtstagskarte von meiner Oma...“ 🤍",
                    AudioDirection = "Sanftes Piano ab Sekunde 0 + kurzes Laser-Zischen bei Sekunde 6–10",
                    Step1Setup = "Handgeschriebene Beispiel-Notiz · Seelenanhänger · Weiße Handschuhe · Westpack-Schachtel",
                    Step2Hook = "0–3s: Die handgeschriebenen Worte „Hab dich lieb“ auf Papier neben dem blanken Anhänger",
                    Step3Clips = "Clip 1: 5s Foto-Freistellung am Bildschirm · Clip 2: 8s Lasergravur · Clip 3: 10s Anhänger in Westpack-Box schließen",
                    Step4Edit = "CapCut Vorlage B · Sanfte Überblendung von der Papier-Unterschrift auf die fertige Metallgravur",
                    ReadyCaption = "Ein Foto von einem alten Brief oder einer Karte reicht aus – wir übertragen die exakte Handschrift deiner Liebsten für die Ewigkeit auf deinen Seelenanhänger. 🤍✨\n\n👉 Gleich über den Link in der Bio ausprobieren!\n\n#meinseelenfunke #handschriftgravur #erinnerungsstück #geschenkmitherz #unikat"
                },
                new() {
                    DayNumber = 7, WeekdayLabel = "Sonntag", CategoryBadge = "3D-Konfigurator Magie", DurationLabel = "21 Sek.",
                    Title = "Vom Handyfoto zum 3D-Kristall in 20 Sekunden (Live-Beweis)",
                    ProductName = "Der Seelen Kristall + Live-3D-Konfigurator",
                    CapCutTemplate = "Vorlage B: Vorher-Nachher Walkthrough (21s)", LandingPageSlug = "/l/seelen-kristall",
                    OnScreenHookText = "Viele fragen: „Wie sehe ich vorher, ob mein Foto im Kristall gut aussieht?“ 📱✨",
                    AudioDirection = "Freundliches kurzes Voiceover oder moderner Ambient-Sound + Laser-Klick",
                    Step1Setup = "Smartphone mit geöffnetem Mein-Seelenfunke 3D-Konfigurator · Fertiger Seelen Kristall daneben",
                    Step2Hook = "0–3s: Finger lädt auf dem Handy ein Foto in den 3D-Konfigurator und dreht die 3D-Vorschau",
                    Step3Clips = "Clip 1: 6s Live-3D-Vorschau am Handy · Clip 2: 6s UV-Laser in Aktion · Clip 3: 9s Handy-Vorschau neben echtem Kristall",
                    Step4Edit = "CapCut Vorlage B · Splitscreen oder direkter Match-Cut von der 3D-Handyvorschau zum echten Kristall",
                    ReadyCaption = "Kein Rätselraten: In unserem 3D-Konfigurator kannst du dein Unikat schon vor der Bestellung von allen Seiten im Licht drehen und betrachten! 📱✨\n\n👇 Probier es jetzt kostenlos mit deinem Lieblingsfoto aus (Link in Bio)!\n\n#meinseelenfunke #3dkonfigurator #personalisiertegeschenke #kristallglas #madeingermany"
                },
                new() {
                    DayNumber = 8, WeekdayLabel = "Montag", CategoryBadge = "Packaging-ASMR", DurationLabel = "13 Sek.",
                    Title = "Das Westpack White-Glove Verpackungs-Ritual (Luxury ASMR)",
                    ProductName = "Das Goldene Seelen-Herz in Westpack-Schmuckschachtel",
                    CapCutTemplate = "Vorlage A: Pure Atelier ASMR (13s)", LandingPageSlug = "/l/goldenes-seelen-herz",
                    OnScreenHookText = "Wenn schon das Auspacken Gänsehaut macht 🖤✨",
                    AudioDirection = "Reines Packaging-ASMR (Samt-Streichen, Einlegen, sattes Schachtel-Schließen)",
                    Step1Setup = "Frische weiße Handschuhe · Schwarze matte Westpack-Schachtel mit Logo · Goldenes Seelen-Herz",
                    Step2Hook = "0–2s: Weiße Handschuhe streichen langsam über das goldene Logo der schwarzen Schachtel",
                    Step3Clips = "Clip 1: 4s Polieren des goldenen Herzens · Clip 2: 5s Einsetzen in den schwarzen Schaumstoff · Clip 3: 4s Deckel schließen",
                    Step4Edit = "CapCut Vorlage A · Kein Voiceover, 100 % Fokus auf edle Optik und satte Geräusche",
                    ReadyCaption = "Jedes Schmuckstück kommt fertig zum Verschenken in unserer edlen, mattschwarzen Manufaktur-Schachtel zu dir. 🖤✨\n\n#meinseelenfunke #unboxingasmr #schmuckliebe #geschenkverpackung #luxusgeschenk"
                },
                new() {
                    DayNumber = 9, WeekdayLabel = "Dienstag", CategoryBadge = "Emotionale Story", DurationLabel = "29 Sek.",
                    Title = "Der Seelen-Talisman – Koordinaten des ersten Kennenlernens",
                    ProductName = "Der Seelen-Talisman",
                    CapCutTemplate = "Vorlage B: Kunden-Story & Licht-Reveal (29s)", LandingPageSlug = "/l/seelen-talisman",
                    OnScreenHookText = "„An genau diesem Ort hat vor 5 Jahren alles begonnen...“ 📍❤️",
                    AudioDirection = "0–4s Laser-Ton → ab Sek. 5 emotionale Akustik-/Piano-Melodie",
                    Step1Setup = "Der Seelen-Talisman · Weiße Handschuhe · xTool F2 Ultra UV",
                    Step2Hook = "0–3s: Koordinaten-Gravur blitzt unter dem Laserstrahl auf",
                    Step3Clips = "Clip 1: 7s Gravur der Koordinaten & Datum · Clip 2: 10s Rückseite mit Initialen · Clip 3: 9s Reveal im warmen Streiflicht",
                    Step4Edit = "CapCut Vorlage B · Kurze Text-Einblendung für Vorderseite (Ort) und Rückseite (Geheime Botschaft)",
                    ReadyCaption = "Der Ort eures ersten Kusses, das Datum eures Jahrestags oder ein geheimer Satz auf der Rückseite. 📍❤️\n\n👉 Gestalte deinen Seelen-Talisman jetzt über den Link in der Bio!\n\n#meinseelenfunke #paargeschenk #jahrestagsgeschenk #koordinatenschmuck #liebesbeweis"
                },
                new() {
                    DayNumber = 10, WeekdayLabel = "Mittwoch", CategoryBadge = "Makro-Technik", DurationLabel = "15 Sek.",
                    Title = "Wie kommt das Bild OHNE Kratzer mitten ins massive Glas?",
                    ProductName = "Der Seelen Kristall (UV-Laser Innengravur)",
                    CapCutTemplate = "Vorlage A: Pure Atelier ASMR (15s)", LandingPageSlug = "/l/seelen-kristall",
                    OnScreenHookText = "Wie kommt das Foto mitten ins massive Glas – obwohl außen alles glatt bleibt? 🤯✨",
                    AudioDirection = "Hochfrequentes UV-Laser-Knistern + staunender Reveal-Akkord",
                    Step1Setup = "Seelen Kristall im xTool F2 Ultra UV · Weiße Handschuhe",
                    Step2Hook = "0–3s: Extreme Nahaufnahme des violetten UV-Laserpunkts mitten im Glasblock",
                    Step3Clips = "Clip 1: 6s Tausende Mikropunkte entstehen im Glas · Clip 2: 4s Finger mit weißem Handschuh gleitet über glatte Außenseite · Clip 3: 5s Leucht-Reveal",
                    Step4Edit = "CapCut Vorlage A · Kurzer erklärender 1-Zeiler bei Sekunde 5: „5W UV-Laser bündelt Licht exakt im Kern“",
                    ReadyCaption = "Magie trifft auf deutsche Manufaktur-Präzision: Tausende feinste Lichtpunkte schweben mitten im massiven K9-Kristall – unzerstörbar für die Ewigkeit. ✨💎\n\n#meinseelenfunke #uvlaser #3dkristall #technikfakten #geschenkideen"
                },
                new() {
                    DayNumber = 11, WeekdayLabel = "Donnerstag", CategoryBadge = "Haustier-Liebe", DurationLabel = "25 Sek.",
                    Title = "Die Seelen-Pfote + Seelenanhänger im Partner-Look (Mensch & Hund)",
                    ProductName = "Die Seelen-Pfote & Der Seelenanhänger",
                    CapCutTemplate = "Vorlage B: Kunden-Story & Licht-Reveal (25s)", LandingPageSlug = "/l/seelen-pfote",
                    OnScreenHookText = "Einer für sein Halsband – und einer für deinen Schlüsselbund 🐾🔑",
                    AudioDirection = "Laser-Duett (2 schnelle Schnitte) + herzerwärmende Instrumentalmusik",
                    Step1Setup = "1x Seelen-Pfote + 1x Seelenanhänger · Weiße Handschuhe · Schwarzes Schiefer-Tablett",
                    Step2Hook = "0–3s: Beide Rohlinge liegen nebeneinander unter dem Laser-Positionierkreuz",
                    Step3Clips = "Clip 1: 7s Laser graviert beide Stücke · Clip 2: 8s Nebeneinander auf Schiefer anrichten · Clip 3: 10s Nahaufnahme der feinen Details",
                    Step4Edit = "CapCut Vorlage B · Am Ende Hinweis auf das Set-Geschenk einblenden",
                    ReadyCaption = "Damit ihr immer miteinander verbunden seid – egal wohin der Weg führt. 🐾🤍\n\n👇 Entdecke unsere Seelen-Pfote und den passenden Anhänger über den Link in der Bio!\n\n#meinseelenfunke #hundemama #hundepapa #pfotenabdruck #hundezubehör"
                },
                new() {
                    DayNumber = 12, WeekdayLabel = "Freitag", CategoryBadge = "Bestseller-Vergleich", DurationLabel = "19 Sek.",
                    Title = "Goldenes Seelen-Herz vs. Seelen Kristall – Welcher Typ bist du?",
                    ProductName = "Das Goldene Seelen-Herz & Der Seelen Kristall",
                    CapCutTemplate = "Vorlage A: Kommentar-Trigger (19s)", LandingPageSlug = "/linktree",
                    OnScreenHookText = "Team 1 (14k Gold am Herzen tragen) oder Team 2 (Leuchtender 3D-Kristall)? 👇✨",
                    AudioDirection = "Edler Lounge-/Piano-Beat + 2 markante Sound-Effekte beim Wechsel",
                    Step1Setup = "Goldenes Seelen-Herz in Westpack-Box + Leuchtender Seelen Kristall daneben · Weiße Handschuhe",
                    Step2Hook = "0–3s: Beide fertigen Meisterstücke liegen im abgedunkelten Gold-Streiflicht nebeneinander",
                    Step3Clips = "Clip 1: 7s Nahaufnahme Nr. 1 (Goldenes Herz) · Clip 2: 7s Nahaufnahme Nr. 2 (Seelen Kristall) · Clip 3: 5s Beide im Bild + Frage",
                    Step4Edit = "CapCut Vorlage A · Große goldene „1“ und „2“ einblenden für maximale Kommentar-Rate",
                    ReadyCaption = "Schreib es in die Kommentare: Würdest du deine Erinnerung lieber als 14k vergoldetes Herz bei dir tragen (1) oder als leuchtenden 3D-Kristall aufstellen (2)? ✨👇\n\n#meinseelenfunke #geschenkefürfrauen #erinnerungsgeschenk #schmuckoderdeko #unikat"
                },
                new() {
                    DayNumber = 13, WeekdayLabel = "Samstag", CategoryBadge = "Hochzeit & Paare", DurationLabel = "27 Sek.",
                    Title = "Der Seelen Kristall – Das Hochzeitsgeschenk, bei dem die Braut weinte",
                    ProductName = "Der Seelen Kristall (Hochzeits-Edition)",
                    CapCutTemplate = "Vorlage B: Kunden-Story & Licht-Reveal (27s)", LandingPageSlug = "/l/seelen-kristall",
                    OnScreenHookText = "Wenn Geld im Umschlag zur Hochzeit einfach zu unpersönlich ist... 💍✨",
                    AudioDirection = "2s Laser-Start → ab Sek. 3 emotionale Hochzeits-Streicher/Piano",
                    Step1Setup = "Seelen Kristall · Weiße Handschuhe · Geschenkverpackung",
                    Step2Hook = "0–3s: Text-Hook über dem glitzernden Schliff des K9-Kristalls",
                    Step3Clips = "Clip 1: 8s Gravur des Brautpaars + Hochzeitsdatum · Clip 2: 9s Licht-Reveal im Dunkeln · Clip 3: 7s Sicheres Verpacken für den großen Tag",
                    Step4Edit = "CapCut Vorlage B · Fokus auf die funkelnden Facetten des K9-Kristalls im Streiflicht",
                    ReadyCaption = "Geldgeschenke sind nach einer Woche ausgegeben – dieser Moment bleibt ein Leben lang. 💍🤍\n\n👉 Verewige das schönste Hochzeitsfoto jetzt in unserem 3D-Konfigurator (Link in Bio)!\n\n#meinseelenfunke #hochzeitsgeschenk #brautpaar #personalisiertehochzeit #3dglasgravur"
                },
                new() {
                    DayNumber = 14, WeekdayLabel = "Sonntag", CategoryBadge = "Gründerin & Manufaktur", DurationLabel = "31 Sek.",
                    Title = "Sonntags in der Manufaktur Gifhorn: Eure Wochenend-Bestellungen entstehen",
                    ProductName = "Alle Bestseller (Werkstatt-Rundgang)",
                    CapCutTemplate = "Vorlage C: Gründer-Einblick & Batch-Produktion (31s)", LandingPageSlug = "/linktree",
                    OnScreenHookText = "Während draußen Sonntag ist, entstehen hier eure Herzensgeschenke 🤍🧤",
                    AudioDirection = "Mix aus echten Werkstatt-Sounds + ruhigem Voiceover / Piano",
                    Step1Setup = "Saubere Werkbank · Weiße Handschuhe · Mehrere Westpack-Schachteln & Kristall-Kartons bereitstellen",
                    Step2Hook = "0–3s: Du ziehst die weißen Handschuhe an und schaltest den xTool F2 Ultra UV ein",
                    Step3Clips = "Clip 1: 10s Verschiedene Kundenstücke unter dem Laser · Clip 2: 10s Qualitätskontrolle im Licht · Clip 3: 8s Stapel fertiger Westpack-Boxen",
                    Step4Edit = "CapCut Vorlage C · Zeigt Verlässlichkeit, echte Handarbeit in Deutschland und Schnelligkeit",
                    ReadyCaption = "Hinter jeder einzelnen Bestellung bei Mein Seelenfunke steckt keine anonyme Massenfabrik, sondern echte Handarbeit und Herzblut hier in Gifhorn. 🤍🧤 Danke für jede einzelne Geschichte, die wir für euch gravieren dürfen!\n\n#meinseelenfunke #gifhorn #manufaktur #kleinunternehmen #mitliebegemacht"
                }
            };

            // 2. DIE 20 BESTEN BENCHMARK-ACCOUNTS (TIKTOK & INSTAGRAM) + JE 10 VIRAL-VIDEOS (= 200 VIDEOS)
            var rawAccounts = new (int Rank, string Name, string Handle, string Platform, string Cluster, string ClusterLabel, string Followers, string AvgLen, string Bio, string Web, string Gbp, string[] Secrets, string BaseProduct, int RecipeDay)[]
            {
                (1, "PicturesOnGold", "@picturesongold", "TikTok & IG", "schmuck", "1. Gravur- & Erinnerungsschmuck", "485K", "16s",
                    "Zeile 1: Nummer 1 für Foto- & Handschrift-Schmuck | Zeile 2: Lebenslange Gravur-Garantie | Zeile 3: Versand in 24h | Zeile 4: Direktlink in Medaillon-Konfigurator",
                    "Eigener High-Speed-Shop mit sofortigem Foto-Upload direkt auf der Produktseite (1 Klick aus der Bio).",
                    "4,9 ★ (1.800+ Rezensionen) mit hunderten Kundenfotos ausgepackter Medaillons und Werkstatt-Kurzvideos.",
                    new[] { "Zeigt in Sekunde 0 immer den Original-Zettel der Oma neben dem Gold-Herz", "Laser läuft im Video in 2,5x Geschwindigkeit mit echtem Sound", "Weiße Handschuhe beim Schließen des Medaillons" },
                    "Goldenes Seelen-Herz", 1),

                (2, "Customodish", "@customodish", "TikTok & IG", "schmuck", "1. Gravur- & Erinnerungsschmuck", "620K", "14s",
                    "Zeile 1: Trag deine Liebsten immer bei dir ✨ | Zeile 2: Handgefertigter Haustier- & Foto-Schmuck | Zeile 3: Über 250.000 glückliche Kunden | Zeile 4: Linktree mit 3 Top-Kategorien",
                    "Mobile-First Shop mit Vorher-Nachher-Slider und Express-Checkout (Apple Pay / PayPal direkt unter dem Upload).",
                    "4,8 ★ mit Fokus auf Haustier-Gedenkschmuck-Keywords und emotionalen Foto-Bewertungen.",
                    new[] { "nutzt extrem kurze 12–15s Loops ohne ein einziges gesprochenes Wort", "Fokus auf verstorbene Hunde/Katzen sorgt für tausende Markierungen in Kommentaren", "Edle dunkle Schachtel als Kontrast zum goldenen Anhänger" },
                    "Die Seelen-Pfote", 4),

                (3, "Mignon & Mignon", "@mignonandmignon", "Instagram & TikTok", "schmuck", "1. Gravur- & Erinnerungsschmuck", "310K", "22s",
                    "Zeile 1: Personalisierte Geschenke, die Tränen der Freude auslösen 🤍 | Zeile 2: 14k Gold & Edelstahl Atelier | Zeile 3: Gefertigt in unserem Studio | Zeile 4: Direktlink zu Bestsellern",
                    "Cleanes Luxus-Design in Schwarz/Gold (#C5A059-Ästhetik), Geschenkbox-Upgrade direkt im Warenkorb.",
                    "4,9 ★ mit konsistenten Studio-Fotos der Geschenkverpackung und schnellen Inhaber-Antworten.",
                    new[] { "Erzählt in 2 Textzeilen die Geschichte hinter der Bestellung (z. B. Brautstrauß-Anhänger für verstorbenen Vater)", "Warmes goldenes Streiflicht auf dunklem Untergrund", "Jedes Video endet mit dem Einlegen in die schwarze Schmuckbox" },
                    "Der Seelenanhänger", 6),

                (4, "SilverCut Official", "@silvercut_official", "Instagram & TikTok", "schmuck", "1. Gravur- & Erinnerungsschmuck", "290K", "26s",
                    "Zeile 1: Dein Foto als ewiges Schmuckstück 🐾 | Zeile 2: Präzisions-Gravur aus echtem Metall | Zeile 3: 100.000+ 5-Sterne Bewertungen | Zeile 4: Jetzt Foto testen 👇",
                    "Eigener Konfigurator mit automatischer Hintergrund-Freistellung des Haustierfotos.",
                    "4,8 ★ im europäischen Markt; starke Verknüpfung zwischen Google Shopping Sterne-Snippets und Reels.",
                    new[] { "Kombiniert 5 Sek. Werkstatt-Laser mit 10 Sek. echter weinender Kundenreaktion beim Auspacken", "Sehr hohe Kommentar-Rate durch die Frage: 'Welche Rasse sollen wir als Nächstes gravieren?'", "Klare Vorher-Foto zu Nachher-Gravur Gegenüberstellung" },
                    "Die Seelen-Pfote", 11),

                (5, "Caitlyn Minimalist", "@caitlynminimalist", "Instagram & TikTok", "schmuck", "1. Gravur- & Erinnerungsschmuck", "1.1M", "15s",
                    "Zeile 1: Everyday Personalized Jewelry ✨ | Zeile 2: Custom Handwriting & Memorial Pieces | Zeile 3: Gift Ready Packaging | Zeile 4: Shop Our Bestsellers",
                    "Eigener Flagship-Shop + Marktplatz-Präsenz; extrem reduzierte Produktseiten ohne Ablenkung.",
                    "4,9 ★ mit über 10.000 Bewertungen; nutzt automatisierte Nachkauf-Mails für Foto-Reviews.",
                    new[] { "Absolute Ruhe im Bild: Kein hektisches Wackeln, nur Makro-Stativ und sanfte Handbewegungen", "Starker Kontrast zwischen alter verwackelter Handschrift und hochglänzendem 14k Gold", "Pinned Video #1 zeigt in 14 Sek., wie man ein Handyfoto von einer Unterschrift einschickt" },
                    "Goldenes Seelen-Herz", 1),

                (6, "ArtPix 3D", "@artpix3d", "TikTok & IG", "kristall", "2. 3D-Kristallglas & Licht-Magie", "540K", "18s",
                    "Zeile 1: Watch Your Photos Come To Life in 3D Crystal 💎 | Zeile 2: High-Grade K9 Crystal | Zeile 3: Made to Order in 24h | Zeile 4: 3D-Preview Link",
                    "Dedizierte Landingpages pro Anlass (/pet-memorial, /wedding, /family) mit Live-3D-Vorschau und LED-Sockel-Upsell.",
                    "4,8 ★ (3.200+ Google Reviews); Google Business zeigt 360°-Einblick in die Laser-Produktion.",
                    new[] { "Der 'Lights-Off'-Moment: Bei Sekunde 9 wird das Raumlicht gelöscht und der LED-Sockel eingeschaltet", "Zeigt den 360°-Effekt, indem der Kristall langsam mit weißen Handschuhen gedreht wird", "Verkauft zu fast 65 % den LED-Leuchtsockel als Zusatzartikel im Warenkorb" },
                    "Der Seelen Kristall", 2),

                (7, "3DCrystal Official", "@3dcrystal", "TikTok & IG", "kristall", "2. 3D-Kristallglas & Licht-Magie", "780K", "31s",
                    "Zeile 1: The Original 3D Subsurface Laser Atelier ✨ | Zeile 2: Turning Memories into Heirlooms | Zeile 3: Founder-Led Craftsmanship | Zeile 4: Create Yours Now",
                    "Hochkonvertierender Shop mit Video-Header und Vertrauens-Siegel direkt neben dem Upload-Button.",
                    "4,9 ★ Google Business Profil mit wöchentlichen Werkstatt-Updates und Antworten vom Gründer.",
                    new[] { "Gründer steht selbst am UV-Laser und erklärt in 20 Sek. voller Stolz die Kundenbestellung", "Extreme Nahaufnahme, wie der UV-Laser Schicht für Schicht im Glasinneren aufbaut", "Kombiniert Technik-Faszination ('Wie geht das ohne das Glas außen zu verletzen?') mit Emotion" },
                    "Der Seelen Kristall", 10),

                (8, "Masterpiece Laser", "@masterpiecelaser", "TikTok & IG", "kristall", "2. 3D-Kristallglas & Licht-Magie", "410K", "13s",
                    "Zeile 1: Mesmerizing 3D Laser Art inside Pure Crystal 🔮 | Zeile 2: Custom Portraits & Trophies | Zeile 3: Worldwide Insured Shipping | Zeile 4: Direct Configurator",
                    "Ultraschnelle mobile Landingpage mit 3 festen Kristallgrößen und 1-Klick-Foto-Upload.",
                    "4,9 ★ mit Fokus auf '3D Glasgravur Geschenk' für Google Local & Shopping.",
                    new[] { "100 % Fokus auf das hypnotische violette UV-Laserlicht und das feine Knistern im Glas", "Nahtloser Loop: Das Ende des Videos springt unbemerkt wieder zum ersten Laserblitz zurück", "Hohe Watchtime (>110 %), weil Zuschauer raten wollen, welches Motiv gerade im Glas entsteht" },
                    "Der Seelen Kristall", 10),

                (9, "CrystalClever Atelier", "@crystalclever", "Instagram & TikTok", "kristall", "2. 3D-Kristallglas & Licht-Magie", "230K", "25s",
                    "Zeile 1: Unvergessliche Erinnerungen in K9-Kristall 🤍 | Zeile 2: Hochzeit · Familie · Sternenkinder · Haustiere | Zeile 3: Mit weißen Handschuhen geprüft | Zeile 4: Zum 3D-Shop",
                    "Empathisch gestaltete Themen-Landingpages mit echten Kundenstimmen und gepolsterter Geschenkbox.",
                    "5,0 ★ Google-Profil durch persönliche Dankeskarte mit QR-Code in jedem versendeten Paket.",
                    new[] { "Nutzt ausschließlich weiße Baumwollhandschuhe beim Herausnehmen des Kristalls aus der Maschine", "Hält das Original-Kundenfoto auf dem Handy direkt neben den fertig beleuchteten Kristall", "Starker Fokus auf Hochzeits- und Jahrestagsgeschenke ('Besser als Geld im Umschlag')" },
                    "Der Seelen Kristall", 13),

                (10, "3D Glass Atelier", "@3dglassatelier", "TikTok", "kristall", "2. 3D-Kristallglas & Licht-Magie", "350K", "16s",
                    "Zeile 1: Dein 2D Handyfoto -> Echtes 3D im Glas 📱💎 | Zeile 2: Kostenlose Live-3D-Vorschau im Shop | Zeile 3: Express-Manufaktur | Zeile 4: Jetzt Foto testen 👇",
                    "Fokus auf den mobilen 3D-Viewer: Der Kunde sieht sofort im Browser sein 3D-Modell.",
                    "4,8 ★ mit kurzen Video-Clips der 3D-Konfigurator-Bedienung im Google-Profil.",
                    new[] { "Startet mit einer Bildschirmaufnahme des 3D-Konfigurators am Handy (2 Sek.) und schneidet direkt auf den echten Laser", "Nimmt den Kunden die Angst ('Sieht mein Foto gut aus?'), indem die 3D-Vorschau demonstriert wird", "Sehr hohe Klickrate auf den Bio-Link, weil Nutzer ihr eigenes Foto im 3D-Tool ausprobieren wollen" },
                    "Der Seelen Kristall", 7),

                (11, "LaserEngravingASMR", "@laserengravingasmr", "TikTok", "laser", "3. Laser, Metall, Glas & Schiefer-ASMR", "1.4M", "12s",
                    "Zeile 1: Pure Laser Sounds & Precision Engraving 🔊⚡ | Zeile 2: Custom Metal, Gold & Slate | Zeile 3: Turn Sound ON 🎧 | Zeile 4: Custom Orders Link",
                    "Schlanker Direkt-Shop für personalisierte Metall-Anhänger, Hundemarken und Schieferplatten.",
                    "4,9 ★ als spezialisiertes Laser-Gravur-Studio gelistet.",
                    new[] { "Keinerlei Musik in den ersten 8 Sekunden – nur das knackige, melodische Singen des Lasers", "Richtet das Mikrofon im 45°-Winkel nah am Schutzgehäuse aus für satten Sound", "Millionen Views durch Zuschauer, die das Video wegen des Klangs 3–4 Mal hintereinander hören" },
                    "Die Seelen-Pfote", 4),

                (12, "EngraveIt Studio", "@engraveitstudio", "TikTok & IG", "laser", "3. Laser, Metall, Glas & Schiefer-ASMR", "390K", "17s",
                    "Zeile 1: Custom Glassware & Metal Gifts 🍺✨ | Zeile 2: Precision Rotary UV & Fiber Laser | Zeile 3: Made for Legends | Zeile 4: Personalize Yours",
                    "Themen-Landingpages für Männergeschenke, Vatertag, Trauzeugen und Vereins-Gläser (Mengenrabatt).",
                    "4,9 ★ mit B2C- und B2B-Bewertungen (Firmen- und Vereinsbestellungen).",
                    new[] { "Inszeniert das Rotationsmodul (wie xTool RA3) bei Weizengläsern wie ein Raumschiff im Gegenlicht", "Wickelt nach dem Lasern ein feuchtes Tuch ab bzw. hebt das Glas ins goldene Streiflicht", "Positioniert personalisierte Weizengläser als das ultimative Feierabend- & Männergeschenk" },
                    "Der Feierabend-Funke", 5),

                (13, "BlackMetalAtelier", "@blackmetalatelier", "TikTok & IG", "laser", "3. Laser, Metall, Glas & Schiefer-ASMR", "460K", "14s",
                    "Zeile 1: Luxury Engraved Talisman & Metal Keepsakes 🖤 | Zeile 2: Never Fades · Waterproof | Zeile 3: Handcrafted Precision | Zeile 4: Design Your Talisman",
                    "Dark-Mode E-Commerce-Shop (exakt wie Mein Seelenfunke #0F1117 / #C5A059) mit Sofort-Textvorschau.",
                    "4,8 ★ mit Fotos von Schlüsselbund-Talismanen und Koordinaten-Anhängern.",
                    new[] { "Nutzt dunklen Schiefer als Untergrund, damit Funkenflug und Gold-/Edelstahl-Glanz maximal leuchten", "Graviert Koordinaten des Kennenlern-Ortes + geheime Nachricht auf die Rückseite", "Jeder Handgriff erfolgt mit schwarzen oder blütenweißen Juwelierhandschuhen" },
                    "Der Seelen-Talisman", 9),

                (14, "SlateCraft Laser", "@slatecraftlaser", "TikTok & IG", "laser", "3. Laser, Metall, Glas & Schiefer-ASMR", "275K", "19s",
                    "Zeile 1: Deine Fotos auf echtem Naturschiefer & Kristall 🪨✨ | Zeile 2: Handverlesene Unikate | Zeile 3: Wetterfest & für die Ewigkeit | Zeile 4: Foto-Check im Shop",
                    "Kombiniert Schiefer-Gedenktafeln, Kristallblöcke und Schmuck in gemeinsamen Geschenk-Sets.",
                    "4,9 ★ regionales Manufaktur-Profil mit starker lokaler & überregionaler Strahlkraft.",
                    new[] { "Zeigt die rauen Bruchkanten des Naturschiefers im Streiflicht vor dem Start des Lasers", "Verbindet Schiefer, Glas und Edelmetall als zusammengehörige Manufaktur-Welt", "Antwortet auf fast jeden Kommentar mit einem kurzen 12-Sekunden-Werkstattvideo" },
                    "Der Seelen Kristall", 14),

                (15, "xTool Master Creators", "@xtool.official", "TikTok & IG", "laser", "3. Laser, Metall, Glas & Schiefer-ASMR", "690K", "20s",
                    "Zeile 1: Next-Gen UV & Dual-Laser Craftsmanship ⚡ | Zeile 2: K9 Crystal · 14k Gold · Rotary Glass | Zeile 3: Ultra-Fine Precision | Zeile 4: Showcase & Shop",
                    "Moderne Produkt-Showcases mit Fokus auf die Überlegenheit von 5W UV-Laser-Innengravur und feinstem Schmuck.",
                    "4,9 ★ globale Referenz für High-End Laser-Veredelung.",
                    new[] { "Macht die High-Tech-Maschine (F2 Ultra UV + RA3) selbst zum Helden der Qualität", "Zeigt den Unterschied zwischen billiger Oberflächengravur und edler UV-/Tiefengravur unter der Lupe", "Perfekte Ausleuchtung des Maschinenraums mit warmem Akzentlicht" },
                    "Der Feierabend-Funke", 5),

                (16, "WhiteGlove Atelier", "@whitegloveatelier", "TikTok & IG", "luxury", "4. White-Glove Luxury & Packaging", "510K", "16s",
                    "Zeile 1: Bespoke Heirlooms · White-Glove Standard 🧤✨ | Zeile 2: 14k Gold & Crystal Keepsakes | Zeile 3: Complimentary Luxury Box | Zeile 4: Private Atelier Shop",
                    "Ultra-cleaner Luxus-Shop ohne Rabatt-Schreierei; Fokus auf Wertigkeit, Schachtel und Handarbeit.",
                    "5,0 ★ Boutique-Profil; Kunden loben besonders die edle Verpackung und das Auspack-Erlebnis.",
                    new[] { "Kein Produkt wird jemals mit bloßen Fingern berührt – immer blütenweiße Baumwollhandschuhe", "Verwendet feine Naturhaarpinsel und Messing-Pinzetten zur Qualitätsprüfung vor der Kamera", "Hebt den wahrgenommenen Wert eines 49–69 € Produkts optisch auf 250 € Juwelier-Niveau" },
                    "Goldenes Seelen-Herz", 8),

                (17, "LuxuryBox Ritual", "@luxuryboxritual", "TikTok & IG", "luxury", "4. White-Glove Luxury & Packaging", "330K", "14s",
                    "Zeile 1: The Art of Gifting 🖤✨ | Zeile 2: Personalized Jewelry in Matte Black Signature Boxes | Zeile 3: Ready to Gift | Zeile 4: Order Yours",
                    "Fokus auf 'Sofort verschenkfertig' – zeigt die schwarze matte Schmuckschachtel auf jedem Produktbild mit an.",
                    "4,9 ★ mit unzähligen Unboxing-Reels von glücklichen Beschenkten.",
                    new[] { "Widmet die letzten 4 Sekunden jedes Videos exklusiv der mattschwarzen Schmuckschachtel mit Logodruck", "Lässt das satte Schließgeräusch der Schachtel als akustisches Markenzeichen am Videoende stehen", "Steigert die Kaufrate vor Weihnachten, Valentinstag und Muttertag massiv, weil keine Verpackung mehr nötig ist" },
                    "Goldenes Seelen-Herz", 8),

                (18, "The Bespoke Jeweler", "@thebespokejeweler", "Instagram & TikTok", "luxury", "4. White-Glove Luxury & Packaging", "440K", "35s",
                    "Zeile 1: Handcrafted with Obsessive Perfection 🔍✨ | Zeile 2: Custom Engraved Keepsakes | Zeile 3: Studio Quality Guarantee | Zeile 4: Start Your Custom Order",
                    "Persönlich geführter Manufaktur-Shop mit Gründer-Foto und '100 % Zufriedenheits-Versprechen'.",
                    "5,0 ★ mit ausführlichen Erfahrungsberichten über den persönlichen Kontakt und die Qualität.",
                    new[] { "Das viralste Format (über 6 Mio. Views): 'Warum dieses Stück meine Qualitätskontrolle nicht bestanden hat'", "Schafft blindes Vertrauen, weil Zuschauer sehen, wie penibel jedes Stück unter der Lupe geprüft wird", "Kombiniert ruhige Gründer-Stimme mit edlen Makro-B-Roll-Aufnahmen aus der Werkstatt" },
                    "Manufaktur-Qualitätscheck", 3),

                (19, "MemorialCraft Studio", "@memorialcraftstudio", "Instagram & TikTok", "luxury", "4. White-Glove Luxury & Packaging", "380K", "28s",
                    "Zeile 1: Keeping Loved Ones Close Forever 🤍🕊️ | Zeile 2: Memorial Crystals, Paw Prints & Handwriting | Zeile 3: Handcrafted with Respect & Care | Zeile 4: Memorial Collection",
                    "Sanftes, würdevolles Shop-Design mit eigener Kategorie für Trauer- & Haustier-Erinnerungsstücke.",
                    "5,0 ★ – extrem treue Weiterempfehlungs-Community durch einfühlsamen Umgang mit Erinnerungsstücken.",
                    new[] { "Behandelt jedes Kundenstück im Video mit sichtbarer Ehrfurcht (langsame Bewegungen, weiße Handschuhe, gedämpftes Licht)", "Verzichtet bei Trauer-/Gedenk-Videos auf laute Trend-Musik und nutzt nur warmes Klavier + echten Werkstatt-Ton", "Überdurchschnittlich hohe Weiterleitungs-Rate (Shares per WhatsApp innerhalb von Familien)" },
                    "Der Seelen Kristall", 2),

                (20, "Heirloom Engravers", "@heirloomengravers", "Instagram & TikTok", "luxury", "4. White-Glove Luxury & Packaging", "295K", "23s",
                    "Zeile 1: Modern Laser Precision · Timeless Heirlooms ✨ | Zeile 2: Crystal · Gold · Glass · Metal | Zeile 3: 5.0 ★ Rated Atelier | Zeile 4: Configure in 3D 👇",
                    "Verbindet Bestseller-Landingpages mit echten Google-Bewertungs-Kacheln direkt unter dem Konfigurator.",
                    "5,0 ★ – bindet seine besten Google-Bewertungen direkt als Social-Proof-Einblendung in Reels und Ads ein.",
                    new[] { "Blendet bei Sekunde 3 kurz den echten Kundenwunsch oder eine 5-Sterne-Bewertung als Story-Einstieg ein", "Zeigt die komplette Bandbreite (Gold-Herz, 3D-Kristall, Weizenglas) unter einer einheitlichen schwarz-goldenen Bildsprache", "Schaltet nur die Top 10 % seiner organischen Videos als Spark Ads auf maßgeschneiderte Landingpages" },
                    "Alle Bestseller", 12)
            };

            foreach (var a in rawAccounts)
            {
                var dto = new TopCompetitorAccountDto
                {
                    Rank = a.Rank,
                    Name = a.Name,
                    Handle = a.Handle,
                    Platform = a.Platform,
                    Cluster = a.Cluster,
                    ClusterLabel = a.ClusterLabel,
                    Followers = a.Followers,
                    AvgVideoLength = a.AvgLen,
                    BioStructure = a.Bio,
                    WebsiteFunnel = a.Web,
                    GoogleBusinessProfile = a.Gbp,
                    WinningSecrets = a.Secrets.ToList(),
                    TopVideos = BuildTenViralVideosForAccount(a.Name, a.BaseProduct, a.AvgLen, a.RecipeDay)
                };
                vault.TopAccounts.Add(dto);
            }

            // 3. DER 37-PUNKTE-RÖNTGENKATALOG (7 KERNFRAGEN + 30 TIEFENFRAGEN)
            vault.QuestionsCatalog = new List<MarketQuestionItemDto>
            {
                // Basis-Fragen 1–7
                new() { Number = 1, CategoryKey = "base", CategoryName = "Basis-Analyse", Question = "Wie lang sind die erfolgreichsten Videos?", MarketFinding = "ASMR-Laserclips: 11–16 Sek. | Emotionale Kunden-Storys: 24–32 Sek. | Gründer-Einblicke: 30–42 Sek.", SeelenfunkeRule = "Reine Laser-/Handschuh-Videos strikt bei 13–15 Sek. halten; Story-Videos bei 26–30 Sek." },
                new() { Number = 2, CategoryKey = "base", CategoryName = "Basis-Analyse", Question = "Was ist der exakte Inhalt der Top-Videos?", MarketFinding = "Immer nur 1 Produkt + 1 klares Gefühl pro Video (Entstehung vom Kundenfoto bis zum Licht-Reveal).", SeelenfunkeRule = "Nie mehrere Produkte durcheinander mischen – 1 Bestellung = 1 fokussiertes Video." },
                new() { Number = 3, CategoryKey = "base", CategoryName = "Basis-Analyse", Question = "Welche Sounds werden verwendet?", MarketFinding = "Sekunde 0–3 immer 100 % echter Werkstatt-Sound (Laser-Singen/Box-Klacken), ab Reveal warmes Piano/Strings.", SeelenfunkeRule = "Dual-Layer-Audio in CapCut: Erst knackiges xTool-ASMR (0 dB), beim Aufleuchten Piano (-8 dB)." },
                new() { Number = 4, CategoryKey = "base", CategoryName = "Basis-Analyse", Question = "Welche visuellen Besonderheiten stechen sofort ins Auge?", MarketFinding = "Blütenweiße Juwelierhandschuhe, schwarzer Untergrund, warmes Streiflicht und edle mattschwarze Schachtel.", SeelenfunkeRule = "Jeder Handgriff mit weißen Handschuhen + Abschluss in der schwarzen Westpack-Schmuckschachtel." },
                new() { Number = 5, CategoryKey = "base", CategoryName = "Basis-Analyse", Question = "Wie sieht die Bio des Accounts aus (Aufbau, Struktur, Links)?", MarketFinding = "TikTok hat ein striktes 80-Zeichen-Limit (3 ultrakurze Zeilen); Instagram erlaubt 150 Zeichen (inkl. 4. CTA-Zeile).", SeelenfunkeRule = "TikTok (66/80 Zeichen): '✨ Unikate / 🤍 Glas, Schiefer & Metall / 🧤 Handveredelt aus Deutschland' (+ 4. CTA-Zeile auf Instagram)." },
                new() { Number = 6, CategoryKey = "base", CategoryName = "Basis-Analyse", Question = "Haben sie eigene Webseiten und wie funktionieren diese?", MarketFinding = "100 % eigene Mobile-First-Shops mit direktem Foto-Upload ohne Umweg über eine kalte Startseite.", SeelenfunkeRule = "Aus Videos und Ads immer direkt auf die passende Produkt-Landingpage (/l/...) mit 3D-Konfigurator leiten." },
                new() { Number = 7, CategoryKey = "base", CategoryName = "Basis-Analyse", Question = "Wie sieht deren Google Business Profil aus?", MarketFinding = "4,8–5,0 Sterne, echte Werkstatt-Fotos, Kurzvideos der Lasergravur und aktive Nachkauf-Bewertungsschleife.", SeelenfunkeRule = "Google Business für Manufaktur Gifhorn mit Werkstatt-Videos pflegen + QR-Code-Dankeskarte im Paket." },

                // A. Hook-Psychologie (8–12)
                new() { Number = 8, CategoryKey = "hook", CategoryName = "Hook (0–3s)", Question = "Welches Bild sieht man im allerersten Frame (Sekunde 0,0)?", MarketFinding = "Kein leeres Intro: Entweder blitzt bereits der Laserstrahl oder weiße Handschuhe halten Foto + Rohling.", SeelenfunkeRule = "In CapCut die ersten 0,5 Sekunden Leerlauf wegschneiden – Frame 1 startet mitten in der Bewegung." },
                new() { Number = 9, CategoryKey = "hook", CategoryName = "Hook (0–3s)", Question = "Welcher Text-Hook steht in den ersten 2 Sekunden im Bild?", MarketFinding = "Ein einziger emotionaler Satz in Augenhöhe (z. B. 'Als sie die letzte Notiz ihrer Oma schickte...').", SeelenfunkeRule = "Weiße Schrift mit dezenter abgerundeter dunkler Box oder Gold-Highlight (#C5A059) im oberen Drittel." },
                new() { Number = 10, CategoryKey = "hook", CategoryName = "Hook (0–3s)", Question = "Welcher akustische Reiz stoppt das Weiterwischen in Sekunde 1?", MarketFinding = "Das hochfrequente Zischen des Lasers oder das satte Klacken beim Öffnen der Schmuckschachtel.", SeelenfunkeRule = "Mikrofon nah an die xTool-Haube: Sekunde 0 startet mit einem klaren akustischen Signal." },
                new() { Number = 11, CategoryKey = "hook", CategoryName = "Hook (0–3s)", Question = "Wie bewegt sich die Kamera in der ersten Einstellung?", MarketFinding = "Entweder extrem ruhiges Makro-Stativ (Fokus auf Laser) oder sanfte Bewegung der weißen Handschuhe ins Licht.", SeelenfunkeRule = "Kein hektisches Verwackeln – Kamera fest fixieren, Bewegung kommt durch Laser und Hände." },
                new() { Number = 12, CategoryKey = "hook", CategoryName = "Hook (0–3s)", Question = "Wie funktioniert der nahtlose Loop am Videoende?", MarketFinding = "Das Video endet exakt nach dem Reveal ohne Abspann, sodass es sofort wieder bei Sekunde 0 einrastet.", SeelenfunkeRule = "Kein 'Tschüss'-Outro am Ende – letzter Frame = geschlossene Westpack-Box -> springt direkt zum Start." },

                // B. Kamera, Schnitt & White-Glove (13–18)
                new() { Number = 13, CategoryKey = "camera", CategoryName = "Kamera & Handschuhe", Question = "Wie schnell ist der Schnitt-Rhythmus (Pacing)?", MarketFinding = "Schnelle 1,0–1,5 Sek. Schnitte während der Fertigung, aber 3,5–5,0 Sek. Ruhe beim finalen Licht-Reveal.", SeelenfunkeRule = "Prozess zügig schneiden, aber dem fertigen Kunstwerk im Licht mindestens 4 Sekunden Ruhe gönnen." },
                new() { Number = 14, CategoryKey = "camera", CategoryName = "Kamera & Handschuhe", Question = "Wie wird das Licht beim Reveal inszeniert?", MarketFinding = "Dramatischer Kontrast: Arbeitslicht aus -> nur noch der LED-Sockel oder warmes Seitenlicht beleuchtet das Werkstück.", SeelenfunkeRule = "Beim Seelen Kristall für die letzte Aufnahme das Raumlicht löschen und nur von unten/seitlich beleuchten." },
                new() { Number = 15, CategoryKey = "camera", CategoryName = "Kamera & Handschuhe", Question = "Welche Requisiten steigern den wahrgenommenen Produktwert?", MarketFinding = "Weiße Baumwollhandschuhe, feiner Pinsel, Lupe, schwarzer Schiefer und mattschwarze Logobox.", SeelenfunkeRule = "Immer weiße Handschuhe + Westpack-Schachtel im Bild haben – macht aus 69 € gefühlte 200 € Luxus." },
                new() { Number = 16, CategoryKey = "camera", CategoryName = "Kamera & Handschuhe", Question = "Wie viel Prozent des Videos sind extreme Makro-Aufnahmen?", MarketFinding = "Über 70 % sind Nahaufnahmen (Gravurtiefe, Funken, Glas-Facetten), damit Details auf dem Handybildschirm wirken.", SeelenfunkeRule = "Mit 2x/3x optischem Zoom nah ans Werkstück gehen, statt den ganzen Tisch aus der Ferne zu filmen." },
                new() { Number = 17, CategoryKey = "camera", CategoryName = "Kamera & Handschuhe", Question = "Wie werden Originalton und Musik abgemischt (Audio-Ducking)?", MarketFinding = "Originalton beim Lasern auf 100 % (0 dB), beim Einsetzen der Reveal-Musik sinkt der Raumton auf -18 dB.", SeelenfunkeRule = "Feste Lautstärke-Kurve in den 3 CapCut-Master-Vorlagen hinterlegen." },
                new() { Number = 18, CategoryKey = "camera", CategoryName = "Kamera & Handschuhe", Question = "Welcher Farblook (Color Grading) konvertiert am besten?", MarketFinding = "Tiefes Schwarz mit warmen Gold-Highlights (kein bläuliches, kaltes Neon-Werkstattlicht).", SeelenfunkeRule = "Warmes Streiflicht (ca. 3200K–4000K) nutzen, passend zur Markenfarbe Gold (#C5A059)." },

                // C. Emotionales Storytelling (19–23)
                new() { Number = 19, CategoryKey = "story", CategoryName = "Emotion & Story", Question = "Nach welchem Spannungsbogen sind die besten Story-Videos aufgebaut?", MarketFinding = "4 Akte: 1. Emotionaler Kundenwunsch -> 2. Präzisions-Fertigung -> 3. Magischer Licht-Reveal -> 4. Edles Verpacken.", SeelenfunkeRule = "Genau diese 4-Akte-Formel ist fest in unserer 4-Boxen-Tagesempfehlung oben verankert." },
                new() { Number = 20, CategoryKey = "story", CategoryName = "Emotion & Story", Question = "Welche Kauf-Anlässe sorgen für die höchsten Umsätze?", MarketFinding = "Platz 1: Haustier-Erinnerung | Platz 2: Handschrift Verstorbener/Großeltern | Platz 3: Hochzeit & Jahrestag | Platz 4: Papa/Männer.", SeelenfunkeRule = "Unser 14-Tage-Drehplan deckt exakt diese 4 umsatzstärksten Anlässe im Wechsel ab." },
                new() { Number = 21, CategoryKey = "story", CategoryName = "Emotion & Story", Question = "Wann nutzt man Voiceover und wann reinen Text + Sound?", MarketFinding = "80 % der Top-Videos nutzen nur Text-Hook + ASMR/Musik (schneller gedreht!); 20 % nutzen ruhige Gründer-Stimme.", SeelenfunkeRule = "4x pro Woche schnelle ASMR-/Story-Clips ohne Sprechen, 1x pro Woche persönlicher Gründer-Einblick." },
                new() { Number = 22, CategoryKey = "story", CategoryName = "Emotion & Story", Question = "Wie wird bewiesen, dass jedes Stück echte Einzelanfertigung ist?", MarketFinding = "Kurzes Einblenden des eingesandten Kundenfotos oder der handgeschriebenen Vorlage vor dem Lasern.", SeelenfunkeRule = "Immer 1,5 Sekunden lang das Ausgangsfoto neben dem Rohling zeigen." },
                new() { Number = 23, CategoryKey = "story", CategoryName = "Emotion & Story", Question = "Wie werden Kaufzweifel direkt im Video aufgelöst?", MarketFinding = "Zuschauer sehen live die 3D-Vorschau am Handy ('So sieht es vorher aus') und die kratzfeste Gravur.", SeelenfunkeRule = "Regelmäßig 3 Sekunden lang den Mein-Seelenfunke 3D-Konfigurator auf dem Smartphone ins Bild halten." },

                // D. Algorithmus-Trigger & Kommentare (24–28)
                new() { Number = 24, CategoryKey = "algo", CategoryName = "Algorithmus", Question = "Was bringt Zuschauer dazu, unter dem Video zu kommentieren?", MarketFinding = "Auswahlfragen ('Team Gold-Herz 1 oder Team Kristall 2?') oder hochgradig berührende Geschichten.", SeelenfunkeRule = "Am Ende der Caption immer eine leichte 1-Satz-Frage stellen oder auf Kundenkommentare per Video antworten." },
                new() { Number = 25, CategoryKey = "algo", CategoryName = "Algorithmus", Question = "Warum speichern (Bookmark) so viele Nutzer diese Videos?", MarketFinding = "Weil sie nicht heute Geburtstag/Hochzeitstag haben, sich die Geschenkidee aber für später merken wollen.", SeelenfunkeRule = "Hinweis einbauen: 'Speichere dir dieses Video für das nächste besondere Geschenk 📌'." },
                new() { Number = 26, CategoryKey = "algo", CategoryName = "Algorithmus", Question = "Wie ist die perfekte Caption (Videobeschreibung) aufgebaut?", MarketFinding = "Maximal 3 kurze Zeilen: 1. Emotionaler Kernsatz, 2. Hinweis auf Manufaktur Gifhorn, 3. Klarer Call-to-Action zur Bio.", SeelenfunkeRule = "Für alle 14 Tagesempfehlungen sind die perfekten 3-Zeilen-Captions bereits zum 1-Klick-Kopieren hinterlegt." },
                new() { Number = 27, CategoryKey = "algo", CategoryName = "Algorithmus", Question = "Welche Hashtag-Strategie funktioniert aktuell am besten?", MarketFinding = "Keine 30 Spam-Hashtags, sondern 5 präzise SEO-Hashtags (1x Marke, 2x Produkt, 2x Anlass).", SeelenfunkeRule = "Immer #meinseelenfunke + 4 passende Kauf-Hashtags (z. B. #3dglasgravur #erinnerungsschmuck #geschenkidee)." },
                new() { Number = 28, CategoryKey = "algo", CategoryName = "Algorithmus", Question = "Wie verwandelt man Kommentare ('Was kostet das?') in Verkäufe?", MarketFinding = "Die beste Frage oben anpinnen und mit einem neuen Werkstatt-Video direkt auf den Kommentar antworten.", SeelenfunkeRule = "Top-Kommentar sofort anpinnen + auf den 3D-Konfigurator in der Bio verweisen." },

                // E. Bio, Webseite & Konfigurator-Funnel (29–33)
                new() { Number = 29, CategoryKey = "funnel", CategoryName = "Bio & Shop-Funnel", Question = "Welche 3 Videos müssen oben im Profil angepinnt sein?", MarketFinding = "Pin 1: Emotionalster Bestseller | Pin 2: Gründerin & Manufaktur-Vertrauen | Pin 3: 3D-Konfigurator in 15s erklärt.", SeelenfunkeRule = "Genau diese 3 Pinned-Posts auf @meinseelenfunke fest verankern." },
                new() { Number = 30, CategoryKey = "funnel", CategoryName = "Bio & Shop-Funnel", Question = "Welche Instagram Story-Highlights schaffen sofort Vertrauen?", MarketFinding = "5 aufgeräumte Kreise in Schwarz/Gold: 'Unikate', 'Werkstatt', 'Kundenstimmen', '3D-Vorschau', 'Versand'.", SeelenfunkeRule = "Exakt diese 5 Highlight-Bubbles im Gold-Design (#C5A059) unter der Bio anlegen." },
                new() { Number = 31, CategoryKey = "funnel", CategoryName = "Bio & Shop-Funnel", Question = "Wie viele Klicks liegen zwischen Bio-Link und Foto-Upload?", MarketFinding = "Bei den Marktführern maximal 2 Klicks (Bio-Link -> Direkt auf Produkt-Landingpage mit Upload-Button).", SeelenfunkeRule = "Unsere /linktree und /l/... Seiten führen ohne Zwischenmenüs direkt zum Produkt-Konfigurator." },
                new() { Number = 32, CategoryKey = "funnel", CategoryName = "Bio & Shop-Funnel", Question = "Was muss auf der mobilen Landingpage sofort ohne Scrollen sichtbar sein?", MarketFinding = "Produkt-Loop, Preis, 'Handgefertigt in Deutschland', Lieferzeit (3–5 Werktage) und der goldene Konfigurator-Button.", SeelenfunkeRule = "Kein langer Text über dem Konfigurator – der Upload-/3D-Button steht mobil sofort im Sichtfeld." },
                new() { Number = 33, CategoryKey = "funnel", CategoryName = "Bio & Shop-Funnel", Question = "Wie erhöhen die Top-Accounts ihren durchschnittlichen Warenkorbwert?", MarketFinding = "Durch passende Zusatzoptionen (LED-Leuchtsockel beim Kristall, Geschenkbox oder Partner-Zweitstück).", SeelenfunkeRule = "Im Video immer die edle Westpack-Schachtel und den Leuchteffekt mitzeigen." },

                // F. Paid Ads, Influencer & Google (34–37)
                new() { Number = 34, CategoryKey = "ads", CategoryName = "Ads & Google", Question = "Welche Videos werden als bezahlte TikTok/Instagram Ads geschaltet?", MarketFinding = "Niemals ungetestete Videos! Nur organische Posts, die bereits >35 % 3s-Hold-Rate und viele Kommentare haben.", SeelenfunkeRule = "Erst organisch posten -> nur den besten Post der Woche als Spark Ad / Reels Ad mit 5–10 €/Tag pushen." },
                new() { Number = 35, CategoryKey = "ads", CategoryName = "Ads & Google", Question = "Welcher Call-to-Action-Button funktioniert in Ads am besten?", MarketFinding = "'Jetzt gestalten' / 'Shop Now' mit direkter Weiterleitung auf die exakt zum Video passende Landingpage (/l/...).", SeelenfunkeRule = "Jede Ad führt exakt auf das im Video gezeigte Produkt (z. B. Hunde-Video -> /l/seelen-kristall)." },
                new() { Number = 36, CategoryKey = "ads", CategoryName = "Ads & Google", Question = "Wie arbeiten TikTok/Reels und Google Ads zusammen?", MarketFinding = "TikTok/Reels wecken das Verlangen; 40 % der Käufer googeln danach den Markennamen oder '3D Glasgravur Foto'.", SeelenfunkeRule = "Google Search Kampagne auf Kauf-Keywords + Brand-Kampagne auf 'Mein Seelenfunke' aktiv halten." },
                new() { Number = 37, CategoryKey = "ads", CategoryName = "Ads & Google", Question = "Wie funktioniert erfolgreiches Influencer-Marketing in unserer Nische?", MarketFinding = "Keine aufgesetzten Werbetexte, sondern echte Live-Reaktion beim ersten Auspacken eines Überraschungs-Unikats.", SeelenfunkeRule = "2-Teil-System: Teil 1 Werkstatt-Fertigung bei uns + Teil 2 echtes Unboxing mit Tränen-Garantie beim Influencer." }
            };

            return vault;
        }

        private List<ViralVideoAnalysisDto> BuildTenViralVideosForAccount(string accName, string baseProd, string avgLen, int baseRecipeDay)
        {
            return new List<ViralVideoAnalysisDto>
            {
                new() { Rank = 1, Title = $"Emotionaler Kundenauftrag: Letzte Handschrift / Erinnerungsfoto ({baseProd})", Duration = avgLen, Views = "4.8M", HookFirst3Sec = "Original-Vorlage neben Rohling + weiße Handschuhe", SoundUsed = "3s Laser-Originalton -> Piano", SpecialFeature = "Licht-Reveal im abgedunkelten Raum", SeelenfunkeAdaptation = $"Tag {baseRecipeDay:00} im Drehplan (Vorlage B)", MappedRecipeDay = baseRecipeDay },
                new() { Rank = 2, Title = $"Pure Laser-ASMR Nahaufnahme ohne Musik ({baseProd})", Duration = "13s", Views = "3.4M", HookFirst3Sec = "Laserblitz startet in Frame 1 auf Metall/Glas", SoundUsed = "100 % hochfrequentes Laser-Singen (0 dB)", SpecialFeature = "70 % Makro-Optik + Abpinseln", SeelenfunkeAdaptation = "Tag 04 / Tag 01 (Vorlage A – 14s ASMR)", MappedRecipeDay = 4 },
                new() { Rank = 3, Title = "White-Glove Packaging & Schachtel-Ritual", Duration = "14s", Views = "2.9M", HookFirst3Sec = "Weiße Handschuhe streichen über mattschwarze Box", SoundUsed = "Samt-Rascheln + sattes Schachtel-Schließen", SpecialFeature = "Fokus auf Geschenkfertigkeit", SeelenfunkeAdaptation = "Tag 08 (Westpack-Ritual Vorlage A)", MappedRecipeDay = 8 },
                new() { Rank = 4, Title = "Haustier-Gedenkstück: 'Er war 14 Jahre mein bester Freund'", Duration = "27s", Views = "2.5M", HookFirst3Sec = "Hundefoto am Handy neben dem ungravierten Stück", SoundUsed = "Leises Laser-Summen -> Streicher-Aufbau", SpecialFeature = "Vorher-Foto direkt neben fertigem Unikat", SeelenfunkeAdaptation = "Tag 02 (Seelen Kristall Haustier-Story)", MappedRecipeDay = 2 },
                new() { Rank = 5, Title = "Qualitätskontrolle: 'Warum ich dieses Stück heute neu mache'", Duration = "33s", Views = "2.1M", HookFirst3Sec = "Prüfung mit Lupe & weißen Handschuhen im Streiflicht", SoundUsed = "Ruhige ehrliche Stimme + Werkstatt-Atmo", SpecialFeature = "Beweist kompromisslose Manufaktur-Qualität", SeelenfunkeAdaptation = "Tag 03 (Gründerin Perfektionismus-Check)", MappedRecipeDay = 3 },
                new() { Rank = 6, Title = "Vom 2D-Handyfoto zur 3D-Vorschau in 15 Sekunden", Duration = "18s", Views = "1.8M", HookFirst3Sec = "Finger dreht das 3D-Modell live auf dem Smartphone", SoundUsed = "Moderner Clean-Beat + Laser-Klick", SpecialFeature = "Nimmt jede Kaufunsicherheit vorab weg", SeelenfunkeAdaptation = "Tag 07 (3D-Konfigurator Live-Beweis)", MappedRecipeDay = 7 },
                new() { Rank = 7, Title = "Hochzeits- & Jahrestags-Unikat ('Besser als Geld im Umschlag')", Duration = "25s", Views = "1.6M", HookFirst3Sec = "Text-Hook über funkelndem Kristall/Gold-Herz", SoundUsed = "2s Laser-Start -> Romantisches Piano", SpecialFeature = "Funkelnde Kanten im warmen Goldlicht", SeelenfunkeAdaptation = "Tag 13 (Hochzeits-Edition Vorlage B)", MappedRecipeDay = 13 },
                new() { Rank = 8, Title = "Bestseller-Duell: 'Team 1 (Gold) oder Team 2 (Kristall)?'", Duration = "17s", Views = "1.4M", HookFirst3Sec = "Beide fertigen Stücke liegen im Streiflicht nebeneinander", SoundUsed = "Edler Lounge-Track + 2 Whoosh-Sounds", SpecialFeature = "Provoziert hunderte Antworten in den Kommentaren", SeelenfunkeAdaptation = "Tag 12 (Kommentar-Trigger Vorlage A)", MappedRecipeDay = 12 },
                new() { Rank = 9, Title = "Wie kommt die Gravur mitten ins Glas ohne Kratzer außen?", Duration = "15s", Views = "1.2M", HookFirst3Sec = "Violetter UV-Laserpunkt schwebt mitten im Glasblock", SoundUsed = "Feines UV-Laser-Knistern + Reveal-Akkord", SpecialFeature = "Technik-Faszination sorgt für hohe Speicher-Rate", SeelenfunkeAdaptation = "Tag 10 (UV-Laser Magie erklärt)", MappedRecipeDay = 10 },
                new() { Rank = 10, Title = "Sonntagsschicht in der Manufaktur: Eure Pakete werden gepackt", Duration = "29s", Views = "980K", HookFirst3Sec = "Anziehen der weißen Handschuhe an der Werkbank", SoundUsed = "Werkstatt-Sounds + warme Hintergrundmusik", SpecialFeature = "Baut persönliche Bindung zur Marke auf", SeelenfunkeAdaptation = "Tag 14 (Sonntag in der Manufaktur Gifhorn)", MappedRecipeDay = 14 }
            };
        }
    }
}

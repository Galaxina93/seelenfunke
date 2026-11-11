using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Windows.Media.Imaging;

namespace FunkenwerkOS
{
    public sealed class DesktopGridPos
    {
        public int Col { get; set; }
        public int Row { get; set; }
    }

    public sealed class OsSettings
    {
        public bool DarkMode { get; set; } = true;
        public string WallpaperMode { get; set; } = "AtelierPoster"; // AtelierVideo | AtelierPoster | ObsidianKosmos
        public bool AmbientSparksEnabled { get; set; } = true;
        public string ExplorerViewMode { get; set; } = "Details"; // Details | Icons
        public string ExplorerSortBy { get; set; } = "Name"; // Name | Created | Modified | Type | Size
        public bool ExplorerSortAsc { get; set; } = true;
        public string ApiEnvironment { get; set; } = "live";
        public bool OrderChimeEnabled { get; set; } = false;
        public string SavedEmail { get; set; } = "kontakt@mein-seelenfunke.de";
        public string? EncryptedTokenLocal { get; set; }
        public string? EncryptedTokenStage { get; set; }
        public string? EncryptedTokenLive { get; set; }
        public List<int> KnownOpenOrderIds { get; set; } = new();
        public Dictionary<string, DesktopGridPos> DesktopPositions { get; set; } = new(StringComparer.OrdinalIgnoreCase);
        public Dictionary<string, string> CustomDisplayNames { get; set; } = new(StringComparer.OrdinalIgnoreCase);
        public List<string> HiddenDesktopShortcutIds { get; set; } = new();
        public List<string> VisibleDesktopShortcutIds { get; set; } = new();
        public List<string> PinnedTaskbarAppIds { get; set; } = new()
        {
            "sys:gewerbe",
            "sys:leitstand",
            "app:browser",
            "sys:explorer",
            "sys:calculator",
            "sys:snip",
            "sys:notes",
            "app:pdf",
            "app:player"
        };
        public string TaskbarPosition { get; set; } = "Bottom"; // Bottom | Top | Left | Right
        public string TaskbarAlignment { get; set; } = "Left"; // Left | Center | Right
        public bool TaskbarTransparentMode { get; set; } = false;
        public Dictionary<string, string> DefaultPrograms { get; set; } = new(StringComparer.OrdinalIgnoreCase)
        {
            ["web"] = "browser",
            ["pdf"] = "pdf",
            ["audio"] = "player",
            ["video"] = "player",
            ["image"] = "internal_image",
            ["kdbx"] = "tresor",
            ["text"] = "internal_editor"
        };
        public Dictionary<string, bool> FirewallExtraApps { get; set; } = new(StringComparer.OrdinalIgnoreCase)
        {
            ["kdrive"] = true,
            ["discord"] = false,
            ["lol"] = false,
            ["dev"] = false
        };
        public bool AutoRedirectStandardDownloadsToFunnel { get; set; } = true;
        public bool ZeroGarbageGuardEnabled { get; set; } = true;
        public List<OsStorageBindingDto> StorageBindings { get; set; } = new();
    }

    public sealed class OsStorageBindingDto
    {
        public string Id { get; set; } = "";
        public string Name { get; set; } = "";
        public string ProviderKind { get; set; } = "kDrive"; // kDrive | NAS | WebDAV | Custom
        public string LocalOrUncPath { get; set; } = "";
        public bool AutoIngestToFunnel { get; set; } = true;
        public bool MirrorMeineDatenStructure { get; set; } = true;

        [System.Text.Json.Serialization.JsonIgnore]
        public bool IsOnline => !string.IsNullOrWhiteSpace(LocalOrUncPath) && Directory.Exists(LocalOrUncPath);

        [System.Text.Json.Serialization.JsonIgnore]
        public string StatusBadge => IsOnline ? "Verbunden" : "Offline";

        [System.Text.Json.Serialization.JsonIgnore]
        public string StatusHex => IsOnline ? "#10B981" : "#6B7280";

        [System.Text.Json.Serialization.JsonIgnore]
        public string IconGlyph => ProviderKind.Equals("kDrive", StringComparison.OrdinalIgnoreCase) ? "☁" : "⇄";

        [System.Text.Json.Serialization.JsonIgnore]
        public string SidebarLabel => $"{IconGlyph}   {Name}";

        [System.Text.Json.Serialization.JsonIgnore]
        public string ModeSummary =>
            $"{(AutoIngestToFunnel ? "Trichter-Eingang: AN" : "Trichter-Eingang: AUS")} · {(MirrorMeineDatenStructure ? "8-Säulen-Sync: AN" : "8-Säulen-Sync: AUS")}";
    }

    public enum DesktopEntryKind
    {
        CollectionApp,
        SystemTool,
        UserFolder,
        UserFile,
        RecycleBin
    }

    public sealed class DesktopItemModel
    {
        public string Id { get; set; } = "";
        public string Title { get; set; } = "";
        public DesktopEntryKind Kind { get; set; }
        public string AppOrToolKey { get; set; } = "";
        public string? FullFilePath { get; set; }
        public BitmapSource? IconImage { get; set; }
        public int Col { get; set; }
        public int Row { get; set; }
        public bool IsDeletable => Kind is DesktopEntryKind.UserFolder or DesktopEntryKind.UserFile;
        public bool IsShortcutRemovable => Kind is DesktopEntryKind.CollectionApp or DesktopEntryKind.SystemTool or DesktopEntryKind.RecycleBin;
        public bool IsRenamable => true;
        public bool IsSearchHighlighted { get; set; }
        public string BadgeText { get; set; } = "";
    }

    public sealed class OsOrderItemDto
    {
        public int Id { get; set; }
        public string OrderNumber { get; set; } = "";
        public string CustomerName { get; set; } = "";
        public string StatusLabel { get; set; } = "";
        public string StatusBadgeHex { get; set; } = "#C5A059";
        public int ItemCount { get; set; }
        public string ItemCountLabel => $"{ItemCount} Pos.";
        public string TotalFormatted { get; set; } = "0,00 €";
        public bool IsExpress { get; set; }
    }

    public sealed class OsPriorityOrderDto
    {
        public string OrderNumber { get; set; } = "Keine offenen Aufträge";
        public string CustomerName { get; set; } = "";
        public string TotalFormatted { get; set; } = "";
        public int ItemCount { get; set; }
        public bool IsExpress { get; set; }
        public string TipSummary { get; set; } = "";
        public bool HasPriorityOrder { get; set; }
    }

    public sealed class OsTaskItemDto
    {
        public int Id { get; set; }
        public string Title { get; set; } = "";
        public string PriorityBadge { get; set; } = "";
        public string PriorityColorHex { get; set; } = "#9CA3AF";
        public bool IsCompleted { get; set; }
    }

    public sealed class OsDashboardSnapshot
    {
        public bool IsConnected { get; set; }
        public string StatusSummary { get; set; } = "Nicht verbunden";
        public long LatencyMs { get; set; }
        public int OpenOrdersCount { get; set; }
        public int ExpressOrdersCount { get; set; }
        public string ShopRevenueFormatted { get; set; } = "0,00 €";
        public string AvailableLiquidityFormatted { get; set; } = "0,00 €";
        public string MonthLabel { get; set; } = "";
        public int OpenTasksCount { get; set; }
        public OsPriorityOrderDto PriorityOrder { get; set; } = new();
        public List<OsOrderItemDto> ActiveOrders { get; set; } = new();
        public List<OsTaskItemDto> OpenTasks { get; set; } = new();
    }

    public sealed class OsCollectionAppDto
    {
        public string Key { get; set; } = "";
        public string Title { get; set; } = "";
        public string ShortLabel { get; set; } = "";
        public string SubFolder { get; set; } = "";
        public string ExeName { get; set; } = "";
        public string IconFileName { get; set; } = "";
        public string ProcessName { get; set; } = "";
        public string ExePath { get; set; } = "";
        public bool IsInstalled { get; set; }
        public bool IsRunning { get; set; }
        public BitmapSource? IconImage { get; set; }
    }

    public sealed class OsOpenWindowDto
    {
        public IntPtr Hwnd { get; set; }
        public string ShortcutId { get; set; } = "";
        public string InternalWindowKey { get; set; } = "";
        public string AppKey { get; set; } = "";
        public int ProcessId { get; set; }
        public string AppLabel { get; set; } = "";
        public string WindowTitle { get; set; } = "";
        public BitmapSource? IconImage { get; set; }
        public bool IsPinned { get; set; }
        public bool IsRunning { get; set; }
        public bool IsActive { get; set; }
        public System.Windows.Visibility RunningIndicatorVisibility => IsRunning ? System.Windows.Visibility.Visible : System.Windows.Visibility.Collapsed;
        public System.Windows.Visibility LabelVisibility { get; set; } = System.Windows.Visibility.Visible;
        public string PillLabel => !string.IsNullOrWhiteSpace(AppLabel) ? AppLabel : WindowTitle;
    }

    public sealed class MeineDatenIndexRecord
    {
        public string FileName { get; set; } = "";
        public string PillarFolder { get; set; } = "";
        public string SubFolder { get; set; } = "";
        public string TargetFullPath { get; set; } = "";
        public string[] Tags { get; set; } = Array.Empty<string>();
        public string Sha256 { get; set; } = "";
        public DateTime SortedAt { get; set; } = DateTime.Now;
    }

    public sealed class OsLanDeviceDto
    {
        public string Name { get; set; } = "";
        public string IpAddress { get; set; } = "";
        public int ProbePort { get; set; }
        public bool IsOnline { get; set; }
        public long LatencyMs { get; set; }
        public string DotHex => IsOnline ? "#10B981" : "#6B7280";
        public string StatusLabel => IsOnline ? $"{LatencyMs} ms" : "Offline";
    }

    public sealed class OsDriveSidebarDto
    {
        public string RootPath { get; set; } = "";
        public string Label { get; set; } = "";
        public string FreeSpaceBadge { get; set; } = "";
    }

    public sealed class OsBreadcrumbSegment
    {
        public string Label { get; set; } = "";
        public string FullPath { get; set; } = "";
    }

    public sealed class OsFileEntryDto : System.ComponentModel.INotifyPropertyChanged
    {
        private BitmapSource? _iconImage;
        private bool _isSelected;
        private bool _isCut;

        public event System.ComponentModel.PropertyChangedEventHandler? PropertyChanged;

        public string Name { get; set; } = "";
        public string FullPath { get; set; } = "";
        public bool IsDirectory { get; set; }
        public string TypeBadge { get; set; } = "";
        public long RawBytes { get; set; }
        public string SizeFormatted { get; set; } = "";
        public DateTime CreatedAt { get; set; }
        public DateTime ModifiedAt { get; set; }
        public string CreatedFormatted { get; set; } = "";
        public string ModifiedFormatted { get; set; } = "";
        public string TileSubtitle => IsDirectory ? CreatedFormatted : $"{CreatedFormatted} · {SizeFormatted}";

        public BitmapSource? IconImage
        {
            get => _iconImage;
            set
            {
                if (!ReferenceEquals(_iconImage, value))
                {
                    _iconImage = value;
                    PropertyChanged?.Invoke(this, new System.ComponentModel.PropertyChangedEventArgs(nameof(IconImage)));
                }
            }
        }

        public bool IsSelected
        {
            get => _isSelected;
            set
            {
                if (_isSelected != value)
                {
                    _isSelected = value;
                    PropertyChanged?.Invoke(this, new System.ComponentModel.PropertyChangedEventArgs(nameof(IsSelected)));
                }
            }
        }

        public bool IsCut
        {
            get => _isCut;
            set
            {
                if (_isCut != value)
                {
                    _isCut = value;
                    PropertyChanged?.Invoke(this, new System.ComponentModel.PropertyChangedEventArgs(nameof(IsCut)));
                }
            }
        }
    }

    public sealed class WifiNetworkDto
    {
        public string Ssid { get; set; } = "";
        public int SignalPercent { get; set; }
        public string Security { get; set; } = "";
        public bool IsConnected { get; set; }
        public bool HasSavedProfile { get; set; }
        public bool IsOpen => Security.Contains("Offen", StringComparison.OrdinalIgnoreCase) || Security.Contains("Open", StringComparison.OrdinalIgnoreCase);
        public string SignalLabel => $"{SignalPercent}%";
        public string StatusBadge => IsConnected ? "✓ Verbunden" : (HasSavedProfile ? "Gespeichert" : Security);
        public string StatusColorHex => IsConnected ? "#34D399" : "#C5A059";
    }

    public sealed class BluetoothDeviceDto
    {
        public string Name { get; set; } = "";
        public string InstanceId { get; set; } = "";
        public ulong BluetoothAddress { get; set; }
        public bool IsConnected { get; set; }
        public bool IsPaired { get; set; } = true;
        public bool IsTeufel => Name.Contains("Teufel", StringComparison.OrdinalIgnoreCase);
        public string StatusBadge => IsConnected ? "✓ Verbunden" : (IsPaired ? "Gekoppelt" : "Koppeln");
        public string StatusColorHex => IsConnected ? "#34D399" : (IsPaired ? "#C5A059" : "#9CA3AF");
    }

    public sealed class AudioOutputDeviceDto
    {
        public string DeviceId { get; set; } = "";
        public string Name { get; set; } = "";
        public bool IsDefault { get; set; }
        public bool IsTeufel => Name.Contains("Teufel", StringComparison.OrdinalIgnoreCase);
        public string ActiveMark => IsDefault ? "✓" : "";
    }

    public sealed class FortressAuditReport
    {
        public bool IsCustomShellActive { get; set; }
        public bool IsFortressFirewallActive { get; set; }
        public bool IsTelemetryLocked { get; set; }
        public int RemainingBloatwareCount { get; set; }
        public bool IsWallPowerPlanActive { get; set; }
        public bool HasWindowsKeyBackup { get; set; }
        public string MaskedWindowsKey { get; set; } = "Kein Backup";
        public double FreeRamGb { get; set; }
        public double TotalRamGb { get; set; }
        public int CpuLoadPercent { get; set; }
        public int PassedChecksCount =>
            (IsCustomShellActive ? 1 : 0) +
            (IsFortressFirewallActive ? 1 : 0) +
            (IsTelemetryLocked ? 1 : 0) +
            (RemainingBloatwareCount == 0 ? 1 : 0) +
            (IsWallPowerPlanActive ? 1 : 0) +
            (HasWindowsKeyBackup ? 1 : 0);
    }

    public static class OsVaultAndStorage
    {
        private static readonly byte[] FunkenwerkMainEntropy = Encoding.UTF8.GetBytes("Funkenwerk_Seelenfunke_HardwareVault_v1");
        private static readonly byte[] FunkenwerkMainAad = Encoding.UTF8.GetBytes("Funkenwerk-Vault-v2-AEAD-AES256GCM");

        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        private struct DATA_BLOB
        {
            public int cbData;
            public IntPtr pbData;
        }

        [DllImport("crypt32.dll", SetLastError = true, CharSet = CharSet.Auto)]
        private static extern bool CryptProtectData(
            ref DATA_BLOB pDataIn,
            string? szDataDescr,
            ref DATA_BLOB pOptionalEntropy,
            IntPtr pvReserved,
            IntPtr pPromptStruct,
            int dwFlags,
            out DATA_BLOB pDataOut);

        [DllImport("crypt32.dll", SetLastError = true, CharSet = CharSet.Auto)]
        private static extern bool CryptUnprotectData(
            ref DATA_BLOB pCipherText,
            IntPtr ppszDescription,
            ref DATA_BLOB pEntropy,
            IntPtr pReserved,
            IntPtr pPrompt,
            int dwFlags,
            out DATA_BLOB pPlainText);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern IntPtr LocalFree(IntPtr hMem);

        public static string DataDir
        {
            get
            {
                string dir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkOS");
                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                    LockDownDirectoryAcl(dir);
                }
                return dir;
            }
        }

        public static string OsDesktopDir
        {
            get
            {
                string dir = Path.Combine(DataDir, "Desktop");
                if (!Directory.Exists(dir))
                    Directory.CreateDirectory(dir);
                return dir;
            }
        }

        public static string OsRecycleBinDir
        {
            get
            {
                string dir = Path.Combine(DataDir, "RecycleBin");
                if (!Directory.Exists(dir))
                    Directory.CreateDirectory(dir);
                return dir;
            }
        }

        public static string MeineDatenRootDir
        {
            get
            {
                string dir = Path.Combine(DataDir, "Meine Daten");
                if (!Directory.Exists(dir))
                    EnsureMeineDatenStructure(dir);
                return dir;
            }
        }

        public static string EingangTrichterDir => Path.Combine(MeineDatenRootDir, "00_Eingang_Trichter");

        public static string MeineDatenIndexFilePath => Path.Combine(MeineDatenRootDir, "meine_daten_index.json");

        public static readonly (string Pillar, string[] SubFolders)[] MeineDatenPillars =
        {
            ("00_Eingang_Trichter", Array.Empty<string>()),
            ("01_Mein-Seelenfunke", new[] { "Eingangsrechnungen_Belege", "Vertraege_Versicherungen", "Steuern_Finanzamt_Gewerbe", "Bank_FYRST", "Laser_Manufaktur" }),
            ("02_Finanzen_Steuern_Banken", new[] { "Banken", "Darlehen", "Schufa", "Steuern_Elster", "Rechnungen" }),
            ("03_Haus_Immobilie", new[] { "Bauakten_Grundriss", "Kaufvertrag_Notar", "Kredit_Lastenzuschuss", "Photovoltaik_Waermepumpe", "Gas_Wasser_Strom", "Gebaeudeversicherung", "Vermietung" }),
            ("04_Versicherungen_Vertraege_Mobilitaet", new[] { "Versicherungen_Privat", "Handy_Internet", "Rundfunk", "Mobilitaet_Roller_Kfz" }),
            ("05_Familie_Ausweise_Vorsorge", new[] { "Ausweise_Fuehrerschein", "Urkunden", "Antraege_Elterngeld_Kindergeld", "Erbe_Vorsorgevollmacht", "Familie" }),
            ("06_Gesundheit", new[] { "Arztbriefe_Befunde", "Krankmeldungen", "Transition", "Krankenkasse" }),
            ("07_Behoerden_Recht_Berufshistorie", new[] { "Gifhorn_Behoerden", "Polizei_Recht", "Arbeitgeber_Historie", "Agentur_fuer_Arbeit" }),
            ("08_Tresor_Backups", new[] { "Keepass_Backups", "Nummern_Daten" })
        };

        public static void EnsureMeineDatenStructure(string? customRoot = null)
        {
            try
            {
                string root = customRoot ?? Path.Combine(DataDir, "Meine Daten");
                Directory.CreateDirectory(root);
                foreach (var (pillar, subs) in MeineDatenPillars)
                {
                    string pDir = Path.Combine(root, pillar);
                    Directory.CreateDirectory(pDir);
                    foreach (string sub in subs)
                        Directory.CreateDirectory(Path.Combine(pDir, sub));
                }
            }
            catch { }
        }

        public static (string Pillar, string SubFolder, string[] Tags) ClassifyFileIntoMeineDatenPillar(string filePath)
        {
            string name = Path.GetFileName(filePath);
            string ext = Path.GetExtension(filePath).ToLowerInvariant();
            string sample = name.ToLowerInvariant();

            try
            {
                if (File.Exists(filePath) && ext is ".txt" or ".csv" or ".json" or ".xml" or ".md" or ".html" && new FileInfo(filePath).Length <= 256 * 1024)
                {
                    sample += " " + File.ReadAllText(filePath).ToLowerInvariant();
                }
            }
            catch { }

            if (ext is ".kdbx" or ".keyx" || HasAny(sample, "keepass", "passwort", "tresor", "backup_key", "recovery"))
                return ("08_Tresor_Backups", "Keepass_Backups", new[] { "Tresor", "Sicherheit" });

            if (HasAny(sample, "seelenfunke", "fyrst", "xtool", "glücksfieber", "gluecksfieber", "westpack", "printequipment", "karton", "gewerbe", "manufaktur", "shopify", "ustva"))
            {
                if (HasAny(sample, "fyrst", "kontoauszug")) return ("01_Mein-Seelenfunke", "Bank_FYRST", new[] { "Seelenfunke", "FYRST" });
                if (HasAny(sample, "xtool", "laser", "gravur", "if2", "ra3")) return ("01_Mein-Seelenfunke", "Laser_Manufaktur", new[] { "Seelenfunke", "Laser" });
                if (HasAny(sample, "finanzamt", "gewerbeanmeldung", "steuer", "elster")) return ("01_Mein-Seelenfunke", "Steuern_Finanzamt_Gewerbe", new[] { "Seelenfunke", "Steuern" });
                if (HasAny(sample, "vertrag", "versicherung", "hispanic", "police")) return ("01_Mein-Seelenfunke", "Vertraege_Versicherungen", new[] { "Seelenfunke", "Vertrag" });
                return ("01_Mein-Seelenfunke", "Eingangsrechnungen_Belege", new[] { "Seelenfunke", "Beleg" });
            }

            if (HasAny(sample, "haus", "immobilie", "bauakte", "grundriss", "notar", "grundbuch", "photovoltaik", "waermepumpe", "wärmepumpe", "schornsteinfeger", "strom", "gas", "wasser", "lsw", "stadtwerke", "wohngebäude", "mieter", "mietvertrag", "lastenzuschuss"))
            {
                if (HasAny(sample, "bauakte", "grundriss", "statik", "baubeschreibung")) return ("03_Haus_Immobilie", "Bauakten_Grundriss", new[] { "Haus", "Bauakte" });
                if (HasAny(sample, "notar", "kaufvertrag", "grundbuch")) return ("03_Haus_Immobilie", "Kaufvertrag_Notar", new[] { "Haus", "Notar" });
                if (HasAny(sample, "photovoltaik", "solar", "waermepumpe", "wärmepumpe", "heizung")) return ("03_Haus_Immobilie", "Photovoltaik_Waermepumpe", new[] { "Haus", "Energie" });
                if (HasAny(sample, "strom", "gas", "wasser", "lsw", "zähler", "zaehler")) return ("03_Haus_Immobilie", "Gas_Wasser_Strom", new[] { "Haus", "Versorgung" });
                if (HasAny(sample, "miete", "mieter", "nebenkosten", "kaution")) return ("03_Haus_Immobilie", "Vermietung", new[] { "Haus", "Vermietung" });
                if (HasAny(sample, "gebäudeversicherung", "gebaeudeversicherung", "elementar")) return ("03_Haus_Immobilie", "Gebaeudeversicherung", new[] { "Haus", "Versicherung" });
                return ("03_Haus_Immobilie", "Kredit_Lastenzuschuss", new[] { "Haus", "Finanzierung" });
            }

            if (HasAny(sample, "arzt", "befund", "krankenhaus", "klinik", "krankmeldung", "au-", "rezept", "transition", "hormon", "krankenkasse", "aok", "tk ", "barmer", "dak", "blutbild", "labor"))
            {
                if (HasAny(sample, "krankmeldung", "arbeitsunfähig", "au_")) return ("06_Gesundheit", "Krankmeldungen", new[] { "Gesundheit", "AU" });
                if (HasAny(sample, "transition", "endokrin", "gutachten", "indikation")) return ("06_Gesundheit", "Transition", new[] { "Gesundheit", "Transition" });
                if (HasAny(sample, "krankenkasse", "aok", "barmer", "dak", "techniker")) return ("06_Gesundheit", "Krankenkasse", new[] { "Gesundheit", "Krankenkasse" });
                return ("06_Gesundheit", "Arztbriefe_Befunde", new[] { "Gesundheit", "Befund" });
            }

            if (HasAny(sample, "ausweis", "reisepass", "führerschein", "fuehrerschein", "geburtsurkunde", "urkunde", "elterngeld", "kindergeld", "testament", "vorsorgevollmacht", "patientenverfügung", "erbe", "abstammung"))
            {
                if (HasAny(sample, "ausweis", "reisepass", "führerschein", "fuehrerschein")) return ("05_Familie_Ausweise_Vorsorge", "Ausweise_Fuehrerschein", new[] { "Familie", "Ausweis" });
                if (HasAny(sample, "urkunde", "geburtsurkunde", "standesamt")) return ("05_Familie_Ausweise_Vorsorge", "Urkunden", new[] { "Familie", "Urkunde" });
                if (HasAny(sample, "elterngeld", "kindergeld", "familienkasse")) return ("05_Familie_Ausweise_Vorsorge", "Antraege_Elterngeld_Kindergeld", new[] { "Familie", "Antrag" });
                if (HasAny(sample, "vollmacht", "erbe", "testament", "patientenverfügung")) return ("05_Familie_Ausweise_Vorsorge", "Erbe_Vorsorgevollmacht", new[] { "Familie", "Vorsorge" });
                return ("05_Familie_Ausweise_Vorsorge", "Familie", new[] { "Familie" });
            }

            if (HasAny(sample, "versicherung", "haftpflicht", "rechtsschutz", "hausrat", "allianz", "huk", "debeka", "vodafone", "telekom", "o2", "1und1", "rundfunk", "gez", "beitragsservice", "roller", "kfz", "kennzeichen"))
            {
                if (HasAny(sample, "vodafone", "telekom", "o2", "dsl", "mobilfunk", "handy", "glasfaser")) return ("04_Versicherungen_Vertraege_Mobilitaet", "Handy_Internet", new[] { "Vertrag", "Internet" });
                if (HasAny(sample, "rundfunk", "gez", "beitragsservice")) return ("04_Versicherungen_Vertraege_Mobilitaet", "Rundfunk", new[] { "Vertrag", "Rundfunk" });
                if (HasAny(sample, "roller", "kfz", "auto", "kennzeichen", "tüv", "tuev")) return ("04_Versicherungen_Vertraege_Mobilitaet", "Mobilitaet_Roller_Kfz", new[] { "Mobilität" });
                return ("04_Versicherungen_Vertraege_Mobilitaet", "Versicherungen_Privat", new[] { "Versicherung" });
            }

            if (HasAny(sample, "polizei", "gericht", "anwalt", "bußgeld", "bussgeld", "stadt gifhorn", "landkreis gifhorn", "arbeitsvertrag", "lohnabrechnung", "zeugnis", "arbeitsagentur", "jobcenter", "rentenversicherung"))
            {
                if (HasAny(sample, "polizei", "anwalt", "gericht", "recht", "aktenzeichen")) return ("07_Behoerden_Recht_Berufshistorie", "Polizei_Recht", new[] { "Recht" });
                if (HasAny(sample, "arbeitsvertrag", "lohn", "gehalt", "zeugnis", "arbeitgeber")) return ("07_Behoerden_Recht_Berufshistorie", "Arbeitgeber_Historie", new[] { "Beruf" });
                if (HasAny(sample, "arbeitsagentur", "agentur für arbeit", "alg")) return ("07_Behoerden_Recht_Berufshistorie", "Agentur_fuer_Arbeit", new[] { "Behörde" });
                return ("07_Behoerden_Recht_Berufshistorie", "Gifhorn_Behoerden", new[] { "Behörde", "Gifhorn" });
            }

            if (HasAny(sample, "bank", "sparkasse", "ing ", "dkb", "comdirect", "darlehen", "kredit", "schufa", "steuerbescheid", "elster", "finanzamt", "rechnung", "quittung", "mahnung", "iban"))
            {
                if (HasAny(sample, "schufa")) return ("02_Finanzen_Steuern_Banken", "Schufa", new[] { "Finanzen", "Schufa" });
                if (HasAny(sample, "steuer", "elster", "finanzamt")) return ("02_Finanzen_Steuern_Banken", "Steuern_Elster", new[] { "Finanzen", "Steuern" });
                if (HasAny(sample, "darlehen", "kredit", "tilgung")) return ("02_Finanzen_Steuern_Banken", "Darlehen", new[] { "Finanzen", "Darlehen" });
                if (HasAny(sample, "bank", "sparkasse", "kontoauszug", "iban")) return ("02_Finanzen_Steuern_Banken", "Banken", new[] { "Finanzen", "Bank" });
                return ("02_Finanzen_Steuern_Banken", "Rechnungen", new[] { "Finanzen", "Rechnung" });
            }

            return ("02_Finanzen_Steuern_Banken", "Rechnungen", new[] { "Dokument", "Allgemein" });
        }

        private static bool HasAny(string haystack, params string[] needles)
        {
            foreach (string n in needles)
            {
                if (haystack.Contains(n, StringComparison.OrdinalIgnoreCase))
                    return true;
            }
            return false;
        }

        private static readonly HashSet<string> GarbageSystemFileNames = new(StringComparer.OrdinalIgnoreCase)
        {
            "Thumbs.db",
            "ehthumbs.db",
            "desktop.ini",
            ".DS_Store"
        };

        private static readonly string[] IncompleteDownloadExtensions =
        {
            ".crdownload",
            ".part",
            ".tmp",
            ".opdownload",
            ".download"
        };

        public static bool IsGarbageOrTempFile(string filePath)
        {
            string name = Path.GetFileName(filePath);
            if (string.IsNullOrWhiteSpace(name)) return true;
            if (GarbageSystemFileNames.Contains(name)) return true;
            if (name.StartsWith("._", StringComparison.Ordinal) || name.StartsWith("~$", StringComparison.Ordinal))
                return true;
            string ext = Path.GetExtension(name);
            if (IncompleteDownloadExtensions.Any(e => string.Equals(e, ext, StringComparison.OrdinalIgnoreCase)))
                return true;
            return false;
        }

        public static int PurgeZeroGarbageFiles(string? targetRoot = null)
        {
            int removed = 0;
            try
            {
                string root = targetRoot ?? MeineDatenRootDir;
                if (Directory.Exists(root))
                {
                    foreach (string file in Directory.EnumerateFiles(root, "*", SearchOption.AllDirectories))
                    {
                        try
                        {
                            string name = Path.GetFileName(file);
                            string ext = Path.GetExtension(name);
                            if (GarbageSystemFileNames.Contains(name) || name.StartsWith("._", StringComparison.Ordinal))
                            {
                                File.Delete(file);
                                removed++;
                            }
                            else if ((ext.Equals(".tmp", StringComparison.OrdinalIgnoreCase) ||
                                      ext.Equals(".crdownload", StringComparison.OrdinalIgnoreCase) ||
                                      ext.Equals(".part", StringComparison.OrdinalIgnoreCase) ||
                                      name.StartsWith("~$", StringComparison.Ordinal)) &&
                                     !name.Equals("meine_daten_index.json.tmp", StringComparison.OrdinalIgnoreCase))
                            {
                                var fi = new FileInfo(file);
                                if ((DateTime.UtcNow - fi.LastWriteTimeUtc).TotalHours >= 2.0)
                                {
                                    File.Delete(file);
                                    removed++;
                                }
                            }
                        }
                        catch { }
                    }
                }

                // Also clean Desktop garbage & temp Sticky-Note drag files
                if (targetRoot == null && Directory.Exists(OsDesktopDir))
                {
                    foreach (string f in Directory.EnumerateFiles(OsDesktopDir))
                    {
                        try
                        {
                            string n = Path.GetFileName(f);
                            if (GarbageSystemFileNames.Contains(n) || n.StartsWith("._", StringComparison.Ordinal))
                            {
                                File.Delete(f);
                                removed++;
                            }
                        }
                        catch { }
                    }

                    string tempNotesDir = Path.Combine(Path.GetTempPath(), "FunkenwerkOS_Notes");
                    if (Directory.Exists(tempNotesDir))
                    {
                        foreach (string tf in Directory.EnumerateFiles(tempNotesDir))
                        {
                            try
                            {
                                if ((DateTime.UtcNow - File.GetLastWriteTimeUtc(tf)).TotalMinutes >= 15)
                                {
                                    File.Delete(tf);
                                    removed++;
                                }
                            }
                            catch { }
                        }
                    }
                }
            }
            catch { }
            return removed;
        }

        public static int EnforceStrictTrichterStructureOnMeineDaten()
        {
            EnsureMeineDatenStructure();
            int funneled = 0;
            try
            {
                var canonicalPillars = MeineDatenPillars.Select(p => p.Pillar).ToHashSet(StringComparer.OrdinalIgnoreCase);
                string inbox = EingangTrichterDir;
                Directory.CreateDirectory(inbox);

                // 1. Any loose file placed directly in MeineDatenRootDir (except meine_daten_index.json) -> move to 00_Eingang_Trichter
                foreach (string looseFile in Directory.GetFiles(MeineDatenRootDir))
                {
                    try
                    {
                        string fn = Path.GetFileName(looseFile);
                        if (fn.Equals("meine_daten_index.json", StringComparison.OrdinalIgnoreCase) ||
                            fn.Equals("meine_daten_index.json.tmp", StringComparison.OrdinalIgnoreCase))
                            continue;

                        if (GarbageSystemFileNames.Contains(fn))
                        {
                            File.Delete(looseFile);
                            continue;
                        }

                        string dest = Path.Combine(inbox, fn);
                        if (File.Exists(dest))
                            dest = Path.Combine(inbox, $"{Path.GetFileNameWithoutExtension(fn)}_{DateTime.Now:HHmmss}{Path.GetExtension(fn)}");
                        File.Move(looseFile, dest);
                        funneled++;
                    }
                    catch { }
                }

                // 2. Any unauthorized/standard folder created at root of MeineDatenRootDir (e.g. Downloads, Dokumente, Bilder, Neuer Ordner)
                //    -> funnel all its files into 00_Eingang_Trichter and remove the unauthorized folder!
                foreach (string subDir in Directory.GetDirectories(MeineDatenRootDir))
                {
                    try
                    {
                        string dirName = Path.GetFileName(subDir);
                        if (canonicalPillars.Contains(dirName)) continue;

                        foreach (string nestedFile in Directory.EnumerateFiles(subDir, "*", SearchOption.AllDirectories))
                        {
                            try
                            {
                                string fn = Path.GetFileName(nestedFile);
                                if (GarbageSystemFileNames.Contains(fn))
                                {
                                    File.Delete(nestedFile);
                                    continue;
                                }
                                string dest = Path.Combine(inbox, fn);
                                if (File.Exists(dest))
                                    dest = Path.Combine(inbox, $"{Path.GetFileNameWithoutExtension(fn)}_{DateTime.Now:HHmmss}{Path.GetExtension(fn)}");
                                File.Move(nestedFile, dest);
                                funneled++;
                            }
                            catch { }
                        }

                        Directory.Delete(subDir, recursive: true);
                    }
                    catch { }
                }
            }
            catch { }
            return funneled;
        }

        public static void DiscoverAndEnsureStorageBindings(OsSettings settings)
        {
            settings.StorageBindings ??= new List<OsStorageBindingDto>();
            string userProfile = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
            string kDriveDefault = Path.Combine(userProfile, "kDrive");

            var existingKDrive = settings.StorageBindings.FirstOrDefault(b =>
                b.Id.Equals("kdrive", StringComparison.OrdinalIgnoreCase) ||
                b.ProviderKind.Equals("kDrive", StringComparison.OrdinalIgnoreCase));

            if (existingKDrive == null)
            {
                settings.StorageBindings.Insert(0, new OsStorageBindingDto
                {
                    Id = "kdrive",
                    Name = "kDrive (Infomaniak)",
                    ProviderKind = "kDrive",
                    LocalOrUncPath = kDriveDefault,
                    AutoIngestToFunnel = true,
                    MirrorMeineDatenStructure = true
                });
            }
            else if (string.IsNullOrWhiteSpace(existingKDrive.LocalOrUncPath))
            {
                existingKDrive.LocalOrUncPath = kDriveDefault;
            }

            foreach (var b in settings.StorageBindings)
            {
                try
                {
                    if (!b.IsOnline) continue;
                    if (b.MirrorMeineDatenStructure)
                    {
                        string mirrorRoot = GetBindingMeineDatenRoot(b);
                        EnsureMeineDatenStructure(mirrorRoot);
                    }
                    if (b.AutoIngestToFunnel)
                    {
                        string inboxDir = Path.Combine(b.LocalOrUncPath, "00_Eingang_Trichter");
                        Directory.CreateDirectory(inboxDir);
                    }
                }
                catch { }
            }
        }

        public static string GetBindingMeineDatenRoot(OsStorageBindingDto binding)
        {
            string raw = (binding.LocalOrUncPath ?? "").TrimEnd('\\', '/');
            if (raw.EndsWith("Meine Daten", StringComparison.OrdinalIgnoreCase))
                return raw;
            return Path.Combine(raw, "Meine Daten");
        }

        public static int IngestExternalDownloadsAndCloudInboxes(OsSettings settings, DateTime minDownloadWriteUtc)
        {
            EnsureMeineDatenStructure();
            string inbox = EingangTrichterDir;
            Directory.CreateDirectory(inbox);
            int ingested = 0;

            bool TryMoveCompletedExternalFileToFunnel(string srcFile)
            {
                try
                {
                    string fn = Path.GetFileName(srcFile);
                    if (string.IsNullOrWhiteSpace(fn) || IsGarbageOrTempFile(srcFile))
                        return false;

                    var fi = new FileInfo(srcFile);
                    if (!fi.Exists || fi.Length == 0 || (fi.Attributes & (FileAttributes.Hidden | FileAttributes.System)) != 0)
                        return false;

                    // Verify file is not still being written by browser/sync client
                    using (var stream = new FileStream(srcFile, FileMode.Open, FileAccess.ReadWrite, FileShare.None))
                    {
                        if (stream.Length == 0) return false;
                    }

                    string dest = Path.Combine(inbox, fn);
                    if (File.Exists(dest))
                        dest = Path.Combine(inbox, $"{Path.GetFileNameWithoutExtension(fn)}_{DateTime.Now:HHmmss}{Path.GetExtension(fn)}");

                    File.Move(srcFile, dest);
                    return true;
                }
                catch
                {
                    return false;
                }
            }

            // 1. Intercept new files landing in Windows' standard Downloads folder so no unstructured clutter accumulates
            if (settings.AutoRedirectStandardDownloadsToFunnel)
            {
                try
                {
                    string winDownloads = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), "Downloads");
                    if (Directory.Exists(winDownloads))
                    {
                        foreach (string f in Directory.GetFiles(winDownloads))
                        {
                            try
                            {
                                var fi = new FileInfo(f);
                                if (fi.LastWriteTimeUtc >= minDownloadWriteUtc || fi.CreationTimeUtc >= minDownloadWriteUtc)
                                {
                                    if (TryMoveCompletedExternalFileToFunnel(f))
                                        ingested++;
                                }
                            }
                            catch { }
                        }
                    }
                }
                catch { }
            }

            // 2. Ingest incoming files from bound Cloud/Storage Trichter folders (e.g. kDrive\00_Eingang_Trichter)
            if (settings.StorageBindings != null)
            {
                foreach (var b in settings.StorageBindings.Where(x => x.IsOnline && x.AutoIngestToFunnel))
                {
                    string[] candidateInboxes =
                    {
                        Path.Combine(b.LocalOrUncPath, "00_Eingang_Trichter"),
                        Path.Combine(GetBindingMeineDatenRoot(b), "00_Eingang_Trichter")
                    };

                    foreach (string cInbox in candidateInboxes.Distinct(StringComparer.OrdinalIgnoreCase))
                    {
                        try
                        {
                            if (!Directory.Exists(cInbox)) continue;
                            foreach (string f in Directory.GetFiles(cInbox))
                            {
                                if (TryMoveCompletedExternalFileToFunnel(f))
                                    ingested++;
                            }
                        }
                        catch { }
                    }
                }
            }

            return ingested;
        }

        public static (int CopiedFiles, int BoundTargets) SyncMeineDatenToStorageBindings(OsSettings settings)
        {
            EnsureMeineDatenStructure();
            DiscoverAndEnsureStorageBindings(settings);
            int copied = 0;
            int targets = 0;

            foreach (var b in settings.StorageBindings.Where(x => x.IsOnline && x.MirrorMeineDatenStructure))
            {
                try
                {
                    string targetRoot = GetBindingMeineDatenRoot(b);
                    EnsureMeineDatenStructure(targetRoot);
                    targets++;

                    foreach (var (pillar, _) in MeineDatenPillars)
                    {
                        if (pillar.Equals("00_Eingang_Trichter", StringComparison.OrdinalIgnoreCase))
                            continue;

                        string srcPillarDir = Path.Combine(MeineDatenRootDir, pillar);
                        string dstPillarDir = Path.Combine(targetRoot, pillar);
                        if (!Directory.Exists(srcPillarDir)) continue;

                        foreach (string srcFile in Directory.EnumerateFiles(srcPillarDir, "*", SearchOption.AllDirectories))
                        {
                            try
                            {
                                if (IsGarbageOrTempFile(srcFile)) continue;
                                string rel = Path.GetRelativePath(srcPillarDir, srcFile);
                                string dstFile = Path.Combine(dstPillarDir, rel);
                                string? dstParent = Path.GetDirectoryName(dstFile);
                                if (!string.IsNullOrEmpty(dstParent))
                                    Directory.CreateDirectory(dstParent);

                                var srcInfo = new FileInfo(srcFile);
                                var dstInfo = new FileInfo(dstFile);
                                if (!dstInfo.Exists || dstInfo.Length != srcInfo.Length || srcInfo.LastWriteTimeUtc > dstInfo.LastWriteTimeUtc.AddSeconds(2))
                                {
                                    File.Copy(srcFile, dstFile, overwrite: true);
                                    copied++;
                                }
                            }
                            catch { }
                        }
                    }

                    if (File.Exists(MeineDatenIndexFilePath))
                    {
                        string dstIdx = Path.Combine(targetRoot, "meine_daten_index.json");
                        File.Copy(MeineDatenIndexFilePath, dstIdx, overwrite: true);
                    }

                    PurgeZeroGarbageFiles(targetRoot);
                }
                catch { }
            }

            return (copied, targets);
        }

        public static int SortEingangTrichterFiles()
        {
            EnsureMeineDatenStructure();
            EnforceStrictTrichterStructureOnMeineDaten();
            PurgeZeroGarbageFiles();
            string inbox = EingangTrichterDir;
            if (!Directory.Exists(inbox)) return 0;

            var indexList = new List<MeineDatenIndexRecord>();
            try
            {
                if (File.Exists(MeineDatenIndexFilePath))
                {
                    var loaded = JsonSerializer.Deserialize<List<MeineDatenIndexRecord>>(File.ReadAllText(MeineDatenIndexFilePath, Encoding.UTF8));
                    if (loaded != null) indexList = loaded;
                }
            }
            catch { }

            int movedCount = 0;
            foreach (string file in Directory.GetFiles(inbox))
            {
                try
                {
                    if (IsGarbageOrTempFile(file))
                    {
                        var fiCheck = new FileInfo(file);
                        if (GarbageSystemFileNames.Contains(fiCheck.Name))
                            File.Delete(file);
                        continue;
                    }

                    var (pillar, sub, tags) = ClassifyFileIntoMeineDatenPillar(file);
                    string targetDir = Path.Combine(MeineDatenRootDir, pillar, sub);
                    Directory.CreateDirectory(targetDir);

                    string rawName = Path.GetFileName(file);
                    bool alreadyDated = rawName.Length >= 10 && char.IsDigit(rawName[0]) && char.IsDigit(rawName[1]) && char.IsDigit(rawName[2]) && char.IsDigit(rawName[3]) && rawName[4] == '-';
                    string datedName = alreadyDated ? rawName : $"{DateTime.Now:yyyy-MM-dd}_{rawName}";
                    string finalPath = Path.Combine(targetDir, datedName);
                    if (File.Exists(finalPath))
                    {
                        string stem = Path.GetFileNameWithoutExtension(datedName);
                        string ext = Path.GetExtension(datedName);
                        finalPath = Path.Combine(targetDir, $"{stem}_{DateTime.Now:HHmmss}{ext}");
                    }

                    string sha = "";
                    try
                    {
                        var fi = new FileInfo(file);
                        if (fi.Length <= 50 * 1024 * 1024)
                        {
                            using var fs = File.OpenRead(file);
                            sha = Convert.ToHexString(SHA256.HashData(fs)).Substring(0, 24);
                        }
                    }
                    catch { }

                    File.Move(file, finalPath);
                    indexList.Insert(0, new MeineDatenIndexRecord
                    {
                        FileName = Path.GetFileName(finalPath),
                        PillarFolder = pillar,
                        SubFolder = sub,
                        TargetFullPath = finalPath,
                        Tags = tags,
                        Sha256 = sha,
                        SortedAt = DateTime.Now
                    });
                    movedCount++;
                }
                catch { }
            }

            if (movedCount > 0)
            {
                try
                {
                    string tmpIdx = MeineDatenIndexFilePath + ".tmp";
                    File.WriteAllText(tmpIdx, JsonSerializer.Serialize(indexList.Take(2000).ToList(), new JsonSerializerOptions { WriteIndented = true }), new UTF8Encoding(false));
                    File.Move(tmpIdx, MeineDatenIndexFilePath, overwrite: true);
                    LockDownFileAcl(MeineDatenIndexFilePath);
                }
                catch { }
            }

            return movedCount;
        }

        public static string RecycleOriginsFilePath => Path.Combine(DataDir, "recycle_origins.json");

        public static void RememberRecycleOrigin(string recycledFileName, string? originalParentDir)
        {
            if (string.IsNullOrWhiteSpace(recycledFileName) || string.IsNullOrWhiteSpace(originalParentDir))
                return;
            try
            {
                var map = LoadRecycleOriginsMap();
                map[recycledFileName] = originalParentDir;
                SaveRecycleOriginsMap(map);
            }
            catch { }
        }

        public static string ResolveAndRemoveRecycleOrigin(string recycledFileName)
        {
            try
            {
                var map = LoadRecycleOriginsMap();
                if (map.TryGetValue(recycledFileName, out string? origDir))
                {
                    map.Remove(recycledFileName);
                    SaveRecycleOriginsMap(map);
                    if (!string.IsNullOrWhiteSpace(origDir) && Directory.Exists(origDir))
                        return origDir;
                }
            }
            catch { }
            return OsDesktopDir;
        }

        private static Dictionary<string, string> LoadRecycleOriginsMap()
        {
            try
            {
                if (File.Exists(RecycleOriginsFilePath))
                {
                    var loaded = JsonSerializer.Deserialize<Dictionary<string, string>>(File.ReadAllText(RecycleOriginsFilePath, Encoding.UTF8));
                    if (loaded != null)
                        return new Dictionary<string, string>(loaded, StringComparer.OrdinalIgnoreCase);
                }
            }
            catch { }
            return new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
        }

        private static void SaveRecycleOriginsMap(Dictionary<string, string> map)
        {
            try
            {
                string tmp = RecycleOriginsFilePath + ".tmp";
                File.WriteAllText(tmp, JsonSerializer.Serialize(map, new JsonSerializerOptions { WriteIndented = true }), new UTF8Encoding(false));
                File.Move(tmp, RecycleOriginsFilePath, overwrite: true);
                LockDownFileAcl(RecycleOriginsFilePath);
            }
            catch { }
        }

        public static string SettingsFilePath => Path.Combine(DataDir, "os_settings.json");

        public static string CollectionRootDir
        {
            get
            {
                string exeParent = AppContext.BaseDirectory.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
                DirectoryInfo? parent = Directory.GetParent(exeParent);
                if (parent != null && parent.Exists && Directory.Exists(Path.Combine(parent.FullName, "Funkenwerk OS")))
                    return parent.FullName;

                if (Directory.Exists(@"C:\Funkenwerk Collection\Funkenwerk OS"))
                    return @"C:\Funkenwerk Collection";

                string desktopCol = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "Funkenwerk Collection");
                if (Directory.Exists(Path.Combine(desktopCol, "Funkenwerk OS")))
                    return desktopCol;

                if (Directory.Exists(@"C:\Funkenwerk Collection"))
                    return @"C:\Funkenwerk Collection";

                if (parent != null && parent.Exists) return parent.FullName;

                return desktopCol;
            }
        }

        public static string? ResolveAtelierMediaPath(string fileName)
        {
            string p1 = Path.Combine(AppContext.BaseDirectory, fileName);
            if (File.Exists(p1)) return p1;

            string p2 = Path.Combine(CollectionRootDir, "Funkenwerk OS", fileName);
            if (File.Exists(p2)) return p2;

            string p3 = Path.Combine(CollectionRootDir, "Funkenwerk", fileName);
            if (File.Exists(p3)) return p3;

            return null;
        }

        public static OsSettings LoadSettings()
        {
            try
            {
                if (File.Exists(SettingsFilePath))
                {
                    string json = File.ReadAllText(SettingsFilePath, Encoding.UTF8);
                    var loaded = JsonSerializer.Deserialize<OsSettings>(json);
                    if (loaded != null)
                    {
                        EnsureDefaults(loaded);
                        return loaded;
                    }
                }
            }
            catch { }

            var fresh = new OsSettings();
            EnsureDefaults(fresh);

            try
            {
                string fwSettings = Path.Combine(CollectionRootDir, "Funkenwerk", "settings.json");
                if (File.Exists(fwSettings))
                {
                    using var doc = JsonDocument.Parse(File.ReadAllText(fwSettings, Encoding.UTF8));
                    if (doc.RootElement.TryGetProperty("ApiBaseUrl", out var prop))
                    {
                        string raw = prop.GetString() ?? "";
                        if (raw.Contains("127.0.0.1", StringComparison.OrdinalIgnoreCase) || raw.Contains("localhost", StringComparison.OrdinalIgnoreCase))
                            fresh.ApiEnvironment = "local";
                        else if (raw.Contains("stage.mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase))
                            fresh.ApiEnvironment = "stage";
                        else if (raw.Contains("mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase))
                            fresh.ApiEnvironment = "live";
                    }
                }
            }
            catch { }

            return fresh;
        }

        private static void EnsureDefaults(OsSettings s)
        {
            s.DefaultPrograms ??= new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
            s.DesktopPositions ??= new Dictionary<string, DesktopGridPos>(StringComparer.OrdinalIgnoreCase);
            s.CustomDisplayNames ??= new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
            s.HiddenDesktopShortcutIds ??= new List<string>();
            s.PinnedTaskbarAppIds ??= new List<string>
            {
                "app:browser",
                "sys:explorer",
                "sys:calculator",
                "sys:snip",
                "sys:notes",
                "app:pdf",
                "app:player"
            };
            s.PinnedTaskbarAppIds.RemoveAll(x => string.Equals(x, "app:miro", StringComparison.OrdinalIgnoreCase));
            s.FirewallExtraApps ??= new Dictionary<string, bool>(StringComparer.OrdinalIgnoreCase);

            s.DefaultPrograms.Remove("board");
            if (!s.DefaultPrograms.ContainsKey("web")) s.DefaultPrograms["web"] = "browser";
            if (!s.DefaultPrograms.ContainsKey("pdf")) s.DefaultPrograms["pdf"] = "pdf";
            if (!s.DefaultPrograms.ContainsKey("audio")) s.DefaultPrograms["audio"] = "player";
            if (!s.DefaultPrograms.ContainsKey("video")) s.DefaultPrograms["video"] = "player";
            if (!s.DefaultPrograms.ContainsKey("image")) s.DefaultPrograms["image"] = "internal_image";
            if (!s.DefaultPrograms.ContainsKey("kdbx")) s.DefaultPrograms["kdbx"] = "tresor";
            if (!s.DefaultPrograms.ContainsKey("text")) s.DefaultPrograms["text"] = "internal_editor";

            if (!s.FirewallExtraApps.ContainsKey("kdrive")) s.FirewallExtraApps["kdrive"] = true;
            if (!s.FirewallExtraApps.ContainsKey("discord")) s.FirewallExtraApps["discord"] = false;
            if (!s.FirewallExtraApps.ContainsKey("lol")) s.FirewallExtraApps["lol"] = false;
            if (!s.FirewallExtraApps.ContainsKey("dev")) s.FirewallExtraApps["dev"] = false;

            DiscoverAndEnsureStorageBindings(s);

            if (string.IsNullOrWhiteSpace(s.ExplorerViewMode)) s.ExplorerViewMode = "Details";
            if (string.IsNullOrWhiteSpace(s.ExplorerSortBy)) s.ExplorerSortBy = "Name";
            if (string.IsNullOrWhiteSpace(s.TaskbarPosition)) s.TaskbarPosition = "Bottom";
            if (string.IsNullOrWhiteSpace(s.TaskbarAlignment)) s.TaskbarAlignment = "Left";
        }

        public static void SaveSettings(OsSettings settings)
        {
            try
            {
                string tmp = SettingsFilePath + ".tmp";
                string json = JsonSerializer.Serialize(settings, new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(tmp, json, new UTF8Encoding(false));
                File.Move(tmp, SettingsFilePath, overwrite: true);
                LockDownFileAcl(SettingsFilePath);
            }
            catch { }
        }

        public static string ProtectVaultSecret(string plainSecret)
        {
            if (string.IsNullOrWhiteSpace(plainSecret)) return "";

            byte[]? plainBytes = null;
            byte[]? pwdBytes = null;
            byte[]? key = null;
            byte[]? envelope = null;
            try
            {
                plainBytes = Encoding.UTF8.GetBytes(plainSecret.Trim());
                byte[] salt = RandomNumberGenerator.GetBytes(16);
                byte[] nonce = RandomNumberGenerator.GetBytes(12);
                byte[] tag = new byte[16];
                byte[] cipher = new byte[plainBytes.Length];

                string identity = $"{Environment.MachineName}|{Environment.UserName}|Funkenwerk_AES256GCM_KeyDerivation_v2";
                pwdBytes = Encoding.UTF8.GetBytes(identity);
                key = Rfc2898DeriveBytes.Pbkdf2(pwdBytes, salt, 100_000, HashAlgorithmName.SHA512, 32);

                using (var aesGcm = new AesGcm(key, 16))
                {
                    aesGcm.Encrypt(nonce, plainBytes, cipher, tag, FunkenwerkMainAad);
                }

                envelope = new byte[45 + cipher.Length];
                envelope[0] = 0x02;
                Buffer.BlockCopy(salt, 0, envelope, 1, 16);
                Buffer.BlockCopy(nonce, 0, envelope, 17, 12);
                Buffer.BlockCopy(tag, 0, envelope, 29, 16);
                Buffer.BlockCopy(cipher, 0, envelope, 45, cipher.Length);

                byte[]? dpapiBytes = DpapiProtectWin32(envelope, FunkenwerkMainEntropy);
                if (dpapiBytes == null || dpapiBytes.Length == 0) return "";

                return "FWVAULT2:" + Convert.ToBase64String(dpapiBytes);
            }
            catch
            {
                return "";
            }
            finally
            {
                if (plainBytes != null) CryptographicOperations.ZeroMemory(plainBytes);
                if (pwdBytes != null) CryptographicOperations.ZeroMemory(pwdBytes);
                if (key != null) CryptographicOperations.ZeroMemory(key);
                if (envelope != null) CryptographicOperations.ZeroMemory(envelope);
            }
        }

        public static string UnprotectVaultSecret(string? storedCipher)
        {
            const string prefix = "FWVAULT2:";
            if (string.IsNullOrWhiteSpace(storedCipher) || !storedCipher.StartsWith(prefix, StringComparison.Ordinal))
                return "";

            byte[]? envelope = null;
            byte[]? key = null;
            byte[]? plainBytes = null;
            byte[]? pwdBytes = null;
            try
            {
                byte[] dpapiCipher = Convert.FromBase64String(storedCipher.Substring(prefix.Length));
                envelope = DpapiUnprotectWin32(dpapiCipher, FunkenwerkMainEntropy);
                if (envelope == null || envelope.Length < 45 || envelope[0] != 0x02)
                    return "";

                byte[] salt = new byte[16];
                byte[] nonce = new byte[12];
                byte[] tag = new byte[16];
                int cipherLen = envelope.Length - 45;
                byte[] cipher = new byte[cipherLen];
                plainBytes = new byte[cipherLen];

                Buffer.BlockCopy(envelope, 1, salt, 0, 16);
                Buffer.BlockCopy(envelope, 17, nonce, 0, 12);
                Buffer.BlockCopy(envelope, 29, tag, 0, 16);
                Buffer.BlockCopy(envelope, 45, cipher, 0, cipherLen);

                string identity = $"{Environment.MachineName}|{Environment.UserName}|Funkenwerk_AES256GCM_KeyDerivation_v2";
                pwdBytes = Encoding.UTF8.GetBytes(identity);
                key = Rfc2898DeriveBytes.Pbkdf2(pwdBytes, salt, 100_000, HashAlgorithmName.SHA512, 32);

                using var aesGcm = new AesGcm(key, 16);
                aesGcm.Decrypt(nonce, cipher, tag, plainBytes, FunkenwerkMainAad);
                return Encoding.UTF8.GetString(plainBytes);
            }
            catch
            {
                return "";
            }
            finally
            {
                if (envelope != null) CryptographicOperations.ZeroMemory(envelope);
                if (key != null) CryptographicOperations.ZeroMemory(key);
                if (plainBytes != null) CryptographicOperations.ZeroMemory(plainBytes);
                if (pwdBytes != null) CryptographicOperations.ZeroMemory(pwdBytes);
            }
        }

        public static string ResolveBearerToken(OsSettings settings, string env)
        {
            string normEnv = (env ?? "live").Trim().ToLowerInvariant();
            string? ownCipher = normEnv switch
            {
                "local" => settings.EncryptedTokenLocal,
                "stage" => settings.EncryptedTokenStage,
                _ => settings.EncryptedTokenLive
            };

            string ownPlain = UnprotectVaultSecret(ownCipher);
            if (!string.IsNullOrWhiteSpace(ownPlain))
                return ownPlain.Trim();

            try
            {
                string fwSettings = Path.Combine(CollectionRootDir, "Funkenwerk", "settings.json");
                if (File.Exists(fwSettings))
                {
                    using var doc = JsonDocument.Parse(File.ReadAllText(fwSettings, Encoding.UTF8));
                    var root = doc.RootElement;
                    var candidates = new List<string?>();
                    if (normEnv == "local" && root.TryGetProperty("ApiTokenLocal", out var tl)) candidates.Add(tl.GetString());
                    if (normEnv == "stage" && root.TryGetProperty("ApiTokenStage", out var ts)) candidates.Add(ts.GetString());
                    if (normEnv == "live" && root.TryGetProperty("ApiTokenLive", out var tlv)) candidates.Add(tlv.GetString());
                    if (root.TryGetProperty("ApiToken", out var ta)) candidates.Add(ta.GetString());

                    foreach (string? c in candidates)
                    {
                        string dec = UnprotectVaultSecret(c);
                        if (!string.IsNullOrWhiteSpace(dec))
                            return dec.Trim();
                    }
                }
            }
            catch { }

            return "";
        }

        public static void StoreBearerTokenUnified(OsSettings settings, string env, string rawBearerToken)
        {
            string encrypted = ProtectVaultSecret(rawBearerToken);
            if (string.IsNullOrEmpty(encrypted)) return;

            string normEnv = (env ?? "live").Trim().ToLowerInvariant();
            if (normEnv == "local") settings.EncryptedTokenLocal = encrypted;
            else if (normEnv == "stage") settings.EncryptedTokenStage = encrypted;
            else settings.EncryptedTokenLive = encrypted;

            settings.ApiEnvironment = normEnv;
            SaveSettings(settings);

            try
            {
                string fwDir = Path.Combine(CollectionRootDir, "Funkenwerk");
                if (Directory.Exists(fwDir))
                {
                    string fwSettingsPath = Path.Combine(fwDir, "settings.json");
                    JsonObject obj = File.Exists(fwSettingsPath)
                        ? (JsonNode.Parse(File.ReadAllText(fwSettingsPath, Encoding.UTF8)) as JsonObject ?? new JsonObject())
                        : new JsonObject();

                    string apiBaseUrl = SeelenfunkeLiveClient.GetBaseUrlForEnvironment(normEnv);
                    obj["ApiBaseUrl"] = apiBaseUrl;
                    obj["ApiToken"] = encrypted;
                    if (normEnv == "local") obj["ApiTokenLocal"] = encrypted;
                    else if (normEnv == "stage") obj["ApiTokenStage"] = encrypted;
                    else obj["ApiTokenLive"] = encrypted;

                    if (!string.IsNullOrWhiteSpace(settings.SavedEmail))
                        obj["SavedAuthEmail"] = ProtectVaultSecret(settings.SavedEmail);
                    obj["RememberCredentials"] = true;
                    obj["IsSessionLocked"] = false;

                    string tmp = fwSettingsPath + ".tmp";
                    File.WriteAllText(tmp, obj.ToJsonString(new JsonSerializerOptions { WriteIndented = true }), new UTF8Encoding(false));
                    File.Move(tmp, fwSettingsPath, overwrite: true);
                    LockDownFileAcl(fwSettingsPath);
                }
            }
            catch { }
        }

        private static byte[]? DpapiProtectWin32(byte[] plainBytes, byte[] entropy)
        {
            IntPtr plainPtr = IntPtr.Zero;
            IntPtr entropyPtr = IntPtr.Zero;
            DATA_BLOB outBlob = default;
            try
            {
                plainPtr = Marshal.AllocHGlobal(plainBytes.Length);
                Marshal.Copy(plainBytes, 0, plainPtr, plainBytes.Length);
                entropyPtr = Marshal.AllocHGlobal(entropy.Length);
                Marshal.Copy(entropy, 0, entropyPtr, entropy.Length);

                var inBlob = new DATA_BLOB { cbData = plainBytes.Length, pbData = plainPtr };
                var entBlob = new DATA_BLOB { cbData = entropy.Length, pbData = entropyPtr };

                if (CryptProtectData(ref inBlob, null, ref entBlob, IntPtr.Zero, IntPtr.Zero, 0x1, out outBlob)
                    && outBlob.pbData != IntPtr.Zero && outBlob.cbData > 0)
                {
                    byte[] cipher = new byte[outBlob.cbData];
                    Marshal.Copy(outBlob.pbData, cipher, 0, outBlob.cbData);
                    return cipher;
                }
            }
            catch { }
            finally
            {
                if (plainPtr != IntPtr.Zero) Marshal.FreeHGlobal(plainPtr);
                if (entropyPtr != IntPtr.Zero) Marshal.FreeHGlobal(entropyPtr);
                if (outBlob.pbData != IntPtr.Zero) LocalFree(outBlob.pbData);
            }
            return null;
        }

        private static byte[]? DpapiUnprotectWin32(byte[] cipherBytes, byte[] entropy)
        {
            IntPtr cipherPtr = IntPtr.Zero;
            IntPtr entropyPtr = IntPtr.Zero;
            DATA_BLOB outBlob = default;
            try
            {
                cipherPtr = Marshal.AllocHGlobal(cipherBytes.Length);
                Marshal.Copy(cipherBytes, 0, cipherPtr, cipherBytes.Length);
                entropyPtr = Marshal.AllocHGlobal(entropy.Length);
                Marshal.Copy(entropy, 0, entropyPtr, entropy.Length);

                var inBlob = new DATA_BLOB { cbData = cipherBytes.Length, pbData = cipherPtr };
                var entBlob = new DATA_BLOB { cbData = entropy.Length, pbData = entropyPtr };

                if (CryptUnprotectData(ref inBlob, IntPtr.Zero, ref entBlob, IntPtr.Zero, IntPtr.Zero, 0x1, out outBlob)
                    && outBlob.pbData != IntPtr.Zero && outBlob.cbData >= 0)
                {
                    byte[] plain = new byte[outBlob.cbData];
                    if (outBlob.cbData > 0)
                        Marshal.Copy(outBlob.pbData, plain, 0, outBlob.cbData);
                    return plain;
                }
            }
            catch { }
            finally
            {
                if (cipherPtr != IntPtr.Zero) Marshal.FreeHGlobal(cipherPtr);
                if (entropyPtr != IntPtr.Zero) Marshal.FreeHGlobal(entropyPtr);
                if (outBlob.pbData != IntPtr.Zero) LocalFree(outBlob.pbData);
            }
            return null;
        }

        public static void LockDownDirectoryAcl(string dirPath)
        {
            try
            {
                var di = new DirectoryInfo(dirPath);
                if (!di.Exists) return;
                var user = WindowsIdentity.GetCurrent().User;
                if (user == null) return;

                var sec = new DirectorySecurity();
                sec.SetAccessRuleProtection(true, false);
                sec.AddAccessRule(new FileSystemAccessRule(user, FileSystemRights.FullControl, InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit, PropagationFlags.None, AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null), FileSystemRights.FullControl, InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit, PropagationFlags.None, AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null), FileSystemRights.FullControl, InheritanceFlags.ContainerInherit | InheritanceFlags.ObjectInherit, PropagationFlags.None, AccessControlType.Allow));
                di.SetAccessControl(sec);
            }
            catch { }
        }

        public static void LockDownFileAcl(string filePath)
        {
            try
            {
                var fi = new FileInfo(filePath);
                if (!fi.Exists) return;
                var user = WindowsIdentity.GetCurrent().User;
                if (user == null) return;

                var sec = new FileSecurity();
                sec.SetAccessRuleProtection(true, false);
                sec.AddAccessRule(new FileSystemAccessRule(user, FileSystemRights.FullControl, AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null), FileSystemRights.FullControl, AccessControlType.Allow));
                sec.AddAccessRule(new FileSystemAccessRule(new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null), FileSystemRights.FullControl, AccessControlType.Allow));
                fi.SetAccessControl(sec);
            }
            catch { }
        }
    }
}

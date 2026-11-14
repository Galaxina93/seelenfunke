using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Animation;
using System.Windows.Media.Imaging;
using System.Windows.Shapes;
using System.Windows.Threading;
using Brush = System.Windows.Media.Brush;
using Brushes = System.Windows.Media.Brushes;
using Button = System.Windows.Controls.Button;
using Clipboard = System.Windows.Clipboard;
using Cursors = System.Windows.Input.Cursors;
using DataFormats = System.Windows.DataFormats;
using DataObject = System.Windows.DataObject;
using DragDropEffects = System.Windows.DragDropEffects;
using DragEventArgs = System.Windows.DragEventArgs;
using HorizontalAlignment = System.Windows.HorizontalAlignment;
using Image = System.Windows.Controls.Image;
using KeyEventArgs = System.Windows.Input.KeyEventArgs;
using MessageBox = System.Windows.MessageBox;
using MouseEventArgs = System.Windows.Input.MouseEventArgs;
using Orientation = System.Windows.Controls.Orientation;
using Panel = System.Windows.Controls.Panel;
using Path = System.IO.Path;
using TextBox = System.Windows.Controls.TextBox;
using VerticalAlignment = System.Windows.VerticalAlignment;

namespace FunkenwerkOS
{
    public partial class MainWindow : Window
    {
        private const double GridCellW = 104.0;
        private const double GridCellH = 104.0;
        private const double GridPadX = 18.0;
        private const double GridPadY = 18.0;

        private readonly bool _isShellBoot;
        private readonly string? _initialWorkspace;
        private OsSettings _settings;
        private OsDashboardSnapshot _lastSnapshot = new();
        private SecondaryWallWindow? _secondaryWallWindow;
        private GewerbeMonitorWindow? _gewerbeMonitorWindow;
        private bool _isFullscreenBorderless;

        // Atelier Wallpaper Loop (Zero-Flicker Underneath Pre-Roll Engine)
        private readonly DispatcherTimer _wallpaperLoopTimer = new() { Interval = TimeSpan.FromMilliseconds(200) };
        private bool _isVideoActiveB;
        private bool _isStandbyPreRolling;
        private bool _isVideoCrossfading;
        private DateTime _videoCrossfadeStartUtc;

        // Timers & Live FileSystemWatchers
        private readonly DateTime _osBootUtc = DateTime.UtcNow;
        private readonly DispatcherTimer _fastSystemTimer = new() { Interval = TimeSpan.FromSeconds(1) };
        private readonly DispatcherTimer _dashboardSyncTimer = new() { Interval = TimeSpan.FromSeconds(20) };
        private readonly DispatcherTimer _toastTimer = new() { Interval = TimeSpan.FromSeconds(3.2) };
        private readonly DispatcherTimer _fsDebounceTimer = new() { Interval = TimeSpan.FromMilliseconds(350) };
        private FileSystemWatcher? _desktopFsWatcher;
        private FileSystemWatcher? _funnelFsWatcher;
        private FileSystemWatcher? _meineDatenRootWatcher;
        private FileSystemWatcher? _downloadsFsWatcher;
        private readonly List<FileSystemWatcher> _cloudInboxWatchers = new();
        private bool _fsDesktopDirty;
        private bool _fsFunnelDirty;

        // Desktop Icons & Selection
        private readonly List<DesktopItemModel> _desktopItems = new();
        private readonly Dictionary<string, Border> _desktopVisuals = new(StringComparer.OrdinalIgnoreCase);
        private readonly HashSet<string> _selectedDesktopIds = new(StringComparer.OrdinalIgnoreCase);
        private readonly Dictionary<string, (double Left, double Top)> _iconDragGroupStartOffsets = new(StringComparer.OrdinalIgnoreCase);
        private DesktopItemModel? _draggingIcon;
        private Border? _draggingVisual;
        private System.Windows.Point _iconDragStartMouse;
        private double _iconDragStartLeft;
        private double _iconDragStartTop;
        private bool _isIconDragging;
        private string? _hoveredDesktopDropTargetId;
        private DateTime _lastIconClickUtc;
        private string _lastClickedIconId = "";
        private bool _isMarqueeSelecting;
        private System.Windows.Point _marqueeOrigin;
        private DesktopItemModel? _renamingItem;

        // Floating OS Windows (Move, Resize, Snap, Minimize, Maximize)
        private int _topWindowZIndex = 10;
        private Border? _draggingWinBorder;
        private string? _draggingWinKey;
        private string? _activeAeroSnapZone;
        private System.Windows.Point _winDragStartMouse;
        private double _winDragStartLeft;
        private double _winDragStartTop;
        private Border? _resizingWinBorder;
        private System.Windows.Point _winResizeStartMouse;
        private double _winResizeStartW;
        private double _winResizeStartH;
        private readonly HashSet<string> _minimizedInternalWins = new(StringComparer.OrdinalIgnoreCase);
        private readonly Dictionary<string, Rect> _preMaximizeBounds = new(StringComparer.OrdinalIgnoreCase);
        private DateTime _lastWallpaperRippleUtc;
        private System.Windows.Point _lastWallpaperRipplePos;
        private string _lastTaskbarSignature = "";

        // Pro File Explorer State (Async Load, History, Selection, Sort, Clipboard, Drag & Drop, Modal)
        private string _currentExplorerDir = "";
        private readonly Stack<string> _explorerBackStack = new();
        private readonly Stack<string> _explorerForwardStack = new();
        private List<OsFileEntryDto> _currentExplorerEntries = new();
        private List<OsFileEntryDto> _visibleExplorerEntries = new();
        private System.Threading.CancellationTokenSource? _explorerLoadCts;
        private int _explorerLoadVersion;
        private readonly HashSet<string> _selectedExplorerPaths = new(StringComparer.OrdinalIgnoreCase);
        private string? _lastClickedExplorerPath;
        private DateTime _lastExplorerClickUtc;
        private System.Windows.Point _explorerDragOrigin;
        private bool _explorerMouseArmedForDrag;
        private readonly List<string> _fileClipboardPaths = new();
        private bool _fileClipboardIsCut;
        private string? _expModalRenameTargetFullPath;
        private string? _openNoteFilePath;
        private string? _currentLightboxImagePath;
        private double _currentLightboxZoom = 1.0;

        // Audio, Network, Account, Calendar & Built-In Apps State
        private bool _suppressVolumeEvent;
        private bool _isLoginPwdVisible;
        private bool _isWifiPwdVisible;
        private bool _isLockPwdVisible;
        private bool _isLockPinSetupVisible;
        private bool _desktopLiveBootInitialized;
        private WifiNetworkDto? _selectedWifiNet;

        [DllImport("advapi32.dll", SetLastError = true, CharSet = CharSet.Unicode)]
        private static extern bool LogonUser(
            string lpszUsername,
            string lpszDomain,
            string lpszPassword,
            int dwLogonType,
            int dwLogonProvider,
            out IntPtr phToken);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool CloseHandle(IntPtr hObject);

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool LockWorkStation();
        private string _selectedLoginEnv = "live";
        private bool _initialOrdersSeeded;
        private bool _focusMusicActive;
        private DateTime _displayedCalendarMonth;

        private WindowsKeySearchHook? _winKeyHook;
        private List<DesktopItemModel> _currentSearchResults = new();
        private int _highlightedSearchIndex;
        private string? _modalCustomRenameItemId;
        private System.Windows.Point _startMenuAppDragStart;
        private string? _startMenuArmedDragAppId;
        private System.Windows.Point _taskbarDragStart;
        private OsOpenWindowDto? _taskbarArmedDragDto;

        private readonly OsCalculatorEngine _calcEngine = new();
        private List<StickyNoteDto> _stickyNotes = new();
        private StickyNoteDto? _activeStickyNote;
        private bool _suppressStickyEvent;
        private System.Windows.Point _stickyNoteDragOrigin;
        private BitmapSource? _currentSnipBitmap;
        private bool _snipDelay3Sec;

        private static readonly System.Collections.Concurrent.ConcurrentDictionary<string, SolidColorBrush> s_frozenBrushCache = new(StringComparer.OrdinalIgnoreCase);
        private static readonly CultureInfo DeCulture = new("de-DE");
        private static readonly CubicEase SmoothEaseOut = new() { EasingMode = EasingMode.EaseOut };
        private const string WslSeelenfunkeProjectPath = @"\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke";

        public MainWindow(bool isShellMode, string? initialWorkspace)
        {
            InitializeComponent();

            _isShellBoot = isShellMode;
            _initialWorkspace = initialWorkspace;
            _settings = OsVaultAndStorage.LoadSettings();
            OsVaultAndStorage.DiscoverAndEnsureStorageBindings(_settings);
            OsVaultAndStorage.EnsureMeineDatenStructure();
            OsVaultAndStorage.IngestExternalDownloadsAndCloudInboxes(_settings, _osBootUtc);
            OsVaultAndStorage.SortEingangTrichterFiles();
            _selectedLoginEnv = _settings.ApiEnvironment;
            _displayedCalendarMonth = new DateTime(DateTime.Now.Year, DateTime.Now.Month, 1);
            _currentExplorerDir = OsVaultAndStorage.MeineDatenRootDir;

            ExplorerHeaderIcon.Source = OsHardwareAndAudioEngine.GetSystemBadgeIcon("explorer");
            ExplorerWslProjectBtn.Visibility = Directory.Exists(WslSeelenfunkeProjectPath) ? Visibility.Visible : Visibility.Collapsed;

            LoginEmailBox.Text = string.IsNullOrWhiteSpace(_settings.SavedEmail)
                ? "kontakt@mein-seelenfunke.de"
                : _settings.SavedEmail;

            ApplyTheme(_settings.DarkMode);
            ApplyTaskbarLayoutAndStyle();
            UpdateTaskbarSettingsButtonsUi();
            ApplyExplorerViewModeUi();
            UpdateDefaultProgramsButtonsUi();
            UpdateStorageAndFunnelSettingsUi();
            UpdateFirewallAppButtonsUi();
            UpdateVisitorPrivacyButtonsUi();
            UpdateOrderChimeButtonUi();
            RefreshExplorerDrivesSidebar();

            _wallpaperLoopTimer.Tick += WallpaperLoopTimer_Tick;
            _fastSystemTimer.Tick += (_, _) => TickSystemClockAndTray();
            _dashboardSyncTimer.Tick += async (_, _) => await RefreshDashboardLiveAsync();
            _fsDebounceTimer.Tick += FsDebounceTimer_Tick;
            _toastTimer.Tick += (_, _) =>
            {
                OsToastBanner.Visibility = Visibility.Collapsed;
                _toastTimer.Stop();
            };

            InitLiveFileSystemWatchers();

            SizeChanged += (_, _) => ClampFloatingWindowsToBounds();
            Loaded += MainWindow_Loaded;
            Closed += (_, _) => DisposeManagedResources();
        }

        public void DisposeManagedResources()
        {
            try { _winKeyHook?.Dispose(); _winKeyHook = null; } catch { }
            try { _desktopFsWatcher?.Dispose(); _desktopFsWatcher = null; } catch { }
            try { _funnelFsWatcher?.Dispose(); _funnelFsWatcher = null; } catch { }
            try { _meineDatenRootWatcher?.Dispose(); _meineDatenRootWatcher = null; } catch { }
            try { _downloadsFsWatcher?.Dispose(); _downloadsFsWatcher = null; } catch { }
            foreach (var cw in _cloudInboxWatchers)
            {
                try { cw.Dispose(); } catch { }
            }
            _cloudInboxWatchers.Clear();
            _fsDebounceTimer.Stop();
            _wallpaperLoopTimer.Stop();
            _fastSystemTimer.Stop();
            _dashboardSyncTimer.Stop();
            _toastTimer.Stop();
            try { DesktopSparksLayer.SetRunning(false); } catch { }
            try { _secondaryWallWindow?.Close(); _secondaryWallWindow = null; } catch { }
            try { _gewerbeMonitorWindow?.Close(); _gewerbeMonitorWindow = null; } catch { }
            OsHardwareAndAudioEngine.ReleaseHighResTimerPeriod();
        }

        private void InitLiveFileSystemWatchers()
        {
            try
            {
                _desktopFsWatcher?.Dispose();
                _funnelFsWatcher?.Dispose();
                _meineDatenRootWatcher?.Dispose();
                _downloadsFsWatcher?.Dispose();
                foreach (var cw in _cloudInboxWatchers) cw.Dispose();
                _cloudInboxWatchers.Clear();

                if (Directory.Exists(OsVaultAndStorage.OsDesktopDir))
                {
                    _desktopFsWatcher = new FileSystemWatcher(OsVaultAndStorage.OsDesktopDir)
                    {
                        IncludeSubdirectories = false,
                        NotifyFilter = NotifyFilters.FileName | NotifyFilters.DirectoryName | NotifyFilters.LastWrite,
                        EnableRaisingEvents = true
                    };
                    FileSystemEventHandler onDesktopChange = (_, _) =>
                    {
                        _fsDesktopDirty = true;
                        Dispatcher.BeginInvoke(() => { _fsDebounceTimer.Stop(); _fsDebounceTimer.Start(); });
                    };
                    _desktopFsWatcher.Created += onDesktopChange;
                    _desktopFsWatcher.Deleted += onDesktopChange;
                    _desktopFsWatcher.Renamed += (_, _) => onDesktopChange(null!, null!);
                }

                FileSystemEventHandler onFunnelChange = (_, _) =>
                {
                    _fsFunnelDirty = true;
                    Dispatcher.BeginInvoke(() => { _fsDebounceTimer.Stop(); _fsDebounceTimer.Start(); });
                };

                if (Directory.Exists(OsVaultAndStorage.EingangTrichterDir))
                {
                    _funnelFsWatcher = new FileSystemWatcher(OsVaultAndStorage.EingangTrichterDir)
                    {
                        IncludeSubdirectories = false,
                        NotifyFilter = NotifyFilters.FileName | NotifyFilters.LastWrite,
                        EnableRaisingEvents = true
                    };
                    _funnelFsWatcher.Created += onFunnelChange;
                    _funnelFsWatcher.Changed += onFunnelChange;
                    _funnelFsWatcher.Renamed += (_, _) => onFunnelChange(null!, null!);
                }

                if (Directory.Exists(OsVaultAndStorage.MeineDatenRootDir))
                {
                    _meineDatenRootWatcher = new FileSystemWatcher(OsVaultAndStorage.MeineDatenRootDir)
                    {
                        IncludeSubdirectories = false,
                        NotifyFilter = NotifyFilters.FileName | NotifyFilters.DirectoryName,
                        EnableRaisingEvents = true
                    };
                    _meineDatenRootWatcher.Created += onFunnelChange;
                    _meineDatenRootWatcher.Renamed += (_, _) => onFunnelChange(null!, null!);
                }

                string winDownloads = Path.Combine(OsVaultAndStorage.UserProfileDir, "Downloads");
                if (Directory.Exists(winDownloads))
                {
                    _downloadsFsWatcher = new FileSystemWatcher(winDownloads)
                    {
                        IncludeSubdirectories = false,
                        NotifyFilter = NotifyFilters.FileName | NotifyFilters.LastWrite,
                        EnableRaisingEvents = true
                    };
                    _downloadsFsWatcher.Created += onFunnelChange;
                    _downloadsFsWatcher.Changed += onFunnelChange;
                    _downloadsFsWatcher.Renamed += (_, _) => onFunnelChange(null!, null!);
                }

                if (_settings.StorageBindings != null)
                {
                    foreach (var b in _settings.StorageBindings.Where(x => x.IsOnline && x.AutoIngestToFunnel))
                    {
                        try
                        {
                            string cloudInbox = Path.Combine(b.LocalOrUncPath, "00_Eingang_Trichter");
                            Directory.CreateDirectory(cloudInbox);
                            var cw = new FileSystemWatcher(cloudInbox)
                            {
                                IncludeSubdirectories = false,
                                NotifyFilter = NotifyFilters.FileName | NotifyFilters.LastWrite,
                                EnableRaisingEvents = true
                            };
                            cw.Created += onFunnelChange;
                            cw.Changed += onFunnelChange;
                            cw.Renamed += (_, _) => onFunnelChange(null!, null!);
                            _cloudInboxWatchers.Add(cw);
                        }
                        catch { }
                    }
                }
            }
            catch { }
        }

        private void FsDebounceTimer_Tick(object? sender, EventArgs e)
        {
            _fsDebounceTimer.Stop();
            if (_fsFunnelDirty)
            {
                _fsFunnelDirty = false;
                int ingested = OsVaultAndStorage.IngestExternalDownloadsAndCloudInboxes(_settings, _osBootUtc);
                int sorted = OsVaultAndStorage.SortEingangTrichterFiles();
                if (sorted > 0 || ingested > 0)
                {
                    ShowToast($"✓ {Math.Max(sorted, ingested)} Datei(en) im Daten-Trichter (8 Säulen) automatisch einsortiert");
                    _ = Task.Run(() => OsVaultAndStorage.SyncMeineDatenToStorageBindings(_settings));
                }
                if (ExplorerWin.Visibility == Visibility.Visible)
                    NavigateExplorer(_currentExplorerDir, recordHistory: false);
            }

            if (_fsDesktopDirty)
            {
                _fsDesktopDirty = false;
                if (_settings.ZeroGarbageGuardEnabled)
                    OsVaultAndStorage.PurgeZeroGarbageFiles();
                if (!_isIconDragging && _renamingItem == null)
                    RebuildDesktopIcons();
                if (ExplorerWin.Visibility == Visibility.Visible &&
                    string.Equals(Path.GetFullPath(_currentExplorerDir).TrimEnd('\\'), Path.GetFullPath(OsVaultAndStorage.OsDesktopDir).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase))
                {
                    NavigateExplorer(_currentExplorerDir, recordHistory: false);
                }
            }
        }

        private async void MainWindow_Loaded(object sender, RoutedEventArgs e)
        {
            OsHardwareAndAudioEngine.EnsureWindowsExplorerTaskbarRunning(_isShellBoot);
            WindowState = WindowState.Maximized;

            try
            {
                FortressProvisioningEngine.WriteMiniPcDeploymentScripts(FortressProvisioningEngine.DefaultSetupScriptsDirectory);
            }
            catch { }

            _winKeyHook = new WindowsKeySearchHook(OpenInstantWindowsKeySearch);

            bool settingsChanged = false;
            if (!_settings.PinnedTaskbarAppIds.Any(x => string.Equals(x, "sys:gewerbe", StringComparison.OrdinalIgnoreCase)))
            {
                _settings.PinnedTaskbarAppIds.Insert(0, "sys:gewerbe");
                settingsChanged = true;
            }
            if (!_settings.PinnedTaskbarAppIds.Any(x => string.Equals(x, "sys:leitstand", StringComparison.OrdinalIgnoreCase)))
            {
                int idx = Math.Min(1, _settings.PinnedTaskbarAppIds.Count);
                _settings.PinnedTaskbarAppIds.Insert(idx, "sys:leitstand");
                settingsChanged = true;
            }
            if (_settings.VisibleDesktopShortcutIds.Count > 0)
            {
                _settings.VisibleDesktopShortcutIds.Clear();
                settingsChanged = true;
            }
            if (settingsChanged)
                OsVaultAndStorage.SaveSettings(_settings);

            InitStickyNotesUi();
            RefreshCalculatorUi();
            RebuildDesktopIcons();
            RefreshExplorerDrivesSidebar();
            RefreshStartMenuApps("");
            RenderCalendarMonthGrid();
            OsHardwareAndAudioEngine.EnsureKioskPowerAndDotNetIntegrity();
            OsHardwareAndAudioEngine.TriggerBackgroundTrayRefresh();
            TickSystemClockAndTray();
            RefreshAudioUi(forceDirectRead: true);
            RefreshFortressAuditUi();

            if (string.Equals(_initialWorkspace, "gewerbe", StringComparison.OrdinalIgnoreCase))
            {
                OpenOrFocusGewerbeMonitor();
                return;
            }

            InitAtelierWallpaperEngine();
            ApplyWallpaperAndSparksSettings();

            _fastSystemTimer.Start();
            _dashboardSyncTimer.Start();

            await RunMagicPortalAwakeningAsync("FUNKENWERK OS ERWACHT");
            if (string.Equals(_initialWorkspace, "leitstand", StringComparison.OrdinalIgnoreCase))
                OpenInternalWindowAnimated("LeitstandWin");

            _desktopLiveBootInitialized = true;
            _ = RefreshLanInfrastructureAsync();
            await RefreshDashboardLiveAsync();
        }

        // =====================================================================
        // 1. ATELIER WALLPAPER & 60 FPS VSYNC SPARKS
        // =====================================================================
        private void InitAtelierWallpaperEngine()
        {
            try
            {
                string? posterPath = OsVaultAndStorage.ResolveAtelierMediaPath("login_atelier_poster.jpg");
                if (!string.IsNullOrEmpty(posterPath))
                {
                    var bi = new BitmapImage();
                    bi.BeginInit();
                    bi.DecodePixelWidth = 1920;
                    bi.CacheOption = BitmapCacheOption.OnLoad;
                    bi.UriSource = new Uri(posterPath, UriKind.Absolute);
                    bi.EndInit();
                    bi.Freeze();
                    WallpaperPosterImg.Source = bi;
                }

                if (string.Equals(_settings.WallpaperMode, "AtelierVideo", StringComparison.OrdinalIgnoreCase))
                    EnsureAtelierVideoSourcesLoaded();
            }
            catch { }
        }

        private void EnsureAtelierVideoSourcesLoaded()
        {
            try
            {
                if (WallpaperVideoA.Source != null) return;
                string? videoPath = OsVaultAndStorage.ResolveAtelierMediaPath("atelier_seamless_loop.mp4")
                                 ?? OsVaultAndStorage.ResolveAtelierMediaPath("login_atelier_bg.mp4");
                if (!string.IsNullOrEmpty(videoPath))
                {
                    var uri = new Uri(videoPath, UriKind.Absolute);
                    WallpaperVideoA.Source = uri;
                    WallpaperVideoB.Source = uri;
                }
            }
            catch { }
        }

        private void ApplyWallpaperAndSparksSettings()
        {
            string mode = _settings.WallpaperMode ?? "AtelierPoster";
            if (mode == "AtelierVideo")
                EnsureAtelierVideoSourcesLoaded();
            bool hasVideo = WallpaperVideoA.Source != null;
            bool hasPoster = WallpaperPosterImg.Source != null;

            Style goldBtn = (Style)Resources["NobleGoldBtn"];
            Style glassBtn = (Style)Resources["GlassBtn"];
            WpVideoBtn.Style = mode == "AtelierVideo" ? goldBtn : glassBtn;
            WpPosterBtn.Style = mode == "AtelierPoster" ? goldBtn : glassBtn;
            WpKosmosBtn.Style = mode == "ObsidianKosmos" ? goldBtn : glassBtn;

            if (mode == "AtelierVideo" && hasVideo)
            {
                WallpaperPosterImg.Visibility = hasPoster ? Visibility.Visible : Visibility.Collapsed;
                WallpaperVideoA.Visibility = Visibility.Visible;
                WallpaperVideoB.Visibility = Visibility.Visible;
                _isVideoActiveB = false;
                _isStandbyPreRolling = false;
                _isVideoCrossfading = false;
                Panel.SetZIndex(WallpaperPosterImg, 1);
                Panel.SetZIndex(WallpaperVideoB, 2);
                Panel.SetZIndex(WallpaperVideoA, 3);
                WallpaperVideoA.Opacity = 1.0;
                WallpaperVideoB.Opacity = 1.0;
                try
                {
                    WallpaperVideoA.Position = TimeSpan.Zero;
                    WallpaperVideoA.Play();
                    WallpaperVideoB.Position = TimeSpan.Zero;
                    WallpaperVideoB.Play();
                    WallpaperVideoB.Pause();
                    _wallpaperLoopTimer.Start();
                }
                catch { }
            }
            else
            {
                _wallpaperLoopTimer.Stop();
                try { WallpaperVideoA.Pause(); WallpaperVideoB.Pause(); } catch { }
                WallpaperVideoA.Visibility = Visibility.Collapsed;
                WallpaperVideoB.Visibility = Visibility.Collapsed;
                WallpaperPosterImg.Visibility = (mode == "AtelierPoster" && hasPoster) ? Visibility.Visible : Visibility.Collapsed;
            }

            DesktopSparksLayer.Visibility = _settings.AmbientSparksEnabled ? Visibility.Visible : Visibility.Collapsed;
            DesktopSparksLayer.SetRunning(_settings.AmbientSparksEnabled);
            SparksToggleBtn.Content = _settings.AmbientSparksEnabled ? "AKTIV" : "AUS";
            SparksToggleBtn.Style = _settings.AmbientSparksEnabled ? goldBtn : glassBtn;
        }

        private void WallpaperLoopTimer_Tick(object? sender, EventArgs e)
        {
            try
            {
                if (WallpaperVideoA.Visibility != Visibility.Visible) return;
                var active = _isVideoActiveB ? WallpaperVideoB : WallpaperVideoA;
                var standby = _isVideoActiveB ? WallpaperVideoA : WallpaperVideoB;

                if (_isVideoCrossfading)
                {
                    double alpha = Math.Clamp((DateTime.UtcNow - _videoCrossfadeStartUtc).TotalMilliseconds / 380.0, 0.0, 1.0);
                    active.Opacity = 1.0 - alpha;

                    if (alpha >= 1.0)
                    {
                        // Promote standby to top (ZIndex 3), demote active underneath (ZIndex 2) BEFORE seeking!
                        standby.Opacity = 1.0;
                        Panel.SetZIndex(standby, 3);
                        Panel.SetZIndex(active, 2);

                        active.Pause();
                        active.Position = TimeSpan.Zero;
                        active.Opacity = 1.0;

                        _isVideoActiveB = !_isVideoActiveB;
                        _isVideoCrossfading = false;
                        _isStandbyPreRolling = false;
                        _wallpaperLoopTimer.Interval = TimeSpan.FromMilliseconds(200);
                    }
                    return;
                }

                double dur = active.NaturalDuration.HasTimeSpan ? active.NaturalDuration.TimeSpan.TotalSeconds : 32.0;
                double pos = active.Position.TotalSeconds;

                if (dur > 2.5 && !_isStandbyPreRolling && pos >= dur - 1.50)
                {
                    // Pre-roll standby completely hidden underneath active (ZIndex 2 under ZIndex 3)
                    _isStandbyPreRolling = true;
                    standby.Opacity = 1.0;
                    Panel.SetZIndex(standby, 2);
                    Panel.SetZIndex(active, 3);
                    standby.Play();
                }

                if (dur > 2.5 && _isStandbyPreRolling && !_isVideoCrossfading && pos >= dur - 0.50)
                {
                    _isVideoCrossfading = true;
                    _videoCrossfadeStartUtc = DateTime.UtcNow;
                    _wallpaperLoopTimer.Interval = TimeSpan.FromMilliseconds(25);
                }
            }
            catch { }
        }

        private void SetWallpaperVideo_Click(object sender, RoutedEventArgs e) { _settings.WallpaperMode = "AtelierVideo"; OsVaultAndStorage.SaveSettings(_settings); ApplyWallpaperAndSparksSettings(); }
        private void SetWallpaperPoster_Click(object sender, RoutedEventArgs e) { _settings.WallpaperMode = "AtelierPoster"; OsVaultAndStorage.SaveSettings(_settings); ApplyWallpaperAndSparksSettings(); }
        private void SetWallpaperKosmos_Click(object sender, RoutedEventArgs e) { _settings.WallpaperMode = "ObsidianKosmos"; OsVaultAndStorage.SaveSettings(_settings); ApplyWallpaperAndSparksSettings(); }
        private void ToggleSparks_Click(object sender, RoutedEventArgs e) { _settings.AmbientSparksEnabled = !_settings.AmbientSparksEnabled; OsVaultAndStorage.SaveSettings(_settings); ApplyWallpaperAndSparksSettings(); }

        // =====================================================================
        // 2. 60-240 FPS PORTAL AWAKENING
        // =====================================================================
        private sealed class PortalSpark
        {
            public Ellipse El = null!;
            public double Angle;
            public double Dist;
            public double Speed;
            public double Vx;
            public double Vy;
            public bool Exploded;
        }

        private Task RunMagicPortalAwakeningAsync(string phase2Text)
        {
            var tcs = new TaskCompletionSource<bool>();
            var rng = new Random();

            MagicSparksCanvas.Children.Clear();
            MagicPhasePillTxt.Text = "SEELENFUNKEN SAMMELN SICH...";
            MagicPortalOverlay.Opacity = 1.0;
            MagicPortalOverlay.Visibility = Visibility.Visible;

            double w = Math.Max(1100, ActualWidth > 0 ? ActualWidth : 1540);
            double h = Math.Max(700, ActualHeight > 0 ? ActualHeight : 900);
            double cx = w * 0.5;
            double cy = h * 0.5 - 28.0;

            var goldA = Solid("#FBBF24");
            var goldB = Solid("#FDE68A");
            goldA.Freeze();
            goldB.Freeze();

            var sparks = new List<PortalSpark>(68);
            for (int i = 0; i < 68; i++)
            {
                double ang = rng.NextDouble() * Math.PI * 2.0;
                double dist = 50.0 + rng.NextDouble() * Math.Max(w, h) * 0.40;
                double r = 2.0 + rng.NextDouble() * 3.6;
                var el = new Ellipse { Width = r * 2.0, Height = r * 2.0, Fill = (i % 3 == 0) ? goldB : goldA, Opacity = 0.92 };
                Canvas.SetLeft(el, cx + Math.Cos(ang) * dist - r);
                Canvas.SetTop(el, cy + Math.Sin(ang) * dist - r);
                MagicSparksCanvas.Children.Add(el);

                sparks.Add(new PortalSpark
                {
                    El = el,
                    Angle = ang,
                    Dist = dist,
                    Speed = (3.4 + rng.NextDouble() * 3.2) * (i % 2 == 0 ? 1.0 : -1.0),
                    Vx = Math.Cos(ang) * (420.0 + rng.NextDouble() * 780.0),
                    Vy = Math.Sin(ang) * (420.0 + rng.NextDouble() * 780.0)
                });
            }

            var sw = Stopwatch.StartNew();
            double prevSec = 0;
            bool phase2 = false;

            void FrameHandler(object? s, EventArgs args)
            {
                double sec = sw.Elapsed.TotalSeconds;
                double dt = Math.Clamp(sec - prevSec, 0.001, 0.04);
                prevSec = sec;

                bool supernova = sec >= 0.70;
                if (supernova && !phase2)
                {
                    phase2 = true;
                    MagicPhasePillTxt.Text = phase2Text;
                }

                MagicOrbitRotate.Angle = (sec * 52.0) % 360.0;
                if (!supernova)
                {
                    double p1 = Math.Clamp(sec / 0.70, 0.0, 1.0);
                    MagicCoreScale.ScaleX = 0.90 + 0.16 * p1;
                    MagicCoreScale.ScaleY = 0.90 + 0.16 * p1;
                    MagicStarRotate.Angle = p1 * 90.0;
                    MagicShockwaveRing.Opacity = 0.0;
                }
                else
                {
                    double p2 = Math.Clamp((sec - 0.70) / 0.72, 0.0, 1.0);
                    MagicCoreScale.ScaleX = 1.12 - 0.08 * p2;
                    MagicCoreScale.ScaleY = 1.12 - 0.08 * p2;
                    MagicStarRotate.Angle = 90.0 + 90.0 * Math.Sin(p2 * Math.PI * 0.5);
                    MagicShockwaveScale.ScaleX = 1.0 + p2 * 2.5;
                    MagicShockwaveScale.ScaleY = 1.0 + p2 * 2.5;
                    MagicShockwaveRing.Opacity = Math.Max(0.0, (1.0 - p2) * 0.88);
                }

                if (sec >= 1.18)
                    MagicPortalOverlay.Opacity = Math.Clamp(1.0 - ((sec - 1.18) / 0.34), 0.0, 1.0);

                foreach (var sp in sparks)
                {
                    double r = sp.El.Width * 0.5;
                    if (!supernova)
                    {
                        sp.Angle += sp.Speed * dt;
                        sp.Dist = Math.Max(5.0, sp.Dist * Math.Pow(0.025, dt));
                        Canvas.SetLeft(sp.El, cx + Math.Cos(sp.Angle) * sp.Dist - r);
                        Canvas.SetTop(sp.El, cy + Math.Sin(sp.Angle) * sp.Dist - r);
                    }
                    else
                    {
                        if (!sp.Exploded) { sp.Exploded = true; sp.Dist = 0; }
                        double curX = Canvas.GetLeft(sp.El) + r + sp.Vx * dt;
                        double curY = Canvas.GetTop(sp.El) + r + sp.Vy * dt;
                        sp.Vx *= Math.Pow(0.08, dt);
                        sp.Vy *= Math.Pow(0.08, dt);
                        Canvas.SetLeft(sp.El, curX - r);
                        Canvas.SetTop(sp.El, curY - r);
                    }
                }

                if (sec >= 1.52)
                {
                    CompositionTarget.Rendering -= FrameHandler;
                    MagicSparksCanvas.Children.Clear();
                    MagicPortalOverlay.Visibility = Visibility.Collapsed;
                    tcs.TrySetResult(true);
                }
            }

            CompositionTarget.Rendering += FrameHandler;
            return tcs.Task;
        }

        // =====================================================================
        // 3. THEME & FULLSCREEN
        // =====================================================================
        private void ApplyTheme(bool darkMode)
        {
            _settings.DarkMode = darkMode;
            if (darkMode)
            {
                Resources["TaskbarGlassBrush"] = Solid("#DF0A0C12");
                Resources["FlyoutGlassBrush"] = Solid("#EE0D1018");
                Resources["WindowGlassBrush"] = Solid("#F20E111A");
                Resources["WindowHeaderGlassBrush"] = Solid("#2EC5A059");
                Resources["CardGlassBrush"] = Solid("#88161B28");
                Resources["CardHoverGlassBrush"] = Solid("#BF1F273A");
                Resources["InputGlassBrush"] = Solid("#BF07090E");
                Resources["GoldBorderBrush"] = Solid("#80C5A059");
                Resources["SubtleBorderBrush"] = Solid("#3AC5A059");
                Resources["TextPrimaryBrush"] = Solid("#F9FAFB");
                Resources["TextSecondaryBrush"] = Solid("#B8C0CC");
                Resources["ChampagneGoldBrush"] = Solid("#FCE7B2");
                if (ThemeModeSettingBtn != null) ThemeModeSettingBtn.Content = "OBSIDIAN DUNKEL";
            }
            else
            {
                Resources["TaskbarGlassBrush"] = Solid("#ECFAF8F3");
                Resources["FlyoutGlassBrush"] = Solid("#F4FFFFFF");
                Resources["WindowGlassBrush"] = Solid("#F5FFFFFF");
                Resources["WindowHeaderGlassBrush"] = Solid("#30C5A059");
                Resources["CardGlassBrush"] = Solid("#D9F3EFE6");
                Resources["CardHoverGlassBrush"] = Solid("#ECE5D5");
                Resources["InputGlassBrush"] = Solid("#FFFFFFFF");
                Resources["GoldBorderBrush"] = Solid("#A6C5A059");
                Resources["SubtleBorderBrush"] = Solid("#55C5A059");
                Resources["TextPrimaryBrush"] = Solid("#111827");
                Resources["TextSecondaryBrush"] = Solid("#4B5563");
                Resources["ChampagneGoldBrush"] = Solid("#8E6924");
                if (ThemeModeSettingBtn != null) ThemeModeSettingBtn.Content = "CHAMPAGNER HELL";
            }
            _secondaryWallWindow?.ApplyTheme(darkMode);
            UpdateDesktopSelectionVisuals();
            ApplyTaskbarLayoutAndStyle();
        }

        private void ToggleTheme_Click(object sender, RoutedEventArgs e)
        {
            ApplyTheme(!_settings.DarkMode);
            OsVaultAndStorage.SaveSettings(_settings);
        }

        private void SetBorderlessFullscreen(bool enable)
        {
            _isFullscreenBorderless = enable;
            if (enable)
            {
                WindowState = WindowState.Normal;
                WindowStyle = WindowStyle.None;
                ResizeMode = ResizeMode.NoResize;
                WindowState = WindowState.Maximized;
            }
            else
            {
                WindowStyle = WindowStyle.SingleBorderWindow;
                ResizeMode = ResizeMode.CanResize;
                WindowState = WindowState.Maximized;
            }
        }

        private void ToggleFullscreen_Click(object sender, RoutedEventArgs e) => SetBorderlessFullscreen(!_isFullscreenBorderless);

        // =====================================================================
        // 4. INTERACTIVE DESKTOP ICONS & DRAG-AND-DROP
        // =====================================================================
        private string ResolveCustomTitle(string id, string defaultTitle) =>
            _settings.CustomDisplayNames.TryGetValue(id, out string? custom) && !string.IsNullOrWhiteSpace(custom)
                ? custom
                : defaultTitle;

        private List<DesktopItemModel> BuildFullAppAndSystemCatalog()
        {
            var list = new List<DesktopItemModel>(20);
            list.Add(new DesktopItemModel
            {
                Id = "sys:gewerbe",
                Title = ResolveCustomTitle("sys:gewerbe", "Monitor Gewerbebereich"),
                Kind = DesktopEntryKind.SystemTool,
                AppOrToolKey = "GewerbeMonitorWin",
                IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon("gewerbe")
            });

            foreach (var app in OsHardwareAndAudioEngine.DiscoverCollectionApps())
            {
                string appId = $"app:{app.Key}";
                list.Add(new DesktopItemModel
                {
                    Id = appId,
                    Title = ResolveCustomTitle(appId, app.Title),
                    Kind = DesktopEntryKind.CollectionApp,
                    AppOrToolKey = app.Key,
                    IconImage = app.IconImage
                });
            }

            int recycleCount = 0;
            try { recycleCount = Directory.GetFileSystemEntries(OsVaultAndStorage.OsRecycleBinDir).Length; } catch { }

            list.Add(new DesktopItemModel { Id = "sys:meinedaten", Title = ResolveCustomTitle("sys:meinedaten", "Meine Daten"), Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "MeineDatenDir", IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon("meinedaten") });
            list.Add(new DesktopItemModel { Id = "sys:calculator", Title = ResolveCustomTitle("sys:calculator", "Taschenrechner"), Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "CalculatorWin", IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon("calculator") });
            list.Add(new DesktopItemModel { Id = "sys:snip", Title = ResolveCustomTitle("sys:snip", "Ausschneiden"), Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "SnipWin", IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon("snip") });
            list.Add(new DesktopItemModel { Id = "sys:notes", Title = ResolveCustomTitle("sys:notes", "Notizen"), Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "NotesWin", IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon("notes") });
            list.Add(new DesktopItemModel { Id = "sys:explorer", Title = ResolveCustomTitle("sys:explorer", "Dateien"), Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "ExplorerWin", IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon("explorer") });
            list.Add(new DesktopItemModel { Id = "sys:leitstand", Title = ResolveCustomTitle("sys:leitstand", "Leitstand"), Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "LeitstandWin", IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon("leitstand") });
            list.Add(new DesktopItemModel { Id = "sys:settings", Title = ResolveCustomTitle("sys:settings", "Einstellungen"), Kind = DesktopEntryKind.SystemTool, AppOrToolKey = "SettingsWin", IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon("settings") });
            list.Add(new DesktopItemModel
            {
                Id = "sys:recycle",
                Title = ResolveCustomTitle("sys:recycle", "Papierkorb"),
                Kind = DesktopEntryKind.RecycleBin,
                AppOrToolKey = "RecycleBin",
                BadgeText = recycleCount > 0 ? recycleCount.ToString() : "",
                IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon(recycleCount > 0 ? "recycle_full" : "recycle_empty")
            });

            return list;
        }

        private bool IsAppPinnedToTaskbar(string id) =>
            _settings.PinnedTaskbarAppIds.Any(x => string.Equals(x, id, StringComparison.OrdinalIgnoreCase));

        private void TogglePinAppToTaskbar(string id, string displayTitle)
        {
            if (IsAppPinnedToTaskbar(id))
            {
                _settings.PinnedTaskbarAppIds.RemoveAll(x => string.Equals(x, id, StringComparison.OrdinalIgnoreCase));
                OsVaultAndStorage.SaveSettings(_settings);
                RefreshTaskbarStrip();
                ShowToast($"✓ '{displayTitle}' von Taskleiste gelöst");
            }
            else
            {
                _settings.PinnedTaskbarAppIds.Add(id);
                OsVaultAndStorage.SaveSettings(_settings);
                RefreshTaskbarStrip();
                ShowToast($"✓ '{displayTitle}' an Taskleiste angeheftet");
            }
        }

        private bool IsShortcutHiddenOnDesktop(string id) =>
            !_settings.VisibleDesktopShortcutIds.Any(x => string.Equals(x, id, StringComparison.OrdinalIgnoreCase));

        private void RemoveShortcutFromDesktop(string id, string displayTitle)
        {
            if (!IsShortcutHiddenOnDesktop(id))
            {
                _settings.VisibleDesktopShortcutIds.RemoveAll(x => string.Equals(x, id, StringComparison.OrdinalIgnoreCase));
                _selectedDesktopIds.Remove(id);
                OsVaultAndStorage.SaveSettings(_settings);
                RebuildDesktopIcons();
                RefreshStartMenuApps(StartSearchBox.Text);
                ShowToast($"✓ '{displayTitle}' vom Desktop entfernt (weiterhin über Suche erreichbar)");
            }
        }

        private void RestoreShortcutToDesktop(string id, string displayTitle)
        {
            if (!_settings.VisibleDesktopShortcutIds.Any(x => string.Equals(x, id, StringComparison.OrdinalIgnoreCase)))
                _settings.VisibleDesktopShortcutIds.Add(id);
            OsVaultAndStorage.SaveSettings(_settings);
            RebuildDesktopIcons();
            RefreshStartMenuApps(StartSearchBox.Text);
            ShowToast($"✓ '{displayTitle}' auf dem Desktop abgelegt");
        }

        private void RebuildDesktopIcons()
        {
            _desktopItems.Clear();
            _desktopVisuals.Clear();
            DesktopIconsCanvas.Children.Clear();

            var occupied = new HashSet<(int Col, int Row)>();

            void PlaceItem(DesktopItemModel item, int preferredCol, int preferredRow)
            {
                if (_settings.DesktopPositions.TryGetValue(item.Id, out var saved) && !occupied.Contains((saved.Col, saved.Row)))
                {
                    item.Col = saved.Col;
                    item.Row = saved.Row;
                }
                else
                {
                    (int c, int r) = FindNearestFreeCell(occupied, preferredCol, preferredRow);
                    item.Col = c;
                    item.Row = r;
                }
                occupied.Add((item.Col, item.Row));
                _desktopItems.Add(item);
            }

            // Visible App & System Shortcuts (Columns 0..2, 7 per column)
            int idx = 0;
            foreach (var catalogItem in BuildFullAppAndSystemCatalog())
            {
                if (!IsShortcutHiddenOnDesktop(catalogItem.Id))
                {
                    int col = idx / 7;
                    int row = idx % 7;
                    PlaceItem(catalogItem, col, row);
                    idx++;
                }
            }

            // User Folders & Files on Desktop
            int uCol = Math.Max(2, (idx + 6) / 7);
            int uRow = 0;
            try
            {
                foreach (string dir in Directory.GetDirectories(OsVaultAndStorage.OsDesktopDir).OrderBy(Path.GetFileName))
                {
                    string name = Path.GetFileName(dir);
                    PlaceItem(new DesktopItemModel
                    {
                        Id = $"fs:{name}",
                        Title = name,
                        Kind = DesktopEntryKind.UserFolder,
                        FullFilePath = dir,
                        IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon("folder")
                    }, uCol, uRow++);
                    if (uRow >= 7) { uRow = 0; uCol++; }
                }

                foreach (string file in Directory.GetFiles(OsVaultAndStorage.OsDesktopDir).OrderBy(Path.GetFileName))
                {
                    string name = Path.GetFileName(file);
                    PlaceItem(new DesktopItemModel
                    {
                        Id = $"fs:{name}",
                        Title = name,
                        Kind = DesktopEntryKind.UserFile,
                        FullFilePath = file,
                        IconImage = ResolveFileOrThumbIcon(file, useThumb: true)
                    }, uCol, uRow++);
                    if (uRow >= 7) { uRow = 0; uCol++; }
                }
            }
            catch { }

            foreach (var item in _desktopItems)
            {
                var visual = CreateDesktopIconVisual(item);
                _desktopVisuals[item.Id] = visual;
                Canvas.SetLeft(visual, GridPadX + item.Col * GridCellW);
                Canvas.SetTop(visual, GridPadY + item.Row * GridCellH);
                DesktopIconsCanvas.Children.Add(visual);
            }

            UpdateDesktopSelectionVisuals();
        }

        private BitmapSource? ResolveFileOrThumbIcon(string filePath, bool useThumb)
        {
            string ext = Path.GetExtension(filePath).ToLowerInvariant();
            if (useThumb && ext is ".png" or ".jpg" or ".jpeg" or ".webp" or ".bmp")
            {
                var thumb = OsHardwareAndAudioEngine.LoadImageThumbnailCached(filePath);
                if (thumb != null) return thumb;
            }

            var apps = OsHardwareAndAudioEngine.DiscoverCollectionApps().ToDictionary(a => a.Key, a => a.IconImage, StringComparer.OrdinalIgnoreCase);
            if (ext is ".html" or ".htm" or ".url" && apps.TryGetValue("browser", out var brIco)) return brIco;
            if (ext == ".pdf" && apps.TryGetValue("pdf", out var pdfIco)) return pdfIco;
            if (ext is ".mp3" or ".wav" or ".flac" or ".m4a" or ".mp4" or ".mkv" or ".mov" && apps.TryGetValue("player", out var plIco)) return plIco;
            if (ext is ".kdbx" or ".keyx" && apps.TryGetValue("tresor", out var trIco)) return trIco;
            if (ext is ".png" or ".jpg" or ".jpeg" or ".webp" or ".bmp") return OsHardwareAndAudioEngine.GetSystemBadgeIcon("file_image");
            return OsHardwareAndAudioEngine.GetSystemBadgeIcon("file_text");
        }

        private static (int Col, int Row) FindNearestFreeCell(HashSet<(int Col, int Row)> occupied, int targetCol, int targetRow)
        {
            int c = Math.Max(0, targetCol);
            int r = Math.Max(0, targetRow);
            if (!occupied.Contains((c, r))) return (c, r);

            for (int radius = 1; radius < 24; radius++)
            {
                for (int dc = -radius; dc <= radius; dc++)
                {
                    for (int dr = -radius; dr <= radius; dr++)
                    {
                        int nc = c + dc;
                        int nr = r + dr;
                        if (nc >= 0 && nr >= 0 && nr < 7 && !occupied.Contains((nc, nr)))
                            return (nc, nr);
                    }
                }
            }
            return (c + 1, 0);
        }

        private Border CreateDesktopIconVisual(DesktopItemModel item)
        {
            var scale = new ScaleTransform(1.0, 1.0);
            var card = new Border
            {
                Width = 94,
                Height = 96,
                CornerRadius = new CornerRadius(12),
                Background = Brushes.Transparent,
                BorderBrush = Brushes.Transparent,
                BorderThickness = new Thickness(1),
                Padding = new Thickness(6, 7, 6, 5),
                Cursor = Cursors.Hand,
                RenderTransformOrigin = new System.Windows.Point(0.5, 0.5),
                RenderTransform = scale,
                Tag = item
            };

            var grid = new Grid();
            grid.RowDefinitions.Add(new RowDefinition { Height = new GridLength(50) });
            grid.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });

            var imgHost = new Grid { Width = 48, Height = 48, HorizontalAlignment = HorizontalAlignment.Center };
            var img = new Image { Source = item.IconImage, Width = 46, Height = 46, Stretch = Stretch.Uniform };
            RenderOptions.SetBitmapScalingMode(img, BitmapScalingMode.HighQuality);
            imgHost.Children.Add(img);

            if (!string.IsNullOrEmpty(item.BadgeText))
            {
                var badge = new Border
                {
                    Background = Solid("#D91F1708"),
                    BorderBrush = Solid("#F2C5A059"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(8),
                    Padding = new Thickness(5, 0, 5, 0),
                    HorizontalAlignment = HorizontalAlignment.Right,
                    VerticalAlignment = VerticalAlignment.Top
                };
                badge.Child = new TextBlock { Text = item.BadgeText, Foreground = Solid("#FCE7B2"), FontSize = 9.5, FontWeight = FontWeights.Bold };
                imgHost.Children.Add(badge);
            }

            Grid.SetRow(imgHost, 0);
            grid.Children.Add(imgHost);

            var lbl = new TextBlock
            {
                Text = item.Title,
                Foreground = Solid("#FFFFFF"),
                FontSize = 11.5,
                FontWeight = FontWeights.SemiBold,
                TextAlignment = TextAlignment.Center,
                TextWrapping = TextWrapping.Wrap,
                MaxHeight = 34,
                TextTrimming = TextTrimming.CharacterEllipsis,
                HorizontalAlignment = HorizontalAlignment.Center,
                Margin = new Thickness(0, 3, 0, 0)
            };
            Grid.SetRow(lbl, 1);
            grid.Children.Add(lbl);

            card.Child = grid;

            card.MouseEnter += (_, _) =>
            {
                AnimateScale(scale, 1.055, 110);
                if (!_selectedDesktopIds.Contains(item.Id))
                {
                    card.Background = Solid("#22C5A059");
                    card.BorderBrush = Solid("#48C5A059");
                }
            };

            card.MouseLeave += (_, _) =>
            {
                AnimateScale(scale, 1.0, 120);
                UpdateDesktopSelectionVisuals();
            };

            card.MouseLeftButtonDown += DesktopIcon_MouseLeftButtonDown;
            card.MouseRightButtonUp += DesktopIcon_MouseRightButtonUp;

            return card;
        }

        private void UpdateDesktopSelectionVisuals()
        {
            foreach (var kv in _desktopVisuals)
            {
                if (string.Equals(kv.Key, _hoveredDesktopDropTargetId, StringComparison.OrdinalIgnoreCase))
                {
                    kv.Value.Background = Solid("#52C5A059");
                    kv.Value.BorderBrush = Solid("#F2FCE7B2");
                    continue;
                }
                bool sel = _selectedDesktopIds.Contains(kv.Key);
                kv.Value.Background = sel ? Solid("#3EC5A059") : Brushes.Transparent;
                kv.Value.BorderBrush = sel ? Solid("#D9C5A059") : Brushes.Transparent;
            }
        }

        private void UpdateDesktopDropTargetHighlight(string? targetId)
        {
            if (string.Equals(_hoveredDesktopDropTargetId, targetId, StringComparison.OrdinalIgnoreCase))
                return;
            _hoveredDesktopDropTargetId = targetId;
            UpdateDesktopSelectionVisuals();
        }

        private void DesktopIcon_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            e.Handled = true;
            CloseAllFlyoutsAndMenus();
            CommitInlineRenameIfNeeded();

            if (sender is not Border card || card.Tag is not DesktopItemModel item) return;

            DateTime now = DateTime.UtcNow;
            bool isDoubleClick = (_lastClickedIconId == item.Id) && (now - _lastIconClickUtc).TotalMilliseconds < 380;
            _lastClickedIconId = item.Id;
            _lastIconClickUtc = now;

            if ((Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                if (!_selectedDesktopIds.Remove(item.Id)) _selectedDesktopIds.Add(item.Id);
            }
            else if (!_selectedDesktopIds.Contains(item.Id))
            {
                _selectedDesktopIds.Clear();
                _selectedDesktopIds.Add(item.Id);
            }
            UpdateDesktopSelectionVisuals();

            if (isDoubleClick)
            {
                _lastClickedIconId = "";
                ActivateDesktopItem(item);
                return;
            }

            _draggingIcon = item;
            _draggingVisual = card;
            _iconDragStartMouse = e.GetPosition(DesktopWorkAreaGrid);
            _iconDragStartLeft = Canvas.GetLeft(card);
            _iconDragStartTop = Canvas.GetTop(card);
            _iconDragGroupStartOffsets.Clear();
            foreach (string selId in _selectedDesktopIds)
            {
                if (_desktopVisuals.TryGetValue(selId, out var vis))
                    _iconDragGroupStartOffsets[selId] = (Canvas.GetLeft(vis), Canvas.GetTop(vis));
            }
            if (!_iconDragGroupStartOffsets.ContainsKey(item.Id))
                _iconDragGroupStartOffsets[item.Id] = (_iconDragStartLeft, _iconDragStartTop);

            _isIconDragging = false;
            DesktopWorkAreaGrid.CaptureMouse();
        }

        private void DesktopIcon_MouseRightButtonUp(object sender, MouseButtonEventArgs e)
        {
            e.Handled = true;
            CloseAllFlyoutsAndMenus(keepContext: true);
            CommitInlineRenameIfNeeded();

            if (sender is not Border card || card.Tag is not DesktopItemModel item) return;
            if (!_selectedDesktopIds.Contains(item.Id))
            {
                _selectedDesktopIds.Clear();
                _selectedDesktopIds.Add(item.Id);
                UpdateDesktopSelectionVisuals();
            }

            ShowIconContextMenu(item, e.GetPosition(DesktopWorkAreaGrid));
        }

        private void DesktopSurface_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (e.OriginalSource != DesktopWorkAreaGrid && e.OriginalSource != DesktopIconsCanvas)
                return;

            CloseAllFlyoutsAndMenus();
            CommitInlineRenameIfNeeded();

            _selectedDesktopIds.Clear();
            UpdateDesktopSelectionVisuals();

            _marqueeOrigin = e.GetPosition(DesktopWorkAreaGrid);
            _isMarqueeSelecting = true;
            Canvas.SetLeft(SelectionMarqueeBorder, _marqueeOrigin.X);
            Canvas.SetTop(SelectionMarqueeBorder, _marqueeOrigin.Y);
            SelectionMarqueeBorder.Width = 0;
            SelectionMarqueeBorder.Height = 0;
            SelectionMarqueeBorder.Visibility = Visibility.Visible;
            DesktopWorkAreaGrid.CaptureMouse();
            _lastWallpaperRipplePos = _marqueeOrigin;
            _lastWallpaperRippleUtc = DateTime.UtcNow;
            DesktopSparksLayer.SpawnRipple(_marqueeOrigin.X, _marqueeOrigin.Y, isClick: true);
        }

        private void DesktopSurface_MouseMove(object sender, MouseEventArgs e)
        {
            var pos = e.GetPosition(DesktopWorkAreaGrid);

            if (e.LeftButton == MouseButtonState.Released)
            {
                double rdx = pos.X - _lastWallpaperRipplePos.X;
                double rdy = pos.Y - _lastWallpaperRipplePos.Y;
                if ((rdx * rdx + rdy * rdy) >= 256.0 && (DateTime.UtcNow - _lastWallpaperRippleUtc).TotalMilliseconds >= 68)
                {
                    _lastWallpaperRipplePos = pos;
                    _lastWallpaperRippleUtc = DateTime.UtcNow;
                    DesktopSparksLayer.SpawnRipple(pos.X, pos.Y, isClick: false);
                }
            }

            // Resizing Floating OS Window
            if (_resizingWinBorder != null && e.LeftButton == MouseButtonState.Pressed)
            {
                double nw = Math.Max(_resizingWinBorder.MinWidth, _winResizeStartW + (pos.X - _winResizeStartMouse.X));
                double nh = Math.Max(_resizingWinBorder.MinHeight, _winResizeStartH + (pos.Y - _winResizeStartMouse.Y));
                _resizingWinBorder.Width = Math.Min(nw, Math.Max(480, DesktopWorkAreaGrid.ActualWidth - Canvas.GetLeft(_resizingWinBorder) - 4));
                _resizingWinBorder.Height = Math.Min(nh, Math.Max(340, DesktopWorkAreaGrid.ActualHeight - Canvas.GetTop(_resizingWinBorder) - 4));
                return;
            }

            // Dragging Floating OS Window + Aero-Snap Preview
            if (_draggingWinBorder != null && e.LeftButton == MouseButtonState.Pressed)
            {
                double nx = Math.Clamp(_winDragStartLeft + (pos.X - _winDragStartMouse.X), 0, Math.Max(40, DesktopWorkAreaGrid.ActualWidth - 140));
                double ny = Math.Clamp(_winDragStartTop + (pos.Y - _winDragStartMouse.Y), 0, Math.Max(40, DesktopWorkAreaGrid.ActualHeight - 50));
                Canvas.SetLeft(_draggingWinBorder, nx);
                Canvas.SetTop(_draggingWinBorder, ny);

                double fullW = Math.Max(800, DesktopWorkAreaGrid.ActualWidth);
                double fullH = Math.Max(500, DesktopWorkAreaGrid.ActualHeight);
                double halfW = (fullW - 18) * 0.5;

                string? snapZone = null;
                if (pos.Y <= 12) snapZone = "Maximize";
                else if (pos.X <= 14) snapZone = "Left";
                else if (pos.X >= fullW - 14) snapZone = "Right";

                _activeAeroSnapZone = snapZone;
                if (snapZone != null)
                {
                    WindowSnapPreviewBorder.Visibility = Visibility.Visible;
                    if (snapZone == "Maximize")
                    {
                        Canvas.SetLeft(WindowSnapPreviewBorder, 6);
                        Canvas.SetTop(WindowSnapPreviewBorder, 6);
                        WindowSnapPreviewBorder.Width = fullW - 12;
                        WindowSnapPreviewBorder.Height = fullH - 12;
                    }
                    else
                    {
                        Canvas.SetLeft(WindowSnapPreviewBorder, snapZone == "Left" ? 6 : (6 + halfW + 6));
                        Canvas.SetTop(WindowSnapPreviewBorder, 6);
                        WindowSnapPreviewBorder.Width = halfW;
                        WindowSnapPreviewBorder.Height = fullH - 12;
                    }
                }
                else
                {
                    WindowSnapPreviewBorder.Visibility = Visibility.Collapsed;
                }
                return;
            }

            // Dragging Desktop Icon(s)
            if (_draggingIcon != null && _draggingVisual != null && e.LeftButton == MouseButtonState.Pressed)
            {
                double dx = pos.X - _iconDragStartMouse.X;
                double dy = pos.Y - _iconDragStartMouse.Y;
                if (!_isIconDragging && (Math.Abs(dx) > 5 || Math.Abs(dy) > 5))
                {
                    _isIconDragging = true;
                    foreach (string id in _iconDragGroupStartOffsets.Keys)
                    {
                        if (_desktopVisuals.TryGetValue(id, out var v))
                            Panel.SetZIndex(v, 50);
                    }
                }

                if (_isIconDragging)
                {
                    double maxW = Math.Max(10, DesktopWorkAreaGrid.ActualWidth - 98);
                    double maxH = Math.Max(10, DesktopWorkAreaGrid.ActualHeight - 100);
                    foreach (var kv in _iconDragGroupStartOffsets)
                    {
                        if (_desktopVisuals.TryGetValue(kv.Key, out var v))
                        {
                            Canvas.SetLeft(v, Math.Clamp(kv.Value.Left + dx, 4, maxW));
                            Canvas.SetTop(v, Math.Clamp(kv.Value.Top + dy, 4, maxH));
                        }
                    }

                    double dropCenterX = Canvas.GetLeft(_draggingVisual) + 47;
                    double dropCenterY = Canvas.GetTop(_draggingVisual) + 48;
                    var hoverTarget = _desktopItems.FirstOrDefault(other =>
                        !_iconDragGroupStartOffsets.ContainsKey(other.Id) &&
                        _desktopVisuals.TryGetValue(other.Id, out var ov) &&
                        new Rect(Canvas.GetLeft(ov), Canvas.GetTop(ov), ov.Width, ov.Height).Contains(new System.Windows.Point(dropCenterX, dropCenterY)));

                    UpdateDesktopDropTargetHighlight(hoverTarget?.Id);
                }
                return;
            }

            // Rubber-band Marquee
            if (_isMarqueeSelecting && e.LeftButton == MouseButtonState.Pressed)
            {
                double x = Math.Min(pos.X, _marqueeOrigin.X);
                double y = Math.Min(pos.Y, _marqueeOrigin.Y);
                double w = Math.Abs(pos.X - _marqueeOrigin.X);
                double h = Math.Abs(pos.Y - _marqueeOrigin.Y);
                Canvas.SetLeft(SelectionMarqueeBorder, x);
                Canvas.SetTop(SelectionMarqueeBorder, y);
                SelectionMarqueeBorder.Width = w;
                SelectionMarqueeBorder.Height = h;

                var rect = new Rect(x, y, w, h);
                _selectedDesktopIds.Clear();
                foreach (var item in _desktopItems)
                {
                    if (_desktopVisuals.TryGetValue(item.Id, out var vis))
                    {
                        var itemRect = new Rect(Canvas.GetLeft(vis), Canvas.GetTop(vis), vis.Width, vis.Height);
                        if (rect.IntersectsWith(itemRect)) _selectedDesktopIds.Add(item.Id);
                    }
                }
                UpdateDesktopSelectionVisuals();
            }
        }

        private void DesktopSurface_MouseLeftButtonUp(object sender, MouseButtonEventArgs e)
        {
            if (_resizingWinBorder != null)
            {
                _resizingWinBorder = null;
                DesktopWorkAreaGrid.ReleaseMouseCapture();
                return;
            }

            if (_draggingWinBorder != null)
            {
                var win = _draggingWinBorder;
                string? key = _draggingWinKey;
                string? snap = _activeAeroSnapZone;
                _draggingWinBorder = null;
                _draggingWinKey = null;
                _activeAeroSnapZone = null;
                WindowSnapPreviewBorder.Visibility = Visibility.Collapsed;
                DesktopWorkAreaGrid.ReleaseMouseCapture();

                if (!string.IsNullOrEmpty(snap) && !string.IsNullOrEmpty(key))
                {
                    double fullW = Math.Max(800, DesktopWorkAreaGrid.ActualWidth);
                    double fullH = Math.Max(500, DesktopWorkAreaGrid.ActualHeight);
                    double halfW = (fullW - 18) * 0.5;
                    if (!_preMaximizeBounds.ContainsKey(key))
                        _preMaximizeBounds[key] = new Rect(_winDragStartLeft, _winDragStartTop, win.Width, win.Height);

                    if (snap == "Maximize")
                    {
                        Canvas.SetLeft(win, 6);
                        Canvas.SetTop(win, 6);
                        win.Width = fullW - 12;
                        win.Height = fullH - 12;
                    }
                    else
                    {
                        Canvas.SetLeft(win, snap == "Left" ? 6 : (6 + halfW + 6));
                        Canvas.SetTop(win, 6);
                        win.Width = halfW;
                        win.Height = fullH - 12;
                    }
                }
                return;
            }

            if (_draggingIcon != null && _draggingVisual != null)
            {
                var movedItem = _draggingIcon;
                var movedVis = _draggingVisual;
                bool didDrag = _isIconDragging;
                var groupIds = _iconDragGroupStartOffsets.Keys.ToList();
                _draggingIcon = null;
                _draggingVisual = null;
                _isIconDragging = false;
                _iconDragGroupStartOffsets.Clear();
                UpdateDesktopDropTargetHighlight(null);

                foreach (string id in groupIds)
                {
                    if (_desktopVisuals.TryGetValue(id, out var v))
                        Panel.SetZIndex(v, 0);
                }
                DesktopWorkAreaGrid.ReleaseMouseCapture();

                if (didDrag)
                {
                    var draggedItems = _desktopItems.Where(x => groupIds.Contains(x.Id, StringComparer.OrdinalIgnoreCase)).ToList();
                    if (draggedItems.Count == 0) draggedItems.Add(movedItem);
                    var deletablePaths = draggedItems.Where(x => x.IsDeletable && !string.IsNullOrEmpty(x.FullFilePath)).Select(x => x.FullFilePath!).ToList();

                    // 1. Check if dropped directly onto the Taskbar (pins apps or opens files in hovered Taskbar app!)
                    var tbPos = e.GetPosition(TaskbarBorder);
                    if (tbPos.X >= 0 && tbPos.Y >= 0 && tbPos.X <= TaskbarBorder.ActualWidth && tbPos.Y <= TaskbarBorder.ActualHeight)
                    {
                        bool pinnedAny = false;
                        foreach (var appItem in draggedItems.Where(x => x.Kind is DesktopEntryKind.CollectionApp or DesktopEntryKind.SystemTool))
                        {
                            if (!IsAppPinnedToTaskbar(appItem.Id))
                            {
                                TogglePinAppToTaskbar(appItem.Id, appItem.Title);
                                pinnedAny = true;
                            }
                        }
                        if (pinnedAny)
                        {
                            RebuildDesktopIcons();
                            return;
                        }
                    }

                    double dropCenterX = Canvas.GetLeft(movedVis) + 47;
                    double dropCenterY = Canvas.GetTop(movedVis) + 48;

                    // 2. If dropped directly inside open Explorer window -> move/copy there!
                    if (ExplorerWin.Visibility == Visibility.Visible && deletablePaths.Count > 0)
                    {
                        var expRect = new Rect(Canvas.GetLeft(ExplorerWin), Canvas.GetTop(ExplorerWin), ExplorerWin.ActualWidth, ExplorerWin.ActualHeight);
                        if (expRect.Contains(new System.Windows.Point(dropCenterX, dropCenterY)))
                        {
                            TransferPathsIntoDirectory(deletablePaths, _currentExplorerDir, moveInsteadOfCopy: (Keyboard.Modifiers & ModifierKeys.Control) == 0);
                            return;
                        }
                    }

                    // 3. If dropped inside open ViewerWin or NotesWin -> open file there!
                    if (ViewerWin.Visibility == Visibility.Visible && deletablePaths.Count > 0)
                    {
                        var viewRect = new Rect(Canvas.GetLeft(ViewerWin), Canvas.GetTop(ViewerWin), ViewerWin.ActualWidth, ViewerWin.ActualHeight);
                        if (viewRect.Contains(new System.Windows.Point(dropCenterX, dropCenterY)))
                        {
                            OpenFileWithConfiguredDefaultProgram(deletablePaths[0]);
                            RebuildDesktopIcons();
                            return;
                        }
                    }

                    // 4. Check if dropped onto another Desktop Icon (Folder, Meine Daten, App, or RecycleBin)
                    var dropTarget = _desktopItems.FirstOrDefault(other =>
                        !groupIds.Contains(other.Id, StringComparer.OrdinalIgnoreCase) &&
                        _desktopVisuals.TryGetValue(other.Id, out var ov) &&
                        new Rect(Canvas.GetLeft(ov), Canvas.GetTop(ov), ov.Width, ov.Height).Contains(new System.Windows.Point(dropCenterX, dropCenterY)));

                    if (dropTarget != null)
                    {
                        if (dropTarget.Kind == DesktopEntryKind.RecycleBin)
                        {
                            foreach (var di in draggedItems)
                            {
                                if (di.IsDeletable && !string.IsNullOrEmpty(di.FullFilePath))
                                    MovePathToRecycleBin(di.FullFilePath);
                                else if (di.IsShortcutRemovable && di.Kind != DesktopEntryKind.RecycleBin)
                                    RemoveShortcutFromDesktop(di.Id, di.Title);
                            }
                            return;
                        }

                        if (deletablePaths.Count > 0)
                        {
                            if (dropTarget.Kind == DesktopEntryKind.UserFolder && !string.IsNullOrEmpty(dropTarget.FullFilePath))
                            {
                                TransferPathsIntoDirectory(deletablePaths, dropTarget.FullFilePath, moveInsteadOfCopy: true);
                                return;
                            }
                            if (dropTarget.Kind == DesktopEntryKind.SystemTool && dropTarget.AppOrToolKey == "MeineDatenDir")
                            {
                                OsVaultAndStorage.EnsureMeineDatenStructure();
                                TransferPathsIntoDirectory(deletablePaths, OsVaultAndStorage.EingangTrichterDir, moveInsteadOfCopy: true);
                                return;
                            }
                            if (dropTarget.Kind == DesktopEntryKind.CollectionApp && !string.IsNullOrEmpty(dropTarget.AppOrToolKey))
                            {
                                OsHardwareAndAudioEngine.LaunchOrFocusCollectionApp(dropTarget.AppOrToolKey, deletablePaths[0]);
                                RebuildDesktopIcons();
                                return;
                            }
                            if (dropTarget.Kind == DesktopEntryKind.SystemTool && dropTarget.AppOrToolKey == "ExplorerWin" && Directory.Exists(deletablePaths[0]))
                            {
                                NavigateExplorer(deletablePaths[0], recordHistory: true);
                                OpenInternalWindowAnimated("ExplorerWin");
                                RebuildDesktopIcons();
                                return;
                            }
                        }
                    }

                    // 5. Snap all dragged desktop icons to nearest free grid cells
                    var occupied = _desktopItems
                        .Where(x => !groupIds.Contains(x.Id, StringComparer.OrdinalIgnoreCase))
                        .Select(x => (x.Col, x.Row))
                        .ToHashSet();

                    int maxRow = Math.Max(1, (int)((DesktopWorkAreaGrid.ActualHeight - 40) / GridCellH) - 1);
                    foreach (var di in draggedItems)
                    {
                        if (!_desktopVisuals.TryGetValue(di.Id, out var vis)) continue;
                        int tCol = Math.Max(0, (int)Math.Round((Canvas.GetLeft(vis) - GridPadX) / GridCellW));
                        int tRow = Math.Clamp((int)Math.Round((Canvas.GetTop(vis) - GridPadY) / GridCellH), 0, maxRow);
                        var (finalCol, finalRow) = FindNearestFreeCell(occupied, tCol, tRow);
                        occupied.Add((finalCol, finalRow));
                        di.Col = finalCol;
                        di.Row = finalRow;
                        _settings.DesktopPositions[di.Id] = new DesktopGridPos { Col = finalCol, Row = finalRow };
                        AnimateCanvasPosition(vis, GridPadX + finalCol * GridCellW, GridPadY + finalRow * GridCellH, 155);
                    }
                    OsVaultAndStorage.SaveSettings(_settings);
                }
                return;
            }

            if (_isMarqueeSelecting)
            {
                _isMarqueeSelecting = false;
                SelectionMarqueeBorder.Visibility = Visibility.Collapsed;
                DesktopWorkAreaGrid.ReleaseMouseCapture();
            }
        }

        private void DesktopSurface_MouseRightButtonUp(object sender, MouseButtonEventArgs e)
        {
            if (e.OriginalSource != DesktopWorkAreaGrid && e.OriginalSource != DesktopIconsCanvas)
                return;

            CloseAllFlyoutsAndMenus(keepContext: true);
            CommitInlineRenameIfNeeded();
            ShowEmptyDesktopContextMenu(e.GetPosition(DesktopWorkAreaGrid));
        }

        private void DesktopSurface_ExternalFileDragOver(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent(DataFormats.FileDrop) || e.Data.GetDataPresent("FunkenwerkAppId"))
            {
                e.Effects = (Keyboard.Modifiers & ModifierKeys.Control) != 0 ? DragDropEffects.Copy : DragDropEffects.Move;
                var pos = e.GetPosition(DesktopWorkAreaGrid);
                var hoverTarget = _desktopItems.FirstOrDefault(other =>
                    _desktopVisuals.TryGetValue(other.Id, out var ov) &&
                    new Rect(Canvas.GetLeft(ov), Canvas.GetTop(ov), ov.Width, ov.Height).Contains(pos));
                UpdateDesktopDropTargetHighlight(hoverTarget?.Id);
            }
            else
            {
                e.Effects = DragDropEffects.None;
                UpdateDesktopDropTargetHighlight(null);
            }
            e.Handled = true;
        }

        private void DesktopSurface_ExternalFileDrop(object sender, DragEventArgs e)
        {
            UpdateDesktopDropTargetHighlight(null);
            var dropPos = e.GetPosition(DesktopWorkAreaGrid);
            int dropCol = Math.Max(0, (int)Math.Round((dropPos.X - GridPadX) / GridCellW));
            int dropRow = Math.Clamp((int)Math.Round((dropPos.Y - GridPadY) / GridCellH), 0, Math.Max(1, (int)((DesktopWorkAreaGrid.ActualHeight - 40) / GridCellH) - 1));

            // 1. Dragged App from Start Menu onto Desktop
            if (e.Data.GetDataPresent("FunkenwerkAppId") && e.Data.GetData("FunkenwerkAppId") is string appId)
            {
                e.Handled = true;
                if (!_settings.VisibleDesktopShortcutIds.Any(x => string.Equals(x, appId, StringComparison.OrdinalIgnoreCase)))
                    _settings.VisibleDesktopShortcutIds.Add(appId);
                var occupied = _desktopItems.Where(x => !string.Equals(x.Id, appId, StringComparison.OrdinalIgnoreCase)).Select(x => (x.Col, x.Row)).ToHashSet();
                var (finalCol, finalRow) = FindNearestFreeCell(occupied, dropCol, dropRow);
                _settings.DesktopPositions[appId] = new DesktopGridPos { Col = finalCol, Row = finalRow };
                OsVaultAndStorage.SaveSettings(_settings);
                CloseAllFlyoutsAndMenus();
                RebuildDesktopIcons();
                ShowToast("✓ Auf Desktop platziert");
                return;
            }

            if (!e.Data.GetDataPresent(DataFormats.FileDrop)) return;
            if (e.Data.GetData(DataFormats.FileDrop) is not string[] droppedPaths || droppedPaths.Length == 0) return;

            // 2. Check if dropped directly onto a Desktop Icon (RecycleBin, Meine Daten, Folder, or App)
            var dropTarget = _desktopItems.FirstOrDefault(other =>
                _desktopVisuals.TryGetValue(other.Id, out var ov) &&
                new Rect(Canvas.GetLeft(ov), Canvas.GetTop(ov), ov.Width, ov.Height).Contains(dropPos));

            if (dropTarget != null)
            {
                if (dropTarget.Kind == DesktopEntryKind.RecycleBin)
                {
                    foreach (string p in droppedPaths) MovePathToRecycleBin(p);
                    return;
                }
                if (dropTarget.Kind == DesktopEntryKind.UserFolder && !string.IsNullOrEmpty(dropTarget.FullFilePath))
                {
                    TransferPathsIntoDirectory(droppedPaths, dropTarget.FullFilePath, moveInsteadOfCopy: (Keyboard.Modifiers & ModifierKeys.Control) == 0);
                    return;
                }
                if (dropTarget.Kind == DesktopEntryKind.SystemTool && dropTarget.AppOrToolKey == "MeineDatenDir")
                {
                    OsVaultAndStorage.EnsureMeineDatenStructure();
                    TransferPathsIntoDirectory(droppedPaths, OsVaultAndStorage.EingangTrichterDir, moveInsteadOfCopy: (Keyboard.Modifiers & ModifierKeys.Control) == 0);
                    return;
                }
                if (dropTarget.Kind == DesktopEntryKind.CollectionApp && !string.IsNullOrEmpty(dropTarget.AppOrToolKey))
                {
                    OsHardwareAndAudioEngine.LaunchOrFocusCollectionApp(dropTarget.AppOrToolKey, droppedPaths[0]);
                    return;
                }
            }

            bool fromExplorerMove = (Keyboard.Modifiers & ModifierKeys.Control) == 0 &&
                                    droppedPaths.All(p => !string.Equals(Path.GetDirectoryName(p), OsVaultAndStorage.OsDesktopDir, StringComparison.OrdinalIgnoreCase) &&
                                                          p.StartsWith(OsVaultAndStorage.DataDir, StringComparison.OrdinalIgnoreCase));

            var occ = _desktopItems.Select(x => (x.Col, x.Row)).ToHashSet();
            foreach (string p in droppedPaths)
            {
                string fn = Path.GetFileName(p);
                var (fc, fr) = FindNearestFreeCell(occ, dropCol, dropRow);
                occ.Add((fc, fr));
                _settings.DesktopPositions[$"fs:{fn}"] = new DesktopGridPos { Col = fc, Row = fr };
            }
            OsVaultAndStorage.SaveSettings(_settings);
            TransferPathsIntoDirectory(droppedPaths, OsVaultAndStorage.OsDesktopDir, moveInsteadOfCopy: fromExplorerMove);
        }

        private void ActivateDesktopItem(DesktopItemModel item)
        {
            switch (item.Kind)
            {
                case DesktopEntryKind.CollectionApp:
                    OsHardwareAndAudioEngine.LaunchOrFocusCollectionApp(item.AppOrToolKey);
                    break;
                case DesktopEntryKind.SystemTool:
                    if (item.AppOrToolKey == "GewerbeMonitorWin")
                    {
                        OpenOrFocusGewerbeMonitor();
                    }
                    else if (item.AppOrToolKey == "ExplorerWin")
                    {
                        NavigateExplorer(OsVaultAndStorage.OsDesktopDir, recordHistory: true);
                        OpenInternalWindowAnimated("ExplorerWin");
                    }
                    else if (item.AppOrToolKey == "MeineDatenDir")
                    {
                        OsVaultAndStorage.EnsureMeineDatenStructure();
                        NavigateExplorer(OsVaultAndStorage.MeineDatenRootDir, recordHistory: true);
                        OpenInternalWindowAnimated("ExplorerWin");
                    }
                    else
                    {
                        OpenInternalWindowAnimated(item.AppOrToolKey);
                    }
                    break;
                case DesktopEntryKind.RecycleBin:
                    NavigateExplorer(OsVaultAndStorage.OsRecycleBinDir, recordHistory: true);
                    OpenInternalWindowAnimated("ExplorerWin");
                    break;
                case DesktopEntryKind.UserFolder:
                    if (!string.IsNullOrEmpty(item.FullFilePath))
                    {
                        NavigateExplorer(item.FullFilePath, recordHistory: true);
                        OpenInternalWindowAnimated("ExplorerWin");
                    }
                    break;
                case DesktopEntryKind.UserFile:
                    if (!string.IsNullOrEmpty(item.FullFilePath))
                        OpenFileWithConfiguredDefaultProgram(item.FullFilePath);
                    break;
            }
        }

        // =====================================================================
        // 5. CONTEXT MENUS, FILE CLIPBOARD (COPY/CUT/PASTE), PROPERTIES & RENAME
        // =====================================================================
        private void ShowEmptyDesktopContextMenu(System.Windows.Point pos)
        {
            ContextMenuItemsStack.Children.Clear();
            AddContextBtn("📁   Neuer Ordner", () => CreateNewFolderOnDesktop(pos));
            AddContextBtn("📄   Neue Notiz", () => CreateNewNoteOnDesktop(pos));
            if (_fileClipboardPaths.Count > 0)
                AddContextBtn($"📋   Einfügen ({_fileClipboardPaths.Count})", () => PasteClipboardIntoFolder(OsVaultAndStorage.OsDesktopDir));
            try
            {
                if (Clipboard.ContainsImage())
                    AddContextBtn("🖼   Bild einfügen (Strg+V)", () => PasteClipboardImageIntoFolder(OsVaultAndStorage.OsDesktopDir));
            }
            catch { }
            AddContextSeparator();
            AddContextBtn("✦   Monitor Gewerbebereich öffnen", OpenOrFocusGewerbeMonitor);
            AddContextBtn("↻   Aktualisieren", RebuildDesktopIcons);
            AddContextBtn("⊞   Symbole anordnen", AutoArrangeDesktopIcons);
            if (_settings.VisibleDesktopShortcutIds.Count > 0)
            {
                AddContextBtn($"🧹   Desktop aufräumen (Apps ausblenden)", () =>
                {
                    _settings.VisibleDesktopShortcutIds.Clear();
                    OsVaultAndStorage.SaveSettings(_settings);
                    RebuildDesktopIcons();
                    RefreshStartMenuApps(StartSearchBox.Text);
                    ShowToast("✓ Desktop aufgeräumt");
                });
            }
            AddContextSeparator();
            AddContextBtn("☁   Anbindungen & Trichter (kDrive)", () => { SwitchSettingsTab("storage"); OpenInternalWindowAnimated("SettingsWin"); });
            AddContextBtn("⚙   Taskleisteneinstellungen", () => { SwitchSettingsTab("taskbar"); OpenInternalWindowAnimated("SettingsWin"); });
            AddContextBtn("⚙   Standardprogramme", () => { SwitchSettingsTab("defaults"); OpenInternalWindowAnimated("SettingsWin"); });
            AddContextBtn("✦   Personalisierung", () => { SwitchSettingsTab("style"); OpenInternalWindowAnimated("SettingsWin"); });
            AddContextBtn("🛡   Festung & Freigaben", () => { SwitchSettingsTab("fortress"); OpenInternalWindowAnimated("SettingsWin"); });
            PopupContextMenuAt(pos);
        }

        private void ShowIconContextMenu(DesktopItemModel item, System.Windows.Point pos)
        {
            ContextMenuItemsStack.Children.Clear();
            AddContextBtn("▶   Öffnen", () => ActivateDesktopItem(item));

            if (item.IsDeletable && !string.IsNullOrEmpty(item.FullFilePath))
            {
                string path = item.FullFilePath;
                string iconExt = Path.GetExtension(path).ToLowerInvariant();
                if (iconExt is ".cmd" or ".bat" or ".ps1" or ".exe")
                    AddContextBtn("🛡   Als Administrator ausführen", () => ConfirmAndExecuteElevated(path));
                AddContextSeparator();
                AddContextBtn("📋   Kopieren", () => SetFileClipboard(new[] { path }, isCut: false));
                AddContextBtn("✂   Ausschneiden", () => SetFileClipboard(new[] { path }, isCut: true));
                AddContextBtn("✎   Umbenennen", () => BeginInlineRename(item));
                AddContextBtn("ℹ   Eigenschaften", () => OpenFilePropertiesModal(path));
                AddContextSeparator();
                AddContextBtn("🗑   In Papierkorb", () => MovePathToRecycleBin(path));
            }
            else
            {
                if (item.Kind is DesktopEntryKind.CollectionApp or DesktopEntryKind.SystemTool)
                {
                    AddContextSeparator();
                    bool pinned = IsAppPinnedToTaskbar(item.Id);
                    AddContextBtn(pinned ? "📌   Von Taskleiste lösen" : "📌   An Taskleiste anheften", () => TogglePinAppToTaskbar(item.Id, item.Title));
                }

                AddContextSeparator();
                AddContextBtn("✎   Umbenennen", () => BeginInlineRename(item));
                if (_settings.CustomDisplayNames.ContainsKey(item.Id))
                {
                    AddContextBtn("↺   Standardname", () =>
                    {
                        _settings.CustomDisplayNames.Remove(item.Id);
                        OsVaultAndStorage.SaveSettings(_settings);
                        RebuildDesktopIcons();
                        RefreshStartMenuApps(StartSearchBox.Text);
                        RefreshTaskbarStrip();
                        ShowToast("✓ Standardname wiederhergestellt");
                    });
                }

                if (item.Kind == DesktopEntryKind.RecycleBin)
                {
                    AddContextSeparator();
                    AddContextBtn("🗑   Papierkorb leeren", EmptyRecycleBinInternal);
                }

                if (item.IsShortcutRemovable)
                {
                    AddContextSeparator();
                    AddContextBtn("🗑   Vom Desktop entfernen", () => RemoveShortcutFromDesktop(item.Id, item.Title));
                }
            }

            PopupContextMenuAt(pos);
        }

        private void SetFileClipboard(IEnumerable<string> paths, bool isCut)
        {
            _fileClipboardPaths.Clear();
            _fileClipboardPaths.AddRange(paths.Where(p => File.Exists(p) || Directory.Exists(p)).Distinct(StringComparer.OrdinalIgnoreCase));
            _fileClipboardIsCut = isCut;

            if (ExplorerWin.Visibility == Visibility.Visible)
                UpdateExplorerSelectionAndClipboardFast();

            ShowToast(isCut ? $"✂ {_fileClipboardPaths.Count} zum Verschieben gemerkt" : $"📋 {_fileClipboardPaths.Count} kopiert");
        }

        private void PasteClipboardIntoFolder(string targetFolder)
        {
            if (!Directory.Exists(targetFolder)) return;
            if (_fileClipboardPaths.Count > 0)
            {
                TransferPathsIntoDirectory(_fileClipboardPaths.ToList(), targetFolder, moveInsteadOfCopy: _fileClipboardIsCut);
                if (_fileClipboardIsCut)
                {
                    _fileClipboardPaths.Clear();
                    _fileClipboardIsCut = false;
                }
                return;
            }

            try
            {
                if (Clipboard.ContainsFileDropList())
                {
                    var dropList = Clipboard.GetFileDropList().Cast<string>().Where(p => !string.IsNullOrWhiteSpace(p)).ToList();
                    if (dropList.Count > 0)
                    {
                        TransferPathsIntoDirectory(dropList, targetFolder, moveInsteadOfCopy: false);
                        return;
                    }
                }
                if (Clipboard.ContainsImage())
                {
                    PasteClipboardImageIntoFolder(targetFolder);
                }
            }
            catch { }
        }

        private void PasteClipboardImageIntoFolder(string targetFolder)
        {
            try
            {
                if (!Directory.Exists(targetFolder) || !Clipboard.ContainsImage()) return;
                var bmp = Clipboard.GetImage();
                if (bmp == null) return;

                string baseName = $"Ausschnitt_{DateTime.Now:yyyy-MM-dd_HHmmss}.png";
                string fullPath = GetUniquePathInFolder(targetFolder, baseName, isFolder: false);

                var encoder = new PngBitmapEncoder();
                encoder.Frames.Add(BitmapFrame.Create(bmp));
                using (var fs = new FileStream(fullPath, FileMode.Create, FileAccess.Write, FileShare.None))
                {
                    encoder.Save(fs);
                }

                if (string.Equals(Path.GetFullPath(targetFolder).TrimEnd('\\'), Path.GetFullPath(OsVaultAndStorage.EingangTrichterDir).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase))
                    OsVaultAndStorage.SortEingangTrichterFiles();

                RebuildDesktopIcons();
                if (ExplorerWin.Visibility == Visibility.Visible)
                    NavigateExplorer(_currentExplorerDir, recordHistory: false);

                ShowToast($"✓ Bild eingefügt ({Path.GetFileName(fullPath)})");
            }
            catch { }
        }

        private void TransferPathsIntoDirectory(IEnumerable<string> sourcePaths, string targetFolder, bool moveInsteadOfCopy)
        {
            if (!Directory.Exists(targetFolder)) return;

            // Strict Trichter Invariant: Dropping directly onto MeineDatenRootDir funnels into 00_Eingang_Trichter -> 8 Pillars!
            if (string.Equals(Path.GetFullPath(targetFolder).TrimEnd('\\'), Path.GetFullPath(OsVaultAndStorage.MeineDatenRootDir).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase))
            {
                targetFolder = OsVaultAndStorage.EingangTrichterDir;
                Directory.CreateDirectory(targetFolder);
            }

            int done = 0;

            foreach (string src in sourcePaths)
            {
                try
                {
                    if (string.Equals(Path.GetFullPath(src).TrimEnd('\\'), Path.GetFullPath(targetFolder).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase))
                        continue;

                    bool isDir = Directory.Exists(src);
                    bool isFile = !isDir && File.Exists(src);
                    if (!isDir && !isFile) continue;

                    string srcParent = Path.GetDirectoryName(src) ?? "";
                    if (moveInsteadOfCopy && string.Equals(Path.GetFullPath(srcParent).TrimEnd('\\'), Path.GetFullPath(targetFolder).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase))
                        continue;

                    string dest = GetUniquePathInFolder(targetFolder, Path.GetFileName(src), isDir);

                    if (isDir)
                    {
                        if (Path.GetFullPath(dest).StartsWith(Path.GetFullPath(src).TrimEnd('\\') + "\\", StringComparison.OrdinalIgnoreCase))
                            continue;

                        if (moveInsteadOfCopy && string.Equals(Path.GetPathRoot(src), Path.GetPathRoot(dest), StringComparison.OrdinalIgnoreCase))
                            Directory.Move(src, dest);
                        else
                        {
                            CopyDirectoryRecursive(src, dest);
                            if (moveInsteadOfCopy) Directory.Delete(src, recursive: true);
                        }
                    }
                    else
                    {
                        if (moveInsteadOfCopy)
                            File.Move(src, dest);
                        else
                            File.Copy(src, dest, overwrite: false);
                    }
                    done++;
                }
                catch { }
            }

            if (done > 0)
            {
                int funnelSorted = 0;
                if (string.Equals(Path.GetFullPath(targetFolder).TrimEnd('\\'), Path.GetFullPath(OsVaultAndStorage.EingangTrichterDir).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase))
                {
                    funnelSorted = OsVaultAndStorage.SortEingangTrichterFiles();
                }

                RebuildDesktopIcons();
                if (ExplorerWin.Visibility == Visibility.Visible)
                    NavigateExplorer(_currentExplorerDir, recordHistory: false);

                if (funnelSorted > 0)
                    ShowToast($"✓ {funnelSorted} Datei(en) im Daten-Trichter automatisch sortiert");
                else
                    ShowToast(moveInsteadOfCopy ? $"✓ {done} Element(e) verschoben" : $"✓ {done} Element(e) eingefügt");
            }
        }

        private static void CopyDirectoryRecursive(string sourceDir, string destDir, int depth = 0)
        {
            if (depth > 32) return;
            var di = new DirectoryInfo(sourceDir);
            if (!di.Exists || (di.Attributes & FileAttributes.ReparsePoint) != 0) return;

            Directory.CreateDirectory(destDir);
            foreach (string file in Directory.GetFiles(sourceDir))
                File.Copy(file, Path.Combine(destDir, Path.GetFileName(file)), overwrite: true);
            foreach (string sub in Directory.GetDirectories(sourceDir))
                CopyDirectoryRecursive(sub, Path.Combine(destDir, Path.GetFileName(sub)), depth + 1);
        }

        private void MovePathToRecycleBin(string fullPath)
        {
            try
            {
                bool isDir = Directory.Exists(fullPath);
                if (!isDir && !File.Exists(fullPath)) return;

                string? originalParent = Path.GetDirectoryName(fullPath);
                string name = Path.GetFileName(fullPath);
                string dest = GetUniquePathInFolder(OsVaultAndStorage.OsRecycleBinDir, name, isDir);

                if (isDir)
                {
                    if (string.Equals(Path.GetPathRoot(fullPath), Path.GetPathRoot(dest), StringComparison.OrdinalIgnoreCase))
                        Directory.Move(fullPath, dest);
                    else { CopyDirectoryRecursive(fullPath, dest); Directory.Delete(fullPath, true); }
                }
                else
                {
                    File.Move(fullPath, dest);
                }

                OsVaultAndStorage.RememberRecycleOrigin(Path.GetFileName(dest), originalParent);
                _settings.DesktopPositions.Remove($"fs:{name}");
                OsVaultAndStorage.SaveSettings(_settings);
                RebuildDesktopIcons();
                if (ExplorerWin.Visibility == Visibility.Visible)
                    NavigateExplorer(_currentExplorerDir, recordHistory: false);
                ShowToast("In Papierkorb verschoben");
            }
            catch { }
        }

        private void AddContextBtn(string label, Action action)
        {
            var btn = new Button { Content = label, Style = (Style)Resources["ContextItemBtn"] };
            btn.Click += (_, _) =>
            {
                ContextMenuCanvas.Visibility = Visibility.Collapsed;
                action();
            };
            ContextMenuItemsStack.Children.Add(btn);
        }

        private void AddContextSeparator()
        {
            ContextMenuItemsStack.Children.Add(new Border
            {
                Height = 1,
                Background = (Brush)Resources["SubtleBorderBrush"],
                Margin = new Thickness(8, 4, 8, 4)
            });
        }

        private void PopupContextMenuAt(System.Windows.Point pos)
        {
            ContextMenuCanvas.Visibility = Visibility.Visible;
            DesktopContextMenuBorder.UpdateLayout();

            double menuW = 222;
            double menuH = Math.Max(130, DesktopContextMenuBorder.ActualHeight > 0 ? DesktopContextMenuBorder.ActualHeight : 250);
            double x = Math.Clamp(pos.X, 8, Math.Max(12, DesktopWorkAreaGrid.ActualWidth - menuW - 10));
            double y = Math.Clamp(pos.Y, 8, Math.Max(12, DesktopWorkAreaGrid.ActualHeight - menuH - 10));

            Canvas.SetLeft(DesktopContextMenuBorder, x);
            Canvas.SetTop(DesktopContextMenuBorder, y);

            if (DesktopContextMenuBorder.RenderTransform is ScaleTransform st)
            {
                st.ScaleX = 0.92;
                st.ScaleY = 0.92;
                AnimateScale(st, 1.0, 130);
            }
            AnimateOpacity(DesktopContextMenuBorder, 0.0, 1.0, 120);
        }

        private void CreateNewFolderOnDesktop(System.Windows.Point atPoint)
        {
            string path = GetUniquePathInFolder(OsVaultAndStorage.OsDesktopDir, "Neuer Ordner", isFolder: true);
            Directory.CreateDirectory(path);
            string name = Path.GetFileName(path);

            int col = Math.Max(0, (int)Math.Round((atPoint.X - GridPadX) / GridCellW));
            int row = Math.Max(0, (int)Math.Round((atPoint.Y - GridPadY) / GridCellH));
            _settings.DesktopPositions[$"fs:{name}"] = new DesktopGridPos { Col = col, Row = row };
            OsVaultAndStorage.SaveSettings(_settings);

            RebuildDesktopIcons();
            var created = _desktopItems.FirstOrDefault(i => i.Id == $"fs:{name}");
            if (created != null) BeginInlineRename(created);
        }

        private void CreateNewNoteOnDesktop(System.Windows.Point atPoint)
        {
            string path = GetUniquePathInFolder(OsVaultAndStorage.OsDesktopDir, "Notiz.txt");
            File.WriteAllText(path, "", System.Text.Encoding.UTF8);
            string name = Path.GetFileName(path);

            int col = Math.Max(0, (int)Math.Round((atPoint.X - GridPadX) / GridCellW));
            int row = Math.Max(0, (int)Math.Round((atPoint.Y - GridPadY) / GridCellH));
            _settings.DesktopPositions[$"fs:{name}"] = new DesktopGridPos { Col = col, Row = row };
            OsVaultAndStorage.SaveSettings(_settings);

            RebuildDesktopIcons();
            var created = _desktopItems.FirstOrDefault(i => i.Id == $"fs:{name}");
            if (created != null) BeginInlineRename(created);
        }

        private void AutoArrangeDesktopIcons()
        {
            _settings.DesktopPositions.Clear();
            OsVaultAndStorage.SaveSettings(_settings);
            RebuildDesktopIcons();
            ShowToast("✓ Symbole angeordnet");
        }

        private void BeginInlineRename(DesktopItemModel item)
        {
            if (!item.IsRenamable || !_desktopVisuals.TryGetValue(item.Id, out var vis)) return;
            _renamingItem = item;
            Canvas.SetLeft(RenameBoxBorder, Math.Max(4, Canvas.GetLeft(vis) - 13));
            Canvas.SetTop(RenameBoxBorder, Canvas.GetTop(vis) + 60);

            InlineRenameTextBox.Text = item.Title;
            RenameOverlayCanvas.Visibility = Visibility.Visible;
            InlineRenameTextBox.Focus();
            int dot = item.Title.LastIndexOf('.');
            if (item.Kind == DesktopEntryKind.UserFile && dot > 0) InlineRenameTextBox.Select(0, dot);
            else InlineRenameTextBox.SelectAll();
        }

        private void InlineRenameTextBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter) { e.Handled = true; CommitInlineRenameIfNeeded(); }
            else if (e.Key == Key.Escape) { e.Handled = true; _renamingItem = null; RenameOverlayCanvas.Visibility = Visibility.Collapsed; }
        }

        private void InlineRenameTextBox_LostFocus(object sender, RoutedEventArgs e) => CommitInlineRenameIfNeeded();

        private void CommitInlineRenameIfNeeded()
        {
            if (_renamingItem == null) return;
            var item = _renamingItem;
            _renamingItem = null;
            RenameOverlayCanvas.Visibility = Visibility.Collapsed;

            string rawName = InlineRenameTextBox.Text.Trim();
            if (item.Kind is DesktopEntryKind.CollectionApp or DesktopEntryKind.SystemTool or DesktopEntryKind.RecycleBin)
            {
                if (!string.IsNullOrWhiteSpace(rawName) && !string.Equals(rawName, item.Title, StringComparison.Ordinal))
                {
                    _settings.CustomDisplayNames[item.Id] = rawName;
                    OsVaultAndStorage.SaveSettings(_settings);
                    RebuildDesktopIcons();
                    RefreshStartMenuApps(StartSearchBox.Text);
                    RefreshTaskbarStrip();
                    ShowToast($"✓ Umbenannt in '{rawName}'");
                }
                return;
            }

            if (string.IsNullOrEmpty(item.FullFilePath)) return;
            RenameFileSystemEntry(item.FullFilePath, rawName);
        }

        private bool RenameFileSystemEntry(string oldFullPath, string rawNewName)
        {
            string clean = rawNewName;
            foreach (char invalid in Path.GetInvalidFileNameChars())
                clean = clean.Replace(invalid.ToString(), "");
            clean = clean.Trim();

            string oldName = Path.GetFileName(oldFullPath);
            if (string.IsNullOrWhiteSpace(clean) || string.Equals(clean, oldName, StringComparison.Ordinal))
                return false;

            try
            {
                string parent = Path.GetDirectoryName(oldFullPath)!;
                string newFull = Path.Combine(parent, clean);
                if (File.Exists(newFull) || Directory.Exists(newFull)) return false;

                if (Directory.Exists(oldFullPath)) Directory.Move(oldFullPath, newFull);
                else if (File.Exists(oldFullPath)) File.Move(oldFullPath, newFull);
                else return false;

                if (_settings.DesktopPositions.TryGetValue($"fs:{oldName}", out var oldPos))
                {
                    _settings.DesktopPositions.Remove($"fs:{oldName}");
                    _settings.DesktopPositions[$"fs:{clean}"] = oldPos;
                    OsVaultAndStorage.SaveSettings(_settings);
                }

                RebuildDesktopIcons();
                if (ExplorerWin.Visibility == Visibility.Visible)
                    NavigateExplorer(_currentExplorerDir, recordHistory: false);
                return true;
            }
            catch
            {
                return false;
            }
        }

        private void EmptyRecycleBin_Click(object sender, RoutedEventArgs e) => EmptyRecycleBinInternal();

        private void EmptyRecycleBinInternal()
        {
            try
            {
                foreach (string f in Directory.GetFiles(OsVaultAndStorage.OsRecycleBinDir)) File.Delete(f);
                foreach (string d in Directory.GetDirectories(OsVaultAndStorage.OsRecycleBinDir)) Directory.Delete(d, true);
                RebuildDesktopIcons();
                if (ExplorerWin.Visibility == Visibility.Visible)
                    NavigateExplorer(_currentExplorerDir, recordHistory: false);
                ShowToast("✓ Papierkorb geleert");
            }
            catch { }
        }

        private static string GetUniquePathInFolder(string folder, string baseName, bool isFolder = false)
        {
            string candidate = Path.Combine(folder, baseName);
            if (!File.Exists(candidate) && !Directory.Exists(candidate)) return candidate;

            string stem = isFolder ? baseName : Path.GetFileNameWithoutExtension(baseName);
            string ext = isFolder ? "" : Path.GetExtension(baseName);
            for (int i = 2; i < 999; i++)
            {
                string next = Path.Combine(folder, $"{stem} ({i}){ext}");
                if (!File.Exists(next) && !Directory.Exists(next)) return next;
            }
            return Path.Combine(folder, $"{stem}_{Guid.NewGuid():N}{ext}");
        }

        // =====================================================================
        // 6. PRO FILE EXPLORER (GROSSE SYMBOLE + SORTABLE DETAIL-TABELLE)
        // =====================================================================
        private void RefreshExplorerDrivesSidebar()
        {
            OsVaultAndStorage.DiscoverAndEnsureStorageBindings(_settings);
            if (ExplorerCloudBindingsControl != null)
            {
                ExplorerCloudBindingsControl.ItemsSource = null;
                ExplorerCloudBindingsControl.ItemsSource = _settings.StorageBindings.ToList();
            }
            if (ExplorerDrivesControl != null)
            {
                ExplorerDrivesControl.ItemsSource = OsHardwareAndAudioEngine.ListMountedDrivesForSidebar();
            }
        }

        private void ApplyExplorerViewModeUi()
        {
            bool isIcons = string.Equals(_settings.ExplorerViewMode, "Icons", StringComparison.OrdinalIgnoreCase);
            ExplorerIconsView.Visibility = isIcons ? Visibility.Visible : Visibility.Collapsed;
            ExplorerDetailsView.Visibility = isIcons ? Visibility.Collapsed : Visibility.Visible;

            Style gold = (Style)Resources["NobleGoldBtn"];
            Style glass = (Style)Resources["GlassBtn"];
            ViewIconsBtn.Style = isIcons ? gold : glass;
            ViewDetailsBtn.Style = !isIcons ? gold : glass;
            UpdateSortHeaderLabels();
        }

        private void SwitchExplorerViewIcons_Click(object sender, RoutedEventArgs e)
        {
            _settings.ExplorerViewMode = "Icons";
            OsVaultAndStorage.SaveSettings(_settings);
            ApplyExplorerViewModeUi();
            RefreshExplorerItemsViewOnly();
            if (_explorerLoadCts != null)
                _ = PopulateImageThumbnailsAsync(_explorerLoadVersion, _currentExplorerEntries, _explorerLoadCts.Token);
        }

        private void SwitchExplorerViewDetails_Click(object sender, RoutedEventArgs e)
        {
            _settings.ExplorerViewMode = "Details";
            OsVaultAndStorage.SaveSettings(_settings);
            ApplyExplorerViewModeUi();
            RefreshExplorerItemsViewOnly();
        }

        private void ExplorerSortColumn_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string col) return;
            if (string.Equals(_settings.ExplorerSortBy, col, StringComparison.OrdinalIgnoreCase))
                _settings.ExplorerSortAsc = !_settings.ExplorerSortAsc;
            else
            {
                _settings.ExplorerSortBy = col;
                _settings.ExplorerSortAsc = col == "Name" || col == "Type";
            }
            OsVaultAndStorage.SaveSettings(_settings);
            UpdateSortHeaderLabels();
            RefreshExplorerItemsViewOnly();
        }

        private void UpdateSortHeaderLabels()
        {
            string arrow = _settings.ExplorerSortAsc ? " ▲" : " ▼";
            ColSortNameBtn.Content = "Name" + (_settings.ExplorerSortBy == "Name" ? arrow : "");
            ColSortCreatedBtn.Content = "Erstellt am" + (_settings.ExplorerSortBy == "Created" ? arrow : "");
            ColSortModifiedBtn.Content = "Geändert am" + (_settings.ExplorerSortBy == "Modified" ? arrow : "");
            ColSortTypeBtn.Content = "Typ" + (_settings.ExplorerSortBy == "Type" ? arrow : "");
            ColSortSizeBtn.Content = "Größe" + (_settings.ExplorerSortBy == "Size" ? arrow : "");
        }

        private void NavigateExplorer(string folderPath, bool recordHistory)
        {
            if (string.IsNullOrWhiteSpace(folderPath) || !Directory.Exists(folderPath)) return;

            if (recordHistory && !string.IsNullOrWhiteSpace(_currentExplorerDir) &&
                !string.Equals(_currentExplorerDir, folderPath, StringComparison.OrdinalIgnoreCase))
            {
                _explorerBackStack.Push(_currentExplorerDir);
                _explorerForwardStack.Clear();
            }

            _currentExplorerDir = folderPath;
            _selectedExplorerPaths.Clear();
            ExpBackBtn.IsEnabled = _explorerBackStack.Count > 0;
            ExpForwardBtn.IsEnabled = _explorerForwardStack.Count > 0;

            bool isRecycle = string.Equals(Path.GetFullPath(folderPath).TrimEnd('\\'), Path.GetFullPath(OsVaultAndStorage.OsRecycleBinDir).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase);
            EmptyRecycleBtn.Visibility = isRecycle ? Visibility.Visible : Visibility.Collapsed;

            BuildExplorerBreadcrumbs(folderPath);
            LoadExplorerDirectoryEntries(folderPath);
        }

        private void BuildExplorerBreadcrumbs(string folderPath)
        {
            var crumbs = new List<OsBreadcrumbSegment>();
            string norm = Path.GetFullPath(folderPath).TrimEnd('\\');

            if (string.Equals(norm, Path.GetFullPath(OsVaultAndStorage.OsDesktopDir).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase))
            {
                crumbs.Add(new OsBreadcrumbSegment { Label = "Desktop", FullPath = OsVaultAndStorage.OsDesktopDir });
            }
            else if (string.Equals(norm, Path.GetFullPath(OsVaultAndStorage.OsRecycleBinDir).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase))
            {
                crumbs.Add(new OsBreadcrumbSegment { Label = "Papierkorb", FullPath = OsVaultAndStorage.OsRecycleBinDir });
            }
            else if (string.Equals(norm, Path.GetFullPath(OsVaultAndStorage.MeineDatenRootDir).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase))
            {
                crumbs.Add(new OsBreadcrumbSegment { Label = "✦ Meine Daten", FullPath = OsVaultAndStorage.MeineDatenRootDir });
            }
            else if (norm.StartsWith(OsVaultAndStorage.MeineDatenRootDir, StringComparison.OrdinalIgnoreCase))
            {
                crumbs.Add(new OsBreadcrumbSegment { Label = "✦ Meine Daten", FullPath = OsVaultAndStorage.MeineDatenRootDir });
                string rel = norm.Substring(OsVaultAndStorage.MeineDatenRootDir.TrimEnd('\\').Length).TrimStart('\\');
                string acc = OsVaultAndStorage.MeineDatenRootDir;
                foreach (string part in rel.Split('\\', StringSplitOptions.RemoveEmptyEntries))
                {
                    acc = Path.Combine(acc, part);
                    crumbs.Add(new OsBreadcrumbSegment { Label = part, FullPath = acc });
                }
            }
            else if (norm.StartsWith(OsVaultAndStorage.OsDesktopDir, StringComparison.OrdinalIgnoreCase))
            {
                crumbs.Add(new OsBreadcrumbSegment { Label = "Desktop", FullPath = OsVaultAndStorage.OsDesktopDir });
                string rel = norm.Substring(OsVaultAndStorage.OsDesktopDir.TrimEnd('\\').Length).TrimStart('\\');
                string acc = OsVaultAndStorage.OsDesktopDir;
                foreach (string part in rel.Split('\\', StringSplitOptions.RemoveEmptyEntries))
                {
                    acc = Path.Combine(acc, part);
                    crumbs.Add(new OsBreadcrumbSegment { Label = part, FullPath = acc });
                }
            }
            else
            {
                string[] parts = norm.Split('\\', StringSplitOptions.RemoveEmptyEntries);
                string acc = norm.StartsWith(@"\\") ? @"\\" : "";
                for (int i = 0; i < parts.Length; i++)
                {
                    if (i == 0 && parts[0].EndsWith(':'))
                        acc = parts[0] + "\\";
                    else
                        acc = string.IsNullOrEmpty(acc) || acc == @"\\" ? acc + parts[i] : Path.Combine(acc, parts[i]);

                    crumbs.Add(new OsBreadcrumbSegment { Label = parts[i], FullPath = acc });
                }
            }

            ExplorerBreadcrumbsControl.ItemsSource = crumbs;
        }

        private void BreadcrumbSegment_Click(object sender, RoutedEventArgs e)
        {
            if (sender is FrameworkElement fe && fe.Tag is string path && Directory.Exists(path))
                NavigateExplorer(path, recordHistory: true);
        }

        private void ExplorerWin_PreviewMouseDown(object sender, MouseButtonEventArgs e)
        {
            if (e.ChangedButton == MouseButton.XButton1)
            {
                ExplorerBack_Click(this, new RoutedEventArgs());
                e.Handled = true;
            }
            else if (e.ChangedButton == MouseButton.XButton2)
            {
                ExplorerForward_Click(this, new RoutedEventArgs());
                e.Handled = true;
            }
        }

        private void ExplorerDropTarget_DragOver(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent(DataFormats.FileDrop))
                e.Effects = (Keyboard.Modifiers & ModifierKeys.Control) != 0 ? DragDropEffects.Copy : DragDropEffects.Move;
            else
                e.Effects = DragDropEffects.None;
            e.Handled = true;
        }

        private void BreadcrumbSegment_Drop(object sender, DragEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string targetPath || !Directory.Exists(targetPath))
                return;
            if (e.Data.GetDataPresent(DataFormats.FileDrop) && e.Data.GetData(DataFormats.FileDrop) is string[] dropped)
            {
                e.Handled = true;
                TransferPathsIntoDirectory(dropped, targetPath, moveInsteadOfCopy: (Keyboard.Modifiers & ModifierKeys.Control) == 0);
            }
        }

        private void ExplorerDriveItem_Drop(object sender, DragEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string root || !Directory.Exists(root))
                return;
            if (e.Data.GetDataPresent(DataFormats.FileDrop) && e.Data.GetData(DataFormats.FileDrop) is string[] dropped)
            {
                e.Handled = true;
                TransferPathsIntoDirectory(dropped, root, moveInsteadOfCopy: (Keyboard.Modifiers & ModifierKeys.Control) == 0);
            }
        }

        private void ExplorerQuickNav_Drop(object sender, DragEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string tag) return;
            if (!e.Data.GetDataPresent(DataFormats.FileDrop) || e.Data.GetData(DataFormats.FileDrop) is not string[] dropped) return;
            e.Handled = true;

            if (tag == "RecycleBin")
            {
                foreach (string p in dropped) MovePathToRecycleBin(p);
                return;
            }

            string target = ResolveQuickNavTargetFolder(tag);
            if (tag == "MeineDaten")
                target = OsVaultAndStorage.EingangTrichterDir; // Drop on Meine Daten sorts automatically via Funnel!

            TransferPathsIntoDirectory(dropped, target, moveInsteadOfCopy: (Keyboard.Modifiers & ModifierKeys.Control) == 0);
        }

        private string ResolveQuickNavTargetFolder(string tag)
        {
            OsVaultAndStorage.EnsureMeineDatenStructure();
            if (!string.IsNullOrWhiteSpace(tag) && tag.StartsWith("Pillar:", StringComparison.OrdinalIgnoreCase))
            {
                string pillarName = tag.Substring(7);
                string pillarPath = Path.Combine(OsVaultAndStorage.MeineDatenRootDir, pillarName);
                Directory.CreateDirectory(pillarPath);
                return pillarPath;
            }

            if (string.Equals(tag, "CloudKDrive", StringComparison.OrdinalIgnoreCase))
            {
                var kd = _settings.StorageBindings.FirstOrDefault(b => b.Id.Equals("kdrive", StringComparison.OrdinalIgnoreCase));
                if (kd != null && kd.IsOnline)
                {
                    string kdRoot = kd.MirrorMeineDatenStructure ? OsVaultAndStorage.GetBindingMeineDatenRoot(kd) : kd.LocalOrUncPath;
                    if (Directory.Exists(kdRoot)) return kdRoot;
                    if (Directory.Exists(kd.LocalOrUncPath)) return kd.LocalOrUncPath;
                }
                return OsVaultAndStorage.MeineDatenRootDir;
            }

            return tag switch
            {
                "MeineDaten" => OsVaultAndStorage.MeineDatenRootDir,
                "EingangTrichter" => OsVaultAndStorage.EingangTrichterDir,
                "OsDesktop" => OsVaultAndStorage.OsDesktopDir,
                "RecycleBin" => OsVaultAndStorage.OsRecycleBinDir,
                "SeelenfunkeProject" => WslSeelenfunkeProjectPath,
                _ => OsVaultAndStorage.MeineDatenRootDir
            };
        }

        private void ExplorerCloudBindingItem_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not OsStorageBindingDto binding) return;
            RefreshExplorerDrivesSidebar();
            if (!binding.IsOnline)
            {
                SwitchSettingsTab("storage");
                OpenInternalWindowAnimated("SettingsWin");
                ShowToast($"Pfad '{binding.LocalOrUncPath}' aktuell nicht erreichbar");
                return;
            }

            string target = binding.MirrorMeineDatenStructure
                ? OsVaultAndStorage.GetBindingMeineDatenRoot(binding)
                : binding.LocalOrUncPath;
            if (!Directory.Exists(target))
                target = binding.LocalOrUncPath;

            NavigateExplorer(target, recordHistory: true);
        }

        private void ExplorerCloudBindingItem_RightClick(object sender, MouseButtonEventArgs e)
        {
            e.Handled = true;
            if (sender is not FrameworkElement fe || fe.Tag is not OsStorageBindingDto binding) return;
            CloseAllFlyoutsAndMenus(keepContext: true);
            ContextMenuItemsStack.Children.Clear();

            if (binding.IsOnline)
            {
                AddContextBtn("✦   Meine Daten (8 Säulen) öffnen", () =>
                {
                    string md = OsVaultAndStorage.GetBindingMeineDatenRoot(binding);
                    OsVaultAndStorage.EnsureMeineDatenStructure(md);
                    NavigateExplorer(md, recordHistory: true);
                });
                AddContextBtn("📥   00 · Eingang-Trichter öffnen", () =>
                {
                    string inb = Path.Combine(binding.LocalOrUncPath, "00_Eingang_Trichter");
                    Directory.CreateDirectory(inb);
                    NavigateExplorer(inb, recordHistory: true);
                });
                AddContextBtn("📁   Hauptordner öffnen", () => NavigateExplorer(binding.LocalOrUncPath, recordHistory: true));
                AddContextSeparator();
                AddContextBtn("☁   Jetzt mit Trichter synchronisieren", () => ExplorerSyncCloudNow_Click(this, new RoutedEventArgs()));
            }
            AddContextBtn("⚙   Anbindung konfigurieren", () => { SwitchSettingsTab("storage"); OpenInternalWindowAnimated("SettingsWin"); });
            PopupContextMenuAt(e.GetPosition(DesktopWorkAreaGrid));
        }

        private void ExplorerCloudBindingItem_Drop(object sender, DragEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not OsStorageBindingDto binding || !binding.IsOnline) return;
            if (!e.Data.GetDataPresent(DataFormats.FileDrop) || e.Data.GetData(DataFormats.FileDrop) is not string[] dropped) return;
            e.Handled = true;

            // Dropping files onto kDrive / Cloud Binding routes them through the 8-Pillar Trichter & syncs to the cloud binding!
            TransferPathsIntoDirectory(dropped, OsVaultAndStorage.EingangTrichterDir, moveInsteadOfCopy: (Keyboard.Modifiers & ModifierKeys.Control) == 0);
            var (copied, _) = OsVaultAndStorage.SyncMeineDatenToStorageBindings(_settings);
            ShowToast($"✓ Im 8-Säulen-Trichter sortiert & mit {binding.Name} synchronisiert ({copied} Dateien)");
        }

        private void ExplorerSyncCloudNow_Click(object sender, RoutedEventArgs e)
        {
            int ingested = OsVaultAndStorage.IngestExternalDownloadsAndCloudInboxes(_settings, _osBootUtc);
            int sorted = OsVaultAndStorage.SortEingangTrichterFiles();
            var (copied, targets) = OsVaultAndStorage.SyncMeineDatenToStorageBindings(_settings);
            RefreshExplorerDrivesSidebar();
            UpdateStorageAndFunnelSettingsUi();
            if (ExplorerWin.Visibility == Visibility.Visible)
                NavigateExplorer(_currentExplorerDir, recordHistory: false);

            ShowToast(targets > 0
                ? $"✓ 8-Säulen-Trichter mit {targets} Anbindung(en) synchronisiert ({copied} übertragen, {ingested + sorted} einsortiert)"
                : "✓ Trichter bereinigt (keine aktive Cloud-Anbindung verbunden)");
        }

        private void ExplorerBack_Click(object sender, RoutedEventArgs e)
        {
            if (_explorerBackStack.Count == 0) return;
            string prev = _explorerBackStack.Pop();
            _explorerForwardStack.Push(_currentExplorerDir);
            NavigateExplorer(prev, recordHistory: false);
        }

        private void ExplorerForward_Click(object sender, RoutedEventArgs e)
        {
            if (_explorerForwardStack.Count == 0) return;
            string next = _explorerForwardStack.Pop();
            _explorerBackStack.Push(_currentExplorerDir);
            NavigateExplorer(next, recordHistory: false);
        }

        private void ExplorerUp_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                var parent = Directory.GetParent(_currentExplorerDir);
                if (parent != null && parent.Exists)
                    NavigateExplorer(parent.FullName, recordHistory: true);
            }
            catch { }
        }

        private void ExplorerDriveItem_Click(object sender, RoutedEventArgs e)
        {
            if (sender is FrameworkElement fe && fe.Tag is string root && Directory.Exists(root))
                NavigateExplorer(root, recordHistory: true);
        }

        private void ExplorerQuickNav_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string tag) return;
            RefreshExplorerDrivesSidebar();
            NavigateExplorer(ResolveQuickNavTargetFolder(tag), recordHistory: true);
        }

        private void ExplorerSortFunnel_Click(object sender, RoutedEventArgs e)
        {
            int ingested = OsVaultAndStorage.IngestExternalDownloadsAndCloudInboxes(_settings, _osBootUtc);
            int moved = OsVaultAndStorage.SortEingangTrichterFiles();
            if (ExplorerWin.Visibility == Visibility.Visible)
                NavigateExplorer(_currentExplorerDir, recordHistory: false);
            RebuildDesktopIcons();
            int total = moved + ingested;
            ShowToast(total > 0 ? $"✓ {moved} Datei(en) im 8-Säulen-Trichter sortiert" : "✓ Daten-Trichter ist sauber (0 offene Dateien)");
        }

        private void ExplorerSearchBox_TextChanged(object sender, TextChangedEventArgs e) => RefreshExplorerItemsViewOnly();

        private async void LoadExplorerDirectoryEntries(string folderPath)
        {
            _explorerLoadCts?.Cancel();
            _explorerLoadCts?.Dispose();
            var cts = new System.Threading.CancellationTokenSource();
            _explorerLoadCts = cts;
            int version = ++_explorerLoadVersion;

            // Pre-fetch frozen BitmapSources on UI thread (0.02 ms, 100% thread-safe)
            var folderBadge = OsHardwareAndAudioEngine.GetSystemBadgeIcon("folder");
            var imgBadge = OsHardwareAndAudioEngine.GetSystemBadgeIcon("file_image");
            var txtBadge = OsHardwareAndAudioEngine.GetSystemBadgeIcon("file_text");
            var appIconMap = OsHardwareAndAudioEngine.DiscoverCollectionApps()
                .ToDictionary(a => a.Key, a => a.IconImage, StringComparer.OrdinalIgnoreCase);

            bool isIconsMode = string.Equals(_settings.ExplorerViewMode, "Icons", StringComparison.OrdinalIgnoreCase);
            ExplorerStatusFooterTxt.Text = "Lade Ordner...";

            BitmapSource? ResolveFastFrozenIcon(string ext)
            {
                if (ext is ".html" or ".htm" or ".url" && appIconMap.TryGetValue("browser", out var brIco)) return brIco;
                if (ext == ".pdf" && appIconMap.TryGetValue("pdf", out var pdfIco)) return pdfIco;
                if (ext is ".mp3" or ".wav" or ".flac" or ".m4a" or ".mp4" or ".mkv" or ".mov" && appIconMap.TryGetValue("player", out var plIco)) return plIco;
                if (ext is ".kdbx" or ".keyx" && appIconMap.TryGetValue("tresor", out var trIco)) return trIco;
                if (ext is ".png" or ".jpg" or ".jpeg" or ".webp" or ".bmp") return imgBadge;
                return txtBadge;
            }

            List<OsFileEntryDto> rawList;
            try
            {
                rawList = await Task.Run(() =>
                {
                    var list = new List<OsFileEntryDto>(128);
                    var diRoot = new DirectoryInfo(folderPath);
                    if (!diRoot.Exists) return list;

                    foreach (var di in diRoot.EnumerateDirectories())
                    {
                        if (cts.Token.IsCancellationRequested) return list;
                        try
                        {
                            if ((di.Attributes & FileAttributes.Hidden) != 0) continue;
                            list.Add(new OsFileEntryDto
                            {
                                Name = di.Name,
                                FullPath = di.FullName,
                                IsDirectory = true,
                                TypeBadge = "Ordner",
                                RawBytes = 0,
                                SizeFormatted = "—",
                                CreatedAt = di.CreationTime,
                                ModifiedAt = di.LastWriteTime,
                                CreatedFormatted = di.CreationTime.ToString("dd.MM.yyyy HH:mm"),
                                ModifiedFormatted = di.LastWriteTime.ToString("dd.MM.yyyy HH:mm"),
                                IconImage = folderBadge
                            });
                        }
                        catch { }
                    }

                    int fileCap = 0;
                    foreach (var fi in diRoot.EnumerateFiles())
                    {
                        if (cts.Token.IsCancellationRequested || fileCap >= 2000) break;
                        try
                        {
                            if ((fi.Attributes & FileAttributes.Hidden) != 0) continue;
                            if (OsVaultAndStorage.IsGarbageOrTempFile(fi.FullName)) continue;
                            string extLower = fi.Extension.ToLowerInvariant();
                            string ext = extLower.TrimStart('.').ToUpperInvariant();
                            if (string.IsNullOrEmpty(ext)) ext = "DATEI";

                            list.Add(new OsFileEntryDto
                            {
                                Name = fi.Name,
                                FullPath = fi.FullName,
                                IsDirectory = false,
                                TypeBadge = ext.Length > 7 ? ext.Substring(0, 7) : ext,
                                RawBytes = fi.Length,
                                SizeFormatted = FormatBytes(fi.Length),
                                CreatedAt = fi.CreationTime,
                                ModifiedAt = fi.LastWriteTime,
                                CreatedFormatted = fi.CreationTime.ToString("dd.MM.yyyy HH:mm"),
                                ModifiedFormatted = fi.LastWriteTime.ToString("dd.MM.yyyy HH:mm"),
                                IconImage = ResolveFastFrozenIcon(extLower)
                            });
                            fileCap++;
                        }
                        catch { }
                    }

                    return list;
                }, cts.Token);
            }
            catch
            {
                return;
            }

            if (version != _explorerLoadVersion || cts.Token.IsCancellationRequested)
                return;

            _currentExplorerEntries = rawList;
            RefreshExplorerItemsViewOnly();

            if (isIconsMode)
                _ = PopulateImageThumbnailsAsync(version, rawList, cts.Token);
        }

        private async Task PopulateImageThumbnailsAsync(int version, List<OsFileEntryDto> entries, System.Threading.CancellationToken token)
        {
            var imgEntries = entries
                .Where(e => !e.IsDirectory && Path.GetExtension(e.FullPath).ToLowerInvariant() is ".png" or ".jpg" or ".jpeg" or ".webp" or ".bmp")
                .Take(160)
                .ToList();

            if (imgEntries.Count == 0) return;

            foreach (var entry in imgEntries)
            {
                if (token.IsCancellationRequested || version != _explorerLoadVersion) return;
                BitmapSource? thumb = await Task.Run(() => OsHardwareAndAudioEngine.LoadImageThumbnailCached(entry.FullPath), token);
                if (token.IsCancellationRequested || version != _explorerLoadVersion) return;
                if (thumb != null)
                    entry.IconImage = thumb;
            }
        }

        private void RefreshExplorerItemsViewOnly()
        {
            string query = (ExplorerSearchBox?.Text ?? "").Trim();
            IEnumerable<OsFileEntryDto> filtered = _currentExplorerEntries;
            if (!string.IsNullOrEmpty(query))
                filtered = filtered.Where(x => x.Name.Contains(query, StringComparison.OrdinalIgnoreCase) || x.TypeBadge.Contains(query, StringComparison.OrdinalIgnoreCase));

            bool asc = _settings.ExplorerSortAsc;
            IOrderedEnumerable<OsFileEntryDto> sorted = _settings.ExplorerSortBy switch
            {
                "Created" => asc
                    ? filtered.OrderByDescending(x => x.IsDirectory).ThenBy(x => x.CreatedAt)
                    : filtered.OrderByDescending(x => x.IsDirectory).ThenByDescending(x => x.CreatedAt),
                "Modified" => asc
                    ? filtered.OrderByDescending(x => x.IsDirectory).ThenBy(x => x.ModifiedAt)
                    : filtered.OrderByDescending(x => x.IsDirectory).ThenByDescending(x => x.ModifiedAt),
                "Type" => asc
                    ? filtered.OrderByDescending(x => x.IsDirectory).ThenBy(x => x.TypeBadge).ThenBy(x => x.Name)
                    : filtered.OrderByDescending(x => x.IsDirectory).ThenByDescending(x => x.TypeBadge).ThenBy(x => x.Name),
                "Size" => asc
                    ? filtered.OrderByDescending(x => x.IsDirectory).ThenBy(x => x.RawBytes)
                    : filtered.OrderByDescending(x => x.IsDirectory).ThenByDescending(x => x.RawBytes),
                _ => asc
                    ? filtered.OrderByDescending(x => x.IsDirectory).ThenBy(x => x.Name, StringComparer.OrdinalIgnoreCase)
                    : filtered.OrderByDescending(x => x.IsDirectory).ThenByDescending(x => x.Name, StringComparer.OrdinalIgnoreCase)
            };

            _visibleExplorerEntries = sorted.ToList();
            bool isIcons = string.Equals(_settings.ExplorerViewMode, "Icons", StringComparison.OrdinalIgnoreCase);

            foreach (var entry in _visibleExplorerEntries)
            {
                entry.IsSelected = _selectedExplorerPaths.Contains(entry.FullPath);
                entry.IsCut = _fileClipboardIsCut && _fileClipboardPaths.Contains(entry.FullPath, StringComparer.OrdinalIgnoreCase);
            }

            if (isIcons)
            {
                ExplorerIconsItemsControl.ItemsSource = _visibleExplorerEntries;
                ExplorerDetailsItemsControl.ItemsSource = null;
            }
            else
            {
                ExplorerDetailsItemsControl.ItemsSource = _visibleExplorerEntries;
                ExplorerIconsItemsControl.ItemsSource = null;
            }

            UpdateExplorerFooterText();
        }

        private void UpdateExplorerSelectionAndClipboardFast()
        {
            foreach (var entry in _visibleExplorerEntries)
            {
                entry.IsSelected = _selectedExplorerPaths.Contains(entry.FullPath);
                entry.IsCut = _fileClipboardIsCut && _fileClipboardPaths.Contains(entry.FullPath, StringComparer.OrdinalIgnoreCase);
            }
            UpdateExplorerFooterText();
        }

        private void UpdateExplorerFooterText()
        {
            int total = _visibleExplorerEntries.Count;
            int selCount = 0;
            long selBytes = 0;
            foreach (var x in _visibleExplorerEntries)
            {
                if (x.IsSelected)
                {
                    selCount++;
                    if (!x.IsDirectory) selBytes += x.RawBytes;
                }
            }

            string clipInfo = _fileClipboardPaths.Count > 0
                ? $"  ·  Zwischenablage: {_fileClipboardPaths.Count}"
                : "";

            ExplorerStatusFooterTxt.Text = selCount > 0
                ? $"{total} Elemente  ·  {selCount} ausgewählt ({FormatBytes(selBytes)}){clipInfo}"
                : $"{total} Elemente{clipInfo}";
        }

        private void ExplorerEntry_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            e.Handled = true;
            CloseAllFlyoutsAndMenus();
            if (sender is not FrameworkElement fe || fe.Tag is not OsFileEntryDto dto) return;

            DateTime now = DateTime.UtcNow;
            bool isDbl = string.Equals(_lastClickedExplorerPath, dto.FullPath, StringComparison.OrdinalIgnoreCase) &&
                         (now - _lastExplorerClickUtc).TotalMilliseconds < 380;
            _lastExplorerClickUtc = now;

            if (isDbl)
            {
                _lastClickedExplorerPath = null;
                _explorerMouseArmedForDrag = false;
                if (dto.IsDirectory)
                    NavigateExplorer(dto.FullPath, recordHistory: true);
                else
                    OpenFileWithConfiguredDefaultProgram(dto.FullPath);
                return;
            }

            if ((Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                if (!_selectedExplorerPaths.Remove(dto.FullPath))
                    _selectedExplorerPaths.Add(dto.FullPath);
            }
            else if ((Keyboard.Modifiers & ModifierKeys.Shift) != 0 && !string.IsNullOrEmpty(_lastClickedExplorerPath))
            {
                var currentView = _visibleExplorerEntries.Count > 0 ? _visibleExplorerEntries : _currentExplorerEntries;
                int i1 = currentView.FindIndex(x => string.Equals(x.FullPath, _lastClickedExplorerPath, StringComparison.OrdinalIgnoreCase));
                int i2 = currentView.FindIndex(x => string.Equals(x.FullPath, dto.FullPath, StringComparison.OrdinalIgnoreCase));
                if (i1 >= 0 && i2 >= 0)
                {
                    _selectedExplorerPaths.Clear();
                    for (int k = Math.Min(i1, i2); k <= Math.Max(i1, i2); k++)
                        _selectedExplorerPaths.Add(currentView[k].FullPath);
                }
            }
            else if (!_selectedExplorerPaths.Contains(dto.FullPath))
            {
                _selectedExplorerPaths.Clear();
                _selectedExplorerPaths.Add(dto.FullPath);
            }

            _lastClickedExplorerPath = dto.FullPath;
            _explorerDragOrigin = e.GetPosition(ExplorerWin);
            _explorerMouseArmedForDrag = true;
            UpdateExplorerSelectionAndClipboardFast();
        }

        private void ExplorerEntry_MouseMove(object sender, MouseEventArgs e)
        {
            if (!_explorerMouseArmedForDrag || e.LeftButton != MouseButtonState.Pressed)
            {
                _explorerMouseArmedForDrag = false;
                return;
            }

            var cur = e.GetPosition(ExplorerWin);
            if (Math.Abs(cur.X - _explorerDragOrigin.X) > 7 || Math.Abs(cur.Y - _explorerDragOrigin.Y) > 7)
            {
                _explorerMouseArmedForDrag = false;
                if (_selectedExplorerPaths.Count == 0 && sender is FrameworkElement fe && fe.Tag is OsFileEntryDto single)
                    _selectedExplorerPaths.Add(single.FullPath);

                string[] dragFiles = _selectedExplorerPaths.Where(p => File.Exists(p) || Directory.Exists(p)).ToArray();
                if (dragFiles.Length > 0 && sender is DependencyObject dep)
                {
                    var data = new DataObject(DataFormats.FileDrop, dragFiles);
                    DragDrop.DoDragDrop(dep, data, DragDropEffects.Copy | DragDropEffects.Move);
                }
            }
        }

        private void ExplorerFolderRow_Drop(object sender, DragEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not OsFileEntryDto targetDto || !targetDto.IsDirectory)
                return;

            if (e.Data.GetDataPresent(DataFormats.FileDrop) && e.Data.GetData(DataFormats.FileDrop) is string[] dropped)
            {
                e.Handled = true;
                bool move = (Keyboard.Modifiers & ModifierKeys.Control) == 0;
                TransferPathsIntoDirectory(dropped, targetDto.FullPath, moveInsteadOfCopy: move);
            }
        }

        private void ExplorerContent_Drop(object sender, DragEventArgs e)
        {
            if (e.Handled) return;
            if (e.Data.GetDataPresent(DataFormats.FileDrop) && e.Data.GetData(DataFormats.FileDrop) is string[] dropped)
            {
                e.Handled = true;
                bool copy = (Keyboard.Modifiers & ModifierKeys.Control) != 0;
                TransferPathsIntoDirectory(dropped, _currentExplorerDir, moveInsteadOfCopy: !copy);
            }
        }

        private void ExplorerEmptySpace_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (e.OriginalSource is Grid or ScrollViewer or System.Windows.Controls.ListBox)
            {
                _selectedExplorerPaths.Clear();
                UpdateExplorerSelectionAndClipboardFast();
            }
        }

        private void ExplorerEmptySpace_MouseRightButtonUp(object sender, MouseButtonEventArgs e)
        {
            CloseAllFlyoutsAndMenus(keepContext: true);
            ContextMenuItemsStack.Children.Clear();

            AddContextBtn("📁   Neuer Ordner", () => ExplorerNewFolder_Click(this, new RoutedEventArgs()));
            AddContextBtn("📄   Neue Notiz", () => ExplorerNewNote_Click(this, new RoutedEventArgs()));
            if (_fileClipboardPaths.Count > 0)
                AddContextBtn($"📋   Einfügen ({_fileClipboardPaths.Count})", () => PasteClipboardIntoFolder(_currentExplorerDir));
            try
            {
                if (Clipboard.ContainsImage())
                    AddContextBtn("🖼   Bild einfügen (Strg+V)", () => PasteClipboardImageIntoFolder(_currentExplorerDir));
            }
            catch { }
            AddContextSeparator();
            AddContextBtn("⊞   Große Symbole", () => SwitchExplorerViewIcons_Click(this, new RoutedEventArgs()));
            AddContextBtn("☰   Detail-Tabelle", () => SwitchExplorerViewDetails_Click(this, new RoutedEventArgs()));
            AddContextBtn("↻   Aktualisieren", () => NavigateExplorer(_currentExplorerDir, recordHistory: false));

            PopupContextMenuAt(e.GetPosition(DesktopWorkAreaGrid));
        }

        private void ExplorerEntry_MouseRightButtonUp(object sender, MouseButtonEventArgs e)
        {
            e.Handled = true;
            CloseAllFlyoutsAndMenus(keepContext: true);
            if (sender is not FrameworkElement fe || fe.Tag is not OsFileEntryDto dto) return;

            if (!_selectedExplorerPaths.Contains(dto.FullPath))
            {
                _selectedExplorerPaths.Clear();
                _selectedExplorerPaths.Add(dto.FullPath);
                UpdateExplorerSelectionAndClipboardFast();
            }

            bool inRecycle = _currentExplorerDir.StartsWith(OsVaultAndStorage.OsRecycleBinDir, StringComparison.OrdinalIgnoreCase);
            ContextMenuItemsStack.Children.Clear();

            if (inRecycle)
            {
                AddContextBtn("↩   Wiederherstellen", () =>
                {
                    foreach (string p in _selectedExplorerPaths.ToList())
                    {
                        string restoreDir = OsVaultAndStorage.ResolveAndRemoveRecycleOrigin(Path.GetFileName(p));
                        TransferPathsIntoDirectory(new[] { p }, restoreDir, moveInsteadOfCopy: true);
                    }
                });
                AddContextBtn("✕   Endgültig löschen", () =>
                {
                    foreach (string p in _selectedExplorerPaths.ToList())
                    {
                        try
                        {
                            OsVaultAndStorage.ResolveAndRemoveRecycleOrigin(Path.GetFileName(p));
                            if (Directory.Exists(p)) Directory.Delete(p, true);
                            else if (File.Exists(p)) File.Delete(p);
                        }
                        catch { }
                    }
                    NavigateExplorer(_currentExplorerDir, recordHistory: false);
                    RebuildDesktopIcons();
                });
            }
            else
            {
                string entryExt = Path.GetExtension(dto.FullPath).ToLowerInvariant();
                bool isScriptOrExe = !dto.IsDirectory && entryExt is ".cmd" or ".bat" or ".ps1" or ".exe";

                AddContextBtn(isScriptOrExe ? "▶   Ausführen" : "▶   Öffnen", () =>
                {
                    if (dto.IsDirectory) NavigateExplorer(dto.FullPath, recordHistory: true);
                    else OpenFileWithConfiguredDefaultProgram(dto.FullPath);
                });
                if (isScriptOrExe)
                {
                    AddContextBtn("🛡   Als Administrator ausführen", () => ConfirmAndExecuteElevated(dto.FullPath));
                    if (entryExt is ".cmd" or ".bat" or ".ps1")
                        AddContextBtn("✎   Im Text-Editor bearbeiten", () => OpenTextFileInBuiltInEditor(dto.FullPath));
                }
                if (!dto.IsDirectory)
                {
                    AddContextBtn("⚙   Standardprogramm...", () => { SwitchSettingsTab("defaults"); OpenInternalWindowAnimated("SettingsWin"); });
                }
                AddContextSeparator();
                AddContextBtn("📋   Kopieren", () => SetFileClipboard(_selectedExplorerPaths.ToList(), isCut: false));
                AddContextBtn("✂   Ausschneiden", () => SetFileClipboard(_selectedExplorerPaths.ToList(), isCut: true));
                AddContextBtn("✎   Umbenennen", () => OpenExplorerRenameModal(dto.FullPath));
                AddContextBtn("🔗   Pfad kopieren", () => { try { Clipboard.SetText(dto.FullPath); ShowToast("✓ Pfad kopiert"); } catch { } });
                AddContextBtn("ℹ   Eigenschaften", () => OpenFilePropertiesModal(dto.FullPath));
                AddContextSeparator();
                AddContextBtn("🗑   In Papierkorb", () =>
                {
                    foreach (string p in _selectedExplorerPaths.ToList())
                        MovePathToRecycleBin(p);
                });
            }

            PopupContextMenuAt(e.GetPosition(DesktopWorkAreaGrid));
        }

        private void OpenExplorerRenameModal(string fullPath)
        {
            _expModalRenameTargetFullPath = fullPath;
            ExpModalTitleTxt.Text = "UMBENENNEN";
            ExpModalDetailsTxt.Visibility = Visibility.Collapsed;
            ExpModalInputBox.Visibility = Visibility.Visible;
            ExpModalConfirmBtn.Visibility = Visibility.Visible;
            ExpModalInputBox.Text = Path.GetFileName(fullPath);
            ExplorerModalOverlay.Visibility = Visibility.Visible;
            ExpModalInputBox.Focus();
            ExpModalInputBox.SelectAll();
        }

        private void OpenFilePropertiesModal(string fullPath)
        {
            OpenInternalWindowAnimated("ExplorerWin");
            _expModalRenameTargetFullPath = null;
            ExpModalTitleTxt.Text = "EIGENSCHAFTEN";
            ExpModalInputBox.Visibility = Visibility.Collapsed;
            ExpModalConfirmBtn.Visibility = Visibility.Collapsed;
            ExpModalDetailsTxt.Visibility = Visibility.Visible;

            try
            {
                if (Directory.Exists(fullPath))
                {
                    var di = new DirectoryInfo(fullPath);
                    int files = 0, dirs = 0;
                    try { files = di.GetFiles().Length; dirs = di.GetDirectories().Length; } catch { }
                    ExpModalDetailsTxt.Text =
                        $"Name:  {di.Name}\n" +
                        $"Typ:  Ordner ({dirs} Unterordner, {files} Dateien)\n" +
                        $"Ort:  {di.Parent?.FullName}\n" +
                        $"Erstellt am:  {di.CreationTime:dd.MM.yyyy HH:mm:ss}\n" +
                        $"Geändert am:  {di.LastWriteTime:dd.MM.yyyy HH:mm:ss}";
                }
                else if (File.Exists(fullPath))
                {
                    var fi = new FileInfo(fullPath);
                    string shaShort = "-";
                    if (fi.Length <= 50 * 1024 * 1024)
                    {
                        using var fs = File.OpenRead(fullPath);
                        byte[] hash = SHA256.HashData(fs);
                        shaShort = Convert.ToHexString(hash).Substring(0, 20) + "…";
                    }
                    ExpModalDetailsTxt.Text =
                        $"Name:  {fi.Name}\n" +
                        $"Typ:  {fi.Extension.ToUpperInvariant()}\n" +
                        $"Größe:  {FormatBytes(fi.Length)} ({fi.Length:N0} Bytes)\n" +
                        $"Ort:  {fi.DirectoryName}\n" +
                        $"Erstellt am:  {fi.CreationTime:dd.MM.yyyy HH:mm:ss}\n" +
                        $"Geändert am:  {fi.LastWriteTime:dd.MM.yyyy HH:mm:ss}\n" +
                        $"SHA-256:  {shaShort}";
                }
            }
            catch { }

            ExplorerModalOverlay.Visibility = Visibility.Visible;
        }

        private void ExpModalConfirm_Click(object sender, RoutedEventArgs e)
        {
            if (!string.IsNullOrEmpty(_modalCustomRenameItemId))
            {
                string clean = ExpModalInputBox.Text.Trim();
                if (!string.IsNullOrWhiteSpace(clean))
                {
                    _settings.CustomDisplayNames[_modalCustomRenameItemId] = clean;
                    OsVaultAndStorage.SaveSettings(_settings);
                    RebuildDesktopIcons();
                    RefreshStartMenuApps(StartSearchBox.Text);
                    RefreshTaskbarStrip();
                    ShowToast($"✓ Umbenannt in '{clean}'");
                }
                _modalCustomRenameItemId = null;
            }
            else if (!string.IsNullOrEmpty(_expModalRenameTargetFullPath))
            {
                RenameFileSystemEntry(_expModalRenameTargetFullPath, ExpModalInputBox.Text);
            }
            ExplorerModalOverlay.Visibility = Visibility.Collapsed;
        }

        private void ExpModalInputBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter) { e.Handled = true; ExpModalConfirm_Click(sender, e); }
            else if (e.Key == Key.Escape) { e.Handled = true; ExplorerModalOverlay.Visibility = Visibility.Collapsed; }
        }

        private void ExpModalClose_Click(object sender, RoutedEventArgs e) => ExplorerModalOverlay.Visibility = Visibility.Collapsed;

        private void ExplorerNewFolder_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                if (string.Equals(Path.GetFullPath(_currentExplorerDir).TrimEnd('\\'), Path.GetFullPath(OsVaultAndStorage.MeineDatenRootDir).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase))
                {
                    ShowToast("✓ 'Meine Daten' besitzt die feste 8-Säulen-Struktur. Bitte eine Säule öffnen.");
                    return;
                }

                string path = GetUniquePathInFolder(_currentExplorerDir, "Neuer Ordner", isFolder: true);
                Directory.CreateDirectory(path);
                NavigateExplorer(_currentExplorerDir, recordHistory: false);
                RebuildDesktopIcons();
                OpenExplorerRenameModal(path);
            }
            catch { }
        }

        private void ExplorerNewNote_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                string targetDir = _currentExplorerDir;
                if (string.Equals(Path.GetFullPath(targetDir).TrimEnd('\\'), Path.GetFullPath(OsVaultAndStorage.MeineDatenRootDir).TrimEnd('\\'), StringComparison.OrdinalIgnoreCase))
                {
                    targetDir = Path.Combine(OsVaultAndStorage.MeineDatenRootDir, "01_Mein-Seelenfunke", "Eingangsrechnungen_Belege");
                    Directory.CreateDirectory(targetDir);
                    NavigateExplorer(targetDir, recordHistory: true);
                }

                string path = GetUniquePathInFolder(targetDir, "Notiz.txt");
                File.WriteAllText(path, "", System.Text.Encoding.UTF8);
                NavigateExplorer(targetDir, recordHistory: false);
                RebuildDesktopIcons();
                OpenTextFileInBuiltInEditor(path);
            }
            catch { }
        }

        private static string FormatBytes(long b)
        {
            if (b < 1024) return $"{b} B";
            if (b < 1024 * 1024) return $"{b / 1024.0:0.0} KB";
            if (b < 1024L * 1024L * 1024L) return $"{b / (1024.0 * 1024.0):0.0} MB";
            return $"{b / (1024.0 * 1024.0 * 1024.0):0.00} GB";
        }

        // =====================================================================
        // 7. DEFAULT PROGRAMS & BUILT-IN VIEWER / EDITOR
        // =====================================================================
        private void OpenFileWithConfiguredDefaultProgram(string fullPath)
        {
            if (!File.Exists(fullPath)) return;
            string ext = Path.GetExtension(fullPath).ToLowerInvariant();

            if (ext is ".html" or ".htm" or ".url")
            {
                OsHardwareAndAudioEngine.LaunchOrFocusCollectionApp("browser", fullPath);
                return;
            }
            if (ext == ".pdf")
            {
                OsHardwareAndAudioEngine.LaunchOrFocusCollectionApp(_settings.DefaultPrograms.GetValueOrDefault("pdf", "pdf"), fullPath);
                return;
            }
            if (ext is ".kdbx" or ".keyx")
            {
                OsHardwareAndAudioEngine.LaunchOrFocusCollectionApp("tresor", fullPath);
                return;
            }
            if (ext is ".mp3" or ".wav" or ".flac" or ".m4a" or ".ogg")
            {
                OsHardwareAndAudioEngine.LaunchOrFocusCollectionApp(_settings.DefaultPrograms.GetValueOrDefault("audio", "player"), fullPath);
                return;
            }
            if (ext is ".mp4" or ".mkv" or ".mov" or ".webm")
            {
                OsHardwareAndAudioEngine.LaunchOrFocusCollectionApp(_settings.DefaultPrograms.GetValueOrDefault("video", "player"), fullPath);
                return;
            }
            if (ext is ".png" or ".jpg" or ".jpeg" or ".webp" or ".bmp")
            {
                if (_settings.DefaultPrograms.GetValueOrDefault("image", "internal_image") == "drucker")
                {
                    OsHardwareAndAudioEngine.LaunchOrFocusCollectionApp("drucker", fullPath);
                    return;
                }
                OpenImageInBuiltInLightbox(fullPath);
                return;
            }

            if (ext is ".cmd" or ".bat" or ".ps1" or ".exe" or ".lnk")
            {
                ExecuteLocalScriptOrExecutable(fullPath, confirmedRunAsAdmin: false);
                return;
            }

            OpenTextFileInBuiltInEditor(fullPath);
        }

        private void ConfirmAndExecuteElevated(string fullPath)
        {
            if (!File.Exists(fullPath)) return;
            string fileName = Path.GetFileName(fullPath);
            if (MessageBox.Show(
                    $"Soll '{fileName}' mit Administratorrechten ausgeführt werden?",
                    "Administrator-Bestätigung",
                    MessageBoxButton.YesNo,
                    MessageBoxImage.Warning) != MessageBoxResult.Yes)
            {
                return;
            }

            ExecuteLocalScriptOrExecutable(fullPath, confirmedRunAsAdmin: true);
        }

        /// <summary>
        /// Builds a safe ProcessStartInfo without cmd.exe /c string interpolation, without
        /// -ExecutionPolicy Bypass, and without automatic keyword-based Verb="runas" elevation.
        /// </summary>
        public static ProcessStartInfo BuildSafeLocalScriptOrExeStartInfo(string fullPath, bool confirmedRunAsAdmin)
        {
            if (string.IsNullOrWhiteSpace(fullPath))
                throw new ArgumentException("Executable path must not be empty.", nameof(fullPath));

            string ext = Path.GetExtension(fullPath).ToLowerInvariant();
            string workDir = Path.GetDirectoryName(fullPath) ?? "";

            ProcessStartInfo psi;
            if (ext == ".ps1")
            {
                psi = new ProcessStartInfo
                {
                    FileName = "powershell.exe",
                    Arguments = $"-NoProfile -File \"{fullPath}\"",
                    WorkingDirectory = workDir,
                    UseShellExecute = true
                };
            }
            else
            {
                psi = new ProcessStartInfo
                {
                    FileName = fullPath,
                    WorkingDirectory = workDir,
                    UseShellExecute = true
                };
            }

            if (confirmedRunAsAdmin && ext != ".lnk")
                psi.Verb = "runas";

            return psi;
        }

        private void ExecuteLocalScriptOrExecutable(string fullPath, bool confirmedRunAsAdmin)
        {
            try
            {
                if (!File.Exists(fullPath)) return;
                var psi = BuildSafeLocalScriptOrExeStartInfo(fullPath, confirmedRunAsAdmin);
                Process.Start(psi);
                ShowToast($"✓ Starte '{Path.GetFileName(fullPath)}'...");
            }
            catch (Exception ex)
            {
                ShowToast($"Start abgebrochen: {ex.Message}");
            }
        }

        private void OpenTextFileInBuiltInEditor(string filePath)
        {
            try
            {
                _openNoteFilePath = filePath;
                _currentLightboxImagePath = null;
                ViewerTitleTxt.Text = $"✦  {Path.GetFileName(filePath)}";
                SaveNoteBtn.Visibility = Visibility.Visible;
                LightboxToolbarPanel.Visibility = Visibility.Collapsed;
                LightboxImageHost.Visibility = Visibility.Collapsed;
                NoteEditorTextBox.Visibility = Visibility.Visible;
                NoteEditorTextBox.Text = File.ReadAllText(filePath);
                OpenInternalWindowAnimated("ViewerWin");
            }
            catch { }
        }

        private void OpenImageInBuiltInLightbox(string imagePath)
        {
            try
            {
                _openNoteFilePath = null;
                _currentLightboxImagePath = imagePath;
                _currentLightboxZoom = 1.0;
                LightboxImgScale.ScaleX = 1.0;
                LightboxImgScale.ScaleY = 1.0;
                LightboxZoomResetBtn.Content = "100%";
                ViewerTitleTxt.Text = $"✦  {Path.GetFileName(imagePath)}";
                SaveNoteBtn.Visibility = Visibility.Collapsed;
                LightboxToolbarPanel.Visibility = Visibility.Visible;
                NoteEditorTextBox.Visibility = Visibility.Collapsed;
                LightboxImageHost.Visibility = Visibility.Visible;

                var bi = new BitmapImage();
                bi.BeginInit();
                bi.CacheOption = BitmapCacheOption.OnLoad;
                bi.UriSource = new Uri(imagePath, UriKind.Absolute);
                bi.EndInit();
                bi.Freeze();
                LightboxPreviewImg.Source = bi;
                OpenInternalWindowAnimated("ViewerWin");
            }
            catch { }
        }

        private void LightboxPrevImg_Click(object sender, RoutedEventArgs e) => StepLightboxImage(-1);
        private void LightboxNextImg_Click(object sender, RoutedEventArgs e) => StepLightboxImage(+1);

        private void StepLightboxImage(int delta)
        {
            if (string.IsNullOrEmpty(_currentLightboxImagePath) || !File.Exists(_currentLightboxImagePath)) return;
            try
            {
                string? dir = Path.GetDirectoryName(_currentLightboxImagePath);
                if (string.IsNullOrEmpty(dir) || !Directory.Exists(dir)) return;

                var images = Directory.EnumerateFiles(dir)
                    .Where(f => Path.GetExtension(f).ToLowerInvariant() is ".png" or ".jpg" or ".jpeg" or ".webp" or ".bmp")
                    .OrderBy(f => Path.GetFileName(f), StringComparer.OrdinalIgnoreCase)
                    .ToList();

                if (images.Count <= 1) return;
                int idx = images.FindIndex(f => string.Equals(f, _currentLightboxImagePath, StringComparison.OrdinalIgnoreCase));
                if (idx < 0) idx = 0;
                int nextIdx = (idx + delta + images.Count) % images.Count;
                OpenImageInBuiltInLightbox(images[nextIdx]);
            }
            catch { }
        }

        private void LightboxZoomReset_Click(object sender, RoutedEventArgs e)
        {
            _currentLightboxZoom = 1.0;
            LightboxImgScale.ScaleX = 1.0;
            LightboxImgScale.ScaleY = 1.0;
            LightboxZoomResetBtn.Content = "100%";
        }

        private void LightboxImageHost_MouseWheel(object sender, MouseWheelEventArgs e)
        {
            if (LightboxImageHost.Visibility != Visibility.Visible) return;
            e.Handled = true;
            double step = e.Delta > 0 ? 0.15 : -0.15;
            _currentLightboxZoom = Math.Clamp(_currentLightboxZoom + step, 0.5, 4.0);
            LightboxImgScale.ScaleX = _currentLightboxZoom;
            LightboxImgScale.ScaleY = _currentLightboxZoom;
            LightboxZoomResetBtn.Content = $"{(int)Math.Round(_currentLightboxZoom * 100)}%";
        }

        private void ViewerWin_DragOver(object sender, DragEventArgs e)
        {
            e.Effects = e.Data.GetDataPresent(DataFormats.FileDrop) ? DragDropEffects.Copy : DragDropEffects.None;
            e.Handled = true;
        }

        private void ViewerWin_Drop(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent(DataFormats.FileDrop) && e.Data.GetData(DataFormats.FileDrop) is string[] dropped && dropped.Length > 0)
            {
                e.Handled = true;
                OpenFileWithConfiguredDefaultProgram(dropped[0]);
            }
        }

        private void SaveOpenNote_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrEmpty(_openNoteFilePath)) return;
            try
            {
                File.WriteAllText(_openNoteFilePath, NoteEditorTextBox.Text, new System.Text.UTF8Encoding(false));
                ShowToast("✓ Gespeichert");
                RebuildDesktopIcons();
                if (ExplorerWin.Visibility == Visibility.Visible)
                    NavigateExplorer(_currentExplorerDir, recordHistory: false);
            }
            catch { }
        }

        private void UpdateDefaultProgramsButtonsUi()
        {
            Style gold = (Style)Resources["NobleGoldBtn"];
            Style glass = (Style)Resources["GlassBtn"];

            string pdf = _settings.DefaultPrograms.GetValueOrDefault("pdf", "pdf");
            DefPdfAppBtn.Style = pdf == "pdf" ? gold : glass;
            DefPdfPrintBtn.Style = pdf == "drucker" ? gold : glass;

            string aud = _settings.DefaultPrograms.GetValueOrDefault("audio", "player");
            DefAudioPlayerBtn.Style = aud == "player" ? gold : glass;
            DefAudioSuiteBtn.Style = aud == "funkenwerk" ? gold : glass;

            string vid = _settings.DefaultPrograms.GetValueOrDefault("video", "player");
            DefVideoPlayerBtn.Style = vid == "player" ? gold : glass;
            DefVideoStudioBtn.Style = vid == "video" ? gold : glass;

            string img = _settings.DefaultPrograms.GetValueOrDefault("image", "internal_image");
            DefImgInternalBtn.Style = img == "internal_image" ? gold : glass;
            DefImgDruckerBtn.Style = img == "drucker" ? gold : glass;
        }

        private void SelectDefaultProgram_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string spec) return;
            string[] parts = spec.Split('|');
            if (parts.Length != 2) return;

            _settings.DefaultPrograms[parts[0]] = parts[1];
            OsVaultAndStorage.SaveSettings(_settings);
            UpdateDefaultProgramsButtonsUi();
            RebuildDesktopIcons();
            ShowToast("✓ Standardprogramm gesetzt");
        }

        private void SyncSystemDefaultPrograms_Click(object sender, RoutedEventArgs e)
        {
            bool ok = OsHardwareAndAudioEngine.ApplyWindowsDefaultAssociations(_settings);
            ShowToast(ok ? "✓ System-Dateizuordnungen aktualisiert" : "Zuordnung fehlgeschlagen");
        }

        // =====================================================================
        // 8. FLOATING OS WINDOWS (RESIZE, SNAP, MAXIMIZE, MINIMIZE)
        // =====================================================================
        private Border? ResolveInternalWindow(string key) => key switch
        {
            "ExplorerWin" => ExplorerWin,
            "SettingsWin" => SettingsWin,
            "LeitstandWin" => LeitstandWin,
            "ViewerWin" => ViewerWin,
            "CalculatorWin" => CalculatorWin,
            "SnipWin" => SnipWin,
            "NotesWin" => NotesWin,
            _ => null
        };

        private void OpenInternalWindowAnimated(string key)
        {
            CloseAllFlyoutsAndMenus();
            var win = ResolveInternalWindow(key);
            if (win == null) return;

            _minimizedInternalWins.Remove(key);
            Panel.SetZIndex(win, ++_topWindowZIndex);

            if (key == "CalculatorWin") CalcWinTitleTxt.Text = $"✦  {ResolveCustomTitle("sys:calculator", "Taschenrechner").ToUpperInvariant()}";
            else if (key == "SnipWin") SnipWinTitleTxt.Text = $"✦  {ResolveCustomTitle("sys:snip", "Ausschneiden").ToUpperInvariant()}";
            else if (key == "NotesWin") NotesWinTitleTxt.Text = $"✦  {ResolveCustomTitle("sys:notes", "Notizen").ToUpperInvariant()}";

            if (win.Visibility != Visibility.Visible)
            {
                win.Visibility = Visibility.Visible;
                if (win.RenderTransform is ScaleTransform st)
                {
                    st.ScaleX = 0.94;
                    st.ScaleY = 0.94;
                    AnimateScale(st, 1.0, 165);
                }
                AnimateOpacity(win, 0.0, 1.0, 150);
            }

            if (key == "SettingsWin")
            {
                RefreshFortressAuditUi();
                AutoPopulateUsbDrivePath();
            }
            else if (key == "ExplorerWin" && _currentExplorerEntries.Count == 0)
            {
                NavigateExplorer(_currentExplorerDir, recordHistory: false);
            }
            else if (key == "NotesWin")
            {
                ActiveStickyTextBox.Focus();
            }

            _lastTaskbarSignature = "";
            RefreshTaskbarStrip();
        }

        private void FloatingWindow_ActivateMouseDown(object sender, MouseButtonEventArgs e)
        {
            CloseAllFlyoutsAndMenus();
            if (sender is Border win)
                Panel.SetZIndex(win, ++_topWindowZIndex);
        }

        private void WindowHeader_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string key) return;
            var win = ResolveInternalWindow(key);
            if (win == null) return;

            Panel.SetZIndex(win, ++_topWindowZIndex);
            if (e.ClickCount == 2)
            {
                ToggleMaximizeInternalWindow(key, win);
                e.Handled = true;
                return;
            }

            var mousePos = e.GetPosition(DesktopWorkAreaGrid);
            if (_preMaximizeBounds.TryGetValue(key, out Rect prev))
            {
                double curW = Math.Max(200, win.ActualWidth);
                double relX = Math.Clamp((mousePos.X - Canvas.GetLeft(win)) / curW, 0.15, 0.85);
                win.Width = prev.Width;
                win.Height = prev.Height;
                Canvas.SetLeft(win, Math.Clamp(mousePos.X - prev.Width * relX, 4, Math.Max(20, DesktopWorkAreaGrid.ActualWidth - prev.Width - 8)));
                Canvas.SetTop(win, Math.Max(4, mousePos.Y - 22));
                _preMaximizeBounds.Remove(key);
            }

            _draggingWinBorder = win;
            _draggingWinKey = key;
            _activeAeroSnapZone = null;
            _winDragStartMouse = mousePos;
            _winDragStartLeft = Canvas.GetLeft(win);
            _winDragStartTop = Canvas.GetTop(win);
            DesktopWorkAreaGrid.CaptureMouse();
            e.Handled = true;
        }

        private void WindowResizeGrip_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string key) return;
            var win = ResolveInternalWindow(key);
            if (win == null) return;

            Panel.SetZIndex(win, ++_topWindowZIndex);
            _resizingWinBorder = win;
            _winResizeStartMouse = e.GetPosition(DesktopWorkAreaGrid);
            _winResizeStartW = win.ActualWidth;
            _winResizeStartH = win.ActualHeight;
            DesktopWorkAreaGrid.CaptureMouse();
            e.Handled = true;
        }

        private void SnapInternalWin_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string spec) return;
            string[] parts = spec.Split('|');
            if (parts.Length != 2) return;
            var win = ResolveInternalWindow(parts[0]);
            if (win == null) return;

            double fullW = Math.Max(800, DesktopWorkAreaGrid.ActualWidth);
            double fullH = Math.Max(500, DesktopWorkAreaGrid.ActualHeight);
            double halfW = (fullW - 18) * 0.5;

            if (!_preMaximizeBounds.ContainsKey(parts[0]))
                _preMaximizeBounds[parts[0]] = new Rect(Canvas.GetLeft(win), Canvas.GetTop(win), win.Width, win.Height);

            Canvas.SetLeft(win, parts[1] == "Left" ? 6 : (6 + halfW + 6));
            Canvas.SetTop(win, 6);
            win.Width = halfW;
            win.Height = fullH - 12;
        }

        private void MinimizeInternalWin_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string key) return;
            var win = ResolveInternalWindow(key);
            if (win == null) return;
            _minimizedInternalWins.Add(key);
            win.Visibility = Visibility.Collapsed;
            _lastTaskbarSignature = "";
            RefreshTaskbarStrip();
        }

        private void MaximizeInternalWin_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string key) return;
            var win = ResolveInternalWindow(key);
            if (win != null) ToggleMaximizeInternalWindow(key, win);
        }

        private void ToggleMaximizeInternalWindow(string key, Border win)
        {
            if (_preMaximizeBounds.TryGetValue(key, out Rect prev))
            {
                Canvas.SetLeft(win, prev.Left);
                Canvas.SetTop(win, prev.Top);
                win.Width = prev.Width;
                win.Height = prev.Height;
                _preMaximizeBounds.Remove(key);
            }
            else
            {
                _preMaximizeBounds[key] = new Rect(Canvas.GetLeft(win), Canvas.GetTop(win), win.Width, win.Height);
                Canvas.SetLeft(win, 6);
                Canvas.SetTop(win, 6);
                win.Width = Math.Max(680, DesktopWorkAreaGrid.ActualWidth - 12);
                win.Height = Math.Max(420, DesktopWorkAreaGrid.ActualHeight - 12);
            }
        }

        private void CloseInternalWin_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string key) return;
            var win = ResolveInternalWindow(key);
            if (win == null) return;
            _minimizedInternalWins.Remove(key);
            win.Visibility = Visibility.Collapsed;
            if (key == "SnipWin")
                DiscardCurrentSnipFromMemory();
            _lastTaskbarSignature = "";
            RefreshTaskbarStrip();
        }

        private void ClampFloatingWindowsToBounds()
        {
            double maxW = DesktopWorkAreaGrid.ActualWidth;
            double maxH = DesktopWorkAreaGrid.ActualHeight;
            if (maxW < 200 || maxH < 200) return;

            foreach (var win in new[] { ExplorerWin, SettingsWin, LeitstandWin, ViewerWin, CalculatorWin, SnipWin, NotesWin })
            {
                if (Canvas.GetLeft(win) > maxW - 120) Canvas.SetLeft(win, Math.Max(8, maxW - win.Width - 16));
                if (Canvas.GetTop(win) > maxH - 80) Canvas.SetTop(win, Math.Max(8, maxH - win.Height - 16));
            }
        }

        // =====================================================================
        // 9. TASKBAR, START MENU & BOTTOM-RIGHT TRAY FLYOUTS
        // =====================================================================
        private void TickSystemClockAndTray()
        {
            DateTime now = DateTime.Now;
            TrayClockTimeTxt.Text = now.ToString("HH:mm");
            TrayClockDateTxt.Text = now.ToString("dd.MM.yyyy");
            LockClockTxt.Text = now.ToString("HH:mm");
            LockDateTxt.Text = now.ToString("dddd, dd. MMMM yyyy", DeCulture);

            if (CalendarFlyout.Visibility == Visibility.Visible)
            {
                CalLargeClockTxt.Text = now.ToString("HH:mm:ss");
                CalFullDateTxt.Text = now.ToString("dddd, dd. MMMM yyyy", DeCulture);
            }

            _secondaryWallWindow?.TickClock();
            OsHardwareAndAudioEngine.TriggerBackgroundTrayRefresh();
            var (netOk, _, _, _) = OsHardwareAndAudioEngine.CachedNetworkSummary;
            TrayWifiPath.Stroke = netOk ? (Brush)Resources["TextPrimaryBrush"] : Solid("#EF4444");

            RefreshAudioUi(forceDirectRead: false);
            RefreshTaskbarStrip();
        }

        private void RefreshTaskbarStrip()
        {
            if (TaskbarWindowsStrip == null) return;

            var items = new List<OsOpenWindowDto>();
            bool isVertical = _settings.TaskbarPosition is "Left" or "Right";
            Visibility labelVis = isVertical ? Visibility.Collapsed : Visibility.Visible;

            var collectionMap = OsHardwareAndAudioEngine.DiscoverCollectionApps()
                .ToDictionary(a => a.Key, a => a, StringComparer.OrdinalIgnoreCase);
            var openExtWindows = OsHardwareAndAudioEngine.CachedOpenExtWindows;
            var consumedExtHwnds = new HashSet<IntPtr>();
            var consumedInternalKeys = new HashSet<string>(StringComparer.OrdinalIgnoreCase);

            (string InternalWinKey, string DefaultTitle, string BadgeKey)? ResolveSysMeta(string sysId) => sysId.ToLowerInvariant() switch
            {
                "sys:gewerbe" => ("GewerbeMonitorWin", "Monitor Gewerbebereich", "gewerbe"),
                "sys:meinedaten" => ("MeineDatenDir", "Meine Daten", "meinedaten"),
                "sys:explorer" => ("ExplorerWin", "Dateien", "explorer"),
                "sys:calculator" => ("CalculatorWin", "Taschenrechner", "calculator"),
                "sys:snip" => ("SnipWin", "Ausschneiden", "snip"),
                "sys:notes" => ("NotesWin", "Notizen", "notes"),
                "sys:leitstand" => ("LeitstandWin", "Leitstand", "leitstand"),
                "sys:settings" => ("SettingsWin", "Einstellungen", "settings"),
                _ => null
            };

            bool IsInternalOpen(string internalKey, out bool isActive)
            {
                if (internalKey == "GewerbeMonitorWin")
                {
                    bool gwOpen = _gewerbeMonitorWindow != null && _gewerbeMonitorWindow.IsVisible;
                    isActive = gwOpen && _gewerbeMonitorWindow!.WindowState != WindowState.Minimized;
                    return gwOpen;
                }
                if (internalKey == "MeineDatenDir")
                {
                    bool expOpen = ExplorerWin.Visibility == Visibility.Visible || _minimizedInternalWins.Contains("ExplorerWin");
                    bool inMeineDaten = _currentExplorerDir.StartsWith(OsVaultAndStorage.MeineDatenRootDir, StringComparison.OrdinalIgnoreCase);
                    isActive = ExplorerWin.Visibility == Visibility.Visible && inMeineDaten;
                    return expOpen && inMeineDaten;
                }
                var win = ResolveInternalWindow(internalKey);
                isActive = win != null && win.Visibility == Visibility.Visible;
                return win != null && (win.Visibility == Visibility.Visible || _minimizedInternalWins.Contains(internalKey));
            }

            // 1. Pinned Favorites in Taskbar
            foreach (string pinnedId in _settings.PinnedTaskbarAppIds.Distinct(StringComparer.OrdinalIgnoreCase))
            {
                if (pinnedId.StartsWith("app:", StringComparison.OrdinalIgnoreCase))
                {
                    string appKey = pinnedId.Substring(4);
                    if (!collectionMap.TryGetValue(appKey, out var appMeta)) continue;

                    var extWin = openExtWindows.FirstOrDefault(w => string.Equals(w.AppKey, appKey, StringComparison.OrdinalIgnoreCase));
                    if (extWin != null && extWin.Hwnd != IntPtr.Zero)
                        consumedExtHwnds.Add(extWin.Hwnd);

                    string label = ResolveCustomTitle(pinnedId, appMeta.Title);
                    items.Add(new OsOpenWindowDto
                    {
                        ShortcutId = pinnedId,
                        AppKey = appKey,
                        AppLabel = label,
                        WindowTitle = extWin != null && !string.IsNullOrWhiteSpace(extWin.WindowTitle) ? extWin.WindowTitle : label,
                        IconImage = appMeta.IconImage,
                        Hwnd = extWin?.Hwnd ?? IntPtr.Zero,
                        IsPinned = true,
                        IsRunning = extWin != null,
                        IsActive = extWin != null,
                        LabelVisibility = labelVis
                    });
                }
                else if (pinnedId.StartsWith("sys:", StringComparison.OrdinalIgnoreCase))
                {
                    var sysMeta = ResolveSysMeta(pinnedId);
                    if (sysMeta == null) continue;
                    var (internalKey, defTitle, badgeKey) = sysMeta.Value;
                    bool running = IsInternalOpen(internalKey, out bool active);
                    consumedInternalKeys.Add(internalKey);

                    string label = ResolveCustomTitle(pinnedId, defTitle);
                    items.Add(new OsOpenWindowDto
                    {
                        ShortcutId = pinnedId,
                        InternalWindowKey = internalKey,
                        AppLabel = label,
                        WindowTitle = label,
                        IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon(badgeKey),
                        IsPinned = true,
                        IsRunning = running,
                        IsActive = active,
                        LabelVisibility = labelVis
                    });
                }
            }

            // 2. Unpinned Open Internal Windows
            void AddUnpinnedInternal(string shortcutId, string key, Border win, string label, string badge)
            {
                if (consumedInternalKeys.Contains(key)) return;
                if (win.Visibility == Visibility.Visible || _minimizedInternalWins.Contains(key))
                {
                    items.Add(new OsOpenWindowDto
                    {
                        ShortcutId = shortcutId,
                        InternalWindowKey = key,
                        AppLabel = label,
                        WindowTitle = label,
                        IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon(badge),
                        IsPinned = false,
                        IsRunning = true,
                        IsActive = win.Visibility == Visibility.Visible,
                        LabelVisibility = labelVis
                    });
                }
            }

            if (!consumedInternalKeys.Contains("GewerbeMonitorWin") && _gewerbeMonitorWindow != null && _gewerbeMonitorWindow.IsVisible)
            {
                string gwLabel = ResolveCustomTitle("sys:gewerbe", "Monitor Gewerbebereich");
                items.Add(new OsOpenWindowDto
                {
                    ShortcutId = "sys:gewerbe",
                    InternalWindowKey = "GewerbeMonitorWin",
                    AppLabel = gwLabel,
                    WindowTitle = gwLabel,
                    IconImage = OsHardwareAndAudioEngine.GetSystemBadgeIcon("gewerbe"),
                    IsPinned = false,
                    IsRunning = true,
                    IsActive = _gewerbeMonitorWindow.WindowState != WindowState.Minimized,
                    LabelVisibility = labelVis
                });
            }

            AddUnpinnedInternal("sys:explorer", "ExplorerWin", ExplorerWin, ResolveCustomTitle("sys:explorer", "Dateien"), "explorer");
            AddUnpinnedInternal("sys:calculator", "CalculatorWin", CalculatorWin, ResolveCustomTitle("sys:calculator", "Taschenrechner"), "calculator");
            AddUnpinnedInternal("sys:snip", "SnipWin", SnipWin, ResolveCustomTitle("sys:snip", "Ausschneiden"), "snip");
            AddUnpinnedInternal("sys:notes", "NotesWin", NotesWin, ResolveCustomTitle("sys:notes", "Notizen"), "notes");
            AddUnpinnedInternal("sys:leitstand", "LeitstandWin", LeitstandWin, ResolveCustomTitle("sys:leitstand", "Leitstand"), "leitstand");
            AddUnpinnedInternal("sys:settings", "SettingsWin", SettingsWin, ResolveCustomTitle("sys:settings", "Einstellungen"), "settings");
            AddUnpinnedInternal("", "ViewerWin", ViewerWin, ViewerTitleTxt.Text.Replace("✦  ", ""), "file_text");

            // 3. Unpinned Open Collection App Windows
            foreach (var extWin in openExtWindows)
            {
                if (extWin.Hwnd != IntPtr.Zero && consumedExtHwnds.Contains(extWin.Hwnd))
                    continue;

                if (!string.IsNullOrEmpty(extWin.AppKey))
                {
                    extWin.ShortcutId = $"app:{extWin.AppKey}";
                    extWin.AppLabel = ResolveCustomTitle(extWin.ShortcutId, extWin.AppLabel);
                }
                extWin.IsPinned = false;
                extWin.IsRunning = true;
                extWin.LabelVisibility = labelVis;
                items.Add(extWin);
            }

            string sig = string.Join(";;", items.Select(x => $"{x.ShortcutId}|{x.InternalWindowKey}|{x.Hwnd}|{x.IsPinned}|{x.IsRunning}|{x.IsActive}|{x.AppLabel}|{x.WindowTitle}|{(int)x.LabelVisibility}"));
            if (!string.Equals(sig, _lastTaskbarSignature, StringComparison.Ordinal))
            {
                _lastTaskbarSignature = sig;
                TaskbarWindowsStrip.ItemsSource = items;
            }
        }

        private void TaskbarWindowItem_LeftClick(object sender, MouseButtonEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not OsOpenWindowDto dto) return;
            CloseAllFlyoutsAndMenus();

            if (!string.IsNullOrEmpty(dto.InternalWindowKey))
            {
                if (dto.InternalWindowKey == "GewerbeMonitorWin")
                {
                    OpenOrFocusGewerbeMonitor();
                    RefreshTaskbarStrip();
                    return;
                }

                if (dto.InternalWindowKey == "MeineDatenDir")
                {
                    OsVaultAndStorage.EnsureMeineDatenStructure();
                    NavigateExplorer(OsVaultAndStorage.MeineDatenRootDir, recordHistory: true);
                    OpenInternalWindowAnimated("ExplorerWin");
                    RefreshTaskbarStrip();
                    return;
                }

                var win = ResolveInternalWindow(dto.InternalWindowKey);
                if (win == null) return;
                if (win.Visibility == Visibility.Visible && Panel.GetZIndex(win) == _topWindowZIndex)
                {
                    _minimizedInternalWins.Add(dto.InternalWindowKey);
                    win.Visibility = Visibility.Collapsed;
                }
                else
                {
                    OpenInternalWindowAnimated(dto.InternalWindowKey);
                }
                RefreshTaskbarStrip();
                return;
            }

            if (dto.Hwnd != IntPtr.Zero)
            {
                OsHardwareAndAudioEngine.ToggleOrFocusWindowHandle(dto.Hwnd);
                return;
            }

            if (!string.IsNullOrEmpty(dto.AppKey))
            {
                OsHardwareAndAudioEngine.LaunchOrFocusCollectionApp(dto.AppKey);
            }
        }

        private void TaskbarWindowItem_RightClick(object sender, MouseButtonEventArgs e)
        {
            e.Handled = true;
            if (sender is not FrameworkElement fe || fe.Tag is not OsOpenWindowDto dto) return;

            CloseAllFlyoutsAndMenus(keepContext: true);
            ContextMenuItemsStack.Children.Clear();

            AddContextBtn("▶   Öffnen", () => TaskbarWindowItem_LeftClick(fe, e));

            if (!string.IsNullOrEmpty(dto.ShortcutId))
            {
                bool pinned = IsAppPinnedToTaskbar(dto.ShortcutId);
                AddContextBtn(pinned ? "📌   Von Taskleiste lösen" : "📌   An Taskleiste anheften", () => TogglePinAppToTaskbar(dto.ShortcutId, dto.AppLabel));
            }

            if (dto.IsRunning)
            {
                AddContextSeparator();
                AddContextBtn("✕   Fenster schließen", () =>
                {
                    if (!string.IsNullOrEmpty(dto.InternalWindowKey))
                    {
                        if (dto.InternalWindowKey == "GewerbeMonitorWin")
                        {
                            _gewerbeMonitorWindow?.Close();
                            _gewerbeMonitorWindow = null;
                        }
                        else
                        {
                            string winKey = dto.InternalWindowKey == "MeineDatenDir" ? "ExplorerWin" : dto.InternalWindowKey;
                            var win = ResolveInternalWindow(winKey);
                            if (win != null) win.Visibility = Visibility.Collapsed;
                            _minimizedInternalWins.Remove(winKey);
                            if (winKey == "SnipWin") DiscardCurrentSnipFromMemory();
                        }
                    }
                    else if (dto.Hwnd != IntPtr.Zero)
                    {
                        OsHardwareAndAudioEngine.CloseWindowHandle(dto.Hwnd);
                    }
                    RefreshTaskbarStrip();
                });
            }

            AddContextSeparator();
            AddContextBtn("⚙   Taskleisteneinstellungen", () => { SwitchSettingsTab("taskbar"); OpenInternalWindowAnimated("SettingsWin"); });
            PopupContextMenuAt(e.GetPosition(DesktopWorkAreaGrid));
        }

        private void TaskbarWindowItem_PreviewMouseDown(object sender, MouseButtonEventArgs e)
        {
            if (e.ChangedButton == MouseButton.Left && sender is FrameworkElement fe && fe.Tag is OsOpenWindowDto dto)
            {
                _taskbarDragStart = e.GetPosition(TaskbarBorder);
                _taskbarArmedDragDto = dto;
            }
        }

        private void TaskbarWindowItem_PreviewMouseMove(object sender, MouseEventArgs e)
        {
            if (_taskbarArmedDragDto == null || e.LeftButton != MouseButtonState.Pressed)
            {
                _taskbarArmedDragDto = null;
                return;
            }

            var pos = e.GetPosition(TaskbarBorder);
            if (Math.Abs(pos.X - _taskbarDragStart.X) > 7 || Math.Abs(pos.Y - _taskbarDragStart.Y) > 7)
            {
                var dto = _taskbarArmedDragDto;
                _taskbarArmedDragDto = null;
                if (!string.IsNullOrEmpty(dto.ShortcutId) && sender is DependencyObject dep)
                {
                    var data = new DataObject("FunkenwerkTaskbarReorder", dto.ShortcutId);
                    data.SetData("FunkenwerkAppId", dto.ShortcutId);
                    DragDrop.DoDragDrop(dep, data, DragDropEffects.Move | DragDropEffects.Copy);
                }
            }
        }

        private void TaskbarWindowItem_MouseDown(object sender, MouseButtonEventArgs e)
        {
            // Middle-Click on Taskbar Item closes the window immediately!
            if (e.ChangedButton != MouseButton.Middle || sender is not FrameworkElement fe || fe.Tag is not OsOpenWindowDto dto)
                return;

            e.Handled = true;
            if (!string.IsNullOrEmpty(dto.InternalWindowKey))
            {
                if (dto.InternalWindowKey == "GewerbeMonitorWin")
                {
                    _gewerbeMonitorWindow?.Close();
                    _gewerbeMonitorWindow = null;
                }
                else
                {
                    string winKey = dto.InternalWindowKey == "MeineDatenDir" ? "ExplorerWin" : dto.InternalWindowKey;
                    var win = ResolveInternalWindow(winKey);
                    if (win != null) win.Visibility = Visibility.Collapsed;
                    _minimizedInternalWins.Remove(winKey);
                    if (winKey == "SnipWin") DiscardCurrentSnipFromMemory();
                }
                _lastTaskbarSignature = "";
                RefreshTaskbarStrip();
            }
            else if (dto.Hwnd != IntPtr.Zero)
            {
                OsHardwareAndAudioEngine.CloseWindowHandle(dto.Hwnd);
                _lastTaskbarSignature = "";
                RefreshTaskbarStrip();
            }
        }

        private void TaskbarWindowItem_DragOver(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent("FunkenwerkTaskbarReorder") || e.Data.GetDataPresent("FunkenwerkAppId") || e.Data.GetDataPresent(DataFormats.FileDrop))
            {
                e.Effects = DragDropEffects.Move | DragDropEffects.Copy;
                if (sender is Border b)
                    b.BorderBrush = Solid("#F2FCE7B2");
            }
            else
            {
                e.Effects = DragDropEffects.None;
            }
            e.Handled = true;
        }

        private void TaskbarWindowItem_DragLeave(object sender, DragEventArgs e)
        {
            if (sender is Border b)
                b.ClearValue(Border.BorderBrushProperty);
        }

        private void TaskbarWindowItem_Drop(object sender, DragEventArgs e)
        {
            if (sender is Border b)
                b.ClearValue(Border.BorderBrushProperty);
            if (sender is not FrameworkElement fe || fe.Tag is not OsOpenWindowDto targetDto)
                return;

            // 1. Reordering pinned Taskbar items or pinning onto position
            string? draggedId = e.Data.GetDataPresent("FunkenwerkTaskbarReorder")
                ? e.Data.GetData("FunkenwerkTaskbarReorder") as string
                : (e.Data.GetDataPresent("FunkenwerkAppId") ? e.Data.GetData("FunkenwerkAppId") as string : null);

            if (!string.IsNullOrEmpty(draggedId))
            {
                e.Handled = true;
                var list = _settings.PinnedTaskbarAppIds.Distinct(StringComparer.OrdinalIgnoreCase).ToList();
                list.RemoveAll(x => string.Equals(x, draggedId, StringComparison.OrdinalIgnoreCase));

                int targetIdx = !string.IsNullOrEmpty(targetDto.ShortcutId)
                    ? list.FindIndex(x => string.Equals(x, targetDto.ShortcutId, StringComparison.OrdinalIgnoreCase))
                    : -1;

                if (targetIdx >= 0)
                    list.Insert(targetIdx, draggedId);
                else
                    list.Add(draggedId);

                _settings.PinnedTaskbarAppIds = list;
                OsVaultAndStorage.SaveSettings(_settings);
                _lastTaskbarSignature = "";
                RefreshTaskbarStrip();
                return;
            }

            // 2. Dropping file(s) onto a Taskbar App button opens the file in that app!
            if (e.Data.GetDataPresent(DataFormats.FileDrop) && e.Data.GetData(DataFormats.FileDrop) is string[] droppedFiles && droppedFiles.Length > 0)
            {
                e.Handled = true;
                if (!string.IsNullOrEmpty(targetDto.AppKey))
                {
                    OsHardwareAndAudioEngine.LaunchOrFocusCollectionApp(targetDto.AppKey, droppedFiles[0]);
                }
                else if (targetDto.InternalWindowKey == "MeineDatenDir")
                {
                    OsVaultAndStorage.EnsureMeineDatenStructure();
                    TransferPathsIntoDirectory(droppedFiles, OsVaultAndStorage.EingangTrichterDir, moveInsteadOfCopy: (Keyboard.Modifiers & ModifierKeys.Control) == 0);
                }
                else if (targetDto.InternalWindowKey == "ExplorerWin")
                {
                    if (Directory.Exists(droppedFiles[0]))
                    {
                        NavigateExplorer(droppedFiles[0], recordHistory: true);
                        OpenInternalWindowAnimated("ExplorerWin");
                    }
                    else
                    {
                        TransferPathsIntoDirectory(droppedFiles, _currentExplorerDir, moveInsteadOfCopy: (Keyboard.Modifiers & ModifierKeys.Control) == 0);
                    }
                }
                else if (targetDto.InternalWindowKey == "ViewerWin")
                {
                    OpenFileWithConfiguredDefaultProgram(droppedFiles[0]);
                }
            }
        }

        private void Taskbar_DragOver(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent("FunkenwerkAppId") || e.Data.GetDataPresent("FunkenwerkTaskbarReorder"))
                e.Effects = DragDropEffects.Move | DragDropEffects.Copy;
            else
                e.Effects = DragDropEffects.None;
            e.Handled = true;
        }

        private void Taskbar_Drop(object sender, DragEventArgs e)
        {
            if (e.Handled) return;
            string? appId = e.Data.GetDataPresent("FunkenwerkAppId")
                ? e.Data.GetData("FunkenwerkAppId") as string
                : (e.Data.GetDataPresent("FunkenwerkTaskbarReorder") ? e.Data.GetData("FunkenwerkTaskbarReorder") as string : null);

            if (!string.IsNullOrEmpty(appId))
            {
                e.Handled = true;
                if (!IsAppPinnedToTaskbar(appId))
                {
                    _settings.PinnedTaskbarAppIds.Add(appId);
                    OsVaultAndStorage.SaveSettings(_settings);
                    _lastTaskbarSignature = "";
                    RefreshTaskbarStrip();
                    ShowToast("📌 An Taskleiste angeheftet");
                }
            }
        }

        private void TaskbarBorder_MouseRightButtonUp(object sender, MouseButtonEventArgs e)
        {
            if (e.Handled) return;
            e.Handled = true;

            CloseAllFlyoutsAndMenus(keepContext: true);
            ContextMenuItemsStack.Children.Clear();

            AddContextBtn("⚙   Taskleisteneinstellungen", () => { SwitchSettingsTab("taskbar"); OpenInternalWindowAnimated("SettingsWin"); });
            AddContextBtn(_settings.TaskbarTransparentMode ? "✦   Transparent-Modus: AN" : "✦   Transparent-Modus: AUS", () =>
            {
                _settings.TaskbarTransparentMode = !_settings.TaskbarTransparentMode;
                OsVaultAndStorage.SaveSettings(_settings);
                ApplyTaskbarLayoutAndStyle();
                UpdateTaskbarSettingsButtonsUi();
                ShowToast(_settings.TaskbarTransparentMode ? "✓ Taskleiste: Transparent-Modus aktiv" : "✓ Taskleiste: Glas-Modus aktiv");
            });
            AddContextSeparator();
            AddContextBtn("⬇   Position: Unten", () => SetTaskbarPositionInternal("Bottom"));
            AddContextBtn("⬆   Position: Oben", () => SetTaskbarPositionInternal("Top"));
            AddContextBtn("⬅   Position: Links", () => SetTaskbarPositionInternal("Left"));
            AddContextBtn("➡   Position: Rechts", () => SetTaskbarPositionInternal("Right"));
            AddContextSeparator();
            AddContextBtn("↤   Apps: Links", () => SetTaskbarAlignmentInternal("Left"));
            AddContextBtn("↔   Apps: Mittig", () => SetTaskbarAlignmentInternal("Center"));
            AddContextBtn("↦   Apps: Rechts", () => SetTaskbarAlignmentInternal("Right"));

            PopupContextMenuAt(e.GetPosition(DesktopWorkAreaGrid));
        }

        private void ApplyTaskbarLayoutAndStyle()
        {
            if (TaskbarBorder == null || TaskbarInnerGrid == null || TaskbarWindowsStrip == null) return;

            string pos = _settings.TaskbarPosition ?? "Bottom";
            string align = _settings.TaskbarAlignment ?? "Left";
            bool transparent = _settings.TaskbarTransparentMode;
            bool isVertical = pos is "Left" or "Right";

            // 1. Transparency vs Noble Glass
            if (transparent)
            {
                TaskbarBorder.Background = Brushes.Transparent;
                TaskbarBorder.BorderBrush = Brushes.Transparent;
                TaskbarBorder.BorderThickness = new Thickness(0);
            }
            else
            {
                TaskbarBorder.Background = (Brush)Resources["TaskbarGlassBrush"];
                TaskbarBorder.BorderBrush = (Brush)Resources["SubtleBorderBrush"];
                TaskbarBorder.BorderThickness = pos switch
                {
                    "Top" => new Thickness(0, 0, 0, 1),
                    "Left" => new Thickness(0, 0, 1, 0),
                    "Right" => new Thickness(1, 0, 0, 0),
                    _ => new Thickness(0, 1, 0, 0)
                };
            }

            // 2. Dock Position in 3x3 WorkspaceShellGrid
            if (pos == "Top")
            {
                Grid.SetRow(TaskbarBorder, 0);
                Grid.SetColumn(TaskbarBorder, 0);
                Grid.SetRowSpan(TaskbarBorder, 1);
                Grid.SetColumnSpan(TaskbarBorder, 3);
                TaskbarBorder.Height = 54;
                TaskbarBorder.Width = double.NaN;
                TaskbarBorder.Padding = new Thickness(12, 0, 12, 0);
            }
            else if (pos == "Left")
            {
                Grid.SetRow(TaskbarBorder, 0);
                Grid.SetColumn(TaskbarBorder, 0);
                Grid.SetRowSpan(TaskbarBorder, 3);
                Grid.SetColumnSpan(TaskbarBorder, 1);
                TaskbarBorder.Width = 76;
                TaskbarBorder.Height = double.NaN;
                TaskbarBorder.Padding = new Thickness(0, 10, 0, 10);
            }
            else if (pos == "Right")
            {
                Grid.SetRow(TaskbarBorder, 0);
                Grid.SetColumn(TaskbarBorder, 2);
                Grid.SetRowSpan(TaskbarBorder, 3);
                Grid.SetColumnSpan(TaskbarBorder, 1);
                TaskbarBorder.Width = 76;
                TaskbarBorder.Height = double.NaN;
                TaskbarBorder.Padding = new Thickness(0, 10, 0, 10);
            }
            else
            {
                Grid.SetRow(TaskbarBorder, 2);
                Grid.SetColumn(TaskbarBorder, 0);
                Grid.SetRowSpan(TaskbarBorder, 1);
                Grid.SetColumnSpan(TaskbarBorder, 3);
                TaskbarBorder.Height = 54;
                TaskbarBorder.Width = double.NaN;
                TaskbarBorder.Padding = new Thickness(12, 0, 12, 0);
            }

            // 3. Inner Grid Orientation & App Shortcuts Alignment
            TaskbarInnerGrid.RowDefinitions.Clear();
            TaskbarInnerGrid.ColumnDefinitions.Clear();

            var stackFactory = new FrameworkElementFactory(typeof(StackPanel));
            stackFactory.SetValue(StackPanel.OrientationProperty, isVertical ? Orientation.Vertical : Orientation.Horizontal);
            TaskbarWindowsStrip.ItemsPanel = new ItemsPanelTemplate(stackFactory);

            if (!isVertical)
            {
                TaskbarInnerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                TaskbarInnerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                TaskbarInnerGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                Grid.SetRow(TaskbarStartCluster, 0); Grid.SetColumn(TaskbarStartCluster, 0);
                Grid.SetRow(TaskbarAppsScrollViewer, 0); Grid.SetColumn(TaskbarAppsScrollViewer, 1);
                Grid.SetRow(TaskbarTrayCluster, 0); Grid.SetColumn(TaskbarTrayCluster, 2);

                TaskbarStartCluster.Orientation = Orientation.Horizontal;
                TaskbarStartCluster.HorizontalAlignment = HorizontalAlignment.Left;
                TaskbarTrayCluster.Orientation = Orientation.Horizontal;
                TaskbarTrayCluster.HorizontalAlignment = HorizontalAlignment.Right;

                if (StartBtnLabelTxt != null) StartBtnLabelTxt.Visibility = Visibility.Visible;
                StartButton.Width = double.NaN;
                StartButton.Padding = new Thickness(9, 5, 9, 5);
                TaskbarSearchBtn.Margin = new Thickness(6, 0, 8, 0);
                if (TrayVolTxt != null) TrayVolTxt.Visibility = Visibility.Visible;
                if (ShowDesktopSliverBorder != null) ShowDesktopSliverBorder.Visibility = Visibility.Visible;

                TaskbarAppsScrollViewer.HorizontalScrollBarVisibility = ScrollBarVisibility.Hidden;
                TaskbarAppsScrollViewer.VerticalScrollBarVisibility = ScrollBarVisibility.Disabled;
                TaskbarAppsScrollViewer.VerticalAlignment = VerticalAlignment.Center;
                TaskbarAppsScrollViewer.HorizontalAlignment = align switch
                {
                    "Center" => HorizontalAlignment.Center,
                    "Right" => HorizontalAlignment.Right,
                    _ => HorizontalAlignment.Left
                };
            }
            else
            {
                TaskbarInnerGrid.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
                TaskbarInnerGrid.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });
                TaskbarInnerGrid.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });

                Grid.SetRow(TaskbarStartCluster, 0); Grid.SetColumn(TaskbarStartCluster, 0);
                Grid.SetRow(TaskbarAppsScrollViewer, 1); Grid.SetColumn(TaskbarAppsScrollViewer, 0);
                Grid.SetRow(TaskbarTrayCluster, 2); Grid.SetColumn(TaskbarTrayCluster, 0);

                TaskbarStartCluster.Orientation = Orientation.Vertical;
                TaskbarStartCluster.HorizontalAlignment = HorizontalAlignment.Center;
                TaskbarTrayCluster.Orientation = Orientation.Vertical;
                TaskbarTrayCluster.HorizontalAlignment = HorizontalAlignment.Center;

                if (StartBtnLabelTxt != null) StartBtnLabelTxt.Visibility = Visibility.Collapsed;
                StartButton.Width = 44;
                StartButton.Padding = new Thickness(0);
                TaskbarSearchBtn.Margin = new Thickness(0, 6, 0, 8);
                if (TrayVolTxt != null) TrayVolTxt.Visibility = Visibility.Collapsed;
                if (ShowDesktopSliverBorder != null) ShowDesktopSliverBorder.Visibility = Visibility.Collapsed;

                TaskbarAppsScrollViewer.HorizontalScrollBarVisibility = ScrollBarVisibility.Disabled;
                TaskbarAppsScrollViewer.VerticalScrollBarVisibility = ScrollBarVisibility.Hidden;
                TaskbarAppsScrollViewer.HorizontalAlignment = HorizontalAlignment.Center;
                TaskbarAppsScrollViewer.VerticalAlignment = align switch
                {
                    "Center" => VerticalAlignment.Center,
                    "Right" => VerticalAlignment.Bottom,
                    _ => VerticalAlignment.Top
                };
            }

            // 4. Position Flyouts next to active Taskbar edge
            if (StartMenuFlyout != null)
            {
                StartMenuFlyout.HorizontalAlignment = pos == "Right" ? HorizontalAlignment.Right : HorizontalAlignment.Left;
                StartMenuFlyout.VerticalAlignment = pos is "Top" or "Left" or "Right" ? VerticalAlignment.Top : VerticalAlignment.Bottom;
                StartMenuFlyout.Margin = pos switch
                {
                    "Top" => new Thickness(14, 10, 0, 0),
                    "Left" => new Thickness(10, 10, 0, 0),
                    "Right" => new Thickness(0, 10, 10, 0),
                    _ => new Thickness(14, 0, 0, 10)
                };
            }

            foreach (var trayFlyout in new[] { WifiFlyout, BluetoothFlyout, SoundFlyout, CalendarFlyout })
            {
                if (trayFlyout == null) continue;
                trayFlyout.HorizontalAlignment = pos == "Left" ? HorizontalAlignment.Left : HorizontalAlignment.Right;
                trayFlyout.VerticalAlignment = pos == "Top" ? VerticalAlignment.Top : VerticalAlignment.Bottom;
                trayFlyout.Margin = pos switch
                {
                    "Top" => new Thickness(0, 10, 14, 0),
                    "Left" => new Thickness(10, 0, 0, 10),
                    "Right" => new Thickness(0, 0, 10, 10),
                    _ => new Thickness(0, 0, 14, 10)
                };
            }

            _lastTaskbarSignature = "";
            RefreshTaskbarStrip();
        }

        private void ShowDesktopSliver_Click(object sender, MouseButtonEventArgs e)
        {
            CloseAllFlyoutsAndMenus();
            var allWins = new[]
            {
                ("ExplorerWin", ExplorerWin),
                ("CalculatorWin", CalculatorWin),
                ("SnipWin", SnipWin),
                ("NotesWin", NotesWin),
                ("SettingsWin", SettingsWin),
                ("LeitstandWin", LeitstandWin),
                ("ViewerWin", ViewerWin)
            };
            bool anyVisible = allWins.Any(x => x.Item2.Visibility == Visibility.Visible);

            if (anyVisible)
            {
                foreach (var (k, w) in allWins)
                {
                    if (w.Visibility == Visibility.Visible) { _minimizedInternalWins.Add(k); w.Visibility = Visibility.Collapsed; }
                }
            }
            else
            {
                foreach (string k in _minimizedInternalWins.ToList()) OpenInternalWindowAnimated(k);
            }
            _lastTaskbarSignature = "";
            RefreshTaskbarStrip();
        }

        private void CloseAllFlyoutsAndMenus(Border? except = null, bool keepContext = false)
        {
            if (!keepContext) ContextMenuCanvas.Visibility = Visibility.Collapsed;
            foreach (var fl in new[] { StartMenuFlyout, WifiFlyout, BluetoothFlyout, SoundFlyout, CalendarFlyout })
                if (fl != except) fl.Visibility = Visibility.Collapsed;
        }

        private void ToggleFlyoutAnimated(Border flyout)
        {
            if (flyout.Visibility == Visibility.Visible) { flyout.Visibility = Visibility.Collapsed; return; }
            CloseAllFlyoutsAndMenus(except: flyout);
            flyout.Visibility = Visibility.Visible;
            if (flyout.RenderTransform is TranslateTransform tt)
            {
                tt.Y = 20;
                tt.BeginAnimation(TranslateTransform.YProperty, new DoubleAnimation(20, 0, TimeSpan.FromMilliseconds(165)) { EasingFunction = SmoothEaseOut });
            }
            AnimateOpacity(flyout, 0.0, 1.0, 150);
        }

        private void OpenInstantWindowsKeySearch()
        {
            if (IsLockScreenActive) return;
            if (StartMenuFlyout.Visibility == Visibility.Visible && string.IsNullOrEmpty(StartSearchBox.Text))
            {
                StartMenuFlyout.Visibility = Visibility.Collapsed;
                return;
            }

            StartSearchBox.Text = "";
            RefreshStartMenuApps("");
            if (StartMenuFlyout.Visibility != Visibility.Visible)
                ToggleFlyoutAnimated(StartMenuFlyout);

            Activate();
            StartSearchBox.Focus();
            Keyboard.Focus(StartSearchBox);
        }

        private void ToggleStartMenu_Click(object sender, RoutedEventArgs e)
        {
            if (StartMenuFlyout.Visibility == Visibility.Visible)
            {
                StartMenuFlyout.Visibility = Visibility.Collapsed;
                return;
            }
            StartSearchBox.Text = "";
            RefreshStartMenuApps("");
            ToggleFlyoutAnimated(StartMenuFlyout);
            StartSearchBox.Focus();
            Keyboard.Focus(StartSearchBox);
        }

        private void TaskbarSearch_Click(object sender, RoutedEventArgs e)
        {
            StartSearchBox.Text = "";
            RefreshStartMenuApps("");
            if (StartMenuFlyout.Visibility != Visibility.Visible) ToggleFlyoutAnimated(StartMenuFlyout);
            StartSearchBox.Focus();
            Keyboard.Focus(StartSearchBox);
        }

        private void RefreshStartMenuApps(string query)
        {
            string q = (query ?? "").Trim();
            var catalog = BuildFullAppAndSystemCatalog();

            // Also include User Desktop Folders/Files when searching!
            if (!string.IsNullOrEmpty(q))
            {
                foreach (var uItem in _desktopItems.Where(x => x.Kind is DesktopEntryKind.UserFolder or DesktopEntryKind.UserFile))
                    catalog.Add(uItem);
            }

            _currentSearchResults = OsSmartSearchEngine.FilterAndRank(q, catalog);
            _highlightedSearchIndex = _currentSearchResults.Count > 0 ? 0 : -1;

            ApplySearchHighlightVisuals();

            if (StartMenuSectionHeaderTxt != null)
            {
                StartMenuSectionHeaderTxt.Text = string.IsNullOrEmpty(q)
                    ? "PROGRAMME"
                    : $"TREFFER ({_currentSearchResults.Count})";
            }
        }

        private void ApplySearchHighlightVisuals()
        {
            bool hasQuery = !string.IsNullOrWhiteSpace(StartSearchBox?.Text);
            for (int i = 0; i < _currentSearchResults.Count; i++)
                _currentSearchResults[i].IsSearchHighlighted = (hasQuery || _highlightedSearchIndex > 0) && (i == _highlightedSearchIndex);

            if (StartMenuAppsGrid != null)
            {
                StartMenuAppsGrid.ItemsSource = null;
                StartMenuAppsGrid.ItemsSource = _currentSearchResults;
            }
        }

        private void StartSearchBox_TextChanged(object sender, TextChangedEventArgs e) => RefreshStartMenuApps(StartSearchBox.Text);

        private void StartSearchBox_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Escape)
            {
                e.Handled = true;
                StartMenuFlyout.Visibility = Visibility.Collapsed;
                return;
            }

            if (_currentSearchResults.Count == 0) return;

            if (e.Key == Key.Enter)
            {
                e.Handled = true;
                int idx = Math.Clamp(_highlightedSearchIndex, 0, _currentSearchResults.Count - 1);
                var chosen = _currentSearchResults[idx];
                CloseAllFlyoutsAndMenus();
                ActivateDesktopItem(chosen);
                return;
            }

            if (e.Key is Key.Right or Key.Left or Key.Down or Key.Up)
            {
                e.Handled = true;
                int cur = Math.Max(0, _highlightedSearchIndex);
                int delta = e.Key switch
                {
                    Key.Right => 1,
                    Key.Left => -1,
                    Key.Down => 4,
                    Key.Up => -4,
                    _ => 0
                };
                _highlightedSearchIndex = Math.Clamp(cur + delta, 0, _currentSearchResults.Count - 1);
                for (int i = 0; i < _currentSearchResults.Count; i++)
                    _currentSearchResults[i].IsSearchHighlighted = (i == _highlightedSearchIndex);

                StartMenuAppsGrid.ItemsSource = null;
                StartMenuAppsGrid.ItemsSource = _currentSearchResults;
            }
        }

        private void StartMenuApp_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string id) return;
            CloseAllFlyoutsAndMenus();
            var found = _currentSearchResults.FirstOrDefault(x => x.Id == id)
                     ?? BuildFullAppAndSystemCatalog().FirstOrDefault(x => x.Id == id)
                     ?? _desktopItems.FirstOrDefault(x => x.Id == id);
            if (found != null) ActivateDesktopItem(found);
        }

        private void StartMenuApp_PreviewMouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (sender is FrameworkElement fe && fe.Tag is string id)
            {
                _startMenuAppDragStart = e.GetPosition(StartMenuFlyout);
                _startMenuArmedDragAppId = id;
            }
        }

        private void StartMenuApp_PreviewMouseMove(object sender, MouseEventArgs e)
        {
            if (string.IsNullOrEmpty(_startMenuArmedDragAppId) || e.LeftButton != MouseButtonState.Pressed)
            {
                _startMenuArmedDragAppId = null;
                return;
            }

            var pos = e.GetPosition(StartMenuFlyout);
            if (Math.Abs(pos.X - _startMenuAppDragStart.X) > 8 || Math.Abs(pos.Y - _startMenuAppDragStart.Y) > 8)
            {
                string id = _startMenuArmedDragAppId;
                _startMenuArmedDragAppId = null;
                if (sender is DependencyObject dep)
                {
                    var data = new DataObject("FunkenwerkAppId", id);
                    DragDrop.DoDragDrop(dep, data, DragDropEffects.Copy | DragDropEffects.Move);
                }
            }
        }

        private void StartMenuApp_RightClick(object sender, MouseButtonEventArgs e)
        {
            e.Handled = true;
            if (sender is not FrameworkElement fe || fe.Tag is not string id) return;
            var target = _currentSearchResults.FirstOrDefault(x => x.Id == id)
                      ?? BuildFullAppAndSystemCatalog().FirstOrDefault(x => x.Id == id);
            if (target == null) return;

            ContextMenuItemsStack.Children.Clear();
            AddContextBtn("▶   Öffnen", () =>
            {
                CloseAllFlyoutsAndMenus();
                ActivateDesktopItem(target);
            });

            if (target.Kind is DesktopEntryKind.CollectionApp or DesktopEntryKind.SystemTool)
            {
                AddContextSeparator();
                bool pinned = IsAppPinnedToTaskbar(target.Id);
                AddContextBtn(pinned ? "📌   Von Taskleiste lösen" : "📌   An Taskleiste anheften", () => TogglePinAppToTaskbar(target.Id, target.Title));
            }

            if (target.IsShortcutRemovable)
            {
                AddContextSeparator();
                if (IsShortcutHiddenOnDesktop(target.Id))
                {
                    AddContextBtn("📌   Auf Desktop ablegen", () => RestoreShortcutToDesktop(target.Id, target.Title));
                }
                else
                {
                    AddContextBtn("🗑   Vom Desktop entfernen", () => RemoveShortcutFromDesktop(target.Id, target.Title));
                }
            }

            AddContextSeparator();
            AddContextBtn("✎   Umbenennen", () =>
            {
                CloseAllFlyoutsAndMenus();
                var onDesktop = _desktopItems.FirstOrDefault(x => x.Id == target.Id);
                if (onDesktop != null)
                {
                    _selectedDesktopIds.Clear();
                    _selectedDesktopIds.Add(onDesktop.Id);
                    UpdateDesktopSelectionVisuals();
                    BeginInlineRename(onDesktop);
                }
                else
                {
                    OpenInternalWindowAnimated("ExplorerWin");
                    _modalCustomRenameItemId = target.Id;
                    _expModalRenameTargetFullPath = null;
                    ExpModalTitleTxt.Text = "UMBENENNEN";
                    ExpModalDetailsTxt.Visibility = Visibility.Collapsed;
                    ExpModalInputBox.Visibility = Visibility.Visible;
                    ExpModalConfirmBtn.Visibility = Visibility.Visible;
                    ExpModalInputBox.Text = target.Title;
                    ExplorerModalOverlay.Visibility = Visibility.Visible;
                    ExpModalInputBox.Focus();
                    ExpModalInputBox.SelectAll();
                }
            });

            if (_settings.CustomDisplayNames.ContainsKey(target.Id))
            {
                AddContextBtn("↺   Standardname", () =>
                {
                    _settings.CustomDisplayNames.Remove(target.Id);
                    OsVaultAndStorage.SaveSettings(_settings);
                    RebuildDesktopIcons();
                    RefreshStartMenuApps(StartSearchBox.Text);
                    RefreshTaskbarStrip();
                    ShowToast("✓ Standardname wiederhergestellt");
                });
            }

            PopupContextMenuAt(e.GetPosition(DesktopWorkAreaGrid));
        }

        private void StartQuickFolder_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string tag) return;
            CloseAllFlyoutsAndMenus();
            RefreshExplorerDrivesSidebar();
            string path = ResolveQuickNavTargetFolder(tag);
            NavigateExplorer(path, recordHistory: true);
            OpenInternalWindowAnimated("ExplorerWin");
        }

        private void StartOpenTaskbarTab_Click(object sender, RoutedEventArgs e) { CloseAllFlyoutsAndMenus(); SwitchSettingsTab("taskbar"); OpenInternalWindowAnimated("SettingsWin"); }
        private void StartOpenUsbTab_Click(object sender, RoutedEventArgs e) { CloseAllFlyoutsAndMenus(); SwitchSettingsTab("fortress"); OpenInternalWindowAnimated("SettingsWin"); }
        private void StartOpenAccount_Click(object sender, RoutedEventArgs e) => OpenAccountDirect_Click(sender, e);
        private void OpenAccountDirect_Click(object sender, RoutedEventArgs e) { CloseAllFlyoutsAndMenus(); SwitchSettingsTab("account"); OpenInternalWindowAnimated("SettingsWin"); }

        // WLAN, Bluetooth, Sound + Headset Microphone & Calendar Flyouts
        private async void ToggleWifiFlyout_Click(object sender, RoutedEventArgs e) { ToggleFlyoutAnimated(WifiFlyout); if (WifiFlyout.Visibility == Visibility.Visible) await RefreshWifiFlyoutAsync(); }
        private async void RefreshWifiScan_Click(object sender, RoutedEventArgs e) => await RefreshWifiFlyoutAsync();

        private async Task RefreshWifiFlyoutAsync()
        {
            var (netOk, _, kind, ip) = OsHardwareAndAudioEngine.ReadLocalNetworkSummary();
            WifiActiveSummaryTxt.Text = netOk ? $"{kind} aktiv · {ip}" : "Nicht verbunden";
            var (ssid, nets) = await OsHardwareAndAudioEngine.ScanWifiNetworksAsync();
            if (!string.IsNullOrEmpty(ssid)) WifiActiveSummaryTxt.Text = $"WLAN · {ssid} ({ip})";
            WifiListControl.ItemsSource = nets;
        }

        private async void WifiNetworkItem_Click(object sender, MouseButtonEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not WifiNetworkDto net) return;
            _selectedWifiNet = net;

            if (net.IsOpen)
            {
                WifiConnectSubPanel.Visibility = Visibility.Collapsed;
                ShowToast($"Verbinde mit '{net.Ssid}'...");
                var (ok, msg) = await OsHardwareAndAudioEngine.ConnectWifiNetworkAsync(net.Ssid, "", true, net.Security);
                ShowToast(ok ? $"✓ {msg}" : msg);
                await Task.Delay(800);
                await RefreshWifiFlyoutAsync();
                return;
            }

            if (net.HasSavedProfile && !net.IsConnected)
            {
                ShowToast($"Verbinde mit '{net.Ssid}' (Gespeichertes Profil)...");
                var (ok, msg) = await OsHardwareAndAudioEngine.ConnectWifiNetworkAsync(net.Ssid, "", false, net.Security);
                if (ok)
                {
                    WifiConnectSubPanel.Visibility = Visibility.Collapsed;
                    ShowToast($"✓ {msg}");
                    await Task.Delay(700);
                    await RefreshWifiFlyoutAsync();
                    return;
                }
            }

            WifiSelectedSsidTxt.Text = net.HasSavedProfile
                ? $"Verbinden mit: {net.Ssid} (Profil gespeichert)"
                : $"Verbinden mit: {net.Ssid}";
            WifiConnectSubPanel.Visibility = Visibility.Visible;
            WifiPwdBox.Password = "";
            WifiPwdRevealBox.Text = "";
            if (_isWifiPwdVisible) WifiPwdRevealBox.Focus();
            else WifiPwdBox.Focus();
        }

        private void ToggleWifiPwdEye_Click(object sender, RoutedEventArgs e)
        {
            _isWifiPwdVisible = !_isWifiPwdVisible;
            if (_isWifiPwdVisible) { WifiPwdRevealBox.Text = WifiPwdBox.Password; WifiPwdBox.Visibility = Visibility.Collapsed; WifiPwdRevealBox.Visibility = Visibility.Visible; }
            else { WifiPwdBox.Password = WifiPwdRevealBox.Text; WifiPwdRevealBox.Visibility = Visibility.Collapsed; WifiPwdBox.Visibility = Visibility.Visible; }
        }

        private async void ConnectSelectedWifi_Click(object sender, RoutedEventArgs e)
        {
            if (_selectedWifiNet == null) return;
            string pwd = _isWifiPwdVisible ? WifiPwdRevealBox.Text : WifiPwdBox.Password;
            ShowToast($"Verbinde mit '{_selectedWifiNet.Ssid}'...");
            var (ok, msg) = await OsHardwareAndAudioEngine.ConnectWifiNetworkAsync(_selectedWifiNet.Ssid, pwd, _selectedWifiNet.IsOpen, _selectedWifiNet.Security);
            WifiPwdBox.Password = "";
            WifiPwdRevealBox.Text = "";
            WifiConnectSubPanel.Visibility = Visibility.Collapsed;
            ShowToast(ok ? $"✓ {msg}" : msg);
            await Task.Delay(800);
            await RefreshWifiFlyoutAsync();
        }

        private async void ToggleBluetoothFlyout_Click(object sender, RoutedEventArgs e)
        {
            ToggleFlyoutAnimated(BluetoothFlyout);
            if (BluetoothFlyout.Visibility == Visibility.Visible)
            {
                BluetoothListControl.ItemsSource = await OsHardwareAndAudioEngine.ScanBluetoothDevicesAsync(issueHardwareInquiry: false);
            }
        }

        private async void RefreshBtScan_Click(object sender, RoutedEventArgs e)
        {
            ShowToast("Suche Bluetooth-Geräte in Reichweite...");
            var list = await OsHardwareAndAudioEngine.ScanBluetoothDevicesAsync(issueHardwareInquiry: true);
            BluetoothListControl.ItemsSource = list;
            ShowToast($"✓ {list.Count} Bluetooth-Gerät(e) gefunden");
        }

        private async void OpenBtPairing_Click(object sender, RoutedEventArgs e)
        {
            ShowToast("Suche & verbinde Bluetooth-Geräte...");
            var list = await OsHardwareAndAudioEngine.ScanBluetoothDevicesAsync(issueHardwareInquiry: false);
            BluetoothListControl.ItemsSource = list;

            var candidate = list.FirstOrDefault(b => b.IsTeufel) ?? list.FirstOrDefault(b => !b.IsConnected);
            if (candidate != null)
            {
                var (ok, msg) = await OsHardwareAndAudioEngine.ConnectOrPairBluetoothDeviceAsync(candidate);
                ShowToast(ok ? $"✓ {msg}" : $"✓ {list.Count} Bluetooth-Gerät(e) bereit zum Koppeln");
                BluetoothListControl.ItemsSource = await OsHardwareAndAudioEngine.ScanBluetoothDevicesAsync(issueHardwareInquiry: false);
                RefreshAudioUi(forceDirectRead: true);
            }
            else
            {
                ShowToast($"✓ {list.Count} Bluetooth-Gerät(e) bereit zum Koppeln");
            }
        }

        private async void BluetoothDeviceItem_Click(object sender, MouseButtonEventArgs e)
        {
            if (sender is not FrameworkElement fe) return;

            BluetoothDeviceDto? btDev = fe.Tag as BluetoothDeviceDto;
            if (btDev == null && fe.Tag is string devName)
                btDev = new BluetoothDeviceDto { Name = devName, IsPaired = true };
            if (btDev == null) return;

            bool ok;
            string msg;
            if (btDev.IsConnected)
            {
                ShowToast($"Trenne '{btDev.Name}'...");
                (ok, msg) = await OsHardwareAndAudioEngine.DisconnectBluetoothDeviceAsync(btDev);
            }
            else
            {
                ShowToast($"Verbinde '{btDev.Name}'...");
                (ok, msg) = await OsHardwareAndAudioEngine.ConnectOrPairBluetoothDeviceAsync(btDev);
            }
            RefreshAudioUi(forceDirectRead: true);
            BluetoothListControl.ItemsSource = await OsHardwareAndAudioEngine.ScanBluetoothDevicesAsync(issueHardwareInquiry: false);
            ShowToast(ok ? $"✓ {msg}" : msg);
        }

        private void ToggleSoundFlyout_Click(object sender, RoutedEventArgs e)
        {
            ToggleFlyoutAnimated(SoundFlyout);
            if (SoundFlyout.Visibility == Visibility.Visible)
            {
                RefreshAudioUi(forceDirectRead: true);
                AudioOutputsListControl.ItemsSource = OsHardwareAndAudioEngine.ListAudioOutputDevices();
                AudioInputsListControl.ItemsSource = OsHardwareAndAudioEngine.ListAudioInputMicrophones();
            }
        }

        private void RefreshAudioUi(bool forceDirectRead = true)
        {
            var audio = forceDirectRead ? OsHardwareAndAudioEngine.ReadAudioState() : OsHardwareAndAudioEngine.CachedAudioState;
            _suppressVolumeEvent = true;
            try
            {
                MasterVolSlider.Value = audio.VolumePercent;
                FlyoutVolTxt.Text = audio.VolumePercent.ToString();
                TrayVolTxt.Text = audio.IsMuted ? "Stumm" : $"{audio.VolumePercent}%";
                FlyoutMuteBtn.Content = audio.IsMuted ? "🔇" : "🔊";
                TrayBtPath.Stroke = (audio.IsTeufelConnected || audio.IsBluetoothAudio)
                    ? Solid("#C5A059")
                    : (Brush)Resources["TextPrimaryBrush"];

                bool micMuted = forceDirectRead ? OsHardwareAndAudioEngine.ReadMicrophoneMuteState() : OsHardwareAndAudioEngine.CachedMicMuted;
                MicMuteBtn.Content = micMuted ? "🔇 Stumm" : "🎙 Aktiv";
            }
            finally
            {
                _suppressVolumeEvent = false;
            }
        }

        private void MasterVolSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressVolumeEvent || FlyoutVolTxt == null) return;
            int exactVol = Math.Clamp((int)Math.Round(e.NewValue), 0, 100);
            FlyoutVolTxt.Text = exactVol.ToString();
            TrayVolTxt.Text = $"{exactVol}%";
            OsHardwareAndAudioEngine.SetSystemVolumeExact(exactVol);
        }

        private void VolMinus_Click(object sender, RoutedEventArgs e) => MasterVolSlider.Value = Math.Clamp((int)Math.Round(MasterVolSlider.Value) - 1, 0, 100);
        private void VolPlus_Click(object sender, RoutedEventArgs e) => MasterVolSlider.Value = Math.Clamp((int)Math.Round(MasterVolSlider.Value) + 1, 0, 100);
        private void MuteToggle_Click(object sender, RoutedEventArgs e) { OsHardwareAndAudioEngine.ToggleSystemMute(); RefreshAudioUi(); }
        private void ToggleMicMute_Click(object sender, RoutedEventArgs e) { OsHardwareAndAudioEngine.ToggleMicrophoneMute(); RefreshAudioUi(); }

        private void TraySoundButton_PreviewMouseWheel(object sender, MouseWheelEventArgs e)
        {
            e.Handled = true;
            int step = e.Delta > 0 ? 2 : -2;
            int nextVol = Math.Clamp((int)Math.Round(MasterVolSlider.Value) + step, 0, 100);
            MasterVolSlider.Value = nextVol;
            FlyoutVolTxt.Text = nextVol.ToString();
            TrayVolTxt.Text = $"{nextVol}%";
            OsHardwareAndAudioEngine.SetSystemVolumeExact(nextVol);
        }

        private void AudioOutputItem_Click(object sender, MouseButtonEventArgs e)
        {
            if (sender is FrameworkElement fe && fe.Tag is string devId && OsHardwareAndAudioEngine.SetDefaultAudioOutputDevice(devId))
            {
                AudioOutputsListControl.ItemsSource = OsHardwareAndAudioEngine.ListAudioOutputDevices();
                RefreshAudioUi();
                ShowToast("✓ Ausgabegerät gewechselt");
            }
        }

        private void AudioInputItem_Click(object sender, MouseButtonEventArgs e)
        {
            if (sender is FrameworkElement fe && fe.Tag is string devId && OsHardwareAndAudioEngine.SetDefaultAudioOutputDevice(devId))
            {
                AudioInputsListControl.ItemsSource = OsHardwareAndAudioEngine.ListAudioInputMicrophones();
                RefreshAudioUi();
                ShowToast("✓ Mikrofon gewechselt");
            }
        }

        private async void MusicPrev_Click(object sender, RoutedEventArgs e) => await OsHardwareAndAudioEngine.SendFunkenwerkMusicCommandAsync("prev");
        private async void MusicToggle_Click(object sender, RoutedEventArgs e) => await OsHardwareAndAudioEngine.SendFunkenwerkMusicCommandAsync("toggle");
        private async void MusicNext_Click(object sender, RoutedEventArgs e) => await OsHardwareAndAudioEngine.SendFunkenwerkMusicCommandAsync("next");
        private async void MusicFocus_Click(object sender, RoutedEventArgs e) { _focusMusicActive = !_focusMusicActive; await OsHardwareAndAudioEngine.SendFunkenwerkMusicCommandAsync(_focusMusicActive ? "focus_on" : "focus_off"); }

        private void ToggleCalendarFlyout_Click(object sender, RoutedEventArgs e) { RenderCalendarMonthGrid(); ToggleFlyoutAnimated(CalendarFlyout); TickSystemClockAndTray(); }
        private void CalPrevMonth_Click(object sender, RoutedEventArgs e) { _displayedCalendarMonth = _displayedCalendarMonth.AddMonths(-1); RenderCalendarMonthGrid(); }
        private void CalNextMonth_Click(object sender, RoutedEventArgs e) { _displayedCalendarMonth = _displayedCalendarMonth.AddMonths(1); RenderCalendarMonthGrid(); }

        private void RenderCalendarMonthGrid()
        {
            CalMonthTitleTxt.Text = _displayedCalendarMonth.ToString("MMMM yyyy", DeCulture);
            CalendarDaysGrid.Children.Clear();
            DateTime first = new DateTime(_displayedCalendarMonth.Year, _displayedCalendarMonth.Month, 1);
            int offset = ((int)first.DayOfWeek + 6) % 7;
            DateTime cursor = first.AddDays(-offset);
            DateTime today = DateTime.Today;

            for (int i = 0; i < 42; i++)
            {
                DateTime d = cursor.AddDays(i);
                bool inMonth = d.Month == _displayedCalendarMonth.Month;
                bool isToday = d == today;
                var cell = new Border
                {
                    Width = 34,
                    Height = 30,
                    CornerRadius = new CornerRadius(8),
                    Margin = new Thickness(1),
                    Background = isToday ? Solid("#D91F1708") : Brushes.Transparent,
                    BorderBrush = isToday ? Solid("#F2C5A059") : Brushes.Transparent,
                    BorderThickness = new Thickness(isToday ? 1.2 : 0),
                    Child = new TextBlock
                    {
                        Text = d.Day.ToString(),
                        FontSize = 11.5,
                        FontWeight = isToday ? FontWeights.Bold : FontWeights.SemiBold,
                        Foreground = isToday ? Solid("#FCE7B2") : (inMonth ? (Brush)Resources["TextPrimaryBrush"] : Solid("#6B7280")),
                        HorizontalAlignment = HorizontalAlignment.Center,
                        VerticalAlignment = VerticalAlignment.Center
                    }
                };
                CalendarDaysGrid.Children.Add(cell);
            }
        }

        // =====================================================================
        // 10. SETTINGS, FIREWALL APP SWITCHES & SEELENFUNKE ACCOUNT
        // =====================================================================
        private void SwitchSettingsTab(string tab)
        {
            SettingsDefaultsPanel.Visibility = tab == "defaults" ? Visibility.Visible : Visibility.Collapsed;
            SettingsStoragePanel.Visibility = tab == "storage" ? Visibility.Visible : Visibility.Collapsed;
            SettingsTaskbarPanel.Visibility = tab == "taskbar" ? Visibility.Visible : Visibility.Collapsed;
            SettingsStylePanel.Visibility = tab == "style" ? Visibility.Visible : Visibility.Collapsed;
            SettingsAccountPanel.Visibility = tab == "account" ? Visibility.Visible : Visibility.Collapsed;
            SettingsFortressPanel.Visibility = tab == "fortress" ? Visibility.Visible : Visibility.Collapsed;

            Style gold = (Style)Resources["NobleGoldBtn"];
            Style glass = (Style)Resources["GlassBtn"];
            SetTabDefaultsBtn.Style = tab == "defaults" ? gold : glass;
            SetTabStorageBtn.Style = tab == "storage" ? gold : glass;
            SetTabTaskbarBtn.Style = tab == "taskbar" ? gold : glass;
            SetTabStyleBtn.Style = tab == "style" ? gold : glass;
            SetTabAccountBtn.Style = tab == "account" ? gold : glass;
            SetTabFortressBtn.Style = tab == "fortress" ? gold : glass;

            if (tab == "storage") UpdateStorageAndFunnelSettingsUi();
            if (tab == "taskbar") UpdateTaskbarSettingsButtonsUi();
            if (tab == "account") SelectLoginEnvUi(_settings.ApiEnvironment);
            if (tab == "fortress") { RefreshFortressAuditUi(); AutoPopulateUsbDrivePath(); }
        }

        private void OpenSettingsDefaultsTab_Click(object sender, RoutedEventArgs e) => SwitchSettingsTab("defaults");
        private void OpenSettingsStorageTab_Click(object sender, RoutedEventArgs e) { SwitchSettingsTab("storage"); OpenInternalWindowAnimated("SettingsWin"); }
        private void OpenSettingsTaskbarTab_Click(object sender, RoutedEventArgs e) => SwitchSettingsTab("taskbar");
        private void OpenSettingsStyleTab_Click(object sender, RoutedEventArgs e) => SwitchSettingsTab("style");
        private void OpenSettingsAccountTab_Click(object sender, RoutedEventArgs e) => SwitchSettingsTab("account");
        private void OpenSettingsFortressTab_Click(object sender, RoutedEventArgs e) => SwitchSettingsTab("fortress");

        private void UpdateStorageAndFunnelSettingsUi()
        {
            if (AutoRedirectDownloadsToggleBtn == null) return;
            Style gold = (Style)Resources["NobleGoldBtn"];
            Style glass = (Style)Resources["GlassBtn"];

            AutoRedirectDownloadsToggleBtn.Content = _settings.AutoRedirectStandardDownloadsToFunnel ? "AKTIV" : "AUS";
            AutoRedirectDownloadsToggleBtn.Style = _settings.AutoRedirectStandardDownloadsToFunnel ? gold : glass;

            ZeroGarbageGuardToggleBtn.Content = _settings.ZeroGarbageGuardEnabled ? "AKTIV" : "AUS";
            ZeroGarbageGuardToggleBtn.Style = _settings.ZeroGarbageGuardEnabled ? gold : glass;

            OsVaultAndStorage.DiscoverAndEnsureStorageBindings(_settings);
            StorageBindingsListControl.ItemsSource = null;
            StorageBindingsListControl.ItemsSource = _settings.StorageBindings.ToList();
            RefreshExplorerDrivesSidebar();
        }

        private void ToggleAutoRedirectDownloads_Click(object sender, RoutedEventArgs e)
        {
            _settings.AutoRedirectStandardDownloadsToFunnel = !_settings.AutoRedirectStandardDownloadsToFunnel;
            OsVaultAndStorage.SaveSettings(_settings);
            UpdateStorageAndFunnelSettingsUi();
            ShowToast(_settings.AutoRedirectStandardDownloadsToFunnel
                ? "✓ Downloads werden automatisch in den 8-Säulen-Trichter geleitet"
                : "Downloads-Umleitung pausiert");
        }

        private void ToggleZeroGarbageGuard_Click(object sender, RoutedEventArgs e)
        {
            _settings.ZeroGarbageGuardEnabled = !_settings.ZeroGarbageGuardEnabled;
            OsVaultAndStorage.SaveSettings(_settings);
            if (_settings.ZeroGarbageGuardEnabled)
            {
                OsVaultAndStorage.EnforceStrictTrichterStructureOnMeineDaten();
                OsVaultAndStorage.PurgeZeroGarbageFiles();
            }
            UpdateStorageAndFunnelSettingsUi();
            ShowToast(_settings.ZeroGarbageGuardEnabled
                ? "✓ Kein-Datenmüll-Wächter & 8-Säulen-Zwang aktiv"
                : "Kein-Datenmüll-Wächter pausiert");
        }

        private void OpenStorageBindingInExplorer_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string id) return;
            var binding = _settings.StorageBindings.FirstOrDefault(x => string.Equals(x.Id, id, StringComparison.OrdinalIgnoreCase));
            if (binding == null) return;
            if (!binding.IsOnline)
            {
                ShowToast($"Pfad '{binding.LocalOrUncPath}' aktuell nicht erreichbar");
                return;
            }

            string target = binding.MirrorMeineDatenStructure
                ? OsVaultAndStorage.GetBindingMeineDatenRoot(binding)
                : binding.LocalOrUncPath;
            if (!Directory.Exists(target))
                target = binding.LocalOrUncPath;

            NavigateExplorer(target, recordHistory: true);
            OpenInternalWindowAnimated("ExplorerWin");
        }

        private void ToggleStorageBindingIngest_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string id) return;
            var binding = _settings.StorageBindings.FirstOrDefault(x => string.Equals(x.Id, id, StringComparison.OrdinalIgnoreCase));
            if (binding == null) return;

            binding.AutoIngestToFunnel = !binding.AutoIngestToFunnel;
            OsVaultAndStorage.DiscoverAndEnsureStorageBindings(_settings);
            OsVaultAndStorage.SaveSettings(_settings);
            InitLiveFileSystemWatchers();
            UpdateStorageAndFunnelSettingsUi();
            ShowToast(binding.AutoIngestToFunnel
                ? $"✓ {binding.Name}: Trichter-Eingang aktiv"
                : $"{binding.Name}: Trichter-Eingang AUS");
        }

        private void ToggleStorageBindingMirror_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string id) return;
            var binding = _settings.StorageBindings.FirstOrDefault(x => string.Equals(x.Id, id, StringComparison.OrdinalIgnoreCase));
            if (binding == null) return;

            binding.MirrorMeineDatenStructure = !binding.MirrorMeineDatenStructure;
            OsVaultAndStorage.DiscoverAndEnsureStorageBindings(_settings);
            OsVaultAndStorage.SaveSettings(_settings);
            if (binding.MirrorMeineDatenStructure)
                OsVaultAndStorage.SyncMeineDatenToStorageBindings(_settings);
            UpdateStorageAndFunnelSettingsUi();
            ShowToast(binding.MirrorMeineDatenStructure
                ? $"✓ {binding.Name}: 8-Säulen-Sync aktiv"
                : $"{binding.Name}: 8-Säulen-Sync AUS");
        }

        private void RemoveStorageBinding_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string id) return;
            if (string.Equals(id, "kdrive", StringComparison.OrdinalIgnoreCase))
            {
                ShowToast("kDrive ist als primäre Cloud-Anbindung fest integriert");
                return;
            }
            _settings.StorageBindings.RemoveAll(x => string.Equals(x.Id, id, StringComparison.OrdinalIgnoreCase));
            OsVaultAndStorage.SaveSettings(_settings);
            InitLiveFileSystemWatchers();
            UpdateStorageAndFunnelSettingsUi();
            ShowToast("Anbindung entfernt");
        }

        private void BrowseNewBindingFolder_Click(object sender, RoutedEventArgs e)
        {
            using var dlg = new System.Windows.Forms.FolderBrowserDialog
            {
                Description = "kDrive-, Cloud-, WebDAV- oder Netzwerk-Ordner auswählen",
                UseDescriptionForTitle = true
            };
            if (dlg.ShowDialog() == System.Windows.Forms.DialogResult.OK && !string.IsNullOrWhiteSpace(dlg.SelectedPath))
            {
                NewBindingPathBox.Text = dlg.SelectedPath;
                if (string.IsNullOrWhiteSpace(NewBindingNameBox.Text) || NewBindingNameBox.Text == "kDrive / Cloud")
                    NewBindingNameBox.Text = Path.GetFileName(dlg.SelectedPath.TrimEnd('\\', '/'));
            }
        }

        private void AddStorageBinding_Click(object sender, RoutedEventArgs e)
        {
            string name = (NewBindingNameBox.Text ?? "").Trim();
            string path = (NewBindingPathBox.Text ?? "").Trim();
            if (string.IsNullOrWhiteSpace(path))
            {
                ShowToast("Bitte einen Ordner- oder Laufwerkspfad angeben");
                return;
            }
            if (string.IsNullOrWhiteSpace(name))
                name = Path.GetFileName(path.TrimEnd('\\', '/'));
            if (string.IsNullOrWhiteSpace(name))
                name = "Speicher-Anbindung";

            string provider = name.Contains("kdrive", StringComparison.OrdinalIgnoreCase) || path.Contains("kdrive", StringComparison.OrdinalIgnoreCase)
                ? "kDrive"
                : "Custom";

            _settings.StorageBindings.Add(new OsStorageBindingDto
            {
                Id = $"bind_{Guid.NewGuid():N}".Substring(0, 12),
                Name = name,
                ProviderKind = provider,
                LocalOrUncPath = path,
                AutoIngestToFunnel = true,
                MirrorMeineDatenStructure = true
            });

            NewBindingPathBox.Text = "";
            OsVaultAndStorage.DiscoverAndEnsureStorageBindings(_settings);
            OsVaultAndStorage.SaveSettings(_settings);
            InitLiveFileSystemWatchers();
            UpdateStorageAndFunnelSettingsUi();
            ShowToast($"✓ '{name}' eingebunden & auf 8-Säulen-Trichter geschaltet");
        }

        private void UpdateTaskbarSettingsButtonsUi()
        {
            if (TbPosBottomBtn == null) return;
            Style gold = (Style)Resources["NobleGoldBtn"];
            Style glass = (Style)Resources["GlassBtn"];

            string pos = _settings.TaskbarPosition ?? "Bottom";
            TbPosBottomBtn.Style = pos == "Bottom" ? gold : glass;
            TbPosTopBtn.Style = pos == "Top" ? gold : glass;
            TbPosLeftBtn.Style = pos == "Left" ? gold : glass;
            TbPosRightBtn.Style = pos == "Right" ? gold : glass;

            string align = _settings.TaskbarAlignment ?? "Left";
            TbAlignLeftBtn.Style = align == "Left" ? gold : glass;
            TbAlignCenterBtn.Style = align == "Center" ? gold : glass;
            TbAlignRightBtn.Style = align == "Right" ? gold : glass;

            TaskbarTransparentToggleBtn.Content = _settings.TaskbarTransparentMode ? "AN (SCHWEBEND)" : "AUS";
            TaskbarTransparentToggleBtn.Style = _settings.TaskbarTransparentMode ? gold : glass;
        }

        private void SelectTaskbarPosition_Click(object sender, RoutedEventArgs e)
        {
            if (sender is FrameworkElement fe && fe.Tag is string pos)
                SetTaskbarPositionInternal(pos);
        }

        private void SetTaskbarPositionInternal(string pos)
        {
            _settings.TaskbarPosition = pos;
            OsVaultAndStorage.SaveSettings(_settings);
            ApplyTaskbarLayoutAndStyle();
            UpdateTaskbarSettingsButtonsUi();
            ShowToast($"✓ Taskleiste: {pos switch { "Top" => "Oben", "Left" => "Links", "Right" => "Rechts", _ => "Unten" }}");
        }

        private void SelectTaskbarAlignment_Click(object sender, RoutedEventArgs e)
        {
            if (sender is FrameworkElement fe && fe.Tag is string align)
                SetTaskbarAlignmentInternal(align);
        }

        private void SetTaskbarAlignmentInternal(string align)
        {
            _settings.TaskbarAlignment = align;
            OsVaultAndStorage.SaveSettings(_settings);
            ApplyTaskbarLayoutAndStyle();
            UpdateTaskbarSettingsButtonsUi();
            ShowToast($"✓ Apps in Taskleiste: {align switch { "Center" => "Mittig", "Right" => "Rechts", _ => "Links" }}");
        }

        private void ToggleTaskbarTransparent_Click(object sender, RoutedEventArgs e)
        {
            _settings.TaskbarTransparentMode = !_settings.TaskbarTransparentMode;
            OsVaultAndStorage.SaveSettings(_settings);
            ApplyTaskbarLayoutAndStyle();
            UpdateTaskbarSettingsButtonsUi();
            ShowToast(_settings.TaskbarTransparentMode ? "✓ Transparent-Modus aktiv (Inhalt schwebt)" : "✓ Taskleiste im Glas-Modus");
        }

        private void UpdateFirewallAppButtonsUi()
        {
            Style gold = (Style)Resources["NobleGoldBtn"];
            Style glass = (Style)Resources["GlassBtn"];

            if (FwToggleKDriveBtn != null)
            {
                bool kd = _settings.FirewallExtraApps.GetValueOrDefault("kdrive", true);
                FwToggleKDriveBtn.Content = kd ? "kDrive: FREI" : "kDrive: AUS";
                FwToggleKDriveBtn.Style = kd ? gold : glass;
            }

            bool disc = _settings.FirewallExtraApps.GetValueOrDefault("discord", false);
            FwToggleDiscordBtn.Content = disc ? "Discord: FREI" : "Discord: AUS";
            FwToggleDiscordBtn.Style = disc ? gold : glass;

            bool lol = _settings.FirewallExtraApps.GetValueOrDefault("lol", false);
            FwToggleLolBtn.Content = lol ? "LoL: FREI" : "LoL: AUS";
            FwToggleLolBtn.Style = lol ? gold : glass;

            bool dev = _settings.FirewallExtraApps.GetValueOrDefault("dev", false);
            FwToggleDevBtn.Content = dev ? "PhpStorm/Docker: FREI" : "PhpStorm/Docker: AUS";
            FwToggleDevBtn.Style = dev ? gold : glass;
        }

        private void ToggleFirewallApp_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string key) return;
            bool next = !_settings.FirewallExtraApps.GetValueOrDefault(key, false);
            _settings.FirewallExtraApps[key] = next;
            OsVaultAndStorage.SaveSettings(_settings);
            UpdateFirewallAppButtonsUi();
            bool synced = FortressProvisioningEngine.SyncExtraAppFirewallRuleIfActive(key, next);
            ShowToast(next
                ? (synced ? "✓ Firewall-Freigabe aktiv" : "✓ Freigabe aktiviert")
                : (synced ? "✓ Firewall-Freigabe gesperrt" : "Freigabe geschlossen"));
        }

        private void SelectLoginEnvUi(string env)
        {
            _selectedLoginEnv = (env ?? "live").ToLowerInvariant();
            Style gold = (Style)Resources["NobleGoldBtn"];
            Style glass = (Style)Resources["GlassBtn"];
            EnvLocalBtn.Style = _selectedLoginEnv == "local" ? gold : glass;
            EnvStageBtn.Style = _selectedLoginEnv == "stage" ? gold : glass;
            EnvLiveBtn.Style = _selectedLoginEnv == "live" ? gold : glass;
            UpdateAccountLoginConnectedStateUi();
        }

        private void UpdateAccountLoginConnectedStateUi()
        {
            if (LoginPasswordBox == null || LoginPasswordRevealBox == null || SubmitLoginBtn == null) return;
            bool isConnected = !string.IsNullOrWhiteSpace(OsVaultAndStorage.ResolveBearerToken(_settings, _selectedLoginEnv));
            bool pwdEmpty = string.IsNullOrEmpty(LoginPasswordBox.Password) && string.IsNullOrEmpty(LoginPasswordRevealBox.Text);

            if (LoginPasswordConnectedHintTxt != null)
            {
                LoginPasswordConnectedHintTxt.Visibility = (isConnected && pwdEmpty)
                    ? Visibility.Visible
                    : Visibility.Collapsed;
            }

            SubmitLoginBtn.Content = (isConnected && pwdEmpty)
                ? "✓   ANGEMELDET   ✓"
                : "✦   ANMELDEN   ✦";

            if (LoginStatusMsgTxt != null && isConnected && pwdEmpty && string.IsNullOrWhiteSpace(LoginStatusMsgTxt.Text))
            {
                LoginStatusMsgTxt.Text = $"✓ Angemeldet ({_selectedLoginEnv.ToUpperInvariant()})";
            }
        }

        private void LoginPasswordBox_PasswordChanged(object sender, RoutedEventArgs e) => UpdateAccountLoginConnectedStateUi();
        private void LoginPasswordRevealBox_TextChanged(object sender, TextChangedEventArgs e) => UpdateAccountLoginConnectedStateUi();
        private void LoginPasswordField_GotOrLostFocus(object sender, RoutedEventArgs e) => UpdateAccountLoginConnectedStateUi();

        private async void SelectEnvLocal_Click(object sender, RoutedEventArgs e) => await SwitchActiveEnvIfTokenExistsAsync("local");
        private async void SelectEnvStage_Click(object sender, RoutedEventArgs e) => await SwitchActiveEnvIfTokenExistsAsync("stage");
        private async void SelectEnvLive_Click(object sender, RoutedEventArgs e) => await SwitchActiveEnvIfTokenExistsAsync("live");

        private async Task SwitchActiveEnvIfTokenExistsAsync(string env)
        {
            SelectLoginEnvUi(env);
            if (!string.IsNullOrWhiteSpace(OsVaultAndStorage.ResolveBearerToken(_settings, env)))
            {
                _settings.ApiEnvironment = env;
                OsVaultAndStorage.SaveSettings(_settings);
                LoginStatusMsgTxt.Text = $"✓ Angemeldet ({env.ToUpperInvariant()}) · Tresor-Token aktiv.";
                UpdateAccountLoginConnectedStateUi();
                await RefreshDashboardLiveAsync();
            }
            else
            {
                LoginStatusMsgTxt.Text = "";
                UpdateAccountLoginConnectedStateUi();
            }
        }

        private void ToggleLoginPasswordEye_Click(object sender, RoutedEventArgs e)
        {
            _isLoginPwdVisible = !_isLoginPwdVisible;
            if (_isLoginPwdVisible) { LoginPasswordRevealBox.Text = LoginPasswordBox.Password; LoginPasswordBox.Visibility = Visibility.Collapsed; LoginPasswordRevealBox.Visibility = Visibility.Visible; LoginEyeSlashPath.Visibility = Visibility.Visible; }
            else { LoginPasswordBox.Password = LoginPasswordRevealBox.Text; LoginPasswordRevealBox.Visibility = Visibility.Collapsed; LoginPasswordBox.Visibility = Visibility.Visible; LoginEyeSlashPath.Visibility = Visibility.Collapsed; }
            UpdateAccountLoginConnectedStateUi();
        }

        private async void SubmitLogin_Click(object sender, RoutedEventArgs e)
        {
            string email = LoginEmailBox.Text.Trim();
            string pwd = _isLoginPwdVisible ? LoginPasswordRevealBox.Text : LoginPasswordBox.Password;
            string twoFa = LoginTwoFactorBox.Text.Trim();
            if (string.IsNullOrWhiteSpace(pwd) && !string.IsNullOrWhiteSpace(OsVaultAndStorage.ResolveBearerToken(_settings, _selectedLoginEnv)))
            {
                _settings.ApiEnvironment = _selectedLoginEnv;
                OsVaultAndStorage.SaveSettings(_settings);
                LoginStatusMsgTxt.Text = $"✓ Angemeldet ({_selectedLoginEnv.ToUpperInvariant()}) & aktiv verbunden.";
                UpdateAccountLoginConnectedStateUi();
                await RefreshDashboardLiveAsync();
                return;
            }
            if (string.IsNullOrWhiteSpace(email) || string.IsNullOrWhiteSpace(pwd)) { LoginStatusMsgTxt.Text = "Bitte E-Mail und Passwort eingeben."; return; }

            SubmitLoginBtn.IsEnabled = false;
            LoginStatusMsgTxt.Text = "Verbinde...";
            var res = await SeelenfunkeLiveClient.LoginAsync(_settings, _selectedLoginEnv, email, pwd, twoFa);
            SubmitLoginBtn.IsEnabled = true;

            if (res.Success)
            {
                _settings.SavedEmail = email;
                OsVaultAndStorage.SaveSettings(_settings);
                LoginPasswordBox.Password = "";
                LoginPasswordRevealBox.Text = "";
                LoginTwoFactorBox.Text = "";
                TwoFactorPanel.Visibility = Visibility.Collapsed;
                LoginStatusMsgTxt.Text = $"✓ Angemeldet ({_selectedLoginEnv.ToUpperInvariant()}) & Tresor gekoppelt.";
                UpdateAccountLoginConnectedStateUi();
                await RunMagicPortalAwakeningAsync("SEELENFUNKEN ENTFESSELT");
                await RefreshDashboardLiveAsync();
            }
            else if (res.TwoFactorRequired) { TwoFactorPanel.Visibility = Visibility.Visible; LoginStatusMsgTxt.Text = res.Message; }
            else { LoginStatusMsgTxt.Text = res.Message; }
        }

        private void RefreshFortressAuditUi()
        {
            var audit = FortressProvisioningEngine.AuditSystemState();
            FortressScoreTxt.Text = $"{audit.PassedChecksCount} / 6 AKTIV";
            AuditShellTxt.Text = audit.IsCustomShellActive ? "✓ Aktiv" : "Standard";
            AuditFirewallTxt.Text = audit.IsFortressFirewallActive ? "✓ Aktiv" : "Standard";
            AuditTelemetryTxt.Text = audit.IsTelemetryLocked ? "✓ Gesperrt" : "Offen";
            AuditBloatTxt.Text = audit.RemainingBloatwareCount == 0 ? "✓ 0 Pakete" : $"{audit.RemainingBloatwareCount} Pakete";
            AuditPowerTxt.Text = audit.IsWallPowerPlanActive ? "✓ 24/7" : "Standard";
            AuditKeyTxt.Text = audit.HasWindowsKeyBackup ? "✓ Gesichert" : "Offen";
        }

        private void AutoPopulateUsbDrivePath()
        {
            if (!string.IsNullOrWhiteSpace(UsbTargetPathBox.Text)) return;
            var drives = FortressProvisioningEngine.GetCandidateUsbDrives();
            var rem = drives.FirstOrDefault(d => d.DriveType == DriveType.Removable) ?? drives.FirstOrDefault();
            UsbTargetPathBox.Text = rem != null
                ? rem.RootDirectory.FullName
                : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "MiniPC-USB-Vorbereitung");
        }

        private void DetectUsbDrives_Click(object sender, RoutedEventArgs e) { UsbTargetPathBox.Text = ""; AutoPopulateUsbDrivePath(); }

        private void BrowseUsbFolder_Click(object sender, RoutedEventArgs e)
        {
            using var dlg = new System.Windows.Forms.FolderBrowserDialog { Description = "USB-Stick oder Zielordner wählen", UseDescriptionForTitle = true };
            if (dlg.ShowDialog() == System.Windows.Forms.DialogResult.OK && !string.IsNullOrWhiteSpace(dlg.SelectedPath))
                UsbTargetPathBox.Text = dlg.SelectedPath;
        }

        private async void StartUsbExport_Click(object sender, RoutedEventArgs e)
        {
            string target = UsbTargetPathBox.Text.Trim();
            if (string.IsNullOrWhiteSpace(target)) return;
            RunUsbExportBtn.IsEnabled = false;
            var prog = new Progress<string>(msg => UsbProgressTxt.Text = msg);
            var (ok, message, _) = await FortressProvisioningEngine.ExportCompleteUsbStickAsync(target, prog);
            RunUsbExportBtn.IsEnabled = true;
            UsbProgressTxt.Text = ok ? $"✓ {message}" : message;
            ShowToast(ok ? "✓ USB-Paket bereit" : "Fehler beim Export");
        }

        private void BackupWinKeyNow_Click(object sender, RoutedEventArgs e)
        {
            var (ok, masked, _) = FortressProvisioningEngine.BackupWindowsProductKey();
            RefreshFortressAuditUi();
            ShowToast(ok ? $"✓ Lizenz gesichert ({masked})" : "Fehler");
        }

        private void ToggleCustomShell_Click(object sender, RoutedEventArgs e)
        {
            var audit = FortressProvisioningEngine.AuditSystemState();
            bool enable = !audit.IsCustomShellActive;
            if (enable && MessageBox.Show("Soll DIESER Rechner beim nächsten Start direkt in Funkenwerk OS starten?", "Funkenwerk OS", MessageBoxButton.YesNo, MessageBoxImage.Question) != MessageBoxResult.Yes)
                return;
            FortressProvisioningEngine.SetCustomShellForCurrentUser(enable);
            RefreshFortressAuditUi();
            ShowToast(enable ? "✓ Direkt-Shell aktiviert" : "✓ Windows-Shell aktiv");
        }

        private void RunDebloatAdmin_Click(object sender, RoutedEventArgs e)
        {
            if (MessageBox.Show("Werbe-Apps & Telemetrie auf DIESEM Gerät entfernen?", "Funkenwerk OS", MessageBoxButton.YesNo, MessageBoxImage.Question) == MessageBoxResult.Yes)
                FortressProvisioningEngine.RunElevatedFortressScript("DebloatOnly");
        }

        private void RestoreStandardWindows_Click(object sender, RoutedEventArgs e)
        {
            SetBorderlessFullscreen(false);
            FortressProvisioningEngine.SetCustomShellForCurrentUser(false);
            OsHardwareAndAudioEngine.EnsureWindowsExplorerTaskbarRunning(isShellLaunch: false, forceResetCustomShell: true);
            FortressProvisioningEngine.RunElevatedFortressScript("RestoreWindows");
            FortressProvisioningEngine.StartMaintenanceExplorer();
            RefreshFortressAuditUi();
            ShowToast("✓ Standard-Windows & Taskleiste wiederhergestellt");
        }

        // =====================================================================
        // 11. LIVE LEITSTAND & KEYBOARD SHORTCUTS (INCL. CTRL+C/X/V/A, F2, DEL)
        // =====================================================================
        public string DisplayedLeitstandRevenue => KpiRevenueTxt.Text;
        public string DisplayedLeitstandAvailable => KpiAvailableTxt.Text;
        public string DisplayedLeitstandPriority => PrioOrderMainTxt.Text;
        public bool IsLockScreenActive => LockScreenOverlay.Visibility == Visibility.Visible;

        private async Task RefreshDashboardLiveAsync()
        {
            var snap = await SeelenfunkeLiveClient.FetchDashboardSnapshotAsync(_settings);
            ApplyDashboardSnapshotToUi(snap);
        }

        public void ApplyDashboardSnapshotToUi(OsDashboardSnapshot snap)
        {
            _lastSnapshot = snap;
            bool visitorPrivacy = _settings.VisitorPrivacyMode;

            TrayApiDot.Fill = Solid(snap.IsConnected ? "#10B981" : "#F59E0B");
            StartUserOnlineDot.Fill = Solid(snap.IsConnected ? "#10B981" : "#F59E0B");

            KpiOpenOrdersTxt.Text = snap.OpenOrdersCount.ToString();
            KpiRevenueLabelTxt.Text = string.IsNullOrWhiteSpace(snap.MonthLabel) ? "UMSATZ MONAT" : $"UMSATZ · {snap.MonthLabel.ToUpperInvariant()}";
            KpiRevenueTxt.Text = visitorPrivacy ? "•••" : snap.ShopRevenueFormatted;
            KpiAvailableTxt.Text = visitorPrivacy ? "•••" : snap.AvailableLiquidityFormatted;
            KpiOpenTasksTxt.Text = snap.OpenTasksCount.ToString();

            if (snap.PriorityOrder.HasPriorityOrder)
            {
                if (visitorPrivacy)
                {
                    PrioOrderMainTxt.Text = $"FOKUS: {snap.PriorityOrder.OrderNumber} · Gesperrt · •••";
                }
                else
                {
                    string gdprCust = GewerbeMonitorWindow.AnonymizeCustomerNameGdpr(snap.PriorityOrder.CustomerName);
                    PrioOrderMainTxt.Text = string.IsNullOrWhiteSpace(snap.PriorityOrder.CustomerName)
                        ? $"FOKUS: {snap.PriorityOrder.OrderNumber} · {snap.PriorityOrder.TotalFormatted}"
                        : $"FOKUS: {snap.PriorityOrder.OrderNumber} · {gdprCust} · {snap.PriorityOrder.TotalFormatted}";
                }
            }
            else
            {
                PrioOrderMainTxt.Text = "BESTELLUNGEN";
            }

            var displayOrders = snap.ActiveOrders.Select(o =>
            {
                string gdprName = GewerbeMonitorWindow.AnonymizeCustomerNameGdpr(o.CustomerName);
                string statusText = visitorPrivacy
                    ? (string.IsNullOrWhiteSpace(o.StatusLabel) ? "Gesperrt" : $"{o.StatusLabel} · Gesperrt")
                    : (string.IsNullOrWhiteSpace(o.CustomerName) ? o.StatusLabel : $"{gdprName} · {o.StatusLabel}");
                return new OsOrderItemDto
                {
                    Id = o.Id,
                    OrderUuid = o.OrderUuid,
                    OrderNumber = o.OrderNumber,
                    CustomerName = visitorPrivacy ? "Gesperrt" : gdprName,
                    StatusLabel = statusText,
                    StatusBadgeHex = o.StatusBadgeHex,
                    ItemCount = o.ItemCount,
                    TotalFormatted = visitorPrivacy ? "•••" : o.TotalFormatted,
                    IsExpress = o.IsExpress
                };
            }).ToList();

            OrdersItemsControl.ItemsSource = displayOrders;
            TasksItemsControl.ItemsSource = snap.OpenTasks;

            if (snap.IsConnected)
            {
                var ids = snap.ActiveOrders.Where(o => o.Id > 0).Select(o => o.Id).ToList();
                if (_initialOrdersSeeded && _settings.OrderChimeEnabled && ids.Any(id => !_settings.KnownOpenOrderIds.Contains(id)))
                {
                    GewerbeMonitorWindow.PlayMatteOrderArrivalSound();
                }
                _settings.KnownOpenOrderIds = ids;
                _initialOrdersSeeded = true;
            }
            _secondaryWallWindow?.UpdateFromSnapshot(snap, visitorPrivacy);
            _gewerbeMonitorWindow?.UpdateFromSnapshot(snap, visitorPrivacy);
        }

        public void SetVisitorPrivacyMode(bool enabled)
        {
            _settings.VisitorPrivacyMode = enabled;
            OsVaultAndStorage.SaveSettings(_settings);
            UpdateVisitorPrivacyButtonsUi();
            ApplyDashboardSnapshotToUi(_lastSnapshot);
        }

        private void UpdateVisitorPrivacyButtonsUi()
        {
            Style gold = (Style)Resources["NobleGoldBtn"];
            Style glass = (Style)Resources["GlassBtn"];
            bool active = _settings.VisitorPrivacyMode;
            string label = active ? "Besuchermodus: AKTIV" : "Besuchermodus: AUS";

            if (VisitorModeToggleBtn != null)
            {
                VisitorModeToggleBtn.Content = label;
                VisitorModeToggleBtn.Style = active ? gold : glass;
            }
            if (LeitstandVisitorToggleBtn != null)
            {
                LeitstandVisitorToggleBtn.Content = label;
                LeitstandVisitorToggleBtn.Style = active ? gold : glass;
            }
        }

        private void ToggleVisitorPrivacyMode_Click(object sender, RoutedEventArgs e)
        {
            SetVisitorPrivacyMode(!_settings.VisitorPrivacyMode);
            ShowToast(_settings.VisitorPrivacyMode ? "✓ Besuchermodus aktiv" : "Besuchermodus beendet");
        }

        private async void ManualRefreshDashboard_Click(object sender, RoutedEventArgs e) => await RefreshDashboardLiveAsync();
        private void UpdateOrderChimeButtonUi() => OrderChimeToggleBtn.Content = _settings.OrderChimeEnabled ? "🔔 Ton: AN" : "🔕 Ton: AUS";
        private void ToggleOrderChime_Click(object sender, RoutedEventArgs e) { _settings.OrderChimeEnabled = !_settings.OrderChimeEnabled; UpdateOrderChimeButtonUi(); OsVaultAndStorage.SaveSettings(_settings); }
        private async void AddQuickTask_Click(object sender, RoutedEventArgs e) => await CreateQuickTaskInternalAsync();
        private async void QuickTaskInputBox_KeyDown(object sender, KeyEventArgs e) { if (e.Key == Key.Enter) { e.Handled = true; await CreateQuickTaskInternalAsync(); } }

        private async Task CreateQuickTaskInternalAsync()
        {
            string title = QuickTaskInputBox.Text.Trim();
            if (string.IsNullOrWhiteSpace(title)) return;
            if (await SeelenfunkeLiveClient.CreateTaskAsync(_settings, title))
            {
                QuickTaskInputBox.Text = "";
                await RefreshDashboardLiveAsync();
            }
        }

        private async void CompleteTaskBtn_Click(object sender, RoutedEventArgs e)
        {
            if (sender is FrameworkElement fe && fe.Tag is int id && id > 0 && await SeelenfunkeLiveClient.ToggleTaskAsync(_settings, id, true))
                await RefreshDashboardLiveAsync();
        }

        private async Task RefreshLanInfrastructureAsync() => LanDevicesItemsControl.ItemsSource = await OsHardwareAndAudioEngine.ProbeManufactoryLanDevicesAsync();
        private void ToggleDualHdmi_Click(object sender, RoutedEventArgs e) => OpenOrFocusGewerbeMonitor();

        private void PauseDesktopBackgroundForGewerbeMonitor()
        {
            DesktopSparksLayer.SetRunning(false);
            DesktopSparksLayer.Visibility = Visibility.Collapsed;
            OsRootGrid.Visibility = Visibility.Collapsed;
            _wallpaperLoopTimer.Stop();
            _fastSystemTimer.Stop();
            _dashboardSyncTimer.Stop();
            try { WallpaperVideoA.Pause(); WallpaperVideoB.Pause(); } catch { }
        }

        private void ResumeDesktopBackgroundAfterGewerbeMonitor()
        {
            OsRootGrid.Visibility = Visibility.Visible;
            if (WallpaperPosterImg.Source == null)
                InitAtelierWallpaperEngine();
            ApplyWallpaperAndSparksSettings();
            _fastSystemTimer.Start();
            _dashboardSyncTimer.Start();
            if (!_desktopLiveBootInitialized)
            {
                _desktopLiveBootInitialized = true;
                _ = RefreshLanInfrastructureAsync();
                _ = RefreshDashboardLiveAsync();
            }
        }

        private void OpenOrFocusGewerbeMonitor()
        {
            if (_gewerbeMonitorWindow != null)
            {
                if (_gewerbeMonitorWindow.WindowState == WindowState.Minimized)
                    _gewerbeMonitorWindow.WindowState = WindowState.Maximized;
                PauseDesktopBackgroundForGewerbeMonitor();
                _gewerbeMonitorWindow.Activate();
                _gewerbeMonitorWindow.Focus();
                return;
            }

            PauseDesktopBackgroundForGewerbeMonitor();

            _gewerbeMonitorWindow = new GewerbeMonitorWindow(_settings);
            _gewerbeMonitorWindow.StateChanged += (_, _) =>
            {
                if (_gewerbeMonitorWindow == null) return;
                if (_gewerbeMonitorWindow.WindowState == WindowState.Minimized)
                {
                    ResumeDesktopBackgroundAfterGewerbeMonitor();
                }
                else
                {
                    PauseDesktopBackgroundForGewerbeMonitor();
                }
            };
            _gewerbeMonitorWindow.Closed += (_, _) =>
            {
                _gewerbeMonitorWindow = null;
                ResumeDesktopBackgroundAfterGewerbeMonitor();
                _lastTaskbarSignature = "";
                RefreshTaskbarStrip();
            };
            _gewerbeMonitorWindow.Show();
            _gewerbeMonitorWindow.UpdateFromSnapshot(_lastSnapshot, _settings.VisitorPrivacyMode);
            _gewerbeMonitorWindow.Activate();
        }

        private void OpenOrCloseSecondaryWall(bool forceOpen)
        {
            if (_secondaryWallWindow != null) { if (!forceOpen) { _secondaryWallWindow.Close(); _secondaryWallWindow = null; } return; }
            _secondaryWallWindow = new SecondaryWallWindow(_settings.DarkMode);
            _secondaryWallWindow.Closed += (_, _) => _secondaryWallWindow = null;
            _secondaryWallWindow.Show();
            _secondaryWallWindow.UpdateFromSnapshot(_lastSnapshot, _settings.VisitorPrivacyMode);
        }

        public void LockScreenProgrammatically()
        {
            CloseAllFlyoutsAndMenus();
            _isLockPwdVisible = false;
            LockPasswordBox.Password = "";
            LockPasswordRevealBox.Text = "";
            LockPasswordRevealBox.Visibility = Visibility.Collapsed;
            LockPasswordBox.Visibility = Visibility.Visible;
            LockEyeSlashPath.Visibility = Visibility.Collapsed;
            LockErrorTxt.Text = "";
            LockErrorTxt.Visibility = Visibility.Collapsed;
            LockPromptLabelTxt.Text = !string.IsNullOrWhiteSpace(_settings.EncryptedLockPin)
                ? "SPERRBILDSCHIRM-PIN ODER WINDOWS-PASSWORT"
                : "WINDOWS-PASSWORT";
            LockScreenOverlay.Visibility = Visibility.Visible;
            AnimateOpacity(LockScreenOverlay, 0.0, 1.0, 180);
            LockPasswordBox.Focus();
        }

        private void LockScreenNow_Click(object sender, RoutedEventArgs e) => LockScreenProgrammatically();

        private void NativeLockWorkStation_Click(object sender, RoutedEventArgs e)
        {
            try { LockWorkStation(); } catch { }
        }

        private void ToggleLockPasswordEye_Click(object sender, RoutedEventArgs e)
        {
            _isLockPwdVisible = !_isLockPwdVisible;
            if (_isLockPwdVisible)
            {
                LockPasswordRevealBox.Text = LockPasswordBox.Password;
                LockPasswordBox.Visibility = Visibility.Collapsed;
                LockPasswordRevealBox.Visibility = Visibility.Visible;
                LockEyeSlashPath.Visibility = Visibility.Visible;
                LockPasswordRevealBox.Focus();
                LockPasswordRevealBox.CaretIndex = LockPasswordRevealBox.Text.Length;
            }
            else
            {
                LockPasswordBox.Password = LockPasswordRevealBox.Text;
                LockPasswordRevealBox.Visibility = Visibility.Collapsed;
                LockPasswordBox.Visibility = Visibility.Visible;
                LockEyeSlashPath.Visibility = Visibility.Collapsed;
                LockPasswordBox.Focus();
            }
        }

        private void ToggleLockPinSetupEye_Click(object sender, RoutedEventArgs e)
        {
            _isLockPinSetupVisible = !_isLockPinSetupVisible;
            if (_isLockPinSetupVisible)
            {
                LockPinSetupRevealBox.Text = LockPinSetupBox.Password;
                LockPinSetupBox.Visibility = Visibility.Collapsed;
                LockPinSetupRevealBox.Visibility = Visibility.Visible;
                LockPinSetupEyeSlashPath.Visibility = Visibility.Visible;
            }
            else
            {
                LockPinSetupBox.Password = LockPinSetupRevealBox.Text;
                LockPinSetupRevealBox.Visibility = Visibility.Collapsed;
                LockPinSetupBox.Visibility = Visibility.Visible;
                LockPinSetupEyeSlashPath.Visibility = Visibility.Collapsed;
            }
        }

        private void SaveLockPin_Click(object sender, RoutedEventArgs e)
        {
            string pin = (_isLockPinSetupVisible ? LockPinSetupRevealBox.Text : LockPinSetupBox.Password).Trim();
            if (pin.Length < 4)
            {
                ShowToast("PIN muss mindestens 4 Zeichen lang sein");
                return;
            }
            _settings.EncryptedLockPin = OsVaultAndStorage.ProtectOsVaultSecret(pin);
            OsVaultAndStorage.SaveSettings(_settings);
            LockPinSetupBox.Password = "";
            LockPinSetupRevealBox.Text = "";
            ShowToast("✓ Sperrbildschirm-PIN verschlüsselt gespeichert");
        }

        private void ClearLockPin_Click(object sender, RoutedEventArgs e)
        {
            _settings.EncryptedLockPin = "";
            OsVaultAndStorage.SaveSettings(_settings);
            LockPinSetupBox.Password = "";
            LockPinSetupRevealBox.Text = "";
            ShowToast("Sperrbildschirm-PIN entfernt");
        }

        public static bool VerifyUnlockCredential(string? candidateSecret, OsSettings settings, bool allowWindowsLogonCheck = true)
        {
            if (string.IsNullOrWhiteSpace(candidateSecret) || settings == null)
                return false;

            if (!string.IsNullOrWhiteSpace(settings.EncryptedLockPin))
            {
                byte[]? expectedBytes = null;
                byte[]? candidateBytes = null;
                try
                {
                    string expectedPin = OsVaultAndStorage.UnprotectVaultSecret(settings.EncryptedLockPin);
                    if (!string.IsNullOrEmpty(expectedPin))
                    {
                        expectedBytes = Encoding.UTF8.GetBytes(expectedPin);
                        candidateBytes = Encoding.UTF8.GetBytes(candidateSecret);
                        if (CryptographicOperations.FixedTimeEquals(expectedBytes, candidateBytes))
                            return true;
                    }
                }
                finally
                {
                    if (expectedBytes != null) CryptographicOperations.ZeroMemory(expectedBytes);
                    if (candidateBytes != null) CryptographicOperations.ZeroMemory(candidateBytes);
                }
            }

            if (!allowWindowsLogonCheck)
                return false;

            const int LOGON32_LOGON_INTERACTIVE = 2;
            const int LOGON32_PROVIDER_DEFAULT = 0;
            string user = Environment.UserName;
            string[] domains = new[] { ".", Environment.UserDomainName };
            foreach (string domain in domains.Distinct(StringComparer.OrdinalIgnoreCase))
            {
                IntPtr hToken = IntPtr.Zero;
                try
                {
                    if (LogonUser(user, domain, candidateSecret, LOGON32_LOGON_INTERACTIVE, LOGON32_PROVIDER_DEFAULT, out hToken) && hToken != IntPtr.Zero)
                        return true;
                }
                catch { }
                finally
                {
                    if (hToken != IntPtr.Zero)
                        CloseHandle(hToken);
                }
            }

            return false;
        }

        private async void UnlockWithMagicPortal_Click(object sender, RoutedEventArgs e) => await TryUnlockScreenAsync();

        private async Task TryUnlockScreenAsync()
        {
            string candidate = _isLockPwdVisible ? LockPasswordRevealBox.Text : LockPasswordBox.Password;
            if (!VerifyUnlockCredential(candidate, _settings, allowWindowsLogonCheck: true))
            {
                LockPasswordBox.Password = "";
                LockPasswordRevealBox.Text = "";
                LockErrorTxt.Text = "Zugangsdaten ungültig";
                LockErrorTxt.Visibility = Visibility.Visible;
                return;
            }

            LockPasswordBox.Password = "";
            LockPasswordRevealBox.Text = "";
            LockErrorTxt.Text = "";
            LockErrorTxt.Visibility = Visibility.Collapsed;
            LockScreenOverlay.Visibility = Visibility.Collapsed;
            await RunMagicPortalAwakeningAsync("SEELENFUNKEN ENTFESSELT");
        }

        // =====================================================================
        // 11B. BUILT-IN OS APPS: TASCHENRECHNER, AUSSCHNEIDEN & KURZNOTIZEN
        // =====================================================================
        private void RefreshCalculatorUi()
        {
            if (CalcDisplayTxt == null) return;
            CalcDisplayTxt.Text = _calcEngine.CurrentDisplay;
            CalcExpressionTxt.Text = _calcEngine.ExpressionHeader;
            CalcHistoryList.ItemsSource = null;
            CalcHistoryList.ItemsSource = _calcEngine.History.Take(8).ToList();
        }

        private void CalcKey_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string tag) return;
            ExecuteCalculatorCommand(tag);
        }

        private void ExecuteCalculatorCommand(string tag)
        {
            switch (tag)
            {
                case "C": _calcEngine.ClearAll(); break;
                case "BACK": _calcEngine.Backspace(); break;
                case "SIGN": _calcEngine.ToggleSign(); break;
                case "%": _calcEngine.ApplyPercent(); break;
                case "VAT_ADD": _calcEngine.ApplyPlusVat19(); break;
                case "VAT_SUB": _calcEngine.ApplyNetFromGross19(); break;
                case "COPY":
                    CopyCalculatorDisplayToClipboard();
                    return;
                case ",": _calcEngine.InputDecimal(); break;
                case "00": _calcEngine.InputDigit('0'); _calcEngine.InputDigit('0'); break;
                case "+":
                case "-":
                case "*":
                case "/":
                    _calcEngine.InputOperator(tag);
                    break;
                case "=":
                    _calcEngine.InputEquals();
                    break;
                default:
                    if (tag.Length == 1 && char.IsDigit(tag[0]))
                        _calcEngine.InputDigit(tag[0]);
                    break;
            }
            RefreshCalculatorUi();
        }

        private void CalcCopyDisplay_Click(object sender, MouseButtonEventArgs e) => CopyCalculatorDisplayToClipboard();

        private void CopyCalculatorDisplayToClipboard()
        {
            try
            {
                Clipboard.SetText(_calcEngine.CurrentDisplay);
                ShowToast($"✓ {_calcEngine.CurrentDisplay} kopiert");
            }
            catch { }
        }

        private void CalcHistoryItem_Click(object sender, MouseButtonEventArgs e)
        {
            if (sender is FrameworkElement fe && fe.Tag is CalcHistoryItem item)
            {
                _calcEngine.LoadValueDirect(item.RawValue, item.Expression);
                RefreshCalculatorUi();
                CopyCalculatorDisplayToClipboard();
            }
        }

        // AUSSCHNEIDEN (SNIPPING TOOL)
        private void ToggleSnipDelay_Click(object sender, RoutedEventArgs e)
        {
            _snipDelay3Sec = !_snipDelay3Sec;
            SnipDelayBtn.Content = _snipDelay3Sec ? "⏱ 3 Sek." : "⏱ Sofort";
            SnipDelayBtn.Style = (Style)Resources[_snipDelay3Sec ? "NobleGoldBtn" : "GlassBtn"];
        }

        private async void StartNewSnip_Click(object sender, RoutedEventArgs e)
        {
            bool wasSnipVisible = SnipWin.Visibility == Visibility.Visible;
            SnipWin.Visibility = Visibility.Collapsed;
            CloseAllFlyoutsAndMenus();
            await Dispatcher.Yield(DispatcherPriority.Render);

            if (_snipDelay3Sec)
            {
                for (int s = 3; s >= 1; s--)
                {
                    ShowToast($"✂ Aufnahme in {s}...");
                    await Task.Delay(950);
                }
                OsToastBanner.Visibility = Visibility.Collapsed;
                await Task.Delay(90);
            }
            else
            {
                await Task.Delay(75);
            }

            try
            {
                using var fullBmp = SnipCaptureOverlayWindow.CaptureFullVirtualScreenBitmap();
                var overlay = new SnipCaptureOverlayWindow(fullBmp);
                bool? ok = overlay.ShowDialog();

                if (ok == true && overlay.CapturedBitmapSource != null)
                {
                    _currentSnipBitmap = overlay.CapturedBitmapSource;
                    SnipCapturedImg.Source = _currentSnipBitmap;
                    SnipExportSurface.Width = _currentSnipBitmap.PixelWidth;
                    SnipExportSurface.Height = _currentSnipBitmap.PixelHeight;
                    SnipInkCanvas.Strokes.Clear();
                    SnipEmptyPromptBtn.Visibility = Visibility.Collapsed;
                    SnipPreviewViewbox.Visibility = Visibility.Visible;
                    SnipDimensionsFooterTxt.Text = $"{_currentSnipBitmap.PixelWidth} × {_currentSnipBitmap.PixelHeight} px  ·  Direkt im Zwischenspeicher (bereit für Strg + V)";
                    SyncCurrentSnipToClipboard(showToast: false);
                    OpenInternalWindowAnimated("SnipWin");
                    ShowToast("✓ Ausschnitt im Zwischenspeicher (Strg + V)");
                    return;
                }
            }
            catch { }

            if (wasSnipVisible)
                OpenInternalWindowAnimated("SnipWin");
        }

        private void SelectSnipPen_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not string mode) return;
            Style gold = (Style)Resources["NobleGoldBtn"];
            Style glass = (Style)Resources["GlassBtn"];

            SnipToolNoneBtn.Style = mode == "None" ? gold : glass;
            SnipToolGoldBtn.Style = mode == "Gold" ? gold : glass;
            SnipToolRedBtn.Style = mode == "Red" ? gold : glass;
            SnipToolMarkerBtn.Style = mode == "Marker" ? gold : glass;

            if (mode == "None")
            {
                SnipInkCanvas.EditingMode = InkCanvasEditingMode.None;
                return;
            }

            SnipInkCanvas.EditingMode = InkCanvasEditingMode.Ink;
            var attr = new System.Windows.Ink.DrawingAttributes
            {
                FitToCurve = true
            };

            if (mode == "Gold")
            {
                attr.Color = System.Windows.Media.Color.FromRgb(251, 191, 36);
                attr.Width = 3.8;
                attr.Height = 3.8;
                attr.IsHighlighter = false;
            }
            else if (mode == "Red")
            {
                attr.Color = System.Windows.Media.Color.FromRgb(239, 68, 68);
                attr.Width = 3.8;
                attr.Height = 3.8;
                attr.IsHighlighter = false;
            }
            else if (mode == "Marker")
            {
                attr.Color = System.Windows.Media.Color.FromArgb(140, 250, 204, 21);
                attr.Width = 18.0;
                attr.Height = 24.0;
                attr.StylusTip = System.Windows.Ink.StylusTip.Rectangle;
                attr.IsHighlighter = true;
            }

            SnipInkCanvas.DefaultDrawingAttributes = attr;
        }

        private void SnipInkCanvas_StrokeCollected(object sender, InkCanvasStrokeCollectedEventArgs e)
        {
            SyncCurrentSnipToClipboard(showToast: false);
        }

        private void SnipUndoStroke_Click(object sender, RoutedEventArgs e)
        {
            if (SnipInkCanvas.Strokes.Count > 0)
            {
                SnipInkCanvas.Strokes.RemoveAt(SnipInkCanvas.Strokes.Count - 1);
                SyncCurrentSnipToClipboard(showToast: false);
            }
        }

        private void SnipClearStrokes_Click(object sender, RoutedEventArgs e)
        {
            SnipInkCanvas.Strokes.Clear();
            SyncCurrentSnipToClipboard(showToast: false);
        }

        private void SnipCopyCurrent_Click(object sender, RoutedEventArgs e) => SyncCurrentSnipToClipboard(showToast: true);

        private void SyncCurrentSnipToClipboard(bool showToast)
        {
            if (_currentSnipBitmap == null) return;
            try
            {
                var exported = SnipInkCanvas.Strokes.Count > 0
                    ? SnipCaptureOverlayWindow.RenderAnnotatedSurface(SnipExportSurface, _currentSnipBitmap)
                    : _currentSnipBitmap;
                if (exported != null)
                {
                    Clipboard.SetImage(exported);
                    if (showToast)
                        ShowToast("✓ Im Zwischenspeicher (bereit für Strg + V)");
                }
            }
            catch { }
        }

        private void DiscardCurrentSnipFromMemory()
        {
            _currentSnipBitmap = null;
            if (SnipCapturedImg != null) SnipCapturedImg.Source = null;
            SnipInkCanvas?.Strokes.Clear();
            if (SnipPreviewViewbox != null) SnipPreviewViewbox.Visibility = Visibility.Collapsed;
            if (SnipEmptyPromptBtn != null) SnipEmptyPromptBtn.Visibility = Visibility.Visible;
            if (SnipDimensionsFooterTxt != null)
                SnipDimensionsFooterTxt.Text = "Nach dem Ausschneiden sofort per Strg + V einfügbar (kein Datenmüll)";
        }

        // NOTIZEN (KURZNOTIZEN / STICKY NOTES)
        private void InitStickyNotesUi()
        {
            _stickyNotes = StickyNotesVault.LoadNotes();
            _activeStickyNote = _stickyNotes.FirstOrDefault();
            RefreshStickyNotesListUi();
            LoadActiveStickyIntoEditor();
        }

        private void RefreshStickyNotesListUi()
        {
            string q = (NotesSearchBox?.Text ?? "").Trim();
            IEnumerable<StickyNoteDto> list = _stickyNotes.OrderByDescending(n => n.UpdatedAt);
            if (!string.IsNullOrEmpty(q))
                list = list.Where(n => n.Content.Contains(q, StringComparison.OrdinalIgnoreCase));

            var filtered = list.ToList();
            foreach (var n in _stickyNotes)
                n.IsSelected = (_activeStickyNote != null && n.Id == _activeStickyNote.Id);

            StickyNotesListControl.ItemsSource = null;
            StickyNotesListControl.ItemsSource = filtered;
        }

        private void LoadActiveStickyIntoEditor()
        {
            _suppressStickyEvent = true;
            try
            {
                if (_activeStickyNote == null)
                {
                    ActiveStickyTextBox.Text = "";
                    ActiveStickyTextBox.IsEnabled = false;
                    return;
                }

                ActiveStickyTextBox.IsEnabled = true;
                ActiveStickyTextBox.Text = _activeStickyNote.Content;
                ActiveStickyPadBorder.Background = _activeStickyNote.CardTintBrush;
                ActiveStickyPadBorder.BorderBrush = _activeStickyNote.AccentBrush;
            }
            finally
            {
                _suppressStickyEvent = false;
            }
        }

        private void CreateStickyNote_Click(object sender, RoutedEventArgs e)
        {
            var note = new StickyNoteDto
            {
                ColorKey = "gold",
                Content = "",
                UpdatedAt = DateTime.Now
            };
            _stickyNotes.Insert(0, note);
            _activeStickyNote = note;
            StickyNotesVault.SaveNotes(_stickyNotes);
            RefreshStickyNotesListUi();
            LoadActiveStickyIntoEditor();
            ActiveStickyTextBox.Focus();
        }

        private void StickyNoteCard_Click(object sender, MouseButtonEventArgs e)
        {
            if (sender is not FrameworkElement fe || fe.Tag is not StickyNoteDto dto) return;
            _stickyNoteDragOrigin = e.GetPosition(NotesWin);
            _activeStickyNote = dto;
            foreach (var n in _stickyNotes) n.IsSelected = (n.Id == dto.Id);
            LoadActiveStickyIntoEditor();
            ActiveStickyTextBox.Focus();
        }

        public static string StageStickyNoteForDragExport(StickyNoteDto dto)
        {
            string exportDir = OsVaultAndStorage.StickyDragStagingDir;
            string rawTitle = (dto?.Content ?? "").Split(new[] { '\r', '\n' }, StringSplitOptions.RemoveEmptyEntries).FirstOrDefault()?.Trim() ?? "Notiz";
            if (rawTitle.Length > 36) rawTitle = rawTitle.Substring(0, 36).Trim();
            foreach (char c in Path.GetInvalidFileNameChars()) rawTitle = rawTitle.Replace(c.ToString(), "");
            if (string.IsNullOrWhiteSpace(rawTitle)) rawTitle = "Notiz";
            string tempFile = Path.Combine(exportDir, $"{rawTitle}.txt");

            // Pre-create empty file and apply non-inherited user-only ACL before writing note contents
            using (var fs = new FileStream(tempFile, FileMode.Create, FileAccess.Write, FileShare.None))
            {
            }
            OsVaultAndStorage.LockDownFileAcl(tempFile);
            File.WriteAllText(tempFile, dto?.Content ?? "", new UTF8Encoding(false));
            OsVaultAndStorage.LockDownFileAcl(tempFile);
            return tempFile;
        }

        private void StickyNoteCard_MouseMove(object sender, MouseEventArgs e)
        {
            if (e.LeftButton != MouseButtonState.Pressed || sender is not FrameworkElement fe || fe.Tag is not StickyNoteDto dto)
                return;

            var pos = e.GetPosition(NotesWin);
            if (Math.Abs(pos.X - _stickyNoteDragOrigin.X) > 10 || Math.Abs(pos.Y - _stickyNoteDragOrigin.Y) > 10)
            {
                try
                {
                    string tempFile = StageStickyNoteForDragExport(dto);
                    var data = new DataObject(DataFormats.FileDrop, new[] { tempFile });
                    DragDrop.DoDragDrop(fe, data, DragDropEffects.Copy);
                }
                catch { }
            }
        }

        private void NotesWin_DragOver(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent(DataFormats.FileDrop) || e.Data.GetDataPresent(DataFormats.UnicodeText))
                e.Effects = DragDropEffects.Copy;
            else
                e.Effects = DragDropEffects.None;
            e.Handled = true;
        }

        private void NotesWin_Drop(object sender, DragEventArgs e)
        {
            try
            {
                if (e.Data.GetDataPresent(DataFormats.FileDrop) && e.Data.GetData(DataFormats.FileDrop) is string[] files && files.Length > 0)
                {
                    foreach (string f in files.Where(File.Exists))
                    {
                        var fi = new FileInfo(f);
                        if (fi.Length > 512 * 1024) continue;
                        string content = File.ReadAllText(f);
                        var note = new StickyNoteDto { ColorKey = "gold", Content = content, UpdatedAt = DateTime.Now };
                        _stickyNotes.Insert(0, note);
                        _activeStickyNote = note;
                    }
                    StickyNotesVault.SaveNotes(_stickyNotes);
                    RefreshStickyNotesListUi();
                    LoadActiveStickyIntoEditor();
                    ShowToast("✓ Als Notiz importiert");
                    e.Handled = true;
                }
                else if (e.Data.GetDataPresent(DataFormats.UnicodeText) && e.Data.GetData(DataFormats.UnicodeText) is string txt && !string.IsNullOrWhiteSpace(txt))
                {
                    var note = new StickyNoteDto { ColorKey = "gold", Content = txt, UpdatedAt = DateTime.Now };
                    _stickyNotes.Insert(0, note);
                    _activeStickyNote = note;
                    StickyNotesVault.SaveNotes(_stickyNotes);
                    RefreshStickyNotesListUi();
                    LoadActiveStickyIntoEditor();
                    ShowToast("✓ Text als Notiz angelegt");
                    e.Handled = true;
                }
            }
            catch { }
        }

        private void ActiveStickyTextBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (_suppressStickyEvent || _activeStickyNote == null) return;
            _activeStickyNote.Content = ActiveStickyTextBox.Text;
            _activeStickyNote.UpdatedAt = DateTime.Now;
            StickyNotesVault.SaveNotes(_stickyNotes);
        }

        private void SelectStickyColor_Click(object sender, RoutedEventArgs e)
        {
            if (_activeStickyNote == null || sender is not FrameworkElement fe || fe.Tag is not string colorKey) return;
            _activeStickyNote.ColorKey = colorKey;
            ActiveStickyPadBorder.Background = _activeStickyNote.CardTintBrush;
            ActiveStickyPadBorder.BorderBrush = _activeStickyNote.AccentBrush;
            StickyNotesVault.SaveNotes(_stickyNotes);
        }

        private void CopyStickyNote_Click(object sender, RoutedEventArgs e)
        {
            if (_activeStickyNote == null || string.IsNullOrEmpty(_activeStickyNote.Content)) return;
            try
            {
                Clipboard.SetText(_activeStickyNote.Content);
                ShowToast("✓ Notiz kopiert");
            }
            catch { }
        }

        private void DeleteStickyNote_Click(object sender, RoutedEventArgs e)
        {
            if (_activeStickyNote == null) return;
            _stickyNotes.Remove(_activeStickyNote);
            if (_stickyNotes.Count == 0)
                _stickyNotes.Add(new StickyNoteDto { ColorKey = "gold", Content = "", UpdatedAt = DateTime.Now });

            _activeStickyNote = _stickyNotes[0];
            StickyNotesVault.SaveNotes(_stickyNotes);
            RefreshStickyNotesListUi();
            LoadActiveStickyIntoEditor();
            ShowToast("Notiz gelöscht");
        }

        private void NotesSearchBox_TextChanged(object sender, TextChangedEventArgs e) => RefreshStickyNotesListUi();

        /// <summary>
        /// Evaluates whether a key gesture must be blocked while LockScreenOverlay is visible.
        /// Returns true if the event is swallowed/handled while locked.
        /// </summary>
        public bool ShouldBlockKeyWhileLocked(Key key, ModifierKeys modifiers, IInputElement? focusedElement, out bool triggerUnlockAttempt)
        {
            triggerUnlockAttempt = false;
            if (!IsLockScreenActive)
                return false;

            if (key == Key.Enter && modifiers == ModifierKeys.None)
            {
                triggerUnlockAttempt = true;
                return true;
            }

            bool inLockInput = ReferenceEquals(focusedElement, LockPasswordBox) || ReferenceEquals(focusedElement, LockPasswordRevealBox);
            bool globalShortcutOrSystemKey = key is Key.LWin or Key.RWin or Key.Snapshot or Key.F11 or Key.Escape or Key.System or Key.Apps
                || (modifiers & (ModifierKeys.Alt | ModifierKeys.Windows)) != 0
                || ((modifiers & ModifierKeys.Control) != 0 && key is not (Key.A or Key.Back));

            if (!inLockInput || globalShortcutOrSystemKey)
                return true;

            // Allow typing into LockPasswordBox / LockPasswordRevealBox while still stopping global window handlers below
            return false;
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (IsLockScreenActive)
            {
                if (ShouldBlockKeyWhileLocked(e.Key, Keyboard.Modifiers, Keyboard.FocusedElement, out bool tryUnlock))
                {
                    e.Handled = true;
                    if (tryUnlock)
                        _ = TryUnlockScreenAsync();
                }
                return;
            }

            if (e.Key is Key.LWin or Key.RWin && Keyboard.Modifiers == ModifierKeys.None)
            {
                e.Handled = true;
                OpenInstantWindowsKeySearch();
                return;
            }

            if (e.Key == Key.Snapshot || (e.Key == Key.S && (Keyboard.Modifiers & (ModifierKeys.Control | ModifierKeys.Shift)) == (ModifierKeys.Control | ModifierKeys.Shift)))
            {
                e.Handled = true;
                StartNewSnip_Click(this, new RoutedEventArgs());
                return;
            }

            bool textInputFocused = Keyboard.FocusedElement is TextBox or PasswordBox;

            if (e.Key == Key.F11)
            {
                SetBorderlessFullscreen(!_isFullscreenBorderless);
                e.Handled = true;
                return;
            }

            if (e.Key == Key.Escape)
            {
                ExplorerModalOverlay.Visibility = Visibility.Collapsed;
                CloseAllFlyoutsAndMenus();
                CommitInlineRenameIfNeeded();
                return;
            }

            if (textInputFocused)
            {
                if (e.Key == Key.S && (Keyboard.Modifiers & ModifierKeys.Control) != 0 && ViewerWin.Visibility == Visibility.Visible && !string.IsNullOrEmpty(_openNoteFilePath))
                {
                    SaveOpenNote_Click(this, new RoutedEventArgs());
                    e.Handled = true;
                }
                return;
            }

            bool viewerActive = ViewerWin.Visibility == Visibility.Visible && Panel.GetZIndex(ViewerWin) == _topWindowZIndex && LightboxImageHost.Visibility == Visibility.Visible;
            if (viewerActive)
            {
                if (e.Key == Key.Left) { StepLightboxImage(-1); e.Handled = true; return; }
                if (e.Key == Key.Right) { StepLightboxImage(+1); e.Handled = true; return; }
                if (e.Key == Key.D0 && (Keyboard.Modifiers & ModifierKeys.Control) != 0) { LightboxZoomReset_Click(this, new RoutedEventArgs()); e.Handled = true; return; }
            }

            bool calcActive = CalculatorWin.Visibility == Visibility.Visible && Panel.GetZIndex(CalculatorWin) == _topWindowZIndex;
            if (calcActive)
            {
                if ((Keyboard.Modifiers & ModifierKeys.Control) != 0 && e.Key == Key.C)
                {
                    CopyCalculatorDisplayToClipboard();
                    e.Handled = true;
                    return;
                }

                string? calcCmd = e.Key switch
                {
                    >= Key.D0 and <= Key.D9 when Keyboard.Modifiers == ModifierKeys.None => ((char)('0' + (e.Key - Key.D0))).ToString(),
                    >= Key.NumPad0 and <= Key.NumPad9 => ((char)('0' + (e.Key - Key.NumPad0))).ToString(),
                    Key.Add or Key.OemPlus => "+",
                    Key.Subtract or Key.OemMinus => "-",
                    Key.Multiply => "*",
                    Key.Divide => "/",
                    Key.Decimal or Key.OemComma or Key.OemPeriod => ",",
                    Key.Enter => "=",
                    Key.Back => "BACK",
                    Key.Delete => "C",
                    _ => null
                };
                if (calcCmd != null)
                {
                    ExecuteCalculatorCommand(calcCmd);
                    e.Handled = true;
                    return;
                }
            }

            bool explorerActive = ExplorerWin.Visibility == Visibility.Visible && Panel.GetZIndex(ExplorerWin) == _topWindowZIndex;

            if (explorerActive && (Keyboard.Modifiers & ModifierKeys.Alt) != 0)
            {
                if (e.Key == Key.System && e.SystemKey == Key.Left) { ExplorerBack_Click(this, new RoutedEventArgs()); e.Handled = true; return; }
                if (e.Key == Key.System && e.SystemKey == Key.Right) { ExplorerForward_Click(this, new RoutedEventArgs()); e.Handled = true; return; }
                if (e.Key == Key.System && e.SystemKey == Key.Up) { ExplorerUp_Click(this, new RoutedEventArgs()); e.Handled = true; return; }
            }

            if ((Keyboard.Modifiers & ModifierKeys.Control) != 0)
            {
                if (e.Key == Key.N && (Keyboard.Modifiers & ModifierKeys.Shift) != 0 && explorerActive)
                {
                    ExplorerNewFolder_Click(this, new RoutedEventArgs());
                    e.Handled = true;
                    return;
                }
                if (e.Key == Key.F && explorerActive)
                {
                    ExplorerSearchBox.Focus();
                    ExplorerSearchBox.SelectAll();
                    e.Handled = true;
                    return;
                }
                if (e.Key == Key.C)
                {
                    var paths = explorerActive
                        ? _selectedExplorerPaths.ToList()
                        : _desktopItems.Where(x => _selectedDesktopIds.Contains(x.Id) && x.IsDeletable && !string.IsNullOrEmpty(x.FullFilePath)).Select(x => x.FullFilePath!).ToList();
                    if (paths.Count > 0) { SetFileClipboard(paths, isCut: false); e.Handled = true; }
                }
                else if (e.Key == Key.X)
                {
                    var paths = explorerActive
                        ? _selectedExplorerPaths.ToList()
                        : _desktopItems.Where(x => _selectedDesktopIds.Contains(x.Id) && x.IsDeletable && !string.IsNullOrEmpty(x.FullFilePath)).Select(x => x.FullFilePath!).ToList();
                    if (paths.Count > 0) { SetFileClipboard(paths, isCut: true); e.Handled = true; }
                }
                else if (e.Key == Key.V)
                {
                    PasteClipboardIntoFolder(explorerActive ? _currentExplorerDir : OsVaultAndStorage.OsDesktopDir);
                    e.Handled = true;
                }
                else if (e.Key == Key.A && explorerActive)
                {
                    foreach (var entry in _visibleExplorerEntries) _selectedExplorerPaths.Add(entry.FullPath);
                    UpdateExplorerSelectionAndClipboardFast();
                    e.Handled = true;
                }
                else if (e.Key == Key.L)
                {
                    LockScreenOverlay.Visibility = Visibility.Visible;
                    e.Handled = true;
                }
                return;
            }

            if (explorerActive)
            {
                if (e.Key == Key.Enter && _selectedExplorerPaths.Count > 0)
                {
                    string first = _selectedExplorerPaths.First();
                    if (Directory.Exists(first)) NavigateExplorer(first, recordHistory: true);
                    else if (File.Exists(first)) OpenFileWithConfiguredDefaultProgram(first);
                    e.Handled = true;
                    return;
                }
                if (e.Key == Key.Back)
                {
                    if (_explorerBackStack.Count > 0) ExplorerBack_Click(this, new RoutedEventArgs());
                    else ExplorerUp_Click(this, new RoutedEventArgs());
                    e.Handled = true;
                    return;
                }
                if (e.Key == Key.F5)
                {
                    NavigateExplorer(_currentExplorerDir, recordHistory: false);
                    e.Handled = true;
                    return;
                }
            }
            else if (e.Key == Key.Enter && _selectedDesktopIds.Count == 1 && _renamingItem == null)
            {
                var item = _desktopItems.FirstOrDefault(x => x.Id == _selectedDesktopIds.First());
                if (item != null) { ActivateDesktopItem(item); e.Handled = true; return; }
            }
            else if (e.Key == Key.F5)
            {
                RebuildDesktopIcons();
                e.Handled = true;
                return;
            }

            if (e.Key == Key.F2)
            {
                if (explorerActive && _selectedExplorerPaths.Count == 1)
                {
                    OpenExplorerRenameModal(_selectedExplorerPaths.First());
                    e.Handled = true;
                }
                else if (_selectedDesktopIds.Count == 1 && _renamingItem == null)
                {
                    var item = _desktopItems.FirstOrDefault(x => x.Id == _selectedDesktopIds.First());
                    if (item != null && item.IsRenamable) { BeginInlineRename(item); e.Handled = true; }
                }
            }
            else if (e.Key == Key.Delete)
            {
                if (explorerActive && _selectedExplorerPaths.Count > 0)
                {
                    foreach (string p in _selectedExplorerPaths.ToList()) MovePathToRecycleBin(p);
                    e.Handled = true;
                }
                else if (_selectedDesktopIds.Count > 0)
                {
                    foreach (string id in _selectedDesktopIds.ToList())
                    {
                        var item = _desktopItems.FirstOrDefault(x => x.Id == id);
                        if (item == null) continue;

                        if (item.IsDeletable && !string.IsNullOrEmpty(item.FullFilePath))
                            MovePathToRecycleBin(item.FullFilePath);
                        else if (item.IsShortcutRemovable)
                            RemoveShortcutFromDesktop(item.Id, item.Title);
                    }
                    e.Handled = true;
                }
            }
        }

        private void PowerReboot_Click(object sender, RoutedEventArgs e)
        {
            if (MessageBox.Show("Gerät jetzt neu starten?", "Funkenwerk OS", MessageBoxButton.YesNo, MessageBoxImage.Question) == MessageBoxResult.Yes)
                OsHardwareAndAudioEngine.TriggerSystemReboot();
        }

        private void PowerShutdown_Click(object sender, RoutedEventArgs e)
        {
            if (_isShellBoot)
            {
                var res = MessageBox.Show(
                    "Möchtest du das Gerät in die automatische Nachtruhe versetzen?\n\n" +
                    "• [Ja] = Nachtruhe (Schaltet sich jetzt lautlos aus & startet morgen um 08:00 Uhr automatisch)\n" +
                    "• [Nein] = Komplett ausschalten (Manueller Start am Gehäuse nötig)\n" +
                    "• [Abbrechen] = Eingeschaltet lassen",
                    "Funkenwerk OS · Nachtruhe & Morgen-Automatik",
                    MessageBoxButton.YesNoCancel,
                    MessageBoxImage.Question);

                if (res == MessageBoxResult.Yes)
                    OsHardwareAndAudioEngine.TriggerNightSleepUntilMorning0800();
                else if (res == MessageBoxResult.No)
                    OsHardwareAndAudioEngine.TriggerSystemShutdown();
                return;
            }

            if (MessageBox.Show("Gerät jetzt herunterfahren?", "Funkenwerk OS", MessageBoxButton.YesNo, MessageBoxImage.Question) == MessageBoxResult.Yes)
                OsHardwareAndAudioEngine.TriggerSystemShutdown();
        }

        private void PowerExitApp_Click(object sender, RoutedEventArgs e) => Close();

        // =====================================================================
        // 12. 60 FPS ANIMATION HELPERS & ZERO-ALLOCATION FROZEN BRUSH CACHE
        // =====================================================================
        private void ShowToast(string message)
        {
            OsToastTxt.Text = message;
            OsToastBanner.Visibility = Visibility.Visible;
            AnimateOpacity(OsToastBanner, 0.0, 1.0, 140);
            _toastTimer.Stop();
            _toastTimer.Start();
        }

        private static void AnimateScale(ScaleTransform st, double target, int durationMs)
        {
            var anim = new DoubleAnimation(target, TimeSpan.FromMilliseconds(durationMs)) { EasingFunction = SmoothEaseOut };
            st.BeginAnimation(ScaleTransform.ScaleXProperty, anim);
            st.BeginAnimation(ScaleTransform.ScaleYProperty, anim);
        }

        private static void AnimateOpacity(UIElement el, double from, double to, int durationMs)
        {
            el.BeginAnimation(OpacityProperty, new DoubleAnimation(from, to, TimeSpan.FromMilliseconds(durationMs)) { EasingFunction = SmoothEaseOut });
        }

        private static void AnimateCanvasPosition(UIElement el, double toLeft, double toTop, int durationMs)
        {
            el.BeginAnimation(Canvas.LeftProperty, new DoubleAnimation(Canvas.GetLeft(el), toLeft, TimeSpan.FromMilliseconds(durationMs)) { EasingFunction = SmoothEaseOut });
            el.BeginAnimation(Canvas.TopProperty, new DoubleAnimation(Canvas.GetTop(el), toTop, TimeSpan.FromMilliseconds(durationMs)) { EasingFunction = SmoothEaseOut });
        }

        private static SolidColorBrush Solid(string hex)
        {
            return s_frozenBrushCache.GetOrAdd(hex, static h =>
            {
                var b = new SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString(h));
                b.Freeze();
                return b;
            });
        }
    }
}

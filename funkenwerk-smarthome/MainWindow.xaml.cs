using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.IO.Pipes;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Security.AccessControl;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Controls.Primitives;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using Ellipse = System.Windows.Shapes.Ellipse;
using System.Windows.Threading;
using Funkenwerk;

namespace FunkenwerkSmartHome
{
    public partial class MainWindow : Window
    {
        public const string PipeName = "Funkenwerk_SmartHomePipe_v1";
        private const int MaxPipePayloadBytes = 32768;

        private string _activeTab = "shutters";
        private bool _isDarkMode = true;
        private string _modalMode = "";
        private string? _editingGroupId;
        private string? _editingCameraId;
        private bool _isSecretVisible;
        private bool _suppressSecretSync;
        private bool _isCamRtspVisible;
        private bool _suppressCamRtspSync;
        private bool _camModalPreferHd;

        private SmartHomePersistedData _smartHomeData = new();
        private TahomaPersistedConfig _tahomaConfig = new();
        private readonly Dictionary<string, CameraStreamWorker> _cameraWorkers = new(StringComparer.OrdinalIgnoreCase);
        private readonly Dictionary<string, Image> _cameraImages = new(StringComparer.OrdinalIgnoreCase);
        private readonly Dictionary<string, TextBlock> _cameraStatusLabels = new(StringComparer.OrdinalIgnoreCase);
        private readonly Dictionary<string, Ellipse> _cameraStatusDots = new(StringComparer.OrdinalIgnoreCase);
        private readonly ConcurrentDictionary<string, int> _cameraFrameRenderGate = new(StringComparer.OrdinalIgnoreCase);
        private readonly HashSet<string> _expandedPtzPanels = new(StringComparer.OrdinalIgnoreCase);
        private readonly List<string> _discoveredCameraIps = new();
        private readonly CameraLiveAudioPlayer _cameraAudioPlayer = new();
        private int _previousMultiGridCount = 4;
        private string _ptzStepMode = "fine"; // micro | fine | normal | wide
        private DispatcherTimer? _ptzHoldTimer;
        private SmartCameraConfig? _ptzHoldCamera;
        private double _ptzHoldPanDelta;
        private double _ptzHoldTiltDelta;
        private DispatcherTimer? _smartClipTimer;
        private SmartRecordingItem? _currentSmartMediaItem;
        private bool _isSmartClipPlaying;
        private bool _isDraggingSmartClipSlider;
        private double _smartClipSpeed = 1.0;

        private readonly HashSet<string> _modalExtraShutterNames = new(StringComparer.OrdinalIgnoreCase);
        private CancellationTokenSource? _pipeCts;
        private CancellationTokenSource? _portalRelayCts;
        private static readonly HttpClient _portalHttp = FunkenwerkSecurityVault.CreateSecureHttpClient(TimeSpan.FromSeconds(8));

        public MainWindow(string[]? args = null)
        {
            InitializeComponent();
            if (args != null && args.Any(a => a.Equals("--groups", StringComparison.OrdinalIgnoreCase)))
                _activeTab = "groups";
            else if (args != null && args.Any(a => a.Equals("--plugs", StringComparison.OrdinalIgnoreCase)))
                _activeTab = "plugs";
            else if (args != null && args.Any(a => a.Equals("--cameras", StringComparison.OrdinalIgnoreCase)))
                _activeTab = "cameras";
        }

        private async void Window_Loaded(object sender, RoutedEventArgs e)
        {
            LoadPersistedConfigs();
            ApplyTheme(_tahomaConfig.IsDarkMode);
            SwitchTabUI(_activeTab);
            RenderCameraRecordingsSidebar();
            StartIpcPipeServer();
            StartPortalRelayPoller();
            await RefreshAllAsync();
        }

        private void Window_Closed(object? sender, EventArgs e)
        {
            StopPtzHoldLoop(sendStop: false);
            try { _smartClipTimer?.Stop(); } catch { }
            try { SmartClipMediaElement.Stop(); SmartClipMediaElement.Source = null; } catch { }
            try { _cameraAudioPlayer.Dispose(); } catch { }
            try { _pipeCts?.Cancel(); } catch { }
            try { _portalRelayCts?.Cancel(); } catch { }
            foreach (var kv in _cameraWorkers)
            {
                try { kv.Value.Dispose(); } catch { }
            }
            _cameraWorkers.Clear();
        }

        private void LoadPersistedConfigs()
        {
            _tahomaConfig = SomfyTahomaLocalEngine.LoadConfig();
            try
            {
                string shPath = FunkenwerkSecurityVault.GetAppDataFilePath("smarthome.json", migrateFromBaseDir: true);
                if (File.Exists(shPath))
                {
                    string raw = File.ReadAllText(shPath, Encoding.UTF8);
                    var loaded = JsonSerializer.Deserialize<SmartHomePersistedData>(raw);
                    if (loaded != null && (loaded.Cameras.Count > 0 || loaded.Plugs.Count > 0 || loaded.Groups.Count > 0 || !string.IsNullOrWhiteSpace(loaded.TapoLocalEmail)))
                    {
                        loaded.TapoLocalEmail = FunkenwerkSecurityVault.UnprotectSecret(loaded.TapoLocalEmail);
                        loaded.TapoLocalPassword = FunkenwerkSecurityVault.UnprotectSecret(loaded.TapoLocalPassword);
                        foreach (var cam in loaded.Cameras)
                        {
                            cam.Url = FunkenwerkSecurityVault.UnprotectSecret(cam.Url);
                        }
                        _smartHomeData = loaded;
                    }
                    FunkenwerkSecurityVault.LockDownFileAcl(shPath);
                }
            }
            catch { }

            if (_smartHomeData.Plugs.Count == 0)
            {
                _smartHomeData.Plugs.Add(new SmartPlugConfig { Name = "Steckdose 1", IpAddress = "192.168.188.109", Model = "Tapo P100" });
                _smartHomeData.Plugs.Add(new SmartPlugConfig { Name = "Steckdose 2", IpAddress = "192.168.188.110", Model = "Tapo P100" });
                _smartHomeData.Plugs.Add(new SmartPlugConfig { Name = "Lichterkette Spiegel", IpAddress = "192.168.188.123", Model = "Tapo P100" });
                SaveSmartHomeConfig();
            }
        }

        private void SaveSmartHomeConfig(bool syncPortal = true)
        {
            try
            {
                string shPath = FunkenwerkSecurityVault.GetAppDataFilePath("smarthome.json");
                var copy = JsonSerializer.Deserialize<SmartHomePersistedData>(JsonSerializer.Serialize(_smartHomeData)) ?? new SmartHomePersistedData();
                copy.TapoLocalEmail = FunkenwerkSecurityVault.ProtectSecret(FunkenwerkSecurityVault.UnprotectSecret(_smartHomeData.TapoLocalEmail));
                copy.TapoLocalPassword = FunkenwerkSecurityVault.ProtectSecret(FunkenwerkSecurityVault.UnprotectSecret(_smartHomeData.TapoLocalPassword));
                foreach (var cam in copy.Cameras)
                {
                    cam.Url = FunkenwerkSecurityVault.ProtectSecret(FunkenwerkSecurityVault.UnprotectSecret(cam.Url));
                }
                string json = JsonSerializer.Serialize(copy, new JsonSerializerOptions { WriteIndented = true });
                FunkenwerkSecurityVault.WriteSecureFileText(shPath, json);

                if (syncPortal && !FunkenwerkSecurityVault.IsUsingTemporaryAppData)
                {
                    _ = SyncInventoryToPortalAsync();
                }
            }
            catch { }
        }

        private void SetStatus(string text, string hexDot = "#10B981")
        {
            StatusBannerTxt.Text = text;
            StatusDotEllipse.Fill = BrushHex(hexDot);
        }

        private static SolidColorBrush BrushHex(string hex) =>
            new((Color)ColorConverter.ConvertFromString(hex));

        // =====================================================================
        // THEME (100 % DARK & LIGHT MODE)
        // =====================================================================
        public bool IsDarkModeActive => _isDarkMode;

        public void ApplyThemeForSelfTest(bool dark) => ApplyTheme(dark);

        private void ThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            ApplyTheme(!_isDarkMode);
            _tahomaConfig.IsDarkMode = _isDarkMode;
            SomfyTahomaLocalEngine.SaveConfig(_tahomaConfig);
        }

        private void ApplyTheme(bool dark)
        {
            _isDarkMode = dark;
            ThemeToggleBtn.Content = dark ? "🌙" : "☀";
            string rootBg = dark ? "#0F1117" : "#F3F4F6";
            string topBg = dark ? "#0A0C10" : "#FFFFFF";
            string cardBg = dark ? "#151821" : "#FFFFFF";
            string borderHex = dark ? "#262A36" : "#E5E7EB";
            string primaryFg = dark ? "#F9FAFB" : "#111827";
            string mutedLabelFg = dark ? "#9CA3AF" : "#4B5563";
            string secBtnBg = dark ? "#1E222D" : "#F3F4F6";
            string secBtnBorder = dark ? "#374151" : "#D1D5DB";
            string topBtnBg = dark ? "#161922" : "#F9FAFB";
            string goldAccentBg = dark ? "#26231F" : "#FEF9EC";
            string goldAccentFg = dark ? "#C5A059" : "#8A6522";
            string inputBg = dark ? "#0F1117" : "#F9FAFB";
            string inputBorder = dark ? "#262A36" : "#D1D5DB";

            Background = BrushHex(rootBg);
            Foreground = BrushHex(primaryFg);
            RootGrid.Background = BrushHex(rootBg);
            TopBarBorder.Background = BrushHex(topBg);
            TopBarBorder.BorderBrush = BrushHex(borderHex);
            HeaderTitleTxt.Foreground = BrushHex(dark ? "#C5A059" : "#8A6522");
            StatusBannerBorder.Background = BrushHex(cardBg);
            StatusBannerBorder.BorderBrush = BrushHex(borderHex);
            StatusBannerTxt.Foreground = BrushHex(primaryFg);

            foreach (var btn in new[] { RefreshTopBtn, ThemeToggleBtn })
            {
                btn.Background = BrushHex(topBtnBg);
                btn.Foreground = BrushHex(primaryFg);
                btn.BorderBrush = BrushHex(secBtnBorder);
            }

            foreach (var card in new[] { ShutterHeaderCard, GroupsHeaderCard, PlugsHeaderCard, CamerasHeaderCard, CameraListSidebarBorder, ModalCardBorder, GroupModalCardBorder, CameraModalCardBorder })
            {
                card.Background = BrushHex(cardBg);
                if (card != ModalCardBorder && card != GroupModalCardBorder && card != CameraModalCardBorder) card.BorderBrush = BrushHex(borderHex);
            }
            CameraViewportBorder.BorderBrush = BrushHex(borderHex);
            CamSidebarDividerBorder.BorderBrush = BrushHex(borderHex);
            SmartPlayerTopBarBorder.Background = BrushHex(dark ? "#11151E" : "#FFFFFF");
            SmartPlayerTopBarBorder.BorderBrush = BrushHex(borderHex);
            SmartClipTransportBar.Background = BrushHex(dark ? "#11151E" : "#FFFFFF");
            SmartClipTransportBar.BorderBrush = BrushHex(borderHex);
            SmartPlayerTitleTxt.Foreground = BrushHex(primaryFg);
            SmartPlayerSubTxt.Foreground = BrushHex(mutedLabelFg);
            SmartClipPosTxt.Foreground = BrushHex(primaryFg);
            SmartClipDurTxt.Foreground = BrushHex(mutedLabelFg);
            SmartPlayerBadgeBorder.Background = BrushHex(goldAccentBg);
            SmartPlayerBadgeBorder.BorderBrush = BrushHex("#C5A059");
            SmartPlayerBadgeTxt.Foreground = BrushHex(goldAccentFg);

            foreach (var txt in new[] { TahomaGatewayTitleTxt, GroupsTitleTxt, PlugsTitleTxt, CamerasTitleTxt, ModalTitleTxt, GroupModalTitleTxt, CamModalTitleTxt })
            {
                txt.Foreground = BrushHex(primaryFg);
            }

            foreach (var badge in new[] { PinBadgeBorder, CamerasOnlineBadgeBorder })
            {
                badge.Background = BrushHex(goldAccentBg);
                badge.BorderBrush = BrushHex("#C5A059");
            }
            TahomaPinBadgeTxt.Foreground = BrushHex(goldAccentFg);
            CamerasOnlineBadgeTxt.Foreground = BrushHex(goldAccentFg);
            CamSidebarCountTxt.Foreground = BrushHex(goldAccentFg);
            CamRefreshRecBtn.Foreground = BrushHex(goldAccentFg);
            CamModalSensValueTxt.Foreground = BrushHex(goldAccentFg);

            AllShuttersOpenBtn.Background = BrushHex(dark ? "#162A22" : "#ECFDF5");
            AllShuttersOpenBtn.Foreground = BrushHex(dark ? "#34D399" : "#047857");
            AllShuttersOpenBtn.BorderBrush = BrushHex("#10B981");

            foreach (var btn in new[] { AllShuttersStopBtn, AllShuttersCloseBtn, QuickAddGroupBtn, ScanLanPlugsBtn, AddPlugBtn, CamScanLanBtn, CamOpenRecFolderBtn, SmartPlayerRevealBtn, SmartClipReplayBtn })
            {
                btn.Background = BrushHex(secBtnBg);
                btn.Foreground = BrushHex(primaryFg);
                btn.BorderBrush = BrushHex(secBtnBorder);
            }

            foreach (var btn in new[] { ConnectConnexoonBtn, AddGroupBtn, TapoAuthBtn, AddCameraBtn, GroupAddCustomShutterBtn, CamModalTestBtn, SmartClipSpeedBtn })
            {
                btn.Background = BrushHex(goldAccentBg);
                btn.Foreground = BrushHex(goldAccentFg);
                btn.BorderBrush = BrushHex("#C5A059");
            }

            foreach (var lbl in new[]
            {
                Field1Label, Field2Label, Field3Label, SecretFieldLabel,
                GroupNameFieldLabel, GroupShuttersSectionLabel, GroupPlugsSectionLabel,
                CamSidebarListHeaderTxt, CamRecordingsHeaderTxt,
                CamModalNameLabel, CamModalModelLabel, CamModalRtspLabel, CamModalOnvifLabel, CamModalDescLabel, CamModalSensLabel
            })
            {
                lbl.Foreground = BrushHex(mutedLabelFg);
            }
            foreach (var box in new[]
            {
                Field1Box, Field2Box, Field3Box, GroupNameInputBox, GroupCustomShutterBox,
                CamModalNameBox, CamModalModelBox, CamModalOnvifUrlBox, CamModalDescBox
            })
            {
                box.Background = BrushHex(inputBg);
                box.Foreground = BrushHex(primaryFg);
                box.BorderBrush = BrushHex(inputBorder);
            }
            foreach (var bd in new[] { SecretInputBorder, GroupShuttersListBorder, GroupPlugsListBorder, CamModalRtspInputBorder, CamModalOptionsBorder })
            {
                bd.Background = BrushHex(inputBg);
                bd.BorderBrush = BrushHex(inputBorder);
            }
            ModalSecretPasswordBox.Foreground = BrushHex(primaryFg);
            ModalSecretVisibleBox.Foreground = BrushHex(primaryFg);
            CamModalRtspPasswordBox.Foreground = BrushHex(primaryFg);
            CamModalRtspVisibleBox.Foreground = BrushHex(primaryFg);
            foreach (var cb in new[] { CamModalMotionCheck, CamModalHasPtzCheck, CamModalInvertPanCheck, CamModalInvertTiltCheck })
            {
                cb.Foreground = BrushHex(primaryFg);
            }

            ModalFeedbackTxt.Foreground = BrushHex(dark ? "#FCA5A5" : "#DC2626");
            GroupModalFeedbackTxt.Foreground = BrushHex(dark ? "#FCA5A5" : "#DC2626");
            CamModalFeedbackTxt.Foreground = BrushHex(dark ? "#FCA5A5" : "#DC2626");
            foreach (var cancelBtn in new[] { CloseModalBtn, GroupModalCancelBtn, CamModalCancelBtn })
            {
                cancelBtn.Background = BrushHex(dark ? "#1E222D" : "#E5E7EB");
                cancelBtn.Foreground = BrushHex(dark ? "#D1D5DB" : "#374151");
            }
            foreach (var delBtn in new[] { GroupModalDeleteBtn, CamModalDeleteBtn, SmartPlayerDeleteBtn })
            {
                delBtn.Background = BrushHex(dark ? "#2D161B" : "#FEE2E2");
                delBtn.Foreground = BrushHex(dark ? "#F87171" : "#B91C1C");
            }

            UpdateCameraModalStreamPillsTheme();
            UpdateCameraGridButtonsTheme();
            SwitchTabUI(_activeTab);
            RenderShutters();
            RenderGroups();
            RenderPlugs();
            RenderCamerasSidebar();
            RenderCameraRecordingsSidebar();
        }

        private void UpdateCameraGridButtonsTheme()
        {
            int count = NormalizeCameraGridCount(_smartHomeData.GridLayoutCount);
            StyleTabBtn(CamGrid1Btn, count == 1);
            StyleTabBtn(CamGrid4Btn, count == 4);
            StyleTabBtn(CamGrid6Btn, count == 6);
            StyleTabBtn(CamGrid8Btn, count == 8);
        }

        private static int NormalizeCameraGridCount(int raw) => raw switch
        {
            1 => 1,
            6 => 6,
            8 => 8,
            _ => 4
        };

        private void SwitchTab_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string tab)
            {
                SwitchTabUI(tab);
            }
        }

        private void SwitchTabUI(string tab)
        {
            _activeTab = tab;
            ShuttersViewGrid.Visibility = tab == "shutters" ? Visibility.Visible : Visibility.Collapsed;
            GroupsViewGrid.Visibility = tab == "groups" ? Visibility.Visible : Visibility.Collapsed;
            PlugsViewGrid.Visibility = tab == "plugs" ? Visibility.Visible : Visibility.Collapsed;
            CamerasViewGrid.Visibility = tab == "cameras" ? Visibility.Visible : Visibility.Collapsed;

            StyleTabBtn(TabShuttersBtn, tab == "shutters");
            StyleTabBtn(TabGroupsBtn, tab == "groups");
            StyleTabBtn(TabPlugsBtn, tab == "plugs");
            StyleTabBtn(TabCamerasBtn, tab == "cameras");

            if (tab == "cameras")
            {
                RenderCamerasSidebar();
                RenderCameraRecordingsSidebar();
                RenderCameraMatrix();
            }
            else
            {
                StopPtzHoldLoop(sendStop: false);
            }
        }

        private void StyleTabBtn(Button btn, bool active)
        {
            if (active)
            {
                btn.Background = BrushHex(_isDarkMode ? "#26231F" : "#C5A059");
                btn.Foreground = BrushHex(_isDarkMode ? "#C5A059" : "#111827");
                btn.BorderBrush = BrushHex("#C5A059");
                btn.FontWeight = FontWeights.Bold;
            }
            else
            {
                btn.Background = BrushHex(_isDarkMode ? "#161922" : "#F9FAFB");
                btn.Foreground = BrushHex(_isDarkMode ? "#D1D5DB" : "#374151");
                btn.BorderBrush = BrushHex(_isDarkMode ? "#262A36" : "#D1D5DB");
                btn.FontWeight = FontWeights.SemiBold;
            }
        }

        private async void RefreshCurrentTab_Click(object sender, RoutedEventArgs e)
        {
            await RefreshAllAsync();
        }

        private async Task RefreshAllAsync()
        {
            SetStatus("Prüfe WLAN-Geräte...", "#F59E0B");
            await RefreshShuttersAsync();
            await RefreshPlugsAsync();
            RenderGroups();
            RenderCamerasSidebar();
            RenderCameraRecordingsSidebar();
            if (_activeTab == "cameras")
            {
                RenderCameraMatrix();
            }
            if (!FunkenwerkSecurityVault.IsUsingTemporaryAppData)
            {
                _ = SyncInventoryToPortalAsync();
            }
        }

        // =====================================================================
        // TAB 1: CONNEXOON WINDOW · SOMFY ROLLLÄDEN & FENSTER (io / RTS)
        // =====================================================================
        private async Task RefreshShuttersAsync()
        {
            TahomaGatewayTitleTxt.Text = $"Connexoon Window · Somfy ({_tahomaConfig.GatewayIp})";
            TahomaPinBadgeTxt.Text = $"{_tahomaConfig.GatewayPin}  !";

            var probe = await SomfyTahomaLocalEngine.ProbeGatewayAsync(_tahomaConfig.GatewayIp, _tahomaConfig.GatewayPin);
            if (!string.IsNullOrWhiteSpace(probe.DetectedGatewayPin) && probe.DetectedGatewayPin != _tahomaConfig.GatewayPin)
            {
                _tahomaConfig.GatewayPin = probe.DetectedGatewayPin;
                TahomaPinBadgeTxt.Text = $"{_tahomaConfig.GatewayPin}  !";
                SomfyTahomaLocalEngine.SaveConfig(_tahomaConfig);
            }

            if (SomfyTahomaLocalEngine.HasStoredCredentials(_tahomaConfig))
            {
                var (ok, modeUsed, shutters, err) = await SomfyTahomaLocalEngine.FetchShuttersAutoAsync(
                    _tahomaConfig,
                    probe.DeveloperApiPort8443Open);
                if (ok)
                {
                    _tahomaConfig.ConnectionMode = modeUsed;
                    _tahomaConfig.CachedShutters = shutters;
                    SomfyTahomaLocalEngine.SaveConfig(_tahomaConfig);
                    string modeLabel = modeUsed == "local" ? "Lokal" : "io/RTS";
                    SetStatus($"Connexoon Window · {shutters.Count} Antriebe ({modeLabel})", "#10B981");
                }
                else
                {
                    SetStatus(err, "#EF4444");
                }
            }
            else
            {
                SetStatus(probe.StatusSummary, probe.GatewayReachableOnLan ? "#F59E0B" : "#EF4444");
            }

            RenderShutters();
            RenderGroups();
        }

        private void RenderShutters()
        {
            ShuttersQuickGroupsPanel.Children.Clear();
            ShuttersWrapPanel.Children.Clear();

            foreach (var grp in _smartHomeData.Groups.Where(g => g.ShutterTargets.Count > 0))
            {
                ShuttersQuickGroupsPanel.Children.Add(BuildGroupCardElement(grp));
            }

            string cardBg = _isDarkMode ? "#151821" : "#FFFFFF";
            string borderHex = _isDarkMode ? "#262A36" : "#E5E7EB";
            string primaryFg = _isDarkMode ? "#F9FAFB" : "#111827";
            string mutedFg = _isDarkMode ? "#9CA3AF" : "#4B5563";
            string btnBg = _isDarkMode ? "#1E222D" : "#F3F4F6";
            string goldFg = _isDarkMode ? "#C5A059" : "#8A6522";

            if (_tahomaConfig.CachedShutters.Count == 0)
            {
                var emptyCard = new Border
                {
                    Width = 520,
                    Background = BrushHex(cardBg),
                    BorderBrush = BrushHex("#C5A059"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(14),
                    Padding = new Thickness(22, 18, 22, 18),
                    Margin = new Thickness(0, 0, 14, 14)
                };
                var sp = new StackPanel();
                sp.Children.Add(new TextBlock
                {
                    Text = SomfyTahomaLocalEngine.HasStoredCredentials(_tahomaConfig)
                        ? "Keine Rollläden oder Fensterantriebe gemeldet"
                        : "Connexoon Window · Somfy bereit",
                    FontSize = 15,
                    FontWeight = FontWeights.Bold,
                    Foreground = BrushHex(primaryFg),
                    Margin = new Thickness(0, 0, 0, 10)
                });
                var pairBtn = new Button
                {
                    Content = "🔑 Connexoon verbinden",
                    Background = BrushHex("#C5A059"),
                    Foreground = BrushHex("#111827"),
                    BorderThickness = new Thickness(0),
                    Padding = new Thickness(16, 8, 16, 8),
                    HorizontalAlignment = HorizontalAlignment.Left,
                    Cursor = Cursors.Hand,
                    FontWeight = FontWeights.Bold
                };
                pairBtn.Click += OpenTahomaAuthModal_Click;
                sp.Children.Add(pairBtn);
                emptyCard.Child = sp;
                ShuttersWrapPanel.Children.Add(emptyCard);
                return;
            }

            foreach (var shutter in _tahomaConfig.CachedShutters)
            {
                var card = new Border
                {
                    Width = 352,
                    Background = BrushHex(cardBg),
                    BorderBrush = BrushHex(borderHex),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(14),
                    Padding = new Thickness(18, 16, 18, 16),
                    Margin = new Thickness(0, 0, 14, 14)
                };

                var stack = new StackPanel();
                var topRow = new Grid { Margin = new Thickness(0, 0, 0, 12) };
                topRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                topRow.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var titleTxt = new TextBlock
                {
                    Text = shutter.Label,
                    FontSize = 14.5,
                    FontWeight = FontWeights.Bold,
                    Foreground = BrushHex(primaryFg),
                    TextTrimming = TextTrimming.CharacterEllipsis
                };
                var pctTxt = new TextBlock
                {
                    Text = shutter.SupportsPosition
                        ? $"{shutter.ClosurePercent} %"
                        : (shutter.ClosurePercent >= 50 ? "RTS · Zu" : "RTS · Auf"),
                    FontSize = 12.5,
                    FontWeight = FontWeights.Bold,
                    Foreground = BrushHex(goldFg)
                };
                Grid.SetColumn(titleTxt, 0);
                Grid.SetColumn(pctTxt, 1);
                topRow.Children.Add(titleTxt);
                topRow.Children.Add(pctTxt);
                stack.Children.Add(topRow);

                if (shutter.SupportsPosition)
                {
                    var slider = new Slider
                    {
                        Minimum = 0,
                        Maximum = 100,
                        Value = shutter.ClosurePercent,
                        IsSnapToTickEnabled = true,
                        TickFrequency = 5,
                        Margin = new Thickness(0, 0, 0, 12),
                        Cursor = Cursors.Hand
                    };
                    slider.ValueChanged += (_, ev) => pctTxt.Text = $"{(int)ev.NewValue} %";
                    slider.PreviewMouseLeftButtonUp += async (_, _) =>
                    {
                        int pos = (int)Math.Round(slider.Value);
                        shutter.ClosurePercent = pos;
                        await TriggerShutterCommandAsync(new[] { shutter }, "setClosure", pos);
                    };
                    stack.Children.Add(slider);
                }

                var btnGrid = new UniformGrid { Columns = shutter.SupportsPosition ? 5 : 4 };
                btnGrid.Children.Add(MakeShutterActionBtn("▲ Auf", btnBg, primaryFg, async () => await TriggerShutterCommandAsync(new[] { shutter }, "open", 0)));
                btnGrid.Children.Add(MakeShutterActionBtn("■ Stopp", btnBg, mutedFg, async () => await TriggerShutterCommandAsync(new[] { shutter }, "stop", null)));
                btnGrid.Children.Add(MakeShutterActionBtn("★ My", btnBg, goldFg, async () => await TriggerShutterCommandAsync(new[] { shutter }, "my", null)));
                if (shutter.SupportsPosition)
                {
                    btnGrid.Children.Add(MakeShutterActionBtn("50 %", btnBg, goldFg, async () => await TriggerShutterCommandAsync(new[] { shutter }, "setClosure", 50)));
                }
                btnGrid.Children.Add(MakeShutterActionBtn("▼ Zu", btnBg, primaryFg, async () => await TriggerShutterCommandAsync(new[] { shutter }, "close", 100)));

                stack.Children.Add(btnGrid);
                card.Child = stack;
                ShuttersWrapPanel.Children.Add(card);
            }
        }

        private static Button MakeShutterActionBtn(string label, string bgHex, string fgHex, Func<Task> onClick)
        {
            var b = new Button
            {
                Content = label,
                Background = BrushHex(bgHex),
                Foreground = BrushHex(fgHex),
                BorderThickness = new Thickness(0),
                Padding = new Thickness(5, 8, 5, 8),
                Margin = new Thickness(2, 0, 2, 0),
                Cursor = Cursors.Hand,
                FontSize = 11.5,
                FontWeight = FontWeights.Bold
            };
            b.Click += async (_, _) => await onClick();
            return b;
        }

        private async Task TriggerShutterCommandAsync(IEnumerable<TahomaShutterDevice> targets, string cmd, int? pos)
        {
            var targetArr = targets.ToList();
            var (ok, msg) = await SomfyTahomaLocalEngine.ExecuteShutterActionAutoAsync(
                _tahomaConfig,
                targetArr,
                cmd,
                pos);
            SetStatus(msg, ok ? "#10B981" : "#EF4444");
            if (ok && pos.HasValue)
            {
                foreach (var t in targetArr) t.ClosurePercent = pos.Value;
                SomfyTahomaLocalEngine.SaveConfig(_tahomaConfig);
                RenderShutters();
                RenderGroups();
                if (!FunkenwerkSecurityVault.IsUsingTemporaryAppData)
                    _ = SyncInventoryToPortalAsync();
            }
        }

        private async void AllShuttersOpen_Click(object sender, RoutedEventArgs e) =>
            await TriggerShutterCommandAsync(_tahomaConfig.CachedShutters, "open", 0);

        private async void AllShuttersStop_Click(object sender, RoutedEventArgs e) =>
            await TriggerShutterCommandAsync(_tahomaConfig.CachedShutters, "stop", null);

        private async void AllShuttersClose_Click(object sender, RoutedEventArgs e) =>
            await TriggerShutterCommandAsync(_tahomaConfig.CachedShutters, "close", 100);

        // =====================================================================
        // TAB 2: GERÄTE-GRUPPEN (MULTI-DEVICE ROLLLÄDEN & STECKDOSEN)
        // =====================================================================
        private void RenderGroups()
        {
            GroupsWrapPanel.Children.Clear();
            string cardBg = _isDarkMode ? "#151821" : "#FFFFFF";
            string primaryFg = _isDarkMode ? "#F9FAFB" : "#111827";

            if (_smartHomeData.Groups.Count == 0)
            {
                var emptyCard = new Border
                {
                    Width = 460,
                    Background = BrushHex(cardBg),
                    BorderBrush = BrushHex("#C5A059"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(14),
                    Padding = new Thickness(22, 18, 22, 18),
                    Margin = new Thickness(0, 0, 14, 14)
                };
                var sp = new StackPanel();
                sp.Children.Add(new TextBlock
                {
                    Text = "Keine Gruppen angelegt",
                    FontSize = 15,
                    FontWeight = FontWeights.Bold,
                    Foreground = BrushHex(primaryFg),
                    Margin = new Thickness(0, 0, 0, 10)
                });
                var createBtn = new Button
                {
                    Content = "＋ Erste Gruppe anlegen",
                    Background = BrushHex("#C5A059"),
                    Foreground = BrushHex("#111827"),
                    BorderThickness = new Thickness(0),
                    Padding = new Thickness(16, 8, 16, 8),
                    HorizontalAlignment = HorizontalAlignment.Left,
                    Cursor = Cursors.Hand,
                    FontWeight = FontWeights.Bold
                };
                createBtn.Click += OpenNewGroupModal_Click;
                sp.Children.Add(createBtn);
                emptyCard.Child = sp;
                GroupsWrapPanel.Children.Add(emptyCard);
                return;
            }

            foreach (var grp in _smartHomeData.Groups)
            {
                GroupsWrapPanel.Children.Add(BuildGroupCardElement(grp));
            }
        }

        private Border BuildGroupCardElement(SmartHomeDeviceGroup grp)
        {
            string cardBg = _isDarkMode ? "#151821" : "#FFFFFF";
            string primaryFg = _isDarkMode ? "#F9FAFB" : "#111827";
            string mutedFg = _isDarkMode ? "#9CA3AF" : "#4B5563";
            string btnBg = _isDarkMode ? "#1E222D" : "#F3F4F6";
            string chipBg = _isDarkMode ? "#26231F" : "#FEF9EC";
            string goldFg = _isDarkMode ? "#C5A059" : "#8A6522";

            var card = new Border
            {
                Width = 372,
                Background = BrushHex(cardBg),
                BorderBrush = BrushHex("#C5A059"),
                BorderThickness = new Thickness(1.2),
                CornerRadius = new CornerRadius(14),
                Padding = new Thickness(18, 16, 18, 16),
                Margin = new Thickness(0, 0, 14, 14)
            };

            var stack = new StackPanel();
            var topRow = new Grid { Margin = new Thickness(0, 0, 0, 10) };
            topRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            topRow.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            var titleStack = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
            titleStack.Children.Add(new TextBlock
            {
                Text = "🗂 " + grp.Name,
                FontSize = 14.5,
                FontWeight = FontWeights.Bold,
                Foreground = BrushHex(primaryFg),
                TextTrimming = TextTrimming.CharacterEllipsis
            });
            int totalMembers = grp.ShutterTargets.Count + grp.PlugTargets.Count;
            titleStack.Children.Add(new TextBlock
            {
                Text = $"  ({totalMembers})",
                FontSize = 12,
                FontWeight = FontWeights.Bold,
                Foreground = BrushHex(goldFg),
                VerticalAlignment = VerticalAlignment.Center
            });

            var editBtn = new Button
            {
                Content = "✎",
                Background = BrushHex(btnBg),
                Foreground = BrushHex(goldFg),
                BorderThickness = new Thickness(0),
                Padding = new Thickness(9, 4, 9, 4),
                Cursor = Cursors.Hand,
                FontWeight = FontWeights.Bold,
                ToolTip = "Gruppe bearbeiten"
            };
            var capturedGrp = grp;
            editBtn.Click += (_, _) => OpenEditGroupModal(capturedGrp);

            Grid.SetColumn(titleStack, 0);
            Grid.SetColumn(editBtn, 1);
            topRow.Children.Add(titleStack);
            topRow.Children.Add(editBtn);
            stack.Children.Add(topRow);

            var chipWrap = new WrapPanel { Margin = new Thickness(0, 0, 0, 10) };
            foreach (var st in grp.ShutterTargets)
            {
                string display = _tahomaConfig.CachedShutters.FirstOrDefault(s => s.DeviceUrl == st)?.Label ?? st;
                chipWrap.Children.Add(BuildMemberChip("🪟 " + display, chipBg, goldFg));
            }
            foreach (var pt in grp.PlugTargets)
            {
                string display = _smartHomeData.Plugs.FirstOrDefault(p => p.IpAddress == pt)?.Name ?? pt;
                chipWrap.Children.Add(BuildMemberChip("🔌 " + display, chipBg, goldFg));
            }
            stack.Children.Add(chipWrap);

            if (grp.ShutterTargets.Count > 0)
            {
                var matchedShutters = ResolveGroupShutters(grp);
                int avgPct = matchedShutters.Count > 0 ? (int)Math.Round(matchedShutters.Average(s => s.ClosurePercent)) : 0;

                var slider = new Slider
                {
                    Minimum = 0,
                    Maximum = 100,
                    Value = avgPct,
                    IsSnapToTickEnabled = true,
                    TickFrequency = 5,
                    Margin = new Thickness(0, 0, 0, 10),
                    Cursor = Cursors.Hand
                };
                slider.PreviewMouseLeftButtonUp += async (_, _) =>
                {
                    int pos = (int)Math.Round(slider.Value);
                    var (_, summary) = await ExecuteUnifiedSmartHomeCommandAsync("group_position", capturedGrp.Name, pos);
                    SetStatus(summary, "#10B981");
                };
                stack.Children.Add(slider);

                var shutterBtnGrid = new UniformGrid { Columns = 5, Margin = new Thickness(0, 0, 0, grp.PlugTargets.Count > 0 ? 8 : 0) };
                shutterBtnGrid.Children.Add(MakeShutterActionBtn("▲ Auf", btnBg, primaryFg, async () =>
                {
                    var (ok, s) = await ExecuteUnifiedSmartHomeCommandAsync("group_open", capturedGrp.Name, 0);
                    SetStatus(s, ok ? "#10B981" : "#EF4444");
                }));
                shutterBtnGrid.Children.Add(MakeShutterActionBtn("■ Stopp", btnBg, mutedFg, async () =>
                {
                    var (ok, s) = await ExecuteUnifiedSmartHomeCommandAsync("group_stop", capturedGrp.Name, null);
                    SetStatus(s, ok ? "#10B981" : "#EF4444");
                }));
                shutterBtnGrid.Children.Add(MakeShutterActionBtn("★ My", btnBg, goldFg, async () =>
                {
                    var (ok, s) = await ExecuteUnifiedSmartHomeCommandAsync("group_my", capturedGrp.Name, null);
                    SetStatus(s, ok ? "#10B981" : "#EF4444");
                }));
                shutterBtnGrid.Children.Add(MakeShutterActionBtn("50 %", btnBg, goldFg, async () =>
                {
                    var (ok, s) = await ExecuteUnifiedSmartHomeCommandAsync("group_position", capturedGrp.Name, 50);
                    SetStatus(s, ok ? "#10B981" : "#EF4444");
                }));
                shutterBtnGrid.Children.Add(MakeShutterActionBtn("▼ Zu", btnBg, primaryFg, async () =>
                {
                    var (ok, s) = await ExecuteUnifiedSmartHomeCommandAsync("group_close", capturedGrp.Name, 100);
                    SetStatus(s, ok ? "#10B981" : "#EF4444");
                }));
                stack.Children.Add(shutterBtnGrid);
            }

            if (grp.PlugTargets.Count > 0)
            {
                var plugBtnGrid = new UniformGrid { Columns = 2 };
                plugBtnGrid.Children.Add(MakeShutterActionBtn("⚡ Gruppe EIN", "#C5A059", "#111827", async () =>
                {
                    var (ok, s) = await ExecuteUnifiedSmartHomeCommandAsync("group_on", capturedGrp.Name, null);
                    SetStatus(s, ok ? "#10B981" : "#EF4444");
                }));
                plugBtnGrid.Children.Add(MakeShutterActionBtn("○ Gruppe AUS", btnBg, primaryFg, async () =>
                {
                    var (ok, s) = await ExecuteUnifiedSmartHomeCommandAsync("group_off", capturedGrp.Name, null);
                    SetStatus(s, ok ? "#10B981" : "#EF4444");
                }));
                stack.Children.Add(plugBtnGrid);
            }

            card.Child = stack;
            return card;
        }

        private static Border BuildMemberChip(string text, string bgHex, string fgHex)
        {
            return new Border
            {
                Background = BrushHex(bgHex),
                BorderBrush = BrushHex("#C5A059"),
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(6),
                Padding = new Thickness(8, 3, 8, 3),
                Margin = new Thickness(0, 0, 5, 5),
                Child = new TextBlock
                {
                    Text = text,
                    FontSize = 11,
                    FontWeight = FontWeights.SemiBold,
                    Foreground = BrushHex(fgHex)
                }
            };
        }

        private void OpenNewGroupModal_Click(object sender, RoutedEventArgs e)
        {
            OpenEditGroupModal(null);
        }

        private void OpenEditGroupModal(SmartHomeDeviceGroup? existing)
        {
            _editingGroupId = existing?.Id;
            _modalExtraShutterNames.Clear();
            GroupModalTitleTxt.Text = existing == null ? "Gruppe anlegen" : "Gruppe bearbeiten";
            GroupNameInputBox.Text = existing?.Name ?? "";
            GroupCustomShutterBox.Text = "";
            GroupModalFeedbackTxt.Visibility = Visibility.Collapsed;
            GroupModalDeleteBtn.Visibility = existing == null ? Visibility.Collapsed : Visibility.Visible;

            if (existing != null)
            {
                foreach (var st in existing.ShutterTargets)
                {
                    if (!_tahomaConfig.CachedShutters.Any(s => s.DeviceUrl == st || SmartHomeGroupResolver.NamesMatch(s.Label, st)))
                    {
                        _modalExtraShutterNames.Add(st);
                    }
                }
            }

            PopulateGroupModalChecklists(existing);
            GroupModalOverlayGrid.Visibility = Visibility.Visible;
        }

        private void PopulateGroupModalChecklists(SmartHomeDeviceGroup? existing)
        {
            string primaryFg = _isDarkMode ? "#F9FAFB" : "#111827";
            GroupShuttersCheckPanel.Children.Clear();
            GroupPlugsCheckPanel.Children.Clear();

            var selectedShutters = existing?.ShutterTargets ?? new List<string>();
            var selectedPlugs = existing?.PlugTargets ?? new List<string>();

            foreach (var s in _tahomaConfig.CachedShutters)
            {
                bool isChecked = selectedShutters.Any(st => st == s.DeviceUrl || SmartHomeGroupResolver.NamesMatch(s.Label, st));
                GroupShuttersCheckPanel.Children.Add(new CheckBox
                {
                    Content = s.Label,
                    Tag = s.Label,
                    IsChecked = isChecked,
                    Foreground = BrushHex(primaryFg),
                    FontSize = 12.5,
                    FontWeight = FontWeights.SemiBold,
                    Margin = new Thickness(0, 3, 0, 3),
                    Cursor = Cursors.Hand
                });
            }

            foreach (var extraName in _modalExtraShutterNames)
            {
                GroupShuttersCheckPanel.Children.Add(new CheckBox
                {
                    Content = extraName,
                    Tag = extraName,
                    IsChecked = true,
                    Foreground = BrushHex(primaryFg),
                    FontSize = 12.5,
                    FontWeight = FontWeights.SemiBold,
                    Margin = new Thickness(0, 3, 0, 3),
                    Cursor = Cursors.Hand
                });
            }

            if (GroupShuttersCheckPanel.Children.Count == 0)
            {
                // Offer convenient defaults so Alina can immediately group e.g. Wohnzimmer & Terrassentür even offline
                foreach (var preset in new[] { "Wohnzimmer", "Terrassentür" })
                {
                    GroupShuttersCheckPanel.Children.Add(new CheckBox
                    {
                        Content = preset,
                        Tag = preset,
                        IsChecked = false,
                        Foreground = BrushHex(primaryFg),
                        FontSize = 12.5,
                        FontWeight = FontWeights.SemiBold,
                        Margin = new Thickness(0, 3, 0, 3),
                        Cursor = Cursors.Hand
                    });
                }
            }

            foreach (var p in _smartHomeData.Plugs)
            {
                bool isChecked = selectedPlugs.Any(pt => pt == p.IpAddress || SmartHomeGroupResolver.NamesMatch(p.Name, pt));
                GroupPlugsCheckPanel.Children.Add(new CheckBox
                {
                    Content = $"{p.Name} ({p.IpAddress})",
                    Tag = p.Name,
                    IsChecked = isChecked,
                    Foreground = BrushHex(primaryFg),
                    FontSize = 12.5,
                    FontWeight = FontWeights.SemiBold,
                    Margin = new Thickness(0, 3, 0, 3),
                    Cursor = Cursors.Hand
                });
            }
        }

        private void GroupCustomShutterBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
            {
                e.Handled = true;
                AddCustomShutterToGroupModal_Click(sender, e);
            }
        }

        private void AddCustomShutterToGroupModal_Click(object sender, RoutedEventArgs e)
        {
            string custom = GroupCustomShutterBox.Text.Trim();
            if (string.IsNullOrWhiteSpace(custom)) return;
            GroupCustomShutterBox.Text = "";

            foreach (var child in GroupShuttersCheckPanel.Children.OfType<CheckBox>())
            {
                if (child.Tag is string t && SmartHomeGroupResolver.NamesMatch(t, custom))
                {
                    child.IsChecked = true;
                    return;
                }
            }

            _modalExtraShutterNames.Add(custom);
            string primaryFg = _isDarkMode ? "#F9FAFB" : "#111827";
            GroupShuttersCheckPanel.Children.Add(new CheckBox
            {
                Content = custom,
                Tag = custom,
                IsChecked = true,
                Foreground = BrushHex(primaryFg),
                FontSize = 12.5,
                FontWeight = FontWeights.SemiBold,
                Margin = new Thickness(0, 3, 0, 3),
                Cursor = Cursors.Hand
            });
        }

        private void CloseGroupModal_Click(object sender, RoutedEventArgs e) =>
            GroupModalOverlayGrid.Visibility = Visibility.Collapsed;

        private void DeleteGroupModal_Click(object sender, RoutedEventArgs e)
        {
            if (!string.IsNullOrEmpty(_editingGroupId))
            {
                _smartHomeData.Groups.RemoveAll(g => g.Id == _editingGroupId);
                SaveSmartHomeConfig();
                RenderShutters();
                RenderGroups();
                SetStatus("Gruppe gelöscht", "#10B981");
            }
            GroupModalOverlayGrid.Visibility = Visibility.Collapsed;
        }

        private void SaveGroupModal_Click(object sender, RoutedEventArgs e)
        {
            string name = GroupNameInputBox.Text.Trim();
            if (string.IsNullOrWhiteSpace(name))
            {
                GroupModalFeedbackTxt.Text = "Bitte einen Gruppennamen eingeben.";
                GroupModalFeedbackTxt.Visibility = Visibility.Visible;
                return;
            }

            var chosenShutters = GroupShuttersCheckPanel.Children
                .OfType<CheckBox>()
                .Where(c => c.IsChecked == true && c.Tag is string)
                .Select(c => ((string)c.Tag).Trim())
                .Where(s => !string.IsNullOrWhiteSpace(s))
                .Distinct(StringComparer.OrdinalIgnoreCase)
                .ToList();

            var chosenPlugs = GroupPlugsCheckPanel.Children
                .OfType<CheckBox>()
                .Where(c => c.IsChecked == true && c.Tag is string)
                .Select(c => ((string)c.Tag).Trim())
                .Where(s => !string.IsNullOrWhiteSpace(s))
                .Distinct(StringComparer.OrdinalIgnoreCase)
                .ToList();

            if (chosenShutters.Count == 0 && chosenPlugs.Count == 0)
            {
                GroupModalFeedbackTxt.Text = "Bitte mindestens ein Gerät für die Gruppe auswählen.";
                GroupModalFeedbackTxt.Visibility = Visibility.Visible;
                return;
            }

            var targetGroup = !string.IsNullOrEmpty(_editingGroupId)
                ? _smartHomeData.Groups.FirstOrDefault(g => g.Id == _editingGroupId)
                : null;

            if (targetGroup == null)
            {
                targetGroup = new SmartHomeDeviceGroup { Id = Guid.NewGuid().ToString("N") };
                _smartHomeData.Groups.Add(targetGroup);
            }

            targetGroup.Name = name;
            targetGroup.ShutterTargets = chosenShutters;
            targetGroup.PlugTargets = chosenPlugs;

            SaveSmartHomeConfig();
            GroupModalOverlayGrid.Visibility = Visibility.Collapsed;
            RenderShutters();
            RenderGroups();
            SetStatus($"Gruppe '{name}' gespeichert ({chosenShutters.Count + chosenPlugs.Count} Geräte)", "#10B981");
        }

        // =====================================================================
        // TAB 3: TAPO STECKDOSEN
        // =====================================================================
        private async Task RefreshPlugsAsync()
        {
            foreach (var p in _smartHomeData.Plugs)
            {
                if (!FunkenwerkSecurityVault.IsSafePrivateLanHost(p.IpAddress))
                {
                    p.StatusNote = "Ungültige LAN-IP";
                    continue;
                }
                try
                {
                    using var ping = new Ping();
                    var reply = await ping.SendPingAsync(p.IpAddress, 350);
                    p.StatusNote = reply.Status == IPStatus.Success ? "WLAN Online" : "Offline";
                }
                catch
                {
                    p.StatusNote = "Offline";
                }
            }
            RenderPlugs();
        }

        private void RenderPlugs()
        {
            PlugsWrapPanel.Children.Clear();
            string cardBg = _isDarkMode ? "#151821" : "#FFFFFF";
            string borderHex = _isDarkMode ? "#262A36" : "#E5E7EB";
            string primaryFg = _isDarkMode ? "#F9FAFB" : "#111827";
            string mutedFg = _isDarkMode ? "#9CA3AF" : "#6B7280";

            foreach (var plug in _smartHomeData.Plugs)
            {
                var card = new Border
                {
                    Width = 300,
                    Background = BrushHex(cardBg),
                    BorderBrush = BrushHex(plug.IsOn ? "#C5A059" : borderHex),
                    BorderThickness = new Thickness(plug.IsOn ? 1.4 : 1),
                    CornerRadius = new CornerRadius(14),
                    Padding = new Thickness(18, 16, 18, 16),
                    Margin = new Thickness(0, 0, 14, 14)
                };

                var grid = new Grid();
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var left = new StackPanel();
                left.Children.Add(new TextBlock
                {
                    Text = plug.Name,
                    FontSize = 14.5,
                    FontWeight = FontWeights.Bold,
                    Foreground = BrushHex(primaryFg)
                });
                left.Children.Add(new TextBlock
                {
                    Text = plug.IpAddress,
                    FontSize = 11.5,
                    Foreground = BrushHex(mutedFg),
                    Margin = new Thickness(0, 3, 0, 0)
                });

                var toggleBtn = new Button
                {
                    Content = plug.IsOn ? "EIN" : "AUS",
                    Background = BrushHex(plug.IsOn ? "#C5A059" : (_isDarkMode ? "#1E222D" : "#E5E7EB")),
                    Foreground = BrushHex(plug.IsOn ? "#111827" : primaryFg),
                    BorderThickness = new Thickness(0),
                    Padding = new Thickness(18, 8, 18, 8),
                    VerticalAlignment = VerticalAlignment.Center,
                    Cursor = Cursors.Hand,
                    FontWeight = FontWeights.Bold
                };
                toggleBtn.Click += async (_, _) =>
                {
                    bool desired = !plug.IsOn;
                    var (ok, msg) = await TapoLocalPlugEngine.SetPlugStateKlapV2Async(
                        plug.IpAddress,
                        _smartHomeData.TapoLocalEmail,
                        _smartHomeData.TapoLocalPassword,
                        desired);
                    if (ok)
                    {
                        plug.IsOn = desired;
                        SaveSmartHomeConfig();
                        RenderPlugs();
                        SetStatus($"{plug.Name}: {(desired ? "EIN" : "AUS")}", "#10B981");
                    }
                    else
                    {
                        SetStatus($"{plug.Name}: {msg}", "#EF4444");
                    }
                };

                Grid.SetColumn(left, 0);
                Grid.SetColumn(toggleBtn, 1);
                grid.Children.Add(left);
                grid.Children.Add(toggleBtn);
                card.Child = grid;
                PlugsWrapPanel.Children.Add(card);
            }
        }

        private async void ScanLanPlugs_Click(object sender, RoutedEventArgs e)
        {
            SetStatus("Scanne Heimnetz 192.168.188.x...", "#F59E0B");
            await RefreshPlugsAsync();
            SetStatus($"{_smartHomeData.Plugs.Count} Steckdosen geprüft", "#10B981");
        }

        // =====================================================================
        // TAB 4: LIVE-ÜBERWACHUNG & KAMERASTEUERUNG (RTSP / ONVIF PTZ / PLAYER)
        // =====================================================================
        private void SetCameraGrid1_Click(object sender, RoutedEventArgs e) => SetCameraGridLayout(1);
        private void SetCameraGrid4_Click(object sender, RoutedEventArgs e) => SetCameraGridLayout(4);
        private void SetCameraGrid6_Click(object sender, RoutedEventArgs e) => SetCameraGridLayout(6);
        private void SetCameraGrid8_Click(object sender, RoutedEventArgs e) => SetCameraGridLayout(8);

        public void SetCameraGridLayout(int count)
        {
            int normalized = NormalizeCameraGridCount(count);
            if (normalized > 1)
                _previousMultiGridCount = normalized;
            _smartHomeData.GridLayoutCount = normalized;
            CloseSmartMediaPlayerInternal();
            SaveSmartHomeConfig(syncPortal: false);
            UpdateCameraGridButtonsTheme();
            RenderCamerasSidebar();
            RenderCameraMatrix();
        }

        private double GetCurrentPtzStepDelta() => _ptzStepMode switch
        {
            "micro" => 0.02,
            "fine" => 0.045,
            "normal" => 0.09,
            "wide" => 0.16,
            _ => 0.045
        };

        private string GetCurrentPtzStepLabel() => _ptzStepMode switch
        {
            "micro" => "🎯 Mikro 1°",
            "fine" => "🎯 Fein 3°",
            "normal" => "🎯 Mittel 7°",
            "wide" => "🎯 Weit 15°",
            _ => "🎯 Fein 3°"
        };

        public string CyclePtzStepMode()
        {
            _ptzStepMode = _ptzStepMode switch
            {
                "micro" => "fine",
                "fine" => "normal",
                "normal" => "wide",
                _ => "micro"
            };
            return GetCurrentPtzStepLabel();
        }

        private async Task TriggerCameraPtzStepAsync(SmartCameraConfig cam, double rawPanSign, double rawTiltSign)
        {
            if (cam == null || !cam.HasPtz || string.IsNullOrWhiteSpace(cam.Url)) return;
            double delta = GetCurrentPtzStepDelta();
            double pan = rawPanSign * delta * (cam.InvertPan ? -1.0 : 1.0);
            double tilt = rawTiltSign * delta * (cam.InvertTilt ? -1.0 : 1.0);
            await OnvifPtzClient.StepMoveAsync(cam.Url, pan, tilt, cam.ServiceUrl);
        }

        private void StartPtzHoldLoop(SmartCameraConfig cam, double rawPanSign, double rawTiltSign)
        {
            StopPtzHoldLoop(sendStop: false);
            _ptzHoldCamera = cam;
            _ptzHoldPanDelta = rawPanSign;
            _ptzHoldTiltDelta = rawTiltSign;
            _ = TriggerCameraPtzStepAsync(cam, rawPanSign, rawTiltSign);

            _ptzHoldTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(220) };
            _ptzHoldTimer.Tick += async (_, _) =>
            {
                if (_ptzHoldCamera == null || Mouse.LeftButton != MouseButtonState.Pressed)
                {
                    StopPtzHoldLoop(sendStop: false);
                    return;
                }
                await TriggerCameraPtzStepAsync(_ptzHoldCamera, _ptzHoldPanDelta, _ptzHoldTiltDelta);
            };
            _ptzHoldTimer.Start();
        }

        private void StopPtzHoldLoop(bool sendStop)
        {
            var cam = _ptzHoldCamera;
            _ptzHoldCamera = null;
            if (_ptzHoldTimer != null)
            {
                _ptzHoldTimer.Stop();
                _ptzHoldTimer = null;
            }
            if (sendStop && cam != null && !string.IsNullOrWhiteSpace(cam.Url))
            {
                _ = OnvifPtzClient.StopMoveAsync(cam.Url, cam.ServiceUrl);
            }
        }

        private async void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Escape)
            {
                if (CameraModalOverlayGrid.Visibility == Visibility.Visible)
                {
                    CameraModalOverlayGrid.Visibility = Visibility.Collapsed;
                    e.Handled = true;
                    return;
                }
                if (GroupModalOverlayGrid.Visibility == Visibility.Visible)
                {
                    GroupModalOverlayGrid.Visibility = Visibility.Collapsed;
                    e.Handled = true;
                    return;
                }
                if (ModalOverlayGrid.Visibility == Visibility.Visible)
                {
                    ModalOverlayGrid.Visibility = Visibility.Collapsed;
                    e.Handled = true;
                    return;
                }
                if (SmartMediaPlayerBorder.Visibility == Visibility.Visible)
                {
                    CloseSmartMediaPlayerInternal();
                    e.Handled = true;
                    return;
                }
            }

            if (_activeTab != "cameras" ||
                CameraModalOverlayGrid.Visibility == Visibility.Visible ||
                GroupModalOverlayGrid.Visibility == Visibility.Visible ||
                ModalOverlayGrid.Visibility == Visibility.Visible ||
                SmartMediaPlayerBorder.Visibility == Visibility.Visible)
            {
                return;
            }

            if (Keyboard.FocusedElement is TextBox || Keyboard.FocusedElement is PasswordBox)
                return;

            var activeCam = _smartHomeData.Cameras.FirstOrDefault(c => string.Equals(c.Name, _smartHomeData.SelectedCameraName, StringComparison.OrdinalIgnoreCase))
                            ?? _smartHomeData.Cameras.FirstOrDefault();
            if (activeCam == null || !activeCam.HasPtz) return;

            switch (e.Key)
            {
                case Key.Left:
                case Key.A:
                    e.Handled = true;
                    await TriggerCameraPtzStepAsync(activeCam, -1, 0);
                    break;
                case Key.Right:
                case Key.D:
                    e.Handled = true;
                    await TriggerCameraPtzStepAsync(activeCam, 1, 0);
                    break;
                case Key.Up:
                case Key.W:
                    e.Handled = true;
                    await TriggerCameraPtzStepAsync(activeCam, 0, 1);
                    break;
                case Key.Down:
                case Key.S:
                    e.Handled = true;
                    await TriggerCameraPtzStepAsync(activeCam, 0, -1);
                    break;
                case Key.Space:
                    e.Handled = true;
                    await OnvifPtzClient.StopMoveAsync(activeCam.Url, activeCam.ServiceUrl);
                    break;
            }
        }

        private void UpdateCameraOnlineHeaderBadge()
        {
            int total = _smartHomeData.Cameras.Count;
            int online = _smartHomeData.Cameras.Count(c => c.IsOnline);
            int rec = _smartHomeData.Cameras.Count(c => c.IsRecordingManual || c.IsRecordingMotion);
            CamSidebarCountTxt.Text = total.ToString();
            if (total == 0)
            {
                CamerasOnlineBadgeTxt.Text = "0 KAMERAS  !";
            }
            else if (rec > 0)
            {
                CamerasOnlineBadgeTxt.Text = $"● {rec} REC · {online}/{total} LIVE  !";
            }
            else
            {
                CamerasOnlineBadgeTxt.Text = $"{online}/{total} LIVE  !";
            }
        }

        private static string GetCameraDotHex(SmartCameraConfig cam)
        {
            if (cam.IsRecordingManual || cam.IsRecordingMotion) return "#EF4444";
            if (cam.IsOnline) return "#10B981";
            if (cam.IsHostReachable || cam.IsConnecting) return "#F59E0B";
            return "#6B7280";
        }

        private void RenderCamerasSidebar()
        {
            CameraSidebarStack.Children.Clear();
            CameraDiscoveredLanStack.Children.Clear();
            UpdateCameraOnlineHeaderBadge();

            string primaryFg = _isDarkMode ? "#F9FAFB" : "#111827";
            string mutedFg = _isDarkMode ? "#9CA3AF" : "#6B7280";
            string selectedBg = _isDarkMode ? "#26231F" : "#FEF9EC";
            string idleBg = _isDarkMode ? "#10131B" : "#F9FAFB";
            string idleBorder = _isDarkMode ? "#1F2430" : "#E5E7EB";
            string goldFg = _isDarkMode ? "#C5A059" : "#8A6522";

            var ordered = _smartHomeData.Cameras
                .OrderBy(c => c.Position)
                .ThenBy(c => c.Name, StringComparer.OrdinalIgnoreCase)
                .ToList();

            foreach (var cam in ordered)
            {
                bool selected = string.Equals(cam.Name, _smartHomeData.SelectedCameraName, StringComparison.OrdinalIgnoreCase);
                var bd = new Border
                {
                    Background = BrushHex(selected ? selectedBg : idleBg),
                    BorderBrush = BrushHex(selected ? "#C5A059" : idleBorder),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(9),
                    Padding = new Thickness(10, 8, 10, 8),
                    Margin = new Thickness(0, 0, 0, 6),
                    Cursor = Cursors.Hand,
                    AllowDrop = true,
                    Tag = cam.Name
                };

                var row = new Grid();
                row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                row.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var sp = new StackPanel();
                var nameRow = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
                nameRow.Children.Add(new Ellipse
                {
                    Width = 8,
                    Height = 8,
                    Fill = BrushHex(GetCameraDotHex(cam)),
                    Margin = new Thickness(0, 0, 7, 0),
                    VerticalAlignment = VerticalAlignment.Center
                });
                nameRow.Children.Add(new TextBlock
                {
                    Text = cam.Name,
                    FontWeight = FontWeights.Bold,
                    FontSize = 12.5,
                    Foreground = BrushHex(primaryFg),
                    TextTrimming = TextTrimming.CharacterEllipsis
                });
                sp.Children.Add(nameRow);

                string streamTag = cam.PreferHdStream ? "HD" : "Sub";
                string motionTag = cam.MotionRecord ? " · 🎯 Motion" : "";
                sp.Children.Add(new TextBlock
                {
                    Text = $"{(string.IsNullOrEmpty(cam.IpAddress) ? "RTSP" : cam.IpAddress)} · {streamTag}{motionTag}",
                    FontSize = 10.5,
                    Foreground = BrushHex(mutedFg),
                    Margin = new Thickness(15, 2, 0, 0),
                    TextTrimming = TextTrimming.CharacterEllipsis
                });

                var gearBtn = new Button
                {
                    Content = "⚙",
                    Background = Brushes.Transparent,
                    Foreground = BrushHex(goldFg),
                    BorderThickness = new Thickness(0),
                    Padding = new Thickness(7, 3, 7, 3),
                    VerticalAlignment = VerticalAlignment.Center,
                    Cursor = Cursors.Hand,
                    FontWeight = FontWeights.Bold,
                    ToolTip = "Kamera-Einstellungen öffnen"
                };
                var capturedCam = cam;
                gearBtn.Click += (_, ev) =>
                {
                    ev.Handled = true;
                    OpenCameraModal(capturedCam);
                };

                Grid.SetColumn(sp, 0);
                Grid.SetColumn(gearBtn, 1);
                row.Children.Add(sp);
                row.Children.Add(gearBtn);
                bd.Child = row;

                bd.MouseLeftButtonUp += (_, _) =>
                {
                    _smartHomeData.SelectedCameraName = capturedCam.Name;
                    _smartHomeData.GridLayoutCount = 1;
                    CloseSmartMediaPlayerInternal();
                    SaveSmartHomeConfig(syncPortal: false);
                    UpdateCameraGridButtonsTheme();
                    RenderCamerasSidebar();
                    RenderCameraMatrix();
                };
                bd.MouseMove += (_, ev) =>
                {
                    if (ev.LeftButton == MouseButtonState.Pressed)
                    {
                        try { DragDrop.DoDragDrop(bd, capturedCam.Name, DragDropEffects.Move); } catch { }
                    }
                };
                bd.Drop += (_, ev) =>
                {
                    if (ev.Data.GetDataPresent(typeof(string)) && ev.Data.GetData(typeof(string)) is string srcName)
                    {
                        SwapCamerasOrder(srcName, capturedCam.Name);
                    }
                };

                CameraSidebarStack.Children.Add(bd);
            }

            foreach (var ip in _discoveredCameraIps.Where(ip => !_smartHomeData.Cameras.Any(c => string.Equals(c.IpAddress, ip, StringComparison.OrdinalIgnoreCase))))
            {
                var discBtn = new Button
                {
                    Content = $"＋ Gefunden: {ip}",
                    Background = BrushHex(selectedBg),
                    Foreground = BrushHex(goldFg),
                    BorderBrush = BrushHex("#C5A059"),
                    BorderThickness = new Thickness(1),
                    Padding = new Thickness(10, 6, 10, 6),
                    Margin = new Thickness(0, 2, 0, 4),
                    HorizontalAlignment = HorizontalAlignment.Stretch,
                    HorizontalContentAlignment = HorizontalAlignment.Left,
                    Cursor = Cursors.Hand,
                    FontSize = 11.5,
                    FontWeight = FontWeights.Bold,
                    ToolTip = "Gefundene Kamera im Heimnetz übernehmen"
                };
                string capturedIp = ip;
                discBtn.Click += (_, _) => OpenCameraModal(null, prefillIp: capturedIp);
                CameraDiscoveredLanStack.Children.Add(discBtn);
            }
        }

        private void SwapCamerasOrder(string sourceName, string targetName)
        {
            if (string.IsNullOrWhiteSpace(sourceName) || string.Equals(sourceName, targetName, StringComparison.OrdinalIgnoreCase))
                return;

            var ordered = _smartHomeData.Cameras
                .OrderBy(c => c.Position)
                .ThenBy(c => c.Name, StringComparer.OrdinalIgnoreCase)
                .ToList();
            for (int i = 0; i < ordered.Count; i++) ordered[i].Position = i;

            var src = ordered.FirstOrDefault(c => string.Equals(c.Name, sourceName, StringComparison.OrdinalIgnoreCase));
            var dst = ordered.FirstOrDefault(c => string.Equals(c.Name, targetName, StringComparison.OrdinalIgnoreCase));
            if (src == null || dst == null) return;

            (src.Position, dst.Position) = (dst.Position, src.Position);
            _smartHomeData.Cameras = ordered.OrderBy(c => c.Position).ToList();
            SaveSmartHomeConfig(syncPortal: false);
            RenderCamerasSidebar();
            RenderCameraMatrix();
        }

        private void RenderCameraMatrix()
        {
            StopPtzHoldLoop(sendStop: false);
            CameraMatrixGrid.Children.Clear();
            _cameraImages.Clear();
            _cameraStatusLabels.Clear();
            _cameraStatusDots.Clear();

            int layout = NormalizeCameraGridCount(_smartHomeData.GridLayoutCount);
            var (cols, rows) = layout switch
            {
                1 => (1, 1),
                6 => (3, 2),
                8 => (4, 2),
                _ => (2, 2)
            };
            CameraMatrixGrid.Columns = cols;
            CameraMatrixGrid.Rows = rows;

            var ordered = _smartHomeData.Cameras
                .OrderBy(c => c.Position)
                .ThenBy(c => c.Name, StringComparer.OrdinalIgnoreCase)
                .ToList();

            if (ordered.Count == 0)
            {
                CameraMatrixGrid.Columns = 1;
                CameraMatrixGrid.Rows = 1;
                CameraMatrixGrid.Children.Add(BuildEmptyCameraMatrixSlot(isFirstEmptyState: true));
                UpdateCameraOnlineHeaderBadge();
                return;
            }

            List<SmartCameraConfig> visibleCams;
            if (layout == 1)
            {
                var focused = ordered.FirstOrDefault(c => string.Equals(c.Name, _smartHomeData.SelectedCameraName, StringComparison.OrdinalIgnoreCase))
                              ?? ordered[0];
                _smartHomeData.SelectedCameraName = focused.Name;
                visibleCams = new List<SmartCameraConfig> { focused };
            }
            else
            {
                visibleCams = ordered.Take(layout).ToList();
            }

            foreach (var cam in visibleCams)
            {
                CameraMatrixGrid.Children.Add(BuildActiveCameraMatrixTile(cam, layout));
                EnsureCameraWorkerRunning(cam);
            }

            if (layout > 1 && visibleCams.Count < layout)
            {
                for (int slot = visibleCams.Count; slot < layout; slot++)
                {
                    CameraMatrixGrid.Children.Add(BuildEmptyCameraMatrixSlot(isFirstEmptyState: false));
                }
            }

            UpdateCameraOnlineHeaderBadge();
        }

        private Border BuildEmptyCameraMatrixSlot(bool isFirstEmptyState)
        {
            var slot = new Border
            {
                Margin = new Thickness(4),
                Background = BrushHex("#090C12"),
                BorderBrush = BrushHex("#1E2433"),
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(10),
                AllowDrop = true
            };

            var sp = new StackPanel
            {
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Center
            };
            sp.Children.Add(new TextBlock
            {
                Text = isFirstEmptyState ? "Keine IP-Kamera hinterlegt" : "Freier Matrix-Slot",
                Foreground = BrushHex("#9CA3AF"),
                FontSize = isFirstEmptyState ? 14 : 12,
                FontWeight = FontWeights.SemiBold,
                HorizontalAlignment = HorizontalAlignment.Center,
                Margin = new Thickness(0, 0, 0, 10)
            });

            var addBtn = new Button
            {
                Content = "＋ Kamera hinzufügen",
                Background = BrushHex("#26231F"),
                Foreground = BrushHex("#C5A059"),
                BorderBrush = BrushHex("#C5A059"),
                BorderThickness = new Thickness(1),
                Padding = new Thickness(14, 7, 14, 7),
                Cursor = Cursors.Hand,
                FontSize = 12,
                FontWeight = FontWeights.Bold,
                HorizontalAlignment = HorizontalAlignment.Center
            };
            addBtn.Click += OpenAddCameraModal_Click;
            sp.Children.Add(addBtn);
            slot.Child = sp;

            slot.Drop += (_, ev) =>
            {
                if (ev.Data.GetDataPresent(typeof(string)) && ev.Data.GetData(typeof(string)) is string srcName)
                {
                    var src = _smartHomeData.Cameras.FirstOrDefault(c => string.Equals(c.Name, srcName, StringComparison.OrdinalIgnoreCase));
                    if (src != null)
                    {
                        src.Position = _smartHomeData.Cameras.Count + 1;
                        SaveSmartHomeConfig(syncPortal: false);
                        RenderCamerasSidebar();
                        RenderCameraMatrix();
                    }
                }
            };
            return slot;
        }

        private Border BuildActiveCameraMatrixTile(SmartCameraConfig cam, int layoutCount)
        {
            bool isSingle = layoutCount == 1;
            bool isSelected = string.Equals(cam.Name, _smartHomeData.SelectedCameraName, StringComparison.OrdinalIgnoreCase);

            var tileBorder = new Border
            {
                Margin = new Thickness(4),
                Background = BrushHex("#05070A"),
                BorderBrush = BrushHex(isSelected && !isSingle ? "#C5A059" : "#1F2430"),
                BorderThickness = new Thickness(isSelected && !isSingle ? 1.4 : 1),
                CornerRadius = new CornerRadius(10),
                ClipToBounds = true,
                AllowDrop = true,
                Tag = cam.Name
            };

            var root = new Grid { Background = BrushHex("#05070A") };

            var placeholderTxt = new TextBlock
            {
                Text = $"{cam.Name} · {cam.StatusText}",
                Foreground = BrushHex("#6B7280"),
                FontSize = isSingle ? 14 : 12,
                FontWeight = FontWeights.SemiBold,
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Center
            };
            root.Children.Add(placeholderTxt);

            var img = new Image
            {
                Stretch = Stretch.Uniform,
                Cursor = Cursors.Hand
            };
            RenderOptions.SetBitmapScalingMode(img, BitmapScalingMode.LowQuality);
            if (_cameraWorkers.TryGetValue(cam.Name, out var existingWorker) && existingWorker.LatestBitmap != null)
            {
                img.Source = existingWorker.LatestBitmap;
            }
            _cameraImages[cam.Name] = img;
            img.MouseLeftButtonDown += (_, ev) =>
            {
                _smartHomeData.SelectedCameraName = cam.Name;
                if (ev.ClickCount == 2)
                {
                    ev.Handled = true;
                    SetCameraGridLayout(isSingle ? _previousMultiGridCount : 1);
                }
            };
            root.Children.Add(img);

            // Top-Left Live Status HUD Pill (draggable for reordering)
            var topLeftPill = new Border
            {
                Background = BrushHex("#CC0B0E14"),
                BorderBrush = BrushHex("#44C5A059"),
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(7),
                Padding = new Thickness(10, 4, 10, 4),
                Margin = new Thickness(10, 10, 0, 0),
                HorizontalAlignment = HorizontalAlignment.Left,
                VerticalAlignment = VerticalAlignment.Top,
                Cursor = Cursors.SizeAll,
                ToolTip = "Ziehen zum Umsortieren · Doppelklick auf das Bild für Vollbild/Matrix"
            };
            var statusStack = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
            var statusDot = new Ellipse
            {
                Width = 8,
                Height = 8,
                Fill = BrushHex(GetCameraDotHex(cam)),
                Margin = new Thickness(0, 0, 7, 0),
                VerticalAlignment = VerticalAlignment.Center
            };
            var statusLbl = new TextBlock
            {
                Text = $"{cam.Name} · {cam.StatusText}",
                Foreground = BrushHex("#F9FAFB"),
                FontSize = isSingle ? 12 : 11,
                FontWeight = FontWeights.Bold,
                VerticalAlignment = VerticalAlignment.Center
            };
            _cameraStatusDots[cam.Name] = statusDot;
            _cameraStatusLabels[cam.Name] = statusLbl;
            statusStack.Children.Add(statusDot);
            statusStack.Children.Add(statusLbl);
            topLeftPill.Child = statusStack;
            topLeftPill.MouseMove += (_, ev) =>
            {
                if (ev.LeftButton == MouseButtonState.Pressed)
                {
                    try { DragDrop.DoDragDrop(topLeftPill, cam.Name, DragDropEffects.Move); } catch { }
                }
            };
            root.Children.Add(topLeftPill);

            // Top-Right Quick Stream / Settings / Zoom Bar
            var topRightBar = new StackPanel
            {
                Orientation = Orientation.Horizontal,
                HorizontalAlignment = HorizontalAlignment.Right,
                VerticalAlignment = VerticalAlignment.Top,
                Margin = new Thickness(0, 10, 10, 0)
            };

            var streamBtn = MakeHudButton(
                cam.PreferHdStream ? "✦ HD" : "⚡ Sub",
                cam.PreferHdStream ? "#C5A059" : "#CC11151E",
                cam.PreferHdStream ? "#111827" : "#E5E7EB",
                "Zwischen HD (/stream1) und schnellem Substream (/stream2) umschalten");
            streamBtn.Click += (_, _) =>
            {
                cam.PreferHdStream = !cam.PreferHdStream;
                SaveSmartHomeConfig(syncPortal: false);
                if (_cameraWorkers.TryGetValue(cam.Name, out var w))
                    w.RestartStream();
                RenderCamerasSidebar();
                RenderCameraMatrix();
                SetStatus($"{cam.Name}: {(cam.PreferHdStream ? "HD-Stream (/stream1)" : "Substream (/stream2)")} aktiv", "#10B981");
            };
            topRightBar.Children.Add(streamBtn);

            var cfgBtn = MakeHudButton("⚙", "#CC11151E", "#C5A059", "Kamera-Einstellungen, PTZ-Achsen & Bewegungserkennung");
            cfgBtn.Click += (_, _) => OpenCameraModal(cam);
            topRightBar.Children.Add(cfgBtn);

            var zoomBtn = MakeHudButton(isSingle ? "⊞ Raster" : "⛶ Groß", "#CC11151E", "#F9FAFB", isSingle ? "Zurück zur Matrix" : "Einzelansicht vergrößern");
            zoomBtn.Click += (_, _) =>
            {
                _smartHomeData.SelectedCameraName = cam.Name;
                SetCameraGridLayout(isSingle ? _previousMultiGridCount : 1);
            };
            topRightBar.Children.Add(zoomBtn);
            root.Children.Add(topRightBar);

            // Floating Haptic PTZ Cross D-Pad Overlay
            bool ptzVisible = cam.HasPtz && (isSingle || _expandedPtzPanels.Contains(cam.Name));
            var ptzOverlay = BuildFloatingPtzCrossOverlay(cam, isSingle);
            ptzOverlay.Visibility = ptzVisible ? Visibility.Visible : Visibility.Collapsed;
            root.Children.Add(ptzOverlay);

            // Bottom Action Bar (PTZ Toggle, Stop, Live Audio, Manual Rec, Auto-Motion, Snapshot)
            var bottomBar = new Border
            {
                Background = BrushHex("#D90A0D14"),
                BorderBrush = BrushHex("#262A36"),
                BorderThickness = new Thickness(0, 1, 0, 0),
                Padding = new Thickness(10, 6, 10, 6),
                VerticalAlignment = VerticalAlignment.Bottom
            };
            var bottomGrid = new Grid();
            bottomGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            bottomGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            var leftActions = new WrapPanel { VerticalAlignment = VerticalAlignment.Center };
            if (cam.HasPtz)
            {
                var ptzToggleBtn = MakeHudButton("🕹 PTZ", ptzOverlay.Visibility == Visibility.Visible ? "#26231F" : "#181C26", "#C5A059", "PTZ-Steuerkreuz ein-/ausblenden");
                ptzToggleBtn.Click += (_, _) =>
                {
                    if (ptzOverlay.Visibility == Visibility.Visible)
                    {
                        ptzOverlay.Visibility = Visibility.Collapsed;
                        _expandedPtzPanels.Remove(cam.Name);
                    }
                    else
                    {
                        ptzOverlay.Visibility = Visibility.Visible;
                        _expandedPtzPanels.Add(cam.Name);
                    }
                };
                leftActions.Children.Add(ptzToggleBtn);

                var stopPtzBtn = MakeHudButton("⏹", "#181C26", "#D1D5DB", "PTZ-Bewegung sofort stoppen");
                stopPtzBtn.Click += async (_, _) =>
                {
                    StopPtzHoldLoop(sendStop: false);
                    await OnvifPtzClient.StopMoveAsync(cam.Url, cam.ServiceUrl);
                };
                leftActions.Children.Add(stopPtzBtn);
            }

            bool audioOn = _cameraAudioPlayer.IsPlaying && string.Equals(_cameraAudioPlayer.ActiveCameraName, cam.Name, StringComparison.OrdinalIgnoreCase);
            cam.IsAudioActive = audioOn;
            var audioBtn = MakeHudButton(
                audioOn ? "🔊 Ton AN" : "🔇 Ton",
                audioOn ? "#162A22" : "#181C26",
                audioOn ? "#34D399" : "#D1D5DB",
                "Live-Kamera-Ton ein-/ausschalten");
            audioBtn.Click += (_, _) =>
            {
                if (_cameraAudioPlayer.IsPlaying && string.Equals(_cameraAudioPlayer.ActiveCameraName, cam.Name, StringComparison.OrdinalIgnoreCase))
                {
                    _cameraAudioPlayer.Stop();
                    cam.IsAudioActive = false;
                    SetStatus($"{cam.Name}: Live-Ton stummgeschaltet", "#10B981");
                }
                else
                {
                    foreach (var other in _smartHomeData.Cameras) other.IsAudioActive = false;
                    _cameraAudioPlayer.Start(cam.Name, cam.Url, ResolveFfmpegBinaryPath());
                    cam.IsAudioActive = true;
                    SetStatus($"{cam.Name}: Live-Ton aktiv", "#10B981");
                }
                RenderCameraMatrix();
            };
            leftActions.Children.Add(audioBtn);

            var recBtn = MakeHudButton(
                cam.IsRecordingManual ? "⏹ Stop Rec" : "⏺ Aufnahme",
                cam.IsRecordingManual ? "#7F1D1D" : "#181C26",
                cam.IsRecordingManual ? "#FCA5A5" : "#F9FAFB",
                "Manuelle MP4-Aufnahme starten / beenden");
            recBtn.Click += async (_, _) =>
            {
                EnsureCameraWorkerRunning(cam);
                if (_cameraWorkers.TryGetValue(cam.Name, out var w))
                {
                    string? recPath = w.ToggleManualRecording();
                    RenderCameraMatrix();
                    UpdateCameraOnlineHeaderBadge();
                    if (recPath != null)
                    {
                        SetStatus($"{cam.Name}: Aufnahme läuft ({Path.GetFileName(recPath)})", "#EF4444");
                    }
                    else
                    {
                        SetStatus($"{cam.Name}: Aufnahme gespeichert", "#10B981");
                        await Task.Delay(700);
                        RenderCameraRecordingsSidebar();
                    }
                }
            };
            leftActions.Children.Add(recBtn);

            var rightActions = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
            var motionBtn = MakeHudButton(
                cam.MotionRecord ? "🎯 Motion: EIN" : "🎯 Motion: AUS",
                cam.MotionRecord ? "#26231F" : "#181C26",
                cam.MotionRecord ? "#C5A059" : "#9CA3AF",
                "Automatische 15-Sekunden-Aufnahme bei Bewegung umschalten");
            motionBtn.Click += (_, _) =>
            {
                cam.MotionRecord = !cam.MotionRecord;
                if (!cam.MotionRecord && _cameraWorkers.TryGetValue(cam.Name, out var w))
                {
                    w.StopMotionRecording();
                }
                SaveSmartHomeConfig(syncPortal: false);
                RenderCamerasSidebar();
                RenderCameraMatrix();
                SetStatus($"{cam.Name}: Bewegungs-Aufnahme {(cam.MotionRecord ? "aktiviert" : "deaktiviert")}", "#10B981");
            };
            rightActions.Children.Add(motionBtn);

            var snapBtn = MakeHudButton("📸 Foto", "#C5A059", "#111827", "Sofort-Snapshot speichern");
            snapBtn.Click += (_, _) =>
            {
                if (_cameraWorkers.TryGetValue(cam.Name, out var w))
                {
                    string? snapPath = w.SaveInstantSnapshot();
                    if (!string.IsNullOrEmpty(snapPath))
                    {
                        FunkenwerkSecurityVault.LockDownFileAcl(snapPath);
                        RenderCameraRecordingsSidebar();
                        SetStatus($"Snapshot gespeichert: {Path.GetFileName(snapPath)}", "#10B981");
                        return;
                    }
                }
                SetStatus($"{cam.Name}: Noch kein Live-Frame für Snapshot verfügbar", "#F59E0B");
            };
            rightActions.Children.Add(snapBtn);

            Grid.SetColumn(leftActions, 0);
            Grid.SetColumn(rightActions, 1);
            bottomGrid.Children.Add(leftActions);
            bottomGrid.Children.Add(rightActions);
            bottomBar.Child = bottomGrid;
            root.Children.Add(bottomBar);

            tileBorder.Drop += (_, ev) =>
            {
                if (ev.Data.GetDataPresent(typeof(string)) && ev.Data.GetData(typeof(string)) is string srcName)
                {
                    SwapCamerasOrder(srcName, cam.Name);
                }
            };
            tileBorder.Child = root;
            return tileBorder;
        }

        private Border BuildFloatingPtzCrossOverlay(SmartCameraConfig cam, bool includeDiagonals)
        {
            var panel = new Border
            {
                Background = BrushHex("#D90B0E14"),
                BorderBrush = BrushHex("#66C5A059"),
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(12),
                Padding = new Thickness(8),
                Margin = new Thickness(0, 44, 12, 52),
                HorizontalAlignment = HorizontalAlignment.Right,
                VerticalAlignment = VerticalAlignment.Center
            };

            var stack = new StackPanel();
            var cross = new UniformGrid { Columns = 3, Rows = 3, Width = includeDiagonals ? 114 : 102, Height = includeDiagonals ? 114 : 102 };

            UIElement CellOrSpacer(string label, double panSign, double tiltSign, bool enabled)
            {
                if (!enabled) return new Border();
                var b = new Button
                {
                    Content = label,
                    Background = BrushHex("#1A1F2B"),
                    Foreground = BrushHex("#F9FAFB"),
                    BorderBrush = BrushHex("#374151"),
                    BorderThickness = new Thickness(1),
                    Margin = new Thickness(2),
                    Cursor = Cursors.Hand,
                    FontSize = 12,
                    FontWeight = FontWeights.Bold
                };
                b.PreviewMouseLeftButtonDown += (_, ev) =>
                {
                    ev.Handled = true;
                    _smartHomeData.SelectedCameraName = cam.Name;
                    StartPtzHoldLoop(cam, panSign, tiltSign);
                };
                b.PreviewMouseLeftButtonUp += (_, _) => StopPtzHoldLoop(sendStop: false);
                b.MouseLeave += (_, _) => StopPtzHoldLoop(sendStop: false);
                return b;
            }

            cross.Children.Add(CellOrSpacer("↖", -0.8, 0.8, includeDiagonals));
            cross.Children.Add(CellOrSpacer("▲", 0.0, 1.0, true));
            cross.Children.Add(CellOrSpacer("↗", 0.8, 0.8, includeDiagonals));
            cross.Children.Add(CellOrSpacer("◄", -1.0, 0.0, true));

            var stopCenterBtn = new Button
            {
                Content = "⏹",
                Background = BrushHex("#26231F"),
                Foreground = BrushHex("#C5A059"),
                BorderBrush = BrushHex("#C5A059"),
                BorderThickness = new Thickness(1),
                Margin = new Thickness(2),
                Cursor = Cursors.Hand,
                FontSize = 11.5,
                FontWeight = FontWeights.Bold,
                ToolTip = "Stopp"
            };
            stopCenterBtn.Click += async (_, _) =>
            {
                StopPtzHoldLoop(sendStop: false);
                await OnvifPtzClient.StopMoveAsync(cam.Url, cam.ServiceUrl);
            };
            cross.Children.Add(stopCenterBtn);

            cross.Children.Add(CellOrSpacer("►", 1.0, 0.0, true));
            cross.Children.Add(CellOrSpacer("↙", -0.8, -0.8, includeDiagonals));
            cross.Children.Add(CellOrSpacer("▼", 0.0, -1.0, true));
            cross.Children.Add(CellOrSpacer("↘", 0.8, -0.8, includeDiagonals));
            stack.Children.Add(cross);

            var stepBtn = new Button
            {
                Content = GetCurrentPtzStepLabel(),
                Background = BrushHex("#181C26"),
                Foreground = BrushHex("#C5A059"),
                BorderBrush = BrushHex("#374151"),
                BorderThickness = new Thickness(1),
                Padding = new Thickness(6, 3, 6, 3),
                Margin = new Thickness(2, 5, 2, 0),
                Cursor = Cursors.Hand,
                FontSize = 10.5,
                FontWeight = FontWeights.Bold,
                ToolTip = "PTZ-Schrittweite umschalten (Mikro 1° · Fein 3° · Mittel 7° · Weit 15°)"
            };
            stepBtn.Click += (_, _) => stepBtn.Content = CyclePtzStepMode();
            stack.Children.Add(stepBtn);

            panel.Child = stack;
            return panel;
        }

        private static Button MakeHudButton(string text, string bgHex, string fgHex, string tooltip)
        {
            return new Button
            {
                Content = text,
                Background = BrushHex(bgHex),
                Foreground = BrushHex(fgHex),
                BorderBrush = BrushHex("#374151"),
                BorderThickness = new Thickness(1),
                Padding = new Thickness(9, 4, 9, 4),
                Margin = new Thickness(0, 0, 5, 0),
                Cursor = Cursors.Hand,
                FontSize = 11,
                FontWeight = FontWeights.Bold,
                ToolTip = tooltip
            };
        }

        private static string ResolveFfmpegBinaryPath()
        {
            string baseDir = AppDomain.CurrentDomain.BaseDirectory;
            string[] candidates =
            {
                Path.Combine(baseDir, "ffmpeg.exe"),
                Path.Combine(baseDir, "tools", "ffmpeg.exe"),
                Path.GetFullPath(Path.Combine(baseDir, "..", "..", "Funkenwerk", "ffmpeg.exe")),
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Programs", "Funkenwerk Collection", "Funkenwerk", "ffmpeg.exe"),
                @"C:\Users\konta\AppData\Local\Programs\Funkenwerk Collection\Funkenwerk\ffmpeg.exe",
                @"C:\Users\konta\Desktop\Funkenwerk Collection\Funkenwerk\ffmpeg.exe"
            };
            foreach (var c in candidates)
            {
                if (File.Exists(c)) return c;
            }

            try
            {
                string wingetPkgs = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Microsoft", "WinGet", "Packages");
                if (Directory.Exists(wingetPkgs))
                {
                    var found = Directory.EnumerateFiles(wingetPkgs, "ffmpeg.exe", SearchOption.AllDirectories).FirstOrDefault();
                    if (!string.IsNullOrEmpty(found)) return found;
                }
            }
            catch { }

            return "ffmpeg";
        }

        private static string GetSmartHomeRecordingsDir()
        {
            string dir = Path.Combine(FunkenwerkSecurityVault.AppDataDirectory, "Recordings");
            Directory.CreateDirectory(dir);
            FunkenwerkSecurityVault.LockDownDirectoryAcl(dir);
            return dir;
        }

        private void EnsureCameraWorkerRunning(SmartCameraConfig cam)
        {
            if (FunkenwerkSecurityVault.IsUsingTemporaryAppData) return;
            if (_cameraWorkers.ContainsKey(cam.Name) || string.IsNullOrWhiteSpace(cam.Url)) return;
            if (!FunkenwerkSecurityVault.IsSafeRtspUrl(cam.Url)) return;
            var worker = new CameraStreamWorker(
                cam,
                ResolveFfmpegBinaryPath(),
                GetSmartHomeRecordingsDir(),
                onFrameUpdated: (name, bmp) =>
                {
                    if (bmp == null) return;
                    if (_cameraFrameRenderGate.AddOrUpdate(name, 1, (_, cur) => cur == 0 ? 1 : 2) != 1)
                        return;

                    _ = Dispatcher.BeginInvoke(DispatcherPriority.Render, new Action(() =>
                    {
                        try
                        {
                            if (_cameraImages.TryGetValue(name, out var im))
                            {
                                im.Source = (_cameraWorkers.TryGetValue(name, out var w) && w.LatestBitmap != null)
                                    ? w.LatestBitmap
                                    : bmp;
                            }
                        }
                        finally
                        {
                            _cameraFrameRenderGate[name] = 0;
                        }
                    }));
                },
                onStateChanged: name =>
                {
                    _ = Dispatcher.BeginInvoke(DispatcherPriority.Background, new Action(() =>
                    {
                        var targetCam = _smartHomeData.Cameras.FirstOrDefault(c => string.Equals(c.Name, name, StringComparison.OrdinalIgnoreCase));
                        if (targetCam != null)
                        {
                            if (_cameraStatusLabels.TryGetValue(name, out var lbl))
                                lbl.Text = $"{targetCam.Name} · {targetCam.StatusText}";
                            if (_cameraStatusDots.TryGetValue(name, out var dot))
                                dot.Fill = BrushHex(GetCameraDotHex(targetCam));
                        }
                        UpdateCameraOnlineHeaderBadge();
                        if (_activeTab == "cameras")
                        {
                            RenderCamerasSidebar();
                        }
                    }));
                });
            _cameraWorkers[cam.Name] = worker;
            worker.Start();
        }

        // =====================================================================
        // RECORDINGS SIDEBAR & INTEGRATED MP4 / SNAPSHOT PLAYER
        // =====================================================================
        private List<SmartRecordingItem> LoadAllSmartRecordings()
        {
            var items = new List<SmartRecordingItem>();
            string root = GetSmartHomeRecordingsDir();
            var folders = new (string Sub, bool IsMotion, bool IsSnapshot)[]
            {
                ("Bewegung", true, false),
                ("Manuell", false, false),
                ("Snapshots", false, true)
            };

            foreach (var (sub, isMotion, isSnap) in folders)
            {
                string dir = Path.Combine(root, sub);
                if (!Directory.Exists(dir)) continue;
                try
                {
                    foreach (var path in Directory.EnumerateFiles(dir))
                    {
                        string ext = Path.GetExtension(path);
                        if (isSnap && !ext.Equals(".jpg", StringComparison.OrdinalIgnoreCase) && !ext.Equals(".jpeg", StringComparison.OrdinalIgnoreCase) && !ext.Equals(".png", StringComparison.OrdinalIgnoreCase))
                            continue;
                        if (!isSnap && !ext.Equals(".mp4", StringComparison.OrdinalIgnoreCase))
                            continue;

                        var fi = new FileInfo(path);
                        string stem = Path.GetFileNameWithoutExtension(fi.Name);
                        int lastSplit = stem.LastIndexOf('_');
                        string camName = stem;
                        if (lastSplit > 0)
                        {
                            int prevSplit = stem.LastIndexOf('_', lastSplit - 1);
                            camName = (prevSplit > 0 ? stem.Substring(0, prevSplit) : stem.Substring(0, lastSplit)).Replace('_', ' ');
                        }

                        items.Add(new SmartRecordingItem
                        {
                            Filename = fi.Name,
                            FullPath = fi.FullName,
                            CameraName = string.IsNullOrWhiteSpace(camName) ? "Kamera" : camName,
                            CreatedAt = fi.LastWriteTime,
                            DateDisplay = fi.LastWriteTime.ToString("dd.MM. HH:mm:ss"),
                            SizeMb = Math.Round(fi.Length / (1024.0 * 1024.0), 2),
                            IsMotion = isMotion,
                            IsSnapshot = isSnap
                        });
                    }
                }
                catch { }
            }

            return items.OrderByDescending(i => i.CreatedAt).Take(35).ToList();
        }

        private void RefreshCameraRecordings_Click(object sender, RoutedEventArgs e) =>
            RenderCameraRecordingsSidebar();

        private void RenderCameraRecordingsSidebar()
        {
            CameraRecordingsSidebarStack.Children.Clear();
            string primaryFg = _isDarkMode ? "#F9FAFB" : "#111827";
            string mutedFg = _isDarkMode ? "#9CA3AF" : "#6B7280";
            string itemBg = _isDarkMode ? "#10131B" : "#F9FAFB";
            string itemBorder = _isDarkMode ? "#1F2430" : "#E5E7EB";
            string activeBg = _isDarkMode ? "#26231F" : "#FEF9EC";

            var list = LoadAllSmartRecordings();
            if (list.Count == 0)
            {
                CameraRecordingsSidebarStack.Children.Add(new TextBlock
                {
                    Text = "Keine Aufnahmen vorhanden",
                    FontSize = 11.5,
                    Foreground = BrushHex(mutedFg),
                    Margin = new Thickness(4, 6, 4, 6)
                });
                return;
            }

            foreach (var rec in list)
            {
                bool isCurrent = _currentSmartMediaItem != null && string.Equals(_currentSmartMediaItem.FullPath, rec.FullPath, StringComparison.OrdinalIgnoreCase);
                var bd = new Border
                {
                    Background = BrushHex(isCurrent ? activeBg : itemBg),
                    BorderBrush = BrushHex(isCurrent ? "#C5A059" : itemBorder),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(8),
                    Padding = new Thickness(9, 7, 9, 7),
                    Margin = new Thickness(0, 0, 0, 5),
                    Cursor = Cursors.Hand
                };

                var g = new Grid();
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                string icon = rec.IsSnapshot ? "📸" : (rec.IsMotion ? "🎯" : "🎬");
                var sp = new StackPanel();
                sp.Children.Add(new TextBlock
                {
                    Text = $"{icon} {rec.CameraName}",
                    FontSize = 12,
                    FontWeight = FontWeights.Bold,
                    Foreground = BrushHex(primaryFg),
                    TextTrimming = TextTrimming.CharacterEllipsis
                });
                string sizeStr = rec.SizeMb >= 0.1 ? $"{rec.SizeMb:0.0} MB" : "< 0.1 MB";
                sp.Children.Add(new TextBlock
                {
                    Text = $"{rec.DateDisplay} · {sizeStr}",
                    FontSize = 10.5,
                    Foreground = BrushHex(mutedFg)
                });

                var delBtn = new Button
                {
                    Content = "🗑",
                    Background = Brushes.Transparent,
                    Foreground = BrushHex(_isDarkMode ? "#F87171" : "#DC2626"),
                    BorderThickness = new Thickness(0),
                    Padding = new Thickness(6, 2, 6, 2),
                    VerticalAlignment = VerticalAlignment.Center,
                    Cursor = Cursors.Hand,
                    ToolTip = "Aufnahme sicher löschen"
                };
                var captured = rec;
                delBtn.Click += (_, ev) =>
                {
                    ev.Handled = true;
                    DeleteSmartRecordingItem(captured);
                };

                Grid.SetColumn(sp, 0);
                Grid.SetColumn(delBtn, 1);
                g.Children.Add(sp);
                g.Children.Add(delBtn);
                bd.Child = g;
                bd.MouseLeftButtonUp += (_, _) => PlaySmartRecordingInApp(captured);
                CameraRecordingsSidebarStack.Children.Add(bd);
            }
        }

        private void PlaySmartRecordingInApp(SmartRecordingItem item)
        {
            if (item == null || !File.Exists(item.FullPath)) return;
            _currentSmartMediaItem = item;
            SmartPlayerBadgeTxt.Text = item.IsSnapshot ? "SNAPSHOT" : (item.IsMotion ? "BEWEGUNG" : "AUFNAHME");
            SmartPlayerTitleTxt.Text = item.CameraName;
            SmartPlayerSubTxt.Text = $"{item.DateDisplay} · {(item.SizeMb >= 0.1 ? $"{item.SizeMb:0.00} MB" : "JPEG")}";
            SmartMediaPlayerBorder.Visibility = Visibility.Visible;

            try { _smartClipTimer?.Stop(); } catch { }
            try { SmartClipMediaElement.Stop(); SmartClipMediaElement.Source = null; } catch { }

            if (item.IsSnapshot)
            {
                SmartClipMediaElement.Visibility = Visibility.Collapsed;
                SmartClipTransportBar.Visibility = Visibility.Collapsed;
                SmartClipSnapshotImage.Visibility = Visibility.Visible;

                var bmp = new BitmapImage();
                using (var fs = new FileStream(item.FullPath, FileMode.Open, FileAccess.Read, FileShare.ReadWrite))
                {
                    bmp.BeginInit();
                    bmp.CacheOption = BitmapCacheOption.OnLoad;
                    bmp.StreamSource = fs;
                    bmp.EndInit();
                }
                bmp.Freeze();
                SmartClipSnapshotImage.Source = bmp;
            }
            else
            {
                SmartClipSnapshotImage.Source = null;
                SmartClipSnapshotImage.Visibility = Visibility.Collapsed;
                SmartClipMediaElement.Visibility = Visibility.Visible;
                SmartClipTransportBar.Visibility = Visibility.Visible;

                SmartClipPosTxt.Text = "00:00";
                SmartClipDurTxt.Text = "00:00";
                SmartClipTimelineSlider.Value = 0;
                SmartClipMediaElement.SpeedRatio = _smartClipSpeed;
                SmartClipMediaElement.Source = new Uri(item.FullPath, UriKind.Absolute);
                SmartClipMediaElement.Play();
                _isSmartClipPlaying = true;
                SmartClipPlayPauseBtn.Content = "⏸";

                _smartClipTimer ??= new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(120) };
                _smartClipTimer.Tick -= SmartClipTimer_Tick;
                _smartClipTimer.Tick += SmartClipTimer_Tick;
                _smartClipTimer.Start();
            }

            RenderCameraRecordingsSidebar();
        }

        private void SmartClipTimer_Tick(object? sender, EventArgs e)
        {
            if (_isDraggingSmartClipSlider || !SmartClipMediaElement.NaturalDuration.HasTimeSpan) return;
            var pos = SmartClipMediaElement.Position;
            var dur = SmartClipMediaElement.NaturalDuration.TimeSpan;
            if (dur.TotalSeconds > 0)
            {
                SmartClipTimelineSlider.Maximum = dur.TotalSeconds;
                SmartClipTimelineSlider.Value = Math.Clamp(pos.TotalSeconds, 0, dur.TotalSeconds);
                SmartClipPosTxt.Text = FormatClipTime(pos);
                SmartClipDurTxt.Text = FormatClipTime(dur);
            }
        }

        private static string FormatClipTime(TimeSpan ts) =>
            ts.TotalHours >= 1 ? ts.ToString(@"hh\:mm\:ss") : ts.ToString(@"mm\:ss");

        private void SmartClipMediaElement_MediaOpened(object sender, RoutedEventArgs e)
        {
            if (SmartClipMediaElement.NaturalDuration.HasTimeSpan)
            {
                var dur = SmartClipMediaElement.NaturalDuration.TimeSpan;
                SmartClipTimelineSlider.Maximum = Math.Max(0.5, dur.TotalSeconds);
                SmartClipDurTxt.Text = FormatClipTime(dur);
            }
            SmartClipMediaElement.SpeedRatio = _smartClipSpeed;
        }

        private void SmartClipMediaElement_MediaEnded(object sender, RoutedEventArgs e)
        {
            _isSmartClipPlaying = false;
            SmartClipPlayPauseBtn.Content = "▶";
        }

        private void ToggleSmartClipPlayPause_Click(object sender, RoutedEventArgs e)
        {
            if (_isSmartClipPlaying)
            {
                SmartClipMediaElement.Pause();
                _isSmartClipPlaying = false;
                SmartClipPlayPauseBtn.Content = "▶";
            }
            else
            {
                if (SmartClipMediaElement.NaturalDuration.HasTimeSpan &&
                    SmartClipMediaElement.Position >= SmartClipMediaElement.NaturalDuration.TimeSpan - TimeSpan.FromMilliseconds(150))
                {
                    SmartClipMediaElement.Position = TimeSpan.Zero;
                }
                SmartClipMediaElement.Play();
                SmartClipMediaElement.SpeedRatio = _smartClipSpeed;
                _isSmartClipPlaying = true;
                SmartClipPlayPauseBtn.Content = "⏸";
            }
        }

        private void ReplaySmartClip_Click(object sender, RoutedEventArgs e)
        {
            SmartClipMediaElement.Position = TimeSpan.Zero;
            SmartClipMediaElement.Play();
            SmartClipMediaElement.SpeedRatio = _smartClipSpeed;
            _isSmartClipPlaying = true;
            SmartClipPlayPauseBtn.Content = "⏸";
        }

        private void SmartClipTimelineSlider_PreviewMouseLeftButtonDown(object sender, MouseButtonEventArgs e) =>
            _isDraggingSmartClipSlider = true;

        private void SmartClipTimelineSlider_PreviewMouseLeftButtonUp(object sender, MouseButtonEventArgs e)
        {
            _isDraggingSmartClipSlider = false;
            if (SmartClipMediaElement.NaturalDuration.HasTimeSpan)
            {
                SmartClipMediaElement.Position = TimeSpan.FromSeconds(SmartClipTimelineSlider.Value);
            }
        }

        private void SmartClipTimelineSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_isDraggingSmartClipSlider)
            {
                SmartClipPosTxt.Text = FormatClipTime(TimeSpan.FromSeconds(e.NewValue));
            }
        }

        private void CycleSmartClipSpeed_Click(object sender, RoutedEventArgs e)
        {
            _smartClipSpeed = _smartClipSpeed switch
            {
                < 0.9 => 1.0,
                < 1.25 => 1.5,
                < 1.75 => 2.0,
                _ => 0.5
            };
            SmartClipSpeedBtn.Content = $"{_smartClipSpeed:0.0}×";
            try { SmartClipMediaElement.SpeedRatio = _smartClipSpeed; } catch { }
        }

        private void CloseSmartMediaPlayer_Click(object sender, RoutedEventArgs e) =>
            CloseSmartMediaPlayerInternal();

        private void CloseSmartMediaPlayerInternal()
        {
            _currentSmartMediaItem = null;
            _isSmartClipPlaying = false;
            try { _smartClipTimer?.Stop(); } catch { }
            try { SmartClipMediaElement.Stop(); SmartClipMediaElement.Source = null; } catch { }
            SmartClipSnapshotImage.Source = null;
            SmartMediaPlayerBorder.Visibility = Visibility.Collapsed;
            RenderCameraRecordingsSidebar();
        }

        private void RevealCurrentSmartMediaInExplorer_Click(object sender, RoutedEventArgs e)
        {
            if (_currentSmartMediaItem == null || !File.Exists(_currentSmartMediaItem.FullPath)) return;
            string full = Path.GetFullPath(_currentSmartMediaItem.FullPath);
            string root = Path.GetFullPath(GetSmartHomeRecordingsDir());
            if (!full.StartsWith(root, StringComparison.OrdinalIgnoreCase)) return;
            try
            {
                var psi = new ProcessStartInfo("explorer.exe") { UseShellExecute = false };
                psi.ArgumentList.Add($"/select,{full}");
                Process.Start(psi);
            }
            catch { }
        }

        private void DeleteCurrentSmartMedia_Click(object sender, RoutedEventArgs e)
        {
            if (_currentSmartMediaItem != null)
            {
                var target = _currentSmartMediaItem;
                CloseSmartMediaPlayerInternal();
                DeleteSmartRecordingItem(target);
            }
        }

        private void DeleteSmartRecordingItem(SmartRecordingItem item)
        {
            string full = Path.GetFullPath(item.FullPath);
            string root = Path.GetFullPath(GetSmartHomeRecordingsDir());
            if (!full.StartsWith(root, StringComparison.OrdinalIgnoreCase)) return;
            if (_currentSmartMediaItem != null && string.Equals(_currentSmartMediaItem.FullPath, full, StringComparison.OrdinalIgnoreCase))
            {
                CloseSmartMediaPlayerInternal();
            }
            FunkenwerkSecurityVault.SecureZeroDeleteFile(full);
            RenderCameraRecordingsSidebar();
            SetStatus($"Gelöscht: {item.Filename}", "#10B981");
        }

        private void OpenRecordingsFolder_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                string dir = GetSmartHomeRecordingsDir();
                Process.Start(new ProcessStartInfo("explorer.exe", dir) { UseShellExecute = true });
            }
            catch { }
        }

        // =====================================================================
        // PARALLEL LOCAL SUBNET RTSP / ONVIF CAMERA SCANNER (RFC1918 ONLY)
        // =====================================================================
        private static string DetectLocalPrivateSubnetPrefix()
        {
            try
            {
                foreach (var ni in NetworkInterface.GetAllNetworkInterfaces())
                {
                    if (ni.OperationalStatus != OperationalStatus.Up || ni.NetworkInterfaceType == NetworkInterfaceType.Loopback)
                        continue;
                    foreach (var ua in ni.GetIPProperties().UnicastAddresses)
                    {
                        if (ua.Address.AddressFamily != AddressFamily.InterNetwork) continue;
                        string ip = ua.Address.ToString();
                        if (FunkenwerkSecurityVault.IsSafePrivateLanHost(ip) && !ip.StartsWith("127.", StringComparison.Ordinal))
                        {
                            int lastDot = ip.LastIndexOf('.');
                            if (lastDot > 0) return ip.Substring(0, lastDot + 1);
                        }
                    }
                }
            }
            catch { }
            return "192.168.188.";
        }

        private static async Task<bool> QuickTcpPortOpenAsync(string ip, int port, int timeoutMs)
        {
            try
            {
                using var tcp = new TcpClient();
                using var cts = new CancellationTokenSource(timeoutMs);
                await tcp.ConnectAsync(ip, port, cts.Token).ConfigureAwait(false);
                return tcp.Connected;
            }
            catch
            {
                return false;
            }
        }

        private async void ScanLanCameras_Click(object sender, RoutedEventArgs e)
        {
            CamScanLanBtn.IsEnabled = false;
            try
            {
                string prefix = DetectLocalPrivateSubnetPrefix();
                SetStatus($"Scanne Heimnetz {prefix}x nach RTSP/ONVIF-Kameras...", "#F59E0B");
                var found = new ConcurrentBag<string>();
                using var sem = new SemaphoreSlim(48, 48);

                var tasks = Enumerable.Range(2, 252).Select(async hostOctet =>
                {
                    string ip = prefix + hostOctet;
                    // Never probe frozen workshop Mini-PC 192.168.188.151 or Somfy Gateway .29
                    if (ip == "192.168.188.151" || ip == _tahomaConfig.GatewayIp || !FunkenwerkSecurityVault.IsSafePrivateLanHost(ip))
                        return;

                    await sem.WaitAsync().ConfigureAwait(false);
                    try
                    {
                        if (await QuickTcpPortOpenAsync(ip, 554, 240).ConfigureAwait(false) ||
                            await QuickTcpPortOpenAsync(ip, 2020, 240).ConfigureAwait(false))
                        {
                            found.Add(ip);
                        }
                    }
                    finally
                    {
                        sem.Release();
                    }
                });

                await Task.WhenAll(tasks);
                _discoveredCameraIps.Clear();
                foreach (var ip in found.Distinct(StringComparer.OrdinalIgnoreCase).OrderBy(s => s, StringComparer.OrdinalIgnoreCase))
                {
                    _discoveredCameraIps.Add(ip);
                }

                RenderCamerasSidebar();
                int newCount = _discoveredCameraIps.Count(ip => !_smartHomeData.Cameras.Any(c => string.Equals(c.IpAddress, ip, StringComparison.OrdinalIgnoreCase)));
                SetStatus(_discoveredCameraIps.Count == 0
                    ? $"Keine weiteren RTSP/ONVIF-Kameras unter {prefix}x gefunden"
                    : $"{_discoveredCameraIps.Count} Kamera(s) im WLAN erkannt ({newCount} neu)", "#10B981");
            }
            finally
            {
                CamScanLanBtn.IsEnabled = true;
            }
        }

        // =====================================================================
        // FULL CAMERA SETTINGS MODAL (EYE ICON + STREAM + PTZ + MOTION + ONVIF)
        // =====================================================================
        private void OpenAddCameraModal_Click(object sender, RoutedEventArgs e) =>
            OpenCameraModal(null);

        public void OpenCameraModal(SmartCameraConfig? existing, string? prefillIp = null)
        {
            _editingCameraId = existing?.Id;
            CamModalTitleTxt.Text = existing == null ? "IP-Kamera hinzufügen" : $"Kamera konfigurieren · {existing.Name}";
            CamModalNameBox.Text = existing?.Name ?? (string.IsNullOrEmpty(prefillIp) ? "" : $"Kamera {prefillIp.Split('.').Last()}");
            CamModalModelBox.Text = existing?.DeviceModel ?? "IP-Kamera (RTSP/ONVIF)";
            CamModalDescBox.Text = existing?.Description ?? "";

            string defaultIp = string.IsNullOrWhiteSpace(prefillIp) ? DetectLocalPrivateSubnetPrefix() + "100" : prefillIp;
            string rtspVal = existing?.Url ?? $"rtsp://admin:passwort@{defaultIp}:554/stream2";
            string onvifVal = existing?.ServiceUrl ?? (string.IsNullOrEmpty(prefillIp) ? "" : $"http://{prefillIp}:2020/onvif/device_service");

            _suppressCamRtspSync = true;
            CamModalRtspPasswordBox.Password = rtspVal;
            CamModalRtspVisibleBox.Text = rtspVal;
            _suppressCamRtspSync = false;
            _isCamRtspVisible = existing == null; // Show template URL when creating new, mask saved password when editing!
            CamModalRtspVisibleBox.Visibility = _isCamRtspVisible ? Visibility.Visible : Visibility.Collapsed;
            CamModalRtspPasswordBox.Visibility = _isCamRtspVisible ? Visibility.Collapsed : Visibility.Visible;

            CamModalOnvifUrlBox.Text = onvifVal;
            _camModalPreferHd = existing?.PreferHdStream ?? rtspVal.Contains("/stream1", StringComparison.OrdinalIgnoreCase);
            UpdateCameraModalStreamPillsTheme();

            CamModalMotionCheck.IsChecked = existing?.MotionRecord ?? false;
            int sens = Math.Clamp(existing?.MotionSensitivity ?? 50, 10, 100);
            CamModalSensSlider.Value = sens;
            CamModalSensValueTxt.Text = $"{sens} %";
            CamModalHasPtzCheck.IsChecked = existing?.HasPtz ?? true;
            CamModalInvertPanCheck.IsChecked = existing?.InvertPan ?? true;
            CamModalInvertTiltCheck.IsChecked = existing?.InvertTilt ?? false;

            CamModalFeedbackTxt.Visibility = Visibility.Collapsed;
            CamModalDeleteBtn.Visibility = existing == null ? Visibility.Collapsed : Visibility.Visible;
            CameraModalOverlayGrid.Visibility = Visibility.Visible;
        }

        private void UpdateCameraModalStreamPillsTheme()
        {
            if (CamModalSubStreamBtn == null || CamModalHdStreamBtn == null) return;
            StyleTabBtn(CamModalSubStreamBtn, !_camModalPreferHd);
            StyleTabBtn(CamModalHdStreamBtn, _camModalPreferHd);
        }

        private string GetCamModalRtspUrl() =>
            (_isCamRtspVisible ? CamModalRtspVisibleBox.Text : CamModalRtspPasswordBox.Password).Trim();

        private void SetCamModalRtspUrl(string url)
        {
            _suppressCamRtspSync = true;
            CamModalRtspPasswordBox.Password = url;
            CamModalRtspVisibleBox.Text = url;
            _suppressCamRtspSync = false;
        }

        private void SelectCamModalSubStream_Click(object sender, RoutedEventArgs e)
        {
            _camModalPreferHd = false;
            string cur = GetCamModalRtspUrl();
            if (cur.Contains("/stream1", StringComparison.OrdinalIgnoreCase))
                SetCamModalRtspUrl(cur.Replace("/stream1", "/stream2", StringComparison.OrdinalIgnoreCase));
            UpdateCameraModalStreamPillsTheme();
        }

        private void SelectCamModalHdStream_Click(object sender, RoutedEventArgs e)
        {
            _camModalPreferHd = true;
            string cur = GetCamModalRtspUrl();
            if (cur.Contains("/stream2", StringComparison.OrdinalIgnoreCase))
                SetCamModalRtspUrl(cur.Replace("/stream2", "/stream1", StringComparison.OrdinalIgnoreCase));
            UpdateCameraModalStreamPillsTheme();
        }

        private void ToggleCamModalRtspEye_Click(object sender, RoutedEventArgs e)
        {
            _isCamRtspVisible = !_isCamRtspVisible;
            CamModalRtspVisibleBox.Visibility = _isCamRtspVisible ? Visibility.Visible : Visibility.Collapsed;
            CamModalRtspPasswordBox.Visibility = _isCamRtspVisible ? Visibility.Collapsed : Visibility.Visible;
        }

        private void CamModalRtspPasswordBox_PasswordChanged(object sender, RoutedEventArgs e)
        {
            if (_suppressCamRtspSync) return;
            _suppressCamRtspSync = true;
            CamModalRtspVisibleBox.Text = CamModalRtspPasswordBox.Password;
            _suppressCamRtspSync = false;
        }

        private void CamModalRtspVisibleBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (_suppressCamRtspSync) return;
            _suppressCamRtspSync = true;
            CamModalRtspPasswordBox.Password = CamModalRtspVisibleBox.Text;
            _suppressCamRtspSync = false;
        }

        private void CamModalSensSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (CamModalSensValueTxt != null)
                CamModalSensValueTxt.Text = $"{(int)Math.Round(e.NewValue)} %";
        }

        private void CloseCameraModal_Click(object sender, RoutedEventArgs e) =>
            CameraModalOverlayGrid.Visibility = Visibility.Collapsed;

        private async void TestCameraConnectionModal_Click(object sender, RoutedEventArgs e)
        {
            string rtsp = GetCamModalRtspUrl();
            string onvif = CamModalOnvifUrlBox.Text.Trim();
            if (!FunkenwerkSecurityVault.IsSafeRtspUrl(rtsp))
            {
                CamModalFeedbackTxt.Foreground = BrushHex(_isDarkMode ? "#FCA5A5" : "#DC2626");
                CamModalFeedbackTxt.Text = "Ungültige RTSP-URL. Erlaubt sind nur lokale Heimnetz-Adressen (rtsp://192.168.x.x/...).";
                CamModalFeedbackTxt.Visibility = Visibility.Visible;
                return;
            }
            if (!string.IsNullOrEmpty(onvif) && !OnvifPtzClient.IsSafeCustomOnvifServiceUrl(onvif))
            {
                CamModalFeedbackTxt.Foreground = BrushHex(_isDarkMode ? "#FCA5A5" : "#DC2626");
                CamModalFeedbackTxt.Text = "ONVIF-URL muss auf eine private Heimnetz-IP (http://192.168.x.x:2020/...) zeigen.";
                CamModalFeedbackTxt.Visibility = Visibility.Visible;
                return;
            }

            CamModalTestBtn.IsEnabled = false;
            try
            {
                var (host, _, _) = OnvifPtzClient.ParseRtspCredentials(rtsp);
                bool rtspOpen = await QuickTcpPortOpenAsync(host, 554, 700);
                bool onvifOpen = await QuickTcpPortOpenAsync(host, 2020, 700);
                var (model, fw, _) = await OnvifPtzClient.QueryDeviceInfoAsync(rtsp, onvif);
                if (!string.IsNullOrWhiteSpace(model) && model != "IP-Kamera")
                {
                    CamModalModelBox.Text = string.IsNullOrWhiteSpace(fw) ? model : $"{model} ({fw})";
                }
                if (string.IsNullOrWhiteSpace(onvif) && onvifOpen)
                {
                    CamModalOnvifUrlBox.Text = $"http://{host}:2020/onvif/device_service";
                }

                CamModalFeedbackTxt.Foreground = BrushHex(rtspOpen ? "#34D399" : "#F59E0B");
                CamModalFeedbackTxt.Text = rtspOpen
                    ? $"Erreichbar ({host}) · RTSP :554 OK · ONVIF :2020 {(onvifOpen ? "OK" : "nicht offen")} · Modell: {model}"
                    : $"Kamera {host} antwortet aktuell nicht auf RTSP Port 554.";
                CamModalFeedbackTxt.Visibility = Visibility.Visible;
            }
            finally
            {
                CamModalTestBtn.IsEnabled = true;
            }
        }

        private void DeleteCameraModal_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrEmpty(_editingCameraId)) return;
            var existing = _smartHomeData.Cameras.FirstOrDefault(c => c.Id == _editingCameraId);
            if (existing != null)
            {
                if (_cameraAudioPlayer.IsPlaying && string.Equals(_cameraAudioPlayer.ActiveCameraName, existing.Name, StringComparison.OrdinalIgnoreCase))
                    _cameraAudioPlayer.Stop();
                if (_cameraWorkers.Remove(existing.Name, out var w))
                {
                    try { w.Dispose(); } catch { }
                }
                _smartHomeData.Cameras.Remove(existing);
                if (string.Equals(_smartHomeData.SelectedCameraName, existing.Name, StringComparison.OrdinalIgnoreCase))
                {
                    _smartHomeData.SelectedCameraName = _smartHomeData.Cameras.FirstOrDefault()?.Name ?? "";
                }
                SaveSmartHomeConfig();
                RenderCamerasSidebar();
                RenderCameraMatrix();
                SetStatus($"Kamera '{existing.Name}' entfernt", "#10B981");
            }
            CameraModalOverlayGrid.Visibility = Visibility.Collapsed;
        }

        private void SaveCameraModal_Click(object sender, RoutedEventArgs e)
        {
            string name = CamModalNameBox.Text.Trim();
            string rtsp = GetCamModalRtspUrl();
            string onvif = CamModalOnvifUrlBox.Text.Trim();

            if (string.IsNullOrWhiteSpace(name))
            {
                CamModalFeedbackTxt.Foreground = BrushHex(_isDarkMode ? "#FCA5A5" : "#DC2626");
                CamModalFeedbackTxt.Text = "Bitte einen Kameranamen vergeben.";
                CamModalFeedbackTxt.Visibility = Visibility.Visible;
                return;
            }
            if (!FunkenwerkSecurityVault.IsSafeRtspUrl(rtsp))
            {
                CamModalFeedbackTxt.Foreground = BrushHex(_isDarkMode ? "#FCA5A5" : "#DC2626");
                CamModalFeedbackTxt.Text = "Nur gültige lokale RTSP-URLs im privaten Heimnetz (rtsp://192.168.x.x/...) sind zulässig.";
                CamModalFeedbackTxt.Visibility = Visibility.Visible;
                return;
            }
            if (!string.IsNullOrEmpty(onvif) && !OnvifPtzClient.IsSafeCustomOnvifServiceUrl(onvif))
            {
                CamModalFeedbackTxt.Foreground = BrushHex(_isDarkMode ? "#FCA5A5" : "#DC2626");
                CamModalFeedbackTxt.Text = "Ungültige ONVIF-URL. Nur lokale http/https Heimnetz-Adressen ohne eingebettete Zugangsdaten erlaubt.";
                CamModalFeedbackTxt.Visibility = Visibility.Visible;
                return;
            }
            if (_smartHomeData.Cameras.Any(c => c.Id != _editingCameraId && string.Equals(c.Name, name, StringComparison.OrdinalIgnoreCase)))
            {
                CamModalFeedbackTxt.Foreground = BrushHex(_isDarkMode ? "#FCA5A5" : "#DC2626");
                CamModalFeedbackTxt.Text = $"Eine Kamera mit dem Namen '{name}' existiert bereits.";
                CamModalFeedbackTxt.Visibility = Visibility.Visible;
                return;
            }

            var target = !string.IsNullOrEmpty(_editingCameraId)
                ? _smartHomeData.Cameras.FirstOrDefault(c => c.Id == _editingCameraId)
                : null;

            string oldName = target?.Name ?? "";
            string oldUrl = target?.Url ?? "";
            bool oldHd = target?.PreferHdStream ?? false;

            if (target == null)
            {
                target = new SmartCameraConfig
                {
                    Id = Guid.NewGuid().ToString("N"),
                    Position = _smartHomeData.Cameras.Count
                };
                _smartHomeData.Cameras.Add(target);
            }

            if (string.IsNullOrWhiteSpace(onvif))
            {
                var (host, _, _) = OnvifPtzClient.ParseRtspCredentials(rtsp);
                if (!string.IsNullOrWhiteSpace(host))
                    onvif = $"http://{host}:2020/onvif/device_service";
            }

            target.Name = name;
            target.DeviceModel = string.IsNullOrWhiteSpace(CamModalModelBox.Text) ? "IP-Kamera (RTSP/ONVIF)" : CamModalModelBox.Text.Trim();
            target.Url = rtsp;
            target.ServiceUrl = onvif;
            target.Description = CamModalDescBox.Text.Trim();
            target.PreferHdStream = _camModalPreferHd;
            target.MotionRecord = CamModalMotionCheck.IsChecked == true;
            target.MotionSensitivity = (int)Math.Round(CamModalSensSlider.Value);
            target.HasPtz = CamModalHasPtzCheck.IsChecked == true;
            target.InvertPan = CamModalInvertPanCheck.IsChecked == true;
            target.InvertTilt = CamModalInvertTiltCheck.IsChecked == true;

            if (!string.IsNullOrEmpty(oldName) && _cameraWorkers.TryGetValue(oldName, out var oldWorker))
            {
                if (!string.Equals(oldName, name, StringComparison.OrdinalIgnoreCase) ||
                    !string.Equals(oldUrl, rtsp, StringComparison.Ordinal) ||
                    oldHd != target.PreferHdStream)
                {
                    _cameraWorkers.Remove(oldName);
                    try { oldWorker.Dispose(); } catch { }
                }
            }

            _discoveredCameraIps.RemoveAll(ip => string.Equals(ip, target.IpAddress, StringComparison.OrdinalIgnoreCase));
            _smartHomeData.SelectedCameraName = target.Name;
            SaveSmartHomeConfig();
            CameraModalOverlayGrid.Visibility = Visibility.Collapsed;
            RenderCamerasSidebar();
            RenderCameraMatrix();
            SetStatus($"Kamera '{target.Name}' gespeichert", "#10B981");
        }

        // =====================================================================
        // MODALS (TAHOMA / TAPO / ADD PLUG) WITH EYE ICON & LAN FIREWALL
        // =====================================================================
        private void OpenModal(string mode, string title, (string Label, string Val)? f1, (string Label, string Val)? f2, (string Label, string Val)? f3, (string Label, string Val)? secret)
        {
            _modalMode = mode;
            ModalTitleTxt.Text = title;
            ModalFeedbackTxt.Visibility = Visibility.Collapsed;

            SetupModalField(Field1Panel, Field1Label, Field1Box, f1);
            SetupModalField(Field2Panel, Field2Label, Field2Box, f2);
            SetupModalField(Field3Panel, Field3Label, Field3Box, f3);

            if (secret.HasValue)
            {
                SecretFieldPanel.Visibility = Visibility.Visible;
                SecretFieldLabel.Text = secret.Value.Label;
                _suppressSecretSync = true;
                ModalSecretPasswordBox.Password = secret.Value.Val;
                ModalSecretVisibleBox.Text = secret.Value.Val;
                _suppressSecretSync = false;
                _isSecretVisible = false;
                ModalSecretPasswordBox.Visibility = Visibility.Visible;
                ModalSecretVisibleBox.Visibility = Visibility.Collapsed;
            }
            else
            {
                SecretFieldPanel.Visibility = Visibility.Collapsed;
            }

            ModalOverlayGrid.Visibility = Visibility.Visible;
        }

        private static void SetupModalField(StackPanel panel, TextBlock lbl, TextBox box, (string Label, string Val)? cfg)
        {
            if (cfg.HasValue)
            {
                panel.Visibility = Visibility.Visible;
                lbl.Text = cfg.Value.Label;
                box.Text = cfg.Value.Val;
            }
            else
            {
                panel.Visibility = Visibility.Collapsed;
                box.Text = "";
            }
        }

        private void OpenTahomaAuthModal_Click(object sender, RoutedEventArgs e)
        {
            string storedSecret = FunkenwerkSecurityVault.UnprotectSecret(_tahomaConfig.SomfyPasswordProtected);
            if (string.IsNullOrWhiteSpace(storedSecret))
                storedSecret = _tahomaConfig.LocalBearerToken;

            OpenModal(
                "tahoma",
                "Connexoon Window · Somfy verbinden",
                ("CONNEXOON IP IM WLAN", _tahomaConfig.GatewayIp),
                ("BOX-PIN (CONNEXOON WINDOW)", _tahomaConfig.GatewayPin),
                ("SOMFY / CONNEXOON E-MAIL", _tahomaConfig.SomfyEmail),
                ("SOMFY PASSWORT (ODER LOKALES TOKEN)", storedSecret));
        }

        private void OpenTapoAuthModal_Click(object sender, RoutedEventArgs e)
        {
            OpenModal(
                "tapo_auth",
                "Tapo Zugangsdaten",
                ("TP-LINK / TAPO E-MAIL", _smartHomeData.TapoLocalEmail),
                null,
                null,
                ("TP-LINK / TAPO PASSWORT", _smartHomeData.TapoLocalPassword));
        }

        private void OpenAddPlugModal_Click(object sender, RoutedEventArgs e)
        {
            OpenModal("add_plug", "Steckdose hinzufügen", ("NAME", ""), ("IP-ADRESSE", "192.168.188."), null, null);
        }

        private void CloseModal_Click(object sender, RoutedEventArgs e) =>
            ModalOverlayGrid.Visibility = Visibility.Collapsed;

        private void ToggleModalSecretEye_Click(object sender, RoutedEventArgs e)
        {
            _isSecretVisible = !_isSecretVisible;
            ModalSecretVisibleBox.Visibility = _isSecretVisible ? Visibility.Visible : Visibility.Collapsed;
            ModalSecretPasswordBox.Visibility = _isSecretVisible ? Visibility.Collapsed : Visibility.Visible;
        }

        private void ModalSecretPasswordBox_PasswordChanged(object sender, RoutedEventArgs e)
        {
            if (_suppressSecretSync) return;
            _suppressSecretSync = true;
            ModalSecretVisibleBox.Text = ModalSecretPasswordBox.Password;
            _suppressSecretSync = false;
        }

        private void ModalSecretVisibleBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (_suppressSecretSync) return;
            _suppressSecretSync = true;
            ModalSecretPasswordBox.Password = ModalSecretVisibleBox.Text;
            _suppressSecretSync = false;
        }

        private string GetModalSecret() =>
            _isSecretVisible ? ModalSecretVisibleBox.Text : ModalSecretPasswordBox.Password;

        private async void SaveModal_Click(object sender, RoutedEventArgs e)
        {
            ModalSaveBtn.IsEnabled = false;
            try
            {
                if (_modalMode == "tahoma")
                {
                    string ip = string.IsNullOrWhiteSpace(Field1Box.Text) ? "192.168.188.29" : Field1Box.Text.Trim();
                    if (!SomfyTahomaLocalEngine.IsPrivateLanIp(ip))
                    {
                        ModalFeedbackTxt.Text = "Nur lokale Heimnetz-IP-Adressen (z. B. 192.168.188.29) sind zulässig.";
                        ModalFeedbackTxt.Visibility = Visibility.Visible;
                        return;
                    }

                    string pin = Field2Box.Text.Trim();
                    string email = Field3Box.Text.Trim();
                    string secret = GetModalSecret().Trim();

                    _tahomaConfig.GatewayIp = ip;
                    _tahomaConfig.GatewayPin = string.IsNullOrWhiteSpace(pin) ? "0813-5321-9574" : pin;
                    _tahomaConfig.GatewayModel = "Connexoon Window";
                    _tahomaConfig.SomfyEmail = email;

                    if (!string.IsNullOrWhiteSpace(secret))
                    {
                        var (ok, mode, tok, shutters, msg) = await SomfyTahomaLocalEngine.ConnectConnexoonOrLocalAsync(
                            email,
                            secret,
                            _tahomaConfig.GatewayPin,
                            _tahomaConfig.GatewayIp);
                        if (!ok)
                        {
                            ModalFeedbackTxt.Text = msg;
                            ModalFeedbackTxt.Visibility = Visibility.Visible;
                            return;
                        }

                        _tahomaConfig.ConnectionMode = mode;
                        _tahomaConfig.CachedShutters = shutters;
                        if (!string.IsNullOrWhiteSpace(email))
                        {
                            _tahomaConfig.SomfyPasswordProtected = FunkenwerkSecurityVault.ProtectSecret(secret);
                        }
                        if (!string.IsNullOrWhiteSpace(tok))
                        {
                            _tahomaConfig.LocalBearerToken = tok;
                        }
                        SetStatus(msg, "#10B981");
                    }

                    SomfyTahomaLocalEngine.SaveConfig(_tahomaConfig);
                    ModalOverlayGrid.Visibility = Visibility.Collapsed;
                    RenderShutters();
                    RenderGroups();
                    await RefreshShuttersAsync();
                    _ = SyncInventoryToPortalAsync();
                }
                else if (_modalMode == "tapo_auth")
                {
                    _smartHomeData.TapoLocalEmail = Field1Box.Text.Trim();
                    _smartHomeData.TapoLocalPassword = GetModalSecret();
                    SaveSmartHomeConfig();
                    ModalOverlayGrid.Visibility = Visibility.Collapsed;
                    SetStatus("Tapo-Zugangsdaten verschlüsselt gespeichert", "#10B981");
                }
                else if (_modalMode == "add_plug")
                {
                    string name = Field1Box.Text.Trim();
                    string ip = Field2Box.Text.Trim();
                    if (string.IsNullOrEmpty(name) || !FunkenwerkSecurityVault.IsSafePrivateLanHost(ip))
                    {
                        ModalFeedbackTxt.Text = "Bitte Name und eine gültige private Heimnetz-IP (192.168.x.x) eingeben.";
                        ModalFeedbackTxt.Visibility = Visibility.Visible;
                        return;
                    }
                    _smartHomeData.Plugs.Add(new SmartPlugConfig { Name = name, IpAddress = ip, Model = "Tapo P100" });
                    SaveSmartHomeConfig();
                    RenderPlugs();
                    ModalOverlayGrid.Visibility = Visibility.Collapsed;
                }
            }
            finally
            {
                ModalSaveBtn.IsEnabled = true;
            }
        }

        // =====================================================================
        // UNIFIED DEVICE + GROUP + COMPOUND TARGET RESOLUTION & EXECUTION
        // =====================================================================
        public List<TahomaShutterDevice> ResolveGroupShutters(SmartHomeDeviceGroup group)
        {
            var matched = new List<TahomaShutterDevice>();
            foreach (var s in _tahomaConfig.CachedShutters)
            {
                if (group.ShutterTargets.Any(st => st == s.DeviceUrl || SmartHomeGroupResolver.NamesMatch(s.Label, st)))
                {
                    matched.Add(s);
                }
            }
            return matched;
        }

        public List<SmartPlugConfig> ResolveGroupPlugs(SmartHomeDeviceGroup group)
        {
            var matched = new List<SmartPlugConfig>();
            foreach (var p in _smartHomeData.Plugs)
            {
                if (group.PlugTargets.Any(pt => pt == p.IpAddress || SmartHomeGroupResolver.NamesMatch(p.Name, pt)))
                {
                    matched.Add(p);
                }
            }
            return matched;
        }

        public (SmartHomeDeviceGroup? Group, List<TahomaShutterDevice> Shutters, List<SmartPlugConfig> Plugs) ResolveTargetsForCommand(string? rawTarget)
        {
            string clean = (rawTarget ?? "").Trim();
            if (string.IsNullOrWhiteSpace(clean) ||
                clean.Equals("alle", StringComparison.OrdinalIgnoreCase) ||
                clean.Equals("all", StringComparison.OrdinalIgnoreCase) ||
                clean.Equals("alles", StringComparison.OrdinalIgnoreCase))
            {
                return (null, _tahomaConfig.CachedShutters.ToList(), _smartHomeData.Plugs.ToList());
            }

            var group = SmartHomeGroupResolver.FindMatchingGroup(_smartHomeData.Groups, clean);
            if (group != null)
            {
                return (group, ResolveGroupShutters(group), ResolveGroupPlugs(group));
            }

            var parts = SmartHomeGroupResolver.SplitCompoundTargets(clean);
            var shutters = new List<TahomaShutterDevice>();
            var plugs = new List<SmartPlugConfig>();

            foreach (var part in parts)
            {
                var subGrp = SmartHomeGroupResolver.FindMatchingGroup(_smartHomeData.Groups, part);
                if (subGrp != null)
                {
                    foreach (var gs in ResolveGroupShutters(subGrp))
                        if (!shutters.Contains(gs)) shutters.Add(gs);
                    foreach (var gp in ResolveGroupPlugs(subGrp))
                        if (!plugs.Contains(gp)) plugs.Add(gp);
                    continue;
                }

                foreach (var s in _tahomaConfig.CachedShutters)
                {
                    if ((s.DeviceUrl == part || SmartHomeGroupResolver.NamesMatch(s.Label, part)) && !shutters.Contains(s))
                        shutters.Add(s);
                }
                foreach (var p in _smartHomeData.Plugs)
                {
                    if ((p.IpAddress == part || SmartHomeGroupResolver.NamesMatch(p.Name, part)) && !plugs.Contains(p))
                        plugs.Add(p);
                }
            }

            return (null, shutters, plugs);
        }

        public async Task<(bool Ok, string Summary)> ExecuteUnifiedSmartHomeCommandAsync(string action, string? target, int? position)
        {
            string act = (action ?? "status").Trim().ToLowerInvariant();
            var (matchedGroup, shutters, plugs) = ResolveTargetsForCommand(target);

            if (act is "shutter_open" or "shutter_close" or "shutter_stop" or "shutter_my" or "shutter_position"
                    or "group_open" or "group_close" or "group_stop" or "group_my" or "group_position")
            {
                string somfyCmd = act switch
                {
                    "shutter_open" or "group_open" => "open",
                    "shutter_close" or "group_close" => "close",
                    "shutter_my" or "group_my" => "my",
                    "shutter_position" or "group_position" => "setClosure",
                    _ => "stop"
                };
                int? effPos = somfyCmd == "open" ? 0 : (somfyCmd == "close" ? 100 : position);
                var (ok, msg) = await SomfyTahomaLocalEngine.ExecuteShutterActionAutoAsync(
                    _tahomaConfig,
                    shutters,
                    somfyCmd,
                    effPos);
                if (ok && effPos.HasValue)
                {
                    foreach (var m in shutters) m.ClosurePercent = effPos.Value;
                    SomfyTahomaLocalEngine.SaveConfig(_tahomaConfig);
                }
                await Dispatcher.InvokeAsync(() =>
                {
                    RenderShutters();
                    RenderGroups();
                });
                string prefix = matchedGroup != null ? $"Gruppe '{matchedGroup.Name}': " : "";
                return (ok, prefix + msg);
            }

            if (act is "plug_on" or "plug_off" or "plug_toggle" or "group_on" or "group_off")
            {
                if (plugs.Count == 0)
                    return (false, $"Keine Steckdose für '{target}' gefunden.");

                var summaries = new List<string>();
                bool anyOk = false;
                foreach (var p in plugs)
                {
                    bool desired = act == "plug_toggle" ? !p.IsOn : (act is "plug_on" or "group_on");
                    var (ok, msg) = await TapoLocalPlugEngine.SetPlugStateKlapV2Async(
                        p.IpAddress,
                        _smartHomeData.TapoLocalEmail,
                        _smartHomeData.TapoLocalPassword,
                        desired);
                    if (ok)
                    {
                        p.IsOn = desired;
                        anyOk = true;
                        summaries.Add($"{p.Name}: {(desired ? "EIN" : "AUS")}");
                    }
                    else
                    {
                        summaries.Add($"{p.Name}: {msg}");
                    }
                }
                SaveSmartHomeConfig();
                await Dispatcher.InvokeAsync(() =>
                {
                    RenderPlugs();
                    RenderGroups();
                });
                string prefix = matchedGroup != null ? $"Gruppe '{matchedGroup.Name}': " : "";
                return (anyOk, prefix + string.Join(" · ", summaries));
            }

            if (act is "camera_focus" or "camera_grid" or "camera_ptz" or "camera_snapshot" or "camera_motion_on" or "camera_motion_off")
            {
                var matchedCam = _smartHomeData.Cameras.FirstOrDefault(c => SmartHomeGroupResolver.NamesMatch(c.Name, target))
                                 ?? _smartHomeData.Cameras.FirstOrDefault(c => string.Equals(c.Name, _smartHomeData.SelectedCameraName, StringComparison.OrdinalIgnoreCase))
                                 ?? _smartHomeData.Cameras.FirstOrDefault();

                if (act == "camera_grid")
                {
                    int gCount = NormalizeCameraGridCount(position ?? 4);
                    await Dispatcher.InvokeAsync(() =>
                    {
                        SwitchTabUI("cameras");
                        SetCameraGridLayout(gCount);
                    });
                    return (true, $"Kamera-Matrix auf {gCount}er Ansicht umgeschaltet.");
                }

                if (matchedCam == null)
                    return (false, "Keine IP-Kamera in Funkenwerk SmartHome konfiguriert.");

                if (act == "camera_focus")
                {
                    await Dispatcher.InvokeAsync(() =>
                    {
                        _smartHomeData.SelectedCameraName = matchedCam.Name;
                        SwitchTabUI("cameras");
                        SetCameraGridLayout(1);
                    });
                    return (true, $"Kamera '{matchedCam.Name}' in Einzelansicht geöffnet.");
                }

                if (act is "camera_motion_on" or "camera_motion_off")
                {
                    bool enableMotion = act == "camera_motion_on";
                    matchedCam.MotionRecord = enableMotion;
                    SaveSmartHomeConfig(syncPortal: false);
                    await Dispatcher.InvokeAsync(() =>
                    {
                        RenderCamerasSidebar();
                        if (_activeTab == "cameras") RenderCameraMatrix();
                    });
                    return (true, $"{matchedCam.Name}: Bewegungs-Aufnahme {(enableMotion ? "aktiviert" : "deaktiviert")}.");
                }

                if (act == "camera_snapshot")
                {
                    string? snap = null;
                    await Dispatcher.InvokeAsync(() =>
                    {
                        if (_cameraWorkers.TryGetValue(matchedCam.Name, out var w))
                            snap = w.SaveInstantSnapshot();
                        if (!string.IsNullOrEmpty(snap))
                        {
                            FunkenwerkSecurityVault.LockDownFileAcl(snap);
                            RenderCameraRecordingsSidebar();
                        }
                    });
                    return !string.IsNullOrEmpty(snap)
                        ? (true, $"Snapshot von '{matchedCam.Name}' gespeichert ({Path.GetFileName(snap)}).")
                        : (false, $"Kein Live-Frame von '{matchedCam.Name}' verfügbar.");
                }

                if (act == "camera_ptz")
                {
                    string dir = (target ?? "").ToLowerInvariant();
                    if (dir.Contains("stop"))
                    {
                        await OnvifPtzClient.StopMoveAsync(matchedCam.Url, matchedCam.ServiceUrl);
                        return (true, $"{matchedCam.Name}: PTZ gestoppt.");
                    }
                    double pan = dir.Contains("left") || dir.Contains("links") ? -1.0 : (dir.Contains("right") || dir.Contains("rechts") ? 1.0 : 0.0);
                    double tilt = dir.Contains("up") || dir.Contains("oben") || dir.Contains("hoch") ? 1.0 : (dir.Contains("down") || dir.Contains("unten") || dir.Contains("runter") ? -1.0 : 0.0);
                    await TriggerCameraPtzStepAsync(matchedCam, pan, tilt);
                    return (true, $"{matchedCam.Name}: PTZ ausgeführt.");
                }
            }

            return (true, $"SmartHome aktiv: {_tahomaConfig.CachedShutters.Count} Rollläden, {_smartHomeData.Plugs.Count} Steckdosen, {_smartHomeData.Groups.Count} Gruppen, {_smartHomeData.Cameras.Count} Kameras.");
        }

        // =====================================================================
        // PORTAL INVENTORY SYNC & REMOTE COMMAND RELAY (ANDROID FUNKIRA/FALCONE)
        // =====================================================================
        private async Task SyncInventoryToPortalAsync()
        {
            try
            {
                var session = FunkenwerkSharedSession.Load();
                if (session.IsSessionLocked || string.IsNullOrWhiteSpace(session.BearerToken) || string.IsNullOrWhiteSpace(session.ApiBaseUrl))
                    return;

                string safeApi = FunkenwerkSecurityVault.NormalizeSecureApiUrl(session.ApiBaseUrl);
                var payload = new
                {
                    connexoon_gateway = new
                    {
                        model = _tahomaConfig.GatewayModel,
                        ip = _tahomaConfig.GatewayIp,
                        pin = _tahomaConfig.GatewayPin
                    },
                    shutters = _tahomaConfig.CachedShutters.Select(s => new
                    {
                        device_url = s.DeviceUrl,
                        label = s.Label,
                        controllable_name = s.ControllableName,
                        closure_percent = s.ClosurePercent,
                        supports_position = s.SupportsPosition,
                        is_available = s.IsAvailable
                    }).ToArray(),
                    plugs = _smartHomeData.Plugs.Select(p => new
                    {
                        id = p.Id,
                        name = p.Name,
                        ip = p.IpAddress,
                        mac = p.MacAddress,
                        model = p.Model,
                        room = p.Room
                    }).ToArray(),
                    groups = _smartHomeData.Groups.Select(g => new
                    {
                        id = g.Id,
                        name = g.Name,
                        shutter_targets = g.ShutterTargets,
                        plug_targets = g.PlugTargets
                    }).ToArray()
                };

                using var req = new HttpRequestMessage(HttpMethod.Post, $"{safeApi}/funki/routine/smarthome-sync")
                {
                    Content = new StringContent(JsonSerializer.Serialize(payload), Encoding.UTF8, "application/json")
                };
                req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", session.BearerToken);
                req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                using var _ = await _portalHttp.SendAsync(req).ConfigureAwait(false);
            }
            catch { }
        }

        private void StartPortalRelayPoller()
        {
            if (FunkenwerkSecurityVault.IsUsingTemporaryAppData) return;
            _portalRelayCts = new CancellationTokenSource();
            var ct = _portalRelayCts.Token;

            _ = Task.Run(async () =>
            {
                while (!ct.IsCancellationRequested)
                {
                    try
                    {
                        await Task.Delay(3500, ct).ConfigureAwait(false);
                        var session = FunkenwerkSharedSession.Load();
                        if (session.IsSessionLocked || string.IsNullOrWhiteSpace(session.BearerToken) || string.IsNullOrWhiteSpace(session.ApiBaseUrl))
                            continue;

                        string safeApi = FunkenwerkSecurityVault.NormalizeSecureApiUrl(session.ApiBaseUrl);
                        using var req = new HttpRequestMessage(HttpMethod.Get, $"{safeApi}/funki/routine/smarthome-commands/pending");
                        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", session.BearerToken);
                        req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

                        using var resp = await _portalHttp.SendAsync(req, ct).ConfigureAwait(false);
                        if (!resp.IsSuccessStatusCode) continue;

                        string body = await resp.Content.ReadAsStringAsync(ct).ConfigureAwait(false);
                        using var doc = JsonDocument.Parse(body);
                        if (!doc.RootElement.TryGetProperty("commands", out var cmdsEl) || cmdsEl.ValueKind != JsonValueKind.Array)
                            continue;

                        foreach (var cmdEl in cmdsEl.EnumerateArray())
                        {
                            string cmdId = cmdEl.TryGetProperty("id", out var idEl) && idEl.ValueKind == JsonValueKind.String ? (idEl.GetString() ?? "") : "";
                            string action = cmdEl.TryGetProperty("action", out var aEl) && aEl.ValueKind == JsonValueKind.String ? (aEl.GetString() ?? "") : "";
                            string target = cmdEl.TryGetProperty("target", out var tEl) && tEl.ValueKind == JsonValueKind.String ? (tEl.GetString() ?? "alle") : "alle";
                            int? pos = cmdEl.TryGetProperty("position", out var pEl) && pEl.ValueKind == JsonValueKind.Number && pEl.TryGetInt32(out int pv) ? pv : null;
                            if (string.IsNullOrEmpty(cmdId) || string.IsNullOrEmpty(action)) continue;

                            var (ok, summary) = await ExecuteUnifiedSmartHomeCommandAsync(action, target, pos).ConfigureAwait(false);
                            await Dispatcher.InvokeAsync(() => SetStatus(summary, ok ? "#10B981" : "#EF4444"));

                            using var ackReq = new HttpRequestMessage(HttpMethod.Post, $"{safeApi}/funki/routine/smarthome-commands/ack")
                            {
                                Content = new StringContent(JsonSerializer.Serialize(new { command_id = cmdId, ok, summary }), Encoding.UTF8, "application/json")
                            };
                            ackReq.Headers.Authorization = new AuthenticationHeaderValue("Bearer", session.BearerToken);
                            using var _ = await _portalHttp.SendAsync(ackReq, ct).ConfigureAwait(false);
                        }
                    }
                    catch (OperationCanceledException) { break; }
                    catch { }
                }
            }, ct);
        }

        // =====================================================================
        // HARDENED NAMED PIPE IPC SERVER FOR FUNKIRA & FUNKENWERK MCP
        // =====================================================================
        private void StartIpcPipeServer()
        {
            _pipeCts = new CancellationTokenSource();
            var ct = _pipeCts.Token;
            string activePipe = FunkenwerkSecurityVault.IsUsingTemporaryAppData
                ? $"{PipeName}_selftest_{Environment.ProcessId}"
                : PipeName;

            for (int worker = 0; worker < 2; worker++)
            {
                _ = Task.Run(async () =>
                {
                    while (!ct.IsCancellationRequested)
                    {
                        NamedPipeServerStream? server = null;
                        try
                        {
                            var pipeSec = new PipeSecurity();
                            var sid = WindowsIdentity.GetCurrent().User;
                            if (sid != null)
                            {
                                pipeSec.AddAccessRule(new PipeAccessRule(sid, PipeAccessRights.FullControl, AccessControlType.Allow));
                            }
                            server = NamedPipeServerStreamAcl.Create(
                                activePipe,
                                PipeDirection.InOut,
                                4,
                                PipeTransmissionMode.Byte,
                                PipeOptions.Asynchronous | PipeOptions.CurrentUserOnly,
                                MaxPipePayloadBytes,
                                MaxPipePayloadBytes,
                                pipeSec);

                            await server.WaitForConnectionAsync(ct).ConfigureAwait(false);
                            string? line = await ReadBoundedPipeLineAsync(server, MaxPipePayloadBytes, TimeSpan.FromSeconds(5), ct).ConfigureAwait(false);
                            if (!string.IsNullOrWhiteSpace(line))
                            {
                                string respJson = await HandleIpcCommandAsync(line).ConfigureAwait(false);
                                using var writer = new StreamWriter(server, new UTF8Encoding(false), 4096, leaveOpen: true) { AutoFlush = true };
                                await writer.WriteLineAsync(respJson).ConfigureAwait(false);
                            }
                        }
                        catch (OperationCanceledException) { break; }
                        catch { await Task.Delay(250, ct).ConfigureAwait(false); }
                        finally { try { server?.Dispose(); } catch { } }
                    }
                }, ct);
            }
        }

        private static async Task<string?> ReadBoundedPipeLineAsync(Stream stream, int maxBytes, TimeSpan timeout, CancellationToken outerCt)
        {
            using var cts = CancellationTokenSource.CreateLinkedTokenSource(outerCt);
            cts.CancelAfter(timeout);
            using var ms = new MemoryStream(512);
            byte[] one = new byte[1];
            while (ms.Length < maxBytes)
            {
                int n = await stream.ReadAsync(one.AsMemory(0, 1), cts.Token).ConfigureAwait(false);
                if (n <= 0) break;
                if (one[0] == (byte)'\n') break;
                if (one[0] != (byte)'\r') ms.WriteByte(one[0]);
            }
            return ms.Length == 0 ? null : Encoding.UTF8.GetString(ms.ToArray());
        }

        private async Task<string> HandleIpcCommandAsync(string rawJson)
        {
            try
            {
                using var doc = JsonDocument.Parse(rawJson);
                var root = doc.RootElement;
                string action = root.TryGetProperty("action", out var a) && a.ValueKind == JsonValueKind.String ? (a.GetString() ?? "").ToLowerInvariant() : "status";
                string target = root.TryGetProperty("target", out var t) && t.ValueKind == JsonValueKind.String ? (t.GetString() ?? "") : "";
                int? position = root.TryGetProperty("position", out var posEl) && posEl.ValueKind == JsonValueKind.Number && posEl.TryGetInt32(out int pVal) ? pVal : null;

                if (action != "status" && action != "group_list")
                {
                    var (ok, summary) = await ExecuteUnifiedSmartHomeCommandAsync(action, target, position).ConfigureAwait(false);
                    return JsonSerializer.Serialize(new { ok, summary });
                }

                return JsonSerializer.Serialize(new
                {
                    ok = true,
                    summary = $"SmartHome aktiv (Connexoon Window): {_tahomaConfig.CachedShutters.Count} Rollläden, {_smartHomeData.Plugs.Count} Steckdosen, {_smartHomeData.Groups.Count} Gruppen, {_smartHomeData.Cameras.Count} Kameras.",
                    gateway_model = _tahomaConfig.GatewayModel,
                    connection_mode = _tahomaConfig.ConnectionMode,
                    connexoon_ip = _tahomaConfig.GatewayIp,
                    connexoon_pin = _tahomaConfig.GatewayPin,
                    shutters = _tahomaConfig.CachedShutters.Select(s => new
                    {
                        s.Label,
                        s.ClosurePercent,
                        s.SupportsPosition,
                        s.IsAvailable
                    }),
                    plugs = _smartHomeData.Plugs.Select(p => new
                    {
                        p.Name,
                        p.IpAddress,
                        p.IsOn,
                        p.Model
                    }),
                    groups = _smartHomeData.Groups.Select(g => new
                    {
                        g.Id,
                        g.Name,
                        g.ShutterTargets,
                        g.PlugTargets
                    }),
                    cameras = _smartHomeData.Cameras.Select(c => new
                    {
                        c.Id,
                        c.Name,
                        c.IpAddress,
                        c.DeviceModel,
                        c.HasPtz,
                        c.MotionRecord,
                        c.PreferHdStream,
                        c.IsOnline
                    })
                });
            }
            catch (Exception ex)
            {
                return JsonSerializer.Serialize(new { ok = false, summary = ex.Message });
            }
        }

        public bool RunGroupResolutionSelfTest()
        {
            _tahomaConfig.CachedShutters = new List<TahomaShutterDevice>
            {
                new TahomaShutterDevice { DeviceUrl = "io://0813-5321-9574/101", Label = "Wohnzimmer", ClosurePercent = 0, SupportsPosition = true },
                new TahomaShutterDevice { DeviceUrl = "io://0813-5321-9574/102", Label = "Terrassentür", ClosurePercent = 0, SupportsPosition = true },
                new TahomaShutterDevice { DeviceUrl = "io://0813-5321-9574/103", Label = "Schlafzimmer", ClosurePercent = 100, SupportsPosition = true }
            };
            _smartHomeData.Plugs = new List<SmartPlugConfig>
            {
                new SmartPlugConfig { Name = "Lichterkette Spiegel", IpAddress = "192.168.188.123", Model = "Tapo P100" }
            };
            _smartHomeData.Groups = new List<SmartHomeDeviceGroup>
            {
                new SmartHomeDeviceGroup
                {
                    Id = "grp_test_1",
                    Name = "Gartenfront",
                    ShutterTargets = new List<string> { "Wohnzimmer", "Terrassentür" },
                    PlugTargets = new List<string> { "Lichterkette Spiegel" }
                }
            };
            _smartHomeData.Cameras = new List<SmartCameraConfig>
            {
                new SmartCameraConfig
                {
                    Id = "cam_test_1",
                    Name = "Terrasse",
                    Url = "rtsp://admin:TopSecret123!@192.168.188.140:554/stream2",
                    ServiceUrl = "http://192.168.188.140:2020/onvif/device_service",
                    DeviceModel = "Tapo C210",
                    HasPtz = true,
                    MotionRecord = true,
                    MotionSensitivity = 65,
                    Position = 0
                },
                new SmartCameraConfig
                {
                    Id = "cam_test_2",
                    Name = "Werkstatt",
                    Url = "rtsp://admin:TopSecret123!@192.168.188.141:554/stream1",
                    ServiceUrl = "http://192.168.188.141:2020/onvif/device_service",
                    DeviceModel = "Tapo C220",
                    HasPtz = true,
                    PreferHdStream = true,
                    Position = 1
                }
            };
            SaveSmartHomeConfig(syncPortal: false);

            RenderShutters();
            RenderGroups();

            var grpMatch = ResolveTargetsForCommand("Gruppe Gartenfront");
            if (grpMatch.Group?.Name != "Gartenfront" || grpMatch.Shutters.Count != 2 || grpMatch.Plugs.Count != 1)
                return false;

            // Verify compound natural language ("Wohnzimmer und Terassentür" with single 'r' spelling variant)
            var multiMatch = ResolveTargetsForCommand("Wohnzimmer und Terassentür");
            if (multiMatch.Shutters.Count != 2)
                return false;

            // Verify Camera Surveillance Matrix Layouts (1, 4, 6, 8), PTZ Precision Steps, Modal Masking & Drag-Swap
            SwitchTabUI("cameras");
            SetCameraGridLayout(1);
            if (CameraMatrixGrid.Columns != 1 || CameraMatrixGrid.Rows != 1 || CameraMatrixGrid.Children.Count != 1)
                return false;

            SetCameraGridLayout(4);
            if (CameraMatrixGrid.Columns != 2 || CameraMatrixGrid.Rows != 2 || CameraMatrixGrid.Children.Count != 4)
                return false;

            SetCameraGridLayout(6);
            if (CameraMatrixGrid.Columns != 3 || CameraMatrixGrid.Rows != 2 || CameraMatrixGrid.Children.Count != 6)
                return false;

            SetCameraGridLayout(8);
            if (CameraMatrixGrid.Columns != 4 || CameraMatrixGrid.Rows != 2 || CameraMatrixGrid.Children.Count != 8)
                return false;

            string step1 = CyclePtzStepMode();
            string step2 = CyclePtzStepMode();
            if (string.IsNullOrEmpty(step1) || step1 == step2)
                return false;

            SwapCamerasOrder("Terrasse", "Werkstatt");
            if (_smartHomeData.Cameras[0].Name != "Werkstatt")
                return false;

            OpenCameraModal(_smartHomeData.Cameras[0]);
            if (CameraModalOverlayGrid.Visibility != Visibility.Visible ||
                CamModalRtspPasswordBox.Visibility != Visibility.Visible ||
                CamModalRtspVisibleBox.Visibility != Visibility.Collapsed)
            {
                return false;
            }
            CameraModalOverlayGrid.Visibility = Visibility.Collapsed;

            return GroupsWrapPanel.Children.Count == 1
                && ShuttersQuickGroupsPanel.Children.Count == 1
                && CameraSidebarStack.Children.Count == 2;
        }
    }
}

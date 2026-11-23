using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Linq;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Shapes;
using System.Windows.Threading;

namespace FunkenwerkRemote
{
    public partial class MainWindow : Window
    {
        private sealed class ActiveRdpTab
        {
            public string TabId { get; init; } = Guid.NewGuid().ToString("N");
            public string ProfileId { get; init; } = "";
            public string Title { get; set; } = "";
            public string Host { get; init; } = "";
            public int Port { get; init; } = 3389;
            public RdpSessionHostControl HostControl { get; init; } = null!;
            public int LastConnectionState { get; set; } = -1;
        }

        private readonly string? _startupConnectTarget;
        private List<RemoteConnectionProfile> _profiles = new();
        private readonly List<ActiveRdpTab> _openTabs = new();
        private ActiveRdpTab? _activeTab;

        private bool _isDarkMode = true;
        private bool _isPassVisible;
        private bool _isSetupPassVisible;
        private bool _suppressPassSync;
        private bool _suppressSetupPassSync;
        private bool _currentUseNla;
        private bool _currentViewOnly = true;
        private bool _isFullscreen;
        private WindowState _preFullscreenWindowState = WindowState.Normal;
        private bool _isSetupRunning;
        private bool _probeInFlight;
        private Action? _pendingConfirmAction;

        private readonly DispatcherTimer _sessionMonitorTimer;
        private readonly DispatcherTimer _reachabilityTimer;

        public MainWindow(string? startupConnectTarget = null)
        {
            _startupConnectTarget = string.IsNullOrWhiteSpace(startupConnectTarget) ? null : startupConnectTarget.Trim();

            try
            {
                var shared = Funkenwerk.FunkenwerkSharedSession.Load();
                _isDarkMode = shared.IsDarkMode;
            }
            catch
            {
                _isDarkMode = true;
            }

            InitializeComponent();
            ApplyTheme();
            UpdateViewOnlyButtonUi();
            UpdateNlaButtonUi();
            LoadProfilesIntoUi();

            _sessionMonitorTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(600) };
            _sessionMonitorTimer.Tick += (_, _) => MonitorOpenSessions();
            _sessionMonitorTimer.Start();

            _reachabilityTimer = new DispatcherTimer { Interval = TimeSpan.FromSeconds(7) };
            _reachabilityTimer.Tick += async (_, _) => await RefreshAllReachabilityAsync();
            _reachabilityTimer.Start();

            SetupSudoPassBox.KeyDown += SetupPassInput_KeyDown;
            SetupSudoPassVisibleBox.KeyDown += SetupPassInput_KeyDown;

            Loaded += MainWindow_Loaded;
        }

        public void HandleNativeRdpFault(string detail)
        {
            if (_activeTab != null)
            {
                var failed = _activeTab;
                CloseTab(failed);
                StatusText.Text = $"⚠ RDP-Verbindung von Gegenstelle abgelehnt ({failed.Host}:{failed.Port}).";
                return;
            }
            StatusText.Text = $"⚠ RDP-Protokollfehler abgefangen: {detail}";
        }

        private async void MainWindow_Loaded(object sender, RoutedEventArgs e)
        {
            await RefreshAllReachabilityAsync();

            if (!string.IsNullOrWhiteSpace(_startupConnectTarget))
            {
                var match = _profiles.FirstOrDefault(p =>
                    string.Equals(p.Name, _startupConnectTarget, StringComparison.OrdinalIgnoreCase) ||
                    string.Equals(p.Host, _startupConnectTarget, StringComparison.OrdinalIgnoreCase));
                if (match != null)
                {
                    ProfilesListBox.SelectedItem = match;
                    await ConnectCurrentFormOrPromptSetupAsync();
                }
            }
        }

        private void LoadProfilesIntoUi()
        {
            try
            {
                _profiles = FunkenwerkRemoteSecurity.LoadProfiles();
                ProfilesListBox.ItemsSource = null;
                ProfilesListBox.ItemsSource = _profiles;
                if (_profiles.Count > 0)
                {
                    var mini = _profiles.FirstOrDefault(p => p.IsMiniPcKiosk) ?? _profiles[0];
                    ProfilesListBox.SelectedItem = mini;
                }
            }
            catch (Exception ex)
            {
                _profiles = new List<RemoteConnectionProfile>();
                ProfilesListBox.ItemsSource = _profiles;
                StatusText.Text = $"🚨 Tresor-Schutz aktiv: {ex.Message}";
            }
        }

        private async Task RefreshAllReachabilityAsync()
        {
            if (_probeInFlight || _profiles.Count == 0) return;
            _probeInFlight = true;
            try
            {
                var tasks = _profiles.Select(FunkenwerkRemoteSecurity.ProbeProfileReachabilityAsync).ToArray();
                await Task.WhenAll(tasks);
                ProfilesListBox.Items.Refresh();
                UpdateMiniPcSetupButtonsVisibility();
            }
            catch { }
            finally
            {
                _probeInFlight = false;
            }
        }

        private void UpdateMiniPcSetupButtonsVisibility()
        {
            var selected = ProfilesListBox.SelectedItem as RemoteConnectionProfile;
            bool selectedIsMiniPc = selected != null &&
                                    (selected.IsMiniPcKiosk || string.Equals(selected.Host, FunkenwerkRemoteSecurity.MiniPcDefaultHost, StringComparison.OrdinalIgnoreCase));

            bool showSetup = selectedIsMiniPc && selected!.Reachability == HostReachabilityState.SshOnlineNeedsRdpSetup;

            SetupMiniPcSidebarBtn.Visibility = showSetup ? Visibility.Visible : Visibility.Collapsed;
            EmptySetupMiniPcBtn.Visibility = showSetup ? Visibility.Visible : Visibility.Collapsed;

            if (selected != null)
            {
                QuickConnectMiniPcBtn.Content = $"⚡ {selected.Name} öffnen";
            }
        }

        private void ProfilesListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (ProfilesListBox.SelectedItem is not RemoteConnectionProfile p) return;

            NameBox.Text = p.Name;
            HostBox.Text = p.Host;
            PortBox.Text = p.Port.ToString();
            UserBox.Text = p.Username;
            _currentUseNla = p.UseNla;
            UpdateNlaButtonUi();

            if (_activeTab == null)
            {
                _currentViewOnly = p.DefaultViewOnly;
                UpdateViewOnlyButtonUi();
            }

            try
            {
                SetPasswordValue(FunkenwerkRemoteSecurity.DecryptSecret(p.EncryptedPassword));
            }
            catch (Exception ex)
            {
                SetPasswordValue("");
                StatusText.Text = $"⚠ Tresor-Hinweis ({p.Name}): {ex.Message}";
            }

            UpdateMiniPcSetupButtonsVisibility();
        }

        private async void ProfilesListBox_MouseDoubleClick(object sender, MouseButtonEventArgs e)
        {
            if (ProfilesListBox.SelectedItem is RemoteConnectionProfile)
            {
                await ConnectCurrentFormOrPromptSetupAsync();
            }
        }

        private async void QuickConnectSelected_Click(object sender, RoutedEventArgs e)
        {
            await ConnectCurrentFormOrPromptSetupAsync();
        }

        private async void Connect_Click(object sender, RoutedEventArgs e)
        {
            await ConnectCurrentFormOrPromptSetupAsync();
        }

        private async Task ConnectCurrentFormOrPromptSetupAsync()
        {
            string rawHost = HostBox.Text.Trim();
            if (string.IsNullOrWhiteSpace(rawHost))
            {
                StatusText.Text = "⚠ Bitte Hostname oder IP-Adresse eingeben.";
                HostBox.Focus();
                return;
            }

            string cleanHost;
            try
            {
                cleanHost = FunkenwerkRemoteSecurity.SanitizeHost(rawHost);
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ {ex.Message}";
                return;
            }

            int port = int.TryParse(PortBox.Text.Trim(), out int pt) && pt > 0 && pt <= 65535 ? pt : 3389;
            string user = UserBox.Text.Trim();
            string pass = GetPasswordValue();
            string displayName = string.IsNullOrWhiteSpace(NameBox.Text) ? cleanHost : NameBox.Text.Trim();

            var selectedProfile = ProfilesListBox.SelectedItem as RemoteConnectionProfile;
            bool isMiniPc = (selectedProfile?.IsMiniPcKiosk == true) ||
                            string.Equals(cleanHost, FunkenwerkRemoteSecurity.MiniPcDefaultHost, StringComparison.OrdinalIgnoreCase);

            // Check live TCP reachability before launching ActiveX socket so Mini-PC setup opens cleanly if port 3389 isn't active yet
            StatusText.Text = $"Prüfe Verbindung zu {cleanHost}:{port}...";
            bool rdpOpen = await FunkenwerkRemoteSecurity.IsTcpPortOpenAsync(cleanHost, port, 750);
            if (isMiniPc && (!rdpOpen || string.IsNullOrWhiteSpace(pass)))
            {
                bool sshOpen = await FunkenwerkRemoteSecurity.IsTcpPortOpenAsync(cleanHost, selectedProfile?.SshPort ?? 22, 750);
                if (sshOpen)
                {
                    if (selectedProfile != null)
                    {
                        selectedProfile.Reachability = HostReachabilityState.SshOnlineNeedsRdpSetup;
                        ProfilesListBox.Items.Refresh();
                        UpdateMiniPcSetupButtonsVisibility();
                    }
                    StatusText.Text = "ℹ Mini-PC im WLAN gefunden. Einmalige RDP-Aktivierung erforderlich.";
                    OpenMiniPcSetupModal(prefillPass: pass);
                    return;
                }
            }

            if (!rdpOpen)
            {
                StatusText.Text = $"⚠ {displayName} ({cleanHost}:{port}) antwortet aktuell nicht im Netzwerk.";
                return;
            }

            // Focus existing tab if already connected to the same Host + Port
            var existingTab = _openTabs.FirstOrDefault(t =>
                string.Equals(t.Host, cleanHost, StringComparison.OrdinalIgnoreCase) &&
                t.Port == port);

            if (existingTab != null)
            {
                int state = existingTab.HostControl.GetConnectionState();
                if (state == 1 || state == 2)
                {
                    ActivateTab(existingTab);
                    StatusText.Text = $"✓ Aktive Sitzung '{existingTab.Title}' in den Vordergrund geholt.";
                    return;
                }
                CloseTab(existingTab);
            }

            int deskW = selectedProfile?.DesktopWidth ?? 2560;
            int deskH = selectedProfile?.DesktopHeight ?? 1440;
            bool smartSizing = selectedProfile?.SmartSizing ?? true;
            bool initialViewOnly = selectedProfile?.DefaultViewOnly ?? _currentViewOnly;

            try
            {
                var hostCtrl = new RdpSessionHostControl(initialViewOnly: initialViewOnly);
                hostCtrl.ConfigureConnection(
                    host: cleanHost,
                    port: port,
                    username: user,
                    password: pass,
                    desktopWidth: deskW,
                    desktopHeight: deskH,
                    smartSizing: smartSizing,
                    useNla: _currentUseNla);

                var newTab = new ActiveRdpTab
                {
                    ProfileId = selectedProfile?.Id ?? "",
                    Title = displayName,
                    Host = cleanHost,
                    Port = port,
                    HostControl = hostCtrl
                };

                _openTabs.Add(newTab);
                ActivateTab(newTab);
                hostCtrl.Connect();

                StatusText.Text = $"⚡ Verbinde mit {displayName} ({cleanHost}:{port})...";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"🚨 RDP-Start fehlgeschlagen: {ex.Message}";
            }
        }

        private void ActivateTab(ActiveRdpTab tab)
        {
            _activeTab = tab;
            _currentViewOnly = tab.HostControl.IsViewOnly;
            UpdateViewOnlyButtonUi();

            SessionHostContainer.Children.Clear();
            SessionHostContainer.Children.Add(tab.HostControl.WpfHost);

            SyncStageVisibility();
            RenderSessionTabs();
        }

        private void CloseTab(ActiveRdpTab tab)
        {
            bool wasActive = ReferenceEquals(_activeTab, tab);
            _openTabs.Remove(tab);

            if (SessionHostContainer.Children.Contains(tab.HostControl.WpfHost))
            {
                SessionHostContainer.Children.Remove(tab.HostControl.WpfHost);
            }

            try { tab.HostControl.Dispose(); } catch { }

            if (wasActive)
            {
                _activeTab = _openTabs.LastOrDefault();
                if (_activeTab != null)
                {
                    ActivateTab(_activeTab);
                    return;
                }
            }

            if (_openTabs.Count == 0 && _isFullscreen)
            {
                SetFullscreenMode(false);
            }

            SyncStageVisibility();
            RenderSessionTabs();
            StatusText.Text = $"⏹ Verbindung '{tab.Title}' getrennt.";
        }

        private void SyncStageVisibility()
        {
            bool modalOpen = SetupModalOverlayGrid.Visibility == Visibility.Visible ||
                             ConfirmModalOverlayGrid.Visibility == Visibility.Visible;

            bool hasActive = _activeTab != null && _openTabs.Count > 0;

            EmptyStagePanel.Visibility = hasActive ? Visibility.Collapsed : Visibility.Visible;
            SessionTabsBar.Visibility = hasActive ? Visibility.Visible : Visibility.Collapsed;
            SessionHostContainer.Visibility = (hasActive && !modalOpen) ? Visibility.Visible : Visibility.Collapsed;
        }

        private void RenderSessionTabs()
        {
            SessionTabsWrapPanel.Children.Clear();
            foreach (var tab in _openTabs)
            {
                bool isCurrent = ReferenceEquals(tab, _activeTab);
                int connState = tab.HostControl.GetConnectionState();
                string dotHex = connState switch
                {
                    1 => "#10B981",
                    2 => "#F59E0B",
                    _ => "#EF4444"
                };

                var pill = new Border
                {
                    CornerRadius = new CornerRadius(7),
                    Padding = new Thickness(10, 4, 6, 4),
                    Margin = new Thickness(0, 0, 6, 0),
                    Background = BrushHex(isCurrent ? (_isDarkMode ? "#26C5A059" : "#22C5A059") : (_isDarkMode ? "#181C26" : "#F3F4F6")),
                    BorderBrush = BrushHex(isCurrent ? "#C5A059" : (_isDarkMode ? "#2D3343" : "#D1D5DB")),
                    BorderThickness = new Thickness(1),
                    Cursor = Cursors.Hand
                };

                var row = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
                row.Children.Add(new Ellipse
                {
                    Width = 8,
                    Height = 8,
                    Fill = BrushHex(dotHex),
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(0, 0, 7, 0)
                });

                string modeSuffix = tab.HostControl.IsViewOnly ? " · Ansicht" : " · Steuern";
                row.Children.Add(new TextBlock
                {
                    Text = tab.Title + modeSuffix,
                    Foreground = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827"),
                    FontSize = 12,
                    FontWeight = isCurrent ? FontWeights.Bold : FontWeights.SemiBold,
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(0, 0, 8, 0)
                });

                var closeBtn = new Button
                {
                    Content = "✕",
                    Width = 20,
                    Height = 20,
                    Background = Brushes.Transparent,
                    Foreground = BrushHex(_isDarkMode ? "#9CA3AF" : "#4B5563"),
                    BorderThickness = new Thickness(0),
                    Cursor = Cursors.Hand,
                    ToolTip = "Sitzung trennen"
                };
                var capturedTab = tab;
                closeBtn.Click += (s, e) =>
                {
                    e.Handled = true;
                    CloseTab(capturedTab);
                };
                row.Children.Add(closeBtn);

                pill.Child = row;
                pill.MouseLeftButtonUp += (_, _) => ActivateTab(capturedTab);
                SessionTabsWrapPanel.Children.Add(pill);
            }
        }

        private void MonitorOpenSessions()
        {
            if (_openTabs.Count == 0) return;

            bool changed = false;
            foreach (var tab in _openTabs.ToList())
            {
                int state = tab.HostControl.GetConnectionState();
                if (state != tab.LastConnectionState)
                {
                    int prev = tab.LastConnectionState;
                    tab.LastConnectionState = state;
                    changed = true;

                    if (ReferenceEquals(tab, _activeTab))
                    {
                        if (state == 1)
                        {
                            string modeTxt = tab.HostControl.IsViewOnly ? "Nur Ansicht (Fehlklick-Schutz aktiv)" : "Maus & Tastatur aktiv";
                            StatusText.Text = $"🟢 Verbunden mit {tab.Title} ({tab.Host}:{tab.Port}) · {modeTxt}";
                        }
                        else if (state == 0 && prev >= 1)
                        {
                            StatusText.Text = $"⏹ Sitzung '{tab.Title}' wurde getrennt.";
                        }
                    }
                }
            }

            if (changed)
            {
                RenderSessionTabs();
            }
        }

        // =====================================================================
        // VIEW-ONLY VS INTERACTIVE CONTROL & NLA TOGGLE
        // =====================================================================
        private void ViewOnlyToggle_Click(object sender, RoutedEventArgs e)
        {
            _currentViewOnly = !_currentViewOnly;
            if (_activeTab != null)
            {
                _activeTab.HostControl.SetViewOnly(_currentViewOnly);
                RenderSessionTabs();
                StatusText.Text = _currentViewOnly
                    ? $"👁 '{_activeTab.Title}': Nur Ansicht aktiv (Mausklicks gesperrt)."
                    : $"🖱 '{_activeTab.Title}': Live-Steuerung (Maus & Tastatur) freigegeben.";
            }
            UpdateViewOnlyButtonUi();
        }

        private void UpdateViewOnlyButtonUi()
        {
            if (ViewOnlyToggleBtn == null) return;
            if (_currentViewOnly)
            {
                ViewOnlyToggleBtn.Content = "👁 NUR ANSICHT";
                ViewOnlyToggleBtn.Foreground = BrushHex("#C5A059");
                ViewOnlyToggleBtn.BorderBrush = BrushHex("#C5A059");
            }
            else
            {
                ViewOnlyToggleBtn.Content = "🖱 STEUERN";
                ViewOnlyToggleBtn.Foreground = BrushHex("#10B981");
                ViewOnlyToggleBtn.BorderBrush = BrushHex("#10B981");
            }
        }

        private void NlaToggle_Click(object sender, RoutedEventArgs e)
        {
            _currentUseNla = !_currentUseNla;
            UpdateNlaButtonUi();
            StatusText.Text = _currentUseNla
                ? "🔐 Authentifizierung: Windows NLA (CredSSP) aktiv."
                : "🔐 Authentifizierung: Standard-TLS (optimal für Linux xrdp / Mini-PC).";
        }

        private void UpdateNlaButtonUi()
        {
            if (NlaToggleBtn == null) return;
            NlaToggleBtn.Content = _currentUseNla ? "NLA" : "TLS";
            NlaToggleBtn.Foreground = BrushHex(_currentUseNla ? "#C5A059" : (_isDarkMode ? "#9CA3AF" : "#4B5563"));
        }

        // =====================================================================
        // FULLSCREEN (F11 / ESC) & EXTERNAL MSTSC
        // =====================================================================
        private void Fullscreen_Click(object sender, RoutedEventArgs e)
        {
            SetFullscreenMode(!_isFullscreen);
        }

        private void SetFullscreenMode(bool fullscreen)
        {
            if (_isFullscreen == fullscreen) return;
            _isFullscreen = fullscreen;

            if (fullscreen)
            {
                _preFullscreenWindowState = WindowState;
                TopBarBorder.Visibility = Visibility.Collapsed;
                TopBarRow.Height = new GridLength(0);
                BottomBarBorder.Visibility = Visibility.Collapsed;
                BottomBarRow.Height = new GridLength(0);
                SidebarTileBorder.Visibility = Visibility.Collapsed;
                SidebarCol.Width = new GridLength(0);
                SpacerCol.Width = new GridLength(0);
                MainWorkspaceGrid.Margin = new Thickness(0);
                StageTileBorder.CornerRadius = new CornerRadius(0);
                StageTileBorder.BorderThickness = new Thickness(0);
                ExitFullscreenFloatingBtn.Visibility = Visibility.Visible;

                WindowStyle = WindowStyle.None;
                WindowState = WindowState.Maximized;
            }
            else
            {
                WindowStyle = WindowStyle.SingleBorderWindow;
                WindowState = _preFullscreenWindowState == WindowState.Minimized ? WindowState.Normal : _preFullscreenWindowState;

                TopBarBorder.Visibility = Visibility.Visible;
                TopBarRow.Height = new GridLength(64);
                BottomBarBorder.Visibility = Visibility.Visible;
                BottomBarRow.Height = new GridLength(36);
                SidebarTileBorder.Visibility = Visibility.Visible;
                SidebarCol.Width = new GridLength(280);
                SpacerCol.Width = new GridLength(14);
                MainWorkspaceGrid.Margin = new Thickness(16, 14, 16, 14);
                StageTileBorder.CornerRadius = new CornerRadius(14);
                StageTileBorder.BorderThickness = new Thickness(1);
                ExitFullscreenFloatingBtn.Visibility = Visibility.Collapsed;
            }
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.F11)
            {
                SetFullscreenMode(!_isFullscreen);
                e.Handled = true;
                return;
            }

            if (e.Key == Key.Escape && _isFullscreen && (_activeTab == null || _activeTab.HostControl.IsViewOnly))
            {
                SetFullscreenMode(false);
                e.Handled = true;
            }
        }

        private void ExternalMstsc_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                int port = int.TryParse(PortBox.Text.Trim(), out int pt) && pt > 0 && pt <= 65535 ? pt : 3389;
                RdpSessionHostControl.LaunchExternalMstsc(HostBox.Text, port);
                StatusText.Text = "↗ Externer Windows-Remotedesktop gestartet.";
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ {ex.Message}";
            }
        }

        // =====================================================================
        // PROFILE CRUD & MANDATORY EYE-ICON PASSWORD SYNC
        // =====================================================================
        private string GetPasswordValue() => _isPassVisible ? PassVisibleBox.Text : PassBox.Password;

        private void SetPasswordValue(string val)
        {
            _suppressPassSync = true;
            PassBox.Password = val ?? "";
            PassVisibleBox.Text = val ?? "";
            _suppressPassSync = false;
        }

        private void PassBox_PasswordChanged(object sender, RoutedEventArgs e)
        {
            if (_suppressPassSync) return;
            _suppressPassSync = true;
            PassVisibleBox.Text = PassBox.Password;
            _suppressPassSync = false;
        }

        private void PassVisibleBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (_suppressPassSync) return;
            _suppressPassSync = true;
            PassBox.Password = PassVisibleBox.Text;
            _suppressPassSync = false;
        }

        private void TogglePassEye_Click(object sender, RoutedEventArgs e)
        {
            _isPassVisible = !_isPassVisible;
            PassVisibleBox.Visibility = _isPassVisible ? Visibility.Visible : Visibility.Collapsed;
            PassBox.Visibility = _isPassVisible ? Visibility.Collapsed : Visibility.Visible;
            TogglePassEyeBtn.Content = _isPassVisible ? "🙈" : "👁";
        }

        private void NewProfile_Click(object sender, RoutedEventArgs e)
        {
            ProfilesListBox.SelectedItem = null;
            NameBox.Text = "";
            HostBox.Text = "";
            PortBox.Text = "3389";
            UserBox.Text = "";
            _currentUseNla = true;
            _currentViewOnly = false;
            UpdateNlaButtonUi();
            UpdateViewOnlyButtonUi();
            SetPasswordValue("");
            UpdateMiniPcSetupButtonsVisibility();
            NameBox.Focus();
        }

        private async void SaveProfile_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                string cleanHost = FunkenwerkRemoteSecurity.SanitizeHost(HostBox.Text);
                int port = int.TryParse(PortBox.Text.Trim(), out int pt) && pt > 0 && pt <= 65535 ? pt : 3389;
                string user = UserBox.Text.Trim();
                string pass = GetPasswordValue();
                string name = string.IsNullOrWhiteSpace(NameBox.Text)
                    ? (string.IsNullOrWhiteSpace(user) ? cleanHost : $"{user}@{cleanHost}")
                    : NameBox.Text.Trim();

                if (ProfilesListBox.SelectedItem is RemoteConnectionProfile current &&
                    string.Equals(current.Host, cleanHost, StringComparison.OrdinalIgnoreCase))
                {
                    current.Name = name;
                    current.Host = cleanHost;
                    current.Port = port;
                    current.Username = user;
                    current.UseNla = _currentUseNla;
                    current.DefaultViewOnly = _currentViewOnly;
                    current.EncryptedPassword = FunkenwerkRemoteSecurity.EncryptSecret(pass);
                }
                else
                {
                    bool isMini = string.Equals(cleanHost, FunkenwerkRemoteSecurity.MiniPcDefaultHost, StringComparison.OrdinalIgnoreCase);
                    var created = new RemoteConnectionProfile
                    {
                        Name = name,
                        Host = cleanHost,
                        Port = port,
                        Username = user,
                        EncryptedPassword = FunkenwerkRemoteSecurity.EncryptSecret(pass),
                        DesktopWidth = isMini ? 2560 : 1920,
                        DesktopHeight = isMini ? 1440 : 1080,
                        SmartSizing = true,
                        DefaultViewOnly = _currentViewOnly,
                        UseNla = _currentUseNla,
                        IsMiniPcKiosk = isMini,
                        SshPort = 22,
                        SshPrivateKeyPath = isMini ? FunkenwerkRemoteSecurity.ResolveDefaultMiniPcKeyPath() : ""
                    };
                    _profiles.Add(created);
                    ProfilesListBox.ItemsSource = null;
                    ProfilesListBox.ItemsSource = _profiles;
                    ProfilesListBox.SelectedItem = created;
                }

                FunkenwerkRemoteSecurity.SaveProfiles(_profiles);
                ProfilesListBox.Items.Refresh();
                StatusText.Text = "🔒 RDP-Profil AES-256-GCM + DPAPI verschlüsselt gespeichert.";
                await RefreshAllReachabilityAsync();
            }
            catch (Exception ex)
            {
                StatusText.Text = $"⚠ {ex.Message}";
            }
        }

        private void DeleteProfile_Click(object sender, RoutedEventArgs e)
        {
            if (ProfilesListBox.SelectedItem is not RemoteConnectionProfile current) return;

            ConfirmTitleTxt.Text = "Verbindung entfernen";
            ConfirmBodyTxt.Text = $"Gespeicherte Verbindung '{current.Name}' wirklich löschen?";
            _pendingConfirmAction = () =>
            {
                _profiles.Remove(current);
                if (_profiles.Count == 0)
                {
                    _profiles.Add(FunkenwerkRemoteSecurity.CreateDefaultMiniPcProfile());
                }
                FunkenwerkRemoteSecurity.SaveProfiles(_profiles);
                ProfilesListBox.ItemsSource = null;
                ProfilesListBox.ItemsSource = _profiles;
                ProfilesListBox.SelectedIndex = 0;
                StatusText.Text = $"✓ Verbindung '{current.Name}' entfernt.";
            };

            ConfirmModalOverlayGrid.Visibility = Visibility.Visible;
            SyncStageVisibility();
        }

        private void ConfirmCancel_Click(object sender, RoutedEventArgs e)
        {
            _pendingConfirmAction = null;
            ConfirmModalOverlayGrid.Visibility = Visibility.Collapsed;
            SyncStageVisibility();
        }

        private void ConfirmAccept_Click(object sender, RoutedEventArgs e)
        {
            var act = _pendingConfirmAction;
            _pendingConfirmAction = null;
            ConfirmModalOverlayGrid.Visibility = Visibility.Collapsed;
            SyncStageVisibility();
            act?.Invoke();
        }

        private async void RefreshStatus_Click(object sender, RoutedEventArgs e)
        {
            StatusText.Text = "Prüfe erreichbare Systeme im lokalen Netzwerk...";
            await RefreshAllReachabilityAsync();
            StatusText.Text = "✓ Netzwerkstatus aktualisiert.";
        }

        // =====================================================================
        // 1-CLICK MINI-PC LIVE-KIOSK RDP PROVISIONING MODAL
        // =====================================================================
        private void OpenMiniPcSetupModal_Click(object sender, RoutedEventArgs e)
        {
            OpenMiniPcSetupModal(prefillPass: GetPasswordValue());
        }

        private void OpenMiniPcSetupModal(string prefillPass)
        {
            SetSetupPasswordValue(prefillPass);
            SetupProgressText.Text = "";
            SetupProgressText.Visibility = Visibility.Collapsed;
            RunSetupModalBtn.IsEnabled = true;
            CancelSetupModalBtn.IsEnabled = true;
            SetupModalOverlayGrid.Visibility = Visibility.Visible;
            SyncStageVisibility();

            if (_isSetupPassVisible) SetupSudoPassVisibleBox.Focus();
            else SetupSudoPassBox.Focus();
        }

        private string GetSetupPasswordValue() => _isSetupPassVisible ? SetupSudoPassVisibleBox.Text : SetupSudoPassBox.Password;

        private void SetSetupPasswordValue(string val)
        {
            _suppressSetupPassSync = true;
            SetupSudoPassBox.Password = val ?? "";
            SetupSudoPassVisibleBox.Text = val ?? "";
            _suppressSetupPassSync = false;
        }

        private void SetupSudoPassBox_PasswordChanged(object sender, RoutedEventArgs e)
        {
            if (_suppressSetupPassSync) return;
            _suppressSetupPassSync = true;
            SetupSudoPassVisibleBox.Text = SetupSudoPassBox.Password;
            _suppressSetupPassSync = false;
        }

        private void SetupSudoPassVisibleBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (_suppressSetupPassSync) return;
            _suppressSetupPassSync = true;
            SetupSudoPassBox.Password = SetupSudoPassVisibleBox.Text;
            _suppressSetupPassSync = false;
        }

        private void ToggleSetupEye_Click(object sender, RoutedEventArgs e)
        {
            _isSetupPassVisible = !_isSetupPassVisible;
            SetupSudoPassVisibleBox.Visibility = _isSetupPassVisible ? Visibility.Visible : Visibility.Collapsed;
            SetupSudoPassBox.Visibility = _isSetupPassVisible ? Visibility.Collapsed : Visibility.Visible;
            ToggleSetupEyeBtn.Content = _isSetupPassVisible ? "🙈" : "👁";
        }

        private void SetupPassInput_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter && !_isSetupRunning)
            {
                e.Handled = true;
                RunSetupModal_Click(sender, new RoutedEventArgs());
            }
        }

        private void CancelSetupModal_Click(object sender, RoutedEventArgs e)
        {
            if (_isSetupRunning) return;
            SetSetupPasswordValue("");
            SetupModalOverlayGrid.Visibility = Visibility.Collapsed;
            SyncStageVisibility();
        }

        private async void RunSetupModal_Click(object sender, RoutedEventArgs e)
        {
            if (_isSetupRunning) return;
            string sudoPass = GetSetupPasswordValue();
            if (string.IsNullOrWhiteSpace(sudoPass))
            {
                SetupProgressText.Foreground = BrushHex("#EF4444");
                SetupProgressText.Text = "Bitte das Linux-Passwort für 'alina' eingeben.";
                SetupProgressText.Visibility = Visibility.Visible;
                return;
            }

            var miniProfile = (ProfilesListBox.SelectedItem as RemoteConnectionProfile)
                              ?? _profiles.FirstOrDefault(p => p.IsMiniPcKiosk)
                              ?? FunkenwerkRemoteSecurity.CreateDefaultMiniPcProfile();

            _isSetupRunning = true;
            RunSetupModalBtn.IsEnabled = false;
            CancelSetupModalBtn.IsEnabled = false;
            SetupProgressText.Foreground = BrushHex("#C5A059");
            SetupProgressText.Visibility = Visibility.Visible;
            SetupProgressText.Text = "Starte SSH-Einrichtung auf 192.168.188.151...";

            try
            {
                var (ok, msg) = await FunkenwerkRemoteSecurity.ProvisionMiniPcKioskRdpAsync(
                    miniProfile,
                    sudoPass,
                    step => Dispatcher.Invoke(() =>
                    {
                        SetupProgressText.Text = step;
                        StatusText.Text = step;
                    }));

                if (!ok)
                {
                    SetupProgressText.Foreground = BrushHex("#EF4444");
                    SetupProgressText.Text = msg;
                    StatusText.Text = $"⚠ {msg}";
                    return;
                }

                // Store encrypted password into the Mini-PC profile so all future 1-click connections require zero typing
                miniProfile.EncryptedPassword = FunkenwerkRemoteSecurity.EncryptSecret(sudoPass);
                miniProfile.UseNla = false;
                FunkenwerkRemoteSecurity.SaveProfiles(_profiles);
                SetPasswordValue(sudoPass);
                _currentUseNla = false;
                UpdateNlaButtonUi();

                SetSetupPasswordValue("");
                SetupModalOverlayGrid.Visibility = Visibility.Collapsed;
                SyncStageVisibility();

                await RefreshAllReachabilityAsync();
                StatusText.Text = $"✓ {msg}";

                // Immediately open the live Kiosk RDP view!
                await ConnectCurrentFormOrPromptSetupAsync();
            }
            finally
            {
                _isSetupRunning = false;
                RunSetupModalBtn.IsEnabled = true;
                CancelSetupModalBtn.IsEnabled = true;
            }
        }

        // =====================================================================
        // 100% LIGHT- & DARK-MODE CONSISTENCY
        // =====================================================================
        private void ThemeToggle_Click(object sender, RoutedEventArgs e)
        {
            _isDarkMode = !_isDarkMode;
            ApplyTheme();
            RenderSessionTabs();
        }

        private void ApplyTheme()
        {
            if (ThemeToggleBtn != null)
            {
                ThemeToggleBtn.Content = _isDarkMode ? "☀" : "🌙";
            }

            var res = Application.Current.Resources;
            res["RdpBgRoot"] = BrushHex(_isDarkMode ? "#0F1117" : "#F3F4F6");
            res["RdpBgBar"] = BrushHex(_isDarkMode ? "#12151E" : "#FFFFFF");
            res["RdpBgSidebar"] = BrushHex(_isDarkMode ? "#141821" : "#FFFFFF");
            res["RdpBgCard"] = BrushHex(_isDarkMode ? "#1A1F2C" : "#F9FAFB");
            res["RdpBgCardHover"] = BrushHex(_isDarkMode ? "#24C5A059" : "#1FC5A059");
            res["RdpBgCardSelected"] = BrushHex(_isDarkMode ? "#33C5A059" : "#2AC5A059");
            res["RdpBgInput"] = BrushHex(_isDarkMode ? "#181C26" : "#F9FAFB");
            res["RdpBgButton"] = BrushHex(_isDarkMode ? "#1B1F2B" : "#F3F4F6");
            res["RdpBorderSubtle"] = BrushHex(_isDarkMode ? "#232734" : "#E5E7EB");
            res["RdpBorderStrong"] = BrushHex(_isDarkMode ? "#2D3343" : "#D1D5DB");
            res["RdpFgPrimary"] = BrushHex(_isDarkMode ? "#F9FAFB" : "#111827");
            res["RdpFgSecondary"] = BrushHex(_isDarkMode ? "#9CA3AF" : "#4B5563");

            UpdateNlaButtonUi();
        }

        private static SolidColorBrush BrushHex(string hex)
        {
            var b = new SolidColorBrush((Color)ColorConverter.ConvertFromString(hex));
            b.Freeze();
            return b;
        }

        private void Window_Closing(object sender, CancelEventArgs e)
        {
            _sessionMonitorTimer.Stop();
            _reachabilityTimer.Stop();

            foreach (var tab in _openTabs.ToList())
            {
                try { tab.HostControl.Dispose(); } catch { }
            }
            _openTabs.Clear();
        }
    }
}

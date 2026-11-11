using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Security.Cryptography;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Interop;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Threading;
using Microsoft.Win32;
using Path = System.IO.Path;

namespace FunkenwerkTresor
{
    public sealed class EntryRowViewModel
    {
        public KdbxEntry Entry { get; init; } = null!;
        public ImageSource IconImage => Entry.IconImage;
        public string Title => Entry.Title;
        public string UserName => Entry.UserName;
        public string PasswordDisplay { get; init; } = "••••••••";
        public string Url => Entry.Url;
        public string NotesSingleLine => Entry.NotesSingleLine;
    }

    public sealed class GroupComboOption
    {
        public KdbxGroup Group { get; init; } = null!;
        public string DisplayPath { get; init; } = "";
        public override string ToString() => DisplayPath;
    }

    public sealed class OpenedVaultSession
    {
        public KdbxDatabase Database { get; set; } = null!;
        public string MasterPassword { get; set; } = "";
        public string? KeyfilePath { get; set; }
        public KdbxGroup? SelectedGroup { get; set; }
        public KdbxEntry? SelectedEntry { get; set; }
        public string DisplayTitle => string.IsNullOrWhiteSpace(Database.FilePath)
            ? $"{Database.DatabaseName}.kdbx"
            : Path.GetFileName(Database.FilePath);
    }

    public sealed class UsernameSuggestionItem
    {
        public string UserName { get; init; } = "";
        public string ContextHint { get; init; } = "";
    }

    public partial class MainWindow : Window
    {
        private readonly TresorAppSettings _settings;
        private readonly DispatcherTimer _mainTickTimer;
        private DateTime _lastUserActivityUtc = DateTime.UtcNow;
        private DateTime _saveFeedbackUntilUtc = DateTime.MinValue;

        private readonly List<OpenedVaultSession> _openSessions = new();
        private OpenedVaultSession? _activeSession;

        private KdbxDatabase? _activeDb;
        private string? _activeMasterPassword;
        private string? _activeKeyfilePath;

        private KdbxGroup? _selectedGroup;
        private KdbxEntry? _selectedEntry;
        private bool _showAllEntriesMode;
        private bool _revealTablePasswords;
        private string _sortColumn = "Title";
        private bool _sortAscending = true;

        // Modal state
        private KdbxEntry? _editingEntry;
        private bool _isCreatingNewEntry;
        private List<KdbxAttachment> _editingAttachments = new();

        private KdbxGroup? _editingGroupTarget;
        private bool _isCreatingNewSubgroup;
        private bool _saveCurrentTreeOnCreate;

        // Eye toggle state
        private bool _openMasterEyeVisible;
        private bool _createMasterEyeVisible;
        private bool _editPassEyeVisible;
        private bool _editTotpEyeVisible;
        private bool _importPassEyeVisible;

        private bool _suppressTreeSelectionEvent;
        private bool _suppressAutoLockEvents;

        // Drag & Drop State (Einträge & Ordner verschieben)
        private Point _entryDragStartPoint;
        private KdbxEntry? _potentialDragEntry;
        private Point _groupDragStartPoint;
        private KdbxGroup? _potentialDragGroup;
        private TreeViewItem? _dragHighlightedTreeItem;
        private DateTime _dragHoverStartTimeUtc = DateTime.MinValue;

        public MainWindow(string? initialKdbxFilePath = null)
        {
            _suppressAutoLockEvents = true;
            _settings = TresorAppSettings.Load();

            InitializeComponent();

            ApplyTheme(_settings.DarkMode);
            LoadBrandAndToolbarIcons();

            // Icon Pickers initialisieren
            var selectableIcons = KeePassIconRenderer.GetSelectableIcons();
            EditEntryIconPicker.ItemsSource = selectableIcons;
            GroupModalIconPicker.ItemsSource = selectableIcons;

            // Optionaler Auto-Sperre Schalter initialisieren (Standard: AUS -> bleibt geöffnet)
            AutoLockCheckBox.IsChecked = _settings.AutoLockEnabled;
            AutoLockCombo.IsEnabled = _settings.AutoLockEnabled;
            SelectAutoLockComboMinutes(_settings.AutoLockMinutes);
            MenuScreenShield.IsChecked = _settings.ScreenCaptureProtection;
            _suppressAutoLockEvents = false;
            UpdateAutoLockStatusText();

            // Wenn eine .kdbx per Kommandozeile / Doppelklick übergeben wurde, direkt als zuletzt genutzte DB speichern
            if (!string.IsNullOrWhiteSpace(initialKdbxFilePath) && File.Exists(initialKdbxFilePath))
            {
                _settings.AddRecentDatabase(initialKdbxFilePath);
            }

            // Gatekeeper Felder mit der zuletzt hinzugefügten / geöffneten DB vorbelegen
            string defaultNewPath = Path.Combine(TresorAppSettings.DefaultVaultsDir, "DB - Tresor.kdbx");
            CreateDbPathBox.Text = defaultNewPath;

            string latestDbPath = _settings.GetMostRecentDatabasePath();
            if (!string.IsNullOrWhiteSpace(latestDbPath))
                OpenDbPathBox.Text = latestDbPath;
            if (!string.IsNullOrWhiteSpace(_settings.LastKeyfilePath) && File.Exists(_settings.LastKeyfilePath))
                OpenKeyfilePathBox.Text = _settings.LastKeyfilePath;
            RefreshRecentDatabasesButtons();

            // Vor der Passworteingabe ist absolut nichts vom Tresor-Innenleben (Ordnerstruktur/Tabelle/Menü) sichtbar!
            // Direkt die Passworteingabe der zuletzt hinzugefügten DB öffnen und fokussieren.
            SetVaultLockedUiState(isLocked: true);
            bool hasExistingDb = !string.IsNullOrWhiteSpace(OpenDbPathBox.Text) && File.Exists(OpenDbPathBox.Text);
            ShowGatekeeperOverlay(openTab: hasExistingDb || _settings.RecentDatabases.Any(File.Exists), preserveCurrentTreeOnCreate: false);

            Loaded += (_, _) => FocusGatekeeperPasswordInput();
            ContentRendered += (_, _) => FocusGatekeeperPasswordInput();

            // 250ms Timer für TOTP-Live-Ticker, 12s Clipboard-Guard & optionale Auto-Sperre
            _mainTickTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(250) };
            _mainTickTimer.Tick += MainTickTimer_Tick;
            _mainTickTimer.Start();

            SystemEvents.SessionSwitch += SystemEvents_SessionSwitch;
            SourceInitialized += MainWindow_SourceInitialized;
        }

        private void MainWindow_SourceInitialized(object? sender, EventArgs e)
        {
            TresorSecurityGuard.ApplyScreenCaptureProtection(this, _settings.ScreenCaptureProtection);
        }

        private void SystemEvents_SessionSwitch(object sender, SessionSwitchEventArgs e)
        {
            if (e.Reason == SessionSwitchReason.SessionLock || e.Reason == SessionSwitchReason.RemoteDisconnect)
            {
                // Nur automatisch sperren, wenn die optionale Auto-Sperre aktiviert ist
                if (_settings.AutoLockEnabled)
                {
                    Dispatcher.Invoke(() => LockVault("Tresor wegen Windows-Sitzungssperre automatisch gesperrt."));
                }
            }
        }

        private void LoadBrandAndToolbarIcons()
        {
            TbIconNew.Source = KeePassIconRenderer.GetStandardIcon(22);
            TbIconOpen.Source = KeePassIconRenderer.GetStandardIcon(49);
            TbIconAddEntry.Source = KeePassIconRenderer.GetStandardIcon(0);
            TbIconAddGroup.Source = KeePassIconRenderer.GetStandardIcon(48);

            try
            {
                string pngPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "funkenwerk_tresor.png");
                if (File.Exists(pngPath))
                {
                    var bmp = new BitmapImage();
                    bmp.BeginInit();
                    bmp.CacheOption = BitmapCacheOption.OnLoad;
                    bmp.UriSource = new Uri(pngPath, UriKind.Absolute);
                    bmp.EndInit();
                    bmp.Freeze();
                    GatekeeperLogoImg.Source = bmp;
                }
            }
            catch { }
        }

        // =====================================================================
        // THEME ENGINE (KLASSISCH HELL WIE SCREENSHOT vs. DUNKEL)
        // =====================================================================
        private void ApplyTheme(bool dark)
        {
            _settings.DarkMode = dark;
            void SetBrush(string key, string hex)
            {
                Resources[key] = (SolidColorBrush)new BrushConverter().ConvertFromString(hex)!;
            }

            if (dark)
            {
                SetBrush("AppBgBrush", "#111622");
                SetBrush("MenuBgBrush", "#161D2B");
                SetBrush("ToolbarBgBrush", "#1A2233");
                SetBrush("PaneBgBrush", "#0E131D");
                SetBrush("HeaderBgBrush", "#182030");
                SetBrush("BorderBrushColor", "#2A354B");
                SetBrush("GridLineBrush", "#1E293B");
                SetBrush("TextPrimaryBrush", "#F1F5F9");
                SetBrush("TextSecondaryBrush", "#94A3B8");
                SetBrush("TextMutedBrush", "#64748B");
                SetBrush("SelectionBgBrush", "#2B3A55");
                SetBrush("SelectionBorderBrush", "#C5A059");
                SetBrush("HoverBgBrush", "#1B2538");
                SetBrush("ModalCardBgBrush", "#151C28");
                SetBrush("InputBgBrush", "#0B0F17");
                ThemeBtnIcon.Text = "☀";
            }
            else
            {
                // Exakte klassische KeePass-Farben wie auf Alinas Screenshot
                SetBrush("AppBgBrush", "#F0F0F0");
                SetBrush("MenuBgBrush", "#F8F8F8");
                SetBrush("ToolbarBgBrush", "#F3F3F3");
                SetBrush("PaneBgBrush", "#FFFFFF");
                SetBrush("HeaderBgBrush", "#F7F7F7");
                SetBrush("BorderBrushColor", "#D0D0D0");
                SetBrush("GridLineBrush", "#EAEAEA");
                SetBrush("TextPrimaryBrush", "#111111");
                SetBrush("TextSecondaryBrush", "#444444");
                SetBrush("TextMutedBrush", "#777777");
                SetBrush("SelectionBgBrush", "#DCEBFC");
                SetBrush("SelectionBorderBrush", "#7DA2CE");
                SetBrush("HoverBgBrush", "#EEF5FD");
                SetBrush("ModalCardBgBrush", "#FFFFFF");
                SetBrush("InputBgBrush", "#FFFFFF");
                ThemeBtnIcon.Text = "🌙";
            }
        }

        private void ToggleTheme_Click(object sender, RoutedEventArgs e)
        {
            ApplyTheme(!_settings.DarkMode);
            _settings.Save();
        }

        // =====================================================================
        // OPTIONALER AUTO-SPERRE SCHALTER (STANDARD: AUS -> BLEIBT GEÖFFNET)
        // =====================================================================
        private void SelectAutoLockComboMinutes(int mins)
        {
            foreach (ComboBoxItem item in AutoLockCombo.Items)
            {
                if (int.TryParse(item.Tag?.ToString(), out int v) && v == mins)
                {
                    AutoLockCombo.SelectedItem = item;
                    return;
                }
            }
            AutoLockCombo.SelectedIndex = 1; // 5 Min.
        }

        private void AutoLockCheckBox_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressAutoLockEvents || _settings == null || AutoLockCombo == null) return;
            _settings.AutoLockEnabled = AutoLockCheckBox.IsChecked == true;
            AutoLockCombo.IsEnabled = _settings.AutoLockEnabled;
            _lastUserActivityUtc = DateTime.UtcNow;
            _settings.Save();
            UpdateAutoLockStatusText();
        }

        private void AutoLockCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressAutoLockEvents || _settings == null || AutoLockCombo == null) return;
            if (AutoLockCombo.SelectedItem is ComboBoxItem item && int.TryParse(item.Tag?.ToString(), out int mins))
            {
                _settings.AutoLockMinutes = mins;
                _lastUserActivityUtc = DateTime.UtcNow;
                _settings.Save();
                UpdateAutoLockStatusText();
            }
        }

        private void UpdateAutoLockStatusText()
        {
            if (StatusAutoLockText == null) return;
            if (!_settings.AutoLockEnabled)
            {
                StatusAutoLockText.Text = "Geöffnet (Auto-Sperre: Aus)";
                StatusAutoLockText.Foreground = new SolidColorBrush(Color.FromRgb(0x15, 0x80, 0x3D));
            }
            else
            {
                StatusAutoLockText.Text = $"Auto-Sperre: {_settings.AutoLockMinutes} Min.";
                StatusAutoLockText.Foreground = new SolidColorBrush(Color.FromRgb(0xB4, 0x53, 0x09));
            }
        }

        // =====================================================================
        // TIMER: FOREGROUND TRACKING, CLIPBOARD AUTO-CLEAR, TOTP & AUTO-LOCK
        // =====================================================================
        private void MainTickTimer_Tick(object? sender, EventArgs e)
        {
            // 1. 12s Clipboard Auto-Clear Countdown
            if (TresorSecurityGuard.TryGetClipboardRemainingSeconds(out double remSec, out double totalSec))
            {
                ClipboardGuardBar.Visibility = Visibility.Visible;
                ClipboardGuardLabel.Text = $"🛡 Zwischenablage wird in {Math.Ceiling(remSec):0}s automatisch geleert";
                ClipboardGuardProgress.Value = Math.Clamp((remSec / Math.Max(1.0, totalSec)) * 100.0, 0.0, 100.0);
            }
            else
            {
                if (ClipboardGuardBar.Visibility == Visibility.Visible)
                    ClipboardGuardBar.Visibility = Visibility.Collapsed;
            }

            // 2. Optionale Auto-Sperre (NUR wenn AutoLockEnabled == true!)
            if (_settings.AutoLockEnabled && _activeDb != null && GatekeeperOverlay.Visibility != Visibility.Visible && _settings.AutoLockMinutes > 0)
            {
                double idleSec = (DateTime.UtcNow - _lastUserActivityUtc).TotalSeconds;
                double limitSec = _settings.AutoLockMinutes * 60.0;
                double leftSec = limitSec - idleSec;
                if (leftSec <= 0)
                {
                    LockVault($"Tresor nach {_settings.AutoLockMinutes} Min. Inaktivität automatisch gesperrt.");
                    return;
                }
                int mins = (int)(leftSec / 60);
                int secs = (int)(leftSec % 60);
                StatusAutoLockText.Text = $"Auto-Sperre in {mins:D2}:{secs:D2}";
            }

            // 3. Live 2FA / TOTP Ticker im unteren Detailfeld
            if (_selectedEntry != null && _selectedEntry.HasTotp && BottomTotpWrap.Visibility == Visibility.Visible)
            {
                if (TotpEngine.TryGenerateTotp(_selectedEntry.TotpSecretOrUri, DateTimeOffset.UtcNow, out string code, out int rem))
                {
                    BottomTotpCode.Text = code;
                    BottomTotpCountdown.Text = $" ({rem}s)";
                }
            }

            // 4. Speicher-Feedback nach Ablauf automatisch ausblenden
            if (_saveFeedbackUntilUtc != DateTime.MinValue && DateTime.UtcNow >= _saveFeedbackUntilUtc)
            {
                _saveFeedbackUntilUtc = DateTime.MinValue;
                if (TbSaveFeedbackText != null) TbSaveFeedbackText.Visibility = Visibility.Collapsed;
                if (SaveFeedbackToast != null) SaveFeedbackToast.Visibility = Visibility.Collapsed;
            }
        }

        private void Window_UserActivity(object sender, MouseEventArgs e)
        {
            _lastUserActivityUtc = DateTime.UtcNow;
        }

        // =====================================================================
        // WORKSPACE, MULTI-DATENBANK SESSIONS & HIERARCHISCHER STRUKTURBAUM
        // =====================================================================
        private OpenedVaultSession? FindSessionForGroup(KdbxGroup? group)
        {
            if (group == null) return _activeSession;
            foreach (var s in _openSessions)
            {
                if (s.Database.FindGroupByUuid(group.UuidHex) != null)
                    return s;
            }
            return _activeSession;
        }

        private void RegisterOrSwitchDatabaseSession(KdbxDatabase db, string masterPassword, string? keyfilePath, string? selectGroupName = null)
        {
            db.RefreshHierarchyReferences();

            OpenedVaultSession? existing = null;
            if (!string.IsNullOrWhiteSpace(db.FilePath))
            {
                existing = _openSessions.FirstOrDefault(s =>
                    string.Equals(s.Database.FilePath, db.FilePath, StringComparison.OrdinalIgnoreCase));
            }

            if (existing != null)
            {
                if (!ReferenceEquals(existing.Database, db))
                    existing.Database.WipeAllInMemory();
                existing.Database = db;
                existing.MasterPassword = masterPassword;
                existing.KeyfilePath = keyfilePath;
                SwitchActiveDatabaseSession(existing, selectGroupName: selectGroupName);
            }
            else
            {
                var newSession = new OpenedVaultSession
                {
                    Database = db,
                    MasterPassword = masterPassword,
                    KeyfilePath = keyfilePath,
                    SelectedGroup = db.RootGroup
                };
                _openSessions.Add(newSession);
                SwitchActiveDatabaseSession(newSession, selectGroupName: selectGroupName);
            }
        }

        private void SwitchActiveDatabaseSession(OpenedVaultSession session, string? selectGroupName = null, string? selectGroupUuid = null)
        {
            if (_activeSession != null)
            {
                _activeSession.SelectedGroup = _selectedGroup;
                _activeSession.SelectedEntry = _selectedEntry;
            }

            _activeSession = session;
            _activeDb = session.Database;
            _activeMasterPassword = session.MasterPassword;
            _activeKeyfilePath = session.KeyfilePath;

            UpdateWindowHeaderAndBadges();
            RenderOpenDatabasesTabBar();

            string? targetUuid = selectGroupUuid ?? session.SelectedGroup?.UuidHex;
            RebuildGroupTreeView(selectGroupName: selectGroupName, selectGroupUuid: targetUuid);
            UpdateDirtyIndicator();
        }

        private void UpdateWindowHeaderAndBadges()
        {
            if (_activeDb == null)
            {
                Title = "Tresor";
                return;
            }

            string fileLabel = string.IsNullOrWhiteSpace(_activeDb.FilePath)
                ? $"{_activeDb.DatabaseName}.kdbx"
                : Path.GetFileName(_activeDb.FilePath);

            Title = _openSessions.Count > 1
                ? $"{fileLabel} (+{_openSessions.Count - 1} weitere geöffnet) - Tresor"
                : $"{fileLabel} - Tresor";

            StatusSecurityBadge.Text = _activeDb.SecurityBadgeText;
            UpgradeArgonBtn.Visibility = (!string.IsNullOrWhiteSpace(_activeDb.FilePath) && !_activeDb.IsModernArgon2Kdbx4)
                ? Visibility.Visible
                : Visibility.Collapsed;
        }

        private void RenderOpenDatabasesTabBar()
        {
            if (OpenDatabasesTabsPanel == null) return;
            OpenDatabasesTabsPanel.Children.Clear();

            foreach (var session in _openSessions)
            {
                bool isActive = ReferenceEquals(session, _activeSession);
                var tabBorder = new Border
                {
                    Background = isActive
                        ? new SolidColorBrush(Color.FromArgb(45, 197, 160, 89))
                        : (Brush)FindResource("PaneBgBrush"),
                    BorderBrush = isActive
                        ? new SolidColorBrush(Color.FromRgb(197, 160, 89))
                        : (Brush)FindResource("BorderBrushColor"),
                    BorderThickness = new Thickness(isActive ? 1.5 : 1),
                    CornerRadius = new CornerRadius(3),
                    Padding = new Thickness(8, 2, 4, 2),
                    Margin = new Thickness(0, 0, 5, 0),
                    Cursor = Cursors.Hand,
                    AllowDrop = true,
                    ToolTip = string.IsNullOrWhiteSpace(session.Database.FilePath) ? session.DisplayTitle : session.Database.FilePath
                };

                var row = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
                row.Children.Add(new TextBlock
                {
                    Text = isActive ? "🔓 " : "📂 ",
                    FontSize = 11.5,
                    VerticalAlignment = VerticalAlignment.Center
                });
                row.Children.Add(new TextBlock
                {
                    Text = session.DisplayTitle + (session.Database.IsDirty ? " *" : ""),
                    FontSize = 11.5,
                    FontWeight = isActive ? FontWeights.Bold : FontWeights.Normal,
                    Foreground = (Brush)FindResource("TextPrimaryBrush"),
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(0, 0, 6, 0)
                });

                var closeBtn = new Button
                {
                    Content = "✕",
                    Style = (Style)FindResource("ToolbarIconBtn"),
                    Width = 18,
                    Height = 18,
                    Padding = new Thickness(0),
                    FontSize = 10,
                    ToolTip = $"Datenbank „{session.DisplayTitle}“ schließen"
                };

                var capturedSession = session;
                closeBtn.Click += (s, ev) =>
                {
                    ev.Handled = true;
                    CloseSingleDatabaseSession(capturedSession);
                };

                tabBorder.MouseLeftButtonDown += (s, ev) =>
                {
                    if (ev.OriginalSource is DependencyObject dep && FindVisualAncestor<Button>(dep) == closeBtn)
                        return;
                    SwitchActiveDatabaseSession(capturedSession);
                };

                // Drag & Drop von Einträgen direkt auf einen Datenbank-Tab ermöglichen
                tabBorder.DragOver += (s, ev) =>
                {
                    if (ev.Data.GetDataPresent("FunkenwerkTresorEntries"))
                    {
                        ev.Effects = DragDropEffects.Move;
                        ev.Handled = true;
                    }
                };
                tabBorder.Drop += (s, ev) =>
                {
                    if (ev.Data.GetData("FunkenwerkTresorEntries") is List<KdbxEntry> entries && entries.Count > 0)
                    {
                        MoveEntriesToTargetGroupAndSession(entries, capturedSession.Database.RootGroup, capturedSession);
                        ev.Handled = true;
                    }
                };

                row.Children.Add(closeBtn);
                tabBorder.Child = row;
                OpenDatabasesTabsPanel.Children.Add(tabBorder);
            }
        }

        private void CloseSingleDatabaseSession(OpenedVaultSession session)
        {
            if (session.Database.IsDirty && !string.IsNullOrWhiteSpace(session.Database.FilePath) && !string.IsNullOrEmpty(session.MasterPassword))
            {
                try
                {
                    KdbxCryptoEngine.SaveKdbxFile(session.Database, session.Database.FilePath, session.MasterPassword, session.KeyfilePath);
                }
                catch { }
            }

            session.Database.WipeAllInMemory();
            _openSessions.Remove(session);

            if (_openSessions.Count == 0)
            {
                _activeSession = null;
                _activeDb = null;
                _activeMasterPassword = null;
                _selectedGroup = null;
                _selectedEntry = null;
                SetVaultLockedUiState(isLocked: true);
                ShowGatekeeperOverlay(openTab: true, preserveCurrentTreeOnCreate: false);
            }
            else if (ReferenceEquals(_activeSession, session))
            {
                SwitchActiveDatabaseSession(_openSessions[0]);
            }
            else
            {
                UpdateWindowHeaderAndBadges();
                RenderOpenDatabasesTabBar();
                RebuildGroupTreeView(selectGroupUuid: _selectedGroup?.UuidHex);
            }
        }

        private void LoadDatabaseIntoWorkspace(KdbxDatabase db, string? selectGroupName = null)
        {
            RegisterOrSwitchDatabaseSession(db, _activeMasterPassword ?? "", _activeKeyfilePath, selectGroupName);
            StatusReadyText.Text = "Ready.";
        }

        private void RebuildGroupTreeView(string? selectGroupName = null, string? selectGroupUuid = null)
        {
            if (_activeDb == null) return;

            _suppressTreeSelectionEvent = true;
            GroupTreeView.Items.Clear();

            TreeViewItem? itemToSelect = null;
            TreeViewItem? activeRootTvi = null;

            var sessionsToRender = _openSessions.Count > 0
                ? _openSessions
                : new List<OpenedVaultSession> { new OpenedVaultSession { Database = _activeDb, MasterPassword = _activeMasterPassword ?? "" } };

            TreeViewItem BuildNode(OpenedVaultSession ownerSession, KdbxGroup group, bool isRoot)
            {
                group.IsRootGroup = isRoot;
                group.CustomIconsRef = ownerSession.Database.CustomIcons;

                var headerStack = new StackPanel
                {
                    Orientation = Orientation.Horizontal,
                    Margin = new Thickness(1, 0, 4, 0)
                };

                var iconImg = new Image
                {
                    Width = 16,
                    Height = 16,
                    Source = group.IconImage,
                    Margin = new Thickness(0, 0, 5, 0)
                };
                RenderOptions.SetBitmapScalingMode(iconImg, BitmapScalingMode.HighQuality);

                string label = (isRoot && sessionsToRender.Count > 1)
                    ? $"{group.Name} ({ownerSession.DisplayTitle})"
                    : group.Name;

                var nameText = new TextBlock
                {
                    Text = label,
                    FontSize = 12,
                    FontWeight = (isRoot && ReferenceEquals(ownerSession, _activeSession) && sessionsToRender.Count > 1)
                        ? FontWeights.SemiBold
                        : FontWeights.Normal,
                    VerticalAlignment = VerticalAlignment.Center
                };

                headerStack.Children.Add(iconImg);
                headerStack.Children.Add(nameText);

                var tvi = new TreeViewItem
                {
                    Header = headerStack,
                    Tag = group,
                    IsExpanded = group.IsExpanded,
                    AllowDrop = true
                };

                tvi.Expanded += (s, ev) => { if (ReferenceEquals(s, ev.OriginalSource)) group.IsExpanded = true; };
                tvi.Collapsed += (s, ev) => { if (ReferenceEquals(s, ev.OriginalSource)) group.IsExpanded = false; };
                tvi.PreviewMouseRightButtonDown += (s, ev) =>
                {
                    tvi.IsSelected = true;
                    tvi.Focus();
                };

                if (!string.IsNullOrWhiteSpace(selectGroupUuid) && string.Equals(group.UuidHex, selectGroupUuid, StringComparison.OrdinalIgnoreCase))
                    itemToSelect = tvi;
                else if (itemToSelect == null && ReferenceEquals(ownerSession.Database, _activeDb) && !string.IsNullOrWhiteSpace(selectGroupName) && string.Equals(group.Name, selectGroupName, StringComparison.OrdinalIgnoreCase))
                    itemToSelect = tvi;

                foreach (var child in group.SubGroups)
                {
                    child.Parent = group;
                    tvi.Items.Add(BuildNode(ownerSession, child, false));
                }

                return tvi;
            }

            foreach (var session in sessionsToRender)
            {
                var rootTvi = BuildNode(session, session.Database.RootGroup, true);
                GroupTreeView.Items.Add(rootTvi);
                if (ReferenceEquals(session.Database, _activeDb))
                    activeRootTvi = rootTvi;
            }

            if (itemToSelect == null)
                itemToSelect = activeRootTvi ?? (GroupTreeView.Items.Count > 0 ? GroupTreeView.Items[0] as TreeViewItem : null);

            _suppressTreeSelectionEvent = false;

            if (itemToSelect != null)
            {
                itemToSelect.IsSelected = true;
                _selectedGroup = itemToSelect.Tag as KdbxGroup;
                if (_activeSession != null)
                    _activeSession.SelectedGroup = _selectedGroup;
            }
            RefreshEntriesTable();
        }

        private void GroupTreeView_SelectedItemChanged(object sender, RoutedPropertyChangedEventArgs<object> e)
        {
            if (_suppressTreeSelectionEvent) return;
            if (GroupTreeView.SelectedItem is TreeViewItem tvi && tvi.Tag is KdbxGroup group)
            {
                var ownerSession = FindSessionForGroup(group);
                if (ownerSession != null && !ReferenceEquals(ownerSession, _activeSession))
                {
                    _activeSession = ownerSession;
                    _activeDb = ownerSession.Database;
                    _activeMasterPassword = ownerSession.MasterPassword;
                    _activeKeyfilePath = ownerSession.KeyfilePath;
                    UpdateWindowHeaderAndBadges();
                    RenderOpenDatabasesTabBar();
                    UpdateDirtyIndicator();
                }

                _selectedGroup = group;
                if (_activeSession != null)
                    _activeSession.SelectedGroup = group;
                _showAllEntriesMode = false;
                RefreshEntriesTable();
            }
        }

        private void GroupTreeView_MouseDoubleClick(object sender, MouseButtonEventArgs e)
        {
            // Doppelklick im Strukturbaum klappt standardmäßig auf/zu; über Menü/Rechtsklick kann bearbeitet werden
        }

        private void ExpandAllGroups_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null) return;
            foreach (var g in _activeDb.GetDisplayGroups())
                g.IsExpanded = true;
            RebuildGroupTreeView(selectGroupUuid: _selectedGroup?.UuidHex);
        }

        private void CollapseAllGroups_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null) return;
            foreach (var g in _activeDb.GetDisplayGroups())
                g.IsExpanded = g.IsRootGroup;
            RebuildGroupTreeView(selectGroupUuid: _activeDb.RootGroup.UuidHex);
        }

        // =====================================================================
        // RECHTE 5-SPALTEN-TABELLE (Title | User Name | Password | URL | Notes)
        // =====================================================================
        private void RefreshEntriesTable(string? selectEntryUuid = null)
        {
            if (_activeDb == null) return;

            string query = (SearchBox.Text ?? "").Trim();
            SearchPlaceholder.Visibility = string.IsNullOrEmpty(query) ? Visibility.Visible : Visibility.Collapsed;
            ClearSearchBtn.Visibility = string.IsNullOrEmpty(query) ? Visibility.Collapsed : Visibility.Visible;

            IEnumerable<KdbxEntry> sourceEntries;
            if (!string.IsNullOrEmpty(query) || _showAllEntriesMode)
            {
                sourceEntries = _activeDb.GetAllEntriesFlat(includeRecycleBin: false);
                if (!string.IsNullOrEmpty(query))
                {
                    sourceEntries = sourceEntries.Where(e =>
                        (e.Title?.Contains(query, StringComparison.OrdinalIgnoreCase) ?? false) ||
                        (e.UserName?.Contains(query, StringComparison.OrdinalIgnoreCase) ?? false) ||
                        (e.Url?.Contains(query, StringComparison.OrdinalIgnoreCase) ?? false) ||
                        (e.Notes?.Contains(query, StringComparison.OrdinalIgnoreCase) ?? false) ||
                        (e.GroupName?.Contains(query, StringComparison.OrdinalIgnoreCase) ?? false));
                }
            }
            else if (_selectedGroup != null)
            {
                foreach (var entry in _selectedGroup.Entries)
                {
                    entry.GroupUuidHex = _selectedGroup.UuidHex;
                    entry.GroupName = _selectedGroup.Name;
                    entry.CustomIconsRef = _activeDb.CustomIcons;
                }
                sourceEntries = _selectedGroup.Entries;
            }
            else
            {
                sourceEntries = Enumerable.Empty<KdbxEntry>();
            }

            // Sortierung
            Func<KdbxEntry, string> keySelector = _sortColumn switch
            {
                "UserName" => x => x.UserName ?? "",
                "Url" => x => x.Url ?? "",
                _ => x => x.Title ?? ""
            };

            var sorted = _sortAscending
                ? sourceEntries.OrderBy(keySelector, StringComparer.OrdinalIgnoreCase).ToList()
                : sourceEntries.OrderByDescending(keySelector, StringComparer.OrdinalIgnoreCase).ToList();

            var rows = sorted.Select(e => new EntryRowViewModel
            {
                Entry = e,
                PasswordDisplay = _revealTablePasswords ? e.Password : (string.IsNullOrEmpty(e.Password) ? "" : "••••••••")
            }).ToList();

            EntriesListBox.ItemsSource = rows;
            EmptyTableText.Visibility = rows.Count == 0 ? Visibility.Visible : Visibility.Collapsed;

            if (!string.IsNullOrWhiteSpace(selectEntryUuid))
            {
                var match = rows.FirstOrDefault(r => string.Equals(r.Entry.UuidHex, selectEntryUuid, StringComparison.OrdinalIgnoreCase));
                if (match != null)
                    EntriesListBox.SelectedItem = match;
            }

            UpdateBottomPaneAndStatusBar();
        }

        private void EntriesListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            _selectedEntry = (EntriesListBox.SelectedItem as EntryRowViewModel)?.Entry;
            UpdateBottomPaneAndStatusBar();
        }

        private void EntriesListBox_MouseDoubleClick(object sender, MouseButtonEventArgs e)
        {
            if (_selectedEntry != null)
                OpenEntryEditorModal(_selectedEntry, isNew: false);
        }

        // =====================================================================
        // DRAG & DROP: EINTRÄGE (UND ORDNER) PER MAUS IN ORDNER VERSCHIEBEN
        // =====================================================================
        private static T? FindVisualAncestor<T>(DependencyObject? current) where T : DependencyObject
        {
            while (current != null)
            {
                if (current is T match)
                    return match;
                current = VisualTreeHelper.GetParent(current);
            }
            return null;
        }

        private void SetTreeDragHighlight(TreeViewItem? tvi)
        {
            if (ReferenceEquals(_dragHighlightedTreeItem, tvi))
            {
                if (tvi != null && !tvi.IsExpanded && tvi.HasItems &&
                    _dragHoverStartTimeUtc != DateTime.MinValue &&
                    (DateTime.UtcNow - _dragHoverStartTimeUtc).TotalMilliseconds > 650)
                {
                    tvi.IsExpanded = true;
                    _dragHoverStartTimeUtc = DateTime.UtcNow;
                }
                return;
            }

            ClearTreeDragHighlight();
            _dragHighlightedTreeItem = tvi;
            _dragHoverStartTimeUtc = DateTime.UtcNow;

            if (_dragHighlightedTreeItem != null)
            {
                _dragHighlightedTreeItem.Background = new SolidColorBrush(Color.FromArgb(75, 197, 160, 89));
                _dragHighlightedTreeItem.BorderBrush = new SolidColorBrush(Color.FromRgb(197, 160, 89));
            }
        }

        private void ClearTreeDragHighlight()
        {
            if (_dragHighlightedTreeItem != null)
            {
                _dragHighlightedTreeItem.ClearValue(Control.BackgroundProperty);
                _dragHighlightedTreeItem.ClearValue(Control.BorderBrushProperty);
                _dragHighlightedTreeItem = null;
            }
            _dragHoverStartTimeUtc = DateTime.MinValue;
        }

        private void EntriesListBox_PreviewMouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (e.ClickCount > 1 || FindVisualAncestor<System.Windows.Controls.Primitives.ScrollBar>(e.OriginalSource as DependencyObject) != null)
            {
                _potentialDragEntry = null;
                return;
            }

            var item = FindVisualAncestor<ListBoxItem>(e.OriginalSource as DependencyObject);
            _potentialDragEntry = (item?.DataContext as EntryRowViewModel)?.Entry;
            _entryDragStartPoint = e.GetPosition(null);
        }

        private void EntriesListBox_PreviewMouseMove(object sender, MouseEventArgs e)
        {
            if (e.LeftButton != MouseButtonState.Pressed || _potentialDragEntry == null)
                return;

            Point pos = e.GetPosition(null);
            Vector diff = _entryDragStartPoint - pos;
            if (Math.Abs(diff.X) <= SystemParameters.MinimumHorizontalDragDistance &&
                Math.Abs(diff.Y) <= SystemParameters.MinimumVerticalDragDistance)
            {
                return;
            }

            var selectedEntries = EntriesListBox.SelectedItems
                .OfType<EntryRowViewModel>()
                .Select(r => r.Entry)
                .ToList();

            List<KdbxEntry> entriesToDrag = (selectedEntries.Count > 1 && selectedEntries.Contains(_potentialDragEntry))
                ? selectedEntries
                : new List<KdbxEntry> { _potentialDragEntry };

            _potentialDragEntry = null;

            StatusReadyText.Text = entriesToDrag.Count == 1
                ? $"Ziehe „{entriesToDrag[0].Title}“ auf einen Ordner links..."
                : $"Ziehe {entriesToDrag.Count} Einträge auf einen Ordner links...";

            var dataObj = new DataObject("FunkenwerkTresorEntries", entriesToDrag);
            DragDrop.DoDragDrop(EntriesListBox, dataObj, DragDropEffects.Move);
            ClearTreeDragHighlight();
        }

        private void EntriesListBox_DragOver(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent("FunkenwerkTresorEntries"))
            {
                e.Effects = DragDropEffects.Move;
                e.Handled = true;
            }
        }

        private void EntriesListBox_Drop(object sender, DragEventArgs e)
        {
            if (_activeDb == null || _selectedGroup == null) return;
            if (e.Data.GetData("FunkenwerkTresorEntries") is not List<KdbxEntry> draggedEntries || draggedEntries.Count != 1)
                return;

            var dragged = draggedEntries[0];
            var targetItem = FindVisualAncestor<ListBoxItem>(e.OriginalSource as DependencyObject);
            var targetEntry = (targetItem?.DataContext as EntryRowViewModel)?.Entry;

            if (targetEntry == null || ReferenceEquals(dragged, targetEntry))
                return;

            int oldIdx = _selectedGroup.Entries.IndexOf(dragged);
            int newIdx = _selectedGroup.Entries.IndexOf(targetEntry);
            if (oldIdx >= 0 && newIdx >= 0 && oldIdx != newIdx)
            {
                _selectedGroup.Entries.RemoveAt(oldIdx);
                _selectedGroup.Entries.Insert(newIdx, dragged);
                _activeDb.IsDirty = true;
                RefreshEntriesTable(selectEntryUuid: dragged.UuidHex);
                AutoSaveIfPossible($"Reihenfolge von „{dragged.Title}“ angepasst.");
            }
        }

        private void GroupTreeView_PreviewMouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (e.ClickCount > 1 ||
                FindVisualAncestor<System.Windows.Controls.Primitives.ToggleButton>(e.OriginalSource as DependencyObject) != null ||
                FindVisualAncestor<System.Windows.Controls.Primitives.ScrollBar>(e.OriginalSource as DependencyObject) != null)
            {
                _potentialDragGroup = null;
                return;
            }

            var tvi = FindVisualAncestor<TreeViewItem>(e.OriginalSource as DependencyObject);
            var grp = tvi?.Tag as KdbxGroup;
            _potentialDragGroup = (grp != null && !grp.IsRootGroup) ? grp : null;
            _groupDragStartPoint = e.GetPosition(null);
        }

        private void GroupTreeView_PreviewMouseMove(object sender, MouseEventArgs e)
        {
            if (e.LeftButton != MouseButtonState.Pressed || _potentialDragGroup == null)
                return;

            Point pos = e.GetPosition(null);
            Vector diff = _groupDragStartPoint - pos;
            if (Math.Abs(diff.X) <= SystemParameters.MinimumHorizontalDragDistance &&
                Math.Abs(diff.Y) <= SystemParameters.MinimumVerticalDragDistance)
            {
                return;
            }

            var groupToDrag = _potentialDragGroup;
            _potentialDragGroup = null;

            var dataObj = new DataObject("FunkenwerkTresorGroup", groupToDrag);
            DragDrop.DoDragDrop(GroupTreeView, dataObj, DragDropEffects.Move);
            ClearTreeDragHighlight();
        }

        private static bool IsDescendantOrSameGroup(KdbxGroup candidateParent, KdbxGroup target)
        {
            KdbxGroup? cur = target;
            while (cur != null)
            {
                if (ReferenceEquals(cur, candidateParent) || string.Equals(cur.UuidHex, candidateParent.UuidHex, StringComparison.OrdinalIgnoreCase))
                    return true;
                cur = cur.Parent;
            }
            return false;
        }

        private void GroupTreeView_DragOver(object sender, DragEventArgs e)
        {
            var targetTvi = FindVisualAncestor<TreeViewItem>(e.OriginalSource as DependencyObject);
            if (targetTvi?.Tag is KdbxGroup targetGroup)
            {
                if (e.Data.GetDataPresent("FunkenwerkTresorEntries"))
                {
                    SetTreeDragHighlight(targetTvi);
                    e.Effects = DragDropEffects.Move;
                    e.Handled = true;
                    return;
                }

                if (e.Data.GetDataPresent("FunkenwerkTresorGroup") &&
                    e.Data.GetData("FunkenwerkTresorGroup") is KdbxGroup draggedGroup &&
                    !draggedGroup.IsRootGroup &&
                    !IsDescendantOrSameGroup(draggedGroup, targetGroup) &&
                    !ReferenceEquals(draggedGroup.Parent, targetGroup))
                {
                    SetTreeDragHighlight(targetTvi);
                    e.Effects = DragDropEffects.Move;
                    e.Handled = true;
                    return;
                }
            }

            ClearTreeDragHighlight();
            e.Effects = DragDropEffects.None;
            e.Handled = true;
        }

        private void GroupTreeView_DragLeave(object sender, DragEventArgs e)
        {
            if (!GroupTreeView.IsMouseOver)
                ClearTreeDragHighlight();
        }

        private void MoveEntriesToTargetGroupAndSession(List<KdbxEntry> entriesToMove, KdbxGroup targetGroup, OpenedVaultSession? targetSession = null)
        {
            if (_activeDb == null || entriesToMove.Count == 0) return;
            targetSession ??= FindSessionForGroup(targetGroup) ?? _activeSession;
            var dstDb = targetSession?.Database ?? _activeDb;

            int movedCount = 0;
            string singleTitle = entriesToMove[0].Title;

            foreach (var entry in entriesToMove)
            {
                if (string.Equals(entry.GroupUuidHex, targetGroup.UuidHex, StringComparison.OrdinalIgnoreCase) &&
                    targetGroup.Entries.Contains(entry))
                {
                    continue;
                }

                // Aus allen geöffneten Datenbanken & Gruppen entfernen (falls datenbankübergreifend verschoben wird)
                foreach (var session in _openSessions)
                {
                    bool removedFromSession = false;
                    foreach (var g in session.Database.GetDisplayGroups())
                    {
                        if (g.Entries.Remove(entry))
                            removedFromSession = true;
                    }
                    if (removedFromSession)
                        session.Database.IsDirty = true;
                }

                foreach (var g in _activeDb.GetDisplayGroups())
                    g.Entries.Remove(entry);

                entry.GroupUuidHex = targetGroup.UuidHex;
                entry.GroupName = targetGroup.Name;
                entry.CustomIconsRef = dstDb.CustomIcons;
                entry.ModifiedUtc = DateTime.UtcNow;
                targetGroup.Entries.Add(entry);
                dstDb.IsDirty = true;
                movedCount++;
            }

            if (movedCount > 0)
            {
                RefreshEntriesTable();
                RenderOpenDatabasesTabBar();
                string msg = movedCount == 1
                    ? $"Eintrag „{singleTitle}“ nach „{targetGroup.Name}“ verschoben."
                    : $"{movedCount} Einträge nach „{targetGroup.Name}“ verschoben.";
                AutoSaveIfPossible(msg);
            }
            else
            {
                StatusReadyText.Text = $"Eintrag befindet sich bereits in „{targetGroup.Name}“.";
            }
        }

        private void GroupTreeView_Drop(object sender, DragEventArgs e)
        {
            ClearTreeDragHighlight();
            if (_activeDb == null) return;

            var targetTvi = FindVisualAncestor<TreeViewItem>(e.OriginalSource as DependencyObject);
            if (targetTvi?.Tag is not KdbxGroup targetGroup)
                return;

            // 1. Einträge in Zielordner (auch über geöffnete Datenbanken hinweg) verschieben
            if (e.Data.GetDataPresent("FunkenwerkTresorEntries") &&
                e.Data.GetData("FunkenwerkTresorEntries") is List<KdbxEntry> entriesToMove &&
                entriesToMove.Count > 0)
            {
                MoveEntriesToTargetGroupAndSession(entriesToMove, targetGroup, FindSessionForGroup(targetGroup));
                e.Handled = true;
                return;
            }

            // 2. Unterordner in anderen Ordner verschieben
            if (e.Data.GetDataPresent("FunkenwerkTresorGroup") &&
                e.Data.GetData("FunkenwerkTresorGroup") is KdbxGroup draggedGroup &&
                !draggedGroup.IsRootGroup &&
                draggedGroup.Parent != null &&
                !IsDescendantOrSameGroup(draggedGroup, targetGroup) &&
                !ReferenceEquals(draggedGroup.Parent, targetGroup))
            {
                var srcSession = FindSessionForGroup(draggedGroup);
                var dstSession = FindSessionForGroup(targetGroup);

                draggedGroup.Parent.SubGroups.Remove(draggedGroup);
                draggedGroup.Parent = targetGroup;
                draggedGroup.ModifiedUtc = DateTime.UtcNow;
                targetGroup.SubGroups.Add(draggedGroup);
                targetGroup.IsExpanded = true;

                if (srcSession != null) srcSession.Database.IsDirty = true;
                if (dstSession != null) dstSession.Database.IsDirty = true;
                _activeDb.IsDirty = true;

                RebuildGroupTreeView(selectGroupUuid: draggedGroup.UuidHex);
                RenderOpenDatabasesTabBar();
                AutoSaveIfPossible($"Ordner „{draggedGroup.Name}“ nach „{targetGroup.Name}“ verschoben.");
                e.Handled = true;
            }
        }

        private void UpdateBottomPaneAndStatusBar()
        {
            int total = EntriesListBox.Items.Count;
            int sel = _selectedEntry != null ? 1 : 0;
            StatusSelectedText.Text = $"{sel} of {total} selected";

            if (_selectedEntry == null)
            {
                BottomGroupSummaryPane.Visibility = Visibility.Visible;
                BottomEntryDetailPane.Visibility = Visibility.Collapsed;
                string gName = _selectedGroup?.Name ?? "DB";
                BottomGroupNameText.Text = $"{gName}.";
            }
            else
            {
                BottomGroupSummaryPane.Visibility = Visibility.Collapsed;
                BottomEntryDetailPane.Visibility = Visibility.Visible;

                BottomEntryIcon.Source = _selectedEntry.IconImage;
                BottomEntryTitle.Text = string.IsNullOrWhiteSpace(_selectedEntry.Title) ? "(Ohne Titel)" : _selectedEntry.Title;
                BottomEntryGroupPath.Text = $" ({_selectedEntry.GroupName})";
                BottomEntryUser.Text = string.IsNullOrWhiteSpace(_selectedEntry.UserName) ? "-" : _selectedEntry.UserName;
                BottomEntryPassword.Text = _revealTablePasswords
                    ? _selectedEntry.Password
                    : (string.IsNullOrEmpty(_selectedEntry.Password) ? "-" : "••••••••");
                BottomEntryUrl.Text = string.IsNullOrWhiteSpace(_selectedEntry.Url) ? "-" : _selectedEntry.Url;
                BottomEntryNotes.Text = _selectedEntry.Notes ?? "";

                if (_selectedEntry.HasTotp && TotpEngine.TryGenerateTotp(_selectedEntry.TotpSecretOrUri, DateTimeOffset.UtcNow, out string code, out int rem))
                {
                    BottomTotpWrap.Visibility = Visibility.Visible;
                    BottomCopyTotpBtn.Visibility = Visibility.Visible;
                    BottomTotpCode.Text = code;
                    BottomTotpCountdown.Text = $" ({rem}s)";
                }
                else
                {
                    BottomTotpWrap.Visibility = Visibility.Collapsed;
                    BottomCopyTotpBtn.Visibility = Visibility.Collapsed;
                }
            }
        }

        private void SortByTitle_Click(object sender, MouseButtonEventArgs e)
        {
            ToggleSort("Title");
        }

        private void SortByUser_Click(object sender, MouseButtonEventArgs e)
        {
            ToggleSort("UserName");
        }

        private void SortByUrl_Click(object sender, MouseButtonEventArgs e)
        {
            ToggleSort("Url");
        }

        private void ToggleSort(string col)
        {
            if (_sortColumn == col)
                _sortAscending = !_sortAscending;
            else
            {
                _sortColumn = col;
                _sortAscending = true;
            }
            SortArrowText.Text = _sortAscending ? "⌃" : "⌄";
            RefreshEntriesTable(_selectedEntry?.UuidHex);
        }

        private void ToggleTablePasswords_Click(object sender, RoutedEventArgs e)
        {
            _revealTablePasswords = !_revealTablePasswords;
            TbRevealPassText.Text = _revealTablePasswords ? "🙈" : "👁";
            RefreshEntriesTable(_selectedEntry?.UuidHex);
        }

        private void SearchBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            RefreshEntriesTable(_selectedEntry?.UuidHex);
        }

        private void ClearSearch_Click(object sender, RoutedEventArgs e)
        {
            SearchBox.Text = "";
            SearchBox.Focus();
        }

        private void FocusSearch_Click(object sender, RoutedEventArgs e)
        {
            SearchBox.Focus();
            SearchBox.SelectAll();
        }

        private void ShowAllEntries_Click(object sender, RoutedEventArgs e)
        {
            _showAllEntriesMode = true;
            RefreshEntriesTable();
            StatusReadyText.Text = "Alle Einträge der Datenbank werden angezeigt.";
        }

        // =====================================================================
        // GRUPPEN-VERWALTUNG IM STRUKTURBAUM (HINZUFÜGEN / BEARBEITEN / LÖSCHEN)
        // =====================================================================
        private void AddGroup_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null) return;
            var parent = _selectedGroup ?? _activeDb.RootGroup;

            _editingGroupTarget = parent;
            _isCreatingNewSubgroup = true;
            GroupModalHeaderText.Text = $"Neue Untergruppe in „{parent.Name}“";
            GroupModalNameBox.Text = "";
            SelectIconInListBox(GroupModalIconPicker, 48);
            GroupEditorModal.Visibility = Visibility.Visible;
            GroupModalNameBox.Focus();
        }

        private void EditGroup_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null || _selectedGroup == null) return;

            _editingGroupTarget = _selectedGroup;
            _isCreatingNewSubgroup = false;
            GroupModalHeaderText.Text = $"Gruppe „{_selectedGroup.Name}“ bearbeiten";
            GroupModalNameBox.Text = _selectedGroup.Name;
            SelectIconInListBox(GroupModalIconPicker, _selectedGroup.IsRootGroup && _selectedGroup.IconId == 48 ? 1048 : _selectedGroup.IconId);
            GroupEditorModal.Visibility = Visibility.Visible;
            GroupModalNameBox.Focus();
            GroupModalNameBox.SelectAll();
        }

        private void CloseGroupModal_Click(object sender, RoutedEventArgs e)
        {
            GroupEditorModal.Visibility = Visibility.Collapsed;
        }

        private void GroupModalNameBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
                SaveGroupModal_Click(sender, e);
        }

        private void SaveGroupModal_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null || _editingGroupTarget == null) return;
            string name = (GroupModalNameBox.Text ?? "").Trim();
            if (string.IsNullOrEmpty(name))
            {
                StatusReadyText.Text = "Bitte einen Gruppennamen eingeben.";
                return;
            }

            int chosenIconId = (GroupModalIconPicker.SelectedItem as KeePassIconOption)?.IconId ?? 48;

            if (_isCreatingNewSubgroup)
            {
                var newSub = _editingGroupTarget.AddChild(name, chosenIconId, expanded: true);
                _editingGroupTarget.IsExpanded = true;
                _activeDb.IsDirty = true;
                GroupEditorModal.Visibility = Visibility.Collapsed;
                RebuildGroupTreeView(selectGroupUuid: newSub.UuidHex);
                AutoSaveIfPossible($"Untergruppe „{name}“ angelegt.");
            }
            else
            {
                _editingGroupTarget.Name = name;
                _editingGroupTarget.IconId = chosenIconId;
                _editingGroupTarget.CustomIconUuidHex = "";
                _editingGroupTarget.ModifiedUtc = DateTime.UtcNow;
                if (_editingGroupTarget.IsRootGroup)
                    _activeDb.DatabaseName = name;

                _activeDb.IsDirty = true;
                GroupEditorModal.Visibility = Visibility.Collapsed;
                RebuildGroupTreeView(selectGroupUuid: _editingGroupTarget.UuidHex);
                AutoSaveIfPossible($"Gruppe „{name}“ aktualisiert.");
            }
        }

        private void MoveGroupUp_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null || _selectedGroup?.Parent == null) return;
            var siblings = _selectedGroup.Parent.SubGroups;
            int idx = siblings.IndexOf(_selectedGroup);
            if (idx > 0)
            {
                (siblings[idx - 1], siblings[idx]) = (siblings[idx], siblings[idx - 1]);
                _activeDb.IsDirty = true;
                RebuildGroupTreeView(selectGroupUuid: _selectedGroup.UuidHex);
                AutoSaveIfPossible("Gruppe nach oben verschoben.");
            }
        }

        private void MoveGroupDown_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null || _selectedGroup?.Parent == null) return;
            var siblings = _selectedGroup.Parent.SubGroups;
            int idx = siblings.IndexOf(_selectedGroup);
            if (idx >= 0 && idx < siblings.Count - 1)
            {
                (siblings[idx + 1], siblings[idx]) = (siblings[idx], siblings[idx + 1]);
                _activeDb.IsDirty = true;
                RebuildGroupTreeView(selectGroupUuid: _selectedGroup.UuidHex);
                AutoSaveIfPossible("Gruppe nach unten verschoben.");
            }
        }

        private void DeleteGroup_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null || _selectedGroup == null) return;
            if (_selectedGroup.IsRootGroup || _selectedGroup.Parent == null)
            {
                StatusReadyText.Text = "Der Wurzelordner der Datenbank kann nicht gelöscht werden.";
                return;
            }

            var res = MessageBox.Show(
                this,
                $"Möchtest du die Gruppe „{_selectedGroup.Name}“ inklusive Untergruppen und Einträgen wirklich löschen?",
                "Gruppe löschen",
                MessageBoxButton.YesNo,
                MessageBoxImage.Warning);

            if (res != MessageBoxResult.Yes) return;

            var parent = _selectedGroup.Parent;
            parent.SubGroups.Remove(_selectedGroup);
            _activeDb.IsDirty = true;
            RebuildGroupTreeView(selectGroupUuid: parent.UuidHex);
            AutoSaveIfPossible("Gruppe gelöscht.");
        }

        // =====================================================================
        // EINTRAGS-VERWALTUNG (NEU / BEARBEITEN / DUPLIZIEREN / LÖSCHEN)
        // =====================================================================
        private void NewEntry_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null) return;
            var targetGroup = _selectedGroup ?? _activeDb.RootGroup;
            var fresh = new KdbxEntry
            {
                GroupUuidHex = targetGroup.UuidHex,
                GroupName = targetGroup.Name,
                IconId = targetGroup.IconId == 48 || targetGroup.IconId == 1048 ? 0 : targetGroup.IconId,
                CustomIconsRef = _activeDb.CustomIcons
            };
            OpenEntryEditorModal(fresh, isNew: true);
        }

        private void EditSelectedEntry_Click(object sender, RoutedEventArgs e)
        {
            if (_selectedEntry == null)
            {
                StatusReadyText.Text = "Bitte zuerst einen Eintrag in der Tabelle auswählen.";
                return;
            }
            OpenEntryEditorModal(_selectedEntry, isNew: false);
        }

        private void OpenEntryEditorModal(KdbxEntry entry, bool isNew)
        {
            if (_activeDb == null) return;
            _editingEntry = entry;
            _isCreatingNewEntry = isNew;

            EntryModalHeaderText.Text = isNew ? "Neuen Eintrag anlegen" : $"Eintrag bearbeiten: {entry.Title}";
            EditTitleBox.Text = entry.Title;
            EditUserBox.Text = entry.UserName;
            EditUrlBox.Text = entry.Url;
            EditNotesBox.Text = entry.Notes;

            _editPassEyeVisible = false;
            EditPassBox.Visibility = Visibility.Visible;
            EditPassTextBox.Visibility = Visibility.Collapsed;
            EditPassBox.Password = entry.Password;
            EditPassTextBox.Text = entry.Password;

            _editTotpEyeVisible = false;
            EditTotpBox.Visibility = Visibility.Visible;
            EditTotpTextBox.Visibility = Visibility.Collapsed;
            EditTotpBox.Password = entry.TotpSecretOrUri;
            EditTotpTextBox.Text = entry.TotpSecretOrUri;

            // Gruppen-Combo mit Pfaden befüllen
            var groupOptions = new List<GroupComboOption>();
            void BuildGroupPaths(KdbxGroup g, string prefix)
            {
                string path = string.IsNullOrEmpty(prefix) ? g.Name : $"{prefix} / {g.Name}";
                groupOptions.Add(new GroupComboOption { Group = g, DisplayPath = path });
                foreach (var sub in g.SubGroups)
                    BuildGroupPaths(sub, path);
            }
            BuildGroupPaths(_activeDb.RootGroup, "");
            EditGroupCombo.ItemsSource = groupOptions;
            EditGroupCombo.SelectedItem = groupOptions.FirstOrDefault(o => string.Equals(o.Group.UuidHex, entry.GroupUuidHex, StringComparison.OrdinalIgnoreCase))
                                       ?? groupOptions.FirstOrDefault(o => ReferenceEquals(o.Group, _selectedGroup))
                                       ?? groupOptions.FirstOrDefault();

            SelectIconInListBox(EditEntryIconPicker, entry.IconId);

            _editingAttachments = entry.Attachments.Select(a =>
            {
                byte[] copy = new byte[a.Data.Length];
                Buffer.BlockCopy(a.Data, 0, copy, 0, a.Data.Length);
                return new KdbxAttachment { FileName = a.FileName, Data = copy };
            }).ToList();
            EditAttachmentsList.ItemsSource = null;
            EditAttachmentsList.ItemsSource = _editingAttachments;

            EntryEditorModal.Visibility = Visibility.Visible;
            if (UserSuggestionsPopupBorder != null)
                UserSuggestionsPopupBorder.Visibility = Visibility.Collapsed;
            EditTitleBox.Focus();
        }

        // =====================================================================
        // PARALLELE LIVE-SUCHE & AUTOCOMPLETE FÜR BENUTZERNAMEN BEIM TIPPEN
        // =====================================================================
        private bool _suppressUserSuggestionEvents;

        private void UpdateUsernameSuggestions(string rawTyped, bool showTopWhenEmpty)
        {
            if (_suppressUserSuggestionEvents || UserSuggestionsPopupBorder == null || UserSuggestionsListBox == null)
                return;

            string query = (rawTyped ?? "").Trim();
            if (string.IsNullOrEmpty(query) && !showTopWhenEmpty)
            {
                UserSuggestionsPopupBorder.Visibility = Visibility.Collapsed;
                return;
            }

            // Alle Einträge aus allen aktuell geöffneten Datenbanken parallel durchsuchen
            var allEntries = (_openSessions.Count > 0
                    ? _openSessions.SelectMany(s => s.Database.GetAllEntriesFlat(includeRecycleBin: false))
                    : (_activeDb?.GetAllEntriesFlat(includeRecycleBin: false) ?? Enumerable.Empty<KdbxEntry>()))
                .Where(e => !string.IsNullOrWhiteSpace(e.UserName))
                .ToList();

            if (allEntries.Count == 0)
            {
                UserSuggestionsPopupBorder.Visibility = Visibility.Collapsed;
                return;
            }

            string titleHint = (EditTitleBox?.Text ?? "").Trim();

            var grouped = allEntries
                .GroupBy(e => e.UserName.Trim(), StringComparer.OrdinalIgnoreCase)
                .Select(g =>
                {
                    string userName = g.First().UserName.Trim();
                    int count = g.Count();
                    var titles = g.Select(x => x.Title)
                        .Where(t => !string.IsNullOrWhiteSpace(t))
                        .Distinct(StringComparer.OrdinalIgnoreCase)
                        .Take(2)
                        .ToList();
                    bool matchesCurrentTitle = !string.IsNullOrEmpty(titleHint) &&
                        g.Any(x => (x.Title?.Contains(titleHint, StringComparison.OrdinalIgnoreCase) ?? false) ||
                                   (x.Url?.Contains(titleHint, StringComparison.OrdinalIgnoreCase) ?? false));
                    bool startsWith = !string.IsNullOrEmpty(query) && userName.StartsWith(query, StringComparison.OrdinalIgnoreCase);
                    bool containsUser = !string.IsNullOrEmpty(query) && userName.Contains(query, StringComparison.OrdinalIgnoreCase);
                    bool matchesEntryMeta = !string.IsNullOrEmpty(query) &&
                        g.Any(x => (x.Title?.Contains(query, StringComparison.OrdinalIgnoreCase) ?? false) ||
                                   (x.Url?.Contains(query, StringComparison.OrdinalIgnoreCase) ?? false));

                    string hint = titles.Count > 0
                        ? (count > 1 ? $"{count}× ({string.Join(", ", titles)})" : $"aus „{titles[0]}“")
                        : $"{count}× vorhanden";

                    return new
                    {
                        UserName = userName,
                        Count = count,
                        Hint = hint,
                        MatchesCurrentTitle = matchesCurrentTitle,
                        StartsWith = startsWith,
                        ContainsUser = containsUser,
                        MatchesEntryMeta = matchesEntryMeta
                    };
                });

            if (!string.IsNullOrEmpty(query))
            {
                grouped = grouped.Where(x => x.StartsWith || x.ContainsUser || x.MatchesEntryMeta);
            }

            var results = grouped
                .OrderByDescending(x => x.StartsWith)
                .ThenByDescending(x => x.ContainsUser)
                .ThenByDescending(x => x.MatchesCurrentTitle)
                .ThenByDescending(x => x.Count)
                .ThenBy(x => x.UserName, StringComparer.OrdinalIgnoreCase)
                .Take(6)
                .Select(x => new UsernameSuggestionItem
                {
                    UserName = x.UserName,
                    ContextHint = x.Hint
                })
                .ToList();

            if (results.Count == 0 ||
                (results.Count == 1 && string.Equals(results[0].UserName, query, StringComparison.Ordinal)))
            {
                UserSuggestionsPopupBorder.Visibility = Visibility.Collapsed;
                return;
            }

            UserSuggestionsListBox.ItemsSource = results;
            UserSuggestionsListBox.SelectedIndex = 0;
            UserSuggestionsPopupBorder.Visibility = Visibility.Visible;
        }

        private void ApplyUsernameSuggestion(string chosenUserName)
        {
            if (string.IsNullOrWhiteSpace(chosenUserName)) return;
            _suppressUserSuggestionEvents = true;
            EditUserBox.Text = chosenUserName;
            EditUserBox.CaretIndex = chosenUserName.Length;
            UserSuggestionsPopupBorder.Visibility = Visibility.Collapsed;
            _suppressUserSuggestionEvents = false;
            EditUserBox.Focus();
        }

        private void EditUserBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (!EditUserBox.IsKeyboardFocusWithin) return;
            UpdateUsernameSuggestions(EditUserBox.Text, showTopWhenEmpty: false);
        }

        private void EditUserBox_GotFocus(object sender, RoutedEventArgs e)
        {
            if (!string.IsNullOrWhiteSpace(EditUserBox.Text))
                UpdateUsernameSuggestions(EditUserBox.Text, showTopWhenEmpty: false);
        }

        private void EditUserBox_LostFocus(object sender, RoutedEventArgs e)
        {
            Dispatcher.BeginInvoke(DispatcherPriority.Background, new Action(() =>
            {
                if (!EditUserBox.IsKeyboardFocusWithin && UserSuggestionsPopupBorder != null)
                    UserSuggestionsPopupBorder.Visibility = Visibility.Collapsed;
            }));
        }

        private void EditUserBox_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (UserSuggestionsPopupBorder == null || UserSuggestionsPopupBorder.Visibility != Visibility.Visible || UserSuggestionsListBox.Items.Count == 0)
            {
                if (e.Key == Key.Down)
                {
                    UpdateUsernameSuggestions(EditUserBox.Text, showTopWhenEmpty: true);
                    e.Handled = true;
                }
                return;
            }

            if (e.Key == Key.Down)
            {
                int next = UserSuggestionsListBox.SelectedIndex + 1;
                if (next < UserSuggestionsListBox.Items.Count)
                {
                    UserSuggestionsListBox.SelectedIndex = next;
                    UserSuggestionsListBox.ScrollIntoView(UserSuggestionsListBox.SelectedItem);
                }
                e.Handled = true;
            }
            else if (e.Key == Key.Up)
            {
                int prev = UserSuggestionsListBox.SelectedIndex - 1;
                if (prev >= 0)
                {
                    UserSuggestionsListBox.SelectedIndex = prev;
                    UserSuggestionsListBox.ScrollIntoView(UserSuggestionsListBox.SelectedItem);
                }
                e.Handled = true;
            }
            else if (e.Key == Key.Enter || e.Key == Key.Tab)
            {
                if (UserSuggestionsListBox.SelectedItem is UsernameSuggestionItem sel &&
                    !string.Equals(EditUserBox.Text, sel.UserName, StringComparison.Ordinal))
                {
                    ApplyUsernameSuggestion(sel.UserName);
                    if (e.Key == Key.Enter)
                        e.Handled = true;
                }
            }
            else if (e.Key == Key.Escape)
            {
                UserSuggestionsPopupBorder.Visibility = Visibility.Collapsed;
                e.Handled = true;
            }
        }

        private void UserSuggestionsListBox_PreviewMouseLeftButtonUp(object sender, MouseButtonEventArgs e)
        {
            var item = FindVisualAncestor<ListBoxItem>(e.OriginalSource as DependencyObject);
            if (item?.DataContext is UsernameSuggestionItem suggestion)
            {
                ApplyUsernameSuggestion(suggestion.UserName);
                e.Handled = true;
            }
        }

        private void EditNotesResizeThumb_DragDelta(object sender, System.Windows.Controls.Primitives.DragDeltaEventArgs e)
        {
            if (EditNotesBox == null) return;
            double currentH = double.IsNaN(EditNotesBox.Height) || EditNotesBox.Height <= 0
                ? EditNotesBox.ActualHeight
                : EditNotesBox.Height;
            EditNotesBox.Height = Math.Clamp(currentH + e.VerticalChange, EditNotesBox.MinHeight, EditNotesBox.MaxHeight);
        }

        private void EntryModalCornerResizeThumb_DragDelta(object sender, System.Windows.Controls.Primitives.DragDeltaEventArgs e)
        {
            if (EditNotesBox != null)
            {
                double currentH = double.IsNaN(EditNotesBox.Height) || EditNotesBox.Height <= 0
                    ? EditNotesBox.ActualHeight
                    : EditNotesBox.Height;
                EditNotesBox.Height = Math.Clamp(currentH + e.VerticalChange, EditNotesBox.MinHeight, EditNotesBox.MaxHeight);
            }

            if (EntryEditorModalCard != null)
            {
                double currentW = double.IsNaN(EntryEditorModalCard.Width) || EntryEditorModalCard.Width <= 0
                    ? EntryEditorModalCard.ActualWidth
                    : EntryEditorModalCard.Width;
                EntryEditorModalCard.Width = Math.Clamp(currentW + e.HorizontalChange, EntryEditorModalCard.MinWidth, EntryEditorModalCard.MaxWidth);
            }
        }

        private void CloseEntryModal_Click(object sender, RoutedEventArgs e)
        {
            if (UserSuggestionsPopupBorder != null)
                UserSuggestionsPopupBorder.Visibility = Visibility.Collapsed;
            EntryEditorModal.Visibility = Visibility.Collapsed;
        }

        private void SaveEntryModal_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null || _editingEntry == null) return;

            string title = (EditTitleBox.Text ?? "").Trim();
            if (string.IsNullOrEmpty(title))
            {
                StatusReadyText.Text = "Bitte einen Titel (Title) für den Eintrag eingeben.";
                EditTitleBox.Focus();
                return;
            }

            string pass = _editPassEyeVisible ? EditPassTextBox.Text : EditPassBox.Password;
            string totp = _editTotpEyeVisible ? EditTotpTextBox.Text : EditTotpBox.Password;
            var targetGroup = (EditGroupCombo.SelectedItem as GroupComboOption)?.Group ?? _selectedGroup ?? _activeDb.RootGroup;
            int iconId = (EditEntryIconPicker.SelectedItem as KeePassIconOption)?.IconId ?? 0;

            _editingEntry.Title = title;
            _editingEntry.UserName = (EditUserBox.Text ?? "").Trim();
            _editingEntry.Password = pass;
            _editingEntry.Url = (EditUrlBox.Text ?? "").Trim();
            _editingEntry.Notes = EditNotesBox.Text ?? "";
            _editingEntry.TotpSecretOrUri = totp;
            _editingEntry.IconId = iconId;
            _editingEntry.ModifiedUtc = DateTime.UtcNow;
            _editingEntry.Attachments.Clear();
            _editingEntry.Attachments.AddRange(_editingAttachments);

            if (_isCreatingNewEntry)
            {
                _editingEntry.GroupUuidHex = targetGroup.UuidHex;
                _editingEntry.GroupName = targetGroup.Name;
                targetGroup.Entries.Add(_editingEntry);
            }
            else if (!string.Equals(_editingEntry.GroupUuidHex, targetGroup.UuidHex, StringComparison.OrdinalIgnoreCase))
            {
                var oldGroup = _activeDb.FindGroupByUuid(_editingEntry.GroupUuidHex);
                oldGroup?.Entries.Remove(_editingEntry);
                _editingEntry.GroupUuidHex = targetGroup.UuidHex;
                _editingEntry.GroupName = targetGroup.Name;
                targetGroup.Entries.Add(_editingEntry);
            }

            _activeDb.IsDirty = true;
            EntryEditorModal.Visibility = Visibility.Collapsed;
            RebuildGroupTreeView(selectGroupUuid: targetGroup.UuidHex);
            RefreshEntriesTable(selectEntryUuid: _editingEntry.UuidHex);
            AutoSaveIfPossible($"Eintrag „{title}“ gespeichert.");
        }

        private void DuplicateEntry_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null || _selectedEntry == null) return;
            var group = _activeDb.FindGroupByUuid(_selectedEntry.GroupUuidHex) ?? _selectedGroup ?? _activeDb.RootGroup;
            var clone = _selectedEntry.CloneEntry(newUuid: true);
            clone.Title = _selectedEntry.Title + " (Kopie)";
            group.Entries.Add(clone);
            _activeDb.IsDirty = true;
            RefreshEntriesTable(selectEntryUuid: clone.UuidHex);
            AutoSaveIfPossible($"Eintrag „{clone.Title}“ dupliziert.");
        }

        private void DeleteEntry_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null || _selectedEntry == null) return;
            var group = _activeDb.FindGroupByUuid(_selectedEntry.GroupUuidHex) ?? _selectedGroup;
            if (group == null) return;

            string title = _selectedEntry.Title;
            group.Entries.Remove(_selectedEntry);
            _selectedEntry = null;
            _activeDb.IsDirty = true;
            RefreshEntriesTable();
            AutoSaveIfPossible($"Eintrag „{title}“ gelöscht.");
        }

        private static void SelectIconInListBox(ListBox lb, int iconId)
        {
            if (lb.ItemsSource is IEnumerable<KeePassIconOption> opts)
            {
                var match = opts.FirstOrDefault(o => o.IconId == iconId) ?? opts.FirstOrDefault();
                lb.SelectedItem = match;
                if (match != null)
                    lb.ScrollIntoView(match);
            }
        }

        // =====================================================================
        // KOPIEREN (12S CLIPBOARD GUARD), URL ÖFFNEN & AUTO-FILL
        // =====================================================================
        private void CopyUser_Click(object sender, RoutedEventArgs e)
        {
            if (_selectedEntry == null || string.IsNullOrEmpty(_selectedEntry.UserName))
            {
                StatusReadyText.Text = "Kein Benutzername zum Kopieren vorhanden.";
                return;
            }
            TresorSecurityGuard.CopySensitiveToClipboard(_selectedEntry.UserName, _settings.ClipboardClearSeconds);
            StatusReadyText.Text = $"Benutzername von „{_selectedEntry.Title}“ kopiert (12s Auto-Clear).";
        }

        private void CopyPass_Click(object sender, RoutedEventArgs e)
        {
            if (_selectedEntry == null || string.IsNullOrEmpty(_selectedEntry.Password))
            {
                StatusReadyText.Text = "Kein Passwort zum Kopieren vorhanden.";
                return;
            }
            TresorSecurityGuard.CopySensitiveToClipboard(_selectedEntry.Password, _settings.ClipboardClearSeconds);
            StatusReadyText.Text = $"Passwort von „{_selectedEntry.Title}“ kopiert (12s Auto-Clear).";
        }

        private void CopyTotp_Click(object sender, RoutedEventArgs e)
        {
            if (_selectedEntry == null || !_selectedEntry.HasTotp)
            {
                StatusReadyText.Text = "Kein 2FA / TOTP Secret für diesen Eintrag hinterlegt.";
                return;
            }
            if (TotpEngine.TryGenerateTotp(_selectedEntry.TotpSecretOrUri, DateTimeOffset.UtcNow, out string code, out _))
            {
                string rawDigits = code.Replace(" ", "");
                TresorSecurityGuard.CopySensitiveToClipboard(rawDigits, _settings.ClipboardClearSeconds);
                StatusReadyText.Text = $"2FA-Code ({code}) kopiert (12s Auto-Clear).";
            }
        }

        private void OpenUrl_Click(object sender, RoutedEventArgs e)
        {
            if (_selectedEntry == null || string.IsNullOrWhiteSpace(_selectedEntry.Url))
            {
                StatusReadyText.Text = "Keine URL für diesen Eintrag hinterlegt.";
                return;
            }
            OpenExternalUrl(_selectedEntry.Url);
        }

        private void BottomEntryUrl_Click(object sender, MouseButtonEventArgs e)
        {
            if (_selectedEntry != null && !string.IsNullOrWhiteSpace(_selectedEntry.Url))
                OpenExternalUrl(_selectedEntry.Url);
        }

        private void OpenExternalUrl(string rawUrl)
        {
            try
            {
                string url = rawUrl.Trim();
                if (!url.StartsWith("http://", StringComparison.OrdinalIgnoreCase) &&
                    !url.StartsWith("https://", StringComparison.OrdinalIgnoreCase))
                {
                    url = "https://" + url;
                }
                Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
                StatusReadyText.Text = $"URL geöffnet: {url}";
            }
            catch (Exception ex)
            {
                StatusReadyText.Text = "URL konnte nicht geöffnet werden: " + ex.Message;
            }
        }

        private async void AutoType_Click(object sender, RoutedEventArgs e)
        {
            if (_selectedEntry == null)
            {
                StatusReadyText.Text = "Bitte zuerst einen Eintrag auswählen.";
                return;
            }
            StatusReadyText.Text = "Auto-Fill läuft...";
            bool ok = await TresorSecurityGuard.PerformAutoTypeToPreviousWindowAsync(this, _selectedEntry.UserName, _selectedEntry.Password);
            StatusReadyText.Text = ok
                ? $"Auto-Fill für „{_selectedEntry.Title}“ ausgeführt."
                : "Auto-Fill konnte nicht ausgeführt werden.";
        }

        private void ClearClipboardNow_Click(object sender, RoutedEventArgs e)
        {
            TresorSecurityGuard.ClearClipboardNow();
            ClipboardGuardBar.Visibility = Visibility.Collapsed;
            StatusReadyText.Text = "Zwischenablage geleert.";
        }

        // =====================================================================
        // DATEIANHÄNGE IM EINTRAGS-MODAL
        // =====================================================================
        private void AddAttachment_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog { Title = "Datei verschlüsselt in .kdbx anhängen" };
            if (dlg.ShowDialog(this) == true)
            {
                byte[] bytes = File.ReadAllBytes(dlg.FileName);
                _editingAttachments.Add(new KdbxAttachment
                {
                    FileName = Path.GetFileName(dlg.FileName),
                    Data = bytes
                });
                EditAttachmentsList.ItemsSource = null;
                EditAttachmentsList.ItemsSource = _editingAttachments;
            }
        }

        private void SaveAttachment_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as FrameworkElement)?.DataContext is KdbxAttachment att)
            {
                var dlg = new SaveFileDialog { FileName = att.FileName };
                if (dlg.ShowDialog(this) == true)
                {
                    File.WriteAllBytes(dlg.FileName, att.Data);
                    StatusReadyText.Text = $"Anhang „{att.FileName}“ gespeichert.";
                }
            }
        }

        private void RemoveAttachment_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as FrameworkElement)?.DataContext is KdbxAttachment att)
            {
                _editingAttachments.Remove(att);
                EditAttachmentsList.ItemsSource = null;
                EditAttachmentsList.ItemsSource = _editingAttachments;
            }
        }

        // =====================================================================
        // GATEKEEPER: ÖFFNEN, ERSTELLEN, SPEICHERN & SPERREN (.KDBX)
        // =====================================================================
        private void SetVaultLockedUiState(bool isLocked)
        {
            if (isLocked)
            {
                MainMenuBorder.Visibility = Visibility.Collapsed;
                MainToolbarBorder.Visibility = Visibility.Collapsed;
                VaultWorkspaceGrid.Visibility = Visibility.Collapsed;
                MainStatusBarBorder.Visibility = Visibility.Collapsed;
                GatekeeperOverlay.Visibility = Visibility.Visible;
                GatekeeperCloseBtn.Visibility = Visibility.Collapsed;
                GroupTreeView.Items.Clear();
                EntriesListBox.ItemsSource = null;
                Title = "Tresor";
            }
            else
            {
                MainMenuBorder.Visibility = Visibility.Visible;
                MainToolbarBorder.Visibility = Visibility.Visible;
                VaultWorkspaceGrid.Visibility = Visibility.Visible;
                MainStatusBarBorder.Visibility = Visibility.Visible;
                GatekeeperOverlay.Visibility = Visibility.Collapsed;
            }
        }

        private void FocusGatekeeperPasswordInput()
        {
            if (GatekeeperOverlay.Visibility != Visibility.Visible) return;

            Dispatcher.BeginInvoke(DispatcherPriority.Input, new Action(() =>
            {
                if (GateOpenPanel.Visibility == Visibility.Visible)
                {
                    if (_openMasterEyeVisible)
                    {
                        OpenMasterPassTextBox.Focus();
                        Keyboard.Focus(OpenMasterPassTextBox);
                        OpenMasterPassTextBox.CaretIndex = OpenMasterPassTextBox.Text.Length;
                    }
                    else
                    {
                        OpenMasterPassBox.Focus();
                        Keyboard.Focus(OpenMasterPassBox);
                    }
                }
                else if (GateCreatePanel.Visibility == Visibility.Visible)
                {
                    if (_createMasterEyeVisible)
                    {
                        CreateMasterPassTextBox.Focus();
                        Keyboard.Focus(CreateMasterPassTextBox);
                    }
                    else
                    {
                        CreateMasterPassBox.Focus();
                        Keyboard.Focus(CreateMasterPassBox);
                    }
                }
            }));
        }

        private void NewDatabaseMenu_Click(object sender, RoutedEventArgs e)
        {
            ShowGatekeeperOverlay(openTab: false, preserveCurrentTreeOnCreate: false);
        }

        private void OpenDatabaseMenu_Click(object sender, RoutedEventArgs e)
        {
            ShowGatekeeperOverlay(openTab: true, preserveCurrentTreeOnCreate: false);
        }

        private void ShowGatekeeperOverlay(bool openTab, bool preserveCurrentTreeOnCreate)
        {
            _saveCurrentTreeOnCreate = preserveCurrentTreeOnCreate;
            GatekeeperErrorBorder.Visibility = Visibility.Collapsed;
            GatekeeperCloseBtn.Visibility = _activeDb != null ? Visibility.Visible : Visibility.Collapsed;

            if (openTab)
            {
                // Wenn bereits eine DB geöffnet ist und der Nutzer eine weitere öffnen möchte, automatisch die nächste noch nicht geöffnete DB vorauswählen!
                if (_openSessions.Count > 0)
                {
                    string? nextUnopened = _settings.RecentDatabases
                        .Where(File.Exists)
                        .FirstOrDefault(p => !_openSessions.Any(s => string.Equals(s.Database.FilePath, p, StringComparison.OrdinalIgnoreCase)));
                    if (!string.IsNullOrWhiteSpace(nextUnopened))
                        OpenDbPathBox.Text = nextUnopened;
                }
                else if (string.IsNullOrWhiteSpace(OpenDbPathBox.Text))
                {
                    string recent = _settings.GetMostRecentDatabasePath();
                    if (!string.IsNullOrWhiteSpace(recent))
                        OpenDbPathBox.Text = recent;
                }
            }

            RefreshRecentDatabasesButtons();

            if (openTab)
                GateTabOpen_Click(this, new RoutedEventArgs());
            else
                GateTabCreate_Click(this, new RoutedEventArgs());

            GatekeeperOverlay.Visibility = Visibility.Visible;
            FocusGatekeeperPasswordInput();
        }

        private void CloseGatekeeper_Click(object sender, RoutedEventArgs e)
        {
            // Solange noch keine Datenbank per Passwort entsperrt wurde, darf das Gatekeeper-Overlay nicht geschlossen werden!
            if (_activeDb == null)
            {
                FocusGatekeeperPasswordInput();
                return;
            }
            GatekeeperOverlay.Visibility = Visibility.Collapsed;
        }

        private void GateTabOpen_Click(object sender, RoutedEventArgs e)
        {
            GateOpenPanel.Visibility = Visibility.Visible;
            GateCreatePanel.Visibility = Visibility.Collapsed;
            GateTabOpenBtn.Style = (Style)FindResource("GoldBtn");
            GateTabCreateBtn.Style = (Style)FindResource("SecondaryBtn");
            GatekeeperErrorBorder.Visibility = Visibility.Collapsed;
            FocusGatekeeperPasswordInput();
        }

        private void GateTabCreate_Click(object sender, RoutedEventArgs e)
        {
            GateOpenPanel.Visibility = Visibility.Collapsed;
            GateCreatePanel.Visibility = Visibility.Visible;
            GateTabCreateBtn.Style = (Style)FindResource("GoldBtn");
            GateTabOpenBtn.Style = (Style)FindResource("SecondaryBtn");
            GatekeeperErrorBorder.Visibility = Visibility.Collapsed;
            FocusGatekeeperPasswordInput();
        }

        private void RefreshRecentDatabasesButtons()
        {
            RecentFilesPanel.Children.Clear();
            _settings.EnsureKnownKdbxFilesDiscovered();

            string currentSelected = (OpenDbPathBox.Text ?? "").Trim();

            foreach (string path in _settings.RecentDatabases.Where(File.Exists).Take(5))
            {
                bool isSelected = string.Equals(path, currentSelected, StringComparison.OrdinalIgnoreCase);
                bool isAlreadyOpen = _openSessions.Any(s => string.Equals(s.Database.FilePath, path, StringComparison.OrdinalIgnoreCase));
                string prefix = isSelected ? "✓ " : (isAlreadyOpen ? "🔓 " : "📄 ");
                var btn = new Button
                {
                    Content = prefix + Path.GetFileName(path) + (isAlreadyOpen ? " (Geöffnet)" : ""),
                    ToolTip = path,
                    Style = (Style)FindResource(isSelected ? "GoldBtn" : "SecondaryBtn"),
                    Padding = new Thickness(8, 3, 8, 3),
                    Margin = new Thickness(0, 0, 5, 4),
                    FontSize = 11
                };
                string captured = path;
                btn.Click += (_, _) =>
                {
                    OpenDbPathBox.Text = captured;
                    _settings.AddRecentDatabase(captured);
                    RefreshRecentDatabasesButtons();
                    FocusGatekeeperPasswordInput();
                };
                RecentFilesPanel.Children.Add(btn);
            }
        }

        private void BrowseOpenDb_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Title = "KeePass Datenbank (.kdbx) öffnen",
                Filter = "KeePass Datenbank (*.kdbx)|*.kdbx|Alle Dateien (*.*)|*.*"
            };
            if (dlg.ShowDialog(this) == true)
            {
                OpenDbPathBox.Text = dlg.FileName;
                _settings.AddRecentDatabase(dlg.FileName);
                RefreshRecentDatabasesButtons();
                FocusGatekeeperPasswordInput();
            }
        }

        private void BrowseOpenKeyfile_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Title = "Schlüsseldatei (.keyx / .key) wählen",
                Filter = "KeePass Schlüsseldatei (*.keyx;*.key)|*.keyx;*.key|Alle Dateien (*.*)|*.*"
            };
            if (dlg.ShowDialog(this) == true)
                OpenKeyfilePathBox.Text = dlg.FileName;
        }

        private void ClearOpenKeyfile_Click(object sender, RoutedEventArgs e)
        {
            OpenKeyfilePathBox.Text = "";
        }

        private void OpenMasterPassBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
                UnlockDatabase_Click(sender, e);
        }

        private void CreateMasterPassBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
                CreateDatabase_Click(sender, e);
        }

        private void UnlockDatabase_Click(object sender, RoutedEventArgs e)
        {
            GatekeeperErrorBorder.Visibility = Visibility.Collapsed;
            string dbPath = (OpenDbPathBox.Text ?? "").Trim();
            string masterPass = _openMasterEyeVisible ? OpenMasterPassTextBox.Text : OpenMasterPassBox.Password;
            string? keyfile = string.IsNullOrWhiteSpace(OpenKeyfilePathBox.Text) ? null : OpenKeyfilePathBox.Text.Trim();

            if (string.IsNullOrWhiteSpace(dbPath) || !File.Exists(dbPath))
            {
                ShowGatekeeperError("Bitte eine gültige .kdbx-Datei auswählen.");
                return;
            }

            try
            {
                Mouse.OverrideCursor = Cursors.Wait;
                var loadedDb = KdbxCryptoEngine.OpenKdbxFile(dbPath, masterPass, keyfile);

                _activeMasterPassword = masterPass;
                _activeKeyfilePath = keyfile;

                OpenMasterPassBox.Password = "";
                OpenMasterPassTextBox.Text = "";

                _settings.AddRecentDatabase(dbPath);
                _settings.LastKeyfilePath = keyfile ?? "";
                _settings.Save();

                SetVaultLockedUiState(isLocked: false);
                LoadDatabaseIntoWorkspace(loadedDb);
                StatusReadyText.Text = $"Datenbank „{Path.GetFileName(dbPath)}“ erfolgreich entsperrt.";
            }
            catch (Exception ex)
            {
                ShowGatekeeperError(ex.Message);
                FocusGatekeeperPasswordInput();
            }
            finally
            {
                Mouse.OverrideCursor = null;
            }
        }

        private void CreateDbNameBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (CreateDbPathBox == null) return;
            string name = (CreateDbNameBox.Text ?? "DB").Trim();
            foreach (char c in Path.GetInvalidFileNameChars())
                name = name.Replace(c, '_');
            if (string.IsNullOrWhiteSpace(name)) name = "DB";
            CreateDbPathBox.Text = Path.Combine(TresorAppSettings.DefaultVaultsDir, $"{name}.kdbx");
        }

        private void BrowseCreateDb_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new SaveFileDialog
            {
                Title = "Speicherort für neuen .kdbx Tresor wählen",
                Filter = "KeePass KDBX 4 Datenbank (*.kdbx)|*.kdbx",
                FileName = Path.GetFileName(CreateDbPathBox.Text)
            };
            if (dlg.ShowDialog(this) == true)
                CreateDbPathBox.Text = dlg.FileName;
        }

        private void GenerateNewKeyfile_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new SaveFileDialog
            {
                Title = "Neue 256-Bit Schlüsseldatei (.keyx) speichern",
                Filter = "KeePass 2.0 XML Schlüsseldatei (*.keyx)|*.keyx",
                FileName = "Tresor-Schluessel.keyx"
            };
            if (dlg.ShowDialog(this) == true)
            {
                KdbxCryptoEngine.GenerateKeyfileXmlV2(dlg.FileName);
                CreateKeyfilePathBox.Text = dlg.FileName;
                StatusReadyText.Text = $"Schlüsseldatei erzeugt: {dlg.FileName}";
            }
        }

        private void CreateDatabase_Click(object sender, RoutedEventArgs e)
        {
            GatekeeperErrorBorder.Visibility = Visibility.Collapsed;
            string dbName = (CreateDbNameBox.Text ?? "DB").Trim();
            string dbPath = (CreateDbPathBox.Text ?? "").Trim();
            string p1 = _createMasterEyeVisible ? CreateMasterPassTextBox.Text : CreateMasterPassBox.Password;
            string p2 = _createMasterEyeVisible ? CreateMasterRepeatTextBox.Text : CreateMasterRepeatBox.Password;
            string? keyfile = string.IsNullOrWhiteSpace(CreateKeyfilePathBox.Text) ? null : CreateKeyfilePathBox.Text.Trim();

            if (string.IsNullOrWhiteSpace(dbPath))
            {
                ShowGatekeeperError("Bitte einen Speicherpfad für die .kdbx-Datei angeben.");
                return;
            }
            if (string.IsNullOrEmpty(p1) && string.IsNullOrWhiteSpace(keyfile))
            {
                ShowGatekeeperError("Bitte ein starkes Master-Passwort vergeben.");
                return;
            }
            if (!string.Equals(p1, p2, StringComparison.Ordinal))
            {
                ShowGatekeeperError("Die beiden Master-Passwörter stimmen nicht überein.");
                return;
            }

            try
            {
                Mouse.OverrideCursor = Cursors.Wait;
                KdbxDatabase targetDb;
                if (_saveCurrentTreeOnCreate && _activeDb != null)
                {
                    targetDb = _activeDb;
                    targetDb.DatabaseName = string.IsNullOrWhiteSpace(dbName) ? "DB" : dbName;
                    targetDb.RootGroup.Name = targetDb.DatabaseName;
                }
                else
                {
                    targetDb = KdbxDatabase.CreateNewDefault(dbName);
                }

                targetDb.FilePath = dbPath;
                KdbxCryptoEngine.SaveKdbxFile(targetDb, dbPath, p1, keyfile);

                _activeMasterPassword = p1;
                _activeKeyfilePath = keyfile;

                CreateMasterPassBox.Password = "";
                CreateMasterRepeatBox.Password = "";
                CreateMasterPassTextBox.Text = "";
                CreateMasterRepeatTextBox.Text = "";

                _settings.AddRecentDatabase(dbPath);
                _settings.LastKeyfilePath = keyfile ?? "";
                _settings.Save();

                SetVaultLockedUiState(isLocked: false);
                LoadDatabaseIntoWorkspace(targetDb, selectGroupName: "Emails");
                StatusReadyText.Text = $"Tresor gespeichert unter: {dbPath}";
            }
            catch (Exception ex)
            {
                ShowGatekeeperError(ex.Message);
            }
            finally
            {
                Mouse.OverrideCursor = null;
            }
        }

        private void ShowGatekeeperError(string msg)
        {
            GatekeeperErrorText.Text = "⚠ " + msg;
            GatekeeperErrorBorder.Visibility = Visibility.Visible;
        }

        private void ShowSaveFeedback(string message)
        {
            _saveFeedbackUntilUtc = DateTime.UtcNow.AddSeconds(2.5);
            if (TbSaveFeedbackText != null)
                TbSaveFeedbackText.Visibility = Visibility.Visible;
            if (SaveFeedbackToastText != null)
                SaveFeedbackToastText.Text = message;
            if (SaveFeedbackToast != null)
                SaveFeedbackToast.Visibility = Visibility.Visible;
            if (StatusReadyText != null)
                StatusReadyText.Text = "✓ " + message;
        }

        private void SaveDatabase_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null) return;

            if (string.IsNullOrWhiteSpace(_activeDb.FilePath) || _activeMasterPassword == null)
            {
                ShowGatekeeperOverlay(openTab: false, preserveCurrentTreeOnCreate: true);
                return;
            }

            try
            {
                Mouse.OverrideCursor = Cursors.Wait;
                var sessionsToSave = _openSessions.Where(s => s.Database.IsDirty || ReferenceEquals(s.Database, _activeDb)).ToList();
                if (sessionsToSave.Count == 0 && _activeSession != null)
                    sessionsToSave.Add(_activeSession);

                foreach (var s in sessionsToSave)
                {
                    if (!string.IsNullOrWhiteSpace(s.Database.FilePath) && !string.IsNullOrEmpty(s.MasterPassword))
                        KdbxCryptoEngine.SaveKdbxFile(s.Database, s.Database.FilePath, s.MasterPassword, s.KeyfilePath);
                }

                UpdateDirtyIndicator();
                RenderOpenDatabasesTabBar();
                string fileName = Path.GetFileName(_activeDb.FilePath);
                ShowSaveFeedback(sessionsToSave.Count > 1
                    ? $"Alle geöffneten Datenbanken gespeichert ({DateTime.Now:HH:mm:ss})"
                    : $"Gespeichert in „{fileName}“ ({DateTime.Now:HH:mm:ss})");
            }
            catch (Exception ex)
            {
                MessageBox.Show(this, ex.Message, "Fehler beim Speichern", MessageBoxButton.OK, MessageBoxImage.Error);
            }
            finally
            {
                Mouse.OverrideCursor = null;
            }
        }

        private void AutoSaveIfPossible(string statusMsg)
        {
            UpdateDirtyIndicator();
            bool anySaved = false;

            foreach (var s in _openSessions.Where(x => x.Database.IsDirty))
            {
                if (!string.IsNullOrWhiteSpace(s.Database.FilePath) && !string.IsNullOrEmpty(s.MasterPassword))
                {
                    try
                    {
                        KdbxCryptoEngine.SaveKdbxFile(s.Database, s.Database.FilePath, s.MasterPassword, s.KeyfilePath);
                        anySaved = true;
                    }
                    catch { }
                }
            }

            if (!anySaved && _activeDb != null && _activeDb.IsDirty && !string.IsNullOrWhiteSpace(_activeDb.FilePath) && _activeMasterPassword != null)
            {
                try
                {
                    KdbxCryptoEngine.SaveKdbxFile(_activeDb, _activeDb.FilePath, _activeMasterPassword, _activeKeyfilePath);
                    anySaved = true;
                }
                catch { }
            }

            UpdateDirtyIndicator();
            RenderOpenDatabasesTabBar();
            if (anySaved)
            {
                ShowSaveFeedback(statusMsg + " (Gespeichert)");
                return;
            }
            StatusReadyText.Text = statusMsg;
        }

        private void UpdateDirtyIndicator()
        {
            if (DirtyStarText != null)
            {
                bool anyDirty = (_activeDb?.IsDirty == true) || _openSessions.Any(s => s.Database.IsDirty);
                DirtyStarText.Visibility = anyDirty ? Visibility.Visible : Visibility.Collapsed;
            }
        }

        private void LockVault_Click(object sender, RoutedEventArgs e)
        {
            LockVault("Tresor gesperrt.");
        }

        private void LockVault(string reason)
        {
            foreach (var s in _openSessions)
            {
                if (s.Database.IsDirty && !string.IsNullOrWhiteSpace(s.Database.FilePath) && !string.IsNullOrEmpty(s.MasterPassword))
                {
                    try
                    {
                        KdbxCryptoEngine.SaveKdbxFile(s.Database, s.Database.FilePath, s.MasterPassword, s.KeyfilePath);
                    }
                    catch { }
                }
                s.Database.WipeAllInMemory();
            }
            _openSessions.Clear();
            _activeSession = null;

            string lastFile = _activeDb?.FilePath ?? _settings.GetMostRecentDatabasePath();
            _activeDb?.WipeAllInMemory();
            _activeDb = null;
            _activeMasterPassword = null;
            _selectedGroup = null;
            _selectedEntry = null;

            TresorSecurityGuard.ClearClipboardNow();
            ClipboardGuardBar.Visibility = Visibility.Collapsed;

            EntryEditorModal.Visibility = Visibility.Collapsed;
            GroupEditorModal.Visibility = Visibility.Collapsed;
            ImportModalOverlay.Visibility = Visibility.Collapsed;
            GeneratorModalOverlay.Visibility = Visibility.Collapsed;

            SetVaultLockedUiState(isLocked: true);

            if (!string.IsNullOrWhiteSpace(lastFile) && File.Exists(lastFile))
                OpenDbPathBox.Text = lastFile;

            ShowGatekeeperOverlay(openTab: true, preserveCurrentTreeOnCreate: false);
            StatusReadyText.Text = reason;
        }

        private void UpgradeToArgon2id_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null) return;
            _activeDb.FormatMajor = 4;
            _activeDb.FormatMinor = 0;
            _activeDb.KdfAlgorithm = "Argon2id";
            _activeDb.CipherAlgorithm = "AES-256";
            _activeDb.Argon2MemoryBytes = 64UL * 1024UL * 1024UL;
            _activeDb.Argon2Iterations = 3;
            _activeDb.Argon2Parallelism = 2;
            _activeDb.IsDirty = true;

            StatusSecurityBadge.Text = _activeDb.SecurityBadgeText;
            UpgradeArgonBtn.Visibility = Visibility.Collapsed;
            AutoSaveIfPossible("Datenbank auf KDBX 4.0 · Argon2id (64 MB) gehärtet.");
        }

        // =====================================================================
        // ALTE .KDBX IMPORTIEREN / ZUSAMMENFÜHREN
        // =====================================================================
        private void OpenImportModal_Click(object sender, RoutedEventArgs e)
        {
            ImportSourcePassBox.Password = "";
            ImportSourcePassTextBox.Text = "";
            ImportModalOverlay.Visibility = Visibility.Visible;
        }

        private void CloseImportModal_Click(object sender, RoutedEventArgs e)
        {
            ImportModalOverlay.Visibility = Visibility.Collapsed;
        }

        private void BrowseImportSourceDb_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Title = "Zu importierende .kdbx Datenbank wählen",
                Filter = "KeePass Datenbank (*.kdbx)|*.kdbx|Alle Dateien (*.*)|*.*"
            };
            if (dlg.ShowDialog(this) == true)
                ImportSourceDbBox.Text = dlg.FileName;
        }

        private void BrowseImportSourceKeyfile_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Title = "Schlüsseldatei der Quell-Datenbank wählen",
                Filter = "Schlüsseldatei (*.keyx;*.key)|*.keyx;*.key|Alle Dateien (*.*)|*.*"
            };
            if (dlg.ShowDialog(this) == true)
                ImportSourceKeyfileBox.Text = dlg.FileName;
        }

        private void ExecuteImportMerge_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null) return;
            string srcPath = (ImportSourceDbBox.Text ?? "").Trim();
            string srcPass = _importPassEyeVisible ? ImportSourcePassTextBox.Text : ImportSourcePassBox.Password;
            string? srcKey = string.IsNullOrWhiteSpace(ImportSourceKeyfileBox.Text) ? null : ImportSourceKeyfileBox.Text.Trim();

            if (string.IsNullOrWhiteSpace(srcPath) || !File.Exists(srcPath))
            {
                MessageBox.Show(this, "Bitte eine gültige Quell-.kdbx-Datei auswählen.", "Import", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            try
            {
                Mouse.OverrideCursor = Cursors.Wait;
                var sourceDb = KdbxCryptoEngine.OpenKdbxFile(srcPath, srcPass, srcKey);
                var stats = _activeDb.MergeFrom(sourceDb);
                sourceDb.WipeAllInMemory();
                _activeDb.RefreshHierarchyReferences();

                ImportModalOverlay.Visibility = Visibility.Collapsed;
                RebuildGroupTreeView();
                AutoSaveIfPossible($"Import abgeschlossen: {stats.ImportedGroups} Gruppen, {stats.ImportedEntries} neue Einträge ({stats.SkippedDuplicates} Dubletten übersprungen).");
            }
            catch (Exception ex)
            {
                MessageBox.Show(this, ex.Message, "Import fehlgeschlagen", MessageBoxButton.OK, MessageBoxImage.Error);
            }
            finally
            {
                Mouse.OverrideCursor = null;
            }
        }

        // =====================================================================
        // PASSWORT-GENERATOR & SICHERHEITS-AUDIT
        // =====================================================================
        private void OpenGeneratorModal_Click(object sender, RoutedEventArgs e)
        {
            RegeneratePassword();
            GeneratorModalOverlay.Visibility = Visibility.Visible;
        }

        private void CloseGeneratorModal_Click(object sender, RoutedEventArgs e)
        {
            GeneratorModalOverlay.Visibility = Visibility.Collapsed;
        }

        private void GenOptions_Changed(object sender, RoutedEventArgs e)
        {
            if (GenLenLabel == null) return;
            GenLenLabel.Text = $"{(int)GenLengthSlider.Value} Zeichen";
            RegeneratePassword();
        }

        private void RegeneratePassword_Click(object sender, RoutedEventArgs e) => RegeneratePassword();

        private void RegeneratePassword()
        {
            if (GenOutputBox == null || GenLengthSlider == null) return;
            GenOutputBox.Text = PasswordGeneratorEngine.GeneratePassword(
                (int)GenLengthSlider.Value,
                GenUpperCheck?.IsChecked == true,
                GenLowerCheck?.IsChecked == true,
                GenDigitsCheck?.IsChecked == true,
                GenSymbolsCheck?.IsChecked == true);
        }

        private void GeneratePassphraseBtn_Click(object sender, RoutedEventArgs e)
        {
            GenOutputBox.Text = PasswordGeneratorEngine.GeneratePassphrase(4);
        }

        private void CopyGeneratedPassword_Click(object sender, RoutedEventArgs e)
        {
            if (!string.IsNullOrEmpty(GenOutputBox.Text))
            {
                TresorSecurityGuard.CopySensitiveToClipboard(GenOutputBox.Text, _settings.ClipboardClearSeconds);
                StatusReadyText.Text = "Generiertes Passwort kopiert (12s Auto-Clear).";
            }
        }

        private void GenerateInlinePassword_Click(object sender, RoutedEventArgs e)
        {
            string pwd = PasswordGeneratorEngine.GeneratePassword(20, true, true, true, true);
            EditPassBox.Password = pwd;
            EditPassTextBox.Text = pwd;
        }

        private void OpenAuditModal_Click(object sender, RoutedEventArgs e)
        {
            if (_activeDb == null) return;
            var all = _activeDb.GetAllEntriesFlat(includeRecycleBin: false);
            var issues = PasswordGeneratorEngine.AuditEntries(all);
            int weakCount = issues.Count(i => i.Severity == "Kritisch");
            int dupCount = issues.Count(i => i.Severity == "Warnung");

            MessageBox.Show(
                this,
                $"Offline Sicherheits-Audit für „{_activeDb.DatabaseName}“:\n\n" +
                $"• Geprüfte Einträge: {all.Count}\n" +
                $"• Schwache Passwörter (< 12 Zeichen / < 50 Bit): {weakCount}\n" +
                $"• Doppelt verwendete Passwörter: {dupCount}",
                "Sicherheits-Audit",
                MessageBoxButton.OK,
                issues.Count == 0 ? MessageBoxImage.Information : MessageBoxImage.Warning);
        }

        private void MenuScreenShield_Click(object sender, RoutedEventArgs e)
        {
            _settings.ScreenCaptureProtection = MenuScreenShield.IsChecked;
            _settings.Save();
            TresorSecurityGuard.ApplyScreenCaptureProtection(this, _settings.ScreenCaptureProtection);
            StatusReadyText.Text = _settings.ScreenCaptureProtection
                ? "Screenshot-Schutz aktiviert."
                : "Screenshot-Schutz deaktiviert.";
        }

        // =====================================================================
        // PASSWORT-AUGE TOGGLES (👁)
        // =====================================================================
        private void ToggleOpenMasterEye_Click(object sender, RoutedEventArgs e)
        {
            _openMasterEyeVisible = !_openMasterEyeVisible;
            if (_openMasterEyeVisible)
            {
                OpenMasterPassTextBox.Text = OpenMasterPassBox.Password;
                OpenMasterPassBox.Visibility = Visibility.Collapsed;
                OpenMasterPassTextBox.Visibility = Visibility.Visible;
            }
            else
            {
                OpenMasterPassBox.Password = OpenMasterPassTextBox.Text;
                OpenMasterPassTextBox.Visibility = Visibility.Collapsed;
                OpenMasterPassBox.Visibility = Visibility.Visible;
            }
        }

        private void ToggleCreateMasterEye_Click(object sender, RoutedEventArgs e)
        {
            _createMasterEyeVisible = !_createMasterEyeVisible;
            if (_createMasterEyeVisible)
            {
                CreateMasterPassTextBox.Text = CreateMasterPassBox.Password;
                CreateMasterRepeatTextBox.Text = CreateMasterRepeatBox.Password;
                CreateMasterPassBox.Visibility = Visibility.Collapsed;
                CreateMasterRepeatBox.Visibility = Visibility.Collapsed;
                CreateMasterPassTextBox.Visibility = Visibility.Visible;
                CreateMasterRepeatTextBox.Visibility = Visibility.Visible;
            }
            else
            {
                CreateMasterPassBox.Password = CreateMasterPassTextBox.Text;
                CreateMasterRepeatBox.Password = CreateMasterRepeatTextBox.Text;
                CreateMasterPassTextBox.Visibility = Visibility.Collapsed;
                CreateMasterRepeatTextBox.Visibility = Visibility.Collapsed;
                CreateMasterPassBox.Visibility = Visibility.Visible;
                CreateMasterRepeatBox.Visibility = Visibility.Visible;
            }
        }

        private void ToggleEditPassEye_Click(object sender, RoutedEventArgs e)
        {
            _editPassEyeVisible = !_editPassEyeVisible;
            if (_editPassEyeVisible)
            {
                EditPassTextBox.Text = EditPassBox.Password;
                EditPassBox.Visibility = Visibility.Collapsed;
                EditPassTextBox.Visibility = Visibility.Visible;
            }
            else
            {
                EditPassBox.Password = EditPassTextBox.Text;
                EditPassTextBox.Visibility = Visibility.Collapsed;
                EditPassBox.Visibility = Visibility.Visible;
            }
        }

        private void ToggleEditTotpEye_Click(object sender, RoutedEventArgs e)
        {
            _editTotpEyeVisible = !_editTotpEyeVisible;
            if (_editTotpEyeVisible)
            {
                EditTotpTextBox.Text = EditTotpBox.Password;
                EditTotpBox.Visibility = Visibility.Collapsed;
                EditTotpTextBox.Visibility = Visibility.Visible;
            }
            else
            {
                EditTotpBox.Password = EditTotpTextBox.Text;
                EditTotpTextBox.Visibility = Visibility.Collapsed;
                EditTotpBox.Visibility = Visibility.Visible;
            }
        }

        private void ToggleImportPassEye_Click(object sender, RoutedEventArgs e)
        {
            _importPassEyeVisible = !_importPassEyeVisible;
            if (_importPassEyeVisible)
            {
                ImportSourcePassTextBox.Text = ImportSourcePassBox.Password;
                ImportSourcePassBox.Visibility = Visibility.Collapsed;
                ImportSourcePassTextBox.Visibility = Visibility.Visible;
            }
            else
            {
                ImportSourcePassBox.Password = ImportSourcePassTextBox.Text;
                ImportSourcePassTextBox.Visibility = Visibility.Collapsed;
                ImportSourcePassBox.Visibility = Visibility.Visible;
            }
        }

        // =====================================================================
        // TASTATUR-SHORTCUTS & BEENDEN
        // =====================================================================
        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            _lastUserActivityUtc = DateTime.UtcNow;

            if (GatekeeperOverlay.Visibility == Visibility.Visible)
            {
                if (e.Key == Key.Escape)
                {
                    CloseGatekeeper_Click(sender, e);
                    e.Handled = true;
                }
                return;
            }

            if (Keyboard.Modifiers == ModifierKeys.Control)
            {
                if (e.Key == Key.S) { SaveDatabase_Click(sender, e); e.Handled = true; return; }
                if (e.Key == Key.L) { LockVault_Click(sender, e); e.Handled = true; return; }
                if (e.Key == Key.I || e.Key == Key.N) { NewEntry_Click(sender, e); e.Handled = true; return; }
                if (e.Key == Key.F) { FocusSearch_Click(sender, e); e.Handled = true; return; }
                if (e.Key == Key.O) { OpenDatabaseMenu_Click(sender, e); e.Handled = true; return; }
                if (e.Key == Key.B && !(Keyboard.FocusedElement is TextBox) && !(Keyboard.FocusedElement is PasswordBox))
                {
                    CopyUser_Click(sender, e);
                    e.Handled = true;
                    return;
                }
                if (e.Key == Key.C && !(Keyboard.FocusedElement is TextBox) && !(Keyboard.FocusedElement is PasswordBox))
                {
                    CopyPass_Click(sender, e);
                    e.Handled = true;
                    return;
                }
                if (e.Key == Key.U && !(Keyboard.FocusedElement is TextBox) && !(Keyboard.FocusedElement is PasswordBox))
                {
                    OpenUrl_Click(sender, e);
                    e.Handled = true;
                    return;
                }
            }
            else if (Keyboard.Modifiers == ModifierKeys.None)
            {
                if (e.Key == Key.Escape)
                {
                    if (UserSuggestionsPopupBorder != null && UserSuggestionsPopupBorder.Visibility == Visibility.Visible)
                    {
                        UserSuggestionsPopupBorder.Visibility = Visibility.Collapsed;
                        e.Handled = true;
                        return;
                    }
                    EntryEditorModal.Visibility = Visibility.Collapsed;
                    GroupEditorModal.Visibility = Visibility.Collapsed;
                    ImportModalOverlay.Visibility = Visibility.Collapsed;
                    GeneratorModalOverlay.Visibility = Visibility.Collapsed;
                }
                else if (e.Key == Key.Enter && EntriesListBox.IsKeyboardFocusWithin && _selectedEntry != null)
                {
                    EditSelectedEntry_Click(sender, e);
                    e.Handled = true;
                }
                else if (e.Key == Key.Delete && EntriesListBox.IsKeyboardFocusWithin && _selectedEntry != null)
                {
                    DeleteEntry_Click(sender, e);
                    e.Handled = true;
                }
            }
        }

        private void ExitApp_Click(object sender, RoutedEventArgs e)
        {
            Close();
        }

        private void Window_Closing(object? sender, System.ComponentModel.CancelEventArgs e)
        {
            SystemEvents.SessionSwitch -= SystemEvents_SessionSwitch;
            foreach (var s in _openSessions)
            {
                if (s.Database.IsDirty && !string.IsNullOrWhiteSpace(s.Database.FilePath) && !string.IsNullOrEmpty(s.MasterPassword))
                {
                    try
                    {
                        KdbxCryptoEngine.SaveKdbxFile(s.Database, s.Database.FilePath, s.MasterPassword, s.KeyfilePath);
                    }
                    catch { }
                }
                s.Database.WipeAllInMemory();
            }
            _openSessions.Clear();

            if (_activeDb != null && _activeDb.IsDirty && !string.IsNullOrWhiteSpace(_activeDb.FilePath) && _activeMasterPassword != null)
            {
                try
                {
                    KdbxCryptoEngine.SaveKdbxFile(_activeDb, _activeDb.FilePath, _activeMasterPassword, _activeKeyfilePath);
                }
                catch { }
            }
            _activeDb?.WipeAllInMemory();
            TresorSecurityGuard.ClearClipboardNow();
        }
    }
}
